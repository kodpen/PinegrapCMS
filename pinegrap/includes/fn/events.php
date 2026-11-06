<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Announcing something, from wherever it happened.
//
// The event catalogue and the delivery queue live with the API
// (includes/api/outbound/webhooks.php), because that is who sends them. The
// things worth announcing, though, happen all over the software: a contact is
// created by the checkout, by a sign-up form, by a submitted form and by the
// panel's own screen. None of those files should have to know where the API
// keeps its queue, or remember the path to it, so this is the one line they
// call.
//
// Queued, never sent: delivery is the scheduled job's work, so a slow receiver
// cannot hold up a visitor's checkout.
//
// Silent when the API is not installed or its file is missing. An event that
// cannot be queued must never be the reason a sign-up form fails.

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

function pg_announce($event, $data = array())
{
    // The workspace hears about it first, and whether or not the API is here:
    // a site with no webhooks still wants the new order in its channel.
    pg_event_record($event, (array) $data);

    // A changed page, product, product group or file makes every page the
    // page cache keeps old once the request ends, whoever made the change
    // (the panel, the API, the MCP server, the workspace).
    if (function_exists('pg_page_cache_announce_dirties') && pg_page_cache_announce_dirties($event)) {
        pg_page_cache_mark_dirty();
    }

    // A product back in stock tells whoever asked to be told.
    if (function_exists('pg_stock_notify_heard')) {
        pg_stock_notify_heard($event, (array) $data);
    }

    // A changed page or record reaches the site's Fediverse followers on the
    // general job's next tick (includes/fn/activitypub_outbox.php). The
    // module is loaded only on a site that has the feature's settings.
    if (preg_match('/^(page|form)\./', (string) $event) && function_exists('pg_config_text') && (pg_config_text('ap_settings') !== '')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/activitypub_outbox.php');
        pg_ap_heard($event);
    }

    // Contacts put into a group get the mail of the campaign profiles that
    // follow the group (includes/fn/mail.php).
    if (function_exists('pg_contact_group_joined_heard')) {
        pg_contact_group_joined_heard($event, (array) $data);
    }

    // Chosen Workspace events to the Slack, Teams and Telegram targets.
    if (function_exists('pg_notify_announced')) {
        pg_notify_announced($event, (array) $data);
    }

    if (!function_exists('api_webhook_enqueue')) {

        $file = PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php';

        if (!file_exists($file)) {
            return;
        }

        require_once($file);
    }

    if (!function_exists('api_webhook_enqueue')) {
        return;
    }

    // Recorded above already: the queue must not hold the event twice.
    api_webhook_enqueue($event, (array) $data, true);
}

// What customer.consent_changed carries for a consent_log row, or null when
// the row announces nothing: a cookie answer (no customer), a pending one (no
// answer yet), an import (a bulk path), or no customer record to name.
function pg_consent_changed_event($id, $row, $contact_id)
{
    if (($row['channel'] === 'cookie') || ($row['status'] === 'pending') || ($row['source'] === 'import') || ((int) $contact_id <= 0)) {
        return null;
    }

    return array(
        'id'      => (int) $contact_id,
        'consent' => array(
            'id'         => (int) $id,
            'channel'    => (string) $row['channel'],
            'purpose'    => (string) $row['purpose'],
            'status'     => (string) $row['status'],
            'source'     => (string) $row['source'],
            'created_at' => gmdate('Y-m-d\TH:i:s\Z', (int) $row['created_at']),
        ),
    );
}

// A consent answer just written to consent_log (pg_consent_log_record()). A
// row kept under the account alone names the account's customer record.
function pg_announce_consent_changed($id, $row)
{
    $contact_id = (int) $row['contact_id'];

    if (($contact_id <= 0) && ((int) $row['user_id'] > 0)) {
        $contact_id = (int) db_value("SELECT user_contact FROM user WHERE user_id = '" . (int) $row['user_id'] . "'");
    }

    $event = pg_consent_changed_event($id, $row, $contact_id);

    if ($event !== null) {
        pg_announce('customer.consent_changed', $event);
    }
}

// The campaign.sent payload; no database. Identifiers only: the figures are
// read from GET /campaigns/{id} or GET /campaigns/sms.
function pg_campaign_sent_event($channel, $id, $type)
{
    return array(
        'id'      => (int) $id,
        'channel' => ($channel === 'sms') ? 'sms' : 'email',
        'type'    => ($type === 'automatic') ? 'automatic' : 'manual',
    );
}

// A contact that did not exist a moment ago, whichever screen or form made it.
//
// The address book is written from eight places in this software and most of
// them insert an empty row first and fill it in a moment later, so the caller
// rarely has the e-mail address to hand at the point it knows the id. Rather
// than make every caller carry one, this reads it back - one indexed lookup,
// and only when a contact was really created.
//
// Bulk paths do not call this: an import of five thousand rows would put five
// thousand events in the queue and the receiver would learn nothing it could
// not have read in one listing. The event catalogue says so.
function pg_announce_contact_created($contact_id, $email = null)
{
    $contact_id = (int) $contact_id;

    if ($contact_id <= 0) {
        return;
    }

    if (($email === null) && (function_exists('db'))) {
        $email = db("SELECT email_address FROM contacts WHERE id = '" . $contact_id . "' LIMIT 1");
    }

    pg_announce('customer.created', array(
        'id'    => $contact_id,
        'email' => (string) $email,
    ));
}

// What contact_group.members_changed carries: the group, and the contacts put
// into it and taken out of it as two lists of ids. null when nobody moved, so a
// save that ticked what was already ticked announces nothing.
function pg_contact_group_members_event($contact_group_id, $name, $added, $removed)
{
    $lists = array('added' => array(), 'removed' => array());

    foreach (array('added' => $added, 'removed' => $removed) as $key => $ids) {
        foreach ((array) $ids as $id) {
            $id = (int) $id;

            if (($id > 0) && !in_array($id, $lists[$key], true)) {
                $lists[$key][] = $id;
            }
        }
    }

    if (((int) $contact_group_id <= 0) || (empty($lists['added']) && empty($lists['removed']))) {
        return null;
    }

    return array(
        'id'      => (int) $contact_group_id,
        'name'    => (string) $name,
        'added'   => $lists['added'],
        'removed' => $lists['removed'],
    );
}

// Contacts put into or taken out of one contact group: one event for the
// change, not one per contact, so ticking a group for two hundred selected
// contacts queues one row per subscriber. The group's name is read back when
// the caller does not have it; a group that is not there is not announced.
function pg_announce_contact_group_members($contact_group_id, $added = array(), $removed = array(), $name = null)
{
    $contact_group_id = (int) $contact_group_id;

    if (!pg_contact_group_members_event($contact_group_id, '', $added, $removed)) {
        return;
    }

    if ($name === null) {
        $name = db_value("SELECT name FROM contact_groups WHERE id = '" . $contact_group_id . "' LIMIT 1");

        if ($name === null) {
            return;
        }
    }

    $event = pg_contact_group_members_event($contact_group_id, $name, $added, $removed);

    pg_announce('contact_group.members_changed', $event);

    // Flows hear each contact who joined on their own (contact_group.joined):
    // a run is about one contact, so a change of two hundred keeps two
    // hundred rows, worked off by the workspace's event run. The webhooks
    // keep the one members_changed event above.
    foreach (pg_contact_group_joined_events($event) as $joined) {
        pg_event_record('contact_group.joined', $joined);
    }
}

// What contact_group.joined carries for each contact a members_changed event
// put into the group: the contact (id, as every contact event names it, and
// contact_id for a flow's {{trigger.contact_id}}), the group and its name.
// Nobody added, or no event, gives an empty list.
function pg_contact_group_joined_events($event)
{
    $out = array();

    if (!is_array($event) || ((int) ($event['id'] ?? 0) <= 0)) {
        return $out;
    }

    foreach ((array) ($event['added'] ?? array()) as $contact_id) {
        $out[] = array(
            'id'         => (int) $contact_id,
            'contact_id' => (int) $contact_id,
            'group_id'   => (int) $event['id'],
            'group_name' => (string) ($event['name'] ?? ''),
        );
    }

    return $out;
}

// The events the workspace listens to (includes/workspace/watch.php): what
// starts a scheduled action with an event rule, and what is written into the
// channels that watch a record. Only these are kept from pg_announce(): the
// ERP's (erp.*) have nobody here to read them, and the workspace keeps its
// own (workspace.*) through ws_event(), only while a scheduled action waits
// for one - a public channel announces every message.
function pg_event_workspace_events()
{
    return array(
        'order.created',
        'order.status_changed',
        'order.shipped',
        'order.delivered',
        'order.cancelled',
        'order.return.requested',
        'stock.low',
        'customer.created',
        'customer.updated',
        'contact_group.joined',
        'product.updated',
        'form.submitted',
        'booking.created',
        'booking.confirmed',
        'booking.rescheduled',
        'booking.cancelled',
        'product.review.created',
        'order.return.approved',
        'order.return.received',
        'order.return.refunded',
        'cart_quote.created',
        'cart_quote.ordered',
        'marketplace.order.imported',
        'subscription.failed',
        'subscription.renewed',
        'subscription.cancelled',
        'form.paid',
        'inventory.changed',
    );
}

// The site's events kept only while an active scheduled action or flow
// names them: a stock change comes with every line of every sale, and rows
// nobody waits for would hold up the run's batch for the events people do.
function pg_event_workspace_on_demand()
{
    return array('inventory.changed');
}

// Is the event kept, given the rules of the active scheduled actions that
// may name it? Every event but the on-demand ones is; an on-demand one only
// when an event rule names it. Pure.
function pg_event_workspace_awaited($event, $rules_rows)
{
    if (!in_array((string) $event, pg_event_workspace_on_demand(), true)) {
        return true;
    }

    foreach ((array) $rules_rows as $rules) {
        foreach ((array) json_decode((string) $rules, true) as $rule) {
            if (is_array($rule) && (($rule['type'] ?? '') === 'event') && ((string) ($rule['event'] ?? '') === (string) $event)) {
                return true;
            }
        }
    }

    return false;
}

// The rules of the active scheduled actions that may name an on-demand
// event, read once a request for each event.
function pg_event_workspace_waiting_rules($event)
{
    static $read = array();

    $event = (string) $event;

    if (!isset($read[$event])) {
        $read[$event] = pg_schema_has('ws_scheduled_actions')
            ? array_column((array) db_items("SELECT rules FROM ws_scheduled_actions WHERE status = 'active' AND rules LIKE '%" . e($event) . "%' LIMIT 50"), 'rules')
            : array();
    }

    return $read[$event];
}

// Keeps an event for the workspace to pick up (ws_events_in).
//
// One INSERT and nothing else: the visitor's checkout or form submission is
// still waiting, so nothing here reads a channel or runs an action. The
// workspace's own run (ws_events_process(), started by an open screen or by
// the general job) works the rows off later. The workspace's files are not
// loaded for this; front pages never load them.
//
// Silent when the workspace is switched off, when the event is not one it
// listens to, and when the table is not there yet (2026.4.8, 8.85).
function pg_event_record($event, $payload = array())
{
    if (!defined('WORKSPACE_ENABLED') || !WORKSPACE_ENABLED) {
        return;
    }

    if (!in_array((string) $event, pg_event_workspace_events(), true)) {
        return;
    }

    if (!function_exists('pg_schema_has') || !pg_schema_has('ws_events_in')) {
        return;
    }

    if (in_array((string) $event, pg_event_workspace_on_demand(), true) && !pg_event_workspace_awaited($event, pg_event_workspace_waiting_rules($event))) {
        return;
    }

    $json = json_encode(pg_event_payload_depth((array) $payload), JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

    db("INSERT INTO ws_events_in (event, payload, created_at, taken_at)
        VALUES ('" . e((string) $event) . "', '" . e(($json === false) ? '{}' : $json) . "', UNIX_TIMESTAMP(), 0)");
}

// The payload with the chain depth an event caused right now stands at
// (ws_event_depth()), as ws_event() stamps it: an order a flow's step makes
// starts the actions waiting for it one step further down the run's chain,
// so a loop of runs stops at the chain limit (ws_events_start()). Outside a
// run, or where the workspace's files are not loaded, it is left as it is.
function pg_event_payload_depth($payload)
{
    $payload = (array) $payload;
    $depth = function_exists('ws_event_depth') ? (int) ws_event_depth() : 0;

    if ($depth > 0) {
        $payload['_depth'] = $depth;
    }

    return $payload;
}
