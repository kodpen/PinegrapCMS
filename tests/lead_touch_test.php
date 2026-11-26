<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the first and last touch of a lead or a customer and the Lead
 * sources report (includes/fn/lead_touch.php): which visits are the touches,
 * when a contact becomes a lead or a customer, the report's choices, groups,
 * totals and shares; and the pins: the acts' save paths, the report screen,
 * its Excel and its menu entry.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/lead_touch.php');

function test_lead_touch_pick_first_and_last_before_the_act()
{
	$visits = array(
		array('id' => 7, 'started_at' => 300),
		array('id' => 3, 'started_at' => 100),
		array('id' => 9, 'started_at' => 900),
		array('id' => 5, 'started_at' => 300),
	);

	pg_assert_same(array('first' => 3, 'last' => 7), pg_lead_touch_pick($visits, 500), 'the visit after the act is no touch; same second: the higher id is the later');
	pg_assert_same(array('first' => 3, 'last' => 9), pg_lead_touch_pick($visits, 900), 'a visit started at the act counts');
	pg_assert_same(array('first' => 3, 'last' => 3), pg_lead_touch_pick($visits, 100), 'one visit is both touches');
	pg_assert_same(null, pg_lead_touch_pick($visits, 50), 'no visit before the act');
	pg_assert_same(null, pg_lead_touch_pick(array(), 500), 'no visit at all');
	pg_assert_same(null, pg_lead_touch_pick(array(array('id' => 4, 'started_at' => 0)), 500), 'a visit without a time');
}

function test_lead_touch_acts()
{
	pg_assert_same(PG_LEAD_JOURNEY_LEAD, pg_lead_touch_act_kind('form'));
	pg_assert_same(PG_LEAD_JOURNEY_LEAD, pg_lead_touch_act_kind('booking'));
	pg_assert_same(PG_LEAD_JOURNEY_CUSTOMER, pg_lead_touch_act_kind('order'));
	pg_assert_same(PG_LEAD_JOURNEY_CUSTOMER, pg_lead_touch_act_kind('invoice'));
	pg_assert_same(0, pg_lead_touch_act_kind('sign_in'), 'a sign-in makes nobody a lead');
	pg_assert_same(null, pg_lead_journey_row_kind(array('kind' => PG_LEAD_JOURNEY_LEAD, 'detail' => 'form')), 'no journey row of its own: the form is one');
	pg_assert_same(null, pg_lead_journey_row_kind(array('kind' => PG_LEAD_JOURNEY_CUSTOMER, 'detail' => 'order')));
}

function test_lead_touch_verdict_order()
{
	$facts = array('kind' => PG_LEAD_JOURNEY_LEAD, 'contact_id' => 12, 'staff' => false, 'marked' => false, 'earlier' => false, 'touches' => array('first' => 1, 'last' => 2));

	pg_assert_same('mark', pg_lead_touch_verdict($facts));
	pg_assert_same('not_an_act', pg_lead_touch_verdict(array('kind' => 0) + $facts));
	pg_assert_same('no_contact', pg_lead_touch_verdict(array('contact_id' => 0) + $facts));
	pg_assert_same('panel_user', pg_lead_touch_verdict(array('staff' => true) + $facts), 'staff are never counted');
	pg_assert_same('already', pg_lead_touch_verdict(array('marked' => true) + $facts), 'a contact becomes a lead once');
	pg_assert_same('not_first', pg_lead_touch_verdict(array('earlier' => true) + $facts), 'a second form is no new lead');
	pg_assert_same('no_visit', pg_lead_touch_verdict(array('touches' => null) + $facts), 'without a tied visit there is no touch');
	pg_assert_same('panel_user', pg_lead_touch_verdict(array('staff' => true, 'marked' => true, 'touches' => null) + $facts), 'the first reason wins');
}

function test_lead_touch_choices()
{
	pg_assert_same('campaign', pg_lead_sources_choice('campaign', array('channel', 'campaign', 'landing_page')));
	pg_assert_same('channel', pg_lead_sources_choice('', array('channel', 'campaign', 'landing_page')), 'empty: the first');
	pg_assert_same('channel', pg_lead_sources_choice(array('x'), array('channel', 'campaign')), 'not a string');
	pg_assert_same('first', pg_lead_sources_choice('middle', array('first', 'last')));
}

function test_lead_touch_group_by_channel()
{
	$rows = array(
		array('kind' => PG_LEAD_JOURNEY_LEAD, 'channel' => 'organic', 'campaign' => '', 'landing_page' => '/', 'converted' => true, 'revenue' => 0),
		array('kind' => PG_LEAD_JOURNEY_LEAD, 'channel' => 'organic', 'campaign' => '', 'landing_page' => '/blog', 'converted' => false, 'revenue' => 0),
		array('kind' => PG_LEAD_JOURNEY_LEAD, 'channel' => 'paid', 'campaign' => 'spring', 'landing_page' => '/', 'converted' => false, 'revenue' => 0),
		array('kind' => PG_LEAD_JOURNEY_CUSTOMER, 'channel' => 'organic', 'campaign' => '', 'landing_page' => '/', 'converted' => false, 'revenue' => 12550),
		array('kind' => PG_LEAD_JOURNEY_CUSTOMER, 'channel' => 'email', 'campaign' => 'news', 'landing_page' => '/shop', 'converted' => false, 'revenue' => 4000),
		array('kind' => PG_LEAD_JOURNEY_CUSTOMER, 'channel' => null, 'campaign' => null, 'landing_page' => null, 'converted' => false, 'revenue' => 100),
	);

	$groups = pg_lead_sources_group($rows, 'channel');

	pg_assert_same(array('organic', 'paid', '', 'email'), array_column($groups, 'key'), 'most leads first, then most customers, then by name');
	pg_assert_same(array('key' => 'organic', 'gone' => false, 'leads' => 2, 'converted' => 1, 'customers' => 1, 'revenue' => 12550, 'share' => 50), $groups[0]);
	pg_assert_same(0, $groups[1]['share'], 'no lead became a customer');
	pg_assert_same(null, $groups[3]['share'], 'no lead: no share');
	pg_assert_same('', $groups[2]['key'], 'a trimmed visit groups as not known');

	$totals = pg_lead_sources_totals($groups);

	pg_assert_same(array('leads' => 3, 'converted' => 1, 'customers' => 3, 'revenue' => 16650, 'share' => 33), $totals);
	pg_assert_same(array('leads' => 0, 'converted' => 0, 'customers' => 0, 'revenue' => 0, 'share' => null), pg_lead_sources_totals(array()));

	$pages = pg_lead_sources_group($rows, 'landing_page');

	pg_assert_same('/', $pages[0]['key']);
	pg_assert_same(2, $pages[0]['leads'], 'two leads landed on the home page');
	pg_assert_same(1, $pages[0]['customers']);
}

function test_lead_touch_share_rounds()
{
	pg_assert_same(67, pg_lead_sources_share(2, 3));
	pg_assert_same(100, pg_lead_sources_share(3, 3));
	pg_assert_same(null, pg_lead_sources_share(0, 0));
}

function test_lead_touch_save_paths_pinned()
{
	$form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/custom_form.php');
	$order = (string) file_get_contents(PG_FUNCTIONS_DIR . '/submit_order.php');
	$booking = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/bookings.php');
	$invoice = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/settlement.php');

	pg_assert_contains("pg_lead_became('form'", $form, 'a form sent');
	pg_assert_true(strpos($form, "pg_lead_tie('form'") < strpos($form, "pg_lead_became('form'"), 'after the tie, so the visits tied back are its touches');
	pg_assert_contains("pg_lead_became('order'", $order, 'an order placed');
	pg_assert_true(strpos($order, "pg_lead_tie('order'") < strpos($order, "pg_lead_became('order'"), 'after the tie');
	pg_assert_contains('pg_lead_became_booking(', $booking, 'a booking made on the site');
	pg_assert_contains('pg_lead_became_invoice($invoice_id)', $invoice, 'a sales invoice paid in full');
}

function test_lead_touch_screen_pinned()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/lead_sources.php');
	$menu = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/output.php');
	$help = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_help_url.php');

	pg_assert_contains('validate_visitors_access($user)', $screen, 'the visitor reports\' gate');
	pg_assert_contains('pg_xlsx_write_plain(', $screen, 'Excel as the other reports build it');
	pg_assert_contains('pg-report-tools', $screen);
	pg_assert_contains('pg_report_figures_html(', $screen);
	pg_assert_contains('pg-report-frame', $screen);
	pg_assert_contains("lang('The figures count only visitors who accepted marketing cookies.')", $screen);
	pg_assert_contains("/lead_sources.php", $menu, 'listed under Visitors');
	pg_assert_contains("case 'lead_sources.php':", $menu, 'the Visitors menu is the active one');
	pg_assert_contains("'lead_sources'", $help, 'a help row');
}

function test_lead_touch_became_runs_under_the_contacts_lock()
{
	$name = pg_lead_became_lock_name(42, PG_LEAD_JOURNEY_LEAD, 'shop_db');

	pg_assert_true(strlen($name) <= 64, 'a lock name has at most 64 characters');
	pg_assert_true($name !== pg_lead_became_lock_name(42, PG_LEAD_JOURNEY_CUSTOMER, 'shop_db'), 'a lead and a customer mark lock apart');
	pg_assert_true($name !== pg_lead_became_lock_name(42, PG_LEAD_JOURNEY_LEAD, 'other_db'), 'two sites on one server lock apart');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/lead_touch.php');
	$body = substr($source, strpos($source, 'function pg_lead_became($act'));
	pg_assert_true(strpos($body, 'GET_LOCK') !== false && strpos($body, 'GET_LOCK') < strpos($body, 'FROM contact_journey'), 'the check and the write run under the lock');
	pg_assert_contains('RELEASE_LOCK', $body);
}

function test_lead_touch_a_cancelled_booking_is_no_earlier_act()
{
	$counted = pg_lead_touch_booking_statuses();

	pg_assert_false(in_array('held', $counted, true), 'a hold is no booking yet');
	pg_assert_false(in_array('cancelled', $counted, true), 'an abandoned deposit hold ends cancelled: no booking, as a cancelled order is none');
	pg_assert_true(in_array('requested', $counted, true) && in_array('confirmed', $counted, true) && in_array('done', $counted, true));
	pg_assert_contains('pg_lead_touch_booking_statuses()', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/lead_touch.php'));
}

function test_lead_touch_a_trimmed_visit_is_not_known_under_campaign()
{
	$rows = array(
		array('kind' => PG_LEAD_JOURNEY_LEAD, 'channel' => 'direct', 'campaign' => '', 'landing_page' => '/', 'converted' => false, 'revenue' => 0, 'gone' => false),
		array('kind' => PG_LEAD_JOURNEY_LEAD, 'channel' => '', 'campaign' => '', 'landing_page' => '', 'converted' => false, 'revenue' => 0, 'gone' => true),
		array('kind' => PG_LEAD_JOURNEY_LEAD, 'channel' => '', 'campaign' => '', 'landing_page' => '', 'converted' => false, 'revenue' => 0, 'gone' => true),
	);

	$groups = pg_lead_sources_group($rows, 'campaign');

	pg_assert_same(2, count($groups), 'a visit without a campaign and a trimmed visit are two rows');
	pg_assert_same(array('', ''), array_column($groups, 'key'));
	pg_assert_same(array(true, false), array_column($groups, 'gone'), 'the trimmed one has more leads here');
	pg_assert_same(2, $groups[0]['leads']);
	pg_assert_same(1, count(pg_lead_sources_group($rows, 'landing_page')) - 1, 'by landing page as before');
}

function test_lead_touch_report_counts_a_contacts_first_row_of_a_kind()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/lead_touch.php');
	$body = substr($source, strpos($source, 'function pg_lead_sources_read('));

	pg_assert_contains('NOT EXISTS (SELECT 1 FROM contact_journey e WHERE e.contact_id = j.contact_id AND e.kind = j.kind', $body, 'a merged contact holding two lead rows is one lead');
	pg_assert_contains('v.id AS visit_id', $body, 'a trimmed visit is marked');
}

function test_lead_touch_became_customer_only_at_or_after_the_lead()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/lead_touch.php');
	$body = substr($source, strpos($source, 'function pg_lead_sources_read('));

	pg_assert_contains("\$converted = \$converted || (\$at >= (int) \$row['at']);", $body, 'a contact who bought first and sent a form later did not become a customer since');
}

function test_lead_touch_customer_read_a_counting_first_order()
{
	$row = array('at' => 1000, 'detail' => 'order', 'ref_id' => 5);
	$own = array('id' => 5, 'status' => 'complete', 'cancelled_at' => 0, 'total' => 4000, 'refunded_amount' => 0, 'order_date' => 1000);

	pg_assert_same(array('at' => 1000, 'revenue' => 4000), pg_lead_customer_read($row, $own, array()), 'a counting first order is the customer and its total the revenue');
	pg_assert_same(array('at' => 1000, 'revenue' => 4000), pg_lead_customer_read($row, array('status' => 'exported') + $own, array()), 'exported counts');
}

function test_lead_touch_customer_read_a_cancelled_or_fully_refunded_first_order()
{
	$row = array('at' => 1000, 'detail' => 'order', 'ref_id' => 5);
	$own = array('id' => 5, 'status' => 'complete', 'cancelled_at' => 0, 'total' => 4000, 'refunded_amount' => 0, 'order_date' => 1000);

	pg_assert_same(null, pg_lead_customer_read($row, array('cancelled_at' => 1200) + $own, array()), 'a cancelled first order makes no customer');
	pg_assert_same(null, pg_lead_customer_read($row, array('refunded_amount' => 4000) + $own, array()), 'a first order refunded in full makes no customer');
	pg_assert_same(null, pg_lead_customer_read($row, array('refunded_amount' => 4000, 'total' => 4000, 'cancelled_at' => 0) + $own, array(
		array('id' => 9, 'status' => 'complete', 'cancelled_at' => 1500, 'total' => 900, 'refunded_amount' => 0, 'order_date' => 1400),
		array('id' => 8, 'status' => 'incomplete', 'cancelled_at' => 0, 'total' => 900, 'refunded_amount' => 0, 'order_date' => 1300),
	)), 'later orders that do not count take nothing over');
}

function test_lead_touch_customer_read_a_partly_refunded_first_order()
{
	$row = array('at' => 1000, 'detail' => 'order', 'ref_id' => 5);
	$own = array('id' => 5, 'status' => 'complete', 'cancelled_at' => 0, 'total' => 4000, 'refunded_amount' => 1500, 'order_date' => 1000);

	pg_assert_same(array('at' => 1000, 'revenue' => 2500), pg_lead_customer_read($row, $own, array()), 'a partial refund keeps the customer; revenue less the refund');
}

function test_lead_touch_customer_read_a_later_counting_order_takes_over()
{
	$row = array('at' => 1000, 'detail' => 'order', 'ref_id' => 5);
	$own = array('id' => 5, 'status' => 'complete', 'cancelled_at' => 1100, 'total' => 4000, 'refunded_amount' => 0, 'order_date' => 1000);
	$others = array(
		array('id' => 12, 'status' => 'complete', 'cancelled_at' => 0, 'total' => 700, 'refunded_amount' => 0, 'order_date' => 3000),
		array('id' => 5, 'status' => 'complete', 'cancelled_at' => 1100, 'total' => 4000, 'refunded_amount' => 0, 'order_date' => 1000),
		array('id' => 11, 'status' => 'exported', 'cancelled_at' => 0, 'total' => 2000, 'refunded_amount' => 500, 'order_date' => 2000),
		array('id' => 10, 'status' => 'complete', 'cancelled_at' => 0, 'total' => 900, 'refunded_amount' => 900, 'order_date' => 1500),
		array('id' => 4, 'status' => 'complete', 'cancelled_at' => 0, 'total' => 100, 'refunded_amount' => 0, 'order_date' => 900),
	);

	pg_assert_same(array('at' => 2000, 'revenue' => 1500), pg_lead_customer_read($row, $own, $others), 'the next counting order after the first takes the row over, with its own amount');
	pg_assert_same(array('at' => 1000, 'revenue' => 4000), pg_lead_customer_read($row, array('cancelled_at' => 0) + $own, $others), 'an undone cancel brings the first order back');
}

function test_lead_touch_customer_read_as_written()
{
	pg_assert_same(array('at' => 1000, 'revenue' => 0), pg_lead_customer_read(array('at' => 1000, 'detail' => 'invoice', 'ref_id' => 3), null, array()), 'a paid-invoice row stays, with no revenue');
	pg_assert_same(array('at' => 1000, 'revenue' => 0), pg_lead_customer_read(array('at' => 1000, 'detail' => 'order', 'ref_id' => 3), null, array()), 'an order that is gone is read as written');
}

function test_lead_touch_report_judges_the_customer_rows_at_read()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/lead_touch.php');
	$body = substr($source, strpos($source, 'function pg_lead_sources_read('));

	pg_assert_contains('pg_lead_customer_read(', $body, 'each customer row is judged by its order');
	pg_assert_contains('pg_product_insights_counted_sql(', $body, 'a row before the period is read when a counting order of the period may take it over');
}
