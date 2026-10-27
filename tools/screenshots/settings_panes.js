#!/usr/bin/env node
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Opens the settings dialog on a sandbox instance and loads each of its
 * categories in turn (the panes settings_pane.php answers with), then the
 * dialogs, drawers and menus each pane opens. Writes results.json (the
 * pane's dialog box, script errors, and every element still carrying a
 * Bootstrap 5 component class the panel no longer styles after the switch)
 * and a picture of each pane, so a run against the base commit and one
 * against the switched tree compare pane by pane.
 *
 * Usage:  node tools/screenshots/settings_panes.js <out dir> [base URL] [--width=834|390] [--theme=dark]
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

'use strict';
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');
const ROOT = path.resolve(__dirname, '..', '..');
const ARGS = process.argv.slice(2).filter(a => !a.startsWith('--'));
const OPTION = name => { const a = process.argv.find(x => x.startsWith('--' + name + '=')); return a ? a.slice(name.length + 3) : null; };
const OUT = ARGS[0];
const BASE = (ARGS[1] || 'http://127.0.0.1:8000').replace(/\/+$/, '');
const WIDTH = parseInt(OPTION('width') || '1440', 10);
const HEIGHT = { 390: 844, 834: 1112 }[WIDTH] || 900;
const THEME = OPTION('theme') || 'light';
if (!OUT || path.resolve(OUT).startsWith(ROOT + path.sep)) {
    console.error('Usage: node tools/screenshots/settings_panes.js <out dir outside the repository> [base] [--width=N] [--theme=dark]');
    process.exit(2);
}
fs.mkdirSync(OUT, { recursive: true });

// Classes of Bootstrap 5 components and form controls that the switched
// panel's stylesheet does not draw; on the base commit they are expected.
const V5_SELECTOR = '.modal-dialog, .modal-content, .dropdown-menu, .offcanvas, .form-select, .form-check, .form-check-input, .form-switch, .btn-primary, .btn-secondary, .btn-outline-primary, .btn-outline-secondary, .btn-light, .text-muted, .badge.bg-light, .input-group-text.form-select';

(async () => {
    const browser = await chromium.launch();
    const context = await browser.newContext({ viewport: { width: WIDTH, height: HEIGHT } });
    await context.addInitScript(`try{localStorage.setItem('pinegrap backend color scheme', ${JSON.stringify(THEME)});}catch(e){}
        (()=>{let e;Object.defineProperty(window,'PgTour',{configurable:true,get(){return e},set(v){if(v&&typeof v==='object'){v.autoStart=function(){}}e=v}})})();`);
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', e => errors.push(String(e.message).slice(0, 200)));
    page.on('dialog', d => d.dismiss().catch(() => {}));
    await page.goto(BASE + '/pinegrap/', { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="email"]', 'admin');
    await page.fill('input[name="password"]', 'sandbox-admin');
    await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.click('input[type="submit"]')]);
    await page.goto(BASE + '/pinegrap/welcome.php', { waitUntil: 'load' });
    await page.waitForTimeout(800);
    await page.evaluate(() => document.getElementById('pg_settings_open').click());
    await page.waitForTimeout(1500);
    const panes = await page.evaluate(() => Array.from(document.querySelectorAll('.pg-sm-link[data-pane]')).map(b => b.getAttribute('data-pane')));
    const results = [];

    for (const pane of panes) {
        const before = errors.length;
        await page.evaluate(p => {
            const nav = document.getElementById('pg_settings_modal_menu');
            const link = document.querySelector('.pg-sm-link[data-pane="' + p + '"]');
            if (link && link.getBoundingClientRect().width === 0 && nav) nav.click();
            if (link) link.click();
        }, pane);
        await page.waitForTimeout(2000);
        const state = await page.evaluate(sel => {
            const d = document.getElementById('pg_settings_modal');
            const body = document.getElementById('pg_settings_modal_form');
            const r = d.getBoundingClientRect();
            const left = Array.from(body.querySelectorAll(sel)).filter(e => e.getBoundingClientRect().width > 0)
                .map(e => e.tagName.toLowerCase() + '.' + Array.from(e.classList).slice(0, 4).join('.'));
            return { box: [Math.round(r.x), Math.round(r.y), Math.round(r.width), Math.round(r.height)], body: Math.round(body.scrollHeight),
                fields: body.querySelectorAll('input, select, textarea').length, v5: left.slice(0, 12), v5count: left.length,
                toggles: body.querySelectorAll('[data-bs-toggle]').length };
        }, V5_SELECTOR);
        const file = THEME + '-' + WIDTH + '-settings-' + pane.replace(/[^a-z0-9_-]/gi, '_') + '.png';
        await page.screenshot({ path: path.join(OUT, file) }).catch(() => {});

        // The pane's own dialogs, drawers and menus, each opened and closed.
        const opened = [];
        for (let i = 0; i < state.toggles; i++) {
            const info = await page.evaluate(i => {
                const el = document.querySelectorAll('#pg_settings_modal_form [data-bs-toggle]')[i];
                if (!el) return null;
                const r = el.getBoundingClientRect();
                return { kind: el.getAttribute('data-bs-toggle'), label: (el.getAttribute('aria-label') || el.getAttribute('title') || el.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 40), visible: r.width > 0 && r.height > 0 };
            }, i);
            if (!info || !info.visible || !/^(dialog|drawer|menu|modal|offcanvas|dropdown)$/.test(info.kind)) continue;
            const e0 = errors.length;
            await page.evaluate(i => document.querySelectorAll('#pg_settings_modal_form [data-bs-toggle]')[i].click(), i).catch(() => {});
            await page.waitForTimeout(500);
            const shown = await page.evaluate(() => {
                const el = document.querySelector('.menu.show, .dropdown-menu.show') || Array.from(document.querySelectorAll('dialog[open], .modal.show .modal-content, .offcanvas.show')).filter(d => d.id !== 'pg_settings_modal').pop();
                if (!el) return null;
                const r = el.getBoundingClientRect();
                return [Math.round(r.x), Math.round(r.y), Math.round(r.width), Math.round(r.height)];
            });
            opened.push({ ...info, box: shown, errors: errors.slice(e0) });
            await page.keyboard.press('Escape');
            await page.waitForTimeout(300);
            if (!(await page.evaluate(() => { const d = document.getElementById('pg_settings_modal'); return d.open || d.classList.contains('show'); }))) {
                await page.evaluate(() => document.getElementById('pg_settings_open').click());
                await page.waitForTimeout(1200);
                await page.evaluate(p => { const l = document.querySelector('.pg-sm-link[data-pane="' + p + '"]'); if (l) l.click(); }, pane);
                await page.waitForTimeout(1500);
            }
        }

        results.push({ pane, ...state, opened, file, errors: errors.slice(before) });
    }

    await browser.close();
    fs.writeFileSync(path.join(OUT, 'results.json'), JSON.stringify(results, null, 1));
    console.log('done', results.length);
})().catch(e => { console.error(e); process.exit(1); });
