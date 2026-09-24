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
 */

/*
 * The counter sale. The page is drawn once; after that every action (a scan,
 * a quantity, the customer, completing the sale) is a POST to this same file
 * that answers in JSON with the parts of the screen it changed - no page is
 * reloaded between two scans. A POST without the X-Requested-With header
 * still works the old way (redirect back), so a form left over from an
 * earlier screen does not break.
 *
 * The actions and the HTML they return live in includes/local_sale.php.
 */

include('init.php');
include_once('liveform.class.php');

$request = isset($_GET['request']) ? (string) $_GET['request'] : '';
$ajax = ($request !== '') || (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest');

/**
 * Send one JSON answer and stop.
 *
 * @param array $data
 * @param int   $status
 */
function add_order_json($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    header('X-Robots-Tag: noindex');
    echo json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    exit();
}

// A script waiting for JSON gets its refusal in JSON, not the login page.
if ($ajax) {
    if (!USER_LOGGED_IN) {
        add_order_json(array('ok' => false, 'code' => 'session', 'message' => lang('Your session has expired. Reload the page and sign in again.')), 401);
    }
    // Same gate as the screen (validate_ecommerce_access): roles above user
    // pass, a user needs the manage e-commerce right.
    if (!USER_MANAGE_ECOMMERCE) {
        log_activity(lang('access denied to commerce'), $_SESSION['sessionusername'] ?? '');
        add_order_json(array('ok' => false, 'code' => 'denied', 'message' => lang('Access denied.')), 403);
    }
}

$liveform = new liveform('add_order');
$user = validate_user();
validate_ecommerce_access($user);

require_once(PG_FUNCTIONS_DIR . '/includes/local_sale.php');

/* ---------------------------------------------------------
   GET: product search  (?request=search_products&q=...)
   short_description is the product's name; name is the SKU. Both are
   searched, the list is ordered by the name people know.
   --------------------------------------------------------- */
if ($request === 'search_products') {
    $q = trim((string) ($_GET['q'] ?? ''));
    if ($q === '') {
        add_order_json(array('results' => array()));
    }
    $like = escape(escape_like(mb_substr($q, 0, 100)));
    $rows = db_items(
        "SELECT id, name, short_description, inventory, inventory_quantity, price, taxable, tax_rate
         FROM products
         WHERE enabled = '1'
           AND (name LIKE '%" . $like . "%' OR short_description LIKE '%" . $like . "%')
         ORDER BY short_description, name
         LIMIT 10"
    );
    $results = array();
    foreach ((array) $rows as $row) {
        $rate = local_sale_line_rate($row);
        $results[] = array(
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'short_description' => (string) $row['short_description'],
            'inventory' => (int) $row['inventory'],
            'inventory_quantity' => (int) $row['inventory_quantity'],
            'price_text' => local_sale_money_text(local_sale_prices_net() ? (int) $row['price'] : (int) round((int) $row['price'] * (100 + $rate) / 100)),
        );
    }
    add_order_json(array('results' => $results));
}

/* ---------------------------------------------------------
   GET: customer search  (?request=search_customers&q=...)
   Contacts by name, company or e-mail, each with the ERP account it is
   linked to; and, with the ERP on, accounts that have no contact of their
   own. The sale is billed to whichever is picked.
   --------------------------------------------------------- */
if ($request === 'search_customers') {
    $q = trim((string) ($_GET['q'] ?? ''));
    if ($q === '') {
        add_order_json(array('results' => array()));
    }
    $like = escape(escape_like(mb_substr($q, 0, 100)));
    $erp_tables = local_sale_erp_on();
    $results = array();

    $contacts = db_items(
        "SELECT c.id, c.first_name, c.last_name, c.company, c.email_address, c.business_state"
        . ($erp_tables ? ", (SELECT a.id FROM erp_accounts a WHERE a.contact_id = c.id ORDER BY a.id ASC LIMIT 1) AS account_id" : ", 0 AS account_id") . "
         FROM contacts c
         WHERE c.first_name LIKE '%" . $like . "%'
            OR c.last_name LIKE '%" . $like . "%'
            OR CONCAT(c.first_name, ' ', c.last_name) LIKE '%" . $like . "%'
            OR c.company LIKE '%" . $like . "%'
            OR c.email_address LIKE '%" . $like . "%'
         ORDER BY c.last_name, c.first_name
         LIMIT 10"
    );
    foreach ((array) $contacts as $row) {
        $person = trim(trim((string) $row['first_name']) . ' ' . trim((string) $row['last_name']));
        $company = trim((string) $row['company']);
        $results[] = array(
            'kind' => 'contact',
            'contact_id' => (int) $row['id'],
            'account_id' => (int) $row['account_id'],
            'label' => ($person !== '') ? $person : (($company !== '') ? $company : ('#' . (int) $row['id'])),
            'sub' => trim(implode(' · ', array_filter(array(($person !== '') ? $company : '', trim((string) $row['email_address']), trim((string) $row['business_state']))))),
        );
    }

    if ($erp_tables) {
        $accounts = db_items(
            "SELECT id, title, email, city, tax_number
             FROM erp_accounts
             WHERE contact_id = 0 AND status = 'active' AND kind IN ('customer', 'both')
               AND (title LIKE '%" . $like . "%' OR email LIKE '%" . $like . "%' OR tax_number LIKE '%" . $like . "%')
             ORDER BY title
             LIMIT 10"
        );
        foreach ((array) $accounts as $row) {
            $results[] = array(
                'kind' => 'account',
                'contact_id' => 0,
                'account_id' => (int) $row['id'],
                'label' => (string) $row['title'],
                'sub' => trim(implode(' · ', array_filter(array(lang('Ledger account'), trim((string) $row['city']), trim((string) $row['email']))))),
            );
        }
    }

    add_order_json(array('results' => $results));
}

/* ---------------------------------------------------------
   GET: the whole screen state, and the recent sales drawer
   --------------------------------------------------------- */
if ($request === 'state') {
    add_order_json(local_sale_payload($user, local_sale_ok(), true));
}

if ($request === 'recent') {
    add_order_json(array('ok' => true, 'html' => local_sale_render_recent($user)));
}

/* ---------------------------------------------------------
   POST: the actions
   --------------------------------------------------------- */
$action = isset($_POST['action']) ? trim((string) $_POST['action']) : '';

if ($action !== '') {
    // Every action changes the cart or completes an order, so the token is
    // checked once, before the dispatch. validate_token_field() answers
    // with an HTML page, so the JSON caller gets the same check by hand.
    if ($ajax) {
        $session_token = (string) ($_SESSION['software']['token'] ?? '');
        $post_token = (isset($_POST['token']) && is_string($_POST['token'])) ? $_POST['token'] : '';
        if (($session_token === '') || !hash_equals($session_token, $post_token)) {
            add_order_json(array('ok' => false, 'code' => 'session', 'message' => lang('Your session has expired. Reload the page and sign in again.')), 403);
        }
    } else {
        validate_token_field();
    }

    $item_id = (int) ($_POST['item_id'] ?? 0);

    switch ($action) {
        case 'add_item':
        case 'add_to_cart':
            $res = local_sale_add_item((string) ($_POST['barcode'] ?? ''), (int) ($_POST['product_id'] ?? 0), (int) ($_POST['quantity'] ?? 1));
            break;

        case 'set_qty':
            $res = local_sale_set_qty($item_id, (int) ($_POST['quantity'] ?? 0));
            break;

        // The screen before this one sent +1 / -1; turned into the absolute
        // figure set_qty takes.
        case 'increase_qty':
        case 'decrease_qty':
            $current = (int) db_value("SELECT quantity FROM order_items WHERE id = '" . $item_id . "' AND order_id = '" . local_sale_order_id() . "' LIMIT 1");
            $res = local_sale_set_qty($item_id, $current + (($action === 'increase_qty') ? 1 : -1));
            break;

        case 'remove_item':
            $res = local_sale_set_qty($item_id, 0);
            break;

        case 'clear_cart':
            $res = local_sale_clear_cart();
            break;

        case 'set_customer':
            $res = local_sale_set_customer((int) ($_POST['contact_id'] ?? 0), (int) ($_POST['account_id'] ?? 0));
            break;

        case 'clear_customer':
            $res = local_sale_clear_customer();
            break;

        case 'quick_account':
            $res = local_sale_quick_account($user, $_POST);
            break;

        case 'complete':
        case 'complete_order':
            $res = local_sale_complete($user, array(
                'pay_method' => (string) ($_POST['pay_method'] ?? ''),
                'pay_till' => (int) ($_POST['pay_till'] ?? 0),
                'received' => (int) ($_POST['received'] ?? 0),
                'pay_parts' => (string) ($_POST['pay_parts'] ?? ''),
            ));
            break;

        case 'issue_invoice':
            $res = local_sale_issue_invoice($user, (int) ($_POST['order_id'] ?? 0));
            break;

        default:
            $res = local_sale_fail(lang('Invalid action.'));
    }

    if ($ajax) {
        $payload = local_sale_payload($user, $res, in_array($action, array('set_customer', 'clear_customer', 'quick_account', 'complete', 'complete_order'), true));
        if ($action === 'issue_invoice') {
            $payload['recent'] = local_sale_render_recent($user);
        }
        add_order_json($payload);
    }

    // Without the script: back to the screen, or to the order once it is done.
    if (!$res['ok'] && ($res['message'] !== '')) {
        $liveform->mark_error('error', $res['message']);
    }

    if (!empty($res['result'])) {
        $liveform_order = new liveform('view_order');
        if (!empty($res['result']['invoice'])) {
            $liveform_order->add_notice(lang(array('string' => 'Invoice {var:1} created.', 'vars' => array($res['result']['invoice']['number']))));
        }
        foreach ($res['result']['warnings'] as $warning) {
            $liveform_order->mark_error('_error', $warning);
        }
        header('Location: ' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_order.php?id=' . (int) $res['result']['order_id']);
        exit();
    }

    header('Location: ' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_order.php');
    exit();
}

/* ---------------------------------------------------------
   GET: the page
   --------------------------------------------------------- */
$order_id = local_sale_order_id();
$lines = local_sale_lines($order_id);
$totals = local_sale_totals($lines);
$erp_on = local_sale_erp_on();
$can_erp = local_sale_can_erp($user);
$page_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_order.php';

// The operator who holds the ERP right starts in the document view; everyone
// else only has the till view. The choice is remembered in the browser.
$default_mode = $can_erp ? 'belge' : 'kasa';

$methods = local_sale_methods();
$config = array(
    'url' => $page_url,
    'token' => (string) ($_SESSION['software']['token'] ?? ''),
    'symbol' => html_entity_decode(BASE_CURRENCY_SYMBOL, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
    // How local_sale_money() writes amounts, for the change and the quick
    // amounts the script formats itself.
    'decimal' => local_sale_number_separators()['decimal'],
    'thousands' => local_sale_number_separators()['thousands'],
    'erp' => $erp_on,
    'canErp' => $can_erp,
    'defaultMode' => $default_mode,
    'total' => (int) $totals['gross'],
    'drawerUrl' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/get_erp_drawer.php',
    'methods' => $methods,
    'i18n' => array(
        'ready' => lang('Scanner ready — scan wherever the cursor is'),
        'working' => lang('Working…'),
        'noProducts' => lang('No products found.'),
        'noProductsEnter' => lang('No product found. Enter reads the text as a barcode.'),
        'inStock' => lang('In stock: {var:1}'),
        'outOfStock' => lang('Out of stock'),
        'vatIncluded' => local_sale_till_price_label(),
        'noCustomers' => lang('No customers found.'),
        'customerPlaceholder' => $erp_on ? lang('Name, company, e-mail or account') : lang('Name, company or e-mail'),
        'newAccount' => lang('Open a new account: “{var:1}”'),
        'newAccountEmpty' => lang('Open a new account'),
        'cancel' => lang('Cancel'),
        'change' => lang('Change due'),
        'short' => lang('Short'),
        'exact' => lang('Exact'),
        'complete' => lang('Complete the sale'),
        'completing' => lang('Completing…'),
        'cartTotal' => lang('Cart total: {var:1}'),
        'badAnswer' => lang('The server sent an answer the screen cannot read. The cart was reloaded; check it and try again.'),
        'offline' => lang('The connection dropped. The cart was reloaded; check it and try again.'),
        'session' => lang('Your session has expired. Reload the page and sign in again.'),
        'reload' => lang('Reload'),
        'searchFailed' => lang('The search did not answer. Try again.'),
        'loadFailed' => lang('The details could not be loaded.'),
        'splitRest' => lang('{var:1} left on the account'),
        'splitPaid' => lang('The parts cover the total.'),
        'splitOver' => lang('The parts add up to more than the total.'),
        'splitEmpty' => lang('Enter the amount of at least one part.'),
    ),
);

$output_can_settings = $can_erp && (((int) $user['role'] < 3) || !empty($user['manage_erp_settings']));

// The account drawer of the ERP lists, reused for "Account summary".
if ($can_erp) {
    require_once(PG_FUNCTIONS_DIR . '/includes/erp/drawer.php');
}

echo
    pg_page_shell(
        array(
            'title'         => lang('Add Local Order'),
            'extra classes' => 'product',
            'icon'          => 'store',
            'heading'       => lang('Add Local Order'),
            'heading_description' => lang('Scan a barcode or search for a product; complete the sale in one step.'),
            'cancel' => array('enable' => 'true', 'url' => 'view_orders.php'),
            'breadcrumb' => array(
                array('label' => lang('Orders'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_orders.php'),
                array('label' => lang('Add Local Order')),
            ),
        )
    ) . '

    <main class="container-fluid mb-5" id="content">
        ' . $liveform->get_messages() . '

        <div class="pg-sale" id="pg_sale" data-mode="' . $default_mode . '">
            <script>
                // The view is picked before the first paint, so the page does
                // not flash the other view and then switch.
                (function () {
                    var root = document.getElementById("pg_sale");
                    try {
                        var saved = window.localStorage.getItem("pg_sale_mode");
                        if ((saved === "kasa") || ((saved === "belge") && ' . ($can_erp ? 'true' : 'false') . ')) {
                            root.setAttribute("data-mode", saved);
                        }
                    } catch (e) {}
                })();
            </script>

            <div class="pg-toolbar" id="button_bar">
                <button type="button" class="btn btn-sm btn-ghost no-submit" id="pg_sale_recent_btn"><i class="bi bi-clock-history" aria-hidden="true"></i><span class="pg-sale-lbl">' . lang('Recent sales') . '</span></button>
                <button type="button" class="btn btn-sm btn-ghost no-submit" id="pg_sale_clear_btn"' . (($totals['count'] > 0) ? '' : ' disabled') . '><i class="bi bi-cart-x" aria-hidden="true"></i><span class="pg-sale-lbl">' . lang('Empty the cart') . '</span></button>
                <div class="pg-toolbar-grow small text-body-secondary text-truncate" id="pg_sale_info"></div>'
                . ($can_erp ? '
                <div class="pg-sale-switch" role="group" aria-label="' . h(lang('View')) . '">
                    <button type="button" class="btn btn-sm btn-ghost no-submit" data-sale-mode="kasa" title="' . h(lang('Till view: prices with VAT, change, large controls')) . '"><i class="bi bi-shop" aria-hidden="true"></i><span class="pg-sale-lbl">' . lang('Till') . '</span></button>
                    <button type="button" class="btn btn-sm btn-ghost no-submit" data-sale-mode="belge" title="' . h(lang('Document view: VAT breakdown, account and due date')) . '"><i class="bi bi-journal-text" aria-hidden="true"></i><span class="pg-sale-lbl">' . lang('Document') . '</span></button>
                </div>' : '') . '
                <div class="dropdown">
                    <button type="button" class="btn btn-sm btn-ghost no-submit" data-bs-toggle="dropdown" aria-expanded="false" aria-label="' . h(lang('More')) . '"><i class="bi bi-three-dots-vertical" aria-hidden="true"></i></button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><button type="button" class="dropdown-item link-body-emphasis no-submit" data-sale-keys><i class="bi bi-keyboard me-2" aria-hidden="true"></i>' . lang('Keyboard shortcuts') . '</button></li>
                        <li><button type="button" class="dropdown-item link-body-emphasis d-flex align-items-center no-submit" data-sale-sound><i class="bi bi-volume-up me-2" aria-hidden="true"></i>' . lang('Error sound') . '<i class="bi bi-check2 ms-auto ps-3" id="pg_sale_sound_tick" aria-hidden="true"></i></button></li>'
                        . ($output_can_settings ? '
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item link-body-emphasis" href="' . h(pg_settings_link('commerce', 'pgset-erp')) . '"><i class="bi bi-gear me-2" aria-hidden="true"></i>' . lang('Sales settings (ERP card)') . '</a></li>' : '') . '
                    </ul>
                </div>
            </div>

            <div class="row g-3">

                <section class="col-12 col-lg-8 col-xxl-9 order-1" aria-label="' . h(lang('Sale')) . '">
                    <div class="card pg-sale-entry">
                        <div class="card-body">
                            <div class="pg-sale-field">
                                <label class="form-label" for="pg_sale_product">' . lang('Product') . '</label>
                                <div class="pg-sale-ig form-control" id="pg_sale_product_group">
                                    <span class="pg-sale-ig-icon" aria-hidden="true"><i class="bi bi-upc-scan"></i></span>
                                    <input type="text" id="pg_sale_product" autocomplete="off" spellcheck="false"
                                        role="combobox" aria-expanded="false" aria-controls="pg_sale_results" aria-autocomplete="list"
                                        aria-describedby="pg_sale_product_foot"
                                        placeholder="' . h(lang('Scan a barcode or search for a product')) . '">
                                    <label class="pg-sale-mult" title="' . h(lang('Quantity for the next product')) . '"><span aria-hidden="true">×</span><input type="number" id="pg_sale_qty" min="1" max="9999" value="1" aria-label="' . h(lang('Quantity for the next product')) . '"></label>
                                    <button type="button" class="btn btn-primary no-submit" id="pg_sale_add">' . lang('Add') . '</button>
                                </div>
                                <div class="pg-sale-foot" id="pg_sale_product_foot"></div>
                                <div class="pg-sale-results" id="pg_sale_results" role="listbox" aria-label="' . h(lang('Products')) . '" hidden></div>
                            </div>
                            <div class="pg-sale-field">
                                <span class="form-label" id="pg_sale_customer_label">' . lang('Customer') . '</span>
                                <div id="pg_sale_customer" aria-labelledby="pg_sale_customer_label">' . local_sale_render_customer($user) . '</div>
                            </div>
                        </div>
                    </div>

                    <div class="card pg-sale-cart mt-3">
                        <div class="card-header">
                            <h2 class="pg-sale-ch text-primary">' . lang('Cart') . '</h2>
                            <span class="small text-body-secondary" id="pg_sale_count">' . (($totals['count'] > 0) ? h(lang(array('string' => '{var:1} lines · {var:2} units', 'vars' => array($totals['count'], $totals['units'])))) : '') . '</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table mb-0 pg-sale-table">
                                <thead>
                                    <tr>
                                        <th class="pg-sale-c-thumb pg-kasa"><span class="visually-hidden">' . lang('Image') . '</span></th>
                                        <th class="pg-sale-c-no pg-belge">#</th>
                                        <th>' . lang('Product') . '</th>
                                        <th>' . lang('Qty') . '</th>
                                        <th class="text-end pg-sale-c-unit">' . lang('Unit price') . '<small class="pg-kasa-inline">' . h(local_sale_till_price_label()) . '</small><small class="pg-belge-inline">' . h(local_sale_tax_label('excluding')) . '</small></th>
                                        <th class="text-end pg-belge">' . h(local_sale_tax_label('tax')) . '</th>
                                        <th class="text-end pg-belge">' . h(local_sale_tax_label('amount')) . '</th>
                                        <th class="text-end">' . lang('Amount') . '<small class="pg-kasa-inline">' . h(local_sale_till_price_label()) . '</small><small class="pg-belge-inline">' . h(local_sale_tax_label('excluding')) . '</small></th>
                                        <th><span class="visually-hidden">' . lang('Remove') . '</span></th>
                                    </tr>
                                </thead>
                                <tbody id="pg_sale_rows">' . local_sale_render_rows($lines) . '</tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!--
                    The status column. It departs from the house pattern in one
                    place, on purpose: the primary button sits here, under the
                    total, not in a save bar at the foot of the page. At a till
                    the total and the button that takes it are read together and
                    have to stay in view while the operator scans; a bar at the
                    bottom would pull them apart. Below lg, where the column drops
                    under the cart, the save bar (order-3) comes back.

                    Stickiness sits on the box inside the column, not on the column.
                -->
                <aside class="col-12 col-lg-4 col-xxl-3 order-2" aria-label="' . h(lang('Summary and payment')) . '">
                    <div class="pg-sale-status" id="pg_sale_status">
                        <div id="pg_sale_done" hidden></div>
                        <div class="card" id="pg_sale_summary_card">
                            <div class="card-header pg-belge"><h2 class="pg-sale-ch text-primary">' . lang('Summary') . '</h2></div>
                            <div id="pg_sale_summary">' . local_sale_render_summary($totals) . '</div>
                        </div>
                        ' . local_sale_render_pay($user) . '
                        <div id="pg_sale_ledger">' . local_sale_render_ledger($user) . '</div>
                    </div>
                </aside>

                <div class="col-12 order-3 position-sticky d-lg-none pg-sale-savebar" id="pg_sale_savebar" hidden>
                    <div class="pg-sale-savebar-in">
                        <div class="pg-sale-savebar-text"><b id="pg_sale_bar_total"></b><small id="pg_sale_bar_how"></small></div>
                        <button type="button" class="btn btn-success rounded-pill px-3 no-submit" id="pg_sale_bar_go"><i class="bi bi-check2-circle me-1" aria-hidden="true"></i>' . lang('Complete the sale') . '</button>
                    </div>
                </div>

            </div>

            <div class="visually-hidden" aria-live="polite" aria-atomic="true" id="pg_sale_live"></div>
        </div>

        <div class="offcanvas offcanvas-end pg-sale-recent" tabindex="-1" id="pg_sale_recent" aria-labelledby="pg_sale_recent_title">
            <div class="offcanvas-header border-bottom">
                <h5 class="offcanvas-title" id="pg_sale_recent_title">' . lang('Recent Local Sales') . '</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="' . h(lang('Close')) . '"></button>
            </div>
            <div class="offcanvas-body p-0" id="pg_sale_recent_body"></div>
            <div class="border-top px-3 py-2 small"><a href="view_orders.php" class="link-body-emphasis">' . lang('All orders') . ' <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
        </div>

        <div class="modal fade" id="pg_sale_keys" tabindex="-1" aria-labelledby="pg_sale_keys_title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="pg_sale_keys_title">' . lang('Keyboard shortcuts') . '</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . h(lang('Close')) . '"></button>
                    </div>
                    <div class="modal-body">
                        <dl class="pg-sale-keys">
                            <dt><kbd>F2</kbd></dt><dd>' . lang('Go to the product box') . '</dd>
                            <dt><kbd>F4</kbd></dt><dd>' . lang('Pick the customer') . '</dd>
                            <dt><kbd>F9</kbd> <kbd>Ctrl+Enter</kbd></dt><dd>' . lang('Complete the sale') . '</dd>
                            <dt><kbd>↑</kbd> <kbd>↓</kbd> <kbd>Enter</kbd></dt><dd>' . lang('Move through the results and add') . '</dd>
                            <dt><kbd>Esc</kbd></dt><dd>' . lang('Close the results') . '</dd>
                            <dt><kbd>Enter</kbd></dt><dd>' . lang('Start a new sale once one is complete') . '</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="pg_sale_clear" tabindex="-1" aria-labelledby="pg_sale_clear_title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title" id="pg_sale_clear_title">' . lang('Empty the cart?') . '</h5></div>
                    <div class="modal-body small">' . lang('Every line leaves the cart. Nothing has left the stock yet, so nothing is put back.') . '</div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-ghost no-submit" data-bs-dismiss="modal">' . lang('Cancel') . '</button>
                        <button type="button" class="btn btn-sm btn-outline-warning no-submit" id="pg_sale_clear_go"><i class="bi bi-cart-x me-1" aria-hidden="true"></i>' . lang('Empty the cart') . '</button>
                    </div>
                </div>
            </div>
        </div>'

        . ($can_erp ? '
        <div class="modal fade" id="pg_sale_account_modal" tabindex="-1" aria-labelledby="pg_sale_account_title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content disable_shortcut" id="pg_sale_account_form" data-pg-no-curtain novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="pg_sale_account_title">' . lang('New account') . '</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . h(lang('Close')) . '"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-body-secondary">' . lang('Only the name is needed. Whatever the buyer does not give now, the ERP manager completes on the card later.') . '</p>
                        <div class="btn-group btn-group-sm mb-3" role="group" aria-label="' . h(lang('Taxpayer type')) . '">
                            <input type="radio" class="btn-check" name="is_person" id="pg_sale_acc_person" value="1" checked>
                            <label class="btn btn-outline-secondary" for="pg_sale_acc_person"><i class="bi bi-person me-1" aria-hidden="true"></i>' . lang('Person') . '</label>
                            <input type="radio" class="btn-check" name="is_person" id="pg_sale_acc_company" value="0">
                            <label class="btn btn-outline-secondary" for="pg_sale_acc_company"><i class="bi bi-building me-1" aria-hidden="true"></i>' . lang('Company') . '</label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="pg_sale_acc_title">' . lang('Name and surname, or company name') . '</label>
                            <input type="text" class="form-control" id="pg_sale_acc_title" name="title" maxlength="255" required autocomplete="off">
                            <div class="invalid-feedback" data-sale-error="title"></div>
                        </div>
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <label class="form-label" for="pg_sale_acc_phone">' . lang('Phone') . '</label>
                                <input type="tel" class="form-control" id="pg_sale_acc_phone" name="phone" maxlength="50" autocomplete="off">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="pg_sale_acc_email">' . lang('Email') . '</label>
                                <input type="email" class="form-control" id="pg_sale_acc_email" name="email" maxlength="255" autocomplete="off">
                                <div class="invalid-feedback" data-sale-error="email"></div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label" for="pg_sale_acc_tax">' . h((pg_erp_store_country() === 'TR') ? lang('VKN / TCKN') : lang('Tax number')) . ' <span class="text-body-secondary fw-normal">(' . lang('optional') . ')</span></label>
                            <input type="text" class="form-control" id="pg_sale_acc_tax" name="tax_number"' . ((pg_erp_store_country() === 'TR') ? ' inputmode="numeric" maxlength="11"' : ' maxlength="32"') . ' autocomplete="off">
                            <div class="invalid-feedback" data-sale-error="tax_number"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-ghost no-submit" data-bs-dismiss="modal">' . lang('Cancel') . '</button>
                        <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 no-submit" id="pg_sale_account_go">' . lang('Open and pick') . '</button>
                    </div>
                </form>
            </div>
        </div>
        ' . erp_drawer_markup('account') : '') . '

        <script type="application/json" id="pg_sale_config">' . json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) . '</script>
    </main>

    ' . output_footer() . '

    <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/add_order.js?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/js/add_order.js') . '"></script>
';

$liveform->remove_form();
?>
