# 2026 Ücret Tarifesi — Staging Aktivasyon Planı (kurum onayı SONRASI uygulanacak)

> **Bu belge plandır; hiçbir kayıt yayımlanmadı/aktifleştirilmedi/pasifleştirilmedi/silinmedi.** Toplu aktifleştirme yapılmaz. Canlıya dokunulmaz.
> Kurum kararı `BEKLIYOR` iken bu plan **uygulanamaz**.

## 1. Görünürlük koşulları (koddan doğrulandı: `public/class-catalog-service.php`)

Bir ücret ziyaretçiye ancak **hepsi** doğruysa görünür:

| # | Koşul | Katman | Kodla zorunlu mu? |
|---|---|---|---|
| 1 | WordPress `post_status = publish` | WP durumu | Evet |
| 2 | `_mb_record_status = active` | Mavi Belge durumu | Evet |
| 3 | `_mb_tariff_period` = aktif tarife dönemi seçeneği (`mb_active_tariff_period`, şu an staging'de geçici `2026`) | Dönem | Evet |
| 4 | Bugün `[_mb_valid_from, _mb_valid_until]` içinde (boşsa sınırsız) | Tarih | Evet |
| 5 | `_mb_price_options` dolu ve tüm seçenekler geçerli | Fiyat | Evet |
| 6 | Kaydın sektörü gerçek bir `mb_sektor` terimine çözülür | Sektör | Evet |
| 7 | Doğrulanmış kaynak (PDF ile tutarlı) | Süreç | **Hayır — süreçle sağlanır** (`onay-matrisi.csv`: `doğrulama sonucu`) |
| 8 | Kurumsal onay | Süreç | **Hayır — süreçle sağlanır** (`kurum kararı = ONAY`) |
| 9 | Bağlı yeterlilik gerekiyorsa gerçek bağlantı (`_mb_qualification_id > 0`) | Bağlantı | Hayır (kod 0'a izin verir); kurum kararı ile |

Yayın hazırlığı denetimi (`Publish_Readiness`) `publish`/`active` kullanımda ad, seviye, sektör, dönem, fiyat türü ve geçerli fiyat seçeneklerini ister; bağlantıyı istemez.

## 2. Mevcut sistem bunu toplu yapabiliyor mu? — **HAYIR** (koddan doğrulandı)

- İçe aktarma ücret kaydını **her zaman `draft` + `planned_record_status = draft`** olarak yazar; doğrulayıcı başka değeri reddeder (`class-import-record-validator.php`). İçe aktarma ücret yayınlamaz/aktifleştirmez.
- Sayfalar için var olan **sunucu-tarafı yayın aracı** (`Page_Publisher`) ücret kaydını kapsamaz; `mb_ucret` için toplu aktivasyon aracı **yoktur**.
- Bu yüzden aktivasyon bugün yalnız **WordPress yönetiminden kayıt kayıt** yapılabilir (yayınla + `Kayıt Durumu = Aktif`). Bunun sonuçları:
  - Yayınlamak `post_status`'u değiştirir: bu alan ücret için **yönetilmeyen alandır** → sonraki **run rollback'i** ilgili kayıtta `drift_detected` ile engellenir (kod sözleşmesi; bu görevde WordPress'te denenmedi).
  - `Kayıt Durumu` yönetilen alandır → aynı `catalog`/`all` aşaması yeniden önizlenirse ilgili kayıtlar `conflict` görünür (elle değişiklik). **Aktivasyondan sonra o aşama yeniden uygulanmamalıdır.**
- **Öneri (kod YAZILMADI):** `Page_Publisher` kalıbında "ücret aktivasyon" işlemi — yalnız onaylı `source_key` listesiyle (onay matrisinden), en çok 10 kayıt/istek, sunucu-tarafı kapılar (dönem, fiyat, kaynak/onay listesi, bağlantı kararı), audit'li, katı readback, `draft/passive`'e geri alma. Bunun için ayrı görev/uygulama önerisi gerekir; kurum onayı olmadan hiçbir şey uygulanmaz.

## 3. Aday sayıları (kurum onayı VERİLMEDİ; yalnız kapasite)

- Toplam 103: bağlı 84, bağlantısız 19.
- Doğrulama: `VERIFIED` 72, `DECISION_REQUIRED` 31, `MISMATCH` 0.
- Yalnız `VERIFIED` kayıtlar onaydan sonra **aday** olabilir; `DECISION_REQUIRED` kayıtlar önce kurum kararı almalıdır (bağlantısız 19, Güzellik KDV 7, ad farkı, çift bağlantı).
- Staging'de zaten aktif **iki kontrollü QA kaydı** vardır (`10UY0002-3/03` Makine Bakımcı Seviye 3; `11UY0036-2/01` İplik Bitim İşleri Operatörü). Bunlar yalnız QA kanıtıdır; toplu onay sayılmaz, planda **dokunulmaz**, mutabakatta ayrıca sayılır.
- 10'arlık batch sayısı: tüm kayıtlar için 11; bağlı kayıtlar için en çok 9 (gerçek sayı onaylı kayıt sayısına göre belirlenir).

## 4. Ön koşullar (hepsi sağlanmadan başlanmaz)

1. `onay-matrisi.csv` içinde kurum, **aktifleştirilecek her kayıt** için `kurum kararı = ONAY` yazmış (kayıt bazlı, imzalı/tarihli); `BEKLIYOR` olanlar kapsam dışı.
2. Bağlantısız kayıtlar için ayrı yazılı karar: (a) kod verildi → manifest güncellenip yeniden dry-run (bu görevde yapılmadı), veya (b) "bağlantısız yayın" onayı.
3. Güzellik KDV teyidi (7 kayıt) ve ad farkı kararları yazılı.
4. Aktif tarife dönemi `2026` (staging'de geçici olarak ayarlı; canlıda ayrı karar).
5. **Staging tam yedeği** (DB + `wp-content`), geri yükleme doğrulanmış (`admin-import-operations.md` §2). Rollback (uygulama içi) yedeğin yerine geçmez.
6. **Anlık görüntü (önce):** toplam `mb_ucret` sayısı (`draft`/`publish`/`trash`), `Kayıt Durumu` dağılımı, aktif ücret sayısı (yönetim "Sağlık"/ücret listesi), Sınav Ücretleri sayfasındaki kayıt sayısı, iki QA kaydının durumu ve fiyatları, `mb_active_tariff_period` değeri, debug.log boyutu.

## 5. Batch prosedürü (her batch en çok 10 kayıt)

Sıra: onay matrisindeki `source_index` sırası, yalnız `ONAY` + `VERIFIED` (+ karar verilmiş) kayıtlar. **Önce tek kayıtlık pilot batch**, sonra 10'arlık.

Her batch için:
1. Batch listesini (10 `source_key`) yazılı kaydet; kayıtları tek tek aç: alanları onay matrisiyle karşılaştır (ad, seviye, sektör, fiyat seçenekleri, KDV, dönem `2026`, geçerlilik tarihleri boş/uygun).
2. Her kayıtta: `Kayıt Durumu = Aktif` ve **Yayınla** (iki katman birlikte; biri eksikse kayıt görünmez).
3. **Batch sonrası kontroller** (sırayla; biri başarısızsa DUR):
   - Yönetim sayaçları: aktif ücret sayısı beklenen değerle eşit (önceki + batch boyutu).
   - **Sınav Ücretleri** sayfası: kayıt sayısı artmış; batch'teki kayıtlar doğru tutar/KDV/belge basım ile görünüyor.
   - **Yeterlilik detayı** (bağlı kayıtlar): ücret bölümü doğru; bağlantılar çalışıyor.
   - **Tek fiyatlı** ve **çok seçenekli** en az birer örnek: tutarlar PDF ile birebir (matris).
   - **"Yalnız güncel fiyatı bulunanlar" filtresi** (Meslekler): yalnız aktif ücreti olan yeterlilikler; bağlantısız ücretler filtreyi etkilemez.
   - Hata/log/sağlık: Araçlar → Sağlık temiz; `debug.log` yeni satır yok.
   - **Audit kaydı:** denetim günlüğünde bu batch'in kayıtları için `content_meta_changed` olayları (kullanıcı, kayıt, zaman) görünüyor. Olay türü kodda tanımlıdır; `Kayıt Durumu`/yayın değişiminin hangi kayıtlarda olay ürettiği bu görevde WordPress'te doğrulanmadı — pilot batch'te ilk kez doğrulanır, olay yoksa DUR ve bildir.
4. Batch'i mutabakat tablosuna işle (bkz. §7).

## 6. Bağlantısız kayıtlar

Kurum "bağlantısız yayın" kararını yazılı vermeden aktifleştirilmez. Karar verilirse ayrı pilot yapılır: Sınav Ücretleri'nde görünür, "Detay →" bağlantısı çıkmaz, Meslekler filtresini etkilemez (`baglantisiz-19-kayit.md`). Kod verilirse önce manifest güncellemesi ve yeniden dry-run/apply (ayrı görev; bu görevde yapılmadı).

## 7. Son toplam mutabakatı

`onceki aktif sayı + onaylı ve aktifleştirilen kayıt sayısı = son aktif sayı = Sınav Ücretleri sayfasındaki toplam`. Ayrıca: `ONAY` verilmeyen her kayıt `draft`/`passive` kalmış; iki QA kaydı değişmemiş; `mb_active_tariff_period` değişmemiş; kayıt silinmemiş (toplam `mb_ucret` sayısı önceki anlık görüntüyle aynı). Sonuç yazılı rapora bağlanır.

## 8. Rollback

- **Kayıt bazlı (önerilen):** ilgili kayıtta `Kayıt Durumu = Pasif` (veya Taslak) ve WordPress durumu **Taslak**; Sınav Ücretleri sayacı ve önbellek yeniden kontrol edilir. (Uygulama içi run-rollback bu kayıtlarda drift nedeniyle çalışmaz — §2.)
- **Toplu:** staging yedeğine dönüş (§4.5). Geri dönüşten sonra sayaçlar ve anlık görüntü karşılaştırılır.
- Hata gördüğün anda batch'e devam edilmez; aktifleştirilen kayıtlar geri alınır, neden yazılır.

## 9. Bu planın YAPMADIĞI şeyler

Canlı (`mavibelge.com.tr`) değişikliği yok; toplu yayın yok; aktif dönem değişikliği yok; iki QA kaydına dokunulmadı; PDF yüklenmedi; kurum onayı varmış gibi davranılmadı.
