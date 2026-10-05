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
// password, set password, profile, email preferences and address book - the
// designed pages that stand in for the legacy page types, and the login
// region a site header carries.
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
    if (!$pages) return '';
    return $cache[$page_type] = (defined('PATH') ? PATH : '/') . encode_url_path((string)$pages[0]['page_name']);
}

// Where a visitor who has to sign in before a page opens is sent: the
// site's designed sign-in page (one carrying the login widget), with the
// page as send_to. A site that keeps a legacy page of $legacy_type ('login',
// 'registration entrance') is served by the legacy screen, which shows that
// page - unless the page asked for is itself a visual design page
// ($designed_first): its visitor is on the designed site, and the sample
// content every installation starts with carries the legacy pages. A site
// with no designed sign-in page gets the legacy screen too. $fallback is the
// legacy screen's own address.
function pg_sw_sign_in_url($send_to, $fallback, $legacy_type = 'login', $designed_first = false)
{
    if (function_exists('pg_multi_page_design_ready') && !pg_multi_page_design_ready()) return $fallback;
    if (!$designed_first && (int)db_value("SELECT COUNT(*) FROM page WHERE page_type = '" . e($legacy_type) . "'") > 0) return $fallback;
    $name = _pg_sw_open_widget_page_name('login_form');
    if ($name === '') return $fallback;
    $url = (defined('PATH') ? PATH : '/') . encode_url_path($name);
    return ((string)$send_to === '') ? $url : $url . '?send_to=' . urlencode((string)$send_to);
}

// Name of the first designed page carrying a widget of $type that the
// visitor may open, or ''. A page in a folder that itself asks for a sign-in
// does not count: sending a signed-out visitor there to sign in would send
// them round in a circle.
function _pg_sw_open_widget_page_name($type)
{
    foreach (pg_sw_widget_pages($type) as $p) {
        $folder = db_value("SELECT page_folder FROM page WHERE page_id = '" . (int)$p['page_id'] . "' LIMIT 1");
        if ($folder === null || $folder === '' || !check_view_access($folder)) continue;
        return (string)$p['page_name'];
    }
    return '';
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
    foreach ((array)db_items("SELECT name, code FROM countries ORDER BY name") as $c) {
        $out[] = array((string)$c['code'], (string)$c['name']);
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
    $label = array_search($site, $zones);
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
            $values[$f] = is_array($user) ? (string)$user['timezone'] : '';
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
    ));
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
// Controls by name: email_address, opt_in. loop_area: one row per mailing
// list the contact may see (open lists, and closed ones the contact is on);
// the row's checkbox is named contact_group_^^__group_id^^ and is checked by
// the server. Row tokens: ^^__group_id^^, ^^__group_name^^,
// ^^__group_description^^.
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

    $lf = new liveform('email_preferences');
    $submitted = $lf->field_in_session('token');
    if ($submitted) {
        _pg_member_fill_control($tree, 'email_address', (string)$lf->get_field_value('email_address'));
        _pg_member_fill_control($tree, 'opt_in', '1', (bool)$lf->get_field_value('opt_in'));
    } else {
        _pg_member_fill_control($tree, 'email_address', $email);
        _pg_member_fill_control($tree, 'opt_in', '1', $opt_in === 1);
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
            _pg_member_fill_control($t, 'contact_group_^^__group_id^^', '1', $checked);
            if (!$has_box) {
                foreach (_pg_member_controls($t) as $c) {
                    if ($c['name'] === 'contact_group_^^__group_id^^') { $has_box = true; break; }
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
            $country = isset($countries[(string)$r['country']]) ? $countries[(string)$r['country']] : (string)$r['country'];
            $lines = array_values(array_filter(array(
                (string)$r['address_1'],
                (string)$r['address_2'],
                trim(implode(' ', array_filter(array((string)$r['zip_code'], (string)$r['city'],
                    ((string)$r['state'] !== (string)$r['city']) ? (string)$r['state'] : ''), 'strlen'))),
                $country,
            ), 'strlen'));
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
            ));
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
// ^^__logout_url^^. Section: order_history (the member's orders, newest
// first, each linking to the order view page). Flags: has_orders, no_orders.
function _pg_my_account_extras($logged_in)
{
    $shop  = defined('ECOMMERCE') && ECOMMERCE;
    $links = array(
        '__profile_url'           => '',
        '__email_preferences_url' => '',
        '__change_password_url'   => '',
        '__address_book_url'      => '',
        '__logout_url'            => '',
    );
    $out = array('links' => $links, 'sections' => array('order_history' => ''),
                 'flags' => array('has_orders' => false, 'no_orders' => true));
    if (!$logged_in) return $out;

    $links['__profile_url']           = _pg_acct_page_url(0, 'my account profile');
    $links['__email_preferences_url'] = _pg_acct_page_url(0, 'email preferences');
    $links['__change_password_url']   = _pg_acct_page_url(0, 'change password');
    if ($shop) $links['__address_book_url'] = _pg_acct_page_url(0, 'update address book');
    // A designed logout page asks first; without one the token signs out at once.
    $logout = pg_sw_page_type_widget_url('logout');
    $links['__logout_url'] = ($logout !== '') ? $logout
        : _pg_acct_software_path() . '/logout.php?send_to=' . urlencode(defined('PATH') ? PATH : '/')
          . '&token=' . urlencode(isset($_SESSION['software']['token']) ? (string)$_SESSION['software']['token'] : '');
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
            $out['flags'] = array('has_orders' => true, 'no_orders' => false);
        }
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
// instead of the site.
function _pg_login_region_panel_access()
{
    if (!defined('USER_ID') || (int)USER_ID <= 0) return false;
    if (defined('USER_ROLE') && (int)USER_ROLE < 3) return true;
    $u = db_item("SELECT * FROM user WHERE user_id = '" . (int)USER_ID . "' LIMIT 1");
    if (!is_array($u)) return false;
    foreach (array('user_manage_contacts', 'user_manage_visitors', 'user_manage_ecommerce',
                   'user_manage_forms', 'user_manage_calendars', 'user_manage_emails') as $k) {
        if (isset($u[$k]) && (string)$u[$k] === 'yes') return true;
    }
    foreach (array('manage_ecommerce_reports', 'manage_erp', 'manage_workspace') as $k) {
        if (!empty($u[$k])) return true;
    }
    return function_exists('no_acl_check') && no_acl_check((int)USER_ID);
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

        // The staff link only for those the staff page lets in - the folder
        // access decides, the same rule get_page.php applies on arrival.
        $staff_page_id = isset($cfg['staff_page_id']) ? (int)$cfg['staff_page_id'] : 0;
        if ($staff_page_id > 0) {
            $staff = db_item("SELECT page_name, page_folder FROM page WHERE page_id = '" . $staff_page_id . "' LIMIT 1");
            $binned = (is_array($staff) && function_exists('pg_recycle_bin_folder_ids'))
                ? in_array((int)$staff['page_folder'], array_map('intval', (array)pg_recycle_bin_folder_ids()), true) : false;
            if (is_array($staff) && (string)$staff['page_name'] !== '' && !$binned && check_view_access($staff['page_folder'])) {
                $links['__staff_url'] = $output . encode_url_path((string)$staff['page_name']);
                $flags['is_staff']    = true;
            }
        }

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
    return _pg_member_apply_tokens($rendered, $tokens);
}
