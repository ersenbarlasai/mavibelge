# Roller ve Yetenek Matrisi — `mavibelge-core` (Faz 2)

> Kaynak: `wp-content/plugins/mavibelge-core/roles/class-roles.php`. Kurulum `MaviBelge_Core_Roles::install()` ile idempotent yapılır; `mavibelge_core_roles_version` option'ı sürüm eşleşirse kurulum tekrar çalışmaz (görev kartı 02 §10, brief §10/§14).
>
> **Düzeltme (sürüm 1 → 2):** v1'de `content_editor_caps()` yalnız `mb_ucret`'i hariç tutuyordu; `mb_yeterlilik` de hariç tutulması gerekirken yanlışlıkla dahil kalmıştı, bu yüzden `mb_content_editor` rolü yeterlilik kayıtlarını düzenleyip silebiliyordu. v2 bunu hem tanımda düzeltir hem de v1'den yükseltilen kurulumlarda `edit_mb_yeterlilikler`/`delete_mb_yeterlilikler` cap'lerini **açıkça kaldırır** (`remove_stale_v1_content_editor_yeterlilik_caps()`); başka hiçbir cap dokunulmaz.

## Roller

| Rol anahtarı | Türkçe adı | Özet |
|---|---|---|
| `administrator` (yerleşik) | — | Tüm `mb_*` içerik türleri üzerinde tam yetki + taksonomi/dönem yönetimi. |
| `mb_site_manager` | Mavi Belge Site Yöneticisi | Tüm 7 içerik türünde tam CRUD + yayımlama + taksonomi terim yönetimi + aktif tarife dönemi. Tema/eklenti dosyası kuramaz/düzenleyemez (`edit_theme_options`, `install_plugins`, `edit_plugins`, `edit_files` **verilmedi**). |
| `mb_content_editor` | Mavi Belge İçerik Editörü | Sayfa, Haber, Doküman, Referans, Lokasyon, SSS için taslak oluşturma/düzenleme/silme. **Ücrete ve Yeterliliğe hiç erişimi yok.** Yayımlama yetkisi yok (`publish_*` verilmedi) — yayın, `mb_reviewer`'a bırakılır. Haberde en fazla `draft`/`in_review` seçebilir; `approved`/`rejected` sunucu tarafında reddedilir. |
| `mb_price_editor` | Mavi Belge Fiyat Editörü | Yalnız `mb_ucret` oluşturma/düzenleme/silme. `_mb_record_status`'ü `active`/`archived` yapamaz (yalnız `publish_mb_ucretler` yetkisiyle mümkün — bu rolde yok); aktif tarife dönemini değiştiremez (`mb_manage_tariff_period` yok). Her iki kısıt da sunucu tarafında zorunludur, yalnız arayüzde gizlenmez. |
| `mb_reviewer` | Mavi Belge Kontrol Eden/Onaylayan | Tüm 7 içerik türünde (kendi ve başkasının) düzenleme + yayımlama + private okuma. Haberi `approved`/`rejected` yapabilen tek roldür (`mb_content_editor`/`mb_price_editor` hariç); bu geçişte `_mb_reviewer_user_id`/`_mb_reviewed_at` sunucu tarafından otomatik yazılır. Sistem ayarlarına (`manage_options`, taksonomi terim yönetimi, tema/eklenti) erişimi yok. |

## Özel (custom) yetenekler

| Yetenek | Amaç | Kimde |
|---|---|---|
| `mb_manage_taxonomies` | `mb_sektor`, `mb_haber_turu`, `mb_dokuman_kategori`, `mb_sss_kategori` terim oluşturma/düzenleme/silme (`manage_terms`/`edit_terms`/`delete_terms`) | `administrator`, `mb_site_manager` |
| `mb_manage_tariff_period` | `mb_active_tariff_period` option'ını değiştirme (Ücret Kaydı → Aktif Tarife Dönemi ekranı) | `administrator`, `mb_site_manager` |

Bir içerik türünü düzenleyebilen herkes, o türe bağlı taksonomiye **mevcut terimi atayabilir** (`assign_terms` capability'si o türün `edit_{plural}` yeteneğine eşlenmiştir); yeni terim oluşturma ayrıca `mb_manage_taxonomies` gerektirir.

## İçerik türü başına primitive capability seti

Her post type kendi `capability_type` çiftini taşır (`register_post_type()` ile `map_meta_cap => true`); WordPress'in standart 10 primitive cap deseni uygulanır:

```text
edit_{plural}, edit_others_{plural}, publish_{plural}, read_private_{plural},
delete_{plural}, delete_private_{plural}, delete_published_{plural},
delete_others_{plural}, edit_private_{plural}, edit_published_{plural}
```

| Post type | Plural cap kökü |
|---|---|
| `mb_yeterlilik` | `mb_yeterlilikler` |
| `mb_ucret` | `mb_ucretler` |
| `mb_haber` | `mb_haberler` |
| `mb_dokuman` | `mb_dokumanlar` |
| `mb_referans` | `mb_referanslar` |
| `mb_lokasyon` | `mb_lokasyonlar` |
| `mb_sss` | `mb_sss_kayitlari` |

## Rol × yetenek matrisi (özet)

| | `mb_yeterlilik` | `mb_ucret` | `mb_haber` | `mb_dokuman` | `mb_referans` | `mb_lokasyon` | `mb_sss` | Sayfa (core `page`) | Taksonomi terimi | Dönem |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| `administrator` | tam | tam | tam | tam | tam | tam | tam | (yerleşik) | evet | evet |
| `mb_site_manager` | tam | tam | tam | tam | tam | tam | tam | — | evet | evet |
| `mb_content_editor` | **yok** | **yok** | düzenle/sil (yalnız draft/in_review) | düzenle/sil (yayımlayamaz) | düzenle/sil (yayımlayamaz) | düzenle/sil (yayımlayamaz) | düzenle/sil (yayımlayamaz) | düzenle/sil (yayımlayamaz) | hayır | hayır |
| `mb_price_editor` | yok | düzenle/sil (active/archived **hariç** — bkz. not) | yok | yok | yok | yok | yok | yok | hayır | hayır |
| `mb_reviewer` | düzenle+yayımla (kendi/başkası) | düzenle+yayımla (kendi/başkası) | düzenle+yayımla (kendi/başkası, onaylayabilir/reddedebilir) | düzenle+yayımla (kendi/başkası) | düzenle+yayımla (kendi/başkası) | düzenle+yayımla (kendi/başkası) | düzenle+yayımla (kendi/başkası) | yok | hayır | hayır |

"tam" = 10 primitive cap'in tamamı (kendi/başkası, yayımla, sil, private okuma dahil).

**Alan bazlı ek kısıtlar (capability_type primitive cap'lerinin ötesinde, `admin/class-meta-boxes.php` içinde uygulanır):**

- `mb_ucret._mb_record_status` → `active`/`archived`: yalnız `publish_mb_ucretler`. `mb_price_editor`'ın `edit_mb_ucretler` yetkisi olması bunu **kapsamaz**; ayrı bir kontrol noktasıdır (`guard_ucret_record_status()`).
- `mb_haber._mb_approval_status` → `approved`/`rejected`: yalnız `publish_mb_haberler` (`guard_haber_approval_status()`). Yetkisiz bir POST denemesi, genel alan döngüsünün yazdığı değeri eski haline geri döndürür.

## Kurulum davranışı

- `add_role()` yalnız rol yoksa çağrılır; var olan rolün elle yapılmış ek yetkileri **korunur** (yalnız eksik cap'ler eklenir, hiçbir cap kaldırılmaz).
- Kurulum, `mavibelge_core_roles_version` option'ı güncel sürümle eşleşirse **hiçbir şey yapmadan** döner — her istekte ağır `add_cap` döngüsü çalışmaz.
- Aktivasyonda (`class-installer.php`) ve her `admin_init`'te (versiyon kontrolüyle, ucuz) tetiklenir; böylece aktivasyon kancası tetiklenmeden yapılan bir sürüm yükseltmesi de yakalanır.
- Deaktivasyonda rol/yetenek **silinmez** (`class-deactivator.php` yalnız `flush_rewrite_rules()` çağırır).
- Sürüm geçmişi: `1` (ilk Faz 2 teslimi, `mb_content_editor`'a yanlışlıkla yeterlilik cap'i verildi) → `2` (bu düzeltme; hatalı cap'ler `remove_stale_v1_content_editor_yeterlilik_caps()` ile kaldırıldı). `install()`, yükseltmeyi yalnız kurulu sürüm tam olarak `'1'` ise tetikler.
