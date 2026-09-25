#!/usr/bin/env node
/**
 * Tema dist paketlerini src dosyalarından DETERMİNİSTİK üretir (Node yalnız yerelde; sunucuda gerekmez).
 *   node tools/build/build-theme-assets.js --write   dist/style.css ve dist/main.js yazılır
 *   node tools/build/build-theme-assets.js --check   dist, src'den üretilenle BYTE-EŞİT mi (aksi hâlde çıkış 1)
 * Sıra ve ayraç, Faz 3/4'te elle üretilen paketle birebir aynıdır (banner + dosyalar, aralarında tek "\n").
 */
'use strict';
const fs = require('fs');
const path = require('path');

const THEME = path.join(__dirname, '..', '..', 'wp-content', 'themes', 'mavibelge', 'assets');
const CSS_ORDER = ['tokens', 'base', 'layout', 'components', 'pages', 'header', 'footer', 'responsive'];
const JS_ORDER = ['navigation', 'components', 'main'];

function build(kind) {
	const dir = kind === 'css' ? 'css' : 'js';
	const order = kind === 'css' ? CSS_ORDER : JS_ORDER;
	const banner = fs.readFileSync(path.join(__dirname, kind === 'css' ? 'style.banner.txt' : 'main.banner.txt'), 'utf8');
	const parts = order.map((n) => fs.readFileSync(path.join(THEME, 'src', dir, n + '.' + kind), 'utf8'));
	return banner + parts.join('\n');
}

const targets = { 'dist/style.css': build('css'), 'dist/main.js': build('js') };
const mode = process.argv[2];
let bad = 0;
for (const [rel, content] of Object.entries(targets)) {
	const file = path.join(THEME, rel);
	if (mode === '--write') {
		fs.writeFileSync(file, content);
		console.log('yazıldı  ' + rel + ' (' + Buffer.byteLength(content) + ' bayt)');
	} else if (mode === '--check') {
		const same = fs.existsSync(file) && fs.readFileSync(file, 'utf8') === content;
		console.log((same ? 'EŞİT   ' : 'FARKLI ') + rel);
		if (!same) bad++;
	} else {
		console.error('Kullanım: --write | --check');
		process.exit(2);
	}
}
process.exit(bad ? 1 : 0);
