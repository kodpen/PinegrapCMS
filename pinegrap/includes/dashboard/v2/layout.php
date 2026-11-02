<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard: the person's row in dashboard_layout (the switch, the
 * layout and look JSON, the last two sign-in times) and the switch row both
 * dashboards draw at the top of #content.
 *
 * Every file under includes/dashboard/v2/ checks PG_DASHBOARD_V2, defined
 * here; welcome.php and the panel actions load this file first.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

if (!defined('PG_DASHBOARD_V2')) {
    define('PG_DASHBOARD_V2', true);
}

/**
 * The person's own row, or null when there is none. A database that has not
 * run the step that makes the table yet answers null too: the query fails
 * without throwing and the old dashboard is drawn.
 *
 * @param int $user_id
 * @return array|null
 */
function pg_dashboard_v2_row($user_id)
{
    $user_id = (int) $user_id;

    if ($user_id <= 0) {
        return null;
    }

    $result = @mysqli_query(db::$con, "SELECT * FROM dashboard_layout WHERE user_id = '" . $user_id . "' AND role = 0");

    if (!$result) {
        return null;
    }

    $row = mysqli_fetch_assoc($result);

    return is_array($row) ? $row : null;
}

/**
 * Whether the person turned the new dashboard on.
 *
 * @param int $user_id
 * @return bool
 */
function pg_dashboard_v2_enabled($user_id)
{
    $row = pg_dashboard_v2_row($user_id);

    return ($row !== null) && ((int) $row['enabled'] === 1);
}

/**
 * Turn the new dashboard on or off for the person. The row is made on the
 * first flip when no sign-in made it before.
 *
 * @param int  $user_id
 * @param bool $on
 * @return bool false when the write failed (no table yet)
 */
function pg_dashboard_v2_set_enabled($user_id, $on)
{
    $user_id = (int) $user_id;

    if ($user_id <= 0) {
        return false;
    }

    $enabled = $on ? 1 : 0;
    $now = time();

    return (bool) @mysqli_query(db::$con,
        "INSERT INTO dashboard_layout (user_id, role, enabled, updated_at, updated_by)
        VALUES ('" . $user_id . "', 0, '" . $enabled . "', '" . $now . "', '" . $user_id . "')
        ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), updated_at = VALUES(updated_at), updated_by = VALUES(updated_by)");
}

/**
 * The new dashboard's stylesheet: the minified copy when the site runs
 * minified and the copy is there, else the source, versioned by its
 * modification time.
 *
 * @return string URL
 */
function pg_dashboard_v2_style_url()
{
    $minified = 'assets/css/pg_dash.min.css';
    $bundles = pg_css_bundles();
    $file = $minified;

    if ((defined('ENVIRONMENT_SUFFIX') && (ENVIRONMENT_SUFFIX !== 'min')) || !is_file(PG_FUNCTIONS_DIR . '/' . $minified)) {
        $file = $bundles[$minified];
    }

    return OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/' . $file . '?v=' . (int) @filemtime(PG_FUNCTIONS_DIR . '/' . $file);
}

/**
 * The switch row, the first row of #content on both dashboards: the switch
 * labelled "New dashboard", a hint saying what flipping it does, and on the
 * new page a slot at the end for its own buttons. Flipping it saves the
 * choice and loads the page again, which welcome.php then draws as the
 * other dashboard. On the old dashboard the row also links the stylesheet.
 *
 * @param bool   $on    the new dashboard is the one drawn
 * @param string $end   HTML right-aligned at the end of the row
 * @return string HTML
 */
function pg_dashboard_v2_switch_row($on, $end = '')
{
    $hint = $on
        ? lang('Turn it off to go back to the old dashboard.')
        : lang('Try the new design; switch back here at any time.');

    // The old dashboard does not load the new one's stylesheet in its head,
    // so the row brings it along there.
    return ($on ? '' : '
    <link rel="stylesheet" href="' . h(pg_dashboard_v2_style_url()) . '">') . '
    <div class="pg-dash-switch" id="pg_dash_switch_row">
        <label class="pg-dash-switch-label" for="pg_dash_switch">
            <span class="switch"><input type="checkbox" id="pg_dash_switch" role="switch" aria-describedby="pg_dash_switch_hint"' . ($on ? ' checked' : '') . '></span>
            <span class="pg-dash-switch-name">' . h(lang('New dashboard')) . '</span>
        </label>
        <span class="pg-dash-switch-hint" id="pg_dash_switch_hint">' . h($hint) . '</span>
        ' . (($end !== '') ? '<span class="pg-dash-switch-end">' . $end . '</span>' : '') . '
    </div>
    <script>
        (function () {
            var box = document.getElementById("pg_dash_switch");
            if (!box) {
                return;
            }
            box.addEventListener("change", function () {
                var on = box.checked;
                box.disabled = true;
                $.ajax({
                    contentType: "application/json",
                    url: "api.php",
                    data: JSON.stringify({action: "dashboard_switch", token: software_token, on: on ? 1 : 0}),
                    type: "POST",
                    dataType: "json"
                }).done(function (answer) {
                    if (answer && answer.status === "success") {
                        window.location.reload();
                        return;
                    }
                    box.checked = !on;
                    box.disabled = false;
                    if (typeof window.pgToast === "function") {
                        window.pgToast({variant: "danger", message: (answer && answer.message) || ' . json_encode(lang('The change could not be saved.')) . '});
                    }
                }).fail(function () {
                    box.checked = !on;
                    box.disabled = false;
                    if (typeof window.pgToast === "function") {
                        window.pgToast({variant: "danger", message: ' . json_encode(lang('The change could not be saved.')) . '});
                    }
                });
            });
        })();
    </script>';
}

/**
 * The largest layout JSON a save stores.
 */
if (!defined('PG_DASHBOARD_V2_MAX_LAYOUT')) {
    define('PG_DASHBOARD_V2_MAX_LAYOUT', 65536);
}

/**
 * A layout sent by the edit mode, cleaned for storing: unknown card ids,
 * repeats, shortcut and tile keys outside their pools and unknown keys are
 * dropped, bad values take the default. The cards the person may not see
 * are not in what the page sent; they keep their places from the stored
 * layout, so a module switched off and on again finds its cards where they
 * were. Goals, pins and notes are kept from the stored layout: the edit
 * mode does not change them. Pure.
 *
 * @param mixed    $layout    the decoded layout the page sent
 * @param mixed    $old       the decoded stored layout, null when none
 * @param string[] $visible   ids of the cards the person may see
 * @param string[] $shortcuts shortcut keys the person may use
 * @param string[] $figures   figure keys the person may see
 * @param bool     $shop      the ECOMMERCE switch is on
 * @return array|null null when $layout is not a layout at all
 */
function pg_dashboard_v2_layout_clean($layout, $old, $visible, $shortcuts, $figures, $shop)
{
    if (!is_array($layout) || !isset($layout['cards']) || !is_array($layout['cards'])) {
        return null;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/presets.php');

    $old = is_array($old) ? $old : array();
    $cards = pg_dashboard_v2_cards();
    $presets = array_keys(pg_dashboard_v2_presets());
    $range = function ($value) {
        return (is_int($value) && ($value >= 0) && ($value <= 2)) ? $value : 0;
    };

    $list = array();
    $seen = array();

    foreach ($layout['cards'] as $entry) {
        if (!is_array($entry) || !isset($entry['id']) || !is_string($entry['id']) || !isset($cards[$entry['id']])
            || !in_array($entry['id'], $visible, true) || isset($seen[$entry['id']])) {
            continue;
        }

        $id = $entry['id'];
        $seen[$id] = true;

        $list[] = array(
            'id'    => $id,
            'w'     => (isset($entry['w']) && in_array($entry['w'], array('S', 'M', 'L'), true)) ? $entry['w'] : $cards[$id]['width'],
            'per'   => isset($entry['per']) ? $range($entry['per']) : (int) $cards[$id]['period'],
            'cmp'   => isset($entry['cmp']) ? $range($entry['cmp']) : 0,
            'badge' => (isset($entry['badge']) && !$entry['badge']) ? 0 : 1,
        );
    }

    // The stored cards the person may not see, back at their old places.
    if (isset($old['cards']) && is_array($old['cards'])) {
        foreach (array_values($old['cards']) as $i => $entry) {
            if (is_array($entry) && isset($entry['id']) && is_string($entry['id']) && isset($cards[$entry['id']])
                && !in_array($entry['id'], $visible, true) && !isset($seen[$entry['id']])) {
                $seen[$entry['id']] = true;
                array_splice($list, min($i, count($list)), 0, array($entry));
            }
        }
    }

    $keys = function ($name, $pool, $most) use ($layout) {
        $out = array();
        foreach ((isset($layout[$name]) && is_array($layout[$name])) ? $layout[$name] : array() as $key) {
            if (is_string($key) && in_array($key, $pool, true) && !in_array($key, $out, true)) {
                $out[] = $key;
            }
        }
        return array_slice($out, 0, $most);
    };

    $top = array();
    foreach (array('since', 'actions', 'tiles', 'status') as $part) {
        $top[$part] = (isset($layout['top'][$part]) && !$layout['top'][$part]) ? 0 : 1;
    }

    return array(
        'v'       => 1,
        'preset'  => (isset($layout['preset']) && in_array($layout['preset'], $presets, true)) ? $layout['preset'] : pg_dashboard_v2_preset_first($shop),
        'cards'   => $list,
        'top'     => $top,
        'actions' => $keys('actions', $shortcuts, 6),
        'tiles'   => $keys('tiles', $figures, 4),
        'goals'   => (isset($old['goals']) && is_array($old['goals'])) ? $old['goals'] : array(),
        'pins'    => (isset($old['pins']) && is_array($old['pins'])) ? $old['pins'] : array(),
        'notes'   => (isset($old['notes']) && is_string($old['notes'])) ? $old['notes'] : '',
    );
}

/**
 * A look sent by the page, cleaned: a value outside its choices is left
 * out, so the site's Appearance value stands for it. Pure.
 *
 * @param mixed $look
 * @return array style, backdrop (when chosen), dense, calm
 */
function pg_dashboard_v2_look_clean($look)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/dashboard.php');

    $choices = pg_dashboard_appearance_choices();
    $look = is_array($look) ? $look : array();
    $out = array();

    if (isset($look['style']) && in_array($look['style'], $choices['widget_theme'], true)) {
        $out['style'] = $look['style'];
    }

    if (isset($look['backdrop']) && in_array($look['backdrop'], $choices['panel_backdrop'], true)) {
        $out['backdrop'] = $look['backdrop'];
    }

    $out['dense'] = empty($look['dense']) ? 0 : 1;
    $out['calm'] = empty($look['calm']) ? 0 : 1;

    return $out;
}

/**
 * Is the layout of a role locked by its default (the role's own row or the
 * one for everyone)? A locked person cannot change their layout.
 *
 * @param int $role
 * @return bool
 */
function pg_dashboard_v2_role_locked($role)
{
    $result = @mysqli_query(db::$con, "SELECT locked FROM dashboard_layout WHERE user_id = 0 AND role IN ('" . (int) $role . "', 9) AND locked = 1 LIMIT 1");

    return $result && (mysqli_num_rows($result) > 0);
}

/**
 * Store the person's layout, and their look when one is given.
 *
 * @param int         $user_id
 * @param string      $layout JSON
 * @param string|null $look   JSON, null to keep the stored look
 * @return bool
 */
function pg_dashboard_v2_layout_write($user_id, $layout, $look = null)
{
    $user_id = (int) $user_id;
    $now = time();

    if ($user_id <= 0) {
        return false;
    }

    $look_sql = ($look === null) ? '' : ", look = VALUES(look)";

    return (bool) @mysqli_query(db::$con,
        "INSERT INTO dashboard_layout (user_id, role, layout, look, updated_at, updated_by)
        VALUES ('" . $user_id . "', 0, '" . escape($layout) . "', '" . escape((string) $look) . "', '" . $now . "', '" . $user_id . "')
        ON DUPLICATE KEY UPDATE layout = VALUES(layout)" . $look_sql . ", updated_at = VALUES(updated_at), updated_by = VALUES(updated_by)");
}

/**
 * The roles a layout can be made the default for, everyone (9) first, then
 * by role number: Administrator (0), Designer (1), Manager (2), User (3).
 *
 * @return array role => label
 */
function pg_dashboard_v2_share_roles()
{
    return array(
        9 => lang('Everyone'),
        0 => lang('Administrator'),
        1 => lang('Designer'),
        2 => lang('Manager'),
        3 => lang('User'),
    );
}

/**
 * @param mixed $role
 * @return bool
 */
function pg_dashboard_v2_share_role_valid($role)
{
    if (is_int($role)) {
        $number = $role;
    } elseif (is_string($role) && ctype_digit($role)) {
        $number = (int) $role;
    } else {
        return false;
    }

    return array_key_exists($number, pg_dashboard_v2_share_roles());
}

/**
 * Only administrators and designers make a role's default and lock it.
 *
 * @param int $role the person's role
 * @return bool
 */
function pg_dashboard_v2_can_share($role)
{
    return (int) $role <= 1;
}

/**
 * The default row for a role: its own, else the one for everyone. A locked
 * row wins over an unlocked one, so the lock that pg_dashboard_v2_role_locked()
 * reports is also the layout drawn.
 *
 * @param int $role
 * @return array|null
 */
function pg_dashboard_v2_role_default($role)
{
    $result = @mysqli_query(db::$con,
        "SELECT layout, look, locked FROM dashboard_layout
        WHERE user_id = 0 AND role IN ('" . (int) $role . "', 9)
        ORDER BY locked DESC, (role = 9) ASC LIMIT 1");

    if (!$result) {
        return null;
    }

    $row = mysqli_fetch_assoc($result);

    return is_array($row) ? $row : null;
}

/**
 * The row the dashboard is drawn from: the person's own, with the role
 * default's layout where they saved none and its look where they picked
 * none. While the default is locked, its layout and look replace theirs;
 * their own stay stored for when the lock lifts. Other fields (the switch,
 * sign-in times) are always the person's.
 *
 * @param array|null $own
 * @param array|null $default
 * @return array|null
 */
function pg_dashboard_v2_row_effective($own, $default)
{
    if (!is_array($default)) {
        return $own;
    }

    $row = is_array($own) ? $own : array();
    $locked = !empty($default['locked']);

    foreach (array('layout', 'look') as $field) {
        $mine = isset($row[$field]) ? (string) $row[$field] : '';
        if ($locked || ($mine === '')) {
            $row[$field] = (string) ($default[$field] ?? '');
        }
    }

    return $row;
}

/**
 * pg_dashboard_v2_row_effective() for a person.
 *
 * @param array $user the row validate_user() returned
 * @return array|null
 */
function pg_dashboard_v2_row_for($user)
{
    $default = pg_dashboard_v2_role_default((int) $user['role']);

    // The lock does not bind those who set it (pg_dashboard_v2_locked_for()).
    if (is_array($default) && pg_dashboard_v2_can_share((int) $user['role'])) {
        $default['locked'] = 0;
    }

    return pg_dashboard_v2_row_effective(pg_dashboard_v2_row($user['id']), $default);
}

/**
 * Whether the person's layout is locked. Administrators and designers make
 * and lift the lock from their own edit mode, so it never binds them: a lock
 * on their own role or on everyone would otherwise leave nobody able to lift
 * it.
 *
 * @param array $user the row validate_user() returned
 * @return bool
 */
function pg_dashboard_v2_locked_for($user)
{
    return !pg_dashboard_v2_can_share((int) $user['role']) && pg_dashboard_v2_role_locked((int) $user['role']);
}

/**
 * Make a layout and look a role's default, locked or not.
 *
 * @param int    $role    0-3, or 9 for everyone
 * @param string $layout  JSON
 * @param string $look    JSON
 * @param bool   $locked
 * @param int    $user_id who made it
 * @return bool
 */
function pg_dashboard_v2_role_default_write($role, $layout, $look, $locked, $user_id)
{
    if (!pg_dashboard_v2_share_role_valid($role)) {
        return false;
    }

    return (bool) @mysqli_query(db::$con,
        "INSERT INTO dashboard_layout (user_id, role, layout, look, locked, updated_at, updated_by)
        VALUES (0, '" . (int) $role . "', '" . escape($layout) . "', '" . escape($look) . "', '" . ($locked ? 1 : 0) . "', '" . time() . "', '" . (int) $user_id . "')
        ON DUPLICATE KEY UPDATE layout = VALUES(layout), look = VALUES(look), locked = VALUES(locked), updated_at = VALUES(updated_at), updated_by = VALUES(updated_by)");
}

/**
 * The confirmation shown once a default is made.
 *
 * @param int  $role
 * @param bool $locked
 * @return string
 */
function pg_dashboard_v2_share_done($role, $locked)
{
    $roles = pg_dashboard_v2_share_roles();
    $text = ((int) $role === 9)
        ? lang('Default for everyone')
        : lang(array('string' => 'Default for the {var:1} role', 'vars' => $roles[(int) $role] ?? ''));

    return $locked ? $text . ' · ' . lang('locked') : $text;
}

/**
 * What the side panel's Share with the team section needs, or null for a
 * person who may not share. Roles are a list of pairs so their order holds
 * in the script.
 *
 * @param array $user the row validate_user() returned
 * @return array|null
 */
function pg_dashboard_v2_share_config($user)
{
    if (!pg_dashboard_v2_can_share((int) $user['role'])) {
        return null;
    }

    $roles = array();
    foreach (pg_dashboard_v2_share_roles() as $value => $label) {
        $roles[] = array($value, $label);
    }

    return array(
        'roles'   => $roles,
        'title'   => lang('Share with the team'),
        'hint'    => lang('Only administrators and designers see this.'),
        'label'   => lang('Make this layout the default for'),
        'lock'    => lang('Lock: people in this role cannot change the layout'),
        'button'  => lang('Make default'),
    );
}
