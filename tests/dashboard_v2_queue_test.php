<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the new dashboard's Next up card
 * (includes/dashboard/v2/cards/queue.php): which counts make a row, their
 * order, their actions and the row limit.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_DASHBOARD_V2')) {
	define('PG_DASHBOARD_V2', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/queue.php');

function test_dashboard_v2_queue_nothing_waiting_makes_no_rows()
{
	pg_assert_same(array(), pg_dashboard_v2_queue_rows(array()), 'no sources');
	pg_assert_same(array(), pg_dashboard_v2_queue_rows(array(
		'forms'    => array('count' => 0, 'oldest' => 0, 'url' => ''),
		'comments' => array('count' => 0),
		'seo'      => array('critical' => 0, 'average' => 82),
		'a11y'     => null,
	)), 'only zero counts and a source the person may not see');
}

function test_dashboard_v2_queue_rows_most_urgent_first()
{
	$rows = pg_dashboard_v2_queue_rows(array(
		'forms'     => array('count' => 3, 'oldest' => 0, 'url' => 'view_submitted_forms.php?x=1'),
		'comments'  => array('count' => 1),
		'seo'       => array('critical' => 2, 'average' => 61),
		'a11y'      => array('issues' => 6, 'pages' => 1),
		'overdue'   => array('count' => 1, 'open' => 4),
		'approvals' => array('count' => 2, 'url' => ''),
	));

	pg_assert_same(array('team', 'content', 'team', 'form', 'content', 'content'), array_column($rows, 'module'), 'modules in order');
	pg_assert_same(array('bad', 'bad', 'warn', 'warn', 'warn', 'plain'), array_column($rows, 'tone'), 'the urgent ones first');
	pg_assert_same('workspace_tasks.php', $rows[0]['url'], 'overdue tasks open the task list');
	pg_assert_same('view_pages.php?filter=seo_critical', $rows[1]['url'], 'critical pages open the filtered list');
	pg_assert_same('workspace.php', $rows[2]['url'], 'approvals without an address open the Workspace');
	pg_assert_same('view_submitted_forms.php?x=1', $rows[3]['url'], 'forms open the list on the counted period');
	pg_assert_same('1 comment waiting for moderation', $rows[4]['text'], 'one comment');
	pg_assert_same('on 1 page', $rows[5]['note'], 'one page');

	foreach ($rows as $row) {
		pg_assert_true(($row['action'] !== '') && ($row['tag'] !== ''), 'every row has a tag and an action');
	}
}

function test_dashboard_v2_queue_keeps_to_the_limit()
{
	$rows = pg_dashboard_v2_queue_rows(array(
		'seo'       => array('critical' => 2, 'average' => 61),
		'overdue'   => array('count' => 5, 'open' => 9),
		'approvals' => array('count' => 1, 'url' => 'workspace.php?approval=7'),
	), 2);

	pg_assert_same(2, count($rows), 'two rows');
	pg_assert_same('5 overdue tasks', $rows[0]['text'], 'plural');
	pg_assert_same('9 open in all', $rows[0]['note'], 'the open count');
}

function test_dashboard_v2_queue_shop_accounting_and_drafts()
{
	$rows = pg_dashboard_v2_queue_rows(array(
		'refunds'   => array('count' => 2, 'owed' => '₺240,00', 'late' => 0, 'url' => 'view_orders.php?status=refund_pending'),
		'unshipped' => array('count' => 1, 'packages' => 3, 'late' => 1, 'url' => 'view_orders.php?shipping_status=unshipped'),
		'stock'     => array('out' => 4, 'low' => 2, 'threshold' => 5),
		'invoices'  => array('count' => 3, 'owed' => '₺2.400,00', 'days' => 12),
		'drafts'    => array('count' => 1, 'title' => 'About us', 'url' => 'edit_page.php?id=9'),
	), 10);

	pg_assert_same(array('shop', 'shop', 'erp', 'shop', 'shop', 'content'), array_column($rows, 'module'), 'modules, the urgent ones first');
	pg_assert_same(array('bad', 'bad', 'bad', 'warn', 'warn', 'plain'), array_column($rows, 'tone'), 'tones in order');
	pg_assert_same('1 order not shipped', $rows[0]['text'], 'a late order is urgent');
	pg_assert_same('Waiting for more than two days', $rows[0]['note'], 'the late note');
	pg_assert_same('view_orders.php?shipping_status=unshipped', $rows[0]['url'], 'opens the unshipped list');
	pg_assert_same('4 products out of stock', $rows[1]['text'], 'out of stock');
	pg_assert_same('view_products.php?filter=out_of_stock_products', $rows[1]['url'], 'opens the out of stock filter');
	pg_assert_same('3 overdue invoices', $rows[2]['text'], 'overdue invoices');
	pg_assert_same('₺2.400,00 past due, the oldest by 12 days', $rows[2]['note'], 'money and days');
	pg_assert_same('erp_invoices.php?filter=overdue&direction=sales', $rows[2]['url'], 'opens the overdue invoices');
	pg_assert_same('2 refunds waiting', $rows[3]['text'], 'refunds not yet late');
	pg_assert_same('₺240,00 to pay back', $rows[3]['note'], 'the money owed');
	pg_assert_same('5 or fewer left', $rows[4]['note'], 'the threshold');
	pg_assert_same('“About us” is ready to publish', $rows[5]['text'], 'one draft is named');
	pg_assert_same('edit_page.php?id=9', $rows[5]['url'], 'one draft opens in its editor');
}

function test_dashboard_v2_queue_drafts_counted_and_stock_quiet()
{
	$rows = pg_dashboard_v2_queue_rows(array(
		'stock'  => array('out' => 0, 'low' => 0, 'threshold' => 0),
		'drafts' => array('count' => 3, 'title' => '', 'url' => ''),
	));

	pg_assert_same(1, count($rows), 'only the drafts');
	pg_assert_same('3 draft pages ready to publish', $rows[0]['text'], 'several drafts are counted');
	pg_assert_same('view_system_styles.php', $rows[0]['url'], 'several drafts open the Visual Editor');
}

function test_dashboard_v2_queue_tone_order_keeps_source_order()
{
	$rows = pg_dashboard_v2_queue_rows(array(
		'a11y'      => array('issues' => 1, 'pages' => 1),
		'approvals' => array('count' => 1, 'url' => ''),
		'refunds'   => array('count' => 1, 'owed' => '1', 'late' => 1, 'url' => ''),
		'overdue'   => array('count' => 1, 'open' => 1),
	));

	pg_assert_same(array('bad', 'bad', 'warn', 'plain'), array_column($rows, 'tone'), 'bad, warn, plain');
	pg_assert_same(array('team', 'shop', 'team', 'content'), array_column($rows, 'module'), 'a tone keeps the order of its sources');
}
