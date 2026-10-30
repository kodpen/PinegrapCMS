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
 * @return string  HTML
 */
function erp_till_form_cards($liveform, $currency_locked = false)
{
    $kind_options = array();
    $kind_options[lang('Cash')] = 'cash';
    $kind_options[lang('Bank')] = 'bank';
    $kind_options[lang('Card terminal')] = 'pos';
    $kind_options[lang('Credit card')] = 'credit_card';

    $active_options = array();
    $active_options[lang('Active')] = '1';
    $active_options[lang('Passive')] = '0';

    return '
    <div class="card my-7">
        <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
            ' . lang('Till or Bank Account') . '
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-12 lg:col-5 my-3">
                    <label for="name" class="form-label">' . lang('Name') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'name', 'name' => 'name',
                        'class' => 'form-control', 'maxlength' => '100',
                        'autocomplete' => 'off', 'required' => 'required')) . '
                </div>
                <div class="col-12 sm:col-6 lg:col-4 my-3">
                    <label for="kind" class="form-label">' . lang('Type') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'select', 'id' => 'kind', 'name' => 'kind',
                        'class' => 'form-control', 'options' => $kind_options)) . '
                </div>
                <div class="col-12 sm:col-6 lg:col-3 my-3">
                    <label for="is_active" class="form-label">' . lang('Status') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'select', 'id' => 'is_active', 'name' => 'is_active',
                        'class' => 'form-control', 'options' => $active_options)) . '
                </div>
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
                        'class' => 'form-control', 'maxlength' => '34', 'autocomplete' => 'off',
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
