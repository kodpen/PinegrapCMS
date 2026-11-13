<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The ad form, shared by the create and the edit screens (add_ad.php,
 * edit_ad.php): the form column with the Options and display option
 * cards, the sticky status column with the Ad Region and a Summary, the
 * sticky save bar, the checks a save runs and the values it writes. The screens keep what differs: the edit
 * screen loads the record, checks the user's access to the ad's region,
 * offers delete and goes back to where it was opened from.
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
 * The form column: the Options card (name, content) and the Dynamic Ad
 * Region Display Options card (label, sort order, caption). The Ad Region
 * select sits in the status column (pg_ad_form_status()).
 *
 * @param liveform $liveform
 * @return string
 */
function pg_ad_form_cards($liveform)
{
    return '
                        <div class="col-12 lg:col-8 2xl:col-9 order-1">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Options') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="row">
                                        <div class="col-12 md:col-8 my-3">
                                            <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Ad'))) . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'id'=>'name', 'name'=>'name', 'size'=>'60', 'class'=>'form-control', 'maxlength'=>'100', 'required'=>'required')) . '
                                        </div>
                                        <div class="col-12 mt-1 mb-3">
                                            <label for="content_textarea" class="form-label">' . lang('Ad Content') . '</label>
                                            ' . $liveform->output_field(array('type'=>'textarea', 'id'=>'content_textarea', 'name'=>'content')) . '
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Dynamic Ad Region Display Options') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="row">
                                        <div class="col-12 md:col-8 my-3">
                                            <label for="label" class="form-label">' . lang('Label') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'id'=>'label', 'name'=>'label', 'size'=>'60', 'class'=>'form-control', 'maxlength'=>'255')) . '
                                        </div>
                                        <div class="col-12 md:col-4 my-3">
                                            <label for="sort_order" class="form-label">' . lang('Sort Order') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'id'=>'sort_order', 'name'=>'sort_order', 'size'=>'5', 'class'=>'form-control', 'maxlength'=>'4', 'inputmode'=>'numeric')) . '
                                            <div class="form-text">' . lang('leave blank for random order') . '</div>
                                        </div>
                                        <div class="col-12 mt-1 mb-3">
                                            <label for="caption" class="form-label">' . lang('Caption') . ' (' . lang('Add Caption on Top of Content') . ')</label>
                                            ' . $liveform->output_field(array('type'=>'textarea', 'id'=>'caption', 'name'=>'caption')) . '
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>';
}

/**
 * The sticky status column: the Ad Region card with its select, then a
 * Summary of the chosen region and the sort order (blank reads Random),
 * then the screen's own rows (the last change on edit). The Summary
 * follows the fields as they change.
 *
 * @param liveform $liveform
 * @param string $status_rows  More rows (pg_ad_form_status_row()), HTML
 * @return string
 */
function pg_ad_form_status($liveform, $status_rows = '')
{
    global $user;

    $region_options = get_ad_region_options();

    return '
                        <div class="col-12 lg:col-4 2xl:col-3 order-2">
                            <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                            <div class="position-sticky" style="top:3.3rem;">
                                <div class="card mb-7">
                                    <h2 class="card-header bg-reset border-0 pg-title-section">
                                        <label for="ad_region_id" class="mb-0">' . lang('Ad Region') . '</label>
                                    </h2>
                                    <div class="card-body pt-0">
                                        ' . $liveform->output_field(array('type'=>'select', 'name'=>'ad_region_id', 'id'=>'ad_region_id', 'class'=>'form-control', 'options'=>$region_options, 'required'=>'required')) . '
                                        <div class="form-text">' . lang('Assign to Ad Region') . '</div>
                                        ' . pg_ad_form_region_hint($region_options, $user['role']) . '
                                    </div>
                                </div>
                                <div class="card mb-7">
                                    <h2 class="card-header bg-reset border-0 pg-title-section">
                                        ' . lang('Summary') . '
                                    </h2>
                                    <div class="card-body pt-0">
                                        <dl class="mb-0">'
        . pg_ad_form_status_row(lang('Ad Region'), '<span id="ad_summary_region">' . h(pg_ad_form_region_label($region_options, $liveform->get_field_value('ad_region_id'))) . '</span>', false)
        . pg_ad_form_status_row(lang('Sort Order'), '<span id="ad_summary_sort_order">' . h(pg_ad_form_sort_order_label($liveform->get_field_value('sort_order'))) . '</span>', $status_rows === '')
        . $status_rows . '
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <script>
                            (function () {
                                var region = document.getElementById("ad_region_id");
                                var sortOrder = document.getElementById("sort_order");
                                var showRegion = function () {
                                    var option = region.options[region.selectedIndex];
                                    document.getElementById("ad_summary_region").textContent = (option && option.value !== "") ? option.text : "—";
                                };
                                var showSortOrder = function () {
                                    var value = sortOrder.value.trim();
                                    document.getElementById("ad_summary_sort_order").textContent = value === "" ? ' . json_encode(lang('Random')) . ' : value;
                                };
                                region.addEventListener("change", showRegion);
                                sortOrder.addEventListener("input", showSortOrder);
                                sortOrder.addEventListener("change", showSortOrder);
                            })();
                        </script>';
}

/**
 * The name of the chosen ad region for the Summary, or a dash when none
 * is chosen or the id is not in the list.
 *
 * @param array $options  get_ad_region_options(): label => id
 * @param mixed $value  The chosen ad region id
 * @return string  Plain text
 */
function pg_ad_form_region_label(array $options, $value)
{
    $value = (string) $value;

    if ($value !== '') {
        foreach ($options as $label => $id) {
            if ((string) $id === $value) {
                return (string) $label;
            }
        }
    }

    return "\u{2014}";
}

/**
 * The sort order for the Summary: the number as typed, or Random when blank.
 *
 * @param mixed $value
 * @return string  Plain text
 */
function pg_ad_form_sort_order_label($value)
{
    $value = trim((string) $value);

    return ($value === '') ? lang('Random') : $value;
}

/**
 * One Summary row of the status column: a label and its value.
 *
 * @param string $label  The label, plain text
 * @param string $value  The value, already HTML
 * @param bool   $last   False draws the line under the row
 * @return string  HTML
 */
function pg_ad_form_status_row($label, $value, $last = true)
{
    return '
                                            <div class="d-flex justify-content-between gap-3 py-2' . ($last ? '' : ' border-bottom') . '">
                                                <dt class="fw-normal">' . h($label) . '</dt>
                                                <dd class="mb-0 fw-bold text-end text-break">' . $value . '</dd>
                                            </div>';
}

/**
 * The screen row: the form column, the status column and the sticky save
 * bar with the given buttons.
 *
 * @param string $columns  pg_ad_form_cards() . pg_ad_form_status()
 * @param string $buttons  The buttons inside the bar's group
 * @return string  HTML
 */
function pg_ad_form_layout($columns, $buttons)
{
    return '
                    <div class="row">' . $columns . '
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
 * The line under the Ad Region select when no ad region exists yet, so the
 * required select does not just refuse the save. Designers (role 1 and
 * above) get a link to Create Ad Region; other roles cannot open that
 * screen, so they are told a designer creates ad regions.
 *
 * @param array $options  get_ad_region_options(): a blank first entry, then one per region
 * @param int|string $role  The signed-in user's role
 * @return string  The hint markup, or '' when a region exists
 */
function pg_ad_form_region_hint(array $options, $role)
{
    foreach ($options as $value) {
        if ((string) $value !== '') {
            return '';
        }
    }

    if ((int) $role <= 1) {
        return '<div class="form-text">' . lang('No ad region exists yet.') . ' <a href="add_ad_region.php" class="pg-link-body-emphasis">' . lang('Create Ad Region') . '</a></div>';
    }

    return '<div class="form-text">' . lang('No ad region exists yet.') . ' ' . lang('A designer creates ad regions.') . '</div>';
}

/**
 * The checks a save runs: name required, at most 100 characters (the
 * column's length; the session is not strict, so a longer name would be
 * cut into a clash with an existing ad) and not used by another ad; the ad
 * region required and still there; a user role may only pick an ad region
 * it can edit.
 *
 * @param liveform $liveform
 * @param array $user  The signed-in user (validate_user())
 * @param string|null $id  The ad being edited, null on create
 * @return void
 */
function pg_ad_form_validate($liveform, $user, $id)
{
    $liveform->validate_required_field('name', lang('Name is required.'));
    $liveform->validate_required_field('ad_region_id', lang('Ad Region is required.'));

    if (($liveform->check_field_error('name') == false) && pg_name_too_long($liveform->get_field_value('name'), 100)) {
        $liveform->mark_error('name', lang(array('string' => 'The name can be at most {var:1} characters long.', 'vars' => array(100))));
    }

    // if there is not already an error for the name field, check to see if name is already in use by a different ad
    if ($liveform->check_field_error('name') == false) {
        $taken = pg_ad_name_in_use('ads', $liveform->get_field_value('name'), ($id === null) ? null : (int) $id);
        if ($taken === null) {
            output_error('Query failed.');
        }
        if ($taken) {
            $liveform->mark_error('name', lang('The name that you entered is already in use, so please enter a different name.'));
        }
    }
    // the region may have been deleted while the form was open, or the id was made up
    if ($liveform->check_field_error('ad_region_id') == false) {
        $exists = pg_ad_region_exists($liveform->get_field_value('ad_region_id'));
        if ($exists === null) {
            output_error('Query failed.');
        }
        if (!$exists) {
            $liveform->mark_error('ad_region_id', lang('The selected Ad Region was not found, so please select a different Ad Region.'));
        }
    }
    $sort_order_error = pg_ad_form_sort_order_error($liveform->get_field_value('sort_order'));

    if ($sort_order_error !== '') {
        $liveform->mark_error('sort_order', $sort_order_error);
    }

    // a user role may only put the ad into an ad region it has access to
    if (($liveform->check_field_error('ad_region_id') == false) && ($user['role'] == 3) && (in_array($liveform->get_field_value('ad_region_id'), get_items_user_can_edit('ad_regions', $user['id'])) == FALSE)) {
        $liveform->mark_error('ad_region_id', lang('You do not have access to the selected Ad Region, so please select a different Ad Region.'));
    }
}

/**
 * The error for a Sort Order value, or '' when it is fine: blank (random
 * order) or a whole number of up to four digits, as the field allows. The
 * column is an integer, so anything else was stored as 0 or cut, and the
 * ad went back to random order without a word.
 *
 * @param mixed $value  The posted value
 * @return string
 */
function pg_ad_form_sort_order_error($value)
{
    $value = trim((string) $value);

    if (($value === '') || preg_match('/^[0-9]{1,4}$/', $value)) {
        return '';
    }

    return lang('Sort Order must be a whole number, or blank for random order.');
}

/**
 * The six ad values a save writes (pg_ad_save()): name, content, caption,
 * ad region, label, sort order. Content and caption store the site path as
 * the {path} placeholder.
 *
 * @param liveform $liveform
 * @param string|null $path  The site path (PATH unless given)
 * @return array
 */
function pg_ad_form_values($liveform, $path = null)
{
    return array(
        'name' => (string) $liveform->get_field_value('name'),
        'content' => (string) prepare_rich_text_editor_content_for_input($liveform->get_field_value('content'), $path),
        'caption' => (string) prepare_rich_text_editor_content_for_input($liveform->get_field_value('caption'), $path),
        'ad_region_id' => (string) $liveform->get_field_value('ad_region_id'),
        'label' => (string) $liveform->get_field_value('label'),
        'sort_order' => (string) $liveform->get_field_value('sort_order'),
    );
}
