<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The menu form, shared by the create and the edit screens (add_menu.php,
 * edit_menu.php): the Menu Options card, the name rule and its uniqueness
 * check, and the column values a save writes. The screens keep what
 * differs: the edit screen loads the record, shows the body tag and the
 * Duplicate link, deletes, and goes back to where it was opened from.
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
 * The Menu Options card: name, submenu effect with its pop-up positions,
 * the menu classes and the active item classes.
 *
 * @param liveform $liveform
 * @return string
 */
function pg_menu_form_card($liveform)
{
    $popup_position_options =
        array(
            lang('Top') => 'Top',
            lang('Bottom') => 'Bottom',
            lang('Left') => 'Left',
            lang('Right') => 'Right');

    return '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Menu Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 sm:col-4 my-3">
                                            <label for="name" class="form-label">' . lang('Menu Name') . '</label>
                                            <div class="input-group">
                                                <div class="input-group-text">' . h('<menu>') . '</div>
                                                ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'class'=>'form-control', 'maxlength'=>'100', 'required'=>'required')) . '
                                                <div class="input-group-text">' . h('</menu>') . '</div>
                                            </div>
                                        </div>
                                        <div class="col-12 mt-1">
                                            <label class="form-label" for="">'. lang('Submenu Effect') . '</label>
                                        </div>
                                        <div class="col-12 mb-1">
                                            <div class="form-field d-inline-grid me-5" title="">
                                                ' . $liveform->output_field(array('type'=>'radio', 'id'=>'effect_popup',  'name'=>'effect', 'value'=>'Pop-up', 'class'=>'radio collapse-switcher', 'data-bs-target'=>'#pop-up_row')) . '
                                                <label class="" for="effect_popup">'. lang('Pop-up') . '<br><img src="assets/images/menu_effect_popup.png" width="71" height="75" alt="img" /></label>
                                            </div>
                                            <div class="form-field d-inline-grid me-5">
                                                ' . $liveform->output_field(array('type'=>'radio', 'id'=>'effect_accordion', 'name'=>'effect', 'value'=>'Accordion', 'class'=>'radio collapse-switcher')) . '
                                                <label class="" for="effect_accordion">'. lang('Accordion') . '<br><img src="assets/images/menu_effect_accordion.png" width="71" height="75" alt="img" /></label>
                                            </div>
                                            <div class="form-field d-inline-grid me-5">
                                                ' . $liveform->output_field(array('type'=>'radio', 'id'=>'effect_none', 'name'=>'effect', 'value'=>'', 'class'=>'radio collapse-switcher')) . '
                                                <label class="" for="effect_none">'. lang('None') . '<br><img src="assets/images/menu_effect_none.png" width="71" height="75" alt="img" /></label>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-3 w-100" id="pop-up_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(25px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 md:col-6 my-1">
                                                            <label for="first_level_popup_position" class="form-label">' . lang('First Expand Menu') . '</label>
                                                            ' . $liveform->output_field(array('type'=>'select', 'class'=>'form-control', 'name'=>'first_level_popup_position', 'id'=>'first_level_popup_position', 'options'=>$popup_position_options)) . '
                                                        </div>
                                                        <div class="col-12 md:col-6 my-1">
                                                            <label for="first_level_popup_position" class="form-label">' . lang('Then Expand Menu') . '</label>
                                                            ' . $liveform->output_field(array('type'=>'select', 'class'=>'form-control', 'name'=>'second_level_popup_position', 'id'=>'second_level_popup_position', 'options'=>$popup_position_options)) . '
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        ' . pg_menu_form_class_field($liveform, 'class', lang('Classes'), '&lt;ul class="<span class="chip">software_menu</span>', lang('One or More Custom Classes for the Menu. Separate classes with a space')) . '
                                        ' . pg_menu_form_class_field($liveform, 'active_item_class', lang('Active Item Classes'), '&lt;a class="current', lang('One or More Classes for the Active Menu Item. Separate classes with a space')) . '
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>';
}

/**
 * One classes field: chips between the start and the end of the tag it
 * fills, with its help text.
 *
 * @param liveform $liveform
 * @param string $name  Field and column name
 * @param string $label
 * @param string $prefix  Markup shown before the chips (the tag's start)
 * @param string $help
 * @return string
 */
function pg_menu_form_class_field($liveform, $name, $label, $prefix, $help)
{
    return '<div class="col-12 mt-5 mb-3">
                                            <label for="' . $name . '" class="form-label">' . $label . '</label>
                                            <div class="input-group input-group-sm d-flex flex-wrap border rounded-4 border-2">
                                                <div class="input-group-text bg-reset border-0">' . $prefix . '</div>
                                                ' . $liveform->output_field(array(
                                                    'type' => 'text',
                                                    'name' => $name,
                                                    'id' => $name,
                                                    'class' => 'form-control tagin border-0 min-width-tagin',
                                                    'data-placeholder' => lang('Add classes'),
                                                    'data-pg-chips' => '',
                                                    'data-pg-separator' => ' ',
                                                    'maxlength' => '255')) . pg_fields_script() . '
                                                <div class="input-group-text bg-reset border-0">"&gt;</div>
                                            </div>
                                            <div class="form-text text-end">' . $help . '</div>
                                        </div>';
}

/**
 * Why a menu name breaks the name rule, or '' when it does not. A new name
 * takes letters, numbers and dashes; underscores stay allowed only for a
 * menu whose stored name already has one, because the theme designer does
 * not support them and renaming would break its styles.
 *
 * @param string $name
 * @param string|null $previous_name  Stored name on edit, null on create
 * @return string
 */
function pg_menu_form_name_error($name, $previous_name)
{
    if ($previous_name === null) {
        $regex = '/[^A-Za-z0-9-]/';
        $message = lang('Please only enter letters, numbers, and dashes for the name.');
    } elseif (mb_strpos($previous_name, '_') !== false) {
        $regex = '/[^A-Za-z0-9_-]/';
        $message = lang('Please only enter letters, numbers, dashes, and underscores for the name.');
    } else {
        $regex = '/[^A-Za-z0-9-]/';
        $message = lang('Please only enter letters, numbers, or dashes for the name.');
    }

    return (preg_match($regex, (string) $name) == 1) ? $message : '';
}

/**
 * Checks the posted name: required, the name rule, not used by another
 * menu. Errors are marked on the form.
 *
 * @param liveform $liveform
 * @param string|null $id  Edited menu's id, null on create
 */
function pg_menu_form_validate_name($liveform, $id)
{
    $liveform->validate_required_field('name', lang('Name is required.'));

    if ($liveform->check_field_error('name') == false) {
        $previous_name = null;

        if ($id !== null) {
            $result = db_q("SELECT name FROM menus WHERE id = ?", array((string) $id)) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            $previous_name = (string) ($row['name'] ?? '');
        }

        $error = pg_menu_form_name_error($liveform->get_field_value('name'), $previous_name);

        if ($error !== '') {
            $liveform->mark_error('name', $error);
        }
    }

    if ($liveform->check_field_error('name') == false) {
        $query = "SELECT id FROM menus WHERE (name = ?)";
        $values = array((string) $liveform->get_field_value('name'));

        if ($id !== null) {
            $query .= " AND (id != ?)";
            $values[] = (string) $id;
        }

        $result = db_q($query, $values) or output_error('Query failed.');

        if (mysqli_num_rows($result) > 0) {
            $liveform->mark_error('name', lang('The name that you entered is already in use, so please enter a different name.'));
        }
    }
}

/**
 * The six column values a save writes, in INSERT/UPDATE order: name,
 * effect, first and second level pop-up positions, classes, active item
 * classes.
 *
 * @param liveform $liveform
 * @return array
 */
function pg_menu_form_values($liveform)
{
    return array(
        (string) $liveform->get_field_value('name'),
        (string) $liveform->get_field_value('effect'),
        (string) $liveform->get_field_value('first_level_popup_position'),
        (string) $liveform->get_field_value('second_level_popup_position'),
        (string) $liveform->get('class'),
        (string) $liveform->get('active_item_class'),
    );
}
