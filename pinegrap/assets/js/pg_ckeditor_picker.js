/**
 * Pinegrap - Enterprise Website Platform
 *
 * The rich text editor's bridge to the picker (CKEditor 4): the Link dialog's
 * Browse Server opens the page dialog (pgPicker, kind page), with the short
 * links as its own entries for a manager, and a File button beside it opens
 * the file dialog; the Image dialog's Browse Server opens the picture dialog
 * (kind image) and an Unsplash button the Unsplash dialog (pgUnsplash). The
 * pick is written as an address into the dialog's URL field once the picker
 * has closed. The picker knows no editor; this file is the one that knows
 * both.
 *
 * Without pgPicker and pgUI (a site page, where the panel's scripts are not
 * loaded) the dialogs keep CKEditor's own Browse Server window.
 * #pg_editor_bridge_config (pg_ckeditor_picker_script(), includes/fn/editor.php)
 * holds the site's path, the texts, whether short links are offered and the
 * Unsplash key.
 *
 * pgCkeditorPicker.pure holds the helpers without the DOM
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

    // A page or file name as a path, as encode_url_path() writes it: every
    // character rawurlencode() encodes, the slashes kept.
    function encodePath(name) {
        return encodeURIComponent(String(name || '')).replace(/[!'()*]/g, function (c) {
            return '%' + c.charCodeAt(0).toString(16).toUpperCase();
        }).replace(/%2F/g, '/');
    }

    // The address a pick goes into the content as: a page, a file or a
    // picture by its name, a short link (an entry of the page dialog's own
    // list) by its value, the short link's name. '' for nothing picked.
    function pickAddress(item, path) {
        if (!item) {
            return '';
        }
        var name = item.extra ? item.value : item.text;
        return (name === undefined || name === null || String(name) === '') ? '' : String(path || '/') + encodePath(name);
    }

    // The value the dialog opens on: the address in the URL field when it
    // is one of the site's own, as a name (a page by name is looked up as
    // the typed text, since the page kind stores ids).
    function nameFromAddress(url, path) {
        url = String(url || '');
        path = String(path || '/');
        if (url.indexOf(path) !== 0) {
            return '';
        }
        var name = url.slice(path.length).split(/[?#]/)[0];
        try {
            name = decodeURIComponent(name);
        } catch (e) {
            return '';
        }
        return name.indexOf('/') === -1 ? name : '';
    }

    // Finds an element definition by id in a dialog tab's definition, with
    // the list that holds it, through the hbox and vbox children.
    function findElement(elements, id) {
        for (var i = 0; elements && i < elements.length; i++) {
            if (elements[i] && elements[i].id === id) {
                return { list: elements, index: i, element: elements[i] };
            }
            var found = elements[i] && findElement(elements[i].children, id);
            if (found) {
                return found;
            }
        }
        return null;
    }

    var pure = { encodePath: encodePath, pickAddress: pickAddress, nameFromAddress: nameFromAddress, findElement: findElement };

    var doc = root.document;

    if (!doc || !doc.createElement) {
        root.pgCkeditorPicker = { pure: pure };
        return;
    }

    var config = null;

    function conf() {
        if (!config) {
            var node = doc.getElementById('pg_editor_bridge_config');
            try {
                config = JSON.parse(node ? node.textContent : '{}') || {};
            } catch (e) {
                config = {};
            }
            config.texts = config.texts || {};
        }
        return config;
    }

    function t(key) {
        var text = conf().texts[key];
        return text === undefined ? key : text;
    }

    function usable() {
        return !!(root.pgPicker && root.pgPicker.open && root.pgUI && doc.getElementById('pg_editor_bridge_config'));
    }

    // The short links, asked once per page.
    var shortLinks = null;

    function loadShortLinks() {
        if (!conf().short_links || !conf().endpoint) {
            return Promise.resolve([]);
        }
        if (!shortLinks) {
            shortLinks = root.fetch(conf().endpoint + '?kind=page&request=short_links', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                .then(function (response) { return response.ok ? response.json() : {}; })
                .then(function (body) { return Array.isArray(body.items) ? body.items : []; })
                .catch(function () { shortLinks = null; return []; });
        }
        return shortLinks;
    }

    // Writes an address into a dialog field and keeps the dialog on that tab.
    function writeUrl(dialog, tab, field, url) {
        if (!url || !dialog) {
            return;
        }
        var element = dialog.getContentElement(tab, field);
        if (!element) {
            return;
        }
        element.setValue(url);
        dialog.selectPage(tab);
        element.focus();
    }

    function browseLink(dialog) {
        var current = nameFromAddress(dialog.getValueOf('info', 'url'), conf().path);
        loadShortLinks().then(function (links) {
            return root.pgPicker.open({
                kind: 'page', title: t('Choose a page'), query: current,
                extra: links, extraLabel: t('Short links')
            });
        }).then(function (item) {
            if (!item) {
                return;
            }
            // A page, a file and a short link are all addresses of the site:
            // the link type is URL and the protocol is the site's own.
            if (dialog.getContentElement('info', 'linkType')) {
                dialog.setValueOf('info', 'linkType', 'url');
            }
            writeUrl(dialog, 'info', 'url', pickAddress(item, conf().path));
        });
    }

    function browseFile(dialog) {
        root.pgPicker.open({ kind: 'file', title: t('Choose a file') }).then(function (item) {
            if (!item) {
                return;
            }
            if (dialog.getContentElement('info', 'linkType')) {
                dialog.setValueOf('info', 'linkType', 'url');
            }
            writeUrl(dialog, 'info', 'url', pickAddress(item, conf().path));
        });
    }

    function browseImage(dialog) {
        var current = nameFromAddress(dialog.getValueOf('info', 'txtUrl'), conf().path);
        root.pgPicker.open({ kind: 'image', title: t('Choose a picture'), value: current || undefined }).then(function (item) {
            writeUrl(dialog, 'info', 'txtUrl', pickAddress(item, conf().path));
        });
    }

    function browseUnsplash(dialog) {
        root.pgUnsplash.open({ key: conf().unsplash, texts: conf().texts }).then(function (url) {
            writeUrl(dialog, 'info', 'txtUrl', url);
        });
    }

    // Runs after the filebrowser plugin (priority 10), which shows Browse
    // Server and hands it its window: the click is taken over here.
    function onDefinition(event) {
        if (!usable() || !event.data || !event.data.definition) {
            return;
        }
        var name = event.data.name;
        var tab = event.data.definition.getContents('info');
        if (!tab || ((name !== 'link') && (name !== 'image'))) {
            return;
        }
        var found = findElement(tab.elements, 'browse');
        if (!found) {
            return;
        }
        var browse = found.element;
        browse.hidden = false;

        if (name === 'link') {
            browse.label = t('Page…');
            browse.onClick = function () { browseLink(this.getDialog()); };
            found.list.splice(found.index + 1, 0, {
                type: 'button', id: 'pgBrowseFile', label: t('File…'),
                onClick: function () { browseFile(this.getDialog()); }
            });
            return;
        }

        browse.label = t('Picture…');
        browse.onClick = function () { browseImage(this.getDialog()); };
        if (conf().unsplash && root.pgUnsplash) {
            found.list.splice(found.index + 1, 0, {
                type: 'button', id: 'pgBrowseUnsplash', label: 'Unsplash…', style: browse.style,
                onClick: function () { browseUnsplash(this.getDialog()); }
            });
        }
    }

    function hook() {
        if (root.CKEDITOR && root.CKEDITOR.on && !root.CKEDITOR.pgPickerBridge) {
            root.CKEDITOR.pgPickerBridge = true;
            root.CKEDITOR.on('dialogDefinition', onDefinition, null, null, 20);
        }
    }

    hook();
    if (!root.CKEDITOR) {
        doc.addEventListener('DOMContentLoaded', hook);
    }

    root.pgCkeditorPicker = { pure: pure };
})(typeof window !== 'undefined' ? window : this);
