/**
 * Pinegrap - Enterprise Website Platform
 *
 * "Select similar" of the visual designer (window.PGSelectSimilar): the
 * matcher that finds the elements like the chosen one. Pure: it walks a
 * plain node tree ({ _id, type, props, children }) and returns plain
 * objects; it touches no element and reads no editor state.
 *
 * - Four ways of alike (`by`): 'kindClasses' (same kind and the same set of
 *   classes, the default), 'kind', 'oneClass' (shares a class: `cls` when
 *   given, else any of the chosen one's classes) and 'component' (a placed
 *   shared component with the same sharedId).
 * - Three places (`where`): 'siblings' (beside it: the parent's children),
 *   'section' (the nearest enclosing section, header, footer or aside; the
 *   whole tree when there is none, the default) and 'page' (the whole tree).
 * - Left out, counted per reason: 'locked' (opts.mayEdit(node) said no),
 *   'shared' (inside a placed shared component, whose children the page
 *   tree only holds when expanded in memory) and 'kept' (inside a system
 *   widget slot the server fills, a node bound to a `section` that does not
 *   print its children as designed).
 * - ways(): the ways that apply to a node, in menu order.
 * - wider: the next wider place to offer when nothing was found.
 *
 * Nothing runs at load but the definition of window.PGSelectSimilar.
 * tests/designer_select_similar_test.php runs it in Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function (root) {
    'use strict';

    var BY = ['kindClasses', 'kind', 'oneClass', 'component'];
    var WHERE = ['siblings', 'section', 'page'];

    // Semantic tags that close a section of the page for the 'section' place.
    var SECTION_TAGS = { section: 1, header: 1, footer: 1, aside: 1 };

    // Section slots whose children print as designed (the editor's
    // _SD_SECTION_KEEPS_CHILDREN and _SD_BOOKING_KEEPS_CHILDREN, the test
    // holds them equal); every other bound section is filled by the server
    // or a site script and its children are kept parts.
    var KEEPS_CHILDREN = {
        recipient_picker: 1, comment_watchers: 1, form_step_nav: 1, password_rules: 1, guest: 1,
        booking_step_service: 1, booking_step_staff: 1, booking_step_when: 1,
        booking_step_details: 1, booking_step_done: 1, booking_form: 1
    };

    function propsOf(node) {
        return (node && node.props && typeof node.props === 'object') ? node.props : {};
    }

    function kidsOf(node) {
        return (node && Array.isArray(node.children)) ? node.children : [];
    }

    // The kind of a node: its type, with the sub-kind that changes what it
    // is (a heading is not a paragraph, a section is not a span).
    function kind(node) {
        if (!node || typeof node !== 'object') return '';
        var p = propsOf(node), t = String(node.type || '');
        if (t === 'content' && p.contentType) return t + ':' + p.contentType;
        if (t === 'component' && p.componentType) return t + ':' + p.componentType;
        if (t === 'semantic' && p.tag) return t + ':' + String(p.tag).toLowerCase();
        return t;
    }

    // The node's classes, once each, in the order written.
    function classes(node) {
        var raw = propsOf(node).cssClass;
        var list = String(raw == null ? '' : raw).split(/\s+/), out = [];
        for (var i = 0; i < list.length; i++) {
            if (list[i] && out.indexOf(list[i]) === -1) out.push(list[i]);
        }
        return out;
    }

    // The same set of classes, whatever their order.
    function sameClasses(a, b) {
        if (a.length !== b.length) return false;
        for (var i = 0; i < a.length; i++) {
            if (b.indexOf(a[i]) === -1) return false;
        }
        return true;
    }

    function sharedId(node) {
        if (!node || node.type !== 'shared_ref') return 0;
        return parseInt(propsOf(node).sharedId, 10) || 0;
    }

    // The ways of alike that apply to `node`, in the order the menu lists
    // them: one of its classes needs a class, the same shared component a
    // placed component.
    function ways(node) {
        if (!node || typeof node !== 'object') return [];
        var out = ['kindClasses', 'kind'];
        if (classes(node).length) out.push('oneClass');
        if (sharedId(node)) out.push('component');
        return out;
    }

    // A test for "like the chosen one" by way `by`; null when the way does
    // not apply to it.
    function likeTest(target, by, cls) {
        var k = kind(target), cl = classes(target);
        if (by === 'kind') {
            return function (n) { return kind(n) === k; };
        }
        if (by === 'oneClass') {
            var want = cls ? [String(cls)] : cl;
            if (!want.length || (cls && cl.indexOf(String(cls)) === -1)) return null;
            return function (n) {
                var has = classes(n);
                for (var i = 0; i < want.length; i++) {
                    if (has.indexOf(want[i]) !== -1) return true;
                }
                return false;
            };
        }
        if (by === 'component') {
            var sid = sharedId(target);
            if (!sid) return null;
            return function (n) { return sharedId(n) === sid; };
        }
        return function (n) { return kind(n) === k && sameClasses(classes(n), cl); };
    }

    // The chain from the tree's root to the node (both ends included), or
    // null when the node is not in the tree. `target` is a node or its _id.
    function pathTo(tree, target) {
        var byId = typeof target === 'string';
        var stack = [];
        function walk(n) {
            if (!n || typeof n !== 'object') return false;
            stack.push(n);
            if (byId ? n._id === target : n === target) return true;
            var kids = kidsOf(n);
            for (var i = 0; i < kids.length; i++) {
                if (walk(kids[i])) return true;
            }
            stack.pop();
            return false;
        }
        return walk(tree) ? stack : null;
    }

    // The node whose subtree is the place `where` around the chosen one at
    // the end of `path`.
    function placeRoot(path, where) {
        var tree = path[0];
        if (where === 'page' || path.length < 2) return tree;
        if (where === 'siblings') return path[path.length - 2];
        for (var i = path.length - 2; i >= 0; i--) {
            var p = path[i];
            if (p.type === 'semantic' && SECTION_TAGS[String(propsOf(p).tag || '').toLowerCase()]) return p;
        }
        return tree;
    }

    // A section slot the server fills: its children are kept parts.
    function keepsOut(node, keeps) {
        var b = propsOf(node)._bindings;
        var sec = (b && typeof b.section === 'string') ? b.section : '';
        return !!sec && !keeps[sec];
    }

    /**
     * The elements like `target` in `tree`.
     *
     * @param tree    the tree the chosen one lives in (the page's, or a shared
     *                component's when it was chosen inside one)
     * @param target  the chosen node, or its _id
     * @param opts    { by, where, cls, mayEdit(node) → bool, keepsChildren }
     * @return null when the chosen one is not in the tree, else
     *         { ids, nodes, by, where, leftOut: { locked, shared, kept },
     *           leftOutTotal, wider } — `wider` is the next wider place when
     *         nothing was found and there is one, else ''.
     */
    function find(tree, target, opts) {
        opts = opts || {};
        var by = BY.indexOf(opts.by) !== -1 ? opts.by : 'kindClasses';
        var where = WHERE.indexOf(opts.where) !== -1 ? opts.where : 'section';
        var mayEdit = typeof opts.mayEdit === 'function' ? opts.mayEdit : null;
        var keeps = opts.keepsChildren || KEEPS_CHILDREN;

        var path = pathTo(tree, target);
        if (!path) return null;
        var chosen = path[path.length - 1];
        var place = placeRoot(path, where);

        var out = { ids: [], nodes: [], by: by, where: where,
            leftOut: { locked: 0, shared: 0, kept: 0 }, leftOutTotal: 0, wider: '' };
        var like = likeTest(chosen, by, opts.cls);

        if (like) {
            var take = function (n, why) {
                if (n === chosen || !like(n)) return;
                if (why) out.leftOut[why]++;
                else if (mayEdit && !mayEdit(n)) out.leftOut.locked++;
                else { out.ids.push(n._id); out.nodes.push(n); }
            };
            // The reason a node gives everything below it: a placed shared
            // component, a kept slot; '' for none.
            var reasonOf = function (n) {
                return n.type === 'shared_ref' ? 'shared' : (keepsOut(n, keeps) ? 'kept' : '');
            };
            // `why` is inherited from an enclosing node: everything below a
            // placed component or a kept slot is left out for it.
            var walk = function (n, why) {
                if (!n || typeof n !== 'object') return;
                take(n, why);
                var inner = why || reasonOf(n);
                var kids = kidsOf(n);
                for (var i = 0; i < kids.length; i++) walk(kids[i], inner);
            };
            // The reason the place itself sits under, from the root down to
            // it; the place's own children are the candidates.
            var ctx = '';
            for (var a = 0; a < path.length && !ctx; a++) {
                ctx = reasonOf(path[a]);
                if (path[a] === place) break;
            }
            var top = kidsOf(place);
            for (var c = 0; c < top.length; c++) {
                if (where === 'siblings') take(top[c], ctx);
                else walk(top[c], ctx);
            }
        }

        out.leftOutTotal = out.leftOut.locked + out.leftOut.shared + out.leftOut.kept;
        if (!out.ids.length) out.wider = widerPlace(path, where);
        return out;
    }

    // The next place out from `where` that covers more than `where` does,
    // or '' when there is none.
    function widerPlace(path, where) {
        var here = placeRoot(path, where);
        for (var i = WHERE.indexOf(where) + 1; i < WHERE.length; i++) {
            if (placeRoot(path, WHERE[i]) !== here) return WHERE[i];
        }
        return '';
    }

    root.PGSelectSimilar = {
        BY: BY.slice(),
        WHERE: WHERE.slice(),
        kind: kind,
        classes: classes,
        ways: ways,
        pathTo: pathTo,
        placeRoot: placeRoot,
        find: find
    };
})(typeof window !== 'undefined' ? window : this);
