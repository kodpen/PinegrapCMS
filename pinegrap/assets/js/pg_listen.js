/**
 * Pinegrap - Enterprise Website Platform
 *
 * The "Listen" block: reads the page's article aloud with the browser's own
 * voice (speechSynthesis). The block is markup only: a root marked
 * data-pg-listen and hidden, holding buttons marked data-pg-listen-play,
 * data-pg-listen-pause and data-pg-listen-stop, each with an element marked
 * data-pg-listen-text for its label. The server adds this file to a page
 * whose HTML holds the mark (pg_listen_inject(), with the labels in the
 * page's language as window.pgListenLabels); the editor's Preview loads it
 * from its twin row in _SD_PAGE_SCRIPTS.
 *
 * What is read: the text of the nearest article around the block, else of
 * the page's main, else nothing; never navigation, header, footer, aside,
 * forms and their controls, buttons, hidden parts, scripts or the block
 * itself. It is spoken a sentence at a time, at most 200 characters a piece
 * (a browser stops a long one by itself), each piece in its own language:
 * the page's <html lang> without a "-x-" tail, or a part's own lang.
 *
 * The root shows only where the browser speaks and lists a voice for the
 * page's language (also when the voices arrive late) and the page has
 * something to read: never a button that does nothing. One block speaks at
 * a time; leaving or hiding the page stops it. No request, no storage.
 *
 * Plain ES5 and no jQuery: it has to work whatever the design loads. The
 * pure parts are exported for the tests under Node (tests/listen_script_test.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (factory) {
    'use strict';
    var api = factory();
    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    } else if (typeof window !== 'undefined' && typeof document !== 'undefined') {
        var run = function () { api.start(window, document); };
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
        else run();
    }
})(function () {
    'use strict';

    // The longest piece handed to the voice.
    var MAX = 200;

    // Elements never read, with everything inside them.
    var SKIP = {
        NAV: 1, HEADER: 1, FOOTER: 1, ASIDE: 1, FORM: 1, INPUT: 1, SELECT: 1, TEXTAREA: 1,
        BUTTON: 1, LABEL: 1, OPTION: 1, SCRIPT: 1, STYLE: 1, NOSCRIPT: 1, TEMPLATE: 1,
        SVG: 1, CANVAS: 1, IFRAME: 1, OBJECT: 1, VIDEO: 1, AUDIO: 1, DIALOG: 1
    };
    // Elements inside a sentence; every other one starts a new piece.
    var INLINE = {
        A: 1, ABBR: 1, B: 1, BDI: 1, BDO: 1, CITE: 1, CODE: 1, DATA: 1, DFN: 1, EM: 1, I: 1,
        KBD: 1, MARK: 1, Q: 1, S: 1, SAMP: 1, SMALL: 1, SPAN: 1, STRONG: 1, SUB: 1, SUP: 1,
        TIME: 1, U: 1, VAR: 1, WBR: 1, BR: 1
    };
    // Marks that end a sentence when a space or the end follows them.
    var ENDS = '.!?…。！？';

    // A language tag without its private-use tail: "en-x-mtfrom-tr" is
    // "en"; "" for none or a tag that is only private use.
    function langOf(value) {
        var v = (value == null) ? '' : String(value).replace(/^\s+|\s+$/g, '');
        var at = v.toLowerCase().indexOf('x-');
        if (at === 0) return '';
        at = v.toLowerCase().indexOf('-x-');
        if (at !== -1) v = v.slice(0, at);
        // The language subtag in lower case, as the tag's own rules write it.
        return v.replace(/^[A-Za-z]+/, function (m) { return m.toLowerCase(); });
    }

    // A text cut into sentences, each at most MAX characters (cut at the
    // last space within them).
    function split(text) {
        var t = String(text).replace(/\s+/g, ' ').replace(/^ | $/g, '');
        var out = [];
        if (t === '') return out;
        var sentences = [];
        var from = 0;
        for (var i = 0; i < t.length; i++) {
            if (ENDS.indexOf(t.charAt(i)) === -1) continue;
            while (i + 1 < t.length && ENDS.indexOf(t.charAt(i + 1)) !== -1) i++;
            if (i + 1 === t.length || t.charAt(i + 1) === ' ') {
                sentences.push(t.slice(from, i + 1));
                from = i + 1;
            }
        }
        if (from < t.length) sentences.push(t.slice(from));
        sentences.forEach(function (s) {
            s = s.replace(/^ | $/g, '');
            while (s.length > MAX) {
                var cut = s.lastIndexOf(' ', MAX);
                if (cut < MAX / 2) cut = MAX;
                out.push(s.slice(0, cut).replace(/ $/, ''));
                s = s.slice(cut).replace(/^ /, '');
            }
            if (s !== '') out.push(s);
        });
        return out;
    }

    // Whether an element is left out by its markup.
    function markedOut(node) {
        if (!node.getAttribute) return false;
        return node.getAttribute('hidden') !== null
            || node.getAttribute('aria-hidden') === 'true'
            || node.getAttribute('data-pg-listen') !== null;
    }

    // The pieces of `scope` in reading order: [{ text, lang }]. `lang` is
    // the language the scope is in; `isHidden(el)` may leave out more (the
    // page's own styles). Pure: it reads only the nodes it is given.
    function pieces(scope, lang, isHidden) {
        var out = [];
        var cur = null;
        if (!scope) return out;
        var flush = function () {
            if (!cur) return;
            var piece = cur;
            cur = null;
            split(piece.text).forEach(function (t) { out.push({ text: t, lang: piece.lang }); });
        };
        var add = function (text, l) {
            if (cur && cur.lang === l) { cur.text += text; return; }
            if (/^\s*$/.test(text)) { if (cur) cur.text += ' '; return; }
            flush();
            cur = { text: text, lang: l };
        };
        var walk = function (node, l) {
            if (node.nodeType === 3) { add(node.nodeValue || '', l); return; }
            if (node.nodeType !== 1) return;
            var tag = String(node.tagName || '').toUpperCase();
            if (SKIP[tag] || markedOut(node) || (isHidden && isHidden(node))) {
                if (cur) cur.text += ' ';
                return;
            }
            if (tag === 'BR') { if (cur) cur.text += ' '; return; }
            var own = node.getAttribute ? node.getAttribute('lang') : null;
            if (own !== null && langOf(own) !== '') l = langOf(own);
            var block = !INLINE[tag];
            if (block) flush();
            for (var i = 0; i < node.childNodes.length; i++) walk(node.childNodes[i], l);
            if (block) flush();
        };
        walk(scope, langOf(lang));
        flush();
        return out;
    }

    // What a block reads: the nearest article around it, else the page's
    // main, else nothing (null).
    function scopeFor(block, main) {
        for (var n = block ? block.parentNode : null; n && n.nodeType === 1; n = n.parentNode) {
            if (String(n.tagName).toUpperCase() === 'ARTICLE') return n;
        }
        return main || null;
    }

    function primary(tag) {
        return String(tag || '').replace(/_/g, '-').toLowerCase().split('-')[0];
    }

    // The voice for a language: the same tag first, else the same language
    // in another region; null for none.
    function voiceFor(voices, lang) {
        var want = String(lang || '').replace(/_/g, '-').toLowerCase();
        if (want === '' || !voices) return null;
        var near = null;
        for (var i = 0; i < voices.length; i++) {
            var have = String(voices[i] && voices[i].lang || '').replace(/_/g, '-').toLowerCase();
            if (have === want) return voices[i];
            if (!near && have !== '' && primary(have) === primary(want)) near = voices[i];
        }
        return near;
    }

    function hasVoice(voices, lang) {
        return voiceFor(voices, lang) !== null;
    }

    // The block that speaks now; another one stops it.
    var active = null;

    // The controls of one block. env: synth, Utterance, voices() (the
    // browser's list), read() (the pieces, asked at each start), labels.
    function createPlayer(root, env) {
        var q = function (name) { return root.querySelector('[data-pg-listen-' + name + ']'); };
        var play = q('play');
        var pause = q('pause');
        var stop = q('stop');
        var labels = env.labels || {};
        var label = function (key) { return (typeof labels[key] === 'string' && labels[key] !== '') ? labels[key] : key; };
        var state = 'idle';
        var queue = [];
        var index = 0;
        var token = 0;
        var player = {};

        // The label goes into the button's text element; a button with no
        // text element is named by aria-label.
        var name = function (btn, text) {
            if (!btn) return;
            var t = btn.querySelector('[data-pg-listen-text]');
            if (t) t.textContent = text;
            else btn.setAttribute('aria-label', text);
        };
        var within = function (doc) {
            var a = doc && doc.activeElement;
            return !!a && root.contains(a);
        };
        var setState = function (s) {
            var doc = env.doc;
            var hadFocus = within(doc);
            state = s;
            if (play) play.hidden = (s !== 'idle');
            if (pause) {
                pause.hidden = (s === 'idle');
                pause.setAttribute('aria-pressed', s === 'paused' ? 'true' : 'false');
                name(pause, label(s === 'paused' ? 'Resume' : 'Pause'));
            }
            if (stop) stop.hidden = (s === 'idle');
            root.setAttribute('data-pg-listen-state', s);
            // The pressed button may have just hidden: the focus moves to the
            // one that stands in its place.
            if (hadFocus || s === 'speaking') {
                var to = (s === 'idle') ? play : pause;
                if (to && to.focus && (hadFocus || s === 'speaking')) to.focus();
            }
        };
        var finish = function () {
            token++;
            queue = [];
            if (active === player) active = null;
            setState('idle');
        };
        var next = function (t) {
            if (t !== token) return;
            if (index >= queue.length) { finish(); return; }
            var p = queue[index++];
            var u = new env.Utterance(p.text);
            u.lang = p.lang;
            var v = voiceFor(env.voices(), p.lang);
            if (v) u.voice = v;
            u.onend = function () { next(t); };
            u.onerror = function (e) {
                if (t !== token) return;
                if (e && (e.error === 'interrupted' || e.error === 'canceled')) return;
                next(t);
            };
            env.synth.speak(u);
        };

        player.start = function () {
            if (active && active !== player) active.stop();
            queue = env.read();
            index = 0;
            if (!queue.length) return;
            token++;
            env.synth.cancel();
            active = player;
            setState('speaking');
            next(token);
        };
        player.togglePause = function () {
            if (state === 'speaking') {
                env.synth.pause();
                setState('paused');
            } else if (state === 'paused') {
                env.synth.resume();
                setState('speaking');
            }
        };
        player.stop = function () {
            if (state === 'idle') return;
            token++;
            env.synth.cancel();
            finish();
        };
        player.show = function () {
            if (!root.hidden) return;
            name(play, label('Listen'));
            name(stop, label('Stop'));
            setState('idle');
            root.hidden = false;
        };

        if (play) play.addEventListener('click', function () { player.start(); });
        if (pause) pause.addEventListener('click', function () { player.togglePause(); });
        if (stop) stop.addEventListener('click', function () { player.stop(); });
        return player;
    }

    // Binds every block of the page; the roots stay hidden where nothing
    // can be read aloud.
    function start(win, doc) {
        var roots = doc.querySelectorAll('[data-pg-listen]');
        if (!roots || !roots.length) return;
        var synth = win.speechSynthesis;
        var Utterance = win.SpeechSynthesisUtterance;
        if (!synth || typeof Utterance !== 'function') return;
        var html = doc.documentElement;
        var pageLang = langOf(html && html.getAttribute ? html.getAttribute('lang') : '');
        if (pageLang === '') return;

        var voices = function () {
            try { return synth.getVoices() || []; } catch (e) { return []; }
        };
        // Left out by the page's own styles too.
        var styleHidden = (typeof win.getComputedStyle === 'function')
            ? function (node) {
                var cs = win.getComputedStyle(node);
                return !!cs && (cs.display === 'none' || cs.visibility === 'hidden');
            }
            : null;
        var main = doc.querySelector('main');

        var players = [];
        Array.prototype.forEach.call(roots, function (root) {
            var scope = scopeFor(root, main);
            if (!scope) return;
            var host = scope.closest ? scope.closest('[lang]') : null;
            var lang = host ? (langOf(host.getAttribute('lang')) || pageLang) : pageLang;
            players.push(createPlayer(root, {
                synth: synth,
                Utterance: Utterance,
                voices: voices,
                doc: doc,
                labels: win.pgListenLabels,
                read: function () { return pieces(scope, lang, styleHidden); }
            }));
        });
        if (!players.length) return;

        var reveal = function () {
            if (!hasVoice(voices(), pageLang)) return;
            players.forEach(function (p) { p.show(); });
        };
        reveal();
        if (typeof synth.addEventListener === 'function') synth.addEventListener('voiceschanged', reveal);
        else synth.onvoiceschanged = reveal;

        var stopAll = function () { players.forEach(function (p) { p.stop(); }); };
        win.addEventListener('pagehide', stopAll);
        doc.addEventListener('visibilitychange', function () {
            if (doc.visibilityState === 'hidden') stopAll();
        });
    }

    return {
        langOf: langOf,
        split: split,
        pieces: pieces,
        scopeFor: scopeFor,
        voiceFor: voiceFor,
        hasVoice: hasVoice,
        createPlayer: createPlayer,
        start: start
    };
});
