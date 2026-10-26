<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Panel actions - the dashboard, the account menu and the background jobs
 * every panel page runs.
 *
 *   user_online_check            marks the signed-in person online
 *   sitemap_check                regenerates the sitemap and pings the
 *                                search engines when it is due
 *   get_widget_data              one dashboard widget's data
 *                                (includes/dashboard/widgets.php)
 *   get_widgets_data             several dashboard widgets in one request
 *   get_dashboard_brief          a fresh line for the dashboard greeting
 *                                (includes/dashboard/brief.php)
 *   user_pinned_app_update       the apps pinned to the caller's own menu
 *   update_dashboard_widgets     the caller's dashboard widget layout
 *   update_dashboard_appearance  the dashboard's backdrop and look
 *   tour_seen                    a guided tour has been watched or skipped
 *   contact_quick_save           the account menu's quick form for one's own
 *                                name
 *
 * Called by pg_panel_dispatch() (includes/panel/actions.php); see that file
 * for the contract.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_PANEL_ACTIONS')) {
    exit;
}

function pg_panel_user_online_check($request, $action)
{
    validate_token();

    if (USER_LOGGED_IN && defined('DB_CONNECTED')) {
        who_is_online(50);
        $response = array(
            'status' => 'success',
            'message' => 'Online status updated.'
        );
    } else {
        $response = array(
            'status' => 'error',
            'message' => 'Not logged in.'
        );
    }
    respond($response);
}

function pg_panel_sitemap_check($request, $action)
{
    // The action sits on the general gate's exemption list because every
    // panel role's pages fire it, and that gate would turn away anyone
    // above role 1. The exemption also skips the session and token checks,
    // so they happen here: regenerating the sitemap and pinging the search
    // engines is work an anonymous request must not be able to start.
    if (!USER_LOGGED_IN) {
        respond(array(
            'status' => 'error',
            'message' => 'Invalid login.'
        ));
    }

    validate_token();

    if (defined('DB_CONNECTED')) {
        $current_timestamp = time();
        if ($current_timestamp >= (LAST_SITEMAP_CHECK_TIMESTAMP + 259200)) {
            $pinged = update_sitemap_and_ping();
            $response = array(
                'status' => 'success',
                'message' => 'Sitemap check triggered. Pinged: ' . ($pinged ? 'yes' : 'no')
            );
        } else {
            $response = array(
                'status' => 'skipped',
                'message' => 'Time threshold not met.'
            );
        }
    } else {
        $response = array(
            'status' => 'error',
            'message' => 'Database not connected.'
        );
    }
    respond($response);
}

function pg_panel_get_widget_data($request, $action)
{
    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    $user = validate_user();
    // Release the session file lock immediately after authentication so that
    // concurrent widget AJAX requests are not serialized waiting for each other.
    session_write_close();

    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets.php');

    respond(pg_dashboard_widget_run($request, $user));
}

function pg_panel_get_widgets_data($request, $action)
{
    // Kept global for the same reason as get_widget_data above.
    global $user;

    $user = validate_user();
    session_write_close();

    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets.php');

    respond(pg_dashboard_widgets_run_many($request, $user));
}

function pg_panel_get_dashboard_brief($request, $action)
{
    // Kept global: the brief reaches helpers that read `global $user`.
    global $user;

    $user = validate_user();

    // The session stays open here, unlike the widget calls above. The brief
    // remembers what it said last so the next line can say something else,
    // and that memory is in the session; a single request has nothing to
    // queue behind it.
    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/brief.php');

    $brief = pg_dashboard_brief($user, pg_changelog_sections());

    session_write_close();

    respond(array(
        'status' => 'success',
        'message' => 'Action Success',
        'data' => array(
            'icon' => $brief['icon'],
            'greeting' => $brief['greeting'],
            'line' => $brief['line'],
            'strip' => $brief['strip'],
            'next' => $brief['next'],
        ),
    ));
}

function pg_panel_user_pinned_app_update($request, $action)
{
    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    // The list is written straight into the operator's own user row, so it
    // has to come from a signed-in session with a valid token, and the
    // entries can only be menu item numbers.
    // Exempt from the general gate, which answered a request without a
    // session this way; the same answer is kept.
    if (!USER_LOGGED_IN) {
        respond(array(
            'status' => 'error',
            'message' => 'Invalid login.'
        ));
    }
    $user = validate_user();
    validate_token();

    $list = array();
    if (isset($request['list']) && is_array($request['list'])) {
        foreach ($request['list'] as $list_item) {
            $list[] = (int) $list_item;
        }
    }
    $list = implode(',', $list);
    $query =
        "UPDATE user
            SET selected_appmenu_items_array = '" . escape($list) . "'
            WHERE user_id = '" . (int) $user['id'] . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    //return success json output
    $response = array(
        'status' => 'success',
        'message' => 'Editing Success'
    );
    echo encode_json($response);
    exit();
}

function pg_panel_update_dashboard_widgets($request, $action)
{
    // The action sits on the general gate's exemption list because every
    // panel role may arrange its dashboard, and that gate would turn away
    // anyone above role 1. The exemption also skips the session and token
    // checks, so they happen here: without them an anonymous POST could
    // reset or reorder the site-wide layout.
    if (!USER_LOGGED_IN) {
        respond(array(
            'status' => 'error',
            'message' => 'Invalid login.'
        ));
    }

    validate_token();

    // What the dashboard stores is positions: one widget id per position,
    // in order, hidden cards included. The only other value the screen
    // sends is the word "default", which Reset Widgets writes to mean
    // "factory order" -- welcome.php reads it as an empty arrangement and
    // falls back to its registry order.
    //
    // Hidden ids are part of the list on purpose. A card that a setting has
    // switched off still owns a position, and welcome.php merges it back in
    // before saving; dropping it here would put it back at square one.
    //
    // The list is filtered to those two shapes rather than stored as it
    // arrives. It used to be joined and interpolated into the UPDATE
    // straight out of the request body, and no validation stood between the
    // browser and the statement.
    $message_text = (((string) ($request['message_text'] ?? '')) == 'restart') ? 'restart' : 'repositioning';

    $widgets = (isset($request['widgets']) && is_array($request['widgets'])) ? $request['widgets'] : array();

    if ((count($widgets) == 1) && (((string) reset($widgets)) == 'default')) {

        $order_widgets = 'default';

    } else {

        $widget_ids = array();

        foreach ($widgets as $widget_id) {

            $widget_id = (int) $widget_id;

            if (($widget_id > 0) && (!in_array($widget_id, $widget_ids, true))) {
                $widget_ids[] = $widget_id;
            }
        }

        // An empty list is refused rather than written. An empty column
        // reads as "no positions at all", and every load afterwards would
        // have to invent an arrangement -- which is a silent way to lose
        // one somebody built.
        if (empty($widget_ids)) {

            $response = array(
                'status' => 'error',
                'message' => 'No widget positions received.'
            );
            echo encode_json($response);
            exit();
        }

        $order_widgets = implode(',', $widget_ids);
    }

    db("UPDATE dashboard SET order_widgets = '" . escape($order_widgets) . "'");

    //return success json output
    $response = array(
        'status' => 'success',
        'message' => 'widget ' . $message_text . ' process successful.'
    );
    echo encode_json($response);
    exit();
}

function pg_panel_update_dashboard_appearance($request, $action)
{
    // A write from the panel session needs the session token like every
    // other one; a password-authenticated API request is waived inside
    // validate_token().
    validate_token();

    // How the dashboard looks: which of the four treatments the cards wear,
    // and what sits behind them. Both are site-wide and live on the
    // dashboard row beside the widget order. The panel sets them on the
    // Dashboard card of Site Settings > Appearance, whose save writes them
    // itself (includes/settings/appearance.save.php); this action sets one
    // of them per request, for a caller of api.php.
    //
    // Deliberately not exempt from the general gate (its row in
    // includes/panel/actions.php says exempt => false), so the gate applies:
    // signed in, and role 1 or better. That is the same reach as the
    // settings card (pg_dashboard_appearance_allowed()).
    //
    // Field and value are whitelisted rather than validated. One of them
    // becomes a column name and the other a stored slug, and neither has a
    // legitimate free-text form; the list is the one the dashboard and the
    // settings card read (pg_dashboard_appearance_choices()).
    $appearance_fields = pg_dashboard_appearance_choices();

    $appearance_field = (string) ($request['field'] ?? '');
    $appearance_value = (string) ($request['value'] ?? '');

    if ((!isset($appearance_fields[$appearance_field]))
        || (!in_array($appearance_value, $appearance_fields[$appearance_field], true))) {

        $response = array(
            'status' => 'error',
            'message' => 'Unknown appearance setting.'
        );
        echo encode_json($response);
        exit();
    }

    // The columns arrive with 2026.4.4. An installation whose files are
    // ahead of its database is told to run the upgrade rather than handed a
    // failed query: the caller cannot know which columns the row has.
    if (!pg_schema_has('dashboard', $appearance_field)) {

        $response = array(
            'status' => 'error',
            'message' => lang('Run the upgrade to use this setting.')
        );
        echo encode_json($response);
        exit();
    }

    db("UPDATE dashboard SET " . $appearance_field . " = '" . escape($appearance_value) . "'");

    //return success json output
    $response = array(
        'status' => 'success',
        'message' => 'dashboard appearance updated.'
    );
    echo encode_json($response);
    exit();
}

function pg_panel_tour_seen($request, $action)
{
    // A guided tour has been watched, or skipped -- which is the same answer to
    // the only question stored: do not open it by itself again.  The key is
    // checked against a pattern in pg_tour_mark() before it reaches the row.

    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    $user = validate_user();
    validate_token();

    pg_tour_mark($user['id'], (isset($request['key']) ? (string) $request['key'] : ''));

    respond(array('status' => 'success'));
}

function pg_panel_contact_quick_save($request, $action)
{
    // The account menu's quick form: the signed-in person's own first and
    // last name, into their address-book contact (includes/fn/contacts.php).

    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    $user = validate_user();
    validate_token();

    $result = pg_contact_quick_save($user['id'], (string) ($request['first_name'] ?? ''), (string) ($request['last_name'] ?? ''), (string) ($request['mobile_phone'] ?? ''), (string) ($request['photo'] ?? ''));

    respond($result['ok'] ? array('status' => 'success') : array('status' => 'error', 'message' => $result['error']));
}
