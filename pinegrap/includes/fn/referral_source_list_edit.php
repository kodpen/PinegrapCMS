<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The All Referral Sources list (view_referral_sources.php) and its quick
 * edit: every list value the referral source screen
 * (edit_referral_source.php) changes is a field - the name, the code and
 * the sort order - checked by that screen's own rules
 * (includes/forms/referral_source_form.php) and written by its save
 * (pg_store_referral_source_save()). Last Modified stays text.
 *
 * Loaded where it is needed (require_once), not by functions.php; it needs
 * includes/forms/referral_source_form.php and includes/fn/list_table.php.
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
 * The quick edit fields, with the screen's limits: a name and a code of at
 * most 50 characters, both required, and a sort order from 0 to 999 where a
 * blank box is 0.
 *
 * @return array field => definition
 */
function pg_referral_source_quick_edit_columns()
{
    return array(
        'name' => array('type' => 'text', 'label' => lang('Name'), 'max' => 50, 'required' => true),
        'code' => array('type' => 'text', 'label' => lang('Code'), 'max' => 50, 'required' => true),
        'sort_order' => array('type' => 'integer', 'label' => lang('Sort Order'), 'min' => 0, 'max' => 999, 'empty' => true),
    );
}

/**
 * The list's columns; each one the screen changes is editable.
 *
 * @param bool $edit whether the list has quick edit
 * @return array key => spec, as pg_list_table_printed() takes them
 */
function pg_referral_source_list_columns($edit)
{
    $columns = array(
        'name' => array('heading' => lang('Name'), 'editable' => 'text'),
        'code' => array('heading' => lang('Code'), 'editable' => 'text'),
        'sort_order' => array('heading' => lang('Sort Order'), 'kind' => 'number', 'editable' => 'integer'),
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
 * The list's query, without its ORDER BY.
 *
 * @param int $id one source only, 0 for all
 * @return string
 */
function pg_referral_source_list_sql($id = 0)
{
    return "SELECT
            referral_sources.id,
            referral_sources.name,
            referral_sources.code,
            referral_sources.sort_order,
            user.user_username AS user,
            referral_sources.timestamp
        FROM referral_sources
        LEFT JOIN user ON referral_sources.user = user.user_id"
        . (((int) $id > 0) ? " WHERE referral_sources.id = '" . (int) $id . "'" : '');
}

/**
 * A source's cells, drawn the one way the list and the answer to an edit
 * both use.
 *
 * @param array $source     pg_referral_source_list_sql() row
 * @param array $quick_edit pg_referral_source_quick_edit_columns(), empty without quick edit
 * @return array column => pg_list_cell()
 */
function pg_referral_source_list_cells(array $source, array $quick_edit)
{
    $id = (int) $source['id'];
    $sort_order = (string) (int) $source['sort_order'];

    return array(
        'name' => pg_list_cell('<a href="edit_referral_source.php?id=' . $id . '" data-pg-open>' . h($source['name']) . '</a>', 'align-middle', pg_list_edit_cell_attributes($quick_edit, 'name', $id, (string) $source['name'])),
        'code' => pg_list_cell(h($source['code']), 'align-middle', pg_list_edit_cell_attributes($quick_edit, 'code', $id, (string) $source['code'])),
        'sort_order' => pg_list_cell($sort_order, 'align-middle', pg_list_edit_cell_attributes($quick_edit, 'sort_order', $id, $sort_order)),
        'last_modified' => pg_list_table_when_cell($source['timestamp'], (string) $source['user']),
    );
}

/**
 * The source's values as the screen's checks read a post, with the edited
 * field replaced: an empty sort order is '' (stored as 0). Pure.
 *
 * @param array  $source pg_referral_source_list_sql() row
 * @param string $field
 * @param mixed  $value  pg_list_edit_request()'s value
 * @return array 'name', 'code', 'sort_order' as text
 */
function pg_referral_source_quick_values(array $source, $field, $value)
{
    $values = array(
        'name' => trim((string) $source['name']),
        'code' => trim((string) $source['code']),
        'sort_order' => (string) (int) $source['sort_order'],
    );
    $values[$field] = trim((string) $value);

    return $values;
}

/**
 * The quick edit answer from a source read again: the edited cell and every
 * other cell of the row, each with the value its field opens with.
 *
 * @param array  $cells pg_referral_source_list_cells()
 * @param string $field
 * @return array
 */
function pg_referral_source_quick_saved(array $cells, $field)
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
 * Writes one value changed on the list the way the referral source screen
 * saves: its checks (required name and code, sort order 0-999, no other
 * source with the same name or code), then pg_store_referral_source_save()
 * and the screen's Site Log line.
 *
 * @param int    $id
 * @param string $field a pg_referral_source_quick_edit_columns() field
 * @param mixed  $value pg_list_edit_request()'s value
 * @param array  $user  validate_user()
 * @param array  $quick_edit
 * @return array the answer for pg_list_edit_reply()
 */
function pg_referral_source_quick_update($id, $field, $value, array $user, array $quick_edit)
{
    $refuse = function ($message) {
        return array('ok' => false, 'code' => 'invalid', 'message' => (string) $message);
    };

    $source = db_item(pg_referral_source_list_sql($id));

    if (!$source) {
        return $refuse(lang('Record not found.'));
    }

    $values = pg_referral_source_quick_values($source, $field, $value);
    $others = pg_store_referral_source_others((int) $id);

    if ($others === false) {
        return $refuse(lang('The value could not be saved.'));
    }

    $error = pg_referral_source_form_error($values, $others);

    if ($error !== '') {
        return $refuse($error);
    }

    if (!pg_store_referral_source_save((int) $id, $values, (int) $user['id'])) {
        return $refuse(lang('The value could not be saved.'));
    }

    log_activity(lang(array('string' => 'referral source ({var:1}) was modified', 'vars' => array($values['name']))), $_SESSION['sessionusername'] ?? '');

    $row = db_item(pg_referral_source_list_sql($id));

    if (!$row) {
        return array('ok' => true, 'value' => (string) $value);
    }

    return pg_referral_source_quick_saved(pg_referral_source_list_cells($row, $quick_edit), $field);
}
