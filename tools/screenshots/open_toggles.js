#!/usr/bin/env node
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Opens every dialog, drawer and menu toggle of the screens in screens.json
 * (signed in, light and dark, 1440 px) on a sandbox instance and writes
 * results.json (what opened, its box and background, script errors) and a
 * picture of each open state. Reads Bootstrap 5 (modal, offcanvas,
 * dropdown) and Bootstrap 6 (dialog, drawer, menu) toggles alike, so a run
 * against the base commit and one against the switched tree compare by
 * screen, kind, target and label.
 *
 * Usage:  node tools/screenshots/open_toggles.js <out dir> [base URL] [screen,screen]
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { chromium } = require('playwright');
const ROOT = path.resolve(__dirname, '..', '..');
const OUT = process.argv[2];
const BASE = (process.argv[3] || 'http://127.0.0.1:8000').replace(/\/+$/, '');
const ONLY = process.argv[4] ? process.argv[4].split(',') : null;
if (!OUT || path.resolve(OUT).startsWith(ROOT + path.sep)) {
    console.error('Usage: node tools/screenshots/open_toggles.js <out dir outside the repository> [base] [screen,screen]');
    process.exit(2);
}
fs.mkdirSync(OUT, { recursive: true });
const screens = JSON.parse(fs.readFileSync(ROOT + '/tools/screenshots/screens.json', 'utf8')).screens
    .filter(s => !s.signed_out && (!ONLY || ONLY.includes(s.name)));
const seedOut = execFileSync('php', [ROOT + '/tools/screenshots/seed.php'], { cwd: ROOT, encoding: 'utf8' }).trim().split('\n');
const ids = JSON.parse(seedOut[seedOut.length - 1]);
const fill = p => p.replace(/\{([a-z_]+)\}/g, (a, k) => encodeURIComponent(String(ids[k])));

(async () => {
    const browser = await chromium.launch();
    const results = [];
    for (const theme of ['light', 'dark']) {
        const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
        await context.addInitScript(`try{localStorage.setItem('pinegrap backend color scheme', ${JSON.stringify(theme)});}catch(e){}
            (()=>{let e;Object.defineProperty(window,'PgTour',{configurable:true,get(){return e},set(v){if(v&&typeof v==='object'){v.autoStart=function(){}}e=v}})})();`);
        const login = await context.newPage();
        await login.goto(BASE + '/pinegrap/', { waitUntil: 'domcontentloaded' });
        await login.fill('input[name="email"]', 'admin');
        await login.fill('input[name="password"]', 'sandbox-admin');
        await Promise.all([login.waitForNavigation({ waitUntil: 'domcontentloaded' }), login.click('input[type="submit"]')]);
        await login.close();
        const seen = new Set();
        for (const screen of screens) {
            const url = BASE + '/pinegrap/' + fill(screen.path);
            const page = await context.newPage();
            const errors = [];
            page.on('pageerror', e => errors.push(String(e.message).slice(0, 200)));
            page.on('dialog', d => d.dismiss().catch(() => {}));
            await page.goto(url, { waitUntil: 'load' }).catch(e => errors.push('goto ' + e.message));
            await page.waitForTimeout(1200);
            const count = await page.evaluate(() => {
                const list = Array.from(document.querySelectorAll('[data-bs-toggle="dialog"],[data-bs-toggle="drawer"],[data-bs-toggle="menu"],[data-bs-toggle="modal"],[data-bs-toggle="offcanvas"],[data-bs-toggle="dropdown"]'));
                list.forEach((el, i) => el.setAttribute('data-probe', i));
                return list.length;
            });
            for (let i = 0; i < count; i++) {
                const info = await page.evaluate(i => {
                    const el = document.querySelector('[data-probe="' + i + '"]');
                    if (!el) return null;
                    const r = el.getBoundingClientRect();
                    const style = getComputedStyle(el);
                    const map = { modal: 'dialog', offcanvas: 'drawer', dropdown: 'menu' }; const k = el.getAttribute('data-bs-toggle');
                    return { kind: map[k] || k, target: el.getAttribute('data-bs-target') || el.getAttribute('href') || '',
                        label: (el.getAttribute('aria-label') || el.getAttribute('title') || el.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 40),
                        id: el.id, visible: r.width > 0 && r.height > 0 && style.visibility !== 'hidden' && r.bottom > 0 };
                }, i);
                if (!info || !info.visible) continue;
                const key = theme + '|' + screen.name.replace(/-new$/, '') + '|' + info.kind + '|' + info.target + '|' + info.label;
                if (seen.has(key)) continue;
                seen.add(key);
                const before = errors.length;
                                try {
                    await page.locator('[data-probe="' + i + '"]').scrollIntoViewIfNeeded({ timeout: 2000 });
                    await page.locator('[data-probe="' + i + '"]').click({ timeout: 3000 });
                } catch (e) {
                    results.push({ theme, screen: screen.name, ...info, opened: false, note: 'click failed: ' + e.message.split('\n')[0].slice(0, 120) });
                    continue;
                }
                await page.waitForTimeout(500);
                const state = await page.evaluate(info => {
                    const out = {};
                    if (info.kind === 'menu') {
                        const m = document.querySelector('.menu.show, .dropdown-menu.show, .menu:popover-open');
                        const any = Array.from(document.querySelectorAll('.menu')).find(x => { const r = x.getBoundingClientRect(); return r.width > 0 && r.height > 0 && getComputedStyle(x).visibility !== 'hidden' && getComputedStyle(x).display !== 'none'; });
                        const el = m || any;
                        out.opened = !!el;
                        if (el) { const r = el.getBoundingClientRect(); out.box = [Math.round(r.x), Math.round(r.y), Math.round(r.width), Math.round(r.height)]; out.bg = getComputedStyle(el).backgroundColor; out.inView = r.right <= innerWidth + 1 && r.bottom <= innerHeight + 1 && r.x >= -1; }
                    } else {
                        const d = Array.from(document.querySelectorAll('dialog[open], .modal.show .modal-content, .offcanvas.show')).pop();
                        out.opened = !!d;
                        if (d) { const r = d.getBoundingClientRect(); out.box = [Math.round(r.x), Math.round(r.y), Math.round(r.width), Math.round(r.height)]; out.bg = getComputedStyle(d).backgroundColor; out.modal = d.matches('dialog') ? d.matches(':modal') : true; out.id = d.id; out.inView = r.right <= innerWidth + 1 && r.x >= -1; }
                    }
                    return out;
                }, info);
                const file = (theme + '-' + screen.name + '-' + info.kind + '-' + (info.target || info.id || info.label).slice(0, 30)).replace(/[^a-z0-9_-]/gi, '_') + '.png';
                if (state.opened) await page.screenshot({ path: path.join(OUT, file) }).catch(() => {});
                results.push({ theme, screen: screen.name, ...info, ...state, file: state.opened ? file : '', errors: errors.slice(before) });
                await page.keyboard.press('Escape');
                await page.waitForTimeout(300);
                await page.evaluate(() => { document.querySelectorAll('dialog[open]').forEach(d => { try { d.close(); } catch (e) {} }); document.documentElement.classList.remove('dialog-open');
                    if (window.bootstrap && bootstrap.Modal) { document.querySelectorAll('.modal.show').forEach(m => { try { bootstrap.Modal.getOrCreateInstance(m).hide(); } catch (e) {} }); document.querySelectorAll('.offcanvas.show').forEach(m => { try { bootstrap.Offcanvas.getOrCreateInstance(m).hide(); } catch (e) {} }); } });
                await page.mouse.click(5, 895).catch(() => {});
                await page.waitForTimeout(200);
            }
            results.push({ theme, screen: screen.name, kind: 'page', toggles: count, errors });
            await page.close();
        }
        await context.close();
    }
    await browser.close();
    fs.writeFileSync(path.join(OUT, 'results.json'), JSON.stringify(results, null, 1));
    console.log('done', results.length);
})().catch(e => { console.error(e); process.exit(1); });
