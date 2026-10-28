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
 * group on its explorer screen. Which branches are open, and the Pinned and
 * Recent lists, are the ones the explorer keeps in this browser for the area.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // How many branches one request may ask for (pg_explorer_side_ids()).
    var NODES_PER_REQUEST = 25;
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
            toggle.setAttribute('aria-label', this.box.getAttribute(opened ? 'data-explorer-side-close' : 'data-explorer-side-expand') || '');
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
            this.ask([id], function () {
                if (self.rows[id]) {
                    self.fill(list, id);
                }
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
        toggle.setAttribute('aria-label', this.box.getAttribute(opened ? 'data-explorer-side-close' : 'data-explorer-side-expand') || '');

        if (opened) {
            this.open[id] = true;
            this.fill(list, id);
        } else {
            delete this.open[id];
        }

        storageSet(this.openKey, JSON.stringify(this.open));
    };

    // Asks for the branches not in yet, those not asked for already in one
    // request; done runs once all of them are in, whichever request brought
    // them.
    Tree.prototype.ask = function (ids, done, failed) {
        var self = this;
        var missing = ids.filter(function (id) {
            return !self.rows[id];
        }).slice(0, NODES_PER_REQUEST);

        if (!missing.length) {
            done();
            return;
        }

        this.waiting.push({ ids: missing, done: done });

        var wanted = missing.filter(function (id) {
            return !self.asked[id];
        });

        if (!wanted.length || !this.api) {
            return;
        }

        wanted.forEach(function (id) {
            self.asked[id] = true;
        });

        fetch(this.api, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'explorer_side',
                token: (typeof window.software_token !== 'undefined') ? window.software_token : '',
                area: this.area,
                node_ids: wanted
            })
        }).then(function (response) {
            return response.json();
        }).then(function (json) {
            if (!json || (json.status !== 'success') || !Array.isArray(json.nodes)) {
                throw new Error((json && json.message) ? String(json.message) : '');
            }

            json.nodes.forEach(function (node) {
                self.rows[node.id] = Array.isArray(node.rows) ? node.rows : [];
            });

            self.flush();
        }).catch(function (error) {
            wanted.forEach(function (id) {
                delete self.asked[id];
            });

            // Whoever waited on these branches waits no more.
            self.waiting = self.waiting.filter(function (wait) {
                return !wait.ids.some(function (id) {
                    return wanted.indexOf(id) !== -1;
                });
            });

            if (failed) {
                failed(error && error.message ? error.message : '');
            }
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

    Tree.prototype.drawError = function (message) {
        var self = this;
        var li = el('li', 'pg-explorer-side-wait');
        var retry = el('button', 'btn btn-link btn-sm p-0 ms-2', this.box.getAttribute('data-explorer-side-retry') || '');

        li.appendChild(document.createTextNode(message || this.box.getAttribute('data-explorer-side-error') || ''));
        retry.type = 'button';
        retry.addEventListener('click', function () {
            self.started = false;
            self.start();
        });
        li.appendChild(retry);

        this.rootList.textContent = '';
        this.rootList.appendChild(li);
    };

    // Pinned and Recent, as the explorer keeps them for this area.
    Tree.prototype.drawShortcuts = function () {
        var self = this;
        var icon = (this.area === 'catalog') ? 'bi bi-boxes fg-primary' : 'bi bi-folder-fill fg-primary';

        ['pinned', 'recent'].forEach(function (name) {
            var wrap = self.box.querySelector('[data-explorer-side-list="' + name + '"]');
            var items = wrap ? wrap.querySelector('[data-explorer-side-items]') : null;
            var list = readJson('pg_explorer_' + name + '_' + self.area, []);

            if (!items || !Array.isArray(list)) {
                return;
            }

            if (name === 'recent') {
                list = list.slice(0, RECENT_LIMIT);
            }

            items.textContent = '';

            list.forEach(function (entry) {
                var id = wholeNumber(entry && entry.id);

                if ((id < 0) || !entry.name) {
                    return;
                }

                var line = el('div', 'tree-row');

                line.appendChild(el('span', 'tree-toggle'));
                line.appendChild(self.link(id, icon, String(entry.name), ''));
                items.appendChild(line);
            });

            wrap.classList.toggle('d-none', !items.childNodes.length);
        });
    };

    Tree.prototype.start = function () {
        var self = this;

        if (this.started || !this.rootList) {
            return;
        }

        this.started = true;
        this.drawShortcuts();

        // The top, and the branches left open, in one request.
        var ids = [0].concat(Object.keys(this.open).map(wholeNumber).filter(function (id) {
            return id > 0;
        }));

        this.ask(ids, function () {
            self.fill(self.rootList, 0);
        }, function (message) {
            self.started = false;
            self.drawError(message);
        });
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
