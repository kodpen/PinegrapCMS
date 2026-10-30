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
 * A missing or non-scalar value is empty. The name and the code are
 * trimmed: checkout matches the code against the trimmed visitor post, and
 * the PAD SPACE collation ignores trailing but not leading spaces.
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

    $values['name'] = trim($values['name']);
    $values['code'] = trim($values['code']);

    return $values;
}

/**
 * Whether another state of the country already has the code.
 *
 * @param string   $code       The code
 * @param int      $country_id The country the state belongs to
 * @param int|null $except_id  The edited state (left out); null on create
 * @return bool
 */
function pg_state_form_code_taken($code, $country_id, $except_id = null)
{
    $query = "SELECT id FROM states WHERE code = ? AND country_id = ?";
    $values = array((string) $code, (int) $country_id);

    if ($except_id !== null) {
        $query .= " AND id != ?";
        $values[] = (int) $except_id;
    }

    $result = db_q($query . " LIMIT 1", $values) or output_error('Query failed.');

    return mysqli_num_rows($result) > 0;
}

/**
 * The message for a code another state of the same country has, or ''.
 * Only a non-empty code is looked up, and on edit only when the code or the
 * country changed, so an edit that keeps an old clash still saves.
 *
 * @param array      $values    'code', 'country_id' as posted
 * @param array|null $stored    The edited state's 'code', 'country_id'; null on create
 * @param int|null   $except_id The edited state's id; null on create
 * @param callable   $taken     Lookup as pg_state_form_code_taken() (tests stub it)
 * @return string
 */
function pg_state_form_code_error($values, $stored, $except_id, $taken = 'pg_state_form_code_taken')
{
    $code = trim((string) $values['code']);

    if ($code === '') {
        return '';
    }

    if (
        ($stored !== null)
        // A legacy padded stored code posted back unchanged arrives trimmed.
        && ($code === trim((string) $stored['code']))
        && ((int) $values['country_id'] === (int) $stored['country_id'])
    ) {
        return '';
    }

    return $taken($code, (int) $values['country_id'], $except_id)
        ? lang('The code that you entered is already in use by another state in this country, please enter a different code.')
        : '';
}

/**
 * The options card, from the opening row to its closing tag; the screens
 * print the form tag, the buttons and the edit screen's id around it.
 *
 * @param array|null $values 'name', 'code', 'country_id' of a stored state
 *                           or a refused post (plain text); null on create,
 *                           which prints no values
 * @param array      $errors Field => message; 'code' is printed under the code field
 * @return string  HTML
 */
function pg_state_form_card($values, $errors = array())
{
    $code_error = isset($errors['code']) ? (string) $errors['code'] : '';
    $code_class = ($code_error !== '') ? ' is-invalid' : '';
    $code_aria = ($code_error !== '') ? ' aria-invalid="true" aria-describedby="code-error"' : '';
    $code_feedback = ($code_error !== '') ? '
                                                <div id="code-error" class="invalid-feedback d-block">' . h($code_error) . '</div>' : '';

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
                                                <input ' . $code_value . 'name="code" id="code" type="text" class="form-control' . $code_class . '" maxlength="50"' . $code_aria . ' />' . $code_feedback . '
                                                <div class="text-end form-text">' . lang('New State/Province Code for Order Reporting') . '</div>
                                            </div>
                                            <div class="col-12 md:col-12 lg:col-4 my-3">
                                                <label for="country_id" class="form-label">' . lang('Country') . '</label>
                                                <select name="country_id" id="country_id"  class="form-control">' .  $country_options . '</select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
';
}
