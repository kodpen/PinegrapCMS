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
 * in, applications people connected with OAuth, webhook subscriptions. A
 * table not yet installed counts none.
 *
 * @return array applications, devices, connected, webhooks (int)
 */
function pg_connections_screen_other_side()
{
    $count = function ($table, $column, $sql) {
        return (function_exists('waf_table_has_column') && waf_table_has_column($table, $column)) ? (int) db_value($sql) : 0;
    };

    return array(
        'applications' => $count('api_apps', 'id', "SELECT COUNT(*) FROM api_apps WHERE name NOT LIKE '\\_\\_test\\_\\_%'"),
        'devices'      => $count('api_devices', 'oauth_client_id', "SELECT COUNT(*) FROM api_devices WHERE oauth_client_id IS NULL OR oauth_client_id = 0"),
        'connected'    => $count('oauth_clients', 'id', "SELECT COUNT(*) FROM oauth_clients"),
        'webhooks'     => $count('api_webhooks', 'id', "SELECT COUNT(*) FROM api_webhooks"),
    );
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

    return (isset($rows[$asked]['kind']) && ($rows[$asked]['kind'] === 'service')) ? $asked : '';
}

/**
 * The services whose page holds their account provider's application
 * ("Connection details"), by registry key: that provider's row on the list
 * opens the page instead of its drawer. Pure.
 *
 * @return array service key => provider key
 */
function pg_connections_screen_page_providers()
{
    return array('github' => 'github');
}

/**
 * The cards of a service's page, in their order: "status", "details" (its
 * provider's application, for a manager; an AI provider's key, for who may
 * change the assistants), "uses", then "repositories" on
 * GitHub's page for a holder of the Workspace settings right. Each card
 * keeps the gate of the place it came from; a card the viewer may not open
 * is left out. Pure.
 *
 * @param string $id  the service's registry key
 * @param array  $can 'manager' (bool), 'repositories' (bool: may manage
 *                    the GitHub repository links), 'ai' (bool: may change
 *                    the assistants, connections_ai_may_manage())
 * @return array list of card keys
 */
function pg_connections_screen_page_cards($id, $can)
{
    $cards = array('status');

    if (!empty($can['manager']) && isset(pg_connections_screen_page_providers()[$id])) {
        $cards[] = 'details';
    } elseif (!empty($can['ai']) && isset(pg_ai_connection_services()[$id])) {
        $cards[] = 'details';
    }

    $cards[] = 'uses';

    if (($id === 'github') && !empty($can['repositories'])) {
        $cards[] = 'repositories';
    }

    return $cards;
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
