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

/**
 * The Options card: name between <dregion> tags, then the PHP code snippet.
 *
 * @param string $name  The region name ('' on create)
 * @param string $code  The PHP code ('' on create)
 * @return string
 */
function pg_dynamic_region_form_card($name = '', $code = '')
{
    return '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Dynamic Region'))) . '</label>
                                            <div class="input-group">
                                                <div class="input-group-text">' . h('<dregion>') . '</div>
                                                <input value="' . h($name) . '" name="name" id="name" type="text" class="form-control" maxlength="100" />
                                                <div class="input-group-text">' . h('</dregion>') . '</div>
                                            </div>
                                        </div>
                                        <h5 class="mt-12">' . lang('PHP Code to appear on associated Pages') . '</h5>
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
