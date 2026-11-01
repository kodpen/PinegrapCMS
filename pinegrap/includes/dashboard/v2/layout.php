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
    $minified = 'assets/css/dashboard_v2.min.css';
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
