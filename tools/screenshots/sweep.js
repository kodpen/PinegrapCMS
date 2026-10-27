#!/usr/bin/env node
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Loads every panel screen (the root files that call pg_page_shell(), with
 * no query string) on a sandbox instance, signed in, and writes
 * results.json: the HTTP status, where the screen ended up, script errors,
 * and the classes on visible elements that Bootstrap 5.3.8 defines but no
 * stylesheet of the page does (a v5 class the switched panel no longer
 * draws). A run against the base commit and one against the switched tree
 * compare by screen. Screens whose name says they act (delete_, duplicate_,
 * remove_, the repair screen) are left out.
 *
 * Usage:  node tools/screenshots/sweep.js <out dir> [base URL] [screen.php,screen.php] [--width=N] [--theme=dark]
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
    console.error('Usage: node tools/screenshots/sweep.js <out dir outside the repository> [base] [screen.php,screen.php] [--width=N] [--theme=dark]');
    process.exit(2);
}
fs.mkdirSync(OUT, { recursive: true });
execFileSync('php', [ROOT + '/tools/screenshots/seed.php'], { cwd: ROOT, encoding: 'utf8' });

const product = ROOT + '/pinegrap';
const screens = fs.readdirSync(product)
    .filter(f => /\.php$/.test(f) && !/^(delete_|duplicate_|remove_)|^software_repair\.php$/.test(f))
    .filter(f => fs.readFileSync(product + '/' + f, 'utf8').includes('pg_page_shell('))
    .filter(f => !ONLY || ONLY.includes(f))
    .sort();

// Every class Bootstrap 5.3.8's stylesheet defines.
const v5css = fs.readFileSync(product + '/assets/lib/bootstrap-5.3.8/css/bootstrap.min.css', 'utf8');
const V5 = Array.from(new Set((v5css.match(/\.-?[_a-zA-Z][_a-zA-Z0-9-]*/g) || []).map(c => c.slice(1))));

// Classes on visible elements that the v5 list holds and no loaded sheet defines.
const UNDRAWN = v5 => {
    const defined = new Set();
    const walk = rules => {
        for (const rule of Array.from(rules || [])) {
            if (rule.selectorText) (rule.selectorText.match(/\.-?[_a-zA-Z][_a-zA-Z0-9-]*/g) || []).forEach(c => defined.add(c.slice(1)));
            if (rule.cssRules) walk(rule.cssRules);
        }
    };
    for (const sheet of Array.from(document.styleSheets)) { try { walk(sheet.cssRules); } catch (e) {} }
    const wanted = new Set(v5.filter(c => !defined.has(c)));
    const found = {};
    document.querySelectorAll('body [class]').forEach(el => {
        if (!el.getClientRects().length) return;
        el.classList.forEach(c => {
            if (!wanted.has(c)) return;
            if (!found[c]) found[c] = { count: 0, example: el.tagName.toLowerCase() + '.' + Array.from(el.classList).slice(0, 4).join('.') };
            found[c].count++;
        });
    });
    return found;
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

    for (const screen of screens) {
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', e => errors.push(String(e.message).slice(0, 200)));
        page.on('dialog', d => d.dismiss().catch(() => {}));
        let status = 0;
        const response = await page.goto(BASE + '/pinegrap/' + screen, { waitUntil: 'load', timeout: 30000 }).catch(e => { errors.push('goto ' + String(e.message).slice(0, 120)); return null; });
        if (response) status = response.status();
        await page.waitForTimeout(400);
        const landed = page.url().replace(BASE + '/pinegrap/', '');
        const undrawn = await page.evaluate(UNDRAWN, V5).catch(() => ({}));
        results.push({ screen, status, landed, errors, undrawn });
        process.stdout.write('.');
        await page.close();
    }

    await browser.close();
    fs.writeFileSync(path.join(OUT, 'results.json'), JSON.stringify(results, null, 1));
    console.log('\ndone', results.length);
})().catch(e => { console.error(e); process.exit(1); });
