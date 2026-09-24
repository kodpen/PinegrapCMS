<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Panel notifications: who may see one, and who has read it.
//
// The four notification endpoints in api.php each carried their own copy of the
// same visibility ladder - which meant four places to change when a rule moved,
// and four chances for them to disagree about what a person is allowed to see.
// The ladder lives here once. The push sender asks the same question of the
// same function, so a notification that never reaches a person's bell can never
// reach their phone either.
//
// Loaded on demand from api.php, which has already run init.php.
if (!defined('PG_FUNCTIONS_DIR')) {
	exit;
}

// Whether read state can be kept per person yet.
//
// Files land before the schema does: software_update.php replaces the code and
// runs the upgrade afterwards, and an operator who copies files by hand may not
// run it for days. Every reader below therefore has to answer on the previous
// schema too, where the only thing available is the single notifications.readed
// flag. Probed once per request; information_schema rather than SHOW TABLES
// LIKE, because '_' is a wildcard in LIKE.
function pg_notification_reads_available()
{
	static $available = null;

	if ($available === null) {
		$count = db_value("SELECT COUNT(*) FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = DATABASE()
			AND TABLE_NAME = 'notification_reads'");
		$available = ((int) $count > 0);
	}

	return $available;
}

// Whether a notification can be addressed to one person yet (4.83):
// notifications.target_user_id, and reference_id pointing at what the row is
// about in the module that wrote it. Probed once per request.
function pg_notification_targets_available()
{
	static $available = null;

	if ($available === null) {
		$count = db_value("SELECT COUNT(*) FROM information_schema.COLUMNS
			WHERE TABLE_SCHEMA = DATABASE()
			AND TABLE_NAME = 'notifications'
			AND COLUMN_NAME = 'target_user_id'");
		$available = ((int) $count > 0);
	}

	return $available;
}

// Can this person see this notification?
//
// The rights are passed in rather than read from the session, because the
// question is asked about two different people: the one whose bell is being
// drawn, and the ones whose devices are about to be woken by something they did
// not do. The two wrappers below fill them in from either source.
//
// The ecommerce branch reads as a single test on purpose. What the endpoints
// carried was an outer gate (role below 3, or either ecommerce permission)
// wrapped around an inner one (manage ecommerce), and the inner test is the
// narrower of the two - anything that satisfies it satisfies the outer gate as
// well, so the pair only ever answered what the inner test answered.
function pg_notification_visible($notification, $rights)
{
	$action = isset($notification['action']) ? $notification['action'] : '';

	// A row addressed to one person is theirs alone, whatever its kind.
	$target = isset($notification['target_user_id']) ? (int) $notification['target_user_id'] : 0;

	if (($target > 0) && ($target !== (int) $rights['id'])) {
		return false;
	}

	// A mention, a task handed over, an invitation to a channel: always
	// addressed, and only while the workspace is switched on.
	if ($action == 'workspace') {
		return (($target > 0) && defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED);
	}

	if (($action == 'new_order') || ($action == 'out_stock')) {
		return ((ECOMMERCE === true) && $rights['manage_ecommerce']);
	}

	if ($action == 'form_submited') {
		return ((FORMS === true) && $rights['manage_forms']);
	}

	if ($action == 'software_update') {
		return ($rights['role'] < 3);
	}

	if ($action == 'new_comment') {
		return pg_notification_comment_visible($notification, $rights);
	}

	// An overdue-receivable digest is for whoever may use the ERP; with the
	// module off the row stays in the table but reaches nobody.
	if ($action == 'erp_overdue') {
		return ((defined('ERP_ENABLED') && ERP_ENABLED) && $rights['manage_erp']);
	}

	// Money in is for whoever may open the receipt: the ERP with its cash
	// right. A low stock notice is for whoever may use the ERP.
	if ($action == 'erp_money') {
		return ((defined('ERP_ENABLED') && ERP_ENABLED) && $rights['manage_erp']
			&& (isset($rights['manage_erp_cash']) ? $rights['manage_erp_cash'] : $rights['manage_erp']));
	}

	if ($action == 'erp_low_stock') {
		return ((defined('ERP_ENABLED') && ERP_ENABLED) && $rights['manage_erp']);
	}

	// Anything else - a message written by the software itself - is for
	// everybody who can sign in.
	return true;
}

// The signed-in person, as the panel endpoints ask it.
function pg_notification_visible_to($notification, $user)
{
	return pg_notification_visible($notification, array(
		'id'               => $user['id'],
		'role'             => $user['role'],
		'manage_ecommerce' => (defined('USER_MANAGE_ECOMMERCE') && USER_MANAGE_ECOMMERCE),
		'manage_forms'     => (($user['role'] < 3) || ($user['manage_forms'] == true)),
		'manage_erp'       => (($user['role'] < 3) || !empty($user['manage_erp'])),
		'manage_erp_cash'  => (($user['role'] < 3) || !empty($user['manage_erp_cash']))
	));
}

// Somebody else, from their row in the user table. The two permissions are
// derived here exactly the way initialize_user() derives the constants, so an
// account that would see the notification in the panel is the account that gets
// it on a device.
function pg_notification_visible_to_user($notification, $user_row)
{
	$role = (int) $user_row['role'];

	return pg_notification_visible($notification, array(
		'id'               => (int) $user_row['id'],
		'role'             => $role,
		'manage_ecommerce' => (($role < 3) || ($user_row['manage_ecommerce'] == 'yes')),
		'manage_forms'     => (($role < 3) || ($user_row['manage_forms'] == 'yes')),
		'manage_erp'       => (($role < 3) || ((int) ($user_row['manage_erp'] ?? 0) === 1)),
		'manage_erp_cash'  => (($role < 3) || ((int) ($user_row['manage_erp_cash'] ?? 0) === 1))
	));
}

// A comment notification is visible to whoever may edit the page it was left
// on. Cached per request and per person: the list is walked twice on a dropdown
// open, and once per account when a device notification is being prepared.
function pg_notification_comment_visible($notification, $rights)
{
	static $access = array();

	$comment_id = isset($notification['comment_id']) ? (string) $notification['comment_id'] : '';
	$key = $rights['id'] . ':' . $comment_id;

	if (!isset($access[$key])) {

		$folder_id = db_value("SELECT page.page_folder
			FROM comments
			LEFT JOIN page ON page.page_id = comments.page_id
			WHERE comments.id = '" . escape($comment_id) . "'");

		$access[$key] = (pg_folder_edit_access($folder_id, $rights['id'], $rights['role']) == true);
	}

	return $access[$key];
}

// The notification ids this person has already read. One query per request; the
// dropdown asks about every row it draws.
function pg_notification_read_ids($user_id)
{
	static $cache = array();

	$user_id = (int) $user_id;

	if (!isset($cache[$user_id])) {
		$read = array();

		if (pg_notification_reads_available()) {
			$rows = db_values("SELECT notification_id FROM notification_reads
				WHERE user_id = '" . $user_id . "'");

			foreach ($rows as $notification_id) {
				$read[(int) $notification_id] = true;
			}
		}

		$cache[$user_id] = $read;
	}

	return $cache[$user_id];
}

// The rows this person has not read yet. The filter is done in SQL rather than
// in PHP because the badge is polled every twenty seconds by every open panel,
// and the table keeps everything the site has ever announced.
function pg_notification_unread_rows($user_id, $full_row = false)
{
	$user_id = (int) $user_id;

	// The badge only needs enough to run the visibility ladder. Anything that
	// has to render the notification asks for the whole row instead.
	$columns = ($full_row) ? 'notifications.*' : 'notifications.id, notifications.action, notifications.comment_id';

	// Rows addressed to somebody else are not even read.
	$target_filter = '';

	if (pg_notification_targets_available()) {
		$columns .= ($full_row) ? '' : ', notifications.target_user_id, notifications.reference_id';
		$target_filter = " AND notifications.target_user_id IN (0, '" . $user_id . "')";
	}

	if (!pg_notification_reads_available()) {
		return db_items("SELECT " . (($full_row) ? '*' : 'id, action, comment_id, readed') . " FROM notifications
			WHERE readed = 0
			ORDER BY timestamp DESC");
	}

	return db_items("SELECT " . $columns . "
		FROM notifications
		LEFT JOIN notification_reads
			ON notification_reads.notification_id = notifications.id
			AND notification_reads.user_id = '" . $user_id . "'
		WHERE notification_reads.notification_id IS NULL" . $target_filter . "
		ORDER BY notifications.timestamp DESC");
}

// What a notification says and where it points.
//
// The wording is not a property of the row - the row carries an order number or
// a reference code, and the sentence around it is built here - so both the
// dropdown and the push sender have to agree on it. They agree by asking the
// same function.
//
// title, description and details are returned as HTML: the dropdown inserts
// them as markup (details carries a <br/>), so every value that came from the
// row is escaped here. The row's title is whatever the creator passed - a
// comment message typed by an anonymous visitor, a product name, a billing
// name - and must never reach the panel as markup. pg_notification_body()
// strips the tags and decodes the entities again for a plain-text banner.
function pg_notification_display($notification)
{
	$action = isset($notification['action']) ? $notification['action'] : '';

	$display = array(
		'title'       => isset($notification['title']) ? h($notification['title']) : '',
		'description' => '',
		'details'     => '',
		'url'         => '#!',
		'action'      => 'custom',
		'icon'        => '',
		'badge'       => ''
	);

	if ($action == 'new_order') {

		$display['title']   = lang('Congratulations! There is a new successful order.');
		$display['details'] = lang('Order Number') . ': #' . h($notification['title']) . '<br/>' . lang('Total') . ':' . h($notification['order_total']);
		$display['url']     = 'view_order.php?id=' . (int) $notification['order_id'];
		$display['icon']     = 'assets/images/notification-order.png';
		$display['badge']    = 'assets/images/notification-order-badge.png';
		$display['action']  = $action;

	} elseif ($action == 'out_stock') {

		$display['title']   = lang('A product out of stock by purchased.');
		$display['details'] = h($notification['title']);
		$display['url']     = 'edit_product.php?id=' . (int) $notification['product_id'];
		$display['icon']     = 'assets/images/notification-order.png';
		$display['badge']    = 'assets/images/notification-order-badge.png';
		$display['action']  = $action;

	} elseif ($action == 'form_submited') {

		$display['title']   = lang('A custom form was submitted.');
		$display['details'] = lang('Reference Code') . ':' . h($notification['title']);
		$display['url']     = 'edit_submitted_form.php?id=' . (int) $notification['form_id'];
		$display['icon']     = 'assets/images/notification-form.png';
		$display['badge']    = 'assets/images/notification-form-badge.png';
		$display['action']  = $action;

	} elseif ($action == 'software_update') {

		$display['title']       = lang('Software update available');
		$display['description'] = lang('A new security and development update is available for your software.');
		$display['url']         = 'software_update.php';
		$display['action']      = $action;

	} elseif ($action == 'new_comment') {

		// The label a page gives its comments is what the operator called them,
		// so it is read from the page the comment sits on rather than assumed.
		$comment = db_item("SELECT
				comments.id AS id,
				comments.page_id,
				page.comments_label
			FROM comments
			LEFT JOIN page ON page.page_id = comments.page_id
			WHERE comments.id = '" . escape($notification['comment_id']) . "'");

		$comments_label = ($comment) ? $comment['comments_label'] : '';

		$display['title']       = lang(array('string' => 'There is a new {var:1} exist.', 'vars' => array(h($comments_label))));
		$display['description'] = h($comments_label) . ': ' . h($notification['title']);
		$display['url']         = 'edit_comment.php?id=' . (($comment) ? (int) $comment['id'] : '');
		$display['icon']     = 'assets/images/notification-comment.png';
		$display['badge']    = 'assets/images/notification-comment-badge.png';
		$display['action']      = $action;

	} elseif ($action == 'workspace') {

		// The row points at the reader's workspace inbox row, which says what
		// happened and where; the row's own title is what it said when it was
		// written, kept for when the inbox row is gone.
		$display['action'] = $action;
		$display['icon']   = 'assets/images/notification-chat.png';
		$display['badge']  = 'assets/images/notification-chat-badge.png';

		if (defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED && ((int) ($notification['reference_id'] ?? 0) > 0)) {

			require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

			$described = ws_ready() ? ws_bell_describe((int) $notification['reference_id'], (int) ($notification['target_user_id'] ?? 0)) : null;

			if (is_array($described)) {
				$display['title']       = h($described['title']);
				$display['description'] = h($described['body']);
				$display['url']         = $described['url'];
			}
		}

	} elseif ($action == 'erp_overdue') {

		// The row carries the counts and the formatted total the way an order
		// row carries its number and total: title is the number of documents
		// announced for the first time, form_id the number announced for the
		// second and last time, order_total the total of both. The sentence
		// around them is built here, and the digest mail opens with the same
		// one.
		$first  = (int) $notification['title'];
		$second = (int) ($notification['form_id'] ?? 0);
		$total  = h($notification['order_total']);

		if (($first > 0) && ($second > 0)) {
			$sentence = lang(array('string' => '{var:1} receivable(s) passed the reminder threshold and {var:2} are still open a month past it, {var:3} in total.', 'vars' => array($first, $second, $total)));
		} elseif ($second > 0) {
			$sentence = lang(array('string' => '{var:1} receivable(s) are still open a month past the reminder threshold, {var:2} in total.', 'vars' => array($second, $total)));
		} else {
			$sentence = lang(array('string' => '{var:1} receivable(s) passed the reminder threshold, {var:2} in total.', 'vars' => array($first, $total)));
		}

		$display['title']       = lang('Overdue receivables');
		$display['description'] = $sentence;
		$display['url']         = 'erp_invoices.php?filter=overdue&direction=sales';
		$display['icon']        = 'assets/images/notification-general.png';
		$display['badge']       = 'assets/images/notification-general-badge.png';
		$display['action']      = $action;

	} elseif ($action == 'erp_money') {

		// title is the account, form_id the receipt, order_total the amount
		// as it was received (includes/erp/alerts.php).
		$display['title']       = lang('Money received');
		$display['description'] = lang(array('string' => '{var:1} from {var:2}', 'vars' => array(h($notification['order_total']), h($notification['title']))));
		$display['url']         = 'erp_receipt.php?id=' . (int) ($notification['form_id'] ?? 0);
		$display['icon']        = 'assets/images/notification-order.png';
		$display['badge']       = 'assets/images/notification-order-badge.png';
		$display['action']      = $action;

	} elseif ($action == 'erp_low_stock') {

		// title is the number of products newly at or below their minimum;
		// product_id the first of them, named when it is the only one.
		$count = (int) $notification['title'];
		$name  = '';

		if (($count === 1) && ((int) ($notification['product_id'] ?? 0) > 0)) {
			$product = db_item("SELECT name, short_description FROM products WHERE id = '" . (int) $notification['product_id'] . "' LIMIT 1");
			$name = is_array($product) ? ((trim((string) $product['short_description']) !== '') ? (string) $product['short_description'] : (string) $product['name']) : '';
		}

		$display['title']       = lang('Stock running low');
		$display['description'] = ($name !== '')
			? lang(array('string' => '{var:1} is at or below its minimum stock.', 'vars' => array(h($name))))
			: lang(array('string' => '{var:1} product(s) dropped to or below their minimum stock.', 'vars' => array($count)));
		$display['url']         = 'erp_stock_minimums.php?show=low';
		$display['icon']        = 'assets/images/notification-order.png';
		$display['badge']       = 'assets/images/notification-order-badge.png';
		$display['action']      = $action;
	}

	return $display;
}

// The one-line body a device notification carries. The dropdown can show markup
// but a system banner cannot, so the richer of the two fields is flattened.
function pg_notification_body($display)
{
	$body = ($display['description'] != '') ? $display['description'] : $display['details'];
	$body = str_ireplace(array('<br>', '<br/>', '<br />'), ' ', $body);

	return trim(html_entity_decode(strip_tags($body), ENT_QUOTES, 'UTF-8'));
}

function pg_notification_is_read($notification, $user_id)
{
	// Previous schema: the shared flag is all there is.
	if (!pg_notification_reads_available()) {
		return (isset($notification['readed']) && ((int) $notification['readed'] === 1));
	}

	$read = pg_notification_read_ids($user_id);

	return isset($read[(int) $notification['id']]);
}

// Marks notifications read for one person. Takes the whole list the dropdown
// drew rather than one id at a time: opening the bell on a busy site would
// otherwise be one round trip per row.
//
// INSERT IGNORE against the composite primary key is what makes a second tab
// opening the same dropdown a no-op instead of a duplicate key error.
function pg_notification_mark_read($notification_ids, $user_id)
{
	$user_id = (int) $user_id;

	if (!is_array($notification_ids)) {
		$notification_ids = array($notification_ids);
	}

	$values = array();

	foreach ($notification_ids as $notification_id) {
		$notification_id = (int) $notification_id;

		if ($notification_id > 0) {
			$values[$notification_id] = "('" . $notification_id . "', '" . $user_id . "', '" . time() . "')";
		}
	}

	if (!$values) {
		return;
	}

	if (!pg_notification_reads_available()) {
		// Previous schema: the flag is shared, which is the behaviour this
		// version replaces, but it is the only one the old table can express.
		db("UPDATE notifications SET readed = '1'
			WHERE id IN (" . implode(',', array_keys($values)) . ")");
		return;
	}

	db("INSERT IGNORE INTO notification_reads (notification_id, user_id, timestamp)
		VALUES " . implode(',', $values));

	// A workspace row read in the bell is read in the workspace's inbox too.
	if (defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED && pg_notification_targets_available()) {

		$references = db_values("SELECT reference_id FROM notifications
			WHERE action = 'workspace' AND target_user_id = '" . $user_id . "'
			AND id IN (" . implode(',', array_keys($values)) . ")");

		if (!empty($references)) {
			db("UPDATE ws_inbox SET read_at = '" . time() . "'
				WHERE read_at = 0 AND user_id = '" . $user_id . "'
				AND id IN (" . implode(',', array_map('intval', $references)) . ")");
		}
	}
}

// Back to unread, for this person only. No row means unread, so there is
// nothing to write - the row is simply taken away.
function pg_notification_mark_unread($notification_id, $user_id)
{
	$notification_id = (int) $notification_id;
	$user_id = (int) $user_id;

	if ($notification_id < 1) {
		return;
	}

	if (!pg_notification_reads_available()) {
		db("UPDATE notifications SET readed = '0' WHERE id = '" . $notification_id . "'");
		return;
	}

	db("DELETE FROM notification_reads
		WHERE notification_id = '" . $notification_id . "'
		AND user_id = '" . $user_id . "'");
}

// Deleting a notification takes its read rows with it. There is no foreign key
// on the table - the software still runs on installations where notifications
// is MyISAM - so the cleanup is explicit and belongs everywhere a notification
// is removed.
function pg_notification_delete($notification_id)
{
	$notification_id = (int) $notification_id;

	if ($notification_id < 1) {
		return;
	}

	db("DELETE FROM notifications WHERE id = '" . $notification_id . "'");

	if (pg_notification_reads_available()) {
		db("DELETE FROM notification_reads WHERE notification_id = '" . $notification_id . "'");
	}
}

// A new account starts with the history already read. Without this the first
// sign-in of a person hired today opens onto every notification the site has
// kept, none of which is theirs to act on.
function pg_notification_seed_user($user_id)
{
	$user_id = (int) $user_id;

	if (($user_id < 1) || (!pg_notification_reads_available())) {
		return;
	}

	db("INSERT IGNORE INTO notification_reads (notification_id, user_id, timestamp)
		SELECT id, '" . $user_id . "', '" . time() . "' FROM notifications");
}
