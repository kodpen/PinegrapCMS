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
// the line, the unit or the order it belongs to.
//
// Not in the functions.php manifest: loaded with require_once by the places
// that need it (the field editor, the cart and order paths, get_file.php).
// get_file.php loads no functions.php code, so the functions it calls
// (pg_pfu_file_lines(), pg_pfu_access_verdict()) use only db_items(),
// escape() and PHP itself.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
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
 * Whether a $_FILES entry carries a file the shopper chose (an upload error
 * counts, so it is refused rather than passed over); an empty input and a
 * list of files posted under one name do not.
 *
 * @param mixed $upload one $_FILES entry
 * @return bool
 */
function pg_pfu_upload_given($upload)
{
    if (!is_array($upload) || !isset($upload['error']) || !is_scalar($upload['error'])) {
        return false;
    }

    return (int) $upload['error'] !== UPLOAD_ERR_NO_FILE;
}

/**
 * What a cart save does with one File Upload answer: place a new file
 * (it replaces the stored one), refuse it (the stored one stays), remove the
 * stored one (the Remove box), or keep what is stored. A save without a new
 * file never drops the stored one.
 *
 * @param bool   $given      pg_pfu_upload_given()
 * @param string $refusal    pg_pfu_file_refusal() ('' when the file may be placed)
 * @param bool   $remove     the Remove box was ticked
 * @param bool   $has_stored the unit's answer holds a file
 * @return string place, refuse, remove or keep
 */
function pg_pfu_save_action($given, $refusal, $remove, $has_stored)
{
    if ($given) {
        return ((string) $refusal === '') ? 'place' : 'refuse';
    }

    return ($remove && $has_stored) ? 'remove' : 'keep';
}

/**
 * Whether the unit's answer holds a file once the save's action is done:
 * what a required File Upload field is checked against.
 *
 * @param string $action     pg_pfu_save_action()
 * @param bool   $has_stored the answer held a file before the save
 * @return bool
 */
function pg_pfu_holds_file($action, $has_stored)
{
    if ($action === 'place') {
        return true;
    }

    if ($action === 'remove') {
        return false;
    }

    return (bool) $has_stored;
}

/**
 * The form_data condition of the answers a classic cart save clears before
 * it writes a line's form again: every answer of the line but its File
 * Upload answers, which stay until a new file, the Remove box or a lower
 * quantity takes them, and every answer of a unit above the line's number of
 * forms.
 *
 * @param int   $order_item_id
 * @param array $file_field_ids the product form's File Upload field ids
 * @param int   $number_of_forms the forms the line now has
 * @return string values cast, ready for SQL
 */
function pg_pfu_reset_where($order_item_id, $file_field_ids, $number_of_forms)
{
    $line = "(form_data.order_item_id = '" . (int) $order_item_id . "') AND (form_data.order_item_id != '0')";
    $ids = array();

    foreach ((array) $file_field_ids as $id) {
        if (is_numeric($id) && ((int) $id > 0) && !in_array((int) $id, $ids, true)) {
            $ids[] = (int) $id;
        }
    }

    if (!$ids) {
        return $line;
    }

    return $line . " AND ((form_data.quantity_number > '" . max(0, (int) $number_of_forms) . "')"
        . " OR (form_data.form_field_id NOT IN (" . implode(', ', $ids) . ")))";
}

/**
 * The file input's accept list for a rule: the extensions with their dot,
 * '' when the field takes any file the blocklist lets through.
 *
 * @param array $rule pg_pfu_rule_clean()
 * @return string
 */
function pg_pfu_accept_attribute($rule)
{
    $out = array();

    foreach (pg_pfu_rule_extensions($rule) as $extension) {
        $out[] = '.' . $extension;
    }

    return implode(',', $out);
}

/**
 * The kept file of a File Upload answer, drawn above the field's input: its
 * name as a link, its size, a small preview for an image, and the Remove box
 * (posted as <field>_remove). The input below it replaces the file.
 *
 * @param array|null $file            id, name, size (pg_pfu_stored_file())
 * @param string     $html_field_name the field's input name
 * @param string     $base_path       OUTPUT_PATH
 * @return string '' when no file is kept
 */
function pg_pfu_kept_file_html($file, $html_field_name, $base_path)
{
    if (!is_array($file) || !isset($file['name']) || ((string) $file['name'] === '')) {
        return '';
    }

    $name = (string) $file['name'];
    $href = h($base_path . encode_url_path($name));
    $dot = mb_strrpos($name, '.');
    $extension = ($dot === false) ? '' : mb_strtolower(mb_substr($name, $dot + 1));
    $preview = '';

    if (in_array($extension, pg_pfu_image_extensions(), true)) {
        $preview = '<a href="' . $href . '" target="_blank"><img src="' . $href . '" alt="" style="max-width: 80px; max-height: 80px; vertical-align: middle; margin-right: .5em"></a>';
    }

    return '<div class="software_product_form_file" style="margin-bottom: .5em">'
        . $preview
        . '<a href="' . $href . '" target="_blank">' . h($name) . '</a>'
        . ' (' . h(convert_bytes_to_string((int) ($file['size'] ?? 0), 'auto')) . ')'
        . ' <label style="white-space: nowrap"><input type="checkbox" name="' . h($html_field_name . '_remove') . '" value="1"> ' . h(lang('Remove')) . '</label>'
        . '<div class="software_product_form_file_replace" style="font-size: 90%">' . h(lang('Choose a new file to replace it:')) . '</div>'
        . '</div>';
}

/**
 * The file a cart line unit's answer to a File Upload field holds.
 *
 * @param int $order_item_id
 * @param int $quantity_number
 * @param int $form_field_id
 * @return array|null id, name, size
 */
function pg_pfu_stored_file($order_item_id, $quantity_number, $form_field_id)
{
    if (((int) $order_item_id <= 0) || ((int) $form_field_id <= 0)) {
        return null;
    }

    $row = db_item(
        "SELECT files.id, files.name, files.size
        FROM form_data
        INNER JOIN files ON files.id = form_data.file_id
        WHERE
            (form_data.order_item_id = '" . (int) $order_item_id . "')
            AND (form_data.form_id = '0')
            AND (form_data.form_field_id = '" . (int) $form_field_id . "')
            AND (form_data.quantity_number = '" . (int) $quantity_number . "')
            AND (form_data.file_id > 0)
        ORDER BY form_data.id DESC
        LIMIT 1");

    return $row ? $row : null;
}

/**
 * Clears a cart line's answers before a classic cart save writes its form
 * again (pg_pfu_reset_where()), taking the files of the answers that go.
 *
 * @param int   $order_item_id
 * @param array $file_field_ids
 * @param int   $number_of_forms
 */
function pg_pfu_cart_reset($order_item_id, $file_field_ids, $number_of_forms)
{
    $where = pg_pfu_reset_where($order_item_id, $file_field_ids, $number_of_forms);

    pg_pfu_remove_files_where($where);
    db("DELETE FROM form_data WHERE " . $where);
}

/**
 * Whether this visitor has sent more cart files than the limit allows; each
 * call counts one file.
 *
 * @return bool
 */
function pg_pfu_upload_rate_exceeded()
{
    if (!function_exists('waf_rate_exceeded') || !function_exists('waf_client_ip') || !function_exists('waf_ip_subject')) {
        return false;
    }

    $ip = waf_client_ip();

    return ($ip !== '') && waf_rate_exceeded(waf_ip_subject($ip), 'cart_upload', 30, 600);
}

/**
 * Saves one File Upload answer of a classic cart or express order line unit
 * (shopping_cart.php, express_order.php): a new file is checked against the
 * field's rule and placed, a refused one is marked on its own field and the
 * stored file stays, the Remove box takes the stored file, and a save
 * without either keeps it. A required field without a file is marked when
 * $check_required.
 *
 * @param liveform $liveform
 * @param string   $html_field_name
 * @param array    $field          form_fields id, name, label, required, upload_folder_id, rules
 * @param int      $order_id
 * @param int      $order_item_id
 * @param int      $quantity_number
 * @param bool     $check_required checkout was asked for
 * @return string the action done (pg_pfu_save_action())
 */
function pg_pfu_cart_save_file($liveform, $html_field_name, $field, $order_id, $order_item_id, $quantity_number, $check_required)
{
    $upload = $_FILES[$html_field_name] ?? null;
    $given = pg_pfu_upload_given($upload);
    $remove = isset($_POST[$html_field_name . '_remove']) && is_string($_POST[$html_field_name . '_remove']) && ($_POST[$html_field_name . '_remove'] !== '');
    $stored = pg_pfu_stored_file($order_item_id, $quantity_number, $field['id']);
    $refusal = '';

    if ($given) {
        $limits = pg_upload_limits();
        $refusal = pg_pfu_file_refusal($upload, pg_pfu_rule_clean($field['rules'] ?? null), $limits['file_max']);

        if (($refusal === '') && pg_pfu_upload_rate_exceeded()) {
            $refusal = lang('Too many files were sent from your address. Please wait a few minutes and try again.');
        }
    }

    $action = pg_pfu_save_action($given, $refusal, $remove, (bool) $stored);

    if ($action === 'place') {
        $user_id = (defined('USER_LOGGED_IN') && USER_LOGGED_IN && defined('USER_ID')) ? (int) USER_ID : 0;
        $file = pg_pfu_place($upload, (int) ($field['upload_folder_id'] ?? 0), $user_id);

        if ($file === false) {
            $action = 'refuse';
            $refusal = lang(array(
                'string' => 'The server could not accept {var:1}. Please try again.',
                'vars' => array(pg_basename(str_replace('\\', '/', is_scalar($upload['name'] ?? null) ? (string) $upload['name'] : '')))));
        } else {
            pg_pfu_keep($order_id, $order_item_id, $quantity_number, $field, $file);
        }
    } elseif ($action === 'remove') {
        $where = "(form_data.order_item_id = '" . (int) $order_item_id . "') AND (form_data.order_item_id != '0')"
            . " AND (form_data.form_field_id = '" . (int) $field['id'] . "')"
            . " AND (form_data.quantity_number = '" . (int) $quantity_number . "')";

        pg_pfu_remove_files_where($where);
        db("DELETE FROM form_data WHERE " . $where);
    }

    if ($action === 'refuse') {
        $liveform->mark_error($html_field_name, h($refusal));
    } elseif ($check_required && !empty($field['required']) && !pg_pfu_holds_file($action, (bool) $stored)) {
        $liveform->mark_error($html_field_name, ((string) ($field['label'] ?? '') !== '')
            ? lang(array('string' => '{var:1} is required.', 'vars' => h($field['label'])))
            : lang('Please choose a file.'));
    }

    return $action;
}
