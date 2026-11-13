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
 * @param int   $auto_dialog_id
 * @param array $values   the eight values of pg_auto_dialog_form_values(), in
 *                        its order: name, enabled, url, width, height, delay,
 *                        frequency, page ('' numbers are stored as 0)
 * @param int   $user_id
 * @return int the auto dialog's id, 0 when a query failed
 */
function pg_auto_dialog_save($auto_dialog_id, $values, $user_id)
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

    if ($auto_dialog_id > 0) {
        $row[] = $auto_dialog_id;

        return db_q("UPDATE auto_dialogs SET
                name = ?,
                enabled = ?,
                url = ?,
                width = ?,
                height = ?,
                delay = ?,
                frequency = ?,
                page = ?,
                last_modified_user_id = ?,
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = ?", $row) ? $auto_dialog_id : 0;
    }

    $row[] = (int) $user_id;

    if (!db_q("INSERT INTO auto_dialogs (
                name, enabled, url, width, height, delay, frequency, page,
                created_user_id, created_timestamp, last_modified_user_id, last_modified_timestamp)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP(), ?, UNIX_TIMESTAMP())", $row)) {
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
 * @param int $auto_dialog_id
 * @return bool
 */
function pg_auto_dialog_delete($auto_dialog_id)
{
    return (bool) db_q("DELETE FROM auto_dialogs WHERE id = ?", array((int) $auto_dialog_id));
}
