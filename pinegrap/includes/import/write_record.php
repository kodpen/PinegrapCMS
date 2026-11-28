<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The record writer of a content import (includes/fn/importers.php): a post
 * of an export becomes a record of the form the mapping names, its parts in
 * the fields the mapping chose for them.
 *
 * - Title, body, summary, date, featured image, categories and tags; a part
 *   without a field, or whose field is not of that form, is left out.
 * - The body's pictures are fetched first (includes/import/media.php) and
 *   the body passes pg_sanitize_rich_text() with the staff profile, as the
 *   panel's record editor keeps it: the visitor profile would drop a picture
 *   that could not be fetched and still stands at its old address.
 * - Categories and tags meet an option or reference field by label (case
 *   ignored); a name no choice carries is named in the report.
 * - The record belongs to the author's account this import made, else to
 *   nobody: an existing account is never matched by its address, which
 *   registration does not verify. A draft is an incomplete record, and gets
 *   no pretty address until it is completed.
 * - A private or password-protected post is skipped: a record's visibility
 *   does not follow complete, so it would be readable on the site.
 * - A featured image whose attachment comes later in the export is attached
 *   when that file is fetched (pg_import_record_attach_picture()).
 * - Beside a post's parts, a collection file's cells (data.columns, keyed by
 *   the mapping's col_<n> rows) go into the fields paired with them
 *   (pg_import_record_column_rows()): rich text as HTML through the same
 *   media fetch and cleaner, a picture as a fetched file, an option by its
 *   label, a reference as the text it holds.
 *
 * The item's data: status, body, excerpt, time, slug, author (login, name,
 * email), categories, tags, picture (the attachment's source key), columns
 * (mapping row => cell).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/import/media.php');
require_once(PG_FUNCTIONS_DIR . '/includes/import/collection.php');

/**
 * The source key prefix of a record waiting for its featured image: the
 * import_items row (kind record, the record's id) the file writer looks for
 * when it fetches that attachment.
 */
define('PG_IMPORT_PICTURE_WAIT', 'picture-wait:');

/**
 * HTML as plain text: paragraphs, list items and headings end in a blank
 * line, a <br> in a line break, entities decoded, runs of blank lines
 * folded. Pure.
 *
 * @param string $html
 * @return string
 */
function pg_import_record_plain_text($html)
{
    $text = str_replace(array("\r\n", "\r"), "\n", (string) $html);
    $text = preg_replace('#<br\s*/?>#i', "\n", $text);
    $text = preg_replace('#</(p|div|li|h[1-6]|blockquote)\s*>#i', "\n\n", $text);
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/[ \t]+\n/", "\n", $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);

    return trim($text);
}

/**
 * Whether the post becomes a complete record: every status but draft. Pure.
 *
 * @param array $item
 * @return bool
 */
function pg_import_record_complete($item)
{
    return (string) ($item['data']['status'] ?? '') !== 'draft';
}

/**
 * Why a post is not made a record, '' when it is: the old site kept a
 * private or password-protected post from its readers. Pure.
 *
 * @param array $item
 * @return string
 */
function pg_import_record_withheld($item)
{
    $data = (array) ($item['data'] ?? array());

    if (!empty($data['protected'])) {
        return lang('The post is protected by a password on the old site, so it is not imported.');
    }

    if ((string) ($data['wp_status'] ?? '') === 'private') {
        return lang('The post is private on the old site, so it is not imported.');
    }

    return '';
}

/**
 * The source key of the post's author as the reader gave it
 * ('<site>:author:<login>'), '' when the post or its author has none. Pure.
 *
 * @param array $item
 * @return string
 */
function pg_import_record_author_key($item)
{
    $login = (string) ($item['data']['author']['login'] ?? '');
    $key = (string) ($item['source_key'] ?? '');
    $at = strpos($key, ':post:');

    if (($login === '') || ($at === false) || ($at === 0)) {
        return '';
    }

    return substr($key, 0, $at) . ':author:' . $login;
}

/**
 * Category or tag names as a field takes them. A field with choices (an
 * option set, a reference) keeps the choices whose label or value matches a
 * name, case ignored; a text field takes the names. Each name once. Pure.
 *
 * @param string[] $names
 * @param array    $field pg_import_record_form_fields() row
 * @return array{values:string[],missing:string[]}
 */
function pg_import_record_terms($names, $field)
{
    $values = array();
    $missing = array();
    $options = isset($field['options']) && is_array($field['options']) ? $field['options'] : null;

    foreach ($names as $name) {
        $name = trim((string) $name);

        if ($name === '') {
            continue;
        }

        if ($options === null) {
            $values[] = $name;
            continue;
        }

        $wanted = mb_strtolower($name);
        $found = null;

        foreach ($options as $value => $label) {
            if ((mb_strtolower((string) $label) === $wanted) || (mb_strtolower((string) $value) === $wanted)) {
                $found = (string) $value;
                break;
            }
        }

        if ($found === null) {
            $missing[] = $name;
        } else {
            $values[] = $found;
        }
    }

    return array('values' => array_values(array_unique($values)), 'missing' => array_values(array_unique($missing)));
}

/**
 * The form_data rows a post becomes. Pure: the caller reads the form's
 * fields and prepares the parts that need the database or the network.
 *
 * @param array $item
 * @param array $mapping the import's mapping (part => field id)
 * @param array $fields  field id => pg_import_record_form_fields() row
 * @param array $context body, excerpt (sanitised HTML), picture_id, picture_note
 * @return array{rows:array[],notes:string[]}
 */
function pg_import_record_rows($item, $mapping, $fields, $context)
{
    $data = (array) ($item['data'] ?? array());
    $rows = array();
    $notes = array();
    $field_of = function ($part) use ($mapping, $fields) {
        $id = (int) ($mapping[$part] ?? 0);

        return ($id > 0) && isset($fields[$id]) ? $fields[$id] : null;
    };
    $add = function ($field, $value, $type = 'standard', $file_id = 0) use (&$rows) {
        $rows[] = array('field_id' => (int) $field['id'], 'name' => (string) $field['name'], 'data' => (string) $value, 'file_id' => (int) $file_id, 'type' => $type);
    };

    $title = trim(preg_replace('/\s+/u', ' ', pg_import_record_plain_text((string) ($item['title'] ?? ''))));

    if ($title === '') {
        $title = trim(rawurldecode((string) ($data['slug'] ?? '')));
    }

    if (($field = $field_of('title')) && ($title !== '')) {
        $add($field, mb_substr($title, 0, 255));
    }

    foreach (array('body', 'excerpt') as $part) {
        $field = $field_of($part);
        $html = (string) ($context[$part] ?? ($data[$part] ?? ''));

        if (!$field || (trim($html) === '')) {
            continue;
        }

        if (($field['type'] === 'text area') && !empty($field['wysiwyg'])) {
            $add($field, $html, 'html');
        } elseif (in_array($field['type'], array('text area', 'text box'), true)) {
            $text = pg_import_record_plain_text($html);

            if ($text !== '') {
                $add($field, ($field['type'] === 'text box') ? trim(preg_replace('/\s+/u', ' ', $text)) : $text);
            }
        }
    }

    $time = (int) ($data['time'] ?? 0);

    if (($field = $field_of('date')) && ($time > 0)) {
        if ($field['type'] === 'date') {
            $add($field, date('Y-m-d', $time), 'date');
        } elseif ($field['type'] === 'date and time') {
            $add($field, date('Y-m-d H:i', $time), 'date and time');
        } elseif ($field['type'] === 'text box') {
            $add($field, date('Y-m-d H:i', $time));
        }
    }

    if (($field = $field_of('picture')) && ($field['type'] === 'file upload') && ((int) ($context['picture_id'] ?? 0) > 0)) {
        $add($field, '', 'standard', (int) $context['picture_id']);
    }

    if ((string) ($context['picture_note'] ?? '') !== '') {
        $notes[] = (string) $context['picture_note'];
    }

    // Categories and tags may share one field: its names are gathered first.
    $terms = array();

    foreach (array('categories', 'tags') as $part) {
        if ($field = $field_of($part)) {
            $terms[$field['id']] = array_merge($terms[$field['id']] ?? array(), (array) ($data[$part] ?? array()));
        }
    }

    foreach ($terms as $field_id => $names) {
        $field = $fields[$field_id];
        $found = pg_import_record_terms($names, $field);

        if ($found['missing']) {
            $notes[] = lang(array('string' => 'No choice of {var:1} matches: {var:2}', 'vars' => array((string) $field['name'], implode(', ', $found['missing']))));
        }

        if (!$found['values']) {
            continue;
        }

        $one_row_each = ($field['type'] === 'check box') || (($field['type'] === 'pick list') && !empty($field['multiple']));

        if ($one_row_each) {
            foreach ($found['values'] as $value) {
                $add($field, $value);
            }
        } elseif (in_array($field['type'], array('pick list', 'radio button'), true)) {
            $add($field, $found['values'][0]);
        } elseif (in_array($field['type'], array('text box', 'text area'), true)) {
            $add($field, implode(', ', $found['values']));
        }
    }

    // A collection file's cells, each in the field paired with its column.
    foreach ((array) ($data['columns'] ?? array()) as $column => $cell) {
        if ((strpos((string) $column, 'col_') !== 0) || !($field = $field_of((string) $column))) {
            continue;
        }

        $built = pg_import_record_column_rows((string) $cell, $field, (array) ($context['columns'][$column] ?? array()));

        foreach ($built['rows'] as $row) {
            $add($field, $row['data'], $row['type'], $row['file_id']);
        }

        $notes = array_merge($notes, $built['notes']);
    }

    return array('rows' => $rows, 'notes' => $notes);
}

/**
 * Whether a cell holds HTML rather than plain text: an opening or closing
 * tag. Pure.
 *
 * @param string $cell
 * @return bool
 */
function pg_import_record_cell_is_html($cell)
{
    return (bool) preg_match('#</?[a-z][a-z0-9]*(\s[^<>]*)?/?>#i', (string) $cell);
}

/**
 * The names an option cell lists: a JSON array of names, else the cell cut
 * at semicolons, else at commas. A field that takes one choice reads the
 * whole cell. Pure.
 *
 * @param string $cell
 * @param bool   $one
 * @return string[]
 */
function pg_import_record_cell_names($cell, $one)
{
    $cell = trim((string) $cell);
    $list = (substr($cell, 0, 1) === '[') ? json_decode($cell, true) : null;

    if (is_array($list)) {
        $names = array();

        foreach ($list as $entry) {
            if (is_scalar($entry)) {
                $names[] = (string) $entry;
            }
        }

        return $one ? array_slice($names, 0, 1) : $names;
    }

    if ($one) {
        return array($cell);
    }

    return preg_split((strpos($cell, ';') !== false) ? '/\s*;\s*/' : '/\s*,\s*/', $cell);
}

/**
 * The first web address of a cell (a picture, a list of pictures, a Wix
 * gallery's JSON), '' when it has none. Pure.
 *
 * @param string $cell
 * @return string
 */
function pg_import_record_cell_address($cell)
{
    return preg_match('#https?://[^\s"\'<>,;\]]+#i', (string) $cell, $match) ? $match[0] : '';
}

/**
 * The form_data rows one cell of a collection file becomes in its paired
 * field. Pure: a rich text area's sanitised HTML (html) and a file upload's
 * fetched file (file_id, note) come prepared in $prepared.
 *
 * - Rich text area: the prepared HTML, else a plain cell as escaped
 *   paragraphs. Other text area: plain text; text box: one line.
 * - E-mail address: the address when it is one, else a note.
 * - Date, date and time: the cell read as a time, else a note.
 * - File upload: the fetched file, else the fetch's note.
 * - Check box, pick list, radio button: the choices matching the cell's
 *   names by label or value; a lone check box takes a true cell.
 *
 * @param string $cell
 * @param array  $field    pg_import_record_form_fields() row
 * @param array  $prepared html, file_id, note
 * @return array{rows:array[],notes:string[]}
 */
function pg_import_record_column_rows($cell, $field, $prepared)
{
    $cell = trim((string) $cell);
    $rows = array();
    $notes = array();
    $type = (string) ($field['type'] ?? '');
    $name = (string) ($field['name'] ?? '');
    $add = function ($value, $row_type = 'standard', $file_id = 0) use (&$rows) {
        $rows[] = array('data' => (string) $value, 'type' => $row_type, 'file_id' => (int) $file_id);
    };

    if ($cell === '') {
        return array('rows' => $rows, 'notes' => $notes);
    }

    if (($type === 'text area') && !empty($field['wysiwyg'])) {
        $html = (string) ($prepared['html'] ?? '');

        if ($html === '') {
            $html = pg_import_record_cell_is_html($cell) ? '' : '<p>' . str_replace(array("\r\n", "\r", "\n"), '<br>', htmlspecialchars($cell, ENT_QUOTES, 'UTF-8')) . '</p>';
        }

        if (trim($html) !== '') {
            $add($html, 'html');
        }
    } elseif (in_array($type, array('text area', 'text box'), true)) {
        $text = pg_import_record_cell_is_html($cell) ? pg_import_record_plain_text($cell) : $cell;
        $text = ($type === 'text box') ? trim(preg_replace('/\s+/u', ' ', $text)) : $text;

        if ($text !== '') {
            $add($text);
        }
    } elseif ($type === 'email address') {
        if (filter_var($cell, FILTER_VALIDATE_EMAIL) !== false) {
            $add($cell);
        } else {
            $notes[] = lang(array('string' => '{var:1} is not an e-mail address: {var:2}', 'vars' => array($name, $cell)));
        }
    } elseif (in_array($type, array('date', 'date and time'), true)) {
        // A date keeps the day the cell names; a date and time is shown in
        // the site's zone.
        $time = pg_import_collection_time($cell);
        $value = ($type === 'date') ? pg_import_collection_date($cell) : (($time > 0) ? date('Y-m-d H:i', $time) : '');

        if ($value !== '') {
            $add($value, $type);
        } else {
            $notes[] = lang(array('string' => '{var:1} is not a date: {var:2}', 'vars' => array($name, $cell)));
        }
    } elseif ($type === 'file upload') {
        if ((int) ($prepared['file_id'] ?? 0) > 0) {
            $add('', 'standard', (int) $prepared['file_id']);
        } elseif ((string) ($prepared['note'] ?? '') !== '') {
            $notes[] = (string) $prepared['note'];
        }
    } elseif (in_array($type, array('check box', 'pick list', 'radio button'), true)) {
        $options = (array) ($field['options'] ?? array());
        $many = ($type === 'check box') || (($type === 'pick list') && !empty($field['multiple']));

        // A lone check box (an "On" switch) is ticked by a true cell.
        if (($type === 'check box') && (count($options) === 1) && in_array(mb_strtolower($cell), array('true', 'false', 'yes', 'no'), true)) {
            if (in_array(mb_strtolower($cell), array('true', 'yes'), true)) {
                $add((string) key($options));
            }

            return array('rows' => $rows, 'notes' => $notes);
        }

        $found = pg_import_record_terms(pg_import_record_cell_names($cell, !$many), $field);

        if ($found['missing']) {
            $notes[] = lang(array('string' => 'No choice of {var:1} matches: {var:2}', 'vars' => array($name, implode(', ', $found['missing']))));
        }

        foreach ($many ? $found['values'] : array_slice($found['values'], 0, 1) as $value) {
            $add($value);
        }
    }

    return array('rows' => $rows, 'notes' => $notes);
}

/**
 * The fields of a form as the row builder reads them, with the choices of
 * an option field (value => label) or a reference field (code => label).
 *
 * @param int $page_id
 * @return array field id => row
 */
function pg_import_record_form_fields($page_id)
{
    $page_id = (int) $page_id;
    $kinds = pg_coll_field_kinds($page_id);
    $fields = array();

    foreach ((array) db_items("SELECT id, name, type, wysiwyg, multiple FROM form_fields WHERE page_id = '" . $page_id . "' ORDER BY sort_order, id") as $row) {
        $id = (int) $row['id'];
        $kind = $kinds[$id] ?? array('kind' => '', 'reference_page_id' => 0);
        $options = null;

        if ($kind['kind'] !== '') {
            $options = pg_coll_kind_options(array('kind' => $kind['kind'], 'reference_page_id' => $kind['reference_page_id']));
        } elseif (in_array($row['type'], array('pick list', 'radio button', 'check box'), true)) {
            $options = array();

            foreach ((array) db_items("SELECT label, value FROM form_field_options WHERE form_field_id = '" . $id . "' ORDER BY sort_order, id") as $option) {
                $options[(string) $option['value']] = (string) $option['label'];
            }
        }

        $fields[$id] = array(
            'id'       => $id,
            'name'     => (string) $row['name'],
            'type'     => (string) $row['type'],
            'wysiwyg'  => (int) $row['wysiwyg'],
            'multiple' => (int) $row['multiple'],
            'kind'     => $kind['kind'],
            'options'  => is_array($options) ? $options : null,
        );
    }

    return $fields;
}

/**
 * The account a post belongs to: the author's account an import of this
 * export made (still there), else 0.
 *
 * @param array $item
 * @param int[] $related
 * @return int
 */
function pg_import_record_author_id($item, $related)
{
    $key = pg_import_record_author_key($item);
    $user_id = ($key !== '') ? pg_import_item_id($related, 'user', $key) : 0;

    if (($user_id > 0) && ((int) db_value("SELECT COUNT(*) FROM user WHERE user_id = '" . $user_id . "'") > 0)) {
        return $user_id;
    }

    return 0;
}

/**
 * @param array $item
 * @param array $import pg_import_get()
 * @return array{result:string,id:int,reason:string}
 */
function pg_import_write_record($item, $import)
{
    $mapping = (array) ($import['mapping'] ?? array());
    $page_id = (int) ($mapping['form'] ?? 0);

    if (($page_id <= 0) || ((int) db_value("SELECT COUNT(*) FROM custom_form_pages WHERE page_id = '" . $page_id . "'") === 0)) {
        return array('result' => 'failed', 'id' => 0, 'reason' => lang('The form chosen for the posts is not there.'));
    }

    $withheld = pg_import_record_withheld($item);

    if ($withheld !== '') {
        return array('result' => 'skipped', 'id' => 0, 'reason' => $withheld);
    }

    $data = (array) ($item['data'] ?? array());
    $related = pg_import_related_ids($import);
    $fields = pg_import_record_form_fields($page_id);
    $media_notes = array();
    $context = array();

    foreach (array('body', 'excerpt') as $part) {
        $html = (string) ($data[$part] ?? '');

        if ((trim($html) === '') || ((int) ($mapping[$part] ?? 0) <= 0)) {
            continue;
        }

        if ($part === 'body') {
            $local = pg_import_media_localize($import, $related, $html);
            $html = $local['html'];
            $media_notes = $local['notes'];
        }

        $context[$part] = pg_sanitize_rich_text($html, 'staff');
    }

    // The featured image: the attachment's file when it came before the
    // post, else the record waits for it.
    $picture_key = (string) ($data['picture'] ?? '');
    $picture_wait = false;

    if (($picture_key !== '') && ((int) ($mapping['picture'] ?? 0) > 0)) {
        $file_id = pg_import_item_id($related, 'file', $picture_key);

        if (($file_id > 0) && ((int) db_value("SELECT COUNT(*) FROM files WHERE id = '" . $file_id . "'") > 0)) {
            $context['picture_id'] = $file_id;
        } else {
            $picture_wait = true;
        }
    }

    // A collection file's cells: rich text through the media fetch and the
    // cleaner, a picture fetched into the file library.
    foreach ((array) ($data['columns'] ?? array()) as $column => $cell) {
        $field_id = (strpos((string) $column, 'col_') === 0) ? (int) ($mapping[$column] ?? 0) : 0;
        $field = ($field_id > 0) ? ($fields[$field_id] ?? null) : null;

        if (!$field) {
            continue;
        }

        if (($field['type'] === 'text area') && !empty($field['wysiwyg']) && pg_import_record_cell_is_html((string) $cell)) {
            $local = pg_import_media_localize($import, $related, (string) $cell);
            $media_notes = array_merge($media_notes, $local['notes']);
            $context['columns'][$column] = array('html' => pg_sanitize_rich_text($local['html'], 'staff'));
        } elseif ($field['type'] === 'file upload') {
            $address = pg_import_record_cell_address((string) $cell);
            $fetched = ($address !== '') ? pg_import_media_fetch($import, $related, $address) : array('ok' => false, 'id' => 0, 'reason' => lang('The address is not a web address.'));
            $context['columns'][$column] = $fetched['ok']
                ? array('file_id' => (int) $fetched['id'])
                : array('note' => lang(array('string' => '{var:1} was not fetched: {var:2}', 'vars' => array((string) $field['name'], (string) $fetched['reason']))));
        }
    }

    $context['picture_note'] = pg_import_media_notes_line($media_notes);
    $built = pg_import_record_rows($item, $mapping, $fields, $context);
    $time = (int) ($data['time'] ?? 0);
    $editor_id = (int) ($import['user_id'] ?? 0);

    $saved = db_q("INSERT INTO forms (page_id, complete, user_id, reference_code, submitted_timestamp, last_modified_user_id, last_modified_timestamp)
        VALUES (?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP())", array(
        (string) $page_id,
        pg_import_record_complete($item) ? '1' : '0',
        (string) pg_import_record_author_id($item, $related),
        (string) generate_form_reference_code(),
        (string) (($time > 0) ? $time : time()),
        (string) $editor_id,
    ));
    $form_id = $saved ? (int) mysqli_insert_id(db::$con) : 0;

    if ($form_id <= 0) {
        return array('result' => 'failed', 'id' => 0, 'reason' => lang('The record could not be saved.'));
    }

    foreach ($built['rows'] as $row) {
        db_q("INSERT INTO form_data (form_id, form_field_id, file_id, data, name, type) VALUES (?, ?, ?, ?, ?, ?)", array(
            (string) $form_id, (string) $row['field_id'], (string) $row['file_id'], $row['data'], $row['name'], $row['type'],
        ));
    }

    if ($picture_wait) {
        db("INSERT IGNORE INTO import_items SET
            import_id = '" . (int) $import['id'] . "',
            kind = 'record',
            item_id = '" . $form_id . "',
            source_key = '" . e(mb_substr(PG_IMPORT_PICTURE_WAIT . $picture_key, 0, 191)) . "'");
    }

    // An incomplete record gets its address when it is completed.
    if (pg_import_record_complete($item) && check_if_pretty_urls_are_enabled($page_id)) {
        update_submitted_form_address_name($form_id);
    }

    // The job writes outside a signed-in request.
    if (function_exists('pg_page_cache_mark_dirty')) {
        pg_page_cache_mark_dirty();
    }

    return array('result' => 'made', 'id' => $form_id, 'reason' => implode(' ', array_filter($built['notes'])));
}

/**
 * Gives a record that waited for its featured image the file just fetched
 * for that attachment, in the mapping's picture field, and drops the wait.
 * Called by the file writer.
 *
 * @param array  $import
 * @param int[]  $related
 * @param string $file_key the attachment's source key
 * @param int    $file_id
 */
function pg_import_record_attach_picture($import, $related, $file_key, $file_id)
{
    $wait_key = mb_substr(PG_IMPORT_PICTURE_WAIT . $file_key, 0, 191);
    $form_id = pg_import_item_id($related, 'record', $wait_key);
    $field_id = (int) ($import['mapping']['picture'] ?? 0);

    if (($form_id <= 0) || ($field_id <= 0) || ((int) $file_id <= 0)) {
        return;
    }

    $field = db_item("SELECT form_fields.id, form_fields.name, form_fields.type
        FROM forms
        JOIN form_fields ON form_fields.page_id = forms.page_id
        WHERE forms.id = '" . $form_id . "' AND form_fields.id = '" . $field_id . "'");

    $taken = (int) db_value("SELECT COUNT(*) FROM form_data WHERE form_id = '" . $form_id . "' AND form_field_id = '" . $field_id . "'");

    if (is_array($field) && ($field['type'] === 'file upload') && ($taken === 0)) {
        db_q("INSERT INTO form_data (form_id, form_field_id, file_id, data, name, type) VALUES (?, ?, ?, '', ?, 'standard')", array(
            (string) $form_id, (string) $field_id, (string) (int) $file_id, (string) $field['name'],
        ));
    }

    db("DELETE FROM import_items
        WHERE import_id IN (" . implode(',', array_map('intval', $related)) . ") AND kind = 'record' AND source_key = '" . e($wait_key) . "'");
}

/**
 * Removes an imported record through the submitted forms screen's delete
 * path: the record, its values, signatures, view counts and its comment
 * thread. Its files are left alone: each one is an item of the import too
 * and goes with its own row, and a picture may also stand in a page or
 * another record. A record already gone (removed by hand, or listed twice
 * while it waited for its picture) is done.
 *
 * @param int $form_id
 * @return true
 */
function pg_import_unwrite_record($form_id)
{
    $form_id = (int) $form_id;

    if ((int) db_value("SELECT COUNT(*) FROM forms WHERE id = '" . $form_id . "'") === 0) {
        return true;
    }

    db_q("DELETE FROM forms WHERE id = ?", array((string) $form_id));
    db_q("DELETE FROM form_data WHERE (form_id = ?) AND (form_id != '0')", array((string) $form_id));
    pg_signature_delete($form_id);
    if (function_exists('pg_record_old_address_delete')) {
        pg_record_old_address_delete($form_id);
    }
    pg_sfv_delete_views('submitted_form_id', $form_id);
    pg_comment_delete_record_thread($form_id);

    if (function_exists('pg_page_cache_mark_dirty')) {
        pg_page_cache_mark_dirty();
    }

    return true;
}
