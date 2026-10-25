/**
 * Pinegrap - Enterprise Website Platform
 *
 * The favourite hearts of the catalog widgets: a button bound to the
 * catalog_wishlist action on a listing card, or to wishlist on a product
 * page, saves the product to the visitor's favourites (wishlist.php). The
 * file is added to a page only when the page holds a heart
 * (pg_wishlist_inject(), with window.pgWishlist: the endpoint's address and
 * the words the script may show).
 *
 * The server draws every heart hidden (marked data-pg-wishlist-hidden, which
 * the page's rule hides whatever display classes the designer gave it) and
 * empty, the same for every visitor, so the page can come from the page
 * cache. One request (wishlist.php?action=state, never cached) brings the
 * visitor's list and the session token; each heart of a product in the list
 * is pressed (aria-pressed, the active class, bi-heart-fill for a bi-heart
 * icon), and all of them lose the mark; their classes are never touched.
 * Without that answer they stay hidden: a heart that could not save anything
 * is not offered. A page the browser brings back from its back/forward
 * cache asks again, since the list and the token may have changed meanwhile.
 *
 * A click saves or removes the product: every heart of it on the page
 * follows at once, and goes back if the server refuses, whose words are
 * read out to a screen reader (the page's own words when there are none,
 * or no answer came at all). The button stays a button throughout, usable
 * from the keyboard. On a product page of a variant group, the variant the
 * visitor picks (pg_civ_variants.js, the pg-civ-product-change event)
 * becomes the product of the hearts inside that page's form.
 *
 * Plain ES5 and no jQuery: it has to work whatever the design loads. Under
 * Node (tests/wishlist_heart_test.php) it exports create() and starts
 * nothing.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (root) {
    'use strict';

    var MARK = '[data-pg-wishlist]';
    var HIDDEN = 'data-pg-wishlist-hidden';
    var STATUS_ID = 'pg-wishlist-status';

    /**
     * The hearts of one document.
     *
     * @param {Object} env document, fetch, config ({url, labels}), and the
     *                     window for its pageshow (optional)
     * @return {Object} start() and toggle(el), each answering a Promise
     */
    function create(env) {
        var doc = env.document;
        var send = env.fetch;
        var config = env.config || {};
        var labels = config.labels || {};
        var token = '';
        // Product id => true for the products in the list; null until the
        // state request has answered.
        var saved = null;
        // Product id => true while a change of it is on its way.
        var pending = {};

        function label(key) {
            return Object.prototype.hasOwnProperty.call(labels, key) ? String(labels[key]) : '';
        }

        // The endpoint, with the language of a page drawn in another one
        // (<html data-pg-lang>), as the other visitor scripts send it: the
        // server's refusals are then in the page's language.
        function address(action) {
            var url = String(config.url || '');
            var query = [];
            var lang = (doc.documentElement && doc.documentElement.getAttribute)
                ? doc.documentElement.getAttribute('data-pg-lang') : '';
            if (action) query.push('action=' + action);
            if (lang) query.push('pg_lang=' + encodeURIComponent(lang));
            if (!url || !query.length) return url;
            return url + ((url.indexOf('?') === -1) ? '?' : '&') + query.join('&');
        }

        function hearts(scope) {
            return Array.prototype.slice.call((scope || doc).querySelectorAll(MARK));
        }

        // The product a heart stands for; '' for a mark the server left
        // empty (a heart outside a card's loop, a product not found).
        function productOf(el) {
            var id = String(el.getAttribute('data-pg-wishlist') || '');
            return /^[1-9][0-9]{0,9}$/.test(id) ? id : '';
        }

        function paint(el, on) {
            el.setAttribute('aria-pressed', on ? 'true' : 'false');
            if (on) el.classList.add('active'); else el.classList.remove('active');
            var icons = el.querySelectorAll('i');
            for (var i = 0; i < icons.length; i++) {
                var icon = icons[i];
                if (!icon.classList.contains('bi-heart') && !icon.classList.contains('bi-heart-fill')) continue;
                icon.classList.remove(on ? 'bi-heart' : 'bi-heart-fill');
                icon.classList.add(on ? 'bi-heart-fill' : 'bi-heart');
            }
        }

        // Shows a heart in its state, once the list is known and the heart
        // has a product; hides it otherwise. Only the mark comes and goes:
        // the designer's classes (d-none d-md-inline-block) decide the rest.
        function show(el) {
            var id = productOf(el);
            if (saved === null || !id) {
                el.setAttribute(HIDDEN, '1');
                return;
            }
            paint(el, !!saved[id]);
            el.removeAttribute(HIDDEN);
        }

        function paintProduct(id, on) {
            hearts().forEach(function (el) {
                if (productOf(el) === id) paint(el, on);
            });
        }

        function busy(id, on) {
            hearts().forEach(function (el) {
                if (productOf(el) !== id) return;
                if (on) el.setAttribute('aria-busy', 'true'); else el.removeAttribute('aria-busy');
            });
        }

        // Read out to a screen reader: a polite status line, visually hidden.
        function announce(text) {
            if (!text || !doc.body) return;
            var box = doc.getElementById(STATUS_ID);
            if (!box) {
                box = doc.createElement('div');
                box.setAttribute('id', STATUS_ID);
                box.setAttribute('class', 'visually-hidden');
                box.setAttribute('role', 'status');
                box.setAttribute('aria-live', 'polite');
                doc.body.appendChild(box);
            }
            box.textContent = '';
            box.textContent = text;
        }

        // The visitor's list and the session token. Answers whether it came.
        function refresh() {
            return send(address('state'), {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (response) {
                if (!response.ok) throw new Error('state ' + response.status);
                return response.json();
            }).then(function (data) {
                if (!data || !Array.isArray(data.ids)) throw new Error('state');
                token = String(data.token || '');
                saved = {};
                data.ids.forEach(function (id) { saved[String(id)] = true; });
                hearts().forEach(show);
                return true;
            }).then(null, function () {
                return false;
            });
        }

        // Saves (on) or removes a product. Resolves with the server's
        // {product_id, on}; rejects with an Error carrying the server's
        // words (message) and the status, or no words and status 0 when no
        // answer came (the browser's own error text is English).
        function write(id, on) {
            return send(address(''), {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'action=' + (on ? 'add' : 'remove')
                    + '&product_id=' + encodeURIComponent(id)
                    + '&token=' + encodeURIComponent(token)
            }).then(function (response) {
                return response.json().then(null, function () { return null; }).then(function (data) {
                    if (response.ok && data && typeof data.on === 'boolean') return data;
                    var error = new Error((data && data.message) ? String(data.message) : '');
                    error.status = response.status;
                    throw error;
                });
            }, function () {
                // Offline or a dropped connection: no words of the server's.
                var error = new Error('');
                error.status = 0;
                throw error;
            });
        }

        // Saves a product the heart shows empty, removes one it shows
        // pressed. Every heart of the product changes at once and goes back
        // when the server refuses; a click while the product's change is on
        // its way is ignored.
        function toggle(el) {
            var id = productOf(el);
            if (saved === null || !id || pending[id]) return Promise.resolve(false);

            var was = !!saved[id];
            var done = function (on) {
                if (on) saved[id] = true; else delete saved[id];
                paintProduct(id, on);
            };

            pending[id] = true;
            done(!was);
            busy(id, true);

            return write(id, !was).then(function (reply) {
                done(!!reply.on);
                return true;
            }, function (error) {
                done(was);
                announce((error && error.message) ? error.message : label('failed'));
                // A session that ended took its token: the next click
                // carries a new one.
                if (error && error.status === 403) refresh();
                return false;
            }).then(function (ok) {
                delete pending[id];
                busy(id, false);
                return ok;
            });
        }

        function onClick(event) {
            var target = event.target;
            var el = (target && target.closest) ? target.closest(MARK) : null;
            if (!el || saved === null) return;
            event.preventDefault();
            toggle(el);
        }

        // A variant picked on a product page: the hearts inside its form
        // stand for the new product from now on, busy only while that
        // product's change is on its way (the end of an earlier product's
        // change no longer finds them).
        function onProductChange(event) {
            var id = (event.detail && event.detail.productId) ? String(event.detail.productId) : '';
            var scope = (event.target && event.target.querySelectorAll) ? event.target : null;
            if (!scope) return;
            hearts(scope).forEach(function (el) {
                el.setAttribute('data-pg-wishlist', id);
                if (id && pending[id]) el.setAttribute('aria-busy', 'true'); else el.removeAttribute('aria-busy');
                show(el);
            });
        }

        function start() {
            if (!hearts().length || !config.url) return Promise.resolve(false);
            doc.addEventListener('click', onClick);
            doc.addEventListener('pg-civ-product-change', onProductChange);
            var view = env.window;
            if (view && typeof view.addEventListener === 'function') {
                // A page brought back from the back/forward cache runs no
                // script again and keeps the list and the token of its first
                // load: read both anew.
                view.addEventListener('pageshow', function (event) {
                    if (event && event.persisted) refresh();
                });
            }
            return refresh();
        }

        return { start: start, toggle: toggle };
    }

    var api = { create: create };

    if (typeof module === 'object' && module && module.exports) {
        module.exports = api;
    }

    // In a browser: start on the page's own hearts.
    if (!root || !root.document || typeof root.fetch !== 'function' || !root.Promise || !Array.isArray) return;

    var page = create({
        document: root.document,
        window: root,
        fetch: function (url, options) { return root.fetch(url, options); },
        config: root.pgWishlist || {}
    });

    if (root.document.readyState === 'loading') {
        root.document.addEventListener('DOMContentLoaded', function () { page.start(); });
    } else {
        page.start();
    }
})(typeof window !== 'undefined' ? window : this);
