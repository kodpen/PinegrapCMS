/**
 * Pinegrap - Enterprise Website Platform
 *
 * Real-user page speed: the page's Largest Contentful Paint, Interaction to
 * Next Paint and Cumulative Layout Shift, measured in the visitor's browser
 * with PerformanceObserver and sent once, when the page is hidden or left,
 * to page_view.php with navigator.sendBeacon (served as pg_vitals.min.js,
 * printed by pg_vitals_beacon_tag() in includes/fn/page_vitals.php).
 *
 * The script tag carries the endpoint (data-u), the page id (data-p) and the
 * page's signature (data-s). The request holds those, the three figures and
 * the width class of the screen; no cookie, no identifier. The server puts
 * them into the hourly rollup page_vitals_hourly.
 *
 *   • LCP: the last largest-contentful-paint entry before the first input,
 *     in milliseconds.
 *   • INP: the longest event duration among the interactions of the page
 *     (an approximation of the 98th percentile that equals it below fifty
 *     interactions), in milliseconds; empty when nobody interacted.
 *   • CLS: the largest session window of layout shifts without recent
 *     input (shifts at most 1 s apart, a window at most 5 s), × 1000.
 *
 * A page restored from the back-forward cache sends nothing more: the
 * figures of its first showing are the ones that were sent.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function () {
    'use strict';

    var script = document.currentScript;

    if (!script || !window.PerformanceObserver || !navigator.sendBeacon || !window.FormData) {
        return;
    }

    var endpoint = script.getAttribute('data-u') || '';
    var page = script.getAttribute('data-p') || '';
    var signature = script.getAttribute('data-s') || '';

    if (!endpoint || !page || !signature) {
        return;
    }

    var supported = PerformanceObserver.supportedEntryTypes || [];
    var lcp = -1;
    var inp = -1;
    var cls = 0;
    var clsSeen = false;
    var windowValue = 0;
    var windowFirst = 0;
    var windowLast = 0;
    var sent = false;
    var observers = [];

    function observe(type, callback, options) {
        if (supported.indexOf(type) < 0) {
            return;
        }

        try {
            var observer = new PerformanceObserver(function (list) {
                callback(list.getEntries());
            });
            var init = { type: type, buffered: true };

            for (var key in options) {
                if (Object.prototype.hasOwnProperty.call(options, key)) {
                    init[key] = options[key];
                }
            }

            observer.observe(init);
            observers.push({ observer: observer, callback: callback });
        } catch (e) {
            // An old engine without the type option: no figure for it.
        }
    }

    observe('largest-contentful-paint', function (entries) {
        if (entries.length) {
            lcp = entries[entries.length - 1].startTime;
        }
    });

    observe('event', function (entries) {
        for (var i = 0; i < entries.length; i++) {
            if (entries[i].interactionId && (entries[i].duration > inp)) {
                inp = entries[i].duration;
            }
        }
    }, { durationThreshold: 40 });

    // The first interaction is counted even when it was quick.
    observe('first-input', function (entries) {
        if (entries.length && (entries[0].duration > inp)) {
            inp = entries[0].duration;
        }
    });

    observe('layout-shift', function (entries) {
        for (var i = 0; i < entries.length; i++) {
            var entry = entries[i];

            if (entry.hadRecentInput) {
                continue;
            }

            clsSeen = true;

            if (windowValue && ((entry.startTime - windowLast) < 1000) && ((entry.startTime - windowFirst) < 5000)) {
                windowValue += entry.value;
            } else {
                windowValue = entry.value;
                windowFirst = entry.startTime;
            }

            windowLast = entry.startTime;

            if (windowValue > cls) {
                cls = windowValue;
            }
        }
    });

    if (supported.indexOf('layout-shift') >= 0) {
        clsSeen = true;
    }

    // The width class the server keeps the figures under.
    function device() {
        var width = window.innerWidth || document.documentElement.clientWidth || 0;

        if (width < 768) {
            return 'mobile';
        }

        return (width < 1024) ? 'tablet' : 'desktop';
    }

    function figure(value) {
        return (value >= 0) ? String(Math.round(value)) : '';
    }

    function send() {
        if (sent) {
            return;
        }

        // Whatever the observers hold but have not handed over yet.
        for (var i = 0; i < observers.length; i++) {
            try {
                observers[i].callback(observers[i].observer.takeRecords());
            } catch (e) {
                // Nothing to take.
            }
        }

        var values = [figure(lcp), figure(inp), clsSeen ? figure(cls * 1000) : ''];

        if ((values[0] === '') && (values[1] === '') && (values[2] === '')) {
            return;
        }

        sent = true;

        try {
            var data = new FormData();
            data.append('vp', page);
            data.append('vs', signature);
            data.append('v', values.join(',') + ',' + device());
            navigator.sendBeacon(endpoint, data);
        } catch (e) {
            // A lost figure is no error for the visitor.
        }
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') {
            send();
        }
    });

    window.addEventListener('pagehide', send);

    // Back from the back-forward cache: no second set of figures.
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            sent = true;
        }
    });
})();
