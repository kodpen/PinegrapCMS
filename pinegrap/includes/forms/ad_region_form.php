<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The ad region form, shared by the create and the edit screens
 * (add_ad_region.php, edit_ad_region.php): the form column with the
 * Options card, the Summary column, the save bar, the name
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
 * The form column with the Options card: name between <ad> tags, display
 * type, transition type and duration, autoplay with its interval and
 * continuous play.
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
                        <div class="col-12 lg:col-8 2xl:col-9 order-1">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Options') . '
                                </h2>
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
                                        <h3 class="h5 mt-12">' . lang('Region Behavior') . '</h3>
                                        <div class="col-12">
                                            <fieldset>
                                                <legend class="form-label fs-md">' . lang('Display Type') . '</legend>
                                                <div class="form-field">
                                                    ' . $liveform->output_field($checked + array('type'=>'radio', 'name'=>'display_type', 'id'=>'static', 'value'=>'static', 'class'=>'radio collapse-switcher')) . '
                                                    <label class="" for="static">' . lang('Static') . ' (' . lang('i.e. display one ad per page view') . ')</label>
                                                </div>
                                                <div class="form-field">
                                                    ' . $liveform->output_field(array('type'=>'radio', 'name'=>'display_type', 'id'=>'dynamic', 'value'=>'dynamic', 'class'=>'radio collapse-switcher', 'data-bs-target'=>'#display_type_dynamic_row')) . '
                                                    <label class="" for="dynamic">' . lang('Dynamic') . ' (' . lang('i.e. display multiple ads per page view') . ')</label>
                                                </div>
                                            </fieldset>
                                            
                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-3 w-100" id="display_type_dynamic_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(18px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <fieldset class="col-12 my-1">
                                                            <legend class="form-label fs-md">' . lang('Transition Type') . '</legend>
                                                            <div class="form-field">
                                                                ' . $liveform->output_field($checked + array('type'=>'radio', 'name'=>'transition_type', 'id'=>'slide', 'value'=>'slide', 'class'=>'radio')) . '
                                                                <label class="" for="slide">' . lang('Slide') . '</label>
                                                            </div>
                                                            <div class="form-field">
                                                                ' . $liveform->output_field(array('type'=>'radio', 'name'=>'transition_type', 'id'=>'fade', 'value'=>'fade', 'class'=>'radio')) . '
                                                                <label class="" for="fade">' . lang('Fade') . '</label>
                                                            </div>
                                                        </fieldset>
                                                        <div class="col-12 md:col-6 lg:col-4 my-1">
                                                            <label class="form-label" for="transition_duration">' . lang('Transition Duration') . '</label>
                                                            <label class="form-control form-adorn form-adorn-end">
                                                                <span class="form-adorn-text" aria-hidden="true">' . lang('milliseconds') . '</span>
                                                                ' . $liveform->output_field(array('type'=>'text', 'name'=>'transition_duration', 'id'=>'transition_duration', 'class'=>'form-ghost text-end', 'inputmode'=>'numeric', 'maxlength'=>'4', 'data-inputmask-alias'=>'integer', 'data-inputmask-allowminus'=>'false', 'data-inputmask-placeholder'=>'0')) . '
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
                                                                                ' . $liveform->output_field(array('type'=>'text', 'name'=>'slideshow_interval', 'id'=>'slideshow_interval', 'class'=>'form-ghost text-end', 'inputmode'=>'numeric', 'maxlength'=>'3', 'data-inputmask-alias'=>'integer', 'data-inputmask-allowminus'=>'false', 'data-inputmask-placeholder'=>'0')) . '
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
                        </div>';
}

/**
 * The values the Summary starts with, as the card draws them: the stored
 * or posted ones, or a new region's defaults on its first draw.
 *
 * @param liveform $liveform
 * @param bool $defaults  As for pg_ad_region_form_card()
 * @return array  name, dynamic (bool), transition ('slide' or 'fade'),
 *                autoplay (bool), interval (string, digits or '')
 */
function pg_ad_region_form_summary_values($liveform, $defaults = false)
{
    $display = (string) $liveform->get_field_value('display_type');
    $transition = (string) $liveform->get_field_value('transition_type');
    $interval = trim((string) $liveform->get_field_value('slideshow_interval'));

    return array(
        'name' => (string) $liveform->get_field_value('name'),
        'dynamic' => !$defaults && ($display === 'dynamic'),
        'transition' => (!$defaults && ($transition === 'fade')) ? 'fade' : 'slide',
        'autoplay' => !$defaults && ((string) $liveform->get_field_value('slideshow') === '1'),
        'interval' => ctype_digit($interval) ? $interval : '',
    );
}

/**
 * The Autoplay value of the Summary: Off, or On with its interval.
 *
 * @param bool $autoplay
 * @param string $interval  Whole seconds, or '' when none was given
 * @return string  Plain text
 */
function pg_ad_region_form_autoplay_text($autoplay, $interval)
{
    if (!$autoplay) {
        return lang('Off');
    }

    return ($interval !== '') ? lang('On') . ' (' . (int) $interval . ' ' . lang('seconds') . ')' : lang('On');
}

/**
 * The sticky status column: a Summary of the body tag a page style uses,
 * the display type and, for a dynamic region, the transition and
 * autoplay, all following the boxes as they change; then the screen's
 * own rows (the ads and the last change on edit).
 *
 * @param liveform $liveform
 * @param bool $defaults  As for pg_ad_region_form_card()
 * @param string $status_rows  More rows (pg_ad_region_form_status_row()), HTML
 * @return string  HTML
 */
function pg_ad_region_form_status($liveform, $defaults = false, $status_rows = '')
{
    $values = pg_ad_region_form_summary_values($liveform, $defaults);
    $labels = array(
        'static' => lang('Static'),
        'dynamic' => lang('Dynamic'),
        'slide' => lang('Slide'),
        'fade' => lang('Fade'),
        'off' => lang('Off'),
        'on' => lang('On'),
        'seconds' => lang('seconds'),
    );
    $dynamic_class = $values['dynamic'] ? '' : ' d-none';

    return '
                        <div class="col-12 lg:col-4 2xl:col-3 order-2">
                            <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                            <div class="position-sticky" style="top:3.3rem;">
                                <div class="card mb-7">
                                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                        ' . lang('Summary') . '
                                    </h2>
                                    <div class="card-body pt-0">
                                        <dl class="mb-0" id="ad_region_summary" data-labels="' . h(json_encode($labels)) . '">
                                            <div class="py-2 border-bottom">
                                                <dt class="fw-normal">' . lang('Page Style Body Tag') . '</dt>
                                                <dd class="mb-0 text-break"><code id="ad_region_summary_tag">' . h('<ad>' . $values['name'] . '</ad>') . '</code></dd>
                                            </div>'
        . pg_ad_region_form_status_row(lang('Display Type'), h($values['dynamic'] ? $labels['dynamic'] : $labels['static']), false, 'ad_region_summary_display')
        . pg_ad_region_form_status_row(lang('Transition Type'), h($labels[$values['transition']]), false, 'ad_region_summary_transition', $dynamic_class)
        . pg_ad_region_form_status_row(lang('Autoplay'), h(pg_ad_region_form_autoplay_text($values['autoplay'], $values['interval'])), $status_rows === '', 'ad_region_summary_autoplay', $dynamic_class)
        . $status_rows . '
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <script>
                            (function () {
                                // The Summary follows the boxes as they change.
                                var summary = document.getElementById("ad_region_summary");

                                if (!summary) {
                                    return;
                                }

                                var labels = JSON.parse(summary.getAttribute("data-labels"));

                                function checked(id) {
                                    var box = document.getElementById(id);
                                    return !!(box && box.checked);
                                }

                                function update() {
                                    var name = document.getElementById("name");
                                    var interval = document.getElementById("slideshow_interval");
                                    var seconds = interval ? interval.value.trim() : "";
                                    var dynamic = checked("dynamic");
                                    var autoplay = labels.off;

                                    if (name && name.tagName === "INPUT") {
                                        document.getElementById("ad_region_summary_tag").textContent = "<ad>" + name.value + "</ad>";
                                    }

                                    if (checked("slideshow")) {
                                        autoplay = /^[0-9]+$/.test(seconds) ? labels.on + " (" + parseInt(seconds, 10) + " " + labels.seconds + ")" : labels.on;
                                    }

                                    document.getElementById("ad_region_summary_display").textContent = dynamic ? labels.dynamic : labels.static;
                                    document.getElementById("ad_region_summary_transition").textContent = checked("fade") ? labels.fade : labels.slide;
                                    document.getElementById("ad_region_summary_autoplay").textContent = autoplay;
                                    document.getElementById("ad_region_summary_transition").parentNode.classList.toggle("d-none", !dynamic);
                                    document.getElementById("ad_region_summary_autoplay").parentNode.classList.toggle("d-none", !dynamic);
                                }

                                document.addEventListener("input", function (event) {
                                    if (event.target.id === "name" || event.target.id === "slideshow_interval") {
                                        update();
                                    }
                                });
                                document.addEventListener("change", function (event) {
                                    if (event.target.name === "display_type" || event.target.name === "transition_type" || event.target.id === "slideshow") {
                                        update();
                                    }
                                });
                            })();
                        </script>';
}

/**
 * One Summary row of the status column: a label and its value.
 *
 * @param string $label  The label, plain text
 * @param string $value  The value, already HTML
 * @param bool   $last   False draws the line under the row
 * @param string $id     The value's id, for a row the script updates
 * @param string $class  More classes of the row (' d-none' while hidden)
 * @return string  HTML
 */
function pg_ad_region_form_status_row($label, $value, $last = true, $id = '', $class = '')
{
    return '
                                            <div class="d-flex justify-content-between gap-3 py-2' . ($last ? '' : ' border-bottom') . $class . '">
                                                <dt class="fw-normal">' . h($label) . '</dt>
                                                <dd class="mb-0 fw-bold text-end text-break"' . ($id !== '' ? ' id="' . h($id) . '"' : '') . '>' . $value . '</dd>
                                            </div>';
}

/**
 * The screen row: the form column, the status column and the sticky save
 * bar with the given buttons.
 *
 * @param string $columns pg_ad_region_form_card() . pg_ad_region_form_status()
 * @param string $buttons The buttons inside the bar's group
 * @return string  HTML
 */
function pg_ad_region_form_layout($columns, $buttons)
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
 * Why a posted ad region name is refused, or '' when it may be saved:
 * required, at most 100 characters (the column's length; the session is
 * not strict, so a longer name would be cut into a clash with an existing
 * one), the name rule, and not taken by another ad region.
 *
 * @param string $name
 * @param string|null $previous_name  Stored name on edit, null on create
 * @param callable $taken  function (string $name): bool, true when another region uses it
 * @return string
 */
function pg_ad_region_name_error($name, $previous_name, $taken)
{
    $name = (string) $name;

    if (trim($name) === '') {
        return lang('Name is required.');
    }

    if (pg_name_too_long($name, 100)) {
        return lang(array('string' => 'The name can be at most {var:1} characters long.', 'vars' => array(100)));
    }

    $error = pg_tag_name_error($name, $previous_name);

    if ($error !== '') {
        return $error;
    }

    if ($taken($name)) {
        return lang('The name that you entered is already in use, so please enter a different name.');
    }

    return '';
}

/**
 * Checks the posted name (pg_ad_region_name_error()) and marks an error on
 * the form.
 *
 * @param liveform $liveform
 * @param string|null $id  Edited region's id, null on create
 */
function pg_ad_region_form_validate_name($liveform, $id)
{
    $previous_name = null;

    if ($id !== null) {
        $result = db_q("SELECT name FROM ad_regions WHERE id = ?", array((string) $id)) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        $previous_name = (string) ($row['name'] ?? '');
    }

    $taken = function ($name) use ($id) {
        $in_use = pg_ad_name_in_use('ad_regions', $name, ($id === null) ? null : (int) $id);
        if ($in_use === null) {
            output_error('Query failed.');
        }
        return $in_use;
    };
    $error = pg_ad_region_name_error($liveform->get_field_value('name'), $previous_name, $taken);

    if ($error !== '') {
        $liveform->mark_error('name', $error);
    }
}

/**
 * The region behaviour fields a save refuses, as field => error: Display
 * Type static or dynamic; for a dynamic region Transition Type slide or
 * fade and Transition Duration blank or 0-9999 milliseconds; with autoplay
 * on, Interval blank or 1-999 whole seconds. The columns are enums and
 * small integers and the session is not strict, so a wrong value was
 * stored as an empty type, a negative number or a rounded one.
 *
 * @param array $fields  display_type, transition_type, transition_duration, slideshow, slideshow_interval
 * @return array
 */
function pg_ad_region_behavior_errors(array $fields)
{
    $errors = array();
    $value = function ($name) use ($fields) {
        return isset($fields[$name]) && is_scalar($fields[$name]) ? trim((string) $fields[$name]) : '';
    };

    if (!in_array($value('display_type'), array('static', 'dynamic'), true)) {
        $errors['display_type'] = lang('Please select a Display Type.');

        return $errors;
    }

    if ($value('display_type') !== 'dynamic') {
        return $errors;
    }

    if (!in_array($value('transition_type'), array('slide', 'fade'), true)) {
        $errors['transition_type'] = lang('Please select a Transition Type.');
    }

    $duration = $value('transition_duration');

    if (($duration !== '') && !preg_match('/^[0-9]{1,4}$/', $duration)) {
        $errors['transition_duration'] = lang('Transition Duration must be a whole number from 0 to 9999 milliseconds, or blank for the default.');
    }

    $interval = $value('slideshow_interval');

    if (($value('slideshow') === '1') && ($interval !== '') && (!preg_match('/^[0-9]{1,3}$/', $interval) || ((int) $interval < 1))) {
        $errors['slideshow_interval'] = lang('Interval must be a whole number of seconds from 1 to 999, or blank.');
    }

    return $errors;
}

/**
 * Marks the region behaviour errors (pg_ad_region_behavior_errors()) on
 * the form.
 *
 * @param liveform $liveform
 */
function pg_ad_region_form_validate_behavior($liveform)
{
    $fields = array();

    foreach (array('display_type', 'transition_type', 'transition_duration', 'slideshow', 'slideshow_interval') as $name) {
        $fields[$name] = $liveform->get_field_value($name);
    }

    foreach (pg_ad_region_behavior_errors($fields) as $name => $error) {
        $liveform->mark_error($name, $error);
    }
}

/**
 * The six region behaviour values a save writes, in INSERT/UPDATE order:
 * display type, transition type, transition duration, autoplay, interval,
 * continuous play. A value the form does not show for the chosen type (a
 * static region's transition, an interval with autoplay off) is not
 * checked, so it is written as its default instead of as posted.
 *
 * @param liveform $liveform
 * @return array
 */
function pg_ad_region_form_values($liveform)
{
    $fields = array();
    foreach (array('display_type', 'transition_type', 'transition_duration', 'slideshow', 'slideshow_interval', 'slideshow_continuous') as $name) {
        $fields[$name] = $liveform->get_field_value($name);
    }
    return pg_ad_region_behavior_values($fields);
}

/**
 * pg_ad_region_form_values() over plain fields, for a caller without a
 * form (the external API). Pure.
 *
 * @param array $fields  display_type, transition_type, transition_duration, slideshow, slideshow_interval, slideshow_continuous
 * @return array
 */
function pg_ad_region_behavior_values(array $fields)
{
    $value = function ($name) use ($fields) {
        return isset($fields[$name]) && is_scalar($fields[$name]) ? trim((string) $fields[$name]) : '';
    };
    $type = in_array($value('transition_type'), array('slide', 'fade'), true) ? $value('transition_type') : 'slide';
    $duration = preg_match('/^[0-9]{1,4}$/', $value('transition_duration')) ? $value('transition_duration') : '0';
    $interval = (preg_match('/^[0-9]{1,3}$/', $value('slideshow_interval')) && ((int) $value('slideshow_interval') >= 1)) ? $value('slideshow_interval') : '0';
    return array(
        ($value('display_type') === 'dynamic') ? 'dynamic' : 'static',
        $type,
        $duration,
        ($value('slideshow') === '1') ? '1' : '0',
        $interval,
        ($value('slideshow_continuous') === '1') ? '1' : '0',
    );
}
