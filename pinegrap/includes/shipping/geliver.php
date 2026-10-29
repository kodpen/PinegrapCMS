<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Geliver label driver: Geliver is a Turkish shipping aggregator with one
 * REST API (https://api.geliver.io/api/v1, a bearer token) in front of
 * several carriers. The driver follows the two-step flow of its reference
 * (https://docs.geliver.io): POST /shipments opens a shipment and collects
 * the carriers' offers, POST /transactions accepts one and buys its label.
 * A test shipment ("test": true) is free and needs no approved account.
 *
 * The contract is the one in includes/fn/carrier_labels.php. The shipment
 * create() takes:
 *
 *   order_number  string  the store's order number
 *   recipient     array   name, email, phone, address_1, address_2, city
 *                         (the district), state (the province), state_code
 *                         (the province's licence plate number), zip,
 *                         country (ISO 3166-1 alpha-2, TR when empty)
 *   parcel        array   length, width, height (cm), weight (kg)
 *   items         array   list of title, quantity
 *   total         int     the order's total in minor units
 *   currency      string  ISO 4217, TRY when empty
 *   service       string  a Geliver service code (YURTICI_STANDART) to take
 *                         instead of the account's one; '' for the account's
 *   return        bool    a return shipment: collected from the recipient
 *                         and delivered to the sender address
 *
 * The offer accepted is the one of the service asked for, else the cheapest.
 * $reference is Geliver's shipment id.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

function pg_label_geliver_info()
{
    return array(
        'carriers' => array_values(array_unique(pg_label_geliver_carriers())),
        'formats'  => array('pdf'),
    );
}

function pg_label_geliver_fields()
{
    return array(
        array(
            'name'     => 'api_token',
            'type'     => 'secret',
            'label'    => lang('API Token'),
            'help'     => lang('Created in the Geliver panel under Settings, API Tokens; it is shown only once there.'),
            'required' => true,
        ),
        array(
            'name'    => 'mode',
            'type'    => 'select',
            'label'   => lang('Mode'),
            'help'    => lang('Test shipments are free and are never collected.'),
            'options' => array('live' => lang('Live'), 'test' => lang('Test')),
            'default' => 'test',
        ),
        array(
            'name'       => 'sender_address',
            'label'      => lang('Sender Address ID'),
            'help'       => lang('The ID of a sender address in the Geliver panel. Empty uses the default sender address there.'),
            'max_length' => 64,
        ),
        array(
            'name'       => 'service',
            'label'      => lang('Service Code'),
            'help'       => lang('A Geliver service code such as YURTICI_STANDART. Empty takes the cheapest offer.'),
            'max_length' => 64,
        ),
    );
}

/**
 * Geliver's provider codes of the carriers the store knows, code => carrier.
 *
 * @return array
 */
function pg_label_geliver_carriers()
{
    return array(
        'YURTICI' => 'yurtici',
        'SURAT'   => 'surat',
        'ARAS'    => 'aras',
        'MNG'     => 'mng',
        'PTT'     => 'ptt',
    );
}

/**
 * The HTTP transport: pg_curl_run() unless one is set in its place (the
 * tests). A set transport is also asked instead of waiting between reads of
 * the offers.
 *
 * @param callable|null $replace function ($method, $url, $headers, $body)
 *                               answering status, body, error, sent
 * @param bool          $reset   true to go back to pg_curl_run()
 * @return callable|null the transport set, null for pg_curl_run()
 */
function pg_label_geliver_transport($replace = null, $reset = false)
{
    static $transport = null;

    if ($reset) {
        $transport = null;
    } elseif ($replace !== null) {
        $transport = $replace;
    }

    return $transport;
}

/**
 * One request to Geliver.
 *
 * @param string     $method
 * @param string     $url     a path under the API, or a full https address
 * @param string     $token   '' to send none (a label file)
 * @param array|null $payload JSON body
 * @return array status (0 when Geliver was not reached), body (text), error,
 *               sent (whether the request may have reached Geliver)
 */
function pg_label_geliver_http($method, $url, $token, $payload = null)
{
    if (strncmp($url, 'https://', 8) !== 0) {
        $url = 'https://api.geliver.io/api/v1' . $url;
    }

    $headers = array('Accept: application/json');

    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $body = null;

    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $transport = pg_label_geliver_transport();

    if ($transport !== null) {
        $call = call_user_func($transport, $method, $url, $headers, $body);
    } else {
        // pg_curl_run() adds the proxy, the user agent and the TLS settings;
        // Geliver's hosts are fixed, so no address check.
        $call = pg_curl_run($method, $url, array(
            'headers'         => $headers,
            'body'            => $body,
            'timeout'         => 45,
            'connect_timeout' => 10,
            'max_size'        => 10485760,
        ));

        if (($call['status'] === 0) && ($call['errno'] !== 0)) {
            $call['error'] = trim($call['error'] . ' ' . pg_curl_tls_hint($call['errno']));
        }

        $call['sent'] = ($call['status'] !== 0) || ($call['request_size'] > 0);
    }

    return array(
        'status' => isset($call['status']) ? (int) $call['status'] : 0,
        'body'   => isset($call['body']) ? (string) $call['body'] : '',
        'error'  => isset($call['error']) ? (string) $call['error'] : '',
        'sent'   => !empty($call['sent']),
    );
}

/**
 * A request to the API read into one shape.
 *
 * @return array ok (a 2xx answer whose result is true), reached, sent,
 *               status, data (the answer's data, array), message (Geliver's
 *               code and words or the transport's), raw
 */
function pg_label_geliver_api($method, $path, $account, $payload = null)
{
    $call = pg_label_geliver_http($method, $path, (string) $account['api_token'], $payload);
    $decoded = json_decode($call['body'], true);
    $decoded = is_array($decoded) ? $decoded : array();

    $read = array(
        'ok'      => false,
        'reached' => ($call['status'] !== 0),
        'sent'    => $call['sent'],
        'status'  => $call['status'],
        'data'    => (isset($decoded['data']) && is_array($decoded['data'])) ? $decoded['data'] : array(),
        'message' => '',
        'raw'     => array('request' => $method . ' ' . $path, 'status' => $call['status'], 'answer' => $decoded ? $decoded : substr($call['body'], 0, 500)),
    );

    if ($call['status'] === 0) {
        $read['message'] = $call['error'];

        return $read;
    }

    $read['ok'] = ($call['status'] >= 200) && ($call['status'] < 300)
        && isset($decoded['result']) && ($decoded['result'] === true);

    if (!$read['ok']) {
        $words = array();

        foreach (array('code', 'errorCode', 'message', 'additionalMessage') as $name) {
            if (isset($decoded[$name]) && is_scalar($decoded[$name]) && (trim((string) $decoded[$name]) !== '') && ($decoded[$name] !== 'Success')) {
                $words[] = trim((string) $decoded[$name]);
            }
        }

        $read['message'] = $words ? implode(' ', array_unique($words)) : 'HTTP ' . $call['status'];
    }

    return $read;
}

/**
 * The failure of a request that did not succeed. A request that changes
 * something at Geliver and went out without a readable answer is unclear.
 *
 * @param array $read     pg_label_geliver_api()
 * @param bool  $changing whether the request opens, buys or cancels
 * @return array
 */
function pg_label_geliver_failure($read, $changing)
{
    if (!$read['reached']) {
        return array(
            'success' => false,
            'unclear' => $changing && $read['sent'],
            'error'   => lang('The carrier label service could not be reached.'),
            'log'     => array('Geliver could not be reached: ' . $read['message']),
            'raw'     => $read['raw'],
        );
    }

    // A server error or an answer that is not Geliver's JSON does not say
    // whether the change was made.
    $unread = ($read['status'] >= 500) || !isset($read['raw']['answer']['result']);

    return array(
        'success' => false,
        'unclear' => $changing && $unread,
        'error'   => lang('Geliver refused the request.') . (($read['message'] !== '') ? ' (' . $read['message'] . ')' : ''),
        'log'     => array('Geliver refused ' . $read['raw']['request'] . ': ' . $read['message']),
        'raw'     => $read['raw'],
    );
}

/**
 * A decimal text of a number, at most $places places.
 *
 * @return string
 */
function pg_label_geliver_number($value, $places = 2)
{
    $value = is_numeric($value) ? max(0, (float) $value) : 0;

    return rtrim(rtrim(number_format($value, $places, '.', ''), '0'), '.');
}

/**
 * The POST /shipments body of a shipment.
 *
 * @param array $shipment see the header of this file
 * @param array $account
 * @return array
 */
function pg_label_geliver_shipment_body($shipment, $account)
{
    $shipment = is_array($shipment) ? $shipment : array();
    $text = function ($source, $name) {
        return (is_array($source) && isset($source[$name]) && is_scalar($source[$name])) ? trim((string) $source[$name]) : '';
    };

    $recipient = isset($shipment['recipient']) ? $shipment['recipient'] : array();
    $parcel = isset($shipment['parcel']) ? $shipment['parcel'] : array();
    $country = strtoupper($text($recipient, 'country'));

    $address = array(
        'name'         => $text($recipient, 'name'),
        'email'        => $text($recipient, 'email'),
        'phone'        => $text($recipient, 'phone'),
        'address1'     => $text($recipient, 'address_1'),
        'address2'     => $text($recipient, 'address_2'),
        'countryCode'  => ($country !== '') ? $country : 'TR',
        'cityName'     => $text($recipient, 'state'),
        'districtName' => $text($recipient, 'city'),
        'zip'          => $text($recipient, 'zip'),
    );

    // The province's licence plate number, two digits as the reference writes it.
    $plate = $text($recipient, 'state_code');

    if (preg_match('/^(?:TR-)?(\d{1,2})$/i', $plate, $match)) {
        $address['cityCode'] = sprintf('%02d', (int) $match[1]);
    }

    $items = array();

    if (isset($shipment['items']) && is_array($shipment['items'])) {
        foreach ($shipment['items'] as $item) {
            if ($text($item, 'title') !== '') {
                $items[] = array('title' => $text($item, 'title'), 'quantity' => max(1, (int) $text($item, 'quantity')));
            }
        }
    }

    $currency = strtoupper($text($shipment, 'currency'));

    $body = array(
        'test'                     => ($text($account, 'mode') !== 'live'),
        'isReturn'                 => !empty($shipment['return']),
        'length'                   => pg_label_geliver_number($text($parcel, 'length')),
        'width'                    => pg_label_geliver_number($text($parcel, 'width')),
        'height'                   => pg_label_geliver_number($text($parcel, 'height')),
        'distanceUnit'             => 'cm',
        'weight'                   => pg_label_geliver_number($text($parcel, 'weight'), 3),
        'massUnit'                 => 'kg',
        'items'                    => $items,
        'recipientAddress'         => array_filter($address, 'strlen'),
        'productPaymentOnDelivery' => false,
        'order'                    => array(
            'sourceCode'          => 'API',
            'orderNumber'         => $text($shipment, 'order_number'),
            // Geliver writes Turkish lira as TL.
            'totalAmount'         => round(((int) $text($shipment, 'total')) / 100, 2),
            'totalAmountCurrency' => (($currency === '') || ($currency === 'TRY')) ? 'TL' : $currency,
        ),
    );

    if ($text($account, 'sender_address') !== '') {
        $body['senderAddressID'] = $text($account, 'sender_address');
    }

    return $body;
}

/**
 * The offer to accept: the service's own when one is asked for, else the
 * cheapest.
 *
 * @param array  $offers  the shipment's offers
 * @param string $service a service code, '' for the cheapest
 * @return array|null
 */
function pg_label_geliver_offer($offers, $service)
{
    $list = (isset($offers['list']) && is_array($offers['list'])) ? $offers['list'] : array();

    if ($service !== '') {
        foreach ($list as $offer) {
            if (is_array($offer) && isset($offer['providerServiceCode'], $offer['id']) && (strcasecmp((string) $offer['providerServiceCode'], $service) === 0)) {
                return $offer;
            }
        }

        return null;
    }

    return (isset($offers['cheapest']['id']) && is_string($offers['cheapest']['id']) && ($offers['cheapest']['id'] !== '')) ? $offers['cheapest'] : null;
}

/**
 * Whether Geliver has collected every offer it asked for.
 *
 * @return bool
 */
function pg_label_geliver_offers_complete($offers)
{
    return is_array($offers) && isset($offers['percentageCompleted']) && ((float) $offers['percentageCompleted'] >= 100);
}

/**
 * The carrier key of a shipment's provider code, '' for one the store does
 * not know.
 *
 * @return string
 */
function pg_label_geliver_carrier($data)
{
    $code = isset($data['providerCode']) && is_string($data['providerCode']) ? strtoupper($data['providerCode']) : '';
    $carriers = pg_label_geliver_carriers();

    return isset($carriers[$code]) ? $carriers[$code] : '';
}

/**
 * A time of Geliver's (RFC 3339) as unix time, 0 when none.
 *
 * @return int
 */
function pg_label_geliver_time($value)
{
    if (!is_string($value) || ($value === '') || (strncmp($value, '0001-', 5) === 0)) {
        return 0;
    }

    // Geliver writes up to nine places of a second, which strtotime() on
    // PHP 7.1 does not read; the second is precise enough.
    $time = strtotime(preg_replace('/(\d{2}:\d{2}:\d{2})\.\d+/', '$1', $value));

    return ($time === false) ? 0 : max(0, $time);
}

/**
 * The shipment status of Geliver's tracking status, or of its shipment
 * status while no tracking status is given.
 *
 * @param array $data a shipment
 * @return string a pg_shipment_statuses() value, '' when unknown
 */
function pg_label_geliver_status($data)
{
    $tracking = (isset($data['trackingStatus']) && is_array($data['trackingStatus'])) ? $data['trackingStatus'] : array();
    $code = isset($tracking['trackingStatusCode']) ? strtoupper((string) $tracking['trackingStatusCode']) : '';
    $sub = isset($tracking['trackingSubStatusCode']) ? strtolower((string) $tracking['trackingSubStatusCode']) : '';

    $by_tracking = array(
        'PRE_TRANSIT' => 'created',
        'TRANSIT'     => 'in_transit',
        'DELIVERED'   => 'delivered',
        'RETURNED'    => 'returned',
        'FAILURE'     => 'exception',
        'CANCELED'    => 'exception',
    );

    if (($code === 'TRANSIT') && ($sub === 'out_for_delivery')) {
        return 'out_for_delivery';
    }

    if (isset($by_tracking[$code])) {
        return $by_tracking[$code];
    }

    $by_shipment = array(
        'CREATED'               => 'created',
        'GOT_OFFERS'            => 'created',
        'OFFER_ACCEPTED'        => 'created',
        'TRACKING_CODE_CREATED' => 'created',
        'LABEL_PRINTED'         => 'created',
        'SHIPPED'               => 'in_transit',
        'DELIVERED'             => 'delivered',
        'RETURNED'              => 'returned',
        'RETURN_SHIPPED'        => 'returned',
        'CANCELED'              => 'exception',
        'FAILED'                => 'exception',
    );

    $status = isset($data['statusCode']) ? strtoupper((string) $data['statusCode']) : '';

    return isset($by_shipment[$status]) ? $by_shipment[$status] : '';
}

/**
 * The PDF behind a shipment's label address, '' when it cannot be had.
 * Only an https address on Geliver's own hosts is fetched, without the token.
 *
 * @param array $data a shipment
 * @return array label (bytes, '' when none), log (a sentence when it failed)
 */
function pg_label_geliver_download($data)
{
    $url = (isset($data['labelURL']) && is_string($data['labelURL'])) ? trim($data['labelURL']) : '';
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));

    if ((strncmp($url, 'https://', 8) !== 0) || (($host !== 'geliver.io') && (substr($host, -11) !== '.geliver.io'))) {
        return array('label' => '', 'log' => 'Geliver gave no label address on its own host.');
    }

    $call = pg_label_geliver_http('GET', $url, '');

    if (($call['status'] < 200) || ($call['status'] >= 300) || !pg_label_document_valid($call['body'], 'pdf')) {
        return array('label' => '', 'log' => 'The Geliver label could not be read: ' . (($call['status'] === 0) ? $call['error'] : 'HTTP ' . $call['status']) . '.');
    }

    return array('label' => $call['body'], 'log' => '');
}

/**
 * A reference as a path segment: Geliver's ids are UUIDs.
 *
 * @return string '' when it cannot be one
 */
function pg_label_geliver_id($reference)
{
    $reference = is_scalar($reference) ? trim((string) $reference) : '';

    return (preg_match('/^[A-Za-z0-9-]{1,64}$/', $reference) === 1) ? $reference : '';
}

function pg_label_geliver_test($account)
{
    $sender = trim((string) $account['sender_address']);
    $path = ($sender !== '') ? '/addresses/' . rawurlencode($sender) : '/addresses?isRecipientAddress=false&limit=1&page=1';
    $read = pg_label_geliver_api('GET', $path, $account);

    if (!$read['ok']) {
        return pg_label_geliver_failure($read, false);
    }

    $address = ($sender !== '') ? $read['data'] : (isset($read['data'][0]) && is_array($read['data'][0]) ? $read['data'][0] : array());
    $name = '';

    foreach (array('shortName', 'name') as $key) {
        if (($name === '') && isset($address[$key]) && is_string($address[$key])) {
            $name = trim($address[$key]);
        }
    }

    return array(
        'success' => true,
        'account' => $name,
        'log'     => array('Geliver accepted the API token' . (($account['mode'] !== 'live') ? ' (test mode)' : '') . '.'),
    );
}

function pg_label_geliver_create($shipment, $account)
{
    $service = (is_array($shipment) && isset($shipment['service']) && is_string($shipment['service']) && (trim($shipment['service']) !== ''))
        ? trim($shipment['service'])
        : trim((string) $account['service']);

    // A test shipment is offered by Geliver's test service only.
    if ($account['mode'] !== 'live') {
        $service = '';
    }

    $read = pg_label_geliver_api('POST', '/shipments', $account, pg_label_geliver_shipment_body($shipment, $account));

    if (!$read['ok']) {
        return pg_label_geliver_failure($read, false);
    }

    $id = pg_label_geliver_id(isset($read['data']['id']) ? $read['data']['id'] : '');

    if ($id === '') {
        return array('success' => false, 'error' => lang('The carrier label service gave an answer that cannot be read. Check the shipment on the service\'s own screen before trying again.'), 'raw' => $read['raw']);
    }

    $log = array('Geliver shipment ' . $id . ' opened.');
    $offers = isset($read['data']['offers']) ? $read['data']['offers'] : array();
    $offer = pg_label_geliver_offer($offers, $service);

    // Offers arrive one by one; the shipment is read again while some are
    // still being collected.
    for ($round = 0; ($round < 5) && !pg_label_geliver_offers_complete($offers) && (($offer === null) || ($service === '')); $round++) {
        if (pg_label_geliver_transport() === null) {
            sleep(2);
        }

        $again = pg_label_geliver_api('GET', '/shipments/' . $id, $account);

        if ($again['ok'] && isset($again['data']['offers'])) {
            $offers = $again['data']['offers'];
            $offer = pg_label_geliver_offer($offers, $service);
        }
    }

    if ($offer === null) {
        return array(
            'success' => false,
            'error'   => ($service !== '') ? lang('Geliver made no offer of the chosen service for this shipment.') : lang('Geliver made no offer for this shipment.'),
            'log'     => array_merge($log, array('No offer was accepted; the shipment stays unpaid at Geliver.')),
            'raw'     => $read['raw'],
        );
    }

    $accept = pg_label_geliver_api('POST', '/transactions', $account, array('offerID' => (string) $offer['id']));

    if (!$accept['ok']) {
        $failure = pg_label_geliver_failure($accept, true);
        $failure['log'] = array_merge($log, $failure['log']);

        return $failure;
    }

    $data = (isset($accept['data']['shipment']) && is_array($accept['data']['shipment'])) ? $accept['data']['shipment'] : array();
    $text = function ($name) use ($data) {
        return (isset($data[$name]) && is_scalar($data[$name])) ? trim((string) $data[$name]) : '';
    };

    // Some carriers give their tracking number only when the parcel is
    // handed in; until then the barcode on the label is the number the
    // branch reads.
    $tracking = ($text('trackingNumber') !== '') ? $text('trackingNumber') : $text('barcode');
    $log[] = 'Geliver offer ' . $offer['id'] . ' (' . (isset($offer['providerServiceCode']) ? $offer['providerServiceCode'] : '?') . ') accepted.';

    $answer = array(
        'success'         => true,
        'reference'       => $id,
        'tracking_number' => $tracking,
        'carrier'         => pg_label_geliver_carrier($data),
        'tracking_url'    => $text('trackingUrl'),
        'status'          => 'created',
        'barcode'         => $text('barcode'),
        'log'             => $log,
        'raw'             => $accept['raw'],
    );

    // The label is fetched again by label() when it cannot be had now: the
    // shipment is bought either way.
    $download = pg_label_geliver_download($data);

    if ($download['label'] !== '') {
        $answer['label'] = $download['label'];
        $answer['label_format'] = 'pdf';
    } else {
        $answer['log'][] = $download['log'];
    }

    return $answer;
}

function pg_label_geliver_label($reference, $format, $account)
{
    $id = pg_label_geliver_id($reference);

    if ($format !== 'pdf') {
        return array('success' => false, 'error' => lang('Geliver gives labels as PDF only.'));
    }

    if ($id === '') {
        return array('success' => false, 'error' => lang('No shipment of the carrier label service is known for this parcel.'));
    }

    $read = pg_label_geliver_api('GET', '/shipments/' . $id, $account);

    if (!$read['ok']) {
        return pg_label_geliver_failure($read, false);
    }

    $download = pg_label_geliver_download($read['data']);

    if ($download['label'] === '') {
        return array('success' => false, 'error' => lang('Geliver has no label for this shipment yet.'), 'log' => array($download['log']), 'raw' => $read['raw']);
    }

    return array(
        'success'      => true,
        'reference'    => $id,
        'label'        => $download['label'],
        'label_format' => 'pdf',
        'barcode'      => isset($read['data']['barcode']) && is_scalar($read['data']['barcode']) ? (string) $read['data']['barcode'] : '',
    );
}

function pg_label_geliver_cancel($reference, $account)
{
    $id = pg_label_geliver_id($reference);

    if ($id === '') {
        return array('success' => false, 'error' => lang('No shipment of the carrier label service is known for this parcel.'));
    }

    $read = pg_label_geliver_api('DELETE', '/shipments/' . $id, $account);

    if (!$read['ok']) {
        return pg_label_geliver_failure($read, true);
    }

    return array('success' => true, 'reference' => $id, 'log' => array('Geliver shipment ' . $id . ' cancelled.'));
}

function pg_label_geliver_track($reference, $account)
{
    $id = pg_label_geliver_id($reference);

    if ($id === '') {
        return array('success' => false, 'error' => lang('No shipment of the carrier label service is known for this parcel.'));
    }

    $read = pg_label_geliver_api('GET', '/shipments/' . $id, $account);

    if (!$read['ok']) {
        return pg_label_geliver_failure($read, false);
    }

    return array('reference' => $id) + pg_label_geliver_track_answer($read['data'], $read['raw']);
}

/**
 * The track() answer of a shipment as Geliver gives it, in GET
 * /shipments/{id} or in a TRACK_UPDATED message. The time is the tracking
 * status's statusDate, else the moment Geliver recorded that status (a
 * message's statusDate may be null).
 *
 * @param array $data a shipment
 * @param array $raw  what to keep of Geliver's answer
 * @return array
 */
function pg_label_geliver_track_answer($data, $raw)
{
    $status = pg_label_geliver_status($data);

    if ($status === '') {
        return array('success' => false, 'error' => lang('Geliver gives no status for this shipment yet.'), 'raw' => $raw);
    }

    $tracking = (isset($data['trackingStatus']) && is_array($data['trackingStatus'])) ? $data['trackingStatus'] : array();
    $at = 0;

    foreach (array('statusDate', 'updatedAt', 'createdAt') as $name) {
        $at = pg_label_geliver_time(isset($tracking[$name]) ? $tracking[$name] : '');

        if ($at > 0) {
            break;
        }
    }

    return array(
        'success'         => true,
        'reference'       => isset($data['id']) && is_string($data['id']) ? $data['id'] : '',
        'status'          => $status,
        'status_at'       => $at,
        'tracking_number' => isset($data['trackingNumber']) && is_scalar($data['trackingNumber']) ? (string) $data['trackingNumber'] : '',
        'tracking_url'    => isset($data['trackingUrl']) && is_string($data['trackingUrl']) ? $data['trackingUrl'] : '',
        'carrier'         => pg_label_geliver_carrier($data),
        'log'             => (isset($tracking['statusDetails']) && is_string($tracking['statusDetails']) && (trim($tracking['statusDetails']) !== ''))
            ? array('Geliver: ' . trim($tracking['statusDetails']) . '.')
            : array(),
    );
}

/**
 * The parcel news in a message Geliver posted to the store's webhook
 * address (carrier_webhook.php). Geliver's reference gives the message as
 * {event, metadata, data: <the shipment>} and no signature: the key in the
 * registered address is the credential (includes/fn/carrier_webhook.php).
 * Only TRACK_UPDATED is read; another event carries nothing. Pure.
 *
 * @param string $body
 * @return array|false list of reference (Geliver's shipment id), is_return
 *                     (Geliver's own return flag), answer (a track() answer);
 *                     false when the body is not a message
 */
function pg_label_geliver_webhook_read($body)
{
    $message = json_decode((string) $body, true);

    if (!is_array($message) || !isset($message['event']) || !is_string($message['event'])) {
        return false;
    }

    $data = (isset($message['data']) && is_array($message['data'])) ? $message['data'] : array();
    $id = pg_label_geliver_id(isset($data['id']) ? $data['id'] : '');

    if ((strtoupper($message['event']) !== 'TRACK_UPDATED') || ($id === '')) {
        return array();
    }

    return array(array(
        'reference' => $id,
        'is_return' => !empty($data['isReturn']),
        'answer'    => pg_label_geliver_track_answer($data, array('event' => 'TRACK_UPDATED', 'shipment' => $id)),
    ));
}

/**
 * Registers the store's webhook address at Geliver for TRACK_UPDATED
 * (POST /webhook).
 *
 * @param string $url
 * @param array  $account
 * @return array success, id (Geliver's webhook id), error
 */
function pg_label_geliver_webhook_register($url, $account)
{
    $read = pg_label_geliver_api('POST', '/webhook', $account, array('type' => 'TRACK_UPDATED', 'url' => (string) $url));
    $id = $read['ok'] ? pg_label_geliver_id(isset($read['data']['id']) ? $read['data']['id'] : '') : '';

    if ($id === '') {
        return array('success' => false, 'id' => '', 'error' => $read['ok'] ? lang('Geliver refused the request.') : pg_label_geliver_failure($read, false)['error']);
    }

    return array('success' => true, 'id' => $id, 'error' => '');
}

/**
 * Removes a webhook at Geliver (DELETE /webhook/{id}). One Geliver no longer
 * has (404) counts as removed.
 *
 * @param string $id
 * @param array  $account
 * @return array success, error
 */
function pg_label_geliver_webhook_remove($id, $account)
{
    $id = pg_label_geliver_id($id);

    if ($id === '') {
        return array('success' => false, 'error' => lang('Geliver refused the request.'));
    }

    $read = pg_label_geliver_api('DELETE', '/webhook/' . $id, $account);

    if (!$read['ok'] && ($read['status'] !== 404)) {
        return array('success' => false, 'error' => pg_label_geliver_failure($read, false)['error']);
    }

    return array('success' => true, 'error' => '');
}
