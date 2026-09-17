<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the account form, shared by the create and the edit screen.
 *
 * Two screens, one set of fields. They are described once here because an
 * account that can be created carrying a field the edit screen does not show is
 * an account nobody can afterwards correct.
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
 * The fields an account is described by, as the cards they are shown in.
 *
 * @param liveform $liveform
 * @param bool     $with_opening  Show the opening balance card. Creating only:
 *                                once an account has movements behind it, the
 *                                opening figure is one of them and is corrected
 *                                there rather than retyped here.
 * @return string  HTML
 */
function erp_account_form_cards($liveform, $with_opening = false)
{
    $kind_options = array();
    $kind_options[lang('Customer')] = 'customer';
    $kind_options[lang('Supplier')] = 'supplier';
    $kind_options[lang('Customer and supplier')] = 'both';

    $status_options = array();
    $status_options[lang('Active')] = 'active';
    $status_options[lang('Passive')] = 'passive';

    $person_options = array();
    $person_options[lang('Person')] = '1';
    $person_options[lang('Company')] = '0';

    $output = '
    <div class="card my-4">
        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
            ' . lang('Account') . '
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-12 col-lg-6 my-2">
                    <label for="title" class="form-label">' . lang('Name') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'title', 'name' => 'title',
                        'class' => 'form-control', 'maxlength' => '255',
                        'autocomplete' => 'off', 'required' => 'required')) . '
                </div>
                <div class="col-12 col-sm-6 col-lg-3 my-2">
                    <label for="kind" class="form-label">' . lang('Type') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'select', 'id' => 'kind', 'name' => 'kind',
                        'class' => 'form-select', 'options' => $kind_options)) . '
                </div>
                <div class="col-12 col-sm-6 col-lg-3 my-2">
                    <label for="status" class="form-label">' . lang('Status') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'select', 'id' => 'status', 'name' => 'status',
                        'class' => 'form-select', 'options' => $status_options)) . '
                </div>
            </div>
        </div>
    </div>

    <div class="card my-4">
        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
            ' . lang('Tax Details') . '
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-12 col-sm-6 col-lg-3 my-2">
                    <label for="is_person" class="form-label">' . lang('Taxpayer') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'select', 'id' => 'is_person', 'name' => 'is_person',
                        'class' => 'form-select', 'options' => $person_options)) . '
                    <div class="form-text">' . lang('Decides whether the number is read as a TCKN or a VKN.') . '</div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3 my-2">
                    <label for="tax_number" class="form-label">' . lang('VKN / TCKN') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'tax_number', 'name' => 'tax_number',
                        'class' => 'form-control', 'maxlength' => '11',
                        'inputmode' => 'numeric', 'autocomplete' => 'off')) . '
                </div>
                <div class="col-12 col-lg-6 my-2">
                    <label for="tax_office" class="form-label">' . lang('Tax Office') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'tax_office', 'name' => 'tax_office',
                        'class' => 'form-control', 'maxlength' => '100', 'autocomplete' => 'off')) . '
                </div>
            </div>
        </div>
    </div>

    <div class="card my-4">
        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
            ' . lang('Contact Information') . '
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-12 col-lg-6 my-2">
                    <label for="email" class="form-label">' . lang('E-mail Address') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'email', 'name' => 'email',
                        'class' => 'form-control', 'maxlength' => '255', 'autocomplete' => 'off')) . '
                </div>
                <div class="col-12 col-lg-6 my-2">
                    <label for="phone" class="form-label">' . lang('Phone Number') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'phone', 'name' => 'phone',
                        'class' => 'form-control', 'maxlength' => '50', 'autocomplete' => 'off')) . '
                </div>
                <div class="col-12 my-2">
                    <label for="address" class="form-label">' . lang('Address') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'address', 'name' => 'address',
                        'class' => 'form-control', 'maxlength' => '255', 'autocomplete' => 'off')) . '
                </div>
                <div class="col-12 col-sm-5 my-2">
                    <label for="district" class="form-label">' . lang('District') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'district', 'name' => 'district',
                        'class' => 'form-control', 'maxlength' => '100', 'autocomplete' => 'off')) . '
                </div>
                <div class="col-12 col-sm-4 my-2">
                    <label for="city" class="form-label">' . lang('City') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'city', 'name' => 'city',
                        'class' => 'form-control', 'maxlength' => '100', 'autocomplete' => 'off')) . '
                </div>
                <div class="col-12 col-sm-3 my-2">
                    <label for="postcode" class="form-label">' . lang('Postal Code') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'postcode', 'name' => 'postcode',
                        'class' => 'form-control', 'maxlength' => '20', 'autocomplete' => 'off')) . '
                </div>
            </div>
        </div>
    </div>';

    if ($with_opening) {
        $output .= '
    <div class="card my-4">
        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
            ' . lang('Opening Balance') . '
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-12 col-sm-4 col-lg-3 my-2">
                    <label for="opening_amount" class="form-label">' . lang('Amount') . '</label>
                    <div class="input-group">
                        ' . $liveform->output_field(array(
                            'type' => 'text', 'id' => 'opening_amount', 'name' => 'opening_amount',
                            'class' => 'form-control text-end', 'maxlength' => '15',
                            'inputmode' => 'decimal', 'autocomplete' => 'off')) . '
                        <label class="input-group-text" for="opening_amount">' . BASE_CURRENCY_SYMBOL . '</label>
                    </div>
                    <div class="form-text">' . lang('Positive when they owe you, negative when you owe them.') . '</div>
                </div>
                <div class="col-12 col-sm-4 col-lg-3 my-2">
                    <label for="opening_date" class="form-label">' . lang('Date') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'opening_date', 'name' => 'opening_date',
                        'class' => 'form-control', 'size' => '10', 'maxlength' => '10',
                        'autocomplete' => 'off')) . '
                    ' . get_date_picker_format() . '
                    <script>$("#opening_date").datepicker(datetimepicker_options);</script>
                </div>
                <div class="col-12 col-lg-6 my-2">
                    <div class="form-text pt-4">' . lang('Recorded as a movement rather than written straight onto the balance, so it can be traced later like any other entry.') . '</div>
                </div>
            </div>
        </div>
    </div>';
    }

    $output .= '
    <div class="card my-4">
        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
            ' . lang('Notes') . '
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-12 my-2">
                    ' . $liveform->output_field(array(
                        'type' => 'textarea', 'id' => 'notes', 'name' => 'notes',
                        'class' => 'form-control', 'rows' => '3')) . '
                </div>
            </div>
        </div>
    </div>';

    return $output;
}
