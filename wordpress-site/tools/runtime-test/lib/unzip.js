'use strict';
/**
 * Test yardımcısı: build-packages.js biçimindeki (deflate/store, alt dizinli) bir zip'i hedef dizine açar. Yalnız runtime testleri
 * içindir (konteynerde unzip/ZipArchive yok). Mutlak yol veya ".." içeren giriş REDDEDİLİR.
 *   node lib/unzip.js <zip> <hedef-dizin>
 */
const fs = require('fs');
const path = require('path');
const zlib = require('zlib');

const [zipPath, dest] = process.argv.slice(2);
const buf = fs.readFileSync(zipPath);
const eocd = buf.lastIndexOf(Buffer.from([0x50, 0x4b, 0x05, 0x06]));
const count = buf.readUInt16LE(eocd + 10);
let p = buf.readUInt32LE(eocd + 16);
for (let i = 0; i < count; i++) {
	const method = buf.readUInt16LE(p + 10);
	const crc = buf.readUInt32LE(p + 16);
	const csize = buf.readUInt32LE(p + 20);
	const nlen = buf.readUInt16LE(p + 28);
	const off = buf.readUInt32LE(p + 42);
	const name = buf.slice(p + 46, p + 46 + nlen).toString('utf8');
	if (path.isAbsolute(name) || name.split('/').includes('..')) throw new Error('güvensiz zip girişi: ' + name);
	const start = off + 30 + buf.readUInt16LE(off + 26) + buf.readUInt16LE(off + 28);
	const body = buf.slice(start, start + csize);
	const data = method === 8 ? zlib.inflateRawSync(body) : body;
	if ((zlib.crc32(data) >>> 0) !== crc) throw new Error('CRC uyuşmuyor: ' + name);
	const out = path.join(dest, name);
	fs.mkdirSync(path.dirname(out), { recursive: true });
	fs.writeFileSync(out, data);
	p += 46 + nlen;
}
console.log('açıldı: ' + count + ' dosya');
