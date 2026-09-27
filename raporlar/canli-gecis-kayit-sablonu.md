# Mavi Belge Canlı Geçiş Operasyon Kaydı

> Bu dosya gizli bilgi, parola, kullanıcı adı, sunucu yolu, IP adresi, kişisel veri, SQL veya `.wpress` içeriği taşımaz.

## 1. Bakım penceresi

| Alan | Değer |
|---|---|
| Hazırlık başlangıcı | 27 Eylül 2026, 22:28 TRT |
| Canlı kesinti başlangıcı | 27 Eylül 2026 — SeedProd bakım modu etkinleştirildi |
| Uygulayıcı | Site sahibi (DirectAdmin/WordPress) + Codex yönlendirmesi |
| Geri dönüş sorumlusu | Site sahibi |
| Azami kesinti süresi | 120 dakika — kullanıcı onayladı |
| Hedef adres | `https://mavibelge.com.tr/` |
| Staging adresi | `https://cms-yeni.mavibelge.com.tr/` |

## 2. Geri dönüş yedekleri

| Kopya | Tarih | Yaklaşık boyut | Saklama türü | Geri yükleme doğrulaması |
|---|---|---:|---|---|
| Eski canlı yedek 1 | 27 Eylül 2026 | `12,77 GB` | DirectAdmin yedek alanında tamamlanmış `.tar.gz` | Dosya listesinde tamamlanmış arşiv görüldü; geri yükleme prova tarihi BEKLİYOR |
| Eski canlı yedek 2 | 27 Eylül 2026 | `13.715.857.408` bayt | Yerel bilgisayarda, sunucudan ayrı tamamlanmış `.tar.gz` | İşletim sistemi dosya özelliklerinde tam boyut görüldü; geri yükleme provası kullanıcı tarafından daha önce onaylandı |
| Import öncesi son canlı yedek | Henüz alınmadı | — | DirectAdmin özel yedek alanı | Görev 3'te alınacak |

## 3. Kaynak ve sürüm doğrulaması

| Bileşen | Beklenen | Kanıt | Sonuç |
|---|---|---|---|
| GitHub `main` | `af3ca05ddab16132264ed1dbfc80d82c1f11c55d` | `git ls-remote origin refs/heads/main` | PASS |
| Tema | `0.6.8` | Staging sistem bilgisi + paket | PASS |
| Tema ZIP SHA-256 | `04d926c2c1bcebc7418a832932729cbb7f590e1cd3ab22b0c923b51948626a4b` | Yerel `Get-FileHash` | PASS |
| Tema ZIP boyutu | `1.062.563` bayt | Yerel dosya ölçümü | PASS |
| Mavi Belge Core | `0.5.3` | Staging sistem bilgisi + paket | PASS |
| Core ZIP SHA-256 | `4af81e82cbdd889e2342be7898a7fb18d5a8b136cf24864de54811f1f84cf5a8` | Yerel `Get-FileHash` | PASS |
| Core ZIP boyutu | `350.167` bayt | Yerel dosya ölçümü | PASS |
| Staging WordPress | `6.9.9` | Staging sistem bilgisi | PASS |
| Staging PHP | `7.3.33` | Staging sistem bilgisi | PASS |
| Post SMTP | `4.0.2` | Kurulum ve gerçek teslim testi | PASS |
| All-in-One WP Migration | Staging ve canlı aynı sürüm | Her iki ortamda `7.111` kullanıcı tarafından doğrulandı | PASS |
| Beş form | Tekil SMTP teslimi | Kullanıcı kabulü | PASS |

## 4. Kapasite önkoşulu

| Kontrol | Değer | Sonuç |
|---|---:|---|
| Ana disk boş alanı | Yaklaşık `12 GB` (`%95` dolu) | PASS — 86 MB paket için yeterli; YÜKSEK DOLULUK riski açık |
| Yedek diski boş alanı | Yaklaşık `108 GB` (`%88` dolu) | PASS — geri dönüş kopyaları için alan var |
| DirectAdmin inode kullanımı/limiti | BEKLİYOR | BEKLİYOR |
| DirectAdmin hesap disk kullanımı | `179,35 GB` (büyük kısmı e-posta) | BİLGİ — kota/boş alan görünmüyor |
| DirectAdmin alan adı disk kullanımı | `4,77 GB` | BİLGİ |
| DirectAdmin inode kullanımı | `244.387` | BİLGİ — limit görünmüyor |
| PHP `upload_max_filesize` | `64M` | BİLGİ |
| PHP `post_max_size` | `64M` | BİLGİ |
| All-in-One WP Migration tarayıcı import limiti | `64 MB` | FAIL — normal Import From → File yolu kullanılamaz |
| Staging `.wpress` export boyutu | `86.058.411` bayt | PASS — dosya oluştu ve yerel kopya mevcut |
| Staging `.wpress` SHA-256 | `370020fa43c32460b25ee4e5d9ed4c18e14fda5de56a440ec2c6aaacb9a0cabd` | PASS |
| Alternatif desteklenen aktarım | File Manager ile `wp-content/ai1wm-backups` + Backups → Restore | ServMask resmî kullanıcı kılavuzunda doğrulandı; henüz canlıya yüklenmedi |

**Kapı kuralı:** `.wpress` boyutu boş alan, PHP/eklenti yükleme ve import limitlerinin tamamının altında değilse canlı import BAŞLATILMAZ.

## 5. Mevcut canlı kanıtları

- Ana sayfa: herkese açık salt okunur kontrol PASS; ekran görüntüsü P1 turunda yenilenecek
- İletişim: herkese açık salt okunur kontrol PASS; ekran görüntüsü P1 turunda yenilenecek
- Sınav ücretleri ekran görüntüsü: BEKLİYOR
- Örnek içerik ekran görüntüsü: BEKLİYOR
- Sitemap/URL listesi: BEKLİYOR
- Kişisel veri içeren yönetim ekranı görüntüsü alınmayacak.

## 6. Ön uçuş kararı

| Kapı | Durum |
|---|---|
| İki geri dönüş yedeği | PASS — sunucu yedek alanında tamamlanmış arşiv + yerel bilgisayarda bağımsız tamamlanmış kopya |
| Kapasite | KOŞULLU PASS — disk alanı yeterli; ana disk `%95` dolu, 64 MB tarayıcı yolu yetersiz ve File Manager/Backups yolu kullanılacak |
| Sürümler | PASS |
| Staging kabulü | PASS |
| Genel sonuç | PASS — Task 2 staging paket/sürüm doğrulamasına geçilebilir; canlı upload/import henüz yetkili değildir |

## 6.1. Task 2 export kararı

| Kontrol | Durum |
|---|---|
| Staging son kabulü | PASS |
| Migration sürüm eşitliği | PASS — iki ortam `7.111` |
| Export oluşumu | PASS — `86.058.411` bayt |
| Yerel SHA-256 | PASS — `370020fa43c32460b25ee4e5d9ed4c18e14fda5de56a440ec2c6aaacb9a0cabd` |
| Özel saklama | PASS — yerel cihaz; GitHub ve herkese açık web alanı dışında |
| Tarayıcı import kapasitesi | FAIL — `64 MB`; bu yol kullanılmayacak |
| Onaylı geri yükleme yolu | File Manager → `wp-content/ai1wm-backups` → All-in-One WP Migration Backups → Restore |
| Task 2 sonucu | PASS — canlı hazırlığı için ayrı onay kapısına geçilebilir |

## 6.2. Task 3 canlı hazırlık başlangıcı

| Kontrol | Durum |
|---|---|
| Canlı hazırlık ve son yedek onayı | PASS — kullanıcı 27 Eylül 2026 tarihinde açık onay verdi |
| İçerik/ayar dondurma başlangıcı | 27 Eylül 2026, 23:00 TRT |
| DNS ve mail ayarları | KAPSAM DIŞI — değiştirilmez |
| Bakım modu | PASS — mevcut SeedProd bakım ekranı anonim ziyaretçide görüldü |
| Yönetim erişimi | PASS — bakım modu açıkken kullanıcı `/wp-admin/` erişimini doğruladı |
| Import öncesi son yedek | 27 Eylül 2026 tarihli 12,77 GB sunucu arşivi + 13.715.857.408 bayt yerel kopya |
| Canlı WordPress çekirdeği | `6.8.10`; `6.9.9` eşitlemesi kullanıcı tarafından açıkça ONAYLANDI |
| Çekirdek eşitleme sonucu | PASS — yönetim paneli erişilebilir ve WordPress `6.9.9` gösteriyor |
| Kullanılan resmî çekirdek paketi | `wordpress-6.9.9.zip` — WordPress.org genel dağıtımı |
| Resmî paket SHA-1 | `99a0c1ba620bfba0b2188e10e5406f56eee901f2` |
| Yerel SHA-1 doğrulaması | PASS — `certutil` sonucu resmî SHA-1 ile birebir eşit |
| Paket içi sürüm doğrulaması | PASS — `wp-includes/version.php` içinde `$wp_version = '6.9.9'` |
| Eski kök çekirdek dosyaları geri alma kopyası | PASS — canlı kökteki 16 standart çekirdek dosyası web kökü dışındaki özel klasöre kopyalandı; `wp-config.php` dahil edilmedi |
| Dil/içerik koruması | Paket içindeki `wp-content` canlıya kopyalanmayacak; mevcut dil, yükleme, tema ve eklenti dosyaları bu çekirdek adımında korunacak |
| Güncelleme sonrası anonim HTTP smoke | PASS — ana rota HTTP 200, bakım metni ve özel/no-cache yanıtı mevcut |
| Güncelleme sonrası anonim yönetim koruması | PASS — `/wp-admin/` anonim isteği WordPress tarafından gizli bulunamadı rotasına yönlendiriliyor; oturumlu yönetici erişimi kullanıcı tarafından doğrulandı |
| Güncelleme sonrası oturumlu ana sayfa smoke | PASS — kullanıcı ana sayfanın normal yüklendiğini ve fatal/beyaz ekran olmadığını doğruladı |
| Task 3 sonucu | PASS — import öncesi geri dönüş noktası, bakım ekranı, yönetim erişimi, WordPress `6.9.9` ve eşit migration sürümü doğrulandı |

### Canlı aktif eklenti envanteri (import öncesi)

| Eklenti | Sürüm |
|---|---:|
| Advanced Excerpt | 4.4.0 |
| Akismet Anti-Spam | 5.1 |
| All-in-One WP Migration | 7.111 (kullanıcının güncel doğrulaması) |
| Classic Editor | 1.6.3 |
| SeedProd Coming Soon / Maintenance Mode | 6.15.7 |
| Contact Form 7 | 5.7.7 |
| Contact Form 7 Material Design | 1.0.0 |
| Display Posts | 3.0.2 |
| Green Popups | 7.33 |
| iThemes Security | 8.1.6 |
| Loginizer | 1.7.9 |
| Logo Slider | 3.9.0 |
| MetaSlider | 3.31.0 |
| Super Logos Showcase | 2.5 |
| UpdraftPlus | 1.26.4 |
| WordPress Importer | 0.8.1 |
| WPBakery Page Builder | 6.13.0 |
| WPForms Lite | 1.8.2.1 |
| WP Mail SMTP | 3.8.0 |

Bu envanter salt geri dönüş içindir. Import sonrasında eski eklentiler topluca yeniden etkinleştirilmeyecek; staging paketi kendi onaylı eklenti durumunu getirecektir.

## 6.3. Task 4 canlı import başlangıcı

| Kontrol | Durum |
|---|---|
| Ayrı canlı import onayı | PASS — kullanıcı 28 Eylül 2026 tarihinde doğrulanmış `.wpress` paketinin canlıya importunu açıkça onayladı |
| Kaynak paket | Task 2'de doğrulanan özel `.wpress`; 86.058.411 bayt; SHA-256 `370020fa43c32460b25ee4e5d9ed4c18e14fda5de56a440ec2c6aaacb9a0cabd` |
| Aktarım yöntemi | File Manager ile `wp-content/ai1wm-backups`; ardından All-in-One WP Migration → Backups → Restore |
| Sunucuya yüklenen paket boyutu | PASS — DirectAdmin `82,07 MB` gösteriyor; 86.058.411 baytlık doğrulanmış kaynak paketle uyumlu |
| Geçici dış erişim koruması | PASS — DirectAdmin dizin koruması anonim isteğe HTTP 401 ve `Basic realm="Bakim Penceresi"` döndürüyor |
| Restore öncesi yönetim/paket kapısı | PASS — kullanıcı WordPress yönetimine erişti ve `82,07 MB` paketin All-in-One WP Migration Backups ekranında listelendiğini doğruladı |
| Backups → Restore denemesi | BEKLENEN ENGEL — ücretsiz sürüm Unlimited Extension istedi; import başlamadı ve canlı veri değişmedi |
| Kök neden | Backups listesinden Restore ücretli özellik; ücretsiz File Import kullanılabilir, ancak mevcut PHP/LiteSpeed upload sınırı 64 MB ve paket 86.058.411 bayt |
| Geçici PHP limit değişikliği onayı | PASS — kullanıcı LiteSpeed kökünde geri alınabilir `.user.ini` ile 128 MB upload/post limiti testini açıkça onayladı |
| Geçici PHP limit testi | PASS — All-in-One WP Migration Import ekranı azami yükleme boyutunu `128 MB` gösteriyor; doğrulanmış `82,07 MB` paket sınırın altında |
| File Import sonucu | PASS — kullanıcı importun tamamlandığını ve staging WordPress yönetici hesabıyla canlı yönetime eriştiğini doğruladı |
| Import sonrası dış erişim koruması | PASS — anonim istek hâlâ HTTP 401 ve `Basic realm="Bakim Penceresi"` dönüyor |
| WordPress/Site adresleri | PASS — ikisi de `https://mavibelge.com.tr` |
| Üretim ortamı | PASS — WordPress `6.9.9`, environment `production` |
| Arama motoru görünürlüğü | PASS — indeks engeli kapalı |
| Kalıcı bağlantı | PASS — `/%postname%/` |
| Etkin tema | PASS — Mavi Belge `0.6.8` |
| Zorunlu eklentiler | PASS — Mavi Belge Core `0.5.3`, Post SMTP `4.0.2`, All-in-One WP Migration `7.111` etkin |
| Task 4 sonucu | PASS — yeni WordPress canlı alana aktarıldı; HTTP koruması altında, henüz kamuya açılmadı |
| Kamuya açma | YASAK — Task 5 P0 kabulü tamamlanana kadar bakım modu korunur |

## 6.4. Task 5 kapalı P0 kabulü

| Kontrol | Durum |
|---|---|
| Yedi içerik rotası | PASS — kullanıcı normal renderı, fatal/PHP uyarısı/beyaz ekran olmadığını doğruladı |
| Özel 404 | PASS |
| Header/footer/logo/görseller | PASS |
| Masaüstü ve hamburger menü | PASS |
| Yeterlilik ve sektör filtreleri | PASS |
| Örnek yeterlilik detayı | PASS — `11UY0036-2/01`, Seviye 2, Tekstil |
| Örnek ücret ve ücret filtresi | PASS — `6.500,00 TL` |
| Temiz canonical yollar | PASS — `/index.php/` yok |
| Post SMTP sunucu kabulü | PASS |
| Post SMTP gerçek gelen kutusu teslimi | PASS — kullanıcı yetkili alıcı kutusunda doğruladı |
| İletişim formu | PASS — başarı yanıtı; tam bir e-posta ulaştı |
| Online başvuru formu | PASS — başarı yanıtı; tam bir e-posta ve onaylı test eki ulaştı |
| Sınav talebi formu | PASS — başarı yanıtı; tam bir e-posta ulaştı |
| İtiraz/şikâyet formu | PASS — başarı yanıtı; tam bir e-posta ulaştı |
| İş başvurusu formu | PASS — başarı yanıtı; tam bir e-posta ve onaylı test eki ulaştı |
| Form gizlilik ve tekrar kontrolü | PASS — başarı URL'lerinde kişisel veri yok; mükerrer e-posta yok |
| Post SMTP günlük politikası | PASS — kullanıcı ayrıntılı e-posta günlüğünü kapattı; geçiş test kayıtlarını desteklenen arayüzden temizledi ve günlüklemeyi yeniden kapattı |
| Task 5 sonucu | PASS — tüm kapalı P0 kabul satırları geçti; site HTTP parola koruması altında, henüz kamuya açık değil |

## 7. Ruling ve sapmalar

- Operasyonel geçişte RED/GREEN kanıtı her kapının gerçek FAIL/PASS ölçümüdür; üretim kodu değişikliği yoktur.
- Uygulama planındaki görev başlıkları yürütme aracının tanıyabilmesi için `Görev N` yerine `Task N` olarak adlandırıldı; içerik değişmedi.
- Kullanıcı Task 1 kapanmadan staging export aldı. Paket yalnız yerelde hash/byte düzeyinde doğrulandı; canlıya yüklenmedi. Bu, geri döndürülemez bir işlem değildir; kapasite ve yedek kapıları geçmeden canlı adımlar hâlâ kapalıdır.
- Görüntülerden birinde hesap/iletişim/ağ bilgileri bulunuyordu. Değerler operasyon kaydına aktarılmadı ve kamuya açık raporlarda kullanılmayacak.
- Hosting firmasının sağladığı disk çıktısı ana diskte yaklaşık 12 GB, yedek diskinde yaklaşık 108 GB boş alan gösteriyor. Ana disk %95 dolu olduğundan geçici paketlerin çoğaltılmaması ve mail alanına dokunulmaması zorunludur.
- DirectAdmin listesinde 27 Eylül 2026 tarihli 12,77 GB tamamlanmış `.tar.gz` birinci yedek olarak görüldü. Aynı listedeki yaklaşık 210 KB `.tmp` dosyası ikinci yedek değildir ve işlem kapsamına alınmaz.
- Aynı adla yerel bilgisayara indirilmiş ikinci kopya işletim sistemi özelliklerinde 13.715.857.408 bayt olarak görüldü. Kullanıcı konum bilgisini gizledi; bu değer rapora alınmadı.
- 12,77 GB sunucu yedeği dondurma başlangıcından yaklaşık on dakika önce tamamlandı ve aynı arşivin yerel ikinci kopyası doğrulandı. Arada içerik/ayar değişikliği yapılmaması talimatıyla bu arşiv import öncesi son geri dönüş noktası kabul edildi.
- SeedProd bakım ekranı anonim görünümde doğrulandı; yönetici oturumu erişilebilir kaldı. Bakım ekranında form veya veri toplama bulunmuyor.
- `.wpress` paketi 64 MB tarayıcı import sınırını aştığından planın “İçe Aktar → Dosya” yolu kullanılmayacaktır. Aynı eklentinin resmî sunucu-klasörü yöntemiyle paket `wp-content/ai1wm-backups` altına konup Backups ekranından Restore çalıştırılacaktır; paket boyutu restore öncesinde yeniden doğrulanacaktır.
- Backups ekranındaki Restore işleminin Unlimited Extension gerektirdiği canlı arayüzde kanıtlandı; önceki sunucu-klasörü kararı geçersiz kılındı. Ücretsiz ve resmî desteklenen yol File Import'tur; LiteSpeed kökünde geçici `.user.ini` ile PHP sınırı yükseltilmeden yeniden deneme yapılmayacaktır.
