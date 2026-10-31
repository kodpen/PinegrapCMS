<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the external API's side of a channel's customer: who the
 * customer is and where they stand (GET /workspace/channels/{id}/customer),
 * and what happened with them (GET /workspace/channels/{id}/customer/history).
 *
 * The summary is built in customer.php beside this file, for the panel and
 * the API alike, with the rights of whoever reads it. Here the reader is the
 * owner of the application - or, for Claude's application, the person who
 * asked it in a channel its owner is not in, as the channel context reads it
 * (ws_api_assistant_channel_reader()) - narrowed to the key's scopes: a
 * section is read only with the scope the API reads that kind of record with
 * elsewhere, so a key reaches no order, balance or form through a channel
 * that it could not read over its own endpoints.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL') && !defined('PG_INIT_LOADED')) {
    exit;
}

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The scope each right of the reader the summary reads is narrowed to: the
 * one the external API reads that kind of record with (ws_api_search_record_scopes(),
 * which the assistants follow too). User accounts have none: no API scope
 * reads them, so GET /workspace/search leaves them out and a user, the
 * customer or a tie, goes by kind and number here.
 *
 * @return array ws_viewer() right => scope, null for none
 */
function ws_api_customer_scopes()
{
    return array(
        'ecommerce' => 'orders:read',
        'contacts'  => 'customers:read',
        'erp'       => 'erp:read',
        'erp_cash'  => 'erp_cash:read',
        'forms'     => 'forms:read',
        'users'     => null,
    );
}

/**
 * The reader narrowed to the key's scopes: a right stays only where the key
 * holds its scope, and a right no scope reads is dropped. A scope never opens
 * what the person may not see. The rights the summary does not read are left
 * as they are.
 *
 * @param array    $viewer ws_viewer()
 * @param string[] $scopes the key's scopes (api_current_scopes())
 * @return array
 */
function ws_api_customer_viewer($viewer, $scopes)
{
    foreach (ws_api_customer_scopes() as $right => $scope) {
        $viewer[$right] = ($scope !== null) && !empty($viewer[$right]) && api_has_scope($scopes, $scope);
    }

    return $viewer;
}

function ws_api_channel_customer($params)
{
    list($viewer, $channel) = ws_api_assistant_channel_reader($params['id']);

    $viewer = ws_api_customer_viewer($viewer, api_current_scopes());
    $identity = ws_customer_identity($viewer, $channel);
    $summary = is_array($identity) ? ws_customer_summary($viewer, $channel, $identity) : array();

    api_ok(ws_api_channel_customer_present($channel, $identity, $summary, URL_SCHEME . HOSTNAME_SETTING));
}

/**
 * The answer: the customer and its ties, and a section for each part of the
 * summary the reader may see and that holds something, null otherwise.
 *
 * @param array      $channel  a ws_channels row
 * @param array|null $identity ws_customer_identity()
 * @param array      $summary  ws_customer_summary()
 * @param string     $origin   scheme and host the panel's addresses are made whole with
 * @param string|null $base     the panel's path, ending in /; null for this site's
 * @return array
 */
function ws_api_channel_customer_present($channel, $identity, $summary, $origin, $base = null)
{
    if ($base === null) {
        $base = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/';
    }

    $record = function ($item) use ($origin) {
        return ws_api_customer_record_present($item, $origin);
    };

    return array(
        'channel_id'   => (int) $channel['id'],
        'customer'     => is_array($identity) ? $record($identity['customer']) : null,
        'links'        => is_array($identity) ? array_map($record, (array) $identity['links']) : array(),
        'orders'       => isset($summary['orders']) ? ws_api_customer_orders_present($summary['orders']) : null,
        'erp_accounts' => isset($summary['erp_accounts']) ? array_map('ws_api_customer_account_present', $summary['erp_accounts']) : null,
        'sales_quotes' => isset($summary['sales_quotes']) ? ws_api_customer_quotes_present($summary['sales_quotes']) : null,
        'cart_quotes'  => isset($summary['cart_quotes']) ? ws_api_customer_cart_quotes_present($summary['cart_quotes'], $origin, $base) : null,
        'forms'        => isset($summary['forms']) ? array_map('ws_api_customer_form_present', $summary['forms']) : null,
    );
}

// What ws_api_channel_customer_present() returns.
function ws_api_channel_customer_schema()
{
    return array(
        'channel_id'   => 'integer',
        'customer'     => 'WorkspaceCustomerRecord?',
        'links'        => 'WorkspaceCustomerRecord[]',
        'orders'       => 'WorkspaceCustomerOrders?',
        'erp_accounts' => 'WorkspaceCustomerAccount[]?',
        'sales_quotes' => 'WorkspaceCustomerQuotes?',
        'cart_quotes'  => 'WorkspaceCustomerCartQuotes?',
        'forms'        => 'WorkspaceCustomerForm[]?',
    );
}

/**
 * The customer, or a record tied to it.
 *
 * @param array  $item   ws_customer_identity_item()
 * @param string $origin see ws_api_channel_customer_present()
 * @return array
 */
function ws_api_customer_record_present($item, $origin)
{
    $url = (string) ($item['url'] ?? '');

    return array(
        'type'   => (string) $item['type'],
        'id'     => (int) $item['id'],
        'kind'   => (string) $item['kind'],
        'label'  => (string) $item['label'],
        'detail' => (string) $item['detail'],
        'known'  => (bool) $item['known'],
        'url'    => ($url !== '') ? ws_api_inbox_url($url, $origin) : null,
    );
}

// What ws_api_customer_record_present() returns.
function ws_api_customer_record_schema()
{
    return array(
        'type'   => 'string',
        'id'     => 'integer',
        'kind'   => 'string',
        'label'  => 'string',
        'detail' => 'string',
        'known'  => 'boolean',
        'url'    => 'string?',
    );
}

function ws_api_customer_orders_present($section)
{
    return array(
        'count'  => (int) $section['count'],
        'latest' => array_map('ws_api_customer_order_present', (array) $section['latest']),
    );
}

// What ws_api_customer_orders_present() returns.
function ws_api_customer_orders_schema()
{
    return array(
        'count'  => 'integer',
        'latest' => 'WorkspaceCustomerOrder[]',
    );
}

function ws_api_customer_order_present($row)
{
    return array(
        'id'           => (int) $row['id'],
        'number'       => (string) $row['number'],
        'status'       => (string) $row['status'],
        'status_label' => (string) $row['status_label'],
        'total'        => api_money($row['total']),
        'total_text'   => (string) $row['total_text'],
        'placed_at'    => api_time($row['placed_at']),
    );
}

// What ws_api_customer_order_present() returns.
function ws_api_customer_order_schema()
{
    return array(
        'id'           => 'integer',
        'number'       => 'string',
        'status'       => 'string',
        'status_label' => 'string',
        'total'        => 'integer',
        'total_text'   => 'string',
        'placed_at'    => 'string?',
    );
}

function ws_api_customer_account_present($row)
{
    return array(
        'id'           => (int) $row['id'],
        'title'        => (string) $row['title'],
        'status'       => (string) $row['status'],
        'balance'      => api_money($row['balance']),
        'balance_text' => (string) $row['balance_text'],
    );
}

// What ws_api_customer_account_present() returns.
function ws_api_customer_account_schema()
{
    return array(
        'id'           => 'integer',
        'title'        => 'string',
        'status'       => 'string',
        'balance'      => 'integer',
        'balance_text' => 'string',
    );
}

function ws_api_customer_quotes_present($section)
{
    return array(
        'count'  => (int) $section['count'],
        'latest' => array_map('ws_api_customer_quote_present', (array) $section['latest']),
    );
}

// What ws_api_customer_quotes_present() returns.
function ws_api_customer_quotes_schema()
{
    return array(
        'count'  => 'integer',
        'latest' => 'WorkspaceCustomerQuote[]',
    );
}

function ws_api_customer_quote_present($row)
{
    return array(
        'id'            => (int) $row['id'],
        'number'        => (string) $row['number'],
        'account_id'    => (int) $row['account_id'],
        'account_title' => (string) $row['account_title'],
        'issue_date'    => (string) $row['issue_date'],
        'valid_until'   => ((string) $row['valid_until'] !== '') ? (string) $row['valid_until'] : null,
        'currency'      => (string) $row['currency'],
        'total'         => api_money($row['total']),
        'total_text'    => (string) $row['total_text'],
    );
}

// What ws_api_customer_quote_present() returns.
function ws_api_customer_quote_schema()
{
    return array(
        'id'            => 'integer',
        'number'        => 'string',
        'account_id'    => 'integer',
        'account_title' => 'string',
        'issue_date'    => 'string',
        'valid_until'   => 'string?',
        'currency'      => 'string',
        'total'         => 'integer',
        'total_text'    => 'string',
    );
}

/**
 * The customer's open cart quotes: how many, and the latest with the panel's
 * address of each. A cart quote holds quantities only, priced when it fills a
 * cart, so it has no amount; the link's token and its hint never leave.
 *
 * @param array  $section ws_customer_summary_build()'s cart_quotes
 * @param string $origin  see ws_api_channel_customer_present()
 * @param string $base    the panel's path, ending in /
 * @return array
 */
function ws_api_customer_cart_quotes_present($section, $origin, $base)
{
    $latest = array();

    foreach ((array) $section['latest'] as $row) {
        $latest[] = ws_api_customer_cart_quote_present($row, $origin, $base);
    }

    return array(
        'count'  => (int) $section['count'],
        'latest' => $latest,
    );
}

// What ws_api_customer_cart_quotes_present() returns.
function ws_api_customer_cart_quotes_schema()
{
    return array(
        'count'  => 'integer',
        'latest' => 'WorkspaceCustomerCartQuote[]',
    );
}

function ws_api_customer_cart_quote_present($row, $origin, $base)
{
    return array(
        'id'          => (int) $row['id'],
        'state'       => (string) $row['state'],
        'lines'       => (int) $row['lines'],
        'valid_until' => ((int) $row['valid_until'] > 0) ? api_time($row['valid_until']) : null,
        'created_at'  => api_time($row['created_at']),
        'url'         => ws_api_inbox_url($base . 'view_cart_quotes.php?id=' . (int) $row['id'], $origin),
    );
}

// What ws_api_customer_cart_quote_present() returns.
function ws_api_customer_cart_quote_schema()
{
    return array(
        'id'          => 'integer',
        'state'       => 'string',
        'lines'       => 'integer',
        'valid_until' => 'string?',
        'created_at'  => 'string?',
        'url'         => 'string',
    );
}

function ws_api_customer_form_present($row)
{
    return array(
        'id'             => (int) $row['id'],
        'name'           => (string) $row['name'],
        'reference_code' => (string) $row['reference_code'],
        'complete'       => (bool) $row['complete'],
        'submitted_at'   => api_time($row['submitted_at']),
    );
}

// What ws_api_customer_form_present() returns.
function ws_api_customer_form_schema()
{
    return array(
        'id'             => 'integer',
        'name'           => 'string',
        'reference_code' => 'string',
        'complete'       => 'boolean',
        'submitted_at'   => 'string?',
    );
}

function ws_api_channel_customer_history($params)
{
    list($viewer, $channel) = ws_api_assistant_channel_reader($params['id']);

    $viewer = ws_api_customer_viewer($viewer, api_current_scopes());
    // Tasks need tasks:read, as every other Workspace read of tasks; Claude's
    // key reads messages and tasks only in channels Claude may be asked in
    // (ws_customer_history_ref_rows()).
    $viewer['history_tasks'] = api_has_scope(api_current_scopes(), 'tasks:read');
    $viewer['history_claude'] = ws_api_is_claude();
    $identity = ws_customer_identity($viewer, $channel);
    $history = is_array($identity)
        ? ws_customer_history($viewer, $channel, $identity, (int) ($params['before'] ?? 0), ws_customer_history_limit($params['limit'] ?? null))
        : array('entries' => array(), 'more' => false, 'before' => 0);

    api_ok(ws_api_customer_history_present($channel, $history, URL_SCHEME . HOSTNAME_SETTING));
}

/**
 * A page of the customer's history: its entries, and where the next page
 * starts when there is one.
 *
 * @param array  $channel a ws_channels row
 * @param array  $history ws_customer_history()
 * @param string $origin  scheme and host the panel's addresses are made whole with
 * @return array
 */
function ws_api_customer_history_present($channel, $history, $origin)
{
    $entries = array();

    foreach ((array) $history['entries'] as $entry) {
        $entries[] = ws_api_customer_history_entry_present($entry, $origin);
    }

    return array(
        'channel_id' => (int) $channel['id'],
        'entries'    => $entries,
        'more'       => !empty($history['more']),
        'before'     => !empty($history['more']) ? (int) $history['before'] : null,
    );
}

// What ws_api_customer_history_present() returns.
function ws_api_customer_history_schema()
{
    return array(
        'channel_id' => 'integer',
        'entries'    => 'WorkspaceCustomerHistoryEntry[]',
        'more'       => 'boolean',
        'before'     => 'integer?',
    );
}

/**
 * One entry of the history.
 *
 * @param array  $entry  a ws_customer_history_build() entry
 * @param string $origin see ws_api_customer_history_present()
 * @return array
 */
function ws_api_customer_history_entry_present($entry, $origin)
{
    $url = (string) ($entry['url'] ?? '');

    return array(
        'kind'         => (string) $entry['kind'],
        'id'           => (int) $entry['id'],
        'at'           => api_time($entry['at']),
        'title'        => (string) $entry['title'],
        'detail'       => (string) $entry['detail'],
        'status_label' => (string) $entry['status_label'],
        'amount'       => ($entry['amount'] === null) ? null : api_money($entry['amount']),
        'amount_text'  => (string) $entry['amount_text'],
        'url'          => ($url !== '') ? ws_api_inbox_url($url, $origin) : null,
    );
}

// What ws_api_customer_history_entry_present() returns.
function ws_api_customer_history_entry_schema()
{
    return array(
        'kind'         => 'string',
        'id'           => 'integer',
        'at'           => 'string?',
        'title'        => 'string',
        'detail'       => 'string',
        'status_label' => 'string',
        'amount'       => 'integer?',
        'amount_text'  => 'string',
        'url'          => 'string?',
    );
}
