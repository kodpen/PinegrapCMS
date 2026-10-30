<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: the account system widgets - logout, change
// password, set password, profile, email preferences, address book and
// account security - the designed pages that stand in for the legacy page
// types, the member's invoices from the ERP, the login region a site
// header carries, and the designed welcome and password reset e-mails.
//
// Loaded by functions.php through require_once, never on its own.
//
// Each widget posts to the processor its legacy page type posts to, with two
// extra hidden fields the processors read:
//   return_to    this page. Errors, and the confirmation when nothing else
//                is named, come back here instead of the legacy screen.
//   pg_controls  the names of the controls the widget drew. A processor
//                writes (and requires) only those, so a field the designer
//                left out keeps its stored value instead of being blanked.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// ============================================================================
// Designed pages for the legacy page types
// ============================================================================

// The system widget that stands in for each legacy page type.
function pg_sw_page_type_widgets()
{
    return array(
        'my account'            => 'my_account',
        'my account profile'    => 'account_profile',
        'email preferences'     => 'email_preferences',
        'update address book'   => 'address_book',
        'change password'       => 'change_password',
        'set password'          => 'set_password',
        'logout'                => 'logout',
        'login'                 => 'login_form',
        'forgot password'       => 'forgot_password',
        'registration entrance' => 'registration',
        'membership entrance'   => 'membership',
        'search results'        => 'search_results',
        'shopping cart'         => 'shopping_cart',
        'express order'         => 'express_order',
        'shipping address and arrival' => 'shipping_address_and_arrival',
        'shipping method'       => 'shipping_method',
        'billing information'   => 'billing_information',
        'order preview'         => 'order_preview',
        'order form'            => 'order_form',
        'folder view'           => 'folder_view',
        'photo gallery'         => 'photo_gallery',
        'affiliate sign up form' => 'affiliate_sign_up_form',
        'view order'            => 'order_view',
        'calendar view'         => 'calendar_view',
        'calendar event view'   => 'calendar_event_view',
    );
}

// Address of the first designed page carrying the widget that stands in for
// $page_type, or '' when there is none. get_page_type_url() asks this when
// the site has no page of the legacy type, so a password reset link, a
// "back to my account" redirect or a logout lands on the designed page.
function pg_sw_page_type_widget_url($page_type)
{
    static $cache = array();
    $page_type = (string)$page_type;
    if (array_key_exists($page_type, $cache)) return $cache[$page_type];
    $cache[$page_type] = '';

    $map = pg_sw_page_type_widgets();
    if (!isset($map[$page_type])) return '';
    // page_tree_json arrived with the multi-page editor; before that upgrade
    // no designed page can carry a widget.
    if (function_exists('pg_multi_page_design_ready') && !pg_multi_page_design_ready()) return '';

    $pages = pg_sw_widget_pages($map[$page_type]);
    // The order receipt e-mail page carries an order view too, sits in a
    // private folder and sorts before the public order page by title.
    if ($page_type === 'view order') $pages = pg_sw_pages_without($pages, _pg_sw_order_receipt_email_page_ids());
    if (!$pages) return '';
    return $cache[$page_type] = (defined('PATH') ? PATH : '/') . encode_url_path((string)$pages[0]['page_name']);
}

// $pages (rows with page_id) without those whose id is in $skip_ids, in the
// same order, re-indexed.
function pg_sw_pages_without(array $pages, array $skip_ids)
{
    $skip = array();
    foreach ($skip_ids as $id) $skip[(int)$id] = true;
    $left = array();
    foreach ($pages as $page) {
        if (!isset($skip[(int)$page['page_id']])) $left[] = $page;
    }
    return $left;
}

// Ids of the pages set as an order receipt e-mail: the express order and
// order preview page rows, and the widgets' own settings (a template site
// mirrors the widget value into express_order_pages only once it renders).
function _pg_sw_order_receipt_email_page_ids()
{
    $ids = array();
    foreach (array('express_order_pages', 'order_preview_pages') as $table) {
        $rows = db_items("SELECT order_receipt_email_page_id FROM $table WHERE order_receipt_email_page_id > 0");
        foreach ((is_array($rows) ? $rows : array()) as $row) $ids[] = (int)$row['order_receipt_email_page_id'];
    }
    $rows = db_items(
        "SELECT system_region_config FROM shared_components
         WHERE system_region_config LIKE '%order_receipt_email_page_id%'");
    foreach ((array)$rows as $row) {
        $cfg = json_decode((string)$row['system_region_config'], true);
        if (!is_array($cfg) || !isset($cfg['regionType'], $cfg['order_receipt_email_page_id'])) continue;
        if (!in_array($cfg['regionType'], array('express_order', 'order_preview'), true)) continue;
        if ((int)$cfg['order_receipt_email_page_id'] > 0) $ids[] = (int)$cfg['order_receipt_email_page_id'];
    }
    return $ids;
}

// Where a visitor who has to sign in before a page opens is sent: the
// site's designed sign-in page (one carrying the login widget), with the
// page as send_to. A site that keeps a legacy page of $legacy_type ('login',
// 'registration entrance') is served by the legacy screen, which shows that
// page - unless the page asked for is itself a visual design page
// ($designed_first): its visitor is on the designed site, and the sample
// content every installation starts with carries the legacy pages. A site
// with no designed sign-in page gets the legacy screen too. $fallback is the
// legacy screen's own address. $page_id is the page asked for: a site with
// two designs has a sign-in page in each, and the one of that page's design
// is the one that looks like it and links to its own sign-up and account
// pages.
function pg_sw_sign_in_url($send_to, $fallback, $legacy_type = 'login', $designed_first = false, $page_id = 0)
{
    if (function_exists('pg_multi_page_design_ready') && !pg_multi_page_design_ready()) return $fallback;
    if (!$designed_first && (int)db_value("SELECT COUNT(*) FROM page WHERE page_type = '" . e($legacy_type) . "'") > 0) return $fallback;
    $style_id = ((int)$page_id > 0) ? (int)db_value("SELECT page_style FROM page WHERE page_id = '" . (int)$page_id . "' LIMIT 1") : 0;
    $name = _pg_sw_open_widget_page_name('login_form', $style_id);
    if ($name === '') return $fallback;
    $url = (defined('PATH') ? PATH : '/') . encode_url_path($name);
    return ((string)$send_to === '') ? $url : $url . '?send_to=' . urlencode((string)$send_to);
}

// Name of the first designed page carrying a widget of $type that the
// visitor may open, or ''. A page in a folder that itself asks for a sign-in
// does not count: sending a signed-out visitor there to sign in would send
// them round in a circle. With $style_id, a page of that design comes first.
function _pg_sw_open_widget_page_name($type, $style_id = 0)
{
    $first = '';
    foreach (pg_sw_widget_pages($type) as $p) {
        $row = db_item("SELECT page_folder, page_style FROM page WHERE page_id = '" . (int)$p['page_id'] . "' LIMIT 1");
        if (!is_array($row) || (string)$row['page_folder'] === '' || !check_view_access($row['page_folder'])) continue;
        if ((int)$style_id <= 0 || (int)$row['page_style'] === (int)$style_id) return (string)$p['page_name'];
        if ($first === '') $first = (string)$p['page_name'];
    }
    return $first;
}

// Where a screen of the software directory sends its visitor on a site that
// has no page of the legacy type the screen draws, or ''. index.php (the
// 'login' type: the control panel and every other address that asks for a
// sign-in land there) and forgot_password.php (the 'forgot password' type)
// otherwise fall back to a bare built-in form. A site built in the visual
// page editor has no page types at all: the widget on a page is what makes
// it the sign-in page, so the designed page carrying the widget that stands
// in for the type answers, with the send_to the screen was opened with.
// Kept to a GET: a posted form is the screen's own processor.
function pg_sw_screen_page_url($page_type)
{
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] !== 'GET') return '';
    $map = pg_sw_page_type_widgets();
    if (!isset($map[$page_type])) return '';
    // page_tree_json arrived with the multi-page editor; before that upgrade
    // no designed page can carry a widget.
    if (function_exists('pg_multi_page_design_ready') && !pg_multi_page_design_ready()) return '';
    $name = _pg_sw_open_widget_page_name($map[$page_type]);
    if ($name === '') return '';
    $url = (defined('PATH') ? PATH : '/') . encode_url_path($name);
    // Only a same-site path is passed on, as the widget itself would read it.
    $send_to = (isset($_GET['send_to']) && is_scalar($_GET['send_to'])) ? (string)$_GET['send_to'] : '';
    if ($send_to !== '' && pg_safe_redirect_path($send_to, '/__none__') !== '/__none__') {
        $url .= '?send_to=' . urlencode($send_to);
    }
    return $url;
}

// Whether a page belongs to a visual design (its own tree, or a design made
// in the visual editor) - what pg_sw_sign_in_url() is told as $designed_first.
function pg_sw_page_is_visual($page_id)
{
    if (!function_exists('pg_page_edit_row')) return false;
    $row = pg_page_edit_row($page_id);
    return is_array($row) && pg_page_is_visual_design($row);
}

// The address one of the account widgets links to: the page its config
// names, else a designed page carrying the matching widget, else the legacy
// page type's page. '' when the site has none.
function _pg_acct_page_url($page_ref, $legacy_type)
{
    $url = _pg_member_page_url($page_ref);
    if ($url !== '') return $url;
    $url = pg_sw_page_type_widget_url($legacy_type);
    if ($url !== '') return $url;
    $name = db_value("SELECT page_name FROM page WHERE page_type = '" . e($legacy_type) . "' LIMIT 1");
    return $name ? (defined('PATH') ? PATH : '/') . encode_url_path((string)$name) : '';
}

// ============================================================================
// Shared pieces
// ============================================================================

function _pg_acct_software_path()
{
    return (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : '');
}

function _pg_acct_signed_in()
{
    if (defined('USER_LOGGED_IN')) return USER_LOGGED_IN === true;
    return function_exists('pg_session_signed_in') && pg_session_signed_in();
}

// $url without the named query parameters.
function _pg_acct_url_without($url, $drop)
{
    $parts = explode('?', (string)$url, 2);
    if (count($parts) < 2) return $parts[0];
    parse_str($parts[1], $query);
    foreach ($drop as $key) unset($query[$key]);
    $qs = http_build_query($query);
    return $parts[0] . ($qs !== '' ? '?' . $qs : '');
}

// The pg_controls value: every named control the widget draws.
function _pg_acct_control_names($tree)
{
    $names = array();
    foreach (_pg_member_controls($tree) as $c) $names[$c['name']] = true;
    return implode(',', array_keys($names));
}

// Give every <select> named $name these choices ([value, label] pairs), the
// one matching $selected marked. A choice list the site owns - countries,
// time zones - cannot live in the tree; the designer draws the control.
function _pg_acct_select_options(&$tree, $name, $options, $selected)
{
    _pg_member_edit_control($tree, $name, function (&$node) use ($options, $selected) {
        if (strtolower((string)$node['props']['tag']) !== 'select') return;
        $kids = array();
        foreach ($options as $o) {
            $attrs = array(array('name' => 'value', 'value' => (string)$o[0]));
            if ((string)$o[0] === (string)$selected) $attrs[] = array('name' => 'selected', 'value' => '');
            $kids[] = array(
                'type'     => 'semantic',
                'props'    => array('tag' => 'option', 'text' => (string)$o[1], '_attrs' => $attrs),
                'children' => array(),
            );
        }
        $node['children'] = $kids;
    });
}

// Check the radio (or checkbox) named $name whose own value is $value, and
// clear the others of the group.
function _pg_acct_check_value(&$tree, $name, $value)
{
    if (!is_array($tree)) return;
    if (_pg_member_control_name($tree) === $name) {
        $attrs = _pg_cf_node_attrs($tree);
        $own   = isset($attrs['value']) ? (string)$attrs['value'] : '';
        _pg_member_set_attr($tree, 'checked', ($own !== '' && $own === (string)$value) ? '' : null);
        return;
    }
    if (!empty($tree['children']) && is_array($tree['children'])) {
        foreach ($tree['children'] as &$c) _pg_acct_check_value($c, $name, $value);
        unset($c);
    }
}

// Fill the controls of a record form: a text box gets its value, a select its
// choice, a radio group its checked member. $selects: name => choices.
function _pg_acct_fill_values(&$tree, $values, $selects = array())
{
    foreach ($values as $name => $value) {
        if (isset($selects[$name])) {
            _pg_acct_select_options($tree, $name, $selects[$name], $value);
        }
        _pg_member_fill_control($tree, $name, (string)$value);
    }
    foreach ($selects as $name => $choices) {
        if (!array_key_exists($name, $values)) _pg_acct_select_options($tree, $name, $choices, '');
    }
}

// label => value arrays (get_salutation_options() and the like) as the
// [value, label] pairs _pg_acct_select_options() takes.
function _pg_acct_pairs($label_value)
{
    $out = array();
    foreach ((array)$label_value as $label => $value) $out[] = array((string)$value, (string)$label);
    return $out;
}

function _pg_acct_country_options()
{
    $out = array(array('', ''));
    $rows = array();
    foreach ((array)db_items("SELECT name, code FROM countries") as $c) {
        $rows[] = array('code' => (string)$c['code'], 'label' => pg_geo_country_label($c['name'], $c['code']));
    }
    // Named and ordered in the site's language (pg_geo_country_label()).
    foreach (pg_geo_sort_by_label($rows) as $c) {
        $out[] = array($c['code'], $c['label']);
    }
    return $out;
}

function _pg_acct_default_country()
{
    return (string)db_value("SELECT code FROM countries WHERE default_selected = 1 LIMIT 1");
}

function _pg_acct_timezone_options()
{
    $site = (defined('TIMEZONE') && TIMEZONE != '') ? TIMEZONE : (defined('SERVER_TIMEZONE') ? SERVER_TIMEZONE : '');
    $zones = get_timezones();
    $label = array_search(pg_timezone_list_value($site), $zones);
    $out = array(array('', lang('Default') . ': ' . ($label ? $label : $site)));
    foreach ($zones as $zone_label => $zone) $out[] = array((string)$zone, (string)$zone_label);
    return $out;
}

function _pg_acct_password_rules()
{
    return (defined('STRONG_PASSWORD') && STRONG_PASSWORD && function_exists('get_strong_password_requirements'))
        ? '<div class="pg-password-rules small text-muted">' . get_strong_password_requirements() . '</div>' : '';
}

// A widget for signed-in members, seen by a visitor who is not: the controls
// go, the words and the sign-in link stay.
function _pg_acct_render_signed_out($tree, $cfg, $flags, $tokens, $form_name)
{
    $login_url = _pg_acct_page_url(isset($cfg['login_page_id']) ? $cfg['login_page_id'] : 0, 'login');
    if ($login_url === '') $login_url = _pg_acct_software_path() . '/';
    $message = (isset($cfg['not_logged_in_message']) && is_string($cfg['not_logged_in_message']) && $cfg['not_logged_in_message'] !== '')
        ? $cfg['not_logged_in_message'] : lang('You must be logged in to view this page.');

    _eo_apply_visibility_bindings($tree, $flags);
    _pg_member_drop_all_controls($tree);
    $empty = array();
    foreach ($tokens as $t) $empty[$t] = '';
    $empty['__login_url'] = $login_url;
    _pg_member_drop_empty_links($tree, $empty);
    pg_cf_link_labels($tree);
    _pg_inject_messages_node($tree, $form_name);

    $split = _split_widget_tree($tree);
    $html  = trim(_render_tree_node($split['static_tree'], 0, 0));
    $html  = str_replace('<!--pg-loop-slot-->', '', $html);
    return _pg_member_apply_tokens($html, array(
        '__site_name'     => h(_pg_member_site_name()),
        '__login_url'     => h($login_url),
        '__not_logged_in' => h($message),
    ));
}

// ============================================================================
// Processor side: the posts the widgets make
// ============================================================================

// The controls a widget said it drew (pg_controls), or null for a post from
// a legacy screen - which draws every field, so everything is handled.
function pg_sw_posted_controls()
{
    if (!isset($_POST['pg_controls']) || !is_string($_POST['pg_controls'])) return null;
    $out = array();
    foreach (explode(',', $_POST['pg_controls']) as $name) {
        $name = trim($name);
        if ($name !== '' && preg_match('/^[A-Za-z0-9_-]{1,100}$/', $name)) $out[] = $name;
    }
    return $out;
}

// Whether a processor handles a field: always for a legacy post, and for a
// widget post only when the widget drew it.
function pg_sw_posted_control($controls, $name)
{
    return ($controls === null) || in_array((string)$name, $controls, true);
}

// The page a widget post names in return_to, as a same-site path, or ''.
function pg_sw_return_to($value = null)
{
    if ($value === null) $value = isset($_POST['return_to']) ? $_POST['return_to'] : '';
    if (!is_scalar($value) || (string)$value === '') return '';
    $path = pg_safe_redirect_path((string)$value, '/__none__');
    return ($path === '/__none__') ? '' : $path;
}

// The config of widget $widget_id when it is of $type, else null. A processor
// reads the settings of the widget that drew the form (the address type
// switch of an address book) from the widget itself.
function pg_sw_widget_cfg($widget_id, $type)
{
    $widget_id = (int)$widget_id;
    if ($widget_id <= 0) return null;
    $cfg = json_decode((string)db_value("SELECT system_region_config FROM shared_components WHERE id = '" . $widget_id . "' LIMIT 1"), true);
    return (is_array($cfg) && isset($cfg['regionType']) && $cfg['regionType'] === (string)$type) ? $cfg : null;
}

// Where a widget post goes once it succeeded: the page it named (send_to),
// else the site's my account page, else back to the widget. The notice is
// left on the liveform the destination prints - 'my_account' away from the
// widget, the widget's own form when it comes back to itself.
function pg_sw_account_done($notice, $send_to, $return_to, $form_name)
{
    $url = '';
    if (is_scalar($send_to) && (string)$send_to !== '') {
        $url = pg_safe_redirect_path((string)$send_to, '/__none__');
        if ($url === '/__none__') $url = '';
    }
    if ($url === '') {
        $url = pg_sw_page_type_widget_url('my account');
        if ($url === '') $url = (string)get_page_type_url('my account');
    }
    if ($url !== '') {
        $done = new liveform('my_account');
        $done->add_notice($notice);
        return $url;
    }
    $done = new liveform($form_name);
    $done->add_notice($notice);
    return $return_to;
}

// ============================================================================
// logout
// ============================================================================

// Render a 'logout' system widget.
//
// Signed in: the widget is wrapped in a form posting to logout.php with the
// session token, so its button signs the visitor out at once. send_to brings
// them back here (logout.php adds ?logged_out=true) unless the widget names
// another page. Signed out: the form goes, the words and links stay.
//
// Visibility flags: is_signed_in, is_signed_out, just_logged_out.
// Tokens: ^^__site_name^^, ^^__username^^, ^^__login_url^^, ^^__home_url^^.
// cfg: redirect_page_id (after logging out), login_page_id (the "sign in
// again" link; a page with the login widget, then the legacy login page).
function _render_system_widget_logout($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();
    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';
    // Nothing reports here (the legacy page prints no messages); the name
    // keeps the node from taking the messages of a form beside it.
    _pg_inject_messages_node($tree, 'logout');

    $signed_in = _pg_acct_signed_in();
    $self      = _pg_acct_url_without(function_exists('get_request_uri') ? (string)get_request_uri() : '', array('logged_out'));
    $send_to   = _pg_member_page_url(isset($cfg['redirect_page_id']) ? $cfg['redirect_page_id'] : 0);
    if ($send_to === '') $send_to = $self;
    $login_url = _pg_acct_page_url(isset($cfg['login_page_id']) ? $cfg['login_page_id'] : 0, 'login');
    if ($login_url === '') $login_url = _pg_acct_software_path() . '/';
    $home_url  = defined('PATH') ? PATH : '/';

    _eo_apply_visibility_bindings($tree, array(
        'is_signed_in'    => $signed_in,
        'is_signed_out'   => !$signed_in,
        'just_logged_out' => !$signed_in && isset($_GET['logged_out']) && $_GET['logged_out'] === 'true',
    ));
    if (!$signed_in) _pg_member_drop_all_controls($tree);
    _pg_member_drop_empty_links($tree, array('__login_url' => $login_url, '__home_url' => $home_url));
    pg_cf_link_labels($tree);

    $split    = _split_widget_tree($tree);
    $rendered = str_replace('<!--pg-loop-slot-->', '', trim(_render_tree_node($split['static_tree'], 0, 0)));
    if ($signed_in) {
        $rendered = _pg_member_form_wrap($rendered, _pg_acct_software_path() . '/logout.php',
            array('send_to' => $send_to), 'pg-logout-form');
    }
    return _pg_member_apply_tokens($rendered, array(
        '__site_name' => h(_pg_member_site_name()),
        '__username'  => h($signed_in && defined('USER_USERNAME') ? (string)USER_USERNAME : ''),
        '__login_url' => h($login_url),
        '__home_url'  => h($home_url),
    ));
}

// ============================================================================
// change_password
// ============================================================================

// Render a 'change_password' system widget.
//
// Posts to change_password.php. Controls by name: email_address,
// current_password, new_password, new_password_verify, password_hint. A
// signed-in account that has no password yet (created through Google) sets
// its first one: current_password is dropped, as on the legacy screen.
// password_hint is dropped while the site setting is off.
//
// Visibility flags: is_signed_in, is_signed_out, has_password,
// password_not_set. Section: password_rules. Tokens: ^^__site_name^^,
// ^^__my_account_url^^, ^^__password_rules^^. Errors: liveform
// 'change_password'; the confirmation goes where send_to points, else to
// the my account page.
// cfg: redirect_page_id (after the change), my_account_page_id (the "back"
// link; a page with the my account widget, then the legacy page).
function _render_system_widget_change_password($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();
    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';

    $signed_in = _pg_acct_signed_in();
    $page_url  = function_exists('get_request_uri') ? (string)get_request_uri() : '';
    $lf        = new liveform('change_password');

    $password_not_set = false;
    if ($signed_in && defined('USER_ID') && (int)USER_ID > 0) {
        $password_not_set = ((int)db_value("SELECT user_password_algo FROM user WHERE user_id = '" . (int)USER_ID . "'") === 3);
    }

    if ($lf->field_in_session('token')) {
        _pg_member_fill_from_form($tree, $lf, array('current_password', 'new_password', 'new_password_verify'));
    } elseif ($signed_in) {
        _pg_member_fill_control($tree, 'email_address', defined('USER_EMAIL_ADDRESS') ? (string)USER_EMAIL_ADDRESS : '');
    }
    if ($password_not_set) _pg_member_drop_control($tree, 'current_password');
    if (!(defined('PASSWORD_HINT') && PASSWORD_HINT)) _pg_member_drop_control($tree, 'password_hint');

    $rules_html  = _pg_acct_password_rules();
    $account_url = $signed_in ? _pg_acct_page_url(isset($cfg['my_account_page_id']) ? $cfg['my_account_page_id'] : 0, 'my account') : '';
    $send_to     = _pg_member_page_url(isset($cfg['redirect_page_id']) ? $cfg['redirect_page_id'] : 0);

    _eo_apply_visibility_bindings($tree, array(
        'is_signed_in'     => $signed_in,
        'is_signed_out'    => !$signed_in,
        'has_password'     => !$password_not_set,
        'password_not_set' => $password_not_set,
    ));
    pg_cf_apply_section_bindings($tree, array('password_rules' => $rules_html));
    _pg_member_drop_empty_links($tree, array('__my_account_url' => $account_url));
    pg_cf_link_labels($tree);
    _pg_inject_messages_node($tree, 'change_password');

    $split    = _split_widget_tree($tree);
    $rendered = str_replace('<!--pg-loop-slot-->', '', trim(_render_tree_node($split['static_tree'], 0, 0)));
    $lf->remove();
    $rendered = _pg_member_form_wrap($rendered, _pg_acct_software_path() . '/change_password.php', array(
        'send_to'     => $send_to,
        'return_to'   => $page_url,
        'pg_controls' => _pg_acct_control_names($tree),
    ), 'pg-change-password-form');

    return _pg_member_apply_tokens($rendered, array(
        '__site_name'      => h(_pg_member_site_name()),
        '__my_account_url' => h($account_url),
        '__password_rules' => $rules_html,
    ));
}

// ============================================================================
// set_password
// ============================================================================

// Render a 'set_password' system widget - the page a password reset e-mail
// links to (?k=<token>). forgot_password.php finds it through
// get_page_type_url('set password') when the site has no legacy page of that
// type.
//
// Posts to set_password.php. Controls by name: new_password, password_hint.
// Three states: the form (the token is good); the token is missing, unknown
// or older than a day (flag token_invalid, the reason in ^^__token_error^^
// with a link to ask for a new mail); done (flag is_confirmed - the controls
// go and the Messages block prints the notice with its Continue link). An
// editor without a token sees the form, to design it.
//
// Visibility flags: show_form, token_invalid, is_confirmed. Section:
// password_rules. Tokens: ^^__site_name^^, ^^__email^^ (the account's
// address), ^^__token_error^^ (HTML), ^^__forgot_password_url^^,
// ^^__password_rules^^.
// cfg: redirect_page_id (the Continue link after the password is set; the
// link's own ?send_to= wins), forgot_password_page_id.
function _render_system_widget_set_password($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();
    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';

    $page_url   = function_exists('get_request_uri') ? (string)get_request_uri() : '';
    $lf         = new liveform('set_password');
    $forgot_url = _pg_acct_page_url(isset($cfg['forgot_password_page_id']) ? $cfg['forgot_password_page_id'] : 0, 'forgot password');
    if ($forgot_url === '') $forgot_url = _pg_acct_software_path() . '/forgot_password.php';

    $token = (isset($_GET['k']) && is_scalar($_GET['k'])) ? trim((string)$_GET['k']) : '';
    $state = 'form';
    $error = '';
    $email = '';
    if ((string)$lf->get('screen') === 'confirm') {
        $state = 'confirm';
    } elseif ($token !== '') {
        $user = db_item(
            "SELECT user_email AS email, token_timestamp FROM user
             WHERE token = '" . e(hash('sha256', $token)) . "' LIMIT 1");
        if (!is_array($user) || empty($user['email'])) {
            $state = 'invalid';
            $error = lang(array('string' => 'Sorry, the token is not valid, so we can\'t allow you to set a password. Please check if your email client possibly broke the link. If so, then you might try fixing the link. Otherwise, the token might be old, so please <a href="{var:1}">request a new email</a>.', 'vars' => array(h($forgot_url))));
        } elseif (((int)$user['token_timestamp'] + 86400) < time()) {
            $state = 'invalid';
            $error = lang(array('string' => 'Sorry, the token has expired. Please <a href="{var:1}">request a new email</a>.', 'vars' => array(h($forgot_url))));
        } else {
            $email = (string)$user['email'];
        }
    } elseif ($mode !== 'edit') {
        $state = 'invalid';
        $error = h(lang('Sorry, the token is missing from the address, so we can\'t allow you to set a password.'));
    }

    $send_to = _pg_member_query_path('send_to');
    if ($send_to === '') $send_to = _pg_member_page_url(isset($cfg['redirect_page_id']) ? $cfg['redirect_page_id'] : 0);

    if ($state !== 'form') {
        _pg_member_drop_all_controls($tree);
    } elseif ($lf->field_in_session('token')) {
        _pg_member_fill_from_form($tree, $lf, array('new_password'));
    }
    if (!(defined('PASSWORD_HINT') && PASSWORD_HINT)) _pg_member_drop_control($tree, 'password_hint');

    $rules_html = ($state === 'form') ? _pg_acct_password_rules() : '';
    _eo_apply_visibility_bindings($tree, array(
        'show_form'     => $state === 'form',
        'token_invalid' => $state === 'invalid',
        'is_confirmed'  => $state === 'confirm',
    ));
    pg_cf_apply_section_bindings($tree, array('password_rules' => $rules_html));
    _pg_member_drop_empty_links($tree, array('__forgot_password_url' => $forgot_url));
    pg_cf_link_labels($tree);
    _pg_inject_messages_node($tree, 'set_password');

    $split    = _split_widget_tree($tree);
    $rendered = str_replace('<!--pg-loop-slot-->', '', trim(_render_tree_node($split['static_tree'], 0, 0)));
    $lf->remove();
    if ($state === 'form') {
        $rendered = _pg_member_form_wrap($rendered, _pg_acct_software_path() . '/set_password.php', array(
            'k'         => $token,
            'send_to'   => $send_to,
            'return_to' => $page_url,
        ), 'pg-set-password-form');
    }

    return _pg_member_apply_tokens($rendered, array(
        '__site_name'           => h(_pg_member_site_name()),
        '__email'               => h($email),
        '__token_error'         => $error,
        '__forgot_password_url' => h($forgot_url),
        '__password_rules'      => $rules_html,
    ));
}

// ============================================================================
// account_profile
// ============================================================================

// The contact columns the profile form edits, by control name.
function pg_sw_profile_fields()
{
    return array('salutation', 'first_name', 'last_name', 'suffix', 'title', 'company',
        'business_address_1', 'business_address_2', 'business_city', 'business_state',
        'business_zip_code', 'business_country', 'business_phone', 'mobile_phone',
        'home_phone', 'business_fax', 'tax_number', 'tax_office');
}

// Render an 'account_profile' system widget: the signed-in member's contact
// details, posting to my_account_profile.php.
//
// Controls by name: the pg_sw_profile_fields() columns and timezone. A
// <select> named salutation, suffix, business_country or timezone gets the
// site's choices; any control may be left out - the processor then keeps
// that column as it is.
//
// Visibility flags: is_signed_in, is_signed_out. Tokens: ^^__site_name^^,
// ^^__username^^, ^^__email_address^^, ^^__my_account_url^^, and for a
// visitor who is not signed in ^^__login_url^^, ^^__not_logged_in^^.
// Errors: liveform 'my_account_profile'; the confirmation goes where send_to
// points, else to the my account page.
// cfg: redirect_page_id, my_account_page_id, login_page_id,
// not_logged_in_message.
function _render_system_widget_account_profile($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();
    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';

    if (!_pg_acct_signed_in()) {
        return _pg_acct_render_signed_out($tree, $cfg,
            array('is_signed_in' => false, 'is_signed_out' => true),
            array('__my_account_url'), 'my_account_profile');
    }

    $page_url = function_exists('get_request_uri') ? (string)get_request_uri() : '';
    $lf = new liveform('my_account_profile');

    $user = db_item("SELECT user_contact, timezone FROM user WHERE user_id = '" . (int)USER_ID . "' LIMIT 1");
    $contact = array();
    if (is_array($user) && (int)$user['user_contact'] > 0) {
        $contact = db_item("SELECT " . implode(', ', pg_sw_profile_fields()) . " FROM contacts WHERE id = '" . (int)$user['user_contact'] . "' LIMIT 1");
        if (!is_array($contact)) $contact = array();
    }

    $values = array();
    $submitted = $lf->field_in_session('token');
    foreach (array_merge(pg_sw_profile_fields(), array('timezone')) as $f) {
        if ($submitted) {
            $values[$f] = (string)$lf->get_field_value($f);
        } elseif ($f === 'timezone') {
            $values[$f] = is_array($user) ? pg_timezone_list_value((string)$user['timezone']) : '';
        } else {
            $values[$f] = isset($contact[$f]) ? (string)$contact[$f] : '';
        }
    }
    if ($values['business_country'] === '') $values['business_country'] = _pg_acct_default_country();

    _pg_acct_fill_values($tree, $values, array(
        'salutation'       => _pg_acct_pairs(get_salutation_options()),
        'suffix'           => _pg_acct_pairs(get_suffix_options()),
        'business_country' => _pg_acct_country_options(),
        'timezone'         => _pg_acct_timezone_options(),
    ));

    $account_url = _pg_acct_page_url(isset($cfg['my_account_page_id']) ? $cfg['my_account_page_id'] : 0, 'my account');
    $send_to     = _pg_member_page_url(isset($cfg['redirect_page_id']) ? $cfg['redirect_page_id'] : 0);

    _eo_apply_visibility_bindings($tree, array('is_signed_in' => true, 'is_signed_out' => false));
    _pg_member_drop_empty_links($tree, array('__my_account_url' => $account_url, '__login_url' => ''));
    pg_cf_link_labels($tree);
    _pg_inject_messages_node($tree, 'my_account_profile');

    $split    = _split_widget_tree($tree);
    $rendered = str_replace('<!--pg-loop-slot-->', '', trim(_render_tree_node($split['static_tree'], 0, 0)));
    $lf->remove();
    $rendered = _pg_member_form_wrap($rendered, _pg_acct_software_path() . '/my_account_profile.php', array(
        'send_to'     => $send_to,
        'return_to'   => $page_url,
        'pg_controls' => _pg_acct_control_names($tree),
    ), 'pg-account-profile-form');

    return _pg_member_apply_tokens($rendered, array(
        '__site_name'      => h(_pg_member_site_name()),
        '__username'       => h(defined('USER_USERNAME') ? (string)USER_USERNAME : ''),
        '__email_address'  => h(defined('USER_EMAIL_ADDRESS') ? (string)USER_EMAIL_ADDRESS : ''),
        '__my_account_url' => h($account_url),
    ) + pg_sw_entity_account_tokens((int)USER_ID, $tree_json));
}

// ============================================================================
// email_preferences
// ============================================================================

// Render an 'email_preferences' system widget, posting to
// email_preferences.php.
//
// Whose preferences: the signed-in member's, or - for a visitor arriving
// from a campaign's "email preferences" link - the contact the link's signed
// ?id=&sig= names. Anyone else is asked to sign in.
//
// Controls by name: email_address, opt_in, opt_in_sms (drawn only while SMS
// is on and the contact has a mobile number). loop_area: one row per mailing
// list the contact may see (open lists, and closed ones the contact is on);
// the row's checkbox, named contact_group, becomes contact_group_<list id>
// and is checked by the server. Trees saved before that carry the name
// contact_group_^^__group_id^^, read the same way. Row tokens:
// ^^__group_id^^, ^^__group_name^^, ^^__group_description^^.
//
// Visibility flags: show_form (whose preferences is known), is_signed_in,
// is_signed_out, has_contact_groups, no_contact_groups. Tokens:
// ^^__site_name^^, ^^__email_address^^,
// ^^__my_account_url^^, ^^__login_url^^, ^^__not_logged_in^^.
// Errors and the notice: liveform 'email_preferences'.
// cfg: my_account_page_id, login_page_id, not_logged_in_message.
function _render_system_widget_email_preferences($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();
    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';

    $signed_in  = _pg_acct_signed_in();
    $contact_id = 0;
    $email      = '';
    $opt_in     = 0;
    $link_id    = (isset($_GET['id']) && is_scalar($_GET['id'])) ? trim((string)$_GET['id']) : '';
    $link_sig   = (isset($_GET['sig']) && is_scalar($_GET['sig'])) ? trim((string)$_GET['sig']) : '';

    if ($signed_in) {
        $user = db_item("SELECT user_email, user_contact FROM user WHERE user_id = '" . (int)USER_ID . "' LIMIT 1");
        if (is_array($user)) {
            $email      = (string)$user['user_email'];
            $contact_id = (int)$user['user_contact'];
        }
    } elseif ($link_id !== '' && $link_sig !== '') {
        $linked = str_rot13((string)base64_decode($link_id));
        if (validate_email_address($linked) && pg_email_preferences_signature_valid($linked, $link_sig)) {
            $row = db_item("SELECT id FROM contacts WHERE email_address = '" . e($linked) . "' LIMIT 1");
            if (is_array($row)) {
                $email      = $linked;
                $contact_id = (int)$row['id'];
            }
        }
    }
    if ($email === '') {
        return _pg_acct_render_signed_out($tree, $cfg,
            array('show_form' => false, 'is_signed_in' => false, 'is_signed_out' => true, 'has_contact_groups' => false, 'no_contact_groups' => true),
            array('__my_account_url'), 'email_preferences');
    }
    if ($contact_id > 0) {
        $opt_in = (int)db_value("SELECT opt_in FROM contacts WHERE id = '" . $contact_id . "'");
    }

    // The lists the contact may see, and whether they are on each.
    $groups = array();
    foreach ((array)db_items(
        "SELECT id, name, description, email_subscription_type FROM contact_groups
         WHERE email_subscription = '1' ORDER BY name") as $g) {
        $member = $contact_id > 0 && db_value(
            "SELECT contact_id FROM contacts_contact_groups_xref
             WHERE contact_id = '" . $contact_id . "' AND contact_group_id = '" . (int)$g['id'] . "' LIMIT 1");
        if ($g['email_subscription_type'] !== 'open' && !$member) continue;
        $on = false;
        if ($member) {
            $o = db_item("SELECT opt_in FROM opt_in WHERE contact_id = '" . $contact_id . "' AND contact_group_id = '" . (int)$g['id'] . "' LIMIT 1");
            $on = !is_array($o) || (int)$o['opt_in'] === 1;
        }
        $g['on'] = $on;
        $groups[] = $g;
    }

    // The SMS box (opt_in_sms) beside the opt-in, while SMS is on and the
    // contact has a mobile number; it starts from the person's last SMS
    // answer in the consent log, ticked when there is none.
    $sms_offered = ($mode === 'edit')
        || pg_consent_sms_offered(pg_consent_sms_number_known(pg_consent_sms_contact_number($contact_id)));

    $lf = new liveform('email_preferences');
    $submitted = $lf->field_in_session('token');
    if ($submitted) {
        _pg_member_fill_control($tree, 'email_address', (string)$lf->get_field_value('email_address'));
        _pg_member_fill_control($tree, 'opt_in', '1', (bool)$lf->get_field_value('opt_in'));
        _pg_member_fill_control($tree, 'opt_in_sms', '1', (bool)$lf->get_field_value('opt_in_sms'));
    } else {
        $sms_on = $sms_offered && pg_consent_sms_start_for(array(
            'email' => $email, 'user_id' => $signed_in ? (int)USER_ID : 0, 'contact_id' => $contact_id));
        _pg_member_fill_control($tree, 'email_address', $email);
        _pg_member_fill_control($tree, 'opt_in', '1', $opt_in === 1);
        _pg_member_fill_control($tree, 'opt_in_sms', '1', $sms_on);
    }
    if (!$sms_offered) {
        _pg_member_drop_control($tree, 'opt_in_sms');
    }

    $account_url = $signed_in ? _pg_acct_page_url(isset($cfg['my_account_page_id']) ? $cfg['my_account_page_id'] : 0, 'my account') : '';
    _eo_apply_visibility_bindings($tree, array(
        'show_form'          => true,
        'is_signed_in'       => $signed_in,
        'is_signed_out'      => false,
        'has_contact_groups' => count($groups) > 0,
        'no_contact_groups'  => count($groups) === 0,
    ));
    _pg_member_drop_empty_links($tree, array('__my_account_url' => $account_url, '__login_url' => ''));
    pg_cf_link_labels($tree);
    _pg_inject_messages_node($tree, 'email_preferences');

    $split = _split_widget_tree($tree);
    $controls = _pg_acct_control_names($split['static_tree']);
    $rows = '';
    if ($split['loop_children'] !== null) {
        $row_tree = array('type' => 'root', 'props' => array(), 'children' => $split['loop_children']);
        $has_box  = false;
        foreach ($groups as $g) {
            $gid = (int)$g['id'];
            $checked = $submitted ? (bool)$lf->get_field_value('contact_group_' . $gid) : $g['on'];
            $t = $row_tree;
            _pg_email_preferences_name_box($t, 'contact_group_' . $gid);
            _pg_member_fill_control($t, 'contact_group_' . $gid, '1', $checked);
            if (!$has_box) {
                foreach (_pg_member_controls($t) as $c) {
                    if ($c['name'] === 'contact_group_' . $gid) { $has_box = true; break; }
                }
            }
            $html = '';
            foreach ($t['children'] as $child) $html .= _render_tree_node($child, 0, 0);
            $html = _pg_sw_fill_tokens($html, array(
                '__group_id'          => (string)$gid,
                '__group_name'        => h((string)$g['name']),
                '__group_description' => h((string)$g['description']),
            ));
            $rows .= pg_sw_uniquify_row_ids($html, $widget_id, $gid);
            if ($has_box) $controls .= ($controls !== '' ? ',' : '') . 'contact_group_' . $gid;
        }
    }
    $rendered = str_replace('<!--pg-loop-slot-->', $rows, trim(_render_tree_node($split['static_tree'], 0, 0)));
    $lf->remove();
    $rendered = _pg_member_form_wrap($rendered, _pg_acct_software_path() . '/email_preferences.php', array(
        'id'          => $signed_in ? '' : $link_id,
        'sig'         => $signed_in ? '' : $link_sig,
        'return_to'   => function_exists('get_request_uri') ? (string)get_request_uri() : '',
        'pg_controls' => $controls,
    ), 'pg-email-preferences-form');

    return _pg_member_apply_tokens($rendered, array(
        '__site_name'      => h(_pg_member_site_name()),
        '__email_address'  => h($email),
        '__my_account_url' => h($account_url),
    ));
}

// Give a mailing list row's checkbox the list's own name: `contact_group`,
// or the older contact_group_^^__group_id^^, becomes $name.
function _pg_email_preferences_name_box(&$tree, $name)
{
    if (!is_array($tree)) return;
    $own = _pg_member_control_name($tree);
    if ($own === 'contact_group' || $own === 'contact_group_^^__group_id^^') {
        _pg_member_set_attr($tree, 'name', $name);
    }
    if (!empty($tree['children']) && is_array($tree['children'])) {
        foreach ($tree['children'] as &$c) _pg_email_preferences_name_box($c, $name);
        unset($c);
    }
}

// ============================================================================
// address_book
// ============================================================================

// The address_book columns the recipient form edits, by control name.
function pg_sw_address_book_fields()
{
    return array('ship_to_name', 'salutation', 'first_name', 'last_name', 'company',
        'address_1', 'address_2', 'city', 'state', 'zip_code', 'country',
        'address_type', 'phone_number');
}

// Render an 'address_book' system widget: the signed-in member's saved
// recipients and the form that adds one or, with ?id=, edits one. Posts to
// update_address_book.php; a row's remove link goes to remove_recipient.php.
// Both come back here.
//
// Controls by name: the pg_sw_address_book_fields() columns. A <select>
// named salutation or country gets the site's choices; the address_type
// radios (residential / business) are drawn only while the widget's
// "Address type" switch is on, and are then required.
//
// loop_area: one row per recipient. Row tokens: ^^__recipient_id^^,
// ^^__recipient_name^^ (ship-to name), ^^__recipient_full_name^^,
// ^^__recipient_company^^, ^^__recipient_address^^ (one line),
// ^^__recipient_address_html^^ (lines), ^^__recipient_phone^^,
// ^^__recipient_edit_url^^, ^^__recipient_remove_url^^.
//
// Visibility flags: is_signed_in, is_signed_out, has_recipients,
// no_recipients, is_editing, is_adding. Tokens: ^^__site_name^^,
// ^^__recipient_count^^, ^^__add_url^^ (the empty form),
// ^^__my_account_url^^, ^^__login_url^^, ^^__not_logged_in^^.
// Errors and the notice: liveform 'update_address_book'.
// cfg: address_type (bool), redirect_page_id (after saving; default: back
// here), my_account_page_id, login_page_id, not_logged_in_message.
function _render_system_widget_address_book($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();
    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';

    $no_state = array('is_signed_in' => false, 'is_signed_out' => true, 'has_recipients' => false,
                      'no_recipients' => true, 'is_editing' => false, 'is_adding' => false);
    if (!_pg_acct_signed_in()) {
        return _pg_acct_render_signed_out($tree, $cfg, $no_state, array('__my_account_url', '__add_url'), 'update_address_book');
    }

    $self       = _pg_acct_url_without(function_exists('get_request_uri') ? (string)get_request_uri() : '', array('id'));
    $edit_id    = (isset($_GET['id']) && is_scalar($_GET['id'])) ? (int)$_GET['id'] : 0;
    $type_on    = !empty($cfg['address_type']);
    $recipients = (array)db_items("SELECT * FROM address_book WHERE user = '" . (int)USER_ID . "' ORDER BY ship_to_name");
    $editing    = null;
    foreach ($recipients as $r) {
        if ($edit_id > 0 && (int)$r['id'] === $edit_id) $editing = $r;
    }

    $countries = array();
    foreach ((array)db_items("SELECT code, name FROM countries") as $c) $countries[(string)$c['code']] = (string)$c['name'];

    // The form: what the visitor typed before a refused post, the recipient
    // being edited, else empty with the default country.
    $lf = new liveform('update_address_book');
    $values = array();
    foreach (pg_sw_address_book_fields() as $f) {
        if ($lf->field_in_session('token')) $values[$f] = (string)$lf->get_field_value($f);
        elseif ($editing)                    $values[$f] = isset($editing[$f]) ? (string)$editing[$f] : '';
        else                                 $values[$f] = '';
    }
    if ($values['country'] === '') $values['country'] = _pg_acct_default_country();
    $type_value = $values['address_type'];
    unset($values['address_type']);
    _pg_acct_fill_values($tree, $values, array(
        'salutation' => _pg_acct_pairs(get_salutation_options()),
        'country'    => _pg_acct_country_options(),
    ));
    if ($type_on) _pg_acct_check_value($tree, 'address_type', $type_value);
    else          _pg_member_drop_control($tree, 'address_type');

    $account_url = _pg_acct_page_url(isset($cfg['my_account_page_id']) ? $cfg['my_account_page_id'] : 0, 'my account');
    $send_to     = _pg_member_page_url(isset($cfg['redirect_page_id']) ? $cfg['redirect_page_id'] : 0);

    _eo_apply_visibility_bindings($tree, array(
        'is_signed_in'   => true,
        'is_signed_out'  => false,
        'has_recipients' => count($recipients) > 0,
        'no_recipients'  => count($recipients) === 0,
        'is_editing'     => $editing !== null,
        'is_adding'      => $editing === null,
    ));
    _pg_member_drop_empty_links($tree, array('__my_account_url' => $account_url, '__login_url' => ''));
    pg_cf_link_labels($tree);
    _pg_inject_messages_node($tree, 'update_address_book');

    $split = _split_widget_tree($tree);
    $rows  = '';
    if ($split['loop_children'] !== null) {
        $template = '';
        foreach ($split['loop_children'] as $child) $template .= _render_tree_node($child, 0, 0);
        $template = trim($template);
        $token    = isset($_SESSION['software']['token']) ? (string)$_SESSION['software']['token'] : '';
        foreach ($recipients as $r) {
            $rid  = (int)$r['id'];
            $name = trim(implode(' ', array_filter(array((string)$r['salutation'], (string)$r['first_name'], (string)$r['last_name']), 'strlen')));
            // The country in the site language, as the address forms list it.
            $country = isset($countries[(string)$r['country']]) ? pg_geo_country_label($countries[(string)$r['country']], (string)$r['country']) : (string)$r['country'];
            $lines = array_values(array_filter(array(
                (string)$r['address_1'],
                (string)$r['address_2'],
                trim(implode(' ', array_filter(array((string)$r['zip_code'], (string)$r['city'],
                    ((string)$r['state'] !== (string)$r['city']) ? (string)$r['state'] : ''), 'strlen'))),
                $country,
            ), 'strlen'));
            // Then the recipient's allow-listed values (pg_sw_entity_fields(
            // 'address_book')); its type only where the form asks for it.
            $html = _pg_sw_fill_tokens($template, array(
                '__recipient_id'           => (string)$rid,
                '__recipient_name'         => h((string)$r['ship_to_name']),
                '__recipient_full_name'    => h($name),
                '__recipient_company'      => h((string)$r['company']),
                '__recipient_address'      => h(implode(', ', $lines)),
                '__recipient_address_html' => implode('<br>', array_map('h', $lines)),
                '__recipient_phone'        => h((string)$r['phone_number']),
                '__recipient_edit_url'     => h($self . (strpos($self, '?') === false ? '?' : '&') . 'id=' . $rid),
                '__recipient_remove_url'   => h(_pg_acct_software_path() . '/remove_recipient.php?id=' . $rid
                                              . '&token=' . urlencode($token) . '&send_to=' . urlencode($self)),
            ) + array_map('h', pg_sw_entity_values('address_book', array(
                'country'      => $country,
                'address_type' => $type_on ? (string)$r['address_type'] : '',
            ) + $r)));
            $rows .= pg_sw_uniquify_row_ids(pg_sw_sweep_tokens($html), $widget_id, $rid);
        }
    }
    $rendered = str_replace('<!--pg-loop-slot-->', $rows, trim(_render_tree_node($split['static_tree'], 0, 0)));
    $lf->remove();
    $rendered = _pg_member_form_wrap($rendered, _pg_acct_software_path() . '/update_address_book.php', array(
        'id'           => $editing !== null ? (string)(int)$editing['id'] : '',
        'send_to'      => $send_to,
        'return_to'    => $self,
        'pg_widget_id' => $widget_id,
        'pg_controls'  => _pg_acct_control_names($split['static_tree']),
    ), 'pg-address-book-form');

    return _pg_member_apply_tokens($rendered, array(
        '__site_name'       => h(_pg_member_site_name()),
        '__recipient_count' => (string)count($recipients),
        '__add_url'         => h($self),
        '__my_account_url'  => h($account_url),
    ));
}

// ============================================================================
// account_security
// ============================================================================

// The actions an account security button can carry: the pg_security_action
// values account_security.php reads.
function pg_sw_account_security_actions()
{
    return array('logout_all', 'unlink_google', 'unlink_microsoft', 'unlink_apple', 'revoke_device', 'mfa_begin', 'mfa_confirm',
        'mfa_cancel_setup', 'mfa_codes_seen', 'mfa_recovery_regenerate', 'mfa_disable', 'passkey_rename', 'passkey_remove');
}

// The account security widget's list (widgets_lists.php), in
// pg_sw_list_sources()'s shape: the signed-in member's passkeys, read by
// ^^account_id^^.
function pg_sw_list_security_sources()
{
    return array(
        'security.passkeys' => array(
            'family'       => 'security',
            'key'          => 'account_id',
            'label'        => lang('Passkeys of the member'),
            'arg'          => '',
            'arg_required' => false,
            'tokens'       => array(
                array('__passkey_id', lang('Passkey identifier (the Rename and Remove buttons carry it)')),
                array('__passkey_name', lang('Passkey name')),
                array('__passkey_added', lang('Added (date)')),
                array('__passkey_last_used', lang('Last used (relative time, else "Never")')),
                array('__passkey_is_synced', lang('Synced to the member\'s other devices (1 or empty)')),
            ),
        ),
    );
}

// The rows of the passkey list, as pg_sw_list_publish('security') files
// them: array('security.passkeys' => array($user_id => rows)). $passkeys:
// pg_passkeys_of_user() of the account, in its order. Plain text: the pass
// escapes the values once.
function pg_sw_list_passkey_rows($user_id, $passkeys, $date_format)
{
    $rows = array();
    foreach ((array)$passkeys as $p) {
        if (!is_array($p)) continue;
        $last = isset($p['last_used_at']) ? (int)$p['last_used_at'] : 0;
        $rows[] = array(
            '__passkey_id'        => (string)(isset($p['id']) ? (int)$p['id'] : 0),
            '__passkey_name'      => (isset($p['name']) && (string)$p['name'] !== '') ? (string)$p['name'] : lang('Passkey'),
            '__passkey_added'     => _pg_sw_short_date(isset($p['created_at']) ? (int)$p['created_at'] : 0, $date_format),
            // get_relative_time() wraps the text in a <time> element.
            '__passkey_last_used' => ($last > 0) ? strip_tags(get_relative_time(array('timestamp' => $last))) : lang('Never'),
            '__passkey_is_synced' => !empty($p['backup_state']) ? '1' : '',
        );
    }
    return array('security.passkeys' => array((int)$user_id => $rows));
}

// Whether the widget's tree holds the field of the code e-mailed for adding
// a passkey (a control named email_code in the add a passkey form). An
// account without two-step verification adds one only with that code; a
// tree saved before the field existed keeps offering two-step verification
// first. The new recovery codes form has a field of that name too, which
// says nothing about adding. $in_add: $node is inside the add form.
function _pg_sec_tree_has_email_code($node, $in_add = false)
{
    if (!is_array($node)) return false;
    $b = (isset($node['props']['_bindings']) && is_array($node['props']['_bindings'])) ? $node['props']['_bindings'] : array();
    if (isset($b['action']) && (string)$b['action'] === 'passkey_add_form') $in_add = true;
    if ($in_add) {
        foreach ((isset($node['props']['_attrs']) && is_array($node['props']['_attrs'])) ? $node['props']['_attrs'] : array() as $a) {
            if (is_array($a) && isset($a['name'], $a['value']) && strtolower((string)$a['name']) === 'name'
                && (string)$a['value'] === 'email_code') return true;
        }
    }
    if (!empty($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as $child) {
            if (_pg_sec_tree_has_email_code($child, $in_add)) return true;
        }
    }
    return false;
}

// Whether the account security widget offers new recovery codes for the
// password and a code e-mailed for that purpose: the account's second factor
// is its passkeys (no TOTP; pg_mfa_recovery_proof()), the two-step tables
// exist, nobody is signed in as the member and no fresh codes are being
// shown. The classic page's rule (pg_passkey_account_section()).
function pg_sw_mfa_email_recovery($live, $show_codes, $mfa_enabled, $passkey_count)
{
    return $live && !$show_codes && (pg_mfa_recovery_proof($mfa_enabled, (int)$passkey_count > 0) === 'email');
}

// The add a passkey flags of the account security widget, by the rules of
// pg_passkey_account_section(): $live (shown, not signed in as the member),
// $site_ok (pg_passkey_ready()), $mfa_ready (the two-step tables exist),
// $mfa_enabled, $email_field (_pg_sec_tree_has_email_code()), $count (the
// account's passkeys).
function pg_sw_passkey_add_state($live, $site_ok, $mfa_ready, $mfa_enabled, $email_field, $count)
{
    $open = $live && $site_ok && $mfa_ready;
    $may  = $open && ($mfa_enabled || $email_field);
    return array(
        'passkey_can_add'   => $may && (int)$count < PG_PASSKEYS_MAX,
        'passkey_needs_mfa' => $open && !$mfa_enabled && !$email_field,
        'passkey_limit'     => $may && (int)$count >= PG_PASSKEYS_MAX,
    );
}

// Wire the account security widget from its bindings. Every block is its own
// form, so a required code field only holds back the form it is in and Enter
// in a field submits that form's own button:
//   form   _bindings.action = 'account_security_form' -> POST to
//          account_security.php with the CSRF token and return_to
//   form   _bindings.action = 'passkey_add_form' -> the add a passkey form:
//          posts nowhere itself, hidden until pg_passkey_add_form_script()
//          finds a browser that can make passkeys
//   button _bindings.action = 'passkey_send_code' -> in the add form, sends
//          the code e-mailed for adding a passkey without two-step
//          verification (a plain button, data-pg-passkey-send)
//   button _bindings.action = 'mfa_recovery_send_code' -> in the new
//          recovery codes form of an account whose second factor is its
//          passkeys, sends the code e-mailed for them (a plain button,
//          data-pg-recovery-send)
//   button _bindings.action = a pg_sw_account_security_actions() value -> a
//          submit button carrying it; revoke_device carries the row's device
//          as pg_revoke_device, passkey_rename and passkey_remove the row's
//          passkey as pg_passkey_rename / pg_passkey_remove, since a button
//          holds one name and one value
// The wiring attributes are rewritten from the binding whatever the node
// carries. $found counts the bound forms.
function _pg_sec_apply_action_bindings(&$node, $ctx, &$found)
{
    if (!is_array($node)) return;
    $b      = (isset($node['props']['_bindings']) && is_array($node['props']['_bindings'])) ? $node['props']['_bindings'] : array();
    $type   = isset($node['type']) ? $node['type'] : '';
    $tag    = isset($node['props']['tag']) ? strtolower((string)$node['props']['tag']) : '';
    $action = isset($b['action']) ? (string)$b['action'] : '';

    $set_attrs = function (&$n, $managed, $add) {
        $kept = array();
        foreach ((isset($n['props']['_attrs']) && is_array($n['props']['_attrs'])) ? $n['props']['_attrs'] : array() as $a) {
            if (is_array($a) && isset($a['name']) && !isset($managed[strtolower((string)$a['name'])])) $kept[] = $a;
        }
        foreach ($add as $name => $value) $kept[] = array('name' => $name, 'value' => $value);
        $n['props']['_attrs'] = $kept;
    };

    if ($type === 'semantic' && $tag === 'form' && $action === 'account_security_form') {
        $found++;
        $set_attrs($node, array('action' => 1, 'method' => 1, 'novalidate' => 1),
            array('action' => $ctx['action_url'], 'method' => 'post', 'novalidate' => ''));
        $classes = preg_split('/\s+/', trim((string)(isset($node['props']['cssClass']) ? $node['props']['cssClass'] : '')), -1, PREG_SPLIT_NO_EMPTY);
        foreach (array('needs-validation', 'pg-cf-form', 'pg-account-security-form') as $c) {
            if (!in_array($c, $classes, true)) $classes[] = $c;
        }
        $node['props']['cssClass'] = implode(' ', $classes);
        if (!isset($node['children']) || !is_array($node['children'])) $node['children'] = array();
        array_unshift($node['children'], array('type' => 'content',
            'props' => array('contentType' => 'custom_html', 'html' => $ctx['hidden_html']), 'children' => array()));
    } elseif ($type === 'semantic' && $tag === 'form' && $action === 'passkey_add_form') {
        $found++;
        $set_attrs($node, array('action' => 1, 'method' => 1, 'novalidate' => 1, 'hidden' => 1, 'autocomplete' => 1, 'data-pg-passkey-add' => 1),
            array('action' => $ctx['action_url'], 'method' => 'post', 'hidden' => '', 'autocomplete' => 'off', 'data-pg-passkey-add' => ''));
        if (!isset($node['children']) || !is_array($node['children'])) $node['children'] = array();
        array_unshift($node['children'], array('type' => 'content',
            'props' => array('contentType' => 'custom_html', 'html' => $ctx['token_html']), 'children' => array()));
    } elseif ($action === 'passkey_send_code') {
        // Sends the code for adding a passkey (pg_passkey_add_form_script()):
        // a plain button the script finds by its mark, never a submit.
        $managed = array('type' => 1, 'name' => 1, 'value' => 1, 'formnovalidate' => 1, 'data-pg-passkey-send' => 1);
        if ($type === 'semantic' && $tag === 'button') {
            $set_attrs($node, $managed, array('type' => 'button', 'data-pg-passkey-send' => ''));
        } elseif ($type === 'component' && isset($node['props']['componentType']) && $node['props']['componentType'] === 'btn') {
            $node['props']['btnElement'] = 'button';
            $node['props']['btnType']    = 'button';
            $set_attrs($node, $managed, array('data-pg-passkey-send' => ''));
        }
    } elseif ($action === 'mfa_recovery_send_code') {
        // Sends the code for new recovery codes (pg_mfa_recovery_send_script()):
        // a plain button in the new recovery codes form, never a submit.
        $managed = array('type' => 1, 'name' => 1, 'value' => 1, 'formnovalidate' => 1, 'data-pg-recovery-send' => 1);
        if ($type === 'semantic' && $tag === 'button') {
            $set_attrs($node, $managed, array('type' => 'button', 'data-pg-recovery-send' => ''));
        } elseif ($type === 'component' && isset($node['props']['componentType']) && $node['props']['componentType'] === 'btn') {
            $node['props']['btnElement'] = 'button';
            $node['props']['btnType']    = 'button';
            $set_attrs($node, $managed, array('data-pg-recovery-send' => ''));
        }
    } elseif ($action !== '' && in_array($action, pg_sw_account_security_actions(), true)) {
        $carried = array('revoke_device' => array('pg_revoke_device', '^^__device_selector^^'),
                         'passkey_rename' => array('pg_passkey_rename', '^^__passkey_id^^'),
                         'passkey_remove' => array('pg_passkey_remove', '^^__passkey_id^^'));
        $add = isset($carried[$action])
            ? array('name' => $carried[$action][0], 'value' => $carried[$action][1])
            : array('name' => 'pg_security_action', 'value' => $action);
        // Cancelling a setup needs no code.
        if ($action === 'mfa_cancel_setup') $add['formnovalidate'] = '';
        $managed = array('type' => 1, 'name' => 1, 'value' => 1, 'formnovalidate' => 1);
        if ($type === 'semantic' && $tag === 'button') {
            $set_attrs($node, $managed, array_merge(array('type' => 'submit'), $add));
        } elseif ($type === 'component' && isset($node['props']['componentType']) && $node['props']['componentType'] === 'btn') {
            $node['props']['btnElement'] = 'button';
            $node['props']['btnType']    = 'submit';
            $set_attrs($node, $managed, $add);
        }
    }
    if (!empty($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as &$child) _pg_sec_apply_action_bindings($child, $ctx, $found);
        unset($child);
    }
}

// Render an 'account_security' system widget: the signed-in member's Google
// connection, remembered devices, two-step verification and passkeys - the
// designed counterpart of pg_account_security_section(). Each block is a form bound
// with _bindings.action = 'account_security_form' posting to
// account_security.php; its buttons are bound to the action they take (see
// _pg_sec_apply_action_bindings()). return_to brings every action back here.
// A tree with no bound form is wrapped whole in one form, so its buttons
// still post.
//
// Controls by name: code (a TOTP or recovery code), current_password, name
// (a passkey's name), email_code (the code e-mailed for adding a passkey;
// without that field an account without two-step verification is told to
// turn it on first).
//
// loop_area: one row per remembered device. Row tokens: ^^__device_label^^,
// ^^__device_user_agent^^, ^^__device_ip^^, ^^__device_last_used^^,
// ^^__device_first_seen^^, ^^__device_selector^^, ^^__device_passkey^^ (the
// name of the passkey that opened the session, else empty), ^^__passkey_id^^
// (its id, so a Remove button in the row removes it). Row flags:
// device_is_current, device_online, device_offline, device_is_locked,
// device_can_sign_out, device_by_passkey, and the widget's
// passkey_can_change, passkey_asks_password and passkey_asks_code.
//
// List security.passkeys (widgets_lists.php, rows by
// pg_sw_list_passkey_rows()): one row per passkey, the same rows
// pg_passkey_account_section() lists; each row's rename and remove forms
// post to account_security.php. Row tokens: ^^__passkey_id^^,
// ^^__passkey_name^^, ^^__passkey_added^^, ^^__passkey_last_used^^,
// ^^__passkey_is_synced^^ (1 or empty, for a condition).
//
// Visibility flags: is_signed_in, is_signed_out, is_impersonated, has_google,
// has_microsoft, microsoft_removable, microsoft_only, has_apple,
// apple_removable, apple_only (removable: another way in remains),
// signin_connectable (a provider that is on can still be connected),
// has_password, password_not_set, has_devices, no_devices, mfa_ready,
// mfa_off, mfa_no_key, mfa_pending, mfa_codes, mfa_on, mfa_key_unreadable,
// mfa_can_regenerate, mfa_required, and for passkeys: passkeys_shown (the
// section says something: the site can use them, or the account keeps
// some), has_passkeys, no_passkeys, passkey_can_change (rename and remove;
// not while an operator is signed in as the member), passkey_can_add,
// passkey_needs_mfa (adding waits for two-step verification: a tree
// without the email_code field), passkey_limit, passkey_unavailable (not on
// this site or at this address), passkey_asks_password and
// passkey_asks_code (the proofs adding and removing ask for),
// passkey_asks_email_code (adding asks for the e-mailed code: two-step
// verification is off), mfa_email_recovery (new recovery codes for the
// password and a code e-mailed for them: the account's second factor is its
// passkeys; a button bound to mfa_recovery_send_code sends that code and
// its sentence goes to the form's [data-pg-recovery-sent] element). Tokens: ^^__passkey_notice^^ (why adding is not
// offered: the site, the address or the limit),
// ^^__site_name^^, ^^__passkey_count^^,
// ^^__device_count^^, ^^__mfa_status_text^^, ^^__mfa_enabled_since^^,
// ^^__mfa_recovery_remaining^^ (also for mfa_email_recovery),
// ^^__mfa_recovery_left_text^^ (for mfa_email_recovery: "{n} recovery codes
// left. New codes replace them."), ^^__mfa_key^^, ^^__mfa_key_uri^^,
// ^^__mfa_qr^^ (inline SVG), ^^__recovery_codes^^,
// ^^__recovery_codes_download_url^^, ^^__signin_connect_buttons^^ (the
// "Connect ..." buttons, HTML; pg_signin_connect_buttons()), ^^__login_url^^,
// ^^__not_logged_in^^.
// Errors: liveform 'my_account_profile', where account_security.php puts the
// two-step ones.
// cfg: login_page_id, not_logged_in_message.
function _render_system_widget_account_security($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();
    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';

    $user_id = (int)(isset($_SESSION['sessionuserid']) ? $_SESSION['sessionuserid'] : 0);
    if (!_pg_acct_signed_in() || $user_id <= 0) {
        $sec_no_state = array(
            'is_signed_in' => false, 'is_signed_out' => true, 'is_impersonated' => false,
            'has_google' => false, 'has_password' => false, 'password_not_set' => false,
            'has_microsoft' => false, 'microsoft_removable' => false, 'microsoft_only' => false,
            'has_apple' => false, 'apple_removable' => false, 'apple_only' => false,
            'signin_connectable' => false,
            'has_devices' => false, 'no_devices' => false, 'mfa_ready' => false,
            'passkeys_shown' => false, 'has_passkeys' => false, 'no_passkeys' => false, 'passkey_can_change' => false,
            'passkey_can_add' => false, 'passkey_needs_mfa' => false, 'passkey_limit' => false, 'passkey_unavailable' => false,
            'passkey_asks_password' => false, 'passkey_asks_code' => false, 'passkey_asks_email_code' => false,
            'mfa_email_recovery' => false,
        );
        return _pg_acct_render_signed_out($tree, $cfg, $sec_no_state, array(), 'my_account_profile');
    }

    $self         = function_exists('get_request_uri') ? (string)get_request_uri() : '';
    $impersonated = !empty($_SESSION['software']['logged_in_as_different_user']);
    $user         = db_item("SELECT user_username, user_email, user_password_algo FROM user WHERE user_id = '" . $user_id . "' LIMIT 1");
    if (!is_array($user)) $user = array('user_username' => '', 'user_email' => '', 'user_password_algo' => 0);
    $algo       = (int)$user['user_password_algo'];
    $identities = pg_signin_identities($user_id);
    $has_google = isset($identities['google']);
    $has_microsoft       = isset($identities['microsoft']);
    $microsoft_removable = $has_microsoft && pg_signin_can_remove($user_id, 'microsoft');
    $has_apple           = isset($identities['apple']);
    $apple_removable     = $has_apple && pg_signin_can_remove($user_id, 'apple');
    // Providers that are on and not connected yet, connected from here
    // whatever their e-mail address (signin.php, &connect=).
    $connect_buttons     = pg_signin_connect_buttons($user_id, $self);

    // This browser's own token, to mark "this device".
    $current_selector = '';
    if (isset($_COOKIE['software']['auth'])) {
        $auth_parts = explode(':', (string)$_COOKIE['software']['auth'], 2);
        $current_selector = (string)$auth_parts[0];
    }
    $pin_column = function_exists('pg_auth_tokens_have_pin') && pg_auth_tokens_have_pin();
    $pk_column  = pg_auth_tokens_have_passkey();
    $devices = (array)db_items(
        "SELECT selector, user_agent, ip_address, created_at, last_used_at" . ($pin_column ? ", pinned" : "") . ($pk_column ? ", passkey_id" : "") . "
         FROM auth_tokens WHERE user_id = '" . $user_id . "'
         ORDER BY (last_used_at = 0) ASC, last_used_at DESC, created_at DESC");

    // Two-step verification: the same states pg_mfa_account_section() draws.
    // An operator signed in as this person sees the state and nothing to
    // press; fresh recovery codes come before every other state.
    $mfa_ready = pg_mfa_table_exists();
    $available = $mfa_ready && pg_mfa_available();
    $enabled   = $mfa_ready && pg_mfa_enabled($user_id);
    $live      = $mfa_ready && !$impersonated;
    $codes     = isset($_SESSION['software']['mfa_codes_show']) ? $_SESSION['software']['mfa_codes_show'] : null;
    $codes     = ($live && is_array($codes)) ? array_values(array_map('strval', $codes)) : array();
    $show_codes = count($codes) > 0;
    $row       = $mfa_ready ? pg_mfa_row($user_id) : null;
    $secret    = '';
    if ($live && !$show_codes && !$enabled && $available && $row !== null
        && (string)$row['pending_secret'] !== ''
        && (time() - (int)$row['pending_at']) <= pg_mfa_setup_lifetime()) {
        $secret = pg_mfa_secret_decode($row['pending_secret']);
    }
    $pending = ($secret !== '');
    $mfa_on  = $live && !$show_codes && $enabled;

    $status_text = '';
    $since       = '';
    $remaining   = '';
    if ($impersonated && $mfa_ready) {
        $status_text = lang(array('string' => 'Two-step verification is {var:1}.', 'vars' => array($enabled ? lang('on') : lang('off'))));
    } elseif ($mfa_on && is_array($row)) {
        // get_absolute_time() wraps the date in a <time> element; the token
        // carries the text alone.
        $since     = strip_tags(get_absolute_time(array('timestamp' => (int)$row['enabled_at'], 'type' => 'date')));
        $remaining = (string)pg_mfa_recovery_remaining($user_id);
        $status_text = lang(array(
            'string' => 'Two-step verification is on since {var:1}. {var:2} recovery codes left.',
            'vars'   => array($since, $remaining)));
    }

    $key_uri = $pending
        ? pg_totp_uri(pg_mfa_issuer(), ((string)$user['user_email'] !== '') ? (string)$user['user_email'] : (string)$user['user_username'], $secret)
        : '';
    // Drawn here as inline SVG, so the key is never part of an image URL.
    $qr = $pending ? pg_qr_svg($key_uri, 200, array('label' => lang('QR code for the authenticator app'), 'class' => 'd-block')) : '';
    // Passkeys: the states pg_passkey_account_section() draws. A site where
    // they never work says nothing unless the account keeps some; an
    // operator signed in as the member sees the count only.
    $pk_table = pg_passkeys_table_exists();
    $passkeys = $pk_table ? pg_passkeys_of_user($user_id) : array();
    $pk_count = count($passkeys);
    $pk_site  = $pk_table ? pg_passkey_ready() : array('ok' => false, 'error' => 'unavailable');
    $pk_shown = $pk_table && ($pk_site['ok'] || $pk_count > 0 || $pk_site['error'] === 'host');
    $pk_live  = $pk_shown && !$impersonated;
    $pk_proofs = pg_passkey_reauth_fields($algo, $enabled, true);
    $pk_state  = pg_sw_passkey_add_state($pk_live, $pk_site['ok'], $mfa_ready, $enabled, _pg_sec_tree_has_email_code($tree), $pk_count);
    // New recovery codes for an account whose second factor is its passkeys;
    // the count of the codes left is shown with them.
    $email_recovery = pg_sw_mfa_email_recovery($live && $pk_table, $show_codes, $enabled, pg_mfa_has_passkey($user_id) ? $pk_count : 0);
    if ($email_recovery) $remaining = (string)pg_mfa_recovery_remaining($user_id);

    $codes_text = implode("\n", $codes);
    $codes_download = $show_codes ? 'data:text/plain;charset=utf-8,' . rawurlencode($codes_text . "\n") : '';

    $found = 0;
    _pg_sec_apply_action_bindings($tree, array(
        'action_url'  => _pg_acct_software_path() . '/account_security.php',
        'hidden_html' => get_token_field() . '<input type="hidden" name="return_to" value="' . h($self) . '">',
        'token_html'  => get_token_field(),
    ), $found);
    _pg_member_drop_empty_links($tree, array('__recovery_codes_download_url' => $codes_download, '__login_url' => ''));
    pg_cf_link_labels($tree);
    _pg_inject_messages_node($tree, 'my_account_profile');

    $split = _split_widget_tree($tree);
    _eo_apply_visibility_bindings($split['static_tree'], array(
        'is_signed_in'        => true,
        'is_signed_out'       => false,
        'is_impersonated'     => $impersonated,
        'has_google'          => $has_google,
        'has_microsoft'       => $has_microsoft,
        'microsoft_removable' => $microsoft_removable,
        'microsoft_only'      => $has_microsoft && !$microsoft_removable,
        'has_apple'           => $has_apple,
        'apple_removable'     => $apple_removable,
        'apple_only'          => $has_apple && !$apple_removable,
        'signin_connectable'  => $connect_buttons !== '',
        'has_password'        => $algo !== 3,
        'password_not_set'    => $algo === 3,
        'has_devices'         => count($devices) > 0,
        'no_devices'          => count($devices) === 0,
        'mfa_ready'           => $mfa_ready,
        'mfa_off'             => $live && !$show_codes && !$enabled && $available && !$pending,
        'mfa_no_key'          => $live && !$show_codes && !$enabled && !$available,
        'mfa_pending'         => $pending,
        'mfa_codes'           => $show_codes,
        'mfa_on'              => $mfa_on,
        'mfa_key_unreadable'  => $mfa_on && !$available,
        'mfa_can_regenerate'  => $mfa_on && $available,
        'mfa_required'        => $mfa_on && pg_mfa_required_for_user($user_id),
        'passkeys_shown'        => $pk_shown,
        'has_passkeys'          => $pk_live && $pk_count > 0,
        'no_passkeys'           => $pk_live && $pk_count === 0,
        'passkey_can_change'    => $pk_live,
        'passkey_can_add'       => $pk_state['passkey_can_add'],
        'passkey_needs_mfa'     => $pk_state['passkey_needs_mfa'],
        'passkey_limit'         => $pk_state['passkey_limit'],
        'passkey_unavailable'   => $pk_live && !$pk_site['ok'],
        'passkey_asks_password' => $pk_proofs['password'],
        'passkey_asks_code'     => $pk_proofs['code'],
        'passkey_asks_email_code' => $pk_proofs['email'],
        'mfa_email_recovery'    => $email_recovery,
    ));

    // Device rows see the passkey flags too: a Remove button beside a
    // session opened with a passkey asks for the same proofs as in the list.
    $pk_names = array();
    foreach ($passkeys as $p) $pk_names[(int)$p['id']] = ((string)$p['name'] !== '') ? (string)$p['name'] : lang('Passkey');
    $pk_row_flags = array(
        'passkey_can_change'    => $pk_live,
        'passkey_asks_password' => $pk_proofs['password'],
        'passkey_asks_code'     => $pk_proofs['code'],
    );
    $rows = '';
    if ($split['loop_children'] !== null) {
        $row_tree = array('type' => 'root', 'props' => array(), 'children' => $split['loop_children']);
        foreach ($devices as $i => $d) {
            $fresh  = max((int)$d['last_used_at'], (int)$d['created_at']);
            $locked = $pin_column && isset($d['pinned']) && (int)$d['pinned'] === 1;
            // The passkey that opened this session, while the account keeps
            // it (pg_passkey_remove() ends its sessions anyway).
            $d_pk   = isset($d['passkey_id']) ? (int)$d['passkey_id'] : 0;
            $d_pk   = ($d_pk > 0 && isset($pk_names[$d_pk])) ? $d_pk : 0;
            $sec_row_flags = array(
                'device_is_current'   => $current_selector !== '' && hash_equals((string)$d['selector'], $current_selector),
                'device_online'       => (time() - $fresh) < 300,
                'device_offline'      => (time() - $fresh) >= 300,
                'device_is_locked'    => $locked,
                'device_can_sign_out' => !$locked,
                'device_by_passkey'   => $d_pk > 0,
            ) + $pk_row_flags;
            $t = $row_tree;
            _eo_apply_visibility_bindings($t, $sec_row_flags);
            $html = '';
            foreach ($t['children'] as $child) $html .= _render_tree_node($child, 0, 0);
            $html = _pg_sw_fill_tokens($html, pg_sw_escape_token_values(array(
                '__device_label'      => pg_user_agent_label($d['user_agent']),
                '__device_user_agent' => trim((string)$d['user_agent']),
                '__device_ip'         => (string)$d['ip_address'],
                '__device_last_used'  => strip_tags(get_relative_time(array('timestamp' => $fresh))),
                '__device_first_seen' => date('Y-m-d H:i', (int)$d['created_at']),
                '__device_selector'   => (string)$d['selector'],
                '__device_passkey'    => $d_pk > 0 ? $pk_names[$d_pk] : '',
                '__passkey_id'        => $d_pk > 0 ? (string)$d_pk : '',
            )));
            $rows .= pg_sw_uniquify_row_ids(pg_sw_sweep_tokens($html), $widget_id, (int)$i + 1);
        }
    }
    $rendered = str_replace('<!--pg-loop-slot-->', $rows, trim(_render_tree_node($split['static_tree'], 0, 0)));
    // The passkey list's rows, after the render drew its span; the operator
    // signed in as the member gets none, as the classic page lists none.
    if ($pk_live) pg_sw_list_publish('security', pg_sw_list_passkey_rows($user_id, $passkeys, pg_sw_default_date_format('date')));

    if ($found > 0) {
        $rendered .= pg_cf_validation_script();
        if (strpos($rendered, 'data-pg-passkey-add') !== false) $rendered .= pg_passkey_add_form_script();
        if (strpos($rendered, 'data-pg-recovery-send') !== false) $rendered .= pg_mfa_recovery_send_script();
    } else {
        $rendered = _pg_member_form_wrap($rendered, _pg_acct_software_path() . '/account_security.php',
            array('return_to' => $self), 'pg-account-security-form');
    }

    return _pg_member_apply_tokens($rendered, pg_sw_escape_token_values(array(
        '__site_name'                   => _pg_member_site_name(),
        'account_id'                    => (string)$user_id,
        '__device_count'                => (string)count($devices),
        '__passkey_count'               => (string)$pk_count,
        '__passkey_notice'              => !$pk_site['ok'] ? pg_passkey_refusal($pk_site['error'], 'register')
            : (($pk_count >= PG_PASSKEYS_MAX) ? pg_passkey_refusal('limit', 'register') : ''),
        '__mfa_status_text'             => $status_text,
        '__mfa_enabled_since'           => $since,
        '__mfa_recovery_remaining'      => $remaining,
        '__mfa_recovery_left_text'      => $email_recovery ? lang(array(
            'string' => '{var:1} recovery codes left. New codes replace them.', 'vars' => $remaining)) : '',
        '__mfa_key'                     => $pending ? pg_mfa_format_secret($secret) : '',
        '__mfa_key_uri'                 => $key_uri,
        '__mfa_qr'                      => $qr,
        '__recovery_codes'              => $codes_text,
        '__recovery_codes_download_url' => $codes_download,
        '__signin_connect_buttons'      => $connect_buttons,
    ), array('__mfa_qr', '__signin_connect_buttons')));
}

// ============================================================================
// account_invoices
// ============================================================================

// Render an 'account_invoices' system widget ("My Invoices"): the signed-in
// member's issued sales invoices and returns from the ERP, what is still owed
// on each, the account's balance and the statement to download. Drawn only
// while the ERP is on and the member's contact has an ERP account; for anyone
// else it prints nothing at all, so the widget can sit on the my account page
// of a site that does not use the ERP. The documents open through
// get_account_document.php, which asks the same question again.
//
// loop_area: one row per document, newest first. Row tokens:
// ^^__invoice_number^^, ^^__invoice_kind^^, ^^__invoice_date^^,
// ^^__invoice_due_date^^, ^^__invoice_total^^, ^^__invoice_open^^,
// ^^__invoice_status^^, ^^__invoice_overdue_days^^,
// ^^__invoice_order_number^^, ^^__invoice_pdf_url^^. Row flags:
// invoice_is_invoice, invoice_is_return; the state, one of invoice_is_paid,
// invoice_is_unpaid (nothing paid, not yet due), invoice_is_partial (part
// paid, not yet due), invoice_is_overdue; invoice_is_open (anything still
// owed), invoice_has_order.
//
// Visibility flags: has_documents, no_documents, has_balance (the member
// owes), has_credit (the store owes the member), no_balance, has_overdue.
// Tokens: ^^__account_title^^, ^^__balance^^, ^^__overdue_total^^,
// ^^__document_count^^, ^^__statement_url^^.
// Errors: none - nothing posts from this widget; its Messages node is named
// for it so it leaves the page's other alerts alone.
// cfg: max_results (default 50, at most 500).
function _render_system_widget_account_invoices($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();
    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';

    $account = function_exists('pg_erp_member_account') ? pg_erp_member_account() : null;
    if ($account === null) return '';

    require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');

    $limit     = (isset($cfg['max_results']) && (int)$cfg['max_results'] > 0) ? min(500, (int)$cfg['max_results']) : 50;
    $documents = erp_storefront_documents($account['id'], $limit);
    $figures   = erp_storefront_account_figures($account['id']);
    $title     = (string)db_value("SELECT title FROM erp_accounts WHERE id = '" . (int)$account['id'] . "' LIMIT 1");
    $endpoint  = _pg_acct_software_path() . '/get_account_document.php';

    _pg_inject_messages_node($tree, 'account_invoices');

    $split = _split_widget_tree($tree);
    _eo_apply_visibility_bindings($split['static_tree'], array(
        'has_documents' => count($documents) > 0,
        'no_documents'  => count($documents) === 0,
        'has_balance'   => $figures['balance'] > 0,
        'has_credit'    => $figures['balance'] < 0,
        'no_balance'    => $figures['balance'] === 0,
        'has_overdue'   => $figures['overdue_count'] > 0,
    ));

    $status_text = array(
        'return'  => lang('Return'),
        'paid'    => lang('Paid'),
        'partial' => lang('Partially paid'),
        'open'    => lang('Open'),
    );

    $rows = '';
    if ($split['loop_children'] !== null) {
        $row_tree = array('type' => 'root', 'props' => array(), 'children' => $split['loop_children']);
        foreach ($documents as $d) {
            $state     = (string)$d['state'];
            $is_return = ($state === 'return');
            $t = $row_tree;
            _eo_apply_visibility_bindings($t, array(
                'invoice_is_invoice' => !$is_return,
                'invoice_is_return'  => $is_return,
                'invoice_is_paid'    => $state === 'paid',
                'invoice_is_unpaid'  => $state === 'open',
                'invoice_is_partial' => $state === 'partial',
                'invoice_is_overdue' => $state === 'overdue',
                'invoice_is_open'    => (int)$d['open'] > 0,
                'invoice_has_order'  => (int)$d['order_id'] > 0 && (string)$d['order_number'] !== '',
            ));
            $html = '';
            foreach ($t['children'] as $child) $html .= _render_tree_node($child, 0, 0);
            $html = _pg_sw_fill_tokens($html, pg_sw_escape_token_values(array(
                '__invoice_number'       => (string)$d['full_number'],
                '__invoice_kind'         => $is_return ? lang('Return') : lang('Invoice'),
                '__invoice_date'         => erp_document_date((string)$d['issue_date']),
                '__invoice_due_date'     => $is_return ? '' : erp_document_date((string)$d['effective_due']),
                '__invoice_total'        => erp_money_out_currency((int)$d['grand_total'], (string)$d['currency']),
                '__invoice_open'         => ((int)$d['open'] > 0) ? erp_money_out_currency((int)$d['open'], (string)$d['currency']) : '',
                '__invoice_status'       => ($state === 'overdue')
                    ? lang(array('string' => '{var:1} days overdue', 'vars' => array((int)$d['days'])))
                    : $status_text[$state],
                '__invoice_overdue_days' => ($state === 'overdue') ? (string)(int)$d['days'] : '',
                '__invoice_order_number' => ((int)$d['order_id'] > 0) ? (string)$d['order_number'] : '',
                '__invoice_pdf_url'      => $endpoint . '?id=' . (int)$d['id'],
            )));
            $rows .= pg_sw_uniquify_row_ids(pg_sw_sweep_tokens($html), $widget_id, (int)$d['id']);
        }
    }
    $rendered = str_replace('<!--pg-loop-slot-->', $rows, trim(_render_tree_node($split['static_tree'], 0, 0)));

    return _pg_member_apply_tokens($rendered, pg_sw_escape_token_values(array(
        '__account_title'  => $title,
        '__balance'        => erp_money_out_currency(abs($figures['balance']), $figures['currency']),
        '__overdue_total'  => ($figures['overdue'] > 0) ? erp_money_out($figures['overdue']) : '',
        '__document_count' => (string)count($documents),
        '__statement_url'  => $endpoint . '?statement=1&download=1',
    )));
}

// The address of the staff page a widget setting names, for a signed-in
// visitor that page lets in - the folder access decides, the same rule
// get_page.php applies on arrival. '' for anyone else, for a page in the
// recycle bin, and when no page is named.
function _pg_acct_staff_url($staff_page_id)
{
    $staff_page_id = (int)$staff_page_id;
    if ($staff_page_id <= 0) return '';
    $staff = db_item("SELECT page_name, page_folder FROM page WHERE page_id = '" . $staff_page_id . "' LIMIT 1");
    if (!is_array($staff) || (string)$staff['page_name'] === '') return '';
    if (function_exists('pg_recycle_bin_folder_ids')
        && in_array((int)$staff['page_folder'], array_map('intval', (array)pg_recycle_bin_folder_ids()), true)) return '';
    if (!check_view_access($staff['page_folder'])) return '';
    return (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . encode_url_path((string)$staff['page_name']);
}

// ============================================================================
// my_account: links to the other account pages, order history
// ============================================================================

// What the my account widget adds to its account tokens: the links to the
// other account pages, the order history section and their flags. A link
// resolves to a designed page carrying the matching widget, else the legacy
// page; with nowhere to go it is dropped together with its node. Signed out,
// every link is empty.
//
// Tokens: ^^__profile_url^^, ^^__email_preferences_url^^,
// ^^__change_password_url^^, ^^__address_book_url^^ (shop sites),
// ^^__my_account_wishlist_url^^ (shop sites: the first page carrying a
// Favourites widget, which has no legacy page type),
// ^^__logout_url^^, ^^__staff_url^^ (cfg staff_page_id, only for a member
// the staff page's folder lets in). Sections: order_history (the member's
// orders, newest first, each linking to the order view page), subscriptions
// (the member's subscriptions with their actions,
// pg_subscription_customer_section_html()). Flags: has_orders, no_orders,
// is_staff.
function _pg_my_account_extras($logged_in, $cfg = array())
{
    $shop  = defined('ECOMMERCE') && ECOMMERCE;
    $links = array(
        '__profile_url'             => '',
        '__email_preferences_url'   => '',
        '__change_password_url'     => '',
        '__address_book_url'        => '',
        '__my_account_wishlist_url' => '',
        '__logout_url'              => '',
        '__staff_url'               => '',
    );
    // 'orders' and 'order_view_url': the history's rows and the view order
    // page, for a list of the member's orders (pg_sw_list_account_rows()).
    $out = array('links' => $links, 'sections' => array('order_history' => '', 'subscriptions' => ''),
                 'flags' => array('has_orders' => false, 'no_orders' => true, 'is_staff' => false),
                 'orders' => array(), 'order_view_url' => '');
    if (!$logged_in) return $out;

    $links['__profile_url']           = _pg_acct_page_url(0, 'my account profile');
    $links['__email_preferences_url'] = _pg_acct_page_url(0, 'email preferences');
    $links['__change_password_url']   = _pg_acct_page_url(0, 'change password');
    if ($shop) {
        $links['__address_book_url']        = _pg_acct_page_url(0, 'update address book');
        $links['__my_account_wishlist_url'] = pg_wishlist_widget_page_url();
    }
    // A designed logout page asks first; without one the token signs out at once.
    $logout = pg_sw_page_type_widget_url('logout');
    $links['__logout_url'] = ($logout !== '') ? $logout
        : _pg_acct_software_path() . '/logout.php?send_to=' . urlencode(defined('PATH') ? PATH : '/')
          . '&token=' . urlencode(isset($_SESSION['software']['token']) ? (string)$_SESSION['software']['token'] : '');
    $links['__staff_url'] = _pg_acct_staff_url(is_array($cfg) && isset($cfg['staff_page_id']) ? $cfg['staff_page_id'] : 0);
    $out['flags']['is_staff'] = ($links['__staff_url'] !== '');
    $out['links'] = $links;

    if ($shop && defined('USER_ID') && (int)USER_ID > 0) {
        $orders = (array)db_items(
            "SELECT id, order_number, order_date, (total / 100) AS total, status FROM orders
             WHERE user_id = '" . (int)USER_ID . "' AND status IN ('complete', 'exported', 'cancelled')
             ORDER BY order_date DESC LIMIT 50");
        if ($orders) {
            $view = _pg_acct_page_url(0, 'view order');
            $html = '<div class="list-group pg-sw-order-history">';
            foreach ($orders as $o) {
                $inner = '<span><span class="fw-semibold">#' . h((string)$o['order_number']) . '</span>'
                       . '<span class="text-body-secondary small ms-2">' . h(_pg_sw_short_date((int)$o['order_date'], pg_sw_default_date_format('date'))) . '</span>'
                       . ($o['status'] === 'cancelled' ? '<span class="badge text-bg-secondary ms-2">' . h(lang('Cancelled')) . '</span>' : '')
                       . '</span><span>' . prepare_amount($o['total']) . '</span>';
                $cls = 'list-group-item d-flex justify-content-between align-items-center';
                $html .= ($view !== '')
                    ? '<a href="' . h($view . '?id=' . (int)$o['id']) . '" class="' . $cls . ' list-group-item-action">' . $inner . '</a>'
                    : '<div class="' . $cls . '">' . $inner . '</div>';
            }
            $out['sections']['order_history'] = $html . '</div>';
            $out['orders']         = $orders;
            $out['order_view_url'] = $view;
            $out['flags']['has_orders'] = true;
            $out['flags']['no_orders']  = false;
        }
        // The actions post back to the page the member is on.
        $out['sections']['subscriptions'] = pg_subscription_customer_section_html(
            pg_subscription_customer_rows((int)USER_ID),
            _pg_acct_software_path() . '/subscription_action.php',
            pg_safe_redirect_path(isset($_SERVER['REQUEST_URI']) ? strtok((string)$_SERVER['REQUEST_URI'], '?') : '', defined('PATH') ? PATH : '/'),
            isset($_SESSION['software']['token']) ? (string)$_SESSION['software']['token'] : '');
    }
    return $out;
}

// ============================================================================
// login_region: the part of a site header that follows the session
// ============================================================================

// A link from the login region to the sign-in or sign-up page, carrying
// ?send_to= the page the visitor is on, so they come back where they were.
// On that page itself the send_to it was reached with is passed on instead:
// a sign-in page never sends the visitor back to the sign-in page.
function _pg_login_region_return_link($url, $page_url)
{
    if ($url === '') return '';
    $target = explode('?', $url, 2);
    $here   = explode('?', (string)$page_url, 2);
    $send   = (rtrim($target[0], '/') === rtrim($here[0], '/')) ? _pg_member_query_path('send_to') : (string)$page_url;
    if ($send === '') return $url;
    return $url . (strpos($url, '?') === false ? '?' : '&') . 'send_to=' . urlencode($send);
}

// Whether the signed-in person has work in the control panel: the test
// send_user_to_login_home() makes before it opens the panel for them
// instead of the site. The rule has one copy, waf_panel_access_from_row().
function _pg_login_region_panel_access()
{
    return pg_user_has_panel_access();
}

// Render a 'login_region' system widget: what a site header shows about the
// session. The designer draws both states in one tree - the part for a
// visitor who is not signed in (sign-in and sign-up links, or a compact
// sign-in form) and the part for a signed-in member (their name, initials or
// picture, the links of their account) - and the visibility flags keep the
// one that applies. Being a widget, one row placed in the header of every
// page is edited once.
//
// Flags: is_signed_in, is_signed_out, has_badge, is_staff (the visitor may
// open the configured staff page), has_panel_access (has work in the
// control panel). Tokens: ^^__site_name^^, ^^__user_name^^,
// ^^__user_first_name^^, ^^__user_initials^^, ^^__user_email^^,
// ^^__user_avatar_url^^ (their picture, else the picture of their
// initials), ^^__user_badge^^, and the links ^^__login_url^^,
// ^^__register_url^^, ^^__my_account_url^^, ^^__profile_url^^,
// ^^__logout_url^^, ^^__staff_url^^, ^^__panel_url^^. A link with nowhere
// to go leaves with its node.
//
// A signed-out part holding controls named email and password is a compact
// sign-in form: it is wrapped in the <form> index.php reads, and a refused
// attempt goes on to the sign-in page, which prints the error.
//
// cfg: login_page_id, register_page_id, show_register_link (default on),
// account_page_id, staff_page_id. The sign-in, sign-up and account links
// fall back to a designed page carrying the matching widget, then to the
// legacy page type.
function _render_system_widget_login_region($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    static $placement = 0;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();
    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';

    // A page drawn for the search index or the SEO score is drawn inside
    // somebody else's request, and must not carry their name.
    $background = (function_exists('pg_seo_rendering') && pg_seo_rendering())
               || (defined('UPDATE_SEARCH_INDEX') && UPDATE_SEARCH_INDEX === true);
    $signed_in  = !$background && _pg_acct_signed_in() && defined('USER_ID') && (int)USER_ID > 0;
    $page_url   = (!$background && function_exists('get_request_uri')) ? (string)get_request_uri() : '';
    $software   = _pg_acct_software_path();
    $output     = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';

    $values = array(
        '__site_name'       => h(_pg_member_site_name()),
        '__user_name'       => '',
        '__user_first_name' => '',
        '__user_initials'   => '',
        '__user_email'      => '',
        '__user_avatar_url' => '',
        '__user_badge'      => '',
    );
    $links = array(
        '__login_url'      => '',
        '__register_url'   => '',
        '__my_account_url' => '',
        '__profile_url'    => '',
        '__logout_url'     => '',
        '__staff_url'      => '',
        '__panel_url'      => '',
    );
    $flags = array(
        'is_signed_in'     => $signed_in,
        'is_signed_out'    => !$signed_in,
        'has_badge'        => false,
        'is_staff'         => false,
        'has_panel_access' => false,
    );

    if ($signed_in) {
        $row = db_item(
            "SELECT user.user_username, user.user_email, user.user_badge, user.user_badge_label,
                    contacts.first_name, contacts.last_name, contacts.image,
                    contacts.file_id AS image_file_id, files.name AS image_file_name
             FROM user
             LEFT JOIN contacts ON contacts.id = user.user_contact
             LEFT JOIN files ON files.id = contacts.file_id
             WHERE user.user_id = '" . (int)USER_ID . "'
             LIMIT 1");
        if (!is_array($row)) $row = array();
        $username = isset($row['user_username']) ? (string)$row['user_username'] : (defined('USER_USERNAME') ? (string)USER_USERNAME : '');
        $first    = trim(isset($row['first_name']) ? (string)$row['first_name'] : '');
        $last     = trim(isset($row['last_name']) ? (string)$row['last_name'] : '');
        $full     = trim($first . ' ' . $last);

        // The picture: the member's own, else the letters of their name, the
        // way the panel draws people (includes/fn/contacts.php).
        $avatar = '';
        if (!empty($row['image_file_id']) && (string)$row['image_file_name'] !== '') {
            $avatar = $output . $row['image_file_name'];
        } elseif (!empty($row['image'])) {
            $image  = (string)$row['image'];
            $avatar = preg_match('#^(https?:)?/#i', $image) ? $image : $output . $image;
        } elseif (function_exists('pg_avatar_for')) {
            $avatar = pg_avatar_for($first, $last, $username, (string)USER_ID);
        }

        $badge = '';
        if (isset($row['user_badge']) && (int)$row['user_badge'] === 1) {
            $badge = (string)$row['user_badge_label'];
            if ($badge === '' && defined('BADGE_LABEL')) $badge = (string)BADGE_LABEL;
        }

        $values['__user_name']       = h($full !== '' ? $full : $username);
        $values['__user_first_name'] = h($first !== '' ? $first : ($full !== '' ? $full : $username));
        $values['__user_initials']   = h(function_exists('pg_initials') ? pg_initials($first, $last, $username) : mb_strtoupper(mb_substr($username, 0, 2)));
        $values['__user_email']      = h(isset($row['user_email']) ? (string)$row['user_email'] : '');
        $values['__user_avatar_url'] = h($avatar);
        $values['__user_badge']      = h($badge);
        $flags['has_badge']          = ($badge !== '');

        $links['__my_account_url'] = _pg_acct_page_url(isset($cfg['account_page_id']) ? $cfg['account_page_id'] : 0, 'my account');
        $links['__profile_url']    = _pg_acct_page_url(0, 'my account profile');

        // Signed out at once: the token is what lets logout.php skip its
        // question. Without a send_to it goes on to the site's logout page
        // ("you have logged out"); a site with none goes back home.
        $token = isset($_SESSION['software']['token']) ? (string)$_SESSION['software']['token'] : '';
        $has_logout_page = (pg_sw_page_type_widget_url('logout') !== '')
                        || ((int)db_value("SELECT COUNT(*) FROM page WHERE page_type = 'logout'") > 0);
        $links['__logout_url'] = $software . '/logout.php?token=' . urlencode($token)
                               . ($has_logout_page ? '' : '&send_to=' . urlencode(defined('PATH') ? PATH : '/'));

        $links['__staff_url'] = _pg_acct_staff_url(isset($cfg['staff_page_id']) ? $cfg['staff_page_id'] : 0);
        $flags['is_staff']    = ($links['__staff_url'] !== '');

        if (_pg_login_region_panel_access()) {
            $links['__panel_url']      = $software . '/welcome.php';
            $flags['has_panel_access'] = true;
        }
    } else {
        $login = _pg_acct_page_url(isset($cfg['login_page_id']) ? $cfg['login_page_id'] : 0, 'login');
        if ($login === '') $login = $software . '/index.php';
        $links['__login_url'] = _pg_login_region_return_link($login, $page_url);

        // Sign-up: the chosen page; otherwise, unless switched off, the site's
        // registration page (a designed one first).
        $show_register = !(isset($cfg['show_register_link'])
            && ($cfg['show_register_link'] === false || $cfg['show_register_link'] === 0 || $cfg['show_register_link'] === '0'));
        $register = '';
        if ($show_register) {
            if (!empty($cfg['register_page_id'])) $register = _pg_member_page_url($cfg['register_page_id']);
            if ($register === '') $register = _pg_acct_page_url(0, 'registration entrance');
        }
        $links['__register_url'] = _pg_login_region_return_link($register, $page_url);
    }

    _eo_apply_visibility_bindings($tree, $flags);
    _pg_member_drop_empty_links($tree, $links);

    // A compact sign-in form in the signed-out part.
    $controls = array();
    foreach (_pg_member_controls($tree) as $c) $controls[$c['name']] = true;
    $sign_in_form = !$signed_in && isset($controls['email']) && isset($controls['password']);
    if ($sign_in_form && !(defined('REMEMBER_ME') && REMEMBER_ME)) {
        _pg_member_drop_control($tree, 'remember_me');
    }

    pg_cf_link_labels($tree);
    // Stamped with a form name nothing posts to: a Messages block placed in
    // a header must not print the alerts that belong to the page's own form.
    _pg_inject_messages_node($tree, 'login_region');

    $split    = _split_widget_tree($tree);
    $rendered = str_replace('<!--pg-loop-slot-->', '', trim(_render_tree_node($split['static_tree'], 0, 0)));

    // The same region may sit in a page twice (a header and an offcanvas
    // menu): every id gets the placement's own suffix.
    $rendered = pg_sw_uniquify_row_ids($rendered, (int)$widget_id, ++$placement);

    if ($sign_in_form) {
        // display:contents - the form must not become a box of its own
        // between a navbar and its items.
        $rendered = '<form action="' . h($software . '/index.php') . '" method="post" class="pg-login-region-form" style="display:contents">'
                  . get_token_field()
                  . '<input type="hidden" name="send_to" value="' . h($page_url) . '">'
                  . '<input type="hidden" name="return_to" value="' . h($links['__login_url']) . '">'
                  . '<input type="hidden" name="require_cookies" value="true">'
                  . $rendered . '</form>';
    }

    $tokens = $values;
    foreach ($links as $k => $v) $tokens[$k] = h($v);
    // The member's own allow-listed account values: read only when the
    // tree binds one, as the region sits on every page.
    if ($signed_in) $tokens += pg_sw_entity_account_tokens((int)USER_ID, $tree_json);
    return _pg_member_apply_tokens($rendered, $tokens);
}

// ============================================================================
// account_email: the designed welcome and password reset e-mails
// ============================================================================
//
// The registration and membership widgets name a page as their welcome
// e-mail (welcome_email_page_id, welcome_email_subject), the forgot password
// widget one as its reset e-mail (reset_email_page_id, reset_email_subject).
// The sender draws that page with get_page_content() in e-mail mode and the
// render context key 'account_email' => array('kind' => 'welcome' |
// 'password_reset', 'user_id' => N, 'set_password_url' => the reset link);
// the Account E-mail widget on the page reads the member from there. No
// page chosen: no welcome e-mail, and the built-in plain-text reset e-mail.

// Whether the address typed into the forgot password form is the account's
// stored address, letter case aside (pg_email_address_matches()): the
// lookup folds accents, and the reset link must never go to the look-alike
// domain "exámple.com" for the account of "example.com".
function pg_password_reset_address_matches($typed, $stored)
{
    return pg_email_address_matches($typed, $stored);
}

// The widget setting keys of an account e-mail: 'welcome' or 'reset' =>
// array(page key, subject key).
function pg_account_email_keys($kind)
{
    return ((string)$kind === 'reset')
        ? array('reset_email_page_id', 'reset_email_subject')
        : array('welcome_email_page_id', 'welcome_email_subject');
}

// The e-mail page a widget config names: a page id, or 0 for none and for a
// tab of an unpublished design ('tab:<key>').
function _pg_account_email_page_ref($cfg, $key)
{
    if (!is_array($cfg) || !isset($cfg[$key]) || !is_scalar($cfg[$key])) return 0;
    $v = (string)$cfg[$key];
    return ctype_digit($v) ? (int)$v : 0;
}

// Which widget's setting answers: the widget the form was drawn by
// ($posted: null, or array('id', 'cfg') of a widget of the right type)
// decides, even when it names no page; otherwise the first of $candidates
// (array of array('id', 'cfg')) that names a page. $kind is 'welcome' or
// 'reset'. Returns array('widget_id', 'page_id', 'subject').
function pg_account_email_pick($posted, $candidates, $kind)
{
    list($page_key, $subject_key) = pg_account_email_keys($kind);
    $chosen = null;
    if (is_array($posted) && isset($posted['cfg'])) {
        $chosen = $posted;
    } else {
        foreach ((array)$candidates as $c) {
            if (is_array($c) && isset($c['cfg']) && _pg_account_email_page_ref($c['cfg'], $page_key) > 0) {
                $chosen = $c;
                break;
            }
        }
    }
    if ($chosen === null) return array('widget_id' => 0, 'page_id' => 0, 'subject' => '');
    $cfg = is_array($chosen['cfg']) ? $chosen['cfg'] : array();
    return array(
        'widget_id' => isset($chosen['id']) ? (int)$chosen['id'] : 0,
        'page_id'   => _pg_account_email_page_ref($cfg, $page_key),
        'subject'   => (isset($cfg[$subject_key]) && is_scalar($cfg[$subject_key])) ? trim((string)$cfg[$subject_key]) : '',
    );
}

// Whether a page row (page_id, page_folder) can be sent: it exists, is not
// in the recycle bin and is not a draft.
function pg_account_email_page_usable($row, $binned_ids, $draft_folder_id)
{
    if (!is_array($row) || empty($row['page_id']) || (int)$row['page_id'] <= 0) return false;
    $folder = isset($row['page_folder']) ? (int)$row['page_folder'] : 0;
    if (in_array($folder, array_map('intval', (array)$binned_ids), true)) return false;
    if ((int)$draft_folder_id > 0 && $folder === (int)$draft_folder_id) return false;
    return true;
}

// The account e-mail setting that applies to a sign-up or reset made through
// a widget of $type ('registration', 'membership', 'forgot_password'):
// the posted widget's own, else the first widget of that type on a published
// page that names a page. A widget placed only inside a shared component is
// not found. Returns array('widget_id', 'page_id' (0 when the page cannot be
// sent), 'subject', 'chosen' (the page id the setting names)).
function pg_account_email_setting($type, $kind, $posted_widget_id)
{
    $out = array('widget_id' => 0, 'page_id' => 0, 'subject' => '', 'chosen' => 0);
    // page_tree_json arrived with the multi-page editor; before that upgrade
    // no designed page can carry a widget.
    if (function_exists('pg_multi_page_design_ready') && !pg_multi_page_design_ready()) return $out;

    $type   = (string)$type;
    $posted = null;
    $posted_widget_id = (int)$posted_widget_id;
    if ($posted_widget_id > 0) {
        $row = db_item("SELECT system_region_config FROM shared_components WHERE id = '" . $posted_widget_id . "' LIMIT 1");
        $cfg = is_array($row) ? json_decode((string)$row['system_region_config'], true) : null;
        if (is_array($cfg) && isset($cfg['regionType']) && $cfg['regionType'] === $type) {
            $posted = array('id' => $posted_widget_id, 'cfg' => $cfg);
        }
    }

    // Only widgets that name a page are looked for: a site that names none
    // pays one shared_components query, and no page tree is scanned.
    $candidates = array();
    if ($posted === null) {
        list($page_key) = pg_account_email_keys($kind);
        $names_page = function ($cfg) use ($page_key) {
            return _pg_account_email_page_ref($cfg, $page_key) > 0;
        };
        foreach (pg_sw_widget_pages($type, $names_page) as $p) {
            $folder = db_value("SELECT page_folder FROM page WHERE page_id = '" . (int)$p['page_id'] . "' LIMIT 1");
            if (pg_page_is_draft((int)$folder)) continue;
            $w = pg_sw_page_widget((int)$p['page_id'], $type, $names_page);
            if ($w !== null) {
                $candidates[] = $w;
                break;
            }
        }
    }

    $out = pg_account_email_pick($posted, $candidates, $kind);
    $out['chosen'] = $out['page_id'];
    if ($out['page_id'] > 0) {
        $page = db_item("SELECT page_id, page_folder FROM page WHERE page_id = '" . (int)$out['page_id'] . "' LIMIT 1");
        if (!pg_account_email_page_usable($page, pg_recycle_bin_folder_ids(), pg_page_draft_folder_id(false))) {
            $out['page_id'] = 0;
        }
    }
    return $out;
}

// The subject line: the configured text (already translated) without
// ^^tokens^^ and line breaks, else $fallback. Plain text: nothing is filled
// into it.
function pg_account_email_subject($configured, $fallback)
{
    $s = is_scalar($configured) ? (string)$configured : '';
    $s = preg_replace('/\^\^[^\^]*\^\^(%%.*?%%)?/', '', $s);
    $s = trim(preg_replace('/\s*[\r\n]+\s*/', ' ', $s));
    return ($s !== '') ? $s : (string)$fallback;
}

// The Account E-mail widget's tokens (raw, unescaped) and flags.
// $kind: 'welcome' or 'password_reset' in an e-mail render, 'preview' for
// the editor's preview (sample values, both parts shown); anything else
// leaves every token empty, so no account data and no link is drawn.
// $member: user_username, user_email, first_name, last_name. $links: the
// absolute '__login_url' and '__my_account_url'. $set_password_url is used
// for a reset (and the preview) only.
function pg_account_email_values($kind, $member, $links, $set_password_url, $site_name = '')
{
    $tokens = array(
        '__site_name'        => '',
        '__user_name'        => '',
        '__user_first_name'  => '',
        '__user_email'       => '',
        '__username'         => '',
        '__login_url'        => '',
        '__my_account_url'   => '',
        '__set_password_url' => '',
    );
    $flags = array('is_welcome' => false, 'is_password_reset' => false);
    $kind  = (string)$kind;
    if ($kind !== 'welcome' && $kind !== 'password_reset' && $kind !== 'preview') {
        return array('tokens' => $tokens, 'flags' => $flags);
    }

    if (!is_array($member)) $member = array();
    if (!is_array($links)) $links = array();
    $username = _pg_account_email_field($member, 'user_username');
    $first    = _pg_account_email_field($member, 'first_name');
    $full     = trim($first . ' ' . _pg_account_email_field($member, 'last_name'));

    $tokens['__site_name']       = (string)$site_name;
    $tokens['__user_name']       = ($full !== '') ? $full : $username;
    $tokens['__user_first_name'] = ($first !== '') ? $first : (($full !== '') ? $full : $username);
    $tokens['__user_email']      = _pg_account_email_field($member, 'user_email');
    $tokens['__username']        = $username;
    $tokens['__login_url']       = isset($links['__login_url']) ? (string)$links['__login_url'] : '';
    $tokens['__my_account_url']  = isset($links['__my_account_url']) ? (string)$links['__my_account_url'] : '';
    if ($kind !== 'welcome') $tokens['__set_password_url'] = (string)$set_password_url;

    $flags['is_welcome']        = ($kind !== 'password_reset');
    $flags['is_password_reset'] = ($kind !== 'welcome');
    return array('tokens' => $tokens, 'flags' => $flags);
}

// One trimmed text column of a member row, '' when absent.
function _pg_account_email_field($member, $key)
{
    return (isset($member[$key]) && is_scalar($member[$key])) ? trim((string)$member[$key]) : '';
}

// Whether an e-mail body carries the reset link of $token (k=<token> as a
// query parameter, wherever the language prefix put the path).
function pg_account_email_has_link($body, $token)
{
    $token = is_scalar($token) ? (string)$token : '';
    if ($token === '' || !is_string($body) || $body === '') return false;
    return (bool)preg_match('/[?&;]k=' . preg_quote($token, '/') . '(?![A-Za-z0-9])/', $body);
}

// The e-mail page drawn for the member, in the visitor's language.
function pg_account_email_body($page_id, $ctx)
{
    require_once(PG_FUNCTIONS_DIR . '/get_page_content.php');
    $body = get_page_content((int)$page_id, '', '', 'preview', true, array('account_email' => $ctx));
    return function_exists('pg_tr_email') ? pg_tr_email($body) : $body;
}

// The welcome e-mail after a sign-up through a widget of $type
// ('registration', 'membership'), queued to the account's stored address.
// No page chosen: nothing is sent. $widget_id is the widget the form was
// drawn by, 0 for the legacy screens and the social sign-up.
function pg_account_email_send_welcome($user_id, $type, $widget_id)
{
    $user_id = (int)$user_id;
    if ($user_id <= 0) return false;
    $set = pg_account_email_setting($type, 'welcome', $widget_id);
    if ($set['page_id'] <= 0) {
        if ($set['chosen'] > 0) {
            log_activity(lang('The welcome e-mail was not sent because its page is no longer available.'));
        }
        return false;
    }
    $to = (string)db_value("SELECT user_email FROM user WHERE user_id = '" . $user_id . "' LIMIT 1");
    if ($to === '') return false;

    $body = pg_account_email_body($set['page_id'], array('kind' => 'welcome', 'user_id' => $user_id));
    $subject = pg_account_email_subject(
        function_exists('pg_tr_text') ? pg_tr_text($set['subject']) : $set['subject'],
        lang(array('string' => 'Welcome to {var:1}', 'vars' => ORGANIZATION_NAME)));

    return email(array(
        'to'                 => $to,
        'from_name'          => ORGANIZATION_NAME,
        'from_email_address' => EMAIL_ADDRESS,
        'subject'            => $subject,
        'format'             => 'html',
        'body'               => $body,
        'queue'              => true));
}

// The unknown-address answer of a forgot password request draws the reset
// page too, with no member (every token empty) and throws it away: the page
// render is the slow part of the known-address answer, and a time that
// differs with whether an account exists tells which addresses have one.
function pg_account_email_reset_unsent($set)
{
    if (!is_array($set) || empty($set['page_id']) || (int)$set['page_id'] <= 0) return;
    pg_account_email_body((int)$set['page_id'], array('kind' => ''));
}

// The designed password reset e-mail. $set is the request's forgot_password
// pg_account_email_setting(), $to the account's stored address, $link the
// reset link with $token in it. True once the designed page was sent (even
// when the mail server refused it, so the member never gets two e-mails);
// false when the caller sends the built-in e-mail: no page chosen, the page
// cannot be sent, or its body does not carry the link.
function pg_account_email_send_reset($user_id, $to, $link, $token, $set)
{
    if (!is_array($set) || empty($set['page_id']) || (int)$set['page_id'] <= 0) return false;

    $body = pg_account_email_body($set['page_id'], array(
        'kind'             => 'password_reset',
        'user_id'          => (int)$user_id,
        'set_password_url' => (string)$link,
    ));
    if (!pg_account_email_has_link($body, $token)) {
        log_activity(lang('The password reset e-mail page shows no set password link, so the built-in e-mail was sent.'));
        return false;
    }

    email(array(
        'to'                 => (string)$to,
        'from_name'          => ORGANIZATION_NAME,
        'from_email_address' => EMAIL_ADDRESS,
        'subject'            => pg_account_email_subject(
            function_exists('pg_tr_text') ? pg_tr_text($set['subject']) : $set['subject'],
            lang('Reset Password')),
        'format'             => 'html',
        'body'               => $body,
        'queue'              => true,
        // The body holds a live reset link: kept out of the failure log
        // and the failure notice.
        'secret'             => true));
    return true;
}

// An absolute address on the configured host for an account page path,
// '' when there is none. Links in an e-mail leave the request, so the
// request's own host is never used.
function _pg_account_email_absolute($path)
{
    $path = (string)$path;
    if ($path === '') return '';
    return URL_SCHEME . HOSTNAME_SETTING . $path;
}

// Render an 'account_email' system widget: the member part of a welcome or
// password reset e-mail page.
//
// Flags: is_welcome, is_password_reset. Tokens: ^^__site_name^^,
// ^^__user_name^^, ^^__user_first_name^^, ^^__user_email^^, ^^__username^^,
// and the links ^^__login_url^^, ^^__my_account_url^^,
// ^^__set_password_url^^ (the reset link, reset only). A link with nowhere
// to go leaves with its node.
//
// Filled only in an e-mail render the sender told about the member (the
// render context's 'account_email'). Anywhere else - a visitor or staff
// opening the page, the search index, the SEO pass - every token is empty
// and every bound link drops; the editor's preview shows sample values.
function _render_system_widget_account_email($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    if ($tree_json === '' || $tree_json === null) return '';
    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';

    $ctx  = pg_sw_render_context();
    $acct = (!empty($ctx['email']) && isset($ctx['account_email']) && is_array($ctx['account_email'])) ? $ctx['account_email'] : null;

    $kind   = '';
    $member = array();
    $links  = array();
    $reset  = '';
    $user_id = 0;
    if ($acct !== null) {
        $kind = isset($acct['kind']) ? (string)$acct['kind'] : '';
        $user_id = isset($acct['user_id']) ? (int)$acct['user_id'] : 0;
        $row = ($user_id > 0) ? db_item(
            "SELECT user.user_username, user.user_email, contacts.first_name, contacts.last_name
             FROM user
             LEFT JOIN contacts ON contacts.id = user.user_contact
             WHERE user.user_id = '" . $user_id . "'
             LIMIT 1") : null;
        // The links are looked up even with no member (the unknown-address
        // answer of a reset draws the page that way): finding the account
        // pages is the slow part of the render, and the two answers of a
        // reset request take the same time only when both do it.
        $links  = array(
            '__login_url'      => _pg_account_email_absolute(_pg_acct_page_url(0, 'login')),
            '__my_account_url' => _pg_account_email_absolute(_pg_acct_page_url(0, 'my account')),
        );
        if (is_array($row)) {
            $member = $row;
            if (isset($acct['set_password_url']) && is_scalar($acct['set_password_url'])) $reset = (string)$acct['set_password_url'];
        } else {
            $kind = '';
        }
    } elseif (function_exists('pg_sw_preview_overrides') && pg_sw_preview_overrides()) {
        $kind   = 'preview';
        $member = array('user_username' => 'jane.cooper', 'user_email' => 'jane.cooper@example.com', 'first_name' => lang('Jane'));
        $links  = array('__login_url' => '#', '__my_account_url' => '#');
        $reset  = '#';
    }

    $values = pg_account_email_values($kind, $member, $links, $reset, _pg_member_site_name());
    if ($kind === 'preview') $values['tokens']['__user_name'] = lang('Jane Cooper');

    _eo_apply_visibility_bindings($tree, array(
        'is_welcome'        => $values['flags']['is_welcome'],
        'is_password_reset' => $values['flags']['is_password_reset'],
    ));
    _pg_member_drop_empty_links($tree, $values['tokens']);
    pg_cf_link_labels($tree);
    // Stamped with a name nothing posts to: an e-mail shows no alerts, and a
    // visitor opening the page must not see another form's.
    _pg_inject_messages_node($tree, 'account_email');

    $split    = _split_widget_tree($tree);
    $rendered = str_replace('<!--pg-loop-slot-->', '', trim(_render_tree_node($split['static_tree'], 0, 0)));
    // The addressee's allow-listed account values; none in the unknown-
    // address answer of a reset or the preview.
    $account = ($kind !== '' && $kind !== 'preview') ? pg_sw_entity_account_tokens($user_id, $tree_json) : array();
    return _pg_member_apply_tokens($rendered, pg_sw_escape_token_values($values['tokens']) + $account);
}
