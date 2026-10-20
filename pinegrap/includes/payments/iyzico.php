<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Payments - iyzico, two drivers in one file:
 *
 *   iyzico           (card)      the Payment Gateway value "Iyzipay": a card
 *                                payment, direct or with 3-D Secure, in up to
 *                                ECOMMERCE_IYZIPAY_INSTALLMENT instalments, in
 *                                the protected currency when the store has one
 *   pay_with_iyzico  (redirect)  the payment method "Pay With Iyzico": the
 *                                visitor pays on iyzico's own page and comes back
 *
 * Both keep their settings in the config columns the Ecommerce card has always
 * drawn (ECOMMERCE_IYZIPAY_*, ECOMMERCE_PAY_WITH_IYZICO), so they declare no
 * fields. Every request is built with the same values and in the same units as
 * the gateway code in submit_order.php, view_order.php and
 * process_order_cancellation() has always sent.
 *
 * The bank's answer to a 3-D Secure payment comes back to submit_order.php
 * (mode iyzipay_threedsecure_return), which finishes the payment itself and
 * reads the row the start of the payment keeps in iyzipay_3ds_state; so the
 * card driver writes that row and defines no complete().
 *
 * Each operation is a thin wrapper around a *_with() function that takes the
 * settings (and, for the 3-D Secure row, the writer) as arguments, so the
 * whole flow can run against a stand-in for iyzico's HTTP client.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/* ---------------------------------------------------------------------------
   iyzico (card)
   --------------------------------------------------------------------------- */

function pg_payment_iyzico_capabilities()
{
    return array('installments', 'partial_refund');
}

function pg_payment_iyzico_authorize($order, $amount, $payment)
{
    return pg_payment_iyzico_authorize_with($order, $amount, $payment, pg_payment_iyzico_settings(true), 'pg_payment_iyzico_threeds_keep');
}

function pg_payment_iyzico_refund($order, $amount, $lines)
{
    return pg_payment_iyzico_refund_with($order, $amount, pg_payment_iyzico_settings());
}

function pg_payment_iyzico_cancel($order, $transaction_id)
{
    return pg_payment_iyzico_cancel_with($order, $transaction_id, pg_payment_iyzico_settings());
}

/* ---------------------------------------------------------------------------
   Pay With Iyzico (redirect)
   --------------------------------------------------------------------------- */

function pg_payment_pay_with_iyzico_capabilities()
{
    return array('installments', 'partial_refund');
}

function pg_payment_pay_with_iyzico_authorize($order, $amount, $payment)
{
    return pg_payment_pay_with_iyzico_authorize_with($order, $amount, $payment, pg_payment_iyzico_settings());
}

function pg_payment_pay_with_iyzico_complete($order, $amount, $return, $state)
{
    return pg_payment_pay_with_iyzico_complete_with($order, $amount, $return, $state, pg_payment_iyzico_settings());
}

function pg_payment_pay_with_iyzico_refund($order, $amount, $lines)
{
    return pg_payment_iyzico_refund_with($order, $amount, pg_payment_iyzico_settings());
}

function pg_payment_pay_with_iyzico_cancel($order, $transaction_id)
{
    return pg_payment_iyzico_cancel_with($order, $transaction_id, pg_payment_iyzico_settings());
}

/* ---------------------------------------------------------------------------
   Settings and the SDK
   --------------------------------------------------------------------------- */

/**
 * The store's iyzico settings, from the config columns.
 *
 * @param bool $with_rate also read the protected currency's exchange rate (a
 *                        query), which only a payment needs
 * @return array api_key, secret_key, base_url, locale, threeds (bool),
 *               installments (the highest number accepted, as configured),
 *               currency, protected_currency ('' when none), protected_rate
 */
function pg_payment_iyzico_settings($with_rate = false)
{
    $constant = function ($name, $default) {
        return defined($name) ? constant($name) : $default;
    };

    $settings = array(
        'api_key'            => (string) $constant('ECOMMERCE_IYZIPAY_API_KEY', ''),
        'secret_key'         => (string) $constant('ECOMMERCE_IYZIPAY_SECRET_KEY', ''),
        'base_url'           => ($constant('ECOMMERCE_PAYMENT_GATEWAY_MODE', '') === 'test') ? 'https://sandbox-api.iyzipay.com' : 'https://api.iyzipay.com',
        'locale'             => strtoupper((string) lang(array('info' => ''))),
        'threeds'            => (bool) $constant('ECOMMERCE_IYZIPAY_THREEDS', false),
        'installments'       => $constant('ECOMMERCE_IYZIPAY_INSTALLMENT', 1),
        'currency'           => (string) $constant('BASE_CURRENCY_CODE', ''),
        'protected_currency' => '',
        'protected_rate'     => 0.0,
    );

    // The protected currency is used only when every switch says so, exactly
    // as the checkout totals (get_express_order.php) decide it.
    if (
        defined('ECOMMERCE_MULTICURRENCY')
        && defined('ENABLE_IYZIPAY_PROTECTED_CURRENCY')
        && defined('IYZIPAY_PROTECTED_CURRENCY_CODE')
        && (ECOMMERCE_MULTICURRENCY === true)
        && (ENABLE_IYZIPAY_PROTECTED_CURRENCY === true)
        && (IYZIPAY_PROTECTED_CURRENCY_CODE !== '')
        && (IYZIPAY_PROTECTED_CURRENCY_CODE !== $settings['currency'])
    ) {
        $settings['protected_currency'] = (string) IYZIPAY_PROTECTED_CURRENCY_CODE;

        if ($with_rate) {
            $rate = db("SELECT exchange_rate FROM currencies WHERE code = '" . e($settings['protected_currency']) . "'");
            $settings['protected_rate'] = is_numeric($rate) ? (float) $rate : 0.0;
        }
    }

    return $settings;
}

function pg_payment_iyzico_sdk()
{
    require_once(PG_FUNCTIONS_DIR . '/includes/iyzipay-php/IyzipayBootstrap.php');

    if (!class_exists('Iyzipay\Options', false)) {
        IyzipayBootstrap::init();
    }
}

/**
 * @param array $settings pg_payment_iyzico_settings()
 * @return \Iyzipay\Options
 */
function pg_payment_iyzico_options($settings)
{
    pg_payment_iyzico_sdk();

    $options = new \Iyzipay\Options();
    $options->setApiKey($settings['api_key']);
    $options->setSecretKey($settings['secret_key']);
    $options->setBaseUrl($settings['base_url']);

    return $options;
}

/**
 * The conversation id iyzico echoes back: nine random digits.
 *
 * @return int
 */
function pg_payment_iyzico_conversation_id()
{
    return rand(100000000, 999999999);
}

/**
 * The address of whoever is making the request, for a refund or a cancel.
 *
 * @return string
 */
function pg_payment_iyzico_request_ip()
{
    return isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
}

/**
 * One value of the orders row, '' when the row does not have it.
 *
 * @param array  $order
 * @param string $name
 * @return mixed
 */
function pg_payment_iyzico_order_value($order, $name)
{
    return (is_array($order) && array_key_exists($name, $order)) ? $order[$name] : '';
}

/**
 * The Turkish identity number iyzico requires: the first custom billing field
 * when it is a number, else iyzico's placeholder.
 *
 * @param array $order
 * @return string
 */
function pg_payment_iyzico_identity_number($order)
{
    $custom_field_1 = pg_payment_iyzico_order_value($order, 'custom_field_1');

    if ($custom_field_1 && is_numeric($custom_field_1)) {
        return substr($custom_field_1, 0, 11);
    }

    return '11111111111';
}

/**
 * The buyer and the billing address, from the orders row.
 *
 * @param array  $order
 * @param array  $payment pg_payment_iyzico_authorize_with()
 * @return array buyer, billing (\Iyzipay\Model\Buyer, \Iyzipay\Model\Address)
 */
function pg_payment_iyzico_buyer($order, $payment)
{
    $value = function ($name) use ($order) {
        return pg_payment_iyzico_order_value($order, $name);
    };

    $customer_id = isset($payment['customer_id']) ? $payment['customer_id'] : '';

    $buyer = new \Iyzipay\Model\Buyer();
    $buyer->setId($customer_id ? $customer_id : 'Guest');
    $buyer->setName($value('billing_first_name'));
    $buyer->setSurname($value('billing_last_name'));
    $buyer->setGsmNumber($value('billing_phone_number'));
    $buyer->setEmail($value('billing_email_address'));
    $buyer->setIdentityNumber(pg_payment_iyzico_identity_number($order));
    $buyer->setRegistrationAddress(trim($value('billing_address_1') . ' ' . $value('billing_address_2') . ' ' . $value('billing_city')));
    $buyer->setIp(h(isset($payment['ip']) ? (string) $payment['ip'] : ''));
    $buyer->setCity($value('billing_state'));
    $buyer->setCountry($value('billing_country'));
    $buyer->setZipCode($value('billing_zip_code'));

    $billing = new \Iyzipay\Model\Address();
    $billing->setContactName(trim($value('billing_first_name') . ' ' . $value('billing_last_name')));
    $billing->setCity($value('billing_state'));
    $billing->setCountry($value('billing_country'));
    $billing->setAddress(trim($value('billing_address_1') . ' ' . $value('billing_address_2') . ' ' . $value('billing_city')));
    $billing->setZipCode($value('billing_zip_code'));

    return array('buyer' => $buyer, 'billing' => $billing);
}

/**
 * The basket iyzico is shown: one item per order line with a price, the
 * line's total in the currency the payment is made in.
 *
 * @param array $lines order lines as submit_order.php reads them
 * @param float $rate  the protected currency's rate, 0 for none
 * @return array \Iyzipay\Model\BasketItem
 */
function pg_payment_iyzico_basket($lines, $rate)
{
    $items = array();

    foreach ((is_array($lines) ? $lines : array()) as $line) {
        $price_cents = (isset($line['price']) && is_numeric($line['price'])) ? (int) $line['price'] : 0;
        $quantity = isset($line['quantity']) ? (int) $line['quantity'] : 1;

        if ($price_cents <= 0) {
            continue;
        }

        if ($rate > 0) {
            $price_cents = (int) round(($price_cents / 100.0) * $rate * 100);
        }

        $line_cents = $price_cents * $quantity;
        $description = isset($line['short_description']) ? $line['short_description'] : '';

        $item = new \Iyzipay\Model\BasketItem();
        // The product's id when the line has one.
        $item->setId((string) (isset($line['id']) ? $line['id'] : (isset($line['product_name']) ? $line['product_name'] : '')));
        $item->setName(($quantity > 1 ? $quantity . ' Qty/Adet - ' : '') . $description);
        $item->setPrice(sprintf('%01.2lf', $line_cents / 100));
        $item->setItemType(!empty($line['shippable']) ? \Iyzipay\Model\BasketItemType::PHYSICAL : \Iyzipay\Model\BasketItemType::VIRTUAL);
        $item->setCategory1($description);

        $items[] = $item;
    }

    return $items;
}

/**
 * The sentence for the visitor when iyzico would not start a payment, with
 * iyzico's own reason when it gave one.
 *
 * @param mixed  $error_message what the SDK read as errorMessage
 * @param string $raw           iyzico's answer as it came
 * @return string
 */
function pg_payment_iyzico_start_error($error_message, $raw)
{
    $sentence = lang('Payment could not be initiated. Please try again.');

    if ($error_message !== '') {
        $sentence .= ' (' . $error_message . ')';
    } elseif ($raw !== '') {
        // The SDK read no message, but the answer may still carry one.
        $parsed = @json_decode($raw, true);

        if (is_array($parsed) && !empty($parsed['errorMessage'])) {
            $sentence .= ' (' . $parsed['errorMessage'] . ')';
        }
    }

    return $sentence;
}

/* ---------------------------------------------------------------------------
   Card payment
   --------------------------------------------------------------------------- */

/**
 * A card payment: direct, or the start of a 3-D Secure one.
 *
 * $payment (built by submit_order.php):
 *   card_number, expiration_month, expiration_year, card_verification_number
 *   installment      the number the visitor picked, 0 for none
 *   threeds          the visitor asked for 3-D Secure (it is also on when the
 *                    store requires it)
 *   recurring        the order starts a recurring plan (iyzico keeps the card)
 *   subtotal, discount   hundredths, for the 3-D Secure row
 *   lines            order lines; ship_to: the order's single delivery
 *                    address, null when there is none
 *   customer_id, ip, basket_id, return_url
 *
 * @param array    $order    orders row
 * @param int      $amount   hundredths: the order total before any instalment charge
 * @param array    $payment
 * @param array    $settings pg_payment_iyzico_settings(true)
 * @param callable $keep     writes the 3-D Secure row (pg_payment_iyzico_threeds_keep())
 * @return array an answer: paid, or redirect with the bank's page
 */
function pg_payment_iyzico_authorize_with($order, $amount, $payment, $settings, $keep)
{
    pg_payment_iyzico_sdk();

    $payment = is_array($payment) ? $payment : array();
    $options = pg_payment_iyzico_options($settings);
    $log = array();

    $total = (int) $amount;
    $subtotal = isset($payment['subtotal']) ? (int) $payment['subtotal'] : 0;
    $discount = isset($payment['discount']) ? (int) $payment['discount'] : 0;
    $card_number = isset($payment['card_number']) ? (string) $payment['card_number'] : '';
    $threeds = !empty($payment['threeds']) || $settings['threeds'];
    $conversation_id = pg_payment_iyzico_conversation_id();

    // Instalments: the visitor's choice up to the store's limit, at the price
    // iyzico quotes for this card's BIN; the difference is the instalment charge.
    $installment = 1;
    $installment_charge = 0;
    $selected = isset($payment['installment']) ? (int) $payment['installment'] : 0;

    if ($selected >= 1) {
        if ($selected > $settings['installments']) {
            return pg_payment_failure(lang('Payment Installment cannot be bigger than accepted Installment!'));
        }

        $installment = $selected;

        $installment_request = new \Iyzipay\Request\RetrieveInstallmentInfoRequest();
        $installment_request->setLocale($settings['locale']);
        $installment_request->setConversationId($conversation_id);
        $installment_request->setBinNumber(substr($card_number, 0, 6));
        $installment_request->setPrice(sprintf('%01.2lf', $total / 100));

        $installment_info = \Iyzipay\Model\InstallmentInfo::retrieve($installment_request, $options);
        $data = json_decode((string) $installment_info->getRawResult(), true);

        if (isset($data['installmentDetails'][0]['installmentPrices'])) {
            foreach ($data['installmentDetails'][0]['installmentPrices'] as $price) {
                if ((int) $price['installmentNumber'] === $installment) {
                    $installment_cents = (int) round((float) $price['totalPrice'] * 100);

                    if ($installment_cents > $total) {
                        $installment_charge = $installment_cents - $total;
                        $total = $total + $installment_charge;
                    }

                    break;
                }
            }
        }
    }

    // iyzico requires a delivery address even for a basket with nothing to
    // ship; the billing address stands in when the order has no single one.
    $ship_name = '';
    $ship_address = '';
    $ship_state = '';
    $ship_zip = '';
    $ship_country = '';

    if (isset($payment['ship_to']) && is_array($payment['ship_to'])) {
        $ship_to = $payment['ship_to'];
        $ship_name = $ship_to['first_name'] . ' ' . $ship_to['last_name'];
        $ship_address = $ship_to['address_1'] . ' ' . $ship_to['address_2'] . ' ' . $ship_to['city'];
        $ship_state = $ship_to['state'];
        $ship_zip = $ship_to['zip_code'];
        $ship_country = $ship_to['country'];
    }

    if (($ship_name === '') || ($ship_address === '')) {
        $ship_name = trim(pg_payment_iyzico_order_value($order, 'billing_first_name') . ' ' . pg_payment_iyzico_order_value($order, 'billing_last_name'));
        $ship_address = trim(pg_payment_iyzico_order_value($order, 'billing_address_1') . ' ' . pg_payment_iyzico_order_value($order, 'billing_address_2') . ' ' . pg_payment_iyzico_order_value($order, 'billing_city'));
        $ship_state = pg_payment_iyzico_order_value($order, 'billing_state');
        $ship_zip = pg_payment_iyzico_order_value($order, 'billing_zip_code');
        $ship_country = pg_payment_iyzico_order_value($order, 'billing_country');
    }

    // In the protected currency the amounts are converted at today's rate.
    $currency = $settings['currency'];
    $rate = 0.0;
    $charged_subtotal = $subtotal;
    $charged_total = $total;

    if ($settings['protected_currency'] !== '') {
        $currency = $settings['protected_currency'];
        $rate = (float) $settings['protected_rate'];

        if ($subtotal > 0) {
            $charged_subtotal = (int) round(($subtotal / 100.0) * $rate * 100);
        }

        if ($total > 0) {
            $charged_total = (int) round(($total / 100.0) * $rate * 100);
        }
    }

    $request = new \Iyzipay\Request\CreatePaymentRequest();
    $request->setConversationId($conversation_id);
    $request->setLocale($settings['locale']);
    $request->setPrice(sprintf('%01.2lf', $charged_subtotal / 100));
    $request->setPaidPrice(sprintf('%01.2lf', $charged_total / 100));
    $request->setCurrency($currency);
    $request->setInstallment($installment);
    $request->setBasketId(isset($payment['basket_id']) ? (string) $payment['basket_id'] : '');
    $request->setPaymentChannel(\Iyzipay\Model\PaymentChannel::WEB);
    $request->setPaymentGroup(\Iyzipay\Model\PaymentGroup::PRODUCT);

    if ($threeds) {
        // The bank's answer is checked against this row: the totals the payment
        // was started with, before the instalment charge.
        call_user_func($keep, array(
            'conversation_id'          => $conversation_id,
            'order_id'                 => (int) pg_payment_iyzico_order_value($order, 'id'),
            'subtotal_cents'           => $subtotal,
            'discount_cents'           => $discount,
            'installment_charge_cents' => (int) $installment_charge,
            'base_total_cents'         => (int) ($total - $installment_charge),
        ));

        $request->setCallbackUrl(isset($payment['return_url']) ? (string) $payment['return_url'] : '');
    }

    $card = new \Iyzipay\Model\PaymentCard();
    $card->setCardHolderName(trim(pg_payment_iyzico_order_value($order, 'billing_first_name') . ' ' . pg_payment_iyzico_order_value($order, 'billing_last_name')));
    $card->setCardNumber($card_number);
    $card->setExpireMonth(isset($payment['expiration_month']) ? $payment['expiration_month'] : '');
    $card->setExpireYear(isset($payment['expiration_year']) ? $payment['expiration_year'] : '');
    $card->setCvc(isset($payment['card_verification_number']) ? $payment['card_verification_number'] : '');
    $card->setRegisterCard(!empty($payment['recurring']) ? 1 : 0);
    $request->setPaymentCard($card);

    $buyer = pg_payment_iyzico_buyer($order, $payment);
    $request->setBuyer($buyer['buyer']);

    $shipping = new \Iyzipay\Model\Address();
    $shipping->setContactName($ship_name);
    $shipping->setCity($ship_state);
    $shipping->setCountry($ship_country);
    $shipping->setAddress($ship_address);
    $shipping->setZipCode($ship_zip);
    $request->setShippingAddress($shipping);

    $request->setBillingAddress($buyer['billing']);
    $request->setBasketItems(pg_payment_iyzico_basket(isset($payment['lines']) ? $payment['lines'] : array(), $rate));

    $billing_name = array(
        pg_payment_iyzico_order_value($order, 'billing_first_name'),
        pg_payment_iyzico_order_value($order, 'billing_last_name'),
        pg_payment_iyzico_order_value($order, 'billing_email_address'),
    );

    if ($threeds) {
        $initialize = \Iyzipay\Model\ThreedsInitialize::create($request, $options);

        $status = $initialize->getStatus();
        $error_code = $initialize->getErrorCode();
        $error_message = $initialize->getErrorMessage();

        if ($status !== 'success') {
            $raw = (string) $initialize->getRawResult();
            $log[] = "Iyzipay 3DS init error: status=$status, code=$error_code, message=$error_message, raw=" . substr($raw, 0, 1500);

            return pg_payment_failure_logged(pg_payment_iyzico_start_error($error_message, $raw), $log);
        }

        $log[] = lang(array(
            'string' => 'The 3D Security process has been started by {var:1} {var:2} ({var:3}).',
            'vars'   => $billing_name,
        ));

        return array(
            'success'      => true,
            'status'       => 'redirect',
            'html'         => (string) $initialize->getHtmlContent(),
            'amount'       => $total,
            'installments' => $installment,
            'log'          => $log,
        );
    }

    $paid = \Iyzipay\Model\Payment::create($request, $options);

    $status = $paid->getStatus();
    $error_code = $paid->getErrorCode();
    $error_message = $paid->getErrorMessage();
    $payment_id = $paid->getPaymentId();
    $fraud_status = $paid->getFraudStatus();

    if ($status !== 'success') {
        $log[] = "Iyzipay payment error: code=$error_code, message=$error_message";

        return pg_payment_failure_logged(lang('Payment failed. Please try again.'), $log);
    }

    if ($fraud_status === -1) {
        $log[] = "Iyzipay fraud rejected: paymentId=$payment_id";

        return pg_payment_failure_logged(lang('Payment was found to be risky and rejected.'), $log);
    }

    if ($fraud_status === 0) {
        $log[] = "Iyzipay fraud pending review: paymentId=$payment_id";
    }

    return array(
        'success'        => true,
        'status'         => 'paid',
        'transaction_id' => (string) $payment_id,
        'amount'         => $total,
        'installments'   => $installment,
        'card_brand'     => (string) $paid->getCardAssociation(),
        'log'            => $log,
    );
}

/**
 * Writes the row the 3-D Secure return in submit_order.php reads.
 *
 * @param array $row pg_payment_iyzico_authorize_with()
 * @return void
 */
function pg_payment_iyzico_threeds_keep($row)
{
    $query = "
        INSERT INTO iyzipay_3ds_state
            (conversation_id, order_id, subtotal_cents, discount_cents, installment_charge_cents, base_total_cents)
        VALUES (
            '" . mysqli_real_escape_string(db::$con, (string) $row['conversation_id']) . "',
            '" . (int) $row['order_id'] . "',
            '" . (int) $row['subtotal_cents'] . "',
            '" . (int) $row['discount_cents'] . "',
            '" . (int) $row['installment_charge_cents'] . "',
            '" . (int) $row['base_total_cents'] . "'
        )
    ";

    mysqli_query(db::$con, $query) or output_error(lang('3DS state insert failed.'));
}

/* ---------------------------------------------------------------------------
   Pay With Iyzico
   --------------------------------------------------------------------------- */

/**
 * Starts a payment on iyzico's own page.
 *
 * @param array $order    orders row
 * @param int   $amount   hundredths: the order total
 * @param array $payment  as pg_payment_iyzico_authorize_with() takes it (the
 *                        card fields unused)
 * @param array $settings pg_payment_iyzico_settings()
 * @return array an answer: redirect to iyzico's page, with the state the
 *               return is checked against
 */
function pg_payment_pay_with_iyzico_authorize_with($order, $amount, $payment, $settings)
{
    pg_payment_iyzico_sdk();

    $payment = is_array($payment) ? $payment : array();
    $options = pg_payment_iyzico_options($settings);
    $log = array();

    $total = (int) $amount;
    $subtotal = isset($payment['subtotal']) ? (int) $payment['subtotal'] : 0;
    $conversation_id = pg_payment_iyzico_conversation_id();

    $request = new \Iyzipay\Request\CreatePayWithIyzicoInitializeRequest();
    $request->setLocale($settings['locale']);
    $request->setConversationId($conversation_id);
    $request->setPrice(sprintf('%01.2lf', $subtotal / 100));
    $request->setPaidPrice(sprintf('%01.2lf', $total / 100));
    $request->setCurrency($settings['currency']);
    $request->setBasketId(isset($payment['basket_id']) ? (string) $payment['basket_id'] : '');
    $request->setPaymentGroup(\Iyzipay\Model\PaymentGroup::PRODUCT);

    $buyer = pg_payment_iyzico_buyer($order, $payment);
    $request->setBuyer($buyer['buyer']);
    $request->setBillingAddress($buyer['billing']);

    // The delivery address is the billing address.
    $shipping = new \Iyzipay\Model\Address();
    $shipping->setContactName(trim(pg_payment_iyzico_order_value($order, 'billing_first_name') . ' ' . pg_payment_iyzico_order_value($order, 'billing_last_name')));
    $shipping->setCity(pg_payment_iyzico_order_value($order, 'billing_state'));
    $shipping->setCountry(pg_payment_iyzico_order_value($order, 'billing_country'));
    $shipping->setAddress(trim(pg_payment_iyzico_order_value($order, 'billing_address_1') . ' ' . pg_payment_iyzico_order_value($order, 'billing_address_2') . ' ' . pg_payment_iyzico_order_value($order, 'billing_city')));
    $shipping->setZipCode(pg_payment_iyzico_order_value($order, 'billing_zip_code'));
    $request->setShippingAddress($shipping);

    $request->setBasketItems(pg_payment_iyzico_basket(isset($payment['lines']) ? $payment['lines'] : array(), 0));
    $request->setCallbackUrl(isset($payment['return_url']) ? (string) $payment['return_url'] : '');

    $result = \Iyzipay\Model\PayWithIyzicoInitialize::create($request, $options);

    $status = $result->getStatus();
    $error_code = $result->getErrorCode();
    $error_message = $result->getErrorMessage();

    if ($status !== 'success') {
        $raw = (string) $result->getRawResult();
        $log[] = "Pay with Iyzico init error: code=$error_code, message=$error_message, raw=" . substr($raw, 0, 1500);

        return pg_payment_failure_logged(pg_payment_iyzico_start_error($error_message, $raw), $log);
    }

    $log[] = lang(array(
        'string' => 'Pay with Iyzico has been started by {var:1} {var:2} ({var:3}).',
        'vars'   => array(
            pg_payment_iyzico_order_value($order, 'billing_first_name'),
            pg_payment_iyzico_order_value($order, 'billing_last_name'),
            pg_payment_iyzico_order_value($order, 'billing_email_address'),
        ),
    ));

    return array(
        'success'      => true,
        'status'       => 'redirect',
        'redirect_url' => (string) $result->getPayWithIyzicoPageUrl(),
        'amount'       => $total,
        // base_total_cents: the full order total, what was sent as the paid
        // price; the return must find the order at that total still.
        'state'        => array(
            'token'            => (string) $result->getToken(),
            'conversation_id'  => $conversation_id,
            'order_id'         => (int) pg_payment_iyzico_order_value($order, 'id'),
            'base_total_cents' => $total,
        ),
        'log'          => $log,
    );
}

/**
 * The visitor is back from iyzico's page: the payment is read from iyzico
 * and taken only for the order and the total it was started with. A payment
 * iyzico took for another total is cancelled.
 *
 * @param array $order    orders row of the order in the visitor's session
 * @param int   $amount   hundredths: the order total now
 * @param array $return   what iyzico posted back (token)
 * @param array $state    pg_payment_pay_with_iyzico_authorize_with()
 * @param array $settings pg_payment_iyzico_settings()
 * @return array an answer: paid; state kept only while iyzico was not asked
 */
function pg_payment_pay_with_iyzico_complete_with($order, $amount, $return, $state, $settings)
{
    pg_payment_iyzico_sdk();

    $state = is_array($state) ? $state : array();
    $log = array();

    $token = (isset($return['token']) && is_scalar($return['token'])) ? (string) $return['token'] : '';
    $stored_token = isset($state['token']) ? (string) $state['token'] : '';
    $conversation_id = isset($state['conversation_id']) ? $state['conversation_id'] : '';
    $stored_order_id = isset($state['order_id']) ? (int) $state['order_id'] : 0;
    $order_id = (int) pg_payment_iyzico_order_value($order, 'id');

    // The token iyzico posts back must be the one this payment was started
    // with, for the order in this session; nothing is asked of iyzico before.
    if (($token === '') || ($token !== $stored_token) || ($order_id <= 0) || ($stored_order_id !== $order_id)) {
        $log[] = "Pay with Iyzico callback: token or order mismatch (order_id=$order_id, stored_order_id=$stored_order_id)";

        $answer = pg_payment_failure_logged(lang('Session could not be verified. Please try again.'), $log);
        $answer['state'] = $state;

        return $answer;
    }

    $base_total = isset($state['base_total_cents']) ? (int) $state['base_total_cents'] : 0;
    $current_total = (int) $amount;
    $options = pg_payment_iyzico_options($settings);

    $request = new \Iyzipay\Request\RetrievePayWithIyzicoRequest();
    $request->setLocale($settings['locale']);
    $request->setConversationId($conversation_id);
    $request->setToken($token);

    $retrieved = \Iyzipay\Model\PayWithIyzico::retrieve($request, $options);

    $status = $retrieved->getStatus();
    $error_code = $retrieved->getErrorCode();
    $error_message = $retrieved->getErrorMessage();

    if ($status !== 'success') {
        $log[] = "Pay with Iyzico status!=success: code=$error_code, message=$error_message";

        return pg_payment_failure_logged(lang('Payment could not be verified. Please try again.'), $log);
    }

    // iyzico's paid price is the price sent plus the instalment charge.
    $installment = (int) $retrieved->getInstallment();
    $paid_cents = (int) round((float) $retrieved->getPaidPrice() * 100);
    $payment_id = (string) $retrieved->getPaymentId();

    // The order must still total what the payment was started for, and iyzico
    // must have taken at least that (more only as an instalment charge).
    if (
        ($payment_id === '')
        || ($base_total <= 0)
        || ($current_total !== $base_total)
        || ($paid_cents < $base_total)
        || (($installment <= 1) && ($paid_cents !== $base_total))
    ) {
        $log[] = "Pay with Iyzico amount mismatch: paymentId=$payment_id, order_id=$order_id, current_total=$current_total, stored_total=$base_total, gateway_paid=$paid_cents, installment=$installment";
        $log = array_merge($log, pg_payment_iyzico_void($options, $payment_id, $conversation_id, 'Order total changed during Pay with Iyzico checkout'));

        return pg_payment_failure_logged(lang('The order total changed while the payment was being verified. The payment was cancelled. Please review your order and try again.'), $log);
    }

    $log[] = lang(array(
        'string' => 'Pay with Iyzico has been completed by {var:1} {var:2} ({var:3}).',
        'vars'   => array(
            pg_payment_iyzico_order_value($order, 'billing_first_name'),
            pg_payment_iyzico_order_value($order, 'billing_last_name'),
            pg_payment_iyzico_order_value($order, 'billing_email_address'),
        ),
    ));

    return array(
        'success'        => true,
        'status'         => 'paid',
        'transaction_id' => $payment_id,
        'amount'         => (($installment > 1) && ($paid_cents > $base_total)) ? $paid_cents : $base_total,
        'installments'   => ($installment > 1) ? $installment : 1,
        'log'            => $log,
    );
}

/* ---------------------------------------------------------------------------
   Refund and cancel (both drivers)
   --------------------------------------------------------------------------- */

/**
 * Cancels a payment iyzico took for a total the order no longer has.
 *
 * @param \Iyzipay\Options $options
 * @param string           $payment_id
 * @param mixed            $conversation_id
 * @param string           $reason a short English reason, for the log and iyzico
 * @return array lines for the activity log; a refusal says MANUAL REFUND REQUIRED
 */
function pg_payment_iyzico_void($options, $payment_id, $conversation_id, $reason)
{
    $payment_id = (string) $payment_id;
    $reason = (string) $reason;

    if ($payment_id === '') {
        return array('Iyzipay payment could not be cancelled because the payment id is empty (' . $reason . ').');
    }

    try {
        $request = new \Iyzipay\Request\CreateCancelRequest();
        $request->setLocale(\Iyzipay\Model\Locale::TR);
        $request->setConversationId((string) $conversation_id);
        $request->setPaymentId($payment_id);
        $request->setIp(pg_payment_iyzico_request_ip());
        $request->setDescription(mb_substr($reason, 0, 240));

        $cancel = \Iyzipay\Model\Cancel::create($request, $options);

        if ($cancel->getStatus() == 'success') {
            return array('Iyzipay payment ' . $payment_id . ' was cancelled: ' . $reason . '.');
        }

        $error_message = (string) $cancel->getErrorMessage();

        return array(
            'Iyzipay payment ' . $payment_id . ' could not be cancelled (' . $reason . '). MANUAL REFUND REQUIRED. ' .
            'Gateway message: ' . ($error_message !== '' ? $error_message : '(no message)'),
        );
    } catch (\Throwable $exception) {
        return array('Iyzipay payment ' . $payment_id . ' could not be cancelled (' . $reason . '). MANUAL REFUND REQUIRED. ' . $exception->getMessage());
    }
}

/**
 * Gives back part or all of a payment (iyzico's amount-based refund).
 *
 * @param array $order    orders row (transaction_id)
 * @param int   $amount   hundredths to give back
 * @param array $settings pg_payment_iyzico_settings()
 * @return array an answer: refunded, with iyzico's id of the refund
 */
function pg_payment_iyzico_refund_with($order, $amount, $settings)
{
    pg_payment_iyzico_sdk();

    $request = new \Iyzipay\Request\AmountBaseRefundRequest();
    $request->setLocale(\Iyzipay\Model\Locale::TR);
    $request->setConversationId((string) pg_payment_iyzico_conversation_id());
    $request->setPaymentId(pg_payment_iyzico_order_value($order, 'transaction_id'));
    $request->setPrice(pg_payment_decimal($amount));
    $request->setIp(pg_payment_iyzico_request_ip());

    $result = \Iyzipay\Model\AmountBaseRefund::create($request, pg_payment_iyzico_options($settings));

    if ($result->getStatus() != 'success') {
        return pg_payment_failure((string) $result->getErrorMessage());
    }

    return array(
        'success'          => true,
        'status'           => 'refunded',
        'amount'           => (int) $amount,
        'refund_reference' => (string) $result->getPaymentId(),
    );
}

/**
 * Voids a payment that has not settled (iyzico's cancel), with the order's
 * cancellation reason as the description.
 *
 * @param array  $order          orders row (id, cancellation_reason)
 * @param string $transaction_id
 * @param array  $settings       pg_payment_iyzico_settings()
 * @return array an answer: cancelled, with iyzico's id as the reference
 */
function pg_payment_iyzico_cancel_with($order, $transaction_id, $settings)
{
    pg_payment_iyzico_sdk();

    $reason = trim((string) pg_payment_iyzico_order_value($order, 'cancellation_reason'));

    $request = new \Iyzipay\Request\CreateCancelRequest();
    $request->setLocale(\Iyzipay\Model\Locale::TR);
    $request->setConversationId((string) pg_payment_iyzico_order_value($order, 'id') . '-' . time());
    $request->setPaymentId((string) $transaction_id);
    $request->setIp(pg_payment_iyzico_request_ip());

    if ($reason !== '') {
        $request->setDescription(mb_substr($reason, 0, 240));
    }

    $cancel = \Iyzipay\Model\Cancel::create($request, pg_payment_iyzico_options($settings));

    if ($cancel->getStatus() != 'success') {
        return pg_payment_failure((string) $cancel->getErrorMessage());
    }

    $reference = (string) $cancel->getPaymentId();

    return array(
        'success'          => true,
        'status'           => 'cancelled',
        'refund_reference' => ($reference !== '') ? $reference : (string) $transaction_id,
    );
}
