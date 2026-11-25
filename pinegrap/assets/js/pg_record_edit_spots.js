/**
 * Pinegrap - Enterprise Website Platform
 *
 * Which bound elements of a page the designer's Preview lets a person
 * correct in place for one collection item, and with which control
 * (window.PGRecordEditSpots). Pure: it reads a page tree and the item's
 * fields and touches no element.
 *
 * - fields: the `fields` of the record_edit_read answer (id, name, label,
 *   control, rss), the item's fields that have a control in place
 *   (pg_rec_edit_control(): text, rich, number, date, picture).
 * - Inside the item's own detail widget (form_item_view of its form) a
 *   binding names a field as the widget's binding pass reads it
 *   (pg_sw_index_form_data()): its name, the name stripped to
 *   [a-z0-9_], its label, `field_<id>`, case aside; `__post_title` and
 *   `__post_image` name the fields of the RSS title and picture roles.
 * - Outside widgets, when the page's first record widget opens a record of
 *   the item's form, `__record__field_<name>` (the name lowercased and
 *   stripped to [a-z0-9_], the first field when two strip alike),
 *   `__record__post_title` and `__record__post_image` do
 *   (pg_page_record_bind_props()). A shared component is walked in the
 *   scope it stands in; a list or another record's widget is not walked.
 * - A property takes the controls that write what it shows: text the text,
 *   formatted text, number and date controls; html formatted text; src a
 *   picture; alt a line of text. An address (href), a summary and a field
 *   of another control are not edited in place.
 *
 * Nothing runs at load but the definition of window.PGRecordEditSpots.
 * tests/record_edit_spots_test.php runs it in Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // The controls each bound property takes, in the order they are tried.
    var PROPS = {
        text: ['text', 'rich', 'number', 'date'],
        html: ['rich'],
        src: ['picture'],
        alt: ['text']
    };

    // Widget kinds that open the page's record (pg_page_record_entities()).
    var RECORD_KINDS = { form_item_view: 1, catalog_item_view: 1, calendar_event_view: 1 };

    // The RSS roles a post token reads (pg_sw_entity_post_values()).
    var POST_ROLES = { post_title: 'title', post_image: 'media' };

    function accepts(prop, control) {
        return Object.prototype.hasOwnProperty.call(PROPS, prop) && PROPS[prop].indexOf(control) !== -1;
    }

    function lower(v) {
        return String(v == null ? '' : v).trim().toLowerCase();
    }

    // Field lookups for both scopes, built once per call: `widget` maps every
    // name the detail widget's binding pass answers to the first field it
    // names; `page` maps the page-record token of a name.
    function index(fields) {
        var widget = {}, page = {}, roles = {};
        var put = function (map, key, f) {
            if (key !== '' && !Object.prototype.hasOwnProperty.call(map, key)) map[key] = f;
        };
        (Array.isArray(fields) ? fields : []).forEach(function (f) {
            if (!f || !f.control) return;
            var name = lower(f.name);
            put(widget, name, f);
            put(widget, name.replace(/[^a-z0-9_]/g, ''), f);
            put(widget, lower(f.label), f);
            put(widget, 'field_' + f.id, f);
            put(page, '__record__field_' + name.replace(/[^a-z0-9_]/g, ''), f);
            put(roles, lower(f.rss), f);
        });
        Object.keys(POST_ROLES).forEach(function (t) {
            var f = roles[POST_ROLES[t]];
            if (!f) return;
            put(widget, '__' + t, f);
            put(page, '__record__' + t, f);
        });
        return { widget: widget, page: page };
    }

    function widgetOf(node, opts) {
        if (!node || node.type !== 'shared_ref' || typeof opts.lookup !== 'function') return null;
        var w = opts.lookup(node);
        return (w && typeof w === 'object') ? w : null;
    }

    function isItemWidget(w, opts) {
        return !!w && w.kind === 'form_item_view' && parseInt(w.formId, 10) === parseInt(opts.formId, 10);
    }

    // Whether the page's first record widget, in drawing order, opens a
    // record of the item's form: the first one publishes the page's record.
    function opensItem(tree, opts) {
        var found = null;
        var walk = function (n, depth) {
            if (!n || found !== null || depth > 50) return;
            var w = widgetOf(n, opts);
            if (w) {
                if (RECORD_KINDS[w.kind]) { found = isItemWidget(w, opts); return; }
                if (!w.kind) walk(w.tree, depth + 1);
                return;
            }
            (Array.isArray(n.children) ? n.children : []).forEach(function (c) { walk(c, depth + 1); });
        };
        walk(tree, 0);
        return found === true;
    }

    /**
     * The spots of `tree` that show one of `fields`, in drawing order, each
     * as { node, prop, field, control, token, widget }: the node's _id, the
     * bound property, the field's id and control, the binding as written,
     * and the _id of the outermost reference (widget or shared component)
     * the node is drawn through, '' on the page itself.
     *
     * opts: formId (the item's form), lookup(node) giving a shared_ref's
     * { kind, formId, tree } ('' kind for a shared component) or null;
     * scope 'widget' when `tree` is the detail widget's own tree;
     * pageRecord true/false to say whether the page opens the item, else
     * read from its first record widget.
     */
    function find(tree, fields, opts) {
        opts = opts || {};
        var out = [];
        if (!tree || !Array.isArray(fields) || !fields.length) return out;
        var maps = index(fields);
        var pageOpens = (typeof opts.pageRecord === 'boolean') ? opts.pageRecord : opensItem(tree, opts);

        var walk = function (n, scope, via, depth) {
            if (!n || depth > 50) return;
            var w = widgetOf(n, opts);
            if (w) {
                var ref = via || String(n._id == null ? '' : n._id);
                if (isItemWidget(w, opts)) walk(w.tree, 'widget', ref, depth + 1);
                else if (!w.kind) walk(w.tree, scope, ref, depth + 1);
                return;
            }
            var b = n.props && n.props._bindings;
            if (b && typeof b === 'object' && (scope === 'widget' || pageOpens)) {
                var map = (scope === 'widget') ? maps.widget : maps.page;
                Object.keys(b).forEach(function (prop) {
                    if (typeof b[prop] !== 'string' || !PROPS[prop]) return;
                    var key = lower(b[prop]);
                    var f = Object.prototype.hasOwnProperty.call(map, key) ? map[key] : null;
                    if (!f || !accepts(prop, f.control)) return;
                    out.push({
                        node: String(n._id == null ? '' : n._id),
                        prop: prop,
                        field: parseInt(f.id, 10),
                        control: f.control,
                        token: b[prop],
                        widget: via
                    });
                });
            }
            (Array.isArray(n.children) ? n.children : []).forEach(function (c) { walk(c, scope, via, depth + 1); });
        };
        walk(tree, opts.scope === 'widget' ? 'widget' : 'page', '', 0);
        return out;
    }

    window.PGRecordEditSpots = {
        PROPS: PROPS,
        accepts: accepts,
        find: find
    };
})();
