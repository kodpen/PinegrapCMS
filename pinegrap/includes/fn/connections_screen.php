<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Connections screen's list (connections.php): the state of every row
 * of the registry of outside services (pg_connections_registry()) read from
 * what the site has stored, the order a group is read in, the search, the
 * state filter, which rows a user may see, and how far a long group is
 * folded. The state of a row is worked out by a pure function from facts
 * read once per screen (pg_connections_screen_facts()); nothing here calls
 * an outside service.
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
 * The states a row can be in, in the order a group is read: what needs
 * attention, what is connected, what is not, what is switched off, what
 * shows no state. Pure.
 *
 * @return array state => rank
 */
function pg_connections_screen_state_ranks()
{
    return array('attention' => 0, 'connected' => 1, 'not_connected' => 2, 'off' => 3, 'none' => 4);
}

/**
 * The filter a state is counted under: the three states of the registry.
 * An application switched off is not connected (its pill says why); a row
 * whose state cannot be read is listed only under All. Pure.
 *
 * @param string $state
 * @return string attention, connected, not_connected, or '' for none
 */
function pg_connections_screen_filter_of($state)
{
    if ($state === 'off') {
        return 'not_connected';
    }

    return in_array($state, array('attention', 'connected', 'not_connected'), true) ? $state : '';
}

/**
 * A row's state from the facts the screen read. Pure.
 *
 * @param array $row   a row of pg_connections_registry()
 * @param array $facts see pg_connections_screen_facts()
 * @return string a key of pg_connections_screen_state_ranks()
 */
function pg_connections_screen_state($row, $facts)
{
    $state = isset($row['state']) ? $row['state'] : null;

    if (!is_array($state) || !isset($state['kind'])) {
        return 'none';
    }

    $fact = function ($list, $key) use ($facts) {
        return isset($facts[$list][$key]) ? $facts[$list][$key] : null;
    };

    switch ($state['kind']) {

        case 'account':
            $active = false;
            $reauth = false;

            foreach ((isset($facts['accounts']) ? $facts['accounts'] : array()) as $account) {
                if (($account['provider'] !== $state['provider']) || !in_array($state['purpose'], (array) $account['purposes'], true)) {
                    continue;
                }

                if ($account['status'] === 'reauth') {
                    $reauth = true;
                } elseif ($account['status'] === 'active') {
                    $active = true;
                }
            }

            if ($reauth && !empty($state['attention'])) {
                return 'attention';
            }

            return ($active || $reauth) ? 'connected' : 'not_connected';

        case 'app':
            $enabled = $fact('apps', $state['key']);

            if ($enabled === null) {
                return 'not_connected';
            }

            return $enabled ? 'connected' : 'off';

        case 'config':
            $names = (array) $state['names'];

            foreach ($names as $name) {
                $value = $fact('config', $name);

                if (($value === null) || ((string) $value === '') || ($value === false)) {
                    return 'not_connected';
                }
            }

            if (isset($state['equals']) && ((string) $fact('config', $names[0]) !== (string) $state['equals'])) {
                return 'not_connected';
            }

            return 'connected';

        case 'reader':
            return $fact('readers', pg_connections_screen_reader_key($state)) ? 'connected' : 'not_connected';

        case 'edoc':
            return (isset($facts['edoc']) && ($facts['edoc'] === $state['key'])) ? 'connected' : 'not_connected';

        case 'service':
            $list = 'services';
            break;

        case 'marketplace':
            $list = 'marketplaces';
            break;

        case 'model':
            $list = 'models';
            break;

        case 'mcp':
            $list = 'mcp';
            break;

        case 'notify':
            $list = 'notify';
            break;

        default:
            return 'none';
    }

    return $fact($list, $state['key']) ? 'connected' : 'not_connected';
}

/**
 * The key a reader's answer is kept under in the facts. Pure.
 *
 * @param array $state a reader of kind 'reader'
 * @return string
 */
function pg_connections_screen_reader_key($state)
{
    return (string) $state['function'] . '(' . implode(',', array_map('strval', isset($state['args']) ? (array) $state['args'] : array())) . ')';
}

/**
 * The state of an account provider's row: its own accounts (the site's) and
 * its application. Pure.
 *
 * @param array  $accounts the provider's accounts of the site (status)
 * @param string $app      ready, off or not_set_up
 * @return string
 */
function pg_connections_screen_provider_state($accounts, $app)
{
    $active = false;

    foreach ((array) $accounts as $account) {
        if ($account['status'] === 'reauth') {
            return 'attention';
        }

        if ($account['status'] === 'active') {
            $active = true;
        }
    }

    if ($active) {
        return 'connected';
    }

    return ($app === 'off') ? 'off' : 'not_connected';
}

/**
 * The connected accounts a service's account reader stands on: the
 * provider's accounts holding its purpose, whoever owns them. Pure.
 *
 * @param array|null $state    a row's 'state'
 * @param array      $accounts pg_connect_accounts() rows
 * @return array list of accounts, in the given order
 */
function pg_connections_screen_accounts_for($state, $accounts)
{
    if (!is_array($state) || !isset($state['kind']) || ($state['kind'] !== 'account')) {
        return array();
    }

    $found = array();

    foreach ((array) $accounts as $account) {
        if (($account['provider'] === $state['provider']) && in_array($state['purpose'], (array) $account['purposes'], true)) {
            $found[] = $account;
        }
    }

    return $found;
}

/**
 * The last error on file among some accounts: a stopped account's first,
 * else any account's kept error; '' when none is stored. Pure.
 *
 * @param array $accounts
 * @return string
 */
function pg_connections_screen_last_error($accounts)
{
    $kept = '';

    foreach ((array) $accounts as $account) {
        $error = isset($account['last_error']) ? (string) $account['last_error'] : '';

        if ($error === '') {
            continue;
        }

        if ($account['status'] === 'reauth') {
            return $error;
        }

        if ($kept === '') {
            $kept = $error;
        }
    }

    return $kept;
}

/**
 * Whether the site stores a service's last error: only a connected account
 * keeps one (connect_accounts.last_error); no other reader has a place for
 * it, and none is added. Pure.
 *
 * @param array|null $state a row's 'state'
 * @return bool
 */
function pg_connections_screen_keeps_errors($state)
{
    return is_array($state) && isset($state['kind']) && ($state['kind'] === 'account');
}

/**
 * An account's address as HTML whose line breaks after its "@" when the
 * column is too narrow. A <wbr> adds no character, so a copy and a screen
 * reader still get the one address. Pure.
 *
 * @param string $text plain text, an address or a sentence holding one
 * @return string escaped HTML
 */
function pg_connections_screen_address_html($text)
{
    return str_replace('@', '@<wbr>', h($text));
}

/**
 * What connects to the site, as Application Access counts it: applications
 * (keys, the documentation's temporary ones left out), team devices signed
 * in, applications people connected with OAuth, webhook subscriptions, and
 * the errors those applications met in the last 24 hours. A table not yet
 * installed counts none.
 *
 * @param int|null $now the time the day ends at, the clock when left out
 * @return array applications, devices, connected, webhooks, errors (int)
 */
function pg_connections_screen_other_side($now = null)
{
    $count = function ($table, $column, $sql) {
        return (function_exists('waf_table_has_column') && waf_table_has_column($table, $column)) ? (int) db_value($sql) : 0;
    };

    return array(
        'applications' => $count('api_apps', 'id', "SELECT COUNT(*) FROM api_apps WHERE name NOT LIKE '\\_\\_test\\_\\_%'"),
        'devices'      => $count('api_devices', 'oauth_client_id', "SELECT COUNT(*) FROM api_devices WHERE oauth_client_id IS NULL OR oauth_client_id = 0"),
        'connected'    => $count('oauth_clients', 'id', "SELECT COUNT(*) FROM oauth_clients"),
        'webhooks'     => $count('api_webhooks', 'id', "SELECT COUNT(*) FROM api_webhooks"),
        'errors'       => $count('api_request_log', 'status_code', pg_connections_screen_errors_sql(($now === null) ? time() : (int) $now)),
    );
}

/**
 * The query that counts the errors of the day: the requests of the last 24
 * hours that Application Access's list counts as failures (an answer of 400
 * or above), of the applications it lists, the documentation's temporary
 * ones left out. A refused request that named no application is no
 * application's error. Pure.
 *
 * @param int $now the time the day ends at
 * @return string
 */
function pg_connections_screen_errors_sql($now)
{
    return "SELECT COUNT(*) FROM api_request_log
        INNER JOIN api_apps ON api_apps.id = api_request_log.app_id
        WHERE api_request_log.timestamp >= " . ((int) $now - 86400) . "
            AND api_request_log.status_code >= 400
            AND api_apps.name NOT LIKE '\\_\\_test\\_\\_%'";
}

/**
 * Whether a user may open a row's place, so may see the row: a card of Site
 * Settings in a category they may open, a System tools screen their tools
 * list holds, or one of pg_connections_other_screens() for a manager. Pure.
 *
 * @param string $place      as the registry writes it
 * @param array  $categories category key => true, those the user may open
 * @param array  $tools      script => true, the System tools screens they see
 * @param bool   $manager    the user is a manager or above
 * @return bool
 */
function pg_connections_screen_may_see($place, $categories, $tools, $manager)
{
    $place = (string) $place;

    if (preg_match('~^#settings/([a-z_]+)/~', $place, $match)) {
        return !empty($categories[$match[1]]);
    }

    if (isset($tools[$place])) {
        return true;
    }

    return $manager && in_array($place, pg_connections_other_screens(), true);
}

/**
 * The rows in the order they are read: the groups in the given order, and
 * inside a group needs attention, connected, not connected, off, no state,
 * each in the registry's own order. Pure.
 *
 * @param array $rows   id => row with 'group' and 'state'
 * @param array $groups the group keys in their order
 * @return array group => list of ids, groups without a row left out
 */
function pg_connections_screen_order($rows, $groups)
{
    $ranks = pg_connections_screen_state_ranks();
    $ordered = array();

    foreach ($groups as $group) {
        $ids = array();
        $position = 0;

        foreach ($rows as $id => $row) {
            if ($row['group'] === $group) {
                $rank = isset($ranks[$row['state']]) ? $ranks[$row['state']] : count($ranks);
                $ids[] = array($rank, $position++, $id);
            }
        }

        if (!$ids) {
            continue;
        }

        usort($ids, function ($a, $b) {
            return ($a[0] !== $b[0]) ? ($a[0] - $b[0]) : ($a[1] - $b[1]);
        });

        $ordered[$group] = array_map(function ($item) {
            return $item[2];
        }, $ids);
    }

    return $ordered;
}

/**
 * A text as the search compares it: lower case, Turkish letters folded to
 * their plain Latin ones, so "parasut" finds Paraşüt and "iys" finds İYS.
 * Pure.
 *
 * @param string $text
 * @return string
 */
function pg_connections_screen_fold($text)
{
    $text = str_replace(array('İ', 'I'), 'i', (string) $text);
    $text = mb_strtolower($text, 'UTF-8');

    return strtr($text, array(
        'ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u',
        'â' => 'a', 'î' => 'i', 'û' => 'u', 'é' => 'e',
    ));
}

/**
 * The rows a search finds: every word of the query is in the row's name,
 * group, place or uses (any of its 'search' texts). Pure.
 *
 * @param array  $rows  id => row with 'search' (list of texts)
 * @param string $query
 * @return array id => row, in the given order
 */
function pg_connections_screen_search($rows, $query)
{
    $words = preg_split('/\s+/u', trim(pg_connections_screen_fold($query)), -1, PREG_SPLIT_NO_EMPTY);

    if (!$words) {
        return $rows;
    }

    $found = array();

    foreach ($rows as $id => $row) {
        $haystack = pg_connections_screen_fold(implode(' ', isset($row['search']) ? (array) $row['search'] : array()));
        $all = true;

        foreach ($words as $word) {
            if (mb_strpos($haystack, $word, 0, 'UTF-8') === false) {
                $all = false;
                break;
            }
        }

        if ($all) {
            $found[$id] = $row;
        }
    }

    return $found;
}

/**
 * The rows of one filter: '' keeps every row. Pure.
 *
 * @param array  $rows   id => row with 'state'
 * @param string $filter '', attention, connected or not_connected
 * @return array
 */
function pg_connections_screen_filter($rows, $filter)
{
    if ((string) $filter === '') {
        return $rows;
    }

    return array_filter($rows, function ($row) use ($filter) {
        return pg_connections_screen_filter_of($row['state']) === $filter;
    });
}

/**
 * How many rows each filter holds. Pure.
 *
 * @param array $rows id => row with 'state'
 * @return array all, attention, connected, not_connected, none
 */
function pg_connections_screen_counts($rows)
{
    $counts = array('all' => count($rows), 'attention' => 0, 'connected' => 0, 'not_connected' => 0, 'none' => 0);

    foreach ($rows as $row) {
        $filter = pg_connections_screen_filter_of($row['state']);
        $counts[($filter === '') ? 'none' : $filter]++;
    }

    return $counts;
}

/**
 * How many rows of a group show before "Show all": every row that needs
 * attention or is connected, at least two, and the whole group when only
 * one would be left behind. Pure.
 *
 * @param array $states the group's states in their order
 * @return int
 */
function pg_connections_screen_fold_at($states)
{
    $total = count($states);
    $shown = 0;

    foreach ($states as $state) {
        if (in_array($state, array('attention', 'connected'), true)) {
            $shown++;
        }
    }

    $shown = max(2, $shown);

    return (($total - $shown) <= 1) ? $total : $shown;
}

/**
 * The mail routes the list shows as one row, E-mail sending's: the sending
 * services and the two accounts the System card chooses. Mailchimp keeps
 * its own row. Pure.
 *
 * @return array registry keys
 */
function pg_connections_screen_mail_routes()
{
    return array('postmark', 'mailgun', 'brevo', 'resend', 'ses', 'gmail', 'microsoft_mail');
}

/**
 * The list's rows with the mail routes folded into one, keyed mail_sending,
 * at the first route's place: its state the most urgent of theirs, its
 * search every route's texts besides its own, its uses their names when it
 * brings none. Without a route the rows come back as they were. Pure.
 *
 * @param array $rows id => row with 'name', 'state' and 'search'
 * @param array $row  the folded row: kind, name, group, line, url, search
 * @return array id => row
 */
function pg_connections_screen_fold_mail($rows, $row)
{
    $routes = array_flip(pg_connections_screen_mail_routes());
    $ranks = pg_connections_screen_state_ranks();
    $folded = array();
    $state = '';
    $names = array();
    $search = isset($row['search']) ? (array) $row['search'] : array();

    foreach ($rows as $id => $item) {

        if (!isset($routes[$id])) {
            $folded[$id] = $item;
            continue;
        }

        if (!$names) {
            $folded['mail_sending'] = null;
        }

        $rank = isset($ranks[$item['state']]) ? $ranks[$item['state']] : count($ranks);

        if (($state === '') || ($rank < (isset($ranks[$state]) ? $ranks[$state] : count($ranks)))) {
            $state = $item['state'];
        }

        $names[] = $item['name'];
        $search = array_merge($search, isset($item['search']) ? (array) $item['search'] : array());
    }

    if (!$names) {
        return $rows;
    }

    $row['state'] = $state;
    $row['search'] = $search;

    if (!isset($row['uses']) || ($row['uses'] === '')) {
        $row['uses'] = implode(', ', $names);
    }

    $folded['mail_sending'] = $row;

    return $folded;
}

/**
 * The one action a row of the list offers: Connect while nothing is set
 * up, Manage for every other state. Pure.
 *
 * @param string $state a row's state (pg_connections_screen_state())
 * @return string connect or manage
 */
function pg_connections_screen_action($state)
{
    return ($state === 'not_connected') ? 'connect' : 'manage';
}

/**
 * The address of a service's own page on the Connections screen. Pure.
 *
 * @param string $id the service's registry key
 * @return string
 */
function pg_connections_screen_page_url($id)
{
    return 'connections.php?service=' . rawurlencode((string) $id);
}

/**
 * The ready-made MCP connector a page is of: the catalogue key its
 * registry row covers (ws_mcp_catalogue()), '' for any other page. Pure.
 *
 * @param mixed $id a page key as it came
 * @return string
 */
function pg_connections_screen_mcp_connector($id)
{
    if (!is_string($id) || ($id === '')) {
        return '';
    }

    $registry = pg_connections_registry();
    $row = isset($registry[$id]) ? $registry[$id] : null;

    if (!is_array($row) || ($row['group'] !== 'mcp') || empty($row['covers']['mcp'][0])) {
        return '';
    }

    return (string) $row['covers']['mcp'][0];
}

/**
 * The service a page address asks for: its registry key when it is one of
 * the rows the user may see and a service of the registry (an account
 * provider keeps its drawer), else ''. Pure.
 *
 * @param mixed $asked the service parameter as it came
 * @param array $rows  the screen's rows, id => row with 'kind'
 * @return string
 */
function pg_connections_screen_page_id($asked, $rows)
{
    if (!is_string($asked) || !preg_match('/^[a-z0-9_]{1,60}$/', $asked)) {
        return '';
    }

    if (pg_connections_screen_provider_page($asked, $rows) !== '') {
        return $asked;
    }

    return (isset($rows[$asked]['kind']) && ($rows[$asked]['kind'] === 'service')) ? $asked : '';
}

/**
 * The account provider a page is of when no registry service carries the
 * page's key (Google, Microsoft: the provider's own page, its application
 * and every account it connects), else ''. Pure.
 *
 * @param string $id   a page key
 * @param array  $rows the screen's rows, id => row with 'kind'
 * @return string the provider key
 */
function pg_connections_screen_provider_page($id, $rows)
{
    $providers = pg_connections_screen_page_providers();

    if (!isset($providers[$id]) || isset($rows[$id])) {
        return '';
    }

    $row = 'provider_' . $providers[$id];

    return (isset($rows[$row]['kind']) && ($rows[$row]['kind'] === 'provider')) ? $providers[$id] : '';
}

/**
 * The services of the registry an account provider serves, among the rows
 * the user may see: those whose state is read from its accounts and those
 * covering it as a sign-in provider, in the registry's order. Pure.
 *
 * @param string $provider a provider key
 * @param array  $registry pg_connections_registry()
 * @param array  $rows     the screen's rows, id => row
 * @return array list of registry keys
 */
function pg_connections_screen_provider_uses($provider, $registry, $rows)
{
    $out = array();

    foreach ((array) $registry as $id => $service) {

        $state = isset($service['state']) && is_array($service['state']) ? $service['state'] : array();
        $by_account = (isset($state['kind'], $state['provider']) && ($state['kind'] === 'account') && ($state['provider'] === $provider));
        $by_cover = isset($service['covers']['connect']) && in_array($provider, (array) $service['covers']['connect'], true);

        if (($by_account || $by_cover) && isset($rows[$id])) {
            $out[] = (string) $id;
        }

    }

    return $out;
}

/**
 * The pages that hold an account provider's application ("Connection
 * details"), by page key: a registry service's page (GitHub) or, where no
 * service carries the key, the provider's own page (Google, Microsoft).
 * That provider's row on the list opens the page instead of a drawer. Pure.
 *
 * @return array page key => provider key
 */
function pg_connections_screen_page_providers()
{
    return array('github' => 'github', 'google' => 'google', 'microsoft' => 'microsoft');
}

/**
 * The account provider whose application a service's page asks for itself
 * (Google, Microsoft), else '': a service whose state is read from that
 * provider's accounts, on a page that is not the provider's own, while the
 * application is not ready, for a manager. Its fields are the provider
 * page's, saved to the same place, so the service's page finishes the
 * set-up: the application, then the sign-in, then the account. Pure.
 *
 * @param string $id      the service's registry key
 * @param mixed  $service its registry entry
 * @param array  $ready   provider key => bool (pg_connect_app_ready()); a
 *                        provider missing counts as not ready
 * @param bool   $manager may connect the site's accounts
 * @return string the provider key
 */
function pg_connections_screen_app_here($id, $service, $ready, $manager)
{
    $page_providers = pg_connections_screen_page_providers();

    if (!$manager || isset($page_providers[$id]) || !is_array($service) || !isset($service['state']) || !is_array($service['state'])) {
        return '';
    }

    $state = $service['state'];

    if (!isset($state['kind'], $state['provider']) || ($state['kind'] !== 'account')) {
        return '';
    }

    $provider = (string) $state['provider'];

    // Only a provider whose own page holds its application (Google,
    // Microsoft); GitHub's is a service's page, Dropbox has none.
    if (!isset($page_providers[$provider]) || ($page_providers[$provider] !== $provider) || ($provider === 'github')) {
        return '';
    }

    return (is_array($ready) && !empty($ready[$provider])) ? '' : $provider;
}

/**
 * The card gateways whose keys are typed on their own page (the keys of
 * pg_payment_gateway_connections()). Pure.
 *
 * @return array page keys
 */
function pg_connections_screen_gateway_pages()
{
    return array('iyzico', 'stripe_classic', 'payflow_pro', 'payments_pro', 'authorize_net', 'clearcommerce', 'first_data', 'sage');
}

/**
 * The payment drivers whose keys are typed on their own page
 * (pg_payment_driver_pages()). Pure.
 *
 * @return array page keys
 */
function pg_connections_screen_driver_pages()
{
    return array('stripe', 'paypal', 'paytr');
}

/**
 * Every page whose keys came from Site Settings › Ecommerce: the card
 * gateways, the payment drivers, Givex (pg_payment_method_connections();
 * PayPal Express Checkout's keys are on PayPal's page), the carrier label
 * services, UPS, FedEx and USPS, the address autocomplete providers, and
 * Google Merchant Center (its account, from the Product Feeds card). Pure.
 *
 * @return array page keys
 */
function pg_connections_screen_commerce_pages()
{
    return array_merge(pg_connections_screen_gateway_pages(), pg_connections_screen_driver_pages(), array('givex'),
        pg_connections_screen_label_pages(), pg_connections_screen_carrier_pages(),
        pg_connections_screen_address_pages(), array('merchant_center'));
}

/**
 * The e-document providers whose connection is set on their own page, from
 * the E-Invoice card of Site Settings › Accounting: each registry key is the
 * driver's code (erp_edoc_drivers()). Pure.
 *
 * @return array page keys
 */
function pg_connections_screen_accounting_pages()
{
    return array('parasut', 'isbasi');
}

/**
 * The address autocomplete providers whose key or server is typed on their
 * own page (the keys of pg_addr_providers()). Pure.
 *
 * @return array page keys
 */
function pg_connections_screen_address_pages()
{
    return array('google_places', 'mapbox', 'photon');
}

/**
 * The carrier label services whose keys are typed on their own page (the
 * shipped drivers of pg_label_builtin_drivers()). Pure.
 *
 * @return array page keys
 */
function pg_connections_screen_label_pages()
{
    return array('geliver', 'basitkargo', 'shippo', 'sendcloud', 'aras', 'kargonomi');
}

/**
 * The carriers whose API application is typed on their own page (the keys
 * of pg_carrier_api_definitions()). Pure.
 *
 * @return array page keys
 */
function pg_connections_screen_carrier_pages()
{
    return array('ups', 'fedex', 'usps');
}

/**
 * The cards of a service's page, in their order: "status", on Google's
 * and Microsoft's pages "mailboxes" (Connect an account for mailboxes, for
 * a manager who may open the Mailboxes settings, drawn inside Status), on
 * Google's page "signin" (the Sign in with Google application, for who may open the
 * Accounts and sign-in settings, drawn inside Connection details above the
 * connected accounts' application that falls back to it; on Microsoft's,
 * Sign in with Microsoft's part over the one application both use), "app"
 * (an account service's provider application while it is missing, for a
 * manager: pg_connections_screen_app_here()), "details" (its
 * provider's application, for a manager; an AI provider's key, for who may
 * change the assistants; IndexNow's key, for who may open Site Settings ›
 * SEO; Google Analytics' tag and figures, for who may open Visitors and
 * privacy; the Google Cloud Translation key, for who may open Languages and
 * Translation; the WhatsApp, İYS and SMS providers' accounts, for who may
 * open Communication; Sign in with Apple's application and the CAPTCHA
 * providers' keys, for who may open Accounts and sign-in; a card
 * gateway's, a payment driver's, a shipping service's or an address
 * provider's keys and Merchant Center's account, for who may open
 * Ecommerce; an e-document provider's connection, for who may open
 * Accounting; Google reviews' key and Place ID, for who may open
 * Integrations), "uses", then
 * "repositories" on
 * GitHub's page for a holder of the Workspace settings right. Each card
 * keeps the gate of the place it came from; a card the viewer may not open
 * is left out. Pure.
 *
 * @param string $id  the service's registry key
 * @param array  $can 'manager' (bool), 'repositories' (bool: may manage
 *                    the GitHub repository links), 'ai' (bool: may change
 *                    the assistants, connections_ai_may_manage()), 'signin'
 *                    (bool: may open Accounts and sign-in: Google's
 *                    sign-in application, Apple's application),
 *                    'security' (bool: may open Accounts and sign-in, the
 *                    CAPTCHA providers' keys), 'commerce' (bool: may
 *                    open Ecommerce, a payment or shipping service's
 *                    keys), 'seo' (bool: may
 *                    open the SEO settings, IndexNow's key), 'privacy' (bool:
 *                    may open Visitors and privacy, Google Analytics' tag
 *                    and figures), 'languages' (bool: may open Languages
 *                    and Translation, the Google Cloud Translation key),
 *                    'contact' (bool: may open Communication, the
 *                    WhatsApp, İYS and SMS providers' accounts),
 *                    'mailboxes' (bool: may open Mailboxes, the accounts
 *                    that read mailboxes), 'accounting' (bool: may open
 *                    Accounting, Paraşüt's and Logo İşbaşı's connection),
 *                    'xero' (bool: may open Accounting, Xero's
 *                    application, organisation and accounts),
 *                    'quickbooks' (bool: may open Accounting,
 *                    QuickBooks' application, company and accounts),
 *                    'integrations' (bool: may open Integrations, Google
 *                    reviews' key and Place ID), 'maintenance' (bool:
 *                    may open Updates and maintenance, connecting Google
 *                    Drive, OneDrive and Dropbox accounts for backups),
 *                    'mail_sending' (bool: may open E-mail › Sending,
 *                    Mailchimp's key and sync), 'chat_notify' (bool: may
 *                    open Updates and maintenance, Slack's, Teams' and
 *                    Telegram's targets), 'claude' (bool: may open
 *                    Assistants and agents as the Workspace's
 *                    administrator, Claude's routine address and token),
 *                    'mcp' (bool: the same, a ready-made MCP connector's
 *                    server: its address and sign-in), 'app' (bool: the
 *                    provider's application is asked on this page,
 *                    pg_connections_screen_app_here())
 * @return array list of card keys
 */
function pg_connections_screen_page_cards($id, $can)
{
    $cards = array('status');

    // connect.php connects the site's own accounts for a manager only.
    if (in_array($id, array('google', 'microsoft'), true) && !empty($can['manager']) && !empty($can['mailboxes'])) {
        $cards[] = 'mailboxes';
    }

    if (in_array($id, array('google', 'microsoft'), true) && !empty($can['signin'])) {
        $cards[] = 'signin';
    }

    // connect.php connects the site's accounts for a manager only; which
    // account a destination uses stays on the Backups card.
    if ((pg_backup_cloud_type_of_service($id) !== '') && !empty($can['manager']) && !empty($can['maintenance'])) {
        $cards[] = 'backups';
    }

    // The provider's application asked on an account service's page while
    // it is missing (pg_connections_screen_app_here()); a page holding an
    // application of its own never shows a second one.
    if (!empty($can['manager']) && !empty($can['app']) && !isset(pg_connections_screen_page_providers()[$id])) {
        $cards[] = 'app';
    }

    if (!empty($can['manager']) && isset(pg_connections_screen_page_providers()[$id])) {
        $cards[] = 'details';
    } elseif (!empty($can['ai']) && isset(pg_ai_connection_services()[$id])) {
        $cards[] = 'details';
    } elseif (($id === 'indexnow') && !empty($can['seo'])) {
        $cards[] = 'details';
    } elseif (($id === 'google_analytics') && !empty($can['privacy'])) {
        $cards[] = 'details';
    } elseif (($id === 'google_translate') && !empty($can['languages'])) {
        $cards[] = 'details';
    } elseif (in_array($id, array('whatsapp', 'iys', 'netgsm', 'iletimerkezi', 'twilio'), true) && !empty($can['contact'])) {
        $cards[] = 'details';
    } elseif (($id === 'mailchimp') && !empty($can['mail_sending'])) {
        $cards[] = 'details';
    } elseif (($id === 'signin_apple') && !empty($can['signin'])) {
        $cards[] = 'details';
    } elseif (in_array($id, array('turnstile', 'hcaptcha'), true) && !empty($can['security'])) {
        $cards[] = 'details';
    } elseif (in_array($id, pg_connections_screen_commerce_pages(), true) && !empty($can['commerce'])) {
        $cards[] = 'details';
    } elseif (in_array($id, pg_connections_screen_accounting_pages(), true) && !empty($can['accounting'])) {
        $cards[] = 'details';
    } elseif (($id === 'xero') && !empty($can['xero'])) {
        $cards[] = 'details';
    } elseif (($id === 'quickbooks') && !empty($can['quickbooks'])) {
        $cards[] = 'details';
    } elseif (($id === 'google_reviews') && !empty($can['integrations'])) {
        $cards[] = 'details';
    } elseif (in_array($id, array('slack', 'teams', 'telegram'), true) && !empty($can['chat_notify'])) {
        $cards[] = 'details';
    } elseif (($id === 'claude') && !empty($can['claude'])) {
        $cards[] = 'details';
    } elseif (($id === 'figma') && !empty($can['figma'])) {
        $cards[] = 'details';
    } elseif ((pg_connections_screen_mcp_connector($id) !== '') && !empty($can['mcp'])) {
        $cards[] = 'details';
    }

    $cards[] = 'uses';

    if (($id === 'github') && !empty($can['repositories'])) {
        $cards[] = 'repositories';
    }

    return $cards;
}

/**
 * Whether a service's page holds settings of its own, typed on it (keys,
 * accounts, targets), whether or not the viewer may change them: then its
 * page never says "here only its state is shown". Pure.
 *
 * @param string $id    the service's registry key
 * @param array  $extra more such pages the caller knows (the AI providers
 *                      of pg_ai_connection_services(), the ad services of
 *                      pg_ad_conversion_services())
 * @return bool
 */
function pg_connections_screen_page_here($id, $extra)
{
    $id = (string) $id;

    return in_array($id, (array) $extra, true)
        || in_array($id, array('indexnow', 'google_analytics', 'google_translate', 'whatsapp', 'iys', 'mailchimp', 'netgsm', 'iletimerkezi', 'twilio',
            'signin_apple', 'turnstile', 'hcaptcha', 'google_reviews', 'slack', 'teams', 'telegram', 'claude', 'figma', 'xero', 'quickbooks'), true)
        || in_array($id, pg_connections_screen_commerce_pages(), true)
        || in_array($id, pg_connections_screen_accounting_pages(), true)
        || (pg_connections_screen_mcp_connector($id) !== '');
}

/**
 * Whether a row of the Connections list opens the service's card (or its
 * screen) straight away instead of its page: the page would only show its
 * state and "Open its settings", one hop for nothing. Its cards are Status
 * and Where it is used alone, it holds no settings of its own, it is no
 * provider's page, and its place is a screen of the panel other than the
 * Connections list. The page's own address still answers. Pure.
 *
 * @param string $id    the service's registry key
 * @param array  $cards its page's cards for the viewer (pg_connections_screen_page_cards())
 * @param bool   $here  pg_connections_screen_page_here()
 * @param string $url   the place of its settings (a panel screen's address)
 * @return bool
 */
function pg_connections_screen_opens_card($id, $cards, $here, $url)
{
    if ($here || isset(pg_connections_screen_page_providers()[(string) $id])) {
        return false;
    }

    if (array_diff((array) $cards, array('status', 'uses')) !== array()) {
        return false;
    }

    $url = (string) $url;

    // A panel screen's relative address only, never the list itself.
    return ($url !== '') && (bool) preg_match('~^[a-z0-9_]+\.php(?:[?#]|$)~D', $url) && (strpos($url, 'connections.php') !== 0);
}

/**
 * The Site Settings card a lead to a service's page came from (from=), by
 * the settings registry's keys: category, card and, where there is one, a
 * drawer's key. Never an address: the page builds the way back from these
 * keys with pg_settings_link(). A category the reader may not open, or a
 * card it does not hold, is dropped. Pure.
 *
 * @param mixed $asked      <category>/<card>[/<drawer>]
 * @param array $categories the categories the reader may open
 *                          (pg_settings_categories_for()): key => label, sections
 * @return array|null category, card, drawer, label (the card's title), value
 */
function pg_connections_back($asked, $categories)
{
    if (!is_string($asked) || !preg_match('~^([a-z_]{1,40})/(pgset-[a-z0-9\-]{1,60})(?:/([a-z0-9_\-]{1,60}))?$~D', $asked, $match)) {
        return null;
    }

    if (!is_array($categories) || !isset($categories[$match[1]]['sections'][$match[2]])) {
        return null;
    }

    return array(
        'category' => $match[1],
        'card'     => $match[2],
        'drawer'   => isset($match[3]) ? $match[3] : '',
        'label'    => (string) $categories[$match[1]]['sections'][$match[2]],
        'value'    => $asked,
    );
}

/**
 * The address of a lead from a Site Settings card to a service's page of
 * Connections, carrying the card it came from, in the shape connect.php
 * keeps (pg_connect_return_path()). Every lead from a card uses it, in the
 * same tab. Pure.
 *
 * @param string $service  the service's registry key
 * @param string $category the card's category, '' for no return
 * @param string $card     the card's anchor (pgset-…)
 * @param string $anchor   a part of the page to open at, without '#'
 * @param string $drawer   a drawer of the card the lead sits in
 * @return string
 */
function pg_connections_lead_url($service, $category, $card, $anchor = '', $drawer = '')
{
    $from = ($category !== '' && $card !== '') ? $category . '/' . $card . (($drawer !== '') ? '/' . $drawer : '') : '';

    return pg_connections_screen_page_url($service)
        . (($from !== '') ? '&from=' . rawurlencode($from) : '')
        . (($anchor !== '') ? '#' . $anchor : '');
}

/**
 * A service's page, keeping the way back it was reached with. Pure.
 *
 * @param string     $service
 * @param array|null $back what pg_connections_back() read
 * @return string
 */
function pg_connections_back_page_url($service, $back)
{
    return pg_connections_screen_page_url($service)
        . (is_array($back) ? '&from=' . rawurlencode($back['value']) : '');
}

/**
 * Whether a write on a service's page takes something away (Disconnect,
 * Remove, Delete): it stays on the page whatever led there. Pure.
 *
 * @param string $action the posted action
 * @return bool
 */
function pg_connections_back_removes($action)
{
    return is_string($action) && (bool) preg_match('/(^|_)(remove|disconnect|delete)(_|$)/', $action);
}

/**
 * The address a write on a service's page goes to: its page, keeping the
 * card that led there, marked saved= when it went through and took nothing
 * away, so the page can return to the card once the service is ready. The
 * list and a page reached from it are left as they are. Pure.
 *
 * @param string $location the address the write would go to
 * @param mixed  $from     the posted connections_from
 * @param string $action   the posted action
 * @param bool   $refused  whether the write was refused (the form's errors)
 * @return string
 */
function pg_connections_back_after_write($location, $from, $action, $refused)
{
    $location = (string) $location;

    if (!is_string($from) || !preg_match('~^[a-z_]{1,40}/pgset-[a-z0-9\-]{1,60}(/[a-z0-9_\-]{1,60})?$~D', $from)
        || !preg_match('/\?service=[a-z0-9_]{1,60}(?=$|#)/', $location)) {
        return $location;
    }

    $hash = strpos($location, '#');
    $anchor = ($hash === false) ? '' : substr($location, $hash);
    $address = ($hash === false) ? $location : substr($location, 0, $hash);

    return $address . '&from=' . rawurlencode($from)
        . ((!$refused && !pg_connections_back_removes($action)) ? '&saved=1' : '')
        . $anchor;
}

/**
 * Whether a service's page returns to the card that led there by itself:
 * a Connect that went through, or a Save that leaves the service ready.
 * A refused Save, a Save that leaves something missing and a plain visit
 * stay. Pure.
 *
 * @param array|null $back  what pg_connections_back() read
 * @param string     $done  saved, connected, or ''
 * @param string     $state the service's state now (pg_connections_screen_state())
 * @return bool
 */
function pg_connections_back_due($back, $done, $state)
{
    if (!is_array($back)) {
        return false;
    }

    return ($done === 'connected') || (($done === 'saved') && ($state === 'connected'));
}

/**
 * The card a page was led to from, read from the address: from=, or one of
 * the two parameters it replaced, for the links written before it: a
 * drive's backup_return=1 (the Backups card) and mail_return= (a settings
 * page with its card's anchor; a category that became a tab names the
 * category holding it, pg_settings_moved_categories()). Checked again by
 * pg_connections_back(). Pure.
 *
 * @param array  $get     the page's query
 * @param string $service the page's service
 * @return string <category>/<card>[/<drawer>], or ''
 */
function pg_connections_back_legacy($get, $service)
{
    if (isset($get['from']) && is_string($get['from']) && ($get['from'] !== '')) {
        return $get['from'];
    }

    if (!empty($get['backup_return']) && is_string($get['backup_return'])
        && function_exists('pg_backup_cloud_type_of_service') && (pg_backup_cloud_type_of_service($service) !== '')) {
        return 'maintenance/pgset-backup';
    }

    if (isset($get['mail_return']) && is_string($get['mail_return'])
        && preg_match('/^settings_([a-z_]{1,40})\.php#(pgset-[a-z0-9\-]{1,60})$/D', $get['mail_return'], $match)) {
        $moved = function_exists('pg_settings_moved_categories') ? pg_settings_moved_categories() : array();

        return (isset($moved[$match[1]]) ? $moved[$match[1]][0] : $match[1]) . '/' . $match[2];
    }

    return '';
}

/**
 * Where a return to a card lands when the card's own address is not
 * enough: the Backups card is opened with the destination of the drive
 * just connected shown chosen (?backup_connect=). '' for every other card,
 * whose address is pg_settings_link(). Pure.
 *
 * @param array|null $back
 * @param string     $service
 * @return string
 */
function pg_connections_back_destination($back, $service)
{
    if (!is_array($back) || ($back['category'] !== 'maintenance') || ($back['card'] !== 'pgset-backup')
        || !function_exists('pg_backup_cloud_type_of_service')) {
        return '';
    }

    $type = pg_backup_cloud_type_of_service($service);

    return ($type !== '') ? pg_backup_cloud_connect_return($type) : '';
}

/**
 * What the site has stored that the states are read from, read once. No
 * outside service is called: every reader answers from the database, the
 * config or the sealed entries.
 *
 * @return array accounts, apps, config, readers, services, marketplaces,
 *               edoc, models, mcp, notify
 */
function pg_connections_screen_facts()
{
    $facts = array(
        'accounts' => array(), 'apps' => array(), 'config' => array(), 'readers' => array(),
        'services' => array(), 'marketplaces' => array(), 'edoc' => '', 'models' => array(),
        'mcp' => array(), 'notify' => array(),
    );

    if (function_exists('pg_connect_schema_ready') && pg_connect_schema_ready()) {
        $facts['accounts'] = pg_connect_accounts();

        foreach ((array) db_items("SELECT provider, enabled FROM connect_apps") as $app) {
            $facts['apps'][(string) $app['provider']] = ((int) $app['enabled'] === 1);
        }
    }

    // The Workspace's readers need its functions; without it they answer no.
    $workspace = defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED && is_file(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

    if ($workspace) {
        require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
        $workspace = function_exists('ws_ready') && ws_ready();
    }

    foreach (pg_connections_registry() as $row) {
        $state = isset($row['state']) ? $row['state'] : null;

        if (!is_array($state)) {
            continue;
        }

        switch ($state['kind']) {

            case 'config':
                foreach ((array) $state['names'] as $name) {
                    $facts['config'][$name] = defined($name) ? constant($name) : null;
                }
                break;

            case 'reader':
                $function = (string) $state['function'];
                $facts['readers'][pg_connections_screen_reader_key($state)] = function_exists($function)
                    && (bool) call_user_func_array($function, isset($state['args']) ? (array) $state['args'] : array());
                break;

            case 'service':
                $facts['services'][$state['key']] = function_exists('pg_connect_service_get') && (bool) pg_connect_service_get($state['key']);
                break;
        }
    }

    if (function_exists('waf_table_has_column') && waf_table_has_column('marketplace_accounts', 'provider')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/base.php');

        foreach (mp_accounts() as $account) {
            $facts['marketplaces'][(string) $account['provider']] = true;
        }
    }

    // The e-document drivers live in the ERP module; the gate its files
    // check is defined as its bootstrap defines it.
    if (defined('ERP_ENABLED') && ERP_ENABLED) {
        if (!defined('PG_ERP_ENTRY')) {
            define('PG_ERP_ENTRY', true);
        }

        require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/registry.php');
        $facts['edoc'] = erp_edoc_active();
    }

    if ($workspace && function_exists('ws_model_agents')) {
        foreach (ws_model_agents() as $agent) {
            $facts['models'][(string) $agent['provider']] = true;
        }
    }

    if ($workspace && function_exists('ws_mcp_servers') && function_exists('ws_mcp_catalogue_added')) {
        foreach (ws_mcp_catalogue_added(ws_mcp_servers()) as $key => $server) {
            $facts['mcp'][$key] = true;
        }
    }

    if (function_exists('pg_notify_targets')) {
        foreach (pg_notify_targets() as $target) {
            if (isset($target['kind'])) {
                $facts['notify'][(string) $target['kind']] = true;
            }
        }
    }

    return $facts;
}
