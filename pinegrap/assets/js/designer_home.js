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
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
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

    function entry(act, label, key) {
        return { act: act, label: label, key: key || '' };
    }

    // The rows in their groups, a divider between two groups; a row that
    // does not apply is left out, and so is a group left empty.
    function grouped(groups) {
        var list = [];

        groups.forEach(function (group) {
            if (!group.length) {
                return;
            }
            if (list.length) {
                list.push({ divider: true });
            }
            list = list.concat(group);
        });
        return list;
    }

    // Work on it; the site; the design itself; Delete alone.
    function designEntries(card) {
        var state = stateOf(card);
        var data = card.dataset;
        var work = [entry('open', say('edit'), 'Enter'), entry('newtab', say('newTab'))];
        var site = [];
        var self = [];

        if (C.canChange) {
            work.push(entry('add', say('addPage')));
        }
        if (data.viewUrl) {
            site.push(entry('view', say('view')));
        }
        if (C.canDraft && state.drafts > 0) {
            site.push(entry('on', say('on')));
        }
        if (C.canDraft && state.offable > 0) {
            site.push(entry('off', say('off')));
        }
        if (C.canChange) {
            self.push(entry('rename', say('rename'), 'F2'));
        }
        if (C.canTemplate && state.pages > 0) {
            self.push(entry('template', say('template')));
        }
        return grouped([work, site, self, C.canChange ? [entry('delete', say('delete'), 'Delete')] : []]);
    }

    // The page in the editor; the page on the site.
    function pageEntries(chip) {
        var editor = [];

        if (chip.dataset.locked !== '1') {
            editor.push(entry('page-open', say('editPage'), 'Enter'));
            editor.push(entry('page-newtab', say('newTab')));
        }
        return grouped([editor, [entry('page-view', say('view')), entry('page-copy', say('copy'))]]);
    }

    function drawMenu(title, list) {
        var html = '<div class="sdh-menu-head" aria-hidden="true">' + escapeHtml(title) + '</div>';

        list.forEach(function (item) {
            if (item.divider) {
                html += '<div class="menu-divider" role="separator"></div>';
                return;
            }
            html += '<button type="button" class="menu-item' + (item.act === 'delete' ? ' sdh-menu-danger' : '') + '" role="menuitem" tabindex="-1" data-act="' + item.act + '">'
                + '<span>' + escapeHtml(item.label) + '</span>'
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
            if (folded) {
                fitChips(box);
            }
        }
    });

    // ── One row of page chips ───────────────────────────────────────────

    // A folded row shows as many chips as fit it, then "+n more"
    // (pg_designer_home_page_chips() prints a first guess).
    function fitChips(box) {
        var toggle = box.querySelector('.sdh-pages-toggle');

        if (!toggle || !box.classList.contains('is-folded') || !box.offsetParent) {
            return;
        }

        var chips = Array.prototype.slice.call(box.querySelectorAll('.sdh-page:not(.sdh-pages-toggle)'));
        var show = function (count) {
            chips.forEach(function (chip, i) {
                chip.classList.toggle('is-extra', i >= count);
            });
            toggle.dataset.more = say('pagesMore').replace('{var}', String(chips.length - count));
            toggle.textContent = toggle.dataset.more;
            toggle.hidden = (count >= chips.length);
        };

        toggle.hidden = true;
        chips.forEach(function (chip) {
            chip.classList.remove('is-extra');
        });

        var top = chips.length ? chips[0].offsetTop : 0;
        var count = chips.length;

        for (var i = 1; i < chips.length; i++) {
            if (chips[i].offsetTop > top) {
                count = i;
                break;
            }
        }

        show(count);
        while (count > 1 && !toggle.hidden && toggle.offsetTop > top) {
            count--;
            show(count);
        }
    }

    function fitAllChips() {
        document.querySelectorAll('.sdh-pages.is-row').forEach(fitChips);
    }

    var fitTimer = 0;

    window.addEventListener('resize', function () {
        window.clearTimeout(fitTimer);
        fitTimer = window.setTimeout(fitAllChips, 120);
    });
    fitAllChips();
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(fitAllChips);
    }

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
        if (C.templatesStale && C.templateUrl && event.target.closest && event.target.closest('[data-sd-start="template"]')) {
            event.preventDefault();
            event.stopPropagation();
            window.location.href = C.templateUrl;
        }
    }, true);

    // The New design window (pg_designer_start_window()): the starts are a
    // vertical tab list in three groups, each showing its own pane; the
    // foot's one button takes the chosen start's words and the address of
    // its framework choice, or of the chosen template card (grey until one
    // is chosen). A start card or ?start=<key> names the start it opens on.
    (function () {
        var win = document.getElementById('sdStartModal');

        if (!win || !window.pgUI) {
            return;
        }

        var tabs = Array.prototype.slice.call(win.querySelectorAll('.sd-ns-item'));
        var go = document.getElementById('sd-ns-go');

        function open(tab) {
            return tab.getAttribute('aria-disabled') !== 'true';
        }

        function paneOf(tab) {
            return document.getElementById(tab.getAttribute('aria-controls'));
        }

        function syncGo() {
            var pane = win.querySelector('.sd-ns-pane:not([hidden])');

            if (!pane || !go) {
                return;
            }

            var label = pane.getAttribute('data-go') || '';
            var pick = pane.querySelector('input[type="radio"][data-href]:checked');
            var part = pane.getAttribute('data-part') || '';
            var partFoot = win.querySelector('.sd-ns-part-foot');
            var count = win.querySelector('.sd-ns-count');

            // A start with a part of its own (the Figma start) draws its own
            // buttons and count in the foot; the part is loaded the first
            // time the start is chosen.
            if (partFoot) {
                partFoot.hidden = !part;
                partFoot.setAttribute('data-part', part);
            }
            if (count && !part) {
                count.hidden = true;
            }
            if (part) {
                loadPart(part, pane);
            }

            // The template start: the chosen card, none until a press.
            var card = pane.querySelector('.sd-tpl-card.is-chosen');
            var href = pick ? pick.getAttribute('data-href') : (card ? card.getAttribute('data-use') : '');
            var gallery = !!pane.querySelector('.sd-tpl-card');

            go.classList.toggle('d-none', !label || (!pick && !gallery));

            if (label) {
                go.textContent = label;
            }
            if (href) {
                go.href = href;
                go.removeAttribute('aria-disabled');
                go.classList.remove('disabled');
                go.removeAttribute('tabindex');
            } else {
                go.removeAttribute('href');
                go.setAttribute('aria-disabled', 'true');
                go.classList.add('disabled');
                go.tabIndex = -1;
            }
        }

        // A template card: a press chooses it, a double press or Enter uses
        // it at once; its links and buttons (Preview, details, delete) keep
        // their own work.
        function chooseCard(card) {
            win.querySelectorAll('.sd-tpl-card.is-chosen').forEach(function (other) {
                other.classList.remove('is-chosen');
                other.removeAttribute('aria-current');
            });
            card.classList.add('is-chosen');
            card.setAttribute('aria-current', 'true');
            syncGo();
        }

        function cardOf(event) {
            var card = event.target.closest ? event.target.closest('.sd-tpl-card') : null;

            if (!card || !win.contains(card) || event.target.closest('a, button, input, label')) {
                return null;
            }
            return card;
        }

        win.addEventListener('click', function (event) {
            var card = cardOf(event);

            if (card) {
                chooseCard(card);
                return;
            }

            // A press anywhere on a framework card chooses it.
            var fw = event.target.closest ? event.target.closest('.sd-ns-fw') : null;
            var radio = fw ? fw.querySelector('input[type="radio"]') : null;

            if (radio && !radio.checked && !event.target.closest('input, label')) {
                radio.checked = true;
                radio.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        win.addEventListener('dblclick', function (event) {
            var card = cardOf(event);

            if (card) {
                chooseCard(card);
                window.location.href = card.getAttribute('data-use');
            }
        });

        win.addEventListener('keydown', function (event) {
            var card = (event.target.classList && event.target.classList.contains('sd-tpl-card')) ? event.target : null;

            if (!card) {
                return;
            }
            if (event.key === 'Enter') {
                event.preventDefault();
                chooseCard(card);
                window.location.href = card.getAttribute('data-use');
            } else if (event.key === ' ') {
                event.preventDefault();
                chooseCard(card);
            }
        });

        var parts = {};

        function loadPart(part, pane) {
            var ready = function () {
                if (window.sdStartParts && window.sdStartParts[part]) {
                    window.sdStartParts[part].show(pane, win);
                }
            };

            if (parts[part]) {
                if (parts[part] === 'loaded') {
                    ready();
                }
                return;
            }

            parts[part] = 'loading';

            var script = document.createElement('script');
            var stamp = pane.getAttribute('data-part-stamp') || C.partStamp || '';
            script.src = (C.partBase || 'assets/js/') + 'designer_' + part + '.js' + (stamp ? '?v=' + stamp : '');
            script.onload = function () {
                parts[part] = 'loaded';
                ready();
            };
            script.onerror = function () {
                parts[part] = '';
                toast(say('network'), 'danger');
            };
            document.head.appendChild(script);
        }

        function choose(key, focus) {
            var tab = null;

            tabs.forEach(function (t) {
                if (t.getAttribute('data-start') === key && open(t)) {
                    tab = t;
                }
            });

            tab = tab || tabs[0];

            if (!tab) {
                return;
            }

            leaveQuestions();

            if (tab.getAttribute('data-start') === 'template' && C.templatesStale && C.templateUrl) {
                window.location.href = C.templateUrl;
                return;
            }

            tabs.forEach(function (t) {
                var on = (t === tab);
                t.setAttribute('aria-selected', on ? 'true' : 'false');
                t.tabIndex = on ? 0 : -1;
                t.classList.toggle('is-active', on);
                paneOf(t).hidden = !on;
            });

            if (focus) {
                tab.focus();
            }

            syncGo();
        }

        // On a phone the list stands over the pane: the chosen start's pane
        // is brought into view, or a tap would seem to do nothing.
        function showPane(tab) {
            var list = win.querySelector('.sd-ns-list');
            var pane = paneOf(tab);

            if (pane && !pane.hidden && list && pane.getBoundingClientRect().top >= list.getBoundingClientRect().bottom) {
                pane.scrollIntoView({ block: 'start', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
            }
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                if (open(tab)) {
                    choose(tab.getAttribute('data-start'), false);
                    showPane(tab);
                }
            });
        });

        // Up and down walk the open starts, Home and End go to the ends.
        win.querySelector('.sd-ns-list').addEventListener('keydown', function (event) {
            var usable = tabs.filter(open);
            var at = usable.indexOf(document.activeElement);
            var next = null;

            if (event.key === 'ArrowDown') {
                next = usable[(at + 1) % usable.length];
            } else if (event.key === 'ArrowUp') {
                next = usable[(at - 1 + usable.length) % usable.length];
            } else if (event.key === 'Home') {
                next = usable[0];
            } else if (event.key === 'End') {
                next = usable[usable.length - 1];
            }

            if (next) {
                event.preventDefault();
                choose(next.getAttribute('data-start'), true);
            }
        });

        win.addEventListener('change', syncGo);

        // The design variables' start questions: after the start's own form
        // of a design built with Bootstrap 5, the pane turns into five
        // questions (assets/js/designer_questions.js and its data, both
        // fetched the first time). Their answers ride on the address the
        // filled button leads to; skipping all five leads where it did.
        var asking = null;
        var questionsReady = null;

        function questionsLoad() {
            if (questionsReady) {
                return questionsReady;
            }

            var script = new Promise(function (resolve, reject) {
                if (window.pgStartQuestions) {
                    resolve();
                    return;
                }
                var el = document.createElement('script');
                el.src = (C.partBase || 'assets/js/') + 'designer_questions.js' + (C.questionsStamp ? '?v=' + C.questionsStamp : '');
                el.onload = resolve;
                el.onerror = function () {
                    el.remove();
                    reject();
                };
                document.head.appendChild(el);
            });
            var data = api({ action: 'designer', sub_action: 'variables_state', questions: 1 }).then(function (res) {
                if (!res || res.status !== 'success' || !res.questions) {
                    throw new Error('questions');
                }
                return res.questions;
            });

            questionsReady = Promise.all([script, data]).then(function (both) {
                return both[1];
            }).catch(function () {
                questionsReady = null;
                return null;
            });

            return questionsReady;
        }

        function leaveQuestions() {
            if (!asking) {
                return;
            }

            asking.mounted.off();
            asking.box.remove();
            asking.foot.remove();
            Array.prototype.forEach.call(asking.pane.children, function (child) {
                child.hidden = child.hasAttribute('data-q-hidden') ? child.hidden : false;
                child.removeAttribute('data-q-hidden');
            });
            asking.hide.forEach(function (el) {
                el.classList.remove('d-none');
            });
            asking = null;
            syncGo();
        }

        // start: answers the questions open with (the Canva start's two
        // colours); before: run just before the window leaves for the
        // design.
        function askQuestions(pane, href, start, before) {
            var leave = function (url) {
                if (before) {
                    before();
                }
                window.location.href = url;
            };

            questionsLoad().then(function (q) {
                if (!q) {
                    // Without the questions the design opens as before.
                    leave(href);
                    return;
                }

                if (asking || pane.hidden) {
                    return;
                }

                Array.prototype.forEach.call(pane.children, function (child) {
                    if (child.hidden) {
                        child.setAttribute('data-q-hidden', '');
                    }
                    child.hidden = true;
                });

                var box = document.createElement('div');
                var foot = document.createElement('span');
                var hide = Array.prototype.slice.call(win.querySelectorAll('.dialog-footer > [data-bs-dismiss="dialog"], #sd-ns-go, .sd-ns-part-foot, .sd-ns-count'));

                foot.className = 'd-contents';
                pane.appendChild(box);
                go.parentNode.insertBefore(foot, go);
                hide.forEach(function (el) {
                    el.classList.add('d-none');
                });

                asking = { pane: pane, box: box, foot: foot, hide: hide, mounted: null };
                asking.mounted = window.pgStartQuestions.mount({
                    el: box,
                    foot: foot,
                    q: q,
                    start: start || null,
                    mode: 'window',
                    btn: { fill: 'pg-button pg-button-fill', quiet: 'pg-button pg-button-quiet', ghost: 'pg-button pg-button-ghost' },
                    back: leaveQuestions,
                    cancel: leaveQuestions,
                    done: function (answers) {
                        var any = Object.keys(answers).some(function (k) { return !!answers[k]; });
                        leave(href + (any ? '&answers=' + encodeURIComponent(JSON.stringify(answers)) : ''));
                    }
                });
            });
        }

        // A start with its own part (the document and Canva starts) asks the
        // questions before the address its own button leads to.
        window.sdStartAsk = askQuestions;

        if (go) {
            go.addEventListener('click', function (event) {
                if (go.getAttribute('aria-disabled') === 'true') {
                    event.preventDefault();
                    return;
                }

                var pane = win.querySelector('.sd-ns-pane:not([hidden])');
                var pick = pane ? pane.querySelector('input[type="radio"][data-href]:checked') : null;

                if (!pick || pick.value !== 'bootstrap5' || asking || event.ctrlKey || event.metaKey || event.shiftKey) {
                    return;
                }

                event.preventDefault();
                askQuestions(pane, go.href);
            });
        }

        pgUI.dialog.on(win, 'hidden', leaveQuestions);

        pgUI.dialog.on(win, 'show', function (event) {
            var source = event.relatedTarget;
            choose((source && source.dataset && source.dataset.sdStart) || win.dataset.sdStart || 'blank', false);
        });

        // The keyboard starts on the chosen start, where Up and Down work,
        // not on the window's Close button.
        pgUI.dialog.on(win, 'shown', function () {
            var at = win.querySelector('.sd-ns-item[aria-selected="true"]');

            if (at && win.contains(document.activeElement) && !document.activeElement.closest('.sd-ns-panes')) {
                at.focus();
            }
        });

        pgUI.dialog.on(win, 'hidden', function () {
            delete win.dataset.sdStart;
        });

        var asked = new URLSearchParams(window.location.search).get('start');

        if (asked && tabs.some(function (t) { return t.getAttribute('data-start') === asked; })) {
            var show = function () {
                win.dataset.sdStart = asked;
                pgUI.dialog.show(win);
            };

            if (document.readyState === 'complete') {
                show();
            } else {
                window.addEventListener('load', show);
            }
        }
    })();

    function countDesigns() {
        var count = document.querySelectorAll('.sdh-card').length;
        var badge = document.querySelector('#sdh-designs-title .sdh-count');
        var others = document.getElementById('sdh-others-count');
        if (badge) {
            badge.textContent = String(count);
        }
        if (others) {
            others.textContent = String(document.querySelectorAll('.sdh-tile').length);
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
            var words = document.getElementById('sdh-filter-empty-text');
            if (none) {
                none.classList.toggle('d-none', (needle === '') || (shown > 0));
            }
            if (words) {
                words.textContent = say('noMatch').replace('{var}', filter.value.trim());
            }
        });

        var clear = document.getElementById('sdh-filter-clear');

        if (clear) {
            clear.addEventListener('click', function () {
                filter.value = '';
                filter.dispatchEvent(new Event('input'));
                filter.focus();
            });
        }
    }

    // ── Shared components and system widgets (pg_designer_components_card()) ──

    // The panel arrives empty: its rows (shared_component/home_rows) are
    // asked when it first opens, so a closed panel costs the screen nothing.
    // Tick rows in the panel, delete them in one go, however many: "select
    // all" ticks every row the panel lists (it has no filter and draws every
    // row) and the server takes any number in one request.
    // Rows still placed on a page are deleted too (force) once the operator
    // has seen where.
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

        var body = document.getElementById('sd-comp-rows');

        // Rows the confirmation names one by one.
        var LISTED = 20;

        // 'idle' until the first open, 'loading' while asked, 'done' once
        // the rows are in; a failed load goes back to 'idle' so the next
        // open asks again.
        var state = 'idle';

        function notice(text) {
            body.innerHTML = '<tr class="sd-comp-wait"><td colspan="6" class="text-center fg-secondary py-5" role="status">' + escapeHtml(text) + '</td></tr>';
        }

        function load() {
            if (state !== 'idle') {
                return;
            }
            state = 'loading';
            api({ action: 'shared_component', sub_action: 'home_rows' })
                .then(function (res) {
                    if (!res || res.status !== 'success') {
                        state = 'idle';
                        notice((res && res.message) || say('fail'));
                        return;
                    }
                    state = 'done';
                    body.innerHTML = res.html || '';
                    all.disabled = false;
                    if (total) {
                        total.textContent = String(boxes().length);
                    }
                    if (boxes().length) {
                        sync();
                    } else {
                        retire();
                    }
                })
                .catch(function () {
                    state = 'idle';
                    notice(say('network'));
                });
        }

        if (opener) {
            opener.addEventListener('click', load);
        }

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

            // Every row is counted; the confirmation names the first ones
            // (the used ones lead) and the number of the rest, so a few
            // thousand ticked rows still give a dialog that can be read.
            rows.forEach(function (row) {
                var where = [];
                try { where = JSON.parse(row.dataset.usage || '[]'); } catch (e) { where = []; }
                if (where.length) {
                    used++;
                }
                if (lines.length < LISTED) {
                    lines.push('• ' + (row.dataset.name || '') + (where.length ? ' — ' + say('usedOn') + where.join(', ') : ''));
                }
            });

            if (rows.length > lines.length) {
                lines.push('… ' + say('compMore').replace('{var}', rows.length - lines.length));
            }

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
                            var goneSet = {};

                            gone.forEach(function (id) { goneSet[id] = true; });
                            rows.forEach(function (row) {
                                if (goneSet[num(row.dataset.id)]) {
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
