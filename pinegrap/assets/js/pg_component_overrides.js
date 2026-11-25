/**
 * Pinegrap - Enterprise Website Platform
 *
 * Per-place values of a shared component, the browser twin of
 * includes/fn/component_overrides.php (window.PGComponentOverrides). A node
 * of a component's tree may open some of its fields to the places that use
 * the component: `props._open` is a list of {id, kind, label}. A placing
 * shared_ref node keeps its own values in `props._place`, an object keyed by
 * the open field's stable id. Pure: it reads what it is handed; apply()
 * answers a copy and never changes the tree it is given.
 *
 * - KINDS: text (props.text), picture (props.src, props.alt), link
 *   (props.href), icon (props.iconName), shown (false leaves the node out).
 * - openFields(tree): [{id, kind, label, nodeId, value}] in document order,
 *   value being the component's own; a placed component inside is not
 *   entered; an id met twice keeps its first node.
 * - apply(tree, values): the tree as drawn at a place; with nothing to
 *   apply, the same tree object comes back.
 * - leftovers(tree, values): ids of values no open field takes any more.
 * - newFieldId(taken), validFieldId(id), cleanValue(kind, value),
 *   placeValues(refNode).
 *
 * Nothing runs at load but the definition. tests/component_overrides_test.php
 * runs it in Node beside the PHP functions.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function () {
    'use strict';

    var KINDS = {
        text: ['text'],
        picture: ['src', 'alt'],
        link: ['href'],
        icon: ['iconName'],
        shown: []
    };

    function has(obj, key) {
        return Object.prototype.hasOwnProperty.call(obj, key);
    }

    function isObject(v) {
        return v !== null && typeof v === 'object' && !Array.isArray(v);
    }

    function validFieldId(id) {
        return typeof id === 'string' && /^[a-z][a-z0-9_]{0,39}$/.test(id);
    }

    // Random so that a field opened after another was closed never inherits
    // the old field's values.
    function newFieldId(taken) {
        var chars = 'abcdefghijklmnopqrstuvwxyz0123456789', seen = {}, id, i;
        (taken || []).forEach(function (t) { seen[String(t)] = true; });
        do {
            id = 'of_';
            for (i = 0; i < 8; i++) {
                id += chars.charAt(Math.floor(Math.random() * 36));
            }
        } while (has(seen, id));
        return id;
    }

    function nodeOpen(node) {
        var list = isObject(node) && isObject(node.props) ? node.props._open : null, out = [];
        if (!Array.isArray(list)) return out;
        list.forEach(function (entry) {
            if (!isObject(entry) || !validFieldId(entry.id)) return;
            if (typeof entry.kind !== 'string' || !has(KINDS, entry.kind)) return;
            out.push({
                id: entry.id,
                kind: entry.kind,
                label: typeof entry.label === 'string' ? entry.label.trim() : ''
            });
        });
        return out;
    }

    function defaultValue(node, kind) {
        var props = isObject(node.props) ? node.props : {};
        function str(key) {
            var v = props[key];
            return (typeof v === 'string' || typeof v === 'number') ? String(v) : '';
        }
        switch (kind) {
            case 'picture': return {src: str('src'), alt: str('alt')};
            case 'shown': return true;
            case 'text': return str('text');
            case 'link': return str('href');
            case 'icon': return str('iconName');
        }
        return null;
    }

    function openFields(tree) {
        var fields = [], seen = {};
        (function walk(node) {
            if (!isObject(node)) return;
            nodeOpen(node).forEach(function (entry) {
                if (has(seen, entry.id)) return;
                seen[entry.id] = true;
                fields.push({
                    id: entry.id,
                    kind: entry.kind,
                    label: entry.label,
                    nodeId: node.id === undefined || node.id === null ? '' : String(node.id),
                    value: defaultValue(node, entry.kind)
                });
            });
            if (node.type === 'shared_ref') return;
            if (Array.isArray(node.children)) node.children.forEach(walk);
        })(tree);
        return fields;
    }

    function cleanValue(kind, value) {
        switch (kind) {
            case 'text':
            case 'link':
            case 'icon':
                return typeof value === 'string' ? value : null;
            case 'shown':
                return typeof value === 'boolean' ? value : null;
            case 'picture':
                if (typeof value === 'string') return {src: value, alt: null};
                if (!isObject(value) || typeof value.src !== 'string') return null;
                return {src: value.src, alt: typeof value.alt === 'string' ? value.alt : null};
        }
        return null;
    }

    function placeValues(ref) {
        return isObject(ref) && isObject(ref.props) && isObject(ref.props._place) ? ref.props._place : {};
    }

    function apply(tree, values) {
        if (!isObject(tree) || !isObject(values) || !Object.keys(values).length) return tree;
        var fields = openFields(tree), usable = {}, any = false, seen = {};
        fields.forEach(function (field) {
            if (!has(values, field.id)) return;
            var clean = cleanValue(field.kind, values[field.id]);
            if (clean !== null) { usable[field.id] = clean; any = true; }
        });
        if (!any) return tree;

        // Answers the node with its values, or null when it is not shown.
        // The root itself is never left out.
        function walk(node, root) {
            if (!isObject(node)) return node;
            var entries = nodeOpen(node), i, entry, value;
            for (i = 0; i < entries.length; i++) {
                entry = entries[i];
                if (has(seen, entry.id)) continue;
                seen[entry.id] = true;
                if (!has(usable, entry.id)) continue;
                value = usable[entry.id];
                switch (entry.kind) {
                    case 'shown':
                        if (value === false && !root) return null;
                        break;
                    case 'text':
                        node.props.text = value;
                        break;
                    case 'link':
                        node.props.href = value;
                        break;
                    case 'icon':
                        node.props.iconName = value;
                        break;
                    case 'picture':
                        node.props.src = value.src;
                        if (value.alt !== null) node.props.alt = value.alt;
                        break;
                }
            }
            if (node.type === 'shared_ref') return node;
            if (Array.isArray(node.children)) {
                node.children = node.children.map(function (child) {
                    return walk(child, false);
                }).filter(function (child) {
                    return child !== null;
                });
            }
            return node;
        }

        return walk(JSON.parse(JSON.stringify(tree)), true);
    }

    function leftovers(tree, values) {
        if (!isObject(values)) return [];
        var kinds = {};
        openFields(tree).forEach(function (field) { kinds[field.id] = field.kind; });
        return Object.keys(values).filter(function (id) {
            return !has(kinds, id) || cleanValue(kinds[id], values[id]) === null;
        });
    }

    window.PGComponentOverrides = {
        KINDS: KINDS,
        validFieldId: validFieldId,
        newFieldId: newFieldId,
        openFields: openFields,
        cleanValue: cleanValue,
        placeValues: placeValues,
        apply: apply,
        leftovers: leftovers
    };
})();
