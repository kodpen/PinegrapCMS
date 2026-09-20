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

    api_webhook_enqueue($event, (array) $data);
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
