/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's Security card (assets/js/pg_dash.js draws the frame):
 * Firewall, with the Security Scan's last run and Scan now. The data is what includes/dashboard/v2/cards/firewall.php
 * returns.
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

    var SVG = "http://www.w3.org/2000/svg";

    // Bars with the ones well above the rest marked in the danger colour.
    // The picture's title lists every figure, so colour is not the only
    // place they are.
    function hotBars(values, hot, label, tips) {
        var width = 300;
        var height = 60;
        var max = Math.max.apply(null, (values || []).concat([1]));
        var step = width / Math.max((values || []).length, 1);
        var svg = document.createElementNS(SVG, "svg");
        svg.setAttribute("viewBox", "0 0 " + width + " " + height);
        svg.setAttribute("preserveAspectRatio", "none");
        svg.setAttribute("role", "img");
        svg.setAttribute("class", "pg-dash-bars pg-dash-fw-bars");
        var title = document.createElementNS(SVG, "title");
        title.textContent = label + ": " + (values || []).join(", ");
        svg.appendChild(title);
        (values || []).forEach(function (value, i) {
            var h = Math.max(1, ((value || 0) / max) * height);
            var rect = document.createElementNS(SVG, "rect");
            rect.setAttribute("x", (i * step + step * 0.15).toFixed(1));
            rect.setAttribute("y", (height - h).toFixed(1));
            rect.setAttribute("width", (step * 0.7).toFixed(1));
            rect.setAttribute("height", h.toFixed(1));
            rect.setAttribute("rx", "1.5");
            if (hot && hot[i]) {
                rect.setAttribute("class", "is-hot");
            }
            svg.appendChild(rect);
        });
        if (tips && tips.length) {
            pgDash.chartTips(svg, tips, {mark: pgDash.markBars(svg)});
        }
        return svg;
    }

    // ── Security Scan ──────────────────────────────────────────────────────

    // A Scan now started here keeps going while the card is drawn again by
    // its refresh: the new button stays disabled and shows the last line.
    var scan = {running: false, line: "", entry: null};

    function scanAsk(data, done) {
        $.ajax({
            contentType: "application/json",
            url: "api.php",
            data: JSON.stringify({
                action: "security_scan_step",
                token: (typeof software_token !== "undefined") ? software_token : ""
            }),
            type: "POST",
            dataType: "json"
        }).done(function (answer) {
            if (!answer || (answer.status !== "success")) {
                done(false, (answer && answer.message) || data.labels.failed);
                return;
            }
            if (answer.done) {
                done(true, "");
                return;
            }
            // The answer's line is the section's escaped markup: read it as
            // text through a parser that runs nothing.
            scan.line = new DOMParser().parseFromString(answer.line || "", "text/html").body.textContent;
            var line = document.querySelector(".pg-dash-scan-line");
            if (line) {
                line.textContent = scan.line;
            }
            // Busy: the scheduled task holds the scan; ask again shortly.
            window.setTimeout(function () {
                scanAsk(data, done);
            }, answer.busy ? 3000 : 0);
        }).fail(function () {
            done(false, data.labels.failed);
        });
    }

    function scanPart(data, entry) {
        var el = pgDash.el;
        var labels = data.labels;
        var box = el("section", "pg-dash-scan");

        var head = el("div", "pg-dash-scan-head");
        head.appendChild(el("h3", "pg-dash-rows-title", data.title));
        var line = el("p", "pg-dash-scan-line", scan.running ? (scan.line || labels.scanning) : data.line);
        line.setAttribute("role", "status");
        head.appendChild(line);
        box.appendChild(head);

        var pills = el("div", "pg-dash-scan-pills");
        pills.setAttribute("aria-label", labels.open);
        (data.pills || []).forEach(function (pill) {
            pills.appendChild(el("span", "pg-pill " + pill.tone, pill.label + " " + pill.count));
        });
        if (!(data.pills || []).length && data.none) {
            pills.appendChild(el("span", "pg-pill pg-tone-teal", data.none));
        }
        if (pills.children.length) {
            box.appendChild(pills);
        }

        var actions = el("div", "pg-dash-scan-actions");
        var button = el("button", "pg-button pg-button-quiet pg-button-sm", scan.running ? labels.scanning : labels.now);
        button.type = "button";
        button.disabled = scan.running;
        button.addEventListener("click", function () {
            scan.running = true;
            scan.line = labels.scanning;
            scan.entry = entry;
            button.disabled = true;
            button.textContent = labels.scanning;
            line.textContent = labels.scanning;
            scanAsk(data, function (ok, message) {
                scan.running = false;
                scan.line = "";
                if (!ok) {
                    var now = document.querySelector(".pg-dash-scan-now");
                    var said = document.querySelector(".pg-dash-scan-line");
                    if (now) {
                        now.disabled = false;
                        now.textContent = labels.now;
                    }
                    if (said) {
                        said.textContent = message;
                        said.setAttribute("data-tone", "bad");
                    }
                    return;
                }
                // Drawn again with the finished scan's line and findings.
                if (scan.entry && pgDash.reload && pgDash.frame && pgDash.frame(scan.entry.id)) {
                    pgDash.reload(scan.entry);
                }
            });
        });
        button.classList.add("pg-dash-scan-now");
        actions.appendChild(button);
        var link = el("a", "pg-button pg-button-quiet pg-button-sm", labels.findings);
        link.href = data.url;
        actions.appendChild(link);
        box.appendChild(actions);

        return box;
    }

    pgDash.renderers.firewall = function (body, data, entry) {
        var el = pgDash.el;

        var tiles = pgDash.tiles(data.tiles);
        // The tone of a tile without a note goes on its value (failed
        // sign-ins in red).
        (data.tiles || []).forEach(function (tile, i) {
            var value = tiles.children[i] && tiles.children[i].querySelector(".pg-dash-fig-value");
            if (value && tile.tone && !tile.note) {
                value.setAttribute("data-tone", tile.tone);
            }
        });
        body.appendChild(tiles);

        if (data.mode) {
            body.appendChild(el("p", "pg-dash-fw-mode", data.mode));
        }

        var chart = el("figure", "pg-dash-chart");
        chart.appendChild(hotBars(data.bars, data.hot, data.bars_label, data.tips));
        chart.appendChild(el("figcaption", "pg-dash-chart-caption", data.bars_label));
        body.appendChild(chart);

        var rows = pgDash.rows(data.rows_title, data.rows);
        rows.classList.add("pg-dash-fw-rows");
        body.appendChild(rows);

        if (data.scan) {
            body.appendChild(scanPart(data.scan, entry));
        }

        if (data.url) {
            var link = el("a", "pg-dash-card-more", data.url_text);
            link.href = data.url;
            body.appendChild(link);
        }
    };
})();
