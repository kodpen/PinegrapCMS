/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's work cards (assets/js/pg_dash.js draws the frames):
 * Next up. The data is what includes/dashboard/v2/cards/<id>.php returns;
 * every text in it was translated on the server.
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

    // Next up: one row per kind of waiting work. The tag names the module
    // in words and the note carries the tone in its text as well, so the
    // colour never says anything on its own.
    pgDash.renderers.queue = function (body, data) {
        var el = pgDash.el;

        if (data.sub) {
            body.appendChild(el("p", "pg-dash-queue-sub", data.sub));
        }

        var list = el("ul", "pg-dash-queue");

        (data.rows || []).forEach(function (row) {
            var item = el("li", "pg-dash-queue-row");
            item.setAttribute("data-tone", row.tone || "plain");

            var tag = el("span", "pg-dash-queue-tag", row.tag);
            tag.setAttribute("data-module", row.module || "");
            item.appendChild(tag);

            var text = el("span", "pg-dash-queue-text");
            text.appendChild(el("span", "pg-dash-queue-title", row.text));
            if (row.note) {
                text.appendChild(el("span", "pg-dash-queue-note", row.note));
            }
            item.appendChild(text);

            if (row.url && row.action) {
                var action = el("a", "btn btn-outline theme-secondary btn-sm pg-dash-queue-action", row.action);
                action.href = row.url;
                action.setAttribute("aria-label", row.action + ": " + row.text);
                item.appendChild(action);
            }

            list.appendChild(item);
        });

        body.appendChild(list);
    };
})();
