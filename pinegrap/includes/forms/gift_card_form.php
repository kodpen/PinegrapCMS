<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The gift card form, shared by the create and the edit screens
 * (add_gift_card.php, edit_gift_card.php): the Gift Card Options card (the
 * money-masked amount or balance, the expiration date, the create screen's
 * quantity, the notes), the expiration date the create screen opens with
 * and the check of the posted amount and date. The screens keep what
 * differs: the create screen's quantity limit and INSERT loop; the edit
 * screen's not-found guard, the balance_was field and its stale-save guard,
 * the order info and redemption history cards and delete.
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
 * The expiration date a new gift card opens with: the number of validity
 * days from today, written the way the date field takes it; '' when no
 * validity days are set.
 *
 * @param mixed  $validity_days ECOMMERCE_GIFT_CARD_VALIDITY_DAYS
 * @param string $date_format   DATE_FORMAT ('month_day' or 'day_month')
 * @param int    $now           Unix time of today
 * @return string
 */
function pg_gift_card_form_default_expiration($validity_days, $date_format, $now)
{
    if (!$validity_days) {
        return '';
    }

    $month_and_day_format = ($date_format == 'month_day') ? 'n/j' : 'j/n';

    return date($month_and_day_format . '/Y', strtotime('+' . (int) $validity_days . ' day', (int) $now));
}

/**
 * The Gift Card Options card, from the opening row to its closing tag; the
 * screens print the form tag, their hidden fields and the buttons around it.
 *
 * @param liveform $liveform
 * @param string   $mode     'add' (amount and quantity) or 'edit' (balance)
 * @param array    $options  'symbol' the currency symbol beside the amount,
 *                           as HTML (BASE_CURRENCY_SYMBOL);
 *                           'scripts' the date picker format and field
 *                           scripts; 'quantity_max' (add) the highest
 *                           quantity, '' for none; 'original_amount' (edit)
 *                           the card's first amount, formatted
 * @return string  HTML
 */
function pg_gift_card_form_card($liveform, $mode, $options)
{
    $add = ($mode === 'add');
    $field = $add ? 'amount' : 'balance';

    if ($add) {
        $output_label = lang('Amount');
        $output_help = '<script>$("#amount").focus()</script>';
    } else {
        $output_label = lang('Balance');
        $output_help = '<div class="form-text text-end">' . lang(array('string'=>'Original Amount: {var:1}','vars'=>array($options['original_amount'] ?? ''))) . '</div>';
    }

    $output_quantity = '';

    if ($add) {
        $output_quantity = '
                                        <div class="col-12 sm:col-4 xl:col-3 my-3">
                                            <label for="quantity" class="form-label">' . lang('Quantity') . '</label>
                                            <div class="input-group number-controls">
                                                <button class="btn material-icons minus border border-end-0" type="button" aria-label="' . h(lang('Decrease')) . '">remove</button>
                                                ' . $liveform->output_field(array(
                                                    'type' => 'text',
                                                    'name' => 'quantity',
                                                    'id' => 'quantity',
                                                    'value' => '1',
                                                    'min' => '1',
                                                    'max' => (string) ($options['quantity_max'] ?? ''),
                                                    'class' => 'form-control text-center border-start-0 border-end-0',
                                                    'inputmode'=>'numeric',
                                                    'data-inputmask-alias'=>'decimal',
                                                    'data-inputmask-placeholder'=>'0')) . '
                                                <button class="btn material-icons plus border border-start-0" type="button" aria-label="' . h(lang('Increase')) . '">add</button>
                                            </div>
                                            <div class="text-end form-text">' . lang('increase quantity to create multiple gift cards at once') . '</div>
                                        </div>';
    }

    return '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Gift Card Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 sm:col-4 lg:col-3 xl:col-2 my-3">
                                            <label for="' . $field . '" class="form-label">' . $output_label . '</label>
                                            <label class="form-control form-adorn form-adorn-end">
                                                <span class="form-adorn-text" aria-hidden="true">' . ($options['symbol'] ?? '') . '</span>
                                                ' . $liveform->output_field(array('type' => 'text', 'id' => $field, 'name' => $field, 'class' => 'form-ghost text-end', 'maxlength'=>'12', 'inputmode'=>'numeric', 'required'=>'required') + pg_money_mask_attributes()) . '
                                            </label>
                                            ' . $output_help . '
                                        </div>
                                        <div class="col-12 sm:col-4 xl:col-3 my-3">
                                            <label for="expiration_date" class="form-label">' . lang('Expiration Date') . '</label>
                                            ' . $liveform->output_field(array(
                                                'type' => 'text',
                                                'id' => 'expiration_date',
                                                'name' => 'expiration_date',
                                                'size' => '10',
                                                'maxlength' => '10',
                                                'class'=>'form-control',
                                                'autocomplete'=>'off',
                                                'data-pg-date' => '')) . '
                                                ' . ($options['scripts'] ?? '') . '
                                            <div class="form-text text-end">' . lang('leave blank for no expiration') . '</div>
                                        </div>' . $output_quantity . '
                                        <div class="col-12 xl:col-8 my-3">
                                            <label for="notes" class="form-label">' . lang('Notes') . '</label>
                                            ' . $liveform->output_field(array(
                                                'type' => 'textarea',
                                                'name' => 'notes',
                                                'id' => 'notes',
                                                'class' => 'form-control')) . '
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>';
}

/**
 * Checks the posted amount (create) or balance (edit) and the expiration
 * date, marking the liveform's errors: the amount is required and above 0,
 * the balance is required and 0 or above, a typed date must be valid.
 *
 * @param liveform $liveform
 * @param string   $mode 'add' or 'edit'
 * @return string|false  the amount read either way it is typed (12,50 /
 *                       1.234,56 / 1,234.56), as pg_normalize_amount() returns it
 */
function pg_gift_card_form_check($liveform, $mode)
{
    if ($mode === 'add') {
        $field = 'amount';
        $liveform->validate_required_field('amount', lang(array('string'=>'{var:1} is required','vars'=>lang('Amount'))));
    } else {
        $field = 'balance';
        $liveform->validate_required_field('balance', lang(array('string'=>'{var:1} is required','vars'=>lang('Balance'))));
    }

    $amount = pg_normalize_amount($liveform->get_field_value($field));

    if (
        ($liveform->check_field_error($field) == false)
        && ((is_numeric($amount) == false) || (($mode === 'add') ? ($amount <= 0) : ($amount < 0)))
    ) {
        $liveform->mark_error($field, ($mode === 'add') ? lang('Please enter a valid amount.') : lang('Please enter a valid balance.'));
    }

    $expiration_date = $liveform->get_field_value('expiration_date');

    if (($expiration_date != '') && (validate_date($expiration_date) == false)) {
        $liveform->mark_error('expiration_date', lang('Please enter a valid expiration date.'));
    }

    return $amount;
}
