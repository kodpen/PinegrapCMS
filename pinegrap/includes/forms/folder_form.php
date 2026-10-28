<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The folder form, shared by the create and the edit screens
 * (add_folder.php, edit_folder.php): the folder information card, the
 * content options card and the values a save writes. The screens keep what
 * differs: duplicate and delete, the parent move, the access rules, the
 * INSERT/UPDATE, the log line and the redirect.
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
 * The folder form fields with their option lists, the token and the hidden
 * send_to filled in.
 *
 * @param array $values  See pg_folder_form_markup(); 'role' is the user's role
 * @return string
 */
function pg_folder_form($values)
{
    $values += array(
        'parent' => 0,
        'exclude' => 0,
        'access_control_type' => '',
        'style' => '',
        'mobile_style' => '',
        'role' => 3,
    );

    $values['token_field'] = get_token_field();
    $values['folder_options'] = select_folder($values['parent'], 0, $values['exclude']);
    $values['access_options'] = select_access_control_type($values['access_control_type']);

    // Administrators, designers and managers pick the styles; a user only
    // sees the one the screen names.
    if ($values['role'] < 3) {
        $values['style_field'] = '<select class="form-control" id="style" name="style">' . select_style($values['style']) . '</select>';
        $values['mobile_style_field'] = '<select class="form-control" id="mobile_style_id" name="mobile_style_id">' . get_mobile_style_options($values['mobile_style']) . '</select>';
    }

    return pg_folder_form_markup($values);
}

/**
 * The two folder cards. Reads no database: the option lists come in.
 *
 * Keys: 'edit' (bool), 'name' (stored name, edit), 'name_field' (markup that
 * replaces the plain name input, create), 'order', 'archived' (bool),
 * 'show_parent' (false for the root folder), 'folder_options',
 * 'access_options', 'style_field' / 'mobile_style_field' (select markup) or
 * 'style_label' / 'mobile_style_label' (escaped text), 'token_field'.
 *
 * @param array $values
 * @return string
 */
function pg_folder_form_markup($values)
{
    $default_style_label = lang('Default') . ' (' . lang('inherit') . ')';

    $values += array(
        'edit' => false,
        'name' => '',
        'name_field' => null,
        'order' => '',
        'archived' => false,
        'show_parent' => true,
        'folder_options' => '',
        'access_options' => '',
        'style_field' => null,
        'mobile_style_field' => null,
        'style_label' => $default_style_label,
        'mobile_style_label' => $default_style_label,
        'token_field' => '',
    );

    $name_field = $values['name_field'];

    if ($name_field === null) {
        $name_field = '<input id="name" name="name" type="text" value="' . h($values['name']) . '" class="form-control " required>';
    }

    $parent_row = '';

    if ($values['show_parent']) {
        $parent_row = '
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="folder" class="form-label">' . lang('Parent Folder') . '</label>
                                            <select class="form-control" id="folder" name="folder">' . $values['folder_options'] . '</select>
                                        </div>';
    }

    $style_field = ($values['style_field'] !== null) ? $values['style_field'] : $values['style_label'];
    $mobile_style_field = ($values['mobile_style_field'] !== null) ? $values['mobile_style_field'] : $values['mobile_style_label'];

    return '
                    ' . $values['token_field'] . '
                    <input type="hidden" id="send_to" name="send_to" value="' . (isset($_REQUEST['send_to']) && is_scalar($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : '') . '" />
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . ($values['edit'] ? lang('Folder Information') : lang('New Folder Information')) . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 md:col-8 my-3">
                                            <label for="name" class="form-label">' . lang('Folder Name') . '</label>
                                            ' . $name_field . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        <div class="col-12 md:col-4  my-3">
                                            <label for="order" class="form-label" data-bs-content="' . lang('Display order of this Folder to other Folders') . '" title="' . lang('Sorting') . '">' . lang('Sorting') . ' (?)</label>
                                            <div class="input-group number-controls">
                                                <button class="btn material-icons minus border border-end-0" type="button" aria-label="' . h(lang('Decrease')) . '">remove</button>
                                                <input class="form-control text-center border-start-0 border-end-0" value="' . h($values['order']) . '" type="text" name="order" id="order" maxlength="9" inputmode="numeric" data-inputmask-alias="decimal"  data-inputmask-placeholder="0"/>
                                                <button class="btn material-icons plus border border-start-0" type="button" aria-label="' . h(lang('Increase')) . '">add</button>
                                            </div>
                                        </div>
                                        <div class="col-12 my-5">
                                            <div class="form-field">
                                                <div class="switch"><input class="" type="checkbox" id="folder_archived" name="folder_archived" value="1"' . ($values['archived'] ? ' checked="checked"' : '') . ' /></div>
                                                <label class="" for="folder_archived" data-bs-content="' . lang('Archive Folder for Pages and Files that are no longer being used') . '" title="' . lang('Archive') . '">' . lang('Archive') . ' (?)</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Content Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">' . $parent_row . '
                                        <div class="col-12 md:col-6 lg:col-auto my-3">
                                            <label for="access_control_type" class="form-label" data-bs-content="' . lang('Access Control for all Pages and Files within this Folder') . '" title="' . lang('Folder Access Control Type') . '">' . lang('Folder Access Control Type') . ' (?)</label>
                                            <select class="form-control" id="access_control_type" name="access_control_type">' . $values['access_options'] . '</select>
                                        </div>
                                    </div>
                                    <div class="row mt-7">
                                        <div class="col-12 md:col-6 lg:col-auto my-3">
                                            <label for="style" class="form-label">' . lang('Desktop Page Style') . '</label>
                                            ' . $style_field . '
                                        </div>
                                        <div class="col-12 md:col-6 lg:col-auto my-3">
                                            <label for="mobile_style_id" class="form-label">' . lang('Mobile Page Style') . '</label>
                                            ' . $mobile_style_field . '
                                        </div>
                                        <div class="col-12 form-text">' . lang('Default Page Styles for Pages within this Folder') . '</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
';
}

/**
 * The posted folder values a save writes: the trimmed name, the order, the
 * access control type, the archived flag, and the desktop and mobile style
 * ids (an empty list when the role may not set styles).
 *
 * @param array $post  The posted fields
 * @param int   $role  The user's role
 * @return array
 */
function pg_folder_form_values($post, $role)
{
    $text = function ($key) use ($post) {
        $value = $post[$key] ?? '';

        return is_scalar($value) ? (string) $value : '';
    };

    $style_values = array();

    if ($role < 3) {
        $style_values = array($text('style'), $text('mobile_style_id'));
    }

    return array(
        trim($text('name')),
        $text('order'),
        $text('access_control_type'),
        $text('folder_archived'),
        $style_values,
    );
}
