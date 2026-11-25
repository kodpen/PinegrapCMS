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
