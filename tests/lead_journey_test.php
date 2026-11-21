<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the visitor journey's pure rules (includes/fn/lead_journey.php):
 * the browser key and the marketing consent, the tie and its move on a
 * shared browser, a panel user, the visits tied back inside the keep period,
 * and the interest score fading by its half-life.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/lead_journey.php');

// 2026-11-20 12:00:00 UTC.
define('LEAD_JOURNEY_TEST_NOW', 1795176000);

function lead_journey_test_tie($browser_contact, $contact_id, $act = 'form', $panel = false, $consent = 'yes', $has_key = true)
{
	return pg_lead_tie_decision(array(
		'consent' => $consent,
		'has_key' => $has_key,
		'browser_contact_id' => $browser_contact,
		'contact_id' => $contact_id,
		'act' => $act,
		'panel_user' => $panel,
	));
}

function test_lead_journey_no_key_without_the_yes()
{
	foreach (array('no', 'unanswered', 'off', '', 'YES ') as $consent) {
		pg_assert_same('none', pg_lead_key_verdict($consent, false), 'no key for ' . var_export($consent, true));
	}

	pg_assert_same('write', pg_lead_key_verdict('yes', false), 'the yes writes the key');
	pg_assert_same('keep', pg_lead_key_verdict('yes', true), 'the yes keeps it');
	pg_assert_same('no_consent', lead_journey_test_tie(0, 5, 'form', false, 'unanswered', false)['verdict'], 'unanswered: nothing tied');
	pg_assert_same('no_consent', lead_journey_test_tie(0, 5, 'form', false, 'off', false)['verdict'], 'notice off: nothing tied');
}

function test_lead_journey_refusal_after_a_yes_deletes_the_key()
{
	pg_assert_same('delete', pg_lead_key_verdict('no', true), 'a refusal deletes the key');
	pg_assert_same('delete', pg_lead_key_verdict('off', true), 'the notice turned off deletes it');

	$decision = lead_journey_test_tie(7, 7, 'sign_in', false, 'no', true);
	pg_assert_same('no_consent', $decision['verdict'], 'no new tie after the refusal');
	pg_assert_same(0, $decision['contact_id'], 'no contact named');
}

function test_lead_journey_first_tie_by_the_persons_act()
{
	foreach (pg_lead_tie_acts() as $act) {
		$decision = lead_journey_test_tie(0, 12, $act);
		pg_assert_same('tie', $decision['verdict'], $act);
		pg_assert_same(12, $decision['contact_id'], $act . ' contact');
		pg_assert_same(0, $decision['moved_from'], $act . ' moved from');
	}

	pg_assert_same('not_an_act', lead_journey_test_tie(0, 12, 'ip_guess')['verdict'], 'never by a guess');
	pg_assert_same('not_an_act', lead_journey_test_tie(0, 12, 'page_view')['verdict'], 'never by a page view');
	pg_assert_same('no_contact', lead_journey_test_tie(0, 0)['verdict'], 'no contact');
	pg_assert_same('no_key', lead_journey_test_tie(0, 12, 'form', false, 'yes', false)['verdict'], 'no key yet');
	pg_assert_same('same', lead_journey_test_tie(12, 12, 'order')['verdict'], 'already tied to the same contact');
}

function test_lead_journey_two_contacts_on_one_browser_move_the_key()
{
	$decision = lead_journey_test_tie(12, 30, 'form');
	pg_assert_same('move', $decision['verdict'], 'a second contact moves the key');
	pg_assert_same(30, $decision['contact_id'], 'the key points at the new contact');
	pg_assert_same(12, $decision['moved_from'], 'the earlier contact is named');

	// The earlier contact's visits stay with it after the move.
	$visits = array(
		array('id' => 1, 'started_at' => LEAD_JOURNEY_TEST_NOW - 5 * 86400, 'contact_id' => 12),
		array('id' => 2, 'started_at' => LEAD_JOURNEY_TEST_NOW - 3600, 'contact_id' => 0),
	);
	pg_assert_same(array(2), pg_lead_tie_back_ids($visits, LEAD_JOURNEY_TEST_NOW - 30 * 86400, pg_lead_keep_start(LEAD_JOURNEY_TEST_NOW, 13)), 'only the untied visit goes to the new contact');
}

function test_lead_journey_panel_user_is_never_tied()
{
	foreach (array(array(0, 'tie'), array(4, 'same'), array(9, 'move')) as $case) {
		$decision = lead_journey_test_tie($case[0], 4, 'sign_in', true);
		pg_assert_same('panel_user', $decision['verdict'], 'panel user instead of ' . $case[1]);
		pg_assert_same(0, $decision['contact_id'], 'no contact for a panel user');
	}
}

function test_lead_journey_tie_back_inside_and_outside_the_keep_period()
{
	$keep_start = pg_lead_keep_start(LEAD_JOURNEY_TEST_NOW, 13);
	pg_assert_same(gmmktime(12, 0, 0, 10, 20, 2025), $keep_start, '13 months back');

	$consent_at = gmmktime(0, 0, 0, 1, 1, 2025);
	$visits = array(
		array('id' => 1, 'started_at' => gmmktime(0, 0, 0, 12, 31, 2024), 'contact_id' => 0),
		array('id' => 2, 'started_at' => gmmktime(0, 0, 0, 6, 1, 2025), 'contact_id' => 0),
		array('id' => 3, 'started_at' => $keep_start - 1, 'contact_id' => 0),
		array('id' => 4, 'started_at' => $keep_start, 'contact_id' => 0),
		array('id' => 5, 'started_at' => LEAD_JOURNEY_TEST_NOW - 60, 'contact_id' => 0),
		array('id' => 6, 'started_at' => LEAD_JOURNEY_TEST_NOW - 120, 'contact_id' => 44),
	);

	pg_assert_same(array(4, 5), pg_lead_tie_back_ids($visits, $consent_at, $keep_start), 'after the consent and inside the keep period');
	pg_assert_same(array(5), pg_lead_tie_back_ids($visits, LEAD_JOURNEY_TEST_NOW - 3600, $keep_start), 'nothing before the consent');
}

function test_lead_journey_keep_start_on_a_short_month()
{
	pg_assert_same(gmmktime(8, 0, 0, 2, 28, 2026), pg_lead_keep_start(gmmktime(8, 0, 0, 3, 31, 2027), 13), 'the 31st falls to the last day');
	pg_assert_same(gmmktime(8, 0, 0, 2, 29, 2024), pg_lead_keep_start(gmmktime(8, 0, 0, 3, 31, 2025), 13), 'a leap February');
	pg_assert_same(pg_lead_keep_start(LEAD_JOURNEY_TEST_NOW, 13), pg_lead_keep_start(LEAD_JOURNEY_TEST_NOW, 0), 'a bad period falls back to 13 months');
}

function test_lead_journey_score_after_its_half_life()
{
	pg_assert_same(10.0, pg_lead_score_decay(20, 30 * 86400, 30), 'half after one half-life');
	pg_assert_same(5.0, pg_lead_score_decay(20, 60 * 86400, 30), 'a quarter after two');
	pg_assert_same(20.0, pg_lead_score_decay(20, -3600, 30), 'a future time counts as now');
	pg_assert_same(10.0, pg_lead_score_decay(20, 30 * 86400, 0), 'a bad half-life falls back to 30 days');

	$events = array(
		array('kind' => 'form', 'at' => LEAD_JOURNEY_TEST_NOW - 30 * 86400),
		array('kind' => 'booking', 'at' => LEAD_JOURNEY_TEST_NOW),
		array('kind' => 'pricing_page', 'at' => LEAD_JOURNEY_TEST_NOW - 60 * 86400),
		array('kind' => 'download', 'at' => LEAD_JOURNEY_TEST_NOW - 30 * 86400),
		array('kind' => 'not_a_kind', 'at' => LEAD_JOURNEY_TEST_NOW),
	);
	$score = pg_lead_interest_score($events, LEAD_JOURNEY_TEST_NOW);

	// 30 + 12.5 + 7.5 + 2.5 = 52.5.
	pg_assert_same(53, $score['score'], 'faded sum, rounded');
	pg_assert_same(
		array(array('kind' => 'booking', 'points' => 30.0), array('kind' => 'form', 'points' => 12.5), array('kind' => 'download', 'points' => 7.5)),
		$score['reasons'],
		'the top three reasons as codes'
	);
}

function test_lead_journey_score_caps_and_site_weights()
{
	$events = array();
	for ($i = 0; $i < 6; $i++) {
		$events[] = array('kind' => 'booking', 'at' => LEAD_JOURNEY_TEST_NOW);
	}
	pg_assert_same(100, pg_lead_interest_score($events, LEAD_JOURNEY_TEST_NOW)['score'], 'capped at 100');
	pg_assert_same(0, pg_lead_interest_score(array(), LEAD_JOURNEY_TEST_NOW)['score'], 'nothing seen');

	$weights = pg_lead_score_weights(array('form' => 40, 'download' => -5, 'invented' => 50));
	pg_assert_same(40, $weights['form'], 'a site weight overrides');
	pg_assert_same(0, $weights['download'], 'never below 0');
	pg_assert_false(isset($weights['invented']), 'no unknown kind');

	$score = pg_lead_interest_score(array(array('kind' => 'form', 'at' => LEAD_JOURNEY_TEST_NOW - 10 * 86400)), LEAD_JOURNEY_TEST_NOW, array('form' => 40), 10);
	pg_assert_same(20, $score['score'], 'site weight and half-life');
}

function test_lead_journey_quick_return()
{
	pg_assert_true(pg_lead_is_quick_return(LEAD_JOURNEY_TEST_NOW - 7 * 86400, LEAD_JOURNEY_TEST_NOW), 'within 7 days');
	pg_assert_false(pg_lead_is_quick_return(LEAD_JOURNEY_TEST_NOW - 7 * 86400 - 1, LEAD_JOURNEY_TEST_NOW), 'after 7 days');
	pg_assert_false(pg_lead_is_quick_return(0, LEAD_JOURNEY_TEST_NOW), 'a first visit is no return');
	pg_assert_false(pg_lead_is_quick_return(LEAD_JOURNEY_TEST_NOW, LEAD_JOURNEY_TEST_NOW), 'the same visit is no return');
}

function lead_journey_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The body of upgrade_2026_11_0_lead_journey(), CREATE TABLE by table.
function lead_journey_test_step_tables()
{
	$source = lead_journey_test_source('includes/migrations/2026.11.0.php');
	$start = strpos($source, 'function upgrade_2026_11_0_lead_journey()');
	pg_assert_true($start !== false, 'the step is there');
	$body = substr($source, (int) $start);
	$body = substr($body, 0, (int) strpos($body, "\n}\n"));

	preg_match_all('/install_create_table\(\'([a-z_]+)\', "CREATE TABLE \1 \((.*?)\n\t\) (ENGINE=[^"]+)"\);/s', $body, $matches, PREG_SET_ORDER);
	$tables = array();
	foreach ($matches as $match) {
		$tables[$match[1]] = array('body' => $match[2], 'tail' => $match[3]);
	}

	return $tables;
}

function lead_journey_test_columns($body)
{
	$columns = array();
	foreach (preg_split('/,\n/', trim($body)) as $line) {
		$line = trim($line);
		if (preg_match('/^(PRIMARY|UNIQUE|KEY)\b/', $line)) {
			continue;
		}
		$name = strtok($line, " \t");
		$columns[$name] = trim(substr($line, strlen($name)));
	}

	return $columns;
}

function test_lead_journey_migration_step_shape()
{
	$tables = lead_journey_test_step_tables();
	pg_assert_same(array('lead_browsers', 'lead_visits', 'contact_journey', 'contact_scores'), array_keys($tables), 'the four tables');

	$expected = array(
		'lead_browsers' => array('id', 'key_hash', 'contact_id', 'consent_at', 'tied_at', 'last_seen_at', 'created_at'),
		'lead_visits' => array('id', 'browser_id', 'contact_id', 'visitor_id', 'started_at', 'channel', 'campaign', 'referrer_host', 'landing_page', 'pages'),
		'contact_journey' => array('id', 'contact_id', 'kind', 'at', 'visit_id', 'ref_id', 'detail', 'first_visit_id', 'last_visit_id'),
		'contact_scores' => array('contact_id', 'interest', 'fit', 'reasons', 'topics', 'points', 'hot', 'hot_at', 'returned_at', 'last_visit_at', 'touched_at', 'scored_at'),
	);

	$keys = array(
		'lead_browsers' => array('PRIMARY KEY (id)', 'UNIQUE KEY uniq_key_hash (key_hash)', 'KEY idx_contact (contact_id)', 'KEY idx_last_seen (last_seen_at)'),
		'lead_visits' => array('PRIMARY KEY (id)', 'KEY idx_browser_started (browser_id, started_at)', 'KEY idx_contact_started (contact_id, started_at)', 'KEY idx_started (started_at)', 'KEY idx_visitor (visitor_id)'),
		'contact_journey' => array('PRIMARY KEY (id)', 'KEY idx_contact_at (contact_id, at)', 'KEY idx_kind_at (kind, at)', 'KEY idx_at (at)'),
		'contact_scores' => array('PRIMARY KEY (contact_id)', 'KEY idx_hot_interest (hot, interest)', 'KEY idx_touched (touched_at)'),
	);

	foreach ($expected as $table => $names) {
		if (!isset($tables[$table])) {
			continue;
		}
		$columns = lead_journey_test_columns($tables[$table]['body']);
		pg_assert_same($names, array_keys($columns), $table . ' columns');
		pg_assert_same('ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci', $tables[$table]['tail'], $table . ' engine and collation');

		foreach ($columns as $name => $definition) {
			if (strpos($definition, 'TEXT') === 0) {
				pg_assert_same('TEXT NULL', $definition, $table . '.' . $name . ' TEXT is NULL');
				continue;
			}
			pg_assert_contains('NOT NULL', $definition, $table . '.' . $name . ' NOT NULL');
			if (strpos($definition, 'AUTO_INCREMENT') === false) {
				pg_assert_contains('DEFAULT', $definition, $table . '.' . $name . ' has a default');
			}
			pg_assert_false((bool) preg_match('/ip|agent/i', $name), $table . '.' . $name . ' is no IP or user agent');
		}

		foreach ($keys[$table] as $key) {
			pg_assert_contains($key, $tables[$table]['body'], $table . ' ' . $key);
		}
	}

	pg_assert_contains('CHAR(64) CHARACTER SET ascii', $tables['lead_browsers']['body'] ?? '', 'the key hash is ascii');

	$source = lead_journey_test_source('includes/migrations/2026.11.0.php');
	pg_assert_contains("upgrade_2026_11_0_lead_journey();            // 11.0.265", $source, 'called with its label');

	$install = lead_journey_test_source('install/index.php');
	foreach (array_keys($expected) as $table) {
		pg_assert_contains("\t\t'" . $table . "',\n", $install, $table . ' in get_tables()');
	}
}

function test_lead_journey_no_key_without_the_yes_at_the_store()
{
	foreach (array('no', 'unanswered', 'off') as $consent) {
		$plan = pg_lead_sync_plan($consent, null, 0, false);
		pg_assert_false($plan['write_key'], $consent . ': no key written');
		pg_assert_false($plan['row'], $consent . ': no browser row');
		pg_assert_same('', $plan['forget'], $consent . ': nothing to delete');
	}

	$plan = pg_lead_sync_plan('yes', null, 0, false);
	pg_assert_true($plan['write_key'] && $plan['row'], 'the yes writes a key and its row');

	$key = str_repeat('ab', 32);
	$plan = pg_lead_sync_plan('yes', $key, 0, false);
	pg_assert_false($plan['write_key'], 'a key there is kept');
	pg_assert_true(pg_lead_sync_plan('yes', 'not-a-key', 0, false)['write_key'], 'a cookie that is no key of ours gets a new key');

	pg_assert_same('off', pg_lead_consent_state(false, array('marketing' => true)), 'notice off');
	pg_assert_same('unanswered', pg_lead_consent_state(true, array('analytics' => true)), 'marketing not answered');
	pg_assert_same('yes', pg_lead_consent_state(true, array('marketing' => true)), 'marketing yes');
	pg_assert_same('no', pg_lead_consent_state(true, array('marketing' => false)), 'marketing refused');

	$store = lead_journey_test_source('includes/fn/lead_journey_store.php');
	pg_assert_contains("pg_consent_setcookie('marketing', PG_LEAD_COOKIE", $store, 'the key is written through the notice');
	pg_assert_contains('INSERT IGNORE INTO lead_browsers (key_hash,', $store, 'only the hash is stored');
}

function test_lead_journey_refusal_after_a_yes_at_the_store()
{
	$key = str_repeat('0f', 32);
	$plan = pg_lead_sync_plan('no', $key, 9, false);
	pg_assert_same('key', $plan['forget'], 'the row found by the key goes');
	pg_assert_true($plan['delete_cookie'], 'the cookie is deleted');

	$plan = pg_lead_sync_plan('no', null, 9, false);
	pg_assert_same('session', $plan['forget'], 'the notice deleted the cookie already: the session names the row');
	pg_assert_false($plan['delete_cookie'], 'no cookie to delete');

	pg_assert_true(in_array(PG_LEAD_COOKIE, pg_consent_revoke_names()['marketing'], true), 'the notice deletes the cookie with marketing');

	$store = lead_journey_test_source('includes/fn/lead_journey_store.php');
	pg_assert_contains('DELETE FROM lead_visits WHERE browser_id = ? AND contact_id = 0', $store, 'untied visits go, tied ones stay');
	pg_assert_contains('pg_lead_consent_changed($changes);', lead_journey_test_source('consent_answer.php'), 'the answer endpoint forgets the browser');
}

function test_lead_journey_panel_user_gets_no_row()
{
	$plan = pg_lead_sync_plan('yes', null, 0, true);
	pg_assert_same('panel_user', $plan['verdict'], 'a panel user');
	pg_assert_false($plan['write_key'] || $plan['row'], 'no key and no row');
	pg_assert_same('panel_user', lead_journey_test_tie(0, 4, 'sign_in', true)['verdict'], 'no tie');
}

function test_lead_journey_each_tie_path_names_its_act()
{
	pg_assert_contains("pg_lead_tie('newsletter', (int) \$pg_fs_link['c']", lead_journey_test_source('confirm_subscription.php'), 'the double opt-in yes');
	pg_assert_contains("pg_lead_tie('newsletter', (int) \$contact['id']", lead_journey_test_source('email_preferences.php'), 'a yes on the e-mail preferences');

	$auth = lead_journey_test_source('includes/authentication.php');
	pg_assert_contains('pg_lead_sign_in((int) $user_id);', $auth, 'a sign-in');
	pg_assert_contains('$signed_in_as_other', $auth, 'signing in as someone else is no act of theirs');
	pg_assert_contains("pg_lead_tie('sign_in', \$contact_id", lead_journey_test_source('includes/fn/lead_journey_store.php'), 'the account\'s contact');

	pg_assert_contains("pg_lead_tie('campaign_click', (int) \$contact_id, (int) \$email_campaign_id)", lead_journey_test_source('view_email_campaign.php'), 'the web version opened by its recipient\'s code');
	pg_assert_contains("pg_lead_tie('order', (int) \$contact_id", lead_journey_test_source('submit_order.php'), 'a placed order');
	pg_assert_contains("pg_lead_tie('form', (int) \$contact_id, (int) \$form_id);", lead_journey_test_source('custom_form.php'), 'a form sent');

	foreach (array('newsletter', 'sign_in', 'form', 'campaign_click', 'order') as $act) {
		pg_assert_true(in_array($act, pg_lead_tie_acts(), true), $act . ' is an act');
	}
}

function test_lead_journey_tie_back_at_the_store()
{
	$store = lead_journey_test_source('includes/fn/lead_journey_store.php');
	pg_assert_contains('pg_lead_tie_back_ids($visits, $browser[\'consent_at\'], pg_lead_keep_start($now))', $store, 'the pure rule picks the visits');
	pg_assert_contains('AND contact_id = 0", array((string) $contact_id)', $store, 'a visit with a contact is never moved');

	$visits = array(
		array('id' => 1, 'started_at' => 1000, 'contact_id' => 0),
		array('id' => 2, 'started_at' => 3000, 'contact_id' => 0),
		array('id' => 3, 'started_at' => 4000, 'contact_id' => 8),
	);
	pg_assert_same(array(2), pg_lead_tie_back_ids($visits, 2000, 0), 'only since the consent, only untied');
}

function test_lead_journey_store_helpers()
{
	pg_assert_same('', pg_lead_key_valid(str_repeat('A', 64)), 'upper case is no key');
	pg_assert_same('', pg_lead_key_valid(array()), 'an array is no key');
	pg_assert_same(64, strlen(pg_lead_key_hash('x')), 'sha256');

	pg_assert_same(gmmktime(12, 0, 0, 12, 20, 2027), pg_lead_cookie_expires(LEAD_JOURNEY_TEST_NOW), '13 months');
	pg_assert_same(gmmktime(0, 0, 0, 2, 28, 2027), pg_lead_cookie_expires(gmmktime(0, 0, 0, 1, 31, 2026)), 'a short month');

	$pages = pg_lead_pages_add(array(), 'home', 100);
	$pages = pg_lead_pages_add($pages, 'pricing', 130);
	pg_assert_same(array(array('home', 30, 100), array('pricing', 0, 130)), $pages, 'seconds of the page before');

	$full = array();
	for ($i = 0; $i < PG_LEAD_PAGES_MAX + 5; $i++) {
		$full = pg_lead_pages_add($full, 'p' . $i, $i);
	}
	pg_assert_same(PG_LEAD_PAGES_MAX, count($full), 'capped');
	pg_assert_same('p0', $full[0][0], 'the landing page stays');

	$copy = pg_lead_visit_copy(array('referring_host_name' => 'WWW.Example.com', 'tracking_code' => 'news_2026', 'utm_campaign' => ''), 'email', str_repeat('x', 300));
	pg_assert_same('news_2026', $copy['campaign'], 'the tracking code when no UTM campaign');
	pg_assert_same('www.example.com', $copy['referrer_host'], 'host lower-cased');
	pg_assert_same(255, strlen($copy['landing_page']), 'cut to its column');
}

function test_lead_journey_key_is_synced_before_the_page_prints()
{
	$seo = lead_journey_test_source('includes/fn/seo.php');
	$start = strpos($seo, 'function init_tracking()');
	$end = strpos($seo, "\n}\n", (int) $start);
	pg_assert_contains('pg_lead_request_browser();', substr($seo, (int) $start, (int) $end - (int) $start), 'init_tracking() writes the cookie before output');
	pg_assert_contains('$browser = pg_lead_request_browser();', lead_journey_test_source('includes/fn/lead_journey_store.php'), 'the visit uses the same synced browser');
}
