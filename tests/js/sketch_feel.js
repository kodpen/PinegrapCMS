/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Sketch tool's feel, measured in Chromium on a running sandbox
 * (tools/setup_sandbox.sh): signs in, opens a new design's visual editor,
 * puts a sketch page of a hundred boxes, turns Sketch on and either times
 * drags and a held arrow key under a CPU slowdown (perf) or drives touch,
 * pen and every key and prints PASS or FAIL lines (keys). Run by hand, not
 * in CI; the numbers and how to read them: docs/memory/designer/sketch-feel.md.
 *
 *   node tests/js/sketch_feel.js [perf|keys] [--theme=dark|light] [--cpu=4] [--shots=<dir>] [--base=http://127.0.0.1:8000]
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

'use strict';

const { chromium } = require('playwright');

const opt = { mode: 'perf', theme: 'dark', cpu: 4, shots: '', base: 'http://127.0.0.1:8000' };
process.argv.slice(2).forEach(a => {
    const m = /^--(\w+)=(.*)$/.exec(a);
    if (m) opt[m[1]] = m[1] === 'cpu' ? Number(m[2]) : m[2];
    else opt.mode = a;
});

// The guided tours would cover the editor: their automatic start is a no-op.
const NO_TOUR = `(() => { let engine; Object.defineProperty(window, 'PgTour', { configurable: true, get() { return engine; }, set(v) { if (v && typeof v === 'object') v.autoStart = function () {}; engine = v; } }); })();`;

// A hundred boxes in rows (perf: four columns; keys: three, the fourth
// left free to draw in).
function sketch(cols) {
    const boxes = [];
    for (let i = 0; i < 100; i++) {
        const col = i % cols;
        const row = Math.floor(i / cols);
        boxes.push({ id: 100 + i, x: 8 + col * 118, y: 8 + row * 64, w: 104, h: 56, kind: ['header', 'intro', 'features', 'cards', 'text', ''][i % 6], text: i % 3 ? 'Box ' + i : '' });
    }
    return { n: 1000, pages: [{ id: 1, name: 'Feel', x: 1200, y: 0, boxes }] };
}

// ── perf: frames and event times during drags ──
// Frame recorder: rAF deltas and long animation frames during a drag.
async function rec(page, on) {
    await page.evaluate(on => {
        if (on) {
            window.__f = []; window.__lt = [];
            let last = performance.now();
            const tick = t => { window.__f.push(t - last); last = t; if (window.__run) requestAnimationFrame(tick); };
            window.__run = true; requestAnimationFrame(tick);
            try { window.__po = new PerformanceObserver(l => l.getEntries().forEach(e => window.__lt.push(e.duration))); window.__po.observe({ type: 'long-animation-frame', buffered: false }); } catch (e) {}
        } else { window.__run = false; if (window.__po) window.__po.disconnect(); }
    }, on);
}
async function stats(page) {
    return page.evaluate(() => {
        const f = window.__f.slice(1).sort((a, b) => a - b);
        const sum = f.reduce((a, b) => a + b, 0);
        const p = q => f[Math.min(f.length - 1, Math.floor(q * f.length))];
        return { frames: f.length, fps: +(1000 * f.length / sum).toFixed(1), p50: +p(.5).toFixed(1), p95: +p(.95).toFixed(1), worst: +f[f.length - 1].toFixed(1), over20: f.filter(x => x > 20).length, loaf: window.__lt.length };
    });
}
let cdp = null;
async function metrics() {
    const m = {}; (await cdp.send('Performance.getMetrics')).metrics.forEach(x => { m[x.name] = x.value; }); return m;
}
async function drag(page, from, to, steps, opts) {
    if (!cdp) { cdp = await page.context().newCDPSession(page); await cdp.send('Performance.enable'); }
    const m0 = await metrics();
    await rec(page, true);
    await page.mouse.move(from.x, from.y);
    await page.mouse.down();
    for (let i = 1; i <= steps; i++) {
        await page.mouse.move(from.x + (to.x - from.x) * i / steps, from.y + (to.y - from.y) * i / steps);
        await page.evaluate(() => new Promise(r => requestAnimationFrame(() => r())));
    }
    await page.mouse.up();
    await page.waitForTimeout(100);
    await rec(page, false);
    await page.waitForTimeout(600);
    const m1 = await metrics();
    const st = await stats(page);
    ['ScriptDuration', 'RecalcStyleDuration', 'LayoutDuration', 'TaskDuration'].forEach(k => { st[k.replace('Duration', '')] = Math.round((m1[k] - m0[k]) * 1000); });
    st.layouts = m1.LayoutCount - m0.LayoutCount; st.styles = m1.RecalcStyleCount - m0.RecalcStyleCount;
    return st;
}
async function perf(page) {
    const res = {};
    const box = async sel => { const b = await (await page.$(sel)).boundingBox(); return b; };
    let b = await box('.sd-sketch-box[data-box="105"]');
    res.move = await drag(page, { x: b.x + b.width / 2, y: b.y + b.height - 6 }, { x: b.x + b.width / 2 + 120, y: b.y + b.height + 200 }, 60);
    b = await box('.sd-sketch-box[data-box="102"]');
    res.move2 = await drag(page, { x: b.x + b.width / 2, y: b.y + b.height - 6 }, { x: b.x + b.width / 2 - 100, y: b.y + b.height + 260 }, 60);
    b = await box('.sd-sketch-box[data-box="110"]');
    // the selected box: its se handle
    await page.mouse.click(b.x + b.width / 2, b.y + b.height - 6);
    await page.waitForTimeout(600);
    const h = await box('.sd-sketch-box[data-box="110"] .sd-sketch-handle-se');
    res.resize = await drag(page, { x: h.x + h.width / 2, y: h.y + h.height / 2 }, { x: h.x + 90, y: h.y + 140 }, 60);
    const body = await box('.sd-sketch-frame .sd-sketch-body');
    const y0 = body.y + body.height - 60;
    res.draw = await drag(page, { x: body.x + 20, y: Math.min(y0, 860) - 100 }, { x: body.x + 300, y: Math.min(y0, 860) }, 60);
    // Held arrow key: 30 repeats on the selected box, time in the key handler.
    b = await box('.sd-sketch-box[data-box="111"]');
    await page.mouse.click(b.x + b.width / 2, b.y + b.height - 6);
    await page.waitForTimeout(600);
    await page.evaluate(() => { window.__kd = []; let t0 = 0; window.addEventListener('keydown', () => { t0 = performance.now(); }, true); window.addEventListener('keydown', () => { window.__kd.push(performance.now() - t0); }, false); });
    await rec(page, true);
    for (let i = 0; i < 30; i++) { await page.keyboard.press('ArrowDown'); }
    await page.waitForTimeout(100);
    await rec(page, false);
    res.keys = await stats(page);
    res.keys.handler = await page.evaluate(() => { const a = window.__kd.slice().sort((x, y) => x - y); return { n: a.length, med: +a[a.length >> 1].toFixed(1), worst: +a[a.length - 1].toFixed(1) }; });
    return res;
}

// ── keys: touch, pen and every key ──
// Touch, pen and keys on the sketch; returns pass/fail lines.
async function keysTouch(page) {
    const cdp = await page.context().newCDPSession(page);
    await cdp.send('Emulation.setCPUThrottlingRate', { rate: 1 });
    // Zoom in to about 100% over the top of the frame (Ctrl+wheel).
    const fr = await (await page.$('.sd-sketch-frame')).boundingBox();
    await page.mouse.move(fr.x + fr.width / 2, fr.y + 40);
    await page.keyboard.down('Control');
    for (let i = 0; i < 7; i++) { await page.mouse.wheel(0, -100); await page.waitForTimeout(30); }
    await page.keyboard.up('Control');
    await page.waitForTimeout(400);
    if (opt.shots) await page.screenshot({ path: opt.shots + '/sketch-' + opt.theme + '.png' });
    const out = [];
    const ok = (name, cond, extra) => out.push((cond ? 'PASS ' : 'FAIL ') + name + (extra ? ' ' + JSON.stringify(extra) : ''));
    const data = () => page.evaluate(() => JSON.parse(localStorage.getItem('pg_proto_sketch_new')).pages[0]);
    const boxOf = async id => (await data()).boxes.find(b => b.id === id);
    const rect = async sel => { const e = await page.$(sel); if (!e) { out.push('FAIL missing ' + sel); const err = new Error('missing ' + sel); err.lines = out; throw err; } return e.boundingBox(); };
    const rest = () => page.waitForTimeout(500);
    // Touch drag of a box body.
    async function touchDrag(from, to) {
        await cdp.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: from.x, y: from.y }] });
        for (let i = 1; i <= 12; i++) await cdp.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: from.x + (to.x - from.x) * i / 12, y: from.y + (to.y - from.y) * i / 12 }] });
        await cdp.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
    }
    async function penDrag(from, to) {
        const ev = (type, p, extra) => cdp.send('Input.dispatchMouseEvent', Object.assign({ type, x: p.x, y: p.y, pointerType: 'pen', button: 'left', buttons: type === 'mouseReleased' ? 0 : 1, clickCount: 1, force: .5 }, extra || {}));
        await ev('mousePressed', from);
        for (let i = 1; i <= 12; i++) await ev('mouseMoved', { x: from.x + (to.x - from.x) * i / 12, y: from.y + (to.y - from.y) * i / 12 });
        await ev('mouseReleased', to);
    }
    let b0 = await boxOf(113), r = await rect('.sd-sketch-box[data-box="113"]');
    await touchDrag({ x: r.x + r.width / 2, y: r.y + r.height - 8 }, { x: r.x + r.width / 2 + 40, y: r.y + r.height - 8 + 80 });
    await rest();
    let b1 = await boxOf(113);
    ok('touch moves a box', b1.x !== b0.x && b1.y > b0.y, { from: [b0.x, b0.y], to: [b1.x, b1.y] });
    const sel = await page.evaluate(() => { const e = document.querySelector('.sd-sketch-on'); return e && e.getAttribute('data-box'); });
    ok('touch selects the box', sel === '113');
    // Touch resize by the se handle.
    r = await rect('.sd-sketch-box[data-box="113"] .sd-sketch-handle-se');
    ok('handle hit area', true, { w: r.width, h: r.height });
    b0 = b1;
    await touchDrag({ x: r.x + r.width / 2, y: r.y + r.height / 2 }, { x: r.x + 40, y: r.y + 48 });
    await rest();
    b1 = await boxOf(113);
    ok('touch resizes a box', b1.w > b0.w && b1.h > b0.h, { from: [b0.w, b0.h], to: [b1.w, b1.h] });
    // A finger lands 8px inside the corner, not on the handle.
    r = await rect('.sd-sketch-box[data-box="113"] .sd-sketch-handle-se');
    b0 = b1;
    await touchDrag({ x: r.x + r.width / 2 - 8, y: r.y + r.height / 2 - 8 }, { x: r.x - 8 + 40, y: r.y - 8 + 40 });
    await rest();
    b1 = await boxOf(113);
    ok('touch near the corner resizes', b1.w > b0.w && b1.h > b0.h && b1.x === b0.x, { from: [b0.x, b0.w, b0.h], to: [b1.x, b1.w, b1.h] });
    // Touch in the middle still moves.
    r = await rect('.sd-sketch-box[data-box="113"]');
    b0 = b1;
    await touchDrag({ x: r.x + r.width / 2, y: r.y + r.height / 2 + 10 }, { x: r.x + r.width / 2 + 40, y: r.y + r.height / 2 + 10 });
    await rest();
    b1 = await boxOf(113);
    ok('touch in the middle moves', b1.x > b0.x && b1.w === b0.w, { from: [b0.x, b0.w], to: [b1.x, b1.w] });
    // Pen on the handle resizes.
    r = await rect('.sd-sketch-box[data-box="113"] .sd-sketch-handle-e');
    b0 = b1;
    await penDrag({ x: r.x + r.width / 2, y: r.y + r.height / 2 }, { x: r.x + 40, y: r.y + r.height / 2 });
    await rest();
    b1 = await boxOf(113);
    ok('pen resizes by a handle', b1.w > b0.w && b1.h === b0.h, { from: [b0.w], to: [b1.w] });
    // Pen draws a box in the empty area under the boxes.
    const n0 = (await data()).boxes.length;
    const body = await rect('.sd-sketch-frame .sd-sketch-body');
    const k = body.width / 480;
    const yy = Math.max(body.y, 200) + 100;
    await penDrag({ x: body.x + 370 * k, y: yy }, { x: body.x + 470 * k, y: yy + 80 * k });
    await rest();
    const d1 = await data();
    ok('pen draws a box', d1.boxes.length === n0 + 1, { n0, n1: d1.boxes.length });
    const drawn = d1.boxes[d1.boxes.length - 1];
    const focusedDrawn = await page.evaluate(id => document.activeElement && document.activeElement.getAttribute('data-box') === String(id), drawn.id);
    ok('drawn box selected and focused', focusedDrawn);
    // Pen moves it.
    r = await rect('.sd-sketch-box[data-box="' + drawn.id + '"]');
    await penDrag({ x: r.x + r.width / 2, y: r.y + r.height - 8 }, { x: r.x + r.width / 2 + 48, y: r.y + r.height - 8 });
    await rest();
    ok('pen moves a box', (await boxOf(drawn.id)).x > drawn.x);
    // Keys on the selected box.
    const key = async (k, wait) => { await page.keyboard.press(k); await page.waitForTimeout(wait || 80); };
    let b = await boxOf(drawn.id);
    await key('ArrowRight'); await key('ArrowRight'); await key('Shift+ArrowDown');
    let el = await rect('.sd-sketch-box[data-box="' + drawn.id + '"]');
    let a = await boxOf(drawn.id);
    ok('arrows move 8 / Shift 32', a.x === Math.min(480 - b.w, b.x + 16) && a.y === b.y + 32, { from: [b.x, b.y], to: [a.x, a.y] });
    ok('arrow keeps the focus', await page.evaluate(id => document.activeElement && document.activeElement.getAttribute('data-box') === String(id), drawn.id));
    await rest();
    el = await page.evaluate(id => { const e = document.querySelector('.sd-sketch-box[data-box="' + id + '"]'); return [parseFloat(e.style.left), parseFloat(e.style.top)]; }, drawn.id);
    ok('drawn where kept after the rest', el[0] === a.x && el[1] === a.y, el);
    ok('focus kept after the rest', await page.evaluate(id => document.activeElement && document.activeElement.getAttribute('data-box') === String(id), drawn.id));
    // Held arrow key: 20 repeats.
    const t0 = Date.now();
    for (let i = 0; i < 20; i++) await page.keyboard.press('ArrowDown');
    ok('held arrow (20 presses) ms', true, { ms: Date.now() - t0 });
    await rest();
    a = await boxOf(drawn.id);
    await key('Control+z', 200);
    let u = await boxOf(drawn.id);
    ok('Ctrl+Z undoes the folded nudges at once', u.y < a.y, { before: a.y, after: u.y });
    await key('Control+y', 200);
    ok('Ctrl+Y redoes', (await boxOf(drawn.id)).y === a.y);
    // A letter starts the heading.
    await key('h'); await page.keyboard.type('ello'); await key('Enter', 200);
    ok('letter types the heading', (await boxOf(drawn.id)).text === 'hello');
    await key('F2'); await page.keyboard.type(' world'); await key('Escape', 200);
    ok('F2 + Esc keeps it', (await boxOf(drawn.id)).text === 'hello');
    await page.focus('.sd-sketch-box[data-box="' + drawn.id + '"]');
    await key('Enter'); await page.keyboard.press('End'); await page.keyboard.type('!'); await key('Enter', 200);
    ok('Enter edits', (await boxOf(drawn.id)).text === 'hello!');
    await page.focus('.sd-sketch-box[data-box="' + drawn.id + '"]');
    await key('Escape', 200);
    ok('Esc selects the frame', await page.evaluate(() => !document.querySelector('.sd-sketch-on') && document.activeElement.classList.contains('sd-sketch-frame')));
    await page.focus('.sd-sketch-box[data-box="' + drawn.id + '"]');
    await key('Delete', 200);
    ok('Delete takes it out', !(await boxOf(drawn.id)));
    await key('Control+z', 200);
    ok('Ctrl+Z brings it back', !!(await boxOf(drawn.id)));
    await page.focus('.sd-sketch-box[data-box="' + drawn.id + '"]');
    await key('Backspace', 200);
    ok('Backspace takes it out', !(await boxOf(drawn.id)));
    // Tab reaches a box and its chip; space does not type.
    await page.focus('.sd-sketch-box[data-box="100"]');
    await key('Tab');
    ok('Tab goes to the chip', await page.evaluate(() => document.activeElement.classList.contains('sd-sketch-chip')));
    await key('Enter', 200);
    ok('Enter on chip opens its menu', await page.evaluate(() => !!document.querySelector('.sd-proto-menu:not([hidden])')));
    await key('Escape', 200);
    // Pinch with two fingers does not move a box.
    b0 = await boxOf(101); r = await rect('.sd-sketch-box[data-box="101"]');
    const cx = r.x + r.width / 2, cy = r.y + r.height - 8;
    await cdp.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: cx, y: cy, id: 1 }] });
    await cdp.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: cx + 10, y: cy + 10, id: 1 }] });
    await cdp.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: cx + 10, y: cy + 10, id: 1 }, { x: cx + 100, y: cy + 100, id: 2 }] });
    await cdp.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: cx - 20, y: cy - 20, id: 1 }, { x: cx + 160, y: cy + 160, id: 2 }] });
    await cdp.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
    await rest();
    b1 = await boxOf(101);
    ok('pinch leaves the box where it was', b1.x === b0.x && b1.y === b0.y, { from: [b0.x, b0.y], to: [b1.x, b1.y] });
    return out;
}

(async () => {
    const keys = opt.mode === 'keys';
    const browser = await chromium.launch();
    const ctx = await browser.newContext({ hasTouch: keys, viewport: { width: 1440, height: 900 }, colorScheme: opt.theme });
    await ctx.addInitScript(`try { localStorage.setItem('pinegrap backend color scheme', ${JSON.stringify(opt.theme)}); localStorage.setItem('pg_proto_sketch_new', ${JSON.stringify(JSON.stringify(sketch(keys ? 3 : 4)))}); localStorage.setItem('pg_sketch_build_seen', '1'); } catch (e) {}`);
    await ctx.addInitScript(NO_TOUR);
    const page = await ctx.newPage();
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.goto(opt.base + '/pinegrap/', { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="email"]', 'admin');
    await page.fill('input[name="password"]', 'sandbox-admin');
    await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.click('input[type="submit"]')]);
    await page.goto(opt.base + '/pinegrap/add_system_style.php', { waitUntil: 'load' });
    await page.waitForTimeout(3000);
    await page.click('#sd-vb-proto');
    await page.waitForSelector('.sd-sketch-frame');
    await page.click('[aria-checked]:has(.bi-pencil-square)');
    await page.waitForTimeout(500);
    // Enter on the frame zooms to it (the whole page of boxes on show).
    await page.focus('.sd-sketch-frame');
    await page.keyboard.press('Enter');
    await page.waitForTimeout(800);
    if (keys) {
        let lines;
        try {
            lines = await keysTouch(page);
        } catch (e) {
            lines = (e.lines || []).concat(['FAIL ' + e.message]);
        }
        if (errors.length) lines.push('FAIL page errors ' + JSON.stringify(errors));
        console.log(lines.join('\n'));
        await browser.close();
        process.exit(lines.some(l => /^FAIL/.test(l)) ? 1 : 0);
    }
    const cdp = await ctx.newCDPSession(page);
    await cdp.send('Emulation.setCPUThrottlingRate', { rate: opt.cpu });
    // The time each pointer event spends in script, from the window's
    // capturing listener to its bubbling one.
    await page.evaluate(() => {
        window.__ev = { pointerdown: [], pointermove: [], pointerup: [] };
        let t0 = 0;
        Object.keys(window.__ev).forEach(type => {
            window.addEventListener(type, () => { t0 = performance.now(); }, true);
            window.addEventListener(type, () => { window.__ev[type].push(performance.now() - t0); }, false);
        });
    });
    const results = await perf(page);
    results.events = await page.evaluate(() => {
        const o = {};
        Object.keys(window.__ev).forEach(k => { const a = window.__ev[k].map(x => +x.toFixed(1)); const s = a.slice().sort((x, y) => x - y); o[k] = { n: a.length, med: s[s.length >> 1], worst: s[s.length - 1], each: k === 'pointermove' ? undefined : a }; });
        return o;
    });
    console.log(JSON.stringify(results, null, 1));
    await browser.close();
})().catch(e => { console.error(e.message); process.exit(2); });
