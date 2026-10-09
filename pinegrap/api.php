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
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 * 
 */


// A file dragged into the file manager arrives as one large json body, base64 encoded.  Reading
// it and parsing it leaves two copies of it in memory at once, and memory_limit is sized for
// ordinary page requests, so a file well inside what the server could carry used to end the
// request with "Allowed memory size exhausted" halfway through.
//
// The room has to be made here, before the body is read: by the time a handler could ask for it
// the allocation that fails has already been attempted.  Only a request that is actually
// carrying something large is lifted, only as far as that body needs, and never below what the
// server was already set to.  functions.php has the same ceiling under
// pg_upload_memory_ceiling(), which is what the file manager quotes as its limit; if you change
// one, change the other.  ini_set is allowed to fail: a host that forbids it simply keeps the
// behaviour we had before.
$request_content_length = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;

if ($request_content_length > (4 * 1024 * 1024)) {

    $memory_setting = trim((string) @ini_get('memory_limit'));
    $memory_unit    = strtolower(substr($memory_setting, -1));
    $memory_current = (float) $memory_setting;

    if ($memory_unit == 'g') { $memory_current = $memory_current * 1024 * 1024 * 1024; }
    elseif ($memory_unit == 'm') { $memory_current = $memory_current * 1024 * 1024; }
    elseif ($memory_unit == 'k') { $memory_current = $memory_current * 1024; }

    $memory_ceiling = defined('UPLOAD_MEMORY_CEILING') ? trim((string) UPLOAD_MEMORY_CEILING) : '512M';
    $ceiling_unit   = strtolower(substr($memory_ceiling, -1));
    $ceiling_bytes  = (float) $memory_ceiling;

    if ($ceiling_unit == 'g') { $ceiling_bytes = $ceiling_bytes * 1024 * 1024 * 1024; }
    elseif ($ceiling_unit == 'm') { $ceiling_bytes = $ceiling_bytes * 1024 * 1024; }
    elseif ($ceiling_unit == 'k') { $ceiling_bytes = $ceiling_bytes * 1024; }

    // Two copies of the body, plus room for the software itself and the work around it.
    $memory_needed = (2 * $request_content_length) + (48 * 1024 * 1024);

    if ($memory_needed > $ceiling_bytes) {
        $memory_needed = $ceiling_bytes;
    }

    // -1 (unlimited) reads as 0 here, and there is nothing to raise in that case.
    if (($memory_current > 0) && ($memory_needed > $memory_current)) {
        @ini_set('memory_limit', (int) $memory_needed);
    }

}

$raw_request = @file_get_contents('php://input');

$request = json_decode($raw_request, true);

// PHP throws the whole body away when the request is larger than post_max_size, and the script then
// runs with nothing in it.  Without this the answer would be "Invalid token", which sends everybody
// looking in the wrong place; the real reason is the size of the request.  We cannot look at
// CONTENT_LENGTH here, because PHP sets it to zero when it discards the body, so we go by the fact
// that a json request without a body never happens on purpose.
if (($request === null) && ($raw_request === '') && ($_SERVER['REQUEST_METHOD'] == 'POST') &&
    (isset($_SERVER['CONTENT_TYPE'])) && (stripos($_SERVER['CONTENT_TYPE'], 'json') !== false)) {

    header('Content-Type: application/json');

    // work out what this server would have taken, so the screen can lower its own limit to match
    $post_setting = trim((string) @ini_get('post_max_size'));

    $post_unit = strtolower(substr($post_setting, -1));

    $post_bytes = (float) $post_setting;

    if ($post_unit == 'g') { $post_bytes = $post_bytes * 1024 * 1024 * 1024; }
    elseif ($post_unit == 'm') { $post_bytes = $post_bytes * 1024 * 1024; }
    elseif ($post_unit == 'k') { $post_bytes = $post_bytes * 1024; }

    $upload_limit_bytes = ($post_bytes > 0) ? (int) floor(max(0, $post_bytes - 65536) * 0.74) : 0;

    print json_encode(array(
        'status' => 'error',
        'upload_limit_bytes' => $upload_limit_bytes,
        'post_max_size' => $post_setting,
        'message' => 'The request was larger than this server accepts in one request (post_max_size ' . $post_setting . '). Raise post_max_size and upload_max_filesize, or send a smaller file.'));

    exit();

}

// The body has been parsed, so the raw copy is nothing but weight.  It matters on uploads,
// where the JSON carries a base64 payload of tens of megabytes: holding the raw text and the
// decoded JSON at the same time is already two copies of the file before anything is written.
//
// The size is kept because pg_upload_limits() works the upload allowance out from the memory
// peak, and by this point that peak holds both of those copies.  Without knowing how much of
// it belongs to the body, the allowance would shrink as the file grows -- the bigger the file,
// the smaller the limit it is measured against -- and a file that uploaded yesterday would be
// turned away as too large today.
$GLOBALS['pg_request_body_bytes'] = strlen($raw_request);

// A request from a page drawn in another language says so (pg_lang, in the
// query string or the body), so what it is answered with - a product's
// description, a shipping method's name - is in that language too.
if (function_exists('pg_tr_define_from_request')) {
    pg_tr_define_from_request($request);
}

unset($raw_request);

// If login info was included in the request, then store it, so that initialize_user() can login user.
//
// Both halves or neither. A body carrying a name and no password used to define
// the pair anyway, with the password reading as null, which turned a malformed
// request into a sign-in attempt against a password nobody sent.
if (isset($request['username']) && is_string($request['username']) && ($request['username'] !== '')
    && isset($request['password']) && is_string($request['password'])) {
    define('API_USERNAME', $request['username']);
    // Raw password; initialize_user() verifies it against the stored hash.
    define('API_PASSWORD', $request['password']);
}

include('init.php');

// Add header in order to start response.
header('Content-Type: application/json');
if (isset($request['action'])) {
    $action = $request['action'];
} else {
    $action = 'Null';
}
if (isset($request['token'])) {
    $token = $request['token'];
}


// We only do access control checks for certain sensitive actions.
// Some actions have their own access control checks further below.
if (
    ($action != 'add_to_cart')
    and ($action != 'get_product')
    and ($action != 'get_installment_options')
    and ($action != 'eo_get_installments')
    and ($action != 'eo_default_tree')
    and ($action != 'eo_required_sections')
    and ($action != 'get_shipping_methods')
    and ($action != 'get_delivery_date')
    and ($action != 'get_cross_sell_items')
    and ($action != 'get_cross_sell_for_product')
    and ($action != 'update_product_status')
    and ($action != 'update_product_group_status')
    and ($action != 'get_unselected_products')

    and ($action != 'upload_file')

    // Arranging the dashboard is open to every backend role, so the action
    // is exempted from the role <= 1 gate below; the case block checks the
    // session and the token for itself.
    and ($action != 'update_dashboard_widgets')
    and ($action != 'software_backup')
    and ($action != 'software_update')

    and ($action != 'remove_notifications')
    and ($action != 'get_notifications')
    and ($action != 'check_unread_notifications')
    and ($action != 'edit_notifications')

    and ($action != 'get_widget_data')

    and ($action != 'file_explorer')

    // Noting that someone has watched a guided tour is theirs to do whatever
    // their role, and the case block below checks the session and the token
    // for itself.  The general gate here wants role 1 or better, which would
    // leave a basic user watching the same tour on every visit.
    and ($action != 'tour_seen')

    // The account menu's quick form for one's own name: every role, the case
    // block checks the session and the token.
    and ($action != 'contact_quick_save')

    and ($action != 'backend_search')

    and ($action != 'sort_menu_items')

    and ($action != 'software_update_check')

    and ($action != 'user_online_check')

    // Live chat actions are open to all backend roles (role 3 included):
    // the general gate below requires role <= 1, so they are exempted here.
    // Session + token checks happen in the case block; role/pairing/
    // ownership rules are enforced inside chat.php.
    and (strpos($action, 'chat_') !== 0)

    // Site chat: visitor endpoints — they work without a login; token,
    // ownership, captcha and rate limiting live inside chat.php.
    and (strpos($action, 'site_chat_') !== 0)

    // The workspace is open to its team, role 3 members included; the case
    // block checks the session and the token, the module decides the rest.
    and (strpos($action, 'ws_') !== 0)

    // Every panel page fires the sitemap check whatever the visitor's role,
    // so it is exempted from the role <= 1 gate below; the case block checks
    // the session and the token for itself.
    and ($action != 'sitemap_check')

    and ($action != 'shared_component')

    and ($action != 'designer_file')

    // The visual editor is no longer designer-only. A manager or a user with
    // content rights opens it to edit text, images and the areas marked for
    // them, and the editor needs its endpoints to do that — presence, page
    // load, the SEO check. The `designer` case has its own allow-list and
    // refuses everything that shapes the design, so the general gate here
    // would only be a second, blunter copy of a decision made properly one
    // switch below.
    and ($action != 'designer')
    // The offer editor belongs to whoever manages the store (roles 0-2, or a
    // basic user with manage_ecommerce); the handler checks that itself.
    and ($action != 'offer_editor')

    // The System Status widget's three jobs. Each checks the role it needs for
    // itself -- the table scan and the cache purge stand at manager, the rule
    // writer at administrator -- while the general gate here wants role 1 or
    // better and would turn away the manager the widget draws the tiles for. A
    // tile offered to somebody the endpoint then refuses is worse than a tile
    // that was never drawn.
    and ($action != 'database_deep_check')
    and ($action != 'server_config_repair')
    and ($action != 'write_permissions_repair')
    and ($action != 'purge_cache')

    // Browser notifications for this device. The bell they hang from is drawn
    // for every backend role, and each of these touches only the caller's own
    // subscriptions and devices, so every role may use them; the case blocks
    // check the session and the token for themselves.
    and ($action != 'push_config')
    and ($action != 'push_subscribe')
    and ($action != 'push_unsubscribe')
    and ($action != 'push_test')

    // Asked by the service worker, which cannot hold a form token. It only
    // reads what the bell would show the same person, so the case block asks
    // for the session alone.
    and ($action != 'push_pending')

    // Personal settings: the pinned apps live in the caller's own user row and
    // the front-end toolbar's open/closed state in the caller's own session.
    // Every role; the case blocks check the session and the token.
    and ($action != 'user_pinned_app_update')
    and ($action != 'update_toolbar_properties')

    // Product barcodes and the label template are drawn on the product
    // screens, which admit whoever manages the store (roles 0-2, or a basic
    // user with manage_ecommerce). The case blocks apply that same rule, so
    // an endpoint never refuses a button its screen offered.
    and ($action != 'get_product_barcodes')
    and ($action != 'generate_product_barcode')
    and ($action != 'save_product_barcode')
    and ($action != 'delete_product_barcode')
    and ($action != 'bulk_assign_barcodes')
    and ($action != 'save_barcode_template')

) {

    // The password was right but the account has a second step, which a
    // request carrying a password cannot give (initialize_user() loaded no
    // user). Said apart from "Invalid login." so the client can tell the
    // person what to do instead of asking for the password again.
    if (defined('API_MFA_REQUIRED')) {
        header('HTTP/1.1 401 Unauthorized');
        respond(array(
            'status' => 'error',
            'code' => 'mfa_required',
            'message' => lang('This account uses two-step verification and cannot sign in with a password here. Sign in on the website, or use an application key.')
        ));
    }

    // If a user was not found then respond with an error.
    if (!USER_LOGGED_IN) {
        respond(array(
            'status' => 'error',
            'message' => 'Invalid login.'
        ));
    }

    if ($action != 'get_form' and $action != 'get_forms') {
        // If the user does not have a designer or administrator role, then respond with an error.
        if (USER_ROLE > 1) {
            respond(array(
                'status' => 'error',
                'message' => 'The User must have a Designer or Administrator role.'
            ));
        }
    }
}

require_once(dirname(__FILE__) . '/includes/panel/actions.php');

pg_panel_dispatch($action, $request);

switch ($action) {

    case 'user_online_check':
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
        break;

    // ── Live chat (backend) ─────────────────────────────────────────────
    // Single gate: session + CSRF token here; application rules (pairing
    // rule, side/ownership, rate limit, delete authority) live in chat.php.
    // When the schema has not been upgraded yet,
    // pg_chat_handle_backend_action returns a polite error — no page
    // breaks (pg_chat_ready probe).
    case 'chat_bootstrap':
    case 'chat_online_users':
    case 'chat_open':
    case 'chat_send':
    case 'chat_poll':
    case 'chat_unread_check':
    case 'chat_mark_read':
    case 'chat_conversations':
    case 'chat_close':
    case 'chat_delete':
    case 'chat_attach':

        if (!USER_LOGGED_IN) {
            respond(array(
                'status' => 'error',
                'message' => 'Invalid login.'
            ));
        }

        validate_token();

        require_once(dirname(__FILE__) . '/chat.php');

        respond(pg_chat_handle_backend_action($action, $request));

        break;

    // ── Workspace ───────────────────────────────────────────────────────
    // Session + CSRF token here; who may read, post, assign and plan is
    // decided in includes/workspace/actions.php.
    case 'ws_bootstrap':
    case 'ws_channels':
    case 'ws_channel_open':
    case 'ws_channel_get':
    case 'ws_message_seen':
    case 'ws_scheduled_list':
    case 'ws_scheduled_get':
    case 'ws_scheduled_targets':
    case 'ws_scheduled_save':
    case 'ws_scheduled_status':
    case 'ws_scheduled_run_now':
    case 'ws_scheduled_preview':
    case 'ws_scheduled_message_save':
    case 'ws_scheduled_messages':
    case 'ws_scheduled_message_delete':
    case 'ws_scheduled_message_send':
    case 'ws_sync':
    case 'ws_messages_before':
    case 'ws_send':
    case 'ws_edit':
    case 'ws_delete':
    case 'ws_mark':
    case 'ws_attach':
    case 'ws_channel_create':
    case 'ws_templates':
    case 'ws_template_apply':
    case 'ws_template_from_channel':
    case 'ws_channel_update':
    case 'ws_customer_links':
    case 'ws_channel_members_add':
    case 'ws_mention_invite':
    case 'ws_channel_member_remove':
    case 'ws_channel_join':
    case 'ws_channel_leave':
    case 'ws_channel_make_public':
    case 'ws_channel_make_private':
    case 'ws_channel_eras':
    case 'ws_pin':
    case 'ws_pin_hide':
    case 'ws_forward':
    case 'ws_block_search':
    case 'ws_block_pull':
    case 'ws_channel_clear':
    case 'ws_channel_era_rename':
    case 'ws_group_save':
    case 'ws_group_move':
    case 'ws_group_remove':
    case 'ws_group_access':
    case 'ws_group_access_grant':
    case 'ws_group_access_revoke':
    case 'ws_channel_group':
    case 'ws_channel_archive':
    case 'ws_channel_audit':
    case 'ws_audit_list':
    case 'ws_timeline':
    case 'ws_home':
    case 'ws_tick':
    case 'ws_calc':
    case 'ws_notes':
    case 'ws_note_get':
    case 'ws_note_save':
    case 'ws_note_delete':
    case 'ws_note_from_message':
    case 'ws_note_share':
    case 'ws_note_channel':
    case 'ws_note_unshare':
    case 'ws_note_file':
    case 'ws_note_claude':
    case 'ws_note_claude_deliver':
    case 'ws_file_text':
    case 'ws_file_text_save':
    case 'ws_file_history':
    case 'ws_channel_notify':
    case 'ws_channel_pin':
    case 'ws_channel_order':
    case 'ws_channel_summary':
    case 'ws_channel_decisions':
    case 'ws_channel_files':
    case 'ws_channel_tasks':
    case 'ws_search':
    case 'ws_palette':
    case 'ws_channel_board':
    case 'ws_ref_search':
    case 'ws_record_refs':
    case 'ws_task_get':
    case 'ws_task_check':
    case 'ws_task_save':
    case 'ws_task_status':
    case 'ws_task_move':
    case 'ws_tasks':
    case 'ws_board':
    case 'ws_conflicts':
    case 'ws_calendar':
    case 'ws_event_save':
    case 'ws_event_get':
    case 'ws_event_delete':
    case 'ws_inbox':
    case 'ws_inbox_read':
    case 'ws_react':
    case 'ws_check':
    case 'ws_poll_create':
    case 'ws_poll_vote':
    case 'ws_poll_close':
    case 'ws_poll_edit':
    case 'ws_task_tick':
    case 'ws_task_item_add':
    case 'ws_task_note_add':
    case 'ws_task_note_edit':
    case 'ws_task_note_delete':
    case 'ws_task_recurrence_end':
    case 'ws_channel_claude':
    case 'ws_ai_draft_accept':
    case 'ws_ai_draft_dismiss':
    case 'ws_ai_change_apply':
    case 'ws_ai_change_dismiss':
    case 'ws_ai_bulk_step':
    case 'ws_ai_design_apply':
    case 'ws_ai_design_revert':
    case 'ws_ai_design_dismiss':
    case 'ws_claude_kick':
    case 'ws_ai_kick':
    case 'ws_channel_ai':
    case 'ws_guest_start':
    case 'ws_guest_relink':
    case 'ws_guest_end':
    case 'ws_channel_share':
    case 'ws_thread_start':
    case 'ws_thread_join':
    case 'ws_thread_close':
    case 'ws_thread_rename':
    case 'ws_thread_leave':
    case 'ws_share_relink':
    case 'ws_share_end':

        if (!USER_LOGGED_IN) {
            respond(array(
                'status' => 'error',
                'message' => 'Invalid login.'
            ));
        }

        validate_token();

        require_once(dirname(__FILE__) . '/includes/workspace/actions.php');

        respond(ws_handle_action($action, $request));

        break;

    // ── Live chat (site) ────────────────────────────────────────────────
    // Visitor/member endpoints. bootstrap hands the token to the client;
    // every other action validates it inside chat.php
    // (pg_chat_site_token_ok). Ownership is session-based (same pattern as
    // the cart's order_id); captcha and rate limiting live in the module.
    case 'site_chat_bootstrap':
    case 'site_chat_captcha':
    case 'site_chat_send':
    case 'site_chat_poll':
    case 'site_chat_update_identity':
    case 'site_chat_attach':

        require_once(dirname(__FILE__) . '/chat.php');

        respond(pg_chat_handle_site_action($action, $request));

        break;

    case 'sitemap_check':

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
        break;

    case 'get_widget_data':
        $user = validate_user();
        // Release the session file lock immediately after authentication so that
        // concurrent widget AJAX requests are not serialized waiting for each other.
        session_write_close();

        require_once(dirname(__FILE__) . '/includes/dashboard/widgets.php');

        respond(pg_dashboard_widget_run($request, $user));
        break;
    case 'check_unread_notifications':
        $user = validate_user();
        include_once(dirname(__FILE__) . '/includes/notifications.php');

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
        break;

    case 'edit_notifications':
        $id = (int) $request['id'];
        $do_action = $request['do_action'];

        validate_token();
        $user = validate_user();
        include_once(dirname(__FILE__) . '/includes/notifications.php');

        $notification = db_item("SELECT id, action, comment_id FROM notifications WHERE id = '" . $id . "'");

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
        break;

    case 'get_notifications':
        $array = array();
        $read_mark = $request['read_mark'] ?? '';

        validate_token();
        $user = validate_user();
        include_once(dirname(__FILE__) . '/includes/notifications.php');

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
        break;

    case 'remove_notifications':

        validate_token();
        $user = validate_user();
        include_once(dirname(__FILE__) . '/includes/notifications.php');

        // returned in the response below; this branch never fills it
        $array = array();
        $i = 0;

        $notifications = db_items("SELECT id, action, comment_id FROM notifications ORDER BY timestamp DESC");

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
        break;

    case 'push_config':
        // Exempt from the general gate, which answered a request without a
        // session this way; the same answer is kept.
        if (!USER_LOGGED_IN) {
            respond(array(
                'status' => 'error',
                'message' => 'Invalid login.'
            ));
        }
        validate_token();
        $user = validate_user();
        include_once(dirname(__FILE__) . '/includes/push.php');

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
        break;

    case 'push_subscribe':
        // Exempt from the general gate, which answered a request without a
        // session this way; the same answer is kept.
        if (!USER_LOGGED_IN) {
            respond(array(
                'status' => 'error',
                'message' => 'Invalid login.'
            ));
        }
        validate_token();
        $user = validate_user();
        include_once(dirname(__FILE__) . '/includes/push.php');

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
        break;

    case 'push_unsubscribe':
        // Exempt from the general gate, which answered a request without a
        // session this way; the same answer is kept.
        if (!USER_LOGGED_IN) {
            respond(array(
                'status' => 'error',
                'message' => 'Invalid login.'
            ));
        }
        validate_token();
        $user = validate_user();
        include_once(dirname(__FILE__) . '/includes/push.php');

        pg_push_subscription_delete(isset($request['endpoint']) ? $request['endpoint'] : '');

        //return success json output
        $response = array(
            'status' => 'success',
            'message' => 'Push Unsubscribe Success'
        );
        echo encode_json($response);
        exit();
        break;

    case 'push_test':
        // Exempt from the general gate, which answered a request without a
        // session this way; the same answer is kept.
        if (!USER_LOGGED_IN) {
            respond(array(
                'status' => 'error',
                'message' => 'Invalid login.'
            ));
        }
        validate_token();
        $user = validate_user();
        include_once(dirname(__FILE__) . '/includes/push.php');

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
        break;

    case 'push_pending':
        // Asked by the service worker, which has the session cookie but no way
        // to hold a form token: it is woken by the operating system, with no
        // page of its own to have been handed one. The request only reads, and
        // it reads exactly what the bell would have shown the same person.
        // Exempt from the general gate, which answered a request without a
        // session this way; the same answer is kept.
        if (!USER_LOGGED_IN) {
            respond(array(
                'status' => 'error',
                'message' => 'Invalid login.'
            ));
        }
        $user = validate_user();
        include_once(dirname(__FILE__) . '/includes/notifications.php');

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

            include_once(dirname(__FILE__) . '/chat.php');

            foreach (pg_chat_unread_for_push($user['id'], 3 - count($array)) as $conversation) {
                $array[] = $conversation;
            }
        }

        // Mentions and hand-overs in the workspace, the same way - unless the
        // bell carries them already (addressed rows, 4.83), which the loop
        // above has just listed.
        if ((count($array) < 3) && defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED && !pg_notification_targets_available()) {

            require_once(dirname(__FILE__) . '/includes/workspace/bootstrap.php');

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
        break;

    case 'user_pinned_app_update':
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
        break;



    case 'upload_file':
        validate_token();
        $user = validate_user();
        $data = $request['data'];
        $file_name = isset($request['name']) ? trim((string) $request['name']) : '';
        if (isset($request['folder'])) {
            $folder = $request['folder'];
        } else {
            $folder = ($_SESSION['software']['explorer']['folder']['folder_id'] ?? '');
        }

        // This door is open to every backend role, so it keeps the same three
        // rules as the upload screens: a folder the caller may write to, no
        // name the web server would run or read as its own settings, and the
        // same name preparation every other upload gets. Before this it wrote
        // whatever name it was handed, "shell.php" included, into any folder.
        if (($file_name == '') || ($data == '')) {
            respond(array('status' => 'error', 'message' => lang('Invalid request.')));
        }

        if (((int) $folder <= 0) || (check_edit_access((int) $folder) == false)) {
            log_activity(lang('access denied to upload file because user does not have access to folder'), $_SESSION['sessionusername']);
            respond(array('status' => 'error', 'message' => lang('You do not have access to upload files to this folder.')));
        }

        if (pg_upload_name_blocked($file_name)) {
            log_activity(lang(array('string' => 'upload of {var:1} was refused because files of that type are not allowed', 'vars' => $file_name)), $_SESSION['sessionusername']);
            respond(array('status' => 'error', 'message' => pg_upload_blocked_message($file_name)));
        }

        $file_name = prepare_file_name($file_name);

        // get file name with and without file extension
        $file_name_without_extension = mb_substr($file_name, 0, mb_strrpos($file_name, '.'));
        $file_extension = mb_substr($file_name, mb_strrpos($file_name, '.') + 1);

        $image_data = $data;
        $image_data = explode('base64', $image_data);
        $image_data = str_replace(' ', '+', $image_data);
        $image_data = str_replace(',', '', $image_data);
        $image_data = base64_decode(array_pop($image_data));



        // Check if file name is already in use and change it if necessary.
        $file_name = get_unique_name(array(
            'name' => $file_name,
            'type' => 'file'
        ));

        // save the file
        $handle = fopen(FILE_DIRECTORY_PATH . '/' . $file_name, 'w');
        fwrite($handle, $image_data);
        fclose($handle);

        // insert file data into files table
        $query =
            "INSERT INTO files (
                name,
                folder,
                type,
                size,
                user,
                design,
                optimized,
                timestamp) 
            VALUES (
                '" . escape($file_name) . "',
                '" . escape($folder) . "',
                '" . escape($file_extension) . "',
                '" . escape(filesize(FILE_DIRECTORY_PATH . '/' . $file_name)) . "',
                '" . $user['id'] . "',
                '0',
                '0',
                UNIX_TIMESTAMP())";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $file_id = mysqli_insert_id(db::$con);

        log_activity(lang(array('string' => 'The file, {var:1}, has been uploaded.', 'vars' => h($file_name))), $_SESSION['sessionusername']);

        //return success json output
        $response = array(
            'status' => 'success',
            'name' => $file_name,
            'filesize' => h(convert_bytes_to_string(filesize(FILE_DIRECTORY_PATH . '/' . $file_name))),
            'fileid' => $file_id,
            'message' => 'Upload Success'
        );
        echo encode_json($response);
        exit();
        break;

    case 'update_dashboard_widgets':

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
        break;


    case 'update_dashboard_appearance':

        // A write from the panel session needs the session token like every
        // other one; a password-authenticated API request is waived inside
        // validate_token().
        validate_token();

        // How the dashboard looks: which of the four treatments the cards wear,
        // and what sits behind them. Both are site-wide, both live on the
        // dashboard row beside the widget order, and both are picked from the
        // Panel entry's context menu on the dashboard itself.
        //
        // Deliberately not in the exemption list at the top of this file, so the
        // general gate applies: signed in, and role 1 or better. That is the
        // same reach as the menu the selects are drawn in.
        //
        // Field and value are whitelisted rather than validated. One of them
        // becomes a column name and the other a stored slug, and neither has a
        // legitimate free-text form, so a table of the accepted pairs is both
        // the check and the documentation.
        $appearance_fields = array(
            'widget_theme'   => array('flat', 'neon', 'glass', 'aurora'),
            'panel_backdrop' => array('auto', 'none', 'mesh', 'dusk', 'ember'));

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
        // failed query -- the menu that sent this is drawn from the same row, so
        // it will have offered the choice quite happily.
        if (!db_item("SHOW COLUMNS FROM dashboard LIKE '" . $appearance_field . "'")) {

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
        break;

    case 'get_installment_options':
        //This options for only for Credit/debit Cart and to get installment table,
        // if supported installment, we output a table with all supported cards and banks installment prices.
        switch (ECOMMERCE_PAYMENT_GATEWAY) {
            case 'Iyzipay':
                //prepare to installment check
                $card_number_request = $request['card'];
                //remove spaces in card number.
                $card_number_without_spaces = str_replace(' ', '', $card_number_request);

                //get total price 
                $price = $request['price'];

                //if installment option not activated from site settings than pass to installment check.
                if (ECOMMERCE_IYZIPAY_INSTALLMENT >= 2) {
                    // if test or live mode for iyzipay gateway.
                    if (ECOMMERCE_PAYMENT_GATEWAY_MODE == 'test') {
                        $payment_gateway_host = 'https://sandbox-api.iyzipay.com';
                    } else {
                        $payment_gateway_host = 'https://api.iyzipay.com';
                    }
                    require_once('includes/iyzipay-php/IyzipayBootstrap.php');
                    IyzipayBootstrap::init();
                    $card_binNumber = substr($card_number_without_spaces, 0, 6);
                    // Conversation ID Digits amount
                    $digits = 9;
                    // Random Conversation ID
                    $conversationid = rand(pow(10, $digits - 1), pow(10, $digits) - 1);
                    //config
                    $options = new \Iyzipay\Options();
                    $options->setApiKey(ECOMMERCE_IYZIPAY_API_KEY);
                    $options->setSecretKey(ECOMMERCE_IYZIPAY_SECRET_KEY);
                    $options->setBaseUrl($payment_gateway_host);
                    $request = new \Iyzipay\Request\RetrieveInstallmentInfoRequest();
                    $request->setLocale(strtoupper(lang(array('info' => ''))));//get location from sofware language, where set from software settings
                    $request->setConversationId($conversationid);
                    $request->setBinNumber($card_binNumber);
                    $request->setPrice($price);
                    $installmentInfo = \Iyzipay\Model\InstallmentInfo::retrieve($request, $options);
                    $result = $installmentInfo->getRawResult();
                    $oneinstallment_price = json_decode($result)->installmentDetails[0]->installmentPrices[0]->installmentPrice;
                    $oneinstallment_totalprice = json_decode($result)->installmentDetails[0]->installmentPrices[0]->totalPrice;
                    $twoinstallment_price = json_decode($result)->installmentDetails[0]->installmentPrices[1]->installmentPrice;
                    $twoinstallment_totalprice = json_decode($result)->installmentDetails[0]->installmentPrices[1]->totalPrice;
                    $threeinstallment_price = json_decode($result)->installmentDetails[0]->installmentPrices[2]->installmentPrice;
                    $threeinstallment_totalprice = json_decode($result)->installmentDetails[0]->installmentPrices[2]->totalPrice;
                    $sixinstallment_price = json_decode($result)->installmentDetails[0]->installmentPrices[3]->installmentPrice;
                    $sixinstallment_totalprice = json_decode($result)->installmentDetails[0]->installmentPrices[3]->totalPrice;
                    $nineinstallment_price = json_decode($result)->installmentDetails[0]->installmentPrices[4]->installmentPrice;
                    $nineinstallment_totalprice = json_decode($result)->installmentDetails[0]->installmentPrices[4]->totalPrice;
                    $twelveinstallment_price = json_decode($result)->installmentDetails[0]->installmentPrices[5]->installmentPrice;
                    $twelveinstallment_totalprice = json_decode($result)->installmentDetails[0]->installmentPrices[5]->totalPrice;

                    //create array for response. We will update values with array_replace later. if ajax return 0 value it mean there is no  
                    $response = array(
                        "monthlytwo" => "0",
                        "totaltwo" => "0",
                        "two_supported" => "0",
                        "two_inst_increase" => "0",
                        "monthlythree" => "0",
                        "totalthree" => "0",
                        "three_supported" => "0",
                        "three_inst_increase" => "0",
                        "monthlysix" => "0",
                        "totalsix" => "0",
                        "six_supported" => "0",
                        "six_inst_increase" => "0",
                        "monthlynine" => "0",
                        "totalnine" => "0",
                        "nine_supported" => "0",
                        "nine_inst_increase" => "0",
                        "monthlytwelve" => "0",
                        "totaltwelve" => "0",
                        "twelve_supported" => "0",
                        "twelve_inst_increase" => "0",
                    );


                    //if there is result from iyzipay installment check than update array with them.
                    if ($result) {

                        if (($twoinstallment_price) && (ECOMMERCE_IYZIPAY_INSTALLMENT >= 2)) {
                            $two_installment_monthly_price = BASE_CURRENCY_SYMBOL . $twoinstallment_price;
                            $two_installment_total_price = BASE_CURRENCY_SYMBOL . $twoinstallment_totalprice;
                            $twoinstallment_increase_price = BASE_CURRENCY_SYMBOL . ($twoinstallment_totalprice - $price);

                            $array_replace = ['monthlytwo' => $two_installment_monthly_price];
                            $response = array_replace($response, $array_replace);

                            $array_replace = ['totaltwo' => $two_installment_total_price];
                            $response = array_replace($response, $array_replace);

                            $array_replace = ['two_supported' => '1'];
                            $response = array_replace($response, $array_replace);

                            $array_replace = ['two_inst_increase' => $twoinstallment_increase_price];
                            $response = array_replace($response, $array_replace);
                        }
                        if (($threeinstallment_price) && (ECOMMERCE_IYZIPAY_INSTALLMENT >= 3)) {
                            $three_installment_monthly_price = BASE_CURRENCY_SYMBOL . $threeinstallment_price;
                            $three_installment_total_price = BASE_CURRENCY_SYMBOL . $threeinstallment_totalprice;
                            $threeinstallment_increase_price = BASE_CURRENCY_SYMBOL . ($threeinstallment_totalprice - $price);

                            $array_replace = ['monthlythree' => $three_installment_monthly_price];
                            $response = array_replace($response, $array_replace);

                            $array_replace = ['totalthree' => $three_installment_total_price];
                            $response = array_replace($response, $array_replace);

                            $array_replace = ['three_supported' => '1'];
                            $response = array_replace($response, $array_replace);

                            $array_replace = ['three_inst_increase' => $threeinstallment_increase_price];
                            $response = array_replace($response, $array_replace);
                        }
                        if (($sixinstallment_price) && (ECOMMERCE_IYZIPAY_INSTALLMENT >= 6)) {
                            $six_installment_monthly_price = BASE_CURRENCY_SYMBOL . $sixinstallment_price;
                            $six_installment_total_price = BASE_CURRENCY_SYMBOL . $sixinstallment_totalprice;
                            $sixinstallment_increase_price = BASE_CURRENCY_SYMBOL . ($sixinstallment_totalprice - $price);

                            $array_replace = ['monthlysix' => $six_installment_monthly_price];
                            $response = array_replace($response, $array_replace);

                            $array_replace = ['totalsix' => $six_installment_total_price];
                            $response = array_replace($response, $array_replace);

                            $array_replace = ['six_supported' => '1'];
                            $response = array_replace($response, $array_replace);

                            $array_replace = ['six_inst_increase' => $sixinstallment_increase_price];
                            $response = array_replace($response, $array_replace);
                        }
                        if (($nineinstallment_price) && (ECOMMERCE_IYZIPAY_INSTALLMENT >= 9)) {
                            $nine_installment_monthly_price = BASE_CURRENCY_SYMBOL . $nineinstallment_price;
                            $nine_installment_total_price = BASE_CURRENCY_SYMBOL . $nineinstallment_totalprice;
                            $nineinstallment_increase_price = BASE_CURRENCY_SYMBOL . ($nineinstallment_totalprice - $price);

                            $array_replace = ['monthlynine' => $nine_installment_monthly_price];
                            $response = array_replace($response, $array_replace);

                            $array_replace = ['totalnine' => $nine_installment_total_price];
                            $response = array_replace($response, $array_replace);

                            $array_replace = ['nine_supported' => '1'];
                            $response = array_replace($response, $array_replace);

                            $array_replace = ['nine_inst_increase' => $nineinstallment_increase_price];
                            $response = array_replace($response, $array_replace);
                        }
                        if (($twelveinstallment_price) && (ECOMMERCE_IYZIPAY_INSTALLMENT >= 12)) {
                            $twelve_installment_monthly_price = BASE_CURRENCY_SYMBOL . $twelveinstallment_price;
                            $twelve_installment_total_price = BASE_CURRENCY_SYMBOL . $twelveinstallment_totalprice;
                            $twelveinstallment_increase_price = BASE_CURRENCY_SYMBOL . ($twelveinstallment_totalprice - $price);

                            $array_replace = ['monthlytwelve' => $twelve_installment_monthly_price];
                            $response = array_replace($response, $array_replace);

                            $array_replace = ['totaltwelve' => $twelve_installment_total_price];
                            $response = array_replace($response, $array_replace);

                            $array_replace = ['twelve_supported' => '1'];
                            $response = array_replace($response, $array_replace);

                            $array_replace = ['twelve_inst_increase' => $twelveinstallment_increase_price];
                            $response = array_replace($response, $array_replace);
                        }


                    }
                    //return json output
                    echo encode_json($response);

                } else {
                    //return error json output
                    $response = array(
                        'status' => 'error',
                        'message' => 'No Installment Supported'
                    );
                    echo encode_json($response);
                }
                break;
        }
        exit();
        break;

    // New express order installment endpoint. Returns a forward-compatible
    // table: every installmentNumber Iyzipay reports (typically 1, 2, 3, 6,
    // 9, 12 — but never assumed) up to the operator's ECOMMERCE_IYZIPAY_INSTALLMENT
    // cap, plus card metadata (cardAssociation, cardFamilyName, bankName) so
    // the widget can render brand-aware UI ("Bonus / Garanti — 3
    // installments at ₺X.XX/month, ₺Y.YY total"). Wraps the same SDK call the legacy
    // `get_installment_options` action uses, but doesn't lose entries when
    // Iyzipay returns additional rows (e.g. 4-installment cards).
    //
    // Request: { action: 'eo_get_installments', card: '5528 7900 0000 0008', price: '17.64' }
    // Response on success:
    //   {
    //     "status": "success",
    //     "binNumber": "552879",
    //     "cardAssociation": "MASTER_CARD",
    //     "cardFamilyName": "Bonus",
    //     "bankName": "Garanti Bankası",
    //     "cardType": "CREDIT_CARD",
    //     "max_allowed": 6,
    //     "currency_symbol": "₺",
    //     "currency_code": "TRY",
    //     "installments": [
    //         {"number": 1, "monthly": "17.64", "total": "17.64", "increase": "0.00"},
    //         {"number": 3, "monthly": "6.06", "total": "18.18", "increase": "0.54"}
    //     ]
    //   }
    // Errors return {"status":"error","message":"..."}.
    case 'eo_get_installments':
        if (ECOMMERCE_PAYMENT_GATEWAY !== 'Iyzipay') {
            echo encode_json(array('status'=>'error','message'=>'Iyzipay gateway is not active.'));
            exit();
        }
        $eo_inst_card  = isset($request['card'])  ? trim((string)$request['card'])  : '';
        $eo_inst_price = isset($request['price']) ? trim((string)$request['price']) : '';
        // Strip spaces/dashes so a "5528 7900 …" input still resolves to a BIN.
        $eo_inst_bin   = substr(preg_replace('/[^0-9]/', '', $eo_inst_card), 0, 6);
        if (strlen($eo_inst_bin) < 6 || !is_numeric($eo_inst_price) || (float)$eo_inst_price <= 0) {
            echo encode_json(array('status'=>'error','message'=>'card and price are required.'));
            exit();
        }
        $eo_max_inst = defined('ECOMMERCE_IYZIPAY_INSTALLMENT') ? (int)ECOMMERCE_IYZIPAY_INSTALLMENT : 1;
        if ($eo_max_inst < 2) {
            // Single-shot only; no installment offer to display.
            echo encode_json(array(
                'status'         => 'success',
                'binNumber'      => $eo_inst_bin,
                'max_allowed'    => 1,
                'currency_symbol'=> defined('BASE_CURRENCY_SYMBOL') ? html_entity_decode(BASE_CURRENCY_SYMBOL, ENT_QUOTES | ENT_HTML5, 'UTF-8') : '',
                'currency_code'  => defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : '',
                'installments'   => array(array(
                    'number' => 1,
                    'monthly'=> number_format((float)$eo_inst_price, 2, '.', ''),
                    'total'  => number_format((float)$eo_inst_price, 2, '.', ''),
                    'increase'=> '0.00',
                )),
            ));
            exit();
        }
        require_once(dirname(__FILE__) . '/includes/iyzipay-php/IyzipayBootstrap.php');
        IyzipayBootstrap::init();
        $eo_inst_host = (ECOMMERCE_PAYMENT_GATEWAY_MODE === 'test')
            ? 'https://sandbox-api.iyzipay.com' : 'https://api.iyzipay.com';
        $eo_inst_opts = new \Iyzipay\Options();
        $eo_inst_opts->setApiKey(ECOMMERCE_IYZIPAY_API_KEY);
        $eo_inst_opts->setSecretKey(ECOMMERCE_IYZIPAY_SECRET_KEY);
        $eo_inst_opts->setBaseUrl($eo_inst_host);
        $eo_inst_req = new \Iyzipay\Request\RetrieveInstallmentInfoRequest();
        $eo_inst_req->setLocale(strtoupper(lang(array('info' => ''))));
        $eo_inst_req->setConversationId((string)rand(100000000, 999999999));
        $eo_inst_req->setBinNumber($eo_inst_bin);
        $eo_inst_req->setPrice(sprintf('%01.2lf', (float)$eo_inst_price));
        $eo_inst_resp = \Iyzipay\Model\InstallmentInfo::retrieve($eo_inst_req, $eo_inst_opts);
        $eo_inst_raw  = $eo_inst_resp->getRawResult();
        $eo_inst_data = json_decode((string)$eo_inst_raw, true);
        if (!is_array($eo_inst_data) || ($eo_inst_data['status'] ?? '') !== 'success'
            || empty($eo_inst_data['installmentDetails'][0]['installmentPrices'])) {
            echo encode_json(array(
                'status'  => 'error',
                'message' => isset($eo_inst_data['errorMessage'])
                    ? (string)$eo_inst_data['errorMessage']
                    : 'No installment data returned.',
            ));
            exit();
        }
        $eo_inst_detail = $eo_inst_data['installmentDetails'][0];
        $eo_inst_list   = array();
        $eo_inst_base   = (float)$eo_inst_price;
        foreach ($eo_inst_detail['installmentPrices'] as $p) {
            $eo_inst_n = (int)($p['installmentNumber'] ?? 0);
            if ($eo_inst_n < 1 || $eo_inst_n > $eo_max_inst) continue;
            $eo_inst_monthly = (float)($p['installmentPrice'] ?? 0);
            $eo_inst_total   = (float)($p['totalPrice']       ?? 0);
            $eo_inst_list[] = array(
                'number'   => $eo_inst_n,
                'monthly'  => number_format($eo_inst_monthly, 2, '.', ''),
                'total'    => number_format($eo_inst_total,   2, '.', ''),
                'increase' => number_format(max(0, $eo_inst_total - $eo_inst_base), 2, '.', ''),
            );
        }
        usort($eo_inst_list, function($a, $b) { return $a['number'] - $b['number']; });
        echo encode_json(array(
            'status'          => 'success',
            'binNumber'       => (string)($eo_inst_detail['binNumber']       ?? $eo_inst_bin),
            'cardAssociation' => (string)($eo_inst_detail['cardAssociation'] ?? ''),
            'cardFamilyName'  => (string)($eo_inst_detail['cardFamilyName']  ?? ''),
            'bankName'        => (string)($eo_inst_detail['bankName']        ?? ''),
            'cardType'        => (string)($eo_inst_detail['cardType']        ?? ''),
            'max_allowed'     => $eo_max_inst,
            // BASE_CURRENCY_SYMBOL is stored as an HTML entity (e.g. `&#8378;`)
            // so it inlines safely into legacy template HTML. The install
            // selector\'s JS uses `.textContent` to set the visible value —
            // raw entities would show LITERALLY ("&#8378;14.35"). Decode
            // here so JSON carries the actual unicode glyph (₺), and
            // textContent renders it correctly.
            'currency_symbol' => defined('BASE_CURRENCY_SYMBOL')
                                    ? html_entity_decode(BASE_CURRENCY_SYMBOL, ENT_QUOTES | ENT_HTML5, 'UTF-8')
                                    : '',
            'currency_code'   => defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : '',
            'installments'    => $eo_inst_list,
        ));
        exit();
        break;

    // Designer companion endpoints for the express order widget. Both are
    // dictionary-shaped JSON so the designer's JS can consume them without
    // duplicating the PHP source of truth.
    //   eo_default_tree       → { status, tree:{…} }       — default layout
    //   eo_required_sections  → { status, required:[…], all:[…], labels:{…} }
    case 'eo_default_tree':
        if (!function_exists('_eo_default_designer_tree')) {
            echo encode_json(array('status'=>'error','message'=>'_eo_default_designer_tree() unavailable.'));
            exit();
        }
        echo encode_json(array('status' => 'success', 'tree' => _eo_default_designer_tree()));
        exit();
        break;
    case 'eo_required_sections':
        $eo_req = function_exists('_eo_required_section_bindings') ? _eo_required_section_bindings() : array();
        // Friendly labels for the designer panel checklist. Keys MUST match
        // the section dictionary built in _render_system_widget_express_order;
        // values prefixed `eo_` to avoid clashing with catalog's binding
        // namespace (e.g. catalog also has a `payment_methods` notion).
        $eo_labels = array(
            'eo_errors_notices'         => lang('Errors / notices (filled by backend)'),
            'eo_cart_items'             => lang('Cart items (filled by backend: table + quantity inputs)'),
            'eo_shipping'               => lang('Shipping address & carrier (filled by backend)'),
            'eo_billing'                => lang('Billing details, whole block (filled by backend)'),
            'eo_billing_country_select' => lang('Country list (filled by backend, 240 countries)'),
            'eo_payment'                => lang('Payment block, whole block (filled by backend)'),
            'eo_payment_methods'        => lang('Payment methods (radio buttons, per site setting)'),
            'eo_installment'            => lang('Installment picker (Iyzipay BIN lookup)'),
            'eo_terms'                  => lang('Terms consent (legacy; the new tree uses a real checkbox + modal)'),
            'eo_saved_cart_link'        => lang('Saved cart link (the visitor can return to the cart with the order #)'),
            'eo_upsell_offers'          => lang('Upsell offers (view_offers.php alert strip)'),
            'eo_applied_offers'         => lang('Applied offers (promotions applied to the cart)'),
            'eo_totals'                 => lang('Order totals (computed by backend: VAT, card surcharge, total)'),
            'eo_submit_button'          => lang('Complete Order button'),
            'eo_update_button'          => lang('Update button (for cart quantities)'),
        );
        $required_full = array();
        foreach ($eo_req as $k => $_v) $required_full[] = 'eo_' . $k;
        echo encode_json(array(
            'status'   => 'success',
            'required' => $required_full,
            'all'      => array_keys($eo_labels),
            'labels'   => $eo_labels,
        ));
        exit();
        break;

    case 'add_to_cart':

        require_once(dirname(__FILE__) . '/add_to_cart.php');

        $response = add_to_cart($request);

        echo encode_json($response);
        exit();

        break;

    case 'delete_order':

        validate_token();

        require_once(dirname(__FILE__) . '/delete_order.php');

        $response = delete_order(array('order' => $request['order']));

        echo encode_json($response);
        exit();

        break;

    case 'get_common_regions':
        $common_regions = db_items(
            "SELECT
                cregion_id AS id,
                cregion_name AS name,
                cregion_content AS content
            FROM cregion
            WHERE cregion_designer_type = 'no'
            ORDER BY cregion_name ASC"
        );

        $response = array(
            'status' => 'success',
            'common_regions' => $common_regions
        );

        echo encode_json($response);
        exit();

        break;

    case 'get_cross_sell_items':

        require_once(dirname(__FILE__) . '/get_cross_sell_items.php');

        $response = get_cross_sell_items($request);

        echo encode_json($response);
        exit();

        break;

    // Cross-sell with same-group fallback — designer-facing variant of
    // the legacy get_cross_sell_items. Used by pg_civ_variants.js when
    // the visitor switches variants on a select-type product detail page,
    // so the cross-sell row stays populated even on fresh sites with no
    // order history. Two-tier strategy:
    //   • Tier 1: ordered-together products (legacy logic)
    //   • Tier 2: same-product-group siblings (excluding the source)
    // Returns the same shape regardless of which tier provided the items.
    //
    // Request shape:
    //   action          = 'get_cross_sell_for_product'
    //   product[id]     = source product id
    //   detail_page_id  = (optional) catalog detail page id for URL prefix
    //   count           = (optional) max items, default 4
    case 'get_cross_sell_for_product':

        $_x_pid    = isset($request['product']['id']) ? (int)$request['product']['id'] : 0;
        $_x_detail = isset($request['detail_page_id']) ? (int)$request['detail_page_id'] : 0;
        $_x_count  = isset($request['count']) ? max(1, min(12, (int)$request['count'])) : 4;
        if ($_x_pid <= 0) {
            echo encode_json(array('status' => 'error', 'message' => 'product[id] required'));
            exit();
        }
        $_x_items = function_exists('_civ_get_cross_sell_items')
            ? _civ_get_cross_sell_items($_x_pid, $_x_detail, $_x_count)
            : array();
        echo encode_json(array('status' => 'success', 'items' => $_x_items));
        exit();

        break;

    // Used to get the estimated delivery date for a recipient and method.

    case 'get_delivery_date':

        require_once(dirname(__FILE__) . '/shipping.php');

        $response = get_delivery_date($request);

        echo encode_json($response);
        exit();

        break;

    case 'get_design_files':
        $sql_types = "";

        // If types is an array and has at least one item,
        // then prepare SQL to limit by types.
        if ((is_array($request['types']) == true) && $request['types']) {
            foreach ($request['types'] as $type) {
                if ($sql_types != '') {
                    $sql_types .= " OR ";
                }

                $sql_types .= "(type = '" . escape($type) . "')";
            }

            $sql_types = "AND ($sql_types)";
        }

        $sql_search = "";

        if ($request['search'] != '') {
            $sql_search = "AND (name LIKE '%" . escape(escape_like($request['search'])) . "%')";
        }

        $design_files = db_items(
            "SELECT
                id,
                name,
                type,
                theme
            FROM files
            WHERE
                (design = '1')
                $sql_types
                $sql_search
            ORDER BY timestamp DESC"
        );

        // If a specific type of theme was specified, then loop through design files
        // in order to only include that type of theme.
        if ($request['theme_type'] != '') {
            foreach ($design_files as $key => $design_file) {
                // If this file is a CSS theme, then determine what type of theme it is.
                if ($design_file['theme'] == 1) {
                    // If this is a system theme, then set that.
                    if (db_value("SELECT COUNT(*) FROM system_theme_css_rules WHERE file_id = '" . $design_file['id'] . "'") > 0) {
                        $design_file['theme_type'] = 'system';

                        // Otherwise this is a custom theme, so set that.
                    } else {
                        $design_file['theme_type'] = 'custom';
                    }

                    // If the theme type does not matched the requested theme type,
                    // then remove this design file from the array.
                    if ($design_file['theme_type'] != $request['theme_type']) {
                        unset($design_files[$key]);
                    }
                }
            }
        }

        $response = array(
            'status' => 'success',
            'design_files' => $design_files
        );

        echo encode_json($response);
        exit();

        break;

    case 'get_designer_region':
        $designer_region = db_item(
            "SELECT
                cregion_id AS id,
                cregion_name AS name,
                cregion_content AS content
            FROM cregion
            WHERE
                (cregion_designer_type = 'yes')
                AND (cregion_id = '" . escape($request['designer_region']['id']) . "')"
        );

        // If a designer region was not found then respond with an error.
        if (!$designer_region) {
            $response = array(
                'status' => 'error',
                'message' => 'Designer Region could not be found.'
            );

            echo encode_json($response);
            exit();
        }

        $response = array(
            'status' => 'success',
            'designer_region' => $designer_region
        );

        echo encode_json($response);
        exit();

        break;

    case 'get_designer_regions':
        $sql_content = "";

        if ($request['content'] == true) {
            $sql_content = ", cregion_content AS content";
        }

        $sql_search = "";

        if ($request['search'] != '') {
            $sql_search = "AND (cregion_name LIKE '%" . escape(escape_like($request['search'])) . "%')";
        }

        $designer_regions = db_items(
            "SELECT
                cregion_id AS id,
                cregion_name AS name
                $sql_content
            FROM cregion
            WHERE
                (cregion_designer_type = 'yes')
                $sql_search
            ORDER BY cregion_timestamp DESC"
        );

        $response = array(
            'status' => 'success',
            'designer_regions' => $designer_regions
        );

        echo encode_json($response);
        exit();

        break;

    case 'get_dynamic_region':
        $dynamic_region = db_item(
            "SELECT
                dregion_id AS id,
                dregion_name AS name,
                dregion_code AS content
            FROM dregion
            WHERE dregion_id = '" . escape($request['dynamic_region']['id']) . "'"
        );

        // If a dynamic region was not found then respond with an error.
        if (!$dynamic_region) {
            $response = array(
                'status' => 'error',
                'message' => 'Dynamic Region could not be found.'
            );

            echo encode_json($response);
            exit();
        }

        $response = array(
            'status' => 'success',
            'dynamic_region' => $dynamic_region
        );

        echo encode_json($response);
        exit();

        break;

    case 'get_dynamic_regions':
        $sql_content = "";

        if ($request['content'] == true) {
            $sql_content = ", dregion_code AS content";
        }

        $sql_search = "";

        if ($request['search'] != '') {
            $sql_search = "WHERE dregion_name LIKE '%" . escape(escape_like($request['search'])) . "%'";
        }

        $dynamic_regions = db_items(
            "SELECT
                dregion_id AS id,
                dregion_name AS name
                $sql_content
            FROM dregion
            $sql_search
            ORDER BY dregion_timestamp DESC"
        );

        $response = array(
            'status' => 'success',
            'dynamic_regions' => $dynamic_regions
        );

        echo encode_json($response);
        exit();

        break;

    case 'get_file':
        $file = db_item(
            "SELECT
                id,
                name,
                size,
                theme
            FROM files
            WHERE id = '" . escape($request['file']['id']) . "'"
        );

        // If a file was not found then respond with an error.
        if (!$file) {
            $response = array(
                'status' => 'error',
                'message' => 'File could not be found.'
            );

            echo encode_json($response);
            exit();
        }

        // If this file is a CSS theme, then determine what type of theme it is.
        if ($file['theme'] == 1) {
            // If this is a system theme, then set that.
            if (db_value("SELECT COUNT(*) FROM system_theme_css_rules WHERE file_id = '" . $file['id'] . "'") > 0) {
                $file['theme_type'] = 'system';

            } else {
                $file['theme_type'] = 'custom';
            }
        }
        $file['size'] = convert_bytes_to_string($file['size']);
        $file['content'] = file_get_contents(FILE_DIRECTORY_PATH . '/' . $file['name']);

        $response = array(
            'status' => 'success',
            'file' => $file
        );

        echo encode_json($response);
        exit();

        break;

    case 'get_folders':
        $folders = db_items(
            "SELECT
                folder_id AS id,
                folder_name AS name,
                folder_parent AS parent_folder_id,
                folder_level AS level,
                folder_style AS style_id,
                mobile_style_id,
                folder_order AS sort_order,
                folder_access_control_type AS access_control_type,
                folder_archived AS archived
            FROM folder
            ORDER BY
                folder_level ASC,
                folder_order ASC,
                folder_name ASC"
        );

        $response = array(
            'status' => 'success',
            'folders' => $folders
        );

        echo encode_json($response);
        exit();

        break;

    case 'get_form':

        require_once(dirname(__FILE__) . '/forms.php');

        $request['check_access'] = true;

        respond(get_form($request));

        break;

    case 'get_forms':

        require_once(dirname(__FILE__) . '/forms.php');

        $request['check_access'] = true;

        respond(get_forms($request));

        break;

    case 'get_items_in_style':
        $style = db_item(
            "SELECT
                style_id AS id,
                style_code AS code
            FROM style
            WHERE style_id = '" . escape($request['style_id']) . "'"
        );

        // If a style was not found then respond with an error.
        if (!$style) {
            $response = array(
                'status' => 'error',
                'message' => 'Style could not be found.'
            );

            echo encode_json($response);
            exit();
        }

        $content = $style['code'];

        $content = preg_replace('/{path}/i', OUTPUT_PATH, $content);

        $design_files = array();

        // Find all CSS and JS resources in the style content.
        preg_match_all('/["\']\s*([^"\']*\.(css|js)[^"\']*)\s*["\']/i', $content, $matches, PREG_SET_ORDER);

        // Loop through all of the resources in order to determine if they
        // are design files for this site.
        foreach ($matches as $match) {
            $url = trim($match[1]);
            $url = unhtmlspecialchars($url);
            $url_parts = parse_url($url);
            $file_name = basename($url_parts['path']);
            $file_name = rawurldecode($file_name);

            // Check if design file exists for this file name.
            $design_file = db_item(
                "SELECT
                    id,
                    name,
                    type,
                    theme
                FROM files
                WHERE
                    (design = '1')
                    AND (name = '" . escape($file_name) . "')"
            );

            // If a design file was found, then add it to array.
            if ($design_file) {
                // If this file is a CSS theme, then determine what type of theme it is.
                if ($design_file['theme'] == 1) {
                    // If this is a system theme, then set that.
                    if (db_value("SELECT COUNT(*) FROM system_theme_css_rules WHERE file_id = '" . $design_file['id'] . "'") > 0) {
                        $design_file['theme_type'] = 'system';

                    } else {
                        $design_file['theme_type'] = 'custom';
                    }
                }

                $design_files[] = $design_file;
            }
        }

        $designer_regions = array();

        // Get all designer regions in the style content.
        preg_match_all('/<cregion>.*?<\/cregion>/i', $content, $regions);

        foreach ($regions[0] as $region) {
            $name = strip_tags($region);

            $designer_region = db_item(
                "SELECT
                    cregion_id AS id,
                    cregion_name AS name
                FROM cregion
                WHERE
                    (cregion_name = '" . escape($name) . "')
                    AND (cregion_designer_type = 'yes')"
            );

            // If a designer region was found, then add it to array.
            if ($designer_region) {
                $designer_regions[] = $designer_region;
            }
        }

        $dynamic_regions = array();

        // Get all dynamic regions in the style content.
        preg_match_all('/<dregion.*?>.*?<\/dregion>/i', $content, $regions);

        foreach ($regions[0] as $region) {
            $name = strip_tags($region);

            $dynamic_region = db_item(
                "SELECT
                    dregion_id AS id,
                    dregion_name AS name
                FROM dregion
                WHERE dregion_name = '" . escape($name) . "'"
            );

            // If a dynamic region was found, then add it to array.
            if ($dynamic_region) {
                $dynamic_regions[] = $dynamic_region;
            }
        }

        $system_regions = array();

        // Get all system regions.
        preg_match_all('/<system>.*?<\/system>/i', $content, $regions);

        foreach ($regions[0] as $region) {
            $name = strip_tags($region);

            // If this is a secondary system region with a page name,
            // then add it to the array.
            if ($name != '') {
                $system_region = array();
                $system_region['name'] = $name;

                $system_regions[] = $system_region;
            }
        }

        $response = array(
            'status' => 'success',
            'design_files' => $design_files,
            'designer_regions' => $designer_regions,
            'dynamic_regions' => $dynamic_regions,
            'system_regions' => $system_regions
        );

        echo encode_json($response);
        exit();

        break;

    case 'get_layout':
        $layout = db_item(
            "SELECT
                page_id AS id,
                page_name AS name,
                layout_modified AS modified
            FROM page
            WHERE page_id = '" . escape($request['layout']['id']) . "'"
        );

        // If a layout was not found then respond with an error.
        if (!$layout) {
            $response = array(
                'status' => 'error',
                'message' => 'Layout could not be found.'
            );

            echo encode_json($response);
            exit();
        }

        if ($layout['modified']) {
            $layout['content'] = @file_get_contents(LAYOUT_DIRECTORY_PATH . '/' . $layout['id'] . '.php');

        } else {
            require_once(dirname(__FILE__) . '/generate_layout_content.php');

            $layout['content'] = generate_layout_content($layout['id']);
        }

        $response = array(
            'status' => 'success',
            'layout' => $layout
        );

        echo encode_json($response);
        exit();

        break;

    case 'get_page':
        if ($request['page']['id'] != '') {
            $where = "page_id = '" . e($request['page']['id']) . "'";

        } else {
            $where = "page_name = '" . e($request['page']['name']) . "'";
        }

        $page = db_item(
            "SELECT
                page_id AS id,
                page_name AS name,
                page_type AS type,
                layout_type
            FROM page
            WHERE $where"
        );

        // If a page was not found then respond with an error.
        if (!$page) {
            $response = array(
                'status' => 'error',
                'message' => 'Page could not be found.'
            );

            echo encode_json($response);
            exit();
        }

        // If page type properties were requested, and this page has page type properties,
        // then get them.
        if (
            isset($request['page_type_properties']) &&
            $request['page_type_properties']
            && check_for_page_type_properties($page['type'])
        ) {
            $page_type_properties = get_page_type_properties($page['id'], $page['type']);

            if ($page_type_properties) {
                $page['page_type_properties'] = $page_type_properties;
            }
        }

        $response = array(
            'status' => 'success',
            'page' => $page
        );

        echo encode_json($response);
        exit();

        break;

    case 'get_pages':
        $pages = db_items(
            "SELECT
                page_id AS id,
                page_name AS name,
                page_folder AS folder_id,
                page_style AS style_id,
                mobile_style_id,
                page_home AS home,
                page_title AS title,
                page_search AS search,
                page_search_keywords AS search_keywords,
                page_meta_description AS meta_description,
                page_meta_keywords AS meta_keywords,
                page_type AS type,
                layout_type,
                layout_modified,
                comments,
                comments_label,
                comments_message,
                comments_rating,
                comments_allow_new_comments,
                comments_disallow_new_comment_message,
                comments_automatic_publish,
                comments_allow_user_to_select_name,
                comments_require_login_to_comment,
                comments_allow_file_attachments,
                comments_show_submitted_date_and_time,
                comments_administrator_email_to_email_address,
                comments_administrator_email_subject,
                comments_administrator_email_conditional_administrators,
                comments_submitter_email_page_id,
                comments_submitter_email_subject,
                comments_watcher_email_page_id,
                comments_watcher_email_subject,
                comments_watchers_managed_by_submitter,
                seo_score,
                seo_analysis,
                seo_analysis_current,
                sitemap,
                " . (pg_page_noindex_ready() ? "noindex,
                nofollow," : "'0' AS noindex,
                '0' AS nofollow,") . "
                system_region_header,
                system_region_footer
            FROM page
            ORDER BY page_name ASC"
        );

        // Loop through the pages in order to get page type properties and page regions.
        foreach ($pages as $key => $page) {
            // If this page's type has page type properties, then get them.
            if (check_for_page_type_properties($page['type']) == true) {
                $page_type_properties = get_page_type_properties($page['id'], $page['type']);

                // If properties were found, then add them.
                if (is_array($page_type_properties) == true) {
                    $pages[$key]['page_type_properties'] = $page_type_properties;
                }
            }

            $page_regions = db_items(
                "SELECT
                    pregion_id AS id,
                    pregion_name AS name,
                    pregion_content AS content,
                    pregion_page AS page_id,
                    pregion_order AS sort_order,
                    collection
                FROM pregion
                WHERE pregion_page = '" . $page['id'] . "'"
            );

            $pages[$key]['page_regions'] = $page_regions;
        }

        $response = array(
            'status' => 'success',
            'pages' => $pages
        );

        echo encode_json($response);
        exit();

        break;

    case 'get_product':
        // Extended SELECT — adds price/brand/mpn/gtin/weight/shippable/taxable/
        // custom_fields/reward_points/seo_score/timestamp/backorder/address_name
        // alongside the legacy fields (id/name/short_description/full_description/
        // details/code/image_name/inventory/inventory_quantity/out_of_stock_message).
        //
        // Existing custom layouts only read the legacy keys → adding more fields
        // is backward-compatible. Designer-built (catalog_item_view) product
        // detail pages read the extended fields when the visitor picks a new
        // variant from a select-type group, refreshing the bound elements
        // (price, brand, gallery image, etc.) without a full page reload.
        $product_raw = db_item(
            "SELECT
                id,
                name,
                address_name,
                short_description,
                full_description,
                details,
                code,
                image_name,
                inventory,
                inventory_quantity,
                backorder,
                out_of_stock_message,
                price,
                mpn,
                gtin,
                brand,
                weight,
                shippable,
                taxable,
                reward_points,
                seo_score,
                custom_field_1,
                custom_field_2,
                custom_field_3,
                custom_field_4,
                timestamp
            FROM products
            WHERE id = '" . e($request['product']['id']) . "'"
        );



        //if code has ^^image_loop_start^^ and ^^image_url^^ and ^^image_loop_end^^. with these we can make an ease loop
        if (
            (strpos($product_raw['code'], '^^image_url^^') !== false) &&
            (strpos($product_raw['code'], '^^image_loop_start^^') !== false) &&
            (strpos($product_raw['code'], '^^image_loop_end^^') !== false)
        ) {
            //check for image list from products_images_xref
            $item_images = "SELECT product,file_name FROM products_images_xref WHERE product = '" . e($request['product']['id']) . "'";
            $image_results = mysqli_query(db::$con, $item_images) or output_error('Query failed');

            $code_header_position = strpos($product_raw['code'], '^^image_loop_start^');//number
            $code_content_position = strpos($product_raw['code'], '^^image_url^^');
            $code_footer_position = strpos($product_raw['code'], '^^image_loop_end^');//number
            $code_header = substr($product_raw['code'], 0, strpos($product_raw['code'], '^^image_loop_start^'));
            $code_content_raw = substr($product_raw['code'], (strpos($product_raw['code'], '^^image_loop_start^') + 20), (strpos($product_raw['code'], '^^image_loop_end^') - strpos($product_raw['code'], '^^image_loop_start^') - 20));
            $code_footer = substr($product_raw['code'], strpos($product_raw['code'], '^^image_loop_end^') + 18);
            $code_image_alt = false;
            if (strpos($product_raw['code'], '^^image_alt^^') !== false) {
                $code_image_alt = true;
            }

            //if product image xref or product group  xref exist. this mean this selected multiple product image
            if (mysqli_num_rows($image_results) != 0) {

                //if there is image alt tag
                if ($code_image_alt !== false) {
                    $code_content = str_replace("^^image_url^^", PATH . encode_url_path($product_raw['image_name']), str_replace("^^image_alt^^", $product_raw['short_description'], $code_content_raw));
                    //if there is no image alt tag
                } else {
                    $code_content = str_replace("^^image_url^^", PATH . encode_url_path($product_raw['image_name']), $code_content_raw);

                }
                while ($image = mysqli_fetch_assoc($image_results)) {
                    //if there is image alt tag
                    if ($code_image_alt !== false) {
                        $code_content .= str_replace("^^image_url^^", PATH . encode_url_path($image['file_name']), str_replace("^^image_alt^^", $product_raw['short_description'], $code_content_raw));
                        //if there is no image alt tag
                    } else {
                        $code_content .= str_replace("^^image_url^^", PATH . encode_url_path($image['file_name']), $code_content_raw);
                    }
                }
                $product_replace = ['code' => $code_header . $code_content . $code_footer];
                $product = array_replace($product_raw, $product_replace);
            } else {
                //else if less an image selected and only one image selected, but there is code for action we output single image
                if ($product_raw['image_name']) {
                    //if there is image alt tag
                    if ($code_image_alt !== false) {
                        $code_single_content = str_replace("^^image_url^^", PATH . encode_url_path($product_raw['image_name']), str_replace("^^image_alt^^", $product_raw['short_description'], $code_content_raw));
                        //if there is no image alt tag
                    } else {
                        $code_single_content = str_replace("^^image_url^^", PATH . encode_url_path($product_raw['image_name']), $code_content_raw);
                    }
                    $product_replace = ['code' => $code_header . $code_single_content . $code_footer];
                    $product = array_replace($product_raw, $product_replace);
                } else {
                    $product = $product_raw;
                }
            }
        } else {
            $product = $product_raw;
        }

        // If a product was not found then respond with an error.
        if (!$product) {
            $response = array(
                'status' => 'error',
                'message' => 'Product could not be found.'
            );

            echo encode_json($response);
            exit();
        }

        // ── Extended response: gallery + computed fields ─────────────────
        // Backward-compat: the legacy custom-layout 'code' expansion above
        // ALREADY uses products_images_xref; we just expose the same gallery
        // as a clean array on the response so designer-built (catalog_item_view)
        // detail pages can refresh the carousel without re-parsing 'code'.
        //
        // Also exposes:
        //   • price_cents / price (raw integer cents → matches DB unit)
        //   • price_decimal      (cents/100 with 2 decimals — for arithmetic)
        //   • price_formatted    (currency-symbol-prefixed display string)
        //   • image_url          (full URL to main image, or '' when missing)
        //   • detail_url         (full URL to the product's detail page,
        //                          when products.address_name is set)
        //   • gallery[]          (each entry: {url, alt})
        //   • out_of_stock       (bool — derived from inventory rules)
        $_pg_pid = (int)$product['id'];
        $_pg_gallery = array();
        $_pg_main = (string)($product['image_name'] ?? '');
        if ($_pg_main !== '') {
            $_pg_gallery[] = array(
                'url' => PATH . encode_url_path($_pg_main),
                'alt' => (string)$product['short_description'],
            );
        }
        $_pg_xref = mysqli_query(db::$con,
            "SELECT file_name FROM products_images_xref WHERE product = '" . $_pg_pid . "'"
        );
        if ($_pg_xref) {
            while ($_pg_row = mysqli_fetch_assoc($_pg_xref)) {
                if (!empty($_pg_row['file_name'])) {
                    $_pg_gallery[] = array(
                        'url' => PATH . encode_url_path($_pg_row['file_name']),
                        'alt' => (string)$product['short_description'],
                    );
                }
            }
        }
        // Pricing — effective vs. sticker.
        // `original_*` keeps the pre-discount price (sticker, for strike-
        // through display in the catalog_item_view variant chooser).
        // `price` / `price_formatted` reflect the EFFECTIVE price after any
        // active order-scope offer applies. `has_discount` (1/0) lets the
        // designer's "indirim" badge / strike-through hide-when-empty.
        $_pg_orig_cents  = (int)($product['price'] ?? 0);
        $_pg_disc_map    = function_exists('get_discounted_product_prices')
            ? get_discounted_product_prices() : array();
        if (!is_array($_pg_disc_map)) $_pg_disc_map = array();
        $_pg_price_cents = isset($_pg_disc_map[$_pg_pid]) ? (int)$_pg_disc_map[$_pg_pid] : $_pg_orig_cents;
        $_pg_price_dec   = $_pg_price_cents / 100;
        $_pg_orig_dec    = $_pg_orig_cents / 100;
        $_pg_has_disc    = ($_pg_price_cents < $_pg_orig_cents) ? 1 : 0;
        $_pg_save_cents  = $_pg_orig_cents - $_pg_price_cents;
        $_pg_save_pct    = ($_pg_orig_cents > 0 && $_pg_save_cents > 0)
            ? (int)round(($_pg_save_cents / $_pg_orig_cents) * 100) : 0;
        $_pg_curr        = defined('VISITOR_CURRENCY_SYMBOL') ? VISITOR_CURRENCY_SYMBOL : '$';
        $_pg_inv_tracked = !empty($product['inventory']) ? 1 : 0;
        $_pg_inv_qty     = (int)($product['inventory_quantity'] ?? 0);
        $_pg_backorder   = !empty($product['backorder']) ? 1 : 0;
        $_pg_oos         = ($_pg_inv_tracked && $_pg_inv_qty <= 0 && !$_pg_backorder) ? 1 : 0;

        // Detail URL — only when products.address_name is set AND there's at
        // least one catalog_detail_pages row (legacy resolver). For now we
        // emit the address_name slug; the consumer can prefix with their
        // detail page path.
        $_pg_addr = (string)($product['address_name'] ?? '');

        $product['price']           = number_format($_pg_price_dec, 2, '.', '');
        $product['price_cents']     = $_pg_price_cents;
        $product['price_decimal']   = number_format($_pg_price_dec, 2, '.', '');
        $product['price_formatted'] = pg_visitor_money($_pg_price_dec, $_pg_curr);
        $product['original_price']           = number_format($_pg_orig_dec, 2, '.', '');
        $product['original_price_cents']     = $_pg_orig_cents;
        $product['original_price_formatted'] = pg_visitor_money($_pg_orig_dec, $_pg_curr);
        $product['has_discount']             = (string)$_pg_has_disc;
        $product['discount_amount']          = $_pg_save_cents > 0 ? number_format($_pg_save_cents / 100, 2, '.', '') : '';
        $product['discount_amount_formatted']= $_pg_save_cents > 0 ? pg_visitor_money($_pg_save_cents / 100, $_pg_curr) : '';
        $product['discount_percent']         = $_pg_save_pct > 0 ? (string)$_pg_save_pct : '';
        $product['image_url']       = $_pg_main !== '' ? PATH . encode_url_path($_pg_main) : '';
        $product['address_name']    = $_pg_addr;
        $product['gallery']         = $_pg_gallery;
        $product['gallery_count']   = count($_pg_gallery);
        $product['out_of_stock']    = $_pg_oos;
        $product['quantity_available'] = ($_pg_inv_tracked && !$_pg_backorder) ? $_pg_inv_qty : null;

        $response = array(
            'status' => 'success',
            'product' => $product
        );

        echo encode_json($response);
        exit();

        break;

    // Used to get the appropriate shipping methods for the shipping address and arrival date
    // that customer selected on express order

    case 'get_shipping_methods':

        require_once(dirname(__FILE__) . '/shipping.php');

        $response = get_shipping_methods($request);

        echo encode_json($response);
        exit();

        break;

    case 'get_style':
        $style = db_item(
            "SELECT
                style_id AS id,
                style_name AS name,
                style_type AS type,
                style_layout AS layout,
                style_empty_cell_width_percentage AS empty_cell_width_percentage,
                style_code AS code,
                style_head AS head,
                social_networking_position,
                additional_body_classes,
                collection,
                layout_type
            FROM style
            WHERE style_id = '" . escape($request['style']['id']) . "'"
        );

        // If a style was not found then respond with an error.
        if (!$style) {
            $response = array(
                'status' => 'error',
                'message' => 'Style could not be found.'
            );

            echo encode_json($response);
            exit();
        }

        $response = array(
            'status' => 'success',
            'style' => $style
        );

        echo encode_json($response);
        exit();

        break;

    case 'get_styles':
        $sql_code = "";

        if ($request['code'] == true) {
            $sql_code = "style_code AS code,";
        }

        $sql_where = "";

        if (($request['type'] ?? '') != '') {
            $sql_where .= "WHERE (style_type = '" . escape($request['type']) . "')";
        }

        if (($request['search'] ?? '') != '') {
            if ($sql_where == '') {
                $sql_where .= "WHERE ";
            } else {
                $sql_where .= " AND ";
            }

            $sql_where .= "(style_name LIKE '%" . escape(escape_like($request['search'])) . "%')";
        }

        $styles = db_items(
            "SELECT
                style_id AS id,
                style_name AS name,
                style_type AS type,
                style_layout AS layout,
                style_empty_cell_width_percentage AS empty_cell_width_percentage,
                $sql_code
                style_head AS head,
                social_networking_position,
                additional_body_classes,
                collection,
                layout_type
            FROM style
            $sql_where
            ORDER BY style_timestamp DESC"
        );

        // Loop through the styles in order to get cells for system styles.
        foreach ($styles as $key => $style) {
            // If this style is a system style, then get cells.
            if ($style['type'] == 'system') {
                $cells = db_items(
                    "SELECT
                        area,
                        `row`, # Backticks for reserved word.
                        col,
                        region_type,
                        region_name
                    FROM system_style_cells
                    WHERE style_id = '" . $style['id'] . "'"
                );

                $styles[$key]['cells'] = $cells;
            }
        }

        $response = array(
            'status' => 'success',
            'styles' => $styles
        );

        echo encode_json($response);
        exit();

        break;

    case 'test':
        $response = array('status' => 'success');

        echo encode_json($response);
        exit();

        break;
    case 'create_design_region':
        validate_token();
        $user = validate_user();

        if ($user['role'] < 1) {
            $name = trim($request['designer_region']['name']);
            $query = "INSERT INTO cregion (cregion_name, cregion_content, cregion_designer_type, cregion_user, cregion_timestamp) "
                . "VALUES ('" . escape($name) . "', '" . escape($request['designer_region']['content']) . "', 'yes', " . USER_ID . ", UNIX_TIMESTAMP())";
            // insert row into region table
            $result = mysqli_query(db::$con, $query) or output_error('Query failed');
            log_activity(lang(array('string' => '{var:1} ({var:2}) was created', 'vars' => array(lang('designer region'), $name))), $_SESSION['sessionusername']);

            $response = array(
                'status' => 'success',
                'name' => $name
            );
            echo encode_json($response);
        } else {
            $response = array(
                'status' => 'error',
                'message' => lang('Access denied'),
            );
            echo encode_json($response);
        }

        exit();
        break;
    case 'create_dynamic_region':
        validate_token();
        $user = validate_user();
        validate_area_access($user, 'administrator');

        if (($user['role'] < 1) && ((defined('DYNAMIC_REGIONS') == true) && (DYNAMIC_REGIONS == true))) {
            $name = trim($request['dynamic_region']['name']);

            $query = "INSERT INTO dregion (dregion_name, dregion_code, dregion_user, dregion_timestamp) "
                . "VALUES ('" . escape($name) . "', '" . escape($request['dynamic_region']['code']) . "', " . USER_ID . ", UNIX_TIMESTAMP())";
            // insert row into region table
            $result = mysqli_query(db::$con, $query) or output_error('Query failed');
            log_activity(lang(array('string' => '{var:1} ({var:2}) was created', 'vars' => array(lang('dynamic region'), $name))), $_SESSION['sessionusername']);

            $response = array(
                'status' => 'success',
                'name' => $name
            );
        } else {
            $response = array(
                'status' => 'error',
                'message' => lang('Access denied'),
            );
        }

        echo encode_json($response);
        exit();
        break;
    case 'update_designer_region':
        validate_token();

        $designer_region = db_item(
            "SELECT cregion_id AS id
            FROM cregion
            WHERE
                (cregion_designer_type = 'yes')
                AND (cregion_id = '" . escape($request['designer_region']['id']) . "')"
        );

        // If a designer region was not found then respond with an error.
        if (!$designer_region) {
            $response = array(
                'status' => 'error',
                'message' => 'Designer Region could not be found.'
            );

            echo encode_json($response);
            exit();
        }

        db(
            "UPDATE cregion
            SET
                cregion_content = '" . escape($request['designer_region']['content']) . "',
                cregion_timestamp = UNIX_TIMESTAMP(),
                cregion_user = '" . USER_ID . "'
            WHERE cregion_id = '" . escape($request['designer_region']['id']) . "'"
        );

        $response = array('status' => 'success');

        echo encode_json($response);
        exit();

        break;

    case 'update_dynamic_region':
        validate_token();

        // A dynamic region is PHP that runs on every page it is placed on.
        // Creating and editing one (add_dynamic_region.php,
        // edit_dynamic_region.php) stands at administrator, and the page
        // designer only offers the region editor to role 0, so the save
        // endpoint holds the same line rather than the general designer gate.
        $user = validate_user();

        if ((int) $user['role'] !== 0) {
            respond(array(
                'status' => 'error',
                'message' => lang('Access denied.'),
            ));
        }

        $dynamic_region = db_item(
            "SELECT dregion_id AS id
            FROM dregion
            WHERE dregion_id = '" . escape($request['dynamic_region']['id']) . "'"
        );

        // If a dynamic region was not found then respond with an error.
        if (!$dynamic_region) {
            $response = array(
                'status' => 'error',
                'message' => 'Dynamic Region could not be found.'
            );

            echo encode_json($response);
            exit();
        }

        db(
            "UPDATE dregion
            SET
                dregion_code = '" . escape($request['dynamic_region']['content']) . "',
                dregion_timestamp = UNIX_TIMESTAMP(),
                dregion_user = '" . USER_ID . "'
            WHERE dregion_id = '" . escape($request['dynamic_region']['id']) . "'"
        );

        $response = array('status' => 'success');

        echo encode_json($response);
        exit();

        break;

    case 'update_file':
        validate_token();

        $file = db_item(
            "SELECT
                id,
                name
            FROM files
            WHERE id = '" . escape($request['file']['id']) . "'"
        );

        // If a file was not found then respond with an error.
        if (!$file) {
            $response = array(
                'status' => 'error',
                'message' => 'File could not be found.'
            );

            echo encode_json($response);
            exit();
        }

        unlink(FILE_DIRECTORY_PATH . '/' . $file['name']);

        file_put_contents(FILE_DIRECTORY_PATH . '/' . $file['name'], $request['file']['content']);

        db(
            "UPDATE files
            SET
                timestamp = UNIX_TIMESTAMP(),
                user = '" . USER_ID . "'
            WHERE id = '" . escape($request['file']['id']) . "'"
        );

        $response = array('status' => 'success');

        echo encode_json($response);
        exit();

        break;

    case 'update_layout':
        validate_token();

        // A custom layout is a PHP file that the page render includes as it
        // stands, so a hosted site neither writes nor uses one.
        if (pg_hosted()) {
            echo encode_json(array(
                'status' => 'error',
                'message' => lang('Custom layouts are not available on this site.')
            ));
            exit();
        }

        $layout = db_item(
            "SELECT
                page_id AS id,
                page_name AS name
            FROM page
            WHERE page_id = '" . e($request['layout']['id']) . "'"
        );

        // If a layout was not found then respond with an error.
        if (!$layout) {
            $response = array(
                'status' => 'error',
                'message' => 'Layout could not be found.'
            );

            echo encode_json($response);
            exit();
        }

        require_once(dirname(__FILE__) . '/generate_layout_content.php');

        // If the saved layout matches the generated layout, then mark
        // that the layout has not been modified and delete layout file.
        // We strip white-spaces, because we had issues where possibly
        // new lines characters were different in the generated content from
        // the codemirror content.
        if (preg_replace('/\s+/', '', $request['layout']['content']) === preg_replace('/\s+/', '', generate_layout_content($request['layout']['id']))) {
            // If a layout file exists, then delete it.
            if (file_exists(LAYOUT_DIRECTORY_PATH . '/' . $layout['id'] . '.php')) {
                unlink(LAYOUT_DIRECTORY_PATH . '/' . $layout['id'] . '.php');
            }

            $modified = 0;

            // Otherwise the layout content is unique, so save content to file system.
        } else {
            @file_put_contents(LAYOUT_DIRECTORY_PATH . '/' . $layout['id'] . '.php', $request['layout']['content']);

            $modified = 1;
        }

        // Update the page to mark whether the layout has been modified or not,
        // so that we don't auto-generate the layout anymore when changes are made to the form.
        db(
            "UPDATE page
            SET
                layout_modified = '$modified',
                page_user = '" . USER_ID . "',
                page_timestamp = UNIX_TIMESTAMP()
            WHERE page_id = '" . e($request['layout']['id']) . "'"
        );

        log_activity('layout for page (' . $layout['name'] . ') was modified');

        $response = array('status' => 'success');

        echo encode_json($response);
        exit();

        break;

    // Update shipping & tracking info for completed order.

    case 'update_order':

        validate_token();

        require_once(dirname(__FILE__) . '/update_order.php');

        $response = update_order(array('order' => $request['order']));

        echo encode_json($response);
        exit();

        break;

    case 'update_page_designer_properties':

        validate_token();

        $_SESSION['software']['page_designer']['query'] = $request['query'];

        respond(array('status' => 'success'));

        break;

    case 'update_product_status':

        validate_token();

        $user = validate_user();
        validate_ecommerce_access($user);

        if ($request['status'] == 'enabled') {
            $enabled = 1;
        } else {
            $enabled = 0;
        }

        db(
            "UPDATE products
            SET
                enabled = '$enabled',
                user = '" . USER_ID . "',
                timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . e($request['id']) . "'"
        );

        $product = db_item(
            "SELECT name, short_description
            FROM products
            WHERE id = '" . e($request['id']) . "'"
        );

        log_activity('product (' . $product['name'] . ' - ' . $product['short_description'] . ') was ' . $request['status']);

        $response = array('status' => 'success');

        echo encode_json($response);
        exit();

        break;

    case 'update_product_group_status':

        validate_token();

        $user = validate_user();
        validate_ecommerce_access($user);

        require_once(dirname(__FILE__) . '/update_product_group_status.php');

        $items = update_product_group_status(array(
            'id' => $request['id'],
            'status' => $request['status']
        ));

        $response = array(
            'status' => 'success',
            'items' => $items
        );

        echo encode_json($response);
        exit();

        break;

    case 'update_style':
        validate_token();

        $style = db_item(
            "SELECT style_id AS id
            FROM style
            WHERE style_id = '" . escape($request['style']['id']) . "'"
        );

        // If a style was not found then respond with an error.
        if (!$style) {
            $response = array(
                'status' => 'error',
                'message' => 'Style could not be found.'
            );

            echo encode_json($response);
            exit();
        }

        db(
            "UPDATE style
            SET
                style_code = '" . escape($request['style']['code']) . "',
                style_timestamp = UNIX_TIMESTAMP(),
                style_user = '" . USER_ID . "'
            WHERE style_id = '" . escape($request['style']['id']) . "'"
        );

        $response = array('status' => 'success');

        echo encode_json($response);
        exit();

        break;

    case 'update_toolbar_properties':
        // Exempt from the general gate, which answered a request without a
        // session this way; the same answer is kept.
        if (!USER_LOGGED_IN) {
            respond(array(
                'status' => 'error',
                'message' => 'Invalid login.'
            ));
        }
        validate_token();

        $_SESSION['software']['toolbar_enabled'] = (bool) ($request['enabled'] ?? false);

        respond(array('status' => 'success'));

        break;


    // A guided tour has been watched, or skipped -- which is the same answer to
    // the only question stored: do not open it by itself again.  The key is
    // checked against a pattern in pg_tour_mark() before it reaches the row.
    case 'tour_seen':

        $user = validate_user();
        validate_token();

        pg_tour_mark($user['id'], (isset($request['key']) ? (string) $request['key'] : ''));

        respond(array('status' => 'success'));

        break;

    // The account menu's quick form: the signed-in person's own first and
    // last name, into their address-book contact (includes/fn/contacts.php).
    case 'contact_quick_save':

        $user = validate_user();
        validate_token();

        $result = pg_contact_quick_save($user['id'], (string) ($request['first_name'] ?? ''), (string) ($request['last_name'] ?? ''), (string) ($request['mobile_phone'] ?? ''), (string) ($request['photo'] ?? ''));

        respond($result['ok'] ? array('status' => 'success') : array('status' => 'error', 'message' => $result['error']));

        break;


    case 'getproductlist':
        $data = array();
        /* get results for just this screen*/
        $product_groups = db("SELECT 
        id,
        name,
        enabled,
        parent_id,
        short_description,
        full_description,
        details,
        code,
        keywords,
        image_name,
        address_name,
        title,
        meta_description,
        meta_keywords,
        seo_score,
        attributes,
        timestamp
        FROM product_groups 
        WHERE product_groups.display_type != 'browse'");
        $count_product_groups = db("SELECT count(*) FROM product_groups  WHERE product_groups.display_type != 'browse'");

        foreach ($product_groups as $product_group) {
            //$products = db("SELECT
            //     products.id as id,
            //     products.name as name,
            //     products.enabled,
            //     products.image_name  as image_name,
            //     products.inventory as inventory,
            //     products.inventory_quantity as inventory_quantity,
            //     products.short_description as short_description,
            //     products.price as price,
            //     products.taxable as taxable,
            //     products.form_name as form_name,
            //     products.out_of_stock as out_of_stock,
            //     products.out_of_stock_timestamp as out_of_stock_timestamp,
            //     products.timestamp as timestamp
            //FROM products_groups_xref
            //LEFT JOIN products on products.id = product
            //WHERE product_group = '" . e($product_group['id']) . "'");
            //foreach($products as $product) {}

            array_push($data, array(
                'id' => $product_group['id'],
                'name' => $product_group['name'],
                'enabled' => $product_group['enabled'],
                'short_description' => $product_group['short_description'],
                'image_name' => $product_group['image_name'],
                'timestamp' => get_relative_time(array('timestamp' => $product_group['timestamp']))
            ));


        }


        //return success json output
        $response = array(
            'status' => 'success',
            'title' => lang(array('string' => 'List of {var:1}', 'vars' => array(lang('Product(s)')))),
            'max' => $count_product_groups,
            'data' => $data
        );

        echo respond($response);
        break;

    case 'get_unselected_products':
        $user = validate_user();
        validate_ecommerce_access($user);

        $product_group_id = (int) ($request['product_group_id'] ?? 0);
        $offset = max(0, (int) ($request['offset'] ?? 0));
        $limit = 100;

        // Get IDs of products already in this group.
        $selected_ids = db_values(
            "SELECT product FROM products_groups_xref WHERE product_group = '$product_group_id'"
        );

        $exclude_sql = '';
        if ($selected_ids) {
            $exclude_sql = "AND id NOT IN (" . implode(',', array_map('intval', $selected_ids)) . ")";
        }

        $show_image = (bool) ECOMMERCE_SHOW_PRODUCT_IMAGES;

        $products = db_items(
            "SELECT id, name, enabled, short_description, image_name, price
             FROM products
             WHERE 1 $exclude_sql
             ORDER BY timestamp DESC
             LIMIT $limit OFFSET $offset"
        );

        $total_unselected = (int) db_value(
            "SELECT COUNT(*) FROM products WHERE 1 $exclude_sql"
        );

        $rows_html = '';
        foreach ($products as $product) {
            $product['price'] = $product['price'] / 100;
            $status_class = $product['enabled'] == 1 ? 'text-success' : 'text-danger';

            $output_image_column = '';
            if ($show_image) {
                if (!$product['image_name']) {
                    $output_image_column = '<td class="align-middle text-start"><svg class="bd-placeholder-img img-thumbnail" width="50" height="50" xmlns="http://www.w3.org/2000/svg" role="img"><rect width="100%" height="100%" fill="#868e96"></rect><text x="10%" y="50%" style="font-size: 8px;" fill="#dee2e6" dy=".3em">' . lang('No Image') . '</text></svg></td>';
                } else {
                    $output_image_column = '<td class="align-middle text-start"><img style="width: 50px;height:50px;" class="img-fluid img-thumbnail lazy" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/loading.gif" data-src="' . PATH . h($product['image_name']) . '" /></td>';
                }
            }

            $rows_html .=
                '<tr id="' . $product['id'] . '">' .
                '<td class="select-all align-middle text-start"><input class="form-check-input" type="checkbox" name="products[]" value="' . $product['id'] . '"/></td>' .
                '<td class="align-middle text-start"><button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2" data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'edit_product.php?id=' . $product['id'] . '\'"><i class="bi bi-pencil"></i></button></td>' .
                $output_image_column .
                '<td class="chart_label align-middle ' . $status_class . '">' . h($product['name']) . '</td>' .
                '<td class="align-middle ' . $status_class . '">' . h($product['short_description']) . '</td>' .
                '<td class="align-middle text-end">' . prepare_amount($product['price']) . '</td>' .
                '<td class="align-middle"><input class="form-control text-end" type="text" name="sort_order_product_' . $product['id'] . '" size="5" value="" maxlength="4" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0" style="text-align: right;width:60px;" /></td>' .
                '<td></td>' .
                '</tr>';
        }

        $has_more = ($offset + count($products)) < $total_unselected;

        $response = array(
            'status' => 'success',
            'rows' => $rows_html,
            'has_more' => $has_more,
            'loaded' => count($products),
            'total' => $total_unselected
        );

        echo encode_json($response);
        exit();

        break;

    case 'sort_menu_items':
        validate_token();

        $user = validate_user();

        $menu_id = (int) $request['menu_id'];
        $groups = isset($request['groups']) ? $request['groups'] : array();

        if (!$menu_id || !is_array($groups)) {
            respond(array('status' => 'error', 'message' => lang('Invalid request.')));
        }

        if (!db_value("SELECT 1 FROM menus WHERE id = '" . e($menu_id) . "'")) {
            respond(array('status' => 'error', 'message' => lang('Access denied.')));
        }

        if (($user['role'] == 3) && (in_array($menu_id, get_items_user_can_edit('menus', $user['id'])) == false)) {
            log_activity(lang(array('string' => 'access denied because user does not have access to edit menu ({var:1})', 'vars' => $menu_id)), $_SESSION['sessionusername']);
            respond(array('status' => 'error', 'message' => lang('Access denied.')));
        }

        foreach ($groups as $group) {
            $parent_id = (int) (isset($group['parent_id']) ? $group['parent_id'] : 0);
            $order = isset($group['order']) ? $group['order'] : array();
            $sort_order = 1;
            foreach ($order as $item_id) {
                $item_id = (int) $item_id;
                if ($item_id <= 0) {
                    continue;
                }
                db("UPDATE menu_items
                    SET sort_order = '$sort_order', parent_id = '$parent_id'
                    WHERE id = '$item_id' AND menu_id = '$menu_id'");
                $sort_order++;
            }
        }

        db("UPDATE menus SET last_modified_user_id = '" . $user['id'] . "', last_modified_timestamp = UNIX_TIMESTAMP() WHERE id = '$menu_id'");

        log_activity(lang(array('string' => 'menu ({var:1}) items were reordered', 'vars' => $menu_id)), $_SESSION['sessionusername']);

        respond(array('status' => 'success'));
        break;

    // ── Barcode: get all barcodes for a product ─────────────────────────
    case 'get_product_barcodes':
        // Exempt from the general gate, which answered a request without a
        // session this way; the same answer is kept.
        if (!USER_LOGGED_IN) {
            respond(array(
                'status' => 'error',
                'message' => 'Invalid login.'
            ));
        }
        $user = validate_user();
        // The product screens' own rule, answered in JSON: roles 0-2, or a
        // basic user with manage_ecommerce.
        if (($user['role'] > 2) && ($user['manage_ecommerce'] != true)) {
            log_activity(lang('access denied to commerce'), $_SESSION['sessionusername']);
            respond(array(
                'status' => 'error',
                'message' => lang('Access denied')));
        }
        validate_token();

        $product_id = (int) ($request['product_id'] ?? 0);
        if (!$product_id)
            respond(array('status' => 'error', 'message' => lang('Product not found.')));

        $rows = db_items(
            "SELECT id, barcode, barcode_type, created_at
             FROM product_barcodes
             WHERE product_id = '" . e($product_id) . "'
             ORDER BY created_at DESC"
        );

        respond(array('status' => 'success', 'barcodes' => $rows ?: array()));
        break;

    // ── Barcode: generate a unique barcode for a product ─────────────────
    case 'generate_product_barcode':
        // Exempt from the general gate, which answered a request without a
        // session this way; the same answer is kept.
        if (!USER_LOGGED_IN) {
            respond(array(
                'status' => 'error',
                'message' => 'Invalid login.'
            ));
        }
        $user = validate_user();
        // The product screens' own rule, answered in JSON: roles 0-2, or a
        // basic user with manage_ecommerce.
        if (($user['role'] > 2) && ($user['manage_ecommerce'] != true)) {
            log_activity(lang('access denied to commerce'), $_SESSION['sessionusername']);
            respond(array(
                'status' => 'error',
                'message' => lang('Access denied')));
        }
        validate_token();

        $product_id = (int) ($request['product_id'] ?? 0);
        $barcode_type = trim($request['barcode_type'] ?? 'CODE128');
        if (!$product_id)
            respond(array('status' => 'error', 'message' => lang('Product not found.')));

        $attempts = 0;
        do {
            if ($barcode_type === 'EAN13') {
                $digits = '';
                for ($i = 0; $i < 12; $i++)
                    $digits .= rand(0, 9);
                $sum = 0;
                for ($i = 0; $i < 12; $i++)
                    $sum += ($i % 2 === 0 ? 1 : 3) * (int) $digits[$i];
                $barcode = $digits . ((10 - ($sum % 10)) % 10);
            } elseif ($barcode_type === 'UPC') {
                $digits = '';
                for ($i = 0; $i < 11; $i++)
                    $digits .= rand(0, 9);
                $sum = 0;
                for ($i = 0; $i < 11; $i++)
                    $sum += ($i % 2 === 0 ? 3 : 1) * (int) $digits[$i];
                $barcode = $digits . ((10 - ($sum % 10)) % 10);
            } else {
                $barcode = str_pad($product_id, 5, '0', STR_PAD_LEFT) . str_pad(rand(0, 9999999), 7, '0', STR_PAD_LEFT);
            }
            $exists = db_value("SELECT COUNT(*) FROM product_barcodes WHERE barcode = '" . e($barcode) . "'");
            $attempts++;
        } while ($exists && $attempts < 20);

        if ($exists)
            respond(array('status' => 'error', 'message' => lang('Could not generate a unique barcode. Please try again.')));

        respond(array('status' => 'success', 'barcode' => $barcode, 'barcode_type' => $barcode_type));
        break;

    // ── Barcode: save a new barcode for a product (always inserts) ────────
    case 'save_product_barcode':
        // Exempt from the general gate, which answered a request without a
        // session this way; the same answer is kept.
        if (!USER_LOGGED_IN) {
            respond(array(
                'status' => 'error',
                'message' => 'Invalid login.'
            ));
        }
        $user = validate_user();
        // The product screens' own rule, answered in JSON: roles 0-2, or a
        // basic user with manage_ecommerce.
        if (($user['role'] > 2) && ($user['manage_ecommerce'] != true)) {
            log_activity(lang('access denied to commerce'), $_SESSION['sessionusername']);
            respond(array(
                'status' => 'error',
                'message' => lang('Access denied')));
        }
        validate_token();

        $product_id = (int) ($request['product_id'] ?? 0);
        $barcode = trim($request['barcode'] ?? '');
        $barcode_type = trim($request['barcode_type'] ?? 'CODE128');

        if (!$product_id)
            respond(array('status' => 'error', 'message' => lang('Product not found.')));
        if ($barcode === '')
            respond(array('status' => 'error', 'message' => lang('Barcode value is required.')));

        if ($barcode_type === 'EAN13' && (!ctype_digit($barcode) || strlen($barcode) !== 13)) {
            respond(array('status' => 'error', 'message' => lang('EAN-13 barcode must be exactly 13 digits.')));
        }
        if ($barcode_type === 'UPC' && (!ctype_digit($barcode) || strlen($barcode) !== 12)) {
            respond(array('status' => 'error', 'message' => lang('UPC-A barcode must be exactly 12 digits.')));
        }

        // Barcode must be globally unique
        $conflict = db_value("SELECT COUNT(*) FROM product_barcodes WHERE barcode = '" . e($barcode) . "'");
        if ($conflict)
            respond(array('status' => 'error', 'message' => lang('This barcode is already assigned to another product.')));

        $now = date('Y-m-d H:i:s');
        db("INSERT INTO product_barcodes (product_id, barcode, barcode_type, created_at, updated_at)
            VALUES ('" . e($product_id) . "', '" . e($barcode) . "', '" . e($barcode_type) . "', '" . e($now) . "', '" . e($now) . "')");

        $new_id = db_value("SELECT LAST_INSERT_ID()");
        log_activity(lang(array('string' => 'Saved barcode {var:1} for product #{var:2}.', 'vars' => array($barcode, $product_id))));
        respond(array('status' => 'success', 'message' => lang('Barcode saved.'), 'id' => (int) $new_id, 'barcode' => $barcode, 'barcode_type' => $barcode_type));
        break;

    // ── Barcode: delete a single barcode row by id ───────────────────────
    case 'delete_product_barcode':
        // Exempt from the general gate, which answered a request without a
        // session this way; the same answer is kept.
        if (!USER_LOGGED_IN) {
            respond(array(
                'status' => 'error',
                'message' => 'Invalid login.'
            ));
        }
        $user = validate_user();
        // The product screens' own rule, answered in JSON: roles 0-2, or a
        // basic user with manage_ecommerce.
        if (($user['role'] > 2) && ($user['manage_ecommerce'] != true)) {
            log_activity(lang('access denied to commerce'), $_SESSION['sessionusername']);
            respond(array(
                'status' => 'error',
                'message' => lang('Access denied')));
        }
        validate_token();

        $id = (int) ($request['id'] ?? 0);
        $product_id = (int) ($request['product_id'] ?? 0);
        if (!$id || !$product_id)
            respond(array('status' => 'error', 'message' => lang('Product not found.')));

        db("DELETE FROM product_barcodes WHERE id = '" . e($id) . "' AND product_id = '" . e($product_id) . "'");
        log_activity(lang(array('string' => 'Deleted barcode #{var:1} for product #{var:2}.', 'vars' => array($id, $product_id))));
        respond(array('status' => 'success', 'message' => lang('Barcode deleted.')));
        break;

    // ── Barcode: bulk assign barcodes to selected products ───────────────
    case 'bulk_assign_barcodes':
        // Exempt from the general gate, which answered a request without a
        // session this way; the same answer is kept.
        if (!USER_LOGGED_IN) {
            respond(array(
                'status' => 'error',
                'message' => 'Invalid login.'
            ));
        }
        $user = validate_user();
        // The product screens' own rule, answered in JSON: roles 0-2, or a
        // basic user with manage_ecommerce.
        if (($user['role'] > 2) && ($user['manage_ecommerce'] != true)) {
            log_activity(lang('access denied to commerce'), $_SESSION['sessionusername']);
            respond(array(
                'status' => 'error',
                'message' => lang('Access denied')));
        }
        validate_token();

        $product_ids = isset($request['product_ids']) ? (array) $request['product_ids'] : array();
        $barcode_type = trim($request['barcode_type'] ?? BARCODE_DEFAULT_TYPE);
        if (empty($product_ids))
            respond(array('status' => 'error', 'message' => lang('No products selected.')));

        $assigned = 0;
        $skipped = 0;
        $now = date('Y-m-d H:i:s');

        foreach ($product_ids as $pid) {
            $pid = (int) $pid;
            if (!$pid)
                continue;

            // Generate unique barcode
            $barcode = '';
            $attempts = 0;
            do {
                if ($barcode_type === 'EAN13') {
                    $digits = '';
                    for ($i = 0; $i < 12; $i++)
                        $digits .= rand(0, 9);
                    $sum = 0;
                    for ($i = 0; $i < 12; $i++)
                        $sum += ($i % 2 === 0 ? 1 : 3) * (int) $digits[$i];
                    $barcode = $digits . ((10 - ($sum % 10)) % 10);
                } elseif ($barcode_type === 'UPC') {
                    $digits = '';
                    for ($i = 0; $i < 11; $i++)
                        $digits .= rand(0, 9);
                    $sum = 0;
                    for ($i = 0; $i < 11; $i++)
                        $sum += ($i % 2 === 0 ? 3 : 1) * (int) $digits[$i];
                    $barcode = $digits . ((10 - ($sum % 10)) % 10);
                } else {
                    $barcode = str_pad($pid, 5, '0', STR_PAD_LEFT) . str_pad(rand(0, 9999999), 7, '0', STR_PAD_LEFT);
                }
                $exists = db_value("SELECT COUNT(*) FROM product_barcodes WHERE barcode = '" . e($barcode) . "'");
                $attempts++;
            } while ($exists && $attempts < 30);

            if ($exists) {
                $skipped++;
                continue;
            }

            db("INSERT INTO product_barcodes (product_id, barcode, barcode_type, created_at, updated_at)
                VALUES ('" . e($pid) . "', '" . e($barcode) . "', '" . e($barcode_type) . "', '" . e($now) . "', '" . e($now) . "')");
            $assigned++;
        }

        log_activity(lang(array('string' => '{var:1} barcode(s) assigned.', 'vars' => array($assigned))));
        respond(array(
            'status' => 'success',
            'assigned' => $assigned,
            'skipped' => $skipped,
            'message' => lang(array('string' => '{var:1} barcode(s) assigned.', 'vars' => array($assigned)))
        ));
        break;

    // ── Barcode: save global label template ──────────────────────────────
    case 'save_barcode_template':
        // Exempt from the general gate, which answered a request without a
        // session this way; the same answer is kept.
        if (!USER_LOGGED_IN) {
            respond(array(
                'status' => 'error',
                'message' => 'Invalid login.'
            ));
        }
        $user = validate_user();
        // The product screens' own rule, answered in JSON: roles 0-2, or a
        // basic user with manage_ecommerce.
        if (($user['role'] > 2) && ($user['manage_ecommerce'] != true)) {
            log_activity(lang('access denied to commerce'), $_SESSION['sessionusername']);
            respond(array(
                'status' => 'error',
                'message' => lang('Access denied')));
        }
        validate_token();

        $template = isset($request['template']) ? trim($request['template']) : '';
        if ($template !== '' && json_decode($template) === null) {
            respond(array('status' => 'error', 'message' => lang('Invalid template data.')));
        }
        db("UPDATE config SET barcode_label_template = '" . e($template) . "' LIMIT 1");
        log_activity(lang('Updated barcode label template.'));
        respond(array('status' => 'success', 'message' => lang('Template saved.')));
        break;

    // ========================= SHARED COMPONENTS =========================
    // Internal visual-designer endpoint — session + token auth, admin/designer only.
    // shared_ref node: { type:'shared_ref', props:{ sharedId:N, sharedName:'...' }, children:[] }
    case 'offer_editor':
        // Offer editor (edit_offer.php): load, save, toggle, delete, cleanup.
        // The sub-actions live in their own include and respond themselves.
        require_once(dirname(__FILE__) . '/edit_offer_f.php');
        pg_offer_editor_handle($request);
        break;

    // ========================= VISUAL DESIGNER — PAGE TABS =========================
    // Internal endpoint for the multi-page designer. Session + token auth,
    // same gate as the editor screens themselves (designer = role <= 1).
    case 'designer':
        validate_token();
        $user = validate_user();
        require_once(dirname(__FILE__) . '/includes/designer_access.php');

        $sub = isset($request['sub_action']) ? $request['sub_action'] : '';

        // A content-level operator (manager, user) gets the endpoints the
        // editor needs to READ a page and to work inside it. Everything that
        // shapes the design — adding, detaching or deleting pages, saving a
        // theme, rewriting a shared component — stays with designers.
        //
        // The list is an allow-list on purpose: a new endpoint is closed
        // until somebody decides it is safe, which is the right default for
        // a file that grows an action a month.
        // The refusal is JSON, not validate_area_access(): that answers with
        // an HTML error page, and every caller here is waiting for JSON — it
        // would read the page as "unexpected token <" and show the operator
        // nothing about why the button did nothing.
        if (!pg_designer_is_full($user)) {
            $content_ok = array('presence', 'leave', 'lock_release', 'note_save', 'notes_fetch',
                                'page_load', 'seo_check', 'import_fragment',
                                'form_fields', 'preview_widgets', 'widget_ghosts');
            if (pg_designer_access($user) === PG_DESIGNER_ACCESS_NONE
                || !in_array($sub, $content_ok, true)) {
                respond(array('status' => 'error', 'message' => lang('Permission denied.')));
            }
        }

        // Collaboration endpoints all speak the same two parameters, so they
        // are resolved once rather than in each branch.
        if (in_array($sub, array('presence', 'leave', 'lock_release', 'note_save', 'notes_fetch'), true)) {
            require_once(dirname(__FILE__) . '/includes/designer_collab.php');
        }

        // Page changes proposed by an assistant (includes/designer_ai.php):
        // asking, waiting, applying onto the tab. Designers only - the list
        // above does not open them to a content-level operator.
        if (in_array($sub, array('ai_state', 'ai_ask', 'ai_status', 'ai_cancel', 'ai_apply', 'ai_dismiss'), true)) {
            require_once(dirname(__FILE__) . '/includes/designer_ai.php');
            respond(pg_design_ai_panel($sub, $request, $user));
        }

        switch ($sub) {

            // ── PRESENCE ─────────────────────────────────────────────────
            // One call does everything the editor needs on a heartbeat: says
            // this tab is alive, claims (or renews) the lock on the page it
            // is showing, and reports back who else is here and whether this
            // tab may edit. One round trip because it runs every twenty
            // seconds in every open editor — three would be three times the
            // load for the same answer.
            case 'presence':
                $co_key   = isset($request['session_key']) ? (string)$request['session_key'] : '';
                $co_style = isset($request['style_id'])    ? (int)$request['style_id']       : 0;
                $co_page  = isset($request['page_id'])     ? (int)$request['page_id']        : 0;
                if (!pg_collab_ready()) {
                    // No schema yet: the editor behaves exactly as it did
                    // before this feature existed.
                    respond(array('status' => 'success', 'ready' => false,
                                  'may_edit' => true, 'peers' => array(), 'holder' => null));
                }
                pg_collab_beat($co_key, $co_style, $co_page, $user['id']);
                // `takeover`: this person's OTHER tab holds the page and they
                // asked for it here. The claim evicts a holder only when it
                // is the same user — the flag cannot take a page from
                // somebody else.
                $co_take = !empty($request['takeover']);
                $co_lock = ($co_page > 0)
                    ? pg_collab_claim_page($co_key, $co_page, $co_style, $user['id'], $co_take)
                    : array('held' => true, 'holder' => null);
                $co_holder = null;
                if (!$co_lock['held'] && is_array($co_lock['holder'])) {
                    $co_brief  = pg_collab_user_brief($co_lock['holder']['user_id']);
                    $co_holder = array(
                        'user_id' => (int)$co_lock['holder']['user_id'],
                        'name'    => $co_brief['name'],
                        'avatar'  => $co_brief['avatar'],
                        'since'   => (int)$co_lock['holder']['acquired_at'],
                    );
                }
                respond(array(
                    'status'   => 'success',
                    'ready'    => true,
                    'may_edit' => (bool)$co_lock['held'],
                    'holder'   => $co_holder,
                    'peers'    => pg_collab_peers($co_key, $co_style),
                    'beat'     => PG_COLLAB_BEAT,
                    // page_id => when a note was last written there. The
                    // editor compares this with what it already has and only
                    // asks for the notes of a page whose number moved.
                    'notes'    => pg_collab_note_stamps($co_style),
                ));
                break;

            // Closing the editor. Explicit so the next person does not have to
            // wait out the staleness window; a crash still resolves on its own.
            case 'leave':
                pg_collab_leave(isset($request['session_key']) ? (string)$request['session_key'] : '');
                respond(array('status' => 'success'));
                break;

            // Switching to another tab inside the editor: drop the lock on the
            // page being left before claiming the next one, so a colleague can
            // pick it up immediately.
            case 'lock_release':
                pg_collab_release_page(
                    isset($request['session_key']) ? (string)$request['session_key'] : '',
                    isset($request['page_id']) ? (int)$request['page_id'] : 0);
                respond(array('status' => 'success'));
                break;

            // The one write a view-mode session may make. Notes live on the
            // node, in the page tree — which is exactly what a locked-out
            // session cannot save — so writing one has its own endpoint and
            // is deliberately NOT gated on the lock. Bounded to one property
            // on one node, so it cannot become a way around the lock.
            case 'note_save':
                if (!pg_collab_note_save(
                        isset($request['page_id']) ? (int)$request['page_id'] : 0,
                        isset($request['node_id']) ? (string)$request['node_id'] : '',
                        isset($request['note']) ? (string)$request['note'] : '',
                        $user)) {
                    respond(array('status' => 'error', 'message' => lang('Sorry, we could not accept your request.')));
                }
                respond(array('status' => 'success'));
                break;

            // The notes on one page, and nothing else.
            //
            // Asked for when the heartbeat says the page's note stamp moved.
            // Not the tree: the person asking has the page open and is
            // part-way through their own edits, so returning a tree would
            // force a choice between discarding their work and merging two
            // layouts. A notification should never be making that choice.
            case 'notes_fetch':
                $nf_page = isset($request['page_id']) ? (int)$request['page_id'] : 0;
                if ($nf_page <= 0) {
                    respond(array('status' => 'error', 'message' => lang('Invalid ID.')));
                }
                // Same visibility rule as opening the page: a folder whose
                // pages this operator may not even know about does not leak
                // its notes either.
                $nf_row = db_item("SELECT page_id, page_folder FROM page WHERE page_id = '" . e($nf_page) . "' LIMIT 1");
                if (!is_array($nf_row) || pg_designer_page_access($nf_row, $user) === 'hidden') {
                    respond(array('status' => 'error', 'message' => lang('Permission denied.')));
                }
                respond(array(
                    'status'  => 'success',
                    'page_id' => $nf_page,
                    'notes'   => pg_collab_page_notes($nf_page),
                ));
                break;

            // One form, whole: every column the field editor can set, plus
            // the options. Asked for when the widget points at a form that is
            // not this page's own — the boot payload only carries the pages
            // that are open as tabs.
            case 'form_fields':
                $cf_pid = isset($request['page_id']) ? (int)$request['page_id'] : 0;
                if ($cf_pid <= 0 || !function_exists('pg_cf_load_page_fields')) {
                    respond(array('status' => 'error', 'message' => lang('Invalid ID.')));
                }
                respond(array(
                    'status'   => 'success',
                    'fields'   => pg_cf_load_page_fields($cf_pid),
                    'settings' => pg_cf_load_page_form_settings($cf_pid),
                ));
                break;

            // Adopt an orphaned form: its own page is gone, so the page that
            // renders it becomes its page. Refused for a form whose page is
            // alive — see pg_cf_form_is_orphaned().
            case 'form_adopt':
                $cf_from = isset($request['from_page_id']) ? (int)$request['from_page_id'] : 0;
                $cf_to   = isset($request['to_page_id'])   ? (int)$request['to_page_id']   : 0;
                if (!function_exists('pg_cf_adopt_form') || !pg_cf_adopt_form($cf_from, $cf_to, $user)) {
                    respond(array('status' => 'error', 'message' => lang('Sorry, we could not accept your request.')));
                }
                respond(array(
                    'status'   => 'success',
                    'fields'   => pg_cf_load_page_fields($cf_to),
                    'settings' => pg_cf_load_page_form_settings($cf_to),
                ));
                break;

            // Pages the "Select Page" picker may offer: every visual-designer
            // page not already on this design, with the design it belongs to
            // now so the picker can say what attaching it will change.
            case 'selectable_pages':
                $style_id = isset($request['style_id']) ? (int)$request['style_id'] : 0;
                respond(array(
                    'status' => 'success',
                    'pages'  => pg_designer_selectable_pages($style_id),
                ));
                break;

            // One page, shaped exactly like the entries the screen embeds at
            // load time, so a picked page opens as a tab with no special
            // casing. Its page_style is left alone here — the page only moves
            // to the design when the operator saves.
            case 'page_load':
                $page_id = isset($request['page_id']) ? (int)$request['page_id'] : 0;
                if ($page_id <= 0) {
                    respond(array('status' => 'error', 'message' => lang('Page not found.')));
                }
                $owner = (int)db_value(
                    "SELECT page_style FROM page
                     WHERE page_id = '$page_id' AND layout_type = 'system' LIMIT 1");
                if ($owner <= 0) {
                    respond(array('status' => 'error', 'message' => lang('Page not found.')));
                }
                $found = null;
                foreach (pg_designer_load_pages($owner) as $p) {
                    if ((int)$p['page_id'] === $page_id) { $found = $p; break; }
                }
                if ($found === null) {
                    respond(array('status' => 'error', 'message' => lang('Page not found.')));
                }
                $tree = null;
                if ($found['tree_json'] !== '') {
                    $tree = json_decode($found['tree_json']);
                }
                unset($found['tree_json']);
                $found['tree'] = $tree;
                $found['key']  = 'p' . $page_id;
                // The page's form-level settings come with it; its fields are
                // the controls in its widget tree.
                $found['formSettings'] = function_exists('pg_cf_load_page_form_settings')
                                             ? pg_cf_load_page_form_settings($page_id) : array();
                respond(array('status' => 'success', 'page' => $found));
                break;

            // Take a page OUT of its design. The page is not deleted — it
            // becomes a design of its own, carrying a copy of the shared
            // assets and theme so it keeps rendering exactly as before. This
            // is the reversible move: "Select Page" brings it back. Deleting a
            // page is the pages list's job.
            //
            // Refused for the design's last page: a design with no pages is a
            // style row nothing renders, and the operator almost certainly
            // meant "delete the design" — which the toolbar offers once the
            // pages are gone.
            case 'page_detach':
                $page_id  = isset($request['page_id']) ? (int)$request['page_id'] : 0;
                $style_id = isset($request['style_id']) ? (int)$request['style_id'] : 0;
                if ($page_id <= 0 || $style_id <= 0) {
                    respond(array('status' => 'error', 'message' => lang('Page not found.')));
                }
                $page = db_item(
                    "SELECT page_id, page_name, page_style FROM page
                     WHERE page_id = '$page_id' AND page_style = '$style_id' AND layout_type = 'system' LIMIT 1");
                if (!$page) {
                    respond(array('status' => 'error', 'message' => lang('Page not found.')));
                }
                $siblings = (int)db_value(
                    "SELECT COUNT(*) FROM page WHERE page_style = '$style_id' AND layout_type = 'system'" . pg_designer_not_binned_sql('page_folder'));
                if ($siblings <= 1) {
                    respond(array('status' => 'error', 'message' => lang('The last page cannot be detached from its design. Delete the design instead.')));
                }
                $src = db_item("SELECT * FROM style WHERE style_id = '$style_id' LIMIT 1");
                if (!$src) {
                    respond(array('status' => 'error', 'message' => lang('The style could not be found.')));
                }

                $new_name = get_unique_name(array('name' => (string)$page['page_name'], 'type' => 'style'));
                $new_style_id = (int)save_system_style(array(
                    'style_id'                          => 0,
                    'name'                              => $new_name,
                    'theme_id'                          => $src['theme_id'],
                    'additional_body_classes'           => $src['additional_body_classes'],
                    'collection'                        => $src['collection'],
                    'social_networking_position'        => $src['social_networking_position'],
                    'style_head'                        => $src['style_head'],
                    'style_empty_cell_width_percentage' => $src['style_empty_cell_width_percentage'],
                    'user_id'                           => $user['id'],
                    'style_custom_css'                  => isset($src['style_custom_css'])   ? $src['style_custom_css']   : '',
                    'style_custom_js'                   => isset($src['style_custom_js'])    ? $src['style_custom_js']    : '',
                    'style_custom_fonts'                => isset($src['style_custom_fonts']) ? $src['style_custom_fonts'] : '',
                    // The page was drawn on the design's framework, in its
                    // look and colours.
                    'framework'                         => isset($src['style_framework']) ? $src['style_framework'] : '',
                    'look'                              => isset($src['style_look']) ? $src['style_look'] : '',
                    'palette'                           => isset($src['style_palette']) ? $src['style_palette'] : '',
                ));
                if ($new_style_id <= 0) {
                    respond(array('status' => 'error', 'message' => lang('The style could not be created.')));
                }
                db("UPDATE page SET page_style = '$new_style_id', page_timestamp = UNIX_TIMESTAMP(), page_user = '" . (int)$user['id'] . "'
                    WHERE page_id = '$page_id'");
                // Un-migrated database: the page has no tree column, so the new
                // style must carry the layout itself.
                if (!pg_multi_page_design_ready()) {
                    db("UPDATE style dst, style s
                        SET dst.style_tree_json = s.style_tree_json, dst.style_code = s.style_code
                        WHERE dst.style_id = '$new_style_id' AND s.style_id = '$style_id'");
                }
                log_activity(lang(array('string' => 'page ({var:1}) was detached into its own style ({var:2})', 'vars' => array($page['page_name'], $new_name))), $_SESSION['sessionusername']);
                respond(array(
                    'status'       => 'success',
                    'new_style_id' => $new_style_id,
                    'new_style_name' => $new_name,
                    'edit_url'     => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_system_style.php?id=' . $new_style_id,
                ));
                break;

            // Send a page to the recycle bin. This IS the file explorer's
            // delete, called with a single page, so the rules are the same
            // ones the operator meets there: folder edit access, the
            // delete-pages right for a basic user, name parked while the page
            // waits in the bin. The page keeps its page_style, so restoring it
            // from the bin puts it back on this design's tab strip.
            case 'page_delete':
                $page_id  = isset($request['page_id']) ? (int)$request['page_id'] : 0;
                $style_id = isset($request['style_id']) ? (int)$request['style_id'] : 0;
                if ($page_id <= 0 || $style_id <= 0) {
                    respond(array('status' => 'error', 'message' => lang('Page not found.')));
                }
                $page = db_item(
                    "SELECT page_id, page_name, page_folder FROM page
                     WHERE page_id = '$page_id' AND page_style = '$style_id' AND layout_type = 'system' LIMIT 1");
                if (!$page) {
                    respond(array('status' => 'error', 'message' => lang('Page not found.')));
                }
                if (($user['role'] == 3) && !$user['delete_pages']) {
                    respond(array('status' => 'error', 'message' => lang('You do not have access to delete pages.')));
                }
                // The last page may go too: the design then has no pages,
                // the editor returns to its home screen, and the design is
                // listed there as empty (open it to add a page, or delete
                // it). Keeping the style row is what lets the binned page
                // come back with its assets.
                require_once(dirname(__FILE__) . '/view_folder_and_files_f.php');
                if (pg_recycle_ready() == false) {
                    respond(array('status' => 'error', 'message' => lang('The recycle bin is not available. Delete the page from the pages list instead.')));
                }
                $folders_that_user_has_access_to = ($user['role'] == 3) ? get_folders_that_user_has_access_to($user['id']) : array();
                // Replies on its own: {status, binned, errors, message}.
                pg_explorer_handle(
                    array('type' => 'explorer_recycle_delete', 'items' => array(array('kind' => 'page', 'id' => $page_id))),
                    $user,
                    $folders_that_user_has_access_to);
                break;

            // Pasted HTML, as a whole page. Same parser and the same reply
            // shape as designer_import.php, which cannot serve this: it
            // reads multipart because it carries a file, and there is no
            // file here — the markup arrives as text in the JSON body.
            case 'import_paste':
                include_once(dirname(__FILE__) . '/includes/designer_import.php');
                $paste_html = isset($request['html']) ? (string)$request['html'] : '';
                if (strlen($paste_html) > 4000000) {
                    respond(array('status' => 'error', 'message' => lang('The pasted content is too large.')));
                }
                if (trim($paste_html) === '') {
                    respond(array('status' => 'error', 'message' => lang('Paste some HTML first.')));
                }
                $paste_name = isset($request['page_name']) ? trim((string)$request['page_name']) : '';
                if ($paste_name === '') $paste_name = 'page';
                $paste_name = mb_substr(preg_replace('/[\/\\:*?"<>|]+/', '-', $paste_name), 0, 60);
                $paste_skip = array();
                if (!empty($request['skip_names']) && is_array($request['skip_names'])) {
                    foreach ($request['skip_names'] as $sn) {
                        if (is_string($sn) && $sn !== '') $paste_skip[] = $sn;
                    }
                }
                // A design without a framework keeps the markup's own
                // Bootstrap / jQuery links (pg_designer_import_zip()).
                $paste_res = pg_designer_import_single_html(
                    $paste_html, $paste_name . '.html', $user, array('skip_names' => $paste_skip,
                        'keep_framework' => isset($request['framework_bootstrap']) && (string)$request['framework_bootstrap'] === '0'));
                if (!empty($paste_res['errors'])) {
                    respond(array('status' => 'error', 'message' => implode(' ', $paste_res['errors'])));
                }
                log_activity(lang(array('string' => 'HTML import ({var:1}): {var:2} page(s)',
                    'vars' => array($paste_name, count($paste_res['pages'])))), $_SESSION['sessionusername']);
                $paste_res['status'] = 'success';
                respond($paste_res);
                break;

            // Pasted HTML, as a fragment dropped into the page being edited.
            // Writes nothing and touches no design asset — see
            // pg_designer_import_fragment() for why that is not an omission.
            case 'import_fragment':
                include_once(dirname(__FILE__) . '/includes/designer_import.php');
                $frag_html = isset($request['html']) ? (string)$request['html'] : '';
                if (strlen($frag_html) > 2000000) {
                    respond(array('status' => 'error', 'message' => lang('The pasted content is too large.')));
                }
                $frag = pg_designer_import_fragment($frag_html, $user);
                respond(array(
                    'status'   => 'success',
                    'children' => $frag['children'],
                    'warnings' => $frag['warnings'],
                ));
                break;

            // Live SEO check for the page open in the editor — the same two
            // halves the site-wide score is made of, run on what is on the
            // canvas RIGHT NOW rather than on the saved row:
            //   meta      pg_seo_evaluate_meta on the settings-panel fields
            //   structure pg_seo_analyze_html on the markup the editor
            //             generates (fragment mode: the <head> the renderer
            //             adds at request time is not there to judge)
            // Nothing is stored; the stored score is refreshed on save.
            case 'seo_check':
                require_once(dirname(__FILE__) . '/seo.php');
                require_once(dirname(__FILE__) . '/seo_structure.php');
                if (!pg_seo_schema_ready()) {
                    respond(array('status' => 'error', 'message' => lang('SEO scoring is not available on this installation.')));
                }
                $page_id = isset($request['page_id']) ? (int)$request['page_id'] : 0;
                $folder_id = isset($request['page_folder']) ? (int)$request['page_folder'] : 0;
                $folder = $folder_id > 0 ? db_item("SELECT folder_access_control_type, folder_archived FROM folder WHERE folder_id = '$folder_id' LIMIT 1") : null;
                $row = array(
                    'page_id'               => $page_id,
                    'page_name'             => isset($request['page_name']) ? (string)$request['page_name'] : '',
                    'page_title'            => isset($request['page_title']) ? (string)$request['page_title'] : '',
                    'page_meta_description' => isset($request['page_meta_description']) ? (string)$request['page_meta_description'] : '',
                    'page_search_keywords'  => isset($request['page_search_keywords']) ? (string)$request['page_search_keywords'] : '',
                    'page_search'           => !empty($request['page_search']) ? '1' : '0',
                    'page_type'             => 'standard',
                    'sitemap'               => (!empty($request['page_sitemap']) && empty($request['page_noindex'])) ? '1' : '0',
                    'folder_public'         => ($folder ? ($folder['folder_access_control_type'] == 'public' || $folder['folder_access_control_type'] == '') : true),
                    'folder_archived'       => ($folder ? !empty($folder['folder_archived']) : false),
                );
                $entity  = pg_seo_normalize_entity('page', $row);
                $context = pg_seo_build_context('page', $page_id > 0 ? array($page_id) : array());
                $meta    = pg_seo_evaluate_meta($entity, $context);
                $checks  = array();
                foreach ((isset($meta['analysis']['checks']) ? $meta['analysis']['checks'] : array()) as $c) {
                    $checks[] = array('code' => $c['c'], 'status' => $c['s'], 'weight' => (int)$c['w'], 'earned' => (int)$c['e'], 'label' => pg_seo_check_label($c));
                }

                $html = isset($request['html']) ? (string)$request['html'] : '';
                if (strlen($html) > 2000000) $html = substr($html, 0, 2000000);
                $findings = array();
                $structure_score = null;
                if (trim($html) !== '') {
                    $raw = pg_seo_analyze_html($html, 'fragment', array(
                        'title'         => $row['page_title'],
                        'in_sitemap'    => ($row['sitemap'] === '1'),
                        'open_graph'    => false,
                        'expect_jsonld' => false,
                    ));
                    foreach ($raw as $code => $f) {
                        $findings[] = array(
                            'code'        => $code,
                            'severity'    => $f['severity'],
                            'occurrences' => (int)$f['occurrences'],
                            'detail'      => (string)$f['detail'],
                            'label'       => pg_seo_issue_label($code, (int)$f['occurrences'], (string)$f['detail']),
                        );
                    }
                    // A fragment cannot answer the document-level checks, so
                    // pg_seo_analyze_html() skips them and the fragment scores
                    // against a shorter rule set than the stored score did. Carry
                    // the stored answers over - the same thing this endpoint
                    // already does for the link and speed groups - so the canvas
                    // and the pages list report the same number, and the ones the
                    // operator can act on from here (the title and description in
                    // Page Settings, a missing <main>) are listed rather than
                    // silently priced in.
                    if ($page_id > 0 && function_exists('pg_seo_document_only_codes') && function_exists('pg_seo_load_issues')) {
                        $carried = pg_seo_document_only_codes();
                        foreach (pg_seo_load_issues('page', $page_id) as $issue) {
                            if (!in_array($issue['code'], $carried, TRUE)) continue;
                            if (isset($raw[$issue['code']])) continue;
                            $raw[$issue['code']] = array(
                                'severity'    => $issue['severity'],
                                'occurrences' => (int) $issue['occurrences'],
                                'detail'      => (string) $issue['detail'],
                            );
                            $findings[] = array(
                                'code'        => $issue['code'],
                                'severity'    => $issue['severity'],
                                'occurrences' => (int) $issue['occurrences'],
                                'detail'      => (string) $issue['detail'],
                                'label'       => pg_seo_issue_label($issue['code'], (int) $issue['occurrences'], (string) $issue['detail']),
                                'stored'      => TRUE,
                            );
                        }
                    }
                    $structure_score = pg_seo_structure_score($raw);
                }
                // The stored score is composed from FOUR groups, not two:
                // meta, structure, links and speed. Composing only the first
                // two here gave the editor a different arithmetic from the
                // pages list - the designer read 48 on the canvas and 46 in
                // the list, with nothing on screen to explain the gap.
                //
                // Meta and structure come from what is on the canvas right
                // now; links and speed cannot - links are counted from the
                // saved markup and speed from measurements that arrive with
                // traffic - so those two are read exactly as the stored
                // score read them. The number therefore moves on save only
                // when the save itself changed the link graph.
                $link_score = null;
                if ($page_id > 0 && pg_seo_link_schema_ready()) {
                    $stored = db_value("SELECT seo_link_score FROM page WHERE page_id = '$page_id' LIMIT 1");
                    if ($stored !== null && $stored !== '') $link_score = (int)$stored;
                }
                $speed_score = null;
                if ($page_id > 0) {
                    $speed = pg_seo_evaluate_speed(
                        isset($context['speed'][$page_id]) ? $context['speed'][$page_id] : null);
                    $speed_score = $speed['score'];
                }
                respond(array(
                    'status'          => 'success',
                    'score'           => pg_seo_compose($meta['score'], $structure_score, $link_score, $speed_score),
                    'meta_score'      => $meta['score'],
                    'structure_score' => $structure_score,
                    'link_score'      => $link_score,
                    'speed_score'     => $speed_score,
                    'weights'         => pg_seo_group_weights($structure_score, $link_score, $speed_score),
                    'checks'          => $checks,
                    'findings'        => $findings,
                ));
                break;

            // Delete a design from the home screen list: its live pages go
            // to the recycle bin (each keeps its own tree, so it can be
            // restored and attached to another design with "Select Page"),
            // pages already in the bin stay there, then the style row goes.
            // A template opened in the editor (add_system_style.php?start=template):
            // its pages as unsaved tabs, with unique names, and the rows of
            // the system widgets they place. No page is written here - see
            // pg_design_template_prepare().
            // The editor's Preview: the system widgets its blob page cannot
            // draw, rendered from the editor's own trees and settings, inside
            // the page once it has been saved (pg_designer_preview_widgets()).
            // The canvas's real records: a listing widget's repeated part for
            // up to ten records, drawn after the card being designed
            // (pg_designer_widget_ghosts()).
            case 'widget_ghosts':
                respond(array(
                    'status' => 'success',
                    'rows'   => pg_designer_widget_ghosts(
                        isset($request['page_id']) ? (int)$request['page_id'] : 0,
                        (isset($request['widget']) && is_array($request['widget'])) ? $request['widget'] : array(),
                        isset($request['limit']) ? (int)$request['limit'] : 10
                    ),
                ));
                break;

            case 'preview_widgets':
                respond(array(
                    'status' => 'success',
                    'html'   => pg_designer_preview_widgets(
                        isset($request['page_id']) ? (int)$request['page_id'] : 0,
                        (isset($request['widgets']) && is_array($request['widgets'])) ? $request['widgets'] : array()
                    ),
                ));
                break;

            case 'template_prepare':
                $tp = pg_design_template_prepare(isset($request['template']) ? (string)$request['template'] : '', $user);
                if (empty($tp['ok'])) {
                    respond(array('status' => 'error', 'message' => isset($tp['error']) ? $tp['error'] : lang('An error occurred')));
                }
                unset($tp['ok']);
                $tp['status'] = 'success';
                respond($tp);
                break;

            // Design settings › "Turn this design into a template": the
            // design's saved pages, with everything they place, become a
            // template under Choose a Template
            // (pg_design_template_from_style()). The card is read from the
            // new row itself: the template list may already be cached in
            // this request.
            case 'template_from_design':
                $tf = pg_design_template_from_style(
                    isset($request['style_id']) ? (int)$request['style_id'] : 0,
                    array(
                        'name'        => isset($request['name']) && is_scalar($request['name']) ? (string)$request['name'] : '',
                        'description' => isset($request['description']) && is_scalar($request['description']) ? (string)$request['description'] : '',
                    ),
                    $user
                );
                if (empty($tf['ok'])) {
                    respond(array('status' => 'error', 'message' => isset($tf['error']) ? $tf['error'] : lang('An error occurred')));
                }
                $tf_tpl = pg_design_template_custom_get($tf['template_id']);
                respond(array(
                    'status'   => 'success',
                    'template' => $tf_tpl ? pg_design_template_summary($tf_tpl) : null,
                    'message'  => lang(array('string' => 'The template "{var:1}" is ready. You will find it under Choose a Template.', 'vars' => array((string)$tf['template']['name']))),
                ));
                break;

            // A template made from a design, deleted from the template
            // dialog. The shipped ones cannot be (pg_design_template_custom_delete()).
            case 'template_delete':
                $tdel = pg_design_template_custom_delete(isset($request['template']) && is_scalar($request['template']) ? (string)$request['template'] : '', $user);
                if (empty($tdel['ok'])) {
                    respond(array('status' => 'error', 'message' => $tdel['error']));
                }
                respond(array('status' => 'success', 'message' => lang('The template was deleted.')));
                break;

            // Leaving the editor without publishing a template's tabs: the
            // widgets and shared components it made for them go, unless a
            // page places them (pg_design_template_discard()). Sent as a
            // beacon from `pagehide`, so nobody reads the answer.
            case 'template_discard':
                $td_deleted = pg_design_template_discard(isset($request['ids']) && is_array($request['ids']) ? $request['ids'] : array());
                respond(array('status' => 'success', 'deleted' => $td_deleted));
                break;

            // Every page of a design off the site, or its drafts back on it,
            // from the designs list (pg_designer_design_set_draft()). The
            // answer carries the row's new Status cell.
            case 'design_publish':
                $dp_style_id = isset($request['style_id']) ? (int)$request['style_id'] : 0;
                $dp_off      = !empty($request['draft']);
                $dp = pg_designer_design_set_draft($dp_style_id, $dp_off, $user);
                if (!$dp['ok']) {
                    respond(array('status' => 'error', 'message' => $dp['error']));
                }
                if ($dp['changed'] === 0) {
                    $dp_message = $dp_off ? lang('Nothing to take off the site.') : lang('Nothing to publish.');
                } elseif ($dp_off) {
                    $dp_message = ($dp['changed'] > 1) ? str_replace('{var}', $dp['changed'], lang('{var} pages are kept as drafts, off the site.')) : lang('The page is kept as a draft, off the site.');
                } else {
                    $dp_message = ($dp['changed'] > 1) ? str_replace('{var}', $dp['changed'], lang('{var} pages were published.')) : lang('The page was published.');
                }
                if ($dp['home'] > 0) $dp_message .= ' ' . lang('The home page stays on the site.');
                require_once(dirname(__FILE__) . '/includes/designer_screen.php');
                $dp_counts = pg_designer_design_draft_counts(array($dp_style_id));
                $dp_row = array(
                    'style_id'    => $dp_style_id,
                    'style_name'  => (string)db_value("SELECT style_name FROM style WHERE style_id = '$dp_style_id' LIMIT 1"),
                    'page_count'  => $dp_counts[$dp_style_id]['pages'],
                    'draft_count' => $dp_counts[$dp_style_id]['drafts'],
                    'home_count'  => $dp_counts[$dp_style_id]['home'],
                );
                respond(array(
                    'status'  => 'success',
                    'message' => $dp_message,
                    'changed' => $dp['changed'],
                    'drafts'  => $dp_row['draft_count'],
                    'cell'    => pg_designer_list_status_cell($dp_row, true),
                ));
                break;

            case 'design_delete':
                $style_id = isset($request['style_id']) ? (int)$request['style_id'] : 0;
                $style = $style_id > 0 ? db_item("SELECT style_id, style_name, style_layout FROM style WHERE style_id = '$style_id' LIMIT 1") : null;
                if (!$style) {
                    respond(array('status' => 'error', 'message' => lang('The style could not be found.')));
                }
                if ($style['style_layout'] !== 'visual_designer') {
                    respond(array('status' => 'error', 'message' => lang('Only designs made with the visual editor can be deleted here.')));
                }
                if (($user['role'] == 3) && !$user['delete_pages']) {
                    respond(array('status' => 'error', 'message' => lang('You do not have access to delete pages.')));
                }
                $folders_using = (int)db_value("SELECT COUNT(folder_id) FROM folder WHERE folder_style = '$style_id' OR mobile_style_id = '$style_id'");
                if ($folders_using > 0) {
                    respond(array('status' => 'error', 'message' => lang('You may not delete this page style because it is being used by at least one folder or page.')));
                }
                require_once(dirname(__FILE__) . '/view_folder_and_files_f.php');
                $binned = 0;
                if (pg_recycle_ready()) {
                    $bin_id = (int)pg_recycle_folder_id(true);
                    $live = db_items("SELECT page_id, page_folder FROM page WHERE page_style = '$style_id'" . pg_designer_not_binned_sql('page_folder'));
                    foreach ((is_array($live) ? $live : array()) as $lp) {
                        $pid = (int)$lp['page_id'];
                        db("UPDATE page SET page_folder = '$bin_id', page_timestamp = UNIX_TIMESTAMP(), page_user = '" . (int)$user['id'] . "' WHERE page_id = '$pid'");
                        pg_recycle_park_name('page', $pid, $user);
                        db("DELETE FROM recycle_bin WHERE item_type = 'page' AND item_id = '$pid'");
                        db("INSERT INTO recycle_bin (item_type, item_id, original_parent_id, deleted_at, deleted_by)
                            VALUES ('page', '$pid', '" . (int)$lp['page_folder'] . "', UNIX_TIMESTAMP(), '" . (int)$user['id'] . "')");
                        $binned++;
                    }
                } else {
                    $live_count = (int)db_value("SELECT COUNT(page_id) FROM page WHERE page_style = '$style_id'");
                    if ($live_count > 0) {
                        respond(array('status' => 'error', 'message' => lang('The recycle bin is not available. Delete the page from the pages list instead.')));
                    }
                }
                db("DELETE FROM style WHERE style_id = '$style_id'");
                db("DELETE FROM system_style_cells WHERE style_id = '$style_id'");
                db("DELETE FROM preview_styles WHERE style_id = '$style_id'");
                log_activity(lang(array('string' => 'style ({var:1}) was deleted', 'vars' => array($style['style_name']))), $_SESSION['sessionusername']);
                respond(array('status' => 'success', 'binned' => $binned,
                    'message' => $binned > 0
                        ? lang(array('string' => 'The design was deleted; {var:1} page(s) were moved to the Recycle Bin.', 'vars' => $binned))
                        : lang('The style has been deleted.')));
                break;

            // Save a stylesheet as a theme: a design CSS file in the file
            // manager (design = 1 is what get_theme_options() lists), written
            // from the Themes panel's light/dark variable blocks. Nothing is
            // changed on the design itself — the editor selects the new
            // file in the theme list and the operator saves as usual.
            case 'theme_save':
                $name = isset($request['name']) ? trim((string)$request['name']) : '';
                $css  = isset($request['css'])  ? (string)$request['css'] : '';
                if ($name === '' || trim($css) === '') {
                    respond(array('status' => 'error', 'message' => lang('A theme name and some CSS are required.')));
                }
                if (strlen($css) > 512000) {
                    respond(array('status' => 'error', 'message' => lang('The stylesheet is too large.')));
                }
                if (!preg_match('/\.css$/i', $name)) $name .= '.css';
                $file_name = prepare_file_name($name);
                if ($file_name === '' || $file_name === '.css') {
                    respond(array('status' => 'error', 'message' => lang('Please enter a valid theme name.')));
                }
                if (check_name_availability(array('name' => $file_name)) == false) {
                    respond(array('status' => 'error', 'message' => lang(array('string' => 'The Theme ({var:1}) already exists.', 'vars' => $file_name))));
                }
                if (@file_put_contents(FILE_DIRECTORY_PATH . '/' . $file_name, $css) === false) {
                    respond(array('status' => 'error', 'message' => lang(array('string' => '{var:1} could not be written to disk.', 'vars' => $file_name))));
                }
                $folder_id = (int)db_value("SELECT folder_id FROM folder WHERE folder_parent = '0' ORDER BY folder_id LIMIT 1");
                db("INSERT INTO files (name, folder, description, type, size, user, design, theme, timestamp)
                    VALUES ('" . e($file_name) . "', '$folder_id', '', 'css', '" . (int)strlen($css) . "', '" . (int)$user['id'] . "', '1', '0', UNIX_TIMESTAMP())");
                $theme_id = (int)mysqli_insert_id(db::$con);
                log_activity(lang(array('string' => 'theme ({var:1}) was created', 'vars' => $file_name)), $_SESSION['sessionusername']);
                respond(array('status' => 'success', 'id' => $theme_id, 'name' => $file_name));
                break;

            // The look builder and the palette builder of Settings > Design >
            // Theme (includes/fn/design_themes.php). They send choices - a
            // base look and a few picks, or two colours - never CSS; the
            // stylesheet is written here. *_css answers with the stylesheet
            // for the canvas to try on; *_save writes it to the file manager
            // as a design CSS file every design can wear.
            case 'theme_look_css':
            case 'theme_look_save':
                $base     = isset($request['base']) ? (string)$request['base'] : '';
                $controls = (isset($request['controls']) && is_array($request['controls'])) ? $request['controls'] : array();
                $name     = isset($request['name']) ? trim((string)$request['name']) : '';
                $looks    = pg_design_looks();
                if (!isset($looks[$base])) {
                    respond(array('status' => 'error', 'message' => lang('Choose the look to start from.')));
                }
                $css = pg_design_custom_look_css($base, $controls, $name !== '' ? $name : $looks[$base]['name']);
                if ($css === false) {
                    respond(array('status' => 'error', 'message' => lang('The look could not be made.')));
                }
                if ($sub === 'theme_look_css') {
                    respond(array('status' => 'success', 'css' => $css));
                }
                $saved = pg_design_theme_save_file('look', $name, $css, $user['id']);
                if (!is_array($saved)) {
                    respond(array('status' => 'error', 'message' => $saved));
                }
                log_activity(lang(array('string' => 'theme ({var:1}) was created', 'vars' => $saved['file'])), $_SESSION['sessionusername']);
                respond(array('status' => 'success', 'key' => 'file-' . $saved['id'], 'name' => $saved['name'], 'url' => $saved['url'],
                    'base' => $base, 'controls' => $controls));
                break;

            case 'theme_palette_css':
            case 'theme_palette_save':
                $primary   = isset($request['primary'])   ? trim((string)$request['primary'])   : '';
                $secondary = isset($request['secondary']) ? trim((string)$request['secondary']) : '';
                $name      = isset($request['name']) ? trim((string)$request['name']) : '';
                $css = pg_design_palette_css($primary, $secondary, $name);
                if ($css === false) {
                    respond(array('status' => 'error', 'message' => lang('Enter both colours as #RRGGBB.')));
                }
                if ($sub === 'theme_palette_css') {
                    respond(array('status' => 'success', 'css' => $css));
                }
                $p = _pg_theme_hex(_pg_theme_rgb($primary));
                $s = _pg_theme_hex(_pg_theme_rgb($secondary));
                $css = pg_design_theme_header(array('kind' => 'palette', 'name' => $name, 'primary' => $p, 'secondary' => $s)) . "\n" . $css;
                $saved = pg_design_theme_save_file('palette', $name, $css, $user['id']);
                if (!is_array($saved)) {
                    respond(array('status' => 'error', 'message' => $saved));
                }
                log_activity(lang(array('string' => 'theme ({var:1}) was created', 'vars' => $saved['file'])), $_SESSION['sessionusername']);
                respond(array('status' => 'success', 'key' => 'file-' . $saved['id'], 'name' => $saved['name'], 'url' => $saved['url'],
                    'primary' => $p, 'secondary' => $s));
                break;

            default:
                respond(array('status' => 'error', 'message' => lang('Unknown action.')));
        }
        break;

    case 'shared_component':
        validate_token();
        $user = validate_user();

        // Shared components and system widgets belong to the design: their
        // tree is used by every page that references them, so an edit here
        // is never an edit to "this page". WRITING is administrator (0) and
        // designer (1) only.
        //
        // The check read `role != 0 && role != 2`, written against the old
        // role table where 2 was thought to be the designer. It let MANAGERS
        // rewrite shared components and refused DESIGNERS — the two roles it
        // exists to tell apart.
        //
        // READING stays open to anyone who may open the editor. A page that
        // carries a widget cannot be drawn without the widget's tree: closing
        // the read left a content-level operator looking at a two-line page
        // where the whole layout used to be.
        $sub = isset($request['sub_action']) ? $request['sub_action'] : '';
        $_sc_read_only = array('list', 'usage_all', 'prefetch', 'get',
                               'list_custom_forms', 'list_form_item_view_pages', 'list_product_groups',
                               'list_calendars', 'list_calendar_event_pages');
        if (((int)$user['role'] > 1) && !in_array($sub, $_sc_read_only, true)) {
            respond(array('status' => 'error', 'message' => lang('Permission denied.')));
        }

        // Defensive: ensure shared_components table exists (migration may not have been run).
        // Returns a clear JSON error instead of a cryptic HTML dump / 500.
        $_sc_check = @mysqli_query(db::$con, "SHOW TABLES LIKE 'shared_components'");
        if (!$_sc_check || mysqli_num_rows($_sc_check) === 0) {
            respond(array(
                'status'  => 'error',
                'message' => 'shared_components table is missing — please run the software upgrade at install/index.php',
            ));
        }

        switch ($sub) {

            // ── LIST ─────────────────────────────────────────────────────────
            case 'list':
                // Returns BOTH regular shared components and system widgets.
                // Each row carries `is_system_widget` (1/0) so the JS can split them
                // into the "Ortak" tab vs. the "Sistem" tab.
                $rows = db_items(
                    "SELECT id, name, description, category, updated_at,
                            (system_region_config IS NOT NULL AND system_region_config != '') AS is_system_widget,
                            system_region_config
                     FROM shared_components
                     ORDER BY name ASC"
                );
                respond(array('status' => 'success', 'items' => $rows));
                break;

            // ── USAGE ALL ────────────────────────────────────────────────────
            // For every shared component, count how many styles reference it
            // (via `"sharedId":<id>` in style_tree_json) and return the list of
            // referencing style names. Used by the "Ortak" palette tab to show
            // a "N stilde" badge + pre-populate the delete confirmation.
            case 'usage_all':
                respond(array('status' => 'success', 'usage' => pg_shared_component_usage(null)));
                break;

            // ── PREFETCH ─────────────────────────────────────────────────────
            // Returns id + name + tree_json for a specific set of shared component ids.
            // Called by style_designer.js at init time to warm _sharedCache.
            case 'prefetch':
                $sc_ids_raw = isset($request['ids']) ? $request['ids'] : array();
                $sc_ids     = array();
                if (is_array($sc_ids_raw)) {
                    foreach ($sc_ids_raw as $raw_id) {
                        $id = (int)$raw_id;
                        if ($id > 0) $sc_ids[] = $id;
                    }
                }
                if (empty($sc_ids)) {
                    respond(array('status' => 'success', 'items' => array()));
                }
                $sc_ids_str = implode(',', $sc_ids);
                $rows = db_items(
                    "SELECT id, name, tree_json, system_region_config
                     FROM shared_components
                     WHERE id IN ($sc_ids_str)"
                );
                respond(array('status' => 'success', 'items' => $rows ? $rows : array()));
                break;

            // ── GET ──────────────────────────────────────────────────────────
            case 'get':
                $sc_id = isset($request['id']) ? (int)$request['id'] : 0;
                if ($sc_id <= 0) {
                    respond(array('status' => 'error', 'message' => lang('Invalid ID.')));
                }
                $row = db_items(
                    "SELECT id, name, description, category, tree_json, system_region_config, updated_at
                     FROM shared_components
                     WHERE id = '$sc_id' LIMIT 1"
                );
                if (!$row) {
                    respond(array('status' => 'error', 'message' => lang('Not found.')));
                }
                respond(array('status' => 'success', 'item' => $row[0]));
                break;

            // ── CREATE ───────────────────────────────────────────────────────
            case 'create':
                $sc_name     = isset($request['name'])               ? trim($request['name'])               : '';
                $sc_tree     = isset($request['tree_json'])           ? $request['tree_json']                : '';
                $sc_desc     = isset($request['description'])         ? trim($request['description'])        : '';
                $sc_src_cfg  = isset($request['system_region_config'])? $request['system_region_config']    : null;
                if ($sc_name === '') {
                    respond(array('status' => 'error', 'message' => lang(array('string' => '{var:1} is required', 'vars' => lang('Name')))));
                }
                if ($sc_tree === '' || json_decode($sc_tree) === null) {
                    respond(array('status' => 'error', 'message' => lang('Invalid tree data.')));
                }
                // Validate system_region_config when provided
                if ($sc_src_cfg !== null && ($sc_src_cfg === '' || json_decode($sc_src_cfg) === null)) {
                    respond(array('status' => 'error', 'message' => lang('Invalid system region config.')));
                }
                // Auto-suffix if name taken: "Hero [1]", "Hero [2]", ...
                $sc_name = _sc_unique_name($sc_name);
                $sc_now  = time();
                $sc_cfg_sql = ($sc_src_cfg !== null) ? "'" . e($sc_src_cfg) . "'" : 'NULL';
                // Only the import's marker is taken ("import:<project>"): it
                // lets a design that is never published discard the rows its
                // import made (pg_design_template_discard()).
                $sc_cat = isset($request['category']) && is_string($request['category']) ? trim($request['category']) : '';
                if (strpos($sc_cat, 'import:') !== 0) $sc_cat = '';
                $sc_cat = mb_substr($sc_cat, 0, 100);
                db("INSERT INTO shared_components (name, description, tree_json, system_region_config, category, created_by, created_at, updated_at)
                    VALUES ('" . e($sc_name) . "', '" . e($sc_desc) . "', '" . e($sc_tree) . "',
                            $sc_cfg_sql, '" . e($sc_cat) . "', '" . (int)$user['id'] . "', '$sc_now', '$sc_now')");
                $sc_new_id = mysqli_insert_id(db::$con);
                respond(array('status' => 'success', 'id' => $sc_new_id, 'name' => $sc_name));
                break;

            // ── UPDATE (tree_json) ────────────────────────────────────────────
            case 'update':
                $sc_id      = isset($request['id'])                   ? (int)$request['id']               : 0;
                $sc_tree    = isset($request['tree_json'])             ? $request['tree_json']              : '';
                $sc_src_cfg = isset($request['system_region_config'])  ? $request['system_region_config']  : false;
                if ($sc_id <= 0) {
                    respond(array('status' => 'error', 'message' => lang('Invalid ID.')));
                }
                if ($sc_tree === '' || json_decode($sc_tree) === null) {
                    respond(array('status' => 'error', 'message' => lang('Invalid tree data.')));
                }
                // The tree may place other shared components, never this one —
                // directly or through them: that loop could not be drawn.
                $sc_loop = pg_shared_component_cycle(array($sc_id), array($sc_id => json_decode($sc_tree, true)));
                if (!empty($sc_loop)) {
                    respond(array('status' => 'error', 'message' => pg_shared_component_loop_message($sc_loop)));
                }
                // Validate system_region_config when provided
                if ($sc_src_cfg !== false && $sc_src_cfg !== null && ($sc_src_cfg === '' || json_decode($sc_src_cfg) === null)) {
                    respond(array('status' => 'error', 'message' => lang('Invalid system region config.')));
                }
                $sc_cfg_clause = '';
                if ($sc_src_cfg !== false) {
                    $sc_cfg_clause = ($sc_src_cfg === null)
                        ? ', system_region_config = NULL'
                        : ", system_region_config = '" . e($sc_src_cfg) . "'";
                }
                db("UPDATE shared_components
                    SET tree_json = '" . e($sc_tree) . "'" . $sc_cfg_clause . ", updated_at = " . time() . "
                    WHERE id = '$sc_id' LIMIT 1");
                respond(array('status' => 'success'));
                break;

            // ── UPDATE CONFIG (system_region_config only) ─────────────────────
            // Called by Visual Pinegrap Editor when compiled templates change but the
            // visual tree has not been edited (avoids a full tree re-save round-trip).
            case 'update_config':
                $sc_id      = isset($request['id'])                   ? (int)$request['id']               : 0;
                $sc_src_cfg = isset($request['system_region_config'])  ? $request['system_region_config']  : null;
                if ($sc_id <= 0) {
                    respond(array('status' => 'error', 'message' => lang('Invalid ID.')));
                }
                if ($sc_src_cfg !== null && ($sc_src_cfg === '' || json_decode($sc_src_cfg) === null)) {
                    respond(array('status' => 'error', 'message' => lang('Invalid system region config.')));
                }
                $sc_cfg_val = ($sc_src_cfg === null) ? 'NULL' : "'" . e($sc_src_cfg) . "'";
                db("UPDATE shared_components
                    SET system_region_config = $sc_cfg_val, updated_at = " . time() . "
                    WHERE id = '$sc_id' LIMIT 1");
                respond(array('status' => 'success'));
                break;

            // ── RENAME ───────────────────────────────────────────────────────
            case 'rename':
                $sc_id   = isset($request['id'])   ? (int)$request['id']   : 0;
                $sc_name = isset($request['name'])  ? trim($request['name']) : '';
                if ($sc_id <= 0) {
                    respond(array('status' => 'error', 'message' => lang('Invalid ID.')));
                }
                if ($sc_name === '') {
                    respond(array('status' => 'error', 'message' => lang(array('string' => '{var:1} is required', 'vars' => lang('Name')))));
                }
                // Auto-suffix, but skip our own current name to allow no-op rename
                $sc_current = db_value("SELECT name FROM shared_components WHERE id = '$sc_id' LIMIT 1");
                if ($sc_current !== $sc_name) {
                    $sc_name = _sc_unique_name($sc_name);
                }
                db("UPDATE shared_components
                    SET name = '" . e($sc_name) . "', updated_at = " . time() . "
                    WHERE id = '$sc_id' LIMIT 1");
                respond(array('status' => 'success', 'name' => $sc_name));
                break;

            // ── DELETE ───────────────────────────────────────────────────────
            // Phase 1: no dependency check; Phase 2 will add usage guard + force flag.
            case 'delete':
                $sc_id = isset($request['id']) ? (int)$request['id'] : 0;
                if ($sc_id <= 0) {
                    respond(array('status' => 'error', 'message' => lang('Invalid ID.')));
                }
                db("DELETE FROM shared_components WHERE id = '$sc_id' LIMIT 1");
                respond(array('status' => 'success'));
                break;

            // ── DELETE MANY ──────────────────────────────────────────────────
            // The palette's bulk delete. A row a saved page still places is
            // kept unless the request says `force` — the editor asks for that
            // only after the operator typed the confirmation — so a page saved
            // from another tab since the palette loaded is never broken by
            // accident. Answers with what went and what stayed.
            case 'delete_many':
                $sc_ids = array();
                foreach ((array)(isset($request['ids']) ? $request['ids'] : array()) as $sc_raw) {
                    $sc_one = (int)$sc_raw;
                    if ($sc_one > 0) $sc_ids[$sc_one] = $sc_one;
                }
                if (empty($sc_ids)) {
                    respond(array('status' => 'error', 'message' => lang('Nothing is selected.')));
                }
                if (count($sc_ids) > 500) {
                    respond(array('status' => 'error', 'message' => lang('Select at most 500 at a time.')));
                }
                $sc_force = !empty($request['force']);
                $sc_usage = pg_shared_component_usage(array_values($sc_ids));
                $sc_names = array();
                $sc_rows  = db_items("SELECT id, name FROM shared_components WHERE id IN (" . implode(',', $sc_ids) . ")");
                foreach ((is_array($sc_rows) ? $sc_rows : array()) as $sc_row) $sc_names[(int)$sc_row['id']] = (string)$sc_row['name'];
                $sc_deleted = array();
                $sc_kept    = array();
                foreach ($sc_ids as $sc_one) {
                    if (!isset($sc_names[$sc_one])) continue;   // already gone
                    if (!$sc_force && !empty($sc_usage[$sc_one])) {
                        $sc_kept[] = array('id' => $sc_one, 'name' => $sc_names[$sc_one], 'usage' => $sc_usage[$sc_one]);
                        continue;
                    }
                    $sc_deleted[] = $sc_one;
                }
                if (!empty($sc_deleted)) {
                    db("DELETE FROM shared_components WHERE id IN (" . implode(',', $sc_deleted) . ")");
                    $sc_list = array();
                    foreach ($sc_deleted as $sc_one) $sc_list[] = $sc_names[$sc_one];
                    log_activity(lang(array(
                        'string' => '{var:1} shared components and system widgets were deleted: {var:2}',
                        'vars'   => array(count($sc_deleted), implode(', ', $sc_list)),
                    )), isset($_SESSION['sessionusername']) ? $_SESSION['sessionusername'] : '');
                }
                respond(array('status' => 'success', 'deleted' => $sc_deleted, 'kept' => $sc_kept));
                break;

            // ── LIST CUSTOM FORMS ───────────────────────────────────────────
            // Returns every page with page_type='custom form'. Used by the system widget
            // props panel to populate the "which form's submissions to show?" dropdown.
            // JOINs custom_form_pages for form_name (the user-friendly label) — the
            // canonical FK is page.page_id, and custom_form_pages.page_id maps 1:1.
            case 'list_custom_forms':
                $sc_form_rows = db_items(
                    "SELECT
                        page.page_id,
                        page.page_name,
                        page.page_title,
                        custom_form_pages.form_name
                     FROM page
                     LEFT JOIN custom_form_pages ON custom_form_pages.page_id = page.page_id
                     WHERE " . pg_form_page_sql('page') . "
                     ORDER BY custom_form_pages.form_name ASC, page.page_name ASC"
                );
                respond(array('status' => 'success', 'forms' => $sc_form_rows));
                break;

            // ── LIST FORM ITEM VIEW PAGES ───────────────────────────────────
            // Returns pages with page_type='form item view'. Used by the
            // system-widget props panel to populate the "detail page" dropdown
            // (each list row's ^^__detail_url^^ token will link to one).
            //
            // When `form_id` is supplied, the result is filtered to ONLY the
            // form-item-view pages whose `custom_form_page_id` matches — i.e.
            // the detail pages that actually know how to render submissions of
            // *that* form. Without this filter, users could pick a detail page
            // bound to a different form, and the detail SQL
            //   WHERE forms.page_id = $custom_form_page_id AND reference_code = ?
            // would silently 404 because the page_id check excludes the row.
            //
            // Backwards compat: if `form_id` is missing or 0, return everything
            // (preserves existing callers).
            case 'list_form_item_view_pages':
                $sc_iv_form_id = isset($request['form_id']) ? (int)$request['form_id'] : 0;
                if ($sc_iv_form_id > 0) {
                    $sc_iv_rows = db_items(
                        "SELECT page.page_id, page.page_name, page.page_title
                         FROM page
                         INNER JOIN form_item_view_pages
                                 ON form_item_view_pages.page_id = page.page_id
                                AND form_item_view_pages.collection = 'a'
                                AND form_item_view_pages.custom_form_page_id = '$sc_iv_form_id'
                         WHERE page.page_type = 'form item view'
                         ORDER BY page.page_title ASC, page.page_name ASC"
                    );
                } else {
                    $sc_iv_rows = db_items(
                        "SELECT page_id, page_name, page_title
                         FROM page
                         WHERE page_type = 'form item view'
                         ORDER BY page_title ASC, page_name ASC"
                    );
                }
                // Pages built in the visual editor show a record through a
                // form_item_view widget bound to the same form.
                $sc_iv_merged = array();
                foreach ((array)$sc_iv_rows as $sc_iv_p) $sc_iv_merged[(int)$sc_iv_p['page_id']] = $sc_iv_p;
                foreach (pg_sw_widget_pages('form_item_view', function ($cfg) use ($sc_iv_form_id) {
                    return $sc_iv_form_id <= 0 || (int)(isset($cfg['custom_form_page_id']) ? $cfg['custom_form_page_id'] : 0) === $sc_iv_form_id;
                }) as $sc_iv_p) $sc_iv_merged[(int)$sc_iv_p['page_id']] = $sc_iv_p;
                respond(array('status' => 'success', 'pages' => array_values($sc_iv_merged)));
                break;

            // ── LIST CALENDARS ──────────────────────────────────────────────
            // The calendars a calendar widget can show — its settings panel
            // lists them as checkboxes.
            case 'list_calendars':
                $sc_cal_rows = db_items("SELECT id, name FROM calendars ORDER BY name ASC");
                respond(array('status' => 'success', 'calendars' => is_array($sc_cal_rows) ? $sc_cal_rows : array()));
                break;

            // ── LIST CALENDAR EVENT PAGES ───────────────────────────────────
            // Where a calendar widget's events can link: legacy Calendar Event
            // View pages and pages carrying a calendar_event_view widget.
            case 'list_calendar_event_pages':
                $sc_ce_merged = array();
                foreach ((array)db_items(
                    "SELECT page_id, page_name, page_title FROM page
                     WHERE page_type = 'calendar event view'
                     ORDER BY page_title ASC, page_name ASC") as $sc_ce_p) {
                    $sc_ce_merged[(int)$sc_ce_p['page_id']] = $sc_ce_p;
                }
                foreach (pg_sw_widget_pages('calendar_event_view') as $sc_ce_p) $sc_ce_merged[(int)$sc_ce_p['page_id']] = $sc_ce_p;
                respond(array('status' => 'success', 'pages' => array_values($sc_ce_merged)));
                break;

            // ── LIST PRODUCT GROUPS ─────────────────────────────────────────
            // Returns every product_groups row. Used by the system-widget props panel
            // to populate the "Product Group" dropdown when regionType='catalog_listing'.
            // Indented by parent_id so the user sees the hierarchy at a glance.
            case 'list_product_groups':
                $sc_pg_rows = db_items(
                    "SELECT id, name, parent_id, sort_order
                     FROM product_groups
                     ORDER BY parent_id ASC, sort_order ASC, name ASC"
                );
                respond(array('status' => 'success', 'groups' => $sc_pg_rows));
                break;

            // ── LIST CATALOG PAGES ──────────────────────────────────────────
            // Returns every page with page_type='catalog'. Used by the system
            // widget props panel when group_navigation='page' so the designer
            // can pick which catalog page each group click should redirect to.
            case 'list_catalog_pages':
                $sc_cp_rows = db_items(
                    "SELECT page_id, page_name, page_title
                     FROM page
                     WHERE page_type = 'catalog'
                     ORDER BY page_title ASC, page_name ASC"
                );
                respond(array('status' => 'success', 'pages' => $sc_cp_rows));
                break;

            // ── LIST CATALOG LISTING PAGES ──────────────────────────────────
            // Returns pages that contain a catalog_listing system widget.
            // Used by the catalog_item_view widget's "Catalog page"
            // picker — designer chooses which catalog page the breadcrumb
            // crumbs (Main Catalog → Group → …) and cross-sell card URLs
            // should link back to.
            //
            // Mirrors list_catalog_detail_pages but keys on
            // regionType='catalog_listing' instead of catalog_item_view.
            // Same two-source merge: legacy 'catalog' page_type + system
            // pages whose style_tree_json shared_ref's a catalog_listing
            // shared_component.
            case 'list_catalog_listing_pages':
                $sc_cl_legacy = db_items(
                    "SELECT page_id, page_name, page_title
                     FROM page
                     WHERE page_type = 'catalog'"
                );
                $sc_cl_civ_ids = db_items(
                    "SELECT id FROM shared_components
                     WHERE system_region_config LIKE '%\"regionType\":\"catalog_listing\"%'"
                );
                $sc_cl_sys = array();
                if (!empty($sc_cl_civ_ids)) {
                    $sc_cl_likes = array();
                    foreach ($sc_cl_civ_ids as $sc_cl_r) {
                        $sc_cl_cid = (int)$sc_cl_r['id'];
                        if ($sc_cl_cid <= 0) continue;
                        $sc_cl_likes[] = pg_page_tree_sql_expr() . " LIKE '%\"sharedId\":" . $sc_cl_cid . ",%'"
                                       . " OR " . pg_page_tree_sql_expr() . " LIKE '%\"sharedId\":" . $sc_cl_cid . "}%'";
                    }
                    if (!empty($sc_cl_likes)) {
                        $sc_cl_where = '(' . implode(' OR ', $sc_cl_likes) . ')';
                        $sc_cl_sys = db_items(
                            "SELECT page.page_id, page.page_name, page.page_title
                             FROM page
                             INNER JOIN style ON page.page_style = style.style_id
                             WHERE page.layout_type = 'system'
                               AND $sc_cl_where"
                        );
                    }
                }
                $sc_cl_merged = array();
                foreach ((array)$sc_cl_legacy as $sc_cl_p) { $sc_cl_merged[(int)$sc_cl_p['page_id']] = $sc_cl_p; }
                foreach ((array)$sc_cl_sys    as $sc_cl_p) { $sc_cl_merged[(int)$sc_cl_p['page_id']] = $sc_cl_p; }
                $sc_cl_pages = array_values($sc_cl_merged);
                usort($sc_cl_pages, function ($a, $b) {
                    $ta = trim((string)$a['page_title']);
                    $tb = trim((string)$b['page_title']);
                    if ($ta !== $tb) return strcasecmp($ta, $tb);
                    return strcasecmp((string)$a['page_name'], (string)$b['page_name']);
                });
                respond(array('status' => 'success', 'pages' => $sc_cl_pages));
                break;

            // ── LIST CATALOG DETAIL PAGES ───────────────────────────────────
            // Returns the candidate detail pages for a catalog_listing widget's
            // "Detail page" dropdown. Each product row's ^^__detail_url^^ token
            // resolves to PATH . page_name . '/' . product.address_name, so the
            // chosen page must render a single product (or a select-type group).
            //
            // Two sources merged + deduped by page_id:
            //   1) Legacy custom-style pages with page_type='catalog detail'.
            //   2) System-style pages whose linked style_tree_json contains a
            //      shared_ref to a shared_component whose system_region_config
            //      declares regionType='catalog_item_view'. The widget tree
            //      lives on shared_components, NOT on the style tree — that's
            //      why a naive `style_tree_json LIKE '%catalog_item_view%'`
            //      misses every system-style detail page.
            case 'list_catalog_detail_pages':
                $sc_cd_legacy = db_items(
                    "SELECT page_id, page_name, page_title
                     FROM page
                     WHERE page_type = 'catalog detail'"
                );

                // Step 1: ids of shared_components that ARE catalog_item_view widgets.
                $sc_cd_civ_ids = db_items(
                    "SELECT id FROM shared_components
                     WHERE system_region_config LIKE '%\"regionType\":\"catalog_item_view\"%'"
                );

                $sc_cd_sys = array();
                if (!empty($sc_cd_civ_ids)) {
                    // Step 2: pages linked to a style that shared_ref's at least
                    // one of those component ids. Match `"sharedId":<id>` followed
                    // by either `,` (more props after) or `}` (last prop) to avoid
                    // substring collisions (id=1 vs id=10).
                    $sc_cd_likes = array();
                    foreach ($sc_cd_civ_ids as $sc_cd_r) {
                        $sc_cd_cid = (int)$sc_cd_r['id'];
                        if ($sc_cd_cid <= 0) continue;
                        $sc_cd_likes[] = pg_page_tree_sql_expr() . " LIKE '%\"sharedId\":" . $sc_cd_cid . ",%'"
                                       . " OR " . pg_page_tree_sql_expr() . " LIKE '%\"sharedId\":" . $sc_cd_cid . "}%'";
                    }
                    if (!empty($sc_cd_likes)) {
                        $sc_cd_where = '(' . implode(' OR ', $sc_cd_likes) . ')';
                        $sc_cd_sys = db_items(
                            "SELECT page.page_id, page.page_name, page.page_title
                             FROM page
                             INNER JOIN style ON page.page_style = style.style_id
                             WHERE page.layout_type = 'system'
                               AND $sc_cd_where"
                        );
                    }
                }

                // Merge + dedupe by page_id; legacy entries first, system entries
                // overwrite (same page) — both sources carry identical column shape.
                $sc_cd_merged = array();
                foreach ((array)$sc_cd_legacy as $sc_cd_p) { $sc_cd_merged[(int)$sc_cd_p['page_id']] = $sc_cd_p; }
                foreach ((array)$sc_cd_sys    as $sc_cd_p) { $sc_cd_merged[(int)$sc_cd_p['page_id']] = $sc_cd_p; }
                $sc_cd_pages = array_values($sc_cd_merged);
                usort($sc_cd_pages, function ($a, $b) {
                    $ta = trim((string)$a['page_title']);
                    $tb = trim((string)$b['page_title']);
                    if ($ta !== $tb) return strcasecmp($ta, $tb);
                    return strcasecmp((string)$a['page_name'], (string)$b['page_name']);
                });
                respond(array('status' => 'success', 'pages' => $sc_cd_pages));
                break;

            // ── LIST SHOPPING CART PAGES ────────────────────────────────────
            // Used by the catalog_item_view widget's "Sonraki Sayfa" picker —
            // after add_to_cart succeeds, the visitor is redirected to a page
            // that should actually render a shopping cart, not just any page.
            //
            // Two sources merged + deduped by page_id (mirrors list_catalog_detail_pages):
            //   1) Legacy custom-style pages with page_type='shopping cart'.
            //   2) System-style pages whose linked style_tree_json contains a
            //      shared_ref to a shared_component whose system_region_config
            //      declares regionType='shopping_cart'. The widget tree lives
            //      on shared_components, NOT on the style tree — so a naive
            //      `style_tree_json LIKE '%shopping_cart%'` would miss every
            //      system-style cart page.
            case 'list_shopping_cart_pages':
                $sc_sc_legacy = db_items(
                    "SELECT page_id, page_name, page_title
                     FROM page
                     WHERE page_type = 'shopping cart'"
                );

                // Step 1: ids of shared_components that ARE shopping_cart widgets.
                $sc_sc_ids = db_items(
                    "SELECT id FROM shared_components
                     WHERE system_region_config LIKE '%\"regionType\":\"shopping_cart\"%'"
                );

                $sc_sc_sys = array();
                if (!empty($sc_sc_ids)) {
                    // Step 2: pages linked to a style that shared_ref's at least
                    // one of those component ids. Same pattern as catalog_detail
                    // — match `"sharedId":N,` or `"sharedId":N}` to avoid
                    // substring collisions (id=1 vs id=10).
                    $sc_sc_likes = array();
                    foreach ($sc_sc_ids as $sc_sc_r) {
                        $sc_sc_cid = (int)$sc_sc_r['id'];
                        if ($sc_sc_cid <= 0) continue;
                        $sc_sc_likes[] = pg_page_tree_sql_expr() . " LIKE '%\"sharedId\":" . $sc_sc_cid . ",%'"
                                       . " OR " . pg_page_tree_sql_expr() . " LIKE '%\"sharedId\":" . $sc_sc_cid . "}%'";
                    }
                    if (!empty($sc_sc_likes)) {
                        $sc_sc_where = '(' . implode(' OR ', $sc_sc_likes) . ')';
                        $sc_sc_sys = db_items(
                            "SELECT page.page_id, page.page_name, page.page_title
                             FROM page
                             INNER JOIN style ON page.page_style = style.style_id
                             WHERE page.layout_type = 'system'
                               AND $sc_sc_where"
                        );
                    }
                }

                // Merge + dedupe by page_id; legacy entries first, system entries
                // overwrite (same page) — both sources carry identical column shape.
                $sc_sc_merged = array();
                foreach ((array)$sc_sc_legacy as $sc_sc_p) { $sc_sc_merged[(int)$sc_sc_p['page_id']] = $sc_sc_p; }
                foreach ((array)$sc_sc_sys    as $sc_sc_p) { $sc_sc_merged[(int)$sc_sc_p['page_id']] = $sc_sc_p; }
                $sc_sc_pages = array_values($sc_sc_merged);
                usort($sc_sc_pages, function ($a, $b) {
                    $ta = trim((string)$a['page_title']);
                    $tb = trim((string)$b['page_title']);
                    if ($ta !== $tb) return strcasecmp($ta, $tb);
                    return strcasecmp((string)$a['page_name'], (string)$b['page_name']);
                });
                respond(array('status' => 'success', 'pages' => $sc_sc_pages));
                break;

            // ── LIST ALL PAGES ──────────────────────────────────────────────
            // Returns every page row (page_id, page_name, page_title) sorted by
            // title then name. Used by the my_account widget's login-page picker so
            // the designer can select any page as the login redirect target.
            // Response shape: { status:'success', pages:[{page_id,page_name,page_title},...] }
            case 'list_all_pages':
                // Each page also carries its page_type and the system widgets
                // its tree holds, so a picker that asks for a sign-in page
                // lists sign-in pages instead of every page on the site.
                $sc_ap_types = array();
                foreach ((array) db_items("SELECT id, system_region_config FROM shared_components WHERE system_region_config IS NOT NULL AND system_region_config <> ''") as $sc_ap_w) {
                    $sc_ap_cfg = json_decode((string) $sc_ap_w['system_region_config'], true);
                    if (is_array($sc_ap_cfg) && !empty($sc_ap_cfg['regionType'])) {
                        $sc_ap_types[(int) $sc_ap_w['id']] = (string) $sc_ap_cfg['regionType'];
                    }
                }
                $sc_ap_rows = db_items(
                    "SELECT page_id, page_name, page_title, page_type,
                            IF(page_tree_json LIKE '%sharedId%', page_tree_json, '') AS page_tree_json
                     FROM page
                     ORDER BY page_title ASC, page_name ASC"
                );
                foreach ($sc_ap_rows as &$sc_ap_row) {
                    $sc_ap_found = array();
                    if ($sc_ap_types && $sc_ap_row['page_tree_json'] !== '') {
                        preg_match_all('/"sharedId"\s*:\s*"?(\d+)/', (string) $sc_ap_row['page_tree_json'], $sc_ap_m);
                        foreach (array_unique($sc_ap_m[1]) as $sc_ap_sid) {
                            if (isset($sc_ap_types[(int) $sc_ap_sid])) $sc_ap_found[] = $sc_ap_types[(int) $sc_ap_sid];
                        }
                    }
                    $sc_ap_row['widgets'] = array_values(array_unique($sc_ap_found));
                    unset($sc_ap_row['page_tree_json']);
                }
                unset($sc_ap_row);
                respond(array('status' => 'success', 'pages' => $sc_ap_rows));
                break;

            // ── LIST FORM FIELDS ────────────────────────────────────────────
            // Returns the form_fields rows for a given custom form page_id. Used by the
            // data binding section to populate the per-prop field dropdowns with the
            // ACTUAL fields of the selected form (instead of a generic hardcoded list).
            case 'list_form_fields':
                $sc_ff_pid = isset($request['page_id']) ? (int)$request['page_id'] : 0;
                if ($sc_ff_pid <= 0) {
                    respond(array('status' => 'error', 'message' => lang('Invalid ID.')));
                }
                // rss_field names a field's role (title / description /
                // media / category), wysiwyg says whether a text area holds
                // markup, office_use_only keeps staff fields off a public
                // layout — the designer builds a form's starter layout from
                // these, in the form's own order.
                $sc_ff_rows = db_items(
                    "SELECT id, name, label, type, rss_field, wysiwyg, office_use_only
                     FROM form_fields
                     WHERE page_id = '$sc_ff_pid'
                     ORDER BY sort_order ASC, id ASC"
                );
                respond(array('status' => 'success', 'fields' => $sc_ff_rows));
                break;

            default:
                respond(array('status' => 'error', 'message' => lang('Invalid action.')));
        }
        break;

    // ========================= DESIGNER FILES =========================
    // Visual Pinegrap Editor's "New CSS/JS/JSON File" actions create a real
    // file (disk + `files` row) at click time so the asset is available
    // immediately in View Files and is referenced via a stable URL — same
    // mechanism create_file.php uses for manual file creation.
    case 'designer_file':
        validate_token();
        $user = validate_user();

        // Admin (0) and designer (1) only — symmetric with shared_component.
        // Manager is 2 and does not shape the design.
        if ((int) $user['role'] > 1) {
            respond(array('status' => 'error', 'message' => lang('Permission denied.')));
        }

        if (!defined('FILE_DIRECTORY_PATH')) {
            respond(array('status' => 'error', 'message' => 'FILE_DIRECTORY_PATH is not defined.'));
        }

        $df_sub = isset($request['sub_action']) ? $request['sub_action'] : '';

        switch ($df_sub) {

            // LIST_FOLDERS — return both flat data AND the rendered <option>
            // HTML produced by select_folder() so the assets panel modal can
            // either dump the HTML (matching add_file.php's exact look:
            // hierarchical indent + access-control colour + [ARCHIVED] tag)
            // or render its own UI from the data.
            case 'list_folders':
                $df_options_html = select_folder(0);
                respond(array(
                    'status'       => 'success',
                    'options_html' => $df_options_html,
                ));
                break;

            case 'create':
                $df_type   = isset($request['type'])   ? strtolower(trim($request['type'])) : '';
                $df_base   = isset($request['name'])   ? trim($request['name'])             : '';
                $df_folder = isset($request['folder']) ? (int) $request['folder']            : 0;
                // Design flag (1 = design file). Only honoured for admin (0) /
                // designer (1) — basic users in folder ACLs shouldn't be able
                // to upload "design files" they cannot manage. Manager (2)
                // never reaches this endpoint via the assets panel anyway
                // because they don't have designer-area access.
                $df_design = !empty($request['design']) ? 1 : 0;
                if ((int) $user['role'] > 1) {
                    $df_design = 0;
                }
                // Optional file description — same column View Files surfaces.
                $df_desc = isset($request['description']) ? trim((string)$request['description']) : '';

                if (!in_array($df_type, array('css', 'js', 'json'), true)) {
                    respond(array('status' => 'error', 'message' => 'Invalid file type.'));
                }
                // Folder is required — view_files.php joins on folder.folder_id
                // and excludes rows where the join misses, so a folder=0 file
                // is invisible in the file manager. Reject the create instead
                // of silently writing an unreachable file.
                if ($df_folder <= 0) {
                    respond(array('status' => 'error', 'message' => 'Folder is required.'));
                }
                $df_folder_ok = db_value("SELECT folder_id FROM folder WHERE folder_id = '" . (int)$df_folder . "' LIMIT 1");
                if (!$df_folder_ok) {
                    respond(array('status' => 'error', 'message' => 'Folder not found.'));
                }
                // Role-3 (contributor) must actually have access to the folder.
                if ((int)$user['role'] === 3) {
                    $df_acl = get_folders_that_user_has_access_to($user['id']);
                    if (!in_array($df_folder, $df_acl)) {
                        respond(array('status' => 'error', 'message' => lang('Permission denied.')));
                    }
                }
                // Default base name when caller omits it
                if ($df_base === '') {
                    $df_base = ($df_type === 'css' ? 'style' : ($df_type === 'js' ? 'script' : 'data')) . '.' . $df_type;
                }
                // Force the extension to match the requested type
                $df_ext = strtolower(pathinfo($df_base, PATHINFO_EXTENSION));
                if ($df_ext !== $df_type) {
                    $df_base = preg_replace('/\.[^.]+$/', '', $df_base) . '.' . $df_type;
                }
                $df_base = prepare_file_name($df_base);
                // get_unique_name walks the page/file/folder name space so the URL
                // path won't collide with an existing page route either.
                $df_name = get_unique_name(array('name' => $df_base, 'type' => 'file'));

                // Caller may seed initial content (used by the upload flow,
                // which already has the file body in memory). Empty string and
                // missing key both fall back to a type-appropriate default.
                if (array_key_exists('content', $request) && is_string($request['content']) && $request['content'] !== '') {
                    $df_content = $request['content'];
                } else {
                    $df_content = ($df_type === 'json') ? '{}' : '';
                }

                $df_path = FILE_DIRECTORY_PATH . '/' . $df_name;
                $df_handle = @fopen($df_path, 'w');
                if (!$df_handle) {
                    respond(array('status' => 'error', 'message' => 'Could not create file on disk.'));
                }
                if ($df_content !== '') fwrite($df_handle, $df_content);
                fclose($df_handle);
                $df_size = (int) @filesize($df_path);

                db("INSERT INTO files (name, folder, description, type, size, design, user, timestamp)
                    VALUES (
                        '" . e($df_name) . "',
                        '" . (int)$df_folder . "',
                        '" . e($df_desc) . "',
                        '" . e($df_type) . "',
                        '" . (int)$df_size . "',
                        '" . (int)$df_design . "',
                        '" . (int)$user['id'] . "',
                        UNIX_TIMESTAMP()
                    )");

                log_activity(lang(array('string' => 'file ({var:1}) was created', 'vars' => $df_name)), $_SESSION['sessionusername']);

                respond(array(
                    'status'  => 'success',
                    'name'    => $df_name,
                    'url'     => OUTPUT_PATH . $df_name,
                    'type'    => $df_type,
                    'content' => $df_content,
                ));
                break;

            // READ — return the on-disk content of a managed file. Bound to
            // `files` rows so we never serve arbitrary paths from the host.
            case 'read':
                $df_name = isset($request['name']) ? trim($request['name']) : '';
                if ($df_name === '') {
                    respond(array('status' => 'error', 'message' => 'Name is required.'));
                }
                $df_name_safe = prepare_file_name(basename($df_name));
                if ($df_name_safe === '' || $df_name_safe !== $df_name) {
                    respond(array('status' => 'error', 'message' => 'Invalid file name.'));
                }
                $df_row = db_value("SELECT type FROM files WHERE name = '" . e($df_name_safe) . "' LIMIT 1");
                if (!$df_row && $df_row !== '0') {
                    respond(array('status' => 'error', 'message' => 'File is not registered in files table.'));
                }
                $df_path = FILE_DIRECTORY_PATH . '/' . $df_name_safe;
                if (!file_exists($df_path)) {
                    respond(array('status' => 'error', 'message' => 'File missing on disk.'));
                }
                $df_content = @file_get_contents($df_path);
                if ($df_content === false) {
                    respond(array('status' => 'error', 'message' => 'Could not read file.'));
                }
                respond(array(
                    'status'  => 'success',
                    'name'    => $df_name_safe,
                    'content' => $df_content,
                ));
                break;

            // DELETE — destructive: remove the on-disk file + its `files` row.
            // The caller should confirm with the user first; this endpoint
            // does not prompt. Limited to rows already in `files` so callers
            // can't unlink arbitrary host paths.
            case 'delete':
                $df_name = isset($request['name']) ? trim($request['name']) : '';
                if ($df_name === '') {
                    respond(array('status' => 'error', 'message' => 'Name is required.'));
                }
                $df_name_safe = prepare_file_name(basename($df_name));
                if ($df_name_safe === '' || $df_name_safe !== $df_name) {
                    respond(array('status' => 'error', 'message' => 'Invalid file name.'));
                }
                $df_row_q = mysqli_query(db::$con,
                    "SELECT id, folder FROM files WHERE name = '" . e($df_name_safe) . "' LIMIT 1");
                if (!$df_row_q || mysqli_num_rows($df_row_q) === 0) {
                    respond(array('status' => 'error', 'message' => 'File is not registered in files table.'));
                }
                $df_row = mysqli_fetch_assoc($df_row_q);
                // Role-3 (contributor) needs explicit access to the file's folder.
                if ((int)$user['role'] === 3) {
                    $df_acl = get_folders_that_user_has_access_to($user['id']);
                    if (!in_array($df_row['folder'], $df_acl)) {
                        respond(array('status' => 'error', 'message' => lang('Permission denied.')));
                    }
                }
                $df_path = FILE_DIRECTORY_PATH . '/' . $df_name_safe;
                if (file_exists($df_path)) {
                    if (!@unlink($df_path)) {
                        respond(array('status' => 'error', 'message' => 'Could not delete file from disk.'));
                    }
                }
                db("DELETE FROM files WHERE id = '" . (int)$df_row['id'] . "' LIMIT 1");
                log_activity(lang(array('string' => 'file ({var:1}) was deleted', 'vars' => $df_name_safe)), $_SESSION['sessionusername']);
                respond(array('status' => 'success', 'name' => $df_name_safe));
                break;

            // SAVE — overwrite an existing managed file. Limited to file rows
            // already present in `files` (so callers can't write arbitrary paths).
            case 'save':
                $df_name = isset($request['name']) ? trim($request['name']) : '';
                $df_content = isset($request['content']) ? (string)$request['content'] : '';
                if ($df_name === '') {
                    respond(array('status' => 'error', 'message' => 'Name is required.'));
                }
                $df_name_safe = prepare_file_name(basename($df_name));
                if ($df_name_safe === '' || $df_name_safe !== $df_name) {
                    respond(array('status' => 'error', 'message' => 'Invalid file name.'));
                }
                $df_exists = db_value("SELECT COUNT(*) FROM files WHERE name = '" . e($df_name_safe) . "'");
                if (!$df_exists) {
                    respond(array('status' => 'error', 'message' => 'File is not registered in files table.'));
                }
                $df_path = FILE_DIRECTORY_PATH . '/' . $df_name_safe;
                $df_written = @file_put_contents($df_path, $df_content);
                if ($df_written === false) {
                    respond(array('status' => 'error', 'message' => 'Could not write file.'));
                }
                db("UPDATE files SET size = '" . (int)strlen($df_content) . "', timestamp = UNIX_TIMESTAMP()
                    WHERE name = '" . e($df_name_safe) . "'");
                respond(array('status' => 'success', 'name' => $df_name_safe, 'size' => (int)strlen($df_content)));
                break;

            default:
                respond(array('status' => 'error', 'message' => 'Unknown sub_action.'));
        }
        break;

    default:
        $response = array(
            'status' => 'error',
            'message' => 'Invalid action.'
        );

        echo encode_json($response);
        exit();

        break;
}

function respond($response)
{
    echo encode_json($response);
    exit;
}

// Returns a name that does not yet exist in shared_components.
// If $desired is already taken, appends " [1]", " [2]", ... until a free slot is found.
function _sc_unique_name($desired)
{
    $base = $desired;
    $name = $base;
    $i    = 1;
    while (db_value("SELECT COUNT(*) FROM shared_components WHERE name = '" . e($name) . "'") > 0) {
        $name = $base . ' [' . $i . ']';
        $i++;
    }
    return $name;
}

// A token is required to be passed in the request for session login requests
// that update an item.
function validate_token()
{

    global $token;

    // If the user passed a username and password in this request
    // and did not login via a session, then token validation is not
    // necessary, so return true.
    //
    // API_AUTHENTICATED, not API_USERNAME: the first says the password was
    // checked and was right, the second only says one was sent. A page on
    // another site can make a visitor's browser post whatever body it likes,
    // and while this read the second of the two, adding a made up user name to
    // that body was enough to have the token waived - on a request that still
    // arrived with the visitor's own cookies.
    if (defined('API_AUTHENTICATED')) {
        return true;
    }

    // If the token does not exist in the session,
    // or the passed token does not match the token from the session,
    // then this might be a CSRF attack so respond with an error.
    //
    // The body is decoded json, so the token arrives with whatever type the
    // sender gave it. A loose compare would take `true` (or, on PHP 7, `0`)
    // as equal to the session's hex string; only a string compared byte for
    // byte counts.
    $session_token = isset($_SESSION['software']['token']) ? (string) $_SESSION['software']['token'] : '';
    if (
        ($session_token === '')
        || (!is_string($token))
        || (!hash_equals($session_token, $token))
    ) {
        respond(array(
            'status' => 'error',
            'message' => 'Invalid token.'
        ));
    }
}