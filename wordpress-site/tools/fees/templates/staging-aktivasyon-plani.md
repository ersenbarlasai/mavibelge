# 2026 Ücret Tarifesi — Staging Aktivasyon Planı (Faz 15: onaylı toplu ücret yayını)

> **Bu belge plandır; hiçbir kayıt bu üretimle yayımlanmadı/aktifleştirilmedi/silinmedi.** Canlıya dokunulmaz. Etkinleştirme yalnız eklentinin
> **Ücret yayını** bölümünden (Araçlar → İçe Aktarım Dry-Run → 6), yedekten sonra ve açık onay ifadesiyle yapılır. Üretimde ayrıca §14 üretim
> sabitleri (`docs/admin-import-operations.md`) ve ayrı kullanıcı onayı gerekir.
>
> **Adım adım uygulama, tam geçici izin adları, açma/kapama koşulları ve geri dönüş:** `wordpress-site/docs/ucret-staging-uygulama-yonergesi.md`.
> Aşağıdaki sayılar kaynak kararlardır; sunucudaki durum (hazır/yayında/oluşturulmamış/sapmış) önizlemede **gerçek kayıtlardan** hesaplanır.

## 1. Görünürlük koşulları (koddan: `public/class-catalog-service.php`)

Bir ücret ziyaretçiye ancak **hepsi** doğruysa görünür:

| # | Koşul | Katman | Kodla zorunlu mu? |
|---|---|---|---|
| 1 | WordPress `post_status = publish` | WP durumu | Evet |
| 2 | `_mb_record_status = active` | Mavi Belge durumu | Evet |
| 3 | `_mb_tariff_period` = aktif tarife dönemi seçeneği (`mb_active_tariff_period`) | Dönem | Evet |
| 4 | Bugün `[_mb_valid_from, _mb_valid_until]` içinde (boşsa sınırsız) | Tarih | Evet |
| 5 | `_mb_price_options` dolu ve tüm seçenekler geçerli | Fiyat | Evet |
| 6 | Kaydın sektörü gerçek bir `mb_sektor` terimine çözülür | Sektör | Evet |
| 7 | Kayıt bazlı yayın kararı `publish` (`data/content/fee-publication.manifest.json`, kayıt özetine bağlı) | Karar | Evet — yalnız toplu yayın aracında |

Ücret yayın aracı (eklenti 0.5.5) 1–2'yi birlikte yazar ve etkinleştirmeden ÖNCE 3–6'yı aynı katalog kuralıyla sınar; koşulu sağlamayan kayıt
`ready` olmaz (`not_publicly_valid`).

## 2. Kurum kararı ve sayılar ({{DECIDED_ON}})

- Toplam {{TOTAL}}: bağlı {{LINKED}}, bağlantısız {{UNLINKED}}. Doğrulama: `VERIFIED` {{VERIFIED}}, `DECISION_REQUIRED` {{DECISION}}, `MISMATCH` {{MISMATCH}}.
- **KDV/basım:** tüm ücretlerde %20 KDV dahil, 1.500 TL MYK belge basım ücreti hariç (kurum kararı; tutarlara yeniden KDV eklenmez).
- **Yayın kararı:** yayına uygun **{{PUBLISH}}** (bunların {{PUBLISH_UNLINKED}}'i bağlantısız listelenir), bekletilen **{{HOLD}}**. Bekletilenler (karar gelene kadar
  taslak kalır; admin isterse kurum kararından sonra kaydı kendisi açabilir):

{{HOLD_LINES}}

- 10'arlık batch sayısı (en çok): {{BATCHES_PUBLISH}}. Staging/canlıda daha önce elle aktifleştirilmiş kayıtlar (ör. iki QA kaydı) araçta `published`
  görünür ve yeniden yazılmaz.

## 3. Ön koşullar (hepsi sağlanmadan başlanmaz)

1. Eklenti 0.5.5 + tema 0.6.10 kurulu; ücret veri paketi (`mavibelge-ucret-verisi-*.zip`: 3 katalog manifesti + `fee-publication.manifest.json`)
   web kökü dışında açılmış ve `MAVIBELGE_IMPORT_MANIFEST_DIR` onu gösteriyor (yönerge §4.1).
2. **Tam yedek** (DB + `wp-content`), geri yükleme doğrulanmış (`admin-import-operations.md` §2). Uygulama içi geri alma yedeğin yerine geçmez.
3. Aktif tarife dönemi `2026` (Ücret Kaydı → Aktif Tarife Dönemi).
4. `all` aşaması önizlemesi: ücretlerde `create 0`; elle değişmiş kayıtlar `conflict` olarak görünür ve **ezilmez**.
5. **Anlık görüntü (önce):** Sınav Ücretleri sayfasındaki kayıt sayısı, iki QA kaydının durumu ve fiyatları, `mb_active_tariff_period`, debug.log boyutu.

## 4. Uygulama

1. **Ücret yayın özetini göster** (salt okunur): satır başına `ready` / `published` / `held_pending_decision` / `not_unchanged` / `not_publicly_valid` /
   `not_created` / `unexpected_status`.
2. Onay ifadesi: `UCRET YAYINLA <özet ilk 12>` (birebir). Her istek en çok 10 kayıt; her batch tek transaction (yeniden gözlem → yayın → katı readback
   → audit `import_fees_activated` → commit). Bayat/çift istek `stale_request`, özet değişmişse `confirmation_mismatch`.
3. Sonrası: özet yeniden → `ready 0`; Sınav Ücretleri sayfası `önceki + etkinleştirilen` kayıt; bağlantısız kayıtlarda "Belirtilmemiştir", detay
   bağlantısı yok; bağlı bir kaydın yeterlilik detayında ücret görünür; debug.log temiz.

## 5. Geri dönüş

- **Araçla (yalnız bu aracın etkinleştirdikleri):** aynı bölümde `UCRET YAYINDAN KALDIR <özet ilk 12>` — kayıt hâlâ aracın bıraktığı haldeyse
  (içerik değişmemiş, yayın işareti eşleşiyor) `active/publish` → `draft/draft`; elle değiştirilmiş kayıt atlanır (`not_unchanged`).
- **Kayıt bazlı:** kayıtta `Kayıt Durumu = Taslak/Arşivlendi` ve WordPress durumu **Taslak**.
- **Toplu:** yedeğe dönüş (§3.2); sonra sayaçlar ve anlık görüntü karşılaştırılır.

## 6. Bu planın YAPMADIĞI şeyler

Canlı (`mavibelge.com.tr`) değişikliği yok; aktif dönem değişikliği yok; MYK kodu üretilmedi; altı kaydın ad farkı ve çift bağlantı kararları 2026-10-20 kullanıcı onayıyla, kayıt özetine bağlı olarak çözüldü. Canlı yayın onayı verilmedi.
