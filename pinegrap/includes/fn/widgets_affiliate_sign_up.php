<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the Affiliate Sign Up Form system widget (kind
 * 'affiliate_sign_up_form'): the classic "affiliate sign up form" page
 * drawn by the designer. A signed-in visitor applies to the affiliate
 * programme with their business address and the affiliate code they want.
 *
 * The widget posts to affiliate_sign_up_form.php with the classic page's
 * field names (first_name, last_name, address_1, address_2, city, state,
 * zip_code, country, phone_number, fax_number, email_address,
 * affiliate_name, affiliate_website, affiliate_code, terms_and_conditions,
 * submit, hidden current_page_id and next_page_id), so the handler's
 * checks, the contact it updates, its e-mails and its redirects stay what
 * they were. The handler asks for the terms check box when the page's
 * affiliate_sign_up_form_pages row names a terms page: a page of the
 * classic type keeps its own row, any other page gets one written from the
 * widget's settings when it is drawn.
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
 * The columns of the page's affiliate_sign_up_form_pages row the widget's
 * settings change. Only a key the widget has is written; a terms or next
 * page of 0 means "the page's own setting" and leaves the stored one.
 *
 * @param array|null $row the stored row, null when there is none
 * @param array      $cfg the widget's settings
 * @return array column => value, empty when nothing changes
 */
function pg_aff_page_row_changes($row, $cfg)
{
    $cfg = is_array($cfg) ? $cfg : array();
    $want = array();
    foreach (array('terms_page_id', 'next_page_id') as $k) {
        if (isset($cfg[$k]) && (int)$cfg[$k] > 0) $want[$k] = (int)$cfg[$k];
    }
    if (array_key_exists('submit_button_label', $cfg)) $want['submit_button_label'] = mb_substr(trim((string)$cfg['submit_button_label']), 0, 50);
    if (!is_array($row)) return $want;
    $changes = array();
    foreach ($want as $col => $value) {
        if (!isset($row[$col]) || (string)$row[$col] !== (string)$value) $changes[$col] = $value;
    }
    return $changes;
}

/**
 * The form's starting values for a visitor who has not posted it: the
 * contact's business address, company and website, as the classic page
 * prefills them; the website box opens with "http://" when there is none.
 *
 * @param array|null $contact contacts columns, null without a contact
 * @return array control name => value
 */
function pg_aff_prefill($contact)
{
    $c = is_array($contact) ? $contact : array();
    $v = function ($k) use ($c) {
        return isset($c[$k]) ? (string)$c[$k] : '';
    };
    return array(
        'first_name'        => $v('first_name'),
        'last_name'         => $v('last_name'),
        'address_1'         => $v('business_address_1'),
        'address_2'         => $v('business_address_2'),
        'city'              => $v('business_city'),
        'state'             => $v('business_state'),
        'zip_code'          => $v('business_zip_code'),
        'country'           => $v('business_country'),
        'phone_number'      => $v('business_phone'),
        'fax_number'        => $v('business_fax'),
        'email_address'     => $v('email_address'),
        'affiliate_name'    => $v('company'),
        'affiliate_website' => $v('website') !== '' ? $v('website') : 'http://',
        'affiliate_code'    => '',
    );
}

/**
 * The widget's visibility flags.
 *
 * @param bool $terms the page names a terms page
 * @return array
 */
function pg_aff_flags($terms)
{
    $aff_flags = array(
        'has_terms' => (bool)$terms,
        'no_terms'  => !$terms,
    );
    return $aff_flags;
}

// The page's affiliate_sign_up_form_pages row, made when missing and
// brought in line with the widget's settings; returned as it now stands.
function _pg_aff_page_settings($page_id, $cfg, $write)
{
    $page_id = (int)$page_id;
    if ($page_id <= 0) return array();
    $row = db_item("SELECT * FROM affiliate_sign_up_form_pages WHERE page_id = '" . $page_id . "'");
    if (!$write) return is_array($row) ? $row : array();
    if (!is_array($row)) {
        db("INSERT IGNORE INTO affiliate_sign_up_form_pages (page_id) VALUES ('" . $page_id . "')");
        $row = db_item("SELECT * FROM affiliate_sign_up_form_pages WHERE page_id = '" . $page_id . "'");
        if (!is_array($row)) return array();
    }
    foreach (array('terms_page_id', 'next_page_id') as $k) {
        // A page that is gone is not stored.
        if (!empty($cfg[$k]) && !(int)db_value("SELECT page_id FROM page WHERE page_id = '" . (int)$cfg[$k] . "' LIMIT 1")) unset($cfg[$k]);
    }
    $changes = pg_aff_page_row_changes($row, $cfg);
    if ($changes) {
        $set = array();
        foreach ($changes as $col => $value) {
            $set[] = $col . " = '" . e((string)$value) . "'";
            $row[$col] = $value;
        }
        db("UPDATE affiliate_sign_up_form_pages SET " . implode(', ', $set) . " WHERE page_id = '" . $page_id . "'");
    }
    return $row;
}

// ============================================================================
// affiliate_sign_up_form
// ============================================================================

// Render an 'affiliate_sign_up_form' system widget.
//
// Controls by name (the classic page's): first_name, last_name, address_1,
// address_2, city, country (a <select> the server fills), state (a text
// box and a pick list, the page's script shows the one the country uses),
// zip_code, phone_number, fax_number, email_address, affiliate_name,
// affiliate_website, affiliate_code, terms_and_conditions (dropped without
// a terms page) and the submit button.
// Tokens: ^^__submit_button_label^^, ^^__terms_url^^.
// Flags: pg_aff_flags(). Errors: liveform 'affiliate_sign_up_form'.
// cfg: terms_page_id, submit_button_label, next_page_id.
function _render_system_widget_affiliate_sign_up_form($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();
    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';
    // The visitor's own address and a refused post's values.
    if (function_exists('pg_page_cache_personal')) pg_page_cache_personal('account');

    $page_id = _pg_sa_page_id();
    $row = _pg_aff_page_settings($page_id, $cfg, $mode !== 'background');
    $settings = array_merge($row, pg_aff_page_row_changes(null, $cfg));
    $terms_name = !empty($settings['terms_page_id']) ? (string)get_page_name((int)$settings['terms_page_id']) : '';
    $terms_url = ($terms_name !== '') ? (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . encode_url_path($terms_name) : '';
    $label = trim((string)($settings['submit_button_label'] ?? ''));
    if ($label === '') $label = lang('Sign Up');

    $lf = new liveform('affiliate_sign_up_form');
    $names = array_keys(pg_aff_prefill(null));
    if (!$lf->field_in_session('submit')) {
        $contact = null;
        if ($mode !== 'background' && isset($_SESSION['sessionusername']) && (string)$_SESSION['sessionusername'] !== '') {
            $contact = db_item("SELECT contacts.* FROM user LEFT JOIN contacts ON contacts.id = user.user_contact
                WHERE user.user_username = '" . e((string)$_SESSION['sessionusername']) . "'");
        }
        $values = pg_aff_prefill($contact);
    } else {
        $values = array();
        foreach ($names as $n) $values[$n] = (string)$lf->get_field_value($n);
    }
    if ($values['country'] === '') $values['country'] = _pg_acct_default_country();

    $aff_flags = pg_aff_flags($terms_url !== '');
    _eo_apply_visibility_bindings($tree, $aff_flags);
    _pg_member_drop_empty_links($tree, array('__terms_url' => $terms_url));
    if ($terms_url === '') {
        _pg_member_drop_control($tree, 'terms_and_conditions');
    } else {
        _pg_member_fill_control($tree, 'terms_and_conditions', '1', (string)$lf->get_field_value('terms_and_conditions') !== '');
        _pg_member_edit_control($tree, 'terms_and_conditions', function (&$node) { _pg_member_set_attr($node, 'required', ''); });
    }

    $states = array();
    foreach ((array)db_items("SELECT states.code, states.name, countries.code AS country_code FROM states
        LEFT JOIN countries ON countries.id = states.country_id ORDER BY states.name") as $s) {
        $states[(string)$s['country_code']][] = array((string)$s['code'], (string)$s['name']);
    }
    $zip = array();
    foreach ((array)db_items("SELECT code FROM countries WHERE zip_code_required = 1") as $c) $zip[] = (string)$c['code'];
    $fill = $values;
    unset($fill['state']);
    _pg_acct_fill_values($tree, $fill, array('country' => _pg_acct_country_options()));
    _pg_sa_state_controls($tree, $values['state'], isset($states[$values['country']]) ? $states[$values['country']] : array());
    _pg_member_edit_control($tree, 'submit', function (&$node) use ($label) {
        _pg_member_set_attr($node, 'value', $label);
    });
    pg_cf_link_labels($tree);
    _pg_inject_messages_node($tree, 'affiliate_sign_up_form');

    $split = _split_widget_tree($tree);
    $rendered = str_replace('<!--pg-loop-slot-->', '', trim(_render_tree_node($split['static_tree'], 0, 0)));
    $lf->remove();
    $form_id = 'pg-aff-form-w' . $widget_id;
    $rendered = _pg_member_form_wrap($rendered . pg_sa_state_script($form_id, $states, $zip), _pg_acct_software_path() . '/affiliate_sign_up_form.php', array(
        'current_page_id' => $page_id,
        'next_page_id'    => (int)($settings['next_page_id'] ?? 0),
    ), 'pg-affiliate-sign-up-form');
    $rendered = preg_replace('/^<form /', '<form id="' . h($form_id) . '" ', $rendered, 1);
    return _pg_member_apply_tokens($rendered, array(
        '__submit_button_label' => h($label),
        '__terms_url'           => h($terms_url),
    ));
}
