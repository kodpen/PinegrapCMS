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
 * @return string
 */
function pg_common_region_form_card($name = '', $content = '', $beside = '', $with_content = true)
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
                                                <input value="' . h($name) . '" name="name" id="name" type="text" class="form-control" maxlength="100" />
                                                <div class="input-group-text">' . h('</cregion>') . '</div>
                                            </div>
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
