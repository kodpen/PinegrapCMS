<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Workspace screen "My meeting link" (workspace_meeting_link.php): the
 * signed-in booking staff member's own link, the services it offers and
 * why one is left out, and the one short address of the site that points
 * at it. The link itself is made and checked in booking_links.php; a short
 * address is checked by the short links' own rules.
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
 * The member's services as the screen lists them, in the list's order:
 * each with offered (true when the link offers it) and reason ('deposit'
 * for a service with a deposit, which a link does not offer). Pure.
 *
 * @param array $services booking_services rows (pg_booking_widget_data())
 * @param array $member   booking_staff row with service_ids
 * @return array
 */
function pg_booking_link_screen_services($services, $member)
{
    $own = pg_booking_service_ids(isset($member['service_ids']) ? $member['service_ids'] : '');
    $offered = array();

    foreach (pg_booking_link_services($services, $member) as $service) {
        $offered[(int) $service['id']] = true;
    }

    $rows = array();

    foreach ((array) $services as $service) {
        if (!is_array($service) || !in_array((int) $service['id'], $own, true)) {
            continue;
        }

        $on = isset($offered[(int) $service['id']]);

        $rows[] = $service + array(
            'offered' => $on,
            'reason'  => $on ? '' : 'deposit',
        );
    }

    return $rows;
}

/**
 * The site path a short address of the member's link points at, '' without
 * a key. A path, not a whole address, so a new host name keeps it. Pure.
 *
 * @param array  $member
 * @param string $key
 * @param string $base_path PATH
 * @return string
 */
function pg_booking_link_short_target($member, $key, $base_path)
{
    $code = pg_booking_link_code($member, $key);

    return ($code === '') ? '' : (string) $base_path . 'book_with.php?c=' . $code;
}

/**
 * The signed-in user's booking staff row (switched on), with role and
 * name, null when they are no booking staff (role 3 never is).
 *
 * @param int $user_id
 * @return array|null
 */
function pg_booking_link_screen_member($user_id)
{
    if (((int) $user_id <= 0) || !pg_booking_ready()) {
        return null;
    }

    $staff_id = pg_booking_agenda_user_staff_id($user_id);
    $row = ($staff_id > 0) ? pg_booking_link_staff_row($staff_id) : null;

    // A user of role 3 cannot be booking staff.
    return (is_array($row) && ((int) $row['user_role'] < 3)) ? $row : null;
}

/**
 * The live short link that points at a target path, null when there is
 * none (the oldest when there are more).
 *
 * @param string $target pg_booking_link_short_target()
 * @return array|null id, name, redirect_code (when the column is there)
 */
function pg_booking_link_short_link($target)
{
    if ((string) $target === '') {
        return null;
    }

    $live = function_exists('pg_short_link_recycle_ready') && pg_short_link_recycle_ready() ? " AND recycled = '0'" : '';
    $modes = pg_short_link_modes_ready() ? " AND link_mode <> 'once'" : '';

    $row = db_item(
        "SELECT * FROM short_links
        WHERE destination_type = 'url' AND url = '" . e($target) . "'" . $live . $modes . "
        ORDER BY id LIMIT 1");

    return is_array($row) ? $row : null;
}
