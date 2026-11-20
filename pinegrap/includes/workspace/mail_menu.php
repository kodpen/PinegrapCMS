<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Incoming e-mail pinned to a person's own main menu: Pin to main menu on
 * the tab's toolbar (messages_mail.php) keeps the choice in
 * ws_profiles.mail_pinned, one per person, as My Notes keeps its pin in
 * notes_pinned (notes.php). The menu then draws Incoming e-mail as a row of
 * its own, slot 27, under the Workspace (output_menu(), includes/fn/
 * output.php), for whoever may open the tab; Communication keeps its row
 * and its tab.
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
 * Has the database the column of the pin (2026.11.0, 11.0.239)?
 *
 * @return bool
 */
function ws_mail_menu_ready()
{
    return pg_schema_has('ws_profiles', 'mail_pinned');
}

/**
 * Has the person pinned Incoming e-mail to their main menu?
 *
 * @param int $user_id
 * @return bool
 */
function ws_mail_menu_pinned($user_id)
{
    if (((int) $user_id <= 0) || !ws_mail_menu_ready()) {
        return false;
    }

    return ((int) db_value("SELECT mail_pinned FROM ws_profiles WHERE user_id = '" . (int) $user_id . "'") === 1);
}

/**
 * Pins Incoming e-mail to the person's main menu, or takes it off. A person
 * with no workspace profile yet gets one, its other fields at their
 * defaults.
 *
 * @param array $viewer
 * @param bool  $pinned
 * @return bool done
 */
function ws_mail_menu_pin($viewer, $pinned)
{
    if (((int) ($viewer['id'] ?? 0) <= 0) || !ws_mail_menu_ready()) {
        return false;
    }

    db_q("INSERT INTO ws_profiles (user_id, mail_pinned, updated_at)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE mail_pinned = VALUES(mail_pinned)", array((int) $viewer['id'], $pinned ? 1 : 0, time()));

    return true;
}

/**
 * The toolbar's Pin to main menu, or Remove from main menu once pinned
 * (workspace_mail.js). Its label says what a click does, so it carries no
 * pressed state; data-ws-mail-menu-pin holds the state now. Pure.
 *
 * @param bool|null $pinned null while the database has no column
 * @return string
 */
function ws_mail_menu_pin_html($pinned)
{
    if ($pinned === null) {
        return '';
    }

    return '<button type="button" class="pg-button pg-button-quiet pg-button-sm" data-ws-mail-menu-pin="' . ($pinned ? '1' : '0') . '">'
        . '<i class="bi ' . ($pinned ? 'bi-pin-angle-fill' : 'bi-pin-angle') . '" aria-hidden="true"></i>'
        . h($pinned ? lang('Remove from main menu') : lang('Pin to main menu')) . '</button>';
}

/**
 * The words of the pin for the screen script.
 *
 * @return array
 */
function ws_mail_menu_js_strings()
{
    return array(
        'menu_pin'      => lang('Pin to main menu'),
        'menu_unpin'    => lang('Remove from main menu'),
        'menu_pinned'   => lang('Incoming e-mail is in the main menu now.'),
        'menu_unpinned' => lang('Incoming e-mail is no longer in the main menu; Communication still has it.'),
        'menu_label'    => lang('Incoming e-mail'),
    );
}
