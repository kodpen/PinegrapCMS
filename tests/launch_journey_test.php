<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the launch journey's pure catalogue (includes/fn/launch_journey.php):
 * the steps per purpose, each step's rule over the facts, blockers and
 * warnings, a shop with PayTR in test mode, a blog, "Disallow: /" in the
 * robots extras, a hidden blocker, the payment-live rule per driver and the
 * end of the first week.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/launch_journey.php');

// Facts of a site where every step is done; a test changes what it checks.
function launch_journey_test_facts($changes = array())
{
	$facts = array(
		'https'            => true,
		'certificate'      => 'ok',
		'mail_ready'       => true,
		'mail_spf'         => 'ok',
		'mail_dkim'        => 'ok',
		'mail_dmarc'       => 'ok',
		'kvkk_text'        => true,
		'consent_enabled'  => true,
		'legal_documents'  => true,
		'collects_data'    => true,
		'robots_text'      => "User-agent: *\nDisallow: /cart\n",
		'home_noindex'     => false,
		'search_console'   => 'connected',
		'google_analytics' => 'connected',
		'payments'         => array(array('driver' => 'stripe', 'settings' => array('publishable_key' => 'pk_live_abc', 'secret_key' => 'sk_live_abc'))),
		'gateway_mode'     => 'live',
		'booking_hours'    => true,
		'backup'           => 'pass',
		'cron'             => 'ok',
		'team_signed_in'   => true,
		'maintenance'      => false,
	);

	return array_merge($facts, $changes);
}

// The keys of a list of rows.
function launch_journey_test_keys($rows)
{
	$keys = array();

	foreach ($rows as $row) {
		$keys[] = $row['key'];
	}

	return $keys;
}

function test_launch_journey_purposes_and_catalogue()
{
	pg_assert_same(array('shop', 'company', 'association', 'school', 'clinic', 'agency', 'blog', 'other'), pg_launch_journey_purposes());
	pg_assert_same('other', pg_launch_journey_purpose('nonsense'), 'an unknown purpose is Other');
	pg_assert_same('shop', pg_launch_journey_purpose('shop'));

	$shop = pg_launch_journey_steps('shop');
	pg_assert_true(in_array('payment_live', $shop, true), 'a shop takes payment');
	pg_assert_false(in_array('booking_hours', $shop, true));

	$clinic = pg_launch_journey_steps('clinic');
	pg_assert_true(in_array('booking_hours', $clinic, true), 'a clinic sets its booking hours');
	pg_assert_false(in_array('payment_live', $clinic, true));

	foreach (pg_launch_journey_purposes() as $purpose) {
		pg_assert_false(in_array('maintenance', pg_launch_journey_steps($purpose), true), 'maintenance mode is a go-live check, not a step: ' . $purpose);
		pg_assert_same('domain_ssl', pg_launch_journey_steps($purpose)[0], 'every path starts with the domain: ' . $purpose);
	}
}

function test_launch_journey_payment_live_per_driver()
{
	pg_assert_same('live', pg_launch_journey_payment_live('stripe', array('publishable_key' => 'pk_live_1', 'secret_key' => 'sk_live_1'), 'test'), 'Stripe goes by its key prefix, not the gateway mode');
	pg_assert_same('test', pg_launch_journey_payment_live('stripe', array('publishable_key' => 'pk_live_1', 'secret_key' => 'sk_test_1'), 'live'), 'one test key is test');
	pg_assert_same('test', pg_launch_journey_payment_live('stripe', array('secret_key' => 'rk_test_1'), 'live'));
	pg_assert_same('unknown', pg_launch_journey_payment_live('stripe', array('publishable_key' => '', 'secret_key' => ''), 'live'), 'no keys cannot be judged');

	pg_assert_same('test', pg_launch_journey_payment_live('paytr', array('mode' => 'test'), 'live'));
	pg_assert_same('live', pg_launch_journey_payment_live('paytr', array('mode' => 'live'), 'test'));
	pg_assert_same('test', pg_launch_journey_payment_live('paypal', array('mode' => 'sandbox'), 'live'));
	pg_assert_same('live', pg_launch_journey_payment_live('paypal', array('mode' => 'live'), 'live'));

	pg_assert_same('test', pg_launch_journey_payment_live('iyzico', array(), 'test'), 'iyzico follows the gateway mode');
	pg_assert_same('live', pg_launch_journey_payment_live('Authorize.Net', array(), 'live'), 'a classic gateway follows the gateway mode');
	pg_assert_same('unknown', pg_launch_journey_payment_live('iyzico', array(), ''));
}

function test_launch_journey_shop_with_paytr_in_test_mode()
{
	$facts = launch_journey_test_facts(array('payments' => array(array('driver' => 'paytr', 'settings' => array('mode' => 'test')))));

	$rule = pg_launch_journey_rule('payment_live', $facts, 'shop');
	pg_assert_same('todo', $rule['state']);
	pg_assert_same('blocker', $rule['level']);
	pg_assert_same('test_mode', $rule['reason']);

	$check = pg_launch_journey_golive_check('shop', $facts, array());
	pg_assert_same(array('payment_live'), launch_journey_test_keys($check['blockers']));

	$state = pg_launch_journey_state('shop', $facts, array());
	pg_assert_same('blocked', $state['status']);
	pg_assert_same('payment_live', $state['rows'][0]['key'], 'a blocker comes first');
	pg_assert_false($state['finished']);
}

function test_launch_journey_blog()
{
	$facts = launch_journey_test_facts(array(
		'payments'       => array(),
		'booking_hours'  => false,
		'team_signed_in' => false,
		'collects_data'  => false,
		'kvkk_text'      => false,
	));

	$state = pg_launch_journey_state('blog', $facts, array());
	pg_assert_false(in_array('payment_live', launch_journey_test_keys($state['rows']), true), 'no payment step on a blog');

	$legal = pg_launch_journey_rule('legal', $facts, 'blog');
	pg_assert_same('todo', $legal['state']);
	pg_assert_same('warning', $legal['level'], 'no personal-data text is only a warning where nothing collects data');

	$check = pg_launch_journey_golive_check('blog', $facts, array());
	pg_assert_same(array(), $check['blockers']);
	pg_assert_same(array('legal'), launch_journey_test_keys($check['warnings']), 'the team step is no go-live warning');
	pg_assert_same('in_progress', $state['status']);

	$legal = pg_launch_journey_rule('legal', launch_journey_test_facts(array('kvkk_text' => false)), 'blog');
	pg_assert_same('blocker', $legal['level'], 'a form that collects data needs the text');
}

function test_launch_journey_disallow_all_in_robots_extras()
{
	$facts = launch_journey_test_facts(array('robots_text' => "User-agent: *\nDisallow: /\n"));

	$rule = pg_launch_journey_rule('search_open', $facts, 'company');
	pg_assert_same('todo', $rule['state']);
	pg_assert_same('blocker', $rule['level']);
	pg_assert_same('robots_closed', $rule['reason']);

	$facts = launch_journey_test_facts(array('robots_text' => "User-agent: GPTBot\nDisallow: /\n"));
	pg_assert_same('done', pg_launch_journey_rule('search_open', $facts, 'company')['state'], 'one closed crawler is not the whole site');

	$facts = launch_journey_test_facts(array('home_noindex' => true));
	pg_assert_same('home_noindex', pg_launch_journey_rule('search_open', $facts, 'company')['reason']);
}

function test_launch_journey_hidden_blocker_stays()
{
	$facts = launch_journey_test_facts(array('cron' => 'stalled', 'google_analytics' => 'not_connected'));
	$hidden = array('cron', 'search_tools', 'not_a_step');

	pg_assert_same(array('cron', 'search_tools'), pg_launch_journey_hidden_clean($hidden));

	$state = pg_launch_journey_state('company', $facts, $hidden);
	$keys = launch_journey_test_keys($state['rows']);
	pg_assert_same('cron', $keys[0], 'a blocker cannot be hidden');
	pg_assert_false(in_array('search_tools', $keys, true), 'a hidden warning is gone from the card');
	pg_assert_false($state['finished'], 'hidden is never done');

	$check = pg_launch_journey_golive_check('company', $facts, $hidden);
	pg_assert_same(array('cron'), launch_journey_test_keys($check['blockers']));
	pg_assert_same(array(), $check['warnings']);

	$facts = launch_journey_test_facts(array('google_analytics' => 'not_connected'));
	$state = pg_launch_journey_state('company', $facts, array('search_tools'));
	pg_assert_true($state['finished'], 'every step done or hidden ends the journey');
	pg_assert_same('ready', $state['status']);
	pg_assert_same($state['total'], $state['done']);
}

function test_launch_journey_maintenance_and_unknown_facts()
{
	$check = pg_launch_journey_golive_check('company', launch_journey_test_facts(array('maintenance' => true)), array('maintenance'));
	pg_assert_same(array('maintenance'), launch_journey_test_keys($check['blockers']), 'maintenance mode on blocks going live');

	$rule = pg_launch_journey_rule('cron', array(), 'company');
	pg_assert_same('unknown', $rule['state'], 'a fact not read is no state');
	pg_assert_same('none', $rule['level'], 'what cannot be read never blocks');

	$rule = pg_launch_journey_rule('mail', launch_journey_test_facts(array('mail_dkim' => 'error')), 'company');
	pg_assert_same('warning', $rule['level']);
	pg_assert_same('dns', $rule['reason']);
	pg_assert_same('blocker', pg_launch_journey_rule('mail', launch_journey_test_facts(array('mail_ready' => false)), 'company')['level']);

	pg_assert_same('old', pg_launch_journey_rule('backups', launch_journey_test_facts(array('backup' => 'warn')), 'company')['reason']);
	pg_assert_same('blocker', pg_launch_journey_rule('cron', launch_journey_test_facts(array('cron' => 'stalled')), 'company')['level']);
	pg_assert_same('warning', pg_launch_journey_rule('cron', launch_journey_test_facts(array('cron' => 'never')), 'company')['level']);
}

function test_launch_journey_first_week_ends()
{
	$live = 1795000000;

	pg_assert_false(pg_launch_journey_first_week(0, $live)['open'], 'not live yet');
	pg_assert_false(pg_launch_journey_first_week($live, $live - 1)['open'], 'before going live');

	$week = pg_launch_journey_first_week($live, $live);
	pg_assert_true($week['open']);
	pg_assert_same(1, $week['day']);
	pg_assert_same($live + 7 * 86400, $week['ends_at']);

	$week = pg_launch_journey_first_week($live, $live + 7 * 86400 - 1);
	pg_assert_true($week['open'], 'the last second of the week');
	pg_assert_same(7, $week['day']);
	pg_assert_same(1, $week['left']);

	$week = pg_launch_journey_first_week($live, $live + 7 * 86400);
	pg_assert_false($week['open'], 'the week ends by itself');
	pg_assert_same(0, $week['left']);
}
