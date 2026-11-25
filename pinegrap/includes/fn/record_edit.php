<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A collection item's fields corrected in place from the Page Design
 * Studio's Preview: which fields can be written there and with which
 * control, who may write them, and the save itself.
 *
 * The save goes the way the item's edit screen (edit_submitted_form.php)
 * writes a field: the same type and kind checks
 * (pg_submitted_form_check_field()), the same form_data rows
 * (pg_submitted_form_save_value()), the address name made again from the
 * Title or slug field, and a revision of the item with the source
 * 'designer' (includes/fn/record_revisions.php). It never touches a page's
 * tree: a record's save is apart from the design, its undo and Publish.
 *
 * A field someone else changed after the Preview read it is not written:
 * the caller sends, beside each new value, the value it started from, and a
 * field whose stored value is neither comes back as a conflict with both
 * values. Nothing is written while any field is in conflict.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/submitted_form_form.php');

// The liveform the save checks its values in; emptied before and after.
const PG_REC_EDIT_LIVEFORM = 'pg_record_edit_in_place';

// The most fields one save may carry.
const PG_REC_EDIT_MAX_FIELDS = 100;

/**
 * The control a field is edited with in place, '' when it is not edited in
 * place: 'text' (a text box, an e-mail address, a plain text area, a slug),
 * 'rich' (a formatted text area), 'number' (a number kind), 'date' (a date),
 * 'picture' (an image kind of a file upload). Choices, references, rows,
 * files other than pictures, times, signatures and information fields stay
 * on the item's own screen. Pure.
 *
 * @param array  $field  form_fields row: type, wysiwyg, multiple
 * @param string $kind   pg_coll_field_kinds() kind of the field, '' for none
 * @return string
 */
function pg_rec_edit_control($field, $kind = '')
{
    $type = (string) ($field['type'] ?? '');
    $kind = (string) $kind;

    if ($kind === 'image') {
        return ($type === 'file upload') ? 'picture' : '';
    }

    if (in_array($kind, array('reference', 'product', 'page', 'rows'), true)) {
        return '';
    }

    if ($kind === 'number') {
        return ($type === 'text box') ? 'number' : '';
    }

    switch ($type) {
        case 'text box':
        case 'email address':
            return 'text';
        case 'text area':
            return !empty($field['wysiwyg']) ? 'rich' : 'text';
        case 'date':
            return 'date';
    }

    return '';
}

/**
 * Whether a person may write an item's fields in place: whoever its edit
 * screen lets save it as the forms' manager, a Manager or above, or a user
 * with Manage Forms who may edit the folder of the item's form. A form
 * editor or a submitter, who reach the screen only through a site's form
 * item view page, does not. Pure.
 *
 * @param array $user         validate_user(): role, manage_forms
 * @param bool  $folder_edit  check_edit_access() of the form page's folder
 * @return bool
 */
function pg_rec_edit_may($user, $folder_edit)
{
    if (!is_array($user) || !isset($user['role'])) {
        return false;
    }

    return ((int) $user['role'] <= 2) || (!empty($user['manage_forms']) && $folder_edit);
}

/**
 * Whether a person's content changes wait for approval on this site
 * (includes/fn/content_proposals.php): the switch is on and they are a
 * content level (roles 2 and 3). Such a change is not written in place.
 * Pure.
 *
 * @param array $user
 * @param bool  $approval_on  pg_content_proposals_on()
 * @return bool
 */
function pg_rec_edit_waits($user, $approval_on)
{
    return $approval_on && is_array($user) && ((int) ($user['role'] ?? 0) >= 2);
}

/**
 * The sent changes as field id => array('value', 'base'), strings only, or
 * a string saying why they are refused. Each change is
 * array('field_id', 'value', 'base'); 'base' is the value the caller
 * started from. Pure.
 *
 * @param mixed $changes
 * @return array|string
 */
function pg_rec_edit_changes($changes)
{
    if (!is_array($changes) || !$changes) {
        return lang('No change was sent.');
    }

    if (count($changes) > PG_REC_EDIT_MAX_FIELDS) {
        return lang('Too many fields were sent.');
    }

    $out = array();

    foreach ($changes as $change) {
        $id = is_array($change) && isset($change['field_id']) && is_scalar($change['field_id']) ? (int) $change['field_id'] : 0;

        if (
            ($id <= 0)
            || !array_key_exists('value', $change) || !is_scalar($change['value'] ?? '')
            || !array_key_exists('base', $change) || !is_scalar($change['base'] ?? '')
        ) {
            return lang('Each change must be an object of {field_id, value, base}.');
        }

        $out[$id] = array('value' => (string) $change['value'], 'base' => (string) $change['base']);
    }

    return $out;
}

/**
 * What a save does with each sent field, against the stored values: a
 * field already holding the new value is left ('same'); one still holding
 * the value the caller started from is written ('write'); any other is a
 * conflict, someone having changed it meanwhile ('conflicts', with the
 * caller's value as 'mine' and the stored one as 'theirs'). A field with
 * no stored value reads as ''. Pure.
 *
 * @param array $changes  pg_rec_edit_changes()
 * @param array $current  field id => stored value
 * @return array write (id => value), same (ids), conflicts (id => mine, theirs)
 */
function pg_rec_edit_plan($changes, $current)
{
    $plan = array('write' => array(), 'same' => array(), 'conflicts' => array());

    foreach ($changes as $id => $change) {
        $stored = isset($current[$id]) ? (string) $current[$id] : '';

        if ($stored === $change['value']) {
            $plan['same'][] = (int) $id;
        } elseif ($stored === $change['base']) {
            $plan['write'][(int) $id] = $change['value'];
        } else {
            $plan['conflicts'][(int) $id] = array('mine' => $change['value'], 'theirs' => $stored);
        }
    }

    return $plan;
}

/**
 * A field's stored value as the in-place save compares and writes it: a
 * picture as its file id ('' for none), any other control as the data of
 * its first form_data row. Pure.
 *
 * @param string $control  pg_rec_edit_control()
 * @param array  $rows     the field's form_data rows in id order: data, file_id
 * @return string
 */
function pg_rec_edit_stored($control, $rows)
{
    if (!$rows) {
        return '';
    }

    $row = reset($rows);

    if ($control === 'picture') {
        return ((int) ($row['file_id'] ?? 0) > 0) ? (string) (int) $row['file_id'] : '';
    }

    return (string) ($row['data'] ?? '');
}

/**
 * The item a save or a read is for: its forms row with the page, folder and
 * name of its form; null when there is none or its form is not a
 * collection.
 *
 * @param int $form_id  forms.id
 * @return array|null
 */
function pg_rec_edit_record($form_id)
{
    $form_id = (int) $form_id;

    if (($form_id <= 0) || !pg_coll_ready()) {
        return null;
    }

    $row = db_item(
        "SELECT
            forms.id,
            forms.page_id,
            forms.reference_code,
            forms.address_name,
            forms.complete,
            page.page_folder AS folder_id,
            custom_form_pages.form_name
        FROM forms
        INNER JOIN page ON page.page_id = forms.page_id
        INNER JOIN custom_form_pages ON custom_form_pages.page_id = forms.page_id
        WHERE forms.id = '" . $form_id . "' AND custom_form_pages.collection = '1'
        LIMIT 1");

    return $row ?: null;
}

/**
 * The fields of an item's form that are edited in place, in form order,
 * each with its control and its stored value, and the picture's file name.
 *
 * @param array $record  pg_rec_edit_record()
 * @return array field id => row (id, name, label, type, wysiwyg, required,
 *               rss_field, control, value, file_name)
 */
function pg_rec_edit_fields($record)
{
    $page_id = (int) $record['page_id'];
    $kinds = pg_coll_field_kinds($page_id);
    $fields = array();

    foreach ((array) db_items(
        "SELECT id, name, label, type, wysiwyg, multiple, required, rss_field
        FROM form_fields
        WHERE page_id = '" . $page_id . "'
        ORDER BY sort_order ASC, id ASC") as $field
    ) {
        $control = pg_rec_edit_control($field, $kinds[(int) $field['id']]['kind'] ?? '');

        if ($control !== '') {
            $field['control'] = $control;
            $field['value'] = '';
            $field['file_name'] = '';
            $fields[(int) $field['id']] = $field;
        }
    }

    if (!$fields) {
        return array();
    }

    $rows = array();

    foreach ((array) db_items(
        "SELECT form_data.form_field_id, form_data.data, form_data.file_id, files.name AS file_name
        FROM form_data
        LEFT JOIN files ON files.id = form_data.file_id
        WHERE form_data.form_id = '" . (int) $record['id'] . "'
            AND form_data.form_field_id IN (" . implode(',', array_keys($fields)) . ")
        ORDER BY form_data.id ASC") as $row
    ) {
        $rows[(int) $row['form_field_id']][] = $row;
    }

    foreach ($fields as $id => $field) {
        $fields[$id]['value'] = pg_rec_edit_stored($field['control'], $rows[$id] ?? array());

        if (($field['control'] === 'picture') && ($fields[$id]['value'] !== '')) {
            $fields[$id]['file_name'] = (string) ($rows[$id][0]['file_name'] ?? '');
        }
    }

    return $fields;
}

/**
 * The fields as the Preview reads them: id, name, label, control, required,
 * value, file_name. Pure.
 *
 * @param array $fields  pg_rec_edit_fields()
 * @return array
 */
function pg_rec_edit_fields_out($fields)
{
    $out = array();

    foreach ($fields as $field) {
        $out[] = array(
            'id' => (int) $field['id'],
            'name' => (string) $field['name'],
            'label' => (string) $field['label'],
            'control' => $field['control'],
            'required' => !empty($field['required']),
            'value' => $field['value'],
            'file_name' => $field['file_name'],
        );
    }

    return $out;
}

/**
 * The item's title, as the foot bar names it: the first Title field's value,
 * else its reference code. Pure.
 *
 * @param array $record  pg_rec_edit_record()
 * @param array $fields  pg_rec_edit_fields()
 * @return string
 */
function pg_rec_edit_title($record, $fields)
{
    foreach ($fields as $field) {
        if (($field['rss_field'] === 'title') && (trim((string) $field['value']) !== '')) {
            return trim(strip_tags((string) $field['value']));
        }
    }

    return (string) $record['reference_code'];
}

/**
 * Whether the session user may write an item in place, and whether their
 * change would wait for approval.
 *
 * @param array $user
 * @param array $record  pg_rec_edit_record()
 * @return array may, waits
 */
function pg_rec_edit_rights($user, $record)
{
    $may = pg_rec_edit_may($user, ((int) ($user['role'] ?? 3) <= 2) || check_edit_access((int) $record['folder_id']));
    $approval_on = function_exists('pg_content_proposals_on') && pg_content_proposals_on();

    return array('may' => $may, 'waits' => $may && pg_rec_edit_waits($user, $approval_on));
}

/**
 * The item as the Preview reads it before an edit.
 *
 * @param array $user
 * @param int   $form_id
 * @return array status (ok, missing, denied), may, waits, record, fields
 */
function pg_rec_edit_read($user, $form_id)
{
    $record = pg_rec_edit_record($form_id);

    if (!$record) {
        return array('status' => 'missing', 'message' => lang('This item could not be found.'));
    }

    $rights = pg_rec_edit_rights($user, $record);

    if (!$rights['may']) {
        return array('status' => 'denied', 'message' => lang('Access denied.'));
    }

    $fields = pg_rec_edit_fields($record);

    return array(
        'status' => 'ok',
        'may' => true,
        'waits' => $rights['waits'],
        'record' => pg_rec_edit_record_out($record, $fields),
        'fields' => pg_rec_edit_fields_out($fields),
    );
}

/**
 * The item's own part of an answer. Pure.
 *
 * @param array $record
 * @param array $fields
 * @return array
 */
function pg_rec_edit_record_out($record, $fields)
{
    return array(
        'id' => (int) $record['id'],
        'reference_code' => (string) $record['reference_code'],
        'address_name' => (string) $record['address_name'],
        'title' => pg_rec_edit_title($record, $fields),
        'form_name' => (string) $record['form_name'],
    );
}

/**
 * Why a picture field cannot take a file, '' when it can: the file must be
 * in the files table, named as an image and in a folder the person may see.
 *
 * @param string $value  the file id, '' for none
 * @return string
 */
function pg_rec_edit_picture_refusal($value)
{
    if ($value === '') {
        return '';
    }

    if (!ctype_digit($value)) {
        return lang('Please choose an image (JPG, PNG, GIF or WebP).');
    }

    $file = db_item("SELECT name, folder FROM files WHERE id = '" . (int) $value . "' LIMIT 1");

    if (!$file || !pg_coll_image_name_ok((string) $file['name']) || !check_view_access((int) $file['folder'])) {
        return lang('Please choose an image (JPG, PNG, GIF or WebP).');
    }

    return '';
}

/**
 * Checks the values to write with the item edit screen's own checks.
 *
 * @param array $record  pg_rec_edit_record()
 * @param array $fields  pg_rec_edit_fields()
 * @param array $write   field id => value
 * @return array  field id => plain-text message; empty when all pass
 */
function pg_rec_edit_check($record, $fields, $write)
{
    $errors = array();
    $liveform = pg_rec_edit_liveform($fields, $write);
    $pretty_urls = check_if_pretty_urls_are_enabled($record['page_id']);

    foreach ($write as $id => $value) {
        $field = $fields[$id];
        $label = h(function_exists('pg_tr_label') ? pg_tr_label($field['label']) : $field['label']);

        if (!empty($field['required']) && (trim($value) === '')) {
            $liveform->mark_error($id, lang(array('string' => '{var:1} is required.', 'vars' => $label)));
            continue;
        }

        if ($field['control'] === 'picture') {
            $refusal = pg_rec_edit_picture_refusal($value);

            if ($refusal !== '') {
                $liveform->mark_error($id, h($refusal));
            }

            continue;
        }

        pg_submitted_form_check_field($liveform, $field, $record['page_id'], $pretty_urls, (string) $record['id']);
    }

    foreach ($liveform->field_error_texts() as $id => $text) {
        $errors[(int) $id] = ($text !== '') ? $text : lang('Please check this field.');
    }

    return array('errors' => $errors, 'liveform' => $liveform);
}

/**
 * A fresh liveform holding the item's Title and slug values as they will be
 * after the save (the address check reads them) and the values to write.
 *
 * @param array $fields
 * @param array $write
 * @return liveform
 */
function pg_rec_edit_liveform($fields, $write)
{
    unset($_SESSION['software']['liveforms'][PG_REC_EDIT_LIVEFORM]);

    $liveform = new liveform(PG_REC_EDIT_LIVEFORM);

    foreach ($fields as $id => $field) {
        if ($field['control'] !== 'picture') {
            $liveform->assign_field_value($id, array_key_exists($id, $write) ? $write[$id] : $field['value']);
        }
    }

    return $liveform;
}

/**
 * Writes one item's fields corrected in place.
 *
 * @param array $user     validate_user()
 * @param int   $form_id  forms.id
 * @param mixed $changes  list of array('field_id', 'value', 'base')
 * @return array status: saved, unchanged, conflict, invalid, approval,
 *               denied, missing, refused; message; record and fields after
 *               a save; conflicts (field_id, label, mine, theirs); errors
 *               (field_id, message)
 */
function pg_rec_edit_save($user, $form_id, $changes)
{
    $record = pg_rec_edit_record($form_id);

    if (!$record) {
        return array('status' => 'missing', 'message' => lang('This item could not be found.'));
    }

    $rights = pg_rec_edit_rights($user, $record);

    if (!$rights['may']) {
        return array('status' => 'denied', 'message' => lang('Access denied.'));
    }

    if ($rights['waits']) {
        return array('status' => 'approval', 'message' => lang('Content changes wait for approval on this site, so this item is not changed from the Preview.'));
    }

    $changes = pg_rec_edit_changes($changes);

    if (is_string($changes)) {
        return array('status' => 'refused', 'message' => $changes);
    }

    $fields = pg_rec_edit_fields($record);

    foreach (array_keys($changes) as $id) {
        if (!isset($fields[$id])) {
            return array('status' => 'refused', 'message' => lang('A field sent cannot be changed from the Preview.'), 'field_id' => $id);
        }
    }

    $current = array();

    foreach ($fields as $id => $field) {
        $current[$id] = $field['value'];
    }

    $plan = pg_rec_edit_plan($changes, $current);

    if ($plan['conflicts']) {
        $conflicts = array();

        foreach ($plan['conflicts'] as $id => $conflict) {
            $conflicts[] = array('field_id' => $id, 'label' => (string) $fields[$id]['label']) + $conflict;
        }

        return array(
            'status' => 'conflict',
            'message' => lang('Someone else changed this item meanwhile. Choose which value to keep.'),
            'conflicts' => $conflicts,
            'record' => pg_rec_edit_record_out($record, $fields),
            'fields' => pg_rec_edit_fields_out($fields),
        );
    }

    if (!$plan['write']) {
        return array('status' => 'unchanged', 'message' => '', 'record' => pg_rec_edit_record_out($record, $fields), 'fields' => pg_rec_edit_fields_out($fields));
    }

    $checked = pg_rec_edit_check($record, $fields, $plan['write']);

    if ($checked['errors']) {
        unset($_SESSION['software']['liveforms'][PG_REC_EDIT_LIVEFORM]);

        $errors = array();

        foreach ($checked['errors'] as $id => $text) {
            $errors[] = array('field_id' => $id, 'message' => $text);
        }

        return array('status' => 'invalid', 'message' => $errors[0]['message'], 'errors' => $errors);
    }

    pg_rec_edit_write($user, $record, $fields, $plan['write'], $checked['liveform']);

    unset($_SESSION['software']['liveforms'][PG_REC_EDIT_LIVEFORM]);

    $record = pg_rec_edit_record($record['id']) ?: $record;
    $fields = pg_rec_edit_fields($record);

    return array(
        'status' => 'saved',
        'message' => '',
        'record' => pg_rec_edit_record_out($record, $fields),
        'fields' => pg_rec_edit_fields_out($fields),
    );
}

/**
 * The rows of a checked save, in the edit screen's order: the item's state
 * for its revision, each field's form_data rows replaced, who changed it and
 * when, the address name made again, the revision, the activity log line and
 * the texts sent to the languages that translate on save.
 *
 * A picture is pointed at the chosen file; the file it pointed at before is
 * left where it is, as it may be a library file shown elsewhere.
 *
 * @param array    $user
 * @param array    $record
 * @param array    $fields
 * @param array    $write     field id => value
 * @param liveform $liveform  pg_rec_edit_check()'s, holding the checked values
 */
function pg_rec_edit_write($user, $record, $fields, $write, $liveform)
{
    $id = (int) $record['id'];
    $user_id = (int) $user['id'];

    $revision_before = pg_record_revision_before($id);

    foreach ($write as $field_id => $value) {
        $field = $fields[$field_id];

        db("DELETE FROM form_data WHERE form_id = '" . $id . "' AND form_field_id = '" . (int) $field_id . "'");

        if ($field['control'] === 'picture') {
            db("INSERT INTO form_data (form_id, form_field_id, file_id, name)
                VALUES ('" . $id . "', '" . (int) $field_id . "', '" . (int) $value . "', '" . e($field['name']) . "')");
        } else {
            pg_submitted_form_save_value($liveform, $id, $field);
        }
    }

    db("UPDATE forms SET last_modified_user_id = '" . $user_id . "', last_modified_timestamp = UNIX_TIMESTAMP()
        WHERE id = '" . $id . "' LIMIT 1");

    if (check_if_pretty_urls_are_enabled($record['page_id'])) {
        update_submitted_form_address_name($id);
    }

    pg_record_revision_after($id, $revision_before, 'designer', $user_id);

    log_activity(lang(array(
        'string' => 'submitted form (form name: {var:1}, reference code: {var:2}) was modified in the Page Design Studio',
        'vars' => array((string) $record['form_name'], (string) $record['reference_code']),
    )), (string) ($user['username'] ?? ''));

    if (!empty($record['complete']) && function_exists('pg_tr_records_saved')) {
        pg_tr_records_saved($record['page_id'], $user_id);
    }
}
