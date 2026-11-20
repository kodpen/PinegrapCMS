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

    // What a link says: its own text, its aria-label, its alt; else what
    // its children show (their text and their pictures' alternative text,
    // in document order, up to 80 characters), as a logo link of an icon
    // and a name does.
    function nodeLabel(props, node) {
        var text = plainText(str(props.text));
        var own = text || str(props.ariaLabel) || str(attrOf(props, 'aria-label')) || str(props.alt);

        if (own !== '' || !node || !Array.isArray(node.children)) {
            return own;
        }

        var parts = [];

        (function walk(list) {
            list.forEach(function (child) {
                if (!isObject(child)) {
                    return;
                }

                var p = propsOf(child);
                var said = plainText(str(p.text)) || str(p.alt);

                if (said !== '') {
                    parts.push(said);
                }

                if (Array.isArray(child.children)) {
                    walk(child.children);
                }
            });
        })(node.children);

        return parts.join(' ').replace(/\s+/g, ' ').trim().substring(0, 80);
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

                add(fromAttrs ? 'attrs' : 'href', (fromAttrs ? attrOf(props, 'href') : str(props.href)) || '#', nodeLabel(props, node), 0, toggle, target);
            } else if (type === 'component' && props.componentType === 'btn') {
                if ((str(props.btnElement) || 'a') === 'a') {
                    add('btn', str(props.href) || '#', nodeLabel(props, node), 0, toggle, target);
                }
            } else if (type === 'semantic' && str(props.tag).toLowerCase() === 'a') {
                var own = str(props.href);
                var written = attrOf(props, 'href');

                if (own !== '') {
                    add('href', own, nodeLabel(props, node), 0, toggle, target);
                } else if (written !== null) {
                    add('attrs', written, nodeLabel(props, node), 0, toggle, target);
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
        var out = { hotspots: [], shared: [], menus: [], regions: [], urls: [] };

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

            // A link the server fills per request (an href bound to a field
            // such as __profile_url) is no hotspot; its field is listed.
            var field = isObject(props._bindings) ? str(props._bindings.href) : '';

            if (field.indexOf('__') === 0 && field !== '__page_link') {
                addOnce(out.urls, field);
            }

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
    // The settings of a system widget that lead a visitor to a page (the
    // login widget's "Giriş Yap", a list's detail page, where a form goes
    // after it is sent). Not links: a form's data page, an e-mail's page.
    var WIDGET_LINKS = ['login_page_id', 'register_page_id', 'account_page_id', 'my_account_page_id', 'staff_page_id', 'forgot_password_page_id', 'detail_page_id', 'redirect_page_id'];

    // The fields the server fills with the address of the page that places a
    // system widget of a kind (pg_sw_page_type_widget_url()): a link bound to
    // one leads to that page.
    var BOUND_WIDGET_URLS = {
        __login_url: 'login_form', __register_url: 'registration', __forgot_password_url: 'forgot_password',
        __set_password_url: 'set_password', __new_account_set_password_url: 'set_password', __my_account_url: 'my_account',
        __profile_url: 'account_profile', __change_password_url: 'change_password', __email_preferences_url: 'email_preferences',
        __address_book_url: 'address_book', __logout_url: 'logout'
    };

    function settingsOf(config) {
        var c = config;

        if (typeof c === 'string') {
            try { c = JSON.parse(c); } catch (e) { c = null; }
        }

        return isObject(c) ? c : null;
    }

    // A system widget's kind (its regionType), '' for none.
    function widgetKind(config) {
        var c = settingsOf(config);

        return c ? str(c.regionType) : '';
    }

    // The page ids a system widget's settings (JSON text or an object) lead
    // to, each once, in WIDGET_LINKS order; a register link that is not
    // shown leads nowhere. [] for none or settings that do not parse.
    function widgetPages(config) {
        var c = settingsOf(config);

        if (!c) {
            return [];
        }

        var out = [];

        WIDGET_LINKS.forEach(function (key) {
            var id = parseInt(c[key], 10) || 0;

            if (id > 0 && !(key === 'register_page_id' && c.show_register_link === false) && out.indexOf(id) < 0) {
                out.push(id);
            }
        });

        return out;
    }

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
                var found = tree ? collect(tree) : { hotspots: [], shared: [], menus: [], regions: [], urls: [] };

                byId[sid] = { id: sid, name: entry ? str(entry.name) : '', loaded: !!tree, pages: 0, hotspots: found.hotspots, sharedIn: found.shared, sketch: tree ? sketch(tree) : null, reaches: widgetPages(entry && entry.system_region_config),
                    urls: found.urls, widget: widgetKind(entry && entry.system_region_config) };
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

            out.pages.push({ key: key, pageId: parseInt(page.page_id, 10) || 0, name: str(page.page_name), hotspots: found.hotspots, shared: found.shared, sketch: sketch(page.tree), urls: found.urls });
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
    // Whether a link's own address leads nowhere: empty or '#' alone. '#name'
    // is an anchor; a toggle is no link (collect()); a link kept by page id
    // reads its page (markBound()).
    function emptyHref(href) {
        var h = str(href).trim();

        return h === '' || h === '#';
    }

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
            } else if (emptyHref(spot.href)) {
                // An empty link (F26): it leads nowhere and gets no chip.
                copy.mark = 'empty';
                copy.draft = false;
                copy.type = 'unknown';
                copy['class'] = 'unknown';
                copy.pageId = 0;
                copy.tab = '';
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
    // `inner` units inside a box with the thumbnail's padding and border;
    // the picture starts `x`, `y` into its box, and the foot's middle sits
    // `foot` above the box's lower edge; the selected page opened whole
    // shows at most `full` page pixels (picOpenCut()). `row`: the line a
    // frame with no picture keeps under its head.
    var FRAME = { w: 248, head: 44, thumb: 84, row: 30, pad: 10, chipW: 200, chipH: 36, chipGap: 12, pic: { page: 1280, cut: 1600, full: 6400, inner: 228, edge: 13, x: 10, y: 6, foot: 18 } };

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
    // arrows join it: frames (id 'p:<tab key>' or 's:<shared id>'), each its
    // head and its picture (a shared part's as tall as opts.partH gives its
    // page pixels, picPartBox(); the page opened whole as opts.openH gives,
    // picOpenBox()), its rows taking no room; one chip (id 'c:<n>') per mark and href that ends
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
                // A page or a shared part with a tree is drawn as its
                // picture, the sketch standing in while it loads. A part is
                // as tall as it is, between the sketch strip and the cut.
                var pic = Array.isArray(frame.sketch);
                var partH = isObject(opts.partH) ? opts.partH[frame.id] : null;
                // The one page opened whole (opts.openH, its id => the
                // page's height) is as tall as its page, up to the limit.
                var openH = (group[0] === 'page' && isObject(opts.openH) && has(opts.openH, 'p:' + frame.key)) ? opts.openH['p:' + frame.key] : null;
                var thumb = !pic ? 0 : (group[0] === 'page' ? (openH !== null ? picOpenBox(openH) : picSize().box) : picPartBox(partH));
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
                    // A system widget's pages, reached as by a link (problemsOf()).
                    reaches: (group[0] === 'shared' && Array.isArray(frame.reaches)) ? frame.reaches.slice() : [],
                    // The fields its links are bound to, and a system
                    // widget's kind (problemsOf()).
                    urls: Array.isArray(frame.urls) ? frame.urls.slice() : [],
                    widget: (group[0] === 'shared') ? str(frame.widget) : '',
                    pic: pic,
                    thumb: thumb,
                    x: 0,
                    y: 0,
                    w: FRAME.w,
                    h: FRAME.head + (thumb || FRAME.row) + FRAME.pad
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

    // The box of a shared part's picture, in canvas units, from the part's
    // height in page pixels: as tall as the part, at least the sketch strip
    // and at most the cut (a page's box). The strip until it is measured.
    function picPartBox(height) {
        var size = picSize();
        var h = Math.round((Number(height) || 0) * size.scale);

        return Math.min(size.h, Math.max(FRAME.thumb - FRAME.pic.edge, h)) + FRAME.pic.edge;
    }

    // How many page pixels the page opened whole shows: its height, at
    // least the cut and at most `full`.
    function picOpenCut(height) {
        var p = FRAME.pic;

        return Math.min(p.full, Math.max(p.cut, Math.round(Number(height) || 0)));
    }

    // The box of the page opened whole, in canvas units (a frame's `thumb`).
    function picOpenBox(height) {
        return Math.round(picOpenCut(height) * picSize().scale) + FRAME.pic.edge;
    }

    // What a picture's foot is (`o`: below, the links it cannot show;
    // long, the page runs past what is shown; open, the page is shown
    // whole; selected, its frame is the selected one; page, a page frame,
    // not a shared part). The selected long page's foot is the button
    // opening it whole ('open'), the open page's the one closing it
    // ('close'); else it is the count, hidden at none. Answers { hidden,
    // action, below }.
    function picFootState(o) {
        o = isObject(o) ? o : {};

        var below = Math.max(0, parseInt(o.below, 10) || 0);
        var action = '';

        if (o.page && o.open) {
            action = 'close';
        } else if (o.page && o.selected && o.long) {
            action = 'open';
        }

        return { hidden: action === '' && below === 0, action: action, below: below };
    }

    // A CSS value with its viewport-height units (vh, svh, lvh, dvh, and
    // vmax, the height in a 1280-wide picture) written as the pixels they
    // are in a viewport `viewH` high. The page opened whole is drawn in a
    // taller frame; pinned first, a min-vh-100 section keeps its height.
    function picVh(value, viewH) {
        var h = Number(viewH) || 0;

        return String(value === null || value === undefined ? '' : value).replace(/(^|[^\w.-])(-?(?:\d+\.?\d*|\.\d+))(?:[sld]?vh|vmax)(?![\w-])/gi, function (all, lead, n) {
            return lead + round2(Number(n) * h / 100) + 'px';
        });
    }

    function round2(value) {
        return Math.round(value * 100) / 100;
    }

    // The marker key a row's element carries, '' for a row a picture does
    // not mark (a menu or region is drawn without one).
    function rowPic(row, sharedId) {
        if (!isObject(row) || ['href', 'attrs', 'btn', 'text'].indexOf(row.source) < 0) {
            return '';
        }

        return picMark(row.nodeId, row.source === 'text' ? row.index : null, sharedId);
    }

    // Where each link sits on a frame's picture: port => { x, y, w, h } in
    // units of the picture inside its box (page pixels times the scale),
    // cut at the cut. `marks` are the picture's marked elements, { pic,
    // left, top, w, h } in page pixels; the first element seen is taken. A
    // link is not seen when its elements have no size, start at or under
    // the cut or lie outside the page's width, or when it has none (a menu
    // or region, a link not written). A page frame reads no shared
    // component's marker; a shared frame (sharedId) only its own. `cut`:
    // the page pixels shown, FRAME.pic.cut when not given (the page opened
    // whole: picOpenCut()).
    function picRects(rows, marks, sharedId, cut) {
        var p = FRAME.pic;
        var scale = p.inner / p.page;
        var end = Number(cut) > 0 ? Number(cut) : p.cut;
        var first = {};
        var out = {};

        (Array.isArray(marks) ? marks : []).forEach(function (m) {
            if (!isObject(m)) {
                return;
            }

            var left = Number(m.left) || 0;
            var top = Number(m.top) || 0;
            var w = Number(m.w) || 0;
            var h = Number(m.h) || 0;
            var key = str(m.pic);

            if (w <= 0 || h <= 0 || top >= end || left + w <= 0 || left >= p.page || has(first, key)) {
                return;
            }

            first[key] = { x: round2(left * scale), y: round2(top * scale), w: round2(w * scale), h: round2((Math.min(top + h, end) - top) * scale) };
        });

        (Array.isArray(rows) ? rows : []).forEach(function (row) {
            var key = rowPic(row, sharedId);

            if (key !== '' && has(first, key)) {
                out[row.port] = first[key];
            }
        });

        return out;
    }

    // The foot of a frame's picture: how many of its links it cannot show
    // (picRects() finds no place for them: under the cut, hidden, in a
    // closed menu, in a menu or region, not written). Two rows of one
    // element count once; `cut` as picRects(). Answers { links, below }.
    function picFoot(rows, marks, sharedId, cut) {
        var list = (Array.isArray(rows) ? rows : []).filter(isObject).map(function (row, i) {
            return { port: 'f' + i, nodeId: row.nodeId, source: row.source, index: row.index };
        });
        var seen = picRects(list, marks, sharedId, cut);
        var counted = {};
        var links = 0;
        var below = 0;

        list.forEach(function (row) {
            var key = rowPic(row, sharedId);

            if (key !== '' && counted[key]) {
                return;
            }

            if (key !== '') {
                counted[key] = true;
            }

            links++;

            if (!has(seen, row.port)) {
                below++;
            }
        });

        return { links: links, below: below };
    }

    // Where an arrow leaves a frame ({ x, y } from its top left, and the
    // side): by the side facing its target ({ x, y, w, h }): right or left
    // when the target lies wholly beside the frame, else bottom or top; to
    // its own frame or none, right. `rect` is the link's place on the
    // picture (picRects()): the arrow leaves at the mark's middle, level
    // with it on a left or right edge, under or over it on the bottom or
    // top. null: the picture cannot show the link, the arrow leaves from
    // the foot. undefined: no picture yet, from the middle of the box.
    function markPort(frame, rect, target) {
        var w = Number(frame && frame.w) || 0;
        var h = Number(frame && frame.h) || 0;
        var fx = Number(frame && frame.x) || 0;
        var fy = Number(frame && frame.y) || 0;
        var thumb = Number(frame && frame.thumb) || 0;
        var side = 'right';

        if (isObject(target)) {
            var tx = Number(target.x) || 0;
            var ty = Number(target.y) || 0;

            if (tx >= fx + w) {
                side = 'right';
            } else if (tx + (Number(target.w) || 0) <= fx) {
                side = 'left';
            } else if (ty >= fy + h) {
                side = 'bottom';
            } else if (ty + (Number(target.h) || 0) <= fy) {
                side = 'top';
            }
        }

        var x = w / 2;
        var y = thumb ? FRAME.head + thumb / 2 : h / 2;

        if (isObject(rect)) {
            x = FRAME.pic.x + (Number(rect.x) || 0) + (Number(rect.w) || 0) / 2;
            y = FRAME.head + FRAME.pic.y + (Number(rect.y) || 0) + (Number(rect.h) || 0) / 2;
        } else if (rect === null && thumb) {
            y = FRAME.head + thumb - FRAME.pic.foot;
        }

        if (side === 'right' || side === 'left') {
            return { x: side === 'right' ? w : 0, y: round2(y), side: side };
        }

        return { x: round2(x), y: side === 'bottom' ? h : 0, side: side };
    }

    // The leg of an arrow inside its frame: from the middle of its link's
    // mark (`rect`, picRects()) to its port on the frame's edge (`port`,
    // markPort()), in frame units from the frame's top left. The core
    // routes from the edge only, so the view draws this part itself. null
    // when the link has no mark on the picture (the foot, no picture yet).
    function markLeg(rect, port) {
        if (!isObject(rect) || !isObject(port)) {
            return null;
        }

        return {
            x1: round2(FRAME.pic.x + (Number(rect.x) || 0) + (Number(rect.w) || 0) / 2),
            y1: round2(FRAME.head + FRAME.pic.y + (Number(rect.y) || 0) + (Number(rect.h) || 0) / 2),
            x2: round2(Number(port.x) || 0),
            y2: round2(Number(port.y) || 0)
        };
    }

    // A mark as the view draws it and reaches it: at least `min` frame
    // units each way; the "+" handle `grip` screen pixels across and `gap`
    // from its mark; a mark under `small` screen pixels wide or high takes
    // presses `reach` screen pixels around it.
    var MARK = { min: 6, grip: 20, gap: 4, small: 12, reach: 4 };

    // How far around a mark (`rect`, picRects()) a press still takes it at
    // zoom `k`, in frame units: MARK.reach screen pixels when the mark as
    // drawn is under MARK.small screen pixels wide or high, else 0.
    function markReach(rect, k) {
        var zoom = Number(k) > 0 ? Number(k) : 1;

        if (!isObject(rect)) {
            return 0;
        }

        var w = Math.max(MARK.min, Number(rect.w) || 0) * zoom;
        var h = Math.max(MARK.min, Number(rect.h) || 0) * zoom;

        return (w < MARK.small || h < MARK.small) ? round2(MARK.reach / zoom) : 0;
    }

    // Where the "+" handle of a drawable mark goes, a drag from it drawing
    // the mark's arrow. `rect` is the mark (picRects(), drawn at least
    // MARK.min), `room` ({ w, h }) the marks' layer over the picture, `k`
    // the zoom, `others` the other marks shown on that picture. The handle
    // is MARK.grip screen pixels at any zoom, MARK.gap from the mark, and
    // its box spans the gap so the pointer crosses onto it: right of the
    // mark, level with its middle; else left of it, under it or over it,
    // the first that stays inside the picture's box (the layer and the
    // box's padding, FRAME.pic.x and .y) and covers no other mark; else
    // the first inside the box; else over the mark's right end. Answers
    // { side, x, y } (its box's top left in the layer's units), { w, h }
    // (its box in screen pixels) and `scale` (1 / k, to draw it at), or
    // null without a mark.
    function markGrip(rect, room, k, others) {
        if (!isObject(rect)) {
            return null;
        }

        var zoom = Number(k) > 0 ? Number(k) : 1;
        var area = isObject(room) ? room : {};
        var p = FRAME.pic;
        var x = Number(rect.x) || 0;
        var y = Number(rect.y) || 0;
        var w = Math.max(MARK.min, Number(rect.w) || 0);
        var h = Math.max(MARK.min, Number(rect.h) || 0);
        // The handle and, with its gap, its box's length, in frame units.
        var size = MARK.grip / zoom;
        var span = (MARK.grip + MARK.gap) / zoom;
        var minX = -p.x;
        var maxX = (Number(area.w) || 0) + p.x;
        var minY = -p.y;
        var maxY = (Number(area.h) || 0) + p.y;
        var clamp = function (v, lo, hi) { return Math.max(lo, Math.min(v, hi)); };
        var level = clamp(y + h / 2 - size / 2, minY, maxY - size);
        var under = clamp(x + w / 2 - size / 2, minX, maxX - size);
        var sides = [
            { side: 'right', x: x + w, y: level, w: span, h: size, room: x + w + span <= maxX },
            { side: 'left', x: x - span, y: level, w: span, h: size, room: x - span >= minX },
            { side: 'below', x: under, y: y + h, w: size, h: span, room: y + h + span <= maxY },
            { side: 'above', x: under, y: y - span, w: size, h: span, room: y - span >= minY }
        ];
        var covers = function (one) {
            return (Array.isArray(others) ? others : []).some(function (o) {
                if (!isObject(o)) {
                    return false;
                }

                var ox = Number(o.x) || 0;
                var oy = Number(o.y) || 0;

                return one.x < ox + Math.max(MARK.min, Number(o.w) || 0) && ox < one.x + one.w
                    && one.y < oy + Math.max(MARK.min, Number(o.h) || 0) && oy < one.y + one.h;
            });
        };
        var fit = sides.filter(function (one) { return one.room; });
        var pick = fit.filter(function (one) { return !covers(one); })[0] || fit[0] || sides[0];
        var across = pick.side === 'right' || pick.side === 'left';

        return {
            side: pick.side,
            x: round2(clamp(pick.x, minX, maxX - pick.w)),
            y: round2(clamp(pick.y, minY, maxY - pick.h)),
            w: across ? MARK.grip + MARK.gap : MARK.grip,
            h: across ? MARK.grip : MARK.grip + MARK.gap,
            scale: Math.round(10000 / zoom) / 10000
        };
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
    // with min-vh-100 fills the 1600-high frame exactly) gets none. `cut`:
    // the page pixels shown, the cut when not given.
    function picLong(height, cut) {
        return Number(height) > (Number(cut) > 0 ? Number(cut) : FRAME.pic.cut);
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

    // Which pictures to make again after the view has moved: of the boxes
    // near it ({ id, x, y, w, h }), the `cap` nearest the view's centre (the
    // order the cap drops by, so nothing is made only to be dropped), less
    // those alive (`live`, id => truthy). With no more boxes near the view
    // than the cap, every one that is not alive comes back.
    function picRefill(boxes, view, live, cap) {
        live = isObject(live) ? live : {};

        return picOrder(boxes, view).slice(0, Math.max(0, parseInt(cap, 10) || 0)).filter(function (id) {
            return !live[id];
        });
    }

    // ── Drawing an arrow ──

    // Whether a press at `at` ({ x, y }, canvas units) takes the head of an
    // arrow ending at `end`: within 14 screen pixels of it at zoom `k`.
    function nearHead(end, at, k) {
        var zoom = Number(k) > 0 ? Number(k) : 1;

        if (!isObject(end) || !isObject(at)) {
            return false;
        }

        var dx = (Number(at.x) - Number(end.x)) * zoom;
        var dy = (Number(at.y) - Number(end.y)) * zoom;

        return dx * dx + dy * dy <= 14 * 14;
    }

    // Where the target menu opens ({ left, top }): under its anchor, else
    // above it, else beside it (right, then left), always inside `area`
    // (the canvas under the view's bar, never over the bar). anchor and
    // area are { left, top, right, bottom }, menu { w, h }, all in one
    // frame of reference; 4 apart from the area's edges, 2 from the anchor.
    function menuPlace(anchor, menu, area) {
        var a = isObject(anchor) ? anchor : {};
        var m = isObject(menu) ? menu : {};
        var r = isObject(area) ? area : {};
        var w = Number(m.w) || 0;
        var h = Number(m.h) || 0;
        var minX = (Number(r.left) || 0) + 4;
        var maxX = (Number(r.right) || 0) - 4;
        var minY = (Number(r.top) || 0) + 4;
        var maxY = (Number(r.bottom) || 0) - 4;
        var clampY = function (y) { return Math.max(minY, Math.min(y, maxY - h)); };
        var clampX = function (x) { return Math.max(minX, Math.min(x, maxX - w)); };
        var below = (Number(a.bottom) || 0) + 2;
        var above = (Number(a.top) || 0) - 2 - h;

        if (below + h <= maxY) {
            return { left: clampX(Number(a.left) || 0), top: below };
        }

        if (above >= minY) {
            return { left: clampX(Number(a.left) || 0), top: above };
        }

        var right = (Number(a.right) || 0) + 2;
        var left = (Number(a.left) || 0) - 2 - w;

        return { left: (right + w <= maxX || left < minX) ? clampX(right) : left, top: clampY(Number(a.top) || 0) };
    }

    // What a key does on a link's mark ({ key, shiftKey, ctrlKey, metaKey,
    // altKey }): 'pick' for Enter or Space, 'menu' for L, the menu key or
    // Shift+F10 (the target menu, the keyboard's way to draw), '' else.
    function markKey(event) {
        var e = isObject(event) ? event : {};
        var name = str(e.key).toLowerCase();

        if (e.key === 'Enter' || e.key === ' ') {
            return 'pick';
        }

        if ((name === 'l' && !e.ctrlKey && !e.metaKey && !e.shiftKey && !e.altKey) || e.key === 'ContextMenu' || (e.key === 'F10' && e.shiftKey)) {
            return 'menu';
        }

        return '';
    }

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

    // How a links panel row (one of listOf()'s outgoing) shows where its
    // link goes: { control, state }. `control` is true for a row an arrow
    // may be drawn from, whose target is a button opening the target menu;
    // a read-only row keeps it as text. `state` is 'empty' for a link
    // without a target, 'broken' for a broken one, else 'set'.
    function rowTarget(row) {
        var r = isObject(row) ? row : {};
        var state = (r.mark === 'empty') ? 'empty' : ((str(r.why) !== '') ? 'broken' : 'set');

        return { control: !!r.drawable, state: state };
    }

    // One frame's links without a target, in its rows' order, from
    // problemsOf()'s answer: what its amber count lists when pressed.
    function emptyOf(problems, frameId) {
        var list = (problems && problems.empty && Array.isArray(problems.empty.list)) ? problems.empty.list : [];

        return list.filter(function (one) {
            return one.frame === String(frameId);
        });
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

    // What to check in a design (F27, F28, F29): `broken` as brokenOf();
    // `empty`, the links with no target in the frames' order and each
    // frame's count; `unreached`, the page frames no link of another page
    // or of a shared part leads to (the graph canvas core's check(), from
    // the Start page, which never counts; shared parts and chips are no
    // boxes of it, a shared part's links, a system widget's pages
    // (`reaches`, widgetPages()) and its links bound to a field the server
    // fills (`urls`, BOUND_WIDGET_URLS) stand in as one source); `check`,
    // the empty links plus the unreached pages.
    function problemsOf(laid) {
        var out = { broken: brokenOf(laid), empty: { list: [], count: {} }, unreached: [], check: 0 };
        var frames = (laid && Array.isArray(laid.frames)) ? laid.frames : [];
        var pages = [];
        var kind = {};

        frames.forEach(function (frame) {
            kind[frame.id] = frame.kind;

            if (frame.kind === 'page') {
                pages.push(frame.id);
            }

            (frame.rows || []).forEach(function (row) {
                if (row.mark !== 'empty') {
                    return;
                }

                out.empty.list.push({ frame: frame.id, port: row.port, label: rowLabel(row), href: str(row.href) });
                out.empty.count[frame.id] = (out.empty.count[frame.id] || 0) + 1;
            });
        });

        if (pages.length) {
            var SHARED = '#shared';
            var edges = ((laid && laid.edges) || []).filter(function (edge) {
                return kind[edge.to] === 'page' && (kind[edge.from] === 'page' || kind[edge.from] === 'shared');
            }).map(function (edge) {
                return { from: kind[edge.from] === 'shared' ? SHARED : edge.from, to: edge.to };
            });
            // A system widget's settings lead to pages as a shared part's
            // links do.
            var byPage = {};

            frames.forEach(function (frame) {
                if (frame.kind === 'page' && frame.pageId) {
                    byPage[frame.pageId] = frame.id;
                }
            });
            frames.forEach(function (frame) {
                (frame.kind === 'shared' && Array.isArray(frame.reaches) ? frame.reaches : []).forEach(function (id) {
                    if (byPage[id]) {
                        edges.push({ from: SHARED, to: byPage[id] });
                    }
                });
            });
            // A link bound to a field the server fills (BOUND_WIDGET_URLS)
            // leads to the pages placing a widget of that kind.
            var kindOf = {};
            var placing = {};

            frames.forEach(function (frame) {
                if (frame.kind === 'shared' && frame.widget) {
                    kindOf[frame.id] = frame.widget;
                }
            });
            frames.forEach(function (frame) {
                if (frame.kind !== 'page') {
                    return;
                }

                (frame.parts || []).forEach(function (part) {
                    if (kindOf[part]) {
                        (placing[kindOf[part]] = placing[kindOf[part]] || []).push(frame.id);
                    }
                });
            });
            frames.forEach(function (frame) {
                (Array.isArray(frame.urls) ? frame.urls : []).forEach(function (field) {
                    var widget = has(BOUND_WIDGET_URLS, field) ? BOUND_WIDGET_URLS[field] : '';

                    (placing[widget] || []).forEach(function (to) {
                        edges.push({ from: frame.kind === 'shared' ? SHARED : frame.id, to: to });
                    });
                });
            });

            out.unreached = window.PGGraphCanvas.check(pages.concat([SHARED]), edges, str(laid.start)).unreached.filter(function (id) {
                return id !== SHARED;
            });
        }

        out.check = out.empty.list.length + out.unreached.length;

        return out;
    }

    // The links the designer's canvas marks, from a design marked by mark():
    // each drawable link (drawable(): a link of a tree, never a classic
    // menu's or a region's) that is broken or has no target ('' or '#'),
    // the page frames' first, then the shared components', in document
    // order. Each: { nodeId, sharedId (0 on a page), source, index (the
    // nth link of a text), mark ('broken' or 'empty'), label, href, why
    // (brokenWhy(), '' for an empty link) }. `known` false: the map read
    // has not answered, so no link is called broken and only the empty
    // ones are listed.
    function canvasMarks(marked, known) {
        var out = [];

        function frame(item, sharedId) {
            rowsOf(item).forEach(function (row) {
                var broken = (row.mark === 'broken' && known !== false);

                if (!drawable(row) || !(broken || row.mark === 'empty')) {
                    return;
                }

                out.push({ nodeId: row.nodeId, sharedId: sharedId, source: row.source, index: row.index, mark: row.mark, label: row.label, href: row.href, why: broken ? brokenWhy(row) : '' });
            });
        }

        ((marked && Array.isArray(marked.pages)) ? marked.pages : []).forEach(function (page) { frame(page, 0); });
        ((marked && Array.isArray(marked.shared)) ? marked.shared : []).forEach(function (one) { frame(one, parseInt(one.id, 10) || 0); });

        return out;
    }

    // What the map read is asked for the canvas marks: the design's
    // addresses but the empty ones (mark() calls those empty without the
    // read) and the pages its links are kept by; no menu or region, whose
    // links get no mark (canvasMarks()).
    function canvasRequest(designed) {
        var request = (designed && isObject(designed.request)) ? designed.request : {};

        return {
            hrefs: (Array.isArray(request.hrefs) ? request.hrefs : []).filter(function (href) { return !emptyHref(href); }),
            page_ids: Array.isArray(request.page_ids) ? request.page_ids.slice() : [],
            menus: [],
            regions: []
        };
    }

    // The editor's key back to the Prototype view: P alone (Shift and Caps
    // Lock allowed). Nothing else uses a bare P in the editor; the palette's
    // Ctrl+Shift+P and the preview's Ctrl+Alt+P carry modifiers.
    var BACK_KEY = 'p';

    // Where the key is typed or kept by what holds the focus: a dialog, a
    // menu or list (whose letters jump to an item), a note being written,
    // the code editor, the Prototype view itself (its own keys).
    var BACK_SKIP = 'dialog, [role="dialog"], [role="menu"], [role="listbox"], .sd-note-overlay, .cm-editor, #sd-proto';

    // Whether a keydown is the key back: P with no Ctrl, Alt or Meta, not
    // held down, not part of an IME composition, not taken by an earlier
    // listener, and not where it is typed (a field, a text written in) or
    // kept (BACK_SKIP).
    function backKey(event) {
        if (!isObject(event) || event.ctrlKey || event.metaKey || event.altKey || event.repeat || event.isComposing || event.defaultPrevented) {
            return false;
        }

        if (str(event.key).toLowerCase() !== BACK_KEY) {
            return false;
        }

        var target = event.target;

        if (!isObject(target)) {
            return false;
        }

        var tag = str(target.tagName).toUpperCase();

        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || target.isContentEditable) {
            return false;
        }

        return !(typeof target.closest === 'function' && target.closest(BACK_SKIP));
    }

    // The spot of a row of the view, kept while the editor is open: { frame,
    // nodeId, index (a text's link by its place in the text, -1 for any
    // other source), nth (the row's place among its node's rows, which
    // tells a menu's items apart) }; null when the frame or row is gone.
    function linkSpot(laid, frameId, port) {
        var frame = (laid && Array.isArray(laid.frames)) ? laid.frames.filter(function (f) { return f.id === frameId; })[0] : null;
        var row = frame ? frame.rows.filter(function (r) { return r.port === port; })[0] : null;

        if (!row) {
            return null;
        }

        var same = frame.rows.filter(function (r) { return r.nodeId === row.nodeId; });

        return { frame: frame.id, nodeId: row.nodeId, index: row.source === 'text' ? row.index : -1, nth: same.indexOf(row) };
    }

    // Where the key back takes the view for a spot (linkSpot() or the
    // editor's selection): { frame, port } of the node's row, a text's
    // link found by its index, a menu's item by its place, else the node's
    // first row; port null for a node with no row (its frame alone); null
    // for a frame the view does not draw.
    function backTo(laid, spot) {
        if (!isObject(spot) || !laid || !Array.isArray(laid.frames)) {
            return null;
        }

        var frame = laid.frames.filter(function (f) { return f.id === spot.frame; })[0];

        if (!frame) {
            return null;
        }

        var rows = frame.rows.filter(function (r) { return r.nodeId === spot.nodeId; });
        var index = isFinite(spot.index) ? Number(spot.index) : -1;
        var nth = isFinite(spot.nth) ? Number(spot.nth) : -1;
        var row = (index >= 0 ? rows.filter(function (r) { return r.source === 'text' && r.index === index; })[0] : null)
            || (nth >= 0 ? rows[nth] : null)
            || rows[0];

        return { frame: frame.id, port: row ? row.port : null };
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

    // A second tap on the same frame within this many milliseconds is a
    // double-click, as the Flows canvas reads a box's double tap: the
    // canvas captures the pointer, so the frame gets no dblclick.
    var ZOOM_TAP = 450;

    // The frame a tap zooms to: `last` and `tap` ({ id, time }) are where
    // the last tap and this one ended (a frame's or chip's id, '' for
    // anything else) and when. Both on one frame of the layout, this one
    // less than ZOOM_TAP ms later: its id; else null.
    function zoomTap(last, tap, laid) {
        if (!isObject(last) || !isObject(tap) || !laid || !Array.isArray(laid.frames)) {
            return null;
        }

        var id = String(tap.id || '');
        var gap = Number(tap.time) - Number(last.time);

        if (!id || String(last.id || '') !== id || !(gap >= 0 && gap < ZOOM_TAP)) {
            return null;
        }

        return laid.frames.some(function (f) { return String(f.id) === id; }) ? id : null;
    }

    // ── A links panel row dropped on the canvas ──

    // The item of `items` (frames and chips, { id, x, y, w, h } in canvas
    // units) whose box holds the point `at` ({ x, y }), edges included; of
    // two that overlap the later one, drawn on top; null over none.
    function itemAt(items, at) {
        if (!Array.isArray(items) || !isObject(at)) {
            return null;
        }

        var x = Number(at.x);
        var y = Number(at.y);

        if (!isFinite(x) || !isFinite(y)) {
            return null;
        }

        for (var i = items.length - 1; i >= 0; i--) {
            var item = items[i];
            var left = isObject(item) ? Number(item.x) : NaN;
            var top = isObject(item) ? Number(item.y) : NaN;

            if (x >= left && y >= top && x <= left + (Number(item.w) || 0) && y <= top + (Number(item.h) || 0)) {
                return item;
            }
        }

        return null;
    }

    // Where a links panel row dropped at `at` (canvas units) points its
    // link: { item, href }, the frame or chip under the point (itemAt())
    // and the href an arrow ending there writes (hrefTo()). null for a row
    // no arrow is drawn from (drawable()), over nothing, or over what takes
    // no arrow (a shared part's frame, an anchor's, a hidden or a broken
    // chip).
    function rowDrop(row, items, at, env) {
        if (!drawable(row)) {
            return null;
        }

        var item = itemAt(items, at);
        var href = item ? hrefTo(item, env) : null;

        return (href === null) ? null : { item: item, href: href };
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

    // The shortest click path (PX5) from page frame `from` to page frame
    // `to` over the map's arrows, breadth first: a page leads to the pages
    // its own links lead to and to those of the shared parts placed on it
    // (`parts`, their frame ids: a click on the part as shown on that page); arrows to chips
    // and to shared frames lead nowhere. Of the paths with the fewest
    // clicks, the first by the arrows' order. Answers { frames, edges,
    // hops } (the pages in order, the arrows' ids, each hop as { from, to,
    // edge, via: the shared frame's id or '' }), from === to as no hop;
    // null when there is none, or when an end is no page frame.
    function pathOf(laid, from, to) {
        var frames = (laid && Array.isArray(laid.frames)) ? laid.frames : [];
        var byId = {};

        frames.forEach(function (frame) { byId[frame.id] = frame; });
        from = (from === null || from === undefined) ? '' : String(from);
        to = (to === null || to === undefined) ? '' : String(to);

        if (!has(byId, from) || !has(byId, to) || byId[from].kind !== 'page' || byId[to].kind !== 'page') {
            return null;
        }

        var outOf = {};

        ((laid && laid.edges) || []).forEach(function (edge) {
            if (!has(byId, edge.to) || byId[edge.to].kind !== 'page' || edge.to === edge.from) {
                return;
            }

            (outOf[edge.from] = outOf[edge.from] || []).push(edge);
        });

        var came = {};
        var queue = [from];

        came[from] = null;

        while (queue.length && !has(came, to)) {
            var at = queue.shift();
            var page = byId[at];
            var own = outOf[at] || [];
            var parts = Array.isArray(page.parts) ? page.parts : [];

            parts.forEach(function (sid) {
                sid = String(sid);
                (has(byId, sid) && outOf[sid] ? outOf[sid] : []).forEach(function (edge) {
                    own = own.concat([{ id: edge.id, from: at, to: edge.to, via: sid }]);
                });
            });
            own.forEach(function (edge) {
                if (has(came, edge.to)) {
                    return;
                }

                came[edge.to] = { from: at, edge: str(edge.id), via: edge.via || '' };
                queue.push(edge.to);
            });
        }

        if (!has(came, to)) {
            return null;
        }

        var out = { frames: [to], edges: [], hops: [] };

        for (var step = came[to], at2 = to; step; at2 = step.from, step = came[step.from]) {
            out.frames.unshift(step.from);
            out.edges.unshift(step.edge);
            out.hops.unshift({ from: step.from, to: at2, edge: step.edge, via: step.via });
        }

        return out;
    }

    // The marks a picture shows while frame `id` is selected (F5, F13):
    // '<frame>|<port>' => 'own' for each link of the selected frame, 'lit'
    // for a link of another frame whose arrow leads into it, 'shared' (the
    // amber mark) for a shared part's link into it. Nothing selected: none.
    function markState(laid, id) {
        var out = {};
        var f = focusOf(laid, id);

        if (id === null || id === undefined) {
            return out;
        }

        ((laid && laid.frames) || []).forEach(function (frame) {
            if (frame.id === String(id)) {
                (frame.rows || []).forEach(function (row) { out[frame.id + '|' + row.port] = 'own'; });
            }
        });
        Object.keys(f.lit).forEach(function (frame) {
            f.lit[frame].forEach(function (port) { out[frame + '|' + port] = 'lit'; });
        });
        f.shared.forEach(function (link) { out[link.frame + '|' + link.port] = 'shared'; });

        return out;
    }

    // ── Suggested targets (F30 to F32) ──

    // Words a link's label says without naming a page, dropped before a
    // label, a page name or a page title is matched.
    var SUGGEST_FILLER = ['go', 'to', 'the', 'page', 'view', 'see', 'git', 'sayfa', 'gör', 'incele'];

    // At most this many pages offered for an unsure link.
    var SUGGEST_MAX = 3;

    // The words of a text as the match compares them: lower case, Turkish
    // letters to plain ones (dotted capital I and dotless i to i, the marks
    // of ç, ğ, ö, ş, ü, â, î, û dropped), punctuation and spaces between
    // words, the filler words left out.
    function foldWord(text) {
        return String(text).replace(/İ/g, 'i').toLowerCase().replace(/ı/g, 'i')
            .normalize('NFD').replace(/[̀-ͯ]/g, '');
    }

    // The view's search (Ctrl+F): the frames whose name holds the query and
    // the links whose text holds it, folded as foldWord() folds (case,
    // marks, the Turkish dotted and dotless i). `label(item)`, optional,
    // is the name a frame or chip shows (the view's "Untitled page"), else
    // a frame's stored name and a chip's address (none for a hidden page).
    // Frames in reading order (top to bottom, then left to right), each
    // one's name before its links in their rows' order; then the chips.
    // { query (folded), hits: [{ kind: 'frame' | 'link' | 'chip', frame
    // (the frame's or chip's id), port ('' but for a link), text (as
    // shown) }], frames: { id: true } for each frame or chip with a hit }.
    function searchOf(laid, query, label) {
        var q = foldWord(str(query)).replace(/\s+/g, ' ').trim();
        var found = { query: q, hits: [], frames: {} };
        var shown = (typeof label === 'function') ? label : function (item) {
            return (item.kind === 'chip') ? (item.mark === 'hidden' ? '' : str(item.href)) : str(item.name);
        };
        var reading = function (a, b) { return ((Number(a.y) || 0) - (Number(b.y) || 0)) || ((Number(a.x) || 0) - (Number(b.x) || 0)); };
        var hit = function (kind, item, port, text) {
            if (foldWord(text).indexOf(q) < 0) {
                return;
            }

            found.hits.push({ kind: kind, frame: String(item.id), port: port, text: text });
            found.frames[String(item.id)] = true;
        };

        if (q === '' || !isObject(laid)) {
            return found;
        }

        (Array.isArray(laid.frames) ? laid.frames : []).filter(isObject).slice().sort(reading).forEach(function (frame) {
            hit('frame', frame, '', str(shown(frame)));
            (Array.isArray(frame.rows) ? frame.rows : []).forEach(function (row) {
                hit('link', frame, str(row.port), str(rowLabel(row)));
            });
        });
        (Array.isArray(laid.chips) ? laid.chips : []).filter(isObject).slice().sort(reading).forEach(function (chip) {
            hit('chip', chip, '', str(shown(chip)));
        });

        return found;
    }

    // A key in the search box: 1 for the next match (Enter, ArrowDown), -1
    // for the one before (Shift+Enter, ArrowUp), 0 for anything else or
    // with Ctrl, Alt or Cmd held.
    function searchKey(event) {
        if (!event || event.ctrlKey || event.altKey || event.metaKey) {
            return 0;
        }

        if (event.key === 'Enter') {
            return event.shiftKey ? -1 : 1;
        }

        return (event.key === 'ArrowDown') ? 1 : ((event.key === 'ArrowUp') ? -1 : 0);
    }

    // The match after `at` (dir 1) or before it (dir -1) of `total`, round
    // at either end; the first (or last) when `at` is outside; -1 for none.
    function searchStep(total, at, dir) {
        if (!(total > 0)) {
            return -1;
        }

        if (!(at >= 0 && at < total)) {
            return (dir < 0) ? total - 1 : 0;
        }

        return (at + (dir < 0 ? -1 : 1) + total) % total;
    }

    function foldWords(text) {
        var filler = SUGGEST_FILLER.map(foldWord);

        return foldWord(str(text)).split(/[\s!-\/:-@\[-`{-~ -¿‐-‧‰-⁞]+/).filter(function (word) {
            return word !== '' && filler.indexOf(word) < 0;
        });
    }

    // How well a page matches the words of a label: 3 when its folded name
    // or title is the label, 2 when every word of it begins a word of the
    // label (a word of three letters or more as a beginning, a shorter one
    // whole), between 1 and 1.5 when only some do, else 0.
    function suggestScore(words, page) {
        var said = words.join(' ');
        var best = 0;

        [page.name, page.title].forEach(function (text) {
            var own = foldWords(text);

            if (!own.length || !words.length) {
                return;
            }

            if (own.join(' ') === said) {
                best = Math.max(best, 3);
                return;
            }

            var hit = own.filter(function (w) {
                return words.some(function (l) { return l === w || (w.length >= 3 && l.indexOf(w) === 0); });
            }).length;

            if (hit === own.length) {
                best = Math.max(best, 2);
            } else if (hit > 0) {
                best = Math.max(best, 1 + hit / own.length / 2);
            }
        });

        return best;
    }

    function suggestMatches(words, pages) {
        return pages.map(function (page, at) {
            return { page: page, at: at, score: suggestScore(words, page) };
        }).filter(function (m) { return m.score > 0; }).sort(function (a, b) {
            return (b.score - a.score) || (a.at - b.at);
        });
    }

    // The page a link without a target (link: { label, page, the id of the
    // page it is on, heading, the nearest heading above it in its block })
    // should lead to, among `pages` ([{ id, name, title, bin }]): { sure,
    // the page when its label names it and no other page matches at all,
    // else null; candidates, at most three pages best first }. A label
    // that matches nothing is tried once more with its heading, and what
    // that finds is unsure. The link's own page and a page in the bin are
    // never offered.
    function suggest(link, pages) {
        link = isObject(link) ? link : {};

        var own = str(link.page);
        var open = (Array.isArray(pages) ? pages : []).filter(function (page) {
            return isObject(page) && !page.bin && (own === '' || String(page.id) !== own);
        });
        var found = suggestMatches(foldWords(link.label), open);

        if (found.length === 1 && found[0].score >= 2) {
            return { sure: found[0].page, candidates: [found[0].page] };
        }

        if (!found.length) {
            found = suggestMatches(foldWords(link.heading), open);
        }

        return { sure: null, candidates: found.slice(0, SUGGEST_MAX).map(function (m) { return m.page; }) };
    }

    // The text of the nearest heading above node `id` in its block (the
    // top-level part of the tree holding it), '' for none: a heading
    // content's or an h1 to h6's text, read before the node in document
    // order.
    function headingOf(tree, id) {
        var top = (isObject(tree) && Array.isArray(tree.children)) ? tree.children : [];
        var want = String(id);

        for (var i = 0; i < top.length; i++) {
            var last = '';
            var found = (function walk(node) {
                if (!isObject(node)) {
                    return false;
                }

                if (String(node._id) === want) {
                    return true;
                }

                var props = propsOf(node);
                var tag = str(props.tag).toLowerCase();

                if ((node.type === 'content' && props.contentType === 'heading' && (tag === '' || /^h[1-6]$/.test(tag))) || (node.type === 'semantic' && /^h[1-6]$/.test(tag))) {
                    var text = plainText(str(props.text));

                    if (text !== '') {
                        last = text;
                    }
                }

                return (Array.isArray(node.children) ? node.children : []).some(walk);
            })(top[i]);

            if (found) {
                return last;
            }
        }

        return '';
    }

    // The rows of the Suggest targets dialog: one per drawable link without
    // a target in the frames' order (a shared part's once, as it has one
    // frame), each { frame, port, nodeId, source, label, kind ('sure', 'ask'
    // or 'none'), page (the sure page's id, else null), candidates (ids) };
    // `headings` gives a link's heading by '<frame>|<port>'. count: the
    // head's numbers { sure, ask, none }.
    function suggestRows(laid, pages, headings) {
        var out = { rows: [], count: { sure: 0, ask: 0, none: 0 } };

        headings = isObject(headings) ? headings : {};

        ((laid && laid.frames) || []).forEach(function (frame) {
            (frame.rows || []).forEach(function (row) {
                if (row.mark !== 'empty' || !drawable(row)) {
                    return;
                }

                var key = frame.id + '|' + row.port;
                var s = suggest({ label: row.label, page: frame.kind === 'page' ? frame.id : '', heading: has(headings, key) ? headings[key] : '' }, pages);
                var kind = s.sure ? 'sure' : (s.candidates.length ? 'ask' : 'none');

                out.count[kind]++;
                out.rows.push({
                    frame: frame.id,
                    port: row.port,
                    nodeId: str(row.nodeId),
                    source: str(row.source),
                    label: rowLabel(row),
                    kind: kind,
                    page: s.sure ? String(s.sure.id) : null,
                    candidates: s.candidates.map(function (page) { return String(page.id); })
                });
            });
        });

        return out;
    }

    // What Apply writes of the dialog's ticked rows (each { frame, port,
    // page, the target frame's id, tab, the key of the tab it is written
    // on, locked, whether that tab is closed to the operator }): tabs, one
    // per tab in the order of its first row, each { key, picks }, so a tab
    // gets one undo entry; refused, the rows left unwritten with why
    // ('target' with no page chosen, 'tab' for a link on no tab, 'locked').
    function suggestBatch(picks) {
        var out = { tabs: [], refused: [] };
        var byKey = {};

        (Array.isArray(picks) ? picks : []).forEach(function (pick) {
            if (!isObject(pick)) {
                return;
            }

            var key = str(pick.tab);
            var why = (str(pick.page) === '') ? 'target' : (key === '' ? 'tab' : (pick.locked ? 'locked' : ''));

            if (why !== '') {
                out.refused.push({ pick: pick, why: why });
                return;
            }

            if (!has(byKey, key)) {
                byKey[key] = { key: key, picks: [] };
                out.tabs.push(byKey[key]);
            }

            byKey[key].picks.push(pick);
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
        picRects: picRects,
        picPartBox: picPartBox,
        picOpenCut: picOpenCut,
        picOpenBox: picOpenBox,
        picFootState: picFootState,
        picVh: picVh,
        markPort: markPort,
        markLeg: markLeg,
        MARK: MARK,
        markReach: markReach,
        markGrip: markGrip,
        markState: markState,
        picOrder: picOrder,
        picRefill: picRefill,
        picLong: picLong,
        PIC_FRAME: PIC_FRAME,
        picHead: picHead,
        picDoc: picDoc,
        placeKey: placeKey,
        places: places,
        LIMITS: LIMITS,
        htmlLinks: htmlLinks,
        nodeLinks: nodeLinks,
        pageLinkable: pageLinkable,
        collect: collect,
        design: design,
        markHref: markHref,
        mark: mark,
        drawable: drawable,
        nearHead: nearHead,
        markKey: markKey,
        menuPlace: menuPlace,
        nodeLabel: nodeLabel,
        hrefTo: hrefTo,
        setHtmlHref: setHtmlHref,
        hrefPatch: hrefPatch,
        applyPatch: applyPatch,
        boundTo: boundTo,
        markBound: markBound,
        drawTargets: drawTargets,
        itemAt: itemAt,
        rowDrop: rowDrop,
        focusOf: focusOf,
        fitStart: fitStart,
        ZOOM_TAP: ZOOM_TAP,
        zoomTap: zoomTap,
        listOf: listOf,
        rowTarget: rowTarget,
        brokenOf: brokenOf,
        problemsOf: problemsOf,
        emptyOf: emptyOf,
        canvasMarks: canvasMarks,
        canvasRequest: canvasRequest,
        BACK_KEY: BACK_KEY,
        backKey: backKey,
        linkSpot: linkSpot,
        backTo: backTo,
        widgetPages: widgetPages,
        widgetKind: widgetKind,
        brokenWhy: brokenWhy,
        SUGGEST_FILLER: SUGGEST_FILLER,
        foldWords: foldWords,
        pathOf: pathOf,
        searchOf: searchOf,
        searchStep: searchStep,
        searchKey: searchKey,
        suggest: suggest,
        headingOf: headingOf,
        suggestRows: suggestRows,
        suggestBatch: suggestBatch
    };
})();
