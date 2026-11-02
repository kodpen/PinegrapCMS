<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The common region form, shared by the create and the edit screens
 * (add_common_region.php, edit_common_region.php): the Options card and the
 * values a save writes. The screens keep what differs: the edit screen
 * loads the record, shows its body tag, offers the region type, the shared
 * component conversion, duplicate and delete.
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
 * The Options card: name between <cregion> tags, an optional column beside
 * it, then the shared content in the rich text editor. A region made a
 * shared component shows no content: the component draws it.
 *
 * @param string $name          The region name ('' on create)
 * @param string $content       The stored content ('' on create)
 * @param string $beside        Markup of a column printed after the name (edit: the region type)
 * @param bool   $with_content  False leaves the content field out
 * @param string $name_error    Why the posted name was refused, '' for none
 * @return string
 */
function pg_common_region_form_card($name = '', $content = '', $beside = '', $with_content = true, $name_error = '')
{
    $output_content = '';

    if ($with_content) {
        $output_content = '
                                        <h3 class="h5 mt-12">' . lang('Shared Content to appear on associated Pages') . '</h3>
                                        <div class="col-12 my-3">
                                            <label for="content_textarea" class="form-label">' . lang('Content') . '</label>
                                            <textarea id="content_textarea" name="content" style="width: 100%" rows="30" wrap="off">' . h(prepare_rich_text_editor_content_for_output($content)) . '</textarea>
                                        </div>';
    }

    return '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Options') . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="name" class="form-label">' . lang('Common Region Name') . '</label>
                                            <div class="input-group">
                                                <div class="input-group-text">' . h('<cregion>') . '</div>
                                                <input value="' . h($name) . '" name="name" id="name" type="text" class="form-control' . pg_region_form_name_error_attributes($name_error) . '" maxlength="100" required="required" />
                                                <div class="input-group-text">' . h('</cregion>') . '</div>
                                            </div>' . pg_region_form_name_error($name_error) . '
                                        </div>' . $beside . $output_content . '
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
';
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
