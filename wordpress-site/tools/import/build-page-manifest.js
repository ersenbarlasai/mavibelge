'use strict';
/**
 * Faz 12 — `page` manifest üretimi: tanitim-site/*.html (dondurulmuş, YALNIZ OKUNUR) -> wordpress-site/data/content/pages.manifest.json.
 * Katalog ve haber/referans manifestleri DEĞİŞMEZ. WordPress'e dokunmaz, ağ isteği yapmaz.
 *
 * Run: node wordpress-site/tools/import/build-page-manifest.js [--write]
 *   (--write yoksa yalnız doğrular ve özeti yazdırır.)
 *
 * Güvenceler: (1) ön-doğrulama (envanter çapraz denetimi + manifest doğrulayıcı) başarısızsa çıkış 1 ve HİÇBİR şey yazılmaz;
 * (2) deterministik: iki koşu byte-eşit; (3) yazma geçici dosya + yeniden adlandırma ile dosya-başına atomiktir.
 */
const fs = require('fs');
const path = require('path');
const { sha256Hex, toDeterministicJson } = require('./lib/hash');
const { PAGE_INVENTORY, PENDING_CODES } = require('./lib/page-inventory');
const { convertPage, convertApprovedFragment, sanitizeCheck } = require('./lib/page-content');
const { loadApprovedSources, validatePageManifest, crossCheckInventory, inventoryDigest, SCHEMA_VERSION, REPO_ROOT } = require('./lib/validate-page-set');

const OUT_PATH = path.join(REPO_ROOT, 'wordpress-site', 'data', 'content', 'pages.manifest.json');

const NOTES = [
	'Kaynak: tanitim-site/*.html (dondurulmuş, yalnız okunur). Her sayfanın ana içeriği (hero sonrası) kapalı izin listeli, kanonik semantik HTML olarak çıkarılır; header/footer/gezinme/CTA düğmesi/script/form/görsel içeriğe kopyalanmaz.',
	'mode=empty sayfalarda içerik BOŞTUR: tema dinamik olarak çizer (hub kartları, form altyapısı, CPT listeleri) veya statik metin kurumca onaylanmamıştır (KVKK/gizlilik özeti); hiçbir metin uydurulmaz. Nedenleri page-inventory.js içindedir.',
	'Yer-tutucu/demo cümleleri ("tanıtım sürümü", "bilgi güncellenecektir", "yazılım aşamasında" ...) ve href="#" bağlantıları içeriğe alınmaz; başlıklardan "(Demo)" ekleri çıkarılmıştır.',
	'Tüm sayfalar hedef post_status=draft olarak planlanır; yayınlama ayrı, açık onaylı işlemdir ve publish_hold=true (bekleyen bloklayıcı kurum kararı) sayfalarını atlar.',
	'Hero özeti (varsa) excerpt olarak taşınır; tema başlık altında gösterir.',
];

function buildPageManifest() {
	const errors = crossCheckInventory();
	const approved = loadApprovedSources(errors);
	const records = [];
	const report = [];
	if (errors.length === 0) {
		PAGE_INVENTORY.forEach(function (inv, i) {
			let rel = 'tanitim-site/' + inv.file;
			let buf = fs.readFileSync(path.join(REPO_ROOT, rel));
			let conv;
			try {
				if ('approved' === inv.mode) {
					const entry = approved.bySlug[inv.slug];
					if (!entry) {
						errors.push(inv.slug + ': onaylı kaynak künyesi (approved-sources.manifest.json) yok');
						return;
					}
					rel = entry.fragment_file;
					buf = fs.readFileSync(path.join(REPO_ROOT, rel));
					conv = Object.assign({ title: inv.title, excerpt: inv.excerpt || '' }, convertApprovedFragment(buf.toString('utf8')));
				} else {
					conv = convertPage(buf.toString('utf8'), inv);
				}
			} catch (e) {
				errors.push(inv.slug + ': dönüştürme hatası: ' + e.message);
				return;
			}
			if (conv.title !== inv.title) {
				errors.push(inv.slug + ': statik <h1> ("' + conv.title + '") envanter başlığıyla ("' + inv.title + '") eşleşmiyor');
			}
			const contentErrors = sanitizeCheck(conv.content);
			contentErrors.forEach((e) => errors.push(inv.slug + ': ' + e));
			report.push({ slug: inv.slug, dropped: conv.dropped });
			records.push({
				schema_version: SCHEMA_VERSION,
				source_key: 'page:' + inv.slug,
				source_index: i,
				slug: inv.slug,
				title: inv.title,
				content: conv.content,
				excerpt: conv.excerpt,
				parent_source_key: null,
				menu_order: i + 1,
				page_template: '',
				post_status: 'draft',
				layout: inv.layout,
				content_sha256: sha256Hex(Buffer.from(conv.content, 'utf8')),
				pending_decisions: inv.pending.slice(),
				publish_hold: inv.pending.some((c) => PENDING_CODES[c].blocking),
				publish_requires: (inv.publishRequires || []).slice(),
				source: { file: rel, sha256: sha256Hex(buf) },
			});
		});
	}
	const manifest = {
		schema_version: SCHEMA_VERSION,
		record_type: 'page',
		count: records.length,
		source: { file: 'tanitim-site/*.html', sha256: records.length ? inventoryDigest(records) : '' },
		notes: NOTES,
		records,
	};
	if (errors.length === 0) {
		validatePageManifest(manifest).forEach((e) => errors.push(e));
	}
	return { manifest, errors, report };
}

function writeAtomic(file, text) {
	const tmp = file + '.tmp-' + process.pid;
	fs.writeFileSync(tmp, text);
	fs.renameSync(tmp, file);
}

if (require.main === module) {
	const built = buildPageManifest();
	if (built.errors.length) {
		process.stderr.write('Sayfa manifest üretimi BAŞARISIZ (' + built.errors.length + ' hata):\n' + built.errors.map((e) => '  - ' + e).join('\n') + '\nHiçbir dosya yazılmadı.\n');
		process.exit(1);
	}
	const text = toDeterministicJson(built.manifest);
	if (process.argv.includes('--write')) {
		writeAtomic(OUT_PATH, text);
		process.stdout.write('yazıldı wordpress-site/data/content/pages.manifest.json (' + text.length + ' bayt, 32 kayıt)\n');
	} else {
		process.stdout.write('doğrulandı (yazılmadı): 32 kayıt, ' + text.length + ' bayt\n');
	}
	if (process.argv.includes('--report')) {
		built.report.forEach((r) => process.stdout.write(r.slug + ' -> düşürülen ' + r.dropped.length + '\n' + r.dropped.map((d) => '    · ' + d).join('\n') + '\n'));
	}
}

module.exports = { buildPageManifest, NOTES };
