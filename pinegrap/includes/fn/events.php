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

// The events the workspace listens to (includes/workspace/watch.php): what
// starts a scheduled action with an event rule, and what is written into the
// channels that watch a record. Only these are kept: the workspace's own
// events (workspace.*) and the ERP's (erp.*) have nobody here to read them,
// and a public channel announces every message.
function pg_event_workspace_events()
{
    return array(
        'order.created',
        'order.status_changed',
        'order.shipped',
        'order.delivered',
        'order.cancelled',
        'stock.low',
        'customer.created',
        'customer.updated',
        'product.updated',
        'form.submitted',
    );
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

    $json = json_encode((array) $payload, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

    db("INSERT INTO ws_events_in (event, payload, created_at, taken_at)
        VALUES ('" . e((string) $event) . "', '" . e(($json === false) ? '{}' : $json) . "', UNIX_TIMESTAMP(), 0)");
}
