<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Kargonomi label driver: Kargonomi is a Turkish shipping aggregator with
 * one REST API (https://app.kargonomi.com.tr/api/v1, a bearer token) in
 * front of its own contracts with Aras, Sürat, PTT, Kolay Gelsin, HepsiJet
 * and other carriers. The driver follows its public reference
 * (www.kargonomi.com.tr/help/api-dokumantasyonu/kargonomi-api/):
 * POST /shipments opens a free draft (the province and district as
 * Kargonomi's ids from GET /states and GET /cities/{state}), GET
 * /shipment-price-comparison/{id} gives the carriers' prices, POST
 * /confirm-shipping-price buys the label with one carrier (-1: the
 * cheapest), after which Kargonomi makes the carrier's order and GET
 * /shipments/{id} gives its numbers and status; GET
 * /shipments/{id}/barcode gives the label (base64, PDF only), POST
 * /shipments/cancel asks for a cancellation (carried out within 36 hours).
 *
 * Kargonomi has no test mode: confirming the price of a shipment buys a
 * real, charged label. The sender is a warehouse of the Kargonomi account.
 *
 * The contract is the one in includes/fn/carrier_labels.php; the shipment
 * create() takes is the one includes/shipping/geliver.php describes
 * (service: a Kargonomi carrier code such as aras or surat). Kargonomi
 * ships within Turkey and the reference makes outgoing shipments only, so
 * an address abroad and a return are refused before a call. $reference is
 * Kargonomi's shipment id.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

function pg_label_kargonomi_info()
{
    return array(
        'carriers' => array('surat', 'aras', 'ptt'),
        'formats'  => array('pdf'),
    );
}

function pg_label_kargonomi_fields()
{
    return array(
        array(
            'name'     => 'api_token',
            'type'     => 'secret',
            'label'    => lang('API Token'),
            'help'     => lang('Created in the Kargonomi panel. Kargonomi has no test mode: confirming the price buys a real, charged label.'),
            'required' => true,
        ),
        array(
            'name'       => 'warehouse_id',
            'label'      => lang('Warehouse ID'),
            'help'       => lang('The ID of a warehouse (sender address) in the Kargonomi panel. Shipments leave from it.'),
            'required'   => true,
            'max_length' => 20,
        ),
        array(
            'name'       => 'provider',
            'label'      => lang('Carrier Code'),
            'help'       => lang('A Kargonomi carrier code such as aras, surat or ptt. Empty takes the cheapest offer.'),
            'max_length' => 32,
        ),
    );
}

/**
 * Kargonomi's carrier codes of the carriers the store knows, code =>
 * carrier. Kolay Gelsin, HepsiJet and others stay unknown: the shipping
 * method decides.
 *
 * @return array
 */
function pg_label_kargonomi_carriers()
{
    return array(
        'aras'    => 'aras',
        'surat'   => 'surat',
        'ptt'     => 'ptt',
        'yurtici' => 'yurtici',
        'mng'     => 'mng',
    );
}

/**
 * The HTTP transport: pg_curl_run() unless one is set in its place (the
 * tests). A set transport is also asked instead of waiting between reads
 * of a shipment, and setting one forgets the places read so far.
 *
 * @param callable|null $replace function ($method, $url, $headers, $body)
 *                               answering status, body, error, sent
 * @param bool          $reset   true to go back to pg_curl_run()
 * @return callable|null the transport set, null for pg_curl_run()
 */
function pg_label_kargonomi_transport($replace = null, $reset = false)
{
    static $transport = null;

    if ($reset || ($replace !== null)) {
        $transport = $reset ? null : $replace;
        $places = &pg_label_kargonomi_place_cache();
        $places = array();
    }

    return $transport;
}

/**
 * The provinces and districts read in this request, by token and path.
 *
 * @return array
 */
function &pg_label_kargonomi_place_cache()
{
    static $places = array();

    return $places;
}

/**
 * A text field, '' when missing.
 *
 * @return string
 */
function pg_label_kargonomi_text($data, $name)
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
 * @return array ok (a 2xx answer), reached, sent, status, data (the JSON
 *               answer, array), body, message (Kargonomi's words or the
 *               transport's), raw
 */
function pg_label_kargonomi_api($method, $path, $account, $payload = null)
{
    $headers = array('Accept: application/json', 'Authorization: Bearer ' . (string) $account['api_token']);
    $body = null;

    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $url = 'https://app.kargonomi.com.tr/api/v1' . $path;
    $transport = pg_label_kargonomi_transport();

    if ($transport !== null) {
        $call = call_user_func($transport, $method, $url, $headers, $body);
    } else {
        // Kargonomi's host is fixed, so no address check.
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
        'raw'     => array('request' => $method . ' ' . $path, 'status' => $status, 'answer' => $decoded ? $decoded : substr($text, 0, 200)),
    );

    if ($status === 0) {
        $read['message'] = isset($call['error']) ? (string) $call['error'] : '';
    } elseif (!$read['ok']) {
        $words = pg_label_kargonomi_error_texts($decoded);
        $read['message'] = $words ? mb_substr(implode(' ', $words), 0, 300, 'UTF-8') : 'HTTP ' . $status;
    }

    return $read;
}

/**
 * The words of a refused request: its message, then each field's first
 * validation message. Pure.
 *
 * @param array $data
 * @return string[]
 */
function pg_label_kargonomi_error_texts($data)
{
    $texts = array();
    $message = pg_label_kargonomi_text($data, 'message');

    if ($message !== '') {
        $texts[] = $message;
    }

    foreach ((isset($data['errors']) && is_array($data['errors'])) ? $data['errors'] : array() as $messages) {
        $first = is_array($messages) ? reset($messages) : $messages;

        if (is_scalar($first) && (trim((string) $first) !== '')) {
            $texts[] = trim((string) $first);
        }
    }

    return array_values(array_unique($texts));
}

/**
 * The failure of a request that did not succeed. A request that buys or
 * cancels and went out without an answer, or met a server error, is
 * unclear.
 *
 * @param array $read     pg_label_kargonomi_api()
 * @param bool  $changing whether the request buys or cancels
 * @return array
 */
function pg_label_kargonomi_failure($read, $changing)
{
    if (!$read['reached']) {
        return array(
            'success' => false,
            'unclear' => $changing && $read['sent'],
            'error'   => lang('The carrier label service could not be reached.'),
            'log'     => array('Kargonomi could not be reached: ' . $read['message']),
            'raw'     => $read['raw'],
        );
    }

    return array(
        'success' => false,
        'unclear' => $changing && ($read['status'] >= 500),
        'error'   => lang('Kargonomi refused the request.') . (($read['message'] !== '') ? ' (' . $read['message'] . ')' : ''),
        'log'     => array('Kargonomi refused ' . $read['raw']['request'] . ': ' . $read['message']),
        'raw'     => $read['raw'],
    );
}

/**
 * A phone number as Kargonomi takes it: ten digits, without the country
 * code or the leading zero; a Turkish number then starts with 2-5 (a
 * landline area, a mobile) or 8. Pure.
 *
 * @param string $phone
 * @return string '' when it is not a Turkish number
 */
function pg_label_kargonomi_phone($phone)
{
    $digits = preg_replace('/\D+/', '', (string) $phone);

    return (preg_match('/^(?:0090|90|0)?([2-58]\d{9})$/', $digits, $match) === 1) ? $match[1] : '';
}

/**
 * Turkish capitals and small letters folded to ASCII capitals, spaces
 * collapsed, so a place name matches however it was typed. Pure.
 *
 * @param string $text
 * @return string
 */
function pg_label_kargonomi_fold($text)
{
    $text = strtr((string) $text, array(
        'İ' => 'I', 'ı' => 'I', 'Ş' => 'S', 'ş' => 'S', 'Ğ' => 'G', 'ğ' => 'G',
        'Ü' => 'U', 'ü' => 'U', 'Ö' => 'O', 'ö' => 'O', 'Ç' => 'C', 'ç' => 'C',
    ));

    // ASCII capitals whatever the locale: strtoupper() follows LC_CTYPE
    // before PHP 8.2, and a Turkish locale turns i into a dotted capital.
    return strtr(trim(preg_replace('/\s+/', ' ', $text)), 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');
}

/**
 * Why Kargonomi cannot take a shipment, '' when it can: a return, an
 * address abroad, or a recipient the reference refuses (a name of two
 * words and five letters, an address of ten characters, a district, a
 * province and a ten-digit phone number). Pure.
 *
 * @param array $shipment
 * @return string
 */
function pg_label_kargonomi_shipment_error($shipment)
{
    if (!is_array($shipment) || !empty($shipment['return'])) {
        return lang('Kargonomi return labels are not made from the store yet.');
    }

    $recipient = isset($shipment['recipient']) ? $shipment['recipient'] : array();
    $country = strtoupper(pg_label_kargonomi_text($recipient, 'country'));

    if (($country !== '') && ($country !== 'TR')) {
        return lang('Kargonomi ships within Turkey only.');
    }

    $name = pg_label_kargonomi_text($recipient, 'name');
    $address = trim(pg_label_kargonomi_text($recipient, 'address_1') . ' ' . pg_label_kargonomi_text($recipient, 'address_2'));

    if ((mb_strlen($name) < 5) || (count(preg_split('/\s+/', $name)) < 2) || (mb_strlen($address) < 10)
        || (pg_label_kargonomi_text($recipient, 'city') === '') || (pg_label_kargonomi_text($recipient, 'state') === '')
        || (pg_label_kargonomi_phone(pg_label_kargonomi_text($recipient, 'phone')) === '')) {
        return lang('Kargonomi needs the recipient\'s name and surname, an address of at least ten characters, the district, the province and a Turkish phone number.');
    }

    return '';
}

/**
 * A list of Kargonomi's provinces (GET /states) or a province's districts
 * (GET /cities/{state}), read once a request.
 *
 * @param string $path
 * @param array  $account
 * @return array ok, data, read (pg_label_kargonomi_api(), null when cached)
 */
function pg_label_kargonomi_places($path, $account)
{
    $places = &pg_label_kargonomi_place_cache();
    $key = sha1((string) $account['api_token']) . ' ' . $path;

    if (isset($places[$key])) {
        return array('ok' => true, 'data' => $places[$key], 'read' => null);
    }

    $read = pg_label_kargonomi_api('GET', $path, $account);

    if ($read['ok']) {
        $places[$key] = $read['data'];
    }

    return array('ok' => $read['ok'], 'data' => $read['data'], 'read' => $read);
}

/**
 * The id of a place by its name in a list of provinces or districts: the
 * entry whose name reads the same in ASCII capitals. The list may be the
 * answer itself or its data. Pure.
 *
 * @param mixed  $data
 * @param string $name
 * @return string the id, '' when none matches
 */
function pg_label_kargonomi_place_id($data, $name)
{
    $list = (is_array($data) && isset($data['data']) && is_array($data['data'])) ? $data['data'] : (is_array($data) ? $data : array());
    $wanted = pg_label_kargonomi_fold($name);

    foreach ($list as $item) {
        $label = pg_label_kargonomi_text($item, 'name');
        $label = ($label !== '') ? $label : pg_label_kargonomi_text($item, 'title');
        $id = pg_label_kargonomi_text($item, 'id');

        if (($wanted !== '') && (pg_label_kargonomi_fold($label) === $wanted) && (preg_match('/^\d{1,10}$/', $id) === 1)) {
            return $id;
        }
    }

    return '';
}

/**
 * The POST /shipments body of a shipment: the warehouse as the sender, one
 * package whose desi is the larger of its volume (cm³ / 3000) and its
 * weight in kg, at least 1. Pure.
 *
 * @param array  $shipment see the header of this file
 * @param array  $account
 * @param string $state    Kargonomi's province id
 * @param string $city     Kargonomi's district id
 * @return array
 */
function pg_label_kargonomi_shipment_body($shipment, $account, $state, $city)
{
    $text = 'pg_label_kargonomi_text';
    $recipient = isset($shipment['recipient']) ? $shipment['recipient'] : array();
    $parcel = isset($shipment['parcel']) ? $shipment['parcel'] : array();
    $volume = ((float) $text($parcel, 'length')) * ((float) $text($parcel, 'width')) * ((float) $text($parcel, 'height'));
    $desi = max(1, round(max($volume / 3000, (float) $text($parcel, 'weight')), 2));
    $lines = array();

    if (isset($shipment['items']) && is_array($shipment['items'])) {
        foreach ($shipment['items'] as $item) {
            if ($text($item, 'title') !== '') {
                $lines[] = max(1, (int) $text($item, 'quantity')) . ' x ' . $text($item, 'title');
            }
        }
    }

    $content = mb_substr(implode(', ', $lines), 0, 255);

    $body = array_filter(array(
        'warehouse_id'   => (int) $text($account, 'warehouse_id'),
        'buyer_name'     => mb_substr($text($recipient, 'name'), 0, 255),
        'buyer_email'    => (strlen($text($recipient, 'email')) <= 255) ? $text($recipient, 'email') : '',
        'buyer_phone'    => pg_label_kargonomi_phone($text($recipient, 'phone')),
        'buyer_address'  => mb_substr(trim($text($recipient, 'address_1') . ' ' . $text($recipient, 'address_2')), 0, 512),
        'buyer_state_id' => (int) $state,
        'buyer_city_id'  => (int) $city,
    ), function ($value) {
        return ($value !== '') && ($value !== 0);
    });

    $body['packages'] = array(array_filter(array(
        'content' => ($content !== '') ? $content : $text($shipment, 'order_number'),
        'desi'    => ((float) $desi === floor($desi)) ? (int) $desi : $desi,
    ), function ($value) {
        return $value !== '';
    }));

    return array('shipment' => $body);
}

/**
 * The carrier to confirm: the offer of the carrier code asked for when it
 * has a price, else -1 (Kargonomi takes the cheapest) while any carrier
 * priced the shipment. Pure.
 *
 * @param array  $data     the answer of GET /shipment-price-comparison/{id}
 * @param string $provider a carrier code, '' for the cheapest
 * @return string the shipping_provider_id, '' when no offer fits
 */
function pg_label_kargonomi_offer($data, $provider)
{
    $offers = (isset($data['shipping_provider_with_price']) && is_array($data['shipping_provider_with_price'])) ? $data['shipping_provider_with_price'] : array();
    $priced = false;

    foreach ($offers as $offer) {
        $id = pg_label_kargonomi_text($offer, 'id');

        // "Hizmet Dışı Bölge" (out of the carrier's area) is no price.
        if (($id === '-1') || (preg_match('/\d/', pg_label_kargonomi_text($offer, 'price')) !== 1)) {
            continue;
        }

        if ($provider === '') {
            $priced = true;
        } elseif (strtolower(pg_label_kargonomi_text($offer, 'slug')) === $provider) {
            return $id;
        }
    }

    return $priced ? '-1' : '';
}

/**
 * The shipment in an answer: the answer itself, its data or its shipment.
 * Pure.
 *
 * @param array $data
 * @return array
 */
function pg_label_kargonomi_shipment_of($data)
{
    foreach (array('shipment', 'data') as $name) {
        if (isset($data[$name]['id']) && is_array($data[$name])) {
            return $data[$name];
        }
    }

    return isset($data['id']) ? $data : array();
}

/**
 * A shipment's carrier tracking code, else the carrier's barcode: the
 * first of a parcel number's shape (5-40 letters, digits, spaces and
 * hyphens). Pure.
 *
 * @param array $data a shipment
 * @return string '' while the carrier gave none
 */
function pg_label_kargonomi_number_of($data)
{
    foreach (array('shipping_webservice_tracking_code', 'shipping_webservice_barcode') as $name) {
        $number = pg_label_kargonomi_text($data, $name);

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9 -]{4,39}$/', $number) === 1) {
            return $number;
        }
    }

    return '';
}

/**
 * The carrier key of a shipment's carrier code, '' for one the store does
 * not know. Pure.
 *
 * @param array $data a shipment
 * @return string
 */
function pg_label_kargonomi_carrier($data)
{
    $carriers = pg_label_kargonomi_carriers();
    $slug = strtolower(pg_label_kargonomi_text($data, 'shipping_provider_slug'));

    return isset($carriers[$slug]) ? $carriers[$slug] : '';
}

/**
 * The shipment status of a Kargonomi status code. A cancellation, a failed
 * carrier order, an undelivered or a lost parcel need a look. Pure.
 *
 * @param string $code
 * @return string a pg_shipment_statuses() value, '' when unknown
 */
function pg_label_kargonomi_status($code)
{
    $statuses = array(
        'draft'                             => 'created',
        'ready'                             => 'created',
        'webservice_order_creating'         => 'created',
        'webservice_order_created'          => 'created',
        'webservice_checking_shipment'      => 'created',
        'webservice_shipment_started'       => 'in_transit',
        'webservice_shipment_delivered'     => 'delivered',
        'webservice_shipment_returning'     => 'returned',
        'webservice_shipment_not_delivered' => 'exception',
        'webservice_shipment_missing'       => 'exception',
        'webservice_order_failed'           => 'exception',
        'cancelled'                         => 'exception',
        'request_for_cancellation'          => 'exception',
    );

    $code = strtolower(trim((string) $code));

    return isset($statuses[$code]) ? $statuses[$code] : '';
}

/**
 * When a shipment reached its status: delivered at the customer's delivery
 * date, on its way from the day it was handed to the carrier, else when
 * Kargonomi last changed it. Pure.
 *
 * @param array  $data   a shipment
 * @param string $status pg_label_kargonomi_status()
 * @return int unix time, 0 when none
 */
function pg_label_kargonomi_time($data, $status)
{
    $names = array('updated_at');

    if ($status === 'delivered') {
        array_unshift($names, 'shipping_provider_customer_delivery_date');
    } elseif ($status === 'in_transit') {
        array_unshift($names, 'delivery_date_to_shipment_office');
    }

    foreach ($names as $name) {
        $time = strtotime(pg_label_kargonomi_text($data, $name));

        if (($time !== false) && ($time > 0)) {
            return $time;
        }
    }

    return 0;
}

/**
 * The PDF in an answer of GET /shipments/{id}/barcode, which the reference
 * calls base64 without an example: the body as a PDF, as base64, or the
 * first text of its JSON that is either. Pure.
 *
 * @param string $body
 * @param array  $data the JSON answer, array() when it is none
 * @return string '' when it holds no PDF
 */
function pg_label_kargonomi_document($body, $data)
{
    $texts = array((string) $body);

    array_walk_recursive($data, function ($value) use (&$texts) {
        if (is_string($value) && (strlen($value) > 20)) {
            $texts[] = $value;
        }
    });

    // A document is bytes: a PDF is kept as it came, only base64 is trimmed.
    foreach ($texts as $text) {
        $document = (strncmp($text, '%PDF-', 5) === 0) ? $text : base64_decode(preg_replace('/^data:application\/pdf;base64,/i', '', trim($text)), true);

        if (is_string($document) && pg_label_document_valid($document, 'pdf')) {
            return $document;
        }
    }

    return '';
}

/**
 * A shipment's label.
 *
 * @param string $id
 * @param array  $account
 * @return array label ('' when it cannot be had), read
 */
function pg_label_kargonomi_download($id, $account)
{
    $read = pg_label_kargonomi_api('GET', '/shipments/' . rawurlencode($id) . '/barcode?format=pdf', $account);

    return array('label' => $read['ok'] ? pg_label_kargonomi_document($read['body'], $read['data']) : '', 'read' => $read);
}

/**
 * Removes a draft that will not be bought, so none piles up in the
 * Kargonomi panel. A failure is only logged.
 *
 * @param string $id
 * @param array  $account
 * @return string a sentence for the log
 */
function pg_label_kargonomi_discard($id, $account)
{
    $read = pg_label_kargonomi_api('DELETE', '/shipments/' . rawurlencode($id), $account);

    return $read['ok']
        ? 'Kargonomi draft ' . $id . ' deleted.'
        : 'Kargonomi draft ' . $id . ' stays in the panel: ' . $read['message'];
}

/**
 * Waits between reads of a shipment, unless a stand-in transport is set.
 *
 * @param int $seconds
 */
function pg_label_kargonomi_sleep($seconds)
{
    if (pg_label_kargonomi_transport() === null) {
        sleep($seconds);
    }
}

/**
 * A reference as a path segment: Kargonomi's ids are numbers.
 *
 * @return string '' when it cannot be one
 */
function pg_label_kargonomi_id($reference)
{
    $reference = is_scalar($reference) ? trim((string) $reference) : '';

    return (preg_match('/^\d{1,20}$/', $reference) === 1) ? $reference : '';
}

function pg_label_kargonomi_test($account)
{
    $read = pg_label_kargonomi_api('GET', '/user/credit', $account);

    if (!$read['ok']) {
        return pg_label_kargonomi_failure($read, false);
    }

    $credit = isset($read['data']['data']) ? pg_label_kargonomi_text($read['data']['data'], 'credit') : '';

    return array(
        'success' => true,
        'log'     => array('Kargonomi accepted the API token' . (is_numeric($credit) ? '; balance ' . number_format((float) $credit, 2, '.', '') : '') . '.'),
    );
}

function pg_label_kargonomi_create($shipment, $account)
{
    $refusal = pg_label_kargonomi_shipment_error($shipment);

    if ($refusal !== '') {
        return array('success' => false, 'error' => $refusal);
    }

    $recipient = $shipment['recipient'];
    $province = pg_label_kargonomi_text($recipient, 'state');
    $district = pg_label_kargonomi_text($recipient, 'city');
    $states = pg_label_kargonomi_places('/states', $account);

    if (!$states['ok']) {
        return pg_label_kargonomi_failure($states['read'], false);
    }

    $state = pg_label_kargonomi_place_id($states['data'], $province);

    if ($state === '') {
        return array('success' => false, 'error' => lang(array('string' => 'Kargonomi does not know the province {var:1}.', 'vars' => array($province))));
    }

    $cities = pg_label_kargonomi_places('/cities/' . $state, $account);

    if (!$cities['ok']) {
        return pg_label_kargonomi_failure($cities['read'], false);
    }

    $city = pg_label_kargonomi_place_id($cities['data'], $district);

    if ($city === '') {
        return array('success' => false, 'error' => lang(array('string' => 'Kargonomi does not know the district {var:1} of {var:2}.', 'vars' => array($district, $province))));
    }

    $provider = strtolower(pg_label_kargonomi_text($shipment, 'service'));
    $provider = ($provider !== '') ? $provider : strtolower(pg_label_kargonomi_text($account, 'provider'));
    $provider = (preg_match('/^[a-z0-9_-]{2,32}$/', $provider) === 1) ? $provider : '';

    // A draft is free: a failure here buys nothing.
    $draft = pg_label_kargonomi_api('POST', '/shipments', $account, pg_label_kargonomi_shipment_body($shipment, $account, $state, $city));

    if (!$draft['ok']) {
        return pg_label_kargonomi_failure($draft, false);
    }

    $id = pg_label_kargonomi_id(pg_label_kargonomi_text(pg_label_kargonomi_shipment_of($draft['data']), 'id'));

    if ($id === '') {
        return array('success' => false, 'error' => lang('The carrier label service gave an answer that cannot be read. Check the shipment on the service\'s own screen before trying again.'), 'raw' => $draft['raw']);
    }

    $log = array('Kargonomi draft ' . $id . ' opened.');
    $offers = pg_label_kargonomi_api('GET', '/shipment-price-comparison/' . $id, $account);
    $choice = $offers['ok'] ? pg_label_kargonomi_offer($offers['data'], $provider) : '';

    if ($choice === '') {
        $failure = $offers['ok']
            ? array(
                'success' => false,
                'error'   => ($provider !== '')
                    ? lang(array('string' => 'Kargonomi has no offer of {var:1} for this shipment.', 'vars' => array($provider)))
                    : lang('Kargonomi has no offer for this shipment.'),
                'raw'     => $offers['raw'],
            )
            : pg_label_kargonomi_failure($offers, false);
        $failure['log'] = array_merge($log, isset($failure['log']) ? $failure['log'] : array(), array(pg_label_kargonomi_discard($id, $account)));

        return $failure;
    }

    // The confirmation is the charge: a lost answer or a server error
    // leaves it unknown whether the label was bought.
    $confirm = pg_label_kargonomi_api('POST', '/confirm-shipping-price', $account, array('shipment_id' => (int) $id, 'shipping_provider_id' => (int) $choice));

    if (!$confirm['ok']) {
        $failure = pg_label_kargonomi_failure($confirm, true);
        $failure['log'] = array_merge($log, $failure['log']);

        if (!$failure['unclear']) {
            $failure['log'][] = pg_label_kargonomi_discard($id, $account);
        }

        return $failure;
    }

    $log[] = 'Kargonomi shipment ' . $id . ' confirmed with ' . (($choice === '-1') ? 'the cheapest carrier' : $provider) . '.';

    // Kargonomi makes the carrier's order after the confirmation: the
    // shipment is read again until it has the carrier's number or the
    // order failed.
    $data = pg_label_kargonomi_shipment_of($confirm['data']);

    for ($round = 0; ($round < 5) && (pg_label_kargonomi_number_of($data) === '') && (pg_label_kargonomi_text($data, 'status') !== 'webservice_order_failed'); $round++) {
        pg_label_kargonomi_sleep(2);
        $again = pg_label_kargonomi_api('GET', '/shipments/' . $id, $account);

        if ($again['ok']) {
            $data = pg_label_kargonomi_shipment_of($again['data']);
        }
    }

    if (pg_label_kargonomi_text($data, 'status') === 'webservice_order_failed') {
        $words = pg_label_kargonomi_text($data, 'status_label');

        return array(
            'success' => false,
            'error'   => lang('Kargonomi could not make the carrier\'s order for this shipment.') . (($words !== '') ? ' (' . $words . ')' : ''),
            'log'     => array_merge($log, array('Kargonomi shipment ' . $id . ': webservice_order_failed.')),
        );
    }

    $number = pg_label_kargonomi_number_of($data);

    if ($number === '') {
        return array(
            'success' => false,
            'unclear' => true,
            'error'   => lang(array('string' => 'Kargonomi has not given the carrier\'s number of shipment {var:1} yet. Look it up in the Kargonomi panel before trying again.', 'vars' => array($id))),
            'log'     => $log,
        );
    }

    $answer = array(
        'success'         => true,
        'reference'       => $id,
        'tracking_number' => $number,
        'carrier'         => pg_label_kargonomi_carrier($data),
        'status'          => 'created',
        'barcode'         => pg_label_kargonomi_text($data, 'shipping_webservice_barcode'),
        'log'             => $log,
        'raw'             => array('request' => 'GET /shipments/' . $id, 'answer' => $data),
    );

    // The label is fetched again by label() when it cannot be had now: the
    // shipment is bought either way.
    $download = pg_label_kargonomi_download($id, $account);

    if ($download['label'] !== '') {
        $answer['label'] = $download['label'];
        $answer['label_format'] = 'pdf';
    } else {
        $answer['log'][] = 'The Kargonomi label could not be read: ' . ($download['read']['ok'] ? 'no PDF in the answer' : $download['read']['message']) . '.';
    }

    return $answer;
}

function pg_label_kargonomi_label($reference, $format, $account)
{
    $id = pg_label_kargonomi_id($reference);

    if ($format !== 'pdf') {
        return array('success' => false, 'error' => lang('Kargonomi gives labels as PDF only.'));
    }

    if ($id === '') {
        return array('success' => false, 'error' => lang('No shipment of the carrier label service is known for this parcel.'));
    }

    $download = pg_label_kargonomi_download($id, $account);

    if ($download['label'] === '') {
        return $download['read']['ok']
            ? array('success' => false, 'error' => lang('Kargonomi has no label for this shipment yet.'), 'raw' => array('request' => 'GET /shipments/' . $id . '/barcode'))
            : pg_label_kargonomi_failure($download['read'], false);
    }

    return array('success' => true, 'reference' => $id, 'label' => $download['label'], 'label_format' => 'pdf');
}

function pg_label_kargonomi_cancel($reference, $account)
{
    $id = pg_label_kargonomi_id($reference);

    if ($id === '') {
        return array('success' => false, 'error' => lang('No shipment of the carrier label service is known for this parcel.'));
    }

    // Taken for a shipment not yet handed to the carrier; Kargonomi carries
    // it out within 36 hours.
    $read = pg_label_kargonomi_api('POST', '/shipments/cancel', $account, array('shipment_id' => (int) $id));

    if (!$read['ok']) {
        return pg_label_kargonomi_failure($read, true);
    }

    $words = pg_label_kargonomi_text($read['data'], 'message');

    return array('success' => true, 'reference' => $id, 'log' => array('Kargonomi took the cancellation of shipment ' . $id . (($words !== '') ? ': ' . $words : '.')));
}

function pg_label_kargonomi_track($reference, $account)
{
    $id = pg_label_kargonomi_id($reference);

    if ($id === '') {
        return array('success' => false, 'error' => lang('No shipment of the carrier label service is known for this parcel.'));
    }

    $read = pg_label_kargonomi_api('GET', '/shipments/' . $id, $account);

    if (!$read['ok']) {
        return pg_label_kargonomi_failure($read, false);
    }

    $data = pg_label_kargonomi_shipment_of($read['data']);
    $status = pg_label_kargonomi_status(pg_label_kargonomi_text($data, 'status'));

    if ($status === '') {
        return array('success' => false, 'error' => lang('Kargonomi gives no status for this shipment yet.'), 'raw' => $read['raw']);
    }

    $words = pg_label_kargonomi_text($data, 'status_label');

    return array(
        'success'         => true,
        'reference'       => $id,
        'status'          => $status,
        'status_at'       => pg_label_kargonomi_time($data, $status),
        'tracking_number' => pg_label_kargonomi_number_of($data),
        'carrier'         => pg_label_kargonomi_carrier($data),
        'log'             => ($words !== '') ? array('Kargonomi: ' . $words . '.') : array(),
    );
}
