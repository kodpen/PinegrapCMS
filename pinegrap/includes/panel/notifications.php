<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Panel actions - the notification bell and browser push notifications.
 *
 *   check_unread_notifications  the bell's unread count
 *   edit_notifications          deletes or marks unread the chosen
 *                               notifications
 *   get_notifications           the bell's list, marking what it shows as
 *                               read
 *   remove_notifications        deletes every notification the caller can
 *                               see
 *   push_config                 the VAPID public key a browser subscribes
 *                               with
 *   push_subscribe              stores this browser's push subscription
 *   push_unsubscribe            drops this browser's push subscription
 *   push_test                   sends a test notification to the caller's
 *                               devices
 *   push_pending                what the service worker shows when a push
 *                               arrives
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

function pg_panel_check_unread_notifications($request, $action)
{
    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    $user = validate_user();
    include_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');

    // Read state is per person, so the unread filter is a join rather than a
    // column test; the visibility ladder then drops what is not this
    // person's to see.
    $number_of_unread = 0;

    foreach (pg_notification_unread_rows($user['id']) as $notification) {
        if (pg_notification_visible_to($notification, $user)) {
            $number_of_unread++;
        }
    }

    //return success json output
    $response = array(
        'status' => 'success',
        'number_of_unread_notifications' => $number_of_unread,
        'message' => 'Check Success'
    );
    echo encode_json($response);
    exit();
}

function pg_panel_edit_notifications($request, $action)
{
    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    $id = (int) $request['id'];
    $do_action = $request['do_action'];

    validate_token();
    $user = validate_user();
    include_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');

    $notification = db_item("SELECT " . pg_notification_ladder_columns() . " FROM notifications WHERE id = '" . $id . "'");

    // Acting on a notification requires being allowed to see it. The
    // branches this replaced agreed on that everywhere except comments,
    // where the test was missing and any signed-in account could delete
    // one.
    if (($notification) && (pg_notification_visible_to($notification, $user))) {

        if ($do_action === 'remove') {
            pg_notification_delete($id);
        } elseif ($do_action == 'mark_unread') {
            pg_notification_mark_unread($id, $user['id']);
        }
    }

    //return success json output
    $response = array(
        'status' => 'success',
        'message' => 'Delete Notification Success'
    );
    echo encode_json($response);
    exit();
}

function pg_panel_get_notifications($request, $action)
{
    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    $array = array();
    $read_mark = $request['read_mark'] ?? '';

    validate_token();
    $user = validate_user();
    include_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');

    $mark_read = array();
    $notifications = db_items("SELECT * FROM notifications ORDER BY timestamp DESC");

    foreach ($notifications as $notification) {

        if (!pg_notification_visible_to($notification, $user)) {
            continue;
        }

        $display = pg_notification_display($notification);

        $item = array(
            'id' => $notification['id'],
            'type' => $notification['type'],
            'title' => $display['title'],
            'description' => $display['description'],
            'details' => $display['details'],
            'user' => $notification['user'],
            'readed' => (pg_notification_is_read($notification, $user['id']) ? 1 : 0),
            'url' => $display['url'],
            'action' => $display['action'],
            'time' => get_relative_time(array('timestamp' => $notification['timestamp']))
        );

        array_push($array, $item);
        $mark_read[] = $notification['id'];
    }

    // One write for the whole list. Marking each row as it was drawn cost a
    // query per notification, and on a site with more than one operator it
    // marked the row read for all of them at once.
    if (($read_mark == true) && ($mark_read)) {
        pg_notification_mark_read($mark_read, $user['id']);
    }

    //return success json output
    $response = array(
        'status' => 'success',
        'data' => $array,
        'message' => 'Get Notifications Success'
    );
    echo encode_json($response);
    exit();
}

function pg_panel_remove_notifications($request, $action)
{
    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    validate_token();
    $user = validate_user();
    include_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');

    // returned in the response below; this branch never fills it
    $array = array();
    $i = 0;

    $notifications = db_items("SELECT " . pg_notification_ladder_columns() . " FROM notifications ORDER BY timestamp DESC");

    foreach ($notifications as $notification) {

        if (pg_notification_visible_to($notification, $user)) {
            pg_notification_delete($notification['id']);
            $i++;
        }
    }

    log_activity(lang(array('string' => '{var:1} notifications have been deleted.', 'vars' => $i)), $_SESSION['sessionusername']);

    //return success json output
    $response = array(
        'status' => 'success',
        'data' => $array,
        'message' => 'Delete Notifications Success'
    );
    echo encode_json($response);
    exit();
}

function pg_panel_push_config($request, $action)
{
    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    // Exempt from the general gate, which answered a request without a
    // session this way; the same answer is kept.
    if (!USER_LOGGED_IN) {
        respond(panel_login_refusal());
    }
    validate_token();
    $user = validate_user();
    include_once(PG_FUNCTIONS_DIR . '/includes/push.php');

    // The public key is what a browser needs to ask its push service for a
    // subscription. It is made on the first request that wants it, so a
    // site that never turns notifications on never grows a key pair.
    $public_key = pg_push_vapid_public_key();

    // The browser holds its own subscription and would go on reporting
    // itself subscribed after an operator ended it from the sessions
    // screen. It asks here whether this site still knows the endpoint.
    $known = false;

    if ((isset($request['endpoint'])) && ($request['endpoint'] != '')) {
        $known = pg_push_subscription_exists($request['endpoint']);
    }

    //return success json output
    $response = array(
        'status' => 'success',
        'data' => array(
            'available'  => (($public_key != '') ? true : false),
            'public_key' => $public_key,
            'known'      => $known
        ),
        'message' => 'Push Config Success'
    );
    echo encode_json($response);
    exit();
}

function pg_panel_push_subscribe($request, $action)
{
    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    // Exempt from the general gate, which answered a request without a
    // session this way; the same answer is kept.
    if (!USER_LOGGED_IN) {
        respond(panel_login_refusal());
    }
    validate_token();
    $user = validate_user();
    include_once(PG_FUNCTIONS_DIR . '/includes/push.php');

    $subscription = isset($request['subscription']) ? $request['subscription'] : array();
    $endpoint     = isset($subscription['endpoint']) ? $subscription['endpoint'] : '';
    $p256dh       = isset($subscription['keys']['p256dh']) ? $subscription['keys']['p256dh'] : '';
    $auth         = isset($subscription['keys']['auth']) ? $subscription['keys']['auth'] : '';

    // A browser that rotated its subscription reports what it had before;
    // the old row would otherwise sit there until a send failed on it.
    if ((isset($request['old_endpoint'])) && ($request['old_endpoint'] != '')) {
        pg_push_subscription_delete($request['old_endpoint']);
    }

    $saved = pg_push_subscription_save(
        $user['id'],
        $endpoint,
        $p256dh,
        $auth,
        (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '')
    );

    //return success json output
    $response = array(
        'status' => (($saved) ? 'success' : 'error'),
        'message' => (($saved) ? 'Push Subscribe Success' : 'Push Subscribe Failed')
    );
    echo encode_json($response);
    exit();
}

function pg_panel_push_unsubscribe($request, $action)
{
    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    // Exempt from the general gate, which answered a request without a
    // session this way; the same answer is kept.
    if (!USER_LOGGED_IN) {
        respond(panel_login_refusal());
    }
    validate_token();
    $user = validate_user();
    include_once(PG_FUNCTIONS_DIR . '/includes/push.php');

    pg_push_subscription_delete(isset($request['endpoint']) ? $request['endpoint'] : '');

    //return success json output
    $response = array(
        'status' => 'success',
        'message' => 'Push Unsubscribe Success'
    );
    echo encode_json($response);
    exit();
}

function pg_panel_push_test($request, $action)
{
    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    // Exempt from the general gate, which answered a request without a
    // session this way; the same answer is kept.
    if (!USER_LOGGED_IN) {
        respond(panel_login_refusal());
    }
    validate_token();
    $user = validate_user();
    include_once(PG_FUNCTIONS_DIR . '/includes/push.php');

    // Wakes this person's own devices and nobody else's. What each one then
    // shows is whatever the bell would show it, because the worker asks for
    // that itself - the push carries no text.
    $sent = pg_push_notify_user($user['id']);

    //return success json output
    $response = array(
        'status' => 'success',
        'data' => $sent,
        'message' => 'Push Test Success'
    );
    echo encode_json($response);
    exit();
}

function pg_panel_push_pending($request, $action)
{
    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    // Asked by the service worker, which has the session cookie but no way
    // to hold a form token: it is woken by the operating system, with no
    // page of its own to have been handed one. The request only reads, and
    // it reads exactly what the bell would have shown the same person.
    // Exempt from the general gate, which answered a request without a
    // session this way; the same answer is kept.
    if (!USER_LOGGED_IN) {
        respond(panel_login_refusal());
    }
    $user = validate_user();
    include_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');

    $array = array();

    foreach (pg_notification_unread_rows($user['id'], true) as $notification) {

        if (!pg_notification_visible_to($notification, $user)) {
            continue;
        }

        $display = pg_notification_display($notification);

        $array[] = array(
            'id'        => $notification['id'],
            'title'     => $display['title'],
            'body'      => pg_notification_body($display),
            'url'       => $display['url'],
            'icon'      => $display['icon'],
            'badge'     => $display['badge'],
            'tag'       => 'pg-notification-' . $notification['id'],
            'timestamp' => (int) $notification['timestamp']
        );

        // A device banner is not a list. Three is what the worker will show.
        if (count($array) >= 3) {
            break;
        }
    }

    // Conversations nobody has answered belong in the same list: the worker
    // is woken by both kinds and shows whatever is waiting, oldest concern
    // first.
    if (count($array) < 3) {

        include_once(PG_FUNCTIONS_DIR . '/chat.php');

        foreach (pg_chat_unread_for_push($user['id'], 3 - count($array)) as $conversation) {
            $array[] = $conversation;
        }
    }

    // Mentions and hand-overs in the workspace, the same way - unless the
    // bell carries them already (addressed rows, 4.83), which the loop
    // above has just listed.
    if ((count($array) < 3) && defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED && !pg_notification_targets_available()) {

        require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

        if (ws_ready()) {
            foreach (ws_push_pending($user, 3 - count($array)) as $item) {
                $array[] = $item;
            }
        }
    }

    //return success json output
    $response = array(
        'status' => 'success',
        'data' => $array,
        'message' => 'Push Pending Success'
    );
    echo encode_json($response);
    exit();
}
