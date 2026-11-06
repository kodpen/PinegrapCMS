<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The All States list (view_states.php) and its quick edit: every value the
 * state screen (edit_state.php) changes is a field (name, code, country),
 * checked with that screen's own form rules (includes/forms/state_form.php)
 * and written through its write path (pg_store_state_save()). Last
 * Modified stays text.
 *
 * Loaded where it is needed (require_once), not by functions.php; it needs
 * includes/forms/state_form.php and includes/fn/list_table.php.
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
 * The countries the Country field offers, as id => name on screen, in the
 * panel language's order, a shared name carrying its code: the choices of
 * the state screen's picker (select_country()).
 *
 * @return array
 */
function pg_state_quick_countries()
{
    $rows = array();

    foreach ((array) db_items("SELECT id, name, code FROM countries") as $row) {
        $row['label'] = pg_geo_country_label($row['name'], $row['code']);
        $rows[] = $row;
    }

    $choices = array();

    foreach (pg_geo_sort_by_label(pg_geo_label_disambiguate($rows)) as $row) {
        $choices[(string) (int) $row['id']] = (string) $row['label'];
    }

    return $choices;
}

/**
 * The quick edit fields, keyed by the list's column. The lengths are the
 * screen's maxlength. Pure but for lang().
 *
 * @param array $countries pg_state_quick_countries()
 * @return array field => definition
 */
function pg_state_edit_columns(array $countries = array())
{
    return array(
        'name' => array('type' => 'text', 'label' => lang('Name'), 'max' => 50, 'required' => true),
        'code' => array('type' => 'text', 'label' => lang('Code'), 'max' => 50, 'required' => true),
        'country' => array('type' => 'select', 'label' => lang('Country'), 'options' => $countries),
    );
}

/**
 * The list's columns; without quick edit none is editable.
 *
 * @param bool $edit
 * @return array key => spec, as pg_list_table_printed() takes them
 */
function pg_state_list_columns($edit)
{
    $columns = array(
        'name' => array('heading' => lang('Name'), 'editable' => 'text'),
        'code' => array('heading' => lang('Code'), 'editable' => 'text'),
        'country' => array('heading' => lang('Country'), 'editable' => 'select'),
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
 * The value a field opens with: the stored name, code and country id.
 *
 * @param array  $state a row of pg_state_list_sql()
 * @param string $field
 * @return string
 */
function pg_state_quick_value($state, $field)
{
    return ($field === 'country') ? (string) (int) ($state['country_id'] ?? 0) : (string) ($state[$field] ?? '');
}

/**
 * One quick edit checked the way the state screen checks the same box, as
 * the values pg_store_state_save() writes: the stored state with the one
 * change through pg_state_form_values(), pg_state_form_required_errors(),
 * pg_state_form_code_error() (not another state's code in the country,
 * asked again when the country changes) and pg_state_form_country_error().
 *
 * @param array    $state  a row of pg_state_list_sql()
 * @param string   $field  a key of pg_state_edit_columns()
 * @param mixed    $value  as pg_list_edit_parse() read it
 * @param callable $taken  code lookup as pg_state_form_code_taken() (tests stub it)
 * @param callable $exists country lookup as pg_state_form_country_exists() (tests stub it)
 * @return array array('ok' => true, 'values' => name, code, country_id) or
 *               array('ok' => false, 'code', 'message')
 */
function pg_state_quick_change($state, $field, $value, $taken = 'pg_state_form_code_taken', $exists = 'pg_state_form_country_exists')
{
    $names = array('name' => 'name', 'code' => 'code', 'country' => 'country_id');

    if (!isset($names[$field])) {
        return array('ok' => false, 'code' => 'invalid', 'message' => lang('This column cannot be changed here.'));
    }

    $post = array(
        'name' => (string) ($state['name'] ?? ''),
        'code' => (string) ($state['code'] ?? ''),
        'country_id' => (string) ($state['country_id'] ?? ''),
    );
    $post[$names[$field]] = (string) $value;

    $values = pg_state_form_values($post);
    $errors = pg_state_form_required_errors($values);
    $code_error = pg_state_form_code_error($values, $state, (int) ($state['id'] ?? 0), $taken);

    if (($code_error !== '') && !isset($errors['code'])) {
        $errors['code'] = $code_error;
    }

    $country_error = pg_state_form_country_error($values['country_id'], $exists);

    if ($country_error !== '') {
        $errors['country_id'] = $country_error;
    }

    if ($errors) {
        return array('ok' => false, 'code' => 'invalid', 'message' => (string) reset($errors));
    }

    $values['country_id'] = (int) $values['country_id'];

    return array('ok' => true, 'values' => $values);
}

/**
 * The list's query, without its ORDER BY.
 *
 * @param int $id one state only, 0 for all
 * @return string
 */
function pg_state_list_sql($id = 0)
{
    return "SELECT
            states.id,
            states.name,
            states.code,
            states.country_id,
            countries.name as country_name,
            countries.code as country_code,
            user.user_username as user,
            states.timestamp
        FROM states
        LEFT JOIN countries ON states.country_id = countries.id
        LEFT JOIN user ON states.user = user.user_id"
        . (((int) $id > 0) ? "
        WHERE states.id = '" . (int) $id . "'" : '');
}

/**
 * A row with its country in the panel's language as 'country_label' ('' for
 * a state whose country is gone).
 *
 * @param array $row a row of pg_state_list_sql()
 * @return array
 */
function pg_state_list_labelled($row)
{
    $row['country_label'] = (($row['country_name'] ?? null) !== null) ? pg_geo_country_label($row['country_name'], $row['country_code']) : '';

    return $row;
}

/**
 * A state's cells, drawn the one way the list and the answer to an edit
 * both use. A cell is a field only when its column is in $quick_edit; the
 * Country cell of a state whose country the picker does not offer (gone)
 * stays text, as the screen asks for a country first.
 *
 * @param array $state      a row of pg_state_list_labelled()
 * @param array $quick_edit pg_state_edit_columns(), empty without quick edit
 * @return array key => pg_list_cell()
 */
function pg_state_list_cells($state, array $quick_edit)
{
    $id = (int) ($state['id'] ?? 0);

    $attributes = function ($field) use ($state, $quick_edit, $id) {
        if (($field === 'country') && !isset($quick_edit['country']['options'][pg_state_quick_value($state, 'country')])) {
            return array();
        }

        return pg_list_edit_cell_attributes($quick_edit, $field, $id, pg_state_quick_value($state, $field));
    };

    return array(
        'name' => pg_list_cell('<a href="edit_state.php?id=' . $id . '" data-pg-open>' . h((string) ($state['name'] ?? '')) . '</a>', 'align-middle', $attributes('name')),
        'code' => pg_list_cell(h((string) ($state['code'] ?? '')), 'align-middle', $attributes('code')),
        'country' => pg_list_cell(h((string) ($state['country_label'] ?? '')), 'align-middle', $attributes('country')),
        'last_modified' => pg_list_table_when_cell($state['timestamp'] ?? 0, (string) ($state['user'] ?? '')),
    );
}

/**
 * The quick edit answer from a state read again: the edited cell and every
 * other cell of the row (Last Modified follows every change), each with the
 * value its field opens with.
 *
 * @param array  $cells pg_state_list_cells()
 * @param string $field the edited cell's column
 * @return array
 */
function pg_state_quick_saved(array $cells, $field)
{
    $view = function ($cell) {
        $out = array('html' => (string) $cell['html'], 'class' => (string) $cell['class']);

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
 * Write one quick edit from the list the way the state screen writes the
 * same box: the screen's checks (pg_state_quick_change()), the shared write
 * (pg_store_state_save(), the last change stamped) and the screen's Site
 * Log line. The same value again writes nothing.
 *
 * @param int    $state_id
 * @param string $field      a key of $quick_edit
 * @param mixed  $value      as pg_list_edit_parse() read it
 * @param int    $user_id
 * @param array  $quick_edit pg_state_edit_columns()
 * @return array the answer for pg_list_edit_reply()
 */
function pg_state_quick_update($state_id, $field, $value, $user_id, array $quick_edit)
{
    $state_id = (int) $state_id;
    $state = db_item(pg_state_list_sql($state_id));

    if (!$state) {
        return array('ok' => false, 'code' => 'not_found', 'message' => lang('Record not found.'));
    }

    $change = pg_state_quick_change($state, $field, $value);

    if (!$change['ok']) {
        return $change;
    }

    $values = $change['values'];
    $changed = false;

    foreach ($values as $column => $new) {
        if ((string) $state[$column] !== (string) $new) {
            $changed = true;
        }
    }

    if ($changed) {
        if (pg_store_state_save($state_id, $values, (int) $user_id) <= 0) {
            return array('ok' => false, 'code' => 'invalid', 'message' => lang('The value could not be saved.'));
        }

        log_activity(lang(array('string' => '{var:1} ({var:2}) was modified', 'vars' => array(lang('state'), $values['name']))), $_SESSION['sessionusername'] ?? '');

        $state = db_item(pg_state_list_sql($state_id));
    }

    return pg_state_quick_saved(pg_state_list_cells(pg_state_list_labelled($state), $quick_edit), $field);
}
