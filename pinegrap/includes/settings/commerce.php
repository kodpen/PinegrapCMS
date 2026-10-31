<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the Ecommerce cards: the store, shipping, gift cards, payment and affiliates.
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
            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                ' . lang('Store') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">
                                        <div class="col-12 ">
                                            <div class="form-field">
                                                <div class="switch"><input value="1"' . $ecommerce_checked . ' class="" type="checkbox" id="ecommerce" name="ecommerce"/></div>
                                                <label class="" for="ecommerce">' . lang('Enable Commerce') . '</label>
                                            </div>
                                        </div>
' . ($ecommerce_checked === ''
    ? '<div class="pg-f-xs"><div class="pg-f-xs alert theme-secondary mb-0 py-3 small">' . lang('The store is off. These settings are kept but nothing on the site uses them until Commerce is enabled.') . '</div></div>'
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
                                                            <select name="product_upload_folder_id" id="product_upload_folder_id" class="form-control">' . select_folder($product_upload_folder_id, 0) . '</select>
                                                            <div class="form-text">' . lang('The product and product group screens open on this folder when an image is uploaded. It can still be changed while uploading.') . '</div>
                                                        </div>
                                                        <div class="col-12 ">
                                                            <div class="form-field">
                                                                <div class="switch"><input value="1"' . $ecommerce_multicurrency_checked . ' class="" type="checkbox" id="ecommerce_multicurrency" name="ecommerce_multicurrency" onclick="show_or_hide_ecommerce_payment_gateway()"/></div>
                                                                <label class="" for="ecommerce_multicurrency">' . lang('Multi-Currency') . '</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 ">
                                                            <div class="form-field">
                                                                <div class="switch"><input value="1"' . $ecommerce_show_product_images_checked . ' class="" type="checkbox" id="ecommerce_show_product_images" name="ecommerce_show_product_images"/></div>
                                                                <label class="" for="ecommerce_show_product_images">' . lang('Show Product Images in Tables') . '</label>
                                                            </div>
                                                        </div>
' . ($review_photos_ready ? '
                                                        <div class="col-12 ">
                                                            <div class="form-field">
                                                                <div class="switch"><input value="1"' . $review_photos_checked . ' class="" type="checkbox" id="review_photos" name="review_photos" aria-describedby="review_photos_hint"/></div>
                                                                <label class="" for="review_photos">' . lang('Photo Reviews') . '</label>
                                                                <div class="form-text" id="review_photos_hint">' . lang('A customer may add one image (JPG, PNG, GIF or WebP) to a product review. Other files are never taken on a review, whatever the page allows.') . '</div>
                                                            </div>
                                                        </div>
' : '') . '

                                                        <!-- ── Barcode ── -->
                                                        <div class="col-12 ">
                                                            <div class="form-field">
                                                                <div class="switch"><input value="1"' . $barcode_enabled_checked . ' class="collapse-switcher" type="checkbox" id="barcode_enabled" name="barcode_enabled" data-bs-target="#barcode_settings_row"/></div>
                                                                <label class="fw-semibold" for="barcode_enabled"><i class="bi bi-upc-scan me-1"></i>' . lang('Enable Barcode Feature') . '</label>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 " id="barcode_settings_row">
                                                                <div class="popover-arrow" style="position:absolute;left:0px;transform:translate(30px,0px);"></div>
                                                                <div class="popover-body">
                                                                    <div class="row g-3">
                                                                        <div class="pg-f-sm">
                                                                            <label class="form-label" for="barcode_default_type">' . lang('Default Barcode Type') . '</label>
                                                                            <select name="barcode_default_type" id="barcode_default_type" class="form-control form-control-sm">
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
                                                            <div class="form-field">
                                                                <div class="switch"><input value="1"' . $ecommerce_tax_checked . ' class="collapse-switcher" type="checkbox" id="ecommerce_tax" name="ecommerce_tax" data-bs-target="#ecommerce_tax_row"/></div>
                                                                <label class="" for="ecommerce_tax">' . lang('Tax') . '</label>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 " id="ecommerce_tax_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                <div class="popover-body">
                                                                   <div class="row gy-5">
                                                                        <div class="col-12 ">
                                                                            <div class="form-field">
                                                                                <div class="switch"><input value="1"' . $ecommerce_tax_exempt_checked . ' class="collapse-switcher" type="checkbox" id="ecommerce_tax_exempt" name="ecommerce_tax_exempt" data-bs-target="#ecommerce_tax_exempt_row"/></div>
                                                                                <label class="" for="ecommerce_tax_exempt">' . lang('Allow Tax-Exempt') . '</label>
                                                                            </div>
                                                                            <div class="collapse popover fade bs-popover-bottom p-0 " id="ecommerce_tax_exempt_row">
                                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(40px, 0px);"></div>
                                                                                <div class="popover-body">
                                                                                   <div class="row gy-5">
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
            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                ' . lang('Shipping') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">
                    <div class="col-12">
                                                            <div class="form-field">
                                                                <div class="switch"><input value="1"' . $ecommerce_shipping_checked . ' class="collapse-switcher" type="checkbox" id="ecommerce_shipping" name="ecommerce_shipping" data-bs-target="#ecommerce_shipping_row"/></div>
                                                                <label class="" for="ecommerce_shipping">' . lang('Shipping') . '</label>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 " id="ecommerce_shipping_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                <div class="popover-body">
                                                                   <div class="row gy-5">
                                                                        <div class="col-12 ">
                                                                            <fieldset><legend class="form-label fs-md">'. lang('Recipient Mode') . '</legend>
                                                                            <div class="form-field">
                                                                                <input value="single recipient" class="radio" type="radio" id="ecommerce_recipient_mode_single_recipient" name="ecommerce_recipient_mode" ' . $ecommerce_recipient_mode_single_recipient . '>
                                                                                <label class="" for="ecommerce_recipient_mode_single_recipient">'. lang('Single Recipient') . '</label>
                                                                            </div>
                                                                            <div class="form-field">
                                                                                <input value="multi-recipient" class="radio" type="radio" id="ecommerce_recipient_mode_multirecipient" name="ecommerce_recipient_mode" ' . $ecommerce_recipient_mode_multirecipient . '>
                                                                                <label class="" for="ecommerce_recipient_mode_multirecipient">'. lang('Multi-Recipient') . '</label>
                                                                            </div>
                                                                            </fieldset>
                                                                        </div>
                                                                        <div class="col-12 "><h6 class="fg-secondary mb-0">' . lang('USPS') . '</h6></div>
                                                                        ' . pg_carrier_api_settings_fields('usps', $usps_legacy_set) . '
                                                                        ' . pg_carrier_api_test_button('usps') . '
                                                                        <div class="col-12 ">
                                                                            <div class="form-field">
                                                                                <div class="switch"><input value="1"' . $ecommerce_address_verification_checked . ' class="collapse-switcher" type="checkbox" id="ecommerce_address_verification" name="ecommerce_address_verification" data-bs-target="#ecommerce_address_verification_row"/></div>
                                                                                <label class="" for="ecommerce_address_verification">' . lang('Verify US Addresses') . '</label>
                                                                            </div>
                                                                            <div class="collapse popover fade bs-popover-bottom p-0 " id="ecommerce_address_verification_row">
                                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(40px, 0px);"></div>
                                                                                <div class="popover-body">
                                                                                   <div class="row gy-5">
                                                                                        <div class="alert theme-primary">' . lang('Uses the USPS Addresses API with the consumer key and secret entered above.') . '</div>
                                                                                        <div class="col-12 ">
                                                                                            <fieldset><legend class="form-label fs-md">'. lang('Enforcement') . '</legend>
                                                                                            <div class="form-field">
                                                                                                <input value="warning" class="radio" type="radio" id="ecommerce_address_verification_enforcement_type_warning" name="ecommerce_address_verification_enforcement_type" ' . $ecommerce_address_verification_enforcement_type_warning_checked . '>
                                                                                                <label class="" for="ecommerce_address_verification_enforcement_type_warning">'. lang('Warning') . '</label>
                                                                                            </div>
                                                                                            <div class="form-field">
                                                                                                <input value="error" class="radio" type="radio" id="ecommerce_address_verification_enforcement_type_error" name="ecommerce_address_verification_enforcement_type" ' . $ecommerce_address_verification_enforcement_type_error_checked . '>
                                                                                                <label class="" for="ecommerce_address_verification_enforcement_type_error">'. lang('Error') . '</label>
                                                                                            </div>
                                                                                            </fieldset>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <div class="form-field">
                                                                                <div class="switch"><input value="1"' . $ups_checked . ' class="collapse-switcher" type="checkbox" id="ups" name="ups" data-bs-target="#ups_row"/></div>
                                                                                <label class="" for="ups">' . lang('UPS') . '</label>
                                                                            </div>
                                                                            <div class="collapse popover fade bs-popover-bottom p-0 " id="ups_row">
                                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                                <div class="popover-body">
                                                                                   <div class="row gy-5">
                                                                                        ' . pg_carrier_api_settings_fields('ups', $ups_legacy_set) . '
                                                                                        <div class="pg-f-md">
                                                                                            <label for="ups_account" class="form-label">' . lang('Account Number') . '</label>
                                                                                            <input type="text" name="ups_account" id="ups_account" class="form-control" value="' . h($ups_account) . '" maxlength="100"/>
                                                                                            <div class="form-text">' . lang('For negotiated rates. Leave empty for published rates.') . '</div>
                                                                                        </div>
                                                                                        ' . pg_carrier_api_test_button('ups') . '
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <div class="form-field">
                                                                                <div class="switch"><input value="1"' . $fedex_checked . ' class="collapse-switcher" type="checkbox" id="fedex" name="fedex" data-bs-target="#fedex_row"/></div>
                                                                                <label class="" for="fedex">' . lang('FedEx') . '</label>
                                                                            </div>
                                                                            <div class="collapse popover fade bs-popover-bottom p-0 " id="fedex_row">
                                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                                <div class="popover-body">
                                                                                   <div class="row gy-5">
                                                                                        ' . pg_carrier_api_settings_fields('fedex', $fedex_legacy_set) . '
                                                                                        <div class="pg-f-md">
                                                                                            <label for="fedex_account" class="form-label">' . lang('Account Number') . '</label>
                                                                                            <input type="text" name="fedex_account" id="fedex_account" class="form-control" value="' . h($fedex_account) . '" maxlength="100"/>
                                                                                        </div>
                                                                                        ' . pg_carrier_api_test_button('fedex') . '
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        ' . pg_label_settings_rows() . '
                                                                        <div class="col-12 ">
                                                                            <label for="ecommerce_product_restriction_message" class="form-label">' . lang('Product Restriction Message') . '</label>
                                                                            <input type="text" name="ecommerce_product_restriction_message" id="ecommerce_product_restriction_message" class="form-control" value="' . h($ecommerce_product_restriction_message) . '" maxlength="255"/>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <label for="ecommerce_no_shipping_methods_message" class="form-label">' . lang('No Shipping Methods Message') . '</label>
                                                                            <input type="text" name="ecommerce_no_shipping_methods_message" id="ecommerce_no_shipping_methods_message" class="form-control" value="' . h($ecommerce_no_shipping_methods_message) . '" maxlength="255"/>
                                                                        </div>
                                                                        ' . get_time_picker_format() . '
                                                                        <div class="pg-f-xs">
                                                                            <label for="ecommerce_end_of_day_time" class="form-label">' . lang('End of Day Time') . '</label>
                                                                            <input type="text" name="ecommerce_end_of_day_time" id="ecommerce_end_of_day_time" class="form-control" data-pg-time value="' . prepare_form_data_for_output($ecommerce_end_of_day_time, 'time') . '" maxlength="8"/>' . get_date_picker_format() . pg_fields_script() . '
                                                                            <div class="form-text">(' . h(pg_time_format_help()) . '), ' . lang('Current Site Time') . ': ' . h(date(pg_time_format_code())) . '</div>
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
            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                ' . lang('Gift Cards & Rewards') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">
                    <div class="col-12">
                                                            <div class="form-field">
                                                                <div class="switch"><input value="1"' . $ecommerce_gift_card_checked . ' class="collapse-switcher" type="checkbox" id="ecommerce_gift_card" name="ecommerce_gift_card" data-bs-target="#ecommerce_gift_card_row"/></div>
                                                                <label class="" for="ecommerce_gift_card">' . lang('Accept Gift Cards') . '</label>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 " id="ecommerce_gift_card_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                <div class="popover-body">
                                                                   <div class="row gy-5">
                                                                        <div class="pg-f-xs">
                                                                            <label for="ecommerce_gift_card_validity_days" class="form-label">' . lang('Validity Length') . '</label>
                                                                            <label class="form-control form-adorn form-adorn-end">
                                                                                <span class="form-adorn-text" aria-hidden="true">' . lang('day(s)') . '</span>
                                                                                <input type="text" name="ecommerce_gift_card_validity_days" id="ecommerce_gift_card_validity_days" class="form-ghost" value="' . $ecommerce_gift_card_validity_days . '" size="7" maxlength="7" inputmode="numeric" data-inputmask-alias="decimal"  style="text-align: right;" />
                                                                            </label>
                                                                            <div class="text-end form-text">' . lang('leave blank for no expiration') . '</div>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <div class="form-field">
                                                                                <div class="switch"><input value="1"' . $ecommerce_givex_checked . ' class="collapse-switcher" type="checkbox" id="ecommerce_givex" name="ecommerce_givex" data-bs-target="#ecommerce_givex_row"/></div>
                                                                                <label class="" for="ecommerce_givex">' . lang('Accept Givex Cards') . '</label>
                                                                            </div>
                                                                            <div class="collapse popover fade bs-popover-bottom p-0 " id="ecommerce_givex_row">
                                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                                <div class="popover-body">
                                                                                   <div class="row gy-5">
                                                                                        <div class="alert theme-primary">' . lang('Requires Givex service') . '</div>
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
                                                            <div class="form-field">
                                                                <div class="switch"><input value="1"' . $ecommerce_reward_program_checked . ' class="collapse-switcher" type="checkbox" id="ecommerce_reward_program" name="ecommerce_reward_program" data-bs-target="#ecommerce_reward_program_row"/></div>
                                                                <label class="" for="ecommerce_reward_program">' . lang('Enable Reward Program') . '</label>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0  w-100" id="ecommerce_reward_program_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                <div class="popover-body">
                                                                   <div class="row gy-5">
                                                                        <div class="pg-f-xs">
                                                                            <label for="ecommerce_reward_program_points" class="form-label">' . lang('Goal') . '</label>
                                                                            <label class="form-control form-adorn form-adorn-end">
                                                                                <span class="form-adorn-text" aria-hidden="true">' . lang('point(s)') . '</span>
                                                                                <input value="' . $ecommerce_reward_program_points . '" type="text" name="ecommerce_reward_program_points" id="ecommerce_reward_program_points" class="form-ghost" size="6" maxlength="6" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                                            </label>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <div class="form-field">
                                                                                <div class="switch"><input value="1"' . $ecommerce_reward_program_membership_checked . ' class="collapse-switcher" type="checkbox" id="ecommerce_reward_program_membership" name="ecommerce_reward_program_membership" data-bs-target="#ecommerce_reward_program_membership_row"/></div>
                                                                                <label class="" for="ecommerce_reward_program_membership">' . lang('Grant Membership') . '</label>
                                                                            </div>
                                                                            <div class="collapse popover fade bs-popover-bottom p-0 " id="ecommerce_reward_program_membership_row">
                                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                                <div class="popover-body">
                                                                                   <div class="row gy-5">
                                                                                        <div class="pg-f-xs">
                                                                                            <label for="ecommerce_reward_program_membership_days" class="form-label">' . lang('Membership Length') . '</label>
                                                                                            <label class="form-control form-adorn form-adorn-end">
                                                                                                <span class="form-adorn-text" aria-hidden="true">' . lang('day(s)') . '</span>
                                                                                                <input value="' . $ecommerce_reward_program_membership_days . '" type="text" name="ecommerce_reward_program_membership_days" id="ecommerce_reward_program_membership_days" class="form-ghost" size="5" maxlength="5" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                                                            </label>
                                                                                            <div class="form-text text-end">(' . lang('leave blank for lifetime membership') . ')</div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <div class="form-field">
                                                                                <div class="switch"><input value="1"' . $ecommerce_reward_program_email_checked . ' class="collapse-switcher" type="checkbox" id="ecommerce_reward_program_email" name="ecommerce_reward_program_email" data-bs-target="#ecommerce_reward_program_email_row"/></div>
                                                                                <label class="" for="ecommerce_reward_program_email">' . lang('Send E-mail') . '</label>
                                                                            </div>
                                                                            <div class="collapse popover fade bs-popover-bottom p-0  w-100" id="ecommerce_reward_program_email_row">
                                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                                <div class="popover-body">
                                                                                   <div class="row gy-5">
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
                                                                                            <select class="form-control" id="ecommerce_reward_program_email_page_id" name="ecommerce_reward_program_email_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page')) )) . '-</option>' . select_page($ecommerce_reward_program_email_page_id) . '</select>
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

// ── Payment Methods ──
$pg_settings_cards[] = '
    <div id="pgset-payments" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                ' . lang('Payment Methods') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">
                    <div class="col-12">
                                                            <div class="form-field">
                                                                <div class="switch"><input value="1"' . $ecommerce_credit_debit_card_checked . ' class="collapse-switcher" type="checkbox" id="ecommerce_credit_debit_card" name="ecommerce_credit_debit_card" data-bs-target="#ecommerce_credit_debit_card_row"/></div>
                                                                <label class="" for="ecommerce_credit_debit_card">' . lang('Credit/Debit Card') . '</label>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 " id="ecommerce_credit_debit_card_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                <div class="popover-body">
                                                                   <div class="row gy-5">
                                                                        <div class="col-12 ">
                                                                            <label class="form-label">'. lang('Accepted Cards') . '</label>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <div class="form-field">
                                                                                <div class="switch"><input value="1"' . $ecommerce_american_express_checked . ' class="collapse-switcher" type="checkbox" id="ecommerce_american_express" name="ecommerce_american_express"/></div>
                                                                                <label class="" for="ecommerce_american_express">' . lang('American Express') . '</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <div class="form-field">
                                                                                <div class="switch"><input value="1"' . $ecommerce_diners_club_checked . ' class="collapse-switcher" type="checkbox" id="ecommerce_diners_club" name="ecommerce_diners_club"/></div>
                                                                                <label class="" for="ecommerce_diners_club">' . lang('Diners Club') . '</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <div class="form-field">
                                                                                <div class="switch"><input value="1"' . $ecommerce_discover_card_checked . ' class="collapse-switcher" type="checkbox" id="ecommerce_discover_card" name="ecommerce_discover_card"/></div>
                                                                                <label class="" for="ecommerce_discover_card">' . lang('Discover Card') . '</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <div class="form-field">
                                                                                <div class="switch"><input value="1"' . $ecommerce_mastercard_checked . ' class="collapse-switcher" type="checkbox" id="ecommerce_mastercard" name="ecommerce_mastercard"/></div>
                                                                                <label class="" for="ecommerce_mastercard">' . lang('MasterCard') . '</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <div class="form-field">
                                                                                <div class="switch"><input value="1"' . $ecommerce_visa_checked . ' class="collapse-switcher" type="checkbox" id="ecommerce_visa" name="ecommerce_visa"/></div>
                                                                                <label class="" for="ecommerce_visa">' . lang('Visa') . '</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <div class="form-field">
                                                                                <div class="switch"><input value="1"' . $ecommerce_troy_checked . ' class="collapse-switcher" type="checkbox" id="ecommerce_troy" name="ecommerce_troy"/></div>
                                                                                <label class="" for="ecommerce_troy">' . lang('Troy') . '</label>
                                                                            </div>
                                                                        </div>

                                                                        <div class="col-12 ">
                                                                           <div class="row gy-5">
                                                                                <div class="pg-f-sm">
                                                                                    <label class="form-label" for="ecommerce_payment_gateway">' . lang('Payment Gateway') . '</label>
                                                                                    <select class="form-control collapse-if-selected " name="ecommerce_payment_gateway" id="ecommerce_payment_gateway" onchange="show_or_hide_ecommerce_payment_gateway()" data-bs-target="#ecommerce_payment_gateway_row">
                                                                                        <option value="">-' . lang('None') . '-</option>
                                                                                        <option value="Authorize.Net"' . $ecommerce_payment_gateway_authorizenet . '>Authorize.Net</option>
                                                                                        <option value="ClearCommerce"' . $ecommerce_payment_gateway_clearcommerce . '>ClearCommerce/PayFuse</option>
                                                                                        <option value="First Data Global Gateway"' . $ecommerce_payment_gateway_first_data_global_gateway . '>First Data Global Gateway</option>
                                                                                        <option value="PayPal Payflow Pro"' . $ecommerce_payment_gateway_paypal_payflow_pro . '>PayPal Payflow Pro</option>
                                                                                        <option value="PayPal Payments Pro"' . $ecommerce_payment_gateway_paypal_payments_pro . '>PayPal Payments Pro</option>
                                                                                        <option value="Sage"' . $ecommerce_payment_gateway_sage . '>Sage</option>
                                                                                        <option value="Stripe"' . $ecommerce_payment_gateway_stripe . '>' . h(lang('Stripe (classic)')) . '</option>
                                                                                        <option value="Iyzipay"' . $ecommerce_payment_gateway_iyzipay . '>Iyzipay</option>' . pg_payment_settings_gateway_options($ecommerce_payment_gateway) . '
                                                                                    </select>
                                                                                </div>
                                                                            </div>
                                                                            <div class="collapse popover fade bs-popover-bottom p-0  " id="ecommerce_payment_gateway_row">
                                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                                                <div class="popover-body">
                                                                                   <div class="row gy-5">
                                                                                        <div class="col-12 " id="ecommerce_payment_gateway_transaction_type_row" style="' . $ecommerce_payment_gateway_transaction_type_row_style . '">
                                                                                            <fieldset><legend class="form-label fs-md">'. lang('Transaction Type') . '</legend>
                                                                                            <div class="form-field">
                                                                                                <input value="Authorize" class="radio" type="radio" id="ecommerce_payment_gateway_transaction_type_authorize" name="ecommerce_payment_gateway_transaction_type" ' . $ecommerce_payment_gateway_transaction_type_authorize . '>
                                                                                                <label class="" for="ecommerce_payment_gateway_transaction_type_authorize">'. lang('Authorize') . '</label>
                                                                                            </div>
                                                                                            <div class="form-field">
                                                                                                <input value="Authorize &amp; Capture" class="radio" type="radio" id="ecommerce_payment_gateway_transaction_type_authorize_and_capture" name="ecommerce_payment_gateway_transaction_type" ' . $ecommerce_payment_gateway_transaction_type_authorize_and_capture . '>
                                                                                                <label class="" for="ecommerce_payment_gateway_transaction_type_authorize_and_capture">'. lang('Authorize & Capture') . '</label>
                                                                                            </div>
                                                                                            </fieldset>
                                                                                        </div>
                                                                                        <div class="col-12 " id="ecommerce_payment_gateway_mode_row" style="' . $ecommerce_payment_gateway_mode_row_style . '">
                                                                                            <fieldset><legend class="form-label fs-md">'. lang('Mode') . '</legend>
                                                                                            <div class="form-field">
                                                                                                <input value="test" class="radio" type="radio" id="ecommerce_payment_gateway_mode_test" name="ecommerce_payment_gateway_mode" ' . $ecommerce_payment_gateway_mode_test . '>
                                                                                                <label class="" for="ecommerce_payment_gateway_mode_test">'. lang('Test') . '</label>
                                                                                            </div>
                                                                                            <div class="form-field">
                                                                                                <input value="live" class="radio" type="radio" id="ecommerce_payment_gateway_mode_live" name="ecommerce_payment_gateway_mode" ' . $ecommerce_payment_gateway_mode_live . '>
                                                                                                <label class="" for="ecommerce_payment_gateway_mode_live">'. lang('Live') . '</label>
                                                                                            </div>
                                                                                            </fieldset>
                                                                                        </div>
                                                                                        <div class="col-12 " id="ecommerce_paypal_payments_pro_gateway_mode_row" style="' . $ecommerce_paypal_payments_pro_gateway_mode_row_style . '">
                                                                                            <fieldset><legend class="form-label fs-md">'. lang('Mode') . '</legend>
                                                                                            <div class="form-field">
                                                                                                <input value="test" class="radio" type="radio" id="ecommerce_paypal_payments_pro_gateway_mode_test" name="ecommerce_paypal_payments_pro_gateway_mode" ' . $ecommerce_payment_gateway_mode_test . '>
                                                                                                <label class="" for="ecommerce_paypal_payments_pro_gateway_mode_test">'. lang('Sandbox') . '</label>
                                                                                            </div>
                                                                                            <div class="form-field">
                                                                                                <input value="live" class="radio" type="radio" id="ecommerce_paypal_payments_pro_gateway_mode_live" name="ecommerce_paypal_payments_pro_gateway_mode" ' . $ecommerce_payment_gateway_mode_live . '>
                                                                                                <label class="" for="ecommerce_paypal_payments_pro_gateway_mode_live">'. lang('Live') . '</label>
                                                                                            </div>
                                                                                            </fieldset>
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
                                                                                            <select class="form-control" name="ecommerce_first_data_global_gateway_pem_file_name" id="ecommerce_first_data_global_gateway_pem_file_name"><option value="">-' . lang('None') . '-</option>' . $ecommerce_first_data_global_gateway_pem_file_name_options . '</select>
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
                                                                                            <label class="form-label" for="ecommerce_iyzipay_installment">'. lang('Installment') . '</label>
                                                                                            <select class="form-control" id="ecommerce_iyzipay_installment" name="ecommerce_iyzipay_installment">'.$ecommerce_iyzipay_installment_options.'</select>
                                                                                        </div>
                                                                                        <div class="pg-f-xs" id="ecommerce_surcharge_percentage_row" style="' . $ecommerce_surcharge_percentage_row_style . '">
                                                                                            <label for="ecommerce_surcharge_percentage" class="form-label">' . lang('Surcharge') . '</label>
                                                                                            <label class="form-control form-adorn form-adorn-end">
                                                                                                <span class="form-adorn-text" aria-hidden="true">%</span>
                                                                                                <input value="' . $ecommerce_surcharge_percentage . '" type="text" name="ecommerce_surcharge_percentage" id="ecommerce_surcharge_percentage" class="form-ghost" size="7" maxlength="7" inputmode="decimal" data-inputmask-alias="decimal" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                                                            </label>
                                                                                        </div>
                                                                                        <div class="col-12 " id="ecommerce_iyzipay_threeds_row" style="' . $ecommerce_iyzipay_3ds_row_style . '">
                                                                                            <div class="form-field">
                                                                                                <div class="switch"><input value="1"' . $ecommerce_iyzipay_threeds_checked . ' class="collapse-switcher" type="checkbox" id="ecommerce_iyzipay_threeds" name="ecommerce_iyzipay_threeds"/></div>
                                                                                                <label class="" for="ecommerce_iyzipay_threeds">' . lang('3D Secure') . '</label>
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="col-12 ">
                                                                                            <div class="form-field">
                                                                                                <div class="switch"><input value="1" ' . $ecommerce_pay_with_iyzico_checked . ' class="" type="checkbox" id="ecommerce_pay_with_iyzico" name="ecommerce_pay_with_iyzico"/></div>
                                                                                                <label class="" for="ecommerce_pay_with_iyzico">' . lang('Enable Pay with Iyzico') . '</label>
                                                                                            </div>
                                                                                        </div>

                                                                                        <div class="col-12" id="ecommerce_iyzipay_protected_currency_row" style="' . $ecommerce_iyzipay_protected_currency_row_style . '">
                                                                                            <div class="form-field">
                                                                                                <div class="switch"><input value="1"' . $enable_iyzipay_protected_currency_checked . ' class="collapse-switcher" type="checkbox" id="enable_iyzipay_protected_currency" name="enable_iyzipay_protected_currency" data-bs-target="#enable_iyzipay_protected_currency_row"/></div>
                                                                                                <label class="" for="enable_iyzipay_protected_currency">' . lang('Enable Iyzipay Protected Currency') . '</label>
                                                                                            </div>
                                                                                            <div class="collapse popover fade bs-popover-bottom p-0  w-100" id="enable_iyzipay_protected_currency_row">
                                                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                                                <div class="popover-body">
                                                                                                   <div class="row gy-5">
                                                                                                        <div class="pg-f-md">
                                                                                                            <label class="form-label" for="iyzipay_protected_currency_code">' . lang('Currency to be converted in submiting order for currency protection') . '</label>
                                                                                                            ' . $output_iyzipay_protected_currency_select . '
                                                                                                        </div>
                                                                                                    </div>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>' . pg_payment_settings_card_rows($ecommerce_payment_gateway, ($ecommerce_credit_debit_card == 1)) . '
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 ">
                                                            <div class="form-field">
                                                                <div class="switch"><input value="1"' . $ecommerce_paypal_express_checkout_checked . ' class="collapse-switcher" type="checkbox" id="ecommerce_paypal_express_checkout" name="ecommerce_paypal_express_checkout" data-bs-target="#ecommerce_paypal_express_checkout_row"/></div>
                                                                <label class="" for="ecommerce_paypal_express_checkout">' . lang('PayPal Express Checkout') . '</label>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 " id="ecommerce_paypal_express_checkout_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                <div class="popover-body">
                                                                   <div class="row gy-5">
                                                                        <div class="col-12 ">
                                                                            <fieldset><legend class="form-label fs-md">'. lang('Transaction Type') . '</legend>
                                                                            <div class="form-field">
                                                                                <input value="Authorize" class="radio" type="radio" id="ecommerce_paypal_express_checkout_transaction_type_authorize" name="ecommerce_paypal_express_checkout_transaction_type" ' . $ecommerce_paypal_express_checkout_transaction_type_authorize . '>
                                                                                <label class="" for="ecommerce_paypal_express_checkout_transaction_type_authorize">'. lang('Authorize') . '</label>
                                                                            </div>
                                                                            <div class="form-field">
                                                                                <input value="Authorize &amp; Capture" class="radio" type="radio" id="ecommerce_paypal_express_checkout_transaction_type_authorize_and_capture" name="ecommerce_paypal_express_checkout_transaction_type" ' . $ecommerce_paypal_express_checkout_transaction_type_authorize_and_capture . '>
                                                                                <label class="" for="ecommerce_paypal_express_checkout_transaction_type_authorize_and_capture">'. lang('Authorize & Capture') . '</label>
                                                                            </div>
                                                                            </fieldset>
                                                                        </div>
                                                                        <div class="col-12 ">
                                                                            <fieldset><legend class="form-label fs-md">'. lang('Mode') . '</legend>
                                                                            <div class="form-field">
                                                                                <input value="sandbox" class="radio" type="radio" id="ecommerce_paypal_express_checkout_mode_sandbox" name="ecommerce_paypal_express_checkout_mode" ' . $ecommerce_paypal_express_checkout_mode_sandbox . '>
                                                                                <label class="" for="ecommerce_paypal_express_checkout_mode_sandbox">'. lang('Sandbox') . '</label>
                                                                            </div>
                                                                            <div class="form-field">
                                                                                <input value="live" class="radio" type="radio" id="ecommerce_paypal_express_checkout_mode_live" name="ecommerce_paypal_express_checkout_mode" ' . $ecommerce_paypal_express_checkout_mode_live . '>
                                                                                <label class="" for="ecommerce_paypal_express_checkout_mode_live">'. lang('Live') . '</label>
                                                                            </div>
                                                                            </fieldset>
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
                                                        </div>' . pg_payment_settings_redirect_rows() . '
                                                        <div class="col-12 ">
                                                            <div class="form-field">
                                                                <div class="switch"><input value="1"' . $ecommerce_offline_payment_checked . ' class="collapse-switcher" type="checkbox" id="ecommerce_offline_payment" name="ecommerce_offline_payment" data-bs-target="#ecommerce_offline_payment_row"/></div>
                                                                <label class="" for="ecommerce_offline_payment">' . lang('Allow Offline Payments') . '</label>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 " id="ecommerce_offline_payment_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                <div class="popover-body">
                                                                   <div class="row gy-5">
                                                                        <div class="col-12 ">
                                                                            <div class="form-field">
                                                                                <div class="switch"><input value="1"' . $ecommerce_offline_payment_only_specific_orders_checked . ' class="" type="checkbox" id="ecommerce_offline_payment_only_specific_orders" name="ecommerce_offline_payment_only_specific_orders"/></div>
                                                                                <label class="" for="ecommerce_offline_payment_only_specific_orders">' . lang('Only on specific orders') . '</label>
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
                                                            <select class="form-control" id="ecommerce_private_folder_id" name="ecommerce_private_folder_id">
                                                                <option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Folder')) )) . '-</option>
                                                                ' . select_folder($ecommerce_private_folder_id, 0, 0, 0, array(), array(), 'private') . '
                                                            </select>
                                                        </div>
                                                        <div class="pg-f-lg">
                                                            <label class="form-label" for="ecommerce_retrieve_order_next_page_id">'. lang('Reorder/Retrieve Order Next Page') . '</label>
                                                            <select class="form-control" id="ecommerce_retrieve_order_next_page_id" name="ecommerce_retrieve_order_next_page_id">
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

// ── Reference Price ──
// Which price stands beside a reduced price as the price before the discount
// (pg_price_reference_rule()). Drawn once the schema has its column; the save
// writes it only then.
if ($reference_price_rule_ready) {
    $reference_price_rule_options = array(
        'lowest_30d' => array(lang('Lowest price of the last 30 days'), lang('The Turkish price label regulation and the EU Omnibus rule ask for this one.')),
        'lowest_10d' => array(lang('Lowest price of the last 10 days'), lang('The Turkish rule for discount advertising from 1 August 2026.')),
        'manual'     => array(lang('Compare-at Price'), lang('The compare-at price entered on each product, shown only when it is higher than the current price.')),
    );
    $reference_price_rule_fields = '';

    foreach ($reference_price_rule_options as $reference_rule => $reference_rule_text) {
        $reference_rule_id = 'ecommerce_reference_price_rule_' . $reference_rule;
        $reference_price_rule_fields .= '
                            <div class="form-field">
                                <input value="' . $reference_rule . '" class="radio" type="radio" id="' . $reference_rule_id . '" name="ecommerce_reference_price_rule"' . (($ecommerce_reference_price_rule === $reference_rule) ? ' checked' : '') . '>
                                <label class="" for="' . $reference_rule_id . '">' . $reference_rule_text[0] . '</label>
                            </div>
                            <div class="form-text mb-3">' . $reference_rule_text[1] . '</div>';
    }

    $pg_settings_cards[] = '
    <div id="pgset-reference-price" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                ' . lang('Reference Price') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">
                    <div class="col-12">
                        <fieldset><legend class="form-label fs-md">' . lang('Price before the discount') . '</legend>' . $reference_price_rule_fields . '
                        <div class="form-text">' . lang('Under a lowest-price rule, a product whose price history is shorter than the period shows its current price as the reference, never a lowest read from part of the period.') . '</div>
                        </fieldset>
                    </div>
                </div>
            </div>
        </div>
    </div>';
}

// ── Legal Documents ──
// The pre-information form and the distance sales contract, filled from the
// order. Drawn once the schema has its fields; the save writes them only then.
if ($legal_documents_ready) {
    $legal_placeholder_rows = '';

    foreach (pg_legal_document_placeholders() as $legal_name => $legal_description) {
        $legal_placeholder_rows .= '<tr><td class="text-nowrap"><code>^^' . h($legal_name) . '^^</code></td><td>' . h($legal_description) . '</td></tr>';
    }

    $legal_template_fields = '';

    foreach (pg_legal_document_types() as $legal_type => $legal_label) {
        $legal_field = 'ecommerce_' . $legal_type . '_template';
        $legal_template_fields .= '
                                                        <div class="col-12">
                                                            <label class="form-label" for="' . $legal_field . '">' . h($legal_label) . '</label>
                                                            <textarea class="form-control font-monospace" id="' . $legal_field . '" name="' . $legal_field . '" rows="12" spellcheck="false">' . h($ecommerce_legal_templates[$legal_type]) . '</textarea>
                                                        </div>';
    }

    // The KVKK lines of the checkout: a link to the store's own notice page
    // and an optional explicit consent box.
    $legal_kvkk_fields = '';

    if ($legal_kvkk_ready) {
        $legal_kvkk_fields = '
                                    <div class="col-12 md:col-6">
                                        <label class="form-label" for="ecommerce_kvkk_page_id">' . lang('KVKK Notice Page') . '</label>
                                        <select class="form-control" id="ecommerce_kvkk_page_id" name="ecommerce_kvkk_page_id">
                                            <option value="">-' . lang(array('string' => 'Select {var:1}', 'vars' => array(lang('Page')))) . '-</option>
                                            ' . select_page($ecommerce_kvkk_page_id ?: '') . '
                                        </select>
                                        <div class="form-text">' . lang('The checkout and the sign-up forms link this page where they explain how personal data is processed.') . '</div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="ecommerce_kvkk_consent_text">' . lang('Explicit Consent Sentence') . '</label>
                                        <textarea class="form-control" id="ecommerce_kvkk_consent_text" name="ecommerce_kvkk_consent_text" rows="3">' . h($ecommerce_kvkk_consent_text) . '</textarea>
                                        <div class="form-text">' . lang('Shown beside a box the customer may leave unticked; the answer is kept with the order, or in the consent log on sign-up. Leave it empty for no box.') . '</div>
                                    </div>';
    }

    $pg_settings_cards[] = '
    <div id="pgset-legal" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                ' . lang('Legal Documents') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">
                    <div class="col-12">
                        <div class="form-field">
                            <div class="switch"><input value="1"' . $ecommerce_legal_documents_checked . ' class="collapse-switcher" type="checkbox" id="ecommerce_legal_documents" name="ecommerce_legal_documents" data-bs-target="#ecommerce_legal_documents_row"/></div>
                            <label class="" for="ecommerce_legal_documents">' . lang('Ask customers to accept the pre-information form and the distance sales contract') . '</label>
                        </div>
                        <div class="form-text">' . lang('Both documents are filled from the order and a copy is kept with it as the customer saw it, so a later change to a template never changes an accepted copy.') . '</div>
                        <div class="collapse popover fade bs-popover-bottom p-0 " id="ecommerce_legal_documents_row">
                            <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(50px, 0px);"></div>
                            <div class="popover-body">
                                <div class="row gy-5">
                                    <div class="col-12">
                                        <div class="form-text">' . lang('The templates are a starting point. Have them reviewed for your business before switching this on, and add what only you know, such as your MERSİS number and your return carrier. Clear a template to go back to the built-in one.') . '</div>
                                    </div>' . $legal_template_fields . $legal_kvkk_fields . '
                                    <div class="col-12">
                                        <details>
                                            <summary class="form-text">' . lang('Fields you can use in a template') . '</summary>
                                            <div class="form-text">' . lang('[[text ^^field^^ text]] shows the text only when the field has a value.') . '</div>
                                            <table class="table table-sm mt-3 mb-0"><tbody>' . $legal_placeholder_rows . '</tbody></table>
                                        </details>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>';
}

// ── Affiliate Program ──
$pg_settings_cards[] = '
                        <div id="pgset-affiliate" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Affiliate Program') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-5">
                                        <div class="col-12 ">
                                            <div class="form-field">
                                                <div class="switch"><input value="1"' . $affiliate_program_checked . ' class="collapse-switcher" type="checkbox" id="affiliate_program" name="affiliate_program" data-bs-target="#affiliate_program_row"/></div>
                                                <label class="" for="affiliate_program">' . lang('Enable Affiliate Program') . '</label>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0 " id="affiliate_program_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(50px, 0px);"></div>
                                                <div class="popover-body">
                                                   <div class="row gy-5">
                                                        <div class="col-12 ">
                                                            <div class="form-field">
                                                                <div class="switch"><input value="1"' . $affiliate_automatic_approval_checked . ' class="" type="checkbox" id="affiliate_automatic_approval" name="affiliate_automatic_approval"/></div>
                                                                <label class="" for="affiliate_automatic_approval">' . lang('Automatically Approve Affiliates') . '</label>
                                                            </div>
                                                        </div>
                                                        <div class="pg-f-xs">
                                                            <label for="affiliate_default_commission_rate" class="form-label">' . lang('Default Commission Rate') . '</label>
                                                            <label class="form-control form-adorn form-adorn-end">
                                                                <span class="form-adorn-text" aria-hidden="true">%</span>
                                                                <input value="' . $affiliate_default_commission_rate . '" type="text" name="affiliate_default_commission_rate" id="affiliate_default_commission_rate" class="form-ghost" size="6" maxlength="6" inputmode="decimal" data-inputmask-alias="decimal" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                            </label>
                                                        </div>
                                                        <div class="pg-f-md">
                                                            <label class="form-label" for="affiliate_contact_group_id">'. lang('Affiliate Contact Group') . '</label>
                                                            <select class="form-control" id="affiliate_contact_group_id" name="affiliate_contact_group_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Contact Group')) )) . '-</option>' . select_contact_group($affiliate_contact_group_id, $user) . '</select>
                                                        </div>
                                                        <div class="pg-f-sm">
                                                            <label for="affiliate_email_address" class="form-label">' . lang('Support E-mail Address') . '</label>
                                                            <input type="text" name="affiliate_email_address" id="affiliate_email_address" class="form-control" value="' . h($affiliate_email_address) . '" inputmode="email" data-inputmask-alias="email"/>
                                                        </div>
                                                        <div class="pg-f-md">
                                                            <label class="form-label" for="affiliate_group_offer_id">'. lang('Group Offer') . '</label>
                                                            <select class="form-control" id="affiliate_group_offer_id" name="affiliate_group_offer_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Offer')) )) . '-</option>' . select_offer($affiliate_group_offer_id) . '</select>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                   </div>
                                </div>
                            </div>
                        </div>';
