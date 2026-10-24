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
//     product meets the code the marketplace knows it by.
//   Did it land - the queue, which is the only honest answer, because these
//     marketplaces accept a batch and refuse it line by line minutes later.

include('init.php');
include_once('liveform.class.php');

$user = validate_user();
validate_area_access($user, 'manager');
validate_ecommerce_access($user);

require_once(dirname(__FILE__) . '/includes/api/outbound/connectors/base.php');
require_once(dirname(__FILE__) . '/includes/api/outbound/listings.php');
require_once(dirname(__FILE__) . '/includes/api/outbound/webhook_receiver.php');

$liveform = new liveform('marketplace_settings');

$screen_url = URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/marketplace_settings.php';

/* ---------------------------------------------------------------------------
   Writes
   --------------------------------------------------------------------------- */

if ($_POST) {

	validate_token_field();

	// Every field here holds one value, except the category attributes
	// (attribute[id]). One posted as a list (provider[]=x) stopped the request
	// in trim(), and account_id[]=x or group_id[]=x was read as record 1.
	// Nothing is written, so every account and match stays as it was.
	$mp_settings_lists = pg_request_unexpected_lists($_POST, array('attribute' => 1));

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

	if ($action === 'categories' && $account) {

		$result = mp_categories_refresh($account);

		if ($result['ok']) {

			$liveform->add_notice(lang(array(
				'string' => '{var:1} categories are up to date.',
				'vars'   => array(pg_format_number((int)$result['data']['count'], 0))
			)));

		} else {

			$liveform->add_warning(h($result['error']));

		}

		header('Location: ' . $screen_url . '?account=' . $account_id . '#mp-list');

		exit();

	}

	if ($action === 'category' && $account) {

		$group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;

		$category_id = isset($_POST['remote_category_id']) ? trim($_POST['remote_category_id']) : '';

		// Only a leaf can hold a product. A branch is a shelf on their side too,
		// and a listing filed against one is refused - by them, hours later.
		$leaf = $category_id !== '' ? db_item("SELECT remote_id, path, is_leaf FROM marketplace_categories
			WHERE (provider = '" . e($account['provider']) . "') AND (remote_id = '" . e($category_id) . "')") : null;

		if (!$group_id || !$leaf) {

			$liveform->add_error(lang('Choose a category from the list.'));

		} elseif (empty($leaf['is_leaf'])) {

			$liveform->add_error(lang('That is a parent category. Pick one of the categories inside it.'));

		} else {

			$existing = db_value("SELECT id FROM marketplace_category_map
				WHERE (account_id = '" . e($account_id) . "') AND (group_id = '" . e($group_id) . "')");

			if ($existing) {

				// A different category means different attributes, so what was
				// answered for the old one is thrown away rather than carried
				// across under ids that mean something else there - the
				// marketplace would refuse every variant, and its reason would
				// name an attribute nobody recognised.
				//
				// attributes_json is assigned BEFORE remote_category_id. MySQL
				// evaluates a SET list left to right and a later assignment sees
				// the new value of an earlier one, so testing the category
				// after setting it compares the new value with itself and the
				// answers are always kept.
				db("UPDATE marketplace_category_map
					SET attributes_json = IF(remote_category_id = '" . e($category_id) . "', attributes_json, ''),
						remote_category_id = '" . e($category_id) . "',
						last_error = '', updated_timestamp = UNIX_TIMESTAMP()
					WHERE id = '" . e((int)$existing) . "'");

			} else {

				db("INSERT INTO marketplace_category_map
					(account_id, group_id, remote_category_id, attributes_json, updated_timestamp)
					VALUES ('" . e($account_id) . "', '" . e($group_id) . "', '" . e($category_id) . "', '',
						UNIX_TIMESTAMP())");

			}

			// Fetched now, while somebody is waiting and can be told it failed.
			$loaded = mp_category_attributes($account, $category_id);

			if (!$loaded['ok']) {

				$liveform->add_warning(h($loaded['error']));

			}

		}

		header('Location: ' . $screen_url . '?account=' . $account_id . '&group=' . $group_id . '#mp-list');

		exit();

	}

	if ($action === 'attributes' && $account) {

		$group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;

		$map = $group_id ? db_item("SELECT * FROM marketplace_category_map
			WHERE (account_id = '" . e($account_id) . "') AND (group_id = '" . e($group_id) . "')") : null;

		if ($map) {

			$values = array();

			foreach ((array)(isset($_POST['attribute']) ? $_POST['attribute'] : array()) as $attribute_id => $value) {

				$value = trim((string)$value);

				if ($value !== '') {

					$values[(string)$attribute_id] = $value;

				}

			}

			db("UPDATE marketplace_category_map
				SET attributes_json = '" . e(json_encode($values)) . "', last_error = '',
					updated_timestamp = UNIX_TIMESTAMP()
				WHERE id = '" . e((int)$map['id']) . "'");

			$liveform->add_notice(lang('Saved.'));

		}

		header('Location: ' . $screen_url . '?account=' . $account_id . '&group=' . $group_id . '#mp-list');

		exit();

	}

	if ($action === 'list_now' && $account) {

		$group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;

		$ready = mp_can($account['provider'], 'create_listings')
			? mp_listing_items($account, $group_id)
			: array('ok' => false, 'error' => lang(array(
				'string' => '{var:1} cannot do this.',
				'vars'   => array(mp_provider_name($account['provider']))
			)));

		if (!$ready['ok']) {

			$liveform->add_warning(h($ready['error']));

		} else {

			$queued = mp_listing_enqueue($account_id, $group_id);

			$liveform->add_notice($queued
				? lang(array('string' => '{var:1} product(s) queued for listing. They go out on the next run.',
					'vars' => array($queued)))
				: lang('Already queued.'));

		}

		header('Location: ' . $screen_url . '?account=' . $account_id . '&group=' . $group_id . '#mp-list');

		exit();

	}

	if ($action === 'map' && $account) {

		$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

		$remote_code = isset($_POST['remote_code']) ? trim($_POST['remote_code']) : '';

		$product = $product_id ? db_item("SELECT id, name FROM products WHERE id = '" . e($product_id) . "'") : null;

		// The picker resolves the typed label to an id in the browser. What was
		// typed is submitted as well, and is what decides here when it did not -
		// a datalist that was filled by pasting, or a browser that does not do
		// datalists at all, otherwise silently submits nothing.
		if (!$product) {

			$typed = isset($_POST['product_code']) ? trim($_POST['product_code']) : '';

			// The option is "CODE - Title"; the code is what identifies it.
			$typed = trim(explode(' — ', $typed)[0]);

			if ($typed !== '') {

				$product = db_item("SELECT id, name FROM products WHERE name = '" . e($typed) . "' LIMIT 1");

				$product_id = $product ? (int)$product['id'] : 0;

			}

		}

		if (!$product) {

			$liveform->add_error(lang('That product was not found. Choose one from the list.'));

		}

		if ($liveform->check_form_errors() == false) {

			// Blank means the product's own code. Most shops list under the same
			// code on both sides, and making the operator retype it is how a
			// typo gets into the mapping. On a marketplace keyed on barcodes
			// the product's own code is its barcode, not its name.
			if ($remote_code === '') {

				$remote_code = mp_provider_keys_by_barcode($account['provider'])
					? mp_product_first_barcode($product_id)
					: $product['name'];

			}

			if ($remote_code === '') {

				$liveform->add_error(lang(array(
					'string' => 'This product has no barcode. Type the barcode it is listed under on {var:1}.',
					'vars'   => array(mp_provider_name($account['provider']))
				)));

				header('Location: ' . $screen_url . '?account=' . $account_id . '#mp-map');

				exit();

			}

			$existing = db_value("SELECT id FROM marketplace_product_map
				WHERE (account_id = '" . e($account_id) . "') AND (product_id = '" . e($product_id) . "')");

			if ($existing) {

				db("UPDATE marketplace_product_map
					SET remote_code = '" . e($remote_code) . "', status = 'active', last_error = ''
					WHERE id = '" . e((int)$existing) . "'");

			} else {

				db("INSERT INTO marketplace_product_map
					(account_id, product_id, remote_code, status, created_timestamp)
					VALUES ('" . e($account_id) . "', '" . e($product_id) . "',
						'" . e($remote_code) . "', 'active', UNIX_TIMESTAMP())");

			}

			$liveform->add_notice(lang('Product matched.'));

			header('Location: ' . $screen_url . '?account=' . $account_id . '#mp-map');

			exit();

		}

	}

	if ($action === 'unmap' && $account) {

		$map_id = isset($_POST['map_id']) ? (int)$_POST['map_id'] : 0;

		db("DELETE FROM marketplace_product_map
			WHERE (id = '" . e($map_id) . "') AND (account_id = '" . e($account_id) . "')");

		$liveform->add_notice(lang('Match removed.'));

		header('Location: ' . $screen_url . '?account=' . $account_id . '#mp-map');

			exit();

	}

	if ($action === 'push_all' && $account) {

		$queued = mp_enqueue_account($account_id);

		$liveform->add_notice($queued
			? lang(array('string' => '{var:1} product(s) queued. They go out on the next run.', 'vars' => array($queued)))
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

	// The webhook: the marketplace posts each order package to this site as it
	// changes, instead of waiting for the next pull. The key is made here, kept
	// in the account's encrypted credentials and never shown; the marketplace
	// sends it back with every delivery (includes/api/outbound/webhook_receiver.php).
	if ($action === 'webhook_register' && $account) {

		$provider_name = mp_provider_name($account['provider']);

		$problem = mp_can($account['provider'], 'register_webhook')
			? mp_webhook_register_problem($account)
			: lang(array('string' => '{var:1} cannot do this.', 'vars' => array($provider_name)));

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

		}

		header('Location: ' . $screen_url . '?account=' . $account_id . '#mp-webhook');

		exit();

	}

	if ($action === 'webhook_remove' && $account) {

		if (!mp_can($account['provider'], 'remove_webhook')) {

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

		header('Location: ' . $screen_url . '?account=' . $account_id . '#mp-webhook');

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

// The two-step mapping for one article, drawn inline under its row.
//
// Two steps rather than one, and no scripting: the attributes cannot be drawn
// until the category is known, because they ARE the category. A single form
// would have to fetch them in the browser, and this screen has no endpoint to
// fetch them from - so the first submit chooses the category and the page comes
// back with the second half.
function mp_settings_article_form($account, $account_id, $article, $map, $attributes, $chosen) {

	$group_id = (int) $article['id'];

	$chosen_category = $map ? (string)$map['remote_category_id'] : '';

	$out = '
			<div class="mp-row" style="display:block;background:var(--bs-tertiary-bg)">
				<form method="post" action="marketplace_settings.php" class="mb-3">
					' . get_token_field() . '
					<input type="hidden" name="mp_action" value="category">
					<input type="hidden" name="account_id" value="' . (int)$account_id . '">
					<input type="hidden" name="group_id" value="' . $group_id . '">
					<label class="form-label small" for="cat_' . $group_id . '">'
						. lang('Marketplace category') . '</label>
					<div class="d-flex gap-2 flex-wrap">
						<input class="form-control form-control-sm" list="mp_categories" id="cat_' . $group_id . '"
							name="remote_category_id" style="max-width:520px" autocomplete="off"
							placeholder="' . lang('Type part of the category name') . '"
							value="' . h($chosen_category) . '">
						<button type="submit" class="btn btn-sm btn-primary">' . lang('Choose') . '</button>
					</div>
					<div class="form-text">'
						. lang('Only the deepest categories can hold a product. Start typing and pick one from the list.') . '</div>
				</form>';

	if ($chosen_category === '') {

		return $out . '
			</div>';

	}

	if (!$attributes) {

		return $out . '
				<p class="small opacity-75 mb-0">'
			. lang('This category asks for nothing else. The article is ready to list.') . '</p>
			</div>';

	}

	$out .= '
				<form method="post" action="marketplace_settings.php">
					' . get_token_field() . '
					<input type="hidden" name="mp_action" value="attributes">
					<input type="hidden" name="account_id" value="' . (int)$account_id . '">
					<input type="hidden" name="group_id" value="' . $group_id . '">
					<div class="row g-2">';

	foreach ($attributes as $attribute) {

		$id = (string) $attribute['attribute_id'];

		$value = isset($chosen[$id]) ? (string)$chosen[$id] : '';

		// A variant attribute is answered by the products themselves - it is
		// what tells them apart - so it is never required here. A value chosen
		// for the article is used only by a product whose options do not match
		// it (no option of that name, no label on the category's list): an
		// article in one colour, in a category that splits its pages by colour.
		// Two products that would end up the same refuse the article
		// (mp_listing_variant_clash()).
		$variant = !empty($attribute['is_variant']);

		$required = !empty($attribute['mandatory']) && !$variant;

		$out .= '
						<div class="col-12 col-md-6 col-xl-4">
							<label class="form-label small mb-1" for="attr_' . $group_id . '_' . h($id) . '">'
								. h((string)$attribute['name'])
								. ($required ? ' <span class="text-danger">*</span>' : '')
								. ($variant ? ' <span class="mp-pill off">' . lang('from the variants') . '</span>' : '')
								. '</label>';

		if (!empty($attribute['values'])) {

			$out .= '
							<select class="form-select form-select-sm" id="attr_' . $group_id . '_' . h($id) . '"
								name="attribute[' . h($id) . ']">
								<option value="">' . ($required ? lang('Choose') : lang('Leave empty')) . '</option>';

			foreach ((array)$attribute['values'] as $option) {

				$out .= '<option value="' . h((string)$option['id']) . '"'
					. (((string)$option['id'] === $value) ? ' selected' : '') . '>'
					. h((string)$option['value']) . '</option>';

			}

			$out .= '
							</select>';

		} else {

			$out .= '
							<input type="text" class="form-control form-control-sm"
								id="attr_' . $group_id . '_' . h($id) . '"
								name="attribute[' . h($id) . ']" value="' . h($value) . '" autocomplete="off">';

		}

		if ($variant) {

			$out .= '
							<div class="form-text">'
								. lang('Taken from each product\'s own option. This value is used only for a product whose options do not match this attribute; two products that would end up the same refuse the article.') . '</div>';

		}

		$out .= '
						</div>';

	}

	$out .= '
					</div>
					<button type="submit" class="btn btn-sm btn-primary mt-3">' . lang('Save') . '</button>
				</form>
			</div>';

	return $out;

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

	if ($seconds < 3600) { return lang(array('string' => '{var:1} minutes ago', 'vars' => (int)($seconds / 60))); }

	if ($seconds < 86400) { return lang(array('string' => '{var:1} hours ago', 'vars' => (int)($seconds / 3600))); }

	return lang(array('string' => '{var:1} days ago', 'vars' => (int)($seconds / 86400)));

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
			<div class="mb-3">
				<label class="form-label" for="' . h($id) . '">' . h(lang($meta['label'])) . '</label>';

	if (mp_provider_field_is_select($meta)) {

		$selected = ($current !== '' && isset($meta['options'][$current]))
			? $current
			: (isset($meta['default']) ? (string)$meta['default'] : '');

		$out .= '
				<select class="form-select" id="' . h($id) . '" name="' . h($field) . '"' . ($disabled ? ' disabled' : '') . '>';

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
					id="' . h($id) . '" name="' . h($field) . '" autocomplete="off"'
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

$maps = array();

$orders = array();

$failed_orders = 0;

$articles = array();

$category_count = 0;

$open_group = 0;

$open_map = null;

$open_attributes = array();

$open_chosen = array();

$queue_counts = array('waiting' => 0, 'sent' => 0, 'done' => 0, 'failed' => 0);

$failures = array();

if ($selected) {

	// One query for the mapping and the product beside it. The screen shows the
	// price and quantity next to the remote code, because the question an
	// operator opens this screen with is "what is it going to send": the price
	// is the one the queue sends, tax included (mp_product_price()), which is
	// why the product's taxable flag and rate ride along.
	$maps = (array) db_items("SELECT marketplace_product_map.*,
			products.name AS product_name, products.title, products.price,
			products.taxable, products.tax_rate,
			products.inventory, products.inventory_quantity, products.out_of_stock, products.enabled
		FROM marketplace_product_map
		LEFT JOIN products ON products.id = marketplace_product_map.product_id
		WHERE marketplace_product_map.account_id = '" . e($selected_id) . "'
		ORDER BY products.name ASC
		LIMIT 500");

	foreach ((array) db_items("SELECT status, COUNT(*) AS n FROM marketplace_sync_queue
		WHERE account_id = '" . e($selected_id) . "' GROUP BY status") as $row) {

		$queue_counts[$row['status']] = (int) $row['n'];

	}

	$articles = mp_listing_articles($selected_id);

	$category_count = (int) db_value("SELECT COUNT(*) FROM marketplace_categories
		WHERE (provider = '" . e($selected['provider']) . "') AND (is_leaf = '1')");

	// The article whose mapping is open, if the operator picked one.
	$open_group = isset($_GET['group']) ? (int)$_GET['group'] : 0;

	$open_map = $open_group ? db_item("SELECT * FROM marketplace_category_map
		WHERE (account_id = '" . e($selected_id) . "') AND (group_id = '" . e($open_group) . "')") : null;

	$open_attributes = array();

	$open_chosen = array();

	if ($open_map && $open_map['remote_category_id'] !== '') {

		$loaded = mp_category_attributes($selected, $open_map['remote_category_id']);

		$open_attributes = $loaded['ok'] ? (array)$loaded['data']['attributes'] : array();

		$decoded = json_decode((string)$open_map['attributes_json'], true);

		$open_chosen = is_array($decoded) ? $decoded : array();

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

<style>
/* Same reasoning as the application list: six columns of real content do not
   fit a phone, so the block scrolls sideways rather than stacking every row
   into a paragraph nobody can scan. */
.mp-scroll { overflow-x: auto; overflow-y: hidden; }
.mp-head, .mp-row { display: grid; grid-template-columns: 2fr 1.4fr 1fr .9fr .9fr 40px;
	gap: 12px; align-items: center; min-width: 760px; }
.mp-head { padding: 0 14px 8px; font-size: 10.5px; letter-spacing: .06em; text-transform: uppercase; opacity: .55; }
.mp-row { padding: 11px 14px; border-top: 1px solid var(--bs-border-color); }
.mp-row b { font-size: 13px; display: block; }
.mp-row code { font-size: 11px; opacity: .7; }
.mp-pill { font-size: 10.5px; padding: 3px 9px; border-radius: 20px; font-weight: 600;
	border: 1px solid var(--bs-border-color); display: inline-block; white-space: nowrap; }
.mp-pill.on { color: var(--bs-success); border-color: rgba(var(--bs-success-rgb), .35); }
.mp-pill.off { opacity: .6; }
.mp-pill.bad { color: var(--bs-danger); border-color: rgba(var(--bs-danger-rgb), .35); }
.mp-tab { border: 1px solid var(--bs-border-color); border-radius: 8px; padding: 10px 14px;
	text-decoration: none; color: inherit; display: block; }
.mp-tab.sel { border-color: var(--bs-primary); background: var(--bs-tertiary-bg); }
.mp-tab small { opacity: .6; }
.mp-count { display: flex; gap: 18px; flex-wrap: wrap; }
.mp-count div { min-width: 82px; }
.mp-count b { font-size: 20px; display: block; line-height: 1.1; }
.mp-count span { font-size: 11px; opacity: .6; }
.mp-err { font-size: 11.5px; color: var(--bs-danger); word-break: break-word; }
</style>

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
		<div class="card-body text-center py-5">
			<i class="bi bi-shop" style="font-size:34px;opacity:.35"></i>
			<p class="mt-3 mb-1"><b>' . lang('No marketplace is connected yet.') . '</b></p>
			<p class="small opacity-75 mb-4" style="max-width:520px;margin:0 auto">'
				. lang('Connect a marketplace account and match your products to the codes it knows them by. Stock and price changes here are then sent over there on a schedule.') . '</p>
			<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#mp_new">
				<i class="bi bi-plus-lg me-1"></i>' . lang('Connect a Marketplace') . '</button>
		</div>
	</div>';

} else {

	echo '
	<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
		<span class="small opacity-75">
			<i class="bi bi-clock-history me-1"></i>'
			. ($last_run
				? lang(array('string' => 'Last run {var:1}', 'vars' => array(mp_settings_ago($last_run))))
				: lang('The scheduled job has not run yet.')) . '
		</span>
		<div class="flex-grow-1"></div>
		<button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#mp_new">
			<i class="bi bi-plus-lg me-1"></i>' . lang('Connect a Marketplace') . '</button>
	</div>

	<div class="row g-3 mb-3">';

	foreach ($accounts as $row) {

		$is_selected = ((int)$row['id'] === $selected_id);

		$state = ($row['status'] === 'active')
			? '<span class="mp-pill on">' . lang('Active') . '</span>'
			: '<span class="mp-pill off">' . lang('Paused') . '</span>';

		echo '
		<div class="col-12 col-md-6 col-xl-4">
			<a class="mp-tab' . ($is_selected ? ' sel' : '') . '" href="marketplace_settings.php?account=' . (int)$row['id'] . '">
				<div class="d-flex align-items-center gap-2">
					<i class="bi bi-shop"></i>
					<b>' . h($row['name']) . '</b>
					<span class="ms-auto">' . $state . '</span>
				</div>
				<small>' . h(mp_provider_name($row['provider']))
					. ($row['last_error'] !== ''
						? ' &middot; <span class="text-danger">' . h(mb_substr($row['last_error'], 0, 60)) . '</span>'
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

	// A marketplace that cannot open listings yet still has its categories and
	// attributes mapped here; only the button that would queue the listing is
	// left out, since the job would never pick that row up.
	$can_list = mp_can($selected['provider'], 'create_listings');

	// Orders come in only from a marketplace whose connector reads them, and
	// are accepted only by one that can accept them. The job skips the rest
	// without a word, so their switches and the orders card are not drawn: a
	// switch shown on would promise orders that never come, while the stock
	// they sell there is still sold here.
	$can_orders = mp_can($selected['provider'], 'pull_orders');

	$can_approve = $can_orders && mp_can($selected['provider'], 'approve_lines');

	$provider_fields = isset($providers[$selected['provider']]['fields'])
		? $providers[$selected['provider']]['fields']
		: array();

	/* ------------------------------------------------------------ account */

	echo '
	<div class="card mb-3">
		<div class="card-header d-flex align-items-center flex-wrap gap-2">
			<i class="bi bi-sliders me-1"></i><b>' . h($selected['name']) . '</b>
			<span class="small opacity-75">' . h(mp_provider_name($selected['provider'])) . '</span>'
			. ((isset($selected['credentials']['environment']) && $selected['credentials']['environment'] === 'stage')
				? ' <span class="mp-pill off">' . lang('Test environment') . '</span>' : '') . '
			<div class="ms-auto d-flex gap-2">
				<form method="post" action="marketplace_settings.php" class="d-inline">
					' . get_token_field() . '
					<input type="hidden" name="mp_action" value="test">
					<input type="hidden" name="account_id" value="' . $selected_id . '">
					<button type="submit" class="btn btn-sm btn-outline-secondary">
						<i class="bi bi-plug me-1"></i>' . lang('Test connection') . '</button>
				</form>
				<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="offcanvas" data-bs-target="#mp_drawer">
					<i class="bi bi-pencil me-1"></i>' . lang('Settings') . '</button>
			</div>
		</div>
		<div class="card-body">
			<div class="mp-count">
				<div><b>' . pg_format_number(count($maps), 0) . '</b><span>' . lang('matched products') . '</span></div>
				<div><b>' . pg_format_number($queue_counts['waiting'], 0) . '</b><span>' . lang('waiting') . '</span></div>
				<div><b>' . pg_format_number($queue_counts['sent'], 0) . '</b><span>' . lang('with the marketplace') . '</span></div>
				<div><b class="' . ($queue_counts['failed'] ? 'text-danger' : '') . '">'
					. pg_format_number($queue_counts['failed'], 0) . '</b><span>' . lang('failed') . '</span></div>
			</div>';

	if ($selected['status'] !== 'active') {

		echo '
			<div class="alert alert-warning small mt-3 mb-0">
				<i class="bi bi-pause-circle me-1"></i>'
				. lang('This account is paused. Nothing is sent while it is, and changes made in the meantime are not queued.') . '
			</div>';

	}

	if ($selected['last_error'] !== '') {

		echo '
			<div class="alert alert-danger small mt-3 mb-0">
				<i class="bi bi-exclamation-triangle me-1"></i>' . h($selected['last_error']) . '
			</div>';

	}

	echo '
		</div>
	</div>';

	/* ------------------------------------------------------------ mapping */

	echo '
	<div class="card mb-3" id="mp-map">
		<div class="card-header d-flex align-items-center flex-wrap gap-2">
			<i class="bi bi-link-45deg me-1"></i><b>' . lang('Matched products') . '</b>
			<span class="small opacity-75 d-none d-lg-inline">'
				. lang('The code on the left is this shop\'s; the code on the right is what the marketplace calls it.') . '</span>
			<div class="ms-auto d-flex gap-2">
				<form method="post" action="marketplace_settings.php" class="d-inline">
					' . get_token_field() . '
					<input type="hidden" name="mp_action" value="push_all">
					<input type="hidden" name="account_id" value="' . $selected_id . '">
					<button type="submit" class="btn btn-sm btn-outline-secondary"' . ($maps ? '' : ' disabled') . '>
						<i class="bi bi-arrow-repeat me-1"></i>' . lang('Send all now') . '</button>
				</form>
				<button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#mp_match">
					<i class="bi bi-plus-lg me-1"></i>' . lang('Match a product') . '</button>
			</div>
		</div>
		<div class="card-body px-2 py-3">';

	if (!$maps) {

		echo '
			<p class="small opacity-75 text-center my-4 mb-0">'
			. lang('No product is matched yet. Nothing is sent until at least one is.') . '</p>';

	} else {

		echo '
			<div class="mp-scroll">
			<div class="mp-head">
				<span>' . lang('Product') . '</span><span>' . lang('Code on the marketplace') . '</span>
				<span>' . lang('Price') . '</span><span>' . lang('Stock') . '</span>
				<span>' . lang('Last sent') . '</span><span></span>
			</div>';

		// The price cell below is the site's own formatter and is deliberately
		// not escaped: it returns the currency symbol as an HTML entity -
		// Turkish lira is &#8378; - and escaping that prints the entity itself
		// instead of the sign. Everything else in the row is text somebody
		// typed and is escaped.
		foreach ($maps as $map) {

			$tracked = !empty($map['inventory']);

			$quantity = $tracked ? (int)$map['inventory_quantity'] : 0;

			if (empty($map['enabled']) || !empty($map['out_of_stock'])) {

				$quantity = 0;

			}

			// The figure the marketplace gets; marked when it carries tax the
			// product's stored price does not, so it is not read as a typo.
			$map_price = mp_product_price($map);

			echo '
			<div class="mp-row">
				<span>
					<b>' . h((string)$map['product_name']) . '</b>
					<code>' . h(mb_substr((string)$map['title'], 0, 46)) . '</code>
				</span>
				<span><code>' . h($map['remote_code']) . '</code>'
					. ($map['status'] === 'error'
						? '<div class="mp-err">' . h(mb_substr((string)$map['last_error'], 0, 90)) . '</div>'
						: '') . '</span>
				<span>' . prepare_price_for_output($map_price, false, 0, 'html', false)
					. ($map_price !== (int)$map['price'] ? ' <span class="small opacity-50">' . lang('VAT included') . '</span>' : '') . '</span>
				<span>' . pg_format_number($quantity, 0)
					. ($tracked ? '' : ' <span class="small opacity-50">' . lang('untracked') . '</span>') . '</span>
				<span class="small opacity-75">'
					. ($map['last_pushed'] ? h(mp_settings_ago((int)$map['last_pushed'])) : '&mdash;') . '</span>
				<span>
					<form method="post" action="marketplace_settings.php">
						' . get_token_field() . '
						<input type="hidden" name="mp_action" value="unmap">
						<input type="hidden" name="account_id" value="' . $selected_id . '">
						<input type="hidden" name="map_id" value="' . (int)$map['id'] . '">
						<button type="submit" class="btn btn-sm btn-link text-danger p-0" title="' . lang('Remove match') . '">
							<i class="bi bi-x-lg"></i></button>
					</form>
				</span>
			</div>';

		}

		echo '
			</div>';

	}

	echo '
		</div>
	</div>';

	/* ------------------------------------------------------------ listings */

	echo '
	<div class="card mb-3" id="mp-list">
		<div class="card-header d-flex align-items-center flex-wrap gap-2">
			<i class="bi bi-tags me-1"></i><b>' . lang('Listing articles') . '</b>
			<span class="small opacity-75 d-none d-lg-inline">'
				. lang('An article is listed once, with its variants as options. Pick the marketplace category and answer what it asks.') . '</span>
			<div class="ms-auto d-flex align-items-center gap-2">
				<span class="small opacity-75">'
					. ($category_count
						? h(lang(array('string' => '{var:1} categories', 'vars' => array(pg_format_number($category_count, 0)))))
						: lang('No categories yet')) . '</span>
				<form method="post" action="marketplace_settings.php" class="d-inline">
					' . get_token_field() . '
					<input type="hidden" name="mp_action" value="categories">
					<input type="hidden" name="account_id" value="' . $selected_id . '">
					<button type="submit" class="btn btn-sm btn-outline-secondary">
						<i class="bi bi-download me-1"></i>' . lang('Fetch categories') . '</button>
				</form>
			</div>
		</div>
		<div class="card-body px-2 py-3">';

	if (!$category_count) {

		echo '
			<p class="small opacity-75 text-center my-4 mb-0">'
			. lang('Fetch the marketplace category list first. It is downloaded once and kept.') . '</p>';

	} elseif (!$articles) {

		echo '
			<p class="small opacity-75 text-center my-4 mb-0">'
			. lang('This shop has no product group with products in it, so there is no article to list.') . '</p>';

	} else {

		echo '
			<div class="mp-scroll">
			<div class="mp-head" style="grid-template-columns:1.6fr 2fr .7fr 1.1fr">
				<span>' . lang('Article') . '</span><span>' . lang('Marketplace category') . '</span>
				<span>' . lang('Variants') . '</span><span></span>
			</div>';

		foreach ($articles as $article) {

			$is_open = ((int)$article['id'] === $open_group);

			echo '
			<div class="mp-row" style="grid-template-columns:1.6fr 2fr .7fr 1.1fr">
				<span>
					<b>' . h((string)$article['name']) . '</b>
					<code>' . h(mb_substr((string)$article['title'], 0, 40)) . '</code>
				</span>
				<span>' . (($article['category_path'] !== null && $article['category_path'] !== '')
						? '<span class="small">' . h((string)$article['category_path']) . '</span>'
						: '<span class="mp-pill off">' . lang('Not chosen') . '</span>')
					. ((string)$article['last_error'] !== ''
						? '<div class="mp-err">' . h(mb_substr((string)$article['last_error'], 0, 110)) . '</div>'
						: '') . '</span>
				<span>' . (int)$article['variants'] . '</span>
				<span class="d-flex gap-1 justify-content-end">
					<a class="btn btn-sm ' . ($is_open ? 'btn-primary' : 'btn-outline-secondary') . '"
						href="marketplace_settings.php?account=' . $selected_id . '&amp;group=' . (int)$article['id'] . '#mp-list">'
						. ($is_open ? lang('Close') : lang('Set up')) . '</a>';

			if ((string)$article['remote_category_id'] !== '' && $can_list) {

				echo '
					<form method="post" action="marketplace_settings.php" class="d-inline">
						' . get_token_field() . '
						<input type="hidden" name="mp_action" value="list_now">
						<input type="hidden" name="account_id" value="' . $selected_id . '">
						<input type="hidden" name="group_id" value="' . (int)$article['id'] . '">
						<button type="submit" class="btn btn-sm btn-outline-secondary" title="' . lang('List on the marketplace') . '">
							<i class="bi bi-upload"></i></button>
					</form>';

			}

			echo '
				</span>
			</div>';

			if ($is_open) {

				echo mp_settings_article_form($selected, $selected_id, $article, $open_map, $open_attributes, $open_chosen);

			}

		}

		echo '
			</div>';

	}

	echo '
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

				$webhook_text .= '<div class="text-danger mt-1">' . h(lang(array(
					'string' => 'While this account is paused or does not bring orders in, the orders {var:1} sends are refused, and after repeated refusals {var:1} switches the webhook off. Remove the webhook if this lasts.',
					'vars'   => array($provider_name)
				))) . '</div>';

			}

			$webhook_action = 'webhook_remove';

			$webhook_button = '<button type="submit" class="btn btn-sm btn-outline-danger"
						onclick="return confirm(' . h(json_encode(lang('Remove the webhook?'))) . ')">
						<i class="bi bi-x-lg me-1"></i>' . lang('Remove webhook') . '</button>';

		} else {

			$webhook_text = h(lang(array(
				'string' => 'Orders are read every five minutes. Register a webhook so {var:1} sends them as they happen.',
				'vars'   => array($provider_name)
			)));

			$webhook_action = 'webhook_register';

			$webhook_button = '<button type="submit" class="btn btn-sm btn-outline-secondary">
						<i class="bi bi-broadcast me-1"></i>' . lang('Register webhook') . '</button>';

		}

		$webhook_block = '
			<div class="d-flex align-items-center flex-wrap gap-2 small px-2 pb-3 mb-3 border-bottom" id="mp-webhook">
				<i class="bi bi-broadcast"></i>
				<div class="flex-grow-1">' . $webhook_text . '</div>
				<form method="post" action="marketplace_settings.php" class="d-inline">
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
	<div class="card mb-3">
		<div class="card-body px-2 pt-3 pb-0">' . $webhook_block . '
		</div>
	</div>';

	}

	if ($can_orders && !empty($selected['pull_orders'])) {

		echo '
	<div class="card mb-3" id="mp-orders">
		<div class="card-header d-flex align-items-center flex-wrap gap-2">
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
				<button type="submit" class="btn btn-sm btn-outline-secondary"
					title="' . h(lang('Match the products first. The next run reads the failed orders again.')) . '">
					<i class="bi bi-arrow-clockwise me-1"></i>' . lang('Try again') . '</button>
			</form>' : '') . '
		</div>
		<div class="card-body px-2 py-3">' . $webhook_block;

		if (!$orders) {

			echo '
			<p class="small opacity-75 text-center my-4 mb-0">'
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
						? ' <span class="mp-pill on">' . lang('Accepted') . '</span>' : '') . '
					' . ($row['last_error'] !== ''
						? '<div class="mp-err">' . h(mb_substr((string)$row['last_error'], 0, 120)) . '</div>' : '') . '</span>
				<span class="small opacity-75">' . h(mp_settings_ago((int)$row['imported_timestamp'])) . '</span>
				<span>' . ($row['order_id']
					? '<a class="btn btn-sm btn-link p-0" href="view_order.php?id=' . (int)$row['order_id'] . '" title="'
						. lang('View Order') . '"><i class="bi bi-box-arrow-up-right"></i></a>'
					: '') . '</span>
			</div>';

			}

			echo '
			</div>';

		}

		echo '
		</div>
	</div>';

	}

	/* -------------------------------------------------------------- queue */

	echo '
	<div class="card" id="mp-queue">
		<div class="card-header d-flex align-items-center flex-wrap gap-2">
			<i class="bi bi-hourglass-split me-1"></i><b>' . lang('What did not go through') . '</b>
			<div class="ms-auto d-flex gap-2">';

	if ($failures) {

		echo '
				<form method="post" action="marketplace_settings.php" class="d-inline">
					' . get_token_field() . '
					<input type="hidden" name="mp_action" value="retry">
					<input type="hidden" name="account_id" value="' . $selected_id . '">
					<button type="submit" class="btn btn-sm btn-outline-secondary">
						<i class="bi bi-arrow-clockwise me-1"></i>' . lang('Try again') . '</button>
				</form>
				<form method="post" action="marketplace_settings.php" class="d-inline">
					' . get_token_field() . '
					<input type="hidden" name="mp_action" value="clear_queue">
					<input type="hidden" name="account_id" value="' . $selected_id . '">
					<button type="submit" class="btn btn-sm btn-outline-secondary">
						<i class="bi bi-trash me-1"></i>' . lang('Clear') . '</button>
				</form>';

	}

	echo '
			</div>
		</div>
		<div class="card-body px-3 py-3">';

	if (!$failures) {

		echo '
			<p class="small opacity-75 text-center my-3 mb-0">'
			. lang('Nothing has failed. Sent items disappear from here once the marketplace confirms them.') . '</p>';

	} else {

		foreach ($failures as $row) {

			echo '
			<div class="d-flex gap-3 py-2 border-top align-items-start">
				<div style="min-width:150px"><b class="small">' . h((string)$row['product_name']) . '</b></div>
				<div class="flex-grow-1 mp-err">' . h((string)$row['last_error']) . '</div>
				<div class="small opacity-50 text-nowrap">' . h(mp_settings_ago((int)$row['updated_timestamp'])) . '</div>
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
					<div class="form-text mt-n2 mb-3">' . h(lang($meta['help'])) . '</div>';

	}

	$new_fields .= '
				</div>';

}

echo '
<div class="modal fade" id="mp_new" tabindex="-1">
	<div class="modal-dialog">
		<form method="post" action="marketplace_settings.php" autocomplete="off" class="modal-content disable_shortcut">
			' . get_token_field() . '
			<input type="hidden" name="mp_action" value="create">
			<div class="modal-header">
				<h5 class="modal-title"><i class="bi bi-shop me-2"></i>' . lang('Connect a Marketplace') . '</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
			</div>
			<div class="modal-body">
				<div class="mb-3">
					<label class="form-label" for="new_provider">' . lang('Marketplace') . '</label>
					<select class="form-select" id="new_provider" name="provider">' . $provider_options . '</select>
				</div>
				<div class="mb-3">
					<label class="form-label" for="new_name">' . lang('Name') . '</label>
					<input type="text" class="form-control" id="new_name" name="name"
						placeholder="' . lang('The name you will recognise this store by') . '">
				</div>
				' . $new_fields . '
				<div class="form-check form-switch">
					<input class="form-check-input" type="checkbox" role="switch" id="new_push_price" name="push_price" value="1" checked>
					<label class="form-check-label" for="new_push_price">' . lang('Send prices as well as stock') . '</label>
					<div class="form-text">'
						. lang('Many shops price differently on a marketplace because of its commission. Leave this off to send only quantities.') . '</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">' . lang('Cancel') . '</button>
				<button type="submit" class="btn btn-primary">' . lang('Connect') . '</button>
			</div>
		</form>
	</div>
</div>';

if ($selected) {

	$edit_fields = '';

	foreach ($provider_fields as $field => $meta) {

		$current = isset($selected['credentials'][$field]) ? (string)$selected['credentials'][$field] : '';

		$edit_fields .= mp_settings_credential_field('edit', $field, $meta, $current, true);

	}

	if (!empty($providers[$selected['provider']]['help'])) {

		$edit_fields .= '
			<div class="form-text mt-n2 mb-3">' . h(lang($providers[$selected['provider']]['help'])) . '</div>';

	}

	echo '
<div class="offcanvas offcanvas-end" tabindex="-1" id="mp_drawer" style="width:min(480px,100%)">
	<form method="post" action="marketplace_settings.php" autocomplete="off" class="disable_shortcut d-flex flex-column h-100">
		' . get_token_field() . '
		<input type="hidden" name="mp_action" value="update">
		<input type="hidden" name="account_id" value="' . $selected_id . '">
		<div class="offcanvas-header border-bottom">
			<h5 class="offcanvas-title"><i class="bi bi-sliders me-2"></i>' . h($selected['name']) . '</h5>
			<button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
		</div>
		<div class="offcanvas-body">
			<div class="mb-3">
				<label class="form-label" for="edit_name">' . lang('Name') . '</label>
				<input type="text" class="form-control" id="edit_name" name="name" value="' . h($selected['name']) . '">
			</div>
			' . $edit_fields . '
			<div class="form-check form-switch mb-2">
				<input class="form-check-input" type="checkbox" role="switch" id="edit_status" name="status" value="active"'
					. ($selected['status'] === 'active' ? ' checked' : '') . '>
				<label class="form-check-label" for="edit_status">' . lang('Send changes to this marketplace') . '</label>
			</div>
			<div class="form-check form-switch mb-2">
				<input class="form-check-input" type="checkbox" role="switch" id="edit_push_stock" name="push_stock" value="1"'
					. (!empty($selected['push_stock']) ? ' checked' : '') . '>
				<label class="form-check-label" for="edit_push_stock">' . lang('Send stock') . '</label>
			</div>
			<div class="form-check form-switch mb-4">
				<input class="form-check-input" type="checkbox" role="switch" id="edit_push_price" name="push_price" value="1"'
					. (!empty($selected['push_price']) ? ' checked' : '') . '>
				<label class="form-check-label" for="edit_push_price">' . lang('Send prices') . '</label>
			</div>
			<hr>'
			. ($can_orders ? '
			<div class="form-check form-switch ' . ($can_approve ? 'mb-2' : 'mb-4') . '">
				<input class="form-check-input" type="checkbox" role="switch" id="edit_pull_orders" name="pull_orders" value="1"'
					. (!empty($selected['pull_orders']) ? ' checked' : '') . '>
				<label class="form-check-label" for="edit_pull_orders">' . lang('Bring orders in from this marketplace') . '</label>
			</div>'
			. ($can_approve ? '
			<div class="form-check form-switch mb-4">
				<input class="form-check-input" type="checkbox" role="switch" id="edit_auto_approve" name="auto_approve" value="1"'
					. (!empty($selected['auto_approve']) ? ' checked' : '') . '>
				<label class="form-check-label" for="edit_auto_approve">' . lang('Accept new orders automatically') . '</label>
				<div class="form-text">'
					. lang('Accepting an order tells the marketplace you are preparing it, and their dispatch clock starts. Leave this off to accept them yourself.') . '</div>
			</div>' : '') : '
			<p class="small opacity-75 mb-4">' . h(lang(array(
				'string' => 'Orders are not brought in from {var:1} yet.',
				'vars'   => array(mp_provider_name($selected['provider']))
			))) . '</p>') . '
			<hr>'
			. (!empty($providers[$selected['provider']]['shipment_template']) ? '
			<div class="mb-3">
				<label class="form-label" for="edit_shipment_template">' . lang('Cargo template') . '</label>
				<input type="text" class="form-control" id="edit_shipment_template" name="shipment_template"
					value="' . h((string)$selected['shipment_template']) . '">
				<div class="form-text">'
					. lang('The name of the cargo agreement in your marketplace store settings, copied exactly. A new listing is refused without it.') . '</div>
			</div>' : '') . '
			<div class="mb-4">
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
			<button type="submit" class="btn btn-outline-danger btn-sm" name="mp_action" value="delete"
				formnovalidate onclick="return confirm(' . h(json_encode(lang('Remove this marketplace?'))) . ')">
				<i class="bi bi-trash me-1"></i>' . lang('Remove marketplace') . '</button>'
			. (!empty($selected['credentials']['webhook_id']) ? '
			<div class="form-check small mt-2">
				<input class="form-check-input" type="checkbox" id="edit_webhook_force" name="webhook_force" value="1">
				<label class="form-check-label" for="edit_webhook_force">' . h(lang(array(
					'string' => 'Remove the marketplace even if its webhook cannot be removed from {var:1} (then remove the webhook in the seller panel)',
					'vars'   => array(mp_provider_name($selected['provider']))
				))) . '</label>
			</div>' : '') . '
		</div>
		<div class="offcanvas-footer border-top p-3 d-flex gap-2">
			<button type="button" class="btn btn-outline-secondary flex-grow-1" data-bs-dismiss="offcanvas">' . lang('Cancel') . '</button>
			<button type="submit" class="btn btn-primary flex-grow-1">' . lang('Save') . '</button>
		</div>
	</form>
</div>

<div class="modal fade" id="mp_match" tabindex="-1">
	<div class="modal-dialog">
		<form method="post" action="marketplace_settings.php" autocomplete="off" class="modal-content disable_shortcut">
			' . get_token_field() . '
			<input type="hidden" name="mp_action" value="map">
			<input type="hidden" name="account_id" value="' . $selected_id . '">
			<div class="modal-header">
				<h5 class="modal-title"><i class="bi bi-link-45deg me-2"></i>' . lang('Match a product') . '</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
			</div>
			<div class="modal-body">
				<div class="mb-3">
					<label class="form-label" for="match_product">' . lang('Product') . '</label>
					<input class="form-control" list="mp_products" id="match_product_text" name="product_code"
						placeholder="' . lang('Type a product code') . '" autocomplete="off">
					<input type="hidden" name="product_id" id="match_product">
					<datalist id="mp_products"></datalist>
					<div class="form-text">' . lang('Only products that are not matched to this marketplace yet.') . '</div>
				</div>
				<div class="mb-3">
					<label class="form-label" for="match_code">' . lang('Code on the marketplace') . '</label>
					<input type="text" class="form-control" id="match_code" name="remote_code" autocomplete="off">
					<div class="form-text">'
						. (($selected['provider'] === 'trendyol')
							? lang('Leave empty to use the product\'s barcode. Trendyol knows a listing by its barcode.')
							: lang('Leave empty to use the same code as here. On n11 this is the stock code (stockCode) of the listing.')) . '</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">' . lang('Cancel') . '</button>
				<button type="submit" class="btn btn-primary">' . lang('Match') . '</button>
			</div>
		</form>
	</div>
</div>';

}

/* The product picker is filled from a list built here rather than by a search
   endpoint: a catalogue that fits in a datalist does not need one, and the
   screens that do have a search endpoint are the ones with tens of thousands
   of rows. The cap is the point at which that stops being true. */

// The category picker's list.
//
// Leaves only, because nothing else can hold a product, and only when an
// article is open - the tree runs to tens of thousands of rows and putting all
// of them into every page view would be a megabyte of markup nobody reads.
$category_options = '';

if ($selected && $open_group) {

	foreach ((array) db_items("SELECT remote_id, path FROM marketplace_categories
		WHERE (provider = '" . e($selected['provider']) . "') AND (is_leaf = '1')
		ORDER BY path ASC
		LIMIT 6000") as $row) {

		$category_options .= '<option value="' . h((string)$row['remote_id']) . '">'
			. h((string)$row['path']) . '</option>';

	}

}

$unmatched = array();

if ($selected) {

	// Products in the Recycle Bin are not offered for matching.
	$unmatched = (array) db_items("SELECT products.id, products.name, products.title
		FROM products
		WHERE products.id NOT IN (
			SELECT product_id FROM marketplace_product_map WHERE account_id = '" . e($selected_id) . "')" . pg_catalog_bin_live_sql('products') . "
		ORDER BY products.name ASC
		LIMIT 2000");

}

echo '
<datalist id="mp_categories">' . $category_options . '</datalist>

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

	var products = ' . json_encode($unmatched, JSON_UNESCAPED_UNICODE) . ';

	var list = document.getElementById(\'mp_products\');
	var text = document.getElementById(\'match_product_text\');
	var hidden = document.getElementById(\'match_product\');

	if (list && products.length) {

		var html = \'\';

		for (var i = 0; i < products.length; i++) {

			var label = products[i].name + (products[i].title ? \' \\u2014 \' + products[i].title : \'\');

			html += \'<option value="\' + label.replace(/"/g, \'&quot;\') + \'"></option>\';

		}

		list.innerHTML = html;

	}

	// The datalist hands back the label, not the id. The code is everything up
	// to the em dash, which is what the option was built from.
	if (text && hidden) {

		text.addEventListener(\'input\', function () {

			var typed = text.value.split(\' \\u2014 \')[0].trim();

			hidden.value = \'\';

			for (var i = 0; i < products.length; i++) {

				if (products[i].name === typed) {

					hidden.value = products[i].id;

					break;

				}

			}

		});

	}

})();
</script>' . output_footer();
