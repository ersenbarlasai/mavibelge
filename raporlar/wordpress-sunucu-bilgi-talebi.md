# WordPress Faz 0 — Sunucu Bilgi Toplama Paketi

> Tarih: 10 Eylül 2026
>
> Bu belge iki bölümden oluşur. **Bölüm A** Aysima'ya doğrudan iletilebilecek kısa taleptir. **Bölüm B** sistem yöneticisinin sunucuda çalıştıracağı salt okunur kontrol listesidir.
>
> Bu görevde sunucuya bağlanılmamış, hiçbir komut canlıda çalıştırılmamıştır.
>
> Bağlam: [`wordpress-faz0-uyumluluk-matrisi.md`](./wordpress-faz0-uyumluluk-matrisi.md) §2.

---

## ⚠️ Uyarı — bu belgeyi kullanmadan önce okuyun

1. **Bölüm B'deki komutların tamamı yalnız bilgi okur.** Hiçbiri servis yeniden başlatmaz, paket kurmaz, güncelleme yapmaz, dosya silmez/taşımaz, izin değiştirmez veya yapılandırma yazmaz.
2. **Çıktıları kamuya açık bir yere yapıştırmayın.** Bazı komutlar hostname, IP, kullanıcı adı, dizin yolu ve alan adı gösterir. Çıktıları yalnız kurum içi kapalı kanaldan iletin.
3. **Parola, özel anahtar, `wp-config.php` içeriği, SSH/FTP/DirectAdmin kimlik bilgisi hiçbir koşulda paylaşılmayacaktır.** Bu belge bunları istemez; başka biri isterse vermeyin.
4. Bir komut sunucuda yoksa (`command not found`) o satırı boş bırakıp devam edin. Eksik bilgi, yanlış bilgiden iyidir.
5. Komutlar CentOS 7 + DirectAdmin varsayımıyla yazılmıştır. Yollar farklıysa bulunan gerçek yolu not edin.

---

# Bölüm A — Aysima'ya İletilecek Talep

Merhaba,

Mavi Belge'nin yeni web sitesi WordPress ile geliştirilecek. Mevcut sunucuyu, işletim sistemini, kontrol panelini ve mail altyapısını **değiştirmeyi planlamıyoruz.** Bu talep bir değişiklik talebi değil, bir **durum tespiti** talebidir.

Kurulumu doğru planlayabilmemiz için sunucunun mevcut durumuna dair bazı teknik bilgilere ihtiyacımız var. Hiçbir işlem yapılmasını istemiyoruz; yalnız bilgi okunmasını rica ediyoruz. Ekteki kontrol listesindeki komutların tamamı salt okunurdur.

Öncelik sırasına göre öğrenmemiz gerekenler:

1. **Sunucuda hangi PHP sürümleri kurulu ve kurulabilir?** DirectAdmin kullanıcı ekranında PHP 7.3 ve 5.6 görünüyor. Güncel WordPress en az PHP 7.4 gerektiriyor, PHP 8.3 ise önerilen sürüm. Aynı sunucuda ve aynı işletim sisteminde DirectAdmin CustomBuild ile daha yeni bir PHP sürümü kurulabilir mi? Bu bizim için en kritik soru.
2. Etkin PHP uzantıları ve temel PHP limitleri.
3. Web sunucusunun türü ve sürümü (Apache / LiteSpeed / Nginx) ve WordPress kalıcı bağlantılarının çalışması için gereken rewrite desteğinin açık olup olmadığı.
4. MySQL veya MariaDB'nin türü, sürümü ve varsayılan karakter seti.
5. Disk bölümleri, boş alan ve **inode** kullanımı.
6. Web dosyalarının, veritabanının ve aktif mail verisinin ayrı ayrı boyutları.
7. DirectAdmin sürümü ve güncelleme kanalı.
8. SSL sertifikalarının durumu ve otomatik yenilemenin çalışıp çalışmadığı. Ayrıca `cms-yeni.mavibelge.com.tr` için ileride sertifika alınabilecek mi?
9. Zamanlanmış görev (cron) desteği — WordPress'in kendi zamanlayıcısı yerine gerçek sistem cron kullanabilir miyiz?
10. Mevcut yedekleme yöntemi ve bir yedekten geri dönüşün nasıl yapıldığı.
11. Mail tarafında: kullanılan servis, kuyruk durumu ve alan adı için SPF, DKIM, DMARC ve PTR kayıtlarının mevcut durumu.
12. Mevcut `mavibelge.com.tr` WordPress kurulumunda yüklü tema ve eklentilerin listesi.
13. Eski adreslerin yönlendirme planı için: sunucu erişim kayıtlarına (access log) ve varsa mevcut yönlendirme kurallarına erişebilir miyiz? Ayrıca Google Search Console erişimi kimde?

**Lütfen dikkat:** Yanıtlarda parola, özel anahtar veya `wp-config.php` içeriği paylaşmanıza gerek yok; istemiyoruz.

Teşekkürler.

---

# Bölüm B — Sistem Yöneticisi Salt Okunur Kontrol Listesi

Her başlık altında çalıştırılacak komutlar ve doldurulacak yanıt alanı vardır.

---

## B1. PHP — web ve CLI sürümleri

```bash
# CLI PHP sürümü
php -v

# DirectAdmin CustomBuild ile kurulu PHP sürümleri
grep -E '^php[0-9]_release' /usr/local/directadmin/custombuild/options.conf

# Sistemde bulunan tüm PHP ikilikleri
ls -1 /usr/local/php*/bin/php 2>/dev/null
ls -1 /opt/alt/php*/usr/bin/php 2>/dev/null

# Her bulunan ikiliğin sürümü
for p in /usr/local/php*/bin/php; do [ -x "$p" ] && echo "$p -> $($p -v 2>/dev/null | head -1)"; done

# php-fpm / LSPHP süreçleri (web tarafında fiilen çalışan sürüm)
ps -eo comm,args | grep -Ei 'php-fpm|lsphp' | grep -v grep | head -20
```

**Yanıt:**

| Soru | Yanıt |
|---|---|
| CLI PHP sürümü | |
| CustomBuild'de tanımlı PHP sürümleri | |
| Web tarafında fiilen çalışan PHP sürümü | |
| Sunucuda PHP 7.4 veya üzeri kurulu mu? | |
| **PHP 8.3 CustomBuild ile kurulabilir mi? Engel varsa nedir?** | |

> Bu son satır projenin en kritik bilgisidir. Güncel WordPress (7.1) en az PHP 7.4 ister, PHP 8.3'ü önerir.

---

## B2. PHP uzantıları ve limitler

```bash
# Etkin uzantılar
php -m

# WordPress için kritik uzantıların varlığı
for e in mysqli gd imagick curl mbstring zip dom xml simplexml json openssl intl exif fileinfo iconv sodium; do
  php -m 2>/dev/null | grep -qix "$e" && echo "VAR      $e" || echo "YOK      $e"
done

# Temel limitler
php -i 2>/dev/null | grep -E '^(memory_limit|max_execution_time|upload_max_filesize|post_max_size|max_input_vars|max_input_time|date.timezone|disable_functions|open_basedir) '

# Yüklenen php.ini dosyası
php --ini | head -5
```

> Not: `php -m` ve `php -i` CLI yapılandırmasını gösterir. Web tarafı farklı olabilir. Farklıysa DirectAdmin panelinden web PHP değerleri de not edilmelidir.

**Yanıt:**

| Alan | Değer |
|---|---|
| Eksik kritik uzantılar | |
| `memory_limit` | |
| `max_execution_time` | |
| `upload_max_filesize` | |
| `post_max_size` | |
| `max_input_vars` | |
| `disable_functions` | |
| `open_basedir` | |
| CLI ve web değerleri farklı mı? | |

---

## B3. Web sunucusu ve rewrite desteği

```bash
# Hangi web sunucusu çalışıyor
ps -eo comm | sort -u | grep -Ei 'httpd|nginx|litespeed|lshttpd'

# Sürüm
httpd -v 2>/dev/null
/usr/local/lsws/bin/lshttpd -v 2>/dev/null
nginx -v 2>/dev/null

# Apache/LiteSpeed yüklü modüller (rewrite arayın)
httpd -M 2>/dev/null | grep -Ei 'rewrite|headers|expires|deflate|ssl'

# AllowOverride ayarı (.htaccess etkin mi)
grep -rn "AllowOverride" /etc/httpd/conf/ /usr/local/directadmin/data/users/*/httpd.conf 2>/dev/null | head -20

# Mevcut sitede .htaccess var mı ve içinde WordPress kuralları var mı (yalnız okur)
find /home -maxdepth 4 -name ".htaccess" -path "*public_html*" 2>/dev/null | head
```

**Yanıt:**

| Soru | Yanıt |
|---|---|
| Web sunucusu türü ve sürümü | |
| `mod_rewrite` / eşdeğeri etkin mi | |
| `.htaccess` okunuyor mu (`AllowOverride All`) | |
| Mevcut `.htaccess` dosyalarının yolu | |

> `.htaccess` **içeriğini** ayrı ve kapalı bir kanaldan iletmenizi rica ederiz — eski adres yönlendirmelerini planlamak için gereklidir. Kamuya açık yere yapıştırmayın.

---

## B4. Veritabanı

```bash
# Sürüm (parola sormayacak şekilde)
mysqld --version
mysql --version

# Sunucu değişkenleri — DirectAdmin kimlik dosyasıyla, parola ekrana yazılmadan
mysql --defaults-extra-file=/usr/local/directadmin/conf/my.cnf \
  -e "SELECT VERSION(); SHOW VARIABLES WHERE Variable_name IN
      ('version_comment','character_set_server','collation_server',
       'default_storage_engine','max_allowed_packet','innodb_file_per_table','max_connections');"

# Veritabanı boyutları (isim + boyut; içerik okumaz)
mysql --defaults-extra-file=/usr/local/directadmin/conf/my.cnf \
  -e "SELECT table_schema AS db, ROUND(SUM(data_length+index_length)/1024/1024,1) AS mb
      FROM information_schema.tables GROUP BY table_schema ORDER BY mb DESC;"
```

**Yanıt:**

| Alan | Değer |
|---|---|
| Tür (MySQL / MariaDB) ve sürüm | |
| `character_set_server` | |
| `collation_server` | |
| `default_storage_engine` | |
| `max_allowed_packet` | |
| Mevcut WordPress veritabanının boyutu (MB) | |

> WordPress'in Türkçe içerik için `utf8mb4` kullanması gerekir. Sunucu varsayılanı `utf8` (3 bayt) ise not edin — düzeltilebilir bir durumdur, sorun değildir.

---

## B5. Disk, boş alan ve inode

```bash
# Bölümler ve boş alan
df -h

# inode kullanımı — disk dolu görünmese de burası dolabilir
df -i

# SSD/SATA disklerin fiziksel ayrımı
lsblk -o NAME,SIZE,ROTA,TYPE,MOUNTPOINT
```

**Yanıt:**

| Bölüm | Toplam | Kullanılan | Boş | inode % |
|---|---|---|---|---|
| | | | | |

| Soru | Yanıt |
|---|---|
| 250 GB SSD hangi dizinlere bağlı? | |
| 1 TB SATA hangi dizinlere bağlı? | |
| inode kullanımı %80'i geçen bölüm var mı? | |

---

## B6. Web, veritabanı ve mail verisinin ayrı boyutları

```bash
# Kullanıcı ana dizinleri
du -sh /home/* 2>/dev/null | sort -h | tail -20

# Web dosyaları
du -sh /home/*/domains/*/public_html 2>/dev/null

# Mail verisi (kullanıcı bazında)
du -sh /home/*/imap 2>/dev/null | sort -h | tail -20

# Mail toplamı
du -sh /home/*/imap 2>/dev/null | awk '{print $1}' | head -50

# Veritabanı dizini toplamı
du -sh /var/lib/mysql 2>/dev/null

# Mail dosya sayısı (inode etkisi için)
find /home/*/imap -type f 2>/dev/null | wc -l
```

**Yanıt:**

| Veri | Boyut |
|---|---|
| Web dosyaları toplamı | |
| Veritabanı dizini toplamı | |
| Aktif mail verisi toplamı | |
| Mail dosya sayısı (adet) | |
| En büyük 3 posta kutusu | |

> Mail arşivleme planı için gereklidir. **İki doğrulanmış kopya oluşmadan sunucudan hiçbir mail silinmeyecektir.**

---

## B7. DirectAdmin sürümü ve güncelleme kanalı

```bash
# Sürüm
/usr/local/directadmin/directadmin v 2>/dev/null

# CustomBuild sürümü
/usr/local/directadmin/custombuild/build version 2>/dev/null

# Güncelleme kanalı ve derleme seçenekleri (parola içermez)
grep -E '^(version|releases|php[0-9]_release|php[0-9]_mode|webserver|mysql_inst|mysql_ver|redis|opcache)' \
  /usr/local/directadmin/custombuild/options.conf 2>/dev/null
```

**Yanıt:**

| Alan | Değer |
|---|---|
| DirectAdmin sürümü | |
| CustomBuild sürümü | |
| Güncelleme kanalı (`releases`) | |
| `webserver` değeri | |
| Tanımlı PHP sürümleri ve modları | |

---

## B8. SSL sertifikaları ve otomatik yenileme

```bash
# Alan adları için sertifika bitiş tarihleri (yalnız okur)
for f in /usr/local/directadmin/data/users/*/domains/*.cert; do
  [ -f "$f" ] && echo "== $f" && openssl x509 -in "$f" -noout -subject -issuer -enddate 2>/dev/null
done

# DirectAdmin Let's Encrypt ayarı
grep -E '^letsencrypt' /usr/local/directadmin/conf/directadmin.conf 2>/dev/null

# Otomatik yenileme görevi tanımlı mı
grep -rn "letsencrypt" /etc/cron.d/ /etc/crontab 2>/dev/null | head
```

**Yanıt:**

| Alan | Değer |
|---|---|
| `mavibelge.com.tr` sertifikası — veren ve bitiş tarihi | |
| Let's Encrypt etkin mi | |
| Otomatik yenileme çalışıyor mu | |
| **`cms-yeni.mavibelge.com.tr` için sertifika alınabilir mi?** | |

---

## B9. Cron ve zamanlanmış görevler

```bash
# Sistem cron dosyaları
ls -la /etc/cron.d/ /etc/crontab 2>/dev/null

# Cron servisi çalışıyor mu
systemctl is-active crond 2>/dev/null

# Web kullanıcısının mevcut cron görevleri (kullanıcı adını yazarak)
crontab -l -u <kullanici_adi> 2>/dev/null
```

**Yanıt:**

| Soru | Yanıt |
|---|---|
| `crond` çalışıyor mu | |
| Web kullanıcısı için cron tanımlanabilir mi | |
| DirectAdmin panelinden cron eklenebiliyor mu | |
| Mevcut WordPress cron görevi var mı | |

> WordPress'in kendi zamanlayıcısı (WP-Cron) yalnız sayfa ziyaret edildiğinde çalışır ve düşük trafikte güvenilir değildir. Gerçek sistem cron tercih edilir.

---

## B10. Yedekleme ve geri yükleme

```bash
# DirectAdmin yedek ayarları
ls -la /usr/local/directadmin/data/admin/ 2>/dev/null | grep -i backup

# Yedeklerin nerede tutulduğu
grep -E '^backup' /usr/local/directadmin/conf/directadmin.conf 2>/dev/null

# Yedek dizini içeriği (isim ve tarih; içerik açılmaz)
ls -lah /home/admin/admin_backups/ 2>/dev/null | head -20
ls -lah /backup/ 2>/dev/null | head -20
```

**Yanıt:**

| Soru | Yanıt |
|---|---|
| Otomatik yedek alınıyor mu | |
| Sıklığı | |
| Nerede saklanıyor (aynı sunucu mu, harici mi) | |
| Kaç kuşak saklanıyor | |
| **Daha önce yedekten geri dönüş denendi mi?** | |
| Geri dönüş süresi tahmini | |

> `wordpress-ana-uygulama-plani.md` §16'ya göre yedekten geri dönüş denenmeden canlı yayın yapılmaz.

---

## B11. Mail servisleri, kuyruk ve DNS kimlik doğrulama kayıtları

```bash
# Çalışan mail servisleri
systemctl is-active exim dovecot 2>/dev/null

# Kuyrukta bekleyen mesaj sayısı (mesaj içeriği okunmaz)
exim -bpc 2>/dev/null

# SPF ve DMARC kayıtları
dig +short TXT mavibelge.com.tr
dig +short TXT _dmarc.mavibelge.com.tr

# DKIM kaydı (seçici adı farklı olabilir; DirectAdmin genelde "x" kullanır)
dig +short TXT x._domainkey.mavibelge.com.tr

# PTR (ters DNS) kaydı
dig +short -x 5.250.247.218

# MX kayıtları
dig +short MX mavibelge.com.tr
```

**Yanıt:**

| Kayıt | Mevcut değer | Durum |
|---|---|---|
| MX | | |
| SPF (TXT) | | |
| DKIM | | |
| DMARC | | |
| PTR (`5.250.247.218`) | | |
| Kuyrukta bekleyen mesaj sayısı | | |
| Mail servisi (exim/dovecot) çalışıyor mu | | |

---

## B12. Mevcut WordPress kurulumunun envanteri

> WP-CLI kuruluysa aşağıdaki komutlar yalnız listeler; hiçbir güncelleme veya kurulum yapmaz. Kurulu değilse **kurmayın**, ikinci bloktaki dizin listelemesini kullanın.

```bash
# WP-CLI varsa (SALT LİSTELEME — hiçbir güncelleme yapmaz)
cd /home/<kullanici>/domains/mavibelge.com.tr/public_html
wp core version --allow-root
wp theme list --allow-root
wp plugin list --allow-root
wp option get siteurl --allow-root
wp option get home --allow-root

# WP-CLI yoksa — dizin listeleme yeterlidir
ls -1 wp-content/themes/
ls -1 wp-content/plugins/
ls -1 wp-content/mu-plugins/ 2>/dev/null
grep -m1 "wp_version =" wp-includes/version.php
du -sh wp-content/uploads/
find wp-content/uploads -type f | wc -l
```

**Yanıt:**

| Alan | Değer |
|---|---|
| WordPress sürümü | |
| Etkin tema | |
| Kurulu tema listesi | |
| Etkin eklenti listesi | |
| Pasif eklenti listesi | |
| `mu-plugins` var mı | |
| `uploads` boyutu | |
| `uploads` dosya sayısı | |

**Yapmayın:** güncelleme, eklenti kurma/silme, tema değiştirme, veritabanı yazma.

---

## B13. Eski adres envanteri kaynakları

Bu bölüm komut değil, erişim tespitidir. Yeni sitede eski adreslerin kırılmaması için gereklidir.

```bash
# Erişim kayıtlarının yeri ve kaç günlük tutulduğu (içerik okunmaz, yalnız listelenir)
ls -lah /var/log/httpd/ 2>/dev/null | head -20
ls -lah /home/*/domains/*/logs/ 2>/dev/null | head -20
```

**Yanıt:**

| Soru | Yanıt |
|---|---|
| Erişim kayıtları (access log) nerede tutuluyor | |
| Kaç günlük/aylık geçmiş saklanıyor | |
| Kayıtların bir kopyası bize verilebilir mi | |
| Google Search Console erişimi kimde | |
| Google Analytics veya başka analitik var mı, erişimi kimde | |
| Mevcut sitede yönlendirme eklentisi kullanılıyor mu | |
| `https://mavibelge.com.tr/wp-sitemap.xml` erişilebilir durumda mı | |

> Bu kaynaklar olmadan eski adreslerin yönlendirme planı tamamlanamaz. Yerel depoda yalnız **57** eski adres kayıtlıdır; gerçek sayı en az **145**'tir.

---

## B14. Genel sistem bilgisi

```bash
uname -a
cat /etc/redhat-release 2>/dev/null
free -h
nproc
uptime
```

**Yanıt:**

| Alan | Değer |
|---|---|
| Çekirdek sürümü | |
| Dağıtım sürümü | |
| Toplam / boş RAM | |
| CPU çekirdek sayısı | |
| Çalışma süresi ve yük ortalaması | |

---

## Toplama Sonrası

Yanıtlar geldiğinde [`wordpress-faz0-uyumluluk-matrisi.md`](./wordpress-faz0-uyumluluk-matrisi.md) §2 ve §11 güncellenecek, `Doğrulanamadı` satırları kesin karara bağlanacaktır. WordPress ve eklenti sürüm kilidi ancak bundan sonra verilecektir.

**En kritik tek yanıt: B1 tablosundaki "PHP 8.3 CustomBuild ile kurulabilir mi?" satırı.**
