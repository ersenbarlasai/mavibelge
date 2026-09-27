# Claude Skill / Plugin Denetimi — Faz 13 (Referans Sayfa Ailesi Uyumu)

> Tarih: 27 Eylül 2026 · Hazırlayan: ana koordinatör (Opus 5.5) · Kapsam: bu depoda çalışan Claude Code oturumu.
> Kurulum kapsamı: **project** (`.claude/settings.json` → `enabledPlugins`). Kullanıcı/genel kapsam DEĞİŞTİRİLMEDİ.
> `.claude/` dizini `.gitignore` kapsamındadır; `.claude/settings.json` ve `.claude/settings.local.json` commit EDİLMEZ.
> Yalnız iki proje skill dosyası (`.claude/skills/*/SKILL.md`) açık yol ile (`git add -f <yol>`) izlenir.

## 1. Başlangıç envanteri (kurulumdan önce)

| Bileşen | Kapsam | Kaynak | Sürüm / SHA | Not |
|---|---|---|---|---|
| `caveman@caveman` | user | `JuliusBrussee/caveman` | `25d22f864ad68cc447a4cb93aefde918aa4aec9f` | Önceden kurulu; dokunulmadı |
| Pazar `claude-plugins-official` | — | `anthropics/claude-plugins-official` | katalog güncelleme 2026-09-26 | Önceden tanımlı |
| Kullanıcı skills | user | `~/.claude/skills/synced` | — | Dokunulmadı |
| Proje skills / agents / hooks | project | — | — | YOKTU |
| Oturumda mevcut Anthropic araçları | — | Claude Code | — | Agent (alt ajan), Skill, Artifact, Claude-in-Chrome MCP, headless Chrome yok (proje kendi CDP istemcisini kullanır) |

Depoda izlenmeyen `docs/superpowers/{plans,specs}` dizini önceden vardı (kullanıcı/önceki oturum çıktısı); **dokunulmadı, commit edilmedi**.

## 2. Kurulan bileşenler

| Plugin | Yayıncı | Lisans | Sabit sürüm / SHA | İçerik | Karar |
|---|---|---|---|---|---|
| `frontend-design@claude-plugins-official` | Anthropic | Apache-2.0 (plugin `LICENSE`) | `fa59bc9037741ecfa131aa27938272605710d7b2` | Yalnız `skills/frontend-design/SKILL.md` + `plugin.json`; hook / script / `.mcp.json` / paket manifesti YOK | **KURULDU (project)** |
| `superpowers@claude-plugins-official` | Jesse Vincent (`obra/superpowers`), resmî katalogda SHA sabit | MIT | `5bf4e78011075bcfc0dc295f0724994cd123ee71` (v6.4.1, 2026-09-18) | 15 skill, `hooks/session-start` (SessionStart), `package.json` (script YOK), `brainstorming/scripts/*` yerel görsel sunucu (yalnız skill çağrılınca), çoklu platform manifestleri | **KURULDU (project)**; yalnız aşağıdaki 6 iş akışı kullanılır |

Kullanılan Superpowers iş akışları: `brainstorming`, `writing-plans`, `test-driven-development`, `systematic-debugging`,
`subagent-driven-development`, `verification-before-completion`. **Kullanılmayan**: `using-git-worktrees`,
`finishing-a-development-branch` (merge/dal yönetimi — proje Git kurallarıyla çakışır), `writing-skills`, `executing-plans`.

**Hot-load:** Pluginler bu oturumda kuruldu; Claude Code yeni pluginleri çalışan oturuma yüklemez. Bu görevde aynı iş
akışları elle uygulandı (RED→GREEN, sistematik hata ayıklama, bağımsız inceleme, tamamlamadan önce doğrulama).
**Sonraki oturumda etkin olmaları için Claude Code yeniden başlatılmalıdır.**

## 3. Güvenlik denetimi (kurulan)

### 3.1 frontend-design
- Yalnız metin skill'i; komut, hook, MCP, ağ erişimi, dosya sistemi erişimi, otomatik git davranışı yok.
- Prompt içeriği tasarım yönlendirmesi; proje kurallarıyla çelişen talimat yok (statik referans önceliği proje skill'inde bağlayıcı).

### 3.2 superpowers (SHA `5bf4e780`)
- Kaynak depo klonlanıp o SHA'da incelendi (`git checkout 5bf4e78011075bcfc0dc295f0724994cd123ee71`).
- `hooks/hooks.json`: yalnız `SessionStart` (matcher `startup|clear|compact`) → `hooks/session-start` bash betiği.
  Betik yalnız `skills/using-superpowers/SKILL.md` dosyasını okuyup JSON `additionalContext` olarak basar.
  **Ağ erişimi yok, dosya yazma yok, gizli bilgi okuma yok.**
- `package.json`: `scripts`/`postinstall` YOK; bağımlılık yok. İkili (binary) indirme yok.
- `.mcp.json` YOK.
- `skills/brainstorming/scripts/server.cjs` yerel bir görsel yardımcı sunucusudur; yalnız skill açıkça çağrılıp
  "visual companion" seçilirse başlar. Bu görevde KULLANILMADI.
- Metin içinde `git commit` örnekleri (`writing-plans`) ve dal bitirme/merge akışları var: proje skill'i
  `mavibelge-project-guardrails` §7 bunları sınırlar (açık yol ile stage, main'e push yok, force yok, merge yok).
- Otomatik commit/push/deploy davranışı YOK (yalnız metin önerisi).

## 4. Reddedilen / ertelenen adaylar

| Aday | Kaynak | Red gerekçesi |
|---|---|---|
| `playwright` (resmî katalog, `external_plugins/playwright`) | `.mcp.json` → `npx @playwright/mcp@latest` | Sürüm **sabit değil** (`@latest`), her başlatmada npm'den paket + tarayıcı ikilisi indirir (ağ + binary). Projede zaten bağımlılıksız, sürümü depoda sabit CDP istemcisi (`tools/runtime-test/lib/cdp.js`) + yerel Chrome var. Tarayıcı QA onunla yapıldı. **Öneri**: ileride sabit sürüm (`@playwright/mcp@<x.y.z>`) ile project scope değerlendirilebilir. |
| `security-guidance` (Anthropic) | `hooks/*.py` | Her `UserPromptSubmit`/`PostToolUse`/`Stop` olayında Python hook; `ensure_agent_sdk.py` SessionStart'ta pip ile Agent SDK kurar (ağ, paket kurulumu); `review_api.py` diff'i LLM API'sine gönderir. Hook maliyeti ve ağ davranışı nedeniyle kurulmadı; güvenlik incelemesi bağımsız alt ajanla (Opus) yapıldı. |
| `code-review` (Anthropic) | `commands/code-review.md` | `gh pr comment` ile PR'a yorum yazar (dışa dönük eylem). Bağımsız inceleme alt ajanla yapıldı; PR'a otomatik yorum istenmedi. |
| `pr-review-toolkit` (Anthropic) | 6 agent + 1 komut (yalnız metin) | Güvenli (yalnız md) ancak bu görevdeki bağımsız inceleme rolüyle çakışır; token maliyeti için kurulmadı. **Öneri**: sonraki PR incelemelerinde project scope kurulabilir. |
| `commit-commands` (Anthropic) | `commands/commit*.md` | `Bash(git add:*)` geniş izin + `commit-push-pr` tek adımda push/PR — projenin "dosya dosya stage, testsiz push yok" kuralıyla çelişir. |
| `feature-dev` (Anthropic) | agents + command | Mevcut çok ajanlı orkestrasyonla çakışır. |
| Diğer üçüncü taraf paketler | — | Görev kuralı gereği otomatik kurulmadı. |

## 5. Proje skill'leri (yeni)

| Skill | Yol | Tetik | Mekanik doğrulama |
|---|---|---|---|
| `mavibelge-project-guardrails` | `.claude/skills/mavibelge-project-guardrails/SKILL.md` | Depodaki her görev başı + commit/paket/runtime öncesi | `tools/qa/run-all-gates.sh` (PHP 7.4+ sözdizimi taraması, `tanitim-site` git koruması, korunan dosyalar, gizli bilgi taraması, `git diff --check`, paket testi) |
| `mavibelge-reference-page-parity` | `.claude/skills/mavibelge-reference-page-parity/SKILL.md` | Statik referans ↔ WordPress sunum uyumu işleri | `tests/static/page-presentation-contract.test.js`, `tools/runtime-test/page-parity-test.js` (gerçek WP + headless Chrome) |

İki skill çakışmaz: guardrails genel/bağlayıcı, parity yalnız sunum katmanı ve önce guardrails'e yönlendirir.

## 6. Token etkisi

| Bileşen | Oturum başı sabit maliyet | Not |
|---|---|---|
| superpowers SessionStart hook | ≈3,2 KB metin (`using-superpowers/SKILL.md`) her oturum/clear/compact | En büyük sabit maliyet |
| superpowers skill açıklamaları | 15 skill açıklaması listede | Gövdeler yalnız çağrılınca yüklenir (6 kullanılan skill gövdesi toplam ≈85 KB) |
| frontend-design | 1 açıklama satırı | Gövde çağrılınca |
| 2 proje skill'i | 2 açıklama satırı; gövdeler ≈4 KB + ≈3 KB | Çağrılınca |

`claude plugin details <ad>` ile güncel tahmin görülebilir. Superpowers'ın oturum başı enjeksiyonu istenmezse
`claude plugin disable superpowers@claude-plugins-official --scope project` ile kapatılabilir.
