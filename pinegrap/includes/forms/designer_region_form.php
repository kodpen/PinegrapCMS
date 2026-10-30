<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The designer region form, shared by the create and the edit screens
 * (add_designer_region.php, edit_designer_region.php): the Options card and
 * the values a save writes. The screens keep what differs: the edit screen
 * loads the record, shows its body tag, offers the region type, duplicate
 * and delete.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The name check and its error markup are shared with common regions: both
// live in the cregion table.
require_once(PG_FUNCTIONS_DIR . '/includes/forms/common_region_form.php');

/**
 * The Options card: name between <cregion> tags, an optional column beside
 * it, then the shared content as an HTML code snippet.
 *
 * @param string $name     The region name ('' on create)
 * @param string $content  The shared content ('' on create)
 * @param string $beside   Markup of a column printed after the name (edit: the region type)
 * @param bool   $name_taken  True marks the name as used by another region
 * @return string
 */
function pg_designer_region_form_card($name = '', $content = '', $beside = '', $name_taken = false)
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
                                            <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Designer Region'))) . '</label>
                                            <div class="input-group">
                                                <div class="input-group-text">' . h('<cregion>') . '</div>
                                                <input value="' . h($name) . '" name="name" id="name" type="text" class="form-control' . pg_region_form_name_error_attributes($name_taken) . '" maxlength="100" />
                                                <div class="input-group-text">' . h('</cregion>') . '</div>
                                            </div>' . pg_region_form_name_error($name_taken) . '
                                        </div>' . $beside . '
                                        <h3 class="h5 mt-12">' . lang('Shared Content to appear on associated Pages') . '</h3>
                                        <div class="col-12 my-3">
                                            <label for="code" class="form-label">' . lang('HTML Code Snippet') . '</label>
                                            <textarea name="content" id="code" rows="30" cols="60" wrap="off">' . h($content) . '</textarea>
                                            ' . pg_code_editor_attach(array('id' => 'code', 'code_type' => 'mixed')) . '
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
';
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
