<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the Shipping Address and Arrival system widget (kind
 * 'shipping_address_and_arrival'): the first step of the classic multi-step
 * checkout, drawn by the designer. One recipient of the order (?ship_to_id=,
 * as the cart sends the visitor here) gets its address, the requested
 * arrival date and, on a page with one, the custom shipping form's fields.
 *
 * The widget posts to shipping_address_and_arrival.php with the classic
 * page's field names, so the handler, its checks and its redirects stay
 * what they were: back here with the errors on the 'shipping_address_and_
 * arrival' liveform, or on to the page's next page. The page's settings are
 * its shipping_address_and_arrival_pages row: a page of the classic type
 * keeps its own, any other page gets one, written from the widget's
 * settings (as the express order widget does for express_order_pages).
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
 * The address controls the widget fills, by name: the classic page's field
 * names, which shipping_address_and_arrival.php reads.
 *
 * @return array
 */
function pg_sa_address_fields()
{
    return array('salutation', 'first_name', 'last_name', 'company', 'address_1', 'address_2',
        'city', 'state', 'zip_code', 'country', 'address_type', 'phone_number');
}

/**
 * Why the recipient cannot be edited here, as the classic page refuses it,
 * or '' when it can.
 *
 * @param array|null $recipient ship_tos.order_id with orders.status AS order_status, null when not found
 * @param mixed      $session_order_id the visitor's order ($_SESSION['ecommerce']['order_id'])
 * @return string '' | 'not_found' | 'complete' | 'no_order' | 'other_order'
 */
function pg_sa_recipient_problem($recipient, $session_order_id)
{
    if (!is_array($recipient)) return 'not_found';
    $status = isset($recipient['order_status']) ? (string)$recipient['order_status'] : '';
    if ($status === 'complete' || $status === 'exported') return 'complete';
    if (!$session_order_id) return 'no_order';
    if ((string)$recipient['order_id'] !== (string)$session_order_id) return 'other_order';
    return '';
}

/**
 * The classic page's words for a refused recipient.
 *
 * @param string $problem a pg_sa_recipient_problem() code
 * @return string
 */
function pg_sa_recipient_problem_message($problem)
{
    switch ($problem) {
        case 'not_found':
            return lang('Sorry, that recipient could not be found.  This might happen if you used the back button to go back to a recipient that no longer exists in the order.');
        case 'complete':
            return lang('Sorry, the order for that recipient has already been completed, so that recipient may not be updated.  This might happen if you already completed your order and then used the back button to go back to a recipient.  Since your order is complete, there is nothing further you need to do.');
        case 'no_order':
            return lang('Sorry, we could not find your order, so we can\'t allow you to update that recipient.  This might happen if you were inactive for a while and your session expired.  You might try starting a new order or retrieving a past order.');
        case 'other_order':
            return lang('Sorry, that recipient belongs to a different order, so we can\'t allow you to update that recipient.  This might happen if your session expired and you started a new order, and then you used the back button to go back to a recipient for an old order.');
    }
    return '';
}

/**
 * Whether the "my billing address is the same" box is offered, as the
 * classic page decides it: not once the order has billing details, and in
 * the multi-recipient mode not for a recipient other than "myself" when the
 * order has a "myself" recipient.
 *
 * @param bool   $multi_recipient ECOMMERCE_RECIPIENT_MODE is 'multi-recipient'
 * @param array  $recipient_names ship_to_name of every recipient of the order
 * @param string $ship_to_name    this recipient's
 * @param bool   $has_billing     the order already holds a billing detail
 * @return bool
 */
function pg_sa_offers_same_as_billing($multi_recipient, $recipient_names, $ship_to_name, $has_billing)
{
    if ($has_billing) return false;
    if ($multi_recipient && in_array('myself', (array)$recipient_names, true) && (string)$ship_to_name !== 'myself') {
        return false;
    }
    return true;
}

/**
 * The columns of the page's shipping_address_and_arrival_pages row the
 * widget's settings change: only the settings the widget has, so a page of
 * the classic type keeps what its own screen stored until the panel sets
 * one. A next page of 0 means "not chosen" and changes nothing.
 *
 * @param array|null $row the stored row, null when there is none
 * @param array      $cfg the widget's settings
 * @return array column => value, empty when nothing changes
 */
function pg_sa_page_row_changes($row, $cfg)
{
    $want = array();
    if (isset($cfg['next_page_id']) && (int)$cfg['next_page_id'] > 0) {
        $want['next_page_id'] = (int)$cfg['next_page_id'];
    }
    if (array_key_exists('address_type', $cfg)) {
        $want['address_type'] = empty($cfg['address_type']) ? 0 : 1;
    }
    if (array_key_exists('address_type_page_id', $cfg)) {
        $want['address_type_page_id'] = max(0, (int)$cfg['address_type_page_id']);
    }
    if (!is_array($row)) return $want;
    $changes = array();
    foreach ($want as $col => $value) {
        if (!isset($row[$col]) || (int)$row[$col] !== $value) $changes[$col] = $value;
    }
    return $changes;
}

/**
 * The value an <input type="date"> takes for a stored or typed date: ISO
 * (YYYY-MM-DD), '' for none. A date typed in the site's format before a
 * refused post is read as the handler reads it.
 *
 * @param string $value
 * @return string
 */
function pg_sa_date_input_value($value)
{
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00') return '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
    if (!preg_match('/^\d{1,4}[-\/,]\d{1,2}[-\/,]\d{1,4}$/', $value) || !defined('DATE_FORMAT')) return '';
    $iso = (string)prepare_form_data_for_input($value, 'date');
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $iso) ? $iso : '';
}

/**
 * The widget's visibility flags.
 *
 * @param bool $has_recipient    a recipient of the visitor's order is being edited
 * @param bool $multi_recipient  the shop ships to several recipients per order
 * @param bool $has_arrival_dates at least one arrival date can be requested
 * @param bool $has_custom_form  the page has a custom shipping form
 * @param bool $has_verified     the site has verified shipping addresses to pick from
 * @return array
 */
function pg_sa_flags($has_recipient, $multi_recipient, $has_arrival_dates, $has_custom_form, $has_verified = false)
{
    $sa_flags = array(
        'has_recipient'      => (bool)$has_recipient,
        'no_recipient'       => !$has_recipient,
        'is_multi_recipient' => (bool)$multi_recipient,
        'has_arrival_dates'  => $has_recipient && $has_arrival_dates,
        'has_custom_form'    => $has_recipient && $has_custom_form,
        'has_verified_addresses' => $has_recipient && $has_verified,
    );
    return $sa_flags;
}

/**
 * The script that switches the state control with the country, as the
 * classic page's software.init_country() does: the pick list named `state`
 * for a country with states, the text box for any other, and the zip code
 * required for a country that requires one. Both controls carry
 * data-pg-sa-state; the label moves to the one shown.
 *
 * @param string $form_id
 * @param array  $states   country code => list of [code, name]
 * @param array  $zip      country codes that require a zip code
 * @param string $prefix   the controls' name prefix ('billing_' on the billing step)
 * @return string
 */
function pg_sa_state_script($form_id, $states, $zip, $prefix = '')
{
    $data = encode_json(array('states' => (object)$states, 'zip' => array_values($zip), 'name' => $prefix . 'state'));
    $data = str_replace('</', '<\/', $data);
    return '<script>(function(){'
        . 'var f=document.getElementById(' . encode_json((string)$form_id) . ');if(!f)return;'
        . 'var d=' . $data . ';'
        . 'var c=f.querySelector("select[name=' . $prefix . 'country]"),t=f.querySelector("[data-pg-sa-state=text]"),'
        .   'l=f.querySelector("[data-pg-sa-state=list]"),z=f.querySelector("input[name=' . $prefix . 'zip_code]");'
        . 'if(!c)return;'
        . 'function lab(from,to){if(!from||!to||!from.id||!to.id)return;'
        .   'f.querySelectorAll("label[for=\""+from.id+"\"]").forEach(function(x){x.htmlFor=to.id;});}'
        . 'function sync(first){var s=d.states[c.value];'
        .   'if(l&&s){var keep=first?(l.value||(t?t.value:"")):"";l.innerHTML="";l.appendChild(new Option("",""));'
        .     's.forEach(function(r){l.appendChild(new Option(r[1],r[0]));});l.value=keep;'
        .     'l.name=d.name;l.hidden=false;l.required=c.required;'
        .     'if(t){t.name="";t.hidden=true;t.required=false;lab(t,l);}}'
        .   'else if(t){if(!first)t.value="";t.name=d.name;t.hidden=false;'
        .     'if(l){l.name="";l.hidden=true;l.required=false;lab(l,t);}}'
        .   'if(z)z.required=c.required&&d.zip.indexOf(c.value)!==-1;}'
        . 'c.addEventListener("change",function(){sync(false);});sync(true);'
        . 'f.addEventListener("change",function(e){var m=e.target&&e.target.name&&/^custom_arrival_date_(\d+)$/.exec(e.target.name);'
        .   'if(!m||!e.target.value)return;var r=f.querySelector("input[name=arrival_date][value=\""+m[1]+"\"]");if(r)r.checked=true;});'
        . '})();</script>';
}

/**
 * The controls a picked verified address fills, by name: the widget's own
 * address controls (pg_sa_address_fields()), as the classic page's "Use This
 * Address" button fills them. The country goes before the state, so the
 * state control the country uses is the one named `state` when it is set.
 *
 * @return array
 */
function pg_sa_verified_fill_fields()
{
    return array('company', 'address_1', 'address_2', 'city', 'zip_code', 'country', 'state');
}

/**
 * The verified shipping address help box, drawn into the widget's
 * `verified_addresses` section: a country, a state and an address pick list,
 * a message, the picked address and the "Use This Address" button. The pick
 * lists carry no name, so the form posts none of them; the script
 * (pg_sa_verified_script()) finds the parts by data-pg-sa-v.
 *
 * @param string $box_id    id of the box, unique on the page
 * @param array  $countries list of [code, label] (the first may be the empty choice)
 * @param string $default   country code chosen at first, the site's default
 * @return string
 */
function pg_sa_verified_box_html($box_id, $countries, $default)
{
    $id = h((string)$box_id);
    $options = '<option value=""></option>';
    foreach ((array)$countries as $c) {
        if ((string)$c[0] === '') continue;
        $selected = ((string)$c[0] === (string)$default) ? ' selected' : '';
        $options .= '<option value="' . h((string)$c[0]) . '"' . $selected . '>' . h((string)$c[1]) . '</option>';
    }
    return '<div id="' . $id . '" class="pg-sa-verified">'
        . '<div class="mb-3" data-pg-sa-v="country-wrap">'
        .   '<label class="form-label" for="' . $id . '-country">' . h(lang('Select a Country:')) . '</label>'
        .   '<select class="form-select" id="' . $id . '-country" data-pg-sa-v="country">' . $options . '</select></div>'
        . '<div class="mb-3" data-pg-sa-v="state-wrap" hidden>'
        .   '<label class="form-label" for="' . $id . '-state">' . h(lang('Select a State:')) . '</label>'
        .   '<select class="form-select" id="' . $id . '-state" data-pg-sa-v="state"></select></div>'
        . '<div class="mb-3" data-pg-sa-v="address-wrap" hidden>'
        .   '<label class="form-label" for="' . $id . '-address">' . h(lang('Select a Verified Address:')) . '</label>'
        .   '<select class="form-select" id="' . $id . '-address" data-pg-sa-v="address"></select></div>'
        . '<div class="mb-2" data-pg-sa-v="message" role="status" hidden></div>'
        . '<div class="mb-3 fw-bold" data-pg-sa-v="summary" hidden></div>'
        . '<div class="mb-3" data-pg-sa-v="button-wrap" hidden>'
        .   '<button type="button" class="btn btn-primary" data-pg-sa-v="use">' . h(lang('Use This Address')) . '</button></div>'
        . '</div>';
}

/**
 * The script of the help box, as the classic page's software_change_verified_*
 * and software_use_verified_address() work: the country lists its states,
 * the state its verified addresses, a picked address is shown, and the
 * button copies it into the widget's controls by name
 * (pg_sa_verified_fill_fields()), the country first with a change event so
 * the state script (pg_sa_state_script()) switches the state control.
 *
 * @param string $form_id   id of the widget's form
 * @param string $box_id    id of the help box
 * @param array  $states    list of [id, name, country code]
 * @param array  $addresses rows of _pg_sa_verified_addresses() with a `label`
 * @return string
 */
function pg_sa_verified_script($form_id, $box_id, $states, $addresses)
{
    $list = array();
    foreach ((array)$addresses as $a) {
        $row = array('id' => (string)$a['id'], 'label' => (string)$a['label'], 'state_id' => (string)$a['state_id']);
        foreach (array('company', 'address_1', 'address_2', 'city', 'state_code', 'zip_code', 'country_code', 'country_name') as $k) {
            $row[$k] = isset($a[$k]) ? (string)$a[$k] : '';
        }
        $list[] = $row;
    }
    $data = encode_json(array(
        'states'    => array_values((array)$states),
        'addresses' => $list,
        'fields'    => pg_sa_verified_fill_fields(),
        'words'     => array(
            'country' => lang('Sorry, we don\'t have any verified addresses for that Country.'),
            'state'   => lang('Sorry, we don\'t have any verified addresses for that State.'),
            'used'    => lang('The shipping address fields have been updated with') . ':',
        ),
    ));
    $data = str_replace('</', '<\/', $data);
    return '<script>(function(){'
        . 'var f=document.getElementById(' . encode_json((string)$form_id) . '),b=document.getElementById(' . encode_json((string)$box_id) . ');'
        . 'if(!f||!b)return;var d=' . $data . ';'
        . 'function q(n){return b.querySelector("[data-pg-sa-v="+n+"]");}'
        . 'var cs=q("country"),ss=q("state"),as=q("address"),msg=q("message"),sum=q("summary");'
        . 'if(!cs||!ss||!as)return;'
        . 'function show(n,on){var e=q(n);if(e)e.hidden=!on;}'
        . 'function say(t,bad){if(!msg)return;msg.textContent=t;msg.classList.toggle("text-danger",bad);msg.hidden=false;}'
        . 'function fill(s,rows){s.innerHTML="";s.appendChild(new Option("",""));rows.forEach(function(r){s.appendChild(new Option(r[1],r[0]));});return rows.length>0;}'
        . 'function picked(){var v=as.value,hit=null;d.addresses.forEach(function(a){if(a.id===v)hit=a;});return hit;}'
        . 'function country(){["state-wrap","address-wrap","message","summary","button-wrap"].forEach(function(n){show(n,false);});'
        .   'if(cs.value==="")return;'
        .   'if(fill(ss,d.states.filter(function(s){return s[2]===cs.value;})))show("state-wrap",true);else say(d.words.country,true);}'
        . 'function state(){["address-wrap","message","summary","button-wrap"].forEach(function(n){show(n,false);});'
        .   'if(ss.value==="")return;var rows=[];'
        .   'd.addresses.forEach(function(a){if(a.state_id===ss.value)rows.push([a.id,a.label]);});'
        .   'if(fill(as,rows))show("address-wrap",true);else say(d.words.state,true);}'
        . 'function address(){["message","summary","button-wrap"].forEach(function(n){show(n,false);});'
        .   'var a=picked();if(!a||!sum)return;sum.textContent="";'
        .   'var lines=[];if(a.company!=="")lines.push(a.company);lines.push(a.address_1);if(a.address_2!=="")lines.push(a.address_2);'
        .   'lines.push(a.city+", "+a.state_code+" "+a.zip_code);lines.push(a.country_name);'
        .   'lines.forEach(function(t,i){if(i)sum.appendChild(document.createElement("br"));sum.appendChild(document.createTextNode(t));});'
        .   'show("summary",true);show("button-wrap",true);}'
        . 'function take(){var a=picked();if(!a)return;'
        .   'd.fields.forEach(function(n){var e=f.querySelector("[name=\""+n+"\"]");if(!e)return;'
        .     'e.value=n==="country"?a.country_code:(n==="state"?a.state_code:a[n]);'
        .     'if(n==="country")e.dispatchEvent(new Event("change",{bubbles:true}));});'
        .   'show("button-wrap",false);as.value="";say(d.words.used,false);}'
        . 'cs.addEventListener("change",country);ss.addEventListener("change",state);as.addEventListener("change",address);'
        . 'var u=q("use");if(u)u.addEventListener("click",take);country();'
        . '})();</script>';
}

// The verified shipping addresses, read as the classic page reads them
// (get_shipping_address_and_arrival.php): the state's code and its country,
// ordered by company, then address line 1. Each row gets the pick list's
// label: the company, else address line 1, then the city.
function _pg_sa_verified_addresses()
{
    $rows = (array)db_items(
        "SELECT
            verified_shipping_addresses.id,
            verified_shipping_addresses.company,
            verified_shipping_addresses.address_1,
            verified_shipping_addresses.address_2,
            verified_shipping_addresses.city,
            verified_shipping_addresses.state_id,
            states.code as state_code,
            verified_shipping_addresses.zip_code,
            countries.code as country_code,
            countries.name as country_name
        FROM verified_shipping_addresses
        LEFT JOIN states ON verified_shipping_addresses.state_id = states.id
        LEFT JOIN countries ON states.country_id = countries.id
        ORDER BY
            verified_shipping_addresses.company,
            verified_shipping_addresses.address_1");
    foreach ($rows as &$r) {
        $r['label'] = ((string)$r['company'] !== '' ? (string)$r['company'] : (string)$r['address_1']) . ', ' . (string)$r['city'];
        $r['country_name'] = pg_geo_country_label((string)$r['country_name'], (string)$r['country_code']);
    }
    unset($r);
    return $rows;
}

// The page the widget is drawn on, as the handler needs it in page_id: the
// page being drawn, else the one the address names.
function _pg_sa_page_id()
{
    $ctx = pg_sw_render_context();
    if (!empty($ctx['page_id'])) return (int)$ctx['page_id'];
    if (isset($_GET['page']) && is_string($_GET['page']) && $_GET['page'] !== '') {
        $id = (int)db_value("SELECT page_id FROM page WHERE page_name = '" . e($_GET['page']) . "' LIMIT 1");
        if ($id > 0) return $id;
    }
    return function_exists('pg_rendered_page') ? (int)pg_rendered_page() : 0;
}

// The page's shipping_address_and_arrival_pages row, made when missing and
// brought in line with the widget's settings.
function _pg_sa_page_settings($page_id, $cfg)
{
    $page_id = (int)$page_id;
    if ($page_id <= 0) return array();
    $row = db_item("SELECT * FROM shipping_address_and_arrival_pages WHERE page_id = '" . $page_id . "'");
    if (!is_array($row)) {
        db("INSERT IGNORE INTO shipping_address_and_arrival_pages (page_id) VALUES ('" . $page_id . "')");
        $row = db_item("SELECT * FROM shipping_address_and_arrival_pages WHERE page_id = '" . $page_id . "'");
        if (!is_array($row)) return array();
        $changes = pg_sa_page_row_changes(null, $cfg);
    } else {
        $changes = pg_sa_page_row_changes($row, $cfg);
    }
    if ($changes) {
        $set = array();
        foreach ($changes as $col => $value) $set[] = $col . " = '" . (int)$value . "'";
        db("UPDATE shipping_address_and_arrival_pages SET " . implode(', ', $set) . " WHERE page_id = '" . $page_id . "'");
        $row = array_merge($row, $changes);
    }
    return $row;
}

// The arrival dates a visitor may request now, in the classic page's order.
function _pg_sa_arrival_dates()
{
    return (array)db_items(
        "SELECT id, name, description, default_selected, custom
        FROM arrival_dates
        WHERE
            (status = 'enabled')
            AND (start_date <= CURRENT_DATE())
            AND (concat(end_date, ' " . ECOMMERCE_END_OF_DAY_TIME . "') > NOW())
        ORDER BY sort_order, arrival_date");
}

// The address the form starts with: what the visitor typed before a refused
// post, else the address book entry of the same name (a signed-in member,
// not a ghost), else the recipient's stored address; the default country
// when none. Also the arrival date chosen before, else the default one.
function _pg_sa_start_values($lf, $recipient, $ship_to_id, $arrival_dates)
{
    $values = array();
    if ($lf->field_in_session('first_name')) {
        foreach (pg_sa_address_fields() as $f) $values[$f] = (string)$lf->get_field_value($f);
        $values['arrival_date'] = (string)$lf->get_field_value('arrival_date');
        $values['custom_arrival_date'] = (string)$lf->get_field_value('custom_arrival_date_' . $values['arrival_date']);
        $values['shipping_same_as_billing'] = (string)$lf->get_field_value('shipping_same_as_billing');
        return $values;
    }
    $row = null;
    $ghost = !empty($_SESSION['software']['ghost']);
    if (defined('USER_LOGGED_IN') && USER_LOGGED_IN && !$ghost && $recipient) {
        $row = db_item("SELECT * FROM address_book WHERE user = '" . e(USER_ID) . "' AND ship_to_name = '" . e($recipient['ship_to_name']) . "' LIMIT 1");
    }
    if (!is_array($row)) $row = is_array($recipient) ? $recipient : array();
    foreach (pg_sa_address_fields() as $f) $values[$f] = isset($row[$f]) ? (string)$row[$f] : '';
    if ($values['country'] === '') $values['country'] = _pg_acct_default_country();
    $values['arrival_date'] = '';
    $values['custom_arrival_date'] = '';
    $values['shipping_same_as_billing'] = '';
    if ($ship_to_id > 0) {
        $chosen = db_item(
            "SELECT arrival_dates.id, arrival_dates.custom, ship_tos.arrival_date
            FROM ship_tos
            LEFT JOIN arrival_dates ON arrival_dates.id = ship_tos.arrival_date_id
            WHERE ship_tos.id = '" . (int)$ship_to_id . "'");
        if (is_array($chosen) && $chosen['id']) {
            $values['arrival_date'] = (string)$chosen['id'];
            if ($chosen['custom']) $values['custom_arrival_date'] = (string)$chosen['arrival_date'];
        }
    }
    if ($values['arrival_date'] === '') {
        foreach ($arrival_dates as $d) {
            if ($d['default_selected']) { $values['arrival_date'] = (string)$d['id']; break; }
        }
    }
    return $values;
}

// Put the stored answers of a custom shipping or billing form into the
// liveform, as the classic page does when it opens: one value, or the list
// of a field that holds several. $where picks this recipient's or this
// order's form_data rows.
function _pg_sa_prefill_custom_form($lf, $where)
{
    $by_field = array();
    foreach ((array)db_items(
        "SELECT form_data.form_field_id, form_data.data, form_fields.type
        FROM form_data
        LEFT JOIN form_fields ON form_data.form_field_id = form_fields.id
        WHERE " . $where . "
        ORDER BY form_data.id") as $r) {
        $by_field[(int)$r['form_field_id']]['type'] = (string)$r['type'];
        $by_field[(int)$r['form_field_id']]['data'][] = $r['data'];
    }
    foreach ($by_field as $field_id => $f) {
        $data = count($f['data']) > 1 ? $f['data'] : $f['data'][0];
        $lf->assign_field_value('field_' . $field_id, prepare_form_data_for_output($data, $f['type'], false));
    }
}

// Give an arrival date row's own controls their names: the custom date box
// (`custom_arrival_date`) becomes custom_arrival_date_<id>, the radio takes
// the date's id as its value.
function _pg_sa_name_arrival_row(&$tree, $date_id)
{
    if (!is_array($tree)) return;
    $own = _pg_member_control_name($tree);
    if ($own === 'custom_arrival_date') {
        _pg_member_set_attr($tree, 'name', 'custom_arrival_date_' . (int)$date_id);
    } elseif ($own === 'arrival_date') {
        _pg_member_set_attr($tree, 'value', (string)(int)$date_id);
    }
    if (!empty($tree['children']) && is_array($tree['children'])) {
        foreach ($tree['children'] as &$c) _pg_sa_name_arrival_row($c, $date_id);
        unset($c);
    }
}

// Mark the two state controls (the text box and the pick list, both named
// $name: `state`, `billing_state` on the billing step) for the script, fill the pick list with the country's states and
// leave unnamed and hidden the one the country does not use, so the form
// posts the right one before the script runs.
function _pg_sa_state_controls(&$tree, $value, $country_states, $name = 'state')
{
    $use_list = !empty($country_states);
    _pg_member_edit_control($tree, $name, function (&$node) use ($value, $country_states, $use_list) {
        $is_list = strtolower((string)$node['props']['tag']) === 'select';
        _pg_member_set_attr($node, 'data-pg-sa-state', $is_list ? 'list' : 'text');
        if ($is_list) {
            $kids = array(array('type' => 'semantic', 'props' => array('tag' => 'option', 'text' => '',
                '_attrs' => array(array('name' => 'value', 'value' => ''))), 'children' => array()));
            foreach ((array)$country_states as $s) {
                $attrs = array(array('name' => 'value', 'value' => (string)$s[0]));
                if ((string)$s[0] === (string)$value) $attrs[] = array('name' => 'selected', 'value' => '');
                $kids[] = array('type' => 'semantic', 'props' => array('tag' => 'option', 'text' => (string)$s[1], '_attrs' => $attrs), 'children' => array());
            }
            $node['children'] = $kids;
        } elseif ((string)$value !== '') {
            _pg_member_set_attr($node, 'value', (string)$value);
        }
        if ($is_list !== $use_list) {
            _pg_member_set_attr($node, 'name', '');
            _pg_member_set_attr($node, 'hidden', '');
            _pg_member_set_attr($node, 'required', null);
        } else {
            _pg_member_set_attr($node, 'hidden', null);
        }
    });
}

// ============================================================================
// shipping_address_and_arrival
// ============================================================================

// Render a 'shipping_address_and_arrival' system widget.
//
// Controls by name (the classic page's): salutation and country are <select>s
// the server fills; state is a text box and a pick list (both named state:
// the country decides which posts); address_type radios (residential /
// business), dropped unless the page asks for the address type, then
// required; shipping_same_as_billing, dropped when it is not offered.
// loop_area: one row per arrival date that can be requested: a radio named
// arrival_date (its value set per row) and, for a date with a custom day, a
// date box named custom_arrival_date (renamed per row; dropped otherwise).
// Row tokens: ^^__arrival_date_name^^, ^^__arrival_date_description^^ (the
// operator's HTML). Row flag: arrival_is_custom.
// Tokens: ^^__recipient_name^^ (ship-to name), ^^__address_type_url^^ (the
// address type help page; a link bound to it drops when there is none),
// ^^__custom_form_title^^, ^^__cart_url^^.
// Sections: custom_shipping_form (the page's custom shipping form fields),
// verified_addresses (the verified shipping address help box, with its
// script; shown by has_verified_addresses).
// Flags: pg_sa_flags(). Errors: liveform 'shipping_address_and_arrival'.
// cfg: next_page_id, address_type (bool), address_type_page_id.
function _render_system_widget_shipping_address_and_arrival($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
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
    $settings = _pg_sa_page_settings($page_id, $cfg);
    if ($page_id > 0 && $mode !== 'background') {
        // The billing step comes back here when the visitor goes back.
        $_SESSION['ecommerce']['shipping_address_and_arrival_page_id'] = $page_id;
    }

    // The recipient, refused as the classic page refuses it. A refusal in
    // the editor or a background render draws the widget's own state.
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
    // The editor's Preview draws the empty form, so its layout can be seen.
    $has_recipient = is_array($recipient) || $mode === 'edit';
    $multi = defined('ECOMMERCE_RECIPIENT_MODE') && ECOMMERCE_RECIPIENT_MODE === 'multi-recipient';

    $arrival_dates = $has_recipient ? _pg_sa_arrival_dates() : array();
    $verified = $has_recipient ? _pg_sa_verified_addresses() : array();
    $custom_form = $has_recipient && !empty($settings['form']);
    $type_on = !empty($settings['address_type']);

    $lf = new liveform('shipping_address_and_arrival');
    if ($custom_form && $ship_to_id > 0 && !$lf->field_in_session('first_name')) {
        _pg_sa_prefill_custom_form($lf, "form_data.ship_to_id = '" . $ship_to_id . "'");
    }
    $values = $has_recipient ? _pg_sa_start_values($lf, $recipient, $ship_to_id, $arrival_dates) : array();

    // The billing box, offered as the classic page offers it.
    $same_as_billing = false;
    if (is_array($recipient)) {
        $order_id = (int)$recipient['order_id'];
        $names = array();
        foreach ((array)db_items("SELECT ship_to_name FROM ship_tos WHERE order_id = '" . $order_id . "'") as $r) $names[] = (string)$r['ship_to_name'];
        $has_billing = (bool)db_value(
            "SELECT id FROM orders WHERE id = '" . $order_id . "' AND (billing_salutation != '' OR billing_first_name != ''
                OR billing_last_name != '' OR billing_company != '' OR billing_address_1 != '' OR billing_address_2 != ''
                OR billing_city != '' OR billing_state != '' OR billing_zip_code != '' OR billing_country != ''
                OR billing_phone_number != '')");
        $same_as_billing = pg_sa_offers_same_as_billing($multi, $names, (string)$recipient['ship_to_name'], $has_billing);
    } elseif ($mode === 'edit') {
        $same_as_billing = true;
    }

    // Links first, so a wrapper whose controls and link both go is left
    // empty and goes with them.
    $type_url = '';
    if ($type_on && !empty($settings['address_type_page_id'])) $type_url = _pg_member_page_url($settings['address_type_page_id']);
    $cart_url = function_exists('pg_sw_cart_url') ? pg_sw_cart_url() : '';
    _pg_member_drop_empty_links($tree, array('__address_type_url' => $type_url, '__cart_url' => $cart_url));

    // An arrival date row's own flag is decided per row, below.
    $sa_flags = pg_sa_flags($has_recipient, $multi, count($arrival_dates) > 0, $custom_form, count($verified) > 0);
    _eo_apply_visibility_bindings($tree, $sa_flags + array('arrival_is_custom' => true));

    $states = array();
    $verified_states = array();
    $zip = array();
    $countries = array();
    if ($has_recipient) {
        foreach ((array)db_items("SELECT states.id, states.code, states.name, countries.code AS country_code FROM states
            LEFT JOIN countries ON countries.id = states.country_id ORDER BY states.name") as $s) {
            $states[(string)$s['country_code']][] = array((string)$s['code'], (string)$s['name']);
            $verified_states[] = array((string)$s['id'], (string)$s['name'], (string)$s['country_code']);
        }
        $countries = _pg_acct_country_options();
        foreach ((array)db_items("SELECT code FROM countries WHERE zip_code_required = 1") as $c) $zip[] = (string)$c['code'];

        $fill = $values;
        foreach (array('state', 'address_type', 'arrival_date', 'custom_arrival_date', 'shipping_same_as_billing') as $k) unset($fill[$k]);
        _pg_acct_fill_values($tree, $fill, array(
            'salutation' => _pg_acct_pairs(get_salutation_options()),
            'country'    => $countries,
        ));
        _pg_sa_state_controls($tree, $values['state'], isset($states[$values['country']]) ? $states[$values['country']] : array());
        if ($type_on) {
            _pg_acct_check_value($tree, 'address_type', $values['address_type']);
            _pg_member_edit_control($tree, 'address_type', function (&$node) { _pg_member_set_attr($node, 'required', ''); });
        } else {
            _pg_member_drop_control($tree, 'address_type');
        }
        if ($same_as_billing) _pg_member_fill_control($tree, 'shipping_same_as_billing', '1', $values['shipping_same_as_billing'] === '1');
        else                  _pg_member_drop_control($tree, 'shipping_same_as_billing');
    }

    // The custom shipping form: the classic page's rows, its own markup.
    $scripts = '';
    $sections = array('custom_shipping_form' => '', 'verified_addresses' => '');
    $verified_id = 'pg-sa-verified-w' . $widget_id;
    if ($verified) $sections['verified_addresses'] = pg_sa_verified_box_html($verified_id, $countries, _pg_acct_default_country());
    if ($custom_form) {
        $info = get_form_info($page_id, 0, 0, 0, (string)$settings['form_label_column_width'], false, $lf, 'frontend', false, 'desktop', 0);
        if (trim((string)$info['content']) !== '') {
            $sections['custom_shipping_form'] = '<table class="custom_shipping_form">' . $info['content'] . '</table>';
        }
        if (!empty($info['wysiwyg_fields'])) $scripts .= get_wysiwyg_editor_code($info['wysiwyg_fields']);
    }
    pg_cf_apply_section_bindings($tree, $sections);
    pg_cf_link_labels($tree);
    _pg_inject_messages_node($tree, 'shipping_address_and_arrival');

    $split = _split_widget_tree($tree);
    $rows = '';
    if ($split['loop_children'] !== null && $arrival_dates) {
        $row_tree = array('type' => 'root', 'props' => array(), 'children' => $split['loop_children']);
        foreach ($arrival_dates as $d) {
            $did = (int)$d['id'];
            $t = $row_tree;
            _eo_apply_visibility_bindings($t, array('arrival_is_custom' => (bool)$d['custom']));
            _pg_sa_name_arrival_row($t, $did);
            _pg_acct_check_value($t, 'arrival_date', $values['arrival_date']);
            if ($d['custom']) {
                $day = ($values['arrival_date'] === (string)$did) ? pg_sa_date_input_value($values['custom_arrival_date']) : '';
                _pg_member_fill_control($t, 'custom_arrival_date_' . $did, $day);
            } else {
                _pg_member_drop_control($t, 'custom_arrival_date_' . $did);
            }
            $html = '';
            foreach ($t['children'] as $child) $html .= _render_tree_node($child, 0, 0);
            $html = _pg_sw_fill_tokens($html, array(
                '__arrival_date_name'        => h((string)$d['name']),
                '__arrival_date_description' => (string)$d['description'],
            ));
            $rows .= pg_sw_uniquify_row_ids(pg_sw_sweep_tokens($html), $widget_id, $did);
        }
    }
    $rendered = str_replace('<!--pg-loop-slot-->', $rows, trim(_render_tree_node($split['static_tree'], 0, 0)));
    $lf->remove();

    $tokens = array(
        '__recipient_name'    => h(is_array($recipient) ? (string)$recipient['ship_to_name'] : ''),
        '__address_type_url'  => h($type_url),
        '__custom_form_title' => h(isset($settings['form_name']) ? (string)$settings['form_name'] : ''),
        '__cart_url'          => h($cart_url),
    );
    if (!$has_recipient) return _pg_member_apply_tokens($rendered, $tokens);

    $form_id = 'pg-sa-form-w' . $widget_id;
    $folder_id = $page_id > 0 ? (int)db_value("SELECT page_folder FROM page WHERE page_id = '" . (int)$page_id . "'") : 0;
    $scripts .= pg_sa_state_script($form_id, $states, $zip);
    if ($verified) $scripts .= pg_sa_verified_script($form_id, $verified_id, $verified_states, $verified);
    $rendered = _pg_member_form_wrap($rendered . $scripts, _pg_acct_software_path() . '/shipping_address_and_arrival.php', array(
        'page_id'    => $page_id,
        'ship_to_id' => $ship_to_id,
        'folder_id'  => $folder_id,
    ), 'pg-shipping-address-form');
    $rendered = preg_replace('/^<form /', '<form id="' . h($form_id) . '" ', $rendered, 1);

    return _pg_member_apply_tokens($rendered, $tokens);
}
