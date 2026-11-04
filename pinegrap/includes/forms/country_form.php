<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The country form, shared by the create and the edit screens
 * (add_country.php, edit_country.php): the form and status columns (name,
 * code, the zip code and default switches, extra delivery days, a Summary),
 * the sticky save bar and the posted values both screens write. The screens keep what differs: the edit screen
 * loads the country, posts its id, offers delete and clears the states, zone
 * links and addresses of a deleted country; each writes its own row.
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
 * The posted fields as text, in INSERT/UPDATE order. A missing or
 * non-scalar value is empty. The name and the code are trimmed: checkout
 * matches the code against the trimmed visitor post, and the PAD SPACE
 * collation ignores trailing but not leading spaces, so ' US' would pass the
 * taken check beside 'US' and never be found.
 *
 * @param array $post The posted form ($_POST)
 * @return array  'name', 'code', 'zip_code_required', 'transit_adjustment_days', 'default_selected'
 */
function pg_country_form_values($post)
{
    $values = array();

    foreach (array('name', 'code', 'zip_code_required', 'transit_adjustment_days', 'default_selected') as $key) {
        $values[$key] = (isset($post[$key]) && is_scalar($post[$key])) ? (string) $post[$key] : '';
    }

    $values['name'] = trim($values['name']);
    $values['code'] = trim($values['code']);
    $values['transit_adjustment_days'] = trim($values['transit_adjustment_days']);

    return $values;
}

/**
 * The message for transit adjustment days that are not a whole number of
 * zero or more, or ''. The column is INT UNSIGNED and the session runs
 * without strict mode, so MySQL would store '-5' as 0 and '12,50' as 12
 * without a word; an empty box means 0 (pg_country_form_transit_days()).
 *
 * @param string $value The posted value (pg_country_form_values())
 * @return string
 */
function pg_country_form_transit_days_error($value)
{
    $value = trim((string) $value);

    return (($value === '') || preg_match('/^[0-9]{1,9}$/', $value))
        ? ''
        : lang('Enter a whole number of days, 0 or more.');
}

/**
 * The transit adjustment days to store: a value that passed
 * pg_country_form_transit_days_error() as an integer, an empty box as 0.
 *
 * @param string $value
 * @return int
 */
function pg_country_form_transit_days($value)
{
    $value = trim((string) $value);

    return preg_match('/^[0-9]{1,9}$/', $value) ? (int) $value : 0;
}

/**
 * The required fields left blank, as field => message. A country with no
 * name prints an empty row in every list and picker, and one with no code
 * cannot be matched by checkout or written to an order. Transit adjustment
 * days that are not a whole number of zero or more are refused too.
 *
 * @param array $values pg_country_form_values()
 * @return array  'name', 'code' and/or 'transit_adjustment_days' => message
 */
function pg_country_form_required_errors($values)
{
    $errors = array();

    if (trim((string) ($values['name'] ?? '')) === '') {
        $errors['name'] = lang('Enter a name.');
    }

    if (trim((string) ($values['code'] ?? '')) === '') {
        $errors['code'] = lang('Enter a code.');
    }

    $transit_error = pg_country_form_transit_days_error($values['transit_adjustment_days'] ?? '');

    if ($transit_error !== '') {
        $errors['transit_adjustment_days'] = $transit_error;
    }

    return $errors;
}

/**
 * Whether another country already has the code. Checkout's zone lookup and
 * the product destination check take the country id from the first row with
 * the code, so a second country with it would be read as the first.
 *
 * @param string   $code      The code
 * @param int|null $except_id The edited country (left out); null on create
 * @return bool
 */
function pg_country_form_code_taken($code, $except_id = null)
{
    $query = "SELECT id FROM countries WHERE code = ?";
    $values = array((string) $code);

    if ($except_id !== null) {
        $query .= " AND id != ?";
        $values[] = (int) $except_id;
    }

    $result = db_q($query . " LIMIT 1", $values) or output_error('Query failed.');

    return mysqli_num_rows($result) > 0;
}

/**
 * The message for a code another country has, or ''. Only a non-empty code
 * that is new or changed is looked up, so an edit that keeps an old clash
 * still saves.
 *
 * @param string      $code        The posted code
 * @param string|null $stored_code The edited country's stored code; null on create
 * @param int|null    $except_id   The edited country's id; null on create
 * @param callable    $taken       Lookup as pg_country_form_code_taken() (tests stub it)
 * @return string
 */
function pg_country_form_code_error($code, $stored_code, $except_id, $taken = 'pg_country_form_code_taken')
{
    $code = trim((string) $code);

    // A legacy padded stored code posted back unchanged arrives trimmed.
    if (($code === '') || (($stored_code !== null) && ($code === trim((string) $stored_code)))) {
        return '';
    }

    return $taken($code, $except_id)
        ? lang('The code that you entered is already in use by another country, please enter a different code.')
        : '';
}

/**
 * The form column (Options card: name and code; Delivery card: the zip code
 * switch and the extra delivery days) and the sticky status column (the
 * Selected by Default switch and a Summary of the code, the zip code rule
 * and the extra days, following the fields as they change), in the edit
 * screen layout of product_builder.php. Below lg the status column follows
 * the form in source order. The screens print the form tag, the buttons
 * (pg_country_form_layout()) and the edit screen's id around it.
 *
 * @param array|null $values      The fields of pg_country_form_values() from a
 *                                stored country or a refused post; null on
 *                                create, which prints no values and no switch on
 * @param array      $errors      Field => message; 'name', 'code' and
 *                                'transit_adjustment_days' are printed under
 *                                their fields
 * @param string     $status_rows Extra Summary rows (pg_country_form_status_row()),
 *                                the edit screen's counts and last change
 * @return string  HTML
 */
function pg_country_form_card($values, $errors = array(), $status_rows = '')
{
    if ($values === null) {
        $values = pg_country_form_values(array());
    }

    $name_value = ($values['name'] !== '') ? ' value="' . h($values['name']) . '"' : '';
    $code_value = ($values['code'] !== '') ? ' value="' . h($values['code']) . '"' : '';
    $code_error = isset($errors['code']) ? (string) $errors['code'] : '';
    $code_class = ($code_error !== '') ? ' is-invalid' : '';
    $code_aria = ($code_error !== '') ? ' aria-invalid="true" aria-describedby="code-error"' : '';
    $code_feedback = ($code_error !== '') ? '
                                            <div id="code-error" class="invalid-feedback d-block">' . h($code_error) . '</div>' : '';
    $name_error = isset($errors['name']) ? (string) $errors['name'] : '';
    $name_class = ($name_error !== '') ? ' is-invalid' : '';
    $name_aria = ($name_error !== '') ? ' aria-invalid="true" aria-describedby="name-error"' : '';
    $name_feedback = ($name_error !== '') ? '
                                            <div id="name-error" class="invalid-feedback d-block">' . h($name_error) . '</div>' : '';
    $transit_error = isset($errors['transit_adjustment_days']) ? (string) $errors['transit_adjustment_days'] : '';
    $transit_class = ($transit_error !== '') ? ' is-invalid' : '';
    $transit_aria = ($transit_error !== '') ? ' aria-invalid="true" aria-describedby="transit_adjustment_days-error"' : '';
    $transit_feedback = ($transit_error !== '') ? '
                                            <div id="transit_adjustment_days-error" class="invalid-feedback d-block">' . h($transit_error) . '</div>' : '';
    $zip_checked = $values['zip_code_required'] ? ' checked' : '';
    $default_checked = ($values['default_selected'] == 1) ? ' checked' : '';
    $days = pg_country_form_transit_days($values['transit_adjustment_days']);

    return '
                        <div class="col-12 lg:col-8 2xl:col-9 order-1">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Options') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="row">
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Country'))) . '</label>
                                            <input type="text" name="name" id="name" class="form-control' . $name_class . '" maxlength="50" required' . $name_value . $name_aria . ' />' . $name_feedback . '
                                            <div class="form-text">' . lang('Display on Commerce Pages') . '</div>
                                        </div>
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="code" class="form-label">' . lang('Code') . '</label>
                                            <input type="text" name="code" id="code" class="form-control' . $code_class . '" maxlength="50" required' . $code_value . $code_aria . ' />' . $code_feedback . '
                                            <div class="form-text">' . lang('Country Code for Order Reporting') . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Delivery') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="row">
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="transit_adjustment_days" class="form-label">' . lang('Extra Delivery Days') . '</label>
                                            <div class="input-group number-controls">
                                                <button class="btn material-icons minus border border-end-0" type="button" aria-label="' . h(lang('Decrease')) . '">remove</button>
                                                <input class="form-control text-center border-start-0 border-end-0' . $transit_class . '" value="' . h($values['transit_adjustment_days']) . '" type="text" name="transit_adjustment_days" id="transit_adjustment_days" maxlength="9" min="0" inputmode="numeric" data-inputmask-alias="integer" data-inputmask-allowMinus="false" data-inputmask-placeholder="0"' . $transit_aria . '/>
                                                <button class="btn material-icons plus border border-start-0" type="button" aria-label="' . h(lang('Increase')) . '">add</button>
                                            </div>' . $transit_feedback . '
                                            <div class="form-text">' . lang('Shipping Delays Specific to this Country') . '</div>
                                        </div>
                                        <div class="col-12 md:col-6 my-3 md:pt-7">
                                            <div class="form-field">
                                                <div class="switch"><input value="1"' . $zip_checked . ' name="zip_code_required" id="zip_code_required" type="checkbox" role="switch"></div>
                                                <label for="zip_code_required">' . lang('Zip Code Required') . '</label>
                                            </div>
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
                                        ' . lang('Checkout') . '
                                    </h2>
                                    <div class="card-body pt-0">
                                        <div class="form-field">
                                            <div class="switch"><input value="1"' . $default_checked . ' name="default_selected" id="default_selected" type="checkbox" role="switch"></div>
                                            <label for="default_selected">' . lang('Selected by Default') . '</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="card mb-7">
                                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                        ' . lang('Summary') . '
                                    </h2>
                                    <div class="card-body pt-0">
                                        <dl class="mb-0" id="country_summary" data-yes="' . h(lang('Yes')) . '" data-no="' . h(lang('No')) . '">'
        . pg_country_form_status_row(lang('Code'), h($values['code']), false, 'country_summary_code')
        . pg_country_form_status_row(lang('Zip Code Required'), h($zip_checked !== '' ? lang('Yes') : lang('No')), false, 'country_summary_zip')
        . pg_country_form_status_row(lang('Extra Delivery Days'), h((string) $days), $status_rows === '', 'country_summary_days')
        . $status_rows . '
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <script>
                    (function () {
                        // The Summary follows the fields as they change.
                        var summary = document.getElementById("country_summary");
                        var code = document.getElementById("code");
                        var zip = document.getElementById("zip_code_required");
                        var days = document.getElementById("transit_adjustment_days");

                        if (!summary || !code || !zip || !days) {
                            return;
                        }

                        function update() {
                            var typed = days.value.trim();
                            document.getElementById("country_summary_code").textContent = code.value.trim();
                            document.getElementById("country_summary_zip").textContent = summary.getAttribute(zip.checked ? "data-yes" : "data-no");
                            document.getElementById("country_summary_days").textContent = /^[0-9]{1,9}$/.test(typed) ? String(parseInt(typed, 10)) : (typed === "" ? "0" : typed);
                        }

                        code.addEventListener("input", update);
                        zip.addEventListener("change", update);
                        days.addEventListener("input", update);
                        days.addEventListener("change", update);
                        // The plus and minus buttons set the value without an input event.
                        document.addEventListener("click", function (event) {
                            if (event.target.closest && event.target.closest(".number-controls")) {
                                setTimeout(update, 0);
                            }
                        });
                    })();
                    </script>';
}

/**
 * One Summary row of the status column.
 *
 * @param string $label The label (plain text)
 * @param string $value The value (HTML, escaped by the caller)
 * @param bool   $last  The last row draws no bottom border
 * @param string $id    The value's id, for a row the script updates
 * @return string  HTML
 */
function pg_country_form_status_row($label, $value, $last = true, $id = '')
{
    return '
                                            <div class="d-flex justify-content-between gap-3 py-2' . ($last ? '' : ' border-bottom') . '">
                                                <dt class="fw-normal">' . h($label) . '</dt>
                                                <dd class="mb-0 fw-bold text-end text-break"' . ($id !== '' ? ' id="' . h($id) . '"' : '') . '>' . $value . '</dd>
                                            </div>';
}

/**
 * The edit screen's Summary rows: the states of the country, the shipping
 * and tax zones it or its states are in, and the last change.
 *
 * @param int    $states    States of the country
 * @param int    $zones     Shipping zones (pg_zone_form_member_delete_counts())
 * @param int    $tax_zones Tax zones (pg_zone_form_member_delete_counts())
 * @param string $changed   The last change, HTML (relative time and user)
 * @return string  HTML
 */
function pg_country_form_edit_status_rows($states, $zones, $tax_zones, $changed)
{
    return pg_country_form_status_row(lang('States'), (string) (int) $states, false)
        . pg_country_form_status_row(lang('Shipping Zones'), (string) (int) $zones, false)
        . pg_country_form_status_row(lang('Tax Zones'), (string) (int) $tax_zones, false)
        . pg_country_form_status_row(lang('Last Modified'), $changed);
}

/**
 * The screen row: the columns (pg_country_form_card()) and the sticky save
 * bar with the given buttons.
 *
 * @param string $cards   pg_country_form_card()
 * @param string $buttons The buttons inside the bar's group
 * @return string  HTML
 */
function pg_country_form_layout($cards, $buttons)
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
 * The Delete confirmation of Edit Country as lang() arguments: the country
 * goes with its states and their addresses, then the shipping zones and
 * tax zones it or its states leave, and those left with no member.
 *
 * @param int $zones
 * @param int $tax_zones
 * @param int $emptied_zones
 * @param int $emptied_tax_zones
 * @return array list of lang() arguments, one per sentence
 */
function pg_country_form_delete_confirm($zones, $tax_zones, $emptied_zones, $emptied_tax_zones)
{
    return array_merge(
        array(array('string' => 'WARNING: This country and all states and verified shipping addresses in this country will be permanently deleted.')),
        pg_zone_form_member_delete_sentences($zones, $tax_zones, $emptied_zones, $emptied_tax_zones)
    );
}
