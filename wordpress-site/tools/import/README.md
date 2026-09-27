# Faz 6A — Katalog Veri Manifesti Araçları

> Bu araçlar WordPress'e **hiçbir şey yazmaz**. Yalnız `tanitim-site/assets/data/{sectors,qualifications,fees}.js`'i okur ve `wordpress-site/data/**` altında deterministik JSON manifest dosyaları üretir/doğrular. Ayrıntılı sözleşme: `raporlar/veri-aktarim-raporlari/faz6a-manifest-sozlesmesi.md`.

## Kullanım

```bash
# Üretim modu — manifest dosyalarını (yeniden) üretir.
node wordpress-site/tools/import/build-manifest.js

# Doğrulama modu — kaynaktan yeniden hesaplar, diskteki dosyalarla
# byte-eşitliğini ve tüm kalite kapılarını kontrol eder. Fark varsa
# non-zero (1) ile çıkar. Hiçbir şey YAZMAZ (validation-summary.json
# hariç — o bir çalıştırma raporudur, içerik manifesti değildir).
node wordpress-site/tools/import/verify-manifest.js

# Güvenli-kaynak-çıkarımı güvenlik testleri (kötü niyetli girdi
# senaryoları — kritik exploit dahil).
node wordpress-site/tools/import/test-extract-safety.js

# Paylaşılan doğrulayıcının negatif/regresyon testleri (bellek-içi fixture'lar).
node wordpress-site/tools/import/test-manifest-validation.js

# Tema ücret metni statik kontrolü (belge basım ücretinin sınav ücretine
# dahil olmadığını açıkça belirten metin).
node wordpress-site/tools/test-theme-fee-text.js
```

## Dosyalar

```text
extract-source.js         Üç kaynağı GÜVENLİ biçimde okur — hiçbir kod ÇALIŞTIRMAZ (vm/eval/Function yok), yalnız-literal bir recursive-descent ayrıştırıcı kullanır.
build-manifest.js         Üretim modu: paylaşılan doğrulayıcıdan geçemeyen hiçbir şey yazılmaz (ön-doğrulama hatasında writer HİÇ ÇAĞRILMAZ — mutlak garanti). Yazma başladıktan SONRAKİ bir I/O hatası ayrı bir konudur: dosya-başına atomik (torn-write korumalı), set-düzeyinde yalnız best-effort — bkz. writeAllAtomic()'in kendi docblock'u.
verify-manifest.js        Doğrulama modu: yeniden hesaplar, diskle karşılaştırır, AYNI paylaşılan doğrulayıcıyı çalıştırır.
test-extract-safety.js    Ayrıştırıcının güvenlik sınırlarının testi (54 senaryo, kritik exploit dahil).
test-manifest-validation.js  Paylaşılan doğrulayıcının negatif/regresyon testleri (85 senaryo — bellek-içi fixture'lar + iki gerçek dosya-sistemi fixture testi).
lib/hash.js               SHA-256 + deterministik JSON serileştirme.
lib/myk-code.js           MYK kodu ayrıştırma (revizyon opsiyonel).
lib/money.js              TL(integer)->kuruş dönüşümü, taşma korumalı.
lib/price-options.js      Fiyat seçeneği kanonikleştirme (mevcut PHP sınırlarıyla aynı).
lib/slug.js               Türkçe-uyumlu, saf slugify (yalnız source_key üretiminde).
lib/mini-schema.js         Bağımsızlık gerektirmeyen küçük JSON-Schema (draft-07 alt kümesi) doğrulayıcı; bilinmeyen anahtar kelimeyi/$ref'i reddeder.
lib/validate-manifest-set.js  TEK paylaşılan doğrulayıcı — build VE verify ikisi de bunu çağırır.
lib/expected-counts.js    9 doğrulanmış sabit sayının (14/83/103/145/87/16/58/84/19) TEK kaynağı — build VE validate ikisi de bunu import eder, ikinci bir sayı kopyası yok.
lib/manifest-notes.js     sectors/qualifications "notes" metninin TEK kaynağı — build (yazar) VE validate (bağımsız yeniden türetip karşılaştırır) ikisi de bunu çağırır.
```

## Çıktılar

```text
wordpress-site/data/content/sectors.manifest.json
wordpress-site/data/content/qualifications.manifest.json
wordpress-site/data/content/fees.manifest.json
wordpress-site/data/mapping/mapping.manifest.json
wordpress-site/data/mapping/unmatched-fees.manifest.json
wordpress-site/data/mapping/validation-summary.json   (yalnız verify-manifest.js üretir — çalıştırma raporu)
wordpress-site/data/schema/*.schema.json               (elle yazılmış, sabit — build/verify araçları bunları değiştirmez)
```

## Sınırlar (bilinçli, bu alt fazda değişmedi)

- WordPress veritabanına hiçbir yazma yapılmaz.
- 19 kodsuz ücrete tahminle MYK kodu/ilişki verilmez (`qualification_source_key: null` kalır).
- Ağ erişimi yoktur; yalnız üç sabit yerel dosya okunur.
- Sektör `icon`/`image` alanları için WordPress hedefi artık ŞEMA düzeyinde var (`mb_sektor` term-meta, Faz 6A Güvenlik/Şema/Sözleşme Kapanışı'nda eklendi) — ama bu araçlar hiçbir terim/medya OLUŞTURMAZ/yazmaz; hedefe gerçek yazma Faz 6B'nin konusu (bkz. sözleşme belgesi Bulgu #2, çözüldü).
- 20/83 MYK kodunda revizyon eki yok; eklenti doğrulayıcısı artık bunları KABUL EDİYOR (revizyon opsiyonel oldu, Faz 6A Güvenlik/Şema/Sözleşme Kapanışı'nda çözüldü — bkz. sözleşme belgesi Bulgu #1).
- Manifest `schema_version` Faz 6A Son Kabul Düzeltmesi'nde `2.0.0`'a çıktı — `myk_code_matches_current_plugin_format` alanı `matches_legacy_revision_required_format` olarak yeniden adlandırıldı (eski ad artık GÜNCEL eklenti davranışını yanlış tanımlıyordu). Kaynak SHA-256 ve 9 veri sayısı DEĞİŞMEDİ.
- Kaynak alan doğrulaması artık iki katmanlı: genel/birleşik `ALLOWED_KEYS` (parser aşaması, "hiç tanınmayan anahtar" reddi) + kaynak türüne özel TAM şekil sözleşmesi (`extractOne()` içinde, `RECORD_SHAPES` — bir kaynağın alanının başka bir kaynağa sızmasını da reddeder).
- Manifest zarfı artık dosya türüne özel TAM üst-seviye anahtar kümesi uygular (`lib/validate-manifest-set.js`'deki `ENVELOPE_CONTRACTS`) — `records`/`rows` karışımı, fazladan/eksik üst-seviye alan, `source`/`counts` şekil ihlalleri hepsi reddedilir.
- **Doğrulayıcı artık gerçek "tam alan-alan karşılaştırması" yapıyor** (Faz 6A Doğrulayıcı Bütünlük Kapanışı) — her sektör/yeterlilik/ücret kaydının HER alanı (slug, source_key formülü, pricing_type, sort_order, kayıt-içi source meta verisi dahil) kaynakla/türetim formülüyle karşılaştırılıyor; mapping/unmatched raporları içerik manifestlerinden BAĞIMSIZ türetilen tam izdüşümle birebir karşılaştırılıyor (yalnız satır sayısı değil). Bağımsız incelemenin bulduğu 6 somut kabul-edilen-bozulma karşı-örneği artık gerçekten reddediliyor.
- **Kaynak kardinalitesi artık bağlayıcı** (Faz 6A Tam Kapsam ve Sayaç Kapanışı) — her içerik türü için `records.length === sources.<type>.data.length` VE `records.length === EXPECTED.<type>` (14/83/103) zorunlu; `source_index` benzersizliği+0..n-1 tam kapsamı artık gerçek kardinaliteyle BİRLİKTE bir bijeksiyon kanıtlıyor (son kaydın tutarlı biçimde silinmesi de artık reddediliyor). `fees.manifest.json.counts`'taki 8 sayacın TAMAMI `feeFile.records`'tan bağımsız yeniden hesaplanıp karşılaştırılıyor. `sources` girdisi (yok/null/dizi, `.data` dizi değil, `repoRelativePath`/`sha256` eksik/bozuk) artık fail-closed — kontrollü hata döner, `TypeError` fırlatmaz. Zarf `source` alanı ve sector/qualification `notes` metni artık gerçek çıkarım meta verisiyle/formülle doğrudan karşılaştırılıyor (`lib/expected-counts.js`, `lib/manifest-notes.js` — build ile paylaşılan tek kaynak). Fee/unmatched şemalarındaki source-key deseni artık PHP tarafındaki gerçek slug kuralıyla (`[a-z0-9]+(-[a-z0-9]+)*`) birebir aynı — `schema_version` bu nedenle artırılmadı (yalnız doğrulayıcı katılığı).

## Faz 6B2 — bu manifestleri gerçek WordPress'e SALT OKUNUR bağlayan katman

Faz 6B2, buradaki üç `data/content/*.manifest.json` dosyasını `wordpress-site/wp-content/plugins/mavibelge-core/includes/import/class-import-manifest-loader.php` (PHP tarafı, bu Node araçlarının GÜVENLİ OKUMA/zarf doğrulamasını PHP'de bağımsız olarak yeniden uygular — tam alan sözleşmesini burada TEKRARLAMAZ) ile okuyup `MaviBelge_Core_Import_WordPress_Target_Repository` (gerçek `get_term_by()`/`get_posts()`/`get_post_meta()`, yalnız OKUMA) üzerinden gerçek WordPress kayıtlarına karşı planlar. Bu araçlar ve bu klasördeki dosyalar Faz 6B2'de **hiç değişmedi** — yalnız TÜKETİLDİLER. Ayrıntı: `raporlar/veri-aktarim-raporlari/faz6b2-salt-okunur-wordpress-entegrasyonu.md`. WordPress'e Faz 6B2'de de hiçbir kayıt yazılmadı.

## Faz 7 — içerik aktarımı (haber + referans)

Katalog hattına **iki içerik türü** eklendi: `news` (`mb_haber`) ve `reference` (`mb_referans`). Kaynaklar `tanitim-site/assets/data/news.js` (6 gerçek haber/duyuru) ve `references.js` (12 **temsili** referans logosu — gerçek müşteri değil). Katalog araçları ve beş katalog manifesti **değişmedi**; içerik ayrı manifest çiftidir ve ayrı araçlarla üretilir/doğrulanır (tam sözleşme: `wordpress-site/docs/content-import-contract.md`).

```bash
node wordpress-site/tools/import/build-content-manifest.js    # data/content/{news,references}.manifest.json (deterministik, ya-hep-ya-hiç)
node wordpress-site/tools/import/verify-content-manifest.js   # taze üretim = disk (byte), şema, sayılar 6/12
node wordpress-site/tools/import/test-content-manifest.js     # negatif/regresyon (bellek içi + yalnız geçici dizin)
```

```text
build-content-manifest.js    İçerik manifest üretimi. Ön-doğrulama hatasında yazıcı ASLA çağrılmaz; yazma sırasında ikinci rename başarısız olursa ilk dosya eski içeriğine (veya yokluğuna) döndürülür.
verify-content-manifest.js   Doğrulama modu (yazmaz): taze üretim + disk byte-eşitliği + şema + çapraz alan + sayılar (haber 6, referans logosu 15, SSS 6) + beklenmeyen dosya. Faz 12b: referans kaynağı `data/sources/reference-logos/` (yerel, künyeli), SSS kaynağı `tanitim-site/sss.html`; üç dosya: news / references / faqs.
test-content-manifest.js     56 negatif/regresyon testi (değiştirilmiş slug/tarih/tür, eksik/fazla kayıt, sayı uyuşmazlığı, tekrar eden source_key, fazladan dosya, karışık disk seti, atomik yazma).
lib/validate-content-set.js  İçerik çifti için TEK doğrulayıcı (build + verify + test ortak).
lib/expected-counts.js       Yeni AYRI sabit CONTENT_EXPECTED {news: 6, references: 12}; katalog EXPECTED değişmedi.
extract-source.js            news.js/references.js AYNI literal-only ayrıştırıcıyla okunur (kod çalıştırılmaz). Yeni: TAM SATIR tek satırlık blok yorumu (references.js başlığı) silinir; çok satırlı/satır ortası blok yorumu hâlâ reddedilir. extractContent() içerik kaynaklarını, extractAll() yalnız üç katalog kaynağını okur.
```

Kayıt anahtarları: `news:<slug>` / `reference:<slug>` (referans slug'ı `lib/slug.js` `slugify(name)`). `verify-manifest.js` yalnız bu iki dosya adını "beklenmeyen manifest" saymayacak biçimde bilgilendirildi; içeriklerini doğrulamaz.
