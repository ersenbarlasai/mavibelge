# Dağıtım Sözleşmesi — `wordpress-site/`

Bu belge bir dağıtım prosedürü **değildir** (bkz. `deploy/README.md`, DevOps agentının kapsamı). Yalnız yerel/üretim sınırlarını kaydeder.

## Ortamlar

| Ortam | Amaç | Durum |
|---|---|---|
| Yerel geliştirme | Tema/eklenti kodlama | Bu fazda yerel WordPress runtime **kurulmamıştır** |
| `https://yeni.mavibelge.com.tr/` | Onaylı statik görsel referans | Üzerine yazılmaz |
| `https://cms-yeni.mavibelge.com.tr/` | WordPress kabul/staging | **Henüz oluşturulmamıştır** |
| `https://mavibelge.com.tr/` | Üretim | Son kabulden önce değiştirilmez |

## Sunucu varsayımları

- Üretimde Composer veya Node.js bulunması **gerekmez**. `composer.json` ve `package.json` yalnız yerel geliştirme metadata'sıdır.
- Derleme (CSS/JS) yerelde yapılır; üretime yalnız derlenmiş çıktı gönderilir.
- Dosya yükleme yöntemi ve gerçek sunucu yolları DevOps fazında doğrulanacaktır.

## Bu fazda yapılmayanlar

- WordPress çekirdeği indirilmedi/kurulmadı.
- `cms-yeni` subdomain oluşturulmadı.
- Sunucuya bağlanılmadı.
- Composer/npm paketi kurulmadı.
