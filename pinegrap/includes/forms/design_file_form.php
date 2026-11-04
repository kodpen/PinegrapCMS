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
 * @param string $buttons       The buttons inside the bar's group, HTML
 * @return string
 */
function pg_design_file_form_layout($form_cards, $status_cards, $buttons)
{
    return '
                    <div class="row">
                        <div class="col-12 lg:col-8 2xl:col-9 order-1">' . $form_cards . '
                        </div>
                        <div class="col-12 lg:col-4 2xl:col-3 order-2">
                            <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                            <div class="position-sticky" style="top:3.3rem;">' . $status_cards . '
                            </div>
                        </div>
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
