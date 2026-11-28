/**
 * Pinegrap - Enterprise Website Platform
 *
 * "Was this helpful?" on a collection record's page. The server draws the
 * Yes and No buttons hidden (data-pg-kb-vote, data-pg-kb-record:
 * _pg_sw_apply_kb_vote_bindings()) and adds this file only to a page that
 * holds one (pg_kb_vote_inject()). For a record this browser has not
 * answered, the buttons are shown; a press posts the answer to kb_vote.php
 * and the buttons give way to a thank-you line. A record the cookie already
 * lists shows the thank-you line at once, so a page served from a cache
 * still asks only once.
 *
 * Plain ES5 and no jQuery: it has to work whatever the design loads.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var config = window.pgKbVote || {};

    function answered(record) {
        var name = String(config.cookie || 'pg_kb_votes') + '=';
        var parts = document.cookie ? document.cookie.split(';') : [];
        for (var i = 0; i < parts.length; i++) {
            var part = parts[i].replace(/^\s+/, '');
            if (part.indexOf(name) === 0) {
                var ids = decodeURIComponent(part.substring(name.length)).split('.');
                for (var j = 0; j < ids.length; j++) {
                    if (ids[j] === record) return true;
                }
            }
        }
        return false;
    }

    function buttonsOf(record) {
        var all = document.querySelectorAll('[data-pg-kb-vote][data-pg-kb-record]');
        var out = [];
        for (var i = 0; i < all.length; i++) {
            if (all[i].getAttribute('data-pg-kb-record') === record) out.push(all[i]);
        }
        return out;
    }

    function show(el, on) {
        if (on) {
            el.removeAttribute('hidden');
            el.classList.remove('d-none');
        } else {
            el.setAttribute('hidden', '');
            el.classList.add('d-none');
        }
    }

    // The thank-you line, once per record, after the last of its buttons.
    function thank(record, text) {
        var buttons = buttonsOf(record);
        if (!buttons.length) return;
        for (var i = 0; i < buttons.length; i++) show(buttons[i], false);
        var last = buttons[buttons.length - 1];
        var note = last.parentNode.querySelector('[data-pg-kb-thanks]');
        if (!note) {
            note = document.createElement('p');
            note.className = 'pg-kb-vote-thanks mb-0';
            note.setAttribute('role', 'status');
            note.setAttribute('data-pg-kb-thanks', '');
            last.parentNode.insertBefore(note, last.nextSibling);
        }
        note.textContent = text;
    }

    function send(button) {
        var record = button.getAttribute('data-pg-kb-record');
        var buttons = buttonsOf(record);
        for (var i = 0; i < buttons.length; i++) buttons[i].disabled = true;
        var body = 'record=' + encodeURIComponent(record) + '&answer=' + encodeURIComponent(button.getAttribute('data-pg-kb-vote'));
        var request = new XMLHttpRequest();
        request.open('POST', String(config.url || ''), true);
        request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        request.setRequestHeader('X-PG-Vote', '1');
        request.onreadystatechange = function () {
            if (request.readyState !== 4) return;
            // 409: this browser answered already, from another tab.
            if (request.status === 200 || request.status === 409) {
                thank(record, String(config.thanks || ''));
                return;
            }
            for (var k = 0; k < buttons.length; k++) buttons[k].disabled = false;
            thank(record, String(config.failed || ''));
            for (var m = 0; m < buttons.length; m++) show(buttons[m], true);
        };
        request.send(body);
    }

    function start() {
        var buttons = document.querySelectorAll('[data-pg-kb-vote][data-pg-kb-record]');
        for (var i = 0; i < buttons.length; i++) {
            var button = buttons[i];
            var record = button.getAttribute('data-pg-kb-record');
            // No record shown (not found, or no access): the buttons stay hidden.
            if (!/^[1-9][0-9]*$/.test(record || '')) continue;
            if (answered(record)) {
                thank(record, String(config.thanks || ''));
                continue;
            }
            show(button, true);
            button.addEventListener('click', function (event) {
                event.preventDefault();
                if (!this.disabled) send(this);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
