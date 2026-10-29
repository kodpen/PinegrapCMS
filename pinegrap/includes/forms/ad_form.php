<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The ad form, shared by the create and the edit screens (add_ad.php,
 * edit_ad.php): the Options and display option cards, the checks a save
 * runs and the values it writes. The screens keep what differs: the edit
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
 * The Options card (name, ad region, content) and the Dynamic Ad Region
 * Display Options card (label, sort order, caption).
 *
 * @param liveform $liveform
 * @return string
 */
function pg_ad_form_cards($liveform)
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
                                            <div class="col-12 md:col-6 lg:col-4 my-3">
                                                <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Ad'))) . '</label>
                                                ' . $liveform->output_field(array('type'=>'text', 'id'=>'name', 'name'=>'name', 'size'=>'60', 'class'=>'form-control', 'maxlength'=>'100')) . '
                                                <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                            </div>
                                            <div class="col-12 md:col-6 lg:col-4 my-3">
                                                <label for="ad_region_id" class="form-label">' . lang('Ad Region') . '</label>
                                                ' . $liveform->output_field(array('type'=>'select', 'name'=>'ad_region_id', 'id'=>'ad_region_id', 'class'=>'form-control', 'options'=>get_ad_region_options())) . '
                                                <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                                <div class="form-text">' . lang('Assign to Ad Region') . '</div>
                                            </div>
                                            <div class="col-12 mt-1 mb-3">
                                                <label for="content_textarea" class="form-label">' . lang('Ad Content') . '</label>
                                                ' . $liveform->output_field(array('type'=>'textarea', 'id'=>'content_textarea', 'name'=>'content')) . '
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="card my-7">
                                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                        ' . lang('Dynamic Ad Region Display Options') . '
                                    </h2>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-12 md:col-4 my-3">
                                                <label for="label" class="form-label">' . lang('Label') . '</label>
                                                ' . $liveform->output_field(array('type'=>'text', 'id'=>'label', 'name'=>'label', 'size'=>'60', 'class'=>'form-control', 'maxlength'=>'255')) . '
                                            </div>
                                            <div class="col-12 md:col-4 my-3">
                                                <label for="sort_order" class="form-label">' . lang('Sort Order') . '</label>
                                                ' . $liveform->output_field(array('type'=>'text', 'id'=>'sort_order', 'name'=>'sort_order', 'size'=>'5', 'class'=>'form-control', 'maxlength'=>'4')) . '
                                                <div class="form-text">' . lang('leave blank for random order') . '</div>
                                            </div>
                                            <div class="col-12 mt-1 mb-3">
                                                <label for="caption" class="form-label">' . lang('Caption') . ' ( ' . lang('Add Caption on Top of Content') . ' )</label>
                                                ' . $liveform->output_field(array('type'=>'textarea', 'id'=>'caption', 'name'=>'caption')) . '
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
';
}

/**
 * The checks a save runs: name and ad region required, the name not used
 * by another ad, and a user role may only pick an ad region it can edit.
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

    // if there is not already an error for the name field, check to see if name is already in use by a different ad
    if ($liveform->check_field_error('name') == false) {
        $query = "SELECT id FROM ads WHERE (name = ?)";
        $values = array((string) $liveform->get_field_value('name'));

        if ($id !== null) {
            $query .= " AND (id != ?)";
            $values[] = (string) $id;
        }

        $result = db_q($query, $values) or output_error('Query failed.');

        if (mysqli_num_rows($result) > 0) {
            $liveform->mark_error('name', lang('The name that you entered is already in use, so please enter a different name.'));
        }
    }

    // a user role may only put the ad into an ad region it has access to
    if (($liveform->check_field_error('ad_region_id') == false) && ($user['role'] == 3) && (in_array($liveform->get_field_value('ad_region_id'), get_items_user_can_edit('ad_regions', $user['id'])) == FALSE)) {
        $liveform->mark_error('ad_region_id', lang('You do not have access to the selected Ad Region, so please select a different Ad Region.'));
    }
}

/**
 * The six ad values a save writes, in INSERT/UPDATE order: name, content,
 * caption, ad region, label, sort order. Content and caption store the
 * site path as the {path} placeholder.
 *
 * @param liveform $liveform
 * @param string|null $path  The site path (PATH unless given)
 * @return array
 */
function pg_ad_form_values($liveform, $path = null)
{
    return array(
        (string) $liveform->get_field_value('name'),
        (string) prepare_rich_text_editor_content_for_input($liveform->get_field_value('content'), $path),
        (string) prepare_rich_text_editor_content_for_input($liveform->get_field_value('caption'), $path),
        (string) $liveform->get_field_value('ad_region_id'),
        (string) $liveform->get_field_value('label'),
        (string) $liveform->get_field_value('sort_order'),
    );
}
