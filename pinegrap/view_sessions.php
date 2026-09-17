<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 *
 * All sessions - every live sign-in token across every account, with per-row
 * sign-out and a per-user sign-out-everything action. The same screen answers
 * the POSTs from the sessions block on the edit-user screen (send_back).
 * Every sign-in carries an auth token, so every session is listed here;
 * sessions from before universal binding are bound automatically on their
 * next request.
 */

include('init.php');

$user = validate_user();
validate_area_access($user, 'manager');

// Device notifications are listed beside the sessions: a subscription outlives
// the page that made it and is invisible everywhere else, so this is the only
// screen that can show one - or take it away.
include_once(dirname(__FILE__) . '/includes/push.php');

// ---------------------------------------------------------------------------
// Actions (POST + CSRF): sign out one session, or every session of one user.
// The role rule matches login_as_user.php: a non-administrator may only act
// on users with a strictly lower-privileged role, and always on themselves.
// ---------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    validate_token_field();

    $session_action = isset($_POST['pg_session_action']) ? (string) $_POST['pg_session_action'] : '';
    $send_back = pg_safe_back_url((string) ($_POST['send_back'] ?? ''), PATH . SOFTWARE_DIRECTORY . '/view_sessions.php');

    $target_user_id = 0;
    $target_selector = '';

    if ($session_action === 'revoke') {
        $target_selector = isset($_POST['pg_selector']) ? (string) $_POST['pg_selector'] : '';
        if (!preg_match('/^[a-f0-9]{24}$/', $target_selector)) {
            go($send_back);
        }
        $target_user_id = (int) db_value("SELECT user_id FROM auth_tokens WHERE selector = '" . escape($target_selector) . "'");
    } elseif (($session_action === 'pin') || ($session_action === 'unpin')) {
        $target_selector = isset($_POST['pg_selector']) ? (string) $_POST['pg_selector'] : '';
        if (!preg_match('/^[a-f0-9]{24}$/', $target_selector)) {
            go($send_back);
        }
        $target_user_id = (int) db_value("SELECT user_id FROM auth_tokens WHERE selector = '" . escape($target_selector) . "'");
    } elseif (($session_action === 'revoke_push') || ($session_action === 'test_push')) {

        // The subscription's owner decides who may end it or send to it, so the
        // role rule below needs no branch of its own.
        $target_push_id = (int) ($_POST['pg_push_id'] ?? 0);

        if (($target_push_id > 0) && (pg_push_schema_available())) {
            $target_user_id = (int) db_value("SELECT user_id FROM push_subscriptions WHERE id = '" . $target_push_id . "'");
        }

    } elseif ($session_action === 'revoke_user') {
        $target_user_id = (int) ($_POST['pg_user_id'] ?? 0);
    } else {
        go($send_back);
    }

    // Nothing to act on (already revoked, or bad id): just go back.
    if ($target_user_id <= 0) {
        go($send_back);
    }

    $target_role = db_value("SELECT user_role FROM user WHERE user_id = '" . $target_user_id . "'");
    if (($target_role === '') || ($target_role === null)) {
        go($send_back);
    }
    if (($target_user_id !== (int) USER_ID) && (USER_ROLE > 0) && (USER_ROLE >= (int) $target_role)) {
        log_activity(lang(array('string' => 'access denied for user to sign out sessions of a higher-role user ({var:1})', 'vars' => array((string) $target_user_id))), USER_USERNAME);
        output_error(lang('Access denied.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    $target_username = db_value("SELECT user_username FROM user WHERE user_id = '" . $target_user_id . "'");

    if ($session_action === 'revoke') {
        pg_auth_token_revoke($target_selector);
        log_activity(lang(array('string' => 'signed out one device of user ({var:1})', 'vars' => array($target_username))), USER_USERNAME);

    } elseif (($session_action === 'pin') || ($session_action === 'unpin')) {

        // The column arrives with migration 4.17; on a database that has not
        // been upgraded the button simply does nothing rather than erroring.
        if (pg_auth_tokens_have_pin()) {
            db("UPDATE auth_tokens SET pinned = '" . (($session_action === 'pin') ? 1 : 0) . "'
                WHERE selector = '" . escape($target_selector) . "'");

            log_activity(lang(array(
                'string' => (($session_action === 'pin') ? 'locked a device of user ({var:1})' : 'unlocked a device of user ({var:1})'),
                'vars'   => array($target_username))), USER_USERNAME);
        }

    } elseif ($session_action === 'revoke_push') {

        db("DELETE FROM push_subscriptions WHERE id = '" . $target_push_id . "'");

        log_activity(lang(array('string' => 'stopped notifications on one device of user ({var:1})', 'vars' => array($target_username))), USER_USERNAME);

    } elseif ($session_action === 'test_push') {

        // One device, now, so that "is this actually working" has an answer
        // that does not involve waiting for somebody to place an order. The
        // push carries no text, as every push here does; the device wakes, asks
        // this site what is waiting, and shows whatever that is - the generic
        // wording when nothing is.
        $test_subscription = db_item("SELECT id, endpoint, p256dh, auth, user_agent
            FROM push_subscriptions WHERE id = '" . $target_push_id . "'");

        $test_result = ($test_subscription) ? pg_push_send_to_subscription($test_subscription) : array('ok' => false);

        log_activity(lang(array('string' => 'sent a test notification to one device of user ({var:1})', 'vars' => array($target_username))), USER_USERNAME);

        // The outcome travels in the address because the screen answers with a
        // redirect. Only whether it worked: the reason, when there is one, is
        // recorded on the row and shown in the table.
        $send_back .= ((strpos($send_back, '?') !== false) ? '&' : '?')
            . 'push_test=' . (($test_result['ok']) ? 'ok' : 'fail');

    } else {
        pg_auth_token_revoke_user($target_user_id);
        log_activity(lang(array('string' => 'signed out all devices of user ({var:1})', 'vars' => array($target_username))), USER_USERNAME);
    }

    // If that included this very browser's token, the next request ends this
    // session too (token-bound check) - intended.
    go($send_back);
}

// ---------------------------------------------------------------------------
// Listing. ?user=ID narrows to one account (linked from the user screen).
// ---------------------------------------------------------------------------
$filter_user_id = (int) ($_GET['user'] ?? 0);

$where = "WHERE auth_tokens.expires_at > '" . time() . "'";
if ($filter_user_id > 0) {
    $where .= " AND auth_tokens.user_id = '" . $filter_user_id . "'";
}

// The pinned column arrives with migration 4.17; on a database that has not
// been upgraded yet the query must not name it at all.
$pin_column = pg_auth_tokens_have_pin();

// With a limit of one, refusing new sign-ins already ties the account to a
// single device: the member's only way to another device is to sign out here
// first, and pinning does not stop that either. So the button would promise
// something it cannot add - it is shown disabled instead, with the reason.
// From two devices upward pinning does add something (it stops the member
// freeing a slot by signing out one of their OTHER devices), so it stays live.
$pin_redundant = (pg_device_limit_strict() && (pg_device_limit() === 1));

$sessions = db_items(
    "SELECT
        auth_tokens.selector,
        auth_tokens.user_id,
        auth_tokens.user_agent,
        auth_tokens.ip_address,
        auth_tokens.created_at,
        auth_tokens.last_used_at,
        auth_tokens.expires_at,
        user.user_username,
        user.user_google_id,
        user.user_password_algo" . ($pin_column ? ", auth_tokens.pinned" : "") . "
    FROM auth_tokens
    LEFT JOIN user ON auth_tokens.user_id = user.user_id
    " . $where . "
    ORDER BY GREATEST(auth_tokens.last_used_at, auth_tokens.created_at) DESC");

// This very browser's selector, to badge "this device".
$current_selector = '';
if (isset($_COOKIE['software']['auth'])) {
    $auth_parts = explode(':', (string) $_COOKIE['software']['auth'], 2);
    $current_selector = (string) $auth_parts[0];
}

$self_url = PATH . SOFTWARE_DIRECTORY . '/view_sessions.php' . (($filter_user_id > 0) ? '?user=' . $filter_user_id : '');

// Live session count per account, for the connected-accounts table below.
$session_counts = array();
$counts_result = mysqli_query(db::$con,
    "SELECT user_id, COUNT(*) AS live
    FROM auth_tokens
    WHERE expires_at > '" . time() . "'
    GROUP BY user_id");
if ($counts_result) {
    while ($count_row = mysqli_fetch_assoc($counts_result)) {
        $session_counts[(int) $count_row['user_id']] = (int) $count_row['live'];
    }
}

// Every account with an external sign-in connected to it. Sessions come and
// go; a connection outlives them, and it is the thing an operator has to be
// able to find - "who can get in with Google" is not answerable from a list
// of current sessions alone.
$connected_accounts = db_items(
    "SELECT user_id, user_username, user_email, user_role, user_password_algo, user_google_id
    FROM user
    WHERE (user_google_id IS NOT NULL) AND (user_google_id != '')
    ORDER BY user_username");

$output_connected_rows = '';
foreach ($connected_accounts as $account) {
    $account_id = (int) $account['user_id'];
    $output_connected_rows .=
        '<tr>'
        . '<td class="align-middle"><a href="edit_user.php?id=' . $account_id . '">' . h((string) $account['user_username']) . '</a></td>'
        . '<td class="align-middle">' . h((string) $account['user_email']) . '</td>'
        . '<td class="align-middle">' . h(pg_user_role_name($account['user_role'])) . '</td>'
        . '<td class="align-middle">' . pg_connected_account_label($account['user_google_id'], $account['user_password_algo']) . '</td>'
        . '<td class="align-middle">' . (isset($session_counts[$account_id]) ? (int) $session_counts[$account_id] : 0) . '</td>'
        . '<td class="align-middle text-end"><a href="edit_user.php?id=' . $account_id . '" class="btn btn-sm btn-outline-secondary">' . lang('Manage') . '</a></td>'
        . '</tr>';
}

if ($output_connected_rows === '') {
    $output_connected_rows = '<tr><td colspan="6">' . lang('No connected accounts.') . '</td></tr>';
}

// Devices that have asked to be notified.
//
// A subscription is not a session: it belongs to a browser, survives a closed
// window, and until now had no screen of its own. Listing it here answers the
// question an operator actually has - "what is this account being notified on"
// - and gives them the one control that matters, which is stopping it.
$push_available = pg_push_schema_available();
$push_subscriptions = array();

if ($push_available) {

    $push_where = ($filter_user_id > 0) ? " WHERE push_subscriptions.user_id = '" . $filter_user_id . "'" : '';

    $push_subscriptions = db_items(
        "SELECT
            push_subscriptions.id,
            push_subscriptions.user_id,
            push_subscriptions.user_agent,
            push_subscriptions.created_timestamp,
            push_subscriptions.last_ok_timestamp,
            push_subscriptions.fail_count,
            " . ((pg_push_has_selector()) ? "push_subscriptions.auth_selector," : "'' AS auth_selector,") . "
            " . ((pg_push_has_last_error()) ? "push_subscriptions.last_error," : "'' AS last_error,") . "
            user.user_username
        FROM push_subscriptions
        LEFT JOIN user ON push_subscriptions.user_id = user.user_id"
        . $push_where . "
        ORDER BY push_subscriptions.created_timestamp DESC");
}

// Which of them still have a live sign-in behind them. A subscription whose
// session has gone is not an error - a browser can be signed out somewhere the
// software never hears about - but it is worth showing, because that device
// can only be told that something happened, never what.
$push_live_selectors = array();

if (($push_subscriptions) && (pg_push_has_selector())) {

    foreach (db_values("SELECT selector FROM auth_tokens WHERE expires_at > '" . time() . "'") as $live_selector) {
        $push_live_selectors[(string) $live_selector] = true;
    }
}

// What the test button reports back. The redirect carries only whether the
// push was accepted by the push service - whether the device then drew a
// banner is between the device and its owner, and the row's own status column
// carries the reason when there was a failure.
$output_push_test_note = '';

if (isset($_GET['push_test'])) {

    $output_push_test_note = (($_GET['push_test'] === 'ok')
        ? '<div class="alert alert-success">' . lang('A test notification was sent to that device. It can take a few seconds to arrive, and a browser that is completely closed receives it when it is next opened.') . '</div>'
        : '<div class="alert alert-warning">' . lang('The test notification could not be sent. The reason is on the device\'s row, under the delivery column.') . '</div>');
}

$output_push_rows = '';

foreach ($push_subscriptions as $subscription) {

    $subscription_selector = (string) $subscription['auth_selector'];
    $is_this_device = (($current_selector !== '') && ($subscription_selector !== '') && hash_equals($subscription_selector, $current_selector));
    $has_session = (($subscription_selector !== '') && isset($push_live_selectors[$subscription_selector]));

    if (((int) $subscription['fail_count']) > 0) {
        $status = '<span class="badge bg-warning-subtle text-warning-emphasis fw-light" title="'
            . h((string) $subscription['last_error']) . '">'
            . h(lang(array('string' => '{var:1} failed attempts', 'vars' => array((int) $subscription['fail_count'])))) . '</span>';
    } elseif (((int) $subscription['last_ok_timestamp']) > 0) {
        $status = get_relative_time(array('timestamp' => (int) $subscription['last_ok_timestamp']));
    } else {
        $status = '<span class="text-body-secondary">' . h(lang('Nothing sent yet')) . '</span>';
    }

    $output_push_rows .=
        '<tr>'
        . '<td class="align-middle"><a href="edit_user.php?id=' . (int) $subscription['user_id'] . '">' . h((string) $subscription['user_username']) . '</a></td>'
        . '<td class="align-middle" title="' . h(trim((string) $subscription['user_agent'])) . '">' . h(pg_user_agent_label($subscription['user_agent']))
        . ($is_this_device ? ' <span class="badge bg-success fw-light">' . h(lang('This device')) . '</span>' : '')
        . '</td>'
        . '<td class="align-middle">'
        . (($subscription_selector === '')
            ? '<span class="text-body-secondary" title="' . h(lang('This subscription was made before the software started recording which browser a subscription belongs to, so it cannot be matched to a sign-in. Turning notifications off and on again on that device records it.')) . '">&mdash;</span>'
            : ($has_session
                ? '<span class="badge bg-success-subtle text-success-emphasis fw-light">' . h(lang('Signed in')) . '</span>'
                : '<span class="badge bg-secondary-subtle text-body-secondary fw-light" title="' . h(lang('This browser has no live sign-in, so it can only be told that something happened - never what.')) . '">' . h(lang('Signed out')) . '</span>'))
        . '</td>'
        . '<td class="align-middle">' . h(date('Y-m-d H:i', (int) $subscription['created_timestamp'])) . '</td>'
        . '<td class="align-middle">' . $status . '</td>'
        . '<td class="align-middle text-end">'
        . '<div class="d-inline-flex gap-1">'
        . '<form method="post" action="view_sessions.php" style="margin:0">' . get_token_field()
        . '<input type="hidden" name="pg_session_action" value="test_push"/>'
        . '<input type="hidden" name="pg_push_id" value="' . (int) $subscription['id'] . '"/>'
        . '<input type="hidden" name="send_back" value="' . h($self_url) . '"/>'
        . '<button type="submit" class="btn btn-sm btn-outline-secondary" title="' . h(lang('Sends one notification to this device now, so you can see whether it arrives.')) . '">' . h(lang('Test')) . '</button>'
        . '</form>'
        . '<form method="post" action="view_sessions.php" style="margin:0">' . get_token_field()
        . '<input type="hidden" name="pg_session_action" value="revoke_push"/>'
        . '<input type="hidden" name="pg_push_id" value="' . (int) $subscription['id'] . '"/>'
        . '<input type="hidden" name="send_back" value="' . h($self_url) . '"/>'
        . '<button type="submit" class="btn btn-sm btn-outline-danger" title="' . h(lang('The device stops receiving notifications. Whoever uses it can turn them on again from the bell in their own panel.')) . '">' . h(lang('Stop')) . '</button>'
        . '</form>'
        . '</div>'
        . '</td>'
        . '</tr>';
}

if ($output_push_rows === '') {
    $output_push_rows = '<tr><td colspan="6">' . lang('No device is subscribed to notifications.') . '</td></tr>';
}

$output_rows = '';
foreach ($sessions as $s) {
    $is_current = (($current_selector !== '') && hash_equals((string) $s['selector'], $current_selector));
    $fresh = max((int) $s['last_used_at'], (int) $s['created_at']);
    $is_pinned = ($pin_column && isset($s['pinned']) && (((int) $s['pinned']) === 1));
    // get_relative_time() returns a <time> element, not plain text: escaping
    // it printed the markup instead of the date.
    $last = ((time() - $fresh) < 300)
        ? '<span class="badge bg-success fw-light">' . h(lang('Online')) . '</span>'
        : get_relative_time(array('timestamp' => $fresh));
    $output_rows .=
        '<tr>'
        . '<td class="align-middle"><a href="edit_user.php?id=' . (int) $s['user_id'] . '">' . h((string) $s['user_username']) . '</a></td>'
        . '<td class="align-middle" title="' . h(trim((string) $s['user_agent'])) . '">' . h(pg_user_agent_label($s['user_agent']))
        . ($is_current ? ' <span class="badge bg-success fw-light">' . h(lang('This device')) . '</span>' : '')
        . ($is_pinned ? ' <span class="badge bg-secondary fw-light" title="' . h(lang('Locked by the site owner: the device limit cannot evict it and the member cannot sign it out.')) . '"><i class="bi bi-pin-angle-fill"></i> ' . h(lang('Locked')) . '</span>' : '')
        . '</td>'
        . '<td class="align-middle">' . pg_connected_account_label($s['user_google_id'], $s['user_password_algo']) . '</td>'
        . '<td class="align-middle">' . h((string) $s['ip_address']) . '</td>'
        . '<td class="align-middle">' . h(date('Y-m-d H:i', (int) $s['created_at'])) . '</td>'
        . '<td class="align-middle">' . $last . '</td>'
        . '<td class="align-middle">' . h(date('Y-m-d H:i', (int) $s['expires_at'])) . '</td>'
        . '<td class="align-middle text-end">'
        . '<div class="d-inline-flex gap-1">'
        . ($pin_column
            ? (($pin_redundant && !$is_pinned)
                ? ('<button type="button" class="btn btn-sm btn-outline-secondary disabled" disabled title="'
                    . h(lang('The setting "Refuse new sign-ins when the limit is full" already ties this account to one device, so pinning a single session adds nothing here. It becomes useful with a limit of two or more, where it stops the member freeing a slot by signing out one of their other devices.')) . '"><i class="bi bi-pin-angle-fill me-1"></i>' . h(lang('Locked')) . '</button>')
                : ('<form method="post" action="view_sessions.php" style="margin:0">' . get_token_field()
                . '<input type="hidden" name="pg_session_action" value="' . ($is_pinned ? 'unpin' : 'pin') . '"/>'
                . '<input type="hidden" name="pg_selector" value="' . h((string) $s['selector']) . '"/>'
                . '<input type="hidden" name="send_back" value="' . h($self_url) . '"/>'
                . '<button type="submit" class="btn btn-sm btn-outline-secondary" title="' . h(lang('Pins THIS session: the device limit cannot evict it and the member cannot sign it out from their own screen. Different from the setting "Refuse new sign-ins when the limit is full", which only stops new devices - here the account is tied to this one.')) . '">'
                . '<i class="bi ' . ($is_pinned ? 'bi-unlock' : 'bi-pin-angle') . ' me-1"></i>'
                . h($is_pinned ? lang('Unlock') : lang('Lock')) . '</button></form>'))
            : '')
        . '<form method="post" action="view_sessions.php" style="margin:0">' . get_token_field()
        . '<input type="hidden" name="pg_session_action" value="revoke"/>'
        . '<input type="hidden" name="pg_selector" value="' . h((string) $s['selector']) . '"/>'
        . '<input type="hidden" name="send_back" value="' . h($self_url) . '"/>'
        . '<button type="submit" class="btn btn-sm btn-outline-danger">' . lang('Sign out') . '</button>'
        . '</form>'
        . '</div>'
        . '</td>'
        . '</tr>';
}

if ($output_rows === '') {
    $output_rows = '<tr><td colspan="8">' . lang('No active sessions.') . '</td></tr>';
}

$output_filter_note = '';
if ($filter_user_id > 0) {
    $output_filter_note =
        '<p class="mb-3"><a href="view_sessions.php">' . lang('All Sessions') . '</a></p>';
}

// The trail carries real ancestry only, which this screen has just one of:
// the whole list, when it has been narrowed to a single account. The settings
// are not above it -- they are a dialog that opens over whatever is on screen,
// and the back control at the head of the strip is what offers them.
$breadcrumb = array();

if ($filter_user_id > 0) {
    $breadcrumb[] = array('label' => lang('All Sessions'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_sessions.php');
    $breadcrumb[] = array('label' => (string) db_value("SELECT user_username FROM user WHERE user_id = '" . $filter_user_id . "'"));
}

echo pg_page_shell(array(
    'title' => lang('Sessions and connected accounts'),
    'extra classes' => 'users',
    'icon' => 'devices',
    'heading' => lang('Sessions and connected accounts'),
    'heading_description' => lang('Active sign-ins on all accounts'),
    'breadcrumb' => $breadcrumb,
));

print '<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="row mb-2 flex-wrap">
                <div class="col-12 text-center text-md-start">
                    <h2 class="d-inline-block">' . lang('All Sessions') . '</h2>
                </div>
            </div>
            ' . $output_filter_note . '
            <div class="alert alert-secondary">' . lang('Every sign-in carries a session token and is listed here. Sessions started before this feature are bound automatically on their next request.') . '</div>
            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" style="width:100%">
                            <thead>
                                <tr>
                                    <th>' . lang('User') . '</th>
                                    <th>' . lang('Device') . '</th>
                                    <th>' . lang('Connected account') . '</th>
                                    <th>' . lang('IP address') . '</th>
                                    <th>' . lang('Created') . '</th>
                                    <th>' . lang('Last used') . '</th>
                                    <th>' . lang('Expires') . '</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>' . $output_rows . '</tbody>
                        </table>
                    </div>
                </div>
            </div>
' . (($push_available) ? '
            <div class="row mb-2 flex-wrap mt-4">
                <div class="col-12 text-center text-md-start">
                    <h2 class="d-inline-block">' . lang('Notification subscriptions') . '</h2>
                </div>
            </div>
            ' . $output_push_test_note . '
            <div class="alert alert-secondary">' . lang('Every browser an operator turned notifications on in is listed here. A subscription belongs to the browser rather than to the sign-in, so it survives a closed window; signing out of a device ends it, and so does the button below.') . '</div>
            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" style="width:100%">
                            <thead>
                                <tr>
                                    <th>' . lang('User') . '</th>
                                    <th>' . lang('Device') . '</th>
                                    <th>' . lang('Sign-in') . '</th>
                                    <th>' . lang('Subscribed') . '</th>
                                    <th>' . lang('Last delivery') . '</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>' . $output_push_rows . '</tbody>
                        </table>
                    </div>
                </div>
            </div>
' : '') . '
            <div class="row mb-2 flex-wrap mt-4">
                <div class="col-12 text-center text-md-start">
                    <h2 class="d-inline-block">' . lang('Connected accounts') . '</h2>
                </div>
            </div>
            <div class="alert alert-secondary">' . lang('Accounts that can sign in through an external provider. The connection is tied to the provider account itself, not to the email address, and it outlives any session - remove it on the user screen.') . '</div>
            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" style="width:100%">
                            <thead>
                                <tr>
                                    <th>' . lang('User') . '</th>
                                    <th>' . lang('Email') . '</th>
                                    <th>' . lang('Role') . '</th>
                                    <th>' . lang('Connected account') . '</th>
                                    <th>' . lang('Sessions') . '</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>' . $output_connected_rows . '</tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>';

echo output_footer();
