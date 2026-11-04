<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The common region form, shared by the create and the edit screens
 * (add_common_region.php, edit_common_region.php): the form and status
 * columns with the save bar, the region type field and the values a save
 * writes. The screens keep what differs: the edit screen loads the record,
 * adds its Summary rows, the shared component conversion, duplicate and
 * delete.
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
 * The form column (Options card: the name between <cregion> tags and, on
 * edit, the region type; Content card: the shared content in the rich text
 * editor) and the sticky status column (a Summary of the body tag and the
 * region type, plus the screen's own rows), in the edit screen layout of
 * product_builder.php. Below lg the status column follows the form in
 * source order. A region made a shared component shows no content: the
 * component draws it.
 *
 * @param string $name     The region name ('' on create)
 * @param string $content  The stored or posted content ('' on create)
 * @param array  $form     'type' (null on create: no type field, the region
 *                         is created common; else 'common' or 'designer'),
 *                         'type_locked' (a shared component keeps the
 *                         common type), 'with_content' (false leaves the
 *                         content card out), 'name_error' (why the posted
 *                         name was refused, '' for none), 'status' (extra
 *                         Summary rows, HTML)
 * @return string
 */
function pg_common_region_form_cards($name = '', $content = '', $form = array())
{
    $form = array_merge(array('type' => null, 'type_locked' => false, 'with_content' => true, 'name_error' => '', 'status' => ''), $form);
    $type_labels = pg_common_region_form_type_labels();
    $type = ((string) $form['type'] === 'designer') ? 'designer' : 'common';

    $output_type = '';

    if ($form['type'] !== null) {
        $options = '';

        foreach ($type_labels as $value => $label) {
            $options .= '<option value="' . $value . '"' . ($type == $value ? ' selected="selected"' : '') . '>' . h($label) . '</option>';
        }

        $output_type = '
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="type" class="form-label">' . lang('Region Type') . '</label>
                                            <select name="type" id="type" class="form-control"' . ($form['type_locked'] ? ' disabled' : '') . '>' . $options . '</select>
                                            ' . ($form['type_locked'] ? '<input type="hidden" name="type" value="common">' : '<div class="form-text">' . lang('You can change Region Type') . '</div>') . '
                                        </div>';
    }

    $output_content = '';

    if ($form['with_content']) {
        $output_content = '
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Shared Content to appear on associated Pages') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="my-3">
                                        <label for="content_textarea" class="form-label">' . lang('Content') . '</label>
                                        <textarea id="content_textarea" name="content" style="width: 100%" rows="30" wrap="off">' . h(prepare_rich_text_editor_content_for_output($content)) . '</textarea>
                                    </div>
                                </div>
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
                                            <label for="name" class="form-label">' . lang('Common Region Name') . '</label>
                                            <div class="input-group">
                                                <div class="input-group-text">' . h('<cregion>') . '</div>
                                                <input value="' . h($name) . '" name="name" id="name" type="text" class="form-control' . pg_region_form_name_error_attributes($form['name_error']) . '" maxlength="100" required="required" />
                                                <div class="input-group-text pg-tag-close">' . h('</cregion>') . '</div>
                                            </div>' . pg_region_form_name_error($form['name_error']) . '
                                        </div>' . $output_type . '
                                    </div>
                                </div>
                            </div>' . $output_content . '
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
 * The shown name of each region type the edit screen offers.
 *
 * @return array  type field value => label
 */
function pg_common_region_form_type_labels()
{
    return array(
        'common' => lang('Common Region'),
        'designer' => lang('Designer Region'),
    );
}

/**
 * One Summary row of the status column: a label and its value.
 *
 * @param string $label  The label, plain text
 * @param string $value  The value, already HTML
 * @param bool   $last   False draws the line under the row
 * @return string
 */
function pg_common_region_form_status_row($label, $value, $last = true)
{
    return '
                                            <div class="d-flex justify-content-between gap-3 py-2' . ($last ? '' : ' border-bottom') . '">
                                                <dt class="fw-normal">' . h($label) . '</dt>
                                                <dd class="mb-0 fw-bold text-end text-break">' . $value . '</dd>
                                            </div>';
}

/**
 * The screen row: the cards (pg_common_region_form_cards()) and the sticky
 * save bar with the given buttons.
 *
 * @param string $cards    pg_common_region_form_cards()
 * @param string $buttons  The buttons inside the bar's group
 * @return string
 */
function pg_common_region_form_layout($cards, $buttons)
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
 * The two common region values a save writes, in INSERT/UPDATE order: the
 * trimmed name and the content as posted (the screens prepare it for the
 * database).
 *
 * @param array $post  The posted fields
 * @return array
 */
function pg_common_region_form_values($post)
{
    $name = $post['name'] ?? '';
    $content = $post['content'] ?? '';

    return array(
        is_scalar($name) ? trim((string) $name) : '',
        is_scalar($content) ? (string) $content : '',
    );
}

/**
 * Why a posted region name is refused, '' when it is not: a name is
 * required (blank after the trim), fits the 100-character name column (the
 * session is not strict, so a longer one would be cut without an error and
 * could come out as a name another region has) and is not used by another
 * region of the same table. Pure: the caller asks the table.
 *
 * @param string $name   The trimmed posted name
 * @param bool   $taken  pg_cregion_name_taken() / pg_dregion_name_taken()
 * @return string
 */
function pg_region_form_name_refusal($name, $taken)
{
    $name = (string) $name;

    if ($name === '') {
        return lang('Name is required.');
    }

    if (pg_name_too_long($name)) {
        return lang(array('string' => 'The name can be at most {var:1} characters long.', 'vars' => array(pg_name_max_length())));
    }

    if ($taken) {
        return lang('The name that you entered is already in use, so please enter a different name.');
    }

    return '';
}

/**
 * The name error a refused save left on the screen's liveform, as plain
 * text; '' when the name was not refused.
 *
 * @param liveform $liveform
 * @return string
 */
function pg_region_form_name_error_text($liveform)
{
    if (!$liveform->check_field_error('name')) {
        return '';
    }

    $texts = $liveform->field_error_texts();

    return (isset($texts['name']) && ((string) $texts['name'] !== '')) ? (string) $texts['name'] : lang('The name that you entered is already in use, so please enter a different name.');
}

/**
 * The rest of the name input's class attribute and its error attributes:
 * empty unless the name was refused.
 *
 * @param string $name_error  The refusal text, '' for none
 * @return string
 */
function pg_region_form_name_error_attributes($name_error)
{
    return ((string) $name_error !== '') ? ' is-invalid" aria-invalid="true" aria-describedby="name_error' : '';
}

/**
 * The message printed under the name input when the name was refused.
 *
 * @param string $name_error  The refusal text, '' for none
 * @return string
 */
function pg_region_form_name_error($name_error)
{
    if ((string) $name_error === '') {
        return '';
    }

    return '
                                            <div id="name_error" class="invalid-feedback d-block">' . h($name_error) . '</div>';
}

/**
 * Whether another common or designer region already has this name: both
 * kinds share the cregion table and the <cregion> tag, and the site reads
 * only one row per name. The comparison is SQL '=' under the column
 * collation. An empty name is not checked. On edit ($id given) the check
 * runs only when the name differs from the stored one, so a region that
 * already shares its name with another still saves its content.
 *
 * @param string        $name    The trimmed posted name
 * @param int|null      $id      The region being edited, null on create
 * @param callable|null $lookup  function ($sql, $values) returning the first column of the first row (tests)
 * @return bool
 */
function pg_cregion_name_taken($name, $id = null, $lookup = null)
{
    $name = (string) $name;

    if ($name === '') {
        return false;
    }

    if ($lookup === null) {
        $lookup = 'pg_region_form_lookup';
    }

    $query = "SELECT COUNT(*) FROM cregion WHERE cregion_name = ?";
    $values = array($name);

    if ($id !== null) {
        if ((string) $lookup("SELECT cregion_name FROM cregion WHERE cregion_id = ?", array((int) $id)) === $name) {
            return false;
        }

        $query .= " AND cregion_id != ?";
        $values[] = (int) $id;
    }

    return (int) $lookup($query, $values) > 0;
}

/**
 * The first column of the first row a bound query returns (null when no
 * row); a failed query stops the screen.
 *
 * @param string $sql
 * @param array  $values
 * @return string|null
 */
function pg_region_form_lookup($sql, $values)
{
    $result = db_q($sql, $values) or output_error('Query failed.');
    $row = mysqli_fetch_row($result);

    return $row ? $row[0] : null;
}
