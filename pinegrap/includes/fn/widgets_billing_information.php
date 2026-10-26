<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the Billing Information system widget (kind
 * 'billing_information'): the third step of the classic multi-step
 * checkout, drawn by the designer. The visitor's order gets its billing
 * address, e-mail, phone and the order's own fields (custom fields, PO
 * number, referral source, tax exemption, the custom billing form).
 *
 * The widget posts to billing_information.php with the classic page's
 * field names, so the handler's checks, the contact it updates and its
 * redirects stay what they were. The page's settings are its
 * billing_information_pages row, which the handler reads: a page of the
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
 * The billing controls the widget fills from the order or the contact, by
 * name, with the contact column each falls back to ('' for none).
 *
 * @return array control name => contacts column
 */
function pg_bi_address_fields()
{
    return array(
        'billing_salutation'    => 'salutation',
        'billing_first_name'    => 'first_name',
        'billing_last_name'     => 'last_name',
        'billing_company'       => 'company',
        'billing_address_1'     => 'business_address_1',
        'billing_address_2'     => 'business_address_2',
        'billing_city'          => 'business_city',
        'billing_state'         => 'business_state',
        'billing_zip_code'      => 'business_zip_code',
        'billing_country'       => 'business_country',
        'billing_phone_number'  => 'business_phone',
        'billing_fax_number'    => 'business_fax',
        'billing_email_address' => 'email_address',
        'custom_field_1'        => '',
        'custom_field_2'        => '',
        'po_number'             => '',
        'referral_source'       => 'lead_source',
    );
}

/**
 * The form's values when the page opens, as the classic page starts them:
 * the order's value, else the contact's; the referral source from the
 * order's code; tax number and office from the contact; the opt-in and the
 * tax exemption from the order; "update my contact" on unless the visitor
 * turned it off before.
 *
 * @param array $order   orders row (billing_*, custom_field_*, po_number, opt_in, tax_exempt, referral_source_code)
 * @param array $contact contacts row
 * @param bool  $update_contact_off the visitor unticked "update my contact" earlier in the session
 * @return array control name => value
 */
function pg_bi_start_values($order, $contact, $update_contact_off)
{
    $order = is_array($order) ? $order : array();
    $contact = is_array($contact) ? $contact : array();
    $values = array();
    foreach (pg_bi_address_fields() as $name => $contact_col) {
        $order_col = ($name === 'referral_source') ? 'referral_source_code' : $name;
        $own = isset($order[$order_col]) ? (string)$order[$order_col] : '';
        if ($own === '' && $contact_col !== '' && isset($contact[$contact_col])) $own = (string)$contact[$contact_col];
        $values[$name] = $own;
    }
    $values['tax_number'] = isset($contact['tax_number']) ? (string)$contact['tax_number'] : '';
    $values['tax_office'] = isset($contact['tax_office']) ? (string)$contact['tax_office'] : '';
    $values['opt_in'] = (!empty($order['opt_in']) && (string)$order['opt_in'] === '1') ? '1' : '';
    $values['tax_exempt'] = (!empty($order['tax_exempt']) && (string)$order['tax_exempt'] === '1') ? '1' : '';
    $values['update_contact'] = $update_contact_off ? '' : '1';
    return $values;
}

/**
 * The columns of the page's billing_information_pages row the widget's
 * settings change: only the settings the widget has, so a page of the
 * classic type keeps what its own screen stored until the panel sets one.
 * A next page of 0 changes nothing.
 *
 * @param array|null $row the stored row, null when there is none
 * @param array      $cfg the widget's settings
 * @return array column => value, empty when nothing changes
 */
function pg_bi_page_row_changes($row, $cfg)
{
    $want = array();
    if (isset($cfg['next_page_id']) && (int)$cfg['next_page_id'] > 0) $want['next_page_id'] = (int)$cfg['next_page_id'];
    foreach (array('po_number', 'custom_field_1_required', 'custom_field_2_required') as $k) {
        if (array_key_exists($k, $cfg)) $want[$k] = empty($cfg[$k]) ? 0 : 1;
    }
    foreach (array('custom_field_1_label', 'custom_field_2_label') as $k) {
        if (array_key_exists($k, $cfg)) $want[$k] = mb_substr(trim((string)$cfg[$k]), 0, 255);
    }
    if (!is_array($row)) return $want;
    $changes = array();
    foreach ($want as $col => $value) {
        if (!isset($row[$col]) || (string)$row[$col] !== (string)$value) $changes[$col] = $value;
    }
    return $changes;
}

/**
 * The widget's visibility flags.
 *
 * @param array $row      the page's billing_information_pages row
 * @param bool  $opt_in   the opt-in box is offered (no contact, or one not opted in)
 * @param bool  $signed_in a member is signed in, not ghosting
 * @param bool  $referral the site has referral sources
 * @param bool  $tax_exempt tax is on and tax exemption allowed
 * @return array
 */
function pg_bi_flags($row, $opt_in, $signed_in, $referral, $tax_exempt)
{
    $bi_flags = array(
        'has_custom_field_1'   => isset($row['custom_field_1_label']) && (string)$row['custom_field_1_label'] !== '',
        'has_custom_field_2'   => isset($row['custom_field_2_label']) && (string)$row['custom_field_2_label'] !== '',
        'has_po_number'        => !empty($row['po_number']),
        'has_custom_form'      => !empty($row['form']),
        'shows_opt_in'         => (bool)$opt_in,
        'can_update_contact'   => (bool)$signed_in,
        'has_referral_sources' => (bool)$referral,
        'has_tax_exempt'       => (bool)$tax_exempt,
    );
    return $bi_flags;
}

// The page's billing_information_pages row, made when missing and brought
// in line with the widget's settings.
function _pg_bi_page_settings($page_id, $cfg)
{
    $page_id = (int)$page_id;
    if ($page_id <= 0) return array();
    $row = db_item("SELECT * FROM billing_information_pages WHERE page_id = '" . $page_id . "'");
    $changes = pg_bi_page_row_changes(is_array($row) ? $row : null, $cfg);
    if (!is_array($row)) {
        db("INSERT IGNORE INTO billing_information_pages (page_id) VALUES ('" . $page_id . "')");
        $row = db_item("SELECT * FROM billing_information_pages WHERE page_id = '" . $page_id . "'");
        if (!is_array($row)) return array();
    }
    if ($changes) {
        $set = array();
        foreach ($changes as $col => $value) $set[] = $col . " = '" . e((string)$value) . "'";
        db("UPDATE billing_information_pages SET " . implode(', ', $set) . " WHERE page_id = '" . $page_id . "'");
        $row = array_merge($row, $changes);
    }
    return $row;
}

// ============================================================================
// billing_information
// ============================================================================

// Render a 'billing_information' system widget.
//
// Controls by name (the classic page's): billing_salutation, billing_first_
// name, billing_last_name, billing_company, billing_address_1/2,
// billing_city, billing_state (a text box and a pick list, both named so:
// the country decides which posts), billing_zip_code, billing_country,
// billing_phone_number, billing_fax_number, billing_email_address,
// custom_field_1/2, po_number, referral_source (<select>), tax_number,
// tax_office, tax_exempt, opt_in, update_contact. A control the page does not
// ask for is dropped: the custom fields without a label, the PO number when
// off, the referral source without sources, tax exemption when off, the
// opt-in for a contact already opted in, "update my contact" for a visitor
// who is not signed in.
// Tokens: ^^__custom_field_1_label^^, ^^__custom_field_2_label^^,
// ^^__tax_exempt_label^^, ^^__opt_in_label^^, ^^__custom_form_title^^,
// ^^__cart_url^^. Section: custom_billing_form. Flags: pg_bi_flags().
// Errors: liveform 'billing_information'.
// cfg: next_page_id, po_number, custom_field_1_label/_required,
// custom_field_2_label/_required.
function _render_system_widget_billing_information($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();
    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';
    if (!defined('ECOMMERCE') || ECOMMERCE !== true) return '';
    // The form is the visitor's own order.
    if (function_exists('pg_page_cache_personal')) pg_page_cache_personal('checkout');

    $page_id  = _pg_sa_page_id();
    $settings = _pg_bi_page_settings($page_id, $cfg);
    if ($page_id > 0 && $mode !== 'background') {
        // The checkout comes back here when the billing step is needed again.
        $_SESSION['ecommerce']['billing_information_page_id'] = $page_id;
    }
    $order_id = (int)($_SESSION['ecommerce']['order_id'] ?? 0);
    $ghost = !empty($_SESSION['software']['ghost']);
    $signed_in = defined('USER_LOGGED_IN') && USER_LOGGED_IN && !$ghost;

    $contact = $signed_in && defined('USER_CONTACT_ID')
        ? db_item("SELECT * FROM contacts WHERE id = '" . (int)USER_CONTACT_ID . "'") : null;
    if (!is_array($contact)) $contact = array();

    $lf = new liveform('billing_information');
    $custom_form = !empty($settings['form']);
    if ($lf->field_in_session('billing_first_name')) {
        $values = array();
        foreach (array_keys(pg_bi_address_fields()) as $name) $values[$name] = (string)$lf->get_field_value($name);
        foreach (array('tax_number', 'tax_office', 'opt_in', 'tax_exempt', 'update_contact') as $name) {
            $values[$name] = (string)$lf->get_field_value($name);
        }
    } else {
        $order = $order_id > 0 ? db_item("SELECT * FROM orders WHERE id = '" . $order_id . "'") : null;
        $values = pg_bi_start_values($order, $contact,
            $signed_in && (isset($_SESSION['software']['update_contact']) && $_SESSION['software']['update_contact'] === false));
        if ($values['billing_country'] === '') $values['billing_country'] = _pg_acct_default_country();
        if ($custom_form && $order_id > 0) {
            _pg_sa_prefill_custom_form($lf, "form_data.order_id = '" . $order_id . "' AND form_data.ship_to_id = '0' AND form_data.order_item_id = '0'");
        }
    }

    $opt_in = empty($contact['id']) || empty($contact['opt_in']);
    $referral = array();
    foreach ((array)db_items("SELECT name, code FROM referral_sources ORDER BY sort_order, name") as $r) {
        $referral[] = array((string)$r['code'], (string)$r['name']);
    }
    $tax_exempt = defined('ECOMMERCE_TAX') && ECOMMERCE_TAX && defined('ECOMMERCE_TAX_EXEMPT') && ECOMMERCE_TAX_EXEMPT;
    $bi_flags = pg_bi_flags($settings, $opt_in, $signed_in, count($referral) > 0, $tax_exempt);
    _eo_apply_visibility_bindings($tree, $bi_flags);

    // The controls a switched-off part leaves are dropped with it.
    $off = array(
        'custom_field_1'  => !$bi_flags['has_custom_field_1'],
        'custom_field_2'  => !$bi_flags['has_custom_field_2'],
        'po_number'       => !$bi_flags['has_po_number'],
        'referral_source' => !$bi_flags['has_referral_sources'],
        'tax_exempt'      => !$bi_flags['has_tax_exempt'],
        'opt_in'          => !$bi_flags['shows_opt_in'],
        'update_contact'  => !$bi_flags['can_update_contact'],
    );
    foreach ($off as $name => $drop) {
        if ($drop) _pg_member_drop_control($tree, $name);
    }
    foreach (array('custom_field_1', 'custom_field_2') as $name) {
        $required = !empty($settings[$name . '_required']);
        _pg_member_edit_control($tree, $name, function (&$node) use ($required) {
            _pg_member_set_attr($node, 'required', $required ? '' : null);
        });
    }

    $states = array();
    foreach ((array)db_items("SELECT states.code, states.name, countries.code AS country_code FROM states
        LEFT JOIN countries ON countries.id = states.country_id ORDER BY states.name") as $s) {
        $states[(string)$s['country_code']][] = array((string)$s['code'], (string)$s['name']);
    }
    $zip = array();
    foreach ((array)db_items("SELECT code FROM countries WHERE zip_code_required = 1") as $c) $zip[] = (string)$c['code'];

    $fill = $values;
    foreach (array('billing_state', 'opt_in', 'tax_exempt', 'update_contact') as $k) unset($fill[$k]);
    $selects = array(
        'billing_salutation' => _pg_acct_pairs(get_salutation_options()),
        'billing_country'    => _pg_acct_country_options(),
    );
    if ($referral) $selects['referral_source'] = array_merge(array(array('', '')), $referral);
    _pg_acct_fill_values($tree, $fill, $selects);
    _pg_sa_state_controls($tree, $values['billing_state'],
        isset($states[$values['billing_country']]) ? $states[$values['billing_country']] : array(), 'billing_state');
    foreach (array('opt_in', 'tax_exempt', 'update_contact') as $name) {
        _pg_member_fill_control($tree, $name, '1', $values[$name] === '1');
    }

    $cart_url = function_exists('pg_sw_cart_url') ? pg_sw_cart_url() : '';
    _pg_member_drop_empty_links($tree, array('__cart_url' => $cart_url));

    // The custom billing form: the classic page's rows, its own markup.
    $scripts = '';
    $sections = array('custom_billing_form' => '');
    if ($custom_form) {
        $info = get_form_info($page_id, 0, 0, 0, (string)$settings['form_label_column_width'], false, $lf, 'frontend', false, 'desktop', 0);
        if (trim((string)$info['content']) !== '') {
            $sections['custom_billing_form'] = '<table class="custom_billing_form">' . $info['content'] . '</table>';
        }
        if (!empty($info['wysiwyg_fields'])) $scripts .= get_wysiwyg_editor_code($info['wysiwyg_fields']);
    }
    pg_cf_apply_section_bindings($tree, $sections);
    pg_cf_link_labels($tree);
    _pg_inject_messages_node($tree, 'billing_information');

    $split = _split_widget_tree($tree);
    $rendered = str_replace('<!--pg-loop-slot-->', '', trim(_render_tree_node($split['static_tree'], 0, 0)));
    $lf->remove();

    $form_id = 'pg-bi-form-w' . $widget_id;
    $folder_id = $page_id > 0 ? (int)db_value("SELECT page_folder FROM page WHERE page_id = '" . (int)$page_id . "'") : 0;
    $scripts .= pg_sa_state_script($form_id, $states, $zip, 'billing_');
    $next_page_id = isset($settings['next_page_id']) ? (int)$settings['next_page_id'] : 0;
    $rendered = _pg_member_form_wrap($rendered . $scripts, _pg_acct_software_path() . '/billing_information.php', array(
        'page_id'          => $page_id,
        'next_page_id'     => $next_page_id > 0 ? $next_page_id : '',
        'opt_in_displayed' => $bi_flags['shows_opt_in'] ? 'true' : 'false',
        'folder_id'        => $folder_id,
    ), 'pg-billing-information-form');
    $rendered = preg_replace('/^<form /', '<form id="' . h($form_id) . '" ', $rendered, 1);

    $tax_label = (defined('ECOMMERCE_TAX_EXEMPT_LABEL') && ECOMMERCE_TAX_EXEMPT_LABEL != '')
        ? (string)ECOMMERCE_TAX_EXEMPT_LABEL : lang('Tax-Exempt?');
    return _pg_member_apply_tokens($rendered, array(
        '__custom_field_1_label' => h(isset($settings['custom_field_1_label']) ? (string)$settings['custom_field_1_label'] : ''),
        '__custom_field_2_label' => h(isset($settings['custom_field_2_label']) ? (string)$settings['custom_field_2_label'] : ''),
        '__tax_exempt_label'     => h($tax_label),
        '__opt_in_label'         => h(defined('OPT_IN_LABEL') ? (string)OPT_IN_LABEL : ''),
        '__custom_form_title'    => h(isset($settings['form_name']) ? (string)$settings['form_name'] : ''),
        '__cart_url'             => h($cart_url),
    ));
}
