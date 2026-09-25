# admin/

Admin screens, meta boxes, list-table columns and notices (Faz 2):

- `class-meta-boxes.php` — schema-driven meta box + save handler for all 7 content types.
- `class-list-columns.php` — extra admin-list columns per content type.
- `class-admin-notices.php` — transient-backed Turkish notice queue.
- `class-settings.php` — "Aktif Tarife Dönemi" screen.
- `assets/meta-boxes.js` — progressive-enhancement script for the ücret price-options repeater.

No wp-admin menu beyond what WordPress generates for each registered post type, plus the single "Aktif Tarife Dönemi" submenu under Ücret Kaydı.
