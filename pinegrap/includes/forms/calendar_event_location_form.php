<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The calendar event location form, shared by the create and the edit
 * screens (add_calendar_event_location.php,
 * edit_calendar_event_location.php): the options card and the check of a
 * name another location uses. The screens keep what differs: the edit
 * screen loads the record, offers delete and updates the row; the create
 * screen inserts it.
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
 * The Options card with the location name.
 *
 * @param liveform $liveform
 * @return string
 */
function pg_calendar_event_location_form_card($liveform)
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
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Event Location'))) . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'class'=>'form-control', 'maxlength'=>'100', 'required'=>'required')) . '
                                        </div>
                                    </div>
                                </div>
                            </div>
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
    $query =
        "SELECT id
        FROM calendar_event_locations
        WHERE (name = ?)";
    $values = array((string) $name);

    if ($id !== null) {
        $query .= " AND (id != ?)";
        $values[] = (string) $id;
    }

    $result = db_q($query, $values) or output_error('Query failed.');

    return mysqli_num_rows($result) > 0;
}
