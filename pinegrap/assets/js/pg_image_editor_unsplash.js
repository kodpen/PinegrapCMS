/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: From Unsplash in Import. With the site's Unsplash key, on a
 * panel screen, Import's menu offers the photo search the rich text editor
 * has (pgUnsplash, in pg_editor_bridge.min.js, loaded the first time it is
 * used). A picked photo becomes a picture layer that keeps the address the
 * API returned and the photographer's credit, not a files row; the dialog
 * tells Unsplash of the download when the photo is picked. The layer's
 * panel shows "Photo by {name} on Unsplash" with both links, as the
 * Unsplash API guidelines ask. A save draws the photo into the saved
 * picture like any layer (the picture is loaded with CORS,
 * M.corsPicture()).
 *
 * The model's UNSPLASH section holds the layer and the credit. A part of
 * window.PgImageEditor bundled after the frames
 * (docs/memory/ui/image-editor-parts.md).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */

(function (X) {
    'use strict';

    var Editor = X.Editor, M = X.M, el = X.el;
    var t = function (key) { return X.t(key); };

    // The site's key and the dialog's script and words; null without a key
    // or off the panel, where the dialog layer (pgUI) is missing.
    function unsplashConfig() {
        var c = X.config().unsplash;

        return (c && c.key && window.pgUI && window.pgUI.dialog) ? c : null;
    }

    // The dialog's script, loaded once: the editor's bridge may have
    // brought it already.
    Editor.prototype.unsplashScript = function () {
        var c = unsplashConfig();

        if (window.pgUnsplash && window.pgUnsplash.open) {
            return Promise.resolve();
        }

        return (c.scripts || []).reduce(function (p, url) {
            return p.then(function () {
                return new Promise(function (resolve, reject) {
                    var tag = document.createElement('script');

                    tag.src = url;
                    tag.onload = resolve;
                    tag.onerror = reject;
                    document.head.appendChild(tag);
                });
            });
        }, Promise.resolve()).then(function () {
            if (!window.pgUnsplash || !window.pgUnsplash.open) {
                throw new Error('pgUnsplash');
            }
        });
    };

    // The search; a picked photo becomes a selected picture layer in the
    // middle of the view, one undo step.
    Editor.prototype.openUnsplash = function () {
        var self = this, c = unsplashConfig();
        var fail = function () {
            if (!self.finished) {
                self.showMessage(t('unsplashError'), true);
            }
        };

        this.unsplashScript().then(function () {
            return window.pgUnsplash.open({ key: c.key, texts: c.texts || {}, photo: true });
        }).then(function (info) {
            if (!info || self.finished) {
                return;
            }

            self.picture(info.src).then(function (img) {
                var l = M.unsplashLayer(info, img.naturalWidth || info.width, img.naturalHeight || info.height, self.viewRect());

                if (!l || self.finished) {
                    fail();

                    return;
                }

                self.toSelect();
                self.addLayers([l], 0);
            }, fail);
        }, fail);
    };

    Editor.importItems.push(function () {
        var self = this;

        return unsplashConfig() ? [t('unsplashImport'), function () { self.openUnsplash(); }] : null;
    });

    // A link of the credit, opened beside the editor.
    function link(href, text) {
        return el('a', { href: href, target: '_blank', rel: 'noopener', text: text });
    }

    // "Photo by {name} on Unsplash": the name links to the photographer's
    // profile, Unsplash to the photo's page.
    function creditLine(credit) {
        var words = t('unsplashCredit').split('{var:1}'), rest = words.slice(1).join(''), at = rest.lastIndexOf('Unsplash');
        var parts = [words[0], link(credit.profile, credit.name)];

        if (at < 0) {
            parts.push(rest);
        } else {
            parts.push(rest.slice(0, at), link(credit.page, 'Unsplash'), rest.slice(at + 8));
        }

        return el('p', { class: 'pgie-note pgie-credit' }, parts.filter(function (part) { return part !== ''; }));
    }

    // The credit comes first in the panel of one picture layer that has one,
    // before any other part's controls of the type.
    var own = Editor.layerTypes.image.panel;

    Editor.layerTypes.image.panel = function (body, layers) {
        var credit = (layers.length === 1) ? M.pictureCredit(layers[0]) : null;

        if (credit) {
            body.appendChild(creditLine(credit));
        }

        if (own) {
            own.call(this, body, layers);
        }
    };
}(window.PgImageEditor.parts));
