<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The state/province form, shared by the create and the edit screens
 * (add_state.php, edit_state.php): the wide layout (the Options card with
 * name, code and country, a Summary in the status column, the save bar)
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
 * The required fields left blank, as field => message: a state with no name
 * prints an empty row in the lists and pickers, and one with no code cannot
 * be written to an order.
 *
 * @param array $values pg_state_form_values()
 * @return array  'name' and/or 'code' => message
 */
function pg_state_form_required_errors($values)
{
    $errors = array();

    if (trim((string) ($values['name'] ?? '')) === '') {
        $errors['name'] = lang('Enter a name.');
    }

    if (trim((string) ($values['code'] ?? '')) === '') {
        $errors['code'] = lang('Enter a code.');
    }

    return $errors;
}

/**
 * Whether a country with the id exists.
 *
 * @param int $country_id
 * @return bool
 */
function pg_state_form_country_exists($country_id)
{
    $result = db_q("SELECT id FROM countries WHERE id = ? LIMIT 1", array((int) $country_id)) or output_error('Query failed.');

    return mysqli_num_rows($result) > 0;
}

/**
 * The message for a country id that names no country, or ''. A state
 * saved under a deleted or hand-edited id shows no country in the list,
 * and its edit screen would select another one.
 *
 * @param string   $country_id The posted or stored country id
 * @param callable $exists     Lookup as pg_state_form_country_exists() (tests stub it)
 * @return string
 */
function pg_state_form_country_error($country_id, $exists = 'pg_state_form_country_exists')
{
    $country_id = trim((string) $country_id);

    if (!preg_match('/^[1-9][0-9]{0,9}$/', $country_id)) {
        return lang('Select a country.');
    }

    return $exists((int) $country_id) ? '' : lang('The selected country was not found. Select a country.');
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
 * The form column (the Options card: name, code, country) and the sticky
 * status column (a Summary of the name, the code and the country as they
 * are typed or picked, then the screen's own rows: on edit the zones and
 * addresses the state is in and the last change); the screens wrap it in
 * pg_state_form_layout().
 *
 * @param array|null $values      'name', 'code', 'country_id' of a stored state
 *                                or a refused post (plain text); null on create,
 *                                which prints no values
 * @param array      $errors      Field => message; 'name', 'code' and
 *                                'country_id' are printed under their fields
 * @param string     $status_rows More Summary rows (pg_state_form_status_row()), HTML
 * @return string  HTML
 */
function pg_state_form_cards($values, $errors = array(), $status_rows = '')
{
    $country_error = isset($errors['country_id']) ? (string) $errors['country_id'] : '';
    $country_class = ($country_error !== '') ? ' is-invalid' : '';
    $country_aria = ($country_error !== '') ? ' aria-invalid="true" aria-describedby="country_id-error"' : '';
    $country_feedback = ($country_error !== '') ? '
                                            <div id="country_id-error" class="invalid-feedback d-block">' . h($country_error) . '</div>' : '';
    $name_error = isset($errors['name']) ? (string) $errors['name'] : '';
    $name_class = ($name_error !== '') ? ' is-invalid' : '';
    $name_aria = ($name_error !== '') ? ' aria-invalid="true" aria-describedby="name-error"' : '';
    $name_feedback = ($name_error !== '') ? '
                                            <div id="name-error" class="invalid-feedback d-block">' . h($name_error) . '</div>' : '';
    $code_error = isset($errors['code']) ? (string) $errors['code'] : '';
    $code_class = ($code_error !== '') ? ' is-invalid' : '';
    $code_aria = ($code_error !== '') ? ' aria-invalid="true" aria-describedby="code-error"' : '';
    $code_feedback = ($code_error !== '') ? '
                                            <div id="code-error" class="invalid-feedback d-block">' . h($code_error) . '</div>' : '';

    if ($values === null) {
        $name = '';
        $code = '';
        $country_options = select_country();
    } else {
        $name = (string) $values['name'];
        $code = (string) $values['code'];
        $country_options = select_country($values['country_id']);
    }

    $summary = pg_state_form_status_row(lang('Name'), '<span id="state_summary_name">' . h($name) . '</span>', false)
        . pg_state_form_status_row(lang('Code'), '<span id="state_summary_code">' . h($code) . '</span>', false)
        . pg_state_form_status_row(lang('Country'), '<span id="state_summary_country">' . pg_state_form_selected_label($country_options) . '</span>', $status_rows === '')
        . $status_rows;

    return '
                        <div class="col-12 lg:col-8 2xl:col-9 order-1">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Options') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="row">
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('State'))) . '</label>
                                            <input value="' . h($name) . '" name="name" id="name" type="text" class="form-control' . $name_class . '" maxlength="50" required' . $name_aria . ' />' . $name_feedback . '
                                        </div>
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="code" class="form-label">' . lang('State Code') . '</label>
                                            <input value="' . h($code) . '" name="code" id="code" type="text" class="form-control' . $code_class . '" maxlength="50" required' . $code_aria . ' />' . $code_feedback . '
                                            <div class="form-text">' . lang('State/Province Code for Order Reporting') . '</div>
                                        </div>
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="country_id" class="form-label">' . lang('Country') . '</label>
                                            <select name="country_id" id="country_id" class="form-control' . $country_class . '" required' . $country_aria . '>' . $country_options . '</select>' . $country_feedback . '
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
                                        <dl class="mb-0">' . $summary . '
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <script>
                            // The Summary follows the fields as they change.
                            (function () {
                                function follow(id, summary_id, read) {
                                    var field = document.getElementById(id);
                                    var summary = document.getElementById(summary_id);
                                    var update = function () { summary.textContent = read(field); };
                                    field.addEventListener("input", update);
                                    field.addEventListener("change", update);
                                }
                                var value = function (field) { return field.value.trim(); };
                                var label = function (field) {
                                    var option = field.options[field.selectedIndex];
                                    return (option && option.value !== "") ? option.text : "";
                                };
                                follow("name", "state_summary_name", value);
                                follow("code", "state_summary_code", value);
                                follow("country_id", "state_summary_country", label);
                            })();
                        </script>';
}

/**
 * The label of the selected option of select_country(), as the option
 * prints it (already HTML-escaped), or '' when none is selected or the
 * selected one is the empty "Select a country" choice.
 *
 * @param string $options select_country() output
 * @return string  HTML
 */
function pg_state_form_selected_label($options)
{
    if (!preg_match('/<option value="([^"]*)" selected="selected">([^<]*)<\/option>/', (string) $options, $match)) {
        return '';
    }

    return ($match[1] === '') ? '' : $match[2];
}

/**
 * One Summary row of the status column: a label and its value.
 *
 * @param string $label  The label, plain text
 * @param string $value  The value, already HTML
 * @param bool   $last   False draws the line under the row
 * @return string
 */
function pg_state_form_status_row($label, $value, $last = true)
{
    return '
                                            <div class="d-flex justify-content-between gap-3 py-2' . ($last ? '' : ' border-bottom') . '">
                                                <dt class="fw-normal">' . h($label) . '</dt>
                                                <dd class="mb-0 fw-bold text-end text-break">' . $value . '</dd>
                                            </div>';
}

/**
 * The screen row: the columns (pg_state_form_cards()) and the sticky save
 * bar with the given buttons.
 *
 * @param string $cards    pg_state_form_cards()
 * @param string $buttons  The buttons inside the bar's group
 * @return string
 */
function pg_state_form_layout($cards, $buttons)
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
 * What a state delete takes or unlinks: its verified shipping addresses,
 * then the shipping zone and tax zone counts of
 * pg_zone_form_member_delete_counts().
 *
 * @param int $state_id
 * @return array list of (addresses, zones, tax zones, emptied zones, emptied tax zones)
 */
function pg_state_form_delete_counts($state_id)
{
    $result = db_q("SELECT COUNT(*) FROM verified_shipping_addresses WHERE state_id = ?", array((int) $state_id));
    $row = $result ? mysqli_fetch_row($result) : null;

    return array_merge(array($row ? (int) $row[0] : 0), pg_zone_form_member_delete_counts('state', $state_id));
}

/**
 * The Delete confirmation of Edit State as lang() arguments: the verified
 * shipping addresses of the state go with it, so they are named when there
 * are any; then the shipping zones and tax zones it leaves, and those left
 * with no member.
 *
 * @param int $addresses
 * @param int $zones
 * @param int $tax_zones
 * @param int $emptied_zones
 * @param int $emptied_tax_zones
 * @return array list of lang() arguments, one per sentence
 */
function pg_state_form_delete_confirm($addresses, $zones = 0, $tax_zones = 0, $emptied_zones = 0, $emptied_tax_zones = 0)
{
    $addresses = (int) $addresses;

    if ($addresses < 1) {
        $sentences = array(array('string' => 'WARNING: This {var:1} will be permanently deleted.', 'vars' => array(lang('state'))));
    } else {
        $sentences = array(array(
            'string' => 'WARNING: This state and its {var:1} verified shipping address{suffix:1} will be permanently deleted.',
            'vars' => array($addresses),
            'suffix' => array($addresses == 1 ? '' : 'es'),
        ));
    }

    return array_merge($sentences, pg_zone_form_member_delete_sentences($zones, $tax_zones, $emptied_zones, $emptied_tax_zones));
}
