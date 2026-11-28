/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the list in the main menu's Workspace panel, on every panel
 * page: the search of the workspace with a new channel and the settings
 * beside it, Today, one section of channels (pinned, the groups, the rest),
 * the person's discussions; at the foot the tools dock with the workspace's
 * other screens (My Tasks first), the rarer ones behind More.
 *
 * What is in it comes from the server only (ws_side_payload(),
 * includes/workspace/nav.php), which leaves out whatever the person may not
 * see; this script draws what it is given and hides nothing itself. The
 * workspace's own screens print the data with the page (#ws-side-data);
 * anywhere else it is asked for (the ws_side action) the first time the
 * panel opens.
 *
 * Every entry is a link, so it opens its screen, a new tab or a channel from
 * any page. The workspace's screens hand in what they answer on the spot
 * (PGWsSide.attach(): assets/js/workspace.js): the channel screen opens a
 * channel, a discussion or the overview in place and adds the pin, the
 * dragging and the right-click menus. The list has no inbox of its own:
 * its Notifications row opens the panel's bell, the one inbox
 * (backend.src.js). Which sections and
 * groups are closed is the person's, on this browser, the same on every
 * page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // ── The composer's unsent text, kept on this browser ──
    // One slot per site, viewer, channel and discussion, so two people on one
    // browser or two sites on one host never read each other's text. The
    // composer (workspace.js) writes it; the list marks a row that holds one.
    // Set before the list looks for its box: the composer needs it on a page
    // whatever the list does.
    var DRAFT_PREFIX = 'ws-draft:';
    var DRAFT_DAYS = 14;

    function draftKey(site, viewer, channelId, threadId) {
        var thread = parseInt(threadId, 10) || 0;

        return DRAFT_PREFIX + String(site || '') + '|' + (parseInt(viewer, 10) || 0)
            + ':c' + (parseInt(channelId, 10) || 0) + (thread ? ':t' + thread : '');
    }

    // A kept value as { raw, labels, at } while it is younger than DRAFT_DAYS
    // and holds words; null otherwise.
    function draftFresh(stored, now) {
        var value = null;

        try {
            value = JSON.parse(String(stored || ''));
        } catch (error) {
            return null;
        }

        if (!value || (typeof value.raw !== 'string') || (value.raw.trim() === '') || (typeof value.at !== 'number')) {
            return null;
        }

        if (!(value.at > now - DRAFT_DAYS * 86400000)) {
            return null;
        }

        var labels = (value.labels && (typeof value.labels === 'object') && !Array.isArray(value.labels)) ? value.labels : {};

        return { raw: value.raw, labels: labels, at: value.at };
    }

    window.PGWsDraft = {
        prefix: DRAFT_PREFIX,
        days: DRAFT_DAYS,
        key: draftKey,
        fresh: draftFresh
    };

    // Whether a row holds something new for the list's Unread filter: a
    // joined channel or a discussion with an unread count; a muted channel
    // (notify none) only when it mentions the reader.
    function sideUnread(item, isThread) {
        if (!item || !((parseInt(item.unread, 10) || 0) > 0)) {
            return false;
        }

        if (isThread) {
            return true;
        }

        return !!item.joined && ((item.notify !== 'none') || ((parseInt(item.mentions, 10) || 0) > 0));
    }

    // The row Alt+Up / Alt+Down go to. rows are the list's rows in their
    // order, each { key, unread }; step is 1 (down) or -1 (up); unreadOnly
    // (with Shift) passes over the rows with nothing new. From the open row
    // the next that fits, '' past the first or the last; with no row open,
    // from the top whichever the direction.
    function sideStep(rows, currentKey, step, unreadOnly) {
        var list = rows || [];
        var at = -1;
        var i;

        for (i = 0; i < list.length; i++) {
            if (currentKey && (list[i].key === currentKey)) {
                at = i;
                break;
            }
        }

        if (at < 0) {
            step = 1;
        }

        for (i = at + step; (i >= 0) && (i < list.length); i += step) {
            if (!unreadOnly || list[i].unread) {
                return list[i].key;
            }
        }

        return '';
    }

    var root = document.getElementById('ws-menu-side');

    if (!root) {
        return;
    }

    var panel = root.closest('.pg-menu-panel');
    var API_URL = root.getAttribute('data-ws-side-api') || '';
    var TARGET = root.getAttribute('data-ws-side-target') || '';
    var THREADS_SHOWN = 5;

    // Each part of the workspace has its own colour in the list (the
    // [data-ws-nav] rules of backend.src.css); the channels take theirs.
    var state = {
        data: null,
        hooks: null,
        loading: false,
        threadsAll: false,
        // More's card: open or shut on this page; null until the first draw,
        // which opens it on one of its own screens.
        moreOpen: null,
        // Drawn once while on the screen (draw()).
        shown: false,
        // The rows the keys walk, in the list's order, from the last draw.
        order: [],
        // A key pressed before the list's data had come (load()).
        pendingStep: null,
        // "Mark all as read" on its way.
        reading: false
    };

    var FILTER_KEY = 'ws-side-filter';

    var S = {};

    // ── Small helpers, as workspace.js has them ─────────────────────────

    function t(key) {
        var text = Object.prototype.hasOwnProperty.call(S, key) ? S[key] : key;
        var values = Array.prototype.slice.call(arguments, 1);

        values.forEach(function (value, index) {
            text = text.split('{' + (index + 1) + '}').join(String(value));
        });

        return text;
    }

    function el(tag, className, text) {
        var node = document.createElement(tag);

        if (className) {
            node.className = className;
        }

        if ((text !== undefined) && (text !== null) && (text !== '')) {
            node.textContent = String(text);
        }

        return node;
    }

    function icon(name, extra) {
        var node = el('i', 'bi ' + name + (extra ? ' ' + extra : ''));
        node.setAttribute('aria-hidden', 'true');
        return node;
    }

    // A place's icon: the main menu row's drawing the server sent (svg, a
    // place that stands for a row), else its bi icon.
    function navIcon(item) {
        if (item.svg) {
            var box = document.createElement('span');

            box.innerHTML = item.svg;

            if (box.firstChild) {
                return box.firstChild;
            }
        }

        return icon(item.icon);
    }

    // An icon with a small mark in its corner (ws_icon_badged_html() in
    // includes/workspace/channel_icons.php); the plain icon without one.
    function badgedIcon(name, badge, extra) {
        if (!badge) {
            return icon(name, extra);
        }

        var node = el('span', 'ws-icon-badged' + (extra ? ' ' + extra : ''));
        node.setAttribute('aria-hidden', 'true');
        node.appendChild(el('i', 'bi ' + name));
        node.appendChild(el('i', 'bi ' + badge + ' ws-icon-badge'));
        return node;
    }

    // A channel's icon on its 18 px tile: the one it was given, with the mark
    // of its kind in the corner (a lock, a door, a person), or the one its
    // kind gives. The tile takes the row's --ws-ch (the channel's colour)
    // as its tone; without one it is grey.
    function channelIcon(channel, extra) {
        var name = channel.icon || (channel.kind === 'private' ? 'bi-lock' : (channel.contact_id ? 'bi-person-vcard' : 'bi-hash'));
        var tile = el('span', 'pg-icon-tile ws-side-ch-tile' + (extra ? ' ' + extra : ''));

        tile.setAttribute('aria-hidden', 'true');
        tile.appendChild(badgedIcon(name, channel.icon_badge || '', ''));

        return tile;
    }

    // Whether the reader keeps an unsent text for a channel or a discussion
    // on this browser (the composer's slot, draftKey()).
    function hasDraft(channelId, threadId) {
        var data = state.data || {};

        if (!data.draft) {
            return false;
        }

        try {
            return !!draftFresh(window.localStorage.getItem(draftKey(data.draft.site, data.draft.me, channelId, threadId)), Date.now());
        } catch (error) {
            return false;
        }
    }

    function button(className, label, iconName, title) {
        var node = el('button', className);
        node.type = 'button';

        if (iconName) {
            // A button of the look's recipes spaces its icon with its gap.
            node.appendChild(icon(iconName, (label && !/\bpg-button\b/.test(className)) ? 'me-1' : ''));
        }

        if (label) {
            node.appendChild(document.createTextNode(label));
        }

        if (title) {
            node.title = title;
            node.setAttribute('aria-label', title);
            node.classList.add('no-popover');
        }

        return node;
    }

    function clear(node) {
        while (node.firstChild) {
            node.removeChild(node.firstChild);
        }

        return node;
    }

    function countBadge(count, mention) {
        return count ? el('span', 'ws-count' + (mention ? ' ws-count-mention' : ''), count > 99 ? '99+' : count) : null;
    }

    function workspaceUrl(query) {
        var base = (state.data && state.data.urls && state.data.urls.workspace) || '';

        return base + (query ? '?' + query : '');
    }

    // An entry that leaves for its screen. In the page toolbar's frame the
    // whole window goes, not the frame.
    function link(className, href) {
        var node = el('a', className);

        node.href = href;

        if (TARGET) {
            node.target = TARGET;
        }

        return node;
    }

    function go(href) {
        var win = (TARGET === '_parent') ? window.parent : window;

        win.location.href = href;
    }

    function menu() {
        return window.pgMenu || null;
    }

    // An entry this page answers itself: a plain click is taken, the
    // widened rail or the phone's drawer steps aside, the hook runs. A click
    // with a modifier or the middle button still opens the address in a new
    // tab. The loading curtain leaves it alone (pg_preloader_markup()).
    function inPlace(node, handler) {
        if (typeof handler !== 'function') {
            return;
        }

        node.setAttribute('data-pg-no-curtain', '');
        node.addEventListener('click', function (event) {
            if (event.defaultPrevented || (event.button !== 0) || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
                return;
            }

            event.preventDefault();

            if (menu()) {
                menu().dismiss();
            }

            handler();
        });
    }

    // ── What is kept on this browser ────────────────────────────────────

    function readKept(key) {
        try {
            return JSON.parse(window.localStorage.getItem(key) || '{}') || {};
        } catch (error) {
            return {};
        }
    }

    function writeKept(key, id, shut) {
        var kept = readKept(key);

        if (shut) {
            kept[id] = 1;
        } else {
            delete kept[id];
        }

        try {
            window.localStorage.setItem(key, JSON.stringify(kept));
        } catch (error) {
            // Not kept: it opens again next time.
        }
    }

    // The list's filter, the person's on every page: 'unread' or 'all'.
    function readFilter() {
        try {
            return (window.localStorage.getItem(FILTER_KEY) === 'unread') ? 'unread' : 'all';
        } catch (error) {
            return 'all';
        }
    }

    function writeFilter(value) {
        try {
            window.localStorage.setItem(FILTER_KEY, (value === 'unread') ? 'unread' : 'all');
        } catch (error) {
            // Not kept: this page still shows the choice.
        }
    }

    // Which group of the list holds a place (ws_nav_groups()), '' for none.
    function navGroupOf(key) {
        var nav = (state.data && state.data.nav) || {};
        var found = '';

        Object.keys(nav).some(function (group) {
            return (nav[group] || []).some(function (item) {
                if (item.key === key) {
                    found = group;
                }

                return found !== '';
            });
        });

        return found;
    }

    // ── The groups of channels (includes/workspace/groups.php) ──────────

    function groupTree(groups) {
        var children = {};

        (groups || []).forEach(function (group) {
            (children[group.parent_id] = children[group.parent_id] || []).push(group);
        });

        Object.keys(children).forEach(function (key) {
            children[key].sort(function (a, b) { return (a.sort - b.sort) || a.name.localeCompare(b.name); });
        });

        return children;
    }

    function groupTreeIds(children, id) {
        var out = [];
        var queue = [id];

        while (queue.length) {
            var next = queue.shift();

            if (out.indexOf(next) === -1) {
                out.push(next);
                (children[next] || []).forEach(function (child) { queue.push(child.id); });
            }
        }

        return out;
    }

    // What is unread in a group and the groups inside it, for its closed
    // heading: the channels one is in and hears from.
    function groupUnread(channels, children, id) {
        var tree = groupTreeIds(children, id);

        return channels.reduce(function (sum, channel) {
            return sum + ((tree.indexOf(channel.group_id) !== -1 && !channel.pinned && channel.joined && channel.notify !== 'none') ? (channel.unread || 0) : 0);
        }, 0);
    }

    // What is new in a list of channels: the mentions when there are any,
    // otherwise the unread messages of the channels not muted.
    function channelsBadge(list) {
        var mentions = 0;
        var unread = 0;

        list.forEach(function (channel) {
            mentions += channel.mentions || 0;

            if (channel.notify !== 'none') {
                unread += channel.unread || 0;
            }
        });

        return mentions ? countBadge(mentions, true) : countBadge(unread, false);
    }

    // ── Drawing ─────────────────────────────────────────────────────────

    // The data drawn: the channel screen's own, kept current by its sync,
    // or what the server sent for this page.
    function current() {
        var data = state.data || {};
        var hooks = state.hooks || {};
        var live = (typeof hooks.data === 'function') ? hooks.data() : null;

        return {
            channels: (live && live.channels) || data.channels || [],
            groups: (live && live.groups) || data.groups || [],
            threads: (live && live.threads) || data.threads || [],
            inbox: (live && (typeof live.inbox === 'number')) ? live.inbox : (data.inbox || 0),
            tasksDue: (live && (typeof live.tasks_due === 'number')) ? live.tasks_due : (data.tasks_due || 0)
        };
    }

    // What is on the screen: the channel screen says (hooks.current());
    // before it has, the address does.
    function here() {
        var hooks = state.hooks || {};

        if (typeof hooks.current === 'function') {
            return hooks.current() || {};
        }

        var screen = (state.data && state.data.screen) || '';

        if (screen !== 'channels') {
            return { view: '', channel: 0, thread: 0, screen: screen };
        }

        var query = new URLSearchParams(window.location.search);

        return {
            view: query.get('view') || '',
            channel: parseInt(query.get('channel') || '0', 10) || 0,
            thread: parseInt(query.get('thread') || '0', 10) || 0,
            screen: screen
        };
    }

    // The Notifications row's red pill: the unread count at the row's right
    // edge, nothing at zero.
    function paintNotifyCount(node, count) {
        var pill = node.querySelector('.ws-side-notify-count');

        if (!count) {
            if (pill) {
                pill.parentNode.removeChild(pill);
            }

            node.removeAttribute('aria-label');
            return;
        }

        var shown = (count > 99) ? '99+' : String(count);

        if (!pill) {
            pill = el('span', 'ws-side-notify-count badge rounded-pill theme-danger');
            pill.setAttribute('aria-hidden', 'true');
            node.appendChild(pill);
        }

        pill.textContent = shown;
        node.setAttribute('aria-label', t('side_notify') + ', ' + t('side_notify_unread', shown));
    }

    // The menu row's count of what is new for the person, kept with the
    // list on the screens that keep the list current; the list's
    // Notifications row shows the same number.
    function paintRowBadge(count) {
        var row = panel ? document.getElementById(panel.getAttribute('data-pg-menu-row')) : null;
        var notify = root.querySelector('[data-ws-side-key="notify"]');

        if (notify) {
            paintNotifyCount(notify, count);
        }

        if (!row) {
            return;
        }

        var badge = row.querySelector('.pg-menu-badge');

        if (!count) {
            if (badge) {
                badge.parentNode.removeChild(badge);
            }

            return;
        }

        if (!badge) {
            badge = el('span', 'pg-menu-badge badge rounded-pill theme-danger');

            var hint = row.querySelector('.pg-menu-hint');

            row.insertBefore(badge, hint || null);
        }

        badge.textContent = (count > 99) ? '99+' : String(count);
    }

    function draw() {
        if (!state.data) {
            return;
        }

        S = state.data.strings || {};

        var hooks = state.hooks || {};
        var navHooks = hooks.nav || {};
        var manage = !!hooks.manage;
        var data = current();
        var now = here();
        var scroller = root.closest('.menu-body');
        var focused = document.activeElement && root.contains(document.activeElement) ? document.activeElement.getAttribute('data-ws-side-key') : '';
        var keep = {
            top: scroller ? scroller.scrollTop : 0
        };
        var closedParts = readKept('ws-side-closed');
        var closedGroups = readKept('ws-groups-closed');
        var fragment = document.createDocumentFragment();
        // Unread: only the channels and discussions holding something new,
        // and the open one; Today stays.
        var unreadOnly = readFilter() === 'unread';
        var unreadRows = data.channels.filter(function (channel) { return sideUnread(channel, false); }).length
            + data.threads.filter(function (thread) { return sideUnread(thread, true); }).length;
        var order = [];

        var nav = state.data.nav || {};
        var titles = state.data.titles || {};

        // ── Head: one search field for the whole workspace, the row to
        // itself; a new channel and the settings beside the panel's name
        // over it (the tour is played from the channel's header) ──

        var head = el('div', 'ws-side-head');
        var tools = el('span', 'ws-side-head-tools');
        var find;

        // The workspace search (workspace_palette.js) finds channels,
        // people, messages, decisions, tasks, notes and files; a page
        // without it goes to the overview, which opens it.
        if (typeof hooks.palette === 'function') {
            find = button('ws-side-find no-popover', '', '', '');
            find.title = t('pal_shortcut');
            find.addEventListener('click', function () {
                if (menu()) {
                    menu().dismiss();
                }

                hooks.palette();
            });
        } else {
            // Ctrl+K is the panel's own search on such a page: the way to
            // the workspace's names no key.
            find = link('ws-side-find no-popover', workspaceUrl('view=home&palette=1'));
            find.title = t('pal_open');
            find.setAttribute('data-pg-menu-skip', '');
        }

        // The field's words say both ways it serves: a search, and going to
        // a channel or a screen by its name.
        find.setAttribute('aria-label', t('side_find'));
        find.setAttribute('data-ws-side-key', 'palette');
        find.appendChild(icon('bi-search'));
        find.appendChild(el('span', 'ws-side-find-text', t('side_find')));

        // Its key where Ctrl+K opens it; a touch screen has none to show.
        if (typeof hooks.palette === 'function') {
            find.appendChild(el('kbd', 'ws-side-find-key', /Mac|iPhone|iPad/.test(navigator.platform || '') ? '\u2318K' : 'Ctrl K'));
        }

        head.appendChild(find);

        var add;

        if (typeof hooks.newChannel === 'function') {
            add = button('pg-icon-button pg-icon-button-sm ws-side-tool', '', 'bi-plus-lg', t('new_channel'));
            add.addEventListener('click', function () {
                if (menu()) {
                    menu().dismiss();
                }

                hooks.newChannel();
            });
        } else {
            add = link('pg-icon-button pg-icon-button-sm ws-side-tool no-popover', workspaceUrl('view=home&new=channel'));
            add.title = t('new_channel');
            add.setAttribute('aria-label', add.title);
            add.setAttribute('data-pg-menu-skip', '');
            add.appendChild(icon('bi-plus-lg'));
        }

        add.setAttribute('data-ws-side-key', 'new');
        tools.appendChild(add);

        // Workspace Settings, for whoever holds the right (ws_nav_groups()).
        (nav.head || []).forEach(function (item) {
            var gear = link('pg-icon-button pg-icon-button-sm ws-side-tool no-popover' + (navActive(item) ? ' active' : ''), item.url);

            gear.setAttribute('data-ws-nav', item.key);
            gear.setAttribute('data-ws-side-key', 'nav:' + item.key);
            gear.title = item.label;
            gear.setAttribute('aria-label', item.label);
            gear.appendChild(icon('bi-gear'));

            if (navActive(item)) {
                gear.setAttribute('aria-current', 'page');
            }

            inPlace(gear, navHooks[item.key]);
            tools.insertBefore(gear, add);
        });

        // In the panel's head when there is one, the tool that had the
        // focus keeping it; else at the end of the search row.
        var panelHead = panel ? panel.querySelector('.pg-menu-panel-head') : null;

        if (panelHead) {
            var oldTools = panelHead.querySelector('.ws-side-head-tools');
            var focusedTool = (oldTools && oldTools.contains(document.activeElement)) ? document.activeElement.getAttribute('data-ws-side-key') : '';

            if (oldTools) {
                oldTools.parentNode.removeChild(oldTools);
            }

            panelHead.appendChild(tools);

            if (focusedTool) {
                var again = tools.querySelector('[data-ws-side-key="' + focusedTool + '"]');

                if (again) {
                    again.focus({ preventScroll: true });
                }
            }
        } else {
            head.appendChild(tools);
        }

        fragment.appendChild(head);

        // ── All / Unread across the width; Unread counts the rows holding
        // something new, and its title names the keys ──

        var filter = el('div', 'pg-seg ws-side-filter');

        filter.setAttribute('role', 'group');
        filter.setAttribute('aria-label', t('side_filter'));

        [['all', t('side_all')], ['unread', t('side_unread_tab')]].forEach(function (choice) {
            var chosen = (choice[0] === 'unread') === unreadOnly;
            var item = el('button', 'pg-seg-item no-popover');

            item.type = 'button';
            item.setAttribute('aria-pressed', chosen ? 'true' : 'false');
            item.setAttribute('data-ws-side-key', 'filter:' + choice[0]);
            item.appendChild(document.createTextNode(choice[1]));

            if (choice[0] === 'unread') {
                item.title = t('side_keys');

                if (unreadRows) {
                    item.appendChild(el('span', 'pg-seg-count', (unreadRows > 99) ? '99+' : String(unreadRows)));
                }
            }

            item.addEventListener('click', function () {
                if (!chosen) {
                    writeFilter(choice[0]);
                    draw();
                }
            });
            filter.appendChild(item);
        });

        fragment.appendChild(filter);

        var scroll = el('div', 'ws-side-scroll');

        fragment.appendChild(scroll);

        // A heading that opens and closes its section: its name as written
        // (no capitals), the chevron at its end, shown on pointing, on focus
        // and while the section is closed; closed, it still says how much is
        // new inside.
        function sectionToggle(box, key, title, iconName, shut, badge) {
            var toggle = el('button', 'ws-side-toggle no-popover');

            toggle.type = 'button';
            toggle.setAttribute('aria-expanded', shut ? 'false' : 'true');
            toggle.setAttribute('data-ws-side-key', 'section:' + key);
            toggle.title = shut ? t('side_expand') : t('side_collapse');

            if (iconName) {
                toggle.appendChild(icon(iconName));
            }

            toggle.appendChild(el('span', 'ws-side-toggle-name', title));
            toggle.appendChild(icon(shut ? 'bi-chevron-right' : 'bi-chevron-down', 'ws-side-toggle-chevron'));
            toggle.addEventListener('click', function () {
                writeKept('ws-side-closed', key, !shut);
                draw();
            });
            box.appendChild(toggle);

            if (shut && badge) {
                box.appendChild(badge);
            }
        }

        // An entry of a place of the workspace (ws_nav_groups()).
        function navLink(box, item, active) {
            var node = link('ws-channel-link' + (active ? ' active' : ''), item.url);

            node.setAttribute('data-ws-nav', item.key);
            node.setAttribute('data-pg-menu-label', item.label);
            node.appendChild(navIcon(item));
            node.appendChild(el('span', 'ws-channel-name', item.label));
            inPlace(node, navHooks[item.key]);
            box.appendChild(node);
        }

        // A cell of the tools dock: the screen's icon on a tile in its own
        // tone over its short name; the full name is its title. A number
        // the server counted stands at the tile's corner: the tasks due
        // today or before in the accent, the unread e-mails quiet.
        function navCell(item, active, count) {
            var node = link('ws-side-cell no-popover' + (active ? ' active' : ''), item.url);
            var tile = el('span', 'pg-icon-tile ws-side-cell-tile');

            node.setAttribute('data-ws-nav', item.key);
            node.setAttribute('data-ws-side-key', 'nav:' + item.key);
            node.setAttribute('data-pg-menu-label', item.label);
            node.title = item.label;

            if (active) {
                node.setAttribute('aria-current', 'page');
            }

            tile.setAttribute('aria-hidden', 'true');
            tile.appendChild(navIcon(item));
            node.appendChild(tile);
            node.appendChild(el('span', 'ws-side-cell-name', item.short || item.label));

            if (count > 0) {
                var shown = (count > 99) ? '99+' : String(count);

                node.appendChild(el('span', 'ws-side-cell-count' + ((item.key === 'tasks') ? ' ws-side-cell-due' : ''), shown));
                node.setAttribute('aria-label', item.label + ', ' + ((item.key === 'tasks') ? t('side_due', shown) : shown));
            }

            inPlace(node, navHooks[item.key]);

            return node;
        }

        function navActive(item) {
            if (typeof hooks.current === 'function') {
                return ((item.key === 'home') && (now.view === 'home')) || ((item.key === 'scheduled') && (now.view === 'scheduled'));
            }

            return item.key === (now.screen || '');
        }

        // A section of entries, and whether it is closed.
        function group(title, key) {
            var box = el('div', 'ws-side-section');
            var shut = !!(key && closedParts[key]);

            if (title && key) {
                var heading = el('div', 'ws-side-title');

                sectionToggle(heading, key, title, '', shut, null);
                box.appendChild(heading);
            }

            scroll.appendChild(box);
            return { box: box, shut: shut };
        }

        function emptyLine(kind, text, extra) {
            if (typeof hooks.emptyState === 'function') {
                return hooks.emptyState(kind, text, extra);
            }

            var box = el('div', 'ws-empty ws-empty-friendly ' + extra);

            box.appendChild(el('div', 'ws-empty-line', text));
            return box;
        }

        // ── Today, without a heading, over the channels ──

        var top = group('', '').box;

        (nav.top || []).forEach(function (item) {
            navLink(top, item, navActive(item));
        });

        // Notifications: the bell's icon and the unread count. It opens the
        // bell on Team, so it is never the list's current entry; where the
        // page has no bell its address opens the workspace's own drawer.
        (function () {
            var node = link('ws-channel-link ws-side-notify', workspaceUrl('view=home&inbox=1'));
            var host = ((TARGET === '_parent') && window.parent && (typeof window.parent.pgBellOpen === 'function')) ? window.parent : window;

            node.setAttribute('data-ws-side-key', 'notify');
            node.setAttribute('data-pg-menu-skip', '');
            node.setAttribute('data-pg-menu-label', t('side_notify'));
            node.appendChild(icon('bi-bell'));
            node.appendChild(el('span', 'ws-channel-name', t('side_notify')));
            paintNotifyCount(node, data.inbox);

            if (typeof host.pgBellOpen === 'function') {
                inPlace(node, function () {
                    if (!host.pgBellOpen('workspace')) {
                        go(node.href);
                    }
                });
            }

            top.appendChild(node);
        })();

        // Channels in a group are listed under it (pinned ones stay pinned).
        var groupKnown = {};

        data.groups.forEach(function (item) { groupKnown[item.id] = item; });

        function inGroup(channel) {
            return !channel.pinned && channel.group_id && groupKnown[channel.group_id];
        }

        // Whether a channel is drawn: every one with All; with Unread those
        // holding something new and the open one.
        function listed(channel) {
            return !unreadOnly || (now.channel === channel.id) || sideUnread(channel, false);
        }

        var joined = data.channels.filter(function (channel) { return channel.joined; });
        var pinned = joined.filter(function (channel) { return channel.pinned && listed(channel); });
        var mine = joined.filter(function (channel) { return !channel.pinned && !inGroup(channel) && listed(channel); });
        var others = data.channels.filter(function (channel) { return !channel.joined && !inGroup(channel); });
        var byGroup = {};

        data.channels.forEach(function (channel) {
            if (inGroup(channel)) {
                (byGroup[channel.group_id] = byGroup[channel.group_id] || []).push(channel);
            }
        });

        // A mouse shortcut at the end of a row (the pin, "⋯", Join). It sits
        // inside the link, so it is hidden from the link's name and out of
        // the Tab order; the keyboard reaches the same action from the
        // channel's context menu (Shift+F10 or the menu key on the link) or
        // on the channel's own screen (workspace/workspace-nested-controls.md).
        function rowShortcut(className, iconName, title, handler) {
            var node = el('span', className + ' no-popover');

            node.setAttribute('aria-hidden', 'true');
            node.title = title;

            if (iconName) {
                node.appendChild(icon(iconName));
            } else {
                node.textContent = title;
            }

            node.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                handler(node);
            });

            return node;
        }

        // A row of 30 px: the channel's tile, its name, then its marks at the
        // end. Unread: the name in weight and the count in a quiet pill, the
        // mentions in the accent. Muted (notify none): the name quiet, a
        // struck bell and, for something unread, a dot in the count's place;
        // a mention still shows its pill. A kept draft: a pencil. Pointing at
        // a row where the screen can pin shows the pin and "⋯" in the
        // count's place, a pinned channel's pin included.
        function channelLink(channel, box, sortable) {
            var muted = channel.notify === 'none';
            var node = link('ws-channel-link'
                + ((now.channel === channel.id) ? ' active' : '')
                + ((channel.unread && (!muted || channel.mentions)) ? ' ws-unread' : '')
                + (muted ? ' ws-side-muted' : '')
                + (channel.joined ? '' : ' ws-notjoined'), workspaceUrl('channel=' + channel.id));

            node.setAttribute('data-ws-channel', channel.id);
            node.setAttribute('data-pg-menu-label', channel.name);

            // A joined channel is a stop of the keys, in drawn order.
            if (channel.joined) {
                order.push({ key: 'c:' + channel.id, unread: sideUnread(channel, false), channel: channel.id, thread: 0, href: node.href });
            }

            // The channel's own colour on its tile.
            if (channel.hex) {
                node.style.setProperty('--ws-ch', channel.hex);
                node.setAttribute('data-ws-colored', '1');
            }

            node.appendChild(channelIcon(channel, 'ws-ch-icon'));
            node.appendChild(el('span', 'ws-channel-name', channel.name));

            var marks = el('span', 'ws-side-marks');

            if (hasDraft(channel.id, 0)) {
                marks.appendChild(icon('bi-pencil', 'ws-side-draft'));
                marks.appendChild(el('span', 'visually-hidden', t('side_draft')));
            }

            if (muted) {
                marks.appendChild(icon('bi-bell-slash', 'ws-side-muted-bell'));
            }

            if (channel.mentions) {
                marks.appendChild(countBadge(channel.mentions, true));
            } else if (channel.unread && !muted) {
                marks.appendChild(countBadge(channel.unread, false));
            } else if (channel.unread && channel.joined) {
                marks.appendChild(el('span', 'ws-side-dot'));
                marks.appendChild(el('span', 'visually-hidden', t('side_unread')));
            }

            // Join, on a channel not joined, where the screen can join it;
            // elsewhere the row opens the channel, which offers it.
            if (!channel.joined && (channel.kind === 'public') && (typeof hooks.joinChannel === 'function')) {
                marks.appendChild(rowShortcut('ws-side-join-now', '', t('join'), function () {
                    hooks.joinChannel(channel);
                }));
            }

            if (marks.firstChild) {
                node.appendChild(marks);
            }

            var tools = el('span', 'ws-side-row-tools');

            if (channel.joined && (typeof hooks.pin === 'function')) {
                tools.appendChild(rowShortcut('ws-pin' + (channel.pinned ? ' ws-pinned' : ''), channel.pinned ? 'bi-pin-angle-fill' : 'bi-pin-angle', channel.pinned ? t('unpin') : t('pin'), function () {
                    hooks.pin(channel, !channel.pinned);
                }));
            }

            if (typeof hooks.channelMenu === 'function') {
                tools.appendChild(rowShortcut('ws-side-row-menu', 'bi-three-dots', t('more'), function (anchor) {
                    var rect = anchor.getBoundingClientRect();

                    hooks.channelMenu(channel, rect.left, rect.bottom);
                }));
            }

            if (tools.firstChild) {
                node.classList.add('ws-side-has-tools');
                node.appendChild(tools);
            }

            if (typeof hooks.openChannel === 'function') {
                inPlace(node, function () { hooks.openChannel(channel.id); });
            }

            // The channel screen's own: the person's order, dragging into a
            // group, the right-click menu.
            if (typeof hooks.decorateChannel === 'function') {
                hooks.decorateChannel(node, channel, box, sortable);
            }

            return node;
        }

        // One part of the Channels section, without a heading of its own.
        function part(box, list, sortable, className) {
            if (!list.length) {
                return;
            }

            var inner = el('div', 'ws-side-part ' + className + (sortable ? ' ws-sortable' : ''));

            list.forEach(function (channel) {
                inner.appendChild(channelLink(channel, inner, sortable));
            });

            box.appendChild(inner);
        }

        var children = groupTree(data.groups);

        function groupListed(id) {
            return groupTreeIds(children, id).some(function (groupId) {
                return (byGroup[groupId] || []).some(listed);
            });
        }

        function groupBox(item, parent, depth) {
            // With Unread a group is drawn when something under it is
            // listed, and its fold is set aside so nothing new hides in it.
            if (unreadOnly && !groupListed(item.id)) {
                return;
            }

            var box = el('div', 'ws-side-group' + (depth ? ' ws-side-subgroup' : ''));
            var heading = el('div', 'ws-side-title ws-group-title');
            var inner = el('div', 'ws-group-inner ws-sortable');
            var shut = !unreadOnly && !!closedGroups[item.id];
            var toggle = el('button', 'ws-group-toggle no-popover');
            var channels = (byGroup[item.id] || []).filter(listed);
            var kids = children[item.id] || [];

            box.setAttribute('data-ws-group-box', item.id);

            if (item.hex) {
                box.style.setProperty('--ws-group', item.hex);
            }

            // A folder in the channels' own column, its name as it was
            // written; the chevron at the end of the line.
            toggle.type = 'button';
            toggle.setAttribute('aria-expanded', shut ? 'false' : 'true');
            toggle.setAttribute('data-ws-side-key', 'group:' + item.id);
            toggle.title = shut ? t('grp_expand') : t('grp_collapse');
            toggle.disabled = unreadOnly;
            toggle.appendChild(icon(shut ? 'bi-folder2' : 'bi-folder2-open', 'ws-group-icon'));
            toggle.appendChild(el('span', 'ws-group-name', item.name));

            // Closed, a group still says there is something new inside.
            var unread = groupUnread(data.channels, children, item.id);

            if (shut && unread) {
                toggle.appendChild(countBadge(unread, false));
            }

            if (manage && item.access) {
                var key = el('span', 'ws-group-access');

                key.title = t('grp_access');
                key.appendChild(icon('bi-key'));
                key.appendChild(document.createTextNode(String(item.access)));
                toggle.appendChild(key);
            }

            toggle.appendChild(icon(shut ? 'bi-chevron-right' : 'bi-chevron-down', 'ws-group-chevron'));
            toggle.addEventListener('click', function () {
                writeKept('ws-groups-closed', item.id, !shut);
                draw();
            });
            heading.appendChild(toggle);
            heading.setAttribute('data-ws-group', item.id);

            // The managers' tools on the heading: its menu, dragging it,
            // dropping a channel or a group on it.
            if (manage && (typeof hooks.decorateGroup === 'function')) {
                hooks.decorateGroup(heading, item);
            }

            box.appendChild(heading);

            if (!shut) {
                channels.forEach(function (channel) {
                    inner.appendChild(channelLink(channel, inner, channel.joined));
                });

                kids.forEach(function (child) {
                    groupBox(child, inner, depth + 1);
                });

                if (!channels.length && !kids.length && !unreadOnly) {
                    var empty = el('div', 'small fg-secondary px-3 ws-group-empty', manage ? t('grp_empty') : t('no_channels'));

                    if (manage && (typeof hooks.dropTarget === 'function')) {
                        hooks.dropTarget(empty, item.id, null);
                    }

                    inner.appendChild(empty);
                }

                box.appendChild(inner);
            }

            parent.appendChild(box);
        }

        // ── Channels, under one heading: pinned, the groups, the person's
        // other channels, then those they are not in (dimmed) ──

        var channelsBox = el('div', 'ws-side-section ws-side-channels');
        var channelsHead = el('div', 'ws-side-title');
        var channelsShut = !!closedParts.channels;

        sectionToggle(channelsHead, 'channels', t('channels'), '', channelsShut, channelsBadge(joined));
        channelsHead.querySelector('.ws-side-toggle').title += ' \u00b7 ' + t('side_keys');

        // Mark all as read, at the heading's right while Unread is chosen
        // and something is new.
        if (unreadOnly && unreadRows) {
            var readAll = button('pg-button pg-button-ghost pg-button-sm ws-side-read-all no-popover', t('side_read_all'));

            readAll.setAttribute('data-ws-side-key', 'read-all');
            readAll.disabled = state.reading;
            readAll.addEventListener('click', function () {
                markAllRead(unreadRows);
            });
            channelsHead.appendChild(readAll);
        }

        channelsBox.appendChild(channelsHead);

        // A channel dropped on the heading comes out of its group.
        if (manage && (typeof hooks.dropTarget === 'function')) {
            hooks.dropTarget(channelsHead, 0, null);
        }

        if (channelsShut) {
            // Closed, the section still shows the one that is on the screen.
            data.channels.forEach(function (channel) {
                if (now.channel === channel.id) {
                    channelsBox.appendChild(channelLink(channel, channelsBox, false));
                }
            });
        } else {
            part(channelsBox, pinned, true, 'ws-side-pinned');

            if ((children[0] || []).length) {
                var groupSection = el('div', 'ws-side-part ws-side-groups');

                (children[0] || []).forEach(function (item) {
                    groupBox(item, groupSection, 0);
                });

                channelsBox.appendChild(groupSection);
            }

            part(channelsBox, mine, true, 'ws-side-mine');

            // The channels not joined that stand in no group, behind one row
            // that opens them in place; open or shut is the person's
            // (ws-side-open). Shut, the one on the screen still shows.
            if (unreadOnly) {
                // No join row: only the open channel, when it is not joined.
                part(channelsBox, others.filter(function (channel) { return now.channel === channel.id; }), false, 'ws-side-others');

                if (!unreadRows) {
                    var allRead = el('div', 'ws-side-all-read');

                    allRead.setAttribute('role', 'status');
                    allRead.appendChild(icon('bi-check2-circle'));
                    allRead.appendChild(el('span', '', t('side_all_read')));
                    channelsBox.appendChild(allRead);
                }
            } else if (others.length) {
                var joinOpen = !!readKept('ws-side-open').join;
                var joinRow = el('button', 'ws-side-join no-popover');

                joinRow.type = 'button';
                joinRow.setAttribute('aria-expanded', joinOpen ? 'true' : 'false');

                if (joinOpen) {
                    joinRow.setAttribute('aria-controls', 'ws-side-others');
                }

                joinRow.setAttribute('data-ws-side-key', 'join');
                joinRow.appendChild(icon('bi-plus-circle-dotted', 'ws-side-join-icon'));
                joinRow.appendChild(el('span', 'ws-channel-name', t('side_join', others.length)));
                joinRow.appendChild(icon(joinOpen ? 'bi-chevron-down' : 'bi-chevron-right', 'ws-side-join-chevron'));
                joinRow.addEventListener('click', function () {
                    writeKept('ws-side-open', 'join', !joinOpen);
                    draw();
                });
                channelsBox.appendChild(joinRow);

                var shown = joinOpen ? others : others.filter(function (channel) { return now.channel === channel.id; });

                part(channelsBox, shown, false, 'ws-side-others');

                var othersBox = channelsBox.querySelector('.ws-side-others');

                if (othersBox) {
                    othersBox.id = 'ws-side-others';
                }
            }

            if (!data.channels.length && !unreadOnly) {
                channelsBox.appendChild(emptyLine('channels', t('no_channels'), 'ws-empty-side'));
            }

            // New group closes the section, a quiet row for whoever
            // manages the groups.
            if (manage && !unreadOnly && (typeof hooks.newGroup === 'function')) {
                var newGroup = el('button', 'ws-group-new no-popover');

                newGroup.type = 'button';
                newGroup.setAttribute('data-ws-side-key', 'new-group');
                newGroup.appendChild(icon('bi-folder-plus'));
                newGroup.appendChild(el('span', 'ws-channel-name', t('grp_new')));
                newGroup.addEventListener('click', function () { hooks.newGroup(); });
                channelsBox.appendChild(newGroup);
            }
        }

        scroll.appendChild(channelsBox);

        // ── My discussions: the open discussions the person is in
        // (threads.php); none, no section. The open one and those of the
        // open channel come first, then the unread ones, then the latest; a
        // few are shown until the rest are asked for ──

        var rank = function (item) {
            return (item.thread.id === now.thread) ? 0 : ((item.thread.channel_id === now.channel) ? 1 : (item.thread.unread ? 2 : 3));
        };
        var threadsListed = data.threads.filter(function (thread) {
            return !unreadOnly || (now.thread === thread.id) || sideUnread(thread, true);
        });
        var threads = threadsListed.map(function (thread, index) {
            return { thread: thread, index: index };
        }).sort(function (a, b) {
            return (rank(a) - rank(b)) || (a.index - b.index);
        }).map(function (item) { return item.thread; });

        if (threads.length) {
            var talks = el('div', 'ws-side-section ws-side-threads');
            var talksHead = el('div', 'ws-side-title');
            // Closed, it lists none, not even the open one: that one has its
            // own panel beside the channel and opens again with the address
            // (&thread=), so keeping it left the section looking open.
            var talksShut = !!closedParts.threads;

            // The keys walk the discussions in their own order (the latest
            // first), not the ranks above, which change as one is opened.
            if (!talksShut) {
                threadsListed.forEach(function (thread) {
                    order.push({ key: 't:' + thread.id, unread: sideUnread(thread, true), channel: thread.channel_id, thread: thread.id, href: workspaceUrl('channel=' + thread.channel_id + '&thread=' + thread.id) });
                });
            }
            var talksUnread = threads.reduce(function (sum, thread) { return sum + (thread.unread || 0); }, 0);

            sectionToggle(talksHead, 'threads', t('th_mine'), '', talksShut, countBadge(talksUnread, false));
            talks.appendChild(talksHead);

            (talksShut ? [] : (state.threadsAll ? threads : threads.slice(0, THREADS_SHOWN))).forEach(function (thread) {
                var item = link('ws-channel-link ws-thread-link' + ((now.thread === thread.id) ? ' active' : '') + (thread.unread ? ' ws-unread' : ''),
                    workspaceUrl('channel=' + thread.channel_id + '&thread=' + thread.id));

                item.title = thread.title + ' · #' + thread.channel;
                item.classList.add('no-popover');
                item.setAttribute('data-pg-menu-label', thread.title);
                item.appendChild(icon('bi-chat-square-dots', 'ws-ch-icon'));

                var names = el('span', 'ws-channel-name');

                names.appendChild(el('span', 'ws-thread-link-title', thread.title));
                names.appendChild(el('small', 'ws-thread-link-channel', '#' + thread.channel));
                item.appendChild(names);

                if (hasDraft(thread.channel_id, thread.id)) {
                    item.appendChild(icon('bi-pencil', 'ws-side-draft'));
                    item.appendChild(el('span', 'visually-hidden', t('side_draft')));
                }

                if (thread.unread) {
                    item.appendChild(countBadge(thread.unread, false));
                }

                if (typeof hooks.openThread === 'function') {
                    inPlace(item, function () { hooks.openThread(thread); });
                }

                talks.appendChild(item);
            });

            if (!talksShut && (threads.length > THREADS_SHOWN)) {
                var more = button('pg-button pg-button-ghost pg-button-sm ws-side-more', state.threadsAll ? t('th_show_fewer') : t('th_show_all', threads.length));

                more.setAttribute('data-ws-side-key', 'threads-more');
                more.addEventListener('click', function () {
                    state.threadsAll = !state.threadsAll;
                    draw();
                });
                talks.appendChild(more);
            }

            scroll.appendChild(talks);
        }

        // ── Tools: a dock at the list's foot that stays, never folded: the
        // screens of a working day four across with their names; "⋯" opens
        // More, the screens visited now and then, as a card over the list ──

        var tools = nav.tools || [];
        var more = nav.more || [];

        if (tools.length || more.length) {
            var dock = el('div', 'ws-side-dock');
            var dockHead = el('div', 'ws-side-dock-head');
            var moreActive = more.some(navActive);

            // The card opens by itself on one of its own screens, once per
            // page; after that it stays as the person left it.
            if (state.moreOpen === null) {
                state.moreOpen = moreActive;
            }

            dock.setAttribute('role', 'group');
            dock.setAttribute('aria-label', titles.tools || '');
            dockHead.appendChild(el('span', 'ws-side-dock-title', titles.tools || ''));
            dock.appendChild(dockHead);

            if (more.length) {
                var moreButton = button('pg-icon-button pg-icon-button-sm ws-side-dock-more', '', 'bi-three-dots', titles.more || t('more'));

                moreButton.setAttribute('aria-expanded', state.moreOpen ? 'true' : 'false');
                moreButton.setAttribute('aria-controls', 'ws-side-more');
                moreButton.setAttribute('data-ws-side-key', 'more');
                moreButton.addEventListener('click', function () {
                    state.moreOpen = !state.moreOpen;
                    draw();

                    var first = state.moreOpen ? root.querySelector('#ws-side-more a') : null;

                    if (first) {
                        first.focus({ preventScroll: true });
                    }
                });
                dockHead.appendChild(moreButton);
            }

            // Next to its button in the page's order, so Tab goes from "⋯"
            // into it; drawn over the list above the dock. It keeps the
            // widened rail open while it is used (data-pg-menu-hold).
            if (more.length && state.moreOpen) {
                var card = el('div', 'ws-side-more-card');

                card.id = 'ws-side-more';
                card.setAttribute('role', 'group');
                card.setAttribute('aria-label', titles.more || t('more'));
                card.setAttribute('data-pg-menu-hold', '');

                more.forEach(function (item) {
                    navLink(card, item, navActive(item));
                });

                // A place chosen closes the card; one opened in place draws
                // the list again at once.
                card.addEventListener('click', function (event) {
                    if (event.target.closest && event.target.closest('a')) {
                        state.moreOpen = false;

                        if (event.defaultPrevented) {
                            draw();
                        }
                    }
                });
                dock.appendChild(card);
            }

            var cells = el('div', 'ws-side-cells');
            var due = data.tasksDue;

            tools.forEach(function (item) {
                cells.appendChild(navCell(item, navActive(item), (item.key === 'tasks') ? due : (item.count || 0)));
            });

            if (cells.firstChild) {
                dock.appendChild(cells);
            }

            fragment.appendChild(dock);
        }

        clear(root).appendChild(fragment);
        root.removeAttribute('aria-busy');
        state.order = order;

        if (scroller) {
            scroller.scrollTop = keep.top;
        }

        // The first time the list is drawn on the screen it is about, the
        // entry that is open is brought into the menu's view.
        if (!state.shown && root.offsetParent) {
            var open = root.querySelector('.ws-channel-link.active');

            state.shown = true;

            if (open && (typeof open.scrollIntoView === 'function')) {
                open.scrollIntoView({ block: 'nearest' });
            }
        }

        if (focused) {
            var again = root.querySelector('[data-ws-side-key="' + focused + '"]');

            if (again) {
                again.focus({ preventScroll: true });
            }
        }

        if (typeof hooks.data === 'function') {
            paintRowBadge(data.inbox);
        }

        if (menu()) {
            menu().refreshCurrent();
        }
    }

    // The list could not be read: what happened, and the way to ask again.
    function drawError(message) {
        var box = el('div', 'ws-side-error');

        box.setAttribute('role', 'alert');
        box.appendChild(el('div', '', message || root.getAttribute('data-ws-side-error') || ''));

        var again = button('pg-button pg-button-quiet pg-button-sm', root.getAttribute('data-ws-side-retry') || '', 'bi-arrow-clockwise');

        again.addEventListener('click', function () {
            load();
        });
        box.appendChild(again);
        clear(root).appendChild(box);
    }

    function drawLoading() {
        var box = el('div', 'ws-skeleton ws-side-loading');

        box.setAttribute('role', 'status');
        box.setAttribute('aria-busy', 'true');
        box.appendChild(el('span', 'visually-hidden', root.getAttribute('data-ws-side-loading') || ''));

        for (var i = 0; i < 6; i++) {
            var row = el('div', 'ws-skeleton-row');

            row.setAttribute('aria-hidden', 'true');
            row.appendChild(el('span', 'ws-skeleton-dot'));
            row.appendChild(el('span', 'ws-skeleton-bar'));
            box.appendChild(row);
        }

        clear(root).appendChild(box);
    }

    // Asked for once per page, the first time the panel opens (ws_side,
    // includes/workspace/actions.php); a failed try can be made again.
    function load() {
        if (state.loading || state.data || !API_URL) {
            return;
        }

        state.loading = true;
        drawLoading();

        request('ws_side').then(function (json) {
            state.loading = false;

            if (!json || (json.status !== 'success') || !json.data || !json.data.v) {
                drawError((json && json.message) ? String(json.message) : '');
                return;
            }

            state.data = json.data;
            draw();

            // A key pressed while the list was being asked for.
            if (state.pendingStep) {
                var pending = state.pendingStep;

                state.pendingStep = null;
                stepTo(pending.step, pending.unread);
            }
        }).catch(function () {
            state.loading = false;
            state.pendingStep = null;
            drawError('');
        });
    }

    // One action of the panel's api.php (includes/workspace/actions.php).
    function request(action) {
        return fetch(API_URL, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: action, token: (typeof window.software_token !== 'undefined') ? window.software_token : '' })
        }).then(function (response) {
            return response.json();
        });
    }

    // The list asked for again, the rows drawn so far staying until the
    // answer comes: through the channel screen where it keeps the list,
    // else with ws_side.
    function refresh() {
        var hooks = state.hooks || {};

        if (typeof hooks.reload === 'function') {
            hooks.reload();
            return;
        }

        if (!API_URL) {
            return;
        }

        request('ws_side').then(function (json) {
            if (json && (json.status === 'success') && json.data && json.data.v) {
                state.data = json.data;
                draw();
            }
        }).catch(function () {});
    }

    // "Mark all as read" (ws_read_all): asked once, then every channel and
    // discussion the person is in is read and the list asked again.
    function markAllRead(count) {
        if (state.reading || !API_URL) {
            return;
        }

        var ask = (typeof window.pgConfirm === 'function')
            ? window.pgConfirm({ title: t('side_read_all'), message: t('side_read_ask', count), confirmText: t('side_read_yes'), variant: 'primary' })
            : Promise.resolve(window.confirm(t('side_read_ask', count)));

        ask.then(function (agreed) {
            // The dialog took the pointer off the widened rail or the phone's
            // drawer, which closed: the list comes back to show the answer.
            if (panel && menu() && !root.offsetParent) {
                menu().openPanel(panel.id, {});
            }

            if (!agreed) {
                return;
            }

            state.reading = true;
            draw();

            request('ws_read_all').then(function (json) {
                state.reading = false;

                if (!json || (json.status !== 'success')) {
                    draw();

                    if (typeof window.pgToast === 'function') {
                        window.pgToast({ message: (json && json.message) ? String(json.message) : t('error_generic'), variant: 'danger' });
                    }

                    return;
                }

                refresh();

                // The button is gone with what it read: the focus waits on
                // the filter.
                var unread = root.querySelector('[data-ws-side-key="filter:unread"]');

                if (unread) {
                    unread.focus({ preventScroll: true });
                }
            }).catch(function () {
                state.reading = false;
                draw();
            });
        });
    }

    // The keys: Alt+Up and Alt+Down the row before and after the open one,
    // with Shift the next holding something new. On the channel screen in
    // place, elsewhere by the row's address.
    function stepTo(step, unreadOnly) {
        var now = here();
        var currentKey = now.thread ? 't:' + now.thread : (now.channel ? 'c:' + now.channel : '');
        var key = sideStep(state.order, currentKey, step, unreadOnly);
        var hooks = state.hooks || {};
        var row = null;

        state.order.some(function (item) {
            if (item.key === key) {
                row = item;
            }

            return !!row;
        });

        if (!row) {
            return;
        }

        if (row.thread && (typeof hooks.openThread === 'function')) {
            hooks.openThread({ id: row.thread, channel_id: row.channel });
        } else if (!row.thread && (typeof hooks.openChannel === 'function')) {
            hooks.openChannel(row.channel);
        } else {
            go(row.href);
        }
    }

    // Whether the keys are left to the page: a dialog, a drawer or the Flows
    // editor is open, the field already uses Alt and an arrow (a select, a
    // date or time field), or a writing box holds a selection.
    function keysBusy(target) {
        if ((window.pgUI && (window.pgUI.anyOpen('dialog') || window.pgUI.anyOpen('drawer'))) || document.querySelector('.wsf-app:not(.d-none)')) {
            return true;
        }

        if (!target || (target.nodeType !== 1)) {
            return false;
        }

        var tag = target.tagName;

        if ((tag === 'SELECT') || target.closest('[data-pg-date], [data-pg-datetime], [data-pg-time], .flatpickr-calendar')) {
            return true;
        }

        if ((tag === 'INPUT') && /^(date|datetime-local|time|month|week|number|range)$/i.test(target.type || '')) {
            return true;
        }

        if ((tag === 'TEXTAREA') || (tag === 'INPUT')) {
            try {
                return target.selectionStart !== target.selectionEnd;
            } catch (error) {
                return false;
            }
        }

        if (target.isContentEditable) {
            var selection = window.getSelection ? window.getSelection() : null;

            return !!(selection && !selection.isCollapsed);
        }

        return false;
    }

    function inlineData() {
        var node = document.getElementById('ws-side-data');

        if (!node) {
            return null;
        }

        try {
            var parsed = JSON.parse(node.textContent || '{}');

            return (parsed && parsed.v) ? parsed : null;
        } catch (error) {
            return null;
        }
    }

    // More's card closes with Esc, the focus going back to "⋯", and with a
    // click outside it. Esc is taken on the window's capture phase and
    // stopped there, before the menu's own (which would close the widened
    // rail or step the phone drawer back), as the bell's row menu does; only
    // while the focus is in the list or nowhere, so a dialog of the page
    // keeps its own Esc.
    window.addEventListener('keydown', function (event) {
        if (!state.moreOpen || (event.key !== 'Escape') || event.defaultPrevented) {
            return;
        }

        var active = document.activeElement;

        if (active && (active !== document.body) && !root.contains(active)) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        state.moreOpen = false;
        draw();

        var toggle = root.querySelector('.ws-side-dock-more');

        if (toggle) {
            toggle.focus({ preventScroll: true });
        }
    }, true);

    document.addEventListener('click', function (event) {
        if (!state.moreOpen) {
            return;
        }

        // The path, as the toggle that was clicked has been drawn again.
        var path = (typeof event.composedPath === 'function') ? event.composedPath() : [event.target];
        var inside = path.some(function (node) {
            return !!(node && (node.nodeType === 1) && ((node.id === 'ws-side-more') || node.classList.contains('ws-side-dock-more') || node.hasAttribute('data-pg-menu-hold')));
        });

        if (!inside) {
            state.moreOpen = false;
            draw();
        }
    });

    window.addEventListener('keydown', function (event) {
        if (event.defaultPrevented || !event.altKey || event.ctrlKey || event.metaKey
            || ((event.key !== 'ArrowUp') && (event.key !== 'ArrowDown')) || keysBusy(event.target)) {
            return;
        }

        event.preventDefault();

        var step = (event.key === 'ArrowUp') ? -1 : 1;

        if (!state.data) {
            // Asked for the first time; the step waits for it.
            state.pendingStep = { step: step, unread: event.shiftKey };
            load();
            return;
        }

        stepTo(step, event.shiftKey);
    });

    // A draft kept or dropped in another tab marks or clears its row here.
    window.addEventListener('storage', function (event) {
        if (state.data && ((event.key === null) || (String(event.key).indexOf(DRAFT_PREFIX) === 0))) {
            draw();
        }
    });

    var inline = inlineData();

    if (inline) {
        state.data = inline;
        draw();
    }

    if (panel) {
        panel.addEventListener('pg:menu-panel-open', function () {
            load();
        });

        if (panel.classList.contains('is-open')) {
            load();
        }
    }

    window.PGWsSide = {
        // The data printed with the page, for the screen's own start
        // (ws_bootstrap asked without what this already holds).
        inline: function () {
            return inline;
        },

        // What the screen answers itself (see the head of this file);
        // drawn again with it.
        attach: function (hooks) {
            state.hooks = hooks || null;
            draw();
        },

        attached: function () {
            return !!(state.hooks && state.data);
        },

        redraw: function () {
            draw();
        },

        // The list on the screen: the menu's drawer on a phone, the rail
        // widened over the page where the menu is narrow or hidden.
        reveal: function (options) {
            if (panel && menu()) {
                menu().openPanel(panel.id, options || {});
            }
        },

        // Sections opened again before something points at them (the tour).
        openSections: function (keys) {
            (keys || []).forEach(function (key) {
                writeKept('ws-side-closed', key, false);
            });
            draw();
        },

        // Whether the list holds a place of the workspace (ws_nav_groups()),
        // drawn or waiting behind More.
        has: function (key) {
            return navGroupOf(key) !== '';
        },

        // Opens what keeps a place out of sight: More's card for one of its
        // screens (the tools dock is never folded); then draws the list
        // again (the tour).
        openFor: function (key) {
            var group = navGroupOf(key);

            if (group === 'more') {
                state.moreOpen = true;
            }

            if (group !== '') {
                draw();
            }
        },

        root: function () {
            return root;
        }
    };
})();
