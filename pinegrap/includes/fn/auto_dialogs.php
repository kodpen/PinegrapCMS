<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Auto dialogs: the one write path the create and edit screens
 * (add_auto_dialog.php, edit_auto_dialog.php) and the external API
 * (/design/auto-dialogs) share, so a row written from either place is
 * written the same way.
 *
 * The callers check the values first (includes/forms/auto_dialog_form.php);
 * pg_auto_dialogs_for_page() is the site's own guard on the stored URL.
 * Nothing here checks who is asking: the screens gate on the manager role,
 * the API on design:write. Every function returns 0/false/null when a query
 * failed.
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
 * Whether another auto dialog already has the name.
 *
 * @param string   $name
 * @param int|null $except_id  the auto dialog being edited, null on create
 * @return bool|null null when the query failed
 */
function pg_auto_dialog_name_in_use($name, $except_id)
{
    $query = "SELECT id FROM auto_dialogs WHERE (name = ?)";
    $values = array((string) $name);

    if ($except_id !== null) {
        $query .= " AND (id != ?)";
        $values[] = (int) $except_id;
    }

    $result = db_q($query . " LIMIT 1", $values);

    if (!$result) {
        return null;
    }

    return mysqli_num_rows($result) > 0;
}

/**
 * Creates (id 0) or updates an auto dialog.
 *
 * @param int        $auto_dialog_id
 * @param array      $values   the eight values of pg_auto_dialog_form_values(), in
 *                             its order: name, enabled, url, width, height, delay,
 *                             frequency, page ('' numbers are stored as 0)
 * @param int        $user_id
 * @param array|null $open     when, to whom and where it opens (open_on,
 *                             open_scroll, audience, place), read through
 *                             pg_auto_dialog_open_settings(); null, or a site
 *                             whose columns are not added yet, leaves them as
 *                             stored (the defaults on a create)
 * @return int the auto dialog's id, 0 when a query failed
 */
function pg_auto_dialog_save($auto_dialog_id, $values, $user_id, $open = null)
{
    $auto_dialog_id = (int) $auto_dialog_id;
    $values = array_values($values);
    $row = array(
        (string) $values[0],
        (int) $values[1],
        (string) $values[2],
        (int) $values[3],
        (int) $values[4],
        (int) $values[5],
        (int) $values[6],
        (string) $values[7],
        (int) $user_id,
    );

    // The open settings go after the eight values, before the user ids.
    $open_columns = array();

    if (($open !== null) && pg_auto_dialog_open_ready()) {
        $open = pg_auto_dialog_open_settings($open);
        $open_columns = array_keys($open);
        array_splice($row, 8, 0, array_values($open));
    }

    if ($auto_dialog_id > 0) {
        $row[] = $auto_dialog_id;

        $open_sql = '';

        foreach ($open_columns as $column) {
            $open_sql .= $column . " = ?,\n                ";
        }

        return db_q("UPDATE auto_dialogs SET
                name = ?,
                enabled = ?,
                url = ?,
                width = ?,
                height = ?,
                delay = ?,
                frequency = ?,
                page = ?,
                " . $open_sql . "last_modified_user_id = ?,
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = ?", $row) ? $auto_dialog_id : 0;
    }

    $row[] = (int) $user_id;

    $open_names = $open_columns ? ', ' . implode(', ', $open_columns) : '';
    $open_marks = str_repeat(', ?', count($open_columns));

    if (!db_q("INSERT INTO auto_dialogs (
                name, enabled, url, width, height, delay, frequency, page" . $open_names . ",
                created_user_id, created_timestamp, last_modified_user_id, last_modified_timestamp)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?" . $open_marks . ", ?, UNIX_TIMESTAMP(), ?, UNIX_TIMESTAMP())", $row)) {
        return 0;
    }

    return (int) mysqli_insert_id(db::$con);
}

/**
 * The auto dialog rows a page may hand to software.init_auto_dialogs(), which
 * opens each URL in an iframe: a row whose URL a browser would open with a
 * scheme other than http or https (javascript:, data:), saved before the
 * save checked the address, is left out, so it never runs in the site's
 * origin. The keys of the rows that stay are renumbered (a JSON list).
 *
 * @param array $rows  auto_dialogs rows with a url column
 * @return array
 */
function pg_auto_dialogs_for_page($rows)
{
    $kept = array();

    foreach ((array) $rows as $row) {
        if (is_array($row) && isset($row['url']) && pg_address_opens_as_web($row['url'])) {
            $kept[] = $row;
        }
    }

    return $kept;
}

/**
 * The enabled auto dialogs a page prints for software.init_auto_dialogs():
 * the rows whose page is blank or a part of the page's name (case-blind),
 * with the values the script needs. Nothing here depends on the visitor but
 * the cart audience: the script reads the cookie of a dialog already shown
 * and counts the delay from the visit's first page itself, so every visitor
 * gets the same page and a cached copy stays right for all of them. A row
 * for visitors with a cart is printed only when $has_cart is true, and the
 * caller then keeps the page out of the cache.
 *
 * @param array  $rows       auto_dialogs rows (id, url, width, height, delay,
 *                           frequency, page and the open settings)
 * @param string $page_name  the page being drawn
 * @param bool   $has_cart   whether this visitor's order holds a line
 * @return array a JSON list of id, url, width, height, delay, frequency,
 *               open_on, open_scroll, audience, place
 */
function pg_auto_dialogs_on_page($rows, $page_name, $has_cart = false)
{
    $page_name = mb_strtolower((string) $page_name);
    $kept = array();

    foreach ((array) $rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $page = mb_strtolower(isset($row['page']) ? (string) $row['page'] : '');

        if (($page !== '') && (mb_strpos($page_name, $page) === false)) {
            continue;
        }

        $open = pg_auto_dialog_open_settings($row);

        if (!pg_auto_dialog_audience_shows($open['audience'], $has_cart)) {
            continue;
        }

        $kept[] = array(
            'id' => (int) ($row['id'] ?? 0),
            'url' => (string) ($row['url'] ?? ''),
            'width' => (int) ($row['width'] ?? 0),
            'height' => (int) ($row['height'] ?? 0),
            'delay' => (int) ($row['delay'] ?? 0),
            'frequency' => (int) ($row['frequency'] ?? 0),
        ) + $open;
    }

    return $kept;
}

/**
 * The values each open setting takes, its default first: the value a row
 * stored before the columns, or one this version does not know, reads as.
 *
 * @return array  setting => list of values
 */
function pg_auto_dialog_open_choices()
{
    return array(
        'open_on' => array('delay', 'scroll', 'leave'),
        'audience' => array('all', 'cart'),
        'place' => array('centre', 'top', 'bottom', 'corner'),
    );
}

/**
 * When, to whom and where an auto dialog opens, read from a row or from the
 * values a form or the API sends: open_on (delay, scroll, leave), open_scroll
 * (percent of the page, 1 to 100), audience (all, cart), place (centre, top,
 * bottom, corner). A missing or unknown value reads as the default (delay,
 * 50, all, centre), so a row stored before the columns, or by hand or by a
 * newer version, opens as an auto dialog always has. Pure.
 *
 * @param array $row
 * @return array  open_on, open_scroll, audience, place
 */
function pg_auto_dialog_open_settings($row)
{
    $row = (array) $row;
    $open = array();

    foreach (pg_auto_dialog_open_choices() as $setting => $values) {
        $value = isset($row[$setting]) && is_scalar($row[$setting]) ? strtolower(trim((string) $row[$setting])) : '';
        $open[$setting] = in_array($value, $values, true) ? $value : $values[0];
    }

    $scroll = isset($row['open_scroll']) && is_scalar($row['open_scroll']) ? trim((string) $row['open_scroll']) : '';
    $scroll = (preg_match('/^[0-9]{1,3}$/', $scroll) === 1) ? (int) $scroll : 0;

    return array(
        'open_on' => $open['open_on'],
        'open_scroll' => (($scroll >= 1) && ($scroll <= 100)) ? $scroll : 50,
        'audience' => $open['audience'],
        'place' => $open['place'],
    );
}

/**
 * Whether a row with this audience is printed for a visitor: everyone for
 * all, only a visitor whose order holds a line for cart. Pure.
 *
 * @param string $audience  as read by pg_auto_dialog_open_settings()
 * @param bool   $has_cart
 * @return bool
 */
function pg_auto_dialog_audience_shows($audience, $has_cart)
{
    return ($audience !== 'cart') || ($has_cart === true);
}

/**
 * Whether any of the rows is for visitors with a cart, so that the page
 * needs to ask the session about the cart at all.
 *
 * @param array $rows
 * @return bool
 */
function pg_auto_dialogs_need_cart($rows)
{
    foreach ((array) $rows as $row) {
        if (is_array($row) && (pg_auto_dialog_open_settings($row)['audience'] === 'cart')) {
            return true;
        }
    }

    return false;
}

/**
 * Whether this visitor's session holds an order with a line in the cart (a
 * line saved for later does not count).
 *
 * @return bool
 */
function pg_auto_dialog_session_has_cart()
{
    $order_id = (isset($_SESSION['ecommerce']['order_id']) && is_scalar($_SESSION['ecommerce']['order_id']))
        ? (int) $_SESSION['ecommerce']['order_id'] : 0;

    if ($order_id <= 0) {
        return false;
    }

    $active = function_exists('pg_order_item_active_sql') ? pg_order_item_active_sql('order_items') : '';

    return (bool) db_value("SELECT id FROM order_items WHERE order_id = '" . $order_id . "'" . $active . " LIMIT 1");
}

/**
 * Whether the open settings' columns are on auto_dialogs: the files land
 * before the upgrade adds them, and until then a save leaves them out.
 *
 * @return bool
 */
function pg_auto_dialog_open_ready()
{
    return pg_schema_has('auto_dialogs', 'place');
}

/**
 * @param int $auto_dialog_id
 * @return bool
 */
function pg_auto_dialog_delete($auto_dialog_id)
{
    return (bool) db_q("DELETE FROM auto_dialogs WHERE id = ?", array((int) $auto_dialog_id));
}
