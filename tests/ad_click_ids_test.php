<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for includes/fn/ad_click_ids.php: only the listed parameters are
 * read, a value outside the bound is dropped, the ids wait in the session
 * only, reach a purchase row only with consent, and leave the session when
 * consent is taken back.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function ad_click_ids_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The ids a consenting buyer's purchase row gets at the payment step, as
// pg_ad_conversion_payment_step() composes them; null when the step takes
// the ids off (no consent).
function ad_click_ids_test_row_ids($service, $notice_on, array $choices, $held)
{
	$settings = array($service => array('connected' => true, 'events' => array('purchase'), 'person_notice_off' => false, 'without_person' => array()));
	$plan = pg_ad_conversion_payment_step_plan($notice_on, $choices, $settings);

	if (($plan[$service] ?? '') !== 'keep') {
		return null;
	}

	return pg_ad_conversion_ids_for($service, array(), '203.0.113.5', 'Browser') + pg_ad_click_ids_for($service, $held);
}

// A parameter not in the list is not read.
function test_ad_click_ids_only_listed_parameters()
{
	pg_assert_same(array('meta_capi' => 'fbclid', 'ga4_mp' => 'gclid', 'tiktok_events' => 'ttclid'), pg_ad_click_id_parameters(), 'one parameter per service');

	$found = pg_ad_click_ids_found(array('msclkid' => 'abc', 'utm_source' => 'meta', 'fbclid' => 'IwAR1abc_-x', 'FBCLID' => 'zzz'), 1700000000);
	pg_assert_same(array('meta_capi' => array('id' => 'IwAR1abc_-x', 'at' => 1700000000)), $found, 'only fbclid read');

	$found = pg_ad_click_ids_found(array('gclid' => 'Cj0KCQ', 'ttclid' => 'E.C.P.abc'), 5);
	pg_assert_same(array('ga4_mp' => array('id' => 'Cj0KCQ', 'at' => 5), 'tiktok_events' => array('id' => 'E.C.P.abc', 'at' => 5)), $found, 'gclid and ttclid');

	pg_assert_same(array(), pg_ad_click_ids_found(array('gclid' => array('x')), 5), 'an array is no value');
	pg_assert_same(array(), pg_ad_click_ids_hold(array('bing' => array('id' => 'x', 'at' => 1)), array()), 'a service not in the list is not held');
}

// A value outside the bound is dropped, never cut.
function test_ad_click_ids_bound()
{
	pg_assert_same('abc', pg_ad_click_id_value('abc'), 'plain');
	pg_assert_same(str_repeat('a', 500), pg_ad_click_id_value(str_repeat('a', 500)), 'at the length bound');
	pg_assert_same('', pg_ad_click_id_value(str_repeat('a', 501)), 'over the length bound');
	pg_assert_same('', pg_ad_click_id_value(''), 'empty');
	pg_assert_same('', pg_ad_click_id_value('abc def'), 'space');
	pg_assert_same('', pg_ad_click_id_value('abc<script>'), 'markup');
	pg_assert_same('', pg_ad_click_id_value("abc\n"), 'line end');
	pg_assert_same('', pg_ad_click_id_value('çğü'), 'not ASCII');
	pg_assert_same('', pg_ad_click_id_value(12345), 'not a string');

	pg_assert_same(array(), pg_ad_click_ids_found(array('fbclid' => str_repeat('a', 501)), 5), 'a long fbclid is not found');
}

// The fields of each service's row and the time Meta's fbc carries.
function test_ad_click_ids_row_fields()
{
	$held = pg_ad_click_ids_hold(null, pg_ad_click_ids_found(array('fbclid' => 'IwAR1', 'gclid' => 'Cj0', 'ttclid' => 'E.C.P'), 1700000000));

	pg_assert_same(array('fbc' => 'fb.1.1700000000000.IwAR1'), pg_ad_click_ids_for('meta_capi', $held), 'fbc with the click time in milliseconds');
	pg_assert_same(array('gclid' => 'Cj0'), pg_ad_click_ids_for('ga4_mp', $held), 'gclid');
	pg_assert_same(array('ttclid' => 'E.C.P'), pg_ad_click_ids_for('tiktok_events', $held), 'ttclid');
	pg_assert_same(array(), pg_ad_click_ids_for('ga4_mp', array()), 'nothing held');

	$newer = pg_ad_click_ids_hold($held, pg_ad_click_ids_found(array('fbclid' => 'IwAR2'), 1700000100));
	pg_assert_same(array('id' => 'IwAR2', 'at' => 1700000100), $newer['meta_capi'], 'a new click replaces the earlier one');
	pg_assert_same(array('id' => 'Cj0', 'at' => 1700000000), $newer['ga4_mp'], 'another service keeps its click');

	// A browser's own _fbc cookie stays first.
	$row = pg_ad_conversion_ids_for('meta_capi', array('_fbc' => 'fb.1.1.cookie'), '203.0.113.5', 'Browser') + pg_ad_click_ids_for('meta_capi', $held);
	pg_assert_same('fb.1.1.cookie', $row['fbc'], 'the cookie wins');
}

// With no consent the row's ids are empty; when consent comes later the held
// click fills them.
function test_ad_click_ids_consent_later()
{
	$held = pg_ad_click_ids_hold(null, pg_ad_click_ids_found(array('gclid' => 'Cj0'), 1700000000));

	pg_assert_true(pg_ad_click_ids_may_wait(true, array()), 'before any answer the id waits');
	pg_assert_true(pg_ad_click_ids_may_wait(false, array()), 'notice off: the id waits for the service box');
	pg_assert_false(pg_ad_click_ids_may_wait(true, array('marketing' => false)), 'refused: the id does not wait');

	pg_assert_same(null, ad_click_ids_test_row_ids('ga4_mp', true, array(), $held), 'no answer: no ids');
	pg_assert_same(null, ad_click_ids_test_row_ids('ga4_mp', false, array(), $held), 'notice off, box off: no ids');

	$row = ad_click_ids_test_row_ids('ga4_mp', true, array('marketing' => true), $held);
	pg_assert_same('Cj0', $row['gclid'] ?? '', 'consent came: gclid in the row');
}

// The session: the capture holds the id there, the reader reads it, a
// refusal of marketing empties it.
function test_ad_click_ids_session()
{
	$had_session = isset($_SESSION);
	$saved = $had_session ? $_SESSION : null;
	$_SESSION = array();

	pg_ad_click_ids_capture(array('ttclid' => 'E.C.P.abc', 'other' => 'x'));
	pg_assert_same(array('ttclid' => 'E.C.P.abc'), pg_ad_click_ids_held_for('tiktok_events'), 'held for TikTok');
	pg_assert_same(array(), pg_ad_click_ids_held_for('meta_capi'), 'nothing for Meta');

	pg_ad_click_ids_capture(array());
	pg_assert_same(array('ttclid' => 'E.C.P.abc'), pg_ad_click_ids_held_for('tiktok_events'), 'a page without a click keeps it');

	pg_ad_click_ids_consent_changed(array(array('purpose' => 'analytics', 'status' => 'refused')));
	pg_assert_true(isset($_SESSION['ad_click_ids']), 'statistics refused: kept');

	pg_ad_click_ids_consent_changed(array(array('purpose' => 'marketing', 'status' => 'given')));
	pg_assert_true(isset($_SESSION['ad_click_ids']), 'marketing given: kept');

	pg_ad_click_ids_consent_changed(array(array('purpose' => 'marketing', 'status' => 'refused')));
	pg_assert_false(isset($_SESSION['ad_click_ids']), 'marketing taken back: the session is empty');
	pg_assert_same(array(), pg_ad_click_ids_held_for('tiktok_events'), 'nothing left to read');

	pg_ad_click_ids_capture(array());
	pg_assert_same(array(), $_SESSION, 'a page without a click writes nothing');

	if ($had_session) {
		$_SESSION = $saved;
	} else {
		unset($_SESSION);
	}
}

// Wired where the decision put it: the capture in get_page.php, the one
// reader call at the payment step, the refusal in consent_answer.php; no
// table or cookie write in the module.
function test_ad_click_ids_wiring()
{
	pg_assert_contains('pg_ad_click_ids_capture($_GET);', ad_click_ids_test_source('get_page.php'), 'captured when a visitor page opens');
	pg_assert_contains('pg_ad_click_ids_consent_changed($changes);', ad_click_ids_test_source('consent_answer.php'), 'cleared on a refusal');

	$conversions = ad_click_ids_test_source('includes/fn/ad_conversions.php');
	pg_assert_same(1, substr_count($conversions, 'pg_ad_click_ids_'), 'one call in ad_conversions.php');
	pg_assert_contains('+ pg_ad_click_ids_held_for($service)', $conversions, 'the reader at the payment step');

	$module = ad_click_ids_test_source('includes/fn/ad_click_ids.php');
	pg_assert_false((bool) preg_match('/\b(db_q|db|setcookie|pg_consent_setcookie)\s*\(/', $module), 'no table and no cookie');
}
