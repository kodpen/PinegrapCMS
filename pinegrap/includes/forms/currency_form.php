<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The currency form, shared by the create and the edit screens
 * (add_currency.php, edit_currency.php): the form and status columns with
 * the save bar, the check of a
 * code another currency uses, the exchange rate a posted currency is saved
 * with and the base flag it takes from the others. The screens keep what
 * differs: the edit screen loads the record, offers delete and updates the
 * row; the create screen inserts it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The largest rate currencies.exchange_rate (decimal(10,5)) holds.
if (!defined('PG_CURRENCY_FORM_MAX_RATE')) {
    define('PG_CURRENCY_FORM_MAX_RATE', 99999.99999);
}

/**
 * The form column (Options card: name, code, symbol; Exchange Rate card: the
 * rate and the base switch) and the sticky status column (a Summary of the
 * code, the symbol, the base flag and the rate as "1 BASE = rate CODE",
 * following the fields as they are typed), in the edit screen layout of
 * product_builder.php. Below lg the status column follows the form in
 * source order.
 *
 * $locked_base is true on the edit screen of the stored base currency: its
 * switch is drawn on and disabled (a hidden field posts the 1), because a
 * store without a base currency falls back to USD for every price and
 * payment. The base moves by making another currency the base.
 *
 * @param liveform $liveform
 * @param bool $locked_base
 * @return string
 */
function pg_currency_form_cards($liveform, $locked_base = false)
{
    $is_base = $locked_base || ($liveform->get_field_value('base') == 1);

    // The rate is "units of this currency per 1 base unit"; the example
    // names another currency than the base so it never reads "1 USD = 50 USD".
    $base_code = defined('BASE_CURRENCY_CODE') ? strtoupper((string) BASE_CURRENCY_CODE) : '';
    $example_code = ($base_code === 'USD') ? 'EUR' : 'USD';
    $rate_help = lang(array(
        'string' => 'How much of this currency 1 {var:1} buys. Example: if 1 {var:2} costs 50 {var:1}, enter {var:3} (1 ÷ 50), not 50.',
        'vars' => array(h($base_code !== '' ? $base_code : lang('Base')), h($example_code), h(pg_format_number(0.02, 2))),
    ));

    $rate_attributes = array('type'=>'text', 'name'=>'exchange_rate', 'id'=>'exchange_rate', 'class'=>'form-control', 'maxlength'=>'12','inputmode'=>'decimal','style'=>'text-align: right;', 'aria-describedby'=>'exchange_rate_help') + pg_money_mask_attributes(null, 5);

    // A non-base currency needs a rate; the script below follows the switch.
    if (!$is_base) {
        $rate_attributes['required'] = 'required';
    }

    if ($locked_base) {
        $base_field = '<div class="switch"><input type="checkbox" id="base" value="1" class="no-bs" checked disabled aria-describedby="base_help"></div>
                                                <input type="hidden" name="base" value="1">';
        $base_help = '<div id="base_help" class="form-text">' . lang('This is the base currency. To change it, make another currency the base.') . '</div>';
    } else {
        $base_field = '<div class="switch">' . $liveform->output_field(array('type'=>'checkbox', 'id'=>'base', 'name'=>'base', 'value'=>'1', 'class'=>'no-bs')) . '</div>';
        $base_help = '';
    }

    $code = strtoupper((string) $liveform->get_field_value('code'));
    $summary_base = ($base_code !== '') ? $base_code : lang('Base');

    return '
                        <div class="col-12 lg:col-8 2xl:col-9 order-1">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Options') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="row">
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Currency'))) . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'class'=>'form-control', 'maxlength'=>'100', 'required'=>'required')) . '
                                        </div>
                                        <div class="col-12 sm:col-6 md:col-3 my-3">
                                            <label for="code" class="form-label">' . lang('Code') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'code', 'id'=>'code', 'class'=>'form-control', 'maxlength'=>'3', 'required'=>'required', 'pattern'=>'[A-Za-z]{3}', 'style'=>'text-transform: uppercase;', 'aria-describedby'=>'code_help')) . '
                                            <div id="code_help" class="form-text">' . lang('Three-letter ISO 4217 code, e.g. USD or EUR.') . '</div>
                                        </div>
                                        <div class="col-12 sm:col-6 md:col-3 my-3">
                                            <label for="symbol" class="form-label">' . lang('Symbol') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'symbol', 'id'=>'symbol', 'class'=>'form-control', 'maxlength'=>'10')) . '
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Exchange Rate') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="row">
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="exchange_rate" class="form-label">' . lang('Exchange Rate') . '<span id="exchange_rate_marker" class="pg-required-marker fg-danger ms-1' . ($is_base ? ' d-none' : '') . '" aria-hidden="true">*</span></label>
                                            ' . $liveform->output_field($rate_attributes) . '
                                            <div id="exchange_rate_help" class="form-text">' . $rate_help . '</div>
                                        </div>
                                        <div class="col-12 md:col-6 my-3">
                                            <div class="form-field">
                                                ' . $base_field . '
                                                <label class="" for="base">' . lang('Base') . '</label>
                                            </div>
                                            ' . $base_help . '
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 lg:col-4 2xl:col-3 order-2">
                            <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                            <div class="position-sticky" style="top:3.3rem;">
                                <div class="card mb-7">
                                    <h2 class="card-header bg-reset border-0 pg-title-section">
                                        ' . lang('Summary') . '
                                    </h2>
                                    <div class="card-body pt-0">
                                        <dl class="mb-0">
                                            <div class="d-flex justify-content-between gap-3 py-2 border-bottom">
                                                <dt class="fw-normal">' . lang('Code') . '</dt>
                                                <dd class="mb-0 fw-bold text-end text-break" id="currency_summary_code">' . h($code) . '</dd>
                                            </div>
                                            <div class="d-flex justify-content-between gap-3 py-2 border-bottom">
                                                <dt class="fw-normal">' . lang('Symbol') . '</dt>
                                                <dd class="mb-0 fw-bold text-end text-break" id="currency_summary_symbol">' . h((string) $liveform->get_field_value('symbol')) . '</dd>
                                            </div>
                                            <div class="d-flex justify-content-between gap-3 py-2 border-bottom">
                                                <dt class="fw-normal">' . lang('Base') . '</dt>
                                                <dd class="mb-0 fw-bold text-end" id="currency_summary_base" data-yes="' . h(lang('Yes')) . '" data-no="' . h(lang('No')) . '">' . h($is_base ? lang('Yes') : lang('No')) . '</dd>
                                            </div>
                                            <div class="py-2">
                                                <dt class="fw-normal">' . lang('Exchange Rate') . '</dt>
                                                <dd class="mb-0 fw-bold text-break" id="currency_summary_rate" data-base="' . h($summary_base) . '">' . h(pg_currency_form_rate_sentence($summary_base, $is_base ? '1' : (string) $liveform->get_field_value('exchange_rate'), $is_base ? $summary_base : $code)) . '</dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <script>
                    (function () {
                        // The base currency is saved with rate 1, so its rate is not required.
                        var base = document.getElementById("base");
                        var rate = document.getElementById("exchange_rate");
                        var marker = document.getElementById("exchange_rate_marker");

                        var code = document.getElementById("code");
                        var symbol = document.getElementById("symbol");
                        var summaryRate = document.getElementById("currency_summary_rate");
                        var summaryBase = document.getElementById("currency_summary_base");

                        if (!base || !rate || !marker || !code || !summaryRate) {
                            return;
                        }

                        // The Summary follows the fields as they are typed.
                        function summary() {
                            var baseCode = summaryRate.getAttribute("data-base");
                            var typed = code.value.trim().toUpperCase();
                            document.getElementById("currency_summary_code").textContent = typed;
                            document.getElementById("currency_summary_symbol").textContent = symbol ? symbol.value : "";
                            summaryBase.textContent = summaryBase.getAttribute(base.checked ? "data-yes" : "data-no");
                            summaryRate.textContent = base.checked
                                ? "1 " + baseCode + " = 1 " + baseCode
                                : ((rate.value.trim() !== "" && typed !== "") ? "1 " + baseCode + " = " + rate.value.trim() + " " + typed : "");
                        }

                        code.addEventListener("input", summary);
                        rate.addEventListener("input", summary);

                        if (symbol) {
                            symbol.addEventListener("input", summary);
                        }

                        if (base.disabled) {
                            return;
                        }

                        base.addEventListener("change", function () {
                            rate.required = !base.checked;
                            marker.classList.toggle("d-none", base.checked);
                            summary();
                        });
                    })();
                    </script>';
}

/**
 * The Summary's rate line: "1 BASE = rate CODE", or '' while the rate or the
 * code is blank.
 *
 * @param string $base_code  The base currency code (or its label)
 * @param string $rate       The rate as typed
 * @param string $code       This currency's code
 * @return string
 */
function pg_currency_form_rate_sentence($base_code, $rate, $code)
{
    $rate = trim((string) $rate);
    $code = strtoupper(trim((string) $code));

    if (($rate === '') || ($code === '')) {
        return '';
    }

    return '1 ' . $base_code . ' = ' . $rate . ' ' . $code;
}

/**
 * The screen row: the cards (pg_currency_form_cards()) and the sticky save
 * bar with the given buttons.
 *
 * @param string $cards    pg_currency_form_cards()
 * @param string $buttons  The buttons inside the bar's group
 * @return string
 */
function pg_currency_form_layout($cards, $buttons)
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
 * A posted currency code as it is checked and saved: trimmed and in capitals.
 *
 * @param mixed $posted
 * @return string
 */
function pg_currency_form_code($posted)
{
    return is_scalar($posted) ? strtoupper(trim((string) $posted)) : '';
}

/**
 * The message for a code that is not three Latin letters (ISO 4217), or ''.
 * The column holds three characters and the database would cut a longer
 * code silently, so a fourth letter could make a second "USD"; the rate
 * update matches currencies by this code.
 *
 * @param string $code  as pg_currency_form_code() returns it
 * @return string
 */
function pg_currency_form_code_error($code)
{
    return preg_match('/^[A-Z]{3}$/', (string) $code)
        ? ''
        : lang('The code must be a three-letter ISO 4217 code (e.g. USD, EUR).');
}

/**
 * The message for a save that would leave the store without a base
 * currency (the stored base posted with the switch off), or ''. Without a
 * base row, prices are shown in $ and payments are sent in USD.
 *
 * @param mixed $stored_base
 * @param mixed $posted_base
 * @return string
 */
function pg_currency_form_base_error($stored_base, $posted_base)
{
    return (($stored_base == 1) && ($posted_base != 1))
        ? lang('The base currency cannot be switched off. Make another currency the base instead.')
        : '';
}

/**
 * The message for deleting the stored base currency, or ''.
 *
 * @param mixed $stored_base
 * @return string
 */
function pg_currency_form_delete_error($stored_base)
{
    return ($stored_base == 1)
        ? lang('The base currency cannot be deleted. Make another currency the base first.')
        : '';
}

/**
 * Whether another currency already uses a code.
 *
 * @param string $code
 * @param string|null $id  the currency being edited, null on create
 * @return bool
 */
function pg_currency_form_code_in_use($code, $id)
{
    $query =
        "SELECT id
        FROM currencies
        WHERE (code = ?)";
    $values = array((string) $code);

    if ($id !== null) {
        $query .= " AND (id != ?)";
        $values[] = (string) $id;
    }

    $result = db_q($query, $values) or output_error('Query failed.');

    return mysqli_num_rows($result) > 0;
}

/**
 * The exchange rate a posted currency is saved with: the masked amount
 * (1.234,56 or 1,234.56) with five decimals, the column's scale and what
 * update_exchange_rates.php writes (a plain float cast would write a small
 * rate as "6.0E-5"); 1 for the base currency.
 *
 * @param string $posted
 * @param mixed $base
 * @return mixed
 */
function pg_currency_form_exchange_rate($posted, $base)
{
    if ($base == 1) {
        return 1;
    }

    $exchange_rate = pg_normalize_amount($posted);

    if (is_numeric($exchange_rate)) {
        $exchange_rate = number_format((float) $exchange_rate, 5, '.', '');
    }

    return $exchange_rate;
}

/**
 * The message for a posted exchange rate a non-base currency cannot be
 * saved with, or ''. A rate above the column's largest value is refused. An
 * empty, non-numeric, negative or zero rate (or one
 * that rounds to zero at the column's five decimals) is stored as 0, and the
 * storefront divides a donation by the visitor currency's rate, which stops
 * the page on PHP 8. The base currency is always saved with 1.
 *
 * @param mixed $posted
 * @param mixed $base
 * @return string
 */
function pg_currency_form_exchange_rate_error($posted, $base)
{
    if ($base == 1) {
        return '';
    }

    $exchange_rate = is_scalar($posted) ? pg_currency_form_exchange_rate((string) $posted, '') : '';

    if (!is_numeric($exchange_rate) || ((float) $exchange_rate <= 0)) {
        return lang('Enter an exchange rate greater than zero.');
    }

    // The column is decimal(10,5): a larger rate would be cut to its top
    // silently, so it is refused instead.
    if ((float) $exchange_rate > PG_CURRENCY_FORM_MAX_RATE) {
        return lang(array(
            'string' => 'Enter an exchange rate no greater than {var:1}.',
            'vars' => array(h(pg_format_number(PG_CURRENCY_FORM_MAX_RATE, 5))),
        ));
    }

    return '';
}
