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

/* ---------------------------------------------------------
   AJAX: product search  (?request=search_products&q=...)
   --------------------------------------------------------- */
if (isset($_GET['request']) && $_GET['request'] === 'search_products') {
    include('init.php');
    header('Content-Type: application/json');
    if (!USER_LOGGED_IN) {
        echo encode_json(array('status' => 'error', 'message' => lang('You are not logged in.')));
        exit();
    }
    // Same gate as the screen this search serves (validate_ecommerce_access):
    // roles above user pass, a user needs the manage e-commerce right. The
    // refusal is JSON because the caller is waiting for JSON.
    if (!USER_MANAGE_ECOMMERCE) {
        log_activity(lang('access denied to commerce'), $_SESSION['sessionusername']);
        echo encode_json(array('status' => 'error', 'message' => lang('Access denied.')));
        exit();
    }
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    if ($q === '') {
        echo encode_json(array('results' => array()));
        exit();
    }
    // short_description is the product's name; name is the SKU. Both are
    // searched, the list is ordered by the name people know.
    $rows = db_items(
        "SELECT id, name, short_description, inventory, inventory_quantity
         FROM products
         WHERE enabled = '1'
           AND (name LIKE '%" . escape($q) . "%' OR short_description LIKE '%" . escape($q) . "%')
         ORDER BY short_description, name
         LIMIT 10"
    );
    echo encode_json(array('results' => $rows));
    exit();
}

/* ---------------------------------------------------------
   AJAX: customer search  (?request=search_customers&q=...)
   Contacts by name, company or e-mail, each with the ERP account it is
   linked to; and, with the ERP on, accounts that have no contact of their
   own. The sale is billed to whichever is picked.
   --------------------------------------------------------- */
if (isset($_GET['request']) && $_GET['request'] === 'search_customers') {
    include('init.php');
    header('Content-Type: application/json');
    if (!USER_LOGGED_IN) {
        echo encode_json(array('status' => 'error', 'message' => lang('You are not logged in.')));
        exit();
    }
    if (!USER_MANAGE_ECOMMERCE) {
        log_activity(lang('access denied to commerce'), $_SESSION['sessionusername']);
        echo encode_json(array('status' => 'error', 'message' => lang('Access denied.')));
        exit();
    }
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    if ($q === '') {
        echo encode_json(array('results' => array()));
        exit();
    }
    $like = escape(escape_like(mb_substr($q, 0, 100)));
    $erp_tables = defined('ERP_ENABLED') && ERP_ENABLED && waf_table_has_column('erp_accounts', 'contact_id');
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
            "SELECT id, title, email, city
             FROM erp_accounts
             WHERE contact_id = 0 AND status = 'active' AND kind IN ('customer', 'both')
               AND (title LIKE '%" . $like . "%' OR email LIKE '%" . $like . "%')
             ORDER BY title
             LIMIT 10"
        );
        foreach ((array) $accounts as $row) {
            $results[] = array(
                'kind' => 'account',
                'contact_id' => 0,
                'account_id' => (int) $row['id'],
                'label' => (string) $row['title'],
                'sub' => trim(implode(' · ', array_filter(array(lang('Account'), trim((string) $row['email']), trim((string) $row['city']))))),
            );
        }
    }

    echo encode_json(array('results' => $results));
    exit();
}

include('init.php');
include_once('liveform.class.php');

$liveform = new liveform('add_order');
$user     = validate_user();
validate_ecommerce_access($user);

$action = isset($_POST['action']) ? trim($_POST['action']) : '';

// Every action below changes the cart or completes an order, so the token is
// checked once here, before the dispatch. The page render has no action.
if ($action !== '') {
    validate_token_field();
}

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
   Helper: who the sale is for. Kept in the session, not on the order:
   initialize_order() rewrites orders.contact_id to the operator's own
   contact on every cart change, so the choice is applied once, when the
   sale is completed. Nothing chosen means a walk-in sale.
   --------------------------------------------------------- */
function local_sale_customer() {
    $customer = isset($_SESSION['ecommerce']['local_sale_customer']) ? $_SESSION['ecommerce']['local_sale_customer'] : null;
    if (!is_array($customer)) {
        return null;
    }
    $customer['contact_id'] = (int) ($customer['contact_id'] ?? 0);
    $customer['account_id'] = (int) ($customer['account_id'] ?? 0);
    if (($customer['contact_id'] <= 0) && ($customer['account_id'] <= 0)) {
        return null;
    }
    return $customer;
}

/* ---------------------------------------------------------
   Helper: the VAT on one cart line, the way the checkout works it out for
   a buyer standing in the store's own tax zone: the product's rate when it
   has one, the zone's otherwise, on the whole line. 0 when the product is
   not taxable or the store is in no tax zone.
   $item must have keys: price, quantity, taxable, tax_rate
   --------------------------------------------------------- */
function local_sale_line_tax($item) {
    static $zone_rate = null;
    if ($zone_rate === null) {
        $zone_rate = function_exists('get_default_tax_rate') ? get_default_tax_rate() : false;
    }
    if (empty($item['taxable']) || ($zone_rate === false)) {
        return 0;
    }
    $rate = get_effective_tax_rate($item['tax_rate'], $zone_rate);
    return (int) round($rate / 100 * ((int) $item['price'] * (int) $item['quantity']));
}

/* ---------------------------------------------------------
   POST: pick the customer the sale is for, or drop the pick
   --------------------------------------------------------- */
if ($action === 'set_customer') {
    $contact_id = isset($_POST['contact_id']) ? (int) $_POST['contact_id'] : 0;
    $account_id = isset($_POST['account_id']) ? (int) $_POST['account_id'] : 0;
    $erp_tables = defined('ERP_ENABLED') && ERP_ENABLED && waf_table_has_column('erp_accounts', 'contact_id');
    $label = '';

    if ($contact_id > 0) {
        $contact = db_item("SELECT id, first_name, last_name, company FROM contacts WHERE id = '" . $contact_id . "' LIMIT 1");
        if ($contact) {
            $person = trim(trim((string) $contact['first_name']) . ' ' . trim((string) $contact['last_name']));
            $label = ($person !== '') ? $person : (trim((string) $contact['company']) !== '' ? trim((string) $contact['company']) : ('#' . $contact_id));
            // The account the contact is linked to, when it has one; the ERP
            // opens one from the card otherwise, at invoicing.
            $account_id = $erp_tables ? (int) db_value("SELECT id FROM erp_accounts WHERE contact_id = '" . $contact_id . "' ORDER BY id ASC LIMIT 1") : 0;
        } else {
            $contact_id = 0;
        }
    } elseif (($account_id > 0) && $erp_tables) {
        $account = db_item("SELECT id, title, contact_id FROM erp_accounts WHERE id = '" . $account_id . "' LIMIT 1");
        if ($account) {
            $label = (string) $account['title'];
            $contact_id = (int) $account['contact_id'];
        } else {
            $account_id = 0;
        }
    } else {
        $account_id = 0;
    }

    if (($contact_id > 0) || ($account_id > 0)) {
        $_SESSION['ecommerce']['local_sale_customer'] = array('contact_id' => $contact_id, 'account_id' => $account_id, 'label' => $label);
    } else {
        $liveform->mark_error('error', lang('That customer could not be found.'));
    }

    header('Location: ' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_order.php');
    exit();
}

if ($action === 'clear_customer') {
    unset($_SESSION['ecommerce']['local_sale_customer']);
    header('Location: ' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_order.php');
    exit();
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
                oi.id,
                oi.product_id,
                oi.quantity,
                oi.price,
                p.inventory,
                p.inventory_quantity,
                p.taxable,
                p.tax_rate
             FROM order_items oi
             LEFT JOIN products p ON oi.product_id = p.id
             WHERE oi.order_id = '" . escape($order_id) . "'"
        );

        if (count($items) > 0) {
            // Subtotal, and the VAT the way the checkout works it out for a
            // buyer standing in the store's own tax zone: the product's rate
            // when it has one, the zone's otherwise, on the whole line.
            // tax_total is the line's tax; tax stays 0 (see update_order_item_taxes()).
            $subtotal_cents = 0;
            $tax_cents = 0;
            foreach ($items as $item) {
                $line_total = (int)$item['price'] * (int)$item['quantity'];
                $subtotal_cents += $line_total;

                $line_tax = local_sale_line_tax($item);
                $tax_cents += $line_tax;
                db("UPDATE order_items SET tax_total = '" . $line_tax . "', tax = '0' WHERE id = '" . (int)$item['id'] . "'");
            }

            // Who the sale is for: the picked contact and account, or nobody
            // (a walk-in), which the ERP bills to the account named for it.
            $customer = local_sale_customer();
            $sale_contact_id = $customer ? (int) $customer['contact_id'] : 0;
            $sale_account_id = $customer ? (int) $customer['account_id'] : 0;
            if (($sale_account_id <= 0) && ($sale_contact_id <= 0) && defined('ERP_WALKIN_ACCOUNT_ID')) {
                $sale_account_id = (int) ERP_WALKIN_ACCOUNT_ID;
            }
            $sql_erp_account = waf_table_has_column('orders', 'erp_account_id') ? "erp_account_id = '" . $sale_account_id . "'," : '';

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
                    tax                     = '" . escape($tax_cents) . "',
                    total                   = '" . escape($subtotal_cents + $tax_cents) . "',
                    contact_id              = '" . $sale_contact_id . "',
                    " . $sql_erp_account . "
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

                    if (function_exists('pg_marketplace_product_changed')) {
                        pg_marketplace_product_changed((int)$item['product_id']);
                    }
                }
            }

            unset($_SESSION['ecommerce']['order_id']);
            unset($_SESSION['ecommerce']['local_sale_customer']);

            // The counter's second half: the invoice and the money, in the same
            // step as the sale when the operator asked for them. What the
            // operator picked is remembered for the next sale - the till and
            // the way people pay rarely change between two customers.
            $erp_ready = defined('ERP_ENABLED') && ERP_ENABLED && (((int) $user['role'] < 3) || !empty($user['manage_erp']))
                && waf_table_has_column('orders', 'erp_invoice_id');
            $pay_method = isset($_POST['pay_method']) ? trim((string) $_POST['pay_method']) : '';
            $pay_till = isset($_POST['pay_till']) ? (int) $_POST['pay_till'] : 0;
            $issue_invoice = !empty($_POST['issue_invoice']) || ($pay_method !== '');

            if ($erp_ready && isset($_POST['issue_invoice_seen'])) {
                $_SESSION['ecommerce']['local_sale_payment'] = array(
                    'method' => $pay_method,
                    'till' => $pay_till,
                    'invoice' => $issue_invoice ? 1 : 0,
                );
            }

            if ($erp_ready && $issue_invoice) {
                require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
                $liveform_order = new liveform('view_order');
                $invoiced = erp_invoice_from_order($order_id, array('created_by' => (int) $user['id']));

                if (empty($invoiced['success'])) {
                    $liveform_order->mark_error('_error', lang('The invoice was not issued.') . ' ' . (string) $invoiced['error']);
                } else {
                    $liveform_order->add_notice(lang(array('string' => 'Invoice {var:1} created.', 'vars' => $invoiced['full_number'])));

                    $can_take_money = ((int) $user['role'] < 3) || !empty($user['manage_erp_cash']);

                    if (($pay_method !== '') && !$can_take_money) {
                        $liveform_order->mark_error('_error', lang('The receipt was not recorded: you do not hold the ERP cash right.'));
                    } elseif ($pay_method !== '') {
                        $invoice = db_item("SELECT id, account_id, grand_total, currency FROM erp_invoices WHERE id = '" . (int) $invoiced['invoice_id'] . "' LIMIT 1");
                        $till = ($pay_till > 0) ? db_item("SELECT id, name FROM erp_cash_accounts WHERE id = '" . $pay_till . "' AND is_active = 1 LIMIT 1") : null;

                        if (!is_array($till)) {
                            $liveform_order->mark_error('_error', lang('The receipt was not recorded: choose a till or bank account.'));
                        } elseif (is_array($invoice) && ((int) $invoice['grand_total'] > 0)) {
                            $posted = erp_post_receipt(array(
                                'direction' => 'collection',
                                'account_id' => (int) $invoice['account_id'],
                                'cash_account_id' => (int) $till['id'],
                                'amount' => (int) $invoice['grand_total'],
                                'doc_date' => date('Y-m-d'),
                                'currency' => (string) $invoice['currency'],
                                'payment_method' => $pay_method,
                                'description' => lang(array('string' => 'Counter sale #{var:1}', 'vars' => $order_number)),
                                'invoice_id' => (int) $invoice['id'],
                                'created_by' => (int) $user['id'],
                            ));

                            if (empty($posted['success'])) {
                                $liveform_order->mark_error('_error', lang('The receipt was not recorded.') . ' ' . (string) $posted['error']);
                            } else {
                                $liveform_order->add_notice(lang(array(
                                    'string' => 'Receipt #{var:1} for {var:2} was recorded to {var:3}; the invoice is paid.',
                                    'vars' => array((int) $posted['cash_id'], erp_money_out((int) $invoice['grand_total']), $till['name']),
                                )));
                            }
                        }
                    }
                }
            }

            header('Location: ' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_order.php?id=' . (int) $order_id);
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
$cart_tax_cents   = 0;

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
            p.inventory_quantity,
            p.taxable,
            p.tax_rate
         FROM order_items oi
         LEFT JOIN products p ON oi.product_id = p.id
         WHERE oi.order_id = '" . escape($order_id) . "'
         ORDER BY oi.id"
    );
    foreach ($cart_items as $item) {
        $cart_total_cents += (int)$item['price'] * (int)$item['quantity'];
        $cart_tax_cents += local_sale_line_tax($item);
    }
}

// --- Last 5 local orders ---
// With the ERP on, each carries the invoice issued for it, or the means to
// issue one: a sale at the counter is invoiced from here, without a trip
// through the ERP's own picker.
$erp_invoicing = defined('ERP_ENABLED') && ERP_ENABLED && (((int) $user['role'] < 3) || !empty($user['manage_erp'])) && waf_table_has_column('orders', 'erp_invoice_id');
$recent_local_orders = db_items(
    "SELECT
        orders.id,
        orders.reference_code,
        orders.order_number,
        orders.order_date,
        orders.total,
        orders.contact_id,
        COUNT(order_items.id) AS item_count"
        . ($erp_invoicing ? ",
        orders.erp_invoice_id,
        orders.erp_account_id,
        erp_invoices.full_number AS erp_invoice_number" : "") . "
     FROM orders
     LEFT JOIN order_items ON order_items.order_id = orders.id"
     . ($erp_invoicing ? "
     LEFT JOIN erp_invoices ON erp_invoices.id = orders.erp_invoice_id" : "") . "
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

        $output_ro_invoice = '';
        if ($erp_invoicing) {
            if ((int) $ro['erp_invoice_id'] > 0) {
                $output_ro_invoice = '<a href="edit_erp_invoice.php?id=' . (int) $ro['erp_invoice_id'] . '" class="link-success small text-nowrap"><i class="bi bi-receipt me-1"></i>' . h((string) $ro['erp_invoice_number']) . '</a>';
            } elseif (((int) $ro['contact_id'] > 0) || ((int) $ro['erp_account_id'] > 0)) {
                $output_ro_invoice =
                    '<form method="post" action="add_erp_invoice.php" class="d-inline disable_shortcut">
                        ' . get_token_field() . '
                        <input type="hidden" name="order_id" value="' . (int) $ro['id'] . '" />
                        <input type="hidden" name="from_order_screen" value="1" />
                        <button type="submit" name="submit_create" value="Create" class="btn btn-sm btn-outline-success border-0 text-nowrap" data-confirm-content="' . h(lang(array('string' => 'Issue the ERP invoice for order #{var:1}?', 'vars' => $ro['order_number']))) . '" data-loading-content="' . lang(array('string' => 'Creating')) . '"><i class="bi bi-receipt me-1"></i>' . lang('Issue the Invoice') . '</button>
                    </form>';
            } else {
                $output_ro_invoice = '<span class="text-secondary small" title="' . h(lang('No walk-in sales account is named on the ERP settings card, so this sale cannot be invoiced unless a customer is picked.')) . '">&mdash;</span>';
            }
        }

        $output_recent_rows .=
            '<tr>
                <td class="align-middle">' . h($ro['reference_code']) . '<div class="small text-secondary">#' . h($ro['order_number']) . '</div></td>
                <td class="align-middle">' . $ro_date . '</td>
                <td class="align-middle text-center">' . (int)$ro['item_count'] . '</td>
                <td class="align-middle text-end fw-bold">' . $ro_total . '</td>'
                . ($erp_invoicing ? '<td class="align-middle text-center">' . $output_ro_invoice . '</td>' : '') . '
                <td class="align-middle text-center">
                    <a href="' . $ro_url . '" class="btn btn-sm btn-outline-secondary border-0"
                        title="' . lang('View') . '"><i class="bi bi-eye"></i></a>
                </td>
            </tr>';
    }
} else {
    $output_recent_rows =
        '<tr><td colspan="' . ($erp_invoicing ? 6 : 5) . '" class="text-center text-secondary py-3">' . lang('No local orders yet.') . '</td></tr>';
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
                <td class="align-middle">' . h((trim((string) $item['short_description']) !== '') ? $item['short_description'] : $item['name']) . '<br>
                    <small class="text-secondary">' . h($item['name']) . '</small></td>
                <td class="align-middle text-center" style="white-space:nowrap;">
                    <form method="post" action="' . $page_url . '" class="d-inline disable_shortcut">
                        ' . get_token_field() . '
                        <input type="hidden" name="action" value="decrease_qty">
                        <input type="hidden" name="item_id" value="' . (int)$item['id'] . '">
                        <button type="submit" class="btn btn-sm btn-outline-secondary border-0 py-0 px-1"
                            title="' . lang('Decrease') . '">&#8722;</button>
                    </form>
                    <span class="mx-1 fw-bold">' . (int)$item['quantity'] . '</span>
                    <form method="post" action="' . $page_url . '" class="d-inline disable_shortcut">
                        ' . get_token_field() . '
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
                        ' . get_token_field() . '
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
$cart_tax_formatted       = prepare_amount($cart_tax_cents / 100);
$cart_grand_formatted     = prepare_amount(($cart_total_cents + $cart_tax_cents) / 100);
$complete_button_disabled = count($cart_items) === 0 ? ' disabled' : '';

// --- Who the sale is for ---
$customer = local_sale_customer();
$erp_on = defined('ERP_ENABLED') && ERP_ENABLED;
$erp_access = $erp_on && ((int) $user['role'] < 3 || !empty($user['manage_erp']));
$walkin_account = null;
if ($erp_on && defined('ERP_WALKIN_ACCOUNT_ID') && (ERP_WALKIN_ACCOUNT_ID > 0) && waf_table_has_column('erp_accounts', 'title')) {
    $walkin_account = db_item("SELECT id, title FROM erp_accounts WHERE id = '" . (int) ERP_WALKIN_ACCOUNT_ID . "' LIMIT 1");
}

if ($customer) {
    $customer_links = array();
    if ($customer['contact_id'] > 0) {
        $customer_links[] = '<a href="edit_contact.php?id=' . (int) $customer['contact_id'] . '" class="link-secondary small"><i class="bi bi-person-vcard me-1"></i>' . lang('Contact') . '</a>';
    }
    if ($erp_access && ($customer['account_id'] > 0)) {
        $customer_links[] = '<a href="edit_erp_account.php?id=' . (int) $customer['account_id'] . '" class="link-secondary small"><i class="bi bi-journal-text me-1"></i>' . lang('Ledger account') . '</a>';
    }
    $output_customer =
        '<div class="d-flex align-items-start gap-2">
            <div class="flex-grow-1">
                <div class="fw-bold">' . h($customer['label']) . '</div>
                <div class="d-flex flex-wrap gap-3 mt-1">' . implode('', $customer_links) . '</div>
            </div>
            <form method="post" action="' . $page_url . '" class="disable_shortcut">
                ' . get_token_field() . '
                <input type="hidden" name="action" value="clear_customer">
                <button type="submit" class="btn btn-sm btn-outline-secondary border-0" title="' . lang('Remove the customer') . '"><i class="bi bi-x-lg"></i></button>
            </form>
        </div>';
    $output_customer_summary = h($customer['label']);
} else {
    $output_customer =
        '<div class="text-secondary"><i class="bi bi-shop me-1"></i>' . lang('Walk-in sale: no customer is recorded on the order.') . '</div>';
    if ($erp_on) {
        $output_customer .= '<div class="form-text mt-1">' . ($walkin_account
            ? h(lang(array('string' => 'When invoiced, the sale is billed to "{var:1}".', 'vars' => $walkin_account['title'])))
            : lang('No walk-in sales account is named on the ERP settings card, so this sale cannot be invoiced unless a customer is picked.')) . '</div>';
    }
    $output_customer_summary = lang('Walk-in sale');
}

// --- Invoice and payment in the same step ---
//
// Offered when the ERP is on and this operator may issue invoices; the till
// half only to an operator who holds the cash right. A walk-in sale with no
// walk-in account cannot be invoiced, so nothing is offered for it. The last
// choice is remembered for the next sale.
$output_pay_controls = '';
$can_invoice_here = $erp_access && (($customer && (($customer['account_id'] > 0) || ($customer['contact_id'] > 0))) || ($walkin_account !== null));

if ($can_invoice_here) {
    $remembered = isset($_SESSION['ecommerce']['local_sale_payment']) && is_array($_SESSION['ecommerce']['local_sale_payment'])
        ? $_SESSION['ecommerce']['local_sale_payment']
        : array('method' => 'cash', 'till' => 0, 'invoice' => 1);
    $can_take_money = ((int) $user['role'] < 3) || !empty($user['manage_erp_cash']);
    $tills = $can_take_money ? (array) db_items("SELECT id, name FROM erp_cash_accounts WHERE is_active = 1 ORDER BY sort_order ASC, id ASC") : array();

    $default_till = (int) ($remembered['till'] ?? 0);
    if (($default_till <= 0) && defined('ERP_DEFAULT_CASH_ACCOUNT_ID')) {
        $default_till = (int) ERP_DEFAULT_CASH_ACCOUNT_ID;
    }

    $method_labels = array(
        'cash' => lang('Cash'),
        'card' => lang('Card'),
        'transfer' => lang('Bank transfer'),
        'cheque' => lang('Cheque'),
        'other' => lang('Other'),
    );

    $output_pay_controls = '<input type="hidden" name="issue_invoice_seen" value="1">
                            <div class="form-check form-check-inline align-middle me-3">
                                <input class="form-check-input" type="checkbox" name="issue_invoice" value="1" id="issue_invoice"' . (!empty($remembered['invoice']) ? ' checked' : '') . '>
                                <label class="form-check-label" for="issue_invoice">' . lang('Issue the invoice') . '</label>
                            </div>';

    if ($can_take_money && !empty($tills)) {
        $method_options = '<option value="">' . lang('Collect later') . '</option>';
        foreach ($method_labels as $code => $label) {
            $method_options .= '<option value="' . $code . '"' . (((string) ($remembered['method'] ?? '') === $code) ? ' selected' : '') . '>' . h($label) . '</option>';
        }
        $till_options = '';
        foreach ($tills as $till) {
            $till_options .= '<option value="' . (int) $till['id'] . '"' . (((int) $till['id'] === $default_till) ? ' selected' : '') . '>' . h($till['name']) . '</option>';
        }
        $output_pay_controls .= '
                            <select name="pay_method" class="form-select form-select-sm d-inline-block w-auto align-middle me-1" title="' . lang('Payment Method') . '" id="pay_method">' . $method_options . '</select>
                            <select name="pay_till" class="form-select form-select-sm d-inline-block w-auto align-middle me-3" title="' . lang('Till or bank account') . '" id="pay_till">' . $till_options . '</select>';
    }
}

echo
    pg_page_shell(
        array(
            'title'         => lang('Add Local Order'),
            'extra classes' => 'product',
            'icon'          => 'store',
            'heading'       => lang('Add Local Order'),
            'heading_description' => lang('Scan a barcode or search for a product to add items to the cart, then complete the order as a local sale.'),
            'cancel'=>array('enable'=>'true','url'=>'view_orders.php'),
            'breadcrumb' => array(
                array('label' => lang('Orders'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_orders.php'),
                array('label' => lang('Add Local Order')),
            ),
        )
    ) . '

    <main class="container mb-5" style="min-height:calc(100vh - 175px)" id="content">

        <div class="row">
            <div class="col-12">
                
                ' . $liveform->get_messages() . '
            </div>
        </div>

        <div class="row">

            <!-- Left panel: customer, barcode scanner, product search -->
            <div class="col-12 col-md-auto my-2" style="min-width:300px;max-width:420px;">
                <div class="card border-4 border-primary mb-3">
                    <div class="card-body">
                        <label for="customer_search_input" class="form-label">' . lang('Customer') . '</label>
                        <div class="position-relative">
                            <div class="input-group my-2">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" id="customer_search_input"
                                    placeholder="' . lang('Name, company, e-mail or account') . '"
                                    class="form-control"
                                    autocomplete="off">
                            </div>
                            <div id="customer_search_results" class="list-group mt-1" style="display:none;max-height:250px;overflow-y:auto;"></div>
                        </div>
                        <div id="customer_current">' . $output_customer . '</div>
                        <form id="select_customer_form" class="disable_shortcut" method="post" action="' . $page_url . '">
                            ' . get_token_field() . '
                            <input type="hidden" name="action" value="set_customer">
                            <input type="hidden" name="contact_id" id="selected_contact_id" value="0">
                            <input type="hidden" name="account_id" id="selected_account_id" value="0">
                        </form>
                    </div>
                </div>
                <div class="card border-4 border-primary">
                    <div class="card-body">

                        <!-- Barcode scanner input -->
                        <label for="scanner_input" class="form-label"
                            data-bs-content="' . lang('-Last Scan: shows last readed scan for barcode scanners and no need to any action.</br>-Manuel Scan: if scanner read wrong or dont read, you can input manuel and read it with \'Read\' button.') . '"
                            title="' . lang('Last Scan & Manuel Read') . '">' . lang('Last Scan & Manuel Read') . ' (' . lang('what is this?') . ')</label>

                        <form id="scan_form" class="disable_shortcut" method="post" action="' . $page_url . '">
                            ' . get_token_field() . '
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
                            ' . get_token_field() . '
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
                                        <td colspan="4" class="text-end text-secondary">' . lang('Subtotal') . ':</td>
                                        <td class="text-end">' . $cart_total_formatted . '</td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="text-end text-secondary">' . lang('VAT') . ':</td>
                                        <td class="text-end">' . $cart_tax_formatted . '</td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="text-end fw-bold">' . lang('Total') . ':</td>
                                        <td class="text-end fw-bold h5 mb-0">' . $cart_grand_formatted . '</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-reset border-0 text-end">
                        <span class="text-secondary small me-2"><i class="bi bi-person me-1"></i>' . $output_customer_summary . '</span>
                        <form method="post" class="disable_shortcut d-inline" action="' . $page_url . '" id="complete_order_form">
                            ' . get_token_field() . '
                            <input type="hidden" name="action" value="complete_order">
                            ' . $output_pay_controls . '
                            <button type="submit" class="btn btn-success"' . $complete_button_disabled . '>
                                <span class="material-icons me-1 align-middle" style="font-size:1.1rem;">check_circle</span>
                                ' . lang('Complete Order') . '
                            </button>
                        </form>
                        <script>
                        (function () {
                            // Money taken now means an invoice to take it against.
                            var method = document.getElementById("pay_method");
                            var invoice = document.getElementById("issue_invoice");
                            var till = document.getElementById("pay_till");
                            if (!method || !invoice) { return; }
                            var sync = function () {
                                var paying = method.value !== "";
                                if (paying) { invoice.checked = true; }
                                invoice.disabled = paying;
                                if (till) { till.disabled = !paying; }
                            };
                            method.addEventListener("change", sync);
                            sync();
                        })();
                        </script>
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
                                        <th class="text-end">' . lang('Total') . '</th>'
                                        . ($erp_invoicing ? '<th class="text-center">' . lang('Invoice') . '</th>' : '') . '
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
        var stockText = ' . json_encode(lang('In stock: {var:1}')) . ';
        var noCustomersText = ' . json_encode(lang('No customers found.')) . ';

        /* -------------------------------------------------------
           Customer search — contacts and accounts, debounced 300 ms
           ------------------------------------------------------- */
        var customerTimer = null;

        $("#customer_search_input").on("input", function() {
            clearTimeout(customerTimer);
            var q = $(this).val().trim();
            if (q.length < 2) {
                $("#customer_search_results").hide().empty();
                return;
            }
            customerTimer = setTimeout(function() {
                $.getJSON(
                    "' . $page_url . '",
                    { request: "search_customers", q: q },
                    function(data) {
                        var $r = $("#customer_search_results").empty();
                        if (data.results && data.results.length > 0) {
                            $.each(data.results, function(i, c) {
                                var label = $("<span>").text(c.label).prop("outerHTML");
                                var sub = c.sub ? "<br><small class=\"text-secondary\">" + $("<span>").text(c.sub).prop("outerHTML") + "</small>" : "";
                                var icon = c.kind === "account" ? "bi-journal-text" : "bi-person";
                                $("<a>")
                                    .addClass("list-group-item list-group-item-action py-2")
                                    .attr("href", "#")
                                    .html("<i class=\"bi " + icon + " me-2\"></i><strong>" + label + "</strong>" + sub)
                                    .on("click", function(e) {
                                        e.preventDefault();
                                        $("#selected_contact_id").val(c.contact_id);
                                        $("#selected_account_id").val(c.account_id);
                                        $("#customer_search_results").hide().empty();
                                        $("#select_customer_form").submit();
                                    })
                                    .appendTo($r);
                            });
                            $r.show();
                        } else {
                            $r.append(
                                $("<div>").addClass("list-group-item text-secondary py-2").text(noCustomersText)
                            ).show();
                        }
                    }
                );
            }, 300);
        });

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
                                // short_description is the name people know the product by; name is the SKU.
                                var title = p.short_description ? p.short_description : p.name;
                                var name = $("<span>").text(title).prop("outerHTML");
                                var subParts = [];
                                if (p.short_description && p.name !== p.short_description) {
                                    subParts.push($("<span>").text(p.name).prop("outerHTML"));
                                }
                                if (String(p.inventory) === "1") {
                                    subParts.push($("<span>").text(stockText.replace("{var:1}", p.inventory_quantity)).prop("outerHTML"));
                                }
                                var desc = subParts.length
                                    ? "<br><small class=\"text-secondary\">" + subParts.join(" \u00b7 ") + "</small>"
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
            if (!$(e.target).closest("#customer_search_input, #customer_search_results").length) {
                $("#customer_search_results").hide();
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
