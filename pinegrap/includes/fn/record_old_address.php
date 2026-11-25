<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The earlier address names of a record (2026.11.0, 11.0.279): a post or a
 * category whose title or slug changes keeps answering at its old pretty
 * address.
 *
 * form_old_address holds one row per earlier name: the record (form_id), its
 * form page (page_id) and the name it had. A row is written only by
 * update_submitted_form_address_name(), when a complete record's name
 * changes from a name that was not empty; a record keeps at most
 * PG_RECORD_OLD_ADDRESS_MAX of them, the oldest dropped.
 *
 * A live name always wins: a record taking a name removes that name's old
 * rows on the same form page (a rename back, or another record taking it),
 * and every lookup asks the live names first. An old name never draws a
 * page: /<page>/<old name> answers 301 to the record's address of today
 * under the same checks a live name passes (pg_sw_route_record_address(),
 * get_page.php), and a list filter value (?category=<old name>) lists as
 * the name of today does (pg_coll_reference_lookup()).
 *
 * The lookups join forms, so a row whose record is gone answers as an
 * unknown name; rows go with their record (pg_submitted_form_delete(), the
 * personal data erasure).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The earlier names a record keeps; older ones are dropped.
if (!defined('PG_RECORD_OLD_ADDRESS_MAX')) {
    define('PG_RECORD_OLD_ADDRESS_MAX', 20);
}

// Whether the table is there (before 11.0.279 nothing is kept or read).
function pg_record_old_address_ready()
{
    return function_exists('pg_schema_has') && pg_schema_has('form_old_address', 'address_name');
}

/**
 * Whether a record's address name change keeps the name it had: only a
 * complete record (a draft was never on the site) whose earlier name was
 * not empty and differs from the new one. Pure.
 *
 * @param string $old      the name before the save
 * @param string $new      the name after it
 * @param bool   $complete forms.complete
 * @return bool
 */
function pg_record_old_address_kept($old, $new, $complete)
{
    $old = trim((string)$old);
    return $complete && ($old !== '') && ($old !== trim((string)$new));
}

/**
 * Of a record's old-name row ids, the ones beyond the limit: the oldest.
 * Pure.
 *
 * @param array $ids the record's row ids
 * @param int   $max how many it keeps
 * @return int[]
 */
function pg_record_old_address_overflow(array $ids, $max)
{
    $ids = array_map('intval', $ids);
    rsort($ids);
    return array_values(array_slice($ids, max(0, (int)$max)));
}

/**
 * After update_submitted_form_address_name() wrote a record's name: keeps
 * the earlier one when pg_record_old_address_kept() says so, and gives the
 * new name to this record alone by removing the old rows that held it on
 * the form page.
 *
 * @param int    $form_id  forms.id
 * @param int    $page_id  forms.page_id, the custom form's page
 * @param string $old      the name before the save
 * @param string $new      the name after it
 * @param bool   $complete forms.complete
 */
function pg_record_old_address_saved($form_id, $page_id, $old, $new, $complete)
{
    $form_id = (int)$form_id;
    $page_id = (int)$page_id;
    $old = trim((string)$old);
    $new = trim((string)$new);
    if ($form_id <= 0 || $page_id <= 0 || !pg_record_old_address_ready()) return;

    $kept = pg_record_old_address_kept($old, $new, $complete);
    if ($kept) {
        // One row per name of a record: the newest time wins.
        db("DELETE FROM form_old_address WHERE form_id = '" . $form_id . "' AND address_name = '" . e($old) . "'");
        db("INSERT INTO form_old_address (form_id, page_id, address_name, created_at)
            VALUES ('" . $form_id . "', '" . $page_id . "', '" . e($old) . "', '" . time() . "')");
    }

    // A live name is nobody's old name on the form page.
    if ($new !== '') {
        db("DELETE FROM form_old_address WHERE page_id = '" . $page_id . "' AND address_name = '" . e($new) . "'");
    }

    if ($kept) {
        $drop = pg_record_old_address_overflow(db_values("SELECT id FROM form_old_address WHERE form_id = '" . $form_id . "'"), PG_RECORD_OLD_ADDRESS_MAX);
        if ($drop) {
            db("DELETE FROM form_old_address WHERE id IN (" . implode(',', $drop) . ")");
        }
    }
}

/**
 * The record of form page $page_id that had the address name $name, as it
 * is today: array('id', 'reference_code', 'address_name'), null when no
 * complete record had it. Ask the live names first: this one does not.
 *
 * @param int    $page_id the custom form's page
 * @param string $name
 * @param bool   $binary  compare byte for byte (a list filter value) instead
 *                        of under the column's collation (an address)
 * @return array|null
 */
function pg_record_old_address_record($page_id, $name, $binary = false)
{
    $page_id = (int)$page_id;
    $name = trim((string)$name);
    if ($page_id <= 0 || $name === '' || strlen($name) > 255 || !pg_record_old_address_ready()) return null;

    $match = $binary
        ? "CAST(form_old_address.address_name AS BINARY) = CAST('" . e($name) . "' AS BINARY)"
        : "form_old_address.address_name = '" . e($name) . "'";
    $row = db_item(
        "SELECT forms.id, forms.reference_code, forms.address_name
         FROM form_old_address
         INNER JOIN forms ON forms.id = form_old_address.form_id AND forms.page_id = form_old_address.page_id
         WHERE form_old_address.page_id = '" . $page_id . "'
           AND $match
           AND forms.complete = 1
           AND forms.reference_code != ''
         ORDER BY form_old_address.id DESC
         LIMIT 1");
    return (is_array($row) && !empty($row['id'])) ? $row : null;
}

// Removes a record's earlier names (with the record).
function pg_record_old_address_delete($form_id)
{
    $form_id = (int)$form_id;
    if ($form_id > 0 && pg_record_old_address_ready()) {
        db("DELETE FROM form_old_address WHERE form_id = '" . $form_id . "'");
    }
}

/**
 * Sends the visitor on to a record's address of today with a 301, the
 * visitor's query string kept (the router's page parameter left out).
 *
 * @param string $address the address from the site's root (PATH)
 */
function pg_record_address_redirect($address)
{
    $query = $_GET;
    unset($query['page']);
    header('Location: ' . URL_SCHEME . HOSTNAME . $address . ($query ? '?' . http_build_query($query) : ''), true, 301);
    exit();
}
