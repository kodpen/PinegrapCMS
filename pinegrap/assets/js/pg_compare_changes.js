/**
 * Pinegrap - Enterprise Website Platform
 *
 * The change reader of Compare's Look tab (window.PgCompareChanges): two
 * designer trees in, what changed on the page out, each change with its
 * plain-words sentence and its place. Pure: no DOM, no request.
 *
 * - Elements are matched by their `_id`. An element without one is matched
 *   by its place (`#` and its child indexes from the top); a second element
 *   with the same id is matched by its order (`id#2`, `id#3`…).
 * - A change: { kind, code, node, key, text, what, name, side, parent,
 *   section, count, before, after }. `kind` is added, removed, moved,
 *   words, picture, link, look or other; `side` says on which picture the
 *   place is found ('after' the draft, 'before' the live page, 'both');
 *   `section` is the id of the page section around it; `count` the
 *   elements an added or removed subtree holds; `before` / `after` the old
 *   and new words, picture or link.
 * - An added or removed subtree is one change at its top. A prop the reader
 *   cannot name is an `other` change, never dropped. Props only the editor
 *   reads (labels, notes, locks) change nothing on the site and are not
 *   compared; an empty value equals a missing one.
 * - Sentences are English fallbacks; the screen hands its translated ones
 *   in as `texts` (code => sentence with {what}, {name}, {where}, {count}).
 *
 * Nothing runs at load but the definition of window.PgCompareChanges.
 * tests/designer_compare_changes_test.php runs it in Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // Most visible first: the order of the list.
    var KINDS = ['added', 'removed', 'picture', 'words', 'moved', 'link', 'look', 'other'];

    var TEXT = {
        added: '{what} added',
        added_named: '{what} added: {name}',
        removed: '{what} removed',
        removed_named: '{what} removed: {name}',
        moved_up: '{what} moved up',
        moved_down: '{what} moved down',
        moved_into: '{what} moved into {where}',
        words: '{what} text changed',
        alt: 'Picture description changed',
        picture: 'Picture replaced',
        link: 'Link changed',
        look: '{what} look changed',
        other: '{what} changed',
        look_rows: 'Look changed ({count} elements)',
        other_rows: 'Other changes ({count})',
        place_named: '{what}: {name}',
        kind_element: 'Element',
        kind_section: 'Section',
        kind_header: 'Header',
        kind_footer: 'Footer',
        kind_menu: 'Menu',
        kind_heading: 'Heading',
        kind_text: 'Text',
        kind_picture: 'Picture',
        kind_video: 'Video',
        kind_link: 'Link',
        kind_button: 'Button',
        kind_icon: 'Icon',
        kind_list: 'List',
        kind_list_item: 'List item',
        kind_form: 'Form',
        kind_box: 'Box',
        kind_container: 'Container',
        kind_row: 'Row',
        kind_column: 'Column',
        kind_component: 'Component'
    };

    // Props by what their change is on the page. Words and pictures carry
    // old and new values; a prop in none of these lists is an other change.
    var WORDS = ['text', 'html', 'title', 'tooltipTitle'];
    var PICTURE = ['src', 'srcset', 'poster'];
    var LINK = ['href', 'target', 'rel'];
    var LOOK = ['tag', 'cssClass', 'inlineStyle', '_styles', '_bgGrad', '_fx', 'fontFamily', 'fontSize',
        'align', 'justify', 'gutter', 'fluid', 'xs', 'sm', 'md', 'lg', 'xl', 'xxl', 'offsetXs', 'offsetMd',
        'order', 'variant', 'size', 'outline', 'rounded', 'width', 'aspectRatio', 'textPosition', 'placement'];
    // Read by the editor only: never drawn on the site.
    var EDITOR = ['_label', '_labelDetail', '_labelExpand', 'customName', '_notes', '_notes_at', '_notes_by',
        '_locked', '_expanded', '_editable', 'starterKeep', 'partOf'];

    var CONTENT_KIND = {
        heading: 'heading', paragraph: 'text', text: 'text', string: 'text', link: 'link', button: 'button',
        image: 'picture', video: 'video', icon: 'icon', list: 'list'
    };
    var TAG_KIND = {
        section: 'section', article: 'section', aside: 'section', header: 'header', footer: 'footer',
        nav: 'menu', h1: 'heading', h2: 'heading', h3: 'heading', h4: 'heading', h5: 'heading', h6: 'heading',
        p: 'text', span: 'text', small: 'text', strong: 'text', em: 'text', blockquote: 'text',
        img: 'picture', picture: 'picture', video: 'video', a: 'link', button: 'button', i: 'icon',
        ul: 'list', ol: 'list', li: 'list_item', form: 'form'
    };
    var TYPE_KIND = { container: 'container', row: 'row', col: 'column', component: 'component', shared_ref: 'component' };
    var SECTION_TAGS = ['section', 'article', 'aside', 'header', 'footer', 'nav'];

    function str(v) {
        return (v === null || v === undefined) ? '' : String(v);
    }

    function isObject(v) {
        return !!v && typeof v === 'object' && !Array.isArray(v);
    }

    function props(n) {
        return isObject(n.props) ? n.props : {};
    }

    // A value as comparable text: keys sorted, empty values alike.
    function canon(v) {
        if (v === null || v === undefined || v === '' || v === false) return '';
        if (Array.isArray(v)) {
            return v.length ? '[' + v.map(canon).join(',') + ']' : '';
        }
        if (typeof v === 'object') {
            var keys = Object.keys(v).sort();
            var parts = [];
            for (var i = 0; i < keys.length; i++) {
                var c = canon(v[keys[i]]);
                if (c !== '') parts.push(JSON.stringify(keys[i]) + ':' + c);
            }
            return parts.length ? '{' + parts.join(',') + '}' : '';
        }
        return JSON.stringify(v);
    }

    // Words without markup, entities and runs of spaces.
    function plain(v) {
        return str(v).replace(/<[^>]*>/g, ' ')
            .replace(/&nbsp;/gi, ' ').replace(/&lt;/gi, '<').replace(/&gt;/gi, '>')
            .replace(/&quot;/gi, '"').replace(/&#0?39;/gi, "'").replace(/&amp;/gi, '&')
            .replace(/\s+/g, ' ').trim();
    }

    // The tree's top nodes: a node, a list of nodes or their JSON text.
    // Null when it is none of these.
    function tops(tree) {
        if (typeof tree === 'string') {
            try {
                tree = JSON.parse(tree);
            } catch (e) {
                return null;
            }
        }
        if (Array.isArray(tree)) return tree.filter(isObject);
        return isObject(tree) ? [tree] : null;
    }

    // Every node by its key, in document order, with its parent key, its
    // index among its siblings and its section.
    function index(list) {
        var map = {};
        var order = [];
        var seen = {};

        function walk(n, parent, path, i, section) {
            var id = str(n._id);
            var key;
            if (id === '') {
                key = '#' + path;
            } else {
                seen[id] = (seen[id] || 0) + 1;
                key = seen[id] > 1 ? id + '#' + seen[id] : id;
            }
            var p = props(n);
            var own = (n.type === 'semantic' && SECTION_TAGS.indexOf(str(p.tag).toLowerCase()) !== -1) ? key : section;
            var e = { key: key, id: id, node: n, parent: parent, at: i, section: own, kids: [], seq: order.length };
            map[key] = e;
            order.push(e);
            var children = Array.isArray(n.children) ? n.children : [];
            for (var c = 0; c < children.length; c++) {
                if (!isObject(children[c])) continue;
                var kid = walk(children[c], key, path === '' ? String(c) : path + '.' + c, c, own);
                e.kids.push(kid.key);
            }
            return e;
        }

        for (var t = 0; t < list.length; t++) {
            walk(list[t], '', list.length > 1 ? String(t) : '', t, '');
        }
        // An element outside any section belongs to its block under the top.
        for (var o = 0; o < order.length; o++) {
            if (order[o].section !== '') continue;
            var up = order[o];
            while (up.parent !== '' && map[up.parent].parent !== '') up = map[up.parent];
            order[o].section = up.key;
        }
        return { map: map, order: order };
    }

    function kindOf(n) {
        var p = props(n);
        if (n.type === 'content' && CONTENT_KIND[p.contentType]) return CONTENT_KIND[p.contentType];
        if (n.type === 'semantic') return TAG_KIND[str(p.tag).toLowerCase()] || 'box';
        return TYPE_KIND[n.type] || 'element';
    }

    function fill(texts, code, vars) {
        var t = (texts && typeof texts[code] === 'string' && texts[code] !== '') ? texts[code] : (TEXT[code] || code);
        return t.replace(/\{(\w+)\}/g, function (m, k) {
            return Object.prototype.hasOwnProperty.call(vars, k) ? str(vars[k]) : m;
        });
    }

    function what(n, texts) {
        return fill(texts, 'kind_' + kindOf(n), {});
    }

    function size(e, map) {
        var c = 1;
        for (var i = 0; i < e.kids.length; i++) c += size(map[e.kids[i]], map);
        return c;
    }

    // The keys that kept their order: the longest run of siblings whose old
    // places rise. A sibling outside it was moved; one only pushed along by
    // a move stays in it.
    function keptOrder(keys, oldAt) {
        var n = keys.length;
        var len = [];
        var prev = [];
        var best = -1;
        for (var i = 0; i < n; i++) {
            len[i] = 1;
            prev[i] = -1;
            for (var j = 0; j < i; j++) {
                if (oldAt[keys[j]] < oldAt[keys[i]] && len[j] + 1 > len[i]) {
                    len[i] = len[j] + 1;
                    prev[i] = j;
                }
            }
            if (best === -1 || len[i] > len[best]) best = i;
        }
        var kept = {};
        for (var k = best; k !== -1; k = prev[k]) kept[keys[k]] = true;
        return kept;
    }

    function read(before, after, texts) {
        var b = tops(before);
        var a = tops(after);
        if (a === null || (before !== null && before !== undefined && b === null)) {
            return { state: 'unreadable', changes: [] };
        }
        if (b === null) return { state: 'new', changes: [] };

        var old = index(b);
        var now = index(a);
        var changes = [];

        function add(kind, code, e, side, vars, extra) {
            var n = e.node;
            var p = props(n);
            var v = { what: what(n, texts), name: str(p._label) };
            for (var k in vars) v[k] = vars[k];
            var c = {
                kind: kind, code: code, node: e.id !== '' ? e.id : e.key, key: e.key,
                text: fill(texts, code, v), what: v.what, name: v.name, side: side,
                parent: e.parent === '' ? '' : (side === 'before' ? old : now).map[e.parent].id || e.parent,
                section: (side === 'before' ? old : now).map[e.section].id || e.section,
                count: 1, before: '', after: '', seq: e.seq
            };
            for (var x in extra) c[x] = extra[x];
            changes.push(c);
        }

        // Added and removed: one change at the top of each subtree.
        now.order.forEach(function (e) {
            if (old.map[e.key] || (e.parent !== '' && !old.map[e.parent])) return;
            var p = props(e.node);
            add('added', p._label ? 'added_named' : 'added', e, 'after', {}, { count: size(e, now.map) });
        });
        old.order.forEach(function (e) {
            if (now.map[e.key] || (e.parent !== '' && !now.map[e.parent])) return;
            var p = props(e.node);
            add('removed', p._label ? 'removed_named' : 'removed', e, 'before', {}, { count: size(e, old.map), seq: -1 });
        });

        // Moved: to another parent, or out of the kept order of its siblings.
        now.order.forEach(function (e) {
            var o = old.map[e.key];
            if (!o) return;
            if (o.parent !== e.parent) {
                var to = now.map[e.parent];
                var where = to ? fill(texts, props(to.node)._label ? 'place_named' : 'kind_' + kindOf(to.node), {
                    what: what(to.node, texts), name: str(props(to.node)._label)
                }) : '';
                add('moved', 'moved_into', e, 'both', { where: where }, {});
            }
        });
        now.order.forEach(function (e) {
            var po = old.map[e.key];
            if (!po || e.kids.length < 2) return;
            var keys = e.kids.filter(function (k) { return old.map[k] && old.map[k].parent === po.key; });
            if (keys.length < 2) return;
            var oldAt = {};
            var oldKeys = po.kids.filter(function (k) { return keys.indexOf(k) !== -1; });
            oldKeys.forEach(function (k, i) { oldAt[k] = i; });
            var kept = keptOrder(keys, oldAt);
            keys.forEach(function (k, i) {
                if (kept[k]) return;
                add('moved', i < oldAt[k] ? 'moved_up' : 'moved_down', now.map[k], 'both', {}, {});
            });
        });

        // Prop changes of every element on both sides.
        now.order.forEach(function (e) {
            var o = old.map[e.key];
            if (!o) return;
            var pa = props(o.node);
            var pb = props(e.node);
            var keys = {};
            Object.keys(pa).concat(Object.keys(pb)).forEach(function (k) { keys[k] = true; });
            var found = {};
            if (o.node.type !== e.node.type) found.other = true;
            Object.keys(keys).forEach(function (k) {
                if (EDITOR.indexOf(k) !== -1 || canon(pa[k]) === canon(pb[k])) return;
                if (WORDS.indexOf(k) !== -1) {
                    if (plain(pa[k]) === plain(pb[k])) {
                        found.look = true;
                    } else if (!found.words) {
                        found.words = { before: plain(pa[k]), after: plain(pb[k]) };
                    }
                } else if (k === 'alt') {
                    found.alt = { before: str(pa[k]), after: str(pb[k]) };
                } else if (PICTURE.indexOf(k) !== -1) {
                    if (!found.picture || k === 'src') found.picture = { before: str(pa[k]), after: str(pb[k]) };
                } else if (LINK.indexOf(k) !== -1) {
                    if (!found.link || k === 'href') found.link = { before: str(pa[k]), after: str(pb[k]) };
                } else if (LOOK.indexOf(k) !== -1) {
                    found.look = true;
                } else {
                    found.other = true;
                }
            });
            if (found.picture) add('picture', 'picture', e, 'both', {}, found.picture);
            if (found.words) add('words', 'words', e, 'both', {}, found.words);
            if (found.alt) add('words', 'alt', e, 'both', {}, found.alt);
            if (found.link) add('link', 'link', e, 'both', {}, found.link);
            if (found.look) add('look', 'look', e, 'both', {}, {});
            if (found.other) add('other', 'other', e, 'both', {}, {});
        });

        changes.sort(function (x, y) {
            return (KINDS.indexOf(x.kind) - KINDS.indexOf(y.kind)) || (x.seq - y.seq);
        });
        changes.forEach(function (c) { delete c.seq; });

        return { state: changes.length ? 'changed' : 'same', changes: changes };
    }

    // The list's rows: look and other changes of more than one element are
    // one row each, with their count and the elements they hold.
    function rows(changes, texts) {
        var list = Array.isArray(changes) ? changes : [];
        var counts = {};
        list.forEach(function (c) { counts[c.kind] = (counts[c.kind] || 0) + 1; });
        var out = [];
        var grouped = {};
        list.forEach(function (c) {
            if ((c.kind === 'look' || c.kind === 'other') && counts[c.kind] > 1) {
                if (!grouped[c.kind]) {
                    grouped[c.kind] = {
                        kind: c.kind, code: c.kind + '_rows',
                        text: fill(texts, c.kind + '_rows', { count: counts[c.kind] }),
                        nodes: [], changes: []
                    };
                    out.push(grouped[c.kind]);
                }
                grouped[c.kind].nodes.push(c.node);
                grouped[c.kind].changes.push(c);
                return;
            }
            out.push({ kind: c.kind, code: c.code, text: c.text, nodes: [c.node], changes: [c] });
        });
        return out;
    }

    window.PgCompareChanges = {
        read: read,
        rows: rows,
        kinds: function () { return KINDS.slice(); },
        texts: function () {
            var t = {};
            for (var k in TEXT) t[k] = TEXT[k];
            return t;
        }
    };
})();
