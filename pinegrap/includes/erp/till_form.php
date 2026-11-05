<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the till form, shared by the create and the edit screen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

/**
 * The fields a till or bank account is described by.
 *
 * @param liveform $liveform
 * @param bool     $currency_locked  The till already has movements, so its
 *                                   currency is shown but cannot be changed.
 * @param bool     $with_status      false leaves the Status field out: the
 *                                   create screen draws it in its status
 *                                   column (erp_till_form_status_card()).
 * @return string  HTML
 */
function erp_till_form_cards($liveform, $currency_locked = false, $with_status = true)
{
    $kind_options = array();
    $kind_options[lang('Cash')] = 'cash';
    $kind_options[lang('Bank')] = 'bank';
    $kind_options[lang('Card terminal')] = 'pos';
    $kind_options[lang('Credit card')] = 'credit_card';

    return '
    <div class="card my-7">
        <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
            ' . lang('Till or Bank Account') . '
        </h2>
        <div class="card-body">
            <div class="row">
                <div class="col-12 ' . ($with_status ? 'lg:col-5' : 'lg:col-7') . ' my-3">
                    <label for="name" class="form-label">' . lang('Name') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'name', 'name' => 'name',
                        'class' => 'form-control', 'maxlength' => '100',
                        'autocomplete' => 'off', 'required' => 'required')) . '
                </div>
                <div class="col-12 ' . ($with_status ? 'sm:col-6 lg:col-4' : 'lg:col-5') . ' my-3">
                    <label for="kind" class="form-label">' . lang('Type') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'select', 'id' => 'kind', 'name' => 'kind',
                        'class' => 'form-control', 'options' => $kind_options)) . '
                </div>' . ($with_status ? '
                <div class="col-12 sm:col-6 lg:col-3 my-3">
                    <label for="is_active" class="form-label">' . lang('Status') . '</label>
                    ' . erp_till_form_status_field($liveform) . '
                </div>' : '') . '
            </div>
            <div class="row">
                <div class="col-12 lg:col-4 my-3">
                    <label for="bank_name" class="form-label">' . lang('Bank') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'bank_name', 'name' => 'bank_name',
                        'class' => 'form-control', 'maxlength' => '100', 'autocomplete' => 'off')) . '
                </div>
                <div class="col-12 lg:col-5 my-3">
                    <label for="iban" class="form-label">' . lang('IBAN') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'iban', 'name' => 'iban',
                        'class' => 'form-control', 'maxlength' => '42', 'autocomplete' => 'off',
                        'spellcheck' => 'false', 'autocapitalize' => 'characters')) . '
                </div>
                <div class="col-12 sm:col-4 lg:col-3 my-3">
                    <label for="opening_balance" class="form-label">' . lang('Opening Balance') . '</label>
                    <label class="form-control form-adorn form-adorn-end">
                        <span class="form-adorn-text" aria-hidden="true">' . (erp_fx_enabled() ? lang('in the till currency') : BASE_CURRENCY_SYMBOL) . '</span>
                        ' . $liveform->output_field(array(
                            'type' => 'text', 'id' => 'opening_balance', 'name' => 'opening_balance',
                            'class' => 'form-ghost text-end', 'maxlength' => '15',
                            'inputmode' => 'decimal', 'autocomplete' => 'off')) . '
                    </label>
                    <div class="form-text">' . lang('What was in it before the first movement was recorded here.') . '</div>
                </div>
            </div>
            ' . erp_currency_form_row($liveform, $currency_locked, lang('The currency the till or bank account holds. Every movement through it is in this currency.')) . '
        </div>
    </div>';
}

/**
 * The Active / Passive select of a till.
 *
 * @param liveform $liveform
 * @return string  HTML
 */
function erp_till_form_status_field($liveform)
{
    $active_options = array();
    $active_options[lang('Active')] = '1';
    $active_options[lang('Passive')] = '0';

    return $liveform->output_field(array(
        'type' => 'select', 'id' => 'is_active', 'name' => 'is_active',
        'class' => 'form-control', 'options' => $active_options));
}

/**
 * The Status card of the create screen's status column: the one field that
 * says whether the new till takes movements, with what passive means.
 *
 * @param liveform $liveform
 * @return string  HTML
 */
function erp_till_form_status_card($liveform)
{
    return '
    <div class="card my-7">
        <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
            ' . lang('Status') . '
        </h2>
        <div class="card-body pt-0">
            <label for="is_active" class="form-label">' . lang('Status') . '</label>
            ' . erp_till_form_status_field($liveform) . '
            <div class="form-text">' . lang('A passive till keeps its history but takes no new receipt, payment or transfer.') . '</div>
        </div>
    </div>';
}

/**
 * An IBAN as it is stored: upper case, without the spaces it is written in.
 *
 * @param string $iban
 * @return string
 */
function erp_till_iban_normalise($iban)
{
    $clean = preg_replace('/\s+/u', '', (string) $iban);

    // Not valid UTF-8: kept as typed, so the check refuses it rather than
    // reading it as empty.
    return strtoupper(($clean === null) ? (string) $iban : $clean);
}

/**
 * Why an IBAN is refused, or ''. Empty is allowed (a drawer has none).
 *
 * The ISO 13616 shape (two letters, two check digits, 11 to 30 letters and
 * digits) and the mod-97 check: the four leading characters go to the end,
 * each letter becomes two digits (A = 10 … Z = 35), and the number left
 * must give 1 when divided by 97. It is reduced in chunks, so no big
 * number library is needed.
 *
 * @param string $iban  As typed; normalised here.
 * @return string
 */
function erp_till_iban_refusal($iban)
{
    $iban = erp_till_iban_normalise($iban);

    if ($iban === '') {
        return '';
    }

    $refused = lang('This IBAN is not valid. Check it for a typing mistake.');

    if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban)) {
        return $refused;
    }

    $digits = '';
    foreach (str_split(substr($iban, 4) . substr($iban, 0, 4)) as $char) {
        $digits .= ctype_digit($char) ? $char : (string) (ord($char) - 55);
    }

    $remainder = 0;
    foreach (str_split($digits, 7) as $chunk) {
        $remainder = (int) (((string) $remainder . $chunk)) % 97;
    }

    return ($remainder === 1) ? '' : $refused;
}

/**
 * Why an opening balance is refused, or ''. Empty is allowed and saves 0.
 *
 * erp_kurus() reads anything without digits as 0, so a typo ("abc",
 * "1O0,00") would silently replace the figure; only a number, with an
 * optional leading minus and its thousands and decimal marks, passes.
 *
 * @param string $value
 * @return string
 */
function erp_till_opening_refusal($value)
{
    $value = trim((string) $value);

    if (($value === '') || preg_match('/^-?\s*[0-9][0-9., ]*$/', $value)) {
        return '';
    }

    return lang('Enter the opening balance as a number.');
}
