<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The arrival date form, shared by the create and the edit screens
 * (add_arrival_date.php, edit_arrival_date.php): the preview,
 * availability and options cards with the shipping cut-off table, the
 * column values a posted arrival date is saved with and the shipping
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
 * The preview, availability and options cards.
 *
 * @param array $values  The stored row (raw), array() on create
 * @param string $cutoffs_js  Edit: the lines that fill the shipping_cutoffs
 *                            array, drawn as rows once the page has loaded;
 *                            null on create
 * @return string
 */
function pg_arrival_date_form_cards($values, $cutoffs_js = null)
{
    $v = array_merge(array(
        'name' => '', 'description' => '', 'code' => '', 'sort_order' => '', 'status' => 'enabled',
        'default_selected' => '0', 'custom' => '0'), $values);

    // The stored dates in the site's date order; a new arrival date has none,
    // and an unset latest allowed date shows empty.
    $date = function ($column) use ($values) {
        $value = isset($values[$column]) ? prepare_form_data_for_output($values[$column], 'date') : '';

        return (($column === 'custom_maximum_arrival_date') && ($value == '0/0/0000')) ? '' : $value;
    };

    $status_enabled_checked = ($v['status'] == 'enabled') ? ' checked="checked"' : '';
    $default_selected_checked = ($v['default_selected'] == 1) ? ' checked="checked"' : '';

    // A custom arrival date is entered by the customer and has no shipping
    // cut-offs; otherwise there is no latest allowed date to enter.
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

    return '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Preview Options') . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 sm:col-6 lg:col-4 my-3">
                                            <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Arrival Date'))) . '</label>
                                            <input value="' . h($v['name']) . '" type="text" name="name"  id="name" maxlength="50" class="form-control" />
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        <div class="col-12 sm:col-6 lg:col-4 my-3">
                                            <label for="code" class="form-label">' . lang('Arrival Date Code') . '</label>
                                            <input value="' . h($v['code']) . '" type="text" name="code"  id="code" maxlength="50" class="form-control" />
                                            <div class="form-text text-end">' . lang('Arrival Date Code for Order Reporting') . '</div>
                                        </div>
                                        <div class="col-12 sm:col-4  my-3">
                                            <label for="sort_order" class="form-label">' . lang('Sorting') . '</label>
                                            <div class="input-group number-controls">
                                                <button class="btn material-icons minus border border-end-0" type="button" aria-label="' . h(lang('Decrease')) . '">remove</button>
                                                <input value="' . h($v['sort_order']) . '" class="form-control text-center border-start-0 border-end-0" type="text" name="sort_order" id="sort_order" maxlength="3" inputmode="numeric" data-inputmask-alias="decimal"  data-inputmask-placeholder="0"/>
                                                <button class="btn material-icons plus border border-start-0" type="button" aria-label="' . h(lang('Increase')) . '">add</button>
                                            </div>
                                        </div>
                                        <div class="col-12  sm:col-8 lg:col-12 my-3">
                                            <label for="description" class="form-label">' . lang(array('string'=>'{var:1} Description','vars'=>lang('Arrival Date'))) . '</label>
                                            <input value="' . h($v['description']) . '" type="text" name="description" id="description" maxlength="255" class="form-control" />
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Arrival Date Availability') . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-3">
                                            <div class="form-field">
                                                <div class="switch"><input class="" type="checkbox" id="status" name="status" value="1"' . $status_enabled_checked . ' /></div>
                                                <label class="" for="status">' . lang('Enable this Arrival Date') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-3">
                                            <div class="form-field">
                                                <div class="switch"><input class="" type="checkbox" id="default_selected" name="default_selected" value="1"' . $default_selected_checked . ' /></div>
                                                <label class="" for="default_selected">' . lang('Selected by Default') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 lg:col-8 my-5">
                                            <div class="row">
                                                <div class="col-12 md:col">
                                                    <label for="start_date" class="form-label">' . lang('Start Date') . '</label>
                                                    <input value="' . $date('start_date') . '" placeholder="' . date(get_date_format_code() . '/Y') . '" type="text" name="start_date" id="start_date" class="form-control" maxlength="10" autocomplete="off" data-pg-date/>
                                                </div>
                                                <div class="col-12 md:col">
                                                    <label for="end_date" class="form-label">' . lang('End Date') . '</label>
                                                    <input value="' . $date('end_date') . '" type="text" name="end_date" id="end_date" class="form-control " maxlength="10" autocomplete="off" data-pg-date/>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Arrival Date Options') . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 md:col-4">
                                            <label for="arrival_date" class="form-label">' . lang('Arrival Date') . '</label>
                                            <input value="' . $date('arrival_date') . '" placeholder="' . date(get_date_format_code() . '/Y') . '" type="text" name="arrival_date" id="arrival_date" class="form-control" maxlength="10" autocomplete="off" data-pg-date/>
                                        </div>
                                        <div class="col-12 mt-7">
                                            <h3 class="h5">' . lang('Or allow Customer to enter this Arrival Date during checkout') . '</h3>
                                        </div>
                                        <div class="col-12 my-3">
                                            <div class="form-field">
                                                <div class="switch"><input value="1"' . $custom_checked . ' id="custom" name="custom" class="collapse-switcher" type="checkbox" role="switch" data-bs-target="#custom_row" onclick="show_or_hide_custom()"/></div>
                                                <label class="" for="custom">' . lang('Display Custom Field') . '</label>
                                            </div>
                                            <div class="collapse popover  fade bs-popover-bottom p-0 mb-3" id="custom_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 my-1" id="custom_maximum_arrival_date_row"' . $custom_maximum_arrival_date_row_style . '>
                                                            <label for="custom_maximum_arrival_date" class="form-label">' . lang('Latest Allowed Arrival Date') . '</label>
                                                            <input value="' . $date('custom_maximum_arrival_date') . '" placeholder="' . date(get_date_format_code() . '/Y') . '" type="text" name="custom_maximum_arrival_date" id="custom_maximum_arrival_date" class="form-control" maxlength="10" autocomplete="off" data-pg-date/>
                                                            <div class="form-text text-end">' . lang('leave blank for no restriction') . '</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 my-3" id="shipping_cutoff_heading_row"' . $shipping_cutoff_row_style . '>
                                            <h3 class="h4 fw-bold fg-secondary">' . lang('Shipping Cut-offs') . '</h3>
                                        </div>
                                        <div id="shipping_cutoff_row"' . $shipping_cutoff_row_style . '>
                                            <div>
                                                <a href="javascript:void(0)" onclick="create_shipping_cutoff()" class="button btn btn-solid theme-primary">' . lang('Add Shipping Cut-off') . '</a>
                                            </div>
                                            <div class="table-responsive" >
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
                                                <script type="text/javascript">
                                                    var last_shipping_cutoff_number = 0;
                                                    var shipping_cutoffs = new Array();
                                                    ' . $output_cutoffs_js . '
                                                </script>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
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
    $date = function ($field) use ($text) {
        return (string) prepare_form_data_for_input($text($field), 'date');
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
 * Saves the posted shipping cut-offs of an arrival date. A cut-off needs a
 * shipping method not used by an earlier row and a valid date and time
 * between the arrival date's start and end dates; any other row is left
 * out.
 *
 * @param mixed $arrival_date_id
 * @param array $post
 * @return bool  Whether a row was left out
 */
function pg_arrival_date_form_save_cutoffs($arrival_date_id, $post)
{
    $left_out = false;

    // One cut-off per shipping method.
    $shipping_cutoff_shipping_methods = array();
    $start = prepare_form_data_for_input($post['start_date'] ?? '', 'date') . ' 00:00:00';
    $end = prepare_form_data_for_input($post['end_date'] ?? '', 'date') . ' 23:59:59';

    for ($i = 1; $i <= ($post['last_shipping_cutoff_number'] ?? 0); $i++) {
        $shipping_method_id = $post['shipping_cutoff_' . $i . '_shipping_method_id'] ?? '';
        $date_and_time = $post['shipping_cutoff_' . $i . '_date_and_time'] ?? '';

        if (
            ($shipping_method_id != '')
            && (in_array($shipping_method_id, $shipping_cutoff_shipping_methods) == FALSE)
            && ($date_and_time != '')
            && (validate_date_and_time($date_and_time) == TRUE)
            && (prepare_form_data_for_input($date_and_time, 'date and time') >= $start)
            && (prepare_form_data_for_input($date_and_time, 'date and time') <= $end)
        ) {
            $query =
                "INSERT INTO shipping_cutoffs (
                    arrival_date_id,
                    shipping_method_id,
                    date_and_time)
                VALUES (
                    ?,
                    ?,
                    ?)";
            db_q($query, array(
                (string) $arrival_date_id,
                (string) $shipping_method_id,
                (string) prepare_form_data_for_input($date_and_time, 'date and time'))) or output_error('Query failed.');

            $shipping_cutoff_shipping_methods[] = $shipping_method_id;

        } else {
            $left_out = true;
        }
    }

    return $left_out;
}
