/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the customer card on a channel's Summary tab: who the channel's
 * customer is, the records tied to them, their latest orders, the balance of
 * their current accounts, their open sales quotes and the forms they sent,
 * and "Remind me" for a follow-up on a day.
 *
 * assets/js/workspace.js asks this file for the card and lends it its own
 * helpers (api, t, el, ...), so nothing here talks to the server or shows a
 * text any other way:
 *   PGWsCustomer.card(channel, helpers)   the card, filled once it is read,
 *                                         with its History read on demand
 *
 * The server reads the card for the person looking (ws_customer_card(),
 * includes/workspace/customer.php): a section they hold no right to is not
 * sent, and a record they may not open comes without an address.
 *
 * Served minified inside assets/js/workspace.min.js (pg_js_bundles(),
 * includes/fn/output.php); after editing run `php tools/build_js.php`.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // A record's name: a link when it comes with an address.
    function named(h, className, label, url) {
        var node = h.el(url ? 'a' : 'span', className, label);

        if (url) {
            node.href = url;
        }

        return node;
    }

    // The icon the tags give a kind of record.
    function kindIcon(h, type, fallback) {
        var types = h.boot().ref_types || {};

        return (types[type] || {}).icon || fallback;
    }

    function sectionTitle(h, text, iconName, count) {
        var head = h.el('div', 'small fw-semibold mt-5 mb-1 d-flex align-items-center gap-1');

        head.appendChild(h.icon(iconName, 'fg-secondary'));
        head.appendChild(h.el('span', '', text));

        if (count) {
            head.appendChild(h.el('span', 'badge rounded-pill badge-subtle theme-secondary border', count));
        }

        return head;
    }

    // One line of a section: the record, what is said about it, an amount.
    function line(h, label, url, notes, amount) {
        var row = h.el('div', 'd-flex align-items-baseline gap-3 small py-1 border-bottom');

        row.appendChild(named(h, 'text-truncate', label, url));
        row.appendChild(h.el('span', 'fg-secondary text-truncate flex-grow-1', notes.filter(Boolean).join(' · ')));

        if (amount) {
            row.appendChild(h.el('span', 'fw-semibold text-nowrap', amount));
        }

        return row;
    }

    function draw(h, body, data, channelId) {
        var sections = data.sections || {};
        var shown = false;

        h.clear(body);

        var who = h.el('div', 'd-flex align-items-center gap-3');
        var name = h.el('div', 'ws-grow');

        who.appendChild(h.icon(kindIcon(h, data.customer.type, 'bi-person-vcard'), 'fs-xl fg-secondary'));
        name.appendChild(named(h, 'fw-semibold', data.customer.label, data.customer.url));
        name.appendChild(h.el('div', 'small fg-secondary', [data.customer.kind, data.customer.detail].filter(Boolean).join(' · ')));
        who.appendChild(name);
        body.appendChild(who);

        if (data.links.length) {
            var ties = h.el('div', 'd-flex flex-wrap gap-5 small mt-3');

            data.links.forEach(function (link) {
                var tie = h.el('span', 'fg-secondary');

                tie.title = link.kind;
                tie.appendChild(h.icon(kindIcon(h, link.type, 'bi-link-45deg'), 'me-1'));
                tie.appendChild(named(h, '', link.label, link.url));
                ties.appendChild(tie);
            });

            body.appendChild(ties);
        }

        if (sections.orders) {
            body.appendChild(sectionTitle(h, h.t('cust_orders'), 'bi-bag-check', sections.orders.count));
            sections.orders.latest.forEach(function (order) {
                body.appendChild(line(h, '#' + order.number, order.url, [order.status_label, order.date_text], order.total_text));
            });
            shown = true;
        }

        if (sections.erp_accounts) {
            body.appendChild(sectionTitle(h, h.t('cust_balance'), 'bi-building', 0));
            sections.erp_accounts.forEach(function (account) {
                body.appendChild(line(h, account.title, account.url, [account.status_label], account.balance_text));
            });
            shown = true;
        }

        if (sections.sales_quotes) {
            body.appendChild(sectionTitle(h, h.t('cust_quotes'), 'bi-file-earmark-text', sections.sales_quotes.count));
            sections.sales_quotes.latest.forEach(function (quote) {
                body.appendChild(line(h, quote.number, quote.url, [quote.account_title, quote.valid_until_text], quote.total_text));
            });
            shown = true;
        }

        if (sections.cart_quotes) {
            body.appendChild(sectionTitle(h, h.t('cust_cart_quotes'), 'bi-cart3', sections.cart_quotes.count));
            sections.cart_quotes.latest.forEach(function (quote) {
                body.appendChild(line(h, quote.number, quote.url, [quote.lines_text, quote.valid_until_text], ''));
            });
            shown = true;
        }

        if (sections.forms) {
            body.appendChild(sectionTitle(h, h.t('cust_forms'), 'bi-ui-checks', 0));
            sections.forms.forEach(function (form) {
                body.appendChild(line(h, [form.name, form.reference_code].filter(Boolean).join(' '), form.url, [form.status_label, form.date_text], ''));
            });
            shown = true;
        }

        if (!shown) {
            body.appendChild(h.el('div', 'small fg-secondary mt-5', h.t('cust_empty')));
        }

        body.appendChild(historyPart(h, channelId));
    }

    // The kinds of entry in the history and their icons.
    var HISTORY_ICONS = {
        order: 'bi-bag-check',
        invoice: 'bi-receipt',
        sales_quote: 'bi-file-earmark-text',
        form: 'bi-ui-checks',
        message: 'bi-chat-left-text',
        task: 'bi-check2-square'
    };

    // What happened with the customer, newest first, read only when asked
    // for and page by page (ws_customer_history(), customer_history.php).
    function historyPart(h, channelId) {
        var part = h.el('div', 'mt-5');
        var open = h.button('btn btn-sm btn-outline theme-secondary', h.t('cust_history'), 'bi-clock-history');
        var list = h.el('div', '');

        function page(before) {
            var more = h.button('btn btn-sm btn-link px-0', h.t('cust_older'));

            return h.api('ws_channel_customer_history', { channel_id: channelId, before: before }).then(function (data) {
                var history = data.history;

                if (!history.entries.length && !before) {
                    list.appendChild(h.el('div', 'small fg-secondary', h.t('cust_history_empty')));
                }

                history.entries.forEach(function (entry) {
                    var row = h.el('div', 'd-flex align-items-baseline gap-3 small py-1 border-bottom');

                    row.appendChild(h.icon(HISTORY_ICONS[entry.kind] || 'bi-dot', 'fg-secondary'));
                    row.appendChild(named(h, 'text-truncate', entry.title, entry.url));
                    row.appendChild(h.el('span', 'fg-secondary text-truncate flex-grow-1', [entry.status_label, entry.detail].filter(Boolean).join(' · ')));

                    if (entry.amount_text) {
                        row.appendChild(h.el('span', 'fw-semibold text-nowrap', entry.amount_text));
                    }

                    row.appendChild(h.el('span', 'fg-secondary text-nowrap', entry.at_text));
                    list.appendChild(row);
                });

                if (history.more) {
                    more.addEventListener('click', function () {
                        more.disabled = true;
                        page(history.before).then(function () {
                            more.remove();
                        }, function (error) {
                            more.disabled = false;
                            h.fail(error);
                        });
                    });
                    list.appendChild(more);
                }
            });
        }

        open.addEventListener('click', function () {
            open.disabled = true;
            page(0).then(function () {
                open.remove();
                part.appendChild(sectionTitle(h, h.t('cust_history'), 'bi-clock-history', 0));
                part.appendChild(list);
            }, function (error) {
                open.disabled = false;
                h.fail(error);
            });
        });

        part.appendChild(open);

        return part;
    }

    // A day a week from now, as a date field takes it.
    function weekAhead() {
        var day = new Date();

        day.setDate(day.getDate() + 7);

        return day.getFullYear() + '-' + ('0' + (day.getMonth() + 1)).slice(-2) + '-' + ('0' + day.getDate()).slice(-2);
    }

    // A field of the reminder form with its label.
    function field(h, label, input, className) {
        var wrap = h.el('div', className);
        var caption = h.el('label', 'form-label small mb-1', label);

        input.id = h.nextId('ws-cust-remind-');
        caption.htmlFor = input.id;
        wrap.appendChild(caption);
        wrap.appendChild(input);

        return wrap;
    }

    // "Remind me about this customer": a follow-up task for the reader on
    // the channel, tagged with the customer, due and reminded on the day they
    // pick (ws_customer_followup_open(), includes/workspace/followups.php).
    function remindPart(h, channel) {
        var part = h.el('div', 'card-body border-bottom d-none');
        var open = h.button('btn btn-sm btn-outline theme-secondary', h.t('cust_remind'), 'bi-alarm', h.t('cust_remind_help'));

        function close() {
            h.clear(part);
            part.classList.add('d-none');
        }

        function done(task) {
            var row = h.el('div', 'd-flex flex-wrap align-items-center gap-3 small');
            var go = h.button('btn btn-sm btn-link p-0', h.t('open_task'));

            go.addEventListener('click', function () {
                h.openTask(task.id);
            });

            row.appendChild(h.icon('bi-alarm', 'fg-success'));
            row.appendChild(h.el('span', '', h.t('cust_remind_done', task.number, task.due_label)));
            row.appendChild(go);
            h.clear(part);
            part.appendChild(row);
        }

        function form() {
            var box = h.el('form', 'd-flex flex-wrap align-items-end gap-3');
            var what = h.el('input', 'form-control form-control-sm');
            var day = h.el('input', 'form-control form-control-sm');
            var time = h.el('input', 'form-control form-control-sm');
            var save = h.el('button', 'btn btn-sm btn-solid theme-primary', h.t('cust_remind_save'));
            var cancel = h.button('btn btn-sm btn-outline theme-secondary', h.t('cancel'));
            var actions = h.el('div', 'd-flex gap-3');
            var inputs = { title: what, due_date: day, due_time: time };

            what.type = 'text';
            what.maxLength = 255;
            what.placeholder = h.t('cust_remind_default', channel.name);
            h.dateField(day, 'date');
            day.required = true;
            day.value = weekAhead();
            h.dateField(time, 'time');
            save.type = 'submit';

            box.appendChild(field(h, h.t('cust_remind_what'), what, 'flex-grow-1'));
            box.appendChild(field(h, h.t('cust_remind_day'), day, ''));
            box.appendChild(field(h, h.t('cust_remind_time'), time, ''));
            actions.appendChild(save);
            actions.appendChild(cancel);
            box.appendChild(actions);
            box.appendChild(h.el('div', 'form-text w-100 mt-0', h.t('cust_remind_note')));

            cancel.addEventListener('click', close);
            box.addEventListener('submit', function (event) {
                event.preventDefault();
                save.disabled = true;

                h.api('ws_channel_customer_followup', {
                    channel_id: channel.id,
                    title: what.value,
                    due_date: day.value,
                    due_time: time.value
                }).then(function (data) {
                    done(data.task);
                }, function (error) {
                    save.disabled = false;
                    h.fail(error);
                    (inputs[error.field] || day).focus();
                });
            });

            return box;
        }

        open.addEventListener('click', function () {
            var shown = part.querySelector('form');

            close();

            if (!shown) {
                part.appendChild(form());
                part.classList.remove('d-none');
                part.querySelector('input').focus();
            }
        });

        return { button: open, part: part };
    }

    function card(channel, h) {
        var node = h.el('div', 'card ws-customer-card mt-7');
        var header = h.el('div', 'card-header d-flex justify-content-between align-items-center');
        var body = h.el('div', 'card-body');

        header.appendChild(h.el('span', 'text-uppercase h5 fg-primary fw-bold mb-0', h.t('customer')));
        node.appendChild(header);

        // Only where the reader may write: the follow-up is a task of the channel.
        if (channel.can_post && !channel.archived) {
            var remind = remindPart(h, channel);

            header.appendChild(remind.button);
            node.appendChild(remind.part);
        }

        node.appendChild(body);

        // No card for a customer gone since, or one the reader cannot read.
        h.api('ws_channel_customer', { channel_id: channel.id }).then(function (data) {
            if (data.card) {
                draw(h, body, data.card, channel.id);
            } else {
                node.remove();
            }
        }).catch(function (error) {
            node.remove();
            h.fail(error);
        });

        return node;
    }

    window.PGWsCustomer = {
        card: card
    };
})();
