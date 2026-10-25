/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the customer card on a channel's Summary tab: who the channel's
 * customer is, the records tied to them, their latest orders, the balance of
 * their current accounts, their open sales quotes and the forms they sent.
 *
 * assets/js/workspace.js asks this file for the card and lends it its own
 * helpers (api, t, el, ...), so nothing here talks to the server or shows a
 * text any other way:
 *   PGWsCustomer.card(channel, helpers)   the card, filled once it is read
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
        var head = h.el('div', 'small fw-semibold mt-3 mb-1 d-flex align-items-center gap-1');

        head.appendChild(h.icon(iconName, 'text-body-secondary'));
        head.appendChild(h.el('span', '', text));

        if (count) {
            head.appendChild(h.el('span', 'badge rounded-pill text-bg-light border', count));
        }

        return head;
    }

    // One line of a section: the record, what is said about it, an amount.
    function line(h, label, url, notes, amount) {
        var row = h.el('div', 'd-flex align-items-baseline gap-2 small py-1 border-bottom');

        row.appendChild(named(h, 'text-truncate', label, url));
        row.appendChild(h.el('span', 'text-body-secondary text-truncate flex-grow-1', notes.filter(Boolean).join(' · ')));

        if (amount) {
            row.appendChild(h.el('span', 'fw-semibold text-nowrap', amount));
        }

        return row;
    }

    function draw(h, body, data) {
        var sections = data.sections || {};
        var shown = false;

        h.clear(body);

        var who = h.el('div', 'd-flex align-items-center gap-2');
        var name = h.el('div', 'ws-grow');

        who.appendChild(h.icon(kindIcon(h, data.customer.type, 'bi-person-vcard'), 'fs-4 text-body-secondary'));
        name.appendChild(named(h, 'fw-semibold', data.customer.label, data.customer.url));
        name.appendChild(h.el('div', 'small text-body-secondary', [data.customer.kind, data.customer.detail].filter(Boolean).join(' · ')));
        who.appendChild(name);
        body.appendChild(who);

        if (data.links.length) {
            var ties = h.el('div', 'd-flex flex-wrap gap-3 small mt-2');

            data.links.forEach(function (link) {
                var tie = h.el('span', 'text-body-secondary');

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

        if (sections.forms) {
            body.appendChild(sectionTitle(h, h.t('cust_forms'), 'bi-ui-checks', 0));
            sections.forms.forEach(function (form) {
                body.appendChild(line(h, [form.name, form.reference_code].filter(Boolean).join(' '), form.url, [form.status_label, form.date_text], ''));
            });
            shown = true;
        }

        if (!shown) {
            body.appendChild(h.el('div', 'small text-body-secondary mt-3', h.t('cust_empty')));
        }
    }

    function card(channel, h) {
        var node = h.el('div', 'card ws-customer-card mt-4');
        var header = h.el('div', 'card-header d-flex justify-content-between align-items-center');
        var body = h.el('div', 'card-body');

        header.appendChild(h.el('span', 'text-uppercase h5 text-primary fw-bold mb-0', h.t('customer')));
        node.appendChild(header);
        node.appendChild(body);

        // No card for a customer gone since, or one the reader cannot read.
        h.api('ws_channel_customer', { channel_id: channel.id }).then(function (data) {
            if (data.card) {
                draw(h, body, data.card);
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
