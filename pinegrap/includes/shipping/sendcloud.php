<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Sendcloud label driver: Sendcloud is a European shipping platform
 * (https://panel.sendcloud.sc/api/v3, HTTP Basic authentication with the
 * account's public and secret API keys) with its own rates at PostNL, DHL,
 * DPD, GLS, bpost, Colissimo, UPS and other carriers, so a store needs no
 * carrier contract. The driver follows its public reference
 * (https://sendcloud.dev, Shipments API v3): POST /shipping-options prices
 * the carriers' options for a parcel, POST /shipments/announce creates the
 * shipment and announces it to the carrier at once and answers with the
 * label (base64, PDF or ZPL), GET /parcels/{id}/documents/label gives it
 * again, POST /shipments/{id}/cancel cancels it, and GET
 * /parcels/tracking/{number} gives the carrier's events. A customer return
 * goes through the Returns API: POST /returns/announce-synchronously
 * announces it from the customer to the sender address, GET /returns/{id}
 * gives its tracking number, PATCH /returns/{id}/cancel asks the carrier to
 * cancel it. Parcel status changes arrive at carrier_webhook.php
 * (includes/fn/carrier_webhook.php) as parcel_status_changed messages signed
 * with the secret key; PATCH /integrations/{id} sets the API integration's
 * webhook address. POST /pickups (Pickups API v3) books a one-time
 * collection of a shipment's carrier at the sender address; Sendcloud offers
 * it only for the carriers pg_label_sendcloud_pickup_carriers() lists.
 *
 * Sendcloud has no sandbox: test mode ships with the free "Unstamped
 * letter" option (sendcloud:letter), which makes a real label no carrier
 * takes. The sender is one of the sender addresses kept in the Sendcloud
 * account.
 *
 * The contract is the one in includes/fn/carrier_labels.php; the shipment
 * create() takes is the one includes/shipping/geliver.php describes
 * (service: a Sendcloud shipping option code such as postnl:standard). A
 * parcel that needs a customs declaration (leaving the EU, or to or from
 * Great Britain abroad) carries its lines as parcel_items and the
 * customs_information of the shipment once the shop fills the customs block
 * of the settings (pg_label_customs_fields(), pg_label_customs_lines());
 * with the block empty it is refused before a call, a return always. A
 * return is made in live mode only: the unstamped letter of test mode is an
 * outgoing option.
 * $reference is Sendcloud's shipment id, or r-<return id>-<parcel id> for a
 * return.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

function pg_label_sendcloud_info()
{
    return array(
        'carriers' => array('ups', 'fedex', 'dhl', 'deutschepost', 'dpd', 'royalmail'),
        'formats'  => array('pdf', 'zpl'),
    );
}

function pg_label_sendcloud_fields()
{
    return array_merge(array(
        array(
            'name'     => 'public_key',
            'type'     => 'secret',
            'label'    => lang('Public Key'),
            'help'     => lang('Created in the Sendcloud panel under Settings, Integrations, Sendcloud API.'),
            'required' => true,
        ),
        array(
            'name'     => 'secret_key',
            'type'     => 'secret',
            'label'    => lang('Secret Key'),
            'required' => true,
        ),
        array(
            'name'    => 'mode',
            'type'    => 'select',
            'label'   => lang('Mode'),
            'help'    => lang('Test mode ships every parcel as an unstamped letter: the label is free and no carrier takes it.'),
            'options' => array('test' => lang('Test'), 'live' => lang('Live')),
            'default' => 'test',
        ),
        array(
            'name'       => 'sender_address',
            'label'      => lang('Sender Address ID'),
            'help'       => lang('The id of a sender address in the Sendcloud panel. Empty takes the first one.'),
            'max_length' => 20,
        ),
        array(
            'name'       => 'service',
            'label'      => lang('Shipping Option'),
            'help'       => lang('A Sendcloud shipping option code such as postnl:standard or dpd:home. Empty takes the cheapest option.'),
            'max_length' => 100,
        ),
        array(
            'name'    => 'label_format',
            'type'    => 'select',
            'label'   => lang('Label Format'),
            'options' => array('pdf' => lang('PDF, A6'), 'zpl' => lang('ZPL (thermal printer)')),
            'default' => 'pdf',
        ),
    ), pg_label_customs_fields());
}

/**
 * The HTTP transport: pg_curl_run() unless one is set in its place (the
 * tests). While one is set, a wait is recorded (pg_label_sendcloud_waits())
 * instead of slept.
 *
 * @param callable|null $replace function ($method, $url, $headers, $body)
 *                               answering status, body, error, sent
 * @param bool          $reset   true to go back to pg_curl_run()
 * @return callable|null the transport set, null for pg_curl_run()
 */
function pg_label_sendcloud_transport($replace = null, $reset = false)
{
    static $transport = null;

    if ($reset) {
        $transport = null;
        pg_label_sendcloud_waits(true);
    } elseif ($replace !== null) {
        $transport = $replace;
        pg_label_sendcloud_waits(true);
    }

    return $transport;
}

/**
 * The waits asked for while a stand-in transport is set, in seconds.
 *
 * @param bool     $reset
 * @param int|null $add   a wait to record
 * @return array
 */
function pg_label_sendcloud_waits($reset = false, $add = null)
{
    static $waits = array();

    if ($reset) {
        $waits = array();
    } elseif ($add !== null) {
        $waits[] = $add;
    }

    return $waits;
}

/**
 * @param int $seconds
 */
function pg_label_sendcloud_sleep($seconds)
{
    if (pg_label_sendcloud_transport() !== null) {
        pg_label_sendcloud_waits(false, $seconds);
        return;
    }

    sleep($seconds);
}

/**
 * A text field, '' when missing.
 *
 * @return string
 */
function pg_label_sendcloud_text($data, $name)
{
    return (is_array($data) && isset($data[$name]) && is_scalar($data[$name])) ? trim((string) $data[$name]) : '';
}

/**
 * A request to the API read into one shape.
 *
 * @param string     $method
 * @param string     $path    a path under the API
 * @param array      $account
 * @param array|null $payload JSON body
 * @param string     $accept  the answer's type; a document is kept as bytes
 * @return array ok (a 2xx answer), reached, sent, status, data (the JSON
 *               answer, array), body, message, raw
 */
function pg_label_sendcloud_api($method, $path, $account, $payload = null, $accept = 'application/json')
{
    $headers = array(
        'Accept: ' . $accept,
        'Authorization: Basic ' . base64_encode((string) $account['public_key'] . ':' . (string) $account['secret_key']),
    );
    $body = null;

    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $url = 'https://panel.sendcloud.sc/api/v3' . $path;
    $transport = pg_label_sendcloud_transport();

    if ($transport !== null) {
        $call = call_user_func($transport, $method, $url, $headers, $body);
    } else {
        // An announcement waits for the carrier.
        $call = pg_curl_run($method, $url, array(
            'headers'         => $headers,
            'body'            => $body,
            'timeout'         => 60,
            'connect_timeout' => 10,
            'max_size'        => 10485760,
        ));

        if (($call['status'] === 0) && ($call['errno'] !== 0)) {
            $call['error'] = trim($call['error'] . ' ' . pg_curl_tls_hint($call['errno']));
        }

        $call['sent'] = ($call['status'] !== 0) || ($call['request_size'] > 0);
    }

    $status = isset($call['status']) ? (int) $call['status'] : 0;
    $text = isset($call['body']) ? (string) $call['body'] : '';
    $decoded = json_decode($text, true);
    $decoded = is_array($decoded) ? $decoded : array();

    $read = array(
        'ok'      => ($status >= 200) && ($status < 300),
        'reached' => ($status !== 0),
        'sent'    => !empty($call['sent']),
        'status'  => $status,
        'data'    => $decoded,
        'body'    => $text,
        'message' => '',
        'raw'     => array('request' => $method . ' ' . $path, 'status' => $status, 'answer' => $decoded ? pg_label_sendcloud_raw($decoded) : substr($text, 0, 200)),
    );

    if ($status === 0) {
        $read['message'] = isset($call['error']) ? (string) $call['error'] : '';
    } elseif (!$read['ok']) {
        $words = pg_label_sendcloud_error_texts($decoded);
        $read['message'] = $words ? mb_substr(implode(' ', $words), 0, 300, 'UTF-8') : 'HTTP ' . $status;
    }

    return $read;
}

/**
 * An answer as it is logged: the label's base64 is left out.
 *
 * @param array $data
 * @return array
 */
function pg_label_sendcloud_raw($data)
{
    if (isset($data['data']['parcels']) && is_array($data['data']['parcels'])) {
        foreach ($data['data']['parcels'] as $index => $parcel) {
            if (is_array($parcel) && isset($parcel['label_file'])) {
                $data['data']['parcels'][$index]['label_file'] = '(' . strlen((string) $parcel['label_file']) . ' bytes)';
            }
        }
    }

    return $data;
}

/**
 * The words of a refused request: JSON:API errors (detail, with the field
 * the pointer names), a detail sentence or list, or the Returns API's
 * error object or errors with a message. Pure.
 *
 * @param array $data
 * @return string[]
 */
function pg_label_sendcloud_error_texts($data)
{
    $texts = array();
    $errors = (isset($data['errors']) && is_array($data['errors'])) ? $data['errors'] : array();

    if (isset($data['detail'])) {
        $errors = array_merge($errors, is_array($data['detail']) ? $data['detail'] : array(array('detail' => $data['detail'])));
    }

    if (isset($data['error']) && is_array($data['error'])) {
        $errors[] = $data['error'];
    }

    foreach ($errors as $error) {
        if (!is_array($error)) {
            continue;
        }

        $words = pg_label_sendcloud_text($error, 'detail');
        $words = ($words !== '') ? $words : pg_label_sendcloud_text($error, 'msg');
        $words = ($words !== '') ? $words : pg_label_sendcloud_text($error, 'message');
        $words = ($words !== '') ? $words : pg_label_sendcloud_text($error, 'title');
        $field = isset($error['source']) ? trim(pg_label_sendcloud_text($error['source'], 'pointer'), '/') : '';

        if ($words !== '') {
            $texts[] = (($field !== '') ? str_replace('/', '.', $field) . ': ' : '') . $words;
        }
    }

    return array_values(array_unique($texts));
}

/**
 * The failure of a request that did not succeed. A request that announces
 * or cancels and went out without a readable answer, or met a server
 * error, is unclear.
 *
 * @param array $read     pg_label_sendcloud_api()
 * @param bool  $changing whether the request announces or cancels
 * @return array
 */
function pg_label_sendcloud_failure($read, $changing)
{
    if (!$read['reached']) {
        return array(
            'success' => false,
            'unclear' => $changing && $read['sent'],
            'error'   => lang('The carrier label service could not be reached.'),
            'log'     => array('Sendcloud could not be reached: ' . $read['message']),
            'raw'     => $read['raw'],
        );
    }

    return array(
        'success' => false,
        'unclear' => $changing && ($read['status'] >= 500),
        'error'   => lang('Sendcloud refused the request.') . (($read['message'] !== '') ? ' (' . $read['message'] . ')' : ''),
        'log'     => array('Sendcloud refused ' . $read['raw']['request'] . ': ' . $read['message']),
        'raw'     => $read['raw'],
    );
}

/**
 * A size or weight as the decimal text Sendcloud takes.
 *
 * @return string
 */
function pg_label_sendcloud_number($value, $places)
{
    $value = round(is_numeric($value) ? max(0, (float) $value) : 0, $places);

    return number_format($value, $places, '.', '');
}

/**
 * Whether the shop filled the customs block of the settings: a default HS
 * code, country of origin or signer. Only then does a parcel that needs
 * customs go out declared. Pure.
 *
 * @param array $account
 * @return bool
 */
function pg_label_sendcloud_customs_filled($account)
{
    return pg_label_customs_in_use(array(), $account);
}

/**
 * Why a parcel between two countries cannot be sent, '' when it can:
 * Sendcloud's sender countries (the shop's side), then a route that needs a
 * customs declaration (pg_label_customs_needed()) unless one is sent with
 * it. Pure.
 *
 * @param string $from
 * @param string $to
 * @param bool   $return  a return: $from is the customer, $to the shop
 * @param bool   $customs whether a customs declaration goes with the parcel
 * @return string
 */
function pg_label_sendcloud_route_error($from, $to, $return = false, $customs = false)
{
    $from = strtoupper(trim((string) $from));
    $to = strtoupper(trim((string) $to));
    $shop = $return ? $to : $from;
    $customer = $return ? $from : $to;
    $senders = array('AT', 'BE', 'DE', 'ES', 'FR', 'GB', 'IT', 'NL');

    if ($customer === '') {
        return lang('Sendcloud needs the country of the shipping address.');
    }

    if (!in_array($shop, $senders, true)) {
        return lang(array('string' => 'Sendcloud does not ship from {var:1}.', 'vars' => array(($shop === '') ? '?' : $shop)));
    }

    if ($customs || !pg_label_customs_needed($from, $to)) {
        return '';
    }

    if ($return) {
        return lang(array('string' => 'A return from {var:1} to {var:2} needs a customs declaration, which is not sent to Sendcloud yet.', 'vars' => array($from, $to)));
    }

    return lang(array('string' => 'A parcel from {var:1} to {var:2} needs a customs declaration, which is not sent to Sendcloud yet.', 'vars' => array($from, $to)));
}

/**
 * The customs data of a parcel between two countries: null when the route
 * needs none or the shop has not filled the customs block (the route is
 * then refused). Each order line becomes a parcel item with the unit's
 * weight and price, its HS code and country of origin the product's, else
 * the shop default; a line with neither refuses the label. Sendcloud takes
 * an HS code of digits only, at most 12. The shipment's
 * customs_information names the order number as the invoice number and the
 * contents type as the export reason; the incoterm is a property of the
 * shipping option (pg_label_sendcloud_option_pick()). Pure.
 *
 * @param array  $shipment see the header of this file; goods for the lines
 * @param array  $account
 * @param string $from     the sender address's country
 * @return array items (list or null), information (array or null), error
 *               ('' or the translated reason no label can be made)
 */
function pg_label_sendcloud_customs($shipment, $account, $from)
{
    $shipment = is_array($shipment) ? $shipment : array();
    $text = 'pg_label_sendcloud_text';
    $recipient = isset($shipment['recipient']) ? $shipment['recipient'] : array();
    $none = array('items' => null, 'information' => null, 'error' => '');

    if (!pg_label_customs_needed($from, $text($recipient, 'country')) || !pg_label_sendcloud_customs_filled($account)) {
        return $none;
    }

    $parcel = isset($shipment['parcel']) ? $shipment['parcel'] : array();
    $built = pg_label_customs_lines(isset($shipment['goods']) ? $shipment['goods'] : array(), array(
        'hs_code'        => $text($account, 'customs_hs_code'),
        'origin_country' => $text($account, 'customs_origin_country'),
    ), (float) $text($parcel, 'weight'));

    if (!$built['ok']) {
        return array('items' => null, 'information' => null, 'error' => $built['error']);
    }

    $currency = strtoupper($text($shipment, 'currency'));

    if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
        return array('items' => null, 'information' => null, 'error' => lang('The order has no currency to declare its goods in.'));
    }

    $invoice = substr($text($shipment, 'order_number'), 0, 40);

    if ($invoice === '') {
        return array('items' => null, 'information' => null, 'error' => lang('The order has no number to name the customs invoice.'));
    }

    $items = array();

    foreach ($built['lines'] as $line) {
        $hs_code = str_replace('.', '', $line['hs_code']);

        if (strlen($hs_code) > 12) {
            return array('items' => null, 'information' => null, 'error' => lang(array(
                'string' => 'Sendcloud takes an HS code of at most 12 digits; {var:1} has {var:2}.',
                'vars'   => array($line['name'], $line['hs_code']),
            )));
        }

        $item = array(
            'description'    => $line['name'],
            'quantity'       => $line['quantity'],
            'weight'         => array('value' => round($line['unit_weight'], 3), 'unit' => 'kg'),
            // Prices are kept in hundredths of the currency.
            'price'          => array('value' => number_format($line['unit_price'] / 100, 2, '.', ''), 'currency' => $currency),
            'hs_code'        => $hs_code,
            'origin_country' => $line['origin_country'],
        );

        if ($line['sku'] !== '') {
            $item['sku'] = $line['sku'];
        }

        $items[] = $item;
    }

    $reasons = array('merchandise' => 'commercial_goods', 'gift' => 'gift', 'sample' => 'commercial_sample', 'documents' => 'documents', 'return' => 'returned_goods');
    $reason = $text($account, 'customs_contents');

    return array('items' => $items, 'information' => array(
        'invoice_number' => $invoice,
        'export_reason'  => !empty($shipment['return']) ? 'returned_goods' : (isset($reasons[$reason]) ? $reasons[$reason] : 'commercial_goods'),
    ), 'error' => '');
}

/**
 * The sender address of the account: the one its id names, else the first.
 *
 * @param array $account
 * @return array address (null when none), read (pg_label_sendcloud_api())
 */
function pg_label_sendcloud_sender($account)
{
    $read = pg_label_sendcloud_api('GET', '/addresses/sender-addresses?page_size=100', $account);
    $addresses = ($read['ok'] && isset($read['data']['data']) && is_array($read['data']['data'])) ? $read['data']['data'] : array();
    $wanted = trim((string) $account['sender_address']);

    foreach ($addresses as $address) {
        if (is_array($address) && (($wanted === '') || (pg_label_sendcloud_text($address, 'id') === $wanted))) {
            return array('address' => $address, 'read' => $read);
        }
    }

    return array('address' => null, 'read' => $read);
}

/**
 * The parcel of a shipment as Sendcloud describes it in the Shipments API:
 * cm and kg. Pure.
 *
 * @param array $parcel length, width, height, weight
 * @return array
 */
function pg_label_sendcloud_parcel($parcel)
{
    $text = 'pg_label_sendcloud_text';
    $answer = array('weight' => array('value' => pg_label_sendcloud_number($text($parcel, 'weight'), 3), 'unit' => 'kg'));
    $sizes = array();

    foreach (array('length', 'width', 'height') as $side) {
        $sizes[$side] = pg_label_sendcloud_number($text($parcel, $side), 2);
    }

    // Dimensions only when all three are known: some carriers price by
    // volume and refuse a zero side.
    if (min(array_map('floatval', $sizes)) > 0) {
        $answer = array('dimensions' => $sizes + array('unit' => 'cm')) + $answer;
    }

    return $answer;
}

/**
 * The shipment's recipient as a Sendcloud address. Pure.
 *
 * @param mixed $recipient see the header of this file
 * @return array
 */
function pg_label_sendcloud_recipient_address($recipient)
{
    $text = 'pg_label_sendcloud_text';
    $state = strtoupper($text($recipient, 'state_code'));

    return array_filter(array(
        'name'                => mb_substr($text($recipient, 'name'), 0, 75, 'UTF-8'),
        'address_line_1'      => $text($recipient, 'address_1'),
        'address_line_2'      => $text($recipient, 'address_2'),
        'postal_code'         => $text($recipient, 'zip'),
        'city'                => $text($recipient, 'city'),
        'country_code'        => strtoupper($text($recipient, 'country')),
        // An ISO 3166-2 code (IT-RM) only: a plate number or a name is
        // refused by the countries that need one.
        'state_province_code' => (preg_match('/^[A-Z]{2}-[A-Z0-9]{1,3}$/', $state) === 1) ? $state : '',
        'phone_number'        => $text($recipient, 'phone'),
        'email'               => $text($recipient, 'email'),
    ), 'strlen');
}

/**
 * The POST /shipments/announce body of a shipment. Pure.
 *
 * @param array  $shipment see the header of this file
 * @param array  $account
 * @param int    $sender   the sender address id
 * @param string $option   the shipping option code
 * @param array|null $customs pg_label_sendcloud_customs(), null for none
 * @return array
 */
function pg_label_sendcloud_shipment_body($shipment, $account, $sender, $option, $customs = null)
{
    $shipment = is_array($shipment) ? $shipment : array();
    $text = 'pg_label_sendcloud_text';
    $recipient = isset($shipment['recipient']) ? $shipment['recipient'] : array();
    $parcel = isset($shipment['parcel']) ? $shipment['parcel'] : array();

    $body = array(
        'label_details' => ($account['label_format'] === 'zpl') ? array('mime_type' => 'application/zpl', 'dpi' => 203) : array('mime_type' => 'application/pdf', 'dpi' => 72),
        'to_address'    => pg_label_sendcloud_recipient_address($recipient),
        'from_address'  => array('sender_address_id' => (int) $sender),
        'ship_with'     => array('type' => 'shipping_option_code', 'properties' => array('shipping_option_code' => (string) $option)),
        'parcels'       => array(pg_label_sendcloud_parcel($parcel)),
    );

    $order = substr($text($shipment, 'order_number'), 0, 35);

    if ($order !== '') {
        $body['order_number'] = $order;
    }

    $total = isset($shipment['total']) ? (int) $shipment['total'] : 0;
    $currency = strtoupper($text($shipment, 'currency'));

    if (($total > 0) && (preg_match('/^[A-Z]{3}$/', $currency) === 1)) {
        $body['total_order_price'] = array('currency' => $currency, 'value' => number_format($total / 100, 2, '.', ''));
    }

    if (is_array($customs) && is_array($customs['items'])) {
        $body['parcels'][0]['parcel_items'] = $customs['items'];
        $body['customs_information'] = $customs['information'];
    }

    return $body;
}

/**
 * The cheapest shipping option Sendcloud priced that needs no service point,
 * no export documents and is not the free test letter; for a return also
 * one marked for returns. A parcel declared to customs takes an option that
 * needs export documents (Sendcloud makes them from the declaration) and
 * whose incoterm, when it has one, puts the duties on the party the shop's
 * incoterm names. Pure.
 *
 * @param mixed  $options  the data of POST /shipping-options
 * @param bool   $return
 * @param string $incoterm DDU, DAP or DDP for a declared parcel, '' for none
 * @return string the option's code, '' when none
 */
function pg_label_sendcloud_option_pick($options, $return = false, $incoterm = '')
{
    // DDU and DAP leave the duties to the recipient, DDP to the shop.
    $incoterm = strtoupper(trim((string) $incoterm));
    $terms = ($incoterm === 'DDP') ? array('ddp', 'dap_dp') : array('ddu', 'dap', 'dap_np');

    $cheapest = null;

    foreach (is_array($options) ? $options : array() as $option) {
        $code = pg_label_sendcloud_text($option, 'code');
        $needs = (isset($option['requirements']) && is_array($option['requirements'])) ? $option['requirements'] : array();
        $total = isset($option['quotes'][0]['price']['total']) ? pg_label_sendcloud_text($option['quotes'][0]['price']['total'], 'value') : '';
        $term = isset($option['functionalities']) ? strtolower(pg_label_sendcloud_text($option['functionalities'], 'incoterm')) : '';

        if (($code === '') || (strncmp($code, 'sendcloud:letter', 16) === 0) || !empty($option['quote_error'])
            || (($incoterm === '') && !empty($needs['export_documents'])) || (($incoterm !== '') && ($term !== '') && !in_array($term, $terms, true))
            || !empty($needs['is_service_point_required']) || !is_numeric($total)
            || ($return && empty($option['functionalities']['returns']))) {
            continue;
        }

        if (($cheapest === null) || ((float) $total < $cheapest[0])) {
            $cheapest = array((float) $total, $code);
        }
    }

    return ($cheapest === null) ? '' : $cheapest[1];
}

/**
 * The carrier key of a Sendcloud carrier code ("dpd", "dhl_de",
 * "royal_mail"), '' for one the store does not know: DHL Parcel of the
 * Benelux ("dhl") is not DHL's German parcel service. Pure.
 *
 * @param string $code
 * @return string
 */
function pg_label_sendcloud_carrier($code)
{
    $code = strtolower(trim((string) $code));
    $carriers = array(
        'dhl_de'        => 'dhl',
        'dhl_express'   => 'dhl',
        'deutsche_post' => 'deutschepost',
        'ups'           => 'ups',
        'fedex'         => 'fedex',
        'royal_mail'    => 'royalmail',
        'royalmailv2'   => 'royalmail',
    );

    if (isset($carriers[$code])) {
        return $carriers[$code];
    }

    return (preg_match('/^dpd(_[a-z]+)?$/', $code) === 1) ? 'dpd' : '';
}

/**
 * The shipment status of the latest tracking event: its phase, else its
 * exception. Pure.
 *
 * @param mixed $event an event of GET /parcels/tracking/{number}
 * @return string a pg_shipment_statuses() value, '' when unknown
 */
function pg_label_sendcloud_status($event)
{
    $phase = strtolower(pg_label_sendcloud_text($event, 'phase'));
    $exception = strtolower(pg_label_sendcloud_text($event, 'exception'));

    $phases = array(
        'no_label'        => 'created',
        'announcing'      => 'created',
        'announced'       => 'created',
        'ready_to_send'   => 'created',
        'labelled'        => 'created',
        'dropped'         => 'in_transit',
        'accepted'        => 'in_transit',
        'sorted'          => 'in_transit',
        'in_transit'      => 'in_transit',
        'customs'         => 'in_transit',
        'attempted'       => 'in_transit',
        'pup'             => 'in_transit',
        'in_delivery'     => 'out_for_delivery',
        'delivered'       => 'delivered',
        'delivery_forced' => 'delivered',
    );

    if (isset($phases[$phase])) {
        return $phases[$phase];
    }

    // A delay or a change keeps the parcel moving; the rest needs a look.
    $moving = array('changed', 'claimable', 'delay_unknown', 'delivery_delay', 'handover_delay', 'off_radar', 'pup_expiration', 'relabelled', 'sorting_delay');

    if (($exception === '') || ($exception === 'none') || ($exception === 'unknown')) {
        return '';
    }

    if ($exception === 'returned_sender') {
        return 'returned';
    }

    return in_array($exception, $moving, true) ? 'in_transit' : 'exception';
}

/**
 * A reference as a path segment.
 *
 * @return string '' when it cannot be one
 */
function pg_label_sendcloud_id($reference)
{
    $reference = is_scalar($reference) ? trim((string) $reference) : '';

    return (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $reference) === 1) ? $reference : '';
}

/**
 * The first parcel of a shipment answer.
 *
 * @param array $data
 * @return array
 */
function pg_label_sendcloud_first_parcel($data)
{
    return (isset($data['data']['parcels'][0]) && is_array($data['data']['parcels'][0])) ? $data['data']['parcels'][0] : array();
}

/**
 * A shipment read by its reference.
 *
 * @param string $id
 * @param array  $account
 * @return array pg_label_sendcloud_api()
 */
function pg_label_sendcloud_shipment($id, $account)
{
    return pg_label_sendcloud_api('GET', '/shipments/' . rawurlencode($id), $account);
}

/**
 * A parcel's label document.
 *
 * @param string $parcel the parcel id
 * @param string $format pdf or zpl
 * @param array  $account
 * @return array label (bytes, '' when none), log (a sentence when it failed)
 */
function pg_label_sendcloud_download($parcel, $format, $account)
{
    $mime = ($format === 'zpl') ? 'application/zpl' : 'application/pdf';
    $query = ($format === 'zpl') ? '' : '?paper_size=a6';
    $read = pg_label_sendcloud_api('GET', '/parcels/' . rawurlencode($parcel) . '/documents/label' . $query, $account, null, $mime);

    if (!$read['ok'] || !pg_label_document_valid($read['body'], $format)) {
        return array('label' => '', 'log' => 'The Sendcloud label could not be read: ' . (($read['message'] !== '') ? $read['message'] : 'not a ' . strtoupper($format) . ' document') . '.');
    }

    return array('label' => $read['body'], 'log' => '');
}

/**
 * The return id and the parcel id of a return reference
 * (r-<return id>-<parcel id>), null for any other reference. Pure.
 *
 * @param mixed $reference
 * @return array|null return, parcel
 */
function pg_label_sendcloud_return_ids($reference)
{
    $reference = is_scalar($reference) ? trim((string) $reference) : '';

    if (preg_match('/^r-([1-9][0-9]{0,19})-([1-9][0-9]{0,19})$/', $reference, $match) !== 1) {
        return null;
    }

    return array('return' => $match[1], 'parcel' => $match[2]);
}

/**
 * The POST /returns/announce-synchronously body of a return: from the
 * customer (the shipment's recipient) to the sender address. The Returns
 * API takes the whole address, not its id, and numbers, not decimal text.
 * Pure.
 *
 * @param array  $shipment see the header of this file
 * @param array  $sender   a sender address of the account
 * @param string $option   the shipping option code
 * @param array|null $customs pg_label_sendcloud_customs(), null for none
 * @return array
 */
function pg_label_sendcloud_return_body($shipment, $sender, $option)
{
    $text = 'pg_label_sendcloud_text';
    $state = strtoupper($text($sender, 'state_province_code'));
    $parcel = pg_label_sendcloud_parcel(isset($shipment['parcel']) ? $shipment['parcel'] : array());

    $body = array(
        'from_address' => pg_label_sendcloud_recipient_address(isset($shipment['recipient']) ? $shipment['recipient'] : array()),
        'to_address'   => array_filter(array(
            'name'                => mb_substr($text($sender, 'name'), 0, 75, 'UTF-8'),
            'company_name'        => $text($sender, 'company_name'),
            'address_line_1'      => $text($sender, 'address_line_1'),
            'house_number'        => $text($sender, 'house_number'),
            'address_line_2'      => $text($sender, 'address_line_2'),
            'postal_code'         => $text($sender, 'postal_code'),
            'city'                => $text($sender, 'city'),
            'country_code'        => strtoupper($text($sender, 'country_code')),
            'state_province_code' => (preg_match('/^[A-Z]{2}-[A-Z0-9]{1,3}$/', $state) === 1) ? $state : '',
            'phone_number'        => $text($sender, 'phone_number'),
            'email'               => $text($sender, 'email'),
        ), 'strlen'),
        'ship_with'    => array('type' => 'shipping_option_code', 'shipping_option_code' => (string) $option),
        'weight'       => array('value' => (float) $parcel['weight']['value'], 'unit' => 'kg'),
    );

    if (isset($parcel['dimensions'])) {
        $sizes = $parcel['dimensions'];
        $body['dimensions'] = array('length' => (float) $sizes['length'], 'width' => (float) $sizes['width'], 'height' => (float) $sizes['height'], 'unit' => 'cm');
    }

    // The customer prints the label and hands the parcel in.
    $body['delivery_option'] = 'drop_off_point';
    $order = substr($text($shipment, 'order_number'), 0, 35);

    if ($order !== '') {
        $body['order_number'] = $order;
    }

    return $body;
}

/**
 * A return read by its id, asked again a few times while the carrier has not
 * given its tracking number yet.
 *
 * @param string $id
 * @param array  $account
 * @return array pg_label_sendcloud_api(), with return: the return object
 */
function pg_label_sendcloud_return_read($id, $account)
{
    for ($round = 0; ; $round++) {
        $read = pg_label_sendcloud_api('GET', '/returns/' . rawurlencode($id), $account);
        $read['return'] = (isset($read['data']['data']) && is_array($read['data']['data'])) ? $read['data']['data'] : $read['data'];

        if (!$read['ok'] || (pg_label_sendcloud_text($read['return'], 'tracking_number') !== '') || ($round >= 3)) {
            return $read;
        }

        pg_label_sendcloud_sleep(2);
    }
}

/**
 * create() of a return: priced and announced from the customer to the
 * sender address.
 *
 * @param array $shipment
 * @param array $account
 * @return array
 */
function pg_label_sendcloud_return_create($shipment, $account)
{
    // The free letter of test mode is an outgoing option and a return label
    // is a paid one: none is bought in test mode.
    if ($account['mode'] !== 'live') {
        return array('success' => false, 'error' => lang('Sendcloud return labels are made in live mode only: test mode ships unstamped letters, which have no return.'));
    }

    $recipient = isset($shipment['recipient']) ? $shipment['recipient'] : array();
    $customer = strtoupper(pg_label_sendcloud_text($recipient, 'country'));

    if ($customer === '') {
        return array('success' => false, 'error' => lang('Sendcloud needs the country of the shipping address.'));
    }

    $sender = pg_label_sendcloud_sender($account);

    if (!$sender['read']['ok']) {
        return pg_label_sendcloud_failure($sender['read'], false);
    }

    if ($sender['address'] === null) {
        return array('success' => false, 'error' => lang('Sendcloud has no sender address of this id. Add one in the Sendcloud panel.'), 'raw' => $sender['read']['raw']);
    }

    $to = $sender['address'];
    $refusal = pg_label_sendcloud_route_error($customer, pg_label_sendcloud_text($to, 'country_code'), true);

    if ($refusal !== '') {
        return array('success' => false, 'error' => $refusal);
    }

    // The account's option is an outgoing one: only an option asked for
    // this shipment is taken as it is.
    $option = pg_label_sendcloud_text($shipment, 'service');

    if ($option === '') {
        $read = pg_label_sendcloud_api('POST', '/shipping-options', $account, array(
            'from_address'     => array_filter(array('country_code' => $customer, 'postal_code' => pg_label_sendcloud_text($recipient, 'zip')), 'strlen'),
            'to_address'       => array_filter(array('country_code' => strtoupper(pg_label_sendcloud_text($to, 'country_code')), 'postal_code' => pg_label_sendcloud_text($to, 'postal_code')), 'strlen'),
            'parcels'          => array(pg_label_sendcloud_parcel(isset($shipment['parcel']) ? $shipment['parcel'] : array())),
            'functionalities'  => array('returns' => true),
            'calculate_quotes' => true,
        ));

        if (!$read['ok']) {
            return pg_label_sendcloud_failure($read, false);
        }

        $option = pg_label_sendcloud_option_pick(isset($read['data']['data']) ? $read['data']['data'] : array(), true);

        if ($option === '') {
            return array('success' => false, 'error' => lang('Sendcloud found no return option for this shipment.'), 'raw' => $read['raw']);
        }
    }

    // The announcement is the charge: a lost answer or a server error
    // leaves it unknown whether the label was made.
    $read = pg_label_sendcloud_api('POST', '/returns/announce-synchronously', $account, pg_label_sendcloud_return_body($shipment, $to, $option));

    if (!$read['ok']) {
        return pg_label_sendcloud_failure($read, true);
    }

    $return_id = pg_label_sendcloud_text($read['data'], 'return_id');
    $parcel_id = pg_label_sendcloud_text($read['data'], 'parcel_id');
    $reference = pg_label_sendcloud_return_ids('r-' . $return_id . '-' . $parcel_id) ? 'r-' . $return_id . '-' . $parcel_id : '';

    // Without a reference or number the label may be made all the same:
    // pg_label_answer() makes this an unclear failure.
    $answer = array(
        'success'         => true,
        'reference'       => $reference,
        'tracking_number' => '',
        'status'          => 'created',
        'log'             => array('Sendcloud return ' . $return_id . ' made with ' . $option . '.'),
        'raw'             => $read['raw'],
    );

    if ($reference === '') {
        return $answer;
    }

    $details = pg_label_sendcloud_return_read($return_id, $account);

    if ($details['ok']) {
        $tracking = pg_label_sendcloud_text($details['return'], 'tracking_url');
        $code = isset($details['return']['shipping_option']) ? pg_label_sendcloud_text($details['return']['shipping_option'], 'code') : '';
        $answer['tracking_number'] = pg_label_sendcloud_text($details['return'], 'tracking_number');
        $answer['carrier'] = pg_label_sendcloud_carrier(strtok(($code !== '') ? $code : $option, ':'));
        $answer['tracking_url'] = (strncmp($tracking, 'https://', 8) === 0) ? $tracking : '';
    } else {
        $answer['log'][] = 'The Sendcloud return could not be read: ' . $details['message'] . '.';
    }

    $format = ($account['label_format'] === 'zpl') ? 'zpl' : 'pdf';
    $download = pg_label_sendcloud_download($parcel_id, $format, $account);

    if ($download['label'] !== '') {
        $answer['label'] = $download['label'];
        $answer['label_format'] = $format;
    } else {
        $answer['log'][] = $download['log'];
    }

    return $answer;
}

function pg_label_sendcloud_test($account)
{
    $sender = pg_label_sendcloud_sender($account);

    if (!$sender['read']['ok']) {
        return pg_label_sendcloud_failure($sender['read'], false);
    }

    if ($sender['address'] === null) {
        return array('success' => false, 'error' => lang('Sendcloud has no sender address of this id. Add one in the Sendcloud panel.'), 'raw' => $sender['read']['raw']);
    }

    $address = $sender['address'];

    return array(
        'success' => true,
        'account' => trim(pg_label_sendcloud_text($address, 'company_name') . ', ' . pg_label_sendcloud_text($address, 'city') . ' ' . pg_label_sendcloud_text($address, 'country_code'), ', '),
        'log'     => array('Sendcloud accepted the API keys' . (($account['mode'] === 'live') ? '' : ' in test mode') . '; sender address ' . pg_label_sendcloud_text($address, 'id') . '.'),
    );
}

function pg_label_sendcloud_create($shipment, $account)
{
    if (is_array($shipment) && !empty($shipment['return'])) {
        return pg_label_sendcloud_return_create($shipment, $account);
    }

    $recipient = (is_array($shipment) && isset($shipment['recipient'])) ? $shipment['recipient'] : array();
    $to = strtoupper(pg_label_sendcloud_text($recipient, 'country'));

    if ($to === '') {
        return array('success' => false, 'error' => lang('Sendcloud needs the country of the shipping address.'));
    }

    $sender = pg_label_sendcloud_sender($account);

    if (!$sender['read']['ok']) {
        return pg_label_sendcloud_failure($sender['read'], false);
    }

    if ($sender['address'] === null) {
        return array('success' => false, 'error' => lang('Sendcloud has no sender address of this id. Add one in the Sendcloud panel.'), 'raw' => $sender['read']['raw']);
    }

    $from = $sender['address'];
    $refusal = pg_label_sendcloud_route_error(pg_label_sendcloud_text($from, 'country_code'), $to, false, pg_label_sendcloud_customs_filled($account));

    if ($refusal !== '') {
        return array('success' => false, 'error' => $refusal);
    }

    // A parcel that leaves the customs area carries its lines; a line with
    // no HS code anywhere refuses the label before anything is priced.
    $customs = pg_label_sendcloud_customs($shipment, $account, pg_label_sendcloud_text($from, 'country_code'));

    if ($customs['error'] !== '') {
        return array('success' => false, 'error' => $customs['error'], 'log' => array('Sendcloud shipment not announced: the customs data is incomplete.'));
    }

    $incoterm = ($customs['items'] !== null) ? strtoupper(pg_label_sendcloud_text($account, 'customs_incoterm')) : '';
    $incoterm = (($customs['items'] !== null) && !in_array($incoterm, array('DDU', 'DAP', 'DDP'), true)) ? 'DDU' : $incoterm;

    $parcel = pg_label_sendcloud_parcel(isset($shipment['parcel']) ? $shipment['parcel'] : array());

    if ($account['mode'] !== 'live') {
        $option = 'sendcloud:letter';
    } elseif (pg_label_sendcloud_text($shipment, 'service') !== '') {
        $option = pg_label_sendcloud_text($shipment, 'service');
    } elseif (trim((string) $account['service']) !== '') {
        $option = trim((string) $account['service']);
    } else {
        // Pricing costs nothing: a failure here buys nothing.
        $read = pg_label_sendcloud_api('POST', '/shipping-options', $account, array(
            'from_address'     => array_filter(array('country_code' => strtoupper(pg_label_sendcloud_text($from, 'country_code')), 'postal_code' => pg_label_sendcloud_text($from, 'postal_code')), 'strlen'),
            'to_address'       => array_filter(array('country_code' => $to, 'postal_code' => pg_label_sendcloud_text($recipient, 'zip')), 'strlen'),
            'parcels'          => array($parcel),
            'calculate_quotes' => true,
        ));

        if (!$read['ok']) {
            return pg_label_sendcloud_failure($read, false);
        }

        $option = pg_label_sendcloud_option_pick(isset($read['data']['data']) ? $read['data']['data'] : array(), false, $incoterm);

        if ($option === '') {
            $words = pg_label_sendcloud_text($read['data'], 'message');

            return array(
                'success' => false,
                'error'   => lang('Sendcloud found no shipping option for this shipment.') . (($words !== '') ? ' (' . mb_substr($words, 0, 300, 'UTF-8') . ')' : ''),
                'raw'     => $read['raw'],
            );
        }
    }

    // The announcement is the charge: a lost answer or a server error
    // leaves it unknown whether the label was made.
    $body = pg_label_sendcloud_shipment_body($shipment, $account, pg_label_sendcloud_text($from, 'id'), $option, $customs);
    $read = pg_label_sendcloud_api('POST', '/shipments/announce', $account, $body);

    if (!$read['ok']) {
        return pg_label_sendcloud_failure($read, true);
    }

    $reference = pg_label_sendcloud_id(isset($read['data']['data']) ? pg_label_sendcloud_text($read['data']['data'], 'id') : '');
    $first = pg_label_sendcloud_first_parcel($read['data']);
    $status = isset($first['status']) ? strtoupper(pg_label_sendcloud_text($first['status'], 'code')) : '';

    // A carrier's refusal still answers 200; such a shipment is not charged.
    if (($status === 'ANNOUNCEMENT_FAILED') || (!empty($read['data']['data']['errors']) && ($status !== 'READY_TO_SEND'))) {
        $words = pg_label_sendcloud_error_texts(isset($read['data']['data']) ? $read['data']['data'] : array());

        return array(
            'success' => false,
            'error'   => lang('Sendcloud could not make the label.') . ($words ? ' (' . mb_substr(implode(' ', $words), 0, 300, 'UTF-8') . ')' : ''),
            'log'     => array('Sendcloud shipment ' . $reference . ' failed with ' . $option . '.'),
            'raw'     => $read['raw'],
        );
    }

    $carrier = isset($read['data']['data']['carrier']) ? pg_label_sendcloud_text($read['data']['data']['carrier'], 'code') : '';
    $tracking = pg_label_sendcloud_text($first, 'tracking_url');

    // Without a reference or number the label may be made all the same:
    // pg_label_answer() makes this an unclear failure.
    $answer = array(
        'success'         => true,
        'reference'       => $reference,
        'tracking_number' => pg_label_sendcloud_text($first, 'tracking_number'),
        'carrier'         => pg_label_sendcloud_carrier($carrier),
        'tracking_url'    => (strncmp($tracking, 'https://', 8) === 0) ? $tracking : '',
        'status'          => 'created',
        'log'             => array('Sendcloud label ' . $reference . ' made with ' . $option . (($account['mode'] === 'live') ? '' : ' (test)') . '.'),
        'raw'             => $read['raw'],
    );

    $format = ($account['label_format'] === 'zpl') ? 'zpl' : 'pdf';
    $file = pg_label_sendcloud_text($first, 'label_file');
    $document = ($file !== '') ? base64_decode($file, true) : false;

    // The label is fetched again by label() when it cannot be had now.
    if (($document !== false) && pg_label_document_valid($document, $format)) {
        $answer['label'] = $document;
        $answer['label_format'] = $format;
    } elseif (pg_label_sendcloud_text($first, 'id') !== '') {
        $download = pg_label_sendcloud_download(pg_label_sendcloud_text($first, 'id'), $format, $account);

        if ($download['label'] !== '') {
            $answer['label'] = $download['label'];
            $answer['label_format'] = $format;
        } else {
            $answer['log'][] = $download['log'];
        }
    }

    return $answer;
}

function pg_label_sendcloud_label($reference, $format, $account)
{
    $return = pg_label_sendcloud_return_ids($reference);

    // A return's label is its own parcel's document.
    if ($return !== null) {
        $download = pg_label_sendcloud_download($return['parcel'], $format, $account);

        if ($download['label'] === '') {
            return array('success' => false, 'error' => lang('The label could not be read from Sendcloud.'), 'log' => array($download['log']));
        }

        return array('success' => true, 'reference' => trim((string) $reference), 'label' => $download['label'], 'label_format' => $format);
    }

    $id = pg_label_sendcloud_id($reference);

    if ($id === '') {
        return array('success' => false, 'error' => lang('No shipment of the carrier label service is known for this parcel.'));
    }

    $read = pg_label_sendcloud_shipment($id, $account);

    if (!$read['ok']) {
        return pg_label_sendcloud_failure($read, false);
    }

    $first = pg_label_sendcloud_first_parcel($read['data']);
    $status = isset($first['status']) ? strtoupper(pg_label_sendcloud_text($first['status'], 'code')) : '';

    if ((pg_label_sendcloud_text($first, 'id') === '') || in_array($status, array('CANCELLED', 'ANNOUNCEMENT_FAILED'), true)) {
        return array('success' => false, 'error' => lang('Sendcloud has no label for this shipment: it was cancelled or never made.'), 'raw' => $read['raw']);
    }

    $download = pg_label_sendcloud_download(pg_label_sendcloud_text($first, 'id'), $format, $account);

    if ($download['label'] === '') {
        return array('success' => false, 'error' => lang('The label could not be read from Sendcloud.'), 'log' => array($download['log']));
    }

    return array('success' => true, 'reference' => $id, 'label' => $download['label'], 'label_format' => $format);
}

function pg_label_sendcloud_cancel($reference, $account)
{
    $return = pg_label_sendcloud_return_ids($reference);

    if ($return !== null) {
        return pg_label_sendcloud_return_cancel($return['return'], trim((string) $reference), $account);
    }

    $id = pg_label_sendcloud_id($reference);

    // A return reference that names no parcel is not one of the store's.
    if (($id === '') || (strncmp($id, 'r-', 2) === 0)) {
        return array('success' => false, 'error' => lang('No shipment of the carrier label service is known for this parcel.'));
    }

    // 200 cancelled; 202 queued: Sendcloud watches the label 14 days and
    // cancels it while the carrier has not scanned it. A 409 (already
    // cancelled, delivered, older than 42 days) refuses.
    $read = pg_label_sendcloud_api('POST', '/shipments/' . rawurlencode($id) . '/cancel', $account, array());

    if (!$read['ok']) {
        return pg_label_sendcloud_failure($read, true);
    }

    $status = isset($read['data']['data']) ? pg_label_sendcloud_text($read['data']['data'], 'status') : '';

    return array('success' => true, 'reference' => $id, 'log' => array('Sendcloud cancellation of shipment ' . $id . ' is ' . (($status !== '') ? $status : 'requested') . '.'));
}

/**
 * cancel() of a return. Not every carrier cancels a return label: a 202 is
 * a request Sendcloud passes on and counts as done; a 409 (the return
 * cannot be cancelled) refuses with Sendcloud's reason.
 *
 * @param string $id        the return id
 * @param string $reference
 * @param array  $account
 * @return array
 */
function pg_label_sendcloud_return_cancel($id, $reference, $account)
{
    $read = pg_label_sendcloud_api('PATCH', '/returns/' . rawurlencode($id) . '/cancel', $account);

    if (!$read['ok']) {
        return pg_label_sendcloud_failure($read, true);
    }

    $words = pg_label_sendcloud_text($read['data'], 'message');

    return array('success' => true, 'reference' => $reference, 'log' => array('Sendcloud cancellation of return ' . $id . ': ' . (($words !== '') ? mb_substr($words, 0, 200, 'UTF-8') : 'requested') . '.'));
}

function pg_label_sendcloud_track($reference, $account)
{
    $id = pg_label_sendcloud_id($reference);

    if ($id === '') {
        return array('success' => false, 'error' => lang('No shipment of the carrier label service is known for this parcel.'));
    }

    $read = pg_label_sendcloud_shipment($id, $account);

    if (!$read['ok']) {
        return pg_label_sendcloud_failure($read, false);
    }

    $first = pg_label_sendcloud_first_parcel($read['data']);
    $number = pg_label_sendcloud_text($first, 'tracking_number');

    if ($number === '') {
        return array('success' => false, 'error' => lang('Sendcloud gives no status for this shipment yet.'), 'raw' => $read['raw']);
    }

    $read = pg_label_sendcloud_api('GET', '/parcels/tracking/' . rawurlencode($number), $account);

    if (!$read['ok']) {
        return pg_label_sendcloud_failure($read, false);
    }

    $data = isset($read['data']['data']) && is_array($read['data']['data']) ? $read['data']['data'] : $read['data'];
    $events = (isset($data['events']) && is_array($data['events'])) ? $data['events'] : array();
    $event = $events ? reset($events) : array();
    $status = pg_label_sendcloud_status($event);

    if ($status === '') {
        return array('success' => false, 'error' => lang('Sendcloud gives no status for this shipment yet.'), 'raw' => $read['raw']);
    }

    $time = strtotime(pg_label_sendcloud_text($event, 'event_at'));
    $tracking = pg_label_sendcloud_text($first, 'tracking_url');

    return array(
        'success'         => true,
        'reference'       => $id,
        'status'          => $status,
        'status_at'       => ($time === false) ? 0 : max(0, $time),
        'tracking_number' => $number,
        'tracking_url'    => (strncmp($tracking, 'https://', 8) === 0) ? $tracking : '',
    );
}

/**
 * The tracking phase (or exception) of a parcel status id of the webhook,
 * read through pg_label_sendcloud_status(), so both paths name a status
 * alike. A status the store has no use for (cancelled, announcement
 * failed, unknown ids) is '' and records nothing. Pure.
 *
 * @param mixed $id parcel.status.id
 * @return string a pg_shipment_statuses() value, '' when unknown
 */
function pg_label_sendcloud_webhook_status($id)
{
    $events = array(
        1000 => array('phase' => 'ready_to_send'),
        1001 => array('phase' => 'announcing'),
        1    => array('phase' => 'announced'),
        22   => array('phase' => 'accepted'),
        3    => array('phase' => 'in_transit'),
        91   => array('phase' => 'in_transit'),
        5    => array('phase' => 'sorted'),
        7    => array('phase' => 'sorted'),
        8    => array('phase' => 'attempted'),
        12   => array('phase' => 'pup'),
        92   => array('phase' => 'in_delivery'),
        11   => array('phase' => 'delivered'),
        93   => array('phase' => 'delivered'),
        4    => array('exception' => 'delivery_delay'),
        6    => array('exception' => 'sorting_delay'),
        13   => array('exception' => 'handover_delay'),
        15   => array('exception' => 'collection_error'),
        80   => array('exception' => 'undeliverable'),
    );
    $id = is_numeric($id) ? (int) $id : -1;

    return isset($events[$id]) ? pg_label_sendcloud_status($events[$id]) : '';
}

/**
 * A webhook time in seconds: Sendcloud's own timestamp is in milliseconds,
 * the carrier's in seconds. Pure.
 *
 * @param mixed $value
 * @return int 0 when none
 */
function pg_label_sendcloud_webhook_time($value)
{
    $value = is_numeric($value) ? (float) $value : 0.0;

    return (int) (($value > 99999999999) ? floor($value / 1000) : max(0, $value));
}

/**
 * Reads a webhook message: only parcel_status_changed carries a status.
 * The parcel's shipment_uuid is the shipment id create() stored as the
 * reference. A return parcel gives nothing: its label's reference is
 * r-<return id>-<parcel id> and a return records no arrival.
 *
 * @param string $body
 * @return array|false list of reference, is_return, answer; false for a
 *                     body that is not a message
 */
function pg_label_sendcloud_webhook_read($body)
{
    $message = json_decode((string) $body, true);

    if (!is_array($message) || !isset($message['action']) || !is_string($message['action'])) {
        return false;
    }

    $parcel = (isset($message['parcel']) && is_array($message['parcel'])) ? $message['parcel'] : array();

    if ((strtolower(trim($message['action'])) !== 'parcel_status_changed') || !empty($parcel['is_return'])) {
        return array();
    }

    $id = pg_label_sendcloud_id(isset($parcel['shipment_uuid']) ? $parcel['shipment_uuid'] : '');

    if ($id === '') {
        return array();
    }

    $status = pg_label_sendcloud_webhook_status(isset($parcel['status']['id']) ? $parcel['status']['id'] : null);
    $time = pg_label_sendcloud_webhook_time(isset($message['carrier_status_change_timestamp']) ? $message['carrier_status_change_timestamp'] : 0);
    $time = ($time > 0) ? $time : pg_label_sendcloud_webhook_time(isset($message['timestamp']) ? $message['timestamp'] : 0);
    $tracking = pg_label_sendcloud_text($parcel, 'tracking_url');

    $answer = ($status === '')
        ? array('success' => false, 'error' => lang('Sendcloud gives no status for this shipment yet.'))
        : array(
            'success'         => true,
            'reference'       => $id,
            'status'          => $status,
            'status_at'       => $time,
            'tracking_number' => pg_label_sendcloud_text($parcel, 'tracking_number'),
            'tracking_url'    => (strncmp($tracking, 'https://', 8) === 0) ? $tracking : '',
        );

    return array(array('reference' => $id, 'is_return' => false, 'answer' => $answer));
}

/**
 * Whether a message is Sendcloud's: its Sendcloud-Signature header is the
 * HMAC-SHA256 (hex) of the raw body with the integration's secret key.
 *
 * @param string $raw
 * @param array  $headers lower-case name => value
 * @param array  $account
 * @return bool
 */
function pg_label_sendcloud_webhook_verify($raw, $headers, $account)
{
    $key = isset($account['secret_key']) ? (string) $account['secret_key'] : '';
    $signature = (isset($headers['sendcloud-signature']) && is_string($headers['sendcloud-signature'])) ? strtolower(trim($headers['sendcloud-signature'])) : '';

    return ($key !== '') && ($signature !== '') && hash_equals(hash_hmac('sha256', (string) $raw, $key), $signature);
}

/**
 * Whether an integration's webhook address is this store's.
 *
 * @param mixed $integration
 * @return bool
 */
function pg_label_sendcloud_webhook_ours($integration)
{
    $url = pg_label_sendcloud_text($integration, 'webhook_url');

    return ($url === '') || (strpos($url, 'carrier_webhook.php?driver=sendcloud&') !== false);
}

/**
 * Sets the store's address as the webhook of the account's API integration
 * (PATCH /integrations/{id}), the one place Sendcloud keeps it. The keys
 * belong to an integration of type api, which the list does not mark, so
 * the only such integration is taken; with none or several, or one that
 * already sends to another address (never overwritten), the Shipping card
 * shows the address to add by hand.
 *
 * @param string $url
 * @param array  $account
 * @return array success, id (the integration id), error
 */
function pg_label_sendcloud_webhook_register($url, $account)
{
    $read = pg_label_sendcloud_api('GET', '/integrations', $account);

    if (!$read['ok']) {
        return array('success' => false, 'id' => '', 'error' => pg_label_sendcloud_failure($read, false)['error']);
    }

    $list = (isset($read['data']['data']) && is_array($read['data']['data'])) ? $read['data']['data'] : array();
    $found = array_values(array_filter($list, function ($integration) {
        return strtolower(pg_label_sendcloud_text($integration, 'type')) === 'api';
    }));

    if (count($found) !== 1) {
        return array('success' => false, 'id' => '', 'error' => lang('Sendcloud has no single API integration these keys could belong to.'));
    }

    $id = pg_label_sendcloud_id(pg_label_sendcloud_text($found[0], 'id'));

    if ($id === '') {
        return array('success' => false, 'id' => '', 'error' => lang('Sendcloud refused the request.'));
    }

    if (!empty($found[0]['webhook_active']) && !pg_label_sendcloud_webhook_ours($found[0])) {
        return array('success' => false, 'id' => '', 'error' => lang('The Sendcloud API integration already sends its webhook to another address.'));
    }

    $read = pg_label_sendcloud_api('PATCH', '/integrations/' . $id, $account, array('webhook_active' => true, 'webhook_url' => (string) $url));

    if (!$read['ok']) {
        return array('success' => false, 'id' => '', 'error' => pg_label_sendcloud_failure($read, false)['error']);
    }

    return array('success' => true, 'id' => $id, 'error' => '');
}

/**
 * Switches the integration's webhook off, if it still sends to this store.
 * An integration Sendcloud no longer has (404), or one whose address was
 * changed to another since, counts as removed.
 *
 * @param string $id
 * @param array  $account
 * @return array success, error
 */
function pg_label_sendcloud_webhook_remove($id, $account)
{
    $id = pg_label_sendcloud_id($id);

    if (($id === '') || !ctype_digit($id)) {
        return array('success' => false, 'error' => lang('Sendcloud refused the request.'));
    }

    $read = pg_label_sendcloud_api('GET', '/integrations/' . $id, $account);

    if (!$read['ok']) {
        return ($read['status'] === 404) ? array('success' => true, 'error' => '') : array('success' => false, 'error' => pg_label_sendcloud_failure($read, false)['error']);
    }

    $integration = (isset($read['data']['data']) && is_array($read['data']['data'])) ? $read['data']['data'] : $read['data'];

    if (!pg_label_sendcloud_webhook_ours($integration)) {
        return array('success' => true, 'error' => '');
    }

    $read = pg_label_sendcloud_api('PATCH', '/integrations/' . $id, $account, array('webhook_active' => false, 'webhook_url' => null));

    if (!$read['ok'] && ($read['status'] !== 404)) {
        return array('success' => false, 'error' => pg_label_sendcloud_failure($read, false)['error']);
    }

    return array('success' => true, 'error' => '');
}

/**
 * The carriers of the Pickups API: true for one this driver books, false
 * for one whose pickup needs fields the store does not keep (Correos'
 * volumetric kind, the DHL Parcel GB account numbers, FedEx's location
 * details, UPS's destination and service per item). Any other carrier has
 * no pickup at Sendcloud.
 *
 * @return bool[] carrier code => bookable
 */
function pg_label_sendcloud_pickup_carriers()
{
    return array(
        'brt'               => true,
        'correos_express'   => true,
        'dhl'               => true,
        'dhl_de'            => true,
        'dhl_express'       => true,
        'dhl_parcel_iberia' => true,
        'dpd'               => true,
        'dpd_at'            => true,
        'gls_it'            => true,
        'hermes_de'         => true,
        'poste_it_delivery' => true,
        'correos'           => false,
        'dhl_parcel_gb'     => false,
        'fedex'             => false,
        'ups'               => false,
    );
}

/**
 * A parcel's weight in kg, null when the answer gives none. Pure.
 *
 * @param mixed $parcel a parcel of a shipment answer
 * @return float|null
 */
function pg_label_sendcloud_parcel_kg($parcel)
{
    $weight = (is_array($parcel) && isset($parcel['weight'])) ? $parcel['weight'] : null;
    $value = is_array($weight) ? pg_label_sendcloud_text($weight, 'value') : (is_scalar($weight) ? trim((string) $weight) : '');
    $units = array('kg' => 1, 'g' => 0.001, 'lbs' => 0.45359237, 'oz' => 0.028349523125);
    $unit = is_array($weight) ? strtolower(pg_label_sendcloud_text($weight, 'unit')) : 'kg';
    $unit = ($unit === '') ? 'kg' : $unit;

    if (!is_numeric($value) || ((float) $value <= 0) || !isset($units[$unit])) {
        return null;
    }

    return (float) $value * $units[$unit];
}

/**
 * The POST /pickups body: the carrier and its contract, the sender address
 * as the pickup address, one time slot in UTC and the parcels as one item
 * (the shape of the reference's examples). Pure.
 *
 * @param string   $carrier  the Sendcloud carrier code
 * @param int|null $contract the contract id, null when the shipments name none
 * @param array    $sender   a sender address of the account
 * @param int      $count    parcels
 * @param float    $kg       their total weight
 * @param int      $start    unix time
 * @param int      $end      unix time
 * @return array
 */
function pg_label_sendcloud_pickup_body($carrier, $contract, $sender, $count, $kg, $start, $end)
{
    $text = 'pg_label_sendcloud_text';
    $state = strtoupper($text($sender, 'state_province_code'));
    $body = array('carrier_code' => (string) $carrier);

    if ($contract !== null) {
        $body['contract_id'] = (int) $contract;
    }

    $body['address'] = array_filter(array(
        'name'                => $text($sender, 'name'),
        'company_name'        => $text($sender, 'company_name'),
        'address_line_1'      => $text($sender, 'address_line_1'),
        'house_number'        => $text($sender, 'house_number'),
        'address_line_2'      => $text($sender, 'address_line_2'),
        'postal_code'         => $text($sender, 'postal_code'),
        'city'                => $text($sender, 'city'),
        'country_code'        => strtoupper($text($sender, 'country_code')),
        // BRT, GLS Italy and Poste Italiane need an ISO 3166-2 code.
        'state_province_code' => (preg_match('/^[A-Z]{2}-[A-Z0-9]{1,3}$/', $state) === 1) ? $state : '',
        'phone_number'        => $text($sender, 'phone_number'),
        'email'               => $text($sender, 'email'),
    ), 'strlen');
    $body['time_slots'] = array(array(
        'start_at' => gmdate('Y-m-d\TH:i:s\Z', (int) $start),
        'end_at'   => gmdate('Y-m-d\TH:i:s\Z', (int) $end),
    ));
    $body['items'] = array(array(
        'quantity'       => (int) $count,
        'container_type' => 'parcel',
        'total_weight'   => array('value' => number_format(max(0.001, (float) $kg), 3, '.', ''), 'unit' => 'kg'),
    ));

    return $body;
}

/**
 * A pickup answer read: ANNOUNCING (sent on to the carrier) and CREATED are
 * booked, the reference is Sendcloud's pickup id; FAILED and CANCELLED are
 * the carrier's refusal. Pure.
 *
 * @param array $data the answer
 * @param array $raw
 * @return array
 */
function pg_label_sendcloud_pickup_answer($data, $raw)
{
    $pickup = (isset($data['data']) && is_array($data['data'])) ? $data['data'] : array();
    $id = pg_label_sendcloud_text($pickup, 'id');
    $status = strtoupper(pg_label_sendcloud_text($pickup, 'status'));

    if (in_array($status, array('FAILED', 'CANCELLED'), true)) {
        $words = pg_label_sendcloud_error_texts($data);

        return array(
            'success' => false,
            'error'   => lang('The carrier refused the pickup request.') . ($words ? ' (' . mb_substr(implode(' ', $words), 0, 300, 'UTF-8') . ')' : ''),
            'log'     => array('Sendcloud pickup ' . $id . ' is ' . $status . '.'),
            'raw'     => $raw,
        );
    }

    if (($id === '') || (strlen($id) > 64) || !in_array($status, array('ANNOUNCING', 'CREATED'), true)) {
        return array('success' => false, 'unclear' => true, 'error' => lang('The carrier label service gave an answer that cannot be read. Check the shipment on the service\'s own screen before trying again.'), 'raw' => $raw);
    }

    return array(
        'success'   => true,
        'reference' => $id,
        'log'       => array('Sendcloud pickup ' . $id . ' is ' . $status . ' at ' . pg_label_sendcloud_text($pickup, 'carrier_code') . '.'),
        'raw'       => $raw,
    );
}

/**
 * Asks the carrier to collect labels: every shipment must be a live label
 * of one carrier and contract that has a pickup at Sendcloud; the weight is
 * the sum of the parcels'. The sender address of the account is where the
 * courier comes.
 *
 * @param array $request references (shipment ids), start, end (unix time)
 * @param array $account
 * @return array
 */
function pg_label_sendcloud_pickup($request, $account)
{
    $ids = array();

    foreach ((isset($request['references']) && is_array($request['references'])) ? $request['references'] : array() as $reference) {
        // A return is collected from the customer, never by a pickup.
        if (pg_label_sendcloud_return_ids($reference) !== null) {
            return array('success' => false, 'error' => lang('A return label cannot be collected by a pickup request.'));
        }

        $id = pg_label_sendcloud_id($reference);

        if ($id !== '') {
            $ids[$id] = $id;
        }
    }

    $start = isset($request['start']) ? (int) $request['start'] : 0;
    $end = isset($request['end']) ? (int) $request['end'] : 0;

    if (!$ids || ($start <= 0) || ($end <= $start)) {
        return array('success' => false, 'error' => lang('Choose the labels, the date and the time window of the pickup.'));
    }

    $groups = array();
    $count = 0;
    $kg = 0.0;

    foreach ($ids as $id) {
        $read = pg_label_sendcloud_shipment($id, $account);

        if (!$read['ok']) {
            return pg_label_sendcloud_failure($read, false);
        }

        $shipment = (isset($read['data']['data']) && is_array($read['data']['data'])) ? $read['data']['data'] : array();
        $parcels = (isset($shipment['parcels']) && is_array($shipment['parcels'])) ? $shipment['parcels'] : array();
        $option = isset($shipment['ship_with']['properties']) ? $shipment['ship_with']['properties'] : array();
        $carrier = isset($shipment['carrier']) ? strtolower(pg_label_sendcloud_text($shipment['carrier'], 'code')) : '';
        $carrier = ($carrier !== '') ? $carrier : strtolower(strtok(pg_label_sendcloud_text($option, 'shipping_option_code'), ':'));
        $contract = pg_label_sendcloud_text($option, 'contract_id');

        foreach ($parcels as $parcel) {
            $status = (is_array($parcel) && isset($parcel['status'])) ? strtoupper(pg_label_sendcloud_text($parcel['status'], 'code')) : '';

            if (in_array($status, array('CANCELLED', 'ANNOUNCEMENT_FAILED'), true)) {
                $parcels = array();
                break;
            }
        }

        if (!$parcels) {
            return array('success' => false, 'error' => lang('A label of this pickup is not a valid label at Sendcloud (cancelled or never made).'), 'raw' => $read['raw']);
        }

        $carriers = pg_label_sendcloud_pickup_carriers();

        if (!isset($carriers[$carrier])) {
            return array('success' => false, 'error' => lang('Sendcloud offers no pickup request for this carrier: hand the parcels in at the carrier.') . (($carrier !== '') ? ' (' . $carrier . ')' : ''), 'raw' => $read['raw']);
        }

        if (!$carriers[$carrier]) {
            return array('success' => false, 'error' => lang('Sendcloud\'s pickup for this carrier needs details the store does not send: book it in the Sendcloud panel.') . ' (' . $carrier . ')', 'raw' => $read['raw']);
        }

        foreach ($parcels as $parcel) {
            $weight = pg_label_sendcloud_parcel_kg($parcel);

            if ($weight === null) {
                return array('success' => false, 'error' => lang('Sendcloud does not give the weight of a parcel of this pickup.'), 'raw' => $read['raw']);
            }

            $count++;
            $kg += $weight;
        }

        $groups[$carrier . ' ' . $contract] = array($carrier, ctype_digit($contract) ? (int) $contract : null);
    }

    // One pickup is one carrier and one contract.
    if (count($groups) !== 1) {
        return array('success' => false, 'error' => lang('These labels are with more than one carrier or contract at Sendcloud: request a pickup for each carrier separately.'));
    }

    $sender = pg_label_sendcloud_sender($account);

    if (!$sender['read']['ok']) {
        return pg_label_sendcloud_failure($sender['read'], false);
    }

    if ($sender['address'] === null) {
        return array('success' => false, 'error' => lang('Sendcloud has no sender address of this id. Add one in the Sendcloud panel.'), 'raw' => $sender['read']['raw']);
    }

    $group = reset($groups);

    // A booked pickup may cost money: no clear answer is unclear.
    $read = pg_label_sendcloud_api('POST', '/pickups', $account, pg_label_sendcloud_pickup_body($group[0], $group[1], $sender['address'], $count, $kg, $start, $end));

    if (!$read['ok']) {
        return pg_label_sendcloud_failure($read, true);
    }

    return pg_label_sendcloud_pickup_answer($read['data'], $read['raw']);
}
