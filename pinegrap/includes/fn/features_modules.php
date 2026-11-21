<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Features: the Modules board, a module a row with its
 * switch at the end. The rows' order, names, icons and the line each says
 * on and off; Commerce and Accounting show their state and open their own
 * category instead of a switch. Pure but for lang() and h();
 * includes/settings/features.php reads the facts and draws the board.
 *
 * Every switch keeps the field name and id it had in its old card, so the
 * save (includes/settings/features.save.php) reads it unchanged.
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
 * The rows' keys in reading order.
 *
 * @return string[]
 */
function pg_features_module_order()
{
    return array('commerce', 'accounting', 'forms', 'calendars', 'workspace', 'courses', 'tickets', 'ads',
        'auto_dialogs', 'sharing', 'site_push', 'maps', 'page_clicks', 'content_approval', 'mobile');
}

/**
 * A row's name, icon, tone, its switch's field (the old card's name and id,
 * '' for a module switched in its own category), and its two lines: what
 * the module adds while on, what switching it off hides.
 *
 * @param string $key one of pg_features_module_order()
 * @return array name, icon, tone, field, on, off
 */
function pg_features_module_face($key)
{
    switch ($key) {
        case 'commerce':
            return array('name' => lang('Commerce'), 'icon' => 'bi-bag', 'tone' => 'pg-tone-shop', 'field' => '',
                'on' => lang('Products, cart and orders; turned on and set up in its own category.'), 'off' => '');
        case 'accounting':
            return array('name' => lang('Accounting'), 'icon' => 'bi-calculator', 'tone' => 'pg-tone-accounting', 'field' => '',
                'on' => lang('Accounts, invoices and e-documents; turned on and set up in its own category.'), 'off' => '');
        case 'forms':
            return array('name' => lang('Forms'), 'icon' => 'bi-ui-checks', 'tone' => 'pg-tone-blue', 'field' => 'forms',
                'on' => lang('Forms on pages, their submissions gathered in the panel. Switching it off deletes nothing.'),
                'off' => lang('Off: the Forms screens leave the panel; no form or submission is deleted.'));
        case 'calendars':
            return array('name' => lang('Calendars'), 'icon' => 'bi-calendar3', 'tone' => 'pg-tone-teal', 'field' => 'calendars',
                'on' => lang('Event calendars and reservations. Switching it off deletes nothing.'),
                'off' => lang('Off: the Calendars screens leave the panel; no event is deleted.'));
        case 'workspace':
            return array('name' => lang('Workspace'), 'icon' => 'bi-people', 'tone' => 'pg-tone-team', 'field' => 'workspace_enabled',
                'on' => lang('Channels, tasks and a planning board for the team. Switching it off deletes nothing.'),
                'off' => lang('Off: the Workspace leaves the panel; its channels and tasks are kept.'));
        case 'courses':
            return array('name' => lang('Courses'), 'icon' => 'bi-mortarboard', 'tone' => 'pg-tone-amber', 'field' => 'courses_on',
                'on' => lang('Lessons that open in order or some days after enrolment, each member\'s progress kept. Switching it off deletes nothing.'),
                'off' => lang('Off: the Courses screen leaves the Pages menu and lessons no longer wait their turn; nothing is deleted.'));
        case 'tickets':
            return array('name' => lang('Tickets'), 'icon' => 'bi-qr-code', 'tone' => 'pg-tone-violet', 'field' => 'event_tickets_on',
                'on' => lang('A ticket with a QR code for each place a completed order reserves. Switching it off deletes nothing.'),
                'off' => lang('Off: completed orders give no new tickets; tickets already given are kept.'));
        case 'ads':
            return array('name' => lang('Ads'), 'icon' => 'bi-megaphone', 'tone' => 'pg-tone-blue', 'field' => 'ads',
                'on' => lang('Ad areas on pages, filled from the Ads screen. Switching it off deletes nothing.'),
                'off' => lang('Off: ad areas do not show on pages or in the menu; no ad is deleted.'));
        case 'auto_dialogs':
            return array('name' => lang('Auto dialogs'), 'icon' => 'bi-window-stack', 'tone' => 'pg-tone-content', 'field' => 'auto_dialogs',
                'on' => lang('Dialogs that open on their own on pages. Switching it off deletes nothing.'),
                'off' => lang('Off: no dialog opens on its own; the dialogs are kept.'));
        case 'sharing':
            return array('name' => lang('Sharing buttons'), 'icon' => 'bi-share', 'tone' => 'pg-tone-violet', 'field' => 'social_networking',
                'on' => lang('Share buttons on pages, chosen under Settings. Switching it off deletes nothing.'),
                'off' => lang('Off: pages show no share buttons; their setup is kept.'));
        case 'site_push':
            return array('name' => lang('Visitor notifications'), 'icon' => 'bi-bell', 'tone' => 'pg-tone-amber', 'field' => 'site_push_on',
                'on' => lang('Visitors press the Notify Me block and get your news in the browser. Switching it off deletes nothing.'),
                'off' => lang('Off: the Notify Me block shows nothing and no new subscription is taken.'));
        case 'maps':
            return array('name' => lang('Maps'), 'icon' => 'bi-geo-alt', 'tone' => 'pg-tone-teal', 'field' => 'place_map_on',
                'on' => lang('The Map of places block draws its places on a map beside its list.'),
                'off' => lang('Off: the Map of places block shows its list alone; the map settings are kept.'));
        case 'page_clicks':
            return array('name' => lang('Clicks and scrolling'), 'icon' => 'bi-cursor', 'tone' => 'pg-tone-reports', 'field' => 'page_clicks_on',
                'on' => lang('Counts what visitors click and how far they scroll, shown in Visitors. Switching it off deletes nothing.'),
                'off' => lang('Off: nothing new is counted; the counts so far are kept.'));
        case 'content_approval':
            return array('name' => lang('Content approval'), 'icon' => 'bi-check2-square', 'tone' => 'pg-tone-system', 'field' => 'content_approval_on',
                'on' => lang('A Manager\'s or User\'s change to a published page waits until a Designer or Administrator applies it.'),
                'off' => lang('Off: changes are published as they are saved.'));
        default:
            return array('name' => lang('Mobile view'), 'icon' => 'bi-phone', 'tone' => 'pg-tone-gray', 'field' => 'mobile',
                'on' => lang('Phones get the pages\' mobile style. The page cache is off while this is on.'),
                'off' => lang('Off: phones get the same pages as computers, and the page cache may run.'));
    }
}

/**
 * One switch row. A row is a div, never a link or a button: the name is
 * the switch's label and the line its description. A module switched in
 * its own category shows an On or Off pill and Open; a module whose
 * settings sit below has a quiet Settings that scrolls to them.
 *
 * @param array $row key, on (bool), settings (an element id below, or ''),
 *                   href (the category of a module without a switch), extra
 *                   (HTML posted with the switch, such as a card marker)
 * @return string HTML
 */
function pg_features_module_row($row)
{
    $face = pg_features_module_face($row['key']);
    $on = !empty($row['on']);
    $line_id = 'pg-module-' . str_replace('_', '-', $row['key']) . '-line';

    if ($face['field'] === '') {
        $control = '<span class="pg-pill ' . ($on ? 'pg-tone-teal' : 'pg-tone-gray') . '"' . ($on ? ' data-pg-module-on' : '') . '>' . h($on ? lang('On') : lang('Off')) . '</span>'
            . ((($row['href'] ?? '') !== '')
                ? '<a class="pg-button pg-button-quiet pg-button-sm" href="' . h($row['href']) . '" aria-label="' . h(lang('Open it') . ': ' . $face['name']) . '">' . h(lang('Open it')) . '<i class="bi bi-arrow-right" aria-hidden="true"></i></a>'
                : '');

        return '<div class="pg-set-switch-row' . ($on ? '' : ' is-off') . '">'
            . '<span class="pg-icon-tile pg-icon-tile-lg ' . $face['tone'] . '" aria-hidden="true"><i class="bi ' . $face['icon'] . '"></i></span>'
            . '<span class="pg-set-fold-text"><span class="pg-set-fold-name">' . h($face['name']) . '</span>'
            . '<span class="pg-set-fold-line">' . h($face['on']) . '</span></span>'
            . $control . '</div>';
    }

    $settings = (string) ($row['settings'] ?? '');

    return '<div class="pg-set-switch-row">' . ($row['extra'] ?? '')
        . '<span class="pg-icon-tile pg-icon-tile-lg ' . $face['tone'] . '" aria-hidden="true"><i class="bi ' . $face['icon'] . '"></i></span>'
        . '<span class="pg-set-fold-text"><label class="pg-set-fold-name" for="' . $face['field'] . '">' . h($face['name']) . '</label>'
        . '<span class="pg-set-fold-line" id="' . $line_id . '" data-pg-on="' . h($face['on']) . '" data-pg-off="' . h($face['off']) . '">' . h($on ? $face['on'] : $face['off']) . '</span></span>'
        . (($settings !== '')
            ? '<button type="button" class="pg-button pg-button-quiet pg-button-sm" data-pg-set-goto="' . h($settings) . '" aria-label="' . h(lang('Settings') . ': ' . $face['name']) . '">' . h(lang('Settings')) . '</button>'
            : '')
        . '<input type="checkbox" class="pg-switch" value="1" id="' . $face['field'] . '" name="' . $face['field'] . '" aria-describedby="' . $line_id . '"' . ($on ? ' checked="checked"' : '') . '/>'
        . '</div>';
}

/**
 * How many of the rows are on, and how many there are.
 *
 * @param array $rows as pg_features_module_row() takes them
 * @return int[] on, total
 */
function pg_features_modules_count($rows)
{
    $on = 0;

    foreach ((array) $rows as $row) {
        if (!empty($row['on'])) {
            $on++;
        }
    }

    return array($on, count((array) $rows));
}

/**
 * The board: the Modules label with its count, over one card of rows in
 * pg_features_module_order(). A row not in $rows (a module this install or
 * this reader does not have) is left out.
 *
 * @param array $rows key => row, as pg_features_module_row() takes them
 * @return string HTML
 */
function pg_features_modules_board($rows)
{
    $ordered = array();

    foreach (pg_features_module_order() as $key) {
        if (isset($rows[$key])) {
            $ordered[] = $rows[$key] + array('key' => $key);
        }
    }

    list($on, $total) = pg_features_modules_count($ordered);
    $html = '';

    foreach ($ordered as $row) {
        $html .= pg_features_module_row($row);
    }

    return '<div data-pg-modules>'
        . '<div class="pg-group-label mb-2">' . h(lang('Modules'))
        . '<span class="pg-group-label-count" data-pg-modules-count data-pg-template="' . h(lang(array('string' => '{var:1} / {var:2} on', 'vars' => array('{on}', $total)))) . '">'
        . h(lang(array('string' => '{var:1} / {var:2} on', 'vars' => array($on, $total)))) . '</span></div>'
        . '<div class="pg-set-fold">' . $html . '</div></div>';
}
