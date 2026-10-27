/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor home (view_system_styles.php, pg_designer_start_screen()).
 *
 * A design card and a page chip answer the right mouse button with a menu of
 * what can be done to them. The same menu opens from the card's ⋯ button, from
 * a long press on a touch screen and from the menu key (or Shift+F10). A
 * focused card also takes Enter (open), F2 (rename) and Delete. From the menu
 * a design is opened, given a page, viewed on the site, put on or taken off
 * the site, renamed in place, turned into a template or deleted; a page is
 * opened, viewed or its address copied. The shared components panel and the
 * search over many designs live here too.
 *
 * The texts arrive translated in #sdh-config (pg_designer_home_texts()); this
 * file holds none of its own. api.php reads a JSON body.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var configElement = document.getElementById('sdh-config');
    var menu = document.getElementById('sdh-menu');

    if (!configElement || !menu) {
        return;
    }

    var C = {};

    try {
        C = JSON.parse(configElement.textContent || '{}') || {};
    } catch (e) {
        C = {};
    }

    var T = C.t || {};

    function say(key) {
        return (T[key] !== undefined) ? String(T[key]) : '';
    }

    function num(value) {
        var n = parseInt(value, 10);
        return isNaN(n) ? 0 : n;
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function api(body) {
        body.token = (typeof software_token !== 'undefined') ? software_token : '';

        return fetch('api.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        }).then(function (response) { return response.json(); });
    }

    function toast(message, variant) {
        if (!message) {
            return;
        }
        if (typeof window.pgToast === 'function') {
            window.pgToast({ message: message, variant: variant || 'info' });
        } else {
            window.alert(message);
        }
    }

    function ask(options) {
        if (typeof window.pgConfirm === 'function') {
            return window.pgConfirm(options);
        }
        return Promise.resolve(window.confirm(options.message));
    }

    // A message for after the page is drawn again (a delete that changes
    // which design is the large one).
    function toastAfterReload(message, variant) {
        try {
            window.sessionStorage.setItem('sdh-toast', JSON.stringify({ m: message, v: variant }));
        } catch (e) { }
    }

    try {
        var pending = window.sessionStorage.getItem('sdh-toast');
        if (pending) {
            window.sessionStorage.removeItem('sdh-toast');
            pending = JSON.parse(pending);
            if (pending && pending.m) {
                toast(pending.m, pending.v);
            }
        }
    } catch (e) { }

    function absoluteUrl(url) {
        try {
            return new URL(url, window.location.href).href;
        } catch (e) {
            return url;
        }
    }

    // The clipboard API needs a secure page; plain http falls back to a
    // selected text box.
    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text).then(function () { return true; }, function () { return false; });
        }
        return new Promise(function (resolve) {
            var box = document.createElement('textarea');
            box.value = text;
            box.setAttribute('readonly', '');
            box.style.position = 'fixed';
            box.style.opacity = '0';
            document.body.appendChild(box);
            box.select();
            var ok = false;
            try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
            box.remove();
            resolve(ok);
        });
    }

    function cardOf(element) {
        return (element && element.closest) ? element.closest('.sdh-card') : null;
    }

    function chipOf(element) {
        return (element && element.closest) ? element.closest('.sdh-page[data-page-id]') : null;
    }

    function stateOf(card) {
        var status = card.querySelector('.sdh-status');
        var data = status ? status.dataset : {};
        return { pages: num(data.pages), drafts: num(data.drafts), offable: num(data.offable), home: num(data.home) };
    }

    function hideTooltip(element) {
        if (window.pgUI && element) {
            var tip = window.pgUI.tooltip.find(element);
            if (tip) {
                tip.hide();
            }
        }
    }

    // ── The menu ────────────────────────────────────────────────────────

    var current = null;
    var keyboardMenuAt = 0;
    var lastLongPress = 0;

    function entry(act, icon, label, key) {
        return { act: act, icon: icon, label: label, key: key || '' };
    }

    var DIVIDER = { divider: true };

    function designEntries(card) {
        var state = stateOf(card);
        var data = card.dataset;
        var list = [entry('open', 'bi-pencil-square', say('edit'), 'Enter'), entry('newtab', 'bi-box-arrow-up-right', say('newTab'))];

        if (C.canChange) {
            list.push(entry('add', 'bi-file-earmark-plus', say('addPage')));
        }
        if (data.viewUrl) {
            list.push(entry('view', 'bi-eye', say('view')));
        }

        var change = [];

        if (C.canDraft && state.drafts > 0) {
            change.push(entry('on', 'bi-cloud-check', say('on')));
        }
        if (C.canDraft && state.offable > 0) {
            change.push(entry('off', 'bi-cloud-slash', say('off')));
        }
        if (C.canChange) {
            change.push(entry('rename', 'bi-input-cursor-text', say('rename'), 'F2'));
        }
        if (C.canTemplate && state.pages > 0) {
            change.push(entry('template', 'bi-grid-1x2', say('template')));
        }
        if (change.length) {
            list.push(DIVIDER);
            list = list.concat(change);
        }
        if (C.canChange) {
            list.push(DIVIDER);
            list.push(entry('delete', 'bi-trash', say('delete'), 'Delete'));
        }
        return list;
    }

    function pageEntries(chip) {
        var list = [];

        if (chip.dataset.locked !== '1') {
            list.push(entry('page-open', 'bi-pencil-square', say('editPage'), 'Enter'));
            list.push(entry('page-newtab', 'bi-box-arrow-up-right', say('newTab')));
        }
        list.push(entry('page-view', 'bi-eye', say('view')));
        list.push(entry('page-copy', 'bi-link-45deg', say('copy')));
        return list;
    }

    function drawMenu(title, list) {
        var html = '<div class="sdh-menu-head" aria-hidden="true">' + escapeHtml(title) + '</div>';

        list.forEach(function (item) {
            if (item.divider) {
                html += '<div class="menu-divider" role="separator"></div>';
                return;
            }
            html += '<button type="button" class="menu-item" role="menuitem" tabindex="-1" data-act="' + item.act + '">'
                + '<i class="bi ' + item.icon + '" aria-hidden="true"></i><span>' + escapeHtml(item.label) + '</span>'
                + (item.key ? '<span class="sdh-menu-key" aria-hidden="true">' + escapeHtml(item.key) + '</span>' : '')
                + '</button>';
        });

        menu.innerHTML = html;
        menu.setAttribute('aria-label', title);
    }

    // target: a card or a page chip. at: {x, y} for a pointer, or the
    // rectangle of the button it drops from. fromKeyboard puts the focus on
    // the first entry, as menus opened from the keyboard do.
    function openMenu(target, at, opener, fromKeyboard) {
        closeMenu(false);

        var isPage = target.classList.contains('sdh-page');
        var card = cardOf(target);

        if (!card) {
            return;
        }

        drawMenu(isPage ? (target.dataset.name || '') : (card.dataset.name || ''), isPage ? pageEntries(target) : designEntries(card));

        current = { el: target, card: card, page: isPage, opener: opener || null, at: Date.now() };
        target.classList.add('sdh-menu-for');

        if (opener) {
            opener.setAttribute('aria-expanded', 'true');
            hideTooltip(opener);
        }

        menu.style.left = '0px';
        menu.style.top = '0px';
        menu.classList.add('show');

        var width = menu.offsetWidth;
        var height = menu.offsetHeight;
        var x = at.right !== undefined ? at.right - width : at.x;
        var y = at.bottom !== undefined ? at.bottom + 4 : at.y;

        // Above the button or the pointer when it would run off the bottom.
        if (y + height > window.innerHeight - 8) {
            y = (at.top !== undefined ? at.top - 4 : y) - height;
        }

        menu.style.left = Math.max(8, Math.min(x, window.innerWidth - width - 8)) + 'px';
        menu.style.top = Math.max(8, Math.min(y, window.innerHeight - height - 8)) + 'px';

        var first = menu.querySelector('.menu-item');

        if (fromKeyboard && first) {
            first.focus({ preventScroll: true });
        } else {
            menu.focus({ preventScroll: true });
        }
    }

    function closeMenu(returnFocus) {
        if (!current) {
            return;
        }

        var back = current.opener || current.el;

        menu.classList.remove('show');
        current.el.classList.remove('sdh-menu-for');

        if (current.opener) {
            current.opener.setAttribute('aria-expanded', 'false');
        }

        current = null;

        if (returnFocus && back && back.focus) {
            back.focus({ preventScroll: true });
        }
    }

    menu.addEventListener('click', function (event) {
        var button = event.target.closest('.menu-item');

        if (!button || !current) {
            return;
        }

        var target = current;

        closeMenu(false);
        run(button.dataset.act, target);
    });

    // The menu has the keyboard while it is open. Taken on the window, ahead
    // of everything: Bootstrap's dropdown catches the arrow keys and Escape
    // inside any .menu on the document's capture phase, and fails
    // on one that no toggle of its own opened.
    window.addEventListener('keydown', function (event) {
        if (!current || !menu.contains(event.target)) {
            return;
        }

        event.stopPropagation();

        var items = Array.prototype.slice.call(menu.querySelectorAll('.menu-item'));
        var index = items.indexOf(document.activeElement);

        if (!items.length) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            items[(index < 0) ? 0 : (index + 1) % items.length].focus();
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            items[(index <= 0) ? items.length - 1 : index - 1].focus();
        } else if (event.key === 'Home') {
            event.preventDefault();
            items[0].focus();
        } else if (event.key === 'End') {
            event.preventDefault();
            items[items.length - 1].focus();
        } else if ((event.key === 'Escape') || (event.key === 'Tab')) {
            event.preventDefault();
            closeMenu(true);
        }
    }, true);

    // A press anywhere else closes it. The ⋯ button that opened it closes it
    // through its own click instead.
    document.addEventListener('mousedown', function (event) {
        if (!current || menu.contains(event.target)) {
            return;
        }
        if (current.opener && current.opener.contains(event.target)) {
            return;
        }
        closeMenu(false);
    }, true);

    // A page still gliding from the last turn of the wheel does not take the
    // menu away the moment it opens.
    document.addEventListener('scroll', function () {
        if (current && (Date.now() - current.at > 300)) {
            closeMenu(false);
        }
    }, true);
    window.addEventListener('resize', function () { closeMenu(false); });
    window.addEventListener('blur', function () { closeMenu(false); });

    document.addEventListener('contextmenu', function (event) {
        if (event.target.closest && event.target.closest('input, textarea, select, [contenteditable]')) {
            return;
        }

        var target = chipOf(event.target) || cardOf(event.target);

        // The browser's own menu everywhere else on the screen.
        if (!target) {
            return;
        }

        event.preventDefault();

        // Already opened by the key press or the long press that caused this.
        if ((Date.now() - keyboardMenuAt < 400) || (Date.now() - lastLongPress < 700)) {
            return;
        }

        cancelLongPress();

        if ((event.clientX === 0) && (event.clientY === 0)) {
            var rect = target.getBoundingClientRect();
            openMenu(target, { x: rect.left + 12, y: rect.top + Math.min(rect.height - 4, 36) }, null, true);
            return;
        }

        openMenu(target, { x: event.clientX, y: event.clientY }, null, false);
    });

    // Touch screens: a long press opens the same menu. iOS never fires
    // contextmenu; Android does, and the timestamps keep the two from
    // opening it twice.
    var pressTimer = null;
    var pressX = 0;
    var pressY = 0;

    function cancelLongPress() {
        if (pressTimer) {
            window.clearTimeout(pressTimer);
            pressTimer = null;
        }
    }

    document.addEventListener('touchstart', function (event) {
        if (event.touches.length !== 1) {
            return;
        }

        var target = chipOf(event.target) || cardOf(event.target);

        if (!target || (event.target.closest && event.target.closest('input, textarea'))) {
            return;
        }

        pressX = event.touches[0].clientX;
        pressY = event.touches[0].clientY;
        cancelLongPress();
        pressTimer = window.setTimeout(function () {
            pressTimer = null;
            lastLongPress = Date.now();
            openMenu(target, { x: pressX, y: pressY }, null, false);
        }, 550);
    }, { passive: true });

    document.addEventListener('touchmove', function (event) {
        if (!pressTimer) {
            return;
        }
        var dx = event.touches[0].clientX - pressX;
        var dy = event.touches[0].clientY - pressY;
        if ((dx * dx + dy * dy) > 100) {
            cancelLongPress();
        }
    }, { passive: true });

    ['touchend', 'touchcancel'].forEach(function (name) {
        document.addEventListener(name, function (event) {
            cancelLongPress();
            // The lift that ends a long press must not follow the link under it.
            if ((Date.now() - lastLongPress < 700) && event.cancelable) {
                event.preventDefault();
            }
        });
    });

    // ── Clicks on the cards ─────────────────────────────────────────────

    document.addEventListener('click', function (event) {
        var more = event.target.closest('.sdh-more');

        if (more) {
            event.preventDefault();
            if (current && (current.opener === more)) {
                closeMenu(true);
                return;
            }
            openMenu(cardOf(more), more.getBoundingClientRect(), more, event.detail === 0);
            return;
        }

        var action = event.target.closest('.sdh-card [data-act]');

        if (action) {
            event.preventDefault();
            run(action.dataset.act, { el: cardOf(action), card: cardOf(action), page: false, opener: action });
            return;
        }

        var toggle = event.target.closest('.sdh-pages-toggle');

        if (toggle) {
            var box = toggle.closest('.sdh-pages');
            var folded = box.classList.toggle('is-folded');
            toggle.textContent = folded ? toggle.dataset.more : toggle.dataset.less;
            toggle.setAttribute('aria-expanded', folded ? 'false' : 'true');
        }
    });

    // ── Keys on a card or a chip ────────────────────────────────────────

    document.addEventListener('keydown', function (event) {
        if (current || event.defaultPrevented) {
            return;
        }
        if (event.target.closest && event.target.closest('input, textarea, select, [contenteditable]')) {
            return;
        }
        if (document.querySelector('.dialog[open], .drawer[open]')) {
            return;
        }

        var chip = chipOf(event.target);
        var card = cardOf(event.target);

        if (!card) {
            return;
        }

        if ((event.key === 'ContextMenu') || ((event.key === 'F10') && event.shiftKey)) {
            event.preventDefault();
            keyboardMenuAt = Date.now();
            var target = chip || card;
            var rect = target.getBoundingClientRect();
            openMenu(target, { x: rect.left + 12, y: rect.top + Math.min(rect.height - 4, 36) }, null, true);
            return;
        }

        if (chip) {
            return;
        }

        if ((event.key === 'Enter') && (event.target === card)) {
            event.preventDefault();
            run('open', { el: card, card: card, page: false, opener: null });
        } else if ((event.key === 'F2') && C.canChange) {
            event.preventDefault();
            run('rename', { el: card, card: card, page: false, opener: null });
        } else if ((event.key === 'Delete') && C.canChange) {
            event.preventDefault();
            run('delete', { el: card, card: card, page: false, opener: card });
        }
    });

    // ── What the entries do ─────────────────────────────────────────────

    function openTab(url) {
        if (url) {
            window.open(url, '_blank', 'noopener');
        }
    }

    function run(act, target) {
        var card = target.card;
        var el = target.el;

        switch (act) {
            case 'open': window.location.href = card.dataset.editUrl; break;
            case 'newtab': openTab(card.dataset.editUrl); break;
            case 'add': window.location.href = card.dataset.addUrl; break;
            case 'view': openTab(card.dataset.viewUrl); break;
            case 'on': setDraft(card, false); break;
            case 'off': setDraft(card, true); break;
            case 'rename': beginRename(card); break;
            case 'template': makeTemplate(card); break;
            case 'delete': deleteDesign(card); break;
            case 'page-open': window.location.href = el.getAttribute('href'); break;
            case 'page-newtab': openTab(el.getAttribute('href')); break;
            case 'page-view': openTab(el.dataset.viewUrl); break;
            case 'page-copy':
                copyText(absoluteUrl(el.dataset.viewUrl)).then(function (ok) {
                    toast(ok ? say('copied') : say('copyFailed'), ok ? 'success' : 'danger');
                });
                break;
        }
    }

    // A chip drawn again from its data, as pg_designer_home_page_chips()
    // draws it: an icon for the home page, a locked page and a page with a
    // scheduled time only, the dashed outline of a draft, the words a screen
    // reader hears. data-scheduled holds the time in words, '' for none.
    function paintChip(chip) {
        var data = chip.dataset;
        var sched = (data.scheduled || '') !== '' && data.home !== '1';
        var state = (data.locked === '1') ? ['bi-lock', say('locked')]
            : (data.home === '1') ? ['bi-house-door', say('homePage')]
            : sched ? ['bi-clock', (data.draft === '1' ? say('draft') + ', ' : '') + data.scheduled]
            : (data.draft === '1') ? ['', say('draft')]
            : ['', ''];
        var icon = chip.querySelector('.bi');
        var hidden = chip.querySelector('.sdh-page-state');

        chip.classList.toggle('is-draft', data.draft === '1');
        chip.classList.toggle('is-scheduled', sched);
        if (sched) {
            chip.title = data.scheduled;
        } else {
            chip.removeAttribute('title');
        }
        if (icon) {
            icon.className = 'bi ' + (state[0] || 'd-none');
        }
        if (hidden) {
            hidden.textContent = state[1] ? ' (' + state[1] + ')' : '';
        }
    }

    // "Put on the site" shows while the design has drafts. Taking pages off
    // is only in the menu.
    function syncButtons(card) {
        var on = card.querySelector('.sdh-actions [data-act="on"]');

        if (on) {
            on.classList.toggle('d-none', stateOf(card).drafts <= 0);
        }
    }

    // Every page of the design off the site, or every draft back on it
    // (api.php designer/design_publish). The home page stays on the site.
    function setDraft(card, off) {
        var state = stateOf(card);
        var count = off ? state.offable : state.drafts;

        if (count <= 0) {
            return;
        }

        var message = off
            ? say('offAsk').replace('{var}', count) + (state.home > 0 ? ' ' + say('homeStays') : '')
            : say('onAsk').replace('{var}', count);

        ask({
            title: card.dataset.name || (off ? say('off') : say('on')),
            message: message,
            confirmText: off ? say('offYes') : say('on'),
            cancelText: say('no'),
            variant: off ? 'warning' : 'success'
        }).then(function (ok) {
            if (!ok) {
                return;
            }

            var buttons = card.querySelectorAll('.sdh-actions [data-act]');
            var lock = function (on) { Array.prototype.forEach.call(buttons, function (b) { b.disabled = on; }); };

            lock(true);
            api({ action: 'designer', sub_action: 'design_publish', style_id: num(card.dataset.styleId), draft: off ? 1 : 0 })
                .then(function (res) {
                    lock(false);
                    if (!res || res.status !== 'success') {
                        toast((res && res.message) || say('fail'), 'danger');
                        return;
                    }

                    var old = card.querySelector('.sdh-status');
                    if (old && res.cell) {
                        var holder = document.createElement('div');
                        holder.innerHTML = res.cell;
                        if (holder.firstElementChild) {
                            old.replaceWith(holder.firstElementChild);
                        }
                    }

                    var scheduled = (res.scheduled && typeof res.scheduled === 'object') ? res.scheduled : {};
                    card.querySelectorAll('.sdh-page[data-page-id]').forEach(function (chip) {
                        if (chip.dataset.home !== '1') {
                            chip.dataset.draft = off ? '1' : '0';
                            chip.dataset.scheduled = scheduled[chip.dataset.pageId] || '';
                            paintChip(chip);
                        }
                    });

                    syncButtons(card);
                    toast(res.message, 'success');
                })
                .catch(function () {
                    lock(false);
                    toast(say('network'), 'danger');
                });
        });
    }

    // The name becomes a text box where it stands. Enter or leaving the box
    // saves, Escape puts the name back.
    function beginRename(card) {
        if (!C.canChange) {
            return;
        }

        var title = card.querySelector('.sdh-name');

        if (!title || card.querySelector('.sdh-rename')) {
            return;
        }

        var oldName = card.dataset.name || '';
        var input = document.createElement('input');
        var busy = false;
        var closed = false;

        input.type = 'text';
        input.className = 'form-control form-control-sm sdh-rename';
        input.value = oldName;
        input.maxLength = 100;
        input.autocomplete = 'off';
        input.setAttribute('aria-label', say('rename'));

        title.classList.add('d-none');
        title.insertAdjacentElement('afterend', input);
        input.focus();
        input.select();

        function finish() {
            if (closed) {
                return;
            }
            closed = true;
            input.remove();
            title.classList.remove('d-none');
            var link = title.querySelector('a');
            (link || card).focus({ preventScroll: true });
        }

        function save() {
            if (busy || closed) {
                return;
            }

            var value = input.value.replace(/\s+/g, ' ').trim();

            if ((value === '') || (value === oldName)) {
                finish();
                return;
            }

            busy = true;
            input.readOnly = true;

            api({ action: 'designer', sub_action: 'design_rename', style_id: num(card.dataset.styleId), name: value })
                .then(function (res) {
                    busy = false;
                    input.readOnly = false;

                    if (!res || res.status !== 'success') {
                        toast((res && res.message) || say('fail'), 'danger');
                        input.focus();
                        input.select();
                        return;
                    }

                    card.dataset.name = res.name;
                    card.setAttribute('aria-label', res.name);
                    card.querySelectorAll('.sdh-name-text').forEach(function (node) { node.textContent = res.name; });

                    var column = card.closest('.sdh-tile-col');
                    if (column) {
                        column.dataset.name = res.name;
                    }

                    finish();
                    toast(res.message, 'success');
                })
                .catch(function () {
                    busy = false;
                    input.readOnly = false;
                    toast(say('network'), 'danger');
                    input.focus();
                });
        }

        input.addEventListener('keydown', function (event) {
            event.stopPropagation();
            if (event.key === 'Enter') {
                event.preventDefault();
                save();
            } else if (event.key === 'Escape') {
                event.preventDefault();
                finish();
            }
        });

        input.addEventListener('blur', function () {
            if (!busy) {
                save();
            }
        });
    }

    // Turn a design into a template (api.php designer/template_from_design).
    // The gallery is printed with the page, so once a template is made the
    // Choose a Template card draws the page again to list it.
    var templateDialog = document.getElementById('sdhTemplateFromModal');
    var templateFor = null;

    function makeTemplate(card) {
        if (!templateDialog || !window.pgUI) {
            return;
        }
        templateFor = card;
        document.getElementById('sdh-tpl-name').value = card.dataset.name || '';
        document.getElementById('sdh-tpl-description').value = '';
        pgUI.dialog.show(templateDialog);
    }

    if (templateDialog) {
        pgUI.dialog.on(templateDialog, 'shown', function () {
            var field = document.getElementById('sdh-tpl-name');
            field.focus();
            field.select();
        });

        document.getElementById('sdh-tpl-run').addEventListener('click', function () {
            var button = this;

            if (!templateFor) {
                return;
            }

            button.disabled = true;
            api({
                action: 'designer',
                sub_action: 'template_from_design',
                style_id: num(templateFor.dataset.styleId),
                name: document.getElementById('sdh-tpl-name').value,
                description: document.getElementById('sdh-tpl-description').value
            }).then(function (res) {
                button.disabled = false;
                if (!res || res.status !== 'success') {
                    toast((res && res.message) || say('fail'), 'danger');
                    return;
                }
                pgUI.dialog.hide(templateDialog);
                C.templatesStale = true;
                toast(res.message, 'success');
            }).catch(function () {
                button.disabled = false;
                toast(say('network'), 'danger');
            });
        });
    }

    // Ahead of Bootstrap's own listener, so the gallery as it was printed
    // does not open once it is out of date.
    document.addEventListener('click', function (event) {
        if (C.templatesStale && C.templateUrl && event.target.closest && event.target.closest('[data-bs-target="#sdTemplateModal"]')) {
            event.preventDefault();
            event.stopPropagation();
            window.location.href = C.templateUrl;
        }
    }, true);

    function countDesigns() {
        var count = document.querySelectorAll('.sdh-card').length;
        var badge = document.querySelector('#sdh-designs-title .sdh-count');
        if (badge) {
            badge.textContent = String(count);
        }
    }

    // Delete a design: its pages go to the recycle bin, and the design with
    // them once the bin holds styles (api.php designer/design_delete).
    function deleteDesign(card) {
        if (!C.canChange) {
            return;
        }

        var name = card.dataset.name || '';
        var pages = num(card.dataset.pages);
        var folders = num(card.dataset.folders);
        // Asked from the card itself (its Delete key): the focus moves on to
        // a neighbour once it is gone.
        var hadFocus = card.contains(document.activeElement);
        var message = C.styleBin
            ? (pages > 0 ? say('delBinPages').replace('{n}', pages) : say('delBin')).replace('{name}', name)
            : say('delPlain').replace('{name}', name) + ' ' + (pages > 0 ? say('delPlainPages').replace('{n}', pages) : say('delPlainNone'));

        if ((pages > 0) && (card.dataset.home === '1')) {
            message += ' ' + say('delHome');
        }
        if (folders > 0) {
            message += ' ' + say('delFolders').replace('{var:1}', folders);
        }

        ask({ title: say('deleteTitle'), message: message, confirmText: say('deleteYes'), cancelText: say('no'), variant: 'danger' })
            .then(function (ok) {
                if (!ok) {
                    return;
                }

                api({ action: 'designer', sub_action: 'design_delete', style_id: num(card.dataset.styleId) })
                    .then(function (res) {
                        if (!res || res.status !== 'success') {
                            toast((res && res.message) || say('fail'), 'danger');
                            return;
                        }

                        // Which design is shown large, and whether the list
                        // is empty, are the server's to work out again.
                        var left = document.querySelectorAll('.sdh-card').length - 1;

                        if (card.classList.contains('sdh-site') || (left <= 1)) {
                            toastAfterReload(res.message, 'success');
                            window.location.reload();
                            return;
                        }

                        // The keyboard stays on the list: the next design (or
                        // the one before it) takes the focus the deleted one had.
                        var column = card.closest('.sdh-tile-col') || card;
                        var near = column.nextElementSibling || column.previousElementSibling;
                        var nextCard = near ? (near.matches('.sdh-card') ? near : near.querySelector('.sdh-card')) : null;

                        column.remove();
                        countDesigns();
                        if (hadFocus && nextCard) {
                            nextCard.focus({ preventScroll: true });
                        }
                        toast(res.message, 'success');
                    })
                    .catch(function () {
                        toast(say('network'), 'danger');
                    });
            });
    }

    // ── Finding a design by name ────────────────────────────────────────

    // Case and accents aside, so "kafe" finds "Kafé" and "i" finds "İ".
    function fold(text) {
        return String(text || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/ı/g, 'i');
    }

    var filter = document.getElementById('sdh-filter');

    if (filter) {
        filter.addEventListener('input', function () {
            var needle = fold(filter.value.trim());
            var shown = 0;

            document.querySelectorAll('.sdh-tile-col').forEach(function (column) {
                var hit = (needle === '') || (fold(column.dataset.name).indexOf(needle) !== -1);
                column.classList.toggle('d-none', !hit);
                if (hit) {
                    shown++;
                }
            });

            var none = document.getElementById('sdh-filter-empty');
            if (none) {
                none.classList.toggle('d-none', (needle === '') || (shown > 0));
            }
        });
    }

    // ── Shared components and system widgets (pg_designer_components_card()) ──

    // Tick rows in the offcanvas panel, delete them in one go. Rows still
    // placed on a page are deleted too (force) once the operator has seen
    // where.
    (function () {
        var panel = document.getElementById('sd-comp-offcanvas');

        if (!panel) {
            return;
        }

        var all = document.getElementById('sd-comp-all');
        var remove = document.getElementById('sd-comp-delete');
        var counter = document.getElementById('sd-comp-count');
        var opener = document.getElementById('sd-comp-open');
        var total = document.getElementById('sd-comp-total');

        // Nothing left to list: close the panel, then drop it and its button
        // once the slide-out has finished.
        function retire() {
            if (opener) {
                opener.remove();
            }
            if (window.pgUI && pgUI.drawer.find(panel) && pgUI.drawer.isOpen(panel)) {
                pgUI.drawer.on(panel, 'hidden', function () { pgUI.drawer.dispose(panel); panel.remove(); }, { once: true });
                pgUI.drawer.hide(panel);
            } else {
                panel.remove();
            }
        }

        function boxes() {
            return Array.prototype.slice.call(panel.querySelectorAll('.sd-comp-pick'));
        }

        function sync() {
            var list = boxes();
            var ticked = list.filter(function (box) { return box.checked; }).length;
            counter.textContent = String(ticked);
            remove.disabled = (ticked === 0);
            all.checked = (list.length > 0) && (ticked === list.length);
            all.indeterminate = (ticked > 0) && (ticked < list.length);
        }

        all.addEventListener('change', function () {
            boxes().forEach(function (box) { box.checked = all.checked; });
            sync();
        });

        panel.addEventListener('change', function (event) {
            if (event.target.classList.contains('sd-comp-pick')) {
                sync();
            }
        });

        remove.addEventListener('click', function () {
            var rows = boxes().filter(function (box) { return box.checked; }).map(function (box) { return box.closest('tr'); });

            if (!rows.length) {
                return;
            }

            // The rows still in use lead the list, where they are read.
            rows.sort(function (a, b) { return (b.dataset.used === '1' ? 1 : 0) - (a.dataset.used === '1' ? 1 : 0); });

            var used = 0;
            var lines = [];

            rows.forEach(function (row) {
                var where = [];
                try { where = JSON.parse(row.dataset.usage || '[]'); } catch (e) { where = []; }
                if (where.length) {
                    used++;
                }
                lines.push('• ' + (row.dataset.name || '') + (where.length ? ' — ' + say('usedOn') + where.join(', ') : ''));
            });

            var message = say('compAsk').replace('{var}', rows.length) + '\n' + lines.join('\n') + '\n\n'
                + (used ? say('compUsed').replace('{var}', used) + ' ' : '')
                + say('undo');
            var ids = rows.map(function (row) { return num(row.dataset.id); });

            ask({ title: say('compTitle'), message: message, confirmText: say('compYes').replace('{var}', rows.length), cancelText: say('no'), variant: 'danger' })
                .then(function (ok) {
                    if (!ok) {
                        return;
                    }

                    remove.disabled = true;
                    api({ action: 'shared_component', sub_action: 'delete_many', ids: ids, force: used > 0 })
                        .then(function (res) {
                            if (!res || res.status !== 'success') {
                                sync();
                                toast((res && res.message) || say('fail'), 'danger');
                                return;
                            }

                            var gone = (res.deleted || []).map(function (id) { return num(id); });

                            rows.forEach(function (row) {
                                if (gone.indexOf(num(row.dataset.id)) !== -1) {
                                    row.remove();
                                }
                            });

                            if (total) {
                                total.textContent = String(boxes().length);
                            }

                            if (boxes().length) {
                                sync();
                            } else {
                                retire();
                            }

                            toast(say('compDone').replace('{var}', gone.length), 'success');
                            if (res.kept && res.kept.length) {
                                toast(say('compKept').replace('{var}', res.kept.length), 'warning');
                            }
                        })
                        .catch(function () {
                            sync();
                            toast(say('network'), 'danger');
                        });
                });
        });
    })();
})();
