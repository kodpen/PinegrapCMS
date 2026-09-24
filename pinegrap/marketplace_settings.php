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

$liveform = new liveform('marketplace_settings');

$screen_url = URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/marketplace_settings.php';

/* ---------------------------------------------------------------------------
   Writes
   --------------------------------------------------------------------------- */

if ($_POST) {

	validate_token_field();

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

		foreach ($credentials as $field => $value) {

			if (trim((string)$value) === '') {

				$liveform->mark_error($field, lang('This is required.'));

			}

		}

		if ($liveform->check_form_errors() == false) {

			db("INSERT INTO marketplace_accounts
				(provider, name, credentials, status, push_stock, push_price,
				 created_user_id, created_timestamp, updated_timestamp)
				VALUES (
					'" . e($provider) . "',
					'" . e($name) . "',
					'" . e(mp_credentials_encode($credentials)) . "',
					'paused',
					'1',
					'" . (empty($_POST['push_price']) ? '0' : '1') . "',
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
				'vars'   => array($result['error'])
			)));

		}

		header('Location: ' . $screen_url . '?account=' . $account_id);

			exit();

	}

	if ($action === 'delete' && $account) {

		db("DELETE FROM marketplace_sync_queue WHERE account_id = '" . e($account_id) . "'");

		db("DELETE FROM marketplace_product_map WHERE account_id = '" . e($account_id) . "'");

		db("DELETE FROM marketplace_accounts WHERE id = '" . e($account_id) . "'");

		log_activity(lang(array(
			'string' => 'Marketplace account removed: {var:1}.',
			'vars'   => array($account['name'])
		)), $user['username']);

		$liveform->add_notice(lang('Marketplace removed. The products themselves are untouched.'));

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

			$liveform->add_warning($result['error']);

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

				$liveform->add_warning($loaded['error']);

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

		$ready = mp_listing_items($account, $group_id);

		if (!$ready['ok']) {

			$liveform->add_warning($ready['error']);

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
			// typo gets into the mapping.
			if ($remote_code === '') {

				$remote_code = $product['name'];

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
		// what tells them apart - so it is shown and not asked for.
		if (!empty($attribute['is_variant'])) {

			$out .= '
						<div class="col-12 col-md-6 col-xl-4">
							<label class="form-label small mb-1">' . h((string)$attribute['name'])
								. ' <span class="mp-pill off">' . lang('from the variants') . '</span></label>
							<div class="form-text mt-0">'
								. lang('Taken from each product\'s own option.') . '</div>
						</div>';

			continue;

		}

		$required = !empty($attribute['mandatory']);

		$out .= '
						<div class="col-12 col-md-6 col-xl-4">
							<label class="form-label small mb-1" for="attr_' . $group_id . '_' . h($id) . '">'
								. h((string)$attribute['name'])
								. ($required ? ' <span class="text-danger">*</span>' : '') . '</label>';

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

	$providers = mp_providers();

	if (!isset($providers[$provider]['fields'])) {

		return array();

	}

	$values = array();

	foreach ($providers[$provider]['fields'] as $field => $meta) {

		$values[$field] = isset($_POST[$field]) ? trim($_POST[$field]) : '';

	}

	return $values;

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
	// shop's price and quantity next to the remote code, because the question
	// an operator opens this screen with is "what is it going to send".
	$maps = (array) db_items("SELECT marketplace_product_map.*,
			products.name AS product_name, products.title, products.price,
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

	$provider_fields = isset($providers[$selected['provider']]['fields'])
		? $providers[$selected['provider']]['fields']
		: array();

	/* ------------------------------------------------------------ account */

	echo '
	<div class="card mb-3">
		<div class="card-header d-flex align-items-center flex-wrap gap-2">
			<i class="bi bi-sliders me-1"></i><b>' . h($selected['name']) . '</b>
			<span class="small opacity-75">' . h(mp_provider_name($selected['provider'])) . '</span>
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
				<span>' . prepare_price_for_output((int)$map['price'], false, 0, 'html', false) . '</span>
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

			if ((string)$article['remote_category_id'] !== '') {

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

	/* ------------------------------------------------------------- orders */

	if (!empty($selected['pull_orders'])) {

		echo '
	<div class="card mb-3" id="mp-orders">
		<div class="card-header d-flex align-items-center flex-wrap gap-2">
			<i class="bi bi-bag-check me-1"></i><b>' . lang('Imported orders') . '</b>
			<span class="small opacity-75 ms-auto">'
				. ((int)$selected['last_order_pull']
					? h(lang(array('string' => 'Orders read up to {var:1}',
						'vars' => array(mp_settings_ago((int)$selected['last_order_pull'])))))
					: lang('Never pulled')) . '</span>
		</div>
		<div class="card-body px-2 py-3">';

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

$new_fields = '';

if (isset($providers[$first_provider]['fields'])) {

	foreach ($providers[$first_provider]['fields'] as $field => $meta) {

		$new_fields .= '
			<div class="mb-3">
				<label class="form-label" for="new_' . h($field) . '">' . h(lang($meta['label'])) . '</label>
				<input type="' . (!empty($meta['secret']) ? 'password' : 'text') . '" class="form-control"
					id="new_' . h($field) . '" name="' . h($field) . '" autocomplete="off">
			</div>';

	}

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

		$edit_fields .= '
			<div class="mb-3">
				<label class="form-label" for="edit_' . h($field) . '">' . h(lang($meta['label'])) . '</label>
				<input type="' . (!empty($meta['secret']) ? 'password' : 'text') . '" class="form-control"
					id="edit_' . h($field) . '" name="' . h($field) . '" autocomplete="off"
					placeholder="' . h(mp_credential_hint($current)) . '">
				<div class="form-text">' . lang('Leave empty to keep what is stored.') . '</div>
			</div>';

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
			<hr>
			<div class="form-check form-switch mb-2">
				<input class="form-check-input" type="checkbox" role="switch" id="edit_pull_orders" name="pull_orders" value="1"'
					. (!empty($selected['pull_orders']) ? ' checked' : '') . '>
				<label class="form-check-label" for="edit_pull_orders">' . lang('Bring orders in from this marketplace') . '</label>
			</div>
			<div class="form-check form-switch mb-4">
				<input class="form-check-input" type="checkbox" role="switch" id="edit_auto_approve" name="auto_approve" value="1"'
					. (!empty($selected['auto_approve']) ? ' checked' : '') . '>
				<label class="form-check-label" for="edit_auto_approve">' . lang('Accept new orders automatically') . '</label>
				<div class="form-text">'
					. lang('Accepting an order tells the marketplace you are preparing it, and their dispatch clock starts. Leave this off to accept them yourself.') . '</div>
			</div>
			<hr>
			<div class="mb-3">
				<label class="form-label" for="edit_shipment_template">' . lang('Cargo template') . '</label>
				<input type="text" class="form-control" id="edit_shipment_template" name="shipment_template"
					value="' . h((string)$selected['shipment_template']) . '">
				<div class="form-text">'
					. lang('The name of the cargo agreement in your marketplace store settings, copied exactly. A new listing is refused without it.') . '</div>
			</div>
			<div class="mb-4">
				<label class="form-label" for="edit_preparing_day">' . lang('Days to prepare') . '</label>
				<input type="number" class="form-control" id="edit_preparing_day" name="preparing_day" min="1" max="30"
					value="' . (int)$selected['preparing_day'] . '" style="max-width:110px">
				<div class="form-text">' . lang('How long you take to hand a parcel over. It is shown to the buyer.') . '</div>
			</div>
			<hr>
			<p class="small opacity-75">'
				. lang('Removing a marketplace deletes its product matches and its queue. Your products are not touched, and nothing is removed from the marketplace itself.') . '</p>
			<button type="submit" class="btn btn-outline-danger btn-sm" name="mp_action" value="delete"
				formnovalidate onclick="return confirm(' . h(json_encode(lang('Remove this marketplace?'))) . ')">
				<i class="bi bi-trash me-1"></i>' . lang('Remove marketplace') . '</button>
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
						. lang('Leave empty to use the same code as here. On n11 this is the stock code (stockCode) of the listing.') . '</div>
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

	$unmatched = (array) db_items("SELECT products.id, products.name, products.title
		FROM products
		WHERE products.id NOT IN (
			SELECT product_id FROM marketplace_product_map WHERE account_id = '" . e($selected_id) . "')
		ORDER BY products.name ASC
		LIMIT 2000");

}

echo '
<datalist id="mp_categories">' . $category_options . '</datalist>

<script>
(function () {

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
