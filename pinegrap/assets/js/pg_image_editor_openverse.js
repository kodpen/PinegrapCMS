/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: Free photos (Openverse) in Import. Openverse needs no key,
 * so the item is offered wherever Import is, on a site without an Unsplash
 * key too. It opens a box over the editor (as From the site does without
 * the picker): a search field, the photos as tiles with their creator's
 * name under each, and More photos for the next page. Only CC0 and public
 * domain photos are asked for and kept (M.openverseList()).
 *
 * A picked photo's pixels are fetched from Openverse's own thumbnail proxy
 * at full size (it answers CORS; the plain thumbnail when the full size
 * fails) and become a picture layer with a data: address, kept by a save as
 * the site's file like any inserted picture. The layer keeps the record's
 * credit (title, creator, licence, original page), shown first in its
 * panel. The browser asks api.openverse.org itself, so the panel's built-in
 * content policy lists that host (waf_csp_policy()).
 *
 * The model's OPENVERSE section holds the addresses, the records, the layer
 * and the credit. A part of window.PgImageEditor bundled after Unsplash's
 * (docs/memory/ui/image-editor-parts.md).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */

(function (X) {
    'use strict';

    var Editor = X.Editor, M = X.M, el = X.el, button = X.button;
    var t = function (key) { return X.t(key); };

    // A search's answer read through the model; a refusal names why (busy
    // for Openverse's rate limit).
    function ask(url) {
        return window.fetch(url, { headers: { Accept: 'application/json' }, credentials: 'omit' }).then(function (r) {
            if (!r.ok) {
                throw new Error((r.status === 429) ? 'busy' : 'error');
            }

            return r.json();
        }).then(M.openverseList);
    }

    // The photo's pixels as a picture file: the full size, else the plain
    // thumbnail.
    function pixels(info) {
        var get = function (url) {
            return window.fetch(url, { credentials: 'omit' }).then(function (r) {
                if (!r.ok) {
                    throw new Error('pixels');
                }

                return r.blob();
            }).then(function (blob) {
                if (!/^image\//.test(blob.type || '')) {
                    throw new Error('pixels');
                }

                return blob;
            });
        };

        return get(info.full).catch(function () { return get(info.thumb); });
    }

    Editor.prototype.openOpenverse = function () {
        var self = this, st = this.openverse, timer = null;
        var find = el('input', { type: 'search', class: 'pgie-input pgie-stkfind', value: st.search, placeholder: t('openverseFind'), 'aria-label': t('openverseFind') });
        var close = button('pgie-ibtn', t('close'), 'close', function () { self.closeConfirm(); });

        st.grid = el('div', { class: 'pgie-stkfiles pgie-importfiles pgie-ovtiles', 'aria-live': 'polite' });
        st.moreBtn = el('button', { type: 'button', class: 'pgie-btn pgie-ovmore', text: t('openverseMore'), hidden: 'hidden', onclick: function () {
            self.loadOpenverse(st.search, st.page + 1);
        } });
        find.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () { self.loadOpenverse(find.value, 1); }, 500);
        });

        this.confirmBox = el('div', { class: 'pgie-confirm', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'pgie-ov-title' }, [
            el('div', { class: 'pgie-confirm-box pgie-importbox' }, [
                el('div', { class: 'pgie-importhead' }, [el('h2', { id: 'pgie-ov-title', text: t('openverseImport') }), close]),
                find,
                el('p', { class: 'pgie-note', text: t('openverseNote') }),
                st.grid,
                st.moreBtn
            ])
        ]);
        this.confirmReturn = this.importBtn;
        this.root.appendChild(this.confirmBox);
        this.loadOpenverse(st.search, 1);
        find.focus();
    };

    // Page `page` of the search; the first page replaces the tiles, a next
    // one adds to them. An answer to an older search is dropped.
    Editor.prototype.loadOpenverse = function (search, page) {
        var self = this, st = this.openverse, url = M.openverseListUrl(search, page), serial = ++st.serial;

        st.search = String(search || '');
        st.page = page;

        if (page === 1) {
            st.grid.textContent = '';
        }

        st.moreBtn.hidden = true;

        if (!url) {
            return;
        }

        st.moreBtn.disabled = true;
        ask(url).then(function (list) {
            if (serial === st.serial) {
                self.showOpenverse(list, page);
            }
        }, function (e) {
            if (serial === st.serial) {
                self.showOpenverse(null, page, (e && (e.message === 'busy')) ? 'openverseBusy' : 'openverseError');
            }
        });
    };

    Editor.prototype.showOpenverse = function (list, page, error) {
        var self = this, st = this.openverse, grid = st.grid;

        if (!grid || !this.confirmBox || !this.confirmBox.contains(grid)) {
            return;
        }

        st.moreBtn.disabled = false;

        if (!list || ((page === 1) && !list.photos.length)) {
            grid.appendChild(el('p', { class: 'pgie-note', text: t(list ? 'openverseNone' : error) }));

            return;
        }

        list.photos.forEach(function (p) {
            var name = p.creator || t('openverseUnknown');

            grid.appendChild(el('button', { type: 'button', class: 'pgie-stkfile pgie-ovtile', title: p.title || name, 'aria-label': (p.title ? p.title + ', ' : '') + name, onclick: function () {
                self.closeConfirm();
                self.placeOpenverse(p);
            } }, [
                el('img', { src: p.thumb, alt: '', loading: 'lazy', decoding: 'async', draggable: 'false' }),
                el('span', { class: 'pgie-ovname', text: name })
            ]));
        });

        st.moreBtn.hidden = !list.more;
    };

    // The picked photo as a selected picture layer in the middle of the
    // view, one undo step.
    Editor.prototype.placeOpenverse = function (info) {
        var self = this;
        var fail = function () {
            if (!self.finished) {
                self.showMessage(t('openversePlaceError'), true);
            }
        };

        pixels(info).then(function (blob) {
            return self.readPicture(blob);
        }).then(function (pic) {
            return self.picture(pic.src).then(function () { return pic; });
        }).then(function (pic) {
            var l = self.finished ? null : M.openverseLayer(info, pic.src, pic.w, pic.h, self.viewRect());

            if (!l) {
                fail();

                return;
            }

            self.toSelect();
            self.addLayers([l], 0);
        }).catch(fail);
    };

    Editor.importItems.push(function () {
        var self = this;

        return [t('openverseImport'), function () { self.openOpenverse(); }];
    });

    Editor.inits.push(function () {
        this.openverse = { search: '', page: 1, serial: 0, grid: null, moreBtn: null };
    });

    // A link of the credit, opened beside the editor; plain text without
    // an address.
    function link(href, text) {
        return href ? el('a', { href: href, target: '_blank', rel: 'noopener noreferrer', text: text }) : document.createTextNode(text);
    }

    // "Photo by {creator} via Openverse", then the licence and the original
    // page, each linked where the record gave an address.
    function creditLine(c) {
        var words = t('openverseCredit').split('{var:1}');
        var parts = [words[0], link(c.creatorUrl, c.creator || t('openverseUnknown')), words.slice(1).join(''), ' · ',
            link(c.licenseUrl, t((c.license === 'cc0') ? 'openverseCc0' : 'openversePdm'))];

        if (c.page) {
            parts.push(' · ', link(c.page, t('openversePage')));
        }

        return el('p', { class: 'pgie-note pgie-credit' }, parts.filter(function (part) { return part !== ''; }));
    }

    // The credit comes first in the panel of one picture layer that has one.
    var own = Editor.layerTypes.image.panel;

    Editor.layerTypes.image.panel = function (body, layers) {
        var credit = (layers.length === 1) ? M.openverseCredit(layers[0]) : null;

        if (credit) {
            body.appendChild(creditLine(credit));
        }

        if (own) {
            own.call(this, body, layers);
        }
    };
}(window.PgImageEditor.parts));
