/**
 * Pinegrap - Enterprise Website Platform
 *
 * The brief under the new dashboard's greeting
 * (includes/dashboard/v2/page_greeting.php). "Say something else" asks
 * api.php's get_dashboard_brief for another line, which comes back with the
 * workspace strip and the next meeting; once a minute the next meeting
 * counts down and a running timer moves on. Every text was translated on the
 * server.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    "use strict";

    var skew = 0;
    var texts = null;
    var timer = null;

    function brief() {
        return document.getElementById("pg_dash_brief");
    }

    // The server's clock, so the countdown agrees with the line it drew.
    function now() {
        return Date.now() + skew;
    }

    // Same sum as pg_dashboard_brief_until(): minutes are rounded up. Every
    // event left today is in the markup and CSS shows the first; one that
    // has ended is taken out, which brings the next one forward.
    function countDown(at) {
        document.querySelectorAll(".pg-dash-brief-next .pg-next").forEach(function (item) {
            var starts = parseInt(item.getAttribute("data-pg-starts"), 10) * 1000;
            var ends = parseInt(item.getAttribute("data-pg-ends"), 10) * 1000;
            var label = item.querySelector(".pg-next-until");

            if (at >= ends) {
                item.remove();
                return;
            }

            if ((item.getAttribute("data-pg-all-day") === "1") || !label || !texts) {
                return;
            }

            if (at >= starts) {
                item.classList.add("is-now");
                label.textContent = texts.now;
                return;
            }

            var minutes = Math.ceil((starts - at) / 60000);
            var hours = Math.floor(minutes / 60);

            label.textContent = (minutes < 60)
                ? texts.min.replace("%1", minutes)
                : ((minutes % 60) === 0
                    ? texts.h.replace("%1", hours)
                    : texts.hmin.replace("%1", hours).replace("%2", minutes % 60));
        });

        document.querySelectorAll(".pg-dash-brief-next .pg-clockbar-next").forEach(function (box) {
            if (!box.querySelector(".pg-next")) {
                box.remove();
            }
        });
    }

    // A running workspace timer in the strip, as hours and minutes since it
    // started.
    function elapsed(at) {
        var root = brief();

        if (!root) {
            return;
        }

        root.querySelectorAll("[data-pg-elapsed]").forEach(function (box) {
            var started = parseInt(box.getAttribute("data-pg-elapsed"), 10);

            if (!started) {
                return;
            }

            var seconds = Math.max(0, Math.floor(at / 1000) - started);
            var minutes = Math.floor((seconds % 3600) / 60);
            box.textContent = Math.floor(seconds / 3600) + ":" + ((minutes < 10) ? "0" : "") + minutes;
        });
    }

    function tick() {
        var at = now();

        countDown(at);
        elapsed(at);

        window.clearTimeout(timer);
        timer = window.setTimeout(tick, 60000 - (at % 60000) + 50);
    }

    function apply(data) {
        var root = brief();

        if (!root || !data) {
            return;
        }

        var mark = root.querySelector(".pg-dash-brief-mark .bi");

        if (mark && /^bi-[a-z0-9-]+$/.test(data.icon || "")) {
            mark.className = "bi " + data.icon;
        }

        // The server built this markup and escaped every text in it.
        $(root).find(".pg-dash-brief-text").html(data.line || "");
        $(root).children(".pg-brief-ws").remove();
        $(root).append(data.strip || "");
        $(".pg-dash-brief-next").html(data.next || "");

        // The arrival runs again, so the change is seen.
        root.classList.remove("is-new");
        void root.offsetWidth;
        root.classList.add("is-new");

        tick();
    }

    function again(button) {
        if (button.disabled) {
            return;
        }

        button.disabled = true;
        button.classList.add("is-busy");

        $.ajax({
            contentType: "application/json",
            url: "api.php",
            data: JSON.stringify({
                action: "get_dashboard_brief",
                dashboard: "v2",
                token: (typeof software_token !== "undefined") ? software_token : ""
            }),
            type: "POST",
            dataType: "json"
        }).done(function (answer) {
            if (answer && (answer.status === "success")) {
                apply(answer.data);
            }
        }).always(function () {
            button.disabled = false;
            button.classList.remove("is-busy");
        });
    }

    function start() {
        var root = brief();

        if (!root) {
            return;
        }

        skew = (parseInt(root.getAttribute("data-pg-now"), 10) * 1000 - Date.now()) || 0;

        try {
            texts = JSON.parse(root.getAttribute("data-pg-until") || "null");
        } catch (error) {
            texts = null;
        }

        tick();
    }

    // Delegated: Cancel in edit mode puts back a copy of the greeting, and a
    // listener on the old button would be gone with it.
    document.addEventListener("click", function (event) {
        var button = (event.target && event.target.closest) ? event.target.closest("#pg_dash_brief_again") : null;

        if (button) {
            again(button);
        }
    });

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", start);
    } else {
        start();
    }
})();
