<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The dynamic region form, shared by the create and the edit screens
 * (add_dynamic_region.php, edit_dynamic_region.php): the Options card and
 * the values a save writes. The screens keep what differs: the edit screen
 * loads the record, shows its body tag and offers delete.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The name error markup and the lookup are shared with the cregion forms.
require_once(PG_FUNCTIONS_DIR . '/includes/forms/common_region_form.php');

/**
 * The Options card: name between <dregion> tags, then the PHP code snippet.
 *
 * @param string $name  The region name ('' on create)
 * @param string $code  The PHP code ('' on create)
 * @param bool   $name_taken  True marks the name as used by another region
 * @return string
 */
function pg_dynamic_region_form_card($name = '', $code = '', $name_taken = false)
{
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
                                            <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Dynamic Region'))) . '</label>
                                            <div class="input-group">
                                                <div class="input-group-text">' . h('<dregion>') . '</div>
                                                <input value="' . h($name) . '" name="name" id="name" type="text" class="form-control' . pg_region_form_name_error_attributes($name_taken) . '" maxlength="100" />
                                                <div class="input-group-text">' . h('</dregion>') . '</div>
                                            </div>' . pg_region_form_name_error($name_taken) . '
                                        </div>
                                        <h3 class="h5 mt-12">' . lang('PHP Code to appear on associated Pages') . '</h3>
                                        <div class="col-12 my-3">
                                            <label for="code" class="form-label">' . lang('PHP Code Snippet') . '</label>
                                            <textarea name="code" id="code" rows="30" cols="60" wrap="off">' . h($code) . '</textarea>
                                            ' . pg_code_editor_attach(array('id' => 'code', 'code_type' => 'php')) . '
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
';
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
