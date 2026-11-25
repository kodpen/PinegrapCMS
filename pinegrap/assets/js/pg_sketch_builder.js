/**
 * Pinegrap - Enterprise Website Platform
 *
 * The sketch builder of the visual designer's Prototype view
 * (window.PGSketchBuilder): the sections the sketch reader gives
 * (window.PGSketchReader.read()) in, the page's parts out, each a tree made
 * from a starter block of the palette and wearing the design's variables.
 * Pure: it builds only through the functions it is handed and changes none
 * of its input.
 *
 * - blockFor(section): the palette block type a section is built from
 *   ('hero-split', 'features-grid'…), or null for a section built here (text,
 *   picture) and for a system widget (catalog, blog, booking).
 * - build(sections, opts): {header, footer, body}. The first header section
 *   is the header, the last footer section the footer (the caller makes them
 *   the design's shared components); the rest is the body, in order. Each
 *   part is {section, kind, block, widget, unsure, kept, node}; a widget part
 *   has node null and is placed by the caller.
 * - opts.make(type): the starter tree of a palette block type, or null
 *   (createFromPalette() in the editor). opts.node(type, props, children):
 *   a new node (createNode()). opts.words: {heading, paragraph, alt}, the
 *   caller's translated sample words for a section built here. opts.kept:
 *   section id => node, a section the operator already edited, given back as
 *   it is ("Build again").
 *
 * What a part takes from the sketch: the typed heading (the first heading of
 * the block), the number of columns of a row of equal boxes (features,
 * cards, gallery) and a picture beside the text (the split intro). A body
 * section's padding is the design's section rhythm, var(--pg-var-space_section),
 * falling back to the starter's own 3rem, so a design without variables
 * draws as before. Every built root carries _sketchId, the section's id.
 *
 * Nothing runs at load but the definition of window.PGSketchBuilder.
 * tests/sketch_builder_test.php runs it in Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // Kinds built from the palette's blocks; the rest are built here or are
    // system widgets.
    var WIDGETS = {catalog: 'catalog', blog: 'blog', booking: 'booking'};

    // Kinds whose block holds a row of equal columns the sketch counts.
    var COUNTED = {features: true, cards: true, gallery: true};

    // Column widths (of 12) a row of n equal columns takes at lg.
    var WIDTHS = {1: '12', 2: '6', 3: '4', 4: '3', 6: '2'};

    // The most columns a built row takes; a wider sketch row is cut to it.
    var MAX_COLUMNS = 6;

    // A body section's padding: the design's section rhythm, else the
    // starters' own py-5.
    var RHYTHM = 'padding-top: var(--pg-var-space_section, 3rem); padding-bottom: var(--pg-var-space_section, 3rem)';

    function count(value) {
        return (typeof value === 'number' && isFinite(value) && value > 0) ? Math.floor(value) : 0;
    }

    function escapeText(text) {
        return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // The palette block type a section is built from, or null.
    function blockFor(section) {
        var s = section || {};
        var columns = count(s.columns);
        var buttons = count(s.buttons);

        switch (s.kind) {
            case 'header':
                return buttons ? 'navbar-light-cta' : 'navbar-centered-logo';
            case 'intro':
                return s.picture ? (columns >= 2 ? 'hero-split' : 'hero-image-bg') : 'hero-centered';
            case 'features':
                return columns === 2 ? 'features-alt' : 'features-grid';
            case 'cards':
                return s.picture ? 'services-image-cards' : 'services-icon-cards';
            case 'gallery':
                return 'image-grid';
            case 'form':
                return 'contact-form';
            case 'cta':
                return columns >= 2 ? 'cta-inline' : 'cta-simple';
            case 'footer':
                return columns >= 3 ? 'footer-columns' : 'footer-minimal';
            default:
                return null;
        }
    }

    // A node maker for a caller that gives none (tests): plain nodes with
    // their own ids.
    function plainMaker() {
        var n = 0;
        return function (type, props, children) {
            n++;
            return {_id: 'sk_' + n, type: type, props: props || {}, children: children || []};
        };
    }

    // A deep copy whose every node takes a fresh id from the node maker.
    function copyTree(node, make) {
        var props = JSON.parse(JSON.stringify(node.props || {}));
        var kids = (node.children || []).map(function (child) { return copyTree(child, make); });
        return make(node.type, props, kids);
    }

    // The first node, depth first, for which test() is true.
    function find(node, test) {
        if (!node) {
            return null;
        }
        if (test(node)) {
            return node;
        }
        var kids = node.children || [];
        for (var i = 0; i < kids.length; i++) {
            var hit = find(kids[i], test);
            if (hit) {
                return hit;
            }
        }
        return null;
    }

    function isHeading(node) {
        return node.type === 'content' && node.props && node.props.contentType === 'heading';
    }

    // A row of two or more columns: the repeated items of a block.
    function isItemRow(node) {
        var kids = node.children || [];
        if (node.type !== 'row' || kids.length < 2) {
            return false;
        }
        for (var i = 0; i < kids.length; i++) {
            if (kids[i].type !== 'col') {
                return false;
            }
        }
        return true;
    }

    // Gives the block's first item row the sketch's number of columns: extra
    // columns go, missing ones are copies of the last, and each column takes
    // its share of the row where 12 divides evenly.
    function fitColumns(root, columns, make) {
        var row = find(root, isItemRow);
        var want = Math.min(columns, MAX_COLUMNS);
        if (!row || want < 1) {
            return;
        }
        var kids = row.children;
        if (kids.length > want) {
            kids.length = want;
        }
        while (kids.length < want) {
            kids.push(copyTree(kids[kids.length - 1], make));
        }
        if (WIDTHS[want]) {
            kids.forEach(function (col) {
                col.props = col.props || {};
                col.props.lg = WIDTHS[want];
                if (want > 2 && !col.props.md) {
                    col.props.md = '6';
                }
            });
        }
    }

    // Adds a declaration list to a node's inline style, keeping what it has
    // and the Styles panel's rows (_styles) in step.
    function addStyle(node, declarations) {
        var p = node.props = node.props || {};
        if (p._stylesInit && Array.isArray(p._styles)) {
            declarations.split(';').forEach(function (part) {
                var at = part.indexOf(':');
                if (at > 0) {
                    p._styles.push({prop: part.slice(0, at).trim(), val: part.slice(at + 1).trim()});
                }
            });
        }
        var own = p.inlineStyle ? String(p.inlineStyle).replace(/[;\s]+$/, '') : '';
        p.inlineStyle = own ? own + '; ' + declarations : declarations;
    }

    // A text section: heading and paragraph in a narrow column.
    function textSection(section, words, make) {
        var heading = section.heading || words.heading || '';
        return make('semantic', {tag: 'section', cssClass: 'py-5', customName: heading}, [
            make('container', {fluid: false, cssClass: ''}, [
                make('row', {gutter: '4', justify: 'center', align: '', cssClass: '', customName: ''}, [
                    make('col', {xs: '12', sm: '', md: '', lg: '8', xl: '', xxl: '', offsetXs: '', offsetMd: '', order: '', cssClass: '', customName: ''}, [
                        make('content', {contentType: 'heading', tag: 'h2', text: escapeText(heading), cssClass: 'fw-bold mb-3'}),
                        make('content', {contentType: 'paragraph', text: escapeText(words.paragraph || ''), cssClass: 'lead text-body-secondary'})
                    ])
                ])
            ])
        ]);
    }

    // A picture section: one wide picture, with the heading over it when one
    // was typed.
    function pictureSection(section, words, make) {
        var kids = [];
        if (section.heading) {
            kids.push(make('content', {contentType: 'heading', tag: 'h2', text: escapeText(section.heading), cssClass: 'fw-bold mb-4 text-center'}));
        }
        kids.push(make('content', {
            contentType: 'image',
            src: 'https://picsum.photos/seed/sketch/1200/600',
            alt: section.heading || words.alt || '',
            cssClass: 'img-fluid rounded w-100'
        }));
        return make('semantic', {tag: 'section', cssClass: 'py-5', customName: section.heading || words.heading || ''}, [
            make('container', {fluid: false, cssClass: ''}, kids)
        ]);
    }

    // One part of the page from one section.
    function part(section, place, opts) {
        var kind = section.kind;
        var item = {
            section: section.id,
            kind: kind,
            block: blockFor(section),
            widget: WIDGETS[kind] || null,
            unsure: !!section.unsure,
            kept: false,
            node: null
        };

        if (opts.kept && section.id && Object.prototype.hasOwnProperty.call(opts.kept, section.id) && opts.kept[section.id]) {
            item.kept = true;
            item.node = opts.kept[section.id];
            return item;
        }
        if (item.widget) {
            return item;
        }

        var root = null;
        if (item.block) {
            root = opts.make(item.block);
            if (root) {
                root = copyTree(root, opts.node);
            } else {
                item.block = null;
            }
        }
        if (!root) {
            root = kind === 'picture'
                ? pictureSection(section, opts.words, opts.node)
                : textSection(section, opts.words, opts.node);
        }

        if (COUNTED[kind] && count(section.columns) >= 2) {
            fitColumns(root, count(section.columns), opts.node);
        }
        if (section.heading) {
            var heading = find(root, isHeading);
            if (heading) {
                heading.props.text = escapeText(section.heading);
            }
        }
        if (place === 'body') {
            addStyle(root, RHYTHM);
        }
        root.props._sketchId = section.id || '';
        item.node = root;
        return item;
    }

    function build(sections, opts) {
        var o = opts || {};
        var settings = {
            make: typeof o.make === 'function' ? o.make : function () { return null; },
            node: typeof o.node === 'function' ? o.node : plainMaker(),
            words: o.words || {},
            kept: o.kept || null
        };
        var list = Array.isArray(sections) ? sections.filter(function (s) { return s && typeof s === 'object'; }) : [];
        var headerAt = -1;
        var footerAt = -1;
        var i;

        for (i = 0; i < list.length; i++) {
            if (list[i].kind === 'header') {
                headerAt = i;
                break;
            }
        }
        for (i = list.length - 1; i >= 0; i--) {
            if (list[i].kind === 'footer') {
                footerAt = i;
                break;
            }
        }

        var out = {header: null, footer: null, body: []};
        list.forEach(function (section, at) {
            if (at === headerAt) {
                out.header = part(section, 'header', settings);
            } else if (at === footerAt) {
                out.footer = part(section, 'footer', settings);
            } else {
                out.body.push(part(section, 'body', settings));
            }
        });
        return out;
    }

    window.PGSketchBuilder = {
        build: build,
        blockFor: blockFor
    };
})();
