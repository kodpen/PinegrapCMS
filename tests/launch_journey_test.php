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

// The stored row: read whole from anything, changed without losing a later
// step's keys, hidden steps cleared only when a closed card comes back.
function test_launch_journey_stored_row()
{
	$empty = pg_launch_journey_stored_read('not json');
	pg_assert_same(array('purpose' => '', 'suggested' => '', 'live_at' => 0, 'hidden' => array(), 'closed' => false), $empty);

	$read = pg_launch_journey_stored_read('{"purpose":"spaceship","hidden":["team","maintenance","team","x"],"live_at":-5,"closed":1}');
	pg_assert_same('', $read['purpose'], 'an unknown purpose is not kept');
	pg_assert_same(array('team'), $read['hidden'], 'only card steps, once');
	pg_assert_same(0, $read['live_at']);
	pg_assert_true($read['closed']);

	$json = pg_launch_journey_stored_change('{"summary_hash":"abc","hidden":["team"]}', array('purpose' => 'shop', 'hide' => 'backups', 'closed' => false));
	$row = json_decode($json, true);
	pg_assert_same('abc', $row['summary_hash'], 'a later key is kept');
	pg_assert_same('shop', $row['purpose']);
	pg_assert_same(array('team', 'backups'), $row['hidden'], 'an open card keeps its hidden steps');

	$back = json_decode(pg_launch_journey_stored_change('{"hidden":["team"],"closed":true}', array('closed' => false)), true);
	pg_assert_same(array(), $back['hidden'], 'brought back with every step');
	pg_assert_false($back['closed']);

	$ignored = json_decode(pg_launch_journey_stored_change('', array('purpose' => 'nope', 'hide' => 'maintenance')), true);
	pg_assert_same('', $ignored['purpose']);
	pg_assert_same(array(), $ignored['hidden']);
}

// What a request may change, and the template map.
function test_launch_journey_requests_and_templates()
{
	pg_assert_same(array('purpose' => 'blog'), pg_launch_journey_request_changes(array('purpose' => 'blog')));
	pg_assert_same(array('hide' => 'team'), pg_launch_journey_request_changes(array('hide' => 'team')));
	pg_assert_same(array(), pg_launch_journey_request_changes(array('hide' => 'maintenance', 'purpose' => 'x')));
	pg_assert_same(array('closed' => true), pg_launch_journey_request_changes(array('close' => 1)));

	pg_assert_same('shop', pg_launch_journey_template_purpose('pinegrap-store'));
	pg_assert_same('clinic', pg_launch_journey_template_purpose('pinegrap-clinic'));
	pg_assert_same('', pg_launch_journey_template_purpose('pinegrap-zoo'), 'an unknown template leaves it to the dialog');

	foreach (pg_launch_journey_purposes() as $purpose) {
		pg_assert_true(isset(pg_launch_journey_purpose_cards()[$purpose]), 'a card for ' . $purpose);
	}
}

// The card: blockers first, no "Not for this site" on a blocker or a step
// done, a line for every step, the mail line naming each record, links to
// a settings section or a screen.
function test_launch_journey_card()
{
	$facts = launch_journey_test_facts(array('robots_text' => "User-agent: *\nDisallow: /\n", 'mail_dkim' => 'error', 'team_signed_in' => false));
	$card = pg_launch_journey_card('shop', $facts, array());

	pg_assert_same('search_open', $card['rows'][0]['key'], 'the blocker first');
	pg_assert_false($card['rows'][0]['hide'], 'a blocker cannot be hidden');
	pg_assert_same('blocked', $card['status']['key']);
	pg_assert_false($card['finished']);

	foreach ($card['rows'] as $row) {
		pg_assert_true($row['line'] !== '', $row['key'] . ' line');
		pg_assert_true(preg_match('~^(#settings/[a-z_]+/pgset-[a-z-]+|[a-z_]+\.php)$~', $row['url']) === 1, $row['key'] . ' link');
		if ($row['done']) {
			pg_assert_false($row['hide'], $row['key'] . ' done is not hidden');
		}
		if ($row['key'] === 'mail') {
			pg_assert_same('warning', $row['level']);
			pg_assert_true(strpos($row['line'], 'DKIM') !== false, 'the mail line names DKIM');
		}
		if ($row['key'] === 'team') {
			pg_assert_true($row['hide'], 'a step to do may be hidden');
		}
	}

	$done = pg_launch_journey_card('company', launch_journey_test_facts(), array());
	pg_assert_true($done['finished'], 'every step done');
	pg_assert_same('ready', $done['status']['key']);
}

// "Go live" takes the catch-all "Disallow: /" out of the robots extras and
// keeps every other rule; a group left empty loses its User-agent lines so
// they do not join the next group.
function test_launch_journey_robots_opened()
{
	pg_assert_same('', pg_launch_journey_robots_opened("User-agent: *\nDisallow: /\n"));
	pg_assert_same("User-agent: *\nDisallow: /cart", pg_launch_journey_robots_opened("User-agent: *\nDisallow: /\nDisallow: /cart"));

	$text = "# keep\nUser-agent: *\nDisallow: /\n\nUser-agent: BadBot\nDisallow: /";
	$opened = pg_launch_journey_robots_opened($text);
	pg_assert_false(pg_robots_catch_all_closed($opened), 'the catch-all is open');
	pg_assert_same("# keep\n\nUser-agent: BadBot\nDisallow: /", $opened, 'another bot\'s group stays');

	pg_assert_same("User-agent: Googlebot\nDisallow: /", pg_launch_journey_robots_opened("User-agent: Googlebot\nDisallow: /"), 'only the catch-all');
}

// The go-live check's dialog: blockers and warnings as rows, the changes
// "Go live" makes itself only when they apply, and the blockers it leaves.
function test_launch_journey_golive_view()
{
	$facts = launch_journey_test_facts(array(
		'maintenance' => true,
		'robots_text' => "User-agent: *\nDisallow: /\n",
		'gateway_mode' => 'test',
		'payments' => array(array('driver' => 'iyzico', 'settings' => array())),
		'backup' => 'fail',
	));
	$view = pg_launch_journey_golive_view('shop', $facts, array());

	pg_assert_same(array('maintenance', 'payment_live', 'search_open'), launch_journey_test_keys($view['must']), 'maintenance first');
	pg_assert_same(array('backups'), launch_journey_test_keys($view['worth']));
	pg_assert_same(2, count($view['changes']), 'both changes named');
	pg_assert_same(array('payment_live'), launch_journey_test_keys($view['left']), 'a payment mode is never changed');

	foreach (array_merge($view['must'], $view['worth']) as $row) {
		pg_assert_true(($row['name'] !== '') && ($row['line'] !== '') && ($row['url'] !== ''), $row['key'] . ' drawn');
	}

	$clean = pg_launch_journey_golive_view('company', launch_journey_test_facts(), array());
	pg_assert_same(array(), $clean['must']);
	pg_assert_same(array(), $clean['changes'], 'nothing to change');
	pg_assert_same(array(), $clean['left']);

	// The home page's noindex is not changed by "Go live": it stays a blocker left.
	$noindex = pg_launch_journey_golive_view('company', launch_journey_test_facts(array('home_noindex' => true)), array());
	pg_assert_same(array('search_open'), launch_journey_test_keys($noindex['left']));
	pg_assert_same(array(), $noindex['changes']);
}

// With a blocker left "Go live" needs "I know, go live anyway".
function test_launch_journey_golive_allowed()
{
	$left = array(array('key' => 'mail'));

	pg_assert_true(pg_launch_journey_golive_allowed(array(), array()));
	pg_assert_false(pg_launch_journey_golive_allowed($left, array()));
	pg_assert_false(pg_launch_journey_golive_allowed($left, array('anyway' => 0)));
	pg_assert_true(pg_launch_journey_golive_allowed($left, array('anyway' => 1)));
}

// GET /launch: every step of the purpose with its line, hidden marked, the
// go-live check beside it and what "Go live" would change itself.
function test_launch_journey_report()
{
	$stored = pg_launch_journey_stored_read(json_encode(array('purpose' => 'shop', 'hidden' => array('backups', 'payment_live'), 'live_at' => 1700000000)));
	$facts = launch_journey_test_facts(array(
		'maintenance' => true,
		'payments'    => array(array('driver' => 'paytr', 'settings' => array('mode' => 'test'))),
		'backup'      => 'fail',
	));
	$report = pg_launch_journey_report($stored, $facts);

	pg_assert_same('shop', $report['purpose']);
	pg_assert_true($report['purpose_chosen']);
	pg_assert_same('blocked', $report['status']);
	pg_assert_same(1700000000, $report['live_at']);
	pg_assert_same(pg_launch_journey_steps('shop'), launch_journey_test_keys($report['steps']), 'every step, in the purpose\'s order');
	pg_assert_same(array('maintenance', 'payment_live'), launch_journey_test_keys($report['blockers']), 'a hidden blocker still blocks');
	pg_assert_same(array(), $report['warnings'], 'a hidden warning is left out');
	pg_assert_same(array('maintenance'), $report['go_live_changes']);

	foreach ($report['steps'] as $row) {
		pg_assert_true(($row['name'] !== '') && ($row['line'] !== ''), $row['key'] . ' named');
		pg_assert_same(in_array($row['key'], array('backups', 'payment_live'), true), $row['hidden'], $row['key'] . ' hidden');
	}

	// No purpose chosen: the steps follow Other, as on the card.
	$none = pg_launch_journey_report(pg_launch_journey_stored_read(''), launch_journey_test_facts());
	pg_assert_same('other', $none['purpose']);
	pg_assert_false($none['purpose_chosen']);
	pg_assert_same('ready', $none['status']);
	pg_assert_same(0, $none['live_at']);
	pg_assert_same(array(), $none['blockers']);
}
