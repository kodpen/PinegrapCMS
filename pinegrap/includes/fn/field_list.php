<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The fields list of a form (view_fields.php: custom, shipping, billing and
 * product forms and a variant set's form template) printed whole on the
 * list table driver (browser mode, pg_list_table_printed()), and its quick
 * edit. The fields are printed in the form's order, and the Position column
 * keeps that order sortable. The checks and the side effects of a change are
 * the field screen's (edit_field.php, includes/forms/field_form.php).
 *
 * A field's type, its choices, information text and the settings that come
 * with a type change together on the field screen; a cell cannot hold them.
 * A field of a form drawn in the visual designer changes in the designer
 * only.
 *
 * Loaded where it is needed (require_once), not by functions.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/field_form.php');

/**
 * The RSS / search elements a custom form field can be (select_rss_field()).
 *
 * @return array value => label
 */
function pg_field_list_rss_options()
{
    $options = array('' => '');

    foreach (array('category', 'title', 'description', 'media') as $element) {
        $options[$element] = ucwords(lang($element));
    }

    return $options;
}

/**
 * The field columns that change in place: name, label and required, and for
 * a custom form the RSS element and Office Use Only.
 *
 * @param string $form_type custom, shipping, billing, product or product_group
 * @return array field => definition
 */
function pg_field_edit_columns($form_type)
{
    $columns = array(
        'name' => array('type' => 'text', 'label' => lang('Name'), 'max' => 100, 'required' => true),
        'label' => array('type' => 'text', 'label' => lang('Label'), 'max' => 255),
    );

    if ($form_type === 'custom') {
        $columns['rss_field'] = array('type' => 'select', 'label' => lang('RSS Element'), 'options' => pg_field_list_rss_options());
    }

    $columns['required'] = array('type' => 'select', 'label' => lang('Required'), 'options' => pg_field_list_yes_no());

    if ($form_type === 'custom') {
        $columns['office_use_only'] = array('type' => 'select', 'label' => lang('Office Use Only'), 'options' => pg_field_list_yes_no());
    }

    return $columns;
}

/**
 * The choices of a yes/no column.
 *
 * @return array
 */
function pg_field_list_yes_no()
{
    return array('1' => lang('Yes'), '0' => lang('No'));
}

/**
 * The fields list's columns. Position is the field's place in the form.
 *
 * @param string $form_type
 * @param array  $quick_edit field => definition, empty for none
 * @return array key => spec
 */
function pg_field_list_columns($form_type, array $quick_edit)
{
    $specs = array(
        'name' => array('heading' => lang('Name')),
        'position' => array('heading' => lang('Position'), 'kind' => 'number'),
        'label' => array('heading' => lang('Label / Information')),
    );

    if ($form_type === 'custom') {
        $specs['rss_field'] = array('heading' => lang('RSS Element'));
    }

    $specs['type'] = array('heading' => lang('Field Type'));
    $specs['required'] = array('heading' => lang('Required'), 'kind' => 'check');

    if ($form_type === 'custom') {
        $specs['office_use_only'] = array('heading' => lang('Office Use Only'), 'kind' => 'check');
    }

    $specs['last_modified'] = array('heading' => lang('Last Modified'), 'kind' => 'date');

    foreach ($quick_edit as $field => $definition) {
        if (isset($specs[$field])) {
            $specs[$field]['editable'] = (string) $definition['type'];
        }
    }

    return $specs;
}

/**
 * A field row's cells. The label of an information field is its rich text,
 * shown cut; it and the required mark (an information field is never
 * required) change on the field screen. A designer form's field shows its
 * e-mail variable under the name and changes nowhere here.
 *
 * @param array  $row        form_fields row with username
 * @param int    $position   the field's place in the form, from 1
 * @param string $url        the field screen's address
 * @param bool   $designer   whether the form was drawn in the visual designer
 * @param array  $quick_edit field => definition, empty for none
 * @return array key => pg_list_cell()
 */
function pg_field_list_cells(array $row, $position, $url, $designer, array $quick_edit)
{
    $id = (int) $row['id'];
    $information = ($row['type'] === 'information');
    $field_edit = $information ? array_diff_key($quick_edit, array('label' => 1, 'required' => 1)) : $quick_edit;
    $name = h($row['name']) . ($designer ? '<br><code class="user-select-all">^^' . h($row['name']) . '^^</code>' : '');
    $yes = function ($on) {
        return $on ? '<span class="material-icons" role="img" aria-label="' . h(lang('Yes')) . '">task_alt</span>' : '';
    };

    if ($information) {
        $text = pg_sanitize_rich_text($row['information'], 'staff');
        $label = '<span class="text-truncate overflow-hidden d-block" style="width:200px;max-width:100%;" title="' . h(pg_liveform_message_text($text)) . '">' . $text . '</span>';
    } else {
        $label = '<div class="text-wrap text-break" style="max-width:20rem;">' . h($row['label']) . '</div>';
    }

    $rss = pg_field_list_rss_options();
    $cells = array(
        'name' => pg_list_cell('<a href="' . h($url) . '" data-pg-open>' . $name . '</a>', 'align-middle', pg_list_edit_cell_attributes($field_edit, 'name', $id, $row['name'])),
        'position' => pg_list_cell(pg_format_number($position, 0), 'align-middle', array('data-pg-sort-value' => (int) $position)),
        'label' => pg_list_cell($label, 'align-middle', pg_list_edit_cell_attributes($field_edit, 'label', $id, $row['label'])),
        'rss_field' => pg_list_cell(h($rss[(string) $row['rss_field']] ?? ''), 'align-middle', pg_list_edit_cell_attributes($field_edit, 'rss_field', $id, $row['rss_field'])),
        'type' => pg_list_cell(h(get_field_type_name($row['type']))),
        'required' => pg_list_cell($yes(!empty($row['required'])), 'align-middle', pg_list_edit_cell_attributes($field_edit, 'required', $id, empty($row['required']) ? '0' : '1') + array('data-pg-sort-value' => empty($row['required']) ? 0 : 1)),
        'office_use_only' => pg_list_cell($yes(!empty($row['office_use_only'])), 'align-middle', pg_list_edit_cell_attributes($field_edit, 'office_use_only', $id, empty($row['office_use_only']) ? '0' : '1') + array('data-pg-sort-value' => empty($row['office_use_only']) ? 0 : 1)),
        'last_modified' => pg_list_table_when_cell($row['timestamp'], (string) ($row['username'] ?? '')),
    );

    foreach ($cells as $key => $cell) {
        if (isset($cell['attributes'])) {
            $cells[$key]['attributes'] = array_map('strval', $cell['attributes']);
        }
    }

    return $cells;
}

/**
 * The fields of one form, in the form's order.
 *
 * @param string $form_type_filter pg_field_form_filter()'s condition
 * @param int    $id               0 for every field, else that field only
 * @return array
 */
function pg_field_list_rows($form_type_filter, $id = 0)
{
    return db_items(
        "SELECT
            form_fields.id, form_fields.name, form_fields.rss_field, form_fields.label,
            form_fields.information, form_fields.type, form_fields.required,
            form_fields.office_use_only, form_fields.sort_order, form_fields.timestamp,
            user.user_username AS username
        FROM form_fields
        LEFT JOIN user ON form_fields.user = user.user_id
        WHERE (" . $form_type_filter . ")" . ($id ? " AND (form_fields.id = '" . (int) $id . "')" : "") . "
        ORDER BY form_fields.sort_order, form_fields.id") ?: array();
}

/**
 * Writes one field value changed on the list, checked as the field screen
 * checks it, then does what its save does after: the page or product is
 * marked changed, a variant set's template is written to its products, and
 * a custom form field made the title renames the submitted forms' addresses.
 *
 * @param int    $id
 * @param string $field   a pg_field_edit_columns() field
 * @param mixed  $value   pg_list_edit_parse()'s value
 * @param array  $context form_type, filter (pg_field_form_filter()), page_id,
 *                        product_id, product_group_id, user_id, form_name,
 *                        url (the field screen's address up to the id)
 * @return array the answer pg_list_edit_reply() sends
 */
function pg_field_quick_update($id, $field, $value, array $context)
{
    $quick_edit = pg_field_edit_columns($context['form_type']);
    $rows = pg_field_list_rows($context['filter'], $id);
    $row = $rows ? $rows[0] : null;

    if (!$row || !isset($quick_edit[$field])) {
        return array('ok' => false, 'code' => 'invalid', 'message' => lang('Record not found.'));
    }

    $value = (string) $value;

    if (($row['type'] === 'information') && in_array($field, array('label', 'required'), true)) {
        return array('ok' => false, 'code' => 'invalid', 'message' => lang('This column cannot be changed here.'));
    }

    if ($field === 'name') {
        if (pg_field_form_name_has_reserved_chars($value)) {
            return array('ok' => false, 'code' => 'invalid', 'message' => lang('The field name cannot contain the following special characters') . ': ^ & [ ] < > /');
        }

        if (pg_field_form_name_taken($value, pg_field_form_names_in_use($context['filter'], $id, $value), $row['name'])) {
            return array('ok' => false, 'code' => 'invalid', 'message' => lang('Another field of this form already has this name. Please type in a different name.'));
        }
    }

    db_q("UPDATE form_fields SET `" . $field . "` = ?, user = ?, timestamp = UNIX_TIMESTAMP() WHERE id = ?",
        array($value, (string) (int) $context['user_id'], (string) (int) $id)) or output_error('Query failed.');

    if (($field === 'rss_field') && ($value === 'title') && ($row['rss_field'] !== 'title') && check_if_pretty_urls_are_enabled($context['page_id'])) {
        update_multiple_submitted_form_address_names($context['page_id']);
    }

    pg_field_form_touch_owner($context['form_type'], $context['user_id'], $context['product_id'], $context['page_id']);

    if ($context['form_type'] === 'product_group') {
        include_once(PG_FUNCTIONS_DIR . '/product_builder.php');
        pg_pb_apply_form_template((int) $context['product_group_id']);
    }

    log_activity(lang(array('string' => '{var:1} field ({var:2}) on {var:1} ({var:3}) was modified', 'vars' => array((pg_field_form_type_name($context['form_type']) ?: 'product form'), ($field === 'name') ? $value : $row['name'], (string) ($context['form_name'] ?? '')))), $_SESSION['sessionusername'] ?? '');

    $rows = pg_field_list_rows($context['filter'], $id);
    $cells = pg_field_list_cells($rows[0], 0, $context['url'] . (int) $id, false, $quick_edit);
    $cell = $cells[$field];
    unset($cells[$field], $cells['position']);

    return array(
        'ok' => true,
        'value' => (string) ($cell['attributes']['data-pg-edit-value'] ?? ''),
        'html' => $cell['html'],
        'class' => $cell['class'],
        'cells' => array_map(function ($other) {
            return array('html' => $other['html'], 'class' => $other['class']);
        }, $cells),
    );
}
