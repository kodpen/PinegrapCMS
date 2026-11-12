<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The calendar event location form, shared by the create and the edit
 * screens (add_calendar_event_location.php,
 * edit_calendar_event_location.php): the form column with the Options
 * card, the sticky status column with the Summary, the sticky save bar and
 * the check of a name another location uses. The screens keep what differs: the edit
 * screen loads the record and offers delete. Both write through
 * includes/fn/calendar_writes.php.
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
 * The form column (the Options card with the location name) and the sticky
 * status column (a Summary of the name as typed, then the screen's own
 * rows: the events booking the location and the last change on edit).
 *
 * @param liveform $liveform
 * @param string   $status_rows  More Summary rows (pg_calendar_event_location_form_status_row()), HTML
 * @return string
 */
function pg_calendar_event_location_form_cards($liveform, $status_rows = '')
{
    return '
                        <div class="col-12 lg:col-8 2xl:col-9 order-1">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Options') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="row">
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Event Location'))) . '</label>
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
                                    <h2 class="card-header bg-reset border-0 pg-title-section">
                                        ' . lang('Summary') . '
                                    </h2>
                                    <div class="card-body pt-0">
                                        <dl class="mb-0">' . pg_calendar_event_location_form_status_row(lang('Name'), '<span id="calendar_event_location_summary_name">' . h((string) $liveform->get_field_value('name')) . '</span>', $status_rows === '') . $status_rows . '
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <script>
                            // The Summary follows the name as it is typed.
                            document.getElementById("name").addEventListener("input", function () {
                                document.getElementById("calendar_event_location_summary_name").textContent = this.value;
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
function pg_calendar_event_location_form_status_row($label, $value, $last = true)
{
    return '
                                            <div class="d-flex justify-content-between gap-3 py-2' . ($last ? '' : ' border-bottom') . '">
                                                <dt class="fw-normal">' . h($label) . '</dt>
                                                <dd class="mb-0 fw-bold text-end text-break">' . $value . '</dd>
                                            </div>';
}

/**
 * The screen row: the columns (pg_calendar_event_location_form_cards()) and
 * the sticky save bar with the given buttons.
 *
 * @param string $cards    pg_calendar_event_location_form_cards()
 * @param string $buttons  The buttons inside the bar's group
 * @return string
 */
function pg_calendar_event_location_form_layout($cards, $buttons)
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
 * Whether another calendar event location already uses a name.
 *
 * @param string $name
 * @param string|null $id  the location being edited, null on create
 * @return bool
 */
function pg_calendar_event_location_form_name_in_use($name, $id)
{
    $taken = pg_calendar_location_name_taken($name, ($id === null) ? 0 : (int) $id);

    if ($taken === null) {
        output_error('Query failed.');
    }

    return $taken;
}
