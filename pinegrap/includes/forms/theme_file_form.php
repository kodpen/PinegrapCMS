<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Edit Theme check and save, shared by the edit screen
 * (edit_theme_file.php) and the All Themes list's quick edit
 * (includes/fn/theme_list_edit.php): the name rule, the rename on the
 * disk, the activation that takes the other theme off the device type and
 * moves the pages to its styles, the record update and its log lines.
 * The screen keeps the form, the delete, the export and its notices.
 * Also the heading check of the system theme CSV import (add_theme_file.php).
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
 * Checks the theme's name in the form (field 'name'): required, ending in
 * ".css", not ".htaccess" and not used by another file or item. Marks the
 * first errors on the field; the name and extension errors come before the
 * other two are looked at.
 *
 * @param liveform $liveform the form, its 'id' field the edited theme
 * @return string the name as it would be stored (prepare_file_name())
 */
function pg_theme_file_form_validate($liveform)
{
    $liveform->validate_required_field('name', lang('Name is required.'));

    if (($liveform->check_form_errors() == false) && (mb_strtolower(mb_substr($liveform->get_field_value('name'), mb_strrpos($liveform->get_field_value('name'), '.'))) != '.css')) {
        $liveform->mark_error('name', lang('The name must end with ".css".'));
    }

    $name = prepare_file_name($liveform->get_field_value('name'));

    if ($liveform->check_form_errors() == true) {
        return $name;
    }

    if ($name == '.htaccess') {
        $liveform->mark_error('name', lang('File name is invalid.'));
    }

    if (check_name_availability(array('name' => $name, 'ignore_item_id' => $liveform->get_field_value('id'), 'ignore_item_type' => 'file')) == false) {
        $liveform->mark_error('name', lang(array('string' => '{var:1} already exists. Please choose a different file name.', 'vars' => $name)));
    }

    return $name;
}

/**
 * What a theme's save changes for each device type, from its activation
 * flags before and after. Pure.
 *
 * 'keep': device types it was turned off for; the styles the pages wear
 * there are written to its preview rows (pg_theme_keep_page_styles()).
 * 'apply': device types it was turned on for; the pages move to the styles
 * in its preview rows.
 *
 * @param array $before 'desktop', 'mobile' => 1 or 0
 * @param array $after  'desktop', 'mobile' => 1 or 0
 * @return array 'keep', 'apply' => list of 'desktop' / 'mobile'
 */
function pg_theme_activation_changes($before, $after)
{
    $changes = array('keep' => array(), 'apply' => array());

    foreach (array('desktop', 'mobile') as $device_type) {
        $was = ((int) ($before[$device_type] ?? 0) === 1);
        $is = ((int) ($after[$device_type] ?? 0) === 1);

        if ($was && !$is) {
            $changes['keep'][] = $device_type;
        } elseif (!$was && $is) {
            $changes['apply'][] = $device_type;
        }
    }

    return $changes;
}

/**
 * Writes the styles the pages wear now for one device type into a theme's
 * preview rows, in place of the ones it had, so turning the theme on again
 * moves the pages back to them. A page with no style of its own for the
 * device type gets no row and keeps what it wears then.
 *
 * @param int    $theme_id
 * @param string $device_type 'desktop' or 'mobile'
 */
function pg_theme_keep_page_styles($theme_id, $device_type)
{
    $theme_id = (int) $theme_id;
    $device_type = ($device_type === 'mobile') ? 'mobile' : 'desktop';
    $column = ($device_type === 'mobile') ? 'mobile_style_id' : 'page_style';

    if ($theme_id <= 0) {
        return;
    }

    db("DELETE FROM preview_styles WHERE (theme_id = '" . $theme_id . "') AND (device_type = '" . $device_type . "')");

    db(
        "INSERT INTO preview_styles (page_id, theme_id, style_id, device_type)
        SELECT page_id, '" . $theme_id . "', " . $column . ", '" . $device_type . "'
        FROM page
        WHERE (" . $column . " != '0') AND (" . $column . " IS NOT NULL)");
}

/**
 * Writes the form's values to the theme (fields 'id', 'name', 'folder',
 * 'description', 'activated_desktop_theme', 'activated_mobile_theme'),
 * after pg_theme_file_form_validate() found no error. The file is renamed
 * on the disk first; a failed rename marks the name field and writes
 * nothing.
 *
 * @param liveform $liveform
 * @param int      $user_id the user who made the change
 * @return array 'ok', 'name' (stored), 'missing' (the file is not on the
 *               disk), 'deactivated' (ids of the themes this one took the
 *               place of)
 */
function pg_theme_file_form_save($liveform, $user_id)
{
    $query =
        "SELECT
            name,
            activated_desktop_theme,
            activated_mobile_theme
        FROM files
        WHERE id = ?";
    $result = db_q($query, array((string) $liveform->get_field_value('id'))) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);

    $original_name = $row['name'];
    $original_activated_desktop_theme = $row['activated_desktop_theme'];
    $original_activated_mobile_theme = $row['activated_mobile_theme'];

    $name = prepare_file_name($liveform->get_field_value('name'));

    // The row every write below acts on, for its Audit Trail line.
    $audit_id = (int) $liveform->get_field_value('id');

    // The themes this one replaces as the activated theme of a device type.
    $deactivated = array();

    // Rename the file first when its name changed and it is on the disk,
    // before any record changes. A theme recorded here but missing from
    // the disk has nothing to rename; the record is still saved and the
    // themes list says the file is missing. A failed rename sends the
    // user back with the error, so the record never takes a name the
    // disk does not have.
    $theme_file_missing = !is_file(FILE_DIRECTORY_PATH . '/' . $original_name);

    if (
        ($name !== $original_name)
        && !$theme_file_missing
        && !@rename(FILE_DIRECTORY_PATH . '/' . $original_name, FILE_DIRECTORY_PATH . '/' . $name)
    ) {
        $liveform->mark_error('name', lang('The file could not be renamed on the file system. Check write permission for the file directory.'));

        return array('ok' => false, 'name' => $name, 'missing' => $theme_file_missing, 'deactivated' => array());
    }

    // get file extension
    $array_file_extension = explode('.', $name);
    $size_of_array = count($array_file_extension);
    $file_extension = $array_file_extension[$size_of_array - 1];
    
    // if this theme was activated as the desktop theme, then deactivate all other themes
    if ($liveform->get_field_value('activated_desktop_theme') == 1) {
        // Check if there was a theme that was activated before that is not this theme.
        $old_activated_theme_id = db_value(
            "SELECT id
            FROM files
            WHERE
                (activated_desktop_theme = '1')
                AND (id != '" . escape($liveform->get_field_value('id')) . "')");

        // If there is an old activated theme, then continue with deactivation process.
        if ($old_activated_theme_id) {
            // Its rows take the styles the pages wear now, so turning it on
            // again later gives them back.
            pg_theme_keep_page_styles($old_activated_theme_id, 'desktop');

            // Deactivate old theme.
            $old_theme_audit_before = pg_audit_fetch('files', (int) $old_activated_theme_id);
            $query =
                "UPDATE files
                SET activated_desktop_theme = '0'
                WHERE
                    (activated_desktop_theme = '1')
                    AND (id != ?)";
            $result = db_q($query, array((string) $liveform->get_field_value('id'))) or output_error('Query failed.');
            pg_audit_record('update', 'files', (int) $old_activated_theme_id, $old_theme_audit_before);
            $deactivated[] = (int) $old_activated_theme_id;
        }
    }

    // if this theme was activated as the mobile theme, then deactivate all other themes
    if ($liveform->get_field_value('activated_mobile_theme') == 1) {
        // Check if there was a theme that was activated before that is not this theme.
        $old_activated_theme_id = db_value(
            "SELECT id
            FROM files
            WHERE
                (activated_mobile_theme = '1')
                AND (id != '" . escape($liveform->get_field_value('id')) . "')");

        // If there is an old activated theme, then continue with deactivation process.
        if ($old_activated_theme_id) {
            pg_theme_keep_page_styles($old_activated_theme_id, 'mobile');

            // Deactivate old theme.
            $old_theme_audit_before = pg_audit_fetch('files', (int) $old_activated_theme_id);
            $query =
                "UPDATE files
                SET activated_mobile_theme = '0'
                WHERE
                    (activated_mobile_theme = '1')
                    AND (id != ?)";
            $result = db_q($query, array((string) $liveform->get_field_value('id'))) or output_error('Query failed.');
            pg_audit_record('update', 'files', (int) $old_activated_theme_id, $old_theme_audit_before);
            $deactivated[] = (int) $old_activated_theme_id;
        }
    }

    // update theme
    $audit_before = pg_audit_fetch('files', $audit_id);
    $query =
        "UPDATE files 
        SET 
            name = ?,
            folder = ?, 
            description = ?,
            type = ?,
            activated_desktop_theme = ?,
            activated_mobile_theme = ?,
            timestamp = UNIX_TIMESTAMP(),
            user = ?
        WHERE id = ?";
    
    $result = db_q($query, array((string) $name, (string) $liveform->get_field_value('folder'), (string) $liveform->get_field_value('description'), (string) $file_extension, (string) $liveform->get_field_value('activated_desktop_theme'), (string) $liveform->get_field_value('activated_mobile_theme'), (string) (int) $user_id, (string) $liveform->get_field_value('id'))) or output_error('Query failed.');
    pg_audit_record('update', 'files', $audit_id, $audit_before);
    
    log_activity(lang(array('string' => 'theme file ({var:1}) was modified', 'vars' => array($name))), $_SESSION['sessionusername']);

    // What turning this theme on or off changes for each device type.
    $changes = pg_theme_activation_changes(
        array('desktop' => $original_activated_desktop_theme, 'mobile' => $original_activated_mobile_theme),
        array('desktop' => $liveform->get_field_value('activated_desktop_theme'), 'mobile' => $liveform->get_field_value('activated_mobile_theme')));

    // Turned off with no other theme taking its place: its rows take the
    // styles the pages wear now, as they do when another theme replaces it.
    // Left as they were, turning it on again would move the pages back to
    // the styles they had when it was last replaced, undoing every style
    // chosen while it was on.
    foreach ($changes['keep'] as $device_type) {
        pg_theme_keep_page_styles($liveform->get_field_value('id'), $device_type);
    }

    // If this theme was activated as a desktop or mobile theme,
    // then move the pages to its styles for those device types.
    if ($changes['apply']) {
        // Get the preview styles of this theme for the device types turned on.
        $preview_styles = db_items(
            "SELECT
                page_id,
                style_id,
                device_type
            FROM preview_styles
            WHERE
                (theme_id = '" . escape($liveform->get_field_value('id')) . "')
                AND (device_type IN ('" . implode("', '", $changes['apply']) . "'))");

        // Loop through all preview styles in order to update styles for pages.
        foreach ((array) $preview_styles as $preview_style) {
            if ($preview_style['device_type'] == 'desktop') {
                $sql_style_id_column = "page_style";
            } else {
                $sql_style_id_column = "mobile_style_id";
            }

            db(
                "UPDATE page
                SET
                    " . $sql_style_id_column . " = '" . $preview_style['style_id'] . "',
                    page_user = '" . USER_ID . "',
                    page_timestamp = UNIX_TIMESTAMP()
                WHERE page_id = '" . $preview_style['page_id'] . "'");
        }
    }

    // if this theme file was not activated for desktop before and it is now,
    // then check if we should update preview theme and log activity.
    if (
        ($original_activated_desktop_theme == 0)
        && ($liveform->get_field_value('activated_desktop_theme') == 1)
    ) {
        // If the user's device type is desktop and the user is previewing a theme,
        // then update the theme that the user is previewing to be this theme.
        if (
            (($_SESSION['software']['device_type'] ?? '') == 'desktop')
            && (isset($_SESSION['software']['preview_theme_id']))
        ) {
            $_SESSION['software']['preview_theme_id'] = $liveform->get_field_value('id');
        }

        log_activity(lang(array('string' => 'theme file ({var:1}) was activated for desktop', 'vars' => array($name))), $_SESSION['sessionusername']);
    }

    // if this theme file was activated for desktop before and it is not now, then log activity
    if (
        ($original_activated_desktop_theme == 1)
        && ($liveform->get_field_value('activated_desktop_theme') == 0)
    ) {
        log_activity(lang(array('string' => 'theme file ({var:1}) was deactivated for desktop', 'vars' => array($name))), $_SESSION['sessionusername']);
    }

    // if this theme file was not activated for mobile before and it is now,
    // then check if we should update preview theme and log activity.
    if (
        ($original_activated_mobile_theme == 0)
        && ($liveform->get_field_value('activated_mobile_theme') == 1)
    ) {
        // If the user's device type is mobile and the user is previewing a theme,
        // then update the theme that the user is previewing to be this theme.
        if (
            (($_SESSION['software']['device_type'] ?? '') == 'mobile')
            && (isset($_SESSION['software']['preview_theme_id']))
        ) {
            $_SESSION['software']['preview_theme_id'] = $liveform->get_field_value('id');
        }

        log_activity(lang(array('string' => 'theme file ({var:1}) was activated for mobile', 'vars' => array($name))), $_SESSION['sessionusername']);
    }

    // if this theme file was activated for mobile before and it is not now, then log activity
    if (
        ($original_activated_mobile_theme == 1)
        && ($liveform->get_field_value('activated_mobile_theme') == 0)
    ) {
        log_activity(lang(array('string' => 'theme file ({var:1}) was deactivated for mobile', 'vars' => array($name))), $_SESSION['sessionusername']);
    }
    
    return array('ok' => true, 'name' => $name, 'missing' => $theme_file_missing, 'deactivated' => $deactivated);
}

/**
 * The rule columns of a system theme CSV import (Create Theme), read from
 * its heading row: the eight columns the export writes (edit_theme_file.php),
 * each once, in any order and letter case. The list is built from the known
 * names, never from the cells, and quoted: `row` is a reserved word in
 * MySQL 8.
 *
 * @param array $heading the cells of the file's first row
 * @return string|null the column list for the INSERT, null when the heading
 *                     is not one the import can take
 */
function pg_theme_csv_import_columns($heading)
{
    $known = array('area', 'row', 'col', 'module', 'region_type', 'region_name', 'property', 'value');

    if (!is_array($heading) || (count($heading) !== count($known))) {
        return null;
    }

    $columns = array();

    foreach ($heading as $cell) {
        $name = strtolower(trim((string) $cell));

        if (!in_array($name, $known, true) || in_array($name, $columns, true)) {
            return null;
        }

        $columns[] = $name;
    }

    return '`' . implode('`, `', $columns) . '`';
}
