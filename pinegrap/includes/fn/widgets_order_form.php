<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the Order Form system widget (kind 'order_form'):
 * the classic "order form" page drawn by the designer. The products of one
 * product group, each with its check box, quantity box or donation amount
 * (the list layout), or one pick list of them with a quantity (the
 * drop-down layout); the visitor puts many products into the cart at once.
 *
 * The widget posts to order_form.php with the classic page's field names
 * (product_<id>, donation_<id>, recurring_*_<id>, product_id, quantity,
 * ship_to, add_name, submit_add, submit_skip, page_id), so the handler's
 * checks, the items it adds and its redirects stay what they were. The
 * handler reads the page's order_form_pages row: a page of the classic type
 * keeps its own, any other page gets one written from the widget's
 * settings when it is drawn.
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
 * Whether a product can be ordered: stock is not tracked, some is left, or
 * it may be backordered (the classic page's and the handler's test).
 *
 * @param array $product products columns
 * @return bool
 */
function pg_of_product_available($product)
{
    $p = is_array($product) ? $product : array();
    return empty($p['inventory']) || (int)($p['inventory_quantity'] ?? 0) > 0 || !empty($p['backorder']);
}

/**
 * The product layout a page uses: 'drop-down selection', else 'list'.
 *
 * @param mixed $layout
 * @return string
 */
function pg_of_layout($layout)
{
    return ((string)$layout === 'drop-down selection') ? 'drop-down selection' : 'list';
}

/**
 * The widget's visibility flags.
 *
 * @param string $layout    pg_of_layout()
 * @param array  $products  the group's products, each with 'available'
 *                          and 'selection_type'
 * @param bool   $recipient the visitor picks the recipient (multi-recipient
 *                          shipping and an available shippable product)
 * @param string $skip_label the skip button's label ('' for none)
 * @return array
 */
function pg_of_flags($layout, $products, $recipient, $skip_label)
{
    $available = 0;
    $non_donations = 0;
    foreach ((array)$products as $p) {
        if (empty($p['available'])) continue;
        $available++;
        if ((string)($p['selection_type'] ?? '') !== 'donation') $non_donations++;
    }
    $list = (pg_of_layout($layout) === 'list');
    $of_flags = array(
        'has_products'         => $available > 0,
        'no_products'          => $available === 0,
        'is_list_layout'       => $list,
        'is_dropdown_layout'   => !$list,
        // The drop-down layout asks for a quantity unless every product it
        // offers is a donation.
        'has_quantity'         => !$list && $non_donations > 0,
        'has_recipient_choice' => $available > 0 && (bool)$recipient,
        'has_skip_button'      => trim((string)$skip_label) !== '',
    );
    return $of_flags;
}

/**
 * A product row's flags (list layout): which control it carries, by its
 * selection type, while it can be ordered; else it is unavailable.
 *
 * @param array $product with 'available', 'selection_type', 'recurring',
 *                       'recurring_schedule_editable_by_customer'
 * @return array
 */
function pg_of_row_flags($product)
{
    $p = is_array($product) ? $product : array();
    $on = !empty($p['available']);
    $type = (string)($p['selection_type'] ?? '');
    $rf = array(
        'order_form_checkbox'    => $on && $type === 'checkbox',
        'order_form_quantity'    => $on && $type === 'quantity',
        'order_form_donation'    => $on && $type === 'donation',
        'order_form_unavailable' => !$on,
        'has_recurring_schedule' => $on && !empty($p['recurring']) && !empty($p['recurring_schedule_editable_by_customer']),
    );
    return $rf;
}

/**
 * The columns of the page's order_form_pages row the widget's settings
 * change. Only a key the widget has is written, so a page of the classic
 * type keeps what its own screen stored; a product group or next page of 0
 * means "the page's own setting" and leaves the stored one.
 *
 * @param array|null $row the stored row, null when there is none
 * @param array      $cfg the widget's settings
 * @return array column => value, empty when nothing changes
 */
function pg_of_page_row_changes($row, $cfg)
{
    $cfg = is_array($cfg) ? $cfg : array();
    $want = array();
    foreach (array('product_group_id', 'add_button_next_page_id', 'skip_button_next_page_id') as $k) {
        if (isset($cfg[$k]) && (int)$cfg[$k] > 0) $want[$k] = (int)$cfg[$k];
    }
    if (array_key_exists('product_layout', $cfg)) $want['product_layout'] = pg_of_layout($cfg['product_layout']);
    foreach (array('add_button_label', 'skip_button_label') as $k) {
        if (array_key_exists($k, $cfg)) $want[$k] = mb_substr(trim((string)$cfg[$k]), 0, 50);
    }
    if (!is_array($row)) return $want;
    $changes = array();
    foreach ($want as $col => $value) {
        if (!isset($row[$col]) || (string)$row[$col] !== (string)$value) $changes[$col] = $value;
    }
    return $changes;
}

// The page's order_form_pages row, made when missing and brought in line
// with the widget's settings; returned as it now stands.
function _pg_of_page_settings($page_id, $cfg, $write)
{
    $page_id = (int)$page_id;
    if ($page_id <= 0) return array();
    $row = db_item("SELECT * FROM order_form_pages WHERE page_id = '" . $page_id . "'");
    if (!$write) return is_array($row) ? $row : array();
    if (!is_array($row)) {
        db("INSERT IGNORE INTO order_form_pages (page_id) VALUES ('" . $page_id . "')");
        $row = db_item("SELECT * FROM order_form_pages WHERE page_id = '" . $page_id . "'");
        if (!is_array($row)) return array();
    }
    foreach (array('add_button_next_page_id', 'skip_button_next_page_id') as $k) {
        // A page that is gone is not stored.
        if (!empty($cfg[$k]) && !(int)db_value("SELECT page_id FROM page WHERE page_id = '" . (int)$cfg[$k] . "' LIMIT 1")) unset($cfg[$k]);
    }
    $changes = pg_of_page_row_changes($row, $cfg);
    if ($changes) {
        $set = array();
        foreach ($changes as $col => $value) {
            $set[] = $col . " = '" . e((string)$value) . "'";
            $row[$col] = $value;
        }
        db("UPDATE order_form_pages SET " . implode(', ', $set) . " WHERE page_id = '" . $page_id . "'");
    }
    return $row;
}

// The enabled products of an enabled group, in the group's order, as the
// classic page reads them.
function _pg_of_products($group_id)
{
    $group_id = (int)$group_id;
    if ($group_id <= 0 || !db_value("SELECT enabled FROM product_groups WHERE id = '" . $group_id . "'")) return array();
    return (array)db_items(
        "SELECT products.id, products.name, products.image_name, products.short_description,
            products.full_description, products.price, products.selection_type, products.default_quantity,
            products.shippable, products.recurring, products.recurring_schedule_editable_by_customer,
            products.start, products.number_of_payments, products.payment_period, products.inventory,
            products.inventory_quantity, products.backorder, products.out_of_stock_message
        FROM products_groups_xref
        LEFT JOIN products ON products.id = products_groups_xref.product
        WHERE (products_groups_xref.product_group = '" . $group_id . "') AND (products.enabled = '1')
        ORDER BY products_groups_xref.sort_order, products.name");
}

// A recurring product's payment schedule, with the classic field names:
// frequency, number of payments and, unless the gateway sets it, the start
// date with its date picker. The values come from the liveform; on a first
// visit the product's own schedule.
function _pg_of_schedule_html($product, $lf, $prefill)
{
    $id = (int)$product['id'];
    $with_start = !ECOMMERCE_CREDIT_DEBIT_CARD || (ECOMMERCE_PAYMENT_GATEWAY != 'ClearCommerce');
    $period = (string)$lf->get_field_value('recurring_payment_period_' . $id);
    $count = (string)$lf->get_field_value('recurring_number_of_payments_' . $id);
    $start = (string)$lf->get_field_value('recurring_start_date_' . $id);
    if ($prefill) {
        $period = (string)$product['payment_period'];
        $count = (string)$product['number_of_payments'];
        $start = date((DATE_FORMAT == 'month_day' ? 'n/j' : 'j/n') . '/Y', time() + (86400 * (int)$product['start']));
    }
    if ($count === '0') $count = '';
    $required = ECOMMERCE_CREDIT_DEBIT_CARD
        && in_array(ECOMMERCE_PAYMENT_GATEWAY, array('ClearCommerce', 'First Data Global Gateway'), true);
    $options = '';
    foreach (get_payment_period_options() as $label => $value) {
        $options .= '<option value="' . h($value) . '"' . ((string)$value === $period ? ' selected' : '') . '>' . h($label) . '</option>';
    }
    $f = 'of_' . $id . '_';
    $html = '<fieldset class="border rounded p-3 mt-2"><legend class="fs-6 fw-semibold w-auto px-1">' . h(lang('Payment Schedule')) . '</legend>'
        . '<div class="row g-2">'
        . '<div class="col-sm-4"><label class="form-label small" for="' . $f . 'period">' . h(lang('Frequency')) . '*</label>'
        . '<select class="form-select form-select-sm" id="' . $f . 'period" name="recurring_payment_period_' . $id . '">' . $options . '</select></div>'
        . '<div class="col-sm-4"><label class="form-label small" for="' . $f . 'count">' . h(lang('Number of Payments')) . ($required ? '*' : '') . '</label>'
        . '<input type="text" class="form-control form-control-sm" id="' . $f . 'count" name="recurring_number_of_payments_' . $id . '" maxlength="9" value="' . h($count) . '">'
        . '<div class="form-text">' . h(trim(get_number_of_payments_message())) . '</div></div>';
    if ($with_start) {
        // The classic page's date picker reads the box by its id.
        $html .= '<div class="col-sm-4"><label class="form-label small" for="recurring_start_date_' . $id . '">' . h(lang('Start Date')) . '*</label>'
            . '<input type="text" class="form-control form-control-sm" id="recurring_start_date_' . $id . '" name="recurring_start_date_' . $id . '" maxlength="10" value="' . h($start) . '">'
            . '<script>software_$("#recurring_start_date_' . $id . '").datepicker(datetimepicker_options);</script></div>';
    }
    return $html . '</div></fieldset>';
}

/**
 * One product row of the list layout: the row's blocks kept by its flags,
 * its control named for the product (product_<id> or donation_<id>) and
 * filled, its schedule section and tokens filled.
 *
 * @param array  $row_tree  the loop_area's children under a root node
 * @param array  $product   products columns with 'available' and
 *                          'price_html' (the classic price HTML)
 * @param array  $posted    the liveform's 'product' and 'donation' values
 * @param bool   $prefill   the form is new: a quantity box opens with the
 *                          product's default quantity
 * @param string $schedule  the payment schedule's HTML ('' for none)
 * @param int    $widget_id
 * @return string
 */
function pg_of_row_html($row_tree, $product, $posted, $prefill, $schedule, $widget_id)
{
    $p = $product;
    $pid = (int)$p['id'];
    $t = $row_tree;
    $rf = pg_of_row_flags($p);
    _eo_apply_visibility_bindings($t, $rf);
    if (!$rf['order_form_checkbox'] && !$rf['order_form_quantity']) _pg_member_drop_control($t, 'product');
    if (!$rf['order_form_donation']) _pg_member_drop_control($t, 'donation');
    $value = (string)($posted['product'] ?? '');
    _pg_member_edit_control($t, 'product', function (&$node) use ($pid, $rf, $p, $value, $prefill) {
        _pg_member_set_attr($node, 'name', 'product_' . $pid);
        if ($rf['order_form_checkbox']) {
            _pg_member_set_attr($node, 'value', '1');
            _pg_member_set_attr($node, 'checked', $value !== '' ? '' : null);
        } else {
            $qty = $prefill ? (string)(int)$p['default_quantity'] : $value;
            _pg_member_set_attr($node, 'value', ($qty === '' || $qty === '0') ? null : $qty);
        }
    });
    $donation = (string)($posted['donation'] ?? '');
    _pg_member_edit_control($t, 'donation', function (&$node) use ($pid, $donation) {
        _pg_member_set_attr($node, 'name', 'donation_' . $pid);
        _pg_member_set_attr($node, 'value', $donation === '' ? null : $donation);
    });
    pg_cf_apply_section_bindings($t, array('recurring_schedule' => (string)$schedule));
    $html = '';
    foreach ($t['children'] as $child) $html .= _render_tree_node($child, 0, 0);
    // The classic page adds the out-of-stock words of a product whose stock
    // ran out, backordered or not.
    $out_of_stock = (!empty($p['inventory']) && (int)$p['inventory_quantity'] === 0
        && (string)$p['out_of_stock_message'] !== '<p></p>') ? (string)$p['out_of_stock_message'] : '';
    $html = _pg_sw_fill_tokens($html, array(
        '__id'                   => (string)$pid,
        '__name'                 => h((string)$p['name']),
        '__short_description'    => h((string)$p['short_description']),
        '__description'          => (string)$p['full_description'],
        '__price_formatted'      => $rf['order_form_donation'] ? '' : (string)($p['price_html'] ?? ''),
        '__image_url'            => (string)$p['image_name'] !== '' ? h(PATH . encode_url_path((string)$p['image_name'])) : '',
        '__image_alt'            => h((string)$p['short_description']),
        '__out_of_stock_message' => $out_of_stock,
    ));
    return pg_sw_uniquify_row_ids(pg_sw_sweep_tokens($html), $widget_id, $pid);
}

// ============================================================================
// order_form
// ============================================================================

// Render an 'order_form' system widget.
//
// loop_area (list layout): one row per enabled product of the group, in the
// group's order. Row tokens (the catalog's names): ^^__id^^, ^^__name^^,
// ^^__short_description^^, ^^__description^^ (rich text),
// ^^__price_formatted^^ (HTML: struck through when an offer lowers it),
// ^^__image_url^^, ^^__image_alt^^, ^^__out_of_stock_message^^ (rich text).
// Row controls by name, renamed per row: `product` (check box or quantity
// box, named product_<id>) and `donation` (donation_<id>). Row flags:
// pg_of_row_flags(). Row section: recurring_schedule.
// Controls by name: product_id and quantity (drop-down layout), ship_to and
// add_name (the recipient), the submit_add and submit_skip buttons.
// Tokens: ^^__add_button_label^^, ^^__skip_button_label^^,
// ^^__currency_symbol^^, ^^__cart_url^^.
// Flags: pg_of_flags(). Errors: liveform 'order_form'.
// cfg: product_group_id, product_layout, add_button_label,
// add_button_next_page_id, skip_button_label, skip_button_next_page_id.
function _render_system_widget_order_form($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();
    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';
    if (!defined('ECOMMERCE') || ECOMMERCE !== true) return '';
    // Offer prices, the visitor's recipients and a refused post's values.
    if (function_exists('pg_page_cache_personal')) pg_page_cache_personal('checkout');

    $page_id = _pg_sa_page_id();
    $row = _pg_of_page_settings($page_id, $cfg, $mode !== 'background');
    // The widget's settings over the stored row: the same values once
    // written, and still read where nothing is written (no page, a
    // background render).
    $settings = array_merge($row, pg_of_page_row_changes(null, $cfg));
    $setting = function ($k) use ($settings) {
        return $settings[$k] ?? '';
    };
    $layout = pg_of_layout($setting('product_layout'));
    $add_label = trim((string)$setting('add_button_label'));
    if ($add_label === '') $add_label = lang('Continue');
    $skip_label = trim((string)$setting('skip_button_label'));

    $lf = new liveform('order_form');
    $prefill = $lf->is_empty();
    $products = _pg_of_products($setting('product_group_id'));
    $discounted = $products ? get_discounted_product_prices() : array();
    $shippable = false;
    foreach ($products as $i => $p) {
        $products[$i]['available'] = pg_of_product_available($p);
        if ($products[$i]['available'] && !empty($p['shippable'])) $shippable = true;
    }
    $recipient = defined('ECOMMERCE_SHIPPING') && ECOMMERCE_SHIPPING
        && defined('ECOMMERCE_RECIPIENT_MODE') && ECOMMERCE_RECIPIENT_MODE === 'multi-recipient' && $shippable;
    $of_flags = pg_of_flags($layout, $products, $recipient, $skip_label);

    $price_html = function ($p) use ($discounted) {
        $id = $p['id'];
        return prepare_price_for_output($p['price'], isset($discounted[$id]), isset($discounted[$id]) ? $discounted[$id] : '', 'html');
    };

    // A row's own flags are decided per row, below.
    $row_flags = array('order_form_checkbox' => true, 'order_form_quantity' => true, 'order_form_donation' => true,
        'order_form_unavailable' => true, 'has_recurring_schedule' => true);
    _eo_apply_visibility_bindings($tree, $of_flags + $row_flags);
    if (!$of_flags['has_products']) {
        foreach (array('product_id', 'quantity', 'ship_to', 'add_name', 'submit_add') as $name) _pg_member_drop_control($tree, $name);
    }
    if (!$of_flags['has_quantity']) _pg_member_drop_control($tree, 'quantity');
    if (!$of_flags['has_recipient_choice']) {
        _pg_member_drop_control($tree, 'ship_to');
        _pg_member_drop_control($tree, 'add_name');
    }
    if (!$of_flags['has_skip_button']) _pg_member_drop_control($tree, 'submit_skip');

    // The drop-down layout's pick list: each available product with its
    // price; one the stock leaves out carries its out-of-stock words.
    if ($of_flags['is_dropdown_layout']) {
        $choices = array();
        foreach ($products as $p) {
            $label = (string)$p['short_description'] . ' (' . prepare_price_for_output($p['price'], isset($discounted[$p['id']]), isset($discounted[$p['id']]) ? $discounted[$p['id']] : '', 'plain_text') . ')';
            $message = trim(convert_html_to_text((string)$p['out_of_stock_message']));
            if (!$p['available'] && $message !== '') {
                $label .= ' - ' . (mb_strlen($message) > 50 ? mb_substr($message, 0, 50) . '...' : $message);
            }
            $choices[] = array((string)$p['id'], html_entity_decode($label, ENT_QUOTES, 'UTF-8'));
        }
        _pg_acct_select_options($tree, 'product_id', $choices, (string)$lf->get_field_value('product_id'));
        $qty = $prefill ? '1' : (string)$lf->get_field_value('quantity');
        _pg_member_fill_control($tree, 'quantity', $qty);
    }
    if ($of_flags['has_recipient_choice']) {
        _pg_acct_select_options($tree, 'ship_to', _pg_acct_pairs(get_recipient_options()), (string)$lf->get_field_value('ship_to'));
        _pg_member_fill_control($tree, 'add_name', (string)$lf->get_field_value('add_name'));
    }
    _pg_member_edit_control($tree, 'submit_add', function (&$node) use ($add_label) {
        _pg_member_set_attr($node, 'value', $add_label);
    });
    _pg_member_edit_control($tree, 'submit_skip', function (&$node) use ($skip_label) {
        _pg_member_set_attr($node, 'value', $skip_label);
    });
    $cart_url = function_exists('pg_sw_cart_url') ? pg_sw_cart_url() : '';
    _pg_member_drop_empty_links($tree, array('__cart_url' => $cart_url));
    pg_cf_link_labels($tree);
    _pg_inject_messages_node($tree, 'order_form');

    // The rows are filled before the Messages node prints the liveform and
    // uses it up.
    $split = _split_widget_tree($tree);
    $rows = '';
    $has_schedule = false;
    if ($split['loop_children'] !== null && $of_flags['is_list_layout'] && $products) {
        $row_tree = array('type' => 'root', 'props' => array(), 'children' => $split['loop_children']);
        foreach ($products as $p) {
            $pid = (int)$p['id'];
            $schedule = '';
            if (pg_of_row_flags($p)['has_recurring_schedule']) {
                $schedule = _pg_of_schedule_html($p, $lf, $prefill);
                $has_schedule = true;
            }
            $p['price_html'] = $price_html($p);
            $rows .= pg_of_row_html($row_tree, $p, array(
                'product'  => (string)$lf->get_field_value('product_' . $pid),
                'donation' => (string)$lf->get_field_value('donation_' . $pid),
            ), $prefill, $schedule, $widget_id);
        }
    }
    $rendered = str_replace('<!--pg-loop-slot-->', $rows, trim(_render_tree_node($split['static_tree'], 0, 0)));
    $lf->remove();

    $tokens = array(
        '__add_button_label'  => h($add_label),
        '__skip_button_label' => h($skip_label),
        '__currency_symbol'   => h(defined('VISITOR_CURRENCY_SYMBOL') ? (string)VISITOR_CURRENCY_SYMBOL : ''),
        '__cart_url'          => h($cart_url),
    );
    $rendered = _pg_member_apply_tokens($rendered, $tokens);
    if (!$of_flags['has_products'] && !$of_flags['has_skip_button']) return $rendered;

    // The start date boxes' picker reads its options from the classic script.
    if ($has_schedule && (!ECOMMERCE_CREDIT_DEBIT_CARD || ECOMMERCE_PAYMENT_GATEWAY != 'ClearCommerce')) {
        $rendered = get_date_picker_format() . $rendered;
    }
    return _pg_member_form_wrap($rendered, _pg_acct_software_path() . '/order_form.php', array(
        'page_id'         => $page_id,
        'require_cookies' => 'true',
    ), 'pg-order-form');
}
