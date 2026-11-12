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
 * #pg_editor_bridge_config (pg_editor_bridge_script(), includes/fn/editor.php)
 * holds the site's path, the texts, whether short links are offered and the
 * Unsplash key.
 *
 * pgEditorPicker hands the same pickers to another editor (the visual
 * designer's link and picture fields): link() asks for a page or short link
 * or a file, image() for a picture of the site or from Unsplash, each a
 * promise of the address or null.
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

    // ── The pickers by what they hand back: an address ─────────────────────

    // A page or a short link; the dialog opens on the name in `current`.
    function pickPage(current) {
        var name = nameFromAddress(current, conf().path);
        return loadShortLinks().then(function (links) {
            return root.pgPicker.open({
                kind: 'page', title: t('Choose a page'), query: name,
                extra: links, extraLabel: t('Short links')
            });
        }).then(function (item) { return pickAddress(item, conf().path) || null; });
    }

    function pickFile() {
        return root.pgPicker.open({ kind: 'file', title: t('Choose a file') }).then(function (item) {
            return pickAddress(item, conf().path) || null;
        });
    }

    function pickPicture(current) {
        var name = nameFromAddress(current, conf().path);
        return root.pgPicker.open({ kind: 'image', title: t('Choose a picture'), value: name || undefined }).then(function (item) {
            return pickAddress(item, conf().path) || null;
        });
    }

    function pickUnsplash() {
        return root.pgUnsplash.open({ key: conf().unsplash, texts: conf().texts });
    }

    // A small dialog asking where to choose from: choices [{key, label,
    // icon}] -> Promise of the key or null, once it has closed.
    function chooseSource(title, choices) {
        return new Promise(function (resolve) {
            var picked = null;
            var parts = root.pgUI.dialog.build({ size: 'sm', title: title, closeLabel: t('Close'), className: 'pg-source-choice' });
            var list = doc.createElement('div');
            list.className = 'd-grid gap-3';
            choices.forEach(function (choice) {
                var button = doc.createElement('button');
                button.type = 'button';
                button.className = 'btn btn-outline theme-secondary text-start';
                var icon = doc.createElement('span');
                icon.className = 'bi ' + choice.icon + ' me-3';
                icon.setAttribute('aria-hidden', 'true');
                var label = doc.createElement('span');
                label.className = 'pg-btn-label';
                label.textContent = choice.label;
                button.appendChild(icon);
                button.appendChild(label);
                button.addEventListener('click', function () {
                    picked = choice.key;
                    root.pgUI.dialog.hide(parts.root);
                });
                list.appendChild(button);
            });
            parts.body.appendChild(list);
            var cancel = doc.createElement('button');
            cancel.type = 'button';
            cancel.className = 'btn btn-outline theme-secondary';
            cancel.textContent = t('Cancel');
            cancel.addEventListener('click', function () { root.pgUI.dialog.hide(parts.root); });
            parts.footer.appendChild(cancel);
            doc.body.appendChild(parts.root);
            root.pgUI.on(parts.root, 'dialog', 'hidden', function () {
                parts.root.remove();
                resolve(picked);
            }, { once: true });
            root.pgUI.dialog.show(parts.root).then(function () {
                var first = list.querySelector('button');
                if (first) {
                    first.focus();
                }
            });
        });
    }

    // A link: a page or a short link, or a file, asked first.
    function pickLink(current) {
        return chooseSource(t('Choose a link'), [
            { key: 'page', label: t('A page or a short link'), icon: 'bi-file-earmark-text' },
            { key: 'file', label: t('A file'), icon: 'bi-paperclip' }
        ]).then(function (key) {
            return key === 'page' ? pickPage(current) : (key === 'file' ? pickFile() : null);
        });
    }

    // A picture: from the site, or from Unsplash when the site has a key.
    function pickImage(current) {
        if (!conf().unsplash || !root.pgUnsplash) {
            return pickPicture(current);
        }
        return chooseSource(t('Choose a picture'), [
            { key: 'site', label: t('From the site'), icon: 'bi-images' },
            { key: 'unsplash', label: 'Unsplash', icon: 'bi-image' }
        ]).then(function (key) {
            return key === 'site' ? pickPicture(current) : (key === 'unsplash' ? pickUnsplash() : null);
        });
    }

    // ── CKEditor ──────────────────────────────────────────────────────────

    // A page, a file and a short link are all addresses of the site: the
    // link type is URL and the protocol is the site's own.
    function writeLink(dialog, url) {
        if (!url) {
            return;
        }
        if (dialog.getContentElement('info', 'linkType')) {
            dialog.setValueOf('info', 'linkType', 'url');
        }
        writeUrl(dialog, 'info', 'url', url);
    }

    function browseLink(dialog) {
        pickPage(dialog.getValueOf('info', 'url')).then(function (url) { writeLink(dialog, url); });
    }

    function browseFile(dialog) {
        pickFile().then(function (url) { writeLink(dialog, url); });
    }

    function browseImage(dialog) {
        pickPicture(dialog.getValueOf('info', 'txtUrl')).then(function (url) {
            writeUrl(dialog, 'info', 'txtUrl', url);
        });
    }

    function browseUnsplash(dialog) {
        pickUnsplash().then(function (url) {
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

    // The same pickers for another editor (the visual designer): each a
    // promise of an address or null; usable() says whether they can open.
    root.pgEditorPicker = {
        usable: usable, link: pickLink, page: pickPage, file: pickFile,
        image: pickImage, picture: pickPicture
    };
})(typeof window !== 'undefined' ? window : this);
