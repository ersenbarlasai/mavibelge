# 2026 Ücret Tarifesi — MYK Kodu Olmayan / Bağlantısız {{COUNT}} Kayıt

> Makine-okunabilir eşi: `baglantisiz-19-kayit.csv`. Manifest ve kaynak veri **değiştirilmemiştir**; bu belge kurum kararı için hazırlanmıştır.

## Politika

- `qualification_source_key = null`, ilişki `0` kalır. **Hiçbir MYK kodu üretilmedi**, ad/seviye/sektör benzerliğiyle kod **tahmin edilmedi**.
- İki kaynak PDF'de de MYK kodu sütunu yoktur (sayfa görüntülerinden doğrulandı); dolayısıyla kaynakta kod bulunan kayıt yoktur.
- `qualifications.manifest.json` içinde **birebir** (normalize edilmiş ad + seviye) eşleşme aranmıştır. Sonuç tabloda "birebir eşleşme" sütunundadır; bulunsa bile otomatik bağlanmaz, kurum teyidi gerekir.
- "Aynı adlı, farklı seviyeli yeterlilik" sütunu **yalnız bilgidir**, eşleşme değildir; seviye farklı olduğu için bağlanamaz.
- Kurum açıkça onaylamadan bu kayıtlar bir yeterliliğe bağlıymış gibi gösterilmemelidir.
- **2026-10-19 kararı:** fiyatı PDF ile doğrulanmış bağlantısız ücret, ilişki yok diye listeden kaybolmaz; **bağlantısız** olarak listelenir. Bu, MYK kodu/yeterlilik ilişkisi kararını ÇÖZMEZ: kod yine tahmin edilmez, sahte detay veya mesleğe özel başvuru bağlantısı üretilmez. Ad farkı da taşıyan kayıt (#87) yayın kararında bekletilir.

## Tablo

{{TABLE}}

## Bağlantısız ücretlerin Sınav Ücretleri sayfasındaki davranışı

- Aktif + yayında + dönem eşleşen + geçerli fiyatlı bağlantısız ücret **Sınav Ücretleri** sayfasında **görünür** (`get_all_valid_active_fees()` bağlantıyı şart koşmaz); MYK kodu sütununda "Belirtilmemiştir" yazar, kartta/tabloda "Yeterlilik Detayı/Detay →" bağlantısı **çıkmaz** (`qualification_permalink` boş); mesleğe özel başvuru bağlantısı üretilmez.
- Meslekler listesindeki "yalnız güncel fiyatı bulunanlar" filtresini **etkilemez** (`get_active_fee_qualification_ids()` yalnız bağlantılı ücretleri sayar); hiçbir yeterlilik detayında görünmez.
- Bu davranış Faz 15 yerel WordPress runtime ve gerçek tarayıcı testlerinde doğrulanır (`tools/runtime-test/fee-catalog.sh`).
