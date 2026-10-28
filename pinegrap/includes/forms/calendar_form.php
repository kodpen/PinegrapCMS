<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The calendar form, shared by the create and the edit screens
 * (add_calendar.php, edit_calendar.php): the Options card and the name
 * check. The screens keep what differs: the edit screen loads the record,
 * checks the user's access to the calendar and offers delete while the
 * calendar holds no event.
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
 * The Options card: the calendar name.
 *
 * @param liveform $liveform
 * @return string
 */
function pg_calendar_form_card($liveform)
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
                                            <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Calendar'))) . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'class'=>'form-control', 'maxlength'=>'100', 'required'=>'required')) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>';
}

/**
 * Checks the posted name: required, not used by another calendar. Errors
 * are marked on the form; a missing name skips the lookup.
 *
 * @param liveform $liveform
 * @param string|null $id  Edited calendar's id, null on create
 */
function pg_calendar_form_validate_name($liveform, $id)
{
    $liveform->validate_required_field('name', lang('Name is required.'));

    if ($liveform->check_field_error('name') == true) {
        return;
    }

    if ($id === null) {
        $result = db_q("SELECT id FROM calendars WHERE (name = ?)", array((string) $liveform->get_field_value('name'))) or output_error('Query failed.');
    } else {
        $result = db_q("SELECT id FROM calendars WHERE (name = ?) AND (id != ?)", array((string) $liveform->get_field_value('name'), (string) $id)) or output_error('Query failed.');
    }

    if (mysqli_num_rows($result) > 0) {
        $liveform->mark_error('name', lang('The name that you entered is already in use, so please enter a different name.'));
    }
}
