# Faz 6B3 — Apply, Batch, Checkpoint, Audit ve Rollback Mimarisi

> Tarih: 24 Eylül 2026.
>
> **Durum:**
> - Bu tur, katalog içe aktarımının gerçek yazma altyapısını **kodladı** ve yalnız yerel, silinebilir test ortamında doğruladı.
> - Gerçek staging'de veya canlıda **apply çalıştırılmadı**. Gerçek 14/83/103 katalog manifesti hiçbir ortama **yazılmadı**.
> - Gerçek apply ayrıca kullanıcı onayı gerektirir; açık operasyonel önkoşullar §11'dedir.
> - Claude bu çalışmaya nihai kabul vermez; teslim Codex bağımsız incelemesine sunulur.

## 1. Bileşenler ve sorumluluklar

| Dosya (`mavibelge-core/includes/import/`) | Tür | Sorumluluk |
|---|---|---|
| `interface-import-target-writer.php` | arayüz | Dar yazma sözleşmesi: sektör/post create/update, `trash_post`, `delete_sector_term`, yönetilmeyen alan parmak izi, terim referans taraması |
| `interface-import-transaction.php` | arayüz | `preflight` / `begin` / `commit` / `rollback` (sonuçlar kontrol edilir) |
| `interface-import-run-store.php` | arayüz | Run/checkpoint deposu (WP-CLI ve ileride admin fallback AYNI format) |
| `interface-import-audit-sink.php` | arayüz | Audit hedefi; yazılamazsa `false` |
| `class-import-run-state.php` | saf | Kapalı run durum makinesi |
| `class-import-apply-plan.php` | saf | Aşama (stage) modeli, batch sabitleri, plan/manifest/rollback özetleri, yazma ve rollback sırası |
| `class-import-rollback-codec.php` | saf | Rollback kaydının JSON temsili; yazmadan önce ve okuduktan sonra tek doğrulayıcı |
| `class-import-audit-context.php` | saf | Audit context için kapalı izin listesi |
| `class-import-apply-service.php` | servis | Apply akışı (WordPress fonksiyonu çağırmaz) |
| `class-import-rollback-service.php` | servis | Rollback akışı (WordPress fonksiyonu çağırmaz) |
| `class-import-wordpress-target-writer.php` | WP | Yazma adapterı (yalnız WordPress API'leri) |
| `class-import-wpdb-transaction.php` | WP | `START TRANSACTION`/`COMMIT`/`ROLLBACK` + InnoDB/utf8mb4 ön kontrolü |
| `class-import-wpdb-run-store.php` | WP | İki run tablosu (dbDelta, sürümlü) + `GET_LOCK` kilidi |
| `class-import-wp-audit-sink.php` | WP | Mevcut `MaviBelge_Core_Audit_Log` üzerine import olayları |

Değişen mevcut dosyalar:
- `class-import-dry-run-planner.php`: `plan_*()` gövdeleri tek `prepare_projection()` yoluna toplandı; aynı yoldan `project_for_apply()` eklendi. Plan davranışı değişmedi (809/809 test, 52/52 senaryo).
- `class-import-dry-run-service.php`: `run_stage()`, `observe_record()`, `current_manifest_digest()`; yalnız testler için isteğe bağlı manifest dizini. `run_dry_run()` aynı çıktıyı üretir.
- `class-import-cli-command.php`: `--apply` (varsayılan kapalı), `rollback`, `status`.
- `class-import-write-payload.php`: `MANAGED_POST_META` sabiti.
- `audit/class-audit-log.php`: yedi import olay sabiti.

**Salt okunur `MaviBelge_Core_Import_Target_Repository` arayüzü değiştirilmedi.**

**Tek kaynak ilkesi.** İkinci bir karar veya doğrulama sistemi yazılmadı:
- Plan, TOCTOU ve readback aynı yoldan gözlenir: `Dry_Run_Service` → repository → `Dry_Run_Planner` → `Decision`.
- Uygunluk: `Apply_Eligibility::evaluate_plan()`.
- TOCTOU: `toctou_recheck()`.
- Rollback kaydı: `build_rollback_record()` ve `validate_rollback_record()`.
- Atomik yük: `Write_Payload::prepare()`.
- Hash: `Import_Hash`.
- Yönetilen alanlar: `Managed_Fields`.

## 2. Aşama (stage) modeli — neden gerekli

Boş bir hedefte tam katalog planı `applicable === true` olamaz:
- Yeterlilikler var olan bir `mb_sektor` terimine, kodlu ücretler var olan bir `mb_yeterlilik` postuna bağlıdır.
- Bu terimler/postlar henüz yoksa ilgili kayıtlar `blocked_dependency` olur.

Plan kapısı gevşetilmedi. Bunun yerine manifest bağımlılık sırasına göre kapalı öneklere bölündü:

| Aşama | Kapsanan türler | Koşul |
|---|---|---|
| `sectors` | sektör | Plan tamamen uygulanabilir olmalı |
| `qualifications` | sektör + yeterlilik | Sektörler `unchanged` olmalı |
| `all` | tam katalog | Sektörler ve yeterlilikler `unchanged` olmalı |

- Her aşamanın planı gerçek planlayıcının tam planıdır ve kendi başına `summary.applicable === true` olmak zorundadır.
- Bir bağımlılık çözülmeden sonraki kayıt yazılamaz.
- Yeterlilik–sektör ve ücret–yeterlilik ilişkilerinde yalnız doğrulanmış pozitif ID (`{id, type_verified: true}`) kullanılır.
- 19 kodsuz ücret için `_mb_qualification_id = 0` korunur.
- Görselli bir sektör, onaylı görsel eşlemesi olmadan her aşamada `blocked_dependency` kalır. Bu nedenle gerçek manifestin `sectors` aşaması bile bugün uygulanamaz (§11).

## 3. Apply güvenlik kapısı (sıra bağlayıcı)

Biri bile geçmezse hiçbir yazma yapılmaz ve run oluşturulmaz. Sıra statik testle sabitlenmiştir.

1. **Açık anahtar:** `wp-config.php` içinde `MAVIBELGE_IMPORT_APPLY_ENABLED === true` (WP-CLI). Varsayılan kapalıdır.
2. **Yetki:** `--user=` ile giriş yapmış ve `manage_options` VE `mb_manage_tariff_period` yetkisi olan kullanıcı (WP-CLI).
3. **Argümanlar:** geçerli aşama, batch boyutu 1..20 (varsayılan 20, tek merkezi sabit), 64-hex `--confirm`.
4. **Plan onayı:** Aşama planı yeniden üretilir. `plan_digest`, kullanıcının dry-run çıktısında gördüğü özete birebir eşit olmalıdır. Plan ya da manifest onaydan sonra değiştiyse `confirmation_mismatch` döner.
5. **`Apply_Eligibility::evaluate_plan()`:**
   - `summary.applicable === true`, plan hatası yok;
   - tek bir conflict/duplicate/blocked/invalid kayıt yok;
   - her create `natural_key_check === 'none'` taşır;
   - update'te üç-hash koşulu sağlanır;
   - `source_key` tekrarı yok.
6. **Yük hazırlığı:**
   - Her yazma kaydının yönetilen alanları planlayıcıyla aynı yoldan (`project_for_apply`) üretilir.
   - Hash'i plandaki `incoming_hash`'e eşit olmalı ve `Write_Payload::prepare()` atomik yükü üretmelidir.
7. **Altyapı:** Run tabloları kurulu (idempotent) olmalı. Transaction ön kontrolü yazılabilecek her tablonun InnoDB ve utf8mb4 olduğunu, bağlantının utf8mb4 olduğunu doğrular. Audit tablosu hazır olmalı.
8. **Eşzamanlılık:** `GET_LOCK` alınır. Bayat (süreci ölmüş) `running`/`rolling_back` run'lar işaretlenir. Çözülmemiş (`running`/`rolling_back`/`rollback_required`) bir run varsa apply reddedilir.

Yazılacak kayıt yoksa (her şey `unchanged`) sonuç `noop` olur: run, item, audit ve yazma yoktur.

## 4. Batch, transaction ve hata politikası

- **Plan düzeyi:** Tek bir uygunsuz kayıt varsa hiçbir batch başlamaz.
- **Batch düzeyi:** Yazma sırası sektör → yeterlilik → ücret. Sabit boyutlu batch'ler vardır ve her batch kendi `START TRANSACTION`/`COMMIT`'i içinde atomiktir. Her kayıt için sırasıyla:
  1. **TOCTOU:** Kayıt planlamayla aynı yoldan yeniden gözlenir. Karar aynı olmalı ve `toctou_recheck()` geçmelidir. Update'te eski yönetilen alanların hash'i plandaki `current_hash`'e eşit olmalıdır.
  2. **Yazma:** Yalnız yükteki (yönetilen) alanlar yazılır; her değer `wp_slash()` ile verilir.
  3. **Katı readback:**
     - Her meta ve terim alanı saklanan temsilden geri okunur. `update_*_meta()`'nın `false` dönüşü "değer zaten aynıydı" da olabileceğinden tek başına hata sayılmaz; hata yalnız okunan değer beklenen kanonik değere eşit değilse sayılır.
     - Kayıt tek karar motoruyla yeniden gözlenir ve artık `unchanged` olmalıdır: `incoming === current === last`, yönetilen alanlar katı olarak eşit.
  4. **Yönetilmeyen alan koruması (update):** `post_content`, özet, durum, slug, diğer meta (SEO/üçüncü taraf), diğer taksonomi terimleri ve terim meta'sının parmak izi yazmadan önce ve sonra eşit olmalıdır.
  5. **Rollback kaydı:** Kapalı rollback kaydı kurulur, kodekten geçer ve run item olarak aynı transaction içinde saklanır.
  6. **Batch sonu:** `import_batch_committed` audit kaydı ve checkpoint sayaçları aynı transaction içinde yazılır. Ardından `COMMIT` sonucu kontrol edilir.
- **Runtime hatası:** O batch `ROLLBACK` ile tamamen geri alınır (ROLLBACK sonucu da kontrol edilir; nesne önbelleği temizlenir). Sonra run transaction dışında işaretlenir:
  - commit edilmiş batch yoksa `failed`;
  - varsa `rollback_required`.
  - Ayrı ve doğrulanmış bir `import_run_failed` audit kaydı yazılır.
- Önceden commit edilmiş batch'ler kaybolmuş sayılmaz; yalnız doğrulanmış rollback kayıtlarıyla geri alınabilir.
- HTTP istekleri veya CLI süreçleri arasında küresel transaction iddia edilmez.
- Batch'ler arasında manifest özeti yeniden okunur; değiştiyse `manifest_changed` döner.
- **Veritabanı dışı yan etkiler:** WordPress hook'larının veritabanı dışı yan etkileri (dosya, e-posta, dış HTTP, kalıcı nesne önbelleği) transaction ile geri alınamaz. Bu faz medya oluşturmaz, e-posta göndermez, dış servis çağırmaz ve `mb_active_tariff_period` seçeneğine dokunmaz. Oluşturulan postlar `draft` durumundadır.
- **Hata kodları:** Sabit ve içerik taşımaz: `invalid_stage`, `invalid_batch_size`, `invalid_confirmation`, `confirmation_mismatch`, `plan_not_applicable`, `payload_invalid`, `infrastructure_unavailable`, `locked`, `unresolved_run_exists`, `audit_failed`, `transaction_begin_failed`, `toctou_drift`, `write_failed`, `readback_mismatch`, `unmanaged_field_changed`, `rollback_record_invalid`, `item_store_failed`, `checkpoint_failed`, `commit_failed`, `transaction_rollback_failed`, `manifest_changed`, `unexpected_exception`, `stale_run`.

## 5. Run / checkpoint şeması

Mevcut audit tablosu rollback deposu olarak kullanılmadı; iki ayrı tablo eklendi.

- Kurulum `dbDelta` ile idempotent ve sürümlüdür. Sürüm seçeneği `mavibelge_core_import_tables_version` yalnız iki tablo gerçekten oluştuktan sonra yazılır.
- Kurulum yalnız apply yolunda, bütün plan kapıları geçtikten sonra yapılır. Dry-run ve `status` tablo oluşturmaz (runtime'da doğrulandı).
- JSON sütun tipi kullanılmaz; kayıtlar LONGTEXT + uygulama düzeyi JSON'dur. Bozuk JSON, eksik/fazla anahtar, alanlarla uyuşmayan hash veya yanlış `changed_fields` fail-closed reddedilir.

`{prefix}mb_import_runs`:

| Sütun | Açıklama |
|---|---|
| `id`, `uid` | `uid` = `bin2hex(random_bytes(16))`; tahmin edilemez ama YETKİ anahtarı olarak kullanılmaz (yetki `current_user_can`) |
| `stage`, `status` | Aşama ve durum (§5.1) |
| `plan_digest`, `manifest_digest` | Onaylanan plan ve manifest özeti |
| `batch_size`, `total_writes`, `committed_batches`, `committed_items` | Checkpoint sayaçları |
| `error_code` | Sabit hata kodu veya NULL |
| `created_by`, `created_at`, `updated_at` | Kullanıcı ID'si ve UTC zaman |

`{prefix}mb_import_run_items`:

| Sütun | Açıklama |
|---|---|
| `run_id`, `seq`, `batch_no` | (run_id, seq) benzersiz |
| `source_key`, `type`, `decision`, `target_id` | Kayıt kimliği; rollback kaydıyla birebir eşleşmeli |
| `rollback_record` | Kapalı rollback kaydı (`mavibelge-import-rollback/1`, LONGTEXT JSON) — yalnız yönetilen alanlar |
| `rollback_status` | `pending` / `rolled_back` |

Gizli bilgi veya kişisel veri saklanmaz. Run item'ları batch transaction'ı içinde yazıldığından başarısız batch'in item'ı kalmaz (runtime'da doğrulandı).

### 5.1 Durum makinesi (kapalı)

```text
planned -> running | failed
running -> completed | failed | rollback_required
completed | rollback_required | rollback_failed -> rolling_back
rolling_back -> rolled_back | rollback_failed
failed, rolled_back: terminal
```

- Geçişler depoda karşılaştır-ve-değiştir (compare-and-set) ile uygulanır: `UPDATE ... WHERE id AND status = <from>`.
- Kilit alındığında etkin durumda kalmış run bayattır:
  - `running`: commit edilmiş item'ı varsa `rollback_required`, yoksa `failed`;
  - `rolling_back` → `rollback_failed`.
  - Hata kodu `stale_run` olarak kaydedilir.

## 6. Audit sözleşmesi

Olaylar: `import_run_started`, `import_batch_committed`, `import_run_completed`, `import_run_failed`, `import_rollback_started`, `import_rollback_completed`, `import_rollback_failed`. `object_type` = `mb_import_run`, `object_id` = run ID'si.

**Context kapalı izin listesinden geçer.** Taşıyabileceği alanlar:
- `run_id`, `batch_no`, `checkpoint`, `error_code`;
- `source_key`, `type`, `decision`, `target_id`, `old_hash`, `new_hash`, `changed_fields` (yalnız ALAN ADLARI);
- en fazla 20 elemanlı, yalnız bu alanlardan oluşan `items` listesi.

Başka her anahtar veya biçimsiz değer context'in tamamını reddeder ve kayıt yazılmaz. Alan içeriği, parola, nonce, çerez, kişisel veri ve dosya içeriği yapısal olarak taşınamaz.

**Transaction ile ilişki:**
- `import_batch_committed` batch transaction'ının içinde yazılır. Batch geri alınırsa onun audit kaydı da geri alınır.
- `import_run_failed` batch geri alındıktan sonra ayrı yazılır.
- `unchanged` kayıtlar audit üretmez.

**Audit yazılamazsa işlem başarılı sayılmaz:**
- başlangıç kaydı yazılamazsa run `failed` olur;
- batch kaydı yazılamazsa batch geri alınır;
- tamamlanma kaydı yazılamazsa run `rollback_required` olur.

## 7. Rollback prosedürü

1. **Önizleme** (`wp mavibelge import rollback --run-id=<uid>`), salt okunur:
   - bekleyen item'lar, engeller ve onaylanacak `rollback_digest` gösterilir;
   - her rollback kaydı tek doğrulayıcıdan geçer ve item sütunlarıyla birebir eşleşmelidir.
2. **Yürütme** (`--confirm=<rollback_digest>`, apply anahtarı ve yetki gerekir):
   - Önce bütün bekleyen item'lar salt okunur ön kontrolden geçer: hedef bulunmalı, tür ve ID eşleşmeli, `last_applied_hash === new_hash` olmalı ve alanlardan yeniden hesaplanan mevcut hash `new_hash`'e eşit olmalıdır.
   - Arada kullanıcı değişikliği varsa (drift) HİÇBİR şey yazılmaz, run `rollback_failed` (`drift_detected`) olur ve kullanıcı değişikliği korunur.
3. **Sıra:** ücret → yeterlilik → sektör; tür içinde ters seq. Batch'ler transaction içindedir.
4. **update:** yalnız `old_fields` (yönetilen alanlar) ve eski marker/hash (`old_hash`) geri yazılır. Katı readback ve yönetilmeyen alan parmak izi karşılaştırması yapılır.
5. **create (post):** `wp_trash_post()` ile çöp kutusuna alınır, kalıcı silinmez. `EMPTY_TRASH_DAYS = 0` ise WordPress kalıcı sileceği için rollback fail-closed reddeder.
6. **create (sektör terimi):** WordPress'te terimlerin çöp kutusu yoktur; terim silme geri alınamaz. Terim yalnız şu koşulların tamamı sağlanırsa `wp_delete_term()` ile silinir:
   - bu run oluşturmuş olmalı;
   - hash hâlâ eşleşmeli;
   - alt terimi olmamalı;
   - terime bağlı veya `_mb_sector_slug` ile ona işaret eden her post çöp kutusunda olmalı VE ya bu run'ın ya da rollback'i tamamlanmış başka bir import create'inin postu olmalı.

   Aksi hâlde terim silinmez; rollback `rollback_failed` (`term_has_external_dependents`) olur ve manuel inceleme gerekir. Bu yüzden katalog rollback'i run'ların tersi sırasıyla yapılır: `all` → `qualifications` → `sectors`.
7. **Kısmi rollback açıkça hatadır:**
   - geri alınan item'lar `rolled_back` olarak işaretlenir;
   - run `rollback_failed` olur;
   - koşullar düzeltildikten sonra yeniden deneme yalnız kalan item'ları işler.
8. **Çöp kutusundaki postlar:** Rollback edilen create postları çöp kutusunda kalır. Doğal anahtar ön kontrolü çöp kutusunu da taradığı için aynı kaydın yeniden apply'ı çöp boşaltılana (veya post geri yüklenene) kadar conflict olur. Bu, fail-closed tasarımdır.
9. **Rollback veritabanı yedeğinin yerine GEÇMEZ.**

## 8. WP-CLI komut sözleşmesi

```text
wp mavibelge import catalog [--dry-run] [--stage=<sectors|qualifications|all>] [--format=<table|json>]
    Varsayılan: SALT OKUNUR dry-run (Faz 6B2 davranışı). Çıktıya aşama ve plan_digest eklendi.
    Run tablosu oluşturmaz, hiçbir şey yazmaz.

wp mavibelge import catalog --apply --stage=<...> --confirm=<plan_digest> [--batch-size=<1..20>] [--format=...] --user=<yönetici>
    Kapı sırası: MAVIBELGE_IMPORT_APPLY_ENABLED -> yetki -> --confirm -> servis kapıları (§3).
    Onay yoksa plan özeti ve plan_digest gösterilir, "Onay gerekli" hatası verilir, hiçbir şey yazılmaz.
    --dry-run ile birlikte verilemez; --confirm/--batch-size --apply olmadan verilemez.

wp mavibelge import rollback --run-id=<uid> [--confirm=<rollback_digest>] [--batch-size=<1..20>] [--format=...] --user=<yönetici>
    --confirm yoksa salt okunur önizleme (yetki gerekir). Yürütme ayrıca apply anahtarı gerektirir.

wp mavibelge import status [--run-id=<uid>] [--format=...] --user=<yönetici>
    Salt okunur; tablolar kurulu değilse hiçbir şey oluşturmaz.
```

- Hata durumunda çıkış kodu sıfırdan farklıdır.
- `--force`, `--skip-conflicts`, kısmi kayıt yazma veya benzeri bir kaçış yolu YOKTUR; bilinmeyen parametreyi WP-CLI reddeder (runtime'da `--force` → exit 1).
- JSON çıktısı yalnız kimlik, durum, sayaç, hash ve sabit hata kodu taşır.

## 9. Admin fallback — bu turda UYGULANMADI (entegrasyon notu)

Admin apply arayüzü bu turda eklenmedi. Mevcut admin dry-run ekranı salt okunur kaldı (37/37 admin HTTP testi). Sunucuda WP-CLI bulunmazsa (görev kartı 04 §8) gereken fallback için entegrasyon notu:

- **Dosya:** yeni `admin/class-import-apply-page.php` (çekirdek agent). Bağlantı `mavibelge-core.php` içindeki `is_admin()` bloğunda yapılır; ortak dosyayı yalnız ana orkestratör değiştirir.
- **Servisler:** Aynı `MaviBelge_Core_Import_Apply_Service` / `MaviBelge_Core_Import_Rollback_Service` ve aynı run deposu kullanılır; ikinci bir checkpoint formatı açılmaz.
- **İstek kapıları:** `manage_options` + `mb_manage_tariff_period`, nonce (eksik/dizi/bozuk nonce `wp_die` ile fail-closed), yalnız POST, `MAVIBELGE_IMPORT_APPLY_ENABLED`.
- **Güvenilmeyen girdi:** Tarayıcıdan yalnız `stage`, `confirm` digest'i ve run uid alınır. Target ID, hash veya alan değerleri tarayıcıdan ASLA alınmaz; hepsi sunucuda yeniden hesaplanır.
- **Zaman aşımı:**
  - Tek istek en fazla bir batch işlemelidir. Bunun için servise "en fazla N batch işle ve checkpoint'te dur" parametresi eklenmesi gerekir; bu tur yazılmadı.
  - Durdurulan run için yeni bir `paused` durumu veya mevcut durum makinesine eşdeğer bir geçiş kararı gerekir; bu bir tasarım kararıdır, bu turda verilmedi.
- **CSRF/replay:** Her batch isteği yeni nonce + run uid + checkpoint numarası taşımalıdır; beklenen checkpoint'ten farklı bir istek reddedilmelidir.
- **Çıktı:** Kaçışlı (`esc_html`); yalnız sayaç, durum ve sabit hata kodu gösterilir.
- **Geri alma:** Sayfa dosyası ve `is_admin()` bağlantısı kaldırılır; veri modeli değişmez.

## 10. Test ortamı ve hata enjeksiyonu

- **Saf PHP** (`tests/run.php`, PHP 7.3.33): sahte, bellek içi WordPress dünyası (`tests/support/import-apply-fakes.php`; transaction = anlık görüntü + geri yükleme) ve açıkça sahte fixture manifesti (`tests/fixtures/apply-fixture.php`: `zz-test-*`, `97UY7xxx`, görselsiz). Mutasyon kanıtları için bkz. teslim raporu.
- **Gerçek WordPress** (`tools/runtime-test/apply-cycle.sh`): PHP 7.3.33 + WordPress 6.9.9 + MariaDB 10.11.
  - `wp_` tabloları `mbfx_` önekli silinebilir bir klona kopyalanır ve WP-CLI `after_wp_config_load` kancasıyla bu öneke yönlendirilir.
  - Bütün apply/rollback yalnız bu klonda yapılır. Sonunda klon ve sahte manifest silinir; ana DB, dosyalar ve uploads başlangıç anlık görüntüsüyle karşılaştırılır.
  - WordPress çekirdeğinin tema desen önbelleği (site transient, yaklaşık 30 dakika) koşu ortasında süresi dolup yenilenmesin diye taban anlık görüntüden önce tazelenir. İlk koşuda bu çekirdek önbelleği `wp_options` farkı üretmişti; kaynak mavibelge-core kodu değildir.
- **Hata enjeksiyonu** yalnız test sürecinde eklenen WordPress kancalarıyla yapılır; üretim koduna test kancası eklenmedi:
  - `created_mb_sektor` → aynı transaction içinde TOCTOU drift'i;
  - `update_post_metadata` → batch ortasında meta yazım hatası;
  - `ALTER TABLE ... ENGINE=MyISAM` → ön kontrol reddi.

## 11. Açık operasyonel önkoşullar (gerçek apply öncesi, hâlâ açık)

1. **Görsel eşleme stratejisi (açık):** Görselli 10 sektör onaylı bir attachment eşlemesi olmadan `blocked_dependency` kalır. Uydurma attachment ID verilmez; fuzzy veya dosya adı tahmini yasaktır. Bu nedenle gerçek manifest bugün hiçbir aşamada uygulanamaz (runtime'da ana DB'de ve klonda `plan_not_applicable` ile doğrulandı).
2. Gerçek WordPress staging (`cms-yeni`) dry-run'ı — yapılmadı; kullanıcı onayı ve ortam bekliyor.
3. Dry-run sonucunun kurum incelemesi.
4. Conflict/duplicate/blocked kayıtlar için kurum/insan kararları.
5. İki doğrulanmış veritabanı + `wp-content/uploads` + yapılandırma yedeği.
6. Bu yedekten gerçek geri dönüş provası.
7. Kullanıcının ayrı, açık apply onayı ve `MAVIBELGE_IMPORT_APPLY_ENABLED`'ın yalnız o pencerede açılması.
8. Üretim PHP/MySQL/MariaDB sürümü, InnoDB/utf8mb4 durumu ve `GET_LOCK` desteği doğrulanmadı. Ön kontrol bunları apply anında fail-closed denetler, ancak sürüm kilidi verilmedi.
9. Sunucuda WP-CLI varlığı doğrulanmadı; yoksa §9 admin fallback'i ayrı bir görevde yazılmalıdır.

## 12. Bu turda yapılmayanlar

- Gerçek staging veya canlı ortamda apply/rollback — çalıştırılmadı; staging ve canlı değişmedi.
- Gerçek 14/83/103 katalog manifestinin herhangi bir WordPress'e yazılması — yapılmadı.
- Admin apply arayüzü — uygulanmadı (§9).
- Görsel eşleme — uygulanmadı.
- Commit, push, tag, release, deploy — yapılmadı.
