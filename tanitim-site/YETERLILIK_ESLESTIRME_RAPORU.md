# Yeterlilik Eşleştirme Raporu — 75 → 83 Farkının Çözümü

**Tarih:** 2026-08-18
**Kaynak:** final-rapor.md (bölüm 6, sektör/yeterlilik envanteri) ile karşılaştırma + mavibelge.com.tr canlı sayfa doğrulaması.

## Özet

`assets/data/qualifications.js` önceki sürümde 75 kayıt içeriyordu. `final-rapor.md` ve Stitch `PUB-02` referansı 83 görünür kayıt olduğunu belirtiyor. Fark tam olarak üç sektörde toplanıyordu: **Metal** (rapor: 5, veri: 1), **Plastik** (rapor: 3, veri: 1), **Mobilya** (rapor: 3, veri: 1) = 8 eksik kayıt.

## Yapılan doğrulama

`mavibelge.com.tr/metal-meslekleri/`, `/plastik-meslekleri/` ve `/mobilya-meslekleri/` sayfaları canlı olarak yeniden getirildi (2026-08-18).

### Metal sektörü — düzeltildi

Eski veride "Metal Kesim Operatörü" (12UY0084-4/02) hem `makine` hem `metal` sektörüne aynı kodla iki kez eklenmişti — veri girişi hatası. Bu kayıt `makine` sektöründe (CNC/tezgâh grubuyla birlikte, orijinal konumunda) korundu; `metal` sektöründeki mükerrer kopyası silindi ve yerine canlı sayfada listelenen gerçek 5 kayıt eklendi:

| MYK Kodu | Meslek | Seviye |
|---|---|---|
| 11UY0013-3/02 | Endüstriyel Boru Montajcısı | 3 |
| 11UY0010-3/04 | Çelik Kaynakçısı | 3 |
| 11UY0014-3/02 | Alüminyum Kaynakçısı | 3 |
| 11UY0016-4/03 | Kaynak Operatörü | 4 |
| 11UY0015-4/03 | Direnç Kaynak Ayarcısı | 4 |

### Plastik sektörü — 2 kayıt eklendi

Mevcut "Plastik Enjeksiyon Üretim Elemanı" (12UY0069-3/02) korundu; canlı sayfadan doğrulanan 2 kayıt eklendi:

| MYK Kodu | Meslek | Seviye |
|---|---|---|
| 13UY0143-3/01 | Plastik Şişirme Film Üretim Operatörü (Ekstrüzyon) | 3 |
| 13UY0142-3/01 | Plastik Profil Üretim Operatörü (Ekstrüzyon) | 3 |

### Mobilya sektörü — 2 kayıt eklendi

Mevcut "Mobilya Döşemecisi" (17UY0300-3/00) korundu; canlı sayfadan doğrulanan 2 kayıt eklendi:

| MYK Kodu | Meslek | Seviye |
|---|---|---|
| 17UY0301-3/00 | Ahşap Mobilya İmalatçısı | 3 |
| 17UY0301-4/00 | Ahşap Mobilya İmalatçısı | 4 |

## Sonuç

Toplam kayıt sayısı **75 → 83** oldu. `final-rapor.md` bölüm 6'daki 12 sektörlük tablo (13/7/5/12/3/5/3/6/12/3/7/7) ile birebir eşleşiyor. Kod tekrarı kalmadı (script ile doğrulandı).

## Doğrulanamayan / şüpheli kayıt

Maden & Mermer sektöründe final-rapor.md şu uyarıyı koruyor: sayfada 7 görünen kayıt var ama yalnızca 3 farklı detay URL'si bulunuyor; 5 kayıt aynı detay sayfasına bağlanıyor (Mekanizasyon İşçisi Seviye 4, Mermer-Doğaltaş Özel İmalat Elemanı Seviye 4, Yeraltı Hazırlık İşçisi Seviye 4/3, Kırma Eleme Tesis Operatörü Seviye 3). Bu durum veri setinde isim/kod/seviye olarak korunmuştur (7 kayıt) ancak gerçek kaynak sitede bu 5 kaydın ayrı detay sayfası olmadığı, kurum tarafından bilinmesi gereken bir tutarlılık sorunudur — kod uydurulmamış, mevcut final-rapor.md verisi aynen taşınmıştır.

Hiçbir kayıt için MYK kodu veya seviye uydurulmamıştır; tamamı final-rapor.md veya canlı mavibelge.com.tr sayfalarından doğrulanmıştır.
