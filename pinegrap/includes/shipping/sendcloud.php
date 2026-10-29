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
 * /parcels/tracking/{number} gives the carrier's events.
 *
 * Sendcloud has no sandbox: test mode ships with the free "Unstamped
 * letter" option (sendcloud:letter), which makes a real label no carrier
 * takes. The sender is one of the sender addresses kept in the Sendcloud
 * account.
 *
 * The contract is the one in includes/fn/carrier_labels.php; the shipment
 * create() takes is the one includes/shipping/geliver.php describes
 * (service: a Sendcloud shipping option code such as postnl:standard). No
 * customs declaration is sent yet, so a parcel that would need one (leaving
 * the EU, or to or from Great Britain abroad) is refused before a call. The
 * Shipments API makes outgoing shipments only: a return is refused.
 * $reference is Sendcloud's shipment id.
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
    return array(
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
    );
}

/**
 * The HTTP transport: pg_curl_run() unless one is set in its place (the
 * tests).
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
    } elseif ($replace !== null) {
        $transport = $replace;
    }

    return $transport;
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
        $read['message'] = $words ? substr(implode(' ', $words), 0, 300) : 'HTTP ' . $status;
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
 * the pointer names), or a detail sentence or list. Pure.
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

    foreach ($errors as $error) {
        if (!is_array($error)) {
            continue;
        }

        $words = pg_label_sendcloud_text($error, 'detail');
        $words = ($words !== '') ? $words : pg_label_sendcloud_text($error, 'msg');
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
 * The countries of the EU customs union: a parcel between two of them needs
 * no customs declaration.
 *
 * @return string[]
 */
function pg_label_sendcloud_eu_countries()
{
    return array(
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE',
        'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
    );
}

/**
 * Why a parcel between two countries cannot be sent without customs, ''
 * when it can: Sendcloud's sender countries, then within the EU or within
 * Great Britain. Pure.
 *
 * @param string $from
 * @param string $to
 * @return string
 */
function pg_label_sendcloud_route_error($from, $to)
{
    $from = strtoupper(trim((string) $from));
    $to = strtoupper(trim((string) $to));
    $senders = array('AT', 'BE', 'DE', 'ES', 'FR', 'GB', 'IT', 'NL');

    if ($to === '') {
        return lang('Sendcloud needs the country of the shipping address.');
    }

    if (!in_array($from, $senders, true)) {
        return lang(array('string' => 'Sendcloud does not ship from {var:1}.', 'vars' => array(($from === '') ? '?' : $from)));
    }

    $eu = pg_label_sendcloud_eu_countries();

    if (($from === $to) || (in_array($from, $eu, true) && in_array($to, $eu, true))) {
        return '';
    }

    return lang(array('string' => 'A parcel from {var:1} to {var:2} needs a customs declaration, which is not sent to Sendcloud yet.', 'vars' => array($from, $to)));
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
 * The POST /shipments/announce body of a shipment. Pure.
 *
 * @param array  $shipment see the header of this file
 * @param array  $account
 * @param int    $sender   the sender address id
 * @param string $option   the shipping option code
 * @return array
 */
function pg_label_sendcloud_shipment_body($shipment, $account, $sender, $option)
{
    $shipment = is_array($shipment) ? $shipment : array();
    $text = 'pg_label_sendcloud_text';
    $recipient = isset($shipment['recipient']) ? $shipment['recipient'] : array();
    $parcel = isset($shipment['parcel']) ? $shipment['parcel'] : array();
    $country = strtoupper($text($recipient, 'country'));
    $state = $text($recipient, 'state_code');

    $body = array(
        'label_details' => ($account['label_format'] === 'zpl') ? array('mime_type' => 'application/zpl', 'dpi' => 203) : array('mime_type' => 'application/pdf', 'dpi' => 72),
        'to_address'    => array_filter(array(
            'name'                => substr($text($recipient, 'name'), 0, 75),
            'address_line_1'      => $text($recipient, 'address_1'),
            'address_line_2'      => $text($recipient, 'address_2'),
            'postal_code'         => $text($recipient, 'zip'),
            'city'                => $text($recipient, 'city'),
            'country_code'        => $country,
            // An ISO 3166-2 code (IT-RM) only: a plate number or a name is
            // refused by the countries that need one.
            'state_province_code' => (preg_match('/^[A-Z]{2}-[A-Z0-9]{1,3}$/', strtoupper($state)) === 1) ? strtoupper($state) : '',
            'phone_number'        => $text($recipient, 'phone'),
            'email'               => $text($recipient, 'email'),
        ), 'strlen'),
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

    return $body;
}

/**
 * The cheapest shipping option Sendcloud priced that needs no service point,
 * no export documents and is not the free test letter. Pure.
 *
 * @param mixed $options the data of POST /shipping-options
 * @return string the option's code, '' when none
 */
function pg_label_sendcloud_option_pick($options)
{
    $cheapest = null;

    foreach (is_array($options) ? $options : array() as $option) {
        $code = pg_label_sendcloud_text($option, 'code');
        $needs = (isset($option['requirements']) && is_array($option['requirements'])) ? $option['requirements'] : array();
        $total = isset($option['quotes'][0]['price']['total']) ? pg_label_sendcloud_text($option['quotes'][0]['price']['total'], 'value') : '';

        if (($code === '') || (strncmp($code, 'sendcloud:letter', 16) === 0) || !empty($option['quote_error'])
            || !empty($needs['export_documents']) || !empty($needs['is_service_point_required']) || !is_numeric($total)) {
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
        return array('success' => false, 'error' => lang('Sendcloud return labels are not made from the store yet.'));
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
    $refusal = pg_label_sendcloud_route_error(pg_label_sendcloud_text($from, 'country_code'), $to);

    if ($refusal !== '') {
        return array('success' => false, 'error' => $refusal);
    }

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

        $option = pg_label_sendcloud_option_pick(isset($read['data']['data']) ? $read['data']['data'] : array());

        if ($option === '') {
            $words = pg_label_sendcloud_text($read['data'], 'message');

            return array(
                'success' => false,
                'error'   => lang('Sendcloud found no shipping option for this shipment.') . (($words !== '') ? ' (' . substr($words, 0, 300) . ')' : ''),
                'raw'     => $read['raw'],
            );
        }
    }

    // The announcement is the charge: a lost answer or a server error
    // leaves it unknown whether the label was made.
    $body = pg_label_sendcloud_shipment_body($shipment, $account, pg_label_sendcloud_text($from, 'id'), $option);
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
            'error'   => lang('Sendcloud could not make the label.') . ($words ? ' (' . substr(implode(' ', $words), 0, 300) . ')' : ''),
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
    $id = pg_label_sendcloud_id($reference);

    if ($id === '') {
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
