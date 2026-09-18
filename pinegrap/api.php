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


//required for software backup mysql dumb
use Ifsnop\Mysqldump as IMysqldump;

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

) {

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

include_once('mysqldump.php');

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
        // No token validation required, it's a safe internal fallback trigger
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

    case 'database_deep_check':
        // The full CHECK TABLE sweep, on request instead of on every dashboard
        // load. The routine sweep behind the System Status widget uses the
        // cheap MyISAM flags and leaves alone the engines that ignore them;
        // the thorough pass lives here, where an operator decides when the
        // site can afford it.
        $user = validate_user();

        // Same gate as the widget that reports the result.
        if ((int) $user['role'] >= 3) {
            respond(array(
                'status' => 'error',
                'message' => lang('Access denied.'),
            ));
            break;
        }

        // This reads every row and every index of every table. On a large
        // database that is minutes, so it releases the session lock first --
        // otherwise the operator's own next page load queues behind it -- and
        // lifts the execution limit, because a sweep killed halfway leaves the
        // report it was building unwritten.
        session_write_close();

        if (function_exists('set_time_limit')) { // disable_functions on some hosts
            @set_time_limit(0);
        }

        $deep_report = check_and_repair_database_tables(true);

        $deep_issues = 0;
        $deep_repairs = 0;

        foreach ($deep_report as $deep_messages) {
            foreach ($deep_messages as $deep_message) {
                if ($deep_message === 'error') {
                    $deep_issues++;
                }
                if ($deep_message === 'repaired') {
                    $deep_repairs++;
                }
            }
        }

        // The status widget renders from a cache of its own, and would keep
        // showing what the routine sweep found until it expired.
        $deep_status_cache = dirname(__FILE__) . '/data/temp/system_status_cache.json';

        if (file_exists($deep_status_cache)) {
            @unlink($deep_status_cache);
        }

        respond(array(
            'status' => 'success',
            'message' => lang(array(
                'string' => '{var:1} table(s) checked, {var:2} issue(s) found, {var:3} repaired.',
                'vars' => array(
                    number_format(count($deep_report)),
                    number_format($deep_issues),
                    number_format($deep_repairs),
                ),
            )),
            // The tile that starts this is one of the health tiles and has a
            // health tile's room -- a couple of words. The sentence above goes
            // in the row beneath it; this is what fits on the tile itself.
            'summary' => lang(array(
                'string' => '{var:1} issue(s)',
                'vars' => array(number_format($deep_issues)),
            )),
        ));
        break;

    case 'server_config_repair':
        // Write the missing rules into the web.config / .htaccess in the web
        // root. What gets written and how is in includes/server_config.php;
        // this is the door.
        //
        // Administrator only. The general gate above lets a designer through,
        // and a designer is trusted with the look of the site, not with the
        // file that decides what the whole server will and will not hand out.
        // The rest of the widget stands behind role < 3, so the tile that
        // starts this is drawn for role 0 alone rather than being offered to
        // people it would refuse.
        $user = validate_user();

        if ((int) $user['role'] !== 0) {
            respond(array(
                'status' => 'error',
                'message' => lang('Access denied.'),
            ));
            break;
        }

        $server_config_result = pg_server_config_repair(true);

        // The status widget renders from a ten-minute cache and would keep
        // reporting the rules as missing until it expired -- which reads as
        // the button having done nothing.
        $server_config_cache = dirname(__FILE__) . '/data/temp/system_status_cache.json';

        if (file_exists($server_config_cache)) {
            @unlink($server_config_cache);
        }

        if ($server_config_result['status'] !== 'success') {
            respond(array(
                'status' => 'error',
                'message' => $server_config_result['message'],
                'summary' => lang('Failed'),
            ));
            break;
        }

        // Shown relative to the web root: an absolute path names the account and
        // the folder the site lives in, and the operator going after the file
        // over FTP starts at the web root anyway. Separators are normalised
        // first -- on Windows dirname() answers in backslashes while the backup
        // path was built with forward ones, and the two never match.
        $server_config_backup = str_replace('\\', '/', $server_config_result['backup']);
        $server_config_root   = rtrim(str_replace('\\', '/', dirname(dirname(__FILE__))), '/') . '/';

        if (strpos($server_config_backup, $server_config_root) === 0) {
            $server_config_backup = substr($server_config_backup, strlen($server_config_root));
        }

        respond(array(
            'status' => 'success',
            'message' => $server_config_result['message']
                . ($server_config_result['backup'] !== ''
                    ? ' ' . lang(array(
                        'string' => 'The previous file was kept as {var:1}.',
                        'vars'   => $server_config_backup,
                    ))
                    : ''),
            'summary' => lang('Done'),
        ));
        break;

    case 'write_permissions_repair':
        // Open the folders and files of the software the web server cannot
        // write to, from the System Status widget. The scan and the chmod are
        // pg_write_permission_scan() / pg_write_permission_repair() in
        // functions.php; this is the door.
        //
        // Administrator only, like the rules file: this changes who may write
        // into the software directory, which is not the same authority as
        // clearing a cache.
        $user = validate_user();

        if ((int) $user['role'] !== 0) {
            respond(array(
                'status' => 'error',
                'message' => lang('Access denied.'),
            ));
            break;
        }

        $permissions_result = pg_write_permission_repair();

        log_activity(
            lang(array('string' => 'Write permissions repaired from the dashboard ({var:1}).', 'vars' => array($permissions_result['message']))),
            $_SESSION['sessionusername']
        );

        // The widget renders from a ten-minute cache and would keep reporting
        // the folders as closed until it expired -- which reads as the button
        // having done nothing.
        $permissions_cache = dirname(__FILE__) . '/data/temp/system_status_cache.json';

        if (file_exists($permissions_cache)) {
            @unlink($permissions_cache);
        }

        respond(array(
            'status' => ($permissions_result['status'] === 'error') ? 'error' : 'success',
            'message' => $permissions_result['message'],
            'summary' => ($permissions_result['status'] === 'success') ? lang('Done') : (($permissions_result['status'] === 'partial') ? lang('Partly') : lang('Failed')),
        ));
        break;

    case 'purge_cache':
        // Clearing the caches from the System Status widget, without leaving
        // the dashboard.
        //
        // purge_cache.php does the same work and then redirects to
        // settings.php. That is the right ending for a link pressed on the
        // settings screen and the wrong one for a tile on a card: the operator
        // pressed a button on the dashboard and landed on another page, with
        // the widget they were reading left behind. Both doors call
        // pg_purge_caches(), so the two cannot come to clear different things.
        //
        // Manager and above, matching purge_cache.php's own
        // validate_area_access($user, 'manager') -- written here as a role test
        // because that function answers in HTML, and an HTML refusal reaches a
        // caller expecting JSON as "unexpected token <".
        $user = validate_user();

        if ((int) $user['role'] >= 3) {
            respond(array(
                'status' => 'error',
                'message' => lang('Access denied.'),
            ));
            break;
        }

        $purge_result = pg_purge_caches();

        log_activity(
            lang(array('string' => 'Cache purged ({var:1}).', 'vars' => array($purge_result['message']))),
            $_SESSION['sessionusername']
        );

        respond(array(
            'status'  => 'success',
            'message' => lang(array('string' => 'Cache cleared: {var:1}', 'vars' => array($purge_result['message']))),
            // Two words is what the row's own state line holds; the sentence
            // above goes in the panel that opens under it.
            'summary' => lang('Cleared'),
        ));
        break;

    case 'ca_bundle_update':
        // Replace data/cacert.pem with the current Mozilla root list, from the
        // System Status widget. The download, the checks and the atomic swap
        // are pg_ca_bundle_update() in includes/fn/update.php; this is the
        // door.
        //
        // Administrator only, and the token is checked as well as the
        // session: this writes the file that decides which certificates every
        // outbound connection will trust, which is the same authority as the
        // web server rules file, not the same as clearing a cache. The row is
        // drawn for every role that sees the widget, its button for role 0
        // alone, so nobody is offered a control that would refuse them.
        $user = validate_user();

        if ((int) $user['role'] !== 0) {
            respond(array(
                'status' => 'error',
                'message' => lang('Access denied.'),
            ));
            break;
        }

        validate_token();

        // The download may take a while on a slow link; the operator's own
        // next page load should not queue behind it.
        session_write_close();

        $ca_bundle_result = pg_ca_bundle_update();

        log_activity(
            lang(array('string' => 'CA bundle update from the dashboard ({var:1}).', 'vars' => array($ca_bundle_result['message']))),
            $_SESSION['sessionusername']
        );

        if ($ca_bundle_result['status'] === 'error') {
            respond(array(
                'status'  => 'error',
                'message' => $ca_bundle_result['message'],
                'summary' => lang('Failed'),
            ));
            break;
        }

        respond(array(
            'status'  => 'success',
            'message' => $ca_bundle_result['message'],
            // Two words for the row's own state line; the sentence above goes
            // in the panel that opens under it.
            'summary' => ($ca_bundle_result['status'] === 'unchanged') ? lang('Already current') : lang('Updated'),
        ));
        break;

    case 'get_widget_data':
        $user = validate_user();
        // Release the session file lock immediately after authentication so that
        // concurrent widget AJAX requests are not serialized waiting for each other.
        session_write_close();
        $widget_id = $request['widget_id'];
        $output_rows = '';


        switch ($widget_id) {

            case 'clock':
                $output_data = '';
                //return success json output
                $response = array(
                    'status' => 'success',
                    'data' => get_absolute_time(array(
                        'timestamp' => time(),
                        'type' => 'time',
                        'timezone_type' => 'site'
                    )),
                    'message' => lang('Data Received successfully.') . $_SESSION['sessionusername'],
                );
                echo encode_json($response);
                exit();
                break;

            case '1':
                // ── Sales map ───────────────────────────────────────────
                //
                // Where the money came from. The dashboard could say how much
                // was sold and what was sold, never where from: that question
                // needed a report to be built in view_order_report.php and run,
                // which is not something anyone does while glancing at a panel.
                //
                // Two views over one set of numbers. The world map answers
                // "which countries", a country's own map answers "which parts
                // of it", and the list beside either one always names regions:
                // the map is the scale, the list is the detail. The card opens
                // on whichever view its own data calls for -- see
                // pg_sales_map_collect().
                //
                // The figures come from the same columns and the same status
                // filter as the Sales Report's "Billing State" summary, so the
                // card and the report cannot disagree. Everything about the
                // data is in includes/sales_map.php; what is left here is the
                // markup, as it is for every other widget on this screen.
                //
                // Gated on commerce REPORTS, the permission view_order_report.php
                // asks for, rather than on commerce: this is a sales report, and
                // a user can hold one of those two without the other.
                if ((ECOMMERCE === true) && USER_MANAGE_ECOMMERCE_REPORTS) {

                    require_once PG_FUNCTIONS_DIR . '/includes/sales_map.php';

                    $sales_map = pg_sales_map_collect();

                    if ($sales_map['status'] !== 'ok') {

                        respond(array(
                            'status'  => 'success',
                            'message' => lang('Data Received successfully.'),
                            'data'    => '<div class="card-body p-0">'
                                . pg_widget_empty('bi-map', lang(array(
                                    'string' => 'There is no {var:1} right now.',
                                    'vars'   => lang('Order'))))
                                . '</div>'));
                    }

                    // Separators given explicitly, as everywhere else in this
                    // file: number_format() with only a precision falls back to
                    // the English ones and puts "9,986" on the same line as
                    // "798.380,70".
                    // Every place on this card links to the orders screen, and
                    // that screen asks for commerce, not for commerce reports.
                    // Somebody who may read the report but not work the orders
                    // gets the same card without the links -- rather than a row
                    // that lands on "Access denied".
                    $sm_can_open = (defined('USER_MANAGE_ECOMMERCE') && USER_MANAGE_ECOMMERCE);

                    $sm_url = function ($country_name, $state, $from) use ($sm_can_open, $sales_map) {

                        if (!$sm_can_open) {
                            return '';
                        }

                        return pg_sales_map_orders_url($country_name, $state, $from, $sales_map['first']);
                    };

                    $sm_money = function ($cents) {
                        return BASE_CURRENCY_SYMBOL . number_format($cents / 100, 2, ',', '.');
                    };

                    $sm_count = function ($number) {
                        return number_format($number, 0, ',', '.');
                    };

                    $sm_orders = function ($number) use ($sm_count) {
                        return lang(array(
                            'string' => '{var:1} order{suffix:1}',
                            'vars'   => $sm_count($number),
                            'suffix' => ($number == 1) ? '' : 's'));
                    };

                    // The world is always on offer; a country joins it only
                    // where we ship outlines for it. One market and one map
                    // means no switch at all.
                    $sm_views = array('world' => lang('World'));
                    $sm_files = array('world' => 'assets/maps/world-countries.svg');

                    if ($sales_map['home_map'] !== '') {
                        // Through lang(), because the countries table holds one
                        // spelling per installation and it is whatever was typed
                        // into it. The value that filters the orders screen is
                        // the stored one and stays untranslated.
                        $sm_views[$sales_map['home']] = lang($sales_map['home_name']);
                        $sm_files[$sales_map['home']] = 'assets/maps/' . $sales_map['home_map'];
                    }

                    $sm_view = (($sales_map['default'] === 'home') && ($sales_map['home_map'] !== ''))
                        ? $sales_map['home']
                        : 'world';

                    // Versioned by the file's own timestamp, the way the rest of
                    // the panel's assets are: the outlines are cached hard, and
                    // a regenerated map has to reach a browser that already has
                    // the old one.
                    $sm_maps = array();

                    foreach ($sm_files as $sm_key => $sm_file) {
                        $sm_maps[$sm_key] = $sm_file . '?v=' . (int) @filemtime(PG_FUNCTIONS_DIR . '/' . $sm_file);
                    }

                    $sm_country_rows = db_items("SELECT code, name FROM countries", 'code');

                    $sm_heads = '';
                    $sm_lists = '';
                    $sm_tabs = '';
                    $sm_switch = '';

                    $sm_payload = array(
                        'view'  => $sm_view,
                        'mode'  => $sales_map['period'],
                        'maps'  => $sm_maps,
                        'rows'  => array(),
                        'names' => array(),
                        'none'  => '<span class="text-muted">' . h(lang('No sales')) . '</span>');

                    foreach ($sm_views as $sm_view_key => $sm_view_label) {

                        $sm_is_world = ($sm_view_key === 'world');

                        // Names for the places that sold nothing: hovering one
                        // still has to say which place it is.
                        $sm_names = array();

                        if ($sm_is_world) {

                            foreach ($sm_country_rows as $sm_code => $sm_row) {
                                $sm_names[strtoupper($sm_code)] = h(lang($sm_row['name']));
                            }

                        } else {

                            foreach (call_user_func(pg_sales_map_region_maps()[$sm_view_key]['names']) as $sm_code => $sm_name) {
                                $sm_names[$sm_code] = h($sm_name);
                            }
                        }

                        $sm_payload['names'][$sm_view_key] = $sm_names;
                        $sm_payload['rows'][$sm_view_key] = array();

                        $sm_switch .=
                            '<li><button type="button" class="dropdown-item'
                            . (($sm_view_key === $sm_view) ? ' active' : '') . '" data-smap-view="' . h($sm_view_key) . '">'
                            . '<i class="bi bi-check2 pg-smap-tick"></i>' . h($sm_view_label) . '</button></li>';

                        foreach ($sales_map['periods'] as $sm_period_key => $sm_period) {

                            $sm_slot = $sm_view_key . '|' . $sm_period_key;
                            $sm_hidden = (($sm_view_key === $sm_view) && ($sm_period_key === $sales_map['period']))
                                ? ''
                                : ' d-none';

                            // A view answers for what it draws: the world for
                            // every order, a country for its own. Showing the
                            // shop's whole revenue over a map of one country
                            // would invite the reader to add the map up and get
                            // a different number.
                            $sm_regions = array();

                            foreach ($sm_period['regions'] as $sm_region_key => $sm_region) {

                                if (($sm_is_world) || ($sm_region['country'] === $sm_view_key)) {
                                    $sm_regions[$sm_region_key] = $sm_region;
                                }
                            }

                            if ($sm_is_world) {
                                $sm_total = $sm_period['total'];
                                $sm_orders_count = $sm_period['count'];
                            } else {
                                $sm_total = $sm_period['countries'][$sm_view_key]['total'] ?? 0;
                                $sm_orders_count = $sm_period['countries'][$sm_view_key]['count'] ?? 0;
                            }

                            // ── Head ─────────────────────────────────────────
                            $sm_places = $sm_is_world ? count($sm_period['countries']) : count($sm_regions);

                            $sm_heads .=
                                '<div class="pg-head' . $sm_hidden . '" data-smap-head="' . $sm_slot . '">'
                                . '<div class="pg-head-line">'
                                . '<span class="pg-head-num">' . $sm_money($sm_total) . '</span>'
                                . '<span class="pg-head-unit text-muted">' . h($sm_period['label']) . '</span>'
                                . '<span class="pg-head-total text-muted">'
                                . $sm_orders($sm_orders_count)
                                . ' &middot; '
                                . lang(array(
                                    'string' => $sm_is_world ? '{var:1} countr{suffix:1}' : '{var:1} region{suffix:1}',
                                    'vars'   => $sm_count($sm_places),
                                    'suffix' => $sm_is_world
                                        ? (($sm_places == 1) ? 'y' : 'ies')
                                        : (($sm_places == 1) ? '' : 's')))
                                . '</span>'
                                . '</div></div>';

                            // ── Regions, ranked ──────────────────────────────
                            //
                            // Built here rather than in the browser so that a
                            // row on this card is the same row as on every
                            // other one: pg_widget_row() stays the single
                            // description of what a dashboard list line looks
                            // like.
                            $sm_rows = '';
                            $sm_rank = 0;

                            foreach ($sm_regions as $sm_region) {

                                $sm_rank++;

                                if ($sm_rank > 8) {
                                    break;
                                }

                                $sm_share = ($sm_total > 0)
                                    ? round(($sm_region['total'] / $sm_total) * 100)
                                    : 0;

                                $sm_meta = $sm_orders($sm_region['count'])
                                    . ' &middot; ' . lang(array('string' => '{var:1}% share', 'vars' => $sm_share));

                                // On the world view the region's country is
                                // half of its identity: there is a Sivas in
                                // Turkey and a Georgia in two places.
                                if (($sm_is_world) && ($sm_region['country_name'] !== '')) {
                                    $sm_meta = h(lang($sm_region['country_name'])) . ' &middot; ' . $sm_meta;
                                }

                                $sm_rows .= pg_widget_row(array(
                                    'href'  => h($sm_url(
                                        $sm_region['country_name'], $sm_region['state'], $sm_period['from'])),
                                    'badge' => $sm_rank,
                                    'name'  => h($sm_region['name']),
                                    'aside' => $sm_money($sm_region['total']),
                                    'meta'  => $sm_meta));
                            }

                            if ($sm_rows === '') {
                                $sm_rows = pg_widget_empty('bi-map', lang('There are no sales in this period.'));
                            }

                            $sm_lists .=
                                '<div class="pg-list' . $sm_hidden . '" data-smap-list="' . $sm_slot . '">'
                                . $sm_rows . '</div>';

                            // ── What the map colours ─────────────────────────
                            //
                            // Countries on the world view, regions on a
                            // country's own. The tooltip is composed here for
                            // the same reason the rows are: money formatting,
                            // plurals and the translation table all live on
                            // this side.
                            $sm_entries = $sm_is_world ? $sm_period['countries'] : $sm_regions;
                            $sm_steps = pg_sales_map_steps($sm_entries);
                            $sm_tips = array();

                            foreach ($sm_entries as $sm_entry_key => $sm_entry) {

                                if ($sm_entry['code'] === '') {
                                    continue;
                                }

                                $sm_share = ($sm_total > 0)
                                    ? round(($sm_entry['total'] / $sm_total) * 100)
                                    : 0;

                                // A country names its own best regions, a region
                                // names its best cities: one level further down
                                // than whatever is being pointed at.
                                $sm_detail = array();

                                if ($sm_is_world) {

                                    foreach ($sm_period['regions'] as $sm_region) {

                                        if (($sm_region['country'] === $sm_entry['code']) && (count($sm_detail) < 3)) {
                                            $sm_detail[] = h($sm_region['name']);
                                        }
                                    }

                                } else {

                                    foreach ($sm_entry['cities'] as $sm_city) {

                                        if ($sm_city['name'] !== '') {
                                            $sm_detail[] = h($sm_city['name']);
                                        }
                                    }
                                }

                                $sm_tips[$sm_entry['code']] = array(
                                    'b' => (int) ($sm_steps[$sm_entry_key] ?? 0),
                                    'u' => $sm_url(
                                        $sm_is_world ? $sm_entry['name'] : $sm_entry['country_name'],
                                        $sm_is_world ? '' : $sm_entry['state'],
                                        $sm_period['from']),
                                    'p' => '<b>' . h($sm_is_world ? lang($sm_entry['name']) : $sm_entry['name']) . '</b>'
                                        . '<span>' . $sm_money($sm_entry['total']) . ' &middot; '
                                        . $sm_orders($sm_entry['count']) . '</span>'
                                        . '<span>' . lang(array('string' => '{var:1}% share', 'vars' => $sm_share)) . '</span>'
                                        . (!empty($sm_detail)
                                            ? '<span class="text-muted">' . implode(', ', $sm_detail) . '</span>'
                                            : ''));
                            }

                            $sm_payload['rows'][$sm_view_key][$sm_period_key] = $sm_tips;
                        }
                    }

                    // ── Period switch ────────────────────────────────────────
                    foreach ($sales_map['periods'] as $sm_period_key => $sm_period) {

                        $sm_tabs .=
                            '<li><button type="button" class="dropdown-item'
                            . (($sm_period_key === $sales_map['period']) ? ' active' : '') . '"'
                            . ' data-smap="' . $sm_period_key . '">'
                            . '<i class="bi bi-check2 pg-smap-tick"></i>' . h($sm_period['label']) . '</button></li>';
                    }

                    // ── The switches, as a menu ──────────────────────────────
                    //
                    // A strip of buttons under the map cost it a band of its own
                    // height, and a .btn-group stretched across the panel gives
                    // every button an equal share of it whatever its label says
                    // -- three period labels wrapped to two lines apiece there.
                    // A menu in the corner costs one icon, and the head beside
                    // the revenue already names the period on show.
                    //
                    // The map section only appears where there is a second map
                    // to switch to.
                    $sm_menu =
                        '<div class="dropdown pg-smap-menu">'
                        . '<button type="button" class="btn btn-sm btn-ghost pg-smap-menu-btn" id="pg_smap_menu"'
                        . ' data-bs-toggle="dropdown" aria-expanded="false" aria-label="' . h(lang('Filter')) . '">'
                        . '<i class="bi bi-sliders"></i></button>'
                        . '<ul class="dropdown-menu dropdown-menu-end">'
                        . '<li><h6 class="dropdown-header">' . h(lang('Period')) . '</h6></li>'
                        . $sm_tabs
                        . ((count($sm_views) > 1)
                            ? '<li><hr class="dropdown-divider"></li>'
                                . '<li><h6 class="dropdown-header">' . h(lang('Map')) . '</h6></li>' . $sm_switch
                            : '')
                        . '</ul></div>';

                    // ── Map panel ────────────────────────────────────────────
                    //
                    // The outlines are static files so the browser can cache
                    // them: the dashboard rebuilds its widgets once a minute and
                    // the paths do not need to come back with every rebuild.
                    // Fetched rather than pointed at with an <img>, because a
                    // path inside an <img> cannot be coloured.
                    $output_data =
                        '<div class="card-body p-0 pg-split" id="pg_smap">'
                        . '<div class="pg-split-half pg-smap-main">'
                        . $sm_heads
                        . '<div class="pg-smap-canvas' . ($sm_can_open ? '' : ' is-static') . '" id="pg_smap_canvas" role="img" aria-label="' . h(lang('Sales Map')) . '">'
                        . '<div class="pg-smap-tip" id="pg_smap_tip"></div>'
                        . $sm_menu
                        . '</div>'
                        . '</div>'
                        . '<div class="pg-split-half">' . $sm_lists . '</div>'
                        . '</div>
                        <script>(function(){

                            var root = document.getElementById("pg_smap");

                            if (!root) { return; }

                            var data = ' . encode_json($sm_payload) . ';
                            var view = data.view;
                            var mode = data.mode;
                            var canvas = document.getElementById("pg_smap_canvas");
                            var tip = document.getElementById("pg_smap_tip");

                            // One layer per view, fetched once and then kept:
                            // switching back and forth must not go to the
                            // network, and the browser cache is not a promise.
                            var layers = {};

                            function rows() {
                                return (data.rows[view] || {})[mode] || {};
                            }

                            function hide() {
                                if (tip) { tip.classList.remove("is-on"); }
                            }

                            // Painting is separate from loading: switching the
                            // period recolours the paths that are already there
                            // rather than fetching anything.
                            function paint() {

                                var layer = layers[view];

                                if (!layer) { return; }

                                var current = rows();
                                var paths = layer.querySelectorAll("path[id]");

                                for (var i = 0; i < paths.length; i++) {
                                    var row = current[paths[i].id];
                                    paths[i].setAttribute("class", "pg-smap-s" + (row ? row.b : 0));
                                }
                            }

                            function reveal() {
                                for (var key in layers) {
                                    layers[key].classList.toggle("d-none", key !== view);
                                }
                            }

                            function ensure() {

                                if (!canvas) { return; }

                                reveal();

                                if (layers[view]) { paint(); return; }

                                var url = data.maps[view];

                                if (!url) { return; }

                                fetch(url, { credentials: "same-origin" })
                                    .then(function(response){
                                        if (!response.ok) { throw new Error(response.status); }
                                        return response.text();
                                    })
                                    .then(function(markup){
                                        var layer = document.createElement("div");
                                        layer.className = "pg-smap-layer";
                                        layer.innerHTML = markup;
                                        canvas.appendChild(layer);
                                        layers[view] = layer;
                                        reveal();
                                        paint();
                                    })
                                    .catch(function(){
                                        // The outlines are a presentation
                                        // layer: every number they carry is
                                        // already written in the list beside
                                        // them, so a missing file costs the
                                        // picture and nothing else.
                                        canvas.classList.add("d-none");
                                    });
                            }

                            function show() {

                                var slot = view + "|" + mode;

                                root.querySelectorAll("[data-smap-head],[data-smap-list]").forEach(function(el){
                                    var owner = el.getAttribute("data-smap-head") || el.getAttribute("data-smap-list");
                                    el.classList.toggle("d-none", owner !== slot);
                                });

                                root.querySelectorAll("[data-smap]").forEach(function(btn){
                                    btn.classList.toggle("active", btn.getAttribute("data-smap") === mode);
                                });

                                root.querySelectorAll("[data-smap-view]").forEach(function(btn){
                                    btn.classList.toggle("active", btn.getAttribute("data-smap-view") === view);
                                });

                                ensure();
                                hide();
                            }

                            function place(target) {
                                return (target && target.closest) ? target.closest("path[id]") : null;
                            }

                            if (canvas) {

                                canvas.addEventListener("mousemove", function(event){

                                    var path = place(event.target);

                                    if ((!path) || (!tip)) { hide(); return; }

                                    var row = rows()[path.id];
                                    var names = data.names[view] || {};
                                    var body = row
                                        ? row.p
                                        : (names[path.id] ? ("<b>" + names[path.id] + "</b>" + data.none) : "");

                                    if (!body) { hide(); return; }

                                    var box = canvas.getBoundingClientRect();

                                    tip.innerHTML = body;
                                    tip.classList.add("is-on");

                                    // Kept inside the card: a tooltip that hangs
                                    // off the right edge is clipped by the split
                                    // panel, not by the window.
                                    var x = event.clientX - box.left + 14;
                                    var y = event.clientY - box.top + 14;

                                    x = Math.min(x, Math.max(0, box.width - tip.offsetWidth - 4));
                                    y = Math.min(y, Math.max(0, box.height - tip.offsetHeight - 4));

                                    tip.style.left = x + "px";
                                    tip.style.top = y + "px";
                                });

                                canvas.addEventListener("mouseleave", hide);

                                canvas.addEventListener("click", function(event){

                                    var path = place(event.target);
                                    var row = path ? rows()[path.id] : null;

                                    if (row && row.u) { window.location.href = row.u; }
                                });
                            }

                            root.querySelectorAll("[data-smap]").forEach(function(btn){
                                btn.addEventListener("click", function(){
                                    mode = btn.getAttribute("data-smap");
                                    show();
                                });
                            });

                            root.querySelectorAll("[data-smap-view]").forEach(function(btn){
                                btn.addEventListener("click", function(){
                                    view = btn.getAttribute("data-smap-view");
                                    canvas.classList.remove("d-none");
                                    show();
                                });
                            });

                            // Popper in fixed strategy. The menu lives inside
                            // the map panel, and that panel is a scroll box:
                            // absolutely positioned, the menu is cut off at its
                            // edge. Fixed takes it out of that box entirely.
                            var menu = document.getElementById("pg_smap_menu");

                            if ((menu) && (window.bootstrap) && (window.bootstrap.Dropdown)) {

                                new window.bootstrap.Dropdown(menu, {
                                    popperConfig: function (config) {
                                        config.strategy = "fixed";
                                        return config;
                                    }
                                });
                            }

                            show();
                        })();</script>';

                    respond(array(
                        'status'  => 'success',
                        'message' => lang('Data Received successfully.'),
                        'data'    => $output_data));

                } else {

                    respond(array(
                        'status'  => 'error',
                        'message' => 'Access denied'));
                }
                break;

            case '2':
                // ── System status ───────────────────────────────────────
                //
                // Two panels across a double-width card. On the left one score
                // for "is this installation healthy", drawn as a gauge, over a
                // tile per reading. On the right the things an operator can
                // actually do about it, one to a line.
                //
                // The split is the point. This was a single grid in which
                // "SSL · Tamam" and "Önbellek · Temizle" were the same shape --
                // a reading and a button drawn identically, four characters
                // wide. A reading is read; a job is pressed, and a job needs
                // room for a verb and for the sentence that says what pressing
                // it will do.
                //
                // Order on the right is by state, not by run order: whatever
                // has a problem is the first line. Anything that opens -- a
                // per-job run list, the answer from a sweep -- opens directly
                // under its own line and pushes the rest down, instead of in a
                // box at the foot of the card that several rows pointed at.
                //
                // The checks themselves are in functions.php and are cached
                // there for ten minutes -- several of them stat the filesystem
                // or open a socket. This case only renders.
                //
                // Role gate matches the screens the rows link to: backups.php
                // and software_update.php both require manager or above.
                if ($user['role'] < 3) {

                    $status = get_system_status_checks();

                    $health_score = isset($status['score']) ? (int) $status['score'] : 0;
                    $health_checks = isset($status['checks']) && is_array($status['checks'])
                        ? $status['checks']
                        : array();

                    // Arc geometry. A half circle of r=52 about (70,70) --
                    // the same shape and radius the performance widget draws,
                    // so the two gauges on one dashboard read as one idea.
                    // Circumference is 326.73 and half of it is 163.36; the
                    // rest is the opening at the bottom, which is what leaves
                    // room for the score to sit inside the arc rather than
                    // under it.
                    //
                    // Drawn as a dashed circle rather than a path, so filling
                    // it is one number instead of two arc endpoints in PHP.
                    $health_arc = 163.36;

                    // ── Colour band ─────────────────────────────────────
                    //
                    // Same gauge, same three-stop gradient, same glow. What
                    // moves with the score is the hue: an arc that is the
                    // identical violet at 12% and at 98% makes the reader work
                    // the verdict out from the digits, and the colour is the
                    // half that can be read across a room.
                    //
                    // Bands: red under 25, amber under 50, blue under 80,
                    // green from 90. Eighty to ninety is the crossing between
                    // the last two and is drawn as teal rather than lumped in
                    // with either -- a site at 85 is neither "still wrong" nor
                    // "finished".
                    //
                    // Each band is three stops of one family, light to dark,
                    // plus one flat ink that the score, the caption and the
                    // matrix behind them all take. The ink is an RGB triple
                    // rather than a hex string because the matrix needs it at
                    // several alphas.
                    // ── Colour band, and where the gradient is laid ─────
                    //
                    // Four hues a band, and every band is a neon sweep in its
                    // own right. What the score moves is the weight, not the
                    // idea: the healthy end is cool -- lime into emerald into
                    // cyan -- and the failing end is hot -- orange into rose
                    // into violet. Deliberately not a traffic light. A flat
                    // green arc and a flat red one would say what the number
                    // already says, and would say it by throwing the gradient
                    // away.
                    //
                    // All four sit at the same weight, and it matters. A pastel
                    // first stop (#a7f3d0 was one) reads as washed-out white on
                    // a dark card and a 600-weight last stop sinks into it, so
                    // the bar came out bleached at one end and swallowed at the
                    // other -- lightness doing the travelling instead of hue,
                    // which is the one thing a gradient this small cannot
                    // afford. Every stop is a 400: bright, saturated, and only
                    // its hue different from its neighbour's.
                    //
                    // TWO sets of the four, because the same colours cannot
                    // serve both themes. On a black card the arc has to be
                    // bright to be seen; those same stops on a white one are
                    // pastel, and a lime-into-mint sweep on white is barely a
                    // sweep at all -- the four hues collapse into one wash.
                    //
                    // The light set is NOT the dark one darkened. Darkening
                    // alone keeps the four hues as close together as they were
                    // and merely makes them all deep, which on white reads as
                    // one navy arc with a slight lean at each end. It is the
                    // same journey travelled WIDER -- cyan to blue to violet to
                    // fuchsia rather than cyan to sky to indigo to violet -- at
                    // the 600/700 weights, where every stop clears three to one
                    // against the page and no two neighbours are the same hue.
                    //
                    // Both are handed to CSS and the stylesheet picks; PHP has
                    // no idea which theme it is rendering into.
                    //
                    // The ink is the flat colour the number takes, and it has
                    // the same problem: a 32px amber number on white is under
                    // three to one against the page.
                    if ($health_score < 25) {
                        $health_ink       = '236, 72, 153';
                        $health_ink_text  = '190, 24, 93';
                        $health_stops     = array('#fb923c', '#f43f5e', '#e879f9', '#a78bfa');
                        $health_stops_lt  = array('#c2410c', '#be123c', '#a21caf', '#6d28d9');
                    } elseif ($health_score < 50) {
                        $health_ink       = '251, 146, 60';
                        $health_ink_text  = '180, 83, 9';
                        $health_stops     = array('#fde047', '#fb923c', '#fb7185', '#e879f9');
                        $health_stops_lt  = array('#a16207', '#c2410c', '#be123c', '#9333ea');
                    } elseif ($health_score < 80) {
                        $health_ink       = '59, 130, 246';
                        $health_ink_text  = '29, 78, 216';
                        $health_stops     = array('#22d3ee', '#38bdf8', '#818cf8', '#c084fc');
                        $health_stops_lt  = array('#0e7490', '#1d4ed8', '#6d28d9', '#a21caf');
                    } elseif ($health_score < 90) {
                        $health_ink       = '45, 212, 191';
                        $health_ink_text  = '13, 148, 136';
                        $health_stops     = array('#4ade80', '#2dd4bf', '#22d3ee', '#818cf8');
                        $health_stops_lt  = array('#0d9488', '#0891b2', '#2563eb', '#6d28d9');
                    } else {
                        $health_ink       = '52, 211, 153';
                        $health_ink_text  = '4, 120, 87';
                        $health_stops     = array('#a3e635', '#34d399', '#22d3ee', '#818cf8');
                        $health_stops_lt  = array('#65a30d', '#059669', '#0891b2', '#3730a3');
                    }

                    // The gradient is laid ALONG the drawn arc, not across the
                    // circle's box. Across the box a site at 20% saw only the
                    // first stop -- one flat colour, on exactly the card that
                    // most needs to say something -- and even at 70% the last
                    // hue never reached the bar. Anchored to the bar, the whole
                    // sweep is on it at every score.
                    //
                    // Worked in the path's own space: an SVG circle starts at
                    // three o'clock and runs clockwise, and the half turn in the
                    // stylesheet is what puts the visible start at nine. So the
                    // ends are computed here untransformed and rotate with
                    // everything else.
                    //
                    // Clamped at a quarter turn: below it the two ends close on
                    // each other, and a gradient whose axis has no length paints
                    // as a single flat stop -- the very thing this prevents.
                    $health_span  = max($health_score / 100, 0.25);
                    $health_angle = M_PI * $health_span;
                    $health_x2    = round(70 + (52 * cos($health_angle)), 2);
                    $health_y2    = round(70 + (52 * sin($health_angle)), 2);

                    // ── Where the stops go ──────────────────────────────
                    //
                    // Not evenly. A linear gradient changes colour evenly along
                    // its AXIS, and the axis is the straight line between the
                    // two ends of the arc -- so the arc is read by projecting
                    // it onto that chord, and the projection is not even at
                    // all. Near the ends of a half turn the arc runs almost
                    // parallel to the chord and barely advances along it, so a
                    // long stretch of bar gets a sliver of the gradient; at the
                    // top it advances fastest and gets most of it. On a nearly
                    // full arc that is exactly what you see: two flat legs and
                    // every hue crammed into the crown.
                    //
                    // So each stop is placed at the axis position its own point
                    // on the arc actually projects to. The four then arrive at
                    // equal steps of ARC LENGTH, which is the thing being
                    // looked at.
                    $health_bx    = $health_x2 - 122;
                    $health_by    = $health_y2 - 70;
                    $health_len2  = ($health_bx * $health_bx) + ($health_by * $health_by);
                    $health_marks = array();

                    foreach (array(0, 1 / 3, 2 / 3, 1) as $health_u) {

                        $health_t = $health_angle * $health_u;
                        $health_qx = 70 + (52 * cos($health_t));
                        $health_qy = 70 + (52 * sin($health_t));

                        $health_o = ($health_len2 > 0)
                            ? (((($health_qx - 122) * $health_bx) + (($health_qy - 70) * $health_by)) / $health_len2)
                            : $health_u;

                        if ($health_o < 0) { $health_o = 0; }
                        if ($health_o > 1) { $health_o = 1; }

                        $health_marks[] = round($health_o * 100, 2);
                    }

                    // The same axis mirrored through the centre, for the layer
                    // that is written already turned. Rotating a point half a
                    // turn about (70,70) is (140 - x, 140 - y).
                    $health_mx2 = round(140 - $health_x2, 2);
                    $health_my2 = round(140 - $health_y2, 2);

                    // Everything the gauge is coloured from, in one place.
                    $health_vars = '--pg-health-ink:' . $health_ink
                        . ';--pg-health-ink-text:' . $health_ink_text;

                    foreach (array(1, 2, 3, 4) as $health_stop) {
                        $health_vars .= ';--pg-health-d' . $health_stop . ':' . $health_stops[$health_stop - 1]
                            . ';--pg-health-l' . $health_stop . ':' . $health_stops_lt[$health_stop - 1];
                    }


                    // ── Which side a check lands on ─────────────────────
                    //
                    // functions.php marks the four that are jobs rather than
                    // readings ($job_titles there): the rules file, the backup,
                    // the update and the scheduled tasks. Each has somewhere to
                    // go or something to press, so each becomes a line on the
                    // right. Everything else is a reading and stays a tile.
                    $reading_checks = array();
                    $job_checks     = array();

                    foreach ($health_checks as $health_check) {
                        if (!empty($health_check['job'])) {
                            $job_checks[] = $health_check;
                        } else {
                            $reading_checks[] = $health_check;
                        }
                    }

                    // ── Readings ────────────────────────────────────────
                    //
                    // A chip each, not a tile each. Twelve tiles across half a
                    // card put the label at nine pixels with the value at nine
                    // more underneath, and at that size "Veritabanı" and
                    // "Güncelleme" were both an ellipsis -- a grid of boxes
                    // whose labels had to be hovered to be read.
                    //
                    // The chip gets that width back by dropping the half that
                    // was not information. A check that is fine says so with
                    // its colour; printing "Tamam" nine times under nine green
                    // labels is the colour said twice, in the space the label
                    // needed. Only a check with something to report keeps its
                    // value, which is also what makes those rows the ones the
                    // eye lands on.
                    //
                    // Order is by state and then by weight: what is broken,
                    // what is uncertain, then the three security checks that
                    // lead when nothing is wrong, then the rest. usort() is
                    // stable only from PHP 8.0, so position is carried into the
                    // comparison -- without it the chips could reshuffle
                    // between two draws of the same card.
                    $check_ranks = array('fail' => 0, 'warn' => 1, 'info' => 3, 'ok' => 3);
                    $check_order = array();

                    foreach ($reading_checks as $check_index => $reading_check) {

                        $check_rank = isset($check_ranks[$reading_check['state']])
                            ? $check_ranks[$reading_check['state']]
                            : 3;

                        if (($check_rank === 3) && !empty($reading_check['priority'])) {
                            $check_rank = 2;
                        }

                        $check_order[] = array($check_rank, (int) $check_index);
                    }

                    usort($check_order, function ($a, $b) {
                        if ($a[0] === $b[0]) {
                            return ($a[1] < $b[1]) ? -1 : 1;
                        }
                        return ($a[0] < $b[0]) ? -1 : 1;
                    });

                    // Bootstrap's own four, so a chip is the same red as every
                    // other red on the screen.
                    $check_tones = array('ok' => 'success', 'warn' => 'warning', 'fail' => 'danger', 'info' => 'secondary');

                    $output_checks = '';
                    $check_slot = 0;

                    foreach ($check_order as $check_entry) {

                        $health_check = $reading_checks[$check_entry[1]];
                        $check_slot++;

                        $check_tone = isset($check_tones[$health_check['state']])
                            ? $check_tones[$health_check['state']]
                            : 'secondary';

                        $check_detail = (isset($health_check['detail']) && is_array($health_check['detail']))
                            ? $health_check['detail']
                            : array();

                        // The value only when the check said it. "Tamam",
                        // "Uyarı", "Sorun" and "Uygulanmaz" are the four words
                        // functions.php puts in a check's mouth when it has
                        // none of its own -- they are the colour spelled out,
                        // and a red chip does not need to be told it is red.
                        // What survives is what the check actually reported:
                        // "3 eksik", "2026.4.4", "4.2 MB".
                        $check_value = !empty($health_check['generic'])
                            ? ''
                            : '<span class="pg-check-value">' . h($health_check['value']) . '</span>';

                        $check_body = '
                            <i class="bi ' . h($health_check['icon']) . '"></i>
                            <span class="pg-check-name">' . h($health_check['label']) . '</span>' . $check_value;

                        $check_panel = '';

                        if ($check_detail) {

                            // Rows the check brought with it. They exist nowhere
                            // else, so the chip has to be able to show them
                            // rather than point at a screen that does not have
                            // the answer. The panel is a full-width child of the
                            // same wrapping row, so it opens on the line under
                            // its own chip instead of in a box at the foot of
                            // the card that several chips would point at.
                            //
                            // Bootstrap's collapse data-api is delegated from
                            // document, so it binds to markup this widget
                            // injects after page load. Widget 2 is deliberately
                            // absent from welcome.php's periodic refresh list,
                            // so nothing re-renders the card and closes the
                            // panel under the operator's hand.
                            $check_id = 'system_status_detail_' . $check_slot;

                            $check_rows = '';

                            foreach ($check_detail as $detail_row) {
                                $check_rows .= '
                                <div class="pg-job-panel-row">
                                    <span class="text-truncate"><i class="bi bi-circle-fill pg-health-dot text-' . h(($detail_row['state'] == 'ok') ? 'success' : (($detail_row['state'] == 'fail') ? 'danger' : 'secondary')) . '"></i>' . h($detail_row['label']) . '</span>
                                    <span class="text-muted flex-shrink-0">' . h($detail_row['when']) . '</span>
                                </div>';
                            }

                            // The popover would fire on the same hover that
                            // opens the panel, so a chip that expands carries
                            // the collapse attributes instead and its
                            // explanation is the panel.
                            $output_checks .= '
                            <button type="button" class="pg-check pg-check-' . $check_tone . '"
                                    data-bs-toggle="collapse" data-bs-target="#' . $check_id . '"
                                    aria-expanded="false" aria-controls="' . $check_id . '"
                                    title="' . h($health_check['title']) . '">' . $check_body . '
                                <i class="bi bi-chevron-down pg-check-caret"></i>
                            </button>
                            <div class="collapse pg-job-slot" id="' . $check_id . '">
                                <div class="pg-job-panel">' . $check_rows . '</div>
                            </div>';

                        } else {

                            // The popover is the only place the full
                            // explanation fits, and welcome.php binds it by
                            // delegation from document, so it survives the
                            // markup being injected after page load.
                            $check_open = ($health_check['href'] !== '')
                                ? '<a href="' . h($health_check['href']) . '"'
                                : '<div';

                            $output_checks .= $check_open . ' class="pg-check pg-check-' . $check_tone . ' status-popover"'
                                . ' title="' . h($health_check['title']) . '"'
                                . ' data-bs-toggle="popover"'
                                . ' data-bs-trigger="hover focus"'
                                . ' data-bs-content="' . h($health_check['message']) . '">' . $check_body
                                . (($health_check['href'] !== '') ? '</a>' : '</div>');
                        }
                    }

                    // ── The jobs column ─────────────────────────────────
                    //
                    // Four checks that are jobs, plus three tools that are
                    // always available. One record each, so that sorting them
                    // by state is one pass over one list rather than a decision
                    // repeated at every point a row is emitted.
                    //
                    // 'rank' orders the column: a failing check first, then a
                    // warning, then the tools, then whatever has nothing to
                    // report, and last the storage readings. Tools sit above
                    // the healthy checks because a tool is why the operator
                    // opened the column; a green backup row is confirmation and
                    // can wait. Storage is last because it is the only row here
                    // with nothing to act on at all.
                    $health_jobs = array();

                    $job_ranks = array('fail' => 0, 'warn' => 1, 'ok' => 3, 'info' => 3);

                    // Read once for the row and for the button beside it. The
                    // ten-minute status cache is deliberately not the source
                    // here: the operator presses Fix and expects the next draw
                    // to reflect what they just did. pg_server_config_scan()
                    // memoizes per request, so the check above and this share
                    // one read of a file a few kilobytes long.
                    $server_rules_scan = pg_server_config_scan();
                    $server_rules_todo = count($server_rules_scan['missing'])
                        + ($server_rules_scan['stale_path'] ? 1 : 0);

                    foreach ($job_checks as $job_check) {

                        $job_key = isset($job_check['key']) ? $job_check['key'] : '';
                        $job_action = '';
                        $job_panel = '';

                        // Rows the check brought with it -- per-job run times,
                        // the list of rules that are not in the file. They exist
                        // nowhere else, which is why the row expands instead of
                        // pointing at a screen.
                        $job_rows = '';

                        if (isset($job_check['detail']) && is_array($job_check['detail'])) {
                            foreach ($job_check['detail'] as $detail_row) {
                                $job_rows .= '
                                <div class="pg-job-panel-row">
                                    <span class="text-truncate"><i class="bi bi-circle-fill pg-health-dot text-' . h(($detail_row['state'] == 'ok') ? 'success' : (($detail_row['state'] == 'fail') ? 'danger' : 'secondary')) . '"></i>' . h($detail_row['label']) . '</span>
                                    <span class="text-muted flex-shrink-0">' . h($detail_row['when']) . '</span>
                                </div>';
                            }
                        }

                        if ($job_key === 'Web Server Rules') {

                            // The repair the row is about. Offered only when
                            // there is something to write: the file has a
                            // finite list of blocks and is finished once they
                            // are all in it, so leaving the button afterwards
                            // would be a control whose only answer is "nothing
                            // to do".
                            //
                            // Administrator only, matching the endpoint. This
                            // writes the file that decides what the whole site
                            // will and will not hand out, which is not the same
                            // authority as clearing a cache.
                            if (($server_rules_todo > 0) && $server_rules_scan['valid'] && ((int) $user['role'] === 0)) {

                                $job_action = '
                                <button type="button" class="pg-job-btn pg-job-btn-fix" id="server_config_repair"
                                        title="' . h(lang('Adds the missing rules to the web server configuration file in the web root. Nothing already in the file is changed, and a copy is kept first.')) . '"
                                        data-busy-label="' . h(lang('Writing')) . '"
                                        data-idle-label="' . h(lang('Fix')) . '"
                                        data-confirm-content="' . h(lang('The missing rules will be added to the web server configuration file. A copy of the current file is kept first.')) . '"
                                        data-failed-label="' . h(lang('The rules could not be written.')) . '">
                                    <i class="bi bi-wrench-adjustable"></i><span id="server_config_repair_state">' . h(lang('Fix')) . '</span>
                                </button>';

                                $job_panel = '
                                <div class="pg-job-panel pg-job-result d-none" id="server_config_repair_result">
                                    <div class="pg-job-panel-row"><span id="server_config_repair_message"></span></div>
                                </div>';
                            }

                        } elseif ($job_key === 'Write Permissions') {

                            // Offered only while something refuses, and only to
                            // an administrator: it changes who may write into the
                            // software directory. The confirmation says what the
                            // modes will be, because 0777 on a shared server is a
                            // decision the operator makes, not the button.
                            if (($job_check['state'] !== 'ok') && ((int) $user['role'] === 0)) {

                                $job_action = '
                                <button type="button" class="pg-job-btn pg-job-btn-fix" id="write_permissions_repair"
                                        title="' . h(lang('Sets every folder the web server cannot write into to 0777 and every such file to 0666, so that both the web server and your FTP or file manager user can replace them during an update. Entries that belong to another system user cannot be changed from here and are listed afterwards.')) . '"
                                        data-busy-label="' . h(lang('Fixing')) . '"
                                        data-idle-label="' . h(lang('Fix')) . '"
                                        data-confirm-content="' . h(lang('The folders and files the web server cannot write to will be set to 0777 / 0666. On a server shared with other accounts this lets them write there too; on a server that is yours alone it costs nothing.')) . '"
                                        data-failed-label="' . h(lang('The permissions could not be changed.')) . '">
                                    <i class="bi bi-wrench-adjustable"></i><span id="write_permissions_repair_state">' . h(lang('Fix')) . '</span>
                                </button>';

                                $job_panel = '
                                <div class="pg-job-panel pg-job-result d-none" id="write_permissions_repair_result">
                                    <div class="pg-job-panel-row"><span id="write_permissions_repair_message"></span></div>
                                </div>';
                            }

                        } elseif (isset($job_check['href']) && ($job_check['href'] !== '')) {

                            // The verb belongs to the state, not to the row.
                            // "Yazılım Güncelleme · 2026.4.4 · Güncelle" says
                            // an update is waiting when the middle of that line
                            // says the opposite -- the button is the loudest
                            // part of a row and it was contradicting the row.
                            // With nothing to do, the row still opens its
                            // screen, and the word for that is neutral.
                            if (($job_check['state'] == 'ok') || ($job_check['state'] == 'info')) {
                                $job_label = lang('View');
                            } elseif ($job_key === 'Last Backup') {
                                $job_label = lang('Backup');
                            } elseif ($job_key === 'Software Update') {
                                $job_label = lang('Update');
                            } else {
                                $job_label = lang('Go');
                            }

                            $job_action = '
                            <a href="' . h($job_check['href']) . '" class="pg-job-btn">
                                <span>' . h($job_label) . '</span><i class="bi bi-arrow-right"></i>
                            </a>';
                        }

                        $health_jobs[] = array(
                            'rank'   => isset($job_ranks[$job_check['state']]) ? $job_ranks[$job_check['state']] : 3,
                            'icon'   => $job_check['icon'],
                            'color'  => $job_check['color'],
                            // The full title, not the tile's short label. The
                            // column has the width for "Web Sunucusu Kuralları"
                            // and the point of moving these rows here was that
                            // "Sunucu kuralları" in nine pixels was not telling
                            // anybody what the row was about.
                            'name'   => $job_check['title'],
                            'note'   => $job_check['value'],
                            'hint'   => $job_check['message'],
                            'detail' => $job_rows,
                            'action' => $job_action,
                            'panel'  => $job_panel,
                        );
                    }

                    // ── Tools ───────────────────────────────────────────
                    //
                    // Three jobs rather than three readings. They used to be
                    // reachable only through settings.php: the table scan was a
                    // tile that screen injected into this card after load, cache
                    // purge and clean-up were rows in the Settings menu. The
                    // card is no longer on that screen, so the widget renders
                    // them itself -- which is what makes them permanent: every
                    // screen that draws widget 2 gets them, and nothing has to
                    // know to inject anything.
                    //
                    // No extra role gate. purge_cache.php and clean_up.php both
                    // call validate_area_access($user, 'manager'), and the two
                    // endpoints refuse role >= 3, which is the same bar this
                    // case already stands behind.
                    //
                    // Labels ride on the control as data-* rather than going
                    // into the global `translate` object: the only script that
                    // needs them is the one handling that control, and it has
                    // the control.
                    $health_jobs[] = array(
                        'rank'   => 2,
                        'icon'   => 'bi-database-gear',
                        'color'  => 'text-primary',
                        'name'   => lang('Database table scan'),
                        'note'   => lang('All tables'),
                        'hint'   => lang('A full scan of every table. This can take several minutes on a large database.'),
                        'detail' => '',
                        'action' => '
                            <button type="button" class="pg-job-btn" id="database_deep_check"
                                    data-busy-label="' . h(lang('Running')) . '"
                                    data-idle-label="' . h(lang('Run')) . '"
                                    data-failed-label="' . h(lang('The deep check could not be completed.')) . '">
                                <i class="bi bi-arrow-repeat"></i><span id="database_deep_check_state">' . h(lang('Run')) . '</span>
                            </button>',
                        'panel'  => '
                            <div class="pg-job-panel pg-job-result d-none" id="database_deep_check_result">
                                <div class="pg-job-panel-row"><span id="database_deep_check_message"></span></div>
                            </div>',
                    );

                    // The purge answers in place. It used to be a link to
                    // purge_cache.php, which clears the caches and then lands
                    // the operator on settings.php -- so pressing a button on a
                    // dashboard card took the card away and left the one number
                    // the purge had just invalidated unread. api.php runs the
                    // same pg_purge_caches() and the widget redraws itself.
                    $health_jobs[] = array(
                        'rank'   => 2,
                        'icon'   => 'bi-trash3',
                        'color'  => 'text-primary',
                        'name'   => lang('Server caches'),
                        'note'   => lang('OPcache and file caches'),
                        'hint'   => lang('All server-side caches will be cleared.'),
                        'detail' => '',
                        'action' => '
                            <button type="button" class="pg-job-btn" id="purge_cache"
                                    data-busy-label="' . h(lang('Clearing')) . '"
                                    data-idle-label="' . h(lang('Clear')) . '"
                                    data-confirm-content="' . h(lang('All server-side caches will be cleared.')) . '"
                                    data-failed-label="' . h(lang('The cache could not be cleared.')) . '">
                                <i class="bi bi-trash3"></i><span id="purge_cache_state">' . h(lang('Clear')) . '</span>
                            </button>',
                        'panel'  => '
                            <div class="pg-job-panel pg-job-result d-none" id="purge_cache_result">
                                <div class="pg-job-panel-row"><span id="purge_cache_message"></span></div>
                            </div>',
                    );

                    // The CA bundle. data/cacert.pem is the Mozilla root list an
                    // operator points CURL_CA_BUNDLE at when the host's own store
                    // is stale; Mozilla revises it several times a year, and a
                    // list that falls behind is why "cURL error 60" appears on a
                    // site that changed nothing. The row says what is installed
                    // and whether the running configuration actually reads it;
                    // the button fetches the current list from curl.se (or the
                    // CA_BUNDLE_SOURCE_URL mirror) and swaps it in. The library
                    // under includes/iyzipay-php/ keeps its own copy, which is
                    // integrity-hashed and only changes with a release, so the
                    // panel says so rather than leaving the operator to wonder
                    // why two files carry two dates.
                    $ca_bundle = pg_ca_bundle_status();

                    if (!$ca_bundle['exists']) {
                        $ca_bundle_note = lang('Missing');
                    } elseif ($ca_bundle['stamp'] > 0) {
                        $ca_bundle_note = pg_ca_bundle_date($ca_bundle['stamp']);
                    } else {
                        $ca_bundle_note = lang('No header');
                    }

                    if ($ca_bundle['mode'] === 'this') {
                        $ca_bundle_use = lang('CURL_CA_BUNDLE points at this file.');
                    } elseif ($ca_bundle['mode'] === 'other') {
                        $ca_bundle_use = lang(array(
                            'string' => 'CURL_CA_BUNDLE points at another file ({var:1}); the running configuration does not read this one.',
                            'vars'   => array($ca_bundle['configured']),
                        ));
                    } else {
                        $ca_bundle_use = lang('CURL_CA_BUNDLE is not set; connections are verified against the server\'s own certificate store.');
                    }

                    $ca_bundle_rows = '
                        <div class="pg-job-panel-row">
                            <span class="text-truncate">' . h(lang('File')) . '</span>
                            <span class="text-muted flex-shrink-0">' . h(SOFTWARE_DIRECTORY . '/data/cacert.pem') . '</span>
                        </div>
                        <div class="pg-job-panel-row">
                            <span class="text-truncate">' . h(lang('Mozilla data')) . '</span>
                            <span class="text-muted flex-shrink-0">' . h(($ca_bundle['stamp'] > 0) ? pg_ca_bundle_date($ca_bundle['stamp']) : $ca_bundle_note) . '</span>
                        </div>
                        <div class="pg-job-panel-row">
                            <span class="text-truncate">' . h(lang('Root certificates')) . '</span>
                            <span class="text-muted flex-shrink-0">' . h(number_format((int) $ca_bundle['count'])) . '</span>
                        </div>
                        <div class="pg-job-panel-row">
                            <span class="text-truncate">' . h(lang('Source')) . '</span>
                            <span class="text-muted flex-shrink-0">' . h(($ca_bundle['source'] !== '') ? $ca_bundle['source'] : lang('CA_BUNDLE_SOURCE_URL is not an https address')) . '</span>
                        </div>
                        <div class="pg-job-panel-row">
                            <span class="text-muted">' . h($ca_bundle_use) . '</span>
                        </div>
                        <div class="pg-job-panel-row">
                            <span class="text-muted">' . h(lang('The payment library keeps its own copy of the bundle; that one is refreshed with software releases and is not touched here.')) . '</span>
                        </div>';

                    $ca_bundle_action = '';
                    $ca_bundle_panel = '';

                    if ((int) $user['role'] === 0) {
                        $ca_bundle_action = '
                            <button type="button" class="pg-job-btn" id="ca_bundle_update"
                                    data-busy-label="' . h(lang('Updating')) . '"
                                    data-idle-label="' . h(lang('Update')) . '"
                                    data-confirm-content="' . h(lang(array(
                                        'string' => 'The current Mozilla root certificate list will be downloaded from {var:1} and will replace data/cacert.pem. A file that is older than the installed one, or that is not a complete bundle, is refused.',
                                        'vars'   => array(($ca_bundle['source'] !== '') ? $ca_bundle['source'] : 'CA_BUNDLE_SOURCE_URL'),
                                    ))) . '"
                                    data-failed-label="' . h(lang('The CA bundle could not be updated.')) . '">
                                <i class="bi bi-arrow-repeat"></i><span id="ca_bundle_update_state">' . h(lang('Update')) . '</span>
                            </button>';

                        $ca_bundle_panel = '
                            <div class="pg-job-panel pg-job-result d-none" id="ca_bundle_update_result">
                                <div class="pg-job-panel-row"><span id="ca_bundle_update_message"></span></div>
                            </div>';
                    }

                    $health_jobs[] = array(
                        'rank'   => 2,
                        'icon'   => 'bi-shield-lock',
                        'color'  => 'text-primary',
                        'name'   => lang('CA certificate bundle'),
                        'note'   => $ca_bundle_note,
                        'hint'   => lang('The Mozilla root certificate list in data/cacert.pem, which outbound connections are verified against when CURL_CA_BUNDLE points at it. Update downloads the current list and replaces the file.'),
                        'detail' => $ca_bundle_rows,
                        'action' => $ca_bundle_action,
                        'panel'  => $ca_bundle_panel,
                    );

                    // ── Storage ─────────────────────────────────────────
                    //
                    // Three figures that are readings and not checks: there is
                    // no size at which a database, a backup folder or a file
                    // library is wrong -- a busy shop's are large because the
                    // shop works. Database size used to sit among the status
                    // chips as the one entry that could be neither right nor
                    // wrong, and the two figures it belongs with were nowhere
                    // on the card.
                    //
                    // The total is on the line and the breakdown is under it,
                    // because the question is nearly always "how much is this
                    // installation holding" and only sometimes "which part of
                    // it". Ranked last: it is the one row on this side with
                    // nothing to act on.
                    //
                    // Cached for six hours inside pg_storage_usage(), on its
                    // own clock rather than the status cache's ten minutes --
                    // the backup folder is a recursive walk and the dashboard
                    // must not pay for it dozens of times a day.
                    $storage = (isset($status['storage']) && is_array($status['storage']))
                        ? $status['storage']
                        : array();

                    $storage_lines = array(
                        'database' => lang('Database'),
                        'files'    => lang('Files'),
                        'backups'  => lang('Backups'),
                    );

                    $storage_rows = '';

                    foreach ($storage_lines as $storage_key => $storage_label) {

                        // A null is "could not be read" -- information_schema
                        // closed on this host, no backup folder, a files table
                        // older than its size column. Printing a confident zero
                        // for any of those would be worse than the line being
                        // absent.
                        if (!isset($storage[$storage_key]) || ($storage[$storage_key] === null)) {
                            continue;
                        }

                        $storage_rows .= '
                        <div class="pg-job-panel-row">
                            <span class="text-truncate">' . h($storage_label) . '</span>
                            <span class="text-muted flex-shrink-0">' . h(convert_bytes_to_string((float) $storage[$storage_key], 1)) . '</span>
                        </div>';
                    }

                    if ($storage_rows !== '') {
                        $health_jobs[] = array(
                            'rank'   => 4,
                            'icon'   => 'bi-hdd-stack',
                            'color'  => 'text-primary',
                            'name'   => lang('Storage'),
                            'note'   => convert_bytes_to_string((float) $storage['total'], 1),
                            'hint'   => lang('How much this installation is holding: the database, the file library and the backup folder.'),
                            'detail' => $storage_rows,
                            'action' => '',
                            'panel'  => '',
                        );
                    }

                    // Clean-up only lists what it found and waits for a second
                    // press on its own screen, so this row asks nothing.
                    $health_jobs[] = array(
                        'rank'   => 2,
                        'icon'   => 'bi-eraser',
                        'color'  => 'text-primary',
                        'name'   => lang('Clean Up'),
                        'note'   => lang('Obsolete files'),
                        'hint'   => lang('Tool to remove obsolete files and folders inside the software folder.'),
                        'detail' => '',
                        'action' => '
                            <a href="' . h(PATH . SOFTWARE_DIRECTORY . '/clean_up.php') . '" class="pg-job-btn">
                                <span>' . h(lang('Go')) . '</span><i class="bi bi-arrow-right"></i>
                            </a>',
                        'panel'  => '',
                    );

                    // Stable sort by rank. usort() is only guaranteed stable
                    // from PHP 8.0 and this file runs on 7.0, so the original
                    // position is carried into the comparison: without it the
                    // three tools -- which share a rank -- could swap places
                    // between two draws of the same card.
                    $job_order = array();

                    foreach ($health_jobs as $job_index => $health_job) {
                        $job_order[] = array((int) $health_job['rank'], (int) $job_index);
                    }

                    usort($job_order, function ($a, $b) {
                        if ($a[0] === $b[0]) {
                            return ($a[1] < $b[1]) ? -1 : 1;
                        }
                        return ($a[0] < $b[0]) ? -1 : 1;
                    });

                    $output_jobs = '';
                    $job_slot = 0;

                    foreach ($job_order as $job_entry) {

                        $health_job = $health_jobs[$job_entry[1]];
                        $job_slot++;

                        // A row that can expand is a button and the whole name
                        // side of it is the target; a row that cannot is a plain
                        // box. The action beside it is a sibling, never a child:
                        // a control inside the collapse toggle would fire the
                        // toggle on its way out, and the fix button would open a
                        // panel every time it was pressed.
                        if ($health_job['detail'] !== '') {

                            $job_id = 'system_status_job_' . $job_slot;

                            $job_open = '<button type="button" class="pg-job-open" data-bs-toggle="collapse"'
                                . ' data-bs-target="#' . $job_id . '" aria-expanded="false" aria-controls="' . $job_id . '"'
                                . ' title="' . h($health_job['hint']) . '">';
                            $job_close = '</button>';
                            $job_caret = '<i class="bi bi-chevron-down pg-job-caret"></i>';
                            $job_detail = '
                                <div class="collapse pg-job-slot" id="' . $job_id . '">
                                    <div class="pg-job-panel">' . $health_job['detail'] . '</div>
                                </div>';

                        } else {
                            $job_open = '<div class="pg-job-open" title="' . h($health_job['hint']) . '">';
                            $job_close = '</div>';
                            $job_caret = '';
                            $job_detail = '';
                        }

                        $output_jobs .= '
                        <div class="pg-job">
                            ' . $job_open . '
                                <i class="bi ' . h($health_job['icon']) . ' pg-job-icon ' . h($health_job['color']) . '"></i>
                                <span class="pg-job-name">' . h($health_job['name']) . '</span>
                                <span class="pg-job-note text-muted">' . h($health_job['note']) . '</span>
                                ' . $job_caret . '
                            ' . $job_close . '
                            ' . $health_job['action'] . '
                            ' . $job_detail . '
                            ' . $health_job['panel'] . '
                        </div>';
                    }

                    // Turning notifications on is the browser's business, not
                    // the site's: two operators looking at this dashboard on two
                    // computers get two different answers, and the server has no
                    // way to know either of them. So the row is written once,
                    // hidden, and the panel fills it in and reveals it - the
                    // same code that draws the button under the bell.
                    $output_jobs .= '
                        <div class="pg-job d-none" id="push_widget_row">
                            <div class="pg-job-open" title="' . h(lang('Notifications reach this browser even while the panel is closed. Every device decides for itself.')) . '">
                                <i class="bi bi-bell pg-job-icon text-primary"></i>
                                <span class="pg-job-name">' . h(lang('Notifications on this device')) . '</span>
                                <span class="pg-job-note text-muted" id="push_widget_note"></span>
                            </div>
                            <button type="button" class="pg-job-btn pg-push-toggle" id="push_widget_toggle">
                                <i class="bi bi-bell"></i><span class="pg-push-label">' . h(lang('Turn on')) . '</span>
                            </button>
                        </div>';

                    // ── The cells ───────────────────────────────────────
                    //
                    // Real elements in the drawing, animated where they are
                    // drawn. Nothing about the animation lives in <defs>: a
                    // browser rasterises pattern and mask CONTENT into a cached
                    // texture and stops refreshing it, so an animation put in
                    // there goes on running while the picture sits still. What
                    // IS in <defs> here is the clip, and a clip that never
                    // changes is free to cache.
                    //
                    // Written in FINAL coordinates -- the half turn that used to
                    // be done with a CSS transform on the group is done here, in
                    // the numbers. The transform was not wrong, but it made the
                    // group a different user space from everything else on the
                    // gauge, and a clip or a mask on a transformed element is
                    // resolved in the space the element was WRITTEN in, not the
                    // space it ends up in. That cost two rounds of a bar with
                    // squares only at its tips. With the rotation folded into
                    // the coordinates there is one space and no question. The
                    // gradient comes along: pg_health_gradient_m is the same
                    // gradient with its axis mirrored through the centre, which
                    // is what the rotation used to do to it.
                    //
                    // The squares are CUT by the bar rather than fitted inside
                    // it. Whole squares chosen by their centres read as tiles
                    // laid on top of an arc; squares clipped by the arc read as
                    // a field of them showing THROUGH it, which is the picture
                    // this is after -- and it lets the grid run right to both
                    // rims instead of stopping a square short of each.
                    $health_step = 2.6;
                    $health_size = 2.05;
                    $health_half = $health_size / 2;

                    // The drawn half, in final coordinates: an SVG circle starts
                    // at three o'clock and the stylesheet turns the arcs half a
                    // turn, so the fill runs from nine o'clock over the top.
                    $health_reach = M_PI * ($health_score / 100);
                    $health_t0    = M_PI;
                    $health_t1    = M_PI + $health_reach;

                    $health_p0x = 70 + (52 * cos($health_t0));
                    $health_p0y = 70 + (52 * sin($health_t0));
                    $health_p1x = 70 + (52 * cos($health_t1));
                    $health_p1y = 70 + (52 * sin($health_t1));

                    // Wider than the ten units the arc is drawn with, so the
                    // clip has whole squares to cut into halves at both rims.
                    // The halo layer is NOT clipped, so this margin is also how
                    // far the light spills past the bar.
                    $health_edge = 6.6;

                    $health_cells      = '';
                    $health_glow_cells = '';
                    $health_index      = 0;

                    for ($health_gy = 8.0; $health_gy <= 76.0; $health_gy += $health_step) {
                        for ($health_gx = 6.0; $health_gx <= 134.0; $health_gx += $health_step) {

                            $health_cx = $health_gx + $health_half;
                            $health_cy = $health_gy + $health_half;
                            $health_dx = $health_cx - 70;
                            $health_dy = $health_cy - 70;
                            $health_rr = sqrt(($health_dx * $health_dx) + ($health_dy * $health_dy));

                            // atan2 answers between -pi and pi; the fill runs
                            // from pi to pi + reach, so the negative half is
                            // brought round first.
                            $health_ang = atan2($health_dy, $health_dx);
                            if ($health_ang < 0) {
                                $health_ang += 2 * M_PI;
                            }

                            if (($health_ang >= $health_t0) && ($health_ang <= $health_t1)) {

                                $health_on = (abs($health_rr - 52) <= $health_edge);

                            } else {

                                // Past an end: inside the half disc the round
                                // cap paints there.
                                $health_c0x = $health_cx - $health_p0x;
                                $health_c0y = $health_cy - $health_p0y;
                                $health_c1x = $health_cx - $health_p1x;
                                $health_c1y = $health_cy - $health_p1y;

                                $health_on = ((($health_c0x * $health_c0x) + ($health_c0y * $health_c0y)) <= ($health_edge * $health_edge))
                                    || ((($health_c1x * $health_c1x) + ($health_c1y * $health_c1y)) <= ($health_edge * $health_edge));
                            }

                            if (!$health_on) {
                                continue;
                            }

                            $health_index++;

                            // Slow, and no two squares the same length, so the
                            // field never comes back round to a configuration it
                            // has already been in.
                            //
                            // Two and a half to five and a half seconds for one
                            // breath. It was four to nine, then three to seven:
                            // both ends have come down each time the layers
                            // under the squares got quieter, because the slower
                            // a square breathes the more contrast it needs for
                            // the change to be seen at all, and there is no
                            // reason to spend contrast on it now that the
                            // squares are the bar. Still slow enough that the
                            // eye reads a surface quietly alive rather than a
                            // thing blinking at it.
                            $health_cell_time = round(2.6 + (fmod($health_index * 0.75488, 1) * 3.0), 2);

                            // NEGATIVE delay. A positive one is a wait: until it
                            // elapses the square has no animated value and sits
                            // at full opacity, so the first seconds after a draw
                            // were a cascade of squares dropping in one after
                            // another -- a burst that has nothing to do with the
                            // animation, and that made everything after it look
                            // like the animation had died down. A negative delay
                            // starts the cycle already part-way through: every
                            // square is mid-breath from the first frame, and the
                            // phases are spread from the first frame too.
                            $health_cell_phase = round(fmod($health_index * 0.61803, 1) * $health_cell_time, 2);

                            $health_box = ' x="' . round($health_gx, 2) . '" y="' . round($health_gy, 2) . '"'
                                . ' width="' . $health_size . '" height="' . $health_size . '" rx="0.45"';

                            // No class on the square: the animation is selected
                            // through the group, which is a class name saved on
                            // each of three hundred elements.
                            $health_cells .= '<rect' . $health_box
                                . ' style="animation-delay:-' . $health_cell_phase . 's;animation-duration:' . $health_cell_time . 's"/>';

                            // The same square again for the halo below, without
                            // the style, so the animation selector cannot reach
                            // it -- see the note on that group.
                            $health_glow_cells .= '<rect' . $health_box . '/>';
                        }
                    }

                    // ── The shape that cuts them ────────────────────────
                    //
                    // The exact outline of the drawn bar: the outer rim, the
                    // round cap at the far end, the inner rim back, the round
                    // cap at the near end. A clipPath clips to the FILL of its
                    // contents, so a stroked circle is no use here -- that would
                    // clip to the disc, not to the band -- and the band has to be
                    // written out as a closed path.
                    $health_ax = round(70 + (57 * cos($health_t0)), 3);
                    $health_ay = round(70 + (57 * sin($health_t0)), 3);
                    $health_bx = round(70 + (57 * cos($health_t1)), 3);
                    $health_by = round(70 + (57 * sin($health_t1)), 3);
                    $health_ix = round(70 + (47 * cos($health_t1)), 3);
                    $health_iy = round(70 + (47 * sin($health_t1)), 3);
                    $health_jx = round(70 + (47 * cos($health_t0)), 3);
                    $health_jy = round(70 + (47 * sin($health_t0)), 3);

                    $health_clip = 'M' . $health_ax . ' ' . $health_ay
                        . 'A57 57 0 0 1 ' . $health_bx . ' ' . $health_by
                        . 'A5 5 0 0 1 ' . $health_ix . ' ' . $health_iy
                        . 'A47 47 0 0 0 ' . $health_jx . ' ' . $health_jy
                        . 'A5 5 0 0 1 ' . $health_ax . ' ' . $health_ay . 'Z';

                    // ── The groove ─────────────────────────────────────
                    //
                    // The unfilled half only. It used to be the WHOLE half turn,
                    // with the fill drawn over the top of it, and on a dark card
                    // that is invisible -- black under a lit bar is nothing. On a
                    // white one it is the theme's pale grey under every square,
                    // and on white, opacity is also loss of SATURATION: a square
                    // at the bottom of its breath was settling onto grey instead
                    // of onto the page and giving up its colour, taking the
                    // quieter half of the animation with it.
                    //
                    // A negative dash offset walks the pattern along the path, so
                    // the groove starts where the fill stops. The round cap it
                    // starts with reaches back under the fill's own cap, which
                    // covers it.
                    //
                    // At a hundred there is nothing to groove, and a zero-length
                    // dash with a round cap paints a dot -- so nothing is drawn.
                    $health_dash   = round($health_arc * $health_score / 100, 2);
                    $health_empty  = round($health_arc - $health_dash, 2);
                    $health_groove = ($health_empty > 0.5)
                        ? '<circle class="pg-health-track" cx="70" cy="70" r="52" stroke-width="10"'
                            . ' stroke-dasharray="' . $health_empty . ' 326.73"'
                            . ' stroke-dashoffset="-' . $health_dash . '"></circle>'
                        : '';

                    $output_data = '
                    <div class="card-body p-0 pg-split">
                        <div class="pg-split-half pg-split-pinned">
                            <div class="pg-health" style="' . $health_vars . '">
                                <div class="pg-health-dial">
                                    <svg class="pg-health-gauge" viewBox="8 8 124 72" role="img" aria-label="' . h(lang('Overall System Health')) . ' ' . (int) $health_score . '%">
                                        <defs>
                                            <!--
                                                userSpaceOnUse so the axis can sit
                                                on the drawn arc rather than on
                                                the circle, which is what keeps
                                                the whole sweep on the bar at
                                                every score. Every arc below --
                                                the wash, the two blurs, the
                                                hairline and the masked cells --
                                                is stroked with this one
                                                gradient, so all of them agree
                                                about what colour the bar is at
                                                any point along it.

                                                The stops arrive through custom
                                                properties instead of being
                                                written here: the same score
                                                needs different colours on a
                                                black card and a white one, and
                                                12P does not know which one it is
                                                rendering into. Both sets are
                                                declared on .pg-health and the
                                                stylesheet picks.
                                            -->
                                            <linearGradient id="pg_health_gradient" gradientUnits="userSpaceOnUse"
                                                            x1="122" y1="70" x2="' . $health_x2 . '" y2="' . $health_y2 . '">
                                                <stop offset="' . $health_marks[0] . '%" stop-color="var(--pg-health-s1)"/>
                                                <stop offset="' . $health_marks[1] . '%" stop-color="var(--pg-health-s2)"/>
                                                <stop offset="' . $health_marks[2] . '%" stop-color="var(--pg-health-s3)"/>
                                                <stop offset="' . $health_marks[3] . '%" stop-color="var(--pg-health-s4)"/>
                                            </linearGradient>
                                            <!--
                                                The same gradient with its axis
                                                mirrored through the centre of
                                                the dial. The arcs are turned
                                                half a turn by the stylesheet and
                                                take their gradient round with
                                                them; the squares are written
                                                already turned, so theirs has to
                                                be turned here instead. Same
                                                colours, same stops, same
                                                direction on screen.
                                            -->
                                            <linearGradient id="pg_health_gradient_m" gradientUnits="userSpaceOnUse"
                                                            x1="18" y1="70" x2="' . $health_mx2 . '" y2="' . $health_my2 . '">
                                                <stop offset="' . $health_marks[0] . '%" stop-color="var(--pg-health-s1)"/>
                                                <stop offset="' . $health_marks[1] . '%" stop-color="var(--pg-health-s2)"/>
                                                <stop offset="' . $health_marks[2] . '%" stop-color="var(--pg-health-s3)"/>
                                                <stop offset="' . $health_marks[3] . '%" stop-color="var(--pg-health-s4)"/>
                                            </linearGradient>
                                            <!--
                                                The bar itself, as a shape rather
                                                than as a stroke, so the grid of
                                                squares can be cut by it.
                                            -->
                                            <clipPath id="pg_health_cell_clip" clipPathUnits="userSpaceOnUse">
                                                <path d="' . $health_clip . '"/>
                                            </clipPath>
                                            <!--
                                                The glow is a blurred copy of the arc
                                                rather than a drop-shadow, because a
                                                drop-shadow takes one colour and the arc
                                                has four: blurring the stroke itself
                                                keeps the glow the colour of whatever it
                                                is under. .pg-health-gauge is
                                                overflow:visible so the blur is not
                                                clipped at the viewBox edge.
                                            -->
                                            <filter id="pg_health_glow" x="-50%" y="-50%" width="200%" height="200%">
                                                <feGaussianBlur stdDeviation="4.5"/>
                                            </filter>
                                            <!--
                                                And a wider one under it. One blur
                                                gives an outline; two, at different
                                                radii, give the falloff that reads
                                                as light.
                                            -->
                                            <filter id="pg_health_bloom" x="-70%" y="-70%" width="240%" height="240%">
                                                <feGaussianBlur stdDeviation="9"/>
                                            </filter>
                                            <!--
                                                And a tight one for the squares
                                                themselves. The two above are the
                                                light AROUND the bar and are cut
                                                off by the panel; this is the
                                                light BETWEEN the squares, which
                                                is what makes a lit panel of them
                                                rather than a row of tiles with
                                                the card showing through the
                                                gaps. Small radius on purpose: at
                                                the radius the arc glows use, the
                                                squares merge and the matrix is
                                                gone.
                                            -->
                                            <filter id="pg_health_cell_glow" x="-40%" y="-40%" width="180%" height="180%">
                                                <feGaussianBlur stdDeviation="2.1"/>
                                            </filter>
                                        </defs>
                                        ' . $health_groove . '
                                        <circle class="pg-health-bloom" cx="70" cy="70" r="52" stroke-width="10" stroke="url(#pg_health_gradient)"
                                                filter="url(#pg_health_bloom)"
                                                stroke-dasharray="' . round($health_arc * $health_score / 100, 2) . ' 326.73"></circle>
                                        <circle class="pg-health-glow" cx="70" cy="70" r="52" stroke-width="10" stroke="url(#pg_health_gradient)"
                                                filter="url(#pg_health_glow)"
                                                stroke-dasharray="' . round($health_arc * $health_score / 100, 2) . ' 326.73"></circle>
                                        <!--
                                            The halo, and it is NOT clipped: this is
                                            the light the squares throw PAST the bar,
                                            which is what makes them read as a field
                                            showing through it rather than as tiles
                                            sitting on it.

                                            It does not breathe with them either. A
                                            filter is recomputed whenever what it
                                            filters changes, so a blurred copy of
                                            three hundred animating squares would
                                            re-run a blur over the whole bar every
                                            frame. Held still, the blur is computed
                                            once and reused, and what it gives --
                                            light in the gaps -- is ambient anyway:
                                            it is the panel being lit, not the pixel.
                                        -->
                                        <g class="pg-health-cell-glow" fill="url(#pg_health_gradient_m)"
                                           filter="url(#pg_health_cell_glow)">' . $health_glow_cells . '</g>
                                        <g class="pg-health-cells" fill="url(#pg_health_gradient_m)"
                                           clip-path="url(#pg_health_cell_clip)">' . $health_cells . '</g>
                                        <!--
                                            A second, hairline arc inside the thick one.
                                            Its own radius means its own circumference,
                                            so the fraction is recomputed rather than
                                            reused: 2*pi*42 = 263.89, half of it 131.95.
                                        -->
                                        <circle class="pg-health-inner" cx="70" cy="70" r="42" stroke-width="2" stroke="url(#pg_health_gradient)"
                                                stroke-dasharray="' . round(131.95 * $health_score / 100, 2) . ' 263.89"></circle>
                                    </svg>
                                    <div class="pg-health-readout">
                                        <div class="pg-health-score">' . (int) $health_score . '<span>%</span></div>
                                        <div class="pg-health-label">' . lang('Overall System Health') . '</div>
                                    </div>
                                </div>
                            </div>
                            <div class="pg-checks pg-split-scroll">
                                ' . $output_checks . '
                            </div>
                        </div>
                        <div class="pg-split-half pg-split-pinned">
                            <div class="pg-jobs-heading">' . lang('Maintenance and tools') . '</div>
                            <div class="pg-jobs pg-split-scroll">
                                ' . $output_jobs . '
                            </div>
                        </div>
                    </div>';

                    $response = array(
                        'status' => 'success',
                        'message' => 'Action Success',
                        'data' => $output_data,
                    );
                    echo encode_json($response);
                    exit();
                    break;
                } else {
                    $response = array(
                        'status' => 'error',
                        'message' => 'Access denied'
                    );
                    echo encode_json($response);
                    exit();
                    break;
                }
            case '3':
                if ((ECOMMERCE === true) and (($user['role'] < 3) or USER_MANAGE_ECOMMERCE or USER_MANAGE_ECOMMERCE_REPORTS)) {

                    // ── Orders ───────────────────────────────────────────────
                    //
                    // One aggregate rather than the whole table. The body that
                    // stood here selected every completed order and bucketed
                    // them in a PHP foreach, so a shop with fifty thousand
                    // orders moved fifty thousand rows across the wire on every
                    // dashboard load in order to arrive at eight numbers.
                    //
                    // The buckets are anchored to midnight rather than to
                    // time(). "Today" was (time() - order_date) < 86400 -- the
                    // last twenty-four hours -- so at three in the afternoon it
                    // counted yesterday afternoon as today. Anchoring also
                    // keeps the rows nested: today always sits inside the week
                    // and the week inside the month, which is what a reader
                    // assumes when four periods are stacked.
                    $midnight   = strtotime(date('Y-m-d'));
                    $from_week  = $midnight - (6 * 86400);
                    $from_month = $midnight - (29 * 86400);

                    $order_summary = db_item(
                        "SELECT
                            COUNT(*) AS all_count,
                            COALESCE(SUM(orders.total), 0) AS all_total,
                            COALESCE(MAX(orders.order_date), 0) AS last_order_date,
                            COALESCE(SUM(CASE WHEN orders.order_date >= $midnight THEN 1 ELSE 0 END), 0) AS today_count,
                            COALESCE(SUM(CASE WHEN orders.order_date >= $midnight THEN orders.total ELSE 0 END), 0) AS today_total,
                            COALESCE(SUM(CASE WHEN orders.order_date >= $from_week THEN 1 ELSE 0 END), 0) AS week_count,
                            COALESCE(SUM(CASE WHEN orders.order_date >= $from_week THEN orders.total ELSE 0 END), 0) AS week_total,
                            COALESCE(SUM(CASE WHEN orders.order_date >= $from_month THEN 1 ELSE 0 END), 0) AS month_count,
                            COALESCE(SUM(CASE WHEN orders.order_date >= $from_month THEN orders.total ELSE 0 END), 0) AS month_total
                        FROM orders
                        WHERE orders.status IN ('complete', 'exported')"
                    );

                    // ── Stock ────────────────────────────────────────────────
                    //
                    // Same treatment: the old body read every inventory-tracked
                    // product in order to add up three numbers.
                    //
                    // The out-of-stock count deliberately does not filter on
                    // inventory, so that it agrees with the Out of Stock
                    // Products card, which does not filter on it either. A
                    // product can be marked out of stock without inventory
                    // tracking, and the operator who reads both cards should
                    // not have to know that.
                    $stock_summary = db_item(
                        "SELECT
                            COALESCE(SUM(CASE WHEN products.inventory = 1 THEN 1 ELSE 0 END), 0) AS product_count,
                            COALESCE(SUM(CASE WHEN products.inventory = 1 THEN products.inventory_quantity ELSE 0 END), 0) AS quantity_total,
                            COALESCE(SUM(CASE WHEN products.inventory = 1 THEN products.price * products.inventory_quantity ELSE 0 END), 0) AS stock_value,
                            COALESCE(SUM(CASE WHEN products.out_of_stock = '1' THEN 1 ELSE 0 END), 0) AS out_of_stock_count
                        FROM products"
                    );

                    $all_count          = (int) ($order_summary['all_count'] ?? 0);
                    $all_total          = (float) ($order_summary['all_total'] ?? 0);
                    $last_order_date    = (int) ($order_summary['last_order_date'] ?? 0);
                    $product_count      = (int) ($stock_summary['product_count'] ?? 0);
                    $quantity_total     = (int) ($stock_summary['quantity_total'] ?? 0);
                    $stock_value        = (float) ($stock_summary['stock_value'] ?? 0);
                    $out_of_stock_count = (int) ($stock_summary['out_of_stock_count'] ?? 0);

                    // Separators are given explicitly. number_format() with
                    // only a precision falls back to English ones, which is how
                    // the piece count used to read "9,986" on the same line as
                    // a stock value of "798.380,70". The rest of this file
                    // passes ',' and '.' the same way.
                    $pg_money = function ($cents) {
                        return BASE_CURRENCY_SYMBOL . number_format($cents / 100, 2, ',', '.');
                    };
                    $pg_count = function ($number) {
                        return number_format($number, 0, ',', '.');
                    };

                    // ── Head: stock on one line, two facts under it ──────────
                    $output_stock_sub = $pg_count($product_count) . ' ' . lang('Product(s)')
                        . ' <span class="pg-ec-dot">&middot;</span> '
                        . $pg_count($quantity_total) . ' ' . lang('Piece(s)');

                    // Only when there is something to act on. A steady "0
                    // tükendi" is a word the eye learns to skip, and then the
                    // day it says 3 it gets skipped too.
                    if ($out_of_stock_count > 0) {
                        $output_stock_sub .= ' <span class="pg-ec-dot">&middot;</span> '
                            . '<span class="pg-ec-warn">'
                            . lang(array(
                                'string' => '{var:1} out of stock',
                                'vars' => $pg_count($out_of_stock_count),
                            ))
                            . '</span>';
                    }

                    // Average basket and the age of the last order: the two
                    // questions the removed tiles could not answer. The second
                    // one is the cheapest "is this shop still trading?" signal
                    // on the card -- a period row reading zero cannot tell a
                    // quiet Tuesday from a checkout that has been broken since
                    // Friday. get_relative_time() switches to a plain date past
                    // a month, which is the right answer at that distance.
                    $output_average_order = ($all_count > 0)
                        ? $pg_money($all_total / $all_count)
                        : '&mdash;';
                    $output_last_order = ($last_order_date > 0)
                        ? h(get_relative_time(array('timestamp' => $last_order_date, 'format' => 'plain_text')))
                        : '&mdash;';

                    // ── Period rows ──────────────────────────────────────────
                    //
                    // "Last 1 Year" is gone. On any shop older than a year it
                    // says the same thing as the all-time figure, and on a shop
                    // whose trade stopped a year ago it said 0 while the card
                    // above it showed a lifetime total -- the state this dev
                    // install is in. All Time carries the order count and the
                    // lifetime total that used to need a tile of their own.
                    $periods = array(
                        array(
                            'label' => lang('Today'),
                            'icon' => 'bi-sun-fill',
                            'color' => '#f59e0b',
                            'count' => (int) ($order_summary['today_count'] ?? 0),
                            'total' => (float) ($order_summary['today_total'] ?? 0),
                        ),
                        array(
                            'label' => lang('Last 1 Week'),
                            'icon' => 'bi-calendar-week',
                            'color' => '#3b82f6',
                            'count' => (int) ($order_summary['week_count'] ?? 0),
                            'total' => (float) ($order_summary['week_total'] ?? 0),
                        ),
                        array(
                            'label' => lang('Last 1 Month'),
                            'icon' => 'bi-calendar-month',
                            'color' => '#8b5cf6',
                            'count' => (int) ($order_summary['month_count'] ?? 0),
                            'total' => (float) ($order_summary['month_total'] ?? 0),
                        ),
                        array(
                            'label' => lang('All Time'),
                            'icon' => 'bi-infinity',
                            'color' => '#10b981',
                            'count' => $all_count,
                            'total' => $all_total,
                            'total_row' => true,
                        ),
                    );

                    $output_period_rows = '';
                    foreach ($periods as $p) {
                        // Each period already has its own colour in $periods,
                        // and the four of them are a scale rather than one card
                        // accent, so they keep it.
                        $output_period_rows .= pg_widget_row(array(
                            'small' => true,
                            'muted' => (($p['count'] == 0) && empty($p['total_row'])),
                            'class' => (!empty($p['total_row']) ? 'pg-row-total' : ''),
                            'color' => $p['color'],
                            'badge' => '<i class="bi ' . $p['icon'] . '"></i>',
                            'name'  => $p['label'],
                            'aside' => '<span class="badge rounded-pill me-1" style="background:' . $p['color'] . '22;color:' . $p['color'] . '">' . $pg_count($p['count']) . '</span>'
                                . '<span class="fw-semibold">' . ($p['count'] > 0 ? $pg_money($p['total']) : '&mdash;') . '</span>',
                        ));
                    }

                    $output_data = '
                    <div class="card-body p-0 overflow-x-hidden overflow-y-auto">
                        <div class="pg-ec-head">
                            <div class="pg-ec-line">
                                <span class="pg-ec-val">' . $pg_money($stock_value) . '</span>
                                <span class="pg-ec-lbl text-muted">' . lang('Stock Value') . '</span>
                            </div>
                            <div class="pg-ec-sub text-muted">' . $output_stock_sub . '</div>
                        </div>
                        <div class="pg-ec-facts">
                            <div class="pg-ec-fact">
                                <span class="text-muted">' . lang('Average order') . '</span>
                                <b>' . $output_average_order . '</b>
                            </div>
                            <div class="pg-ec-fact">
                                <span class="text-muted">' . lang('Last order') . '</span>
                                <b>' . $output_last_order . '</b>
                            </div>
                        </div>
                        <div class="border-top mx-2 mb-1"></div>
                        <div class="pg-list">' . $output_period_rows . '</div>
                    </div>';

                    //return success json output
                    $response = array(
                        'status' => 'success',
                        'message' => 'Action Success',
                        'data' => $output_data,
                    );
                    echo encode_json($response);
                    exit();
                    break;
                } else {
                    $response = array(
                        'status' => 'error',
                        'message' => 'Access denied'
                    );
                    echo encode_json($response);
                    exit();
                    break;
                }

            case '4':
                // ── Online Engagement ───────────────────────────────────
                //
                // Was "Whois Online", a flat list of accounts. It now answers
                // the question that list only half answered: is anything
                // happening right now that needs a person?
                //
                // Order is deliberate. Counts first, then anyone waiting for
                // a reply, then who is around to give one, then who is not.
                // A conversation with an unread message is the only row here
                // that costs the business something while it is ignored, so
                // it sits above the roster rather than inside it.
                $user = validate_user();

                if ($user['role'] < 3) {

                    $eg_now = time();

                    // 20 minutes, the same span the two counters use. The
                    // roster is split on this boundary rather than on the
                    // 120-second "online" threshold so the tile and the list
                    // beneath it cannot disagree — a tile reading 3 above a
                    // list showing 1 reads as a fault, not as two different
                    // measurements.
                    $eg_recent_seconds = 1200;

                    $eg_active_visitors = (int) db_value(
                        "SELECT COUNT(*) FROM visitors
                         WHERE stop_timestamp >= '" . e($eg_now - $eg_recent_seconds) . "'");

                    $eg_online_users_count = (int) db_value(
                        "SELECT COUNT(*) FROM user
                         WHERE user_online_timestamp >= '" . e($eg_now - $eg_recent_seconds) . "'");

                    // ── Waiting conversations ───────────────────────────
                    //
                    // Same scope as the chat panel's own list
                    // (pg_chat_conversation_list): mine as either side, plus
                    // every site conversation for role 0 administrators.
                    //
                    // "Waiting" is status open AND a message arrived after my
                    // read cursor. Which cursor applies depends on my side,
                    // so the side test and the cursor test travel together in
                    // each branch — comparing against the wrong cursor would
                    // report my own last message as something I owe a reply
                    // to.
                    $eg_chat_rows = array();
                    $eg_chat_available = false;

                    require_once(dirname(__FILE__) . '/chat.php');

                    if (pg_chat_enabled()) {

                        $eg_chat_available = true;
                        $eg_me = (int) $user['id'];

                        $eg_waiting_where =
                            "((c.target_user_id = '" . e($eg_me) . "' AND c.last_message_id > c.target_last_read_id)
                              OR (c.initiator_user_id = '" . e($eg_me) . "' AND c.last_message_id > c.initiator_last_read_id)";

                        if ((int) $user['role'] === 0) {
                            $eg_waiting_where .=
                                " OR (c.channel = 'site' AND c.last_message_id > c.target_last_read_id)";
                        }

                        $eg_waiting_where .= ')';

                        // Oldest first: the visitor who has been waiting
                        // longest is the one about to give up. Newest-first
                        // would bury exactly that row.
                        $eg_chat_rows = db_items(
                            "SELECT
                                c.id,
                                c.channel,
                                c.party_name,
                                c.last_message_at,
                                c.last_message_preview,
                                u.user_username AS peer_username,
                                contacts.first_name AS first_name,
                                contacts.last_name AS last_name
                             FROM chat_conversations c
                             LEFT JOIN user u
                                ON u.user_id = IF(c.initiator_user_id = '" . e($eg_me) . "', c.target_user_id, c.initiator_user_id)
                             LEFT JOIN contacts ON contacts.id = u.user_contact
                             WHERE c.status = 'open'
                               AND " . $eg_waiting_where . "
                             ORDER BY c.last_message_at ASC, c.id ASC
                             LIMIT 5");
                    }

                    $eg_waiting_html = '';

                    foreach ($eg_chat_rows as $eg_chat) {

                        if ($eg_chat['channel'] == 'site') {
                            $eg_title = ($eg_chat['party_name'] != '')
                                ? $eg_chat['party_name']
                                : lang('Visitor') . ' #' . (int) $eg_chat['id'];
                        } else {
                            $eg_title = pg_chat_display_name(
                                isset($eg_chat['first_name']) ? $eg_chat['first_name'] : '',
                                isset($eg_chat['last_name']) ? $eg_chat['last_name'] : '',
                                isset($eg_chat['peer_username']) ? $eg_chat['peer_username'] : '');
                        }

                        $eg_waited = $eg_now - (int) $eg_chat['last_message_at'];

                        // Past a quarter of an hour with no answer this is no
                        // longer a notification, it is a lost conversation.
                        $eg_wait_class = ($eg_waited >= 900) ? 'text-danger' : 'text-warning';

                        $eg_waiting_html .= '
                        <div class="d-flex align-items-center px-2 py-1 pointer"
                             onclick="if (window.pgChatOpenConversation) { window.pgChatOpenConversation(' . (int) $eg_chat['id'] . '); }">
                            <div class="me-2 flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle"
                                 style="width:34px;height:34px;background:rgba(245,158,11,.12)">
                                <i class="bi bi-chat-dots" style="color:#f59e0b"></i>
                            </div>
                            <div class="flex-grow-1 overflow-hidden">
                                <div class="d-flex justify-content-between align-items-center" style="gap:6px">
                                    <span class="text-truncate fw-semibold" style="font-size:13px">' . h($eg_title) . '</span>
                                    <span class="' . $eg_wait_class . ' flex-shrink-0" style="font-size:11px">' . get_relative_time(array('timestamp' => (int) $eg_chat['last_message_at'])) . '</span>
                                </div>
                                <small class="text-muted text-truncate d-block">' . h($eg_chat['last_message_preview']) . '</small>
                            </div>
                        </div>';
                    }

                    // ── Roster ──────────────────────────────────────────
                    //
                    // user_role >= own role: staff see their peers and the
                    // ranks below, never above. Unchanged from the widget
                    // this replaces.
                    $eg_roster = db_items(
                        "SELECT
                            user.user_id AS id,
                            user.user_role AS user_role,
                            user.user_username AS username,
                            user.user_online_timestamp AS user_online_timestamp,
                            contacts.image AS image,
                            contacts.file_id AS image_file_id,
                            contacts.first_name AS first_name,
                            contacts.last_name AS last_name,
                            files.name AS image_file_name
                         FROM user
                         LEFT JOIN contacts ON contacts.id = user.user_contact
                         LEFT JOIN files ON files.id = contacts.file_id
                         WHERE user.user_role >= '" . e((int) $user['role']) . "'
                         ORDER BY user.user_online_timestamp DESC
                         LIMIT 30");

                    $eg_online_html  = '';
                    $eg_offline_html = '';
                    $eg_online_shown = 0;
                    $eg_offline_shown = 0;

                    foreach ($eg_roster as $eg_person) {

                        $eg_seen = (int) $eg_person['user_online_timestamp'];

                        // Never signed in. There is no presence to report and
                        // no last-seen to show, so the row would be three
                        // blanks and an avatar.
                        if ($eg_seen < 1) {
                            continue;
                        }

                        $eg_presence = pg_chat_presence($eg_seen);
                        $eg_is_recent = (($eg_now - $eg_seen) < $eg_recent_seconds);

                        if ($eg_presence == 'online') {
                            $eg_dot = '#10b981';
                            $eg_status_label = lang('Online');
                            // Already-escaped HTML in every branch, because
                            // get_relative_time() emits a <time> element with
                            // an absolute-date tooltip. Escaping the whole
                            // thing at the print site would print the tag.
                            $eg_seen_text = h(lang('Online'));
                        } elseif ($eg_presence == 'away') {
                            $eg_dot = '#f59e0b';
                            $eg_status_label = lang('Away');
                            $eg_seen_text = get_relative_time(array('timestamp' => $eg_seen));
                        } else {
                            $eg_dot = '#a1a1a1';
                            $eg_status_label = lang('Offline');
                            $eg_seen_text = get_relative_time(array('timestamp' => $eg_seen));
                        }

                        switch ((int) $eg_person['user_role']) {
                            case 0:
                                $eg_role_color = '#ef4444';
                                break;
                            case 1:
                                $eg_role_color = '#8b5cf6';
                                break;
                            case 2:
                                $eg_role_color = '#3b82f6';
                                break;
                            default:
                                $eg_role_color = '#6b7280';
                                break;
                        }

                        $eg_avatar = pg_chat_avatar_src(
                            isset($eg_person['image']) ? $eg_person['image'] : '',
                            isset($eg_person['image_file_id']) ? $eg_person['image_file_id'] : 0,
                            isset($eg_person['image_file_name']) ? $eg_person['image_file_name'] : '');

                        $eg_name = pg_chat_display_name(
                            isset($eg_person['first_name']) ? $eg_person['first_name'] : '',
                            isset($eg_person['last_name']) ? $eg_person['last_name'] : '',
                            $eg_person['username']);

                        // Opening someone's account screen is an edit action:
                        // offered to administrators, and otherwise only
                        // downward through the hierarchy. Unchanged from the
                        // widget this replaces.
                        $eg_click = '';
                        $eg_pointer = '';

                        if (((int) $user['role'] === 0) || ((int) $user['role'] < (int) $eg_person['user_role'])) {
                            $eg_click = 'onclick="window.location.href=\'edit_user.php?id=' . (int) $eg_person['id'] . '\'"';
                            $eg_pointer = ' pointer';
                        }

                        // No chat handle against yourself, and none when the
                        // pair is not allowed to talk (pg_chat_can_pair: at
                        // least one side must be staff).
                        $eg_chat_icon = '';

                        if ($eg_chat_available
                            && ((int) $eg_person['id'] !== (int) $user['id'])
                            && pg_chat_can_pair($user['role'], $eg_person['user_role'])
                        ) {
                            $eg_chat_icon = '
                                <i class="bi bi-chat-text ms-1 flex-shrink-0" style="cursor:pointer"
                                   title="' . lang('Start Chat') . '"
                                   onclick="event.stopPropagation(); if (window.pgChatOpenWith) { window.pgChatOpenWith(' . (int) $eg_person['id'] . '); }"></i>';
                        }

                        $eg_row = '
                        <div class="d-flex align-items-center px-2 py-1' . $eg_pointer . '" ' . $eg_click . '>
                            <div class="position-relative me-2 flex-shrink-0">
                                <img src="' . h($eg_avatar) . '" class="rounded-circle" style="width:34px;height:34px;object-fit:cover;" alt="" />
                                <span class="position-absolute rounded-circle border border-2 border-white" title="' . h($eg_status_label) . '"
                                      style="width:10px;height:10px;bottom:0;right:0;background:' . $eg_dot . '"></span>
                            </div>
                            <div class="flex-grow-1 overflow-hidden">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-truncate me-1 fw-semibold" style="font-size:13px">' . h($eg_name) . '</span>
                                    <span class="badge rounded-pill flex-shrink-0" style="background:' . $eg_role_color . '22;color:' . $eg_role_color . ';font-size:10px">' . h(pg_chat_role_label($eg_person['user_role'])) . '</span>
                                    ' . $eg_chat_icon . '
                                </div>
                                <small class="text-muted">' . $eg_seen_text . '</small>
                            </div>
                        </div>';

                        if ($eg_is_recent) {
                            $eg_online_html .= $eg_row;
                            $eg_online_shown++;
                        } else {
                            $eg_offline_html .= $eg_row;
                            $eg_offline_shown++;
                        }
                    }

                    // Section heading. Printed only when the section has rows
                    // — an empty "Waiting for a reply" heading would read as a
                    // fault, and three empty headings would fill the card.
                    $eg_heading = function ($eg_label, $eg_count) {
                        return '
                        <div class="d-flex align-items-center justify-content-between px-2 pt-2 pb-1">
                            <span class="text-muted text-uppercase" style="font-size:10px;letter-spacing:.06em">' . h($eg_label) . '</span>
                            <span class="text-muted" style="font-size:10px">' . number_format($eg_count) . '</span>
                        </div>';
                    };

                    // Two panels rather than one column: the counts and the
                    // conversations still owed an answer are what the operator
                    // acts on, and who happens to be connected is context. They
                    // were competing for the same scroll before. .pg-split lays
                    // them side by side once the card is wide enough and stacks
                    // them again when it is not, so a one-track card still works.
                    $eg_chats_panel = '';

                    if ($eg_waiting_html !== '') {
                        $eg_chats_panel = $eg_heading(lang('Waiting for a reply'), count($eg_chat_rows)) . $eg_waiting_html;
                    }

                    if ($eg_chats_panel === '') {
                        $eg_chats_panel = '
                        <div class="text-center py-3">
                            <i class="bi bi-chat-left-dots d-block mb-2" style="font-size:20px;opacity:.35"></i>
                            <p class="text-muted mb-0" style="font-size:12px">' . lang('Nobody is waiting for a reply.') . '</p>
                        </div>';
                    }

                    $eg_people_panel = '';

                    if ($eg_online_html !== '') {
                        $eg_people_panel .= $eg_heading(lang('Online'), $eg_online_shown) . $eg_online_html;
                    }

                    if ($eg_offline_html !== '') {
                        $eg_people_panel .= $eg_heading(lang('Offline'), $eg_offline_shown) . $eg_offline_html;
                    }

                    if ($eg_people_panel === '') {
                        $eg_people_panel = '
                        <div class="text-center py-4">
                            <i class="bi bi-person-dash d-block mb-2" style="font-size:22px;opacity:.35"></i>
                            <p class="text-muted mb-0" style="font-size:12px">' . lang(array('string' => 'There is no {var:1} right now.', 'vars' => lang('Online User'))) . '</p>
                        </div>';
                    }

                    $output_data = '
                    <div class="card-body p-0 pg-split">
                        <div class="pg-split-half">
                            <div class="d-flex flex-column gap-1 p-2">
                                <div class="pg-eg-stat" style="--pg-eg-ink:#10b981">
                                    <i class="bi bi-people-fill"></i>
                                    <span class="pg-eg-stat-value">' . number_format($eg_active_visitors) . '</span>
                                    <span class="pg-eg-stat-label">' . lang('Active Visitors') . '</span>
                                    <span class="pg-eg-stat-window">' . lang('Last 20 min') . '</span>
                                </div>
                                <div class="pg-eg-stat" style="--pg-eg-ink:#3b82f6">
                                    <i class="bi bi-person-gear"></i>
                                    <span class="pg-eg-stat-value">' . number_format($eg_online_users_count) . '</span>
                                    <span class="pg-eg-stat-label">' . lang('Online Users') . '</span>
                                    <span class="pg-eg-stat-window">' . lang('Last 20 min') . '</span>
                                </div>
                            </div>
                            <div class="border-top mx-2 mb-1">
                                ' . $eg_chats_panel . '
                            </div>
                        </div>
                        <div class="pg-split-half">
                            ' . $eg_people_panel . '
                        </div>
                    </div>';

                    $response = array(
                        'status' => 'success',
                        'message' => 'Action Success',
                        'data' => $output_data,
                    );
                    echo encode_json($response);
                    exit();
                    break;
                } else {
                    $response = array(
                        'status' => 'error',
                        'message' => 'Access denied'
                    );
                    echo encode_json($response);
                    exit();
                    break;
                }
            case '5':
                if (($user['role'] < 3) || ($user['manage_visitors'] == true)) {
                    $now = time();
                    $today_start = strtotime(date('Y-m-d'));
                    $yesterday_start = $today_start - 86400;
                    $month_start = mktime(0, 0, 0, (int) date('n'), 1, (int) date('Y'));
                    $week_ago = $now - 7 * 86400;
                    $two_weeks_ago = $now - 14 * 86400;

                    // Get current hour (0-23) to limit today's data display
                    $current_hour = (int) date('G');

                    $vs5_no_data = pg_widget_empty('bi-graph-up', lang('There is not enough data yet.'));

                    // Every figure below is read from the hourly rollups
                    // rather than counted out of the raw visitors table.
                    //
                    // The old version ran eleven aggregates over `visitors`,
                    // grouping on HOUR(FROM_UNIXTIME(start_timestamp)) — an
                    // expression, so no index applied and each one built a
                    // temporary table. One of them covered twelve months. At
                    // 100,000-200,000 visits a day that is tens of millions of
                    // rows scanned to draw three small charts, which is where
                    // the twenty to thirty second load came from. Worse, on
                    // MyISAM those scans hold a read lock, so every visitor
                    // arriving on the site queued behind an open dashboard.
                    //
                    // The rollups hold 24 rows per day whatever the traffic.
                    $today_date     = date('Y-m-d');
                    $yesterday_date = date('Y-m-d', $yesterday_start);

                    // Carry the backfill forward a slice at a time. This is
                    // the one screen that wants the historical summaries, it
                    // is reached by administrators only, and the budget is
                    // small enough not to be felt. On a fresh upgrade whose
                    // backfill was cut short by a server request timeout, the
                    // history fills in over the next few dashboard loads
                    // instead of needing anyone to restart anything.
                    $vs5_backfill = false;
                    if (function_exists('pg_visitor_backfill_step')) {
                        $vs5_backfill = pg_visitor_backfill_step(3);
                    }

                    // ── PANEL 1 : Hourly peaks — today vs yesterday ────────────────────────
                    $stats_2d = pg_visitor_stats_range($yesterday_date, $today_date);

                    $h_today = array_fill(0, 24, 0);
                    $h_yest  = array_fill(0, 24, 0);

                    // Page views are carried alongside the visitor counts.
                    //
                    // The plotted line counts sessions that STARTED in an
                    // hour, which is not the same as activity during that
                    // hour: someone who arrives at 14:00 and reads ten pages
                    // at 15:00 leaves 15:00 with no new session but ten views.
                    // The tooltip names the busiest content of the hour, so
                    // without this figure the reader sees a named article
                    // with ten views sitting above a chart value of zero and
                    // reasonably concludes something is broken.
                    $h_today_views = array_fill(0, 24, 0);
                    $h_yest_views  = array_fill(0, 24, 0);

                    if (isset($stats_2d[$today_date])) {
                        foreach ($stats_2d[$today_date] as $hh => $vals) {
                            $h_today[(int) $hh]       = $vals['visitors'];
                            $h_today_views[(int) $hh] = $vals['page_views'];
                        }
                    }
                    if (isset($stats_2d[$yesterday_date])) {
                        foreach ($stats_2d[$yesterday_date] as $hh => $vals) {
                            $h_yest[(int) $hh]       = $vals['visitors'];
                            $h_yest_views[(int) $hh] = $vals['page_views'];
                        }
                    }

                    $kpi_today = array_sum($h_today);
                    $kpi_yesterday = array_sum($h_yest);

                    // Separate labels for today (limited to current hour) and yesterday (full 24 hours)
                    $h_today_labels_js = '';
                    $h_today_js = '';
                    $h_today_views_js = '';
                    for ($h = 0; $h <= $current_hour; $h++) {
                        $h_today_labels_js .= '"' . str_pad($h, 2, '0', STR_PAD_LEFT) . ':00",';
                        $h_today_js .= $h_today[$h] . ',';
                        $h_today_views_js .= $h_today_views[$h] . ',';
                    }

                    $h_yest_labels_js = '';
                    $h_yest_js = '';
                    $h_yest_views_js = '';
                    for ($h = 0; $h < 24; $h++) {
                        $h_yest_labels_js .= '"' . str_pad($h, 2, '0', STR_PAD_LEFT) . ':00",';
                        $h_yest_js .= $h_yest[$h] . ',';
                        $h_yest_views_js .= $h_yest_views[$h] . ',';
                    }

                    // Busiest content per hour, for the chart tooltip.
                    //
                    // This is what the widget was asked to show and could not:
                    // the tooltip now names the article or product, not the
                    // page template that displayed it. Rows recorded before
                    // this change still show the page name, because the item
                    // identity was never written down and cannot be recovered.
                    $vs5_hour_label = function ($row) {
                        if (!$row) return null;
                        return array('name' => h($row['name']), 'cnt' => (int) $row['cnt']);
                    };

                    $h_today_pages = array_map($vs5_hour_label, pg_visitor_top_content_by_hour($today_date));
                    $h_yest_pages  = array_map($vs5_hour_label, pg_visitor_top_content_by_hour($yesterday_date));

                    // Slice today pages to match current hour
                    $h_today_pages = array_slice($h_today_pages, 0, $current_hour + 1);

                    // Top content rows under each chart.
                    $vs5_top = function ($from, $to) {
                        $rows = pg_visitor_top_content($from, $to, 1);
                        if (empty($rows)) return null;
                        return array('name' => h($rows[0]['label']), 'cnt' => (int) $rows[0]['views']);
                    };

                    $tp_today = $vs5_top($today_date, $today_date);
                    $tp_yest  = $vs5_top($yesterday_date, $yesterday_date);

                    // ── PANEL 2 : Daily peaks — this 7 days vs previous 7 days ───────────
                    // Daily totals folded up from the hourly rollup.
                    $vs5_daily = function ($from_ts, $to_ts) {
                        $out  = array();
                        $rows = pg_visitor_stats_range(date('Y-m-d', $from_ts), date('Y-m-d', $to_ts));
                        foreach ($rows as $d => $hours) {
                            $sum = 0;
                            foreach ($hours as $vals) $sum += $vals['visitors'];
                            $out[$d] = $sum;
                        }
                        return $out;
                    };

                    // Bounded to exactly the seven dates each chart plots.
                    //
                    // The rollup is keyed by date where the old query filtered
                    // on a timestamp, so a range expressed as "the last seven
                    // times 86,400 seconds" would pull in part of an eighth
                    // day and the headline figure would not match the bars
                    // underneath it.
                    $w_tw_map = $vs5_daily($now - 6 * 86400, $now);
                    $w_lw_map = $vs5_daily($week_ago - 6 * 86400, $week_ago);

                    $w_tw_labels = $w_tw_data = $w_lw_labels = $w_lw_data = '';
                    $has_tw = $has_lw = false;
                    for ($i = 6; $i >= 0; $i--) {
                        $ts_tw = $now - $i * 86400;
                        $ts_lw = $week_ago - $i * 86400;
                        $d_tw = date('Y-m-d', $ts_tw);
                        $d_lw = date('Y-m-d', $ts_lw);
                        $c_tw = isset($w_tw_map[$d_tw]) ? $w_tw_map[$d_tw] : 0;
                        $c_lw = isset($w_lw_map[$d_lw]) ? $w_lw_map[$d_lw] : 0;
                        if ($c_tw > 0)
                            $has_tw = true;
                        if ($c_lw > 0)
                            $has_lw = true;
                        $w_tw_labels .= '"' . date('d', $ts_tw) . ' ' . lang(date('M', $ts_tw)) . '",';
                        $w_lw_labels .= '"' . date('d', $ts_lw) . ' ' . lang(date('M', $ts_lw)) . '",';
                        $w_tw_data .= $c_tw . ',';
                        $w_lw_data .= $c_lw . ',';
                    }
                    $kpi_tw = array_sum($w_tw_map);
                    $kpi_lw = array_sum($w_lw_map);

                    // Top content — this week / last week
                    $tp_tw = $vs5_top(date('Y-m-d', $now - 6 * 86400), date('Y-m-d', $now));
                    $tp_lw = $vs5_top(date('Y-m-d', $week_ago - 6 * 86400), date('Y-m-d', $week_ago));

                    // ── PANEL 3 : Monthly peaks — this month (daily) vs prev 12 months ───
                    $m_tm_map = $vs5_daily($month_start, $now);

                    $days_in_month = (int) date('t');
                    $m_tm_labels = $m_tm_data = '';
                    $has_tm = false;
                    for ($day = 1; $day <= $days_in_month; $day++) {
                        $d_ts = mktime(0, 0, 0, (int) date('n'), $day, (int) date('Y'));
                        $d = date('Y-m-d', $d_ts);
                        $cnt = isset($m_tm_map[$d]) ? $m_tm_map[$d] : 0;
                        if ($cnt > 0)
                            $has_tm = true;
                        $m_tm_labels .= '"' . $day . '",';
                        $m_tm_data .= $cnt . ',';
                    }
                    $kpi_tm = array_sum($m_tm_map);

                    // Previous 12 months — monthly totals.
                    //
                    // This was the single most expensive query on the screen:
                    // a year of raw visitor rows read and grouped on a
                    // formatted date. Against the rollup it reads at most
                    // 8,760 rows.
                    $twelve_months_ago = mktime(0, 0, 0, (int) date('n') - 12, 1, (int) date('Y'));

                    $pm_map = array();
                    $res    = @mysqli_query(
                        db::$con,
                        "SELECT DATE_FORMAT(stat_date, '%Y-%m') AS ym, SUM(new_visitors) AS cnt
                         FROM visitor_stats_hourly
                         WHERE stat_date >= '" . e(date('Y-m-d', $twelve_months_ago)) . "'
                           AND stat_date <  '" . e(date('Y-m-d', $month_start)) . "'
                         GROUP BY ym"
                    );
                    if ($res) {
                        while ($r = @mysqli_fetch_assoc($res))
                            $pm_map[$r['ym']] = (int) $r['cnt'];
                    }

                    $m_pm_labels = $m_pm_data = '';
                    $has_pm = false;
                    for ($i = 12; $i >= 1; $i--) {
                        $m_ts = mktime(0, 0, 0, (int) date('n') - $i, 1, (int) date('Y'));
                        $ym = date('Y-m', $m_ts);
                        $cnt = isset($pm_map[$ym]) ? $pm_map[$ym] : 0;
                        if ($cnt > 0)
                            $has_pm = true;
                        $m_pm_labels .= '"' . lang(date('M', $m_ts)) . ' \'' . date('y', $m_ts) . '",';
                        $m_pm_data .= $cnt . ',';
                    }
                    $kpi_pm = array_sum($pm_map);

                    // Top content — this month
                    $tp_tm = $vs5_top(date('Y-m-d', $month_start), date('Y-m-d', $now));

                    // ── Like-for-like comparisons ─────────────────────────────────────────
                    //
                    // Today is a part-day and yesterday is a whole one. Setting
                    // one against the other says traffic collapsed every
                    // morning and recovered every midnight, which is the clock
                    // talking, not the site. Each comparison below is cut to
                    // the same point in its own period.
                    $kpi_yesterday_same = 0;
                    for ($h = 0; $h <= $current_hour; $h++) {
                        $kpi_yesterday_same += $h_yest[$h];
                    }

                    // Previous month day by day, so the month card can overlay
                    // like with like and its figure can be cut at today's date.
                    // One more read of the rollup, which is 24 rows per day
                    // whatever the traffic -- the same reason the rest of this
                    // widget stopped touching the visitors table.
                    $prev_month_start = mktime(0, 0, 0, (int) date('n') - 1, 1, (int) date('Y'));
                    $prev_month_end   = mktime(0, 0, 0, (int) date('n'), 0, (int) date('Y'));
                    $prev_month_days  = (int) date('t', $prev_month_start);
                    $pm_daily_map     = $vs5_daily($prev_month_start, $prev_month_end);

                    $today_day = (int) date('j');
                    $kpi_pm_prev_same = 0;
                    $m_pmd_data = '';

                    for ($day = 1; $day <= $days_in_month; $day++) {
                        // A 31 day month laid over a 30 day one leaves the last
                        // slot with nothing behind it. null, not zero: zero
                        // draws a line to the floor and reads as "no traffic
                        // that day".
                        if ($day > $prev_month_days) {
                            $m_pmd_data .= 'null,';
                            continue;
                        }
                        $d = date('Y-m-d', mktime(0, 0, 0, (int) date('n') - 1, $day, (int) date('Y')));
                        $cnt = isset($pm_daily_map[$d]) ? $pm_daily_map[$d] : 0;
                        $m_pmd_data .= $cnt . ',';
                        if ($day <= $today_day) {
                            $kpi_pm_prev_same += $cnt;
                        }
                    }

                    // Today's hours run to the current hour, but the axis keeps
                    // all 24 so the shape sits under yesterday's at the same
                    // clock position. The hours that have not happened are null
                    // rather than absent, which is what stops the line instead
                    // of stretching it across the day.
                    $h_today_full_js = '';
                    for ($h = 0; $h < 24; $h++) {
                        $h_today_full_js .= (($h <= $current_hour) ? $h_today[$h] : 'null') . ',';
                    }
                    $h_today_views_full_js = '';
                    for ($h = 0; $h < 24; $h++) {
                        $h_today_views_full_js .= (($h <= $current_hour) ? $h_today_views[$h] : 'null') . ',';
                    }

                    // Same treatment for the month: days after today are null.
                    $m_tmd_data = '';
                    for ($day = 1; $day <= $days_in_month; $day++) {
                        if ($day > $today_day) {
                            $m_tmd_data .= 'null,';
                            continue;
                        }
                        $d = date('Y-m-d', mktime(0, 0, 0, (int) date('n'), $day, (int) date('Y')));
                        $m_tmd_data .= (isset($m_tm_map[$d]) ? $m_tm_map[$d] : 0) . ',';
                    }

                    // Percentage rather than the raw difference: 242 against
                    // 214 and 24,200 against 21,400 are the same news, and only
                    // one of the two fits in a badge.
                    $vs5_pct_change = function ($current, $previous) {
                        if ($previous <= 0) {
                            return null;
                        }
                        return (int) round((($current - $previous) / $previous) * 100);
                    };

                    // ── Helper: inline top-page row HTML ─────────────────────────────────
                    // The badge counts page views, while the figure above the
                    // chart counts visitors. Two different units sitting one
                    // above the other, so the badge says which it is — a top
                    // item can honestly show more views than the panel shows
                    // visitors, and unlabelled that reads as a bug.
                    $vs5_views_label = lang('page views');

                    $vs5_tp = function ($tp, $rgb) use ($vs5_views_label) {
                        if (!$tp)
                            return '';
                        return '<div class="d-flex align-items-center gap-1" style="font-size:11px">'
                            . '<i class="bi bi-window text-muted flex-shrink-0"></i>'
                            . '<span class="text-truncate flex-grow-1 text-muted" title="' . $tp['name'] . '">' . $tp['name'] . '</span>'
                            . '<span class="badge rounded-pill flex-shrink-0" style="background:rgba(' . $rgb . ',.12);color:rgb(' . $rgb . ');font-size:10px" title="' . h($vs5_views_label) . '">' . number_format($tp['cnt']) . ' <span style="opacity:.75;font-weight:400">' . h($vs5_views_label) . '</span></span>'
                            . '</div>';
                    };

                    // While the historical summaries are still being built,
                    // say so. Older periods legitimately read low until the
                    // backfill finishes, and an unexplained dip in a traffic
                    // chart is the kind of thing that gets investigated as a
                    // real problem.
                    $vs5_progress = '';
                    if (is_array($vs5_backfill) && empty($vs5_backfill['done']) && $vs5_backfill['max_id'] > 0) {
                        $vs5_pct = floor(($vs5_backfill['cursor'] / $vs5_backfill['max_id']) * 100);
                        $vs5_progress = '
                        <div class="px-3 pt-2">
                          <div class="alert alert-info py-1 px-2 mb-0 d-flex align-items-center gap-2" style="font-size:11px">
                            <i class="bi bi-hourglass-split flex-shrink-0"></i>
                            <span class="flex-grow-1">' . lang('Historical visitor summaries are still being built. Figures for earlier periods will be incomplete until this finishes.') . '</span>
                            <span class="badge bg-info-subtle text-info-emphasis flex-shrink-0">' . (int) $vs5_pct . '%</span>
                          </div>
                        </div>';
                    }

                    // ── Assemble output ───────────────────────────────────────────────────
                    //
                    // One chart, not three. The three panels each got a third
                    // of the card for a day that has 24 points in it, and the
                    // period buttons swapped the series rather than showing
                    // both, so seeing whether today beat yesterday meant
                    // holding the other shape in your head. Here they share an
                    // axis: today drawn, yesterday dashed behind it.
                    //
                    // The strip underneath promotes a period into the chart
                    // when clicked, so all of the same series are still
                    // reachable -- they are just not all drawn at postage
                    // stamp size at once.
                    $vs5_day_pct = $vs5_pct_change($kpi_today, $kpi_yesterday_same);
                    $vs5_week_pct = $vs5_pct_change($kpi_tw, $kpi_lw);
                    $vs5_month_pct = $vs5_pct_change($kpi_tm, $kpi_pm_prev_same);

                    $vs5_badge = function ($pct) {
                        if ($pct === null) {
                            return '';
                        }
                        $direction = ($pct >= 0) ? 'up' : 'down';
                        $arrow = ($pct >= 0) ? '&#9650;' : '&#9660;';
                        return '<span class="pg-vs-pill ' . $direction . '">' . $arrow . ' %' . abs($pct) . '</span>';
                    };

                    // Today's busiest page, under the period's. Two different
                    // questions -- "what carries this site" and "what is
                    // happening right now" -- and the second one was not
                    // answerable anywhere on the dashboard.
                    $vs5_today_top = '';
                    if ($tp_today) {
                        $vs5_today_top = '<span class="pg-vs-today">' . lang('Today') . ': <b>'
                            . $tp_today['name'] . '</b> &middot; '
                            . number_format($tp_today['cnt']) . ' ' . h($vs5_views_label) . '</span>';
                    }

                    $vs5_cell = function ($key, $label, $value, $pct_badge, $active = false) {
                        return '<button type="button" class="pg-vs-cell' . ($active ? ' is-active' : '') . '" data-vs5="' . $key . '">'
                            . '<span class="pg-vs-cell-label">' . $label . '</span>'
                            . '<span class="pg-vs-cell-value">' . $value . ' ' . $pct_badge . '</span>'
                            // Chart.js sizes a responsive canvas from its
                            // parent, so the box owns the dimensions and the
                            // canvas fills it. Sizing the canvas directly made
                            // the two fight and drew the line as a smear.
                            . '<span class="pg-vs-spark"><canvas data-spark="' . $key . '"></canvas></span>'
                            . '</button>';
                    };

                    $output_data = '
                    <div class="card-body p-0 overflow-x-hidden overflow-y-auto">
                      ' . $vs5_progress . '
                      <div class="pg-vs">

                        <div class="pg-vs-head">
                          <div>
                            <div class="pg-vs-eyebrow" id="vs5_eyebrow">' . lang('Daily Traffic') . '</div>
                            <div class="pg-vs-big"><span id="vs5_kpi">' . number_format($kpi_today) . '</span><span class="pg-vs-unit">' . lang('visitors') . '</span></div>
                            <div class="pg-vs-compare" id="vs5_compare">
                              ' . $vs5_badge($vs5_day_pct) . '
                              <span class="pg-vs-cmp">' . lang(array(
                                  'string' => 'by this time yesterday {var:1}',
                                  'vars' => '<b>' . number_format($kpi_yesterday_same) . '</b>',
                              )) . '</span>
                            </div>
                            <div class="pg-vs-sub" id="vs5_sub">' . lang(array(
                                'string' => 'all of yesterday {var:1}',
                                'vars' => number_format($kpi_yesterday),
                            )) . '</div>
                          </div>
                          <div class="pg-vs-legend" id="vs5_legend">
                            <span class="pg-vs-key"><i class="pg-vs-sw now"></i><span id="vs5_leg_now">' . lang('Today') . '</span></span>
                            <span class="pg-vs-key"><i class="pg-vs-sw prev"></i><span id="vs5_leg_prev">' . lang('Yesterday') . '</span></span>
                          </div>
                        </div>

                        <div class="pg-vs-chart">
                          ' . (($kpi_today > 0 || $kpi_yesterday > 0 || $has_tw || $has_tm) ? '<canvas id="vs5_canvas"></canvas>' : $vs5_no_data) . '
                        </div>

                        <div class="pg-vs-strip">
                          ' . $vs5_cell('w', lang('This Week'), number_format($kpi_tw), $vs5_badge($vs5_week_pct)) . '
                          ' . $vs5_cell('m', lang('This Month'), number_format($kpi_tm), $vs5_badge($vs5_month_pct)) . '
                          ' . $vs5_cell('y', lang('Last 12 Months'), number_format($kpi_pm), '') . '
                          <div class="pg-vs-cell pg-vs-top">
                            <span class="pg-vs-cell-label">' . lang('Most Visited') . '</span>
                            ' . ($tp_tm
                                ? '<span class="pg-vs-page">' . $tp_tm['name'] . '</span>'
                                  . '<span class="pg-vs-views">' . number_format($tp_tm['cnt']) . ' ' . h($vs5_views_label) . '</span>'
                                : '<span class="pg-vs-views">&mdash;</span>') . '
                            ' . $vs5_today_top . '
                          </div>
                        </div>

                      </div>
                    </div>

                    <script>(function(){
                      var tc = getPreferredThemeColor();
                      var fmtN = function(n){ return Number(n).toLocaleString(); };
                      var VIEWS_LABEL = ' . json_encode($vs5_views_label) . ';
                      var L = {
                        visitors: ' . json_encode(lang('New Visitors')) . ',
                        views:    ' . json_encode(lang('Page Views')) . ',
                        daily:    ' . json_encode(lang('Daily Traffic')) . ',
                        weekly:   ' . json_encode(lang('Weekly Traffic')) . ',
                        monthly:  ' . json_encode(lang('Monthly Traffic')) . ',
                        yearly:   ' . json_encode(lang('Last 12 Months')) . ',
                        today:    ' . json_encode(lang('Today')) . ',
                        yesterday:' . json_encode(lang('Yesterday')) . ',
                        thisWeek: ' . json_encode(lang('This Week')) . ',
                        lastWeek: ' . json_encode(lang('Last Week')) . ',
                        thisMonth:' . json_encode(lang('This Month')) . ',
                        prevMonth:' . json_encode(lang('Previous Month')) . ',
                        total:    ' . json_encode(lang('Total')) . '
                      };

                      // The day view has wording of its own -- "by this time
                      // yesterday" is the whole point of the comparison and
                      // "Yesterday: 214" does not say it. Sent as templates so
                      // the sentence stays in tr.json rather than being
                      // assembled from fragments here, which is what makes a
                      // translation impossible to word naturally.
                      var T = {
                        byThisTime:   ' . json_encode(lang(array('string' => 'by this time yesterday {var:1}', 'vars' => '%N%'))) . ',
                        allYesterday: ' . json_encode(lang(array('string' => 'all of yesterday {var:1}', 'vars' => '%N%'))) . '
                      };

                      // Each period carries both series against one label set,
                      // which is what lets them be drawn on top of each other.
                      // "prev" of null means there is nothing to compare with,
                      // and the legend drops its second key accordingly.
                      var P = {
                        d: { eyebrow:L.daily,   type:"line", labels:[' . $h_yest_labels_js . '],
                             now:{ label:L.today,     data:[' . $h_today_full_js . '], views:[' . $h_today_views_full_js . '], pages:' . json_encode(array_values($h_today_pages)) . ' },
                             prev:{ label:L.yesterday, data:[' . $h_yest_js . '],       views:[' . $h_yest_views_js . '],      pages:' . json_encode(array_values($h_yest_pages)) . ' },
                             kpi:' . $kpi_today . ', cmp:' . $kpi_yesterday_same . ', full:' . $kpi_yesterday . ', nowIndex:' . $current_hour . ' },
                        w: { eyebrow:L.weekly,  type:"line", labels:[' . $w_tw_labels . '],
                             now:{ label:L.thisWeek, data:[' . $w_tw_data . '] },
                             prev:{ label:L.lastWeek, data:[' . $w_lw_data . '] },
                             kpi:' . $kpi_tw . ', cmp:' . $kpi_lw . ', full:null, nowIndex:null },
                        m: { eyebrow:L.monthly, type:"line", labels:[' . $m_tm_labels . '],
                             now:{ label:L.thisMonth, data:[' . $m_tmd_data . '] },
                             prev:{ label:L.prevMonth, data:[' . $m_pmd_data . '] },
                             kpi:' . $kpi_tm . ', cmp:' . $kpi_pm_prev_same . ', full:null, nowIndex:' . ($today_day - 1) . ' },
                        y: { eyebrow:L.yearly,  type:"bar",  labels:[' . $m_pm_labels . '],
                             now:{ label:L.total, data:[' . $m_pm_data . '] },
                             prev:null,
                             kpi:' . $kpi_pm . ', cmp:null, full:null, nowIndex:null }
                      };

                      // The line simply stopping is ambiguous -- it reads as
                      // traffic falling to nothing rather than as the day not
                      // being over. This shades what has not happened yet and
                      // rules off where the data ends.
                      var nowMarker = {
                        id: "vs5NowMarker",
                        afterDatasetsDraw: function (c) {
                          var p = P[mode];
                          if (p.nowIndex === null || p.nowIndex >= p.labels.length - 1) return;
                          var x = c.scales.x.getPixelForValue(p.nowIndex);
                          var a = c.chartArea;
                          var ctx = c.ctx;
                          ctx.save();
                          ctx.fillStyle = "rgba(128,128,128,.07)";
                          ctx.fillRect(x, a.top, a.right - x, a.bottom - a.top);
                          ctx.setLineDash([2, 3]);
                          ctx.strokeStyle = "rgba(128,128,128,.5)";
                          ctx.lineWidth = 1;
                          ctx.beginPath();
                          ctx.moveTo(x, a.top);
                          ctx.lineTo(x, a.bottom);
                          ctx.stroke();
                          ctx.restore();
                        }
                      };

                      var NOW_RGB  = "59,130,246";
                      var PREV_RGB = "148,163,184";

                      var el = document.getElementById("vs5_canvas");
                      var chart = null;
                      var mode = "d";

                      function datasets(p) {
                        var out = [];
                        // Previous first so the current series draws over it.
                        if (p.prev) {
                          out.push({
                            label: p.prev.label, data: p.prev.data,
                            borderColor: "rgba("+PREV_RGB+",.9)", backgroundColor: "rgba("+PREV_RGB+",.10)",
                            borderWidth: 1.6, borderDash: p.type === "line" ? [4,4] : [],
                            fill: false, tension: 0.35, pointRadius: 0, pointHoverRadius: 4,
                            spanGaps: false
                          });
                        }
                        out.push({
                          label: p.now.label, data: p.now.data,
                          borderColor: "rgba("+NOW_RGB+",1)", backgroundColor: "rgba("+NOW_RGB+",.14)",
                          borderWidth: 2.2, fill: p.type === "line",
                          tension: 0.35, pointRadius: 0, pointHoverRadius: 5,
                          pointBackgroundColor: "rgba("+NOW_RGB+",1)",
                          borderRadius: 3, borderSkipped: false,
                          // false, so the hours that have not happened end the
                          // line where the data ends instead of jumping the gap
                          // to nothing.
                          spanGaps: false
                        });
                        return out;
                      }

                      function build() {
                        if (!el) return;
                        var p = P[mode];
                        if (chart) { chart.destroy(); }
                        chart = new Chart(el.getContext("2d"), {
                          type: p.type,
                          data: { labels: p.labels, datasets: datasets(p) },
                          plugins: [nowMarker],
                          options: {
                            animation: { duration: 220 },
                            plugins: {
                              legend: { display: false },
                              tooltip: {
                                callbacks: {
                                  label: function(ctx) {
                                    return ctx.dataset.label + " · " + L.visitors + ": " + fmtN(ctx.parsed.y);
                                  },
                                  afterLabel: function(ctx) {
                                    // Only the day view carries per-hour detail;
                                    // the others have nothing to add and an
                                    // empty line in a tooltip looks like a bug.
                                    var p = P[mode];
                                    var series = (p.prev && ctx.datasetIndex === 0) ? p.prev : p.now;
                                    if (!series.views && !series.pages) return "";
                                    var out = [];
                                    var vw = series.views ? series.views[ctx.dataIndex] : null;
                                    if (vw !== null && vw !== undefined) {
                                      out.push(L.views + ": " + fmtN(vw));
                                    }
                                    var pg = series.pages ? series.pages[ctx.dataIndex] : null;
                                    if (pg) { out.push("↳ " + pg.name + "  ·  " + fmtN(pg.cnt)); }
                                    return out.join("\n");
                                  }
                                }
                              }
                            },
                            responsive: true, maintainAspectRatio: false,
                            scales: {
                              x: { ticks:{ color:tc, font:{size:9}, maxRotation:0, autoSkip:true, maxTicksLimit:8 }, grid:{ display:false } },
                              y: { ticks:{ color:tc, precision:0, font:{size:9} }, beginAtZero:true, grid:{ color:"rgba(128,128,128,.1)" } }
                            },
                            interaction: { mode:"index", intersect:false }
                          }
                        });
                      }

                      function head() {
                        var p = P[mode];
                        document.getElementById("vs5_eyebrow").textContent = p.eyebrow;
                        document.getElementById("vs5_kpi").textContent = fmtN(p.kpi);
                        document.getElementById("vs5_leg_now").textContent = p.now.label;

                        var legend = document.getElementById("vs5_legend");
                        legend.classList.toggle("no-prev", !p.prev);
                        if (p.prev) { document.getElementById("vs5_leg_prev").textContent = p.prev.label; }

                        var compare = document.getElementById("vs5_compare");
                        var sub = document.getElementById("vs5_sub");
                        if (p.cmp === null) { compare.innerHTML = ""; sub.textContent = ""; return; }

                        var pct = (p.cmp > 0) ? Math.round(((p.kpi - p.cmp) / p.cmp) * 100) : null;
                        var pill = "";
                        if (pct !== null) {
                          pill = \'<span class="pg-vs-pill \' + (pct >= 0 ? "up" : "down") + \'">\'
                               + (pct >= 0 ? "▲" : "▼") + " %" + Math.abs(pct) + "</span>";
                        }
                        var text = (mode === "d")
                          ? T.byThisTime.replace("%N%", "<b>" + fmtN(p.cmp) + "</b>")
                          : (p.prev.label + ": <b>" + fmtN(p.cmp) + "</b>");
                        compare.innerHTML = pill + \'<span class="pg-vs-cmp">\' + text + "</span>";
                        sub.innerHTML = (p.full !== null) ? T.allYesterday.replace("%N%", fmtN(p.full)) : "";
                      }

                      // Sparklines. Same series the chart would draw, at the
                      // size a cell can hold: no axes, no interaction, just the
                      // shape, because the number beside it is the reading.
                      function sparks() {
                        var colors = { w:"16,185,129", m:"139,92,246", y:"245,158,11" };
                        document.querySelectorAll("[data-spark]").forEach(function(c){
                          var key = c.getAttribute("data-spark");
                          var p = P[key];
                          if (!p) return;
                          new Chart(c.getContext("2d"), {
                            type: p.type,
                            data: { labels: p.labels, datasets: [{
                              data: p.now.data,
                              borderColor: "rgba("+colors[key]+",1)",
                              backgroundColor: "rgba("+colors[key]+",.18)",
                              borderWidth: 1.6, fill: p.type === "line", tension: 0.35,
                              pointRadius: 0, spanGaps: false, borderRadius: 2
                            }]},
                            options: {
                              animation: false, responsive: true, maintainAspectRatio: false,
                              plugins: { legend:{display:false}, tooltip:{enabled:false} },
                              scales: { x:{display:false}, y:{display:false, beginAtZero:true} },
                              events: []
                            }
                          });
                        });
                      }

                      document.querySelectorAll("[data-vs5]").forEach(function(btn){
                        btn.addEventListener("click", function(){
                          var next = btn.getAttribute("data-vs5");
                          // Clicking the period already showing returns to the
                          // day, so the strip toggles rather than trapping the
                          // reader in a period with no way back.
                          mode = (mode === next) ? "d" : next;
                          document.querySelectorAll("[data-vs5]").forEach(function(b){
                            b.classList.toggle("is-active", b.getAttribute("data-vs5") === mode);
                          });
                          build(); head();
                        });
                      });

                      build(); head(); sparks();
                    })();</script>';

                    $response = array(
                        'status' => 'success',
                        'message' => lang('Data Received successfully.'),
                        'data' => $output_data,
                    );
                    echo encode_json($response);
                    exit();
                    break;
                } else {
                    $response = array(
                        'status' => 'error',
                        'message' => 'Access denied'
                    );
                    echo encode_json($response);
                    exit();
                    break;
                }
            case '6':
                if (($user['role'] < 3) || ($user['manage_visitors'] == true)) {
                    $timestamp_7_days_ago = strtotime('-7 days');
                    $timestamp_1_day_ago = strtotime('-1 day');

                    $output_rows = '';

                    // Read from the hourly rollup rather than counting raw
                    // visitor rows. Beyond the cost, this is what makes the
                    // list useful: entries now name the article or product
                    // that was read, where before every blog post in the site
                    // collapsed into a single row called 'blog-gorunum'.
                    //
                    // Grouping by page_id and item also removes the need for
                    // pg_home_page_group_expression() here — the home page's
                    // several recorded spellings share one page_id, so they no
                    // longer split their own traffic between two rows.

                    // --- Get Top Pages (last 7 days)
                    $top_pages = [];
                    foreach (pg_visitor_top_content(date('Y-m-d', $timestamp_7_days_ago), date('Y-m-d'), 5) as $row) {
                        $top_pages[] = ['name' => $row['label'], 'url' => $row['url'], 'visits' => (int) $row['views']];
                    }

                    // --- Get Trend Page (last 1 day)
                    $trend_page = null;
                    $trend_rows = pg_visitor_top_content(date('Y-m-d', $timestamp_1_day_ago), date('Y-m-d'), 1);
                    if (!empty($trend_rows)) {
                        $trend_page = [
                            'name'   => $trend_rows[0]['label'],
                            'url'    => $trend_rows[0]['url'],
                            'visits' => (int) $trend_rows[0]['views'],
                        ];
                    }

                    // --- Mark/merge trend page
                    // Purpose: Mark if trend is in Top 5; else add as 6th row
                    if ($trend_page) {
                        $found = false;
                        foreach ($top_pages as &$pg) {
                            if ($pg['name'] === $trend_page['name']) {
                                $pg['trend'] = true;
                                $found = true;
                                break;
                            }
                        }
                        if (!$found) {
                            $trend_page['trend'] = true;
                            $top_pages[] = $trend_page;
                        }
                    }

                    // --- Get Top 5 Products (all time)
                    $top_products = [];
                    if ((ECOMMERCE === true) and (($user['role'] < 3) or USER_MANAGE_ECOMMERCE or USER_MANAGE_ECOMMERCE_REPORTS)) {

                        $query = "
                            SELECT
                                p.id,
                                MAX(p.short_description) AS product_name,
                                MAX(p.image_name) AS image_name,
                                SUM(oi.quantity) AS total_qty
                            FROM order_items oi
                            JOIN orders o ON oi.order_id = o.id
                            JOIN products p ON p.id = oi.product_id
                            WHERE o.status = 'complete'
                            GROUP BY p.id
                            ORDER BY total_qty DESC
                            LIMIT 5
                        ";

                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                        while ($row = mysqli_fetch_assoc($result)) {
                            $top_products[] = [
                                'id' => (int) $row['id'],
                                'product_name' => h($row['product_name']),
                                'image_name' => $row['image_name'],
                                'qty' => (int) $row['total_qty'],
                            ];
                        }
                    }



                    // --- Render Pages
                    $output_rows .= pg_widget_row_heading(lang('Top Pages'), lang('Total Visits'));
                    foreach ($top_pages as $pg) {
                        $trend_badge = !empty($pg['trend'])
                            ? ' <i class="bi bi-fire text-danger ms-1" title="' . lang('Trending Page') . '"></i>'
                            : '';
                        $output_rows .= pg_widget_row(array(
                            'small'  => true,
                            'href'   => h($pg['url']),
                            'target' => '_blank',
                            'badge'  => '<i class="bi bi-window"></i>',
                            'name'   => h($pg['name']) . $trend_badge,
                            'aside'  => number_format($pg['visits']),
                        ));
                    }

                    // --- Render Products
                    if (!empty($top_products)) {
                        $output_rows .= pg_widget_row_heading(lang('Top Products'), lang('Total Sales'));

                        foreach ($top_products as $pr) {
                            $output_rows .= pg_widget_row(array(
                                'small' => true,
                                'href'  => 'edit_product.php?id=' . $pr['id'],
                                'badge' => $pr['image_name']
                                    ? '<img src="' . PATH . h($pr['image_name']) . '" alt="">'
                                    : '<i class="bi bi-box-seam"></i>',
                                'name'  => $pr['product_name'],
                                'aside' => number_format($pr['qty'], 0, ',', '.'),
                            ));
                        }
                    }

                    // Neither list had anything in it. Without this the card
                    // drew an empty .pg-list and read as a card that had failed
                    // to load rather than as a site with no traffic yet.
                    if ($output_rows === '') {
                        $output_rows = pg_widget_empty(
                            'bi-fire',
                            lang('There is not enough data yet.'));
                    }

                    // --- Final HTML for widget body
                    $output_data = '
                    <div class="card-body p-0 overflow-x-hidden overflow-y-auto">
                        <div class="pg-list">' . $output_rows . '</div>
                    </div>';

                    $response = [
                        'status' => 'success',
                        'message' => lang('Data Received successfully.'),
                        'data' => $output_data
                    ];
                    echo encode_json($response);
                    exit;
                } else {
                    $response = array(
                        'status' => 'error',
                        'message' => 'Access denied'
                    );
                    echo encode_json($response);
                    exit();
                    break;
                }
            case '7':
                // initialize variable for storing the maximum number of items that should appear in the recent update area
                $maximum_number_of_items = 20;
                // initialize variable for storing the maximum number of items for special items (e.g. files, designer files, and products)
                $special_maximum_number_of_items = 5;
                // initialize array for storing items that might appear in the recent updates area
                $recent_update_items = array();

                // initialize array that will be used for sorting the items for the recent updates area
                $recent_update_item_timestamps = array();

                $folders_that_user_has_access_to = array();

                // if user is a basic user, then get folders that user has access to
                if ($user['role'] == 3) {
                    $folders_that_user_has_access_to = get_folders_that_user_has_access_to($user['id']);
                }

                $pages = array();

                // get all pages sorted by last modified descending
                $query = "SELECT
                        page.page_name as name,
                        page.page_timestamp as timestamp,
                        user.user_username as username,
                        page.page_folder as folder_id,
                        page.page_type
                    FROM page
                    LEFT JOIN user ON page.page_user = user.user_id
                    ORDER BY page.page_timestamp DESC";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                // loop through the result in order to prepare array of items
                while ($row = mysqli_fetch_assoc($result)) {
                    $pages[] = $row;
                }

                // initialize variable to keep track of how many items have been added
                $count = 0;

                // loop through the items in order to determine which the user has access to
                foreach ($pages as $page) {
                    // if user has access to item then add it to arrays
                    if (check_folder_access_in_array($page['folder_id'], $folders_that_user_has_access_to) == true) {
                        $page['type'] = 'page';
                        $recent_update_items[] = $page;
                        $recent_update_item_timestamps[] = $page['timestamp'];

                        $count++;

                        // if the maximum number of items has been added, then we are done, so break out of the loop
                        if ($count == $maximum_number_of_items) {
                            break;
                        }
                    }
                }

                $short_links = array();

                // Get all short links sorted by last modified descending
                $query = "SELECT
                        short_links.id,
                        short_links.name,
                        short_links.destination_type,
                        short_links.created_user_id,
                        short_links.last_modified_timestamp AS timestamp,
                        user.user_username AS username,
                        page.page_folder AS folder_id
                    FROM short_links
                    LEFT JOIN user ON short_links.last_modified_user_id = user.user_id
                    LEFT JOIN page ON short_links.page_id = page.page_id
                    ORDER BY short_links.last_modified_timestamp DESC";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                $short_links = mysqli_fetch_items($result);

                // initialize variable to keep track of how many items have been added
                $count = 0;

                // loop through the items in order to determine which the user has access to
                foreach ($short_links as $short_link) {
                    // if user has access to item then add it to arrays
                    if ((USER_ROLE < 3) || ((($short_link['destination_type'] == 'page') || ($short_link['destination_type'] == 'product_group') || ($short_link['destination_type'] == 'product')) && (check_folder_access_in_array($short_link['folder_id'], $folders_that_user_has_access_to) == true)) || (($short_link['destination_type'] == 'url') && (USER_ID == $short_link['created_user_id']))) {
                        $short_link['type'] = 'short_link';
                        $recent_update_items[] = $short_link;
                        $recent_update_item_timestamps[] = $short_link['timestamp'];

                        $count++;

                        // if the maximum number of items has been added, then we are done, so break out of the loop
                        if ($count == $maximum_number_of_items) {
                            break;
                        }
                    }
                }

                $files = array();

                // get all files sorted by last modified descending
                $query = "SELECT
                        files.id,
                        files.name,
                        files.timestamp,
                        user.user_username as username,
                        files.folder as folder_id
                    FROM files
                    LEFT JOIN user ON files.user = user.user_id
                    WHERE files.design = '0'
                    ORDER BY files.timestamp DESC";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                // loop through the result in order to prepare array of items
                while ($row = mysqli_fetch_assoc($result)) {
                    $files[] = $row;
                }

                // initialize variable to keep track of how many items have been added
                $count = 0;

                // loop through the items in order to determine which the user has access to
                foreach ($files as $file) {
                    // if user has access to item then add it to arrays
                    if (check_folder_access_in_array($file['folder_id'], $folders_that_user_has_access_to) == true) {
                        $file['type'] = 'file';
                        $recent_update_items[] = $file;
                        $recent_update_item_timestamps[] = $file['timestamp'];

                        $count++;

                        // if the maximum number of items has been added, then we are done, so break out of the loop
                        if ($count == $special_maximum_number_of_items) {
                            break;
                        }
                    }
                }

                $folders = array();

                // get all folders sorted by last modified descending
                $query = "SELECT
                        folder.folder_id as id,
                        folder.folder_name as name,
                        folder.folder_timestamp as timestamp,
                        user.user_username as username
                    FROM folder
                    LEFT JOIN user ON folder.folder_user = user.user_id
                    ORDER BY folder.folder_timestamp DESC";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                // loop through the result in order to prepare array of items
                while ($row = mysqli_fetch_assoc($result)) {
                    $folders[] = $row;
                }

                // initialize variable to keep track of how many items have been added
                $count = 0;

                // loop through the items in order to determine which the user has access to
                foreach ($folders as $folder) {
                    // if user has access to item then add it to arrays
                    if (check_folder_access_in_array($folder['id'], $folders_that_user_has_access_to) == true) {
                        $folder['type'] = 'folder';
                        $recent_update_items[] = $folder;
                        $recent_update_item_timestamps[] = $folder['timestamp'];

                        $count++;

                        // if the maximum number of items has been added, then we are done, so break out of the loop
                        if ($count == $maximum_number_of_items) {
                            break;
                        }
                    }
                }

                // if calendars is enabled and the user has access to manage calendars, then get calendars and events
                if ((CALENDARS == true) && (($user['role'] < 3) || ($user['manage_calendars'] == true))) {
                    $calendars = array();

                    // get all calendars sorted by last modified descending
                    $query = "SELECT
                            calendars.id,
                            calendars.name,
                            calendars.last_modified_timestamp as timestamp,
                            user.user_username as username
                        FROM calendars
                        LEFT JOIN user ON calendars.last_modified_user_id = user.user_id
                        ORDER BY calendars.last_modified_timestamp DESC";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                    // loop through the result in order to prepare array of items
                    while ($row = mysqli_fetch_assoc($result)) {
                        $calendars[] = $row;
                    }

                    // initialize variable to keep track of how many items have been added
                    $count = 0;

                    // loop through the items in order to determine which the user has access to
                    foreach ($calendars as $calendar) {
                        // if user has access to item then add it to arrays
                        if (validate_calendar_access($calendar['id']) == true) {
                            $calendar['type'] = 'calendar';
                            $recent_update_items[] = $calendar;
                            $recent_update_item_timestamps[] = $calendar['timestamp'];

                            $count++;

                            // if the maximum number of items has been added, then we are done, so break out of the loop
                            if ($count == $maximum_number_of_items) {
                                break;
                            }
                        }
                    }

                    $calendar_events = array();

                    // get all calendar events sorted by last modified descending
                    $query = "SELECT
                            calendar_events.id,
                            calendar_events.name,
                            calendar_events.last_modified_timestamp as timestamp,
                            user.user_username as username
                        FROM calendar_events
                        LEFT JOIN user ON calendar_events.last_modified_user_id = user.user_id
                        ORDER BY calendar_events.last_modified_timestamp DESC";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                    // loop through the result in order to prepare array of items
                    while ($row = mysqli_fetch_assoc($result)) {
                        $calendar_events[] = $row;
                    }

                    // initialize variable to keep track of how many items have been added
                    $count = 0;

                    // loop through the items in order to determine which the user has access to
                    foreach ($calendar_events as $calendar_event) {
                        // if user has access to item then add it to arrays
                        if (validate_calendar_event_access($calendar_event['id']) == true) {
                            $calendar_event['type'] = 'calendar_event';
                            $recent_update_items[] = $calendar_event;
                            $recent_update_item_timestamps[] = $calendar_event['timestamp'];

                            $count++;

                            // if the maximum number of items has been added, then we are done, so break out of the loop
                            if ($count == $maximum_number_of_items) {
                                break;
                            }
                        }
                    }
                }

                // if e-commerce is enabled and the user has access to manage e-commerce, then get e-commerce items
                if ((ECOMMERCE == true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))) {
                    $products = array();

                    // get all products sorted by last modified descending
                    $query = "SELECT
                            products.id,
                            products.short_description as name,
                            products.timestamp,
                            user.user_username as username
                        FROM products
                        LEFT JOIN user ON products.user = user.user_id
                        ORDER BY products.timestamp DESC
                        LIMIT $special_maximum_number_of_items";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                    // loop through the result in order to prepare array of items
                    while ($row = mysqli_fetch_assoc($result)) {
                        $products[] = $row;
                    }

                    // loop through the items in order to add them to arrays
                    foreach ($products as $product) {
                        $product['type'] = 'product';
                        $recent_update_items[] = $product;
                        $recent_update_item_timestamps[] = $product['timestamp'];
                    }

                    $product_groups = array();

                    // get all product groups sorted by last modified descending
                    $query = "SELECT
                            product_groups.id,
                            product_groups.name,
                            product_groups.timestamp,
                            user.user_username as username
                        FROM product_groups
                        LEFT JOIN user ON product_groups.user = user.user_id
                        ORDER BY product_groups.timestamp DESC
                        LIMIT $maximum_number_of_items";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                    // loop through the result in order to prepare array of items
                    while ($row = mysqli_fetch_assoc($result)) {
                        $product_groups[] = $row;
                    }

                    // loop through the items in order to add them to arrays
                    foreach ($product_groups as $product_group) {
                        $product_group['type'] = 'product_group';
                        $recent_update_items[] = $product_group;
                        $recent_update_item_timestamps[] = $product_group['timestamp'];
                    }

                    $offers = array();

                    // get all offers sorted by last modified descending
                    $query = "SELECT
                            offers.id,
                            offers.code as name,
                            offers.timestamp,
                            user.user_username as username
                        FROM offers
                        LEFT JOIN user ON offers.user = user.user_id
                        ORDER BY offers.timestamp DESC
                        LIMIT $maximum_number_of_items";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                    // loop through the result in order to prepare array of items
                    while ($row = mysqli_fetch_assoc($result)) {
                        $offers[] = $row;
                    }

                    // loop through the items in order to add them to arrays
                    foreach ($offers as $offer) {
                        $offer['type'] = 'offer';
                        $recent_update_items[] = $offer;
                        $recent_update_item_timestamps[] = $offer['timestamp'];
                    }
                }

                // If ads are enabled, then get them.
                if (ADS === true) {
                    $ads = array();

                    // get all ads sorted by last modified descending
                    $query = "SELECT
                            ads.id,
                            ads.name,
                            ads.last_modified_timestamp as timestamp,
                            user.user_username as username,
                            ads.ad_region_id
                        FROM ads
                        LEFT JOIN user ON ads.last_modified_user_id = user.user_id
                        ORDER BY ads.last_modified_timestamp DESC";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                    // loop through the result in order to prepare array of items
                    while ($row = mysqli_fetch_assoc($result)) {
                        $ads[] = $row;
                    }

                    // initialize variable to keep track of how many items have been added
                    $count = 0;

                    // loop through the items in order to determine which the user has access to
                    foreach ($ads as $ad) {
                        // if user has access to item then add it to arrays
                        if (($user['role'] < 3) || (in_array($ad['ad_region_id'], get_items_user_can_edit('ad_regions', $user['id'])) == true)) {
                            $ad['type'] = 'ad';
                            $recent_update_items[] = $ad;
                            $recent_update_item_timestamps[] = $ad['timestamp'];

                            $count++;

                            // if the maximum number of items has been added, then we are done, so break out of the loop
                            if ($count == $maximum_number_of_items) {
                                break;
                            }
                        }
                    }
                }

                $menus = array();

                // get all menus sorted by last modified descending
                $query = "SELECT
                        menus.id,
                        menus.name,
                        menus.last_modified_timestamp as timestamp,
                        user.user_username as username
                    FROM menus
                    LEFT JOIN user ON menus.last_modified_user_id = user.user_id
                    ORDER BY menus.last_modified_timestamp DESC";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                // loop through the result in order to prepare array of items
                while ($row = mysqli_fetch_assoc($result)) {
                    $menus[] = $row;
                }

                // initialize variable to keep track of how many items have been added
                $count = 0;

                // loop through the items in order to determine which the user has access to
                foreach ($menus as $menu) {
                    // if user has access to item then add it to arrays
                    if (($user['role'] < 3) || (in_array($menu['id'], get_items_user_can_edit('menus', $user['id'])) == true)) {
                        $menu['type'] = 'menu';
                        $recent_update_items[] = $menu;
                        $recent_update_item_timestamps[] = $menu['timestamp'];

                        $count++;

                        // if the maximum number of items has been added, then we are done, so break out of the loop
                        if ($count == $maximum_number_of_items) {
                            break;
                        }
                    }
                }

                // if the user has access to the design tab, then get design items
                if ($user['role'] < 2) {
                    $styles = array();

                    // get all styles sorted by last modified descending
                    $query = "SELECT
                            style.style_id as id,
                            style.style_name as name,
                            style.style_timestamp as timestamp,
                            user.user_username as username,
                            style.style_type
                        FROM style
                        LEFT JOIN user ON style.style_user = user.user_id
                        ORDER BY style.style_timestamp DESC
                        LIMIT $maximum_number_of_items";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                    // loop through the result in order to prepare array of items
                    while ($row = mysqli_fetch_assoc($result)) {
                        $styles[] = $row;
                    }

                    // loop through the items in order to add them to arrays
                    foreach ($styles as $style) {
                        $style['type'] = 'style';
                        $recent_update_items[] = $style;
                        $recent_update_item_timestamps[] = $style['timestamp'];
                    }

                    $common_regions = array();

                    // get all common regions sorted by last modified descending
                    $query = "SELECT
                            cregion.cregion_id as id,
                            cregion.cregion_name as name,
                            cregion.cregion_timestamp as timestamp,
                            user.user_username as username
                        FROM cregion
                        LEFT JOIN user ON cregion.cregion_user = user.user_id
                        WHERE cregion.cregion_designer_type = 'no'
                        ORDER BY cregion.cregion_timestamp DESC
                        LIMIT $maximum_number_of_items";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                    // loop through the result in order to prepare array of items
                    while ($row = mysqli_fetch_assoc($result)) {
                        $common_regions[] = $row;
                    }

                    // loop through the items in order to add them to arrays
                    foreach ($common_regions as $common_region) {
                        $common_region['type'] = 'common_region';
                        $recent_update_items[] = $common_region;
                        $recent_update_item_timestamps[] = $common_region['timestamp'];
                    }

                    $designer_regions = array();

                    // get all designer regions sorted by last modified descending
                    $query = "SELECT
                            cregion.cregion_id as id,
                            cregion.cregion_name as name,
                            cregion.cregion_timestamp as timestamp,
                            user.user_username as username
                        FROM cregion
                        LEFT JOIN user ON cregion.cregion_user = user.user_id
                        WHERE cregion.cregion_designer_type = 'yes'
                        ORDER BY cregion.cregion_timestamp DESC
                        LIMIT $maximum_number_of_items";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                    // loop through the result in order to prepare array of items
                    while ($row = mysqli_fetch_assoc($result)) {
                        $designer_regions[] = $row;
                    }

                    // loop through the items in order to add them to arrays
                    foreach ($designer_regions as $designer_region) {
                        $designer_region['type'] = 'designer_region';
                        $recent_update_items[] = $designer_region;
                        $recent_update_item_timestamps[] = $designer_region['timestamp'];
                    }

                    // If ads are enabled, then get ad regions.
                    if (ADS === true) {
                        $ad_regions = array();

                        // get all ad regions sorted by last modified descending
                        $query = "SELECT
                                ad_regions.id,
                                ad_regions.name,
                                ad_regions.last_modified_timestamp as timestamp,
                                user.user_username as username
                            FROM ad_regions
                            LEFT JOIN user ON ad_regions.last_modified_user_id = user.user_id
                            ORDER BY ad_regions.last_modified_timestamp DESC
                            LIMIT $maximum_number_of_items";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                        // loop through the result in order to prepare array of items
                        while ($row = mysqli_fetch_assoc($result)) {
                            $ad_regions[] = $row;
                        }

                        // loop through the items in order to add them to arrays
                        foreach ($ad_regions as $ad_region) {
                            $ad_region['type'] = 'ad_region';
                            $recent_update_items[] = $ad_region;
                            $recent_update_item_timestamps[] = $ad_region['timestamp'];
                        }
                    }

                    // if the user is an administrator and dynamic regions are enabled, then get dynamic regions
                    if (($user['role'] == 0) && (defined('DYNAMIC_REGIONS') == true) && (DYNAMIC_REGIONS == true)) {
                        $dynamic_regions = array();

                        // get all dynamic regions sorted by last modified descending
                        $query = "SELECT
                                dregion.dregion_id as id,
                                dregion.dregion_name as name,
                                dregion.dregion_timestamp as timestamp,
                                user.user_username as username
                            FROM dregion
                            LEFT JOIN user ON dregion.dregion_user = user.user_id
                            ORDER BY dregion.dregion_timestamp DESC
                            LIMIT $maximum_number_of_items";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                        // loop through the result in order to prepare array of items
                        while ($row = mysqli_fetch_assoc($result)) {
                            $dynamic_regions[] = $row;
                        }

                        // loop through the items in order to add them to arrays
                        foreach ($dynamic_regions as $dynamic_region) {
                            $dynamic_region['type'] = 'dynamic_region';
                            $recent_update_items[] = $dynamic_region;
                            $recent_update_item_timestamps[] = $dynamic_region['timestamp'];
                        }
                    }

                    $login_regions = array();

                    // get all login regions sorted by last modified descending
                    $query = "SELECT
                            login_regions.id,
                            login_regions.name,
                            login_regions.last_modified_timestamp as timestamp,
                            user.user_username as username
                        FROM login_regions
                        LEFT JOIN user ON login_regions.last_modified_user_id = user.user_id
                        ORDER BY login_regions.last_modified_timestamp DESC
                        LIMIT $maximum_number_of_items";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                    // loop through the result in order to prepare array of items
                    while ($row = mysqli_fetch_assoc($result)) {
                        $login_regions[] = $row;
                    }

                    // loop through the items in order to add them to arrays
                    foreach ($login_regions as $login_region) {
                        $login_region['type'] = 'login_region';
                        $recent_update_items[] = $login_region;
                        $recent_update_item_timestamps[] = $login_region['timestamp'];
                    }

                    $themes = array();

                    // get all themes sorted by last modified descending
                    $query = "SELECT
                            files.id,
                            files.name,
                            files.timestamp,
                            user.user_username as username
                        FROM files
                        LEFT JOIN user ON files.user = user.user_id
                        WHERE (files.type = 'css') AND (files.design = '1')
                        ORDER BY files.timestamp DESC
                        LIMIT $maximum_number_of_items";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                    // loop through the result in order to prepare array of items
                    while ($row = mysqli_fetch_assoc($result)) {
                        $themes[] = $row;
                    }

                    // loop through the items in order to add them to arrays
                    foreach ($themes as $theme) {
                        $theme['type'] = 'theme';
                        $recent_update_items[] = $theme;
                        $recent_update_item_timestamps[] = $theme['timestamp'];
                    }

                    $design_files = array();

                    // get all design files sorted by last modified descending
                    // even though themes are considered design files, we are going to exclude this from this query because we don't want them appear twice (as both a theme and a design file)
                    $query = "SELECT
                            files.id,
                            files.name,
                            files.timestamp,
                            user.user_username as username,
                            files.folder as folder_id
                        FROM files
                        LEFT JOIN user ON files.user = user.user_id
                        WHERE (files.design = '1') AND (files.type != 'css')
                        ORDER BY files.timestamp DESC
                        LIMIT $special_maximum_number_of_items";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                    // loop through the result in order to prepare array of items
                    while ($row = mysqli_fetch_assoc($result)) {
                        $design_files[] = $row;
                    }

                    // loop through the items in order to add them to arrays
                    foreach ($design_files as $design_file) {
                        $design_file['type'] = 'design_file';
                        $recent_update_items[] = $design_file;
                        $recent_update_item_timestamps[] = $design_file['timestamp'];
                    }
                }

                // sort the recent update items by the timestamp descending
                array_multisort($recent_update_item_timestamps, SORT_DESC, $recent_update_items);

                // update array to only contain the maximum number of items
                $recent_update_items = array_slice($recent_update_items, 0, $maximum_number_of_items);

                if (!empty($recent_update_items)) {
                    // loop through the recent update items, in order to output rows
                    foreach ($recent_update_items as $recent_update_item) {
                        $type_name = '';
                        $output_link_url = '';

                        // get type name and icon
                        switch ($recent_update_item['type']) {
                            case 'page':
                                $type_name = lang('Page');
                                $query_string_from = '';
                                switch ($recent_update_item['page_type']) {
                                    case 'view order':
                                    case 'custom form':
                                    case 'custom form confirmation':
                                    case 'calendar event view':
                                    case 'catalog detail':
                                    case 'shipping address and arrival':
                                    case 'shipping method':
                                    case 'logout':
                                        $query_string_from = '?from=control_panel';
                                        break;
                                }
                                $output_link_url = h(escape_javascript(PATH)) . h(escape_javascript(encode_url_path($recent_update_item['name']))) . $query_string_from;
                                $type_bi_icon = 'bi-window';
                                $icon_color = 'var(--pages-color)';
                                break;
                            case 'short_link':
                                $type_name = lang('Short Link');
                                $output_link_url = 'edit_short_link.php?id=' . $recent_update_item['id'];
                                $type_bi_icon = 'bi-link-45deg';
                                $icon_color = 'var(--pages-color)';
                                break;
                            case 'file':
                                $type_name = lang('File');
                                $output_link_url = 'edit_file.php?id=' . $recent_update_item['id'];
                                $type_bi_icon = 'bi-file-earmark';
                                $icon_color = 'var(--files-color)';
                                break;
                            case 'folder':
                                $type_name = lang('Folder');
                                $output_link_url = 'edit_folder.php?id=' . $recent_update_item['id'];
                                $type_bi_icon = 'bi-folder';
                                $icon_color = 'var(--folders-color)';
                                break;
                            case 'calendar':
                                $type_name = lang('Calendar');
                                $output_link_url = 'calendars.php?id=' . $recent_update_item['id'];
                                $type_bi_icon = 'bi-calendar3';
                                $icon_color = 'var(--calendars-color)';
                                break;
                            case 'calendar_event':
                                $type_name = lang('Event');
                                $output_link_url = 'edit_calendar_event.php?id=' . $recent_update_item['id'];
                                $type_bi_icon = 'bi-calendar-check';
                                $icon_color = 'var(--calendars-color)';
                                break;
                            case 'product':
                                $type_name = lang('Product');
                                $output_link_url = 'edit_product.php?id=' . $recent_update_item['id'];
                                $type_bi_icon = 'bi-box-seam';
                                $icon_color = 'var(--ecommerce-color)';
                                break;
                            case 'product_group':
                                $type_name = lang('Product Group');
                                $output_link_url = 'edit_product_group.php?id=' . $recent_update_item['id'];
                                $type_bi_icon = 'bi-grid';
                                $icon_color = 'var(--ecommerce-color)';
                                break;
                            case 'offer':
                                $type_name = lang('Offer');
                                $output_link_url = 'edit_offer.php?id=' . $recent_update_item['id'];
                                $type_bi_icon = 'bi-tag';
                                $icon_color = 'var(--ecommerce-color)';
                                break;
                            case 'ad':
                                $type_name = lang('Ad');
                                $output_link_url = 'edit_ad.php?id=' . $recent_update_item['id'];
                                $type_bi_icon = 'bi-megaphone';
                                $icon_color = 'var(--ad-color)';
                                break;
                            case 'menu':
                                $type_name = lang('Menu');
                                $output_link_url = 'view_menu_items.php?id=' . $recent_update_item['id'] . '&from=welcome&send_to=' . h(escape_javascript(urlencode(get_request_uri())));
                                $type_bi_icon = 'bi-list';
                                $icon_color = 'var(--design-color)';
                                break;
                            case 'style':
                                $type_name = lang('Page Style');
                                $output_link_url = 'edit_' . $recent_update_item['style_type'] . '_style.php?id=' . $recent_update_item['id'];
                                $type_bi_icon = 'bi-palette';
                                $icon_color = 'var(--design-color)';
                                break;
                            case 'common_region':
                                $type_name = lang('Common Region');
                                $output_link_url = 'edit_common_region.php?id=' . $recent_update_item['id'];
                                $type_bi_icon = 'bi-layout-text-sidebar';
                                $icon_color = 'var(--design-color)';
                                break;
                            case 'designer_region':
                                $type_name = lang('Designer Region');
                                $output_link_url = 'edit_designer_region.php?id=' . $recent_update_item['id'];
                                $type_bi_icon = 'bi-columns';
                                $icon_color = 'var(--design-color)';
                                break;
                            case 'ad_region':
                                $type_name = lang('Ad Region');
                                $output_link_url = 'edit_ad_region.php?id=' . $recent_update_item['id'];
                                $type_bi_icon = 'bi-badge-ad';
                                $icon_color = 'var(--design-color)';
                                break;
                            case 'dynamic_region':
                                $type_name = lang('Dynamic Region');
                                $output_link_url = 'edit_dynamic_region.php?id=' . $recent_update_item['id'];
                                $type_bi_icon = 'bi-code-slash';
                                $icon_color = 'var(--design-color)';
                                break;
                            case 'login_region':
                                $type_name = lang('Login Region');
                                $output_link_url = 'edit_login_region.php?id=' . $recent_update_item['id'];
                                $type_bi_icon = 'bi-person-badge';
                                $icon_color = 'var(--design-color)';
                                break;
                            case 'theme':
                                $type_name = lang('Theme');
                                $output_link_url = 'edit_theme_file.php?id=' . $recent_update_item['id'];
                                $type_bi_icon = 'bi-brush';
                                $icon_color = 'var(--design-color)';
                                break;
                            case 'design_file':
                                $type_name = lang('Design File');
                                $output_link_url = 'edit_design_file.php?id=' . $recent_update_item['id'];
                                $type_bi_icon = 'bi-file-code';
                                $icon_color = 'var(--design-color)';
                                break;
                            default:
                                $type_bi_icon = 'bi-file-earmark';
                                $icon_color = 'var(--design-color)';
                        }

                        // The per-type colour the icon used to carry is gone:
                        // the tile is the card's accent now. No information is
                        // lost, because the glyph already differs per type.
                        $output_rows .= pg_widget_row(array(
                            'href'  => $output_link_url,
                            'badge' => '<i class="bi ' . $type_bi_icon . '"></i>',
                            'name'  => h($recent_update_item['name']),
                            'aside' => get_relative_time(array('timestamp' => $recent_update_item['timestamp'])),
                            'meta'  => h($type_name) . ($recent_update_item['username'] ? ' &middot; ' . h($recent_update_item['username']) : ''),
                        ));
                    }

                } else {
                    $output_rows = pg_widget_empty('bi-clock-history', lang(array('string' => 'There is no {var:1} right now.', 'vars' => lang('Recent Update'))));
                }
                $output_data = '
                    <div class="card-body p-0 overflow-x-hidden overflow-y-auto">
                        <div class="pg-list">' . $output_rows . '</div>
                    </div>';

                //return success json output
                $response = array(
                    'status' => 'success',
                    'message' => 'Action Success',
                    'data' => $output_data,
                );
                echo encode_json($response);
                exit();
                break;
            case '8':
                if ((ECOMMERCE == true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))) {
                    $orders = array();
                    $query = "SELECT
                            orders.id ,
                            orders.order_number,
                            orders.status,
                            user.user_username as username,
                            contacts.first_name,
                            contacts.last_name,
                            orders.tracking_code,
                            orders.total,
                            orders.order_date as timestamp
                        FROM orders
                        LEFT JOIN user ON orders.user_id = user.user_id
                        LEFT JOIN contacts ON orders.contact_id = contacts.id
                        LEFT JOIN ship_tos ON orders.id = ship_tos.order_id
                        WHERE status != 'incomplete'
                        ORDER BY orders.order_date DESC
                        LIMIT 50";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                    // loop through the result in order to prepare array of items
                    while ($row = mysqli_fetch_assoc($result)) {
                        $orders[] = $row;
                    }

                    $output_order_rows = '';


                    if (!empty($orders)) {

                        // loop through the orders, in order to output rows
                        foreach ($orders as $order) {
                            $output_link_url = 'view_order.php?id=' . $order['id'];

                            $name = '';

                            // if there is a username then use that for the name
                            if ($order['username'] != '') {
                                $name = $order['username'];

                                // else there is not a username, so use contact name

                            } else {
                                // if there is a first name, then add it to the name
                                if ($order['first_name'] != '') {
                                    $name .= $order['first_name'];
                                }

                                // if there is a last name, then add it to the name
                                if ($order['last_name'] != '') {
                                    // if the name is not blank, then add space
                                    if ($name != '') {
                                        $name .= ' ';
                                    }

                                    $name .= $order['last_name'];
                                }
                            }

                            // if the name is blank, then set it to placeholder
                            if ($name == '') {
                                $name = '[' . lang('Visitor') . ']';
                            }

                            $id = $order['id'];
                            $shipped = false;
                            if (ECOMMERCE_SHIPPING == true) {
                                $ship_result = mysqli_query(db::$con, "SELECT id FROM shipping_tracking_numbers WHERE order_id = '" . $id . "' LIMIT 1") or output_error('Query failed.');
                                $shipped = (bool) mysqli_fetch_assoc($ship_result);
                            }
                            $ship_icon_color = $shipped ? '#10b981' : '#a1a1a1';
                            $ship_title = $shipped ? lang('Shipped') : lang('Not shipped yet');

                            $order_canceled = ($order['status'] == 'cancelled');
                            // Shipped and not-shipped are both ordinary states,
                            // so the glyph separates them and the tile stays on
                            // the card accent. Cancelled is not ordinary, so it
                            // takes the tile.
                            $output_order_rows .= pg_widget_row(array(
                                'href'  => $output_link_url,
                                'color' => $order_canceled ? '#dc3545' : '',
                                'badge' => $order_canceled
                                    ? '<i class="bi bi-x-circle" title="' . lang('Canceled') . '"></i>'
                                    : '<i class="bi ' . ($shipped ? 'bi-truck' : 'bi-box-seam') . '" title="' . $ship_title . '"></i>',
                                'name'  => h($name),
                                'aside' => prepare_amount($order['total'] / 100),
                                'meta'  => h($order['order_number'])
                                    . ($order_canceled ? ' &mdash; <span class="text-danger">' . lang('Canceled') . '</span>' : '')
                                    . ' &middot; ' . get_relative_time(array('timestamp' => $order['timestamp'])),
                            ));
                        }
                    } else {
                        $output_order_rows = pg_widget_empty('bi-cart4', lang(array('string' => 'There is no {var:1} right now.', 'vars' => lang('Order'))));
                    }

                    $carts = array();
                    $query = "SELECT
                            orders.id,
                            user.user_username as username,
                            contacts.first_name,
                            contacts.last_name,
                            orders.reference_code,
                            orders.order_date as timestamp
                        FROM orders
                        LEFT JOIN user ON orders.user_id = user.user_id
                        LEFT JOIN contacts ON orders.contact_id = contacts.id
                        WHERE status = 'incomplete'
                        ORDER BY orders.order_date DESC
                        LIMIT 50";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    // loop through the result in order to prepare array of items
                    while ($row = mysqli_fetch_assoc($result)) {
                        $carts[] = $row;
                    }

                    $output_cart_rows = '';
                    if (!empty($carts)) {
                        // loop through the carts, in order to output rows
                        foreach ($carts as $cart) {

                            // Cart total: every line's price times its own
                            // quantity, summed. Prices are stored in kurus.
                            $cart_total = db_value(
                                "SELECT SUM(order_items.price * order_items.quantity)
                                FROM order_items
                                WHERE order_id = '" . (int) $cart['id'] . "'");
                            $total = BASE_CURRENCY_SYMBOL . number_format(round((float) $cart_total) / 100, 2, '.', ',');

                            $output_link_url = 'view_order.php?id=' . $cart['id'];

                            $name = '';

                            // if there is a username then use that for the name
                            if ($cart['username'] != '') {
                                $name = $cart['username'];

                                // else there is not a username, so use contact name

                            } else {
                                // if there is a first name, then add it to the name
                                if ($cart['first_name'] != '') {
                                    $name .= $cart['first_name'];
                                }

                                // if there is a last name, then add it to the name
                                if ($cart['last_name'] != '') {
                                    // if the name is not blank, then add space
                                    if ($name != '') {
                                        $name .= ' ';
                                    }

                                    $name .= $cart['last_name'];
                                }
                            }

                            // if the name is blank, then set it to placeholder
                            if ($name == '') {
                                $name = '[' . lang('Visitor') . ']';
                            }


                            // Amber tile, not the card accent. Orders and carts
                            // sit side by side now, and two lists of identically
                            // green rows read as one list split down the middle.
                            // The colour is the difference between what sold and
                            // what did not, so it carries the distinction.
                            $output_cart_rows .= pg_widget_row(array(
                                'href'  => $output_link_url,
                                'color' => '#f59e0b',
                                'badge' => '<i class="bi bi-cart"></i>',
                                'name'  => h($name),
                                'aside' => $total,
                                'meta'  => h($cart['reference_code'])
                                    . ' &middot; ' . get_relative_time(array('timestamp' => $cart['timestamp'])),
                            ));
                        }
                    } else {
                        $output_cart_rows = pg_widget_empty('bi-basket', lang(array('string' => 'There is no {var:1} right now.', 'vars' => lang('Shopping Cart'))));
                    }

                    // Orders per day for the last eight -- the figure the
                    // activity summary used to carry. Same status filter the
                    // list below uses, so the headline and the rows cannot
                    // disagree about what counts as an order.
                    $output_headline = pg_widget_headline(array(
                        'id' => 'w8_spark',
                        'rgb' => '5,150,105',
                        'unit' => lang('orders today'),
                        'series' => pg_activity_daily('orders', 'orders.order_date', " AND (orders.status = 'complete')"),
                    ));

                    // Orders and carts side by side rather than behind tabs. They
                    // are read together - an abandoned cart is only interesting
                    // next to what did convert - and a tab hides half the card
                    // behind a click that most operators never make. The fixed
                    // 240px panes are gone with them: each half now fills the
                    // card, so the widget matches every other one on the grid.
                    $output_data = '
                        <div class="card-body p-0 pg-split">
                            <div class="pg-split-half">
                                ' . $output_headline . '
                                <div class="pg-list">' . $output_order_rows . '</div>
                            </div>
                            <div class="pg-split-half">
                                ' . pg_widget_row_heading(lang('Carts')) . '
                                <div class="pg-list">' . $output_cart_rows . '</div>
                            </div>
                        </div>';

                    //return success json output
                    $response = array(
                        'status' => 'success',
                        'message' => 'Action Success',
                        'data' => $output_data,
                    );
                    echo encode_json($response);
                    exit();
                    break;


                } else {
                    $response = array(
                        'status' => 'error',
                        'message' => 'Access denied'
                    );
                    echo encode_json($response);
                    exit();
                    break;
                }

            case '9':
                if ((ECOMMERCE === true) && (ECOMMERCE_SHIPPING === true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))) {

                    // What counts as outstanding
                    // --------------------------
                    // A shipment is outstanding while a shippable line still
                    // has quantity that has not been shipped. Tracking numbers
                    // are deliberately not consulted: one order is regularly
                    // sent in two runs under a single carrier when stock
                    // arrives late, and the first number would otherwise mark
                    // the whole order as gone.
                    //
                    // order_items.ship_to_id is the shippable marker.
                    // add_order_item() writes it only when shipping is on AND
                    // products.shippable was 1 as the line was added, so a
                    // digital line in a mixed order carries 0 and can never
                    // join a recipient row. Reading products.shippable here
                    // instead would answer for today's catalogue rather than
                    // for the order as it was placed.
                    //
                    // ship_tos.complete = 1 skips recipient rows left behind by
                    // abandoned carts, and orders.type = 'online' skips
                    // counter sales, which are handed over rather than shipped.
                    //
                    // 'exported' belongs with 'complete': shipworks.php sets it
                    // when an order is pulled into fulfilment and writes the
                    // shipped quantity back only afterwards, so an exported
                    // order is exactly one that is waiting to leave. Sites on
                    // the export flow would otherwise see an empty card.

                    // Both quantity columns are INT UNSIGNED, so subtracting
                    // them raises "BIGINT UNSIGNED value is out of range" and
                    // aborts the query the moment a line has been recorded as
                    // over-shipped, which nothing stops update_order.php from
                    // accepting. Casting to SIGNED first lets GREATEST() floor
                    // the row at zero instead. Since PHP 8.1 mysqli throws on
                    // that error, so the card would take the dashboard with it.
                    //
                    // Save-for-later lines stay in order_items with the flag
                    // set. The column arrived in 2026.1.28, so probe for it
                    // before filtering, or the widget dies on older schemas.
                    $sql_saved_for_later = '';
                    if (db_value("SHOW COLUMNS FROM order_items LIKE 'saved_for_later'") != '') {
                        $sql_saved_for_later = " AND (order_items.saved_for_later = 0)";
                    }

                    // How far back to look
                    // --------------------
                    // A site that stops filling in shipping information does
                    // not stop taking orders. On those installs every untouched
                    // order stays outstanding for ever, the card fills with
                    // three to five hundred of them, and this week's real work
                    // is buried under last year's. An order that has sat here a
                    // month is not a shipment waiting to leave -- it is a site
                    // that does not use this screen -- and a card nobody can
                    // read is worse than a card that admits a horizon.
                    //
                    // The cut is on orders.order_date, the date the order was
                    // placed. An order nobody ever touched has nothing else to
                    // date it by; a shipping column would only date the orders
                    // that were already being handled.
                    //
                    // Anchored to midnight rather than "now minus thirty days",
                    // so a row does not drop off mid-morning as the clock
                    // passes the hour its order was placed at.
                    $pending_window_days = 30;
                    $pending_since = strtotime(date('Y-m-d')) - ($pending_window_days * 86400);
                    $sql_pending_window = " AND (orders.order_date >= " . $pending_since . ")";

                    // Headline figures, grouped per order rather than per
                    // recipient: the package count is the same either way, but
                    // orders.total must be summed once per order or a
                    // multi-recipient order would contribute its value twice.
                    // MAX(orders.total) rather than a bare column because
                    // ONLY_FULL_GROUP_BY does not always trace the functional
                    // dependency on orders.id through a join.
                    $pending_summary = db_item(
                        "SELECT
                            COALESCE(SUM(pending.pending_quantity), 0) AS package_count,
                            COALESCE(SUM(pending.order_total), 0) AS pending_value
                        FROM (
                            SELECT
                                orders.id,
                                MAX(orders.total) AS order_total,
                                SUM(GREATEST(CAST(order_items.quantity AS SIGNED) - CAST(order_items.shipped_quantity AS SIGNED), 0)) AS pending_quantity
                            FROM orders
                            INNER JOIN ship_tos ON (ship_tos.order_id = orders.id) AND (ship_tos.complete = '1')
                            INNER JOIN order_items ON order_items.ship_to_id = ship_tos.id
                            WHERE
                                (orders.status IN ('complete', 'exported'))
                                AND (orders.type = 'online')
                                $sql_pending_window
                                $sql_saved_for_later
                            GROUP BY orders.id
                            HAVING pending_quantity > 0
                        ) AS pending"
                    );

                    $package_count = (int) ($pending_summary['package_count'] ?? 0);
                    $pending_value = (int) ($pending_summary['pending_value'] ?? 0);

                    // Rows are grouped per recipient because the carrier lives
                    // on ship_tos: one order going to two addresses can leave
                    // by two different carriers, and a row that averaged them
                    // would name neither. One row over the display cap is
                    // fetched so we can tell whether to offer the full list.
                    $row_limit = 12;
                    $pending_shipments = db_items(
                        "SELECT
                            ship_tos.id AS ship_to_id,
                            MAX(ship_tos.ship_to_name) AS ship_to_name,
                            MAX(ship_tos.shipping_method_code) AS shipping_method_code,
                            MAX(shipping_methods.name) AS shipping_method_name,
                            orders.id AS order_id,
                            MAX(orders.order_number) AS order_number,
                            MAX(orders.order_date) AS order_date,
                            SUM(GREATEST(CAST(order_items.quantity AS SIGNED) - CAST(order_items.shipped_quantity AS SIGNED), 0)) AS pending_quantity
                        FROM ship_tos
                        INNER JOIN orders ON orders.id = ship_tos.order_id
                        INNER JOIN order_items ON order_items.ship_to_id = ship_tos.id
                        LEFT JOIN shipping_methods ON shipping_methods.id = ship_tos.shipping_method_id
                        WHERE
                            (orders.status IN ('complete', 'exported'))
                            AND (orders.type = 'online')
                            AND (ship_tos.complete = '1')
                            $sql_pending_window
                            $sql_saved_for_later
                        GROUP BY ship_tos.id, orders.id
                        HAVING pending_quantity > 0
                        ORDER BY MAX(orders.order_date) ASC, ship_tos.id ASC
                        LIMIT " . ($row_limit + 1)
                    );

                    $more_shipments_exist = (count($pending_shipments) > $row_limit);
                    if ($more_shipments_exist) {
                        $pending_shipments = array_slice($pending_shipments, 0, $row_limit);
                    }

                    // An order that ships to several addresses puts the same
                    // order number on more than one row. Count the rows per
                    // order so those rows can name their recipient and stay
                    // tellable apart; single-recipient rows say nothing extra.
                    $rows_per_order = array();
                    foreach ($pending_shipments as $shipment) {
                        $oid = $shipment['order_id'];
                        $rows_per_order[$oid] = ($rows_per_order[$oid] ?? 0) + 1;
                    }

                    // Fixed palette keyed by a hash of the carrier name, so a
                    // carrier keeps its colour across refreshes and installs
                    // without anyone having to configure one.
                    $carrier_colors = array('#f59e0b', '#8b5cf6', '#3b82f6', '#10b981', '#ec4899', '#14b8a6');

                    $output_rows = '';
                    if (!empty($pending_shipments)) {
                        foreach ($pending_shipments as $shipment) {
                            $carrier = trim((string) $shipment['shipping_method_name']);
                            if ($carrier === '') {
                                $carrier = trim((string) $shipment['shipping_method_code']);
                            }
                            if ($carrier === '') {
                                $carrier = lang('No Shipping Method');
                            }

                            $carrier_color = $carrier_colors[abs(crc32($carrier)) % count($carrier_colors)];
                            $shipment_packages = (int) $shipment['pending_quantity'];

                            $meta = '#' . h($shipment['order_number'])
                                . ' &middot; ' . h(lang(array(
                                    'string' => '{var:1} package{suffix:1}',
                                    'vars' => number_format($shipment_packages, 0, ',', '.'),
                                    'suffix' => ($shipment_packages == 1 ? '' : 's'),
                                )));

                            if (($rows_per_order[$shipment['order_id']] ?? 0) > 1) {
                                $meta .= ' &middot; ' . h($shipment['ship_to_name']);
                            }

                            // A real link rather than an onclick handler, so
                            // the row is reachable by keyboard and opens in a
                            // new tab on middle click like any other row here.
                            $output_rows .= pg_widget_row(array(
                                'href'  => 'view_order.php?id=' . (int) $shipment['order_id'],
                                'badge' => '<i class="bi bi-box-seam"></i>',
                                'color' => $carrier_color,
                                'name'  => h($carrier),
                                'meta'  => $meta,
                            ));
                        }

                        // Only offered when the list was actually cut short,
                        // so the link never implies there is more to see when
                        // the card already shows everything.
                        if ($more_shipments_exist) {
                            $output_rows .= '
                            <div class="pg-ship-more">
                                <a href="view_orders.php" class="small text-decoration-none">' . lang('All Orders') . '</a>
                            </div>';
                        }
                    } else {
                        // Not "everything has been shipped": outside the
                        // window this card has not looked, and on the very
                        // installs the window exists for there are hundreds
                        // sitting there. Say what was actually checked.
                        $output_rows = pg_widget_empty(
                            'bi-check2-circle',
                            lang(array(
                                'string' => 'Nothing waiting from the last {var:1} days.',
                                'vars' => $pending_window_days,
                            )),
                            'good');
                    }

                    // Package meter. Twelve slots is a readable width at the
                    // narrowest dashboard column; past that the count in the
                    // headline carries the magnitude and the meter just reads
                    // as full. Both dot states are styled in backend.src.css;
                    // only the on/off class is decided here.
                    $meter_slots = 12;
                    $meter_filled = min($package_count, $meter_slots);
                    $output_meter = '';
                    for ($slot = 0; $slot < $meter_slots; $slot++) {
                        $output_meter .= '<span' . ($slot < $meter_filled ? ' class="is-on"' : '') . '></span>';
                    }

                    // Two keys rather than one sentence: the entity between
                    // them is markup, and a translator should not have to carry
                    // it through to keep the line from breaking.
                    $output_summary =
                        lang(array(
                            'string' => 'package{suffix:1}',
                            'suffix' => ($package_count == 1 ? '' : 's'),
                        ))
                        . ' &middot; '
                        . lang(array(
                            'string' => 'worth {var:1}',
                            'vars' => BASE_CURRENCY_SYMBOL . number_format($pending_value / 100, 2, ',', '.'),
                        ));

                    $output_data = '
                    <div class="card-body p-0 overflow-x-hidden overflow-y-auto">
                        <div class="pg-ship-head">
                            <div class="d-flex align-items-baseline flex-wrap gap-2">
                                <span class="pg-ship-count">' . number_format($package_count, 0, ',', '.') . '</span>
                                <span class="pg-ship-summary text-muted">' . $output_summary . '</span>
                            </div>
                            <div class="pg-ship-meter">' . $output_meter . '</div>
                        </div>
                        <div class="pg-list">
                            ' . $output_rows . '
                        </div>
                    </div>';

                    $response = array(
                        'status' => 'success',
                        'message' => lang('Data Received successfully.'),
                        'data' => $output_data,
                    );
                    echo encode_json($response);
                    exit();
                    break;

                } else {
                    $response = array('status' => 'error', 'message' => 'Access denied');
                    echo encode_json($response);
                    exit();
                    break;
                }
            case '10':
                // if the user has access to contacts, then get contacts
                if (($user['role'] < 3) || ($user['manage_contacts'] == true)) {
                    $contacts = array();
                    // if the user is above a user role, then get the contacts in a certain way (for performance reasons)
                    if ($user['role'] < 3) {
                        $query = "SELECT
                                contacts.id,
                                contacts.first_name,
                                contacts.last_name,
                                contacts.email_address,
                                contacts.timestamp,
                                user.user_username as username
                            FROM contacts
                            LEFT JOIN user ON contacts.user = user.user_id
                            ORDER BY contacts.timestamp DESC
                            LIMIT 20";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                        // loop through the result in order to prepare array of items
                        while ($row = mysqli_fetch_assoc($result)) {
                            $contacts[] = $row;
                        }

                        // else the user has a user role, so get the contacts in a different way

                    } else {
                        $contact_groups = get_items_user_can_edit('contact_groups', $user['id']);

                        // if the user has access to at least one contact group, then get contacts
                        if (count($contact_groups) > 0) {
                            $sql_where = '';

                            // loop through the contact groups in order to prepare where SQL statement
                            foreach ($contact_groups as $contact_group) {
                                // if there is already where content then add an or
                                if ($sql_where != '') {
                                    $sql_where .= ' OR ';
                                }

                                // add condition for this contact group
                                $sql_where .= '(contacts_contact_groups_xref.contact_group_id = ' . $contact_group . ')';
                            }

                            $query = "SELECT
                                    contacts.id,
                                    contacts.first_name,
                                    contacts.last_name,
                                    contacts.email_address,
                                    contacts.timestamp,
                                    user.user_username as username
                                FROM contacts
                                LEFT JOIN user ON contacts.user = user.user_id
                                LEFT JOIN contacts_contact_groups_xref ON contacts.id = contacts_contact_groups_xref.contact_id
                                WHERE $sql_where
                                GROUP BY contacts.id
                                ORDER BY contacts.timestamp DESC
                                LIMIT 25";
                            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                            // loop through the result in order to prepare array of items
                            while ($row = mysqli_fetch_assoc($result)) {
                                $contacts[] = $row;
                            }
                        }
                    }

                    if (!empty($contacts)) {
                        // loop through the contacts, in order to output rows
                        foreach ($contacts as $contact) {
                            $output_link_url = 'edit_contact.php?id=' . $contact['id'];
                            $name = '';
                            // if there is a first name, then add it to the name
                            if ($contact['first_name'] != '') {
                                $name .= $contact['first_name'];
                            }

                            // if there is a last name, then add it to the name
                            if ($contact['last_name'] != '') {
                                // if the name is not blank, then add space
                                if ($name != '') {
                                    $name .= ' ';
                                }

                                $name .= $contact['last_name'];
                            }

                            // Build initials avatar
                            $initials = strtoupper(substr($contact['first_name'], 0, 1) . substr($contact['last_name'], 0, 1));
                            if ($initials == '')
                                $initials = strtoupper(substr($contact['username'], 0, 1));
                            if ($initials == '')
                                $initials = '?';

                            $output_rows .= pg_widget_row(array(
                                'href'  => $output_link_url,
                                'badge' => h($initials),
                                'name'  => h($name ?: $contact['username']),
                                'aside' => get_relative_time(array('timestamp' => $contact['timestamp'])),
                                'meta'  => h($contact['email_address']),
                            ));
                        }
                    } else {
                        $output_rows = pg_widget_empty('bi-person-lines-fill', lang(array('string' => 'There is no {var:1} right now.', 'vars' => lang('Contact'))));
                    }
                    $output_data = '
                        <div class="card-body p-0 overflow-x-hidden overflow-y-auto">
                            ' . pg_widget_headline(array(
                                'id' => 'w10_spark',
                                'rgb' => '99,102,241',
                                'unit' => lang('contacts today'),
                                'series' => pg_activity_daily('contacts', 'contacts.timestamp'),
                            )) . '
                            <div class="pg-list">' . $output_rows . '</div>
                        </div>';

                    //return success json output
                    $response = array(
                        'status' => 'success',
                        'message' => 'Action Success',
                        'data' => $output_data,
                    );
                    echo encode_json($response);
                    exit();
                } else {
                    $response = array(
                        'status' => 'error',
                        'message' => 'Access denied'
                    );
                    echo encode_json($response);
                    exit();
                    break;
                }

            case '11':
                if ($user['role'] < 3) {
                    $sql_where = "";
                    // if the user is not an administrator, then prepare where condition for role
                    if ($user['role'] > 0) {
                        $sql_where = "WHERE user.user_role > '" . $user['role'] . "'";
                    }
                    $users = array();
                    $query = "SELECT
                            user.user_id as id,
                            user.user_username as username,
                            user.user_email as email_address,
                            user.user_timestamp as timestamp,
                            last_modified_user.user_username as last_modified_username
                        FROM user
                        LEFT JOIN user as last_modified_user ON user.user_user = last_modified_user.user_id
                        $sql_where
                        ORDER BY user.user_timestamp DESC
                        LIMIT 20";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                    // loop through the result in order to prepare array of items
                    while ($row = mysqli_fetch_assoc($result)) {
                        $users[] = $row;
                    }

                    $output_user_rows = '';
                    if (!empty($users)) {
                        // loop through the users, in order to output rows
                        // we are using the variable name $recent_user instead of $user, because $user is a reserved variable for storing user information
                        // there was a bug where the start page link in the header would not appear because were using the $user variable
                        foreach ($users as $recent_user) {
                            $output_link_url = 'edit_user.php?id=' . $recent_user['id'];
                            $u_initial = strtoupper(substr($recent_user['username'], 0, 1));
                            if ($u_initial == '')
                                $u_initial = '?';

                            $output_rows .= pg_widget_row(array(
                                'href'  => $output_link_url,
                                'badge' => h($u_initial),
                                'name'  => h($recent_user['username']),
                                'aside' => get_relative_time(array('timestamp' => $recent_user['timestamp'])),
                                'meta'  => h($recent_user['email_address']),
                            ));
                        }
                    } else {
                        $output_rows = pg_widget_empty('bi-people', lang(array('string' => 'There is no {var:1} right now.', 'vars' => lang('User'))));
                    }

                    $output_data = '
                        <div class="card-body p-0 overflow-x-hidden overflow-y-auto">
                            <div class="pg-list">' . $output_rows . '</div>
                        </div>';

                    //return success json output
                    $response = array(
                        'status' => 'success',
                        'message' => 'Action Success',
                        'data' => $output_data,
                    );
                    echo encode_json($response);
                    exit();
                    break;

                } else {
                    $response = array(
                        'status' => 'error',
                        'message' => 'Access denied'
                    );
                    echo encode_json($response);
                    exit();
                    break;
                }

            case '12':
                // if e-commerce is enabled and the user has access to manage e-commerce
                if ((ECOMMERCE == true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))) {
                    $out_of_stock_products = array();
                    $query = "SELECT
                                products.id as id,
                                products.name as name,
                                products.enabled,
	                			products.image_name  as image_name,
                                products.inventory as inventory,
                                products.inventory_quantity as inventory_quantity,
                                products.short_description as short_description,
                                products.price as price,
                                products.taxable as taxable,
                                products.form_name as form_name,
                                products.seo_score as seo_score,
                                user.user_username as user,
                                products.out_of_stock_timestamp as timestamp
                            FROM products
                            LEFT JOIN user ON products.user = user.user_id
                            WHERE out_of_stock = '1'
                            ORDER BY timestamp DESC
                            LIMIT 20";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                    // loop through the result in order to prepare array of items
                    while ($row = mysqli_fetch_assoc($result)) {
                        $out_of_stock_products[] = $row;
                    }

                    if (!empty($out_of_stock_products)) {
                        // loop through the orders, in order to output rows
                        foreach ($out_of_stock_products as $out_of_stock_product) {
                            $output_link_url = 'edit_product.php?id=' . $out_of_stock_product['id'];

                            $has_image = !empty($out_of_stock_product['image_name']);
                            // A product photo fills the tile; without one the
                            // tile falls back to the card accent and the icon.
                            $output_rows .= pg_widget_row(array(
                                'href'  => $output_link_url,
                                'badge' => $has_image
                                    ? '<img src="' . PATH . h($out_of_stock_product['image_name']) . '" alt="">'
                                    : '<i class="bi bi-exclamation-diamond"></i>',
                                'name'  => h($out_of_stock_product['name']),
                                'aside' => prepare_amount($out_of_stock_product['price'] / 100),
                                'meta'  => h($out_of_stock_product['short_description'])
                                    . ' &middot; ' . get_relative_time(array('timestamp' => $out_of_stock_product['timestamp'])),
                            ));
                        }

                    } else {
                        $output_rows = pg_widget_empty('bi-check2-circle', lang(array('string' => 'There is no {var:1} right now.', 'vars' => lang('Out of Stock Product'))), 'good');
                    }


                    $output_data = '
                        <div class="card-body p-0 overflow-x-hidden overflow-y-auto">
                            <div class="pg-list">' . $output_rows . '</div>
                        </div>';

                    //return success json output
                    $response = array(
                        'status' => 'success',
                        'message' => 'Action Success',
                        'data' => $output_data,
                    );
                    echo encode_json($response);
                    exit();
                    break;

                } else {
                    $response = array(
                        'status' => 'error',
                        'message' => 'Access denied'
                    );
                    echo encode_json($response);
                    exit();
                    break;
                }

            case '13':
                // if the user has access to manage forms, then get submitted forms
                if (($user['role'] < 3) || ($user['manage_forms'] == true)) {
                    $submitted_forms = array();
                    // if the user is above a user role, then get the submitted forms in a certain way
                    if ($user['role'] < 3) {
                        $query = "SELECT
                                forms.id,
                                forms.reference_code as reference_code,
                                custom_form_pages.form_name,
                                user.user_username as username,
                                contacts.first_name,
                                contacts.last_name,
                                forms.last_modified_timestamp as timestamp,
                                last_modified_user.user_username as last_modified_username
                            FROM forms
                            LEFT JOIN custom_form_pages ON forms.page_id = custom_form_pages.page_id
                            LEFT JOIN user ON forms.user_id = user.user_id
                            LEFT JOIN contacts ON forms.contact_id = contacts.id
                            LEFT JOIN user as last_modified_user ON forms.last_modified_user_id = last_modified_user.user_id
                            ORDER BY forms.last_modified_timestamp DESC
                            LIMIT 200";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                        // loop through the result in order to prepare array of items
                        while ($row = mysqli_fetch_assoc($result)) {
                            $submitted_forms[] = $row;
                        }

                        // else the user has a user role, so get the submitted forms in a different way

                    } else {
                        $custom_forms = array();
                        $folders_that_user_has_access_to = get_folders_that_user_has_access_to($user['id']);

                        // get all custom forms in order to determine which the user has access to
                        $query = "SELECT
                                page_id,
                                page_folder as folder_id
                            FROM page
                            WHERE " . pg_form_page_sql('page') . "";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                        // loop through the result in order to prepare array of items
                        while ($row = mysqli_fetch_assoc($result)) {
                            // if user has access to the custom form then add it to array
                            if (check_folder_access_in_array($row['folder_id'], $folders_that_user_has_access_to) == true) {
                                $custom_forms[] = $row;
                            }
                        }

                        // if the user has access to at least one custom form, then get submitted forms
                        if (count($custom_forms) > 0) {
                            $sql_where = "";

                            // loop through the custom forms in order to prepare where SQL conditions
                            foreach ($custom_forms as $custom_form) {
                                // if there is already where content then add an or
                                if ($sql_where != "") {
                                    $sql_where .= " OR ";
                                }

                                // add condition for this custom form
                                $sql_where .= "(forms.page_id = '" . $custom_form['page_id'] . "')";
                            }

                            $query = "SELECT
                                    forms.id,
                                    custom_form_pages.form_name,
                                    user.user_username as username,
                                    forms.last_modified_timestamp as timestamp,
                                    last_modified_user.user_username as last_modified_username
                                FROM forms
                                LEFT JOIN custom_form_pages ON forms.page_id = custom_form_pages.page_id
                                LEFT JOIN user ON forms.user_id = user.user_id
                                LEFT JOIN user as last_modified_user ON forms.last_modified_user_id = last_modified_user.user_id
                                WHERE $sql_where
                                ORDER BY forms.last_modified_timestamp DESC
                                LIMIT 25";
                            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                            // loop through the result in order to prepare array of items
                            while ($row = mysqli_fetch_assoc($result)) {
                                $submitted_forms[] = $row;
                            }
                        }
                    }

                    // ── Headline ────────────────────────────────────────────────────────────
                    //
                    // submitted_timestamp, not last_modified_timestamp. The
                    // three KPI tiles that used to sit here counted forms that
                    // had been *edited*, which put an old form touched today
                    // into today's activity and made this figure mean something
                    // different from the orders and contacts figures beside it
                    // on the dashboard.
                    //
                    // It is also a real query now rather than a tally of the
                    // rows this widget happened to fetch, which was capped and
                    // therefore undercounted a busy day.
                    $output_kpi = pg_widget_headline(array(
                        'id' => 'w13_spark',
                        'rgb' => '14,165,233',
                        'unit' => lang('forms today'),
                        'series' => pg_activity_daily('forms', 'forms.submitted_timestamp'),
                    ));

                    // ── Rows ─────────────────────────────────────────────────────────────────
                    $output_rows = '';
                    $display_forms = array_slice($submitted_forms, 0, 20);

                    if (!empty($display_forms)) {
                        foreach ($display_forms as $submitted_form) {
                            $link = 'edit_submitted_form.php?id=' . $submitted_form['id'];

                            // Prefer contact name, fall back to username, then [Unknown]
                            $contact_name = trim(
                                ($submitted_form['first_name'] ?? '') . ' ' . ($submitted_form['last_name'] ?? '')
                            );
                            if ($contact_name == '') {
                                $contact_name = ($submitted_form['username'] != '')
                                    ? $submitted_form['username']
                                    : '[' . lang('Unknown') . ']';
                            }

                            $form_name = h($submitted_form['form_name']);
                            $ref = isset($submitted_form['reference_code']) ? h($submitted_form['reference_code']) : '';
                            $time = get_relative_time(array('timestamp' => $submitted_form['timestamp']));

                            $output_rows .= pg_widget_row(array(
                                'href'  => $link,
                                'badge' => '<i class="bi bi-file-earmark-text"></i>',
                                'name'  => h($contact_name),
                                'aside' => $time,
                                'meta'  => $form_name . ($ref != '' ? ' &middot; ' . $ref : ''),
                            ));
                        }
                    } else {
                        $output_rows = pg_widget_empty('bi-file-earmark-text', lang(array('string' => 'There is no {var:1} right now.', 'vars' => lang('Form'))));
                    }

                    $output_data = '
                        <div class="card-body p-0 overflow-x-hidden overflow-y-auto">
                            ' . $output_kpi . '
                            <div class="pg-list">' . $output_rows . '</div>
                        </div>';

                    //return success json output
                    $response = array(
                        'status' => 'success',
                        'message' => 'Action Success',
                        'data' => $output_data,
                    );
                    echo encode_json($response);
                    exit();
                    break;

                } else {
                    $response = array(
                        'status' => 'error',
                        'message' => 'Access denied'
                    );
                    echo encode_json($response);
                    exit();
                    break;
                }

            case '14':
                // this widget is just for kodpen customers. many api options are removed for security reason.
                // if the user is admin and has a SUBSCRIPTION_ID
                if (($user['role'] < 1) && (SUBSCRIPTION_ID != '') && (SUBSCRIPTION_ID != ' ') && (SUBSCRIPTION_ID != NULL)) {
                    $API = '59593DS72233483322T669223344';
                    if ($API != NULL and $API != '') {
                        $request = array();
                        $request['hostname'] = HOSTNAME_SETTING;
                        $request['url'] = URL_SCHEME . HOSTNAME_SETTING . PATH;
                        $request['version'] = VERSION;
                        $request['edition'] = EDITION;
                        $request['uname'] = function_exists('php_uname') ? php_uname() : PHP_OS; // disable_functions on some hosts
                        $request['os'] = PHP_OS;
                        $request['web_server'] = $_SERVER['SERVER_SOFTWARE'];
                        $request['php_version'] = phpversion();
                        $request['mysql_version'] = db("SELECT VERSION()");
                        $request['installer'] = INSTALLER;
                        $request['private_label'] = PRIVATE_LABEL;
                        $data = encode_json($request);
                        $REQUEST = 'get';
                        $ch = curl_init();
                        // Identify this installation on outgoing requests. Sent with no
                        // User-Agent, a request looks like an anonymous client to the receiving
                        // server's firewall and gets rejected — which is how Pinegrap ended up
                        // blocking its own licence and update checks.
                        curl_setopt($ch, CURLOPT_USERAGENT, function_exists('pinegrap_user_agent') ? pinegrap_user_agent() : 'Pinegrap');
                        curl_setopt($ch, CURLOPT_URL, 'https://www.kodpen.com/api2?API=' . $API . '&REQUEST=' . $REQUEST . '&SECRET=' . SUBSCRIPTION_ID);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
                        // Verify the certificate. See pg_curl_tls() for why this matters most
                        // on the update and licence channel.
                        pg_curl_tls($ch);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
                        curl_setopt($ch, CURLOPT_POST, 1);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                            'Content-Type: application/json',
                            'Content-Length: ' . strlen($data)
                        ));
                        // if there is a proxy address, then send cURL request through proxy
                        if (PROXY_ADDRESS != '') {
                            curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
                            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                            curl_setopt($ch, CURLOPT_PROXY, PROXY_ADDRESS);
                        }
                        $response = curl_exec($ch);
                        $curl_errno = curl_errno($ch);
                        $curl_error = curl_error($ch);
                        curl_close($ch);
                        $data = decode_json($response);

                        // The remote service can be unreachable or answer with something that is not JSON,
                        // in which case decode_json() returns null.
                        if (is_array($data) == false) {
                            $data = array();
                        }

                        foreach (array(
                            'D_name',
                            'D_Host',
                            'D_start_d',
                            'D_end_d',
                            'Hosting',
                            'H_Host',
                            'H_Domain',
                            'H_start_d',
                            'H_end_d',
                            'SSL_author',
                            'SSL_Domain',
                            'SSL_start_d',
                            'SSL_end_d',
                            'P_KEY',
                            'P_start_d',
                            'P_end_d') as $remote_key) {
                            if (isset($data[$remote_key]) == false) {
                                $data[$remote_key] = '';
                            }
                        }

                        $D_name = $data['D_name'];
                        $D_Host = $data['D_Host'];
                        $D_start_d = $data['D_start_d'];
                        $D_end_d = $data['D_end_d'];
                        $Hosting = $data['Hosting'];
                        $H_Host = $data['H_Host'];
                        $H_Domain = $data['H_Domain'];
                        $H_start_d = $data['H_start_d'];
                        $H_end_d = $data['H_end_d'];
                        $SSL_author = $data['SSL_author'];
                        $SSL_Domain = $data['SSL_Domain'];
                        $SSL_start_d = $data['SSL_start_d'];
                        $SSL_end_d = $data['SSL_end_d'];
                        $P_KEY = $data['P_KEY'];
                        $P_start_d = $data['P_start_d'];
                        $P_end_d = $data['P_end_d'];

                        $today = date_create(date("d-m-Y"));
                        $D_start_d_formatted = date_create(date("d-m-Y", strtotime($D_start_d)));
                        $D_end_d_formatted = date_create(date("d-m-Y", strtotime($D_end_d)));
                        $H_start_d_formatted = date_create(date("d-m-Y", strtotime($H_start_d)));
                        $H_end_d_formatted = date_create(date("d-m-Y", strtotime($H_end_d)));
                        $SSL_start_d_formatted = date_create(date("d-m-Y", strtotime($SSL_start_d)));
                        $SSL_end_d_formatted = date_create(date("d-m-Y", strtotime($SSL_end_d)));
                        $P_start_d_formatted = date_create(date("d-m-Y", strtotime($P_start_d)));
                        $P_end_d_formatted = date_create(date("d-m-Y", strtotime($P_end_d)));

                        $D_interval = date_diff($D_end_d_formatted, $today);
                        $D_interval_dif = date_diff($D_end_d_formatted, $D_start_d_formatted);
                        $D_countdown = $D_interval->format('%a');
                        $D_day_dif = $D_interval_dif->format('%a');
                        $H_interval = date_diff($H_end_d_formatted, $today);
                        $H_interval_dif = date_diff($H_end_d_formatted, $H_start_d_formatted);
                        $H_countdown = $H_interval->format('%a');
                        $H_day_dif = $H_interval_dif->format('%a');
                        $SSL_interval = date_diff($SSL_end_d_formatted, $today);
                        $SSL_interval_dif = date_diff($SSL_end_d_formatted, $SSL_start_d_formatted);
                        $SSL_countdown = $SSL_interval->format('%a');
                        $SSL_day_dif = $SSL_interval_dif->format('%a');
                        $P_interval = date_diff($P_end_d_formatted, $today);
                        $P_interval_dif = date_diff($P_end_d_formatted, $P_start_d_formatted);
                        $P_countdown = $P_interval->format('%a');
                        $P_day_dif = $P_interval_dif->format('%a');

                        // Render a single subscription row (Bootstrap Icons, linear progress bar)
                        function render_sub_row($end_date, $total_days, $remaining_days, $bs_icon, $heading, $subline = '')
                        {
                            if (!$end_date)
                                return '';
                            $today_dt = date_create(date('Y-m-d'));
                            $end_dt = date_create(date('Y-m-d', strtotime($end_date)));
                            $is_over = ($today_dt >= $end_dt);
                            if ($is_over) {
                                $color = '#a1a1a1';
                                $pct = 100;
                                $badge_text = lang('Over');
                                $opacity = ' opacity-50';
                            } else {
                                $rd = (int) $remaining_days;
                                if ($rd <= 7) {
                                    $color = '#ef4444';
                                } elseif ($rd <= 30) {
                                    $color = '#f59e0b';
                                } else {
                                    $color = '#10b981';
                                }
                                $pct = ($total_days > 0) ? min(100, (int) round((($total_days - $rd) / $total_days) * 100)) : 0;
                                $badge_text = $rd . ' ' . lang('days remaining');
                                $opacity = '';
                            }
                            return '
                            <div class="px-2 py-2 border-bottom' . $opacity . '">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <div class="d-flex align-items-center overflow-hidden me-2">
                                        <i class="bi ' . $bs_icon . ' me-2 flex-shrink-0" style="color:' . $color . ';font-size:15px"></i>
                                        <span class="fw-semibold text-truncate" style="font-size:13px">' . h($heading) . '</span>
                                    </div>
                                    <span class="badge rounded-pill flex-shrink-0" style="background:' . $color . '22;color:' . $color . ';font-size:10px;white-space:nowrap">' . $badge_text . '</span>
                                </div>'
                                . ($subline ? '<small class="text-muted d-block mb-1" style="padding-left:23px">' . h($subline) . '</small>' : '') .
                                '<div class="rounded-pill ms-1" style="height:4px;background:#e5e7eb;overflow:hidden">
                                    <div class="rounded-pill h-100" style="width:' . $pct . '%;background:' . $color . '"></div>
                                </div>
                            </div>';
                        }

                        $OUTPUT_DOMAIN_ROWS = '';
                        if ($D_Host && $D_name) {
                            $OUTPUT_DOMAIN_ROWS = render_sub_row($D_end_d, $D_day_dif, $D_countdown, 'bi-globe2', lang('Domain'), $D_name);
                        }
                        $OUTPUT_HOSTING_ROWS = '';
                        if ($H_Host && $Hosting) {
                            $OUTPUT_HOSTING_ROWS = render_sub_row($H_end_d, $H_day_dif, $H_countdown, 'bi-server', lang('Hosting'), $H_Host);
                        }
                        $OUTPUT_SSL_ROWS = '';
                        if ($SSL_author && $SSL_Domain) {
                            $OUTPUT_SSL_ROWS = render_sub_row($SSL_end_d, $SSL_day_dif, $SSL_countdown, 'bi-shield-lock-fill', lang('SSL Certificate'), $SSL_Domain);
                        }
                        $OUTPUT_P_ROWS = '';
                        if ($P_KEY) {
                            $OUTPUT_P_ROWS = render_sub_row($P_end_d, $P_day_dif, $P_countdown, 'bi-award-fill', lang('Software License'));
                        }

                        $output_data = '
                        <div class="card-body p-0" style="overflow-x:hidden;overflow-y:auto">
                            ' . $OUTPUT_DOMAIN_ROWS . $OUTPUT_HOSTING_ROWS . $OUTPUT_SSL_ROWS . $OUTPUT_P_ROWS . '
                        </div>';

                        //return success json output
                        $response = array(
                            'status' => 'success',
                            'message' => 'Action Success',
                            'data' => $output_data,
                        );
                        echo encode_json($response);
                        exit();
                        break;
                    }
                } else {
                    $response = array(
                        'status' => 'error',
                        'message' => 'Access denied'
                    );
                    echo encode_json($response);
                    exit();
                    break;
                }
            case '15':
                if ((ECOMMERCE === true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))) {

                    $today = date('Y-m-d');
                    $deadline = date('Y-m-d', strtotime('+7 days'));

                    // Single query: fetch all relevant offers ordered by end_date
                    $all_offers = db_items(
                        "SELECT offers.id, offers.code, offers.description, offers.status,
                                offers.start_date, offers.end_date
                         FROM offers
                         ORDER BY offers.end_date ASC
                         LIMIT 60"
                    );

                    $expiring = array();
                    $active = array();
                    $expired = array();

                    foreach ($all_offers as $offer) {
                        if ($offer['end_date'] < $today) {
                            if (count($expired) < 20)
                                $expired[] = $offer;
                        } elseif (
                            $offer['status'] === 'enabled'
                            && $offer['start_date'] <= $today
                            && $offer['end_date'] <= $deadline
                        ) {
                            if (count($expiring) < 20)
                                $expiring[] = $offer;
                        } elseif (
                            $offer['status'] === 'enabled'
                            && $offer['start_date'] <= $today
                        ) {
                            if (count($active) < 20)
                                $active[] = $offer;
                        }
                    }

                    // Sort expired by end_date DESC (most recently expired first)
                    usort($expired, function ($a, $b) {
                        return strcmp($b['end_date'], $a['end_date']);
                    });

                    // "Automatic - cart is 100.00 or more -> %100 off shipping":
                    // the same sentence the offer list and the editor show, so
                    // an offer reads the same wherever it appears. Built for
                    // every row at once - a load per offer would be four
                    // queries times sixty.
                    require_once(dirname(__FILE__) . '/edit_offer_f.php');
                    $offer_sentences = _pg_offer_sentences(array_merge(
                        array_column($expiring, 'id'),
                        array_column($active, 'id'),
                        array_column($expired, 'id')));

                    $output_rows = '';

                    // ── Section: Expiring Soon (only shown when non-empty) ────
                    if (!empty($expiring)) {
                        $output_rows .= pg_widget_row_heading('<i class="bi bi-alarm-fill text-danger"></i> <span class="text-danger">' . lang('Expiring Soon') . '</span>', lang('Days Left'));
                        foreach ($expiring as $offer) {
                            $days_left = max(0, (int) ceil((strtotime($offer['end_date']) - strtotime($today)) / 86400));
                            // Three days or fewer is the point at which an offer
                            // needs attention today rather than this week, so the
                            // tile turns red instead of amber.
                            $badge_color = $days_left <= 3 ? '#ef4444' : '#f59e0b';
                            $output_rows .= pg_widget_row(array(
                                'href'  => 'edit_offer.php?id=' . $offer['id'],
                                'color' => $badge_color,
                                'badge' => '<i class="bi bi-alarm"></i>',
                                'name'  => h($offer['code'] !== '' ? $offer['code'] : lang('(no code)')),
                                'aside' => $days_left,
                                'meta'  => h(isset($offer_sentences[(int) $offer['id']]) ? $offer_sentences[(int) $offer['id']] : '—'),
                            ));
                        }
                    }

                    // ── Section: Active Offers (only shown when non-empty) ────
                    if (!empty($active)) {
                        $output_rows .= pg_widget_row_heading('<i class="bi bi-tag-fill text-success"></i> <span class="text-success">' . lang('Active Offers') . '</span>', lang('Days Left'));
                        foreach ($active as $offer) {
                            $days_left = max(0, (int) ceil((strtotime($offer['end_date']) - strtotime($today)) / 86400));
                            $output_rows .= pg_widget_row(array(
                                'href'  => 'edit_offer.php?id=' . $offer['id'],
                                'color' => '#10b981',
                                'badge' => '<i class="bi bi-tag-fill"></i>',
                                'name'  => h($offer['code'] !== '' ? $offer['code'] : lang('(no code)')),
                                'aside' => $days_left,
                                'meta'  => h(isset($offer_sentences[(int) $offer['id']]) ? $offer_sentences[(int) $offer['id']] : '—'),
                            ));
                        }
                    }

                    // ── Section: Expired (only shown when non-empty) ──────────
                    if (!empty($expired)) {
                        $output_rows .= pg_widget_row_heading(lang('Expired'), lang('End Date'));
                        foreach ($expired as $offer) {
                            $output_rows .= pg_widget_row(array(
                                'href'  => 'edit_offer.php?id=' . $offer['id'],
                                'color' => '#94a3b8',
                                'badge' => '<i class="bi bi-tag"></i>',
                                'name'  => h($offer['code'] !== '' ? $offer['code'] : lang('(no code)')),
                                'aside' => h($offer['end_date']),
                                'meta'  => h(isset($offer_sentences[(int) $offer['id']]) ? $offer_sentences[(int) $offer['id']] : '—'),
                            ));
                        }
                    }

                    // ── Global empty state ────────────────────────────────────
                    if ($output_rows === '') {
                        $output_rows = pg_widget_empty(
                            'bi-tag',
                            lang(array('string' => 'There is no {var:1} right now.', 'vars' => lang('Offer'))));
                    }

                    $output_data = '
                    <div class="card-body p-0 overflow-x-hidden overflow-y-auto">
                        <div class="pg-list">' . $output_rows . '</div>
                    </div>';

                    $response = array(
                        'status' => 'success',
                        'message' => lang('Data Received successfully.'),
                        'data' => $output_data,
                    );
                    echo encode_json($response);
                    exit();
                    break;

                } else {
                    $response = array(
                        'status' => 'error',
                        'message' => 'Access denied'
                    );
                    echo encode_json($response);
                    exit();
                    break;
                }
            case '16':
                // if e-commerce is enabled and the user has access to manage e-commerce
                if ((ECOMMERCE == true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))) {

                    // get all of the currency information. Join user_id with username
                    $query = "SELECT
                        currencies.id,
                        currencies.name,
                        currencies.base,
                        currencies.code,
                        currencies.symbol,
                        currencies.exchange_rate,
                        currencies.created_user_id,
                        currencies.created_timestamp,
                        currencies.last_modified_user_id,
                        currencies.last_modified_timestamp,
                        last_modified_user.user_username as last_modified_username
                    FROM currencies
                    LEFT JOIN user as last_modified_user ON currencies.last_modified_user_id = last_modified_user.user_id
                    ORDER BY base DESC,name DESC LIMIT 20";

                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    while ($row = mysqli_fetch_assoc($result)) {
                        $currencies[] = $row;
                    }
                    if (!empty($currencies)) {
                        // if there is at least one result to display  
                        foreach ($currencies as $currency) {

                            $output_link_url = 'edit_currency.php?id=' . $currency['id'] . '&amp;send_to=' . h(escape_javascript(urlencode(REQUEST_URL)));
                            if ($currency['base'] != 1) {
                                $rate_display = ((float) $currency['exchange_rate'] > 0) ? number_format((1 / $currency['exchange_rate']), 5) : '-';
                                $output_rows .= pg_widget_row(array(
                                    'href'  => $output_link_url,
                                    'badge' => $currency['symbol'],
                                    'name'  => h($currency['code']) . ' &mdash; ' . h($currency['name']),
                                    'aside' => h($currency['exchange_rate']),
                                    'meta'  => '1 ' . $currency['symbol'] . ' = ' . $rate_display . ' ' . BASE_CURRENCY_SYMBOL,
                                ));
                            }
                        }
                    } else {
                        $output_rows = pg_widget_empty('bi-currency-exchange', lang(array('string' => 'There is no {var:1} right now.', 'vars' => lang('Currency'))));
                    }

                    $output_data = '
                        <div class="card-body p-0 overflow-x-hidden overflow-y-auto">
                            <div class="pg-list">' . $output_rows . '</div>
                        </div>';

                    //return success json output
                    $response = array(
                        'status' => 'success',
                        'message' => 'Action Success',
                        'data' => $output_data,
                    );
                    echo encode_json($response);
                    exit();
                    break;
                } else {
                    $response = array(
                        'status' => 'error',
                        'message' => 'Access denied'
                    );
                    echo encode_json($response);
                    exit();
                    break;
                }

            case '17':
                if ($user['role'] < 3) {

                    $query = "SELECT 
                                log_id, 
                                log_description, 
                                log_ip, 
                                log_user, 
                                log_timestamp 
                              FROM log 
                              ORDER BY log_timestamp DESC LIMIT 20";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed');
                    while ($row = mysqli_fetch_assoc($result)) {
                        $site_logs[] = $row;
                    }

                    // if there is at least one result to display
                    if (!empty($site_logs)) {
                        foreach ($site_logs as $site_log) {
                            $log_id = $site_log['log_id'];
                            $log_timestamp = $site_log['log_timestamp'];
                            $log_description = $site_log['log_description'];
                            $log_ip = $site_log['log_ip'];
                            $log_user = $site_log['log_user'];
                            // if the username is blank, then set to UNKNOWN
                            if ($log_user == '') {
                                $log_user = lang('UNKNOWN');
                            }
                            // output style row
                            $output_rows .= pg_widget_row(array(
                                'href'  => 'view_log.php',
                                'badge' => '<i class="bi bi-journal-text"></i>',
                                'name'  => h($log_user),
                                'aside' => get_relative_time(array('timestamp' => $log_timestamp)),
                                'meta'  => convert_text_to_html($log_description) . ' &middot; IP ' . h($log_ip),
                            ));

                        }
                    } else {
                        $output_rows = pg_widget_empty('bi-journal-text', lang(array('string' => 'There is no {var:1} right now.', 'vars' => lang('Site Log'))));
                    }




                    $output_data = '
                        <div class="card-body p-0 overflow-x-hidden overflow-y-auto">
                            <div class="pg-list">' . $output_rows . '</div>
                        </div>';

                    //return success json output
                    $response = array(
                        'status' => 'success',
                        'message' => 'Action Success',
                        'data' => $output_data,
                    );
                    echo encode_json($response);
                    exit();
                    break;
                } else {
                    $response = array(
                        'status' => 'error',
                        'message' => 'Access denied'
                    );
                    echo encode_json($response);
                    exit();
                    break;
                }

            case '18':
                // ── File Management ─────────────────────────────────────
                //
                // Two lists, in the order an operator acts on them: images
                // that can still be made lighter without changing how they
                // look, then the heaviest files on the site whatever their
                // type.
                //
                // Keeps the id of the Admin Notes widget it replaced, so a
                // dashboard whose stored order already contains "18" picks
                // this up in the same slot with no reset and no migration.
                //
                // Scoped to what the user may actually edit, so the widget
                // never advertises a file that answers with access denied on
                // click:
                //
                //   0 administrator  everything
                //   1 designer       everything
                //   2 manager        design files excluded — view_files.php
                //                    and optimize.php both refuse them above
                //                    role 1, so listing them would only
                //                    produce a dead end
                //   3 contributor    design files excluded, and narrowed to
                //                    the folders granted with edit rights
                //
                // Archived folders are left out for everyone, matching the
                // default view_files.php listing.

                // Above this an image is too heavy for a web page however
                // well it is compressed, so it is reported even when the row
                // is already flagged optimized. Optimizing strips metadata
                // and recompresses; it does not resize, and a 6000 px photo
                // stays a 6000 px photo. Those rows get a resize suggestion
                // instead of an optimize button, because that is the only
                // thing left that would help.
                $fm_large_image_bytes = 3 * 1024 * 1024;

                // The optimize list is the one that gets worked through, so
                // it carries the longer allowance. The size list only has to
                // answer "what is taking up the room", which the top few do.
                $fm_optimize_limit = 10;
                $fm_largest_limit  = 5;

                // Exactly what optimize_image() accepts. 'tif' is absent on
                // purpose: optimize.php answers that spelling with "we don't
                // support optimizing that type of file", so offering the
                // button for one would hand the operator a guaranteed error.
                $fm_optimizable_types = array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'webp');

                // Everything counted as an image, which is the wider set: the
                // column stores whatever extension was uploaded, and a .tif
                // too heavy for a page is still too heavy for a page even
                // though nothing here can recompress it.
                $fm_image_types = array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'tif', 'webp');

                // Without one of these there is nothing to run the image
                // through, so the button is withheld rather than offered and
                // then failing.
                $fm_optimizer_available = (extension_loaded('imagick') || extension_loaded('gd'));

                // Printed in the shrink button's tooltip.
                $fm_image_settings = pg_image_settings();
                $fm_resize_target  = $fm_image_settings['file_max_dimension'];

                $fm_where = "(folder.folder_archived = '0')";

                // Design files belong to designers and administrators.
                if ($user['role'] > 1) {
                    $fm_where .= " AND (files.design = '0')";
                }

                $fm_no_access = false;

                // Contributors see only the folders they were granted edit
                // rights on, plus those folders' children. Resolved into the
                // WHERE clause rather than by filtering rows afterwards: on a
                // site with thousands of files, reading them all to discard
                // most would make this the most expensive card on a dashboard
                // that reloads on every visit.
                if ($user['role'] == 3) {

                    $fm_folders = get_folders_that_user_has_access_to($user['id']);

                    if ($fm_folders) {

                        $fm_folder_ids = array();

                        foreach ($fm_folders as $fm_folder_id) {
                            $fm_folder_ids[] = "'" . e($fm_folder_id) . "'";
                        }

                        $fm_where .= " AND (files.folder IN (" . implode(',', $fm_folder_ids) . "))";

                    } else {
                        // Granted nothing: no query can return a row this user
                        // is allowed to touch.
                        $fm_no_access = true;
                    }
                }

                $fm_image_list = array();

                foreach ($fm_image_types as $fm_image_type) {
                    $fm_image_list[] = "'" . e($fm_image_type) . "'";
                }

                $fm_image_list = implode(',', $fm_image_list);

                $fm_optimizable_list = array();

                foreach ($fm_optimizable_types as $fm_optimizable_type) {
                    $fm_optimizable_list[] = "'" . e($fm_optimizable_type) . "'";
                }

                $fm_optimizable_list = implode(',', $fm_optimizable_list);

                $fm_optimize_items = array();
                $fm_largest_items  = array();
                $fm_optimize_total = 0;

                if (!$fm_no_access) {

                    // One pass for both reasons a picture lands on this list.
                    //
                    // Never optimized, and of a type optimize.php will accept
                    // — an unoptimized .tif is left out because there is no
                    // action to offer for it and a row with no action is just
                    // a row the operator cannot clear.
                    //
                    // Or over the size ceiling, whatever the flag says and
                    // whatever the type: too heavy is too heavy.
                    //
                    // Ordered by size so the rows worth the operator's time
                    // are the ones that fit.
                    $fm_optimize_condition =
                        "(((LOWER(files.type) IN (" . $fm_optimizable_list . ")) AND (files.optimized = '0'))
                          OR ((LOWER(files.type) IN (" . $fm_image_list . ")) AND (files.size > " . (int) $fm_large_image_bytes . ")))";

                    $fm_result = mysqli_query(
                        db::$con,
                        "SELECT
                            files.id,
                            files.name,
                            files.type,
                            files.size,
                            files.optimized,
                            files.optimization_percent,
                            files.image_width,
                            files.image_height,
                            files.design
                         FROM files
                         LEFT JOIN folder ON files.folder = folder.folder_id
                         WHERE " . $fm_where . "
                           AND " . $fm_optimize_condition . "
                         ORDER BY files.size DESC
                         LIMIT " . (int) $fm_optimize_limit
                    );

                    $fm_optimize_items = $fm_result ? mysqli_fetch_items($fm_result) : array();

                    // The list is capped at ten, so the count is what tells an
                    // operator whether they are looking at the whole backlog
                    // or the top of it.
                    $fm_optimize_total = (int) db_value(
                        "SELECT COUNT(*)
                         FROM files
                         LEFT JOIN folder ON files.folder = folder.folder_id
                         WHERE " . $fm_where . "
                           AND " . $fm_optimize_condition
                    );

                    // Every type, not just images: a 40 MB video or an
                    // uncompressed PDF costs the same disk and the same
                    // backup as a photo does.
                    $fm_result = mysqli_query(
                        db::$con,
                        "SELECT
                            files.id,
                            files.name,
                            files.type,
                            files.size
                         FROM files
                         LEFT JOIN folder ON files.folder = folder.folder_id
                         WHERE " . $fm_where . "
                         ORDER BY files.size DESC
                         LIMIT " . (int) $fm_largest_limit
                    );

                    $fm_largest_items = $fm_result ? mysqli_fetch_items($fm_result) : array();
                }

                // File names carry meaning at both ends — what it is at the
                // front, what it is at the back — so long ones lose the
                // middle rather than either edge.
                $fm_shorten = function ($fm_text, $fm_max = 30) {

                    if (mb_strlen($fm_text) <= $fm_max) {
                        return $fm_text;
                    }

                    $fm_head = (int) floor(($fm_max - 1) / 2);
                    $fm_tail = $fm_max - 1 - $fm_head;

                    return mb_substr($fm_text, 0, $fm_head) . '…' . mb_substr($fm_text, -$fm_tail);
                };

                $fm_optimize_rows = '';

                foreach ($fm_optimize_items as $fm_item) {

                    $fm_size      = (int) $fm_item['size'];
                    $fm_too_large = ($fm_size > $fm_large_image_bytes);
                    $fm_can_run   = ((!$fm_item['optimized'])
                        && $fm_optimizer_available
                        && in_array(mb_strtolower($fm_item['type']), $fm_optimizable_types));

                    // Read from the cached column only. Filling it in means
                    // decoding and recompressing the image, which view_files.php
                    // does deliberately and under a threshold; a dashboard that
                    // every logged-in user loads is the wrong place to start
                    // that work.
                    $fm_percent = '';

                    if ($fm_can_run
                        && ($fm_item['optimization_percent'] !== null)
                        && ($fm_item['optimization_percent'] !== '')
                        && ((int) $fm_item['optimization_percent'] > 0)
                    ) {
                        $fm_percent = '<span class="ps-1" style="font-size:10px">' . (int) $fm_item['optimization_percent'] . '%</span>';
                    }

                    // Whether the row can be made narrower rather than only
                    // lighter. Read from the cached dimension columns, never
                    // measured here: this widget loads on every visit to the
                    // dashboard, and opening ten image headers to draw a
                    // button is exactly the kind of work that does not belong
                    // on that path. An unmeasured row simply does not get the
                    // button until some file screen has measured it.
                    $fm_can_resize = ($fm_optimizer_available
                        && in_array(mb_strtolower($fm_item['type']), $fm_optimizable_types)
                        && ($fm_item['image_width'] !== null) && ($fm_item['image_width'] !== '')
                        && pg_image_can_be_resized($fm_item['image_width'], $fm_item['image_height']));

                    $fm_action = '';

                    // send_to is a fixed keyword rather than a URL: optimize.php
                    // turns it into a hard-coded address, so nothing a visitor
                    // puts in the query string can become a redirect target.
                    if ($fm_can_run) {

                        $fm_action .= '
                        <a class="btn btn-sm btn-outline-success border-0 py-0 px-1 flex-shrink-0 d-flex align-items-center"
                           title="' . lang('Optimize this image') . '"
                           href="optimize.php?id=' . h($fm_item['id']) . get_token_query_string_field() . '&amp;send_to=welcome"><i class="bi bi-fast-forward-circle"></i>' . $fm_percent . '</a>';
                    }

                    // Both buttons can appear on the same row, and that is the
                    // point: compressing and shrinking are different jobs, and
                    // an image can want one, the other or both. The shrink is
                    // offered even when the row is already flagged optimized,
                    // because compression never made anything narrower.
                    if ($fm_can_resize) {

                        $fm_action .= '
                        <a class="btn btn-sm btn-outline-warning border-0 py-0 px-1 flex-shrink-0 d-flex align-items-center"
                           title="' . lang(array('string' => 'Resize to {var:1} pixels and optimize', 'vars' => array($fm_resize_target))) . '"
                           href="optimize.php?id=' . h($fm_item['id']) . get_token_query_string_field() . '&amp;mode=resize&amp;send_to=welcome"><i class="bi bi-arrows-angle-contract"></i></a>';

                    } elseif (!$fm_can_run && $fm_too_large && (!$fm_item['design'])) {

                        // Heavy, already compressed, and not wide enough for
                        // the shrink to help — so the only thing left is a
                        // person deciding what to do with it.
                        //
                        // Design files are excluded even for administrators:
                        // image_editor_edit.php refuses to save over one at
                        // any role, so the link would open an editor whose
                        // save button always fails. The row still carries the
                        // size warning, it just has nothing to offer.
                        $fm_action .= '
                        <a class="btn btn-sm btn-outline-secondary border-0 py-0 px-1 flex-shrink-0 d-flex align-items-center"
                           title="' . lang(array('string' => 'Edit this image with {var:1}', 'vars' => array(lang('Image Editor')))) . '"
                           href="image_editor_edit.php?file_name=' . rawurlencode($fm_item['name']) . '&amp;send_to=' . h(PATH . SOFTWARE_DIRECTORY . '/welcome.php') . '"><i class="bi bi-brush"></i></a>';
                    }

                    $fm_note = '';

                    if ($fm_too_large) {
                        $fm_note = '
                        <div class="text-warning" style="font-size:10px;line-height:1.3">' . lang('The file is very large, please make it smaller if possible.') . '</div>';
                    }

                    $fm_optimize_rows .= '
                    <div class="mb-2">
                        <div class="d-flex align-items-center" style="gap:6px">
                            <span class="text-truncate flex-fill" style="font-size:12px" title="' . h($fm_item['name']) . '">' . h($fm_shorten($fm_item['name'])) . '</span>
                            <span class="text-muted flex-shrink-0" style="font-size:11px">' . h(convert_bytes_to_string($fm_size, 1)) . '</span>
                            ' . $fm_action . '
                        </div>
                        ' . $fm_note . '
                    </div>';
                }

                if ($fm_optimize_rows === '') {
                    $fm_optimize_rows = '
                    <div class="text-center py-3">
                        <i class="bi bi-check2-circle d-block mb-1 text-success" style="font-size:22px;opacity:.8"></i>
                        <p class="text-muted mb-0" style="font-size:12px">' . lang('Congratulations, there are no files that need optimizing.') . '</p>
                    </div>';
                }

                // Bars are scaled against the biggest file on the list rather
                // than an absolute ceiling: with one 200 MB archive present,
                // every other bar would round away to nothing.
                $fm_peak = 0;

                foreach ($fm_largest_items as $fm_item) {
                    if ((int) $fm_item['size'] > $fm_peak) {
                        $fm_peak = (int) $fm_item['size'];
                    }
                }

                $fm_largest_rows = '';

                foreach ($fm_largest_items as $fm_item) {

                    $fm_size  = (int) $fm_item['size'];
                    $fm_width = ($fm_peak > 0) ? (int) round(100 * $fm_size / $fm_peak) : 0;

                    if ($fm_width < 3) {
                        $fm_width = 3;
                    }

                    // One neutral colour for every bar. A large video or
                    // archive is not a fault the way an unoptimized photo is,
                    // and colouring it as one would train the operator to
                    // ignore the warning that does mean something.
                    $fm_largest_rows .= '
                    <div class="mb-2">
                        <div class="d-flex align-items-center justify-content-between" style="gap:6px">
                            <a class="text-truncate text-decoration-none" style="font-size:12px"
                               title="' . h($fm_item['name']) . '"
                               href="edit_file.php?id=' . h($fm_item['id']) . '&amp;send_to=' . h(PATH . SOFTWARE_DIRECTORY . '/welcome.php') . '">' . h($fm_shorten($fm_item['name'])) . '</a>
                            <span class="text-muted flex-shrink-0" style="font-size:11px">' . h(convert_bytes_to_string($fm_size, 1)) . '</span>
                        </div>
                        <div class="progress mt-1" style="height:4px;background:rgba(0,0,0,.06)">
                            <div class="progress-bar bg-secondary" style="width:' . $fm_width . '%"></div>
                        </div>
                    </div>';
                }

                if ($fm_largest_rows === '') {
                    $fm_largest_rows = '
                    <div class="text-center py-3">
                        <i class="bi bi-folder2-open d-block mb-1" style="font-size:22px;opacity:.35"></i>
                        <p class="text-muted mb-0" style="font-size:12px">' . lang('No files found.') . '</p>
                    </div>';
                }

                // Said out loud only when it applies, so the absent buttons
                // read as a server limitation rather than a broken widget.
                $fm_optimizer_note = '';

                if ((!$fm_optimizer_available) && $fm_optimize_items) {
                    $fm_optimizer_note = '
                    <div class="px-3 pb-2">
                        <div class="text-muted" style="font-size:10px">' . lang('Image optimization is not available on this server.') . '</div>
                    </div>';
                }

                $output_data = '
                <div class="card-body p-0 d-flex flex-column" style="overflow-x:hidden;overflow-y:auto">
                    <div class="d-flex align-items-center justify-content-between px-3 pt-2 pb-1">
                        <span class="text-muted" style="font-size:11px">' . lang('Needs optimizing') . '</span>
                        <span class="text-muted" style="font-size:10px">' . number_format($fm_optimize_total) . '</span>
                    </div>
                    <div class="px-3 pb-1">' . $fm_optimize_rows . '</div>
                    ' . $fm_optimizer_note . '
                    <div class="d-flex align-items-center justify-content-between px-3 pt-2 pb-1 border-top">
                        <span class="text-muted" style="font-size:11px">' . lang('Largest files') . '</span>
                        <span class="text-muted" style="font-size:10px">' . lang(array('string' => 'Top {var:1}', 'vars' => number_format($fm_largest_limit))) . '</span>
                    </div>
                    <div class="px-3 pb-2">' . $fm_largest_rows . '</div>
                </div>
                <div class="card-footer border-0 bg-reset py-1 text-center">
                    <a href="view_files.php" class="text-decoration-none" style="font-size:11px">'
                    . lang('Files') . ' <i class="bi bi-arrow-right-short"></i></a>
                </div>';

                $response = array(
                    'status' => 'success',
                    'message' => 'Action Success',
                    'data' => $output_data,
                );
                echo encode_json($response);
                exit();
                break;

            case '19':
                if ($user['role'] < 3) {

                    $output_campaigns = array();

                    // Query to fetch latest 20 email campaigns
                    $query = "SELECT
                                email_campaigns.id as id,
                                email_campaigns.type,
                                email_campaigns.subject,
                                email_campaigns.status,
                                email_campaigns.purpose,
                                email_campaigns.start_time,
                                email_campaigns.created_user_id,
                                email_campaigns.created_timestamp,
                                email_campaigns.last_modified_timestamp,
                                created_user.user_username as created_username,
                                last_modified_user.user_username as last_modified_username
                            FROM email_campaigns
                            LEFT JOIN user as created_user ON email_campaigns.created_user_id = created_user.user_id
                            LEFT JOIN user as last_modified_user ON email_campaigns.last_modified_user_id = last_modified_user.user_id
                            ORDER BY email_campaigns.last_modified_timestamp DESC
                            LIMIT 20";

                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                    while ($row = mysqli_fetch_assoc($result)) {
                        $output_campaigns[] = $row;
                    }

                    $output_campaign_rows = '';

                    if (!empty($output_campaigns)) {

                        foreach ($output_campaigns as $output_campaign) {

                            // Prepare start time display if campaign job is enabled
                            if (email_campaign_job_enabled()) {
                                if (isset($output_campaign['start_time']) && $output_campaign['start_time'] == '0000-00-00 00:00:00') {
                                    $start_time = '';
                                } else {
                                    $start_time = isset($output_campaign['start_time']) ? get_relative_time(array(
                                        'timestamp' => strtotime($output_campaign['start_time'])
                                    )) : '';
                                }
                            }

                            // Get total number of recipients
                            $query = "SELECT COUNT(*) FROM email_recipients WHERE email_campaign_id = '" . $output_campaign['id'] . "'";
                            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                            $row = mysqli_fetch_row($result);
                            $number_of_email_recipients = $row[0];

                            // Get number of completed recipients
                            $query = "SELECT COUNT(*) FROM email_recipients WHERE email_campaign_id = '" . $output_campaign['id'] . "' AND complete = '1'";
                            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                            $row = mysqli_fetch_row($result);
                            $number_of_completed_email_recipients = $row[0];

                            $progress_percentage = ($number_of_email_recipients > 0)
                                ? number_format($number_of_completed_email_recipients / $number_of_email_recipients * 100)
                                : '100';

                            $output_link_url = 'edit_email_campaign.php?id=' . $output_campaign['id'] . '&amp;send_to=' . h(escape_javascript(urlencode(REQUEST_URL)));

                            $campaigns_created_username = !empty($output_campaign['created_username'])
                                ? $output_campaign['created_username']
                                : '[' . lang('Unknown') . ']';

                            $campaigns_last_modified_username = !empty($output_campaign['last_modified_username'])
                                ? $output_campaign['last_modified_username']
                                : '[' . lang('Unknown') . ']';

                            switch ($output_campaign['purpose']) {
                                case 'transactional':
                                    $output_purpose = lang('Transactional');
                                    break;
                                case 'commercial':
                                    $output_purpose = lang('Commercial');
                                    break;
                                default:
                                    $output_purpose = '';
                            }

                            // Status badge color
                            switch ($output_campaign['status']) {
                                case 'complete':
                                    $s_col = '#10b981';
                                    break;
                                case 'sending':
                                    $s_col = '#3b82f6';
                                    break;
                                case 'paused':
                                    $s_col = '#f59e0b';
                                    break;
                                default:
                                    $s_col = '#a1a1a1';
                                    break;
                            }

                            // Build campaign row HTML
                            $output_campaign_rows .= '
                            <div class="d-flex align-items-center px-2 py-2 border-bottom pointer" onclick="window.location.href=\'' . $output_link_url . '\'" style="gap:8px;cursor:pointer">
                                <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded" style="width:30px;height:30px;background:rgba(0,0,0,.05)">
                                    <i class="bi bi-megaphone" style="color:var(--campaigns-color);font-size:14px"></i>
                                </div>
                                <div class="flex-fill overflow-hidden">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="fw-semibold text-truncate" style="font-size:13px">' . h($output_campaign['subject']) . '</span>
                                        <span class="badge rounded-pill flex-shrink-0 ms-1" style="background:' . $s_col . '22;color:' . $s_col . ';font-size:10px">' . h(get_email_campaign_status_name($output_campaign['status'])) . '</span>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between gap-1">
                                        <div class="flex-fill" style="height:4px;background:rgba(0,0,0,.08);border-radius:2px">
                                            <div style="width:' . $progress_percentage . '%;height:100%;background:#3b82f6;border-radius:2px"></div>
                                        </div>
                                        <span class="text-muted flex-shrink-0" style="font-size:10px">' . $progress_percentage . '%</span>
                                        <span class="text-muted flex-shrink-0" style="font-size:10px">' . get_relative_time(array('timestamp' => $output_campaign['created_timestamp'])) . '</span>
                                    </div>
                                </div>
                            </div>';
                        }

                    } else {
                        $output_campaign_rows = pg_widget_empty('bi-megaphone', lang(array(
                            'string' => 'There is no {var:1} right now.',
                            'vars' => lang('Email Campaign')
                        )));
                    }

                    $output_data = '
                    <div class="card-body p-0" style="overflow-x:hidden;overflow-y:auto">
                        ' . $output_campaign_rows . '
                    </div>';

                    // Return success JSON response
                    $response = array(
                        'status' => 'success',
                        'message' => 'Action Success',
                        'data' => $output_data
                    );
                    echo encode_json($response);
                    exit();

                } else {
                    // Return error JSON response for unauthorized access
                    $response = array(
                        'status' => 'error',
                        'message' => 'Access denied'
                    );
                    echo encode_json($response);
                    exit();
                }
            case '20':
                if (validate_calendars_access($user, $only_return = true) != false) {
                    // get all calendars for calendar pick list
                    $query =
                        "SELECT
                           id,
                           name
                        FROM calendars
                        ORDER BY name";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    $calendars = array();
                    // loop through all calendars in order to prepare calendar pick list
                    while ($row = mysqli_fetch_assoc($result)) {
                        // if user has access to calendar, then include this calendar
                        if (validate_calendar_access($row['id']) == true) {
                            $calendars[] = $row;
                        }
                    }

                    // No calendar this user may see. get_calendar() answers
                    // that case with a bare sentence and no wrapper, which
                    // arrived flush against the top left corner of the card --
                    // the one card on the dashboard whose empty state was not
                    // centred. Answering it here keeps get_calendar() alone,
                    // since calendars.php prints that same sentence into a
                    // full page where a widget-sized empty state would be
                    // wrong.
                    if (empty($calendars)) {

                        $output_data = '
                        <div class="card-body p-0 d-flex flex-column">
                            ' . pg_widget_empty('bi-calendar3', lang('There are no calendars, so no calendar events could be displayed.')) . '
                        </div>';

                        $response = array(
                            'status' => 'success',
                            'message' => 'Action Success',
                            'data' => $output_data,
                        );
                        echo encode_json($response);
                        exit();
                        break;
                    }

                    $output_data = '
                    <div class="card-body p-0 overflow-auto">
                        ' . get_calendar('', $calendars, '', '', $user, '', '', $number_of_upcoming_events = '', $return = 'html', $output_minimal_calendar = true) . '
                        <a href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/calendars.php?date=&calendar_id=&view=monthly&status=" class=" stretched-link this-after-top-30"></a>
                    </div>';

                    //return success json output
                    $response = array(
                        'status' => 'success',
                        'message' => 'Action Success',
                        'data' => $output_data,
                    );
                    echo encode_json($response);
                    exit();
                    break;
                } else {
                    $response = array(
                        'status' => 'error',
                        'message' => 'Access denied'
                    );
                    echo encode_json($response);
                    exit();
                    break;
                }

            case '21':
                // ── Firewall: event feed ────────────────────────────────
                // Staff roles only (administrator, manager, designer).
                // Contributors are excluded — firewall events expose raw
                // attack payloads and visitor addresses.
                if ($user['role'] < 3) {

                    // Piggyback for the AI bot range lists, the same ride the
                    // visitor backfill takes on the dashboard: staff traffic
                    // keeps them fresh on sites where the cron job was never
                    // switched on. Throttled inside to one attempt per six
                    // hours, so this is a no-op on almost every load.
                    if (function_exists('pg_waf_refresh_ai_ranges')) {
                        pg_waf_refresh_ai_ranges();
                    }

                    $waf_available = (mysqli_num_rows(mysqli_query(db::$con, "SHOW TABLES LIKE 'waf_log'")) > 0);
                    $waf_current_mode = function_exists('waf_mode') ? waf_mode() : 'off';

                    // Schema not upgraded yet — say so rather than render an
                    // empty widget the operator cannot interpret.
                    if (!$waf_available) {
                        $output_data = '
                        <div class="card-body d-flex align-items-center justify-content-center text-center">
                            <div>
                                <i class="bi bi-database-exclamation d-block mb-2" style="font-size:22px;opacity:.4"></i>
                                <p class="text-muted mb-0" style="font-size:12px">' . lang('The firewall tables do not exist yet. Please run the software upgrade to create them.') . '</p>
                            </div>
                        </div>';

                        $response = array(
                            'status'  => 'success',
                            'message' => 'Action Success',
                            'data'    => $output_data,
                        );
                        echo encode_json($response);
                        exit();
                        break;
                    }

                    $waf_day_ago = time() - 86400;

                    // SUM(hit_count), never COUNT(*): identical events are
                    // folded into five-minute buckets, so counting rows would
                    // report a flood of ten thousand requests as one event.
                    $waf_totals = mysqli_fetch_assoc(mysqli_query(
                        db::$con,
                        "SELECT
                            COALESCE(SUM(hit_count), 0) AS requests,
                            COALESCE(SUM(CASE WHEN action IN ('block','rate','ban') THEN hit_count ELSE 0 END), 0) AS blocked,
                            COALESCE(SUM(CASE WHEN action IN ('would-block','would-rate') THEN hit_count ELSE 0 END), 0) AS would_block,
                            COUNT(DISTINCT ip_address) AS addresses
                         FROM waf_log
                         WHERE log_timestamp >= " . (int) $waf_day_ago
                    ));

                    $waf_requests    = (int) $waf_totals['requests'];
                    $waf_blocked     = (int) $waf_totals['blocked'];
                    $waf_would_block = (int) $waf_totals['would_block'];
                    $waf_addresses   = (int) $waf_totals['addresses'];

                    // Active automatic bans, if the columns are present.
                    $waf_active_bans = 0;

                    if (function_exists('waf_table_has_column')
                        && waf_table_has_column('banned_ip_addresses', 'source')
                    ) {
                        $waf_active_bans = (int) db_value(
                            "SELECT COUNT(*) FROM banned_ip_addresses
                             WHERE source = 'auto'
                               AND (expires_at = 0 OR expires_at > " . time() . ")"
                        );
                    }

                    // Mode strip. Monitor is the state that needs explaining:
                    // the numbers below it are what blocking WOULD have
                    // stopped, not what it did stop.
                    if ($waf_current_mode === 'off') {
                        $waf_mode_class = 'secondary';
                        $waf_mode_icon  = 'bi-shield-slash';
                        $waf_mode_label = lang('Off');
                    } elseif ($waf_current_mode === 'monitor') {
                        $waf_mode_class = 'info';
                        $waf_mode_icon  = 'bi-eye';
                        $waf_mode_label = lang('Monitor');
                    } else {
                        $waf_mode_class = 'success';
                        $waf_mode_icon  = 'bi-shield-check';
                        $waf_mode_label = lang('Block');
                    }

                    // The headline number depends on the mode: in monitor
                    // mode nothing was actually blocked, so leading with
                    // "0 blocked" would read as "no attacks".
                    if ($waf_current_mode === 'monitor') {
                        $waf_headline_value = $waf_would_block;
                        $waf_headline_label = lang('Would block');
                        $waf_headline_color = 'warning';
                    } else {
                        $waf_headline_value = $waf_blocked;
                        $waf_headline_label = lang('Blocked');
                        $waf_headline_color = 'danger';
                    }

                    // Recent events: action, requests, address, score.
                    //
                    // The score is the anomaly score — the sum of every rule
                    // the request matched. A single unambiguous rule (a UNION
                    // SELECT, a traversal sequence) scores 10 and blocks on
                    // its own; deliberately weak rules score 4-6 and have to
                    // corroborate each other. The blocking line is the
                    // sensitivity threshold, so the bar is drawn as a share
                    // of THAT rather than of some arbitrary maximum: a full
                    // bar means "this request crossed the line", which is the
                    // only reading of the number an operator actually needs.
                    $waf_threshold_value = function_exists('waf_threshold') ? waf_threshold() : 10;

                    if ($waf_threshold_value < 1) {
                        $waf_threshold_value = 10;
                    }

                    $waf_action_badges = array(
                        'block'       => array('danger',            lang('Blocked')),
                        'rate'        => array('danger',            lang('Rate limited')),
                        'ban'         => array('dark',              lang('Banned')),
                        'would-block' => array('warning text-dark', lang('Would block')),
                        'would-rate'  => array('warning text-dark', lang('Would rate limit')),
                        'log'         => array('secondary',         lang('Recorded')),
                    );

                    $waf_rows = '';

                    $waf_event_result = mysqli_query(
                        db::$con,
                        "SELECT ip_address, action, rule_id, score, hit_count, last_seen,
                                target, matched
                         FROM waf_log
                         WHERE log_timestamp >= " . (int) $waf_day_ago . "
                         ORDER BY last_seen DESC, id DESC
                         LIMIT 8"
                    );

                    if ($waf_event_result) {
                        while ($waf_event = mysqli_fetch_assoc($waf_event_result)) {

                            $waf_event_action = $waf_event['action'];

                            $waf_badge = isset($waf_action_badges[$waf_event_action])
                                ? $waf_action_badges[$waf_event_action]
                                : array('secondary', $waf_event_action);

                            $waf_event_score = (int) $waf_event['score'];
                            $waf_event_hits  = (int) $waf_event['hit_count'];

                            // Capped at 100: a score of 30 is not three times
                            // more blocked than a score of 10.
                            $waf_score_percent = (int) round(100 * $waf_event_score / $waf_threshold_value);

                            if ($waf_score_percent > 100) {
                                $waf_score_percent = 100;
                            }

                            if ($waf_score_percent < 0) {
                                $waf_score_percent = 0;
                            }

                            // Colour encodes the same decision as the bar
                            // length, so the row reads at a glance.
                            if ($waf_event_score >= $waf_threshold_value) {
                                $waf_bar_class = 'bg-danger';
                            } elseif ($waf_event_score >= ($waf_threshold_value / 2)) {
                                $waf_bar_class = 'bg-warning';
                            } else {
                                $waf_bar_class = 'bg-secondary';
                            }

                            // Not every event has a client address — a rule
                            // can fire on the user agent or on a script name
                            // with no address to report. Leaving the cell
                            // blank made the widget look broken, so the
                            // matched target stands in for it.
                            if ($waf_event['ip_address'] !== '') {
                                $waf_identity = '<span class="font-monospace">' . h($waf_event['ip_address']) . '</span>';
                            } elseif ($waf_event['target'] !== '') {
                                $waf_identity = '<span class="fst-italic">' . h($waf_event['target']) . '</span>';
                            } else {
                                $waf_identity = '<span class="fst-italic">' . lang('no address') . '</span>';
                            }

                            // What the rule actually caught: the crawler name,
                            // the payload fragment, the limit that was passed.
                            // Without it a row says something was blocked but
                            // never what.
                            $waf_evidence = ($waf_event['matched'] !== '')
                                ? $waf_event['matched']
                                : $waf_event['rule_id'];

                            $waf_rows .= '
                            <div class="px-2 py-2 border-bottom">
                                <div class="d-flex align-items-center justify-content-between" style="gap:6px">
                                    <span class="badge bg-' . h($waf_badge[0]) . ' flex-shrink-0" style="font-size:9px">' . h($waf_badge[1]) . '</span>
                                    <span class="text-truncate text-muted" style="font-size:11px" title="' . h($waf_event['rule_id']) . '">' . $waf_identity . '</span>
                                    <span class="badge rounded-pill flex-shrink-0" style="background:rgba(0,0,0,.06);color:inherit;font-size:10px" title="' . lang('Requests') . '">&times;' . number_format($waf_event_hits) . '</span>
                                </div>
                                <div class="d-flex align-items-center mt-1" style="gap:6px">
                                    <span class="text-truncate text-muted flex-shrink-0" style="font-size:10px;max-width:45%" title="' . h($waf_event['target']) . '">' . h($waf_evidence) . '</span>
                                    <div class="progress flex-fill" style="height:4px;background:rgba(0,0,0,.06)" title="' . lang('Score') . ': ' . $waf_event_score . ' / ' . (int) $waf_threshold_value . '">
                                        <div class="progress-bar ' . $waf_bar_class . '" style="width:' . $waf_score_percent . '%"></div>
                                    </div>
                                    <span class="text-muted flex-shrink-0" style="font-size:10px;min-width:1.4rem;text-align:right">' . $waf_event_score . '</span>
                                </div>
                            </div>';
                        }
                    }

                    // Whether the feed had anything in it, asked before the
                    // empty state takes its place. The mode strip below needs
                    // to know: an off firewall wants the same nudge either way,
                    // but where it goes depends on whether there is a list to
                    // put it under.
                    $waf_had_rows = ($waf_rows !== '');

                    // Off, and nothing recorded at all. Both halves of this card
                    // read waf_log, so an empty event feed means an empty threat
                    // digest too -- there is no second panel to show and no
                    // figures to put above it.
                    //
                    // What the card was drawing instead: a mode strip, then
                    // Blocked 0 / Addresses 0 / Bans 0, then the message, then a
                    // divider, then a second empty panel, then a link to a log
                    // with nothing in it. Every one of those says the same thing
                    // the badge already said, and three zeros under an Off badge
                    // are not a measurement -- nothing counted them.
                    //
                    // So the card collapses to the one thing worth saying and
                    // the one thing worth doing.
                    if (($waf_current_mode === 'off') && (!$waf_had_rows)) {

                        $output_data = '
                        <div class="card-body p-0 d-flex flex-column">'
                            . pg_widget_empty(
                                'bi-shield-slash',
                                lang('The firewall is off, so nothing is being watched or recorded.'),
                                '',
                                lang('Turn on the firewall'),
                                OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/' . pg_settings_link('firewall', 'pgset-waf')) . '
                        </div>';

                        $response = array(
                            'status' => 'success',
                            'message' => 'Action Success',
                            'data' => $output_data,
                        );
                        echo encode_json($response);
                        exit();
                    }

                    if ($waf_rows === '') {

                        // An empty feed means two different things and the card
                        // has to say which. With the firewall on, nothing
                        // happened -- that is the good outcome and the card
                        // reports it. With the firewall off, nothing was
                        // WATCHING, and an empty feed under a grey "Off" badge
                        // reads as the quiet one unless the card says
                        // otherwise. So the off state names the cause and
                        // offers the switch, rather than leaving the operator
                        // to work out that the reassuring empty list is the
                        // symptom.
                        // Only reachable with the firewall on: off with an
                        // empty feed returned above.
                        $waf_rows = pg_widget_empty(
                            'bi-shield-check',
                            lang('No firewall events were recorded in this period.'),
                            'good');
                    }

                    // Reassurance, but only when it is true.
                    //
                    // The claim is made ONLY in blocking mode. In Monitor the
                    // firewall watches and lets everything through, and telling
                    // an operator they are protected while nothing is being
                    // stopped is the kind of false comfort that stops them
                    // finishing the setup. Off says nothing at all.
                    $waf_shield = '';

                    if ($waf_current_mode === 'block') {
                        $waf_shield = '
                        <div class="d-flex align-items-center px-2 py-2 border-bottom" style="gap:8px">
                            <i class="bi bi-shield-fill-check text-success" style="font-size:20px"></i>
                            <div class="text-success" style="font-size:12px;line-height:1.25">'
                                . lang('Your website is protected against threats.')
                            . '</div>
                        </div>';
                    }

                    // Off with events to show: the empty state is not on
                    // screen to carry the nudge, so it rides the mode strip
                    // instead -- right beside the badge that says Off, which is
                    // the thing it answers. Off with nothing to show puts it in
                    // the empty state instead, so only ever one of the two.
                    $waf_turn_on = '';

                    if (($waf_current_mode === 'off') && ($waf_had_rows)) {

                        $waf_turn_on = '<a class="btn btn-sm btn-outline-secondary position-relative py-0 px-2" style="font-size:10px" href="'
                            . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/' . pg_settings_link('firewall', 'pgset-waf') . '">'
                            . lang('Turn on') . '</a>';
                    }

                    $waf_panel = '
                        <div class="pg-split-half d-flex flex-column">
                            ' . $waf_shield . '
                            <div class="d-flex align-items-center justify-content-between px-2 py-2 border-bottom">
                                <span class="d-flex align-items-center gap-2">
                                    <span class="badge rounded-pill bg-' . h($waf_mode_class) . '-subtle text-' . h($waf_mode_class) . '-emphasis border border-' . h($waf_mode_class) . '-subtle" style="font-size:10px">
                                        <i class="bi ' . h($waf_mode_icon) . ' me-1"></i>' . h($waf_mode_label) . '
                                    </span>
                                    ' . $waf_turn_on . '
                                </span>
                                <span class="text-muted" style="font-size:10px">' . lang('Last 24 hours') . '</span>
                            </div>
                            <div class="row g-0 text-center border-bottom">
                                <div class="col-4 py-2 border-end">
                                    <div class="fw-semibold text-' . h($waf_headline_color) . '" style="font-size:17px">' . number_format($waf_headline_value) . '</div>
                                    <div class="text-muted text-truncate" style="font-size:10px">' . h($waf_headline_label) . '</div>
                                </div>
                                <div class="col-4 py-2 border-end">
                                    <div class="fw-semibold" style="font-size:17px">' . number_format($waf_addresses) . '</div>
                                    <div class="text-muted text-truncate" style="font-size:10px">' . lang('Addresses') . '</div>
                                </div>
                                <div class="col-4 py-2">
                                    <div class="fw-semibold" style="font-size:17px">' . number_format($waf_active_bans) . '</div>
                                    <div class="text-muted text-truncate" style="font-size:10px">' . lang('Bans') . '</div>
                                </div>
                            </div>
                            ' . $waf_rows . '
                        </div>';

                    // Deliberate fall-through into case '22'.
                    //
                    // The two used to be separate cards asking related questions
                    // - what happened, and who is generating it - and reading one
                    // without the other was half an answer. They are the two
                    // halves of one card now, so this case holds its panel and
                    // the threat case below builds its own and emits both. Only
                    // one query pass either way; nothing is computed twice.
                } else {
                    $response = array(
                        'status' => 'error',
                        'message' => 'Access denied'
                    );
                    echo encode_json($response);
                    exit();
                    break;
                }

            case '22':
                // ── Firewall: threat digest ─────────────────────────────
                //
                // Companion to widget 21, deliberately a different question.
                // 21 answers "what happened, most recently"; this answers
                // "who is generating the load", ranked. On a normal site the
                // top of this list is four or five commercial crawlers, and
                // seeing them ranked is what tells an operator whether
                // turning blocking on is worth it.
                if ($user['role'] < 3) {

                    $td_available = (mysqli_num_rows(mysqli_query(db::$con, "SHOW TABLES LIKE 'waf_log'")) > 0);

                    if (!$td_available) {
                        $output_data = '
                        <div class="card-body d-flex align-items-center justify-content-center text-center">
                            <div>
                                <i class="bi bi-database-exclamation d-block mb-2" style="font-size:22px;opacity:.4"></i>
                                <p class="text-muted mb-0" style="font-size:12px">' . lang('The firewall tables do not exist yet. Please run the software upgrade to create them.') . '</p>
                            </div>
                        </div>';

                        $response = array('status' => 'success', 'message' => 'Action Success', 'data' => $output_data);
                        echo encode_json($response);
                        exit();
                        break;
                    }

                    $td_mode = function_exists('waf_mode') ? waf_mode() : 'off';
                    $td_day_ago = time() - 86400;
                    $td_threshold = function_exists('waf_threshold') ? waf_threshold() : 10;

                    if ($td_threshold < 1) {
                        $td_threshold = 10;
                    }

                    $td_totals = mysqli_fetch_assoc(mysqli_query(
                        db::$con,
                        "SELECT
                            COALESCE(SUM(CASE WHEN action IN ('block','rate','ban') THEN hit_count ELSE 0 END), 0) AS blocked,
                            COALESCE(SUM(CASE WHEN action IN ('would-block','would-rate') THEN hit_count ELSE 0 END), 0) AS would_block,
                            COUNT(DISTINCT ip_address) AS addresses
                         FROM waf_log
                         WHERE log_timestamp >= " . (int) $td_day_ago
                    ));

                    if ($td_mode === 'monitor') {
                        $td_headline = (int) $td_totals['would_block'];
                        $td_headline_label = lang('Would block');
                        $td_headline_color = 'warning';
                    } else {
                        $td_headline = (int) $td_totals['blocked'];
                        $td_headline_label = lang('Blocked');
                        $td_headline_color = 'danger';
                    }

                    $td_category_names = array(
                        'sqli'     => lang('SQL Injection'),
                        'xss'      => lang('Cross-site Scripting'),
                        'lfi'      => lang('Path Traversal'),
                        'rce'      => lang('Command Injection'),
                        'protocol' => lang('Protocol Abuse'),
                        'bot'      => lang('Bots'),
                        'tool'     => lang('Scanners'),
                        'rate'     => lang('Rate Limit'),
                        'iplist'   => lang('IP List'),
                        'ban'      => lang('Bans'),
                    );

                    // Grouping key depends on the category. For bots and
                    // scanner tooling the matched value IS the useful name
                    // ("bytespider", "dotbot"), and collapsing those into one
                    // "Bots" bar would throw away the only detail worth
                    // showing. Everything else groups by category, because
                    // there the matched value is a payload fragment that
                    // differs on every request.
                    $td_result = mysqli_query(
                        db::$con,
                        "SELECT
                            CASE WHEN category IN ('bot','tool') AND matched <> ''
                                 THEN matched ELSE category END AS source,
                            category,
                            SUM(hit_count) AS hits,
                            COUNT(DISTINCT ip_address) AS ips,
                            MAX(score) AS top_score
                         FROM waf_log
                         WHERE log_timestamp >= " . (int) $td_day_ago . "
                         GROUP BY source, category
                         ORDER BY hits DESC
                         LIMIT 6"
                    );

                    $td_sources = $td_result ? mysqli_fetch_items($td_result) : array();

                    // Scale the bars against the busiest source, not against
                    // the grand total: with one dominant crawler every other
                    // bar would round to nothing.
                    $td_peak = 0;

                    foreach ($td_sources as $td_source) {
                        if ((int) $td_source['hits'] > $td_peak) {
                            $td_peak = (int) $td_source['hits'];
                        }
                    }

                    $td_rows = '';

                    foreach ($td_sources as $td_source) {
                        $td_hits = (int) $td_source['hits'];
                        $td_label = $td_source['source'];

                        if (in_array($td_source['category'], array('bot', 'tool'), true)) {
                            // Stored bot tokens are lower case; title-case them
                            // for display, but leave anything that already has
                            // capitals alone so "(forged)" style notes survive.
                            if ($td_label === mb_strtolower($td_label, 'UTF-8')) {
                                $td_label = mb_convert_case($td_label, MB_CASE_TITLE, 'UTF-8');
                            }
                        } elseif (isset($td_category_names[$td_label])) {
                            $td_label = $td_category_names[$td_label];
                        }

                        $td_width = ($td_peak > 0) ? (int) round(100 * $td_hits / $td_peak) : 0;

                        if ($td_width < 3) {
                            $td_width = 3;
                        }

                        if ((int) $td_source['top_score'] >= $td_threshold) {
                            $td_bar = 'bg-danger';
                            $td_text = ' text-danger';
                        } elseif ((int) $td_source['top_score'] >= ($td_threshold / 2)) {
                            $td_bar = 'bg-warning';
                            $td_text = '';
                        } else {
                            $td_bar = 'bg-secondary';
                            $td_text = '';
                        }

                        $td_rows .= '
                        <div class="mb-2">
                            <div class="d-flex align-items-center justify-content-between" style="gap:6px">
                                <span class="text-truncate' . $td_text . '" style="font-size:12px" title="' . h($td_source['category']) . '">' . h($td_label) . '</span>
                                <span class="text-muted flex-shrink-0" style="font-size:11px">' . number_format($td_hits) . '</span>
                            </div>
                            <div class="progress mt-1" style="height:4px;background:rgba(0,0,0,.06)" title="' . lang(array(
                                'string' => '{var:1} address{suffix:1}',
                                'vars'   => number_format((int) $td_source['ips']),
                                'suffix' => ((int) $td_source['ips'] == 1 ? '' : 'es'),
                            )) . '">
                                <div class="progress-bar ' . $td_bar . '" style="width:' . $td_width . '%"></div>
                            </div>
                        </div>';
                    }

                    if ($td_rows === '') {
                        $td_rows = '
                        <div class="text-center py-4">
                            <i class="bi bi-shield-check d-block mb-2" style="font-size:22px;opacity:.35"></i>
                            <p class="text-muted mb-0" style="font-size:12px">' . lang('No firewall events were recorded in this period.') . '</p>
                        </div>';
                    }

                    $td_footer = '';

                    if ($user['role'] < 3) {
                        $td_footer = '
                        <div class="card-footer border-0 bg-reset py-1 text-center">
                            <a href="view_waf_log.php" class="text-decoration-none" style="font-size:11px">'
                            . lang('Firewall Log') . ' <i class="bi bi-arrow-right-short"></i></a>
                        </div>';
                    }

                    // No protection banner here. Widget 21 already carries it,
                    // and the same reassurance twice on one dashboard reads as
                    // filler rather than information. This widget answers a
                    // different question — who is generating the load — and
                    // the ranked list is the answer.
                    // No totals row. The firewall half beside this one already
                    // carries blocked, addresses and bans across the top, and the
                    // same three figures twice on one card reads as a rendering
                    // fault rather than as emphasis. This half answers the other
                    // question - who is generating the load - so the ranked list
                    // starts straight away.
                    $td_panel = '
                        <div class="pg-split-half d-flex flex-column">
                            <div class="px-3 pt-2 pb-1 text-muted" style="font-size:11px">' . lang('Top sources') . '</div>
                            <div class="px-3 pb-2">' . $td_rows . '</div>
                        </div>';

                    // $waf_panel is set only when execution arrived through case
                    // '21', which is how the dashboard asks for this card. A
                    // direct request for widget 22 still answers with the threat
                    // half alone rather than an error.
                    $output_data = '
                        <div class="card-body p-0 pg-split">'
                            . (isset($waf_panel) ? $waf_panel : '')
                            . $td_panel . '
                        </div>' . $td_footer;

                    $response = array(
                        'status' => 'success',
                        'message' => 'Action Success',
                        'data' => $output_data,
                    );
                    echo encode_json($response);
                    exit();
                    break;
                } else {
                    $response = array(
                        'status' => 'error',
                        'message' => 'Access denied'
                    );
                    echo encode_json($response);
                    exit();
                    break;
                }

            case '23':
                // ── Performance ─────────────────────────────────────────
                //
                // Reads the hourly summary, never the raw rows: the point of
                // this widget is to be cheap enough to sit on a dashboard that
                // refreshes every minute. The old per-request table would have
                // made it the second most expensive thing on the page.
                //
                // FRONT END ONLY. Back-end screens are still measured and are
                // in the full report, where the operator can filter by area —
                // but they do not belong on this widget. A settings page that
                // one administrator opens twice a day would otherwise sit in
                // the same average as the product page thousands of customers
                // load, and could set the health grade on its own. The number
                // a shop owner needs at a glance is what their visitors are
                // waiting for.
                if ($user['role'] < 3) {

                    // Monitoring off: nothing is being recorded and the tables
                    // were emptied when it was switched off, so say that rather
                    // than render zeroes that look like a broken site.
                    if (defined('PERF_MONITOR_ENABLED') && !PERF_MONITOR_ENABLED) {
                        $output_data = '
                        <div class="card-body d-flex align-items-center justify-content-center text-center">
                            <div>
                                <i class="bi bi-speedometer2 d-block mb-2" style="font-size:22px;opacity:.35"></i>
                                <p class="text-muted mb-0" style="font-size:12px">' . lang('Performance monitoring is turned off.') . '</p>
                            </div>
                        </div>';

                        $response = array('status' => 'success', 'message' => 'Action Success', 'data' => $output_data);
                        echo encode_json($response);
                        exit();
                        break;
                    }

                    $pf_available = (mysqli_num_rows(mysqli_query(db::$con, "SHOW TABLES LIKE 'perf_stats'")) > 0);

                    if (!$pf_available) {
                        $output_data = '
                        <div class="card-body d-flex align-items-center justify-content-center text-center">
                            <div>
                                <i class="bi bi-database-exclamation d-block mb-2" style="font-size:22px;opacity:.4"></i>
                                <p class="text-muted mb-0" style="font-size:12px">' . lang('The performance tables do not exist yet. Please run the software upgrade to create them.') . '</p>
                            </div>
                        </div>';

                        $response = array('status' => 'success', 'message' => 'Action Success', 'data' => $output_data);
                        echo encode_json($response);
                        exit();
                        break;
                    }

                    $pf_day_ago = time() - 86400;
                    $pf_slow_ms = defined('PERF_MONITOR_SLOW_MS') ? (int) PERF_MONITOR_SLOW_MS : 1000;

                    // Averages are computed from the sums. Averaging the
                    // per-bucket averages would weight a quiet hour the same
                    // as a busy one and quietly give the wrong number.
                    $pf_totals = mysqli_fetch_assoc(mysqli_query(
                        db::$con,
                        "SELECT
                            COALESCE(SUM(hits), 0)      AS hits,
                            COALESCE(SUM(slow_hits), 0) AS slow_hits,
                            COALESCE(SUM(total_ms), 0)  AS total_ms,
                            COALESCE(MAX(max_ms), 0)    AS max_ms,
                            COALESCE(SUM(total_kb), 0)  AS total_kb
                         FROM perf_stats
                         WHERE hour_start >= " . (int) $pf_day_ago . "
                           AND area = 'frontend'"
                    ));

                    $pf_hits = (int) $pf_totals['hits'];
                    $pf_slow = (int) $pf_totals['slow_hits'];
                    $pf_avg  = $pf_hits > 0 ? (int) round($pf_totals['total_ms'] / $pf_hits) : 0;
                    $pf_max  = (int) $pf_totals['max_ms'];

                    // Colour the average against what a page should feel like,
                    // not against its own history: 200 ms is fine, 500 ms is
                    // noticeable, beyond that a visitor is waiting.
                    if ($pf_avg >= 500) {
                        $pf_avg_color = 'danger';
                    } elseif ($pf_avg >= 200) {
                        $pf_avg_color = 'warning';
                    } else {
                        $pf_avg_color = 'success';
                    }

                    $pf_slow_color = ($pf_slow > 0) ? 'warning' : 'success';

                    // ── Health ───────────────────────────────────────────
                    //
                    // Graded on the slowest page that gets real traffic, NOT
                    // on the site average.
                    //
                    // The average is the wrong number for a verdict because it
                    // is dominated by whatever is cheapest and most frequent.
                    // A site whose pages are all fast except an eight-second
                    // checkout averages well under 100 ms and would be shown a
                    // green badge while losing sales on the one page that
                    // pays for everything. A visitor never experiences the
                    // average; they experience the page they opened.
                    //
                    // Graded on the page's FASTEST run, not its average.
                    //
                    // An outlier inflates an average but cannot touch a
                    // minimum — that is what a minimum is. So a product page
                    // that normally answers in 200 ms and once took 101
                    // seconds because a crawler caught a lock still reads as
                    // 200 ms, while a checkout that takes eight seconds every
                    // single time reads as eight seconds. The first is not a
                    // broken page; the second is.
                    //
                    // The alternative suggested itself — ignore anything over a
                    // second as probably bogus — would have hidden exactly the
                    // page worth finding, because a consistently slow checkout
                    // is over that line on every request.
                    //
                    // The trade-off, stated plainly: a page that is slow only
                    // half the time grades on its good half. Understating is
                    // the safer error for a badge that has to be trusted, and
                    // the average is still visible in the list underneath.
                    $pf_worst = mysqli_fetch_assoc(mysqli_query(
                        db::$con,
                        "SELECT label, SUM(hits) AS hits, MIN(min_ms) AS floor_ms,
                                SUM(total_ms) / GREATEST(SUM(hits), 1) AS avg_ms
                         FROM perf_stats
                         WHERE hour_start >= " . (int) $pf_day_ago . "
                           AND area = 'frontend'
                         GROUP BY label
                         HAVING SUM(hits) >= 3
                         ORDER BY floor_ms DESC
                         LIMIT 1"
                    ));

                    // Quiet site: nothing opened three times yet.
                    if (!$pf_worst) {
                        $pf_worst = mysqli_fetch_assoc(mysqli_query(
                            db::$con,
                            "SELECT label, SUM(hits) AS hits, MIN(min_ms) AS floor_ms,
                                    SUM(total_ms) / GREATEST(SUM(hits), 1) AS avg_ms
                             FROM perf_stats
                             WHERE hour_start >= " . (int) $pf_day_ago . "
                               AND area = 'frontend'
                             GROUP BY label
                             ORDER BY floor_ms DESC
                             LIMIT 1"
                        ));
                    }

                    $pf_worst_ms = $pf_worst ? (int) round($pf_worst['floor_ms']) : 0;
                    $pf_worst_label = $pf_worst ? $pf_worst['label'] : '';

                    // Below this there is not enough traffic to judge anything,
                    // and a confident verdict from four requests is worse than
                    // admitting the sample is too small.
                    $pf_enough = ($pf_hits >= 10);

                    if (!$pf_enough) {
                        $pf_grade = lang('Not enough data');
                        $pf_grade_class = 'secondary';
                    } elseif ($pf_worst_ms < 300) {
                        $pf_grade = lang('Very good');
                        $pf_grade_class = 'success';
                    } elseif ($pf_worst_ms < 800) {
                        $pf_grade = lang('Good');
                        $pf_grade_class = 'success';
                    } elseif ($pf_worst_ms < 2000) {
                        $pf_grade = lang('Weak');
                        $pf_grade_class = 'warning';
                    } else {
                        $pf_grade = lang('Poor');
                        $pf_grade_class = 'danger';
                    }

                    // Needle position. Duration has no upper bound, so a linear
                    // scale would leave every healthy site pinned at zero and
                    // every unhealthy one pinned at maximum. The scale is
                    // piecewise instead, stretched across the range where the
                    // difference actually changes what a visitor feels.
                    $pf_points = array(
                        array(0, 0.0), array(300, 0.25), array(800, 0.5),
                        array(2000, 0.75), array(5000, 1.0),
                    );

                    $pf_fraction = 1.0;

                    for ($i = 1; $i < count($pf_points); $i++) {
                        if ($pf_worst_ms <= $pf_points[$i][0]) {
                            $pf_span = $pf_points[$i][0] - $pf_points[$i - 1][0];
                            $pf_into = $pf_worst_ms - $pf_points[$i - 1][0];
                            $pf_fraction = $pf_points[$i - 1][1]
                                + (($pf_span > 0 ? $pf_into / $pf_span : 0)
                                   * ($pf_points[$i][1] - $pf_points[$i - 1][1]));
                            break;
                        }
                    }

                    if (!$pf_enough) {
                        $pf_fraction = 0;
                    }

                    // Semicircle: 180° on the left through to 0° on the right.
                    $pf_angle = 180 - ($pf_fraction * 180);
                    $pf_rad = $pf_angle * M_PI / 180;
                    $pf_nx = 70 + (44 * cos($pf_rad));
                    $pf_ny = 70 - (44 * sin($pf_rad));

                    // Band arcs, drawn once. Kept as flat strokes with no
                    // gradient so they render identically in both themes.
                    $pf_arc = '';
                    $pf_bands = array(
                        array(0.00, 0.25, 'var(--bs-success)'),
                        array(0.25, 0.50, 'var(--bs-success)'),
                        array(0.50, 0.75, 'var(--bs-warning)'),
                        array(0.75, 1.00, 'var(--bs-danger)'),
                    );

                    foreach ($pf_bands as $pf_band) {
                        $a1 = (180 - ($pf_band[0] * 180)) * M_PI / 180;
                        $a2 = (180 - ($pf_band[1] * 180)) * M_PI / 180;
                        $x1 = 70 + (52 * cos($a1));
                        $y1 = 70 - (52 * sin($a1));
                        $x2 = 70 + (52 * cos($a2));
                        $y2 = 70 - (52 * sin($a2));

                        $pf_arc .= '<path d="M ' . round($x1, 2) . ' ' . round($y1, 2)
                            . ' A 52 52 0 0 1 ' . round($x2, 2) . ' ' . round($y2, 2) . '"'
                            . ' fill="none" stroke="' . $pf_band[2] . '" stroke-width="9"'
                            . ' stroke-linecap="butt" opacity="' . ($pf_enough ? '0.85' : '0.25') . '"/>';
                    }

                    $pf_worst_display = $pf_worst_label;

                    if (mb_strlen($pf_worst_display) > 26) {
                        $pf_worst_display = '…' . mb_substr($pf_worst_display, -25);
                    }

                    $pf_gauge = '
                    <div class="d-flex align-items-center border-bottom px-2 py-2" style="gap:8px">
                        <svg viewBox="0 0 140 84" style="width:104px;height:62px;flex-shrink:0" role="img" aria-label="' . h($pf_grade) . '">
                            <path d="M 18 70 A 52 52 0 0 1 122 70" fill="none" stroke="rgba(128,128,128,.15)" stroke-width="9"/>
                            ' . $pf_arc . '
                            <line x1="70" y1="70" x2="' . round($pf_nx, 2) . '" y2="' . round($pf_ny, 2) . '"
                                  stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                            <circle cx="70" cy="70" r="4" fill="currentColor"/>
                        </svg>
                        <div class="flex-fill overflow-hidden">
                            <div class="fw-semibold text-' . h($pf_grade_class) . '" style="font-size:15px;line-height:1.1">' . h($pf_grade) . '</div>'
                            . ($pf_enough && $pf_worst_label !== ''
                                ? '<div class="text-truncate text-muted" style="font-size:11px" title="' . h($pf_worst_label) . '">' . h($pf_worst_display) . '</div>
                                   <div class="text-muted" style="font-size:11px">' . lang(array(
                                        'string' => '{var:1} ms at its fastest',
                                        'vars'   => number_format($pf_worst_ms),
                                    )) . '</div>'
                                : '<div class="text-muted" style="font-size:11px">' . lang(array(
                                        'string' => '{var:1} request{suffix:1} recorded',
                                        'vars'   => number_format($pf_hits),
                                        'suffix' => ($pf_hits == 1 ? '' : 's'),
                                    )) . '</div>')
                        . '</div>
                    </div>';

                    // Slowest pages by average. Ordered by average rather than
                    // by worst case, because one freak request says less than a
                    // page that is consistently slow for everyone who opens it.
                    //
                    // No minimum hit count. An earlier version required three
                    // hits to keep one-off flukes out, and on a quiet site that
                    // silently hid the entire front end: product and blog pages
                    // get a visit or two a day, while the admin screens the
                    // operator keeps refreshing sail past the threshold. The
                    // widget then disagreed with the report next to it, which
                    // is worse than showing an occasional outlier. The hit
                    // count is in the bar's tooltip for context.
                    $pf_rows = '';

                    $pf_result = mysqli_query(
                        db::$con,
                        "SELECT
                            label,
                            area,
                            SUM(hits)                              AS hits,
                            SUM(total_ms) / GREATEST(SUM(hits), 1) AS avg_ms,
                            MAX(max_ms)                            AS max_ms
                         FROM perf_stats
                         WHERE hour_start >= " . (int) $pf_day_ago . "
                           AND area = 'frontend'
                         GROUP BY label, area
                         ORDER BY avg_ms DESC
                         LIMIT 5"
                    );

                    $pf_pages = $pf_result ? mysqli_fetch_items($pf_result) : array();

                    // Scale the bars against the slowest entry on the list, not
                    // against some absolute ceiling: with one page at 40 seconds
                    // every other bar would round to nothing.
                    $pf_peak = 0;

                    foreach ($pf_pages as $pf_page) {
                        if ((int) $pf_page['avg_ms'] > $pf_peak) {
                            $pf_peak = (int) $pf_page['avg_ms'];
                        }
                    }

                    foreach ($pf_pages as $pf_page) {
                        $pf_page_avg = (int) $pf_page['avg_ms'];
                        $pf_width = ($pf_peak > 0) ? (int) round(100 * $pf_page_avg / $pf_peak) : 0;

                        if ($pf_width < 3) {
                            $pf_width = 3;
                        }

                        if ($pf_page_avg >= $pf_slow_ms) {
                            $pf_bar = 'bg-danger';
                        } elseif ($pf_page_avg >= 500) {
                            $pf_bar = 'bg-warning';
                        } else {
                            $pf_bar = 'bg-secondary';
                        }

                        // Front-end labels are URLs and can be very long; show
                        // the tail, which is the part that identifies the page.
                        $pf_label = $pf_page['label'];

                        if (mb_strlen($pf_label) > 34) {
                            $pf_label = '…' . mb_substr($pf_label, -33);
                        }

                        $pf_rows .= '
                        <div class="mb-2">
                            <div class="d-flex align-items-center justify-content-between" style="gap:6px">
                                <span class="text-truncate" style="font-size:12px" title="' . h($pf_page['label']) . '">' . h($pf_label) . '</span>
                                <span class="text-muted flex-shrink-0" style="font-size:11px">' . number_format($pf_page_avg) . ' ms</span>
                            </div>
                            <div class="progress mt-1" style="height:4px;background:rgba(0,0,0,.06)" title="' . lang(array(
                                'string' => '{var:1} request{suffix:1} · peak {var:2} ms',
                                'vars'   => array(number_format((int) $pf_page['hits']), number_format((int) $pf_page['max_ms'])),
                                'suffix' => ((int) $pf_page['hits'] == 1 ? '' : 's'),
                            )) . '">
                                <div class="progress-bar ' . $pf_bar . '" style="width:' . $pf_width . '%"></div>
                            </div>
                        </div>';
                    }

                    if ($pf_rows === '') {
                        $pf_rows = '
                        <div class="text-center py-4">
                            <i class="bi bi-speedometer2 d-block mb-2" style="font-size:22px;opacity:.35"></i>
                            <p class="text-muted mb-0" style="font-size:12px">' . lang('No data yet for the selected period.') . '</p>
                        </div>';
                    }

                    $pf_footer = '';

                    if ($user['role'] < 3) {
                        $pf_footer = '
                        <div class="card-footer border-0 bg-reset py-1 text-center">
                            <a href="view_performance_log.php" class="text-decoration-none" style="font-size:11px">'
                            . lang('Performance Log') . ' <i class="bi bi-arrow-right-short"></i></a>
                        </div>';
                    }

                    $output_data = '
                        <div class="card-body p-0 d-flex flex-column" style="overflow-x:hidden;overflow-y:auto">
                            ' . $pf_gauge . '
                            <div class="d-flex border-bottom">
                                <div class="flex-fill px-3 py-2">
                                    <div class="fw-semibold text-' . h($pf_avg_color) . '" style="font-size:17px;line-height:1">' . number_format($pf_avg) . ' <span style="font-size:11px">ms</span></div>
                                    <div class="text-muted text-truncate" style="font-size:11px">' . lang('Average') . '</div>
                                </div>
                                <div class="flex-fill px-3 py-2 border-start">
                                    <div class="fw-semibold text-' . h($pf_slow_color) . '" style="font-size:17px;line-height:1">' . number_format($pf_slow) . '</div>
                                    <div class="text-muted text-truncate" style="font-size:11px">' . lang(array(
                                        'string' => 'Slower than {var:1} ms',
                                        'vars'   => number_format($pf_slow_ms),
                                    )) . '</div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center justify-content-between px-3 pt-2 pb-1">
                                <span class="text-muted" style="font-size:11px">' . lang('Slowest pages') . '</span>
                                <span class="text-muted" style="font-size:10px">' . lang('Front end') . ' · ' . lang('Last 24 hours') . '</span>
                            </div>
                            <div class="px-3 pb-2">' . $pf_rows . '</div>
                        </div>' . $pf_footer;

                    $response = array(
                        'status' => 'success',
                        'message' => 'Action Success',
                        'data' => $output_data,
                    );
                    echo encode_json($response);
                    exit();
                    break;
                } else {
                    $response = array(
                        'status' => 'error',
                        'message' => 'Access denied'
                    );
                    echo encode_json($response);
                    exit();
                    break;
                }

            case '24':

                // Empty slot. The activity summary that lived here counted
                // orders, forms and contacts over eight days -- twenty-four
                // COUNT queries per dashboard load -- and showed all three to
                // anyone holding manage_ecommerce, although the forms and
                // contacts widgets are gated on manage_forms and
                // manage_contacts. Each figure now heads the widget it belongs
                // to, behind that widget's own permission.
                //
                // The id is answered rather than removed because of the
                // upgrade window. welcome.php no longer prints a card for 2, so
                // a fresh page never asks -- but a tab left open across the
                // update still holds the old card and asks on its first run.
                // The switch's default branch answers 'Invalid widget id', and
                // the dashboard only replaces a placeholder on status success,
                // so that card would sit on its skeleton until reload. An empty
                // success clears it instead.
                $response = array(
                    'status' => 'success',
                    'message' => lang('Data Received successfully.'),
                    'data' => '',
                );
                echo encode_json($response);
                exit();
                break;
            case '25':
                // ── Comment moderation ──────────────────────────────────
                //
                // An unapproved comment is invisible to the person who wrote
                // it and to everyone else, and nothing on the dashboard has
                // ever said one was waiting. view_comments.php exists, but a
                // queue nobody is reminded of is a queue that grows.
                //
                // Scope follows view_comments.php exactly: the comment's page
                // decides, through its folder. check_edit_access() returns
                // true for every folder below role 3, so only contributors
                // need the folder list — and resolving it in SQL keeps this
                // to two queries instead of one recursive access check per
                // row.
                $cm_where = "(comments.published = '0')";
                $cm_no_access = false;

                if ($user['role'] == 3) {

                    $cm_folders = get_folders_that_user_has_access_to($user['id']);

                    if ($cm_folders) {

                        $cm_folder_ids = array();

                        foreach ($cm_folders as $cm_folder_id) {
                            $cm_folder_ids[] = "'" . e($cm_folder_id) . "'";
                        }

                        $cm_where .= " AND (page.page_folder IN (" . implode(',', $cm_folder_ids) . "))";

                    } else {
                        $cm_no_access = true;
                    }
                }

                $cm_items = array();
                $cm_total = 0;

                if (!$cm_no_access) {

                    $cm_items = db_items(
                        "SELECT
                            comments.id,
                            comments.name,
                            comments.message,
                            comments.rating,
                            comments.created_timestamp,
                            page.page_name
                         FROM comments
                         LEFT JOIN page ON comments.page_id = page.page_id
                         WHERE " . $cm_where . "
                         ORDER BY comments.created_timestamp DESC
                         LIMIT 5");

                    $cm_total = (int) db_value(
                        "SELECT COUNT(*)
                         FROM comments
                         LEFT JOIN page ON comments.page_id = page.page_id
                         WHERE " . $cm_where);
                }

                $cm_rows = '';

                foreach ($cm_items as $cm_item) {

                    // The stored body is rich text from the comment form.
                    // Tags are stripped rather than rendered: this is a
                    // three-line preview inside a card, and a stray <div> or
                    // an unclosed tag would take the widget's layout with it.
                    $cm_preview = trim(preg_replace('/\s+/', ' ', strip_tags($cm_item['message'])));

                    if (mb_strlen($cm_preview) > 90) {
                        $cm_preview = mb_substr($cm_preview, 0, 89) . '…';
                    }

                    $cm_stars = '';

                    if ((int) $cm_item['rating'] > 0) {
                        $cm_stars = '<span class="text-warning ms-1 flex-shrink-0" style="font-size:10px">'
                            . str_repeat('★', min(5, (int) $cm_item['rating'])) . '</span>';
                    }

                    $cm_where_text = ($cm_item['page_name'] != '')
                        ? $cm_item['page_name']
                        : lang('Unknown');

                    $cm_rows .= '
                    <a class="d-block px-3 py-2 border-top text-decoration-none text-body" href="edit_comment.php?id=' . (int) $cm_item['id'] . '&amp;send_to=' . h(PATH . SOFTWARE_DIRECTORY . '/view_comments.php') . '">
                        <div class="d-flex align-items-center" style="gap:6px">
                            <span class="text-truncate fw-semibold" style="font-size:12px">' . h($cm_item['name'] != '' ? $cm_item['name'] : lang('Unknown')) . '</span>
                            ' . $cm_stars . '
                            <span class="text-muted ms-auto flex-shrink-0" style="font-size:10px">' . get_relative_time(array('timestamp' => (int) $cm_item['created_timestamp'])) . '</span>
                        </div>
                        <div class="text-muted" style="font-size:11px;line-height:1.35">' . h($cm_preview) . '</div>
                        <div class="text-muted text-truncate" style="font-size:10px;opacity:.75">' . h($cm_where_text) . '</div>
                    </a>';
                }

                if ($cm_rows === '') {
                    $cm_rows = '
                    <div class="text-center py-4">
                        <i class="bi bi-check2-circle d-block mb-2 text-success" style="font-size:22px;opacity:.8"></i>
                        <p class="text-muted mb-0" style="font-size:12px">' . lang('No comments are waiting for approval.') . '</p>
                    </div>';
                }

                // The list stops at five; the number says whether that is the
                // whole queue or the top of it.
                $cm_more = '';

                if ($cm_total > count($cm_items)) {
                    $cm_more = '
                    <div class="px-3 py-1 text-center border-top">
                        <span class="text-muted" style="font-size:10px">' . lang(array(
                            'string' => '{var:1} more waiting',
                            'vars'   => number_format($cm_total - count($cm_items)),
                        )) . '</span>
                    </div>';
                }

                $output_data = '
                <div class="card-body p-0 d-flex flex-column" style="overflow-x:hidden;overflow-y:auto">
                    <div class="d-flex align-items-center justify-content-between px-3 pt-2 pb-1">
                        <span class="text-muted" style="font-size:11px">' . lang('Waiting for approval') . '</span>
                        <span class="fw-semibold text-' . ($cm_total > 0 ? 'warning' : 'success') . '" style="font-size:15px">' . number_format($cm_total) . '</span>
                    </div>
                    ' . $cm_rows . '
                    ' . $cm_more . '
                </div>
                <div class="card-footer border-0 bg-reset py-1 text-center">
                    <a href="view_comments.php" class="text-decoration-none" style="font-size:11px">'
                    . lang('Comments') . ' <i class="bi bi-arrow-right-short"></i></a>
                </div>';

                $response = array(
                    'status' => 'success',
                    'message' => 'Action Success',
                    'data' => $output_data,
                );
                echo encode_json($response);
                exit();
                break;

            case '26':
                // ── Pending refunds ─────────────────────────────────────
                //
                // cancel_order.php is customer-facing self-service. A customer
                // cancels; Iyzipay's auto-refund either fails or was never
                // possible for that payment method; process_order_cancellation()
                // writes orders.refund_status = 'manual_required' and a
                // REFUND ACTION REQUIRED line into the activity log.
                //
                // That log line was the only thing on the operator's side. On
                // the customer's side the cancellation email already says
                // "Payment refunds are processed manually. Please contact us
                // for refund status." — so the software has made a promise
                // that nothing in the panel reminded anyone to keep.
                //
                // view_orders.php filters on orders.status, so "cancelled" is
                // reachable but "still owes a refund" was not; the flag showed
                // up one order at a time on view_order.php. A refund filter is
                // added there in the same change for the full list — this card
                // exists to say the queue is non-empty at all.
                //
                // Oldest first: a customer waiting on money escalates to a
                // chargeback, and the fee plus the gateway risk score costs
                // more than the refund did.
                if (defined('ECOMMERCE') && ECOMMERCE
                    && (($user['role'] < 3) || (isset($user['manage_ecommerce']) && $user['manage_ecommerce']))
                ) {

                    // Columns arrive with the 2026.1.27 upgrade. Without them
                    // there is nothing to report and nothing is wrong — an
                    // installation that has not been upgraded is not an
                    // installation with unpaid refunds.
                    if (!db_item("SHOW COLUMNS FROM orders LIKE 'refund_status'")) {

                        $output_data = '
                        <div class="card-body d-flex align-items-center justify-content-center text-center">
                            <div>
                                <i class="bi bi-database-exclamation d-block mb-2" style="font-size:22px;opacity:.4"></i>
                                <p class="text-muted mb-0" style="font-size:12px">' . lang('The refund columns do not exist yet. Please run the software upgrade to create them.') . '</p>
                            </div>
                        </div>';

                        $response = array('status' => 'success', 'message' => 'Action Success', 'data' => $output_data);
                        echo encode_json($response);
                        exit();
                        break;
                    }

                    // 'pending' and 'failed' belong here as much as
                    // 'manual_required': all three mean money has not gone
                    // back yet. Only 'refunded' and '' are settled.
                    //
                    // No orders.status test. refund_status is written by the
                    // cancellation path alone, so it already implies a
                    // cancelled order, and adding the condition would push the
                    // planner off idx_refund_status for nothing.
                    $rf_states = "('manual_required', 'failed', 'pending')";

                    // Grouped by currency on purpose. Summing a 500 TRY refund
                    // and a 40 EUR refund into "540" would be a made-up number
                    // on any multi-currency shop, so the total is only shown
                    // when every waiting refund is in one currency; otherwise
                    // the count stands on its own.
                    $rf_groups = db_items(
                        "SELECT currency_code, COUNT(*) AS orders_count, SUM(total) AS orders_total
                         FROM orders
                         WHERE refund_status IN " . $rf_states . "
                         GROUP BY currency_code");

                    $rf_total_count = 0;

                    foreach ($rf_groups as $rf_group) {
                        $rf_total_count += (int) $rf_group['orders_count'];
                    }

                    $rf_amount_line = '';

                    if (count($rf_groups) === 1) {
                        $rf_amount_line = number_format(((float) $rf_groups[0]['orders_total']) / 100, 2, ',', '.')
                            . ' ' . h($rf_groups[0]['currency_code']);
                    }

                    $rf_items = db_items(
                        "SELECT
                            orders.id,
                            orders.order_number,
                            orders.billing_first_name,
                            orders.billing_last_name,
                            orders.total,
                            orders.currency_code,
                            orders.cancelled_at,
                            orders.refund_status
                         FROM orders
                         WHERE orders.refund_status IN " . $rf_states . "
                         ORDER BY orders.cancelled_at ASC, orders.id ASC
                         LIMIT 5");

                    $rf_rows = '';

                    foreach ($rf_items as $rf_item) {

                        $rf_waited = time() - (int) $rf_item['cancelled_at'];

                        // Two days is where a refund stops being slow and
                        // starts being the thing a customer opens a dispute
                        // about.
                        $rf_wait_class = ($rf_waited >= 172800) ? 'text-danger' : 'text-warning';

                        $rf_who = trim((string) $rf_item['billing_first_name'] . ' ' . (string) $rf_item['billing_last_name']);

                        if ($rf_who === '') {
                            $rf_who = lang('Unknown');
                        }

                        $rf_number = ((string) $rf_item['order_number'] !== '')
                            ? (string) $rf_item['order_number']
                            : (string) (int) $rf_item['id'];

                        $rf_rows .= '
                        <a class="d-block px-3 py-2 border-top text-decoration-none text-body" href="view_order.php?id=' . (int) $rf_item['id'] . '&amp;send_to=' . h(PATH . SOFTWARE_DIRECTORY . '/view_orders.php') . '">
                            <div class="d-flex align-items-center" style="gap:6px">
                                <span class="text-truncate fw-semibold" style="font-size:12px">#' . h($rf_number) . ' · ' . h($rf_who) . '</span>
                                <span class="' . $rf_wait_class . ' ms-auto flex-shrink-0" style="font-size:10px">' . get_relative_time(array('timestamp' => (int) $rf_item['cancelled_at'])) . '</span>
                            </div>
                            <div class="text-muted" style="font-size:11px">'
                                . number_format(((float) $rf_item['total']) / 100, 2, ',', '.') . ' ' . h($rf_item['currency_code'])
                            . '</div>
                        </a>';
                    }

                    if ($rf_rows === '') {
                        $rf_rows = '
                        <div class="text-center py-4">
                            <i class="bi bi-check2-circle d-block mb-2 text-success" style="font-size:22px;opacity:.8"></i>
                            <p class="text-muted mb-0" style="font-size:12px">' . lang('No refunds are waiting.') . '</p>
                        </div>';
                    }

                    $rf_more = '';

                    if ($rf_total_count > count($rf_items)) {
                        $rf_more = '
                        <div class="px-3 py-1 text-center border-top">
                            <span class="text-muted" style="font-size:10px">' . lang(array(
                                'string' => '{var:1} more waiting',
                                'vars'   => number_format($rf_total_count - count($rf_items)),
                            )) . '</span>
                        </div>';
                    }

                    $output_data = '
                    <div class="card-body p-0 d-flex flex-column" style="overflow-x:hidden;overflow-y:auto">
                        <div class="d-flex align-items-center justify-content-between px-3 pt-2 pb-1">
                            <div class="overflow-hidden">
                                <span class="text-muted d-block" style="font-size:11px">' . lang('Refund Pending') . '</span>'
                                . ($rf_amount_line !== ''
                                    ? '<span class="text-muted text-truncate d-block" style="font-size:10px">' . $rf_amount_line . '</span>'
                                    : '')
                            . '</div>
                            <span class="fw-semibold text-' . ($rf_total_count > 0 ? 'danger' : 'success') . ' flex-shrink-0" style="font-size:15px">' . number_format($rf_total_count) . '</span>
                        </div>
                        ' . $rf_rows . '
                        ' . $rf_more . '
                    </div>
                    <div class="card-footer border-0 bg-reset py-1 text-center">
                        <a href="view_orders.php?status=refund_pending" class="text-decoration-none" style="font-size:11px">'
                        . lang('Orders') . ' <i class="bi bi-arrow-right-short"></i></a>
                    </div>';

                    $response = array(
                        'status' => 'success',
                        'message' => 'Action Success',
                        'data' => $output_data,
                    );
                    echo encode_json($response);
                    exit();
                    break;
                } else {
                    $response = array(
                        'status' => 'error',
                        'message' => 'Access denied'
                    );
                    echo encode_json($response);
                    exit();
                    break;
                }
            // if there is no id.
            default:
                $response = array(
                    'status' => 'error',
                    'message' => 'Invalid widget id.'
                );
                echo encode_json($response);
                exit();
                break;
        }
        exit();
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

    case 'software_backup':

        // A backup writes the whole database out to disk and copies every file
        // beside it.  The action sits in the exemption list at the top of this
        // file and had nothing of its own in the general gate's place, so the
        // steps ran for whoever could reach the address.  Manager and a valid
        // token is the same reach backups.php asks for at its own door, and
        // now the door the Backups view knocks on asks the same.
        $user = validate_user();
        validate_area_access($user, 'manager');
        validate_token();

        // This feature can take a long time to run for a large site,
        // so increase the allowed execution time for the PHP script.
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 500);
        $step = $request['step'];
        $backup_name = $request['backup_name'];

        $backup_location = 'data/backups/';
        $backup_folder_name = $backup_name;

        switch ($step) {

            case 'create_backup_folder':
                if (!$backup_name) {
                    $hostname_clean = defined('HOSTNAME') ? HOSTNAME : '';
                    $backup_name = ($hostname_clean ? $hostname_clean . '_' : '') . date('Y-m-d@H-i');
                }

                // Replace remaining special characters (if any)
                $sReplace = array('.', ',', '!', '?');
                $backup_folder_name = str_replace($sReplace, '_', $backup_name);

                //check if directory is exists
                //if not exist Create directory.
                if (!file_exists($backup_location . $backup_folder_name)) {
                    mkdir($backup_location . $backup_folder_name, 0777, true);
                }
                //return success json output
                $response = array(
                    'status' => 'success',
                    'backup_name' => $backup_folder_name,
                    'message' => lang('Site backup folder create successful. Mysql dumb creating, please wait...')
                );
                echo encode_json($response);
                exit();
                break;

            case 'create_mysql_dumb':
                include_once('mysqldump.php');

                //Create mysql dump file named slq.sql and save it in backup directory
                // first backup Mysql because, if there is timeout when file copy mysql important for us. so even timeout to copy files or layouts we have mysql dump anyway.
                try {
                    $dump = new IMysqldump\Mysqldump('mysql:host=' . DB_HOST . ';dbname=' . DB_DATABASE . '', '' . DB_USERNAME . '', '' . DB_PASSWORD . '');
                    $dump->start($backup_location . $backup_folder_name . '/sql.sql');
                } catch (\Exception $e) {
                    $backups_error_message = $e->getMessage();

                    //if mysql error and backup folder is empty, delete it.
                    if (is_dir($backup_location . $backup_folder_name) && count(glob($backup_location . $backup_folder_name . '/*')) === 0) {
                        rmdir($backup_location . $backup_folder_name);
                    }

                    log_activity('Creating Mysql Dumb is Failure. Because: ' . h($backups_error_message), $_SESSION['sessionusername']);
                    //return error json output
                    $response = array(
                        'status' => 'error',
                        'message' => h($backups_error_message)
                    );
                    echo encode_json($response);
                    exit();
                }

                //return success json output
                $response = array(
                    'status' => 'success',
                    'backup_name' => $backup_folder_name,
                    'message' => lang('Mysql dumb created in backup directory successful. Clearing old files in directory, please wait...')
                );
                echo encode_json($response);
                exit();
                //Mysql Backup complete
                break;

            case 'clear_files_and_layouts':
                //Prepare for files and layouts**
                //if files directory not exist Create directory
                if (!file_exists($backup_location . $backup_folder_name . '/files')) {
                    mkdir($backup_location . $backup_folder_name . '/files', 0777, true);
                }
                //if layouts directory not exist Create directory
                if (!file_exists($backup_location . $backup_folder_name . '/layouts')) {
                    mkdir($backup_location . $backup_folder_name . '/layouts', 0777, true);
                }
                //CLEAR//
                // delete all files from template files directory
                $files = glob($backup_location . $backup_folder_name . '/files/{,.}*', GLOB_BRACE); // get all file names
                foreach ($files as $file) { // iterate files
                    if (is_file($file))
                        unlink($file); // delete file
                }
                // delete all files from template layouts directory
                $layouts = glob($backup_location . $backup_folder_name . '/layouts/{,.}*', GLOB_BRACE); // get all layouts names
                foreach ($layouts as $layout) { // iterate layouts files
                    if (is_file($layout))
                        unlink($layout); // delete layouts files
                }

                //return success json output
                $response = array(
                    'status' => 'success',
                    'backup_name' => $backup_folder_name,
                    'message' => lang('Files and layouts cleared in backup directory. Copying files, please wait...')
                );
                echo encode_json($response);
                exit();
                break;


            case 'move_files':

                //WRITE//
                // prepare path to template files
                $backup_files_path = $backup_location . $backup_folder_name . '/files/';
                $handle = opendir(FILE_DIRECTORY_PATH);
                // copy files to backup directory
                while (false !== ($file = readdir($handle))) {
                    if (($file != '.') && ($file != '..')) {
                        copy(FILE_DIRECTORY_PATH . '/' . $file, $backup_files_path . $file);
                    }
                }
                closedir($handle);

                //return success json output
                $response = array(
                    'status' => 'success',
                    'backup_name' => $backup_folder_name,
                    'message' => lang('Files copied to backup directory. Copying layouts, please wait...')
                );
                echo encode_json($response);
                exit();
                break;

            case 'move_layouts':

                //WRITE//
                // prepare path to template layouts
                $backup_layouts_path = $backup_location . $backup_folder_name . '/layouts/';
                $handle = opendir(LAYOUT_DIRECTORY_PATH);
                // copy files to backup directory
                while (false !== ($file = readdir($handle))) {
                    if (($file != '.') && ($file != '..')) {
                        copy(LAYOUT_DIRECTORY_PATH . '/' . $file, $backup_layouts_path . $file);
                    }
                }
                closedir($handle);

                //return success json output
                $response = array(
                    'status' => 'success',
                    'backup_name' => $backup_folder_name,
                    'message' => lang('Layouts copied to backup directory. Creating .htaccess for security reason, please wait...')
                );
                echo encode_json($response);
                exit();
                break;

            case 'create_htaccess_and_config':
                //create .htaccess file to make directory unaccessable.
                file_put_contents($backup_location . $backup_folder_name . '/.htaccess', 'deny from all');
                //return success json output
                $response = array(
                    'status' => 'success',
                    'backup_name' => $backup_folder_name,
                    'message' => lang('Htaccess create in backup directory successful. Check backup folder create success or not, please wait...')
                );
                echo encode_json($response);
                exit();
                break;

            case 'check':

                if (file_exists($backup_location . $backup_folder_name)) {

                    if (file_exists($backup_location . $backup_folder_name . '/sql.sql')) {
                        if (file_exists($backup_location . $backup_folder_name . '/files')) {
                            if (file_exists($backup_location . $backup_folder_name . '/layouts')) {
                                $liveform_backups = new liveform('backups');

                                log_activity("Software Backup (" . $backup_name . ") Success", $_SESSION['sessionusername']);
                                // Add notice to liveform.
                                $liveform_backups->add_notice('Software Backup (' . $backup_name . ') Create Success.');
                                //return success json output
                                $response = array(
                                    'status' => 'success',
                                    'backup_name' => $backup_folder_name,
                                    'message' => lang('Software Backup process Successful. Page will be refresh...')
                                );
                                echo encode_json($response);
                                exit();
                            }
                        }
                    }

                }

                //return error json output
                $response = array(
                    'status' => 'error',
                    'message' => lang('software Backup check has error. backup maybe still created but we cant provide.')
                );
                echo encode_json($response);
                exit();


                break;

            default:
                //return error json output
                $response = array(
                    'status' => 'error',
                    'message' => lang('software Backup steps error.')
                );
                echo encode_json($response);
                exit();
        }
        break;

    case 'software_update_check':
        // Async background check triggered by output_header() JS injection.
        // Runs the daily/periodic software update check without blocking the page load.
        validate_token();
        $user = validate_user();
        $current_timestamp = time();
        if (
            (defined('SOFTWARE_UPDATE_CHECK') == false or SOFTWARE_UPDATE_CHECK == true)
            and ($current_timestamp >= (LAST_SOFTWARE_UPDATE_CHECK_TIMESTAMP + 259200))
        ) {
            require(dirname(__FILE__) . '/software_update_check.php');
            software_update_check();
            exit();
        }
        break;

    case 'software_update':
        //software update is not software update check.
        //it is action to update software from software_update.php
        //used api because some slow servers connections down, timeout or somethings like this when do this one step.

        // The steps below download a package and unpack it over the codebase.
        // The action sits in the exemption list at the top of this file, so
        // the general gate does not run for it: ask here for the same thing
        // software_update.php asks at its own door - a signed-in manager with
        // a valid token - before any step is looked at.
        if (!USER_LOGGED_IN) {
            respond(array(
                'status' => 'error',
                'message' => 'Invalid login.'
            ));
        }
        $user = validate_user();
        validate_area_access($user, 'manager');
        validate_token();

        // This feature can take a long time to run for a large site,
        // so increase the allowed execution time for the PHP script.
        ini_set('max_execution_time', '9999');

        $step = isset($request['step']) ? $request['step'] : '';
        if (!in_array($step, array('check', 'download', 'replace'), true)) {
            respond(array(
                'status' => 'error',
                'message' => 'Invalid step.'
            ));
        }
        switch ($step) {
            case 'check':
                //check if there is really have a software update, also software_update page check but may user open 2 page and update and update again.
                // now if try software update after an update user get error message and update stop.
                if (!function_exists('curl_init')) {
                    $liveform->mark_error('Update', 'Software update check could not communicate with the software update server, because cURL is not installed, so it is not known if there is a software update available.');
                }
                $request = array();
                $request['hostname'] = HOSTNAME_SETTING;
                $request['url'] = URL_SCHEME . HOSTNAME_SETTING . PATH;
                $request['version'] = VERSION;
                $request['edition'] = EDITION;
                $request['uname'] = function_exists('php_uname') ? php_uname() : PHP_OS; // disable_functions on some hosts
                $request['os'] = PHP_OS;
                $request['web_server'] = $_SERVER['SERVER_SOFTWARE'];
                $request['php_version'] = phpversion();
                $request['mysql_version'] = db("SELECT VERSION()");
                $request['installer'] = INSTALLER;
                $request['private_label'] = PRIVATE_LABEL;
                $data = encode_json($request);
                $API = '59593DS72233483322T669223344';
                // Beta sites ask their own question; see pg_update_channel().
                $REQUEST = pg_update_request_key();

                $ch = curl_init();
                // Identify this installation on outgoing requests. Sent with no
                // User-Agent, a request looks like an anonymous client to the receiving
                // server's firewall and gets rejected — which is how Pinegrap ended up
                // blocking its own licence and update checks.
                curl_setopt($ch, CURLOPT_USERAGENT, function_exists('pinegrap_user_agent') ? pinegrap_user_agent() : 'Pinegrap');
                curl_setopt($ch, CURLOPT_URL, 'https://www.kodpen.com/api2?API=' . $API . '&REQUEST=' . $REQUEST);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
                // Verify the certificate. See pg_curl_tls() for why this matters most
                // on the update and licence channel.
                pg_curl_tls($ch);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($data)
                ));

                // if there is a proxy address, then send cURL request through proxy
                if (PROXY_ADDRESS != '') {
                    curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
                    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                    curl_setopt($ch, CURLOPT_PROXY, PROXY_ADDRESS);
                }

                $response = curl_exec($ch);
                $curl_errno = curl_errno($ch);
                $curl_error = curl_error($ch);
                curl_close($ch);

                if ($response === false) {
                    log_activity(
                        'software update check could not communicate with the software update server, so it is not known if there is a software update available. cURL Error Number: ' . $curl_errno . '. cURL Error Message: ' . $curl_error . '.'
                    );
                    //return error json output
                    $response = array(
                        'status' => 'error',
                        'message' => 'No access to the update server.'
                    );
                    echo encode_json($response);
                    exit();
                }

                $response = decode_json($response);

                if (!isset($response['version'])) {
                    log_activity('software update check received an invalid response from the software update server, so it is not known if there is a software update available');
                    //return error json output
                    $response = array(
                        'status' => 'error',
                        'message' => 'No response from the update server.'
                    );
                    echo encode_json($response);
                    exit();

                }
                // If the software update check is not disabled in the config.php file,
                // then continue to determine if there is a software update.
                if (
                    (defined('SOFTWARE_UPDATE_CHECK') == FALSE)
                    || (SOFTWARE_UPDATE_CHECK == TRUE)
                ) {
                    // figure out if new version is greater than old version

                    $new_version = trim($response['version']);
                    $new_version_parts = explode('.', $new_version);

                    $old_version = VERSION;
                    $old_version_parts = explode('.', $old_version);

                    // assume that new version is not greater than old version, until we find out otherwise
                    $new_version_is_greater_than_old_version = FALSE;

                    // if the major number of the new version is greater than the major number of the old version,
                    // then the new version is greater than the old version
                    if ($new_version_parts[0] > $old_version_parts[0]) {
                        $new_version_is_greater_than_old_version = TRUE;

                        // else if the major number of the new version is equal to the major number of the old version,
                        // then continue to check
                    } elseif ($new_version_parts[0] == $old_version_parts[0]) {
                        // if the minor number of the new version is greater than the minor number of the old version,
                        // then the new version is greater than the old version
                        if ($new_version_parts[1] > $old_version_parts[1]) {
                            $new_version_is_greater_than_old_version = TRUE;

                            // else if the minor number of the new version is equal to the minor number of the old version,
                            // then continue to check
                        } elseif ($new_version_parts[1] == $old_version_parts[1]) {
                            // if the maintenance number of the new version is greater than the maintenance number of the old version,
                            // then the new version is greater than the old version
                            if ($new_version_parts[2] > $old_version_parts[2]) {
                                $new_version_is_greater_than_old_version = TRUE;
                            }
                        }
                    }

                    // assume that there is not an available software update until we find out otherwise
                    $software_update_available = 0;

                    // if the new version is greater than the old version, then there is an available software update
                    if ($new_version_is_greater_than_old_version == TRUE) {
                        $software_update_available = 1;
                    }

                }
                //there is no software
                if ($software_update_available == 0) {
                    //return error json output
                    $response = array(
                        'status' => 'error',
                        'message' => 'There is no update available.'
                    );
                    echo encode_json($response);
                    exit();
                }
                //there is software update so we can go step 2:Download the update file.
                //return success json output
                $response = array(
                    'status' => 'success',
                    'message' => 'Downloading...'
                );
                echo encode_json($response);
                exit();

                break;
            case 'download':
                //Step 2: download update file from curl
                // The package of this installation's channel. The name is asked for
                // once and reused below, so the file the replace step opens is the
                // file this step wrote.
                $update_package = pg_update_package_file();

                $ch = curl_init("https://www.kodpen.com/" . $update_package);
                // Identify this installation on outgoing requests. Sent with no
                // User-Agent, a request looks like an anonymous client to the receiving
                // server's firewall and gets rejected — which is how Pinegrap ended up
                // blocking its own licence and update checks.
                curl_setopt($ch, CURLOPT_USERAGENT, function_exists('pinegrap_user_agent') ? pinegrap_user_agent() : 'Pinegrap');
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15); // seconds to establish the connection
                curl_setopt($ch, CURLOPT_TIMEOUT, 120);       // total seconds allowed for the zip download

                // if there is a proxy address, then send cURL request through proxy
                if (PROXY_ADDRESS != '') {
                    curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
                    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                    curl_setopt($ch, CURLOPT_PROXY, PROXY_ADDRESS);
                }
                $raw = curl_exec($ch);
                $curl_errno = curl_errno($ch);
                $curl_error = curl_error($ch);
                $http_status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $expected_bytes = (int) curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
                curl_close($ch);

                // A non-200 body is still a successful transfer as far as cURL
                // is concerned. Without this check a 403 from the update
                // server's own firewall, or a 404 page, gets written to disk
                // as pinegrap_software_update.zip and fails three steps later
                // as an unexplained archive error.
                if ($raw !== false && $http_status !== 200) {
                    log_activity('software update download returned HTTP ' . $http_status . ' instead of the update package.');

                    $response = array(
                        'status' => 'error',
                        'message' => 'The update server returned HTTP ' . $http_status . ' instead of the update package.'
                    );
                    echo encode_json($response);
                    exit();
                }

                // A transfer cut short mid-stream is not an error to cURL
                // either; compare against the length the server promised.
                if ($raw !== false && $expected_bytes > 0 && strlen($raw) < $expected_bytes) {
                    log_activity('software update download was truncated: ' . strlen($raw) . ' of ' . $expected_bytes . ' bytes.');

                    $response = array(
                        'status' => 'error',
                        'message' => 'The download was cut short (' . strlen($raw) . ' of ' . $expected_bytes . ' bytes). Please try again.'
                    );
                    echo encode_json($response);
                    exit();
                }

                if ($raw !== false && !pg_looks_like_zip($raw)) {
                    log_activity('software update download was not a zip archive.');

                    $response = array(
                        'status' => 'error',
                        'message' => 'What was downloaded is not a zip archive. A proxy or firewall may have replaced the response.'
                    );
                    echo encode_json($response);
                    exit();
                }

                if ($raw === false) {
                    // there is an error about download so notice user and log activiy
                    log_activity(
                        'software update file get could not communicate with the software update server, may its about update server so try it later. cURL Error Number: ' . $curl_errno . '. cURL Error Message: ' . $curl_error . '.'
                    );
                    //return error json output
                    $response = array(
                        'status' => 'error',
                        'message' => 'Error while get files from the update server.' . pg_curl_tls_hint($curl_errno)
                    );
                    echo encode_json($response);
                    exit();
                }

                // Zip file name
                $filename = $update_package;
                if (file_exists($filename)) {
                    unlink($filename);
                }

                // 'x' fails when the file still exists, and the unlink above
                // can fail on permissions. Writing through an unchecked handle
                // emitted a warning and carried on as if it had worked.
                $fp = @fopen($filename, 'wb');

                if ($fp === false) {
                    $response = array(
                        'status' => 'error',
                        'message' => 'Could not create the update file. Check write permission for the software directory.'
                    );
                    echo encode_json($response);
                    exit();
                }

                $written = fwrite($fp, $raw);
                fclose($fp);

                // A short write means a full disk. Left unchecked it produced a
                // truncated archive that extracted partially.
                if ($written === false || $written < strlen($raw)) {
                    @unlink($filename);

                    $response = array(
                        'status' => 'error',
                        'message' => 'The update file could not be written completely. The disk may be full.'
                    );
                    echo encode_json($response);
                    exit();
                }
                //zip file download success we can go step 3: replace the software files
                $response = array(
                    'status' => 'success',
                    'message' => 'Files overwriting...'
                );
                echo encode_json($response);
                exit();
                break;

            case 'replace':
                //Step 3: replace files.
                define('_PATH', dirname(__FILE__));
                // Zip file name — the channel's package, the same name the download step used.
                $filename = pg_update_package_file();
                // Unzip path
                $path = _PATH . "/../";

                // pg_extract_archive() checks archive consistency BEFORE
                // touching anything, refuses to start while a file on disk
                // cannot be replaced (another owner, read-only), then proves
                // every entry landed on disk with the archive's own size and
                // CRC afterwards - not merely that a file of that name exists,
                // which an old copy the server kept would satisfy.
                //
                // The previous code called extractTo() and discarded its
                // return value. Extraction stops at the first entry it cannot
                // write — one locked file, one permission problem, a full disk
                // — and everything after it is silently never created, while
                // the screen reports a successful update. That is why an
                // update could leave files missing and need repairing by hand.
                $extract = pg_extract_archive($filename, $path);

                if (!$extract['ok']) {
                    log_activity('software update extraction failed: ' . $extract['message']
                        . (!empty($extract['missing']) ? ' Missing: ' . implode(', ', array_slice($extract['missing'], 0, 10)) : '')
                        . (!empty($extract['stale']) ? ' Not replaced: ' . implode(', ', array_slice($extract['stale'], 0, 10)) : '')
                        . (!empty($extract['blocked']) ? ' Cannot be replaced: ' . implode(', ', array_slice($extract['blocked'], 0, 10)) : ''));

                    $response = array(
                        'status' => 'error',
                        'message' => $extract['message']
                    );
                    echo encode_json($response);
                    exit();
                }

                unlink($filename);

                // The bytecode cache still holds the OLD files. Two reasons
                // this has to be dropped here rather than left to the cache's
                // own timestamp check:
                //
                //  • The screen sends the browser to install/index.php as
                //    soon as this returns. Between the new files landing and
                //    the cache noticing them (opcache.revalidate_freq, two
                //    seconds by default) the upgrade would run the PREVIOUS
                //    version's code against the new schema — the exact
                //    window the upgrade bridge exists to survive, entered on
                //    purpose for no reason.
                //  • Where the host turned timestamp validation off
                //    (opcache.validate_timestamps = 0, common on tuned
                //    production boxes) the old code keeps running until
                //    someone restarts PHP. The operator sees "update
                //    complete" and no change whatsoever.
                //
                // Also reclaims the memory the replaced files occupied:
                // every superseded copy stays in the cache as waste until
                // it is invalidated, and this software's largest file is
                // several megabytes of compiled opcodes on its own.
                //
                // Failure is not fatal — purge_cache.php exists for the
                // hosts that refuse the API — but it is worth a log line,
                // because "I updated and nothing changed" starts here.
                // extension_loaded() is not the question, and neither is
                // function_exists(): the extension can be compiled in while
                // opcache.enable is off, in which case the functions all
                // exist, every call returns false and emits a warning. Ask
                // the cache whether it is running.
                //
                // A host that blocks opcache.restrict_api answers nothing at
                // all — status is unreadable there but a reset may still be
                // allowed, so "unknown" tries anyway and stays quiet about
                // the outcome. Only a cache that says it is enabled AND
                // refuses every attempt is worth a log line.
                $pg_opcache_status  = function_exists('opcache_get_status') ? @opcache_get_status(false) : null;
                $pg_opcache_running = is_array($pg_opcache_status) ? !empty($pg_opcache_status['opcache_enabled']) : null;

                if ($pg_opcache_running !== false) {
                    $pg_update_opcache_cleared = false;

                    if (function_exists('opcache_reset')) {
                        $pg_update_opcache_cleared = (bool) @opcache_reset();
                    }

                    // opcache.restrict_api blocks reset() from a script
                    // outside its directory; per-file invalidation is still
                    // allowed on some of those hosts. The paths are the ones
                    // the archive just wrote, so nothing else is walked.
                    if (!$pg_update_opcache_cleared && function_exists('opcache_invalidate')) {
                        $pg_update_files = (isset($extract['files']) && is_array($extract['files'])) ? $extract['files'] : array();
                        foreach ($pg_update_files as $pg_update_file) {
                            if (substr($pg_update_file, -4) === '.php') {
                                if (@opcache_invalidate($pg_update_file, true)) {
                                    $pg_update_opcache_cleared = true;
                                }
                            }
                        }
                    }

                    if (!$pg_update_opcache_cleared && $pg_opcache_running === true) {
                        log_activity('software update: the bytecode cache could not be cleared - run Purge Cache if the update does not take effect');
                    }
                }

                $query = "DELETE FROM notifications WHERE action = 'software_update'";
                $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));

                //there is no error so update complete.
                //return success json output
                $response = array(
                    'status' => 'success',
                    'message' => 'Success. Being redirected for Upgrade.'
                );
                echo encode_json($response);
                exit();

                break;

            default:
                //return error json output
                $response = array(
                    'status' => 'error',
                    'message' => 'Crashed.'
                );
                echo encode_json($response);
                exit();
        }

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
    // the widget can render brand-aware UI ("Bonus / Garanti Bankası — 3
    // taksit ₺X.XX/ay, toplam ₺Y.YY"). Wraps the same SDK call the legacy
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
                'currency_symbol'=> defined('BASE_CURRENCY_SYMBOL') ? BASE_CURRENCY_SYMBOL : '',
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

    case 'backend_search':
        $user = validate_user();

        $search = isset($request['search']) ? trim($request['search']) : '';

        $offset = isset($request['offset']) ? max(0, (int) $request['offset']) : 0;
        $per_limit = $offset + 21; // +1 extra to detect has_more
        $results = array();

        // Role helpers
        $role = (int) $user['role']; // 0=admin,1=designer,2=manager,3=user
        $can_design = ($role <= 1);
        $can_manage = ($role <= 2);
        $can_ecommerce = ($role <= 2) || !empty($user['manage_ecommerce']);
        $can_manage_forms = ($role <= 2) || !empty($user['manage_forms']);
        $can_contacts = ($role <= 2) || !empty($user['manage_contacts']);

        // Build quick actions based on role (used for both empty and typed searches)
        $base_url = PATH . SOFTWARE_DIRECTORY;
        $actions = array();

        // ── Sayfalar / Pages ──────────────────────────────────────────────────
        $actions[] = array('label' => lang('Pages'), 'icon' => 'bi-file-earmark-text', 'url' => $base_url . '/view_pages.php', 'keys' => array('sayfa', 'sayfalar', 'page', 'pages', 'say'));
        $actions[] = array('label' => lang('Add Page'), 'icon' => 'bi-file-earmark-plus', 'url' => $base_url . '/add_page.php', 'keys' => array('sayfa ekle', 'page add', 'yeni sayfa', 'add page', 'sayfaekle'));
        $actions[] = array('label' => lang('File Manager'), 'icon' => 'bi-folder2', 'url' => $base_url . '/view_folders.php', 'keys' => array('klasor', 'klasör', 'folder', 'fol', 'kla', 'dosya', 'yonetici', 'file', 'manager'));
        $actions[] = array('label' => lang('Add Folder'), 'icon' => 'bi-folder-plus', 'url' => $base_url . '/add_folder.php', 'keys' => array('klasor ekle', 'add folder', 'yeni klasor', 'klasorekle'));
        $actions[] = array('label' => lang('Short Links'), 'icon' => 'bi-link-45deg', 'url' => $base_url . '/view_short_links.php', 'keys' => array('kisa link', 'kisa', 'short', 'link', 'kis'));
        $actions[] = array('label' => lang('Comments'), 'icon' => 'bi-chat-dots', 'url' => $base_url . '/view_comments.php', 'keys' => array('yorum', 'comment', 'com', 'yor'));
        $actions[] = array('label' => lang('Auto Dialogs'), 'icon' => 'bi-chat-square-text', 'url' => $base_url . '/view_auto_dialogs.php', 'keys' => array('dialog', 'auto', 'oto', 'diy'));

        // ── Dosyalar / Files ──────────────────────────────────────────────────
        $actions[] = array('label' => lang('Files'), 'icon' => 'bi-folder2-open', 'url' => $base_url . '/view_files.php', 'keys' => array('dosya', 'dosyalar', 'file', 'files', 'fil', 'dos'));
        $actions[] = array('label' => lang('Add File'), 'icon' => 'bi-file-earmark-arrow-up', 'url' => $base_url . '/add_file.php', 'keys' => array('dosya yukle', 'dosya ekle', 'upload', 'add file', 'yukle'));

        // ── e-Ticaret / eCommerce ─────────────────────────────────────────────
        if ($can_ecommerce) {
            $actions[] = array('label' => lang('Orders'), 'icon' => 'bi-receipt', 'url' => $base_url . '/view_orders.php', 'keys' => array('siparis', 'siparisler', 'order', 'orders', 'ord', 'sip'));
            $actions[] = array('label' => lang('Products'), 'icon' => 'bi-box-seam', 'url' => $base_url . '/view_products.php', 'keys' => array('urun', 'urunler', 'product', 'products', 'pro', 'uru'));
            $actions[] = array('label' => lang('Add Product'), 'icon' => 'bi-box-seam', 'url' => $base_url . '/add_product.php', 'keys' => array('urun ekle', 'add product', 'yeni urun', 'urunek'));
            $actions[] = array('label' => lang('Product Groups'), 'icon' => 'bi-boxes', 'url' => $base_url . '/view_product_groups.php', 'keys' => array('urun grubu', 'grup', 'product group', 'group', 'gru'));
            $actions[] = array('label' => lang('Add Product Group'), 'icon' => 'bi-boxes', 'url' => $base_url . '/add_product_group.php', 'keys' => array('grup ekle', 'add group', 'yeni grup', 'grupekle'));
            $actions[] = array('label' => lang('Offers'), 'icon' => 'bi-percent', 'url' => $base_url . '/view_offers.php', 'keys' => array('teklif', 'teklifler', 'indirim', 'offer', 'offers', 'off', 'tek', 'ind'));
            $actions[] = array('label' => lang('Add Offer'), 'icon' => 'bi-percent', 'url' => $base_url . '/add_offer.php', 'keys' => array('teklif ekle', 'add offer', 'indirim ekle', 'teklifekle'));
            $actions[] = array('label' => lang('Gift Cards'), 'icon' => 'bi-gift', 'url' => $base_url . '/view_gift_cards.php', 'keys' => array('hediye', 'gift', 'kart', 'giftcard', 'hed'));
            $actions[] = array('label' => lang('Shipping Methods'), 'icon' => 'bi-truck', 'url' => $base_url . '/view_shipping_methods.php', 'keys' => array('kargo', 'shipping', 'gonder', 'gönderim', 'kar'));
            $actions[] = array('label' => lang('Currencies'), 'icon' => 'bi-currency-exchange', 'url' => $base_url . '/view_currencies.php', 'keys' => array('para', 'doviz', 'döviz', 'currency', 'cur', 'par', 'döv'));
            $actions[] = array('label' => lang('Countries'), 'icon' => 'bi-globe2', 'url' => $base_url . '/view_countries.php', 'keys' => array('ulke', 'ülke', 'country', 'countries', 'ulk'));
            $actions[] = array('label' => lang('Tax Zones'), 'icon' => 'bi-receipt-cutoff', 'url' => $base_url . '/view_tax_zones.php', 'keys' => array('vergi', 'kdv', 'tax', 'ver'));
            $actions[] = array('label' => lang('Zones'), 'icon' => 'bi-map', 'url' => $base_url . '/view_zones.php', 'keys' => array('bolge', 'bölge', 'zone', 'zon', 'böl'));
            $actions[] = array('label' => lang('States'), 'icon' => 'bi-geo-alt', 'url' => $base_url . '/view_states.php', 'keys' => array('sehir', 'şehir', 'eyalet', 'state', 'seh'));
            $actions[] = array('label' => lang('Order Reports'), 'icon' => 'bi-bar-chart', 'url' => $base_url . '/view_order_reports.php', 'keys' => array('siparis rapor', 'order report', 'rapor sip', 'raporord'));
        }

        // ── Visitors ──────────────────────────────────────────────────────────
        $actions[] = array('label' => lang('Visitor Reports'), 'icon' => 'bi-people', 'url' => $base_url . '/view_visitor_reports.php', 'keys' => array('ziyaretci', 'ziyaretçi', 'visitor', 'visit', 'zia', 'rap'));
        $actions[] = array('label' => lang('Visitor Report'), 'icon' => 'bi-graph-up', 'url' => $base_url . '/view_visitor_report.php', 'keys' => array('ziyaret rapor', 'visitor report', 'rapor ziy', 'ziyrapor'));

        // ── Contacts ──────────────────────────────────────────────────────────
        if ($can_contacts) {
            $actions[] = array('label' => lang('Contacts'), 'icon' => 'bi-people', 'url' => $base_url . '/view_contacts.php', 'keys' => array('kisi', 'kişi', 'contact', 'rehber', 'con', 'kis', 'reh'));
            $actions[] = array('label' => lang('Add Contact'), 'icon' => 'bi-person-plus', 'url' => $base_url . '/add_contact.php', 'keys' => array('kisi ekle', 'add contact', 'yeni kisi', 'kisieki'));
            $actions[] = array('label' => lang('Contact Groups'), 'icon' => 'bi-people-fill', 'url' => $base_url . '/view_contact_groups.php', 'keys' => array('grup kisi', 'contact group', 'kisigrup'));
        }

        // ── Users ─────────────────────────────────────────────────────────────
        if ($can_manage) {
            $actions[] = array('label' => lang('Users'), 'icon' => 'bi-person-gear', 'url' => $base_url . '/view_users.php', 'keys' => array('kullanici', 'kullanıcı', 'user', 'usr', 'kul'));
            $actions[] = array('label' => lang('Add User'), 'icon' => 'bi-person-plus', 'url' => $base_url . '/add_user.php', 'keys' => array('kullanici ekle', 'add user', 'yeni kullanici', 'kullanicieki'));
        }

        // ── Campaigns ─────────────────────────────────────────────────────────
        if ($can_manage) {
            $actions[] = array('label' => lang('Email Campaigns'), 'icon' => 'bi-megaphone', 'url' => $base_url . '/view_email_campaigns.php', 'keys' => array('kampanya', 'mail', 'email', 'campaign', 'kamp'));
            $actions[] = array('label' => lang('Add Email Campaign'), 'icon' => 'bi-megaphone', 'url' => $base_url . '/add_email_campaign.php', 'keys' => array('kampanya ekle', 'add campaign', 'kampanyaekle'));
            $actions[] = array('label' => lang('Calendars'), 'icon' => 'bi-calendar3', 'url' => $base_url . '/view_calendars.php', 'keys' => array('takvim', 'calendar', 'tak', 'cal'));
            $actions[] = array('label' => lang('Submitted Forms'), 'icon' => 'bi-ui-checks', 'url' => $base_url . '/view_submitted_forms.php', 'keys' => array('form', 'gonderilen', 'submitted', 'frm', 'gon'));
            $actions[] = array('label' => lang('Menus'), 'icon' => 'bi-menu-button', 'url' => $base_url . '/view_menus.php', 'keys' => array('menu', 'men'));
            $actions[] = array('label' => lang('Ads'), 'icon' => 'bi-badge-ad', 'url' => $base_url . '/view_ads.php', 'keys' => array('reklam', 'ad', 'ads', 'rek'));

            // Every settings section, from the one list the hub and the sidebar
            // of the dialog draw from (includes/settings/registry.php).
            // Registering them is what answers "where is that setting": the
            // operator types the thing itself (ssl, waf, cron, kargo) and the
            // settings open on the card holding it instead of on a long page.
            // The keywords are the field names of the section, not just its
            // title, because nobody searches for "Feature Options" when they
            // are looking for the cart.
            if (!defined('PG_SETTINGS_MENU')) {
                define('PG_SETTINGS_MENU', true);
            }

            include_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');

            // Plain "Settings" opens on the category last used; the eight
            // below each name one.
            $actions[] = array('label' => lang('Settings'), 'icon' => 'bi-gear', 'url' => $base_url . '/' . pg_settings_link(), 'keys' => array('ayar', 'ayarlar', 'setting', 'settings', 'set', 'aya'));

            foreach (pg_settings_categories() as $settings_key => $settings_category) {

                $actions[] = array(
                    'label' => lang('Settings') . ' - ' . $settings_category['label'],
                    'icon'  => $settings_category['icon'],
                    'url'   => $base_url . '/' . pg_settings_url($settings_key),
                    'keys'  => array_merge(
                        array(mb_strtolower($settings_category['label'], 'UTF-8')),
                        call_user_func_array('array_merge', array_values($settings_category['keywords']))));

                foreach ($settings_category['sections'] as $settings_section_id => $settings_section_label) {

                    $actions[] = array(
                        'label' => lang('Settings') . ' - ' . $settings_section_label,
                        'icon'  => $settings_category['icon'],
                        'url'   => $base_url . '/' . pg_settings_url($settings_key) . '#' . $settings_section_id,
                        'keys'  => isset($settings_category['keywords'][$settings_section_id])
                            ? $settings_category['keywords'][$settings_section_id]
                            : array());
                }
            }
            $actions[] = array('label' => lang('Log'), 'icon' => 'bi-journal-text', 'url' => $base_url . '/view_log.php', 'keys' => array('log', 'kayit', 'journal', 'akt'));
            $actions[] = array('label' => lang('Backups'), 'icon' => 'bi-database', 'url' => $base_url . '/backups.php', 'keys' => array('yedek', 'backup', 'bak', 'yed'));
            $actions[] = array('label' => lang('SMTP Settings'), 'icon' => 'bi-envelope-at', 'url' => $base_url . '/smtp_settings.php', 'keys' => array('smtp', 'mail ayar', 'email ayar', 'smtpayar'));
        }

        // ── Design ────────────────────────────────────────────────────────────
        if ($can_design) {
            $actions[] = array('label' => lang('Styles'), 'icon' => 'bi-window', 'url' => $base_url . '/view_styles.php', 'keys' => array('stil', 'stiller', 'style', 'styles', 'stl'));
            $actions[] = array('label' => lang('Add Style'), 'icon' => 'bi-window-plus', 'url' => $base_url . '/add_style.php', 'keys' => array('stil ekle', 'add style', 'yeni stil', 'stilekle'));
            $actions[] = array('label' => lang('Themes'), 'icon' => 'bi-palette', 'url' => $base_url . '/view_themes.php', 'keys' => array('tema', 'theme', 'them', 'tem'));
            $actions[] = array('label' => lang('Design Files'), 'icon' => 'bi-filetype-css', 'url' => $base_url . '/view_design_files.php', 'keys' => array('tasarim dosya', 'design file', 'css', 'js', 'des', 'tas'));
            $actions[] = array('label' => lang('Common Regions'), 'icon' => 'bi-columns-gap', 'url' => $base_url . '/view_regions.php?filter=all_common_regions', 'keys' => array('ortak bolge', 'common region', 'region', 'reg', 'ort', 'common', 'bol'));
            $actions[] = array('label' => lang('Login Regions'), 'icon' => 'bi-shield-lock', 'url' => $base_url . '/view_regions.php?filter=all_login_regions', 'keys' => array('giris bolge', 'login region', 'logi', 'gir'));
            $actions[] = array('label' => lang('Designer Regions'), 'icon' => 'bi-code-square', 'url' => $base_url . '/view_regions.php?filter=all_designer_regions', 'keys' => array('tasarim bolge', 'designer region', 'desi'));
            $actions[] = array('label' => lang('Dynamic Regions'), 'icon' => 'bi-arrow-repeat', 'url' => $base_url . '/view_regions.php?filter=all_dynamic_regions', 'keys' => array('dinamik bolge', 'dynamic region', 'dyna', 'din'));
            $actions[] = array('label' => lang('Find & Replace'), 'icon' => 'bi-search', 'url' => $base_url . '/find_and_replace.php', 'keys' => array('bul degistir', 'find replace', 'degistir', 'bul'));
        }

        // Empty search: return only quick actions (no DB query needed)
        if (strlen($search) < 1) {
            echo encode_json(array('status' => 'success', 'results' => array(), 'actions' => $actions, 'has_more' => false));
            exit();
        }

        $s = escape('%' . $search . '%');

        // Relevance score: exact=100, starts-with=60, contains=30, secondary=15
        $score_fn = function ($name, $secondary = '') use ($search) {
            $n = mb_strtolower((string) ($name ?? ''));
            $q = mb_strtolower($search);
            $sc = mb_strtolower((string) ($secondary ?? ''));
            $score = 0;
            if ($n === $q)
                $score += 100;
            elseif (mb_strpos($n, $q) === 0)
                $score += 60;
            elseif (mb_strpos($n, $q) !== false)
                $score += 30;
            if ($sc !== '' && mb_strpos($sc, $q) !== false)
                $score += 15;
            return $score;
        };

        $add = function ($type, $id, $name, $sub, $score, $extra = array ()) use (&$results) {
            $results[] = array_merge(array(
                'type' => $type,
                'id' => $id,
                'name' => $name,
                'sub' => $sub,
                'score' => $score
            ), $extra);
        };

        // ── Pages (all authenticated users) ──────────────────────────────────
        $rows = db_items(
            "SELECT page_id AS id, page_name AS name, page_type AS type
             FROM page
             WHERE page_name LIKE '$s'
             LIMIT $per_limit"
        );
        foreach ($rows as $r) {
            $add('page', $r['id'], $r['name'], $r['type'], $score_fn($r['name']));
        }

        // ── Files (all authenticated users) ──────────────────────────────────
        $rows = db_items(
            "SELECT id, name, folder AS folder_id, design
             FROM files
             WHERE name LIKE '$s'
             LIMIT $per_limit"
        );
        foreach ($rows as $r) {
            // Design files restricted to designers+
            if ($r['design'] && !$can_design)
                continue;
            $add(
                'file',
                $r['id'],
                $r['name'],
                '',
                $score_fn($r['name']),
                array('folder_id' => (int) $r['folder_id'], 'design' => (bool) $r['design'])
            );
        }

        // ── Menus (manager+) ─────────────────────────────────────────────────
        if ($can_manage) {
            $rows = db_items(
                "SELECT id, name
                 FROM menus
                 WHERE name LIKE '$s'
                 LIMIT $per_limit"
            );
            foreach ($rows as $r) {
                $add('menu', $r['id'], $r['name'], '', $score_fn($r['name']));
            }
        }

        // ── Calendars (manager+) ──────────────────────────────────────────────
        if ($can_manage) {
            $rows = db_items(
                "SELECT id, name
                 FROM calendars
                 WHERE name LIKE '$s'
                 LIMIT $per_limit"
            );
            foreach ($rows as $r) {
                $add('calendar', $r['id'], $r['name'], '', $score_fn($r['name']));
            }
        }

        // ── Email campaign profiles (manager+) ────────────────────────────────
        if ($can_manage) {
            $rows = db_items(
                "SELECT id, name
                 FROM email_campaign_profiles
                 WHERE name LIKE '$s'
                 LIMIT $per_limit"
            );
            foreach ($rows as $r) {
                $add('email_campaign', $r['id'], $r['name'], '', $score_fn($r['name']));
            }
        }

        // ── Forms (manager+ or manage_forms) ──────────────────────────────────
        if ($can_manage_forms) {
            $rows = db_items(
                "SELECT page_id AS id, page_name AS name
                 FROM page
                 WHERE " . pg_form_page_sql('page') . "
                   AND page_name LIKE '$s'
                 LIMIT $per_limit"
            );
            foreach ($rows as $r) {
                $add('form', $r['id'], $r['name'], '', $score_fn($r['name']));
            }
        }

        // ── Users (manager+) ─────────────────────────────────────────────────
        if ($can_manage) {
            $rows = db_items(
                "SELECT user_id AS id,
                        user_username AS name,
                        user_email AS sub
                 FROM user
                 WHERE (user_username LIKE '$s'
                    OR user_email LIKE '$s')
                 LIMIT $per_limit"
            );
            foreach ($rows as $r) {
                $add('user', $r['id'], $r['name'], $r['sub'], $score_fn($r['name'], $r['sub']));
            }
        }

        // ── E-commerce (manager+ or manage_ecommerce) ────────────────────────
        if ($can_ecommerce) {
            // Products
            $rows = db_items(
                "SELECT id, name, short_description, image_name
                 FROM products
                 WHERE (name LIKE '$s' OR short_description LIKE '$s')
                 LIMIT $per_limit"
            );
            foreach ($rows as $r) {
                $add(
                    'product',
                    $r['id'],
                    $r['name'],
                    $r['short_description'],
                    $score_fn($r['name'], $r['short_description']),
                    array('image' => $r['image_name'] ?: null)
                );
            }

            // Product groups
            $rows = db_items(
                "SELECT id, name
                 FROM product_groups
                 WHERE name LIKE '$s'
                 LIMIT $per_limit"
            );
            foreach ($rows as $r) {
                $add('product_group', $r['id'], $r['name'], '', $score_fn($r['name']));
            }

            // Offers
            $rows = db_items(
                "SELECT id, code, description
                 FROM offers
                 WHERE (code LIKE '$s' OR description LIKE '$s')
                 LIMIT $per_limit"
            );
            foreach ($rows as $r) {
                $add(
                    'offer',
                    $r['id'],
                    $r['code'],
                    $r['description'],
                    $score_fn($r['code'], $r['description'])
                );
            }

            // Orders — search by order_number, customer name, email
            $s_order_num = escape('%' . ltrim($search, '#') . '%');
            $rows = db_items(
                "SELECT
                    orders.id,
                    orders.order_number,
                    TRIM(CONCAT(COALESCE(contacts.first_name,''), ' ', COALESCE(contacts.last_name,''))) AS customer
                 FROM orders
                 LEFT JOIN contacts ON orders.contact_id = contacts.id
                 WHERE orders.status != 'incomplete'
                   AND (orders.order_number LIKE '$s_order_num'
                    OR contacts.first_name LIKE '$s'
                    OR contacts.last_name  LIKE '$s'
                    OR contacts.email_address LIKE '$s')
                 ORDER BY orders.order_date DESC
                 LIMIT $per_limit"
            );
            foreach ($rows as $r) {
                $add(
                    'order',
                    $r['id'],
                    '#' . $r['order_number'],
                    $r['customer'],
                    $score_fn($r['order_number'], $r['customer'])
                );
            }
        }

        // ── Design: styles + design files (designer+) ────────────────────────
        if ($can_design) {
            $rows = db_items(
                "SELECT style_id AS id, style_name AS name
                 FROM style
                 WHERE style_name LIKE '$s'
                 LIMIT $per_limit"
            );
            foreach ($rows as $r) {
                $add('style', $r['id'], $r['name'], '', $score_fn($r['name']));
            }
        }

        // ── Contacts / Rehber (manager+ or manage_contacts) ──────────────────
        if ($can_contacts) {
            $rows = db_items(
                "SELECT c.id,
                        TRIM(CONCAT(c.first_name, ' ', c.last_name)) AS name,
                        COALESCE(NULLIF(c.email_address,''), NULLIF(c.company,'')) AS sub,
                        COALESCE(NULLIF(f.name,''), NULLIF(c.image,'')) AS image
                 FROM contacts c
                 LEFT JOIN files f ON f.id = c.file_id AND c.file_id > 0
                 WHERE (c.first_name LIKE '$s'
                    OR c.last_name LIKE '$s'
                    OR c.email_address LIKE '$s'
                    OR c.company LIKE '$s')
                 LIMIT $per_limit"
            );
            foreach ($rows as $r) {
                $name = $r['name'] ?: lang('Unknown');
                $add(
                    'contact',
                    $r['id'],
                    $name,
                    $r['sub'],
                    $score_fn($r['name'], $r['sub']),
                    array('image' => $r['image'] ?: null)
                );
            }
        }

        // ── Regions (designer+) ───────────────────────────────────────────────
        if ($can_design) {
            $rows = db_items(
                "SELECT cregion_id AS id, cregion_name AS name
                 FROM cregion
                 WHERE cregion_designer_type = 'no'
                   AND cregion_name LIKE '$s'
                 LIMIT $per_limit"
            );
            foreach ($rows as $r) {
                $add('common_region', $r['id'], $r['name'], '', $score_fn($r['name']));
            }

            $rows = db_items(
                "SELECT cregion_id AS id, cregion_name AS name
                 FROM cregion
                 WHERE cregion_designer_type = 'yes'
                   AND cregion_name LIKE '$s'
                 LIMIT $per_limit"
            );
            foreach ($rows as $r) {
                $add('design_region', $r['id'], $r['name'], '', $score_fn($r['name']));
            }

            $rows = db_items(
                "SELECT id, name
                 FROM login_regions
                 WHERE name LIKE '$s'
                 LIMIT $per_limit"
            );
            foreach ($rows as $r) {
                $add('login_region', $r['id'], $r['name'], '', $score_fn($r['name']));
            }
        }

        // ── Short links (manager+) ────────────────────────────────────────────
        if ($can_manage) {
            $rows = db_items(
                "SELECT id, name, destination_type
                 FROM short_links
                 WHERE name LIKE '$s'
                 LIMIT $per_limit"
            );
            foreach ($rows as $r) {
                $add('short_link', $r['id'], $r['name'], $r['destination_type'], $score_fn($r['name']));
            }
        }

        // Sort by score desc, paginate
        usort($results, function ($a, $b) {
            return $b['score'] - $a['score'];
        });
        $has_more = count($results) > $offset + 20;
        $results = array_slice($results, $offset, 20);

        echo encode_json(array('status' => 'success', 'results' => $results, 'actions' => $actions, 'has_more' => $has_more));
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
        $product['price_formatted'] = $_pg_curr . number_format($_pg_price_dec, 2, '.', ',');
        $product['original_price']           = number_format($_pg_orig_dec, 2, '.', '');
        $product['original_price_cents']     = $_pg_orig_cents;
        $product['original_price_formatted'] = $_pg_curr . number_format($_pg_orig_dec, 2, '.', ',');
        $product['has_discount']             = (string)$_pg_has_disc;
        $product['discount_amount']          = $_pg_save_cents > 0 ? number_format($_pg_save_cents / 100, 2, '.', '') : '';
        $product['discount_amount_formatted']= $_pg_save_cents > 0 ? $_pg_curr . number_format($_pg_save_cents / 100, 2, '.', ',') : '';
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
        validate_token();

        $_SESSION['software']['toolbar_enabled'] = $request['enabled'];

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


    case 'file_explorer':
        $user = validate_user();
        validate_token();

        // The catalog rides this same action but is not part of the folder
        // tree, so it is gated on commerce rights instead of folder edit
        // rights.  Asking a basic user for folder rights here would turn away
        // exactly the person "manage all commerce" was granted to, at the door
        // of a store the menu had just offered them.  Every explorer_catalog_*
        // sub-action re-checks the same rule for itself in
        // view_folder_and_files_f.php; this only keeps the shared preamble
        // from answering first.
        $explorer_catalog_request = (strpos((string) ($request['type'] ?? ''), 'explorer_catalog_') === 0);

        if ($explorer_catalog_request == true) {
            if (($user['role'] > 2) && ($user['manage_ecommerce'] != true)) {
                log_activity(lang('access denied to commerce'), $_SESSION['sessionusername']);
                respond(array(
                    'status' => 'error',
                    'request' => (string) ($request['type'] ?? ''),
                    'message' => lang('Access denied')));
            }
        } else {
            validate_area_access($user, 'user');
        }


        if (isset($request['folder_id']) && ($_SESSION['software']['explorer']['folder']['folder_id'] ?? '') != $request['folder_id']) {
            $_SESSION['software']['explorer']['folder']['folder_id'] = $request['folder_id'];
        }

        $folder_id = ($_SESSION['software']['explorer']['folder']['folder_id'] ?? '');
        if (!isset($folder_id)) {
            $folder_id = db("SELECT folder_id FROM folder WHERE folder.folder_parent = '0'");
        }


        if (isset($request['view_type']) && ($_SESSION['software']['explorer']['folder']['view_type'] ?? '') != $request['view_type']) {
            $_SESSION['software']['explorer']['folder']['view_type'] = $request['view_type'];
        }

        $folder_table_view_type = ($_SESSION['software']['explorer']['folder']['view_type'] ?? '');

        if (!isset($folder_table_view_type)) {
            $folder_table_view_type = 'list';
        }

        // A catalog request never carries a folder, so the folder the session
        // happens to have open is none of its business.
        if (($explorer_catalog_request == false) && (check_view_access($folder_id) == false)) {
            $response = array(
                'status' => 'error',
                'request' => $request['type'],
                'message' => lang('Access denied'),
            );
            echo encode_json($response);
            exit();
        }

        $folders_that_user_has_access_to = array();
        // prepare expanded folders array from cookie
        $expanded_folders = isset($_COOKIE['software']['view_folders']['expanded_folders']) ? explode(',', $_COOKIE['software']['view_folders']['expanded_folders']) : array();

        // if user is a basic user, then get folders that user has access to
        if ($user['role'] == 3) {
            $folders_that_user_has_access_to = get_folders_that_user_has_access_to($user['id']);
        }

        switch ($request['type']) {

            // Combined folder/page/file explorer (view_folder_and_files.php).
            // These sub-actions return structured JSON and live in their own
            // include; pg_explorer_handle() responds and exits.
            case 'explorer_list':
            case 'explorer_tree':
            case 'explorer_create_folder':
            case 'explorer_create_file':
            case 'explorer_rename':
            case 'explorer_move':
            case 'explorer_paste':
            case 'explorer_delete_files':
            case 'explorer_upload':
            case 'explorer_folder_options':
            case 'explorer_folder_access_get':
            case 'explorer_folder_access_set':
            case 'explorer_delete_check':
            case 'explorer_recycle_delete':
            case 'explorer_recycle_restore':
            case 'explorer_hard_delete':
            case 'explorer_optimize':
            case 'explorer_webp':
            case 'explorer_folder_settings_get':
            case 'explorer_folder_settings_set':
            case 'explorer_bulk_page_options':
            case 'explorer_pages_bulk_edit':
            case 'explorer_bulk_file_options':
            case 'explorer_files_bulk_edit':
            case 'explorer_shared_list':
            case 'explorer_files_design':
            case 'explorer_file_get':
            case 'explorer_file_usage':
            case 'explorer_file_save':
            case 'explorer_rotate':
            case 'explorer_backups_list':
            case 'explorer_backup_zip':
            case 'explorer_backup_rename':
            case 'explorer_backup_copy':
            case 'explorer_backup_delete':
            case 'explorer_backup_upload':
            case 'explorer_backup_extract':
            case 'explorer_backup_chmod':
            case 'explorer_zip_create':
            case 'explorer_zip_extract':
            case 'explorer_short_links_list':
            case 'explorer_short_link_options':
            case 'explorer_short_link_create':
            case 'explorer_short_link_rename':
            case 'explorer_short_link_update':
            case 'explorer_short_link_duplicate':
            case 'explorer_short_link_delete':
            case 'explorer_catalog_list':
            case 'explorer_catalog_tree':
            case 'explorer_catalog_pages':
            case 'explorer_catalog_recycle':
            case 'explorer_catalog_restore':
            case 'explorer_catalog_purge':
            case 'explorer_catalog_enable':
            case 'explorer_bulk_product_options':
            case 'explorer_products_bulk_edit':
            case 'explorer_catalog_quick_edit':
            case 'explorer_catalog_access_get':
            case 'explorer_catalog_access_set':
            case 'explorer_catalog_membership_remove':
            case 'explorer_catalog_create_group':
            case 'explorer_catalog_rename':
            case 'explorer_catalog_paste':
                require_once(dirname(__FILE__) . '/view_folder_and_files_f.php');
                pg_explorer_handle($request, $user, $folders_that_user_has_access_to);
                break;
            case 'delete_file':
                $query =
                    "SELECT 
                    files.id,
                    files.name,
                    files.folder,
                    files.description,
                    files.type,
                    files.size,
                    files.design,
                    files.optimized,
                    folder.folder_archived
                FROM files 
                LEFT JOIN folder ON files.folder = folder.folder_id
                WHERE files.id = '" . escape($request['file_id']) . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                $row = mysqli_fetch_array($result);

                $file_id = $row['id'];
                $file_design = $row['design'];
                $file_folder = $row['folder'];
                $file_name = $row['name'];

                // if the user does not have edit rights to this file's folder,
                // or this file is a design file and the user is not a designer or administrator,
                // response error
                if (
                    (check_edit_access($file_folder) == false)
                    ||
                    (
                        ($file_design == 1)
                        && ($user['role'] > 1)
                    )
                ) {
                    $response = array(
                        'status' => 'error',
                        'request' => $request['type'],
                        'message' => lang('Access denied'),
                    );
                    echo encode_json($response);
                    exit();
                }

                $result = mysqli_query(db::$con, "DELETE FROM files WHERE id = '" . escape($file_id) . "'") or output_error('Query failed');
                // delete file's system css properties in case any exist
                $query = "DELETE FROM system_theme_css_rules WHERE file_id = '" . escape($file_id) . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                db("DELETE FROM preview_styles WHERE theme_id = '" . escape($file_id) . "'");

                // Delete file on file system.
                @unlink(FILE_DIRECTORY_PATH . '/' . $file_name);

                log_activity(lang(array('string' => 'file ({var:1}) was deleted', 'vars' => $file_name)), $_SESSION['sessionusername']);

                $response = array(
                    'status' => 'success',
                    'request' => $request['type'],
                    'deleted_file_id' => $file_id,
                );

                echo encode_json($response);
                exit();
                break;

            case 'get_folder_id':
                $response = array(
                    'status' => 'success',
                    'request' => $request['type'],
                    'folder_id' => $folder_id,
                );
                echo encode_json($response);
                exit();
                break;

            case 'get_breadcrumb':
                function get_folder_breadcrumb($parent_folder_id)
                {
                    global $user;
                    global $folders_that_user_has_access_to;
                    $output_parent_folder_name = '';

                    $current_folder_name = db("SELECT folder_name FROM folder WHERE folder.folder_id = '" . escape($parent_folder_id) . "'");
                    if (db("SELECT folder_level FROM folder WHERE folder.folder_id = '" . escape($parent_folder_id) . "'") > 0) {
                        $parent_id = $parent_folder_id;
                        for (
                            $current_folder_level = db("SELECT folder_level FROM folder WHERE folder.folder_id = '" . escape($parent_folder_id) . "'");
                            $current_folder_level >= 0;
                            $current_folder_level--
                        ) {
                            $parent_id = db("SELECT folder_parent FROM folder WHERE folder.folder_id = '" . escape($parent_id) . "'");
                            $parent_folder_name = db("SELECT folder_name FROM folder WHERE folder.folder_id = '" . escape($parent_id) . "'");
                            if ($parent_folder_name) {
                                $output_parent_folder_name = '<li class="breadcrumb-item"><a class="text-body-secondary text-decoration-none btn btn-sm btn-link py-0" href="#!" onclick="get_file_explorer({folder_id:\'' . $parent_id . '\'});">' . $parent_folder_name . '</a></li>' . $output_parent_folder_name;
                            }

                        }

                    }

                    return
                        '<nav class="overflow-auto" style="--bs-border-opacity: 0.05;--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'8\' height=\'8\'%3E%3Cpath d=\'M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z\' fill=\'%236c757d\'/%3E%3C/svg%3E&#34;);">
                        <ol class="breadcrumb mb-0">
                            ' . $output_parent_folder_name . '
                            <li class="breadcrumb-item active text-body" aria-current="page">' . $current_folder_name . '</li>
                        </ol>
                    </nav>';
                }

                $response = array(
                    'status' => 'success',
                    'request' => $request['type'],
                    'content' => get_folder_breadcrumb($folder_id),
                );

                echo encode_json($response);
                exit();
                break;


            case 'get_tables':

                function get_folder_table($parent_folder_id, $folder_table_view_type)
                {
                    global $user;
                    global $folders_that_user_has_access_to;
                    function get_access_control_icon_classes($access_control_type)
                    {
                        switch ($access_control_type) {
                            case 'public':
                                $output = ' bi-people-fill public ';
                                break;
                            case 'guest':
                                $output = ' bi-incognito guest ';
                                break;
                            case 'registration':
                                $output = ' bi-person-fill registration ';
                                break;
                            case 'membership':
                                $output = ' bi-person-vcard-fill membership ';
                                break;
                            case 'private':
                                $output = ' bi-lock-fill private ';
                                break;
                        }
                        return $output;
                    }

                    function get_file_icon($file_type)
                    {
                        $file_class = ' bi-file-earmark ';

                        switch (mb_strtolower($file_type)) {
                            case 'css':
                                $file_class = ' bi-filetype-css ';
                                break;
                            case 'js':
                                $file_class = ' bi-filetype-js ';
                                break;
                            case 'jpg':
                            case 'jpeg':
                            case 'png':
                            case 'gif':
                            case 'svg':
                            case 'webp':
                                $file_class = ' bi-file-earmark-image ';
                                break;
                            case 'pdf':
                                $file_class = ' bi-file-earmark-pdf ';
                                break;
                            case 'zip':
                                $file_class = ' bi-file-earmark-zip ';
                                break;
                            case 'mp4':
                                $file_class = ' bi-file-earmark-play ';
                                break;
                            case 'mp3':
                                $file_class = ' bi-file-earmark-music ';
                                break;
                        }

                        return $file_class;
                    }



                    if (!isset($parent_folder_id)) {
                        $parent_folder_id = db("SELECT folder_id FROM folder WHERE folder.folder_level = '0'");
                    }

                    // get styles
                    $query = "SELECT style_id, style_name FROM style";
                    $style_result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                    // get folders
                    $query = "SELECT
                                folder.folder_id,
                                folder.folder_name,
                                folder.folder_level,
                                folder.folder_style,
                                folder.folder_archived,
                                folder.folder_user,
                                style.style_id,
                                style.style_name,
                                user.user_username as user_username
                             FROM folder
                             LEFT JOIN style ON folder.folder_style = style.style_id
                             LEFT JOIN user ON folder.folder_user = user.user_id
                             WHERE folder.folder_parent = '" . escape($parent_folder_id) . "'
                             ORDER BY folder.folder_order, folder.folder_name";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    $output = '';

                    while ($folder = mysqli_fetch_assoc($result)) {
                        // if user has access to folder
                        if (check_folder_access_in_array($folder['folder_id'], $folders_that_user_has_access_to) == true) {
                            $folder_access = true;
                        } else {
                            $folder_access = false;
                        }

                        // if user has access to folder
                        if ($folder_access == true) {
                            $access_control_type = get_access_control_type($folder['folder_id']);
                            $style = '';
                            // If the folder style is not set to zero then set the page style name.
                            if ($folder['folder_style'] != '0') {
                                $style = '<span class="fs-5 bi bi-palette" title="' . lang('Style') . ': ' . h($folder['style_name']) . '"></span>';
                            }

                            $folder_archived = '';
                            if ($folder['folder_archived'] == '1') {
                                $folder_archived = '<span class="fs-5 bi bi-archive" title="' . lang('Archived') . '"></span>';
                            }

                            $folder_user = '';
                            if ($folder['user_username'] != NULL) {
                                $folder_user = h($folder['user_username']);
                            }

                            $parent_folder_pages_size = 0;
                            $parent_folder_file_size = 0;

                            //get size of parent folder or this folder
                            $parent_folder_query = "SELECT
                            folder_id
                            FROM folder
                            WHERE folder.folder_parent = '" . e($folder["folder_id"]) . "' OR folder.folder_id = '" . e($folder["folder_id"]) . "'";
                            $parent_folder_result = mysqli_query(db::$con, $parent_folder_query) or output_error('Query failed.');
                            while ($parent_folder = mysqli_fetch_assoc($parent_folder_result)) {


                                //get all page sizes from this and parent folders.
                                $parent_folder_page_query = "SELECT
                                page_id
                                FROM page
                                LEFT JOIN folder ON page.page_folder = folder.folder_id
                                WHERE folder.folder_id = '" . e($parent_folder["folder_id"]) . "'";
                                $parent_folder_page_result = mysqli_query(db::$con, $parent_folder_page_query) or output_error('Query failed.');

                                while ($parent_folder_page_rows = mysqli_fetch_assoc($parent_folder_page_result)) {
                                    $parent_folder_pages_size = $parent_folder_pages_size + db("SELECT sum(char_length(pregion_content)) FROM pregion WHERE pregion_page = '" . e($parent_folder_page_rows["page_id"]) . "'");
                                }

                                //get all files sizes from this and parent folders.
                                $parent_folder_file_query = "SELECT
                                size
                                FROM files
                                LEFT JOIN folder ON files.folder = folder.folder_id
                                WHERE files.folder = '" . e($parent_folder["folder_id"]) . "'";
                                $parent_folder_file_result = mysqli_query(db::$con, $parent_folder_file_query) or output_error('Query failed.');

                                while ($parent_folder_file_rows = mysqli_fetch_assoc($parent_folder_file_result)) {
                                    $parent_folder_file_size = $parent_folder_file_size + $parent_folder_file_rows["size"];
                                }

                            }

                            //Page size from pregions.
                            $size = '';
                            if ($parent_folder_pages_size > 0 || $parent_folder_file_size > 0) {
                                $size = h(convert_bytes_to_string($parent_folder_pages_size + $parent_folder_file_size));
                            }

                            if (isset($folder_table_view_type) && $folder_table_view_type == 'grid') {
                                // output folder as grid
                                $output .=
                                    '<div class="col-6 col-sm-4 col-md-3 col-lg-3 col-xl-2 col-xxl-2">
                                        <div style="min-height:130px;" class="card h-100 hoverable border-0 bg-transparent shadow-none pointer user-select-none " folder_id="' . $folder['folder_id'] . '"  onclick="get_file_explorer({folder_id:\'' . $folder['folder_id'] . '\'});">
                                            <div class="card-header border-0 bg-transparent p-1 d-flex">
                                                <input class="d-none form-check-input show-on-hovered" type="checkbox" name="folders[]" value="' . $folder['folder_id'] . '" class="checkbox" />
                                            </div>
                                            <div class="card-body text-center position-relative overflow-hidden p-0">
                                                <div class="text-center position-relative">
                                                    <i class="bi display-3 bi-folder ' . $access_control_type . '"></i>
                                                    <i class="bi fs-5 position-absolute top-50 start-50 translate-middle' . get_access_control_icon_classes($access_control_type) . ' "></i>

                                                </div>
                                                <div class="d-none">' . h($style) . '</div>
                                                <div class="d-none">' . $access_control_type . '</div>
                                                <div class="d-none">' . $folder_archived . '</div>
                                            </div>
                                            <div class="card-footer border-0 p-1 text-center bg-transparent">
                                                <div class="text-truncate">' . h($folder['folder_name']) . '</div>
                                            </div>
                                        </div>
                                    </div>';

                            } else {
                                // output folder as table
                                $output .=
                                    '<tr type="folder" folder_id="' . $folder['folder_id'] . '" class="unselectable pointer " onclick="get_file_explorer({folder_id:\'' . $folder['folder_id'] . '\'});">' .
                                    '<td class="position-relative"></td>' .
                                    '<td class="d-none select-all align-middle text-start"><input class="form-check-input " type="checkbox" name="folders[]" value="' . $folder['folder_id'] . '" class="checkbox" /></td>' .
                                    '<td class="position-relative">
                                            <span class="fs-5 bi bi-folder position-relative overflow-hidden ' . $access_control_type . '" title="' . lang(ucwords($access_control_type)) . '">
                                                <span style="font-size:40%" class="bi position-absolute start-50 top-50 translate-middle' . get_access_control_icon_classes($access_control_type) . ' "></span>
                                            </span>
                                            ' . $folder_archived . '
                                            ' . $style . '
                                        </td>' .
                                    '<td >' . h($folder['folder_name']) . '</td>' .
                                    '<td >' . $size . '</td>' .
                                    '<td>' . $folder_user . '</td>
                                    </tr>';
                            }
                        }
                    }



                    // if user has access to folder
                    if (check_folder_access_in_array($parent_folder_id, $folders_that_user_has_access_to) == true) {
                        // get pages
                        $query = "SELECT
                                    page.page_id,
                                    page.page_name,
                                    page.page_folder,
                                    page.page_style,
                                    page.page_home,
                                    page.page_type,
                                    page.page_user,
                                    style.style_id,
                                    style.style_name,
                                    folder.folder_archived,
                                    folder.folder_id,
                                    user.user_username as user_username
                                 FROM page
                                 LEFT JOIN style ON page.page_style = style.style_id
                                 LEFT JOIN folder ON page.page_folder = folder.folder_id
                                 LEFT JOIN user ON page.page_user = user.user_id
                                 WHERE page.page_folder = '" . escape($parent_folder_id) . "'
                                 ORDER BY page.page_name";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                        $access_control_type = '';
                        while ($page = mysqli_fetch_assoc($result)) {

                            $access_control_type = get_access_control_type($page['folder_id']);

                            $style = '';
                            // If the folder style is not set to zero then set the page style name.
                            if ($page['page_style'] != '0') {
                                $style = '<span class="fs-5 bi bi-palette" title="' . lang('Style') . ': ' . h($page['style_name']) . '"></span>';
                            }

                            //Check if page is homepage, if its output icon.
                            $home = '';
                            if ($page['page_home'] == 'yes') {
                                $home = '<span class="fs-5 bi bi-house" title="' . lang('Homepage') . '"></span>';
                            }

                            //Check if page is in archived folder.
                            $folder_archived = '';
                            if ($page['folder_archived'] == '1') {
                                $folder_archived = '<span class="fs-5 bi bi-archive" title="' . lang('Archived') . '"></span>';
                            }

                            //Page size from pregions.
                            $size = '';
                            if (db("SELECT sum(char_length(pregion_content)) FROM pregion WHERE pregion_page = '" . e($page["page_id"]) . "'") > 0) {
                                $size = h(convert_bytes_to_string(db("SELECT sum(char_length(pregion_content)) FROM pregion WHERE pregion_page = '" . e($page["page_id"]) . "'")));
                            }

                            //Modifier user.
                            $page_user = '';
                            if ($page['user_username'] != NULL) {
                                $page_user = h($page['user_username']);
                            }


                            if (isset($folder_table_view_type) && $folder_table_view_type == 'grid') {
                                // output page as grid
                                $output .=
                                    '<div type="page" page_id="' . $page['page_id'] . '" class="col-6 col-sm-4 col-md-3 col-lg-3 col-xl-2 col-xxl-2 pointer custom-contextmenu explorer-contextmenu" onclick="preview_page({page_id:\'' . $page['page_id'] . '\',page_name:\'' . h($page['page_name']) . '\'})">
                                        <div style="min-height:130px;" class="card h-100 hoverable border-0 bg-transparent shadow-none" >
                                           <div class="card-header border-0 bg-transparent p-1 d-flex">
                                                <input class="d-none form-check-input show-on-hovered" type="checkbox" name="pages[]" value="' . $page['page_id'] . '" class="checkbox" />
                                            </div>
                                            <div class="card-body text-center position-relative overflow-hidden p-0">
                                                <div class="text-center position-relative">
                                                    <i class="bi display-3 bi-window-fullscreen ' . $access_control_type . '"></i>
                                                    <i style="top:58%;" class="bi fs-5 position-absolute start-50 translate-middle' . get_access_control_icon_classes($access_control_type) . ' "></i>
                                                </div>
                                                <div class="d-none">' . h($style) . '</div>
                                                <div class="d-none">' . $home . '</div>
                                                <div class="d-none">' . h($page['page_type']) . '</div>
                                                <div class="d-none">' . $access_control_type . '</div>
                                                <div class="d-none">' . $folder_archived . '</div>
                                            </div>
                                            <div class="card-footer border-0 p-1 text-center bg-transparent">
                                                <div class="text-truncate">' . h($page['page_name']) . '</div>
                                            </div>
                                        </div>
                                    </div>';

                            } else {
                                // output page as table
                                $output .=
                                    '<tr type="page" page_id="' . $page['page_id'] . '" class="unselectable pointer custom-contextmenu explorer-contextmenu" onclick="preview_page({page_id:\'' . $page['page_id'] . '\',page_name:\'' . h($page['page_name']) . '\'})">' .
                                    '<td class="position-relative"></td>' .
                                    '<td class="d-none select-all align-middle text-start"><input class="form-check-input " type="checkbox" name="pages[]" value="' . $page['page_id'] . '" class="checkbox" /></td>' .
                                    '<td class="position-relative">
                                            <span class="fs-5 position-relative  overflow-hidden bi bi-window-fullscreen ' . $access_control_type . '" title="' . $access_control_type . '">
                                                <span style="font-size:40%" class="bi position-absolute start-50 top-50 translate-middle' . get_access_control_icon_classes($access_control_type) . ' "></span>
                                            </span>
                                            ' . $folder_archived . '
                                            ' . $style . '
                                            ' . $home . '
                                        </td>' .
                                    '<td title="page type: ' . h($page['page_type']) . ' ">' . h($page['page_name']) . '</td>' .
                                    '<td>' . $size . '</td>' .
                                    '<td>' . $page_user . '</td>' .
                                    '</tr>';
                            }
                        }

                        // get files
                        $query = "SELECT
                                    files.id,
                                    files.name,
                                    files.design,
                                    files.type,
                                    files.size,
                                    files.user,
                                    files.timestamp,
                                    folder.folder_archived,
                                    folder.folder_id,
                                    user.user_username as user_username
                                 FROM files
                                 LEFT JOIN folder ON files.folder = folder.folder_id
                                 LEFT JOIN user ON files.user = user.user_id
                                 WHERE files.folder = '" . escape($parent_folder_id) . "'
                                 ORDER BY files.name";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                        $access_control_type = '';

                        while ($file = mysqli_fetch_assoc($result)) {

                            // if the user does not have edit rights to this file's folder,
                            // or this file is a design file and the user is not a designer or administrator,
                            if (
                                (check_edit_access($file['folder_id']) == false)
                                ||
                                (
                                    ($file['design'] == 1)
                                    && ($user['role'] > 1)
                                )
                            ) {

                            } else {

                                $design = 'false';
                                $access_control_type = get_access_control_type($file['folder_id']);

                                // if the file is a design file, then set design to true
                                if ($file['design'] == '1') {
                                    $design = 'true';
                                }
                                $file_time_before_upload = time() - $file['timestamp'];
                                $new_file_icon = '';
                                if ($file_time_before_upload < 900) {
                                    $new_file_icon = '<i class="bi bi-clock-history" title="' . lang('New file') . '"></i>';
                                }

                                $access = '';
                                // if this is not a design file or if the user has access to design files,
                                // then the user has access so send that
                                if (
                                    ($file['design'] == 0)
                                    || ($user['role'] <= 1)
                                ) {
                                    $access = 'true';
                                }



                                $file_user = '';
                                if ($file['user_username'] != NULL) {
                                    $file_user = h($file['user_username']);
                                }





                                $folder_archived = '';
                                if ($file['folder_archived'] == '1') {
                                    $folder_archived = '<span class="fs-5 bi bi-archive" title="' . lang('Archived') . '"></span>';
                                }



                                $size = '';
                                if ($file['size'] != '' && $file['size'] != 0) {
                                    $size = h(convert_bytes_to_string($file['size']));
                                }




                                if (isset($folder_table_view_type) && $folder_table_view_type == 'grid') {
                                    // If the file is an image.
                                    if (
                                        (mb_strtolower($file['type']) == 'bmp')
                                        || (mb_strtolower($file['type']) == 'gif')
                                        || (mb_strtolower($file['type']) == 'jpg')
                                        || (mb_strtolower($file['type']) == 'jpeg')
                                        || (mb_strtolower($file['type']) == 'png')
                                        || (mb_strtolower($file['type']) == 'tif')
                                        || (mb_strtolower($file['type']) == 'tiff')
                                    ) {

                                        // Get the dimensions of the image.
                                        $image_size = @getimagesize(FILE_DIRECTORY_PATH . '/' . $file['name']);
                                        $image_width = $image_size[0];
                                        $image_height = $image_size[1];

                                        // Output the image dimension to the table.
                                        $output_image_dimensions = lang('width') . ': ' . $image_width . ' px ' . lang('height') . ': ' . $image_height . ' px';

                                        // Set the maximum dimension size for the image.
                                        $max_dimension = 75;
                                        $output_image_style = '';

                                        if ($image_width >= $image_height) {
                                            $output_image_style = 'style="max-width:100%;max-height:auto;" ';
                                        } else {
                                            $output_image_style = 'style="max-width:auto;max-height:100%;" ';
                                        }

                                        // Call function to resize image.
                                        $thumbnail_dimensions = get_thumbnail_dimensions($image_width, $image_height, $max_dimension);
                                        $output_thumbnail = '<img ' . $output_image_style . ' title="' . $output_image_dimensions . '" class="position-absolute no-popover start-50 top-50 translate-middle " src="' . PATH . $file['name'] . '" />';
                                        $output_file_access_icon = '<i class="bi ' . get_access_control_icon_classes($access_control_type) . ' "></i>';
                                    } else {
                                        $output_thumbnail = '
                                            <div class="text-center position-relative">
                                                <i class="bi display-3 ' . get_file_icon($file['type']) . ' ' . $access_control_type . '"></i>
                                                <i style="top:50%;" class="bi fs-5 position-absolute start-50 translate-middle' . get_access_control_icon_classes($access_control_type) . ' "></i>
                                            </div>';
                                        $output_image_dimensions = '';
                                        $output_file_access_icon = '';
                                    }

                                    // output file as grid
                                    $output .=
                                        '<div type="file" class="col-6 col-sm-4 col-md-3 col-lg-3 col-xl-2 col-xxl-2 pointer custom-contextmenu explorer-contextmenu" file_id="' . $file['id'] . '"  onclick="preview_file({file_name:\'' . $file['name'] . '\',file_id:\'' . $file['id'] . '\',file_type:\'' . $file['type'] . '\'})">
                                            <div style="min-height:130px;" class="card h-100 hoverable border-0 bg-transparent shadow-none">
                                                <div class="card-header border-0 bg-transparent p-1 d-flex">
                                                    <input class="d-none form-check-input show-on-hovered" type="checkbox" name="files[]" value="' . $file['id'] . '" class="checkbox" />
                                                    <div class="ms-auto d-inline-block">
                                                        ' . $new_file_icon . '
                                                        ' . $output_file_access_icon . '
                                                    </div>
                                                </div>
                                                <div class="card-body text-center position-relative overflow-hidden p-0">
                                                    ' . $output_thumbnail . '
                                                    <div class="d-none">' . $design . '</div>
                                                    <div class="d-none">' . $access . '</div>
                                                    <div class="d-none">' . $access_control_type . '</div>
                                                    <div class="d-none">' . $folder_archived . '</div>
                                                </div>
                                                <div class="card-footer border-0 p-1 text-center bg-transparent">
                                                    <div class="text-truncate">' . h($file['name']) . '</div>
                                                </div>
                                            </div>
                                        </div>';

                                } else {

                                    // output file as table
                                    $output .=
                                        '<tr type="file" file_id="' . $file['id'] . '" class="unselectable pointer custom-contextmenu explorer-contextmenu" onclick="preview_file({file_name:\'' . $file['name'] . '\',file_id:\'' . $file['id'] . '\',file_type:\'' . $file['type'] . '\'})">' .
                                        '<td class="position-relative"></td>' .
                                        '<td  class="d-none select-all align-middle text-start"><input class="form-check-input " type="checkbox" name="files[]" value="' . $file['id'] . '" class="checkbox" /></td>' .
                                        '<td class="position-relative">
                                            <span class="fs-5 position-relative overflow-hidden bi ' . get_file_icon($file['type']) . ' ' . $access_control_type . '" title="' . $access_control_type . '">
                                                <span style="font-size:40%" class="bi position-absolute top-50 start-50 translate-middle' . get_access_control_icon_classes($access_control_type) . ' "></span>
                                            </span>
                                            ' . $new_file_icon . '
                                            ' . $folder_archived . '
                                        </td>' .
                                        '<td title="design:' . $design . ' |access: ' . $access . ' ">' . h($file['name']) . '</td>' .
                                        '<td>' . $size . '</td>' .
                                        '<td>' . $file_user . '</td>' .
                                        '</tr>';
                                }
                            }
                        }
                    }

                    if (isset($folder_table_view_type) && $folder_table_view_type == 'grid') {
                        if ($output == '') {
                            $output = '
                            <div class="container-fluid">
                            <div class="row my-5 row-cols-1 g-3">
                                <div class="col-12 text-center">
                                    <i class="bi display-3 bi-folder2-open ' . $access_control_type . '"></i>
                                    <p>' . lang('This folder is a bit quiet.') . '</p>
                                </div>
                            </div>
                            </div>';
                        } else {
                            $output = '<div class="container-fluid"><div class="row p-2 g-3">' . $output . '</div></div>';
                        }
                        //defualt
                    } else {
                        $output_table_classes = '';
                        if (isset($folder_table_view_type) && $folder_table_view_type == 'minimal') {
                            //minimal table view
                            $output_table_classes = 'chart table table-hover table-sm table-borderless table-condensed datatable-restricted-mode datatable-no-info datatable-click-to-select';
                        } else {
                            //normal table view
                            $output_table_classes = 'chart table-condensed table-hover table datatable-restricted-mode datatable-no-info datatable-click-to-select ';
                        }

                        $output = '
                        <table class="' . $output_table_classes . '" style="width:100%" >
                            <thead>
                                <tr>
                                    <th class="noVis"></th>
                                    <th class="noVis d-none">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" title="' . lang(array('string' => 'Select/Deselect All')) . '" type="checkbox" id="select_all">
                                        </div>
                                    </th>
                                    <th class="noVis"><i class="bi bi-file-earmark"></i></th>
                                    <th class="noVis">' . lang('Name') . '</th>
                                    <th>' . lang('Size') . '</th>
                                    <th>' . lang('Last Modified') . '</th>
                                </tr>
                            </thead>
                            <tbody>
                                ' . $output . '
                            </tbody>
                        </table>';
                    }

                    return $output;
                }
                $response = array(
                    'status' => 'success',
                    'request' => $request['type'],
                    'view_type' => $folder_table_view_type,
                    'content' => get_folder_table($folder_id, $folder_table_view_type),
                );
                echo encode_json($response);
                break;


        }
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
        $user = validate_user();
        validate_ecommerce_access($user);
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
        $user = validate_user();
        validate_ecommerce_access($user);
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
        $user = validate_user();
        validate_ecommerce_access($user);
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
        $user = validate_user();
        validate_ecommerce_access($user);
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
        $user = validate_user();
        validate_ecommerce_access($user);
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
        $user = validate_user();
        validate_ecommerce_access($user);
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
                                'form_fields');
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

            // Pages the "Sayfa Seç" picker may offer: every visual-designer
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
            // is the reversible move: "Sayfa Seç" brings it back. Deleting a
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
                $paste_res = pg_designer_import_single_html(
                    $paste_html, $paste_name . '.html', $user, array('skip_names' => $paste_skip));
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
                               'list_custom_forms', 'list_form_item_view_pages', 'list_product_groups');
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
                $sc_rows = db_items("SELECT id FROM shared_components");
                $sc_ids  = array();
                if (is_array($sc_rows)) {
                    foreach ($sc_rows as $r) { $sc_ids[] = (int)$r['id']; }
                }
                $usage_map = array();
                foreach ($sc_ids as $sid) { $usage_map[$sid] = array(); }

                // Scan every non-empty tree once; for each shared id found,
                // append the owning page — and the design it belongs to — to
                // its usage list.
                //
                // Trees live on the PAGE since the multi-page designer (one
                // style, several pages, each with its own layout). One row per
                // page with a tree; the style's own tree is the fallback for
                // pages saved before the swap. Both ids travel: the page name
                // is what the operator sees in tabs, the style id is how the
                // editor tells "used in this design" from "used elsewhere on
                // the site". On an un-migrated database this collapses to the
                // old per-style scan and page_id is 0.
                if (pg_multi_page_design_ready()) {
                    $style_rows = db_items(
                        "SELECT page.page_id, page.page_name, style.style_id, style.style_name,
                                " . pg_page_tree_sql_expr() . " AS style_tree_json
                         FROM page
                         INNER JOIN style ON page.page_style = style.style_id
                         WHERE page.layout_type = 'system'
                         HAVING style_tree_json IS NOT NULL AND style_tree_json != ''"
                    );
                } else {
                    $style_rows = db_items(
                        "SELECT 0 AS page_id, style_name AS page_name, style_id, style_name, style_tree_json
                         FROM style
                         WHERE style_tree_json IS NOT NULL AND style_tree_json != ''"
                    );
                }
                if (is_array($style_rows)) {
                    foreach ($style_rows as $sr) {
                        $json = $sr['style_tree_json'];
                        if (strpos($json, '"sharedId"') === false) continue;
                        foreach ($sc_ids as $sid) {
                            // Match the exact JSON literal `"sharedId":<id>` — id
                            // is numeric so value is never quoted in our writer.
                            $needle = '"sharedId":' . $sid;
                            // Guard against substring collisions (e.g. sid=1 matching
                            // sid=10) by checking the next char is not a digit.
                            $pos = 0;
                            $found = false;
                            while (($pos = strpos($json, $needle, $pos)) !== false) {
                                $next = substr($json, $pos + strlen($needle), 1);
                                if ($next === '' || !ctype_digit($next)) { $found = true; break; }
                                $pos += strlen($needle);
                            }
                            if ($found) {
                                $usage_map[$sid][] = array(
                                    'page_id'    => (int)$sr['page_id'],
                                    'page_name'  => (string)$sr['page_name'],
                                    'style_id'   => (int)$sr['style_id'],
                                    'style_name' => (string)$sr['style_name'],
                                );
                            }
                        }
                    }
                }
                respond(array('status' => 'success', 'usage' => $usage_map));
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
                db("INSERT INTO shared_components (name, description, tree_json, system_region_config, created_by, created_at, updated_at)
                    VALUES ('" . e($sc_name) . "', '" . e($sc_desc) . "', '" . e($sc_tree) . "',
                            $sc_cfg_sql, '" . (int)$user['id'] . "', '$sc_now', '$sc_now')");
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
                respond(array('status' => 'success', 'pages' => $sc_iv_rows));
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
            // Used by the catalog_item_view widget's "Katalog sayfası"
            // picker — designer chooses which catalog page the breadcrumb
            // crumbs (Ana Katalog → Group → …) and cross-sell card URLs
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
                $sc_ap_rows = db_items(
                    "SELECT page_id, page_name, page_title
                     FROM page
                     ORDER BY page_title ASC, page_name ASC"
                );
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
                $sc_ff_rows = db_items(
                    "SELECT id, name, label, type
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
    // Visual Pinegrap Editor's "Yeni CSS/JS/JSON Dosyası" actions create a real
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

// Eight daily counts -- seven days ago through today -- for one timestamp
// column, in ONE query.
//
// This replaces what the activity summary widget used to do: eight separate
// COUNT(*) per metric, twenty-four in total on every dashboard load. The counts
// are bucketed with CASE rather than GROUP BY on a formatted date, so the WHERE
// still compares the raw column against a constant and the index on it applies.
// Wrapping the column in FROM_UNIXTIME() to group by day would defeat exactly
// the index that makes this cheap.
//
// $extra is appended to the WHERE and must already be escaped by the caller.
function pg_activity_daily($table, $column, $extra = '')
{
    $midnight = strtotime(date('Y-m-d'));

    // Boundaries first, oldest to newest, so the buckets below read in the same
    // order the chart draws them.
    $edges = array();
    for ($day = 7; $day >= 0; $day--) {
        $edges[] = $midnight - ($day * 86400);
    }

    $sums = array();
    foreach ($edges as $index => $from) {
        // The last bucket is today and has no upper edge: nothing is recorded
        // in the future, and an upper bound of "now" would drop rows written
        // between building this query and running it.
        $to = isset($edges[$index + 1]) ? $edges[$index + 1] : 0;
        $sums[] = ($to > 0)
            ? "SUM(CASE WHEN $column >= $from AND $column < $to THEN 1 ELSE 0 END) AS d$index"
            : "SUM(CASE WHEN $column >= $from THEN 1 ELSE 0 END) AS d$index";
    }

    $row = db_item(
        "SELECT " . implode(', ', $sums) . "
         FROM $table
         WHERE ($column >= " . $edges[0] . ")" . $extra
    );

    $out = array();
    for ($index = 0; $index < 8; $index++) {
        $out[] = isset($row['d' . $index]) ? (int) $row['d' . $index] : 0;
    }

    return $out;
}

// Headline for a list widget: today's count, which way it moved against
// yesterday, the seven day total, and a sparkline of the eight days.
//
// One line rather than the block the pending shipments card uses. That card has
// a dozen rows under it and can spare the height; these three have lists of
// 52px rows, and a headline that cost 100px would take two of them.
function pg_widget_headline($properties)
{
    $series = $properties['series'];
    $unit   = $properties['unit'];
    $rgb    = $properties['rgb'];
    $id     = $properties['id'];

    $today     = (int) end($series);
    $yesterday = (int) $series[count($series) - 2];
    // The series carries eight days so the sparkline has a lead-in point
    // before the week it labels. The total sums the last seven only --
    // today plus six -- because that is what the label says.
    $total     = array_sum(array_slice($series, -7));

    if ($today > $yesterday) {
        $direction = 'up';
        $arrow = 'bi-caret-up-fill';
        $color = 'text-success';
    } elseif ($today < $yesterday) {
        $direction = 'down';
        $arrow = 'bi-caret-down-fill';
        $color = 'text-danger';
    } else {
        $direction = 'flat';
        $arrow = 'bi-dash';
        $color = 'text-muted';
    }

    return '
    <div class="pg-head">
        <div class="pg-head-line">
            <span class="pg-head-num">' . number_format($today) . '</span>
            <span class="pg-head-unit text-muted">' . $unit . '</span>
            <i class="bi ' . $arrow . ' pg-head-dir ' . $color . '" title="' . h(lang('Compared to yesterday')) . '"></i>
            <span class="pg-head-total text-muted">' . lang(array(
                'string' => 'in 7 days {var:1}',
                'vars' => '<b>' . number_format($total) . '</b>',
            )) . '</span>
        </div>
        <span class="pg-head-spark"><canvas id="' . $id . '" data-series="' . h(json_encode($series)) . '" data-rgb="' . $rgb . '"></canvas></span>
    </div>
    <script>(function(){
        var el = document.getElementById(' . json_encode($id) . ');
        if (!el || typeof Chart === "undefined") return;
        var series = JSON.parse(el.getAttribute("data-series"));
        var rgb = el.getAttribute("data-rgb");
        new Chart(el.getContext("2d"), {
            type: "line",
            data: { labels: series.map(function(_, i){ return i; }), datasets: [{
                data: series,
                borderColor: "rgba("+rgb+",1)", backgroundColor: "rgba("+rgb+",.16)",
                borderWidth: 1.6, fill: true, tension: 0.35, pointRadius: 0
            }]},
            options: {
                animation: false, responsive: true, maintainAspectRatio: false,
                plugins: { legend:{display:false}, tooltip:{enabled:false} },
                scales: { x:{display:false}, y:{display:false, beginAtZero:true} },
                events: []
            }
        });
    })();</script>';
}

// Dashboard list row. Every widget that renders a list builds its rows through
// this, so the cards read as one surface instead of a dozen dialects: an accent
// tile, a bold title with an optional value beside it, a muted second line, and
// an arrow. The shape is styled in backend.src.css (.pg-row-*), which is also
// what lets welcome.php reuse the same boxes for the loading skeleton.
//
// Values arrive already escaped, because several callers legitimately pass
// markup: a product thumbnail, a Bootstrap icon, a formatted amount.
//
// 'color' overrides the tile colour. The rule: the tile is the card's accent,
// so a card reads as one thing, EXCEPT where the row's state is exceptional
// and worth breaking the pattern for -- a cancelled order, an offer expiring
// in three days -- or where the row, not the card, is what the colour
// identifies, as pending shipments does with carriers. Two normal states of
// the same kind are told apart by the glyph, not by colour.
//
// A row with no 'href' renders as a div and drops the arrow, so a list that
// leads nowhere does not advertise a click that will not happen.
function pg_widget_row($properties)
{
    $href   = $properties['href']   ?? '';
    $badge  = $properties['badge']  ?? '';
    $name   = $properties['name']   ?? '';
    $aside  = $properties['aside']  ?? '';
    $meta   = $properties['meta']   ?? '';
    $color  = $properties['color']  ?? '';
    $target = $properties['target'] ?? '';

    $small = !empty($properties['small']);
    $muted = !empty($properties['muted']);
    $extra = $properties['class'] ?? '';

    // Nested anchors do not parse: the moment the browser meets an inner <a>
    // inside the row's own one it closes the row, and everything after that
    // point -- the rest of the meta line, the arrow -- lands in the list as a
    // sibling instead of inside the box. Log messages arrive with their URLs
    // already linkified, so a row that is itself a link keeps the text and
    // drops the inner tags. The row's own href is where it goes.
    if ($href !== '') {
        $name  = pg_strip_anchor_tags($name);
        $aside = pg_strip_anchor_tags($aside);
        $meta  = pg_strip_anchor_tags($meta);
    }

    $output_aside  = ($aside !== '') ? '<span class="pg-row-aside text-muted">' . $aside . '</span>' : '';
    $output_meta   = ($meta !== '') ? '<span class="pg-row-meta d-block text-muted">' . $meta . '</span>' : '';
    // A row that sets its own tile colour has to bring its own glyph colour
    // with it, because the CSS variable pairing only covers the card accents.
    $output_color = ($color !== '')
        ? ' style="background:' . $color . ';color:' . pg_readable_ink($color) . '"'
        : '';
    $output_target = ($target !== '') ? ' target="' . $target . '" rel="noopener"' : '';

    // A real link rather than an onclick handler, so the row is reachable by
    // keyboard and opens in a new tab on middle click like any other link.
    $class = 'pg-row' . ($small ? ' pg-row-sm' : '') . ($muted ? ' opacity-50' : '')
        . ($extra !== '' ? ' ' . $extra : '');

    if ($href !== '') {
        $open  = '<a href="' . $href . '" class="' . $class . '"' . $output_target . '>';
        $close = '<i class="bi bi-arrow-right text-muted pg-row-go"></i></a>';
    } else {
        $open  = '<div class="' . $class . '">';
        $close = '</div>';
    }

    return $open . '
        <span class="pg-row-badge"' . $output_color . '>' . $badge . '</span>
        <span class="pg-row-text">
            <span class="pg-row-line">
                <span class="pg-row-name">' . $name . '</span>
                ' . $output_aside . '
            </span>
            ' . $output_meta . '
        </span>
        ' . $close;
}

// Removes <a> and </a> while leaving the text and any other markup -- an icon,
// a <time>, a thumbnail -- untouched. Only the tags go, so nothing the caller
// meant to show is lost.
function pg_strip_anchor_tags($html)
{
    return preg_replace('#</?a\b[^>]*>#i', '', (string) $html);
}

// White or near-black, whichever reads better on the given background. Mid-tone
// brand colours are the problem case: white on #f59e0b is 2.15:1, which fails
// even the 3:1 that a graphical object needs, and some tiles hold initials,
// which is text and wants 4.5:1. Relative luminance per WCAG 2.1.
function pg_readable_ink($hex)
{
    $hex = ltrim((string) $hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
        return '#fff';
    }

    $luminance = 0;
    $weights = array(0.2126, 0.7152, 0.0722);
    foreach (array(0, 2, 4) as $index => $offset) {
        $channel = hexdec(substr($hex, $offset, 2)) / 255;
        $channel = ($channel <= 0.03928)
            ? $channel / 12.92
            : pow(($channel + 0.055) / 1.055, 2.4);
        $luminance += $weights[$index] * $channel;
    }

    // 0.0593 is #18181b's relative luminance plus the same 0.05 offset.
    $against_white = 1.05 / ($luminance + 0.05);
    $against_dark  = ($luminance + 0.05) / 0.0593;

    return ($against_white >= $against_dark) ? '#fff' : '#18181b';
}

// Section heading inside a list, for the widgets that group their rows --
// "Top Pages" over "Top Products", "Expiring Soon" over the rest.
function pg_widget_row_heading($label, $aside = '')
{
    return '<div class="pg-row-heading text-muted">' . $label
        . (($aside !== '') ? '<span class="pg-row-aside">' . $aside . '</span>' : '')
        . '</div>';
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