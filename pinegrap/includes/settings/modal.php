<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The settings modal: its shell, printed on every panel screen.
 *
 * Site Settings stopped being a place you navigate to. The button sits in the
 * header beside the notifications, and the settings open over whatever screen
 * the operator is on -- a setting is almost always something you need in the
 * middle of another job, and going to a page for it means losing the job.
 *
 * Only the shell is printed here: the sidebar (which is the one list from
 * registry.php) and the empty pane. The cards themselves arrive from
 * settings_pane.php when a category is chosen, so a screen that never opens the
 * settings pays for a few hundred bytes of markup and nothing else.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}


/**
 * The button that opens it, for the header.
 *
 * @return string
 */
function pg_settings_modal_button()
{
    if (!defined('USER_ROLE') || ((int) USER_ROLE) > 2) {
        return '';
    }

    return '
                <ul class="navbar-nav">
                    <li class="nav-item no-popover" title="' . h(lang('Site Settings')) . '">
                        <button type="button" id="pg_settings_open" class="nav-link nav-link-sm no-popover" aria-label="' . h(lang('Site Settings')) . '">
                            <span class="bi bi-gear"></span>
                        </button>
                    </li>
                </ul>';
}


/**
 * The shell, for the footer.
 *
 * @param array $user the row validate_user() returned
 * @return string
 */
function pg_settings_modal_markup($user)
{
    if (!isset($user['role']) || ((int) $user['role']) > 2) {
        return '';
    }

    if (!defined('PG_SETTINGS_MENU')) {
        define('PG_SETTINGS_MENU', true);
    }

    include_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');

    // ── the categories ───────────────────────────────────────────────────
    $output_links = '';

    foreach (pg_settings_categories() as $key => $category) {

        $output_links .=
            '<button type="button" class="pg-sm-link" data-pane="' . h($key) . '"'
            . ' title="' . h($category['description']) . '">'
            . '<i class="bi ' . h($category['icon']) . '" aria-hidden="true"></i>'
            . '<span>' . h($category['label']) . '</span></button>';
    }

    // ── the neighbouring screens ─────────────────────────────────────────
    //
    // These are pages, not panes: they leave the modal, and the arrow on the
    // row says so. Same list and the same gates the Settings menu used to
    // apply, so nothing an operator could reach has gone missing.
    $output_tools = '';

    foreach (pg_settings_tool_groups($user) as $group) {

        $output_tools .= '<div class="pg-sm-group">' . h($group['label']) . '</div>';

        foreach ($group['items'] as $item) {

            $target  = isset($item['target']) ? ' target="' . h($item['target']) . '" rel="noopener"' : '';
            $confirm = isset($item['confirm']) ? ' data-confirm-content="' . h($item['confirm']) . '"' : '';

            $output_tools .=
                '<a class="pg-sm-link pg-sm-away" href="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/' . $item['url']) . '"'
                . $target . $confirm . '>'
                . '<i class="bi ' . h($item['icon']) . '" aria-hidden="true"></i>'
                . '<span>' . h($item['label']) . '</span>'
                . '<i class="bi bi-arrow-up-right pg-sm-out" aria-hidden="true"></i></a>';
        }
    }

    // Where an old #pgset- link should land, for the script that follows them.
    $output_anchors = array();

    foreach (pg_settings_legacy_anchors() as $old => $target) {
        $output_anchors[$old] = $target[0] . '|' . $target[1];
    }

    return '
<div class="modal fade pg-settings-modal" id="pg_settings_modal" tabindex="-1" aria-labelledby="pg_settings_modal_title" aria-hidden="true"
    data-pgurl="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/settings_pane.php') . '"
    data-pganchors="' . h(json_encode($output_anchors)) . '"
    data-pgleavetitle="' . h(lang('Unsaved Changes')) . '"
    data-pgleave="' . h(lang('The changes on this screen have not been saved. Close anyway?')) . '"
    data-pgdiscard="' . h(lang('Close Without Saving')) . '"
    data-pgstay="' . h(lang('Keep Editing')) . '"
    data-pgclose="' . h(lang('Close')) . '"
    data-pgfailed="' . h(lang('The request could not be completed. Please try again.')) . '"
    data-pgfilled="' . h(lang('A password manager filled in a field nobody was editing, so it has been put back.')) . '">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-lg-down">
        <div class="modal-content pg-sm">

            <aside class="pg-sm-side" id="pg_settings_modal_side">
                <div class="pg-sm-brand">
                    <i class="bi bi-gear" aria-hidden="true"></i>
                    <span>' . h(lang('Site Settings')) . '</span>
                </div>
                <nav class="pg-sm-nav" id="pg_settings_modal_nav" aria-label="' . h(lang('Site Settings')) . '">
                    ' . $output_links . '
                    ' . $output_tools . '
                </nav>
            </aside>

            <!-- Tapping beside the drawer closes it. Inert until it opens. -->
            <div class="pg-sm-scrim" id="pg_settings_modal_scrim"></div>

            <section class="pg-sm-main">
                <header class="pg-sm-head">
                    <button type="button" class="pg-sm-burger no-popover" id="pg_settings_modal_menu"
                        aria-controls="pg_settings_modal_side" aria-expanded="false"
                        aria-label="' . h(lang('Sections')) . '"><i class="bi bi-list" aria-hidden="true"></i></button>
                    <div class="pg-sm-heading">
                        <h2 class="pg-sm-title" id="pg_settings_modal_title" aria-live="polite">' . h(lang('Site Settings')) . '</h2>
                        <p class="pg-sm-desc" id="pg_settings_modal_desc">' . h(lang('All site-wide settings and defaults.')) . '</p>
                    </div>
                    <button type="button" class="btn-close no-popover" data-bs-dismiss="modal" aria-label="' . h(lang('Close')) . '"></button>
                </header>

                <!-- The only sign of life while a pane is on its way. -->
                <div class="pg-sm-progress" aria-hidden="true"></div>


                <!--
                    data-pg-no-curtain: the loading curtain goes up on any form
                    submit, from a capture-phase listener on the document that
                    runs before this form can cancel the submit. Nothing is
                    navigating, so nothing would take the curtain back down
                    until the operator touched the screen again.
                -->
                <form class="pg-sm-body" id="pg_settings_modal_form" method="post" autocomplete="off" novalidate="novalidate" data-pg-no-curtain>
                    <!--
                        No decoy fields here, and in particular no decoy password.
                        The single screen carried a hidden text input and a hidden
                        password input to absorb the browser\'s own guess, and that
                        password input is the loudest signal a form can give: it is
                        what tells a password manager "this is a sign-in form", and
                        Kaspersky answered it by writing the saved username into the
                        support address. Chrome\'s own autofill, which the decoys were
                        for, skips a read-only field -- and every text field in the
                        pane is held read-only until the operator reaches for it
                        (latch() in backend.src.js). The bait is not worth what it
                        attracts.
                    -->
                    <input type="hidden" name="pane" id="pg_settings_modal_key" value="" />
                    <input type="hidden" name="token" id="pg_settings_modal_token" value="' . h(isset($_SESSION['software']['token']) ? $_SESSION['software']['token'] : '') . '" />

                    <div class="pg-sm-notice" id="pg_settings_modal_notice" role="status" aria-live="polite"></div>

                    <div id="pg_settings_modal_pane" class="pg-settings-body" tabindex="-1">
                        <div class="pg-sm-start text-body-secondary text-center py-5">
                            <i class="bi bi-sliders d-block fs-1 mb-2 opacity-50" aria-hidden="true"></i>
                            ' . h(lang('Choose a section.')) . '
                        </div>
                    </div>
                </form>

                <footer class="pg-sm-foot">
                    <span class="pg-sm-meta small text-body-secondary" id="pg_settings_modal_meta"></span>
                    <div class="ms-auto d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-link link-body-emphasis text-decoration-none no-popover" data-bs-dismiss="modal">' . h(lang('Close')) . '</button>
                        <button type="button" class="btn btn-success btn-sm" id="pg_settings_modal_save" disabled="disabled"
                            data-loading-content="' . h(lang('Saving')) . '">
                            <i class="bi bi-save me-2" aria-hidden="true"></i><span class="btn-text">' . h(lang('Save')) . '</span>
                        </button>
                    </div>
                </footer>
            </section>

        </div>
    </div>
</div>

<!--
    Where a card\'s own dialog goes. A Bootstrap modal nested inside another
    shares the outer one\'s backdrop and is drawn behind it, so the cron dialog
    and its kind are moved out here -- after the settings modal, which is what
    puts them in front of it -- and are cleared when the pane changes.
-->
<div id="pg_settings_modal_extras"></div>';
}
