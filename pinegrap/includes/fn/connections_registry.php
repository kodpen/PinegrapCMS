<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The registry of outside services: one row per outside product or API the
 * site connects to, with its group, the one place its settings live (a card
 * of Site Settings by its address, or a System tools screen), how its state
 * is read from what the site has stored, and what uses it. Pure data and
 * pure functions: nothing here reads the database or calls a service.
 *
 * The lists that already exist stay the source of their own entries
 * (payment and label drivers, marketplaces, SMS providers, connected
 * account providers and purposes, model providers, the MCP catalogue, mail
 * transports, address and captcha providers, cloud pickers, e-document
 * drivers); a row names the keys it covers under 'covers', and a test fails
 * when a key of those lists has no row.
 *
 * What one service is, and what is not counted, is written beside
 * pg_connections_count().
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
 * The groups, in the order a list of services is read. Labels are English
 * source text, translated where a screen draws them. Pure.
 *
 * @return array key => label
 */
function pg_connections_groups()
{
    return array(
        'payments'    => 'Payments',
        'shipping'    => 'Shipping',
        'marketplaces' => 'Marketplaces and feeds',
        'edocuments'  => 'E-documents and tax',
        'email'       => 'E-mail',
        'messaging'   => 'SMS and messaging',
        'storage'     => 'Files and backups',
        'signin'      => 'Sign-in',
        'calendar'    => 'Calendars and notes',
        'analytics'   => 'Analytics and search',
        'maps'        => 'Maps and places',
        'ai'          => 'AI',
        'protection'  => 'Protection and delivery',
        'other'       => 'Other',
        'mcp'         => 'MCP connectors',
    );
}

/**
 * The kinds of state reader, and the keys each needs. Pure.
 *
 * - account: a connected account (connect_accounts) of 'provider' for
 *   'purpose'; the only kind that can read "needs attention", from an
 *   account marked reauth ('attention' => true).
 * - app: a connect_apps row 'key' that is enabled.
 * - service: a token the server keeps for itself under 'key'
 *   (pg_connect_service_get()).
 * - config: the config constants 'names' are all set; with 'equals', the
 *   first of them holds that value.
 * - reader: the function 'function' answers true from stored data, called
 *   with 'args' when given.
 * - marketplace: an account of the marketplace provider 'key'.
 * - edoc: the e-document provider 'key' is the active one.
 * - model: a Workspace assistant runs on the model provider 'key'.
 * - mcp: a Workspace MCP server was added from the catalogue entry 'key'
 *   (ws_mcp_catalogue_added(), matched by its address).
 * - notify: a chat notification target of the kind 'key' is stored
 *   (pg_notify_targets()).
 *
 * @return array kind => list of required keys
 */
function pg_connections_state_kinds()
{
    return array(
        'account'     => array('provider', 'purpose'),
        'app'         => array('key'),
        'service'     => array('key'),
        'config'      => array('names'),
        'reader'      => array('function'),
        'marketplace' => array('key'),
        'edoc'        => array('key'),
        'model'       => array('key'),
        'mcp'         => array('key'),
        'notify'      => array('key'),
    );
}

/**
 * The screens outside Site Settings and System tools that are the one place
 * of a service's setting. Pure.
 *
 * @return array list of scripts
 */
function pg_connections_other_screens()
{
    return array('search_console.php');
}

/**
 * The lists a row may cover, by the name 'covers' uses. Pure.
 *
 * @return array name => the function that answers the list
 */
function pg_connections_source_lists()
{
    return array(
        'payment'     => 'pg_payment_builtin_drivers()',
        'label'       => 'pg_label_builtin_drivers()',
        'marketplace' => 'mp_providers()',
        'sms'         => 'pg_sms_providers()',
        'connect'     => "pg_connect_definitions('providers')",
        'purpose'     => "pg_connect_definitions('purposes')",
        'model'       => 'ws_model_providers()',
        'mcp'         => 'ws_mcp_catalogue()',
        'mail'        => 'pg_mail_transports()',
        'address'     => 'pg_addr_providers()',
        'captcha'     => 'pg_captcha_providers()',
        'picker'      => 'pg_cloud_picker_providers()',
        'edoc'        => 'erp_edoc_drivers()',
        'notify'      => 'pg_notify_channel_kinds()',
    );
}

/**
 * The keys of those lists that are no outside service, with why. Pure.
 *
 * @return array list name => key => reason
 */
function pg_connections_not_services()
{
    return array(
        'model'   => array('custom' => 'Any address the operator types; no product of its own.'),
        'purpose' => array(
            'account' => 'Who the connected account is; every feature asks for more.',
            'mcp'     => 'Any MCP server by its address; the ready-made ones are rows of their own.',
        ),
    );
}

/**
 * What is wrong with one row, as the names of the keys at fault; an empty
 * list for a whole row. Pure.
 *
 * A row: 'name', 'group' (a key of pg_connections_groups()), 'place'
 * ('#settings/<category>/<card>' or a System tools script), 'state' (a reader
 * of pg_connections_state_kinds(), or null with the reason in 'no_state'),
 * 'uses' (English, a few words), optionally 'covers' (list name => keys)
 * and 'found_at' (a card its name still finds in the settings search after
 * its settings moved to its page on Connections); a Google feature names
 * under 'apis' the Google Cloud APIs it calls (service name => product
 * name), listed on Google's page (pg_google_cloud_apis()). Every row
 * also carries 'country' and 'data' from pg_connections_transfer_fields().
 *
 * @param mixed $row
 * @return array list of key names
 */
function pg_connections_row_problems($row)
{
    if (!is_array($row)) {
        return array('row');
    }

    $problems = array();
    $text = function ($key) use ($row) {
        return isset($row[$key]) && is_string($row[$key]) && (trim($row[$key]) !== '');
    };

    if (!$text('name')) {
        $problems[] = 'name';
    }

    if (!$text('group') || !array_key_exists($row['group'], pg_connections_groups())) {
        $problems[] = 'group';
    }

    if (!$text('place') || !preg_match('~^(#settings/[a-z_]+/pgset-[a-z0-9-]+|[a-z_]+\.php)$~', $row['place'])) {
        $problems[] = 'place';
    }

    $kinds = pg_connections_state_kinds();
    $state = isset($row['state']) ? $row['state'] : null;

    if ($state === null) {
        if (!$text('no_state')) {
            $problems[] = 'state';
        }
    } elseif (!is_array($state) || !isset($state['kind']) || !isset($kinds[$state['kind']])) {
        $problems[] = 'state';
    } else {
        foreach ($kinds[$state['kind']] as $key) {
            if (empty($state[$key])) {
                $problems[] = 'state';
                break;
            }
        }
    }

    if (!$text('uses')) {
        $problems[] = 'uses';
    }

    if (!isset($row['country']) || !is_string($row['country']) || !preg_match('/^([A-Z]{2}|\*)$/', $row['country'])) {
        $problems[] = 'country';
    }

    $data = isset($row['data']) ? $row['data'] : null;
    $categories = pg_privacy_inventory_categories();
    $data_whole = is_array($data) && $data && (($data === array('none')) || !in_array('none', $data, true));

    if ($data_whole) {
        foreach ($data as $key) {
            if (($key !== 'none') && (!is_string($key) || !isset($categories[$key]))) {
                $data_whole = false;
                break;
            }
        }
    }

    if (!$data_whole) {
        $problems[] = 'data';
    }

    if (isset($row['covers'])) {
        $lists = pg_connections_source_lists();

        if (!is_array($row['covers'])) {
            $problems[] = 'covers';
        } else {
            foreach ($row['covers'] as $list => $keys) {
                if (!isset($lists[$list]) || !is_array($keys) || !$keys) {
                    $problems[] = 'covers';
                    break;
                }
            }
        }
    }

    return $problems;
}

/**
 * The keys of one source list the rows cover. Pure.
 *
 * @param string $list a name of pg_connections_source_lists()
 * @return array list of keys
 */
function pg_connections_covered($list)
{
    $keys = array();

    foreach (pg_connections_registry() as $row) {
        if (isset($row['covers'][$list])) {
            foreach ($row['covers'][$list] as $key) {
                $keys[$key] = true;
            }
        }
    }

    return array_map('strval', array_keys($keys));
}

/**
 * The product's figure of outside services: the rows of the registry but
 * the ready-made MCP connectors. Pure.
 *
 * One service is one outside product or API the code was written for, with
 * a place to set it up, counted once however many drivers, purposes or
 * screens reach it (iyzico's card and redirect drivers are one row; Google
 * Drive and Google Analytics are two, as two products behind one account).
 *
 * Not counted: generic protocols (SMTP, IMAP, FTP, S3-compatible storage,
 * iCal, RSS, Web Push, time stamps), an address the operator types (a custom
 * model provider, any MCP server), a carrier that is a tracking link only,
 * what runs in the visitor's browser alone (Chrome's translator), services
 * the site only links to, and anything planned or partly built. The
 * ready-made MCP connectors are rows of their own, counted apart
 * (pg_connections_connector_count()), since the one MCP client reaches them.
 *
 * @return int
 */
function pg_connections_count()
{
    $count = 0;

    foreach (pg_connections_registry() as $row) {
        if ($row['group'] !== 'mcp') {
            $count++;
        }
    }

    return $count;
}

/**
 * How many ready-made MCP connectors the registry holds. Pure.
 *
 * @return int
 */
function pg_connections_connector_count()
{
    return count(pg_connections_registry()) - pg_connections_count();
}

/**
 * The names of the services each place holds, as search words: a service
 * typed in the Site Settings search or the command palette finds the card
 * or screen its setting lives on. Pure.
 *
 * A capital dotted İ is lowered to a plain i, since mb_strtolower() would
 * add a combining dot that no typed query holds.
 *
 * @return array place ('#settings/<category>/<card>' or a script) => list
 *               of lower-case names
 */
function pg_connections_place_keys()
{
    static $keys = null;

    if ($keys !== null) {
        return $keys;
    }

    $keys = array();

    foreach (pg_connections_registry() as $row) {
        $name = mb_strtolower(str_replace('İ', 'i', $row['name']), 'UTF-8');

        // A service whose settings moved to its page on Connections is
        // still found at the card that chooses it ('found_at').
        foreach (array_filter(array($row['place'], isset($row['found_at']) ? $row['found_at'] : '')) as $place) {
            if (!isset($keys[$place]) || !in_array($name, $keys[$place], true)) {
                $keys[$place][] = $name;
            }
        }
    }

    return $keys;
}

/**
 * The search words a place's own words gain from the services it holds,
 * merged without repeats. Pure.
 *
 * @param string $place a place as the registry writes it
 * @param array  $words the place's own words
 * @return array list of words
 */
function pg_connections_place_words($place, $words)
{
    $keys = pg_connections_place_keys();

    if (!isset($keys[$place])) {
        return array_values((array) $words);
    }

    return array_values(array_unique(array_merge(array_values((array) $words), $keys[$place])));
}

/**
 * The names of the services one place holds, for the Site Settings search,
 * which ranks them after every other match of a row. Pure.
 *
 * @param string $place a place as the registry writes it
 * @return array list of lower-case names
 */
function pg_connections_place_services($place)
{
    $keys = pg_connections_place_keys();

    return isset($keys[$place]) ? $keys[$place] : array();
}

/**
 * Where each service sits and what personal data the site sends it, for the
 * Privacy centre's transfers: id => array(country, data). Pure.
 *
 * country: the two-letter code of the country of the company the site
 *          contracts with (its seat when this was written; the shop checks
 *          it for its own notice), or '*' for a network with no one country.
 * data:    keys of pg_privacy_inventory_categories(), or array('none') when
 *          the service receives no personal data.
 *
 * A row of pg_connections_registry() missing here fails its test.
 *
 * @return array id => array(country, data)
 */
function pg_connections_transfer_fields()
{
    $card = array('identity', 'contact', 'address', 'order_payment', 'ip_cookie');
    $label = array('identity', 'contact', 'address');
    $order = array('identity', 'contact', 'address', 'order_payment');
    $mail = array('identity', 'contact', 'messages');
    $sms = array('contact', 'messages');
    $alert = array('identity', 'contact', 'messages');
    $backup = array('identity', 'contact', 'address', 'order_payment', 'ip_cookie', 'signature', 'uploads', 'messages');
    $sign_in = array('identity', 'contact');
    $calendar = array('identity', 'contact', 'messages');
    $text = array('messages');
    $none = array('none');

    return array(
        'iyzico' => array('TR', $card),
        'stripe' => array('US', $card),
        'paypal' => array('US', $card),
        'paytr' => array('TR', $card),
        'authorize_net' => array('US', $card),
        'clearcommerce' => array('US', $card),
        'first_data' => array('US', $card),
        'sage' => array('US', $card),
        'givex' => array('CA', array('order_payment')),

        'geliver' => array('TR', $label),
        'basitkargo' => array('TR', $label),
        'kargonomi' => array('TR', $label),
        'aras' => array('TR', $label),
        'shippo' => array('US', $label),
        'sendcloud' => array('NL', $label),
        'ups' => array('US', array('address')),
        'fedex' => array('US', array('address')),
        'usps' => array('US', array('address')),
        'shipworks' => array('US', $order),

        'n11' => array('TR', $order),
        'trendyol' => array('TR', $order),
        'merchant_center' => array('US', $none),
        'openai_feed' => array('US', $none),

        'parasut' => array('TR', $order),
        'isbasi' => array('TR', $order),
        'vies' => array('BE', array('identity')),

        'postmark' => array('US', $mail),
        'mailgun' => array('US', $mail),
        'brevo' => array('FR', $mail),
        'resend' => array('US', $mail),
        'ses' => array('US', $mail),
        'gmail' => array('US', $mail),
        'microsoft_mail' => array('US', $mail),
        'mailchimp' => array('US', $sign_in),

        'netgsm' => array('TR', $sms),
        'iletimerkezi' => array('TR', $sms),
        'twilio' => array('US', $sms),
        'iys' => array('TR', array('contact')),
        'whatsapp' => array('US', $alert),
        'slack' => array('US', $alert),
        'teams' => array('US', $alert),
        'telegram' => array('AE', $alert),

        'google_drive' => array('US', $backup),
        'onedrive' => array('US', $backup),
        'dropbox' => array('US', $backup),

        'signin_google' => array('US', $sign_in),
        'signin_microsoft' => array('US', $sign_in),
        'signin_apple' => array('US', $sign_in),

        'google_calendar' => array('US', $calendar),
        'outlook_calendar' => array('US', $calendar),
        'onenote' => array('US', $text),

        'google_analytics' => array('US', array('ip_cookie')),
        'search_console' => array('US', $none),
        'indexnow' => array('US', $none),

        'google_places' => array('US', array('address')),
        'mapbox' => array('US', array('address')),
        'photon' => array('DE', array('address')),
        'google_reviews' => array('US', $none),
        'dbip' => array('*', $none),

        'pinegrap_ai' => array('TR', $text),
        'claude' => array('US', $text),
        'openai' => array('US', $text),
        'anthropic' => array('US', $text),
        'gemini' => array('US', $text),
        'mistral' => array('FR', $text),
        'deepseek' => array('CN', $text),
        'openrouter' => array('US', $text),
        'azure_openai' => array('US', $text),
        'google_translate' => array('US', $text),

        'cloudflare' => array('US', $none),
        'turnstile' => array('US', array('ip_cookie')),
        'hcaptcha' => array('US', array('ip_cookie')),
        'stopforumspam' => array('GB', array('contact', 'ip_cookie')),

        'github' => array('US', $none),
        'activitypub' => array('*', array('identity', 'messages')),
        'frankfurter' => array('*', $none),
        'hexarate' => array('*', $none),
        'pinegrap' => array('TR', $none),

        // An MCP connector receives what an assistant asks it.
        'mcp_deepwiki' => array('US', $text),
        'mcp_context7' => array('US', $text),
        'mcp_mslearn' => array('US', $text),
        'mcp_cloudflare_docs' => array('US', $text),
        'mcp_huggingface' => array('US', $text),
        'mcp_linear' => array('US', $text),
        'mcp_notion' => array('US', $text),
        'mcp_sentry' => array('US', $text),
        'mcp_atlassian' => array('AU', $text),
        'mcp_vercel' => array('US', $text),
        'mcp_canva' => array('AU', $text),
        'mcp_github' => array('US', $text),
    );
}

/**
 * Every outside service, by id, in the groups' order. Pure.
 *
 * @return array id => row (see pg_connections_row_problems())
 */
function pg_connections_registry()
{
    static $rows = null;

    if ($rows !== null) {
        return $rows;
    }

    $rows = array(
        // Payments. The card gateways keep their config columns; the
        // redirect drivers their connect_apps switch.
        'iyzico' => array('name' => 'iyzico', 'group' => 'payments', 'place' => '#settings/commerce/pgset-payments', 'uses' => 'Card payments and Pay with iyzico at checkout',
            'state' => array('kind' => 'config', 'names' => array('ECOMMERCE_PAYMENT_GATEWAY'), 'equals' => 'Iyzipay'),
            'covers' => array('payment' => array('iyzico', 'pay_with_iyzico'))),
        'stripe' => array('name' => 'Stripe', 'group' => 'payments', 'place' => '#settings/commerce/pgset-payments', 'uses' => 'Card payments at checkout',
            'state' => array('kind' => 'config', 'names' => array('ECOMMERCE_PAYMENT_GATEWAY'), 'equals' => 'Stripe PaymentIntents'),
            'covers' => array('payment' => array('stripe'))),
        'paypal' => array('name' => 'PayPal', 'group' => 'payments', 'place' => '#settings/commerce/pgset-payments', 'uses' => 'PayPal Checkout, and the Payflow Pro and Payments Pro gateways',
            'state' => array('kind' => 'app', 'key' => 'payment_paypal'),
            'covers' => array('payment' => array('paypal'))),
        'paytr' => array('name' => 'PayTR', 'group' => 'payments', 'place' => '#settings/commerce/pgset-payments', 'uses' => 'Payments in PayTR\'s page at checkout',
            'state' => array('kind' => 'app', 'key' => 'payment_paytr'),
            'covers' => array('payment' => array('paytr'))),
        'authorize_net' => array('name' => 'Authorize.Net', 'group' => 'payments', 'place' => '#settings/commerce/pgset-payments', 'uses' => 'Card payments at checkout',
            'state' => array('kind' => 'config', 'names' => array('ECOMMERCE_PAYMENT_GATEWAY'), 'equals' => 'Authorize.Net')),
        'clearcommerce' => array('name' => 'ClearCommerce', 'group' => 'payments', 'place' => '#settings/commerce/pgset-payments', 'uses' => 'Card payments at checkout',
            'state' => array('kind' => 'config', 'names' => array('ECOMMERCE_PAYMENT_GATEWAY'), 'equals' => 'ClearCommerce')),
        'first_data' => array('name' => 'First Data', 'group' => 'payments', 'place' => '#settings/commerce/pgset-payments', 'uses' => 'Card payments at checkout',
            'state' => array('kind' => 'config', 'names' => array('ECOMMERCE_PAYMENT_GATEWAY'), 'equals' => 'First Data Global Gateway')),
        'sage' => array('name' => 'Sage', 'group' => 'payments', 'place' => '#settings/commerce/pgset-payments', 'uses' => 'Card payments at checkout',
            'state' => array('kind' => 'config', 'names' => array('ECOMMERCE_PAYMENT_GATEWAY'), 'equals' => 'Sage')),
        'givex' => array('name' => 'Givex', 'group' => 'payments', 'place' => '#settings/commerce/pgset-giftcards', 'uses' => 'Gift cards at checkout',
            'state' => array('kind' => 'config', 'names' => array('ECOMMERCE_GIVEX', 'ECOMMERCE_GIVEX_PRIMARY_HOSTNAME'))),

        // Shipping: label drivers, live rates, the order reader.
        'geliver' => array('name' => 'Geliver', 'group' => 'shipping', 'place' => '#settings/commerce/pgset-shipping', 'uses' => 'Shipping labels and tracking',
            'state' => array('kind' => 'reader', 'function' => 'pg_label_ready', 'args' => array('geliver')),
            'covers' => array('label' => array('geliver'))),
        'basitkargo' => array('name' => 'Basit Kargo', 'group' => 'shipping', 'place' => '#settings/commerce/pgset-shipping', 'uses' => 'Shipping labels and tracking',
            'state' => array('kind' => 'reader', 'function' => 'pg_label_ready', 'args' => array('basitkargo')),
            'covers' => array('label' => array('basitkargo'))),
        'kargonomi' => array('name' => 'Kargonomi', 'group' => 'shipping', 'place' => '#settings/commerce/pgset-shipping', 'uses' => 'Shipping labels and tracking',
            'state' => array('kind' => 'reader', 'function' => 'pg_label_ready', 'args' => array('kargonomi')),
            'covers' => array('label' => array('kargonomi'))),
        'aras' => array('name' => 'Aras Kargo', 'group' => 'shipping', 'place' => '#settings/commerce/pgset-shipping', 'uses' => 'Shipping labels and tracking',
            'state' => array('kind' => 'reader', 'function' => 'pg_label_ready', 'args' => array('aras')),
            'covers' => array('label' => array('aras'))),
        'shippo' => array('name' => 'Shippo', 'group' => 'shipping', 'place' => '#settings/commerce/pgset-shipping', 'uses' => 'Shipping labels and tracking',
            'state' => array('kind' => 'reader', 'function' => 'pg_label_ready', 'args' => array('shippo')),
            'covers' => array('label' => array('shippo'))),
        'sendcloud' => array('name' => 'Sendcloud', 'group' => 'shipping', 'place' => '#settings/commerce/pgset-shipping', 'uses' => 'Shipping labels and tracking',
            'state' => array('kind' => 'reader', 'function' => 'pg_label_ready', 'args' => array('sendcloud')),
            'covers' => array('label' => array('sendcloud'))),
        'ups' => array('name' => 'UPS', 'group' => 'shipping', 'place' => '#settings/commerce/pgset-shipping', 'uses' => 'Live shipping rates at checkout',
            'state' => array('kind' => 'reader', 'function' => 'pg_carrier_api_ready', 'args' => array('ups'))),
        'fedex' => array('name' => 'FedEx', 'group' => 'shipping', 'place' => '#settings/commerce/pgset-shipping', 'uses' => 'Live shipping rates at checkout',
            'state' => array('kind' => 'reader', 'function' => 'pg_carrier_api_ready', 'args' => array('fedex'))),
        'usps' => array('name' => 'USPS', 'group' => 'shipping', 'place' => '#settings/commerce/pgset-shipping', 'uses' => 'Live shipping rates at checkout',
            'state' => array('kind' => 'reader', 'function' => 'pg_carrier_api_ready', 'args' => array('usps'))),
        'shipworks' => array('name' => 'ShipWorks', 'group' => 'shipping', 'place' => '#settings/commerce/pgset-shipping', 'uses' => 'Reads orders through shipworks.php',
            'state' => null, 'no_state' => 'It signs in as a user of the site; nothing stored says it is used.'),

        // Marketplaces and feeds.
        'n11' => array('name' => 'n11', 'group' => 'marketplaces', 'place' => 'marketplace_settings.php', 'uses' => 'Listings, stock, prices and orders',
            'state' => array('kind' => 'marketplace', 'key' => 'n11'),
            'covers' => array('marketplace' => array('n11'))),
        'trendyol' => array('name' => 'Trendyol', 'group' => 'marketplaces', 'place' => 'marketplace_settings.php', 'uses' => 'Listings, stock, prices and orders',
            'state' => array('kind' => 'marketplace', 'key' => 'trendyol'),
            'covers' => array('marketplace' => array('trendyol'))),
        'merchant_center' => array('name' => 'Google Merchant Center', 'group' => 'marketplaces', 'place' => '#settings/commerce/pgset-feeds', 'uses' => 'The product feed and each product\'s push',
            'state' => array('kind' => 'account', 'provider' => 'google', 'purpose' => 'merchant_center', 'attention' => true),
            'apis' => array('merchantapi.googleapis.com' => 'Merchant API'),
            'covers' => array('marketplace' => array('merchant_center'), 'purpose' => array('merchant_center'))),
        'openai_feed' => array('name' => 'OpenAI product feed', 'group' => 'marketplaces', 'place' => '#settings/commerce/pgset-feeds', 'uses' => 'A product feed for ChatGPT shopping',
            'state' => null, 'no_state' => 'A file the service fetches; a format offered is no account.'),

        // E-documents and tax.
        'parasut' => array('name' => 'Paraşüt', 'group' => 'edocuments', 'place' => '#settings/accounting/pgset-invoice', 'uses' => 'e-Fatura, e-Arşiv and e-İrsaliye',
            'state' => array('kind' => 'edoc', 'key' => 'parasut'),
            'covers' => array('edoc' => array('parasut'))),
        'isbasi' => array('name' => 'Logo İşbaşı', 'group' => 'edocuments', 'place' => '#settings/accounting/pgset-invoice', 'uses' => 'e-Fatura and e-Arşiv',
            'state' => array('kind' => 'edoc', 'key' => 'isbasi'),
            'covers' => array('edoc' => array('isbasi'))),
        'vies' => array('name' => 'VIES', 'group' => 'edocuments', 'place' => '#settings/commerce/pgset-store', 'uses' => 'Checks EU VAT numbers at checkout',
            'state' => null, 'no_state' => 'No key or switch of its own; it runs whenever tax is on.'),

        // E-mail.
        'postmark' => array('name' => 'Postmark', 'group' => 'email', 'place' => '#settings/mail_sending/pgset-mail-transport', 'uses' => 'Sending the site\'s mail',
            'state' => array('kind' => 'app', 'key' => 'mail_postmark'),
            'covers' => array('mail' => array('postmark'))),
        'mailgun' => array('name' => 'Mailgun', 'group' => 'email', 'place' => '#settings/mail_sending/pgset-mail-transport', 'uses' => 'Sending the site\'s mail',
            'state' => array('kind' => 'app', 'key' => 'mail_mailgun'),
            'covers' => array('mail' => array('mailgun'))),
        'brevo' => array('name' => 'Brevo', 'group' => 'email', 'place' => '#settings/mail_sending/pgset-mail-transport', 'uses' => 'Sending the site\'s mail',
            'state' => array('kind' => 'app', 'key' => 'mail_brevo'),
            'covers' => array('mail' => array('brevo'))),
        'resend' => array('name' => 'Resend', 'group' => 'email', 'place' => '#settings/mail_sending/pgset-mail-transport', 'uses' => 'Sending the site\'s mail',
            'state' => array('kind' => 'app', 'key' => 'mail_resend'),
            'covers' => array('mail' => array('resend'))),
        'ses' => array('name' => 'Amazon SES', 'group' => 'email', 'place' => '#settings/mail_sending/pgset-mail-transport', 'uses' => 'Sending the site\'s mail',
            'state' => array('kind' => 'app', 'key' => 'mail_ses'),
            'covers' => array('mail' => array('ses'))),
        'gmail' => array('name' => 'Gmail', 'group' => 'email', 'place' => '#settings/mail_sending/pgset-mail-system', 'uses' => 'Sending the site\'s mail, and reading mailboxes',
            'state' => array('kind' => 'account', 'provider' => 'google', 'purpose' => 'mail', 'attention' => true),
            'apis' => array('gmail.googleapis.com' => 'Gmail API'),
            'covers' => array('purpose' => array('mail', 'mail_read'))),
        'microsoft_mail' => array('name' => 'Microsoft 365 mail', 'group' => 'email', 'place' => '#settings/mail_sending/pgset-mail-system', 'uses' => 'Sending the site\'s mail, and reading mailboxes',
            'state' => array('kind' => 'account', 'provider' => 'microsoft', 'purpose' => 'mail', 'attention' => true),
            'covers' => array('purpose' => array('mail', 'mail_read'))),
        'mailchimp' => array('name' => 'Mailchimp', 'group' => 'email', 'place' => '#settings/mail_sending/pgset-mailchimp', 'uses' => 'Keeps a list of subscribers in step',
            'state' => array('kind' => 'config', 'names' => array('MAILCHIMP'))),

        // SMS, consent and messaging.
        'netgsm' => array('name' => 'Netgsm', 'group' => 'messaging', 'place' => 'connections.php', 'found_at' => '#settings/contact/pgset-sms', 'uses' => 'SMS messages and codes',
            'state' => array('kind' => 'app', 'key' => 'sms_netgsm'),
            'covers' => array('sms' => array('netgsm'))),
        'iletimerkezi' => array('name' => 'İleti Merkezi', 'group' => 'messaging', 'place' => 'connections.php', 'found_at' => '#settings/contact/pgset-sms', 'uses' => 'SMS messages and codes',
            'state' => array('kind' => 'app', 'key' => 'sms_iletimerkezi'),
            'covers' => array('sms' => array('iletimerkezi'))),
        'twilio' => array('name' => 'Twilio', 'group' => 'messaging', 'place' => 'connections.php', 'found_at' => '#settings/contact/pgset-sms', 'uses' => 'SMS messages and codes',
            'state' => array('kind' => 'app', 'key' => 'sms_twilio'),
            'covers' => array('sms' => array('twilio'))),
        'iys' => array('name' => 'İYS', 'group' => 'messaging', 'place' => 'connections.php', 'found_at' => '#settings/contact/pgset-iys', 'uses' => 'Marketing consent records',
            'state' => array('kind' => 'app', 'key' => 'iys')),
        'whatsapp' => array('name' => 'WhatsApp', 'group' => 'messaging', 'place' => 'connections.php', 'found_at' => '#settings/contact/pgset-whatsapp', 'uses' => 'Template messages and replies into the Workspace',
            'state' => array('kind' => 'app', 'key' => 'whatsapp')),
        'slack' => array('name' => 'Slack', 'group' => 'messaging', 'place' => '#settings/maintenance/pgset-chat-notify', 'uses' => 'Operations alerts and Workspace events',
            'state' => array('kind' => 'notify', 'key' => 'slack'),
            'covers' => array('notify' => array('slack'))),
        'teams' => array('name' => 'Microsoft Teams', 'group' => 'messaging', 'place' => '#settings/maintenance/pgset-chat-notify', 'uses' => 'Operations alerts and Workspace events',
            'state' => array('kind' => 'notify', 'key' => 'teams'),
            'covers' => array('notify' => array('teams'))),
        'telegram' => array('name' => 'Telegram', 'group' => 'messaging', 'place' => '#settings/maintenance/pgset-chat-notify', 'uses' => 'Operations alerts and Workspace events',
            'state' => array('kind' => 'notify', 'key' => 'telegram'),
            'covers' => array('notify' => array('telegram'))),

        // Files and backups.
        'google_drive' => array('name' => 'Google Drive', 'group' => 'storage', 'place' => '#settings/maintenance/pgset-backup', 'uses' => 'Backups and the file picker',
            'state' => array('kind' => 'account', 'provider' => 'google', 'purpose' => 'backup', 'attention' => true),
            'apis' => array('drive.googleapis.com' => 'Google Drive API'),
            'covers' => array('picker' => array('google'), 'purpose' => array('backup', 'file_picker'))),
        'onedrive' => array('name' => 'OneDrive', 'group' => 'storage', 'place' => '#settings/maintenance/pgset-backup', 'uses' => 'Backups and the file picker',
            'state' => array('kind' => 'account', 'provider' => 'microsoft', 'purpose' => 'backup', 'attention' => true),
            'covers' => array('picker' => array('onedrive'))),
        'dropbox' => array('name' => 'Dropbox', 'group' => 'storage', 'place' => '#settings/maintenance/pgset-backup', 'uses' => 'Backups and the file picker',
            'state' => array('kind' => 'account', 'provider' => 'dropbox', 'purpose' => 'backup', 'attention' => true),
            'covers' => array('picker' => array('dropbox'), 'connect' => array('dropbox'))),

        // Sign-in. Google's and Microsoft's applications also serve the
        // rows above; each row is one product behind the account.
        'signin_google' => array('name' => 'Sign in with Google', 'group' => 'signin', 'place' => '#settings/security/pgset-signin', 'uses' => 'Visitors and staff sign in with Google',
            'state' => array('kind' => 'reader', 'function' => 'pg_signin_enabled', 'args' => array('google')),
            'covers' => array('connect' => array('google'))),
        'signin_microsoft' => array('name' => 'Sign in with Microsoft', 'group' => 'signin', 'place' => '#settings/security/pgset-signin', 'uses' => 'Visitors and staff sign in with Microsoft',
            'state' => array('kind' => 'reader', 'function' => 'pg_signin_enabled', 'args' => array('microsoft')),
            'covers' => array('connect' => array('microsoft'))),
        'signin_apple' => array('name' => 'Sign in with Apple', 'group' => 'signin', 'place' => '#settings/security/pgset-signin', 'uses' => 'Visitors sign in with Apple',
            'state' => array('kind' => 'reader', 'function' => 'pg_signin_enabled', 'args' => array('apple')),
            'covers' => array('connect' => array('apple'))),

        // Calendars and notes: each person connects their own account.
        'google_calendar' => array('name' => 'Google Calendar', 'group' => 'calendar', 'place' => '#settings/workspace_team/pgset-ws-calendar', 'uses' => 'A person\'s tasks in their calendar, and moves back',
            'state' => array('kind' => 'account', 'provider' => 'google', 'purpose' => 'calendar_sync', 'attention' => true),
            'apis' => array('calendar-json.googleapis.com' => 'Google Calendar API'),
            'covers' => array('purpose' => array('calendar_sync'))),
        'outlook_calendar' => array('name' => 'Outlook Calendar', 'group' => 'calendar', 'place' => '#settings/workspace_team/pgset-ws-calendar', 'uses' => 'A person\'s tasks in their calendar, and moves back',
            'state' => array('kind' => 'account', 'provider' => 'microsoft', 'purpose' => 'calendar_sync', 'attention' => true)),
        'onenote' => array('name' => 'OneNote', 'group' => 'calendar', 'place' => 'connections.php', 'uses' => 'A person brings OneNote pages in as Workspace notes',
            'state' => array('kind' => 'account', 'provider' => 'microsoft', 'purpose' => 'notes', 'attention' => true),
            'covers' => array('purpose' => array('notes'))),

        // Analytics and search.
        'google_analytics' => array('name' => 'Google Analytics', 'group' => 'analytics', 'place' => 'connections.php', 'found_at' => '#settings/privacy/pgset-analytics', 'uses' => 'The tag and the GA4 figures',
            'state' => array('kind' => 'account', 'provider' => 'google', 'purpose' => 'google_analytics', 'attention' => true),
            'apis' => array('analyticsadmin.googleapis.com' => 'Google Analytics Admin API', 'analyticsdata.googleapis.com' => 'Google Analytics Data API'),
            'covers' => array('purpose' => array('google_analytics'))),
        'search_console' => array('name' => 'Google Search Console', 'group' => 'analytics', 'place' => 'search_console.php', 'uses' => 'Search queries and clicks of the site\'s pages',
            'state' => array('kind' => 'account', 'provider' => 'google', 'purpose' => 'search_console', 'attention' => true),
            'apis' => array('searchconsole.googleapis.com' => 'Google Search Console API', 'siteverification.googleapis.com' => 'Google Site Verification API'),
            'covers' => array('purpose' => array('search_console'))),
        'indexnow' => array('name' => 'IndexNow', 'group' => 'analytics', 'place' => 'connections.php', 'found_at' => '#settings/seo/pgset-seo', 'uses' => 'Tells search engines a page changed',
            'state' => array('kind' => 'config', 'names' => array('INDEXNOW_KEY'))),

        // Maps and places.
        'google_places' => array('name' => 'Google Places', 'group' => 'maps', 'place' => '#settings/commerce/pgset-address-autocomplete', 'uses' => 'Address autocomplete',
            'state' => array('kind' => 'app', 'key' => 'addr_google_places'),
            'covers' => array('address' => array('google_places'))),
        'mapbox' => array('name' => 'Mapbox', 'group' => 'maps', 'place' => '#settings/commerce/pgset-address-autocomplete', 'uses' => 'Address autocomplete',
            'state' => array('kind' => 'app', 'key' => 'addr_mapbox'),
            'covers' => array('address' => array('mapbox'))),
        'photon' => array('name' => 'Photon', 'group' => 'maps', 'place' => '#settings/commerce/pgset-address-autocomplete', 'uses' => 'Address autocomplete',
            'state' => array('kind' => 'app', 'key' => 'addr_photon'),
            'covers' => array('address' => array('photon'))),
        'google_reviews' => array('name' => 'Google reviews', 'group' => 'maps', 'place' => '#settings/api/pgset-google-reviews', 'uses' => 'The place\'s reviews on the site',
            'state' => array('kind' => 'reader', 'function' => 'pg_grev_configured')),
        'dbip' => array('name' => 'DB-IP', 'group' => 'maps', 'place' => '#settings/firewall/pgset-countries', 'uses' => 'The country of a visitor\'s address',
            'state' => null, 'no_state' => 'A free database the site fetches; no account or key.'),

        // AI. A provider's key is its connection, typed on its page on
        // Connections (includes/fn/ai_connections.php).
        'pinegrap_ai' => array('name' => 'Pinegrap AI', 'group' => 'ai', 'place' => '#settings/workspace_assistants/pgset-ws-ai', 'uses' => 'Writing help and translation on the licence key',
            'state' => array('kind' => 'reader', 'function' => 'ws_ai_ready')),
        'claude' => array('name' => 'Claude', 'group' => 'ai', 'place' => '#settings/workspace_assistants/pgset-ws-claude', 'uses' => 'Claude in the channels, through a routine',
            'state' => array('kind' => 'reader', 'function' => 'ws_claude_ready')),
        'openai' => array('name' => 'OpenAI', 'group' => 'ai', 'place' => 'connections.php', 'found_at' => '#settings/workspace_assistants/pgset-ws-agents', 'uses' => 'Workspace assistants',
            'state' => array('kind' => 'reader', 'function' => 'pg_ai_connection_ready', 'args' => array('openai')),
            'covers' => array('model' => array('openai'))),
        'anthropic' => array('name' => 'Anthropic', 'group' => 'ai', 'place' => 'connections.php', 'found_at' => '#settings/workspace_assistants/pgset-ws-agents', 'uses' => 'Workspace assistants',
            'state' => array('kind' => 'reader', 'function' => 'pg_ai_connection_ready', 'args' => array('anthropic')),
            'covers' => array('model' => array('anthropic'))),
        'gemini' => array('name' => 'Google Gemini', 'group' => 'ai', 'place' => 'connections.php', 'found_at' => '#settings/workspace_assistants/pgset-ws-agents', 'uses' => 'Workspace assistants',
            'state' => array('kind' => 'reader', 'function' => 'pg_ai_connection_ready', 'args' => array('gemini')),
            'covers' => array('model' => array('gemini'))),
        'mistral' => array('name' => 'Mistral', 'group' => 'ai', 'place' => 'connections.php', 'found_at' => '#settings/workspace_assistants/pgset-ws-agents', 'uses' => 'Workspace assistants',
            'state' => array('kind' => 'reader', 'function' => 'pg_ai_connection_ready', 'args' => array('mistral')),
            'covers' => array('model' => array('mistral'))),
        'deepseek' => array('name' => 'DeepSeek', 'group' => 'ai', 'place' => 'connections.php', 'found_at' => '#settings/workspace_assistants/pgset-ws-agents', 'uses' => 'Workspace assistants',
            'state' => array('kind' => 'reader', 'function' => 'pg_ai_connection_ready', 'args' => array('deepseek')),
            'covers' => array('model' => array('deepseek'))),
        'openrouter' => array('name' => 'OpenRouter', 'group' => 'ai', 'place' => 'connections.php', 'found_at' => '#settings/workspace_assistants/pgset-ws-agents', 'uses' => 'Workspace assistants',
            'state' => array('kind' => 'reader', 'function' => 'pg_ai_connection_ready', 'args' => array('openrouter')),
            'covers' => array('model' => array('openrouter'))),
        'azure_openai' => array('name' => 'Azure OpenAI', 'group' => 'ai', 'place' => 'connections.php', 'found_at' => '#settings/workspace_assistants/pgset-ws-agents', 'uses' => 'Workspace assistants',
            'state' => array('kind' => 'reader', 'function' => 'pg_ai_connection_ready', 'args' => array('azure')),
            'covers' => array('model' => array('azure'))),
        'google_translate' => array('name' => 'Google Cloud Translation', 'group' => 'ai', 'place' => 'connections.php', 'found_at' => '#settings/languages/pgset-translation-engines', 'uses' => 'Translating pages and texts',
            'state' => array('kind' => 'config', 'names' => array('TRANSLATION_GOOGLE_KEY_ENC'))),

        // Protection and delivery.
        'cloudflare' => array('name' => 'Cloudflare', 'group' => 'protection', 'place' => 'cloudflare.php', 'uses' => 'Purging the CDN cache and its rules',
            'state' => array('kind' => 'config', 'names' => array('CLOUDFLARE_API_TOKEN', 'CLOUDFLARE_ZONE_ID'))),
        'turnstile' => array('name' => 'Cloudflare Turnstile', 'group' => 'protection', 'place' => '#settings/security/pgset-captcha', 'uses' => 'A check on forms against bots',
            'state' => array('kind' => 'app', 'key' => 'captcha_turnstile'),
            'covers' => array('captcha' => array('turnstile'))),
        'hcaptcha' => array('name' => 'hCaptcha', 'group' => 'protection', 'place' => '#settings/security/pgset-captcha', 'uses' => 'A check on forms against bots',
            'state' => array('kind' => 'app', 'key' => 'captcha_hcaptcha'),
            'covers' => array('captcha' => array('hcaptcha'))),
        'stopforumspam' => array('name' => 'StopForumSpam', 'group' => 'protection', 'place' => '#settings/security/pgset-captcha', 'uses' => 'Refuses forms from known spam addresses',
            'state' => array('kind' => 'reader', 'function' => 'pg_form_spam_enabled')),

        // Other.
        'github' => array('name' => 'GitHub', 'group' => 'other', 'place' => 'connections.php', 'uses' => 'Repository events into a channel',
            'state' => array('kind' => 'account', 'provider' => 'github', 'purpose' => 'channel_links', 'attention' => true),
            'covers' => array('connect' => array('github'), 'purpose' => array('channel_links'))),
        'activitypub' => array('name' => 'ActivityPub', 'group' => 'other', 'place' => '#settings/fediverse/pgset-fediverse', 'uses' => 'The site as an account of the fediverse',
            'state' => array('kind' => 'reader', 'function' => 'pg_ap_enabled')),
        'frankfurter' => array('name' => 'Frankfurter', 'group' => 'other', 'place' => '#settings/maintenance/pgset-cron', 'uses' => 'Exchange rates',
            'state' => null, 'no_state' => 'No account or key; the exchange-rate job asks it.'),
        'hexarate' => array('name' => 'HexaRate', 'group' => 'other', 'place' => '#settings/maintenance/pgset-cron', 'uses' => 'Exchange rates when Frankfurter has none',
            'state' => null, 'no_state' => 'No account or key; the exchange-rate job asks it.'),
        'pinegrap' => array('name' => 'Pinegrap licence and updates', 'group' => 'other', 'place' => '#settings/general/pgset-software', 'uses' => 'The licence, update checks and the update package',
            'state' => array('kind' => 'config', 'names' => array('SUBSCRIPTION_KEY'))),

        // Ready-made MCP connectors, counted apart (pg_connections_count()).
        'mcp_deepwiki' => array('name' => 'DeepWiki', 'group' => 'mcp', 'place' => '#settings/workspace_assistants/pgset-ws-mcp', 'uses' => 'Tools for the Workspace\'s assistants',
            'state' => array('kind' => 'mcp', 'key' => 'deepwiki'), 'covers' => array('mcp' => array('deepwiki'))),
        'mcp_context7' => array('name' => 'Context7', 'group' => 'mcp', 'place' => '#settings/workspace_assistants/pgset-ws-mcp', 'uses' => 'Tools for the Workspace\'s assistants',
            'state' => array('kind' => 'mcp', 'key' => 'context7'), 'covers' => array('mcp' => array('context7'))),
        'mcp_mslearn' => array('name' => 'Microsoft Learn', 'group' => 'mcp', 'place' => '#settings/workspace_assistants/pgset-ws-mcp', 'uses' => 'Tools for the Workspace\'s assistants',
            'state' => array('kind' => 'mcp', 'key' => 'mslearn'), 'covers' => array('mcp' => array('mslearn'))),
        'mcp_cloudflare_docs' => array('name' => 'Cloudflare Docs', 'group' => 'mcp', 'place' => '#settings/workspace_assistants/pgset-ws-mcp', 'uses' => 'Tools for the Workspace\'s assistants',
            'state' => array('kind' => 'mcp', 'key' => 'cloudflare_docs'), 'covers' => array('mcp' => array('cloudflare_docs'))),
        'mcp_huggingface' => array('name' => 'Hugging Face', 'group' => 'mcp', 'place' => '#settings/workspace_assistants/pgset-ws-mcp', 'uses' => 'Tools for the Workspace\'s assistants',
            'state' => array('kind' => 'mcp', 'key' => 'huggingface'), 'covers' => array('mcp' => array('huggingface'))),
        'mcp_linear' => array('name' => 'Linear', 'group' => 'mcp', 'place' => '#settings/workspace_assistants/pgset-ws-mcp', 'uses' => 'Tools for the Workspace\'s assistants',
            'state' => array('kind' => 'mcp', 'key' => 'linear'), 'covers' => array('mcp' => array('linear'))),
        'mcp_notion' => array('name' => 'Notion', 'group' => 'mcp', 'place' => '#settings/workspace_assistants/pgset-ws-mcp', 'uses' => 'Tools for the Workspace\'s assistants',
            'state' => array('kind' => 'mcp', 'key' => 'notion'), 'covers' => array('mcp' => array('notion'))),
        'mcp_sentry' => array('name' => 'Sentry', 'group' => 'mcp', 'place' => '#settings/workspace_assistants/pgset-ws-mcp', 'uses' => 'Tools for the Workspace\'s assistants',
            'state' => array('kind' => 'mcp', 'key' => 'sentry'), 'covers' => array('mcp' => array('sentry'))),
        'mcp_atlassian' => array('name' => 'Atlassian', 'group' => 'mcp', 'place' => '#settings/workspace_assistants/pgset-ws-mcp', 'uses' => 'Tools for the Workspace\'s assistants',
            'state' => array('kind' => 'mcp', 'key' => 'atlassian'), 'covers' => array('mcp' => array('atlassian'))),
        'mcp_vercel' => array('name' => 'Vercel', 'group' => 'mcp', 'place' => '#settings/workspace_assistants/pgset-ws-mcp', 'uses' => 'Tools for the Workspace\'s assistants',
            'state' => array('kind' => 'mcp', 'key' => 'vercel'), 'covers' => array('mcp' => array('vercel'))),
        'mcp_canva' => array('name' => 'Canva', 'group' => 'mcp', 'place' => '#settings/workspace_assistants/pgset-ws-mcp', 'uses' => 'Tools for the Workspace\'s assistants',
            'state' => array('kind' => 'mcp', 'key' => 'canva'), 'covers' => array('mcp' => array('canva'))),
        'mcp_github' => array('name' => 'GitHub', 'group' => 'mcp', 'place' => '#settings/workspace_assistants/pgset-ws-mcp', 'uses' => 'Tools for the Workspace\'s assistants',
            'state' => array('kind' => 'mcp', 'key' => 'github'), 'covers' => array('mcp' => array('github'))),
    );

    foreach (pg_connections_transfer_fields() as $id => $fields) {
        if (isset($rows[$id])) {
            $rows[$id]['country'] = $fields[0];
            $rows[$id]['data'] = $fields[1];
        }
    }

    return $rows;
}
