# Karar Kaydı — WordPress Üretimi PHP 7.3 Üzerinde

> Karar tarihi: 10 Eylül 2026
>
> Karar sahibi: Proje kullanıcısı (Mavi Belge)
>
> Statü: Kabul edilmiş, bağlayıcı üretim kısıtı — çözülmesi beklenen bir engel değil.

---

## 1. Kabul edilen üretim ortamı

| Bileşen | Değer |
|---|---|
| Sunucu | Dell R210-II Dedicated (değişmeyecek) |
| İşletim sistemi | CentOS 7 (EOL 30 Haziran 2024, değişmeyecek) |
| Kontrol paneli | DirectAdmin Legacy (değişmeyecek) |
| DirectAdmin ekranında görünen PHP | PHP 7.3 ve PHP 5.6 |
| Bu kararla kabul edilen üretim PHP hedefi | **PHP 7.3** |

Ayrıntı: [`wordpress-faz0-uyumluluk-matrisi.md`](./wordpress-faz0-uyumluluk-matrisi.md) §1-§3.

---

## 2. Kararın iş gerekçesi

Kullanıcı, sunucu donanımını, işletim sistemini, kontrol panelini ve mail altyapısını şimdilik değiştirmeyeceğini bildirmiştir. Bu, önceden alınmış ve bu görevde yeniden teyit edilmiş bir işletme kararıdır. Proje, sunucu/OS/panel değişikliğini bir ön koşul olarak dayatmak yerine, **mevcut PHP 7.3 ortamında mümkün olan en sağlıklı WordPress mimarisiyle** ilerleme kararı almıştır.

Bu karar, sunucuda PHP 7.4+ kurulup kurulamayacağının doğrulanmasını **gereksiz kılmaz**; yalnızca bu doğrulamayı Faz 1'in önkoşulu olmaktan çıkarır. Doğrulama, sunucu bilgi toplama süreciyle (`wordpress-sunucu-bilgi-talebi.md`) paralel ilerleyebilir.

---

## 3. Teknik sonuçlar

Bu karar aşağıdaki teknik gerçekleri **değiştirmez** ve hiçbiri gizlenmez:

- WordPress 7.0 ve sonrası (güncel sürüm 7.1) minimum PHP 7.4.0 ister; PHP 7.3 üzerinde **kurulamaz**.
- PHP 7.3 ile kurulabilecek son WordPress ana hattı **6.9**'dur.
- wordpress.org yalnız 7.1 serisinin güncel ve aktif bakımda olduğunu bildirir; **6.9 hattı aktif güvenlik bakımında değildir**.
- WordPress 6.9 + PHP 7.3 kombinasyonu güncel, uzun vadeli güvenli bir platform olarak **sunulamaz**.
- Güncel üçüncü taraf eklentilerin büyük kısmı `Requires PHP 7.4+`/`8.0+` bildirir; PHP 7.3'te eklenti seçenekleri daralır ve eklenti güvenlik güncellemesi alınamayan eklentilere sabitlenme riski vardır.
- CentOS 7 (EOL 30 Haziran 2024) ve PHP 7.3 (EOL 6 Aralık 2021) EOL riskleri **yüksek** olarak kayıtta açık kalır.
- Kod ve eklenti katmanındaki güvenlik önlemleri (nonce, capability, sanitization, escaping vb.) işletim sistemi ve PHP çekirdeğindeki yama eksikliğinden doğan riski **tamamen gideremez**.

Kaynak: [`wordpress-faz0-uyumluluk-matrisi.md`](./wordpress-faz0-uyumluluk-matrisi.md) §3, §6, §11 (madde 5-7, 17-19).

---

## 4. Kabul edilen riskler

| Risk | Kabul durumu |
|---|---|
| WordPress 6.9 hattının aktif güvenlik bakımında olmaması | Kabul edildi — kullanıcı kararıyla açık tutuluyor |
| CentOS 7 EOL (sistem kütüphaneleri yama almıyor) | Kabul edildi — değiştirilmiyor |
| PHP 7.3 EOL (4 yıl 9 aydan uzun süredir yama almıyor) | Kabul edildi — değiştirilmiyor |
| Daralmış/eski üçüncü taraf eklenti ekosistemi | Kabul edildi — eklenti seçiminde ek denetim uygulanacak |
| Eklenti bağımlılığının azaltılmasına rağmen tam güvenli iddiasının yapılamaması | Kabul edildi — bu belge veya başka hiçbir belge "tam güvenli" iddiası üretmez |

---

## 5. Risk azaltma önlemleri

- Özel `mavibelge` teması ve `mavibelge-core` eklentisi, mümkün olduğunca **üçüncü taraf eklenti bağımlılığını azaltacak** şekilde tasarlanır.
- Kullanılacak her üçüncü taraf eklenti, `wordpress-faz0-uyumluluk-matrisi.md` §6.1'deki seçim kurallarına tabidir (Requires PHP uyumu, Tested up to, bakım göstergesi, terk edilmiş eklenti yasağı).
- Tüm özel kod, PHP 7.3'te bulunmayan sözdizimini kullanmaz (matris §4-§5) ve PHP 8.x'te de fatal/deprecated üretmemeyi hedefler (bkz. §6).
- WordPress güvenlik alışkanlıkları (nonce, capability, sanitization, validation, escaping) her fazda zorunlu kalır.
- Sunucu, dosya izinleri, erişim, yedekleme ve izleme kontrolleri WordPress eklentilerinden ayrı yürütülür (`wordpress-ana-uygulama-plani.md` §2.1, §9.2).
- `DISALLOW_FILE_EDIT`, giriş deneme sınırlama, 2FA ve upload dizininde PHP çalıştırma engeli gibi sabit önlemler korunur (`wordpress-ana-uygulama-plani.md` §9.1).
- Güncellemeler doğrudan canlıda değil, önce eş üretim ortamında (aynı PHP + veritabanı sürümü) denenir; başarısız güncelleme için belgelenmiş geri dönüş paketi hazırlanır.

---

## 6. Gelecekte PHP 8.x'e geçiş ilkeleri

- Özel kod, PHP 7.3 uyumluluğu için kaldırılmış eski PHP API'lerine yaslanmaz; hedef, kodun PHP 8.3'te de deprecated/fatal üretmemesidir.
- Composer bağımlılıkları eklenirse `require.php` alanı yalnız mevcut hedefi (`>=7.3`) kapsar; gelecekte PHP 8.x'e geçişte bu alan gözden geçirilir.
- PHP 8.x'e geçiş kararı ayrı bir karar kaydı ile alınır; bu belge o geçişi **otomatik olarak öngörmez veya taahhüt etmez**.
- Geçiş yapıldığında `wordpress-faz0-uyumluluk-matrisi.md` §4-§5'teki sözdizimi kısıtları güncellenir ve WordPress/eklenti sürüm kilidi yeniden değerlendirilir.

---

## 7. Kararı yeniden değerlendirecek tetikleyiciler

Aşağıdakilerden **herhangi biri** gerçekleştiğinde bu karar orkestratör tarafından yeniden değerlendirilir ve gerekirse kullanıcıya yeniden sunulur:

1. WordPress 6.9 hattında kritik ve yamasız bir güvenlik açığı ortaya çıkarsa.
2. Projede gerekli görülen bir eklenti PHP 7.3 desteğini kaybederse (güncel sürümü artık PHP 7.3'te çalışmazsa).
3. PHP 7.3 nedeniyle giderilemeyen bir uygulama hatası veya uyumsuzluk ortaya çıkarsa.
4. Sunucu veya veritabanı bileşeninde kritik bir EOL/güvenlik olayı yaşanırsa.
5. Kişisel veri işleyen yeni bir işlev (örn. hassas form, dosya yükleme) devreye alınacaksa — bu durumda PHP 7.3 riski KVKK kapsamında ayrıca değerlendirilir.

Bu tetikleyicilerden biri gerçekleştiğinde **yayın durdurulur**; sorun tema/eklenti sürümünü düşürerek gizlenmez (`wordpress-ana-uygulama-plani.md` §9.2).

---

## 8. İlgili belgeler

- [`AGENTS.md`](../AGENTS.md) §8 — güncellenmiş üretim kısıtı özeti
- [`wordpress-faz0-uyumluluk-matrisi.md`](./wordpress-faz0-uyumluluk-matrisi.md) — ayrıntılı teknik uyumluluk kararları
- [`proje-durumu.md`](./proje-durumu.md) — Faz 1 yetkilendirmesi
- [`wordpress-ana-uygulama-plani.md`](./wordpress-ana-uygulama-plani.md) §2.1, §9 — bağlayıcı mimari ilkeler (bu görevde değiştirilmedi)
