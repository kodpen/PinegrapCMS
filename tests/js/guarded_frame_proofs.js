#!/usr/bin/env node
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Browser proofs of the guarded frame (includes/fn/guarded_frame.php), run
 * by hand, not by CI: a unit test cannot reach what a browser enforces.
 * guarded_frame_server.php serves the shell exactly as the module builds
 * it, a stand-in panel around it, and an outside origin; the pages under
 * guarded_frame_pages/ try each way out, and a proof fails when one gets
 * further than its own frame:
 *
 *   attack  (script mode) the panel's cookie and storage, parent and top, a
 *           panel action with the reader's cookie, a script, picture,
 *           font, style sheet or frame from outside, fetch, XHR, beacon,
 *           socket, event source, worker, a form, a window, a download,
 *           moving the top window or the shell, a forged height
 *   leave   (script mode) moving itself to another address
 *   mail    (static mode) no script of the document runs at all
 *   alone   the shell asked for as a page of its own is refused
 *
 * Needs Node, PHP on the PATH (or PG_PHP) and Playwright with its Chromium
 * (require('playwright')).
 *
 * Usage:  node tests/js/guarded_frame_proofs.js [--verbose]
 * Exit:   0 when every proof passed, 1 when one failed, 2 when it could not run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

'use strict';

const { spawn } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

let playwright;

try {
    playwright = require('playwright');
} catch (e) {
    console.error('Playwright is not installed: ' + e.message);
    process.exit(2);
}

const verbose = process.argv.includes('--verbose');
const port = 18000 + Math.floor(Math.random() * 1000);
const panel = 'http://127.0.0.1:' + port;
const log = path.join(os.tmpdir(), 'guarded_frame_' + port + '.log');
let failed = 0;

function check(name, ok, detail) {
    if (!ok) {
        failed++;
    }

    if (!ok || verbose) {
        console.log((ok ? '  ok    ' : '  FAIL  ') + name + (detail !== undefined ? '  (' + detail + ')' : ''));
    }
}

function leaks() {
    if (!fs.existsSync(log)) {
        return [];
    }

    return fs.readFileSync(log, 'utf8').split('\n').filter(Boolean).map((line) => JSON.parse(line));
}

const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

async function open(browser, page, mode) {
    const context = await browser.newContext({ acceptDownloads: true });
    const tab = await context.newPage();
    const seen = { downloads: 0, popups: 0, console: [] };

    tab.on('download', () => seen.downloads++);
    context.on('page', (p) => { if (p !== tab) { seen.popups++; } });
    tab.on('console', (m) => seen.console.push(m.text()));
    await tab.goto(panel + '/panel?page=' + page + '&mode=' + mode);
    await wait(2500);

    return { context, tab, seen };
}

function frames(tab) {
    const shell = tab.frames().find((f) => f.parentFrame() === tab.mainFrame());
    const inner = shell ? shell.childFrames()[0] : null;

    return { shell, inner };
}

async function proveAttack(browser) {
    console.log('attack (script mode)');
    const { context, tab, seen } = await open(browser, 'attack', 'script');
    const { inner } = frames(tab);
    const r = inner ? await inner.evaluate(() => window.__results) : {};

    check('the page ran', r.alive === 'yes', JSON.stringify(r).slice(0, 200));

    for (const name of ['cookie', 'localStorage', 'sessionStorage', 'indexedDB', 'parentDocument', 'topDocument', 'topCookie', 'topLocation', 'topFunction', 'worker']) {
        check('cannot read ' + name, String(r[name]).indexOf('blocked') === 0, r[name]);
    }

    for (const name of ['panelFetch', 'outsideFetch', 'socketOpen', 'script', 'picture', 'panelPicture', 'styleSheet', 'font']) {
        check('cannot load ' + name, r[name] !== 'reached', r[name]);
    }

    check('window.open gives nothing', r.open === 'reached: null', r.open);
    check('no window opened', seen.popups === 0, seen.popups);
    check('no download', seen.downloads === 0, seen.downloads);
    check('top window not moved', tab.url() === panel + '/panel?page=attack&mode=script', tab.url());
    check('the shell not moved', frames(tab).shell && frames(tab).shell.url().indexOf(panel + '/shell?page=attack') === 0);
    check('size in reaches the page', r.size === JSON.stringify({ pg: 'guarded-frame', width: 640, height: 480 }), r.size);

    const heights = await tab.evaluate(() => window.__heights);
    const ignored = await tab.evaluate(() => window.__ignored);

    check('height out reaches the panel', heights.length > 0 && heights.every((h) => typeof h === 'number') && heights.indexOf(7777) < 0, JSON.stringify(heights));
    check('the page\'s own height', heights.some((h) => h >= 321 && h < 800), JSON.stringify(heights));
    check('the page posting to the panel is not the shell', ignored.some((d) => d.indexOf('7777') >= 0), JSON.stringify(ignored));
    check('the panel\'s cookie and storage untouched', (await tab.evaluate(() => localStorage.getItem('pgsecret') + document.cookie)).indexOf('panel-storagepgsecret=panel-cookie') === 0);

    await context.close();
}

async function proveLeave(browser) {
    console.log('leave (script mode)');
    const { context, tab, seen } = await open(browser, 'leave', 'script');

    await wait(1500);
    check('top window not moved', tab.url() === panel + '/panel?page=leave&mode=script', tab.url());
    check('no download', seen.downloads === 0, seen.downloads);
    check('no data: document ran', (await tab.evaluate(() => window.__escaped)) === undefined);

    const { inner } = frames(tab);

    check('the page never reached another address', !inner || inner.url().indexOf('localhost') < 0, inner && inner.url());
    await context.close();
}

async function proveMail(browser) {
    console.log('mail (static mode)');
    const { context, tab, seen } = await open(browser, 'mail', 'static');
    const { inner } = frames(tab);

    const title = await frames(tab).shell.evaluate(() => document.getElementById('pg-frame').contentDocument.title);
    const heights = await tab.evaluate(() => window.__heights);

    check('the mail\'s own script did not run', title === 'Mail', title);
    check('the shell measured the mail', heights.some((h) => h >= 412 && h < 900), JSON.stringify(heights));

    // A click on each link and the form: a javascript: address, a form
    // and a link out (blocked, so the frame shows the browser's error).
    if (inner) {
        await inner.click('#link').catch(() => {});
        await inner.click('#send').catch(() => {});
        await inner.click('#out').catch(() => {});
    }

    await wait(1500);

    const ran = await tab.evaluate(() => window.__ran);

    check('no script ran anywhere', ran === undefined, ran);
    check('the panel not moved', tab.url() === panel + '/panel?page=mail&mode=static', tab.url());
    check('no window opened', seen.popups === 0, seen.popups);
    await context.close();
}

async function proveAlone(browser) {
    console.log('alone');
    const context = await browser.newContext();
    const tab = await context.newPage();
    const answer = await tab.goto(panel + '/shell?page=attack&mode=script');

    check('the shell as a page of its own is refused', answer.status() === 404, answer.status());
    await context.close();
}

(async () => {
    const server = spawn(process.env.PG_PHP || 'php', ['-S', '0.0.0.0:' + port, path.join(__dirname, 'guarded_frame_server.php')], {
        env: Object.assign({}, process.env, { PG_GUARDED_FRAME_LOG: log }),
        stdio: 'ignore',
    });

    let browser;

    try {
        await wait(800);

        // The log must hear a request, or "nothing reached" proves nothing.
        await fetch('http://localhost:' + port + '/probe');
        check('the outside logs what reaches it', leaks().length === 1, JSON.stringify(leaks()));
        fs.rmSync(log, { force: true });

        browser = await playwright.chromium.launch();
        await proveAttack(browser);
        await proveLeave(browser);
        await proveMail(browser);
        await proveAlone(browser);

        const got = leaks();

        check('nothing reached the panel\'s actions or the outside', got.length === 0, JSON.stringify(got));
    } catch (e) {
        console.error(e);
        failed = failed || 1;
        process.exitCode = 2;
    } finally {
        if (browser) {
            await browser.close();
        }

        server.kill();
        fs.rmSync(log, { force: true });
    }

    console.log(failed ? failed + ' proof(s) failed' : 'every proof passed');

    if (!process.exitCode) {
        process.exitCode = failed ? 1 : 0;
    }
})();
