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
 * - mcp: a Workspace MCP server was added from the catalogue entry 'key'.
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
    );
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
 * 'uses' (English, a few words) and optionally 'covers' (list name => keys).
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
        // ROWS
    );

    return $rows;
}
