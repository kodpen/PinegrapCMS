/**
 * Pinegrap - Enterprise Website Platform
 *
 * A summary of the record a page shows, made in the visitor's browser with
 * its built-in AI: the Summarizer API of Chrome and Edge 138 and newer, which
 * runs on the device, so nothing is sent anywhere. The file is added to a
 * page only when the page holds a button bound to the ai_summarize action
 * (pg_ai_summary_inject()).
 *
 * The server draws the button hidden (data-pg-ai-summarize); it is shown only
 * in a browser that can summarize the page's language. A click writes the
 * main points into the element bound to __ai_summary (data-pg-ai-summary)
 * nearest to the button, or into a box added below the button when the
 * design has none. The text summarized is the button's <article> (or <main>)
 * without its forms, buttons, navigation, asides, hidden parts and the AI
 * assistants' links.
 *
 * The summarizer writes a handful of languages. For a page in another one it
 * writes English and the browser's Translator API brings the points into the
 * page's language; when either is missing the button stays hidden. A model
 * the browser has yet to download is asked for while the click is still the
 * visitor's gesture, which the browser requires, and its progress is shown
 * in the box.
 *
 * Plain ES5 and no jQuery: it has to work whatever the design loads.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var labels = window.pgAiSummaryLabels || {};
    var BASE = { type: 'key-points', format: 'markdown', length: 'medium' };
    // Characters of the record sent in one call. A longer record is cut;
    // when the model still finds it too long, a third of it is tried once.
    var MAX_CHARS = 12000;
    var SKIP = 'script,style,noscript,template,svg,button,form,nav,aside,select,textarea,input,'
             + '[hidden],[aria-hidden="true"],.d-none,[data-pg-ai-summary],[data-pg-ai-summarize],a[data-pg-bind*="href:ai_"]';
    var BLOCK = /^(P|DIV|LI|H[1-6]|BR|SECTION|ARTICLE|BLOCKQUOTE|TR|UL|OL|PRE|FIGCAPTION|DD|DT|HEADER|FOOTER|TABLE)$/;

    function label(key) {
        return Object.prototype.hasOwnProperty.call(labels, key) ? String(labels[key]) : key;
    }

    function pageLanguage() {
        var lang = String(document.documentElement.getAttribute('lang') || '').toLowerCase().split(/[-_]/)[0];
        return /^[a-z]{2,3}$/.test(lang) ? lang : 'en';
    }

    function options(extra) {
        var out = {}, key;
        for (key in BASE) out[key] = BASE[key];
        for (key in extra) out[key] = extra[key];
        return out;
    }

    function usable(state) {
        return !!state && state !== 'unavailable' && state !== 'no';
    }

    // How this browser can summarize a page in `lang`: 'direct', 'bridge'
    // (in English, then translated) or '' when it cannot.
    function plan(lang) {
        if (!window.Summarizer || typeof window.Summarizer.availability !== 'function') {
            return Promise.resolve('');
        }
        var ask = function (extra) {
            return window.Summarizer.availability(options(extra)).then(usable, function () { return false; });
        };
        return ask({ expectedInputLanguages: [lang], outputLanguage: lang }).then(function (direct) {
            if (direct) return 'direct';
            if (lang === 'en' || !window.Translator || typeof window.Translator.availability !== 'function') return '';
            return Promise.all([
                ask({ outputLanguage: 'en' }),
                window.Translator.availability({ sourceLanguage: 'en', targetLanguage: lang }).then(usable, function () { return false; })
            ]).then(function (both) {
                return (both[0] && both[1]) ? 'bridge' : '';
            });
        }, function () { return ''; });
    }

    // The readable text of `root`, one line per block.
    function textOf(root) {
        var parts = [];
        var walk = function (node) {
            for (var child = node.firstChild; child; child = child.nextSibling) {
                if (child.nodeType === 3) {
                    parts.push(child.nodeValue);
                } else if (child.nodeType === 1 && !child.matches(SKIP)) {
                    var block = BLOCK.test(child.tagName);
                    if (block) parts.push('\n');
                    walk(child);
                    if (block) parts.push('\n');
                }
            }
        };
        walk(root);
        return parts.join('').replace(/[ \t ]+/g, ' ').replace(/\s*\n\s*/g, '\n').trim();
    }

    function sourceOf(button) {
        return button.closest('article') || button.closest('main') || document.body;
    }

    // The box nearest to the button, or one made for it below the button.
    function outputFor(button) {
        for (var node = button.parentNode; node && node.querySelector; node = node.parentNode) {
            var found = node.querySelector('[data-pg-ai-summary]');
            if (found) return found;
            if (node === document.body) break;
        }
        var box = document.createElement('div');
        box.className = 'mt-3';
        box.setAttribute('data-pg-ai-summary', '');
        box.setAttribute('aria-live', 'polite');
        button.parentNode.insertBefore(box, button.nextSibling);
        return box;
    }

    function reveal(el) {
        el.hidden = false;
        el.classList.remove('d-none');
    }

    function conceal(el) {
        el.hidden = true;
        el.classList.add('d-none');
    }

    function setStatus(box, text) {
        box.textContent = '';
        var line = document.createElement(/^(P|SPAN)$/.test(box.tagName) ? 'span' : 'p');
        line.className = 'mb-0 text-body-secondary';
        line.textContent = text;
        box.appendChild(line);
        reveal(box);
    }

    // The points of a Markdown list, without the list marks and emphasis.
    function points(markdown) {
        var out = [];
        String(markdown || '').split(/\n+/).forEach(function (line) {
            var text = line.replace(/^\s*(?:[*\-•]|\d+[.)])\s+/, '')
                .replace(/\*\*(.+?)\*\*/g, '$1').replace(/__(.+?)__/g, '$1').replace(/`/g, '').trim();
            if (text) out.push(text);
        });
        return out;
    }

    // A point as a sentence: a translated point can start in lower case.
    function sentence(text) {
        var first = text.charAt(0);
        try {
            first = first.toLocaleUpperCase(pageLanguage());
        } catch (e) {
            first = first.toUpperCase();
        }
        return first + text.slice(1);
    }

    function render(box, list) {
        list = list.map(sentence);
        box.textContent = '';
        var inline = /^(P|SPAN)$/.test(box.tagName);
        if (inline) {
            box.appendChild(document.createTextNode(list.join(' • ')));
        } else {
            var ul = document.createElement('ul');
            ul.className = 'mb-2';
            list.forEach(function (text) {
                var li = document.createElement('li');
                li.textContent = text;
                ul.appendChild(li);
            });
            box.appendChild(ul);
            var note = document.createElement('p');
            note.className = 'small text-body-secondary mb-0';
            note.textContent = label('Summarized on your device by the browser\'s built-in AI. It may contain mistakes.');
            box.appendChild(note);
        }
        reveal(box);
    }

    function release(model) {
        try {
            if (model && typeof model.destroy === 'function') model.destroy();
        } catch (e) { /* already gone */ }
    }

    function summarize(summarizer, text) {
        var input = text.slice(0, MAX_CHARS);
        return summarizer.summarize(input).then(null, function (error) {
            if (!error || error.name !== 'QuotaExceededError' || input.length < 3000) throw error;
            return summarizer.summarize(input.slice(0, Math.floor(input.length / 3)));
        });
    }

    function start(button, mode, lang) {
        var box = outputFor(button);
        var text = textOf(sourceOf(button));
        if (!text) return;

        button.setAttribute('aria-busy', 'true');
        if ('disabled' in button) button.disabled = true;
        setStatus(box, label('The browser is summarizing the post...'));

        var monitor = function (m) {
            m.addEventListener('downloadprogress', function (event) {
                var share = Math.max(0, Math.min(1, Number(event.loaded) || 0));
                setStatus(box, label('Downloading the AI model: {n}%').replace('{n}', String(Math.round(share * 100))));
            });
        };
        var summarizerOptions = options({ sharedContext: document.title, monitor: monitor });
        if (mode === 'direct') {
            summarizerOptions.expectedInputLanguages = [lang];
            summarizerOptions.outputLanguage = lang;
        } else {
            summarizerOptions.outputLanguage = 'en';
        }

        // Both models are asked for now, inside the click.
        var making = window.Summarizer.create(summarizerOptions);
        var translating = (mode === 'bridge')
            ? window.Translator.create({ sourceLanguage: 'en', targetLanguage: lang, monitor: monitor })
            : null;
        if (translating) translating.then(null, function () { /* reported below */ });

        making.then(function (summarizer) {
            // A download may have shown its progress until now.
            setStatus(box, label('The browser is summarizing the post...'));
            return summarize(summarizer, text).then(function (result) {
                release(summarizer);
                return result;
            }, function (error) {
                release(summarizer);
                throw error;
            });
        }).then(function (summary) {
            var list = points(summary);
            if (!translating) return list;
            return translating.then(function (translator) {
                return Promise.all(list.map(function (item) { return translator.translate(item); })).then(function (done) {
                    release(translator);
                    return done;
                });
            });
        }).then(function (list) {
            if (!list.length) throw new Error('empty');
            render(box, list);
            conceal(button);
        }, function () {
            setStatus(box, label('The summary could not be made in this browser.'));
            button.removeAttribute('aria-busy');
            if ('disabled' in button) button.disabled = false;
        });
    }

    function init() {
        var buttons = document.querySelectorAll('[data-pg-ai-summarize]');
        if (!buttons.length || !window.Promise || !Element.prototype.closest) return;
        var lang = pageLanguage();

        plan(lang).then(function (mode) {
            if (!mode) return;
            Array.prototype.forEach.call(buttons, function (button) {
                reveal(button);
                button.addEventListener('click', function (event) {
                    event.preventDefault();
                    if (button.getAttribute('aria-busy') === 'true') return;
                    start(button, mode, lang);
                });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
