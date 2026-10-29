<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The design file cards, shared by the upload and the edit screens
 * (add_design_file.php, edit_design_file.php, add_file.php, edit_file.php):
 * the File Access Control card and the Description card. The screens keep what differs: the dropzone
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
                                            <div class="switch"><input class="" type="checkbox" id="design" name="design" value="1"' . ($options['design_checked'] ? ' checked="checked"' : '') . '></div>
                                            <label class="" for="design" data-bs-content="' . lang('Check if File is a Design File that is Managed by Site Designers') . '" title="" data-bs-original-title="' . lang('Design File') . '">' . lang('Design File') . ' (?)</label>
                                        </div>
                                    </div>';
    }

    if (is_array($options['theme'])) {
        $theme = $options['theme'] + array('checked' => false, 'disabled' => false);

        $rows .= '
                                    <div class="col-12 my-5">
                                        <div class="form-field">
                                            <div class="switch"><input class="" type="checkbox" id="theme" name="theme" value="1"' . ($theme['checked'] ? ' checked="checked"' : '') . ($theme['disabled'] ? ' disabled="disabled"' : '') . '></div>
                                            <label class="" for="theme" data-bs-content="' . lang('Check if Design File should be included on All Themes screen and in Theme preview') . '" title="" data-bs-original-title="' . lang('Theme') . '">' . lang('Theme') . ' (?)</label>
                                        </div>
                                    </div>';
    }

    return '
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('File Access Control') . '
                                </div>
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
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Description') . '
                                </div>
                                <div class="card-body">
                                    <div class="col-12 my-3">
                                        <label for="description" class="form-label">' . lang('File Description / Photo Gallery Caption') . '</label>
                                        <textarea class="form-control" id="description" name="description"' . $size . '>' . h($description) . '</textarea>
                                    </div>
                                </div>
                            </div>';
}
