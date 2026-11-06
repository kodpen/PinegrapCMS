<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Telling an outside system that something happened here.
//
// Without this an integration has to ask "anything new?" on a timer, and the
// interval is a straight trade between how stale an order may be and how much
// pointless traffic the site carries. A webhook turns that into one call at the
// moment it matters.
//
// Nothing is delivered inside the request that caused it. An order that has just
// been paid for must not wait on somebody else's server - if their endpoint is
// slow, the customer watches a spinner; if it hangs, the checkout hangs. The
// event goes into a queue and the cron sends it.

if (!defined('PG_INIT_LOADED')) {
	exit;
}

require_once(dirname(__FILE__) . '/http.php');

// The events an application may subscribe to. A name that is not in here cannot
// be stored, so a subscription always means something.
function api_webhook_events() {

	$events = array(
		'order.created'        => 'A new order was placed',
		'order.status_changed' => 'An order moved to another status',
		'order.shipped'        => 'A tracking number was recorded for an order',
		'order.delivered'      => 'A delivery date was recorded for an order',
		'order.cancelled'      => 'An order was cancelled',
		// A customer return (includes/fn/returns.php). id is the order's, as
		// in every order.* event; return_id, status, source, reason and the
		// lines follow. Announced from the return model, so every path that
		// makes the move announces it: a request from My Account, the panel or
		// the API, an approval on one return's screen or a bulk approval (one
		// event per request it approves - a person ticks each one, it is not
		// an import), the goods marked received, and the refund once its
		// outcome stands.
		// A rejection, the parcel sent back, a withdrawn request and a closing
		// are not announced; the descriptions say so.
		'order.return.requested' => 'A return was requested for an order: by the customer in My Account, recorded by staff on the panel, or recorded through the API (a withdrawn request is not announced)',
		'order.return.approved'  => 'A return request was approved, one event per request a bulk approval takes (a rejection is not announced)',
		'order.return.received'  => 'The goods of a return arrived back at the shop (the customer saying the parcel left is not announced)',
		'order.return.refunded'  => 'A received return was refunded through the payment service, recorded as refunded another way, or given as store credit (refund_method credit); unclear: true when the service gave no clear answer and the refund is booked unconfirmed (closing a return is not announced)',
		// A marketplace package (includes/api/outbound/orders.php, sync.php).
		// id is the order's, as in every order.* event; the account, the
		// provider and the marketplace's own order and package ids follow.
		// Each is announced where it happens here, not when a later pull
		// sees it again: an order written for a package, a cancellation the
		// order took now, a shipment the shop reported with its own carrier.
		'marketplace.order.imported'  => 'A marketplace package was imported as an order, one event per package (a package cancelled before its first import writes no order and is not announced)',
		'marketplace.order.cancelled' => 'A marketplace cancelled an imported order and the order was cancelled here; order.cancelled is sent as well (not for one that had already shipped)',
		'marketplace.order.shipped'   => 'An order the shop ships with its own carrier was reported shipped to the marketplace, with its tracking link (a parcel of the marketplace\'s own carrier arrives as order.shipped)',
		// A cart quote (includes/fn/cart_quotes.php): products and quantities
		// sent to a customer as a link. id is the quote's; status, state,
		// contact_id, order_id, valid_until and the items (product_id,
		// quantity) follow, never the link. Announced from the cart quote
		// functions, so the screens, the API, the link page and the order's
		// completion alike. A new link and a quote running past its last day
		// are not announced (nothing marks it expired).
		'cart_quote.created'   => 'A cart quote was made, on the cart quotes screen or through the API',
		'cart_quote.applied'   => 'A cart quote\'s link put its products into a visitor\'s cart; added says how many lines went in (the same cart again is not announced)',
		'cart_quote.ordered'   => 'The cart a cart quote\'s link filled was ordered, from the checkout or a payment service\'s message; order_number names the order',
		'cart_quote.cancelled' => 'A cart quote was cancelled, on the cart quotes screen or through the API (one that runs past its last day is not announced)',
		// A store-run subscription (includes/fn/subscription_renewal.php,
		// pg_subscription_announce()). id is the subscription's; customer_id,
		// product_id, status, previous_status, kind (the history event),
		// order_id (the renewal order), amount, currency, next_charge_at and
		// retry_count follow, never the card. Announced once per written
		// event, whoever made the move: the renewal job, a payment service's
		// message, the customer in My Account, staff on the panel or the API.
		// A bounce or a spam complaint recorded for an address
		// (includes/fn/mail_bounces.php, pg_mail_bounce_record()). address,
		// kind (hard, soft, complaint), source, suppressed (whether mail to
		// the address is now stopped), message_id and campaign_id. A manual
		// block from the panel is not announced.
		'mail.bounced'     => 'A message to an address bounced; suppressed says whether mail to it is now stopped',
		'mail.complained'  => 'A recipient marked a message as spam: mail to the address is stopped and its e-mail marketing consent withdrawn',
		// A booking (includes/fn/booking_messages.php, pg_booking_changed()).
		// id is the booking's; service_id, staff_id, user_id, name, email,
		// start_at, end_at (UTC), status and source follow, never the manage
		// link. A visitor's hold is not announced, nor a reminder.
		'booking.created'     => 'A booking was made: a request, or a booking confirmed at once (booking.confirmed follows then); a visitor\'s hold of a slot is not announced',
		'booking.confirmed'   => 'A booking was confirmed, at once or after it was requested',
		'booking.rescheduled' => 'A booking was moved to another time; start_at and end_at are the new times',
		'booking.cancelled'   => 'A booking was cancelled, by the customer or by staff',
		'subscription.renewed'   => 'A subscription was charged for its next period; order_id names the renewal order',
		'subscription.failed'    => 'A subscription\'s renewal was refused or waits for the customer to confirm the payment; status ended when the last retry failed (the end itself is not announced again)',
		'subscription.cancelled' => 'A subscription was cancelled; it runs until next_charge_at and is not charged again (its end that day is not announced)',
		'subscription.paused'    => 'A subscription was paused: nothing is charged until it is resumed',
		'subscription.resumed'   => 'A paused subscription was resumed and is charged again from next_charge_at',
		// A customer's store credit (includes/fn/store_credit_events.php): id is
		// the card's (GET /gift-cards/{id}); account_id, customer_id, kind,
		// reason, amount, balance and expires_at follow, never the code.
		'credit.issued'  => 'Store credit was given to a customer: reward points converted, a refund paid as credit, or issued by staff on the customer\'s screen or through the API; GET /customers/{id}/credit lists the cards',
		'credit.used'    => 'An order spent a customer\'s store credit card; order_id and amount_used name the order and what it took, balance what is left (giving it back when the order is cancelled is not announced)',
		'credit.expired' => 'A store credit card reached its end with money left and was closed by the scheduled job; expired_amount is what was left (a card voided by staff, through the API or by an erasure is not announced)',
		// An affiliate commission's status (includes/fn/commission_list.php,
		// pg_commission_status_save()): id is the commission's; reference_code,
		// status, previous_status, affiliate_code, order_id and amount follow.
		// Announced where a person or an application moves it; an order's
		// cancellation voiding its commissions, and a restore giving them
		// back, are not (order.cancelled is), nor is a new commission.
		'commission.status_changed' => 'A commission was moved to another status on the commissions screen or through the API (not the voiding by an order\'s cancellation or its restore; a new commission is not announced)',
		// An affiliate approved (includes/fn/contacts.php, pg_affiliate_approve()
		// and the contact form's tick): id is the contact's; affiliate_code and
		// affiliate_name follow. An automatic approval on the sign-up form is
		// not announced; customer.created is.
		'affiliate.approved'        => 'A contact was approved as an affiliate from the approval link, on the contact form or through the API (not the sign-up form\'s automatic approval)',
		'product.created'      => 'A new product was added',
		'product.updated'      => 'A product was changed',
		// Moving a product to the Recycle Bin through the API announces this
		// too, with recycled: true in the data: the product leaves the listing
		// either way, and a receiver that only heard of outright deletions
		// would keep selling one the shop no longer offers.
		'product.deleted'      => 'A product was deleted, or moved to the Recycle Bin (recycled: true)',
		// A review of a product or of a group of options, as the visitor sends
		// it from the page: published at once or held for moderation (status).
		// Publishing it later, the store's reply and a removal are not
		// announced; GET /products/{id}/reviews lists them.
		'product.review.created' => 'A review of a product was written on its page (published or held for moderation; a later publication or the store\'s reply is not announced)',
		// Every stock writer on the site announces it: the checkout and a
		// local sale, the product screen and its list, a barcode scan, the
		// bulk edit of selected products (pg_product_stock_changed()), as the
		// stock API, the Workspace and an ERP document do. What stays quiet is
		// the bulk paths, for the reason the contact import does: the product
		// import, a marketplace order import or cancellation and an ERP stock
		// count. They still move products.timestamp, so GET
		// /products?updated_since= lists the product. The description says so.
		// On a site with stock locations the payload adds location_id,
		// location_name and location_quantity (pg_stock_event_location_fields());
		// quantity stays the total. A transfer between locations leaves the
		// total as it was and is not announced (GET /inventory reads it).
		'inventory.changed'    => 'A product stock level changed: a sale, a scan, a screen, the API, the Workspace or an ERP document (not product imports, marketplace order imports or a stock count; those products are listed by GET /products?updated_since=). quantity is the total; on a site with stock locations location_id, location_name and location_quantity name the location that changed. A transfer between locations is not announced: the total stays the same',
		// Only where the site has set ECOMMERCE_LOW_STOCK_THRESHOLD by hand: it
		// has no settings screen yet, and without a threshold there is no low to
		// cross. The cart widget reads the same constant for its own warning.
		'stock.low'            => 'A product fell to or below the low-stock threshold (a site that sets one)',
		// The address book is written from eight places - the checkout, express
		// checkout, the custom forms, the affiliate sign-up, the e-mail
		// preferences screen, a Google sign-in, the panel's contact screen and
		// this API - and every one of them announces the contact it made. What
		// stays quiet is the bulk paths: a contact import or a campaign upload
		// would put thousands of events on the queue, and a receiver learns more
		// from one listing than from the flood. The description says so, because
		// a subscription that silently misses a case is worse than one that is
		// honest about its edge.
		'customer.created'     => 'A customer record was created (not bulk imports)',
		'customer.updated'     => 'A customer record was changed through the API',
		'customer.deleted'     => 'A customer record was deleted through the API',
		// Panel accounts opened, changed or closed by an application; the
		// panel's own user screens do not announce.
		'user.created'         => 'A panel user account was created through the API',
		'user.updated'         => 'A panel user account was changed through the API',
		'user.deleted'         => 'A panel user account was deleted through the API',
		// Every row of the consent log a person gave (sign-up, account and
		// unsubscribe pages, checkout, custom forms) or İYS sent back; a
		// cookie answer names no customer, an import is a bulk path.
		'customer.consent_changed' => 'A customer gave or withdrew a consent: on a sign-up, account or unsubscribe page, at checkout, on a form, or as a refusal pulled back from İYS (not imports or cookie answers); GET /customers/{id}/consents lists them',
		// Announced where a person or an integration moves contacts on
		// purpose: the API and the panel's contact screens (add, edit, and
		// organizing selected contacts). The visitor-facing paths that file a
		// contact into a group - the checkout, the custom forms, the e-mail
		// preferences screen, the sign-ups - stay quiet, and so do the imports
		// and a contact's own deletion; the description says so.
		'contact_group.members_changed' => 'Contacts were put into or taken out of a contact group through the API or the contact screens (not forms, the checkout, e-mail preferences or imports)',
		'page.created'         => 'A page was created (not duplicated or imported pages)',
		// Page experiments (pg_experiment_sheet_move()): the panel and the
		// API alike. id, page_id, name and goal_kind follow; an end names
		// winner_variant_id (0 without a winner). A resume after a pause is
		// not a start.
		'experiment.started'   => 'A page experiment was started for the first time',
		'experiment.ended'     => 'A page experiment was ended, with or without a winner',
		'page.updated'         => 'A page was changed through the API',
		'product_group.created' => 'A product group was created through the API or the Workspace',
		'product_group.updated' => 'A product group was changed',
		'product_group.deleted' => 'A product group was deleted or moved to the Recycle Bin through the API',
		// Shipping zones and tax zones (includes/fn/store_zones.php): announced
		// from the shared write functions, so the zone screens and the API
		// alike. id and name follow.
		'zone.created'         => 'A shipping zone was created',
		'zone.updated'         => 'A shipping zone was changed: its name, rates, countries or states',
		'zone.deleted'         => 'A shipping zone was deleted',
		'tax_zone.created'     => 'A tax zone was created',
		'tax_zone.updated'     => 'A tax zone was changed: its name, rate, countries or states',
		'tax_zone.deleted'     => 'A tax zone was deleted',
		// Shipping methods (includes/fn/shipping_methods.php): announced from
		// the shared write functions, so the screens and the API alike.
		'shipping_method.created' => 'A shipping method was created',
		'shipping_method.updated' => 'A shipping method was changed',
		'shipping_method.deleted' => 'A shipping method was deleted',
		// Currencies (includes/fn/store_currencies.php): announced from the
		// shared write functions, so the currency screens, the rate refresh
		// (button and scheduled job) and the API alike. id, code,
		// exchange_rate and base follow; a deletion carries id and code.
		'currency.created'     => 'A currency was created',
		'currency.updated'     => 'A currency was changed, or the rate refresh moved its exchange rate',
		'currency.deleted'     => 'A currency was deleted',
		// Offers (edit_offer_f.php): announced from the offer editor's save and
		// delete, its on/off switch, the Workspace and the API alike. id, code
		// and enabled follow; a deletion carries id and code.
		'offer.created'        => 'An offer was created or duplicated',
		'offer.updated'        => 'An offer was changed or switched on or off',
		'offer.deleted'        => 'An offer was deleted',
		// Uploads from the panel's file screen and writes through the API alike.
		// A design or theme import brings its own files in bulk and stays quiet,
		// for the same reason the contact import does.
		'file.created'         => 'A file was uploaded (not design or theme imports)',
		'form.submitted'       => 'A visitor submitted a form',
		// The order's completion when the payment service confirmed it, or the
		// payment staff recorded for an offline one.
		'form.paid'            => 'The fee of a designed form\'s record was paid',
		// An order line that reserved a calendar event, once its order is
		// placed (includes/fn/order_completion.php,
		// pg_order_complete_line_reservation()): id is the event's;
		// recurrence_number, quantity, order_item_id, order_id, order_number
		// and contact_id follow. GET /calendars/events/{id}/reservations lists
		// them. A cancellation does not give the spots back and is not
		// announced here (order.cancelled is).
		'calendar_event.reserved' => 'A placed order reserved spots on a calendar event (a cart that is not ordered yet is not announced)',
		// The submitted form screens' deletes and the API's alike (includes/fn/
		// submitted_form_writes.php); undoing an import stays quiet.
		'form.submission.deleted' => 'A form submission was deleted',
		// An e-mail campaign whose last recipient was dealt with (the job or
		// Send now), or an SMS campaign the job handed to the SMS queue.
		// channel email or sms; read the figures from /campaigns.
		'campaign.sent'        => 'An e-mail campaign finished sending, or an SMS campaign was queued'
	);

	// A module announces its own events from its own file. The core list wins a
	// name it already uses, so a module cannot quietly redefine order.created.
	require_once(dirname(__FILE__) . '/../modules.php');

	return $events + api_module_contributions('webhook_events');

}

function api_webhook_event_is_valid($event) {

	$events = api_webhook_events();

	return isset($events[$event]);

}

// Put an event on the queue for every webhook that asked for it.
//
// Called from wherever the thing actually happens - the checkout, the order
// screen, the API's own writes - and deliberately cheap: a lookup and an insert
// per subscriber, no network.
//
// $recorded: the caller (pg_announce()) has kept the event for the workspace
// already. Everybody else calls this directly, so it is kept here, before the
// look for webhooks - a site with none is the usual case and its workspace
// still wants to hear about the order.
function api_webhook_enqueue($event, $payload, $recorded = false) {

	if (!defined('DB_CONNECTED') || !api_webhook_event_is_valid($event)) {

		return 0;

	}

	if (!$recorded && function_exists('pg_event_record')) {

		pg_event_record($event, (array)$payload);

	}

	// A site with no webhooks is the normal case, and it should cost one indexed
	// read and nothing else.
	//
	// The application is joined in rather than assumed. A subscription belongs to
	// one, and it has to answer two questions before anything is queued for it:
	// is that application still here, and is it still allowed in. Without the
	// join a deleted application leaves rows that no screen lists - the panel
	// shows subscriptions under their application - while this keeps delivering
	// them, and switching an application off stops it asking without stopping the
	// site from telling it.
	//
	// A subscription may be narrowed to one group of workspace channels
	// (channel_group_id, 2026.4.10). Before the upgrade has added the column
	// every subscription is a whole-site one, as it always was.
	$narrowed = api_webhook_channel_groups_ready();

	// An event meant for one application, such as its mention in a workspace
	// channel, is queued for that application's subscriptions and nobody
	// else's, whatever else listens to the event; one that names no
	// application is queued for nobody.
	$addressed = api_webhook_event_app_id($event, $payload);

	if ($addressed < 0) {

		return 0;

	}

	// A failing subscription is still told: its events are kept until the
	// operator fixes the address and retries (api_webhook_queue_hold()).
	$hooks = db_items("SELECT api_webhooks.id, api_webhooks.app_id, api_webhooks.events, api_webhooks.status"
		. ($narrowed ? ", api_webhooks.channel_group_id" : "") . "
		FROM api_webhooks
		INNER JOIN api_apps ON api_apps.id = api_webhooks.app_id
		WHERE api_webhooks.status IN ('active', 'failing') AND api_apps.status = 'active'");

	if (!is_array($hooks) || empty($hooks)) {

		return 0;

	}

	$body = api_webhook_body($event, $payload, gmdate('Y-m-d\TH:i:s\Z'));

	if ($body === null) {

		return 0;

	}

	$queued = 0;

	// The groups the event's channel is in, read once for all the narrowed
	// subscriptions and only when there is one.
	$groups = null;

	foreach ($hooks as $hook) {

		$events = json_decode((string)$hook['events'], true);

		if (!is_array($events) || !in_array($event, $events, true)) {

			continue;

		}

		if (($addressed > 0) && ((int)$hook['app_id'] !== $addressed)) {

			continue;

		}

		$group_id = isset($hook['channel_group_id']) ? (int)$hook['channel_group_id'] : 0;

		if ($group_id > 0) {

			if ($groups === null) {

				$groups = api_webhook_event_channel_groups($event, $payload);

			}

			if (!in_array($group_id, $groups, true)) {

				continue;

			}

		}

		db("INSERT INTO api_webhook_queue
			(webhook_id, event, payload, attempts, next_attempt_at, created_timestamp)
			VALUES (
				'" . (int)$hook['id'] . "',
				'" . escape($event) . "',
				'" . escape($body) . "',
				'0',
				" . (api_webhook_queue_hold($hook['status']) ? "'0'" : "UNIX_TIMESTAMP()") . ",
				UNIX_TIMESTAMP()
			)");

		$queued++;

	}

	return $queued;

}

// The body every subscriber of an event is sent: the envelope as JSON,
// written by pg_json_encode_answer() so that bytes which are not UTF-8 (a
// name cut inside a character, a legacy row) become U+FFFD instead of making
// json_encode() answer false, which queued and signed an empty body no
// receiver could read. Null when the payload cannot be written at all (INF or
// NAN): nothing is queued then. Pure.
function api_webhook_body($event, $payload, $created_at) {

	return pg_json_encode_answer(array(
		'event'      => (string)$event,
		'created_at' => (string)$created_at,
		'data'       => $payload
	));

}

// Whether an event for a subscription in this status is queued on hold rather
// than for sending: attempts 0 and next_attempt_at 0, which the dispatcher
// does not pick up. A failing subscription's address stopped answering; trying
// every new event against it would only add six failures each, and dropping
// them is how an integration is found broken a week later. Held rows go out
// only when somebody retries (Send again on the Events tab, POST
// /webhooks/{id}/retry): not on a successful delivery, a resume or PATCH
// status=active, so events meant for an old address never reach a new one
// unasked. Like a row that ran out of attempts, one held for a week is swept.
function api_webhook_queue_hold($status) {

	return ((string)$status === 'failing');

}

// Has the database the column that narrows a subscription to a group of
// workspace channels (2026.4.10, 10.76)? Asked before any read names it.
function api_webhook_channel_groups_ready() {

	return function_exists('pg_schema_has') && pg_schema_has('api_webhooks', 'channel_group_id');

}

// The workspace channel groups an event happened in, nearest first: what a
// narrowed subscription is matched against. For an event about a group itself
// (workspace.channel_group.*) that is the group and the groups it is in. The
// workspace answers it (ws_webhook_event_groups()); with the workspace
// switched off, or for an event that happened in no channel, it is empty and
// a narrowed subscription hears nothing.
function api_webhook_event_channel_groups($event, $payload) {

	if (!function_exists('ws_webhook_event_groups')) {

		return array();

	}

	return array_map('intval', (array)ws_webhook_event_groups($event, (array)$payload));

}

// The one application an event is for: its id, 0 when every subscription
// listening to the event hears it, -1 when it is for an application the event
// does not name, which nobody hears. Only the workspace addresses an event
// today - a mention of an application goes to that application alone
// (ws_webhook_event_app_id()). Its events are in the catalogue only while
// its api.php is loaded (api_module_contributions()), and that loads the
// function asked here, so a workspace event is never valid without it; with
// the workspace off every event is for every subscriber, as before.
function api_webhook_event_app_id($event, $payload) {

	if (!function_exists('ws_webhook_event_app_id')) {

		return 0;

	}

	return (int)ws_webhook_event_app_id($event, (array)$payload);

}

// Everything an application's subscriptions own, removed with it.
//
// Deleting the application row on its own is what leaves a subscription behind
// with nothing pointing at it: the panel lists subscriptions under their
// application, so a row whose application is gone cannot be seen or stopped
// there, and until the dispatcher learned to check, the address kept being
// delivered to. Called from every path that removes an application.
function api_webhooks_delete_for_app($app_id) {

	$app_id = (int)$app_id;

	if ($app_id < 1) {

		return 0;

	}

	$ids = db_items("SELECT id FROM api_webhooks WHERE app_id = '" . $app_id . "'");

	if (!is_array($ids) || empty($ids)) {

		return 0;

	}

	$list = array();

	foreach ($ids as $row) {

		$list[] = (int)$row['id'];

	}

	db("DELETE FROM api_webhook_queue WHERE webhook_id IN (" . implode(',', $list) . ")");

	db("DELETE FROM api_webhooks WHERE app_id = '" . $app_id . "'");

	return count($list);

}

// The signature a receiver checks.
//
// The timestamp is signed with the body rather than sent beside it, so a
// captured call cannot be replayed tomorrow with the clock moved on: the
// receiver refuses anything whose timestamp is not within a few minutes, and the
// timestamp cannot be edited without breaking the signature.
function api_webhook_signature($secret, $timestamp, $body) {

	return hash_hmac('sha256', $timestamp . '.' . $body, $secret);

}

// How long to wait before trying again. Six attempts over about a day and a
// half: long enough to ride out a receiver's maintenance window, short enough
// that a dead address stops being retried.
function api_webhook_backoff($attempts) {

	$schedule = array(60, 300, 1800, 7200, 21600, 86400);

	$index = (int)$attempts - 1;

	if ($index < 0) { $index = 0; }

	if ($index >= count($schedule)) {

		return 0;

	}

	return $schedule[$index];

}

function api_webhook_max_attempts() {

	return 6;

}

// The activity log line for a delivery given up after its last attempt. The
// address is cut to scheme and host (pg_ops_alert_mask_url()): the log is read
// by every manager and by API apps with site_log:read, and Slack, Discord and
// Zapier addresses carry their secret in the path. Pure.
function api_webhook_given_up_note($url, $attempts, $error) {

	return lang(array(
		'string' => 'A webhook delivery to {var:1} was given up after {var:2} attempts: {var:3}',
		'vars'   => array(pg_ops_alert_mask_url($url), (int)$attempts, (string)$error)
	));

}

// Send one queued row. Answers true when it is done with - delivered, or given
// up on - and false when it is waiting for another attempt.
function api_webhook_deliver($row) {

	// A chat notification (Slack, Teams, Telegram): its target is a sealed
	// entry, not a subscription, and its body is sent as built, without the
	// envelope or a signature (includes/fn/notify_channels.php).
	if ((int)$row['webhook_id'] === 0) {

		return function_exists('pg_notify_deliver') ? pg_notify_deliver($row) : true;

	}

	$hook = db_item("SELECT api_webhooks.id, api_webhooks.app_id, api_webhooks.url,
			api_webhooks.secret, api_webhooks.status, api_apps.status AS app_status
		FROM api_webhooks
		LEFT JOIN api_apps ON api_apps.id = api_webhooks.app_id
		WHERE api_webhooks.id = '" . (int)$row['webhook_id'] . "' LIMIT 1");

	if (!$hook || $hook['status'] === 'disabled' || $hook['app_status'] !== 'active') {

		// The subscription is gone or switched off, or the application behind it
		// is; the queued event has nowhere to go and is dropped rather than
		// retried forever. The application is checked here as well as at queue
		// time because a row queued a minute ago outlives the application that
		// asked for it. A missing application leaves app_status null, which is
		// not 'active' either.
		db("DELETE FROM api_webhook_queue WHERE id = '" . (int)$row['id'] . "'");

		return true;

	}

	$body = (string)$row['payload'];

	$timestamp = (string)time();

	$headers = array(
		'Content-Type: application/json',
		'X-Pinegrap-Event: ' . $row['event'],
		'X-Pinegrap-Delivery: ' . (int)$row['id'],
		'X-Pinegrap-Signature: t=' . $timestamp . ',v1=' . api_webhook_signature($hook['secret'], $timestamp, $body)
	);

	$outcome = api_http_post($hook['url'], $body, $headers, 10);

	$attempts = (int)$row['attempts'] + 1;

	if ($outcome['ok']) {

		db("DELETE FROM api_webhook_queue WHERE id = '" . (int)$row['id'] . "'");

		db("UPDATE api_webhooks SET status = 'active', last_success = UNIX_TIMESTAMP()
			WHERE id = '" . (int)$hook['id'] . "'");

		return true;

	}

	$error = ($outcome['error'] !== '') ? $outcome['error'] : ('HTTP ' . $outcome['status']);

	$retry_in = api_http_should_retry($outcome) ? api_webhook_backoff($attempts) : 0;

	$exhausted = ($retry_in === 0 || $attempts >= api_webhook_max_attempts());

	db("UPDATE api_webhook_queue SET
			attempts = '" . $attempts . "',
			last_status_code = '" . (int)$outcome['status'] . "',
			last_error = '" . escape(substr($error, 0, 500)) . "',
			next_attempt_at = '" . ($exhausted ? 0 : (time() + $retry_in)) . "'
		WHERE id = '" . (int)$row['id'] . "'");

	db("UPDATE api_webhooks SET last_failure = UNIX_TIMESTAMP()
		WHERE id = '" . (int)$hook['id'] . "'");

	if ($exhausted) {

		// The subscription is marked, not deleted: the operator has to see that
		// their address stopped answering, and silently dropping events is how
		// an integration is discovered to be broken a week later.
		db("UPDATE api_webhooks SET status = 'failing' WHERE id = '" . (int)$hook['id'] . "'");

		log_activity(api_webhook_given_up_note($hook['url'], $attempts, $error), 'SYSTEM');

		return true;

	}

	return false;

}

// The cron's pass. Bounded by wall clock rather than row count: a run that
// starts is expected to end, and a hundred slow receivers would otherwise hold
// the job open past the next tick.
function api_webhook_dispatch($seconds = 20) {

	if (!defined('DB_CONNECTED')) {

		return array('sent' => 0, 'failed' => 0);

	}

	$deadline = microtime(true) + $seconds;

	$sent = 0;

	$failed = 0;

	while (microtime(true) < $deadline) {

		$row = db_item("SELECT id, webhook_id, event, payload, attempts, next_attempt_at
			FROM api_webhook_queue
			WHERE next_attempt_at > 0 AND next_attempt_at <= UNIX_TIMESTAMP()
			  AND attempts < '" . api_webhook_max_attempts() . "'
			ORDER BY next_attempt_at ASC, id ASC
			LIMIT 1");

		if (!$row) {

			break;

		}

		// Claimed before it is sent, so a second cron overlapping this one does
		// not deliver the same row twice. The claim holds only while the row
		// still has the time this run read: a run that read it too and claimed
		// it first has moved it on, and this one leaves it.
		db("UPDATE api_webhook_queue SET next_attempt_at = UNIX_TIMESTAMP() + 120
			WHERE id = '" . (int)$row['id'] . "' AND next_attempt_at = '" . (int)$row['next_attempt_at'] . "'");

		if (mysqli_affected_rows(db::$con) !== 1) {

			continue;

		}

		if (api_webhook_deliver($row)) {

			$sent++;

		} else {

			$failed++;

		}

	}

	// Rows that ran out of attempts are kept for a week so the panel can show
	// what happened, then swept.
	db("DELETE FROM api_webhook_queue
		WHERE next_attempt_at = 0 AND created_timestamp < '" . (time() - 604800) . "'");

	return array('sent' => $sent, 'failed' => $failed);

}
