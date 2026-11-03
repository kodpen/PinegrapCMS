/**
 * Pinegrap - Enterprise Website Platform
 *
 * The hotspots of a design for the visual designer's Prototype view
 * (window.PGProtoHotspots): every link a page or a shared component holds,
 * where it sits, and what it leads to. Pure: it reads the trees it is handed
 * (the open tabs, the shared component cache) and the answer of the
 * Prototype map read (api.php designer prototype_map,
 * includes/fn/prototype_map.php), and changes neither.
 *
 * - collect(): the links of one tree. A node's href is its `href` prop, the
 *   `_attrs` href only where the prop is empty (as the renderer prints it);
 *   a button counts only when it is drawn as <a>; links written inside text
 *   and custom HTML count one by one. A Bootstrap toggle (data-bs-toggle)
 *   opens something on its page and is no hotspot. A bound href is drawn
 *   per request and is left out. Shared components, classic menus and
 *   common or designer regions are listed by id or name.
 * - design(): every tab once and every shared component once, saying how
 *   many tabs place it, and the request for the map read.
 * - mark(): each hotspot's mark from that read through the resolver's twin
 *   (window.PGLinkResolve): tab, page, broken, hidden, external, contact,
 *   anchor, with a draft flag.
 * - layout(): where the frames, rows, chips and arrows of the view go.
 * - drawable(), hrefTo(), hrefPatch(): an arrow drawn by hand: which rows
 *   may get one, the href its target gives, and what it writes into the
 *   row's node (a copy of the prop that changes; null for a stale map).
 *
 * Nothing runs at load but the definition of window.PGProtoHotspots.
 * Served minified as assets/js/pg_proto_hotspots.min.js (pg_js_bundles(),
 * includes/fn/output.php); after editing run `php tools/build_js.php`.
 * tests/proto_hotspots_test.php runs it in Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // The limits of the map read (pg_proto_map_limits()).
    var LIMITS = { hrefs: 2000, menus: 50, regions: 100, length: 2048 };

    var CONTACT = { mailto: true, tel: true, sms: true };
    var ENTITIES = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ' };

    function has(object, key) {
        return !!object && Object.prototype.hasOwnProperty.call(object, key);
    }

    function isObject(value) {
        return !!value && (typeof value === 'object') && !Array.isArray(value);
    }

    function str(value) {
        return (typeof value === 'string') ? value : '';
    }

    function propsOf(node) {
        return (node && isObject(node.props)) ? node.props : {};
    }

    function attrOf(props, name) {
        var attrs = Array.isArray(props._attrs) ? props._attrs : [];

        for (var i = 0; i < attrs.length; i++) {
            if (attrs[i] && (String(attrs[i].name || '').toLowerCase() === name)) {
                return String((attrs[i].value === undefined || attrs[i].value === null) ? '' : attrs[i].value);
            }
        }

        return null;
    }

    // Character references as a browser reads them in an attribute value:
    // numeric ones and the few named ones markup in a page uses.
    function decode(text) {
        return String(text).replace(/&(#[xX][0-9a-fA-F]+|#[0-9]+|[a-zA-Z]+);/g, function (all, ref) {
            if (ref.charAt(0) === '#') {
                var code = (ref.charAt(1) === 'x' || ref.charAt(1) === 'X') ? parseInt(ref.substr(2), 16) : parseInt(ref.substr(1), 10);

                return (code > 0 && code <= 0x10FFFF && !(code >= 0xD800 && code <= 0xDFFF)) ? String.fromCodePoint(code) : all;
            }

            return has(ENTITIES, ref.toLowerCase()) && (ref === ref.toLowerCase()) ? ENTITIES[ref] : all;
        });
    }

    function plainText(html) {
        return decode(String(html).replace(/<[^>]*>/g, ' ')).replace(/\s+/g, ' ').trim();
    }

    // The attributes of one start tag as the browser tokenizes them, from
    // just after its name; answers the attributes and where the tag ends
    // (after its ">"), or null when it never closes. `where` (optional)
    // gets each attribute's value span, quotes included ({ start, end }).
    function readTag(html, at, where) {
        var attrs = {};
        var n = html.length;
        var i = at;

        while (i < n) {
            var c = html.charAt(i);

            if (c === '>') {
                return { attrs: attrs, end: i + 1 };
            }

            if (/[\s\/]/.test(c)) {
                i++;
                continue;
            }

            var start = i;

            // A name may start with "=" and runs to a space, "/", ">" or "=".
            i++;

            while (i < n && !/[\s\/>=]/.test(html.charAt(i))) {
                i++;
            }

            var name = html.substring(start, i).toLowerCase();
            var value = null;
            var span = null;
            var j = i;

            while (j < n && /\s/.test(html.charAt(j))) {
                j++;
            }

            if (html.charAt(j) === '=') {
                j++;

                while (j < n && /\s/.test(html.charAt(j))) {
                    j++;
                }

                var q = html.charAt(j);

                if (q === '"' || q === "'") {
                    var close = html.indexOf(q, j + 1);

                    if (close < 0) {
                        return null;
                    }

                    value = html.substring(j + 1, close);
                    span = { start: j, end: close + 1 };
                    i = close + 1;
                } else if (q === '>' || j >= n) {
                    value = '';
                    span = { start: j, end: j };
                    i = j;
                } else {
                    var k = j;

                    while (k < n && !/[\s>]/.test(html.charAt(k))) {
                        k++;
                    }

                    value = html.substring(j, k);
                    span = { start: j, end: k };
                    i = k;
                }
            }

            if (!has(attrs, name)) {
                attrs[name] = (value === null) ? null : decode(value);

                if (where) {
                    where[name] = span;
                }
            }
        }

        return null;
    }

    // The links of a piece of markup in order, outside scripts, styles, text
    // areas and comments (pg_proto_html_hrefs() answers the same hrefs):
    // href, toggle (its data-bs-toggle, '' for none) and the words it shows.
    // `spans` (optional) gets where each link's href value sits, in order.
    function htmlLinks(html, spans) {
        html = String(html || '');

        var out = [];
        var lower = html.toLowerCase();
        var n = html.length;
        var i = 0;

        while (i < n) {
            var lt = html.indexOf('<', i);

            if (lt < 0) {
                break;
            }

            if (html.substr(lt, 4) === '<!--') {
                var shut = html.indexOf('-->', lt + 4);
                i = (shut < 0) ? n : shut + 3;
                continue;
            }

            var m = /^<(\/?[a-zA-Z][^\s\/>]*)/.exec(html.substr(lt, 256));

            if (!m) {
                i = lt + 1;
                continue;
            }

            var tagName = m[1].toLowerCase();
            var where = {};
            var tag = readTag(html, lt + m[0].length, where);

            if (!tag) {
                i = lt + 1;
                continue;
            }

            if (tagName === 'script' || tagName === 'style' || tagName === 'textarea') {
                var endTag = new RegExp('</' + tagName + '\\s*>', 'g');
                endTag.lastIndex = tag.end;

                var found = endTag.exec(lower);

                if (found) {
                    i = found.index + found[0].length;
                    continue;
                }
            }

            if (tagName === 'a' && has(tag.attrs, 'href') && tag.attrs.href !== null) {
                var closeAt = lower.indexOf('</a', tag.end);

                out.push({
                    href: tag.attrs.href,
                    toggle: has(tag.attrs, 'data-bs-toggle') ? String(tag.attrs['data-bs-toggle'] || '').trim() : '',
                    target: has(tag.attrs, 'target') ? String(tag.attrs.target || '') : '',
                    label: plainText(html.substring(tag.end, (closeAt < 0) ? n : closeAt))
                });

                if (spans) {
                    spans.push(where.href);
                }
            }

            i = tag.end;
        }

        return out;
    }

    function nodeLabel(props) {
        var text = plainText(str(props.text));

        return text || str(props.ariaLabel) || str(attrOf(props, 'aria-label')) || str(props.alt);
    }

    // The links of one node itself (its children not included).
    function nodeLinks(node) {
        var type = str(node && node.type);
        var props = propsOf(node);
        var bindings = isObject(props._bindings) ? props._bindings : {};
        var out = [];
        var base = { nodeId: String(node && node._id !== undefined ? node._id : '') };

        function add(source, href, label, index, toggle, target) {
            if (toggle) {
                return;
            }

            out.push({ nodeId: base.nodeId, source: source, href: href, label: label, index: index, target: target });
        }

        var toggle = String(attrOf(props, 'data-bs-toggle') || '').trim();
        var target = str(props.target) || str(attrOf(props, 'target'));

        if (!bindings.href) {
            if (type === 'content' && props.contentType === 'link') {
                // The renderer prints '#' for an empty address.
                var fromAttrs = (str(props.href) === '') && (str(attrOf(props, 'href')) !== '');

                add(fromAttrs ? 'attrs' : 'href', (fromAttrs ? attrOf(props, 'href') : str(props.href)) || '#', nodeLabel(props), 0, toggle, target);
            } else if (type === 'component' && props.componentType === 'btn') {
                if ((str(props.btnElement) || 'a') === 'a') {
                    add('btn', str(props.href) || '#', nodeLabel(props), 0, toggle, target);
                }
            } else if (type === 'semantic' && str(props.tag).toLowerCase() === 'a') {
                var own = str(props.href);
                var written = attrOf(props, 'href');

                if (own !== '') {
                    add('href', own, nodeLabel(props), 0, toggle, target);
                } else if (written !== null) {
                    add('attrs', written, nodeLabel(props), 0, toggle, target);
                }
            }
        }

        // Links written inside text or custom HTML, one hotspot each.
        var markup = '';

        if (type === 'content' && props.contentType === 'custom_html') {
            markup = bindings.html ? '' : str(props.html);
        } else if ((type === 'content' || type === 'semantic') && props.contentType !== 'link') {
            markup = bindings.text ? '' : str(props.text);
        }

        if (markup.indexOf('<') >= 0) {
            htmlLinks(markup).forEach(function (link, i) {
                add('text', link.href, link.label, i, link.toggle, link.target);
            });
        }

        return out;
    }

    function addOnce(list, value) {
        if (value !== '' && list.indexOf(value) < 0) {
            list.push(value);
        }
    }

    // The links of a tree in document order; shared components, classic
    // menus and common or designer regions it places, each once.
    function collect(tree) {
        var out = { hotspots: [], shared: [], menus: [], regions: [] };

        (function walk(node) {
            if (!isObject(node)) {
                return;
            }

            var props = propsOf(node);

            if (node.type === 'shared_ref') {
                var sid = parseInt(props.sharedId, 10);

                if (sid > 0 && out.shared.indexOf(sid) < 0) {
                    out.shared.push(sid);
                }

                return;
            }

            if (node.type === 'region') {
                var name = str(props.regionName).trim();
                var kind = str(props.regionType);

                if (name !== '' && (kind === 'menu' || kind === 'menu_sequence')) {
                    addOnce(out.menus, name);
                    out.hotspots.push({ nodeId: String(node._id !== undefined ? node._id : ''), source: 'menu', name: name });
                } else if (name !== '' && (kind === 'common' || kind === 'designer')) {
                    addOnce(out.regions, name);
                    out.hotspots.push({ nodeId: String(node._id !== undefined ? node._id : ''), source: 'region', name: name });
                }
            }

            out.hotspots = out.hotspots.concat(nodeLinks(node));

            (Array.isArray(node.children) ? node.children : []).forEach(walk);
        })(tree);

        return out;
    }

    // ── Thumbnails ──

    // At most this many bands in a sketch; the rest are joined into the last.
    var SKETCH_MAX = 12;

    // What a top-level node of a tree stands for in a frame's thumbnail.
    function sketchRole(node) {
        var props = propsOf(node);
        var tag = str(props.tag).toLowerCase();
        var component = str(props.componentType).toLowerCase();

        if (node.type === 'shared_ref') {
            return 'shared';
        }

        if (node.type === 'region') {
            return 'region';
        }

        if (tag === 'nav' || component.indexOf('navbar') === 0) {
            return 'nav';
        }

        if (tag === 'header') {
            return 'header';
        }

        if (tag === 'footer' || component.indexOf('footer') === 0) {
            return 'footer';
        }

        return 'block';
    }

    function sketchWeight(node) {
        var count = 0;

        (function walk(n) {
            if (!isObject(n)) {
                return;
            }

            count++;

            if (n.type !== 'shared_ref') {
                (Array.isArray(n.children) ? n.children : []).forEach(walk);
            }
        })(node);

        return count;
    }

    // The bands of a page's thumbnail, top to bottom: one per top-level node
    // (through a lone wrapper the page sits in), each { role, weight (nodes
    // in it), links (hotspots in it) }. Roles: nav, header, footer, shared,
    // region, block. Reads the tree only; null for no tree.
    function sketch(tree) {
        if (!isObject(tree)) {
            return null;
        }

        var top = Array.isArray(tree.children) ? tree.children : [];

        // A page held in one plain wrapper is drawn by the wrapper's children.
        for (var depth = 0; depth < 2 && top.length === 1 && isObject(top[0]) && sketchRole(top[0]) === 'block' && Array.isArray(top[0].children) && top[0].children.length > 1; depth++) {
            top = top[0].children;
        }

        var bands = [];

        top.forEach(function (node) {
            if (!isObject(node)) {
                return;
            }

            var band = { role: sketchRole(node), weight: sketchWeight(node), links: collect(node).hotspots.length };

            if (bands.length < SKETCH_MAX) {
                bands.push(band);
                return;
            }

            var last = bands[SKETCH_MAX - 1];

            last.role = 'block';
            last.weight += band.weight;
            last.links += band.links;
        });

        return bands;
    }

    // Where the bands of a sketch go in a w x h box: heights by the square
    // root of their weight, each at least 3 high, 2 apart; each { y, h,
    // role, links }, whole units.
    function sketchBoxes(bands, w, h) {
        var list = Array.isArray(bands) ? bands : [];
        var gap = 2;
        var min = 3;

        if (!list.length) {
            return [];
        }

        var room = Math.max(0, h - gap * (list.length - 1) - min * list.length);
        var total = 0;

        list.forEach(function (band) { total += Math.sqrt(Math.max(1, band.weight)); });

        var out = [];
        var y = 0;
        var used = 0;

        list.forEach(function (band, i) {
            used += Math.sqrt(Math.max(1, band.weight));

            var end = (i === list.length - 1) ? h : Math.round(min * (i + 1) + gap * i + room * used / total);
            var height = Math.max(min, end - y);

            out.push({ y: y, h: height, role: band.role, links: band.links });
            y += height + gap;
        });

        return out;
    }

    // Every tab once, every shared component once (from `shared`, id =>
    // { name, tree }, the designer's _sharedCache; one not fetched yet
    // gets no hotspots and loaded: false), and the request for the map
    // read. `pages` are the designer's tabs: key, page_id, page_name and
    // their tree (the open tab's live tree handed in by the caller).
    function design(pages, shared) {
        shared = isObject(shared) ? shared : {};

        var out = { pages: [], shared: [], request: { hrefs: [], menus: [], regions: [] } };
        var byId = {};
        var reach = {};

        function take(found) {
            found.hotspots.forEach(function (spot) {
                if (spot.href !== undefined && String(spot.href).length <= LIMITS.length) {
                    addOnce(out.request.hrefs, String(spot.href));
                }
            });
            found.menus.forEach(function (name) { addOnce(out.request.menus, name); });
            found.regions.forEach(function (name) { addOnce(out.request.regions, name); });
        }

        // A shared component placed in another reaches the pages of that one.
        function frame(sid, pageKey, path) {
            if (path.indexOf(sid) >= 0) {
                return;
            }

            if (!has(byId, sid)) {
                var entry = shared[sid] || shared[String(sid)] || null;
                var tree = (entry && entry.tree) ? entry.tree : null;
                var found = tree ? collect(tree) : { hotspots: [], shared: [], menus: [], regions: [] };

                byId[sid] = { id: sid, name: entry ? str(entry.name) : '', loaded: !!tree, pages: 0, hotspots: found.hotspots, sharedIn: found.shared, sketch: tree ? sketch(tree) : null };
                reach[sid] = [];
                out.shared.push(byId[sid]);
                take(found);
            }

            if (reach[sid].indexOf(pageKey) < 0) {
                reach[sid].push(pageKey);
                byId[sid].pages = reach[sid].length;
            }

            byId[sid].sharedIn.forEach(function (inner) {
                frame(inner, pageKey, path.concat([sid]));
            });
        }

        (Array.isArray(pages) ? pages : []).forEach(function (page) {
            if (!isObject(page)) {
                return;
            }

            var found = collect(page.tree);
            var key = String(page.key !== undefined ? page.key : '');

            out.pages.push({ key: key, pageId: parseInt(page.page_id, 10) || 0, name: str(page.page_name), hotspots: found.hotspots, shared: found.shared, sketch: sketch(page.tree) });
            take(found);
            found.shared.forEach(function (sid) { frame(sid, key, []); });
        });

        out.shared.forEach(function (one) {
            one.shared = one.sharedIn;
            delete one.sharedIn;
        });

        out.request.hrefs = out.request.hrefs.slice(0, LIMITS.hrefs);
        out.request.menus = out.request.menus.slice(0, LIMITS.menus);
        out.request.regions = out.request.regions.slice(0, LIMITS.regions);

        return out;
    }

    function lowerName(text) {
        return String(text).toLowerCase();
    }

    function pageById(maps, id) {
        var pages = (maps && isObject(maps.pages)) ? maps.pages : {};
        var names = Object.keys(pages);

        for (var i = 0; i < names.length; i++) {
            if ((parseInt(pages[names[i]].id, 10) || 0) === id) {
                return pages[names[i]];
            }
        }

        return null;
    }

    function inList(list, value) {
        return (Array.isArray(list) ? list : []).some(function (one) {
            return (parseInt(one, 10) || 0) === value;
        });
    }

    // The mark of one href: mark (tab, page, broken, hidden, external,
    // contact, anchor), draft, the resolver's type and class, the page id it
    // reaches and the tab key it opens ('' for none). `read` is the map
    // read's answer; `tabs` the designer's tabs (key, page_id, page_name).
    function markHref(href, read, tabs, resolver) {
        var R = resolver || (typeof window !== 'undefined' ? window.PGLinkResolve : null);
        var env = (read && read.env) || {};
        var maps = (read && read.maps) || {};
        var forms = (read && read.form_names) || {};
        var answer = R.resolve(href, maps, env, forms);
        var parsed = R.parse(href, env);
        var item = (parsed.result === null) ? parsed.item : '';
        var out = { href: String(href), mark: '', draft: false, type: answer.type, 'class': answer['class'], pageId: 0, tab: '' };
        var list = Array.isArray(tabs) ? tabs : [];

        if (answer.type === 'external') {
            out.mark = CONTACT[answer['class']] ? 'contact' : ((answer['class'] === 'anchor') ? 'anchor' : 'external');
            return out;
        }

        if (answer.type === 'page') {
            var page = pageById(maps, answer.id);
            var folder = page ? (parseInt(page.folder, 10) || 0) : 0;

            out.pageId = answer.id;

            if (folder && inList(read && read.bin_folders, folder)) {
                out.mark = 'broken';
                return out;
            }

            out.draft = !!folder && folder === (parseInt(read && read.draft_folder, 10) || 0);
        }

        // A tab answers for its saved page, and by name for one not saved
        // yet, as the Preview plays it.
        for (var i = 0; i < list.length; i++) {
            var tabId = parseInt(list[i].page_id, 10) || 0;
            var tabName = str(list[i].page_name);
            var named = (item !== '') && (tabName !== '') && (lowerName(item.split('/')[0]) === lowerName(tabName));

            if ((out.pageId && tabId === out.pageId) || (!out.pageId && answer.type === 'unknown' && named)) {
                out.mark = 'tab';
                out.tab = String(list[i].key);
                return out;
            }
        }

        if (answer.type === 'unknown') {
            var hidden = (read && Array.isArray(read.hidden)) ? read.hidden : [];

            out.mark = (item !== '' && hidden.some(function (name) { return lowerName(name) === lowerName(item); })) ? 'hidden' : 'broken';
            return out;
        }

        out.mark = 'page';

        return out;
    }

    // The design of design() with every hotspot marked; a menu or region
    // hotspot gets its links (`links`, each marked; null when the read
    // knows no menu or region by that name). Answers a copy.
    function mark(designed, read, tabs, resolver) {
        var menus = (read && isObject(read.menus)) ? read.menus : {};
        var regions = (read && isObject(read.regions)) ? read.regions : {};

        function one(spot) {
            var copy = {};

            Object.keys(spot).forEach(function (key) { copy[key] = spot[key]; });

            if (spot.source === 'menu') {
                var links = has(menus, spot.name) ? menus[spot.name] : null;

                copy.links = Array.isArray(links) ? links.map(function (link) {
                    var marked = (link.hidden) ? { href: '', mark: 'hidden', draft: false, type: 'unknown', 'class': 'unknown', pageId: 0, tab: '' } : markHref(link.href, read, tabs, resolver);

                    marked.label = str(link.name);
                    marked.menuItemId = parseInt(link.id, 10) || 0;
                    marked.parent = parseInt(link.parent, 10) || 0;
                    marked.target = str(link.target);

                    return marked;
                }) : null;
            } else if (spot.source === 'region') {
                var region = has(regions, spot.name) ? regions[spot.name] : null;

                copy.links = (region && region.kind) ? (region.hrefs || []).map(function (href) { return markHref(href, read, tabs, resolver); }) : null;
            } else {
                var marked = markHref(spot.href, read, tabs, resolver);

                ['mark', 'draft', 'type', 'class', 'pageId', 'tab'].forEach(function (key) { copy[key] = marked[key]; });
            }

            return copy;
        }

        function frames(list) {
            return list.map(function (frame) {
                var copy = {};

                Object.keys(frame).forEach(function (key) { copy[key] = frame[key]; });
                copy.hotspots = frame.hotspots.map(one);

                return copy;
            });
        }

        return { pages: frames(designed.pages || []), shared: frames(designed.shared || []), request: designed.request };
    }

    // The sizes of what layout() places, in canvas units.
    var FRAME = { w: 248, head: 44, thumb: 84, row: 30, pad: 10, gap: 160, chipW: 200, chipH: 36, chipGap: 12 };

    // The marks that end on a chip, in the order the chips are stacked.
    var CHIP_MARKS = ['page', 'anchor', 'contact', 'external', 'hidden', 'broken'];

    // The rows of one frame: one per link hotspot; a menu or region gives
    // one row per link it holds, or one row marked broken when the read
    // knows no such name. Each row: port, nodeId, source, label, href,
    // mark, draft, tab, from (the menu or region name, '' for none).
    function rowsOf(frame) {
        var rows = [];

        function add(spot, link, from) {
            rows.push({
                port: 'h' + rows.length,
                nodeId: spot.nodeId,
                source: spot.source,
                index: parseInt(spot.index, 10) || 0,
                label: str(link.label) || str(link.href) || '#',
                href: str(link.href),
                mark: str(link.mark) || 'broken',
                draft: !!link.draft,
                tab: str(link.tab),
                from: from
            });
        }

        (frame.hotspots || []).forEach(function (spot) {
            if (spot.source === 'menu' || spot.source === 'region') {
                if (!Array.isArray(spot.links)) {
                    add(spot, { label: spot.name, href: '', mark: 'broken' }, spot.name);
                    return;
                }

                spot.links.forEach(function (link) { add(spot, link, spot.name); });
                return;
            }

            add(spot, spot, '');
        });

        return rows;
    }

    // Where everything of a marked design goes on the canvas and which
    // arrows join it: frames (id 'p:<tab key>' or 's:<shared id>') side by
    // side, each as tall as its rows; one chip (id 'c:<n>') per mark and
    // href that ends outside the design's tabs, stacked right of the
    // frames; an arrow (id '<frame id>|<port>') from each row to the tab
    // frame it opens or to its chip. `saved` (id => { x, y }) puts a frame
    // or chip back where the operator left it.
    function layout(marked, saved) {
        saved = isObject(saved) ? saved : {};

        var frames = [];
        var chips = [];
        var chipByKey = {};
        var edges = [];
        var tabs = {};
        var x = 0;

        function place(item) {
            var at = saved[item.id];

            if (isObject(at) && isFinite(at.x) && isFinite(at.y)) {
                item.x = Number(at.x);
                item.y = Number(at.y);
            }

            return item;
        }

        (marked.pages || []).forEach(function (page) {
            tabs[page.key] = 'p:' + page.key;
        });

        [['page', marked.pages || []], ['shared', marked.shared || []]].forEach(function (group) {
            group[1].forEach(function (frame) {
                var rows = rowsOf(frame);
                var bands = Array.isArray(frame.sketch) ? frame.sketch : [];
                var thumb = bands.length ? FRAME.thumb : 0;
                var item = {
                    id: (group[0] === 'page') ? 'p:' + frame.key : 's:' + frame.id,
                    kind: group[0],
                    key: (group[0] === 'page') ? frame.key : '',
                    sharedId: (group[0] === 'shared') ? frame.id : 0,
                    name: frame.name,
                    pages: (group[0] === 'shared') ? frame.pages : 0,
                    loaded: (group[0] === 'shared') ? frame.loaded : true,
                    rows: rows,
                    sketch: bands,
                    thumb: thumb,
                    x: x,
                    y: 0,
                    w: FRAME.w,
                    h: FRAME.head + thumb + Math.max(1, rows.length) * FRAME.row + FRAME.pad
                };

                x += FRAME.w + FRAME.gap;
                frames.push(place(item));
            });
        });

        frames.forEach(function (frame) {
            frame.rows.forEach(function (row) {
                var to = '';

                if (row.mark === 'tab' && has(tabs, row.tab)) {
                    to = tabs[row.tab];
                } else if (CHIP_MARKS.indexOf(row.mark) >= 0 && !(row.from && row.href === '')) {
                    var key = row.mark + '\n' + row.href;

                    if (!has(chipByKey, key)) {
                        chipByKey[key] = { id: 'c:' + chips.length, kind: 'chip', mark: row.mark, href: row.href, draft: row.draft, x: 0, y: 0, w: FRAME.chipW, h: FRAME.chipH };
                        chips.push(chipByKey[key]);
                    }

                    to = chipByKey[key].id;
                }

                if (to !== '') {
                    edges.push({ id: frame.id + '|' + row.port, from: frame.id, port: row.port, to: to, mark: row.mark });
                }
            });
        });

        // Chips grouped by mark, in CHIP_MARKS order, then as first met.
        chips.sort(function (a, b) {
            return (CHIP_MARKS.indexOf(a.mark) - CHIP_MARKS.indexOf(b.mark)) || (parseInt(a.id.substr(2), 10) - parseInt(b.id.substr(2), 10));
        });
        chips.forEach(function (chip, i) {
            chip.x = x;
            chip.y = i * (FRAME.chipH + FRAME.chipGap);
            place(chip);
        });

        return { frames: frames, chips: chips, edges: edges };
    }

    // The y of a row's port from the top of its frame.
    function rowY(frame, port) {
        var index = parseInt(String(port).replace(/^h/, ''), 10) || 0;

        return FRAME.head + ((frame && frame.thumb) || 0) + index * FRAME.row + FRAME.row / 2;
    }

    // ── Drawing an arrow ──

    // The marks of the chips a link may be pointed at: what the href of
    // such a chip means does not depend on the page it is written on.
    var DRAW_CHIPS = { page: true, external: true, contact: true };

    // Whether an arrow may be drawn from a row: a link of a tree. Classic
    // menus, legacy regions and widget page settings stay read-only here.
    function drawable(row) {
        return !!row && !row.from && (row.source === 'href' || row.source === 'attrs' || row.source === 'btn' || row.source === 'text');
    }

    // The href an arrow ending on `target` writes, null where it may not
    // end: a tab's frame gives its page name under the site path of the
    // map read (`env.path`, '/' when it names none), a chip of a site page,
    // another site or a contact address its own href.
    function hrefTo(target, env) {
        if (!isObject(target)) {
            return null;
        }

        if (target.kind === 'page') {
            var name = str(target.name).trim();
            var path = (isObject(env) && typeof env.path === 'string' && env.path !== '') ? env.path : '/';

            return (name === '') ? null : path + name.split('/').map(encodeURIComponent).join('/');
        }

        if (target.kind === 'chip' && DRAW_CHIPS[target.mark] && str(target.href) !== '') {
            return str(target.href);
        }

        return null;
    }

    // What an arrow from a link row may end on, as the keyboard menu lists
    // it: every item of a layout (tab frames, then chips) that gives an
    // href, each { id, href }, an href once (the first item giving it).
    function drawTargets(laid, env) {
        var out = [];
        var seen = {};

        ((laid && laid.frames) || []).concat((laid && laid.chips) || []).forEach(function (item) {
            var href = hrefTo(item, env);

            if (href !== null && !has(seen, href)) {
                seen[href] = true;
                out.push({ id: item.id, href: href });
            }
        });

        return out;
    }

    function attrValue(value) {
        return '"' + String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;') + '"';
    }

    // The markup with the href of its nth link (htmlLinks() order) set to
    // `href`, written in double quotes; the rest stays as it was. Null when
    // the markup has no such link.
    function setHtmlHref(html, index, href) {
        html = String(html || '');

        var spans = [];

        htmlLinks(html, spans);

        if (!spans[index]) {
            return null;
        }

        return html.substring(0, spans[index].start) + attrValue(href) + html.substring(spans[index].end);
    }

    // What an arrow from `row` to `href` writes into the row's node, as
    // { key, value } of its props (a copy, the node is not changed), or
    // null when the node no longer holds the href the map showed (or the
    // row is not drawable).
    function hrefPatch(node, row, href) {
        if (!drawable(row) || !isObject(node)) {
            return null;
        }

        var props = propsOf(node);
        var index = parseInt(row.index, 10) || 0;
        var now = nodeLinks(node).filter(function (spot) {
            return spot.source === row.source && (spot.index === index);
        })[0];

        if (!now || now.href !== str(row.href)) {
            return null;
        }

        if (row.source === 'attrs') {
            return {
                key: '_attrs',
                value: props._attrs.map(function (attr) {
                    var copy = {};

                    Object.keys(attr || {}).forEach(function (k) { copy[k] = attr[k]; });

                    if (String(copy.name || '').toLowerCase() === 'href') {
                        copy.value = href;
                    }

                    return copy;
                })
            };
        }

        if (row.source === 'text') {
            var key = (node.type === 'content' && props.contentType === 'custom_html') ? 'html' : 'text';
            var markup = setHtmlHref(props[key], index, href);

            return (markup === null) ? null : { key: key, value: markup };
        }

        return { key: 'href', value: href };
    }

    // The key a frame's place is shared under in the design's tab layout
    // (style.style_tab_layout `proto`): 'p<page_id>' for a saved tab's
    // frame, 's<id>' for a shared component's; '' for a chip or an unsaved
    // tab, whose places stay in the browser.
    function placeKey(id) {
        var m = /^(?:p:p|s:)([1-9][0-9]{0,9})$/.exec(str(id));

        return m ? (id.charAt(0) + m[1]) : '';
    }

    // The shared places as layout()'s `saved` (frame id => { x, y }). Twin
    // of pg_designer_tab_layout_proto(): keys 'p<page_id>' or 's<id>',
    // whole units within +-1,000,000, at most 500; the server also drops
    // pages that are not the design's.
    function places(proto) {
        var out = {};
        var count = 0;

        if (!isObject(proto)) {
            return out;
        }

        Object.keys(proto).forEach(function (key) {
            var m = /^([ps])([1-9][0-9]{0,9})$/.exec(key);
            var at = proto[key];

            if (count >= 500 || !m || !isObject(at)) {
                return;
            }

            var pos = {};
            var ok = ['x', 'y'].every(function (axis) {
                var v = at[axis];

                if ((typeof v !== 'number' && typeof v !== 'string') || String(v).trim() === '' || !isFinite(Number(v))) {
                    return false;
                }

                pos[axis] = Math.max(-1000000, Math.min(1000000, Math.round(Number(v))));

                return true;
            });

            if (ok) {
                out[(m[1] === 'p' ? 'p:p' : 's:') + m[2]] = pos;
                count++;
            }
        });

        return out;
    }

    window.PGProtoHotspots = {
        FRAME: FRAME,
        layout: layout,
        sketch: sketch,
        sketchBoxes: sketchBoxes,
        placeKey: placeKey,
        places: places,
        rowY: rowY,
        LIMITS: LIMITS,
        htmlLinks: htmlLinks,
        nodeLinks: nodeLinks,
        collect: collect,
        design: design,
        markHref: markHref,
        mark: mark,
        drawable: drawable,
        hrefTo: hrefTo,
        setHtmlHref: setHtmlHref,
        hrefPatch: hrefPatch,
        drawTargets: drawTargets
    };
})();
