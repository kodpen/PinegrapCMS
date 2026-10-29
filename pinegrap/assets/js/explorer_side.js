/**
 * Pinegrap - Enterprise Website Platform
 *
 * The File Manager's folder tree and the Catalog Manager's product group
 * tree in their main menu panels, on every panel page but the explorer's own
 * screens (there the screen moves its own tree into the panel:
 * view_folders.php).
 *
 * The box is printed by the server (pg_explorer_side_markup(),
 * includes/fn/explorer_side.php) with the fixed entries the person may reach;
 * the branches are asked for (the explorer_side action) the first time the
 * panel opens and as they are opened, and hold only the folders or groups
 * the server lets this person see. Every row is a link to the folder or
 * group on its explorer screen. Which branches are open is the explorer's
 * own record for the area in this browser; its Pinned and Recent lists are
 * drawn only as far as the server confirms them for this person.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // How many branches one request may ask for (pg_explorer_side_ids()),
    // and how many Pinned and Recent ids it may have checked.
    var NODES_PER_REQUEST = 25;
    var SHORTCUTS_PER_REQUEST = 50;
    var RECENT_LIMIT = 5;

    function storageGet(key) {
        try {
            return window.localStorage.getItem(key);
        } catch (error) {
            return null;
        }
    }

    function storageSet(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch (error) {
            // Not kept: the tree opens as it was the next time.
        }
    }

    function readJson(key, fallback) {
        try {
            var value = JSON.parse(storageGet(key) || 'null');

            return (value && (typeof value === 'object')) ? value : fallback;
        } catch (error) {
            return fallback;
        }
    }

    function el(tag, className, text) {
        var node = document.createElement(tag);

        if (className) {
            node.className = className;
        }

        if (text !== undefined) {
            node.textContent = text;
        }

        return node;
    }

    function wholeNumber(value) {
        var number = parseInt(value, 10);

        return ((String(number) === String(value)) && (number >= 0)) ? number : -1;
    }

    // A refusal the server explained, as opposed to a dropped connection or
    // a page that was not JSON (a session that ran out): only its words are
    // shown, anything else gets the box's own message.
    function serverError(message) {
        var error = new Error(message);

        error.fromServer = true;

        return error;
    }

    function shownMessage(error) {
        return (error && error.fromServer && error.message) ? String(error.message) : '';
    }

    function Tree(box) {
        this.box = box;
        this.area = (box.getAttribute('data-explorer-side') === 'catalog') ? 'catalog' : 'files';
        this.api = box.getAttribute('data-explorer-side-api') || '';
        this.base = box.getAttribute('data-explorer-side-base') || '';
        this.target = box.getAttribute('data-explorer-side-target') || '';
        this.rootList = box.querySelector('[data-explorer-side-root]');
        this.panel = box.closest('.pg-menu-panel');
        this.rows = {};
        this.asked = {};
        this.waiting = [];
        this.started = false;
        // The explorer's own keys for this area (view_folders.php).
        this.openKey = 'pg_explorer_tree_' + this.area;
        this.open = readJson(this.openKey, {});
    }

    Tree.prototype.label = function (name) {
        return this.box.getAttribute('data-explorer-side-' + name) || '';
    };

    Tree.prototype.href = function (id) {
        return this.base + ((this.area === 'catalog') ? 'view_product_groups.php?group=' : 'view_folders.php?folder=') + id;
    };

    Tree.prototype.link = function (id, iconClass, name, labelClass) {
        var link = el('a', 'pg-explorer-side-link');

        link.href = this.href(id);
        link.setAttribute('data-pg-menu-skip', '');
        link.title = name;

        if (this.target) {
            link.target = this.target;
        }

        var icon = el('span', iconClass);

        icon.setAttribute('aria-hidden', 'true');
        link.appendChild(icon);
        link.appendChild(el('span', 'tree-label' + (labelClass ? ' ' + labelClass : ''), name));

        return link;
    };

    Tree.prototype.icon = function (row) {
        if (this.area === 'catalog') {
            return 'bi ' + (row.variants ? 'bi-collection-fill' : 'bi-boxes') + (row.enabled ? '' : ' catalog-off');
        }

        return 'bi bi-folder-fill' + (row.access ? ' ' + row.access : '');
    };

    // A list while its rows are on the way, and when they could not come.
    Tree.prototype.drawWait = function (list) {
        list.textContent = '';
        list.appendChild(el('li', 'pg-explorer-side-wait', this.label('loading')));
    };

    Tree.prototype.drawError = function (list, message, retry) {
        var li = el('li', 'pg-explorer-side-wait');
        var button = el('button', 'btn btn-link btn-sm p-0 ms-2', this.label('retry'));

        li.appendChild(document.createTextNode(message || this.label('error')));
        button.type = 'button';
        button.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            retry();
        });
        li.appendChild(button);

        list.textContent = '';
        list.appendChild(li);
    };

    // One row: the chevron (a button, when there is something under it) and
    // the link, then the branch's own list.
    Tree.prototype.item = function (row) {
        var self = this;
        var li = el('li');
        var line = el('div', 'tree-row' + ((this.area === 'catalog') && !row.enabled ? ' catalog-off' : ''));
        var list = el('ul');
        var opened = !!this.open[row.id];

        if (row.children) {
            var toggle = el('button', 'tree-toggle bi bi-chevron-right' + (opened ? ' open' : ''));

            toggle.type = 'button';
            toggle.setAttribute('aria-expanded', opened ? 'true' : 'false');
            toggle.setAttribute('aria-label', this.label(opened ? 'close' : 'expand'));
            toggle.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                self.toggle(row.id, toggle, list);
            });
            line.appendChild(toggle);
        } else {
            line.appendChild(el('span', 'tree-toggle'));
        }

        line.appendChild(this.link(row.id, this.icon(row), row.name, row.archived ? 'archived' : ''));
        li.appendChild(line);

        list.hidden = !opened;
        li.appendChild(list);

        if (opened && row.children) {
            this.fill(list, row.id);
        }

        return li;
    };

    Tree.prototype.fill = function (list, id) {
        var self = this;

        if (!this.rows[id]) {
            this.drawWait(list);
            this.ask([id], function () {
                self.fill(list, id);
            }, function (message) {
                self.drawError(list, message, function () {
                    self.fill(list, id);
                });
            });
            return;
        }

        list.textContent = '';

        this.rows[id].forEach(function (row) {
            list.appendChild(self.item(row));
        });
    };

    Tree.prototype.toggle = function (id, toggle, list) {
        var opened = list.hidden;

        list.hidden = !opened;
        toggle.classList.toggle('open', opened);
        toggle.setAttribute('aria-expanded', opened ? 'true' : 'false');
        toggle.setAttribute('aria-label', this.label(opened ? 'close' : 'expand'));

        if (opened) {
            this.open[id] = true;
            this.fill(list, id);
        } else {
            delete this.open[id];
        }

        storageSet(this.openKey, JSON.stringify(this.open));
    };

    // One request to the explorer_side action; the branches it brings are
    // kept. Rejects with serverError() only for a refusal the server sent.
    Tree.prototype.post = function (nodeIds, shortcutIds) {
        var self = this;

        return fetch(this.api, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'explorer_side',
                token: (typeof window.software_token !== 'undefined') ? window.software_token : '',
                area: this.area,
                node_ids: nodeIds,
                shortcut_ids: shortcutIds || []
            })
        }).then(function (response) {
            return response.json();
        }).then(function (json) {
            if (!json || (json.status !== 'success') || !Array.isArray(json.nodes)) {
                throw serverError((json && json.message) ? String(json.message) : '');
            }

            json.nodes.forEach(function (node) {
                self.rows[node.id] = Array.isArray(node.rows) ? node.rows : [];
            });

            return json;
        });
    };

    // Asks for the branches not in yet, in requests of NODES_PER_REQUEST,
    // leaving out those already on the way; done runs once all of them are
    // in, whichever request brought them, failed if one of them could not.
    Tree.prototype.ask = function (ids, done, failed) {
        var self = this;
        var missing = ids.filter(function (id) {
            return !self.rows[id];
        });

        if (!missing.length) {
            done();
            return;
        }

        this.waiting.push({ ids: missing, done: done, failed: failed });

        var wanted = missing.filter(function (id) {
            return !self.asked[id];
        });

        for (var start = 0; start < wanted.length; start += NODES_PER_REQUEST) {
            this.send(wanted.slice(start, start + NODES_PER_REQUEST), null).catch(function () {
                // Told through the waiting list.
            });
        }
    };

    Tree.prototype.send = function (ids, shortcutIds) {
        var self = this;

        ids.forEach(function (id) {
            self.asked[id] = true;
        });

        return this.post(ids, shortcutIds).then(function (json) {
            self.flush();

            return json;
        }, function (error) {
            self.fail(ids, shownMessage(error));

            throw error;
        });
    };

    Tree.prototype.flush = function () {
        var self = this;
        var waiting = this.waiting;

        this.waiting = [];

        waiting.forEach(function (wait) {
            if (wait.ids.every(function (id) { return !!self.rows[id]; })) {
                wait.done();
            } else {
                self.waiting.push(wait);
            }
        });
    };

    // Whoever waited on these branches is told, and may ask again.
    Tree.prototype.fail = function (ids, message) {
        var self = this;
        var failed = [];

        ids.forEach(function (id) {
            delete self.asked[id];
        });

        this.waiting = this.waiting.filter(function (wait) {
            var hit = wait.ids.some(function (id) {
                return ids.indexOf(id) !== -1;
            });

            if (hit && wait.failed) {
                failed.push(wait.failed);
            }

            return !hit;
        });

        failed.forEach(function (tell) {
            tell(message);
        });
    };

    // The Pinned and Recent ids the explorer keeps for this area.
    Tree.prototype.shortcutLists = function () {
        var lists = {};

        ['pinned', 'recent'].forEach(function (name) {
            var list = readJson('pg_explorer_' + name + '_' + this.area, []);

            list = Array.isArray(list) ? list : [];

            if (name === 'recent') {
                list = list.slice(0, RECENT_LIMIT);
            }

            lists[name] = list.map(function (entry) {
                return wholeNumber(entry && entry.id);
            }).filter(function (id) {
                return id > 0;
            });
        }, this);

        return lists;
    };

    // Pinned and Recent, as far as the server confirmed them (allowed: the
    // shortcuts of its answer), under the names it gave.
    Tree.prototype.drawShortcuts = function (lists, allowed) {
        var self = this;
        var names = {};
        var icon = (this.area === 'catalog') ? 'bi bi-boxes fg-primary' : 'bi bi-folder-fill fg-primary';

        (Array.isArray(allowed) ? allowed : []).forEach(function (entry) {
            var id = wholeNumber(entry && entry.id);

            if ((id > 0) && entry.name) {
                names[id] = String(entry.name);
            }
        });

        ['pinned', 'recent'].forEach(function (name) {
            var wrap = self.box.querySelector('[data-explorer-side-list="' + name + '"]');
            var items = wrap ? wrap.querySelector('[data-explorer-side-items]') : null;

            if (!items) {
                return;
            }

            items.textContent = '';

            lists[name].forEach(function (id) {
                if (!names[id]) {
                    return;
                }

                var line = el('div', 'tree-row');

                line.appendChild(el('span', 'tree-toggle'));
                line.appendChild(self.link(id, icon, names[id], ''));
                items.appendChild(line);
            });

            wrap.classList.toggle('d-none', !items.childNodes.length);
        });
    };

    Tree.prototype.start = function () {
        var self = this;

        if (this.started || !this.rootList || !this.api) {
            return;
        }

        this.started = true;
        this.drawWait(this.rootList);

        // The top, the branches left open and the Pinned and Recent ids to
        // check, in as few requests as the limits allow.
        var open = [0].concat(Object.keys(this.open).map(wholeNumber).filter(function (id) {
            return id > 0;
        }));
        var lists = this.shortcutLists();
        var shortcutIds = lists.pinned.concat(lists.recent).filter(function (id, index, all) {
            return all.indexOf(id) === index;
        }).slice(0, SHORTCUTS_PER_REQUEST);

        this.waiting.push({
            ids: [0],
            done: function () {
                self.fill(self.rootList, 0);
            },
            failed: function (message) {
                self.started = false;
                self.drawError(self.rootList, message, function () {
                    self.start();
                });
            }
        });

        this.send(open.slice(0, NODES_PER_REQUEST), shortcutIds).then(function (json) {
            self.drawShortcuts(lists, json.shortcuts);
        }, function () {
            // Told through the waiting list.
        });

        if (open.length > NODES_PER_REQUEST) {
            this.ask(open.slice(NODES_PER_REQUEST), function () {});
        }
    };

    Array.prototype.forEach.call(document.querySelectorAll('[data-explorer-side]:not([data-explorer-side-inplace])'), function (box) {
        var tree = new Tree(box);

        if (!tree.panel) {
            return;
        }

        tree.panel.addEventListener('pg:menu-panel-open', function () {
            tree.start();
        });

        if (tree.panel.classList.contains('is-open')) {
            tree.start();
        }
    });
})();
