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

$liveform = new liveform('add_cart_quote');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_token_field();
    $liveform->add_fields_to_session();

    $lines = array();

    for ($row = 1; $row <= ADD_CART_QUOTE_ROWS; $row++) {
        $product = $liveform->get_field_value('product_' . $row);

        // A row left empty is no line.
        if ((string) $product !== '') {
            $lines[] = array('product_id' => (string) $product, 'quantity' => trim((string) $liveform->get_field_value('quantity_' . $row)));
        }
    }

    $until = pg_cart_quote_until(prepare_form_data_for_input((string) $liveform->get_field_value('valid_until'), 'date'));
    $contact = is_scalar($liveform->get_field_value('contact_id')) ? (int) $liveform->get_field_value('contact_id') : 0;

    if ($until === null) {
        $liveform->mark_error('valid_until', h(lang('Enter a valid date.')));
    } elseif (($until > 0) && ($until < time())) {
        $liveform->mark_error('valid_until', h(lang('The last day of the link cannot be in the past.')));
    }

    if (($contact > 0) && ((int) db_value("SELECT COUNT(*) FROM contacts WHERE id = '" . $contact . "'") === 0)) {
        $liveform->mark_error('contact_id', h(lang('The contact could not be found.')));
    }

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

        $liveform->mark_error('_error', h($result['error']));
    }

    go(PATH . SOFTWARE_DIRECTORY . '/add_cart_quote.php');
}

// The first visit: the contact the screen was opened for, and the default
// last day.
if (!$liveform->field_in_session('valid_until')) {
    $liveform->set('valid_until', prepare_form_data_for_output(date('Y-m-d', strtotime('+' . PG_CART_QUOTE_DEFAULT_DAYS . ' days')), 'date'));

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

$output_rows = '';

for ($row = 1; $row <= ADD_CART_QUOTE_ROWS; $row++) {
    $product_value = (int) $liveform->get_field_value('product_' . $row);
    $product_row = ($product_value > 0) ? db_item("SELECT id, name, short_description FROM products WHERE id = '" . $product_value . "'") : null;
    $quantity = (string) $liveform->get_field_value('quantity_' . $row);

    $output_rows .= '
                    <div class="row g-5 mb-5">
                        <div class="col-9">
                            <select class="form-control" id="product_' . $row . '" name="product_' . $row . '" aria-label="' . h(lang(array('string' => 'Product {var:1}', 'vars' => $row))) . '" data-pg-combobox data-pg-source="add_cart_quote.php?request=products" data-pg-min-length="2" data-placeholder="' . h(lang('Search for a product')) . '">
                                <option value=""></option>
                                ' . (is_array($product_row) ? $selected($product_row['id'], trim($product_row['short_description'] . ' (' . $product_row['name'] . ')')) : '') . '
                            </select>
                        </div>
                        <div class="col-3">
                            <input class="form-control" type="number" min="1" step="1" id="quantity_' . $row . '" name="quantity_' . $row . '" value="' . h(($quantity !== '') ? $quantity : '1') . '" aria-label="' . h(lang(array('string' => 'Quantity {var:1}', 'vars' => $row))) . '">
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
        'breadcrumb'          => array(
            array('label' => lang('Cart quotes'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_cart_quotes.php'),
            array('label' => lang('New cart quote')),
        ),
    )) . '
<main id="content" class="container">
    ' . $liveform->output_errors() . '
    ' . $liveform->output_notices() . '
    <form action="add_cart_quote.php" method="post">
        ' . get_token_field() . '
        <div class="card my-7">
            <div class="card-body">
                <div class="mb-12">
                    <label class="form-label" for="contact_id">' . h(lang('Customer')) . '</label>
                    <select class="form-control" id="contact_id" name="contact_id" data-pg-combobox data-pg-source="add_cart_quote.php?request=contacts" data-pg-min-length="2" data-placeholder="' . h(lang('Search for a contact')) . '">
                        <option value=""></option>
                        ' . (is_array($contact_row) ? $selected($contact_row['id'], add_cart_quote_contact_text($contact_row)) : '') . '
                    </select>
                    <div class="form-text">' . h(lang('Optional. The link never shows whom the quote was made for.')) . '</div>
                </div>
                <fieldset class="mb-12">
                    <legend class="form-label fs-md">' . h(lang('Products and quantities')) . '</legend>
                    ' . $output_rows . '
                    <div class="form-text">' . h(lang('A product that is disabled, out of stock or outside its minimum and maximum when the link is used is left out or adjusted in the cart.')) . '</div>
                </fieldset>
                <div class="mb-5">
                    <label class="form-label" for="valid_until">' . h(lang('Valid until')) . '</label>
                    ' . $liveform->output_field(array('type' => 'text', 'id' => 'valid_until', 'name' => 'valid_until', 'class' => 'form-control', 'size' => '10', 'maxlength' => '10', 'data-pg-date' => '')) . '
                    <div class="form-text">' . h(lang('The link works through this day. Leave it empty for a link without an end.')) . '</div>
                </div>
            </div>
        </div>
        <div class="buttons position-sticky text-end" style="bottom:.5rem;">
            <a class="btn btn-outline theme-secondary" href="view_cart_quotes.php">' . h(lang('Cancel')) . '</a>
            <button type="submit" class="btn btn-solid theme-primary"><i class="bi bi-link-45deg me-1" aria-hidden="true"></i>' . h(lang('Save and make the link')) . '</button>
        </div>
    </form>
</main>
' . get_date_picker_format() . pg_fields_script() . output_footer();

$liveform->remove_form();
