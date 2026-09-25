# Faz 6B1 — Dry-Run Çıktı Şeması

> `MaviBelge_Core_Import_Dry_Run_Planner::plan()`'ın döndürdüğü dizinin insan-okunur şeması. Bu şema henüz JSON-Schema dosyası olarak sabitlenmedi (Faz 6A'nın `wordpress-site/data/schema/*.json`'ı gibi) — Faz 6B2'de gerçek WP-CLI/admin ekranı çıktısı netleşince ayrıca resmileştirilebilir.
>
> **Faz 6B1 Düzeltme ve Kabul güncellemesi:** `warnings` artık `invalid` kararında GERÇEK, alan-adı temelli hata listesi taşır (tek sabit cümle değil); `reason` listesine `invalid_target_state` eklendi; `summary`'ye bağlayıcı `operations`/`has_invalid`/`applicable` alanları eklendi (aşağı bkz.).
>
> **Faz 6B1 Son Kapanış Düzeltmesi güncellemesi:** `summary.applicable` fail-closed YENİDEN TANIMLANDI (eski anlamı artık yeni `structurally_valid` alanında) — aşağıdaki Özet bölümü güncel.
>
> **Faz 6B1 Kabul Öncesi Nokta Düzeltmeleri güncellemesi:** `errors[]` artık, `source_key` tekrarının YANINDA, her tür listesinin `source_index`/`source.file`/`source.sha256` batch bütünlüğü ihlalinde de dolar (bkz. `faz6b1-karar-motoru-ve-hash-sozlesmesi.md` §3.7); `null` bir `targetLookups[source_key]` değeri artık asla sessizce "hedef yok" sayılmaz (bkz. aynı belge §3.6).

## Üst seviye

```text
{
  "entries": [ ...plan girdisi, bkz. aşağı... ],
  "summary": { ...bkz. aşağı... },
  "errors": [ "string", ... ]   // yalnız girdi düzeyinde bir sorun varsa (ör. tekrar eden source_key) dolu olur
}
```

`errors` doluysa `entries` boştur — plan ya TAM üretilir ya HİÇ üretilmez (girdi düzeyinde fail-closed).

## Bir plan girdisi (`entries[]`)

| Alan | Tip | Açıklama |
|---|---|---|
| `source_key` | string\|null | Faz 6A manifest source_key (`sector:...`/`qualification:...`/`fee:...`). Şekli geçersiz bir kayıtta null olabilir. |
| `type` | string | `sector` \| `qualification` \| `fee` |
| `decision` | string | `MaviBelge_Core_Import_Decision::ALL_DECISIONS`'tan biri |
| `reason` | string | Makine-okunur neden kodu (ör. `hash_match`, `dependency_unresolved`) |
| `message` | string | Türkçe, insan-okunur açıklama |
| `target_id` | int\|null | Hedef bulunduysa yalnız sayısal ID; bulunmadıysa null |
| `incoming_hash` | string\|null | Bu çalıştırmanın manifest kaydından hesaplanan tam SHA-256 (64 hex) |
| `current_hash` | string\|null | Hedefin ŞU ANKİ yönetilen alanlarının tam SHA-256'sı (hedef yoksa null) |
| `last_applied_hash` | string\|null | Hedefte saklı `_mb_last_applied_hash` (yoksa null) |
| `changed_fields` | string[] | Yalnız ALAN ADLARI (ör. `["name","icon_key"]`) — tam içerik DEĞİL |
| `unresolved_dependencies` | string[] | Ör. `["image_attachment_id"]`, `["sector_term_id"]`, `["qualification_post_id"]` |
| `warnings` | string[] | `invalid` kararında `MaviBelge_Core_Import_Record_Validator`'ın döndürdüğü GERÇEK, alan-adı temelli hata listesi (ör. `["level 1-8 aralığında olmalı.", "sector_slug biçimi geçersiz."]`) — bozuk DEĞER asla basılmaz, yalnız hangi alanın/kuralın ihlal edildiği |

**Kişisel veri / gizli bilgi / dosya sistemi mutlak yolu / tam içerik gövdesi TAŞIMAZ** — yalnız alan adları, SHA-256 özetleri, sayısal ID'ler ve sabit Türkçe mesaj şablonları.

## Özet (`summary`)

```text
{
  "total": int,                          // entries.length ile birebir aynı
  "by_decision": { "create": int, "unchanged": int, "update": int, "conflict": int,
                   "conflict_duplicate_target": int, "conflict_wrong_target_type": int,
                   "blocked_dependency": int, "invalid": int },
  "by_type": { "sector": int, "qualification": int, "fee": int },
  "operations": { "create": int, "update": int, "unchanged": int,   // YENİ — bağlayıcı gruplar
                  "conflict": int,   // generic conflict + duplicate + wrong-type'ın TOPLAMI
                  "blocked": int, "invalid": int },
  "total_matches_input": bool,           // total === (sectors.length + qualifications.length + fees.length)
  "has_invalid": bool,                   // en az bir kayıt invalid mi
  "structurally_valid": bool,            // Son Kapanış Düzeltmesi'nde EKLENDİ — total_matches_input VE bilinmeyen-decision-yok VE !has_invalid (ESKİ "applicable" tanımı)
  "applicable": bool                     // Son Kapanış Düzeltmesi'nde YENİDEN TANIMLANDI — structurally_valid VE operations.conflict===0 VE operations.blocked===0 VE operations.invalid===0
}
```

Invariant'lar: `sum(operations) === total`, `sum(by_type) === total`. `applicable=false` iken plan UYGULANABİLİR ADAY sayılmaz (Faz 6B2/6B3'ün apply katmanı bunu asla çalıştırmamalı — bu görev apply katmanını YAZMADI, yalnız invariant'ı üretti). Not: yalnız conflict/blocked_dependency taşıyan (hiç invalid OLMAYAN) bir plan `structurally_valid=true` AMA `applicable=false` olabilir — bu, "hesabı doğru" ile "uygulanması güvenli" ayrımını gösterir (bkz. `faz6b1-karar-motoru-ve-hash-sozlesmesi.md` §5, "neden değişti").

## Örnek (gerçek Faz 6A fixture'ından, hedef HİÇ BULUNAMADIĞI varsayımıyla)

```json
{
  "source_key": "sector:is-makineleri",
  "type": "sector",
  "decision": "create",
  "reason": "no_target",
  "message": "Hedefte bu source_key ile eşleşen kayıt yok; yeni kayıt olarak planlanabilir.",
  "target_id": null,
  "incoming_hash": "<64 hex>",
  "current_hash": null,
  "last_applied_hash": null,
  "changed_fields": [],
  "unresolved_dependencies": [],
  "warnings": []
}
```

## Bu görevde ÜRETİLMEYEN şeyler

- Gerçek WordPress verisine karşı çalıştırılmış bir plan (Faz 6B2 gerektirir — `MaviBelge_Core_Import_Target_Repository`'nin gerçek bir uygulaması yok).
- WP-CLI/admin ekranı çıktı biçimlendirmesi (yalnız PHP dizisi/JSON — insan-okunur tablo biçimi Faz 6B2'nin işi).
- Diskte kalıcı bir dry-run raporu dosyası (bu görev hiçbir dosya yazmadı; `plan()` yalnız bir PHP dizisi döndürür).

## Güncelleme — Faz 6B3 Önkoşul ve Yazma Güvenliği Kapanışı (23 Eylül 2026)

**Yeni girdi alanı `natural_key_check`** (`string|null`). Marker ile hedef bulunamadığında gerçek adapter'ın salt okunur doğal anahtar preflight sonucunu taşır. Değerler: `none`, `unmanaged`, `corrupt_marker`, `wrong_marker_prefix`, `foreign_marker`, `undiscovered_marker`, `duplicate`, `query_error`. `null` "kontrol edilmedi" demektir (hedef marker ile bulundu, ya da adapter bilgi vermedi). Yalnız durum kodudur, içerik değil. WP-CLI JSON çıktısında da yer alır.

**Yeni neden kodları** (hepsi `create`'i engeller):

| Neden | Karar |
|---|---|
| `unmanaged_natural_key` | `conflict` |
| `corrupt_marker` | `conflict` |
| `wrong_marker_prefix` | `conflict` |
| `foreign_marker` | `conflict` |
| `undiscovered_marker` | `conflict` |
| `natural_key_query_error` | `conflict` |
| `duplicate_natural_key` | `conflict_duplicate_target` |

Anlamları ve karar sırası için bkz. [`faz6b3-yazma-guvenligi-sozlesmesi.md`](./faz6b3-yazma-guvenligi-sozlesmesi.md) §6. `summary` şekli değişmedi.
