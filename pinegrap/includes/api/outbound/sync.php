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
		'orders' => 0, 'order_errors' => 0, 'listed' => 0);

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

	$summary['pushed'] += count($payload);

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

		db("UPDATE marketplace_sync_queue
			SET attempts = attempts + 1,
				last_error = '" . $error . "',
				run_after = UNIX_TIMESTAMP() + " . (int)mp_backoff_seconds(1) . ",
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

		$ids = (array) db_items("SELECT marketplace_sync_queue.id
			FROM marketplace_sync_queue
			INNER JOIN products_groups_xref ON products_groups_xref.product = marketplace_sync_queue.product_id
			WHERE (marketplace_sync_queue.account_id = '" . e($account_id) . "')
			  AND (marketplace_sync_queue.operation = 'create')
			  AND (marketplace_sync_queue.status = 'waiting')
			  AND (marketplace_sync_queue.run_after <= UNIX_TIMESTAMP())
			  AND (products_groups_xref.product_group = '" . e($group_id) . "')");

		$queue_ids = array();

		foreach ($ids as $id_row) {

			$queue_ids[] = (int) $id_row['id'];

		}

		if (!$queue_ids) {

			continue;

		}

		// Leased before the call, the same as the stock batch.
		db("UPDATE marketplace_sync_queue
			SET run_after = UNIX_TIMESTAMP() + 300, updated_timestamp = UNIX_TIMESTAMP()
			WHERE id IN (" . implode(',', $queue_ids) . ")");

		$assembled = mp_listing_items($account, $group_id);

		if (!$assembled['ok']) {

			mp_sync_batch_failed($queue_ids, mp_error($assembled['error']), $summary);

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
			marketplace_product_map.id AS map_id, marketplace_product_map.remote_code
		FROM marketplace_sync_queue
		LEFT JOIN marketplace_product_map
			ON  (marketplace_product_map.account_id = marketplace_sync_queue.account_id)
			AND (marketplace_product_map.product_id = marketplace_sync_queue.product_id)
		WHERE (marketplace_sync_queue.remote_task_id = '" . e($task_id) . "')
		  AND (marketplace_sync_queue.status = 'sent')");

	foreach ($rows as $row) {

		$code = (string) $row['remote_code'];

		$verdict = isset($results[$code]) ? $results[$code] : array('ok' => true, 'error' => '');

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

	$result = mp_call($account['provider'], 'pull_orders',
		array($account, (int)$account['last_order_pull']));

	// A failed pull can still have read some pages. They are imported, and the
	// cursor stays put so the rest is asked for again.
	$packages = isset($result['data']['packages']) ? (array)$result['data']['packages'] : array();

	$complete = !empty($result['data']['complete']);

	foreach ($packages as $package) {

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

		db("UPDATE marketplace_accounts
			SET last_order_pull = '" . e($read_to) . "', updated_timestamp = UNIX_TIMESTAMP()
			WHERE id = '" . e($account_id) . "'");

	}

	mp_account_outcome($account_id, true);

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

	$line_ids = array();

	foreach ((array)$package['lines'] as $line) {

		if (isset($line['remote_line_id']) && $line['remote_line_id'] !== '') {

			$line_ids[] = $line['remote_line_id'];

		}

	}

	if (!$line_ids) {

		return;

	}

	$result = mp_call($account['provider'], 'approve_lines', array($account, $line_ids));

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
