/**
 * Pinegrap - Enterprise Website Platform
 *
 * The link resolver in the browser (window.PGLinkResolve): what one href on
 * a page leads to, answered as includes/fn/link_resolve.php answers it, so a
 * link the server calls broken is broken in the editor too. Each function is
 * the twin of the PHP one named beside it and takes the same arguments: the
 * site values (pg_link_resolve_env()) and the name maps (pg_seo_link_maps(),
 * pg_link_maps_for_items()) as JSON objects. tests/link_resolve_js_test.php
 * runs both on the same hrefs and compares the answers; a change to one is
 * made to the other.
 *
 * Nothing runs at load but the definition of window.PGLinkResolve, so it may
 * be printed with or without defer, before its user. Served minified as
 * assets/js/pg_link_resolve.min.js (pg_js_bundles(), includes/fn/output.php);
 * after editing run `php tools/build_js.php`.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var KINDS = ['pages', 'files', 'short_links', 'products', 'product_groups'];
    var SCHEMES = { 'mailto:': 'mailto', 'tel:': 'tel', 'sms:': 'sms', 'javascript:': 'script', 'data:': 'data', 'ftp:': 'ftp', '#': 'anchor' };

    function has(object, key) {
        return !!object && Object.prototype.hasOwnProperty.call(object, key);
    }

    function answer(type, cls) {
        return { type: type, id: 0, 'class': cls };
    }

    function lower(text) {
        return String(text).toLowerCase();
    }

    // PHP's trim() of white space (space, tab, new lines, NUL, vertical tab).
    function trimSpace(text) {
        return String(text).replace(/^[ \t\n\r\0\x0B]+|[ \t\n\r\0\x0B]+$/g, '');
    }

    function trimSlashes(text, left, right) {
        var out = String(text);

        if (left) {
            out = out.replace(/^\/+/, '');
        }

        if (right) {
            out = out.replace(/\/+$/, '');
        }

        return out;
    }

    // rawurldecode(): %XX sequences decoded as UTF-8, '+' kept; a sequence
    // that is no valid UTF-8 is kept as written.
    function rawUrlDecode(text) {
        return String(text).replace(/(%[0-9a-f]{2})+/gi, function (run) {
            try {
                return decodeURIComponent(run);
            } catch (error) {
                return run;
            }
        });
    }

    // The parts of parse_url() the resolver reads: host and path; null where
    // parse_url() answers false (an authority with no host).
    function parseUrl(href) {
        var match = /^(?:([a-z][a-z0-9+.\-]*):)?(\/\/([^\/?#]*))?([^?#]*)/i.exec(href);
        var out = { host: '', path: '' };

        if (!match) {
            return out;
        }

        // "host:80/x" without a scheme reads as a host and a port.
        if (match[2] !== undefined) {
            var authority = match[3].replace(/^[^@]*@/, '');
            var host = authority.replace(/:\d*$/, '');

            if (host === '') {
                return null;
            }

            out.host = host;
        }

        out.path = match[4] || '';

        return out;
    }

    // pg_link_parse()
    function parse(href, env) {
        href = trimSpace(href === null || href === undefined ? '' : href);
        env = env || {};

        if (href === '') {
            return { result: answer('unknown', 'empty'), item: '' };
        }

        var scheme = /^(mailto:|tel:|sms:|javascript:|data:|ftp:|#)/i.exec(href);

        if (scheme) {
            return { result: answer('external', SCHEMES[lower(scheme[1])]), item: '' };
        }

        var parts = parseUrl(href);

        if (parts === null) {
            return { result: answer('unknown', 'invalid'), item: '' };
        }

        if (parts.host !== '') {
            var ownHost = String(env.host || '').replace(/:\d+$/, '');
            var linkHost = parts.host.replace(/:\d+$/, '');

            if ((ownHost === '') || (lower(linkHost) !== lower(ownHost))) {
                return { result: answer('external', 'host'), item: '' };
            }
        }

        var path = parts.path;

        if (path === '') {
            return { result: answer('external', 'self'), item: '' };
        }

        var sitePath = has(env, 'path') && (env.path !== null) ? String(env.path) : '/';

        if (path.indexOf('/') !== 0) {
            path = sitePath + path;
        }

        if ((sitePath === '') || (path.indexOf(sitePath) !== 0)) {
            return { result: answer('external', 'outside'), item: '' };
        }

        var item = trimSlashes(rawUrlDecode(path.substr(sitePath.length)), true, false);
        var folded = lower(item);

        if ((folded === 'robots.txt') || (folded === 'sitemap.xml')) {
            return { result: answer('external', 'robots'), item: '' };
        }

        if ((env.software_directory !== null) && (env.software_directory !== undefined) && (item.indexOf(env.software_directory + '/') === 0)) {
            return { result: answer('external', 'backend'), item: '' };
        }

        return { result: null, item: item };
    }

    // pg_link_map_key()
    function mapKey(maps, kind, name) {
        name = String(name);

        var map = (maps && maps[kind]) || {};

        if (has(map, name)) {
            return name;
        }

        var folded = lower(name);

        if (maps && maps.folded && maps.folded[kind]) {
            return has(maps.folded[kind], folded) ? String(maps.folded[kind][folded]) : null;
        }

        var keys = Object.keys(map);

        for (var i = 0; i < keys.length; i++) {
            if (lower(keys[i]) === folded) {
                return keys[i];
            }
        }

        return null;
    }

    // pg_link_maps_fold(): a copy with the folded index added.
    function fold(maps) {
        var out = {};

        Object.keys(maps || {}).forEach(function (key) {
            out[key] = maps[key];
        });

        out.folded = {};

        KINDS.forEach(function (kind) {
            var index = {};

            Object.keys(out[kind] || {}).forEach(function (key) {
                var folded = lower(key);

                if (!has(index, folded)) {
                    index[folded] = key;
                }
            });

            out.folded[kind] = index;
        });

        return out;
    }

    // pg_link_resolve_item(); formNames is an object (name => true) or a
    // function returning one, called only for a form list address.
    function resolveItem(item, maps, formNames) {
        item = String(item);
        maps = maps || {};

        var pages = maps.pages || {};
        var unknown = answer('unknown', 'unknown');

        if ((item === '') || (item === 'index.php')) {
            return { type: 'page', id: parseInt(maps.home_page_id, 10) || 0, 'class': 'page' };
        }

        var file = mapKey(maps, 'files', item);

        if (file !== null) {
            return { type: 'file', id: parseInt(maps.files[file], 10) || 0, 'class': 'file' };
        }

        if (mapKey(maps, 'short_links', item) !== null) {
            return answer('short_link', 'short_link');
        }

        var name = mapKey(maps, 'pages', item);

        if (name !== null) {
            return { type: 'page', id: parseInt(pages[name].id, 10) || 0, 'class': 'page' };
        }

        if (item.indexOf('/') < 0) {
            return unknown;
        }

        var prefix = mapKey(maps, 'pages', item.substr(0, item.indexOf('/')));
        var slug = trimSlashes(item.substr(item.indexOf('/') + 1), true, true);

        if (prefix === null) {
            return unknown;
        }

        var page = pages[prefix];
        var reachesPage = { type: 'page', id: parseInt(page.id, 10) || 0, 'class': 'page' };

        if (slug === '') {
            return reachesPage;
        }

        if ((page.type === 'catalog') || (page.type === 'catalog detail') || (page.layout_type === 'system')) {
            var product = mapKey(maps, 'products', slug);

            if (product !== null) {
                return { type: 'product', id: parseInt(maps.products[product], 10) || 0, 'class': 'product' };
            }

            var group = mapKey(maps, 'product_groups', slug);

            if (group !== null) {
                return { type: 'product_group', id: parseInt(maps.product_groups[group], 10) || 0, 'class': 'product_group' };
            }

            return (page.layout_type === 'system') ? reachesPage : unknown;
        }

        if (page.type === 'form list view') {
            var names = (typeof formNames === 'function') ? formNames() : formNames;

            return (mapKey({ form_names: names || {} }, 'form_names', slug) !== null) ? reachesPage : unknown;
        }

        return reachesPage;
    }

    // pg_link_resolve()
    function resolve(href, maps, env, formNames) {
        var parsed = parse(href, env);

        return (parsed.result !== null) ? parsed.result : resolveItem(parsed.item, maps, formNames);
    }

    // pg_link_lookup_names()
    function lookupNames(items) {
        var names = [];
        var slugs = [];

        function add(list, value) {
            if (list.indexOf(value) < 0) {
                list.push(value);
            }
        }

        (items || []).forEach(function (item) {
            item = String(item);

            if (item === '') {
                return;
            }

            add(names, item);

            if (item.indexOf('/') >= 0) {
                add(names, item.substr(0, item.indexOf('/')));

                var slug = trimSlashes(item.substr(item.indexOf('/') + 1), true, true);

                if (slug !== '') {
                    add(slugs, slug);
                }
            }
        });

        return { items: names, slugs: slugs };
    }

    window.PGLinkResolve = {
        parse: parse,
        mapKey: mapKey,
        fold: fold,
        resolveItem: resolveItem,
        resolve: resolve,
        lookupNames: lookupNames
    };
})();
