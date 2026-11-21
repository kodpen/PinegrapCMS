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
 * @param string $foot buttons kept in view under the scrolling body, as markup
 * @param string $line a line under the title (an address, a state pill), as markup
 * @param string $bar  a band under the head that does not scroll (the
 *                     segmented control of the drawer's tabs), as markup
 * @return string
 */
function pg_settings_drawer($id, $title, $body, $in_dialog, $foot = '', $line = '', $bar = '')
{
    $id = (string) $id;
    $close = lang('Close');
    $under = ($line !== '') ? '<p class="small fg-2 mb-0 mt-1 d-flex flex-wrap align-items-center gap-3">' . $line . '</p>' : '';

    if ($in_dialog) {
        return '
                <div class="pg-sm-drawer" id="' . h($id) . '" role="dialog" aria-modal="true" aria-labelledby="' . h($id . '_title') . '" tabindex="-1">
                    <div class="pg-sm-drawer-head">
                        ' . (($under !== '') ? '<div class="flex-grow-1 min-width-0">' : '') . '<h3 class="pg-sm-drawer-title" id="' . h($id . '_title') . '">' . $title . '</h3>' . (($under !== '') ? $under . '</div>' : '') . '
                        <button type="button" class="btn-close" data-pgdrawer-close aria-label="' . h($close) . '" title="' . h($close) . '"></button>
                    </div>' . (($bar !== '') ? '
                    <div class="pg-sm-drawer-bar">' . $bar . '</div>' : '') . '
                    <div class="pg-sm-drawer-body">' . $body . '</div>' . (($foot !== '') ? '
                    <div class="pg-sm-drawer-foot">' . $foot . '</div>' : '') . '
                </div>';
    }

    return '
                <dialog class="drawer drawer-end pg-set-drawer" id="' . h($id) . '" aria-labelledby="' . h($id . '_title') . '">
                    <div class="drawer-header border-bottom">
                        ' . (($under !== '') ? '<div class="flex-grow-1 min-width-0">' : '') . '<h5 class="drawer-title" id="' . h($id . '_title') . '">' . $title . '</h5>' . (($under !== '') ? $under . '</div>' : '') . '
                        <button type="button" class="btn-close" data-bs-dismiss="drawer" aria-label="' . h($close) . '"></button>
                    </div>' . (($bar !== '') ? '
                    <div class="pg-sm-drawer-bar border-bottom">' . $bar . '</div>' : '') . '
                    <div class="drawer-body">' . $body . '</div>' . (($foot !== '') ? '
                    <div class="drawer-footer pg-set-drawer-foot">' . $foot . '</div>' : '') . '
                </dialog>';
}


/**
 * The drawer holding the box a refused save marked, named by the save module
 * so the pane opens it again: the box cannot be seen or fixed in a shut
 * drawer. The first module's call wins; a call without an id only reads.
 *
 * @param string|null $id the drawer's element id; '' names none
 * @return string the drawer named so far, '' for none
 */
function pg_settings_drawer_refused($id = null)
{
    static $drawer = '';

    if (($drawer === '') && ($id !== null)) {
        $drawer = (string) $id;
    }

    return $drawer;
}
