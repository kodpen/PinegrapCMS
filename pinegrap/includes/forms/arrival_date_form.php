<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The arrival date form, shared by the create and the edit screens
 * (add_arrival_date.php, edit_arrival_date.php): the wide layout (a form
 * column with the options, dates and shipping cut-off cards, a sticky
 * status column with the availability switches and a Summary, a sticky
 * save bar), the column values a posted arrival date is saved with and the shipping
 * cut-offs it keeps. The screens keep what differs: the edit screen loads
 * the record and its cut-offs, offers delete and updates the row; the
 * create screen inserts it.
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
 * The shipping methods a cut-off can be set for, as the lines that fill
 * the shipping_method_id_options array of the cut-off table script.
 *
 * @return string
 */
function pg_arrival_date_form_shipping_method_options_js()
{
    $query =
        "SELECT
            id,
            name,
            code
        FROM shipping_methods
        ORDER BY name ASC, code ASC";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    $output = '';
    $count = 0;

    while ($shipping_method = mysqli_fetch_assoc($result)) {
        // The code, when there is one, follows the name.
        $shipping_method_name = $shipping_method['name'];

        if ($shipping_method['code'] != '') {
            $shipping_method_name .= ' (' . $shipping_method['code'] . ')';
        }

        $output .=
            'shipping_method_id_options[' . $count . '] = new Array();
            shipping_method_id_options[' . $count . ']["name"] = "' . escape_javascript($shipping_method_name) . '";
            shipping_method_id_options[' . $count . ']["value"] = "' . $shipping_method['id'] . '";' . "\n";

        $count++;
    }

    return $output;
}

/**
 * A stored date in the site's date order, '' for an unset one: an AT-ONCE
 * or customer-entered arrival date has 0000-00-00, which
 * prepare_form_data_for_output() writes as 0/0/0000.
 *
 * @param mixed $stored  'Y-m-d'
 * @return string
 */
function pg_arrival_date_form_date_text($stored)
{
    if (!is_scalar($stored) || (pg_date_input_iso((string) $stored) === '')) {
        return '';
    }

    return (string) prepare_form_data_for_output((string) $stored, 'date');
}

/**
 * The form column (the options, dates and shipping cut-off cards) and the
 * sticky status column (the availability switches and a Summary that
 * follows the fields).
 *
 * @param array $values  The stored row (raw), array() on create
 * @param string $cutoffs_js  Edit: the lines that fill the shipping_cutoffs
 *                            array, drawn as rows once the page has loaded;
 *                            null on create
 * @param string $status_rows  More Summary rows (pg_arrival_date_form_status_row()),
 *                             e.g. the last change on edit
 * @return string
 */
function pg_arrival_date_form_cards($values, $cutoffs_js = null, $status_rows = '')
{
    $v = array_merge(array(
        'name' => '', 'description' => '', 'code' => '', 'sort_order' => '', 'status' => 'enabled',
        'default_selected' => '0', 'custom' => '0'), $values);

    // The stored dates in the site's date order; a new arrival date has none,
    // and an unset date shows empty.
    $date = function ($column) use ($values) {
        return pg_arrival_date_form_date_text($values[$column] ?? '');
    };

    $status_enabled_checked = ($v['status'] == 'enabled') ? ' checked="checked"' : '';
    $default_selected_checked = ($v['default_selected'] == 1) ? ' checked="checked"' : '';
    $date_placeholder = date(get_date_format_code() . '/Y');

    // A custom arrival date is entered by the customer and has no shipping
    // cut-offs; otherwise there is no latest allowed date to enter. The rows
    // are shown and hidden by show_or_hide_custom() through style.display,
    // so they carry an inline display, not d-none.
    if ($v['custom'] == 1) {
        $custom_checked = ' checked="checked"';
        $custom_maximum_arrival_date_row_style = '';
        $shipping_cutoff_row_style = ' style="display: none"';
    } else {
        $custom_checked = '';
        $custom_maximum_arrival_date_row_style = ' style="display: none"';
        $shipping_cutoff_row_style = '';
    }

    $output_cutoffs_js = (string) $cutoffs_js . '
                                        var shipping_method_id_options = new Array();
                                        ' . pg_arrival_date_form_shipping_method_options_js();

    // The stored cut-offs become rows once the page has loaded.
    if ($cutoffs_js !== null) {
        $output_cutoffs_js .= '
                                        window.onload = function() {
                                            for (var i = 0; i < shipping_cutoffs.length; i++) {
                                                create_shipping_cutoff(shipping_cutoffs[i]);
                                            }
                                        };';
    }

    $customer_text = lang('Chosen by the customer');
    $summary_arrival_date = ($v['custom'] == 1) ? $customer_text : $date('arrival_date');

    $summary = pg_arrival_date_form_status_row(lang('Name'), '<span id="arrival_date_summary_name">' . h($v['name']) . '</span>', false)
        . pg_arrival_date_form_status_row(lang('Arrival Date'), '<span id="arrival_date_summary_arrival_date">' . h($summary_arrival_date) . '</span>', false)
        . pg_arrival_date_form_status_row(lang('Start Date'), '<span id="arrival_date_summary_start_date">' . h($date('start_date')) . '</span>', false)
        . pg_arrival_date_form_status_row(lang('End Date'), '<span id="arrival_date_summary_end_date">' . h($date('end_date')) . '</span>', false)
        . pg_arrival_date_form_status_row(lang('Shipping Cut-offs'), '<span id="arrival_date_summary_cutoffs">' . substr_count((string) $cutoffs_js, '["shipping_method_id"]') . '</span>', $status_rows === '')
        . $status_rows;

    return '
                        <div class="col-12 lg:col-8 2xl:col-9 order-1">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Preview Options') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="row">
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Arrival Date'))) . '</label>
                                            <input value="' . h($v['name']) . '" type="text" name="name" id="name" maxlength="50" class="form-control" required />
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="code" class="form-label">' . lang('Arrival Date Code') . '</label>
                                            <input value="' . h($v['code']) . '" type="text" name="code" id="code" maxlength="50" class="form-control" aria-describedby="code_help" />
                                            <div id="code_help" class="form-text">' . lang('Arrival Date Code for Order Reporting') . '</div>
                                        </div>
                                        <div class="col-12 md:col-8 my-3">
                                            <label for="description" class="form-label">' . lang(array('string'=>'{var:1} Description','vars'=>lang('Arrival Date'))) . '</label>
                                            <input value="' . h($v['description']) . '" type="text" name="description" id="description" maxlength="255" class="form-control" />
                                        </div>
                                        <div class="col-12 md:col-4 my-3">
                                            <label for="sort_order" class="form-label">' . lang('Sorting') . '</label>
                                            <div class="input-group number-controls">
                                                <button class="btn material-icons minus border border-end-0" type="button" aria-label="' . h(lang('Decrease')) . '">remove</button>
                                                <input value="' . h($v['sort_order']) . '" class="form-control text-center border-start-0 border-end-0" type="text" name="sort_order" id="sort_order" maxlength="3" inputmode="numeric" data-inputmask-alias="integer" data-inputmask-placeholder="0"/>
                                                <button class="btn material-icons plus border border-start-0" type="button" aria-label="' . h(lang('Increase')) . '">add</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Dates') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="row">
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="start_date" class="form-label">' . lang('Start Date') . '</label>
                                            <input value="' . $date('start_date') . '" placeholder="' . $date_placeholder . '" type="text" name="start_date" id="start_date" class="form-control" maxlength="10" autocomplete="off" data-pg-date/>
                                        </div>
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="end_date" class="form-label">' . lang('End Date') . '</label>
                                            <input value="' . $date('end_date') . '" placeholder="' . $date_placeholder . '" type="text" name="end_date" id="end_date" class="form-control" maxlength="10" autocomplete="off" data-pg-date/>
                                        </div>
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="arrival_date" class="form-label">' . lang('Arrival Date') . '</label>
                                            <input value="' . $date('arrival_date') . '" placeholder="' . $date_placeholder . '" type="text" name="arrival_date" id="arrival_date" class="form-control" maxlength="10" autocomplete="off" data-pg-date/>
                                        </div>
                                        <div class="col-12 my-3">
                                            <div class="form-field">
                                                <div class="switch"><input value="1"' . $custom_checked . ' id="custom" name="custom" type="checkbox" role="switch" aria-describedby="custom_help" onclick="show_or_hide_custom()"/></div>
                                                <label for="custom">' . lang('Display Custom Field') . '</label>
                                            </div>
                                            <div id="custom_help" class="form-text">' . lang('Or allow Customer to enter this Arrival Date during checkout') . '</div>
                                        </div>
                                        <div class="col-12 md:col-6 my-3" id="custom_maximum_arrival_date_row"' . $custom_maximum_arrival_date_row_style . '>
                                            <label for="custom_maximum_arrival_date" class="form-label">' . lang('Latest Allowed Arrival Date') . '</label>
                                            <input value="' . $date('custom_maximum_arrival_date') . '" placeholder="' . $date_placeholder . '" type="text" name="custom_maximum_arrival_date" id="custom_maximum_arrival_date" class="form-control" maxlength="10" autocomplete="off" aria-describedby="custom_maximum_arrival_date_help" data-pg-date/>
                                            <div id="custom_maximum_arrival_date_help" class="form-text">' . lang('leave blank for no restriction') . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card mb-7" id="shipping_cutoff_heading_row"' . $shipping_cutoff_row_style . '>
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Shipping Cut-offs') . '
                                </h2>
                                <div class="card-body pt-0" id="shipping_cutoff_row"' . $shipping_cutoff_row_style . '>
                                    <div class="table-responsive">
                                        <table id="shipping_cutoff_table" style="display: none;" class="chart_no_hover table">
                                            <thead>
                                                <tr>
                                                    <th class="text-nowrap" style="min-width:200px">' . lang('Shipping Method') . '</th>
                                                    <th class="text-nowrap" style="min-width:200px">' . lang('Cut-off Date & Time') . '</th>
                                                    <th class="text-nowrap">&nbsp;</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                    <button type="button" onclick="create_shipping_cutoff()" class="btn btn-solid theme-primary"><span class="material-icons me-3" aria-hidden="true">add</span><span class="pg-btn-label">' . lang('Add Shipping Cut-off') . '</span></button>
                                    <script type="text/javascript">
                                        var last_shipping_cutoff_number = 0;
                                        var shipping_cutoffs = new Array();
                                        ' . $output_cutoffs_js . '
                                    </script>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 lg:col-4 2xl:col-3 order-2">
                            <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                            <div class="position-sticky" style="top:3.3rem;">
                                <div class="card mb-7">
                                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                        ' . lang('Availability') . '
                                    </h2>
                                    <div class="card-body pt-0">
                                        <div class="form-field my-3">
                                            <div class="switch"><input type="checkbox" role="switch" id="status" name="status" value="1"' . $status_enabled_checked . ' /></div>
                                            <label for="status">' . lang('Enable this Arrival Date') . '</label>
                                        </div>
                                        <div class="form-field my-3">
                                            <div class="switch"><input type="checkbox" role="switch" id="default_selected" name="default_selected" value="1"' . $default_selected_checked . ' /></div>
                                            <label for="default_selected">' . lang('Selected by Default') . '</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="card mb-7">
                                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                        ' . lang('Summary') . '
                                    </h2>
                                    <div class="card-body pt-0">
                                        <dl class="mb-0">' . $summary . '
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <script>
                            // The Summary follows the fields as they change; jQuery
                            // hears a pick of the date picker, the cut-off count
                            // follows the rows of the table.
                            (function () {
                                var customer_text = ' . json_encode($customer_text) . ';
                                var custom = document.getElementById("custom");
                                function follow(id, summary_id, read) {
                                    var field = document.getElementById(id);
                                    var summary = document.getElementById(summary_id);
                                    var update = function () { summary.textContent = read(field); };
                                    field.addEventListener("input", update);
                                    jQuery(field).on("change", update);
                                    return update;
                                }
                                var value = function (field) { return field.value; };
                                follow("name", "arrival_date_summary_name", value);
                                follow("start_date", "arrival_date_summary_start_date", value);
                                follow("end_date", "arrival_date_summary_end_date", value);
                                var arrival_date = follow("arrival_date", "arrival_date_summary_arrival_date", function (field) {
                                    return custom.checked ? customer_text : field.value;
                                });
                                custom.addEventListener("change", arrival_date);
                                var rows = document.getElementById("shipping_cutoff_table").tBodies[0];
                                new MutationObserver(function () {
                                    document.getElementById("arrival_date_summary_cutoffs").textContent = String(rows.rows.length);
                                }).observe(rows, {childList: true});
                            })();
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
function pg_arrival_date_form_status_row($label, $value, $last = true)
{
    return '
                                            <div class="d-flex justify-content-between gap-3 py-2' . ($last ? '' : ' border-bottom') . '">
                                                <dt class="fw-normal">' . h($label) . '</dt>
                                                <dd class="mb-0 fw-bold text-end text-break">' . $value . '</dd>
                                            </div>';
}

/**
 * The screen row: the columns (pg_arrival_date_form_cards()) and the
 * sticky save bar with the given buttons.
 *
 * @param string $cards    pg_arrival_date_form_cards()
 * @param string $buttons  The buttons inside the bar's group
 * @return string
 */
function pg_arrival_date_form_layout($cards, $buttons)
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
 * The status column of a posted arrival date.
 *
 * @param array $post
 * @return string  'enabled' or 'disabled'
 */
function pg_arrival_date_form_status($post)
{
    return ((isset($post['status']) && is_scalar($post['status']) && ($post['status'] == 1))) ? 'enabled' : 'disabled';
}

/**
 * What keeps a posted arrival date from being saved: field => message for
 * the error screen. The name and the start and end dates are required and
 * real dates, the end not before the start; the arrival date is a real
 * date unless the customer enters it (custom), and then the latest allowed
 * date is blank or a real date; the sort order is a whole number. A date
 * the screens could not read was stored as 0000-00-00 without a word, and
 * a zero start date kept the arrival date off the checkout for good.
 *
 * @param array $post
 * @return array
 */
function pg_arrival_date_form_errors($post)
{
    $text = function ($field) use ($post) {
        return (isset($post[$field]) && is_scalar($post[$field])) ? trim((string) $post[$field]) : '';
    };

    $errors = array();

    if ($text('name') === '') {
        $errors['name'] = lang(array('string' => 'Please enter a valid {var:1}.', 'vars' => lang(array('string' => '{var:1} Name', 'vars' => lang('Arrival Date')))));
    }

    $custom = ($text('custom') == '1');

    $dates = array('start_date' => array(lang('Start Date'), true), 'end_date' => array(lang('End Date'), true), 'arrival_date' => array(lang('Arrival Date'), !$custom));

    if ($custom) {
        $dates['custom_maximum_arrival_date'] = array(lang('Latest Allowed Arrival Date'), false);
    }

    foreach ($dates as $field => $rule) {
        $value = $text($field);

        if ((($value !== '') || $rule[1]) && (pg_date_input_iso($value) === '')) {
            $errors[$field] = lang(array('string' => 'Please enter a valid date for {var:1}', 'vars' => $rule[0]));
        }
    }

    if (!isset($errors['start_date']) && !isset($errors['end_date']) && (pg_date_input_iso($text('end_date')) < pg_date_input_iso($text('start_date')))) {
        $errors['end_date'] = lang('Please enter a valid end date.');
    }

    if (($text('sort_order') !== '') && (preg_match('/^-?[0-9]{1,5}$/', $text('sort_order')) !== 1 || abs((int) $text('sort_order')) > 32767)) {
        $errors['sort_order'] = lang(array('string' => '{var:1} takes a whole number from {var:2} to {var:3}.', 'vars' => array(lang('Sorting'), pg_format_number(-32768, 0), pg_format_number(32767, 0))));
    }

    return $errors;
}

/**
 * The values of a posted arrival date, in the order of the columns name,
 * description, code, arrival_date, status, start_date, end_date,
 * default_selected, sort_order, custom, custom_maximum_arrival_date, user.
 *
 * @param array $post
 * @param mixed $user_id
 * @return array  Strings for db_q()
 */
function pg_arrival_date_form_values($post, $user_id)
{
    $text = function ($field) use ($post) {
        return (isset($post[$field]) && is_scalar($post[$field])) ? (string) $post[$field] : '';
    };
    // pg_arrival_date_form_errors() has checked the dates; a blank one
    // (a custom arrival date) is stored as before.
    $date = function ($field) use ($text) {
        $iso = pg_date_input_iso($text($field));

        return ($iso !== '') ? $iso : (string) prepare_form_data_for_input($text($field), 'date');
    };

    return array(
        $text('name'),
        $text('description'),
        $text('code'),
        $date('arrival_date'),
        pg_arrival_date_form_status($post),
        $date('start_date'),
        $date('end_date'),
        $text('default_selected'),
        $text('sort_order'),
        $text('custom'),
        $date('custom_maximum_arrival_date'),
        (string) $user_id,
    );
}

/**
 * One shipping cut-off as it is stored: array(shipping method id,
 * 'Y-m-d H:i:s'), or null when it cannot be kept. A cut-off needs a
 * shipping method not in $taken and a valid date and time between the
 * arrival date's start (00:00:00) and end (23:59:59).
 *
 * @param mixed  $shipping_method_id
 * @param mixed  $date_and_time  as the cut-off table or the API sends it
 * @param string $start  'Y-m-d H:i:s'
 * @param string $end    'Y-m-d H:i:s'
 * @param array  $taken  the shipping method ids of the earlier cut-offs
 * @return array|null
 */
function pg_arrival_date_form_cutoff($shipping_method_id, $date_and_time, $start, $end, $taken)
{
    if (!is_scalar($shipping_method_id) || !is_scalar($date_and_time)) {
        return null;
    }

    $shipping_method_id = trim((string) $shipping_method_id);
    $date_and_time = trim((string) $date_and_time);

    if (
        ($shipping_method_id === '')
        || in_array($shipping_method_id, array_map('strval', $taken), true)
        || ($date_and_time === '')
        || (validate_date_and_time($date_and_time) == FALSE)
    ) {
        return null;
    }

    $stored = (string) prepare_form_data_for_input($date_and_time, 'date and time');

    if (($stored < $start) || ($stored > $end)) {
        return null;
    }

    return array((int) $shipping_method_id, $stored);
}

/**
 * Saves the posted shipping cut-offs of an arrival date in place of the
 * stored ones (pg_store_arrival_date_cutoffs_save()). A row
 * pg_arrival_date_form_cutoff() cannot keep is left out.
 *
 * @param mixed $arrival_date_id
 * @param array $post
 * @return bool  Whether a row was left out
 */
function pg_arrival_date_form_save_cutoffs($arrival_date_id, $post)
{
    $left_out = false;
    $cutoffs = array();
    $taken = array();
    $start = prepare_form_data_for_input($post['start_date'] ?? '', 'date') . ' 00:00:00';
    $end = prepare_form_data_for_input($post['end_date'] ?? '', 'date') . ' 23:59:59';
    for ($i = 1; $i <= ($post['last_shipping_cutoff_number'] ?? 0); $i++) {
        $cutoff = pg_arrival_date_form_cutoff($post['shipping_cutoff_' . $i . '_shipping_method_id'] ?? '', $post['shipping_cutoff_' . $i . '_date_and_time'] ?? '', $start, $end, $taken);
        if ($cutoff === null) {
            $left_out = true;
            continue;
        }
        $cutoffs[] = $cutoff;
        $taken[] = $cutoff[0];
    }
    pg_store_arrival_date_cutoffs_save($arrival_date_id, $cutoffs) or output_error('Query failed.');
    return $left_out;
}
