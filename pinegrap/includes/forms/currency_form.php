<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The currency form, shared by the create and the edit screens
 * (add_currency.php, edit_currency.php): the options card, the check of a
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

/**
 * The Options card: name, code, symbol, exchange rate and the base switch.
 *
 * @param liveform $liveform
 * @return string
 */
function pg_currency_form_card($liveform)
{
    return '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Options') . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Currency'))) . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'class'=>'form-control', 'maxlength'=>'100', 'required'=>'required')) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        <div class="col-12 md:col-3 my-3">
                                            <label for="code" class="form-label">' . lang('Code') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'code', 'id'=>'code', 'class'=>'form-control', 'maxlength'=>'3', 'required'=>'required')) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        <div class="col-12 md:col-3 my-3">
                                            <label for="symbol" class="form-label">' . lang('Symbol') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'symbol', 'id'=>'symbol', 'class'=>'form-control', 'maxlength'=>'10')) . '
                                        </div>
                                        <div class="col-12 md:col-3 my-3">
                                            <label for="exchange_rate" class="form-label">' . lang('Exchange Rate') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'exchange_rate', 'id'=>'exchange_rate', 'class'=>'form-control', 'maxlength'=>'12','inputmode'=>'decimal','style'=>'text-align: right;') + pg_money_mask_attributes(null, 5)) . '
                                        </div>

                                        <div class="col-12 my-5">
                                            <div class="form-field">
                                                <div class="switch">' . $liveform->output_field(array('type'=>'checkbox', 'id'=>'base', 'name'=>'base', 'value'=>'1', 'class'=>'no-bs')) . '</div>
                                                <label class="" for="base">' . lang('Base') . '</label>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>';
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
 * Takes the base flag from every currency but the given one, so only one
 * currency is the base.
 *
 * @param string|int $id
 * @return void
 */
function pg_currency_form_clear_other_bases($id)
{
    db_q(
        "UPDATE currencies
        SET base = '0'
        WHERE
            (base = '1')
            AND (id != ?)",
        array((string) $id));
}
