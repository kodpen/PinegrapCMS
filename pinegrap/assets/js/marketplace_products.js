/**
 * Pinegrap - Enterprise Website Platform
 *
 * Marketplace products (marketplace_products.php): reading the store's
 * catalogue from the marketplace a stretch at a time, the steps on each row
 * (accept, pick another product, not sold here, remove a match, undo), the
 * picture grid that picks a shop product, and accepting many at once. In the
 * shop view: matching a product under its own code or a typed one, sending
 * it now, removing its match, and the same for the ticked ones.
 *
 * Every step is a POST to the screen, answered in JSON. A row step answers the
 * row drawn again in its new state; it stays where it is, so the operator sees
 * what happened and can undo it, and the filters' counts move with it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function () {
    'use strict';

    var config = window.mpProducts;

    if (!config) {
        return;
    }

    var labels = config.labels || {};

    function label(key, vars) {
        var text = labels[key] || key;

        (vars || []).forEach(function (value, index) {
            text = text.split('{var:' + (index + 1) + '}').join(String(value));
        });

        return text;
    }

    function number(value) {
        try {
            return new Intl.NumberFormat(document.documentElement.lang || undefined).format(value);
        } catch (e) {
            return String(value);
        }
    }

    // One step. Resolves with the decoded answer; an answer that is not JSON
    // (the login page after the session ended) is told as such.
    function post(action, data) {
        var body = new FormData();

        body.append('mp_action', action);
        body.append('account', config.account);
        body.append('token', config.token);

        Object.keys(data || {}).forEach(function (key) {
            var value = data[key];

            if (Array.isArray(value)) {
                value.forEach(function (item) { body.append(key + '[]', item); });
            } else {
                body.append(key, value);
            }
        });

        return fetch(config.url, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (response) {
                return response.text().then(function (text) {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        return { ok: false, error: label('session') };
                    }
                });
            }, function () {
                return { ok: false, error: label('failed') };
            });
    }

    function flash(message, kind) {
        var host = document.getElementById('mpp-flash');

        if (!host) {
            return;
        }

        var alert = document.createElement('div');

        alert.className = 'alert alert-' + (kind || 'info') + ' alert-dismissible fade show';
        alert.setAttribute('role', kind === 'danger' ? 'alert' : 'status');
        alert.textContent = message;

        var close = document.createElement('button');

        close.type = 'button';
        close.className = 'btn-close';
        close.setAttribute('aria-label', label('close'));
        close.addEventListener('click', function () { alert.remove(); });

        alert.appendChild(close);
        host.innerHTML = '';
        host.appendChild(alert);
    }

    function setCounts(counts) {
        if (!counts) {
            return;
        }

        document.querySelectorAll('[data-mp-count]').forEach(function (el) {
            var key = el.getAttribute('data-mp-count');

            if (Object.prototype.hasOwnProperty.call(counts, key)) {
                el.textContent = number(counts[key]);
            }
        });

        var sure = document.getElementById('mpp-accept-sure');

        if (sure) {
            sure.classList.toggle('d-none', !counts.sure);
        }

        var meter = document.querySelector('#mpp-meter > i');

        if (meter && counts.all) {
            meter.style.width = Math.round(100 * counts.matched / counts.all) + '%';
        }
    }

    /* ------------------------------------------------------------- the read */

    var readButton = document.getElementById('mpp-read');

    // Where a read that stopped goes on from: the page to ask for, the read's
    // start and how many rows it had stored. Null starts a new read.
    var resume = null;

    function readFrom(page, run, readSoFar) {
        var meter = document.getElementById('mpp-meter');
        var text = document.getElementById('mpp-progress-text');

        readButton.disabled = true;

        if (meter) {
            meter.classList.add('busy');
        }

        if (text && !readSoFar) {
            text.textContent = label('reading');
        }

        post('read', { page: page, run: run }).then(function (answer) {
            if (!answer.ok) {
                readButton.disabled = false;
                readButton.textContent = label('continue');
                resume = { page: (answer.next !== undefined) ? answer.next : page, run: answer.run || run, read: readSoFar };

                if (meter) {
                    meter.classList.remove('busy');
                }

                flash(label('read_failed', [answer.error || label('failed')]), 'danger');

                return;
            }

            var read = readSoFar + (answer.read || 0);
            var total = Math.max(answer.total || 0, read);

            if (meter && total) {
                meter.querySelector('i').style.width = Math.min(100, Math.round(100 * read / total)) + '%';
            }

            if (text) {
                text.textContent = label('read_progress', [number(read), number(total)]);
            }

            if (answer.done) {
                if (text) {
                    text.textContent = label('read_done', [number(answer.total || read), number(answer.suggested || 0), number(answer.sure || 0)]);
                }

                window.location.href = config.url + '?account=' + encodeURIComponent(config.account);

                return;
            }

            readFrom(answer.next, answer.run, read);
        });
    }

    if (readButton) {
        readButton.addEventListener('click', function () {
            var from = resume || { page: 0, run: 0, read: 0 };

            resume = null;
            readFrom(from.page, from.run, from.read);
        });
    }

    /* ------------------------------------------------------------- the rows */

    var list = document.getElementById('mpp-list');

    function replaceRow(row, html) {
        if (!html) {
            row.remove();
            return null;
        }

        var holder = document.createElement('div');

        holder.innerHTML = html;

        var fresh = holder.firstElementChild;

        row.replaceWith(fresh);

        return fresh;
    }

    function rowStep(row, action, data) {
        row.classList.add('is-busy');

        return post(action, Object.assign({ id: row.getAttribute('data-mp-row') }, data || {})).then(function (answer) {
            row.classList.remove('is-busy');

            if (answer.html) {
                row = replaceRow(row, answer.html);
            }

            setCounts(answer.counts);

            if (!answer.ok) {
                flash(answer.error || label('failed'), 'warning');
            }

            updateBulk();

            return answer;
        });
    }

    if (list) {
        list.addEventListener('click', function (event) {
            var button = event.target.closest('[data-mp-act]');

            if (!button || !list.contains(button)) {
                return;
            }

            var row = button.closest('[data-mp-row]');
            var act = button.getAttribute('data-mp-act');

            if (row.hasAttribute('data-mp-shop')) {
                if (act === 'code') {
                    editCode(row);
                } else if (act === 'shop_remove') {
                    if (window.confirm(label('confirm_remove', ['1']))) {
                        shopStep(act, [row.getAttribute('data-mp-row')], button);
                    }
                } else {
                    shopStep(act, [row.getAttribute('data-mp-row')], button);
                }
            } else if (act === 'accept') {
                rowStep(row, 'accept', { product_id: button.getAttribute('data-mp-product') });
            } else if (act === 'pick') {
                openPicker(row);
            } else if ((act === 'dismiss') || (act === 'restore') || (act === 'unmatch')) {
                rowStep(row, act);
            }
        });

        list.addEventListener('change', function (event) {
            if (event.target.matches('[data-mp-select]')) {
                updateBulk();
            }
        });
    }

    /* ----------------------------------------------------------- many at once */

    var bulk = document.getElementById('mpp-bulk');
    var selectAll = document.getElementById('mpp-select-all');

    function selected() {
        return Array.prototype.map.call(
            document.querySelectorAll('[data-mp-select]:checked'),
            function (box) { return box.getAttribute('data-mp-select'); });
    }

    function updateBulk() {
        if (!bulk) {
            return;
        }

        var ids = selected();

        document.getElementById('mpp-bulk-n').textContent = number(ids.length);
        bulk.classList.toggle('d-none', !ids.length);

        if (selectAll) {
            var boxes = document.querySelectorAll('[data-mp-select]');

            selectAll.checked = boxes.length > 0 && ids.length === boxes.length;
            selectAll.indeterminate = ids.length > 0 && ids.length < boxes.length;
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('[data-mp-select]').forEach(function (box) {
                box.checked = selectAll.checked;
            });
            updateBulk();
        });
    }

    function acceptMany(action, data, button) {
        button.disabled = true;

        post(action, data).then(function (answer) {
            button.disabled = false;

            if (!answer.ok) {
                flash(answer.error || label('failed'), 'danger');
                return;
            }

            setCounts(answer.counts);
            window.location.reload();
        });
    }

    if (bulk) {
        document.getElementById('mpp-bulk-clear').addEventListener('click', function () {
            document.querySelectorAll('[data-mp-select]').forEach(function (box) { box.checked = false; });
            updateBulk();
        });

        var bulkAccept = document.getElementById('mpp-bulk-accept');

        if (bulkAccept) {
            bulkAccept.addEventListener('click', function () {
                var ids = selected();

                if (ids.length) {
                    acceptMany('accept_many', { ids: ids }, this);
                }
            });
        }
    }

    /* -------------------------------------------------------- the shop view */

    // One step on some products of the shop view (none for "send all"). The
    // rows it changed come back drawn again and take their old places.
    function shopStep(action, ids, button) {
        if (button) {
            button.disabled = true;
        }

        ids.forEach(function (id) {
            var row = list && list.querySelector('[data-mp-row="' + id + '"]');

            if (row) {
                row.classList.add('is-busy');
            }
        });

        return post(action, { ids: ids }).then(function (answer) {
            if (button) {
                button.disabled = false;
            }

            // A redrawn row keeps its tick, so the next step can take the same
            // products (match them, then send them).
            Object.keys(answer.rows || {}).forEach(function (id) {
                var row = list && list.querySelector('[data-mp-row="' + id + '"]');

                if (row) {
                    var box = row.querySelector('[data-mp-select]');
                    var ticked = box ? box.checked : false;
                    var fresh = replaceRow(row, answer.rows[id]);
                    var freshBox = fresh ? fresh.querySelector('[data-mp-select]') : null;

                    if (freshBox) {
                        freshBox.checked = ticked;
                    }
                }
            });

            if (list) {
                list.querySelectorAll('.is-busy').forEach(function (row) { row.classList.remove('is-busy'); });
            }

            setCounts(answer.counts);
            updateBulk();

            if (answer.error) {
                flash(answer.error, 'warning');
            } else if (answer.message) {
                flash(answer.message, 'success');
            }

            return answer;
        });
    }

    document.querySelectorAll('[data-mp-bulk]').forEach(function (button) {
        button.addEventListener('click', function () {
            var action = button.getAttribute('data-mp-bulk');
            var ids = (action === 'shop_send_all') ? [] : selected();

            if ((action !== 'shop_send_all') && !ids.length) {
                return;
            }

            if ((action === 'shop_remove') && !window.confirm(label('confirm_remove', [number(ids.length)]))) {
                return;
            }

            shopStep(action, ids, button);
        });
    });

    // The code a product is sent to, typed in its own row: empty means its
    // own code (data-mp-own), which the hint under the box names.
    function editCode(row) {
        var cell = row.querySelector('.mpp-side.shop');

        if (!cell || cell.querySelector('.mpp-code-edit')) {
            return;
        }

        var before = cell.innerHTML;
        var form = element('form', 'mpp-code-edit');
        var input = element('input', 'form-control form-control-sm');
        var save = element('button', 'btn btn-sm btn-solid theme-primary', label('save'));
        var cancel = element('button', 'btn btn-sm btn-ghost', label('cancel'));

        input.type = 'text';
        input.maxLength = 255;
        input.value = row.getAttribute('data-mp-code') || '';
        input.setAttribute('aria-label', label('code'));
        input.placeholder = label('code');
        save.type = 'submit';
        cancel.type = 'button';

        form.appendChild(input);
        form.appendChild(save);
        form.appendChild(cancel);

        if (row.getAttribute('data-mp-own')) {
            form.appendChild(element('small', '', label('code_hint', [row.getAttribute('data-mp-own')])));
        }

        cell.innerHTML = '';
        cell.appendChild(form);
        input.focus();

        cancel.addEventListener('click', function () {
            cell.innerHTML = before;
        });

        form.addEventListener('submit', function (event) {
            var id = row.getAttribute('data-mp-row');

            event.preventDefault();
            save.disabled = true;

            post('shop_code', { id: id, code: input.value.trim() }).then(function (answer) {
                save.disabled = false;

                if (!answer.ok) {
                    flash(answer.error || label('failed'), 'warning');
                    input.focus();
                    return;
                }

                if (answer.rows && answer.rows[id]) {
                    replaceRow(row, answer.rows[id]);
                }

                setCounts(answer.counts);
            });
        });
    }

    var acceptSure = document.getElementById('mpp-accept-sure');

    if (acceptSure) {
        acceptSure.addEventListener('click', function () {
            var count = (acceptSure.querySelector('[data-mp-count]') || {}).textContent || '';

            if (window.confirm(label('confirm_sure', [count]))) {
                acceptMany('accept_sure', {}, acceptSure);
            }
        });
    }

    /* ------------------------------------------------------------ the picker */

    var picker = null;

    function element(tag, className, text) {
        var el = document.createElement(tag);

        if (className) {
            el.className = className;
        }

        if (text !== undefined) {
            el.textContent = text;
        }

        return el;
    }

    function picture(src) {
        if (!src) {
            var none = element('span', 'mpp-img mpp-noimg');
            none.appendChild(element('i', 'bi bi-image'));
            return none;
        }

        var img = element('img', 'mpp-img');

        img.src = src;
        img.alt = '';
        img.loading = 'lazy';

        return img;
    }

    function buildPicker() {
        var built = pgUI.dialog.build({
            id: 'mpp-picker',
            title: label('pick_title'),
            size: 'xl',
            scrollable: true,
            closeLabel: label('close')
        });

        var head = element('div', 'mpp-pick-head');
        var search = element('input', 'form-control');

        search.type = 'search';
        search.placeholder = label('search');
        search.setAttribute('aria-label', label('search'));

        var caption = element('div', 'small fg-secondary mt-5');
        var grid = element('div', 'mpp-pick-grid');

        built.body.appendChild(head);
        built.body.appendChild(search);
        built.body.appendChild(caption);
        built.body.appendChild(grid);

        document.body.appendChild(built.root);

        var timer = null;

        search.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                var q = search.value.trim();

                if (q === '') {
                    loadSimilar();
                } else {
                    load('search', { q: q }, label('results'));
                }
            }, 300);
        });

        search.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
            }
        });

        picker = { root: built.root, head: head, search: search, caption: caption, grid: grid, row: null, request: 0 };

        return picker;
    }

    function load(action, data, title) {
        var request = ++picker.request;

        picker.caption.textContent = label('loading');
        picker.grid.innerHTML = '';

        post(action, data).then(function (answer) {
            if (request !== picker.request) {
                return;
            }

            picker.caption.textContent = title;

            if (!answer.ok) {
                picker.caption.textContent = answer.error || label('failed');
                return;
            }

            if (!answer.products.length) {
                picker.caption.textContent = label('nothing');
                return;
            }

            answer.products.forEach(function (product) {
                picker.grid.appendChild(card(product));
            });
        });
    }

    function loadSimilar() {
        load('similar', { id: picker.row.getAttribute('data-mp-row') }, label('similar'));
    }

    function card(product) {
        var button = element('button', 'mpp-pick');

        button.type = 'button';

        button.appendChild(picture(product.image));
        button.appendChild(element('div', 'mpp-title', product.title || product.code));

        var meta = element('div', 'mpp-meta');

        meta.appendChild(element('code', '', product.code));
        meta.appendChild(element('span', '', product.price));
        meta.appendChild(element('span', '', product.stock));

        if (!product.enabled) {
            meta.appendChild(element('span', 'fg-warning', label('disabled')));
        }

        button.appendChild(meta);

        if (product.matched) {
            button.disabled = true;
            button.appendChild(element('div', 'small fg-secondary', label('matched_to', [product.matched])));
        }

        button.addEventListener('click', function () {
            var row = picker.row;

            button.disabled = true;

            rowStep(row, 'accept', { product_id: product.id }).then(function (answer) {
                button.disabled = false;

                if (answer.ok) {
                    pgUI.dialog.hide(picker.root);
                }
            });
        });

        return button;
    }

    function openPicker(row) {
        if (!window.pgUI) {
            return;
        }

        if (!picker) {
            buildPicker();
        }

        picker.row = row;
        picker.search.value = '';

        // What is being matched, at the top: their picture, title and code.
        picker.head.innerHTML = '';

        var image = picture(row.getAttribute('data-mp-image'));

        if (image.tagName === 'IMG') {
            image.referrerPolicy = 'no-referrer';
        }

        var text = element('div', 'mpp-text');

        text.appendChild(element('div', 'mpp-origin', config.provider));
        text.appendChild(element('div', 'mpp-title', row.getAttribute('data-mp-title')));
        text.appendChild(element('code', 'small', row.getAttribute('data-mp-code')));

        picker.head.appendChild(image);
        picker.head.appendChild(text);

        loadSimilar();

        pgUI.dialog.show(picker.root).then(function () {
            picker.search.focus();
        });
    }
})();
