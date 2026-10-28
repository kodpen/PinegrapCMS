/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the list in the main menu's Workspace panel, on every panel
 * page: the search, the overview and the inbox, the discussions, the groups
 * of channels, the channels, the workspace's screens and its settings.
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
 * dragging and the right-click menus; the other screens open the inbox and
 * the search box. Which sections and groups are closed is the person's, on
 * this browser, the same on every page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

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
        // Drawn once while on the screen (draw()).
        shown: false
    };

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

    // A channel's icon: the one it was given, with the mark of its kind in
    // the corner (a lock, a door, a person), or the one its kind gives.
    function channelIcon(channel, extra) {
        var name = channel.icon || (channel.kind === 'private' ? 'bi-lock' : (channel.contact_id ? 'bi-person-vcard' : 'bi-hash'));

        return badgedIcon(name, channel.icon_badge || '', extra);
    }

    function button(className, label, iconName, title) {
        var node = el('button', className);
        node.type = 'button';

        if (iconName) {
            node.appendChild(icon(iconName, label ? 'me-1' : ''));
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
            inbox: (live && (typeof live.inbox === 'number')) ? live.inbox : (data.inbox || 0)
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

    // The menu row's count of what is new for the person, kept with the
    // list on the screens that keep the list current.
    function paintRowBadge(count) {
        var row = panel ? document.getElementById(panel.getAttribute('data-pg-menu-row')) : null;

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
        var beforeSearch = root.querySelector('.ws-side-head input[type="search"]');
        var focused = document.activeElement && root.contains(document.activeElement) ? document.activeElement.getAttribute('data-ws-side-key') : '';
        var keep = {
            top: scroller ? scroller.scrollTop : 0,
            search: beforeSearch ? beforeSearch.value : ''
        };
        var closedParts = readKept('ws-side-closed');
        var closedGroups = readKept('ws-groups-closed');
        var fragment = document.createDocumentFragment();

        // ── Head: the search of the messages, the tour, the search box of the
        // whole workspace, a new channel ──

        var head = el('div', 'ws-side-head');
        var search = el('input', 'form-control form-control-sm rounded-pill');

        search.type = 'search';
        search.placeholder = t('search_messages');
        search.setAttribute('aria-label', search.placeholder);
        search.setAttribute('data-ws-side-key', 'search');
        search.value = keep.search;
        search.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();

                var text = search.value.trim();

                if (text === '') {
                    return;
                }

                if (typeof hooks.search === 'function') {
                    if (menu()) {
                        menu().dismiss();
                    }

                    hooks.search(text);
                } else {
                    go(workspaceUrl('view=home&search=' + encodeURIComponent(text)));
                }
            } else if (((event.key === 'Escape') || (event.key === 'Esc')) && (search.value !== '')) {
                // A box with words in it clears first; the panel steps back
                // on the next Escape.
                event.preventDefault();
                event.stopPropagation();
                search.value = '';
            }
        });
        head.appendChild(search);

        // The workspace's own tour (includes/workspace/tour.php), played
        // again from here, on the screen it is about.
        if (typeof hooks.tour === 'function') {
            var tour = button('btn btn-sm btn-text theme-secondary rounded-pill', '', 'bi-signpost-split', t('tour_start'));

            tour.setAttribute('data-ws-tour', '1');
            tour.setAttribute('data-ws-side-key', 'tour');
            tour.addEventListener('click', function () { hooks.tour(); });
            head.appendChild(tour);
        }

        // The search box of the whole workspace (workspace_palette.js), or
        // the way to it on a page without it.
        var find;

        if (typeof hooks.palette === 'function') {
            find = button('btn btn-sm btn-text theme-secondary rounded-pill', '', 'bi-search', t('pal_shortcut'));
            find.addEventListener('click', function () {
                if (menu()) {
                    menu().dismiss();
                }

                hooks.palette();
            });
        } else {
            find = link('btn btn-sm btn-text theme-secondary rounded-pill no-popover', workspaceUrl('view=home&palette=1'));
            find.title = t('pal_shortcut');
            find.setAttribute('aria-label', find.title);
            find.setAttribute('data-pg-menu-skip', '');
            find.appendChild(icon('bi-search'));
        }

        find.setAttribute('data-ws-side-key', 'palette');
        head.appendChild(find);

        var add;

        if (typeof hooks.newChannel === 'function') {
            add = button('btn btn-sm btn-solid theme-primary rounded-pill', '', 'bi-plus-lg', t('new_channel'));
            add.addEventListener('click', function () {
                if (menu()) {
                    menu().dismiss();
                }

                hooks.newChannel();
            });
        } else {
            add = link('btn btn-sm btn-solid theme-primary rounded-pill no-popover', workspaceUrl('view=home&new=channel'));
            add.title = t('new_channel');
            add.setAttribute('aria-label', add.title);
            add.setAttribute('data-pg-menu-skip', '');
            add.appendChild(icon('bi-plus-lg'));
        }

        add.setAttribute('data-ws-side-key', 'new');
        head.appendChild(add);
        fragment.appendChild(head);

        var scroll = el('div', 'ws-side-scroll');

        fragment.appendChild(scroll);

        // A heading that opens and closes its section; closed, it still
        // says how much is new inside.
        function sectionToggle(box, key, title, iconName, shut, badge) {
            var toggle = el('button', 'ws-side-toggle no-popover');

            toggle.type = 'button';
            toggle.setAttribute('aria-expanded', shut ? 'false' : 'true');
            toggle.setAttribute('data-ws-side-key', 'section:' + key);
            toggle.title = shut ? t('side_expand') : t('side_collapse');
            toggle.appendChild(icon(shut ? 'bi-chevron-right' : 'bi-chevron-down', 'ws-group-chevron'));

            if (iconName) {
                toggle.appendChild(icon(iconName));
            }

            toggle.appendChild(el('span', 'ws-side-toggle-name', title));
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
        function navLink(box, item, active, badge) {
            var node = link('ws-channel-link' + (active ? ' active' : ''), item.url);

            node.setAttribute('data-ws-nav', item.key);
            node.setAttribute('data-pg-menu-label', item.label);
            node.appendChild(icon(item.icon));
            node.appendChild(el('span', 'ws-channel-name', item.label));

            if (badge) {
                node.appendChild(el('span', 'ws-count ws-count-mention', badge > 99 ? '99+' : badge));
            }

            inPlace(node, navHooks[item.key]);
            box.appendChild(node);
        }

        function navActive(item) {
            if (typeof hooks.current === 'function') {
                return ((item.key === 'home') && (now.view === 'home')) || ((item.key === 'scheduled') && (now.view === 'scheduled'));
            }

            return item.key === (now.screen || '');
        }

        // A section of entries; null when it is closed and nothing goes in.
        function group(title, key) {
            var box = el('div', 'ws-side-section');
            var shut = !!(key && closedParts[key]);

            if (title && key) {
                var heading = el('div', 'ws-side-title');

                sectionToggle(heading, key, title, '', shut, null);
                box.appendChild(heading);
            }

            scroll.appendChild(box);
            return shut ? null : box;
        }

        function emptyLine(kind, text, extra) {
            if (typeof hooks.emptyState === 'function') {
                return hooks.emptyState(kind, text, extra);
            }

            var box = el('div', 'ws-empty ws-empty-friendly ' + extra);

            box.appendChild(el('div', 'ws-empty-line', text));
            return box;
        }

        var nav = state.data.nav || {};
        var titles = state.data.titles || {};
        var top = group('', '');

        (nav.top || []).forEach(function (item) {
            navLink(top, item, navActive(item), item.inbox ? data.inbox : 0);
        });

        // Channels in a group are listed under it (pinned ones stay pinned).
        var groupKnown = {};

        data.groups.forEach(function (item) { groupKnown[item.id] = item; });

        function inGroup(channel) {
            return !channel.pinned && channel.group_id && groupKnown[channel.group_id];
        }

        var joined = data.channels.filter(function (channel) { return channel.joined; });
        var pinned = joined.filter(function (channel) { return channel.pinned; });
        var mine = joined.filter(function (channel) { return !channel.pinned && !inGroup(channel); });
        var others = data.channels.filter(function (channel) { return !channel.joined && !inGroup(channel); });
        var byGroup = {};

        data.channels.forEach(function (channel) {
            if (inGroup(channel)) {
                (byGroup[channel.group_id] = byGroup[channel.group_id] || []).push(channel);
            }
        });

        function channelLink(channel, box, sortable) {
            var node = link('ws-channel-link'
                + ((now.channel === channel.id) ? ' active' : '')
                + (channel.unread ? ' ws-unread' : '')
                + (channel.joined ? '' : ' ws-notjoined'), workspaceUrl('channel=' + channel.id));

            node.setAttribute('data-ws-channel', channel.id);
            node.setAttribute('data-pg-menu-label', channel.name);

            // The channel's own colour on its sign.
            if (channel.hex) {
                node.style.setProperty('--ws-ch', channel.hex);
                node.setAttribute('data-ws-colored', '1');
            }

            node.appendChild(channelIcon(channel, 'ws-ch-icon'));
            node.appendChild(el('span', 'ws-channel-name', channel.name));

            if (channel.notify === 'none') {
                node.appendChild(icon('bi-bell-slash', 'fg-secondary'));
            }

            if (channel.mentions) {
                node.appendChild(el('span', 'ws-count ws-count-mention', channel.mentions));
            } else if (channel.unread && channel.notify !== 'none') {
                node.appendChild(el('span', 'ws-count', channel.unread > 99 ? '99+' : channel.unread));
            }

            // A pin at the end of the line, shown on hover (and always on a
            // pinned channel), where the screen can pin.
            if (channel.joined && (typeof hooks.pin === 'function')) {
                var pin = el('span', 'ws-pin no-popover' + (channel.pinned ? ' ws-pinned' : ''));

                pin.setAttribute('role', 'button');
                pin.title = channel.pinned ? t('unpin') : t('pin');
                pin.appendChild(icon(channel.pinned ? 'bi-pin-angle-fill' : 'bi-pin-angle'));
                pin.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    hooks.pin(channel, !channel.pinned);
                });
                node.appendChild(pin);
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

        function section(title, list, sortable, iconName, dropOut, key) {
            var box = el('div', 'ws-side-section' + (sortable ? ' ws-sortable' : ''));
            var heading = el('div', 'ws-side-title');
            var shut = !!closedParts[key];

            sectionToggle(heading, key, title, iconName, shut, channelsBadge(list));
            box.appendChild(heading);

            // A channel dropped on the heading comes out of its group.
            if (dropOut && manage && (typeof hooks.dropTarget === 'function')) {
                hooks.dropTarget(heading, 0, null);
            }

            // Closed, a section still shows the one that is on the screen.
            if (shut) {
                list.forEach(function (channel) {
                    if (now.channel === channel.id) {
                        box.appendChild(channelLink(channel, box, false));
                    }
                });
            } else {
                list.forEach(function (channel) {
                    box.appendChild(channelLink(channel, box, sortable));
                });

                if (!list.length) {
                    box.appendChild(emptyLine('channels', t('no_channels'), 'ws-empty-side'));
                }
            }

            scroll.appendChild(box);
        }

        var children = groupTree(data.groups);

        function groupBox(item, parent, depth) {
            var box = el('div', 'ws-side-group' + (depth ? ' ws-side-subgroup' : ''));
            var heading = el('div', 'ws-side-title ws-group-title');
            var inner = el('div', 'ws-group-inner ws-sortable');
            var shut = !!closedGroups[item.id];
            var toggle = el('button', 'ws-group-toggle no-popover');
            var channels = byGroup[item.id] || [];
            var kids = children[item.id] || [];

            box.setAttribute('data-ws-group-box', item.id);

            if (item.hex) {
                box.style.setProperty('--ws-group', item.hex);
            }

            toggle.type = 'button';
            toggle.setAttribute('aria-expanded', shut ? 'false' : 'true');
            toggle.setAttribute('data-ws-side-key', 'group:' + item.id);
            toggle.title = shut ? t('grp_expand') : t('grp_collapse');
            toggle.appendChild(icon(shut ? 'bi-chevron-right' : 'bi-chevron-down', 'ws-group-chevron'));
            toggle.appendChild(icon(shut ? 'bi-folder2' : 'bi-folder2-open', 'ws-group-icon'));
            toggle.appendChild(el('span', 'ws-group-name', item.name));

            // Closed, a group still says there is something new inside.
            var unread = groupUnread(data.channels, children, item.id);

            if (shut && unread) {
                toggle.appendChild(el('span', 'ws-count', unread > 99 ? '99+' : unread));
            }

            if (manage && item.access) {
                var key = el('span', 'ws-group-access');

                key.title = t('grp_access');
                key.appendChild(icon('bi-key'));
                key.appendChild(document.createTextNode(String(item.access)));
                toggle.appendChild(key);
            }

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

                if (!channels.length && !kids.length) {
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

        if (pinned.length) {
            section(t('pinned'), pinned, true, 'bi-pin-angle', false, 'pinned');
        }

        // The open discussions the person is in (threads.php); none, no
        // section. The open one and those of the open channel come first,
        // then the unread ones, then the latest; a few are shown until the
        // rest are asked for.
        var rank = function (item) {
            return (item.thread.id === now.thread) ? 0 : ((item.thread.channel_id === now.channel) ? 1 : (item.thread.unread ? 2 : 3));
        };
        var threads = data.threads.map(function (thread, index) {
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
            var talksUnread = threads.reduce(function (sum, thread) { return sum + (thread.unread || 0); }, 0);

            sectionToggle(talksHead, 'threads', t('th_section'), 'bi-chat-square-dots', talksShut, countBadge(talksUnread, false));
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

                if (thread.unread) {
                    item.appendChild(el('span', 'ws-count', thread.unread > 99 ? '99+' : thread.unread));
                }

                if (typeof hooks.openThread === 'function') {
                    inPlace(item, function () { hooks.openThread(thread); });
                }

                talks.appendChild(item);
            });

            if (!talksShut && (threads.length > THREADS_SHOWN)) {
                var more = button('btn btn-sm btn-link pg-link-secondary ws-side-more', state.threadsAll ? t('th_show_fewer') : t('th_show_all', threads.length));

                more.setAttribute('data-ws-side-key', 'threads-more');
                more.addEventListener('click', function () {
                    state.threadsAll = !state.threadsAll;
                    draw();
                });
                talks.appendChild(more);
            }

            scroll.appendChild(talks);
        }

        if ((children[0] || []).length || manage) {
            var groupSection = el('div', 'ws-side-section ws-side-groups');

            (children[0] || []).forEach(function (item) {
                groupBox(item, groupSection, 0);
            });

            if (manage && (typeof hooks.newGroup === 'function')) {
                var newGroup = button('btn btn-sm btn-link pg-link-secondary ws-group-new', t('grp_new'), 'bi-folder-plus');

                newGroup.setAttribute('data-ws-side-key', 'new-group');
                newGroup.addEventListener('click', function () { hooks.newGroup(); });
                groupSection.appendChild(newGroup);
            }

            scroll.appendChild(groupSection);
        }

        section(t('my_channels'), mine, true, '', true, 'mine');

        if (others.length) {
            section(t('other_channels'), others, false, '', true, 'others');
        }

        ['work', 'records'].forEach(function (key) {
            var items = nav[key] || [];

            if (!items.length) {
                return;
            }

            var box = group(titles[key] || '', key);

            if (box) {
                items.forEach(function (item) {
                    navLink(box, item, navActive(item), 0);
                });
            }
        });

        // The settings stay at the foot of the list, however long the list
        // of channels grows.
        if ((nav.foot || []).length) {
            var foot = el('div', 'ws-side-foot');

            nav.foot.forEach(function (item) {
                navLink(foot, item, navActive(item), 0);
            });

            fragment.appendChild(foot);
        }

        clear(root).appendChild(fragment);
        root.removeAttribute('aria-busy');

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

        var again = button('btn btn-sm btn-outline theme-secondary', root.getAttribute('data-ws-side-retry') || '', 'bi-arrow-clockwise');

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

        fetch(API_URL, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'ws_side', token: (typeof window.software_token !== 'undefined') ? window.software_token : '' })
        }).then(function (response) {
            return response.json();
        }).then(function (json) {
            state.loading = false;

            if (!json || (json.status !== 'success') || !json.data || !json.data.v) {
                drawError((json && json.message) ? String(json.message) : '');
                return;
            }

            state.data = json.data;
            draw();
        }).catch(function () {
            state.loading = false;
            drawError('');
        });
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

        root: function () {
            return root;
        }
    };
})();
