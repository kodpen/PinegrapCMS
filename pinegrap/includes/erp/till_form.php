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
 * @return string  HTML
 */
function erp_till_form_cards($liveform)
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
    <div class="card my-4">
        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
            ' . lang('Till or Bank Account') . '
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-12 col-lg-5 my-2">
                    <label for="name" class="form-label">' . lang('Name') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'name', 'name' => 'name',
                        'class' => 'form-control', 'maxlength' => '100',
                        'autocomplete' => 'off', 'required' => 'required')) . '
                </div>
                <div class="col-12 col-sm-6 col-lg-4 my-2">
                    <label for="kind" class="form-label">' . lang('Type') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'select', 'id' => 'kind', 'name' => 'kind',
                        'class' => 'form-select', 'options' => $kind_options)) . '
                </div>
                <div class="col-12 col-sm-6 col-lg-3 my-2">
                    <label for="is_active" class="form-label">' . lang('Status') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'select', 'id' => 'is_active', 'name' => 'is_active',
                        'class' => 'form-select', 'options' => $active_options)) . '
                </div>
            </div>
            <div class="row">
                <div class="col-12 col-lg-4 my-2">
                    <label for="bank_name" class="form-label">' . lang('Bank') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'bank_name', 'name' => 'bank_name',
                        'class' => 'form-control', 'maxlength' => '100', 'autocomplete' => 'off')) . '
                </div>
                <div class="col-12 col-lg-5 my-2">
                    <label for="iban" class="form-label">' . lang('IBAN') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'iban', 'name' => 'iban',
                        'class' => 'form-control', 'maxlength' => '34', 'autocomplete' => 'off')) . '
                </div>
                <div class="col-12 col-sm-4 col-lg-3 my-2">
                    <label for="opening_balance" class="form-label">' . lang('Opening Balance') . '</label>
                    <div class="input-group">
                        ' . $liveform->output_field(array(
                            'type' => 'text', 'id' => 'opening_balance', 'name' => 'opening_balance',
                            'class' => 'form-control text-end', 'maxlength' => '15',
                            'inputmode' => 'decimal', 'autocomplete' => 'off')) . '
                        <label class="input-group-text" for="opening_balance">' . BASE_CURRENCY_SYMBOL . '</label>
                    </div>
                    <div class="form-text">' . lang('What was in it before the first movement was recorded here.') . '</div>
                </div>
            </div>
        </div>
    </div>';
}
