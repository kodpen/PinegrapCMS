/**
 * Pinegrap - Enterprise Website Platform
 *
 * The page built from a document's rows (window.PGDocumentPage), for the
 * designer's "From a document" start (style_designer_document.js). Pure: it
 * builds only through the functions it is handed and changes none of its
 * input.
 *
 * build(rows, opts): the body's sections, in the rows' order. Intro, text,
 * cards, picture and gallery go through the sketch builder
 * (window.PGSketchBuilder: hero-centered, a text section, services-icon-cards,
 * a wide picture, image-grid); figures and questions are the stats-counter
 * and faq-twocol starters; a testimonial, a table, prices and contact are
 * plain Bootstrap 5 sections. Every section wears the design's section
 * rhythm (var(--pg-var-space-section)), as the sketch builder's do.
 *
 * The words go in as they were written: each item's words fill a starter's
 * column, and what the starter brought as sample words (headings,
 * paragraphs, links) is taken out. The text items among another kind's
 * items (the words the reader took as no item) are plain paragraphs of the
 * same section, over or under the items as they stand, a bold lead kept
 * bold. The intro's picture goes in a hero with a picture place
 * (hero-split), else in a picture section under the intro. Texts are HTML-escaped, as a heading's
 * and a paragraph's `text` is HTML.
 *
 * opts.make(type): a starter tree (createFromPalette()); opts.node(type,
 * props, children): a new node (createNode()); opts.src(src): a picture's
 * address, '' to leave the picture out (a document's picture:<n> stored in
 * the file manager, a pasted text's own address).
 *
 * tests/document_start_test.php runs it in Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var RHYTHM = 'padding-top: var(--pg-var-space-section, 3rem); padding-bottom: var(--pg-var-space-section, 3rem)';
    var WIDTHS = {1: '12', 2: '6', 3: '4', 4: '3'};
    var SAMPLE = {heading: true, paragraph: true, link: true, span: true};
    // A kind the sketch builder makes, as the builder's own kind.
    var SKETCH = {intro: 'intro', text: 'text', cards: 'cards', picture: 'picture', gallery: 'gallery'};
    // The types of a text item. In a section of another kind such an item
    // is a paragraph the reader did not take as an item: it is built as a
    // plain paragraph of the section, above or under the items.
    var LOOSE = {paragraph: true, item: true, quote: true, heading: true, table: true, picture: true};

    function escapeText(text) {
        return String(text == null ? '' : text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function walk(node, fn, parent) {
        if (fn(node, parent) === false) {
            return;
        }
        (node.children || []).slice().forEach(function (child) { walk(child, fn, node); });
    }

    function contentOf(node, kind) {
        var hit = null;
        walk(node, function (n) {
            if (hit) {
                return false;
            }
            if (n.type === 'content' && n.props && n.props.contentType === kind && !n._filled) {
                hit = n;
                return false;
            }
        });
        return hit;
    }

    function put(node, kind, text) {
        var n = contentOf(node, kind);
        if (n && text) {
            n.props.text = escapeText(text);
            n._filled = true;
        }
        return n;
    }

    function sample(c) {
        return !c._filled && c.props && ((c.type === 'content' && SAMPLE[c.props.contentType])
            || (c.type === 'component' && c.props.componentType === 'btn'));
    }

    // Takes out the starter's sample words left unfilled (a hero's buttons
    // too: a document names no button), a group they leave empty, then the
    // marks.
    function prune(n) {
        n.children = (n.children || []).filter(function (c) {
            if (sample(c)) {
                return false;
            }
            var had = (c.children || []).length;
            prune(c);
            return !(c.type === 'semantic' && had && !c.children.length);
        });
    }

    function clean(root) {
        prune(root);
        walk(root, function (n) { delete n._filled; });
        return root;
    }

    function copyTree(node, make) {
        return make(node.type, JSON.parse(JSON.stringify(node.props || {})), (node.children || []).map(function (c) { return copyTree(c, make); }));
    }

    function itemRow(root) {
        var hit = null;
        walk(root, function (n) {
            if (hit) {
                return false;
            }
            if (n.type === 'row' && (n.children || []).length >= 2 && n.children.every(function (c) { return c.type === 'col'; })) {
                hit = n;
                return false;
            }
        });
        return hit;
    }

    // One column of the starter's row for each item, filled by `fill(col, item)`.
    function fillColumns(root, items, make, fill) {
        var row = itemRow(root);
        if (!row) {
            return;
        }
        var first = row.children[0];
        var width = WIDTHS[Math.min(items.length, 4)];
        row.children = items.map(function (item) {
            var col = copyTree(first, make);
            ['lg', 'md'].forEach(function (bp) {
                if (col.props[bp]) {
                    col.props[bp] = width;
                }
            });
            fill(col, item);
            return col;
        });
    }

    // The row's heading in the starter's own heading outside its item row;
    // a starter without one (stats-counter) gets one over the row.
    function headingOver(root, heading, node) {
        var row = itemRow(root);
        var own = null;
        walk(root, function (n) {
            if (own || n === row) {
                return false;
            }
            if (n.type === 'content' && n.props && n.props.contentType === 'heading' && !n._filled) {
                own = n;
                return false;
            }
        });
        if (own) {
            own.props.text = escapeText(heading);
            own._filled = true;
            return;
        }
        var parent = null;
        walk(root, function (n) {
            if (row && (n.children || []).indexOf(row) !== -1) {
                parent = n;
            }
        });
        if (parent) {
            var h = node('content', {contentType: 'heading', tag: 'h2', text: escapeText(heading), cssClass: 'fw-bold mb-4 text-center'});
            h._filled = true;
            parent.children.splice(parent.children.indexOf(row), 0, h);
        }
    }

    function withRhythm(root) {
        var p = root.props = root.props || {};
        if (p.inlineStyle === undefined || String(p.inlineStyle).indexOf('--pg-var-space-section') === -1) {
            var own = p.inlineStyle ? String(p.inlineStyle).replace(/[;\s]+$/, '') : '';
            p.inlineStyle = own ? own + '; ' + RHYTHM : RHYTHM;
        }
        return root;
    }

    function texts(row) {
        return row.items.filter(function (i) { return i.text && !i.rows && !i.src; });
    }

    // A plain section: the heading, then the given children.
    function plain(row, node, kids, name) {
        var inner = row.heading ? [node('content', {contentType: 'heading', tag: 'h2', text: escapeText(row.heading), cssClass: 'fw-bold mb-4'})] : [];
        return node('semantic', {tag: 'section', cssClass: '', customName: row.heading || name}, [node('container', {fluid: false, cssClass: ''}, inner.concat(kids))]);
    }

    function table(rows, node, prices) {
        var body = rows.map(function (cells, r) {
            return node('semantic', {tag: 'tr'}, cells.map(function (cell, c) {
                var head = !prices && r === 0 && rows.length > 1;
                var end = prices && c === cells.length - 1 && c > 0;
                return node('semantic', {tag: head ? 'th' : 'td', cssClass: end ? 'text-end fw-semibold' : ''}, [node('content', {contentType: 'heading', tag: 'span', text: escapeText(cell), cssClass: ''})]);
            }));
        });
        return node('semantic', {tag: 'div', cssClass: 'table-responsive'}, [node('semantic', {tag: 'table', cssClass: 'table mb-0'}, [node('semantic', {tag: 'tbody'}, body)])]);
    }

    function own(row, node, before, after) {
        var wrap = function (kids) { return before.concat(kids, after); };
        switch (row.kind) {
            case 'testimonial':
                return plain(row, node, wrap(row.items.map(function (q) {
                    return node('semantic', {tag: 'figure', cssClass: 'text-center mb-5'}, [
                        node('semantic', {tag: 'blockquote', cssClass: 'blockquote'}, [node('content', {contentType: 'paragraph', text: escapeText(q.quote), cssClass: 'fs-4'})]),
                        node('semantic', {tag: 'figcaption', cssClass: 'blockquote-footer mt-2'}, [node('content', {contentType: 'span', text: escapeText(q.name + (q.role ? ', ' + q.role : '')), cssClass: ''})])
                    ]);
                })), 'Testimonial');
            case 'table':
            case 'prices':
                return plain(row, node, wrap(row.items.map(function (t) { return table(t.rows || [], node, row.kind === 'prices'); })), 'Table');
            case 'contact':
                return plain(row, node, wrap([node('semantic', {tag: 'ul', cssClass: 'list-unstyled fs-5 mb-0'}, row.items.map(function (c) {
                    var href = c.type === 'email' ? 'mailto:' + c.value : (c.type === 'phone' ? 'tel:' + String(c.value).replace(/[^\d+]/g, '') : '');
                    return node('semantic', {tag: 'li', cssClass: 'mb-2'}, [href
                        ? node('content', {contentType: 'link', text: escapeText(c.value), href: href, cssClass: ''})
                        : node('content', {contentType: 'span', text: escapeText(c.value), cssClass: ''})]);
                }))]), 'Contact');
        }
        return null;
    }

    // A paragraph's words, its bold lead kept bold.
    function withLead(i) {
        var text = String(i.text || '');
        var lead = i.lead ? String(i.lead) : '';
        if (lead && text.indexOf(lead) === 0 && text.length > lead.length) {
            return '<strong>' + escapeText(lead) + '</strong>' + escapeText(text.slice(lead.length));
        }
        return escapeText(text);
    }

    // Marks a built node and all under it as filled, so clean() keeps it.
    function filled(n) {
        walk(n, function (m) { m._filled = true; });
        return n;
    }

    // Text items as paragraphs and subheadings; a table or a picture among
    // them where it stands.
    function textBody(items, o) {
        var node = o.node;
        return items.map(function (i) {
            if (i.rows) {
                return table(i.rows, node, false);
            }
            if (i.src) {
                var src = o.src(i.src);
                return src ? node('content', {contentType: 'image', src: src, alt: i.alt || '', cssClass: 'img-fluid rounded my-4'}) : null;
            }
            if (i.type === 'heading') {
                return node('content', {contentType: 'heading', tag: 'h3', text: escapeText(i.text), cssClass: 'h5 fw-semibold mt-4'});
            }
            return i.text ? node('content', {contentType: 'paragraph', text: (i.type === 'item' ? '• ' : '') + withLead(i), cssClass: i.type === 'quote' ? 'fst-italic border-start ps-3' : ''}) : null;
        }).filter(Boolean).map(filled);
    }

    // A section's items without the words around them, and those words:
    // {before, items, after}. A text or an intro has none around.
    function split(row) {
        var out = {before: [], items: [], after: []};
        (row.items || []).forEach(function (i) {
            if (row.kind === 'text' || row.kind === 'intro' || !LOOSE[i && i.type]) {
                out.items.push(i);
            } else {
                (out.items.length ? out.after : out.before).push(i);
            }
        });
        return out;
    }

    // The words around the items, over and under the starter's item row
    // (at the end of the section when it has none).
    function around(root, before, after) {
        if (!before.length && !after.length) {
            return;
        }
        var row = itemRow(root), parent = null;
        if (row) {
            walk(root, function (n) {
                if ((n.children || []).indexOf(row) !== -1) {
                    parent = n;
                }
            });
        }
        if (!parent) {
            walk(root, function (n) {
                if (!parent && n.type === 'container') {
                    parent = n;
                }
            });
            parent = parent || root;
            parent.children = (parent.children || []).concat(before, after);
            return;
        }
        var at = parent.children.indexOf(row);
        parent.children.splice.apply(parent.children, [at, 1].concat(before, [row], after));
    }

    // A section's picture placed by the address callback, or null.
    function placed(i, o) {
        var src = i && i.src ? o.src(i.src) : '';
        return src ? {src: src, alt: i.alt || ''} : null;
    }

    // One row's sections: one, or the intro and a picture section under it
    // when no hero starter has a picture place for the intro's picture.
    function section(row, o) {
        var node = o.node;
        var parts = split(row);
        var before = textBody(parts.before, o);
        var after = textBody(parts.after, o);
        var listRow = {id: row.id, kind: row.kind, heading: row.heading, items: parts.items};
        var pictures = parts.items.map(function (i) { return placed(i, o); }).filter(Boolean);
        var built = own(listRow, node, before, after);
        if (built) {
            return built;
        }
        if (row.kind === 'figures' || row.kind === 'questions') {
            var root = o.make(row.kind === 'figures' ? 'stats-counter' : 'faq-twocol');
            if (!root) {
                return null;
            }
            root = copyTree(root, node);
            if (row.heading) {
                headingOver(root, row.heading, node);
            }
            if (row.kind === 'figures') {
                fillColumns(root, parts.items, node, function (col, f) {
                    put(col, 'heading', f.value);
                    put(col, 'paragraph', f.label);
                });
            } else {
                var faq = itemRow(root);
                var block = faq && faq.children[0].children[0];
                if (faq && block) {
                    var half = Math.ceil(parts.items.length / 2);
                    faq.children.forEach(function (col, c) {
                        col.children = parts.items.slice(c * half, (c + 1) * half).map(function (q) {
                            var b = copyTree(block, node);
                            put(b, 'heading', q.question);
                            put(b, 'paragraph', q.answer);
                            return b;
                        });
                    });
                }
            }
            around(root, before, after);
            return clean(root);
        }
        if ((row.kind === 'picture' || row.kind === 'gallery') && !pictures.length) {
            return null;
        }
        var kind = SKETCH[row.kind] || 'text';
        var sketch = {id: row.id, kind: kind, heading: row.heading, picture: kind !== 'picture' && kind !== 'gallery' && pictures.length > 0};
        if (kind === 'intro' && sketch.picture) {
            sketch.columns = 2;
        }
        var part = window.PGSketchBuilder.build([sketch], {make: o.make, node: node, words: {}}).body[0];
        var out = part && part.node;
        if (!out) {
            return null;
        }
        walk(out, function (n) {
            if (n.type === 'content' && n.props && n.props.contentType === 'heading' && row.heading && n.props.text === escapeText(row.heading)) {
                n._filled = true;
                return false;
            }
        });
        var extra = null;
        if (kind === 'cards') {
            fillColumns(out, parts.items, node, function (col, c) {
                put(col, 'heading', c.title);
                put(col, 'paragraph', c.text);
            });
            around(out, before, after);
        } else if (kind === 'gallery' || kind === 'picture') {
            var image = contentOf(out, 'image');
            if (kind === 'gallery') {
                fillColumns(out, pictures, node, function (col, p) {
                    var img = contentOf(col, 'image');
                    if (img) {
                        img.props.src = p.src;
                        img.props.alt = p.alt;
                    }
                });
            } else if (image) {
                image.props.src = pictures[0].src;
                image.props.alt = pictures[0].alt;
            }
            around(out, before, after);
        } else {
            var para = contentOf(out, 'paragraph');
            if (kind === 'intro') {
                var lead = texts(row).map(function (i) { return i.text; });
                if (para && lead.length) {
                    put(out, 'paragraph', lead.join(' '));
                }
                if (pictures.length) {
                    // The hero's own picture place shows the intro's
                    // picture; a hero without one gets a picture section
                    // under it.
                    var heroImage = contentOf(out, 'image');
                    if (heroImage) {
                        heroImage.props.src = pictures[0].src;
                        heroImage.props.alt = pictures[0].alt;
                    } else {
                        extra = pictureUnder(row, pictures[0], o);
                    }
                }
            } else if (para) {
                var parent = null;
                walk(out, function (n) {
                    if ((n.children || []).indexOf(para) !== -1) {
                        parent = n;
                    }
                });
                if (parent) {
                    var at = parent.children.indexOf(para);
                    parent.children.splice.apply(parent.children, [at, 1].concat(textBody(row.items, o)));
                }
            }
        }
        delete out.props._sketchId;
        clean(out);
        return extra ? [out, extra] : out;
    }

    // The intro's picture as a picture section of its own.
    function pictureUnder(row, picture, o) {
        var part = window.PGSketchBuilder.build([{id: row.id + 'i', kind: 'picture', heading: ''}], {make: o.make, node: o.node, words: {}}).body[0];
        var out = part && part.node;
        var image = out && contentOf(out, 'image');
        if (!image) {
            return null;
        }
        image.props.src = picture.src;
        image.props.alt = picture.alt;
        delete out.props._sketchId;
        return out;
    }

    function plainMaker() {
        var n = 0;
        return function (type, props, children) {
            n++;
            return {_id: 'doc_' + n, type: type, props: props || {}, children: children || []};
        };
    }

    function build(rows, opts) {
        var o = {
            make: (opts && opts.make) || function () { return null; },
            node: (opts && opts.node) || plainMaker(),
            src: (opts && opts.src) || function (src) { return /^(https?:\/\/|\/)/.test(String(src)) ? String(src) : ''; }
        };
        var out = [];
        (rows || []).filter(function (r) { return r && !r.out; }).forEach(function (row) {
            var built = section(row, o);
            (Array.isArray(built) ? built : [built]).forEach(function (root) {
                if (root) {
                    out.push(withRhythm(root));
                }
            });
        });
        return out;
    }

    // The pictures the page places, by their src: every picture of the kept
    // rows (the intro's in its hero or under it, one among the words around
    // a section's items where it stands), so a picture is stored in the
    // file manager only when it is used, and every used one is.
    function pictures(rows) {
        var out = [];
        (rows || []).forEach(function (r) {
            if (r && !r.out) {
                (r.items || []).forEach(function (i) {
                    if (i && i.src && out.indexOf(String(i.src)) === -1) {
                        out.push(String(i.src));
                    }
                });
            }
        });
        return out;
    }

    window.PGDocumentPage = {
        build: build,
        pictures: pictures
    };
})();
