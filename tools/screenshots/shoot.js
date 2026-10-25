#!/usr/bin/env node
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Photographs panel screens on a sandbox instance (tools/setup_sandbox.sh):
 * every screen of screens.json in the light and the dark colour scheme, on a
 * wide and a phone viewport, after seed.php has filled in sample records.
 * Writes PNGs and an index.html contact sheet to the output folder, which
 * must lie outside the repository: the images are never committed.
 *
 * Usage:  node tools/screenshots/shoot.js [options]
 *   --out=DIR        output folder (default: $TMPDIR/pinegrap-screenshots)
 *   --base=URL       instance (default: http://127.0.0.1:8000)
 *   --user=NAME      panel user (default: admin, as setup_sandbox.sh makes it)
 *   --password=PASS  (default: sandbox-admin)
 *   --only=a,b       only these screen names
 *   --theme=light|dark       one colour scheme only
 *   --viewport=wide|phone    one viewport only
 *   --label=TEXT     caption on the contact sheet (a branch or commit)
 *   --no-seed        skip seed.php (an instance that is not the sandbox)
 *
 * Needs Playwright with Chromium (require('playwright')).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

'use strict';

const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync } = require('child_process');
const { chromium } = require('playwright');

const ROOT = path.resolve(__dirname, '..', '..');

const VIEWPORTS = {
    wide: { width: 1440, height: 900, isMobile: false, deviceScaleFactor: 1 },
    phone: { width: 390, height: 844, isMobile: true, hasTouch: true, deviceScaleFactor: 2 }
};
const THEMES = ['light', 'dark'];

function parseArgs(argv) {
    const options = {
        out: path.join(os.tmpdir(), 'pinegrap-screenshots'),
        base: 'http://127.0.0.1:8000',
        user: 'admin',
        password: 'sandbox-admin',
        only: null,
        theme: null,
        viewport: null,
        label: '',
        seed: true
    };
    for (const arg of argv) {
        const match = /^--([a-z-]+)(?:=(.*))?$/.exec(arg);
        if (!match) {
            throw new Error('Unknown argument: ' + arg);
        }
        const [, key, value] = match;
        if (key === 'no-seed') {
            options.seed = false;
        } else if (key === 'only') {
            options.only = value.split(',').map(s => s.trim()).filter(Boolean);
        } else if (key in options) {
            options[key] = value === undefined ? '' : value;
        } else {
            throw new Error('Unknown option: --' + key);
        }
    }
    if (options.theme && !THEMES.includes(options.theme)) {
        throw new Error('--theme is light or dark');
    }
    if (options.viewport && !(options.viewport in VIEWPORTS)) {
        throw new Error('--viewport is wide or phone');
    }
    options.out = path.resolve(options.out);
    if ((options.out + path.sep).startsWith(ROOT + path.sep)) {
        throw new Error('--out must lie outside the repository: screenshots are never committed.');
    }
    options.base = options.base.replace(/\/+$/, '');
    return options;
}

// {name} placeholders in a screen path come from the ids seed.php printed.
function fillPath(screenPath, ids) {
    return screenPath.replace(/\{([a-z_]+)\}/g, (all, key) => {
        if (ids[key] === undefined || ids[key] === null || ids[key] === '') {
            throw new Error('No sample record for ' + all + ' (seed.php printed none).');
        }
        return encodeURIComponent(String(ids[key]));
    });
}

// A screen's click is one selector for every viewport or a map per viewport.
function clickFor(screen, viewportName) {
    if (!screen.click) {
        return null;
    }
    if (typeof screen.click === 'string') {
        return screen.click;
    }
    return screen.click[viewportName] || null;
}

// An off-canvas element (the phone menu drawer while shut) is "visible" to
// Playwright but lies outside the viewport; its picture would be empty.
async function inViewport(page, element) {
    const box = await element.boundingBox();
    const size = page.viewportSize();
    return Boolean(box) && box.width > 0 && box.height > 0
        && box.x + box.width > 0 && box.x < size.width;
}

function loadScreens(only) {
    const list = JSON.parse(fs.readFileSync(path.join(__dirname, 'screens.json'), 'utf8')).screens;
    if (!only) {
        return list;
    }
    const unknown = only.filter(name => !list.some(screen => screen.name === name));
    if (unknown.length) {
        throw new Error('Unknown screen(s): ' + unknown.join(', '));
    }
    return list.filter(screen => only.includes(screen.name));
}

function runSeed() {
    const output = execFileSync('php', [path.join(__dirname, 'seed.php')], { cwd: ROOT, encoding: 'utf8' });
    const lines = output.trim().split('\n');
    return JSON.parse(lines[lines.length - 1]);
}

async function signIn(context, options) {
    const page = await context.newPage();
    await page.goto(options.base + '/pinegrap/', { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="email"]', options.user);
    await page.fill('input[name="password"]', options.password);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
        page.click('input[type="submit"]')
    ]);
    if (await page.$('input[name="password"]')) {
        throw new Error('Sign-in failed for ' + options.user + ' at ' + page.url());
    }
    await page.close();
}

// The panel reads the scheme from localStorage before it paints (output.php),
// so it is set on every document before any script runs.
function themeScript(theme) {
    return `try { localStorage.setItem('pinegrap backend color scheme', ${JSON.stringify(theme)}); } catch (e) {}`;
}

// The guided tours (assets/js/pg_tour.js) open on a person's first visit to
// a screen and would cover every picture: their automatic start is turned
// into a no-op as soon as the engine assigns window.PgTour.
const NO_TOUR_SCRIPT = `(() => {
    let engine;
    Object.defineProperty(window, 'PgTour', {
        configurable: true,
        get() { return engine; },
        set(value) {
            if (value && typeof value === 'object') {
                value.autoStart = function () {};
            }
            engine = value;
        }
    });
})();`;

// Settles a screen for a stable picture: the loading curtain gone, fonts
// and images loaded, no caret and no running animation.
async function settle(page) {
    await page.waitForLoadState('load');
    try {
        await page.waitForLoadState('networkidle', { timeout: 8000 });
    } catch (e) {
        // Screens that poll never go idle; the load event is enough for them.
    }
    await page.addStyleTag({
        content: '*, *::before, *::after { transition: none !important; animation: none !important; caret-color: transparent !important; }'
            + ' #pg-preloader, .pg-preloader, #preloader { display: none !important; }'
    });
    await page.evaluate(async () => {
        if (document.fonts && document.fonts.ready) {
            await document.fonts.ready;
        }
        await Promise.all(Array.from(document.images).filter(img => !img.complete).map(img => new Promise(resolve => {
            img.addEventListener('load', resolve, { once: true });
            img.addEventListener('error', resolve, { once: true });
            setTimeout(resolve, 3000);
        })));
    });
    await page.waitForTimeout(400);
}

function escapeHtml(text) {
    return String(text).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
}

function writeSheet(options, shots, problems) {
    const rows = [];
    const byScreen = new Map();
    for (const shot of shots) {
        if (!byScreen.has(shot.screen)) {
            byScreen.set(shot.screen, []);
        }
        byScreen.get(shot.screen).push(shot);
    }
    for (const [screen, list] of byScreen) {
        rows.push('<section><h2>' + escapeHtml(screen) + ' <small>' + escapeHtml(list[0].url) + '</small></h2><div class="shots">'
            + list.map(shot => '<figure class="' + shot.viewport + '"><a href="' + escapeHtml(shot.file) + '"><img loading="lazy" src="'
                + escapeHtml(shot.file) + '" alt="' + escapeHtml(screen + ' ' + shot.theme + ' ' + shot.viewport) + '"></a><figcaption>'
                + escapeHtml(shot.theme + ' · ' + shot.viewport) + '</figcaption></figure>').join('')
            + '</div></section>');
    }
    const html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        + '<title>Panel screenshots</title><style>'
        + ':root{--bg:#f6f7f9;--fg:#1d2025;--muted:#5f6670;--card:#fff;--line:#d9dde3}'
        + '@media (prefers-color-scheme: dark){:root:not([data-theme="light"]){--bg:#15171a;--fg:#e6e8eb;--muted:#9aa1ab;--card:#1e2125;--line:#30353b}}'
        + ':root[data-theme="dark"]{--bg:#15171a;--fg:#e6e8eb;--muted:#9aa1ab;--card:#1e2125;--line:#30353b}'
        + 'body{margin:0;padding:16px;background:var(--bg);color:var(--fg);font:14px/1.5 system-ui,sans-serif}'
        + 'h1{font-size:20px;margin:0 0 4px}h2{font-size:16px;margin:24px 0 8px}h2 small{color:var(--muted);font-weight:400;font-size:12px;word-break:break-all}'
        + '.meta{color:var(--muted)}.shots{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-start}'
        + 'figure{margin:0;background:var(--card);border:1px solid var(--line);border-radius:8px;padding:8px;max-width:100%;box-sizing:border-box}'
        + 'figure.wide{width:min(560px,100%)}figure.phone{width:min(200px,100%)}figure img{display:block;width:100%;height:auto;max-height:900px;object-fit:cover;object-position:top}'
        + 'figcaption{color:var(--muted);font-size:12px;margin-top:4px}.problems{color:#c0392b}'
        + '</style></head><body><h1>Panel screenshots</h1><p class="meta">' + escapeHtml(options.label || '') + ' · '
        + escapeHtml(new Date().toISOString().slice(0, 16).replace('T', ' ')) + ' UTC · ' + shots.length + ' images</p>'
        + (problems.length ? '<ul class="problems">' + problems.map(p => '<li>' + escapeHtml(p) + '</li>').join('') + '</ul>' : '')
        + rows.join('') + '</body></html>';
    fs.writeFileSync(path.join(options.out, 'index.html'), html);
}

async function main() {
    const options = parseArgs(process.argv.slice(2));
    const screens = loadScreens(options.only);
    const ids = options.seed ? runSeed() : {};
    fs.mkdirSync(options.out, { recursive: true });

    const themes = options.theme ? [options.theme] : THEMES;
    const viewports = options.viewport ? [options.viewport] : Object.keys(VIEWPORTS);
    const browser = await chromium.launch();
    const shots = [];
    const problems = [];

    try {
        for (const viewportName of viewports) {
            for (const theme of themes) {
                const viewport = VIEWPORTS[viewportName];
                const context = await browser.newContext({
                    viewport: { width: viewport.width, height: viewport.height },
                    deviceScaleFactor: viewport.deviceScaleFactor,
                    isMobile: viewport.isMobile,
                    hasTouch: Boolean(viewport.hasTouch),
                    colorScheme: theme,
                    reducedMotion: 'reduce',
                    locale: 'tr-TR',
                    timezoneId: 'Europe/Istanbul'
                });
                await context.addInitScript(themeScript(theme));
                await context.addInitScript(NO_TOUR_SCRIPT);
                await signIn(context, options);
                const page = await context.newPage();
                const errors = [];
                page.on('pageerror', error => errors.push(error.message));

                for (const screen of screens) {
                    const file = screen.name + '-' + theme + '-' + viewportName + '.png';
                    if (screen.viewports && !screen.viewports.includes(viewportName)) {
                        continue;
                    }
                    errors.length = 0;
                    try {
                        const url = options.base + '/pinegrap/' + fillPath(screen.path, ids);
                        const cookies = Object.entries(screen.cookies || {});
                        if (cookies.length) {
                            await context.addCookies(cookies.map(([name, value]) => ({ name, value: String(value), url: options.base })));
                        }
                        const response = await page.goto(url, { waitUntil: 'domcontentloaded' });
                        await settle(page);
                        for (const [name] of cookies) {
                            await context.clearCookies({ name });
                        }
                        const click = clickFor(screen, viewportName);
                        if (click) {
                            await page.click(click);
                            await page.waitForTimeout(600);
                        }
                        if (response && response.status() >= 400) {
                            problems.push(file + ': HTTP ' + response.status());
                        }
                        if (/\/pinegrap\/(index\.php)?(\?|$)/.test(page.url()) && !/\/pinegrap\/(index\.php)?(\?|$)/.test(url)) {
                            problems.push(file + ': redirected to ' + page.url());
                        }
                        if (screen.crop) {
                            const element = await page.$(screen.crop);
                            if (!element || !(await element.isVisible()) || !(await inViewport(page, element))) {
                                problems.push(file + ': ' + screen.crop + ' not visible');
                                continue;
                            }
                            await element.screenshot({ path: path.join(options.out, file) });
                        } else {
                            await page.screenshot({ path: path.join(options.out, file), fullPage: true });
                        }
                        shots.push({ screen: screen.name, theme, viewport: viewportName, file, url: url.slice(options.base.length) });
                        for (const message of errors) {
                            problems.push(file + ': script error: ' + message);
                        }
                        process.stdout.write('ok   ' + file + '\n');
                    } catch (error) {
                        problems.push(file + ': ' + error.message.split('\n')[0]);
                        process.stdout.write('FAIL ' + file + '\n');
                    }
                }
                await context.close();
            }
        }
    } finally {
        await browser.close();
    }

    writeSheet(options, shots, problems);
    for (const problem of problems) {
        process.stderr.write('problem: ' + problem + '\n');
    }
    process.stdout.write(shots.length + ' image(s) in ' + options.out + ' (index.html)\n');
    process.exitCode = problems.length ? 1 : 0;
}

if (require.main === module) {
    main().catch(error => {
        process.stderr.write(error.message + '\n');
        process.exit(2);
    });
}

module.exports = { parseArgs, fillPath, clickFor };
