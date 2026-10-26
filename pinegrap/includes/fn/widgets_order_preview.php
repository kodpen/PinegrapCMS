<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the Order Preview system widget (kind
 * 'order_preview'): the last step of the classic multi-step checkout, drawn
 * by the designer. The visitor reviews the order's recipients, items and
 * totals, the billing details given on the step before, picks a payment
 * method and places the order.
 *
 * Every figure, payment method and setting comes from get_order_preview()
 * (get_order_preview.php), which hands the widget the values it computes
 * for a custom layout: the widget draws them, it never computes a total of
 * its own. It posts to order_preview.php with the classic page's field
 * names, so submit_order() checks and charges the order as it always did.
 * The page's settings are its order_preview_pages row: a page of the
 * classic type keeps its own, any other page gets one written from the
 * widget's settings, and the handler takes a page that has one
 * (pg_order_page_known()).
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
 * The widget's visibility flags, from the values get_order_preview()
 * computes for a custom layout.
 *
 * @param array $d the layout values ('layout_data_only')
 * @return array
 */
function pg_op_flags($d)
{
    $d = is_array($d) ? $d : array();
    $on = function ($k) use ($d) { return !empty($d[$k]); };
    $filled = function ($k) use ($d) { return isset($d[$k]) && (string)$d[$k] !== ''; };
    $methods = isset($d['number_of_payment_methods']) ? (int)$d['number_of_payment_methods'] : 0;
    $op_flags = array(
        'has_items'                 => $on('recipients'),
        'cart_empty'                => !$on('recipients'),
        'has_shipping'              => $on('shippable_items'),
        'shows_subtotal'            => $on('show_subtotal'),
        'has_discount'              => $filled('discount_info'),
        'has_tax'                   => $filled('tax_info'),
        'has_gift_card_discount'    => $filled('gift_card_discount_info'),
        'has_surcharge'             => $on('show_surcharge'),
        'surcharge_message'         => $on('surcharge_message'),
        'has_currency_disclaimer'   => $on('total_disclaimer'),
        'has_recurring'             => $on('recurring_items'),
        'has_applied_offers'        => !empty($d['number_of_applied_offers']),
        'has_payment'               => $on('payment'),
        'has_gift_card_code'        => $on('gift_card_code'),
        'many_payment_methods'      => $methods > 1,
        'pays_by_card'              => $on('credit_debit_card'),
        'pays_by_paypal'            => $on('paypal_express_checkout'),
        'pays_by_iyzico'            => $on('pay_with_iyzico'),
        'pays_offline'              => $on('offline_payment'),
        'has_card_verification_help' => $filled('card_verification_number_url'),
        'has_installments'          => $on('installment'),
        'has_threedsecure'          => $on('threedsecure'),
        'threedsecure_required'     => $filled('threedsecure_required'),
        'has_terms'                 => $filled('terms_url'),
        'can_purchase'              => $on('purchase_now_button'),
        'has_billing_form'          => $on('billing_form'),
        'has_custom_field_1'        => $filled('custom_field_1_label'),
        'has_custom_field_2'        => $filled('custom_field_2_label'),
        'has_po_number'             => $filled('po_number'),
        'is_tax_exempt'             => $on('tax_exempt'),
    );
    return $op_flags;
}

/**
 * The columns of the page's order_preview_pages row the widget's settings
 * change. Only a setting the widget has is written, so a converted classic
 * page keeps what its own screen stored; no next page leaves the stored one.
 * A receipt page turns the order receipt e-mail on (as HTML), none turns it
 * off, as the express order widget does.
 *
 * @param array|null $row the stored row, null when there is none
 * @param array      $cfg the widget's settings
 * @return array column => value, empty when nothing changes
 */
function pg_op_page_row_changes($row, $cfg)
{
    $cfg = is_array($cfg) ? $cfg : array();
    $want = array();
    if (isset($cfg['next_page_id']) && (int)$cfg['next_page_id'] > 0) $want['next_page_id'] = (int)$cfg['next_page_id'];
    foreach (array('terms_page_id', 'card_verification_number_page_id') as $k) {
        if (array_key_exists($k, $cfg)) $want[$k] = max(0, (int)$cfg[$k]);
    }
    if (array_key_exists('offline_payment_always_allowed', $cfg)) {
        $want['offline_payment_always_allowed'] = empty($cfg['offline_payment_always_allowed']) ? 0 : 1;
    }
    if (array_key_exists('offline_payment_label', $cfg)) {
        $want['offline_payment_label'] = mb_substr(trim((string)$cfg['offline_payment_label']), 0, 255);
    }
    if (array_key_exists('order_receipt_email_page_id', $cfg)) {
        $receipt = max(0, (int)$cfg['order_receipt_email_page_id']);
        $want['order_receipt_email'] = $receipt > 0 ? 1 : 0;
        $want['order_receipt_email_page_id'] = $receipt;
        if ($receipt > 0) $want['order_receipt_email_format'] = 'html';
    }
    if (!is_array($row)) return $want;
    $changes = array();
    foreach ($want as $col => $value) {
        if (!isset($row[$col]) || (string)$row[$col] !== (string)$value) $changes[$col] = $value;
    }
    return $changes;
}

/**
 * A reviewed custom form (get_form_review_info()'s fields) as a description
 * list: the fields the visitor filled, information fields left out. The
 * data is already prepared for output.
 *
 * @param array $fields
 * @return string
 */
function pg_op_review_fields_html($fields)
{
    $rows = '';
    foreach ((array)$fields as $field) {
        if (!is_array($field) || ($field['type'] ?? '') === 'information') continue;
        $data = isset($field['data_info']) ? (string)$field['data_info'] : '';
        if ($data === '') continue;
        $rows .= '<dt class="col-sm-4">' . h((string)($field['label'] ?? '')) . '</dt>'
            . '<dd class="col-sm-8">' . $data . '</dd>';
    }
    return $rows === '' ? '' : '<dl class="row mb-0">' . $rows . '</dl>';
}

// The recipients and their items, as the classic page lists them: the
// ship-to heading with its address, arrival date and shipping method, the
// custom shipping form's answers, then each item with its quantity, price
// and amount, an out-of-stock note, its event, gift card and product form
// details, and a recurring item's schedule.
function _pg_op_recipients_html($d)
{
    $html = '';
    $describe = (($d['product_description_type'] ?? '') === 'short_description') ? 'short_description' : 'full_description';
    foreach ((array)($d['recipients'] ?? array()) as $r) {
        if (empty($r['items'])) continue;
        $html .= '<div class="pg-op-recipient mb-4">';
        if (!empty($r['ship_to_heading'])) {
            $html .= '<div class="d-flex flex-wrap justify-content-between gap-2 mb-2">'
                . '<div><div class="fw-semibold">' . h(lang('Ship to')) . ' ' . h((string)$r['ship_to_name']) . '</div>'
                . '<div class="small text-muted">' . h(trim((string)$r['name'])) . ($r['name'] !== '' ? '<br>' : '') . h((string)$r['address']) . '</div>';
            if (!empty($d['arrival_dates']) && !empty($r['arrival_date_name'])) {
                $arrival = !empty($r['arrival_date_custom'])
                    ? get_absolute_time(array('timestamp' => strtotime((string)$r['arrival_date']), 'type' => 'date', 'size' => 'long'))
                    : (string)$r['arrival_date_name'];
                $html .= '<div class="small">' . h(lang('Requested Arrival Date')) . ': ' . h($arrival) . '</div>';
            }
            if ((string)$r['shipping_method_name'] !== '') {
                $html .= '<div class="small">' . h(lang('Shipping Method')) . ': ' . h((string)$r['shipping_method_name'])
                    . (isset($r['shipping_cost_info']) ? ' (' . $r['shipping_cost_info'] . ')' : '') . '</div>';
            }
            $html .= '</div><a class="small" href="' . h((string)$r['update_url']) . '">' . h(lang('Change')) . '</a></div>';
            if (!empty($r['form']) && !empty($r['form_data'])) {
                $html .= '<div class="small mb-2">' . pg_op_review_fields_html($r['fields']) . '</div>';
            }
        }
        $html .= '<ul class="list-group">';
        foreach ($r['items'] as $item) {
            // The full description is the product's own HTML, the short one text.
            $name = (string)$item[$describe] === '' ? h((string)$item['name'])
                : ($describe === 'short_description' ? h((string)$item[$describe]) : (string)$item[$describe]);
            $html .= '<li class="list-group-item"><div class="d-flex flex-wrap justify-content-between gap-2">'
                . '<div><div>' . $name . '</div><div class="small text-muted">' . h((string)$item['name'])
                . ' &times; ' . (int)$item['quantity'] . (isset($item['price_info']) ? ' @ ' . $item['price_info'] : '') . '</div></div>'
                . '<div class="fw-semibold text-nowrap">' . $item['amount_info'] . '</div></div>';
            if (!empty($item['show_out_of_stock_message'])) {
                $html .= '<div class="small text-danger">' . $item['out_of_stock_message'] . '</div>';
            }
            if (!empty($item['calendar_event']['name'])) {
                $html .= '<div class="small">' . h((string)$item['calendar_event']['name'])
                    . (isset($item['calendar_event']['date_and_time_range']) ? ' — ' . $item['calendar_event']['date_and_time_range'] : '') . '</div>';
            }
            if (!empty($item['recurring'])) {
                $html .= '<div class="small text-muted">' . h(lang('Payment Period')) . ': ' . h(lang((string)$item['recurring_payment_period']))
                    . ((int)$item['recurring_number_of_payments'] > 0 ? ', ' . h(lang('Number of Payments')) . ': ' . (int)$item['recurring_number_of_payments'] : '')
                    . (!empty($d['start_date']) && (string)$item['recurring_start_date'] !== '' ? ', ' . h(lang('Start Date')) . ': ' . h(get_absolute_time(array('timestamp' => strtotime((string)$item['recurring_start_date']), 'type' => 'date', 'size' => 'long'))) : '') . '</div>';
            }
            foreach ((array)($item['gift_cards'] ?? array()) as $n => $card) {
                if (!is_array($card) || (string)$card['recipient_email_address'] === '') continue;
                $html .= '<div class="small">' . h(lang('Gift Card')) . ' ' . ($n + 1) . ': ' . h((string)$card['recipient_email_address'])
                    . ((string)$card['from_name'] !== '' ? ', ' . h(lang('From')) . ' ' . h((string)$card['from_name']) : '') . '</div>';
            }
            foreach ((array)($item['forms'] ?? array()) as $form) {
                if (!empty($form['data'])) $html .= '<div class="small mt-1">' . pg_op_review_fields_html($form['fields']) . '</div>';
            }
            $html .= '</li>';
        }
        $html .= '</ul></div>';
    }
    return $html;
}

// The applied gift cards: code, amount taken from each, the balance left
// and the link that takes the card off the order.
function _pg_op_gift_cards_html($cards)
{
    $html = '';
    foreach ((array)$cards as $card) {
        if (!isset($card['amount_info'])) continue;
        $html .= '<li class="list-group-item d-flex flex-wrap justify-content-between gap-2">'
            . '<span>' . h(lang('Gift Card')) . ' ' . h((string)$card['protected_code'])
            . ' <span class="small text-muted">(' . h(lang('Remaining Balance')) . ': ' . $card['remaining_balance_info'] . ')</span></span>'
            . '<span>-' . $card['amount_info'] . ' <a class="small ms-2" href="' . h((string)$card['remove_url']) . '">' . h(lang('Remove')) . '</a></span></li>';
    }
    return $html === '' ? '' : '<ul class="list-group mb-3">' . $html . '</ul>';
}

// The recurring payments by period: subtotal, tax and total per period.
function _pg_op_payment_periods_html($periods)
{
    $rows = '';
    foreach ((array)$periods as $p) {
        if (empty($p['exists'])) continue;
        $rows .= '<tr><td>' . h(lang((string)$p['name'])) . '</td><td class="text-end">' . $p['subtotal_info'] . '</td>'
            . '<td class="text-end">' . ($p['tax_info'] ?? '') . '</td><td class="text-end fw-semibold">' . $p['total_info'] . '</td></tr>';
    }
    if ($rows === '') return '';
    return '<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>' . h(lang('Payment Period')) . '</th>'
        . '<th class="text-end">' . h(lang('Subtotal')) . '</th><th class="text-end">' . h(lang('Tax')) . '</th>'
        . '<th class="text-end">' . h(lang('Total')) . '</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
}

// The page's order_preview_pages row, made when missing and brought in line
// with the widget's settings.
function _pg_op_page_settings($page_id, $cfg)
{
    $page_id = (int)$page_id;
    if ($page_id <= 0) return;
    $row = db_item("SELECT * FROM order_preview_pages WHERE page_id = '" . $page_id . "'");
    if (!is_array($row)) {
        db("INSERT IGNORE INTO order_preview_pages (page_id) VALUES ('" . $page_id . "')");
        $row = db_item("SELECT * FROM order_preview_pages WHERE page_id = '" . $page_id . "'");
        if (!is_array($row)) return;
    }
    foreach (array('next_page_id', 'terms_page_id', 'card_verification_number_page_id', 'order_receipt_email_page_id') as $k) {
        // A page that is gone is not stored.
        if (!empty($cfg[$k]) && !(int)db_value("SELECT page_id FROM page WHERE page_id = '" . (int)$cfg[$k] . "' LIMIT 1")) unset($cfg[$k]);
    }
    $changes = pg_op_page_row_changes($row, $cfg);
    if ($changes) {
        $set = array();
        foreach ($changes as $col => $value) $set[] = $col . " = '" . e((string)$value) . "'";
        db("UPDATE order_preview_pages SET " . implode(', ', $set) . " WHERE page_id = '" . $page_id . "'");
    }
}

// ============================================================================
// order_preview
// ============================================================================

// Render an 'order_preview' system widget.
//
// Tokens (figures are the classic page's price HTML, the cart's names):
// ^^__cart_subtotal^^, ^^__cart_discount^^, ^^__cart_tax^^,
// ^^__cart_shipping^^, ^^__cart_gift_card_discount^^, ^^__cart_surcharge^^,
// ^^__surcharge_percentage^^, ^^__cart_total^^, ^^__cart_total_with_surcharge^^,
// ^^__cart_total_charged^^ (the total in the base currency charged, while
// the visitor reads another), ^^__currency_disclaimer^^,
// ^^__purchase_now_label^^, the order view's ^^__billing_name^^,
// ^^__billing_company^^, ^^__billing_address_html^^, ^^__billing_email^^,
// ^^__billing_phone^^, and ^^__custom_field_1_label^^, ^^__custom_field_1^^,
// ^^__custom_field_2_label^^, ^^__custom_field_2^^, ^^__po_number^^,
// ^^__billing_form_title^^, ^^__offline_payment_label^^,
// ^^__paypal_image_url^^, ^^__iyzico_image_url^^.
// Links: ^^__billing_update_url^^, ^^__terms_url^^, ^^__card_verification_url^^,
// ^^__cart_url^^.
// Sections: order_recipients, applied_gift_cards, recurring_payments,
// applied_offers, billing_form, installment_table, card_installments.
// Controls by name: payment_method (radios with the method's value),
// card_number, expiration, card_verification_number, installment,
// threedsecure, gift_card_code with the submit_apply_gift_card button, terms,
// and the submit_purchase_now button. Flags: pg_op_flags().
// Errors: liveform 'order_preview'.
// cfg: next_page_id, terms_page_id, card_verification_number_page_id,
// offline_payment_always_allowed, offline_payment_label,
// order_receipt_email_page_id.
function _render_system_widget_order_preview($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();
    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';
    if (!defined('ECOMMERCE') || ECOMMERCE !== true) return '';
    // The visitor's own order; a background render has none to show.
    if ($mode === 'background') return '';
    if (function_exists('pg_page_cache_personal')) pg_page_cache_personal('checkout');

    $page_id = _pg_sa_page_id();
    _pg_op_page_settings($page_id, $cfg);
    if (!function_exists('get_order_preview')) require_once(PG_FUNCTIONS_DIR . '/get_order_preview.php');
    $d = get_order_preview(array('page_id' => $page_id, 'device_type' => '', 'layout_data_only' => true));
    if (!is_array($d)) return '';
    $form = $d['form'];

    $op_flags = pg_op_flags($d);
    _eo_apply_visibility_bindings($tree, $op_flags);
    if (!$op_flags['many_payment_methods']) _pg_member_drop_control($tree, 'payment_method');
    if (!$op_flags['pays_by_card']) {
        foreach (array('card_number', 'expiration', 'card_verification_number', 'threedsecure') as $name) _pg_member_drop_control($tree, $name);
    }
    if (!$op_flags['has_threedsecure']) _pg_member_drop_control($tree, 'threedsecure');
    if (!$op_flags['has_gift_card_code']) {
        _pg_member_drop_control($tree, 'gift_card_code');
        _pg_member_drop_control($tree, 'submit_apply_gift_card');
    }
    if (!$op_flags['has_terms']) _pg_member_drop_control($tree, 'terms');
    if (!$op_flags['can_purchase']) _pg_member_drop_control($tree, 'submit_purchase_now');
    if ($op_flags['threedsecure_required']) {
        // The gateway always asks for 3-D Secure: shown ticked, not changeable.
        _pg_member_edit_control($tree, 'threedsecure', function (&$node) {
            _pg_member_set_attr($node, 'checked', '');
            _pg_member_set_attr($node, 'disabled', '');
        });
    }

    $cart_url = function_exists('pg_sw_cart_url') ? pg_sw_cart_url() : '';
    $links = array(
        '__billing_update_url'     => (string)$d['billing_update_url'],
        '__terms_url'              => (string)$d['terms_url'],
        '__card_verification_url'  => (string)$d['card_verification_number_url'],
        '__cart_url'               => $cart_url,
    );
    _pg_member_drop_empty_links($tree, $links);

    $offers = '';
    foreach ((array)$d['applied_offers'] as $offer) {
        if (is_array($offer)) $offers .= '<li>' . h((string)$offer['description']) . '</li>';
    }
    $installments = '';
    if ($op_flags['pays_by_card'] && function_exists('_eo_render_installment_box')) {
        $installments = _eo_render_installment_box($form);
    }
    pg_cf_apply_section_bindings($tree, array(
        'order_recipients'   => _pg_op_recipients_html($d),
        'applied_gift_cards' => _pg_op_gift_cards_html($d['applied_gift_cards']),
        'recurring_payments' => _pg_op_payment_periods_html($d['payment_periods']),
        'applied_offers'     => $offers === '' ? '' : '<ul class="mb-0">' . $offers . '</ul>',
        'billing_form'       => pg_op_review_fields_html($d['fields']),
        'installment_table'  => (string)$d['installment_table'],
        'card_installments'  => $installments,
    ));
    pg_cf_link_labels($tree);
    _pg_inject_messages_node($tree, 'order_preview');

    // The Messages node uses the form up as it prints it; the values the
    // classic computation set on it (the totals posted back, a single
    // method) are still needed by prepare() below, so they are put back.
    $kept_form = $_SESSION['software']['liveforms']['order_preview'] ?? null;
    $split = _split_widget_tree($tree);
    $rendered = str_replace('<!--pg-loop-slot-->', '', trim(_render_tree_node($split['static_tree'], 0, 0)));
    if ($kept_form !== null) $_SESSION['software']['liveforms']['order_preview'] = $kept_form;

    $billing_name = trim(implode(' ', array_filter(array((string)$d['billing_salutation'],
        (string)$d['billing_first_name'], (string)$d['billing_last_name']), 'strlen')));
    $billing_lines = array_values(array_filter(array(
        (string)$d['billing_address_1'], (string)$d['billing_address_2'],
        trim(implode(' ', array_filter(array((string)$d['billing_zip_code'], (string)$d['billing_city'], (string)$d['billing_state']), 'strlen'))),
        (string)$d['billing_country'],
    ), 'strlen'));
    $tokens = array(
        '__cart_subtotal'           => (string)$d['subtotal_info'],
        '__cart_discount'           => (string)$d['discount_info'],
        '__cart_tax'                => (string)$d['tax_info'],
        '__cart_shipping'           => (string)$d['shipping_info'],
        '__cart_gift_card_discount' => (string)$d['gift_card_discount_info'],
        '__cart_surcharge'          => (string)$d['surcharge_info'],
        '__surcharge_percentage'    => h((string)$d['surcharge_percentage']),
        '__cart_total'              => (string)$d['total_info'],
        '__cart_total_with_surcharge' => (string)$d['total_with_surcharge_info'],
        '__cart_total_charged'      => (string)$d['base_currency_total_info'],
        '__currency_disclaimer'     => $op_flags['has_currency_disclaimer'] ? _eo_currency_disclaimer() : '',
        '__purchase_now_label'      => h((string)$d['purchase_now_button_label']),
        '__billing_name'            => h($billing_name),
        '__billing_company'         => h((string)$d['billing_company']),
        '__billing_address_html'    => implode('<br>', array_map('h', $billing_lines)),
        '__billing_email'           => h((string)$d['billing_email_address']),
        '__billing_phone'           => h((string)$d['billing_phone_number']),
        '__custom_field_1_label'    => h((string)$d['custom_field_1_label']),
        '__custom_field_1'          => h((string)$d['custom_field_1']),
        '__custom_field_2_label'    => h((string)$d['custom_field_2_label']),
        '__custom_field_2'          => h((string)$d['custom_field_2']),
        '__po_number'               => h((string)$d['po_number']),
        '__billing_form_title'      => h((string)$d['billing_form_title']),
        '__offline_payment_label'   => h((string)$d['offline_payment_label']),
        '__paypal_image_url'        => h((string)$d['paypal_express_checkout_image_url']),
        '__iyzico_image_url'        => h((string)$d['pay_with_iyzico_image_url']),
    );
    foreach ($links as $k => $v) $tokens[$k] = h($v);
    $rendered = _pg_member_apply_tokens($rendered, $tokens);

    // An empty cart has no form: the classic page's hidden fields and
    // scripts ($d['system']) come only with an order to place.
    if ($op_flags['has_items']) {
        $script = function_exists('pg_cf_validation_script') ? pg_cf_validation_script() : '';
        $rendered = '<form id="pg-op-form-w' . $widget_id . '" action="' . h(_pg_acct_software_path() . '/order_preview.php')
            . '" method="post" novalidate class="needs-validation pg-cf-form pg-order-preview-form">'
            . $rendered . (string)$d['system'] . $script . '</form>';
        // Values, options and required marks the classic computation set on
        // the form: the totals posted back, the payment method chosen; the
        // checkbox and radio values the visitor posted before.
        $rendered = $form->prepare($rendered);
    }
    $form->remove();
    return $rendered;
}
