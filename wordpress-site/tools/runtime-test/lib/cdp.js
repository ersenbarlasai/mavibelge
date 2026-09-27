'use strict';
/**
 * Bağımlılıksız, en küçük Chrome DevTools Protocol istemcisi (Node >= 22 yerleşik WebSocket). Yalnız YEREL test aracıdır:
 * headless Chrome'u geçici bir profil dizininde başlatır, gerçek tarayıcı düzeni (viewport öykünmesi) altında JS değerlendirir
 * ve ekran görüntüsü alır. Ağ isteği yalnız verilen yerel adreslere gider. Paket içine GİRMEZ (tools/).
 */
const cp = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');
const http = require('http');

const CANDIDATES = [
	process.env.MB_CHROME,
	'C:/Program Files/Google/Chrome/Application/chrome.exe',
	'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
	'/usr/bin/google-chrome',
	'/usr/bin/chromium',
].filter(Boolean);

function findChrome() {
	return CANDIDATES.find((p) => fs.existsSync(p)) || null;
}

function getJson(port, p) {
	return new Promise((resolve, reject) => {
		http.get({ host: '127.0.0.1', port, path: p }, (res) => {
			let d = '';
			res.on('data', (c) => (d += c));
			res.on('end', () => {
				try {
					resolve(JSON.parse(d));
				} catch (e) {
					reject(e);
				}
			});
		}).on('error', reject);
	});
}

async function launch() {
	const bin = findChrome();
	if (!bin) throw new Error('EKSİK BAĞIMLILIK: Chrome/Edge bulunamadı (MB_CHROME ile verin)');
	const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'mb-cdp-'));
	const port = 9300 + Math.floor(Math.random() * 500);
	const proc = cp.spawn(bin, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--hide-scrollbars', '--remote-debugging-port=' + port, '--user-data-dir=' + profile, 'about:blank'], { stdio: 'ignore' });
	let pages = null;
	for (let i = 0; i < 60 && !pages; i++) {
		await new Promise((r) => setTimeout(r, 250));
		try {
			pages = (await getJson(port, '/json/list')).filter((t) => t.type === 'page');
		} catch (e) {
			pages = null;
		}
	}
	if (!pages || !pages.length) {
		proc.kill();
		throw new Error('Chrome DevTools bağlantısı kurulamadı');
	}
	const ws = new WebSocket(pages[0].webSocketDebuggerUrl);
	await new Promise((r, j) => {
		ws.onopen = r;
		ws.onerror = j;
	});
	let id = 0;
	const pending = new Map();
	const listeners = [];
	ws.onmessage = (m) => {
		const msg = JSON.parse(typeof m.data === 'string' ? m.data : m.data.toString());
		if (msg.id && pending.has(msg.id)) {
			const { resolve, reject } = pending.get(msg.id);
			pending.delete(msg.id);
			msg.error ? reject(new Error(msg.error.message)) : resolve(msg.result);
		} else if (msg.method) {
			listeners.forEach((l) => l(msg));
		}
	};
	const send = (method, params) =>
		new Promise((resolve, reject) => {
			const mid = ++id;
			pending.set(mid, { resolve, reject });
			ws.send(JSON.stringify({ id: mid, method, params: params || {} }));
		});
	await send('Page.enable');
	await send('Runtime.enable');
	// Görünür, odaklı sekme gibi davran (gerçek ziyaretçi sekmesi odaklıdır); aksi hâlde headless sayfada focus() yok sayılır.
	await send('Emulation.setFocusEmulationEnabled', { enabled: true });
	const consoleErrors = [];
	listeners.push((m) => {
		if (m.method === 'Runtime.exceptionThrown') consoleErrors.push(m.params.exceptionDetails.text || 'exception');
		if (m.method === 'Runtime.consoleAPICalled' && m.params.type === 'error') consoleErrors.push((m.params.args[0] || {}).value || 'console.error');
		if (m.method === 'Log.entryAdded' && m.params.entry.level === 'error') consoleErrors.push(m.params.entry.text + ' ' + (m.params.entry.url || ''));
	});
	await send('Log.enable');
	const api = {
		send,
		consoleErrors,
		async viewport(width, height) {
			await send('Emulation.setDeviceMetricsOverride', { width, height: height || 900, deviceScaleFactor: 1, mobile: width < 769 });
		},
		async goto(url) {
			const loaded = new Promise((r) => {
				const l = (m) => {
					if (m.method === 'Page.loadEventFired') {
						listeners.splice(listeners.indexOf(l), 1);
						r();
					}
				};
				listeners.push(l);
			});
			await send('Page.navigate', { url });
			await loaded;
			await new Promise((r) => setTimeout(r, 150));
		},
		async eval(expr) {
			const r = await send('Runtime.evaluate', { expression: expr, awaitPromise: true, returnByValue: true });
			if (r.exceptionDetails) throw new Error('eval: ' + (r.exceptionDetails.exception ? r.exceptionDetails.exception.description : r.exceptionDetails.text));
			return r.result.value;
		},
		async key(key, code, keyCode) {
			// Enter/Boşluk: 'text' verilmezse tarayıcı düğmeyi etkinleştirmez (gerçek klavyede olduğu gibi keypress üretilir).
			const text = key === 'Enter' ? '\r' : key === ' ' ? ' ' : undefined;
			await send('Input.dispatchKeyEvent', { type: 'keyDown', key, code: code || key, windowsVirtualKeyCode: keyCode || 0, text, unmodifiedText: text });
			await send('Input.dispatchKeyEvent', { type: 'keyUp', key, code: code || key, windowsVirtualKeyCode: keyCode || 0 });
		},
		async click(x, y) {
			for (const type of ['mousePressed', 'mouseReleased']) await send('Input.dispatchMouseEvent', { type, x, y, button: 'left', clickCount: 1 });
		},
		async tap(x, y) {
			await send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x, y }] });
			await send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
		},
		async screenshot(file, fullPage) {
			const params = { format: 'png' };
			if (fullPage) {
				const m = await send('Page.getLayoutMetrics');
				params.clip = { x: 0, y: 0, width: m.cssContentSize.width, height: Math.min(m.cssContentSize.height, 6000), scale: 1 };
				params.captureBeyondViewport = true;
			}
			const r = await send('Page.captureScreenshot', params);
			fs.writeFileSync(file, Buffer.from(r.data, 'base64'));
			return file;
		},
		async close() {
			try {
				ws.close();
			} catch (e) {}
			proc.kill();
			await new Promise((r) => setTimeout(r, 300));
			try {
				fs.rmSync(profile, { recursive: true, force: true });
			} catch (e) {}
		},
	};
	return api;
}

module.exports = { launch, findChrome };
