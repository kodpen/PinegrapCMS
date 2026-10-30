<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Basit Kargo label driver: Basit Kargo is a Turkish shipping aggregator
 * with one REST API (https://basitkargo.com/api, a bearer token) in front of
 * its own carrier contracts and the sender's own ones (SELF_ codes). The
 * driver follows its public reference: POST /v2/order/barcode opens an order
 * and takes its barcode with one carrier (a handler code, or ECONOMIC /
 * FAST to let Basit Kargo choose), GET /label/svg/{id} gives the label as
 * SVG, the only format the reference documents.
 *
 * Basit Kargo has no test mode: every create() is a real, charged order.
 * The service allows 600 requests a minute per token and answers 429 with
 * Retry-After above that. A create is two calls (three when a 429 or a lost
 * answer is asked again); the driver keeps its own count per token within
 * the request and waits for a short Retry-After once. A create carries an
 * Idempotency-Key, so asking again after a lost answer returns the first
 * order instead of opening a second one.
 *
 * The contract is the one in includes/fn/carrier_labels.php; the shipment
 * create() takes is the one includes/shipping/geliver.php describes
 * (service: a Basit Kargo handler code such as SURAT or SELF_ARAS).
 * $reference is Basit Kargo's order id (888-6AR-OUP).
 *
 * Status webhook (includes/fn/carrier_webhook.php): Basit Kargo posts a
 * status change and a movement message, neither signed, to the address the
 * merchant adds in its panel by hand; the key in the address is the
 * credential.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

function pg_label_basitkargo_info()
{
    return array(
        'carriers' => array_values(array_unique(pg_label_basitkargo_carriers())),
        'formats'  => array('svg'),
    );
}

function pg_label_basitkargo_fields()
{
    return array(
        array(
            'name'     => 'api_token',
            'type'     => 'secret',
            'label'    => lang('API Token'),
            'help'     => lang('Created in the Basit Kargo panel under Settings. Basit Kargo has no test mode: every label created is a real, charged shipment.'),
            'required' => true,
        ),
        array(
            'name'       => 'handler',
            'label'      => lang('Carrier Code'),
            'help'       => lang('A Basit Kargo carrier code such as SURAT, or SELF_ARAS for your own contract. ECONOMIC takes the cheapest carrier, FAST the fastest.'),
            'default'    => 'ECONOMIC',
            'max_length' => 32,
        ),
        array(
            'name'       => 'address_id',
            'label'      => lang('Sender Address ID'),
            'help'       => lang('The ID of a sender address in the Basit Kargo panel. Empty uses the default sender address there.'),
            'max_length' => 64,
        ),
        array(
            'name'       => 'brand_id',
            'label'      => lang('Brand ID'),
            'help'       => lang('The ID of an approved brand in the Basit Kargo panel, printed on the label. Empty uses none.'),
            'max_length' => 64,
        ),
    );
}

/**
 * Basit Kargo's handler codes of the carriers the store knows, code =>
 * carrier. An own-contract code (SELF_ARAS) names the same carrier.
 *
 * @return array
 */
function pg_label_basitkargo_carriers()
{
    return array(
        'PTT'     => 'ptt',
        'MNG'     => 'mng',
        'YURTICI' => 'yurtici',
        'ARAS'    => 'aras',
        'SURAT'   => 'surat',
    );
}

/**
 * The HTTP transport: pg_curl_run() unless one is set in its place (the
 * tests). While one is set, a wait is recorded (pg_label_basitkargo_waits())
 * instead of slept.
 *
 * @param callable|null $replace function ($method, $url, $headers, $body)
 *                               answering status, body, error, sent, headers
 * @param bool          $reset   true to go back to pg_curl_run()
 * @return callable|null the transport set, null for pg_curl_run()
 */
function pg_label_basitkargo_transport($replace = null, $reset = false)
{
    static $transport = null;

    if ($reset) {
        $transport = null;
        pg_label_basitkargo_waits(true);
    } elseif ($replace !== null) {
        $transport = $replace;
        pg_label_basitkargo_waits(true);
    }

    return $transport;
}

/**
 * The waits asked for while a stand-in transport is set, in seconds.
 *
 * @param bool       $reset
 * @param float|null $add   a wait to record
 * @return array
 */
function pg_label_basitkargo_waits($reset = false, $add = null)
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
 * Wait, or record the wait while a stand-in transport is set.
 *
 * @param float $seconds
 */
function pg_label_basitkargo_sleep($seconds)
{
    if ($seconds <= 0) {
        return;
    }

    if (pg_label_basitkargo_transport() !== null) {
        pg_label_basitkargo_waits(false, $seconds);
        return;
    }

    usleep((int) ceil($seconds * 1000000));
}

/**
 * How long the next call must wait to keep under the limit a minute:
 * $times are the moments of the calls made, oldest first. Pure.
 *
 * @param float[] $times
 * @param float   $now
 * @param int     $limit
 * @return float seconds, 0.0 when the call may go now
 */
function pg_label_basitkargo_window_wait($times, $now, $limit = 600)
{
    $recent = array_values(array_filter($times, function ($time) use ($now) {
        return $time > $now - 60;
    }));

    if (count($recent) < $limit) {
        return 0.0;
    }

    return max(0.0, (float) $recent[count($recent) - $limit] + 60 - $now);
}

/**
 * The seconds a 429 answer's Retry-After asks for (seconds or an HTTP
 * date), 0 when it gives none. Pure.
 *
 * @param string $headers the answer's header block
 * @param int    $now
 * @return int
 */
function pg_label_basitkargo_retry_after($headers, $now)
{
    if (!preg_match('/^Retry-After:\s*([^\r\n]+)/mi', (string) $headers, $match)) {
        return 0;
    }

    $value = trim($match[1]);

    if (ctype_digit($value)) {
        return (int) $value;
    }

    $time = strtotime($value);

    return ($time === false) ? 0 : max(0, $time - (int) $now);
}

/**
 * A version 4 UUID for an Idempotency-Key.
 *
 * @return string
 */
function pg_label_basitkargo_uuid()
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

/**
 * One request to Basit Kargo, kept under the token's limit a minute.
 *
 * @param string     $method
 * @param string     $path    a path under the API
 * @param string     $token
 * @param array|null $payload JSON body
 * @param string     $key     an Idempotency-Key, '' for none
 * @return array status (0 when Basit Kargo was not reached), body, error,
 *               sent (whether the request may have reached it), headers
 */
function pg_label_basitkargo_http($method, $path, $token, $payload = null, $key = '')
{
    static $calls = array();

    $url = 'https://basitkargo.com/api' . $path;
    $headers = array('Accept: application/json, image/svg+xml', 'Authorization: Bearer ' . $token);
    $body = null;

    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    if ($key !== '') {
        $headers[] = 'Idempotency-Key: ' . $key;
    }

    $transport = pg_label_basitkargo_transport();
    $bucket = sha1($token);
    $times = isset($calls[$bucket]) ? $calls[$bucket] : array();
    pg_label_basitkargo_sleep(pg_label_basitkargo_window_wait($times, microtime(true)));
    $times[] = microtime(true);
    $calls[$bucket] = array_slice($times, -600);

    if ($transport !== null) {
        $call = call_user_func($transport, $method, $url, $headers, $body);
    } else {
        // Basit Kargo's host is fixed, so no address check. A barcode may
        // take a while: the reference advises 90 seconds.
        $call = pg_curl_run($method, $url, array(
            'headers'          => $headers,
            'body'             => $body,
            'timeout'          => 90,
            'connect_timeout'  => 10,
            'max_size'         => 10485760,
            'response_headers' => true,
        ));

        if (($call['status'] === 0) && ($call['errno'] !== 0)) {
            $call['error'] = trim($call['error'] . ' ' . pg_curl_tls_hint($call['errno']));
        }

        $call['sent'] = ($call['status'] !== 0) || ($call['request_size'] > 0);
    }

    return array(
        'status'  => isset($call['status']) ? (int) $call['status'] : 0,
        'body'    => isset($call['body']) ? (string) $call['body'] : '',
        'error'   => isset($call['error']) ? (string) $call['error'] : '',
        'sent'    => !empty($call['sent']),
        'headers' => isset($call['headers']) ? (string) $call['headers'] : '',
    );
}

/**
 * A request to the API read into one shape. A 429 with a Retry-After of at
 * most 10 seconds is waited for and asked once more; a request with an
 * Idempotency-Key that went out without an answer is asked once more with
 * the same key.
 *
 * @return array ok (a 2xx answer), reached, sent, status, data (the JSON
 *               answer, array), body, message (Basit Kargo's words or the
 *               transport's), retry_after, raw
 */
function pg_label_basitkargo_api($method, $path, $account, $payload = null, $key = '')
{
    $token = (string) $account['api_token'];
    $call = pg_label_basitkargo_http($method, $path, $token, $payload, $key);
    $retry_after = ($call['status'] === 429) ? pg_label_basitkargo_retry_after($call['headers'], time()) : 0;

    if (($call['status'] === 429) && ($retry_after <= 10)) {
        pg_label_basitkargo_sleep(max(1, $retry_after));
        $call = pg_label_basitkargo_http($method, $path, $token, $payload, $key);
        $retry_after = ($call['status'] === 429) ? pg_label_basitkargo_retry_after($call['headers'], time()) : 0;
    } elseif (($call['status'] === 0) && $call['sent'] && ($key !== '')) {
        $call = pg_label_basitkargo_http($method, $path, $token, $payload, $key);
    }

    $decoded = json_decode($call['body'], true);
    $decoded = is_array($decoded) ? $decoded : array();

    $read = array(
        'ok'          => ($call['status'] >= 200) && ($call['status'] < 300),
        'reached'     => ($call['status'] !== 0),
        'sent'        => $call['sent'],
        'status'      => $call['status'],
        'data'        => $decoded,
        'body'        => $call['body'],
        'message'     => '',
        'retry_after' => $retry_after,
        'raw'         => array('request' => $method . ' ' . $path, 'status' => $call['status'], 'answer' => $decoded ? $decoded : substr($call['body'], 0, 500)),
    );

    if ($call['status'] === 0) {
        $read['message'] = $call['error'];
    } elseif (!$read['ok']) {
        $words = array();

        foreach (array('message', 'error', 'detail') as $name) {
            if (isset($decoded[$name]) && is_scalar($decoded[$name]) && (trim((string) $decoded[$name]) !== '')) {
                $words[] = trim((string) $decoded[$name]);
            }
        }

        // The reference's validation answers are plain Turkish sentences.
        if (!$words && !$decoded && (stripos($call['body'], '<') === false) && (trim($call['body']) !== '')) {
            $words[] = mb_substr(trim($call['body']), 0, 200, 'UTF-8');
        }

        $read['message'] = $words ? implode(' ', array_unique($words)) : 'HTTP ' . $call['status'];
    }

    return $read;
}

/**
 * The failure of a request that did not succeed. A request that changes
 * something and went out without a readable answer, a server error, or an
 * order still being made (409) is unclear; a 429 is a plain refusal.
 *
 * @param array $read     pg_label_basitkargo_api()
 * @param bool  $changing whether the request opens or cancels
 * @return array
 */
function pg_label_basitkargo_failure($read, $changing)
{
    if (!$read['reached']) {
        return array(
            'success' => false,
            'unclear' => $changing && $read['sent'],
            'error'   => lang('The carrier label service could not be reached.'),
            'log'     => array('Basit Kargo could not be reached: ' . $read['message']),
            'raw'     => $read['raw'],
        );
    }

    if ($read['status'] === 429) {
        return array(
            'success' => false,
            'error'   => lang(array('string' => 'Basit Kargo is taking no more requests for now. Try again in {var:1} seconds.', 'vars' => array(max(1, $read['retry_after'])))),
            'log'     => array('Basit Kargo rate limit on ' . $read['raw']['request'] . '.'),
            'raw'     => $read['raw'],
        );
    }

    return array(
        'success' => false,
        'unclear' => $changing && (($read['status'] >= 500) || ($read['status'] === 409)),
        'error'   => lang('Basit Kargo refused the request.') . (($read['message'] !== '') ? ' (' . $read['message'] . ')' : ''),
        'log'     => array('Basit Kargo refused ' . $read['raw']['request'] . ': ' . $read['message']),
        'raw'     => $read['raw'],
    );
}

/**
 * A number for the JSON body, at most $places places.
 *
 * @return float|int
 */
function pg_label_basitkargo_number($value, $places = 2)
{
    $value = round(is_numeric($value) ? max(0, (float) $value) : 0, $places);

    return (floor($value) == $value) ? (int) $value : $value;
}

/**
 * The POST /v2/order/barcode body of a shipment.
 *
 * @param array  $shipment see the header of this file
 * @param array  $account
 * @param string $handler  the handler code
 * @return array
 */
function pg_label_basitkargo_order_body($shipment, $account, $handler)
{
    $shipment = is_array($shipment) ? $shipment : array();
    $text = function ($source, $name) {
        return (is_array($source) && isset($source[$name]) && is_scalar($source[$name])) ? trim((string) $source[$name]) : '';
    };

    $recipient = isset($shipment['recipient']) ? $shipment['recipient'] : array();
    $parcel = isset($shipment['parcel']) ? $shipment['parcel'] : array();
    $items = array();

    if (isset($shipment['items']) && is_array($shipment['items'])) {
        foreach ($shipment['items'] as $item) {
            if ($text($item, 'title') !== '') {
                $items[] = array('name' => $text($item, 'title'), 'quantity' => max(1, (int) $text($item, 'quantity')));
            }
        }
    }

    $body = array(
        // A return is collected from the customer and comes to the sender.
        'type'               => !empty($shipment['return']) ? 'INCOMING' : 'OUTGOING',
        'handlerCode'        => $handler,
        'content'            => array(
            'name'     => lang(array('string' => 'Order {var:1}', 'vars' => array($text($shipment, 'order_number')))),
            'code'     => $text($shipment, 'order_number'),
            'items'    => $items,
            'packages' => array(array(
                'height' => pg_label_basitkargo_number($text($parcel, 'height')),
                'width'  => pg_label_basitkargo_number($text($parcel, 'width')),
                'depth'  => pg_label_basitkargo_number($text($parcel, 'length')),
                'weight' => pg_label_basitkargo_number($text($parcel, 'weight'), 3),
            )),
        ),
        'client'             => array_filter(array(
            'name'    => $text($recipient, 'name'),
            'phone'   => $text($recipient, 'phone'),
            'email'   => $text($recipient, 'email'),
            'city'    => $text($recipient, 'state'),
            'town'    => $text($recipient, 'city'),
            'address' => trim($text($recipient, 'address_1') . ' ' . $text($recipient, 'address_2')),
        ), 'strlen'),
        'orderTotal'         => round(((int) $text($shipment, 'total')) / 100, 2),
        'orderPaymentType'   => 'OTHER',
        'orderPaymentStatus' => 'PAID',
    );

    foreach (array('address_id' => 'addressId', 'brand_id' => 'brandId') as $field => $name) {
        if ($text($account, $field) !== '') {
            $body[$name] = $text($account, $field);
        }
    }

    return $body;
}

/**
 * The carrier key of an order's handler, '' for one the store does not
 * know (ECONOMIC before a carrier is chosen, HEPSIJET).
 *
 * @param array $data an order
 * @return string
 */
function pg_label_basitkargo_carrier($data)
{
    $code = isset($data['shipmentInfo']['handler']['code']) && is_string($data['shipmentInfo']['handler']['code'])
        ? strtoupper($data['shipmentInfo']['handler']['code'])
        : '';

    if (strncmp($code, 'SELF_', 5) === 0) {
        $code = substr($code, 5);
    }

    $carriers = pg_label_basitkargo_carriers();

    return isset($carriers[$code]) ? $carriers[$code] : '';
}

/**
 * The shipment status of a Basit Kargo order status.
 *
 * @param string $code
 * @return string a pg_shipment_statuses() value, '' when unknown
 */
function pg_label_basitkargo_status($code)
{
    $statuses = array(
        'NEW'              => 'created',
        'READY_TO_SHIP'    => 'created',
        'SHIPPED'          => 'in_transit',
        'DELAYED'          => 'in_transit',
        'OUT_FOR_DELIVERY' => 'out_for_delivery',
        'DELIVERED'        => 'delivered',
        'RETURNING'        => 'returned',
        'RETURNED'         => 'returned',
        'NEEDS_SUPPORT'    => 'exception',
        'LOST'             => 'exception',
    );

    $code = strtoupper((string) $code);

    return isset($statuses[$code]) ? $statuses[$code] : '';
}

/**
 * A text field of an order, '' when missing.
 *
 * @return string
 */
function pg_label_basitkargo_text($data, $name)
{
    return (is_array($data) && isset($data[$name]) && is_scalar($data[$name])) ? trim((string) $data[$name]) : '';
}

/**
 * An order's carrier tracking number, else its Basit Kargo barcode.
 *
 * @return string
 */
function pg_label_basitkargo_number_of($data)
{
    $code = isset($data['shipmentInfo']) ? pg_label_basitkargo_text($data['shipmentInfo'], 'handlerShipmentCode') : '';

    return ($code !== '') ? $code : pg_label_basitkargo_text($data, 'barcode');
}

/**
 * A reference as a path segment: Basit Kargo's ids are letters, digits and
 * dashes.
 *
 * @return string '' when it cannot be one
 */
function pg_label_basitkargo_id($reference)
{
    $reference = is_scalar($reference) ? trim((string) $reference) : '';

    return (preg_match('/^[A-Za-z0-9-]{1,64}$/', $reference) === 1) ? $reference : '';
}

/**
 * An order's SVG label.
 *
 * @return array label ('' when it cannot be had), read
 */
function pg_label_basitkargo_svg($id, $account)
{
    $read = pg_label_basitkargo_api('GET', '/label/svg/' . $id, $account);

    return array(
        'label' => ($read['ok'] && pg_label_document_valid($read['body'], 'svg')) ? $read['body'] : '',
        'read'  => $read,
    );
}

function pg_label_basitkargo_test($account)
{
    $read = pg_label_basitkargo_api('GET', '/firm/status', $account);

    if (!$read['ok']) {
        return pg_label_basitkargo_failure($read, false);
    }

    $type = pg_label_basitkargo_text($read['data'], 'accountTypeLabel');
    $status = pg_label_basitkargo_text($read['data'], 'statusLabel');

    return array(
        'success' => true,
        'account' => trim($type . (($status !== '') ? ' (' . $status . ')' : '')),
        'log'     => array('Basit Kargo accepted the API token' . (empty($read['data']['approved']) ? '; the account is not approved yet' : '') . '.'),
    );
}

function pg_label_basitkargo_create($shipment, $account)
{
    $country = (is_array($shipment) && isset($shipment['recipient']['country']) && is_string($shipment['recipient']['country']))
        ? strtoupper(trim($shipment['recipient']['country']))
        : '';

    if (($country !== '') && ($country !== 'TR')) {
        return array('success' => false, 'error' => lang('Basit Kargo ships within Turkey only.'));
    }

    $handler = (is_array($shipment) && isset($shipment['service']) && is_string($shipment['service']) && (trim($shipment['service']) !== ''))
        ? $shipment['service']
        : (string) $account['handler'];
    $handler = strtoupper(trim($handler));

    if (preg_match('/^[A-Z_]{2,32}$/', $handler) !== 1) {
        $handler = 'ECONOMIC';
    }

    $read = pg_label_basitkargo_api('POST', '/v2/order/barcode', $account, pg_label_basitkargo_order_body($shipment, $account, $handler), pg_label_basitkargo_uuid());

    if (!$read['ok']) {
        return pg_label_basitkargo_failure($read, true);
    }

    $data = $read['data'];
    $id = pg_label_basitkargo_id(pg_label_basitkargo_text($data, 'id'));

    // Without an id or a number the order may exist all the same:
    // pg_label_answer() makes this an unclear failure.
    $answer = array(
        'success'         => true,
        'reference'       => $id,
        'tracking_number' => pg_label_basitkargo_number_of($data),
        'carrier'         => pg_label_basitkargo_carrier($data),
        'status'          => 'created',
        'barcode'         => pg_label_basitkargo_text($data, 'barcode'),
        'log'             => array('Basit Kargo order ' . $id . ' opened with ' . $handler . '.'),
        'raw'             => $read['raw'],
    );

    if ($id === '') {
        return $answer;
    }

    // The label is fetched again by label() when it cannot be had now: the
    // order is bought either way.
    $svg = pg_label_basitkargo_svg($id, $account);

    if ($svg['label'] !== '') {
        $answer['label'] = $svg['label'];
        $answer['label_format'] = 'svg';
    } else {
        $answer['log'][] = 'The Basit Kargo label could not be read: ' . ($svg['read']['ok'] ? 'not an SVG label' : $svg['read']['message']) . '.';
    }

    return $answer;
}

function pg_label_basitkargo_label($reference, $format, $account)
{
    $id = pg_label_basitkargo_id($reference);

    if ($format !== 'svg') {
        return array('success' => false, 'error' => lang('Basit Kargo gives labels as SVG only.'));
    }

    if ($id === '') {
        return array('success' => false, 'error' => lang('No shipment of the carrier label service is known for this parcel.'));
    }

    $svg = pg_label_basitkargo_svg($id, $account);

    if ($svg['label'] === '') {
        return $svg['read']['ok']
            ? array('success' => false, 'error' => lang('Basit Kargo gave a label that cannot be shown safely.'), 'raw' => array('request' => 'GET /label/svg/' . $id))
            : pg_label_basitkargo_failure($svg['read'], false);
    }

    return array('success' => true, 'reference' => $id, 'label' => $svg['label'], 'label_format' => 'svg');
}

function pg_label_basitkargo_cancel($reference, $account)
{
    $id = pg_label_basitkargo_id($reference);

    if ($id === '') {
        return array('success' => false, 'error' => lang('No shipment of the carrier label service is known for this parcel.'));
    }

    $read = pg_label_basitkargo_api('GET', '/v2/order/' . $id, $account);

    if (!$read['ok']) {
        return pg_label_basitkargo_failure($read, false);
    }

    $log = array();
    $barcode = pg_label_basitkargo_text($read['data'], 'barcode');

    // The barcode is taken back first (refused once the carrier collected
    // the parcel; the charge is refunded); then the order is deleted.
    if ($barcode !== '') {
        $cancel = pg_label_basitkargo_api('DELETE', '/order/barcode/' . rawurlencode($barcode), $account);

        if (!$cancel['ok']) {
            return pg_label_basitkargo_failure($cancel, true);
        }

        $log[] = 'Basit Kargo barcode ' . $barcode . ' cancelled.';
    }

    $delete = pg_label_basitkargo_api('DELETE', '/order/' . $id, $account);

    if ($delete['ok']) {
        $log[] = 'Basit Kargo order ' . $id . ' deleted.';
    } elseif ($barcode !== '') {
        $log[] = 'Basit Kargo order ' . $id . ' stays as a new order: ' . $delete['message'];
    } else {
        return pg_label_basitkargo_failure($delete, true);
    }

    return array('success' => true, 'reference' => $id, 'log' => $log);
}

function pg_label_basitkargo_track($reference, $account)
{
    $id = pg_label_basitkargo_id($reference);

    if ($id === '') {
        return array('success' => false, 'error' => lang('No shipment of the carrier label service is known for this parcel.'));
    }

    $read = pg_label_basitkargo_api('GET', '/v2/order/' . $id, $account);

    if (!$read['ok']) {
        return pg_label_basitkargo_failure($read, false);
    }

    return pg_label_basitkargo_track_answer($read['data'], $id, $read['raw']);
}

/**
 * An order's latest trace by its time: the reference lists them newest
 * first, but its answers are not promised in any order. A trace without a
 * readable time never wins over one with a time; of equal times the one
 * listed first does.
 *
 * @param array $data an order
 * @return array the trace, empty when none
 */
function pg_label_basitkargo_latest_trace($data)
{
    $latest = array();
    $latest_at = -1;

    $traces = (is_array($data) && isset($data['traces']) && is_array($data['traces'])) ? $data['traces'] : array();

    foreach ($traces as $trace) {
        if (!is_array($trace)) {
            continue;
        }

        $time = strtotime(pg_label_basitkargo_text($trace, 'time'));
        $at = ($time === false) ? 0 : max(0, $time);

        if (!$latest || ($at > $latest_at)) {
            $latest = $trace;
            $latest_at = $at;
        }
    }

    return $latest;
}

/**
 * A track() answer from an order in the GET /v2/order/{id} shape, shared by
 * track() and the movement webhook. An order without traces has no time
 * (status_at 0, so the tracking decision uses the moment it is read).
 *
 * @param array  $data an order
 * @param string $id   its reference
 * @param string $raw  the service's answer, kept with a failure
 * @return array
 */
function pg_label_basitkargo_track_answer($data, $id, $raw = '')
{
    $status = pg_label_basitkargo_status(pg_label_basitkargo_text($data, 'status'));

    if ($status === '') {
        return array('success' => false, 'error' => lang('Basit Kargo gives no status for this shipment yet.'), 'raw' => $raw);
    }

    $latest = pg_label_basitkargo_latest_trace($data);
    $time = strtotime(pg_label_basitkargo_text($latest, 'time'));
    $location = pg_label_basitkargo_text($latest, 'location');

    return array(
        'success'         => true,
        'reference'       => $id,
        'status'          => $status,
        'status_at'       => ($time === false) ? 0 : max(0, $time),
        'tracking_number' => pg_label_basitkargo_number_of($data),
        'carrier'         => pg_label_basitkargo_carrier($data),
        'log'             => ($location !== '') ? array('Basit Kargo: ' . $location . '.') : array(),
    );
}

/**
 * Basit Kargo has no webhook registration API: the merchant adds the
 * address in its panel and ticks both events.
 *
 * @return array path (as its panel names it), events (as its panel names them)
 */
function pg_label_basitkargo_webhook_manual()
{
    return array(
        'path'   => 'Yönetim Paneli → Ayarlar → Webhook',
        'events' => array('Durum Değişikliği', 'Kargo Hareketi'),
    );
}

/**
 * The two messages of the reference, neither signed: a status change
 * ({id, orderNumber, orderId, barcode, status, handler,
 * handlerShipmentCode}, no time) and a movement (the GET /v2/order/{id}
 * shape, traces included). The order id is the label reference; an
 * INCOMING order is a return.
 *
 * @param string $body
 * @return array|false list of reference, is_return, answer; false when unreadable
 */
function pg_label_basitkargo_webhook_read($body)
{
    $data = json_decode((string) $body, true);

    if (!is_array($data) || !isset($data['id']) || !isset($data['status'])) {
        return false;
    }

    $id = pg_label_basitkargo_id($data['id']);

    if ($id === '') {
        return array();
    }

    // A status change names its carrier at the top level; the order shape
    // keeps it in shipmentInfo.
    if (!isset($data['shipmentInfo']) || !is_array($data['shipmentInfo'])) {
        $data['shipmentInfo'] = array(
            'handler'             => (isset($data['handler']) && is_array($data['handler'])) ? $data['handler'] : array(),
            'handlerShipmentCode' => pg_label_basitkargo_text($data, 'handlerShipmentCode'),
        );
    }

    return array(array(
        'reference' => $id,
        'is_return' => strtoupper(pg_label_basitkargo_text($data, 'type')) === 'INCOMING',
        'answer'    => pg_label_basitkargo_track_answer($data, $id),
    ));
}
