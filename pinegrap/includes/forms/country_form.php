<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The country form, shared by the create and the edit screens
 * (add_country.php, edit_country.php): the options card (name, code, the
 * zip code and default switches, transit adjustment days) and the posted
 * values both screens write. The screens keep what differs: the edit screen
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

    return $values;
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
 * The options card, from the opening row to its closing tag; the screens
 * print the form tag, the buttons and the edit screen's id around it.
 *
 * @param array|null $values The fields of pg_country_form_values() from a
 *                           stored country or a refused post; null on
 *                           create, which prints no values and no switch on
 * @param array      $errors Field => message; 'code' is printed under the code field
 * @return string  HTML
 */
function pg_country_form_card($values, $errors = array())
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
    $zip_checked = $values['zip_code_required'] ? ' checked' : '';
    $default_checked = ($values['default_selected'] == 1) ? ' checked' : '';

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
                                                <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Country'))) . '</label>
                                                <input type="text" name="name" id="name" class="form-control" maxlength="50"' . $name_value . ' />
                                                <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                                <div class="form-text">' . lang('Display on Commerce Pages') . '</div>
                                            </div>
                                            <div class="col-12 md:col-6 lg:col-4 my-3">
                                                <label for="code" class="form-label">' . lang('Code') . '</label>
                                                <input type="text" name="code" id="code" class="form-control' . $code_class . '" maxlength="50"' . $code_value . $code_aria . ' />' . $code_feedback . '
                                                <div class="form-text">' . lang('New Country Code for Order Reporting') . '</div>
                                            </div>

                                            <div class="col-12 my-1 mt-5">
                                                <div class="form-field">
                                                    <div class="switch"><input value="1"' . $zip_checked . ' name="zip_code_required" id="zip_code_required" type="checkbox" role="switch"></div>
                                                    <label for="zip_code_required">' . lang('Zip Code Required') . '</label>
                                                </div>
                                            </div>

                                            <div class="col-12 my-1 mb-5">
                                                <div class="form-field">
                                                    <div class="switch"><input value="1"' . $default_checked . ' name="default_selected" id="default_selected" type="checkbox" role="switch"></div>
                                                    <label for="default_selected">' . lang('Selected by Default') . '</label>
                                                </div>
                                            </div>

                                            <div class="col-12 md:col-6 lg:col-4 my-1">
                                                <label for="transit_adjustment_days" class="form-label">' . lang('Transit Adjustment Days') . '</label>
                                                <div class="input-group number-controls">
                                                    <button class="btn material-icons minus border border-end-0" type="button" aria-label="' . h(lang('Decrease')) . '">remove</button>
                                                    <input class="form-control text-center border-start-0 border-end-0" value="' . h($values['transit_adjustment_days']) . '" type="text" name="transit_adjustment_days" id="transit_adjustment_days" maxlength="9" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0"/>
                                                    <button class="btn material-icons plus border border-start-0" type="button" aria-label="' . h(lang('Increase')) . '">add</button>
                                                </div>
                                                <div class="form-text">' . lang('Shipping Delays Specific to this Country') . '</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
';
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
