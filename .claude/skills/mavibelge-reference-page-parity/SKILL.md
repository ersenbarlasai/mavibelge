---
name: mavibelge-reference-page-parity
description: Use when making a Mavi Belge WordPress page, archive or taxonomy screen visually match its approved static reference in tanitim-site/*.html - route mapping, presentation families, hero/breadcrumb/section/card/table/form comparison, and real-browser proof across desktop/tablet/mobile. Not for data import or plugin business logic.
---

# Statik Referans ↔ WordPress Görsel Uyum

Önce `mavibelge-project-guardrails` uygulanır. Bu skill yalnız SUNUM katmanını kapsar.

## 1. Rota eşleme
- Statik `tanitim-site/<ad>.html` → WordPress rotası: `page` slug'ı, CPT arşivi (`/haberler/`, `/dokumanlar/`) veya taksonomi (`/haber-turu/duyuru/`). Gerçek yol `mavibelge_url()` ile çözülür; `/index.php/` ve temiz yapı ikisi de çalışmalı.
- Eşleme tek merkezde tutulur (tema `inc/page-layouts.php` sunum kaydı). Sayfa başına kopya `page-<slug>.php` YAZILMAZ; en fazla birkaç ortak gövde ailesi.

## 2. Aile çıkarımı
- Statik sayfaları DOM yapısına göre grupla: kahraman + düz metin (prose), kahraman + adım/kart bölümleri, kahraman + liste/filtre/sayfalama (arşiv), kahraman + form.
- Aile = gövde şablon parçası + CSS değiştiricisi. Yeni sayfa aileye kayıt satırıyla eklenir.

## 3. Aktarılan / aktarılmayan
- AKTARILIR: kahraman (koyu lacivert `.page-hero`, kırıntı, eyebrow, tek H1, lead), container genişliği, bölüm aralıkları, H2/H3 hiyerarşisi, kart/tablo/liste/adım/form/filtre/sayfalama görünümü, kırılımlar.
- AKTARILMAZ: statik metin/veri. WordPress başlığı, editör içeriği, CPT sorgusu, form DTO'su, banka bilgisi olduğu gibi kalır. Eyebrow/lead yalnız statik referansta doğrulanmış sabit gezinme metnidir.
- Sabit yükseklik, ekran görüntüsüne benzetmek için boşluk, statik `.html` bağlantısı yasak.

## 4. Karşılaştırma listesi (her sayfa)
Kahraman arka planı + dekor · kırıntı (Anasayfa'dan) · eyebrow · H1 · lead · container max-width · bölüm üst/alt boşluğu · H2/H3 sırası · kart ızgarası sütunları · tablo yatay kaydırma · form alanı/etiket · filtre · sayfalama · boş durum · footer'a geçiş.

## 5. Kanıt
- Statik sözleşme testi (`wp-content/themes/mavibelge/tests/static/*.test.js`) + gerçek WordPress render (`tools/runtime-test/*`, `mbfx_` klonu) + gerçek headless Chrome (`tools/runtime-test/lib/cdp.js`).
- Genişlikler: 390×844, 1024×768, 1279, 1280, 1440×900 (gerekirse 480/768/820/1920). 1279'da hamburger, 1280'de masaüstü menü.
- Her genişlikte: yatay taşma 0, başlık geometrisi (`lib/header-geometry.js`), kart sütun sayısı, tablo kabı kaydırması, konsol hatası 0, 404 varlık 0.
- Erişilebilirlik: tek H1, atlamasız başlık sırası, form etiket ilişkisi, klavye Tab sırası, görünür odak, dekoratif SVG `aria-hidden`, `prefers-reduced-motion`.
- Ekran görüntüleri commit edilmez; yolları raporda verilir.
- RED önce: yeni sözleşme testi uygulamadan önce kırmızı gösterilir; mutasyonla (davranışı geçici boz) testin yakaladığı kanıtlanır.
