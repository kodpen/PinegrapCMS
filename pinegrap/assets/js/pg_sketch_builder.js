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
 * - opts.site: {name, year, path, pages: [{name, address}], footer}, what the site
 *   knows: its name, the year, its site path ('/' when none) and the
 *   Prototype view's pages in their order. With it the header is a navbar of
 *   the name and the pages' links (at most six) and the footer the name, the
 *   year and the same links (or those of `footer`, a list of the same
 *   shape, when given); the caller uses them only when the design has
 *   no header or footer of its own. opts.page: {title, description} of
 *   "Page details", the intro's heading and paragraph. opts.names: kind =>
 *   the caller's translated name, the heading of a section whose box has no
 *   typed word.
 *
 * What a part takes from the sketch: the typed heading (the first heading of
 * the block), the number of columns of a row of equal boxes (features,
 * cards, gallery; a column copied to reach the count gets its number in its
 * heading) and a picture beside the text (the split intro). The title goes
 * into the block's first h1-h6, not a badge before it. A body section whose
 * padding is py-5 takes the design's section rhythm,
 * var(--pg-var-space-section), falling back to the starter's own 3rem, so a
 * design without variables draws as before. Every built root carries _sketchId, the section's id.
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

    // The most page links a built header or footer carries.
    var MAX_LINKS = 6;

    // A body section's padding: the design's section rhythm, else the
    // starters' own py-5.
    var RHYTHM = 'padding-top: var(--pg-var-space-section, 3rem); padding-bottom: var(--pg-var-space-section, 3rem)';

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

    // The heading a section's title goes into: the first h1-h6, so a
    // starter's badge or eyebrow (a heading drawn as a <span>) keeps its
    // word; the first heading of any tag when the block has no h1-h6.
    function titleHeading(root) {
        return find(root, function (node) {
            return isHeading(node) && /^h[1-6]$/.test(String(node.props.tag || ''));
        }) || find(root, isHeading);
    }

    function isParagraph(node) {
        return node.type === 'content' && node.props && node.props.contentType === 'paragraph';
    }

    function str(value) {
        return (typeof value === 'string') ? value.trim() : '';
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
            var copy = copyTree(kids[kids.length - 1], make);
            var heading = titleHeading(copy);
            if (heading && heading.props) {
                heading.props.text = String(heading.props.text || '').replace(/\s+\d+$/, '') + ' ' + (kids.length + 1);
            }
            kids.push(copy);
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

    // A root whose padding is the starters' py-5, which the rhythm stands
    // in for. A root with a style attribute keeps its own: an inline style
    // would hide it (_attrs prints only for an empty prop), and the
    // background picture of hero-image-bg sits there.
    function takesRhythm(root) {
        var p = root.props || {};
        var attrs = Array.isArray(p._attrs) ? p._attrs : [];
        for (var i = 0; i < attrs.length; i++) {
            if (attrs[i] && String(attrs[i].name).toLowerCase() === 'style') {
                return false;
            }
        }
        return /(^|\s)py-5(\s|$)/.test(String(p.cssClass || ''));
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

    // The page links of what the site knows: the pages' names to their
    // addresses under the site path, at most MAX_LINKS, nameless or
    // addressless pages left out. `list`: the pages to link, the site's
    // pages when not given.
    function siteLinks(site, list) {
        var path = str(site.path) || '/';
        if (path.charAt(path.length - 1) !== '/') {
            path += '/';
        }
        var out = [];
        (Array.isArray(list) ? list : (Array.isArray(site.pages) ? site.pages : [])).forEach(function (page) {
            var name = page ? str(page.name) : '';
            var address = page ? str(page.address).replace(/^\/+|\/+$/g, '') : '';
            if (name && address && out.length < MAX_LINKS) {
                out.push({text: name, href: path + address.split('/').map(encodeURIComponent).join('/')});
            }
        });
        return out;
    }

    function siteHome(site) {
        return str(site.path) || '/';
    }

    function linkNode(link, cssClass, make) {
        return make('content', {contentType: 'link', text: escapeText(link.text), href: link.href, cssClass: cssClass});
    }

    // A new header: the site's name as the brand and its pages' links.
    /* @pg-site-output */
    function siteHeader(section, site, make) {
        var collapse = 'pg-sk-nav-' + String(section.id === undefined ? '' : section.id).replace(/[^A-Za-z0-9_-]/g, '');
        var items = siteLinks(site).map(function (link) {
            return make('semantic', {tag: 'li', cssClass: 'nav-item', customName: link.text}, [linkNode(link, 'nav-link', make)]);
        });
        return make('semantic', {tag: 'nav', cssClass: 'navbar navbar-expand-lg bg-body border-bottom', customName: 'Navbar'}, [
            make('semantic', {tag: 'div', cssClass: 'container', customName: ''}, [
                make('content', {contentType: 'link', text: escapeText(str(site.name)), href: siteHome(site), cssClass: 'navbar-brand fw-bold'}),
                make('semantic', {tag: 'button', cssClass: 'navbar-toggler border-0', customName: '', _attrs: [
                    {name: 'type', value: 'button'},
                    {name: 'data-bs-toggle', value: 'collapse'},
                    {name: 'data-bs-target', value: '#' + collapse},
                    {name: 'aria-controls', value: collapse},
                    {name: 'aria-expanded', value: 'false'},
                    {name: 'aria-label', value: str(site.toggle)}
                ]}, [
                    make('semantic', {tag: 'span', cssClass: 'navbar-toggler-icon', customName: ''})
                ]),
                make('semantic', {tag: 'div', cssClass: 'collapse navbar-collapse', id: collapse, customName: ''}, [
                    make('semantic', {tag: 'ul', cssClass: 'navbar-nav ms-auto mb-2 mb-lg-0', customName: ''}, items)
                ])
            ])
        ]);
    }

    // A new footer: the site's name, the year and its pages' links (the
    // footer's own list when the site gives one).
    /* @pg-site-output */
    function siteFooter(site, make) {
        var name = escapeText(str(site.name));
        var year = count(site.year);
        var items = siteLinks(site, Array.isArray(site.footer) ? site.footer : null).map(function (link) {
            return make('semantic', {tag: 'li', cssClass: 'nav-item', customName: link.text}, [linkNode(link, 'nav-link px-2 text-body-secondary', make)]);
        });
        return make('semantic', {tag: 'footer', cssClass: 'py-4 border-top bg-body', customName: 'Footer'}, [
            make('container', {fluid: false, cssClass: ''}, [
                make('semantic', {tag: 'div', cssClass: 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3', customName: ''}, [
                    make('content', {contentType: 'heading', tag: 'span', text: name, cssClass: 'fw-bold mb-0'}),
                    make('semantic', {tag: 'ul', cssClass: 'nav justify-content-center', customName: ''}, items),
                    make('content', {contentType: 'paragraph', text: '&copy; ' + (year ? year + ' ' : '') + name, cssClass: 'small text-body-secondary mb-0'})
                ])
            ])
        ]);
    }

    // The block's first paragraph takes `html`; a block without one gets
    // it after its first heading.
    function placeParagraph(root, html, make) {
        var para = find(root, isParagraph);
        if (para) {
            para.props.text = html;
            return;
        }
        var parent = find(root, function (node) {
            return (node.children || []).some(isHeading);
        });
        if (parent) {
            var at = 0;
            while (!isHeading(parent.children[at])) {
                at++;
            }
            parent.children.splice(at + 1, 0, make('content', {contentType: 'paragraph', text: html, cssClass: 'lead'}));
        }
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
        if (opts.site && (place === 'header' || place === 'footer')) {
            item.block = null;
            root = place === 'header' ? siteHeader(section, opts.site, opts.node) : siteFooter(opts.site, opts.node);
            root.props._sketchId = section.id || '';
            item.node = root;
            return item;
        }
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
        var page = opts.page || {};
        var title = str(section.heading);
        var paragraph = '';
        if (!title && kind === 'intro') {
            title = str(page.title) || str(page.name);
            paragraph = str(page.description);
        }
        if (!title && opts.names && typeof opts.names[kind] === 'string') {
            title = str(opts.names[kind]);
        }
        if (title) {
            var heading = titleHeading(root);
            if (heading) {
                heading.props.text = escapeText(title);
            }
            if (!section.heading && root.props && root.props.customName !== undefined) {
                root.props.customName = title;
            }
        }
        if (paragraph) {
            placeParagraph(root, escapeText(paragraph), opts.node);
        }
        if (place === 'body' && takesRhythm(root)) {
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
            kept: o.kept || null,
            site: (o.site && typeof o.site === 'object') ? o.site : null,
            page: (o.page && typeof o.page === 'object') ? o.page : null,
            names: (o.names && typeof o.names === 'object') ? o.names : null
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
