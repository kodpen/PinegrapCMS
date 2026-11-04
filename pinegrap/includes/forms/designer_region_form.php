<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The designer region form, shared by the create and the edit screens
 * (add_designer_region.php, edit_designer_region.php): the form and status
 * columns and the values a save writes; the row and the save bar come from
 * pg_common_region_form_layout(). The screens keep what differs: the edit
 * screen loads the record, adds its Summary rows, offers the region type,
 * duplicate and delete.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The name check, its error markup, the type labels, the Summary rows and
// the layout are shared with common regions: both live in the cregion table.
require_once(PG_FUNCTIONS_DIR . '/includes/forms/common_region_form.php');

/**
 * The form column (Options card: the name between <cregion> tags and, on
 * edit, the region type; Content card: the shared content as an HTML code
 * snippet) and the sticky status column (a Summary of the body tag and the
 * region type, plus the screen's own rows), in the edit screen layout of
 * product_builder.php. Below lg the status column follows the form in
 * source order. The screens wrap it in pg_common_region_form_layout().
 *
 * @param string $name     The region name ('' on create)
 * @param string $content  The shared content ('' on create)
 * @param array  $form     'type' (null on create: no type field, the region
 *                         is created a designer region; else 'common' or
 *                         'designer'), 'name_error' (why the posted name was
 *                         refused, '' for none), 'status' (extra Summary
 *                         rows, HTML, from pg_common_region_form_status_row())
 * @return string
 */
function pg_designer_region_form_cards($name = '', $content = '', $form = array())
{
    $form = array_merge(array('type' => null, 'name_error' => '', 'status' => ''), $form);
    $name_error = (string) $form['name_error'];
    $type_labels = pg_common_region_form_type_labels();
    $type = ((string) $form['type'] === 'common') ? 'common' : 'designer';

    $output_type = '';

    if ($form['type'] !== null) {
        $options = '';

        foreach ($type_labels as $value => $label) {
            $options .= '<option value="' . $value . '"' . ($type == $value ? ' selected="selected"' : '') . '>' . h($label) . '</option>';
        }

        $output_type = '
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="type" class="form-label">' . lang('Region Type') . '</label>
                                            <select name="type" id="type" class="form-control">' . $options . '</select>
                                            <div class="form-text">' . lang('You can change Region Type') . '</div>
                                        </div>';
    }

    return '
                        <div class="col-12 lg:col-8 2xl:col-9 order-1">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Options') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="row">
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Designer Region'))) . '</label>
                                            <div class="input-group">
                                                <div class="input-group-text">' . h('<cregion>') . '</div>
                                                <input value="' . h($name) . '" name="name" id="name" type="text" class="form-control' . pg_region_form_name_error_attributes($name_error) . '" maxlength="100" required="required" />
                                                <div class="input-group-text pg-tag-close">' . h('</cregion>') . '</div>
                                            </div>' . pg_region_form_name_error($name_error) . '
                                        </div>' . $output_type . '
                                    </div>
                                </div>
                            </div>
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Shared Content to appear on associated Pages') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="my-3">
                                        <label for="code" class="form-label">' . lang('HTML Code Snippet') . '</label>
                                        <textarea name="content" id="code" rows="30" cols="60" wrap="off">' . h($content) . '</textarea>
                                        ' . pg_code_editor_attach(array('id' => 'code', 'code_type' => 'mixed')) . '
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 lg:col-4 2xl:col-3 order-2">
                            <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                            <div class="position-sticky" style="top:3.3rem;">
                                <div class="card mb-7">
                                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                        ' . lang('Summary') . '
                                    </h2>
                                    <div class="card-body pt-0">
                                        <dl class="mb-0">
                                            <div class="py-2 border-bottom">
                                                <dt class="fw-normal">' . lang('Page Style Body Tag') . '</dt>
                                                <dd class="mb-0 text-break"><code id="cregion_summary_tag">' . h('<cregion>' . $name . '</cregion>') . '</code></dd>
                                            </div>
                                            <div class="d-flex justify-content-between gap-3 py-2' . ($form['status'] !== '' ? ' border-bottom' : '') . '">
                                                <dt class="fw-normal">' . lang('Region Type') . '</dt>
                                                <dd class="mb-0 fw-bold text-end" id="cregion_summary_type" data-labels="' . h(json_encode($type_labels)) . '">' . h($type_labels[$type]) . '</dd>
                                            </div>' . $form['status'] . '
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <script>
                            $(document).on(\'input\', \'#name\', function () {
                                $(\'#cregion_summary_tag\').text(\'<cregion>\' + this.value + \'</cregion>\');
                            });
                            $(document).on(\'change\', \'#type\', function () {
                                var $type = $(\'#cregion_summary_type\');
                                $type.text($type.data(\'labels\')[this.value] || \'\');
                            });
                        </script>';
}

/**
 * The two designer region values a save writes, in INSERT/UPDATE order:
 * the trimmed name and the content.
 *
 * @param array $post  The posted fields
 * @return array
 */
function pg_designer_region_form_values($post)
{
    $name = $post['name'] ?? '';
    $content = $post['content'] ?? '';

    return array(
        is_scalar($name) ? trim((string) $name) : '',
        is_scalar($content) ? (string) $content : '',
    );
}
