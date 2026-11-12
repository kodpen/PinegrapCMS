<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The menu form, shared by the create and the edit screens (add_menu.php,
 * edit_menu.php): the form and status columns with the save bar, the
 * name rule and its uniqueness check, and the column values a save writes.
 * The screens keep what differs: the edit screen loads the record, counts
 * its items, shows the Duplicate link, deletes, and goes back to where it
 * was opened from.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/tag_name_rule.php');

/**
 * The form column (Menu Options, Submenu Effect and Classes cards) and the
 * sticky status column (a Summary of the body tag and the effect, plus the
 * menu's item count on edit), in the edit screen layout of
 * product_builder.php. Below lg the status column follows the form in
 * source order.
 *
 * @param liveform $liveform
 * @param array $form  'stored_name' (edited menu's stored name, null on
 *                     create), 'status' (extra Summary rows, HTML)
 * @return string
 */
function pg_menu_form_cards($liveform, $form = array())
{
    $form = array_merge(array('stored_name' => null, 'status' => ''), $form);
    $name_pattern = pg_menu_form_name_pattern($form['stored_name']);
    $name_hint = (strpos($name_pattern, '_') !== false)
        ? lang('Only English letters (a-z), digits, dashes and underscores; no accented letters or spaces.')
        : lang('Only English letters (a-z), digits and dashes; no accented letters or spaces.');

    $popup_position_options =
        array(
            lang('Top') => 'Top',
            lang('Bottom') => 'Bottom',
            lang('Left') => 'Left',
            lang('Right') => 'Right');

    $effect_labels = pg_menu_form_effect_labels();
    $effect = (string) $liveform->get_field_value('effect');
    $effect_label = $effect_labels[$effect] ?? '';

    return '
                        <div class="col-12 lg:col-8 2xl:col-9 order-1">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Menu Options') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="row">
                                        <div class="col-12 md:col-8 lg:col-6 my-3">
                                            <label for="name" class="form-label">' . lang('Menu Name') . '</label>
                                            <div class="input-group">
                                                <div class="input-group-text">' . h('<menu>') . '</div>
                                                ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'class'=>'form-control', 'maxlength'=>'100', 'required'=>'required', 'pattern'=>$name_pattern, 'title'=>$name_hint, 'aria-describedby'=>'name_hint')) . '
                                                <div class="input-group-text">' . h('</menu>') . '</div>
                                            </div>
                                            <div class="form-text" id="name_hint">' . h($name_hint) . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section" id="effect_label">
                                    ' . lang('Submenu Effect') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <style>@layer custom { html[data-bs-theme="dark"] .pg-menu-effect-picture { filter: invert(1); } }</style>
                                    <div role="radiogroup" aria-labelledby="effect_label" class="my-3">
                                        <div class="form-field d-inline-grid me-5" title="">
                                            ' . $liveform->output_field(array('type'=>'radio', 'id'=>'effect_popup',  'name'=>'effect', 'value'=>'Pop-up', 'class'=>'radio', 'aria-controls'=>'pop-up_row')) . '
                                            <label class="" for="effect_popup">'. lang('Pop-up') . '<br><img src="assets/images/menu_effect_popup.png" width="71" height="75" alt="" class="pg-menu-effect-picture" /></label>
                                        </div>
                                        <div class="form-field d-inline-grid me-5">
                                            ' . $liveform->output_field(array('type'=>'radio', 'id'=>'effect_accordion', 'name'=>'effect', 'value'=>'Accordion', 'class'=>'radio')) . '
                                            <label class="" for="effect_accordion">'. lang('Accordion') . '<br><img src="assets/images/menu_effect_accordion.png" width="56" height="75" alt="" class="pg-menu-effect-picture" /></label>
                                        </div>
                                        <div class="form-field d-inline-grid me-5">
                                            ' . $liveform->output_field(array('type'=>'radio', 'id'=>'effect_none', 'name'=>'effect', 'value'=>'', 'class'=>'radio')) . '
                                            <label class="" for="effect_none">'. lang('None') . '<br><img src="assets/images/menu_effect_none.png" width="56" height="75" alt="" class="pg-menu-effect-picture" /></label>
                                        </div>
                                    </div>
                                    <div class="border rounded-4 p-5 mb-3' . ($effect === 'Pop-up' ? '' : ' d-none') . '" id="pop-up_row">
                                            <div class="row">
                                                <div class="col-12 md:col-6 my-1">
                                                    <label for="first_level_popup_position" class="form-label">' . lang('First Expand Menu') . '</label>
                                                    ' . $liveform->output_field(array('type'=>'select', 'class'=>'form-control', 'name'=>'first_level_popup_position', 'id'=>'first_level_popup_position', 'options'=>$popup_position_options)) . '
                                                </div>
                                                <div class="col-12 md:col-6 my-1">
                                                    <label for="second_level_popup_position" class="form-label">' . lang('Then Expand Menu') . '</label>
                                                    ' . $liveform->output_field(array('type'=>'select', 'class'=>'form-control', 'name'=>'second_level_popup_position', 'id'=>'second_level_popup_position', 'options'=>$popup_position_options)) . '
                                                </div>
                                            </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Classes') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="row">
                                        ' . pg_menu_form_class_field($liveform, 'class', lang('Classes'), '&lt;ul class="<span class="chip">software_menu</span>', lang('One or More Custom Classes for the Menu. Separate classes with a space')) . '
                                        ' . pg_menu_form_class_field($liveform, 'active_item_class', lang('Active Item Classes'), '&lt;a class="current', lang('One or More Classes for the Active Menu Item. Separate classes with a space')) . '
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 lg:col-4 2xl:col-3 order-2">
                            <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                            <div class="position-sticky" style="top:3.3rem;">
                                <div class="card mb-7">
                                    <h2 class="card-header bg-reset border-0 pg-title-section">
                                        ' . lang('Summary') . '
                                    </h2>
                                    <div class="card-body pt-0">
                                        <dl class="mb-0">
                                            <div class="py-2 border-bottom">
                                                <dt class="fw-normal">' . lang('Page Style Body Tag') . '</dt>
                                                <dd class="mb-0 text-break"><code id="menu_summary_tag">' . h('<menu>' . (string) $liveform->get_field_value('name') . '</menu>') . '</code></dd>
                                            </div>
                                            <div class="d-flex justify-content-between py-2' . ($form['status'] !== '' ? ' border-bottom' : '') . '">
                                                <dt class="fw-normal">' . lang('Submenu Effect') . '</dt>
                                                <dd class="mb-0 fw-bold" id="menu_summary_effect" data-labels="' . h(json_encode($effect_labels)) . '">' . h($effect_label) . '</dd>
                                            </div>' . $form['status'] . '
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <script>
                            $(document).on(\'input\', \'#name\', function () {
                                $(\'#menu_summary_tag\').text(\'<menu>\' + this.value + \'</menu>\');
                            });
                            $(document).on(\'change\', \'input[name="effect"]\', function () {
                                var $effect = $(\'#menu_summary_effect\');
                                $effect.text($effect.data(\'labels\')[this.value] || \'\');
                                // The pop-up positions apply to the Pop-up effect only.
                                $(\'#pop-up_row\').toggleClass(\'d-none\', this.value !== \'Pop-up\');
                            });
                        </script>';
}

/**
 * The shown name of each stored submenu effect.
 *
 * @return array  effect column value => label
 */
function pg_menu_form_effect_labels()
{
    return array(
        'Pop-up' => lang('Pop-up'),
        'Accordion' => lang('Accordion'),
        '' => lang('None'),
    );
}

/**
 * The Summary row of the edit screen: how many items the menu holds, linked
 * to its items screen.
 *
 * @param int $item_count
 * @param string $items_url  The menu items screen, already escaped for HTML
 * @return string
 */
function pg_menu_form_items_status($item_count, $items_url)
{
    return '
                                            <div class="d-flex justify-content-between py-2">
                                                <dt class="fw-normal"><a href="' . $items_url . '">' . lang('Menu Items') . '</a></dt>
                                                <dd class="mb-0 fw-bold" id="menu_summary_items">' . (int) $item_count . '</dd>
                                            </div>';
}

/**
 * The screen row: the cards (pg_menu_form_cards()) and the sticky save bar
 * with the given buttons.
 *
 * @param string $cards    pg_menu_form_cards()
 * @param string $buttons  The buttons inside the bar's group
 * @return string
 */
function pg_menu_form_layout($cards, $buttons)
{
    return '
                    <div class="row">' . $cards . '
                        <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                            <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                                <div class="container">
                                    <div class="btn-group flex-wrap justify-content-center">' . $buttons . '
                                    </div>
                                </div>
                            </nav>
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
    return '<div class="col-12 my-3">
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
 * The browser pattern of the name field, the rule of pg_tag_name_error():
 * underscores only for a stored name that already has one. The dash is
 * escaped, as the browser reads the pattern with the "v" flag.
 *
 * @param string|null $stored_name  Edited menu's stored name, null on create
 * @return string
 */
function pg_menu_form_name_pattern($stored_name)
{
    if (($stored_name !== null) && (strpos((string) $stored_name, '_') !== false)) {
        return '[A-Za-z0-9_\\-]+';
    }

    return '[A-Za-z0-9\\-]+';
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

    // The column holds 100 characters: a longer name would be stored cut and
    // could then equal another menu's name, which the uniqueness check below
    // compares in full.
    if (($liveform->check_field_error('name') == false) && (mb_strlen((string) $liveform->get_field_value('name'), 'UTF-8') > pg_name_max_length())) {
        $liveform->mark_error('name', lang('Name can be at most 100 characters long.'));
    }

    if ($liveform->check_field_error('name') == false) {
        $previous_name = null;

        if ($id !== null) {
            $result = db_q("SELECT name FROM menus WHERE id = ?", array((string) $id)) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            $previous_name = (string) ($row['name'] ?? '');
        }

        $error = pg_tag_name_error($liveform->get_field_value('name'), $previous_name);

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
 * Checks the two classes fields: each column holds 255 characters, and a
 * longer list would be stored cut in the middle of a class. The chips
 * field joins its value in script, so the input's maxlength never applies.
 *
 * @param liveform $liveform
 */
function pg_menu_form_validate_classes($liveform)
{
    foreach (array('class', 'active_item_class') as $field) {
        if (mb_strlen((string) $liveform->get($field), 'UTF-8') > 255) {
            $liveform->mark_error($field, lang('Classes can be at most 255 characters long.'));
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

/**
 * Writes the edit screen's values (pg_menu_form_values()) to a menu and
 * stamps the change. Shared by the edit screen and the All Menus list's
 * quick edit.
 *
 * @param liveform $liveform
 * @param int $id
 * @param int $user_id  The user who made the change
 * @return bool  Whether the UPDATE ran
 */
function pg_menu_form_save($liveform, $id, $user_id)
{
    $query =
        "UPDATE menus
        SET
            name = ?,
            effect = ?,
            first_level_popup_position = ?,
            second_level_popup_position = ?,
            class = ?,
            active_item_class = ?,
            last_modified_user_id = ?,
            last_modified_timestamp = UNIX_TIMESTAMP()
        WHERE id = ?";

    return (bool) db_q($query, array_merge(pg_menu_form_values($liveform), array((string) (int) $user_id, (string) (int) $id)));
}
