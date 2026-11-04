<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * New cart quote: up to ten products with their quantities, optionally for a
 * contact, with the last day its link works (includes/fn/cart_quotes.php).
 * After saving, the link is shown once on the cart quote list: only its hash
 * is kept.
 *
 *   GET ?request=products&q=...   products for the product boxes
 *   GET ?request=contacts&q=...   contacts for the customer box
 *   GET ?contact_id=N             the customer box filled in
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

/** The product rows the form shows. */
define('ADD_CART_QUOTE_ROWS', 10);

$request = is_string($_GET['request'] ?? null) ? $_GET['request'] : '';

// The boxes' searches answer in JSON, a refusal too.
if ($request !== '') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    header('X-Robots-Tag: noindex');

    if (!USER_LOGGED_IN || !defined('USER_MANAGE_ECOMMERCE') || (USER_MANAGE_ECOMMERCE !== true)) {
        http_response_code(403);
        echo json_encode(array('results' => array()));
        exit();
    }

    $q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
    $results = array();

    if ($q !== '') {
        $like = e(escape_like(mb_substr($q, 0, 100)));

        if ($request === 'products') {
            foreach ((array) db_items("SELECT id, name, short_description FROM products
                WHERE enabled = '1' AND selection_type != 'donation'
                    AND (name LIKE '%" . $like . "%' OR short_description LIKE '%" . $like . "%')
                ORDER BY short_description, name LIMIT 20") as $row) {
                $results[] = array('id' => (int) $row['id'], 'text' => trim((string) $row['short_description'] . ' (' . (string) $row['name'] . ')'));
            }
        } elseif ($request === 'contacts') {
            foreach ((array) db_items("SELECT id, first_name, last_name, company, email_address FROM contacts
                WHERE first_name LIKE '%" . $like . "%' OR last_name LIKE '%" . $like . "%'
                    OR CONCAT(first_name, ' ', last_name) LIKE '%" . $like . "%'
                    OR company LIKE '%" . $like . "%' OR email_address LIKE '%" . $like . "%'
                ORDER BY last_name, first_name LIMIT 20") as $row) {
                $results[] = array('id' => (int) $row['id'], 'text' => add_cart_quote_contact_text($row));
            }
        }
    }

    echo json_encode(array('results' => $results), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    exit();
}

$user = validate_user();
validate_ecommerce_access($user);

if (!defined('ECOMMERCE') || (ECOMMERCE !== true)) {
    output_error(lang('E-commerce is not enabled on this site.'));
}

if (!pg_cart_quotes_ready()) {
    output_error(lang('Cart quotes are not installed yet: the database has to be updated first.'));
}

/**
 * A contact as the customer box names it: the name, or the company, and the
 * e-mail address.
 *
 * @param array $row contacts row
 * @return string
 */
function add_cart_quote_contact_text($row)
{
    $person = trim(trim((string) $row['first_name']) . ' ' . trim((string) $row['last_name']));
    $name = ($person !== '') ? $person : trim((string) $row['company']);
    $email = trim((string) $row['email_address']);

    return trim(($name !== '' ? $name : '#' . (int) $row['id']) . (($email !== '') ? ' · ' . $email : ''));
}

/**
 * One Summary row of the status column: a label and the value the script
 * keeps up to date.
 *
 * @param string $label  The label, plain text
 * @param string $id     The id of the value
 * @param string $value  The value, plain text
 * @param bool   $last   False draws the line under the row
 * @return string
 */
function add_cart_quote_summary_row($label, $id, $value, $last)
{
    return '
                                <div class="d-flex justify-content-between gap-3 py-2' . ($last ? '' : ' border-bottom') . '">
                                    <dt class="fw-normal">' . h($label) . '</dt>
                                    <dd class="mb-0 fw-bold text-end text-break" id="' . h($id) . '">' . h($value) . '</dd>
                                </div>';
}

$liveform = new liveform('add_cart_quote');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_token_field();
    $liveform->add_fields_to_session();

    $lines = array();

    for ($row = 1; $row <= ADD_CART_QUOTE_ROWS; $row++) {
        $product = $liveform->get_field_value('product_' . $row);

        // A row left empty is no line; a chosen product's quantity is
        // checked on its own box, so the row with the wrong one is marked.
        if ((string) $product !== '') {
            $quantity = trim((string) $liveform->get_field_value('quantity_' . $row));

            if (!pg_cart_quote_quantity_valid($quantity)) {
                $liveform->mark_error('quantity_' . $row, h(lang(array(
                    'string' => 'Product {var:1}: enter a whole quantity from 1 to {var:2}.',
                    'vars'   => array($row, PG_CART_QUOTE_MAX_QUANTITY)))));
            }

            $lines[] = array('product_id' => (string) $product, 'quantity' => $quantity);
        }
    }

    $until = pg_cart_quote_until(prepare_form_data_for_input((string) $liveform->get_field_value('valid_until'), 'date'));
    $contact = is_scalar($liveform->get_field_value('contact_id')) ? (int) $liveform->get_field_value('contact_id') : 0;

    if ($until === null) {
        $liveform->mark_error('valid_until', h(lang('Enter a valid date.')));
    }

    // The contact, the last day and the products meet the checks the API's
    // POST /cart-quotes meets too (pg_cart_quote_create_check()).
    if (!$liveform->check_form_errors()) {
        $result = pg_cart_quote_create($contact, $lines, (int) $until, (int) $user['id']);

        if ($result['ok']) {
            log_activity(lang(array('string' => 'cart quote {var:1} was created', 'vars' => '#' . $result['id'])));
            $liveform->remove_form();

            $list = new liveform('view_cart_quotes');
            $list->add_notice(h(lang('The cart quote was saved. Copy its link now: it is not shown again.'))
                . '<div class="mt-5"><input type="text" class="form-control" readonly value="' . h(pg_cart_quote_url($result['token'])) . '" aria-label="' . h(lang('Cart quote link')) . '"></div>');

            go(PATH . SOFTWARE_DIRECTORY . '/view_cart_quotes.php' . (($contact > 0) ? '?contact_id=' . $contact : ''));
        }

        $liveform->mark_error(in_array($result['field'], array('contact_id', 'valid_until'), true) ? $result['field'] : '_error', h($result['error']));
    }

    go(PATH . SOFTWARE_DIRECTORY . '/add_cart_quote.php');
}

// The first visit: the contact the screen was opened for, and the default
// last day.
if (!$liveform->field_in_session('valid_until')) {
    $liveform->set('valid_until', prepare_form_data_for_output(date('Y-m-d', strtotime('+' . PG_CART_QUOTE_DEFAULT_DAYS . ' days')), 'date'));

    // Each quantity starts at one; after a refusal the boxes show what was
    // sent, an emptied one included.
    for ($row = 1; $row <= ADD_CART_QUOTE_ROWS; $row++) {
        $liveform->set('quantity_' . $row, '1');
    }

    $given = is_scalar($_GET['contact_id'] ?? null) ? (int) $_GET['contact_id'] : 0;

    if ($given > 0) {
        $liveform->set('contact_id', $given);
    }
}

// A chosen contact or product is drawn as the box's selected option.
$selected = function ($value, $text) {
    return ((string) $value !== '') ? '<option value="' . h((string) $value) . '" selected>' . h($text) . '</option>' : '';
};

$contact_value = (int) $liveform->get_field_value('contact_id');
$contact_row = ($contact_value > 0) ? db_item("SELECT id, first_name, last_name, company, email_address FROM contacts WHERE id = '" . $contact_value . "'") : null;

// The back arrow, Cancel and the breadcrumb go where Save goes: the list of
// the chosen contact's quotes.
$back = 'view_cart_quotes.php' . (is_array($contact_row) ? '?contact_id=' . (int) $contact_row['id'] : '');

$output_rows = '';
$chosen_products = 0;
$chosen_quantity = 0;

for ($row = 1; $row <= ADD_CART_QUOTE_ROWS; $row++) {
    $product_value = (int) $liveform->get_field_value('product_' . $row);
    $product_row = ($product_value > 0) ? db_item("SELECT id, name, short_description FROM products WHERE id = '" . $product_value . "'") : null;
    $quantity = (string) $liveform->get_field_value('quantity_' . $row);

    if (is_array($product_row)) {
        $chosen_products++;
        $chosen_quantity += max(0, (int) $quantity);
    }

    $output_rows .= '
                    <div class="row g-5 mb-5">
                        <div class="col-12 sm:col-9">
                            <select class="form-control" id="product_' . $row . '" name="product_' . $row . '" aria-label="' . h(lang(array('string' => 'Product {var:1}', 'vars' => $row))) . '" data-pg-combobox data-pg-source="add_cart_quote.php?request=products" data-pg-min-length="2" data-placeholder="' . h(lang('Search for a product')) . '">
                                <option value=""></option>
                                ' . (is_array($product_row) ? $selected($product_row['id'], trim($product_row['short_description'] . ' (' . $product_row['name'] . ')')) : '') . '
                            </select>
                        </div>
                        <div class="col-12 sm:col-3">
                            <input class="form-control' . ($liveform->check_field_error('quantity_' . $row) ? ' is-invalid' : '') . '" type="number" min="1" max="' . PG_CART_QUOTE_MAX_QUANTITY . '" step="1" id="quantity_' . $row . '" name="quantity_' . $row . '" value="' . h($quantity) . '" aria-label="' . h(lang(array('string' => 'Quantity {var:1}', 'vars' => $row))) . '">
                        </div>
                    </div>';
}

echo
    pg_page_shell(array(
        'title'               => lang('New cart quote'),
        'extra classes'       => 'store',
        'icon'                => 'store',
        'heading'             => lang('New cart quote'),
        'heading_description' => lang('The link puts these products into the customer\'s cart at the store\'s prices on the day it is used.'),
        'cancel'              => array('enable' => 'true', 'url' => $back, 'title' => lang('Cart quotes')),
        'breadcrumb'          => array(
            array('label' => lang('Cart quotes'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/' . $back),
            array('label' => lang('New cart quote')),
        ),
    )) . '
<main id="content" class="container-fluid">
    ' . $liveform->output_errors() . '
    ' . $liveform->output_notices() . '
    <form action="add_cart_quote.php" method="post" id="cart_quote_form">
        ' . get_token_field() . '
        <div class="row mt-7">
            <div class="col-12 lg:col-8 2xl:col-9 order-1">
                <div class="card mb-7">
                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . h(lang('Customer')) . '</h2>
                    <div class="card-body pt-0">
                        <label class="form-label visually-hidden" for="contact_id">' . h(lang('Customer')) . '</label>
                        <select class="form-control' . ($liveform->check_field_error('contact_id') ? ' is-invalid' : '') . '" id="contact_id" name="contact_id" aria-describedby="contact_id_help" data-pg-combobox data-pg-source="add_cart_quote.php?request=contacts" data-pg-min-length="2" data-placeholder="' . h(lang('Search for a contact')) . '">
                            <option value=""></option>
                            ' . (is_array($contact_row) ? $selected($contact_row['id'], add_cart_quote_contact_text($contact_row)) : '') . '
                        </select>
                        <div id="contact_id_help" class="form-text">' . h(lang('Optional. The link never shows whom the quote was made for.')) . '</div>
                    </div>
                </div>
                <div class="card mb-7">
                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . h(lang('Products and quantities')) . '</h2>
                    <div class="card-body pt-0">
                        ' . $output_rows . '
                        <div class="form-text">' . h(lang('A product that is disabled, out of stock or outside its minimum and maximum when the link is used is left out or adjusted in the cart.')) . '</div>
                    </div>
                </div>
            </div>
            <div class="col-12 lg:col-4 2xl:col-3 order-2">
                <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                <div class="position-sticky" style="top:3.3rem;">
                    <div class="card mb-7">
                        <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . h(lang('Validity')) . '</h2>
                        <div class="card-body pt-0">
                            <label class="form-label" for="valid_until">' . h(lang('Valid until')) . '</label>
                            ' . $liveform->output_field(array('type' => 'text', 'id' => 'valid_until', 'name' => 'valid_until', 'class' => 'form-control', 'size' => '10', 'maxlength' => '10', 'aria-describedby' => 'valid_until_help', 'data-pg-date' => '')) . '
                            <div id="valid_until_help" class="form-text">' . h(lang('The link works through this day. Leave it empty for a link without an end.')) . '</div>
                        </div>
                    </div>
                    <div class="card mb-7">
                        <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . h(lang('Summary')) . '</h2>
                        <div class="card-body pt-0">
                            <dl class="mb-0">'
                                . add_cart_quote_summary_row(lang('Customer'), 'cart_quote_summary_customer', is_array($contact_row) ? add_cart_quote_contact_text($contact_row) : lang('Not chosen'), false)
                                . add_cart_quote_summary_row(lang('Products'), 'cart_quote_summary_products', (string) $chosen_products, false)
                                . add_cart_quote_summary_row(lang('Total quantity'), 'cart_quote_summary_quantity', (string) $chosen_quantity, false)
                                . add_cart_quote_summary_row(lang('Valid until'), 'cart_quote_summary_until', ((string) $liveform->get_field_value('valid_until') !== '') ? (string) $liveform->get_field_value('valid_until') : lang('No end'), true) . '
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            <a class="btn my-1 btn-outline theme-secondary" href="' . h($back) . '"><span class="pg-btn-label">' . h(lang('Cancel')) . '</span></a>
                            <button type="submit" class="btn my-1 btn-solid theme-primary"><span class="bi bi-link-45deg me-3" aria-hidden="true"></span><span class="pg-btn-label">' . h(lang('Save and make the link')) . '</span></button>
                        </div>
                    </div>
                </nav>
            </div>
        </div>
    </form>
    <script>
        // The Summary follows the boxes: the chosen customer, how many
        // product boxes hold a product and their quantities, the last day.
        // jQuery hears both native changes and a pick of the date picker.
        (function () {
            var form = document.getElementById("cart_quote_form");
            var rows = ' . ADD_CART_QUOTE_ROWS . ';
            var texts = ' . json_encode(array('none' => lang('Not chosen'), 'no_end' => lang('No end')), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . ';
            function text(id, value) { document.getElementById(id).textContent = value; }
            function update() {
                var contact = document.getElementById("contact_id");
                var option = contact.options[contact.selectedIndex];
                text("cart_quote_summary_customer", (contact.value !== "" && option) ? option.text.trim() : texts.none);
                var products = 0, quantity = 0;
                for (var n = 1; n <= rows; n++) {
                    if (document.getElementById("product_" + n).value !== "") {
                        products++;
                        var amount = parseInt(document.getElementById("quantity_" + n).value, 10);
                        quantity += (amount > 0) ? amount : 0;
                    }
                }
                text("cart_quote_summary_products", String(products));
                text("cart_quote_summary_quantity", String(quantity));
                var until = document.getElementById("valid_until").value.trim();
                text("cart_quote_summary_until", (until !== "") ? until : texts.no_end);
            }
            jQuery(form).on("change input", update);
        })();
    </script>
</main>
' . get_date_picker_format() . pg_fields_script() . output_footer();

$liveform->remove_form();
