# Faz 4 Ortak Dosya Entegrasyon Notu

> `wp-content/themes/mavibelge/style.css` başlık bloğu ortak dosyadır (AGENTS.md §3). Tema agentı bu notu hazırladı; ana orkestratör aynı görev içinde inceleyip uyguladı (aşağıda "Uygulama sonucu").

## Dosya

`wordpress-site/wp-content/themes/mavibelge/style.css`

## Değişiklik

Yalnız tema başlığı bloğundaki `Description` ve `Version` satırları:

```diff
-Description: Mavi Belge icin ozel WordPress temasi. Faz 3: tasarim tokenlari, header/footer, global bilesen katalogu. Sayfa sablonlari (Faz 4) henuz eklenmedi.
-Version: 0.3.0
+Description: Mavi Belge icin ozel WordPress temasi. Faz 4: front-page, page/archive/single/taxonomy sablonlari, 41 sayfa eslestirmesi. Veri importu (Faz 6) henuz yapilmadi.
+Version: 0.4.0
```

## Gerekçe

- Tema artık gerçek şablon ailesine sahip (`front-page.php`, `page.php`, CPT `archive-*`/`single-*`/`taxonomy-*`); "Sayfa şablonları henüz eklenmedi" ifadesi artık gerçeği yansıtmıyor.
- Sürüm numarası önceki faz alışkanlığıyla tutarlı artırıldı (0.1.x Faz 1, 0.3.x Faz 3, 0.4.x Faz 4).

## Sıra/bağımlılık

Yok — başka hiçbir dosya bu iki satıra bağımlı değil (enqueue versiyonlaması `inc/assets.php`'de `filemtime()` kullanıyor, bu başlıktan bağımsız).

## Geri alma yöntemi

Bu iki satırı önceki değerlerine (`Version: 0.3.0`, eski `Description`) geri yazmak yeterlidir.

## Uygulama sonucu

**Uygulandı** — bkz. `wordpress-site/wp-content/themes/mavibelge/style.css`. Çakışma yok (başka hiçbir agent bu görevde aynı dosyaya not bırakmadı).
