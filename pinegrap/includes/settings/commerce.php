<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the Ecommerce cards: the store, shipping, gift cards, invoicing, payment and affiliates.
 *
 * Fills $pg_settings_cards, one grid column per card, in reading order. Which
 * cards belong here is said once, in includes/settings/registry.php; this file
 * holds them, and <key>.save.php writes what they edit. The variables they read
 * are prepared by includes/settings/prep.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

// ── Store ──
$pg_settings_cards[] = '
    <div id="pgset-store" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                ' . lang('Store') . '
            </div>
            <div class="card-body">
                <div class="row gy-3">
                                        <div class="col-12 ">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $ecommerce_checked . ' class="form-check-input" type="checkbox" id="ecommerce" name="ecommerce"/>
                                                <label class="form-check-label" for="ecommerce">' . lang('Enable Commerce') . '</label>
                                            </div>
                                        </div>
' . ($ecommerce_checked === ''
    ? '<div class="pg-f-xs"><div class="pg-f-xs alert alert-secondary mb-0 py-2 small">' . lang('The store is off. These settings are kept but nothing on the site uses them until Commerce is enabled.') . '</div></div>'
    : '') . '
                                                        <div id="pgsub-store" class="pg-f-xs">
                                                            <label for="ecommerce_next_order_number" class="form-label">' . lang('Next Order Number') . '</label>
                                                            <input type="number" name="ecommerce_next_order_number" id="ecommerce_next_order_number" class="form-control" value="' . $ecommerce_next_order_number . '" maxlength="20"/>
                                                        </div>
                                                        <div class="pg-f-lg">
                                                            <label for="ecommerce_email_address" class="form-label">' . lang('Commerce E-mail Address') . '</label>
                                                            <input type="text" name="ecommerce_email_address" id="ecommerce_email_address" class="form-control" value="' . h($ecommerce_email_address) . '" inputmode="email" data-inputmask-alias="email"/>
                                                        </div>
                                                        <div class="pg-f-lg">
                                                            <label for="product_upload_folder_id" class="form-label">' . lang('Folder for product images') . '</label>
                                                            <select name="product_upload_folder_id" id="product_upload_folder_id" class="form-select">' . select_folder($product_upload_folder_id, 0) . '</select>
                                                            <div class="form-text">' . lang('The product and product group screens open on this folder when an image is uploaded. It can still be changed while uploading.') . '</div>
                                                        </div>
                                                        <div class="col-12 ">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $ecommerce_multicurrency_checked . ' class="form-check-input" type="checkbox" id="ecommerce_multicurrency" name="ecommerce_multicurrency" onclick="show_or_hide_ecommerce_payment_gateway()"/>
                                                                <label class="form-check-label" for="ecommerce_multicurrency">' . lang('Multi-Currency') . '</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 ">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $ecommerce_show_product_images_checked . ' class="form-check-input" type="checkbox" id="ecommerce_show_product_images" name="ecommerce_show_product_images"/>
                                                                <label class="form-check-label" for="ecommerce_show_product_images">' . lang('Show Product Images in Tables') . '</label>
                                                            </div>
                                                        </div>

                                                        <!-- ── Barcode ── -->
                                                        <div class="col-12 ">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $barcode_enabled_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="barcode_enabled" name="barcode_enabled" data-bs-target="#barcode_settings_row"/>
                                                                <label class="form-check-label fw-semibold" for="barcode_enabled"><i class="bi bi-upc-scan me-1"></i>' . lang('Enable Barcode Feature') . '</label>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 " id="barcode_settings_row">
                                                                <div class="popover-arrow" style="position:absolute;left:0px;transform:translate(30px,0px);"></div>
                                                                <div class="popover-body">
                                                                    <div class="row g-2">
                                                                        <div class="pg-f-sm">
                                                                            <label class="form-label">' . lang('Default Barcode Type') . '</label>
                                                                            <select name="barcode_default_type" id="barcode_default_type" class="form-select form-select-sm">
                                                                                <option value="CODE128"' . ($barcode_default_type === 'CODE128' ? ' selected' : '') . '>Code 128</option>
                                                                                <option value="EAN13"'   . ($barcode_default_type === 'EAN13'   ? ' selected' : '') . '>EAN-13</option>
                                                                                <option value="CODE39"'  . ($barcode_default_type === 'CODE39'  ? ' selected' : '') . '>Code 39</option>
                                                                                <option value="UPC"'     . ($barcode_default_type === 'UPC'     ? ' selected' : '') . '>UPC-A</option>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <!-- ── /Barcode ── -->

                                                        <div class="col-12 ">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $ecommerce_tax_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_tax" name="ecommerce_tax" data-bs-target="#ecommerce_tax_row"/>
                                                                <label class="form-check-label" for="ecommerce_tax">' . lang('Tax') . '</label>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 " id="ecommerce_tax_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                <div class="popover-body">
                                                                   <div class="row gy-3">
                                                                        <div class="col-12 ">
                                                                            <div class="form-check form-switch">
                                                                                <input value="1"' . $ecommerce_tax_exempt_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_tax_exempt" name="ecommerce_tax_exempt" data-bs-target="#ecommerce_tax_exempt_row"/>
                                                                                <label class="form-check-label" for="ecommerce_tax_exempt">' . lang('Allow Tax-Exempt') . '</label>
                                                                            </div>
                                                                            <div class="collapse popover fade bs-popover-bottom p-0 " id="ecommerce_tax_exempt_row">
                                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(40px, 0px);"></div>
                                                                                <div class="popover-body">
                                                                                   <div class="row gy-3">
                                                                                        <div class="col-12 ">
                                                                                            <label for="ecommerce_tax_exempt_label" class="form-label">' . lang('Tax-Exempt Label') . '</label>
                                                                                            <input type="text" name="ecommerce_tax_exempt_label" id="ecommerce_tax_exempt_label" class="form-control" value="' . h($ecommerce_tax_exempt_label) . '" maxlength="255"/>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                </div>
            </div>
        </div>
    </div>';

// ── Shipping ──
$pg_settings_cards[] = '
    <div id="pgset-shipping" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                ' . lang('Shipping') . '
            </div>
            <div class="card-body">
                <div class="row gy-3">
                    <div class="col-12">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $ecommerce_shipping_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_shipping" name="ecommerce_shipping" data-bs-target="#ecommerce_shipping_row"/>
                                                                <label class="form-check-label" for="ecommerce_shipping">' . lang('Shipping') . '</label>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 " id="ecommerce_shipping_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                <div class="popover-body">
                                                                   <div class="row gy-3">
                                                                        <div class="col-12 ">
                                                                            <label class="form-label">'. lang('Recipient Mode') . '</label>
                                                                            <div class="form-check">
                                                                                <input value="single recipient" class="form-check-input" type="radio" id="ecommerce_recipient_mode_single_recipient" name="ecommerce_recipient_mode" ' . $ecommerce_recipient_mode_single_recipient . '>
                                                                                <label class="form-check-label" for="ecommerce_recipient_mode_single_recipient">'. lang('Single Recipient') . '</label>
                                                                            </div>
                                                                            <div class="form-check">
                                                                                <input value="multi-recipient" class="form-check-input" type="radio" id="ecommerce_recipient_mode_multirecipient" name="ecommerce_recipient_mode" ' . $ecommerce_recipient_mode_multirecipient . '>
                                                                                <label class="form-check-label" for="ecommerce_recipient_mode_multirecipient">'. lang('Multi-Recipient') . '</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="pg-f-md">
                                                                            <label for="usps_user_id" class="form-label">' . lang('USPS Web Tools User ID') . '</label>
                                                                            <input type="text" name="usps_user_id" id="usps_user_id" class="form-control" value="' . h($usps_user_id) . '" maxlength="100"/>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <div class="form-check form-switch">
                                                                                <input value="1"' . $ecommerce_address_verification_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_address_verification" name="ecommerce_address_verification" data-bs-target="#ecommerce_address_verification_row"/>
                                                                                <label class="form-check-label" for="ecommerce_address_verification">' . lang('Verify US Addresses') . '</label>
                                                                            </div>
                                                                            <div class="collapse popover fade bs-popover-bottom p-0 " id="ecommerce_address_verification_row">
                                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(40px, 0px);"></div>
                                                                                <div class="popover-body">
                                                                                   <div class="row gy-3">
                                                                                        <div class="alert alert-primary">' . lang('Requires an approved USPS Web Tools account') . '</div>
                                                                                        <div class="col-12 ">
                                                                                            <label class="form-label">'. lang('Enforcement') . '</label>
                                                                                            <div class="form-check">
                                                                                                <input value="warning" class="form-check-input" type="radio" id="ecommerce_address_verification_enforcement_type_warning" name="ecommerce_address_verification_enforcement_type" ' . $ecommerce_address_verification_enforcement_type_warning_checked . '>
                                                                                                <label class="form-check-label" for="ecommerce_address_verification_enforcement_type_warning">'. lang('Warning') . '</label>
                                                                                            </div>
                                                                                            <div class="form-check">
                                                                                                <input value="error" class="form-check-input" type="radio" id="ecommerce_address_verification_enforcement_type_error" name="ecommerce_address_verification_enforcement_type" ' . $ecommerce_address_verification_enforcement_type_error_checked . '>
                                                                                                <label class="form-check-label" for="ecommerce_address_verification_enforcement_type_error">'. lang('Error') . '</label>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <div class="form-check form-switch">
                                                                                <input value="1"' . $ups_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ups" name="ups" data-bs-target="#ups_row"/>
                                                                                <label class="form-check-label" for="ups">' . lang('UPS') . '</label>
                                                                            </div>
                                                                            <div class="collapse popover fade bs-popover-bottom p-0 " id="ups_row">
                                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                                <div class="popover-body">
                                                                                   <div class="row gy-3">
                                                                                        <div class="pg-f-md">
                                                                                            <label for="ups_key" class="form-label">' . lang('Access Key') . '</label>
                                                                                            <input type="text" name="ups_key" id="ups_key" class="form-control" value="' . h($ups_key) . '" maxlength="100"/>
                                                                                        </div>
                                                                                        <div class="pg-f-md">
                                                                                            <label for="ups_user_id" class="form-label">' . lang('User ID') . '</label>
                                                                                            <input type="text" name="ups_user_id" id="ups_user_id" class="form-control" value="' . h($ups_user_id) . '" maxlength="100"/>
                                                                                        </div>
                                                                                        <div class="pg-f-md">
                                                                                            <label for="ups_password" class="form-label">' . lang('Password') . '</label>
                                                                                            <input type="password" name="ups_password" id="ups_password" class="form-control" value="' . h($ups_password) . '" maxlength="100"/>
                                                                                        </div>
                                                                                        <div class="pg-f-md">
                                                                                            <label for="ups_account" class="form-label">' . lang('Account Number') . '</label>
                                                                                            <input type="text" name="ups_account" id="ups_account" class="form-control" value="' . h($ups_account) . '" maxlength="100"/>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <div class="form-check form-switch">
                                                                                <input value="1"' . $fedex_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="fedex" name="fedex" data-bs-target="#fedex_row"/>
                                                                                <label class="form-check-label" for="fedex">' . lang('FedEx') . '</label>
                                                                            </div>
                                                                            <div class="collapse popover fade bs-popover-bottom p-0 " id="fedex_row">
                                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                                <div class="popover-body">
                                                                                   <div class="row gy-3">
                                                                                        <div class="pg-f-md">
                                                                                            <label for="fedex_key" class="form-label">' . lang('Key') . '</label>
                                                                                            <input type="text" name="fedex_key" id="fedex_key" class="form-control" value="' . h($fedex_key) . '" maxlength="100"/>
                                                                                        </div>
                                                                                        <div class="pg-f-md">
                                                                                            <label for="fedex_password" class="form-label">' . lang('Password') . '</label>
                                                                                            <input type="password" name="fedex_password" id="fedex_password" class="form-control" value="' . h($fedex_password) . '" maxlength="100"/>
                                                                                        </div>
                                                                                        <div class="pg-f-md">
                                                                                            <label for="fedex_account" class="form-label">' . lang('Account Number') . '</label>
                                                                                            <input type="text" name="fedex_account" id="fedex_account" class="form-control" value="' . h($fedex_account) . '" maxlength="100"/>
                                                                                        </div>
                                                                                        <div class="pg-f-md">
                                                                                            <label for="fedex_meter" class="form-label">' . lang('Meter Number') . '</label>
                                                                                            <input type="text" name="fedex_meter" id="fedex_meter" class="form-control" value="' . h($fedex_meter) . '" maxlength="100"/>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <label for="ecommerce_product_restriction_message" class="form-label">' . lang('Product Restriction Message') . '</label>
                                                                            <input type="text" name="ecommerce_product_restriction_message" id="ecommerce_product_restriction_message" class="form-control" value="' . h($ecommerce_product_restriction_message) . '" maxlength="255"/>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <label for="ecommerce_no_shipping_methods_message" class="form-label">' . lang('No Shipping Methods Message') . '</label>
                                                                            <input type="text" name="ecommerce_no_shipping_methods_message" id="ecommerce_no_shipping_methods_message" class="form-control" value="' . h($ecommerce_no_shipping_methods_message) . '" maxlength="255"/>
                                                                        </div>
                                                                        ' . get_time_picker_format() . '
                                                                        <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/Jquery/jquery-ui-timepicker-addon-1.2.1.min.js"></script>
                                                                        <div class="pg-f-xs">
                                                                            <label for="ecommerce_end_of_day_time" class="form-label">' . lang('End of Day Time') . '</label>
                                                                            <input type="text" name="ecommerce_end_of_day_time" id="ecommerce_end_of_day_time" class="form-control timepicker" value="' . prepare_form_data_for_output($ecommerce_end_of_day_time, 'time') . '" maxlength="8"/>
                                                                            <div class="form-text">(h:mm AM/PM), ' . lang('Current Site Time') . ': ' . h(date('g:i A')) . '</div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                    </div>
                </div>
            </div>
        </div>
    </div>';

// ── Gift Cards & Rewards ──
$pg_settings_cards[] = '
    <div id="pgset-giftcards" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                ' . lang('Gift Cards & Rewards') . '
            </div>
            <div class="card-body">
                <div class="row gy-3">
                    <div class="col-12">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $ecommerce_gift_card_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_gift_card" name="ecommerce_gift_card" data-bs-target="#ecommerce_gift_card_row"/>
                                                                <label class="form-check-label" for="ecommerce_gift_card">' . lang('Accept Gift Cards') . '</label>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 " id="ecommerce_gift_card_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                <div class="popover-body">
                                                                   <div class="row gy-3">
                                                                        <div class="pg-f-xs">
                                                                            <label for="ecommerce_gift_card_validity_days" class="form-label">' . lang('Validity Length') . '</label>
                                                                            <div class="input-group">
                                                                                <input type="text" name="ecommerce_gift_card_validity_days" id="ecommerce_gift_card_validity_days" class="form-control" value="' . $ecommerce_gift_card_validity_days . '" size="7" maxlength="7" inputmode="numeric" data-inputmask-alias="decimal"  style="text-align: right;" />
                                                                                <span class="input-group-text">' . lang('day(s)') . '</span>
                                                                            </div>
                                                                            <div class="text-end form-text">' . lang('leave blank for no expiration') . '</div>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <div class="form-check form-switch">
                                                                                <input value="1"' . $ecommerce_givex_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_givex" name="ecommerce_givex" data-bs-target="#ecommerce_givex_row"/>
                                                                                <label class="form-check-label" for="ecommerce_givex">' . lang('Accept Givex Cards') . '</label>
                                                                            </div>
                                                                            <div class="collapse popover fade bs-popover-bottom p-0 " id="ecommerce_givex_row">
                                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                                <div class="popover-body">
                                                                                   <div class="row gy-3">
                                                                                        <div class="alert alert-primary">' . lang('Requires Givex service') . '</div>
                                                                                        <div class="pg-f-md">
                                                                                            <label for="ecommerce_givex_primary_hostname" class="form-label">' . lang('Primary Hostname') . '</label>
                                                                                            <input type="text" name="ecommerce_givex_primary_hostname" id="ecommerce_givex_primary_hostname" class="form-control" value="' . h($ecommerce_givex_primary_hostname) . '" maxlength="100"/>
                                                                                        </div>
                                                                                        <div class="pg-f-md">
                                                                                            <label for="ecommerce_givex_secondary_hostname" class="form-label">' . lang('Secondary Hostname') . '</label>
                                                                                            <input type="text" name="ecommerce_givex_secondary_hostname" id="ecommerce_givex_secondary_hostname" class="form-control" value="' . h($ecommerce_givex_secondary_hostname) . '" maxlength="100"/>
                                                                                        </div>
                                                                                        <div class="pg-f-md">
                                                                                            <label for="ecommerce_givex_user_id" class="form-label">' . lang('User ID') . '</label>
                                                                                            <input type="text" name="ecommerce_givex_user_id" id="ecommerce_givex_user_id" class="form-control" value="' . h($ecommerce_givex_user_id) . '" maxlength="100"/>
                                                                                        </div>
                                                                                        <div class="pg-f-md">
                                                                                            <label for="ecommerce_givex_password" class="form-label">' . lang('Password') . '</label>
                                                                                            <input type="text" name="ecommerce_givex_password" id="ecommerce_givex_password" class="form-control" value="' . h($ecommerce_givex_password) . '" maxlength="100"/>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 ">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $ecommerce_reward_program_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_reward_program" name="ecommerce_reward_program" data-bs-target="#ecommerce_reward_program_row"/>
                                                                <label class="form-check-label" for="ecommerce_reward_program">' . lang('Enable Reward Program') . '</label>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0  w-100" id="ecommerce_reward_program_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                <div class="popover-body">
                                                                   <div class="row gy-3">
                                                                        <div class="pg-f-xs">
                                                                            <label for="ecommerce_reward_program_points" class="form-label">' . lang('Goal') . '</label>
                                                                            <div class="input-group">
                                                                                <input value="' . $ecommerce_reward_program_points . '" type="text" name="ecommerce_reward_program_points" id="ecommerce_reward_program_points" class="form-control" size="6" maxlength="6" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                                                <label for="ecommerce_reward_program_points"  class="input-group-text">' . lang('point(s)') . '</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <div class="form-check form-switch">
                                                                                <input value="1"' . $ecommerce_reward_program_membership_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_reward_program_membership" name="ecommerce_reward_program_membership" data-bs-target="#ecommerce_reward_program_membership_row"/>
                                                                                <label class="form-check-label" for="ecommerce_reward_program_membership">' . lang('Grant Membership') . '</label>
                                                                            </div>
                                                                            <div class="collapse popover fade bs-popover-bottom p-0 " id="ecommerce_reward_program_membership_row">
                                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                                <div class="popover-body">
                                                                                   <div class="row gy-3">
                                                                                        <div class="pg-f-xs">
                                                                                            <label for="ecommerce_reward_program_membership_days" class="form-label">' . lang('Membership Length') . '</label>
                                                                                            <div class="input-group">
                                                                                                <input value="' . $ecommerce_reward_program_membership_days . '" type="text" name="ecommerce_reward_program_membership_days" id="ecommerce_reward_program_membership_days" class="form-control" size="5" maxlength="5" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                                                                <label for="ecommerce_reward_program_membership_days"  class="input-group-text">' . lang('day(s)') . '</label>
                                                                                            </div>
                                                                                            <div class="form-text text-end">(' . lang('leave blank for lifetime membership') . ')</div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <div class="form-check form-switch">
                                                                                <input value="1"' . $ecommerce_reward_program_email_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_reward_program_email" name="ecommerce_reward_program_email" data-bs-target="#ecommerce_reward_program_email_row"/>
                                                                                <label class="form-check-label" for="ecommerce_reward_program_email">' . lang('Send E-mail') . '</label>
                                                                            </div>
                                                                            <div class="collapse popover fade bs-popover-bottom p-0  w-100" id="ecommerce_reward_program_email_row">
                                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                                <div class="popover-body">
                                                                                   <div class="row gy-3">
                                                                                        <div class="pg-f-md">
                                                                                            <label class="form-label" for="ecommerce_reward_program_email_bcc_email_address">' . lang('BCC E-mail Address') . '</label>
                                                                                            <input type="text" value="' . h($ecommerce_reward_program_email_bcc_email_address) . '" class="form-control text-end" id="ecommerce_reward_program_email_bcc_email_address" name="ecommerce_reward_program_email_bcc_email_address" maxlength="100" inputmode="email" data-inputmask-alias="email"/>
                                                                                        </div>
                                                                                        <div class="pg-f-md">
                                                                                            <label class="form-label" for="ecommerce_reward_program_email_subject">' . lang('Subject') . '</label>
                                                                                            <input type="text" value="' . h($ecommerce_reward_program_email_subject) . '" id="ecommerce_reward_program_email_subject" name="ecommerce_reward_program_email_subject" class="form-control" maxlength="255" >
                                                                                        </div>
                                                                                        <div class="pg-f-md">
                                                                                            <label class="form-label" for="ecommerce_reward_program_email_page_id">' . lang('Page') . '</label>
                                                                                            <select class="form-select" id="ecommerce_reward_program_email_page_id" name="ecommerce_reward_program_email_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page')) )) . '-</option>' . select_page($ecommerce_reward_program_email_page_id) . '</select>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                    </div>
                </div>
            </div>
        </div>
    </div>';

// ── E-Invoice ──
$pg_settings_cards[] = '
    <div id="pgset-invoice" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                ' . lang('E-Invoice') . '
            </div>
            <div class="card-body">
                <div class="row gy-3">' . (($output_erp_edoc_options !== '') ? '
                    <div class="col-12">
                        <div class="form-label">' . lang('e-Document Provider') . ' <span class="text-body-secondary small">(' . lang('ERP') . ')</span></div>
                        <div class="form-text mb-2">' . lang('Where the ERP\'s invoices and delivery notes go to become e-Invoice, e-Archive or e-Delivery note documents. One provider at a time; the choice can be changed later and every document remembers which provider carried it.') . '</div>
                        ' . $output_erp_edoc_options . '
                        <button type="submit" class="btn btn-sm btn-outline-secondary" formaction="get_erp_edoc_test.php" formmethod="post" formtarget="_blank"><i class="bi bi-plug me-2"></i>' . lang('Test the connection') . '</button>
                        <div class="form-text">' . lang('Tries the provider picked above with what is typed here (or, for a box left empty, what is stored). Save the settings to keep the credentials.') . '</div>
                    </div>
                    <div class="col-12"><hr class="my-1"></div>' : '') . '
                    <div class="col-12">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $enable_parasut_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="enable_parasut" name="enable_parasut" data-bs-target="#ecommerce_parasut_configration_row"/>
                                                                <label class="form-check-label" for="enable_parasut">' . lang('Enable Parasut Configuration') . '</label>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0  w-100" id="ecommerce_parasut_configration_row">
                                                            <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                            <div class="popover-body">
                                                               <div class="row gy-3">
                                                                    <div class="col-12 "><h6 class="text-muted">' . lang('API Connection') . '</h6></div>
                                                                    <div class="pg-f-md">
                                                                        <label class="form-label" for="parasut_client_id">' . lang('Client ID') . '</label>
                                                                        <input type="text" class="form-control" id="parasut_client_id" name="parasut_client_id" value="' . h($parasut_client_id) . '" autocomplete="off" />
                                                                    </div>
                                                                    <div class="pg-f-md">
                                                                        <label class="form-label" for="parasut_client_secret">' . lang('Client Secret') . '</label>
                                                                        <input type="password" class="form-control" id="parasut_client_secret" name="parasut_client_secret" value="" autocomplete="new-password" placeholder="' . h($parasut_credential_placeholder) . '" />
                                                                    </div>
                                                                    <div class="pg-f-md">
                                                                        <label class="form-label" for="parasut_username">' . lang('Username') . ' (' . lang('Email') . ')</label>
                                                                        <input type="text" class="form-control" id="parasut_username" name="parasut_username" value="' . h($parasut_username) . '" autocomplete="off" />
                                                                    </div>
                                                                    <div class="pg-f-md">
                                                                        <label class="form-label" for="parasut_password">' . lang('Password') . '</label>
                                                                        <input type="password" class="form-control" id="parasut_password" name="parasut_password" value="" autocomplete="new-password" placeholder="' . h($parasut_credential_placeholder) . '" />
                                                                        <div class="form-text">' . h($parasut_credential_help) . '</div>
                                                                    </div>
                                                                    <div class="pg-f-md">
                                                                        <label class="form-label" for="parasut_company_id">' . lang('Company ID') . ' <span class="text-muted small">(' . lang(array('string' => 'Parasut field: {var:1}', 'vars' => array('Firma No'))) . ')</span></label>
                                                                        <input type="text" class="form-control" id="parasut_company_id" name="parasut_company_id" value="' . h($parasut_company_id) . '" autocomplete="off" />
                                                                        <div class="form-text">' . lang('You can find your Company ID in the Parasut URL: app.parasut.com/v4/{company_id}') . '</div>
                                                                    </div>
                                                                    <div class="col-12 "><h6 class="text-muted">' . lang('Invoice Settings') . '</h6></div>
                                                                    <div class="pg-f-md">
                                                                        <label class="form-label" for="parasut_tc_in_field">' . lang('Select field to used as Republic of Turkey identification number') . '</label>
                                                                        <select class="form-select" id="parasut_tc_in_field" name="parasut_tc_in_field">' . $parasut_tc_in_field_options . '</select>
                                                                    </div>
                                                                    <div class="pg-f-md">
                                                                        <label class="form-label" for="parasut_default_product_id">' . lang('Default Product/Service ID') . ' <span class="text-muted small">(' . lang(array('string' => 'Parasut field: {var:1}', 'vars' => array('Varsayılan Ürün/Hizmet'))) . ')</span></label>
                                                                        <input type="text" class="form-control" id="parasut_default_product_id" name="parasut_default_product_id" value="' . h($parasut_default_product_id) . '" autocomplete="off" />
                                                                        <div class="form-text">' . lang('Parasut product/service ID used for all invoice line items. Create a generic product in Parasut and enter its ID here.') . '</div>
                                                                    </div>
                                                                    <div class="pg-f-md">
                                                                        <label class="form-label" for="parasut_default_warehouse_id">' . lang('Default Warehouse ID') . ' <span class="text-muted small">(' . lang(array('string' => 'Parasut field: {var:1}', 'vars' => array('Varsayılan Depo'))) . ')</span></label>
                                                                        <input type="text" class="form-control" id="parasut_default_warehouse_id" name="parasut_default_warehouse_id" value="' . h($parasut_default_warehouse_id) . '" autocomplete="off" />
                                                                        <div class="form-text">' . lang('Fetched automatically from Parasut on first use. Override here if you have multiple warehouses.') . '</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            </div>
                    </div>
                </div>
            </div>
        </div>
    </div>';

// ── ERP ──
$pg_settings_cards[] = '
    <div id="pgset-erp" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                ' . lang('ERP') . '
            </div>
            <div class="card-body">
                <div class="row gy-3">
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input value="1"' . $erp_enabled_checked . ' class="form-check-input" type="checkbox" id="erp_enabled" name="erp_enabled" />
                            <label class="form-check-label" for="erp_enabled">' . lang('Enable the ERP module') . '</label>
                        </div>
                        <div class="form-text">' . lang('Adds accounts, cash and bank, invoices and delivery notes to the panel. Switching it off hides the screens; nothing that was entered is deleted.') . '</div>
                    </div>
                    <div class="pg-f-md">
                        <label class="form-label" for="erp_default_series">' . lang('Invoice series') . '</label>
                        <input type="text" class="form-control" id="erp_default_series" name="erp_default_series" value="' . h($erp_default_series) . '" maxlength="10" autocomplete="off" />
                        <div class="form-text">' . lang('Three letters is the usual shape, for example PGF. Numbering restarts each year.') . '</div>
                    </div>
                    <div class="pg-f-md">
                        <label class="form-label" for="erp_web_address">' . lang('Address the sale was made at') . '</label>
                        <input type="text" class="form-control" id="erp_web_address" name="erp_web_address" value="' . h($erp_web_address) . '" maxlength="255" autocomplete="off" />
                        <div class="form-text">' . lang('Printed on invoices for internet sales, which have to name it. For example www.example.com.') . '</div>
                    </div>
                    <div class="pg-f-md">
                        <label class="form-label" for="erp_seller_vkn">' . lang('Seller VKN / TCKN') . '</label>
                        <input type="text" class="form-control" id="erp_seller_vkn" name="erp_seller_vkn" value="' . h($erp_seller_vkn) . '" maxlength="11" inputmode="numeric" autocomplete="off" />
                        <div class="form-text">' . lang('Tax number (10 digits) or ID number (11 digits) of the company that issues the invoices') . '</div>
                    </div>
                    <div class="pg-f-md">
                        <label class="form-label" for="erp_seller_tax_office">' . lang('Seller Tax Office') . '</label>
                        <input type="text" class="form-control" id="erp_seller_tax_office" name="erp_seller_tax_office" value="' . h($erp_seller_tax_office) . '" maxlength="100" autocomplete="off" />
                    </div>
                    <div class="pg-f-md">
                        <label class="form-label" for="erp_default_due_days">' . lang('Default payment term (days)') . '</label>
                        <input type="number" class="form-control" id="erp_default_due_days" name="erp_default_due_days" value="' . (int) $erp_default_due_days . '" min="0" max="3650" step="1" inputmode="numeric" autocomplete="off" />
                        <div class="form-text">' . lang('A new invoice falls due this many days after its date, unless the account carries its own term. 0 means due on the invoice date.') . '</div>
                    </div>
                    <div class="pg-f-md">
                        <label class="form-label" for="erp_walkin_account_id">' . lang('Walk-in sales account') . '</label>
                        <select class="form-select" id="erp_walkin_account_id" name="erp_walkin_account_id">' . $erp_walkin_options . '</select>
                        <div class="form-text">' . lang('A local sale made without picking a customer is billed to this account when it is invoiced. Open an account named for the purpose, such as "Retail customer".') . '</div>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input value="1"' . $erp_fx_enabled_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="erp_fx_enabled" name="erp_fx_enabled" data-bs-target="#erp_fx_row" />
                            <label class="form-check-label" for="erp_fx_enabled">' . lang('Enable foreign-currency invoices and accounts') . '</label>
                        </div>
                        <div class="form-text">' . lang('Off, everything in the ERP is in the store\'s base currency. On, an invoice, an account or a till may be kept in one of the currencies below; amounts stay in that currency and the day\'s exchange rate converts them for the ledger.') . '</div>
                        <div class="collapse popover fade bs-popover-bottom p-0 " id="erp_fx_row">
                            <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                            <div class="popover-body">
                                <div class="row gy-3">
                                    <div class="col-12">
                                        <div class="form-label">' . lang('Currencies allowed on documents') . '</div>
                                        ' . $output_erp_fx_currencies . '
                                        <div class="form-text">' . lang('The base currency is always allowed. Daily rates for these come from the Exchange rates job; run Update Exchange Rates on the currencies screen to fetch today\'s.') . '</div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check form-switch">
                                            <input value="1"' . $erp_fx_auto_diff_checked . ' class="form-check-input" type="checkbox" id="erp_fx_auto_diff" name="erp_fx_auto_diff" />
                                            <label class="form-check-label" for="erp_fx_auto_diff">' . lang('Post the exchange difference automatically when a foreign-currency invoice is paid off') . '</label>
                                        </div>
                                        <div class="form-text">' . lang('The invoice went on the account at the rate of its issue date and the receipts came off at the rate of their own day. When the invoice is paid, one movement in the base currency settles the gap so the account closes to zero.') . '</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-label">' . lang('Overdue receivable reminders') . '</div>
                        <div class="form-text mb-2">' . lang('Sales invoices that pass this many days overdue are announced in the panel bell, by e-mail and on a subscribed device, and once more if still open a month later. An account can carry a threshold of its own. 0 turns the reminders off.') . '</div>
                        <div class="row gy-3">
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="erp_overdue_notify_days">' . lang('Reminder threshold (days)') . '</label>
                                <input type="number" class="form-control" id="erp_overdue_notify_days" name="erp_overdue_notify_days" value="' . (int) $erp_overdue_notify_days . '" min="0" max="3650" step="1" inputmode="numeric" autocomplete="off" />
                            </div>
                            <div class="col-6 col-md-4">
                                <label class="form-label" for="erp_overdue_notify_frequency">' . lang('Frequency') . '</label>
                                <select class="form-select" id="erp_overdue_notify_frequency" name="erp_overdue_notify_frequency">
                                    <option value="daily"' . (($erp_overdue_notify_frequency === 'daily') ? ' selected="selected"' : '') . '>' . lang('Daily') . '</option>
                                    <option value="weekly"' . (($erp_overdue_notify_frequency === 'weekly') ? ' selected="selected"' : '') . '>' . lang('Weekly') . '</option>
                                </select>
                            </div>
                            <div class="col-6 col-md-4">
                                <label class="form-label" for="erp_overdue_notify_hour">' . lang('Hour') . '</label>
                                <select class="form-select" id="erp_overdue_notify_hour" name="erp_overdue_notify_hour">' . $output_erp_overdue_hours . '</select>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input value="1"' . $erp_overdue_notify_panel_checked . ' class="form-check-input" type="checkbox" id="erp_overdue_notify_panel" name="erp_overdue_notify_panel" />
                                    <label class="form-check-label" for="erp_overdue_notify_panel">' . lang('Panel notification') . '</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input value="1"' . $erp_overdue_notify_email_checked . ' class="form-check-input" type="checkbox" id="erp_overdue_notify_email" name="erp_overdue_notify_email" />
                                    <label class="form-check-label" for="erp_overdue_notify_email">' . lang('E-mail') . '</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input value="1"' . $erp_overdue_notify_push_checked . ' class="form-check-input" type="checkbox" id="erp_overdue_notify_push" name="erp_overdue_notify_push" />
                                    <label class="form-check-label" for="erp_overdue_notify_push">' . lang('Device notification') . '</label>
                                </div>' . $output_erp_overdue_push_hint . '
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input value="1"' . $erp_overdue_notify_customer_checked . ' class="form-check-input" type="checkbox" id="erp_overdue_notify_customer" name="erp_overdue_notify_customer" />
                                    <label class="form-check-label" for="erp_overdue_notify_customer">' . lang('Reminder e-mail to the customer') . '</label>
                                </div>
                                <div class="form-text">' . lang('When a customer\'s invoices pass the threshold, a short reminder listing them goes to the e-mail address on their account, from the store address, with a second one a month later. Off for a single account on its card.') . '</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="erp_overdue_notify_recipients">' . lang('Recipients') . '</label>
                                <input type="text" class="form-control" id="erp_overdue_notify_recipients" name="erp_overdue_notify_recipients" value="' . h($erp_overdue_notify_recipients) . '" maxlength="500" autocomplete="off" placeholder="' . h($erp_overdue_notify_default_recipient) . '" />
                                <div class="form-text">' . lang('Comma-separated. Empty uses the store e-mail address.') . '</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>';

// ── Payment Methods ──
$pg_settings_cards[] = '
    <div id="pgset-payments" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                ' . lang('Payment Methods') . '
            </div>
            <div class="card-body">
                <div class="row gy-3">
                    <div class="col-12">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $ecommerce_credit_debit_card_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_credit_debit_card" name="ecommerce_credit_debit_card" data-bs-target="#ecommerce_credit_debit_card_row"/>
                                                                <label class="form-check-label" for="ecommerce_credit_debit_card">' . lang('Credit/Debit Card') . '</label>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 " id="ecommerce_credit_debit_card_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                <div class="popover-body">
                                                                   <div class="row gy-3">
                                                                        <div class="col-12 ">
                                                                            <label class="form-label">'. lang('Accepted Cards') . '</label>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <div class="form-check form-switch">
                                                                                <input value="1"' . $ecommerce_american_express_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_american_express" name="ecommerce_american_express"/>
                                                                                <label class="form-check-label" for="ecommerce_american_express">' . lang('American Express') . '</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <div class="form-check form-switch">
                                                                                <input value="1"' . $ecommerce_diners_club_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_diners_club" name="ecommerce_diners_club"/>
                                                                                <label class="form-check-label" for="ecommerce_diners_club">' . lang('Diners Club') . '</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <div class="form-check form-switch">
                                                                                <input value="1"' . $ecommerce_discover_card_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_discover_card" name="ecommerce_discover_card"/>
                                                                                <label class="form-check-label" for="ecommerce_discover_card">' . lang('Discover Card') . '</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <div class="form-check form-switch">
                                                                                <input value="1"' . $ecommerce_mastercard_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_mastercard" name="ecommerce_mastercard"/>
                                                                                <label class="form-check-label" for="ecommerce_mastercard">' . lang('MasterCard') . '</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <div class="form-check form-switch">
                                                                                <input value="1"' . $ecommerce_visa_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_visa" name="ecommerce_visa"/>
                                                                                <label class="form-check-label" for="ecommerce_visa">' . lang('Visa') . '</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <div class="form-check form-switch">
                                                                                <input value="1"' . $ecommerce_troy_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_troy" name="ecommerce_troy"/>
                                                                                <label class="form-check-label" for="ecommerce_troy">' . lang('Troy') . '</label>
                                                                            </div>
                                                                        </div>

                                                                        <div class="col-12 ">
                                                                           <div class="row gy-3">
                                                                                <div class="pg-f-sm">
                                                                                    <label class="form-label" for="ecommerce_payment_gateway">' . lang('Payment Gateway') . '</label>
                                                                                    <select class="form-select collapse-if-selected " name="ecommerce_payment_gateway" id="ecommerce_payment_gateway" onchange="show_or_hide_ecommerce_payment_gateway()" data-bs-target="#ecommerce_payment_gateway_row">
                                                                                        <option value="">-' . lang('None') . '-</option>
                                                                                        <option value="Authorize.Net"' . $ecommerce_payment_gateway_authorizenet . '>Authorize.Net</option>
                                                                                        <option value="ClearCommerce"' . $ecommerce_payment_gateway_clearcommerce . '>ClearCommerce/PayFuse</option>
                                                                                        <option value="First Data Global Gateway"' . $ecommerce_payment_gateway_first_data_global_gateway . '>First Data Global Gateway</option>
                                                                                        <option value="PayPal Payflow Pro"' . $ecommerce_payment_gateway_paypal_payflow_pro . '>PayPal Payflow Pro</option>
                                                                                        <option value="PayPal Payments Pro"' . $ecommerce_payment_gateway_paypal_payments_pro . '>PayPal Payments Pro</option>
                                                                                        <option value="Sage"' . $ecommerce_payment_gateway_sage . '>Sage</option>
                                                                                        <option value="Stripe"' . $ecommerce_payment_gateway_stripe . '>Stripe</option>
                                                                                        <option value="Iyzipay"' . $ecommerce_payment_gateway_iyzipay . '>Iyzipay</option>
                                                                                    </select>
                                                                                </div>
                                                                            </div>
                                                                            <div class="collapse popover fade bs-popover-bottom p-0  " id="ecommerce_payment_gateway_row">
                                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                                                <div class="popover-body">
                                                                                   <div class="row gy-3">
                                                                                        <div class="col-12 " id="ecommerce_payment_gateway_transaction_type_row" style="' . $ecommerce_payment_gateway_transaction_type_row_style . '">
                                                                                            <label class="form-label">'. lang('Transaction Type') . '</label>
                                                                                            <div class="form-check">
                                                                                                <input value="Authorize" class="form-check-input" type="radio" id="ecommerce_payment_gateway_transaction_type_authorize" name="ecommerce_payment_gateway_transaction_type" ' . $ecommerce_payment_gateway_transaction_type_authorize . '>
                                                                                                <label class="form-check-label" for="ecommerce_payment_gateway_transaction_type_authorize">'. lang('Authorize') . '</label>
                                                                                            </div>
                                                                                            <div class="form-check">
                                                                                                <input value="Authorize &amp; Capture" class="form-check-input" type="radio" id="ecommerce_payment_gateway_transaction_type_authorize_and_capture" name="ecommerce_payment_gateway_transaction_type" ' . $ecommerce_payment_gateway_transaction_type_authorize_and_capture . '>
                                                                                                <label class="form-check-label" for="ecommerce_payment_gateway_transaction_type_authorize_and_capture">'. lang('Authorize & Capture') . '</label>
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="col-12 " id="ecommerce_payment_gateway_mode_row" style="' . $ecommerce_payment_gateway_mode_row_style . '">
                                                                                            <label class="form-label">'. lang('Mode') . '</label>
                                                                                            <div class="form-check">
                                                                                                <input value="test" class="form-check-input" type="radio" id="ecommerce_payment_gateway_mode_test" name="ecommerce_payment_gateway_mode" ' . $ecommerce_payment_gateway_mode_test . '>
                                                                                                <label class="form-check-label" for="ecommerce_payment_gateway_mode_test">'. lang('Test') . '</label>
                                                                                            </div>
                                                                                            <div class="form-check">
                                                                                                <input value="live" class="form-check-input" type="radio" id="ecommerce_payment_gateway_mode_live" name="ecommerce_payment_gateway_mode" ' . $ecommerce_payment_gateway_mode_live . '>
                                                                                                <label class="form-check-label" for="ecommerce_payment_gateway_mode_live">'. lang('Live') . '</label>
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="col-12 " id="ecommerce_paypal_payments_pro_gateway_mode_row" style="' . $ecommerce_paypal_payments_pro_gateway_mode_row_style . '">
                                                                                            <label class="form-label">'. lang('Mode') . '</label>
                                                                                            <div class="form-check">
                                                                                                <input value="test" class="form-check-input" type="radio" id="ecommerce_paypal_payments_pro_gateway_mode_test" name="ecommerce_paypal_payments_pro_gateway_mode" ' . $ecommerce_payment_gateway_mode_test . '>
                                                                                                <label class="form-check-label" for="ecommerce_paypal_payments_pro_gateway_mode_test">'. lang('Sandbox') . '</label>
                                                                                            </div>
                                                                                            <div class="form-check">
                                                                                                <input value="live" class="form-check-input" type="radio" id="ecommerce_paypal_payments_pro_gateway_mode_live" name="ecommerce_paypal_payments_pro_gateway_mode" ' . $ecommerce_payment_gateway_mode_live . '>
                                                                                                <label class="form-check-label" for="ecommerce_paypal_payments_pro_gateway_mode_live">'. lang('Live') . '</label>
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="pg-f-lg" id="ecommerce_authorizenet_api_login_id_row" style="' . $ecommerce_authorizenet_api_login_id_row_style . '">
                                                                                            <label class="form-label" for="ecommerce_authorizenet_api_login_id">'. lang('API Login ID') . '</label>
                                                                                            <input class="form-control" type="text" id="ecommerce_authorizenet_api_login_id" name="ecommerce_authorizenet_api_login_id" value="' . h($ecommerce_authorizenet_api_login_id) . '" size="40" maxlength="100" />
                                                                                        </div>
                                                                                        <div class="pg-f-lg" id="ecommerce_authorizenet_transaction_key_row" style="' . $ecommerce_authorizenet_transaction_key_row_style . '">
                                                                                            <label class="form-label" for="ecommerce_authorizenet_transaction_key">'. lang('Transaction Key') . '</label>
                                                                                            <input class="form-control" type="password" id="ecommerce_authorizenet_transaction_key" name="ecommerce_authorizenet_transaction_key" value="' . h($ecommerce_authorizenet_transaction_key) . '" size="40" maxlength="100" />
                                                                                        </div>

                                                                                        <div class="pg-f-lg" id="ecommerce_clearcommerce_client_id_row" style="' . $ecommerce_clearcommerce_client_id_row_style . '">
                                                                                            <label class="form-label" for="ecommerce_clearcommerce_client_id">'. lang('Client ID') . '</label>
                                                                                            <input class="form-control" type="text" id="ecommerce_clearcommerce_client_id" name="ecommerce_clearcommerce_client_id" value="' . h($ecommerce_clearcommerce_client_id) . '" size="40" maxlength="100" />
                                                                                        </div>
                                                                                        <div class="pg-f-lg" id="ecommerce_clearcommerce_user_id_row" style="' . $ecommerce_clearcommerce_user_id_row_style . '">
                                                                                            <label class="form-label" for="ecommerce_clearcommerce_user_id">'. lang('User ID') . '</label>
                                                                                            <input class="form-control" type="text" id="ecommerce_clearcommerce_user_id" name="ecommerce_clearcommerce_user_id" value="' . h($ecommerce_clearcommerce_user_id) . '" size="40" maxlength="100" />
                                                                                        </div>
                                                                                        <div class="pg-f-lg" id="ecommerce_clearcommerce_password_row" style="' . $ecommerce_clearcommerce_password_row_style . '">
                                                                                            <label class="form-label" for="ecommerce_clearcommerce_password">'. lang('Password') . '</label>
                                                                                            <input class="form-control" type="password" id="ecommerce_clearcommerce_password" name="ecommerce_clearcommerce_password" value="' . h($ecommerce_clearcommerce_password) . '" size="40" maxlength="100" />
                                                                                        </div>

                                                                                        <div class="pg-f-lg" id="ecommerce_first_data_global_gateway_store_number_row" style="' . $ecommerce_first_data_global_gateway_store_number_row_style . '">
                                                                                            <label class="form-label" for="ecommerce_first_data_global_gateway_store_number">'. lang('Store Number') . '</label>
                                                                                            <input class="form-control" type="text" id="ecommerce_first_data_global_gateway_store_number" name="ecommerce_first_data_global_gateway_store_number" value="' . h($ecommerce_first_data_global_gateway_store_number) . '" size="40" maxlength="100" />
                                                                                        </div>
                                                                                        <div class="pg-f-lg" id="ecommerce_first_data_global_gateway_pem_file_name_row" style="' . $ecommerce_first_data_global_gateway_pem_file_name_row_style . '">
                                                                                            <label class="form-label" for="ecommerce_first_data_global_gateway_pem_file_name">'. lang('PEM File') . '</label>
                                                                                            <select class="form-select" name="ecommerce_first_data_global_gateway_pem_file_name" id="ecommerce_first_data_global_gateway_pem_file_name"><option value="">-' . lang('None') . '-</option>' . $ecommerce_first_data_global_gateway_pem_file_name_options . '</select>
                                                                                        </div>
                                                                                    
                                                                                        <div class="pg-f-lg" id="ecommerce_paypal_payflow_pro_partner_row" style="' . $ecommerce_paypal_payflow_pro_partner_row_style . '">
                                                                                            <label class="form-label" for="ecommerce_paypal_payflow_pro_partner">'. lang('Partner') . '</label>
                                                                                            <input class="form-control" type="text" id="ecommerce_paypal_payflow_pro_partner" name="ecommerce_paypal_payflow_pro_partner" value="' . h($ecommerce_paypal_payflow_pro_partner) . '" size="40" maxlength="100" />
                                                                                        </div>
                                                                                        <div class="pg-f-lg" id="ecommerce_paypal_payflow_pro_merchant_login_row" style="' . $ecommerce_paypal_payflow_pro_merchant_login_row_style . '">
                                                                                            <label class="form-label" for="ecommerce_paypal_payflow_pro_merchant_login">'. lang('Merchant Login') . '</label>
                                                                                            <input class="form-control" type="text" id="ecommerce_paypal_payflow_pro_merchant_login" name="ecommerce_paypal_payflow_pro_merchant_login" value="' . h($ecommerce_paypal_payflow_pro_merchant_login) . '" size="40" maxlength="100" />
                                                                                        </div>
                                                                                        <div class="pg-f-lg" id="ecommerce_paypal_payflow_pro_user_row" style="' . $ecommerce_paypal_payflow_pro_user_row_style . '">
                                                                                            <label class="form-label" for="ecommerce_paypal_payflow_pro_user">'. lang('User') . '</label>
                                                                                            <input class="form-control" type="text" id="ecommerce_paypal_payflow_pro_user" name="ecommerce_paypal_payflow_pro_user" value="' . h($ecommerce_paypal_payflow_pro_user) . '" size="40" maxlength="100" />
                                                                                        </div>
                                                                                        <div class="pg-f-lg" id="ecommerce_paypal_payflow_pro_password_row" style="' . $ecommerce_paypal_payflow_pro_password_row_style . '">
                                                                                            <label class="form-label" for="ecommerce_paypal_payflow_pro_password">'. lang('Password') . '</label>
                                                                                            <input class="form-control" type="password" id="ecommerce_paypal_payflow_pro_password" name="ecommerce_paypal_payflow_pro_password" value="' . h($ecommerce_paypal_payflow_pro_password) . '" size="40" maxlength="100" />
                                                                                        </div>

                                                                                        <div class="pg-f-lg" id="ecommerce_paypal_payments_pro_api_username_row" style="' . $ecommerce_paypal_payments_pro_api_username_row_style . '">
                                                                                            <label class="form-label" for="ecommerce_paypal_payments_pro_api_username">'. lang('API Username') . '</label>
                                                                                            <input class="form-control" type="text" id="ecommerce_paypal_payments_pro_api_username" name="ecommerce_paypal_payments_pro_api_username" value="' . h($ecommerce_paypal_payments_pro_api_username) . '" size="40" maxlength="100" />
                                                                                        </div>
                                                                                        <div class="pg-f-lg" id="ecommerce_paypal_payments_pro_api_password_row" style="' . $ecommerce_paypal_payments_pro_api_password_row_style . '">
                                                                                            <label class="form-label" for="ecommerce_paypal_payments_pro_api_password">'. lang('API Password') . '</label>
                                                                                            <input class="form-control" type="password" id="ecommerce_paypal_payments_pro_api_password" name="ecommerce_paypal_payments_pro_api_password" value="' . h($ecommerce_paypal_payments_pro_api_password) . '" size="40" maxlength="100" />
                                                                                        </div>
                                                                                        <div class="pg-f-lg" id="ecommerce_paypal_payments_pro_api_signature_row" style="' . $ecommerce_paypal_payments_pro_api_signature_row_style . '">
                                                                                            <label class="form-label" for="ecommerce_paypal_payments_pro_api_signature">'. lang('API Signature') . '</label>
                                                                                            <input class="form-control" type="password" id="ecommerce_paypal_payments_pro_api_signature" name="ecommerce_paypal_payments_pro_api_signature" value="' . h($ecommerce_paypal_payments_pro_api_signature) . '" size="40" maxlength="100" />
                                                                                        </div>

                                                                                        <div class="pg-f-lg" id="ecommerce_sage_merchant_id_row" style="' . $ecommerce_sage_merchant_id_row_style . '">
                                                                                            <label class="form-label" for="ecommerce_sage_merchant_id">'. lang('Merchant ID') . '</label>
                                                                                            <input class="form-control" type="text" id="ecommerce_sage_merchant_id" name="ecommerce_sage_merchant_id" value="' . h($ecommerce_sage_merchant_id) . '" size="40" maxlength="100" />
                                                                                        </div>
                                                                                        <div class="pg-f-lg" id="ecommerce_sage_merchant_key_row" style="' . $ecommerce_sage_merchant_key_row_style . '">
                                                                                            <label class="form-label" for="ecommerce_sage_merchant_key">'. lang('Merchant Key') . '</label>
                                                                                            <input class="form-control" type="password" id="ecommerce_sage_merchant_key" name="ecommerce_sage_merchant_key" value="' . h($ecommerce_sage_merchant_key) . '" size="40" maxlength="100" />
                                                                                        </div>
                                                                                       
                                                                                        <div class="pg-f-lg" id="ecommerce_stripe_api_key_row" style="' . $ecommerce_stripe_api_key_row_style . '">
                                                                                            <label class="form-label" for="ecommerce_stripe_api_key">'. lang('API Key') . '</label>
                                                                                            <input class="form-control" type="password" id="ecommerce_stripe_api_key" name="ecommerce_stripe_api_key" value="' . h($ecommerce_stripe_api_key) . '" size="40" maxlength="100" />
                                                                                            <div class="form-text">' . lang('Enter the Test or Live Secret Key') . '</div>
                                                                                        </div>
                                                                                       
                                                                                        <div class="pg-f-lg" id="ecommerce_iyzipay_api_key_row" style="' . $ecommerce_iyzipay_api_key_row_style . '">
                                                                                            <label class="form-label" for="ecommerce_iyzipay_api_key">'. lang('API Key') . '</label>
                                                                                            <input class="form-control" type="password" id="ecommerce_iyzipay_api_key" name="ecommerce_iyzipay_api_key" value="' . h($ecommerce_iyzipay_api_key) . '" size="40" maxlength="100" />
                                                                                        </div>
                                                                                        <div class="pg-f-lg" id="ecommerce_iyzipay_secret_key_row" style="' . $ecommerce_iyzipay_secret_key_row_style . '">
                                                                                            <label class="form-label" for="ecommerce_iyzipay_secret_key">'. lang('Secret Key') . '</label>
                                                                                            <input class="form-control" type="password" id="ecommerce_iyzipay_secret_key" name="ecommerce_iyzipay_secret_key" value="' . h($ecommerce_iyzipay_secret_key) . '" size="40" maxlength="100" />
                                                                                        </div>
                                                                                        <div class="pg-f-lg" id="ecommerce_iyzipay_installment_row" style="' . $ecommerce_iyzipay_installment_row_style . '">
                                                                                            <label class="form-label" for="ecommerce_iyzipay_secret_key">'. lang('Installment') . '</label>
                                                                                            <select class="form-select" id="ecommerce_iyzipay_installment" name="ecommerce_iyzipay_installment">'.$ecommerce_iyzipay_installment_options.'</select>
                                                                                        </div>
                                                                                        <div class="pg-f-xs" id="ecommerce_surcharge_percentage_row" style="' . $ecommerce_surcharge_percentage_row_style . '">
                                                                                            <label for="ecommerce_surcharge_percentage" class="form-label">' . lang('Surcharge') . '</label>
                                                                                            <div class="input-group">
                                                                                                <input value="' . $ecommerce_surcharge_percentage . '" type="text" name="ecommerce_surcharge_percentage" id="ecommerce_surcharge_percentage" class="form-control" size="7" maxlength="7" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                                                                <label for="affiliate_default_commission_rate"  class="input-group-text">%</label>
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="col-12 " id="ecommerce_iyzipay_threeds_row" style="' . $ecommerce_iyzipay_3ds_row_style . '">
                                                                                            <div class="form-check form-switch">
                                                                                                <input value="1"' . $ecommerce_iyzipay_threeds_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_iyzipay_threeds" name="ecommerce_iyzipay_threeds"/>
                                                                                                <label class="form-check-label" for="ecommerce_iyzipay_threeds">' . lang('3D Secure') . '</label>
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="col-12 ">
                                                                                            <div class="form-check form-switch">
                                                                                                <input value="1" ' . $ecommerce_pay_with_iyzico_checked . ' class="form-check-input" type="checkbox" id="ecommerce_pay_with_iyzico" name="ecommerce_pay_with_iyzico"/>
                                                                                                <label class="form-check-label" for="ecommerce_pay_with_iyzico">' . lang('Enable Pay with Iyzico') . '</label>
                                                                                            </div>
                                                                                        </div>

                                                                                        <div class="col-12" id="ecommerce_iyzipay_protected_currency_row" style="' . $ecommerce_iyzipay_protected_currency_row_style . '">
                                                                                            <div class="form-check form-switch ">
                                                                                                <input value="1"' . $enable_iyzipay_protected_currency_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="enable_iyzipay_protected_currency" name="enable_iyzipay_protected_currency" data-bs-target="#enable_iyzipay_protected_currency_row"/>
                                                                                                <label class="form-check-label" for="enable_iyzipay_protected_currency">' . lang('Enable Iyzipay Protected Currency') . '</label>
                                                                                            </div>
                                                                                            <div class="collapse popover fade bs-popover-bottom p-0  w-100" id="enable_iyzipay_protected_currency_row">
                                                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                                                <div class="popover-body">
                                                                                                   <div class="row gy-3">
                                                                                                        <div class="pg-f-md">
                                                                                                            <label class="form-label" for="iyzipay_protected_currency_code">' . lang('Currency to be converted in submiting order for currency protection') . '</label>
                                                                                                            ' . $output_iyzipay_protected_currency_select . '
                                                                                                        </div>
                                                                                                    </div>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>

                                                                                        <div class="col-12 " id="ecommerce_reset_encryption_key_row" style="' . $ecommerce_reset_encryption_key_row_style . '">
                                                                                            <div class="form-check form-switch">
                                                                                                <input value="1"' . $ecommerce_reset_encryption_key_disabled . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_reset_encryption_key" name="ecommerce_reset_encryption_key"/>
                                                                                                <label class="form-check-label" for="ecommerce_reset_encryption_key">' . lang('Reset Encryption Key') . '</label>
                                                                                                <div class="form-text">' . $ecommerce_reset_encryption_key_disabled_message . '</div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 ">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $ecommerce_paypal_express_checkout_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_paypal_express_checkout" name="ecommerce_paypal_express_checkout" data-bs-target="#ecommerce_paypal_express_checkout_row"/>
                                                                <label class="form-check-label" for="ecommerce_paypal_express_checkout">' . lang('PayPal Express Checkout') . '</label>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 " id="ecommerce_paypal_express_checkout_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                <div class="popover-body">
                                                                   <div class="row gy-3">
                                                                        <div class="col-12 ">
                                                                            <label class="form-label">'. lang('Transaction Type') . '</label>
                                                                            <div class="form-check">
                                                                                <input value="Authorize" class="form-check-input" type="radio" id="ecommerce_paypal_express_checkout_transaction_type_authorize" name="ecommerce_paypal_express_checkout_transaction_type" ' . $ecommerce_paypal_express_checkout_transaction_type_authorize . '>
                                                                                <label class="form-check-label" for="ecommerce_paypal_express_checkout_transaction_type_authorize">'. lang('Authorize') . '</label>
                                                                            </div>
                                                                            <div class="form-check">
                                                                                <input value="Authorize &amp; Capture" class="form-check-input" type="radio" id="ecommerce_paypal_express_checkout_transaction_type_authorize_and_capture" name="ecommerce_paypal_express_checkout_transaction_type" ' . $ecommerce_paypal_express_checkout_transaction_type_authorize_and_capture . '>
                                                                                <label class="form-check-label" for="ecommerce_paypal_express_checkout_transaction_type_authorize_and_capture">'. lang('Authorize & Capture') . '</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <label class="form-label">'. lang('Mode') . '</label>
                                                                            <div class="form-check">
                                                                                <input value="sandbox" class="form-check-input" type="radio" id="ecommerce_paypal_express_checkout_mode_sandbox" name="ecommerce_paypal_express_checkout_mode" ' . $ecommerce_paypal_express_checkout_mode_sandbox . '>
                                                                                <label class="form-check-label" for="ecommerce_paypal_express_checkout_mode_sandbox">'. lang('Sandbox') . '</label>
                                                                            </div>
                                                                            <div class="form-check">
                                                                                <input value="live" class="form-check-input" type="radio" id="ecommerce_paypal_express_checkout_mode_live" name="ecommerce_paypal_express_checkout_mode" ' . $ecommerce_paypal_express_checkout_mode_live . '>
                                                                                <label class="form-check-label" for="ecommerce_paypal_express_checkout_mode_live">'. lang('Live') . '</label>
                                                                            </div>
                                                                        </div>

                                                                        <div class="pg-f-lg">
                                                                            <label class="form-label" for="ecommerce_paypal_express_checkout_api_username">'. lang('API Username') . '</label>
                                                                            <input class="form-control" type="text" id="ecommerce_paypal_express_checkout_api_username" name="ecommerce_paypal_express_checkout_api_username" value="' . h($ecommerce_paypal_express_checkout_api_username) . '" size="40" maxlength="100" />
                                                                        </div>
                                                                        <div class="pg-f-lg">
                                                                            <label class="form-label" for="ecommerce_paypal_express_checkout_api_password">'. lang('API Password') . '</label>
                                                                            <input class="form-control" type="password" id="ecommerce_paypal_express_checkout_api_password" name="ecommerce_paypal_express_checkout_api_password" value="' . h($ecommerce_paypal_express_checkout_api_password) . '" size="40" maxlength="100" />
                                                                        </div>
                                                                        <div class="pg-f-lg">
                                                                            <label class="form-label" for="ecommerce_paypal_express_checkout_api_signature">'. lang('API Signature') . '</label>
                                                                            <input class="form-control" type="password" id="ecommerce_paypal_express_checkout_api_signature" name="ecommerce_paypal_express_checkout_api_signature" value="' . h($ecommerce_paypal_express_checkout_api_signature) . '" size="40" maxlength="100" />
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 ">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $ecommerce_offline_payment_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_offline_payment" name="ecommerce_offline_payment" data-bs-target="#ecommerce_offline_payment_row"/>
                                                                <label class="form-check-label" for="ecommerce_offline_payment">' . lang('Allow Offline Payments') . '</label>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 " id="ecommerce_offline_payment_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                <div class="popover-body">
                                                                   <div class="row gy-3">
                                                                        <div class="col-12 ">
                                                                            <div class="form-check form-switch">
                                                                                <input value="1"' . $ecommerce_offline_payment_only_specific_orders_checked . ' class="form-check-input" type="checkbox" id="ecommerce_offline_payment_only_specific_orders" name="ecommerce_offline_payment_only_specific_orders"/>
                                                                                <label class="form-check-label" for="ecommerce_offline_payment_only_specific_orders">' . lang('Only on specific orders') . '</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <label class="form-label" for="ecommerce_offline_payment_cancel_days">' . lang('Cancel unpaid bank transfer orders after') . '</label>
                                                                            <input type="number" min="0" max="255" step="1" class="form-control" id="ecommerce_offline_payment_cancel_days" name="ecommerce_offline_payment_cancel_days" value="' . (int) $ecommerce_offline_payment_cancel_days . '"/>
                                                                            <div class="form-text">' . lang('days; 0 keeps them open until you cancel by hand. Cancelled orders keep the reason on the order screen.') . '</div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div> 
                                                        </div>
                                                        <div class="pg-f-lg">
                                                            <label class="form-label" for="ecommerce_private_folder_id">'. lang('Grant Private Access') . '</label>
                                                            <select class="form-select" id="ecommerce_private_folder_id" name="ecommerce_private_folder_id">
                                                                <option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Folder')) )) . '-</option>
                                                                ' . select_folder($ecommerce_private_folder_id, 0, 0, 0, array(), array(), 'private') . '
                                                            </select>
                                                        </div>
                                                        <div class="pg-f-lg">
                                                            <label class="form-label" for="ecommerce_retrieve_order_next_page_id">'. lang('Reorder/Retrieve Order Next Page') . '</label>
                                                            <select class="form-select" id="ecommerce_retrieve_order_next_page_id" name="ecommerce_retrieve_order_next_page_id">
                                                                <option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page')) )) . '-</option>
                                                                ' . select_page($ecommerce_retrieve_order_next_page_id) . '
                                                            </select>
                                                        </div>
                                                        <div class="pg-f-md">
                                                            <label class="form-label" for="ecommerce_custom_product_field_1_label">'. lang(array('string'=>'Custom Product Field {var:1} Label','vars'=>array('#1') )) . '</label>
                                                            <input class="form-control" type="text" id="ecommerce_custom_product_field_1_label" name="ecommerce_custom_product_field_1_label" value="' . h($ecommerce_custom_product_field_1_label) . '" />
                                                        </div>
                                                        <div class="pg-f-md">
                                                            <label class="form-label" for="ecommerce_custom_product_field_2_label">'. lang(array('string'=>'Custom Product Field {var:1} Label','vars'=>array('#2') )) . '</label>
                                                            <input class="form-control" type="text" id="ecommerce_custom_product_field_2_label" name="ecommerce_custom_product_field_2_label" value="' . h($ecommerce_custom_product_field_2_label) . '" />
                                                        </div>
                                                        <div class="pg-f-md">
                                                            <label class="form-label" for="ecommerce_custom_product_field_3_label">'. lang(array('string'=>'Custom Product Field {var:1} Label','vars'=>array('#3') )) . '</label>
                                                            <input class="form-control" type="text" id="ecommerce_custom_product_field_3_label" name="ecommerce_custom_product_field_3_label" value="' . h($ecommerce_custom_product_field_3_label) . '" />
                                                        </div>
                                                        <div class="pg-f-md">
                                                            <label class="form-label" for="ecommerce_custom_product_field_4_label">'. lang(array('string'=>'Custom Product Field {var:1} Label','vars'=>array('#4') )) . '</label>
                                                            <input class="form-control" type="text" id="ecommerce_custom_product_field_4_label" name="ecommerce_custom_product_field_4_label" value="' . h($ecommerce_custom_product_field_4_label) . '" />
                    </div>
                </div>
            </div>
        </div>
    </div>';

// ── Affiliate Program ──
$pg_settings_cards[] = '
                        <div id="pgset-affiliate" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Affiliate Program') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-3">
                                        <div class="col-12 ">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $affiliate_program_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="affiliate_program" name="affiliate_program" data-bs-target="#affiliate_program_row"/>
                                                <label class="form-check-label" for="affiliate_program">' . lang('Enable Affiliate Program') . '</label>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0 " id="affiliate_program_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(50px, 0px);"></div>
                                                <div class="popover-body">
                                                   <div class="row gy-3">
                                                        <div class="col-12 ">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $affiliate_automatic_approval_checked . ' class="form-check-input" type="checkbox" id="affiliate_automatic_approval" name="affiliate_automatic_approval"/>
                                                                <label class="form-check-label" for="affiliate_automatic_approval">' . lang('Automatically Approve Affiliates') . '</label>
                                                            </div>
                                                        </div>
                                                        <div class="pg-f-xs">
                                                            <label for="affiliate_default_commission_rate" class="form-label">' . lang('Default Commission Rate') . '</label>
                                                            <div class="input-group">
                                                                <input value="' . $affiliate_default_commission_rate . '" type="text" name="affiliate_default_commission_rate" id="affiliate_default_commission_rate" class="form-control" size="6" maxlength="6" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                                <label for="affiliate_default_commission_rate"  class="input-group-text">%</label>
                                                            </div>
                                                        </div>
                                                        <div class="pg-f-md">
                                                            <label class="form-label" for="affiliate_contact_group_id">'. lang('Affiliate Contact Group') . '</label>
                                                            <select class="form-select" id="affiliate_contact_group_id" name="affiliate_contact_group_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Contact Group')) )) . '-</option>' . select_contact_group($affiliate_contact_group_id, $user) . '</select>
                                                        </div>
                                                        <div class="pg-f-sm">
                                                            <label for="affiliate_email_address" class="form-label">' . lang('Support E-mail Address') . '</label>
                                                            <input type="text" name="affiliate_email_address" id="affiliate_email_address" class="form-control" value="' . h($affiliate_email_address) . '" inputmode="email" data-inputmask-alias="email"/>
                                                        </div>
                                                        <div class="pg-f-md">
                                                            <label class="form-label" for="affiliate_group_offer_id">'. lang('Group Offer') . '</label>
                                                            <select class="form-select" id="affiliate_group_offer_id" name="affiliate_group_offer_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Offer')) )) . '-</option>' . select_offer($affiliate_group_offer_id) . '</select>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                   </div>
                                </div>
                            </div>
                        </div>';
