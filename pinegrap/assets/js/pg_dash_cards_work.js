/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's work cards (assets/js/pg_dash.js draws the frames):
 * Next up, the pipeline and flows. The data is what includes/dashboard/v2/cards/<id>.php returns;
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

    // The pipeline: the title names the preset's line; each step is a box
    // with its figure, an eight-point line of the period and an optional
    // flag, and opens its list when it has one.
    function spark(points) {
        var NS = "http://www.w3.org/2000/svg";
        var svg = document.createElementNS(NS, "svg");
        var max = Math.max.apply(null, points.concat([1]));
        var last = Math.max(1, points.length - 1);

        svg.setAttribute("viewBox", "0 0 90 30");
        svg.setAttribute("preserveAspectRatio", "none");
        svg.setAttribute("class", "pg-dash-pipe-spark");
        svg.setAttribute("aria-hidden", "true");
        svg.setAttribute("focusable", "false");

        var line = document.createElementNS(NS, "polyline");
        line.setAttribute("fill", "none");
        line.setAttribute("stroke", "currentColor");
        line.setAttribute("stroke-width", "2");
        line.setAttribute("stroke-linejoin", "round");
        line.setAttribute("stroke-linecap", "round");
        line.setAttribute("vector-effect", "non-scaling-stroke");
        line.setAttribute("points", points.map(function (value, i) {
            return Math.round(i * 90 / last) + "," + (28 - Math.round((Number(value) || 0) / max * 26));
        }).join(" "));
        svg.appendChild(line);

        return svg;
    }

    pgDash.renderers.pipeline = function (body, data) {
        var el = pgDash.el;
        var title = body.parentNode && body.parentNode.querySelector(".pg-dash-card-title");

        if (title && data.title) {
            title.textContent = data.title;
        }

        if (data.sub) {
            body.appendChild(el("p", "pg-dash-pipe-sub", data.sub));
        }

        var list = el("ul", "pg-dash-pipe");

        (data.steps || []).forEach(function (step) {
            var item = el("li");
            var box = el(step.url ? "a" : "div", "pg-dash-pipe-step");

            if (step.url) {
                box.href = step.url;
            }

            box.appendChild(el("span", "pg-dash-pipe-label", step.label));

            var figure = el("span", "pg-dash-pipe-figure");
            figure.appendChild(el("span", "pg-dash-pipe-value", step.value));
            if ((step.points || []).length > 1) {
                figure.appendChild(spark(step.points));
            }
            box.appendChild(figure);

            if (step.note) {
                var note = el("span", "pg-dash-pipe-note", step.note);
                note.setAttribute("data-tone", step.tone || "");
                box.appendChild(note);
            }

            if (step.flag && step.flag.text) {
                var flag = el("span", "pg-dash-pipe-flag", step.flag.text);
                flag.setAttribute("data-tone", step.flag.tone || "warn");
                box.appendChild(flag);
            }

            item.appendChild(box);
            list.appendChild(item);
        });

        body.appendChild(list);
    };

    // Flows: three tiles, the run going on now as a line of boxes (each
    // state in words as well as in colour) and the last runs. Approve and
    // Try again call the Workspace's own actions, which check the person
    // again; the card is drawn anew from the server afterwards.
    function flowAction(row, button, action, data, entry) {
        var request = {token: (typeof software_token !== "undefined") ? software_token : ""};

        if (action.kind === "approve") {
            request.action = "ws_approval_decide";
            request.approval_id = action.approval_id;
            request.decision = "approved";
        } else {
            request.action = "ws_flow_step_mend";
            request.run_id = action.run_id;
            request.node_id = action.node_id;
            request.how = "retry";
        }

        button.disabled = true;
        $.ajax({
            contentType: "application/json",
            url: "api.php",
            data: JSON.stringify(request),
            type: "POST",
            dataType: "json"
        }).done(function (answer) {
            if (!answer || answer.status !== "success") {
                flowFailed(row, (answer && answer.message) || data.labels.failed);
                button.disabled = false;
                return;
            }
            if (entry && pgDash.reload) {
                pgDash.reload(entry);
            }
        }).fail(function () {
            flowFailed(row, data.labels.failed);
            button.disabled = false;
        });
    }

    function flowFailed(row, text) {
        var note = row.querySelector(".pg-dash-flow-note");
        note.textContent = text;
        note.setAttribute("data-tone", "bad");
        note.setAttribute("role", "status");
    }

    pgDash.renderers.flows = function (body, data, entry) {
        var el = pgDash.el;
        var states = data.states || {};

        body.appendChild(pgDash.tiles(data.tiles));

        if (data.now && (data.now.nodes || []).length) {
            var now = el("div", "pg-dash-flow-now");
            var head = el("p", "pg-dash-flow-head");
            head.appendChild(document.createTextNode(data.labels.now + " · "));
            var name = el("a", "", data.now.flow);
            name.href = data.now.url;
            head.appendChild(name);
            now.appendChild(head);

            var line = el("ol", "pg-dash-flow-line");
            data.now.nodes.forEach(function (node) {
                var item = el("li", "pg-dash-flow-node");
                item.setAttribute("data-state", node.state);
                var dot = el("span", "pg-dash-flow-dot");
                dot.setAttribute("aria-hidden", "true");
                item.appendChild(dot);
                item.appendChild(el("span", "pg-dash-flow-name", node.name));
                item.appendChild(el("span", "visually-hidden", " (" + (states[node.state] || node.state) + ")"));
                line.appendChild(item);
            });
            now.appendChild(line);
            body.appendChild(now);
        }

        if ((data.rows || []).length) {
            body.appendChild(el("h3", "pg-dash-rows-title", data.labels.last));
            var list = el("ul", "pg-dash-flow-runs");

            data.rows.forEach(function (row) {
                var item = el("li", "pg-dash-flow-run");
                item.setAttribute("data-tone", row.tone || "");

                var text = el("span", "pg-dash-flow-text");
                var link = el("a", "pg-dash-flow-title", row.text);
                link.href = row.url;
                text.appendChild(link);
                text.appendChild(el("span", "pg-dash-flow-note", row.note));
                item.appendChild(text);

                if (row.when) {
                    item.appendChild(el("span", "pg-dash-flow-when", row.when));
                }

                if (row.action) {
                    var button = el("button", "btn btn-outline theme-secondary btn-sm pg-dash-flow-action", row.action.label);
                    button.type = "button";
                    button.setAttribute("aria-label", row.action.label + ": " + row.text);
                    button.addEventListener("click", function () {
                        flowAction(item, button, row.action, data, entry);
                    });
                    item.appendChild(button);
                }

                list.appendChild(item);
            });

            body.appendChild(list);
        }

        if (data.url) {
            var more = el("a", "pg-dash-card-more", data.link);
            more.href = data.url;
            body.appendChild(more);
        }
    };
})();
