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

    // The page a link kept by page id opens (_bindParams.href.page_id of a
    // '__page_link' href): the id as digits, 'tab:<key>' for a tab not
    // saved yet, '' for none (the typed href is then what it opens).
    function boundPage(props) {
        var spec = (isObject(props._bindParams) && isObject(props._bindParams.href)) ? props._bindParams.href : {};
        var id = spec.page_id;

        if (typeof id === 'number' && isFinite(id) && id > 0 && Math.floor(id) === id) {
            return String(id);
        }

        id = str(id);

        return (/^[1-9][0-9]{0,9}$/.test(id) || /^tab:./.test(id)) ? id : '';
    }

    // Whether a link outside a widget may be kept by page id ('__page_link',
    // the Bind Data panel's page choice): a link of its own the site draws
    // as <a> (a content link, a btn drawn as <a>, a semantic a), whatever its
    // href holds or is bound to now; never a Bootstrap toggle.
    function pageLinkable(node) {
        if (!isObject(node)) {
            return false;
        }

        var type = str(node.type);
        var props = propsOf(node);

        if (String(attrOf(props, 'data-bs-toggle') || '').trim() !== '') {
            return false;
        }

        return (type === 'content' && props.contentType === 'link')
            || (type === 'component' && props.componentType === 'btn' && (str(props.btnElement) || 'a') === 'a')
            || (type === 'semantic' && str(props.tag).toLowerCase() === 'a');
    }

    // The links of one node itself (its children not included). A link kept
    // by page id ('__page_link') carries `bound` (boundPage()) and
    // `pageLink`; its href is the typed one, the fallback.
    function nodeLinks(node) {
        var type = str(node && node.type);
        var props = propsOf(node);
        var bindings = isObject(props._bindings) ? props._bindings : {};
        var pageLink = (bindings.href === '__page_link');
        var out = [];
        var base = { nodeId: String(node && node._id !== undefined ? node._id : '') };

        function add(source, href, label, index, toggle, target) {
            if (toggle) {
                return;
            }

            var spot = { nodeId: base.nodeId, source: source, href: href, label: label, index: index, target: target };

            if (pageLink && source !== 'text') {
                spot.bound = boundPage(props);
                spot.pageLink = true;
            }

            out.push(spot);
        }

        var toggle = String(attrOf(props, 'data-bs-toggle') || '').trim();
        var target = str(props.target) || str(attrOf(props, 'target'));

        if (!bindings.href || pageLink) {
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

        var out = { pages: [], shared: [], request: { hrefs: [], menus: [], regions: [], page_ids: [] } };
        var byId = {};
        var reach = {};

        function take(found) {
            found.hotspots.forEach(function (spot) {
                if (/^[1-9][0-9]*$/.test(str(spot.bound))) {
                    addOnce(out.request.page_ids, parseInt(spot.bound, 10));
                } else if (spot.href !== undefined && String(spot.href).length <= LIMITS.length) {
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
        out.request.page_ids = out.request.page_ids.slice(0, LIMITS.hrefs);

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

    function nameById(maps, id) {
        var pages = (maps && isObject(maps.pages)) ? maps.pages : {};
        var names = Object.keys(pages);

        for (var i = 0; i < names.length; i++) {
            if ((parseInt(pages[names[i]].id, 10) || 0) === id) {
                return names[i];
            }
        }

        return '';
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

    // The mark of a link kept by page id (spot.bound): the tab of that page
    // or key, else the page by its current name in the map read
    // (markHref() of its address, so a binned or draft page is marked as
    // one), hidden when the read lists the id in `hidden_ids`, else broken.
    // `href` is the current address; a hidden or broken one keeps the typed
    // href, which the site prints.
    function markBound(spot, read, tabs, resolver) {
        var bound = str(spot.bound);
        var env = (read && read.env) || {};
        var list = Array.isArray(tabs) ? tabs : [];
        var key = /^tab:/.test(bound) ? bound.substr(4) : null;
        var id = (key === null) ? (parseInt(bound, 10) || 0) : 0;
        var out = { href: str(spot.href), mark: 'broken', draft: false, type: 'unknown', 'class': 'unknown', pageId: id, tab: '' };

        for (var i = 0; i < list.length; i++) {
            var tabId = parseInt(list[i].page_id, 10) || 0;

            if ((key !== null && String(list[i].key) === key) || (id && tabId === id)) {
                var at = hrefTo({ kind: 'page', name: list[i].page_name }, env);

                out.href = (at === null) ? out.href : at;
                out.mark = 'tab';
                out.type = 'page';
                out['class'] = 'page';
                out.tab = String(list[i].key);
                out.pageId = tabId;

                return out;
            }
        }

        var name = id ? nameById((read && read.maps) || {}, id) : '';

        if (name !== '') {
            var marked = markHref(hrefTo({ kind: 'page', name: name }, env), read, [], resolver);

            marked.pageId = id;

            return marked;
        }

        if (id && inList(read && read.hidden_ids, id)) {
            out.mark = 'hidden';
        }

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
            } else if (str(spot.bound) !== '') {
                var bound = markBound(spot, read, tabs, resolver);

                ['href', 'mark', 'draft', 'type', 'class', 'pageId', 'tab'].forEach(function (key) { copy[key] = bound[key]; });
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
    // `pic`: a page's picture, drawn 1280 wide and cut 1600 high, scaled to
    // `inner` units inside a box with the thumbnail's padding and border.
    var FRAME = { w: 248, head: 44, thumb: 84, row: 30, pad: 10, chipW: 200, chipH: 36, chipGap: 12, pic: { page: 1280, cut: 1600, inner: 228, edge: 13 } };

    // How layout() arranges the frames: eight a row, the gap between frames
    // and between rows, a section heading's height, and the gap between a
    // side column (shared parts, chips) and the grid.
    var GRID = { cols: 8, gap: 96, head: 40, side: 160 };

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
                bound: str(link.bound),
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
    // arrows join it: frames (id 'p:<tab key>' or 's:<shared id>'), each as
    // tall as its rows; one chip (id 'c:<n>') per mark and href that ends
    // outside the design's tabs; an arrow (id '<frame id>|<port>') from
    // each row to the tab frame it opens or to its chip. The places come
    // from the graph canvas core's arrange() (window.PGGraphCanvas, read
    // when called): the tab frames in a grid of bands, one per section of
    // opts.sections ([{ id, label, keys }], the tab groups in the strip's
    // order), the tabs in none under opts.rest (first when the first tab is
    // one of them, else last), one unlabelled band without sections; inside a band the flow from the first tab (the
    // start, breadth-first over the tab-to-tab arrows, the strip's order
    // between equals, the unreached last). The shared frames stand in a
    // column before the grid under opts.shared, the chips in one after it
    // under opts.outside, one group per mark. `saved` (id => { x, y }) puts
    // a frame or chip back where the operator left it. Answers { frames,
    // chips, edges, headings, start }.
    function layout(marked, saved, opts) {
        saved = isObject(saved) ? saved : {};
        opts = isObject(opts) ? opts : {};

        var frames = [];
        var chips = [];
        var chipByKey = {};
        var edges = [];
        var tabs = {};

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
                // A page with a tree is drawn as its picture, the sketch
                // standing in while it loads; a shared component keeps its
                // sketch (a column of pictures would outgrow the grid).
                var pic = (group[0] === 'page') && Array.isArray(frame.sketch);
                var thumb = pic ? picSize().box : (bands.length ? FRAME.thumb : 0);
                var item = {
                    id: (group[0] === 'page') ? 'p:' + frame.key : 's:' + frame.id,
                    kind: group[0],
                    key: (group[0] === 'page') ? frame.key : '',
                    pageId: (group[0] === 'page') ? (parseInt(frame.pageId, 10) || 0) : 0,
                    sharedId: (group[0] === 'shared') ? frame.id : 0,
                    name: frame.name,
                    pages: (group[0] === 'shared') ? frame.pages : 0,
                    loaded: (group[0] === 'shared') ? frame.loaded : true,
                    rows: rows,
                    // The shared parts placed on the page (ids, nested ones
                    // added below).
                    parts: (group[0] === 'page' && Array.isArray(frame.shared)) ? frame.shared.slice() : [],
                    sketch: bands,
                    pic: pic,
                    thumb: thumb,
                    x: 0,
                    y: 0,
                    w: FRAME.w,
                    h: FRAME.head + thumb + Math.max(1, rows.length) * FRAME.row + FRAME.pad
                };

                frames.push(item);
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

        // A page's shared parts with those placed inside them, each once,
        // as frames ('s:<id>').
        var innerOf = {};

        (marked.shared || []).forEach(function (one) {
            innerOf[String(one.id)] = Array.isArray(one.shared) ? one.shared : [];
        });
        frames.forEach(function (frame) {
            var seen = {};
            var queue = frame.parts.slice();

            frame.parts = [];

            while (queue.length) {
                var sid = String(queue.shift());

                if (seen[sid]) {
                    continue;
                }

                seen[sid] = true;
                frame.parts.push('s:' + sid);
                queue = queue.concat(innerOf[sid] || []);
            }
        });

        var G = window.PGGraphCanvas;
        var byId = {};
        var pageIds = [];
        var sharedIds = [];

        frames.forEach(function (frame) {
            byId[frame.id] = frame;
            (frame.kind === 'page' ? pageIds : sharedIds).push(frame.id);
        });
        chips.forEach(function (chip) { byId[chip.id] = chip; });

        var start = pageIds.length ? pageIds[0] : '';
        var flow = G.order(pageIds, edges.filter(function (e) { return e.to.charAt(0) === 'p'; }), start);
        var rank = {};

        flow.ids.forEach(function (id, i) { rank[id] = i; });

        var bands = [];
        var used = {};

        (Array.isArray(opts.sections) ? opts.sections : []).forEach(function (section) {
            var items = [];

            (Array.isArray(section && section.keys) ? section.keys : []).forEach(function (key) {
                var id = 'p:' + key;

                if (has(byId, id) && byId[id].kind === 'page' && !used[id]) {
                    used[id] = true;
                    items.push(id);
                }
            });
            bands.push({ id: str(section && section.id), label: str(section && section.label), items: items });
        });

        var rest = pageIds.filter(function (id) { return !used[id]; });
        var restBand = { id: 'pages', label: bands.length ? str(opts.rest) : '', items: rest };

        // The start's band leads: the tabs in no group come first when the
        // first tab is one of them, else last.
        if (start !== '' && !used[start]) {
            bands.unshift(restBand);
        } else {
            bands.push(restBand);
        }
        bands.forEach(function (band) {
            band.items.sort(function (a, b) { return rank[a] - rank[b]; });
        });

        var laid = G.arrange(bands, {
            cols: GRID.cols,
            w: FRAME.w,
            h: FRAME.head + FRAME.row + FRAME.pad,
            gapX: GRID.gap,
            gapY: GRID.gap,
            head: GRID.head,
            sideGap: GRID.side,
            size: function (id) { return has(byId, id) ? { w: byId[id].w, h: byId[id].h } : null; },
            // A long column of shared frames goes on beside itself rather
            // than far below the grid (F4).
            before: { id: 'shared', label: str(opts.shared), w: FRAME.w, maxH: 'grid', items: sharedIds },
            after: {
                id: 'outside',
                label: str(opts.outside),
                w: FRAME.chipW,
                h: FRAME.chipH,
                gap: FRAME.chipGap,
                groups: CHIP_MARKS.map(function (mark) {
                    return { id: 'mark:' + mark, items: chips.filter(function (c) { return c.mark === mark; }).map(function (c) { return c.id; }) };
                })
            }
        });

        frames.concat(chips).forEach(function (item) {
            var at = laid.places[item.id];

            if (at) {
                item.x = at.x;
                item.y = at.y;
            }

            place(item);
        });
        frames.forEach(function (frame) { frame.start = (frame.id === start); });

        return { frames: frames, chips: chips, edges: edges, headings: laid.headings, start: start };
    }

    // The y of a row's port from the top of its frame.
    function rowY(frame, port) {
        var index = parseInt(String(port).replace(/^h/, ''), 10) || 0;

        return FRAME.head + ((frame && frame.thumb) || 0) + index * FRAME.row + FRAME.row / 2;
    }

    // ── The page picture ──

    // The picture's size in canvas units: w x h inside its box, the scale
    // from page pixels, and the box's height (h with the padding and
    // border, what layout() gives a frame as `thumb`).
    function picSize() {
        var p = FRAME.pic;
        var scale = p.inner / p.page;

        return { w: p.inner, h: Math.round(p.cut * scale), scale: scale, box: Math.round(p.cut * scale) + p.edge, page: p.page, cut: p.cut };
    }

    // The marker a picture's element carries for a link: the node's id, its
    // n-th link of text or custom HTML as '<id>.<n>' (htmlLinks()' index),
    // inside a shared component 's<shared id>:' before it. '' without an id.
    function picMark(nodeId, index, sharedId) {
        var id = (typeof nodeId === 'number' && isFinite(nodeId)) ? String(nodeId) : str(nodeId);

        if (id === '') {
            return '';
        }

        var shared = parseInt(sharedId, 10) || 0;
        var n = (index === null || index === undefined || index === '') ? -1 : parseInt(index, 10);

        return (shared > 0 ? 's' + shared + ':' : '') + id + (n >= 0 ? '.' + n : '');
    }

    // Which markers a node's own markup gets: `own` when its link is its
    // own (href, the Attributes panel's href, a button), `text` the
    // indexes of the links written in its text or custom HTML. Bootstrap
    // toggles are no links (nodeLinks()).
    function picMarks(node) {
        var out = { own: false, text: [] };

        nodeLinks(node).forEach(function (spot) {
            if (spot.source === 'text') {
                out.text.push(parseInt(spot.index, 10) || 0);
            } else {
                out.own = true;
            }
        });

        return out;
    }

    // The foot of a frame's picture: of its rows that a picture marks (a
    // link of the tree, not a menu or region), how many cannot be seen.
    // `marks` are the picture's marked elements, { pic, top, w, h } in page
    // pixels; a link is seen when one of its elements has a size and starts
    // above the cut. A link with no element at all (hidden, not written)
    // counts too. A page's frame reads no shared component's marker.
    // Answers { links, below }.
    function picFoot(rows, marks, sharedId) {
        var seen = {};
        var cut = FRAME.pic.cut;
        var links = 0;
        var below = 0;

        (Array.isArray(marks) ? marks : []).forEach(function (m) {
            if (isObject(m) && Number(m.w) > 0 && Number(m.h) > 0 && Number(m.top) < cut) {
                seen[str(m.pic)] = true;
            }
        });

        var counted = {};

        (Array.isArray(rows) ? rows : []).forEach(function (row) {
            if (!isObject(row) || ['href', 'attrs', 'btn', 'text'].indexOf(row.source) < 0) {
                return;
            }

            var key = picMark(row.nodeId, row.source === 'text' ? row.index : null, sharedId);

            if (key === '' || counted[key]) {
                return;
            }

            counted[key] = true;
            links++;

            if (!seen[key]) {
                below++;
            }
        });

        return { links: links, below: below };
    }

    // The attributes of a picture's frame: only `allow-same-origin`, so the
    // view reads its document and no script of it runs; it takes no focus
    // and no pointer and says nothing to a screen reader (the rows list the
    // links).
    var PIC_FRAME = { sandbox: 'allow-same-origin', tabindex: '-1', 'aria-hidden': 'true', scrolling: 'no', width: '1280', height: '1600', style: 'pointer-events:none' };

    // What a picture's document gets first in its <head>: a second lock in
    // case the sandbox ever allows scripts (no script, plugin, frame, media
    // or form post), and the stilling rule (same-origin frames share the
    // editor's main thread, so nothing in them moves). An embedded frame or
    // player keeps its place but shows no "blocked" box.
    function picHead() {
        return '<meta http-equiv="Content-Security-Policy" content="script-src \'none\'; object-src \'none\'; frame-src \'none\'; media-src \'none\'; form-action \'none\'">'
            + '<style>*,*::before,*::after{animation:none!important;transition:none!important}'
            + 'html,body{overflow:hidden!important}'
            + 'iframe,embed,object,video,audio{visibility:hidden!important}</style>';
    }

    // A preview document as a picture's: its <script> elements left out
    // (none would run; each would log a blocked script and be parsed for
    // nothing), picHead() right after its <head>, before anything it
    // loads. '' for a document without one.
    function picDoc(html) {
        html = String(html || '').replace(/<script\b[\s\S]*?<\/script\s*>/gi, '');

        var m = /<head(\s[^>]*)?>/i.exec(html);

        if (!m) {
            return '';
        }

        var at = m.index + m[0].length;

        return html.substring(0, at) + picHead() + html.substring(at);
    }

    // Whether a picture runs past its cut and gets the fade: only when the
    // page is taller than the cut. A page that ends at it (a short page
    // with min-vh-100 fills the 1600-high frame exactly) gets none.
    function picLong(height) {
        return Number(height) > FRAME.pic.cut;
    }

    // The ids of `boxes` ({ id, x, y, w, h }) nearest the centre of `view`
    // ({ x, y, w, h }) first, by the distance between centres; equal ones
    // in the order given. A picture beyond the cap is dropped from the end.
    function picOrder(boxes, view) {
        var cx = Number(view && view.x) + Number(view && view.w) / 2;
        var cy = Number(view && view.y) + Number(view && view.h) / 2;

        return (Array.isArray(boxes) ? boxes : []).map(function (b, i) {
            var dx = Number(b.x) + Number(b.w) / 2 - cx;
            var dy = Number(b.y) + Number(b.h) / 2 - cy;

            return { id: b.id, d: dx * dx + dy * dy, i: i };
        }).sort(function (a, b) {
            return (a.d - b.d) || (a.i - b.i);
        }).map(function (e) {
            return e.id;
        });
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

    // The page an arrow ending on `target` keeps its link by: a tab frame's
    // page id, 'tab:<key>' for a tab not saved yet (made an id on Save);
    // null for anything else (a chip keeps the link by its href).
    function boundTo(target) {
        if (!isObject(target) || target.kind !== 'page') {
            return null;
        }

        var id = parseInt(target.pageId, 10) || 0;

        return (id > 0) ? id : ((str(target.key) === '') ? null : 'tab:' + str(target.key));
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
    // null when the node no longer holds the link the map showed (or the
    // row is not drawable). With the arrow's `target` a link of its own
    // (not one in text) ending on a tab is kept by page id: `bind` names
    // the page (boundTo()); one kept by page id that ends on a chip gets
    // `bind: false`, back to its href. applyPatch() writes it.
    function hrefPatch(node, row, href, target) {
        if (!drawable(row) || !isObject(node)) {
            return null;
        }

        var props = propsOf(node);
        var index = parseInt(row.index, 10) || 0;
        var now = nodeLinks(node).filter(function (spot) {
            return spot.source === row.source && (spot.index === index);
        })[0];

        if (!now || (str(row.bound) !== '' ? str(now.bound) !== str(row.bound) : (str(now.bound) !== '' || now.href !== str(row.href)))) {
            return null;
        }

        var patch = hrefPatchValue(node, props, row, index, href);

        if (patch && row.source !== 'text' && target !== undefined) {
            var bind = boundTo(target);

            if (bind !== null) {
                patch.bind = { page_id: bind };
            } else if (now.pageLink) {
                patch.bind = false;
            }
        }

        return patch;
    }

    // The props of a node with a patch of hrefPatch() written (changes
    // `props`): the prop, then the page binding. A link kept by page id
    // keeps its other values (params, written by a widget's Bind Data).
    function applyPatch(props, patch) {
        if (!isObject(props) || !isObject(patch)) {
            return props;
        }

        props[patch.key] = patch.value;

        if (patch.bind === undefined) {
            return props;
        }

        var bindings = {};
        var params = {};
        var old = (isObject(props._bindParams) && isObject(props._bindParams.href)) ? props._bindParams.href : {};

        Object.keys(isObject(props._bindings) ? props._bindings : {}).forEach(function (k) { if (k !== 'href') bindings[k] = props._bindings[k]; });
        Object.keys(isObject(props._bindParams) ? props._bindParams : {}).forEach(function (k) { if (k !== 'href') params[k] = props._bindParams[k]; });

        if (patch.bind) {
            bindings.href = '__page_link';
            params.href = { page_id: patch.bind.page_id, params: Array.isArray(old.params) ? old.params : [] };
        }

        if (Object.keys(bindings).length) {
            props._bindings = bindings;
        } else {
            delete props._bindings;
        }

        if (Object.keys(params).length) {
            props._bindParams = params;
        } else {
            delete props._bindParams;
        }

        return props;
    }

    function hrefPatchValue(node, props, row, index, href) {
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

    // Why a broken row is broken: 'menu' (a menu or region the read does
    // not know), 'kept' (kept by a page id whose page is gone), 'address'
    // (no address), else 'page' (no page at its address).
    function brokenWhy(row) {
        if (row.from && row.label === row.from && str(row.href) === '') {
            return 'menu';
        }

        if (str(row.bound) !== '') {
            return 'kept';
        }

        return (str(row.href) === '') ? 'address' : 'page';
    }

    function rowLabel(row) {
        return (row.from && row.from !== row.label) ? row.from + ' › ' + row.label : row.label;
    }

    function itemsById(laid) {
        var byId = {};

        ((laid && laid.frames) || []).concat((laid && laid.chips) || []).forEach(function (item) {
            byId[item.id] = item;
        });

        return byId;
    }

    // The list beside the canvas for a frame of a layout (its id; null for
    // none, a chip or no such frame): { id, kind, name, outgoing, incoming,
    // parts }. outgoing, one per row: { port, label, mark, href, draft, to
    // (the frame or chip its arrow ends on, '' for none), target (that
    // frame's name or chip's href), targetKind, why (a broken row's
    // brokenWhy(), else ''), drawable }; incoming, the arrows into it from
    // other frames: { frame, port, label, source (its name), shared };
    // parts, a page's shared parts { id, name }.
    function listOf(laid, id) {
        var byId = itemsById(laid);
        var frame = (id === null || id === undefined) ? null : byId[String(id)];

        if (!frame || (frame.kind !== 'page' && frame.kind !== 'shared')) {
            return null;
        }

        var links = window.PGGraphCanvas.linksOf(laid.edges || [], frame.id);
        var edgeOf = {};

        links.out.forEach(function (e) { edgeOf[e.port] = e; });

        return {
            id: frame.id,
            kind: frame.kind,
            name: frame.name,
            outgoing: frame.rows.map(function (row) {
                var e = edgeOf[row.port];
                var to = e ? byId[e.to] : null;

                return {
                    port: row.port,
                    label: rowLabel(row),
                    mark: row.mark,
                    href: row.href,
                    draft: !!row.draft,
                    to: to ? to.id : '',
                    target: to ? (to.kind === 'chip' ? to.href : str(to.name)) : '',
                    targetKind: to ? to.kind : '',
                    why: (row.mark === 'broken') ? brokenWhy(row) : '',
                    drawable: drawable(row)
                };
            }),
            incoming: links['in'].filter(function (e) { return e.from !== frame.id && byId[e.from]; }).map(function (e) {
                var source = byId[e.from];
                var row = (source.rows || []).filter(function (r) { return r.port === e.port; })[0];

                return { frame: source.id, port: e.port, label: row ? rowLabel(row) : '', source: str(source.name), shared: source.kind === 'shared' };
            }),
            parts: (frame.parts || []).filter(function (pid) { return byId[pid]; }).map(function (pid) {
                return { id: pid, name: str(byId[pid].name) };
            })
        };
    }

    // Every broken link of a layout, in its frames' order: { list: [{ frame,
    // port, label, href, why }], count: { frame id: n } }.
    function brokenOf(laid) {
        var out = { list: [], count: {} };

        ((laid && laid.frames) || []).forEach(function (frame) {
            frame.rows.forEach(function (row) {
                if (row.mark !== 'broken') {
                    return;
                }

                out.list.push({ frame: frame.id, port: row.port, label: rowLabel(row), href: row.href, why: brokenWhy(row) });
                out.count[frame.id] = (out.count[frame.id] || 0) + 1;
            });
        });

        return out;
    }

    // Where Fit starts when a design does not fit at the smallest zoom: the
    // start frame's top left corner, raised to its band's heading when that
    // stands right above it; null without a start frame.
    function fitStart(laid) {
        var frame = (laid && Array.isArray(laid.frames)) ? laid.frames.filter(function (f) { return f.start; })[0] : null;

        if (!frame) {
            return null;
        }

        var heading = (laid.headings || []).filter(function (h) { return h.kind === 'group' && h.x === frame.x && h.y + GRID.head === frame.y; })[0];

        return { x: frame.x, y: heading ? heading.y : frame.y };
    }

    // What the selected frame (its id, null for none) shows of a layout:
    // out, the ids of its arrows; in, the ids of the arrows into it from
    // page frames; shared, the links of shared frames into it, which draw
    // no arrow but mark their row and name themselves under their frame
    // ({ frame, port, edge, label, to }); lit, per other frame the ports of
    // its rows that start an arrow into it.
    function focusOf(laid, id) {
        var out = { out: [], in: [], shared: [], lit: {} };
        var byId = {};

        ((laid && laid.frames) || []).concat((laid && laid.chips) || []).forEach(function (item) { byId[item.id] = item; });

        if (id === null || id === undefined || !has(byId, String(id))) {
            return out;
        }

        id = String(id);

        ((laid && laid.edges) || []).forEach(function (edge) {
            if (edge.from === id) {
                out.out.push(edge.id);
                return;
            }

            if (edge.to !== id) {
                return;
            }

            var from = byId[edge.from];

            if (from && from.kind === 'shared') {
                var row = (from.rows || []).filter(function (r) { return r.port === edge.port; })[0] || {};

                out.shared.push({ frame: edge.from, port: edge.port, edge: edge.id, label: row.from ? row.from + ' › ' + row.label : str(row.label), to: str(byId[id].name) });
                return;
            }

            out.in.push(edge.id);
            (out.lit[edge.from] = out.lit[edge.from] || []).push(edge.port);
        });

        return out;
    }

    window.PGProtoHotspots = {
        FRAME: FRAME,
        layout: layout,
        sketch: sketch,
        sketchBoxes: sketchBoxes,
        picSize: picSize,
        picMark: picMark,
        picMarks: picMarks,
        picFoot: picFoot,
        picOrder: picOrder,
        picLong: picLong,
        PIC_FRAME: PIC_FRAME,
        picHead: picHead,
        picDoc: picDoc,
        placeKey: placeKey,
        places: places,
        rowY: rowY,
        LIMITS: LIMITS,
        htmlLinks: htmlLinks,
        nodeLinks: nodeLinks,
        pageLinkable: pageLinkable,
        collect: collect,
        design: design,
        markHref: markHref,
        mark: mark,
        drawable: drawable,
        hrefTo: hrefTo,
        setHtmlHref: setHtmlHref,
        hrefPatch: hrefPatch,
        applyPatch: applyPatch,
        boundTo: boundTo,
        markBound: markBound,
        drawTargets: drawTargets,
        focusOf: focusOf,
        fitStart: fitStart,
        listOf: listOf,
        brokenOf: brokenOf,
        brokenWhy: brokenWhy
    };
})();
