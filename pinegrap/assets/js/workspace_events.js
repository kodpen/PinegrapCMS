/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - what happens on the site, in the screens: the "when something
 * happens" step of a scheduled action, the ready-made actions that use it,
 * and a channel's switch for hearing about the records tied to it.
 *
 * assets/js/workspace.js draws the scheduled action's form and the channel's
 * settings and asks this file for the pieces:
 *
 * - PGWsEvents.whenBox(rule) returns the node of the event rule (what
 *   happens, and the form or the order status it is narrowed to) and a
 *   read() giving the rule the server takes;
 * - PGWsEvents.templates(context) returns ready-made actions in the shape
 *   saTemplates() lists them (context: channelId, me, tell);
 * - PGWsEvents.watchField(channel) returns the switch and its value(), or
 *   null where the channel cannot watch.
 *
 * The server checks everything again (includes/workspace/scheduled.php,
 * includes/workspace/watch.php); the lists and every text come from the
 * #ws-config block.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var configNode = document.getElementById('ws-config');
    var CFG = {};

    try {
        CFG = JSON.parse((configNode && configNode.textContent) || '{}');
    } catch (error) {
        CFG = {};
    }

    var EVENTS = (CFG.scheduled && CFG.scheduled.events) || null;
    var S = CFG.strings || {};
    var uid = 0;

    function text(key) {
        return (S[key] !== undefined) ? String(S[key]) : key;
    }

    function el(tag, className, content) {
        var node = document.createElement(tag);

        if (className) {
            node.className = className;
        }

        if ((content !== undefined) && (content !== null) && (content !== '')) {
            node.textContent = String(content);
        }

        return node;
    }

    function select(options, value) {
        var node = el('select', 'form-select form-select-sm');

        options.forEach(function (option) {
            var item = el('option', '', option[1]);

            item.value = option[0];

            if (String(option[0]) === String(value)) {
                item.selected = true;
            }

            node.appendChild(item);
        });

        return node;
    }

    function labelled(label, node, help) {
        var wrap = el('div', 'ws-sa-field');

        wrap.appendChild(el('label', 'form-label small mb-1', label));
        wrap.appendChild(node);

        if (help) {
            wrap.appendChild(el('div', 'form-text mt-0', help));
        }

        return wrap;
    }

    // The event rule: what happens, and its filter when it has one.
    function whenBox(rule) {
        var list = (EVENTS && EVENTS.list) || {};
        var keys = Object.keys(list);
        var box = el('div', 'ws-sa-when-event');
        var given = rule || {};
        var which = select(keys.map(function (key) { return [key, list[key].label]; }), given.event || keys[0]);
        var filterBox = el('div', 'ws-sa-when-event-filter');
        var filter = null;

        function draw() {
            var info = list[which.value] || {};

            while (filterBox.firstChild) {
                filterBox.removeChild(filterBox.firstChild);
            }

            filter = null;

            if (info.filter === 'page') {
                filter = select([[0, text('sa_event_any_form')]].concat(EVENTS.forms || []), (which.value === given.event) ? (given.page_id || 0) : 0);
                filterBox.appendChild(labelled(text('sa_event_form'), filter));
            } else if (info.filter === 'status') {
                filter = select([['', text('sa_event_any_status')]].concat(EVENTS.statuses || []), (which.value === given.event) ? (given.status || '') : '');
                filterBox.appendChild(labelled(text('sa_event_status'), filter));
            }

            filterBox.hidden = !filter;
        }

        which.addEventListener('change', draw);

        box.appendChild(labelled(text('sa_event'), which, text('sa_event_help')));
        box.appendChild(filterBox);
        draw();

        return {
            node: box,
            read: function () {
                var info = list[which.value] || {};
                var out = { type: 'event', event: which.value };

                if (filter && (info.filter === 'page')) {
                    out.page_id = parseInt(filter.value, 10) || 0;
                }

                if (filter && (info.filter === 'status')) {
                    out.status = filter.value;
                }

                return out;
            }
        };
    }

    // Ready-made actions started by an event. tell(text) is where a text
    // goes: the channel the form was opened in, or the person's inbox.
    function templates(context) {
        var list = (EVENTS && EVENTS.list) || {};
        var out = [];
        var channelId = context.channelId || 0;

        // The tag alone: each member sees of the order what their rights
        // show; {{customer}} and {{amount}} would be read by all of them.
        if (list['order.created']) {
            out.push({ key: 'order_event', label: text('sa_tpl_order_event'), icon: 'bi-bag-plus', help: text('sa_tpl_order_event_help'), data: {
                name: text('sa_tpl_order_event'),
                rules: [{ type: 'event', event: 'order.created' }],
                action: context.tell(text('sa_tpl_order_event_text')),
                follow: []
            } });
        }

        // The fields are drawn by the form's card under the message, for the
        // members who may open the submission; the text only tags it.
        if (list['form.submitted']) {
            out.push({ key: 'form_event', label: text('sa_tpl_form_event'), icon: 'bi-ui-checks', help: text('sa_tpl_form_event_help'), data: {
                name: text('sa_tpl_form_event'),
                rules: [{ type: 'event', event: 'form.submitted', page_id: 0 }],
                action: context.tell('{{record}}'),
                follow: []
            } });
        }

        if (list['stock.low']) {
            out.push({ key: 'stock_event', label: text('sa_tpl_stock_event'), icon: 'bi-box-seam', help: text('sa_tpl_stock_event_help'), data: {
                name: text('sa_tpl_stock_event'),
                rules: [{ type: 'event', event: 'stock.low' }],
                action: { type: 'task', title: text('sa_tpl_stock_event_task'), description: '{{record}}\n{{record_link}}', priority: 'high', assignees: context.me ? [context.me] : [], channel_id: channelId, due_in: 2 },
                follow: []
            } });
        }

        return out;
    }

    // The channel's switch: hear about its customer's orders and the records
    // tagged in it. Null where the server says the channel cannot.
    function watchField(channel) {
        if (!channel || (channel.watch === null) || (channel.watch === undefined)) {
            return null;
        }

        var wrap = el('div', 'form-check form-switch mb-3 ws-watch-field');
        var input = el('input', 'form-check-input');
        var label = el('label', 'form-check-label', text('watch_label'));

        uid += 1;
        input.type = 'checkbox';
        input.id = 'ws-watch-' + uid;
        input.checked = !!channel.watch;
        label.htmlFor = input.id;

        wrap.appendChild(input);
        wrap.appendChild(label);
        wrap.appendChild(el('div', 'form-text mt-0', text('watch_help')));

        return {
            node: wrap,
            value: function () {
                return input.checked ? 1 : 0;
            }
        };
    }

    window.PGWsEvents = {
        ready: !!(EVENTS && EVENTS.list && Object.keys(EVENTS.list).length),
        icon: 'bi-broadcast',
        whenBox: whenBox,
        templates: templates,
        watchField: watchField
    };
})();
