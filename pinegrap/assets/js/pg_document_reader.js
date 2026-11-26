/**
 * Pinegrap - Enterprise Website Platform
 *
 * The document reader of the visual designer (window.PGDocumentReader):
 * the paragraphs of a pasted text or a document in, with their styles, the
 * page's sections out, in the document's order, each with its kind and an
 * unsure mark where the rules cannot decide. Pure: it reads what it is
 * handed and changes nothing; the words go out as they came in.
 *
 * - read(paragraphs): each paragraph {text, style, level, lead, rows, src,
 *   alt}. `style` is a heading ('heading', 'h2', 'Heading 2', 'Title'), a
 *   list item ('item', 'li', 'List Paragraph'), a quote ('quote',
 *   'blockquote'), a table ('table', with `rows` as arrays of cell texts),
 *   a picture ('picture', with `src`), anything else a paragraph. `lead` is
 *   the bold words a paragraph starts with. Answers {sections, skipped,
 *   cut}.
 * - paragraphs(text): a pasted plain or Markdown text to such paragraphs
 *   (# headings, - and 1. items, > quotes, | tables |, ![pictures](src),
 *   **bold** leads).
 * - The rules: a heading opens a section; the first heading with its lead
 *   is the intro; short items led by a few bold words, or short
 *   subheadings with their text, are cards; a question followed by its
 *   answer, repeated, is a list of questions; lines of a number and a
 *   label are figures; a quotation with a name is a testimonial; a table
 *   is a table, or price columns when it holds prices; an e-mail, a phone
 *   number or an address is the contact section; pictures alone are a
 *   picture or a gallery; the rest is text.
 * - A section the rules cannot decide is never guessed in silence: it
 *   carries unsure: true, the reason ('mixed' two readings fit, 'words'
 *   the heading names another kind), the best guess as its kind and the
 *   kinds to ask about as options.
 *
 * Nothing runs at load but the definition of window.PGDocumentReader.
 * tests/document_reader_test.php runs it in Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // The kinds a section can be read as, in the kind list's order.
    var KINDS = ['intro', 'text', 'cards', 'questions', 'figures', 'testimonial', 'table', 'prices', 'contact', 'picture', 'gallery'];

    // Heading words that name a kind, folded (see fold()). A word of four
    // letters or more also matches a longer word it starts.
    var WORDS = {
        cards: ['services', 'features', 'benefits', 'what we do', 'why us', 'hizmetler', 'hizmetlerimiz', 'ozellikler', 'avantajlar', 'neler yapiyoruz'],
        questions: ['faq', 'faqs', 'questions', 'frequently asked', 'sss', 'sorular', 'sikca sorulan', 'sik sorulan'],
        figures: ['numbers', 'figures', 'statistics', 'stats', 'in numbers', 'rakamlar', 'rakamlarla', 'sayilarla', 'istatistik'],
        testimonial: ['testimonials', 'testimonial', 'reviews', 'what our clients say', 'what customers say', 'yorumlar', 'musteri yorumlari', 'referanslar'],
        prices: ['pricing', 'prices', 'price list', 'plans', 'fiyatlar', 'fiyat listesi', 'paketler', 'ucretler'],
        contact: ['contact', 'contact us', 'get in touch', 'reach us', 'iletisim', 'bize ulasin', 'ulasin', 'adres'],
        gallery: ['gallery', 'portfolio', 'galeri', 'portfoy']
    };

    var MAX_PARAGRAPHS = 2000;  // a longer document is read up to here
    var LEAD_WORDS = 8;         // a card's bold lead has at most this many words
    var CARD_WORDS = 80;        // and its text at most this many
    var LABEL_WORDS = 6;        // a figure's label has at most this many words
    var LONG_WORDS = 300;       // a text section longer than this is marked long
    var INTRO_LEAD = 2;         // the intro takes this many paragraphs after its heading

    var CURRENCY = '[$€£₺¥]';
    var PRICE = new RegExp('(?:' + CURRENCY + '\\s?\\d|\\d[\\d.,]*\\s?(?:' + CURRENCY + '|tl|usd|eur|gbp|try)(?![a-z]))', 'i');
    var NUMBER = '(?:' + CURRENCY + '\\s?)?\\d[\\d.,]*\\s?(?:%|\\+|k|m|bn|x)?\\+?';
    var FIGURE_FIRST = new RegExp('^(' + NUMBER + ')\\s+(\\S.*)$', 'i');
    var FIGURE_LAST = new RegExp('^(\\S.*?)\\s*[:–—-]?\\s+(' + NUMBER + ')$', 'i');
    var EMAIL = /[^\s@<>(),;:"']+@[^\s@<>(),;:"']+\.[a-z]{2,}/i;
    var PHONE = /(?:\+|\b)\d[\d\s().\/-]{5,}\d/;
    var ADDRESS = /(?:\b(?:street|st\.|avenue|ave\.?|road|rd\.|boulevard|blvd|lane|suite|floor|po box|mahallesi|mah\.|caddesi|cad\.|cd\.|sokak|sokagi|sok\.|sk\.|bulvari|blv\.|kat|daire)(?:\s|$|,)|\bno\s?:\s?\d)/i;
    var QUOTE_MARKS = /^["“«„'‘]/;
    var NAME_DASH = /^\s*(?:—|–|-|~)\s*(\S.*)$/;
    var NAME_DASH_TAIL = /\s+(?:—|–|~)\s*[^"”»]+$/;  // a name after the quote, on its line

    function str(value) {
        return (typeof value === 'string') ? value : '';
    }

    function trim(text) {
        return str(text).replace(/^\s+|\s+$/g, '');
    }

    function words(text) {
        var t = trim(text);

        return t === '' ? 0 : t.split(/\s+/).length;
    }

    // Lower case, no accents, Turkish dotted and dotless i as i, single
    // spaces, anything but letters and digits a space.
    function fold(text) {
        var out = str(text).replace(/[İIı]/g, 'i').toLowerCase();

        if (typeof out.normalize === 'function') {
            out = out.normalize('NFD');
        }

        return out.replace(/[̀-ͯ]/g, '')
            .replace(/[^a-z0-9]+/g, ' ')
            .replace(/^ +| +$/g, '');
    }

    // The kinds a heading names, in KINDS order.
    function wordKinds(text) {
        var tokens = fold(text).split(' '), found = [];

        if (tokens.length === 1 && tokens[0] === '') {
            return found;
        }

        KINDS.forEach(function (kind) {
            var hit = (WORDS[kind] || []).some(function (entry) {
                var parts = entry.split(' ');

                for (var i = 0; i + parts.length <= tokens.length; i++) {
                    var all = true;

                    for (var j = 0; j < parts.length && all; j++) {
                        var token = tokens[i + j];
                        all = (token === parts[j]) || (j === parts.length - 1 && parts[j].length >= 4 && token.indexOf(parts[j]) === 0);
                    }

                    if (all) {
                        return true;
                    }
                }

                return false;
            });

            if (hit) {
                found.push(kind);
            }
        });

        return found;
    }

    // The paragraph's type from its style name.
    function typeOf(paragraph) {
        var style = fold(paragraph.style).replace(/ /g, '');

        if (/^(heading|h[1-6]|heading[1-6]|title|subtitle)$/.test(style)) {
            return 'heading';
        }

        if (/^(item|li|list|listparagraph|listbullet\d?|listnumber\d?|bullet|numbered)$/.test(style)) {
            return 'item';
        }

        if (/^(quote|blockquote|intensequote)$/.test(style)) {
            return 'quote';
        }

        if (style === 'table' || Array.isArray(paragraph.rows)) {
            return 'table';
        }

        if (/^(picture|image|img|figure)$/.test(style) || (trim(paragraph.src) !== '' && trim(paragraph.text) === '')) {
            return 'picture';
        }

        return 'paragraph';
    }

    function levelOf(paragraph) {
        var style = fold(paragraph.style).replace(/ /g, ''), m = /([1-6])$/.exec(style);

        if (typeof paragraph.level === 'number' && paragraph.level >= 1 && paragraph.level <= 6) {
            return Math.floor(paragraph.level);
        }

        if (style === 'title') {
            return 1;
        }

        if (style === 'subtitle') {
            return 2;
        }

        return m ? parseInt(m[1], 10) : 2;
    }

    // The paragraphs as entries {i, type, level, text, lead, rest, rows,
    // src, alt}; empty ones go to `skipped` by their index.
    function prepare(paragraphs, skipped) {
        var list = [];

        (Array.isArray(paragraphs) ? paragraphs : []).forEach(function (raw, i) {
            if (i >= MAX_PARAGRAPHS) {
                return;
            }

            var p = (raw && typeof raw === 'object') ? raw : {text: str(raw)};
            var type = typeOf(p);
            var entry = {i: i, type: type, level: type === 'heading' ? levelOf(p) : 0, text: trim(p.text), lead: '', rest: ''};

            if (type === 'table') {
                entry.rows = (Array.isArray(p.rows) ? p.rows : []).filter(Array.isArray).map(function (row) {
                    return row.map(function (cell) {
                        return trim(typeof cell === 'number' ? String(cell) : cell);
                    });
                }).filter(function (row) {
                    return row.some(function (cell) {
                        return cell !== '';
                    });
                });

                if (!entry.rows.length) {
                    skipped.push(i);
                    return;
                }
            } else if (type === 'picture') {
                entry.src = trim(p.src);
                entry.alt = trim(p.alt) || entry.text;

                if (entry.src === '') {
                    skipped.push(i);
                    return;
                }
            } else if (entry.text === '') {
                skipped.push(i);
                return;
            }

            var lead = trim(p.lead);

            if (lead !== '' && entry.text.indexOf(lead) === 0 && entry.text.length > lead.length) {
                entry.lead = lead.replace(/\s*[:.–—-]\s*$/, '');
                entry.rest = entry.text.slice(lead.length).replace(/^\s*[:.–—-]?\s*/, '');
            }

            list.push(entry);
        });

        return list;
    }

    // The paragraphs into groups: a heading of the section level
    // (the highest found after the first heading) opens one; what comes
    // before the first heading is a group of its own.
    function groupsOf(entries) {
        var headings = entries.filter(function (e) {
            return e.type === 'heading';
        });
        var level = 6, groups = [], current = null;

        headings.slice(1).forEach(function (h) {
            level = Math.min(level, h.level);
        });

        if (headings.length) {
            level = Math.max(level, headings[0].level);
        }

        entries.forEach(function (e) {
            var opens = e.type === 'heading' && (e === headings[0] || e.level <= level);

            if (opens || !current) {
                current = {heading: opens ? e : null, body: []};
                groups.push(current);

                if (opens) {
                    return;
                }
            }

            current.body.push(e);
        });

        return {groups: groups, first: headings[0] || null};
    }

    function isQuestion(e) {
        return (e.type === 'paragraph' || e.type === 'heading' || e.type === 'item') && /\?\s*$/.test(e.text);
    }

    function figureOf(e) {
        if (e.type !== 'paragraph' && e.type !== 'item') {
            return null;
        }

        var text = e.text.replace(/[.;]\s*$/, ''), m = FIGURE_FIRST.exec(text), value, label;

        if (m) {
            value = m[1];
            label = m[2];
        } else if ((m = FIGURE_LAST.exec(text))) {
            value = m[2];
            label = m[1];
        } else {
            return null;
        }

        if (words(label) > LABEL_WORDS || /\?/.test(label) || value.replace(/\D/g, '').length > 6) {
            return null;
        }

        return {value: trim(value), label: trim(label)};
    }

    // The contact lines of a group: {type: 'email'|'phone'|'address', value}.
    // An address line is one item with all its words; with `every` its
    // e-mail and phone are listed too (to weigh the reading, not to show).
    function contactsOf(body, every) {
        var found = [];

        body.forEach(function (e) {
            if (e.type !== 'paragraph' && e.type !== 'item') {
                return;
            }

            var address = ADDRESS.test(e.text) && words(e.text) <= 25;

            if (address && !every) {
                found.push({type: 'address', value: e.text, i: e.i});
                return;
            }

            var m = EMAIL.exec(e.text);

            if (m) {
                found.push({type: 'email', value: m[0], i: e.i});
            }

            m = PHONE.exec(e.text.replace(EMAIL, ''));

            if (m && m[0].replace(/\D/g, '').length >= 7) {
                found.push({type: 'phone', value: trim(m[0]), i: e.i});
            }

            if (address) {
                found.push({type: 'address', value: e.text, i: e.i});
            }
        });

        return found;
    }

    function isQuote(e) {
        return e.type === 'quote' || (e.type === 'paragraph' && QUOTE_MARKS.test(e.text) && /["”»“'’]\s*$/.test(e.text.replace(NAME_DASH_TAIL, '')));
    }


    // The testimonials of a group: a quote and the name that follows it,
    // on the next line after a dash or as a short line, or after a dash at
    // the quote's own end.
    function testimonialsOf(body) {
        var out = [];

        for (var k = 0; k < body.length; k++) {
            var e = body[k];

            if (!isQuote(e)) {
                continue;
            }

            var quote = e.text, name = '', tail = NAME_DASH_TAIL.exec(quote), next = body[k + 1];

            if (tail) {
                name = trim(tail[0]).replace(/^(?:—|–|~)\s*/, '');
                quote = trim(quote.slice(0, tail.index));
            } else if (next && !isQuote(next) && (next.type === 'paragraph' || next.type === 'quote')) {
                var dash = NAME_DASH.exec(next.text);

                if (dash || (words(next.text) <= 8 && !/[.!?]$/.test(next.text))) {
                    name = trim(dash ? dash[1] : next.text);
                    k++;
                }
            }

            var role = '', comma = name.indexOf(',');

            if (comma > 0) {
                role = trim(name.slice(comma + 1));
                name = trim(name.slice(0, comma));
            }

            out.push({quote: quote, name: name, role: role, i: e.i});
        }

        return out;
    }

    // The question and answer pairs of a group.
    function questionsOf(body) {
        var out = [], current = null;

        body.forEach(function (e) {
            if (isQuestion(e)) {
                current = {question: e.text, answer: '', i: e.i};
                out.push(current);
            } else if (current && (e.type === 'paragraph' || e.type === 'item')) {
                current.answer = current.answer === '' ? e.text : current.answer + '\n' + e.text;
            }
        });

        return out.filter(function (q) {
            return q.answer !== '';
        });
    }

    // The cards of a group: items or paragraphs led by bold words, or short
    // subheadings each with its text.
    function cardsOf(body) {
        var out = [], current = null;

        body.forEach(function (e) {
            if (e.type === 'heading' && words(e.text) <= LEAD_WORDS) {
                current = {title: e.text, text: '', i: e.i, sub: true};
                out.push(current);
            } else if (current && current.sub && (e.type === 'paragraph' || e.type === 'item')) {
                current.text = current.text === '' ? e.text : current.text + '\n' + e.text;
            } else if (e.lead !== '' && words(e.lead) <= LEAD_WORDS && (e.type === 'item' || e.type === 'paragraph')) {
                current = {title: e.lead, text: e.rest, i: e.i, sub: false};
                out.push(current);
            } else {
                current = null;
            }
        });

        return out.filter(function (c) {
            return words(c.text) <= CARD_WORDS;
        }).map(function (c) {
            return {title: c.title, text: c.text, i: c.i};
        });
    }

    function priced(rows) {
        var cells = 0;

        rows.forEach(function (row) {
            row.forEach(function (cell) {
                if (PRICE.test(cell)) {
                    cells++;
                }
            });
        });

        return cells >= 2 || (cells === 1 && rows.length <= 3);
    }

    // Count of the group's paragraphs that carry content (not subheadings).
    function weight(body) {
        return body.filter(function (e) {
            return e.type !== 'heading';
        }).length;
    }

    // Every reading the group's body fits, best first, with its items.
    function readingsOf(body) {
        var out = [], all = weight(body), tables, pictures, q, figures, quotes, cards, contacts;

        if (!body.length) {
            return out;
        }

        tables = body.filter(function (e) {
            return e.type === 'table';
        });

        if (tables.length) {
            var rows = tables[0].rows;
            out.push({kind: priced(rows) ? 'prices' : 'table', items: tables.map(function (t) {
                return {rows: t.rows, i: t.i};
            }), score: 3});
        }

        pictures = body.filter(function (e) {
            return e.type === 'picture';
        });

        if (pictures.length && pictures.length === all) {
            out.push({kind: pictures.length > 1 ? 'gallery' : 'picture', items: pictures.map(function (p) {
                return {src: p.src, alt: p.alt, i: p.i};
            }), score: 3});
        }

        q = questionsOf(body);

        if (q.length >= 2 && q.length * 2 >= all * 0.6) {
            out.push({kind: 'questions', items: q, score: q.length * 2 / Math.max(all, 1)});
        }

        figures = body.map(figureOf).filter(Boolean);

        if (figures.length >= 2 && figures.length >= all * 0.6) {
            out.push({kind: 'figures', items: figures, score: figures.length / Math.max(all, 1)});
        }

        quotes = testimonialsOf(body);

        if (quotes.length && quotes.some(function (t) {
            return t.name !== '';
        })) {
            out.push({kind: 'testimonial', items: quotes, score: quotes.length * 2 / Math.max(all, 1)});
        }

        cards = cardsOf(body);

        if (cards.length >= 2 && cards.length * 2 >= all * 0.6) {
            out.push({kind: 'cards', items: cards, score: cards.length * 2 / Math.max(all, 1)});
        }

        contacts = contactsOf(body, true);

        if (contacts.length && (contacts.some(function (c) {
            return c.type !== 'address';
        }) || contacts.length >= 2) && all <= 8) {
            out.push({kind: 'contact', items: contactsOf(body, false), score: Math.min(1, contacts.length * 2 / Math.max(all, 1))});
        }

        out.sort(function (a, b) {
            return b.score - a.score;
        });

        return out;
    }

    function textItems(body) {
        return body.map(function (e) {
            var item = {type: e.type, text: e.text, i: e.i};

            if (e.type === 'heading') {
                item.level = e.level;
            } else if (e.type === 'table') {
                item.rows = e.rows;
            } else if (e.type === 'picture') {
                item.src = e.src;
                item.alt = e.alt;
            }

            return item;
        });
    }

    function idsOf(heading, body) {
        return (heading ? [heading.i] : []).concat(body.map(function (e) {
            return e.i;
        }));
    }

    function section(kind, heading, body, items, unsure, reason, options) {
        var paragraphs = idsOf(heading, body), count = 0;

        body.forEach(function (e) {
            count += words(e.text);
        });

        return {
            id: 'p' + paragraphs[0],
            kind: kind,
            heading: heading ? heading.text : '',
            items: items.map(function (item) {
                var copy = {}, key;

                for (key in item) {
                    if (Object.prototype.hasOwnProperty.call(item, key) && key !== 'i') {
                        copy[key] = item[key];
                    }
                }

                return copy;
            }),
            paragraphs: paragraphs,
            words: count,
            long: kind === 'text' && count > LONG_WORDS,
            unsure: !!unsure,
            reason: unsure ? reason : null,
            options: unsure ? options : []
        };
    }

    // One group to its section: the best reading of its body, weighed
    // against the kind its heading names.
    function readGroup(heading, body) {
        var readings = readingsOf(body), named = heading ? wordKinds(heading.text) : [];
        var best = readings[0] || null, kind = best ? best.kind : 'text';
        var items = best ? best.items : textItems(body);

        if (best && readings[1] && readings[1].score >= best.score * 0.9 && readings[1].kind !== best.kind) {
            var pick = named.indexOf(readings[1].kind) !== -1 ? readings[1] : best;
            var sure = named.indexOf(pick.kind) !== -1;

            return section(pick.kind, heading, body, pick.items, !sure, 'mixed', sure ? [] : [best.kind, readings[1].kind]);
        }

        if (named.length && named.indexOf(kind) === -1) {
            // The heading names a kind the body does not show: prices on
            // a table, gallery on pictures agree; otherwise it is asked.
            if ((kind === 'table' && named.indexOf('prices') !== -1) || (kind === 'picture' && named.indexOf('gallery') !== -1)) {
                return section(named.indexOf('prices') !== -1 ? 'prices' : 'gallery', heading, body, items, false);
            }

            var match = null;

            readings.forEach(function (r) {
                if (!match && named.indexOf(r.kind) !== -1) {
                    match = r;
                }
            });

            if (match) {
                return section(match.kind, heading, body, match.items, false);
            }

            return section(kind, heading, body, items, true, 'words', [kind, named[0]]);
        }

        return section(kind, heading, body, items, false);
    }

    function read(paragraphs) {
        var skipped = [], sections = [];
        var total = Array.isArray(paragraphs) ? paragraphs.length : 0;
        var entries = prepare(paragraphs, skipped);
        var grouped = groupsOf(entries);

        grouped.groups.forEach(function (group) {
            var heading = group.heading, body = group.body;

            if (heading && heading === grouped.first) {
                // The intro: the first heading with its lead; what follows
                // the lead in the same group is read as a section of its own.
                var lead = [];

                while (lead.length < INTRO_LEAD && body.length && body[0].type === 'paragraph' && body[0].lead === '' && !isQuestion(body[0]) && !figureOf(body[0])) {
                    lead.push(body.shift());
                }

                if (body.length && body[0].type === 'picture' && body.length === 1) {
                    lead.push(body.shift());
                }

                sections.push(section('intro', heading, lead, textItems(lead), false));

                if (body.length) {
                    sections.push(readGroup(null, body));
                }

                return;
            }

            if (!heading && !body.length) {
                return;
            }

            sections.push(readGroup(heading, body));
        });

        return {sections: sections, skipped: skipped, cut: Math.max(0, total - MAX_PARAGRAPHS)};
    }

    // ---- A pasted text to paragraphs --------------------------------------

    function boldLead(text) {
        var m = /^(?:\*\*|__)(.+?)(?:\*\*|__)\s*(.*)$/.exec(text);

        if (!m) {
            return {text: text, lead: ''};
        }

        return {text: trim(m[1] + (m[2] !== '' ? ' ' + m[2] : '')), lead: trim(m[1])};
    }

    function cellsOf(line) {
        return trim(line).replace(/^\|/, '').replace(/\|$/, '').split('|').map(trim);
    }

    // A line that stands alone and reads like a heading in a text with no
    // marked headings: a few words, no end mark, no figure or contact.
    function plainHeading(line) {
        var t = trim(line);

        return words(t) <= 8 && t.length <= 80 && !/[.!?:;,"”»]$/.test(t)
            && !figureOf({type: 'paragraph', text: t}) && !EMAIL.test(t) && !PHONE.test(t)
            && !/^[-*+•]\s|^\d+[.)]\s|^>|^\||^!\[/.test(t);
    }

    function paragraphsOf(text) {
        var lines = str(text).replace(/\r\n?/g, '\n').split('\n'), out = [], k = 0, marked = false;

        lines.forEach(function (line) {
            if (/^\s{0,3}#{1,6}\s+\S/.test(line) || /^\s{0,3}(=+|-+)\s*$/.test(line)) {
                marked = true;
            }
        });

        function push(p) {
            out.push(p);
        }

        while (k < lines.length) {
            var line = lines[k], t = trim(line), m, next = trim(lines[k + 1]);

            if (t === '') {
                k++;
                continue;
            }

            if ((m = /^\s{0,3}(#{1,6})\s+(.*?)\s*#*\s*$/.exec(line))) {
                push({style: 'heading', level: m[1].length, text: m[2]});
                k++;
                continue;
            }

            if (next !== '' && /^(=+|-+)$/.test(next) && !/^[-*+•]\s/.test(t)) {
                push({style: 'heading', level: next.charAt(0) === '=' ? 1 : 2, text: t});
                k += 2;
                continue;
            }

            if ((m = /^!\[([^\]]*)\]\(([^)\s]+)[^)]*\)$/.exec(t))) {
                push({style: 'picture', src: m[2], alt: m[1], text: ''});
                k++;
                continue;
            }

            if (/^\|.*\|$/.test(t) && /^\|.*\|$/.test(next)) {
                var rows = [];

                while (k < lines.length && /^\|.*\|$/.test(trim(lines[k]))) {
                    if (!/^\|[\s:|-]+\|$/.test(trim(lines[k]))) {
                        rows.push(cellsOf(lines[k]));
                    }

                    k++;
                }

                push({style: 'table', rows: rows, text: ''});
                continue;
            }

            if (/^>/.test(t)) {
                var quote = [];

                while (k < lines.length && /^>/.test(trim(lines[k]))) {
                    quote.push(trim(trim(lines[k]).replace(/^>/, '')));
                    k++;
                }

                push({style: 'quote', text: quote.join(' ')});
                continue;
            }

            if ((m = /^(?:[-*+•]|\d+[.)])\s+(.*)$/.exec(t))) {
                var item = boldLead(trim(m[1]));
                push({style: 'item', text: item.text, lead: item.lead});
                k++;
                continue;
            }

            // A paragraph: lines joined while one breaks mid-sentence.
            var parts = [t];
            k++;

            while (k < lines.length && trim(lines[k]) !== '' && !/[.!?:;"”»]$/.test(parts[parts.length - 1])
                && /^[a-zçğıöşü(]/.test(trim(lines[k]))) {
                parts.push(trim(lines[k]));
                k++;
            }

            var joined = boldLead(parts.join(' '));
            var alone = (k >= lines.length || trim(lines[k]) === '') && (out.length === 0 || trim(lines[k - parts.length - 1]) === '');

            if (!marked && parts.length === 1 && alone && k < lines.length && plainHeading(t)) {
                push({style: 'heading', level: out.some(function (p) {
                    return p.style === 'heading';
                }) ? 2 : 1, text: t});
                continue;
            }

            push({style: 'paragraph', text: joined.text, lead: joined.lead});
        }

        return out;
    }

    // Every kind a section's words can fill, with its items: the section's
    // own kind first, the other readings of its body (the heading left
    // out), then text, which any words fill. The rows' kind list offers
    // these and nothing else.
    var fitsMemo = {paragraphs: null, entries: []};

    function fits(paragraphs, sec) {
        if (!sec || !Array.isArray(sec.paragraphs)) {
            return [];
        }

        var at = {}, out = [], seen = {};

        sec.paragraphs.forEach(function (i) {
            at[i] = true;
        });

        // The paragraphs are prepared once for all the sections of a reading.
        if (fitsMemo.paragraphs !== paragraphs) {
            fitsMemo = {paragraphs: paragraphs, entries: prepare(paragraphs, [])};
        }

        var body = fitsMemo.entries.filter(function (e) {
            return at[e.i];
        });

        if (sec.heading && body.length && body[0].type === 'heading') {
            body.shift();
        }

        var add = function (kind, items) {
            if (!seen[kind]) {
                seen[kind] = true;
                out.push({kind: kind, items: section(kind, null, [], items, false).items});
            }
        };

        add(sec.kind, sec.items || []);
        (sec.kind === 'intro' ? [] : readingsOf(body)).forEach(function (r) {
            add(r.kind, r.items);
        });
        add('text', textItems(body));

        return out;
    }

    window.PGDocumentReader = {
        KINDS: KINDS.slice(),
        fits: fits,
        fold: fold,
        wordKinds: wordKinds,
        paragraphs: paragraphsOf,
        read: read
    };
})();
