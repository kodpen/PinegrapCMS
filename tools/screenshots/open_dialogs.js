#!/usr/bin/env node
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Opens, from code, every dialog and drawer the screens in screens.json
 * print (Bootstrap 5 .modal and .offcanvas, Bootstrap 6 <dialog>), whether
 * or not a toggle opens it, and the confirmation dialog pgConfirm() builds.
 * Writes results.json (each open box, script errors, elements inside still
 * carrying a Bootstrap 5 class the switched panel does not draw) and a
 * picture of each, so a run against the base commit and one against the
 * switched tree compare by screen and id.
 *
 * Usage:  node tools/screenshots/open_dialogs.js <out dir> [base URL] [screen,screen] [--width=834|390] [--theme=dark]
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
const ARGS = process.argv.slice(2).filter(a => !a.startsWith('--'));
const OPTION = name => { const a = process.argv.find(x => x.startsWith('--' + name + '=')); return a ? a.slice(name.length + 3) : null; };
const OUT = ARGS[0];
const BASE = (ARGS[1] || 'http://127.0.0.1:8000').replace(/\/+$/, '');
const ONLY = ARGS[2] ? ARGS[2].split(',') : null;
const WIDTH = parseInt(OPTION('width') || '1440', 10);
const HEIGHT = { 390: 844, 834: 1112 }[WIDTH] || 900;
const THEME = OPTION('theme') || 'light';
if (!OUT || path.resolve(OUT).startsWith(ROOT + path.sep)) {
    console.error('Usage: node tools/screenshots/open_dialogs.js <out dir outside the repository> [base] [screen,screen] [--width=N] [--theme=dark]');
    process.exit(2);
}
fs.mkdirSync(OUT, { recursive: true });
const screens = JSON.parse(fs.readFileSync(ROOT + '/tools/screenshots/screens.json', 'utf8')).screens
    .filter(s => !s.signed_out && (!ONLY || ONLY.includes(s.name)));
const seedOut = execFileSync('php', [ROOT + '/tools/screenshots/seed.php'], { cwd: ROOT, encoding: 'utf8' }).trim().split('\n');
const ids = JSON.parse(seedOut[seedOut.length - 1]);
const fill = p => p.replace(/\{([a-z_]+)\}/g, (a, k) => encodeURIComponent(String(ids[k])));
const V5_SELECTOR = '.modal-dialog, .modal-content, .dropdown-menu, .offcanvas-body, .form-select, .form-check, .form-check-input, .form-switch, .btn-primary, .btn-secondary, .btn-outline-primary, .btn-outline-secondary, .btn-light, .text-muted';

// Opens the n-th dialog or drawer of the page and reports it; v5 or v6.
const OPEN = n => {
    const boxes = Array.from(document.querySelectorAll('dialog.dialog, dialog.drawer, .modal, .offcanvas'));
    const el = boxes[n];
    if (!el) return null;
    const v6 = el.tagName === 'DIALOG';
    const kind = /drawer|offcanvas/.test(el.className) ? 'drawer' : 'dialog';
    try {
        if (v6) { pgUI[kind].show(el); } else if (window.bootstrap) { (kind === 'drawer' ? bootstrap.Offcanvas : bootstrap.Modal).getOrCreateInstance(el).show(); }
    } catch (e) { return { id: el.id, kind, failed: String(e.message).slice(0, 120) }; }
    return { id: el.id || ('#' + n), kind };
};
const STATE = sel => {
    const d = Array.from(document.querySelectorAll('dialog[open], .modal.show .modal-content, .offcanvas.show')).pop();
    if (!d) return { opened: false };
    const r = d.getBoundingClientRect();
    const left = Array.from(d.querySelectorAll(sel)).filter(e => e.getBoundingClientRect().width > 0).map(e => e.tagName.toLowerCase() + '.' + Array.from(e.classList).slice(0, 3).join('.'));
    return { opened: true, box: [Math.round(r.x), Math.round(r.y), Math.round(r.width), Math.round(r.height)], v5: left.slice(0, 8), v5count: left.length };
};
const CLOSE = () => {
    document.querySelectorAll('dialog[open]').forEach(d => { try { d.close(); } catch (e) {} });
    document.documentElement.classList.remove('dialog-open');
    if (window.bootstrap && bootstrap.Modal) {
        document.querySelectorAll('.modal.show').forEach(m => { try { bootstrap.Modal.getOrCreateInstance(m).hide(); } catch (e) {} });
        document.querySelectorAll('.offcanvas.show').forEach(m => { try { bootstrap.Offcanvas.getOrCreateInstance(m).hide(); } catch (e) {} });
    }
};

(async () => {
    const browser = await chromium.launch();
    const context = await browser.newContext({ viewport: { width: WIDTH, height: HEIGHT } });
    await context.addInitScript(`try{localStorage.setItem('pinegrap backend color scheme', ${JSON.stringify(THEME)});}catch(e){}
        (()=>{let e;Object.defineProperty(window,'PgTour',{configurable:true,get(){return e},set(v){if(v&&typeof v==='object'){v.autoStart=function(){}}e=v}})})();`);
    const login = await context.newPage();
    await login.goto(BASE + '/pinegrap/', { waitUntil: 'domcontentloaded' });
    await login.fill('input[name="email"]', 'admin');
    await login.fill('input[name="password"]', 'sandbox-admin');
    await Promise.all([login.waitForNavigation({ waitUntil: 'domcontentloaded' }), login.click('input[type="submit"]')]);
    await login.close();
    const results = [];
    const seen = new Set();

    for (const screen of screens) {
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', e => errors.push(String(e.message).slice(0, 200)));
        page.on('dialog', d => d.dismiss().catch(() => {}));
        await page.goto(BASE + '/pinegrap/' + fill(screen.path), { waitUntil: 'load' }).catch(e => errors.push('goto ' + e.message));
        await page.waitForTimeout(1200);
        const count = await page.evaluate(() => document.querySelectorAll('dialog.dialog, dialog.drawer, .modal, .offcanvas').length);

        for (let n = 0; n < count; n++) {
            const before = errors.length;
            const info = await page.evaluate(OPEN, n);
            if (!info || seen.has(info.id)) { await page.evaluate(CLOSE); continue; }
            seen.add(info.id);
            await page.waitForTimeout(500);
            const state = await page.evaluate(STATE, V5_SELECTOR);
            const file = (THEME + '-' + WIDTH + '-' + screen.name + '-' + info.id).replace(/[^a-z0-9_-]/gi, '_') + '.png';
            if (state.opened) await page.screenshot({ path: path.join(OUT, file) }).catch(() => {});
            results.push({ screen: screen.name, ...info, ...state, file: state.opened ? file : '', errors: errors.slice(before) });
            await page.evaluate(CLOSE);
            await page.waitForTimeout(300);
        }

        if (!seen.has('pgConfirm') && (await page.evaluate(() => typeof window.pgConfirm === 'function'))) {
            seen.add('pgConfirm');
            const before = errors.length;
            await page.evaluate(() => { window.pgConfirm({ title: 'Delete', message: 'Delete this record?', variant: 'danger' }); });
            await page.waitForTimeout(600);
            const state = await page.evaluate(STATE, V5_SELECTOR);
            const file = THEME + '-' + WIDTH + '-pgConfirm.png';
            if (state.opened) await page.screenshot({ path: path.join(OUT, file) }).catch(() => {});
            results.push({ screen: screen.name, id: 'pgConfirm', kind: 'dialog', ...state, file: state.opened ? file : '', errors: errors.slice(before) });
            await page.keyboard.press('Escape');
        }

        results.push({ screen: screen.name, kind: 'page', boxes: count, errors });
        await page.close();
    }

    await browser.close();
    fs.writeFileSync(path.join(OUT, 'results.json'), JSON.stringify(results, null, 1));
    console.log('done', results.length);
})().catch(e => { console.error(e); process.exit(1); });
