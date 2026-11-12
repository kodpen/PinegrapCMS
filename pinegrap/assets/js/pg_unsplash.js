/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Unsplash dialog (window.pgUnsplash): popular photos, a search and Load
 * More, read from the Unsplash API in the browser with the site's access key,
 * each tile a button with the photographer's credit the API guidelines ask
 * for. A pick tells Unsplash it was downloaded (download_location) and hands
 * back the photo's address.
 *
 *   pgUnsplash.open({key, texts}) -> Promise of the photo's address or null,
 *   settled once the dialog has closed (docs/memory/ui/dialog-result-after-close.md).
 *
 * pgUnsplash.pure holds the address builders without the DOM
 * (tests/editor_bridge_js_test.php runs them in Node). Served in
 * assets/js/pg_editor_bridge.min.js (pg_js_bundles()); after editing run
 * `php tools/build_js.php`.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (root) {
    'use strict';

    var API = 'https://api.unsplash.com';
    var PER_PAGE = 20;
    var UTM = '?utm_source=pinegrap&utm_medium=referral';

    // The API address of a page: popular photos without a query, a search
    // with one.
    function listUrl(key, query, page) {
        query = String(query || '').trim();
        page = Math.max(1, parseInt(page, 10) || 1);
        if (query === '') {
            return API + '/photos?order_by=popular&per_page=' + PER_PAGE + '&page=' + page + '&client_id=' + encodeURIComponent(key);
        }
        return API + '/search/photos?query=' + encodeURIComponent(query) + '&per_page=' + PER_PAGE + '&page=' + page + '&client_id=' + encodeURIComponent(key);
    }

    // The photos of an answer, its total and whether a further page exists:
    // a search answers {results, total, total_pages}, the popular list an
    // array with the total in the X-Total header.
    function readAnswer(body, page, headerTotal) {
        if (Array.isArray(body)) {
            return { photos: body, total: parseInt(headerTotal, 10) || 0, more: body.length === PER_PAGE };
        }
        body = body || {};
        var photos = Array.isArray(body.results) ? body.results : [];
        return { photos: photos, total: parseInt(body.total, 10) || 0, more: page < (parseInt(body.total_pages, 10) || 0) };
    }

    // A link back to Unsplash carries the referral the guidelines ask for.
    function referral(href, fallback) {
        return (href ? String(href) : fallback) + UTM;
    }

    var pure = { listUrl: listUrl, readAnswer: readAnswer, referral: referral };

    var doc = root.document;

    if (!doc || !doc.createElement) {
        root.pgUnsplash = { pure: pure };
        return;
    }

    function el(tag, className, text) {
        var node = doc.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined && text !== null) {
            node.textContent = text;
        }
        return node;
    }

    function link(href, className, text) {
        var a = el('a', className, text);
        a.href = href;
        a.target = '_blank';
        a.rel = 'noopener';
        return a;
    }

    function open(opts) {
        opts = opts || {};
        var texts = opts.texts || {};
        var t = function (key) { return texts[key] === undefined ? key : texts[key]; };
        var key = String(opts.key || '');
        var state = { query: '', page: 1, result: null, seq: 0 };

        var parts = root.pgUI.dialog.build({ size: 'xl', className: 'pg-unsplash', closeLabel: t('Close'), title: 'Unsplash', scrollable: true });
        var form = el('form', 'd-flex gap-3 mb-4');
        form.setAttribute('role', 'search');
        var input = el('input', 'form-control');
        input.type = 'search';
        input.placeholder = t('Search') + ' Unsplash…';
        input.setAttribute('aria-label', t('Search') + ' Unsplash');
        var go = el('button', 'btn btn-solid theme-primary');
        go.type = 'submit';
        go.appendChild(el('span', 'pg-btn-label', t('Search')));
        form.appendChild(input);
        form.appendChild(go);
        var status = el('div', 'fg-secondary small mb-3');
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        var grid = el('div', 'pg-unsplash-grid');
        grid.style.display = 'grid';
        grid.style.gridTemplateColumns = 'repeat(auto-fill, minmax(140px, 1fr))';
        grid.style.gap = '12px';
        var moreBox = el('div', 'text-center mt-4');
        moreBox.hidden = true;
        var more = el('button', 'btn btn-outline theme-secondary btn-sm');
        more.type = 'button';
        more.appendChild(el('span', 'pg-btn-label', t('Load More')));
        moreBox.appendChild(more);
        [form, status, grid, moreBox].forEach(function (node) { parts.body.appendChild(node); });

        var cancel = el('button', 'btn btn-outline theme-secondary');
        cancel.type = 'button';
        cancel.appendChild(el('span', 'pg-btn-label', t('Cancel')));
        parts.footer.appendChild(cancel);

        function pick(photo) {
            var download = photo.links && photo.links.download_location;
            if (download) {
                // Counted as a download, as the API guidelines ask.
                root.fetch(download + (download.indexOf('?') === -1 ? '?' : '&') + 'client_id=' + encodeURIComponent(key)).catch(function () {});
            }
            state.result = (photo.urls && photo.urls.regular) || null;
            root.pgUI.dialog.hide(parts.root);
        }

        function tile(photo) {
            var box = el('div', 'pg-unsplash-tile');
            var button = el('button', 'pg-unsplash-pick');
            button.type = 'button';
            button.style.cssText = 'display:block;width:100%;aspect-ratio:1;padding:0;border:0;border-radius:8px;overflow:hidden;background:var(--pg-surface-2, transparent);cursor:pointer';
            var img = el('img');
            img.src = (photo.urls && photo.urls.small) || '';
            img.alt = photo.alt_description || '';
            img.loading = 'lazy';
            img.style.cssText = 'width:100%;height:100%;object-fit:cover';
            var name = (photo.user && photo.user.name) || '';
            if (!img.alt) {
                button.setAttribute('aria-label', t('Select') + ': ' + name);
            }
            button.appendChild(img);
            button.addEventListener('click', function () { pick(photo); });
            box.appendChild(button);
            // The credit the Unsplash API guidelines ask for, as text.
            var credit = el('div', 'small mt-1');
            credit.style.cssText = 'font-size:11px;line-height:1.3';
            credit.appendChild(link(referral(photo.user && photo.user.links && photo.user.links.html, 'https://unsplash.com'), 'fg-reset text-decoration-none fw-semibold', name));
            credit.appendChild(doc.createTextNode(' · '));
            credit.appendChild(link(referral(photo.links && photo.links.html, 'https://unsplash.com'), 'fg-secondary text-decoration-none', 'Unsplash'));
            box.appendChild(credit);
            return box;
        }

        function load(next) {
            var seq = ++state.seq;
            var page = next ? state.page + 1 : 1;
            if (!next) {
                grid.textContent = '';
            }
            status.textContent = t('Loading') + '…';
            more.disabled = true;
            root.fetch(listUrl(key, state.query, page)).then(function (response) {
                return response.json().then(function (body) {
                    return readAnswer(body, page, response.headers.get('X-Total'));
                });
            }).then(function (answer) {
                if (seq !== state.seq) {
                    return;
                }
                state.page = page;
                var first = null;
                answer.photos.forEach(function (photo) {
                    var node = tile(photo);
                    first = first || node.firstChild;
                    grid.appendChild(node);
                });
                status.textContent = grid.childNodes.length ? (answer.total ? t('{var:1} result(s)').replace('{var:1}', answer.total) : '') : t('Sorry, we could not find any images.');
                var focused = moreBox.contains(doc.activeElement);
                moreBox.hidden = !answer.more;
                more.disabled = false;
                // Load More hidden under the focus hands it to the first new tile.
                if (focused && moreBox.hidden && first) {
                    first.focus();
                }
            }).catch(function () {
                if (seq === state.seq) {
                    status.textContent = t('Sorry, we could not find any images.');
                    more.disabled = false;
                }
            });
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            state.query = input.value;
            load(false);
        });
        more.addEventListener('click', function () { load(true); });
        cancel.addEventListener('click', function () { root.pgUI.dialog.hide(parts.root); });

        return new Promise(function (resolve) {
            var before = doc.activeElement;
            doc.body.appendChild(parts.root);
            root.pgUI.on(parts.root, 'dialog', 'hidden', function () {
                parts.root.remove();
                if (before && before.focus && doc.contains(before)) {
                    before.focus();
                }
                resolve(state.result);
            }, { once: true });
            root.pgUI.dialog.show(parts.root).then(function () {
                input.focus();
                load(false);
            });
        });
    }

    root.pgUnsplash = { open: open, pure: pure };
})(typeof window !== 'undefined' ? window : this);
