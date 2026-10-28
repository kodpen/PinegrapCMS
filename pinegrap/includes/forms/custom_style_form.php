<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The custom page style form, shared by the create and the edit screens
 * (add_custom_style.php, edit_custom_style.php): the Page Style Options and
 * Additional Options cards and the columns a save writes. The screens keep
 * what differs: the edit screen loads the style, shows its heading and
 * Duplicate link, checks whether folders or pages use it before delete,
 * and goes back to where it was opened from.
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
 * The values a new style starts with: an empty name, a page skeleton with
 * the system region, collection A, no layout override, social networking
 * bottom left.
 *
 * @return array
 */
function pg_custom_style_form_defaults()
{
    return array(
        'style_name' => '',
        'style_code' =>
'<!DOCTYPE html>
<html lang="' . lang(array('info'=>'')) . '">
    <head>
        <meta charset="utf-8">
        <title></title>
        <meta_tags></meta_tags>
        <stylesheet></stylesheet>
    </head>
    <body>
        <pregion></pregion>
        <system></system>
        <pregion></pregion>
    </body>
</html>',
        'collection' => 'a',
        'layout_type' => '',
        'social_networking_position' => 'bottom_left',
    );
}

/**
 * The option list markup with the current value selected.
 *
 * @param array $options  Value => label (label already escaped)
 * @param string $current
 * @return string
 */
function pg_custom_style_form_options($options, $current)
{
    $output = '';

    foreach ($options as $value => $label) {
        $output .= '
                    <option value="' . h($value) . '"' . ((string) $current === (string) $value ? ' selected="selected"' : '') . '>' . $label . '</option>';
    }

    return $output;
}

/**
 * The Social Networking Position pick list.
 *
 * @param string $current
 * @return string
 */
function pg_custom_style_form_social_networking_position($current)
{
    return
            '<div class="col-12 md:col-6 lg:col-4 my-3">
                <label for="social_networking_position" class="form-label">' . lang('Social Networking Position') . '</label>
                <select id="social_networking_position" name="social_networking_position" class="form-control">' .
                    pg_custom_style_form_options(array(
                        'top_left' => lang('Top Left'),
                        'top_right' => lang('Top Right'),
                        'bottom_left' => lang('Bottom Left'),
                        'bottom_right' => lang('Bottom Right'),
                        'disabled' => lang('Disabled'),
                    ), $current) . '
                </select>
            </div>';
}

/**
 * The Page Style Options card (name, code editor) and the Additional
 * Options card (collection, layout type override, social networking
 * position when social networking is on).
 *
 * @param array $style  style_name, style_code, collection, layout_type, social_networking_position
 * @param bool $social_networking
 * @return string
 */
function pg_custom_style_form_cards($style, $social_networking)
{
    $collection_a = $style['collection'] == 'a';
    $layout_type = (string) $style['layout_type'];

    return '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Page Style Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 md:col-8 lg:col-6 my-3">
                                            <label for="name" class="form-label">' . lang('Name') . '</label>
                                            <input value="' . h($style['style_name']) . '" name="name" id="name" type="text" placeholder="' . lang('new page style') . '" class="form-control" maxlength="100" required />
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        <div class="col-12 my-3">
                                            <label class="form-label">' . lang('HTML Page with embedded Tags') . '</label>
                                            <div id="edit_custom">
                                                <textarea name="code" id="code" rows="25" cols="60" wrap="off">' . h($style['style_code']) . '</textarea>
                                                ' . pg_code_editor_attach(array('id' => 'code', 'code_type' => 'mixed')) . '
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Additional Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 mt-3">
                                            <h5>'. lang('Collection') . '</h5>
                                        </div>
                                        <div class="col-12 mb-3">
                                            <div class="form-field d-inline-grid me-5">
                                                <input type="radio" name="collection" id="collection_a" value="a"' . ($collection_a ? ' checked="checked"' : '') . ' class="radio" />
                                                <label class="" for="collection_a">' . lang('Collection') . ' A</label>
                                            </div>
                                            <div class="form-field d-inline-grid me-5">
                                                <input type="radio" name="collection" id="collection_b" value="b"' . ($collection_a ? '' : ' checked="checked"') . ' class="radio" />
                                                <label class="" for="collection_b">' . lang('Collection') . ' B</label>
                                            </div>
                                        </div>
                                        <div class="col-12 md:col-6 lg:col-4 my-3">
                                            <label for="layout_type" class="form-label">' . lang('Override Layout Type') . '</label>
                                            <select id="layout_type" name="layout_type" class="form-control">
                                                <option value=""></option>
                                                <option value="system"' . ($layout_type == 'system' ? ' selected="selected"' : '') . '>' . lang('System') . '</option>
                                                <option value="custom"' . ($layout_type == 'custom' ? ' selected="selected"' : '') . '>' . lang('Custom') . '</option>
                                            </select>
                                        </div>
                                        ' . ($social_networking ? pg_custom_style_form_social_networking_position($style['social_networking_position']) : '') . '
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>';
}

/**
 * The posted style as column => value, in INSERT/UPDATE order; the social
 * networking position only while social networking is on.
 *
 * @param array $post
 * @param bool $social_networking
 * @return array
 */
function pg_custom_style_form_values($post, $social_networking)
{
    $values = array(
        'style_name' => trim((string) ($post['name'] ?? '')),
        'style_code' => (string) ($post['code'] ?? ''),
    );

    if ($social_networking) {
        $values['social_networking_position'] = (string) ($post['social_networking_position'] ?? '');
    }

    $values['collection'] = (string) ($post['collection'] ?? '');
    $values['layout_type'] = (string) ($post['layout_type'] ?? '');

    return $values;
}
