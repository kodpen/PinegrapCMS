<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Communication area of the panel (main menu row 9 under Marketing):
 * its five tabs, who may open each, the tab strip every tab screen prints
 * under its heading, the menu row's panel and the rows that lead from a tab
 * to the screen where its job is done today.
 *
 * Each tab is a screen of its own file with its own gate: Campaigns is
 * view_email_campaigns.php, the others are messages_*.php. A tab its viewer
 * may not open is left out of the strip and the menu panel, so neither leads
 * to "Access denied".
 *
 * Loaded where it is needed (require_once), not by functions.php.
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
 * The five tabs in their order. 'gate' names the rule the tab's screen asks:
 * 'email' is validate_email_access() (role <= 2 or the Campaign Manager
 * right), 'manager' is role <= 2. Labels are English; the strip translates.
 *
 * @return array key => array(file, label, icon, gate)
 */
function pg_messages_area_tabs()
{
    return array(
        'campaigns' => array('file' => 'view_email_campaigns.php', 'label' => 'Campaigns', 'icon' => 'bi-megaphone', 'gate' => 'email'),
        'automatic' => array('file' => 'messages_automatic.php', 'label' => 'Automatic sends', 'icon' => 'bi-diagram-3', 'gate' => 'email'),
        'templates' => array('file' => 'messages_templates.php', 'label' => 'Templates', 'icon' => 'bi-card-text', 'gate' => 'email'),
        'sends'     => array('file' => 'messages_sends.php', 'label' => 'Sends', 'icon' => 'bi-send', 'gate' => 'email'),
        'channels'  => array('file' => 'messages_channels.php', 'label' => 'Channels', 'icon' => 'bi-broadcast', 'gate' => 'manager'),
    );
}

/**
 * Whether a person passes a tab's gate.
 *
 * @param string $gate          'email' or 'manager'
 * @param int    $role          0 administrator to 3 user
 * @param bool   $manage_emails the Campaign Manager right
 * @return bool
 */
function pg_messages_gate_allows($gate, $role, $manage_emails)
{
    $role = (int) $role;

    if ($gate === 'manager') {
        return $role <= 2;
    }

    if ($gate === 'email') {
        return ($role <= 2) || (bool) $manage_emails;
    }

    return false;
}

/**
 * The keys of the tabs a person may open, in the strip's order.
 *
 * @param int  $role
 * @param bool $manage_emails
 * @return array
 */
function pg_messages_tabs_for($role, $manage_emails)
{
    $keys = array();

    foreach (pg_messages_area_tabs() as $key => $tab) {
        if (pg_messages_gate_allows($tab['gate'], $role, $manage_emails)) {
            $keys[] = $key;
        }
    }

    return $keys;
}

/**
 * The same for a signed-in user row (validate_user()).
 *
 * @param array $user
 * @return array
 */
function pg_messages_tabs_for_user($user)
{
    $role = isset($user['role']) ? (int) $user['role'] : 3;
    $manage_emails = !empty($user['manage_emails']);

    return pg_messages_tabs_for($role, $manage_emails);
}

/**
 * The tab strip under a tab screen's heading: links between screens, so the
 * open one carries aria-current="page" (not role=tab: each tab is its own
 * address). Empty when the viewer may open fewer than two tabs.
 *
 * @param string $active a key of pg_messages_area_tabs(), or '' for none
 * @param array  $keys   the viewer's tabs (pg_messages_tabs_for_user())
 * @param string $prefix the address before a file name
 * @return string
 */
function pg_messages_tab_strip($active, $keys, $prefix = '')
{
    $tabs = pg_messages_area_tabs();
    $items = '';
    $count = 0;

    foreach ($keys as $key) {
        if (!isset($tabs[$key])) {
            continue;
        }

        $tab = $tabs[$key];
        $current = ($key === $active);
        $count++;

        $items .= '<li class="nav-item">'
            . '<a class="nav-link' . ($current ? ' active' : '') . '"' . ($current ? ' aria-current="page"' : '')
            . ' href="' . h($prefix . $tab['file']) . '">'
            . '<i class="bi ' . h($tab['icon']) . ' me-3" aria-hidden="true"></i>' . h(lang($tab['label'])) . '</a>'
            . '</li>';
    }

    if ($count < 2) {
        return '';
    }

    return '<ul class="nav nav-tabs mb-5 pg-messages-tabs" aria-label="' . h(lang('Communication')) . '">' . $items . '</ul>';
}

/**
 * The strip for the signed-in user on a panel screen.
 *
 * @param string $active
 * @param array  $user
 * @return string
 */
function pg_messages_tab_strip_for($active, $user)
{
    return pg_messages_tab_strip($active, pg_messages_tabs_for_user($user), OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/');
}

/**
 * The entries of menu row 9's panel: the tabs the person may open, then
 * "New campaign". Markup in the menu's single-quoted attribute style.
 *
 * @param array  $keys   the viewer's tabs
 * @param string $prefix the address before a file name
 * @param string $target the menu's target attribute (may be '')
 * @return string
 */
function pg_messages_menu_entries($keys, $prefix, $target = '')
{
    $tabs = pg_messages_area_tabs();
    $class = 'btn btn-link pg-link-body-emphasis text-start text-decoration-none text-truncate bi bi-me-2 ';
    $html = '';

    foreach ($keys as $key) {
        if (!isset($tabs[$key])) {
            continue;
        }

        $html .= '<a href=\'' . h($prefix . $tabs[$key]['file']) . '\'' . $target . ' class=\'' . $class . h($tabs[$key]['icon']) . '\'>' . h(lang($tabs[$key]['label'])) . '</a>';
    }

    if (in_array('campaigns', $keys, true)) {
        $html .= '<hr class=\'divider my-3\' />';
        $html .= '<a href=\'' . h($prefix . 'add_email_campaign.php') . '\'' . $target . ' class=\'' . $class . 'bi-plus-lg\'>' . h(lang('New campaign')) . '</a>';
    }

    return $html;
}

/**
 * Rows that lead from a tab to the screen where a job is done today, drawn
 * as look A list rows inside a card. A row without a title or an address is
 * left out.
 *
 * Each row: title, text, url, icon (bi-…), tone (a .pg-tone-* name).
 *
 * @param string $heading the card's section title
 * @param array  $rows
 * @return string '' when no row is left
 */
function pg_messages_place_rows($heading, $rows)
{
    $html = '';

    foreach ($rows as $row) {
        if (!is_array($row) || empty($row['title']) || empty($row['url'])) {
            continue;
        }

        $icon = !empty($row['icon']) ? (string) $row['icon'] : 'bi-box-arrow-in-right';
        $tone = !empty($row['tone']) ? (string) $row['tone'] : 'gray';
        $text = isset($row['text']) ? (string) $row['text'] : '';

        $html .= '<div class="pg-list-row">'
            . '<span class="pg-icon-tile pg-tone-' . h($tone) . '" aria-hidden="true"><span class="bi ' . h($icon) . '"></span></span>'
            . '<div class="pg-list-row-main">'
            . '<div class="pg-list-row-head"><a class="pg-list-row-title" href="' . h((string) $row['url']) . '">' . h((string) $row['title']) . '</a></div>'
            . ($text !== '' ? '<div class="pg-list-row-text">' . h($text) . '</div>' : '')
            . '</div></div>';
    }

    if ($html === '') {
        return '';
    }

    return '<section class="pg-card mb-5"><h2 class="pg-title-section">' . h($heading) . '</h2>' . $html . '</section>';
}
