<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings › E-mail's save: each tab's old save module, for a tab the
 * reader may open. E-mail sending's writes every field of its cards, so it
 * runs only when its cards were posted (the panel's hidden field,
 * pg_email_tab_panel_html()): Bring in an .eml file posts a form of its own
 * to the category's page and must never blank the sending settings.
 * Mailboxes' acts only on the buttons and fields it finds, so it runs
 * whenever its tab may be opened. A refusal of the first stops the second.
 *
 * Runs in the scope of includes/settings/screen.php or of
 * pg_settings_apply(), after the token check and after post_value() is
 * defined.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

$email_save_tabs = pg_settings_tabs_open(pg_settings_category('email'), pg_settings_reader($user));

if (isset($email_save_tabs['sending']) && (post_value('email_sending_tab') !== null)) {
    include(PG_FUNCTIONS_DIR . '/includes/settings/mail_sending.save.php');
}

if (isset($email_save_tabs['mailboxes']) && !$liveform->check_form_errors()) {
    include(PG_FUNCTIONS_DIR . '/includes/settings/mailboxes.save.php');
}
