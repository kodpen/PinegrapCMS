<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Panel actions - the store: storefront endpoints, orders, product status,
 * barcodes and offers.
 *
 *   get_installment_options      the checkout's installment table (Iyzipay)
 *   eo_get_installments          the express order widget's installment
 *                                table (Iyzipay)
 *   eo_default_tree              the express order widget's default layout,
 *                                for the designer
 *   eo_required_sections         the express order widget's required
 *                                sections, for the designer
 *   add_to_cart                  adds a product to the visitor's cart
 *   delete_order                 deletes an order
 *   get_cross_sell_items         products ordered together with the given
 *                                ones
 *   get_cross_sell_for_product   cross-sell items for one product,
 *                                same-group fallback
 *   get_delivery_date            the delivery date of a shipping method
 *   get_product                  one product, for the storefront
 *   get_shipping_methods         the shipping methods and their rates for an
 *                                address
 *   update_order                 updates an order
 *   update_product_status        enables or disables products
 *   update_product_group_status  enables or disables product groups
 *   getproductlist               the product groups and their products
 *   get_unselected_products      the products a product group does not hold
 *                                yet
 *   get_product_barcodes         the barcodes of a product
 *   generate_product_barcode     a unique barcode for a product (not saved)
 *   save_product_barcode         adds a barcode to a product
 *   delete_product_barcode       deletes one barcode
 *   bulk_assign_barcodes         assigns barcodes to the chosen products
 *   save_barcode_template        the barcode label template
 *   offer_editor                 the offer editor (edit_offer_f.php)
 *
 * Called by pg_panel_dispatch() (includes/panel/actions.php); see that file
 * for the contract.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_PANEL_ACTIONS')) {
    exit;
}

function pg_panel_get_installment_options($request, $action)
{
    //This options for only for Credit/debit Cart and to get installment table,
    // if supported installment, we output a table with all supported cards and banks installment prices.
    switch (ECOMMERCE_PAYMENT_GATEWAY) {
        case 'Iyzipay':
            //prepare to installment check
            $card_number_request = $request['card'];
            //remove spaces in card number.
            $card_number_without_spaces = str_replace(' ', '', $card_number_request);

            //get total price
            $price = $request['price'];

            //if installment option not activated from site settings than pass to installment check.
            if (ECOMMERCE_IYZIPAY_INSTALLMENT >= 2) {
                // if test or live mode for iyzipay gateway.
                if (ECOMMERCE_PAYMENT_GATEWAY_MODE == 'test') {
                    $payment_gateway_host = 'https://sandbox-api.iyzipay.com';
                } else {
                    $payment_gateway_host = 'https://api.iyzipay.com';
                }
                require_once('includes/iyzipay-php/IyzipayBootstrap.php');
                IyzipayBootstrap::init();
                $card_binNumber = substr($card_number_without_spaces, 0, 6);
                // Conversation ID Digits amount
                $digits = 9;
                // Random Conversation ID
                $conversationid = rand(pow(10, $digits - 1), pow(10, $digits) - 1);
                //config
                $options = new \Iyzipay\Options();
                $options->setApiKey(ECOMMERCE_IYZIPAY_API_KEY);
                $options->setSecretKey(ECOMMERCE_IYZIPAY_SECRET_KEY);
                $options->setBaseUrl($payment_gateway_host);
                $request = new \Iyzipay\Request\RetrieveInstallmentInfoRequest();
                $request->setLocale(strtoupper(lang(array('info' => ''))));//get location from sofware language, where set from software settings
                $request->setConversationId($conversationid);
                $request->setBinNumber($card_binNumber);
                $request->setPrice($price);
                $installmentInfo = \Iyzipay\Model\InstallmentInfo::retrieve($request, $options);
                $result = $installmentInfo->getRawResult();
                $oneinstallment_price = json_decode($result)->installmentDetails[0]->installmentPrices[0]->installmentPrice;
                $oneinstallment_totalprice = json_decode($result)->installmentDetails[0]->installmentPrices[0]->totalPrice;
                $twoinstallment_price = json_decode($result)->installmentDetails[0]->installmentPrices[1]->installmentPrice;
                $twoinstallment_totalprice = json_decode($result)->installmentDetails[0]->installmentPrices[1]->totalPrice;
                $threeinstallment_price = json_decode($result)->installmentDetails[0]->installmentPrices[2]->installmentPrice;
                $threeinstallment_totalprice = json_decode($result)->installmentDetails[0]->installmentPrices[2]->totalPrice;
                $sixinstallment_price = json_decode($result)->installmentDetails[0]->installmentPrices[3]->installmentPrice;
                $sixinstallment_totalprice = json_decode($result)->installmentDetails[0]->installmentPrices[3]->totalPrice;
                $nineinstallment_price = json_decode($result)->installmentDetails[0]->installmentPrices[4]->installmentPrice;
                $nineinstallment_totalprice = json_decode($result)->installmentDetails[0]->installmentPrices[4]->totalPrice;
                $twelveinstallment_price = json_decode($result)->installmentDetails[0]->installmentPrices[5]->installmentPrice;
                $twelveinstallment_totalprice = json_decode($result)->installmentDetails[0]->installmentPrices[5]->totalPrice;

                //create array for response. We will update values with array_replace later. if ajax return 0 value it mean there is no
                $response = array(
                    "monthlytwo" => "0",
                    "totaltwo" => "0",
                    "two_supported" => "0",
                    "two_inst_increase" => "0",
                    "monthlythree" => "0",
                    "totalthree" => "0",
                    "three_supported" => "0",
                    "three_inst_increase" => "0",
                    "monthlysix" => "0",
                    "totalsix" => "0",
                    "six_supported" => "0",
                    "six_inst_increase" => "0",
                    "monthlynine" => "0",
                    "totalnine" => "0",
                    "nine_supported" => "0",
                    "nine_inst_increase" => "0",
                    "monthlytwelve" => "0",
                    "totaltwelve" => "0",
                    "twelve_supported" => "0",
                    "twelve_inst_increase" => "0",
                );


                //if there is result from iyzipay installment check than update array with them.
                if ($result) {

                    if (($twoinstallment_price) && (ECOMMERCE_IYZIPAY_INSTALLMENT >= 2)) {
                        $two_installment_monthly_price = BASE_CURRENCY_SYMBOL . $twoinstallment_price;
                        $two_installment_total_price = BASE_CURRENCY_SYMBOL . $twoinstallment_totalprice;
                        $twoinstallment_increase_price = BASE_CURRENCY_SYMBOL . ($twoinstallment_totalprice - $price);

                        $array_replace = ['monthlytwo' => $two_installment_monthly_price];
                        $response = array_replace($response, $array_replace);

                        $array_replace = ['totaltwo' => $two_installment_total_price];
                        $response = array_replace($response, $array_replace);

                        $array_replace = ['two_supported' => '1'];
                        $response = array_replace($response, $array_replace);

                        $array_replace = ['two_inst_increase' => $twoinstallment_increase_price];
                        $response = array_replace($response, $array_replace);
                    }
                    if (($threeinstallment_price) && (ECOMMERCE_IYZIPAY_INSTALLMENT >= 3)) {
                        $three_installment_monthly_price = BASE_CURRENCY_SYMBOL . $threeinstallment_price;
                        $three_installment_total_price = BASE_CURRENCY_SYMBOL . $threeinstallment_totalprice;
                        $threeinstallment_increase_price = BASE_CURRENCY_SYMBOL . ($threeinstallment_totalprice - $price);

                        $array_replace = ['monthlythree' => $three_installment_monthly_price];
                        $response = array_replace($response, $array_replace);

                        $array_replace = ['totalthree' => $three_installment_total_price];
                        $response = array_replace($response, $array_replace);

                        $array_replace = ['three_supported' => '1'];
                        $response = array_replace($response, $array_replace);

                        $array_replace = ['three_inst_increase' => $threeinstallment_increase_price];
                        $response = array_replace($response, $array_replace);
                    }
                    if (($sixinstallment_price) && (ECOMMERCE_IYZIPAY_INSTALLMENT >= 6)) {
                        $six_installment_monthly_price = BASE_CURRENCY_SYMBOL . $sixinstallment_price;
                        $six_installment_total_price = BASE_CURRENCY_SYMBOL . $sixinstallment_totalprice;
                        $sixinstallment_increase_price = BASE_CURRENCY_SYMBOL . ($sixinstallment_totalprice - $price);

                        $array_replace = ['monthlysix' => $six_installment_monthly_price];
                        $response = array_replace($response, $array_replace);

                        $array_replace = ['totalsix' => $six_installment_total_price];
                        $response = array_replace($response, $array_replace);

                        $array_replace = ['six_supported' => '1'];
                        $response = array_replace($response, $array_replace);

                        $array_replace = ['six_inst_increase' => $sixinstallment_increase_price];
                        $response = array_replace($response, $array_replace);
                    }
                    if (($nineinstallment_price) && (ECOMMERCE_IYZIPAY_INSTALLMENT >= 9)) {
                        $nine_installment_monthly_price = BASE_CURRENCY_SYMBOL . $nineinstallment_price;
                        $nine_installment_total_price = BASE_CURRENCY_SYMBOL . $nineinstallment_totalprice;
                        $nineinstallment_increase_price = BASE_CURRENCY_SYMBOL . ($nineinstallment_totalprice - $price);

                        $array_replace = ['monthlynine' => $nine_installment_monthly_price];
                        $response = array_replace($response, $array_replace);

                        $array_replace = ['totalnine' => $nine_installment_total_price];
                        $response = array_replace($response, $array_replace);

                        $array_replace = ['nine_supported' => '1'];
                        $response = array_replace($response, $array_replace);

                        $array_replace = ['nine_inst_increase' => $nineinstallment_increase_price];
                        $response = array_replace($response, $array_replace);
                    }
                    if (($twelveinstallment_price) && (ECOMMERCE_IYZIPAY_INSTALLMENT >= 12)) {
                        $twelve_installment_monthly_price = BASE_CURRENCY_SYMBOL . $twelveinstallment_price;
                        $twelve_installment_total_price = BASE_CURRENCY_SYMBOL . $twelveinstallment_totalprice;
                        $twelveinstallment_increase_price = BASE_CURRENCY_SYMBOL . ($twelveinstallment_totalprice - $price);

                        $array_replace = ['monthlytwelve' => $twelve_installment_monthly_price];
                        $response = array_replace($response, $array_replace);

                        $array_replace = ['totaltwelve' => $twelve_installment_total_price];
                        $response = array_replace($response, $array_replace);

                        $array_replace = ['twelve_supported' => '1'];
                        $response = array_replace($response, $array_replace);

                        $array_replace = ['twelve_inst_increase' => $twelveinstallment_increase_price];
                        $response = array_replace($response, $array_replace);
                    }


                }
                //return json output
                echo encode_json($response);

            } else {
                //return error json output
                $response = array(
                    'status' => 'error',
                    'message' => 'No Installment Supported'
                );
                echo encode_json($response);
            }
            break;
    }
    exit();
}

function pg_panel_eo_get_installments($request, $action)
{
    // New express order installment endpoint. Returns a forward-compatible
    // table: every installmentNumber Iyzipay reports (typically 1, 2, 3, 6,
    // 9, 12 — but never assumed) up to the operator's ECOMMERCE_IYZIPAY_INSTALLMENT
    // cap, plus card metadata (cardAssociation, cardFamilyName, bankName) so
    // the widget can render brand-aware UI ("Bonus / Garanti — 3
    // installments at ₺X.XX/month, ₺Y.YY total"). Wraps the same SDK call the legacy
    // `get_installment_options` action uses, but doesn't lose entries when
    // Iyzipay returns additional rows (e.g. 4-installment cards).
    // Request: { action: 'eo_get_installments', card: '5528 7900 0000 0008', price: '17.64' }
    // Response on success:
    // {
    // "status": "success",
    // "binNumber": "552879",
    // "cardAssociation": "MASTER_CARD",
    // "cardFamilyName": "Bonus",
    // "bankName": "Garanti Bankası",
    // "cardType": "CREDIT_CARD",
    // "max_allowed": 6,
    // "currency_symbol": "₺",
    // "currency_code": "TRY",
    // "installments": [
    // {"number": 1, "monthly": "17.64", "total": "17.64", "increase": "0.00"},
    // {"number": 3, "monthly": "6.06", "total": "18.18", "increase": "0.54"}
    // ]
    // }
    // Errors return {"status":"error","message":"..."}.
    if (ECOMMERCE_PAYMENT_GATEWAY !== 'Iyzipay') {
        echo encode_json(array('status'=>'error','message'=>'Iyzipay gateway is not active.'));
        exit();
    }
    $eo_inst_card  = isset($request['card'])  ? trim((string)$request['card'])  : '';
    $eo_inst_price = isset($request['price']) ? trim((string)$request['price']) : '';
    // Strip spaces/dashes so a "5528 7900 …" input still resolves to a BIN.
    $eo_inst_bin   = substr(preg_replace('/[^0-9]/', '', $eo_inst_card), 0, 6);
    if (strlen($eo_inst_bin) < 6 || !is_numeric($eo_inst_price) || (float)$eo_inst_price <= 0) {
        echo encode_json(array('status'=>'error','message'=>'card and price are required.'));
        exit();
    }
    $eo_max_inst = defined('ECOMMERCE_IYZIPAY_INSTALLMENT') ? (int)ECOMMERCE_IYZIPAY_INSTALLMENT : 1;
    if ($eo_max_inst < 2) {
        // Single-shot only; no installment offer to display.
        echo encode_json(array(
            'status'         => 'success',
            'binNumber'      => $eo_inst_bin,
            'max_allowed'    => 1,
            'currency_symbol'=> defined('BASE_CURRENCY_SYMBOL') ? html_entity_decode(BASE_CURRENCY_SYMBOL, ENT_QUOTES | ENT_HTML5, 'UTF-8') : '',
            'currency_code'  => defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : '',
            'installments'   => array(array(
                'number' => 1,
                'monthly'=> number_format((float)$eo_inst_price, 2, '.', ''),
                'total'  => number_format((float)$eo_inst_price, 2, '.', ''),
                'increase'=> '0.00',
            )),
        ));
        exit();
    }
    require_once(PG_FUNCTIONS_DIR . '/includes/iyzipay-php/IyzipayBootstrap.php');
    IyzipayBootstrap::init();
    $eo_inst_host = (ECOMMERCE_PAYMENT_GATEWAY_MODE === 'test')
        ? 'https://sandbox-api.iyzipay.com' : 'https://api.iyzipay.com';
    $eo_inst_opts = new \Iyzipay\Options();
    $eo_inst_opts->setApiKey(ECOMMERCE_IYZIPAY_API_KEY);
    $eo_inst_opts->setSecretKey(ECOMMERCE_IYZIPAY_SECRET_KEY);
    $eo_inst_opts->setBaseUrl($eo_inst_host);
    $eo_inst_req = new \Iyzipay\Request\RetrieveInstallmentInfoRequest();
    $eo_inst_req->setLocale(strtoupper(lang(array('info' => ''))));
    $eo_inst_req->setConversationId((string)rand(100000000, 999999999));
    $eo_inst_req->setBinNumber($eo_inst_bin);
    $eo_inst_req->setPrice(sprintf('%01.2lf', (float)$eo_inst_price));
    $eo_inst_resp = \Iyzipay\Model\InstallmentInfo::retrieve($eo_inst_req, $eo_inst_opts);
    $eo_inst_raw  = $eo_inst_resp->getRawResult();
    $eo_inst_data = json_decode((string)$eo_inst_raw, true);
    if (!is_array($eo_inst_data) || ($eo_inst_data['status'] ?? '') !== 'success'
        || empty($eo_inst_data['installmentDetails'][0]['installmentPrices'])) {
        echo encode_json(array(
            'status'  => 'error',
            'message' => isset($eo_inst_data['errorMessage'])
                ? (string)$eo_inst_data['errorMessage']
                : 'No installment data returned.',
        ));
        exit();
    }
    $eo_inst_detail = $eo_inst_data['installmentDetails'][0];
    $eo_inst_list   = array();
    $eo_inst_base   = (float)$eo_inst_price;
    foreach ($eo_inst_detail['installmentPrices'] as $p) {
        $eo_inst_n = (int)($p['installmentNumber'] ?? 0);
        if ($eo_inst_n < 1 || $eo_inst_n > $eo_max_inst) continue;
        $eo_inst_monthly = (float)($p['installmentPrice'] ?? 0);
        $eo_inst_total   = (float)($p['totalPrice']       ?? 0);
        $eo_inst_list[] = array(
            'number'   => $eo_inst_n,
            'monthly'  => number_format($eo_inst_monthly, 2, '.', ''),
            'total'    => number_format($eo_inst_total,   2, '.', ''),
            'increase' => number_format(max(0, $eo_inst_total - $eo_inst_base), 2, '.', ''),
        );
    }
    usort($eo_inst_list, function($a, $b) { return $a['number'] - $b['number']; });
    echo encode_json(array(
        'status'          => 'success',
        'binNumber'       => (string)($eo_inst_detail['binNumber']       ?? $eo_inst_bin),
        'cardAssociation' => (string)($eo_inst_detail['cardAssociation'] ?? ''),
        'cardFamilyName'  => (string)($eo_inst_detail['cardFamilyName']  ?? ''),
        'bankName'        => (string)($eo_inst_detail['bankName']        ?? ''),
        'cardType'        => (string)($eo_inst_detail['cardType']        ?? ''),
        'max_allowed'     => $eo_max_inst,
        // BASE_CURRENCY_SYMBOL is stored as an HTML entity (e.g. `&#8378;`)
        // so it inlines safely into legacy template HTML. The install
        // selector\'s JS uses `.textContent` to set the visible value —
        // raw entities would show LITERALLY ("&#8378;14.35"). Decode
        // here so JSON carries the actual unicode glyph (₺), and
        // textContent renders it correctly.
        'currency_symbol' => defined('BASE_CURRENCY_SYMBOL')
                                ? html_entity_decode(BASE_CURRENCY_SYMBOL, ENT_QUOTES | ENT_HTML5, 'UTF-8')
                                : '',
        'currency_code'   => defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : '',
        'installments'    => $eo_inst_list,
    ));
    exit();
}

function pg_panel_eo_default_tree($request, $action)
{
    // Designer companion endpoints for the express order widget. Both are
    // dictionary-shaped JSON so the designer's JS can consume them without
    // duplicating the PHP source of truth.
    // eo_default_tree       → { status, tree:{…} }       — default layout
    // eo_required_sections  → { status, required:[…], all:[…], labels:{…} }
    if (!function_exists('_eo_default_designer_tree')) {
        echo encode_json(array('status'=>'error','message'=>'_eo_default_designer_tree() unavailable.'));
        exit();
    }
    echo encode_json(array('status' => 'success', 'tree' => _eo_default_designer_tree()));
    exit();
}

function pg_panel_eo_required_sections($request, $action)
{
    $eo_req = function_exists('_eo_required_section_bindings') ? _eo_required_section_bindings() : array();
    // Friendly labels for the designer panel checklist. Keys MUST match
    // the section dictionary built in _render_system_widget_express_order;
    // values prefixed `eo_` to avoid clashing with catalog's binding
    // namespace (e.g. catalog also has a `payment_methods` notion).
    $eo_labels = array(
        'eo_errors_notices'         => lang('Errors / notices (filled by backend)'),
        'eo_cart_items'             => lang('Cart items (filled by backend: table + quantity inputs)'),
        'eo_shipping'               => lang('Shipping address & carrier (filled by backend)'),
        'eo_billing'                => lang('Billing details, whole block (filled by backend)'),
        'eo_billing_country_select' => lang('Country list (filled by backend, 240 countries)'),
        'eo_payment'                => lang('Payment block, whole block (filled by backend)'),
        'eo_payment_methods'        => lang('Payment methods (radio buttons, per site setting)'),
        'eo_installment'            => lang('Installment picker (Iyzipay BIN lookup)'),
        'eo_terms'                  => lang('Terms consent (legacy; the new tree uses a real checkbox + modal)'),
        'eo_saved_cart_link'        => lang('Saved cart link (the visitor can return to the cart with the order #)'),
        'eo_upsell_offers'          => lang('Upsell offers (view_offers.php alert strip)'),
        'eo_applied_offers'         => lang('Applied offers (promotions applied to the cart)'),
        'eo_totals'                 => lang('Order totals (computed by backend: VAT, card surcharge, total)'),
        'eo_submit_button'          => lang('Complete Order button'),
        'eo_update_button'          => lang('Update button (for cart quantities)'),
    );
    $required_full = array();
    foreach ($eo_req as $k => $_v) $required_full[] = 'eo_' . $k;
    echo encode_json(array(
        'status'   => 'success',
        'required' => $required_full,
        'all'      => array_keys($eo_labels),
        'labels'   => $eo_labels,
    ));
    exit();
}

function pg_panel_add_to_cart($request, $action)
{
    require_once(PG_FUNCTIONS_DIR . '/add_to_cart.php');

    $response = add_to_cart($request);

    echo encode_json($response);
    exit();
}

function pg_panel_delete_order($request, $action)
{
    validate_token();

    require_once(PG_FUNCTIONS_DIR . '/delete_order.php');

    $response = delete_order(array('order' => $request['order']));

    echo encode_json($response);
    exit();
}

function pg_panel_get_cross_sell_items($request, $action)
{
    require_once(PG_FUNCTIONS_DIR . '/get_cross_sell_items.php');

    $response = get_cross_sell_items($request);

    echo encode_json($response);
    exit();
}

function pg_panel_get_cross_sell_for_product($request, $action)
{
    // Cross-sell with same-group fallback — designer-facing variant of
    // the legacy get_cross_sell_items. Used by pg_civ_variants.js when
    // the visitor switches variants on a select-type product detail page,
    // so the cross-sell row stays populated even on fresh sites with no
    // order history. Two-tier strategy:
    // • Tier 1: ordered-together products (legacy logic)
    // • Tier 2: same-product-group siblings (excluding the source)
    // Returns the same shape regardless of which tier provided the items.
    // Request shape:
    // action          = 'get_cross_sell_for_product'
    // product[id]     = source product id
    // detail_page_id  = (optional) catalog detail page id for URL prefix
    // count           = (optional) max items, default 4
    $_x_pid    = isset($request['product']['id']) ? (int)$request['product']['id'] : 0;
    $_x_detail = isset($request['detail_page_id']) ? (int)$request['detail_page_id'] : 0;
    $_x_count  = isset($request['count']) ? max(1, min(12, (int)$request['count'])) : 4;
    if ($_x_pid <= 0) {
        echo encode_json(array('status' => 'error', 'message' => 'product[id] required'));
        exit();
    }
    $_x_items = function_exists('_civ_get_cross_sell_items')
        ? _civ_get_cross_sell_items($_x_pid, $_x_detail, $_x_count)
        : array();
    echo encode_json(array('status' => 'success', 'items' => $_x_items));
    exit();
}

function pg_panel_get_delivery_date($request, $action)
{
    // Used to get the estimated delivery date for a recipient and method.
    require_once(PG_FUNCTIONS_DIR . '/shipping.php');

    $response = get_delivery_date($request);

    echo encode_json($response);
    exit();
}

function pg_panel_get_product($request, $action)
{
    // Extended SELECT — adds price/brand/mpn/gtin/weight/shippable/taxable/
    // custom_fields/reward_points/seo_score/timestamp/backorder/address_name
    // alongside the legacy fields (id/name/short_description/full_description/
    // details/code/image_name/inventory/inventory_quantity/out_of_stock_message).
    //
    // Existing custom layouts only read the legacy keys → adding more fields
    // is backward-compatible. Designer-built (catalog_item_view) product
    // detail pages read the extended fields when the visitor picks a new
    // variant from a select-type group, refreshing the bound elements
    // (price, brand, gallery image, etc.) without a full page reload.
    $product_raw = db_item(
        "SELECT
                id,
                name,
                address_name,
                short_description,
                full_description,
                details,
                code,
                image_name,
                inventory,
                inventory_quantity,
                backorder,
                out_of_stock_message,
                price,
                mpn,
                gtin,
                brand,
                weight,
                shippable,
                taxable,
                reward_points,
                seo_score,
                custom_field_1,
                custom_field_2,
                custom_field_3,
                custom_field_4,
                timestamp
            FROM products
            WHERE id = '" . e($request['product']['id']) . "'"
    );



    //if code has ^^image_loop_start^^ and ^^image_url^^ and ^^image_loop_end^^. with these we can make an ease loop
    if (
        (strpos($product_raw['code'], '^^image_url^^') !== false) &&
        (strpos($product_raw['code'], '^^image_loop_start^^') !== false) &&
        (strpos($product_raw['code'], '^^image_loop_end^^') !== false)
    ) {
        //check for image list from products_images_xref
        $item_images = "SELECT product,file_name FROM products_images_xref WHERE product = '" . e($request['product']['id']) . "'";
        $image_results = mysqli_query(db::$con, $item_images) or output_error('Query failed');

        $code_header_position = strpos($product_raw['code'], '^^image_loop_start^');//number
        $code_content_position = strpos($product_raw['code'], '^^image_url^^');
        $code_footer_position = strpos($product_raw['code'], '^^image_loop_end^');//number
        $code_header = substr($product_raw['code'], 0, strpos($product_raw['code'], '^^image_loop_start^'));
        $code_content_raw = substr($product_raw['code'], (strpos($product_raw['code'], '^^image_loop_start^') + 20), (strpos($product_raw['code'], '^^image_loop_end^') - strpos($product_raw['code'], '^^image_loop_start^') - 20));
        $code_footer = substr($product_raw['code'], strpos($product_raw['code'], '^^image_loop_end^') + 18);
        $code_image_alt = false;
        if (strpos($product_raw['code'], '^^image_alt^^') !== false) {
            $code_image_alt = true;
        }

        //if product image xref or product group  xref exist. this mean this selected multiple product image
        if (mysqli_num_rows($image_results) != 0) {

            //if there is image alt tag
            if ($code_image_alt !== false) {
                $code_content = str_replace("^^image_url^^", PATH . encode_url_path($product_raw['image_name']), str_replace("^^image_alt^^", $product_raw['short_description'], $code_content_raw));
                //if there is no image alt tag
            } else {
                $code_content = str_replace("^^image_url^^", PATH . encode_url_path($product_raw['image_name']), $code_content_raw);

            }
            while ($image = mysqli_fetch_assoc($image_results)) {
                //if there is image alt tag
                if ($code_image_alt !== false) {
                    $code_content .= str_replace("^^image_url^^", PATH . encode_url_path($image['file_name']), str_replace("^^image_alt^^", $product_raw['short_description'], $code_content_raw));
                    //if there is no image alt tag
                } else {
                    $code_content .= str_replace("^^image_url^^", PATH . encode_url_path($image['file_name']), $code_content_raw);
                }
            }
            $product_replace = ['code' => $code_header . $code_content . $code_footer];
            $product = array_replace($product_raw, $product_replace);
        } else {
            //else if less an image selected and only one image selected, but there is code for action we output single image
            if ($product_raw['image_name']) {
                //if there is image alt tag
                if ($code_image_alt !== false) {
                    $code_single_content = str_replace("^^image_url^^", PATH . encode_url_path($product_raw['image_name']), str_replace("^^image_alt^^", $product_raw['short_description'], $code_content_raw));
                    //if there is no image alt tag
                } else {
                    $code_single_content = str_replace("^^image_url^^", PATH . encode_url_path($product_raw['image_name']), $code_content_raw);
                }
                $product_replace = ['code' => $code_header . $code_single_content . $code_footer];
                $product = array_replace($product_raw, $product_replace);
            } else {
                $product = $product_raw;
            }
        }
    } else {
        $product = $product_raw;
    }

    // If a product was not found then respond with an error.
    if (!$product) {
        $response = array(
            'status' => 'error',
            'message' => 'Product could not be found.'
        );

        echo encode_json($response);
        exit();
    }

    // ── Extended response: gallery + computed fields ─────────────────
    // Backward-compat: the legacy custom-layout 'code' expansion above
    // ALREADY uses products_images_xref; we just expose the same gallery
    // as a clean array on the response so designer-built (catalog_item_view)
    // detail pages can refresh the carousel without re-parsing 'code'.
    //
    // Also exposes:
    //   • price_cents / price (raw integer cents → matches DB unit)
    //   • price_decimal      (cents/100 with 2 decimals — for arithmetic)
    //   • price_formatted    (currency-symbol-prefixed display string)
    //   • image_url          (full URL to main image, or '' when missing)
    //   • detail_url         (full URL to the product's detail page,
    //                          when products.address_name is set)
    //   • gallery[]          (each entry: {url, alt})
    //   • out_of_stock       (bool — derived from inventory rules)
    $_pg_pid = (int)$product['id'];
    $_pg_gallery = array();
    $_pg_main = (string)($product['image_name'] ?? '');
    if ($_pg_main !== '') {
        $_pg_gallery[] = array(
            'url' => PATH . encode_url_path($_pg_main),
            'alt' => (string)$product['short_description'],
        );
    }
    $_pg_xref = mysqli_query(db::$con,
        "SELECT file_name FROM products_images_xref WHERE product = '" . $_pg_pid . "'"
    );
    if ($_pg_xref) {
        while ($_pg_row = mysqli_fetch_assoc($_pg_xref)) {
            if (!empty($_pg_row['file_name'])) {
                $_pg_gallery[] = array(
                    'url' => PATH . encode_url_path($_pg_row['file_name']),
                    'alt' => (string)$product['short_description'],
                );
            }
        }
    }
    // Pricing — effective vs. sticker.
    // `original_*` keeps the pre-discount price (sticker, for strike-
    // through display in the catalog_item_view variant chooser).
    // `price` / `price_formatted` reflect the EFFECTIVE price after any
    // active order-scope offer applies. `has_discount` (1/0) lets the
    // designer's "indirim" badge / strike-through hide-when-empty.
    $_pg_orig_cents  = (int)($product['price'] ?? 0);
    $_pg_disc_map    = function_exists('get_discounted_product_prices')
        ? get_discounted_product_prices() : array();
    if (!is_array($_pg_disc_map)) $_pg_disc_map = array();
    $_pg_price_cents = isset($_pg_disc_map[$_pg_pid]) ? (int)$_pg_disc_map[$_pg_pid] : $_pg_orig_cents;
    $_pg_price_dec   = $_pg_price_cents / 100;
    $_pg_orig_dec    = $_pg_orig_cents / 100;
    $_pg_has_disc    = ($_pg_price_cents < $_pg_orig_cents) ? 1 : 0;
    $_pg_save_cents  = $_pg_orig_cents - $_pg_price_cents;
    $_pg_save_pct    = ($_pg_orig_cents > 0 && $_pg_save_cents > 0)
        ? (int)round(($_pg_save_cents / $_pg_orig_cents) * 100) : 0;
    $_pg_curr        = defined('VISITOR_CURRENCY_SYMBOL') ? VISITOR_CURRENCY_SYMBOL : '$';
    $_pg_inv_tracked = !empty($product['inventory']) ? 1 : 0;
    $_pg_inv_qty     = (int)($product['inventory_quantity'] ?? 0);
    $_pg_backorder   = !empty($product['backorder']) ? 1 : 0;
    $_pg_oos         = ($_pg_inv_tracked && $_pg_inv_qty <= 0 && !$_pg_backorder) ? 1 : 0;

    // Detail URL — only when products.address_name is set AND there's at
    // least one catalog_detail_pages row (legacy resolver). For now we
    // emit the address_name slug; the consumer can prefix with their
    // detail page path.
    $_pg_addr = (string)($product['address_name'] ?? '');

    $product['price']           = number_format($_pg_price_dec, 2, '.', '');
    $product['price_cents']     = $_pg_price_cents;
    $product['price_decimal']   = number_format($_pg_price_dec, 2, '.', '');
    $product['price_formatted'] = pg_visitor_money($_pg_price_dec, $_pg_curr);
    $product['original_price']           = number_format($_pg_orig_dec, 2, '.', '');
    $product['original_price_cents']     = $_pg_orig_cents;
    $product['original_price_formatted'] = pg_visitor_money($_pg_orig_dec, $_pg_curr);
    $product['has_discount']             = (string)$_pg_has_disc;
    $product['discount_amount']          = $_pg_save_cents > 0 ? number_format($_pg_save_cents / 100, 2, '.', '') : '';
    $product['discount_amount_formatted']= $_pg_save_cents > 0 ? pg_visitor_money($_pg_save_cents / 100, $_pg_curr) : '';
    $product['discount_percent']         = $_pg_save_pct > 0 ? (string)$_pg_save_pct : '';
    $product['image_url']       = $_pg_main !== '' ? PATH . encode_url_path($_pg_main) : '';
    $product['address_name']    = $_pg_addr;
    $product['gallery']         = $_pg_gallery;
    $product['gallery_count']   = count($_pg_gallery);
    $product['out_of_stock']    = $_pg_oos;
    $product['quantity_available'] = ($_pg_inv_tracked && !$_pg_backorder) ? $_pg_inv_qty : null;

    $response = array(
        'status' => 'success',
        'product' => $product
    );

    echo encode_json($response);
    exit();
}

function pg_panel_get_shipping_methods($request, $action)
{
    // Used to get the appropriate shipping methods for the shipping address and arrival date
    // that customer selected on express order
    require_once(PG_FUNCTIONS_DIR . '/shipping.php');

    $response = get_shipping_methods($request);

    echo encode_json($response);
    exit();
}

function pg_panel_update_order($request, $action)
{
    // Update shipping & tracking info for completed order.
    validate_token();

    require_once(PG_FUNCTIONS_DIR . '/update_order.php');

    $response = update_order(array('order' => $request['order']));

    echo encode_json($response);
    exit();
}

function pg_panel_update_product_status($request, $action)
{
    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    validate_token();

    $user = validate_user();
    validate_ecommerce_access($user);

    if ($request['status'] == 'enabled') {
        $enabled = 1;
    } else {
        $enabled = 0;
    }

    db(
        "UPDATE products
            SET
                enabled = '$enabled',
                user = '" . USER_ID . "',
                timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . e($request['id']) . "'"
    );

    $product = db_item(
        "SELECT name, short_description
            FROM products
            WHERE id = '" . e($request['id']) . "'"
    );

    log_activity('product (' . $product['name'] . ' - ' . $product['short_description'] . ') was ' . $request['status']);

    $response = array('status' => 'success');

    echo encode_json($response);
    exit();
}

function pg_panel_update_product_group_status($request, $action)
{
    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    validate_token();

    $user = validate_user();
    validate_ecommerce_access($user);

    require_once(PG_FUNCTIONS_DIR . '/update_product_group_status.php');

    $items = update_product_group_status(array(
        'id' => $request['id'],
        'status' => $request['status']
    ));

    $response = array(
        'status' => 'success',
        'items' => $items
    );

    echo encode_json($response);
    exit();
}

function pg_panel_getproductlist($request, $action)
{
    $data = array();
    /* get results for just this screen*/
    $product_groups = db("SELECT 
        id,
        name,
        enabled,
        parent_id,
        short_description,
        full_description,
        details,
        code,
        keywords,
        image_name,
        address_name,
        title,
        meta_description,
        meta_keywords,
        seo_score,
        attributes,
        timestamp
        FROM product_groups 
        WHERE product_groups.display_type != 'browse'");
    $count_product_groups = db("SELECT count(*) FROM product_groups  WHERE product_groups.display_type != 'browse'");

    foreach ($product_groups as $product_group) {
        //$products = db("SELECT
        //     products.id as id,
        //     products.name as name,
        //     products.enabled,
        //     products.image_name  as image_name,
        //     products.inventory as inventory,
        //     products.inventory_quantity as inventory_quantity,
        //     products.short_description as short_description,
        //     products.price as price,
        //     products.taxable as taxable,
        //     products.form_name as form_name,
        //     products.out_of_stock as out_of_stock,
        //     products.out_of_stock_timestamp as out_of_stock_timestamp,
        //     products.timestamp as timestamp
        //FROM products_groups_xref
        //LEFT JOIN products on products.id = product
        //WHERE product_group = '" . e($product_group['id']) . "'");
        //foreach($products as $product) {}

        array_push($data, array(
            'id' => $product_group['id'],
            'name' => $product_group['name'],
            'enabled' => $product_group['enabled'],
            'short_description' => $product_group['short_description'],
            'image_name' => $product_group['image_name'],
            'timestamp' => get_relative_time(array('timestamp' => $product_group['timestamp']))
        ));


    }


    //return success json output
    $response = array(
        'status' => 'success',
        'title' => lang(array('string' => 'List of {var:1}', 'vars' => array(lang('Product(s)')))),
        'max' => $count_product_groups,
        'data' => $data
    );

    echo respond($response);
}

function pg_panel_get_unselected_products($request, $action)
{
    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    $user = validate_user();
    validate_ecommerce_access($user);

    $product_group_id = (int) ($request['product_group_id'] ?? 0);
    $offset = max(0, (int) ($request['offset'] ?? 0));
    $limit = 100;

    // Get IDs of products already in this group.
    $selected_ids = db_values(
        "SELECT product FROM products_groups_xref WHERE product_group = '$product_group_id'"
    );

    $exclude_sql = '';
    if ($selected_ids) {
        $exclude_sql = "AND id NOT IN (" . implode(',', array_map('intval', $selected_ids)) . ")";
    }

    // Products in the Recycle Bin are not offered (edit_product_group.php).
    $exclude_sql .= pg_catalog_bin_live_sql('products');

    $show_image = (bool) ECOMMERCE_SHOW_PRODUCT_IMAGES;

    $products = db_items(
        "SELECT id, name, enabled, short_description, image_name, price
             FROM products
             WHERE 1 $exclude_sql
             ORDER BY timestamp DESC
             LIMIT $limit OFFSET $offset"
    );

    $total_unselected = (int) db_value(
        "SELECT COUNT(*) FROM products WHERE 1 $exclude_sql"
    );

    // The pictures behind each product's cover, for the image viewer, which
    // the screen that asked for these rows has already loaded.
    $more_images = $show_image ? pg_product_more_images(array_column($products, 'id')) : array();

    $rows_html = '';
    foreach ($products as $product) {
        $product['price'] = $product['price'] / 100;
        $status_class = $product['enabled'] == 1 ? 'text-success' : 'text-danger';

        $output_image_column = '';
        if ($show_image) {
            $output_image_column = pg_product_image_cell('product-' . (int) $product['id'], $product['image_name'], $product['name'], $product['short_description'],
                isset($more_images[(int) $product['id']]) ? $more_images[(int) $product['id']] : array());
        }

        $rows_html .=
            '<tr id="' . $product['id'] . '">' .
            '<td class="select-all align-middle text-start"><input class="form-check-input" type="checkbox" name="products[]" value="' . $product['id'] . '"/></td>' .
            '<td class="align-middle text-start"><button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2" title="' . lang('Edit') . '" onclick="window.location.href=\'edit_product.php?id=' . $product['id'] . '\'"><i class="bi bi-pencil"></i></button></td>' .
            $output_image_column .
            '<td class="chart_label align-middle ' . $status_class . '">' . h($product['name']) . '</td>' .
            '<td class="align-middle ' . $status_class . '">' . h($product['short_description']) . '</td>' .
            '<td class="align-middle text-end">' . prepare_amount($product['price']) . '</td>' .
            '<td class="align-middle"><input class="form-control text-end" type="text" name="sort_order_product_' . $product['id'] . '" size="5" value="" maxlength="4" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0" style="text-align: right;width:60px;" /></td>' .
            '<td></td>' .
            '</tr>';
    }

    $has_more = ($offset + count($products)) < $total_unselected;

    $response = array(
        'status' => 'success',
        'rows' => $rows_html,
        'has_more' => $has_more,
        'loaded' => count($products),
        'total' => $total_unselected
    );

    echo encode_json($response);
    exit();
}

function pg_panel_get_product_barcodes($request, $action)
{
    // Barcode: get all barcodes for a product

    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    // Exempt from the general gate, which answered a request without a
    // session this way; the same answer is kept.
    if (!USER_LOGGED_IN) {
        respond(array(
            'status' => 'error',
            'message' => 'Invalid login.'
        ));
    }
    $user = validate_user();
    // The product screens' own rule, answered in JSON: roles 0-2, or a
    // basic user with manage_ecommerce.
    if (($user['role'] > 2) && ($user['manage_ecommerce'] != true)) {
        log_activity(lang('access denied to commerce'), $_SESSION['sessionusername']);
        respond(array(
            'status' => 'error',
            'message' => lang('Access denied')));
    }
    validate_token();

    $product_id = (int) ($request['product_id'] ?? 0);
    if (!$product_id)
        respond(array('status' => 'error', 'message' => lang('Product not found.')));

    $rows = db_items(
        "SELECT id, barcode, barcode_type, created_at
             FROM product_barcodes
             WHERE product_id = '" . e($product_id) . "'
             ORDER BY created_at DESC"
    );

    respond(array('status' => 'success', 'barcodes' => $rows ?: array()));
}

function pg_panel_generate_product_barcode($request, $action)
{
    // Barcode: generate a unique barcode for a product

    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    // Exempt from the general gate, which answered a request without a
    // session this way; the same answer is kept.
    if (!USER_LOGGED_IN) {
        respond(array(
            'status' => 'error',
            'message' => 'Invalid login.'
        ));
    }
    $user = validate_user();
    // The product screens' own rule, answered in JSON: roles 0-2, or a
    // basic user with manage_ecommerce.
    if (($user['role'] > 2) && ($user['manage_ecommerce'] != true)) {
        log_activity(lang('access denied to commerce'), $_SESSION['sessionusername']);
        respond(array(
            'status' => 'error',
            'message' => lang('Access denied')));
    }
    validate_token();

    $product_id = (int) ($request['product_id'] ?? 0);
    $barcode_type = trim($request['barcode_type'] ?? 'CODE128');
    if (!$product_id)
        respond(array('status' => 'error', 'message' => lang('Product not found.')));

    $attempts = 0;
    do {
        if ($barcode_type === 'EAN13') {
            $digits = '';
            for ($i = 0; $i < 12; $i++)
                $digits .= rand(0, 9);
            $sum = 0;
            for ($i = 0; $i < 12; $i++)
                $sum += ($i % 2 === 0 ? 1 : 3) * (int) $digits[$i];
            $barcode = $digits . ((10 - ($sum % 10)) % 10);
        } elseif ($barcode_type === 'UPC') {
            $digits = '';
            for ($i = 0; $i < 11; $i++)
                $digits .= rand(0, 9);
            $sum = 0;
            for ($i = 0; $i < 11; $i++)
                $sum += ($i % 2 === 0 ? 3 : 1) * (int) $digits[$i];
            $barcode = $digits . ((10 - ($sum % 10)) % 10);
        } else {
            $barcode = str_pad($product_id, 5, '0', STR_PAD_LEFT) . str_pad(rand(0, 9999999), 7, '0', STR_PAD_LEFT);
        }
        $exists = db_value("SELECT COUNT(*) FROM product_barcodes WHERE barcode = '" . e($barcode) . "'");
        $attempts++;
    } while ($exists && $attempts < 20);

    if ($exists)
        respond(array('status' => 'error', 'message' => lang('Could not generate a unique barcode. Please try again.')));

    respond(array('status' => 'success', 'barcode' => $barcode, 'barcode_type' => $barcode_type));
}

function pg_panel_save_product_barcode($request, $action)
{
    // Barcode: save a new barcode for a product (always inserts)

    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    // Exempt from the general gate, which answered a request without a
    // session this way; the same answer is kept.
    if (!USER_LOGGED_IN) {
        respond(array(
            'status' => 'error',
            'message' => 'Invalid login.'
        ));
    }
    $user = validate_user();
    // The product screens' own rule, answered in JSON: roles 0-2, or a
    // basic user with manage_ecommerce.
    if (($user['role'] > 2) && ($user['manage_ecommerce'] != true)) {
        log_activity(lang('access denied to commerce'), $_SESSION['sessionusername']);
        respond(array(
            'status' => 'error',
            'message' => lang('Access denied')));
    }
    validate_token();

    $product_id = (int) ($request['product_id'] ?? 0);
    $barcode = trim($request['barcode'] ?? '');
    $barcode_type = trim($request['barcode_type'] ?? 'CODE128');

    if (!$product_id)
        respond(array('status' => 'error', 'message' => lang('Product not found.')));
    if ($barcode === '')
        respond(array('status' => 'error', 'message' => lang('Barcode value is required.')));

    if ($barcode_type === 'EAN13' && (!ctype_digit($barcode) || strlen($barcode) !== 13)) {
        respond(array('status' => 'error', 'message' => lang('EAN-13 barcode must be exactly 13 digits.')));
    }
    if ($barcode_type === 'UPC' && (!ctype_digit($barcode) || strlen($barcode) !== 12)) {
        respond(array('status' => 'error', 'message' => lang('UPC-A barcode must be exactly 12 digits.')));
    }

    // Barcode must be globally unique
    $conflict = db_value("SELECT COUNT(*) FROM product_barcodes WHERE barcode = '" . e($barcode) . "'");
    if ($conflict)
        respond(array('status' => 'error', 'message' => lang('This barcode is already assigned to another product.')));

    $now = date('Y-m-d H:i:s');
    db("INSERT INTO product_barcodes (product_id, barcode, barcode_type, created_at, updated_at)
            VALUES ('" . e($product_id) . "', '" . e($barcode) . "', '" . e($barcode_type) . "', '" . e($now) . "', '" . e($now) . "')");

    $new_id = db_value("SELECT LAST_INSERT_ID()");
    log_activity(lang(array('string' => 'Saved barcode {var:1} for product #{var:2}.', 'vars' => array($barcode, $product_id))));
    respond(array('status' => 'success', 'message' => lang('Barcode saved.'), 'id' => (int) $new_id, 'barcode' => $barcode, 'barcode_type' => $barcode_type));
}

function pg_panel_delete_product_barcode($request, $action)
{
    // Barcode: delete a single barcode row by id

    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    // Exempt from the general gate, which answered a request without a
    // session this way; the same answer is kept.
    if (!USER_LOGGED_IN) {
        respond(array(
            'status' => 'error',
            'message' => 'Invalid login.'
        ));
    }
    $user = validate_user();
    // The product screens' own rule, answered in JSON: roles 0-2, or a
    // basic user with manage_ecommerce.
    if (($user['role'] > 2) && ($user['manage_ecommerce'] != true)) {
        log_activity(lang('access denied to commerce'), $_SESSION['sessionusername']);
        respond(array(
            'status' => 'error',
            'message' => lang('Access denied')));
    }
    validate_token();

    $id = (int) ($request['id'] ?? 0);
    $product_id = (int) ($request['product_id'] ?? 0);
    if (!$id || !$product_id)
        respond(array('status' => 'error', 'message' => lang('Product not found.')));

    db("DELETE FROM product_barcodes WHERE id = '" . e($id) . "' AND product_id = '" . e($product_id) . "'");
    log_activity(lang(array('string' => 'Deleted barcode #{var:1} for product #{var:2}.', 'vars' => array($id, $product_id))));
    respond(array('status' => 'success', 'message' => lang('Barcode deleted.')));
}

function pg_panel_bulk_assign_barcodes($request, $action)
{
    // Barcode: bulk assign barcodes to selected products

    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    // Exempt from the general gate, which answered a request without a
    // session this way; the same answer is kept.
    if (!USER_LOGGED_IN) {
        respond(array(
            'status' => 'error',
            'message' => 'Invalid login.'
        ));
    }
    $user = validate_user();
    // The product screens' own rule, answered in JSON: roles 0-2, or a
    // basic user with manage_ecommerce.
    if (($user['role'] > 2) && ($user['manage_ecommerce'] != true)) {
        log_activity(lang('access denied to commerce'), $_SESSION['sessionusername']);
        respond(array(
            'status' => 'error',
            'message' => lang('Access denied')));
    }
    validate_token();

    $product_ids = isset($request['product_ids']) ? (array) $request['product_ids'] : array();
    $barcode_type = trim($request['barcode_type'] ?? BARCODE_DEFAULT_TYPE);
    if (empty($product_ids))
        respond(array('status' => 'error', 'message' => lang('No products selected.')));

    $assigned = 0;
    $skipped = 0;
    $now = date('Y-m-d H:i:s');

    foreach ($product_ids as $pid) {
        $pid = (int) $pid;
        if (!$pid)
            continue;

        // Generate unique barcode
        $barcode = '';
        $attempts = 0;
        do {
            if ($barcode_type === 'EAN13') {
                $digits = '';
                for ($i = 0; $i < 12; $i++)
                    $digits .= rand(0, 9);
                $sum = 0;
                for ($i = 0; $i < 12; $i++)
                    $sum += ($i % 2 === 0 ? 1 : 3) * (int) $digits[$i];
                $barcode = $digits . ((10 - ($sum % 10)) % 10);
            } elseif ($barcode_type === 'UPC') {
                $digits = '';
                for ($i = 0; $i < 11; $i++)
                    $digits .= rand(0, 9);
                $sum = 0;
                for ($i = 0; $i < 11; $i++)
                    $sum += ($i % 2 === 0 ? 3 : 1) * (int) $digits[$i];
                $barcode = $digits . ((10 - ($sum % 10)) % 10);
            } else {
                $barcode = str_pad($pid, 5, '0', STR_PAD_LEFT) . str_pad(rand(0, 9999999), 7, '0', STR_PAD_LEFT);
            }
            $exists = db_value("SELECT COUNT(*) FROM product_barcodes WHERE barcode = '" . e($barcode) . "'");
            $attempts++;
        } while ($exists && $attempts < 30);

        if ($exists) {
            $skipped++;
            continue;
        }

        db("INSERT INTO product_barcodes (product_id, barcode, barcode_type, created_at, updated_at)
                VALUES ('" . e($pid) . "', '" . e($barcode) . "', '" . e($barcode_type) . "', '" . e($now) . "', '" . e($now) . "')");
        $assigned++;
    }

    log_activity(lang(array('string' => '{var:1} barcode(s) assigned.', 'vars' => array($assigned))));
    respond(array(
        'status' => 'success',
        'assigned' => $assigned,
        'skipped' => $skipped,
        'message' => lang(array('string' => '{var:1} barcode(s) assigned.', 'vars' => array($assigned)))
    ));
}

function pg_panel_save_barcode_template($request, $action)
{
    // Barcode: save global label template

    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    // Exempt from the general gate, which answered a request without a
    // session this way; the same answer is kept.
    if (!USER_LOGGED_IN) {
        respond(array(
            'status' => 'error',
            'message' => 'Invalid login.'
        ));
    }
    $user = validate_user();
    // The product screens' own rule, answered in JSON: roles 0-2, or a
    // basic user with manage_ecommerce.
    if (($user['role'] > 2) && ($user['manage_ecommerce'] != true)) {
        log_activity(lang('access denied to commerce'), $_SESSION['sessionusername']);
        respond(array(
            'status' => 'error',
            'message' => lang('Access denied')));
    }
    validate_token();

    $template = isset($request['template']) ? trim($request['template']) : '';
    if ($template !== '' && json_decode($template) === null) {
        respond(array('status' => 'error', 'message' => lang('Invalid template data.')));
    }
    db("UPDATE config SET barcode_label_template = '" . e($template) . "' LIMIT 1");
    log_activity(lang('Updated barcode label template.'));
    respond(array('status' => 'success', 'message' => lang('Template saved.')));
}

function pg_panel_offer_editor($request, $action)
{
    // Offer editor (edit_offer.php): load, save, toggle, delete, cleanup.
    // The sub-actions live in their own include and respond themselves.
    require_once(PG_FUNCTIONS_DIR . '/edit_offer_f.php');
    pg_offer_editor_handle($request);
}
