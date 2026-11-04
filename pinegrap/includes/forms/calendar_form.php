<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The calendar form, shared by the create and the edit screens
 * (add_calendar.php, edit_calendar.php): the form column with the Options
 * card, the sticky status column with the Summary, the sticky save bar and
 * the name check. The screens keep what differs: the edit screen loads the record,
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
 * The form column (the Options card with the calendar name) and the sticky
 * status column (a Summary of the name as typed, then the screen's own
 * rows: the event count and the last change on edit).
 *
 * @param liveform $liveform
 * @param string   $status_rows  More Summary rows (pg_calendar_form_status_row()), HTML
 * @return string
 */
function pg_calendar_form_cards($liveform, $status_rows = '')
{
    return '
                        <div class="col-12 lg:col-8 2xl:col-9 order-1">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Options') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="row">
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Calendar'))) . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'class'=>'form-control', 'maxlength'=>'100', 'required'=>'required')) . '
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
                                        ' . lang('Summary') . '
                                    </h2>
                                    <div class="card-body pt-0">
                                        <dl class="mb-0">' . pg_calendar_form_status_row(lang('Name'), '<span id="calendar_summary_name">' . h((string) $liveform->get_field_value('name')) . '</span>', $status_rows === '') . $status_rows . '
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <script>
                            // The Summary follows the name as it is typed.
                            document.getElementById("name").addEventListener("input", function () {
                                document.getElementById("calendar_summary_name").textContent = this.value;
                            });
                        </script>';
}

/**
 * One Summary row of the status column: a label and its value.
 *
 * @param string $label  The label, plain text
 * @param string $value  The value, already HTML
 * @param bool   $last   False draws the line under the row
 * @return string
 */
function pg_calendar_form_status_row($label, $value, $last = true)
{
    return '
                                            <div class="d-flex justify-content-between gap-3 py-2' . ($last ? '' : ' border-bottom') . '">
                                                <dt class="fw-normal">' . h($label) . '</dt>
                                                <dd class="mb-0 fw-bold text-end text-break">' . $value . '</dd>
                                            </div>';
}

/**
 * The screen row: the columns (pg_calendar_form_cards()) and the sticky save
 * bar with the given buttons and an optional note under them.
 *
 * @param string $cards    pg_calendar_form_cards()
 * @param string $buttons  The buttons inside the bar's group
 * @param string $note     HTML under the buttons (why delete is off), or ''
 * @return string
 */
function pg_calendar_form_layout($cards, $buttons, $note = '')
{
    return '
                    <div class="row">' . $cards . '
                        <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                            <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                                <div class="container">
                                    <div class="btn-group flex-wrap justify-content-center">' . $buttons . '
                                    </div>' . $note . '
                                </div>
                            </nav>
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
    pg_calendar_form_check_lengths($liveform, array('name' => array(lang('Name'), 100)));

    if ($liveform->check_field_error('name') == true) {
        return;
    }

    $taken = pg_calendar_name_taken($liveform->get_field_value('name'), (int) $id);

    if ($taken === null) {
        output_error('Query failed.');
    }

    if ($taken) {
        $liveform->mark_error('name', lang('The name that you entered is already in use, so please enter a different name.'));
    }
}
