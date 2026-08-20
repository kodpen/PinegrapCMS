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
$request = json_decode(@file_get_contents('php://input'), true);

// If login info was included in the request, then store it, so that initialize_user() can login user.
if ((isset($request['username'])) && ($request['username'] != '')) {
    define('API_USERNAME', $request['username']);
    define('API_PASSWORD', md5($request['password']));
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
    and ($action != 'update_dashboard_widgets')
    and ($action != 'software_backup')
    and ($action != 'software_update')
    and ($action != 'user_pinned_app_update')

    and ($action != 'remove_notifications')
    and ($action != 'get_notifications')
    and ($action != 'check_unread_notifications')
    and ($action != 'edit_notifications')

    and ($action != 'get_widget_data')

    and ($action != 'file_explorer')

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
                // empty widget slot
                $response = array(
                    'status' => 'success',
                    'message' => lang('Data Received successfully.'),
                    'data' => '',
                );
                echo encode_json($response);
                exit();
                break;


            case '2':
                // ── System status ───────────────────────────────────────
                //
                // One score for "is this installation healthy", drawn as a
                // gauge, over one tile per check. It absorbed the maintenance
                // card that used to live at this id: backup age, pending
                // update and scheduled tasks are now scored checks rather than
                // a separate list, because an operator reading a health number
                // that ignores a month-old backup is reading a number that
                // lies.
                //
                // The checks themselves are in functions.php and are cached
                // there for ten minutes -- several of them stat the filesystem
                // or open a socket. This case only renders.
                //
                // Role gate matches the screens the tiles link to: backups.php
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

                    // The number carries the verdict, the arc carries the
                    // magnitude. Same thresholds the old status bar used, so a
                    // site does not change colour just because the widget did.
                    if ($health_score < 70) {
                        $health_color = 'text-danger';
                    } elseif ($health_score < 90) {
                        $health_color = 'text-warning';
                    } else {
                        $health_color = 'text-success';
                    }

                    // Two grids, not one. The three checks flagged in
                    // functions.php lead at three across, the rest follow at
                    // four. Row count is unchanged either way -- three plus
                    // twelve is one row plus three, same as fifteen over four
                    // -- so leading them costs no height.
                    $output_lead_tiles = '';
                    $output_tiles = '';
                    $output_detail = '';

                    foreach ($health_checks as $health_check) {

                        $tile_href = isset($health_check['href']) ? $health_check['href'] : '';
                        $tile_detail = (isset($health_check['detail']) && is_array($health_check['detail']))
                            ? $health_check['detail']
                            : array();

                        // The popover is the only place the full explanation
                        // fits, and welcome.php and settings.php both bind it
                        // by delegation from document, so it survives the
                        // markup being injected after page load.
                        $tile_attributes =
                            ' class="pg-health-tile status-popover"'
                            . ' title="' . h($health_check['title']) . '"'
                            . ' data-bs-toggle="popover"'
                            . ' data-bs-trigger="hover focus"'
                            . ' data-bs-content="' . h($health_check['message']) . '"';

                        // Three kinds of tile, and the marker in the corner says
                        // which: an arrow for one that navigates, a chevron for
                        // one that opens in place, nothing for one that is only
                        // a reading.
                        $tile_marker = '';

                        if ($tile_detail) {

                            // Bootstrap's collapse data-api is delegated from
                            // document, so it binds to markup this widget
                            // injects after page load. Widget 24 is
                            // deliberately absent from welcome.php's periodic
                            // refresh list, so nothing re-renders the card and
                            // closes the panel under the operator's hand.
                            //
                            // The popover would fire on the same hover that
                            // opens the panel, so this tile carries the
                            // collapse attributes instead and its explanation
                            // is the panel itself.
                            $tile_open = '<a role="button" data-bs-toggle="collapse" href="#system_status_detail"'
                                . ' aria-expanded="false" aria-controls="system_status_detail"'
                                . ' class="pg-health-tile pg-health-tile-marked" title="' . h($health_check['title']) . '">';
                            $tile_close = '</a>';
                            $tile_marker = '<i class="bi bi-chevron-down pg-health-tile-go"></i>';

                            foreach ($tile_detail as $detail_row) {
                                $output_detail .= '
                                <div class="pg-health-detail-row">
                                    <span class="text-truncate"><i class="bi bi-circle-fill pg-health-dot text-' . h(($detail_row['state'] == 'ok') ? 'success' : (($detail_row['state'] == 'fail') ? 'danger' : 'secondary')) . '"></i>' . h($detail_row['label']) . '</span>
                                    <span class="text-muted flex-shrink-0">' . h($detail_row['when']) . '</span>
                                </div>';
                            }

                        } elseif ($tile_href !== '') {
                            // pg-health-tile-marked earns the name line a
                            // little right padding, because the marker is
                            // positioned over the corner rather than sitting in
                            // either line. In the flow it was costing the value
                            // its last few characters -- "10 saat önce" became
                            // "10 saat ö...".
                            $tile_open  = '<a href="' . h($tile_href) . '"' . str_replace('class="pg-health-tile ', 'class="pg-health-tile pg-health-tile-marked ', $tile_attributes) . '>';
                            $tile_close = '</a>';
                            $tile_marker = '<i class="bi bi-arrow-right pg-health-tile-go"></i>';
                        } else {
                            $tile_open  = '<div' . $tile_attributes . '>';
                            $tile_close = '</div>';
                        }

                        // The marker rides the value line rather than the name
                        // line. "Tamam" leaves room for an arrow; "Veritabanı"
                        // does not, and the label is the half worth reading.
                        $output_tile = $tile_open . '
                            <span class="pg-health-tile-name ' . h($health_check['color']) . '">
                                <i class="bi ' . h($health_check['icon']) . '"></i><span>' . h($health_check['label']) . '</span>
                            </span>
                            <span class="pg-health-tile-value text-muted">
                                <span>' . h($health_check['value']) . '</span>
                            </span>' . $tile_marker . '
                        ' . $tile_close;

                        if (!empty($health_check['priority'])) {
                            $output_lead_tiles .= $output_tile;
                        } else {
                            $output_tiles .= $output_tile;
                        }
                    }

                    if ($output_lead_tiles !== '') {
                        $output_lead_tiles = '<div class="pg-health-grid pg-health-grid-lead">' . $output_lead_tiles . '</div>';
                    }

                    if ($output_detail !== '') {
                        $output_detail = '
                        <div class="collapse" id="system_status_detail">
                            <div class="pg-health-detail">' . $output_detail . '</div>
                        </div>';
                    }

                    $output_data = '
                    <div class="card-body p-0 overflow-x-hidden overflow-y-auto">
                        <div class="pg-health">
                            <svg class="pg-health-gauge" viewBox="8 8 124 72" role="img" aria-label="' . h(lang('Overall System Health')) . ' ' . (int) $health_score . '%">
                                <defs>
                                    <linearGradient id="pg_health_gradient" x1="0" y1="1" x2="1" y2="0">
                                        <stop offset="0%" stop-color="#22d3ee"/>
                                        <stop offset="50%" stop-color="#a855f7"/>
                                        <stop offset="100%" stop-color="#ec4899"/>
                                    </linearGradient>
                                    <!--
                                        The glow is a blurred copy of the arc
                                        rather than a drop-shadow, because a
                                        drop-shadow takes one colour and the arc
                                        has three: blurring the stroke itself
                                        keeps the glow the colour of whatever it
                                        is under. .pg-health-gauge is
                                        overflow:visible so the blur is not
                                        clipped at the viewBox edge.
                                    -->
                                    <filter id="pg_health_glow" x="-50%" y="-50%" width="200%" height="200%">
                                        <feGaussianBlur stdDeviation="4.5"/>
                                    </filter>
                                </defs>
                                <circle class="pg-health-track" cx="70" cy="70" r="52" stroke-width="10"
                                        stroke-dasharray="' . $health_arc . ' 326.73"></circle>
                                <circle class="pg-health-glow" cx="70" cy="70" r="52" stroke-width="10" stroke="url(#pg_health_gradient)"
                                        filter="url(#pg_health_glow)"
                                        stroke-dasharray="' . round($health_arc * $health_score / 100, 2) . ' 326.73"></circle>
                                <circle cx="70" cy="70" r="52" stroke-width="10" stroke="url(#pg_health_gradient)"
                                        stroke-dasharray="' . round($health_arc * $health_score / 100, 2) . ' 326.73"></circle>
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
                                <div class="pg-health-score ' . $health_color . '">' . (int) $health_score . '<span>%</span></div>
                                <div class="pg-health-label text-muted">' . lang('Overall System Health') . '</div>
                            </div>
                        </div>
                        ' . $output_lead_tiles . '
                        <div class="pg-health-grid">
                            ' . $output_tiles . '
                        </div>
                        ' . $output_detail . '
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
                    $orders = array();
                    $query = "SELECT
                            orders.id,
                            orders.order_number,
                            orders.total as total,
                            orders.order_date as timestamp
                        FROM orders
                        WHERE status IN ('complete', 'exported')";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                    // loop through the result in order to prepare array of items
                    while ($row = mysqli_fetch_assoc($result)) {
                        $orders[] = $row;
                    }

                    $order_totals = 0;
                    $this_year_totals = 0;
                    $this_month_totals = 0;
                    $this_week_totals = 0;
                    $today_totals = 0;

                    $number_of_order = 0;
                    $this_year_number_of_order = 0;
                    $this_month_number_of_order = 0;
                    $this_week_number_of_order = 0;
                    $today_number_of_order = 0;

                    // loop through the orders, in order to output rows
                    foreach ($orders as $order) {

                        $order_totals = $order_totals + $order['total'];
                        $number_of_order++;

                        if ((time() - $order['timestamp']) < 31556926) { //365days
                            $this_year_totals = $this_year_totals + $order['total'];
                            $this_year_number_of_order++;
                        }
                        if ((time() - $order['timestamp']) < 2629743) { //30days
                            $this_month_totals = $this_month_totals + $order['total'];
                            $this_month_number_of_order++;
                        }
                        if ((time() - $order['timestamp']) < 604800) { //7days
                            $this_week_totals = $this_week_totals + $order['total'];
                            $this_week_number_of_order++;
                        }
                        if ((time() - $order['timestamp']) < 86400) { //24 hours
                            $today_totals = $today_totals + $order['total'];
                            $today_number_of_order++;
                        }

                    }


                    $order_total = sprintf("%01.2lf", $order_totals / 100);
                    $this_year_total = sprintf("%01.2lf", $this_year_totals / 100);
                    $this_month_total = sprintf("%01.2lf", $this_month_totals / 100);
                    $this_week_total = sprintf("%01.2lf", $this_week_totals / 100);
                    $today_total = sprintf("%01.2lf", $today_totals / 100);

                    $order_total = number_format($order_total, 2, ',', '.');
                    $this_year_total = number_format($this_year_total, 2, ',', '.');
                    $this_month_total = number_format($this_month_total, 2, ',', '.');
                    $this_week_total = number_format($this_week_total, 2, ',', '.');
                    $today_total = number_format($today_total, 2, ',', '.');


                    $query = "SELECT
                                id,
                                name,
                                price,
                                inventory,
                                inventory_quantity
                            FROM products
                            WHERE inventory = 1";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    $products = 0;
                    $quantities = 0;
                    $in_stock_product_total_price = 0;
                    $prices = 0;
                    $all_items = '';
                    $all_products = '';

                    // loop through the products in order to output CSV data
                    while ($row = mysqli_fetch_assoc($result)) {

                        $prices = $row['price'] / 100 * $row['inventory_quantity'] + $prices;
                        $products++;
                        $quantities = $row['inventory_quantity'] + $quantities;
                    }
                    $in_stock_product_total_price = BASE_CURRENCY_SYMBOL . number_format($prices, 2, ',', '.');
                    $all_items = number_format($quantities);
                    $all_products = number_format($products);

                    // if there order output informations
                    if ($number_of_order != 0) {
                        $output_all_time_number_of_orders_title = 'title="' . lang(array('string' => '{var:1} orders for a total of {var:2} {var:3}.', 'vars' => array($number_of_order, BASE_CURRENCY_SYMBOL . $order_total, lang('Until Now')))) . '"';
                    } else {
                        //else output no order message
                        $output_all_time_number_of_orders_title = 'title="' . lang('No orders yet') . '"';
                    }


                    // ── Period rows (today / week / month / year) ─────────────
                    $periods = array(
                        array(
                            'label' => lang('Today'),
                            'icon' => 'bi-sun-fill',
                            'color' => '#f59e0b',
                            'count' => $today_number_of_order,
                            'total' => BASE_CURRENCY_SYMBOL . $today_total,
                        ),
                        array(
                            'label' => lang('Last 1 Week'),
                            'icon' => 'bi-calendar-week',
                            'color' => '#3b82f6',
                            'count' => $this_week_number_of_order,
                            'total' => BASE_CURRENCY_SYMBOL . $this_week_total,
                        ),
                        array(
                            'label' => lang('Last 1 Month'),
                            'icon' => 'bi-calendar-month',
                            'color' => '#8b5cf6',
                            'count' => $this_month_number_of_order,
                            'total' => BASE_CURRENCY_SYMBOL . $this_month_total,
                        ),
                        array(
                            'label' => lang('Last 1 Year'),
                            'icon' => 'bi-calendar2-check',
                            'color' => '#10b981',
                            'count' => $this_year_number_of_order,
                            'total' => BASE_CURRENCY_SYMBOL . $this_year_total,
                        ),
                    );

                    $output_period_rows = '';
                    foreach ($periods as $p) {
                        // Each period already has its own colour in $periods,
                        // and the four of them are a scale rather than one card
                        // accent, so they keep it.
                        $output_period_rows .= pg_widget_row(array(
                            'small' => true,
                            'muted' => ($p['count'] == 0),
                            'color' => $p['color'],
                            'badge' => '<i class="bi ' . $p['icon'] . '"></i>',
                            'name'  => $p['label'],
                            'aside' => '<span class="badge rounded-pill me-1" style="background:' . $p['color'] . '22;color:' . $p['color'] . '">' . $p['count'] . '</span>'
                                . '<span class="fw-semibold">' . ($p['count'] > 0 ? $p['total'] : '&mdash;') . '</span>',
                        ));
                    }

                    $output_rows = '
                    <div class="d-flex gap-2 p-2">
                        <div class="flex-fill rounded p-2 text-center" style="background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.2)" title="' . lang('Total value of products in stock') . ' (' . $all_products . ' ' . lang('Product(s)') . ' · ' . $all_items . ' ' . lang('Piece(s)') . ')">
                            <i class="bi bi-boxes d-block" style="color:#10b981;font-size:20px"></i>
                            <span class="fw-bold d-block" style="font-size:13px">' . $in_stock_product_total_price . '</span>
                            <small class="text-muted">' . lang('Stock Value') . '</small>
                            <small class="text-muted d-block" style="font-size:10px">' . $all_products . ' ' . lang('Product(s)') . ' · ' . $all_items . ' ' . lang('Piece(s)') . '</small>
                        </div>
                        <div class="flex-fill rounded p-2 text-center" style="background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.2)" ' . $output_all_time_number_of_orders_title . '>
                            <i class="bi bi-credit-card-fill d-block" style="color:#3b82f6;font-size:20px"></i>
                            <span class="fw-bold d-block" style="font-size:13px">' . BASE_CURRENCY_SYMBOL . $order_total . '</span>
                            <small class="text-muted">' . lang('Total Sales Amount') . '</small>
                            <small class="text-muted d-block" style="font-size:10px">' . $number_of_order . ' ' . lang('Order(s)') . '</small>
                        </div>
                    </div>
                    <div class="border-top mx-2 mb-1"></div>
                    <div class="pg-list">' . $output_period_rows . '</div>';

                    $output_data = '
                    <div class="card-body p-0 overflow-x-hidden overflow-y-auto">
                        ' . $output_rows . '
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

                    $eg_sections = '';

                    if ($eg_waiting_html !== '') {
                        $eg_sections .= $eg_heading(lang('Waiting for a reply'), count($eg_chat_rows)) . $eg_waiting_html;
                    }

                    if ($eg_online_html !== '') {
                        $eg_sections .= $eg_heading(lang('Online'), $eg_online_shown) . $eg_online_html;
                    }

                    if ($eg_offline_html !== '') {
                        $eg_sections .= $eg_heading(lang('Offline'), $eg_offline_shown) . $eg_offline_html;
                    }

                    if ($eg_sections === '') {
                        $eg_sections = '
                        <div class="text-center py-4">
                            <i class="bi bi-person-dash d-block mb-2" style="font-size:22px;opacity:.35"></i>
                            <p class="text-muted mb-0" style="font-size:12px">' . lang(array('string' => 'There is no {var:1} right now.', 'vars' => lang('Online User'))) . '</p>
                        </div>';
                    }

                    $output_data = '
                    <div class="card-body p-0" style="overflow-x:hidden;overflow-y:auto">
                        <div class="d-flex gap-2 p-2">
                            <div class="flex-fill rounded p-2 text-center" style="background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.2)">
                                <i class="bi bi-people-fill d-block" style="color:#10b981;font-size:20px"></i>
                                <span class="fw-bold d-block" style="font-size:15px">' . number_format($eg_active_visitors) . '</span>
                                <small class="text-muted">' . lang('Active Visitors') . '</small>
                                <small class="text-muted d-block" style="font-size:10px">' . lang('Last 20 min') . '</small>
                            </div>
                            <div class="flex-fill rounded p-2 text-center" style="background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.2)">
                                <i class="bi bi-person-gear d-block" style="color:#3b82f6;font-size:20px"></i>
                                <span class="fw-bold d-block" style="font-size:15px">' . number_format($eg_online_users_count) . '</span>
                                <small class="text-muted">' . lang('Online Users') . '</small>
                                <small class="text-muted d-block" style="font-size:10px">' . lang('Last 20 min') . '</small>
                            </div>
                        </div>
                        <div class="border-top mx-2 mb-1">
                            ' . $eg_sections . '
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

                    $vs5_no_data = '<p class="position-absolute top-50 start-50 translate-middle text-center text-muted px-3" style="font-size:11px;width:90%">'
                        . lang('There is not enough data yet.') . '</p>';

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
                    $output_rows = '<p class="position-absolute top-50 start-50 translate-middle w-75 text-center">' . lang(array('string' => 'There is no {var:1} right now.', 'vars' => lang('Recent Update'))) . '</p>';
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
                        $output_order_rows = '<p class="position-absolute top-50 start-50 translate-middle w-75 text-center">' . lang(array('string' => 'There is no {var:1} right now.', 'vars' => lang('Order'))) . '</p>';
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
                        LEFT JOIN contacts ON orders.id = contacts.id
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

                            // get paid total
                            $query =
                                "SELECT SUM(order_items.price) as price,
                                quantity
                                FROM order_items
                                WHERE order_id = '" . $cart['id'] . "'";
                            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                            $row = mysqli_fetch_assoc($result);
                            $price = $row['price'];
                            $quantity = $row['quantity'];
                            $total = BASE_CURRENCY_SYMBOL . number_format($price * $quantity / 100, 2, '.', ',');

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


                            $output_cart_rows .= pg_widget_row(array(
                                'href'  => $output_link_url,
                                'badge' => '<i class="bi bi-cart"></i>',
                                'name'  => h($name),
                                'aside' => $total,
                                'meta'  => h($cart['reference_code'])
                                    . ' &middot; ' . get_relative_time(array('timestamp' => $cart['timestamp'])),
                            ));
                        }
                    } else {
                        $output_cart_rows = '<p class="position-absolute top-50 start-50 translate-middle w-75 text-center">' . lang(array('string' => 'There is no {var:1} right now.', 'vars' => lang('Shopping Cart'))) . '</p>';
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

                    $output_data = '
                        <div class="card-body px-0 pt-1 pb-0">                           
                            <div class="tab-content" id="visitor_report_tabs">
                                <div style="height:240px;overflow-x:hidden;overflow-y:auto" class="tab-pane fade show active" id="tab-order" role="tabpanel" tabindex="0">' . $output_headline . '<div class="pg-list">' . $output_order_rows . '</div></div>
                                <div style="height:240px;overflow-x:hidden;overflow-y:auto" class="tab-pane fade"             id="tab-card" role="tabpanel" tabindex="0"><div class="pg-list">' . $output_cart_rows . '</div></div>
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
                        $output_rows = '<div class="pg-row-empty text-muted small">'
                            . '<i class="bi bi-check-circle text-success"></i>'
                            . lang('All orders have been shipped.') . '</div>';
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
                        $output_rows = '<p class="position-absolute top-50 start-50 translate-middle w-75 text-center">' . lang(array('string' => 'There is no {var:1} right now.', 'vars' => lang('Contact'))) . '</p>';
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
                        $output_rows = '<p class="position-absolute top-50 start-50 translate-middle w-75 text-center">' . lang(array('string' => 'There is no {var:1} right now.', 'vars' => lang('User'))) . '</p>';
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
                        $output_rows = '<p class="position-absolute top-50 start-50 translate-middle w-75 text-center">' . lang(array('string' => 'There is no {var:1} right now.', 'vars' => lang('Out of Stock Product'))) . '</p>';
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

                        // get all custom forms in order to determine which the user has access to
                        $query = "SELECT
                                page_id,
                                page_folder as folder_id
                            FROM page
                            WHERE page_type = 'custom form'";
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
                        $output_rows = '<p class="position-absolute top-50 start-50 translate-middle w-75 text-center">' . lang(array('string' => 'There is no {var:1} right now.', 'vars' => lang('Form'))) . '</p>';
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
                        $request['uname'] = php_uname();
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

                        if (lang(array('info' => '')) === 'tr') {
                            setlocale(LC_ALL, 'tr_TR.UTF-8');
                        }

                        $today = date_create(date("d-m-Y"));
                        $D_start_d_formatted = date_create(date("d-m-Y", strtotime($D_start_d)));
                        $D_end_d_formatted = date_create(date("d-m-Y", strtotime($D_end_d)));
                        $H_start_d_formatted = date_create(date("d-m-Y", strtotime($H_start_d)));
                        $H_end_d_formatted = date_create(date("d-m-Y", strtotime($H_end_d)));
                        $SSL_start_d_formatted = date_create(date("d-m-Y", strtotime($SSL_start_d)));
                        $SSL_end_d_formatted = date_create(date("d-m-Y", strtotime($SSL_end_d)));
                        $P_start_d_formatted = date_create(date("d-m-Y", strtotime($P_start_d)));
                        $P_end_d_formatted = date_create(date("d-m-Y", strtotime($P_end_d)));

                        $D_start_d_localized = strftime("%e %B %Y", strtotime($D_start_d));
                        $D_end_d_localized = strftime("%e %B %Y", strtotime($D_end_d));
                        $H_start_d_localized = strftime("%e %B %Y", strtotime($H_start_d));
                        $H_end_d_localized = strftime("%e %B %Y", strtotime($H_end_d));
                        $SSL_start_d_localized = strftime("%e %B %Y", strtotime($SSL_start_d));
                        $SSL_end_d_localized = strftime("%e %B %Y", strtotime($SSL_end_d));
                        $P_start_d_localized = strftime("%e %B %Y", strtotime($P_start_d));
                        $P_end_d_localized = strftime("%e %B %Y", strtotime($P_end_d));


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
                                offers.start_date, offers.end_date,
                                offer_rules.name AS rule_name
                         FROM offers
                         LEFT JOIN offer_rules ON offers.offer_rule_id = offer_rules.id
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
                                'meta'  => h($offer['rule_name'] ?: '—'),
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
                                'meta'  => h($offer['rule_name'] ?: '—'),
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
                                'meta'  => h($offer['rule_name'] ?: '—'),
                            ));
                        }
                    }

                    // ── Global empty state ────────────────────────────────────
                    if ($output_rows === '') {
                        $output_rows = '<div class="pg-row-empty text-muted small">
                            <i class="bi bi-tag"></i>'
                            . lang(array('string' => 'There is no {var:1} right now.', 'vars' => lang('Offer'))) .
                            '</div>';
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
                                $rate_display = number_format((1 / $currency['exchange_rate']), 5);
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
                        $output_rows = '<p class="position-absolute top-50 start-50 translate-middle w-75 text-center">' . lang(array('string' => 'There is no {var:1} right now.', 'vars' => lang('Currency'))) . '</p>';
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
                        $output_rows = '<p class="position-absolute top-50 start-50 translate-middle w-75 text-center">' . lang(array('string' => 'There is no {var:1} right now.', 'vars' => lang('Site Log'))) . '</p>';
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

                    $fm_action = '';

                    if ($fm_can_run) {

                        // send_to is a fixed keyword rather than a URL:
                        // optimize.php turns it into a hard-coded address, so
                        // nothing a visitor puts in the query string can
                        // become a redirect target.
                        $fm_action = '
                        <a class="btn btn-sm btn-outline-success border-0 py-0 px-1 flex-shrink-0 d-flex align-items-center"
                           title="' . lang('Optimize this image') . '"
                           href="optimize.php?id=' . h($fm_item['id']) . get_token_query_string_field() . '&amp;send_to=welcome"><i class="bi bi-fast-forward-circle"></i>' . $fm_percent . '</a>';

                    } elseif ($fm_too_large && (!$fm_item['design'])) {

                        // Already optimized and still heavy, so compression
                        // has nothing left to give and only resizing will.
                        //
                        // Design files are excluded even for administrators:
                        // image_editor_edit.php refuses to save over one at
                        // any role, so the link would open an editor whose
                        // save button always fails. The row still carries the
                        // size warning, it just has nothing to offer.
                        $fm_action = '
                        <a class="btn btn-sm btn-outline-secondary border-0 py-0 px-1 flex-shrink-0 d-flex align-items-center"
                           title="' . lang(array('string' => 'Edit this image with {var:1}', 'vars' => array(lang('Image Editor')))) . '"
                           href="image_editor_edit.php?file_name=' . rawurlencode($fm_item['name']) . '&amp;send_to=' . h(PATH . SOFTWARE_DIRECTORY . '/welcome.php') . '"><i class="bi bi-arrows-angle-contract"></i></a>';
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
                            if (defined('EMAIL_CAMPAIGN_JOB') && EMAIL_CAMPAIGN_JOB) {
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

                            // Set the "to" value based on campaign type
                            switch ($output_campaign['type']) {
                                case 'manual':
                                    $plural_suffix = ($number_of_email_recipients == 0 || $number_of_email_recipients > 1) ? 's' : '';
                                    $output_to = number_format($number_of_email_recipients) . ' Contact' . $plural_suffix;
                                    break;

                                case 'automatic':
                                    $output_to = h(db_value("SELECT email_address FROM email_recipients WHERE email_campaign_id = '" . $output_campaign['id'] . "'"));
                                    break;
                            }

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
                        $output_campaign_rows = '<p class="position-absolute top-50 start-50 translate-middle w-75 text-center">' . lang(array(
                            'string' => 'There is no {var:1} right now.',
                            'vars' => lang('Email Campaign')
                        )) . '</p>';
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

                    if ($waf_rows === '') {
                        $waf_rows = '
                        <div class="d-flex align-items-center justify-content-center text-center py-4">
                            <div>
                                <i class="bi bi-shield-check d-block mb-2" style="font-size:22px;opacity:.35"></i>
                                <p class="text-muted mb-0" style="font-size:12px">' . lang('No firewall events were recorded in this period.') . '</p>
                            </div>
                        </div>';
                    }

                    // The detailed log is administrator-only, so managers are
                    // not shown a link into a screen that would reject them.
                    $waf_footer = '';

                    // The log screen now admits staff roles, so the link does too.
                    if ($user['role'] < 3) {
                        $waf_footer = '
                        <div class="card-footer border-0 bg-reset py-1 text-center">
                            <a href="view_waf_log.php" class="text-decoration-none" style="font-size:11px">'
                            . lang('Firewall Log') . ' <i class="bi bi-arrow-right-short"></i></a>
                        </div>';
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

                    $output_data = '
                        <div class="card-body p-0 d-flex flex-column" style="overflow-x:hidden;overflow-y:auto">
                            ' . $waf_shield . '
                            <div class="d-flex align-items-center justify-content-between px-2 py-2 border-bottom">
                                <span class="badge rounded-pill bg-' . h($waf_mode_class) . '-subtle text-' . h($waf_mode_class) . '-emphasis border border-' . h($waf_mode_class) . '-subtle" style="font-size:10px">
                                    <i class="bi ' . h($waf_mode_icon) . ' me-1"></i>' . h($waf_mode_label) . '
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
                        </div>' . $waf_footer;

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
                    $output_data = '
                        <div class="card-body p-0 d-flex flex-column" style="overflow-x:hidden;overflow-y:auto">
                            <div class="d-flex border-bottom">
                                <div class="flex-fill px-3 py-2">
                                    <div class="fw-semibold text-' . h($td_headline_color) . '" style="font-size:20px;line-height:1">' . number_format($td_headline) . '</div>
                                    <div class="text-muted text-truncate" style="font-size:11px">' . h($td_headline_label) . '</div>
                                </div>
                                <div class="flex-fill px-3 py-2 border-start">
                                    <div class="fw-semibold" style="font-size:20px;line-height:1">' . number_format((int) $td_totals['addresses']) . '</div>
                                    <div class="text-muted text-truncate" style="font-size:11px">' . lang('Addresses') . '</div>
                                </div>
                            </div>
                            <div class="px-3 pt-2 pb-1 text-muted" style="font-size:11px">' . lang('Top sources') . '</div>
                            <div class="px-3 pb-2">' . $td_rows . '</div>
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
        $notifications = array();
        // get number of unreaded notifications (accessable) to show at Notification button.
        $number_of_unread = 0;
        $query = "SELECT action,comment_id,readed FROM notifications WHERE readed = 0";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        while ($row = mysqli_fetch_assoc($result)) {
            $notifications[] = $row;
        }
        if ($notifications) {
            foreach ($notifications as $notification) {
                if ($notification['action'] == 'new_order' || $notification['action'] == 'out_stock') {
                    if (((ECOMMERCE === true) and (($user['role'] < 3) or USER_MANAGE_ECOMMERCE or USER_MANAGE_ECOMMERCE_REPORTS))) {
                        if (USER_MANAGE_ECOMMERCE) {
                            // user is accessable to orders
                            $number_of_unread++;
                        }
                    }
                } elseif ($notification['action'] == 'form_submited') {
                    if ((FORMS === true) && (($user['role'] < 3) || ($user['manage_forms'] == true))) {
                        // user is accessable to forms
                        $number_of_unread++;
                    }
                } elseif ($notification['action'] == 'software_update') {
                    if (($user['role'] < 3)) {
                        $number_of_unread++;
                    }
                } elseif ($notification['action'] == 'new_comment') {
                    // get comment information
                    $query =
                        "SELECT
                            comments.page_id,
                            page.page_folder as page_folder
                        FROM comments
                        LEFT JOIN page ON page.page_id = comments.page_id
                        WHERE comments.id = '" . escape($notification['comment_id']) . "'";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    $row = mysqli_fetch_assoc($result);
                    $folder_id = $row['page_folder'];
                    if (check_edit_access($folder_id) == true) {
                        $number_of_unread++;
                    }
                } else {
                    $number_of_unread++;
                }
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
        $id = $request['id'];
        $do_action = $request['do_action'];

        validate_token();
        $user = validate_user();

        $query = "SELECT action FROM notifications WHERE id = '" . escape($id) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);

        // check if user has access.
        if ($row['action'] == 'new_order' || $row['action'] == 'out_stock') {
            if (((ECOMMERCE === true) and (($user['role'] < 3) or USER_MANAGE_ECOMMERCE or USER_MANAGE_ECOMMERCE_REPORTS))) {
                if (USER_MANAGE_ECOMMERCE) {
                    if ($do_action === 'remove') {
                        //delete requested notifications
                        $query = "DELETE FROM notifications WHERE id = '" . escape($id) . "'";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    } elseif ($do_action == 'mark_unread') {
                        //mark unreaded requested notifications
                        $query = "UPDATE notifications SET readed = '" . escape(0) . "' WHERE id = '" . escape($id) . "'";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    }
                }
            }
        } elseif ($row['action'] == 'form_submited') {
            if ((FORMS === true) && (($user['role'] < 3) || ($user['manage_forms'] == true))) {
                if ($do_action === 'remove') {
                    //delete requested notifications
                    $query = "DELETE FROM notifications WHERE id = '" . escape($id) . "'";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                } elseif ($do_action == 'mark_unread') {
                    //mark unreaded requested notifications
                    $query = "UPDATE notifications SET readed = '" . escape(0) . "' WHERE id = '" . escape($id) . "'";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                }
            }
        } elseif ($row['action'] == 'software_update') {
            if (($user['role'] < 3)) {
                if ($do_action === 'remove') {
                    //delete requested notifications
                    $query = "DELETE FROM notifications WHERE id = '" . escape($id) . "'";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                } elseif ($do_action == 'mark_unread') {
                    //mark unreaded requested notifications
                    $query = "UPDATE notifications SET readed = '" . escape(0) . "' WHERE id = '" . escape($id) . "'";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                }
            }
        } elseif ($row['action'] == 'new_comment') {
            if ($do_action == 'remove') {
                //delete requested notifications
                $query = "DELETE FROM notifications WHERE id = '" . escape($id) . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            } elseif ($do_action == 'mark_unread') {
                //mark unreaded requested notifications
                $query = "UPDATE notifications SET readed = '" . escape(0) . "' WHERE id = '" . escape($id) . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            }
        } else {

            if ($do_action === 'remove') {
                //delete requested notifications
                $query = "DELETE FROM notifications WHERE id = '" . escape($id) . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            } elseif ($do_action == 'mark_unread') {
                //mark unreaded requested notifications
                $query = "UPDATE notifications SET readed = '" . escape(0) . "' WHERE id = '" . escape($id) . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
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
        $read_mark = $request['read_mark'];

        validate_token();
        $user = validate_user();
        $notifications = array();
        // get all notifications
        $query = "SELECT * FROM notifications ORDER BY timestamp DESC";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        while ($row = mysqli_fetch_assoc($result)) {
            $notifications[] = $row;
        }
        foreach ($notifications as $notification) {

            if ($notification['action'] == 'new_order') {
                if (((ECOMMERCE === true) and (($user['role'] < 3) or USER_MANAGE_ECOMMERCE or USER_MANAGE_ECOMMERCE_REPORTS))) {
                    if (USER_MANAGE_ECOMMERCE) {
                        // user is accessable to orders
                        $NotificationArray = array(
                            'id' => $notification['id'],
                            'type' => $notification['type'],
                            'title' => lang('Congratulations! There is a new successful order.'),
                            'description' => '',
                            'details' => lang('Order Number') . ': #' . $notification['title'] . '<br/>' . lang('Total') . ':' . $notification['order_total'],
                            'user' => $notification['user'],
                            'readed' => $notification['readed'],
                            'url' => 'view_order.php?id=' . $notification['order_id'],
                            'action' => $notification['action'],
                            'time' => get_relative_time(array('timestamp' => $notification['timestamp']))
                        );
                        array_push($array, $NotificationArray);
                        $NotificationArray = '';
                        if ($read_mark == true) {
                            // we mark readed all notifications we can access.
                            $query = "UPDATE notifications SET readed = '" . escape(1) . "' WHERE id = '" . escape($notification['id']) . "'";
                            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                        }
                    }
                }
            } elseif ($notification['action'] == 'out_stock') {
                if (((ECOMMERCE === true) and (($user['role'] < 3) or USER_MANAGE_ECOMMERCE or USER_MANAGE_ECOMMERCE_REPORTS))) {
                    if (USER_MANAGE_ECOMMERCE) {
                        // user is accessable to orders
                        $NotificationArray = array(
                            'id' => $notification['id'],
                            'type' => $notification['type'],
                            'title' => lang('A product out of stock by purchased.'),
                            'description' => '',
                            'details' => $notification['title'],
                            'user' => $notification['user'],
                            'readed' => $notification['readed'],
                            'url' => 'edit_product.php?id=' . $notification['product_id'],
                            'action' => $notification['action'],
                            'time' => get_relative_time(array('timestamp' => $notification['timestamp']))
                        );
                        array_push($array, $NotificationArray);
                        $NotificationArray = '';
                        if ($read_mark == true) {
                            // we mark readed all notifications we can access.
                            $query = "UPDATE notifications SET readed = '" . escape(1) . "' WHERE id = '" . escape($notification['id']) . "'";
                            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                        }
                    }
                }
            } elseif ($notification['action'] == 'form_submited') {
                if ((FORMS === true) && (($user['role'] < 3) || ($user['manage_forms'] == true))) {
                    // user is accessable to forms
                    $NotificationArray = array(
                        'id' => $notification['id'],
                        'type' => $notification['type'],
                        'title' => lang('A custom form was submitted.'),
                        'description' => '',
                        'details' => lang('Reference Code') . ':' . $notification['title'],
                        'user' => $notification['user'],
                        'readed' => $notification['readed'],
                        'url' => 'edit_submitted_form.php?id=' . $notification['form_id'],
                        'action' => $notification['action'],
                        'time' => get_relative_time(array('timestamp' => $notification['timestamp']))
                    );
                    array_push($array, $NotificationArray);
                    $NotificationArray = '';
                    if ($read_mark == true) {
                        // we mark readed all notifications we can access.
                        $query = "UPDATE notifications SET readed = '" . escape(1) . "' WHERE id = '" . escape($notification['id']) . "'";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    }

                }
            } elseif ($notification['action'] == 'software_update') {
                if (($user['role'] < 3)) {
                    $NotificationArray = array(
                        'id' => $notification['id'],
                        'type' => $notification['type'],
                        'title' => lang('Software update available'),
                        'description' => lang('A new security and development update is available for your software.'),
                        'details' => '',
                        'user' => $notification['user'],
                        'readed' => $notification['readed'],
                        'url' => 'software_update.php',
                        'action' => $notification['action'],
                        'time' => get_relative_time(array('timestamp' => $notification['timestamp']))
                    );

                    array_push($array, $NotificationArray);
                    $NotificationArray = '';
                    if ($read_mark == true) {
                        // we mark readed all notifications we can access.
                        $query = "UPDATE notifications SET readed = '" . escape(1) . "' WHERE id = '" . escape($notification['id']) . "'";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    }
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                }
            } elseif ($notification['action'] == 'new_comment') {

                $folder_id = '';
                // get comment information
                $query =
                    "SELECT
                        comments.id as id,
                        comments.page_id,
                        page.page_type,
                        page.page_folder
                    FROM comments
                    LEFT JOIN page ON page.page_id = comments.page_id
                    WHERE comments.id = '" . escape($notification['comment_id']) . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                $row = mysqli_fetch_assoc($result);
                $folder_id = $row['page_folder'];
                $page_id = $row['page_id'];
                $comment_id = $row['id'];
                if (check_edit_access($folder_id) == true) {


                    // get comment label from page
                    $query =
                        "SELECT
                            page_id,
                            page.comments_label as comments_label
                        FROM page
                        WHERE page.page_id = '" . escape($page_id) . "'";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    $row = mysqli_fetch_assoc($result);
                    $comments_label = $row['comments_label'];

                    // user is accessable to comment
                    $NotificationArray = array(
                        'id' => $notification['id'],
                        'type' => $notification['type'],
                        'title' => lang(array('string' => 'There is a new {var:1} exist.', 'vars' => array($comments_label))),
                        'description' => $comments_label . ': ' . $notification['title'],
                        'details' => '',
                        'user' => $notification['user'],
                        'readed' => $notification['readed'],
                        'url' => 'edit_comment.php?id=' . $comment_id,
                        'action' => $notification['action'],
                        'time' => get_relative_time(array('timestamp' => $notification['timestamp']))
                    );
                    array_push($array, $NotificationArray);
                    $NotificationArray = '';
                    if ($read_mark == true) {
                        // we mark readed all notifications we can access.
                        $query = "UPDATE notifications SET readed = '" . escape(1) . "' WHERE id = '" . escape($notification['id']) . "'";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    }
                }
            } else {
                $NotificationArray = array(
                    'id' => $notification['id'],
                    'type' => $notification['type'],
                    'title' => $notification['title'],
                    'description' => '',
                    'details' => '',
                    'user' => $notification['user'],
                    'readed' => $notification['readed'],
                    'url' => '#!',
                    'action' => 'custom',
                    'time' => get_relative_time(array('timestamp' => $notification['timestamp']))
                );
                array_push($array, $NotificationArray);
                $NotificationArray = '';
                if ($read_mark == true) {
                    // we mark readed all notifications we can access.
                    $query = "UPDATE notifications SET readed = '" . escape(1) . "' WHERE id = '" . escape($notification['id']) . "'";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                }
            }

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

        // returned in the response below; this branch never fills it
        $array = array();
        // get all notifications
        $query = "SELECT * FROM notifications ORDER BY timestamp DESC";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        // stays empty when there are no notifications
        $notifications = array();

        while ($row = mysqli_fetch_assoc($result)) {
            $notifications[] = $row;
        }
        $i = 0;
        foreach ($notifications as $notification) {

            if ($notification['action'] == 'new_order' || $notification['action'] == 'out_stock') {
                if (((ECOMMERCE === true) and (($user['role'] < 3) or USER_MANAGE_ECOMMERCE or USER_MANAGE_ECOMMERCE_REPORTS))) {
                    if (USER_MANAGE_ECOMMERCE) {
                        // user is accessable to orders

                        // we remove all notifications we can access.
                        $query = "DELETE FROM notifications WHERE id = '" . escape($notification['id']) . "'";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                        $i++;
                    }
                }
            } elseif ($notification['action'] == 'form_submited') {
                if ((FORMS === true) && (($user['role'] < 3) || ($user['manage_forms'] == true))) {
                    // user is accessable to forms

                    // we remove all notifications we can access.
                    $query = "DELETE FROM notifications WHERE id = '" . escape($notification['id']) . "'";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    $i++;
                }
            } elseif ($notification['action'] == 'software_update') {
                if (($user['role'] < 3)) {
                    // we remove all notifications we can access.
                    $query = "DELETE FROM notifications WHERE id = '" . escape($notification['id']) . "'";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    $i++;
                }
            } elseif ($notification['action'] == 'new_comment') {

                // get comment information
                $query =
                    "SELECT
                        comments.page_id,
                        page.page_type,
                        page.page_folder
                    FROM comments
                    LEFT JOIN page ON page.page_id = comments.page_id
                    WHERE comments.id = '" . escape($notification['comment_id']) . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                $row = mysqli_fetch_assoc($result);
                $folder_id = $row['page_folder'];
                if (check_edit_access($folder_id) == true) {

                    // we remove all notifications we can access.
                    $query = "DELETE FROM notifications WHERE id = '" . escape($notification['id']) . "'";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    $i++;
                }

            } else {
                // we remove all notifications we can access.
                $query = "DELETE FROM notifications WHERE id = '" . escape($notification['id']) . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
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

    case 'user_pinned_app_update':
        $list = $request['list'];
        $list = implode(',', $list);
        $query =
            "UPDATE user
            SET selected_appmenu_items_array = '" . $list . "'
            WHERE user_username = '" . escape($_SESSION['sessionusername']) . "'";
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
        $file_name = $request['name'];
        if (isset($request['folder'])) {
            $folder = $request['folder'];
        } else {
            $folder = ($_SESSION['software']['explorer']['folder']['folder_id'] ?? '');
        }

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
        $message_text = $request['message_text'];
        $widgets = $request['widgets'];
        $widgets = implode(',', $widgets);
        $query =
            "UPDATE dashboard
            SET
            order_widgets = '$widgets'
        ";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        //return success json output
        $response = array(
            'status' => 'success',
            'message' => 'widget ' . $message_text . ' process successful.'
        );
        echo encode_json($response);
        exit();
        break;


    case 'software_backup':
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
                    if (!file_exists($backup_location . $backup_folder_name . '/*')) {
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
                    'message' => 'Layouts copied to backup directory. Creating .htaccess for security reason, please wait...'
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

        // This feature can take a long time to run for a large site,
        // so increase the allowed execution time for the PHP script.
        ini_set('max_execution_time', '9999');

        $step = $request['step'];
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
                $request['uname'] = php_uname();
                $request['os'] = PHP_OS;
                $request['web_server'] = $_SERVER['SERVER_SOFTWARE'];
                $request['php_version'] = phpversion();
                $request['mysql_version'] = db("SELECT VERSION()");
                $request['installer'] = INSTALLER;
                $request['private_label'] = PRIVATE_LABEL;
                $data = encode_json($request);
                $API = '59593DS72233483322T669223344';
                $REQUEST = 'latest_version';

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
                $ch = curl_init("https://www.kodpen.com/pinegrap_software_update.zip");
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
                $filename = 'pinegrap_software_update.zip';
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
                // Zip file name
                $filename = 'pinegrap_software_update.zip';
                // Unzip path
                $path = _PATH . "/../";

                // pg_extract_archive() checks archive consistency BEFORE
                // touching anything, then proves every entry actually landed
                // on disk afterwards.
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
                        . ($extract['missing'] ? ' Missing: ' . implode(', ', array_slice($extract['missing'], 0, 10)) : ''));

                    $response = array(
                        'status' => 'error',
                        'message' => $extract['message']
                    );
                    echo encode_json($response);
                    exit();
                }

                unlink($filename);

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
                    require_once('assets/iyzipay-php/IyzipayBootstrap.php');
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
        require_once(dirname(__FILE__) . '/assets/iyzipay-php/IyzipayBootstrap.php');
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
            'eo_errors_notices'         => 'Hata / Bildirim mesajları (backend doldurur)',
            'eo_cart_items'             => 'Sepet Öğeleri (backend doldurur — tablo + miktar girişleri)',
            'eo_shipping'               => 'Teslimat Adresi & Kargo (backend doldurur)',
            'eo_billing'                => 'Fatura Bilgileri — tüm blok (backend doldurur)',
            'eo_billing_country_select' => 'Ülke Listesi (backend doldurur — 240 ülke)',
            'eo_payment'                => 'Ödeme bloğu — tüm blok (backend doldurur)',
            'eo_payment_methods'        => 'Ödeme Yöntemleri (radyolar — site ayarına göre)',
            'eo_installment'            => 'Taksit Seçici (Iyzipay BIN sorgusu)',
            'eo_terms'                  => 'Koşullar Onayı (legacy — yeni tree gerçek checkbox + modal kullanır)',
            'eo_saved_cart_link'        => 'Sepetim Kayıt Linki (visitor sipariş # ile sepete geri dönebilir)',
            'eo_upsell_offers'          => 'Upsell Teklifleri (view_offers.php — alert şeridi)',
            'eo_applied_offers'         => 'Uygulanan Teklifler (sepete uygulanan promosyonlar)',
            'eo_totals'                 => 'Sipariş Toplamları (backend hesaplar — KDV, kart sürşarjı, toplam)',
            'eo_submit_button'          => 'Siparişi Tamamla Butonu',
            'eo_update_button'          => 'Güncelle Butonu (sepet adetleri için)',
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
        $actions[] = array('label' => lang('Folders'), 'icon' => 'bi-folder2', 'url' => $base_url . '/view_folders.php', 'keys' => array('klasor', 'klasör', 'folder', 'fol', 'kla'));
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
            $actions[] = array('label' => lang('Settings'), 'icon' => 'bi-gear', 'url' => $base_url . '/settings.php', 'keys' => array('ayar', 'ayarlar', 'setting', 'settings', 'set', 'aya'));
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
                 WHERE page_type = 'custom form'
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

        if ($request['type'] != '') {
            $sql_where .= "WHERE (style_type = '" . escape($request['type']) . "')";
        }

        if ($request['search'] != '') {
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


    case 'file_explorer':
        $user = validate_user();
        validate_token();
        validate_area_access($user, 'user');


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

        if (check_view_access($folder_id) == false) {
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
        $expanded_folders = explode(',', $_COOKIE['software']['view_folders']['expanded_folders']);

        // if user is a basic user, then get folders that user has access to
        if ($user['role'] == 3) {
            $folders_that_user_has_access_to = get_folders_that_user_has_access_to($user['id']);
        }

        switch ($request['type']) {
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
                                    <th class="noVis">Name</th>
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
    case 'shared_component':
        validate_token();
        $user = validate_user();

        // Only admin (role 0) and designer (role 2) may manage shared components.
        if ($user['role'] != 0 && $user['role'] != 2) {
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

        $sub = isset($request['sub_action']) ? $request['sub_action'] : '';

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

                // Scan every non-empty tree_json once; for each shared id found,
                // append this style's display name to its usage list.
                $style_rows = db_items(
                    "SELECT style_id, style_name, style_tree_json
                     FROM style
                     WHERE style_tree_json IS NOT NULL AND style_tree_json != ''"
                );
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
                                    'style_id'   => (int)$sr['style_id'],
                                    'style_name' => $sr['style_name'],
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
                            $sc_cfg_sql, '" . (int)$user['user_id'] . "', '$sc_now', '$sc_now')");
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
                     WHERE page.page_type = 'custom form'
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
                        $sc_cl_likes[] = "style.style_tree_json LIKE '%\"sharedId\":" . $sc_cl_cid . ",%'"
                                       . " OR style.style_tree_json LIKE '%\"sharedId\":" . $sc_cl_cid . "}%'";
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
                        $sc_cd_likes[] = "style.style_tree_json LIKE '%\"sharedId\":" . $sc_cd_cid . ",%'"
                                       . " OR style.style_tree_json LIKE '%\"sharedId\":" . $sc_cd_cid . "}%'";
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
                        $sc_sc_likes[] = "style.style_tree_json LIKE '%\"sharedId\":" . $sc_sc_cid . ",%'"
                                       . " OR style.style_tree_json LIKE '%\"sharedId\":" . $sc_sc_cid . "}%'";
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

        // Admin (0) and designer (2) only — symmetric with shared_component.
        if ($user['role'] != 0 && $user['role'] != 2) {
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
                // designer (2) — basic users in folder ACLs shouldn't be able
                // to upload "design files" they cannot manage. Manager (1)
                // never reaches this endpoint via the assets panel anyway
                // because they don't have designer-area access.
                $df_design = !empty($request['design']) ? 1 : 0;
                if ((int)$user['role'] !== 0 && (int)$user['role'] !== 2) {
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
                    $df_acl = get_folders_that_user_has_access_to($user['user_id']);
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
                        '" . (int)$user['user_id'] . "',
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
                    $df_acl = get_folders_that_user_has_access_to($user['user_id']);
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
    $class = 'pg-row' . ($small ? ' pg-row-sm' : '') . ($muted ? ' opacity-50' : '');

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
    if (defined('API_USERNAME')) {
        return true;
    }

    // If the token does not exist in the session,
    // or the passed token does not match the token from the session,
    // then this might be a CSRF attack so respond with an error.
    if (
        ($_SESSION['software']['token'] == '')
        || ($token != $_SESSION['software']['token'])
    ) {
        respond(array(
            'status' => 'error',
            'message' => 'Invalid token.'
        ));
    }
}