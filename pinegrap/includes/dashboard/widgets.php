<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widgets - the loader behind api.php's get_widget_data action.
 *
 * Every widget lives in its own file, includes/dashboard/widgets/widget_<id>.php,
 * and defines one function:
 *
 *     function pg_dashboard_widget_<id>($request, $user)
 *
 * $request is the decoded API request and $user the row validate_user()
 * returned. A widget checks its own access and returns an array with
 * 'status' ('success' or 'error'), 'message' and, on success, 'data' - the
 * card's HTML. It never echoes or exits; api.php respond()s with whatever this
 * loader returns, so a request loads only the widget it asked for.
 *
 * Widgets 21 and 22 are the two halves of the Firewall card. Widget 21 builds
 * the event half and calls pg_dashboard_widget_22($request, $user, $waf_panel),
 * which adds the threat half and returns both; a direct request for widget 22
 * answers with the threat half alone.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The gate every widget file checks.
define('PG_DASHBOARD_WIDGETS', true);

/**
 * @param array $request
 * @param array $user
 * @return array
 */
function pg_dashboard_widget_run($request, $user)
{
    $widget_id = isset($request['widget_id']) ? $request['widget_id'] : '';

    if (is_int($widget_id)) {
        $widget_id = (string) $widget_id;
    }

    $invalid = array(
        'status' => 'error',
        'message' => 'Invalid widget id.'
    );

    // The id becomes part of a file path, so only the known shapes pass.
    if ((!is_string($widget_id)) || (!preg_match('/^(clock|[1-9][0-9]{0,2})$/D', $widget_id))) {
        return $invalid;
    }

    $file = PG_FUNCTIONS_DIR . '/includes/dashboard/widgets/widget_' . $widget_id . '.php';

    if (!is_file($file)) {
        return $invalid;
    }

    require_once($file);

    $handler = 'pg_dashboard_widget_' . $widget_id;

    if (!function_exists($handler)) {
        return $invalid;
    }

    return $handler($request, $user);
}
