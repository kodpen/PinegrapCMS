<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Working the marketplace queue.
//
// Two jobs in one pass, and they are separate because the marketplaces are
// asynchronous:
//
//   push   waiting rows are gathered per account, sent as one batch, and
//          marked 'sent' with the task id the marketplace answered with.
//
//   poll   'sent' rows are looked up by that task id. The answer is per SKU, so
//          one batch can come back with nine hundred rows done and a hundred
//          refused, and each row is closed on its own verdict.
//
// A row is never closed on the strength of the push alone. "Accepted" is not
// "applied", and a queue that treated them as the same would tell the operator
// their catalogue is in step while the marketplace is refusing half of it.
//
// Nothing here runs inside a web request. The screens write rows; this reads
// them, from cron.

if (!defined('PG_INIT_LOADED')) {

	exit;

}

require_once(dirname(__FILE__) . '/connectors/base.php');
require_once(dirname(__FILE__) . '/orders.php');
require_once(dirname(__FILE__) . '/listings.php');

// One pass. Answers what it did, for the cron's output and the panel.
function mp_sync_run($seconds = 25) {

	$summary = array('pushed' => 0, 'batches' => 0, 'done' => 0, 'failed' => 0, 'waiting' => 0,
		'orders' => 0, 'order_errors' => 0, 'listed' => 0, 'listings_checked' => 0);

	if (!defined('DB_CONNECTED')) {

		return $summary;

	}

	$deadline = microtime(true) + $seconds;

	// Poll first. A batch that finished while the last run was asleep frees its
	// rows before this run decides what else to send, which keeps a slow
	// marketplace from being handed a second batch of the same products.
	mp_sync_poll($deadline, $summary);

	mp_sync_push($deadline, $summary);

	// Orders last. Importing one takes stock off, which queues a push - and a
	// push queued now goes out on the next pass rather than being sent inside
	// the same run, which keeps a busy morning of orders from turning into one
	// request per order.
	mp_sync_orders($deadline, $summary);

	// Where the listings stand comes after both, as the least urgent: a slow
	// product read then cannot use up the time the stock and the orders need.
	// A mapping set back to active here, its listing approved, has its stock
	// and price queued again, and they go out on the next pass.
	mp_sync_listing_states($deadline, $summary);

	mp_sync_sweep();

	db("UPDATE config SET marketplace_sync_last_run = UNIX_TIMESTAMP()");

	$summary['waiting'] = (int) db_value("SELECT COUNT(*) FROM marketplace_sync_queue WHERE status = 'waiting'");

	return $summary;

}

/* --------------------------------------------------------------- sending */

function mp_sync_push($deadline, &$summary) {

	while (microtime(true) < $deadline) {

		// Which account has the most urgent work. Priority first, then age, so
		// a "send now" from the product screen overtakes an hour of ordinary
		// stock changes without starving them.
		$next = db_item("SELECT account_id
			FROM marketplace_sync_queue
			WHERE (status = 'waiting') AND (run_after <= UNIX_TIMESTAMP())
			ORDER BY priority ASC, id ASC
			LIMIT 1");

		if (!$next) {

			return;

		}

		$account = mp_account((int)$next['account_id']);

		if (!$account || $account['status'] !== 'active') {

			// The account is gone or switched off. Its rows are closed rather
			// than left to be selected again for ever on every pass.
			mp_sync_close_account_rows((int)$next['account_id'],
				lang('The marketplace account is not active.'), $summary);

			continue;

		}

		// Listings first for this account. A product that is not on the
		// marketplace yet cannot have its stock updated there, so sending the
		// stock before the listing exists is a call that can only be refused.
		mp_sync_send_listings($account, $summary);

		// Then changes to listings already approved, which carry no stock or
		// price and do not wait on anything.
		mp_sync_send_listing_updates($account, $summary);

		if (!mp_sync_send_batch($account, $summary)) {

			return;

		}

	}

}

// One batch for one account. Answers false when there was nothing to send.
function mp_sync_send_batch($account, &$summary) {

	$account_id = (int) $account['id'];

	// The provider's ceiling is a thousand; this stays under it and under the
	// weight of a single request the marketplace has to parse.
	$rows = (array) db_items("SELECT marketplace_sync_queue.id, marketplace_sync_queue.product_id,
			marketplace_sync_queue.attempts,
			marketplace_product_map.remote_code, marketplace_product_map.remote_id
		FROM marketplace_sync_queue
		INNER JOIN marketplace_product_map
			ON  (marketplace_product_map.account_id = marketplace_sync_queue.account_id)
			AND (marketplace_product_map.product_id = marketplace_sync_queue.product_id)
		WHERE (marketplace_sync_queue.account_id = '" . e($account_id) . "')
		  AND (marketplace_sync_queue.status = 'waiting')
		  AND (marketplace_sync_queue.run_after <= UNIX_TIMESTAMP())
		  AND (marketplace_sync_queue.operation = 'stock_price')
		  AND (marketplace_product_map.status = 'active')
		ORDER BY marketplace_sync_queue.priority ASC, marketplace_sync_queue.id ASC
		LIMIT 500");

	if (!$rows) {

		// Either nothing is due, or what is due has no mapping any more. The
		// second case has to be closed or the account is picked again on every
		// pass and the loop never ends.
		mp_sync_close_unmapped($account_id, $summary);

		return false;

	}

	$ids = array();

	$payload = array();

	foreach ($rows as $row) {

		$ids[] = (int) $row['id'];

		$product = mp_product_row($account, $row);

		if ($product === null) {

			// The product was deleted between the change and this pass.
			mp_sync_finish_row((int)$row['id'], 'failed', lang('The product no longer exists.'));

			$summary['failed']++;

			continue;

		}

		$payload[] = $product;

	}

	// Leased before the network call, so a second cron overlapping this one
	// walks past these rows instead of sending them again. The rows stay
	// 'waiting': if this process dies mid-call the lease simply expires.
	if ($ids) {

		db("UPDATE marketplace_sync_queue
			SET run_after = UNIX_TIMESTAMP() + 300, updated_timestamp = UNIX_TIMESTAMP()
			WHERE id IN (" . implode(',', $ids) . ")");

	}

	if (!$payload) {

		return true;

	}

	$result = mp_call($account['provider'], 'push_stock_price', array($account, $payload));

	$summary['batches']++;

	if (!$result['ok']) {

		mp_sync_batch_failed($ids, $result, $summary);

		mp_log($account_id, 'stock_price', false, $result['error'], count($payload));

		return true;

	}

	$task_id = isset($result['data']['task_id']) ? (string)$result['data']['task_id'] : '';

	// Products the connector left out of the request because the marketplace
	// would refuse them (a barcode against its rules, no price). Each fails
	// now on its own reason, which also goes on its mapping row, where the
	// operator looks. The mapping stays active: the next change to the product
	// queues it again, and is sent once the cause is fixed.
	$refused = (isset($result['data']['refused']) && is_array($result['data']['refused']))
		? $result['data']['refused'] : array();

	foreach ($rows as $row) {

		$product_id = (int) $row['product_id'];

		if (!isset($refused[$product_id])) {

			continue;

		}

		mp_sync_finish_row((int)$row['id'], 'failed', (string)$refused[$product_id]);

		db("UPDATE marketplace_product_map
			SET last_error = '" . e(mb_substr((string)$refused[$product_id], 0, 500)) . "'
			WHERE (account_id = '" . e($account_id) . "') AND (product_id = '" . e($product_id) . "')");

		$summary['failed']++;

	}

	// Nothing was sent: whatever is left had nothing to say (a product that
	// does not track stock, on an account that does not send prices). There
	// is no task to ask about, so those rows are done here.
	if ($task_id === '') {

		$done = (int) db_value("SELECT COUNT(*) FROM marketplace_sync_queue
			WHERE id IN (" . implode(',', $ids) . ") AND status = 'waiting'");

		db("UPDATE marketplace_sync_queue
			SET status = 'done', last_error = '', updated_timestamp = UNIX_TIMESTAMP()
			WHERE id IN (" . implode(',', $ids) . ") AND status = 'waiting'");

		$summary['done'] += $done;

		mp_account_outcome($account_id, true);

		return true;

	}

	$summary['pushed'] += isset($result['data']['sent']) ? (int)$result['data']['sent'] : count($payload);

	db("UPDATE marketplace_sync_queue
		SET status = 'sent',
			remote_task_id = '" . e($task_id) . "',
			last_error = '',
			run_after = UNIX_TIMESTAMP() + 30,
			updated_timestamp = UNIX_TIMESTAMP()
		WHERE id IN (" . implode(',', $ids) . ") AND status = 'waiting'");

	mp_account_outcome($account_id, true);

	return true;

}

// A batch the marketplace would not take.
//
// Retryable means their side had a moment - a timeout, a 500, a rate limit -
// and the rows go back to waiting with a longer delay. Anything else is a
// refusal that will be refused again, so the rows are closed and the operator
// is told once rather than every few minutes for a day.
function mp_sync_batch_failed($ids, $result, &$summary) {

	if (!$ids) {

		return;

	}

	$list = implode(',', array_map('intval', $ids));

	$error = e(mb_substr((string)$result['error'], 0, 500));

	if (!empty($result['retry'])) {

		// A connector that knows how long the marketplace will keep refusing
		// (a repeat window) says so; waiting less only spends attempts.
		$delay = (int)mp_backoff_seconds(1);

		if (isset($result['data']['retry_after']) && is_numeric($result['data']['retry_after'])) {

			$delay = max($delay, min(3600, (int)$result['data']['retry_after']));

		}

		db("UPDATE marketplace_sync_queue
			SET attempts = attempts + 1,
				last_error = '" . $error . "',
				run_after = UNIX_TIMESTAMP() + " . $delay . ",
				updated_timestamp = UNIX_TIMESTAMP()
			WHERE id IN (" . $list . ")");

		// Rows that have used up their attempts stop here, whatever the reason.
		db("UPDATE marketplace_sync_queue
			SET status = 'failed', updated_timestamp = UNIX_TIMESTAMP()
			WHERE id IN (" . $list . ") AND attempts >= '" . (int)mp_max_attempts() . "'");

		$summary['failed'] += (int) db_value("SELECT COUNT(*) FROM marketplace_sync_queue
			WHERE id IN (" . $list . ") AND status = 'failed'");

		return;

	}

	db("UPDATE marketplace_sync_queue
		SET status = 'failed', last_error = '" . $error . "', updated_timestamp = UNIX_TIMESTAMP()
		WHERE id IN (" . $list . ")");

	$summary['failed'] += count($ids);

}


/* ------------------------------------------------------------- new listings */

// Open whatever listings are waiting on this account.
//
// One call per article rather than per product: the marketplace groups the
// variants by the article code they all carry, and sending them in separate
// calls would produce separate listings that happen to share a name.
//
// A batch that cannot be assembled is refused here rather than sent. A missing
// category or an unanswered mandatory attribute is something this shop can see,
// and finding out from the marketplace hours later - per SKU, in their words -
// is a worse way to learn it.
function mp_sync_send_listings($account, &$summary) {

	$account_id = (int) $account['id'];

	if (!mp_can($account['provider'], 'create_listings')) {

		return;

	}

	foreach (mp_listing_pending_groups($account_id) as $row) {

		$group_id = (int) $row['group_id'];

		// The bin filter is part of the second join, not of WHERE: a line whose
		// product was binned after it was queued still has to be read, so it
		// can be closed below instead of waiting for ever.
		$lines = (array) db_items("SELECT marketplace_sync_queue.id,
				products.id AS found_id, products.name AS product_name, live.id AS live_id
			FROM marketplace_sync_queue
			INNER JOIN products_groups_xref ON products_groups_xref.product = marketplace_sync_queue.product_id
			LEFT JOIN products ON (products.id = marketplace_sync_queue.product_id)
			LEFT JOIN products AS live ON (live.id = marketplace_sync_queue.product_id)" . pg_catalog_bin_live_sql('live') . "
			WHERE (marketplace_sync_queue.account_id = '" . e($account_id) . "')
			  AND (marketplace_sync_queue.operation = 'create')
			  AND (marketplace_sync_queue.status = 'waiting')
			  AND (marketplace_sync_queue.run_after <= UNIX_TIMESTAMP())
			  AND (products_groups_xref.product_group = '" . e($group_id) . "')");

		$split = mp_listing_split_live($lines);

		// The listing leaves these products out, so their lines are closed
		// here, before the lease, with the reason. Sent with the batch they
		// would close as done with nothing listed. The article's row is left
		// alone: the article is fine, the product is not for sale.
		foreach ($split['binned'] as $queue_id => $product_name) {

			mp_sync_finish_row($queue_id, 'failed', lang(array(
				'string' => '{var:1} is in the Recycle Bin, so it was not listed.',
				'vars'   => array($product_name)
			)));

			$summary['failed']++;

		}

		foreach ($split['missing'] as $queue_id) {

			mp_sync_finish_row($queue_id, 'failed', lang('The product no longer exists.'));

			$summary['failed']++;

		}

		$queue_ids = $split['send'];

		if (!$queue_ids) {

			continue;

		}

		// Leased before the call, the same as the stock batch.
		db("UPDATE marketplace_sync_queue
			SET run_after = UNIX_TIMESTAMP() + 300, updated_timestamp = UNIX_TIMESTAMP()
			WHERE id IN (" . implode(',', $queue_ids) . ")");

		$assembled = mp_listing_items($account, $group_id);

		if (!$assembled['ok']) {

			// A momentary failure while assembling (the category's attributes
			// could not be read) is tried again; everything else is about the
			// article and waits for the operator.
			mp_sync_batch_failed($queue_ids, mp_error($assembled['error'], !empty($assembled['retry'])), $summary);

			db("UPDATE marketplace_category_map
				SET last_error = '" . e(mb_substr((string)$assembled['error'], 0, 500)) . "',
					updated_timestamp = UNIX_TIMESTAMP()
				WHERE (account_id = '" . e($account_id) . "') AND (group_id = '" . e($group_id) . "')");

			continue;

		}

		$result = mp_call($account['provider'], 'create_listings', array($account, $assembled['items']));

		$summary['batches']++;

		if (!$result['ok']) {

			mp_sync_batch_failed($queue_ids, $result, $summary);

			mp_log($account_id, 'create', false, $result['error'], count($assembled['items']));

			// A refusal the connector made before sending (a barcode their rules
			// reject, a brand they do not have) is about the article, so it is
			// shown on the article's row, where the operator fixes it. A
			// momentary failure is not: the row would blame the article for
			// their outage.
			if (empty($result['retry'])) {

				db("UPDATE marketplace_category_map
					SET last_error = '" . e(mb_substr((string)$result['error'], 0, 500)) . "',
						updated_timestamp = UNIX_TIMESTAMP()
					WHERE (account_id = '" . e($account_id) . "') AND (group_id = '" . e($group_id) . "')");

			}

			continue;

		}

		$task_id = isset($result['data']['task_id']) ? (string)$result['data']['task_id'] : '';

		$summary['listed'] += count($assembled['items']);

		db("UPDATE marketplace_sync_queue
			SET status = 'sent',
				remote_task_id = '" . e($task_id) . "',
				last_error = '',
				run_after = UNIX_TIMESTAMP() + 30,
				updated_timestamp = UNIX_TIMESTAMP()
			WHERE id IN (" . implode(',', $queue_ids) . ") AND status = 'waiting'");

		db("UPDATE marketplace_category_map SET last_error = '', updated_timestamp = UNIX_TIMESTAMP()
			WHERE (account_id = '" . e($account_id) . "') AND (group_id = '" . e($group_id) . "')");

		// A listing that lands makes the product mappable, and the mapping is
		// what carries every stock change from here on. Written now rather than
		// when the task finishes, because the code is ours either way and a
		// mapping that points at a listing still being created costs nothing.
		foreach ($assembled['items'] as $item) {

			$exists = db_value("SELECT id FROM marketplace_product_map
				WHERE (account_id = '" . e($account_id) . "') AND (product_id = '" . e((int)$item['product_id']) . "')");

			if ($exists) {

				continue;

			}

			db("INSERT INTO marketplace_product_map
				(account_id, product_id, remote_code, status, created_timestamp)
				VALUES ('" . e($account_id) . "', '" . e((int)$item['product_id']) . "',
					'" . e((string)$item['code']) . "', 'active', UNIX_TIMESTAMP())");

		}

		mp_account_outcome($account_id, true);

	}

}

/* -------------------------------------------------------- listing updates */

// Send the title, description and pictures of approved listings the operator
// asked to update (operation listing_update, queued from the account screen).
//
// Every due line is either leased with the call or closed here, so nothing is
// left for mp_sync_close_unmapped(), which would close it as no longer
// matched. A line whose mapping lost its content id (matched again under
// another code) or whose product is gone is closed with the reason. The
// result is read like any batch, by content id, and never writes the mapping
// row: a refused update leaves the listing as it was over there, and its
// stock still goes.
function mp_sync_send_listing_updates($account, &$summary) {

	$account_id = (int) $account['id'];

	$rows = (array) db_items("SELECT marketplace_sync_queue.id, marketplace_sync_queue.product_id,
			marketplace_product_map.remote_id,
			products.id AS found_id, products.name, products.title, products.short_description,
			products.full_description, products.image_name,
			product_groups.title AS group_title, product_groups.full_description AS group_description
		FROM marketplace_sync_queue
		LEFT JOIN marketplace_product_map
			ON  (marketplace_product_map.account_id = marketplace_sync_queue.account_id)
			AND (marketplace_product_map.product_id = marketplace_sync_queue.product_id)
		LEFT JOIN products ON products.id = marketplace_sync_queue.product_id
		LEFT JOIN product_groups ON product_groups.id = (SELECT MIN(products_groups_xref.product_group)
			FROM products_groups_xref WHERE products_groups_xref.product = marketplace_sync_queue.product_id)
		WHERE (marketplace_sync_queue.account_id = '" . e($account_id) . "')
		  AND (marketplace_sync_queue.operation = 'listing_update')
		  AND (marketplace_sync_queue.status = 'waiting')
		  AND (marketplace_sync_queue.run_after <= UNIX_TIMESTAMP())
		ORDER BY marketplace_sync_queue.priority ASC, marketplace_sync_queue.id ASC
		LIMIT " . (int)mp_listing_update_batch_size());

	if (!$rows) {

		return;

	}

	if (!mp_can($account['provider'], 'update_listings')) {

		foreach ($rows as $row) {

			mp_sync_finish_row((int)$row['id'], 'failed', lang(array(
				'string' => '{var:1} cannot do this.',
				'vars'   => array(mp_provider_name($account['provider']))
			)));

			$summary['failed']++;

		}

		return;

	}

	$items = array();

	$by_content = array();

	foreach ($rows as $row) {

		$content_id = (string) $row['remote_id'];

		if (empty($row['found_id'])) {

			mp_sync_finish_row((int)$row['id'], 'failed', lang('The product no longer exists.'));

			$summary['failed']++;

			continue;

		}

		if ($content_id === '') {

			mp_sync_finish_row((int)$row['id'], 'failed', lang(array(
				'string' => '{var:1} is not approved on {var:2} yet, so there is no listing to update.',
				'vars'   => array((string)$row['name'], mp_provider_name($account['provider']))
			)));

			$summary['failed']++;

			continue;

		}

		$by_content[$content_id][] = (int) $row['id'];

		$items[] = mp_listing_update_item($row, mp_listing_images((int)$row['product_id'], $row['image_name']));

	}

	if (!$items) {

		return;

	}

	$ids = array();

	foreach ($by_content as $queue_ids) {

		$ids = array_merge($ids, $queue_ids);

	}

	// Leased before the call, the same as the stock batch.
	db("UPDATE marketplace_sync_queue
		SET run_after = UNIX_TIMESTAMP() + 300, updated_timestamp = UNIX_TIMESTAMP()
		WHERE id IN (" . implode(',', $ids) . ")");

	$result = mp_call($account['provider'], 'update_listings', array($account, $items));

	$summary['batches']++;

	if (!$result['ok']) {

		mp_sync_batch_failed($ids, $result, $summary);

		mp_log($account_id, 'listing_update', false, $result['error'], count($items));

		return;

	}

	// Listings the connector left out (no title, a picture on http) fail on
	// their own reason; the rest went.
	$refused = (isset($result['data']['refused']) && is_array($result['data']['refused']))
		? $result['data']['refused'] : array();

	$sent = array();

	foreach ($by_content as $content_id => $queue_ids) {

		if (isset($refused[(string)$content_id])) {

			foreach ($queue_ids as $queue_id) {

				mp_sync_finish_row($queue_id, 'failed', (string)$refused[(string)$content_id]);

				$summary['failed']++;

			}

			continue;

		}

		$sent = array_merge($sent, $queue_ids);

	}

	$task_id = isset($result['data']['task_id']) ? (string)$result['data']['task_id'] : '';

	if (!$sent || $task_id === '') {

		return;

	}

	$summary['pushed'] += isset($result['data']['sent']) ? (int)$result['data']['sent'] : count($sent);

	db("UPDATE marketplace_sync_queue
		SET status = 'sent',
			remote_task_id = '" . e($task_id) . "',
			last_error = '',
			run_after = UNIX_TIMESTAMP() + 30,
			updated_timestamp = UNIX_TIMESTAMP()
		WHERE id IN (" . implode(',', $sent) . ") AND status = 'waiting'");

	mp_account_outcome($account_id, true);

}

// How many update lines one pass reads for an account: the content update's
// own ceiling is a thousand listings.
function mp_listing_update_batch_size() {

	return 1000;

}

/* --------------------------------------------------------------- polling */

function mp_sync_poll($deadline, &$summary) {

	while (microtime(true) < $deadline) {

		$next = db_item("SELECT account_id, remote_task_id
			FROM marketplace_sync_queue
			WHERE (status = 'sent') AND (remote_task_id != '') AND (run_after <= UNIX_TIMESTAMP())
			ORDER BY run_after ASC, id ASC
			LIMIT 1");

		if (!$next) {

			return;

		}

		$account = mp_account((int)$next['account_id']);

		$task_id = (string) $next['remote_task_id'];

		if (!$account) {

			mp_sync_close_task($task_id, 'failed', lang('The marketplace account is not active.'), $summary);

			continue;

		}

		// Leased, same reason as the push.
		db("UPDATE marketplace_sync_queue
			SET run_after = UNIX_TIMESTAMP() + 300
			WHERE (remote_task_id = '" . e($task_id) . "') AND (status = 'sent')");

		$result = mp_call($account['provider'], 'read_task', array($account, $task_id));

		if (!$result['ok']) {

			mp_sync_task_unreadable($task_id, $result, $summary);

			continue;

		}

		if (empty($result['data']['finished'])) {

			// Still running over there. Asked again shortly; the wait grows with
			// the number of times it has been asked, because a batch of a
			// thousand takes minutes rather than seconds.
			db("UPDATE marketplace_sync_queue
				SET attempts = attempts + 1,
					run_after = UNIX_TIMESTAMP() + " . (int)mp_sync_poll_delay($task_id) . ",
					updated_timestamp = UNIX_TIMESTAMP()
				WHERE (remote_task_id = '" . e($task_id) . "') AND (status = 'sent')");

			mp_sync_give_up_stale($task_id, $summary);

			continue;

		}

		mp_sync_apply_results($account, $task_id, (array)$result['data']['results'], $summary);

	}

}

// How long to wait before asking about this task again.
function mp_sync_poll_delay($task_id) {

	$attempts = (int) db_value("SELECT MAX(attempts) FROM marketplace_sync_queue
		WHERE remote_task_id = '" . e($task_id) . "'");

	return min(900, 30 * max(1, $attempts));

}

// A task that has been asked about too many times is closed.
//
// Their side has either lost it or is never going to finish it, and a row that
// polls for ever is a row the operator can never clear.
function mp_sync_give_up_stale($task_id, &$summary) {

	$count = (int) db_value("SELECT COUNT(*) FROM marketplace_sync_queue
		WHERE (remote_task_id = '" . e($task_id) . "') AND (status = 'sent')
		  AND (attempts >= '" . (int)mp_max_attempts() . "')");

	if (!$count) {

		return;

	}

	db("UPDATE marketplace_sync_queue
		SET status = 'failed',
			last_error = '" . e(lang('The marketplace never finished this batch.')) . "',
			updated_timestamp = UNIX_TIMESTAMP()
		WHERE (remote_task_id = '" . e($task_id) . "') AND (status = 'sent')
		  AND (attempts >= '" . (int)mp_max_attempts() . "')");

	$summary['failed'] += $count;

}

function mp_sync_task_unreadable($task_id, $result, &$summary) {

	if (!empty($result['retry'])) {

		db("UPDATE marketplace_sync_queue
			SET attempts = attempts + 1,
				last_error = '" . e(mb_substr((string)$result['error'], 0, 500)) . "',
				run_after = UNIX_TIMESTAMP() + " . (int)mp_sync_poll_delay($task_id) . ",
				updated_timestamp = UNIX_TIMESTAMP()
			WHERE (remote_task_id = '" . e($task_id) . "') AND (status = 'sent')");

		mp_sync_give_up_stale($task_id, $summary);

		return;

	}

	mp_sync_close_task($task_id, 'failed', $result['error'], $summary);

}

// The per-SKU verdict, written back onto the rows and onto the mapping.
//
// A code the marketplace did not mention is treated as done. They answer per
// SKU for what they processed, and a line that was accepted without comment is
// the ordinary case; failing it here would report an error the marketplace
// never raised.
function mp_sync_apply_results($account, $task_id, $results, &$summary) {

	$rows = (array) db_items("SELECT marketplace_sync_queue.id, marketplace_sync_queue.product_id,
			marketplace_sync_queue.operation,
			marketplace_product_map.id AS map_id, marketplace_product_map.remote_code,
			marketplace_product_map.remote_id
		FROM marketplace_sync_queue
		LEFT JOIN marketplace_product_map
			ON  (marketplace_product_map.account_id = marketplace_sync_queue.account_id)
			AND (marketplace_product_map.product_id = marketplace_sync_queue.product_id)
		WHERE (marketplace_sync_queue.remote_task_id = '" . e($task_id) . "')
		  AND (marketplace_sync_queue.status = 'sent')");

	foreach ($rows as $row) {

		// A listing update is named by the listing's content id, and its
		// verdict is about the listing's text and pictures, not about whether
		// the product can be sold there: it closes the line and leaves the
		// mapping alone.
		$is_update = ((string)$row['operation'] === 'listing_update');

		$code = $is_update ? (string) $row['remote_id'] : (string) $row['remote_code'];

		$verdict = isset($results[$code]) ? $results[$code] : array('ok' => true, 'error' => '');

		if ($is_update) {

			mp_sync_finish_row((int)$row['id'], !empty($verdict['ok']) ? 'done' : 'failed',
				!empty($verdict['ok']) ? '' : (isset($verdict['error']) ? (string)$verdict['error'] : ''));

			$summary[!empty($verdict['ok']) ? 'done' : 'failed']++;

			continue;

		}

		if (!empty($verdict['ok'])) {

			mp_sync_finish_row((int)$row['id'], 'done', '');

			$summary['done']++;

			if ($row['map_id']) {

				db("UPDATE marketplace_product_map
					SET last_pushed = UNIX_TIMESTAMP(), last_error = '', status = 'active'
					WHERE id = '" . e((int)$row['map_id']) . "'");

			}

			continue;

		}

		$error = isset($verdict['error']) ? (string)$verdict['error'] : '';

		mp_sync_finish_row((int)$row['id'], 'failed', $error);

		$summary['failed']++;

		// The mapping is flagged, not the product. A code the marketplace
		// refuses is a mapping problem - a stock code that is not theirs, a
		// listing that was closed - and the operator fixes it on the mapping
		// screen, where the row now says why.
		if ($row['map_id']) {

			db("UPDATE marketplace_product_map
				SET last_error = '" . e(mb_substr($error, 0, 500)) . "', status = 'error'
				WHERE id = '" . e((int)$row['map_id']) . "'");

		}

	}

	mp_account_outcome((int)$account['id'], true);

}


/* ------------------------------------------------------- listing states */

// Where the listings stand on the marketplace, onto their mapping rows.
//
// A listing created from here goes to the marketplace's approval first, and a
// stock line sent for it meanwhile can be refused, which puts its mapping in
// error and stops it. So the rows whose listing has not been seen approved
// (remote_id still empty) are asked about. That includes the ones written as
// rejected: a rejection is mostly fixed in the marketplace's own seller panel
// (a document, the images), and the approval that follows has to set the row
// back to active. A rejection read again unchanged writes nothing. What each
// answer writes is mp_sync_listing_row_update().
//
// At most a hundred rows an account a pass, fifty to a call and two calls to
// each fifty (approved and unapproved), far below the marketplace's limits.
// Rows leave the set as they are approved. Rows the marketplace answers for
// with neither (never listed there, deleted there) stay, and the hundred asked
// about start at a random place in the set every pass
// (mp_sync_listing_window()), so they cannot hide the rest.
function mp_sync_listing_states($deadline, &$summary) {

	foreach (mp_accounts('', 'active') as $account) {

		if (microtime(true) >= $deadline) {

			return;

		}

		if (!mp_can($account['provider'], 'listing_status')) {

			continue;

		}

		mp_sync_listing_states_account($account, $deadline, $summary);

	}

}

function mp_sync_listing_states_account($account, $deadline, &$summary) {

	$account_id = (int) $account['id'];

	$provider_name = mp_provider_name($account['provider']);

	// A row with a line still out at the marketplace is left for a later
	// pass: the verdict the next poll applies would write over what is read
	// now, and a refusal landing on a row already seen approved would stop it
	// for good, since approved rows are not asked about again.
	$ids = array_map('intval', (array) db_values("SELECT id FROM marketplace_product_map
		WHERE (account_id = '" . e($account_id) . "')
		  AND (remote_code <> '')
		  AND (remote_id = '')
		  AND NOT EXISTS (SELECT 1 FROM marketplace_sync_queue
			WHERE (marketplace_sync_queue.account_id = marketplace_product_map.account_id)
			  AND (marketplace_sync_queue.product_id = marketplace_product_map.product_id)
			  AND (marketplace_sync_queue.status = 'sent'))
		ORDER BY id ASC"));

	// A start drawn every pass rather than one read from the clock: a job that
	// runs every ten or fifteen minutes would otherwise land on the same
	// windows every time and never reach the rest.
	$ids = mp_sync_listing_window($ids, mt_rand(), 100);

	if (!$ids) {

		return;

	}

	// The commission column is read, and so written, only once the database
	// has it (mp_sync_listing_row_update() writes what the row carries).
	$rows = (array) db_items("SELECT id, product_id, remote_code, remote_id, status, last_error"
			. (mp_commission_ready() ? ', commission' : '') . "
		FROM marketplace_product_map
		WHERE id IN (" . implode(',', $ids) . ")
		ORDER BY id ASC");

	foreach (array_chunk($rows, 50) as $chunk) {

		// Each chunk is two calls, so the time left is looked at before each.
		if (microtime(true) >= $deadline) {

			return;

		}

		$result = mp_call($account['provider'], 'listing_status', array($account, array_column($chunk, 'remote_code')));

		if (!$result['ok']) {

			mp_account_outcome($account_id, false, $result['error']);

			return;

		}

		$listings = isset($result['data']['listings']) ? (array)$result['data']['listings'] : array();

		foreach ($chunk as $row) {

			$code = (string) $row['remote_code'];

			$summary['listings_checked']++;

			$update = mp_sync_listing_row_update($row, isset($listings[$code]) ? $listings[$code] : null, $provider_name);

			if ($update === null) {

				continue;

			}

			$set = array();

			foreach ($update as $column => $value) {

				$set[] = $column . " = '" . e($value) . "'";

			}

			// Only onto the row as it was read: matched again under another
			// code meanwhile, the answer belongs to the old code.
			db("UPDATE marketplace_product_map SET " . implode(', ', $set) . "
				WHERE (id = '" . e((int)$row['id']) . "')
				  AND (remote_code = '" . e($code) . "')
				  AND (remote_id = '')");

			if (mysqli_affected_rows(db::$con) < 1) {

				continue;

			}

			// Its stock and price were not sent while it was stopped, and
			// nothing would send them again until the product changed.
			if (isset($update['status']) && $update['status'] === 'active') {

				mp_enqueue((int)$row['product_id']);

			}

		}

	}

}

// The columns a listing's state changes on its mapping row, or null when
// nothing changes.
//
// $row is the mapping (remote_id, status, last_error); $state what the
// connector's listing_status answered for its code, or null.
//
//   approved: remote_id becomes the marketplace's content id. A row in error
//   goes back to active - its stock line was refused while the listing
//   waited - while a paused row stays paused. last_error is cleared, or
//   carries a note when the listing is blacklisted, locked or archived over
//   there (information: the status is not changed for it). The commission
//   rate is written when the row was read with that column.
//
//   rejected: the row goes to error with the reasons, under a prefix
//   (mp_listing_rejected_prefix()) the screen knows it by.
//
//   pending: nothing, unless a document is needed, which is noted without
//   touching the status, and taken off a row that still sends once it is no
//   longer asked for. A row written as rejected goes back to active: the
//   listing was fixed and is in their review again.
//
//   unknown, or no answer: nothing. The listing may still be on its way
//   through their batch, and a row written as missing would stop a product
//   that is about to appear.
//
// An approval or a document note only replaces last_error on a row in error,
// or when it holds nothing or one of these texts
// (mp_sync_listing_text_is_ours()). On a row that still sends it may hold a
// push refusal (no price, a second product under the barcode) that the
// listing's state does not fix, and the next successful push clears it;
// wiping it would leave the row silent while its stock is not sent.
function mp_sync_listing_row_update($row, $state, $provider_name) {

	$kind = (is_array($state) && isset($state['state'])) ? (string)$state['state'] : 'unknown';

	$want = array();

	// Whether a push refusal already on the row outranks the text written
	// here (see above).
	$keep_refusal = false;

	if ($kind === 'approved') {

		$content_id = isset($state['content_id']) ? (string)$state['content_id'] : '';

		if ($content_id === '') {

			return null;

		}

		$note = '';

		if (!empty($state['blacklisted'])) {

			$note = lang(array('string' => 'Blacklisted on {var:1}.', 'vars' => array($provider_name)));

		} elseif (!empty($state['locked'])) {

			$reason = isset($state['lock_reason']) ? trim((string)$state['lock_reason']) : '';

			$note = ($reason !== '')
				? lang(array('string' => 'Locked on {var:1}: {var:2}', 'vars' => array($provider_name, $reason)))
				: lang(array('string' => 'Locked on {var:1}.', 'vars' => array($provider_name)));

		} elseif (!empty($state['archived'])) {

			$note = lang(array('string' => 'Archived on {var:1}.', 'vars' => array($provider_name)));

		}

		$want['remote_id'] = mb_substr($content_id, 0, 64);

		// The rate the marketplace takes on it, shown beside the listing. Only
		// a figure the column can hold (DECIMAL(6,2)), and only on a row read
		// with the column.
		$commission = isset($state['commission']) && is_numeric($state['commission']) ? (float)$state['commission'] : null;

		if (array_key_exists('commission', $row) && $commission !== null && $commission >= 0 && $commission < 10000) {

			$want['commission'] = number_format($commission, 2, '.', '');

		}

		if ((string)$row['status'] === 'error') {

			$want['status'] = 'active';

		}

		$want['last_error'] = mb_substr($note, 0, 500);

		$keep_refusal = true;

	} elseif ($kind === 'rejected') {

		$reasons = (isset($state['reasons']) && is_array($state['reasons'])) ? $state['reasons'] : array();

		$want['status'] = 'error';

		$want['last_error'] = mb_substr(mp_listing_rejected_text($provider_name,
			$reasons ? implode('; ', $reasons) : lang('no reason given')), 0, 500);

	} elseif ($kind === 'pending' && (string)$row['status'] === 'error'
		&& strpos((string)$row['last_error'], mp_listing_rejected_prefix($provider_name)) === 0) {

		// Rejected before and back in their review after a fix in the seller
		// panel: the row waits again as a new listing does.
		$want['status'] = 'active';

		$want['last_error'] = !empty($state['doc_needed']) ? mp_sync_listing_document_note($provider_name) : '';

	} elseif ($kind === 'pending' && !empty($state['doc_needed'])) {

		$want['last_error'] = mp_sync_listing_document_note($provider_name);

		$keep_refusal = true;

	} elseif ($kind === 'pending' && (string)$row['status'] !== 'error'
		&& (string)$row['last_error'] === mp_sync_listing_document_note($provider_name)) {

		// The document is in. A stopped row keeps the note, so it is not left
		// without a reason, until the approval read replaces it.
		$want['last_error'] = '';

	} else {

		return null;

	}

	if ($keep_refusal && ((string)$row['status'] !== 'error')
		&& !mp_sync_listing_text_is_ours((string)$row['last_error'], $provider_name)) {

		unset($want['last_error']);

	}

	// Only what changes is written.
	foreach ($want as $column => $value) {

		if ((string)(isset($row[$column]) ? $row[$column] : '') === (string)$value) {

			unset($want[$column]);

		}

	}

	return $want ? $want : null;

}

// What a pending listing that waits for a document says on its mapping.
function mp_sync_listing_document_note($provider_name) {

	return lang(array(
		'string' => '{var:1} needs a document for this listing; upload it in the seller panel.',
		'vars'   => array($provider_name)
	));

}

// Whether a mapping's last_error is empty or a text the listing read wrote
// (a rejection or the document note), so a new answer may replace it.
function mp_sync_listing_text_is_ours($text, $provider_name) {

	return $text === ''
		|| strpos($text, mp_listing_rejected_prefix($provider_name)) === 0
		|| $text === mp_sync_listing_document_note($provider_name);

}

// The $size ids asked about from position $start (any number, counted round
// the set): $size ids in a row that wrap round, so every id of a set larger
// than one window is in the window of some start.
function mp_sync_listing_window($ids, $start, $size) {

	$ids = array_values((array)$ids);

	$count = count($ids);

	if ($count <= $size) {

		return $ids;

	}

	$start = abs((int)$start) % $count;

	$window = array_slice($ids, $start, $size);

	if (count($window) < $size) {

		$window = array_merge($window, array_slice($ids, 0, $size - count($window)));

	}

	return $window;

}

/* --------------------------------------------------------------- orders in */

// Bring in whatever the marketplaces have sold since the last look.
//
// The cursor is per account and only moves when a pull finished cleanly. A poll
// that failed halfway through leaves it where it was, so the next pass asks for
// the same window again - which is safe, because the importer refuses a package
// it has already seen.
function mp_sync_orders($deadline, &$summary) {

	$accounts = mp_accounts('', 'active');

	foreach ($accounts as $account) {

		if (microtime(true) >= $deadline) {

			return;

		}

		if (empty($account['pull_orders']) || !mp_can($account['provider'], 'pull_orders')) {

			continue;

		}

		mp_sync_orders_account($account, $summary);

	}

}

function mp_sync_orders_account($account, &$summary) {

	$account_id = (int) $account['id'];

	$since = (int) $account['last_order_pull'];

	$result = mp_call($account['provider'], 'pull_orders', array($account, $since));

	// A failed pull can still have read some pages. They are imported, and the
	// cursor stays put so the rest is asked for again.
	$packages = isset($result['data']['packages']) ? (array)$result['data']['packages'] : array();

	$complete = !empty($result['data']['complete']);

	foreach (mp_order_cancellations_first($account, $packages) as $package) {

		$outcome = mp_import_order($account, $package);

		if (!empty($outcome['skipped'])) {

			continue;

		}

		if ($outcome['ok']) {

			$summary['orders']++;

			mp_sync_approve($account, $package, (int)$outcome['order_id']);

			continue;

		}

		$summary['order_errors']++;

	}

	if (!$result['ok']) {

		mp_account_outcome($account_id, false, $result['error']);

		return;

	}

	if ($complete) {

		// Only as far as the feed was actually read. Using "now" would skip
		// anything written while the pages were being walked.
		$read_to = isset($result['data']['read_to']) ? (int)$result['data']['read_to'] : time();

		// Compare-and-set on the cursor this pull started from: if "Try again"
		// moved the cursor back while the pages were being walked, nothing is
		// changed and the next run reads from the rolled-back cursor.
		db("UPDATE marketplace_accounts
			SET last_order_pull = '" . e($read_to) . "', updated_timestamp = UNIX_TIMESTAMP()
			WHERE (id = '" . e($account_id) . "') AND (last_order_pull = '" . e($since) . "')");

	}

	// Packages the webhook receiver imported are accepted here: a request
	// never calls the marketplace back, so they wait for this pass.
	mp_sync_approve_pending($account);

	// Parcels the shop sent with its own carrier are reported after that.
	mp_sync_ship_pending($account);

	mp_account_outcome($account_id, true);

}

// Accept the open packages that were imported but not accepted.
//
// The webhook receiver imports a package while the marketplace waits for its
// answer, and calls nobody back; a pull that later meets the same package
// skips it as already imported. So nothing else would ever accept it. Every
// row of the last day that is imported with an order, not accepted, still
// Created and without an error is a candidate; a refused acceptance writes
// last_error, so it is not asked again on every pass.
//
// Only while the account accepts orders by itself. Switching that on also
// accepts what came in during the last day and is still Created, whichever
// way it came in.
function mp_sync_approve_pending($account) {

	if (empty($account['auto_approve']) || !mp_can($account['provider'], 'approve_lines')) {

		return;

	}

	// remote_status in the query, not only in mp_sync_approvable(): fifty
	// packages that moved on (accepted by hand, shipped) would otherwise fill
	// the page on every pass and hide the Created ones behind them.
	$rows = (array) db_items("SELECT order_id, remote_order_id, remote_package_id, remote_status, lines_json
		FROM marketplace_order_map
		WHERE (account_id = '" . e((int)$account['id']) . "')
		  AND (status = 'imported')
		  AND (order_id > 0)
		  AND (approved = 0)
		  AND (last_error = '')
		  AND (remote_status = 'Created')
		  AND (imported_timestamp >= UNIX_TIMESTAMP() - 86400)
		ORDER BY id ASC
		LIMIT 50");

	foreach ($rows as $row) {

		// mp_sync_approve() asks mp_sync_approvable() itself.
		mp_sync_approve($account, mp_sync_package_from_row($row), (int)$row['order_id']);

	}

}

// The package a map row was imported from, as far as accepting it needs: its
// ids, the status it was last seen in, and its lines as they were stored.
function mp_sync_package_from_row($row) {

	$lines = json_decode(isset($row['lines_json']) ? (string)$row['lines_json'] : '', true);

	return array(
		'remote_order_id'   => isset($row['remote_order_id']) ? (string)$row['remote_order_id'] : '',
		'remote_package_id' => isset($row['remote_package_id']) ? (string)$row['remote_package_id'] : '',
		'remote_status'     => isset($row['remote_status']) ? (string)$row['remote_status'] : '',
		'lines'             => is_array($lines) ? $lines : array()
	);

}

// Report the packages the shop shipped with its own carrier.
//
// Only for an account that says it ships itself (mp_account_ships_itself());
// otherwise the marketplace's carrier collects the parcel and its number comes
// in with the package. A candidate is a package imported in the last thirty
// days, with an order, that the marketplace still has as not shipped and that
// carries no error. It is reported once the order has a parcel this shop
// recorded (order screen, API, carrier, CSV) whose tracking link somebody
// named (mp_sync_shipment_link()) - the link the order screen shows. A package
// with no such parcel yet makes no call; the next pass looks again.
//
// A package still Created that nobody accepted yet is accepted (Picking)
// first, whatever the account's auto-accept says: shipping it is the stronger
// promise, and their status page has Picking come before the later statuses.
//
// A refusal is written on the map row, which takes the package out of this
// pass for good and shows the reason on the Imported orders card; the
// operator ships it in the marketplace's panel. A failure worth repeating
// (their outage, a rate limit) writes nothing, so the next pass tries again.
//
// Only packages imported since the account began shipping itself are
// reported (mp_account_delivery_stamp()), from a minute after the stamp so a
// write racing the save, or a database clock a little behind this one, does
// not count; an older one is shipped in the marketplace's panel, and so is one
// refused before the switch and brought in after it (its import time is the
// first one). Only packages whose order has a parcel the shop
// recorded are read, oldest first, two hundred at a time and up to five
// pages, so packages still waiting for their parcel do not hide the ones
// already shipped; at most fifty calls are made in a pass.
function mp_sync_ship_pending($account) {

	// The account as it is now, not as the job loaded it before the pull: an
	// account switched back to the marketplace's carrier during the pass gets
	// that carrier's name in the method code of packages it imports, and a
	// stale copy would then still report their page with the shop's number.
	$current = isset($account['id']) ? mp_account((int)$account['id']) : null;

	if (is_array($current)) {

		$account = $current;

	}

	if (!mp_can($account['provider'], 'ship_package') || !mp_account_ships_itself($account)) {

		return;

	}

	// Before the parcel rows carry their source, a number the marketplace
	// handed over cannot be told from one the shop recorded, and theirs must
	// never be sent back as the shop's carrier.
	if (!function_exists('pg_shipment_rows_ready') || !pg_shipment_rows_ready()) {

		return;

	}

	$since = isset($account['credentials']['delivery_since']) ? (int) $account['credentials']['delivery_since'] : 0;

	if ($since <= 0) {

		return;

	}

	$default = pg_shipping_default_carrier();

	// Without a site default, a parcel with no page, no carrier and no method
	// code has only its number's shape to go by and is never sent
	// (mp_sync_shipment_link()), so it is not read either.
	$named = ($default !== '') ? '' : "
			  AND ((shipping_tracking_numbers.tracking_url <> '') OR (shipping_tracking_numbers.carrier <> '')
				OR (ship_tos.shipping_method_code <> ''))";

	$calls = 50;

	$after = 0;

	for ($page = 0; $page < 5; $page++) {

		$rows = (array) db_items("SELECT id, order_id, remote_order_id, remote_package_id, remote_status, approved, lines_json
			FROM marketplace_order_map
			WHERE (account_id = '" . e((int)$account['id']) . "')
			  AND (status = 'imported')
			  AND (order_id > 0)
			  AND (remote_status IN ('Created', 'Picking', 'Invoiced'))
			  AND (last_error = '')
			  AND (imported_timestamp >= UNIX_TIMESTAMP() - 2592000)
			  AND (imported_timestamp >= '" . e($since + 60) . "')
			  AND (id > '" . e($after) . "')
			  AND EXISTS (
				SELECT 1
				FROM ship_tos
				INNER JOIN shipping_tracking_numbers ON shipping_tracking_numbers.ship_to_id = ship_tos.id
				WHERE (ship_tos.order_id = marketplace_order_map.order_id)
				  AND (shipping_tracking_numbers.source <> 'marketplace')
				  AND (shipping_tracking_numbers.number <> '')" . $named . "
			  )
			ORDER BY id ASC
			LIMIT 200");

		foreach ($rows as $row) {

			$after = (int) $row['id'];

			if ($calls <= 0) {

				return;

			}

			$link = null;

			foreach ((array) db_items("SELECT id, shipping_method_code FROM ship_tos
				WHERE order_id = '" . e((int)$row['order_id']) . "' ORDER BY id ASC") as $ship_to) {

				$link = mp_sync_shipment_link(pg_shipment_rows((int)$ship_to['id']), (string)$ship_to['shipping_method_code'], $default);

				if ($link !== null) {

					break;

				}

			}

			if ($link === null) {

				continue;

			}

			$map_id = (int) $row['id'];

			$package = mp_sync_package_from_row($row);

			// A package the acceptance pass took already is Picking over there
			// although the row still reads Created; Picking sent again would be
			// refused, and the refusal would stop the shipment.
			if (($package['remote_status'] === 'Created') && empty($row['approved'])) {

				// Accepting and shipping are two calls; both fit in this pass or
				// neither is made, and the package waits for the next one.
				if ($calls < 2) {

					continue;

				}

				$line_ids = array();

				foreach ($package['lines'] as $line) {

					if (isset($line['remote_line_id']) && (string)$line['remote_line_id'] !== '') {

						$line_ids[] = (string)$line['remote_line_id'];

					}

				}

				$calls--;

				$accepted = mp_call($account['provider'], 'approve_lines', array($account, $line_ids, $package));

				if (!$accepted['ok']) {

					if (empty($accepted['retry'])) {

						db("UPDATE marketplace_order_map
							SET last_error = '" . e(mb_substr((string)$accepted['error'], 0, 500)) . "',
								updated_timestamp = UNIX_TIMESTAMP()
							WHERE id = '" . e($map_id) . "'");

					}

					continue;

				}

				// Written at once: if the shipment below has to wait for the next
				// pass, that pass must not send Picking for a package that is
				// Picking already - they refuse it, and the refusal would stop
				// the shipment.
				db("UPDATE marketplace_order_map
					SET remote_status = 'Picking', approved = 1, updated_timestamp = UNIX_TIMESTAMP()
					WHERE id = '" . e($map_id) . "'");

				$package['remote_status'] = 'Picking';

			}

			$calls--;

			$shipped = mp_call($account['provider'], 'ship_package', array($account, $package, $link['url']));

			if ($shipped['ok']) {

				// Shipped here at once, because the marketplace turns the package
				// Shipped as it accepts the link; the next pull confirms it and
				// brings the cargo number the marketplace gives it, which is
				// recorded as a parcel of its own (source marketplace).
				db("UPDATE marketplace_order_map
					SET remote_status = 'Shipped', approved = 1, last_error = '', updated_timestamp = UNIX_TIMESTAMP()
					WHERE id = '" . e($map_id) . "'");

				log_activity(lang(array(
					'string' => 'Order {var:1} was marked shipped on {var:2} with tracking link {var:3}.',
					'vars'   => array((string)$row['remote_order_id'], mp_provider_name($account['provider']), $link['url'])
				)), 'system');

				mp_order_announce('marketplace.order.shipped', $account, $map_id, 'Shipped', array('tracking_url' => (string)$link['url']));

				continue;

			}

			if (empty($shipped['retry'])) {

				db("UPDATE marketplace_order_map
					SET last_error = '" . e(mb_substr((string)$shipped['error'], 0, 500)) . "',
						updated_timestamp = UNIX_TIMESTAMP()
					WHERE id = '" . e($map_id) . "'");

			}

		}

		if (count($rows) < 200) {

			return;

		}

	}

}

// The parcel whose tracking link reports a package shipped, or null.
//
// $rows are an address's parcels (pg_shipment_rows()), $method its shipping
// method code, $default the site's default carrier. The first parcel with a
// number and a link is taken, the link being the one the order screen shows
// (pg_shipment_view()). A parcel the marketplace handed over (source
// marketplace) is never taken: that number is theirs, and sending it back
// would report their carrier as the shop's.
//
// The link is sent only when somebody named where it points: the row's own
// tracking page, a carrier stored on the row, the carrier the method code
// names, or - only while the method code is empty - the site's default
// carrier. Never the number's shape alone, which reads a plain Turkish number
// as UPS or FedEx, and never the default in place of a carrier the method code
// names that the carrier list does not know (Sendeo, HepsiJET): that would
// report another company's page. A shipment once reported cannot be taken
// back, so such a parcel waits until its carrier is chosen on the order
// screen.
//
// The method code and a carrier the row took from it are the shop's: the job
// reads only packages imported since the account began shipping itself, and
// those imports leave the marketplace's carrier out of the method code
// (mp_order_apply_tracking()); only the shop or its integration writes it
// (POST /orders/{id}/shipment).
//
// Answers array(number, url).
function mp_sync_shipment_link($rows, $method, $default) {

	$default = pg_shipping_carrier_text($default);

	if (!pg_shipping_carrier_exists($default)) {

		$default = '';

	}

	$by_method = pg_shipping_method_carrier((string)$method);

	foreach ((array)$rows as $row) {

		if (!is_array($row) || (isset($row['source']) && (string)$row['source'] === 'marketplace')) {

			continue;

		}

		$view = pg_shipment_view($row, $method, $default);

		if ($view['number'] === '' || $view['url'] === '') {

			continue;

		}

		$own_page = isset($row['tracking_url']) && (pg_shipment_tracking_url_clean($row['tracking_url']) !== '');

		// Without a carrier on the row, pg_shipment_view() took the method
		// code's, else the default, else the number's shape.
		if (!$own_page && !$view['named']
			&& ((trim((string)$method) !== '') ? ($by_method === '') : ($default === ''))) {

			continue;

		}

		return array('number' => $view['number'], 'url' => $view['url']);

	}

	return null;

}

// Tell the marketplace the order is being prepared, when the operator asked for
// that to happen by itself.
//
// Off by default. Accepting an order is a promise to ship it, and a promise
// made by a cron on an operator's behalf is a promise nobody read.
function mp_sync_approve($account, $package, $order_id) {

	if (empty($account['auto_approve']) || !$order_id) {

		return;

	}

	if (!mp_can($account['provider'], 'approve_lines')) {

		return;

	}

	if (!mp_sync_approvable($account, $package)) {

		return;

	}

	$line_ids = array();

	foreach ((array)$package['lines'] as $line) {

		if (isset($line['remote_line_id']) && $line['remote_line_id'] !== '') {

			$line_ids[] = $line['remote_line_id'];

		}

	}

	if (!$line_ids) {

		return;

	}

	// The package goes along: a marketplace whose status service names the
	// package and each line's quantity (Trendyol) needs more than the ids.
	$result = mp_call($account['provider'], 'approve_lines', array($account, $line_ids, $package));

	$map_id = (int) db_value("SELECT id FROM marketplace_order_map
		WHERE (account_id = '" . e((int)$account['id']) . "')
		  AND (remote_package_id = '" . e((string)$package['remote_package_id']) . "')");

	if (!$map_id) {

		return;

	}

	if ($result['ok']) {

		db("UPDATE marketplace_order_map SET approved = 1, last_error = '',
				updated_timestamp = UNIX_TIMESTAMP()
			WHERE id = '" . e($map_id) . "'");

		return;

	}

	// The order is here either way; only the acceptance failed, and that is a
	// thing the operator can do by hand on the marketplace.
	db("UPDATE marketplace_order_map
		SET last_error = '" . e(mb_substr((string)$result['error'], 0, 500)) . "',
			updated_timestamp = UNIX_TIMESTAMP()
		WHERE id = '" . e($map_id) . "'");

}

// Whether a package may be accepted.
//
// Only an open package whose status is still exactly Created, the word both
// n11 and Trendyol use for the one state acceptance moves on from. One that
// arrived shipped, delivered or cancelled is past that step; one already
// Picking or Invoiced was accepted by hand in their panel, and sending Picking
// again is refused and would fill the map row's error as if something had
// gone wrong.
function mp_sync_approvable($account, $package) {

	if (mp_order_stage($account, $package) !== 'open') {

		return false;

	}

	return isset($package['remote_status']) && ((string)$package['remote_status'] === 'Created');

}

/* ---------------------------------------------------------------- closing */

function mp_sync_finish_row($id, $status, $error) {

	db("UPDATE marketplace_sync_queue
		SET status = '" . e($status) . "',
			last_error = '" . e(mb_substr((string)$error, 0, 500)) . "',
			updated_timestamp = UNIX_TIMESTAMP()
		WHERE id = '" . e((int)$id) . "'");

}

function mp_sync_close_task($task_id, $status, $error, &$summary) {

	$count = (int) db_value("SELECT COUNT(*) FROM marketplace_sync_queue
		WHERE (remote_task_id = '" . e($task_id) . "') AND (status = 'sent')");

	db("UPDATE marketplace_sync_queue
		SET status = '" . e($status) . "',
			last_error = '" . e(mb_substr((string)$error, 0, 500)) . "',
			updated_timestamp = UNIX_TIMESTAMP()
		WHERE (remote_task_id = '" . e($task_id) . "') AND (status = 'sent')");

	$summary['failed'] += $count;

}

function mp_sync_close_account_rows($account_id, $error, &$summary) {

	$count = (int) db_value("SELECT COUNT(*) FROM marketplace_sync_queue
		WHERE (account_id = '" . e((int)$account_id) . "') AND (status = 'waiting')");

	db("UPDATE marketplace_sync_queue
		SET status = 'failed',
			last_error = '" . e(mb_substr((string)$error, 0, 500)) . "',
			updated_timestamp = UNIX_TIMESTAMP()
		WHERE (account_id = '" . e((int)$account_id) . "') AND (status = 'waiting')");

	$summary['failed'] += $count;

}

// Rows whose mapping was removed while they waited.
function mp_sync_close_unmapped($account_id, &$summary) {

	$count = (int) db_value("SELECT COUNT(*) FROM marketplace_sync_queue
		WHERE (account_id = '" . e((int)$account_id) . "')
		  AND (status = 'waiting')
		  AND (run_after <= UNIX_TIMESTAMP())");

	if (!$count) {

		return;

	}

	db("UPDATE marketplace_sync_queue
		SET status = 'failed',
			last_error = '" . e(lang('This product is no longer matched to the marketplace.')) . "',
			updated_timestamp = UNIX_TIMESTAMP()
		WHERE (account_id = '" . e((int)$account_id) . "')
		  AND (status = 'waiting')
		  AND (run_after <= UNIX_TIMESTAMP())");

	$summary['failed'] += $count;

}

// Finished rows are kept long enough to be looked at, then swept.
//
// Done rows go after a day - nobody reads a successful send - and failures stay
// a week, because a failure is the thing somebody comes back to on Monday.
function mp_sync_sweep() {

	db("DELETE FROM marketplace_sync_queue
		WHERE (status = 'done') AND (updated_timestamp < '" . (time() - 86400) . "') LIMIT 5000");

	db("DELETE FROM marketplace_sync_queue
		WHERE (status = 'failed') AND (updated_timestamp < '" . (time() - 604800) . "') LIMIT 5000");

}
