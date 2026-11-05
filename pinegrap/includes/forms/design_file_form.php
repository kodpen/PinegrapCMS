<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The design file cards, shared by the upload and the edit screens
 * (add_design_file.php, edit_design_file.php, add_file.php, edit_file.php):
 * the File Access Control card and the Description card; for the two design
 * file screens also the wide layout (form column, sticky status column,
 * sticky save bar) and the Summary card. The screens keep what differs: the dropzone
 * upload, the rename and delete, the editor buttons, every query and the
 * redirect. The cards read no database, so the file screens can draw them
 * with their own folder options.
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
 * The File Access Control card: the Folder select and its optional rows.
 *
 * Options: 'design' (bool, the Design File switch), 'design_checked' (bool,
 * whether that switch is on; a plain file screen passes its stored flag),
 * 'theme' (null,
 * or array('checked' => bool, 'disabled' => bool) for the Theme switch of a
 * CSS file), 'extra_rows' (markup printed after the switches).
 *
 * @param string $folder_options  The <option> list from select_folder()
 * @param array  $options
 * @return string
 */
function pg_design_file_form_access_card($folder_options, $options = array())
{
    $options += array(
        'design' => false,
        'design_checked' => true,
        'theme' => null,
        'extra_rows' => '',
    );

    $rows = '';

    if ($options['design']) {
        $rows .= '
                                    <div class="col-12 my-5">
                                        <div class="form-field">
                                            <div class="switch"><input class="" type="checkbox" aria-describedby="design_hint" id="design" name="design" value="1"' . ($options['design_checked'] ? ' checked="checked"' : '') . '></div>
                                            <label class="" for="design">' . lang('Design File') . '</label>
                                        </div>
                                        <div class="form-text" id="design_hint">' . lang('Mark it as a design file managed by site designers') . '</div>
                                    </div>';
    }

    if (is_array($options['theme'])) {
        $theme = $options['theme'] + array('checked' => false, 'disabled' => false);

        $rows .= '
                                    <div class="col-12 my-5">
                                        <div class="form-field">
                                            <div class="switch"><input class="" type="checkbox" aria-describedby="theme_hint" id="theme" name="theme" value="1"' . ($theme['checked'] ? ' checked="checked"' : '') . ($theme['disabled'] ? ' disabled="disabled"' : '') . '></div>
                                            <label class="" for="theme">' . lang('Theme') . '</label>
                                        </div>
                                        <div class="form-text" id="theme_hint">' . lang('Check if Design File should be included on All Themes screen and in Theme preview') . '</div>
                                    </div>';
    }

    return '
                            <div class="card my-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('File Access Control') . '
                                </h2>
                                <div class="card-body">
                                    <div class="col-12 my-3">
                                        <label for="folder" class="form-label">' . lang('Folder') . '</label>
                                        <select class="form-control" id="folder" name="folder">' . $folder_options . '</select>
                                    </div>' . $rows . $options['extra_rows'] . '
                                </div>
                            </div>';
}

/**
 * The Description card. A new upload has an empty one-row box; a stored
 * file shows its description in a taller box.
 *
 * @param string $description  The stored description ('' for a new upload)
 * @param bool   $compact      true for the one-row box of a new upload
 * @return string
 */
function pg_design_file_form_description_card($description = '', $compact = false)
{
    $size = $compact ? ' rows="1"' : ' style="min-height:85px;"';

    return '
                            <div class="card my-7 ">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Description') . '
                                </h2>
                                <div class="card-body">
                                    <div class="col-12 my-3">
                                        <label for="description" class="form-label">' . lang('File Description / Photo Gallery Caption') . '</label>
                                        <textarea class="form-control" id="description" name="description"' . $size . '>' . h($description) . '</textarea>
                                    </div>
                                </div>
                            </div>';
}

/**
 * The screen row of the design file screens: the form column, the sticky
 * status column and the sticky save bar (ui/edit-screen-wide-layout.md).
 *
 * @param string $form_cards    Cards the operator fills (name, files, description), HTML
 * @param string $status_cards  Cards of the status column (Summary, File Access Control), HTML
 * @param string $buttons       The buttons inside the bar's group, HTML; '' draws no bar
 * @return string
 */
function pg_design_file_form_layout($form_cards, $status_cards, $buttons)
{
    // No buttons (a read-only screen): no empty sticky bar.
    $output_save_bar = '';

    if ($buttons !== '') {
        $output_save_bar = '
                        <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                            <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                                <div class="container">
                                    <div class="btn-group flex-wrap justify-content-center">' . $buttons . '
                                    </div>
                                </div>
                            </nav>
                        </div>';
    }

    return '
                    <div class="row">
                        <div class="col-12 lg:col-8 2xl:col-9 order-1">' . $form_cards . '
                        </div>
                        <div class="col-12 lg:col-4 2xl:col-3 order-2">
                            <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                            <div class="position-sticky" style="top:3.3rem;">' . $status_cards . '
                            </div>
                        </div>' . $output_save_bar . '
                    </div>';
}

/**
 * The Summary card of the status column: optional markup on top (the
 * thumbnail or the missing-file note), then label and value rows.
 *
 * @param string $top   HTML above the rows, or ''
 * @param array  $rows  label (plain text) => value (HTML)
 * @return string
 */
function pg_design_file_form_summary_card($top, $rows)
{
    $output_rows = '';
    $last = count($rows) - 1;
    $index = 0;

    foreach ($rows as $label => $value) {
        $output_rows .= '
                                            <div class="d-flex justify-content-between gap-3 py-2' . ($index < $last ? ' border-bottom' : '') . '">
                                                <dt class="fw-normal">' . h($label) . '</dt>
                                                <dd class="mb-0 fw-bold text-end text-break">' . $value . '</dd>
                                            </div>';
        $index++;
    }

    return '
                            <div class="card my-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Summary') . '
                                </h2>
                                <div class="card-body pt-0">' . $top . '
                                    <dl class="mb-0">' . $output_rows . '
                                    </dl>
                                </div>
                            </div>';
}

/**
 * Why a design file cannot take this name, '' when it can: a name is
 * required (prepare_file_name() would turn a blank one into 'file' and
 * break every link to the stored file); a new name the server would run
 * (.php, .htaccess, ...) is refused as an upload of it is, before
 * prepare_file_name() quietly turns its dot into an underscore; the name
 * must not be ".htaccess" and no other item may hold it. Shared by the
 * edit screen and the design files list's quick edit. Plain text.
 *
 * @param string        $name_posted The trimmed name as typed
 * @param string        $stored_name The file's stored name
 * @param int           $id
 * @param callable|null $available   function ($name, $id): bool, check_name_availability() by default
 * @return string
 */
function pg_design_file_form_refusal($name_posted, $stored_name, $id, $available = null)
{
    $name_posted = (string) $name_posted;

    if ($name_posted === '') {
        return lang('Please enter a name.');
    }

    if (($name_posted !== (string) $stored_name) && pg_upload_name_blocked($name_posted)) {
        return pg_upload_blocked_message($name_posted);
    }

    $name = prepare_file_name($name_posted);

    if ($name == '.htaccess') {
        return lang('File name is invalid') . '.';
    }

    if ($available === null) {
        $available = function ($name, $id) {
            return check_name_availability(array('name' => $name, 'ignore_item_id' => $id, 'ignore_item_type' => 'file')) != false;
        };
    }

    if (!$available($name, (int) $id)) {
        return lang(array('string' => '{var:1} already exists. Please choose a different file name.', 'vars' => array($name)));
    }

    return '';
}

/**
 * Saves a design file the way its edit screen does, once
 * pg_design_file_form_refusal() let the name through: the file is renamed
 * on the disk first when its name changed and it is there (a failed rename
 * writes nothing, so the record never names a file that is not there);
 * the record takes the name, its type (the extension), the folder, the
 * description and the design switch, and a CSS file that is not a system
 * theme its Theme switch; audit and log lines. Shared by the edit screen
 * and the design files list's quick edit.
 *
 * @param int    $id
 * @param string $stored_name The file's stored name
 * @param string $name        prepare_file_name() of the trimmed name
 * @param array  $post        'folder', 'description', 'design', 'theme' as the screen posts them
 * @param int    $user_id
 * @return string '' when saved, else why not (plain text)
 */
function pg_design_file_form_save($id, $stored_name, $name, array $post, $user_id)
{
    $id = (int) $id;
    $name = (string) $name;
    $stored_name = (string) $stored_name;
    $value = function ($key) use ($post) {
        return is_scalar($post[$key] ?? '') ? (string) ($post[$key] ?? '') : '';
    };

    // A name without a dot has no extension, so no type.
    $file_extension = pg_file_name_extension($name);

    // A CSS file that is not a system theme takes the Theme switch.
    $sql_theme = '';
    $values = array($name, $value('folder'), $value('description'), $file_extension, $value('design'));

    if (
        (mb_strtolower($file_extension) == 'css')
        && (db_value("SELECT COUNT(*) FROM system_theme_css_rules WHERE file_id = '" . $id . "'") == 0)
    ) {
        $sql_theme = 'theme = ?,';
        $values[] = $value('theme');
    }

    if (
        ($name !== $stored_name)
        && is_file(FILE_DIRECTORY_PATH . '/' . $stored_name)
        && !@rename(FILE_DIRECTORY_PATH . '/' . $stored_name, FILE_DIRECTORY_PATH . '/' . $name)
    ) {
        return lang('The file could not be renamed on the file system. Check write permission for the file directory.');
    }

    $audit_before = pg_audit_fetch('files', $id);
    $values[] = (string) (int) $user_id;
    $values[] = (string) $id;

    $result = db_q(
        "UPDATE files
        SET
            name = ?,
            folder = ?,
            description = ?,
            type = ?,
            design = ?,
            $sql_theme
            timestamp = UNIX_TIMESTAMP(),
            user = ?
        WHERE id = ?",
        $values
    );

    if (!$result) {
        return lang('The value could not be saved.');
    }

    pg_audit_record('update', 'files', $id, $audit_before);

    log_activity(lang(array('string' => 'design file ({var:1}) was modified', 'vars' => $name)), $_SESSION['sessionusername'] ?? '');

    return '';
}

