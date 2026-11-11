<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - a card's drawer: what a card opens beside its fields (a long
 * explanation, a form that has to stand alone) when it does not fit in the
 * card.
 *
 * Inside the Site Settings dialog it is a panel of the dialog that slides in
 * from its right edge over the pane, never a second dialog in the top layer: a
 * second dialog can be scrolled past, closes the settings with it when
 * Bootstrap swaps dialogs, and lets the unsaved pane be left by accident. The
 * settings_<key>.php page has no outer dialog, so there the same content is
 * Bootstrap's own drawer, opened by its data-api.
 *
 * A card hands its drawer over with the markup it prints beside the pane:
 *
 *     $pg_settings_modals[] = pg_settings_drawer('cron_jobs', $title, $body, !empty($pg_settings_in_dialog));
 *
 * and opens it with data-bs-toggle="drawer" data-bs-target="#cron_jobs", which
 * pg_settings_own_dialog_triggers() renames inside the dialog.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}


/**
 * The markup of one drawer.
 *
 * Inside the dialog the panel starts shut and the dialog's script opens it
 * (pgSettingsModal in backend.src.js): its Close is the script's, because
 * Bootstrap's data-bs-dismiss would find the settings dialog and close that.
 *
 * @param string $id the element id the card's button names
 * @param string $title the heading, as markup
 * @param string $body the content, as markup
 * @param bool $in_dialog true when drawn inside the Site Settings dialog
 * @return string
 */
function pg_settings_drawer($id, $title, $body, $in_dialog)
{
    $id = (string) $id;
    $close = lang('Close');

    if ($in_dialog) {
        return '
                <div class="pg-sm-drawer" id="' . h($id) . '" role="dialog" aria-modal="true" aria-labelledby="' . h($id . '_title') . '" tabindex="-1">
                    <div class="pg-sm-drawer-head">
                        <h3 class="pg-sm-drawer-title" id="' . h($id . '_title') . '">' . $title . '</h3>
                        <button type="button" class="btn-close" data-pgdrawer-close aria-label="' . h($close) . '" title="' . h($close) . '"></button>
                    </div>
                    <div class="pg-sm-drawer-body">' . $body . '</div>
                </div>';
    }

    return '
                <dialog class="drawer drawer-end pg-set-drawer" id="' . h($id) . '" aria-labelledby="' . h($id . '_title') . '">
                    <div class="drawer-header border-bottom">
                        <h5 class="drawer-title" id="' . h($id . '_title') . '">' . $title . '</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="drawer" aria-label="' . h($close) . '"></button>
                    </div>
                    <div class="drawer-body">' . $body . '</div>
                </dialog>';
}
