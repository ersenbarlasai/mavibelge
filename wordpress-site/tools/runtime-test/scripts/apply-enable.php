<?php
/**
 * YALNIZ yerel test — `wp --require=/opt/mb-runtime/apply-enable.php ...`
 * Bu tek WP-CLI sürecinde MAVIBELGE_IMPORT_APPLY_ENABLED === true tanımlar.
 * Ana (`wp_`) test veritabanında GERÇEK manifest planının apply kapısında
 * REDDEDİLDİĞİNİ (hiçbir yazma/tablo oluşturma olmadan) göstermek için
 * kullanılır. Üretime kopyalanmaz.
 */
define( 'MAVIBELGE_IMPORT_APPLY_ENABLED', true );
