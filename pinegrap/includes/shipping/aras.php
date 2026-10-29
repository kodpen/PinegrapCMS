<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Aras Kargo label driver: the web service Aras Kargo runs for its
 * contracted senders (SOAP 1.1, arascargoservice.asmx), with the user name
 * and password Aras gives in every request. The driver follows the
 * service's own published description, its WSDL and the IrsaliyeData
 * schema the WSDL imports: SetOrder registers a shipment under an
 * integration code the sender makes up, GetArasBarcode gives Aras's label
 * for it (ZPL, or PDF as base64) with the tracking number, CancelDispatch
 * cancels it while no branch has taken it in, GetCargoInfo reads its
 * waybill (status, tracking number, delivery) and GetAddressList lists the
 * account's sender addresses.
 *
 * Test mode sends to the Aras test service (customerservicestest), which
 * takes the test user Aras gives with the integration; nothing is shipped
 * there.
 *
 * The contract is the one in includes/fn/carrier_labels.php; the shipment
 * create() takes is the one includes/shipping/geliver.php describes, its
 * service unused. Aras is a domestic carrier here: an address abroad is
 * refused before a call, and so is a return (SetOrder sends from the
 * account's own address only). $reference is the integration code.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

function pg_label_aras_info()
{
    return array(
        'carriers' => array('aras'),
        'formats'  => array('pdf', 'zpl'),
    );
}

function pg_label_aras_fields()
{
    return array(
        array(
            'name'       => 'username',
            'label'      => lang('User Name'),
            'help'       => lang('The web service user Aras Kargo gives for sending shipments, not the Esasweb sign-in.'),
            'required'   => true,
            'max_length' => 100,
        ),
        array(
            'name'     => 'password',
            'type'     => 'secret',
            'label'    => lang('Password'),
            'required' => true,
        ),
        array(
            'name'       => 'customer_code',
            'label'      => lang('Customer Code'),
            'help'       => lang('Your Aras Kargo customer code. Needed to read where a parcel is.'),
            'max_length' => 20,
        ),
        array(
            'name'    => 'mode',
            'type'    => 'select',
            'label'   => lang('Mode'),
            'help'    => lang('Test mode sends to the Aras Kargo test service, which takes the test user Aras gives; nothing is shipped there.'),
            'options' => array('test' => lang('Test'), 'live' => lang('Live')),
            'default' => 'test',
        ),
        array(
            'name'       => 'sender_address',
            'label'      => lang('Sender Address ID'),
            'help'       => lang('An address ID of your Aras Kargo account; the connection test lists them. Empty uses the account\'s default address.'),
            'max_length' => 40,
        ),
        array(
            'name'    => 'label_format',
            'type'    => 'select',
            'label'   => lang('Label Format'),
            'options' => array('pdf' => lang('PDF'), 'zpl' => lang('ZPL (thermal printer)')),
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
function pg_label_aras_transport($replace = null, $reset = false)
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
 * The service's address, as its WSDL names it.
 *
 * @param array $account
 * @return string
 */
function pg_label_aras_endpoint($account)
{
    return (isset($account['mode']) && ($account['mode'] === 'live'))
        ? 'https://customerws.araskargo.com.tr/arascargoservice.asmx'
        : 'https://customerservicestest.araskargo.com.tr/arascargoservice/arascargoservice.asmx';
}

/**
 * A text field of an account or a shipment, '' when missing.
 *
 * @return string
 */
function pg_label_aras_field($data, $name)
{
    return (is_array($data) && isset($data[$name]) && is_scalar($data[$name])) ? trim((string) $data[$name]) : '';
}

/**
 * The account's user name and password under the names an operation gives
 * them: the WSDL spells them three ways.
 *
 * @param array  $account
 * @param string $user
 * @param string $password
 * @return array
 */
function pg_label_aras_login($account, $user, $password)
{
    return array(
        $user     => pg_label_aras_field($account, 'username'),
        $password => isset($account['password']) ? (string) $account['password'] : '',
    );
}

/**
 * Text as XML character data: control characters dropped, a byte that is
 * not UTF-8 replaced. Pure.
 *
 * @param mixed $value
 * @return string
 */
function pg_label_aras_xml_text($value)
{
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', (string) $value);

    return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Elements in the order given: an array is an element holding elements, an
 * empty text is left out. The service reads its sequences in the WSDL's
 * order, so the caller gives them in that order. Pure.
 *
 * @param array $elements name => text or array
 * @return string
 */
function pg_label_aras_xml_elements($elements)
{
    $xml = '';

    foreach ($elements as $name => $value) {
        if (is_array($value)) {
            $xml .= '<' . $name . '>' . pg_label_aras_xml_elements($value) . '</' . $name . '>';
        } elseif ((string) $value !== '') {
            $xml .= '<' . $name . '>' . pg_label_aras_xml_text($value) . '</' . $name . '>';
        }
    }

    return $xml;
}

/**
 * The SOAP 1.1 request of one operation. Pure.
 *
 * @param string $operation
 * @param array  $parameters see pg_label_aras_xml_elements()
 * @return string
 */
function pg_label_aras_envelope($operation, $parameters)
{
    return '<?xml version="1.0" encoding="utf-8"?>'
        . '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
        . '<soap:Body><' . $operation . ' xmlns="http://tempuri.org/">' . pg_label_aras_xml_elements($parameters) . '</' . $operation . '></soap:Body>'
        . '</soap:Envelope>';
}

/**
 * An XML element as arrays: a leaf is its text; an element holding others
 * is child name => list of their values, every name a list, so one item
 * and many read alike. The inline schema of a .NET DataSet is left out.
 * Pure.
 *
 * @param DOMNode $node
 * @return array|string
 */
function pg_label_aras_xml_array($node)
{
    $values = array();
    $leaf = true;

    foreach ($node->childNodes as $child) {
        if ($child->nodeType !== XML_ELEMENT_NODE) {
            continue;
        }

        $leaf = false;

        if ($child->namespaceURI === 'http://www.w3.org/2001/XMLSchema') {
            continue;
        }

        $values[$child->localName][] = pg_label_aras_xml_array($child);
    }

    return $leaf ? trim($node->textContent) : $values;
}

/**
 * A SOAP answer read: whether it is an envelope, the fault's words, and
 * the operation's result (pg_label_aras_xml_array(); null when the answer
 * has none). An answer with a document type is not read. Pure.
 *
 * @param string $body
 * @param string $operation
 * @return array envelope, fault, result
 */
function pg_label_aras_parse($body, $operation)
{
    $out = array('envelope' => false, 'fault' => '', 'result' => null);
    $body = (string) $body;

    if (($body === '') || !class_exists('DOMDocument') || (stripos($body, '<!DOCTYPE') !== false)) {
        return $out;
    }

    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = $dom->loadXML($body, LIBXML_NONET | LIBXML_COMPACT);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded || !$dom->documentElement || ($dom->documentElement->localName !== 'Envelope')) {
        return $out;
    }

    $xp = new DOMXPath($dom);
    $soap_body = $xp->query('/*[local-name()="Envelope"]/*[local-name()="Body"]')->item(0);

    if (!$soap_body) {
        return $out;
    }

    $out['envelope'] = true;
    $fault = $xp->query('*[local-name()="Fault"]', $soap_body)->item(0);

    if ($fault) {
        // SOAP 1.1 faultstring, or a SOAP 1.2 Reason/Text.
        $words = $xp->query('*[local-name()="faultstring"] | *[local-name()="Reason"]/*[local-name()="Text"]', $fault)->item(0);
        $out['fault'] = $words ? trim($words->textContent) : '';
        $out['fault'] = ($out['fault'] !== '') ? substr($out['fault'], 0, 300) : 'SOAP fault';

        return $out;
    }

    $result = $xp->query('*[local-name()="' . $operation . 'Response"]/*[local-name()="' . $operation . 'Result"]', $soap_body)->item(0);

    if ($result) {
        $value = pg_label_aras_xml_array($result);
        $out['result'] = is_array($value) ? $value : array();
    }

    return $out;
}

/**
 * The first value of a name in pg_label_aras_xml_array(), null when none.
 *
 * @return array|string|null
 */
function pg_label_aras_first($data, $name)
{
    return (is_array($data) && isset($data[$name][0])) ? $data[$name][0] : null;
}

/**
 * The first value of a name as text, '' when none.
 *
 * @return string
 */
function pg_label_aras_value($data, $name)
{
    $value = pg_label_aras_first($data, $name);

    return is_string($value) ? $value : '';
}

/**
 * Every value of a name, a list.
 *
 * @return array
 */
function pg_label_aras_list($data, $name)
{
    return (is_array($data) && isset($data[$name]) && is_array($data[$name])) ? $data[$name] : array();
}

/**
 * An answer as it is logged: the label documents are left out.
 *
 * @param array $result
 * @return array
 */
function pg_label_aras_raw($result)
{
    foreach (array('Images', 'ZebraZpl', 'ZebraEpl', 'ZebraPdf') as $name) {
        if (isset($result[$name])) {
            $result[$name] = '(' . strlen(json_encode($result[$name])) . ' bytes)';
        }
    }

    return $result;
}

/**
 * One operation of the service, its answer read into one shape.
 *
 * @param string $operation
 * @param array  $parameters see pg_label_aras_xml_elements()
 * @param array  $account
 * @return array ok (an envelope without a fault, HTTP 200), reached, sent,
 *               readable (an envelope was read, a fault included), status,
 *               result (array), message (the fault's or the transport's
 *               words), raw
 */
function pg_label_aras_call($operation, $parameters, $account)
{
    $read = array('ok' => false, 'reached' => false, 'sent' => false, 'readable' => false, 'status' => 0, 'result' => array(), 'message' => '', 'raw' => array('request' => $operation));

    // Nothing goes out when the answer could not be read.
    if (!class_exists('DOMDocument')) {
        $read['message'] = 'the PHP DOM extension is missing';

        return $read;
    }

    $url = pg_label_aras_endpoint($account);
    $headers = array('Content-Type: text/xml; charset=utf-8', 'SOAPAction: "http://tempuri.org/' . $operation . '"');
    $body = pg_label_aras_envelope($operation, $parameters);
    $transport = pg_label_aras_transport();

    if ($transport !== null) {
        $call = call_user_func($transport, 'POST', $url, $headers, $body);
    } else {
        $call = pg_curl_run('POST', $url, array(
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
    $parsed = pg_label_aras_parse($text, $operation);

    $read['reached'] = ($status !== 0);
    $read['sent'] = !empty($call['sent']);
    $read['readable'] = $parsed['envelope'];
    $read['status'] = $status;
    $read['result'] = is_array($parsed['result']) ? $parsed['result'] : array();
    $read['ok'] = ($status === 200) && $parsed['envelope'] && ($parsed['fault'] === '');
    $read['raw'] = array(
        'request' => $operation,
        'status'  => $status,
        'answer'  => ($parsed['fault'] !== '') ? array('fault' => $parsed['fault']) : (is_array($parsed['result']) ? pg_label_aras_raw($parsed['result']) : substr($text, 0, 200)),
    );

    if ($status === 0) {
        $read['message'] = isset($call['error']) ? (string) $call['error'] : '';
    } elseif ($parsed['fault'] !== '') {
        $read['message'] = $parsed['fault'];
    } elseif (!$read['ok']) {
        $read['message'] = 'HTTP ' . $status;
    }

    return $read;
}

/**
 * The failure of a call that did not succeed. A call that registers or
 * cancels and went out without a readable answer is unclear; a SOAP fault
 * is the service's own refusal, and an unreadable 4xx was turned away
 * before anything was done.
 *
 * @param array $read     pg_label_aras_call()
 * @param bool  $changing whether the call registers or cancels
 * @return array
 */
function pg_label_aras_failure($read, $changing)
{
    if (!$read['reached']) {
        return array(
            'success' => false,
            'unclear' => $changing && $read['sent'],
            'error'   => lang('The carrier label service could not be reached.'),
            'log'     => array('Aras Kargo could not be reached: ' . $read['message']),
            'raw'     => $read['raw'],
        );
    }

    $turned_away = ($read['status'] >= 400) && ($read['status'] < 500);

    return array(
        'success' => false,
        'unclear' => $changing && !$read['readable'] && !$turned_away,
        'error'   => lang('Aras Kargo refused the request.') . (($read['message'] !== '') ? ' (' . $read['message'] . ')' : ''),
        'log'     => array('Aras Kargo refused ' . $read['raw']['request'] . ': ' . $read['message']),
        'raw'     => $read['raw'],
    );
}

/**
 * A phone number as Turkish carriers write it: eleven digits with the
 * leading zero (05321234567), the country code dropped; another number is
 * passed on as its digits. Pure.
 *
 * @param string $phone
 * @return string
 */
function pg_label_aras_phone($phone)
{
    $digits = preg_replace('/\D+/', '', (string) $phone);

    if (preg_match('/^(?:0090|90)(\d{10})$/', $digits, $match) || preg_match('/^0?(\d{10})$/', $digits, $match)) {
        return '0' . $match[1];
    }

    return $digits;
}

/**
 * A new integration code: the order number's digits (the last ten) and six
 * random ones, at least ten digits. Digits only, as a branch reads it out
 * and scans it; the random part keeps two labels of one order apart. Pure.
 *
 * @param string $order_number
 * @param int    $random 0-999999
 * @return string
 */
function pg_label_aras_integration_code($order_number, $random)
{
    $digits = substr(preg_replace('/\D+/', '', (string) $order_number), -10);

    return str_pad($digits . sprintf('%06d', abs((int) $random) % 1000000), 10, '0', STR_PAD_LEFT);
}

/**
 * A reference as an integration code, '' when it cannot be one.
 *
 * @return string
 */
function pg_label_aras_code($reference)
{
    $reference = is_scalar($reference) ? trim((string) $reference) : '';

    return (preg_match('/^[A-Za-z0-9_-]{1,40}$/', $reference) === 1) ? $reference : '';
}

/**
 * Why Aras cannot take a shipment, '' when it can: a return, an address
 * abroad, or a shipping address without what the waybill needs. Pure.
 *
 * @param array $shipment
 * @return string
 */
function pg_label_aras_shipment_error($shipment)
{
    if (!is_array($shipment) || !empty($shipment['return'])) {
        return lang('Aras Kargo return labels are not made from the store yet.');
    }

    $recipient = isset($shipment['recipient']) ? $shipment['recipient'] : array();
    $country = strtoupper(pg_label_aras_field($recipient, 'country'));

    if (($country !== '') && ($country !== 'TR')) {
        return lang('Aras Kargo labels are made for addresses in Turkey only.');
    }

    foreach (array('name', 'address_1', 'city', 'state') as $name) {
        if (pg_label_aras_field($recipient, $name) === '') {
            return lang('Aras Kargo needs the name, address, district, province and phone number of the shipping address.');
        }
    }

    if (strlen(pg_label_aras_phone(pg_label_aras_field($recipient, 'phone'))) < 10) {
        return lang('Aras Kargo needs the name, address, district, province and phone number of the shipping address.');
    }

    return '';
}

/**
 * The Order of SetOrder, its elements in the WSDL's order. Weight and desi
 * (cm³ / 3000) go as whole numbers rounded up, at least 1: the service
 * takes them as text and the branch measures the parcel again. The
 * sender pays (PayorTypeCode 1); one piece, no cash on delivery. Pure.
 *
 * @param array  $shipment see the header of this file
 * @param array  $account
 * @param string $code     the integration code
 * @return array
 */
function pg_label_aras_order($shipment, $account, $code)
{
    $field = 'pg_label_aras_field';
    $recipient = isset($shipment['recipient']) ? $shipment['recipient'] : array();
    $parcel = isset($shipment['parcel']) ? $shipment['parcel'] : array();

    $weight = max(1, (int) ceil(round((float) $field($parcel, 'weight'), 3)));
    $volume = ((float) $field($parcel, 'length')) * ((float) $field($parcel, 'width')) * ((float) $field($parcel, 'height'));
    $desi = max(1, (int) ceil(round($volume / 3000, 3)));

    $lines = array();

    if (isset($shipment['items']) && is_array($shipment['items'])) {
        foreach ($shipment['items'] as $item) {
            if ($field($item, 'title') !== '') {
                $lines[] = max(1, (int) $field($item, 'quantity')) . ' x ' . $field($item, 'title');
            }
        }
    }

    $description = mb_substr(implode(', ', $lines), 0, 100);
    $order_number = mb_substr($field($shipment, 'order_number'), 0, 30);
    $order_number = ($order_number !== '') ? $order_number : $code;

    $order = pg_label_aras_login($account, 'UserName', 'Password') + array(
        'TradingWaybillNumber' => $order_number,
        'InvoiceNumber'        => $order_number,
        'ReceiverName'         => mb_substr($field($recipient, 'name'), 0, 100),
        'ReceiverAddress'      => mb_substr(trim($field($recipient, 'address_1') . ' ' . $field($recipient, 'address_2')), 0, 250),
        'ReceiverPhone1'       => pg_label_aras_phone($field($recipient, 'phone')),
        'ReceiverCityName'     => $field($recipient, 'state'),
        'ReceiverTownName'     => $field($recipient, 'city'),
        'VolumetricWeight'     => (string) $desi,
        'Weight'               => (string) $weight,
        'PieceCount'           => '1',
        'IntegrationCode'      => $code,
        'Description'          => $description,
        'PayorTypeCode'        => '1',
        'IsWorldWide'          => '0',
        'IsCod'                => '0',
        'PieceDetails'         => array('PieceDetail' => array(
            'VolumetricWeight' => (string) $desi,
            'Weight'           => (string) $weight,
            'BarcodeNumber'    => $code,
            'Description'      => $description,
        )),
    );

    if ($field($account, 'sender_address') !== '') {
        $order['SenderAccountAddressId'] = $field($account, 'sender_address');
    }

    return $order;
}

/**
 * The label document of a GetArasBarcode result: its ZPL pieces one after
 * the other, or its first PDF (base64). '' when it has none of the format.
 * Pure.
 *
 * @param array  $result
 * @param string $format pdf or zpl
 * @return string
 */
function pg_label_aras_document($result, $format)
{
    if ($format === 'zpl') {
        $labels = array();

        foreach (pg_label_aras_list(pg_label_aras_first($result, 'ZebraZpl'), 'string') as $zpl) {
            if (is_string($zpl) && (stripos($zpl, '^XA') !== false)) {
                $labels[] = $zpl;
            }
        }

        return implode("\n", $labels);
    }

    foreach (pg_label_aras_list(pg_label_aras_first($result, 'ZebraPdf'), 'string') as $pdf) {
        $pdf = is_string($pdf) ? $pdf : '';
        $document = (strncmp($pdf, '%PDF-', 5) === 0) ? $pdf : base64_decode($pdf, true);

        if (is_string($document) && pg_label_document_valid($document, 'pdf')) {
            return $document;
        }
    }

    return '';
}

/**
 * What a GetArasBarcode result gives: whether Aras answered with success
 * (ResultCode 0), its words, the first piece's tracking number and barcode,
 * and the label. Pure.
 *
 * @param array  $result
 * @param string $format pdf or zpl
 * @return array ok, message, tracking_number, barcode, label
 */
function pg_label_aras_barcode_read($result, $format)
{
    $model = array();

    foreach (pg_label_aras_list(pg_label_aras_first($result, 'BarcodeModelLst'), 'BarcodeModel') as $item) {
        if (is_array($item)) {
            $model = $item;
            break;
        }
    }

    return array(
        'ok'              => (pg_label_aras_value($result, 'ResultCode') === '0'),
        'message'         => substr(pg_label_aras_value($result, 'Message'), 0, 300),
        'tracking_number' => pg_label_aras_value($model, 'TrackingNumber'),
        'barcode'         => pg_label_aras_value($model, 'Barcode'),
        'label'           => pg_label_aras_document($result, $format),
    );
}

/**
 * The waybill rows (IrsData) of a GetCargoInfo result, a .NET DataSet:
 * diffgram, then the data set element, then its rows. Pure.
 *
 * @param array $result
 * @return array list of rows
 */
function pg_label_aras_rows($result)
{
    $rows = array();

    foreach (pg_label_aras_list($result, 'diffgram') as $diffgram) {
        foreach (is_array($diffgram) ? $diffgram : array() as $sets) {
            foreach ($sets as $set) {
                foreach (pg_label_aras_list($set, 'IrsData') as $row) {
                    if (is_array($row)) {
                        $rows[] = $row;
                    }
                }
            }
        }
    }

    return $rows;
}

/**
 * A time Aras writes as unix time, 0 when none: a date as d.m.Y or Y-m-d,
 * with the time after it or in $clock, at its offset when it names one,
 * else Turkish time. Pure.
 *
 * @param string $date
 * @param string $clock H:i or H:i:s
 * @return int
 */
function pg_label_aras_time($date, $clock = '')
{
    $date = trim((string) $date);

    if (preg_match('/^(\d{1,2})[.\/](\d{1,2})[.\/](\d{4})/', $date, $match)) {
        list($day, $month, $year) = array((int) $match[1], (int) $match[2], (int) $match[3]);
    } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $date, $match)) {
        list($year, $month, $day) = array((int) $match[1], (int) $match[2], (int) $match[3]);
    } else {
        return 0;
    }

    if (!checkdate($month, $day, $year) || ($year < 2000)) {
        return 0;
    }

    $rest = trim(substr($date, strlen($match[0])));
    $hour = $minute = $second = 0;
    $zone = 'Europe/Istanbul';

    if (preg_match('/^T?(\d{1,2}):(\d{2})(?::(\d{2}))?(?:\.\d+)?\s*(Z|[+-]\d{2}:?\d{2})?/i', $rest, $time)
        || preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?/', trim((string) $clock), $time)) {
        $hour = (int) $time[1];
        $minute = (int) $time[2];
        $second = isset($time[3]) ? (int) $time[3] : 0;

        if (!empty($time[4])) {
            $zone = (strtoupper($time[4]) === 'Z') ? 'UTC' : $time[4];
        }
    }

    if (($hour > 23) || ($minute > 59) || ($second > 59)) {
        return 0;
    }

    try {
        $moment = new DateTime(sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second), new DateTimeZone($zone));
    } catch (\Exception $exception) {
        return 0;
    }

    return max(0, $moment->getTimestamp());
}

/**
 * Turkish capitals and small letters folded to ASCII capitals, spaces
 * collapsed, for reading the status words. Pure.
 *
 * @param string $text
 * @return string
 */
function pg_label_aras_fold($text)
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
 * A waybill row's tracking number (KARGO_TAKIP_NO, a decimal column), ''
 * when it holds none. Pure.
 *
 * @param array $row
 * @return string
 */
function pg_label_aras_row_number($row)
{
    $number = preg_replace('/\.0*$/', '', pg_label_aras_value($row, 'KARGO_TAKIP_NO'));

    return (preg_match('/^\d{5,40}$/', $number) === 1) ? $number : '';
}

/**
 * A waybill row's return reason (IADE_SEBEBI) when it names one: words,
 * not a dash or a word for none. Pure.
 *
 * @param array $row
 * @return string '' when it gives no reason
 */
function pg_label_aras_return_reason($row)
{
    $reason = pg_label_aras_value($row, 'IADE_SEBEBI');

    if ((preg_match('/\pL/u', $reason) !== 1) || in_array(pg_label_aras_fold($reason), array('YOK', 'YOKTUR', 'BOS', 'NULL', 'NONE'), true)) {
        return '';
    }

    return $reason;
}

/**
 * The shipment status of a waybill row. The schema publishes the columns,
 * not the values of DURUM_KODU, so the row is read by what it holds: a
 * return reason or a return in the status words is returned; the words
 * "teslim edildi", or a delivery date with the name of who took it, is
 * delivered; "dağıtımda" is out for delivery; a waybill or tracking
 * number is on its way. Pure.
 *
 * @param array $row an IrsData row
 * @return string a pg_shipment_statuses() value, '' when unknown
 */
function pg_label_aras_status($row)
{
    if (!is_array($row)) {
        return '';
    }

    $words = pg_label_aras_fold(pg_label_aras_value($row, 'DURUMU'));

    if ((pg_label_aras_return_reason($row) !== '') || (strpos($words, 'IADE') !== false)) {
        return 'returned';
    }

    if ((strpos($words, 'TESLIM EDILDI') !== false)
        || ((pg_label_aras_time(pg_label_aras_value($row, 'TESLIM_TARIHI')) > 0) && (pg_label_aras_value($row, 'TESLIM_ALAN') !== ''))) {
        return 'delivered';
    }

    if (preg_match('/DAGITIMDA|DAGITIMA CIK/', $words) === 1) {
        return 'out_for_delivery';
    }

    if ((pg_label_aras_row_number($row) !== '') || (pg_label_aras_value($row, 'IRSALIYE_NUMARA') !== '')) {
        return 'in_transit';
    }

    return '';
}

/**
 * The latest waybill row: the one with the latest waybill date, the later
 * one of equal dates. Pure.
 *
 * @param array $rows
 * @return array|null
 */
function pg_label_aras_latest_row($rows)
{
    $latest = null;
    $latest_at = -1;

    foreach ($rows as $row) {
        $at = pg_label_aras_time(pg_label_aras_value($row, 'IRSALIYE_TARIH'));

        if ($at >= $latest_at) {
            $latest = $row;
            $latest_at = $at;
        }
    }

    return $latest;
}

function pg_label_aras_test($account)
{
    $read = pg_label_aras_call('GetAddressList', pg_label_aras_login($account, 'userName', 'password'), $account);

    if (!$read['ok']) {
        return pg_label_aras_failure($read, false);
    }

    $addresses = array_values(array_filter(pg_label_aras_list($read['result'], 'CustomerAddress'), 'is_array'));

    if (!$addresses) {
        return array('success' => false, 'error' => lang('Aras Kargo listed no sender address for this user. Check the user name and password; your Aras branch adds the addresses.'), 'raw' => $read['raw']);
    }

    $wanted = pg_label_aras_field($account, 'sender_address');
    $address = ($wanted === '') ? $addresses[0] : null;

    foreach ($addresses as $item) {
        if (($wanted !== '') && (pg_label_aras_value($item, 'AddressId') === $wanted)) {
            $address = $item;
        }
    }

    if ($address === null) {
        return array('success' => false, 'error' => lang('Aras Kargo has no sender address of this ID for this user.'), 'raw' => $read['raw']);
    }

    $ids = array();

    foreach ($addresses as $item) {
        $ids[] = pg_label_aras_value($item, 'AddressId');
    }

    $name = pg_label_aras_value($address, 'Sube');

    return array(
        'success' => true,
        'account' => mb_substr(($name !== '') ? $name : pg_label_aras_value($address, 'Adres'), 0, 60),
        'log'     => array('Aras Kargo accepted the user' . (($account['mode'] === 'live') ? '' : ' in test mode') . '; sender addresses: ' . implode(', ', array_filter($ids, 'strlen')) . '.'),
    );
}

function pg_label_aras_create($shipment, $account)
{
    $refusal = pg_label_aras_shipment_error($shipment);

    if ($refusal !== '') {
        return array('success' => false, 'error' => $refusal);
    }

    $code = pg_label_aras_integration_code(pg_label_aras_field($shipment, 'order_number'), random_int(0, 999999));
    $test = ($account['mode'] !== 'live') ? ' (test)' : '';
    $read = pg_label_aras_call('SetOrder', array('orderInfo' => array('Order' => pg_label_aras_order($shipment, $account, $code))) + pg_label_aras_login($account, 'userName', 'password'), $account);

    if (!$read['ok']) {
        $failure = pg_label_aras_failure($read, true);
        $failure['log'][] = 'Integration code ' . $code . '.';

        return $failure;
    }

    $info = array();

    foreach (pg_label_aras_list($read['result'], 'OrderResultInfo') as $item) {
        if (is_array($item)) {
            $info = $item;
            break;
        }
    }

    $result = pg_label_aras_value($info, 'ResultCode');

    // An answer that does not say leaves it unknown whether the shipment
    // was registered.
    if ($result === '') {
        return array(
            'success' => false,
            'unclear' => true,
            'error'   => lang('The carrier label service gave an answer that cannot be read. Check the shipment on the service\'s own screen before trying again.'),
            'log'     => array('Aras Kargo gave no result for integration code ' . $code . '.'),
            'raw'     => $read['raw'],
        );
    }

    if ($result !== '0') {
        $words = substr(pg_label_aras_value($info, 'ResultMessage'), 0, 300);

        return array(
            'success' => false,
            'error'   => lang('Aras Kargo refused the request.') . ' (' . (($words !== '') ? $words : 'ResultCode ' . $result) . ')',
            'log'     => array('Aras Kargo refused integration code ' . $code . ': ' . $result . ' ' . $words),
            'raw'     => $read['raw'],
        );
    }

    $log = array('Aras Kargo shipment ' . $code . ' registered' . $test . '.');
    $format = ($account['label_format'] === 'zpl') ? 'zpl' : 'pdf';
    $label = pg_label_aras_call('GetArasBarcode', pg_label_aras_login($account, 'Username', 'Password') + array('integrationCode' => $code), $account);
    $barcode = $label['ok'] ? pg_label_aras_barcode_read($label['result'], $format) : null;
    $number = '';

    // The tracking number, else the barcode the branch scans (a parcel row
    // takes 5-40 letters, digits, spaces and hyphens).
    foreach (($barcode === null) ? array() : array($barcode['tracking_number'], $barcode['barcode']) as $candidate) {
        if (($number === '') && (preg_match('/^[A-Za-z0-9][A-Za-z0-9 -]{4,39}$/', $candidate) === 1)) {
            $number = $candidate;
        }
    }

    // No number to give the parcel: the registration is taken back, so the
    // shipment can be made again rather than wait at Aras with no label.
    if (($barcode === null) || !$barcode['ok'] || ($number === '')) {
        $words = ($barcode === null) ? $label['message'] : $barcode['message'];
        $log[] = 'Aras Kargo gave no label for ' . $code . (($words !== '') ? ': ' . $words : '') . '.';
        $cancel = pg_label_aras_cancel($code, $account);
        $log = array_merge($log, isset($cancel['log']) ? $cancel['log'] : array());

        if (!empty($cancel['success'])) {
            return array(
                'success' => false,
                'error'   => lang('Aras Kargo gave no label for this shipment, so it was cancelled there. The account may not be set up for labels printed by the sender; ask your Aras branch.') . (($words !== '') ? ' (' . $words . ')' : ''),
                'log'     => $log,
                'raw'     => $label['raw'],
            );
        }

        return array(
            'success' => false,
            'unclear' => true,
            'error'   => lang(array('string' => 'Aras Kargo registered the shipment but gave no label, and it could not be cancelled. Look for integration code {var:1} on the Aras screen before trying again.', 'vars' => array($code))),
            'log'     => $log,
            'raw'     => $label['raw'],
        );
    }

    $answer = array(
        'success'         => true,
        'reference'       => $code,
        'tracking_number' => $number,
        'carrier'         => 'aras',
        'status'          => 'created',
        'barcode'         => $barcode['barcode'],
        'log'             => $log,
        'raw'             => $label['raw'],
    );

    // A label that cannot be read now is asked for again by label(): the
    // shipment and its number stand.
    if ($barcode['label'] !== '') {
        $answer['label'] = $barcode['label'];
        $answer['label_format'] = $format;
    } else {
        $answer['log'][] = 'The Aras Kargo label of ' . $code . ' could not be read as ' . strtoupper($format) . '.';
    }

    return $answer;
}

function pg_label_aras_label($reference, $format, $account)
{
    $code = pg_label_aras_code($reference);

    if (($format !== 'pdf') && ($format !== 'zpl')) {
        return array('success' => false, 'error' => lang('Aras Kargo gives labels as PDF or ZPL.'));
    }

    if ($code === '') {
        return array('success' => false, 'error' => lang('No shipment of the carrier label service is known for this parcel.'));
    }

    $read = pg_label_aras_call('GetArasBarcode', pg_label_aras_login($account, 'Username', 'Password') + array('integrationCode' => $code), $account);

    if (!$read['ok']) {
        return pg_label_aras_failure($read, false);
    }

    $barcode = pg_label_aras_barcode_read($read['result'], $format);

    if (!$barcode['ok'] || ($barcode['label'] === '')) {
        return array(
            'success' => false,
            'error'   => lang('Aras Kargo gave no label for this shipment.') . (($barcode['message'] !== '') ? ' (' . $barcode['message'] . ')' : ''),
            'raw'     => $read['raw'],
        );
    }

    return array('success' => true, 'reference' => $code, 'label' => $barcode['label'], 'label_format' => $format, 'barcode' => $barcode['barcode']);
}

function pg_label_aras_cancel($reference, $account)
{
    $code = pg_label_aras_code($reference);

    if ($code === '') {
        return array('success' => false, 'error' => lang('No shipment of the carrier label service is known for this parcel.'));
    }

    $read = pg_label_aras_call('CancelDispatch', pg_label_aras_login($account, 'userName', 'password') + array('integrationCode' => $code), $account);

    if (!$read['ok']) {
        return pg_label_aras_failure($read, true);
    }

    $result = pg_label_aras_value($read['result'], 'ResultCode');
    $words = substr(pg_label_aras_value($read['result'], 'ResultMessage'), 0, 300);

    if ($result === '') {
        return array(
            'success' => false,
            'unclear' => true,
            'error'   => lang('The carrier label service gave an answer that cannot be read. Check the shipment on the service\'s own screen before trying again.'),
            'log'     => array('Aras Kargo gave no result for the cancellation of ' . $code . '.'),
            'raw'     => $read['raw'],
        );
    }

    // Refused once a branch has made out the waybill.
    if ($result !== '0') {
        return array(
            'success' => false,
            'error'   => lang('Aras Kargo refused the request.') . ' (' . (($words !== '') ? $words : 'ResultCode ' . $result) . ')',
            'log'     => array('Aras Kargo refused to cancel ' . $code . ': ' . $result . ' ' . $words),
            'raw'     => $read['raw'],
        );
    }

    return array('success' => true, 'reference' => $code, 'log' => array('Aras Kargo shipment ' . $code . ' cancelled.'));
}

function pg_label_aras_track($reference, $account)
{
    $code = pg_label_aras_code($reference);

    if ($code === '') {
        return array('success' => false, 'error' => lang('No shipment of the carrier label service is known for this parcel.'));
    }

    if (pg_label_aras_field($account, 'customer_code') === '') {
        return array('success' => false, 'error' => lang('Aras Kargo needs the customer code to say where a parcel is; enter it in the Aras Kargo settings.'));
    }

    $read = pg_label_aras_call('GetCargoInfo', pg_label_aras_login($account, 'username', 'password') + array(
        'customerCode'    => pg_label_aras_field($account, 'customer_code'),
        'integrationCode' => $code,
    ), $account);

    if (!$read['ok']) {
        return pg_label_aras_failure($read, false);
    }

    $row = pg_label_aras_latest_row(pg_label_aras_rows($read['result']));
    $status = pg_label_aras_status($row);

    if ($status === '') {
        return array('success' => false, 'error' => lang('Aras Kargo gives no status for this shipment yet.'), 'raw' => $read['raw']);
    }

    // The row dates the delivery and the waybill (the parcel taken in); for
    // another status the tracking job takes the moment it learnt of it.
    $at = 0;

    if ($status === 'delivered') {
        $at = pg_label_aras_time(pg_label_aras_value($row, 'TESLIM_TARIHI'), pg_label_aras_value($row, 'TESLIM_SAATI'));
    } elseif ($status === 'in_transit') {
        $at = pg_label_aras_time(pg_label_aras_value($row, 'IRSALIYE_TARIH'));
    }

    $number = pg_label_aras_row_number($row);
    $carriers = pg_shipping_carriers();
    $words = trim(pg_label_aras_value($row, 'DURUMU') . ' ' . pg_label_aras_value($row, 'DURUM_KODU'));

    return array(
        'success'         => true,
        'reference'       => $code,
        'status'          => $status,
        'status_at'       => $at,
        'tracking_number' => $number,
        'carrier'         => 'aras',
        'tracking_url'    => ($number !== '') ? str_replace('{code}', rawurlencode($number), $carriers['aras']['url']) : '',
        'log'             => ($words !== '') ? array('Aras Kargo: ' . $words . '.') : array(),
    );
}
