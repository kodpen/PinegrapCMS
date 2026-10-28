/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - what happens on the site, in the screens: a channel's switch
 * for hearing about the records tied to it.
 *
 * assets/js/workspace.js draws the channel's settings and asks this file for
 * the pieces:
 *
 * - PGWsEvents.icon is the sign of an event start on a scheduled action's
 *   card;
 * - PGWsEvents.watchField(channel) returns the switch and its value(), or
 *   null where the channel cannot watch.
 *
 * The server checks everything again (includes/workspace/watch.php); the
 * texts come from the #ws-config block.
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

    var configNode = document.getElementById('ws-config');
    var CFG = {};

    try {
        CFG = JSON.parse((configNode && configNode.textContent) || '{}');
    } catch (error) {
        CFG = {};
    }

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

    // The channel's switch: hear about its customer's orders and the records
    // tagged in it. Null where the server says the channel cannot.
    function watchField(channel) {
        if (!channel || (channel.watch === null) || (channel.watch === undefined)) {
            return null;
        }

        var wrap = el('div', 'form-field mb-5 ws-watch-field');
        var input = el('input', '');
        var label = el('label', '', text('watch_label'));

        uid += 1;
        input.type = 'checkbox';
        input.id = 'ws-watch-' + uid;
        input.checked = !!channel.watch;
        label.htmlFor = input.id;

        wrap.appendChild(el('div', 'switch')).appendChild(input);
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
        icon: 'bi-broadcast',
        watchField: watchField
    };
})();
