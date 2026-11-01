<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * New dashboard - the person's row in dashboard_layout and the switch.
 *
 * The loader of includes/dashboard/v2/: it defines the gate constant the
 * other files of the folder check. One row per person (user_id, role 0) holds
 * the switch, the layout, the look and the sign-in times; role default rows
 * (user_id 0) come with the sharing step. The sign-in times are written by
 * pg_dashboard_sign_in_mark() in includes/authentication.php, which runs on
 * every sign-in path.
 *
 * Every read treats a failed query as "no row": a site that has not run the
 * 2026.11.0 upgrade has no table yet and keeps the current dashboard.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The gate every file of includes/dashboard/v2/ checks.
if (!defined('PG_DASHBOARD_V2')) {
    define('PG_DASHBOARD_V2', true);
}

/**
 * The person's own row, or null when there is none (or no table yet).
 *
 * @param int $user_id
 * @return array|null
 */
function pg_dashboard_v2_person_row($user_id)
{
    $user_id = (int) $user_id;

    if ($user_id <= 0) {
        return null;
    }

    $result = @mysqli_query(db::$con,
        "SELECT id, user_id, enabled, layout, look, signed_in_at, signed_in_before, updated_at
            FROM dashboard_layout
            WHERE user_id = " . $user_id . " AND role = 0");

    if (!$result) {
        return null;
    }

    $row = mysqli_fetch_assoc($result);

    return $row ? $row : null;
}

/**
 * Whether the person has switched the new dashboard on.
 *
 * @param array|null $row pg_dashboard_v2_person_row()
 * @return bool
 */
function pg_dashboard_v2_is_enabled($row)
{
    return is_array($row) && !empty($row['enabled']);
}

/**
 * The upsert behind the switch: the person's row with the switch set, created
 * with an empty layout when the person has none yet.
 *
 * @param int $user_id
 * @param bool $on
 * @param int $now
 * @return string
 */
function pg_dashboard_v2_switch_query($user_id, $on, $now)
{
    $user_id = (int) $user_id;
    $enabled = $on ? 1 : 0;
    $now = (int) $now;

    return "INSERT INTO dashboard_layout (user_id, role, enabled, layout, updated_at, updated_by)
        VALUES (" . $user_id . ", 0, " . $enabled . ", '', " . $now . ", " . $user_id . ")
        ON DUPLICATE KEY UPDATE
            enabled = VALUES(enabled),
            updated_at = VALUES(updated_at),
            updated_by = VALUES(updated_by)";
}

/**
 * Writes the switch for the person. False when the write failed (no table
 * yet: the upgrade has not run).
 *
 * @param int $user_id
 * @param bool $on
 * @return bool
 */
function pg_dashboard_v2_set_enabled($user_id, $on)
{
    if ((int) $user_id <= 0) {
        return false;
    }

    return (bool) @mysqli_query(db::$con, pg_dashboard_v2_switch_query($user_id, $on, time()));
}

/**
 * The switch row at the top of both dashboards: the switch labelled "New
 * dashboard" with its hint, and on the new page whatever goes at its right
 * end ($aside, already escaped markup). The script that saves a flip is
 * assets/js/dashboard_switch.js.
 *
 * @param bool $on
 * @param string $aside
 * @return string
 */
function pg_dashboard_v2_switch_row($on, $aside = '')
{
    $hint = $on
        ? lang('Turn it off to go back to the old dashboard.')
        : lang('Try the new design; switch back here at any time.');

    return '
    <div class="pg-dash-switch" id="pg_dash_switch_row" data-error-text="' . h(lang('The change could not be saved. Please try again.')) . '">
        <div class="pg-dash-switch-toggle">
            <div class="switch"><input type="checkbox" role="switch" id="pg_dash_switch" aria-describedby="pg_dash_switch_hint"' . ($on ? ' checked' : '') . '></div>
            <label for="pg_dash_switch" class="pg-dash-switch-label">' . h(lang('New dashboard')) . '</label>
            <span class="pg-dash-switch-hint" id="pg_dash_switch_hint">' . h($hint) . '</span>
        </div>
        ' . ($aside !== '' ? '<div class="pg-dash-switch-aside">' . $aside . '</div>' : '') . '
    </div>';
}
