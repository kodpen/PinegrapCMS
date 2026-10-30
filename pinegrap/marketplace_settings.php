<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Marketplaces: where else this shop's products are sold, and keeping them in step.
//
// A separate screen from Application Access, and the split is by direction
// rather than by subject. That screen is about systems reaching in - keys the
// operator hands out, and what a holder may see. This one is about the software
// reaching out: accounts the shop holds somewhere else, and work it does on a
// schedule without anybody watching.
//
// The screen answers three questions, in the order they are asked:
//
//   Is the connection alive - the account list, with when it last worked.
//   Which products go over there - the mapping, which is where the shop's
//     product meets the code the marketplace knows it by. Counted here, kept
//     on marketplace_products.php.
//   Did it land - the queue, which is the only honest answer, because these
//     marketplaces accept a batch and refuse it line by line minutes later.

include('init.php');
include_once('liveform.class.php');

// A rejected return's documents can pass post_max_size, and then PHP hands
// over an empty post that would only draw the screen again.
if (pg_post_body_discarded()) {
	output_error(lang('The request was too large for this server and arrived empty.'), 413);
}

$user = validate_user();
validate_area_access($user, 'manager');
validate_ecommerce_access($user);

require_once(dirname(__FILE__) . '/includes/api/outbound/connectors/base.php');
require_once(dirname(__FILE__) . '/includes/api/outbound/listings.php');
require_once(dirname(__FILE__) . '/includes/api/outbound/product_matches.php');
require_once(dirname(__FILE__) . '/includes/api/outbound/webhook_receiver.php');
require_once(dirname(__FILE__) . '/includes/api/outbound/labels.php');

$liveform = new liveform('marketplace_settings');

$screen_url = URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/marketplace_settings.php';

/* ---------------------------------------------------------------------------
   Writes
   --------------------------------------------------------------------------- */

if ($_POST) {

	validate_token_field();

	// Every field here holds one value, except the category attributes
	// (attribute[id]) and the return units ticked for a rejection (unit_ids[]);
	// a rejection's documents (claim_files[]) arrive in $_FILES, and are
	// named only because the form draws them as a list. One posted as a list
	// (provider[]=x) stopped the request in trim(), and account_id[]=x or
	// group_id[]=x was read as record 1.
	// Nothing is written, so every account and match stays as it was.
	$mp_settings_lists = pg_request_unexpected_lists($_POST, array('attribute' => 1, 'unit_ids' => 1, 'claim_files' => 1));

	if ($mp_settings_lists) {

		$liveform->add_error(lang(array('string' => 'Sorry, the value you entered for {var:1} is not valid.', 'vars' => h(implode(', ', $mp_settings_lists)))));

		header('Location: ' . $screen_url . ((is_scalar($_POST['account_id'] ?? null) && ((int)$_POST['account_id'] > 0)) ? '?account=' . (int)$_POST['account_id'] : ''));

		exit();

	}

	$action = isset($_POST['mp_action']) ? $_POST['mp_action'] : '';

	$account_id = isset($_POST['account_id']) ? (int)$_POST['account_id'] : 0;

	$account = $account_id ? mp_account($account_id) : null;

	if ($account_id && !$account) {

		$liveform->add_error(lang('That marketplace account no longer exists.'));

		$action = '';

	}

	if ($action === 'create') {

		$provider = isset($_POST['provider']) ? trim($_POST['provider']) : '';

		$name = isset($_POST['name']) ? trim($_POST['name']) : '';

		if (!mp_provider_is_valid($provider)) {

			$liveform->mark_error('provider', lang('Choose a marketplace.'));

		}

		if ($name === '') {

			$liveform->mark_error('name', lang(array('string' => '{var:1} is required', 'vars' => array(lang('Name')))));

		}

		$credentials = mp_settings_credentials_from_post($provider);

		$provider_meta = mp_providers();

		foreach ($credentials as $field => $value) {

			// A select always answers one of its options; only a text box can
			// be left empty.
			if (mp_provider_field_is_select($provider_meta[$provider]['fields'][$field])) {

				continue;

			}

			if (trim((string)$value) === '') {

				$liveform->mark_error($field, lang('This is required.'));

			}

		}

		if ($liveform->check_form_errors() == false) {

			$credentials = mp_account_delivery_stamp(array(), $credentials, time());

			// pull_orders is written, not left to the column's default of 1: a
			// marketplace whose connector cannot bring orders in would start
			// with the switch on and the job skipping it without a word.
			db("INSERT INTO marketplace_accounts
				(provider, name, credentials, status, push_stock, push_price, pull_orders,
				 created_user_id, created_timestamp, updated_timestamp)
				VALUES (
					'" . e($provider) . "',
					'" . e($name) . "',
					'" . e(mp_credentials_encode($credentials)) . "',
					'paused',
					'1',
					'" . (empty($_POST['push_price']) ? '0' : '1') . "',
					'" . (mp_can($provider, 'pull_orders') ? '1' : '0') . "',
					'" . e((int)$user['id']) . "',
					UNIX_TIMESTAMP(),
					UNIX_TIMESTAMP())");

			$new_id = (int) mysqli_insert_id(db::$con);

			log_activity(lang(array(
				'string' => 'Marketplace account added: {var:1} ({var:2}).',
				'vars'   => array($name, mp_provider_name($provider))
			)), $user['username']);

			// Paused on purpose. The credentials have not been tried yet, and an
			// account that starts sending the moment it is typed sends whatever
			// the first mapping mistake produces.
			$liveform->add_notice(lang('Marketplace added. Test the connection, match some products, then switch it on.'));

			header('Location: ' . $screen_url . '?account=' . $new_id);

			exit();

		}

	}

	if ($action === 'update' && $account) {

		$name = isset($_POST['name']) ? trim($_POST['name']) : '';

		if ($name === '') {

			$liveform->mark_error('name', lang(array('string' => '{var:1} is required', 'vars' => array(lang('Name')))));

		}

		if ($liveform->check_form_errors() == false) {

			$credentials = mp_settings_credentials_from_post($account['provider']);

			// An empty credential field means "leave it alone". The screen
			// cannot show a secret back, so a blank box is the absence of a new
			// value rather than an instruction to clear the old one.
			foreach ($credentials as $field => $value) {

				if (trim((string)$value) === '') {

					$credentials[$field] = isset($account['credentials'][$field]) ? $account['credentials'][$field] : '';

				}

			}

			// What the blob holds besides the form's fields - the webhook's key
			// and id - is kept. The form never carries those, and writing the
			// fields alone would leave a registered webhook delivering with a
			// key nothing here knows any more.
			$credentials = mp_account_delivery_stamp($account['credentials'],
				array_merge((array)$account['credentials'], $credentials), time());

			// A registered webhook belongs to the seller and environment it was
			// registered for, and keeps delivering their packages whatever the
			// account is changed to. Nothing is saved until it is removed.
			if (!empty($account['credentials']['webhook_id'])
				&& mp_webhook_owner_changed($account['provider'], $account['credentials'], $credentials)) {

				$liveform->add_warning(h(lang('Remove the webhook before changing the environment or the seller ID: the webhook was registered for the current ones and would keep delivering their packages.')));

				header('Location: ' . $screen_url . '?account=' . $account_id);

				exit();

			}

			$status = (isset($_POST['status']) && $_POST['status'] === 'active') ? 'active' : 'paused';

			db("UPDATE marketplace_accounts SET
					name = '" . e($name) . "',
					credentials = '" . e(mp_credentials_encode($credentials)) . "',
					status = '" . e($status) . "',
					push_stock = '" . (empty($_POST['push_stock']) ? '0' : '1') . "',
					push_price = '" . (empty($_POST['push_price']) ? '0' : '1') . "',
					pull_orders = '" . (empty($_POST['pull_orders']) ? '0' : '1') . "',
					auto_approve = '" . (empty($_POST['auto_approve']) ? '0' : '1') . "',
					shipment_template = '" . e(mb_substr(isset($_POST['shipment_template']) ? trim($_POST['shipment_template']) : '', 0, 100)) . "',
					preparing_day = '" . max(1, min(30, isset($_POST['preparing_day']) ? (int)$_POST['preparing_day'] : 3)) . "',
					updated_timestamp = UNIX_TIMESTAMP()
				WHERE id = '" . e($account_id) . "'");

			log_activity(lang(array(
				'string' => 'Marketplace account changed: {var:1}.',
				'vars'   => array($name)
			)), $user['username']);

			$liveform->add_notice(lang('Saved.'));

			if (!empty($credentials['webhook_id']) && ($status !== 'active' || empty($_POST['pull_orders']))) {

				$liveform->add_warning(h(lang(array(
					'string' => 'While this account is paused or does not bring orders in, the orders {var:1} sends are refused, and after repeated refusals {var:1} switches the webhook off. Remove the webhook if this lasts.',
					'vars'   => array(mp_provider_name($account['provider']))
				))));

			}

			header('Location: ' . $screen_url . '?account=' . $account_id);

			exit();

		}

	}

	if ($action === 'test' && $account) {

		$result = mp_call($account['provider'], 'test_credentials', array($account));

		if ($result['ok']) {

			mp_account_outcome($account_id, true);

			$liveform->add_notice(lang(array(
				'string' => 'Connected to {var:1}.',
				'vars'   => array(mp_provider_name($account['provider']))
			)));

		} else {

			mp_account_outcome($account_id, false, $result['error']);

			$liveform->add_warning(lang(array(
				'string' => 'Could not connect: {var:1}',
				'vars'   => array(h($result['error']))
			)));

		}

		header('Location: ' . $screen_url . '?account=' . $account_id);

			exit();

	}

	if ($action === 'delete' && $account) {

		// A registered webhook is removed from the marketplace first. Left
		// behind, it would deliver to an address that answers 404 for ever:
		// Trendyol mails the seller about the failures and counts it against
		// their fifteen webhooks per seller. When the removal fails the account
		// stays, so that it can be tried again; a webhook already removed in
		// their panel answers 404, which the connector reads as removed. An
		// account whose credentials no longer work (a closed store, a revoked
		// key) can never remove it, so the operator may tick the box to delete
		// the account anyway and remove the webhook in the seller panel.
		$webhook_id = isset($account['credentials']['webhook_id']) ? (string)$account['credentials']['webhook_id'] : '';

		$webhook_left = false;

		if ($webhook_id !== '' && mp_can($account['provider'], 'remove_webhook')) {

			$removed = mp_call($account['provider'], 'remove_webhook', array($account));

			if (!$removed['ok']) {

				if (!empty($_POST['webhook_force'])) {

					log_activity(lang(array(
						'string' => 'Marketplace account {var:1} was removed with its webhook {var:2} still registered on {var:3}; remove it in the seller panel.',
						'vars'   => array($account['name'], $webhook_id, mp_provider_name($account['provider']))
					)), $user['username']);

					$webhook_left = true;

				} else {

					$liveform->add_warning(lang(array(
						'string' => 'The marketplace was not removed, because its webhook could not be removed from {var:1}: {var:2} Try again, remove the webhook in your {var:1} seller panel first, or tick the box below the button to remove the marketplace anyway.',
						'vars'   => array(h(mp_provider_name($account['provider'])), h($removed['error']))
					)));

					header('Location: ' . $screen_url . '?account=' . $account_id);

					exit();

				}

			}

		}

		db("DELETE FROM marketplace_sync_queue WHERE account_id = '" . e($account_id) . "'");

		db("DELETE FROM marketplace_product_map WHERE account_id = '" . e($account_id) . "'");

		db("DELETE FROM marketplace_accounts WHERE id = '" . e($account_id) . "'");

		log_activity(lang(array(
			'string' => 'Marketplace account removed: {var:1}.',
			'vars'   => array($account['name'])
		)), $user['username']);

		$liveform->add_notice(lang('Marketplace removed. The products themselves are untouched.'));

		if ($webhook_left) {

			$liveform->add_warning(h(lang(array(
				'string' => 'Its webhook is still registered on {var:1}. Remove it in your {var:1} seller panel.',
				'vars'   => array(mp_provider_name($account['provider']))
			))));

		}

		header('Location: ' . $screen_url);

			exit();

	}

	// Matches of products deleted since, or in the Recycle Bin: no view lists
	// them, and while they stand they hold their codes.
	if ($action === 'clear_stale_matches' && $account) {

		$cleared = mp_matches_clear_stale($account_id);

		$liveform->add_notice(lang(array('string' => '{var:1} match(es) removed. Nothing was changed on the marketplace.', 'vars' => array(pg_format_number($cleared, 0)))));

		header('Location: ' . $screen_url . '?account=' . $account_id . '#mp-map');

		exit();

	}

	if ($action === 'push_all' && $account) {

		$queued = mp_enqueue_account($account_id);

		$liveform->add_notice($queued
			? lang(array('string' => '{var:1} product(s) queued. They go out on the next run.', 'vars' => array(pg_format_number($queued, 0))))
			: lang('Nothing to send: no product is matched and active on this account.'));

		header('Location: ' . $screen_url . '?account=' . $account_id . '#mp-queue');

			exit();

	}

	if ($action === 'retry' && $account) {

		// Failed rows go back to the front of the queue with their attempt
		// count cleared. The operator has usually just fixed the thing that
		// made them fail, and making them wait out the old backoff is punishing
		// them for having fixed it.
		db("UPDATE marketplace_sync_queue
			SET status = 'waiting', attempts = 0, run_after = 0, remote_task_id = '',
				last_error = '', updated_timestamp = UNIX_TIMESTAMP()
			WHERE (account_id = '" . e($account_id) . "') AND (status = 'failed')");

		$liveform->add_notice(lang('The failed ones will be tried again on the next run.'));

		header('Location: ' . $screen_url . '?account=' . $account_id . '#mp-queue');

			exit();

	}

	if ($action === 'retry_orders' && $account) {

		// A refused order is taken again only when the feed hands its package
		// back, and a package nobody has touched on the marketplace since is no
		// longer in the window. So the cursor goes back to the oldest refused
		// one: the next run reads on from there, imports what was refused and
		// passes over what is already here, because the importer is keyed on
		// the package. remote_timestamp is on the same scale as the pull's own
		// cursor (read_to is the last package's placed_at), and the pull
		// already starts a few minutes before its cursor, so it is used as it
		// is. Only the imported_timestamp fallback, this server's clock, goes
		// back 3 h. Going back further would read packages from before the
		// first pull and import them as new orders.
		$oldest = (int) db_value("SELECT MIN(IF(remote_timestamp > 0, remote_timestamp, imported_timestamp - 10800))
			FROM marketplace_order_map
			WHERE (account_id = '" . e($account_id) . "') AND (status = 'failed') AND (order_id = 0)");

		if ($oldest > 0) {

			$from = max(1, $oldest);

			db("UPDATE marketplace_accounts
				SET last_order_pull = '" . e($from) . "', updated_timestamp = UNIX_TIMESTAMP()
				WHERE (id = '" . e($account_id) . "') AND (last_order_pull > '" . e($from) . "')");

		}

		$liveform->add_notice(lang('The orders that failed will be read from the marketplace again on the next run.'));

		header('Location: ' . $screen_url . '?account=' . $account_id . '#mp-orders');

		exit();

	}

	// The link of an imported package's invoice, typed by the operator. The
	// e-document PDF is served only behind the panel login, so no job can hand
	// the marketplace a link of its own; the operator gives the address the
	// buyer can open (an e-Archive portal, a shared PDF). A refusal is shown
	// here and not kept in last_error, which takes the package out of the
	// own-carrier shipping pass.
	if ($action === 'invoice_link' && $account) {

		$map_id = isset($_POST['map_id']) ? (int)$_POST['map_id'] : 0;

		$invoice_url = isset($_POST['invoice_url']) ? trim((string)$_POST['invoice_url']) : '';

		$package = $map_id ? db_item("SELECT * FROM marketplace_order_map
			WHERE (id = '" . e($map_id) . "') AND (account_id = '" . e($account_id) . "')
			  AND (status = 'imported') AND (order_id > 0)") : null;

		if (!mp_can($account['provider'], 'send_invoice_link')) {

			$liveform->add_warning(lang(array(
				'string' => '{var:1} cannot do this.',
				'vars'   => array(h(mp_provider_name($account['provider'])))
			)));

		} elseif (!mp_invoice_sent_ready()) {

			$liveform->add_warning(lang('The database has not been upgraded yet. Run the software update first.'));

		} elseif (!$package) {

			$liveform->add_warning(lang('That order is no longer in the imported orders.'));

		} elseif (!empty($package['invoice_sent'])) {

			$liveform->add_notice(lang(array(
				'string' => 'The invoice link of order {var:1} was sent already.',
				'vars'   => array(h((string)$package['remote_order_id']))
			)));

		} else {

			$sent = mp_call($account['provider'], 'send_invoice_link', array($account, $package, $invoice_url));

			if ($sent['ok']) {

				db("UPDATE marketplace_order_map
					SET invoice_sent = UNIX_TIMESTAMP(), updated_timestamp = UNIX_TIMESTAMP()
					WHERE (id = '" . e($map_id) . "') AND (invoice_sent = 0)");

				log_activity(lang(array(
					'string' => 'The invoice link of order {var:1} was sent to {var:2}: {var:3}',
					'vars'   => array((string)$package['remote_order_id'], mp_provider_name($account['provider']), $invoice_url)
				)), 'system');

				$liveform->add_notice(lang(array(
					'string' => 'The invoice link of order {var:1} was sent to {var:2}.',
					'vars'   => array(h((string)$package['remote_order_id']), h(mp_provider_name($account['provider'])))
				)));

			} else {

				$liveform->add_warning(lang(array(
					'string' => 'The invoice link of order {var:1} was not sent: {var:2}',
					'vars'   => array(h((string)$package['remote_order_id']), h($sent['error']))
				)));

			}

		}

		header('Location: ' . $screen_url . '?account=' . $account_id . '#mp-orders');

		exit();

	}

	// The shipping label the marketplace makes for its own carrier's parcel
	// (Trendyol's common label), asked for by the operator for one imported
	// package and kept with the order's labels (includes/api/outbound/labels.php).
	// A refusal is shown here and not kept in last_error, which would take the
	// package out of the acceptance pass.
	if ($action === 'common_label' && $account) {

		$map_id = isset($_POST['map_id']) ? (int)$_POST['map_id'] : 0;

		$made = mp_label_create($account, $map_id, mp_label_boxes(isset($_POST['boxes']) ? $_POST['boxes'] : ''), (int)$user['id']);

		$order_number = (string)db_value("SELECT remote_order_id FROM marketplace_order_map
			WHERE (id = '" . e($map_id) . "') AND (account_id = '" . e($account_id) . "')");

		if ($made['ok'] && $made['existing']) {

			$liveform->add_notice(h(lang(array(
				'string' => 'Order {var:1} has its shipping label already. Print it below or on the order screen.',
				'vars'   => array($order_number)
			))));

		} elseif ($made['ok']) {

			$liveform->add_notice(h(lang(array(
				'string' => 'The shipping label of order {var:1} is kept with the order. Print it below or on the order screen.',
				'vars'   => array($order_number)
			))));

		} elseif ($made['pending']) {

			$liveform->add_notice(h($made['error']));

		} else {

			$liveform->add_warning(h($made['error']));

		}

		header('Location: ' . $screen_url . '?account=' . $account_id . '#mp-orders');

		exit();

	}

	// The webhook: the marketplace posts each order package to this site as it
	// changes, instead of waiting for the next pull. The key is made here, kept
	// in the account's encrypted credentials and never shown; the marketplace
	// sends it back with every delivery (includes/api/outbound/webhook_receiver.php).
	if ($action === 'webhook_register' && $account) {

		$provider_name = mp_provider_name($account['provider']);

		// One registration or removal per account at a time
		// (mp_webhook_lock_name()), and the account read again under the lock:
		// a registration that finished after this request first read it is
		// seen, and refused as already there. A press while another is still
		// running is refused rather than queued.
		$lock = mp_webhook_lock_name($account_id);

		if ((int)db_value("SELECT GET_LOCK('" . e($lock) . "', 0)") !== 1) {

			$problem = lang('The webhook of this account is being changed right now. Look again in a moment.');

		} else {

			$account = mp_account($account_id);

			$problem = !$account
				? lang('That marketplace account no longer exists.')
				: (mp_can($account['provider'], 'register_webhook')
					? mp_webhook_register_problem($account)
					: lang(array('string' => '{var:1} cannot do this.', 'vars' => array($provider_name))));

			if ($problem !== '') {

				db("SELECT RELEASE_LOCK('" . e($lock) . "')");

			}

		}

		if ($problem !== '') {

			$liveform->add_warning(h($problem));

		} else {

			$credentials = (array)$account['credentials'];

			// A new key for every registration, 32 random bytes written URL-safe,
			// so a webhook from an earlier registration that is still alive over
			// there presents a key the receiver no longer accepts.
			$key = pg_short_link_new_token();

			$result = mp_call($account['provider'], 'register_webhook', array($account, mp_webhook_url($account_id), $key));

			if ($result['ok']) {

				$credentials['webhook_key'] = $key;
				$credentials['webhook_id'] = (string)$result['data']['webhook_id'];

				db("UPDATE marketplace_accounts
					SET credentials = '" . e(mp_credentials_encode($credentials)) . "', updated_timestamp = UNIX_TIMESTAMP()
					WHERE id = '" . e($account_id) . "'");

				log_activity(lang(array(
					'string' => 'Marketplace webhook registered: {var:1}.',
					'vars'   => array($account['name'])
				)), $user['username']);

				$liveform->add_notice(h(lang(array(
					'string' => 'Webhook registered. {var:1} now sends new orders to this site as they happen.',
					'vars'   => array($provider_name)
				))));

			} else {

				$liveform->add_warning(lang(array(
					'string' => 'Could not register the webhook: {var:1}',
					'vars'   => array(h($result['error']))
				)));

			}

			db("SELECT RELEASE_LOCK('" . e($lock) . "')");

		}

		header('Location: ' . $screen_url . '?account=' . $account_id . '#mp-webhook');

		exit();

	}

	if ($action === 'webhook_remove' && $account) {

		// Under the registration's lock, with the account read again, for the
		// same reason: credentials read before a registration finished would
		// write its webhook's id away.
		$lock = mp_webhook_lock_name($account_id);

		$locked = ((int)db_value("SELECT GET_LOCK('" . e($lock) . "', 0)") === 1);

		$account = $locked ? mp_account($account_id) : null;

		if (!$locked) {

			$liveform->add_warning(h(lang('The webhook of this account is being changed right now. Look again in a moment.')));

		} elseif (!$account) {

			$liveform->add_error(lang('That marketplace account no longer exists.'));

		} elseif (!mp_can($account['provider'], 'remove_webhook')) {

			$liveform->add_warning(h(lang(array(
				'string' => '{var:1} cannot do this.',
				'vars'   => array(mp_provider_name($account['provider']))
			))));

		} else {

			$result = mp_call($account['provider'], 'remove_webhook', array($account));

			if ($result['ok']) {

				// The key goes with the id; a later registration makes a new one.
				$credentials = (array)$account['credentials'];

				unset($credentials['webhook_id'], $credentials['webhook_key']);

				db("UPDATE marketplace_accounts
					SET credentials = '" . e(mp_credentials_encode($credentials)) . "', updated_timestamp = UNIX_TIMESTAMP()
					WHERE id = '" . e($account_id) . "'");

				log_activity(lang(array(
					'string' => 'Marketplace webhook removed: {var:1}.',
					'vars'   => array($account['name'])
				)), $user['username']);

				$liveform->add_notice(lang('Webhook removed. Orders are read every five minutes again.'));

			} else {

				$liveform->add_warning(lang(array(
					'string' => 'Could not remove the webhook: {var:1}',
					'vars'   => array(h($result['error']))
				)));

			}

		}

		if ($locked) {

			db("SELECT RELEASE_LOCK('" . e($lock) . "')");

		}

		header('Location: ' . $screen_url . '?account=' . $account_id . '#mp-webhook');

		exit();

	}

	// The buyers' returns of the last two weeks, read on the operator's
	// button. The list is kept in the session for this screen and never in a
	// table: the marketplace holds the returns, and a stored copy would only
	// go stale. A failed read leaves the last good one in place.
	if ($action === 'claims_read' && $account) {

		if (!mp_can($account['provider'], 'claims')) {

			$liveform->add_warning(lang(array(
				'string' => '{var:1} cannot do this.',
				'vars'   => array(h(mp_provider_name($account['provider'])))
			)));

		} else {

			$read = mp_call($account['provider'], 'claims', array($account));

			if ($read['ok']) {

				$_SESSION['software']['marketplace_read'][$account_id]['claims'] = array(
					'read'   => time(),
					'claims' => $read['data']['claims'],
					'more'   => !empty($read['data']['more'])
				);

			} else {

				$liveform->add_warning(lang(array(
					'string' => 'The returns could not be read: {var:1}',
					'vars'   => array(h($read['error']))
				)));

			}

			// The reasons a return can be rejected with, for the reject
			// form, read with the returns so the form never asks for them.
			if ($read['ok'] && mp_can($account['provider'], 'reject_claim') && mp_can($account['provider'], 'claim_issue_reasons')) {

				$reasons = mp_call($account['provider'], 'claim_issue_reasons', array($account));

				if ($reasons['ok']) {

					$_SESSION['software']['marketplace_read'][$account_id]['claim_reasons'] = array(
						'read'          => time(),
						'claim_reasons' => $reasons['data']['reasons'],
						'more'          => false
					);

				} else {

					$liveform->add_warning(lang(array(
						'string' => 'The reasons for rejecting a return could not be read: {var:1}',
						'vars'   => array(h($reasons['error']))
					)));

				}

			}

		}

		header('Location: ' . $screen_url . '?account=' . $account_id . '#mp-claims');

		exit();

	}

	// Approve the waiting units of one return from the last read: the buyer
	// is refunded. Which units is taken from that read, not from the form, so
	// a posted id the marketplace never named for this return cannot be sent.
	// The read is not changed beyond noting the approval: what the
	// marketplace made of it is seen on the next read.
	if ($action === 'claim_approve' && $account) {

		$claim_id = isset($_POST['claim_id']) ? trim((string)$_POST['claim_id']) : '';

		$claim = null;

		$claim_index = null;

		foreach (mp_settings_marketplace_read($account_id, 'claims')['claims'] as $index => $row) {

			if (isset($row['claim_id']) && ($row['claim_id'] === $claim_id)) {

				$claim = $row;

				$claim_index = $index;

				break;

			}

		}

		$waiting = $claim ? mp_claim_waiting($claim) : array();

		if (!mp_can($account['provider'], 'approve_claim')) {

			$liveform->add_warning(lang(array(
				'string' => '{var:1} cannot do this.',
				'vars'   => array(h(mp_provider_name($account['provider'])))
			)));

		} elseif (!$claim) {

			$liveform->add_warning(lang('That return is no longer in the list. Read the returns again.'));

		} elseif (!empty($claim['approved']) || !empty($claim['rejected']) || !$waiting) {

			$liveform->add_notice(lang(array(
				'string' => 'The return of order {var:1} has no units waiting for approval.',
				'vars'   => array(h((string)$claim['order_number']))
			)));

		} else {

			$sent = mp_call($account['provider'], 'approve_claim', array($account, $claim_id, $waiting));

			if ($sent['ok']) {

				$_SESSION['software']['marketplace_read'][$account_id]['claims']['claims'][$claim_index]['approved'] = time();

				log_activity(lang(array(
					'string' => 'The return of order {var:1} was approved on {var:2} ({var:3} units).',
					'vars'   => array((string)$claim['order_number'], mp_provider_name($account['provider']), count($waiting))
				)), 'system');

				$liveform->add_notice(lang(array(
					'string' => 'The return of order {var:1} was approved on {var:2}.',
					'vars'   => array(h((string)$claim['order_number']), h(mp_provider_name($account['provider'])))
				)));

			} else {

				$liveform->add_warning(lang(array(
					'string' => 'The return of order {var:1} was not approved: {var:2}',
					'vars'   => array(h((string)$claim['order_number']), h($sent['error']))
				)));

			}

		}

		header('Location: ' . $screen_url . '?account=' . $account_id . '#mp-claims');

		exit();

	}

	// Reject units of one return from the last read, with one of the
	// marketplace's reasons, an explanation and the documents that show it.
	// The units are those ticked in the form that the read names as waiting
	// for this return, and the reason one the read of reasons named, so
	// nothing the marketplace never named is sent.
	if ($action === 'claim_reject' && $account) {

		$claim_id = isset($_POST['claim_id']) ? trim((string)$_POST['claim_id']) : '';

		$claim = null;

		$claim_index = null;

		foreach (mp_settings_marketplace_read($account_id, 'claims')['claims'] as $index => $row) {

			if (isset($row['claim_id']) && ($row['claim_id'] === $claim_id)) {

				$claim = $row;

				$claim_index = $index;

				break;

			}

		}

		$waiting = $claim ? mp_claim_waiting($claim) : array();

		$ticked = (isset($_POST['unit_ids']) && is_array($_POST['unit_ids'])) ? array_map('strval', $_POST['unit_ids']) : array();

		$units = array_values(array_intersect($waiting, $ticked));

		$reason_id = isset($_POST['reason_id']) ? trim((string)$_POST['reason_id']) : '';

		$reason = null;

		foreach (mp_settings_marketplace_read($account_id, 'claim_reasons')['claim_reasons'] as $row) {

			if (isset($row['id']) && ((string)$row['id'] === $reason_id)) {

				$reason = $row;

				break;

			}

		}

		$files = mp_settings_claim_files('claim_files');

		if (!mp_can($account['provider'], 'reject_claim')) {

			$liveform->add_warning(lang(array(
				'string' => '{var:1} cannot do this.',
				'vars'   => array(h(mp_provider_name($account['provider'])))
			)));

		} elseif (!$claim) {

			$liveform->add_warning(lang('That return is no longer in the list. Read the returns again.'));

		} elseif (!empty($claim['approved']) || !empty($claim['rejected']) || !$waiting) {

			$liveform->add_notice(lang(array(
				'string' => 'The return of order {var:1} has no units waiting for you.',
				'vars'   => array(h((string)$claim['order_number']))
			)));

		} elseif (!$units) {

			$liveform->add_warning(lang('Choose the units to reject.'));

		} elseif (!$reason) {

			$liveform->add_warning(lang('Choose why the return is rejected.'));

		} elseif ($files['error'] !== '') {

			$liveform->add_warning(h($files['error']));

		} else {

			$sent = mp_call($account['provider'], 'reject_claim', array($account, $claim_id, $units,
				$reason_id, isset($_POST['description']) ? (string)$_POST['description'] : '', $files['files']));

			if ($sent['ok']) {

				$_SESSION['software']['marketplace_read'][$account_id]['claims']['claims'][$claim_index]['rejected'] = time();

				log_activity(lang(array(
					'string' => 'A rejection of the return of order {var:1} was sent to {var:2} ({var:3} units, reason: {var:4}).',
					'vars'   => array((string)$claim['order_number'], mp_provider_name($account['provider']), count($units), (string)$reason['name'])
				)), 'system');

				$liveform->add_notice(lang(array(
					'string' => 'The rejection of the return of order {var:1} was sent to {var:2}. The marketplace decides on it; the next read of the returns shows its answer.',
					'vars'   => array(h((string)$claim['order_number']), h(mp_provider_name($account['provider'])))
				)));

			} else {

				$liveform->add_warning(lang(array(
					'string' => 'The rejection of the return of order {var:1} was not sent: {var:2}',
					'vars'   => array(h((string)$claim['order_number']), h($sent['error']))
				)));

			}

		}

		header('Location: ' . $screen_url . '?account=' . $account_id . '#mp-claims');

		exit();

	}

	// The buyers' questions of the last two weeks, read on the operator's
	// button and kept in the session like the returns (claims_read).
	if ($action === 'questions_read' && $account) {

		if (!mp_can($account['provider'], 'questions')) {

			$liveform->add_warning(lang(array(
				'string' => '{var:1} cannot do this.',
				'vars'   => array(h(mp_provider_name($account['provider'])))
			)));

		} else {

			$read = mp_call($account['provider'], 'questions', array($account));

			if ($read['ok']) {

				$_SESSION['software']['marketplace_read'][$account_id]['questions'] = array(
					'read'      => time(),
					'questions' => $read['data']['questions'],
					'more'      => !empty($read['data']['more'])
				);

			} else {

				$liveform->add_warning(lang(array(
					'string' => 'The questions could not be read: {var:1}',
					'vars'   => array(h($read['error']))
				)));

			}

		}

		header('Location: ' . $screen_url . '?account=' . $account_id . '#mp-questions');

		exit();

	}

	// Answer one waiting question of the last read. The question has to be in
	// that read and waiting, so an id the marketplace never listed is not
	// sent. A sent answer is noted on the read; whether the marketplace
	// published it is seen on the next read.
	if ($action === 'question_answer' && $account) {

		$question_id = isset($_POST['question_id']) ? trim((string)$_POST['question_id']) : '';

		$answer = isset($_POST['answer']) ? trim((string)$_POST['answer']) : '';

		$question = null;

		$question_index = null;

		foreach (mp_settings_marketplace_read($account_id, 'questions')['questions'] as $index => $row) {

			if (isset($row['id']) && ((string)$row['id'] === $question_id)) {

				$question = $row;

				$question_index = $index;

				break;

			}

		}

		if (!mp_can($account['provider'], 'answer_question')) {

			$liveform->add_warning(lang(array(
				'string' => '{var:1} cannot do this.',
				'vars'   => array(h(mp_provider_name($account['provider'])))
			)));

		} elseif (!$question) {

			$liveform->add_warning(lang('That question is no longer in the list. Read the questions again.'));

		} elseif (!empty($question['sent']) || empty($question['waiting'])) {

			$liveform->add_notice(lang('That question is not waiting for an answer.'));

		} else {

			$sent = mp_call($account['provider'], 'answer_question', array($account, $question_id, $answer));

			if ($sent['ok']) {

				$_SESSION['software']['marketplace_read'][$account_id]['questions']['questions'][$question_index]['sent'] = time();

				$_SESSION['software']['marketplace_read'][$account_id]['questions']['questions'][$question_index]['answer'] = $answer;

				log_activity(lang(array(
					'string' => 'A buyer question about {var:1} was answered on {var:2}.',
					'vars'   => array((string)$question['product'], mp_provider_name($account['provider']))
				)), 'system');

				$liveform->add_notice(lang(array(
					'string' => 'The answer was sent to {var:1}. It is published after their review.',
					'vars'   => array(h(mp_provider_name($account['provider'])))
				)));

			} else {

				$liveform->add_warning(lang(array(
					'string' => 'The answer was not sent: {var:1}',
					'vars'   => array(h($sent['error']))
				)));

			}

		}

		header('Location: ' . $screen_url . '?account=' . $account_id . '#mp-questions');

		exit();

	}

	if ($action === 'clear_queue' && $account) {

		db("DELETE FROM marketplace_sync_queue
			WHERE (account_id = '" . e($account_id) . "') AND (status = 'failed')");

		$liveform->add_notice(lang('Cleared.'));

		header('Location: ' . $screen_url . '?account=' . $account_id . '#mp-queue');

			exit();

	}

}

// The last read of an account's returns or questions in this session (see
// the claims_read and questions_read actions): {read, <kind>, more}, read 0
// when nothing was read yet.
function mp_settings_marketplace_read($account_id, $kind) {

	$read = $_SESSION['software']['marketplace_read'][(int)$account_id][$kind] ?? null;

	if (!is_array($read) || !isset($read[$kind]) || !is_array($read[$kind])) {

		return array('read' => 0, $kind => array(), 'more' => false);

	}

	return array(
		'read'   => (int)($read['read'] ?? 0),
		$kind    => $read[$kind],
		'more'   => !empty($read['more'])
	);

}

// The files posted in one multi-file input as {files: [{name, data}], error}.
// No file chosen is no files; a file the server did not take in whole is an
// error naming it, so a rejection never goes without a document the operator
// attached.
function mp_settings_claim_files($field) {

	$posted = isset($_FILES[$field]['name']) && is_array($_FILES[$field]['name']) ? $_FILES[$field] : null;

	$files = array();

	if (!$posted) {

		return array('files' => $files, 'error' => '');

	}

	foreach ($posted['name'] as $index => $name) {

		$error = isset($posted['error'][$index]) ? (int)$posted['error'][$index] : UPLOAD_ERR_NO_FILE;

		if ($error === UPLOAD_ERR_NO_FILE) {

			continue;

		}

		$name = pg_basename((string)$name);

		$tmp = isset($posted['tmp_name'][$index]) ? (string)$posted['tmp_name'][$index] : '';

		$data = (($error === UPLOAD_ERR_OK) && ($tmp !== '') && is_uploaded_file($tmp)) ? file_get_contents($tmp) : false;

		if (!is_string($data)) {

			return array('files' => array(), 'error' => lang(array(
				'string' => '{var:1} could not be uploaded. It may be larger than this server takes.',
				'vars'   => array($name)
			)));

		}

		$files[] = array('name' => $name, 'data' => $data);

	}

	return array('files' => $files, 'error' => '');

}

// A return unit's status as a pill. Their status names are shown in words
// the operator knows; one this screen does not know is shown as it came.
function mp_settings_claim_status($status) {

	$labels = array(
		'Created'           => array(lang('On its way back'), 'off'),
		'WaitingInAction'   => array(lang('Waiting for you'), 'bad'),
		'WaitingFraudCheck' => array(lang('Fraud check'), 'off'),
		'InAnalysis'        => array(lang('In analysis'), 'off'),
		'Accepted'          => array(lang('Accepted'), 'on'),
		'Rejected'          => array(lang('Rejected'), 'off'),
		'Cancelled'         => array(lang('Cancelled'), 'off'),
		'Unresolved'        => array(lang('Unresolved'), 'off')
	);

	if ($status === '') {

		return '';

	}

	$label = isset($labels[$status]) ? $labels[$status] : array(h($status), 'off');

	return '<span class="mp-pill ' . $label[1] . '">' . $label[0] . '</span>';

}

// A question's status as a pill: an answer sent from here first, else their
// status in words the operator knows, else as it came.
function mp_settings_question_status($question) {

	if (!empty($question['sent'])) {

		return '<span class="mp-pill on" title="' . h(mp_settings_ago((int)$question['sent'])) . '">'
			. lang('Answer sent') . '</span>';

	}

	$labels = array(
		'WAITING_FOR_ANSWER'  => array(lang('Waiting for you'), 'bad'),
		'WAITING_FOR_APPROVE' => array(lang('Answer in review'), 'off'),
		'ANSWERED'            => array(lang('Answered'), 'on'),
		'REJECTED'            => array(lang('Answer rejected'), 'bad'),
		'REPORTED'            => array(lang('Reported'), 'off'),
		'UNANSWERED'          => array(lang('Time ran out'), 'off')
	);

	$status = isset($question['status']) ? (string)$question['status'] : '';

	if ($status === '') {

		return '';

	}

	$label = isset($labels[$status]) ? $labels[$status] : array(h($status), 'off');

	return '<span class="mp-pill ' . $label[1] . '">' . $label[0] . '</span>';

}

// The "Reject" form under one return with units waiting for the seller,
// folded until opened: the waiting units to tick, the marketplace's reason,
// an explanation and the documents. Without a read of the reasons there is
// nothing to choose, and the form says to read the returns again.
function mp_settings_claim_reject_form($account_id, $claim, $reasons) {

	$key = substr(md5((string)$claim['claim_id']), 0, 12);

	$out = '
				<details class="small" style="grid-column:1/-1">
					<summary class="pg-link-secondary">' . lang('Reject') . '</summary>';

	if (!$reasons) {

		return $out . '
					<p class="mt-3 mb-0 opacity-75">' . lang('The reasons for rejecting a return were not read. Read the returns again.') . '</p>
				</details>';

	}

	$units = '';

	foreach ($claim['units'] as $unit) {

		if (empty($unit['waiting'])) {

			continue;

		}

		$units .= '
						<div class="form-field">
							<input class="check" type="checkbox" name="unit_ids[]" value="' . h((string)$unit['id']) . '"
								id="claim_unit_' . h((string)$unit['id']) . '" checked>
							<label class="" for="claim_unit_' . h((string)$unit['id']) . '">'
								. h((string)$unit['product'])
								. (((string)$unit['barcode'] !== '') ? ' <code>' . h((string)$unit['barcode']) . '</code>' : '') . '</label>
						</div>';

	}

	$options = '<option value="">' . lang('Choose a reason') . '</option>';

	foreach ($reasons as $reason) {

		$options .= '<option value="' . h((string)$reason['id']) . '">' . h((string)$reason['name'])
			. (empty($reason['file']) ? '' : ' ' . h(lang('(document needed)'))) . '</option>';

	}

	return $out . '
					<form method="post" action="marketplace_settings.php" enctype="multipart/form-data" class="mt-3" style="max-width:640px"
						onsubmit="this.querySelector(\'button[type=submit]\').disabled = true;">
						' . get_token_field() . '
						<input type="hidden" name="mp_action" value="claim_reject">
						<input type="hidden" name="account_id" value="' . (int)$account_id . '">
						<input type="hidden" name="claim_id" value="' . h((string)$claim['claim_id']) . '">
						<div class="mb-3">' . $units . '
						</div>
						<label class="form-label" for="claim_reason_' . $key . '">' . lang('Reason') . '</label>
						<select class="form-control form-control-sm mb-3" id="claim_reason_' . $key . '" name="reason_id" required>'
							. $options . '</select>
						<label class="form-label" for="claim_description_' . $key . '">' . lang('Explanation') . '</label>
						<textarea class="form-control form-control-sm mb-3" id="claim_description_' . $key . '" name="description"
							maxlength="500" rows="3" required></textarea>
						<label class="form-label" for="claim_files_' . $key . '">' . lang('Documents') . '</label>
						<input type="file" class="form-control form-control-sm" id="claim_files_' . $key . '" name="claim_files[]" multiple
							accept="image/*,video/*,.pdf,.doc,.docx">
						<div class="form-text mb-3">'
							. lang('Photos, videos, PDF or Word files of what came back: at most five, 5 MB each.') . '</div>
						<button type="submit" class="btn btn-sm btn-outline theme-danger">' . lang('Send rejection') . '</button>
					</form>
				</details>';

}

// The "Send invoice link" form under one imported order, folded until opened.
// The operator types the address the buyer can open; the screen cannot offer
// one of its own (see the invoice_link action).
function mp_settings_invoice_form($account_id, $row) {

	$map_id = (int)$row['id'];

	return '
				<details class="small" style="grid-column:1/-1">
					<summary class="pg-link-secondary">' . lang('Send invoice link') . '</summary>
					<form method="post" action="marketplace_settings.php" class="d-flex gap-3 flex-wrap mt-3"
						onsubmit="this.querySelector(\'button[type=submit]\').disabled = true;">
						' . get_token_field() . '
						<input type="hidden" name="mp_action" value="invoice_link">
						<input type="hidden" name="account_id" value="' . (int)$account_id . '">
						<input type="hidden" name="map_id" value="' . $map_id . '">
						<label class="visually-hidden" for="invoice_url_' . $map_id . '">' . lang('Invoice link') . '</label>
						<input type="url" class="form-control form-control-sm" id="invoice_url_' . $map_id . '"
							name="invoice_url" maxlength="1000" required style="max-width:520px"
							placeholder="https://">
						<button type="submit" class="btn btn-sm btn-outline theme-secondary">' . lang('Send') . '</button>
					</form>
					<div class="form-text">'
						. lang('The address of the e-Archive or e-Invoice PDF that the buyer can open without signing in. The marketplace keeps it with the order and it has to stay reachable for ten years. A link once sent cannot be changed from here.') . '</div>
				</details>';

}

// The marketplace's shipping label under one imported order
// (mp_label_offer()): the link to the label kept, a ZPL file saved for the
// thermal printer through shipment_label.php, or the folded form that asks for
// it with the number of boxes, one label each.
function mp_settings_label_line($account_id, $row, $offer, $provider_name) {

	if ($offer['offer'] === 'print') {

		return '
				<div class="small" style="grid-column:1/-1">
					<a class="pg-link-secondary" href="shipment_label.php?id=' . (int)$offer['label_id'] . '">
						<i class="bi bi-printer me-1" aria-hidden="true"></i>' . lang('Print Label') . '</a>
					<span class="opacity-75 ms-1">' . lang('ZPL file for a thermal label printer') . '</span>
				</div>';

	}

	if ($offer['offer'] !== 'create') {

		return '';

	}

	$map_id = (int)$row['id'];

	return '
				<details class="small" style="grid-column:1/-1">
					<summary class="pg-link-secondary">' . h(lang(array('string' => '{var:1} shipping label', 'vars' => array($provider_name)))) . '</summary>
					<form method="post" action="marketplace_settings.php" class="d-flex gap-3 flex-wrap align-items-center mt-3"
						onsubmit="this.querySelector(\'button[type=submit]\').disabled = true;">
						' . get_token_field() . '
						<input type="hidden" name="mp_action" value="common_label">
						<input type="hidden" name="account_id" value="' . (int)$account_id . '">
						<input type="hidden" name="map_id" value="' . $map_id . '">
						<label class="form-label mb-0" for="label_boxes_' . $map_id . '">' . lang('Boxes') . '</label>
						<input type="number" class="form-control form-control-sm" id="label_boxes_' . $map_id . '"
							name="boxes" value="1" min="1" max="' . mp_label_max_boxes() . '" step="1" required style="max-width:90px">
						<button type="submit" class="btn btn-sm btn-outline theme-secondary"
							data-confirm-content="' . h(lang(array(
								'string' => '{var:1} makes the shipping label of this package for its carrier, one per box. A package not accepted yet is accepted first, which starts its dispatch time. Continue?',
								'vars'   => array($provider_name)
							))) . '">' . lang('Get label') . '</button>
					</form>
					<div class="form-text">' . h(lang(array(
						'string' => 'The label {var:1} makes for its own carrier, as a file for a thermal label printer (ZPL). It is kept with the order and listed on the order screen.',
						'vars'   => array($provider_name)
					))) . '</div>
				</details>';

}

// When something last happened, in words.
//
// The same shape the application screen uses. get_relative_time() is the
// site-wide one and returns markup with a tooltip, which is right in a table
// cell and wrong inside an anchor and a pill, where this is used.
function mp_settings_ago($timestamp) {

	$timestamp = (int) $timestamp;

	if ($timestamp <= 0) {

		return lang('Never');

	}

	$seconds = time() - $timestamp;

	if ($seconds < 60) { return lang('Just now'); }

	if ($seconds < 3600) {

		$count = (int)($seconds / 60);

		return lang(array('string' => '{var:1} minute{suffix:1} ago', 'vars' => $count, 'suffix' => ($count == 1 ? '' : 's')));

	}

	if ($seconds < 86400) {

		$count = (int)($seconds / 3600);

		return lang(array('string' => '{var:1} hour{suffix:1} ago', 'vars' => $count, 'suffix' => ($count == 1 ? '' : 's')));

	}

	$count = (int)($seconds / 86400);

	return lang(array('string' => '{var:1} day{suffix:1} ago', 'vars' => $count, 'suffix' => ($count == 1 ? '' : 's')));

}

// The credential fields this provider asks for, read out of the form.
function mp_settings_credentials_from_post($provider) {

	return mp_provider_credentials($provider, $_POST);

}

// One credential field of the create or edit form.
//
// $current is the stored value when editing. A text field never shows it back
// - a blank box keeps it, the placeholder hints at it - while a select shows
// the stored choice, because it is not a secret and has no blank state.
// $disabled keeps the field of a marketplace that is not chosen out of the
// submitted form.
function mp_settings_credential_field($prefix, $field, $meta, $current, $editing, $disabled = false) {

	$id = $prefix . '_' . $field;

	$out = '
			<div class="mb-5">
				<label class="form-label" for="' . h($id) . '">' . h(lang($meta['label'])) . '</label>';

	if (mp_provider_field_is_select($meta)) {

		$selected = ($current !== '' && isset($meta['options'][$current]))
			? $current
			: (isset($meta['default']) ? (string)$meta['default'] : '');

		$out .= '
				<select class="form-control" id="' . h($id) . '" name="' . h($field) . '"' . ($disabled ? ' disabled' : '') . '>';

		foreach ((array)$meta['options'] as $value => $label) {

			$out .= '<option value="' . h((string)$value) . '"' . (((string)$value === $selected) ? ' selected' : '') . '>'
				. h(lang($label)) . '</option>';

		}

		$out .= '
				</select>
			</div>';

		return $out;

	}

	$out .= '
				<input type="' . (!empty($meta['secret']) ? 'password' : 'text') . '" class="form-control"
					id="' . h($id) . '" name="' . h($field) . '" autocomplete="' . (!empty($meta['secret']) ? 'new-password' : 'off') . '"'
					. ($editing ? ' placeholder="' . h(mp_credential_hint($current)) . '"' : '')
					. ($disabled ? ' disabled' : '') . '>'
				. ($editing ? '
				<div class="form-text">' . lang('Leave empty to keep what is stored.') . '</div>' : '') . '
			</div>';

	return $out;

}

/* ---------------------------------------------------------------------------
   Reads
   --------------------------------------------------------------------------- */

$accounts = mp_accounts();

$selected_id = isset($_GET['account']) ? (int)$_GET['account'] : 0;

if (!$selected_id && $accounts) {

	$selected_id = (int) $accounts[0]['id'];

}

$selected = $selected_id ? mp_account($selected_id) : null;

$map_counts = array('all' => 0, 'errors' => 0, 'stale' => 0);

$orders = array();

$order_labels = array();

$failed_orders = 0;

$queue_counts = array('waiting' => 0, 'sent' => 0, 'done' => 0, 'failed' => 0);

$failures = array();

if ($selected) {

	// How many products are matched, how many of them a send was refused
	// for, and how many matches belong to products deleted or binned since;
	// the same counts as the shop view of marketplace_products.php.
	$shop_counts = mp_matches_counts($selected_id);

	$map_counts = array(
		'all'    => $shop_counts['matched'],
		'errors' => $shop_counts['errors'],
		'stale'  => mp_matches_stale_count($selected_id)
	);

	foreach ((array) db_items("SELECT status, COUNT(*) AS n FROM marketplace_sync_queue
		WHERE account_id = '" . e($selected_id) . "' GROUP BY status") as $row) {

		$queue_counts[$row['status']] = (int) $row['n'];

	}

	$failed_orders = (int) db_value("SELECT COUNT(*) FROM marketplace_order_map
		WHERE (account_id = '" . e($selected_id) . "') AND (status = 'failed') AND (order_id = 0)");

	$orders = (array) db_items("SELECT marketplace_order_map.*, orders.order_number, orders.total,
			orders.billing_first_name, orders.billing_last_name, orders.status AS order_status
		FROM marketplace_order_map
		LEFT JOIN orders ON orders.id = marketplace_order_map.order_id
		WHERE marketplace_order_map.account_id = '" . e($selected_id) . "'
		ORDER BY marketplace_order_map.imported_timestamp DESC
		LIMIT 40");

	// What each package offers for the marketplace's shipping label: the
	// request, or the print of the label kept (mp_label_offer()).
	$order_labels = mp_label_card($selected, $orders);

	$failures = (array) db_items("SELECT marketplace_sync_queue.*, products.name AS product_name
		FROM marketplace_sync_queue
		LEFT JOIN products ON products.id = marketplace_sync_queue.product_id
		WHERE (marketplace_sync_queue.account_id = '" . e($selected_id) . "')
		  AND (marketplace_sync_queue.status = 'failed')
		ORDER BY marketplace_sync_queue.updated_timestamp DESC
		LIMIT 50");

}

$last_run = (int) db_value("SELECT marketplace_sync_last_run FROM config");

$providers = mp_providers();

$app_data = array(
	'accounts'  => array(),
	'providers' => array()
);

foreach ($accounts as $row) {

	$app_data['accounts'][] = array(
		'id'         => (int)$row['id'],
		'provider'   => $row['provider'],
		'name'       => $row['name'],
		'status'     => $row['status'],
		'push_stock' => !empty($row['push_stock']),
		'push_price' => !empty($row['push_price'])
	);

}

foreach ($providers as $key => $meta) {

	$app_data['providers'][$key] = array(
		'name'   => $meta['name'],
		'fields' => array_map(function ($field) { return $field['label']; }, $meta['fields'])
	);

}

/* ---------------------------------------------------------------------------
   The screen
   --------------------------------------------------------------------------- */

echo pg_page_shell(array(
	'title'                => lang('Marketplaces'),
	'extra classes'        => 'mp-screen',
	'icon'                 => 'shop',
	'heading'              => lang('Marketplaces'),
	'heading_description'  => lang('Keep the products you sell elsewhere in step with the ones you keep here.'),
)) . '

<style>@layer custom {
/* Same reasoning as the application list: six columns of real content do not
   fit a phone, so the block scrolls sideways rather than stacking every row
   into a paragraph nobody can scan. */
.mp-scroll { overflow-x: auto; overflow-y: hidden; }
.mp-head, .mp-row { display: grid; grid-template-columns: 2fr 1.4fr 1fr .9fr .9fr 40px;
	gap: 12px; align-items: center; min-width: 760px; }
.mp-head { padding: 0 14px 8px; font-size: 0.65625rem; letter-spacing: .06em; text-transform: uppercase; color: var(--pg-fg-2); }
.mp-row { padding: 11px 14px; border-top: 1px solid var(--pg-border-color); }
.mp-row b { font-size: 0.8125rem; display: block; }
.mp-row code { font-size: 0.6875rem; opacity: .7; }
.mp-pill { font-size: 0.65625rem; padding: 3px 9px; border-radius: 20px; font-weight: 600;
	border: 1px solid var(--pg-border-color); display: inline-block; white-space: nowrap; }
.mp-pill.on { color: var(--pg-success-fg-emphasis); border-color: color-mix(in srgb, var(--pg-success) 35%, transparent); }
.mp-pill.off { color: var(--pg-fg-2); }
.mp-pill.bad { color: var(--pg-danger-fg-emphasis); border-color: color-mix(in srgb, var(--pg-danger) 35%, transparent); }
.mp-tab { border: 1px solid var(--pg-border-color); border-radius: 8px; padding: 10px 14px;
	text-decoration: none; color: inherit; display: block; }
.mp-tab.sel { border-color: var(--pg-primary); background: var(--pg-bg-1); }
.mp-tab small { color: var(--pg-fg-2); }
.mp-count { display: flex; gap: 18px; flex-wrap: wrap; }
.mp-count div { min-width: 82px; }
.mp-count b { font-size: 1.25rem; display: block; line-height: 1.1; }
.mp-count span { font-size: 0.6875rem; color: var(--pg-fg-2); }
.mp-err { font-size: 0.71875rem; color: var(--pg-danger-fg-emphasis); word-break: break-word; }
/* Questions and failures are free text, not columns: they sit outside the
   sideways-scrolling block, so they wrap to the width of the card instead. */
.mp-question { padding: 11px 14px; border-top: 1px solid var(--pg-border-color); }
.mp-fail-name { flex: 1 1 150px; min-width: 0; }
.mp-fail .mp-err { flex: 1 1 100%; }
@media (min-width: 768px) { .mp-fail .mp-err { flex: 1 1 0; min-width: 0; } }
.mp-fail-time { color: var(--pg-fg-2); }
}</style>

<main id="content" class="container-fluid">
	<div class="row"><div class="col-12">
		' . $liveform->output_errors() . $liveform->output_notices() . $liveform->get_warnings() . '
	</div></div>
';

// Rendered, so cleared. get_warnings() and output_notices() only read the
// session - nothing empties it - so a warning shown once would be shown on
// every visit to this screen from then on.
$liveform->remove_form();

if (!$accounts) {

	echo '
	<div class="card">
		<div class="card-body text-center py-12">
			<i class="bi bi-shop" style="font-size:2.125rem;opacity:.35"></i>
			<p class="mt-5 mb-1"><b>' . lang('No marketplace is connected yet.') . '</b></p>
			<p class="small opacity-75 mb-7" style="max-width:520px;margin:0 auto">'
				. lang('Connect a marketplace account and match your products to the codes it knows them by. Stock and price changes here are then sent over there on a schedule.') . '</p>
			<button type="button" class="btn btn-solid theme-primary" data-bs-toggle="dialog" data-bs-target="#mp_new">
				<i class="bi bi-plus-lg me-1"></i>' . lang('Connect a Marketplace') . '</button>
		</div>
	</div>';

} else {

	echo '
	<div class="d-flex align-items-center gap-3 mb-5 flex-wrap">
		<span class="small opacity-75">
			<i class="bi bi-clock-history me-1"></i>'
			. ($last_run
				? lang(array('string' => 'Last run {var:1}', 'vars' => array(mp_settings_ago($last_run))))
				: lang('The scheduled job has not run yet.')) . '
		</span>
		<div class="flex-grow-1"></div>
		<button type="button" class="btn btn-sm btn-solid theme-primary" data-bs-toggle="dialog" data-bs-target="#mp_new">
			<i class="bi bi-plus-lg me-1"></i>' . lang('Connect a Marketplace') . '</button>
	</div>

	<div class="row g-5 mb-5">';

	foreach ($accounts as $row) {

		$is_selected = ((int)$row['id'] === $selected_id);

		$state = ($row['status'] === 'active')
			? '<span class="mp-pill on">' . lang('Active') . '</span>'
			: '<span class="mp-pill off">' . lang('Paused') . '</span>';

		echo '
		<div class="col-12 md:col-6 xl:col-4">
			<a class="mp-tab' . ($is_selected ? ' sel" aria-current="page' : '') . '" href="marketplace_settings.php?account=' . (int)$row['id'] . '">
				<div class="d-flex align-items-center gap-3">
					<i class="bi bi-shop"></i>
					<b>' . h($row['name']) . '</b>
					<span class="ms-auto">' . $state . '</span>
				</div>
				<small>' . h(mp_provider_name($row['provider']))
					. ($row['last_error'] !== ''
						? ' &middot; <span class="fg-danger">' . h(mb_substr($row['last_error'], 0, 60)) . '</span>'
						: ($row['last_success']
							? ' &middot; ' . lang(array('string' => 'worked {var:1}', 'vars' => array(mp_settings_ago((int)$row['last_success']))))
							: ' &middot; ' . lang('not tried yet'))) . '</small>
			</a>
		</div>';

	}

	echo '
	</div>';

}

if ($selected) {

	// Articles are filed under the marketplace's categories on the listings
	// view of marketplace_products.php, where the connector reads them.
	$can_listings = mp_can($selected['provider'], 'category_tree');

	// Orders come in only from a marketplace whose connector reads them, and
	// are accepted only by one that can accept them. The job skips the rest
	// without a word, so their switches and the orders card are not drawn: a
	// switch shown on would promise orders that never come, while the stock
	// they sell there is still sold here.
	$can_orders = mp_can($selected['provider'], 'pull_orders');

	$can_approve = $can_orders && mp_can($selected['provider'], 'approve_lines');

	// The invoice link form needs the column its success is written to; a site
	// whose files are newer than its database draws no form.
	$can_invoice = $can_orders && mp_can($selected['provider'], 'send_invoice_link') && mp_invoice_sent_ready();

	// The returns card, for a connector that reads returns; its approve
	// button only where the connector can approve them.
	$can_claims = mp_can($selected['provider'], 'claims');

	$can_approve_claims = $can_claims && mp_can($selected['provider'], 'approve_claim');

	$can_reject_claims = $can_claims && mp_can($selected['provider'], 'reject_claim');

	// The questions card, for a connector that reads questions; its answer
	// forms only where the connector can answer them.
	$can_questions = mp_can($selected['provider'], 'questions');

	$can_answer_questions = $can_questions && mp_can($selected['provider'], 'answer_question');

	// A marketplace whose connector lists the store's products there gets the
	// bulk matching screen (marketplace_products.php).
	$can_read_products = mp_can($selected['provider'], 'remote_products');

	$provider_fields = isset($providers[$selected['provider']]['fields'])
		? $providers[$selected['provider']]['fields']
		: array();

	/* ------------------------------------------------------------ account */

	echo '
	<div class="card mb-5">
		<div class="card-header d-flex align-items-center flex-wrap gap-3">
			<i class="bi bi-sliders me-1"></i><b>' . h($selected['name']) . '</b>
			<span class="small opacity-75">' . h(mp_provider_name($selected['provider'])) . '</span>'
			. ((isset($selected['credentials']['environment']) && $selected['credentials']['environment'] === 'stage')
				? ' <span class="mp-pill off">' . lang('Test environment') . '</span>' : '') . '
			<div class="ms-auto d-flex flex-wrap justify-content-end gap-3">
				<form method="post" action="marketplace_settings.php" class="d-inline">
					' . get_token_field() . '
					<input type="hidden" name="mp_action" value="test">
					<input type="hidden" name="account_id" value="' . $selected_id . '">
					<button type="submit" class="btn btn-sm btn-outline theme-secondary">
						<i class="bi bi-plug me-1"></i>' . lang('Test connection') . '</button>
				</form>
				<button type="button" class="btn btn-sm btn-outline theme-secondary" data-bs-toggle="drawer" data-bs-target="#mp_drawer">
					<i class="bi bi-pencil me-1"></i>' . lang('Settings') . '</button>
			</div>
		</div>
		<div class="card-body">
			<div class="mp-count">
				<div><b>' . pg_format_number($map_counts['all'], 0) . '</b><span>' . lang('matched products') . '</span></div>
				<div><b>' . pg_format_number($queue_counts['waiting'], 0) . '</b><span>' . lang('waiting') . '</span></div>
				<div><b>' . pg_format_number($queue_counts['sent'], 0) . '</b><span>' . lang('with the marketplace') . '</span></div>
				<div><b class="' . ($queue_counts['failed'] ? 'fg-danger' : '') . '">'
					. pg_format_number($queue_counts['failed'], 0) . '</b><span>' . lang('failed') . '</span></div>
			</div>';

	if ($selected['status'] !== 'active') {

		echo '
			<div class="alert theme-warning small mt-5 mb-0">
				<i class="bi bi-pause-circle me-1"></i>'
				. lang('This account is paused. Nothing is sent while it is, and changes made in the meantime are not queued.') . '
			</div>';

	}

	if ($selected['last_error'] !== '') {

		echo '
			<div class="alert theme-danger small mt-5 mb-0">
				<i class="bi bi-exclamation-triangle me-1"></i>' . h($selected['last_error']) . '
			</div>';

	}

	echo '
		</div>
	</div>';

	/* ------------------------------------------------------------ mapping */

	// Which products are sent, and under which codes, is looked after on
	// marketplace_products.php: the shop's products with their pictures, and
	// the marketplace's catalogue where the connector reads it. This card
	// says how many and leads there.
	echo '
	<div class="card mb-5" id="mp-map">
		<div class="card-header d-flex align-items-center flex-wrap gap-3">
			<i class="bi bi-link-45deg me-1"></i><b>' . lang('Matched products') . '</b>
			<div class="ms-auto d-flex flex-wrap justify-content-end gap-3">
				<form method="post" action="marketplace_settings.php" class="d-inline">
					' . get_token_field() . '
					<input type="hidden" name="mp_action" value="push_all">
					<input type="hidden" name="account_id" value="' . $selected_id . '">
					<button type="submit" class="btn btn-sm btn-outline theme-secondary"' . ($map_counts['all'] ? '' : ' disabled') . '>
						<i class="bi bi-arrow-repeat me-1"></i>' . lang('Send all now') . '</button>
				</form>
				<a class="btn btn-sm ' . ($can_read_products ? 'btn-outline theme-secondary' : 'btn-solid theme-primary') . '" href="marketplace_products.php?account=' . $selected_id . '&amp;mode=shop">
					<i class="bi bi-shop me-1"></i>' . h(lang('Products in this shop')) . '</a>'
				. ($can_read_products ? '
				<a class="btn btn-sm btn-solid theme-primary" href="marketplace_products.php?account=' . $selected_id . '">
					<i class="bi bi-boxes me-1"></i>' . h(lang('Match in bulk with pictures')) . '</a>' : '')
				. ($can_listings ? '
				<a class="btn btn-sm btn-outline theme-secondary" href="marketplace_products.php?account=' . $selected_id . '&amp;mode=listings">
					<i class="bi bi-tags me-1"></i>' . h(lang('Listings')) . '</a>' : '') . '
			</div>
		</div>
		<div class="card-body small">'
			. ($map_counts['all']
				? h(lang(array('string' => '{var:1} products are matched and sent to {var:2}.',
					'vars' => array(pg_format_number($map_counts['all'], 0), mp_provider_name($selected['provider'])))))
					. ($map_counts['errors']
						? ' <a class="fg-danger" href="marketplace_products.php?account=' . $selected_id . '&amp;mode=shop&amp;view=errors">'
							. h(lang(array('string' => '{var:1} of them were not sent.', 'vars' => array(pg_format_number($map_counts['errors'], 0))))) . '</a>'
						: '')
				: h(lang('No product is matched yet. Nothing is sent until at least one is.')))
			. ($map_counts['stale'] ? '
			<form method="post" action="marketplace_settings.php" class="d-flex flex-wrap align-items-center gap-3 mt-3 disable_shortcut">
				' . get_token_field() . '
				<input type="hidden" name="mp_action" value="clear_stale_matches">
				<input type="hidden" name="account_id" value="' . $selected_id . '">
				<span class="fg-warning">' . h(lang(array('string' => '{var:1} match(es) belong to products deleted since or in the Recycle Bin. They hold their codes, and no list shows them.',
					'vars' => array(pg_format_number($map_counts['stale'], 0))))) . '</span>
				<button type="submit" id="mp_clear_stale" class="btn btn-sm btn-outline theme-warning" data-confirm-content="' . h(lang(array('string' => 'Remove {var:1} stale match(es) of {var:2}? Their codes are freed; a product restored from the Recycle Bin afterwards comes back unmatched.',
					'vars' => array(pg_format_number($map_counts['stale'], 0), $selected['name'])))) . '"><i class="bi bi-trash me-1"></i>' . h(lang('Remove them')) . '</button>
			</form>' : '') . '
		</div>
	</div>';

	/* ------------------------------------------------------------ webhook */

	// The marketplace's webhook, for a connector that registers one. It sits at
	// the top of the orders card. While one is registered and import is off,
	// the card is not drawn but the webhook keeps delivering (and being
	// refused), so it is drawn on its own, where it can still be removed. The
	// key is never printed.
	$webhook_block = '';

	$webhook_id = isset($selected['credentials']['webhook_id']) ? (string)$selected['credentials']['webhook_id'] : '';

	if ($can_orders && mp_can($selected['provider'], 'register_webhook')
		&& (($webhook_id !== '') || !empty($selected['pull_orders']))) {

		$provider_name = mp_provider_name($selected['provider']);

		if ($webhook_id !== '') {

			$webhook_text = h(lang(array(
				'string' => '{var:1} sends new orders to this site as they happen.',
				'vars'   => array($provider_name)
			)));

			if ($selected['status'] !== 'active' || empty($selected['pull_orders'])) {

				$webhook_text .= '<div class="fg-danger mt-1">' . h(lang(array(
					'string' => 'While this account is paused or does not bring orders in, the orders {var:1} sends are refused, and after repeated refusals {var:1} switches the webhook off. Remove the webhook if this lasts.',
					'vars'   => array($provider_name)
				))) . '</div>';

			}

			$webhook_action = 'webhook_remove';

			$webhook_onsubmit = '';

			$webhook_button = '<button type="submit" class="btn btn-sm btn-outline theme-danger"
						onclick="return confirm(' . h(json_encode(lang('Remove the webhook?'))) . ')">
						<i class="bi bi-x-lg me-1"></i>' . lang('Remove webhook') . '</button>';

		} else {

			$webhook_text = h(lang(array(
				'string' => 'Orders are read every five minutes. Register a webhook so {var:1} sends them as they happen.',
				'vars'   => array($provider_name)
			)));

			$webhook_action = 'webhook_register';

			// Registering waits on the marketplace, longer than the panel's
			// three-second guard against a second submit, so the button stays
			// disabled until the answer reloads the screen. Its label stays as
			// it is; it has no name, so nothing is dropped from the request.
			$webhook_onsubmit = ' onsubmit="this.querySelector(\'button[type=submit]\').disabled = true;"';

			$webhook_button = '<button type="submit" class="btn btn-sm btn-outline theme-secondary">
						<i class="bi bi-broadcast me-1"></i>' . lang('Register webhook') . '</button>';

		}

		$webhook_block = '
			<div class="d-flex align-items-center flex-wrap gap-3 small px-3 pb-5 mb-5 border-bottom" id="mp-webhook">
				<i class="bi bi-broadcast"></i>
				<div class="flex-grow-1">' . $webhook_text . '</div>
				<form method="post" action="marketplace_settings.php" class="d-inline"' . $webhook_onsubmit . '>
					' . get_token_field() . '
					<input type="hidden" name="mp_action" value="' . $webhook_action . '">
					<input type="hidden" name="account_id" value="' . $selected_id . '">
					' . $webhook_button . '
				</form>
			</div>';

	}

	/* ------------------------------------------------------------- orders */

	if ($webhook_block !== '' && empty($selected['pull_orders'])) {

		echo '
	<div class="card mb-5">
		<div class="card-body px-3 pt-5 pb-0">' . $webhook_block . '
		</div>
	</div>';

	}

	if ($can_orders && !empty($selected['pull_orders'])) {

		echo '
	<div class="card mb-5" id="mp-orders">
		<div class="card-header d-flex align-items-center flex-wrap gap-3">
			<i class="bi bi-bag-check me-1"></i><b>' . lang('Imported orders') . '</b>
			<span class="small opacity-75 ms-auto">'
				. ((int)$selected['last_order_pull']
					? h(lang(array('string' => 'Orders read up to {var:1}',
						'vars' => array(mp_settings_ago((int)$selected['last_order_pull'])))))
					: lang('Never pulled')) . '</span>'
			. ($failed_orders ? '
			<form method="post" action="marketplace_settings.php" class="d-inline">
				' . get_token_field() . '
				<input type="hidden" name="mp_action" value="retry_orders">
				<input type="hidden" name="account_id" value="' . $selected_id . '">
				<button type="submit" class="btn btn-sm btn-outline theme-secondary"
					title="' . h(lang('Match the products first. The next run reads the failed orders again.')) . '">
					<i class="bi bi-arrow-clockwise me-1"></i>' . lang('Try again') . '</button>
			</form>' : '') . '
		</div>
		<div class="card-body px-3 py-5">' . $webhook_block;

		if (!$orders) {

			echo '
			<p class="small opacity-75 text-center my-7 mb-0">'
			. lang('No order has come in from this marketplace yet.') . '</p>';

		} else {

			echo '
			<div class="mp-scroll">
			<div class="mp-head" style="grid-template-columns:1.4fr 1.4fr 1fr .9fr .9fr 40px">
				<span>' . lang('Order') . '</span><span>' . lang('Customer') . '</span>
				<span>' . lang('Total') . '</span><span>' . lang('Status') . '</span>
				<span>' . lang('Imported') . '</span><span></span>
			</div>';

			foreach ($orders as $row) {

				$name = trim((string)$row['billing_first_name'] . ' ' . (string)$row['billing_last_name']);

				echo '
			<div class="mp-row" style="grid-template-columns:1.4fr 1.4fr 1fr .9fr .9fr 40px">
				<span>
					<b>' . h((string)$row['remote_order_id']) . '</b>
					<code>' . h((string)$row['remote_package_id']) . '</code>
				</span>
				<span>' . (($name !== '') ? h($name) : '&mdash;') . '</span>
				<span>' . (($row['order_id'] && $row['total'] !== null)
					? prepare_price_for_output((int)$row['total'], false, 0, 'html', false) : '&mdash;') . '</span>
				<span>' . (($row['status'] === 'imported')
						? '<span class="mp-pill on">' . h((string)$row['remote_status']) . '</span>'
						: '<span class="mp-pill bad">' . h(lang('Failed')) . '</span>')
					. (!empty($row['approved'])
						? ' <span class="mp-pill on">' . lang('Accepted') . '</span>' : '')
					. (!empty($row['invoice_sent'])
						? ' <span class="mp-pill on" title="' . h(mp_settings_ago((int)$row['invoice_sent'])) . '">'
							. lang('Invoice sent') . '</span>' : '') . '
					' . ($row['last_error'] !== ''
						? '<div class="mp-err">' . h(mb_substr((string)$row['last_error'], 0, 120)) . '</div>' : '') . '</span>
				<span class="small opacity-75">' . h(mp_settings_ago((int)$row['imported_timestamp'])) . '</span>
				<span>' . ($row['order_id']
					? '<a class="btn btn-sm btn-link p-0" href="view_order.php?id=' . (int)$row['order_id'] . '" title="'
						. lang('View Order') . '"><i class="bi bi-box-arrow-up-right"></i></a>'
					: '') . '</span>'
				. (($can_invoice && ($row['status'] === 'imported') && $row['order_id'] && empty($row['invoice_sent'])
					&& (mp_order_stage($selected, $row) !== 'cancelled'))
					? mp_settings_invoice_form($selected_id, $row) : '')
				. (isset($order_labels[(int)$row['order_id']])
					? mp_settings_label_line($selected_id, $row, $order_labels[(int)$row['order_id']], mp_provider_name($selected['provider'])) : '') . '
			</div>';

			}

			echo '
			</div>';

		}

		echo '
		</div>
	</div>';

	}

	/* ------------------------------------------------------------ returns */

	// The buyers' returns, read on a button and kept in the session (see the
	// claims_read action). A return with units waiting for the seller can be
	// approved after a confirm, or rejected with one of the marketplace's
	// reasons and the documents that show it (claim_reject).
	if ($can_claims) {

		$claims_read = mp_settings_marketplace_read($selected_id, 'claims');

		$claim_reasons = mp_settings_marketplace_read($selected_id, 'claim_reasons')['claim_reasons'];

		echo '
	<div class="card mb-5" id="mp-claims">
		<div class="card-header d-flex align-items-center flex-wrap gap-3">
			<i class="bi bi-arrow-return-left me-1"></i><b>' . h(lang(array(
				'string' => 'Returns from {var:1}',
				'vars'   => array(mp_provider_name($selected['provider']))
			))) . '</b>
			<span class="small opacity-75 ms-auto">'
				. ($claims_read['read']
					? h(lang(array('string' => 'Read {var:1}', 'vars' => array(mp_settings_ago($claims_read['read'])))))
					: lang('Not read yet')) . '</span>
			<form method="post" action="marketplace_settings.php" class="d-inline"
				onsubmit="this.querySelector(\'button[type=submit]\').disabled = true;">
				' . get_token_field() . '
				<input type="hidden" name="mp_action" value="claims_read">
				<input type="hidden" name="account_id" value="' . $selected_id . '">
				<button type="submit" class="btn btn-sm btn-outline theme-secondary">
					<i class="bi bi-arrow-clockwise me-1"></i>' . lang('Read returns') . '</button>
			</form>
		</div>
		<div class="card-body px-3 py-5">';

		if (!$claims_read['read']) {

			echo '
			<p class="small opacity-75 text-center my-7 mb-0">'
			. lang('Returns are read from the marketplace when you ask: the last two weeks, every status.') . '</p>';

		} elseif (!$claims_read['claims']) {

			echo '
			<p class="small opacity-75 text-center my-7 mb-0">'
			. lang('No return in the last two weeks.') . '</p>';

		} else {

			echo '
			<div class="mp-scroll">
			<div class="mp-head" style="grid-template-columns:1.2fr 1.2fr 2.6fr .9fr 110px">
				<span>' . lang('Order') . '</span><span>' . lang('Customer') . '</span>
				<span>' . lang('Products') . '</span><span>' . lang('Date') . '</span><span></span>
			</div>';

			foreach ($claims_read['claims'] as $claim) {

				$waiting = mp_claim_waiting($claim);

				$units = '';

				foreach ($claim['units'] as $unit) {

					$units .= '
					<div class="small">' . h((string)$unit['product'])
						. (((string)$unit['barcode'] !== '') ? ' <code>' . h((string)$unit['barcode']) . '</code>' : '')
						. ' ' . mp_settings_claim_status((string)$unit['status'])
						. (((string)$unit['reason'] !== '') ? '<div class="opacity-75">' . h((string)$unit['reason'])
							. (((string)$unit['note'] !== '') ? ' &mdash; &ldquo;' . h((string)$unit['note']) . '&rdquo;' : '')
							. '</div>' : '') . '
					</div>';

				}

				echo '
			<div class="mp-row" style="grid-template-columns:1.2fr 1.2fr 2.6fr .9fr 110px">
				<span>
					<b>' . h((string)$claim['order_number']) . '</b>
					' . (((string)$claim['carrier'] !== '')
						? '<div class="small opacity-75">' . h((string)$claim['carrier'])
							. (((string)$claim['tracking'] !== '') ? ' <code>' . h((string)$claim['tracking']) . '</code>' : '') . '</div>'
						: '') . '
				</span>
				<span>' . (((string)$claim['customer'] !== '') ? h((string)$claim['customer']) : '&mdash;') . '</span>
				<span>' . $units . '
				</span>
				<span class="small opacity-75">' . ($claim['claimed'] ? h(mp_settings_ago((int)$claim['claimed'])) : '&mdash;') . '</span>
				<span>';

				if (!empty($claim['approved'])) {

					echo '<span class="mp-pill on" title="' . h(mp_settings_ago((int)$claim['approved'])) . '">'
						. lang('Approval sent') . '</span>';

				} elseif (!empty($claim['rejected'])) {

					echo '<span class="mp-pill off" title="' . h(mp_settings_ago((int)$claim['rejected'])) . '">'
						. lang('Rejection sent') . '</span>';

				} elseif ($waiting && $can_approve_claims) {

					echo '
					<form method="post" action="marketplace_settings.php" class="d-inline"
						onsubmit="if (!confirm(' . h(json_encode(lang(array(
							'string' => 'Approve the return of order {var:1}? The buyer is refunded for {var:2} units, and this cannot be undone from here.',
							'vars'   => array((string)$claim['order_number'], count($waiting))
						)))) . ')) return false; this.querySelector(\'button[type=submit]\').disabled = true;">
						' . get_token_field() . '
						<input type="hidden" name="mp_action" value="claim_approve">
						<input type="hidden" name="account_id" value="' . $selected_id . '">
						<input type="hidden" name="claim_id" value="' . h((string)$claim['claim_id']) . '">
						<button type="submit" class="btn btn-sm btn-outline theme-success">' . lang('Approve') . '</button>
					</form>';

				}

				echo '</span>';

				if ($waiting && $can_reject_claims && empty($claim['approved']) && empty($claim['rejected'])) {

					echo mp_settings_claim_reject_form($selected_id, $claim, $claim_reasons);

				}

				echo '
			</div>';

			}

			echo '
			</div>';

			if ($claims_read['more']) {

				echo '
			<p class="small opacity-75 mt-5 mb-0 px-3">'
				. lang('Only the first 200 returns are shown. The rest are in your seller panel.') . '</p>';

			}

		}

		echo '
		</div>
	</div>';

	}

	/* ---------------------------------------------------------- questions */

	// The buyers' questions, read on a button and kept in the session (see
	// the questions_read action). A waiting question has its answer form.
	if ($can_questions) {

		$questions_read = mp_settings_marketplace_read($selected_id, 'questions');

		echo '
	<div class="card mb-5" id="mp-questions">
		<div class="card-header d-flex align-items-center flex-wrap gap-3">
			<i class="bi bi-chat-left-text me-1"></i><b>' . h(lang(array(
				'string' => 'Questions from {var:1}',
				'vars'   => array(mp_provider_name($selected['provider']))
			))) . '</b>
			<span class="small opacity-75 ms-auto">'
				. ($questions_read['read']
					? h(lang(array('string' => 'Read {var:1}', 'vars' => array(mp_settings_ago($questions_read['read'])))))
					: lang('Not read yet')) . '</span>
			<form method="post" action="marketplace_settings.php" class="d-inline"
				onsubmit="this.querySelector(\'button[type=submit]\').disabled = true;">
				' . get_token_field() . '
				<input type="hidden" name="mp_action" value="questions_read">
				<input type="hidden" name="account_id" value="' . $selected_id . '">
				<button type="submit" class="btn btn-sm btn-outline theme-secondary">
					<i class="bi bi-arrow-clockwise me-1"></i>' . lang('Read questions') . '</button>
			</form>
		</div>
		<div class="card-body px-3 py-5">';

		if (!$questions_read['read']) {

			echo '
			<p class="small opacity-75 text-center my-7 mb-0">'
			. lang('Questions are read from the marketplace when you ask: the last two weeks, every status.') . '</p>';

		} elseif (!$questions_read['questions']) {

			echo '
			<p class="small opacity-75 text-center my-7 mb-0">'
			. lang('No question in the last two weeks.') . '</p>';

		} else {

			foreach ($questions_read['questions'] as $question) {

				$question_id = h((string)$question['id']);

				echo '
			<div class="mp-question">
				<div class="d-flex flex-wrap gap-3 align-items-center small">
					' . (((string)$question['product_url'] !== '')
						? '<a href="' . h((string)$question['product_url']) . '" target="_blank" rel="noopener noreferrer">'
							. h((string)$question['product']) . '</a>'
						: h((string)$question['product'])) . '
					' . mp_settings_question_status($question) . '
					<span class="opacity-75 ms-auto">'
						. (((string)$question['user'] !== '') ? h((string)$question['user']) . ' &middot; ' : '')
						. ($question['asked'] ? h(mp_settings_ago((int)$question['asked'])) : '') . '</span>
				</div>
				<div class="my-1" style="white-space:pre-wrap">' . h((string)$question['text']) . '</div>'
				. (((string)$question['answer'] !== '')
					? '
				<div class="small border-start ps-3 opacity-75" style="white-space:pre-wrap">' . h((string)$question['answer']) . '</div>'
					: '')
				. (((string)$question['reason'] !== '')
					? '
				<div class="mp-err">' . h((string)$question['reason']) . '</div>'
					: '');

				if (!empty($question['waiting']) && empty($question['sent']) && $can_answer_questions) {

					echo '
				<form method="post" action="marketplace_settings.php" class="mt-3"
					onsubmit="this.querySelector(\'button[type=submit]\').disabled = true;">
					' . get_token_field() . '
					<input type="hidden" name="mp_action" value="question_answer">
					<input type="hidden" name="account_id" value="' . $selected_id . '">
					<input type="hidden" name="question_id" value="' . $question_id . '">
					<label class="visually-hidden" for="mp_answer_' . $question_id . '">' . lang('Answer') . '</label>
					<textarea class="form-control form-control-sm" id="mp_answer_' . $question_id . '" name="answer"
						rows="2" minlength="10" maxlength="2000" required></textarea>
					<div class="d-flex align-items-center gap-3 mt-1">
						<span class="form-text m-0">' . lang('10 to 2000 characters. The marketplace reviews the answer before the buyer sees it.') . '</span>
						<button type="submit" class="btn btn-sm btn-outline theme-success ms-auto">' . lang('Answer') . '</button>
					</div>
				</form>';

				}

				echo '
			</div>';

			}

			if ($questions_read['more']) {

				echo '
			<p class="small opacity-75 mt-5 mb-0 px-3">'
				. lang('Only the newest 200 questions are shown. The rest are in your seller panel.') . '</p>';

			}

		}

		echo '
		</div>
	</div>';

	}

	/* -------------------------------------------------------------- queue */

	echo '
	<div class="card" id="mp-queue">
		<div class="card-header d-flex align-items-center flex-wrap gap-3">
			<i class="bi bi-hourglass-split me-1"></i><b>' . lang('What did not go through') . '</b>
			<div class="ms-auto d-flex flex-wrap justify-content-end gap-3">';

	if ($failures) {

		echo '
				<form method="post" action="marketplace_settings.php" class="d-inline">
					' . get_token_field() . '
					<input type="hidden" name="mp_action" value="retry">
					<input type="hidden" name="account_id" value="' . $selected_id . '">
					<button type="submit" class="btn btn-sm btn-outline theme-secondary">
						<i class="bi bi-arrow-clockwise me-1"></i>' . lang('Try again') . '</button>
				</form>
				<form method="post" action="marketplace_settings.php" class="d-inline disable_shortcut">
					' . get_token_field() . '
					<input type="hidden" name="mp_action" value="clear_queue">
					<input type="hidden" name="account_id" value="' . $selected_id . '">
					<button type="submit" id="mp_clear_failed" class="btn btn-sm btn-outline theme-secondary" data-confirm-content="' . h(lang(array('string' => 'Delete the failed updates of {var:1}? The stock and price changes in them are not sent again.', 'vars' => $selected['name']))) . '">
						<i class="bi bi-trash me-1"></i>' . lang('Clear') . '</button>
				</form>';

	}

	echo '
			</div>
		</div>
		<div class="card-body px-5 py-5">';

	if (!$failures) {

		echo '
			<p class="small opacity-75 text-center my-5 mb-0">'
			. lang('Nothing has failed. Sent items disappear from here once the marketplace confirms them.') . '</p>';

	} else {

		foreach ($failures as $row) {

			echo '
			<div class="mp-fail d-flex flex-wrap gap-5 py-3 border-top align-items-start">
				<div class="mp-fail-name"><b class="small">' . h((string)$row['product_name']) . '</b>'
					. (((string)$row['operation'] === 'listing_update')
						? '<div class="small opacity-75">' . lang('Listing changes') . '</div>' : '') . '</div>
				<div class="mp-err">' . h((string)$row['last_error']) . '</div>
				<div class="mp-fail-time small text-nowrap">' . h(mp_settings_ago((int)$row['updated_timestamp'])) . '</div>
			</div>';

		}

	}

	echo '
		</div>
	</div>';

}

/* ------------------------------------------------------------------ modals */

$provider_options = '';

foreach ($providers as $key => $meta) {

	$provider_options .= '<option value="' . h($key) . '">' . h($meta['name']) . '</option>';

}

$first_provider = key($providers);

// Every marketplace's fields are drawn, one block each; the script below shows
// the chosen one's. The others are disabled as well as hidden so that only the
// chosen marketplace's credentials are posted.
$new_fields = '';

foreach ($providers as $key => $meta) {

	$is_first = ($key === $first_provider);

	$new_fields .= '
				<div data-mp-fields="' . h($key) . '"' . ($is_first ? '' : ' class="d-none"') . '>';

	foreach ((isset($meta['fields']) ? $meta['fields'] : array()) as $field => $field_meta) {

		$new_fields .= mp_settings_credential_field('new_' . $key, $field, $field_meta, '', false, !$is_first);

	}

	if (!empty($meta['help'])) {

		$new_fields .= '
					<div class="form-text pg-mt-n2 mb-5">' . h(lang($meta['help'])) . '</div>';

	}

	$new_fields .= '
				</div>';

}

echo '
<dialog class="dialog" id="mp_new" aria-labelledby="mp_new_title">
		<form method="post" action="marketplace_settings.php" autocomplete="off" class="d-contents disable_shortcut">
			' . get_token_field() . '
			<input type="hidden" name="mp_action" value="create">
			<div class="dialog-header">
				<h5 class="dialog-title" id="mp_new_title"><i class="bi bi-shop me-3"></i>' . lang('Connect a Marketplace') . '</h5>
				<button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="' . h(lang('Close')) . '"></button>
			</div>
			<div class="dialog-body">
				<div class="mb-5">
					<label class="form-label" for="new_provider">' . lang('Marketplace') . '</label>
					<select class="form-control" id="new_provider" name="provider">' . $provider_options . '</select>
				</div>
				<div class="mb-5">
					<label class="form-label" for="new_name">' . lang('Name') . '</label>
					<input type="text" class="form-control" id="new_name" name="name"
						placeholder="' . lang('The name you will recognise this store by') . '">
				</div>
				' . $new_fields . '
				<div class="form-field">
					<div class="switch"><input class="" type="checkbox" role="switch" id="new_push_price" name="push_price" value="1" checked></div>
					<label class="" for="new_push_price">' . lang('Send prices as well as stock') . '</label>
					<div class="form-text">'
						. lang('Many shops price differently on a marketplace because of its commission. Leave this off to send only quantities.') . '</div>
				</div>
			</div>
			<div class="dialog-footer">
				<button type="button" class="btn btn-outline theme-secondary" data-bs-dismiss="dialog">' . lang('Cancel') . '</button>
				<button type="submit" class="btn btn-solid theme-primary">' . lang('Connect') . '</button>
			</div>
		</form>
</dialog>';

if ($selected) {

	$edit_fields = '';

	foreach ($provider_fields as $field => $meta) {

		$current = isset($selected['credentials'][$field]) ? (string)$selected['credentials'][$field] : '';

		$edit_fields .= mp_settings_credential_field('edit', $field, $meta, $current, true);

	}

	if (!empty($providers[$selected['provider']]['help'])) {

		$edit_fields .= '
			<div class="form-text pg-mt-n2 mb-5">' . h(lang($providers[$selected['provider']]['help'])) . '</div>';

	}

	echo '
<dialog class="drawer drawer-end" id="mp_drawer" aria-labelledby="mp_drawer_title" style="width:min(480px,100%)">
	<form method="post" action="marketplace_settings.php" autocomplete="off" class="disable_shortcut d-flex flex-column h-100">
		<!-- Return in a text field presses the first submit button of the form.
		     This one stands first and has no name, so Return posts the hidden
		     mp_action=update and saves instead of pressing Remove marketplace. -->
		<button type="submit" class="visually-hidden" tabindex="-1" aria-hidden="true"></button>
		' . get_token_field() . '
		<input type="hidden" name="mp_action" value="update">
		<input type="hidden" name="account_id" value="' . $selected_id . '">
		<div class="drawer-header border-bottom">
			<h5 class="drawer-title" id="mp_drawer_title"><i class="bi bi-sliders me-3"></i>' . h($selected['name']) . '</h5>
			<button type="button" class="btn-close" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '"></button>
		</div>
		<div class="drawer-body">
			<div class="mb-5">
				<label class="form-label" for="edit_name">' . lang('Name') . '</label>
				<input type="text" class="form-control" id="edit_name" name="name" value="' . h($selected['name']) . '">
			</div>
			' . $edit_fields . '
			<div class="form-field mb-3">
				<div class="switch"><input class="" type="checkbox" role="switch" id="edit_status" name="status" value="active"'
					. ($selected['status'] === 'active' ? ' checked' : '') . '></div>
				<label class="" for="edit_status">' . lang('Send changes to this marketplace') . '</label>
			</div>
			<div class="form-field mb-3">
				<div class="switch"><input class="" type="checkbox" role="switch" id="edit_push_stock" name="push_stock" value="1"'
					. (!empty($selected['push_stock']) ? ' checked' : '') . '></div>
				<label class="" for="edit_push_stock">' . lang('Send stock') . '</label>
			</div>
			<div class="form-field mb-7">
				<div class="switch"><input class="" type="checkbox" role="switch" id="edit_push_price" name="push_price" value="1"'
					. (!empty($selected['push_price']) ? ' checked' : '') . '></div>
				<label class="" for="edit_push_price">' . lang('Send prices') . '</label>
			</div>
			<hr>'
			. ($can_orders ? '
			<div class="form-field ' . ($can_approve ? 'mb-3' : 'mb-7') . '">
				<div class="switch"><input class="" type="checkbox" role="switch" id="edit_pull_orders" name="pull_orders" value="1"'
					. (!empty($selected['pull_orders']) ? ' checked' : '') . '></div>
				<label class="" for="edit_pull_orders">' . lang('Bring orders in from this marketplace') . '</label>
			</div>'
			. ($can_approve ? '
			<div class="form-field mb-7">
				<div class="switch"><input class="" type="checkbox" role="switch" id="edit_auto_approve" name="auto_approve" value="1"'
					. (!empty($selected['auto_approve']) ? ' checked' : '') . '></div>
				<label class="" for="edit_auto_approve">' . lang('Accept new orders automatically') . '</label>
				<div class="form-text">'
					. lang('Accepting an order tells the marketplace you are preparing it, and their dispatch clock starts. Leave this off to accept them yourself.') . '</div>
			</div>' : '') : '
			<p class="small opacity-75 mb-7">' . h(lang(array(
				'string' => 'Orders are not brought in from {var:1} yet.',
				'vars'   => array(mp_provider_name($selected['provider']))
			))) . '</p>') . '
			<hr>'
			. (!empty($providers[$selected['provider']]['shipment_template']) ? '
			<div class="mb-5">
				<label class="form-label" for="edit_shipment_template">' . lang('Cargo template') . '</label>
				<input type="text" class="form-control" id="edit_shipment_template" name="shipment_template"
					value="' . h((string)$selected['shipment_template']) . '">
				<div class="form-text">'
					. lang('The name of the cargo agreement in your marketplace store settings, copied exactly. A new listing is refused without it.') . '</div>
			</div>' : '') . '
			<div class="mb-7">
				<label class="form-label" for="edit_preparing_day">' . lang('Days to prepare') . '</label>
				<input type="number" class="form-control" id="edit_preparing_day" name="preparing_day" min="1" max="30"
					value="' . (int)$selected['preparing_day'] . '" style="max-width:110px">
				<div class="form-text">' . lang('How long you take to hand a parcel over. It is shown to the buyer.') . '</div>
			</div>
			<hr>
			<p class="small opacity-75">'
				. (!empty($selected['credentials']['webhook_id'])
					? h(lang(array(
						'string' => 'Removing a marketplace deletes its product matches and its queue. Your products are not touched. Its webhook is removed from {var:1} first; nothing else is removed from the marketplace itself.',
						'vars'   => array(mp_provider_name($selected['provider']))
					)))
					: lang('Removing a marketplace deletes its product matches and its queue. Your products are not touched, and nothing is removed from the marketplace itself.')) . '</p>
			<button type="submit" class="btn btn-outline theme-danger btn-sm" name="mp_action" value="delete"
				formnovalidate onclick="return confirm(' . h(json_encode(lang('Remove this marketplace?'))) . ')">
				<i class="bi bi-trash me-1"></i>' . lang('Remove marketplace') . '</button>'
			. (!empty($selected['credentials']['webhook_id']) ? '
			<div class="form-field small mt-3">
				<input class="check" type="checkbox" id="edit_webhook_force" name="webhook_force" value="1">
				<label class="" for="edit_webhook_force">' . h(lang(array(
					'string' => 'Remove the marketplace even if its webhook cannot be removed from {var:1} (then remove the webhook in the seller panel)',
					'vars'   => array(mp_provider_name($selected['provider']))
				))) . '</label>
			</div>' : '') . '
		</div>
		<div class="drawer-footer border-top p-5 d-flex gap-3">
			<button type="button" class="btn btn-outline theme-secondary flex-grow-1" data-bs-dismiss="drawer">' . lang('Cancel') . '</button>
			<button type="submit" class="btn btn-solid theme-primary flex-grow-1">' . lang('Save') . '</button>
		</div>
	</form>
</dialog>';

}

echo '
<script>
(function () {

	// The new-account form shows the chosen marketplace\'s fields only. The
	// others are disabled too, so they are not posted.
	var provider = document.getElementById(\'new_provider\');

	if (provider) {

		var showFields = function () {

			var blocks = document.querySelectorAll(\'[data-mp-fields]\');

			for (var i = 0; i < blocks.length; i++) {

				var chosen = (blocks[i].getAttribute(\'data-mp-fields\') === provider.value);

				blocks[i].classList.toggle(\'d-none\', !chosen);

				var inputs = blocks[i].querySelectorAll(\'input, select\');

				for (var j = 0; j < inputs.length; j++) {

					inputs[j].disabled = !chosen;

				}

			}

		};

		provider.addEventListener(\'change\', showFields);

		showFields();

	}

})();
</script>' . output_footer();
