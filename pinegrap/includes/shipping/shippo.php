<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Shippo label driver: Shippo is a multi-carrier shipping API
 * (https://api.goshippo.com, "Authorization: ShippoToken <token>") with its
 * own accounts at USPS, UPS, FedEx, DHL, Deutsche Post, DPD, Royal Mail and
 * other carriers in the US and Europe, so a store needs no carrier contract.
 * The driver follows its public reference (https://docs.goshippo.com):
 * POST /shipments/ collects the carriers' rates for a parcel, POST
 * /transactions/ buys the label of one rate, the transaction's label_url is
 * the document (PDF in several sizes, or ZPL II), POST /refunds/ voids a
 * label not yet used, and the transaction's tracking_status is where the
 * parcel stands.
 *
 * A token that starts with shippo_test_ is test mode: labels are free
 * samples marked "SAMPLE - DO NOT MAIL" and tracking never moves.
 *
 * The contract is the one in includes/fn/carrier_labels.php; the shipment
 * create() takes is the one includes/shipping/geliver.php describes
 * (service: a Shippo service level token such as usps_priority). Shippo
 * needs the sender's address with every shipment; it comes from the
 * account. A return asks for a scan-based return label (extra.is_return),
 * which Shippo makes for USPS, UPS and FedEx only. $reference is Shippo's
 * transaction id.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

function pg_label_shippo_info()
{
    return array(
        'carriers' => array('ups', 'fedex', 'usps', 'dhl', 'deutschepost', 'dpd', 'royalmail'),
        'formats'  => array('pdf', 'zpl'),
    );
}

function pg_label_shippo_fields()
{
    $fields = array(
        array(
            'name'     => 'api_token',
            'type'     => 'secret',
            'label'    => lang('API Token'),
            'help'     => lang('Created in the Shippo portal under API. A token that starts with shippo_test_ makes free sample labels that cannot be shipped.'),
            'required' => true,
        ),
        array(
            'name'    => 'label_file_type',
            'type'    => 'select',
            'label'   => lang('Label Format'),
            'options' => array('PDF_A6' => lang('PDF, A6'), 'PDF_4x6' => lang('PDF, 4 x 6 in'), 'ZPLII' => lang('ZPL II (thermal printer)')),
            'default' => 'PDF_A6',
        ),
        array(
            'name'       => 'service',
            'label'      => lang('Service Level'),
            'help'       => lang('A Shippo service level token such as usps_priority or dhl_express_worldwide. Empty takes the cheapest rate.'),
            'max_length' => 64,
        ),
    );

    // The sender's address goes with every shipment: Shippo keeps none for
    // the API.
    $sender = array(
        'sender_name'    => array(lang('Sender Name'), true, 100),
        'sender_company' => array(lang('Sender Company'), false, 100),
        'sender_street1' => array(lang('Sender Street'), true, 100),
        'sender_street2' => array(lang('Sender Street (Line 2)'), false, 100),
        'sender_city'    => array(lang('Sender City'), true, 100),
        'sender_state'   => array(lang('Sender State or Province'), false, 50),
        'sender_zip'     => array(lang('Sender Postal Code'), true, 20),
        'sender_country' => array(lang('Sender Country Code'), true, 2),
        'sender_phone'   => array(lang('Sender Phone'), false, 30),
        'sender_email'   => array(lang('Sender E-mail'), false, 100),
    );

    foreach ($sender as $name => $field) {
        $fields[] = array(
            'name'       => $name,
            'label'      => $field[0],
            'help'       => ($name === 'sender_country') ? lang('Two letters, such as US, DE or GB.') : '',
            'required'   => $field[1],
            'max_length' => $field[2],
        );
    }

    return $fields;
}

/**
 * The HTTP transport: pg_curl_run() unless one is set in its place (the
 * tests). While one is set, a wait is recorded (pg_label_shippo_waits())
 * instead of slept.
 *
 * @param callable|null $replace function ($method, $url, $headers, $body)
 *                               answering status, body, error, sent
 * @param bool          $reset   true to go back to pg_curl_run()
 * @return callable|null the transport set, null for pg_curl_run()
 */
function pg_label_shippo_transport($replace = null, $reset = false)
{
    static $transport = null;

    if ($reset) {
        $transport = null;
        pg_label_shippo_waits(true);
    } elseif ($replace !== null) {
        $transport = $replace;
        pg_label_shippo_waits(true);
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
function pg_label_shippo_waits($reset = false, $add = null)
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
function pg_label_shippo_sleep($seconds)
{
    if (pg_label_shippo_transport() !== null) {
        pg_label_shippo_waits(false, $seconds);
        return;
    }

    sleep($seconds);
}

/**
 * One request. $token '' sends none: a label address is signed by itself.
 *
 * @param string     $method
 * @param string     $url
 * @param string     $token
 * @param array|null $payload JSON body
 * @return array status (0 when not reached), body, error, sent
 */
function pg_label_shippo_http($method, $url, $token, $payload = null)
{
    $headers = array('Accept: application/json, application/pdf, */*');
    $body = null;

    if ($token !== '') {
        // The answers' shape is pinned to the version the reference shows.
        $headers[] = 'Authorization: ShippoToken ' . $token;
        $headers[] = 'SHIPPO-API-VERSION: 2018-02-08';
    }

    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $transport = pg_label_shippo_transport();

    if ($transport !== null) {
        $call = call_user_func($transport, $method, $url, $headers, $body);
    } else {
        // A purchase waits for the carrier: Shippo's synchronous calls can
        // take tens of seconds.
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

    return array(
        'status' => isset($call['status']) ? (int) $call['status'] : 0,
        'body'   => isset($call['body']) ? (string) $call['body'] : '',
        'error'  => isset($call['error']) ? (string) $call['error'] : '',
        'sent'   => !empty($call['sent']),
    );
}

/**
 * The words of Shippo's messages: a list of source/code/text objects.
 *
 * @param mixed $messages
 * @return string[]
 */
function pg_label_shippo_message_texts($messages)
{
    $texts = array();

    foreach (is_array($messages) ? $messages : array() as $message) {
        if (is_array($message) && isset($message['text']) && is_scalar($message['text']) && (trim((string) $message['text']) !== '')) {
            $texts[] = trim((string) $message['text']);
        } elseif (is_string($message) && (trim($message) !== '')) {
            $texts[] = trim($message);
        }
    }

    return $texts;
}

/**
 * The words of a refused request: detail, messages, or field => list of
 * sentences (nested for addresses and parcels), named by their path. Pure.
 *
 * @param array  $data
 * @param string $path
 * @return string[]
 */
function pg_label_shippo_error_texts($data, $path = '')
{
    $texts = array();

    foreach ($data as $name => $value) {
        $where = ($path === '') ? (string) $name : $path . (is_int($name) ? '' : '.' . $name);

        if ($path === '' && in_array($name, array('detail', 'message', 'error'), true) && is_scalar($value)) {
            $texts[] = trim((string) $value);
        } elseif (($path === '') && ($name === 'messages')) {
            $texts = array_merge($texts, pg_label_shippo_message_texts($value));
        } elseif (is_scalar($value) && (trim((string) $value) !== '')) {
            $texts[] = (is_int($name) ? $path : $where) . ': ' . trim((string) $value);
        } elseif (is_array($value) && (count($texts) < 10)) {
            $texts = array_merge($texts, pg_label_shippo_error_texts($value, $where));
        }
    }

    return array_values(array_unique(array_filter($texts, 'strlen')));
}

/**
 * A request to the API read into one shape.
 *
 * @param string     $method
 * @param string     $path    a path under the API
 * @param array      $account
 * @param array|null $payload
 * @return array ok (a 2xx answer), reached, sent, status, data (the JSON
 *               answer, array), message, raw
 */
function pg_label_shippo_api($method, $path, $account, $payload = null)
{
    $call = pg_label_shippo_http($method, 'https://api.goshippo.com' . $path, (string) $account['api_token'], $payload);
    $decoded = json_decode($call['body'], true);
    $decoded = is_array($decoded) ? $decoded : array();

    $read = array(
        'ok'      => ($call['status'] >= 200) && ($call['status'] < 300),
        'reached' => ($call['status'] !== 0),
        'sent'    => $call['sent'],
        'status'  => $call['status'],
        'data'    => $decoded,
        'message' => '',
        'raw'     => array('request' => $method . ' ' . $path, 'status' => $call['status'], 'answer' => $decoded ? $decoded : substr($call['body'], 0, 500)),
    );

    if ($call['status'] === 0) {
        $read['message'] = $call['error'];
    } elseif (!$read['ok']) {
        $words = pg_label_shippo_error_texts($decoded);
        $read['message'] = $words ? substr(implode(' ', $words), 0, 300) : 'HTTP ' . $call['status'];
    }

    return $read;
}

/**
 * The failure of a request that did not succeed. A request that buys or
 * refunds and went out without a readable answer, or met a server error,
 * is unclear.
 *
 * @param array $read     pg_label_shippo_api()
 * @param bool  $changing whether the request buys or refunds
 * @return array
 */
function pg_label_shippo_failure($read, $changing)
{
    if (!$read['reached']) {
        return array(
            'success' => false,
            'unclear' => $changing && $read['sent'],
            'error'   => lang('The carrier label service could not be reached.'),
            'log'     => array('Shippo could not be reached: ' . $read['message']),
            'raw'     => $read['raw'],
        );
    }

    return array(
        'success' => false,
        'unclear' => $changing && ($read['status'] >= 500),
        'error'   => lang('Shippo refused the request.') . (($read['message'] !== '') ? ' (' . $read['message'] . ')' : ''),
        'log'     => array('Shippo refused ' . $read['raw']['request'] . ': ' . $read['message']),
        'raw'     => $read['raw'],
    );
}

/**
 * A text field, '' when missing.
 *
 * @return string
 */
function pg_label_shippo_text($data, $name)
{
    return (is_array($data) && isset($data[$name]) && is_scalar($data[$name])) ? trim((string) $data[$name]) : '';
}

/**
 * A size or weight as the decimal text Shippo takes.
 *
 * @return string
 */
function pg_label_shippo_number($value, $places)
{
    $value = round(is_numeric($value) ? max(0, (float) $value) : 0, $places);

    return rtrim(rtrim(number_format($value, $places, '.', ''), '0'), '.');
}

/**
 * The POST /shipments/ body of a shipment. Pure.
 *
 * @param array $shipment see the header of this file
 * @param array $account
 * @return array
 */
function pg_label_shippo_shipment_body($shipment, $account)
{
    $shipment = is_array($shipment) ? $shipment : array();
    $text = 'pg_label_shippo_text';
    $recipient = isset($shipment['recipient']) ? $shipment['recipient'] : array();
    $parcel = isset($shipment['parcel']) ? $shipment['parcel'] : array();
    $order = substr($text($shipment, 'order_number'), 0, 50);

    $from = array();

    foreach (array('name', 'company', 'street1', 'street2', 'city', 'state', 'zip', 'country', 'phone', 'email') as $name) {
        $from[$name] = $text($account, 'sender_' . $name);
    }

    $from['country'] = strtoupper($from['country']);

    $body = array(
        'address_from' => array_filter($from, 'strlen'),
        'address_to'   => array_filter(array(
            'name'    => $text($recipient, 'name'),
            'street1' => $text($recipient, 'address_1'),
            'street2' => $text($recipient, 'address_2'),
            'city'    => $text($recipient, 'city'),
            'state'   => $text($recipient, 'state'),
            'zip'     => $text($recipient, 'zip'),
            'country' => strtoupper($text($recipient, 'country')),
            'phone'   => $text($recipient, 'phone'),
            'email'   => $text($recipient, 'email'),
        ), 'strlen'),
        'parcels'      => array(array(
            'length'        => pg_label_shippo_number($text($parcel, 'length'), 2),
            'width'         => pg_label_shippo_number($text($parcel, 'width'), 2),
            'height'        => pg_label_shippo_number($text($parcel, 'height'), 2),
            'distance_unit' => 'cm',
            'weight'        => pg_label_shippo_number($text($parcel, 'weight'), 3),
            'mass_unit'     => 'kg',
        )),
        'extra'        => array_filter(array('reference_1' => $order), 'strlen'),
        'async'        => false,
    );

    // A scan-based return label: Shippo swaps the addresses itself.
    if (!empty($shipment['return'])) {
        $body['extra']['is_return'] = true;
    }

    if (!$body['extra']) {
        unset($body['extra']);
    }

    return $body;
}

/**
 * The rate to buy: the one of the service level asked for, else the
 * cheapest (by its amount in the account's currency). Pure.
 *
 * @param mixed  $rates   a shipment's rates
 * @param string $service a service level token, '' for the cheapest
 * @return array|null
 */
function pg_label_shippo_rate_pick($rates, $service)
{
    $service = strtolower(trim((string) $service));
    $cheapest = null;

    foreach (is_array($rates) ? $rates : array() as $rate) {
        if (!is_array($rate) || (pg_label_shippo_id(pg_label_shippo_text($rate, 'object_id')) === '')) {
            continue;
        }

        $token = isset($rate['servicelevel']) ? strtolower(pg_label_shippo_text($rate['servicelevel'], 'token')) : '';

        if (($service !== '') && ($token === $service)) {
            return $rate;
        }

        $amount = pg_label_shippo_text($rate, 'amount_local');
        $amount = is_numeric($amount) ? (float) $amount : (float) pg_label_shippo_text($rate, 'amount');

        if (($cheapest === null) || ($amount < $cheapest[0])) {
            $cheapest = array($amount, $rate);
        }
    }

    return ($cheapest === null) ? null : $cheapest[1];
}

/**
 * The carrier key of a Shippo provider name or carrier token ("DHL
 * Express", "dpd_de"), '' for one the store does not know. Pure.
 *
 * @param string $provider
 * @return string
 */
function pg_label_shippo_carrier($provider)
{
    $name = preg_replace('/[^a-z]/', '', strtolower((string) $provider));
    $prefixes = array('deutschepost' => 'deutschepost', 'royalmail' => 'royalmail', 'dhl' => 'dhl', 'dpd' => 'dpd', 'fedex' => 'fedex', 'usps' => 'usps');

    if ($name === 'ups') {
        return 'ups';
    }

    foreach ($prefixes as $prefix => $carrier) {
        if (strncmp($name, $prefix, strlen($prefix)) === 0) {
            return $carrier;
        }
    }

    return '';
}

/**
 * The shipment status of a transaction's tracking_status: the high level
 * code, or a tracking status object (status, substatus.code). Pure.
 *
 * @param mixed $tracking
 * @return string a pg_shipment_statuses() value, '' when unknown
 */
function pg_label_shippo_status($tracking)
{
    $code = is_array($tracking) ? pg_label_shippo_text($tracking, 'status') : (is_string($tracking) ? $tracking : '');
    $sub = (is_array($tracking) && isset($tracking['substatus'])) ? pg_label_shippo_text($tracking['substatus'], 'code') : '';

    $statuses = array(
        'PRE_TRANSIT' => 'created',
        'TRANSIT'     => ($sub === 'out_for_delivery') ? 'out_for_delivery' : 'in_transit',
        'DELIVERED'   => 'delivered',
        'RETURNED'    => 'returned',
        'FAILURE'     => 'exception',
    );

    $code = strtoupper(trim($code));

    return isset($statuses[$code]) ? $statuses[$code] : '';
}

/**
 * A reference as a path segment: Shippo's ids are 32 hex digits.
 *
 * @return string '' when it cannot be one
 */
function pg_label_shippo_id($reference)
{
    $reference = is_scalar($reference) ? trim((string) $reference) : '';

    return (preg_match('/^[A-Za-z0-9]{1,64}$/', $reference) === 1) ? $reference : '';
}

/**
 * Whether a label address is one of Shippo's: https on goshippo.com or its
 * shippo-delivery storage buckets. Pure.
 *
 * @param string $url
 * @return bool
 */
function pg_label_shippo_label_host($url)
{
    $url = (string) $url;
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));

    if (strncmp($url, 'https://', 8) !== 0) {
        return false;
    }

    return ($host === 'goshippo.com')
        || (substr($host, -13) === '.goshippo.com')
        || (preg_match('/^shippo-delivery[a-z0-9-]*\.s3([.-][a-z0-9-]+)?\.amazonaws\.com$/', $host) === 1);
}

/**
 * The label format of a transaction's label_file_type.
 *
 * @return string zpl or pdf
 */
function pg_label_shippo_format($data)
{
    return (strtoupper(pg_label_shippo_text($data, 'label_file_type')) === 'ZPLII') ? 'zpl' : 'pdf';
}

/**
 * The document behind a transaction's label address, fetched without the
 * token.
 *
 * @param array $data a transaction
 * @return array label (bytes, '' when none), format, log (a sentence when
 *               it failed)
 */
function pg_label_shippo_download($data)
{
    $url = pg_label_shippo_text($data, 'label_url');
    $format = pg_label_shippo_format($data);

    if (!pg_label_shippo_label_host($url)) {
        return array('label' => '', 'format' => $format, 'log' => 'Shippo gave no label address on its own host.');
    }

    $call = pg_label_shippo_http('GET', $url, '');

    if (($call['status'] < 200) || ($call['status'] >= 300) || !pg_label_document_valid($call['body'], $format)) {
        return array('label' => '', 'format' => $format, 'log' => 'The Shippo label could not be read: ' . (($call['status'] === 0) ? $call['error'] : 'HTTP ' . $call['status']) . '.');
    }

    return array('label' => $call['body'], 'format' => $format, 'log' => '');
}

/**
 * A shipment or transaction still being made asked again, up to three
 * times two seconds apart.
 *
 * @param array  $read    pg_label_shippo_api() of the first answer
 * @param string $path    where to ask again
 * @param array  $account
 * @return array the last read
 */
function pg_label_shippo_settle($read, $path, $account)
{
    for ($round = 0; $round < 3; $round++) {
        if (!$read['ok'] || !in_array(strtoupper(pg_label_shippo_text($read['data'], 'status')), array('QUEUED', 'WAITING'), true)) {
            break;
        }

        pg_label_shippo_sleep(2);
        $read = pg_label_shippo_api('GET', $path, $account);
    }

    return $read;
}

function pg_label_shippo_test($account)
{
    $read = pg_label_shippo_api('GET', '/carrier_accounts?results=50', $account);

    if (!$read['ok']) {
        return pg_label_shippo_failure($read, false);
    }

    $carriers = array();

    foreach ((isset($read['data']['results']) && is_array($read['data']['results'])) ? $read['data']['results'] : array() as $carrier) {
        if (is_array($carrier) && !empty($carrier['active']) && (pg_label_shippo_text($carrier, 'carrier') !== '')) {
            $carriers[] = pg_label_shippo_text($carrier, 'carrier');
        }
    }

    $test = (strncmp((string) $account['api_token'], 'shippo_test_', 12) === 0);

    return array(
        'success' => true,
        'account' => implode(', ', array_unique($carriers)),
        'log'     => array('Shippo accepted the API token' . ($test ? ' in test mode' : '') . '; ' . count($carriers) . ' carrier account(s) active.'),
    );
}

function pg_label_shippo_create($shipment, $account)
{
    $body = pg_label_shippo_shipment_body($shipment, $account);

    if (empty($body['address_to']['country'])) {
        return array('success' => false, 'error' => lang('Shippo needs the country of the shipping address.'));
    }

    // A shipment and its rates cost nothing: a failure here buys nothing.
    $read = pg_label_shippo_api('POST', '/shipments/', $account, $body);
    $id = $read['ok'] ? pg_label_shippo_id(pg_label_shippo_text($read['data'], 'object_id')) : '';

    if ($id !== '') {
        $read = pg_label_shippo_settle($read, '/shipments/' . $id, $account);
    }

    if (!$read['ok']) {
        return pg_label_shippo_failure($read, false);
    }

    $service = (is_array($shipment) && (pg_label_shippo_text($shipment, 'service') !== '')) ? pg_label_shippo_text($shipment, 'service') : (string) $account['service'];
    $rate = pg_label_shippo_rate_pick(isset($read['data']['rates']) ? $read['data']['rates'] : array(), $service);

    if ($rate === null) {
        $words = pg_label_shippo_message_texts(isset($read['data']['messages']) ? $read['data']['messages'] : array());

        return array(
            'success' => false,
            'error'   => lang('Shippo found no rate for this shipment.') . ($words ? ' (' . substr(implode(' ', $words), 0, 300) . ')' : ''),
            'log'     => array('Shippo shipment ' . $id . ' has no rate.'),
            'raw'     => $read['raw'],
        );
    }

    $format = in_array((string) $account['label_file_type'], array('PDF_A6', 'PDF_4x6', 'ZPLII'), true) ? (string) $account['label_file_type'] : 'PDF_A6';
    $order = is_array($shipment) ? pg_label_shippo_text($shipment, 'order_number') : '';
    $purchase = array('rate' => pg_label_shippo_text($rate, 'object_id'), 'label_file_type' => $format, 'async' => false);

    if ($order !== '') {
        $purchase['metadata'] = substr('Order ' . $order, 0, 100);
    }

    // The purchase is the charge: a lost answer or a server error leaves it
    // unknown whether the label was bought.
    $read = pg_label_shippo_api('POST', '/transactions/', $account, $purchase);
    $reference = $read['ok'] ? pg_label_shippo_id(pg_label_shippo_text($read['data'], 'object_id')) : '';

    if ($reference !== '') {
        $read = pg_label_shippo_settle($read, '/transactions/' . $reference, $account);
    }

    if (!$read['ok']) {
        return pg_label_shippo_failure($read, true);
    }

    $data = $read['data'];
    $status = strtoupper(pg_label_shippo_text($data, 'status'));
    $provider = pg_label_shippo_text($rate, 'provider');

    if ($status === 'ERROR') {
        $words = pg_label_shippo_message_texts(isset($data['messages']) ? $data['messages'] : array());

        return array(
            'success' => false,
            'error'   => lang('Shippo could not make the label.') . ($words ? ' (' . substr(implode(' ', $words), 0, 300) . ')' : ''),
            'log'     => array('Shippo transaction ' . $reference . ' failed with ' . $provider . '.'),
            'raw'     => $read['raw'],
        );
    }

    if ($status !== 'SUCCESS') {
        return array(
            'success'   => false,
            'unclear'   => true,
            'reference' => $reference,
            'error'     => lang('Shippo is still making the label. Check the Shippo dashboard before trying again.'),
            'log'       => array('Shippo transaction ' . $reference . ' is ' . $status . '.'),
            'raw'       => $read['raw'],
        );
    }

    // Without a number the label may be bought all the same:
    // pg_label_answer() makes this an unclear failure.
    $answer = array(
        'success'         => true,
        'reference'       => $reference,
        'tracking_number' => pg_label_shippo_text($data, 'tracking_number'),
        'carrier'         => pg_label_shippo_carrier($provider),
        'tracking_url'    => pg_label_shippo_text($data, 'tracking_url_provider'),
        'status'          => 'created',
        'log'             => array('Shippo label ' . $reference . ' bought with ' . $provider . (isset($rate['servicelevel']) ? ' ' . pg_label_shippo_text($rate['servicelevel'], 'name') : '') . (!empty($data['test']) ? ' (test)' : '') . '.'),
        'raw'             => $read['raw'],
    );

    // The label is fetched again by label() when it cannot be had now.
    $document = pg_label_shippo_download($data);

    if ($document['label'] !== '') {
        $answer['label'] = $document['label'];
        $answer['label_format'] = $document['format'];
    } else {
        $answer['log'][] = $document['log'];
    }

    return $answer;
}

function pg_label_shippo_label($reference, $format, $account)
{
    $id = pg_label_shippo_id($reference);

    if ($id === '') {
        return array('success' => false, 'error' => lang('No shipment of the carrier label service is known for this parcel.'));
    }

    $read = pg_label_shippo_api('GET', '/transactions/' . $id, $account);

    if (!$read['ok']) {
        return pg_label_shippo_failure($read, false);
    }

    if (strtoupper(pg_label_shippo_text($read['data'], 'status')) !== 'SUCCESS') {
        return array('success' => false, 'error' => lang('Shippo has no label for this shipment: it was refunded or never made.'), 'raw' => $read['raw']);
    }

    $made = pg_label_shippo_format($read['data']);

    if ($format !== $made) {
        return array('success' => false, 'error' => lang(array('string' => 'Shippo made this label as {var:1}.', 'vars' => array(strtoupper($made)))));
    }

    $document = pg_label_shippo_download($read['data']);

    if ($document['label'] === '') {
        return array('success' => false, 'error' => lang('The label could not be read from Shippo.'), 'log' => array($document['log']));
    }

    return array('success' => true, 'reference' => $id, 'label' => $document['label'], 'label_format' => $made);
}

function pg_label_shippo_cancel($reference, $account)
{
    $id = pg_label_shippo_id($reference);

    if ($id === '') {
        return array('success' => false, 'error' => lang('No shipment of the carrier label service is known for this parcel.'));
    }

    // Shippo voids a label not yet scanned by the carrier and refunds it.
    $read = pg_label_shippo_api('POST', '/refunds/', $account, array('transaction' => $id, 'async' => false));

    if (!$read['ok']) {
        return pg_label_shippo_failure($read, true);
    }

    $status = strtoupper(pg_label_shippo_text($read['data'], 'status'));

    if ($status === 'ERROR') {
        return array('success' => false, 'error' => lang('Shippo refused to refund this label.'), 'raw' => $read['raw']);
    }

    return array('success' => true, 'reference' => $id, 'log' => array('Shippo refund of label ' . $id . ' is ' . (($status !== '') ? $status : 'requested') . '.'));
}

function pg_label_shippo_track($reference, $account)
{
    $id = pg_label_shippo_id($reference);

    if ($id === '') {
        return array('success' => false, 'error' => lang('No shipment of the carrier label service is known for this parcel.'));
    }

    $read = pg_label_shippo_api('GET', '/transactions/' . $id, $account);

    if (!$read['ok']) {
        return pg_label_shippo_failure($read, false);
    }

    $data = $read['data'];
    $tracking = isset($data['tracking_status']) ? $data['tracking_status'] : '';
    $status = pg_label_shippo_status($tracking);

    if ($status === '') {
        return array('success' => false, 'error' => lang('Shippo gives no status for this shipment yet.'), 'raw' => $read['raw']);
    }

    $time = is_array($tracking) ? strtotime(pg_label_shippo_text($tracking, 'status_date')) : false;

    return array(
        'success'         => true,
        'reference'       => $id,
        'status'          => $status,
        'status_at'       => ($time === false) ? 0 : max(0, $time),
        'tracking_number' => pg_label_shippo_text($data, 'tracking_number'),
        'tracking_url'    => pg_label_shippo_text($data, 'tracking_url_provider'),
    );
}
