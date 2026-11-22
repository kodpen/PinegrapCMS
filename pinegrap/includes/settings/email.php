<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings › E-mail: the e-mail the site sends and the mailboxes it
 * reads, as two tabs, each drawn by its old category's module under its own
 * gate (the registry's 'tabs'): E-mail sending (includes/settings/
 * mail_sending.php) and Mailboxes (includes/settings/mailboxes.php). A
 * reader with one tab's right sees that tab's cards and no tab control.
 *
 * Fills $pg_settings_cards with the tab control, each tab's panel and the
 * line that opens the panel the address names; the modules' drawers and
 * forms go to $pg_settings_modals as before. email.save.php writes it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/email_settings_tabs.php');

// The tabs this reader may open ($pg_settings_category is already the
// reader's), and the one shown first: the one the old page names, else the
// first.
$email_tabs = (array) $pg_settings_category['tabs'];
$email_first = (isset($pg_settings_tab) && isset($email_tabs[$pg_settings_tab])) ? $pg_settings_tab : (string) key($email_tabs);
$email_panels = array();
$email_states = array();
$email_kept = $pg_settings_cards;

foreach ($email_tabs as $email_tab_key => $email_tab) {
    $pg_settings_cards = array();

    include(PG_FUNCTIONS_DIR . '/includes/settings/' . $email_tab['module'] . '.php');

    $email_panels[$email_tab_key] = implode('', $pg_settings_cards);
}

$pg_settings_cards = $email_kept;

// Each tab's state for its pill.
if (isset($email_panels['sending'])) {
    $email_states['sending'] = pg_email_tab_sending_state(!empty($mail_ready), isset($mail_domain_health) ? $mail_domain_health : array());
}

if (isset($email_panels['mailboxes']) && function_exists('ws_mail_ready') && ws_mail_ready()) {
    $email_states['mailboxes'] = pg_email_tab_mailboxes_state(array_map(function ($box) {
        return ws_mail_box_state($box)['key'];
    }, ws_mail_settings_boxes()));
}

$email_bar = pg_email_tabs_html('email', $email_tabs, $email_first, $email_states);

if ($email_bar !== '') {
    $pg_settings_cards[] = $email_bar;
}

foreach ($email_panels as $email_tab_key => $email_cards) {
    $pg_settings_cards[] = pg_email_tab_panel_html('email', $email_tab_key, $email_cards, $email_bar !== '', $email_tab_key === $email_first);
}

// The panel an address names opens once the cards are in (a reload, a
// #settings/email/<panel> address, a link to a card of the other tab).
if ($email_bar !== '') {
    $pg_settings_cards[] = '<script>(function () { var go = function () { window.pgSettingsTabsInit(document.getElementById(' . json_encode(pg_settings_tab_section('email', $email_first) . '-tab') . ')); };'
        . ' if (window.pgSettingsTabsInit) { go(); } else { document.addEventListener(\'DOMContentLoaded\', go); } })();</script>';
}
