# Kurum Kararı Bekleyen Konular ve Etkinleştirdikleri Alanlar

> Bu belge **kod eksikliği değil**, kurumsal/operasyonel önkoşulları listeler. Hiçbiri uydurulmadı; her biri için güvenli
> varsayılan uygulanmıştır (kapalı/pasif/boş). Karar geldiğinde yalnız **veri/ayar girilerek** etkinleşir.

| # | Karar | Bilinmeyen | Güvenli varsayılan (şu an) | Karar gelince ne etkinleşir |
|---|---|---|---|---|
| 1 | Gerçek referans kurumları | Hangi kuruluşlar gerçek referans | 12 temsili kayıt **taslak** içe aktarılabilir; `_mb_reference_status=representative`; sayfada "Temsili görsel" notu; logo yok | Gerçek kurum kaydı `real` + onaylı logo eklenince yayınlanır |
| 2 | Sınav takvimi bağlantısı | Resmî adres | Sayfa dürüst "bağlantı henüz yok" durumunda; `href="#"` yok | Sayfa içeriğine gerçek bağlantı girilir |
| 3 | MYK sorgu bağlantısı | Resmî adres | Aynı | Aynı |
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
| 14 | Görselli sektörlerin attachment eşlemesi | Medya kütüphanesi | Görselli sektör create'i `blocked_dependency` | Attachment ID eşlemesi |
| 15 | Eski URL onayları (29 kuraldan 27'si `proposed`) | Kurum/GSC onayı | `proposed` kurallar **pasif**; 13 URL `needs_decision` | Onaylı kural `origin=verified` yapılıp apply |
| 16 | Nihai eski URL envanteri (≥145) | Canlı sitemap, GSC, log, analitik, backlink | Yalnız yerelde kayıtlı 57 URL sınıflandırıldı | Canlı kaynaklar sağlanınca tablo genişletilir |
| 17 | Doküman/SSS içeriği | Kurum onaylı içerik | Yönetim altyapısı hazır, **boş** | Editörler içerik girer |
| 18 | Üretim PHP/DB sürümleri | Sunucu yanıtı | PHP 7.3 kısıtı kabul edilmiş **yüksek risk** olarak açık | `wordpress-sunucu-bilgi-talebi.md` |
