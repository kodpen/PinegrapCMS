<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The ad region form, shared by the create and the edit screens
 * (add_ad_region.php, edit_ad_region.php): the Options card, the name
 * check and the region behaviour values a save writes. The screens keep
 * what differs: the edit screen checks a user's access, shows the name
 * only to a designer, offers delete when no ad is assigned, and goes back
 * to where it was opened from.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/tag_name_rule.php');

/**
 * The Options card: name between <ad> tags, display type, transition
 * type and duration, autoplay with its interval and continuous play.
 *
 * @param liveform $liveform
 * @param string $name_field  Markup of the name (a field, or the stored name for a manager)
 * @param bool $defaults  Check static, slide and continuous play as a new region's defaults
 * @return string
 */
function pg_ad_region_form_card($liveform, $name_field, $defaults = false)
{
    $checked = $defaults ? array('checked' => 'checked') : array();

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
                                            <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Ad Region'))) . '</label>
                                            <div class="input-group">
                                                <div class="input-group-text">' . h('<ad>') . '</div>
                                                ' . $name_field . '
                                                <div class="input-group-text">' . h('</ad>') . '</div>
                                            </div>
                                        </div>
                                        <h5 class="mt-12">' . lang('Region Behavior') . '</h5>
                                        <div class="col-12">
                                            <label class="form-label">' . lang('Display Type') . '</label>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-field">
                                                ' . $liveform->output_field($checked + array('type'=>'radio', 'name'=>'display_type', 'id'=>'static', 'value'=>'static', 'class'=>'radio collapse-switcher')) . '
                                                <label class="" for="static">' . lang('Static') . ' (' . lang('i.e. display one ad per page view') . ')</label>
                                            </div>
                                            <div class="form-field">
                                                ' . $liveform->output_field(array('type'=>'radio', 'name'=>'display_type', 'id'=>'dynamic', 'value'=>'dynamic', 'class'=>'radio collapse-switcher', 'data-bs-target'=>'#display_type_dynamic_row')) . '
                                                <label class="" for="dynamic">' . lang('Dynamic') . ' (' . lang('i.e. display multiple ads per page view') . ')</label>
                                            </div>
                                            
                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-3 w-100" id="display_type_dynamic_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(18px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 my-1">
                                                            <label class="form-label">' . lang('Transition Type') . '</label>
                                                        </div>
                                                        <div class="col-12 my-1">
                                                            <div class="form-field">
                                                                ' . $liveform->output_field($checked + array('type'=>'radio', 'name'=>'transition_type', 'id'=>'slide', 'value'=>'slide', 'class'=>'radio')) . '
                                                                <label class="" for="slide">' . lang('Slide') . '</label>
                                                            </div>
                                                            <div class="form-field">
                                                                ' . $liveform->output_field(array('type'=>'radio', 'name'=>'transition_type', 'id'=>'fade', 'value'=>'fade', 'class'=>'radio')) . '
                                                                <label class="" for="fade">' . lang('Fade') . '</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 md:col-6 lg:col-4 my-1">
                                                            <label class="form-label" for="transition_duration">' . lang('Transition Duration') . '</label>
                                                            <label class="form-control form-adorn form-adorn-end">
                                                                <span class="form-adorn-text" aria-hidden="true">' . lang('milliseconds') . '</span>
                                                                ' . $liveform->output_field(array('type'=>'text', 'name'=>'transition_duration', 'id'=>'transition_duration', 'class'=>'form-ghost text-end', 'inputmode'=>'numeric', 'maxlength'=>'4', 'data-inputmask-alias'=>'decimal', 'data-inputmask-placeholder'=>'0')) . '
                                                            </label>
                                                            <div class="form-text text-end">' . lang('leave blank for default, 1 for instant, 1000 for 1 second') . '</div>
                                                        </div>
                                                        <div class="col-12 my-1">
                                                            <div class="form-field">
                                                                <div class="switch">' . $liveform->output_field(array('type'=>'checkbox', 'name'=>'slideshow', 'id'=>'slideshow', 'value'=>'1', 'class'=>'collapse-switcher no-bs', 'data-bs-target'=>'#slideshow_row')) . '</div>
                                                                <label class="" for="slideshow">' . lang('Enable Autoplay') . '</label>
                                                            </div>
                                                            <div class="collapse popover  fade bs-popover-bottom p-0 mb-3 w-100" id="slideshow_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                                <div class="popover-body">
                                                                    <div class="row">
                                                                        <div class="col-12 md:col-6 lg:col-4 my-1">
                                                                            <label class="form-label" for="slideshow_interval">' . lang('Interval') . '</label>
                                                                            <label class="form-control form-adorn form-adorn-end">
                                                                                <span class="form-adorn-text" aria-hidden="true">' . lang('seconds') . '</span>
                                                                                ' . $liveform->output_field(array('type'=>'text', 'name'=>'slideshow_interval', 'id'=>'slideshow_interval', 'class'=>'form-ghost text-end', 'inputmode'=>'numeric', 'maxlength'=>'3', 'data-inputmask-alias'=>'decimal', 'data-inputmask-placeholder'=>'0')) . '
                                                                            </label>
                                                                        </div>
                                                                        <div class="col-12 my-1">
                                                                            <div class="form-field">
                                                                                <div class="switch">' . $liveform->output_field($checked + array('type'=>'checkbox', 'name'=>'slideshow_continuous', 'id'=>'slideshow_continuous', 'value'=>'1', 'class'=>'no-bs')) . '</div>
                                                                                <label class="" for="slideshow_continuous">' . lang('Play Continuously') . '</label>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>';
}

/**
 * Checks the posted name: required, the name rule, not used by another ad
 * region. Errors are marked on the form.
 *
 * @param liveform $liveform
 * @param string|null $id  Edited region's id, null on create
 */
function pg_ad_region_form_validate_name($liveform, $id)
{
    $liveform->validate_required_field('name', lang('Name is required.'));

    if ($liveform->check_field_error('name') == false) {
        $previous_name = null;

        if ($id !== null) {
            $result = db_q("SELECT name FROM ad_regions WHERE id = ?", array((string) $id)) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            $previous_name = (string) ($row['name'] ?? '');
        }

        $error = pg_tag_name_error($liveform->get_field_value('name'), $previous_name);

        if ($error !== '') {
            $liveform->mark_error('name', $error);
        }
    }

    if ($liveform->check_field_error('name') == false) {
        $query = "SELECT id FROM ad_regions WHERE (name = ?)";
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
}

/**
 * The six region behaviour values a save writes, in INSERT/UPDATE order:
 * display type, transition type, transition duration, autoplay, interval,
 * continuous play.
 *
 * @param liveform $liveform
 * @return array
 */
function pg_ad_region_form_values($liveform)
{
    return array(
        (string) $liveform->get_field_value('display_type'),
        (string) $liveform->get_field_value('transition_type'),
        (string) $liveform->get_field_value('transition_duration'),
        (string) $liveform->get_field_value('slideshow'),
        (string) $liveform->get_field_value('slideshow_interval'),
        (string) $liveform->get_field_value('slideshow_continuous'),
    );
}
