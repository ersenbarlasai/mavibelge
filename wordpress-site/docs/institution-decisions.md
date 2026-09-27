# Kurum Kararı Bekleyen Konular ve Etkinleştirdikleri Alanlar

> Bu belge **kod eksikliği değil**, kurumsal/operasyonel önkoşulları listeler. Hiçbiri uydurulmadı; her biri için güvenli
> varsayılan uygulanmıştır (kapalı/pasif/boş). Karar geldiğinde yalnız **veri/ayar girilerek** etkinleşir.

| # | Karar | Bilinmeyen | Güvenli varsayılan (şu an) | Karar gelince ne etkinleşir |
|---|---|---|---|---|
| 1 | Gerçek referans kurumları | **ÇÖZÜLDÜ (26 Eylül 2026):** onaylı canlı referans sayfasındaki 15 logo gerçek referanstır. **AÇIK:** logoların FİRMA ADLARI doğrulanmadı | 15 logo yerel envanterde (SHA-256, alınma tarihi, URL); kayıtlar `_mb_reference_status=real`, başlık nötr "Referans NN" (`name_status=unverified`), gerçek logo attachment; taslak içe aktarılır | Kurum adları doğrulanınca ayrı onaylı karar + manifest güncellemesi (adlar görselden tahmin edilmez) |
| 2 | Sınav takvimi bağlantısı | **ÇÖZÜLDÜ:** `https://mavibelge.pratikteorik.com/home/examcalendar` | Yerel bilgilendirme sayfası + tek CTA (birebir adres); iframe yok | — |
| 3 | MYK sorgu bağlantısı | **ÇÖZÜLDÜ:** MYK portalı `…layout=aday_bilgi_sorgu` | Yerel bilgilendirme sayfası + tek CTA (query string birebir); kimlik bilgisi alınmaz | — |
| 4 | 19 kodsuz ücret kaydının MYK kodu | Kod eşlemesi | `qualification_source_key: null`; ilişki `0`; tahmin/bulanık eşleme yok | Kurum kodu verince manifest güncellenir, yeniden dry-run |
| 5 | Maden/mermer görsel kararı | Ayrı görsel | Aynı görsel paylaşımı manifestte notlu; sektör görseli çözülemezse `blocked_dependency` | Doğru attachment eşlemesi |
| 6 | Beş detay URL'sinin kurumsal doğrulaması | Doğrulama | Yayınlanmaz | Doğrulanınca içerik girilir |
| 7 | KVKK açık rıza metni + sürümü | Onaylı metin | **Hiçbir form açılmaz** (`consent_not_approved`); submit düğmesi yok | Form yönetiminde metin + sürüm + "onaylandı" girilir |
| 8 | Form alıcı e-posta adresi | Alıcı | Form kapalı (`recipient_not_configured`) | Adres girilir |
| 9 | Hassas alanlar (T.C. kimlik no, kimlik/CV belgesi) | Toplanmasına onay | `application` ve `job_application` kapalı (`sensitive_fields_not_approved`, `uploads_not_approved`) | İlgili onay kutuları işaretlenir |
| 10 | Form saklama süresi | Süre | **Kalıcı kişisel veri saklanmaz**; gönderim yalnız e-posta; dosyalar e-postadan sonra silinir | Kalıcı depolama istenirse ayrı bir saklama süresi kararı + yeni kod kapısı gerekir |
| 11 | Twitter/X hesabı | Hesap adı | `twitter:site` üretilmez | `mavibelge_core_seo` seçeneğine `twitter_site` |
| 12 | GPTBot (eğitim amaçlı) politikası | Kurum tercihi | robots.txt'te ayrı kural yok (yorum satırı) | Seçenekte `crawler_policy.GPTBot = allow/disallow` |
| 13 | Gerçek lokasyon/iletişim değişiklikleri | Güncel adres/telefon | Footer/iletişim doğrulanmış statik metni kullanır; `mb_lokasyon` kaydı girilince otomatik onu gösterir | Lokasyon kayıtları girilir |
| 14 | Görselli sektörlerin attachment eşlemesi | Medya kütüphanesi (10 sektör, 9 benzersiz kaynak görsel) | Görselli sektör create'i `blocked_dependency` | Yönetim panelinde **Sektör görsel eşleme** (Faz 6B4): kurum onaylı görseller yüklenip elle eşlenir; bkz. `admin-import-operations.md` §4–5 |
| 15 | Eski URL onayları (29 kuraldan 27'si `proposed`) | Kurum/GSC onayı | `proposed` kurallar **pasif**; 13 URL `needs_decision` | Onaylı kural `origin=verified` yapılıp apply |
| 16 | Nihai eski URL envanteri (≥145) | Canlı sitemap, GSC, log, analitik, backlink | Yalnız yerelde kayıtlı 57 URL sınıflandırıldı | Canlı kaynaklar sağlanınca tablo genişletilir |
| 17 | Doküman/SSS içeriği | **SSS ÇÖZÜLDÜ:** kurumca onaylanmış `tanitim-site/sss.html` (6 soru-cevap, `mb_sss` kayıtları). **Doküman AÇIK** | SSS: content aşamasıyla taslak içe aktarılır; doküman altyapısı hazır, **boş** | Editörler doküman girer |
| 18 | Üretim PHP/DB sürümleri | Sunucu yanıtı | PHP 7.3 kısıtı kabul edilmiş **yüksek risk** olarak açık | `wordpress-sunucu-bilgi-talebi.md` |

## Faz 12 / 12b — yedi sayfanın kurum kararı ÇÖZÜLDÜ (26 Eylül 2026)
Kullanıcı/kurum, yedi sayfanın kaynaklarını açıkça onayladı; içerik yerel kaynak dosyalarına künyeli alındı (bkz. `content-import-contract.md` §10). Hiçbir sayfa artık `held_pending_decision` olarak bekletilmez.

| Sayfa | Kaynak (onaylı) | Durum |
|---|---|---|
| `kvkk`, `gizlilik-politikasi` | `mavibelge.com.tr/kvkk-2/`, `/gizlilik-politikamiz/` | içerik aktarıldı (yeniden yazılmadı); eski URL'ler 301 |
| `banka-hesap-bilgileri` | `mavibelge.com.tr/banka-hesap-bilgileri/` | içerik olduğu gibi aktarıldı |
| `sinav-takvimi` | `mavibelge.pratikteorik.com/home/examcalendar` | yerel bilgilendirme + CTA |
| `sonuc-belge-sorgulama` | MYK portalı (aday_bilgi_sorgu) | yerel bilgilendirme + CTA |
| `referanslar` | canlı referans sayfası | 15 gerçek logo; **firma adları doğrulanmadı**; yayın: kayıtlar oluşup yayınlandıktan sonra (`publish_requires`) |
| `sss` | `tanitim-site/sss.html` | 6 soru-cevap; yayın: kayıtlar oluşup yayınlandıktan sonra |

Bloklayıcı olmayan uyarılar (sayfa yayınlanır, kararı bekler): form kapısı kararları (KVKK metni, alıcı e-posta, hassas alan/dosya yükleme onayı), güncel lokasyon/iletişim onayı, kaynak tarife PDF'leri, MYK/TÜRKAK belge taramaları, mevzuat bağlantıları, statik sayaç bloğu (çelişkili olduğu için aktarılmadı).
