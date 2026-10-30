<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the Shipping Method system widget (kind
 * 'shipping_method'): the second step of the classic multi-step checkout,
 * drawn by the designer. One recipient of the order (?ship_to_id=, as the
 * shipping address step sends the visitor here) picks one of the methods
 * that can ship its items to its address by its requested arrival date.
 *
 * The widget posts to shipping_method.php with the classic page's field
 * names (shipping_method, page_id, next_page_id, ship_to_id), so the
 * handler's checks, the cost it stores and its redirects stay what they
 * were. The methods and their costs come from get_shipping_methods()
 * (shipping.php), the list the express order widget offers too.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * A recipient's address as lines: company, address lines, "zip city
 * state", country, the empty ones left out.
 *
 * @param array  $recipient    ship_tos columns
 * @param string $country_name the country's name ('' to print its code)
 * @return array
 */
function pg_sm_address_lines($recipient, $country_name = '')
{
    $r = is_array($recipient) ? $recipient : array();
    $get = function ($k) use ($r) { return isset($r[$k]) ? trim((string)$r[$k]) : ''; };
    $state = ($get('state') !== $get('city')) ? $get('state') : '';
    return array_values(array_filter(array(
        $get('company'),
        $get('address_1'),
        $get('address_2'),
        trim(implode(' ', array_filter(array($get('zip_code'), $get('city'), $state), 'strlen'))),
        $country_name !== '' ? $country_name : $get('country'),
    ), 'strlen'));
}

/**
 * The method chosen when the page opens: the one posted before a refused
 * post, else the one stored on the recipient, while it is still offered;
 * the only method when there is one; else none.
 *
 * @param array  $method_ids the offered methods' ids
 * @param string $posted     the liveform's value ('' when none)
 * @param string $stored     ship_tos.shipping_method_id
 * @return string
 */
function pg_sm_chosen_method($method_ids, $posted, $stored)
{
    $ids = array_map('strval', (array)$method_ids);
    foreach (array((string)$posted, (string)$stored) as $want) {
        if ($want !== '' && $want !== '0' && in_array($want, $ids, true)) return $want;
    }
    return count($ids) === 1 ? $ids[0] : '';
}

/**
 * The widget's visibility flags.
 *
 * @param bool $has_recipient   a recipient of the visitor's order is shown
 * @param bool $multi_recipient the shop ships to several recipients per order
 * @param int  $methods         number of methods offered
 * @param bool  $has_arrival     the recipient asked for an arrival date
 * @param array $currencies      pg_sw_currency_rows()
 * @return array
 */
function pg_sm_flags($has_recipient, $multi_recipient, $methods, $has_arrival, $currencies = array())
{
    $sm_flags = array(
        'has_recipient'        => (bool)$has_recipient,
        'no_recipient'         => !$has_recipient,
        'is_multi_recipient'   => (bool)$multi_recipient,
        'has_shipping_methods' => $has_recipient && $methods > 0,
        'no_shipping_methods'  => $has_recipient && $methods === 0,
        'has_arrival_date'     => $has_recipient && $has_arrival,
        'has_currency_choice'  => count((array)$currencies) > 1,
    );
    return $sm_flags;
}

// The recipient's items, as the classic page lists them: name, quantity
// and amount, a product that cannot go to this address marked, with the
// link that takes it out of the cart.
function _pg_sm_items_html($ship_to_id, $order_id, $country, $state)
{
    $items = (array)db_items(
        "SELECT order_items.id, order_items.product_id, order_items.product_name, order_items.quantity,
            order_items.price, products.short_description
        FROM order_items
        LEFT JOIN products ON products.id = order_items.product_id
        WHERE (order_items.order_id = '" . (int)$order_id . "')" . pg_order_item_active_sql() . "
            AND (order_items.ship_to_id = '" . (int)$ship_to_id . "')
        ORDER BY order_items.id");
    if (!$items) return '';
    $restriction = (defined('ECOMMERCE_PRODUCT_RESTRICTION_MESSAGE') && ECOMMERCE_PRODUCT_RESTRICTION_MESSAGE)
        ? (string)ECOMMERCE_PRODUCT_RESTRICTION_MESSAGE
        : lang('This item cannot be delivered to the specified shipping address. Please remove it from your order to continue.');
    $token = isset($_SESSION['software']['token']) ? (string)$_SESSION['software']['token'] : '';
    $html = '<ul class="list-group pg-sm-items">';
    foreach ($items as $item) {
        $name = (string)$item['short_description'] !== '' ? (string)$item['short_description'] : (string)$item['product_name'];
        $html .= '<li class="list-group-item d-flex flex-wrap justify-content-between gap-2">'
            . '<span>' . h($name) . ' <span class="text-muted">&times; ' . (int)$item['quantity'] . '</span></span>'
            . '<span class="fw-semibold">' . prepare_price_for_output((int)$item['price'] * (int)$item['quantity'], false, '', 'html') . '</span>';
        if (!validate_product_for_destination($item['product_id'], $country, $state)) {
            $remove = PATH . SOFTWARE_DIRECTORY . '/remove_item_from_cart.php?order_item_id=' . (int)$item['id']
                . '&screen=shipping_method&send_to=' . urlencode(REQUEST_URL) . '&token=' . urlencode($token);
            $html .= '<span class="w-100 small text-danger">' . h($restriction)
                . ' <a href="' . h($remove) . '" class="link-danger">' . h(lang('Remove')) . '</a></span>';
        }
        $html .= '</li>';
    }
    return $html . '</ul>';
}

// ============================================================================
// shipping_method
// ============================================================================

// Render a 'shipping_method' system widget.
//
// loop_area: one row per method offered, cheapest first. A radio named
// shipping_method (its value set per row, required) is checked for the
// method chosen (pg_sm_chosen_method()). Row tokens:
// ^^__shipping_method_name^^, ^^__shipping_method_description^^,
// ^^__shipping_method_cost^^ (HTML: struck through when an offer lowers it).
// Row flag: shipping_method_discounted.
// Tokens: ^^__recipient_name^^ (ship-to name), ^^__recipient_full_name^^,
// ^^__recipient_address^^ (one line), ^^__recipient_address_html^^ (lines),
// ^^__requested_arrival_date^^, ^^__change_address_url^^ (the shipping
// address step for this recipient), ^^__no_shipping_methods_message^^,
// ^^__cart_url^^.
// Section: recipient_items (the recipient's items; a product that cannot go
// to the address carries the link that removes it).
// Currency picker: the currency_id select and the currency_update button
// (pg_sw_currency_picker()), shown by has_currency_choice.
// Flags: pg_sm_flags(). Errors: liveform 'shipping_method'.
// cfg: next_page_id (else the classic page's own, else the handler's).
function _render_system_widget_shipping_method($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();
    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';
    if (!defined('ECOMMERCE') || ECOMMERCE !== true) return '';
    // The form is the visitor's own order.
    if (function_exists('pg_page_cache_personal')) pg_page_cache_personal('checkout');
    if (!function_exists('get_shipping_methods')) require_once(PG_FUNCTIONS_DIR . '/shipping.php');

    $page_id = _pg_sa_page_id();
    $next_page_id = isset($cfg['next_page_id']) ? (int)$cfg['next_page_id'] : 0;
    if ($next_page_id <= 0 && $page_id > 0) {
        $next_page_id = (int)db_value("SELECT next_page_id FROM shipping_method_pages WHERE page_id = '" . (int)$page_id . "'");
    }
    if ($next_page_id > 0 && $mode !== 'background') {
        // The cart sends the visitor on to the billing step from here.
        $_SESSION['ecommerce']['billing_information_page_id'] = $next_page_id;
    }

    // The recipient, refused as the classic page refuses it.
    $ship_to_id = (isset($_GET['ship_to_id']) && is_scalar($_GET['ship_to_id']) && ctype_digit((string)$_GET['ship_to_id']))
        ? (int)$_GET['ship_to_id'] : 0;
    if (($_GET['from'] ?? '') === 'control_panel') $ship_to_id = 0;
    $recipient = null;
    if ($ship_to_id > 0) {
        $recipient = db_item(
            "SELECT ship_tos.*, orders.status AS order_status
            FROM ship_tos
            LEFT JOIN orders ON ship_tos.order_id = orders.id
            WHERE ship_tos.id = '" . $ship_to_id . "'");
        $problem = pg_sa_recipient_problem(is_array($recipient) ? $recipient : null, $_SESSION['ecommerce']['order_id'] ?? '');
        if ($problem !== '') {
            if ($mode !== 'edit' && $mode !== 'background') output_error(pg_sa_recipient_problem_message($problem));
            $recipient = null;
        }
    }
    $has_recipient = is_array($recipient);
    $multi = defined('ECOMMERCE_RECIPIENT_MODE') && ECOMMERCE_RECIPIENT_MODE === 'multi-recipient';

    $lf = new liveform('shipping_method');
    $methods = array();
    $none_message = '';
    $arrival = '';
    $country_name = '';
    $items_html = '';
    if ($has_recipient) {
        $resp = get_shipping_methods(array(
            'ship_to_id'      => $ship_to_id,
            'address_1'       => (string)$recipient['address_1'],
            'state'           => (string)$recipient['state'],
            'zip_code'        => (string)$recipient['zip_code'],
            'country'         => (string)$recipient['country'],
            'arrival_date_id' => 0,
            'arrival_date'    => (string)$recipient['arrival_date'],
        ));
        if (is_array($resp) && ($resp['status'] ?? '') === 'success') {
            $methods = array_values((array)$resp['shipping_methods']);
        } else {
            $none_message = (defined('ECOMMERCE_NO_SHIPPING_METHODS_MESSAGE') && ECOMMERCE_NO_SHIPPING_METHODS_MESSAGE)
                ? (string)ECOMMERCE_NO_SHIPPING_METHODS_MESSAGE
                : (is_array($resp) ? (string)($resp['message'] ?? '') : '');
        }
        $date = (int)$recipient['arrival_date_id'] > 0
            ? db_item("SELECT name, custom FROM arrival_dates WHERE id = '" . (int)$recipient['arrival_date_id'] . "'") : null;
        if (is_array($date)) {
            $arrival = $date['custom']
                ? get_absolute_time(array('timestamp' => strtotime((string)$recipient['arrival_date']), 'type' => 'date', 'size' => 'long', 'format' => 'plain_text'))
                : (string)$date['name'];
        }
        $country_name = (string)db_value("SELECT name FROM countries WHERE code = '" . e($recipient['country']) . "'");
        $items_html = _pg_sm_items_html($ship_to_id, $recipient['order_id'], (string)$recipient['country'], (string)$recipient['state']);
    }

    $change_url = '';
    $sa_page = isset($_SESSION['ecommerce']['shipping_address_and_arrival_page_id']) ? (int)$_SESSION['ecommerce']['shipping_address_and_arrival_page_id'] : 0;
    if ($has_recipient && $sa_page > 0) {
        $sa_url = _pg_member_page_url($sa_page);
        if ($sa_url !== '') $change_url = $sa_url . '?ship_to_id=' . $ship_to_id;
    }
    $cart_url = function_exists('pg_sw_cart_url') ? pg_sw_cart_url() : '';
    _pg_member_drop_empty_links($tree, array('__change_address_url' => $change_url, '__cart_url' => $cart_url));

    // A method row's own flag is decided per row, below.
    $currencies = pg_sw_currency_rows();
    $sm_flags = pg_sm_flags($has_recipient, $multi, count($methods), $arrival !== '', $currencies);
    _eo_apply_visibility_bindings($tree, $sm_flags + array('shipping_method_discounted' => true));
    pg_sw_currency_picker($tree, $widget_id, $currencies, defined('VISITOR_CURRENCY_ID') ? (int)VISITOR_CURRENCY_ID : 0);
    pg_cf_apply_section_bindings($tree, array('recipient_items' => $items_html));
    pg_cf_link_labels($tree);
    _pg_inject_messages_node($tree, 'shipping_method');

    $ids = array();
    foreach ($methods as $m) $ids[] = (string)$m['id'];
    $chosen = pg_sm_chosen_method($ids,
        $lf->field_in_session('shipping_method') ? (string)$lf->get_field_value('shipping_method') : '',
        $has_recipient ? (string)$recipient['shipping_method_id'] : '');

    $split = _split_widget_tree($tree);
    $rows = '';
    if ($split['loop_children'] !== null && $methods) {
        $row_tree = array('type' => 'root', 'props' => array(), 'children' => $split['loop_children']);
        foreach ($methods as $m) {
            $mid = (int)$m['id'];
            $t = $row_tree;
            _eo_apply_visibility_bindings($t, array('shipping_method_discounted' => !empty($m['discounted'])));
            _pg_member_edit_control($t, 'shipping_method', function (&$node) use ($mid) {
                _pg_member_set_attr($node, 'value', (string)$mid);
            });
            _pg_acct_check_value($t, 'shipping_method', $chosen);
            $html = '';
            foreach ($t['children'] as $child) $html .= _render_tree_node($child, 0, 0);
            $html = _pg_sw_fill_tokens($html, array(
                '__shipping_method_name'        => h((string)$m['name']),
                '__shipping_method_description' => h((string)$m['description']),
                '__shipping_method_cost'        => (string)$m['cost_info'],
            ));
            $rows .= pg_sw_uniquify_row_ids(pg_sw_sweep_tokens($html), $widget_id, $mid);
        }
    }
    $rendered = str_replace('<!--pg-loop-slot-->', $rows, trim(_render_tree_node($split['static_tree'], 0, 0)));
    $lf->remove();

    $full_name = $has_recipient ? trim(implode(' ', array_filter(array((string)$recipient['salutation'],
        (string)$recipient['first_name'], (string)$recipient['last_name']), 'strlen'))) : '';
    $lines = $has_recipient ? pg_sm_address_lines($recipient, $country_name) : array();
    $tokens = array(
        '__recipient_name'             => h($has_recipient ? (string)$recipient['ship_to_name'] : ''),
        '__recipient_full_name'        => h($full_name),
        '__recipient_address'          => h(implode(', ', $lines)),
        '__recipient_address_html'     => implode('<br>', array_map('h', $lines)),
        '__requested_arrival_date'     => h($arrival),
        '__change_address_url'         => h($change_url),
        '__no_shipping_methods_message' => h($none_message),
        '__cart_url'                   => h($cart_url),
    );
    // The picker's own form follows the widget's: forms cannot nest.
    $currency_form = pg_sw_currency_form_html($widget_id, $currencies, get_request_uri());
    if (!$has_recipient || !$methods) return _pg_member_apply_tokens($rendered, $tokens) . $currency_form;

    $rendered = _pg_member_form_wrap($rendered, _pg_acct_software_path() . '/shipping_method.php', array(
        'page_id'      => $page_id,
        'next_page_id' => $next_page_id > 0 ? $next_page_id : '',
        'ship_to_id'   => $ship_to_id,
    ), 'pg-shipping-method-form');

    return _pg_member_apply_tokens($rendered, $tokens) . $currency_form;
}
