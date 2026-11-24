<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The "Start here" card of a new team member: three to five screens for
 * their role, each a link with a line of what it is for
 * (pg_launch_journey_path()), only screens they may open. "Hide" on the
 * card's menu marks PG_LAUNCH_JOURNEY_PATH_KEY in their tours_seen through
 * the panel action tour_seen, which closes the card's gate.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

/**
 * The journey's screens the person may open, by the rule each screen's
 * own access check uses.
 *
 * @param array $user the row validate_user() returned
 * @return array screen key => bool
 */
function pg_dashboard_v2_starthere_may($user)
{
    $role = (int) $user['role'];
    $staff = ($role < 3);
    $files = $staff || no_acl_check($user['id']);

    return array(
        'view_pages'           => $files,
        'view_styles'          => ($role < 2),
        'view_menus'           => ($role < 2),
        'view_folders'         => $files,
        'view_comments'        => $staff,
        'view_orders'          => defined('ECOMMERCE') && (ECOMMERCE === true) && ($staff || !empty($user['manage_ecommerce'])),
        'view_submitted_forms' => (!defined('FORMS') || (FORMS === true)) && ($staff || !empty($user['manage_forms'])),
        'view_contacts'        => validate_contacts_access($user, true) == true,
        'view_calendars'       => defined('CALENDARS') && (CALENDARS === true) && (validate_calendars_access($user, true) == true),
    );
}

/**
 * @param array $user
 * @param int   $period
 * @param int   $compare
 * @return array
 */
function pg_dashboard_v2_card_starthere($user, $period, $compare)
{
    $rows = pg_launch_journey_path($user['role'], pg_dashboard_v2_starthere_may($user));

    if (!$rows) {
        return array('status' => 'denied', 'data' => array());
    }

    return array('status' => 'success', 'data' => array(
        'rows'   => $rows,
        'key'    => PG_LAUNCH_JOURNEY_PATH_KEY,
        'labels' => array(
            'menu'   => lang('Start here options'),
            'hide'   => lang('Hide'),
            'open'   => lang('Open'),
            'failed' => lang('The change could not be saved.'),
        ),
    ));
}
