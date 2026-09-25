# Faz 6B3 için Yalnız Taslak Not — Apply, Batch/Checkpoint, Audit, Rollback

> Bu bir **taslak nottur** — kod içermez, Faz 6B3 kodu bu görevde yazılmadı. Amaç yalnız sınırı işaretlemek.
>
> **Kapanış kaydı (24 Eylül 2026):** Bu taslaktaki dört madde (apply, batch/checkpoint, audit, rollback) Faz 6B3 Apply Kodlaması turunda kodlandı. Doğrulama yalnız yerel, silinebilir test ortamında yapıldı. Bağlayıcı belge: [`faz6b3-apply-mimarisi.md`](./faz6b3-apply-mimarisi.md). Aşağıdaki metin tarihsel kayıttır.

Gerçek `--apply` çalışması, Faz 6B1'in ürettiği dry-run planını (bkz. `faz6b1-dry-run-cikti-semasi.md`) gerçek WordPress yazma çağrılarına (`wp_insert_post`, `wp_update_post`, `wp_insert_term`, `update_post_meta`, `update_term_meta`, ...) dönüştürür. Bu, Faz 6B1'in VE Faz 6B2'nin kapsamı DIŞINDADIR.

## Faz 6B3'ün ele alması gerekenler (yalnız liste, tasarım değil)

1. **Apply akışı** — yalnız `create`/`update` kararlı girdiler yazılır; `conflict*`/`blocked_dependency`/`invalid` hiçbir zaman otomatik yazılmaz.
2. **Batch/checkpoint** — `faz6a-faz6b-import-sozlesmesi-ve-rollback-taslagi.md` §6 (sabit küçük batch, WP-CLI ve admin fallback'in AYNI checkpoint formatı).
3. **Audit kaydı** — mevcut `MaviBelge_Core_Audit_Log` ile her `create`/`update`/`conflict` olayı (§8 aynı belge).
4. **Rollback** — oluşturulan ID listesi + güncellenen kayıtların önceki meta değerleri (§9 aynı belge) — gerçek veritabanı yedeğinin YERİNE GEÇMEZ.
5. **Gerçek `--apply` öncesi önkoşul (KORUNMALI, gevşetilmemeli):** (a) tam veritabanı yedeği, (b) bu yedekten geri dönüşün fiilen denenmesi, (c) yetkili bir insanın açık onayı — üçü de tamamlanmadan gerçek `--apply` çalıştırılmaz (bkz. aynı belge §12, AGENTS.md §5/§6).
6. **İki-kez-çalıştırma testi** (§10 aynı belge) — gerçek WordPress runtime gerektirir, bu görevde YAPILMADI.

Bu liste sıralı bir plan değildir; Faz 6B3 başlamadan önce ayrı bir görev promptu ile ayrıntılandırılacaktır.

## Güncelleme — Faz 6B3 Önkoşul ve Yazma Güvenliği Kapanışı (23 Eylül 2026)

Yukarıdaki listenin 1. maddesi (apply akışı) için bağlayıcı, **saf ve test edilebilir** sözleşmeler artık kodda var. Gerçek yazma yapmıyorlar:

- `MaviBelge_Core_Import_Write_Payload::prepare()` — atomik yük doğrulama;
- `MaviBelge_Core_Import_Apply_Eligibility::evaluate_plan()`, `toctou_recheck()`, `build_rollback_record()`, `rollback_allowed()`.

Ayrıntı: [`faz6b3-yazma-guvenligi-sozlesmesi.md`](./faz6b3-yazma-guvenligi-sozlesmesi.md). Faz 6B3 apply kodu hâlâ **yazılmadı**. 5. maddedeki önkoşullar (yedek, geri dönüş provası, açık onay) değişmeden geçerlidir.
