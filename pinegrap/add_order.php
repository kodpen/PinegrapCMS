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
 */

/* ---------------------------------------------------------
   AJAX: product search  (?request=search_products&q=...)
   --------------------------------------------------------- */
if (isset($_GET['request']) && $_GET['request'] === 'search_products') {
    include('init.php');
    header('Content-Type: application/json');
    if (!USER_LOGGED_IN) {
        echo encode_json(array('status' => 'error', 'message' => 'Not logged in.'));
        exit();
    }
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    if ($q === '') {
        echo encode_json(array('results' => array()));
        exit();
    }
    $rows = db_items(
        "SELECT id, name, short_description
         FROM products
         WHERE enabled = '1'
           AND (name LIKE '%" . escape($q) . "%' OR short_description LIKE '%" . escape($q) . "%')
         ORDER BY name
         LIMIT 10"
    );
    echo encode_json(array('results' => $rows));
    exit();
}

include('init.php');
include_once('liveform.class.php');

$liveform = new liveform('add_order');
$user     = validate_user();
validate_ecommerce_access($user);
license_check(array('output' => 'validate'));

$action = isset($_POST['action']) ? trim($_POST['action']) : '';

/* ---------------------------------------------------------
   Helper: total qty in cart for a product (ignores offer items)
   --------------------------------------------------------- */
function cart_qty_for_product($product_id) {
    $order_id = isset($_SESSION['ecommerce']['order_id']) ? (int)($_SESSION['ecommerce']['order_id'] ?? '') : 0;
    if ($order_id <= 0) {
        return 0;
    }
    $row = db_item(
        "SELECT SUM(quantity) AS total
         FROM order_items
         WHERE order_id = '" . escape($order_id) . "'
           AND product_id = '" . escape($product_id) . "'
           AND added_by_offer = '0'"
    );
    return ($row && $row['total'] !== null) ? (int)$row['total'] : 0;
}

/* ---------------------------------------------------------
   Helper: stock validation — returns error string or ''
   $product must have keys: id, inventory, inventory_quantity
   --------------------------------------------------------- */
function check_stock($product, $qty_to_add) {
    if ($product['inventory'] != 1) {
        return ''; // inventory tracking off — always allowed
    }
    if ((int)$product['inventory_quantity'] <= 0) {
        return lang('This product is out of stock.');
    }
    $in_cart = cart_qty_for_product($product['id']);
    if (($in_cart + $qty_to_add) > (int)$product['inventory_quantity']) {
        return lang('Cannot add more than available stock') . ' (' . (int)$product['inventory_quantity'] . ').';
    }
    return '';
}

/* ---------------------------------------------------------
   POST: add product to cart by barcode/name OR product_id
   --------------------------------------------------------- */
if ($action === 'add_to_cart') {
    $barcode    = isset($_POST['barcode'])    ? trim($_POST['barcode'])    : '';
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $add_qty    = max(1, isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1);

    $product = null;
    if ($product_id > 0) {
        $product = db_item(
            "SELECT id, name, enabled, inventory, inventory_quantity
             FROM products
             WHERE id = '" . escape($product_id) . "'
             LIMIT 1"
        );
    } elseif ($barcode !== '') {
        if (defined('BARCODE_ENABLED') && BARCODE_ENABLED) {
            // Lookup by product_barcodes table when barcode feature is enabled.
            $bc_row = db_item("SELECT product_id FROM product_barcodes WHERE barcode = '" . escape($barcode) . "' LIMIT 1");
            if ($bc_row) {
                $product = db_item(
                    "SELECT id, name, enabled, inventory, inventory_quantity
                     FROM products
                     WHERE id = '" . escape($bc_row['product_id']) . "'
                     LIMIT 1"
                );
            }
        } else {
            // Legacy: match by product name (used as SKU).
            $product = db_item(
                "SELECT id, name, enabled, inventory, inventory_quantity
                 FROM products
                 WHERE name = '" . escape($barcode) . "'
                 LIMIT 1"
            );
        }
    }

    if ($product && $product['enabled'] == 1) {
        $stock_error = check_stock($product, $add_qty);
        if ($stock_error !== '') {
            $liveform->mark_error('error', $stock_error);
        } else {
            initialize_order();
            add_order_item($product['id'], $add_qty, 0, 'myself', '');
        }
    } elseif ($barcode !== '' || $product_id > 0) {
        $liveform->mark_error('error', lang('Product not found or is disabled.'));
    }

    header('Location: ' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_order.php');
    exit();
}

/* ---------------------------------------------------------
   POST: increase cart item qty by 1
   --------------------------------------------------------- */
if ($action === 'increase_qty') {
    $item_id  = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $order_id = isset($_SESSION['ecommerce']['order_id']) ? (int)($_SESSION['ecommerce']['order_id'] ?? '') : 0;

    if ($item_id > 0 && $order_id > 0) {
        $item = db_item(
            "SELECT oi.id, oi.product_id, oi.quantity,
                    p.inventory, p.inventory_quantity
             FROM order_items oi
             LEFT JOIN products p ON oi.product_id = p.id
             WHERE oi.id = '" . escape($item_id) . "'
               AND oi.order_id = '" . escape($order_id) . "'
             LIMIT 1"
        );

        if ($item) {
            $stock_error = check_stock(
                array(
                    'id'                 => $item['product_id'],
                    'inventory'          => $item['inventory'],
                    'inventory_quantity' => $item['inventory_quantity'],
                ),
                1
            );
            if ($stock_error !== '') {
                $liveform->mark_error('error', $stock_error);
            } else {
                db("UPDATE order_items
                    SET quantity = quantity + 1
                    WHERE id = '" . escape($item_id) . "'
                      AND order_id = '" . escape($order_id) . "'");
            }
        }
    }

    header('Location: ' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_order.php');
    exit();
}

/* ---------------------------------------------------------
   POST: decrease cart item qty by 1 (remove row if qty hits 0)
   --------------------------------------------------------- */
if ($action === 'decrease_qty') {
    $item_id  = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $order_id = isset($_SESSION['ecommerce']['order_id']) ? (int)($_SESSION['ecommerce']['order_id'] ?? '') : 0;

    if ($item_id > 0 && $order_id > 0) {
        $item = db_item(
            "SELECT quantity FROM order_items
             WHERE id = '" . escape($item_id) . "'
               AND order_id = '" . escape($order_id) . "'
             LIMIT 1"
        );
        if ($item) {
            if ((int)$item['quantity'] <= 1) {
                db("DELETE FROM order_items
                    WHERE id = '" . escape($item_id) . "'
                      AND order_id = '" . escape($order_id) . "'");
            } else {
                db("UPDATE order_items
                    SET quantity = quantity - 1
                    WHERE id = '" . escape($item_id) . "'
                      AND order_id = '" . escape($order_id) . "'");
            }
        }
    }

    header('Location: ' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_order.php');
    exit();
}

/* ---------------------------------------------------------
   POST: remove an item from the cart entirely
   --------------------------------------------------------- */
if ($action === 'remove_item') {
    $item_id  = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $order_id = isset($_SESSION['ecommerce']['order_id']) ? (int)($_SESSION['ecommerce']['order_id'] ?? '') : 0;

    if ($item_id > 0 && $order_id > 0) {
        db("DELETE FROM order_items
            WHERE id = '" . escape($item_id) . "'
              AND order_id = '" . escape($order_id) . "'");
    }

    header('Location: ' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_order.php');
    exit();
}

/* ---------------------------------------------------------
   POST: complete the order as a local sale
   --------------------------------------------------------- */
if ($action === 'complete_order') {
    $order_id = isset($_SESSION['ecommerce']['order_id']) ? (int)($_SESSION['ecommerce']['order_id'] ?? '') : 0;

    if ($order_id > 0) {
        // Fetch items with product inventory info for decrement
        $items = db_items(
            "SELECT
                oi.product_id,
                oi.quantity,
                oi.price,
                p.inventory,
                p.inventory_quantity
             FROM order_items oi
             LEFT JOIN products p ON oi.product_id = p.id
             WHERE oi.order_id = '" . escape($order_id) . "'"
        );

        if (count($items) > 0) {
            // Compute subtotal
            $subtotal_cents = 0;
            foreach ($items as $item) {
                $subtotal_cents += (int)$item['price'] * (int)$item['quantity'];
            }

            // Assign order_number — same pattern as submit_order.php
            $result = mysqli_query(db::$con, "LOCK TABLES next_order_number WRITE") or output_error(lang('Query failed.'));
            $result = mysqli_query(db::$con, "SELECT next_order_number FROM next_order_number") or output_error(lang('Query failed.'));
            if (mysqli_num_rows($result) > 0) {
                $row          = mysqli_fetch_assoc($result);
                $order_number = $row['next_order_number'];
            } else {
                mysqli_query(db::$con, "INSERT INTO next_order_number VALUES (1)") or output_error(lang('Query failed.'));
                $order_number = 1;
            }
            mysqli_query(db::$con, "UPDATE next_order_number SET next_order_number = next_order_number + 1") or output_error(lang('Query failed.'));
            mysqli_query(db::$con, "UNLOCK TABLES") or output_error(lang('Query failed.'));

            $now = time();
            db("UPDATE orders
                SET
                    type                    = 'local',
                    status                  = 'complete',
                    order_number            = '" . escape($order_number) . "',
                    subtotal                = '" . escape($subtotal_cents) . "',
                    total                   = '" . escape($subtotal_cents) . "',
                    user_id                 = '" . escape($user['id']) . "',
                    last_modified_timestamp = '" . escape($now) . "',
                    ip_address              = IFNULL(INET_ATON('" . escape($_SERVER['REMOTE_ADDR']) . "'), 0)
                WHERE id = '" . escape($order_id) . "'");

            // Decrement inventory for tracked products
            foreach ($items as $item) {
                if ($item['inventory'] == 1 && (int)$item['inventory_quantity'] > 0) {
                    db("UPDATE products
                        SET inventory_quantity = (inventory_quantity - '" . escape($item['quantity']) . "')
                        WHERE id = '" . escape($item['product_id']) . "'");
                    // Mark out-of-stock when fully depleted
                    if ((int)$item['quantity'] >= (int)$item['inventory_quantity']) {
                        db("UPDATE products
                            SET out_of_stock = '1', out_of_stock_timestamp = UNIX_TIMESTAMP()
                            WHERE id = '" . escape($item['product_id']) . "'");
                    }
                }
            }

            unset($_SESSION['ecommerce']['order_id']);

            header('Location: ' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_orders.php?type=local');
            exit();
        }
    }

    // Nothing to complete — stay on page
    header('Location: ' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_order.php');
    exit();
}

/* ---------------------------------------------------------
   GET: render page — scanner + product search + cart
   --------------------------------------------------------- */

// Verify/load current order
$order_id         = 0;
$cart_items       = array();
$cart_total_cents = 0;

if (isset($_SESSION['ecommerce']['order_id']) && ($_SESSION['ecommerce']['order_id'] ?? '') != '') {
    $order_id = (int)($_SESSION['ecommerce']['order_id'] ?? '');
    $existing = db_item("SELECT id, status FROM orders WHERE id = '" . escape($order_id) . "'");
    if (!$existing || $existing['status'] !== 'incomplete') {
        unset($_SESSION['ecommerce']['order_id']);
        $order_id = 0;
    }
}

if ($order_id > 0) {
    $cart_items = db_items(
        "SELECT
            oi.id,
            oi.quantity,
            oi.price,
            oi.product_id,
            p.name,
            p.image_name,
            p.short_description,
            p.inventory,
            p.inventory_quantity
         FROM order_items oi
         LEFT JOIN products p ON oi.product_id = p.id
         WHERE oi.order_id = '" . escape($order_id) . "'
         ORDER BY oi.id"
    );
    foreach ($cart_items as $item) {
        $cart_total_cents += (int)$item['price'] * (int)$item['quantity'];
    }
}

// --- Last 5 local orders ---
$recent_local_orders = db_items(
    "SELECT
        orders.id,
        orders.reference_code,
        orders.order_date,
        orders.total,
        COUNT(order_items.id) AS item_count
     FROM orders
     LEFT JOIN order_items ON order_items.order_id = orders.id
     WHERE orders.type = 'local'
       AND orders.status = 'complete'
     GROUP BY orders.id
     ORDER BY orders.order_date DESC
     LIMIT 5"
);

// Build recent orders rows
$output_recent_rows = '';
if (count($recent_local_orders) > 0) {
    foreach ($recent_local_orders as $ro) {
        $ro_total    = prepare_amount($ro['total'] / 100);
        $ro_date     = get_relative_time(array('timestamp' => $ro['order_date']));
        $ro_url      = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_order.php?id=' . (int)$ro['id'];
        $output_recent_rows .=
            '<tr>
                <td class="align-middle">' . h($ro['reference_code']) . '</td>
                <td class="align-middle">' . $ro_date . '</td>
                <td class="align-middle text-center">' . (int)$ro['item_count'] . '</td>
                <td class="align-middle text-end fw-bold">' . $ro_total . '</td>
                <td class="align-middle text-center">
                    <a href="' . $ro_url . '" class="btn btn-sm btn-outline-secondary border-0"
                        title="' . lang('View') . '"><i class="bi bi-eye"></i></a>
                </td>
            </tr>';
    }
} else {
    $output_recent_rows =
        '<tr><td colspan="5" class="text-center text-secondary py-3">' . lang('No local orders yet.') . '</td></tr>';
}

// --- Cart rows ---
$page_url         = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_order.php';
$output_cart_rows = '';

if (count($cart_items) > 0) {
    foreach ($cart_items as $item) {
        $item_price    = $item['price'] / 100;
        $item_subtotal = ($item['price'] * $item['quantity']) / 100;
        // Disable + button when already at max tracked stock
        $plus_disabled = ($item['inventory'] == 1 && (int)$item['quantity'] >= (int)$item['inventory_quantity'])
            ? ' disabled' : '';

        if ($item['image_name']) {
            $output_image =
                '<img style="width:40px;height:40px;" class="img-thumbnail lazy"
                    src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/loading.gif"
                    data-src="' . PATH . h($item['image_name']) . '">';
        } else {
            $output_image =
                '<svg class="bd-placeholder-img img-thumbnail" width="40" height="40"
                    xmlns="http://www.w3.org/2000/svg">
                    <rect width="100%" height="100%" fill="#868e96"/>
                    <text x="10%" y="55%" style="font-size:7px;" fill="#dee2e6" dy=".3em">' . lang('No Image') . '</text>
                </svg>';
        }

        $output_cart_rows .=
            '<tr>
                <td class="align-middle">' . $output_image . '</td>
                <td class="align-middle">' . h($item['name']) . '<br>
                    <small class="text-secondary">' . h($item['short_description']) . '</small></td>
                <td class="align-middle text-center" style="white-space:nowrap;">
                    <form method="post" action="' . $page_url . '" class="d-inline disable_shortcut">
                        <input type="hidden" name="action" value="decrease_qty">
                        <input type="hidden" name="item_id" value="' . (int)$item['id'] . '">
                        <button type="submit" class="btn btn-sm btn-outline-secondary border-0 py-0 px-1"
                            title="' . lang('Decrease') . '">&#8722;</button>
                    </form>
                    <span class="mx-1 fw-bold">' . (int)$item['quantity'] . '</span>
                    <form method="post" action="' . $page_url . '" class="d-inline disable_shortcut">
                        <input type="hidden" name="action" value="increase_qty">
                        <input type="hidden" name="item_id" value="' . (int)$item['id'] . '">
                        <button type="submit" class="btn btn-sm btn-outline-secondary border-0 py-0 px-1"' . $plus_disabled . '
                            title="' . lang('Increase') . '">&#43;</button>
                    </form>
                </td>
                <td class="align-middle text-end">' . prepare_amount($item_price) . '</td>
                <td class="align-middle text-end fw-bold">' . prepare_amount($item_subtotal) . '</td>
                <td class="align-middle text-center">
                    <form method="post" action="' . $page_url . '" class="disable_shortcut">
                        <input type="hidden" name="action" value="remove_item">
                        <input type="hidden" name="item_id" value="' . (int)$item['id'] . '">
                        <button type="submit" class="btn btn-sm btn-outline-danger border-0"
                            title="' . lang('Remove') . '"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>';
    }
} else {
    $output_cart_rows =
        '<tr><td colspan="6" class="text-center text-secondary py-3">' . lang('Cart is empty.') . '</td></tr>';
}

$cart_total_formatted     = prepare_amount($cart_total_cents / 100);
$complete_button_disabled = count($cart_items) === 0 ? ' disabled' : '';

echo
    pg_page_shell(
        array(
            'title'         => lang('Add Local Order'),
            'extra classes' => 'product',
            'icon'          => 'store',
            'heading'       => lang('Add Local Order'),
            'cancel'=>array('enable'=>'true','url'=>'view_orders.php'),
            'auto_main'     => false,
            'breadcrumb' => array(
                array('label' => lang('Orders'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_orders.php'),
                array('label' => lang('Add Local Order')),
            ),
        )
    ) . '

    <main class="container mb-5" style="min-height:calc(100vh - 175px)" id="content">

        <div class="row">
            <div class="col-12">
                <div class="row mb-2 flex-wrap">
                    <div class="col-12 col-sm-12 col-md-6 col-xl-9 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page"
                            data-bs-content="' . lang('Scan a barcode or search for a product to add items to the cart, then complete the order as a local sale.') . '"
                            title="' . lang('Add Local Order') . '">' . lang('Add Local Order') . '</h2>
                    </div>
                </div>
                ' . $liveform->get_messages() . '
            </div>
        </div>

        <div class="row">

            <!-- Left panel: barcode scanner + product search -->
            <div class="col-12 col-md-auto my-2" style="min-width:300px;">
                <div class="card border-4 border-primary">
                    <div class="card-body">

                        <!-- Barcode scanner input -->
                        <label for="scanner_input" class="form-label"
                            data-bs-content="' . lang('-Last Scan: shows last readed scan for barcode scanners and no need to any action.</br>-Manuel Scan: if scanner read wrong or dont read, you can input manuel and read it with \'Read\' button.') . '"
                            title="' . lang('Last Scan & Manuel Read') . '">' . lang('Last Scan & Manuel Read') . ' (' . lang('what is this?') . ')</label>

                        <form id="scan_form" class="disable_shortcut" method="post" action="' . $page_url . '">
                            <input type="hidden" name="action" value="add_to_cart">
                            <div class="input-group my-2">
                                <label for="scanner_input" class="input-group-text">' . lang('Barcode') . ':</label>
                                <input type="text" id="scanner_input" name="barcode" value=""
                                    placeholder="' . lang('Barcode') . '"
                                    class="form-control text-center text-md-start"
                                    autocomplete="off">
                                
                                <button type="submit" class="btn btn-primary">' . lang('Read') . '</button>
                            </div>
                        </form>

                        <hr class="my-3">

                        <!-- Product search -->
                        <label for="product_search_input" class="form-label">' . lang('Or Search Product') . '</label>
                        <div class="input-group my-2">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" id="product_search_input"
                                placeholder="' . lang('Type to search...') . '"
                                class="form-control"
                                autocomplete="off">
                        </div>
                        <div id="product_search_results" class="list-group mt-1" style="display:none;max-height:250px;overflow-y:auto;"></div>

                        <!-- Hidden form submitted when user selects a product from search results -->
                        <form id="select_product_form" class="disable_shortcut" method="post" action="' . $page_url . '">
                            <input type="hidden" name="action" value="add_to_cart">
                            <input type="hidden" name="product_id" id="selected_product_id" value="">
                        </form>

                    </div>
                </div>
            </div>

            <!-- Right panel: cart -->
            <div class="col-12 col-md my-2">
                <div class="card border-4 border-primary">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table mb-0">
                                <thead>
                                    <tr>
                                        <th></th>
                                        <th>' . lang('Product') . '</th>
                                        <th class="text-center">' . lang('Qty') . '</th>
                                        <th class="text-end">' . lang('Price') . '</th>
                                        <th class="text-end">' . lang('Subtotal') . '</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ' . $output_cart_rows . '
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4" class="text-end fw-bold">' . lang('Total') . ':</td>
                                        <td class="text-end fw-bold h5 mb-0">' . $cart_total_formatted . '</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-reset border-0 text-end">
                        <form method="post" class="disable_shortcut" action="' . $page_url . '">
                            <input type="hidden" name="action" value="complete_order">
                            <button type="submit" class="btn btn-success"' . $complete_button_disabled . '>
                                <span class="material-icons me-1 align-middle" style="font-size:1.1rem;">check_circle</span>
                                ' . lang('Complete Order') . '
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </div>

        <!-- Recent local sales collapse -->
        <div class="row mt-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header p-0 position-relative overflow-hidden border-0">
                        <button class="btn btn-link link-secondary stretched-link"
                            type="button" data-bs-toggle="collapse" data-bs-target="#recent_local_sales"
                            aria-expanded="false" aria-controls="recent_local_sales">
                            <span>
                                <i class="bi bi-clock-history me-1"></i>
                                ' . lang('Recent Local Sales') . '
                            </span>
                            <i class="bi bi-chevron-down toggle-icon" style="transition:transform .2s;"></i>
                        </button>
                    </div>
                    <div class="collapse" id="recent_local_sales">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>' . lang('Reference') . '</th>
                                        <th>' . lang('Date') . '</th>
                                        <th class="text-center">' . lang('Items') . '</th>
                                        <th class="text-end">' . lang('Total') . '</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ' . $output_recent_rows . '
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </main>

    ' . output_footer() . '

    <script>
        $(window).focus(function() { $("body").focus(); });

        /*
         * jQuery Scanner Detection
         * Copyright (c) 2013 Julien Maurel — MIT License
         * https://github.com/julien-maurel/jQuery-Scanner-Detection
         * Version: 1.2.1
         */
        !function(e){e.fn.scannerDetection=function(n){if("string"==typeof n)return this.each(function(){this.scannerDetectionTest(n)}),this;if(!1===n)return this.each(function(){this.scannerDetectionOff()}),this;var t={onComplete:!1,onError:!1,onReceive:!1,onKeyDetect:!1,timeBeforeScanTest:200,avgTimeByChar:30,minLength:2,endChar:[9,13],startChar:[],ignoreIfFocusOn:!1,scanButtonKeyCode:!0,scanButtonLongPressThreshold:3,onScanButtonLongPressed:!1,stopPropagation:!1,preventDefault:!1};return"function"==typeof n&&(n={onComplete:n}),n="object"!=typeof n?e.extend({},t):e.extend({},t,n),this.each(function(){var t=this,o=e(t),r=0,i=0,c="",s=!1,a=!1,f=0,u=function(){r=0,c="",f=0};t.scannerDetectionOff=function(){o.unbind("keydown.scannerDetection"),o.unbind("keypress.scannerDetection")},t.isFocusOnIgnoredElement=function(){if(!n.ignoreIfFocusOn)return!1;if("string"==typeof n.ignoreIfFocusOn)return e(":focus").is(n.ignoreIfFocusOn);if("object"==typeof n.ignoreIfFocusOn&&n.ignoreIfFocusOn.length)for(var t=e(":focus"),o=0;o<n.ignoreIfFocusOn.length;o++)if(t.is(n.ignoreIfFocusOn[o]))return!0;return!1},t.scannerDetectionTest=function(e){return e&&(r=i=0,c=e),f||(f=1),c.length>=n.minLength&&i-r<c.length*n.avgTimeByChar?(n.onScanButtonLongPressed&&f>n.scanButtonLongPressThreshold?n.onScanButtonLongPressed.call(t,c,f):n.onComplete&&n.onComplete.call(t,c,f),o.trigger("scannerDetectionComplete",{string:c}),u(),!0):(n.onError&&n.onError.call(t,c),o.trigger("scannerDetectionError",{string:c}),u(),!1)},o.data("scannerDetection",{options:n}).unbind(".scannerDetection").bind("keydown.scannerDetection",function(e){if(!1!==n.scanButtonKeyCode&&e.which==n.scanButtonKeyCode)f++,e.preventDefault(),e.stopImmediatePropagation();else if(r&&-1!==n.endChar.indexOf(e.which)||!r&&-1!==n.startChar.indexOf(e.which)){var i=jQuery.Event("keypress",e);i.type="keypress.scannerDetection",o.triggerHandler(i),e.preventDefault(),e.stopImmediatePropagation()}n.onKeyDetect&&n.onKeyDetect.call(t,e),o.trigger("scannerDetectionKeyDetect",{evt:e})}).bind("keypress.scannerDetection",function(e){this.isFocusOnIgnoredElement()||(n.stopPropagation&&e.stopImmediatePropagation(),n.preventDefault&&e.preventDefault(),r&&-1!==n.endChar.indexOf(e.which)?(e.preventDefault(),e.stopImmediatePropagation(),s=!0):r||-1===n.startChar.indexOf(e.which)?(void 0!==e.which&&(c+=String.fromCharCode(e.which)),s=!1):(e.preventDefault(),e.stopImmediatePropagation(),s=!1),r||(r=Date.now()),i=Date.now(),a&&clearTimeout(a),s?(t.scannerDetectionTest(),a=!1):a=setTimeout(t.scannerDetectionTest,n.timeBeforeScanTest),n.onReceive&&n.onReceive.call(t,e),o.trigger("scannerDetectionReceive",{evt:e}))})}),this}}(jQuery);

        $(document).scannerDetection({
            timeBeforeScanTest: 200,
            avgTimeByChar:      100,
            onComplete: function(barcode) {
                // Ignore scanner events when user is typing in an input
                if ($(":focus").is("input, textarea")) {
                    return;
                }
                barcode = barcode.replace(/\*/g, "-");
                $("#scanner_input").val(barcode);
                $("#scan_form").submit();
            }
        });

        /* -------------------------------------------------------
           Product search — debounced AJAX, 300 ms
           ------------------------------------------------------- */
        var searchTimer = null;
        var noResultsText = ' . json_encode(lang('No products found.')) . ';

        $("#product_search_input").on("input", function() {
            clearTimeout(searchTimer);
            var q = $(this).val().trim();
            if (q.length < 1) {
                $("#product_search_results").hide().empty();
                return;
            }
            searchTimer = setTimeout(function() {
                $.getJSON(
                    "' . $page_url . '",
                    { request: "search_products", q: q },
                    function(data) {
                        var $r = $("#product_search_results").empty();
                        if (data.results && data.results.length > 0) {
                            $.each(data.results, function(i, p) {
                                var name = $("<span>").text(p.name).prop("outerHTML");
                                var desc = p.short_description
                                    ? "<br><small class=\"text-secondary\">" + $("<span>").text(p.short_description).prop("outerHTML") + "</small>"
                                    : "";
                                $("<a>")
                                    .addClass("list-group-item list-group-item-action py-2")
                                    .attr("href", "#")
                                    .html("<strong>" + name + "</strong>" + desc)
                                    .on("click", function(e) {
                                        e.preventDefault();
                                        $("#selected_product_id").val(p.id);
                                        $("#product_search_results").hide().empty();
                                        $("#product_search_input").val("");
                                        $("#select_product_form").submit();
                                    })
                                    .appendTo($r);
                            });
                            $r.show();
                        } else {
                            $r.append(
                                $("<div>").addClass("list-group-item text-secondary py-2").text(noResultsText)
                            ).show();
                        }
                    }
                );
            }, 300);
        });

        // Close search results when clicking outside the search area
        $(document).on("click", function(e) {
            if (!$(e.target).closest("#product_search_input, #product_search_results").length) {
                $("#product_search_results").hide();
            }
        });

        // Rotate chevron icon when collapse opens/closes
        $("#recent_local_sales").on("show.bs.collapse", function() {
            $(this).closest(".card").find(".toggle-icon").css("transform", "rotate(180deg)");
        }).on("hide.bs.collapse", function() {
            $(this).closest(".card").find(".toggle-icon").css("transform", "rotate(0deg)");
        });
    </script>
';

$liveform->remove_form();
?>
