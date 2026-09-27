# 2026 Ücret Tarifesi — MYK Kodu Olmayan / Bağlantısız {{COUNT}} Kayıt

> Makine-okunabilir eşi: `baglantisiz-19-kayit.csv`. Manifest ve kaynak veri **değiştirilmemiştir**; bu belge kurum kararı için hazırlanmıştır.

## Politika

- `qualification_source_key = null`, ilişki `0` kalır. **Hiçbir MYK kodu üretilmedi**, ad/seviye/sektör benzerliğiyle kod **tahmin edilmedi**.
- İki kaynak PDF'de de MYK kodu sütunu yoktur (sayfa görüntülerinden doğrulandı); dolayısıyla kaynakta kod bulunan kayıt yoktur.
- `qualifications.manifest.json` içinde **birebir** (normalize edilmiş ad + seviye) eşleşme aranmıştır. Sonuç tabloda "birebir eşleşme" sütunundadır; bulunsa bile otomatik bağlanmaz, kurum teyidi gerekir.
- "Aynı adlı, farklı seviyeli yeterlilik" sütunu **yalnız bilgidir**, eşleşme değildir; seviye farklı olduğu için bağlanamaz.
- Kurum açıkça onaylamadan bu kayıtlar bir yeterliliğe bağlıymış gibi gösterilmemelidir.

## Tablo

{{TABLE}}

## Bağlantısız ücretlerin Sınav Ücretleri sayfasındaki davranışı (kod okumasıyla; tarayıcıda bu görevde denenmedi)

- Aktif + yayında + dönem eşleşen + geçerli fiyatlı bağlantısız ücret **Sınav Ücretleri** sayfasında **görünür** (`get_all_valid_active_fees()` bağlantıyı şart koşmaz); kartta/tabloda "Yeterlilik Detayı/Detay →" bağlantısı **çıkmaz** (`qualification_permalink` boş).
- Meslekler listesindeki "yalnız güncel fiyatı bulunanlar" filtresini **etkilemez** (`get_active_fee_qualification_ids()` yalnız bağlantılı ücretleri sayar).
- Bu nedenle staging'de bağlantısız kayıt aktifleştirilirse ziyaretçi ücreti görür ama meslek detayına gidemez; **kurum "bağlantısız yayın" kararını yazılı vermeden aktifleştirilmemelidir**.
