<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// What each event of the catalogue (api_webhook_events()) carries in data:
// its fields, each with a type and a meaning, and one sample. GET
// /webhooks/events lists them for an application's own event chooser, and
// POST /webhooks/{id}/test sends the sample of one of a subscription's events.
//
// The fields are the keys the announcing code writes, every place that
// announces the event together; tests/webhook_event_payloads_test.php reads
// that code and fails when the two part. A field only some places write says
// so in its meaning. A type ending in ? may be null.

if (!defined('PG_INIT_LOADED')) {
	exit;
}

function api_webhook_event_payloads() {

	$order = array(
		'id'           => array('integer', 'The order (GET /orders/{id}).'),
		'order_number' => array('string', 'The order number customers see (a counter sale sends it as a number).')
	);

	$order_sample = array('id' => 1042, 'order_number' => '100318');

	$parcels = array(
		'parcels'               => array('object[]', 'The address\'s numbered parcels: number, carrier (a key, null when not known), carrier_name, tracking_url, status, shipped_at and delivered_at (UTC, null when not known), as GET /orders/{id} gives them.'),
		'tracking_numbers_text' => array('string', 'The address\'s tracking numbers joined into one text.'),
		'carrier_name'          => array('string', 'The carrier\'s name, empty when not known.'),
		'tracking_url'          => array('string', 'The carrier\'s tracking page, empty when not known.')
	);

	$parcel_sample = array(
		'parcels'               => array(array('number' => '7350012345', 'carrier' => 'yurtici', 'carrier_name' => 'Yurtiçi Kargo', 'tracking_url' => 'https://www.yurticikargo.com/tr/online-servisler/gonderi-sorgula?code=7350012345', 'status' => 'in_transit', 'shipped_at' => '2026-11-17T09:30:00Z', 'delivered_at' => null)),
		'tracking_numbers_text' => '7350012345',
		'carrier_name'          => 'Yurtiçi Kargo',
		'tracking_url'          => 'https://www.yurticikargo.com/tr/online-servisler/gonderi-sorgula?code=7350012345'
	);

	$return = $order + array(
		'return_id'  => array('integer', 'The return (GET /returns/{id}).'),
		'ship_to_id' => array('integer', 'The shipping address of the order the return is about.'),
		'status'     => array('string', 'The return\'s status after the move: requested, approved, received or refunded.'),
		'source'     => array('string', 'Who recorded the request: account (the customer in My Account), panel or api.'),
		'reason'     => array('string', 'The reason\'s key, such as damaged or wrong_item.'),
		'items'      => array('object[]', 'The returned lines: order_item_id and quantity.')
	);

	$return_sample = function ($status) use ($order_sample) {
		return $order_sample + array('return_id' => 77, 'ship_to_id' => 1290, 'status' => $status, 'source' => 'account', 'reason' => 'damaged', 'items' => array(array('order_item_id' => 5521, 'quantity' => 1)));
	};

	$marketplace = $order + array(
		'account_id'        => array('integer', 'The marketplace account the package came through.'),
		'provider'          => array('string', 'The marketplace, such as trendyol or hepsiburada.'),
		'remote_order_id'   => array('string', 'The marketplace\'s own order id.'),
		'remote_package_id' => array('string', 'The marketplace\'s own package id.'),
		'remote_status'     => array('string', 'The package\'s status at the marketplace when the event happened.')
	);

	$marketplace_sample = function ($status) use ($order_sample) {
		return $order_sample + array('account_id' => 2, 'provider' => 'trendyol', 'remote_order_id' => '10457726385', 'remote_package_id' => '3218801254', 'remote_status' => $status);
	};

	$quote = array(
		'id'          => array('integer', 'The cart quote (GET /cart-quotes/{id}).'),
		'status'      => array('string', 'The stored status: open, ordered, expired or cancelled.'),
		'state'       => array('string', 'The status as it stands now: an open quote past its last day is expired.'),
		'contact_id'  => array('integer', 'The contact the quote is for, 0 for nobody named.'),
		'order_id'    => array('integer', 'The order the quote\'s cart became, 0 for none yet.'),
		'valid_until' => array('string?', 'The quote\'s last moment (UTC), null for no end.'),
		'items'       => array('object[]', 'The quote\'s lines: product_id and quantity. The link itself is never sent.')
	);

	$quote_sample = function ($status, $order_id) {
		return array('id' => 31, 'status' => $status, 'state' => $status, 'contact_id' => 884, 'order_id' => $order_id, 'valid_until' => '2026-11-30T21:00:00Z', 'items' => array(array('product_id' => 215, 'quantity' => 2), array('product_id' => 9, 'quantity' => 1)));
	};

	$booking = array(
		'id'           => array('integer', 'The booking.'),
		'service_id'   => array('integer', 'The service booked.'),
		'staff_id'     => array('integer', 'The member of staff, 0 for anyone.'),
		'user_id'      => array('integer', 'The customer\'s account, 0 for a guest.'),
		'name'         => array('string', 'The name the customer gave.'),
		'email'        => array('string', 'The e-mail address the customer gave.'),
		'start_at'     => array('string', 'The start (UTC).'),
		'end_at'       => array('string', 'The end (UTC).'),
		'status'       => array('string', 'The booking\'s status: requested, confirmed, cancelled, no_show or done.'),
		'source'       => array('string', 'Where it was made, such as site or panel.'),
		'service_name' => array('string', 'The service\'s name, empty when not known.'),
		'staff_name'   => array('string', 'The staff member\'s name, empty when not known.')
	);

	$booking_sample = function ($status, $start, $end) {
		return array('id' => 418, 'service_id' => 3, 'staff_id' => 2, 'user_id' => 0, 'name' => 'Ayşe Demir', 'email' => 'ayse@example.com', 'start_at' => $start, 'end_at' => $end, 'status' => $status, 'source' => 'site', 'service_name' => 'Haircut', 'staff_name' => 'Deniz');
	};

	$subscription = array(
		'id'              => array('integer', 'The subscription (GET /customers/{id}/subscriptions lists them).'),
		'customer_id'     => array('integer?', 'The customer record of the subscription\'s account, null for none.'),
		'product_id'      => array('integer', 'The product subscribed to.'),
		'status'          => array('string', 'The status after the event: active, paused, cancelled or ended.'),
		'previous_status' => array('string', 'The status before the event.'),
		'kind'            => array('string', 'What was written: renewed, renewal_failed, confirm_needed, status_paused, status_active or status_cancelled.'),
		'order_id'        => array('integer?', 'The renewal order, null for none.'),
		'amount'          => array('integer', 'The amount of one period, in minor units (cents).'),
		'currency'        => array('string', 'The currency code of the amount.'),
		'next_charge_at'  => array('string?', 'The next charge (UTC), null for none.'),
		'retry_count'     => array('integer', 'How many renewal attempts failed in a row.'),
		'product_name'    => array('string', 'The product\'s name.'),
		'order_number'    => array('string', 'The renewal order\'s number, empty for none.')
	);

	$subscription_sample = function ($status, $before, $kind, $order_id, $order_number, $next, $retries) {
		return array('id' => 56, 'customer_id' => 884, 'product_id' => 120, 'status' => $status, 'previous_status' => $before, 'kind' => $kind, 'order_id' => $order_id, 'amount' => 14900, 'currency' => 'TRY', 'next_charge_at' => $next, 'retry_count' => $retries, 'product_name' => 'Coffee club, monthly', 'order_number' => $order_number);
	};

	$credit = array(
		'id'          => array('integer', 'The store credit card (GET /gift-cards/{id}). Its code is never sent.'),
		'account_id'  => array('integer', 'The customer\'s account.'),
		'customer_id' => array('integer?', 'The account\'s customer record, null for none.'),
		'kind'        => array('string', 'How the credit came: gift, points, refund or manual.'),
		'reason'      => array('string', 'The note given with it, empty for none.'),
		'amount'      => array('integer', 'The amount given, in minor units (cents).'),
		'balance'     => array('integer', 'What is left on the card, in minor units (cents).'),
		'expires_at'  => array('string?', 'When the card ends (UTC), null for never.'),
		'source'      => array('string', 'The name of the kind, in the site\'s language.'),
		'name'        => array('string', 'The customer\'s name, empty when not known.')
	);

	$credit_sample = function ($balance) {
		return array('id' => 92, 'account_id' => 310, 'customer_id' => 884, 'kind' => 'refund', 'reason' => '', 'amount' => 25000, 'balance' => $balance, 'expires_at' => '2027-11-17T20:59:59Z', 'source' => 'Refund', 'name' => 'Ayşe Demir');
	};

	$product = array(
		'id'   => array('integer', 'The product (GET /products/{id}).'),
		'name' => array('string', 'The product\'s name.')
	);

	$group = array(
		'id'   => array('integer', 'The product group (GET /product-groups/{id}).'),
		'name' => array('string', 'The product group\'s name.')
	);

	$location = array(
		'location_id'       => array('integer', 'Only on a site with stock locations: the location that changed.'),
		'location_name'     => array('string', 'Only on a site with stock locations: that location\'s name.'),
		'location_quantity' => array('integer', 'Only on a site with stock locations: the stock left there.')
	);

	$location_sample = array('location_id' => 2, 'location_name' => 'Main depot', 'location_quantity' => 3);

	return array(

		'order.created' => array('fields' => $order, 'sample' => $order_sample),

		'order.status_changed' => array(
			'fields' => $order + array(
				'status'   => array('string', 'The status now: complete, exported or cancelled.'),
				'previous' => array('string', 'The status before.')
			),
			'sample' => $order_sample + array('status' => 'exported', 'previous' => 'complete')
		),

		'order.shipped' => array(
			'fields' => $order + array(
				'ship_to_id'       => array('integer', 'The shipping address that gained a tracking number.'),
				'tracking_numbers' => array('string[]', 'The address\'s tracking numbers; from a marketplace import only the one announced.'),
				'ship_date'        => array('string?', 'The ship date (YYYY-MM-DD), null when none was entered.')
			) + $parcels,
			'sample' => $order_sample + array('ship_to_id' => 1290, 'tracking_numbers' => array('7350012345'), 'ship_date' => '2026-11-17') + $parcel_sample
		),

		'order.delivered' => array(
			'fields' => $order + array(
				'ship_to_id'    => array('integer', 'The shipping address that was delivered.'),
				'delivery_date' => array('string', 'The day the goods arrived (YYYY-MM-DD).')
			) + $parcels,
			'sample' => $order_sample + array('ship_to_id' => 1290, 'delivery_date' => '2026-11-19') + $parcel_sample
		),

		'order.cancelled' => array(
			'fields' => $order + array(
				'reason'        => array('string', 'The reason given, empty for none.'),
				'refund_status' => array('string', 'What happened to the payment: empty (nothing to refund), pending, refunded, failed or manual_required.')
			),
			'sample' => $order_sample + array('reason' => 'Customer asked to cancel', 'refund_status' => 'refunded')
		),

		'order.return.requested' => array('fields' => $return, 'sample' => $return_sample('requested')),
		'order.return.approved'  => array('fields' => $return, 'sample' => $return_sample('approved')),
		'order.return.received'  => array('fields' => $return, 'sample' => $return_sample('received')),

		'order.return.refunded' => array(
			'fields' => $return + array(
				'amount'        => array('integer', 'The amount refunded, in minor units (cents).'),
				'refund_method' => array('string', 'How it went back: driver (the payment service), manual or credit (store credit).'),
				'unclear'       => array('boolean', 'True when the payment service gave no clear answer and the refund is booked unconfirmed.')
			),
			'sample' => $return_sample('refunded') + array('amount' => 34900, 'refund_method' => 'driver', 'unclear' => false)
		),

		'marketplace.order.imported'  => array('fields' => $marketplace, 'sample' => $marketplace_sample('Created')),
		'marketplace.order.cancelled' => array('fields' => $marketplace, 'sample' => $marketplace_sample('Cancelled')),

		'marketplace.order.shipped' => array(
			'fields' => $marketplace + array(
				'tracking_url' => array('string', 'The tracking link reported to the marketplace.')
			),
			'sample' => $marketplace_sample('Shipped') + array('tracking_url' => 'https://www.yurticikargo.com/tr/online-servisler/gonderi-sorgula?code=7350012345')
		),

		'cart_quote.created' => array('fields' => $quote, 'sample' => $quote_sample('open', 0)),

		'cart_quote.applied' => array(
			'fields' => $quote + array(
				'added' => array('integer', 'How many lines went into the cart.')
			),
			'sample' => $quote_sample('open', 0) + array('added' => 2)
		),

		'cart_quote.ordered' => array(
			'fields' => $quote + array(
				'order_number' => array('string', 'The number of the order the cart became.')
			),
			'sample' => $quote_sample('ordered', 1042) + array('order_number' => '100318')
		),

		'cart_quote.cancelled' => array('fields' => $quote, 'sample' => $quote_sample('cancelled', 0)),

		'booking.created'     => array('fields' => $booking, 'sample' => $booking_sample('requested', '2026-11-20T07:00:00Z', '2026-11-20T07:45:00Z')),
		'booking.confirmed'   => array('fields' => $booking, 'sample' => $booking_sample('confirmed', '2026-11-20T07:00:00Z', '2026-11-20T07:45:00Z')),
		'booking.rescheduled' => array('fields' => $booking, 'sample' => $booking_sample('confirmed', '2026-11-21T11:00:00Z', '2026-11-21T11:45:00Z')),
		'booking.cancelled'   => array('fields' => $booking, 'sample' => $booking_sample('cancelled', '2026-11-20T07:00:00Z', '2026-11-20T07:45:00Z')),

		'subscription.renewed'   => array('fields' => $subscription, 'sample' => $subscription_sample('active', 'active', 'renewed', 1043, '100319', '2026-12-17T08:00:00Z', 0)),
		'subscription.failed'    => array('fields' => $subscription, 'sample' => $subscription_sample('active', 'active', 'renewal_failed', null, '', '2026-11-18T08:00:00Z', 1)),
		'subscription.cancelled' => array('fields' => $subscription, 'sample' => $subscription_sample('cancelled', 'active', 'status_cancelled', null, '', '2026-12-17T08:00:00Z', 0)),
		'subscription.paused'    => array('fields' => $subscription, 'sample' => $subscription_sample('paused', 'active', 'status_paused', null, '', null, 0)),
		'subscription.resumed'   => array('fields' => $subscription, 'sample' => $subscription_sample('active', 'paused', 'status_active', null, '', '2026-12-17T08:00:00Z', 0)),

		'credit.issued' => array('fields' => $credit, 'sample' => $credit_sample(25000)),

		'credit.used' => array(
			'fields' => $credit + array(
				'order_id'    => array('integer', 'The order that spent the credit.'),
				'amount_used' => array('integer', 'What the order took, in minor units (cents).')
			),
			'sample' => $credit_sample(15100) + array('order_id' => 1042, 'amount_used' => 9900)
		),

		'credit.expired' => array(
			'fields' => $credit + array(
				'expired_amount' => array('integer', 'What was left when the card ended, in minor units (cents).')
			),
			'sample' => $credit_sample(0) + array('expired_amount' => 15100)
		),

		'commission.status_changed' => array(
			'fields' => array(
				'id'              => array('integer', 'The commission (GET /commissions/{id}).'),
				'reference_code'  => array('string', 'The commission\'s reference code.'),
				'status'          => array('string', 'The status now: pending, payable, ineligible or paid.'),
				'previous_status' => array('string', 'The status before.'),
				'affiliate_code'  => array('string', 'The affiliate\'s code.'),
				'order_id'        => array('integer?', 'The order the commission is for, null for none.'),
				'amount'          => array('integer', 'The commission, in minor units (cents).')
			),
			'sample' => array('id' => 64, 'reference_code' => 'C7Q2X9', 'status' => 'payable', 'previous_status' => 'pending', 'affiliate_code' => 'ayse10', 'order_id' => 1042, 'amount' => 3490)
		),

		'product.created' => array('fields' => $product, 'sample' => array('id' => 215, 'name' => 'Ceramic mug')),
		'product.updated' => array('fields' => $product, 'sample' => array('id' => 215, 'name' => 'Ceramic mug')),

		'product.deleted' => array(
			'fields' => $product + array(
				'recycled' => array('boolean', 'True when the product was moved to the Recycle Bin; not sent by the product screen\'s delete.')
			),
			'sample' => array('id' => 215, 'name' => 'Ceramic mug', 'recycled' => true)
		),

		'product.review.created' => array(
			'fields' => array(
				'id'        => array('integer', 'The review (GET /comments/{id}).'),
				'item_type' => array('string', 'What it reviews: product or product_group.'),
				'item_id'   => array('integer', 'The product or product group reviewed.'),
				'rating'    => array('integer?', 'The stars given, 1 to 5, null for none.'),
				'verified'  => array('boolean', 'Whether the reviewer bought it.'),
				'status'    => array('string', 'published, pending (held for moderation) or scheduled.')
			),
			'sample' => array('id' => 3301, 'item_type' => 'product', 'item_id' => 215, 'rating' => 5, 'verified' => true, 'status' => 'pending')
		),

		'product_group.created' => array('fields' => $group, 'sample' => array('id' => 40, 'name' => 'Mugs')),
		'product_group.updated' => array('fields' => $group, 'sample' => array('id' => 40, 'name' => 'Mugs')),

		'product_group.deleted' => array(
			'fields' => $group + array(
				'recycled' => array('boolean', 'True when the group was moved to the Recycle Bin.')
			),
			'sample' => array('id' => 40, 'name' => 'Mugs', 'recycled' => false)
		),

		'inventory.changed' => array(
			'fields' => array(
				'product_id'   => array('integer', 'The product (GET /products/{id}).'),
				'quantity'     => array('integer', 'The stock left, all locations together.'),
				'out_of_stock' => array('boolean', 'Whether nothing is left.'),
				'source'       => array('string', 'Only from the site\'s own writers: checkout, local_sale, panel, barcode, bulk_edit or bundle.')
			) + $location,
			'sample' => array('product_id' => 215, 'quantity' => 4, 'out_of_stock' => false, 'source' => 'checkout') + $location_sample
		),

		'stock.low' => array(
			'fields' => array(
				'product_id' => array('integer', 'The product (GET /products/{id}).'),
				'name'       => array('string', 'The product\'s name.'),
				'quantity'   => array('integer', 'The stock left, all locations together.'),
				'threshold'  => array('integer', 'The low-stock threshold it fell to or below.')
			) + $location,
			'sample' => array('product_id' => 215, 'name' => 'Ceramic mug', 'quantity' => 4, 'threshold' => 5) + $location_sample
		)

	) + api_webhook_event_payloads_site();

}

// The rest of the core's events: customers and users, contact groups and
// consents, pages and content, the store's settings, files, forms, calendar
// reservations, campaigns, mail and WhatsApp.
function api_webhook_event_payloads_site() {

	$customer = array(
		'id'    => array('integer', 'The customer record (GET /customers/{id}).'),
		'email' => array('string', 'The customer\'s e-mail address, empty for none.')
	);

	$customer_sample = array('id' => 884, 'email' => 'ayse@example.com');

	$user = array(
		'id'       => array('integer', 'The panel user account (GET /users/{id}).'),
		'username' => array('string', 'The account\'s username.'),
		'email'    => array('string', 'The account\'s e-mail address.'),
		'role'     => array('string', 'administrator, designer, manager or user.')
	);

	$user_sample = array('id' => 12, 'username' => 'deniz', 'email' => 'deniz@example.com', 'role' => 'manager');

	$page = array(
		'id'   => array('integer', 'The page (GET /pages/{id}).'),
		'name' => array('string', 'The page\'s name.')
	);

	$experiment = array(
		'id'                => array('integer', 'The page experiment.'),
		'page_id'           => array('integer', 'The page it tests.'),
		'name'              => array('string', 'The experiment\'s name.'),
		'goal_kind'         => array('string', 'What counts as a success, such as a form submission or an order.'),
		'winner_variant_id' => array('integer', 'On an end, the winning variant, 0 for none; 0 on a start.')
	);

	$named = function ($what, $route) {
		return array(
			'id'   => array('integer', 'The ' . $what . ($route !== '' ? ' (' . $route . ').' : '.')),
			'name' => array('string', 'The ' . $what . '\'s name.')
		);
	};

	$currency = array(
		'id'            => array('integer', 'The currency.'),
		'code'          => array('string', 'The currency code, such as EUR.'),
		'exchange_rate' => array('number', 'Its exchange rate against the base currency.'),
		'base'          => array('boolean', 'Whether it is the site\'s base currency.')
	);

	$offer = array(
		'id'      => array('integer', 'The offer (GET /offers/{id}).'),
		'code'    => array('string', 'The offer\'s code.'),
		'enabled' => array('boolean', 'Whether the offer is switched on.')
	);

	$mail = array(
		'address'     => array('string', 'The address the message went to.'),
		'kind'        => array('string', 'hard, soft or complaint.'),
		'source'      => array('string', 'What reported it, such as a mail service\'s report.'),
		'suppressed'  => array('boolean', 'Whether mail to the address is now stopped.'),
		'message_id'  => array('string', 'The message\'s id at the mail service, empty when not known.'),
		'campaign_id' => array('integer', 'The e-mail campaign the message belonged to, 0 for none.')
	);

	$mail_sample = function ($kind) {
		return array('address' => 'ayse@example.com', 'kind' => $kind, 'source' => 'ses', 'suppressed' => true, 'message_id' => '0102018f3c2a9b1e', 'campaign_id' => 15);
	};

	return array(

		'customer.created' => array('fields' => $customer, 'sample' => $customer_sample),
		'customer.updated' => array('fields' => $customer, 'sample' => $customer_sample),
		'customer.deleted' => array('fields' => $customer, 'sample' => $customer_sample),

		'customer.consent_changed' => array(
			'fields' => array(
				'id'      => array('integer', 'The customer record (GET /customers/{id}/consents lists the answers).'),
				'consent' => array('object', 'The answer: id, channel, purpose, status, source and created_at (UTC).')
			),
			'sample' => array('id' => 884, 'consent' => array('id' => 3120, 'channel' => 'email', 'purpose' => 'marketing', 'status' => 'granted', 'source' => 'checkout', 'created_at' => '2026-11-17T09:12:00Z'))
		),

		'affiliate.approved' => array(
			'fields' => array(
				'id'             => array('integer', 'The contact approved as an affiliate.'),
				'affiliate_code' => array('string', 'The affiliate\'s code.'),
				'affiliate_name' => array('string?', 'The affiliate\'s name, null for none.')
			),
			'sample' => array('id' => 884, 'affiliate_code' => 'ayse10', 'affiliate_name' => 'Ayşe Demir')
		),

		'contact_group.members_changed' => array(
			'fields' => array(
				'id'      => array('integer', 'The contact group.'),
				'name'    => array('string', 'The contact group\'s name.'),
				'added'   => array('integer[]', 'The contacts put into the group.'),
				'removed' => array('integer[]', 'The contacts taken out of the group.')
			),
			'sample' => array('id' => 6, 'name' => 'Newsletter', 'added' => array(884, 885), 'removed' => array())
		),

		'user.created' => array('fields' => $user, 'sample' => $user_sample),
		'user.updated' => array('fields' => $user, 'sample' => $user_sample),
		'user.deleted' => array('fields' => $user, 'sample' => $user_sample),

		'page.created' => array('fields' => $page, 'sample' => array('id' => 57, 'name' => 'about-us')),
		'page.updated' => array('fields' => $page, 'sample' => array('id' => 57, 'name' => 'about-us')),

		'experiment.started' => array('fields' => $experiment, 'sample' => array('id' => 8, 'page_id' => 57, 'name' => 'Shorter headline', 'goal_kind' => 'form', 'winner_variant_id' => 0)),
		'experiment.ended'   => array('fields' => $experiment, 'sample' => array('id' => 8, 'page_id' => 57, 'name' => 'Shorter headline', 'goal_kind' => 'form', 'winner_variant_id' => 21)),

		'content.review_due' => array(
			'fields' => array(
				'id'        => array('integer', 'The page or collection record.'),
				'kind'      => array('string', 'page or record.'),
				'name'      => array('string', 'Its name.'),
				'user_id'   => array('integer', 'The person who reviews it, 0 for nobody.'),
				'person'    => array('string', 'That person\'s name, empty for nobody.'),
				'review_on' => array('string', 'The review date that passed (YYYY-MM-DD).')
			),
			'sample' => array('id' => 57, 'kind' => 'page', 'name' => 'about-us', 'user_id' => 12, 'person' => 'Deniz', 'review_on' => '2026-11-16')
		),

		'zone.created' => array('fields' => $named('shipping zone', ''), 'sample' => array('id' => 3, 'name' => 'Europe')),
		'zone.updated' => array('fields' => $named('shipping zone', ''), 'sample' => array('id' => 3, 'name' => 'Europe')),
		'zone.deleted' => array('fields' => $named('shipping zone', ''), 'sample' => array('id' => 3, 'name' => 'Europe')),

		'tax_zone.created' => array('fields' => $named('tax zone', ''), 'sample' => array('id' => 2, 'name' => 'EU VAT')),
		'tax_zone.updated' => array('fields' => $named('tax zone', ''), 'sample' => array('id' => 2, 'name' => 'EU VAT')),
		'tax_zone.deleted' => array('fields' => $named('tax zone', ''), 'sample' => array('id' => 2, 'name' => 'EU VAT')),

		'shipping_method.created' => array('fields' => $named('shipping method', ''), 'sample' => array('id' => 4, 'name' => 'Express')),
		'shipping_method.updated' => array('fields' => $named('shipping method', ''), 'sample' => array('id' => 4, 'name' => 'Express')),
		'shipping_method.deleted' => array('fields' => $named('shipping method', ''), 'sample' => array('id' => 4, 'name' => 'Express')),

		'currency.created' => array('fields' => $currency, 'sample' => array('id' => 2, 'code' => 'EUR', 'exchange_rate' => 0.0281, 'base' => false)),
		'currency.updated' => array('fields' => $currency, 'sample' => array('id' => 2, 'code' => 'EUR', 'exchange_rate' => 0.0279, 'base' => false)),

		'currency.deleted' => array(
			'fields' => array_slice($currency, 0, 2, true),
			'sample' => array('id' => 2, 'code' => 'EUR')
		),

		'offer.created' => array('fields' => $offer, 'sample' => array('id' => 19, 'code' => 'WINTER10', 'enabled' => true)),
		'offer.updated' => array('fields' => $offer, 'sample' => array('id' => 19, 'code' => 'WINTER10', 'enabled' => false)),

		'offer.deleted' => array(
			'fields' => array_slice($offer, 0, 2, true),
			'sample' => array('id' => 19, 'code' => 'WINTER10')
		),

		'file.created' => array('fields' => $named('file', 'GET /files/{id}'), 'sample' => array('id' => 310, 'name' => 'price-list.pdf')),

		'form.submitted' => array(
			'fields' => array(
				'id'             => array('integer', 'The submission (GET /forms/{id}/submissions/{submission_id}).'),
				'form_id'        => array('integer', 'The form.'),
				'form_name'      => array('string', 'The form\'s name.'),
				'reference_code' => array('string', 'The submission\'s reference code.'),
				'contact_id'     => array('integer?', 'The contact the submission was filed under, null for none. The answers themselves are never sent.')
			),
			'sample' => array('id' => 2210, 'form_id' => 14, 'form_name' => 'Contact us', 'reference_code' => 'K4M9QZ', 'contact_id' => 884)
		),

		'form.paid' => array(
			'fields' => array(
				'id'             => array('integer', 'The submission whose fee was paid.'),
				'form_id'        => array('integer', 'The form.'),
				'reference_code' => array('string', 'The submission\'s reference code.'),
				'contact_id'     => array('integer?', 'The contact, null for none.'),
				'order_id'       => array('integer', 'The order that carried the payment, 0 for none.'),
				'order_number'   => array('string?', 'That order\'s number, null for none.'),
				'paid_at'        => array('string?', 'When it was paid (UTC), null when not known.')
			),
			'sample' => array('id' => 2210, 'form_id' => 14, 'reference_code' => 'K4M9QZ', 'contact_id' => 884, 'order_id' => 1042, 'order_number' => '100318', 'paid_at' => '2026-11-17T09:40:00Z')
		),

		'form.submission.deleted' => array(
			'fields' => array(
				'id'             => array('integer', 'The submission that was deleted.'),
				'form_id'        => array('integer', 'The form.'),
				'reference_code' => array('string', 'The submission\'s reference code.')
			),
			'sample' => array('id' => 2210, 'form_id' => 14, 'reference_code' => 'K4M9QZ')
		),

		'calendar_event.reserved' => array(
			'fields' => array(
				'id'                => array('integer', 'The calendar event (GET /calendars/events/{id}/reservations).'),
				'recurrence_number' => array('integer', 'The repeat reserved, 0 for the event as a whole.'),
				'quantity'          => array('integer', 'How many spots.'),
				'order_item_id'     => array('integer', 'The order line that reserved them.'),
				'order_id'          => array('integer', 'The order.'),
				'order_number'      => array('integer', 'The order\'s number.'),
				'contact_id'        => array('integer?', 'The customer\'s contact, null for none.'),
				'start_at'          => array('string?', 'The reserved repeat\'s start (UTC), null when not known.')
			),
			'sample' => array('id' => 77, 'recurrence_number' => 0, 'quantity' => 2, 'order_item_id' => 5521, 'order_id' => 1042, 'order_number' => 100318, 'contact_id' => 884, 'start_at' => '2026-11-22T17:00:00Z')
		),

		'campaign.sent' => array(
			'fields' => array(
				'id'      => array('integer', 'The campaign (GET /campaigns/{id}).'),
				'channel' => array('string', 'email or sms.'),
				'type'    => array('string', 'manual or automatic.')
			),
			'sample' => array('id' => 15, 'channel' => 'email', 'type' => 'manual')
		),

		'mail.bounced'    => array('fields' => $mail, 'sample' => $mail_sample('hard')),
		'mail.complained' => array('fields' => $mail, 'sample' => $mail_sample('complaint')),

		'whatsapp.received' => array(
			'fields' => array(
				'id'           => array('integer', 'The message as the inbox keeps it.'),
				'from'         => array('string', 'The sender\'s number, digits only.'),
				'profile_name' => array('string', 'The sender\'s WhatsApp name.'),
				'type'         => array('string', 'The message type, such as text or image. Media is never sent.'),
				'body'         => array('string', 'The text, up to 1,000 characters.'),
				'contact_id'   => array('integer', 'The contact of the number, 0 for none.')
			),
			'sample' => array('id' => 501, 'from' => '905551112233', 'profile_name' => 'Ayşe', 'type' => 'text', 'body' => 'Is my order on its way?', 'contact_id' => 884)
		)

	);

}

// The events as GET /webhooks/events lists them: each with its description,
// its module ('' for the core; $modules names the module of each event a
// module adds) and its fields and sample, an empty list and null for an event
// whose payload is not written yet. Pure.
function api_webhook_event_rows($events, $modules, $payloads) {

	$rows = array();

	foreach ($events as $name => $description) {

		$fields = array();

		$payload = isset($payloads[$name]) ? $payloads[$name] : null;

		foreach ($payload ? $payload['fields'] : array() as $field => $about) {

			$fields[] = array(
				'name'        => (string)$field,
				'type'        => rtrim((string)$about[0], '?'),
				'nullable'    => (substr((string)$about[0], -1) === '?'),
				'description' => (string)$about[1]
			);

		}

		$rows[] = array(
			'name'        => (string)$name,
			'description' => (string)$description,
			'module'      => isset($modules[$name]) ? (string)$modules[$name] : '',
			'fields'      => $fields,
			'sample'      => $payload ? $payload['sample'] : null
		);

	}

	return $rows;

}
