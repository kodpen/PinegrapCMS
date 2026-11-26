/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: the site colours. Every colour control (X.swatches) starts
 * with a row labelled "Site colours": the palette colours of the site's
 * visual designs, then their colour variables, at most twelve, each swatch
 * titled with its name. The list is asked once a page (action site_colors)
 * when an editor opens; a site whose designs set no colour shows no row.
 *
 * A part of window.PgImageEditor, bundled before the selection bar and the
 * menu (pg_js_bundles()); it hangs its row on the core's swatchRows
 * (docs/memory/ui/image-editor-parts.md). The answer is checked by the
 * model's M.siteColors().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */

(function (X) {
    'use strict';

    var Editor = X.Editor, M = X.M, t = X.t, el = X.el;

    // The site's colours: null until the answer comes, then the checked list.
    // Rows drawn before it wait in `waiting` and are filled when it comes.
    var list = null, asked = false, waiting = [];

    // The swatches of one row, `now` the control's chosen colour.
    function fill(holder, now, pick) {
        var row = el('div', { class: 'pgie-swatches pgie-site-swatches', role: 'group', 'aria-label': t('siteColors') });

        list.forEach(function (c) {
            var on = (c.color === now);

            row.appendChild(el('button', { type: 'button', class: 'pgie-swatch' + (on ? ' on' : ''), style: 'background: ' + c.color,
                'aria-pressed': on ? 'true' : 'false', 'aria-label': c.name, title: c.name,
                onclick: function () { pick(c.color, false); } }));
        });

        holder.textContent = '';
        holder.appendChild(el('div', { class: 'pgie-site-lbl', text: t('siteColors') }));
        holder.appendChild(row);
        holder.hidden = false;
    }

    // The answer came: fill the rows still in a panel, drop the rest.
    function answered(colors) {
        list = colors;

        waiting.forEach(function (w) {
            if (!w.holder.isConnected) {
                return;
            }

            if (list.length) {
                fill(w.holder, w.now, w.pick);
            } else {
                w.holder.remove();
            }
        });

        waiting = [];
    }

    // Asked once a page, as the first editor opens.
    Editor.inits.push(function () {
        if (asked) {
            return;
        }

        asked = true;

        if (!X.config().api) {
            answered([]);

            return;
        }

        this.request({ action: 'site_colors' }).then(function (res) {
            answered(M.siteColors(res));
        }, function () {
            answered([]);
        });
    });

    X.swatchRows.push(function (now, pick) {
        var holder;

        if (list && !list.length) {
            return null;
        }

        holder = el('div', { class: 'pgie-site-colors' });

        if (list) {
            fill(holder, now, pick);
        } else {
            holder.hidden = true;
            waiting.push({ holder: holder, now: now, pick: pick });
        }

        return holder;
    });
}(window.PgImageEditor.parts));
