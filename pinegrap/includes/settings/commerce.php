<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the Ecommerce cards: the store, payment methods, shipping,
 * address autocomplete, stock locations, gift cards, rewards, the reference
 * price, the affiliate and referral programmes and the product feeds.
 *
 * Enable Commerce stands at the pane's head; under it each part is one folded
 * row (includes/settings/fold.php) holding its card, the rows' order, names
 * and lines being includes/fn/commerce_settings_fold.php. Fills
 * $pg_settings_cards with the head and the one list of rows. Which cards
 * belong here is said once, in includes/settings/registry.php; this file
 * holds them, and commerce.save.php writes what they edit, every field under
 * its old name. The variables they read are prepared by
 * includes/settings/prep.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/commerce_settings_fold.php');
require_once(PG_FUNCTIONS_DIR . '/includes/settings/fold.php');

// Whether the store is on, as stored: every row's pill reads Off while not.
$commerce_on = ($ecommerce_on_or_off == 1);

// Each part's card and its line of state, by row key; drawn as rows at the
// end in pg_commerce_fold_order().
$commerce_parts = array();

// ── Enable Commerce ──
// The module's switch stands above the rows, with its line while the store
// is off: the rows' settings are kept but nothing on the site uses them.
$commerce_head = '
    <div class="d-flex flex-column gap-3 mb-5">
        <div class="d-flex align-items-center gap-5">
            <input type="checkbox" class="pg-switch" value="1"' . $ecommerce_checked . ' id="ecommerce" name="ecommerce"' . ($commerce_on ? '' : ' aria-describedby="ecommerce_help"') . '/>
            <label class="pg-text-2" for="ecommerce">' . lang('Enable Commerce') . '</label>
        </div>' . ($commerce_on ? '' : '
        <p class="pg-field-help pg-text-meta fg-secondary mb-0" id="ecommerce_help">' . lang('The store is off. These settings are kept but nothing on the site uses them until Commerce is enabled.') . '</p>') . '
    </div>';

// ── Store ──
// The checked pack before a label (includes/fn/shipment_labels.php), a
// config_text row; drawn once the pick and pack tables exist, and saved only
// when drawn (pack_before_label_card).
$pack_before_label_row = '';

if (pg_schema_has('order_packs') && pg_schema_has('config_text')) {
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/shipment_labels.php');

    $pack_before_label_row = '
                    <input type="hidden" name="pack_before_label_card" value="1"/>'
        . pg_commerce_settings_switch('pack_before_label', pg_shipment_label_pack_required(), lang('Create a label only after the pack is checked'), lang('Create Label, Create Labels, the automatic label and the API wait until every unit of the address is scanned on the Pack screen.'));
}

// Rates by the buyer's country (includes/fn/tax_eu_rates.php), a config_text
// row beside Tax; drawn once the table exists and saved only when drawn
// (ecommerce_tax_by_country_card). Its note, in the switch's cell, says what
// the shop takes on and shows while the switch is on.
$tax_by_country_row = '';

if (pg_schema_has('config_text')) {
    $tax_by_country_on = pg_tax_by_country_on();

    $tax_by_country_row = pg_commerce_settings_switch(
        'ecommerce_tax_by_country',
        $tax_by_country_on,
        lang("Rates by the buyer's country"),
        lang("With Tax on, a buyer in the EU whose address is in no tax zone pays the standard VAT rate of their country. A product's own rate and the tax zones come first."),
        ' data-pg-commerce-shows="ecommerce_tax_by_country_note"',
        '
                        <div class="alert theme-warning mt-3 mb-0 py-3 small" id="ecommerce_tax_by_country_note"' . ($tax_by_country_on ? '' : ' hidden') . '>' . lang("Turn this on only if the shop is registered for the EU's one-stop shop (OSS). The rates come from the European Commission's table and change with updates; they are yours to check, and a wrong rate is the shop's liability.") . '</div>
                        <input type="hidden" name="ecommerce_tax_by_country_card" value="1"/>'
    );
}

// The four labels a product may fill in, numbered as the product form shows
// them.
$store_custom_fields = '';

foreach (array(1 => $ecommerce_custom_product_field_1_label, 2 => $ecommerce_custom_product_field_2_label, 3 => $ecommerce_custom_product_field_3_label, 4 => $ecommerce_custom_product_field_4_label) as $store_custom_number => $store_custom_value) {
    $store_custom_name = 'ecommerce_custom_product_field_' . $store_custom_number . '_label';
    $store_custom_fields .= '
                    <div class="pg-f-md">
                        <label class="form-label" for="' . $store_custom_name . '">' . lang(array('string' => 'Custom product field {var:1} label', 'vars' => array('#' . $store_custom_number))) . '</label>
                        <input class="form-control" type="text" id="' . $store_custom_name . '" name="' . $store_custom_name . '" value="' . h($store_custom_value) . '"/>
                    </div>';
}

$commerce_parts['store'] = array('html' => '
    <div id="pgset-store" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Store') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">
                    <div id="pgsub-store" class="pg-f-xs">
                        <label for="ecommerce_next_order_number" class="form-label">' . lang('Next order number') . '</label>
                        <input type="number" name="ecommerce_next_order_number" id="ecommerce_next_order_number" class="form-control" value="' . h($ecommerce_next_order_number) . '" min="1" step="1"/>
                        <input type="hidden" name="ecommerce_next_order_number_was" value="' . h($ecommerce_next_order_number) . '"/>
                    </div>
                    <div class="pg-f-lg">
                        <label for="ecommerce_email_address" class="form-label">' . lang('Commerce e-mail address') . '</label>
                        <input type="text" name="ecommerce_email_address" id="ecommerce_email_address" class="form-control" value="' . h($ecommerce_email_address) . '" inputmode="email" data-inputmask-alias="email"/>
                    </div>
                    <div class="pg-f-lg">
                        <label for="product_upload_folder_id" class="form-label">' . lang('Folder for product images') . '</label>
                        <select name="product_upload_folder_id" id="product_upload_folder_id" class="form-control" aria-describedby="product_upload_folder_id_help">' . pg_settings_select_folder($product_upload_folder_id) . '</select>
                        <div class="form-text" id="product_upload_folder_id_help">' . lang('The product and product group screens open on this folder when an image is uploaded. It can still be changed while uploading.') . '</div>
                    </div>' . pg_commerce_settings_group(lang('Prices and tax'))
                    . pg_commerce_settings_switch('ecommerce_multicurrency', $ecommerce_multicurrency_checked !== '', lang('Multi-currency'), '', ' onclick="show_or_hide_ecommerce_payment_gateway()"')
                    . pg_commerce_settings_switch('ecommerce_tax', $ecommerce_tax_checked !== '', lang('Tax'))
                    . $tax_by_country_row
                    . pg_commerce_settings_switch('ecommerce_tax_exempt', $ecommerce_tax_exempt_checked !== '', lang('Allow tax-exempt')) . '
                    <div class="pg-f-md">
                        <label for="ecommerce_tax_exempt_label" class="form-label">' . lang('Tax-exempt label') . '</label>
                        <input type="text" name="ecommerce_tax_exempt_label" id="ecommerce_tax_exempt_label" class="form-control" value="' . h($ecommerce_tax_exempt_label) . '" maxlength="255"/>
                    </div>' . pg_commerce_settings_group(lang('Products'))
                    . pg_commerce_settings_switch('ecommerce_show_product_images', $ecommerce_show_product_images_checked !== '', lang('Show product images in tables'))
                    . ($review_photos_ready ? pg_commerce_settings_switch('review_photos', $review_photos_checked !== '', lang('Photo reviews'), lang('A customer may add one image (JPG, PNG, GIF or WebP) to a product review. Other files are never taken on a review, whatever the page allows.')) : '')
                    . pg_commerce_settings_switch('barcode_enabled', $barcode_enabled_checked !== '', lang('Barcodes')) . '
                    <div class="pg-f-sm">
                        <label class="form-label" for="barcode_default_type">' . lang('Default barcode type') . '</label>
                        <select name="barcode_default_type" id="barcode_default_type" class="form-control">
                            <option value="CODE128"' . ($barcode_default_type === 'CODE128' ? ' selected' : '') . '>Code 128</option>
                            <option value="EAN13"'   . ($barcode_default_type === 'EAN13'   ? ' selected' : '') . '>EAN-13</option>
                            <option value="CODE39"'  . ($barcode_default_type === 'CODE39'  ? ' selected' : '') . '>Code 39</option>
                            <option value="UPC"'     . ($barcode_default_type === 'UPC'     ? ' selected' : '') . '>UPC-A</option>
                        </select>
                    </div>' . $store_custom_fields
                    . (($pack_before_label_row !== '') ? pg_commerce_settings_group(lang('Labels')) . $pack_before_label_row : '') . '
                </div>
            </div>
        </div>
    </div>') + pg_commerce_fold_store($commerce_on, $ecommerce_next_order_number, ($ecommerce_tax == 1) && ($ecommerce_tax_exempt == 1));

// ── Shipping ──

// The carriers connected, by name, for the row's line: the carriers' APIs
// (pg_carrier_api_connected()) and the label services that may open
// shipments (pg_label_ready()).
$commerce_carriers = array();

foreach (array('usps', 'ups', 'fedex') as $commerce_carrier) {
    if (pg_carrier_api_connected($commerce_carrier)) {
        $commerce_carriers[] = pg_carrier_api_definition($commerce_carrier)['label'];
    }
}

foreach (pg_label_drivers() as $commerce_label_key => $commerce_label_driver) {
    if (pg_label_ready($commerce_label_key)) {
        $commerce_carriers[] = $commerce_label_driver['label'];
    }
}

// The choices of a few, each a pg-seg posting the values the radios did.
$commerce_seg_options = function ($id, $choices) {
    $options = array();

    foreach ($choices as $choice) {
        $options[] = array('value' => $choice[0], 'id' => $id . '_' . $choice[1], 'label' => $choice[2], 'on' => $choice[3] !== '');
    }

    return $options;
};

$commerce_parts['shipping'] = array('html' => '
    <div id="pgset-shipping" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Shipping') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">' . pg_commerce_settings_switch('ecommerce_shipping', $ecommerce_shipping_checked !== '', lang('Shipping')) . '
                    <div class="col-12">' . pg_commerce_settings_seg(lang('Recipient mode'), 'ecommerce_recipient_mode', $commerce_seg_options('ecommerce_recipient_mode', array(
                        array('single recipient', 'single_recipient', lang('Single recipient'), $ecommerce_recipient_mode_single_recipient),
                        array('multi-recipient', 'multirecipient', lang('Several recipients'), $ecommerce_recipient_mode_multirecipient),
                    ))) . '
                    </div>' . pg_commerce_settings_group('USPS') . '
                    ' . pg_carrier_api_settings_fields('usps', $usps_legacy_set)
                    . pg_commerce_settings_switch('ecommerce_address_verification', $ecommerce_address_verification_checked !== '', lang('Verify US addresses'), lang('Uses the USPS Addresses API with the consumer key and secret on USPS\'s page of Connections.')) . '
                    <div class="col-12">' . pg_commerce_settings_seg(lang('Enforcement'), 'ecommerce_address_verification_enforcement_type', $commerce_seg_options('ecommerce_address_verification_enforcement_type', array(
                        array('warning', 'warning', lang('Warning'), $ecommerce_address_verification_enforcement_type_warning_checked),
                        array('error', 'error', lang('Error'), $ecommerce_address_verification_enforcement_type_error_checked),
                    ))) . '
                    </div>' . pg_carrier_api_test_button('usps')
                    . pg_commerce_settings_group_switch('ups', $ups_checked !== '', 'UPS') . '
                    ' . pg_carrier_api_settings_fields('ups', $ups_legacy_set) . '
                    <div class="pg-f-md">
                        <label for="ups_account" class="form-label">' . lang('Account number') . '</label>
                        <input type="text" name="ups_account" id="ups_account" class="form-control" value="' . h($ups_account) . '" maxlength="100" aria-describedby="ups_account_help"/>
                        <div class="form-text" id="ups_account_help">' . lang('For negotiated rates. Leave empty for published rates.') . '</div>
                    </div>' . pg_carrier_api_test_button('ups')
                    . pg_commerce_settings_group_switch('fedex', $fedex_checked !== '', 'FedEx') . '
                    ' . pg_carrier_api_settings_fields('fedex', $fedex_legacy_set) . '
                    <div class="pg-f-md">
                        <label for="fedex_account" class="form-label">' . lang('Account number') . '</label>
                        <input type="text" name="fedex_account" id="fedex_account" class="form-control" value="' . h($fedex_account) . '" maxlength="100"/>
                    </div>' . pg_carrier_api_test_button('fedex')
                    . pg_label_settings_rows()
                    . pg_commerce_settings_group(lang('Messages')) . '
                    <div class="col-12">
                        <label for="ecommerce_product_restriction_message" class="form-label">' . lang('Product restriction message') . '</label>
                        <textarea name="ecommerce_product_restriction_message" id="ecommerce_product_restriction_message" class="form-control" rows="2" maxlength="255">' . h($ecommerce_product_restriction_message) . '</textarea>
                    </div>
                    <div class="col-12">
                        <label for="ecommerce_no_shipping_methods_message" class="form-label">' . lang('No shipping methods message') . '</label>
                        <textarea name="ecommerce_no_shipping_methods_message" id="ecommerce_no_shipping_methods_message" class="form-control" rows="2" maxlength="255">' . h($ecommerce_no_shipping_methods_message) . '</textarea>
                    </div>
                    ' . get_time_picker_format() . '
                    <div class="pg-f-xs">
                        <label for="ecommerce_end_of_day_time" class="form-label">' . lang('End of day time') . '</label>
                        <input type="text" name="ecommerce_end_of_day_time" id="ecommerce_end_of_day_time" class="form-control" data-pg-time value="' . prepare_form_data_for_output($ecommerce_end_of_day_time, 'time') . '" maxlength="8" aria-describedby="ecommerce_end_of_day_time_help"/>' . get_date_picker_format() . pg_fields_script() . '
                        <div class="form-text" id="ecommerce_end_of_day_time_help">' . h(lang('Site time now') . ': ' . date(pg_time_format_code()) . ' (' . pg_time_format_help() . ')') . '</div>
                    </div>
                </div>
            </div>
        </div>
    </div>') + pg_commerce_fold_shipping($commerce_on, $ecommerce_shipping == 1, $ecommerce_recipient_mode !== 'single recipient', $commerce_carriers);

// ── Stock locations ──
// Where units are kept (includes/fn/stock_moves.php). A location is never
// deleted, since its moves name it; it is turned off instead. The card is
// drawn before the upgrade too, with a note, so the chip always has its card.
require_once(PG_FUNCTIONS_DIR . '/includes/fn/stock_moves.php');

$stock_card_ready = pg_stock_ready();
$stock_card_rows = '';
$stock_card_options = '';
$stock_card_locations = $stock_card_ready ? pg_stock_locations() : array();
$stock_card_default = pg_stock_pick_default($stock_card_locations);

foreach ($stock_card_locations as $stock_card_location) {
    $stock_card_id = (int) $stock_card_location['id'];
    $stock_card_rows .= '
                        <tr>
                            <td class="pg-embedded-table-text"><input type="text" class="form-control form-control-sm" name="stock_location_name[' . $stock_card_id . ']" maxlength="' . PG_STOCK_NAME_LENGTH . '" value="' . h($stock_card_location['name']) . '" aria-label="' . h(lang('Name')) . '" required /></td>
                            <td class="pg-embedded-table-text"><input type="text" class="form-control form-control-sm" name="stock_location_address[' . $stock_card_id . ']" maxlength="' . PG_STOCK_ADDRESS_LENGTH . '" value="' . h((string) $stock_card_location['address']) . '" aria-label="' . h(lang(array('string' => 'Address of {var:1}', 'vars' => array($stock_card_location['name'])))) . '" /></td>
                            <td><input type="number" min="0" max="65535" step="1" class="form-control form-control-sm pg-embedded-table-number" name="stock_location_order[' . $stock_card_id . ']" value="' . (int) $stock_card_location['sort_order'] . '" aria-label="' . h(lang(array('string' => 'Order of {var:1}', 'vars' => array($stock_card_location['name'])))) . '" /></td>
                            <td class="pg-embedded-table-tight text-center"><input type="checkbox" class="pg-switch" value="1" name="stock_location_enabled[' . $stock_card_id . ']"' . (((int) $stock_card_location['enabled'] === 1) ? ' checked' : '') . ' aria-label="' . h(lang(array('string' => '{var:1} is on', 'vars' => array($stock_card_location['name'])))) . '" /></td>
                        </tr>';
    $stock_card_options .= '<option value="' . $stock_card_id . '"' . (($stock_card_id === $stock_card_default) ? ' selected="selected"' : '') . '>' . h($stock_card_location['name']) . '</option>';
}

$commerce_stock_off = 0;

foreach ($stock_card_locations as $stock_card_location) {
    if ((int) $stock_card_location['enabled'] !== 1) {
        $commerce_stock_off++;
    }
}

$commerce_parts['stock-locations'] = array('html' => '
    <div id="pgset-stock-locations" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Stock locations') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">
                    <div class="col-12">' . ($stock_card_ready ? '
                        <input type="hidden" name="stock_location_card" value="1" />' : '') . '
                        <p class="pg-field-help pg-text-meta fg-secondary mb-0">' . lang('A product\'s stock is the sum of its locations; the first location added becomes the default and holds the current stock.') . '</p>' . (!$stock_card_ready ? '
                        <p class="pg-field-help pg-text-meta fg-warning mb-0">' . lang('Stock locations become available once the database upgrade has run.') . '</p>' : '') . '
                    </div>' . ($stock_card_ready ? (($stock_card_rows !== '') ? '
                    <div class="col-12">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 pg-embedded-table">
                                <caption class="visually-hidden">' . lang('Stock locations') . '</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">' . lang('Name') . '</th>
                                        <th scope="col">' . lang('Address') . '</th>
                                        <th scope="col">' . lang('Order') . '</th>
                                        <th scope="col" class="pg-embedded-table-tight text-center">' . lang('On') . '</th>
                                    </tr>
                                </thead>
                                <tbody>' . $stock_card_rows . '
                                </tbody>
                            </table>
                        </div>
                        <div class="form-text">' . lang('A location that is turned off takes no transfers; the units it holds still count in the product\'s stock.') . '</div>
                    </div>
                    <div class="pg-f-md">
                        <label for="stock_location_default" class="form-label">' . lang('Default location') . '</label>
                        <select name="stock_location_default" id="stock_location_default" class="form-control">' . $stock_card_options . '</select>
                    </div>' : '')
                    . pg_commerce_settings_reveal('stock_location_new', lang('Add a location'), 'plus-lg', '
                            <div class="pg-f-md">
                                <label for="stock_location_new_name" class="form-label">' . lang('Name') . '</label>
                                <input type="text" name="stock_location_new_name" id="stock_location_new_name" class="form-control" maxlength="' . PG_STOCK_NAME_LENGTH . '" value="" />
                            </div>
                            <div class="pg-f-lg">
                                <label for="stock_location_new_address" class="form-label">' . lang('Address') . '</label>
                                <input type="text" name="stock_location_new_address" id="stock_location_new_address" class="form-control" maxlength="' . PG_STOCK_ADDRESS_LENGTH . '" value="" aria-describedby="stock_location_new_address_help" />
                                <div class="form-text" id="stock_location_new_address_help">' . lang('Added when you save.') . '</div>
                            </div>') . '
                    <div class="col-12">
                        <a class="pg-button pg-button-ghost pg-button-sm" href="view_stock_moves.php"><i class="bi bi-clock-history" aria-hidden="true"></i>' . lang('Stock history') . '</a>
                    </div>' : '') . '
                </div>
            </div>
        </div>
    </div>') + pg_commerce_fold_stock($commerce_on, $stock_card_ready, count($stock_card_locations), $commerce_stock_off);

// ── Address Autocomplete ──
// The switch and the provider; each provider's key or server is typed on
// its page of Connections (includes/fn/address_autocomplete.php).
$commerce_addr_provider = pg_addr_provider();
$commerce_addr_provider = ($commerce_addr_provider !== '') ? pg_addr_provider_definition($commerce_addr_provider)['label'] : '';

$commerce_parts['address-autocomplete'] = array('html' => '
    <div id="pgset-address-autocomplete" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Address Autocomplete') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">' . pg_addr_settings_fields() . '
                </div>
            </div>
        </div>
    </div>') + pg_commerce_fold_address($commerce_on, $commerce_addr_provider);

$pg_settings_scripts[] = pg_addr_settings_script();
$pg_settings_scripts[] = pg_commerce_settings_reveal_script();

// ── Gift cards ──
$commerce_parts['giftcards'] = array('html' => '
    <div id="pgset-giftcards" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Gift cards') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">' . pg_commerce_settings_switch('ecommerce_gift_card', $ecommerce_gift_card_checked !== '', lang('Accept gift cards')) . '
                    <div class="pg-f-xs">
                        <label for="ecommerce_gift_card_validity_days" class="form-label">' . lang('Validity length') . '</label>
                        <label class="form-control form-adorn form-adorn-end">
                            <span class="form-adorn-text" aria-hidden="true">' . lang('day(s)') . '</span>
                            <input type="text" name="ecommerce_gift_card_validity_days" id="ecommerce_gift_card_validity_days" class="form-ghost text-end" value="' . $ecommerce_gift_card_validity_days . '" size="7" maxlength="7" inputmode="numeric" data-inputmask-alias="decimal" aria-describedby="ecommerce_gift_card_validity_days_help"/>
                        </label>
                        <div class="form-text" id="ecommerce_gift_card_validity_days_help">' . lang('Leave blank for no expiry.') . '</div>
                    </div>'
                    . pg_commerce_settings_group_switch('ecommerce_givex', $ecommerce_givex_checked !== '', lang('Accept Givex cards'))
                    . pg_payment_method_connection_html('givex', $ecommerce_method_connection_stored)
                    . pg_commerce_settings_group(lang('Store credit')) . '
                    <div class="pg-f-xs">
                        <label for="credit_refund_bonus" class="form-label">' . lang('Bonus on refunds given as store credit') . '</label>
                        <label class="form-control form-adorn form-adorn-end">
                            <span class="form-adorn-text" aria-hidden="true">%</span>
                            <input value="' . h($credit_refund_bonus) . '" type="text" name="credit_refund_bonus" id="credit_refund_bonus" class="form-ghost text-end" size="6" maxlength="6" inputmode="decimal" aria-describedby="credit_refund_bonus_help"/>
                        </label>
                        <div class="form-text" id="credit_refund_bonus_help">' . lang('Leave blank for no bonus.') . '</div>
                    </div>
                </div>
            </div>
        </div>
    </div>') + pg_commerce_fold_giftcards($commerce_on, $ecommerce_gift_card == 1, $ecommerce_gift_card_validity_days);

// ── Rewards ──
// Points towards a goal, their store credit, and what reaching the goal
// grants.
$commerce_parts['rewards'] = array('html' => '
    <div id="pgset-rewards" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Rewards') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">' . pg_commerce_settings_switch('ecommerce_reward_program', $ecommerce_reward_program_checked !== '', lang('Reward program')) . '
                    <div class="pg-f-xs">
                        <label for="ecommerce_reward_program_points" class="form-label">' . lang('Goal') . '</label>
                        <label class="form-control form-adorn form-adorn-end">
                            <span class="form-adorn-text" aria-hidden="true">' . lang('point(s)') . '</span>
                            <input value="' . $ecommerce_reward_program_points . '" type="text" name="ecommerce_reward_program_points" id="ecommerce_reward_program_points" class="form-ghost text-end" size="6" maxlength="6" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0" />
                        </label>
                    </div>
                    <div class="pg-f-xs">
                        <label for="credit_points_per_unit" class="form-label">' . lang('Points per unit of store credit') . '</label>
                        <label class="form-control form-adorn form-adorn-end">
                            <span class="form-adorn-text" aria-hidden="true">' . lang('point(s)') . '</span>
                            <input value="' . h($credit_points_per_unit) . '" type="text" name="credit_points_per_unit" id="credit_points_per_unit" class="form-ghost text-end" size="8" maxlength="12" inputmode="decimal" aria-describedby="credit_points_per_unit_help"/>
                        </label>
                        <div class="form-text" id="credit_points_per_unit_help">' . lang('Leave blank to keep points from turning into store credit.') . '</div>
                    </div>
                    <div class="pg-f-xs">
                        <label for="credit_points_smallest" class="form-label">' . lang('Smallest conversion') . '</label>
                        <label class="form-control form-adorn form-adorn-end">
                            <span class="form-adorn-text" aria-hidden="true">' . h(BASE_CURRENCY_CODE) . '</span>
                            <input value="' . h($credit_points_smallest) . '" type="text" name="credit_points_smallest" id="credit_points_smallest" class="form-ghost text-end" size="8" maxlength="14" inputmode="decimal" />
                        </label>
                    </div>' . pg_commerce_settings_group(lang('When the goal is reached'))
                    . pg_commerce_settings_switch('ecommerce_reward_program_membership', $ecommerce_reward_program_membership_checked !== '', lang('Grant membership')) . '
                    <div class="pg-f-xs">
                        <label for="ecommerce_reward_program_membership_days" class="form-label">' . lang('Membership length') . '</label>
                        <label class="form-control form-adorn form-adorn-end">
                            <span class="form-adorn-text" aria-hidden="true">' . lang('day(s)') . '</span>
                            <input value="' . $ecommerce_reward_program_membership_days . '" type="text" name="ecommerce_reward_program_membership_days" id="ecommerce_reward_program_membership_days" class="form-ghost text-end" size="5" maxlength="5" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0" aria-describedby="ecommerce_reward_program_membership_days_help"/>
                        </label>
                        <div class="form-text" id="ecommerce_reward_program_membership_days_help">' . lang('Leave blank for lifetime membership.') . '</div>
                    </div>'
                    . pg_commerce_settings_switch('ecommerce_reward_program_email', $ecommerce_reward_program_email_checked !== '', lang('Send e-mail')) . '
                    <div class="pg-f-md">
                        <label class="form-label" for="ecommerce_reward_program_email_bcc_email_address">' . lang('BCC e-mail address') . '</label>
                        <input type="text" value="' . h($ecommerce_reward_program_email_bcc_email_address) . '" class="form-control" id="ecommerce_reward_program_email_bcc_email_address" name="ecommerce_reward_program_email_bcc_email_address" maxlength="100" inputmode="email" data-inputmask-alias="email"/>
                    </div>
                    <div class="pg-f-md">
                        <label class="form-label" for="ecommerce_reward_program_email_subject">' . lang('Subject') . '</label>
                        <input type="text" value="' . h($ecommerce_reward_program_email_subject) . '" id="ecommerce_reward_program_email_subject" name="ecommerce_reward_program_email_subject" class="form-control" maxlength="255"/>
                    </div>
                    <div class="pg-f-md">
                        <label class="form-label" for="ecommerce_reward_program_email_page_id">' . lang('Page') . '</label>
                        ' . pg_picker_field('page', 'ecommerce_reward_program_email_page_id', $ecommerce_reward_program_email_page_id, array('placeholder' => lang('Choose a page'))) . pg_picker_script() . '
                    </div>
                </div>
            </div>
        </div>
    </div>') + pg_commerce_fold_rewards($commerce_on, $ecommerce_reward_program == 1, $ecommerce_reward_program_points);

// ── Payment Methods ──
// The methods switched on, by name, in the checkout's order, and a card
// gateway chosen whose keys are not all on its page of Connections.
$commerce_methods = array();
$commerce_unconnected = '';

if ($ecommerce_credit_debit_card == 1) {
    $commerce_gateway_page = pg_payment_gateway_connection_for($ecommerce_payment_gateway);
    $commerce_gateway_driver = pg_payment_driver_for_gateway($ecommerce_payment_gateway);
    $commerce_gateway_name = lang('Cards');

    if ($commerce_gateway_page !== '') {
        $commerce_gateway_definitions = pg_payment_gateway_connections();
        $commerce_gateway_name = $commerce_gateway_definitions[$commerce_gateway_page]['label'];

        if (!pg_payment_gateway_connected($commerce_gateway_definitions[$commerce_gateway_page], pg_payment_gateway_connection_stored())) {
            $commerce_unconnected = $commerce_gateway_name;
        }
    } elseif ($commerce_gateway_driver !== '') {
        $commerce_gateway_name = pg_payment_driver($commerce_gateway_driver)['label'];

        if (pg_payment_driver_has_page($commerce_gateway_driver)
            && !pg_payment_driver_keys_complete(pg_payment_fields($commerce_gateway_driver), pg_payment_settings($commerce_gateway_driver))) {
            $commerce_unconnected = $commerce_gateway_name;
        }
    }

    $commerce_methods[] = $commerce_gateway_name;
}

if ($ecommerce_paypal_express_checkout == 1) {
    $commerce_methods[] = 'PayPal Express Checkout';
}

if ($ecommerce_pay_with_iyzico == 1) {
    $commerce_methods[] = lang('Pay with iyzico');
}

foreach (pg_payment_drivers() as $commerce_driver_key => $commerce_driver) {
    if (($commerce_driver['kind'] === 'redirect') && ($commerce_driver['settings'] === 'connect') && pg_payment_settings($commerce_driver_key)['enabled']) {
        $commerce_methods[] = $commerce_driver['label'];
    }
}

if ($ecommerce_offline_payment == 1) {
    $commerce_methods[] = lang('Offline payments');
}

$commerce_parts['payments'] = array('html' => '
    <div id="pgset-payments" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Payment methods') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">' . pg_commerce_settings_group_switch('ecommerce_credit_debit_card', $ecommerce_credit_debit_card_checked !== '', lang('Cards'), ' onclick="show_or_hide_ecommerce_payment_gateway()"')
                    . pg_commerce_settings_ticks(lang('Accepted cards'), array(
                        array('name' => 'ecommerce_american_express', 'label' => lang('American Express'), 'on' => $ecommerce_american_express_checked !== ''),
                        array('name' => 'ecommerce_diners_club', 'label' => lang('Diners Club'), 'on' => $ecommerce_diners_club_checked !== ''),
                        array('name' => 'ecommerce_discover_card', 'label' => lang('Discover Card'), 'on' => $ecommerce_discover_card_checked !== ''),
                        array('name' => 'ecommerce_mastercard', 'label' => lang('MasterCard'), 'on' => $ecommerce_mastercard_checked !== ''),
                        array('name' => 'ecommerce_visa', 'label' => lang('Visa'), 'on' => $ecommerce_visa_checked !== ''),
                        array('name' => 'ecommerce_troy', 'label' => lang('Troy'), 'on' => $ecommerce_troy_checked !== ''),
                    )) . '
                    <div class="pg-f-sm">
                        <label class="form-label" for="ecommerce_payment_gateway">' . lang('Payment gateway') . '</label>
                        <select class="form-control" name="ecommerce_payment_gateway" id="ecommerce_payment_gateway" onchange="show_or_hide_ecommerce_payment_gateway()">
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
                    <div class="col-12" id="ecommerce_payment_gateway_transaction_type_row" style="' . $ecommerce_payment_gateway_transaction_type_row_style . '">' . pg_commerce_settings_seg(lang('Transaction type'), 'ecommerce_payment_gateway_transaction_type', $commerce_seg_options('ecommerce_payment_gateway_transaction_type', array(
                        array('Authorize', 'authorize', lang('Authorize'), $ecommerce_payment_gateway_transaction_type_authorize),
                        array('Authorize & Capture', 'authorize_and_capture', lang('Authorize & capture'), $ecommerce_payment_gateway_transaction_type_authorize_and_capture),
                    ))) . '
                    </div>
                    <div class="col-12" id="ecommerce_payment_gateway_mode_row" style="' . $ecommerce_payment_gateway_mode_row_style . '">' . pg_commerce_settings_seg(lang('Mode'), 'ecommerce_payment_gateway_mode', $commerce_seg_options('ecommerce_payment_gateway_mode', array(
                        array('test', 'test', lang('Test'), $ecommerce_payment_gateway_mode_test),
                        array('live', 'live', lang('Live'), $ecommerce_payment_gateway_mode_live),
                    ))) . '
                    </div>
                    <div class="col-12" id="ecommerce_paypal_payments_pro_gateway_mode_row" style="' . $ecommerce_paypal_payments_pro_gateway_mode_row_style . '">' . pg_commerce_settings_seg(lang('Mode'), 'ecommerce_paypal_payments_pro_gateway_mode', $commerce_seg_options('ecommerce_paypal_payments_pro_gateway_mode', array(
                        array('test', 'test', lang('Sandbox'), $ecommerce_payment_gateway_mode_test),
                        array('live', 'live', lang('Live'), $ecommerce_payment_gateway_mode_live),
                    ))) . '
                    </div>
                    ' . pg_payment_gateway_connections_html($ecommerce_payment_gateway, ($ecommerce_credit_debit_card == 1), $ecommerce_gateway_connection_stored) . '
                    <div class="pg-f-lg" id="ecommerce_iyzipay_installment_row" style="' . $ecommerce_iyzipay_installment_row_style . '">
                        <label class="form-label" for="ecommerce_iyzipay_installment">' . lang('Installment') . '</label>
                        <select class="form-control" id="ecommerce_iyzipay_installment" name="ecommerce_iyzipay_installment">' . $ecommerce_iyzipay_installment_options . '</select>
                    </div>
                    <div class="pg-f-xs" id="ecommerce_surcharge_percentage_row" style="' . $ecommerce_surcharge_percentage_row_style . '">
                        <label for="ecommerce_surcharge_percentage" class="form-label">' . lang('Surcharge') . '</label>
                        <label class="form-control form-adorn form-adorn-end">
                            <span class="form-adorn-text" aria-hidden="true">%</span>
                            <input value="' . $ecommerce_surcharge_percentage . '" type="text" name="ecommerce_surcharge_percentage" id="ecommerce_surcharge_percentage" class="form-ghost text-end" size="7" maxlength="7" inputmode="decimal" data-inputmask-alias="decimal" data-inputmask-placeholder="0" />
                        </label>
                    </div>
                    <div class="col-12" id="ecommerce_iyzipay_threeds_row" style="' . $ecommerce_iyzipay_3ds_row_style . '">
                        <div class="row gy-5">' . pg_commerce_settings_switch('ecommerce_iyzipay_threeds', $ecommerce_iyzipay_threeds_checked !== '', lang('3D Secure')) . '
                        </div>
                    </div>
                    <div class="col-12" id="ecommerce_iyzipay_protected_currency_row" style="' . $ecommerce_iyzipay_protected_currency_row_style . '">
                        <div class="row gy-5">' . pg_commerce_settings_switch('enable_iyzipay_protected_currency', $enable_iyzipay_protected_currency_checked !== '', lang('Iyzipay protected currency')) . (($output_iyzipay_protected_currency_select !== '') ? '
                            <div class="pg-f-md">
                                <label class="form-label" for="iyzipay_protected_currency_code">' . lang('Currency to be converted in submiting order for currency protection') . '</label>
                                ' . $output_iyzipay_protected_currency_select . '
                            </div>' : '') . '
                        </div>
                    </div>' . pg_payment_settings_card_rows($ecommerce_payment_gateway, ($ecommerce_credit_debit_card == 1))
                    . pg_commerce_settings_group_switch('ecommerce_pay_with_iyzico', $ecommerce_pay_with_iyzico_checked !== '', lang('Pay with iyzico'))
                    . pg_commerce_settings_group_switch('ecommerce_paypal_express_checkout', $ecommerce_paypal_express_checkout_checked !== '', 'PayPal Express Checkout') . '
                    <div class="col-12">' . pg_commerce_settings_seg(lang('Transaction type'), 'ecommerce_paypal_express_checkout_transaction_type', $commerce_seg_options('ecommerce_paypal_express_checkout_transaction_type', array(
                        array('Authorize', 'authorize', lang('Authorize'), $ecommerce_paypal_express_checkout_transaction_type_authorize),
                        array('Authorize & Capture', 'authorize_and_capture', lang('Authorize & capture'), $ecommerce_paypal_express_checkout_transaction_type_authorize_and_capture),
                    ))) . '
                    </div>
                    <div class="col-12">' . pg_commerce_settings_seg(lang('Mode'), 'ecommerce_paypal_express_checkout_mode', $commerce_seg_options('ecommerce_paypal_express_checkout_mode', array(
                        array('sandbox', 'sandbox', lang('Sandbox'), $ecommerce_paypal_express_checkout_mode_sandbox),
                        array('live', 'live', lang('Live'), $ecommerce_paypal_express_checkout_mode_live),
                    ))) . '
                    </div>' . pg_payment_method_connection_html('paypal_express', $ecommerce_method_connection_stored)
                    . pg_payment_settings_redirect_rows()
                    . pg_commerce_settings_group_switch('ecommerce_offline_payment', $ecommerce_offline_payment_checked !== '', lang('Offline payments'))
                    . pg_commerce_settings_switch('ecommerce_offline_payment_only_specific_orders', $ecommerce_offline_payment_only_specific_orders_checked !== '', lang('Only on specific orders')) . '
                    <div class="pg-f-xs">
                        <label class="form-label" for="ecommerce_offline_payment_cancel_days">' . lang('Cancel unpaid bank transfer orders after') . '</label>
                        <input type="number" min="0" max="255" step="1" class="form-control" id="ecommerce_offline_payment_cancel_days" name="ecommerce_offline_payment_cancel_days" value="' . (int) $ecommerce_offline_payment_cancel_days . '" aria-describedby="ecommerce_offline_payment_cancel_days_help"/>
                    </div>
                    <div class="col-12 mt-3">
                        <div class="form-text mt-0" id="ecommerce_offline_payment_cancel_days_help">' . lang('days; 0 keeps them open until you cancel by hand. Cancelled orders keep the reason on the order screen.') . '</div>
                    </div>' . pg_commerce_settings_group(lang('After the order')) . '
                    <div class="pg-f-lg">
                        <label class="form-label" for="ecommerce_private_folder_id">' . lang('Grant private access') . '</label>
                        <select class="form-control" id="ecommerce_private_folder_id" name="ecommerce_private_folder_id">
                            <option value="">-' . lang(array('string' => 'Select {var:1}', 'vars' => array(lang('Folder')))) . '-</option>
                            ' . pg_settings_select_folder($ecommerce_private_folder_id, 'private') . '
                        </select>
                    </div>
                    <div class="pg-f-lg">
                        <label class="form-label" for="ecommerce_retrieve_order_next_page_id">' . lang('Page after a reorder or retrieved order') . '</label>
                        ' . pg_picker_field('page', 'ecommerce_retrieve_order_next_page_id', $ecommerce_retrieve_order_next_page_id, array('placeholder' => lang('Choose a page'))) . pg_picker_script() . '
                    </div>
                </div>
            </div>
        </div>
    </div>') + pg_commerce_fold_payments($commerce_on, $commerce_methods, $commerce_unconnected);

// ── Reference Price ──
// Which price stands beside a reduced price as the price before the discount
// (pg_price_reference_rule()). Drawn once the schema has its column; the save
// writes it only then.
if ($reference_price_rule_ready) {
    $reference_price_rule_options = array();

    foreach (array(
        'lowest_30d' => array(lang('Lowest price of the last 30 days'), lang('The Turkish price label regulation and the EU Omnibus rule ask for this one.')),
        'lowest_10d' => array(lang('Lowest price of the last 10 days'), lang('The Turkish rule for discount advertising from 1 August 2026.')),
        'manual'     => array(lang('Compare-at price'), lang('The compare-at price entered on each product, shown only when it is higher than the current price.')),
    ) as $reference_rule => $reference_rule_text) {
        $reference_price_rule_options[] = array('value' => $reference_rule, 'label' => $reference_rule_text[0], 'line' => $reference_rule_text[1], 'on' => ($ecommerce_reference_price_rule === $reference_rule));
    }

    $commerce_parts['reference-price'] = array('html' => '
    <div id="pgset-reference-price" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Reference Price') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">' . pg_commerce_settings_choice('ecommerce_reference_price_rule', lang('Price before the discount'), $reference_price_rule_options, lang('Under a lowest-price rule, a product whose price history is shorter than the period shows its current price as the reference, never a lowest read from part of the period.')) . '
                </div>
            </div>
        </div>
    </div>') + pg_commerce_fold_reference($commerce_on, $ecommerce_reference_price_rule);
}

// ── Affiliate Program ──
// Its fields are drawn under the switch whatever the switch says.
$commerce_parts['affiliate'] = array('html' => '
    <div id="pgset-affiliate" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Affiliate Program') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">' . pg_commerce_settings_switch('affiliate_program', $affiliate_program == 1, lang('Turn on the affiliate programme'))
                    . pg_commerce_settings_switch('affiliate_automatic_approval', $affiliate_automatic_approval_checked !== '', lang('Approve affiliates automatically')) . '
                    <div class="pg-f-xs">
                        <label for="affiliate_default_commission_rate" class="form-label">' . lang('Default commission rate') . '</label>
                        <label class="form-control form-adorn form-adorn-end">
                            <span class="form-adorn-text" aria-hidden="true">%</span>
                            <input value="' . $affiliate_default_commission_rate . '" type="text" name="affiliate_default_commission_rate" id="affiliate_default_commission_rate" class="form-ghost text-end" size="6" maxlength="6" inputmode="decimal" data-inputmask-alias="decimal" data-inputmask-placeholder="0" />
                        </label>
                    </div>
                    <div class="pg-f-md">
                        <label class="form-label" for="affiliate_contact_group_id">' . lang('Affiliate contact group') . '</label>
                        <select class="form-control" id="affiliate_contact_group_id" name="affiliate_contact_group_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Contact Group')) )) . '-</option>' . select_contact_group($affiliate_contact_group_id, $user) . '</select>
                    </div>
                    <div class="pg-f-sm">
                        <label for="affiliate_email_address" class="form-label">' . lang('Affiliate programme e-mail address') . '</label>
                        <input type="text" name="affiliate_email_address" id="affiliate_email_address" class="form-control" value="' . h($affiliate_email_address) . '" inputmode="email" data-inputmask-alias="email"/>
                    </div>
                    <div class="pg-f-lg">
                        <label class="form-label" for="affiliate_group_offer_id">' . lang('Group offer') . '</label>
                        <select class="form-control" id="affiliate_group_offer_id" name="affiliate_group_offer_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Offer')) )) . '-</option>' . select_offer($affiliate_group_offer_id) . '</select>
                    </div>
                </div>
            </div>
        </div>
    </div>') + pg_commerce_fold_affiliate($commerce_on, $affiliate_program == 1, $affiliate_default_commission_rate);

// ── Referral programme ──
// A customer with an order brings a friend through their own link; the rules
// are includes/fn/referral.php, the values config_text rows that
// commerce.save.php writes through pg_referral_settings_check().
$referral_notice = '';

if (!defined('ECOMMERCE') || (ECOMMERCE !== true)) {
    $referral_notice = lang('Ecommerce is off, so the referral programme gives no reward until it is turned on.');
} elseif (!$referral_ready) {
    $referral_notice = lang('The referral programme gives rewards once the database upgrade has run.');
}

$commerce_parts['referral'] = array('html' => '
    <div id="pgset-referral" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Referral programme') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">' . (($referral_notice !== '') ? '
                    <div class="col-12">
                        <p class="pg-field-help pg-text-meta fg-warning mb-0">' . h($referral_notice) . '</p>
                    </div>' : '')
                    . pg_commerce_settings_switch('referral_on', !empty($referral_settings['on']), lang('Turn on the referral programme'), lang('A customer with an order shares their own link; when a friend\'s first order is completed, the customer earns store credit. An approved affiliate keeps the commission programme instead. Off, nothing changes.'))
                    . pg_commerce_settings_group(lang('For the friend')) . '
                    <div class="pg-f-lg">
                        <label class="form-label" for="referral_offer_id">' . lang('Offer for the friend') . '</label>
                        <select class="form-control" id="referral_offer_id" name="referral_offer_id" aria-describedby="referral_offer_id_help"><option value="">-' . lang('None') . '-</option>' . select_offer($referral_settings['offer_id']) . '</select>
                        <div class="form-text" id="referral_offer_id_help">' . lang('Put in the friend\'s cart when they arrive by a referral link, while the offer runs.') . '</div>
                    </div>
                    <div class="pg-f-xs">
                        <label for="referral_smallest" class="form-label">' . lang('Smallest order that counts') . '</label>
                        <label class="form-control form-adorn form-adorn-end">
                            <span class="form-adorn-text" aria-hidden="true">' . h(BASE_CURRENCY_CODE) . '</span>
                            <input value="' . h($referral_settings['smallest']) . '" type="text" name="referral_smallest" id="referral_smallest" class="form-ghost text-end" size="8" maxlength="14" inputmode="decimal" />
                        </label>
                    </div>' . pg_commerce_settings_group(lang('For the customer')) . '
                    <div class="pg-f-xs">
                        <label for="referral_reward" class="form-label">' . lang('Reward') . '</label>
                        <label class="form-control form-adorn form-adorn-end">
                            <span class="form-adorn-text" aria-hidden="true">' . h(BASE_CURRENCY_CODE) . '</span>
                            <input value="' . h($referral_settings['reward']) . '" type="text" name="referral_reward" id="referral_reward" class="form-ghost text-end" size="8" maxlength="14" inputmode="decimal" aria-describedby="referral_reward_help"/>
                        </label>
                        <div class="form-text" id="referral_reward_help">' . lang('Store credit the customer earns for each friend.') . '</div>
                    </div>
                    <div class="pg-f-xs">
                        <label for="referral_reward_days" class="form-label">' . lang('Reward lasts') . '</label>
                        <label class="form-control form-adorn form-adorn-end">
                            <span class="form-adorn-text" aria-hidden="true">' . lang('day(s)') . '</span>
                            <input value="' . h($referral_settings['days']) . '" type="text" name="referral_reward_days" id="referral_reward_days" class="form-ghost text-end" size="5" maxlength="4" inputmode="numeric" aria-describedby="referral_reward_days_help"/>
                        </label>
                        <div class="form-text" id="referral_reward_days_help">' . lang('Leave blank for credit that never expires.') . '</div>
                    </div>
                    <div class="pg-f-xs">
                        <label for="referral_month_cap" class="form-label">' . lang('Rewards a month per customer') . '</label>
                        <input value="' . h($referral_settings['month_cap']) . '" type="text" name="referral_month_cap" id="referral_month_cap" class="form-control text-end" size="5" maxlength="4" inputmode="numeric" aria-describedby="referral_month_cap_help"/>
                        <div class="form-text" id="referral_month_cap_help">' . lang('Leave blank for no limit.') . '</div>
                    </div>
                </div>
            </div>
        </div>
    </div>') + pg_commerce_fold_referral($commerce_on, !empty($referral_settings['on']), $referral_settings['reward'], BASE_CURRENCY_CODE);

// ── Product Feeds ──
// The shop's products as files shopping platforms read
// (includes/fn/product_feeds.php): a switch, an address and the last build per
// format, then the seller fields every format shares. The fields are drawn
// even while e-commerce is off or before the 11.0.4 step, so a save never
// finds them missing; commerce.save.php writes nothing until the column is there.
$product_feeds_groups = pg_product_feed_screen_groups();
$product_feeds_rows = '';

// Prices in: drawn only on a site with an enabled market. A stored market
// that is no longer offered shows as the base currency.
$product_feeds_market = '';
$product_feeds_markets = pg_product_feed_market_choices();

if ($product_feeds_markets) {
    $product_feeds_market_options = '<option value="0">' . h(lang(array('string' => 'Base currency ({var:1})', 'vars' => array(BASE_CURRENCY_CODE)))) . '</option>';

    foreach ($product_feeds_markets as $product_feeds_market_id => $product_feeds_market_label) {
        $product_feeds_market_options .= '<option value="' . (int) $product_feeds_market_id . '"' . (((int) $product_feeds_settings['market_id'] === (int) $product_feeds_market_id) ? ' selected' : '') . '>' . h($product_feeds_market_label) . '</option>';
    }

    $product_feeds_market = '
                    <div class="pg-f-md">
                        <label for="product_feed_market_id" class="form-label">' . lang('Prices in') . '</label>
                        <select name="product_feed_market_id" id="product_feed_market_id" class="form-control" aria-describedby="product_feed_market_id_help">' . $product_feeds_market_options . '</select>
                        <div class="form-text" id="product_feed_market_id_help">' . lang('In a market, every feed gives its currency and the price the cart there charges: the fixed price, else the converted and rounded one.') . '</div>
                    </div>';
}
$product_feeds_in_dialog = !empty($pg_settings_in_dialog);

// The Google Merchant Center part of the card ($product_feeds_merchant).
require(PG_FUNCTIONS_DIR . '/includes/settings/commerce_merchant.php');

foreach (pg_product_feed_format_texts() as $product_feeds_format => $product_feeds_text) {
    $product_feeds_entry = $product_feeds_settings['formats'][$product_feeds_format];
    $product_feeds_address = '';

    if ($product_feeds_entry['token_hash'] !== '') {
        $product_feeds_token = pg_product_feed_token($product_feeds_entry);

        // New address asks in a drawer: its button there posts the format
        // through the form attribute, as the drawer sits beside the pane.
        $product_feeds_drawer = 'pg-feed-address-' . $product_feeds_format;
        $pg_settings_modals[] = pg_settings_drawer(
            $product_feeds_drawer,
            h(lang('New address')),
            '<p class="mb-0">' . h(lang('Make a new address? The current address stops working at once, so the platforms that read it must be given the new one.')) . '</p>',
            $product_feeds_in_dialog,
            '<button type="submit" form="' . ($product_feeds_in_dialog ? 'pg_settings_modal_form' : 'pg_settings_page_form') . '" name="product_feed_new_address" value="' . h($product_feeds_format) . '" class="pg-button pg-button-danger"><i class="bi bi-arrow-repeat" aria-hidden="true"></i>' . h(lang('Make a new address')) . '</button>',
            h($product_feeds_text['label'])
        );

        $product_feeds_address = '
                    <div class="col-12">
                        <div class="d-flex align-items-center gap-2 pg-f-lg">
                            ' . (($product_feeds_token !== '')
                                ? '<input type="text" class="form-control" readonly value="' . h(pg_product_feed_url($product_feeds_token)) . '" aria-label="' . h(lang('Feed address')) . '" onfocus="this.select()" />'
                                : '<span class="form-control fg-secondary">' . h(lang('This address cannot be shown again on this site. Make a new address to see it.')) . '</span>') . '
                            <button type="button" class="pg-button pg-button-quiet pg-button-sm text-nowrap" data-bs-toggle="drawer" data-bs-target="#' . h($product_feeds_drawer) . '" aria-controls="' . h($product_feeds_drawer) . '"><i class="bi bi-arrow-repeat" aria-hidden="true"></i>' . lang('New address') . '</button>
                        </div>
                    </div>';
    }

    $product_feeds_status = pg_product_feed_status_text($product_feeds_entry);

    if ($product_feeds_entry['error'] !== '') {
        $product_feeds_status = '<span class="fg-danger">' . h(lang(array('string' => 'The last build failed: {var:1} The file built before is still served.', 'vars' => array(pg_product_feed_error_text($product_feeds_entry['error']))))) . '</span>'
            . (($product_feeds_status !== '') ? ' ' . h($product_feeds_status) : '');
    } elseif ($product_feeds_entry['enabled'] && ((int) $product_feeds_entry['built_at'] === 0)) {
        $product_feeds_status = h(lang('Not built yet.'));
    } else {
        $product_feeds_status = h($product_feeds_status);
    }

    $product_feeds_rows .= pg_commerce_settings_switch('product_feed_' . $product_feeds_format, (bool) $product_feeds_entry['enabled'], $product_feeds_text['label'], $product_feeds_text['help'])
        . $product_feeds_address . (($product_feeds_status !== '') ? '
                    <div class="col-12">
                        <p class="pg-field-help pg-text-meta fg-secondary mb-0">' . $product_feeds_status . '</p>
                    </div>' : '');
}

$product_feeds_notice = '';

if (!$product_feeds_ready) {
    $product_feeds_notice = lang('Product feeds become available once the database upgrade has run.');
} elseif (!defined('ECOMMERCE') || (ECOMMERCE !== true)) {
    $product_feeds_notice = lang('E-commerce is turned off, so no feed is built.');
}

// The feeds switched on and their last build, for the row's line.
$commerce_feeds_on = 0;
$commerce_feeds_built = 0;
$commerce_feeds_count = 0;

foreach ($product_feeds_settings['formats'] as $commerce_feed) {
    if (!$commerce_feed['enabled']) {
        continue;
    }

    $commerce_feeds_on++;

    if ((int) $commerce_feed['built_at'] > $commerce_feeds_built) {
        $commerce_feeds_built = (int) $commerce_feed['built_at'];
        $commerce_feeds_count = (int) $commerce_feed['count'];
    }
}

$commerce_parts['feeds'] = array('html' => '
    <div id="pgset-feeds" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Product Feeds') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">
                    <div class="col-12">
                        <p class="pg-field-help pg-text-meta fg-secondary mb-0">' . lang('Publishes the products of your catalog as files that shopping platforms and AI shopping assistants read. Each feed has its own private address and is built again every day.') . '</p>' . (($product_feeds_notice !== '') ? '
                        <p class="pg-field-help pg-text-meta fg-warning mb-0">' . h($product_feeds_notice) . '</p>' : '') . '
                    </div>' . $product_feeds_rows . pg_commerce_settings_group(lang('Seller')) . '
                    <div class="pg-f-md">
                        <label for="product_feed_seller_name" class="form-label">' . lang('Seller name') . '</label>
                        <input type="text" name="product_feed_seller_name" id="product_feed_seller_name" class="form-control" maxlength="255" value="' . h(($product_feeds_settings['seller_name'] !== '') ? $product_feeds_settings['seller_name'] : (defined('ORGANIZATION_NAME') ? ORGANIZATION_NAME : '')) . '" aria-describedby="product_feed_seller_name_help"/>
                        <div class="form-text" id="product_feed_seller_name_help">' . lang('The name shoppers see as the seller. The OpenAI feed needs it.') . '</div>
                    </div>
                    <div class="pg-f-lg">
                        <label for="product_feed_seller_url" class="form-label">' . lang('Seller website') . '</label>
                        <input type="text" inputmode="url" spellcheck="false" autocapitalize="off" name="product_feed_seller_url" id="product_feed_seller_url" class="form-control" maxlength="2000" value="' . h($product_feeds_settings['seller_url']) . '" placeholder="https://"/>
                    </div>
                    <div class="pg-f-lg">
                        <label for="product_feed_privacy_policy_url" class="form-label">' . lang('Privacy policy address') . '</label>
                        <input type="text" inputmode="url" spellcheck="false" autocapitalize="off" name="product_feed_privacy_policy_url" id="product_feed_privacy_policy_url" class="form-control" maxlength="2000" value="' . h($product_feeds_settings['privacy_policy_url']) . '" placeholder="https://"/>
                    </div>
                    <div class="pg-f-lg">
                        <label for="product_feed_terms_url" class="form-label">' . lang('Terms of sale address') . '</label>
                        <input type="text" inputmode="url" spellcheck="false" autocapitalize="off" name="product_feed_terms_url" id="product_feed_terms_url" class="form-control" maxlength="2000" value="' . h($product_feeds_settings['terms_url']) . '" placeholder="https://" aria-describedby="product_feed_terms_url_help"/>
                        <div class="form-text" id="product_feed_terms_url_help">' . lang('Full addresses starting with https://. The OpenAI feed passes them on with every product.') . '</div>
                    </div>' . pg_commerce_settings_group(lang('Products')) . '
                    <div class="pg-f-md">
                        <label for="product_feed_default_brand" class="form-label">' . lang('Brand for products without one') . '</label>
                        <input type="text" name="product_feed_default_brand" id="product_feed_default_brand" class="form-control" maxlength="255" value="' . h($product_feeds_settings['default_brand']) . '" aria-describedby="product_feed_default_brand_help"/>
                        <div class="form-text" id="product_feed_default_brand_help">' . lang('Usually your own brand. The OpenAI feed leaves out a product with no brand.') . '</div>
                    </div>' . $product_feeds_market . ($product_feeds_groups
                        ? pg_commerce_settings_group_ticks('product_feed_excluded_groups', lang('Leave out these product groups'), $product_feeds_groups, $product_feeds_settings['excluded_group_ids'], lang('A product in a ticked group, or in a group under it, is in no feed.'), lang('Filter groups'))
                        : '
                    <div class="col-12">
                        <p class="form-label pg-field-label mb-1">' . lang('Leave out these product groups') . '</p>
                        <p class="pg-field-help pg-text-meta fg-secondary mb-0">' . lang('No catalog shows a product group yet.') . '</p>
                    </div>') . '
                    <div class="col-12">
                        <p class="pg-field-help pg-text-meta fg-secondary mb-3">' . lang('Changes reach the feeds at the next daily build. A feed you switch on is built when you save.') . '</p>
                        <button type="submit" name="product_feed_build" value="1" class="pg-button pg-button-quiet pg-button-sm"><i class="bi bi-arrow-repeat" aria-hidden="true"></i>' . lang('Save and build now') . '</button>
                    </div>' . $product_feeds_merchant . '
                </div>
            </div>
        </div>
    </div>') + pg_commerce_fold_feeds($commerce_on, $product_feeds_ready, $commerce_feeds_on, $commerce_feeds_built, $commerce_feeds_count, time(), pg_time_format_code(), get_date_format_code());

// ── The rows ──
$commerce_rows = array();

foreach (pg_commerce_fold_order() as $commerce_key) {
    if (isset($commerce_parts[$commerce_key])) {
        $commerce_rows[] = array('key' => $commerce_key) + pg_commerce_fold_face($commerce_key) + $commerce_parts[$commerce_key];
    }
}

$pg_settings_cards[] = $commerce_head . pg_settings_fold($commerce_rows);
