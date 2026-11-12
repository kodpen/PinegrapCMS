<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The referral source form, shared by the create and the edit screens
 * (add_referral_source.php, edit_referral_source.php): the form column with
 * the information card (name, sort order, code), the sticky status column
 * with the Summary, the sticky save bar and the posted values both screens
 * write. The screens keep what differs: the edit screen loads the source,
 * posts its id and offers delete; each writes its own row.
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
 * The posted name, code and sort order as trimmed text, in INSERT/UPDATE
 * order. A missing or non-scalar value is empty.
 *
 * @param array $post The posted form ($_POST)
 * @return array  'name', 'code', 'sort_order'
 */
function pg_referral_source_form_values($post)
{
    $values = array();

    foreach (array('name', 'code', 'sort_order') as $key) {
        $values[$key] = (isset($post[$key]) && is_scalar($post[$key])) ? trim((string) $post[$key]) : '';
    }

    return $values;
}

/**
 * Why the values cannot be saved: the name or the code is blank (a value
 * of only spaces passes the browser's required check), the sort order is
 * not a whole number from 0 to 999 (the column is a smallint and the
 * session's sql_mode would clip or zero it silently), or another source
 * already uses the name or the code (an order keeps only the code, and the
 * checkout options are keyed by the name). Names and codes are compared
 * without case, as the table's collation does. Empty when all is well.
 *
 * @param array $values pg_referral_source_form_values()
 * @param array $others 'name' and 'code' of every other stored source
 * @return string  the translated message, or ''
 */
function pg_referral_source_form_error($values, $others = array())
{
    $labels = array('name' => 'Referral Source Name', 'code' => 'Referral Source Code');

    foreach ($labels as $key => $label) {
        if (($values[$key] ?? '') === '') {
            return lang(array('string' => '{var:1} is required.', 'vars' => array(lang($label))));
        }
    }

    $sort_order = (string) ($values['sort_order'] ?? '');
    if (($sort_order !== '') && (!ctype_digit($sort_order) || ((int) $sort_order > 999))) {
        return lang(array('string' => '{var:1} must be a whole number from 0 to 999.', 'vars' => array(lang('Sort Order'))));
    }

    $name = mb_strtolower($values['name'], 'UTF-8');
    $code = mb_strtolower($values['code'], 'UTF-8');

    foreach ($others as $other) {
        if (mb_strtolower((string) ($other['code'] ?? ''), 'UTF-8') === $code) {
            return lang(array('string' => 'The code {var:1} is already used by another referral source.', 'vars' => array($other['code'])));
        }
        if (mb_strtolower((string) ($other['name'] ?? ''), 'UTF-8') === $name) {
            return lang(array('string' => 'The name {var:1} is already used by another referral source.', 'vars' => array($other['name'])));
        }
    }

    return '';
}

/**
 * The sort order to store, from a value pg_referral_source_form_error()
 * accepted; an empty box is 0.
 *
 * @param string $text the posted sort order
 * @return int
 */
function pg_referral_source_sort_order($text)
{
    return (int) $text;
}

/**
 * The form column with the information card (name, sort order, code); the
 * screens print the form tag, the hidden fields and the save bar around it
 * (pg_referral_source_form_layout()).
 *
 * @param array|null $values 'name', 'code', 'sort_order' of a stored source
 *                           (plain text); null on create, which prints no values
 * @return string  HTML
 */
function pg_referral_source_form_card($values)
{
    if ($values === null) {
        $name_value = '';
        $code_value = '';
        $sort_order = '';
        $heading = lang('New Referral Source Information');
    } else {
        $name_value = ' value="' . h($values['name']) . '"';
        $code_value = ' value="' . h($values['code']) . '"';
        $sort_order = h($values['sort_order']);
        $heading = lang('Referral Source Information');
    }

    return '
                        <div class="col-12 lg:col-8 2xl:col-9 order-1">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . $heading . '
                                </h2>
                                <div class="card-body pt-0">
                                    <h3 class="h6 mt-3">' . lang('Shown in the "How did you hear about us?" choice at checkout') . '</h3>
                                    <div class="row">
                                        <div class="col-12 md:col-8 my-3">
                                            <label for="name" class="form-label">' . lang('Referral Source Name') . '</label>
                                            <input type="text" id="name" name="name" class="form-control" maxlength="50"' . $name_value . ' required />
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        <div class="col-12 md:col-4 my-3">
                                            <label for="sort_order" class="form-label">' . lang('Sort Order') . '</label>
                                            <div class="input-group number-controls">
                                                <button class="btn material-icons minus border border-end-0" type="button" aria-label="' . h(lang('Decrease')) . '">remove</button>
                                                <input class="form-control text-center border-start-0 border-end-0" value="' . $sort_order . '" type="text" name="sort_order" id="sort_order" maxlength="3" min="0" max="999" inputmode="numeric" data-inputmask-alias="integer" data-inputmask-allowMinus="false" data-inputmask-placeholder="0"/>
                                                <button class="btn material-icons plus border border-start-0" type="button" aria-label="' . h(lang('Increase')) . '">add</button>
                                            </div>
                                        </div>
                                    </div>
                                    <h3 class="h6 mt-5">' . lang('Referral Source Code for Order Reporting') . '</h3>
                                    <div class="row">
                                        <div class="col-12 md:col-8 my-3">
                                            <label for="code" class="form-label">' . lang('Referral Source Code') . '</label>
                                            <input type="text" id="code" name="code" class="form-control" maxlength="50"' . $code_value . ' required />
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>';
}

/**
 * The sticky status column: a Summary of the name, the code and the sort
 * order as typed (an empty sort order reads 0, as it is stored), then the
 * screen's own rows (the other sources on create, the last change on edit).
 *
 * @param array|null $values      As for pg_referral_source_form_card()
 * @param string     $status_rows More rows (pg_referral_source_form_status_row()), HTML
 * @return string  HTML
 */
function pg_referral_source_form_status($values, $status_rows = '')
{
    $name = ($values === null) ? '' : (string) $values['name'];
    $code = ($values === null) ? '' : (string) $values['code'];
    $sort_order = ($values === null) ? '' : (string) $values['sort_order'];

    return '
                        <div class="col-12 lg:col-4 2xl:col-3 order-2">
                            <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                            <div class="position-sticky" style="top:3.3rem;">
                                <div class="card mb-7">
                                    <h2 class="card-header bg-reset border-0 pg-title-section">
                                        ' . lang('Summary') . '
                                    </h2>
                                    <div class="card-body pt-0">
                                        <dl class="mb-0">'
        . pg_referral_source_form_status_row(lang('Name'), '<span id="referral_source_summary_name">' . h($name) . '</span>', false)
        . pg_referral_source_form_status_row(lang('Code'), '<span id="referral_source_summary_code">' . h($code) . '</span>', false)
        . pg_referral_source_form_status_row(lang('Sort Order'), '<span id="referral_source_summary_sort_order">' . h($sort_order === '' ? '0' : $sort_order) . '</span>', $status_rows === '')
        . $status_rows . '
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <script>
                            // The Summary follows the boxes as they are typed; the +/- buttons
                            // change the sort order without an input event, so change is read too.
                            [["name", "referral_source_summary_name", ""], ["code", "referral_source_summary_code", ""], ["sort_order", "referral_source_summary_sort_order", "0"]].forEach(function (pair) {
                                var box = document.getElementById(pair[0]);
                                var show = function () {
                                    var value = box.value.trim();
                                    document.getElementById(pair[1]).textContent = value === "" ? pair[2] : value;
                                };
                                box.addEventListener("input", show);
                                box.addEventListener("change", show);
                            });
                        </script>';
}

/**
 * One Summary row of the status column: a label and its value.
 *
 * @param string $label  The label, plain text
 * @param string $value  The value, already HTML
 * @param bool   $last   False draws the line under the row
 * @return string  HTML
 */
function pg_referral_source_form_status_row($label, $value, $last = true)
{
    return '
                                            <div class="d-flex justify-content-between gap-3 py-2' . ($last ? '' : ' border-bottom') . '">
                                                <dt class="fw-normal">' . h($label) . '</dt>
                                                <dd class="mb-0 fw-bold text-end text-break">' . $value . '</dd>
                                            </div>';
}

/**
 * The screen row: the form column, the status column and the sticky save
 * bar with the given buttons.
 *
 * @param string $columns pg_referral_source_form_card() . pg_referral_source_form_status()
 * @param string $buttons The buttons inside the bar's group
 * @return string  HTML
 */
function pg_referral_source_form_layout($columns, $buttons)
{
    return '
                    <div class="row">' . $columns . '
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
