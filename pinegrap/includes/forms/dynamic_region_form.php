<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The dynamic region form, shared by the create and the edit screens
 * (add_dynamic_region.php, edit_dynamic_region.php): the form and status
 * columns and the values a save writes. The screens keep what differs: the
 * edit screen loads the record, adds its Summary rows and offers delete.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The name error markup, the lookup, the save bar layout and the Summary
// row are shared with the cregion forms.
require_once(PG_FUNCTIONS_DIR . '/includes/forms/common_region_form.php');

/**
 * The form column (Options card: the name between <dregion> tags; PHP Code
 * card: the code snippet in the code editor) and the sticky status column
 * (a Summary of the body tag, plus the screen's own rows), in the edit
 * screen layout of product_builder.php. Below lg the status column follows
 * the form in source order. The screens wrap it in
 * pg_common_region_form_layout() for the save bar.
 *
 * @param string $name  The region name ('' on create)
 * @param string $code  The PHP code ('' on create)
 * @param array  $form  'name_error' (why the posted name was refused, ''
 *                      for none), 'status' (extra Summary rows, HTML)
 * @return string
 */
function pg_dynamic_region_form_cards($name = '', $code = '', $form = array())
{
    $form = array_merge(array('name_error' => '', 'status' => ''), $form);
    $name_error = (string) $form['name_error'];

    return '
                        <div class="col-12 lg:col-8 2xl:col-9 order-1">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Options') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="row">
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Dynamic Region'))) . '</label>
                                            <div class="input-group">
                                                <div class="input-group-text">' . h('<dregion>') . '</div>
                                                <input value="' . h($name) . '" name="name" id="name" type="text" class="form-control' . pg_region_form_name_error_attributes($name_error) . '" maxlength="100" required="required" />
                                                <div class="input-group-text pg-tag-close">' . h('</dregion>') . '</div>
                                            </div>' . pg_region_form_name_error($name_error) . '
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('PHP Code to appear on associated Pages') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="my-3">
                                        <label for="code" class="form-label">' . lang('PHP Code Snippet') . '</label>
                                        <textarea name="code" id="code" rows="30" cols="60" wrap="off">' . h($code) . '</textarea>
                                        ' . pg_code_editor_attach(array('id' => 'code', 'code_type' => 'php')) . '
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
                                            <div class="py-2' . ($form['status'] !== '' ? ' border-bottom' : '') . '">
                                                <dt class="fw-normal">' . lang('Page Style Body Tag') . '</dt>
                                                <dd class="mb-0 text-break"><code id="dregion_summary_tag">' . h('<dregion>' . $name . '</dregion>') . '</code></dd>
                                            </div>' . $form['status'] . '
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <script>
                            $(document).on(\'input\', \'#name\', function () {
                                $(\'#dregion_summary_tag\').text(\'<dregion>\' + this.value + \'</dregion>\');
                            });
                        </script>';
}

/**
 * The two dynamic region values a save writes, in INSERT/UPDATE order:
 * the trimmed name and the PHP code.
 *
 * @param array $post  The posted fields
 * @return array
 */
function pg_dynamic_region_form_values($post)
{
    $name = $post['name'] ?? '';
    $code = $post['code'] ?? '';

    return array(
        is_scalar($name) ? trim((string) $name) : '',
        is_scalar($code) ? (string) $code : '',
    );
}

/**
 * Whether another dynamic region already has this name: <dregion> runs only
 * one row per name. Same rules as pg_cregion_name_taken(): SQL '=' under
 * the column collation, no check for an empty name, and on edit only when
 * the name differs from the stored one.
 *
 * @param string        $name    The trimmed posted name
 * @param int|null      $id      The region being edited, null on create
 * @param callable|null $lookup  function ($sql, $values) returning the first column of the first row (tests)
 * @return bool
 */
function pg_dregion_name_taken($name, $id = null, $lookup = null)
{
    $name = (string) $name;

    if ($name === '') {
        return false;
    }

    if ($lookup === null) {
        $lookup = 'pg_region_form_lookup';
    }

    $query = "SELECT COUNT(*) FROM dregion WHERE dregion_name = ?";
    $values = array($name);

    if ($id !== null) {
        if ((string) $lookup("SELECT dregion_name FROM dregion WHERE dregion_id = ?", array((int) $id)) === $name) {
            return false;
        }

        $query .= " AND dregion_id != ?";
        $values[] = (int) $id;
    }

    return (int) $lookup($query, $values) > 0;
}
