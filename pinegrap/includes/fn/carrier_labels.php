<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Carrier labels - the contract every label driver keeps, its settings and
 * the one door to it.
 *
 * A label service (a shipping aggregator, a carrier's own API) is a driver:
 * a file under includes/shipping/<key>.php listed in pg_label_builtin_drivers(),
 * or a file an add-on registers with pg_label_driver_register(). The driver is
 * a set of functions with one prefix, pg_label_<key>_, the way the payment
 * and SMS layers work: what is not defined is not supported, and
 * pg_label_can() says so before anyone tries. Callers never call a driver
 * function by name; they go through pg_label_call(), which loads the driver,
 * catches what it throws and hands back an answer of one fixed shape.
 *
 *   pg_label_<key>_info()                      required: carriers (the
 *                                              pg_shipping_carriers() keys the
 *                                              service ships with) and formats
 *                                              (pdf, zpl, svg) of its labels
 *   pg_label_<key>_fields()                    the account fields the Shipping
 *                                              card asks for (the field shape of
 *                                              pg_payment_fields_normalize())
 *   pg_label_<key>_test($account)              sign in and read something
 *                                              harmless: the settings' test
 *   pg_label_<key>_create($shipment, $account) open a shipment: label, barcode,
 *                                              tracking number
 *   pg_label_<key>_label($reference, $format, $account)
 *                                              the label of a shipment again
 *   pg_label_<key>_cancel($reference, $account)
 *                                              cancel a shipment not yet picked up
 *   pg_label_<key>_track($reference, $account) where a shipment is now
 *   pg_label_<key>_pickup($request, $account)  ask the carrier to collect
 *
 * $account is the driver's settings (field => text, secrets included) read by
 * the caller (pg_label_account()); a driver never reads the database, the
 * request or the session, and never writes an order or a shipment row. The
 * caller records what it answers: tracking numbers only through
 * pg_shipment_record() (includes/fn/shipping.php), with the options
 * pg_label_shipment_options() builds, so a parcel row carries the same
 * columns whichever service opened it. $reference is the service's own id of
 * a shipment, an opaque text the caller keeps beside its label, never in
 * shipping_tracking_numbers.
 *
 * Every operation answers an array; pg_label_answer() fills what is missing,
 * drops what it does not know and refuses what cannot be trusted:
 *
 *   success          bool
 *   error            string  a sentence for the operator, '' on success
 *   unclear          bool    a failure that does not say whether the service
 *                            did it (no answer in time, an answer that cannot
 *                            be read): the caller must not open the same
 *                            shipment again as if nothing had happened
 *   reference        string  the service's id of the shipment
 *   tracking_number  string  the carrier's number, 5-40 characters
 *   carrier          string  a pg_shipping_carriers() key, '' when unknown
 *   tracking_url     string  the carrier's own page of the parcel (http/https)
 *   status           string  pg_shipment_statuses(), '' when not given
 *   status_at        int     unix time of that status, 0 when not given
 *   label            string  the label document itself (bytes)
 *   label_format     string  pdf, zpl or svg, '' without a label
 *   barcode          string  the text the label's barcode holds
 *   account          string  test(): the account's name at the service
 *   log              array   sentences for the activity log, in order
 *   raw              array   the service's answer, for the log only
 *
 * An answer never carries the account's secrets: pg_label_call() masks any
 * secret value it finds in the error, the log and the raw answer, so a
 * service that echoes the request back cannot put a key into a notice or the
 * log.
 *
 * The settings are kept in connect_apps under 'label_<key>', like the payment
 * drivers: secret fields sealed as one map with the site's key and never
 * drawn back, the other fields in options, the card's switch as enabled.
 * A driver added later needs no schema change.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The operations a driver may define, with the number of arguments each is
 * called with.
 *
 * @return array operation => argument count
 */
function pg_label_operations()
{
    return array(
        'test'   => 1,
        'create' => 2,
        'label'  => 3,
        'cancel' => 2,
        'track'  => 2,
        'pickup' => 2,
    );
}

/**
 * The label formats a driver may declare and answer with.
 *
 * @return string[]
 */
function pg_label_formats()
{
    return array('pdf', 'zpl', 'svg');
}

/**
 * Whether a driver key can be one: lowercase, a letter first, single
 * underscores, 2-32 characters (two underscores split key and field in the
 * card's input names).
 *
 * @param mixed $key
 * @return bool
 */
function pg_label_key_valid($key)
{
    return is_string($key)
        && (strlen($key) >= 2)
        && (strlen($key) <= 32)
        && (preg_match('/^[a-z][a-z0-9]*(_[a-z0-9]+)*$/', $key) === 1);
}

/**
 * @param string $key
 * @param string $operation
 * @return string
 */
function pg_label_function_name($key, $operation)
{
    return 'pg_label_' . $key . '_' . $operation;
}


/* ---------------------------------------------------------------------------
   Registry
   --------------------------------------------------------------------------- */

/**
 * The drivers shipped with the release, files under includes/shipping/.
 *
 * @return array key => label, file, portal
 */
function pg_label_builtin_drivers()
{
    return array(
        'geliver'    => array('label' => 'Geliver', 'file' => 'geliver.php', 'portal' => 'https://app.geliver.io/apitokens'),
        'basitkargo' => array('label' => 'Basit Kargo', 'file' => 'basitkargo.php', 'portal' => 'https://basitkargo.com/api'),
        'shippo'     => array('label' => 'Shippo', 'file' => 'shippo.php', 'portal' => 'https://portal.goshippo.com/api-config/api'),
        'sendcloud'  => array('label' => 'Sendcloud', 'file' => 'sendcloud.php', 'portal' => 'https://app.sendcloud.com/v2/settings/integrations/manage'),
        'aras'       => array('label' => 'Aras Kargo', 'file' => 'aras.php', 'portal' => 'https://esasweb.araskargo.com.tr'),
        'kargonomi'  => array('label' => 'Kargonomi', 'file' => 'kargonomi.php', 'portal' => 'https://app.kargonomi.com.tr'),
    );
}

/**
 * A definition checked and filled in, or null when it cannot be used.
 *
 * @param mixed  $key
 * @param mixed  $definition
 * @param string $directory where a relative file name is looked for; '' to
 *                          take absolute paths only (an add-on names its own)
 * @return array|null key, label, file, portal
 */
function pg_label_driver_definition_normalize($key, $definition, $directory)
{
    if (!pg_label_key_valid($key) || !is_array($definition)) {
        return null;
    }

    $text = function ($name) use ($definition) {
        return (isset($definition[$name]) && is_string($definition[$name])) ? trim($definition[$name]) : '';
    };

    $label = $text('label');
    $file = $text('file');
    $portal = $text('portal');

    if (($label === '') || !preg_match('/\.php$/', $file) || preg_match('#(^|[\\\\/])\.\.([\\\\/]|$)#', $file)) {
        return null;
    }

    $absolute = (preg_match('#^(/|[A-Za-z]:[\\\\/]|\\\\\\\\)#', $file) === 1);

    if (!$absolute) {
        if (($directory === '') || (strpbrk($file, '/\\') !== false)) {
            return null;
        }

        $file = rtrim($directory, '/\\') . '/' . $file;
    }

    if (($portal !== '') && (preg_match('#^https://[^\s/]+#i', $portal) !== 1)) {
        $portal = '';
    }

    return array(
        'key'    => $key,
        'label'  => $label,
        'file'   => $file,
        'portal' => $portal,
    );
}

/**
 * The drivers registered during this request (add-ons), key => definition.
 *
 * @return array
 */
function &pg_label_registered_drivers()
{
    static $registered = array();

    return $registered;
}

/**
 * Registers a driver that is not part of the release, its file named with
 * its full path. Refused when the key is taken: a shipped driver is never
 * replaced.
 *
 * @param string $key
 * @param array  $definition as in pg_label_builtin_drivers(), file absolute
 * @return bool
 */
function pg_label_driver_register($key, $definition)
{
    $normalized = pg_label_driver_definition_normalize($key, $definition, '');

    if (($normalized === null) || (pg_label_driver($key) !== null)) {
        return false;
    }

    $registered = &pg_label_registered_drivers();
    $registered[$normalized['key']] = $normalized;

    return true;
}

/**
 * Every driver, the shipped ones first.
 *
 * @return array key => pg_label_driver_definition_normalize()
 */
function pg_label_drivers()
{
    static $builtin = null;

    if ($builtin === null) {
        $builtin = array();

        foreach (pg_label_builtin_drivers() as $key => $definition) {
            $normalized = pg_label_driver_definition_normalize($key, $definition, PG_FUNCTIONS_DIR . '/includes/shipping');

            if ($normalized !== null) {
                $builtin[$key] = $normalized;
            }
        }
    }

    return $builtin + pg_label_registered_drivers();
}

/**
 * @param string $key
 * @return array|null
 */
function pg_label_driver($key)
{
    $drivers = pg_label_drivers();

    return (is_string($key) && isset($drivers[$key])) ? $drivers[$key] : null;
}

/**
 * Loads one driver's functions, once.
 *
 * @param string $key
 * @return bool false for a key no loadable driver answers to
 */
function pg_label_load($key)
{
    static $loaded = array();

    if (is_string($key) && isset($loaded[$key])) {
        return true;
    }

    $definition = pg_label_driver($key);

    if (($definition === null) || !is_file($definition['file'])) {
        return false;
    }

    require_once($definition['file']);

    if (!function_exists(pg_label_function_name($key, 'info'))) {
        return false;
    }

    $loaded[$key] = true;

    return true;
}

/**
 * What a driver's info() declared, checked: only carriers the store knows,
 * only known formats, each once.
 *
 * @param mixed $declared
 * @return array carriers, formats
 */
function pg_label_info_normalize($declared)
{
    $info = array('carriers' => array(), 'formats' => array());

    if (!is_array($declared)) {
        return $info;
    }

    $known = array_keys(pg_shipping_carriers());

    foreach (array('carriers' => $known, 'formats' => pg_label_formats()) as $name => $allowed) {
        if (isset($declared[$name]) && is_array($declared[$name])) {
            $info[$name] = array_values(array_intersect($allowed, array_filter($declared[$name], 'is_string')));
        }
    }

    return $info;
}

/**
 * A driver's info.
 *
 * @param string $key
 * @return array pg_label_info_normalize()
 */
function pg_label_info($key)
{
    if (!pg_label_load($key)) {
        return pg_label_info_normalize(null);
    }

    try {
        return pg_label_info_normalize(call_user_func(pg_label_function_name($key, 'info')));
    } catch (\Throwable $exception) {
        return pg_label_info_normalize(null);
    }
}

/**
 * Whether a driver defines an operation.
 *
 * @param string $key
 * @param string $operation pg_label_operations() key
 * @return bool
 */
function pg_label_can($key, $operation)
{
    return array_key_exists((string) $operation, pg_label_operations())
        && pg_label_load($key)
        && function_exists(pg_label_function_name($key, (string) $operation));
}


/* ---------------------------------------------------------------------------
   Answers
   --------------------------------------------------------------------------- */

/**
 * A failed answer.
 *
 * @param string $error
 * @param array  $raw
 * @param bool   $unclear
 * @return array
 */
function pg_label_failure($error, $raw = array(), $unclear = false)
{
    return pg_label_answer('', array(
        'success' => false,
        'unclear' => (bool) $unclear,
        'error'   => (string) $error,
        'raw'     => is_array($raw) ? $raw : array(),
    ));
}

/**
 * Whether a label document is of the format it is said to be: a PDF starts
 * with its header, a ZPL label holds a format start command, an SVG is one
 * pg_label_svg_valid() accepts.
 *
 * @param string $label
 * @param string $format
 * @return bool
 */
function pg_label_document_valid($label, $format)
{
    if (!is_string($label) || ($label === '')) {
        return false;
    }

    if ($format === 'pdf') {
        return strncmp($label, '%PDF-', 5) === 0;
    }

    if ($format === 'svg') {
        return pg_label_svg_valid($label);
    }

    return ($format === 'zpl') && (stripos($label, '^XA') !== false);
}

/**
 * Whether an SVG label can be kept and shown: an <svg> root after at most
 * an XML declaration and comments, and nothing that reaches outside the
 * document. A label is shapes and text; a reference to another address
 * (href, src, url(), @import), a document type (entities), script, event
 * attributes or embedded HTML are refused, so the file stays inert wherever
 * it is opened. An embedded PNG, JPEG or GIF (a logo) is allowed. Pure.
 *
 * @param string $svg
 * @return bool
 */
function pg_label_svg_valid($svg)
{
    if (!is_string($svg) || ($svg === '') || (strlen($svg) > 5242880)) {
        return false;
    }

    if (strncmp($svg, "\xEF\xBB\xBF", 3) === 0) {
        $svg = substr($svg, 3);
    }

    if ((preg_match('/^(?:\s*(?:<\?xml\s[^>]*\?>|<!--.*?-->))*\s*<svg[\s>]/si', $svg) !== 1)
        || (stripos($svg, '</svg>') === false)) {
        return false;
    }

    if (preg_match('/<!(?:DOCTYPE|ENTITY)|<\?xml-stylesheet|<(?:[\w-]+:)?(?:script|foreignObject|iframe|object|embed)[\s>\/]|@import|[\s"\'\/]on[a-z]+\s*=/i', $svg) === 1) {
        return false;
    }

    // Every address an attribute or a style names must point into the
    // document itself or be an embedded raster image.
    preg_match_all('/(?:^|[\s"\'])(?:[\w-]+:)?(?:href|src)\s*=\s*(["\'])(.*?)\1/si', $svg, $attributes);
    preg_match_all('/url\(\s*(["\']?)(.*?)\1\s*\)/si', $svg, $urls);

    foreach (array_merge($attributes[2], $urls[2]) as $address) {
        $address = trim(html_entity_decode($address, ENT_QUOTES | ENT_XML1, 'UTF-8'));

        if (($address !== '') && ($address[0] !== '#') && (preg_match('/^data:image\/(?:png|jpeg|gif);base64,/i', $address) !== 1)) {
            return false;
        }
    }

    return true;
}

/**
 * A driver's answer in the fixed shape (see the header of this file). Keys
 * the shape does not have are dropped, so nothing a service adds of its own
 * reaches a caller.
 *
 * A success the caller cannot act on becomes an unclear failure: create()
 * without the service's reference or a tracking number of a parcel's shape,
 * label() without a document of a known format, track() without a known
 * status. The service may have done it; the operator looks on its screen.
 *
 * @param string $operation pg_label_operations() key, '' for none
 * @param mixed  $answer    what the driver returned
 * @return array
 */
function pg_label_answer($operation, $answer)
{
    $shape = array(
        'success'         => false,
        'error'           => '',
        'unclear'         => false,
        'reference'       => '',
        'tracking_number' => '',
        'carrier'         => '',
        'tracking_url'    => '',
        'status'          => '',
        'status_at'       => 0,
        'label'           => '',
        'label_format'    => '',
        'barcode'         => '',
        'account'         => '',
        'log'             => array(),
        'raw'             => array(),
    );

    if (!is_array($answer)) {
        $shape['error'] = lang('The carrier label service gave no answer.');

        return $shape;
    }

    foreach (array('error', 'reference', 'tracking_number', 'status', 'barcode', 'account', 'label_format') as $text) {
        if (isset($answer[$text]) && is_scalar($answer[$text])) {
            $shape[$text] = trim((string) $answer[$text]);
        }
    }

    // The document is bytes: nothing is trimmed off it.
    if (isset($answer['label']) && is_string($answer['label'])) {
        $shape['label'] = $answer['label'];
    }

    if (isset($answer['raw']) && is_array($answer['raw'])) {
        $shape['raw'] = $answer['raw'];
    }

    if (isset($answer['log']) && is_array($answer['log'])) {
        $shape['log'] = array_values(array_map('strval', array_filter($answer['log'], 'is_scalar')));
    }

    if (isset($answer['status_at']) && is_int($answer['status_at']) && ($answer['status_at'] > 0)) {
        $shape['status_at'] = $answer['status_at'];
    }

    // A carrier the store knows by key or by name; any other stays unknown,
    // and the shipment row then reads it from the shipping method.
    if (isset($answer['carrier']) && is_string($answer['carrier']) && (trim($answer['carrier']) !== '')) {
        $carrier = trim($answer['carrier']);
        $carriers = pg_shipping_carriers();
        $shape['carrier'] = isset($carriers[strtolower($carrier)]) ? strtolower($carrier) : (string) pg_shipping_method_carrier($carrier);
    }

    if (isset($answer['tracking_url'])) {
        $shape['tracking_url'] = pg_shipment_tracking_url_clean($answer['tracking_url']);
    }

    if (!in_array($shape['status'], pg_shipment_statuses(), true)) {
        $shape['status'] = '';
        $shape['status_at'] = 0;
    }

    if (($shape['tracking_number'] !== '') && (preg_match('/^[A-Za-z0-9][A-Za-z0-9 -]{4,39}$/', $shape['tracking_number']) !== 1)) {
        $shape['tracking_number'] = '';
    }

    if ((strlen($shape['reference']) > 100) || (preg_match('/[\x00-\x1F\x7F]/', $shape['reference']) === 1)) {
        $shape['reference'] = '';
    }

    if (!in_array($shape['label_format'], pg_label_formats(), true) || !pg_label_document_valid($shape['label'], $shape['label_format'])) {
        $shape['label'] = '';
        $shape['label_format'] = '';
    }

    $shape['success'] = (isset($answer['success']) && ($answer['success'] === true));

    if (!$shape['success']) {
        $shape['unclear'] = (isset($answer['unclear']) && ($answer['unclear'] === true));

        if ($shape['error'] === '') {
            $shape['error'] = lang('The carrier label service refused the request.');
        }

        return $shape;
    }

    $shape['error'] = '';

    $unreadable = (($operation === 'create') && (($shape['reference'] === '') || ($shape['tracking_number'] === '')))
        || (($operation === 'label') && ($shape['label'] === ''))
        || (($operation === 'track') && ($shape['status'] === ''));

    if ($unreadable) {
        $shape['success'] = false;
        $shape['unclear'] = true;
        $shape['error'] = lang('The carrier label service gave an answer that cannot be read. Check the shipment on the service\'s own screen before trying again.');
    }

    return $shape;
}

/**
 * Text with every secret value of an account replaced by asterisks.
 *
 * @param mixed $value  a string, or an array of them (walked)
 * @param array $secrets the secret values, each at least four characters
 * @return mixed
 */
function pg_label_mask($value, $secrets)
{
    if (is_array($value)) {
        foreach ($value as $name => $item) {
            $value[$name] = pg_label_mask($item, $secrets);
        }

        return $value;
    }

    return is_string($value) ? str_replace($secrets, '****', $value) : $value;
}

/**
 * Calls one operation of a driver.
 *
 * @param string $key
 * @param string $operation pg_label_operations() key
 * @param array  $args      the operation's arguments, the account last
 * @return array pg_label_answer()
 */
function pg_label_call($key, $operation, $args = array())
{
    $operations = pg_label_operations();
    $operation = (string) $operation;

    if (!isset($operations[$operation]) || !pg_label_load($key)) {
        return pg_label_failure(lang('This carrier label service is not available.'));
    }

    $function = pg_label_function_name($key, $operation);

    if (!function_exists($function)) {
        return pg_label_failure(lang('This carrier label service cannot do that.'));
    }

    $args = is_array($args) ? array_values($args) : array();
    $args = array_pad(array_slice($args, 0, $operations[$operation]), $operations[$operation], null);

    // The secrets of the account handed in: masked in whatever comes back.
    $secrets = array();
    $account = $args ? $args[count($args) - 1] : null;

    foreach (pg_label_fields($key) as $field) {
        if (($field['type'] === 'secret') && is_array($account) && isset($account[$field['name']])
            && is_string($account[$field['name']]) && (strlen($account[$field['name']]) >= 4)) {
            $secrets[] = $account[$field['name']];
        }
    }

    try {
        $answer = pg_label_answer($operation, call_user_func_array($function, $args));
    } catch (\Throwable $exception) {
        // An \Error stops the driver before anything is sent; an exception
        // from its network layer does not say whether the request went out.
        $answer = pg_label_failure(
            lang('The carrier label service could not be reached.'),
            array('exception' => get_class($exception), 'message' => $exception->getMessage()),
            !($exception instanceof \Error));
    }

    if ($secrets) {
        foreach (array('error', 'log', 'raw') as $name) {
            $answer[$name] = pg_label_mask($answer[$name], $secrets);
        }
    }

    return $answer;
}

/**
 * The options pg_shipment_record() takes for a parcel a label service opened.
 * Only the columns every parcel row has: the service's reference, label and
 * barcode stay with the label, so the row reads the same whichever service
 * wrote it.
 *
 * @param array $answer pg_label_answer() of create()
 * @return array source, and carrier and tracking_url when the service gave them
 */
function pg_label_shipment_options($answer)
{
    $options = array('source' => 'carrier');

    if (!empty($answer['carrier'])) {
        $options['carrier'] = (string) $answer['carrier'];
    }

    if (!empty($answer['tracking_url'])) {
        $options['tracking_url'] = (string) $answer['tracking_url'];
    }

    return $options;
}


/* ---------------------------------------------------------------------------
   Settings
   --------------------------------------------------------------------------- */

/**
 * The connect_apps key a driver's settings are kept under.
 *
 * @param string $key
 * @return string
 */
function pg_label_settings_key($key)
{
    return 'label_' . $key;
}

/**
 * The name a driver's input is posted under.
 *
 * @param string $key
 * @param string $field a field name, or 'present' / 'enabled' / 'row'
 * @return string
 */
function pg_label_input_name($key, $field)
{
    return 'label_' . $key . '__' . $field;
}

/**
 * A driver's account fields: the shape the payment drivers declare
 * (pg_payment_fields_normalize()), switches left out.
 *
 * @param string $key
 * @return array
 */
function pg_label_fields($key)
{
    $function = pg_label_function_name((string) $key, 'fields');

    if (!pg_label_load($key) || !function_exists($function)) {
        return array();
    }

    try {
        $fields = pg_payment_fields_normalize(call_user_func($function));
    } catch (\Throwable $exception) {
        return array();
    }

    return array_values(array_filter($fields, function ($field) {
        return $field['type'] !== 'switch';
    }));
}

/**
 * A driver's stored settings.
 *
 * @param string $key
 * @return array values (field => text), secrets (field => text; only what
 *               this site's key can read), secret_stored, readable, enabled,
 *               exists
 */
function pg_label_settings($key)
{
    $row = pg_connect_schema_ready()
        ? db_item("SELECT secret, options, enabled FROM connect_apps WHERE provider = '" . e(pg_label_settings_key($key)) . "'")
        : null;

    $settings = array(
        'values'        => array(),
        'secrets'       => array(),
        'secret_stored' => false,
        'readable'      => true,
        'enabled'       => false,
        'exists'        => is_array($row),
    );

    if (is_array($row)) {
        $options = json_decode((string) $row['options'], true);
        $secrets = pg_connect_unseal($row['secret']);

        $settings['values'] = is_array($options) ? array_map('strval', array_filter($options, 'is_scalar')) : array();
        $settings['secrets'] = array_map('strval', array_filter($secrets, 'is_scalar'));
        $settings['secret_stored'] = ((string) $row['secret'] !== '');
        $settings['readable'] = !$settings['secret_stored'] || ($settings['secrets'] !== array());
        $settings['enabled'] = ((int) $row['enabled'] === 1);
    }

    return $settings;
}

/**
 * The account a driver's operations are called with: every field's stored
 * value (secrets unsealed), its default when none is stored.
 *
 * @param array $fields   pg_label_fields()
 * @param array $settings pg_label_settings()
 * @return array field => text
 */
function pg_label_account($fields, $settings)
{
    $account = array();

    foreach ($fields as $field) {
        $name = $field['name'];

        if ($field['type'] === 'secret') {
            $account[$name] = isset($settings['secrets'][$name]) ? $settings['secrets'][$name] : '';
        } else {
            $account[$name] = isset($settings['values'][$name]) ? $settings['values'][$name] : $field['default'];
        }
    }

    return $account;
}

/**
 * Whether a driver is switched on with every required field stored and
 * readable: it may open shipments.
 *
 * @param string $key
 * @return bool
 */
function pg_label_ready($key)
{
    if (pg_label_driver($key) === null) {
        return false;
    }

    $settings = pg_label_settings($key);

    if (!$settings['enabled'] || !$settings['readable']) {
        return false;
    }

    $fields = pg_label_fields($key);
    $account = pg_label_account($fields, $settings);

    foreach ($fields as $field) {
        if ($field['required'] && ($account[$field['name']] === '')) {
            return false;
        }
    }

    return true;
}

/**
 * What a post of the Shipping card asks for one driver, decided from the
 * post and the stored settings alone: no database, no service.
 *
 * Only a driver whose fields were on the posted form is decided. An empty
 * secret keeps the stored one; a value outside a select's options keeps the
 * stored one (or the default); a required field is required only while the
 * driver is switched on. Only a change is written, and a driver never set
 * up gets no row while it is off with every value at its default.
 *
 * @param string $key
 * @param array  $fields pg_label_fields()
 * @param array  $post
 * @param array  $stored pg_label_settings()
 * @return array posted, key, values, secrets (only the newly typed ones),
 *               enabled, changed, test (its connection test was asked for),
 *               marks (input name => sentence)
 */
function pg_label_settings_decide($key, $fields, $post, $stored)
{
    if (!is_array($post) || !isset($post[pg_label_input_name($key, 'present')])) {
        return array('posted' => false);
    }

    $posted = function ($name) use ($post, $key) {
        $input = pg_label_input_name($key, $name);

        return (isset($post[$input]) && is_string($post[$input])) ? $post[$input] : '';
    };

    $enabled = ($posted('enabled') === '1');
    $values = array();
    $secrets = array();
    $marks = array();

    foreach ($fields as $field) {
        $name = $field['name'];
        $input = pg_label_input_name($key, $name);
        $raw = $posted($name);
        $label = h($field['label']);

        if ($field['type'] === 'secret') {
            $typed = trim($raw);

            if ($typed !== '') {
                if (mb_strlen($typed) > $field['max_length']) {
                    $marks[$input] = lang(array('string' => '{var:1} is too long.', 'vars' => array($label)));
                }

                if (!isset($stored['secrets'][$name]) || ($stored['secrets'][$name] !== $typed)) {
                    $secrets[$name] = $typed;
                }
            } elseif ($field['required'] && $enabled && !isset($stored['secrets'][$name])) {
                $marks[$input] = lang(array('string' => '{var:1} is required.', 'vars' => array($label)));
            }
        } elseif ($field['type'] === 'select') {
            if (array_key_exists($raw, $field['options'])) {
                $values[$name] = $raw;
            } elseif (isset($stored['values'][$name]) && array_key_exists($stored['values'][$name], $field['options'])) {
                $values[$name] = $stored['values'][$name];
            } else {
                $values[$name] = $field['default'];
            }
        } else {
            $value = trim($raw);

            if (mb_strlen($value) > $field['max_length']) {
                $marks[$input] = lang(array('string' => '{var:1} is too long.', 'vars' => array($label)));
            } elseif ($field['required'] && $enabled && ($value === '')) {
                $marks[$input] = lang(array('string' => '{var:1} is required.', 'vars' => array($label)));
            }

            $values[$name] = $value;
        }
    }

    if (!empty($stored['exists'])) {
        $compare = isset($stored['values']) ? $stored['values'] : array();
        $changed = ($enabled !== !empty($stored['enabled']));
    } else {
        $compare = array();

        foreach ($fields as $field) {
            $compare[$field['name']] = $field['default'];
        }

        $changed = $enabled;
    }

    foreach ($values as $name => $value) {
        if (!isset($compare[$name]) || ($compare[$name] !== $value)) {
            $changed = true;
        }
    }

    if ($secrets !== array()) {
        $changed = true;
    }

    return array(
        'posted'  => true,
        'key'     => $key,
        'values'  => $values,
        'secrets' => $secrets,
        'enabled' => $enabled,
        'changed' => $changed,
        'test'    => (isset($post['label_test']) && ($post['label_test'] === $key)),
        'marks'   => $marks,
    );
}

/**
 * Checks the label drivers' fields of a post of the Shipping card and marks
 * what is refused, before anything on the card is written.
 *
 * @param array    $post     $_POST
 * @param liveform $liveform
 * @return array key => pg_label_settings_decide(), for pg_label_settings_save()
 */
function pg_label_settings_check($post, $liveform)
{
    $decisions = array();

    foreach (pg_label_drivers() as $key => $definition) {
        $fields = pg_label_fields($key);
        $decision = pg_label_settings_decide($key, $fields, $post, pg_label_settings($key));

        if (!$decision['posted']) {
            continue;
        }

        foreach ($decision['marks'] as $input => $sentence) {
            $liveform->mark_error($input, $sentence);
        }

        if ($decision['changed'] && !pg_connect_schema_ready()) {
            $liveform->mark_error(pg_label_input_name($key, 'enabled'), lang('The database has not been upgraded yet. Run the software update first.'));
        } elseif (($decision['secrets'] !== array()) && !pg_connect_can_seal()) {
            $liveform->mark_error(pg_label_input_name($key, (string) key($decision['secrets'])), lang('This site has no encryption key, so it cannot keep the keys of a carrier label service.'));
        }

        $decisions[$key] = $decision;
    }

    return $decisions;
}

/**
 * Stores a driver's settings. The secrets given are added to the stored
 * ones; a stored secret is never cleared here.
 *
 * @param string $key
 * @param array  $values  field => text
 * @param array  $secrets field => text, only the newly typed ones
 * @param bool   $enabled
 * @param int    $user_id
 * @return bool false when they cannot be kept here
 */
function pg_label_settings_store($key, $values, $secrets, $enabled, $user_id)
{
    if (!pg_connect_schema_ready() || (pg_label_driver($key) === null) || ($secrets && !pg_connect_can_seal())) {
        return false;
    }

    $provider = pg_label_settings_key($key);
    $existing = db_item("SELECT secret FROM connect_apps WHERE provider = '" . e($provider) . "'");
    $sealed = is_array($existing) ? (string) $existing['secret'] : '';

    if ($secrets) {
        $sealed = pg_connect_seal(array_merge(pg_connect_unseal($sealed), $secrets));
    }

    $columns = "client_id = '',
        secret = '" . e($sealed) . "',
        options = '" . e(json_encode((object) $values)) . "',
        enabled = '" . ($enabled ? 1 : 0) . "',
        updated_user_id = '" . (int) $user_id . "',
        updated_timestamp = UNIX_TIMESTAMP()";

    if (is_array($existing)) {
        db("UPDATE connect_apps SET " . $columns . " WHERE provider = '" . e($provider) . "'");
    } else {
        db("INSERT INTO connect_apps SET provider = '" . e($provider) . "', " . $columns);
    }

    return true;
}

/**
 * Writes what pg_label_settings_check() decided, then runs the connection
 * test that was asked for, with the settings as now stored, and registers or
 * removes the service's status webhook (includes/fn/carrier_webhook.php).
 * A service that
 * is on and can say where its parcels are puts the hourly shipment tracking
 * job in the general job's list (includes/fn/shipment_tracking.php).
 *
 * @param array    $decisions pg_label_settings_check()
 * @param int      $user_id
 * @param liveform $liveform
 */
function pg_label_settings_save($decisions, $user_id, $liveform)
{
    $tracking = false;

    foreach ($decisions as $key => $decision) {
        if (empty($decision['posted'])) {
            continue;
        }

        if ($decision['changed']) {
            pg_label_settings_store($key, $decision['values'], $decision['secrets'], $decision['enabled'], $user_id);
        }

        if ($decision['test']) {
            pg_label_settings_test($key, $liveform);
        }

        // The service's status webhook, for a driver that has one.
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/carrier_webhook.php');
        pg_carrier_webhook_sync($key, $decision, $liveform);

        $tracking = $tracking || ($decision['enabled'] && pg_label_can($key, 'track'));
    }

    if ($tracking) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/shipment_tracking.php');
        pg_shipment_tracking_job_switch_on();
    }
}

/**
 * The settings' connection test of one driver.
 *
 * @param string   $key
 * @param liveform $liveform
 */
function pg_label_settings_test($key, $liveform)
{
    $definition = pg_label_driver($key);

    if ($definition === null) {
        return;
    }

    $fields = pg_label_fields($key);
    $answer = pg_label_call($key, 'test', array(pg_label_account($fields, pg_label_settings($key))));

    if ($answer['success']) {
        $liveform->add_notice(lang(array('string' => '{var:1} accepted the credentials.', 'vars' => array(h($definition['label'])))));
    } else {
        $liveform->add_notice(lang(array('string' => '{var:1} refused the connection: {var:2}', 'vars' => array(h($definition['label']), h($answer['error'])))));
    }
}

/**
 * The label drivers' rows of the Shipping card: per driver a switch, its
 * account fields (secrets never drawn back) and a connection test. Nothing
 * when no driver is installed.
 *
 * @return string
 */
function pg_label_settings_rows()
{
    $drivers = pg_label_drivers();

    if (!$drivers) {
        return '';
    }

    $html = '
                                                                        <div class="col-12"><h6 class="fg-secondary mb-0">' . lang('Carrier Labels') . '</h6>
                                                                            <div class="form-text">' . lang('Shipments, labels and tracking numbers created through the account of a shipping service.') . '</div>
                                                                        </div>';

    foreach ($drivers as $key => $definition) {
        $fields = pg_label_fields($key);
        $settings = pg_label_settings($key);
        $row = pg_label_input_name($key, 'row');
        $enabled_id = pg_label_input_name($key, 'enabled');
        $info = pg_label_info($key);

        $carriers = array();

        foreach ($info['carriers'] as $carrier) {
            $carriers[] = pg_shipping_carriers()[$carrier]['name'];
        }

        $html .= '
                                                                        <div class="col-12">
                                                                            <div class="form-field">
                                                                                <div class="switch"><input value="1"' . ($settings['enabled'] ? ' checked="checked"' : '') . ' class="collapse-switcher" type="checkbox" id="' . h($enabled_id) . '" name="' . h($enabled_id) . '" data-bs-target="#' . h($row) . '"/></div>
                                                                                <label class="" for="' . h($enabled_id) . '">' . h($definition['label']) . ($carriers ? '<br/><span class="form-text">' . h(implode(', ', $carriers)) . '</span>' : '') . '</label>
                                                                            </div>
                                                                            <div class="collapse popover fade bs-popover-bottom p-0" id="' . h($row) . '">
                                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                                <div class="popover-body">
                                                                                   <div class="row gy-5">
                                                                                        <input type="hidden" name="' . h(pg_label_input_name($key, 'present')) . '" value="1"/>';

        if (!pg_connect_can_seal()) {
            $html .= '
                                                                                        <div class="col-12"><div class="alert theme-warning mb-0">' . lang('This site has no encryption key, so it cannot keep the keys of a carrier label service.') . '</div></div>';
        } elseif (!$settings['readable']) {
            $html .= '
                                                                                        <div class="col-12"><div class="alert theme-warning mb-0">' . lang(array(
                                                                                            'string' => 'The stored keys of {var:1} cannot be read with this site\'s encryption key. Enter them again.',
                                                                                            'vars'   => array(h($definition['label'])))) . '</div></div>';
        }

        if ($definition['portal'] !== '') {
            $html .= '
                                                                                        <div class="col-12 form-text">' . lang(array(
                                                                                            'string' => 'The keys are in your {var:1} account.',
                                                                                            'vars'   => array('<a href="' . h($definition['portal']) . '" target="_blank" rel="noopener">' . h($definition['label']) . '</a>'))) . '</div>';
        }

        foreach ($fields as $field) {
            $id = pg_label_input_name($key, $field['name']);
            $value = isset($settings['values'][$field['name']]) ? $settings['values'][$field['name']] : $field['default'];
            $help = ($field['help'] !== '') ? '
                                                                                            <div class="form-text">' . h($field['help']) . '</div>' : '';

            if ($field['type'] === 'select') {
                $options = '';

                foreach ($field['options'] as $option => $option_label) {
                    $options .= '<option value="' . h($option) . '"' . (((string) $option === $value) ? ' selected="selected"' : '') . '>' . h($option_label) . '</option>';
                }

                $input = '<select class="form-control" id="' . h($id) . '" name="' . h($id) . '">' . $options . '</select>';
            } elseif ($field['type'] === 'secret') {
                $input = '<input class="form-control" type="password" id="' . h($id) . '" name="' . h($id) . '" value="" maxlength="' . (int) $field['max_length'] . '" autocomplete="new-password"'
                    . (isset($settings['secrets'][$field['name']]) ? ' placeholder="' . h(lang('Stored. Leave empty to keep it.')) . '"' : '') . '/>';
            } else {
                $input = '<input class="form-control" type="text" id="' . h($id) . '" name="' . h($id) . '" value="' . h($value) . '" maxlength="' . (int) $field['max_length'] . '" autocomplete="off"/>';
            }

            $html .= '
                                                                                        <div class="pg-f-md">
                                                                                            <label class="form-label" for="' . h($id) . '">' . h($field['label']) . '</label>
                                                                                            ' . $input . $help . '
                                                                                        </div>';
        }

        require_once(PG_FUNCTIONS_DIR . '/includes/fn/carrier_webhook.php');
        $html .= pg_carrier_webhook_card_row($key, $settings);

        if (pg_label_can($key, 'test')) {
            $html .= '
                                                                                        <div class="col-12">
                                                                                            <button type="submit" name="label_test" value="' . h($key) . '" class="btn btn-sm btn-outline theme-secondary"><i class="bi bi-plug me-1" aria-hidden="true"></i>' . lang('Save and test the connection') . '</button>
                                                                                        </div>';
        }

        $html .= '
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>';
    }

    return $html;
}
