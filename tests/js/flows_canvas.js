#!/usr/bin/env node
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A browser harness for the Flows canvas (workspace_flows.php,
 * assets/js/workspace_flow.js), run by hand, not by CI. It serves the flows
 * screen on a local port as the panel prints it: the panel's stylesheets,
 * Bootstrap's scripts and pgUI, the #ws-config block and the bundles the
 * screen loads (ws_screen_assets()), each from its sources (--min: the
 * built .min.js). Its panel actions are answered by a store held in memory
 * (flows_canvas_store.js); the texts, the bundles' sources, the canvas's
 * settings and the graph rules come from the product's PHP
 * (flows_canvas_server.php). A headless Chromium is driven through the
 * DevTools protocol over a pipe, with Node's own modules only; every
 * scenario of flows_canvas_scenarios.js gets a browser context and a store
 * of its own, and one PASS or FAIL line.
 *
 * The screen's other scripts (the date fields, a voice message's text,
 * backend.min.js and the main menu's Workspace list) are left out: the
 * canvas does not use them.
 *
 * Needs Node 18 or later, PHP (PG_PHP, else php on the PATH) and Chromium
 * or Chrome (PG_CHROMIUM, else the usual places and the PATH).
 *
 * Usage:  node tests/js/flows_canvas.js [filter] [--min] [--motion] [--dark] [--shots=<dir>] [--verbose]
 *         filter    only the scenarios whose name holds it
 *         --min     the built .min.js bundles instead of their sources
 *         --motion  with the fades of dialogs and toasts (the page is told
 *                   the person prefers reduced motion otherwise)
 *         --dark    the panel's dark theme
 *         --shots   a picture of the screen at the end of each scenario
 *         --verbose every check, and what the page logged
 * Exit:   0 when every scenario passed, 1 when one failed, 2 when the
 *         harness could not run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

'use strict';

const { spawn } = require('child_process');
const fs = require('fs');
const http = require('http');
const os = require('os');
const path = require('path');
const readline = require('readline');
const { StringDecoder } = require('string_decoder');
const { createStore } = require('./flows_canvas_store.js');
const scenarios = require('./flows_canvas_scenarios.js');

const ROOT = path.resolve(__dirname, '..', '..');
const PANEL = path.join(ROOT, 'pinegrap');

// The bundles the flows screen prints, in its order (ws_screen_assets()).
const SCREEN_BUNDLES = ['assets/js/workspace.min.js', 'assets/js/workspace_palette.min.js', 'assets/js/workspace_flow.min.js'];

const ARGS = process.argv.slice(2);
const FILTER = ARGS.filter((arg) => !arg.startsWith('--'))[0] || '';
const MIN = ARGS.includes('--min');
const VERBOSE = ARGS.includes('--verbose');
const SHOTS = (ARGS.find((arg) => arg.startsWith('--shots=')) || '').slice('--shots='.length);
const MOTION = ARGS.includes('--motion');
const DARK = ARGS.includes('--dark');
const SCENARIO_TIMEOUT = 120000;

const TYPES = {
    '.js': 'text/javascript; charset=utf-8', '.mjs': 'text/javascript; charset=utf-8', '.css': 'text/css; charset=utf-8', '.json': 'application/json',
    '.map': 'application/json', '.svg': 'image/svg+xml', '.png': 'image/png', '.jpg': 'image/jpeg', '.gif': 'image/gif', '.ico': 'image/x-icon',
    '.woff2': 'font/woff2', '.woff': 'font/woff', '.ttf': 'font/ttf'
};

function stop(message) {
    process.stderr.write(message + '\n');
    process.exit(2);
}

// ── Chromium and PHP ───────────────────────────────────────────────────

function onPath(name) {
    for (const dir of (process.env.PATH || '').split(path.delimiter)) {
        const file = path.join(dir, name);

        if (dir && fs.existsSync(file)) {
            return file;
        }
    }

    return '';
}

function findChromium() {
    if (process.env.PG_CHROMIUM) {
        return fs.existsSync(process.env.PG_CHROMIUM) ? process.env.PG_CHROMIUM : stop('PG_CHROMIUM names no file: ' + process.env.PG_CHROMIUM);
    }

    const places = [
        '/opt/google/chrome/chrome',
        '/usr/bin/google-chrome',
        '/usr/bin/google-chrome-stable',
        '/usr/bin/chromium',
        '/usr/bin/chromium-browser',
        '/snap/bin/chromium',
        '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        '/Applications/Chromium.app/Contents/MacOS/Chromium',
        'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe'
    ];

    // Playwright's own browsers, the newest first.
    const cache = process.env.PLAYWRIGHT_BROWSERS_PATH || path.join(os.homedir(), '.cache', 'ms-playwright');

    if (fs.existsSync(cache)) {
        fs.readdirSync(cache).filter((name) => /^chromium-\d+$/.test(name)).sort((a, b) => parseInt(b.slice(9), 10) - parseInt(a.slice(9), 10)).forEach((name) => {
            places.push(path.join(cache, name, 'chrome-linux', 'chrome'));
        });
    }

    const found = places.find((file) => fs.existsSync(file))
        || ['google-chrome', 'google-chrome-stable', 'chromium', 'chromium-browser', 'chrome'].map(onPath).find(Boolean);

    return found || stop('No Chromium or Chrome found: name it with PG_CHROMIUM=/path/to/chrome.');
}

// flows_canvas_server.php, kept running: its first line, then one answer
// per graph sent.
function startPhp() {
    const binary = process.env.PG_PHP || 'php';
    const child = spawn(binary, [path.join(__dirname, 'flows_canvas_server.php')], { cwd: ROOT, stdio: ['pipe', 'pipe', 'pipe'] });
    const waiting = new Map();
    let errors = '';
    let id = 0;
    let first = null;
    const setup = new Promise((resolve, reject) => { first = { resolve: resolve, reject: reject }; });

    child.stderr.on('data', (chunk) => { errors += chunk; });
    child.on('error', (error) => first && first.reject(new Error('PHP could not be started (' + binary + '): ' + error.message + '; name it with PG_PHP=/path/to/php.')));
    child.on('exit', (code) => {
        const error = new Error('PHP stopped (' + code + '): ' + errors.trim().slice(-2000));

        if (first) {
            first.reject(error);
        }

        waiting.forEach((call) => call.reject(error));
        waiting.clear();
    });

    readline.createInterface({ input: child.stdout }).on('line', (line) => {
        if (first) {
            const call = first;

            first = null;
            call.resolve(JSON.parse(line));
            return;
        }

        const answer = JSON.parse(line);
        const call = waiting.get(answer.id);

        if (call) {
            waiting.delete(answer.id);
            call.resolve({ graph: answer.graph, problems: answer.problems, warnings: answer.warnings });
        }
    });

    return {
        setup: setup,
        check: (graph) => new Promise((resolve, reject) => {
            const n = ++id;

            waiting.set(n, { resolve: resolve, reject: reject });
            child.stdin.write(JSON.stringify({ id: n, graph: graph }) + '\n');
        }),
        close: () => child.stdin.end()
    };
}

// ── The screen ─────────────────────────────────────────────────────────

function today() {
    return new Date().toISOString().slice(0, 10);
}

// The #ws-config of the flows screen (ws_screen_config()) for the person
// the store is open for; the settings that are not the canvas's are those
// of a site with nothing more switched on.
function screenConfig(base, setup, store) {
    const urls = { erp_quote_copy: '' };
    const files = {
        workspace: 'workspace.php', tasks: 'workspace_tasks.php', board: 'workspace_board.php', calendar: 'workspace_calendar.php',
        timeline: 'workspace_timeline.php', notes: 'workspace_notes.php', flows: 'workspace_flows.php', notes_export: 'workspace_notes_export.php',
        notes_import: 'workspace_notes_import.php', file_save: 'workspace_file_save.php', settings: 'workspace_settings.php', edit_user: 'edit_user.php'
    };

    Object.keys(files).forEach((name) => { urls[name] = base + '/pinegrap/' + files[name]; });

    return {
        mode: 'flows', api_url: base + '/pinegrap/api.php', token: store.token, me: store.me, me_role: 0, locale: 'en', timezone: 'UTC', today: today(),
        board_from: today(), event_kinds: [], meetings: false, poll: { active: 4 }, open_channel: 0, message_max: 20000, ai_rewrite: '',
        urls: urls, notes: false, notes_import: null, library: false, office: null, strings: setup.texts,
        recurrence: { ready: false }, reminders: { ready: false }, scheduled: null, scheduled_messages: false, threads: false, palette: [], channel_icons: [],
        groups: { manage: false, depth: 2 }, eras: false, pins: false, approvals: null, acks: false, status: {}, blocks: false, saved_replies: false,
        customer: false, templates: {}, calc: {}, task_time: { ready: false }, task_links: false, delegation: {}, guests: { can_host: false, durations: [] },
        forwards: null, flows: setup.config, tour: null
    };
}

// workspace_flows.php as output_header() and ws_screen_assets() print it,
// the probe of flows_canvas_page.js first.
function screenPage(base, setup, store) {
    const json = JSON.stringify(screenConfig(base, setup, store)).replace(/</g, '\\u003c').replace(/>/g, '\\u003e').replace(/&/g, '\\u0026');
    const scripts = [];

    SCREEN_BUNDLES.forEach((bundle) => {
        (MIN ? [bundle] : setup.bundles[bundle]).forEach((file) => scripts.push(file));
    });

    return '<!DOCTYPE html>\n<html lang="en" data-bs-theme="' + (DARK ? 'dark' : 'light') + '">\n<head>\n<meta charset="utf-8">\n'
        + '<meta name="viewport" content="width=device-width, initial-scale=1">\n<title>Flows</title>\n'
        + '<style>@layer colors, config, root, reboot, layout, content, forms, components, vendor, custom, helpers, utilities;</style>\n'
        + '<link rel="stylesheet" href="' + base + '/pinegrap/assets/lib/bootstrap-6/css/bootstrap.min.css">\n'
        + '<style>@import url("' + base + '/pinegrap/assets/fonts/bootstrap-icons/bootstrap-icons.min.css") layer(vendor);</style>\n'
        + '<script type="module" src="' + base + '/pinegrap/assets/js/pg_bs6_bridge.js"></script>\n'
        + '<script src="' + base + '/pinegrap/assets/js/pg_ui.js"></script>\n'
        + '<link rel="stylesheet" href="' + base + '/pinegrap/assets/css/backend.src.css">\n'
        + '<script src="' + base + '/harness/page.js"></script>\n'
        + '</head>\n<body class="workspace workspace_flows">\n'
        + '<main id="content" class="container-fluid p-0"><div class="ws-shell"><div class="ws-shell-main"><div class="row"><div class="col-12">'
        + '<div id="ws-root"><div class="ws-empty">Loading…</div></div></div></div></div></div></main>\n'
        + '<script type="application/json" id="ws-config">' + json + '</script>\n'
        + scripts.map((file) => '<script src="' + base + '/pinegrap/' + file + '" defer></script>').join('\n') + '\n'
        + '</body>\n</html>\n';
}

// One server for the run; each scenario's page and store live under a path
// of its own, so a request of a page already left reaches nobody.
function serve() {
    const mounts = new Map();
    const server = http.createServer((request, response) => {
        const url = new URL(request.url, 'http://127.0.0.1');
        const match = /^(\/s\d+)(\/.*)$/.exec(url.pathname);
        const mount = match ? mounts.get(match[1]) : null;
        const route = match ? match[2] : url.pathname;
        // Every answer closes its connection: Chromium sends a request again
        // when a connection it reused closes before answering, which would
        // turn a request dropped on purpose into one answered.
        const reply = (status, type, body) => {
            response.writeHead(status, { 'Content-Type': type, 'Cache-Control': 'no-store', 'Connection': 'close' });
            response.end(body);
        };

        if (!mount) {
            reply((url.pathname === '/favicon.ico') ? 204 : 404, 'text/plain', '');
            return;
        }

        if (route === '/pinegrap/workspace_flows.php') {
            reply(200, 'text/html; charset=utf-8', mount.page());
            return;
        }

        if (route === '/harness/page.js') {
            reply(200, TYPES['.js'], fs.readFileSync(path.join(__dirname, 'flows_canvas_page.js')));
            return;
        }

        if ((route === '/pinegrap/api.php') && (request.method === 'POST')) {
            const chunks = [];

            request.on('data', (chunk) => chunks.push(chunk));
            request.on('end', () => {
                let body;

                try {
                    body = JSON.parse(Buffer.concat(chunks).toString('utf8') || '{}');
                } catch (error) {
                    reply(400, 'text/plain', 'The request is not JSON.');
                    return;
                }

                mount.store.receive(body, request.headers['x-harness-keepalive'] === '1', (answer) => {
                    if (answer.drop) {
                        request.socket.destroy();
                    } else if (answer.html) {
                        reply(answer.status, 'text/html; charset=utf-8', answer.html);
                    } else {
                        reply(answer.status, 'application/json; charset=utf-8', JSON.stringify(answer.body));
                    }
                });
            });
            return;
        }

        if (route.startsWith('/pinegrap/assets/')) {
            let file = '';

            try {
                file = path.resolve(PANEL, decodeURIComponent(route.slice('/pinegrap/'.length)));
            } catch (error) {
                file = '';
            }

            if (file.startsWith(path.join(PANEL, 'assets') + path.sep) && fs.existsSync(file) && fs.statSync(file).isFile()) {
                reply(200, TYPES[path.extname(file).toLowerCase()] || 'application/octet-stream', fs.readFileSync(file));
                return;
            }
        }

        reply((route === '/favicon.ico') ? 204 : 404, 'text/plain', '');
    });

    return new Promise((resolve) => {
        server.listen(0, '127.0.0.1', () => resolve({
            origin: 'http://127.0.0.1:' + server.address().port,
            mount: (base, entry) => mounts.set(base, entry),
            unmount: (base) => mounts.delete(base),
            close: () => new Promise((done) => server.close(done))
        }));
    });
}

// ── The DevTools protocol over a pipe ─────────────────────────────────

class Chromium {
    constructor(binary) {
        this.profile = fs.mkdtempSync(path.join(os.tmpdir(), 'pg-flows-canvas-'));
        this.binary = binary;
        this.id = 0;
        this.waiting = new Map();
        this.sessions = new Map();
        this.buffer = '';
        this.decoder = new StringDecoder('utf8');
        this.errors = '';

        const flags = [
            '--headless=new', '--remote-debugging-pipe', '--user-data-dir=' + this.profile, '--no-first-run', '--no-default-browser-check',
            '--disable-gpu', '--disable-extensions', '--disable-dev-shm-usage', '--mute-audio', '--hide-scrollbars',
            '--disable-background-timer-throttling', '--disable-renderer-backgrounding', '--disable-backgrounding-occluded-windows', '--lang=en-US'
        ];

        // Chromium refuses to start as root inside its sandbox.
        if (process.getuid && (process.getuid() === 0)) {
            flags.push('--no-sandbox');
        }

        this.child = spawn(binary, flags.concat(['about:blank']), { stdio: ['ignore', 'ignore', 'pipe', 'pipe', 'pipe'] });
        this.child.stderr.on('data', (chunk) => { this.errors = (this.errors + chunk).slice(-4000); });

        const end = (error) => {
            this.closed = this.closed || error;
            this.waiting.forEach((call) => call.reject(this.closed));
            this.waiting.clear();
        };

        this.child.on('error', (error) => end(new Error('Chromium could not be started (' + binary + '): ' + error.message)));
        this.child.on('exit', (code) => end(new Error('Chromium stopped (' + code + '): ' + this.errors.trim().split('\n').slice(-5).join('\n'))));
        this.child.stdio[4].on('data', (chunk) => this.read(chunk));
        this.child.stdio[3].on('error', () => {});
    }

    read(chunk) {
        this.buffer += this.decoder.write(chunk);

        let end;

        while ((end = this.buffer.indexOf('\0')) !== -1) {
            const message = JSON.parse(this.buffer.slice(0, end));

            this.buffer = this.buffer.slice(end + 1);

            if (message.id && this.waiting.has(message.id)) {
                const call = this.waiting.get(message.id);

                this.waiting.delete(message.id);

                if (message.error) {
                    call.reject(new Error(call.method + ': ' + message.error.message + (message.error.data ? ' (' + message.error.data + ')' : '')));
                } else {
                    call.resolve(message.result || {});
                }
            } else if (message.method && message.sessionId && this.sessions.has(message.sessionId)) {
                this.sessions.get(message.sessionId).event(message);
            }
        }
    }

    send(method, params, sessionId) {
        if (this.closed) {
            return Promise.reject(this.closed);
        }

        return new Promise((resolve, reject) => {
            const id = ++this.id;
            const message = { id: id, method: method, params: params || {} };

            if (sessionId) {
                message.sessionId = sessionId;
            }

            this.waiting.set(id, { resolve: resolve, reject: reject, method: method });
            this.child.stdio[3].write(JSON.stringify(message) + '\0');
        });
    }

    async version() {
        return (await this.send('Browser.getVersion')).product;
    }

    // A page in a browser context of its own; touch: a touch screen (a
    // coarse pointer, no hover).
    async page(width, height, touch) {
        const context = (await this.send('Target.createBrowserContext', { disposeOnDetach: true })).browserContextId;
        const target = (await this.send('Target.createTarget', { url: 'about:blank', browserContextId: context })).targetId;
        const session = (await this.send('Target.attachToTarget', { targetId: target, flatten: true })).sessionId;
        const page = new Page(this, session, context);

        this.sessions.set(session, page);
        await page.start(width, height, touch);

        return page;
    }

    async close() {
        if (!this.closed) {
            await this.send('Browser.close').catch(() => {});
        }

        await new Promise((resolve) => setTimeout(resolve, 200));

        if (!this.child.killed && (this.child.exitCode === null)) {
            this.child.kill();
        }

        fs.rmSync(this.profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
    }
}

// Key names as the DevTools protocol takes them.
const KEYS = {
    Enter: { code: 'Enter', keyCode: 13, text: '\r' },
    Tab: { code: 'Tab', keyCode: 9 },
    Escape: { code: 'Escape', keyCode: 27 },
    Backspace: { code: 'Backspace', keyCode: 8 },
    Delete: { code: 'Delete', keyCode: 46 },
    ArrowLeft: { code: 'ArrowLeft', keyCode: 37 },
    ArrowUp: { code: 'ArrowUp', keyCode: 38 },
    ArrowRight: { code: 'ArrowRight', keyCode: 39 },
    ArrowDown: { code: 'ArrowDown', keyCode: 40 },
    ' ': { code: 'Space', keyCode: 32, text: ' ' },
    '+': { code: 'Equal', keyCode: 187, text: '+' },
    '=': { code: 'Equal', keyCode: 187, text: '=' },
    '-': { code: 'Minus', keyCode: 189, text: '-' },
    '_': { code: 'Minus', keyCode: 189, text: '_' }
};
const MODIFIERS = { Alt: 1, Control: 2, Meta: 4, Shift: 8 };

function keyOf(name) {
    if (KEYS[name]) {
        return Object.assign({ key: name }, KEYS[name]);
    }

    if (/^[a-zA-Z]$/.test(name)) {
        return { key: name, code: 'Key' + name.toUpperCase(), keyCode: name.toUpperCase().charCodeAt(0), text: name };
    }

    if (/^[0-9]$/.test(name)) {
        return { key: name, code: 'Digit' + name, keyCode: name.charCodeAt(0), text: name };
    }

    throw new Error('No key named ' + JSON.stringify(name));
}

class Page {
    constructor(browser, session, context) {
        this.browser = browser;
        this.session = session;
        this.context = context;
        this.errors = [];
        this.logs = [];
        this.dialogs = [];
        this.drag = null;
        this.mouse = { x: 0, y: 0, buttons: 0 };
    }

    send(method, params) {
        return this.browser.send(method, params, this.session);
    }

    async start(width, height, touch) {
        this.touchScreen = !!touch;
        await Promise.all([this.send('Page.enable'), this.send('Runtime.enable'), this.send('Log.enable')]);
        await this.send('Emulation.setDeviceMetricsOverride', { width: width, height: height, deviceScaleFactor: 1, mobile: false });
        // Dialogs and toasts appear at once without their fades, unless
        // --motion asks for them.
        await this.send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: MOTION ? 'no-preference' : 'reduce' }] });

        if (touch) {
            await this.send('Emulation.setTouchEmulationEnabled', { enabled: true, maxTouchPoints: 2 });
        }
    }

    event(message) {
        const params = message.params || {};

        if (message.method === 'Runtime.exceptionThrown') {
            const details = params.exceptionDetails || {};
            const error = details.exception ? (details.exception.description || details.exception.value) : details.text;

            this.errors.push(String(error).split('\n').slice(0, 4).join(' / '));
        } else if ((message.method === 'Runtime.consoleAPICalled') && ((params.type === 'error') || (params.type === 'warning') || (params.type === 'assert'))) {
            this.logs.push('console.' + params.type + ': ' + (params.args || []).map((arg) => (arg.value !== undefined) ? String(arg.value) : (arg.description || arg.type)).join(' '));
        } else if (message.method === 'Log.entryAdded') {
            const entry = params.entry || {};

            this.logs.push(entry.level + ': ' + entry.text + (entry.url ? ' ' + entry.url : ''));

            // A file of the screen that did not load is the harness's fault
            // or a missing file, never a passing scenario.
            if ((entry.level === 'error') && entry.url && /\/assets\/|\/harness\//.test(entry.url)) {
                this.errors.push('not loaded: ' + entry.url + ' (' + entry.text + ')');
            }
        } else if (message.method === 'Page.javascriptDialogOpening') {
            this.dialogs.push({ type: params.type, message: params.message });

            this.send('Page.handleJavaScriptDialog', { accept: true }).catch(() => {});
        } else if (message.method === 'Input.dragIntercepted') {
            this.drag = params.data;
        }
    }

    // fn(PGHarness, ...args) in the page; its value, awaited.
    async evaluate(fn, ...args) {
        const expression = '(' + fn.toString() + ')(window.PGHarness, ' + args.map((arg) => JSON.stringify(arg === undefined ? null : arg)).join(', ') + ')';
        const result = await this.send('Runtime.evaluate', { expression: expression, awaitPromise: true, returnByValue: true });

        if (result.exceptionDetails) {
            const details = result.exceptionDetails;

            throw new Error('In the page: ' + (details.exception ? (details.exception.description || details.exception.value) : details.text));
        }

        return result.result.value;
    }

    async navigate(url) {
        const result = await this.send('Page.navigate', { url: url });

        if (result.errorText) {
            throw new Error('The page did not load: ' + result.errorText);
        }
    }

    async mouseTo(x, y, steps) {
        const from = { x: this.mouse.x, y: this.mouse.y };
        const count = Math.max(1, steps || 1);

        for (let i = 1; i <= count; i++) {
            this.mouse.x = Math.round(from.x + (x - from.x) * i / count);
            this.mouse.y = Math.round(from.y + (y - from.y) * i / count);
            await this.send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: this.mouse.x, y: this.mouse.y, button: this.mouse.button || 'none', buttons: this.mouse.buttons, modifiers: this.mouse.modifiers || 0 });
        }
    }

    async mouseDown(button, modifiers, count) {
        this.mouse.button = button || 'left';
        this.mouse.buttons = { left: 1, right: 2, middle: 4 }[this.mouse.button];
        this.mouse.modifiers = modifiers || 0;
        await this.send('Input.dispatchMouseEvent', { type: 'mousePressed', x: this.mouse.x, y: this.mouse.y, button: this.mouse.button, buttons: this.mouse.buttons, clickCount: count || 1, modifiers: this.mouse.modifiers });
    }

    async mouseUp(count) {
        const button = this.mouse.button || 'left';

        this.mouse.buttons = 0;
        await this.send('Input.dispatchMouseEvent', { type: 'mouseReleased', x: this.mouse.x, y: this.mouse.y, button: button, buttons: 0, clickCount: count || 1, modifiers: this.mouse.modifiers || 0 });
        this.mouse.button = 'none';
        this.mouse.modifiers = 0;
    }

    async wheel(x, y, deltaX, deltaY, modifiers) {
        this.mouse.x = x;
        this.mouse.y = y;
        await this.send('Input.dispatchMouseEvent', { type: 'mouseWheel', x: x, y: y, deltaX: deltaX, deltaY: deltaY, modifiers: modifiers || 0, button: 'none', buttons: 0 });
    }

    async key(name, modifiers, type) {
        const key = keyOf(name);
        const writes = key.text && !(modifiers & (MODIFIERS.Control | MODIFIERS.Meta | MODIFIERS.Alt));
        const text = (modifiers & MODIFIERS.Shift) && /^[a-z]$/.test(key.text || '') ? key.text.toUpperCase() : key.text;
        const event = { key: (text && /^[a-zA-Z]$/.test(text)) ? text : key.key, code: key.code, windowsVirtualKeyCode: key.keyCode, nativeVirtualKeyCode: key.keyCode, modifiers: modifiers || 0 };

        if (type !== 'up') {
            await this.send('Input.dispatchKeyEvent', Object.assign({ type: writes ? 'keyDown' : 'rawKeyDown' }, event, writes ? { text: text, unmodifiedText: key.text } : {}));
        }

        if (type !== 'down') {
            await this.send('Input.dispatchKeyEvent', Object.assign({ type: 'keyUp' }, event));
        }
    }

    // Touches reach a page only while it is a touch screen: a page that is
    // not one is made one for the gesture.
    async touches(steps) {
        if (!this.touchScreen) {
            await this.send('Emulation.setTouchEmulationEnabled', { enabled: true, maxTouchPoints: 2 });
        }

        for (const step of steps) {
            await this.send('Input.dispatchTouchEvent', { type: step.type, touchPoints: step.points.map((point, index) => ({ x: point.x, y: point.y, id: index, radiusX: 4, radiusY: 4, force: 1 })) });
        }

        if (!this.touchScreen) {
            await this.send('Emulation.setTouchEmulationEnabled', { enabled: false });
        }
    }

    async picture() {
        return Buffer.from((await this.send('Page.captureScreenshot', { format: 'png' })).data, 'base64');
    }

    async close() {
        this.browser.sessions.delete(this.session);
        await this.browser.send('Target.disposeBrowserContext', { browserContextId: this.context }).catch(() => {});
    }
}

// ── What a scenario is given ───────────────────────────────────────────

function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

function same(a, b) {
    return JSON.stringify(a) === JSON.stringify(b);
}

// A step that cannot go on: the scenario stops there.
class Stop extends Error {}

function driver(page, store, setup, url) {
    const checks = [];

    const h = {
        store: store,
        checks: checks,
        sleep: sleep,

        // A text as the screen shows it: the key's text with {1}, {2}...
        // filled as workspace.js's t() fills them.
        t: (key, ...values) => {
            let text = Object.prototype.hasOwnProperty.call(setup.texts, key) ? setup.texts[key] : key;

            values.forEach((value, index) => { text = text.split('{' + (index + 1) + '}').join(String(value)); });

            return text;
        },

        ok: (label, pass, detail) => {
            checks.push({ label: label, pass: !!pass, detail: pass ? '' : (detail === undefined ? '' : (typeof detail === 'string' ? detail : JSON.stringify(detail))) });

            return !!pass;
        },

        same: (label, actual, expected) => h.ok(label, same(actual, expected), 'got ' + JSON.stringify(actual) + ', wanted ' + JSON.stringify(expected)),

        near: (label, actual, expected, within) => h.ok(label, (typeof actual === 'number') && (Math.abs(actual - expected) <= (within || 1)), 'got ' + JSON.stringify(actual) + ', wanted ' + expected + ' ± ' + (within || 1)),

        // A check the rest of the scenario stands on.
        need: (label, pass, detail) => {
            if (!h.ok(label, pass, detail)) {
                throw new Stop(label);
            }
        },

        page: (fn, ...args) => page.evaluate(fn, ...args),
        probe: (name, ...args) => page.evaluate((harness, call, list) => harness[call].apply(harness, list), name, args),
        state: () => h.probe('state'),

        // Until fn(PGHarness) in the page gives something truthy; that value.
        waitFor: async (label, fn, timeout, ...args) => {
            const end = Date.now() + (timeout || 5000);
            let last;

            for (;;) {
                try {
                    last = await page.evaluate(fn, ...args);
                } catch (error) {
                    last = error.message;
                }

                if (last && !(typeof last === 'string' && last.startsWith('In the page:'))) {
                    h.ok(label, true);
                    return last;
                }

                if (Date.now() > end) {
                    h.ok(label, false, 'not within ' + (timeout || 5000) + ' ms; last: ' + JSON.stringify(last));
                    throw new Stop(label);
                }

                await sleep(40);
            }
        },

        // A check that holds within timeout: fn(PGHarness) in the page gives
        // something truthy; that value, or null (the check failed) after it.
        eventually: async (label, fn, timeout, ...args) => {
            const end = Date.now() + (timeout || 3000);
            let last;

            for (;;) {
                try {
                    last = await page.evaluate(fn, ...args);
                } catch (error) {
                    last = null;
                }

                if (last) {
                    h.ok(label, true);
                    return last;
                }

                if (Date.now() > end) {
                    h.ok(label, false, 'not within ' + (timeout || 3000) + ' ms');
                    return null;
                }

                await sleep(40);
            }
        },

        // Until fn() here gives something truthy (the store's calls).
        until: async (label, fn, timeout) => {
            const end = Date.now() + (timeout || 5000);

            for (;;) {
                const value = fn();

                if (value) {
                    h.ok(label, true);
                    return value;
                }

                if (Date.now() > end) {
                    h.ok(label, false, 'not within ' + (timeout || 5000) + ' ms');
                    throw new Stop(label);
                }

                await sleep(40);
            }
        },

        // No request on its way for a while.
        settle: async (quiet) => {
            let calm = 0;
            const end = Date.now() + 10000;

            while (calm < (quiet || 3)) {
                calm = (await page.evaluate((harness) => harness && harness.settled())) ? calm + 1 : 0;

                if (Date.now() > end) {
                    throw new Stop('the screen did not settle');
                }

                await sleep(50);
            }
        },

        // The flows screen opened (query: ?flow=8 …), its first flow drawn.
        open: async (query) => {
            await page.navigate(url + (query || ''));
            await h.waitFor('the flows screen draws', (harness) => harness && harness.settled() && (!!document.querySelector('#ws-root .wsf-name, #ws-root .wsf-name-text') || !!document.querySelector('#ws-root .wsf-nothing')), 15000);
            await h.settle();
        },

        // Where a pointer meets what spec names; stops when it is not there,
        // or covered by something else (force: as it is).
        at: async (spec, options) => {
            const where = (spec && (typeof spec.x === 'number') && !spec.css && !spec.text) ? { found: true, hit: true, x: spec.x, y: spec.y } : await page.evaluate((harness, given, dx, dy) => harness.point(given, dx, dy), spec, (options || {}).dx || 0, (options || {}).dy || 0);

            if (!where.found) {
                h.ok('found: ' + JSON.stringify(spec), false, 'not on the screen');
                throw new Stop('not found');
            }

            if (!where.hit && !(options || {}).force) {
                h.ok('reachable: ' + JSON.stringify(spec), false, 'covered by ' + where.covered);
                throw new Stop('covered');
            }

            return where;
        },

        click: async (spec, options) => {
            const opts = options || {};
            const where = await h.at(spec, opts);
            const modifiers = (opts.modifiers || []).reduce((sum, name) => sum | MODIFIERS[name], 0);

            await page.mouseTo(where.x, where.y);

            for (let n = 1; n <= (opts.count || 1); n++) {
                await page.mouseDown(opts.button || 'left', modifiers, n);
                await page.mouseUp(n);
            }

            return where;
        },

        // A press here, moves to there, a release; hold: a key held down
        // through it (' ' for Space); cancel: a key pressed before the
        // release (Escape).
        drag: async (from, to, options) => {
            const opts = options || {};
            const start = await h.at(from, opts);
            const end = (to && (typeof to.x === 'number') && !to.css && !to.text) ? to : await h.at(to, Object.assign({}, opts, { force: true }));
            const modifiers = (opts.modifiers || []).reduce((sum, name) => sum | MODIFIERS[name], 0);

            await page.mouseTo(start.x, start.y);

            if (opts.hold) {
                await page.key(opts.hold, 0, 'down');
            }

            await page.mouseDown(opts.button || 'left', modifiers);
            await page.mouseTo(end.x, end.y, opts.steps || 8);

            if (opts.cancel) {
                await page.key(opts.cancel);
            }

            if (opts.before) {
                await opts.before();
            }

            await page.mouseUp();

            if (opts.hold) {
                await page.key(opts.hold, 0, 'up');
            }

            return { from: start, to: end };
        },

        // The steps of a drag one by one, for a drag whose end is known only
        // once it has begun (a field that lights up as a thread is drawn).
        press: async (spec, options) => {
            const where = await h.at(spec, options);

            await page.mouseTo(where.x, where.y);
            await page.mouseDown((options || {}).button || 'left');

            return where;
        },

        moveTo: async (spec, options) => {
            const where = (spec && (typeof spec.x === 'number') && !spec.css && !spec.text) ? spec : await h.at(spec, Object.assign({ force: true }, options || {}));

            await page.mouseTo(where.x, where.y, (options || {}).steps || 8);

            return where;
        },

        release: async () => {
            await page.mouseUp();
        },

        key: async (name, ...modifiers) => {
            await page.key(name, modifiers.reduce((sum, mod) => sum | MODIFIERS[mod], 0));
        },

        type: async (text) => {
            await page.send('Input.insertText', { text: text });
        },

        wheel: async (where, deltaX, deltaY, ...modifiers) => {
            await page.wheel(where.x, where.y, deltaX, deltaY, modifiers.reduce((sum, mod) => sum | MODIFIERS[mod], 0));
        },

        // Two fingers apart by from px around a point, moved to to px apart.
        pinch: async (center, from, to, steps) => {
            const at = (distance) => [{ x: center.x - distance / 2, y: center.y }, { x: center.x + distance / 2, y: center.y }];
            const moves = [{ type: 'touchStart', points: at(from) }];

            for (let i = 1; i <= (steps || 6); i++) {
                moves.push({ type: 'touchMove', points: at(from + (to - from) * i / (steps || 6)) });
            }

            moves.push({ type: 'touchEnd', points: [] });
            await page.touches(moves);
        },

        // A palette entry dragged onto the canvas and dropped at a point
        // (HTML drag and drop, through the browser's drag events).
        dropFrom: async (spec, point) => {
            const start = await h.at(spec);

            page.drag = null;
            await page.send('Input.setInterceptDrags', { enabled: true });
            await page.mouseTo(start.x, start.y);
            await page.mouseDown('left');
            await page.mouseTo(start.x + 24, start.y + 8, 3);
            await h.until('the palette entry is dragged', () => page.drag, 3000);
            await page.send('Input.dispatchDragEvent', { type: 'dragEnter', x: point.x, y: point.y, data: page.drag });
            await page.send('Input.dispatchDragEvent', { type: 'dragOver', x: point.x, y: point.y, data: page.drag });
            await page.send('Input.dispatchDragEvent', { type: 'drop', x: point.x, y: point.y, data: page.drag });
            await page.send('Input.setInterceptDrags', { enabled: false });
            page.mouse.x = point.x;
            page.mouse.y = point.y;
            await page.mouseUp();
        },

        // The question dialog open (null for none), and its answer: the
        // list set to value first, then the button with those words clicked.
        dialog: () => h.probe('dialog'),

        answer: async (button, value) => {
            const dialog = await h.waitFor('a dialog asks', (harness) => harness.dialog(), 5000);

            if (value !== undefined) {
                h.need('the dialog offers ' + value, await h.probe('choose', value), dialog.options);
            }

            // Bootstrap's dialog does not hide while it is still being
            // shown: the click waits for its fade to end.
            await sleep(80);
            await h.waitFor('the dialog is shown', () => {
                const open = document.querySelector('dialog[open]');

                return !!open && open.getAnimations({ subtree: true }).every((animation) => animation.playState !== 'running');
            }, 5000);
            await h.click({ text: button, within: 'dialog[open]' });
            await h.waitFor('the dialog closes', (harness) => !harness.dialog(), 5000);

            return dialog;
        },

        // The page left for another one: the dialogs it raised (a
        // beforeunload question is answered "leave").
        leave: async () => {
            const before = page.dialogs.length;

            await page.navigate('about:blank');
            await sleep(300);

            return page.dialogs.slice(before);
        },

        // The tab hidden: the browser cannot hide a page of its own in
        // headless mode, so the page is told so as a hidden tab is.
        hide: async (hidden) => {
            await page.evaluate((harness, flag) => {
                if (flag) {
                    Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => 'hidden' });
                    Object.defineProperty(document, 'hidden', { configurable: true, get: () => true });
                } else {
                    delete document.visibilityState;
                    delete document.hidden;
                }

                document.dispatchEvent(new Event('visibilitychange'));
            }, hidden !== false);
        },

        // The window losing the focus (the canvas saves on blur).
        blur: async () => {
            await page.evaluate(() => window.dispatchEvent(new Event('blur')));
        },

        saves: () => store.callsOf('ws_flow_save'),
        shot: async (file) => {
            if (SHOTS) {
                fs.mkdirSync(SHOTS, { recursive: true });
                fs.writeFileSync(path.join(SHOTS, file + '.png'), await page.picture());
            }
        }
    };

    return h;
}

// ── The run ────────────────────────────────────────────────────────────

function withTimeout(promise, ms) {
    let timer;

    return Promise.race([
        promise.finally(() => clearTimeout(timer)),
        new Promise((resolve, reject) => { timer = setTimeout(() => reject(new Stop('the scenario took longer than ' + (ms / 1000) + ' s')), ms); })
    ]);
}

async function runScenario(scenario, index, context) {
    const store = await createStore(scenario.fixture, context.php.check);
    const base = '/s' + index;
    const width = scenario.width || 1440;
    const height = scenario.height || 900;

    context.server.mount(base, { store: store, page: () => screenPage(base, context.setup, store) });

    const page = await context.browser.page(width, height, !!scenario.touch);
    const h = driver(page, store, context.setup, context.server.origin + base + '/pinegrap/workspace_flows.php');
    let stopped = '';

    try {
        await withTimeout(scenario.run(h), SCENARIO_TIMEOUT);
    } catch (error) {
        stopped = (error instanceof Stop) ? error.message : (error && error.stack ? error.stack.split('\n').slice(0, 3).join(' / ') : String(error));
    }

    if (SHOTS) {
        await h.shot(String(index).padStart(2, '0') + '-' + scenario.name.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '').toLowerCase().slice(0, 60).replace(/-$/, '')).catch(() => {});
    }

    await page.close();
    context.server.unmount(base);

    return { checks: h.checks, stopped: stopped, errors: page.errors, logs: page.logs, unexpected: store.unexpected };
}

async function main() {
    const chosen = scenarios.filter((scenario) => !FILTER || (scenario.name.indexOf(FILTER) !== -1));

    if (!chosen.length) {
        stop('No scenario matches ' + JSON.stringify(FILTER) + '.');
    }

    const php = startPhp();
    let setup;

    try {
        setup = await php.setup;
    } catch (error) {
        stop(error.message);
    }

    const missing = SCREEN_BUNDLES.filter((bundle) => !setup.bundles[bundle] || (MIN && !fs.existsSync(path.join(PANEL, bundle))));

    if (missing.length) {
        stop('Not in pg_js_bundles() or not built: ' + missing.join(', '));
    }

    const server = await serve();
    const browser = new Chromium(findChromium());
    let product;

    try {
        product = await browser.version();
    } catch (error) {
        stop('Chromium did not answer: ' + error.message);
    }

    process.stdout.write('Flows canvas harness: ' + chosen.length + ' scenarios, ' + product + ', scripts ' + (MIN ? 'built (.min.js)' : 'from source') + '\n');

    const context = { php: php, setup: setup, server: server, browser: browser };
    let passed = 0;

    for (let i = 0; i < chosen.length; i++) {
        const scenario = chosen[i];
        const started = Date.now();
        const result = await runScenario(scenario, i + 1, context);
        const failed = result.checks.filter((check) => !check.pass);
        const pass = !failed.length && !result.stopped && !result.errors.length && result.checks.length > 0;

        passed += pass ? 1 : 0;
        process.stdout.write((pass ? 'PASS ' : 'FAIL ') + scenario.name + '  (' + result.checks.length + ' checks, ' + ((Date.now() - started) / 1000).toFixed(1) + ' s)\n');

        failed.forEach((check) => process.stdout.write('     ✗ ' + check.label + (check.detail ? ': ' + check.detail : '') + '\n'));

        if (result.stopped && !failed.some((check) => check.label === result.stopped)) {
            process.stdout.write('     stopped: ' + result.stopped + '\n');
        }

        result.errors.forEach((error) => process.stdout.write('     page error: ' + error + '\n'));

        if (VERBOSE) {
            result.checks.filter((check) => check.pass).forEach((check) => process.stdout.write('     ✓ ' + check.label + '\n'));
            result.logs.forEach((line) => process.stdout.write('     log: ' + line + '\n'));
            result.unexpected.forEach((action) => process.stdout.write('     not answered by the store: ' + action + '\n'));
        }
    }

    process.stdout.write(passed + ' of ' + chosen.length + ' scenarios passed\n');

    await browser.close();
    await server.close();
    php.close();

    process.exit((passed === chosen.length) ? 0 : 1);
}

main().catch((error) => {
    process.stderr.write((error && error.stack) || String(error));
    process.stderr.write('\n');
    process.exit(2);
});
