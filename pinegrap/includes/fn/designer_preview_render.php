<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the Preview drawn with a chosen record, visitor and
 * case (designer_preview_as.php holds the pure case model). This module is
 * the server side: which record a single-record widget draws for a case,
 * the record search behind the editor's record chooser with the operator's
 * rights, and the scope one widget is drawn in. The scope keeps the render
 * a background one: nothing it draws may write the session, a counter, a
 * cache copy, an order, a mail or a log line of a visit.
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
 * The setup of the widget being drawn now (pg_preview_as_setup()), for a
 * renderer that draws a case: empty outside a Preview widget.
 *
 * @param array|null $set a setup to hold, array() to clear, null to read
 * @return array
 */
function pg_preview_as_current($set = null)
{
    static $current = array();
    if ($set !== null) $current = is_array($set) ? $set : array();
    return $current;
}

/**
 * Whether the widget being drawn now is drawn with flag $flag ('empty',
 * 'long', 'sold_out', 'form_error').
 *
 * @param string $flag
 * @return bool
 */
function pg_preview_as_flag($flag)
{
    $current = pg_preview_as_current();
    return !empty($current['flags']) && is_array($current['flags']) && in_array($flag, $current['flags'], true);
}

/**
 * Draw one widget of the Preview: $fn runs as a background render
 * (pg_seo_rendering(), pg_error_throws()) with $setup held for its
 * renderers. Whatever it does to the session is undone: the session is put
 * back as it was before, so a renderer that reads and clears a message or
 * stores a visit leaves no trace. Everything is restored when $fn throws.
 *
 * @param array    $setup pg_preview_as_setup()
 * @param callable $fn
 * @return mixed what $fn returns
 */
function pg_preview_as_while($setup, $fn)
{
    $saved_seo     = pg_seo_rendering();
    $saved_throw   = pg_error_throws();
    $saved_current = pg_preview_as_current();
    $had_session   = isset($_SESSION) && is_array($_SESSION);
    $saved_session = $had_session ? $_SESSION : null;
    pg_seo_rendering(true);
    pg_error_throws(true);
    pg_preview_as_current(is_array($setup) ? $setup : array());
    try {
        return call_user_func($fn);
    } finally {
        if ($had_session) {
            $_SESSION = $saved_session;
        } elseif (isset($_SESSION)) {
            $_SESSION = array();
        }
        pg_preview_as_current($saved_current);
        pg_seo_rendering($saved_seo);
        pg_error_throws($saved_throw);
    }
}

/**
 * Whether the render may write the visitor's order and the page's own
 * rows: on a real visit yes; in a background render (a Preview widget, the
 * SEO pass) no. In a Preview the session's order is the operator's own
 * cart and the widget settings are the editor's unsaved ones, so the cart
 * widgets read the order as it stands and write nothing.
 *
 * @return bool
 */
function pg_preview_as_may_write()
{
    return !(function_exists('pg_seo_rendering') && pg_seo_rendering());
}

/**
 * A yes of a user row: validate_user() gives booleans, a raw row 'yes'.
 *
 * @param mixed $value
 * @return bool
 */
function _pg_preview_as_yes($value)
{
    return $value === true || $value === 'yes' || $value === 1 || $value === '1';
}

/**
 * What the operator may open of a record source on its own panel screen:
 * 'all'; 'own' (forms: the form's folder must be theirs to edit; events:
 * only the calendars given to them); 'none'. A user who may not publish
 * events opens no published one (edit_calendar_event.php), and the Preview
 * draws only published events. Pure.
 *
 * @param string $source pg_preview_as_record_source()
 * @param mixed  $user   validate_user()
 * @return string
 */
function pg_preview_as_source_right($source, $user)
{
    if (!is_array($user) || !isset($user['role'])) return 'none';
    $role = (int)$user['role'];
    if ($source === 'form') {
        if ($role <= 2) return 'all';
        return _pg_preview_as_yes(isset($user['manage_forms']) ? $user['manage_forms'] : false) ? 'own' : 'none';
    }
    if ($source === 'product') {
        return ($role < 3 || _pg_preview_as_yes(isset($user['manage_ecommerce']) ? $user['manage_ecommerce'] : false)) ? 'all' : 'none';
    }
    if ($source === 'event') {
        if ($role < 3) return 'all';
        $manage  = _pg_preview_as_yes(isset($user['manage_calendars']) ? $user['manage_calendars'] : false);
        $publish = _pg_preview_as_yes(isset($user['publish_calendar_events']) ? $user['publish_calendar_events'] : false);
        return ($manage && $publish) ? 'own' : 'none';
    }
    return 'none';
}

/**
 * The record chooser's search text: trimmed, one line, at most 60
 * characters; '' lists the newest records. Pure.
 *
 * @param mixed $q
 * @return string
 */
function pg_preview_as_search_term($q)
{
    if (!is_string($q)) return '';
    $q = trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $q));
    if ($q === '' || !mb_check_encoding($q, 'UTF-8')) return '';
    return mb_substr($q, 0, 60, 'UTF-8');
}

/**
 * A LIKE pattern that finds $term anywhere, its own % and _ matched as
 * themselves; escaped for a quoted SQL literal by the caller. Pure.
 *
 * @param string $term
 * @return string
 */
function pg_preview_as_like($term)
{
    return '%' . addcslashes((string)$term, '\\%_') . '%';
}

/**
 * The statement that finds the record(s) of a source for one pick. Pure:
 * every value it puts in is a whole number or comes in escaped. Only ever
 * a SELECT.
 *
 * $args: form_page_id, join, where (pg_sw_record_filter_clause()),
 *   group (product group, 0 = every product), calendar_ids (int[], the
 *   calendars the record may be in), id (pick 'chosen'), like (an escaped
 *   pg_preview_as_like(), pick 'search'), limit (pick 'search'), now
 *   ('Y-m-d H:i:s', pick 'default' of an event).
 * Picks: 'default', 'chosen', 'longest', 'sold_out' (products), 'search'.
 * '' when the pick cannot be asked (no form, no calendar, a pick the source
 * does not have).
 *
 * @param string $source 'form' | 'product' | 'event'
 * @param string $pick
 * @param array  $args
 * @return string
 */
function pg_preview_as_record_sql($source, $pick, $args)
{
    $args  = is_array($args) ? $args : array();
    $id    = isset($args['id']) ? max(0, (int)$args['id']) : 0;
    $limit = isset($args['limit']) ? max(1, min(50, (int)$args['limit'])) : 20;
    $like  = isset($args['like']) ? (string)$args['like'] : '%%';
    if ($pick === 'chosen' && $id <= 0) return '';

    if ($source === 'form') {
        $pid = isset($args['form_page_id']) ? (int)$args['form_page_id'] : 0;
        if ($pid <= 0) return '';
        $sql = "SELECT DISTINCT forms.id, forms.reference_code FROM forms "
             . (isset($args['join']) ? (string)$args['join'] : '')
             . " WHERE forms.page_id = '" . $pid . "' "
             . (isset($args['where']) ? (string)$args['where'] : '');
        if ($pick === 'default') return $sql . " ORDER BY forms.id DESC LIMIT 1";
        if ($pick === 'chosen') return $sql . " AND forms.id = '" . $id . "' LIMIT 1";
        if ($pick === 'longest') {
            return $sql . " ORDER BY (SELECT COALESCE(SUM(CHAR_LENGTH(form_data.data)), 0) FROM form_data WHERE form_data.form_id = forms.id) DESC, forms.id DESC LIMIT 1";
        }
        if ($pick === 'search') {
            return $sql . " AND (forms.reference_code LIKE '" . $like . "'"
                 . " OR EXISTS (SELECT 1 FROM form_data WHERE form_data.form_id = forms.id AND form_data.data LIKE '" . $like . "'))"
                 . " ORDER BY forms.id DESC LIMIT " . $limit;
        }
        return '';
    }

    if ($source === 'product') {
        $group = isset($args['group']) ? (int)$args['group'] : 0;
        $sql = "SELECT DISTINCT products.id, products.name, products.address_name FROM products"
             . ($group > 0 ? " INNER JOIN products_groups_xref ON products_groups_xref.product = products.id" : '')
             . " WHERE products.enabled = '1' AND products.address_name != ''"
             . ($group > 0 ? " AND products_groups_xref.product_group = '" . $group . "'" : '');
        if ($pick === 'default') return $sql . " ORDER BY products.id ASC LIMIT 1";
        if ($pick === 'chosen') return $sql . " AND products.id = '" . $id . "' LIMIT 1";
        if ($pick === 'longest') {
            return $sql . " ORDER BY CHAR_LENGTH(COALESCE(products.name, '')) + CHAR_LENGTH(products.full_description) DESC, products.id ASC LIMIT 1";
        }
        if ($pick === 'sold_out') {
            return $sql . " AND products.inventory = '1' AND products.inventory_quantity = '0' AND products.backorder = '0' ORDER BY products.id ASC LIMIT 1";
        }
        if ($pick === 'search') {
            return $sql . " AND (products.name LIKE '" . $like . "' OR products.short_description LIKE '" . $like . "')"
                 . " ORDER BY products.name ASC LIMIT " . $limit;
        }
        return '';
    }

    if ($source === 'event') {
        $ids = array();
        foreach ((isset($args['calendar_ids']) && is_array($args['calendar_ids'])) ? $args['calendar_ids'] : array() as $cid) {
            if ((int)$cid > 0) $ids[(int)$cid] = (int)$cid;
        }
        if (!$ids) return '';
        $sql = "SELECT DISTINCT calendar_events.id, calendar_events.name, calendar_events.start_date_and_time FROM calendar_events"
             . " INNER JOIN calendar_events_calendars_xref ON calendar_events_calendars_xref.calendar_event_id = calendar_events.id"
             . " WHERE calendar_events.published = '1'"
             . " AND calendar_events_calendars_xref.calendar_id IN (" . implode(',', $ids) . ")";
        if ($pick === 'default') {
            $now = (isset($args['now']) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string)$args['now']))
                ? (string)$args['now'] : date('Y-m-d H:i:s');
            // The next event to come; when none is to come, the latest.
            return $sql . " ORDER BY (calendar_events.start_date_and_time >= '" . $now . "') DESC,"
                 . " IF(calendar_events.start_date_and_time >= '" . $now . "', calendar_events.start_date_and_time, NULL) ASC,"
                 . " calendar_events.start_date_and_time DESC LIMIT 1";
        }
        if ($pick === 'chosen') return $sql . " AND calendar_events.id = '" . $id . "' LIMIT 1";
        if ($pick === 'longest') {
            return $sql . " ORDER BY CHAR_LENGTH(calendar_events.name) + CHAR_LENGTH(calendar_events.full_description) DESC, calendar_events.id ASC LIMIT 1";
        }
        if ($pick === 'search') {
            return $sql . " AND calendar_events.name LIKE '" . $like . "'"
                 . " ORDER BY calendar_events.start_date_and_time DESC LIMIT " . $limit;
        }
        return '';
    }
    return '';
}

/**
 * The calendars an event of a calendar_event_view widget may come from for
 * this operator: the widget's calendars (every calendar when it names
 * none), cut to the operator's own with an 'own' right.
 *
 * @param array  $cfg
 * @param string $right pg_preview_as_source_right()
 * @param mixed  $user
 * @return int[]
 */
function _pg_preview_as_event_calendars($cfg, $right, $user)
{
    if ($right === 'none') return array();
    $ids = array();
    foreach (_pg_sw_calendars(is_array($cfg) ? $cfg : array()) as $c) $ids[] = (int)$c['id'];
    if ($right === 'own') {
        $uid  = (is_array($user) && isset($user['id'])) ? (int)$user['id'] : 0;
        $mine = array();
        foreach ((array)db_items("SELECT calendar_id FROM users_calendars_xref WHERE user_id = '" . $uid . "'") as $r) {
            $mine[] = (int)$r['calendar_id'];
        }
        $ids = array_values(array_intersect($ids, $mine));
    }
    return $ids;
}

/**
 * The arguments of pg_preview_as_record_sql() for a widget's source, by
 * what the operator may open; null when they may open none of it.
 *
 * @param string $source
 * @param array  $cfg     the widget's settings
 * @param int    $page_id the page it is drawn on
 * @param mixed  $user
 * @return array|null
 */
function _pg_preview_as_source_args($source, $cfg, $page_id, $user)
{
    $cfg   = is_array($cfg) ? $cfg : array();
    $right = pg_preview_as_source_right($source, $user);
    if ($right === 'none') return null;

    if ($source === 'form') {
        $pid = pg_sw_form_widget_form_page_id($cfg);
        if ($pid <= 0) return null;
        if ($right === 'own') {
            $folder = db_value("SELECT page_folder FROM page WHERE page_id = '" . $pid . "' LIMIT 1");
            if (!function_exists('check_edit_access') || check_edit_access($folder) !== true) return null;
        }
        $clause = pg_sw_record_filter_clause($pid, pg_sw_record_view_filters($cfg), (int)$page_id, true);
        return array('form_page_id' => $pid, 'join' => $clause['join'], 'where' => $clause['where']);
    }
    if ($source === 'product') {
        return array('group' => isset($cfg['product_group_id']) ? (int)$cfg['product_group_id'] : 0);
    }
    if ($source === 'event') {
        $ids = _pg_preview_as_event_calendars($cfg, $right, $user);
        return $ids ? array('calendar_ids' => $ids) : null;
    }
    return null;
}

/**
 * The record chooser's list: the records of a single-record widget's own
 * source that the operator may open on their panel screen, matching
 * $q, at most 20, as {id, label}. Nothing for a kind with no record or a
 * source the operator may not open.
 *
 * @param array  $cfg     the widget's settings (pg_sw_widget_kind() names the source)
 * @param int    $page_id the page the widget is on, 0 unsaved
 * @param mixed  $q       the search text
 * @param mixed  $user    validate_user()
 * @return array [{id, label}]
 */
function pg_preview_as_record_search($cfg, $page_id, $q, $user)
{
    $source = pg_preview_as_record_source(pg_sw_widget_kind($cfg));
    if ($source === '') return array();
    $args = _pg_preview_as_source_args($source, $cfg, (int)$page_id, $user);
    if ($args === null) return array();
    $args['like']  = e(pg_preview_as_like(pg_preview_as_search_term($q)));
    $args['limit'] = 20;
    $sql = pg_preview_as_record_sql($source, 'search', $args);
    if ($sql === '') return array();

    $rows = array();
    foreach ((array)db_items($sql) as $r) {
        $id = (int)$r['id'];
        if ($source === 'form') {
            $label = trim((string)get_submitted_form_title($id));
            if ($label === '') $label = (string)$r['reference_code'];
        } elseif ($source === 'product') {
            $label = (string)$r['name'];
        } else {
            $ts    = strtotime((string)$r['start_date_and_time']);
            $label = (string)$r['name'] . ($ts ? ' (' . date('Y-m-d', $ts) . ')' : '');
        }
        $rows[] = array('id' => $id, 'label' => mb_substr($label, 0, 120, 'UTF-8'));
    }
    return $rows;
}

/**
 * The request values a single-record widget is drawn with for its setup:
 * {get: query values, address: the product's address after the page name,
 * id: the record drawn (0 none)}. A pick other than the default needs the
 * operator's right to open the record; a chosen record they may not open,
 * or a long or sold-out one the source has none of, falls back to the
 * default pick. The default pick of a form or a product is the Preview's
 * own (the newest submission, the first product of the group).
 *
 * @param array $setup   pg_preview_as_setup()
 * @param array $cfg     the widget's settings
 * @param int   $page_id the page it is drawn on
 * @param mixed $user    validate_user(); null draws the default pick only
 * @return array
 */
function pg_preview_as_record_target($setup, $cfg, $page_id, $user)
{
    $out = array('get' => array(), 'address' => '', 'id' => 0);
    $record = (is_array($setup) && isset($setup['record']) && is_array($setup['record'])) ? $setup['record'] : null;
    if ($record === null) return $out;
    $source = (string)$record['source'];
    $pick   = (string)$record['pick'];

    $args = ($user !== null) ? _pg_preview_as_source_args($source, $cfg, (int)$page_id, $user) : null;
    $row  = null;
    if ($args !== null && $pick !== 'default') {
        $args['id'] = isset($record['id']) ? (int)$record['id'] : 0;
        $sql = pg_preview_as_record_sql($source, $pick, $args);
        if ($sql !== '') $row = db_item($sql);
    }
    if (!is_array($row)) {
        if ($source === 'event') {
            if ($args === null) return $out;
            $sql = pg_preview_as_record_sql('event', 'default', $args);
        } elseif ($source === 'form') {
            $pid = pg_sw_form_widget_form_page_id(is_array($cfg) ? $cfg : array());
            if ($pid <= 0) return $out;
            $clause = pg_sw_record_filter_clause($pid, pg_sw_record_view_filters($cfg), (int)$page_id, true);
            $sql = pg_preview_as_record_sql('form', 'default', array('form_page_id' => $pid, 'join' => $clause['join'], 'where' => $clause['where']));
        } else {
            $sql = pg_preview_as_record_sql('product', 'default', array('group' => isset($cfg['product_group_id']) ? (int)$cfg['product_group_id'] : 0));
        }
        $row = ($sql !== '') ? db_item($sql) : null;
    }
    if (!is_array($row)) return $out;

    $out['id'] = (int)$row['id'];
    if ($source === 'form' && (string)$row['reference_code'] !== '') {
        $out['get']['r'] = (string)$row['reference_code'];
    } elseif ($source === 'product') {
        $out['address'] = (string)$row['address_name'];
    } elseif ($source === 'event') {
        $out['get']['id'] = (string)(int)$row['id'];
    }
    return $out;
}

// ---------------------------------------------------------------------------
// What a renderer reads of the case it is drawn in. Outside a Preview widget
// no flag is held, so each of these gives back what the renderer had: a live
// visitor sees exactly what they saw before.
// ---------------------------------------------------------------------------

/**
 * The condition a list's statements add for the case "Empty": no record
 * matches, so the list draws its own "no record" state, count and pages
 * from nothing. '' outside that case.
 *
 * @return string
 */
function pg_preview_as_empty_where()
{
    return pg_preview_as_flag('empty') ? ' AND (TRUE = FALSE) ' : '';
}

/**
 * The ORDER BY head a list's statement puts first while the widget is
 * drawn with $flag: the rows whose $expr is greatest come first ("Long":
 * the most characters, "Sold out": the products that cannot be bought).
 * '' outside that case. $expr is the renderer's own SQL, never input.
 *
 * @param string $flag 'long' | 'sold_out'
 * @param string $expr an SQL expression over the list's tables
 * @return string "<expr> DESC, " or ''
 */
function pg_preview_as_order($flag, $expr)
{
    return ($expr !== '' && pg_preview_as_flag($flag)) ? '(' . $expr . ') DESC, ' : '';
}

/**
 * The characters of a row's text: its string values, or those named in
 * $keys. Pure.
 *
 * @param mixed    $row
 * @param string[] $keys empty: every string value
 * @return int
 */
function pg_preview_as_row_length($row, $keys = array())
{
    if (!is_array($row)) return 0;
    $len = 0;
    foreach ($keys ? $keys : array_keys($row) as $key) {
        if (!isset($row[$key]) || !is_string($row[$key])) continue;
        $len += function_exists('mb_strlen') ? mb_strlen(strip_tags($row[$key]), 'UTF-8') : strlen(strip_tags($row[$key]));
    }
    return $len;
}

/**
 * Rows a renderer has already read, as the case draws them: none for
 * "Empty"; for "Long" the rows with the most characters first (stable, so
 * equal rows keep their order); as they are otherwise. Pure but for the
 * held setup.
 *
 * @param array    $rows
 * @param string[] $keys the text keys measured for "Long" (empty: all)
 * @return array
 */
function pg_preview_as_rows($rows, $keys = array())
{
    if (!is_array($rows)) return $rows;
    if (pg_preview_as_flag('empty')) return array();
    if (!pg_preview_as_flag('long') || count($rows) < 2) return $rows;
    $order = array();
    $i = 0;
    foreach ($rows as $k => $row) {
        $order[] = array(pg_preview_as_row_length($row, $keys), $i++, $k);
    }
    usort($order, function ($a, $b) {
        if ($a[0] !== $b[0]) return ($a[0] > $b[0]) ? -1 : 1;
        return ($a[1] < $b[1]) ? -1 : 1;
    });
    $list = array_keys($rows) === range(0, count($rows) - 1);
    $out  = array();
    foreach ($order as $o) {
        if ($list) {
            $out[] = $rows[$o[2]];
        } else {
            $out[$o[2]] = $rows[$o[2]];
        }
    }
    return $out;
}

/**
 * The field a built-in form marks for the case "Error" and the message
 * its own handler gives when that required field is left empty; null for
 * a form with no such field. Pure.
 *
 * @param string $form the liveform name
 * @return array|null {field, message}
 */
function pg_preview_as_form_error_field($form)
{
    switch ((string)$form) {
        case 'login':
            return array('field' => 'email', 'message' => lang('Email or username is required.'));
        case 'forgot_password':
            return array('field' => 'email', 'message' => lang(array('string' => '{var:1} is required.', 'vars' => lang('Email'))));
        case 'register':
        case 'affiliate_sign_up_form':
        case 'shipping_address_and_arrival':
            return array('field' => 'first_name', 'message' => lang('First Name is required.'));
        case 'billing_information':
            return array('field' => 'billing_first_name', 'message' => lang('First Name is required.'));
    }
    return null;
}

/**
 * For the case "Error": the form drawn as after a submission with its
 * first required field left empty. The mark is written to the form's
 * liveform in the session, which pg_preview_as_while() puts back after the
 * widget. Nothing outside that case.
 *
 * @param mixed  $lf   the widget's liveform
 * @param string $form the liveform name
 * @return void
 */
function pg_preview_as_form_error($lf, $form)
{
    if (!is_object($lf) || !pg_preview_as_flag('form_error')) return;
    $mark = pg_preview_as_form_error_field($form);
    if ($mark !== null) $lf->mark_error($mark['field'], $mark['message']);
}

/**
 * The field of a custom form the case "Error" marks: the first required
 * one a visitor fills in (office use only is left out). Pure.
 *
 * @param array $field_map pg_cf_field_map()
 * @return array|null the form_fields row
 */
function pg_preview_as_custom_form_required($field_map)
{
    foreach (is_array($field_map) ? $field_map : array() as $row) {
        if (!is_array($row) || empty($row['required']) || !empty($row['office_use_only'])) continue;
        return $row;
    }
    return null;
}

/**
 * For the case "Error" on a custom form: its first required field marked
 * with the message custom_form.php gives it (pg_cf_required_message()).
 *
 * @param mixed $lf        the form's liveform
 * @param array $field_map pg_cf_field_map()
 * @return void
 */
function pg_preview_as_custom_form_error($lf, $field_map)
{
    if (!is_object($lf) || !pg_preview_as_flag('form_error')) return;
    $row = pg_preview_as_custom_form_required($field_map);
    if ($row === null) return;
    $label = function_exists('pg_tr_label') ? pg_tr_label((string)$row['label']) : (string)$row['label'];
    $lf->mark_error($row['id'], pg_cf_required_message((string)$row['type'], $label, 0));
}

/**
 * The sample account the Preview draws a signed-in visitor as: the values
 * the designer canvas draws (_sdAccountSample(), _sdOrderAddress() in
 * style_designer.src.js), so the canvas and the Preview tell one person.
 * It has no user row and gets none: a renderer that reads rows by the
 * visitor's id draws these rows instead of running its statement. A list
 * not held here (orders, invoices, bookings, tickets) is drawn empty, as a
 * new account's. Pure.
 *
 * @return array {account: array, contact: array, recipients: array[]}
 */
function pg_preview_as_sample_account()
{
    // A name is split as the canvas splits it: the last word is the last name.
    $split = function ($full) {
        $parts = preg_split('/\s+/', trim((string)$full));
        $last  = count($parts) > 1 ? array_pop($parts) : '';
        return array(implode(' ', $parts), $last);
    };
    $bill = $split(lang('Alex Morgan'));
    $ship = $split(lang('Sam Carter'));
    return array(
        'account' => array(
            'username'   => 'alex',
            'email'      => 'alex@example.com',
            'first_name' => $bill[0],
            'last_name'  => $bill[1],
            'member_id'  => 'M-1042',
        ),
        // The contact row, in the columns of contacts: the sample order's
        // billing person.
        'contact' => array(
            'salutation' => '', 'first_name' => $bill[0], 'last_name' => $bill[1], 'suffix' => '',
            'title' => '', 'company' => lang('Example Ltd.'), 'email_address' => 'alex@example.com',
            'business_address_1' => lang('Sample Street 1'), 'business_address_2' => lang('Flat 4'),
            'business_city' => lang('Springfield'), 'business_state' => lang('Illinois'),
            'business_zip_code' => '62701', 'business_country' => 'US', 'business_phone' => '',
            'mobile_phone' => '+90 555 000 00 00', 'home_phone' => '', 'business_fax' => '',
            'tax_number' => '', 'tax_office' => '', 'member_id' => 'M-1042', 'lead_source' => '', 'opt_in' => 0,
        ),
        // The address book: the order's shipping address first, as on the
        // canvas, and a second saved recipient.
        'recipients' => array(
            array(
                'id' => 1, 'ship_to_name' => lang('Studio'), 'salutation' => lang('Mr.'),
                'first_name' => $ship[0], 'last_name' => $ship[1], 'company' => lang('Cooper Studio'),
                'address_1' => lang('Harbour Road 12'), 'address_2' => lang('Floor 2'), 'city' => lang('Portland'),
                'state' => lang('Oregon'), 'zip_code' => '97201', 'country' => 'US',
                'address_verified' => 0, 'address_type' => 'business', 'phone_number' => '+90 555 111 22 33',
                'arrival_date' => '', 'user' => 0,
            ),
            array(
                'id' => 2, 'ship_to_name' => lang('Home'), 'salutation' => '',
                'first_name' => $bill[0], 'last_name' => $bill[1], 'company' => '',
                'address_1' => lang('Sample Street 1'), 'address_2' => lang('Flat 4'), 'city' => lang('Springfield'),
                'state' => lang('Illinois'), 'zip_code' => '62701', 'country' => 'US',
                'address_verified' => 0, 'address_type' => 'residential', 'phone_number' => '+90 555 000 00 00',
                'arrival_date' => '', 'user' => 0,
            ),
        ),
    );
}

/**
 * The sample account's saved recipient of that name, or null. Pure.
 *
 * @param string $ship_to_name
 * @return array|null
 */
function pg_preview_as_sample_recipient($ship_to_name)
{
    foreach (pg_preview_as_sample_account()['recipients'] as $row) {
        if ((string)$row['ship_to_name'] === (string)$ship_to_name) return $row;
    }
    return null;
}

/**
 * Who the widget being drawn now is drawn for: the one reader the
 * renderers ask in place of the USER_LOGGED_IN family of constants.
 *
 * Outside a Preview widget (pg_preview_as_current() empty) it gives the
 * request's own constants back, read with defined(), so a live visit draws
 * as before. Inside one it gives the setup's visitor: signed out (nobody),
 * signed in (the sample account: id 0, a plain user's role, no user row),
 * member (the sample account let in by the page's access rules), first
 * visit and returning (signed out). No constant is redefined. The operator's own rights (an edit link) are no
 * visitor's and are not read here.
 *
 * preview: whether a Preview visitor is drawn; sample: the sample account,
 * whose rows come from pg_preview_as_sample_account(), never from a
 * statement; role: null when not signed in; returning: whether the browser
 * was seen before this visit, the pg_ret cookie's answer outside a Preview
 * widget (pg_audience_returning()) and the chosen visitor's inside one, so
 * the audience conditions never read the operator's own cookie there.
 *
 * @return array {preview, sample, signed_in, id, contact_id, role, member, username, email, first_name, last_name, returning}
 */
function pg_preview_as_viewer()
{
    $current = pg_preview_as_current();
    if (empty($current)) {
        $signed_in = defined('USER_LOGGED_IN') && USER_LOGGED_IN === true;
        return array(
            'preview'    => false,
            'sample'     => false,
            'signed_in'  => $signed_in,
            'id'         => defined('USER_ID') && is_scalar(USER_ID) ? (int)USER_ID : 0,
            'contact_id' => defined('USER_CONTACT_ID') && is_scalar(USER_CONTACT_ID) ? (int)USER_CONTACT_ID : 0,
            'role'       => defined('USER_ROLE') ? USER_ROLE : null,
            'member'     => defined('USER_MEMBER') && USER_MEMBER === true,
            'username'   => defined('USER_USERNAME') ? (string)USER_USERNAME : '',
            'email'      => defined('USER_EMAIL_ADDRESS') ? (string)USER_EMAIL_ADDRESS : '',
            'first_name' => '',
            'last_name'  => '',
            'returning'  => function_exists('pg_audience_returning') && defined('PG_AUDIENCE_RETURNING_COOKIE')
                && pg_audience_returning(isset($_COOKIE[PG_AUDIENCE_RETURNING_COOKIE]) ? $_COOKIE[PG_AUDIENCE_RETURNING_COOKIE] : null, time()) === '1',
        );
    }
    $visitor   = isset($current['visitor']) ? $current['visitor'] : 'signed_out';
    $signed_in = ($visitor === 'signed_in' || $visitor === 'member');
    $sample    = pg_preview_as_sample_account();
    $account   = $sample['account'];
    return array(
        'preview'    => true,
        'sample'     => $signed_in,
        'signed_in'  => $signed_in,
        'id'         => 0,
        'contact_id' => 0,
        'role'       => $signed_in ? 3 : null,
        'member'     => $visitor === 'member',
        'username'   => $signed_in ? $account['username'] : '',
        'email'      => $signed_in ? $account['email'] : '',
        'first_name' => $signed_in ? $account['first_name'] : '',
        'last_name'  => $signed_in ? $account['last_name'] : '',
        'returning'  => $visitor === 'returning',
    );
}

/**
 * The value of an audience kind (pg_audience_token_kind()) for the visitor
 * of a Preview widget, in place of what pg_audience_value() reads of the
 * request: returning from the chosen case, never the pg_ret cookie; member
 * from the chosen member. Every other kind reads '': the sample account is
 * in no contact group, and the operator's country, device, UTM values and
 * experiment buckets are no visitor's. Pure.
 *
 * @param string $kind
 * @param array  $viewer pg_preview_as_viewer()
 * @return string '1' or ''
 */
function pg_preview_as_audience_value($kind, $viewer)
{
    if ($kind === 'returning') return !empty($viewer['returning']) ? '1' : '';
    if ($kind === 'member') return (!empty($viewer['signed_in']) && !empty($viewer['member'])) ? '1' : '';
    return '';
}

/**
 * Whether the visitor of pg_preview_as_viewer() may view a folder, as
 * check_view_access() answers for the request's own visitor. Inside a
 * Preview widget the answer is the chosen visitor's, by the folder's access
 * control type: public for anybody, guest and registration when signed in
 * or when $always_grant (anybody can get in), membership and private only
 * for the member, whom the page's access rules let in.
 *
 * @param int  $folder_id
 * @param bool $always_grant check_view_access()'s second argument
 * @return bool
 */
function pg_preview_as_view_access($folder_id, $always_grant = false)
{
    $viewer = pg_preview_as_viewer();
    if (!$viewer['preview']) return (bool)check_view_access($folder_id, $always_grant);
    return pg_preview_as_view_rule(get_access_control_type($folder_id), $viewer, $always_grant);
}

/**
 * pg_preview_as_view_access()'s rule from the folder's access control
 * type. Pure.
 *
 * @param string $type   get_access_control_type()
 * @param array  $viewer pg_preview_as_viewer()
 * @param bool   $always_grant
 * @return bool
 */
function pg_preview_as_view_rule($type, $viewer, $always_grant = false)
{
    $signed_in = !empty($viewer['signed_in']);
    if ($type === 'public') return true;
    if ($type === 'guest' || $type === 'registration') return $always_grant || $signed_in;
    if ($type === 'membership' || $type === 'private') return $signed_in && !empty($viewer['member']);
    return false;
}

/**
 * Which mask a record's field takes in a signed-in Preview: 'email',
 * 'phone', 'address' or '' (shown as it is), by the field's type and the
 * contact column it fills. Pure.
 *
 * @param string $type    form_fields.type
 * @param string $contact form_fields.contact_field
 * @return string
 */
function pg_preview_as_mask_kind($type, $contact)
{
    $contact = (string)$contact;
    if ((string)$type === 'email address' || $contact === 'email_address') return 'email';
    if (strpos($contact, 'phone') !== false || strpos($contact, 'fax') !== false) return 'phone';
    if (preg_match('/(address_[12]|_city|_state|_zip_code)$/', $contact)) return 'address';
    return '';
}

/**
 * A record's form_data rows with a real customer's e-mail, phone and
 * address masked (pg_preview_as_mask()). Pure.
 *
 * @param array $rows {data, field_type, field_contact, ...}
 * @return array
 */
function pg_preview_as_mask_rows($rows)
{
    foreach ($rows as $i => $row) {
        if (!is_array($row)) continue;
        $kind = pg_preview_as_mask_kind(isset($row['field_type']) ? $row['field_type'] : '',
            isset($row['field_contact']) ? $row['field_contact'] : '');
        if ($kind !== '' && isset($row['data'])) $rows[$i]['data'] = pg_preview_as_mask($row['data'], $kind);
    }
    return $rows;
}

/**
 * A real customer's e-mail address, phone or address as the Preview shows
 * it to a signed-in visitor: the first character and, for an e-mail, the
 * domain kept, the rest dotted. Pure.
 *
 * @param string $value
 * @param string $kind 'email', 'phone' or 'address'
 * @return string
 */
function pg_preview_as_mask($value, $kind)
{
    $value = trim((string)$value);
    if ($value === '') return '';
    if ($kind === 'email' && strpos($value, '@') !== false) {
        $at = strrpos($value, '@');
        return mb_substr($value, 0, 1) . '•••' . substr($value, $at);
    }
    if ($kind === 'phone') {
        $digits = preg_replace('/\D+/', '', $value);
        return '•••' . substr($digits, -2);
    }
    return mb_substr($value, 0, 1) . '•••';
}
