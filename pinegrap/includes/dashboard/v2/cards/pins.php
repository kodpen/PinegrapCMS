<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Pinned card: tiles of the screens and records the person pinned from
 * the panel's search. Pins are kept in the person's layout and saved by the
 * card through dashboard_personal_save (includes/dashboard/v2/personal.php).
 * A pin opens its screen, which checks access as it always does.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/personal.php');

function pg_dashboard_v2_card_pins($user, $period, $compare)
{
    $stored = pg_dashboard_v2_pins_clean(pg_dashboard_v2_personal($user, 'pins'));
    $pins = array();

    foreach ($stored as $pin) {
        $target = pg_dashboard_v2_pin_target($pin);
        $pins[] = array('text' => $pin['l'], 'url' => $target['url'], 'icon' => $target['icon']);
    }

    return array('status' => 'success', 'data' => array(
        'pins'   => $pins,
        'stored' => $stored,
        'types'  => array_keys(pg_dashboard_v2_pin_types()),
        'most'   => PG_DASHBOARD_V2_MAX_PINS,
        'locked' => pg_dashboard_v2_role_locked((int) $user['role']),
        'labels' => array(
            'none'    => lang('Nothing pinned yet. Pin the pages and screens you open often.'),
            'add'     => lang('Pin'),
            'search'  => lang('Search pages, products, screens…'),
            'nothing' => lang('No Search Result'),
            'remove'  => lang('Unpin {var:1}'),
            'full'    => lang('Unpin one to pin another.'),
            'cancel'  => lang('Cancel'),
            'failed'  => lang('The change could not be saved.'),
        ),
    ));
}
