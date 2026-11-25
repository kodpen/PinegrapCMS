/**
 * Pinegrap - Enterprise Website Platform
 *
 * The page as a keyboard and a screen reader meet it, for the visual
 * designer's Accessibility view (window.PGA11yReader). Pure: it reads a tree
 * of elements and answers lists; it changes nothing and draws nothing.
 *
 * The tree is plain data, one object per element:
 *   { tag: 'a', attrs: { href: '/x' }, children: [ … ], node: 'n12',
 *     hidden: false, ref: <anything> }
 * and { text: '…' } (or a bare string) per text. `hidden` is what the caller
 * knows the browser hides (a computed display: none or visibility: hidden);
 * `node` is the designer node the element draws; `ref` is handed back
 * untouched. fromDom() builds the tree from the canvas's elements.
 *
 * - tabOrder(): the stops Tab reaches, in order, as a browser walks them:
 *   positive tabindex first, then document order; hidden, inert and disabled
 *   elements skipped; one stop per radio group. Where a script moves the
 *   focus the reader cannot know it and says nothing.
 * - jumps(): which steps of that order go back up or to the left on the
 *   page, from the boxes the caller measured.
 * - headings(): the outline, each heading's level, a skipped level marked.
 * - regions(): the landmarks with their role and name.
 * - pictures(): each picture's text, decoration, or no text.
 * - controls(): links, buttons, fields and frames with their names.
 * - read(): all of them over one walk of the tree.
 *
 * A fault carries the code of the accessibility audit's rule
 * (includes/fn/a11y.php, pg_a11y_check_html()) and is raised only where that
 * rule raises it, so the view shows the audit's sentence and finds nothing
 * the audit does not know.
 *
 * Nothing runs at load but the definition of window.PGA11yReader.
 * tests/a11y_reader_test.php runs it in Node, beside the audit's PHP rules.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // Elements whose content is never read or reached.
    var SKIPPED = { script: true, style: true, template: true, noscript: true };

    // Form controls a disabled attribute or a disabled fieldset turns off.
    var DISABLEABLE = { button: true, input: true, select: true, textarea: true, fieldset: true, optgroup: true, option: true };

    // Landmark roles (WAI-ARIA 1.2), and the elements that carry one.
    var LANDMARKS = { banner: true, complementary: true, contentinfo: true, form: true, main: true, navigation: true, region: true, search: true };
    var IMPLICIT = { main: 'main', nav: 'navigation', aside: 'complementary', search: 'search', header: 'banner', footer: 'contentinfo', section: 'region', form: 'form' };

    // A header or footer inside one of these is no banner or contentinfo.
    var SECTIONING = { article: true, aside: true, main: true, nav: true, section: true };

    // Input types that take no label (pg_a11y_rule_form_label()).
    var UNLABELLED_INPUTS = { hidden: true, submit: true, button: true, reset: true, image: true };

    function has(object, key) {
        return !!object && Object.prototype.hasOwnProperty.call(object, key);
    }

    function str(value) {
        return (value === null || value === undefined) ? '' : String(value);
    }

    function squash(value) {
        return str(value).replace(/\s+/g, ' ').replace(/^ | $/g, '');
    }

    function tagOf(el) {
        return str(el && el.tag).toLowerCase();
    }

    function attr(el, name) {
        return (el && has(el.attrs, name)) ? str(el.attrs[name]) : null;
    }

    function hasAttr(el, name) {
        return attr(el, name) !== null;
    }

    function attrLower(el, name) {
        return str(attr(el, name)).replace(/^\s+|\s+$/g, '').toLowerCase();
    }

    function isText(child) {
        return (typeof child === 'string') || (!!child && (typeof child === 'object') && !has(child, 'tag') && has(child, 'text'));
    }

    function textOf(child) {
        return (typeof child === 'string') ? child : str(child.text);
    }

    function childrenOf(el) {
        return (el && Array.isArray(el.children)) ? el.children : [];
    }

    function styleSays(el, property, value) {
        var re = new RegExp('(^|;)\\s*' + property + '\\s*:\\s*' + value + '\\s*(!important\\s*)?(;|$)', 'i');
        return re.test(str(attr(el, 'style')));
    }

    function tabindexOf(el) {
        var raw = attr(el, 'tabindex');

        if (raw === null || !/^\s*[-+]?\d+\s*$/.test(raw)) {
            return null;
        }

        return parseInt(raw, 10);
    }

    function idrefs(value) {
        return str(value).split(/\s+/).filter(function (id) { return id !== ''; });
    }

    // ---- The walk ---------------------------------------------------------

    // One entry per element, in document order, with what its ancestors
    // decide: whether it is drawn, in the accessibility tree, disabled, and
    // which designer node and landmark it sits in.
    function index(root) {
        var doc = { all: [], ids: {}, labelsFor: {} };

        function walk(el, up) {
            if (!el || isText(el) || (typeof el !== 'object')) {
                return;
            }

            var tag = tagOf(el);
            var item = { el: el, tag: tag, parent: up.item, pos: doc.all.length, node: str(el.node) || up.node };
            var parentTag = up.item ? up.item.tag : '';

            item.unrendered = up.unrendered || !!el.hidden || hasAttr(el, 'hidden') || hasAttr(el, 'inert')
                || styleSays(el, 'display', 'none') || styleSays(el, 'visibility', '(hidden|collapse)')
                || ((parentTag === 'details') && !hasAttr(up.item.el, 'open') && !(tag === 'summary' && up.firstSummary))
                || ((tag === 'dialog') && !hasAttr(el, 'open')) || has(SKIPPED, tag);
            // aria-hidden takes an element out of what a screen reader
            // reads, but not out of the keyboard's way.
            item.unread = up.unread || item.unrendered || (attrLower(el, 'aria-hidden') === 'true');
            item.disabled = (has(DISABLEABLE, tag) && hasAttr(el, 'disabled')) || up.disabledFieldset;
            item.inLabel = up.inLabel || (tag === 'label');
            item.form = (tag === 'form') ? item : up.form;

            doc.all.push(item);

            if (attr(el, 'id') !== null && !has(doc.ids, attr(el, 'id'))) {
                doc.ids[attr(el, 'id')] = item;
            }

            if (tag === 'label' && str(attr(el, 'for')).replace(/\s/g, '') !== '') {
                doc.labelsFor[attr(el, 'for')] = doc.labelsFor[attr(el, 'for')] || [];
                doc.labelsFor[attr(el, 'for')].push(item);
            }

            var kids = childrenOf(el);
            var seenSummary = false;
            var firstLegend = null;

            if (tag === 'fieldset' && hasAttr(el, 'disabled')) {
                for (var l = 0; l < kids.length; l++) {
                    if (!isText(kids[l]) && tagOf(kids[l]) === 'legend') {
                        firstLegend = kids[l];
                        break;
                    }
                }
            }

            for (var i = 0; i < kids.length; i++) {
                var kid = kids[i];
                var isSummary = !isText(kid) && tagOf(kid) === 'summary' && !seenSummary;

                if (isSummary) {
                    seenSummary = true;
                }

                walk(kid, {
                    item: item,
                    node: item.node,
                    unrendered: item.unrendered,
                    unread: item.unread,
                    // A disabled fieldset turns off what it holds but its
                    // first legend.
                    disabledFieldset: up.disabledFieldset || (tag === 'fieldset' && hasAttr(el, 'disabled') && kid !== firstLegend),
                    inLabel: item.inLabel,
                    form: item.form,
                    firstSummary: tag === 'details' && isSummary
                });
            }
        }

        walk(root, { item: null, node: '', unrendered: false, unread: false, disabledFieldset: false, inLabel: false, form: null, firstSummary: false });

        return doc;
    }

    // Text of an element as a screen reader reads its content: text, an
    // image's alt, a named child; what it does not read is left out.
    function contentText(doc, item) {
        var parts = [];

        function walk(el, unread) {
            var kids = childrenOf(el);

            for (var i = 0; i < kids.length; i++) {
                var kid = kids[i];

                if (isText(kid)) {
                    parts.push(textOf(kid));
                    continue;
                }

                var tag = tagOf(kid);
                var hidden = unread || !!kid.hidden || hasAttr(kid, 'hidden') || has(SKIPPED, tag)
                    || (attrLower(kid, 'aria-hidden') === 'true') || styleSays(kid, 'display', 'none');

                if (hidden) {
                    continue;
                }

                if (squash(attr(kid, 'aria-label')) !== '') {
                    parts.push(' ' + attr(kid, 'aria-label') + ' ');
                } else if (tag === 'img' || tag === 'area' || (tag === 'input' && attrLower(kid, 'type') === 'image')) {
                    parts.push(' ' + str(attr(kid, 'alt')) + ' ');
                } else if (tag === 'svg') {
                    var title = childrenOf(kid).filter(function (c) { return !isText(c) && tagOf(c) === 'title'; })[0];
                    parts.push(' ' + (title ? plainText(title) : '') + ' ');
                } else {
                    parts.push(' ');
                    walk(kid, false);
                    parts.push(' ');
                }
            }
        }

        walk(item.el, false);

        return squash(parts.join(''));
    }

    // All text inside an element, read or not (what aria-labelledby takes).
    function plainText(el) {
        var parts = [];

        (function walk(node) {
            var kids = childrenOf(node);

            for (var i = 0; i < kids.length; i++) {
                if (isText(kids[i])) {
                    parts.push(textOf(kids[i]));
                } else if (!has(SKIPPED, tagOf(kids[i]))) {
                    parts.push(' ');
                    walk(kids[i]);
                    parts.push(' ');
                }
            }
        })(el);

        return squash(parts.join(''));
    }

    function labelledBy(doc, item) {
        var parts = idrefs(attr(item.el, 'aria-labelledby')).map(function (id) {
            return has(doc.ids, id) ? plainText(doc.ids[id].el) : '';
        });

        return squash(parts.join(' '));
    }

    function labelText(doc, item) {
        var parts = [];
        var id = attr(item.el, 'id');

        if (id !== null && id !== '' && has(doc.labelsFor, id)) {
            doc.labelsFor[id].forEach(function (label) {
                parts.push(contentText(doc, label));
            });
        }

        for (var up = item.parent; up; up = up.parent) {
            if (up.tag === 'label') {
                parts.push(contentText(doc, up));
                break;
            }
        }

        return squash(parts.join(' '));
    }

    function isField(item) {
        return (item.tag === 'input' && !has(UNLABELLED_INPUTS, attrLower(item.el, 'type'))) || item.tag === 'select' || item.tag === 'textarea';
    }

    // The accessible name, close to the browser's computation for what a
    // page carries: aria-labelledby, aria-label, a field's label, a button
    // input's value, an image's alt, content, then title.
    function nameOf(doc, item) {
        var name = labelledBy(doc, item);
        var tag = item.tag;
        var type = attrLower(item.el, 'type');

        if (name === '') {
            name = squash(attr(item.el, 'aria-label'));
        }

        if (name === '' && isField(item)) {
            name = labelText(doc, item);
        }

        if (name === '' && tag === 'input' && (type === 'button' || type === 'submit' || type === 'reset')) {
            name = squash(attr(item.el, 'value'));
        }

        if (name === '' && (tag === 'img' || tag === 'area' || (tag === 'input' && type === 'image'))) {
            name = squash(attr(item.el, 'alt'));
        } else if (name === '' && tag !== 'select' && tag !== 'textarea' && tag !== 'input' && tag !== 'iframe') {
            name = contentText(doc, item);
        }

        if (name === '') {
            name = squash(attr(item.el, 'title'));
        }

        return name;
    }

    function ownRole(item) {
        return attrLower(item.el, 'role').split(/\s+/)[0];
    }

    // What a reader hands back for an element.
    function entry(item, extra) {
        var out = { node: item.node, tag: item.tag, pos: item.pos };

        if (has(item.el, 'ref')) {
            out.ref = item.el.ref;
        }

        for (var key in extra) {
            if (has(extra, key)) {
                out[key] = extra[key];
            }
        }

        return out;
    }

    // ---- Tab order --------------------------------------------------------

    // Whether Tab stops on the element by itself (without a tabindex).
    function nativelyFocusable(item) {
        var el = item.el;

        switch (item.tag) {
            case 'a':
            case 'area':
                return hasAttr(el, 'href');
            case 'button':
            case 'select':
            case 'textarea':
            case 'iframe':
                return true;
            case 'input':
                return attrLower(el, 'type') !== 'hidden';
            case 'summary':
                return !!item.parent && item.parent.tag === 'details' && firstSummaryOf(item.parent) === item.el;
            case 'audio':
            case 'video':
                return hasAttr(el, 'controls');
        }

        var editable = attr(el, 'contenteditable');

        return editable !== null && attrLower(el, 'contenteditable') !== 'false';
    }

    function firstSummaryOf(details) {
        var kids = childrenOf(details.el);

        for (var i = 0; i < kids.length; i++) {
            if (!isText(kids[i]) && tagOf(kids[i]) === 'summary') {
                return kids[i];
            }
        }

        return null;
    }

    function tabOrderOf(doc) {
        var positive = [];
        var normal = [];
        var radios = {};

        doc.all.forEach(function (item) {
            if (item.unrendered || item.disabled) {
                return;
            }

            var tabindex = tabindexOf(item.el);

            if (tabindex === null ? !nativelyFocusable(item) : tabindex < 0) {
                return;
            }

            var stop = { item: item, tabindex: tabindex === null ? 0 : tabindex };

            // One stop per radio group: the checked one, else the first.
            if (item.tag === 'input' && attrLower(item.el, 'type') === 'radio' && str(attr(item.el, 'name')) !== '') {
                var key = (item.form ? item.form.pos : -1) + '|' + attr(item.el, 'name');
                var group = radios[key];

                if (group) {
                    if (hasAttr(item.el, 'checked') && !group.checked) {
                        group.stop.item = item;
                        group.checked = true;
                    }
                    return;
                }

                radios[key] = { checked: hasAttr(item.el, 'checked'), stop: stop };
            }

            (stop.tabindex > 0 ? positive : normal).push(stop);
        });

        // Sort is stable from ES2019; the position keeps equal tabindexes
        // in document order on older engines too.
        positive.sort(function (a, b) {
            return (a.tabindex - b.tabindex) || (a.item.pos - b.item.pos);
        });

        var lastPos = -1;

        return positive.concat(normal).map(function (stop, i) {
            var item = stop.item;
            var out = entry(item, {
                index: i + 1,
                tabindex: stop.tabindex,
                role: roleOf(item),
                name: nameOf(doc, item),
                // Before the previous stop in the document: a positive
                // tabindex sent the keyboard back.
                back: item.pos < lastPos
            });

            lastPos = item.pos;

            return out;
        });
    }

    // What a stop is to a screen reader, for its mark.
    function roleOf(item) {
        var role = ownRole(item);

        if (role !== '') {
            return role;
        }

        switch (item.tag) {
            case 'a':
            case 'area':
                return hasAttr(item.el, 'href') ? 'link' : '';
            case 'button':
            case 'summary':
                return 'button';
            case 'select':
                return (hasAttr(item.el, 'multiple') || parseInt(attr(item.el, 'size'), 10) > 1) ? 'listbox' : 'combobox';
            case 'textarea':
                return 'textbox';
            case 'iframe':
                return 'frame';
            case 'input':
                var type = attrLower(item.el, 'type');
                return ({ button: 'button', submit: 'button', reset: 'button', image: 'button', checkbox: 'checkbox', radio: 'radio', range: 'slider', number: 'spinbutton', search: 'searchbox' })[type] || 'textbox';
        }

        return '';
    }

    // Which steps go back on the page: the next stop starts above the
    // previous one's middle, or on the same line before it (after it, on a
    // right-to-left page). `boxes` holds one { top, left, width, height } per
    // stop, null where the stop is not on the page.
    function jumps(boxes, rtl) {
        var out = [];
        var last = null;

        for (var i = 0; i < boxes.length; i++) {
            var box = boxes[i];

            if (!box) {
                out.push(false);
                continue;
            }

            var back = false;

            if (last) {
                var above = box.top + box.height <= last.top + last.height / 2;
                var sameLine = !above && box.top < last.top + last.height / 2;
                var before = rtl ? (box.left > last.left + last.width / 2) : (box.left + box.width < last.left + last.width / 2);
                back = above || (sameLine && before);
            }

            out.push(back);
            last = box;
        }

        return out;
    }

    // ---- Headings ---------------------------------------------------------

    function headingsOf(doc) {
        var out = [];
        var previous = 0;

        doc.all.forEach(function (item) {
            if (item.unread) {
                return;
            }

            var role = ownRole(item);
            var level = 0;
            var assumed = false;

            if (role === 'heading') {
                level = parseInt(attr(item.el, 'aria-level'), 10) || 0;

                // role="heading" with no level is read as a level 2; the
                // audit does not judge it, so it starts no skip.
                if (!hasAttr(item.el, 'aria-level')) {
                    level = 2;
                    assumed = true;
                }
            } else if (role === '' && /^h[1-6]$/.test(item.tag)) {
                level = parseInt(item.tag.charAt(1), 10);
            }

            if (level < 1 || level > 6) {
                return;
            }

            var skipped = !assumed && previous > 0 && level > previous + 1;
            var found = entry(item, { level: level, text: nameOf(doc, item), skipped: skipped, assumed: assumed });

            if (skipped) {
                found.from = previous;
                found.fault = 'a11y_heading_order';
            }

            if (!assumed) {
                previous = level;
            }

            out.push(found);
        });

        return out;
    }

    // ---- Regions ----------------------------------------------------------

    function regionsOf(doc) {
        var out = [];
        var open = [];

        doc.all.forEach(function (item) {
            if (item.unread) {
                return;
            }

            var role = ownRole(item);
            var name = '';

            if (role === '') {
                role = has(IMPLICIT, item.tag) ? IMPLICIT[item.tag] : '';

                if ((role === 'banner' || role === 'contentinfo') && sectionedAbove(item)) {
                    role = '';
                }
            }

            if (!has(LANDMARKS, role)) {
                return;
            }

            name = squash(labelledBy(doc, item)) || squash(attr(item.el, 'aria-label')) || squash(attr(item.el, 'title'));

            // A section or a form is a landmark only once it is named.
            if ((role === 'region' || role === 'form') && name === '') {
                return;
            }

            while (open.length && !inside(item, open[open.length - 1].item)) {
                open.pop();
            }

            var found = entry(item, { role: role, name: name, depth: open.length });

            out.push(found);
            open.push({ item: item });
        });

        return out;
    }

    function sectionedAbove(item) {
        for (var up = item.parent; up; up = up.parent) {
            if (has(SECTIONING, up.tag) || has({ article: true, complementary: true, main: true, navigation: true, region: true }, ownRole(up))) {
                return true;
            }
        }

        return false;
    }

    function inside(item, ancestor) {
        for (var up = item.parent; up; up = up.parent) {
            if (up === ancestor) {
                return true;
            }
        }

        return false;
    }

    // ---- Pictures ---------------------------------------------------------

    // kind: 'text' (says what it shows), 'decor' (alt="", presentation),
    // 'none' (says nothing). The fault follows pg_a11y_rule_img_alt().
    function picturesOf(doc) {
        var out = [];

        doc.all.forEach(function (item) {
            var el = item.el;
            var tag = item.tag;
            var role = ownRole(item);
            var image = tag === 'img' || (tag === 'input' && attrLower(el, 'type') === 'image') || (tag === 'area' && hasAttr(el, 'href'));

            if (!image && role !== 'img') {
                return;
            }

            if (item.unread) {
                return;
            }

            var label = labelledBy(doc, item) || squash(attr(el, 'aria-label'));
            var alt = attr(el, 'alt');
            var text = label || squash(alt);
            var kind = 'text';

            if (text === '' && role === 'img' && !image) {
                text = contentText(doc, item) || squash(attr(el, 'title'));
            }

            if (text === '') {
                kind = (role === 'presentation' || role === 'none' || (tag === 'img' && alt !== null)) ? 'decor' : 'none';
            }

            var found = entry(item, { kind: kind, text: text });

            if (image && kind === 'none' && !hasAttr(el, 'aria-labelledby')) {
                found.fault = 'a11y_img_alt';
            }

            out.push(found);
        });

        return out;
    }

    // ---- Controls ---------------------------------------------------------

    // Links, buttons, fields and frames, with the name a screen reader says;
    // a missing one carries the audit's fault.
    function controlsOf(doc) {
        var out = [];

        doc.all.forEach(function (item) {
            var el = item.el;
            var tag = item.tag;
            var type = attrLower(el, 'type');
            var kind = '';
            var fault = '';

            if (tag === 'a' && hasAttr(el, 'href')) {
                kind = 'link';
                fault = 'a11y_link_name';
            } else if (tag === 'button' || ownRole(item) === 'button' || (tag === 'input' && type === 'button')) {
                kind = 'button';
                fault = 'a11y_button_name';
            } else if (tag === 'input' && (type === 'submit' || type === 'reset')) {
                // Named by its value or the browser's own word: never a fault.
                kind = 'button';
            } else if (isField(item)) {
                kind = 'field';
                fault = 'a11y_form_label';
            } else if (tag === 'iframe' || tag === 'frame') {
                kind = 'frame';
                fault = 'a11y_frame_title';
            } else {
                return;
            }

            if (item.unread) {
                return;
            }

            var name = nameOf(doc, item);
            var named = name !== '';

            // The audit's own tests where they differ from the name: a field
            // inside a label or tied to one is labelled, even by an empty
            // one; a frame is named only by a label or a title.
            if (kind === 'field' && !named) {
                named = item.inLabel || (str(attr(el, 'id')) !== '' && has(doc.labelsFor, attr(el, 'id')));
            } else if (kind === 'frame') {
                name = labelledBy(doc, item) || squash(attr(el, 'aria-label')) || squash(attr(el, 'title'));
                named = name !== '' || hasAttr(el, 'aria-labelledby');
            }

            var found = entry(item, { kind: kind, role: roleOf(item) || kind, name: name });

            if (!named && fault !== '') {
                found.fault = fault;
            }

            out.push(found);
        });

        return out;
    }

    // ---- Building the tree from the canvas --------------------------------

    // The tree of a DOM element. `options.hidden(el)` says whether the
    // browser hides it (a computed style), `options.skip(el)` leaves out the
    // editor's own chrome; the designer node is read from data-sd-id.
    function fromDom(el, options) {
        options = options || {};

        function build(node) {
            if (node.nodeType === 3) {
                return { text: node.nodeValue };
            }

            if (node.nodeType !== 1 || (options.skip && options.skip(node))) {
                return null;
            }

            var attrs = {};

            for (var i = 0; i < node.attributes.length; i++) {
                attrs[node.attributes[i].name.toLowerCase()] = node.attributes[i].value;
            }

            var out = { tag: str(node.localName || node.tagName).toLowerCase(), attrs: attrs, children: [], node: str(node.getAttribute('data-sd-id')), ref: node };

            if (options.hidden && options.hidden(node)) {
                out.hidden = true;
            }

            var kids = node.childNodes || [];

            for (var k = 0; k < kids.length; k++) {
                var kid = build(kids[k]);

                if (kid) {
                    out.children.push(kid);
                }
            }

            return out;
        }

        return build(el);
    }

    // ---- Entry points -----------------------------------------------------

    function read(root) {
        var doc = index(root);

        return {
            order: tabOrderOf(doc),
            headings: headingsOf(doc),
            regions: regionsOf(doc),
            pictures: picturesOf(doc),
            controls: controlsOf(doc)
        };
    }

    window.PGA11yReader = {
        read: read,
        tabOrder: function (root) { return tabOrderOf(index(root)); },
        headings: function (root) { return headingsOf(index(root)); },
        regions: function (root) { return regionsOf(index(root)); },
        pictures: function (root) { return picturesOf(index(root)); },
        controls: function (root) { return controlsOf(index(root)); },
        jumps: jumps,
        fromDom: fromDom
    };
})();
