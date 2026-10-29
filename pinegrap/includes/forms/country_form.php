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
 * non-scalar value is empty.
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

    return $values;
}

/**
 * The options card, from the opening row to its closing tag; the screens
 * print the form tag, the buttons and the edit screen's id around it.
 *
 * @param array|null $values The fields of pg_country_form_values() from a
 *                           stored country; null on create, which prints no
 *                           values and no switch on
 * @return string  HTML
 */
function pg_country_form_card($values)
{
    if ($values === null) {
        $values = pg_country_form_values(array());
    }

    $name_value = ($values['name'] !== '') ? ' value="' . h($values['name']) . '"' : '';
    $code_value = ($values['code'] !== '') ? ' value="' . h($values['code']) . '"' : '';
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
                                                <input type="text" name="code" id="code" class="form-control" maxlength="50"' . $code_value . ' />
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
