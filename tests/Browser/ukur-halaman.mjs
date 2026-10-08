// Ukur waktu muat halaman (navigasi -> semua panel tergambar) dengan Chromium/Playwright.
// Pemakaian: node tests/Browser/ukur-halaman.mjs <base_url> <username> <password> [ulang=10]
// Target kriteria selesai v1: P95 halaman Ringkasan Eksekutif < 3 detik untuk 380 PSN.
import { chromium } from 'playwright';

const [, , dasar = 'http://127.0.0.1:8000', login, sandi, ulangArg = '10'] = process.argv;
const ulang = Number(ulangArg);
const halaman = [
    ['/dashboard', () => document.querySelectorAll('canvas').length >= 6 && !document.querySelector('[aria-busy="true"]')],
    ['/proyek', () => document.querySelectorAll('tbody tr').length > 0 && !document.querySelector('[aria-busy="true"]')],
    ['/kualitas-data', () => document.querySelectorAll('canvas').length >= 2 && !document.querySelector('[aria-busy="true"]')],
];

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const p = await ctx.newPage();
await p.goto(`${dasar}/login`);
await p.fill('#login', login);
await p.fill('#password', sandi);
await Promise.all([p.waitForNavigation(), p.click('button[type=submit], button:has-text("Masuk")')]);

for (const [url, siap] of halaman) {
    const waktu = [];
    for (let i = 0; i < ulang; i++) {
        const mulai = Date.now();
        await p.goto(`${dasar}${url}`, { waitUntil: 'domcontentloaded' });
        await p.waitForFunction(siap, null, { timeout: 15000 });
        waktu.push(Date.now() - mulai);
    }
    waktu.sort((a, b) => a - b);
    const q = (x) => waktu[Math.min(waktu.length - 1, Math.ceil(x * waktu.length) - 1)];
    console.log(`${url.padEnd(16)} P50 ${String(q(0.5)).padStart(5)} ms   P95 ${String(q(0.95)).padStart(5)} ms   (n=${ulang})`);
}
await browser.close();
