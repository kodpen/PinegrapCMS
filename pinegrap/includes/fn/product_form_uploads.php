<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A File Upload field on a product form (pg_pfu_*): the field's rule (allowed
// types and largest size, kept under the "upload" key of form_fields.rules),
// the check of a shopper's file against it, placing the file as an
// attachment in the field's folder, keeping it on the cart line's answer
// (form_data.file_id), who may open it (get_file.php), and removing it with
// the line, the unit or the order it belongs to, and the general job's
// sweep of the files of carts that never became an order.
//
// Not in the functions.php manifest: loaded with require_once by the places
// that need it (the field editor, the cart and order paths, get_file.php).
// get_file.php loads no functions.php code, so the functions it calls
// (pg_pfu_file_lines(), pg_pfu_access_verdict()) use only db_items(),
// escape() and PHP itself.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// A cart's uploaded files are deleted this many days after its last touch.
if (!defined('PG_PFU_SWEEP_DAYS')) {
    define('PG_PFU_SWEEP_DAYS', 30);
}

// How often a sweep starts, in seconds; one under way carries on every tick.
if (!defined('PG_PFU_SWEEP_EVERY')) {
    define('PG_PFU_SWEEP_EVERY', 86400);
}

// Seconds the sweep may work in one tick of the general job.
if (!defined('PG_PFU_SWEEP_BUDGET')) {
    define('PG_PFU_SWEEP_BUDGET', 5);
}

// Carts read per chunk of the sweep.
if (!defined('PG_PFU_SWEEP_CHUNK')) {
    define('PG_PFU_SWEEP_CHUNK', 200);
}

/**
 * The extensions the "Images" choice stands for.
 *
 * @return array
 */
function pg_pfu_image_extensions()
{
    return array('jpg', 'jpeg', 'png', 'gif', 'webp');
}

/**
 * A field's upload rule, cleaned: from the stored form_fields.rules JSON (or
 * its decoded array, or the rule itself). An extension pg_upload_name_blocked()
 * refuses is never kept, so the allow-list only narrows the blocklist.
 *
 * @param mixed $rules form_fields.rules, the decoded rules, or the upload rule
 * @return array images (bool), pdf (bool), extensions (list), max_mb (int, 0: the server's limit)
 */
function pg_pfu_rule_clean($rules)
{
    if (is_string($rules)) {
        $rules = (trim($rules) === '') ? array() : json_decode($rules, true);
    }

    if (!is_array($rules)) {
        $rules = array();
    }

    $rule = (isset($rules['upload']) && is_array($rules['upload'])) ? $rules['upload'] : $rules;

    $extensions = isset($rule['extensions']) ? $rule['extensions'] : array();

    if (is_string($extensions)) {
        $extensions = pg_pfu_extensions_typed($extensions);
    }

    $clean = array();

    foreach ((array) $extensions as $extension) {
        if (!is_scalar($extension)) {
            continue;
        }

        $extension = mb_strtolower(ltrim(trim((string) $extension), '.'));

        if (preg_match('/^[a-z0-9]{1,10}$/', $extension) && !pg_pfu_extension_blocked($extension) && !in_array($extension, $clean, true)) {
            $clean[] = $extension;
        }
    }

    $max_mb = isset($rule['max_mb']) && is_scalar($rule['max_mb']) ? (int) $rule['max_mb'] : 0;

    return array(
        'images' => !empty($rule['images']),
        'pdf' => !empty($rule['pdf']),
        'extensions' => $clean,
        'max_mb' => max(0, $max_mb),
    );
}

/**
 * The extensions typed into the field's settings: separated by commas, spaces
 * or new lines, with or without their dot.
 *
 * @param string $text
 * @return array
 */
function pg_pfu_extensions_typed($text)
{
    $out = array();

    foreach (preg_split('/[\s,;]+/', (string) $text) as $part) {
        $part = mb_strtolower(ltrim(trim($part), '.'));

        if (($part !== '') && !in_array($part, $out, true)) {
            $out[] = $part;
        }
    }

    return $out;
}

/**
 * Whether the upload blocklist refuses a file with this extension.
 *
 * @param string $extension without its dot
 * @return bool
 */
function pg_pfu_extension_blocked($extension)
{
    return pg_upload_name_blocked('file.' . $extension);
}

/**
 * The extensions a rule lets through; an empty list lets through every file
 * the blocklist lets through.
 *
 * @param array $rule pg_pfu_rule_clean()
 * @return array
 */
function pg_pfu_rule_extensions($rule)
{
    $out = array();

    if (!empty($rule['images'])) {
        $out = array_merge($out, pg_pfu_image_extensions());
    }

    if (!empty($rule['pdf'])) {
        $out[] = 'pdf';
    }

    return array_values(array_unique(array_merge($out, (array) ($rule['extensions'] ?? array()))));
}

/**
 * The largest file a rule takes, in bytes: its own limit, never above the
 * server's.
 *
 * @param array $rule      pg_pfu_rule_clean()
 * @param int   $file_max  bytes, pg_upload_limits()['file_max'] (0: none)
 * @return int 0 when neither limits the file
 */
function pg_pfu_size_cap($rule, $file_max)
{
    $own = (int) ($rule['max_mb'] ?? 0) * 1024 * 1024;
    $file_max = (int) $file_max;

    if ($own <= 0) {
        return max(0, $file_max);
    }

    return ($file_max > 0) ? min($own, $file_max) : $own;
}

/**
 * The stored form_fields.rules value with the upload rule written into it:
 * the other keys stay as they were. Null (NULL in the column) when nothing
 * is left.
 *
 * @param array       $rule   pg_pfu_rule_clean()
 * @param string|null $stored the column's current value
 * @return string|null
 */
function pg_pfu_rules_json($rule, $stored = null)
{
    $rules = (is_string($stored) && (trim($stored) !== '')) ? json_decode($stored, true) : array();

    if (!is_array($rules)) {
        $rules = array();
    }

    $rule = pg_pfu_rule_clean($rule);
    $rules['upload'] = array(
        'images' => $rule['images'],
        'pdf' => $rule['pdf'],
        'extensions' => $rule['extensions'],
        'max_mb' => $rule['max_mb'],
    );

    return json_encode($rules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * The upload rule posted from the field editor, and what is wrong with it.
 * A typed extension the blocklist refuses, and a largest size above the
 * server's, are refused rather than dropped.
 *
 * @param array $post     the posted field form
 * @param int   $file_max bytes, pg_upload_limits()['file_max']
 * @return array rule (pg_pfu_rule_clean()), errors (field name => message)
 */
function pg_pfu_rule_from_post($post, $file_max)
{
    $errors = array();
    $typed = pg_pfu_extensions_typed(is_scalar($post['upload_extensions'] ?? null) ? (string) $post['upload_extensions'] : '');
    $refused = array();

    foreach ($typed as $extension) {
        if (!preg_match('/^[a-z0-9]{1,10}$/', $extension) || pg_pfu_extension_blocked($extension)) {
            $refused[] = $extension;
        }
    }

    if ($refused) {
        $errors['upload_extensions'] = lang(array(
            'string' => 'These file types cannot be uploaded: {var:1}.',
            'vars' => array(implode(', ', $refused))));
    }

    $max_text = is_scalar($post['upload_max_mb'] ?? null) ? trim((string) $post['upload_max_mb']) : '';
    $max_mb = 0;
    $server_mb = ((int) $file_max > 0) ? (int) floor((int) $file_max / (1024 * 1024)) : 0;

    if ($max_text !== '') {
        if (!preg_match('/^\d{1,6}$/', $max_text)) {
            $errors['upload_max_mb'] = lang('Please enter the largest size as a whole number of MB.');
        } else {
            $max_mb = (int) $max_text;

            if (($server_mb > 0) && ($max_mb > $server_mb)) {
                $errors['upload_max_mb'] = lang(array(
                    'string' => 'The largest size can be at most {var:1} MB, the biggest file this server accepts.',
                    'vars' => array($server_mb)));
            }
        }
    }

    $rule = pg_pfu_rule_clean(array(
        'images' => !empty($post['upload_images']),
        'pdf' => !empty($post['upload_pdf']),
        'extensions' => $typed,
        'max_mb' => $max_mb,
    ));

    return array('rule' => $rule, 'errors' => $errors);
}

/**
 * Why a shopper's file is refused, read before it is placed: an upload error,
 * a name the blocklist refuses, a type the field does not take, a file over
 * the field's (or the server's) largest size.
 *
 * @param mixed $upload   one $_FILES entry
 * @param array $rule     pg_pfu_rule_clean()
 * @param int   $file_max bytes, pg_upload_limits()['file_max']
 * @return string '' when the file may be placed or none was chosen
 */
function pg_pfu_file_refusal($upload, $rule, $file_max)
{
    if (!is_array($upload) || !isset($upload['error']) || is_array($upload['error'])) {
        return '';
    }

    if ((int) $upload['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    $refusal = pg_upload_file_refusal($upload, $file_max);

    if ($refusal !== '') {
        return $refusal;
    }

    $name = pg_basename(str_replace('\\', '/', is_scalar($upload['name'] ?? null) ? (string) $upload['name'] : ''));

    if (pg_upload_name_blocked($name)) {
        return lang(array('string' => '{var:1} cannot be uploaded: this type of file is not accepted.', 'vars' => array($name)));
    }

    $allowed = pg_pfu_rule_extensions($rule);
    $dot = mb_strrpos($name, '.');
    $extension = ($dot === false) ? '' : mb_strtolower(mb_substr($name, $dot + 1));

    if ($allowed && !in_array($extension, $allowed, true)) {
        return lang(array(
            'string' => '{var:1} cannot be uploaded here. Accepted types: {var:2}.',
            'vars' => array($name, implode(', ', $allowed))));
    }

    $cap = pg_pfu_size_cap($rule, $file_max);
    $size = (int) ($upload['size'] ?? 0);

    if (($cap > 0) && ($size > $cap)) {
        return lang(array(
            'string' => '{var:1} is larger than {var:2}, the biggest file this field accepts.',
            'vars' => array($name, convert_bytes_to_string($cap, 1))));
    }

    return '';
}

/**
 * Places a shopper's checked file: a unique name from the name it was sent
 * with, copied into the file directory, and a files row marked as an
 * attachment in the field's folder. Nothing is left behind when the copy
 * fails.
 *
 * @param array $upload    one $_FILES entry, checked by pg_pfu_file_refusal()
 * @param int   $folder_id form_fields.upload_folder_id
 * @param int   $user_id   the signed-in shopper, 0 for a guest
 * @return array|false id, name, size
 */
function pg_pfu_place($upload, $folder_id, $user_id = 0)
{
    $sent = pg_basename(str_replace('\\', '/', is_scalar($upload['name'] ?? null) ? (string) $upload['name'] : ''));

    if (($sent === '') || pg_upload_name_blocked($sent)) {
        return false;
    }

    $name = get_unique_name(array('name' => prepare_file_name($sent), 'type' => 'file'));

    if (!pg_cf_place_upload($upload, FILE_DIRECTORY_PATH . '/' . $name)) {
        return false;
    }

    $dot = mb_strrpos($name, '.');
    $extension = ($dot === false) ? '' : mb_substr($name, $dot + 1);
    $size = (int) @filesize(FILE_DIRECTORY_PATH . '/' . $name);

    $result = db_q(
        "INSERT INTO files (name, folder, type, size, user, design, attachment, timestamp)
        VALUES (?, ?, ?, ?, ?, '0', '1', UNIX_TIMESTAMP())",
        array($name, (string) (int) $folder_id, mb_substr($extension, 0, 4), (string) $size, (string) (int) $user_id));

    if ($result === false) {
        @unlink(FILE_DIRECTORY_PATH . '/' . $name);

        return false;
    }

    return array('id' => (int) mysqli_insert_id(db::$con), 'name' => $name, 'size' => $size);
}

/**
 * Keeps a placed file as a cart line unit's answer to a File Upload field:
 * the answer's data is the file's name, its file_id the file. A file the
 * answer held before goes with it.
 *
 * @param int   $order_id
 * @param int   $order_item_id
 * @param int   $quantity_number the unit, from 1
 * @param array $field           form_fields id and name
 * @param array $file            pg_pfu_place()
 */
function pg_pfu_keep($order_id, $order_item_id, $quantity_number, $field, $file)
{
    $where = "(form_data.order_item_id = '" . (int) $order_item_id . "') AND (form_data.order_item_id != '0')"
        . " AND (form_data.form_id = '0') AND (form_data.form_field_id = '" . (int) $field['id'] . "')"
        . " AND (form_data.quantity_number = '" . (int) $quantity_number . "')";

    pg_pfu_remove_files_where($where, array((int) $file['id']));
    db("DELETE FROM form_data WHERE " . $where);

    db_q(
        "INSERT INTO form_data (order_id, order_item_id, quantity_number, form_field_id, data, file_id, name, type)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'standard')",
        array(
            (string) (int) $order_id,
            (string) (int) $order_item_id,
            (string) (int) $quantity_number,
            (string) (int) $field['id'],
            (string) $file['name'],
            (string) (int) $file['id'],
            (string) $field['name']));
}

/**
 * The files to delete with a set of product form answers: each file once,
 * none that another answer still holds (a file is never taken from an answer
 * that stays), none kept on purpose.
 *
 * @param array $rows      file_id, name of the answers going away
 * @param array $still_held file ids another answer holds
 * @param array $keep      file ids not to delete
 * @return array id => name
 */
function pg_pfu_files_to_remove($rows, $still_held = array(), $keep = array())
{
    $still_held = array_map('intval', (array) $still_held);
    $keep = array_map('intval', (array) $keep);
    $out = array();

    foreach ((array) $rows as $row) {
        $id = (int) ($row['file_id'] ?? 0);

        if (($id <= 0) || in_array($id, $still_held, true) || in_array($id, $keep, true)) {
            continue;
        }

        $out[$id] = (string) ($row['name'] ?? '');
    }

    return $out;
}

/**
 * Deletes files from the file directory by name; a name with a path in it is
 * cut to its last segment, so nothing outside the directory is touched.
 *
 * @param array  $names
 * @param string $directory
 * @return int how many were deleted
 */
function pg_pfu_unlink_files($names, $directory)
{
    $count = 0;

    foreach ((array) $names as $name) {
        $name = pg_basename(str_replace('\\', '/', (string) $name));

        if (($name === '') || ($name === '.') || ($name === '..')) {
            continue;
        }

        if (is_file($directory . '/' . $name) && @unlink($directory . '/' . $name)) {
            $count++;
        }
    }

    return $count;
}

/**
 * Deletes the uploaded files of the product form answers $where matches
 * (a form_data condition), before the caller deletes the answers: the
 * files on disk and their files rows.
 *
 * @param string $where a form_data condition, values escaped
 * @param array  $keep  file ids not to delete
 * @return int how many files were deleted
 */
function pg_pfu_remove_files_where($where, $keep = array())
{
    $rows = db_items(
        "SELECT form_data.file_id, files.name
        FROM form_data
        INNER JOIN files ON files.id = form_data.file_id
        WHERE (form_data.file_id > 0) AND (form_data.form_id = '0') AND (" . $where . ")");

    if (!$rows) {
        return 0;
    }

    $ids = array_map(function ($row) { return (int) $row['file_id']; }, $rows);
    $still_held = db_values(
        "SELECT DISTINCT file_id
        FROM form_data
        WHERE (file_id IN (" . implode(', ', $ids) . ")) AND NOT (" . $where . ")");

    $files = pg_pfu_files_to_remove($rows, $still_held, $keep);

    if (!$files) {
        return 0;
    }

    db("DELETE FROM files WHERE id IN (" . implode(', ', array_keys($files)) . ")");

    return defined('FILE_DIRECTORY_PATH') ? pg_pfu_unlink_files(array_values($files), FILE_DIRECTORY_PATH) : 0;
}

/**
 * Deletes the uploaded files of cart lines' answers; called before the
 * answers are deleted, by every path that removes a line.
 *
 * @param array|int $order_item_ids
 * @return int
 */
function pg_pfu_remove_line_files($order_item_ids)
{
    $ids = array_values(array_filter(array_map('intval', (array) $order_item_ids), function ($id) { return $id > 0; }));

    return $ids ? pg_pfu_remove_files_where("form_data.order_item_id IN (" . implode(', ', $ids) . ")") : 0;
}

/**
 * Deletes the uploaded files of a line's units above its new quantity; the
 * cart paths call it when a line's quantity is lowered.
 *
 * @param int $order_item_id
 * @param int $quantity the line's new quantity
 * @return int
 */
function pg_pfu_remove_unit_files($order_item_id, $quantity)
{
    $order_item_id = (int) $order_item_id;

    if ($order_item_id <= 0) {
        return 0;
    }

    return pg_pfu_remove_files_where("(form_data.order_item_id = '" . $order_item_id . "') AND (form_data.quantity_number > '" . max(0, (int) $quantity) . "')");
}

/**
 * Deletes the uploaded files of an order's product form answers; called
 * before the order's answers are deleted.
 *
 * @param int $order_id
 * @return int
 */
function pg_pfu_remove_order_files($order_id)
{
    $order_id = (int) $order_id;

    return ($order_id > 0) ? pg_pfu_remove_files_where("(form_data.order_id = '" . $order_id . "') AND (form_data.order_item_id > 0)") : 0;
}

/**
 * The cart lines whose product form answers hold a file, with their order:
 * for get_file.php, so db_items() and PHP only.
 *
 * @param int $file_id
 * @return array order_id, order_user_id, order_status (empty: not a product form answer's file)
 */
function pg_pfu_file_lines($file_id)
{
    $file_id = (int) $file_id;

    if ($file_id <= 0) {
        return array();
    }

    return (array) db_items(
        "SELECT
            form_data.order_id,
            orders.user_id AS order_user_id,
            orders.status AS order_status
        FROM form_data
        LEFT JOIN orders ON orders.id = form_data.order_id
        WHERE
            (form_data.file_id = '" . $file_id . "')
            AND (form_data.form_id = '0')
            AND (form_data.order_item_id > 0)");
}

/**
 * Who opens a product form answer's file, whatever its folder allows: the
 * visitor whose cart holds the line, the signed-in buyer of the order, and
 * staff who manage orders (role below 3, or the commerce right). Pure, and
 * PHP only, for get_file.php.
 *
 * @param array $lines  pg_pfu_file_lines()
 * @param array $viewer cart_order_id (the session's cart), user_id (0: not
 *                      signed in), role, manage_ecommerce
 * @return bool
 */
function pg_pfu_access_verdict($lines, $viewer)
{
    $user_id = (int) ($viewer['user_id'] ?? 0);
    $cart_order_id = (int) ($viewer['cart_order_id'] ?? 0);

    if (($user_id > 0) && (((int) ($viewer['role'] ?? 3) < 3) || !empty($viewer['manage_ecommerce']))) {
        return true;
    }

    foreach ((array) $lines as $line) {
        $order_id = (int) ($line['order_id'] ?? 0);

        if ($order_id <= 0) {
            continue;
        }

        if ($cart_order_id === $order_id) {
            return true;
        }

        if (($user_id > 0) && ((int) ($line['order_user_id'] ?? 0) === $user_id)) {
            return true;
        }
    }

    return false;
}

/**
 * A cart's last touch, pure: the newest of the time it was started, its last
 * change and the newest file uploaded into its answers.
 *
 * @param array $cart order_date, last_modified_timestamp, file_touch
 * @return int unix time, 0 when nothing is known
 */
function pg_pfu_sweep_last_touch($cart)
{
    return max(
        0,
        (int) ($cart['order_date'] ?? 0),
        (int) ($cart['last_modified_timestamp'] ?? 0),
        (int) ($cart['file_touch'] ?? 0));
}

/**
 * The newest last touch a cart may have for its files to be swept at a
 * moment: PG_PFU_SWEEP_DAYS before it.
 *
 * @param int $now
 * @return int
 */
function pg_pfu_sweep_before($now)
{
    return (int) $now - PG_PFU_SWEEP_DAYS * 86400;
}

/**
 * Whether a cart's uploaded files are swept, pure: the cart never became an
 * order (still incomplete) and its last touch is PG_PFU_SWEEP_DAYS days ago
 * or more. A cart with no known time is kept.
 *
 * @param array $cart status, order_date, last_modified_timestamp, file_touch
 * @param int   $now
 * @return bool
 */
function pg_pfu_sweep_chosen($cart, $now)
{
    if ((string) ($cart['status'] ?? '') !== 'incomplete') {
        return false;
    }

    $touch = pg_pfu_sweep_last_touch($cart);

    return ($touch > 0) && ($touch <= pg_pfu_sweep_before($now));
}

/**
 * The carts of a chunk whose files are swept, pure.
 *
 * @param array $carts pg_pfu_sweep_carts_sql() rows
 * @param int   $now
 * @return int[] order ids
 */
function pg_pfu_sweep_pick($carts, $now)
{
    $out = array();

    foreach ((array) $carts as $cart) {
        $id = (int) ($cart['id'] ?? 0);

        if (($id > 0) && pg_pfu_sweep_chosen($cart, $now) && !in_array($id, $out, true)) {
            $out[] = $id;
        }
    }

    return $out;
}

/**
 * The sweep's marker: where a pass under way stands and when the last one
 * finished.
 *
 * @return string
 */
function pg_pfu_sweep_marker()
{
    return PG_FUNCTIONS_DIR . '/data/temp/product_form_upload_sweep.json';
}

/**
 * The marker as it is used, pure. Anything unreadable is a sweep that never
 * ran.
 *
 * @param mixed $raw the decoded marker
 * @return array after_id (int), done_at (int)
 */
function pg_pfu_sweep_state($raw)
{
    $raw = is_array($raw) ? $raw : array();

    return array(
        'after_id' => isset($raw['after_id']) ? max(0, (int) $raw['after_id']) : 0,
        'done_at' => isset($raw['done_at']) ? max(0, (int) $raw['done_at']) : 0,
    );
}

/**
 * Whether a sweep is due at a moment, pure: one is under way, or the last
 * one finished PG_PFU_SWEEP_EVERY seconds ago or more.
 *
 * @param array $state pg_pfu_sweep_state()
 * @param int   $now
 * @return bool
 */
function pg_pfu_sweep_due_at($state, $now)
{
    return ($state['after_id'] > 0) || ($state['done_at'] <= ((int) $now - PG_PFU_SWEEP_EVERY));
}

/**
 * Whether the general job runs the sweep on this tick: one read of a small
 * file.
 *
 * @return bool
 */
function pg_pfu_sweep_due()
{
    return pg_pfu_sweep_due_at(pg_pfu_sweep_state(@json_decode((string) @file_get_contents(pg_pfu_sweep_marker()), true)), time());
}

/**
 * The query for the next carts the sweep walks: the orders whose product
 * form answers hold a file, after the last one done, in order (form_data's
 * order_id index).
 *
 * @param int $after_id
 * @param int $rows
 * @return string
 */
function pg_pfu_sweep_walk_sql($after_id, $rows)
{
    return "SELECT DISTINCT order_id
        FROM form_data
        WHERE
            (order_id > '" . max(0, (int) $after_id) . "')
            AND (form_id = '0')
            AND (order_item_id > 0)
            AND (file_id > 0)
        ORDER BY order_id
        LIMIT " . max(1, (int) $rows);
}

/**
 * The query for a chunk's carts with what their last touch is made of: the
 * order's status and times, and the newest file in its answers. An answer
 * whose order row is gone has no row here and is never swept.
 *
 * @param int[] $ids
 * @return string '' without an id
 */
function pg_pfu_sweep_carts_sql($ids)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids), function ($id) { return $id > 0; })));

    if (!$ids) {
        return '';
    }

    return "SELECT
            orders.id,
            orders.status,
            orders.order_date,
            orders.last_modified_timestamp,
            MAX(files.timestamp) AS file_touch
        FROM orders
        LEFT JOIN form_data ON
            (form_data.order_id = orders.id)
            AND (form_data.form_id = '0')
            AND (form_data.order_item_id > 0)
            AND (form_data.file_id > 0)
        LEFT JOIN files ON files.id = form_data.file_id
        WHERE orders.id IN (" . implode(', ', $ids) . ")
        GROUP BY orders.id, orders.status, orders.order_date, orders.last_modified_timestamp";
}

/**
 * The form_data condition of the answers whose files a sweep deletes: the
 * picked carts' product form answers, while the cart is still incomplete and
 * still untouched since the cutoff, read again at the delete so a cart that
 * became an order or came back meanwhile keeps its files.
 *
 * @param int[] $ids    pg_pfu_sweep_pick()
 * @param int   $before pg_pfu_sweep_before()
 * @return string '' without an id
 */
function pg_pfu_sweep_where($ids, $before)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids), function ($id) { return $id > 0; })));

    if (!$ids) {
        return '';
    }

    $before = (int) $before;

    return "(form_data.order_item_id > 0) AND form_data.order_id IN (
            SELECT orders.id
            FROM orders
            WHERE
                (orders.id IN (" . implode(', ', $ids) . "))
                AND (orders.status = 'incomplete')
                AND (orders.last_modified_timestamp <= '" . $before . "')
                AND (orders.order_date <= '" . $before . "'))";
}

/**
 * Sweeps the uploaded files of carts that never became an order,
 * PG_PFU_SWEEP_DAYS days after their last touch, for the general job: a
 * chunk of carts at a time until the time is up, the place kept in the
 * marker so the next tick carries on. Files go through
 * pg_pfu_remove_files_where(), so a file another answer still holds stays;
 * the swept answers then lose their file, so a required file is asked for
 * again if the shopper comes back. An order is never swept.
 *
 * @param int $seconds
 * @return array state (skipped, more, done), carts, files
 */
function pg_pfu_sweep_run($seconds)
{
    $result = array('state' => 'skipped', 'carts' => 0, 'files' => 0);

    if (!class_exists('db', false) || empty(db::$con)) {
        return $result;
    }

    $marker = pg_pfu_sweep_marker();
    $state = pg_pfu_sweep_state(@json_decode((string) @file_get_contents($marker), true));
    $now = time();
    $before = pg_pfu_sweep_before($now);
    $deadline = microtime(true) + max(1, (int) $seconds);
    $result['state'] = 'more';

    while (microtime(true) < $deadline) {
        // A failed read ends the tick without marking the pass done.
        $walk = @mysqli_query(db::$con, pg_pfu_sweep_walk_sql($state['after_id'], PG_PFU_SWEEP_CHUNK));

        if (!$walk) {
            break;
        }

        $ids = array();

        while ($row = mysqli_fetch_row($walk)) {
            $ids[] = (int) $row[0];
        }

        if (!$ids) {
            $state = array('after_id' => 0, 'done_at' => $now);
            $result['state'] = 'done';
            break;
        }

        $carts_result = @mysqli_query(db::$con, pg_pfu_sweep_carts_sql($ids));

        if (!$carts_result) {
            break;
        }

        $carts = array();

        while ($row = mysqli_fetch_assoc($carts_result)) {
            $carts[] = $row;
        }

        $picked = pg_pfu_sweep_pick($carts, $now);

        if ($picked) {
            $where = pg_pfu_sweep_where($picked, $before);
            $result['files'] += pg_pfu_remove_files_where($where);

            // The answers stay with their cart; the file they named is gone.
            db("UPDATE form_data
                SET file_id = '0', data = ''
                WHERE
                    (form_data.form_id = '0')
                    AND (form_data.file_id > 0)
                    AND (" . $where . ")
                    AND NOT EXISTS (SELECT 1 FROM files WHERE files.id = form_data.file_id)");

            $result['carts'] += count($picked);
        }

        $state['after_id'] = max($ids);
    }

    @file_put_contents($marker, json_encode($state), LOCK_EX);

    return $result;
}
