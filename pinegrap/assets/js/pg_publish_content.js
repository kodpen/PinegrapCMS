/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Publish check's Content group: what a starter left on a page and a
 * link that goes nowhere new. Pure: no DOM, no request.
 *
 * - samples(starters): the sample values the starters write, read from
 *   what the palette builds (a block's tree, a component's default props):
 *   their texts of three words or more and every picture address. The set
 *   is plain JSON, so it can be kept once built.
 * - check(tree, opts): the rows of one page's tree.
 *     sample_text   a node still shows a starter's text, or lorem ipsum or
 *                   an example.com address at any length
 *     sample_image  a node still shows a starter's picture, or one from the
 *                   sample picture hosts the starters draw from
 *     link_self     a link opens the page it sits on, read from the page's
 *                   link rows as PgProtoHotspots.mark() marks them
 *   opts: { samples, links, page: { id, tab } }.
 * - findings(rows, texts): the rows in the Publish check's finding shape
 *   (PgPublishFindings): group `content`, level `look`, never blocking.
 * - regroup(found): the canvas audit's empty heading (`heading_empty`, found
 *   by validateTree()) moved into the Content group, so it is not found a
 *   second time here.
 *
 * A bound value is drawn per request and is no sample; a shared component
 * is checked on its own tree, never through a page's reference.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
var PgPublishContent = (function () {
    'use strict';

    // A text of fewer words is a label ("Get Started", "Contact"), kept on
    // purpose as often as left behind.
    var MIN_WORDS = 3;

    // Sample words at any length.
    var MARKERS = [/\blorem ipsum\b/i, /\bexample\.(com|net|org)\b/i];

    // The hosts the starters' sample photos and logos come from.
    var HOSTS = ['picsum.photos', 'placehold.co'];

    // Prop names that hold no words a visitor reads: classes, styles,
    // colours, addresses, ids, tags, editor names and settings.
    var NOT_TEXT = /class|css|style|colou?r|^bg|icon|href|src|url|link|^id$|tag|variant|theme|align|size|width|height|anim|font|type|target|rel|name$|key|ratio|fit|position|effect|layout|mode|image|img|poster/i;

    // Ancestors whose links lead to every page of a site, this one too.
    var NAV_TAGS = { nav: true, header: true, footer: true };

    var ENTITIES = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ' };

    var TEXT = {
        sample_text: "A starter's sample words are still on the page",
        sample_image: "A starter's sample picture is still on the page",
        link_self: 'The link opens the page it is on'
    };

    var LABELS = { heading: 'Heading', paragraph: 'Paragraph', text: 'Text', image: 'Image', link: 'Link', button: 'Button' };

    function str(v) {
        return (v === null || v === undefined) ? '' : String(v);
    }

    function isObject(v) {
        return !!v && typeof v === 'object' && !Array.isArray(v);
    }

    function list(v) {
        return Array.isArray(v) ? v : [];
    }

    function propsOf(node) {
        return (isObject(node) && isObject(node.props)) ? node.props : {};
    }

    // A text as a visitor reads it: tags and character references gone,
    // white space collapsed.
    function plain(v) {
        return str(v).replace(/<[^>]*>/g, ' ').replace(/&(#[xX][0-9a-fA-F]+|#[0-9]+|[a-zA-Z]+);/g, function (all, ref) {
            if (ref.charAt(0) === '#') {
                var code = (ref.charAt(1) === 'x' || ref.charAt(1) === 'X') ? parseInt(ref.substr(2), 16) : parseInt(ref.substr(1), 10);

                return (code > 0 && code < 0x110000) ? String.fromCodePoint(code) : all;
            }

            return Object.prototype.hasOwnProperty.call(ENTITIES, ref.toLowerCase()) ? ENTITIES[ref.toLowerCase()] : all;
        }).replace(/[\s ]+/g, ' ').trim();
    }

    function key(v) {
        return plain(v).toLowerCase();
    }

    function words(text) {
        return text === '' ? 0 : text.split(' ').length;
    }

    // The string values of a node's props that hold words: own names only
    // (`_bindings`, `_attrs` and the like are the editor's), a bound one
    // left out, nested items (an accordion's) walked the same way.
    function textsOf(props) {
        var bound = isObject(props._bindings) ? props._bindings : {};
        var out = [];

        function walk(value, name, depth) {
            if (depth > 4 || NOT_TEXT.test(name)) {
                return;
            }

            if (typeof value === 'string') {
                if (/\p{L}/u.test(value)) {
                    out.push(value);
                }
            } else if (Array.isArray(value)) {
                value.forEach(function (one) { walk(one, name, depth + 1); });
            } else if (isObject(value)) {
                Object.keys(value).forEach(function (k) { walk(value[k], k, depth + 1); });
            }
        }

        Object.keys(props).forEach(function (k) {
            if (k.charAt(0) !== '_' && !bound[k]) {
                walk(props[k], k, 0);
            }
        });

        return out;
    }

    // The picture addresses a node's props draw: an image's own prop
    // (`src`, `image`, `poster`, …) and every url() of a style, attributes
    // included; never a link's href, nor a bound value.
    function imagesOf(props) {
        var bound = isObject(props._bindings) ? props._bindings : {};
        var out = [];

        function add(url) {
            url = str(url).trim();

            if (url !== '' && out.indexOf(url) === -1) {
                out.push(url);
            }
        }

        function walk(value, name, depth) {
            if (depth > 4 || /href|link/i.test(name)) {
                return;
            }

            if (typeof value === 'string') {
                var re = /url\(\s*(['"]?)([^'")]+)\1\s*\)/gi;
                var m;

                while ((m = re.exec(value)) !== null) {
                    add(m[2]);
                }

                if (/^(src|image|img|poster|bg_?image|background)$/i.test(name) && !/^\s*(data:|url\()/i.test(value)) {
                    add(value);
                }
            } else if (Array.isArray(value)) {
                value.forEach(function (one) { walk(one, name, depth + 1); });
            } else if (isObject(value)) {
                Object.keys(value).forEach(function (k) { walk(value[k], k, depth + 1); });
            }
        }

        Object.keys(props).forEach(function (k) {
            if (!bound[k]) {
                walk(props[k], k, 0);
            }
        });

        return out;
    }

    // Every node of a tree, a shared component's reference left closed;
    // `fn(node, inNav)`.
    function each(tree, fn) {
        function walk(node, inNav) {
            if (!isObject(node)) {
                return;
            }

            var p = propsOf(node);
            var nav = inNav || (node.type === 'semantic' && NAV_TAGS[str(p.tag).toLowerCase()] === true)
                || (node.type === 'component' && p.componentType === 'navbar');

            fn(node, inNav);

            if (node.type !== 'shared_ref') {
                list(node.children).forEach(function (child) { walk(child, nav); });
            }
        }

        walk(tree, false);
    }

    // A starter is a tree (a node with props) or a component's props.
    function samples(starters) {
        var texts = {};
        var images = {};

        list(starters).filter(isObject).forEach(function (starter) {
            var visit = function (props) {
                textsOf(props).forEach(function (t) {
                    var k = key(t);

                    if (words(k) >= MIN_WORDS) {
                        texts[k] = true;
                    }
                });
                imagesOf(props).forEach(function (u) { images[u] = true; });
            };

            if (isObject(starter.props)) {
                each(starter, function (node) { visit(propsOf(node)); });
            } else {
                visit(starter);
            }
        });

        return { texts: Object.keys(texts).sort(), images: Object.keys(images).sort() };
    }

    function hostOf(url) {
        var m = /^(?:https?:)?\/\/([^\/?#:]+)/i.exec(str(url).trim());

        return m ? m[1].toLowerCase().replace(/^www\./, '') : '';
    }

    function labelOf(node) {
        var p = propsOf(node);

        return plain(p.customName) || LABELS[str(p.contentType)] || str(p.componentType) || str(p.tag) || str(node.type);
    }

    function row(code, node, f) {
        return {
            code: code,
            node: str(node),
            shared: parseInt(f.shared, 10) || 0,
            index: parseInt(f.index, 10) || 0,
            where: str(f.where),
            detail: str(f.detail),
            count: Math.max(1, parseInt(f.count, 10) || 1)
        };
    }

    // The link rows of the page that open the page itself: by the tab's
    // key, or by the saved page's id; an address with a fragment goes to a
    // place on the page and is kept. A link inside the page's navigation,
    // header or footer is one of a site's own links to every page.
    function selfLinks(links, page, navIds, shared) {
        var id = parseInt(page.id, 10) || 0;
        var tab = str(page.tab);

        return list(links).filter(function (r) {
            if (!isObject(r) || r.source === 'menu' || r.source === 'region' || navIds[str(r.nodeId)]) {
                return false;
            }

            if (str(r.href).indexOf('#') !== -1) {
                return false;
            }

            return (tab !== '' && r.mark === 'tab' && str(r.tab) === tab)
                || (id > 0 && (r.mark === 'tab' || r.mark === 'page') && (parseInt(r.pageId, 10) || 0) === id);
        }).map(function (r) {
            return row('link_self', r.nodeId, { shared: shared, index: r.index, where: r.label, detail: r.href });
        });
    }

    // The rows of one page's tree (or one shared component's, `opts.shared`
    // its id).
    function check(tree, opts) {
        var o = isObject(opts) ? opts : {};
        var s = isObject(o.samples) ? o.samples : {};
        var texts = {};
        var images = {};
        var navIds = {};
        var out = [];

        list(s.texts).forEach(function (t) { texts[str(t)] = true; });
        list(s.images).forEach(function (u) { images[str(u)] = true; });

        each(tree, function (node, inNav) {
            var p = propsOf(node);
            var id = str(node._id);
            var where = labelOf(node);
            var found = [];

            if (inNav) {
                navIds[id] = true;
            }

            if (node.type === 'shared_ref') {
                return;
            }

            textsOf(p).forEach(function (t) {
                var k = key(t);
                var marked = MARKERS.some(function (re) { return re.test(k); });

                if (marked || (words(k) >= MIN_WORDS && texts[k])) {
                    found.push(plain(t));
                }
            });

            if (found.length) {
                out.push(row('sample_text', id, { shared: o.shared, where: where, detail: found[0], count: found.length }));
            }

            found = imagesOf(p).filter(function (u) {
                return images[u] === true || HOSTS.indexOf(hostOf(u)) !== -1;
            });

            if (found.length) {
                out.push(row('sample_image', id, { shared: o.shared, where: where, detail: found[0], count: found.length }));
            }
        });

        return out.concat(selfLinks(o.links, isObject(o.page) ? o.page : {}, navIds, o.shared));
    }

    // The Publish check's finding shape (PgPublishFindings' make()).
    function findings(rows, texts) {
        return list(rows).filter(isObject).map(function (r) {
            var code = str(r.code);
            var node = str(r.node);
            var given = (texts && typeof texts[code] === 'string' && texts[code] !== '') ? texts[code] : '';

            return {
                id: 'content:' + code + ':' + node + ':' + (parseInt(r.index, 10) || 0),
                source: 'content',
                code: code,
                group: 'content',
                level: 'look',
                text: given || TEXT[code] || code,
                node: node,
                shared: parseInt(r.shared, 10) || 0,
                where: str(r.where),
                count: Math.max(1, parseInt(r.count, 10) || 1),
                detail: str(r.detail),
                seo: '',
                concept: ''
            };
        });
    }

    // The canvas audit's rules that belong to the Content group.
    var AUDIT_CONTENT = { heading_empty: true };

    function regroup(found) {
        return list(found).filter(isObject).map(function (f) {
            if (f.source !== 'audit' || AUDIT_CONTENT[f.code] !== true) {
                return f;
            }

            var copy = {};

            Object.keys(f).forEach(function (k) { copy[k] = f[k]; });
            copy.group = 'content';

            return copy;
        });
    }

    return {
        samples: samples,
        check: check,
        findings: findings,
        regroup: regroup,
        read: function (tree, opts, texts) { return findings(check(tree, opts), texts); }
    };
}());
