# Mavi Belge Canlı Geçiş Operasyon Kaydı

> Bu dosya gizli bilgi, parola, kullanıcı adı, sunucu yolu, IP adresi, kişisel veri, SQL veya `.wpress` içeriği taşımaz.

## 1. Bakım penceresi

| Alan | Değer |
|---|---|
| Hazırlık başlangıcı | 27 Eylül 2026, 22:28 TRT |
| Canlı kesinti başlangıcı | BEKLİYOR — import onayı sonrasında |
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

## 7. Ruling ve sapmalar

- Operasyonel geçişte RED/GREEN kanıtı her kapının gerçek FAIL/PASS ölçümüdür; üretim kodu değişikliği yoktur.
- Uygulama planındaki görev başlıkları yürütme aracının tanıyabilmesi için `Görev N` yerine `Task N` olarak adlandırıldı; içerik değişmedi.
- Kullanıcı Task 1 kapanmadan staging export aldı. Paket yalnız yerelde hash/byte düzeyinde doğrulandı; canlıya yüklenmedi. Bu, geri döndürülemez bir işlem değildir; kapasite ve yedek kapıları geçmeden canlı adımlar hâlâ kapalıdır.
- Görüntülerden birinde hesap/iletişim/ağ bilgileri bulunuyordu. Değerler operasyon kaydına aktarılmadı ve kamuya açık raporlarda kullanılmayacak.
- Hosting firmasının sağladığı disk çıktısı ana diskte yaklaşık 12 GB, yedek diskinde yaklaşık 108 GB boş alan gösteriyor. Ana disk %95 dolu olduğundan geçici paketlerin çoğaltılmaması ve mail alanına dokunulmaması zorunludur.
- DirectAdmin listesinde 27 Eylül 2026 tarihli 12,77 GB tamamlanmış `.tar.gz` birinci yedek olarak görüldü. Aynı listedeki yaklaşık 210 KB `.tmp` dosyası ikinci yedek değildir ve işlem kapsamına alınmaz.
- Aynı adla yerel bilgisayara indirilmiş ikinci kopya işletim sistemi özelliklerinde 13.715.857.408 bayt olarak görüldü. Kullanıcı konum bilgisini gizledi; bu değer rapora alınmadı.
