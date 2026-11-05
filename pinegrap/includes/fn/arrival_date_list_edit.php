<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The All Shipping Arrival Dates list (view_arrival_dates.php) and its
 * quick edit: every value the arrival date screen (edit_arrival_date.php)
 * changes and the list shows is a field, checked and written by that
 * screen's own code (includes/forms/arrival_date_form.php).
 *
 * Loaded where it is needed (require_once), not by functions.php; it needs
 * includes/forms/arrival_date_form.php and includes/fn/list_table.php.
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
 * The quick edit fields. Dates are typed in the site's date format, as the
 * screen takes them; the screen's own check (pg_arrival_date_form_errors())
 * decides what a date may be. Pure but for lang().
 *
 * @return array field => definition
 */
function pg_arrival_date_quick_edit_columns()
{
    return array(
        'name' => array('type' => 'text', 'label' => lang('Name'), 'max' => 50, 'required' => true),
        'arrival_date' => array('type' => 'text', 'label' => lang('Arrival Date'), 'required' => true),
        'status' => array('type' => 'select', 'label' => lang('Status'), 'options' => array('enabled' => lang('Enabled'), 'disabled' => lang('Disabled'))),
        'start_date' => array('type' => 'text', 'label' => lang('Start Date'), 'required' => true),
        'end_date' => array('type' => 'text', 'label' => lang('End Date'), 'required' => true),
    );
}

/**
 * The list's columns; each one the screen changes is editable.
 *
 * @param bool $edit whether the list has quick edit
 * @return array key => spec, as pg_list_table_printed() takes them
 */
function pg_arrival_date_list_columns($edit)
{
    $columns = array(
        'name' => array('heading' => lang('Name'), 'editable' => 'text'),
        'arrival_date' => array('heading' => lang('Arrival Date'), 'kind' => 'date', 'editable' => 'text'),
        'status' => array('heading' => lang('Status'), 'kind' => 'badge', 'editable' => 'select'),
        'start_date' => array('heading' => lang('Start Date'), 'kind' => 'date', 'editable' => 'text'),
        'end_date' => array('heading' => lang('End Date'), 'kind' => 'date', 'editable' => 'text'),
        'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
    );

    if (!$edit) {
        foreach ($columns as $key => $column) {
            unset($columns[$key]['editable']);
        }
    }

    return $columns;
}

/**
 * The list's query, without its ORDER BY: every stored column the screen
 * posts back, with the last change's user.
 *
 * @param int $id one arrival date only, 0 for all
 * @return string
 */
function pg_arrival_date_list_sql($id = 0)
{
    return "SELECT
            arrival_dates.id,
            arrival_dates.name,
            arrival_dates.description,
            arrival_dates.code,
            arrival_dates.arrival_date,
            arrival_dates.status,
            arrival_dates.start_date,
            arrival_dates.end_date,
            arrival_dates.default_selected,
            arrival_dates.sort_order,
            arrival_dates.custom,
            arrival_dates.custom_maximum_arrival_date,
            user.user_username AS user,
            arrival_dates.timestamp
        FROM arrival_dates
        LEFT JOIN user ON arrival_dates.user = user.user_id"
        . (((int) $id > 0) ? " WHERE arrival_dates.id = '" . (int) $id . "'" : '');
}

/**
 * Whether a cell of this arrival date changes on the list: the arrival
 * date of one the customer chooses is not set, and the screen hides it.
 * Pure.
 *
 * @param string $field
 * @param array  $row pg_arrival_date_list_sql() row
 * @return bool
 */
function pg_arrival_date_quick_cell_editable($field, array $row)
{
    return !(($field === 'arrival_date') && ((string) ($row['custom'] ?? '') === '1'));
}

/**
 * The value a field opens with: the stored value, a date in the site's
 * date format as the screen shows it. Pure but for the site's date format.
 *
 * @param string $field a pg_arrival_date_quick_edit_columns() field
 * @param array  $row   pg_arrival_date_list_sql() row
 * @return string
 */
function pg_arrival_date_quick_value($field, array $row)
{
    if (in_array($field, array('arrival_date', 'start_date', 'end_date'), true)) {
        return pg_arrival_date_form_date_text($row[$field] ?? '');
    }

    if ($field === 'status') {
        return ((string) ($row['status'] ?? '') === 'enabled') ? 'enabled' : 'disabled';
    }

    return (string) ($row[$field] ?? '');
}

/**
 * What the edit screen would post for a change made on the list: the
 * stored arrival date as the screen opens it, with the one changed value
 * in its field; pg_arrival_date_form_errors() and
 * pg_arrival_date_form_values() read it. Pure but for the date format.
 *
 * @param array  $row   pg_arrival_date_list_sql() row
 * @param string $field
 * @param mixed  $value pg_list_edit_request()'s value
 * @return array post field => value
 */
function pg_arrival_date_quick_post(array $row, $field, $value)
{
    $post = array(
        'name' => (string) ($row['name'] ?? ''),
        'description' => (string) ($row['description'] ?? ''),
        'code' => (string) ($row['code'] ?? ''),
        'arrival_date' => pg_arrival_date_quick_value('arrival_date', $row),
        // The screen's switch posts 1 when on, nothing when off.
        'status' => (pg_arrival_date_quick_value('status', $row) === 'enabled') ? '1' : '',
        'start_date' => pg_arrival_date_quick_value('start_date', $row),
        'end_date' => pg_arrival_date_quick_value('end_date', $row),
        'default_selected' => ((string) ($row['default_selected'] ?? '') === '1') ? '1' : '',
        'sort_order' => (string) ($row['sort_order'] ?? ''),
        'custom' => ((string) ($row['custom'] ?? '') === '1') ? '1' : '',
        'custom_maximum_arrival_date' => pg_arrival_date_form_date_text($row['custom_maximum_arrival_date'] ?? ''),
    );

    switch ($field) {
        case 'status':
            $post['status'] = (((string) $value) === 'enabled') ? '1' : '';
            break;

        case 'name':
        case 'arrival_date':
        case 'start_date':
        case 'end_date':
            $post[$field] = trim((string) $value);
            break;
    }

    return $post;
}

/**
 * Why a change of the dates cannot be written on the list, '' when it
 * can: a stored shipping cut-off must stay between the start and the end
 * date. The screen drops such a cut-off on its save and says so; the list
 * shows no cut-offs, so it refuses instead of dropping one unseen. Pure.
 *
 * @param array $cutoffs the stored cut-offs' 'Y-m-d H:i:s'
 * @param array $post    pg_arrival_date_quick_post()
 * @return string
 */
function pg_arrival_date_quick_cutoff_refusal(array $cutoffs, array $post)
{
    $start = pg_date_input_iso($post['start_date'] ?? '') . ' 00:00:00';
    $end = pg_date_input_iso($post['end_date'] ?? '') . ' 23:59:59';

    foreach ($cutoffs as $cutoff) {
        if (((string) $cutoff < $start) || ((string) $cutoff > $end)) {
            return lang('A shipping cut-off of this arrival date falls outside these dates. Change the dates on the arrival date screen, where the cut-offs are.');
        }
    }

    return '';
}

/**
 * An arrival date's cells on the list, with their quick edit attributes.
 *
 * @param array $row pg_arrival_date_list_sql() row
 * @param array $quick_edit field => definition, empty for none
 * @param string $today 'Y-m-d'
 * @return array key => pg_list_cell()
 */
function pg_arrival_date_list_cells(array $row, array $quick_edit, $today)
{
    $id = (int) $row['id'];

    $attributes = function ($field, array $more = array()) use ($row, $quick_edit, $id) {
        if (!pg_arrival_date_quick_cell_editable($field, $row)) {
            return $more;
        }

        return $more + pg_list_edit_cell_attributes($quick_edit, $field, $id, pg_arrival_date_quick_value($field, $row));
    };

    // An unset date (AT-ONCE, or one the customer enters) is not 0/0/0000.
    $arrival_date = pg_arrival_date_form_date_text($row['arrival_date']);
    if (($arrival_date === '') && ($row['custom'] == 1)) {
        $arrival_date = lang('Chosen by the customer');
    }

    // An enabled arrival date outside its dates is not offered: the name
    // says Inactive under it, as the Status badge still reads Enabled.
    if (
        ($row['status'] == 'enabled')
        && ($row['start_date'] <= $today)
        && ($row['end_date'] >= $today)
    ) {
        $output_status_class = 'status_enabled';
        $output_state = '';
    } else {
        $output_status_class = 'status_disabled';
        $output_state = pg_list_status_word(lang('Inactive'));
    }

    $date = function ($field, $text) use ($row, $attributes) {
        return pg_list_cell(h($text), 'align-middle text-nowrap', $attributes($field, array('data-pg-sort-value' => (int) str_replace('-', '', (string) $row[$field]))));
    };

    $status = (string) $row['status'];

    return array(
        'name' => pg_list_cell('<a href="edit_arrival_date.php?id=' . $id . '" class="' . $output_status_class . '" data-pg-open>' . h($row['name']) . '</a>' . $output_state, 'align-middle', $attributes('name')),
        'arrival_date' => $date('arrival_date', $arrival_date),
        'status' => pg_list_cell('<span class="badge fg-white fw-light ' . (($status == 'enabled') ? 'theme-success' : 'theme-danger') . '">' . h(lang(ucwords($status))) . '</span>', 'align-middle', $attributes('status')),
        'start_date' => $date('start_date', pg_arrival_date_form_date_text($row['start_date'])),
        'end_date' => $date('end_date', pg_arrival_date_form_date_text($row['end_date'])),
        'last_modified' => pg_list_table_when_cell($row['timestamp'], (string) $row['user']),
    );
}

/**
 * The quick edit answer from an arrival date read again: the edited cell
 * and every other cell of the row (the name follows the status and the
 * dates, Last Modified every change). Pure.
 *
 * @param array  $cells pg_arrival_date_list_cells()
 * @param string $field the edited cell's column
 * @return array
 */
function pg_arrival_date_quick_saved(array $cells, $field)
{
    $view = function ($cell) {
        $out = array('html' => $cell['html'], 'class' => $cell['class']);

        if (isset($cell['attributes']['data-pg-edit-value'])) {
            $out['value'] = (string) $cell['attributes']['data-pg-edit-value'];
        }

        return $out;
    };

    $answer = array('ok' => true) + $view($cells[$field]);
    $answer['value'] = $answer['value'] ?? '';
    $answer['cells'] = array();

    foreach ($cells as $key => $cell) {
        if ($key !== $field) {
            $answer['cells'][$key] = $view($cell);
        }
    }

    return $answer;
}

/**
 * Writes one value changed on the list through the arrival date screen's
 * own check and save: the name required, the dates real, the end not
 * before the start, the arrival date set unless the customer chooses it;
 * a new start or end date keeps every stored shipping cut-off inside it.
 *
 * @param int    $id
 * @param string $field a pg_arrival_date_quick_edit_columns() field
 * @param mixed  $value pg_list_edit_request()'s value
 * @param array  $user  validate_user()
 * @param array  $quick_edit
 * @return array the answer for pg_list_edit_reply()
 */
function pg_arrival_date_quick_update($id, $field, $value, array $user, array $quick_edit)
{
    $refuse = function ($message) {
        return array('ok' => false, 'code' => 'invalid', 'message' => (string) $message);
    };

    $row = db_item(pg_arrival_date_list_sql($id));

    if (!$row) {
        return $refuse(lang('Record not found.'));
    }

    if (!pg_arrival_date_quick_cell_editable($field, $row)) {
        return $refuse(lang('This column cannot be changed here.'));
    }

    $post = pg_arrival_date_quick_post($row, $field, $value);

    $errors = pg_arrival_date_form_errors($post);

    if ($errors) {
        return $refuse(rtrim(trim((string) reset($errors)), '.') . '.');
    }

    if (in_array($field, array('start_date', 'end_date'), true)) {
        $refusal = pg_arrival_date_quick_cutoff_refusal((array) db_values("SELECT date_and_time FROM shipping_cutoffs WHERE arrival_date_id = '" . (int) $id . "'"), $post);

        if ($refusal !== '') {
            return $refuse($refusal);
        }
    }

    // One saved as the default takes the switch off every other one.
    if (!pg_store_arrival_date_save((int) $id, pg_arrival_date_form_values($post, $user['id']))) {
        return $refuse(lang('The value could not be saved.'));
    }

    log_activity(lang(array('string' => '{var:1} ({var:2}) was modified', 'vars' => array(lang('arrival date'), $post['name']))), $_SESSION['sessionusername'] ?? '');

    $row = db_item(pg_arrival_date_list_sql($id));

    if (!$row) {
        return array('ok' => true, 'value' => (string) $value);
    }

    return pg_arrival_date_quick_saved(pg_arrival_date_list_cells($row, $quick_edit, date('Y-m-d')), $field);
}
