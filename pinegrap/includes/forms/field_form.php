<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The form field form, shared by the field screens (add_field.php,
 * edit_field.php): the field type and settings cards, the name check, the
 * choices parser and the writes that follow a save (choices, trigger targets,
 * sort order, the form's last modified info and the custom layout warning).
 * The screens keep what differs: which form the field belongs to, the access
 * checks, the INSERT or UPDATE of the field row and the redirects.
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
 * The form type of a page's form: custom, shipping or billing.
 *
 * An express order page holds a shipping and a billing form; $express_form_type
 * ('shipping' or anything else) picks one of them.
 *
 * @param string $page_type
 * @param string $express_form_type
 * @return string  '' when the page type has no field form
 */
function pg_field_form_page_form_type($page_type, $express_form_type)
{
    switch ($page_type) {
        case 'custom form':
            return 'custom';

        case 'express order':
            return ($express_form_type == 'shipping') ? 'shipping' : 'billing';

        case 'shipping address and arrival':
            return 'shipping';

        case 'billing information':
            return 'billing';
    }

    return '';
}

/**
 * The name of a page form type as the field screens show it.
 *
 * @param string $form_type  custom, shipping or billing
 * @return string
 */
function pg_field_form_type_name($form_type)
{
    switch ($form_type) {
        case 'custom':
            return lang('custom form');

        case 'shipping':
            return lang('custom shipping form');

        case 'billing':
            return lang('custom billing form');
    }

    return '';
}

/**
 * The SQL condition that picks the fields of one form.
 *
 * Product group templates and express order forms pin form_type as well as the
 * id: a set's generated product copies carry the same product_group_id, and an
 * express order page holds two forms.
 *
 * @param string $column     page_id, product_id or product_group_id
 * @param string $id
 * @param string $page_type
 * @param string $form_type
 * @return string
 */
function pg_field_form_filter($column, $id, $page_type, $form_type)
{
    $filter = "form_fields." . $column . " = '" . e($id) . "'";

    if ($form_type == 'product_group') {
        $filter .= " AND form_fields.form_type = 'product_group'";
    } elseif ($page_type == 'express order') {
        $filter .= " AND form_fields.form_type = '" . e($form_type) . "'";
    }

    return $filter;
}

/**
 * The form name and quiz setting of a page form; the page name when the form
 * has no name of its own. Only a custom form has a quiz, and an express order
 * page keeps its form name on the billing form.
 *
 * @param string $page_type
 * @param string $form_type
 * @param string $page_id
 * @param string $page_name
 * @return array  array(form name, quiz)
 */
function pg_field_form_page_form_name($page_type, $form_type, $page_id, $page_name)
{
    $form_name = '';
    $quiz = '';

    $form_settings_table = pg_form_settings_table($page_type, $form_type);

    if (($form_settings_table !== '') && ($page_type != 'express order' or $form_type != 'shipping')) {
        $sql_quiz = ($form_type == 'custom') ? ", quiz" : "";

        $query = "SELECT form_name" . $sql_quiz . " FROM " . $form_settings_table . " WHERE page_id = ?";
        $result = db_q($query, array((string) $page_id)) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);

        $form_name = $row['form_name'] ?? '';
        $quiz = $row['quiz'] ?? '';
    }

    if (!$form_name) {
        $form_name = $page_name;
    }

    return array($form_name, $quiz);
}

/**
 * The form name of a product or variant set form: its own, else the short
 * description, else the product name.
 *
 * @param string $form_name
 * @param string $short_description
 * @param string $product_name
 * @return string
 */
function pg_field_form_product_form_name($form_name, $short_description, $product_name)
{
    if (($form_name == '') && ($short_description != '')) {
        return $short_description;
    }

    if (($form_name == '') && ($product_name != '')) {
        return $product_name;
    }

    return $form_name;
}

/**
 * The query string part that sends the user back to the right form of an
 * express order page.
 *
 * @param string $page_type
 * @param string $form_type
 * @return string
 */
function pg_field_form_express_query($page_type, $form_type)
{
    if ($page_type != 'express order') {
        return '';
    }

    return '&form_type=' . (($form_type == 'shipping') ? 'shipping' : 'billing');
}

/**
 * Does a field name hold a character that breaks field variables
 * (^^name^^) on list views and in e-mails?
 *
 * @param string $name
 * @return bool
 */
function pg_field_form_name_has_reserved_chars($name)
{
    foreach (array('^', '&', '[', ']', '<', '>', '/') as $character) {
        if (mb_strpos($name, $character) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * Marks the errors of a posted field name.
 *
 * @param liveform $liveform
 * @param string $name  the trimmed name
 */
function pg_field_form_validate_name($liveform, $name)
{
    if ($name == '') {
        $liveform->mark_error('name', lang('The field must have a name. Please type in a name for the field.'));
    }

    if (pg_field_form_name_has_reserved_chars($name)) {
        $liveform->mark_error('name', lang('The field name cannot contain the following special characters') . ': ^ &amp; [ ] &lt; &gt; /');
    }
}

/**
 * The required value a field is saved with: an information field is never
 * required.
 *
 * @param string $type
 * @param mixed $posted  the posted required checkbox
 * @return mixed
 */
function pg_field_form_required_value($type, $posted)
{
    return ($type == 'information') ? 0 : $posted;
}

/**
 * One line of the choices text, split into its parts:
 * label|value|on|target field=option,option|upload folder id ^^e-mail,e-mail^^
 *
 * @param string $line
 * @return array  label, value, default_selected (0/1), email_address (the
 *                valid addresses, ', ' separated), invalid_email (bool),
 *                trigger (the raw trigger part), upload_folder_id (the raw
 *                folder part)
 */
function pg_field_form_parse_option_line($line)
{
    $email_address_list = '';
    $invalid_email = false;

    // Conditional admin e-mail addresses, comma separated: ^^a@x.com,b@x.com^^
    if (preg_match('/\^\^(.*?)\^\^/', $line, $matches)) {
        foreach (explode(',', $matches[1]) as $email_address) {
            $email_address = trim($email_address);

            if (validate_email_address($email_address)) {
                if ($email_address_list != '') {
                    $email_address_list .= ', ';
                }

                $email_address_list .= $email_address;
            } else {
                $invalid_email = true;
            }
        }

        $line = str_replace($matches[0], '', $line);
    }

    $parts = explode('|', $line);
    $label = trim($parts[0]);

    return array(
        'label' => $label,
        // Without a value of its own a choice's value is its label.
        'value' => isset($parts[1]) ? trim($parts[1]) : $label,
        'default_selected' => (mb_strtolower(trim($parts[2] ?? '')) == 'on') ? 1 : 0,
        'email_address' => $email_address_list,
        'invalid_email' => $invalid_email,
        'trigger' => trim($parts[3] ?? ''),
        'upload_folder_id' => trim($parts[4] ?? ''),
    );
}

/**
 * A pick list trigger (target field=option,option) split into the target
 * field's name and its options.
 *
 * @param string $trigger
 * @return array  array(field name, array of options)
 */
function pg_field_form_parse_trigger($trigger)
{
    $parts = explode('=', $trigger);
    $options = array();

    foreach (explode(',', $parts[1] ?? '') as $option) {
        $options[] = trim($option);
    }

    return array(trim($parts[0]), $options);
}

/**
 * Writes the choices of a pick list, radio button or check box field from
 * the posted choices text, with their trigger targets.
 *
 * @param int|string $field_id
 * @param string $type            the field type
 * @param string $options_text    the posted choices, one per line
 * @param string $form_type_filter  pg_field_form_filter() of the field's form
 * @param array $option_columns   the form id columns written on every choice
 * @param string $target_column   the form id column of a trigger target
 * @param string $target_id       its value
 * @return array  array(an e-mail address was invalid, a trigger was invalid)
 */
function pg_field_form_save_options($field_id, $type, $options_text, $form_type_filter, $option_columns, $target_column, $target_id)
{
    $invalid_email_address = false;
    $invalid_trigger = false;

    if (($type != 'pick list') && ($type != 'radio button') && ($type != 'check box')) {
        return array($invalid_email_address, $invalid_trigger);
    }

    $count = 1;

    foreach (explode("\n", (string) $options_text) as $option_line) {
        $option = pg_field_form_parse_option_line($option_line);

        if ($option['invalid_email']) {
            $invalid_email_address = true;
        }

        $target_field_id = '';
        $target_options = array();

        // A pick list choice may show or hide the options of another pick list
        // on the same form.
        if (($type == 'pick list') && ($option['trigger'] != '')) {
            list($target_field_name, $target_options) = pg_field_form_parse_trigger($option['trigger']);

            if ($target_field_name != '') {
                $target_field_id = db_value(
                    "SELECT id
                    FROM form_fields
                    WHERE
                        ($form_type_filter)
                        AND (name = '" . e($target_field_name) . "')
                        AND (type = 'pick list')");

                if (($target_field_id == '') || (count($target_options) == 0)) {
                    $target_field_id = '';
                    $target_options = array();
                    $invalid_trigger = true;
                }
            } else {
                $target_options = array();
                $invalid_trigger = true;
            }
        }

        $upload_folder_id = '';

        // A pick list or radio button choice may send uploads to its own
        // folder, when the folder exists and the user may edit it.
        if (
            (($type == 'pick list') || ($type == 'radio button'))
            && ($option['upload_folder_id'] != '')
            && pg_field_form_folder_exists($option['upload_folder_id'])
            && (check_edit_access($option['upload_folder_id']) == true)
        ) {
            $upload_folder_id = $option['upload_folder_id'];
        }

        $columns = array();

        foreach ($option_columns as $column => $value) {
            $columns[$column] = (string) $value;
        }

        $columns['form_field_id'] = (string) $field_id;
        $columns['label'] = (string) $option['label'];
        $columns['value'] = (string) $option['value'];
        $columns['email_address'] = (string) $option['email_address'];
        $columns['default_selected'] = (string) $option['default_selected'];
        $columns['sort_order'] = (string) $count;
        $columns['target_form_field_id'] = (string) $target_field_id;
        $columns['upload_folder_id'] = (string) $upload_folder_id;

        db_q(
            "INSERT INTO form_field_options (" . implode(', ', array_keys($columns)) . ")
            VALUES (" . implode(', ', array_fill(0, count($columns), '?')) . ")",
            array_values($columns)) or output_error('Query failed.');

        $option_id = mysqli_insert_id(db::$con);

        foreach ($target_options as $target_option) {
            db_q(
                "INSERT INTO target_options (
                    $target_column,
                    trigger_form_field_id,
                    trigger_option_id,
                    value)
                VALUES (?, ?, ?, ?)",
                array((string) $target_id, (string) $field_id, (string) $option_id, (string) $target_option)) or output_error('Query failed.');
        }

        $count++;
    }

    return array($invalid_email_address, $invalid_trigger);
}

/**
 * Is there a folder with this id?
 *
 * @param string $folder_id
 * @return bool
 */
function pg_field_form_folder_exists($folder_id)
{
    $result = db_q("SELECT COUNT(*) FROM folder WHERE folder_id = ?", array((string) $folder_id)) or output_error('Query failed.');
    $row = mysqli_fetch_row($result);

    return $row && ($row[0] > 0);
}

/**
 * One stored choice as a line of the choices text (the format
 * pg_field_form_parse_option_line() reads). Each part is written only when it
 * or a later part has a value.
 *
 * @param array $option         label, value, default_selected,
 *                              target_form_field_name, upload_folder_id,
 *                              email_address
 * @param array $target_values  the options the trigger shows
 * @return string
 */
function pg_field_form_option_line($option, $target_values)
{
    $target_name = isset($option['target_form_field_name']) ? (string) $option['target_form_field_name'] : '';
    $has_folder = ($option['upload_folder_id'] != 0);

    $line = $option['label'];

    if (($option['value'] != $option['label']) || ($option['default_selected'] == 1) || ($target_name != '') || $has_folder) {
        $line .= '|' . $option['value'];
    }

    if (($option['default_selected'] == 1) || ($target_name != '') || $has_folder) {
        $line .= '|' . (($option['default_selected'] == 1) ? 'on' : '');
    }

    if (($target_name != '') || $has_folder) {
        $line .= '|';

        if ($target_name != '') {
            $line .= $target_name . '=' . implode(',', $target_values);
        }
    }

    if ($has_folder) {
        $line .= '|' . $option['upload_folder_id'];
    }

    if ($option['email_address'] != '') {
        $line .= ' ^^' . $option['email_address'] . '^^';
    }

    return $line;
}

/**
 * The stored choices of a field as the choices text, one line per choice,
 * each line starting with a new line as the edit screen always printed it.
 *
 * @param int $field_id
 * @param string $type
 * @return string
 */
function pg_field_form_options_text($field_id, $type)
{
    $sql_trigger_select = '';
    $sql_trigger_join = '';

    // Only a pick list has trigger targets.
    if ($type == 'pick list') {
        $sql_trigger_select = ", form_fields.name AS target_form_field_name";
        $sql_trigger_join = "LEFT JOIN form_fields ON form_field_options.target_form_field_id = form_fields.id";
    }

    $result = db_q(
        "SELECT
            form_field_options.id,
            form_field_options.label,
            form_field_options.value,
            form_field_options.email_address,
            form_field_options.upload_folder_id,
            form_field_options.default_selected
            $sql_trigger_select
        FROM form_field_options
        $sql_trigger_join
        WHERE form_field_options.form_field_id = ?
        ORDER BY form_field_options.sort_order",
        array((string) $field_id)) or output_error('Query failed.');

    $text = '';

    while ($option = mysqli_fetch_assoc($result)) {
        $target_values = array();

        if (isset($option['target_form_field_name']) && ($option['target_form_field_name'] != '')) {
            $targets = db_q("SELECT value FROM target_options WHERE trigger_option_id = ?", array((string) $option['id'])) or output_error('Query failed.');

            while ($target = mysqli_fetch_assoc($targets)) {
                $target_values[] = $target['value'];
            }
        }

        $text .= "\n" . pg_field_form_option_line($option, $target_values);
    }

    return $text;
}

/**
 * The field ids of a form in their new order, with $field_id placed after
 * $position ('top' for first).
 *
 * @param array $other_ids  the form's other field ids in their current order
 * @param string $field_id
 * @param string $position
 * @return array
 */
function pg_field_form_sorted_ids($other_ids, $field_id, $position)
{
    $fields = array();

    if ($position == 'top') {
        $fields[] = $field_id;
    }

    foreach ($other_ids as $id) {
        $fields[] = $id;

        if ($id == $position) {
            $fields[] = $field_id;
        }
    }

    return $fields;
}

/**
 * Renumbers the sort order of a form's fields with $field_id after $position.
 *
 * @param string $field_id
 * @param string $position
 * @param string $form_type_filter
 */
function pg_field_form_update_sort_order($field_id, $position, $form_type_filter)
{
    $other_ids = array();

    $query = "SELECT id
             FROM form_fields
             WHERE ($form_type_filter) AND (id != '" . e($field_id) . "')
             ORDER BY sort_order";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    while ($row = mysqli_fetch_assoc($result)) {
        $other_ids[] = $row['id'];
    }

    $count = 1;

    foreach (pg_field_form_sorted_ids($other_ids, $field_id, $position) as $id) {
        db_q("UPDATE form_fields SET sort_order = ? WHERE id = ?", array((string) $count, (string) $id)) or output_error('Query failed.');

        $count++;
    }
}

/**
 * Stamps the product or page a changed form belongs to as last modified by
 * the user. A variant set's form template belongs to neither.
 *
 * @param string $form_type
 * @param int|string $user_id
 * @param string $product_id
 * @param string $page_id
 */
function pg_field_form_touch_owner($form_type, $user_id, $product_id, $page_id)
{
    if ($form_type == 'product') {
        db_q(
            "UPDATE products SET user = ?, timestamp = UNIX_TIMESTAMP() WHERE id = ?",
            array((string) $user_id, (string) $product_id)) or output_error('Query failed.');
    } elseif ($form_type != 'product_group') {
        db_q(
            "UPDATE page SET page_timestamp = UNIX_TIMESTAMP(), page_user = ? WHERE page_id = ?",
            array((string) $user_id, (string) $page_id)) or output_error('Query failed.');
    }
}

/**
 * Warns that a modified custom layout may need editing after its form's
 * fields changed.
 *
 * @param liveform $liveform  the liveform of the screen the user lands on
 * @param string $page_type
 * @param string $page_id
 */
function pg_field_form_layout_warning($liveform, $page_type, $page_id)
{
    if (!check_if_page_type_supports_layout($page_type)) {
        return;
    }

    $result = db_q("SELECT layout_type, layout_modified FROM page WHERE page_id = ?", array((string) $page_id)) or output_error('Query failed.');
    $page = mysqli_fetch_assoc($result);

    if ($page && ($page['layout_type'] == 'custom') && $page['layout_modified']) {
        $liveform->add_warning(lang('You might need to edit the custom layout now, because you have made changes to fields on the custom form.'));
    }
}

/**
 * The notice after a save, with the choices that were dropped.
 *
 * @param string $notice
 * @param bool $invalid_email_address
 * @param bool $invalid_trigger
 * @return string
 */
function pg_field_form_saved_notice($notice, $invalid_email_address, $invalid_trigger)
{
    if ($invalid_email_address) {
        $notice .= lang(' However, there were one or more e-mail addresses entered for choices that were invalid, so they have been removed.');
    }

    if ($invalid_trigger) {
        if ($invalid_email_address) {
            $notice .= lang(' Also, there were one or more triggers entered for choices that were invalid, so they have been removed.');
        } else {
            $notice .= lang(' However, there were one or more triggers entered for choices that were invalid, so they have been removed.');
        }
    }

    return $notice;
}

/**
 * The values a new field's form starts with.
 *
 * @param string $folder_id  the form's folder, the default upload folder
 * @return array
 */
function pg_field_form_new_field($folder_id)
{
    return array(
        'id' => '',
        'name' => '',
        'rss_field' => '',
        'label' => '',
        'type' => '',
        'required' => 1,
        'default_value' => '',
        'use_folder_name_for_default_value' => 0,
        'size' => '',
        'maxlength' => '',
        'wysiwyg' => 0,
        'rows' => '',
        'cols' => '',
        'multiple' => 0,
        'spacing_above' => 0,
        'spacing_below' => 0,
        'contact_field' => '',
        'office_use_only' => 0,
        'translate_values' => 1,
        'upload_folder_id' => $folder_id,
        'quiz_question' => 0,
        'quiz_answer' => '',
        'information' => '',
        'options' => '',
    );
}

/**
 * ' checked="checked"' for a switched-on flag.
 *
 * @param mixed $value
 * @return string
 */
function pg_field_form_checked($value)
{
    return ($value == 1) ? ' checked="checked"' : '';
}

/**
 * The check box columns of the field form: a box that is not ticked is not
 * posted at all, so a sent-back form reads it as off.
 *
 * @return array
 */
function pg_field_form_check_box_columns()
{
    return array('required', 'wysiwyg', 'multiple', 'spacing_above', 'spacing_below',
        'use_folder_name_for_default_value', 'office_use_only', 'translate_values', 'quiz_question');
}

/**
 * The field form drawn from a sent-back post: each posted value is laid over
 * $field (a new or stored field), and a check box missing from the post is
 * off. A box the form did not show keeps the value of $field.
 *
 * @param array $field
 * @param array $posted  column => posted value, for the columns found in the post
 * @return array
 */
function pg_field_form_posted_field($field, $posted)
{
    $check_boxes = pg_field_form_check_box_columns();

    foreach ($check_boxes as $column) {
        $field[$column] = (isset($posted[$column]) && ($posted[$column] == 1)) ? 1 : 0;
    }

    foreach ($posted as $column => $value) {
        if (($column == 'id') || in_array($column, $check_boxes, true) || !array_key_exists($column, $field)) {
            continue;
        }

        if (is_array($value)) {
            $value = (string) reset($value);
        }

        $field[$column] = (string) $value;
    }

    return $field;
}

/**
 * The values of a field form sent back to its screen: column => value for
 * each field form column found in the liveform session, or null when the
 * form was not sent back (or was sent back for another field id).
 *
 * @param liveform $liveform
 * @param array $field  the new or stored field; its id must match the post
 * @return array|null
 */
function pg_field_form_session_values($liveform, $field)
{
    if ($liveform->prefill()) {
        return null;
    }

    if ((string) $field['id'] !== (string) $liveform->get_field_value('id')) {
        return null;
    }

    $posted = array();

    foreach (array_keys($field) as $column) {
        $value = $liveform->get_field_value($column);

        if ($value !== null) {
            $posted[$column] = $value;
        }
    }

    $position = $liveform->get_field_value('position');

    if ($position !== null) {
        $posted['position'] = $position;
    }

    return $posted;
}

/**
 * The field type card and the settings, information and choices cards.
 *
 * @param liveform $liveform
 * @param array $field    pg_field_form_new_field() or the stored field; options
 *                        is the choices text
 * @param array $context  form_type, quiz, position (the field it follows or
 *                        'top'), form_id (the page, product or set id),
 *                        page_type, upload_folder_row (print the upload folder
 *                        picker)
 * @return string
 */
function pg_field_form_cards($liveform, $field, $context)
{
    $form_type = $context['form_type'];

    $output_rss_field_row = '';
    $output_quiz_rows = '';
    $output_contact_field_row = '';
    $output_office_use_only_row = '';
    $output_translate_values_row = '';
    $output_upload_folder_id_row = '';

    if ($form_type == 'custom') {
        $output_rss_field_row =
            '<div class="col-12 md:col-6 lg:col-4 my-3 collapse" id="rss_field_row">
                <label for="rss_field" class="form-label">' . lang('RSS / Search Element') . '</label>
                <select id="rss_field" name="rss_field" class="form-control">' . select_rss_field($field['rss_field']) . '</select>
            </div>';

        if ($context['quiz'] == 1) {
            $output_quiz_rows =
                '<div class="col-12 my-1 collapse" id="quiz_question_row">
                    <div class="form-field">
                        <div class="switch"><input class="collapse-switcher" type="checkbox" id="quiz_question" name="quiz_question" value="1"' . pg_field_form_checked($field['quiz_question']) . ' data-bs-target="#quiz_answer_row"></div>
                        <label class="" for="quiz_question">' . lang('Quiz Question') . '</label>
                    </div>
                </div>
                <div class="collapse popover fade bs-popover-bottom p-0 mb-3" id="quiz_answer_row">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 my-3">
                                <label for="quiz_answer" class="form-label">' . lang('Correct Answer') . '</label>
                                <input value="' . h(prepare_form_data_for_output($field['quiz_answer'], $field['type'])) . '" type="text" name="quiz_answer" id="quiz_answer" class="form-control"  maxlength="255"/>
                            </div>
                        </div>
                    </div>
                </div>';
        }

        $output_contact_field_row =
            '<div class="col-12 md:col-12 lg:col-8 my-3 collapse" id="contact_field_row">
                <label for="contact_field" class="form-label">' . lang('Connect to Contact') . '</label>
                <select name="contact_field" id="contact_field" class="form-control"><option value="">-' . lang('None') . '-</option>' . select_contact_field($field['contact_field']) . '</select>
                <span class="form-text">' . lang('Prefill & Update the Submitter\'s Contact Field') . '</span>
            </div>';

        $output_office_use_only_row =
            '<div class="col-12 my-1 collapse" id="office_use_only_row">
                <div class="form-field">
                    <div class="switch"><input class="" type="checkbox" id="office_use_only" name="office_use_only" value="1"' . pg_field_form_checked($field['office_use_only']) . '></div>
                    <label class="" for="office_use_only">' . lang('Hide This Field from Submitter') . '</label>
                </div>
            </div>';

        // Answers to a text field are translated where a widget includes the
        // form's records; codes or icon names typed into one are left out.
        if (pg_schema_has('form_fields', 'translate_values')) {
            $output_translate_values_row =
                '<div class="col-12 my-1 collapse" id="translate_values_row">
                    <div class="form-field">
                        <div class="switch"><input class="" type="checkbox" id="translate_values" name="translate_values" value="1"' . ((int) $field['translate_values'] !== 0 ? ' checked="checked"' : '') . '></div>
                        <label class="" for="translate_values">' . lang('Include in translation') . '</label>
                    </div>
                </div>';
        }
    }

    if ($context['upload_folder_row']) {
        $output_upload_folder_id_row =
            '<div class="col-12 my-3 collapse" id="upload_folder_id_row">
                <div class="row">
                    <div class="col-12 md:col-6 my-3">
                        <label for="upload_folder_id" class="form-label">' . lang('Folder to upload File') . '</label>
                        <select name="upload_folder_id" id="upload_folder_id" class="form-control">' . select_folder($field['upload_folder_id']) . '</select>
                    </div>
                </div>
            </div>';
    }

    // A new field's name is required in the browser too; a stored one is
    // filled in.
    $name_attributes = array('type' => 'text', 'id' => 'name', 'name' => 'name', 'placeholder' => lang('Field Name'), 'class' => 'form-control ');

    if ($field['id'] == '') {
        $name_attributes['required'] = 'required';
    } else {
        $name_attributes['value'] = h($field['name']);
    }

    return '
                    <div class="row">
                        <div class="col-12 md:col-4 lg:col-3 xl:col-2">
                            <div class="card my-7 position-sticky" style="top:56px;">
                                <label for="type" class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Field Type') . '
                                </label>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12">
                                            <select id="type" name="type" class="form-control collapse-if-selected" data-bs-target="#options_field_row" onchange="change_field_type(this.options[this.selectedIndex].value)"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>lang('field'))) . '-</option>' .  select_field_type($field['type'], $form_type) . '</select>
                                            <script>
                                                $(document).ready(function() {
                                                    change_field_type($("select#type option:selected").val());
                                                });
                                            </script>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 md:col-8 lg:col-9 xl:col-10 collapse" id="options_field_row">
                            <div class="card my-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                ' . lang('Field Settings') . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 md:col-6 lg:col-4 my-3">
                                            <label for="name" class="form-label">' . lang('Name') . '</label>
                                            ' . $liveform->output_field($name_attributes) . '
                                        </div>
                                        ' . $output_rss_field_row . '
                                        <div class="col-12 md:col-12 lg:col-4 my-3 collapse" id="label_row">
                                            <label for="label" class="form-label">' . lang('Label') . '</label>
                                            <input type="text" name="label" value="' . h($field['label']) . '" placeholder="' . lang('Label to display on pages') . '" id="label" class="form-control" />
                                        </div>
                                        <div class="col-12 md:col-6 lg:col-4 my-3 collapse" id="size_row">
                                            <label for="size" class="form-label">' . lang('Size') . '</label>
                                            <input type="number" placeholder="' . lang('Auto') . '" name="size" value="' . h($field['size']) . '" id="size" class="form-control" maxlength="10" />
                                        </div>
                                        <div class="col-12 md:col-6 lg:col-4 my-3 collapse" id="maxlength_row">
                                            <label for="maxlength" class="form-label">' . lang('Maximum Characters') . '</label>
                                            <input type="number" placeholder="' . lang('Unlimited') . '" name="maxlength" value="' . h($field['maxlength']) . '" id="maxlength" class="form-control" maxlength="10" />
                                        </div>
                                        <div class="col-12 md:col-6  lg:col-4 my-3 collapse" id="position_row">
                                            <label for="position" class="form-label">' . lang('Position') . '</label>
                                            <select name="position" id="position" class="form-control">' . select_field_position($context['position'], $field['id'], $context['form_id'], $context['page_type'], $form_type) . '</select>
                                        </div>
                                        ' . $output_contact_field_row . '
                                        <div class="col-12 my-1 collapse" id="required_row">
                                            <div class="form-field">
                                                <div class="switch"><input class="" type="checkbox" id="required" name="required" value="1"' . pg_field_form_checked($field['required']) . '></div>
                                                <label class="" for="required">' . lang('Is This Filed Required?') . '</label>
                                            </div>
                                        </div>
                                        ' . $output_office_use_only_row . '
                                        ' . $output_translate_values_row . '
                                        <div class="col-12 my-1 collapse" id="wysiwyg_row">
                                            <div class="form-field">
                                                <div class="switch"><input class="" type="checkbox" id="wysiwyg" name="wysiwyg" value="1"' . pg_field_form_checked($field['wysiwyg']) . '></div>
                                                <label class="" for="wysiwyg">' . lang('Enable Rich-text Editor') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-1 collapse" id="multiple_row">
                                            <div class="form-field">
                                                <div class="switch"><input class="" type="checkbox" id="multiple" name="multiple" value="1"' . pg_field_form_checked($field['multiple']) . '></div>
                                                <label class="" for="multiple">' . lang('Allow Multiple Selection') . '</label>
                                            </div>
                                        </div>
                                        ' . $output_quiz_rows . '
                                        <div class="col-12 collapse" id="spacing_row">
                                            <div class="my-1">
                                                <div class="form-field">
                                                    <div class="switch"><input class="" type="checkbox" id="spacing_above" name="spacing_above" value="1"' . pg_field_form_checked($field['spacing_above']) . '></div>
                                                    <label class="" for="spacing_above">' . lang('Add Spacing Above') . '</label>
                                                </div>
                                            </div>
                                            <div class="my-1">
                                                <div class="form-field">
                                                    <div class="switch"><input class="" type="checkbox" id="spacing_below" name="spacing_below" value="1"' . pg_field_form_checked($field['spacing_below']) . '></div>
                                                    <label class="" for="spacing_below">' . lang('Add Spacing Below') . '</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 collapse" id="default_value_row">
                                            <div class="form-field">
                                                <div class="switch"><input class="collapse-switcher" type="checkbox" id="use_folder_name_for_default_value" name="use_folder_name_for_default_value" value="1"' . pg_field_form_checked($field['use_folder_name_for_default_value']) . ' data-bs-target="#default_value_input_row"></div>
                                                <label class="" for="use_folder_name_for_default_value">' . lang('Use Folder name for field default value') . '</label>
                                            </div>
                                            <div class="row">
                                                <div class="col-12 md:col-6 lg:col-4 my-3  collapse show-reverse" id="default_value_input_row">
                                                    <label for="default_value" class="form-label">' . lang('Default Value') . '</label>
                                                    <input value="' . h($field['default_value']) . '" type="text" name="default_value" id="default_value" class="form-control" maxlength="255"/>
                                                </div>
                                            </div>
                                        </div>
                                        ' . $output_upload_folder_id_row . '
                                        <div class="col-12 my-1 collapse" id="rows_row">
                                            <h3 class="h6 fg-secondary">' . lang('Amount of Rows and Columns to Display') . '</h3>
                                            <div class="w-100 border-3 border-top border-start position-relative">
                                                <div style="background: var(--pg-border-color);width: 3px;height: 14px;right: 0px;position: absolute;"></div>
                                                <div style="background: var(--pg-border-color);width: 14px;height: 3px;bottom: 0px;position: absolute;"></div>
                                                <div class="m-5 position-relative"  style="height:150px;">
                                                    <div class="position-absolute end-0 top-0">
                                                        <label for="cols" class="form-label float-end">' . lang('Columns') . '</label>
                                                        <input type="text" placeholder="' . lang('Columns') . '" name="cols" id="cols" value="' . h($field['cols']) . '" class="form-control text-end"  maxlength="10" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-digits="8" data-inputmask-digitsoptional="true" data-inputmask-placeholder="0"/>
                                                    </div>
                                                    <div class="position-absolute start-0 bottom-0">
                                                        <input type="text" name="rows" placeholder="' . lang('Rows') . '" id="rows" value="' . h($field['rows']) . '" class="form-control text-end" maxlength="10" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-digits="8" data-inputmask-digitsoptional="true" data-inputmask-placeholder="0"/>
                                                        <label for="rows" class="form-label mb-0 mt-3">' . lang('Rows') . '</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card my-7 collapse" id="information_row">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold" id="information_heading">
                                ' . lang('Information') . '
                                </h2>
                                <div class="card-body p-0">
                                    <div class="row">
                                        <div class="col-12">
                                            <textarea id="information" name="information" aria-labelledby="information_heading">' . h(prepare_rich_text_editor_content_for_output($field['information'])) . '</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card my-7 collapse" id="choices_row">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                ' . lang('Value Options') . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 md:col-12 lg:col-6">
                                            <textarea name="options" id="options" aria-label="' . h(lang('Value Options')) . '" placeholder="' . lang('Please Enter Values Here') . '" class="form-control rounded-4 bg-1" style="min-height: calc(20em + .75rem + 2px);height:100%;" wrap="off">' . h($field['options']) . '</textarea>
                                            ' . pg_code_editor_attach(array('id' => 'options', 'code_type' => 'plain')) . '
                                        </div>
                                        <div class="col-12 md:col-12 lg:col-6">
                                            ' . pg_field_form_choices_help() . '
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>';
}

/**
 * The three choice formats with an example each, beside the choices text.
 *
 * @return string
 */
function pg_field_form_choices_help()
{
    $on_off = lang('on') . '/' . lang('off');

    $formats = array(
        array(
            lang('Choice') . ' 1<br />' . lang('Choice') . ' 2<br />' . lang('Choice') . ' 3',
            lang('Apple') . '<br />' . lang('Banana') . '<br />' . lang('Pear'),
        ),
        array(
            lang('Label') . ' 1|' . lang('Value') . ' 1<br />' . lang('Label') . ' 2|' . lang('Value') . ' 2<br />' . lang('Label') . ' 3|' . lang('Value') . ' 3<br />',
            lang('Apple') . '|' . lang('apple') . '<br />' . lang('Banana') . '|' . lang('banana') . '<br />' . lang('Pear') . '|' . lang('pear'),
        ),
        array(
            lang('Label') . ' 1|' . lang('Value') . ' 1|' . $on_off . '<br />' . lang('Label') . ' 2|' . lang('Value') . ' 2|' . $on_off . '<br />' . lang('Label') . ' 3|' . lang('Value') . ' 3|' . $on_off,
            lang('Apple') . '|' . lang('apple') . '|' . lang('on') . '<br />' . lang('Banana') . '|' . lang('banana') . '|' . lang('off') . '<br />' . lang('Pear') . '|' . lang('pear') . '|' . lang('on'),
        ),
    );

    $margins = array('mb-1', 'my-1', 'mt-1');
    $output = '';

    foreach ($formats as $index => $format) {
        $number = $index + 1;

        $output .= '
                                                <div class="col-12 ' . $margins[$index] . ' card-group">
                                                    <div class="card">
                                                        <div class="card-header">' . lang('Format') . ' ' . $number . '</div>
                                                        <div class="card-body text-nowrap overflow-auto">
                                                            <p>' . $format[0] . '</p>
                                                        </div>
                                                    </div>
                                                    <div class="card">
                                                        <div class="card-header">' . lang('Example') . ' ' . $number . '</div>
                                                        <div class="card-body text-nowrap overflow-auto">
                                                            <p>' . $format[1] . '</p>
                                                        </div>
                                                    </div>
                                                </div>';
    }

    return '<div class="row">' . $output . '
                                            </div>';
}
