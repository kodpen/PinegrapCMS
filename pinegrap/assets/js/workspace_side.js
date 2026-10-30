/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the list in the main menu's Workspace panel, on every panel
 * page: the search of the workspace with a new channel, the settings and the
 * tour beside it, the places (overview, inbox, tasks), one section of
 * channels (pinned, the groups, the rest), the person's discussions and the
 * workspace's other screens as tiles, the rarer ones behind More.
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
 * dragging and the right-click menus; the other screens open the inbox in
 * place. Which sections and groups are closed is the person's, on
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
        var focused = document.activeElement && root.contains(document.activeElement) ? document.activeElement.getAttribute('data-ws-side-key') : '';
        var keep = {
            top: scroller ? scroller.scrollTop : 0
        };
        var closedParts = readKept('ws-side-closed');
        var closedGroups = readKept('ws-groups-closed');
        var fragment = document.createDocumentFragment();

        var nav = state.data.nav || {};
        var titles = state.data.titles || {};

        // ── Head: one search field for the whole workspace, a new channel,
        // the settings and the tour ──

        var head = el('div', 'ws-side-head');
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

        find.setAttribute('aria-label', t('pal_open'));
        find.setAttribute('data-ws-side-key', 'palette');
        find.appendChild(icon('bi-search'));
        find.appendChild(el('span', 'ws-side-find-text', t('pal_open')));
        head.appendChild(find);

        var add;

        if (typeof hooks.newChannel === 'function') {
            add = button('btn btn-sm btn-solid theme-primary rounded-pill ws-side-tool', '', 'bi-plus-lg', t('new_channel'));
            add.addEventListener('click', function () {
                if (menu()) {
                    menu().dismiss();
                }

                hooks.newChannel();
            });
        } else {
            add = link('btn btn-sm btn-solid theme-primary rounded-pill ws-side-tool no-popover', workspaceUrl('view=home&new=channel'));
            add.title = t('new_channel');
            add.setAttribute('aria-label', add.title);
            add.setAttribute('data-pg-menu-skip', '');
            add.appendChild(icon('bi-plus-lg'));
        }

        add.setAttribute('data-ws-side-key', 'new');
        head.appendChild(add);

        // Workspace Settings, for whoever holds the right (ws_nav_groups()).
        (nav.head || []).forEach(function (item) {
            var gear = link('btn btn-sm btn-text theme-secondary rounded-pill ws-side-tool no-popover' + (navActive(item) ? ' active' : ''), item.url);

            gear.setAttribute('data-ws-nav', item.key);
            gear.setAttribute('data-ws-side-key', 'nav:' + item.key);
            gear.title = item.label;
            gear.setAttribute('aria-label', item.label);
            gear.appendChild(icon('bi-gear'));

            if (navActive(item)) {
                gear.setAttribute('aria-current', 'page');
            }

            inPlace(gear, navHooks[item.key]);
            head.appendChild(gear);
        });

        // The workspace's own tour (includes/workspace/tour.php), played
        // again from here, on the screen it is about.
        if (typeof hooks.tour === 'function') {
            var tour = button('btn btn-sm btn-text theme-secondary rounded-pill ws-side-tool', '', 'bi-signpost-split', t('tour_start'));

            tour.setAttribute('data-ws-tour', '1');
            tour.setAttribute('data-ws-side-key', 'tour');
            tour.addEventListener('click', function () { hooks.tour(); });
            head.appendChild(tour);
        }

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

        // A tile of Tools: the screen's icon in its own colour and its short
        // name; the full name is its title.
        function navTile(item, active) {
            var node = link('ws-side-tile no-popover' + (active ? ' active' : ''), item.url);

            node.setAttribute('data-ws-nav', item.key);
            node.setAttribute('data-ws-side-key', 'nav:' + item.key);
            node.setAttribute('data-pg-menu-label', item.label);
            node.title = item.label;

            if (active) {
                node.setAttribute('aria-current', 'page');
            }

            node.appendChild(icon(item.icon));
            node.appendChild(el('span', 'ws-side-tile-name', item.short || item.label));
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

        // ── The places, without a heading: Overview, Inbox, My Tasks ──

        var top = group('', '').box;

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
                node.appendChild(countBadge(channel.mentions, true));
            } else if (channel.unread && channel.notify !== 'none') {
                node.appendChild(countBadge(channel.unread, false));
            }

            // A pin at the end of the line, shown on hover (and always on a
            // pinned channel), where the screen can pin. It sits inside the
            // link, so it is a mouse shortcut only, hidden from the link's
            // name; the keyboard pins from the channel's context menu
            // (Shift+F10 or the menu key on the link).
            if (channel.joined && (typeof hooks.pin === 'function')) {
                var pin = el('span', 'ws-pin no-popover' + (channel.pinned ? ' ws-pinned' : ''));

                pin.setAttribute('aria-hidden', 'true');
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
                toggle.appendChild(countBadge(unread, false));
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

        // ── Channels, under one heading: pinned, the groups, the person's
        // other channels, then those they are not in (dimmed) ──

        var channelsBox = el('div', 'ws-side-section ws-side-channels');
        var channelsHead = el('div', 'ws-side-title');
        var channelsShut = !!closedParts.channels;

        sectionToggle(channelsHead, 'channels', t('channels'), '', channelsShut, channelsBadge(joined));
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

            if ((children[0] || []).length || manage) {
                var groupSection = el('div', 'ws-side-part ws-side-groups');

                (children[0] || []).forEach(function (item) {
                    groupBox(item, groupSection, 0);
                });

                if (manage && (typeof hooks.newGroup === 'function')) {
                    var newGroup = button('btn btn-sm btn-link pg-link-secondary ws-group-new', t('grp_new'), 'bi-folder-plus');

                    newGroup.setAttribute('data-ws-side-key', 'new-group');
                    newGroup.addEventListener('click', function () { hooks.newGroup(); });
                    groupSection.appendChild(newGroup);
                }

                channelsBox.appendChild(groupSection);
            }

            part(channelsBox, mine, true, 'ws-side-mine');
            part(channelsBox, others, false, 'ws-side-others');

            if (!data.channels.length) {
                channelsBox.appendChild(emptyLine('channels', t('no_channels'), 'ws-empty-side'));
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

                if (thread.unread) {
                    item.appendChild(countBadge(thread.unread, false));
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

        // ── Tools: the screens of a working day as tiles, two to a row, the
        // last tile More opening the screens visited now and then ──

        var tools = nav.tools || [];
        var more = nav.more || [];

        if (tools.length || more.length) {
            var toolsPart = group(titles.tools || '', 'tools');
            var moreActive = more.some(navActive);
            // More stays as the person left it; it opens by itself on one of
            // its own screens.
            var moreOpen = !!readKept('ws-side-open').more || moreActive;
            var tiles = el('div', 'ws-side-tiles');

            // Closed, the section still shows the screen that is open.
            tools.forEach(function (item) {
                var active = navActive(item);

                if (!toolsPart.shut || active) {
                    tiles.appendChild(navTile(item, active));
                }
            });

            if (more.length && !toolsPart.shut) {
                var moreTile = button('ws-side-tile ws-side-more-tile no-popover', '', '', '');

                moreTile.setAttribute('aria-expanded', moreOpen ? 'true' : 'false');
                moreTile.setAttribute('aria-controls', 'ws-side-more');
                moreTile.setAttribute('data-ws-side-key', 'more');
                moreTile.appendChild(icon('bi-three-dots'));
                moreTile.appendChild(el('span', 'ws-side-tile-name', titles.more || t('more')));
                moreTile.appendChild(icon(moreOpen ? 'bi-chevron-up' : 'bi-chevron-down', 'ws-side-tile-chevron'));
                moreTile.addEventListener('click', function () {
                    writeKept('ws-side-open', 'more', !moreOpen);
                    draw();
                });
                tiles.appendChild(moreTile);
            }

            if (tiles.firstChild) {
                toolsPart.box.appendChild(tiles);
            }

            if (more.length && (moreOpen || moreActive)) {
                var moreList = el('div', 'ws-side-more');

                moreList.id = 'ws-side-more';

                more.forEach(function (item) {
                    var active = navActive(item);

                    if (!toolsPart.shut || active) {
                        navLink(moreList, item, active, 0);
                    }
                });

                if (moreList.firstChild) {
                    toolsPart.box.appendChild(moreList);
                }
            }
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

        // Whether the list holds a place of the workspace (ws_nav_groups()),
        // drawn or waiting in a closed section or behind More.
        has: function (key) {
            return navGroupOf(key) !== '';
        },

        // Opens what keeps a place out of sight: Tools, and More for one of
        // its screens; then draws the list again (the tour).
        openFor: function (key) {
            var group = navGroupOf(key);

            if ((group === 'tools') || (group === 'more')) {
                writeKept('ws-side-closed', 'tools', false);
            }

            if (group === 'more') {
                writeKept('ws-side-open', 'more', true);
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
