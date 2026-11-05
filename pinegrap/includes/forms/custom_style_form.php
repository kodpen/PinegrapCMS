<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The custom page style form, shared by the create and the edit screens
 * (add_custom_style.php, edit_custom_style.php): the form column with the
 * Page Style Options card, the sticky status column with the Additional
 * Options card and, on edit, a Summary, the sticky save bar, and the
 * columns a save writes. The screens keep
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

// The name refusal texts and the error under the name input are shared with
// the region forms.
require_once(PG_FUNCTIONS_DIR . '/includes/forms/common_region_form.php');

/**
 * The values each pick list of the form accepts; the first one is the
 * default a missing or unknown value falls back to. Pure.
 *
 * @return array  column => list of values
 */
function pg_custom_style_form_choices()
{
    return array(
        'collection' => array('a', 'b'),
        'layout_type' => array('', 'system', 'custom'),
        'social_networking_position' => array('bottom_left', 'top_left', 'top_right', 'bottom_right', 'disabled'),
    );
}

/**
 * The posted value when the list accepts it, else the list's default. Pure.
 *
 * @param string $column  A key of pg_custom_style_form_choices()
 * @param mixed  $value
 * @return string
 */
function pg_custom_style_form_choice($column, $value)
{
    $choices = pg_custom_style_form_choices();
    $value = is_scalar($value) ? (string) $value : '';

    return in_array($value, $choices[$column], true) ? $value : $choices[$column][0];
}

/**
 * The Layout Type column text of the style list: the translated layout
 * override, '' for none. Pure.
 *
 * @param string $layout_type
 * @return string
 */
function pg_style_layout_type_label($layout_type)
{
    switch ((string) $layout_type) {
        case 'system':
            return lang('System');
        case 'custom':
            return lang('Custom');
    }

    return '';
}

/**
 * Which edit screen a style row belongs to: 'custom' for a custom style,
 * 'system' for a system style and for a visual design (whatever its type),
 * '' for a row that is neither. Pure.
 *
 * @param string $style_type
 * @param string $style_layout
 * @return string
 */
function pg_style_edit_screen($style_type, $style_layout)
{
    if ((string) $style_layout === 'visual_designer') {
        return 'system';
    }

    return in_array((string) $style_type, array('custom', 'system'), true) ? (string) $style_type : '';
}

/**
 * Whether another style (custom, system or visual design) already has this
 * name: the visual designer refuses to save a design whose name another
 * style holds. The comparison is SQL '=' under the column collation. An
 * empty name is not checked. On edit ($id given) the check runs only when
 * the name differs from the stored one, so a style that already shares its
 * name with another still saves its code.
 *
 * @param string        $name    The trimmed posted name
 * @param int|null      $id      The style being edited, null on create
 * @param callable|null $lookup  function ($sql, $values): first column of the first row
 * @return bool
 */
function pg_style_name_taken($name, $id = null, $lookup = null)
{
    $name = (string) $name;

    if ($name === '') {
        return false;
    }

    if ($lookup === null) {
        $lookup = 'pg_region_form_lookup';
    }

    if ($id !== null) {
        $stored = $lookup('SELECT style_name FROM style WHERE style_id = ?', array((string) (int) $id));

        if (($stored !== null) && ((string) $stored === $name)) {
            return false;
        }

        return (int) $lookup('SELECT COUNT(*) FROM style WHERE style_name = ? AND style_id <> ?', array($name, (string) (int) $id)) > 0;
    }

    return (int) $lookup('SELECT COUNT(*) FROM style WHERE style_name = ?', array($name)) > 0;
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
            '<div class="mt-3">
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
 * One row of the status column's Summary: a label and its value.
 *
 * @param string $label  Plain text
 * @param string $value  HTML
 * @param bool   $last   The last row draws no line under it
 * @return string  HTML
 */
function pg_custom_style_form_status_row($label, $value, $last = true)
{
    return '
                                            <div class="d-flex justify-content-between gap-3 py-2' . ($last ? '' : ' border-bottom') . '">
                                                <dt class="fw-normal">' . h($label) . '</dt>
                                                <dd class="mb-0 fw-bold text-end text-break">' . $value . '</dd>
                                            </div>';
}

/**
 * The edit screen's Summary rows: the folders and the pages that use the
 * style (desktop or mobile) and the last change.
 *
 * @param int    $folders  Folders using the style
 * @param int    $pages    Pages using the style
 * @param string $changed  The last change, HTML (relative time and user)
 * @return string  HTML
 */
function pg_custom_style_form_edit_status_rows($folders, $pages, $changed)
{
    return pg_custom_style_form_status_row(lang('Folders'), (string) (int) $folders, false)
        . pg_custom_style_form_status_row(lang('Pages'), (string) (int) $pages, false)
        . pg_custom_style_form_status_row(lang('Last Modified'), $changed);
}

/**
 * The two columns of the screen (edit-screen-wide-layout): the form column
 * with the Page Style Options card (name, code editor), and the sticky
 * status column with the Additional Options card (collection, layout type
 * override, social networking position when social networking is on) and,
 * when $status_rows is given (edit), a Summary card.
 *
 * @param array $style  style_name, style_code, collection, layout_type, social_networking_position
 * @param bool $social_networking
 * @param string $name_error  The refusal text under the name input, '' for none
 * @param string $status_rows  pg_custom_style_form_edit_status_rows(), '' on create
 * @return string
 */
function pg_custom_style_form_cards($style, $social_networking, $name_error = '', $status_rows = '')
{
    // Collection B only when stored as B: an empty value left by an old
    // save is shown, and saved again, as the default A.
    $collection_a = (string) $style['collection'] !== 'b';
    $layout_type = (string) $style['layout_type'];

    $summary = '';

    if ($status_rows !== '') {
        $summary = '
                                <div class="card mb-7">
                                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                        ' . lang('Summary') . '
                                    </h2>
                                    <div class="card-body pt-0">
                                        <dl class="mb-0">' . $status_rows . '
                                        </dl>
                                    </div>
                                </div>';
    }

    return '
                        <div class="col-12 lg:col-8 2xl:col-9 order-1">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Page Style Options') . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 md:col-8 lg:col-6 mb-3">
                                            <label for="name" class="form-label">' . lang('Name') . '</label>
                                            <input value="' . h($style['style_name']) . '" name="name" id="name" type="text" placeholder="' . lang('new page style') . '" class="form-control' . pg_region_form_name_error_attributes($name_error) . '" maxlength="' . pg_name_max_length() . '" required />' . pg_region_form_name_error($name_error) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        <div class="col-12 my-3">
                                            <label for="code" class="form-label">' . lang('HTML Page with embedded Tags') . '</label>
                                            <div id="edit_custom">
                                                <textarea name="code" id="code" rows="25" cols="60" wrap="off">' . h($style['style_code']) . '</textarea>
                                                ' . pg_code_editor_attach(array('id' => 'code', 'code_type' => 'mixed')) . '
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 lg:col-4 2xl:col-3 order-2">
                            <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                            <div class="position-sticky" style="top:3.3rem;">
                                <div class="card mb-7">
                                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                        ' . lang('Additional Options') . '
                                    </h2>
                                    <div class="card-body pt-0">
                                        <fieldset>
                                            <legend class="form-label">' . lang('Collection') . '</legend>
                                            <div class="form-field d-inline-grid me-5">
                                                <input type="radio" name="collection" id="collection_a" value="a"' . ($collection_a ? ' checked="checked"' : '') . ' class="radio" />
                                                <label class="" for="collection_a">' . lang('Collection') . ' A</label>
                                            </div>
                                            <div class="form-field d-inline-grid me-5">
                                                <input type="radio" name="collection" id="collection_b" value="b"' . ($collection_a ? '' : ' checked="checked"') . ' class="radio" />
                                                <label class="" for="collection_b">' . lang('Collection') . ' B</label>
                                            </div>
                                        </fieldset>
                                        <div class="mt-3">
                                            <label for="layout_type" class="form-label">' . lang('Override Layout Type') . '</label>
                                            <select id="layout_type" name="layout_type" class="form-control">
                                                <option value=""' . ($layout_type === '' ? ' selected="selected"' : '') . '>' . lang('No override (the page type layout)') . '</option>
                                                <option value="system"' . ($layout_type == 'system' ? ' selected="selected"' : '') . '>' . lang('System') . '</option>
                                                <option value="custom"' . ($layout_type == 'custom' ? ' selected="selected"' : '') . '>' . lang('Custom') . '</option>
                                            </select>
                                        </div>
                                        ' . ($social_networking ? pg_custom_style_form_social_networking_position($style['social_networking_position']) : '') . '
                                    </div>
                                </div>' . $summary . '
                            </div>
                        </div>';
}

/**
 * The screen row: the columns (pg_custom_style_form_cards()) and the
 * sticky save bar with the given buttons.
 *
 * @param string $cards    pg_custom_style_form_cards()
 * @param string $buttons  The buttons inside the bar's group
 * @return string  HTML
 */
function pg_custom_style_form_layout($cards, $buttons)
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
 * The posted style as column => value, in INSERT/UPDATE order; the social
 * networking position only while social networking is on. A pick list value
 * outside its list falls back to the list's default: the session is not
 * strict, so MySQL would store it as an empty enum.
 *
 * @param array $post
 * @param bool $social_networking
 * @return array
 */
function pg_custom_style_form_values($post, $social_networking)
{
    $values = array(
        'style_name' => trim(is_scalar($post['name'] ?? '') ? (string) ($post['name'] ?? '') : ''),
        'style_code' => is_scalar($post['code'] ?? '') ? (string) ($post['code'] ?? '') : '',
    );

    if ($social_networking) {
        $values['social_networking_position'] = pg_custom_style_form_choice('social_networking_position', $post['social_networking_position'] ?? '');
    }

    $values['collection'] = pg_custom_style_form_choice('collection', $post['collection'] ?? '');
    $values['layout_type'] = pg_custom_style_form_choice('layout_type', $post['layout_type'] ?? '');

    return $values;
}

/**
 * Why a style's name cannot be saved, '' when it can: required, at most
 * pg_name_max_length() characters (the column would cut a longer one) and
 * held by no other style (the visual designer refuses to save a design
 * whose name another style holds). Shared by the edit screen and the
 * style list's quick edit.
 *
 * @param string        $name    The trimmed name
 * @param int|null      $id      The style being edited, null on create
 * @param callable|null $lookup  pg_style_name_taken()'s lookup
 * @return string
 */
function pg_custom_style_form_name_refusal($name, $id = null, $lookup = null)
{
    return pg_region_form_name_refusal($name, pg_style_name_taken($name, $id, $lookup));
}

/**
 * Writes a custom style's values (pg_custom_style_form_values()), stamps
 * the change and logs it. Shared by the edit screen and the style list's
 * quick edit.
 *
 * @param int   $id
 * @param array $values  pg_custom_style_form_values()
 * @param int   $user_id
 * @return bool whether the UPDATE ran
 */
function pg_custom_style_form_update($id, array $values, $user_id)
{
    $result = db_q(
        "UPDATE style SET " . implode(' = ?, ', array_keys($values)) . " = ?, style_timestamp = UNIX_TIMESTAMP(), style_user = ? WHERE style_id = ?",
        array_merge(array_values($values), array((string) (int) $user_id, (string) (int) $id))
    );

    if (!$result) {
        return false;
    }

    log_activity(lang(array('string' => 'style ({var:1}) was modified', 'vars' => $values['style_name'])), $_SESSION['sessionusername'] ?? '');

    return true;
}
