<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Payments - which payment drivers exist, and their settings.
 *
 * The contract a driver keeps and the door every caller goes through are in
 * includes/payments/driver.php, loaded with this module. This module knows
 * the drivers: the ones the release ships (pg_payment_builtin_drivers()) and
 * the ones an add-on registers (pg_payment_driver_register()), and it keeps
 * their settings.
 *
 * A driver is one of two kinds, as the payment methods have always been:
 *
 *   card      takes over a value of the Payment Gateway list of the
 *             Credit/Debit Card method (config.ecommerce_payment_gateway). One
 *             is active at a time: the one the list names.
 *   redirect  a payment method of its own beside the card (as PayPal Express
 *             Checkout is), with its own switch and its own orders.payment_method
 *             value.
 *
 * The gateways that are not driver-based (pg_payment_legacy_gateways()) stay
 * in submit_order.php as they are. A card driver whose gateway value is one
 * of them takes that value over; a new value needs a migration step that adds
 * it to the config.ecommerce_payment_gateway list, and a redirect driver's
 * method one that adds it to orders.payment_method, before the driver ships.
 *
 * A driver's settings are kept in connect_apps under 'payment_<key>', as the
 * carriers' and SMS providers' accounts are: the values it declares as secret
 * sealed with the site's key and never drawn back into a form, the others
 * among the options, the switch of a redirect driver as enabled. A driver
 * added later needs no schema change for them. A driver that declares no
 * fields keeps its settings where they already are; a shipped driver that
 * took over a legacy payment says so with 'settings' => 'config', and the
 * card draws nothing for it, not even a redirect driver's switch, because
 * the card's own rows for that service are its settings.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/payments/driver.php');

/**
 * The Payment Gateway values the card method takes that no driver serves:
 * their code stays in submit_order.php, untouched.
 *
 * @return array
 */
function pg_payment_legacy_gateways()
{
    return array(
        'Authorize.Net',
        'ClearCommerce',
        'First Data Global Gateway',
        'PayPal Payflow Pro',
        'PayPal Payments Pro',
        'Sage',
        'Stripe',
        'Iyzipay',
    );
}

/**
 * The drivers this release ships, key => definition. A file under
 * includes/payments/ and a line here add one; the key is what the settings
 * are kept under, so it never changes once a store has used the driver.
 *
 *   'example' => array(
 *       'label'   => 'Example Pay',            // as the service writes its name
 *       'kind'    => 'card',                   // or 'redirect'
 *       'gateway' => 'Example Pay',            // card: the Payment Gateway value
 *       'method'  => 'Pay With Example',       // redirect: the orders.payment_method value
 *       'file'    => 'example.php',            // under includes/payments/
 *       'portal'  => 'https://example.com/',   // where the operator finds the keys
 *       'log_name' => 'ExamplePay',            // how the activity log names the
 *                                              // service (default: the label)
 *       'settings' => 'config',                // shipped drivers only: keeps the
 *                                              // config columns and switch the card
 *                                              // already draws (default 'connect')
 *       'csp'     => array(                    // what the checkout page loads from
 *           'script-src'  => array('https://js.example.com'),   // the service while the
 *           'frame-src'   => array('https://js.example.com'),   // driver takes payments
 *           'connect-src' => array('https://api.example.com'),  // (waf_csp_policy())
 *       ),
 *   ),
 *
 * @return array
 */
function pg_payment_builtin_drivers()
{
    return array(
        'iyzico' => array(
            'label'    => 'iyzico',
            'log_name' => 'Iyzipay',
            'kind'     => 'card',
            'gateway'  => 'Iyzipay',
            'file'     => 'iyzico.php',
            'settings' => 'config',
        ),
        'pay_with_iyzico' => array(
            'label'    => 'Pay with iyzico',
            'log_name' => 'Iyzipay',
            'kind'     => 'redirect',
            'method'   => 'Pay With Iyzico',
            'file'     => 'iyzico.php',
            'settings' => 'config',
        ),
        'stripe' => array(
            'label'   => 'Stripe',
            'kind'    => 'card',
            'gateway' => 'Stripe PaymentIntents',
            'file'    => 'stripe.php',
            'portal'  => 'https://dashboard.stripe.com/apikeys',
            'csp'     => array(
                'script-src'  => array('https://js.stripe.com'),
                'frame-src'   => array('https://js.stripe.com', 'https://hooks.stripe.com'),
                'connect-src' => array('https://api.stripe.com'),
            ),
        ),
    );
}

/**
 * A definition checked and filled in, or null when it cannot be used.
 *
 * @param mixed  $key
 * @param mixed  $definition
 * @param string $directory where a relative file name is looked for; '' to
 *                          take absolute paths only (an add-on names its own)
 * @return array|null key, label, log_name, kind, gateway, method, file, portal,
 *                    settings, csp (directive => hosts)
 */
function pg_payment_driver_definition_normalize($key, $definition, $directory)
{
    if (!pg_payment_key_valid($key) || !is_array($definition)) {
        return null;
    }

    $text = function ($name) use ($definition) {
        return (isset($definition[$name]) && is_string($definition[$name])) ? trim($definition[$name]) : '';
    };

    $label = $text('label');
    $log_name = $text('log_name');
    $kind = $text('kind');
    $gateway = $text('gateway');
    $method = $text('method');
    $file = $text('file');
    $portal = $text('portal');

    if (($label === '') || !in_array($kind, array('card', 'redirect'), true)) {
        return null;
    }

    // A card driver answers to a gateway value, a redirect driver to a
    // payment method value; both are stored in ENUM columns of 50 or fewer
    // characters.
    if ($kind === 'card') {
        $method = 'Credit/Debit Card';

        if (($gateway === '') || (strlen($gateway) > 50)) {
            return null;
        }
    } else {
        $gateway = '';

        if (($method === '') || (strlen($method) > 50) || in_array($method, array('Credit/Debit Card', 'PayPal Express Checkout', 'Offline Payment'), true)) {
            return null;
        }
    }

    if (!preg_match('/\.php$/', $file) || preg_match('#(^|[\\\\/])\.\.([\\\\/]|$)#', $file)) {
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

    // Only a shipped driver keeps settings outside connect_apps: an add-on has
    // no config column of its own.
    $settings = (($text('settings') === 'config') && ($directory !== '')) ? 'config' : 'connect';

    // Only the directives a payment form needs, and only plain https origins:
    // a value here goes into the site's security header as it is.
    $csp = array();

    if (isset($definition['csp']) && is_array($definition['csp'])) {
        foreach (array('script-src', 'frame-src', 'connect-src') as $directive) {
            $hosts = (isset($definition['csp'][$directive]) && is_array($definition['csp'][$directive])) ? $definition['csp'][$directive] : array();

            foreach ($hosts as $host) {
                if (is_string($host) && (preg_match('#^https://(\*\.)?[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$#', $host) === 1)) {
                    $csp[$directive][] = $host;
                }
            }
        }
    }

    return array(
        'key'      => $key,
        'label'    => $label,
        'log_name' => ($log_name !== '') ? $log_name : $label,
        'kind'     => $kind,
        'gateway'  => $gateway,
        'method'   => $method,
        'file'     => $file,
        'portal'   => $portal,
        'settings' => $settings,
        'csp'      => $csp,
    );
}

/**
 * The drivers registered during this request (add-ons), key => definition.
 *
 * @return array
 */
function &pg_payment_registered_drivers()
{
    static $registered = array();

    return $registered;
}

/**
 * Registers a driver that is not part of the release. The file is named
 * with its full path. Refused when the key, the gateway value or the method
 * value is already taken - a shipped driver is never replaced.
 *
 * @param string $key
 * @param array  $definition as in pg_payment_builtin_drivers(), file absolute
 * @return bool
 */
function pg_payment_driver_register($key, $definition)
{
    $normalized = pg_payment_driver_definition_normalize($key, $definition, '');

    if ($normalized === null) {
        return false;
    }

    foreach (pg_payment_drivers() as $existing) {
        if (($existing['key'] === $normalized['key'])
            || (($normalized['kind'] === 'card') && ($existing['gateway'] === $normalized['gateway']))
            || (($normalized['kind'] === 'redirect') && ($existing['method'] === $normalized['method']))) {
            return false;
        }
    }

    $registered = &pg_payment_registered_drivers();
    $registered[$normalized['key']] = $normalized;

    return true;
}

/**
 * Every driver, the shipped ones first.
 *
 * @return array key => pg_payment_driver_definition_normalize()
 */
function pg_payment_drivers()
{
    static $builtin = null;

    if ($builtin === null) {
        $builtin = array();

        foreach (pg_payment_builtin_drivers() as $key => $definition) {
            $normalized = pg_payment_driver_definition_normalize($key, $definition, PG_FUNCTIONS_DIR . '/includes/payments');

            if ($normalized !== null) {
                $builtin[$key] = $normalized;
            }
        }
    }

    return $builtin + pg_payment_registered_drivers();
}

/**
 * @param string $key
 * @return array|null
 */
function pg_payment_driver($key)
{
    $drivers = pg_payment_drivers();

    return (is_string($key) && isset($drivers[$key])) ? $drivers[$key] : null;
}

/**
 * The card driver that serves a Payment Gateway value.
 *
 * @param string $gateway config.ecommerce_payment_gateway
 * @return string driver key, '' when the gateway is not driver-based
 */
function pg_payment_driver_for_gateway($gateway)
{
    foreach (pg_payment_drivers() as $key => $definition) {
        if (($definition['kind'] === 'card') && ($definition['gateway'] === (string) $gateway)) {
            return $key;
        }
    }

    return '';
}

/**
 * The redirect driver that serves a payment method value.
 *
 * @param string $method orders.payment_method
 * @return string driver key, '' when no driver serves it
 */
function pg_payment_driver_for_method($method)
{
    foreach (pg_payment_drivers() as $key => $definition) {
        if (($definition['kind'] === 'redirect') && ($definition['method'] === (string) $method)) {
            return $key;
        }
    }

    return '';
}

/**
 * Loads one driver's functions, once.
 *
 * @param string $key
 * @return bool false for a key no loadable driver answers to
 */
function pg_payment_load($key)
{
    static $loaded = array();

    if (is_string($key) && isset($loaded[$key])) {
        return true;
    }

    $definition = pg_payment_driver($key);

    if (($definition === null) || !is_file($definition['file'])) {
        return false;
    }

    require_once($definition['file']);

    if (!function_exists(pg_payment_function_name($key, 'capabilities'))) {
        return false;
    }

    $loaded[$key] = true;

    return true;
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
function pg_payment_settings_key($key)
{
    return 'payment_' . $key;
}

/**
 * The name a driver's field is posted under. Neither a key nor a field name
 * holds two underscores in a row, so the double one keeps every pair apart.
 *
 * @param string $key
 * @param string $field a field name, or 'present' / 'enabled' / 'row'
 * @return string
 */
function pg_payment_input_name($key, $field)
{
    return 'payment_' . $key . '__' . $field;
}

/**
 * The settings a driver declares, checked: each field once, of a known type,
 * with what its type needs. A field that cannot be drawn is left out rather
 * than drawn wrong.
 *
 * A field: name (lowercase, a letter first), type (text, secret, select,
 * switch), label (already translated), help, required (when the driver is
 * the active one), options (select: value => label), default, max_length
 * (text and secret, 1-1000, 255 when not given).
 *
 * @param mixed $fields what pg_payment_<key>_fields() returned
 * @return array list
 */
function pg_payment_fields_normalize($fields)
{
    if (!is_array($fields)) {
        return array();
    }

    $normalized = array();
    // Taken by the card's own inputs (pg_payment_input_name()).
    $names = array('present' => true, 'enabled' => true, 'row' => true);

    foreach ($fields as $field) {
        if (!is_array($field) || !isset($field['name']) || !is_string($field['name'])) {
            continue;
        }

        $name = $field['name'];

        if ((preg_match('/^[a-z][a-z0-9]*(_[a-z0-9]+)*$/', $name) !== 1) || (strlen($name) > 40) || isset($names[$name])) {
            continue;
        }

        $type = (isset($field['type']) && is_string($field['type'])) ? $field['type'] : 'text';
        $label = (isset($field['label']) && is_string($field['label'])) ? trim($field['label']) : '';

        if (!in_array($type, array('text', 'secret', 'select', 'switch'), true) || ($label === '')) {
            continue;
        }

        $default = (isset($field['default']) && is_scalar($field['default'])) ? (string) $field['default'] : '';
        $options = array();

        if ($type === 'select') {
            if (isset($field['options']) && is_array($field['options'])) {
                foreach ($field['options'] as $value => $option_label) {
                    if (is_scalar($option_label) && ((string) $option_label !== '')) {
                        $options[(string) $value] = (string) $option_label;
                    }
                }
            }

            if (!$options) {
                continue;
            }

            if (!array_key_exists($default, $options)) {
                $default = (string) key($options);
            }
        } elseif ($type === 'switch') {
            $default = ($default === '1') ? '1' : '';
        }

        $max_length = (isset($field['max_length']) && is_int($field['max_length'])) ? $field['max_length'] : 255;

        $normalized[] = array(
            'name'       => $name,
            'type'       => $type,
            'label'      => $label,
            'help'       => (isset($field['help']) && is_string($field['help'])) ? trim($field['help']) : '',
            'required'   => (isset($field['required']) && ($field['required'] === true)) && ($type !== 'switch'),
            'options'    => $options,
            'default'    => ($type === 'secret') ? '' : $default,
            'max_length' => min(1000, max(1, $max_length)),
        );

        $names[$name] = true;
    }

    return $normalized;
}

/**
 * A driver's fields.
 *
 * @param string $key
 * @return array pg_payment_fields_normalize()
 */
function pg_payment_fields($key)
{
    $function = pg_payment_function_name((string) $key, 'fields');

    if (!pg_payment_load($key) || !function_exists($function)) {
        return array();
    }

    try {
        return pg_payment_fields_normalize(call_user_func($function));
    } catch (\Throwable $exception) {
        return array();
    }
}

/**
 * A driver's stored settings, read once per request.
 *
 * @param string $key
 * @param bool   $fresh read again (after a save)
 * @return array values (field => text), secrets (field => text; only what
 *               this site's key can read), secret_stored, readable (false
 *               when secrets are stored and the site's key cannot read them),
 *               enabled, exists
 */
function pg_payment_settings($key, $fresh = false)
{
    static $cache = array();

    if (!$fresh && isset($cache[$key])) {
        return $cache[$key];
    }

    $row = pg_connect_schema_ready()
        ? db_item("SELECT secret, options, enabled FROM connect_apps WHERE provider = '" . e(pg_payment_settings_key($key)) . "'")
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

    $cache[$key] = $settings;

    return $settings;
}

/**
 * One setting of a driver, for the driver to read: the stored value, the
 * field's default when none is stored, '' for a field it does not declare.
 *
 * @param string $key
 * @param string $name
 * @return string
 */
function pg_payment_setting($key, $name)
{
    $settings = pg_payment_settings($key);

    if (isset($settings['secrets'][$name])) {
        return $settings['secrets'][$name];
    }

    if (isset($settings['values'][$name])) {
        return $settings['values'][$name];
    }

    foreach (pg_payment_fields($key) as $field) {
        if ($field['name'] === $name) {
            return $field['default'];
        }
    }

    return '';
}

/**
 * What a post of the Ecommerce card asks for one driver, decided from the
 * post and the stored settings alone: no database, no service.
 *
 * Only the drivers whose fields were on the posted form are decided: a form
 * without them says nothing about that driver. An empty secret keeps the
 * stored one. A value outside a select's options keeps the stored one (or
 * the default). A required field is required only of the driver that is
 * about to take payments: the selected card gateway, or a redirect driver
 * switched on.
 *
 * @param array $definition pg_payment_driver()
 * @param array $fields     pg_payment_fields()
 * @param array $post
 * @param array $stored     pg_payment_settings()
 * @param bool  $selected   card: the posted form selects this driver's gateway
 *                          with the card method on
 * @return array posted, values, secrets (only the newly typed ones),
 *               enabled, active, changed, marks (input name => sentence)
 */
function pg_payment_settings_decide($definition, $fields, $post, $stored, $selected)
{
    $key = $definition['key'];

    if (!is_array($post) || !isset($post[pg_payment_input_name($key, 'present')])) {
        return array('posted' => false);
    }

    $posted = function ($name) use ($post, $key) {
        $input = pg_payment_input_name($key, $name);

        return (isset($post[$input]) && is_string($post[$input])) ? $post[$input] : '';
    };

    $enabled = ($definition['kind'] === 'card') || ($posted('enabled') === '1');
    $active = ($definition['kind'] === 'card') ? (bool) $selected : $enabled;

    $values = array();
    $secrets = array();
    $marks = array();

    foreach ($fields as $field) {
        $name = $field['name'];
        $input = pg_payment_input_name($key, $name);
        $raw = $posted($name);
        $label = h($field['label']);

        switch ($field['type']) {
            case 'secret':
                $typed = trim($raw);

                if ($typed !== '') {
                    if (mb_strlen($typed) > $field['max_length']) {
                        $marks[$input] = lang(array('string' => '{var:1} is too long.', 'vars' => array($label)));
                    }

                    if (!isset($stored['secrets'][$name]) || ($stored['secrets'][$name] !== $typed)) {
                        $secrets[$name] = $typed;
                    }
                } elseif ($field['required'] && $active && !isset($stored['secrets'][$name])) {
                    $marks[$input] = lang(array('string' => '{var:1} is required.', 'vars' => array($label)));
                }

                break;

            case 'select':
                if (array_key_exists($raw, $field['options'])) {
                    $values[$name] = $raw;
                } elseif (isset($stored['values'][$name]) && array_key_exists($stored['values'][$name], $field['options'])) {
                    $values[$name] = $stored['values'][$name];
                } else {
                    $values[$name] = $field['default'];
                }

                break;

            case 'switch':
                $values[$name] = ($raw === '1') ? '1' : '';

                break;

            default:
                $value = trim($raw);

                if (mb_strlen($value) > $field['max_length']) {
                    $marks[$input] = lang(array('string' => '{var:1} is too long.', 'vars' => array($label)));
                } elseif ($field['required'] && $active && ($value === '')) {
                    $marks[$input] = lang(array('string' => '{var:1} is required.', 'vars' => array($label)));
                }

                $values[$name] = $value;
        }
    }

    // Only a change is written. A driver never set up gets no row while
    // every value is still its default, so an unrelated save of the card
    // does not create one.
    if (!empty($stored['exists'])) {
        $compare = isset($stored['values']) ? $stored['values'] : array();
        $changed = ($enabled !== !empty($stored['enabled']));
    } else {
        $compare = array();

        foreach ($fields as $field) {
            $compare[$field['name']] = $field['default'];
        }

        $changed = ($definition['kind'] === 'redirect') && $enabled;
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
        'active'  => $active,
        'changed' => $changed,
        'marks'   => $marks,
    );
}

/**
 * Checks the drivers' fields of a post of the Ecommerce card and marks what
 * is refused, before anything on the card is written.
 *
 * @param array    $post     $_POST
 * @param liveform $liveform
 * @return array key => pg_payment_settings_decide(), for pg_payment_settings_save()
 */
function pg_payment_settings_check($post, $liveform)
{
    $decisions = array();

    $gateway = (isset($post['ecommerce_payment_gateway']) && is_string($post['ecommerce_payment_gateway'])) ? $post['ecommerce_payment_gateway'] : '';
    $card_on = isset($post['ecommerce_credit_debit_card']) && ((string) $post['ecommerce_credit_debit_card'] === '1');

    foreach (pg_payment_drivers() as $key => $definition) {
        $fields = pg_payment_fields($key);

        $decision = pg_payment_settings_decide($definition, $fields, $post, pg_payment_settings($key), $card_on && ($definition['gateway'] === $gateway));

        if (!$decision['posted']) {
            continue;
        }

        foreach ($decision['marks'] as $input => $sentence) {
            $liveform->mark_error($input, $sentence);
        }

        $first = $fields ? pg_payment_input_name($key, $fields[0]['name']) : pg_payment_input_name($key, 'enabled');

        if ($decision['changed'] && !pg_connect_schema_ready()) {
            $liveform->mark_error($first, lang('The database has not been upgraded yet. Run the software update first.'));
        } elseif (($decision['secrets'] !== array()) && !pg_connect_can_seal()) {
            $liveform->mark_error(pg_payment_input_name($key, (string) key($decision['secrets'])), lang('This site has no encryption key, so it cannot keep the keys of a payment service.'));
        }

        $decisions[$key] = $decision;
    }

    return $decisions;
}

/**
 * Writes what pg_payment_settings_check() decided, for the drivers whose
 * settings changed.
 *
 * @param array $decisions pg_payment_settings_check()
 * @param int   $user_id
 * @return void
 */
function pg_payment_settings_save($decisions, $user_id)
{
    foreach ($decisions as $key => $decision) {
        if (!empty($decision['posted']) && $decision['changed']) {
            pg_payment_settings_store($key, $decision['values'], $decision['secrets'], $decision['enabled'], $user_id);
        }
    }
}

/**
 * Stores a driver's settings. The secrets given are added to the stored
 * ones; a stored secret is never cleared here, so a save of a form that
 * never draws secrets back cannot lose them.
 *
 * @param string $key
 * @param array  $values  field => text
 * @param array  $secrets field => text, only the newly typed ones
 * @param bool   $enabled
 * @param int    $user_id
 * @return bool false when the settings cannot be kept here (no upgraded
 *              database, or secrets and no encryption key)
 */
function pg_payment_settings_store($key, $values, $secrets, $enabled, $user_id)
{
    if (!pg_connect_schema_ready() || (pg_payment_driver($key) === null)) {
        return false;
    }

    if ($secrets && !pg_connect_can_seal()) {
        return false;
    }

    $provider = pg_payment_settings_key($key);
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

    pg_payment_settings($key, true);

    return true;
}

/**
 * A driver's fields as the Ecommerce card draws them: secrets never drawn
 * back, a hidden marker so the save knows the fields were on the form.
 *
 * @param array $definition pg_payment_driver()
 * @param array $fields     pg_payment_fields()
 * @param array $settings   pg_payment_settings()
 * @return string
 */
function pg_payment_settings_fields_html($definition, $fields, $settings)
{
    $key = $definition['key'];

    $html = '
                                                                                        <input type="hidden" name="' . h(pg_payment_input_name($key, 'present')) . '" value="1"/>';

    if (!$settings['readable']) {
        $html .= '
                                                                                        <div class="col-12"><div class="alert alert-warning mb-0">' . lang(array(
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
        $id = pg_payment_input_name($key, $field['name']);
        $value = isset($settings['values'][$field['name']]) ? $settings['values'][$field['name']] : $field['default'];
        $help = ($field['help'] !== '') ? '
                                                                                            <div class="form-text">' . h($field['help']) . '</div>' : '';

        switch ($field['type']) {
            case 'switch':
                $html .= '
                                                                                        <div class="col-12">
                                                                                            <div class="form-check form-switch">
                                                                                                <input value="1"' . (($value === '1') ? ' checked="checked"' : '') . ' class="form-check-input" type="checkbox" id="' . h($id) . '" name="' . h($id) . '"/>
                                                                                                <label class="form-check-label" for="' . h($id) . '">' . h($field['label']) . '</label>
                                                                                            </div>' . $help . '
                                                                                        </div>';
                break;

            case 'select':
                $options = '';

                foreach ($field['options'] as $option => $option_label) {
                    $options .= '<option value="' . h($option) . '"' . (((string) $option === $value) ? ' selected="selected"' : '') . '>' . h($option_label) . '</option>';
                }

                $html .= '
                                                                                        <div class="pg-f-md">
                                                                                            <label class="form-label" for="' . h($id) . '">' . h($field['label']) . '</label>
                                                                                            <select class="form-select" id="' . h($id) . '" name="' . h($id) . '">' . $options . '</select>' . $help . '
                                                                                        </div>';
                break;

            case 'secret':
                $placeholder = isset($settings['secrets'][$field['name']])
                    ? ' placeholder="' . h(lang('Stored. Leave empty to keep it.')) . '"'
                    : '';

                $html .= '
                                                                                        <div class="pg-f-lg">
                                                                                            <label class="form-label" for="' . h($id) . '">' . h($field['label']) . '</label>
                                                                                            <input class="form-control" type="password" id="' . h($id) . '" name="' . h($id) . '" value="" maxlength="' . (int) $field['max_length'] . '" autocomplete="new-password"' . $placeholder . '/>' . $help . '
                                                                                        </div>';
                break;

            default:
                $html .= '
                                                                                        <div class="pg-f-lg">
                                                                                            <label class="form-label" for="' . h($id) . '">' . h($field['label']) . '</label>
                                                                                            <input class="form-control" type="text" id="' . h($id) . '" name="' . h($id) . '" value="' . h($value) . '" maxlength="' . (int) $field['max_length'] . '" autocomplete="off"/>' . $help . '
                                                                                        </div>';
        }
    }

    return $html;
}

/**
 * The Payment Gateway options of the card drivers whose gateway the list
 * does not already carry.
 *
 * @param string $gateway the stored gateway
 * @return string
 */
function pg_payment_settings_gateway_options($gateway)
{
    $html = '';

    foreach (pg_payment_drivers() as $definition) {
        if (($definition['kind'] === 'card') && !in_array($definition['gateway'], pg_payment_legacy_gateways(), true)) {
            $html .= '<option value="' . h($definition['gateway']) . '"' . (($definition['gateway'] === (string) $gateway) ? ' selected="selected"' : '') . '>' . h($definition['label']) . '</option>';
        }
    }

    return $html;
}

/**
 * The card drivers' fields inside the Payment Gateway box, each shown while
 * its gateway is selected (show_or_hide_ecommerce_payment_gateway() in
 * backend.src.js switches them by data-pg-payment-gateway).
 *
 * @param string $gateway      the stored gateway
 * @param bool   $card_enabled the Credit/Debit Card method is on
 * @return string
 */
function pg_payment_settings_card_rows($gateway, $card_enabled)
{
    $html = '';

    foreach (pg_payment_drivers() as $key => $definition) {
        $fields = (($definition['kind'] === 'card') && ($definition['settings'] === 'connect')) ? pg_payment_fields($key) : array();

        if (!$fields) {
            continue;
        }

        $shown = $card_enabled && ($definition['gateway'] === (string) $gateway);

        $html .= '
                                                                                        <div class="col-12" data-pg-payment-gateway="' . h($definition['gateway']) . '"' . ($shown ? '' : ' style="display: none"') . '>
                                                                                            <div class="row gy-3">' . pg_payment_settings_fields_html($definition, $fields, pg_payment_settings($key)) . '
                                                                                            </div>
                                                                                        </div>';
    }

    return $html;
}

/**
 * The redirect drivers, each a payment method with its own switch.
 *
 * @return string
 */
function pg_payment_settings_redirect_rows()
{
    $html = '';

    foreach (pg_payment_drivers() as $key => $definition) {
        if (($definition['kind'] !== 'redirect') || ($definition['settings'] !== 'connect')) {
            continue;
        }

        $settings = pg_payment_settings($key);
        $switch = pg_payment_input_name($key, 'enabled');
        $row = pg_payment_input_name($key, 'row');

        $html .= '
                                                        <div class="col-12 ">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . ($settings['enabled'] ? ' checked="checked"' : '') . ' class="form-check-input collapse-switcher" type="checkbox" id="' . h($switch) . '" name="' . h($switch) . '" data-bs-target="#' . h($row) . '"/>
                                                                <label class="form-check-label" for="' . h($switch) . '">' . h($definition['label']) . '</label>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 " id="' . h($row) . '">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                <div class="popover-body">
                                                                    <div class="row gy-3">' . pg_payment_settings_fields_html($definition, pg_payment_fields($key), $settings) . '
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>';
    }

    return $html;
}


/* ---------------------------------------------------------------------------
   Checkout (submit_order.php)
   --------------------------------------------------------------------------- */

/**
 * The value of the mode parameter that brings the visitor back from a
 * driver's 3-D Secure or hosted page. The shipped drivers keep the addresses
 * submit_order.php and order_preview.php already treat as a return.
 *
 * @param string $key
 * @return string '' for a driver that has none
 */
function pg_payment_return_mode($key)
{
    $modes = pg_payment_return_modes();

    return (is_string($key) && isset($modes[$key])) ? $modes[$key] : '';
}

/**
 * Every driver's return mode, key => mode. A shipped driver keeps the
 * address it has always come back to; one added later comes back to
 * '<key>_return'.
 *
 * @return array
 */
function pg_payment_return_modes()
{
    $modes = array(
        'iyzico'          => 'iyzipay_threedsecure_return',
        'pay_with_iyzico' => 'pay_with_iyzico_return',
    );

    foreach (pg_payment_drivers() as $key => $definition) {
        if (!isset($modes[$key])) {
            $modes[$key] = $key . '_return';
        }
    }

    return $modes;
}

/**
 * Whether a request's mode parameter brings the visitor back from a payment
 * service: PayPal Express Checkout (the legacy code) or a driver. The
 * checkout screens treat these alike: the request is the service's, so it
 * carries no form token and no form fields, and it finishes the order the
 * visitor already submitted.
 *
 * @param string $mode
 * @return bool
 */
function pg_payment_is_return_mode($mode)
{
    $mode = (string) $mode;

    return ($mode !== '') && (($mode === 'paypal_express_checkout_return') || in_array($mode, pg_payment_return_modes(), true));
}

/**
 * The payment method a return mode belongs to: the card method for a card
 * driver, the driver's own method for a redirect driver.
 *
 * @param string $mode
 * @return string '' for a mode no driver returns to
 */
function pg_payment_return_method($mode)
{
    $key = array_search((string) $mode, pg_payment_return_modes(), true);
    $definition = ($key === false) ? null : pg_payment_driver($key);

    return ($definition === null) ? '' : $definition['method'];
}

/**
 * The card driver of the Payment Gateway value when the card is typed into
 * the service's own fields (the 'hosted_fields' capability): the checkout
 * then draws no card inputs and posts the service's token instead.
 *
 * @param string|null $gateway null for the store's own
 * @return string driver key, '' when the card inputs are the site's
 */
function pg_payment_hosted_card_driver($gateway = null)
{
    $key = pg_payment_order_driver('Credit/Debit Card', $gateway);

    return (($key !== '') && pg_payment_can($key, 'hosted_fields') && pg_payment_can($key, 'prepare')) ? $key : '';
}

/**
 * The sources the site's built-in Content-Security-Policy adds for the
 * payment driver that takes cards: the card gateway's driver while the store
 * takes cards. Asked with the config row's values, because the policy header
 * is sent before the config constants are defined.
 *
 * @param string $gateway config.ecommerce_payment_gateway
 * @param bool   $card_on the store is on and takes the Credit/Debit Card method
 * @return array directive => hosts
 */
function pg_payment_csp_sources($gateway, $card_on)
{
    $key = $card_on ? pg_payment_driver_for_gateway((string) $gateway) : '';
    $definition = ($key !== '') ? pg_payment_driver($key) : null;

    return ($definition !== null) ? $definition['csp'] : array();
}

/**
 * The driver that takes the payment of a checkout request.
 *
 * The card method goes to the driver of the Payment Gateway value, any other
 * method to the driver of that method. A return its driver does not finish
 * (no complete()) is finished by the code in submit_order.php that has always
 * handled that return. A driver that is registered but cannot be loaded is
 * still the answer, so the payment is refused rather than taken by no one.
 *
 * @param string $method  the posted payment method
 * @param string $gateway ECOMMERCE_PAYMENT_GATEWAY
 * @param string $mode    the request's mode parameter
 * @return string driver key, '' when submit_order.php's gateway code takes it
 */
function pg_payment_checkout_driver($method, $gateway, $mode)
{
    $key = pg_payment_order_driver((string) $method, (string) $gateway);

    if ($key === '') {
        return '';
    }

    if (((string) $mode !== '') && ((string) $mode === pg_payment_return_mode($key)) && pg_payment_load($key) && !pg_payment_can($key, 'complete')) {
        return '';
    }

    return $key;
}

/**
 * What an order records from a payment a driver took, in the variables the
 * gateway code has always set: the amount above the order total is the
 * instalment charge.
 *
 * @param array $answer pg_payment_answer() of authorize() or complete()
 * @param int   $total  hundredths: the order total before any instalment charge
 * @return array total, installment_charge, total_without_installment_charge
 *               (0 when there is no charge), payment_installment (1 or more)
 */
function pg_payment_checkout_totals($answer, $total)
{
    $total = (int) $total;
    $amount = (isset($answer['amount']) && is_int($answer['amount'])) ? $answer['amount'] : $total;
    $charge = ($amount > $total) ? $amount - $total : 0;
    $installments = (isset($answer['installments']) && is_int($answer['installments'])) ? $answer['installments'] : 0;

    return array(
        'total'                            => $total + $charge,
        'installment_charge'               => $charge,
        'total_without_installment_charge' => ($charge > 0) ? $total : 0,
        'payment_installment'              => ($installments > 1) ? $installments : 1,
    );
}

/**
 * The order's delivery address when it has exactly one and something to
 * ship, for a service that asks for the address the goods go to.
 *
 * @param int $order_id
 * @return array|null first_name, last_name, address_1, address_2, city,
 *                    state, zip_code, country
 */
function pg_payment_order_ship_to($order_id)
{
    $order_id = (int) $order_id;

    $shippable = db_value(
        "SELECT COUNT(*) FROM order_items
        LEFT JOIN products ON products.id = order_items.product_id
        WHERE (order_items.order_id = '" . $order_id . "') AND (products.shippable = '1')" . pg_order_item_active_sql());

    if ((int) $shippable <= 0) {
        return null;
    }

    $recipients = db_items(
        "SELECT first_name, last_name, address_1, address_2, city, state, zip_code, country
        FROM ship_tos
        WHERE order_id = '" . $order_id . "'");

    return (is_array($recipients) && (count($recipients) === 1)) ? $recipients[0] : null;
}


/* ---------------------------------------------------------------------------
   Money back on an order (view_order.php, process_order_cancellation())
   --------------------------------------------------------------------------- */

/**
 * How the activity log names the service behind a driver.
 *
 * @param string $key
 * @return string '' for a key no driver answers to
 */
function pg_payment_log_name($key)
{
    $definition = pg_payment_driver($key);

    return ($definition === null) ? '' : $definition['log_name'];
}

/**
 * The driver that took an order's payment: a card order (the method
 * 'Credit/Debit Card') goes to the driver of the Payment Gateway value, any
 * other method to the driver of that method, whichever gateway the store has.
 * The order records the method and not the gateway, so a card order is
 * credited to the gateway the store has now.
 *
 * @param string      $method  orders.payment_method
 * @param string|null $gateway the Payment Gateway value; null for the store's
 *                             own (ECOMMERCE_PAYMENT_GATEWAY)
 * @return string driver key, '' when no driver serves the order
 */
function pg_payment_order_driver($method, $gateway = null)
{
    if ($gateway === null) {
        $gateway = defined('ECOMMERCE_PAYMENT_GATEWAY') ? ECOMMERCE_PAYMENT_GATEWAY : '';
    }

    return ((string) $method === 'Credit/Debit Card')
        ? pg_payment_driver_for_gateway((string) $gateway)
        : pg_payment_driver_for_method((string) $method);
}

/**
 * The driver the order screen gives an order's money back through.
 *
 * @param string      $method  orders.payment_method
 * @param string|null $gateway as in pg_payment_order_driver()
 * @return string driver key, '' when the order's driver cannot refund
 */
function pg_payment_refund_driver($method, $gateway = null)
{
    $key = pg_payment_order_driver($method, $gateway);

    return (($key !== '') && pg_payment_can($key, 'refund')) ? $key : '';
}

/**
 * The driver that voids an order's payment when the order is cancelled. A
 * gateway that is not driver-based leaves the refund to the operator.
 *
 * @param string      $method  orders.payment_method
 * @param string|null $gateway as in pg_payment_order_driver()
 * @return string driver key, '' when the order's driver cannot cancel
 */
function pg_payment_cancel_driver($method, $gateway = null)
{
    $key = pg_payment_order_driver($method, $gateway);

    return (($key !== '') && pg_payment_can($key, 'cancel')) ? $key : '';
}

/**
 * What a cancellation records after asking a driver to void the payment, and
 * the activity log lines that say so: the driver's own lines first, then the
 * cancellation's.
 *
 * @param int    $order_id
 * @param string $transaction_id orders.transaction_id, the reference when the
 *                               service names none
 * @param array  $answer         pg_payment_call() of cancel()
 * @param string $service        pg_payment_log_name()
 * @return array refund_status ('refunded' or 'manual_required'),
 *               refund_reference, log
 */
function pg_payment_cancel_outcome($order_id, $transaction_id, $answer, $service)
{
    $order_id = (int) $order_id;
    $answer = pg_payment_answer('cancel', $answer);
    $log = $answer['log'];

    if ($answer['success']) {
        $reference = ($answer['refund_reference'] !== '') ? $answer['refund_reference'] : (string) $transaction_id;
        $log[] = 'Auto-refund SUCCESS via ' . $service . ' — order #' . $order_id . ' (payment ' . $reference . ').';

        return array('refund_status' => 'refunded', 'refund_reference' => $reference, 'log' => $log);
    }

    $message = (isset($answer['raw']['message']) && is_scalar($answer['raw']['message'])) ? (string) $answer['raw']['message'] : '';

    if (isset($answer['raw']['exception'])) {
        $log[] = 'Auto-refund EXCEPTION — order #' . $order_id . '. MANUAL REFUND REQUIRED. ' . $message;
    } else {
        $log[] = 'Auto-refund FAILED via ' . $service . ' — order #' . $order_id .
            '. MANUAL REFUND REQUIRED. Gateway message: ' . ($message !== '' ? $message : '(no message)');
    }

    return array('refund_status' => 'manual_required', 'refund_reference' => '', 'log' => $log);
}
