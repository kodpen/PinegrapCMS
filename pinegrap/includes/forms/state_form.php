<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The state/province form, shared by the create and the edit screens
 * (add_state.php, edit_state.php): the options card (name, code, country)
 * and the posted values both screens write. The screens keep what differs:
 * the edit screen loads the state, posts its id, offers delete and clears
 * the zone and address links of a deleted state; each writes its own row.
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
 * The posted name, code and country id as text, in INSERT/UPDATE order.
 * A missing or non-scalar value is empty.
 *
 * @param array $post The posted form ($_POST)
 * @return array  'name', 'code', 'country_id'
 */
function pg_state_form_values($post)
{
    $values = array();

    foreach (array('name', 'code', 'country_id') as $key) {
        $values[$key] = (isset($post[$key]) && is_scalar($post[$key])) ? (string) $post[$key] : '';
    }

    return $values;
}

/**
 * The options card, from the opening row to its closing tag; the screens
 * print the form tag, the buttons and the edit screen's id around it.
 *
 * @param array|null $values 'name', 'code', 'country_id' of a stored state
 *                           (plain text); null on create, which prints no values
 * @return string  HTML
 */
function pg_state_form_card($values)
{
    if ($values === null) {
        $name_value = '';
        $code_value = '';
        $country_options = select_country();
    } else {
        $name_value = 'value="' . h($values['name']) . '" ';
        $code_value = 'value="' . h($values['code']) . '" ';
        $country_options = select_country($values['country_id']);
    }

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
                                                <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('State'))) . '</label>
                                                <input ' . $name_value . 'name="name" id="name" type="text" class="form-control" maxlength="50" />
                                            </div>
                                            <div class="col-12 md:col-6 lg:col-4 my-3">
                                                <label for="code" class="form-label">' . lang('State Code') . '</label>
                                                <input ' . $code_value . 'name="code" id="code" type="text" class="form-control" maxlength="50" />
                                                <div class="text-end form-text">' . lang('New State/Province Code for Order Reporting') . '</div>
                                            </div>
                                            <div class="col-12 md:col-12 lg:col-4 my-3">
                                                <label for="name" class="form-label">' . lang('Country') . '</label>
                                                <select name="country_id" id="country_id"  class="form-control">' .  $country_options . '</select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
';
}
