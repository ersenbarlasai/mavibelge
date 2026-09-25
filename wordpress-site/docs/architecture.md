# Architecture — `wordpress-site/`

> Bağlayıcı mimari karar: [`../raporlar/wordpress-ana-uygulama-plani.md`](../raporlar/wordpress-ana-uygulama-plani.md). Bu belge onu tekrar etmez, yalnız `wordpress-site/` klasörünün yerelde nasıl yapılandırıldığını açıklar.

## Kök konumlar

- Depo çalışma dizini: `E:\PROJELER\MaviBelge`
- WordPress özel kod kökü: `wordpress-site/`
- `tanitim-site/`: yalnız okunur görsel referans — bu klasör tarafından tüketilmez, kopyalanmaz.

## Yapı

```text
wordpress-site/
├── wp-content/
│   ├── themes/mavibelge/       Özel tema — header/footer, tasarım tokenları, bileşen kataloğu (bkz. design-system.md)
│   ├── plugins/mavibelge-core/ Özel eklenti — includes/, roles/, audit/, admin/, tests/ (bkz. content-model.md)
│   └── mu-plugins/             Must-use eklentiler (henüz boş)
├── tools/                      Yerel build/lint/import betikleri (henüz boş)
├── tests/                      Uyumluluk ve smoke testleri (henüz boş)
└── deploy/                     Dağıtım prosedürleri (henüz boş)
```

`wp-admin/`, `wp-includes/` ve diğer WordPress çekirdek dosyaları bu depoya **girmez**. Bu depo yalnız `wp-content/themes/mavibelge/` ve `wp-content/plugins/mavibelge-core/` içeriğini tutar; bir WordPress kurulumuna bu iki klasör kopyalanır/senkronize edilir.

## Faz kapsamı

Faz 1 minimal, çalışabilir bootstrap dosyalarını kurdu. Faz 2, `mavibelge-core` eklentisine içerik modelini ekledi: 7 CPT, 4 taksonomi, merkezi alan sözleşmesi, roller/yetenekler, denetim günlüğü tablosu — bkz. [`content-model.md`](content-model.md), [`roles-capabilities.md`](roles-capabilities.md). Faz 3, temaya onaylı statik tasarımın ortak kabuğunu ekledi: tasarım tokenları, header/footer, 11 parçalık global bileşen kataloğu — bkz. [`design-system.md`](design-system.md), [`component-catalog.md`](component-catalog.md). Faz 4, 41/41 statik sayfanın WordPress karşılığını kurdu: `front-page.php`, `page.php` + slug→layout haritası, 10 CPT `archive-*`/`single-*`/`taxonomy-*` şablonu, 2 `page-{slug}.php` istisnası — bkz. [`page-template-map.md`](page-template-map.md), [`template-architecture.md`](template-architecture.md); statik kod incelemesi düzeyinde kabul edildi (Faz 4 Nihai Kabul Düzeltmesi + Dokümantasyon Kapanışı). Faz 5, `mavibelge-core`'a tek genel katalog servisini (`public/class-catalog-service.php` + saf `includes/class-catalog-query.php`) ve temaya gerçek meslek/sektör arama-filtre + ücret gösterimini ekledi — bkz. [`catalog-service-contract.md`](catalog-service-contract.md), [`admin-catalog-experience.md`](admin-catalog-experience.md). Veri importu ve üçüncü taraf eklenti seçimi sonraki fazlardadır (`raporlar/wordpress-ana-uygulama-plani.md` §13, Faz 6+; açık bağımlılıklar için [`faz3-dependencies.md`](faz3-dependencies.md)).

## Agent sahiplik haritası

Ayrıntı: [`../raporlar/wordpress-agent-mimarisi.md`](../raporlar/wordpress-agent-mimarisi.md) §3. Özet:

| Yol | Sahip |
|---|---|
| `wp-content/themes/mavibelge/**` | Tema ve arayüz agentı |
| `wp-content/plugins/mavibelge-core/**` | WordPress çekirdek/eklenti agentı |
| `tools/`, `tests/`, `deploy/` | Sırasıyla veri, QA, DevOps agentları |
