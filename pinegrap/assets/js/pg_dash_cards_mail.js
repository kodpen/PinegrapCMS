/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's Incoming e-mail card (assets/js/pg_dash.js draws the
 * frame). The data is what includes/dashboard/v2/cards/mail.php returns:
 * the unread count, the link to the tab and the newest conversations of
 * its Incoming view. The sender and the subject come from the mail, so
 * every text is written with textContent, never as markup; the others were
 * translated on the server.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    "use strict";

    var pgDash = window.pgDash = window.pgDash || {};
    pgDash.renderers = pgDash.renderers || {};

    // A conversation: the sender (bold while unread, the word said to a
    // screen reader) and the time on one line, the subject under it, or the
    // kind of a WhatsApp or SMS conversation, which has none. The row is one
    // link that opens the conversation on the screen.
    function row(entry, labels) {
        var el = pgDash.el;
        var item = el("li", "pg-dash-item pg-dash-mail-row");
        var open = el("a", "pg-dash-item-link");
        var main = el("span", "pg-dash-item-main");
        var top = el("span", "pg-dash-mail-top");
        var about = entry.subject || entry.kind;

        open.href = entry.url;
        if (entry.unread) {
            item.setAttribute("data-unread", "1");
        }

        top.appendChild(el("span", "pg-dash-mail-who", entry.who));
        if (entry.unread) {
            top.appendChild(el("span", "visually-hidden", labels.unread));
        }
        top.appendChild(el("span", "pg-dash-mail-time", entry.time));
        main.appendChild(top);
        if (about) {
            main.appendChild(el("span", "pg-dash-item-note pg-dash-mail-subject", about));
        }

        open.appendChild(main);
        item.appendChild(open);
        return item;
    }

    pgDash.renderers.mail = function (body, data) {
        var el = pgDash.el;
        var labels = data.labels || {};
        var rows = data.rows || [];
        var head = el("div", "pg-dash-mail-head");
        var count = el("p", "pg-dash-mail-count", data.count);

        // The head: the unread count, lit while there is one, and the tab.
        count.setAttribute("data-unread", (data.unread > 0) ? "1" : "0");
        head.appendChild(count);
        if (data.url && data.link) {
            var link = el("a", "pg-dash-card-more", data.link);
            link.href = data.url;
            head.appendChild(link);
        }
        body.appendChild(head);

        if (!rows.length) {
            body.appendChild(el("p", "pg-dash-card-empty", labels.none));
            return;
        }

        var list = el("ul", "pg-dash-items pg-dash-mail-list");
        rows.forEach(function (entry) {
            list.appendChild(row(entry, labels));
        });
        body.appendChild(list);
    };
})();
