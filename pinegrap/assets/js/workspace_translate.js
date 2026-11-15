/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - Translate on a message: the browser's own translator
 * (the Translator and LanguageDetector APIs) puts a message drawn in a
 * conversation into the reader's panel language. Nothing leaves the device
 * and nothing is saved: the translation stands in the message's place for
 * this reader until "Show original" or a reload. A browser without both APIs
 * gets no menu item.
 *
 * Tags, links, code, e-mail addresses and pieces without letters are kept as
 * they are: pieces() cuts a text into pieces to translate and pieces to
 * keep, join() puts the translated ones back (pure, tests/
 * workspace_message_translate_test.php).
 *
 * assets/js/workspace.js offers the item (messageActions()) and calls
 * apply() again when it draws a message anew. Every text comes from the
 * #ws-config block.
 *
 * Served minified inside assets/js/workspace.min.js (pg_js_bundles(),
 * includes/fn/output.php); after editing run `php tools/build_js.php`.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // Kept as written: a tag (<@user:12>, <#order:5>), inline code, a web
    // address, an e-mail address.
    var KEEP = /<[@#][^<>\s]+>|`[^`\n]+`|\b(?:https?:\/\/|www\.)[^\s<>]+|[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/g;

    // A letter of any script: a piece without one (numbers, signs, spaces)
    // has nothing to translate.
    var LETTER = /[A-Za-zÀ-ɏͰ-ϿЀ-ӿ԰-֏֐-׿؀-ۿऀ-෿฀-໿ᄀ-ᇿ぀-ヿ㄰-㆏㐀-鿿가-힯]/;

    // Elements whose text is never translated: links and record chips,
    // code, the message's own small controls.
    var SKIP = 'a, code, pre, kbd, button, input, select, textarea, script, style, svg, .ws-chip, .ws-tag-card, .ws-code, .ws-msg-edited, .ws-check-who, .ws-check-task, [data-ws-ref], [translate="no"]';

    /**
     * A text cut into pieces: { text, keep }. The leading and trailing
     * spaces of a piece to translate are pieces of their own, kept, so the
     * translator never loses them.
     */
    function pieces(text) {
        var out = [];
        var source = String(text == null ? '' : text);
        var last = 0;
        var match;

        var add = function (part, keep) {
            if (part === '') {
                return;
            }

            if (keep || !LETTER.test(part)) {
                if (out.length && out[out.length - 1].keep) {
                    out[out.length - 1].text += part;
                } else {
                    out.push({ text: part, keep: true });
                }

                return;
            }

            var lead = part.match(/^\s*/)[0];
            var tail = part.match(/\s*$/)[0];

            add(lead, true);
            out.push({ text: part.slice(lead.length, part.length - tail.length), keep: false });
            add(tail, true);
        };

        KEEP.lastIndex = 0;

        while ((match = KEEP.exec(source)) !== null) {
            var found = match[0];

            // A web address ends before the sign that closes its sentence.
            if (/^(?:https?:|www\.)/.test(found)) {
                var trail = found.match(/[.,;:!?)\]]+$/);

                if (trail) {
                    found = found.slice(0, found.length - trail[0].length);
                }
            }

            add(source.slice(last, match.index), false);
            add(found, true);
            last = match.index + found.length;
            KEEP.lastIndex = last;
        }

        add(source.slice(last), false);

        return out;
    }

    // The pieces to translate, in order.
    function wanted(list) {
        return list.filter(function (piece) { return !piece.keep; }).map(function (piece) { return piece.text; });
    }

    /**
     * The text again, each piece to translate replaced by its translation
     * in order; a missing translation keeps the piece as it was.
     */
    function join(list, translated) {
        var i = 0;

        return list.map(function (piece) {
            if (piece.keep) {
                return piece.text;
            }

            var done = translated[i++];

            return (typeof done === 'string' && done !== '') ? done : piece.text;
        }).join('');
    }

    // The language part of a code: "tr", "en" from "en-US" or "en_GB".
    function language(code) {
        return String(code || '').toLowerCase().split(/[-_]/)[0];
    }

    function supported() {
        return !!(window.Promise && window.Translator && (typeof window.Translator.create === 'function')
            && (typeof window.Translator.availability === 'function')
            && window.LanguageDetector && (typeof window.LanguageDetector.create === 'function'));
    }

    // ── In the conversation ────────────────────────────────────────────

    // What this reader has translated, by message: the html it was made
    // from, the translated html, and whether the original is shown.
    var kept = {};
    var detector = null;
    var translators = {};

    function languageName(code, locale) {
        try {
            if (window.Intl && Intl.DisplayNames) {
                var name = new Intl.DisplayNames([locale || 'en'], { type: 'language' }).of(code);

                if (name) {
                    return name;
                }
            }
        } catch (error) {
            // An unknown code is shown as it is.
        }

        return code;
    }

    function getDetector() {
        if (!detector) {
            detector = Promise.resolve().then(function () { return window.LanguageDetector.create(); });
            detector.then(null, function () { detector = null; });
        }

        return detector;
    }

    function getTranslator(source, target, progress) {
        var key = source + '>' + target;

        if (!translators[key]) {
            translators[key] = Promise.resolve().then(function () {
                return window.Translator.create({
                    sourceLanguage: source,
                    targetLanguage: target,
                    monitor: function (monitor) {
                        monitor.addEventListener('downloadprogress', function (event) {
                            progress(Math.max(0, Math.min(100, Math.round((event.loaded || 0) * 100))));
                        });
                    }
                });
            });
            translators[key].then(null, function () { delete translators[key]; });
        }

        return translators[key];
    }

    // The text nodes of a body that are read, outside the kept elements.
    function textNodes(root) {
        var nodes = [];
        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
            acceptNode: function (node) {
                var parent = node.parentNode;

                if (!LETTER.test(node.nodeValue) || (parent && parent.closest && parent.closest(SKIP))) {
                    return NodeFilter.FILTER_REJECT;
                }

                return NodeFilter.FILTER_ACCEPT;
            }
        });

        while (walker.nextNode()) {
            nodes.push(walker.currentNode);
        }

        return nodes;
    }

    // The line under the message: what is going on, and the switch between
    // the translation and the original.
    function line(body, h) {
        var bar = body.parentNode ? body.parentNode.querySelector(':scope > .ws-msg-tr') : null;

        if (!bar) {
            bar = h.el('div', 'ws-msg-tr small text-body-secondary');
            bar.setAttribute('aria-live', 'polite');
            body.parentNode.insertBefore(bar, body.nextSibling);
        }

        return h.clear(bar);
    }

    // Shows what is kept for the message: the translation or the original.
    function show(message, body, h) {
        var entry = kept[message.id];

        if (!entry || entry.html !== message.html) {
            return false;
        }

        var bar = line(body, h);
        var view = body.parentNode.querySelector(':scope > .ws-msg-tr-body');

        if (!view) {
            view = h.el('div', 'ws-msg-body ws-msg-tr-body');
            body.parentNode.insertBefore(view, bar);
        }

        view.innerHTML = entry.translated;
        view.setAttribute('lang', entry.target);
        view.classList.toggle('d-none', entry.original);
        body.classList.toggle('d-none', !entry.original);

        if (!entry.original) {
            bar.appendChild(h.icon('bi-translate', 'me-1'));
            bar.appendChild(document.createTextNode(h.t('tr_mark', languageName(entry.source, entry.target)) + ' · '));
        }

        var toggle = h.el('button', 'btn btn-link btn-sm p-0 align-baseline ws-msg-tr-toggle', entry.original ? h.t('tr_show_translation') : h.t('tr_show_original'));
        toggle.type = 'button';
        toggle.addEventListener('click', function (event) {
            event.stopPropagation();
            entry.original = !entry.original;
            show(message, body, h);
        });
        bar.appendChild(toggle);

        return true;
    }

    // One short line under the message, gone with the next one.
    function say(body, h, text) {
        line(body, h).textContent = text;
    }

    /**
     * Translates a drawn message into the reader's panel language and shows
     * it in the message's place.
     */
    function translate(message, body, h) {
        if (!body || !body.parentNode) {
            return;
        }

        // Translated before: the translation again, without asking twice.
        if (kept[message.id] && (kept[message.id].html === message.html)) {
            kept[message.id].original = false;
            show(message, body, h);
            return;
        }

        var target = language(h.cfg.locale || document.documentElement.lang || 'en');
        var copy = body.cloneNode(true);
        var nodes = textNodes(copy);
        var text = nodes.map(function (node) { return node.nodeValue; }).join(' ').trim();

        if (text === '') {
            say(body, h, h.t('tr_nothing'));
            return;
        }

        say(body, h, h.t('tr_working'));

        getDetector().then(function (found) {
            return found.detect(text);
        }).then(function (results) {
            var best = (results || [])[0] || {};
            var source = language(best.detectedLanguage);

            if (!source || (source === 'und')) {
                say(body, h, h.t('tr_unknown'));
                return null;
            }

            if (source === target) {
                say(body, h, h.t('tr_same', languageName(target, target)));
                return null;
            }

            return Promise.resolve(window.Translator.availability({ sourceLanguage: source, targetLanguage: target })).then(function (state) {
                if (state === 'unavailable') {
                    say(body, h, h.t('tr_unavailable', languageName(source, target)));
                    return null;
                }

                if (state !== 'available') {
                    say(body, h, h.t('tr_preparing'));
                }

                return getTranslator(source, target, function (percent) {
                    say(body, h, h.t('tr_preparing_percent', percent));
                }).then(function (translator) {
                    return Promise.all(nodes.map(function (node) {
                        var list = pieces(node.nodeValue);

                        return Promise.all(wanted(list).map(function (part) {
                            return translator.translate(part);
                        })).then(function (done) {
                            node.nodeValue = join(list, done);
                        });
                    }));
                }).then(function () {
                    kept[message.id] = { html: message.html, translated: copy.innerHTML, source: source, target: target, original: false };
                    show(message, body, h);
                    return true;
                });
            });
        }).then(null, function () {
            say(body, h, h.t('tr_failed'));
        });
    }

    /**
     * A message drawn anew: its translation shown again when this reader
     * made one of the same text.
     */
    function apply(message, body, h) {
        if (message && kept[message.id] && body && body.parentNode) {
            show(message, body, h);
        }
    }

    var api = { pieces: pieces, join: join, wanted: wanted, language: language, supported: supported, translate: translate, apply: apply };

    if (typeof window !== 'undefined') {
        window.PGWsTranslate = api;
    }
})();
