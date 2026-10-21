# Sınav Ücretleri — Yönetici Kullanım Yönergesi (eklenti 0.5.6 / tema 0.6.11)

Ücretler WordPress yönetiminde **Ücret Kayıtları** menüsündedir. Ziyaretçi sayfası (Sınav Ücretleri) ve yeterlilik detayları bu kayıtlardan
otomatik üretilir; temada fiyat yazılı değildir. Kaydettiğiniz değişiklik bir sonraki sayfa açılışında görünür.

## Bir ücretin ziyaretçiye görünmesi için (hepsi gerekli)

1. WordPress durumu **Yayında** (sağdaki Yayımla kutusu).
2. **Kayıt Durumu = Aktif**.
3. **Tarife Dönemi**, Ücret Kayıtları → **Aktif Tarife Dönemi** ekranındaki değerle aynı (ör. `2026`).
4. Bugün **Geçerlilik Başlangıcı/Bitişi** arasında (boş = sınırsız).
5. En az bir geçerli **fiyat seçeneği** ve listeden seçilmiş bir **Sektör**.

## Online başvuru meslek listesi (eklenti 0.5.6 / tema 0.6.11)

**Başvuru meslekleri, ziyaretçiye açık aktif ücret kayıtlarından otomatik oluşur.** Ayrı bir başvuru meslek listesi yoktur;
yönetilecek tek yer **Ücret Kayıtları**dır.

- Yukarıdaki beş koşulu sağlayan (Sınav Ücretleri sayfasında görünen) **her ücret kaydı**, Online Başvuru formundaki
  "Başvurulacak meslek/yeterlilik" listesinde **bir** seçenek olur. Fiyat seçeneklerinin sayısı önemli değildir.
- Etiket kayıttan üretilir: **Meslek Adı — Seviye N — MYK Kodu** (ör. `Makine Bakımcı — Seviye 3 — 10UY0002-3/03`). Kaynak MYK
  kodu boşsa etikette kod yoktur (ör. `Dokuma Operatörü — Seviye 3`). Aynı yeterliliğe bağlı iki tarife (iki Mobil Vinç kaydı) ayrı
  iki seçenektir.
- Ücret eklemek, düzenlemek, taslağa almak, Kayıt Durumunu değiştirmek, çöpe atmak veya Aktif Tarife Dönemini değiştirmek listeye bir
  sonraki sayfa açılışında yansır. Geçerlilik tarihi dolan kayıt, o gün kayıt yapılmasa da listeden kendiliğinden düşer.
- Ziyaretçi formu açtıktan sonra seçtiği ücret uygunluğunu yitirirse (taslak, pasif, silinmiş, başka dönem, süresi dolmuş)
  gönderim reddedilir ve "Seçtiğiniz meslek/tarife artık online başvuruya açık değil" iletisiyle yeniden seçim istenir.
- Uygun ücret kaydı hiç yoksa form çizilmez; ziyaretçiye telefon/e-posta alternatifi gösterilir. Formun genel açık/kapalı ayarı
  (Ayarlar → Mavi Belge Formlar) bundan bağımsızdır.
- Başvuru e-postasında seçilen tarife; ücret kaydı numarası, meslek adı, seviye, MYK kodu (yoksa "Belirtilmemiş") ve varsa bağlı
  yeterlilik (adı, kodu, kayıt numarası; yoksa "Bağlantısız") ile yazılır.

### Bir ücret neden başvuru listesinde yok?

Liste, Sınav Ücretleri sayfasıyla **aynı** kuralı kullanır; bu yüzden önce o sayfada kaydı arayın.

1. **Sınav Ücretleri** sayfasında meslek adıyla arayın. Orada da yoksa sorun ücret kaydındadır; varsa sayfayı yenileyin (önbellek bir
   sonraki açılışta yenilenir) ve formu yeniden açın.
2. Ücret Kayıtları listesinde kaydı açın ve yukarıdaki beş koşulu tek tek kontrol edin: Yayında mı, Kayıt Durumu Aktif mi, Tarife
   Dönemi Aktif Tarife Dönemi ekranındakiyle aynı mı, bugün geçerlilik aralığında mı, fiyat seçeneği ve sektör geçerli mi.
3. Araçlar → İçe Aktarım Dry-Run → **6) Ücret yayını → Ücret yayın özetini göster**: satır tablosunda kaydın durumu ve nedeni
   (`görünmez` satırında engel: dönem, geçerlilik tarihi, fiyat listesi, sektör) yazılır. Bu ekran salt okunurdur.
4. Bağlantı `?meslek=<MYK kodu>` ile geldiyse ve koda iki tarife bağlıysa form hiçbirini seçmez; ziyaretçiye iki tarifenin adı
   gösterilir. Tekil seçim için yeterlilik detayındaki "Bu tarifeyle başvur" bağlantısı (`?ucret=<ücret kaydı numarası>`) kullanılır.

## Yeni ücret ekleme

1. Ücret Kayıtları → **Yeni Ücret Kaydı Ekle**. Başlığa meslek adını yazın.
2. **Meslek Adı**, **Seviye**, **Sektör** (listeden), **Tarife Dönemi** (ör. 2026), **Fiyatlandırma Türü** (Tek fiyat / Birim bazlı / Paket / Çok seçenekli).
3. **Bağlı Yeterlilik:** arama kutusuna meslek adı, MYK kodu veya seviye yazıp seçin. Gerçek bir eşleşme yoksa **Bağlantısız** bırakın — ücret listede
   görünür ama detay bağlantısı ve "MYK kodu" oluşmaz. **Kaynak MYK Kodu** alanını yalnız kaynak tarifede kod varsa doldurun (seçim bu alanı değiştirmez).
4. **KDV Dahil** (işaretli), **KDV Oranı (%)** = 20 ve **MYK Belge Basım Ücreti** = 1.500,00 yeni kayıtta hazır gelir.
5. Fiyat seçeneklerini girin (aşağıda), **Kayıt Durumu = Aktif** seçip **Yayımla**.

## Fiyat değiştirme ve seçenek düzenleme

- **Tutar (TL, KDV dahil):** ziyaretçinin ödeyeceği tutarı yazın: `17.000` veya `17.000,00` (nokta binlik, virgül kuruş). KDV ayrıca **eklenmez**.
  Kutunun altındaki **Kaydedilecek** satırı tutarın nasıl okunacağını gösterir; "Geçersiz biçim" görürseniz düzeltin.
- **Etiket** (ör. "Sınav ücreti", "B1-Öndeyici Bakım") ve **Birimler** (virgülle: `A1, A2, B1`) ziyaretçiye aynen gösterilir.
- **+ Seçenek Ekle** yeni satır açar; **↑ / ↓** sırayı değiştirir; **Kaldır** satırı siler. Değişiklik **Güncelle** ile kaydedilir.
- Satırlardan biri hatalıysa (geçersiz tutar, etiketsiz tutar, yalnız birim yazılmış satır) **hiçbir fiyat değişikliği kaydedilmez**, ekranın üstünde
  hata görünür ve önceki liste korunur. Min./Maks. tutar kendiliğinden hesaplanır.
- Belge basım ücreti fiyat seçeneklerine eklenmez; ziyaretçiye ayrı cümleyle gösterilir.

## KDV ve belge basım ücretini değiştirme

- **KDV Oranı (%)** (0–100 tam sayı) ve **MYK Belge Basım Ücreti (TL)** kayıt bazındadır. Ziyaretçi cümlesi bu değerlerden üretilir:
  "Fiyatlara %20 KDV dahildir. 1.500 TL MYK belge basım ücreti fiyatlara dahil değildir." Ekranın altındaki önizleme kaydedilmiş değeri gösterir.
- Basım ücretini boş bırakırsanız basım cümlesi gösterilmez.

## Aktiflik ve yayından kaldırma

- Geçici olarak gizlemek: **Kayıt Durumu = Taslak** veya **Arşivlendi** (ya da WordPress durumunu Taslağa alın). Kayıt silinmez.
- Kayıt Durumunu **Aktif/Arşivlendi** yapmak için yayımlama yetkisi gerekir; fiyat editörü rolü bunu yapamaz (sunucu geri alır ve uyarır).

## Tarife dönemi

- Ücret Kayıtları → **Aktif Tarife Dönemi**: ziyaretçiye hangi dönemin gösterileceğini belirler (yalnız yönetici/site yöneticisi).
  Yeni döneme geçerken önce yeni dönemin ücretlerini hazırlayın (Tarife Dönemi alanı yeni dönem, Aktif); sonra aktif dönemi değiştirin.
  Dönem değişince eski dönemin ücretleri listeden kalkar (silinmez).

## Toplu ilk yayın (yalnız kurulum/aktarım penceresinde)

İçe aktarılan ücretler taslak gelir. Toplu yayın Araçlar → İçe Aktarım Dry-Run → **6) Ücret yayını** bölümünden, yedekten sonra ve sistem
yöneticisinin açtığı sabitlerle yapılır (`docs/admin-import-operations.md` §8.3; adım adım: `docs/ucret-staging-uygulama-yonergesi.md`). Panelden
değiştirdiğiniz kayıtlar bu araçla ezilmez. Özet sayıları sitenin gerçek kayıtlarından hesaplanır; elle eklenmiş (manifest dışı) ücret kayıtları
ayrıca listelenir ve araç onlara dokunmaz.
