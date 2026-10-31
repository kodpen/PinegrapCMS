<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A marketplace order's invoice, handed to the marketplace by itself.
//
// Where the store invoices marketplace orders (config.erp_auto_invoice_marketplace),
// the invoice the ERP issues on its own is followed by the two steps the
// Imported orders card offers by hand: the invoice's public address
// (erp_invoice.php) goes to the marketplace as the package's invoice link, and
// on Trendyol the package is marked Invoiced with the invoice number.
//
// Invoiced is sent only to a package already accepted (Picking). Accepting is a
// commitment to ship that the store makes by its own setting (auto_approve) or
// by hand, never as a side effect of invoicing.
//
// A failure is never kept in last_error, which takes the package out of the
// own-carrier shipping pass. It goes to the activity log, and the card shows
// every invoiced package whose link has not gone, with the form to send it
// again filled in (mp_invoice_push_pending()).

if (!defined('PG_INIT_LOADED')) {

	exit;

}

require_once(dirname(__FILE__) . '/connectors/base.php');
require_once(dirname(__FILE__) . '/orders.php');
require_once(dirname(__FILE__) . '/sync.php');

// Which of the two steps a package gets. Pure.
//
// $row is its marketplace_order_map row, $stage mp_order_stage()'s word,
// $can the connector's 'send_invoice_link' and 'mark_invoiced' as booleans,
// $number the invoice number ('' when the order has no issued invoice).
// Answers array('link' => bool, 'invoiced' => bool).
function mp_invoice_push_plan($switch_on, $row, $stage, $can, $number) {

	$plan = array('link' => false, 'invoiced' => false);

	if (!$switch_on || ((string)$number === '') || !is_array($row)
		|| (($row['status'] ?? '') !== 'imported') || empty($row['order_id']) || ($stage === 'cancelled')) {

		return $plan;

	}

	$plan['link'] = !empty($can['send_invoice_link']) && empty($row['invoice_sent']);

	$plan['invoiced'] = !empty($can['mark_invoiced']) && mp_invoiced_offer($row, $stage)
		&& ((string)($row['remote_status'] ?? '') === 'Picking');

	return $plan;

}

// Whether the store invoices its marketplace orders on its own.
function mp_invoice_push_switch_on() {

	return defined('ERP_ENABLED') && ERP_ENABLED
		&& defined('ERP_AUTO_INVOICE_MARKETPLACE') && ((int)ERP_AUTO_INVOICE_MARKETPLACE === 1)
		&& defined('ERP_AUTO_INVOICE_ON') && ((string)ERP_AUTO_INVOICE_ON !== 'off');

}

// The order's issued sales invoice: id, full_number, issue_date; or null.
function mp_invoice_push_invoice($order_id) {

	$invoice = db_item("SELECT id, full_number, issue_date FROM erp_invoices
		WHERE order_id = '" . e((int)$order_id) . "' AND direction = 'sales' AND doc_type = 'invoice'
		  AND status NOT IN ('draft', 'cancelled') AND full_number <> ''
		ORDER BY id DESC LIMIT 1");

	return is_array($invoice) ? $invoice : null;

}

// The package with what a connector may send beside the link: the invoice
// number and its day (n11 takes them; Trendyol reads neither).
function mp_invoice_push_package($row, $invoice) {

	$package = mp_sync_package_from_row($row);

	$package['invoice_number'] = (string)$invoice['full_number'];

	$package['invoice_date'] = (string)$invoice['issue_date'];

	return $package;

}

// Send the link of an invoice's public address for one package. Writes
// invoice_sent and the log line on success. Answers the connector's result.
function mp_invoice_push_link($account, $row, $invoice, $automatic) {

	$url = erp_invoice_public_link_issue((int)$invoice['id']);

	if ($url === '') {

		return mp_error(lang('The invoice has no public address: it is not issued, or the database has not been upgraded yet.'));

	}

	$sent = mp_call($account['provider'], 'send_invoice_link',
		array($account, mp_invoice_push_package($row, $invoice), $url));

	$vars = array((string)$row['remote_order_id'], mp_provider_name($account['provider']));

	if ($sent['ok']) {

		db("UPDATE marketplace_order_map
			SET invoice_sent = UNIX_TIMESTAMP(), updated_timestamp = UNIX_TIMESTAMP()
			WHERE (id = '" . e((int)$row['id']) . "') AND (invoice_sent = 0)");

		log_activity(lang(array(
			'string' => 'The public address of invoice {var:3} was sent to {var:2} as the invoice link of order {var:1}.',
			'vars'   => array_merge($vars, array((string)$invoice['full_number']))
		)), 'system');

	} elseif ($automatic) {

		log_activity(lang(array(
			'string' => 'The invoice link of order {var:1} was not sent to {var:2} on its own; send it again on the Imported orders card: {var:3}',
			'vars'   => array_merge($vars, array($sent['error']))
		)), 'system');

	}

	return $sent;

}

// After the ERP issued a marketplace order's invoice on its own: the link,
// then Invoiced, each where mp_invoice_push_plan() says. Nothing while the
// switch is off. Answers array('link' => result|null, 'invoiced' => result|null).
function mp_invoice_push_order($order_id) {

	$done = array('link' => null, 'invoiced' => null);

	$row = db_item("SELECT * FROM marketplace_order_map
		WHERE (order_id = '" . e((int)$order_id) . "') AND (status = 'imported') LIMIT 1");

	$account = is_array($row) ? mp_account((int)$row['account_id']) : null;

	$invoice = mp_invoice_push_invoice($order_id);

	if (!$account || !$invoice || !mp_invoice_sent_ready() || !function_exists('erp_invoice_public_link_issue')) {

		return $done;

	}

	$provider = $account['provider'];

	$plan = mp_invoice_push_plan(mp_invoice_push_switch_on(), $row, mp_order_stage($account, $row), array(
		'send_invoice_link' => mp_can($provider, 'send_invoice_link'),
		'mark_invoiced'     => mp_can($provider, 'mark_invoiced')
	), (string)$invoice['full_number']);

	if ($plan['link']) {

		$done['link'] = mp_invoice_push_link($account, $row, $invoice, true);

	}

	if ($plan['invoiced']) {

		$number = (string)$invoice['full_number'];

		$done['invoiced'] = mp_call($provider, 'mark_invoiced', array($account, mp_sync_package_from_row($row), $number));

		$vars = array((string)$row['remote_order_id'], mp_provider_name($provider), $number);

		if ($done['invoiced']['ok']) {

			db("UPDATE marketplace_order_map
				SET remote_status = 'Invoiced', approved = 1, updated_timestamp = UNIX_TIMESTAMP()
				WHERE id = '" . e((int)$row['id']) . "'");

			log_activity(lang(array(
				'string' => 'Order {var:1} was marked invoiced on {var:2} with invoice number {var:3}',
				'vars'   => $vars
			)), 'system');

		} else {

			log_activity(lang(array(
				'string' => 'Order {var:1} was not marked invoiced on {var:2} on its own; mark it on the Imported orders card with invoice number {var:3}: {var:4}',
				'vars'   => array_merge($vars, array($done['invoiced']['error']))
			)), 'system');

		}

	}

	return $done;

}

// The invoices of the card's rows: order id => id, full_number, issue_date.
// Empty without the ERP or the 11.0.65 column.
function mp_invoice_push_invoices($order_ids) {

	$ids = array_values(array_filter(array_map('intval', (array)$order_ids)));

	if (!$ids || !defined('ERP_ENABLED') || !ERP_ENABLED || !function_exists('pg_schema_has')
		|| !pg_schema_has('erp_invoices', 'public_token_hash')) {

		return array();

	}

	$invoices = array();

	foreach ((array)db_items("SELECT order_id, id, full_number, issue_date FROM erp_invoices
		WHERE order_id IN (" . implode(',', $ids) . ") AND direction = 'sales' AND doc_type = 'invoice'
		  AND status NOT IN ('draft', 'cancelled') AND full_number <> ''
		ORDER BY id ASC") as $invoice) {

		$invoices[(int)$invoice['order_id']] = $invoice;

	}

	return $invoices;

}

// Whether the card marks a row as invoiced with its link not gone: the switch
// on, an issued invoice, the link not sent. Pure.
function mp_invoice_push_pending($switch_on, $row, $invoice, $can_invoice) {

	return $switch_on && $can_invoice && is_array($invoice)
		&& (($row['status'] ?? '') === 'imported') && !empty($row['order_id']) && empty($row['invoice_sent']);

}
