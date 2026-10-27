<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the cookie consent helpers in includes/fn/consent.php: reading
 * the pg_consent cookie value, holding a script back for a category, finding
 * the scripts a page holds back on its own, the notice's first view and
 * switches, and the Google Consent Mode default at the top of <head>.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Each answered category comes back with its flag; unanswered ones are absent.
function test_consent_parse_reads_answers()
{
	pg_assert_same(array('analytics' => true, 'marketing' => false), pg_consent_parse('1.a1.m0'), 'both');
	pg_assert_same(array('analytics' => false), pg_consent_parse('1.a0'), 'one answered');
	pg_assert_same(array(), pg_consent_parse('1'), 'decided with no optional category');
}

// Unknown versions, garbage and non-strings are no answer; unknown parts are skipped.
function test_consent_parse_refuses_malformed_values()
{
	foreach (array('', '2.a1', 'a1.m1', 'yes', '1a1', null, array('1.a1'), 1) as $raw) {
		pg_assert_same(array(), pg_consent_parse($raw), var_export($raw, true));
	}

	pg_assert_same(array('marketing' => true), pg_consent_parse('1.x1.a2.m1.a'), 'unknown parts skipped');
}

// Without the setting (constant undefined here) every category is allowed.
function test_consent_allows_everything_when_off()
{
	pg_assert_false(pg_consent_enabled(), 'off in the test runner');
	pg_assert_true(pg_consent_allows('analytics'), 'analytics');
	pg_assert_true(pg_consent_allows('marketing'), 'marketing');
}

// Inline and external scripts become inert; src moves to data-pg-src, an old type goes.
function test_consent_hold_scripts_makes_tags_inert()
{
	$html = '<script async src="https://www.googletagmanager.com/gtag/js?id=G-1"></script>'
		. '<script>gtag(\'js\', new Date());</script>'
		. '<script type="text/javascript" data-src="keep">x()</script>';

	$held = pg_consent_hold_scripts($html, 'analytics');

	pg_assert_same(
		'<script type="text/plain" data-pg-consent="analytics" data-pg-consent-builtin="1" async data-pg-src="https://www.googletagmanager.com/gtag/js?id=G-1"></script>'
		. '<script type="text/plain" data-pg-consent="analytics" data-pg-consent-builtin="1">gtag(\'js\', new Date());</script>'
		. '<script type="text/plain" data-pg-consent="analytics" data-pg-consent-builtin="1" data-src="keep">x()</script>',
		$held
	);

	pg_assert_same($held, pg_consent_hold_scripts($held, 'analytics'), 'holding twice changes nothing');
}

// Tagged scripts are found by category, named or not, each name once; the
// software's own held tags are not among them.
function test_consent_page_services_lists_tagged_scripts()
{
	$content = '<script type="text/plain" data-pg-consent="marketing" data-pg-consent-name="Meta Pixel">a()</script>'
		. "<script data-pg-consent='analytics' type='text/plain' src='x.js'></script>"
		. '<script type="text/plain" data-pg-consent="marketing" data-pg-consent-name="Meta Pixel">b()</script>'
		. '<script type="text/plain" data-pg-consent="marketing" data-pg-consent-name="Tom &amp; Co">c()</script>'
		. '<script type="text/plain" data-pg-consent="other">d()</script>'
		. '<script>e()</script>'
		. pg_consent_hold_scripts('<script src="https://www.googletagmanager.com/gtag/js?id=G-1"></script>', 'analytics');

	pg_assert_same(
		array('analytics' => array(''), 'marketing' => array('Meta Pixel', 'Tom & Co')),
		pg_consent_page_services($content)
	);

	pg_assert_same(array('analytics' => array(), 'marketing' => array()), pg_consent_page_services('<p>none</p>'), 'nothing tagged');
}

// The list the Translations screen takes in is every lang() key the notice
// prints: a key added to the notice and missing here would stay untranslated
// until a visitor happened to see it.
function test_consent_ui_keys_match_the_notice()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/consent.php');

	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $source, $matches);

	$used = array_values(array_unique(array_map('stripslashes', $matches[1])));
	$listed = pg_consent_ui_keys();

	sort($used);
	sort($listed);

	pg_assert_same($used, $listed);
}

// The cookies the software writes on visitor pages are in the notice's
// list: the view mode a signed-in user gets on every page (get_page.php,
// update_view_page_mode.php) and the language the redirect remembers.
function test_consent_catalogue_lists_the_view_mode_and_language_cookies()
{
	$catalogue = pg_consent_catalogue('<p>page</p>');
	$names = array();

	foreach ($catalogue['necessary'] as $row) {
		$names[] = $row[0];
	}

	pg_assert_true(in_array('software[view_page_mode]', $names, true), 'the view mode of a signed-in user');

	foreach (array('get_page.php', 'update_view_page_mode.php') as $file) {
		pg_assert_contains("setcookie('software[view_page_mode]'", (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file), $file . ' still writes it');
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/consent.php');
	$from = strpos($source, 'function pg_consent_catalogue(');
	$section = substr($source, $from, strpos($source, "\n}\n", $from) - $from);

	pg_assert_contains("pg_lang_redirect_enabled()", $section, 'the language cookie only where the redirect writes it');
	pg_assert_contains("array(PG_LANGUAGE_COOKIE, true, lang('", $section, 'the language the visitor chose');
}

// The page cache's marker cookie is listed as necessary, only while the cache
// is on (it is off in the test runner, so the row is absent here).
function test_consent_catalogue_lists_the_page_cache_marker()
{
	$names = array();

	foreach (pg_consent_catalogue('<p>page</p>')['necessary'] as $row) {
		$names[] = $row[0];
	}

	pg_assert_false(in_array(PG_PAGE_CACHE_MARKER, $names, true), 'not while the cache is off');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/consent.php');
	$from = strpos($source, 'function pg_consent_catalogue(');
	$section = substr($source, $from, strpos($source, "
}
", $from) - $from);

	pg_assert_contains('(pg_page_cache_ttl() > 0)', $section, 'only while the cache is on');
	pg_assert_contains("array(PG_PAGE_CACHE_MARKER, true, lang('", $section, 'as a necessary cookie');
	pg_assert_contains("setcookie(PG_PAGE_CACHE_MARKER", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/page_cache.php'), 'page_cache.php still writes it');
}

// On a page in a language with no language file only the cookie window is
// read from the site's translations; the rest of the software's wording
// never is, whatever the request.
function test_consent_only_the_cookie_window_is_translated()
{
	pg_assert_same(null, pg_tr_ui_text('Cancel'), 'a word of the dynamic code block');
	pg_assert_same(null, pg_tr_ui_text('Deactivate Fullscreen Mode'), 'a word of the toolbar');
	pg_assert_true(in_array('Accept all', pg_consent_ui_keys(), true), 'a word of the cookie window is listed');
}

// A page that holds a script of each optional category, so the notice asks
// about both (the test runner has no visitor tracking or Google Analytics).
function consent_test_page_with_categories()
{
	return '<html><head><title>t</title></head><body>'
		. '<script type="text/plain" data-pg-consent="analytics" data-pg-consent-name="Hotjar"></script>'
		. '<script type="text/plain" data-pg-consent="marketing" data-pg-consent-name="Meta Pixel"></script>'
		. '</body></html>';
}

// The buttons of one view of the notice, as (class, action) pairs in order.
// A view runs from its marker to the next view's, the last one to the end.
function consent_test_view_buttons($markup, $view)
{
	$start = strpos($markup, 'data-pg-cc-view="' . $view . '"');

	if ($start === false) {
		return null;
	}

	$end = strpos($markup, 'data-pg-cc-view=', $start + 1);
	$section = substr($markup, $start, (($end === false) ? strlen($markup) : $end) - $start);

	preg_match_all('/<button type="button" class="([^"]*)" data-pg-cc="([^"]+)">/', $section, $buttons, PREG_SET_ORDER);

	return array_map(function ($button) {
		return array($button[1], $button[2]);
	}, $buttons);
}

// Refusing is offered exactly like accepting: the first view has Settings,
// Reject all and Accept all, none of them drawn as the one to press, with
// the two answers in the equal-width row.
function test_consent_notice_offers_refusal_like_acceptance()
{
	$markup = pg_consent_markup(consent_test_page_with_categories());

	pg_assert_same(
		array(array('pg-cc-btn', 'settings'), array('pg-cc-btn', 'reject'), array('pg-cc-btn', 'accept')),
		consent_test_view_buttons($markup, 'intro'),
		'first view'
	);

	pg_assert_contains('<div class="pg-cc-actions pg-cc-choices">', $markup, 'equal-width row');
	pg_assert_contains('>Reject all</button>', $markup, 'reject label');
	pg_assert_contains('>Accept all</button>', $markup, 'accept label');
	pg_assert_contains('data-categories="analytics,marketing"', $markup, 'both categories asked');

	pg_assert_same(
		array(array('pg-cc-btn', 'reject'), array('pg-cc-btn pg-cc-primary', 'save')),
		consent_test_view_buttons($markup, 'prefs'),
		'settings view unchanged'
	);
}

// With only necessary cookies there is nothing to refuse: the first view
// keeps Settings and OK.
function test_consent_notice_without_optional_categories_keeps_ok()
{
	$markup = pg_consent_markup('<html><head></head><body><p>plain</p></body></html>');

	pg_assert_same(
		array(array('pg-cc-btn', 'settings'), array('pg-cc-btn pg-cc-primary', 'accept')),
		consent_test_view_buttons($markup, 'intro')
	);

	pg_assert_false(strpos($markup, 'pg-cc-choices') !== false, 'no answer row');
	pg_assert_contains('data-categories=""', $markup, 'nothing to ask');
}

// No optional category is ticked for the visitor; pg_consent.js turns on
// only what the stored answer allows.
function test_consent_notice_ticks_no_category()
{
	$markup = pg_consent_markup(consent_test_page_with_categories());

	preg_match_all('/<input\b[^>]*class="pg-cc-switch"[^>]*>/', $markup, $switches);

	pg_assert_same(2, count($switches[0]), 'one switch per optional category');

	foreach ($switches[0] as $switch) {
		pg_assert_same(0, preg_match('/\schecked\b/', $switch), $switch);
	}
}

// Statistics decides analytics storage, marketing the three advertising
// types; unanswered and refused are denied.
function test_consent_mode_states_follow_answers()
{
	pg_assert_same(
		array('ad_storage' => 'denied', 'ad_user_data' => 'denied', 'ad_personalization' => 'denied', 'analytics_storage' => 'denied'),
		pg_consent_mode_states(array()),
		'no answer'
	);

	pg_assert_same(
		array('ad_storage' => 'denied', 'ad_user_data' => 'denied', 'ad_personalization' => 'denied', 'analytics_storage' => 'granted'),
		pg_consent_mode_states(pg_consent_parse('1.a1.m0')),
		'statistics only'
	);

	pg_assert_same(
		array('ad_storage' => 'granted', 'ad_user_data' => 'granted', 'ad_personalization' => 'granted', 'analytics_storage' => 'denied'),
		pg_consent_mode_states(array('marketing' => true)),
		'marketing only, statistics unanswered'
	);
}

// The default is a gtag-style command queued on dataLayer; window.gtag is
// not defined by it.
function test_consent_mode_script_queues_the_default()
{
	pg_assert_same(
		'<script data-pg-consent-mode>window.dataLayer=window.dataLayer||[];'
		. '(function(){function gtag(){dataLayer.push(arguments);}'
		. 'gtag("consent","default",{"ad_storage":"denied","ad_user_data":"denied","ad_personalization":"denied","analytics_storage":"granted"});})();</script>',
		pg_consent_mode_script(array('analytics' => true, 'marketing' => false))
	);
}

// The script's update uses the same state names as the default.
function test_consent_mode_states_match_the_script()
{
	$script = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_consent.js');

	foreach (array_keys(pg_consent_mode_states(array())) as $state) {
		pg_assert_contains($state . ':', $script, $state);
	}

	pg_assert_contains("'consent', 'update'", $script, 'sent as an update');
}

// Straight after <head>, or after a character-set <meta> that opens it;
// false without a <head> tag (<header> is not one).
function test_consent_mode_position_opens_the_head()
{
	$page = '<!DOCTYPE html><html><head><title>t</title><script src="a.js"></script></head><body></body></html>';
	pg_assert_same(strpos($page, '<title>'), pg_consent_mode_position($page), 'plain head');

	$page = "<html>\n<HEAD lang=\"tr\">\n    <meta charset=\"utf-8\">\n    <script></script>";
	pg_assert_same(strpos($page, "\n    <script>"), pg_consent_mode_position($page), 'after the charset meta');

	$page = '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><script></script>';
	pg_assert_same(strpos($page, '<script>'), pg_consent_mode_position($page), 'after the http-equiv charset');

	$page = '<head><title>t</title><meta charset="utf-8">';
	pg_assert_same(strlen('<head>'), pg_consent_mode_position($page), 'a later charset meta stays where it is');

	pg_assert_false(pg_consent_mode_position('<html><body><header>x</header></body></html>'), 'no head');
}

// The default runs before every script of the page, Google's included, and
// the notice sits before the last </body>.
function test_consent_insert_puts_the_default_before_any_script()
{
	$page = '<!DOCTYPE html><html><head><meta charset="utf-8">'
		. '<script async src="https://www.googletagmanager.com/gtag/js?id=G-1"></script>'
		. '</head><body><p>x</p></body></html>';

	$result = pg_consent_insert($page, array());
	$default = strpos($result, '<script data-pg-consent-mode>');

	pg_assert_same(strlen('<!DOCTYPE html><html><head><meta charset="utf-8">'), $default, 'top of the head');
	pg_assert_true($default < strpos($result, 'googletagmanager.com'), 'before the Google tag');
	pg_assert_contains('"analytics_storage":"denied"', $result, 'nothing answered, nothing granted');
	pg_assert_same(1, substr_count($result, 'id="pg-cc"'), 'one notice');
	pg_assert_true(strpos($result, 'id="pg-cc"') < strrpos($result, '</body>'), 'notice inside the body');
	pg_assert_same(1, substr_count($result, 'data-pg-consent-mode'), 'one default');
}

// No </body>: not a whole page, left alone. No <head>: the notice only.
function test_consent_insert_needs_a_whole_page()
{
	pg_assert_same('<rss><item/></rss>', pg_consent_insert('<rss><item/></rss>', array()), 'feed');

	$result = pg_consent_insert('<p>fragment</p></body>', array('analytics' => true));

	pg_assert_contains('id="pg-cc"', $result, 'notice');
	pg_assert_false(strpos($result, 'data-pg-consent-mode') !== false, 'no default without a head');
}

// Only a value the notice's script writes is an answer: version 1, known
// letters, each at most once.
function test_consent_answer_valid_is_strict()
{
	foreach (array('1', '1.a1', '1.m0', '1.a1.m0', '1.m1.a0') as $raw) {
		pg_assert_true(pg_consent_answer_valid($raw), $raw);
	}

	foreach (array('', '2.a1', 'a1', '1.', '1.x1', '1.a2', '1.a1.a0', '1.a1.m0.', '1.A1', " 1.a1", null, array('1.a1'), 1) as $raw) {
		pg_assert_false(pg_consent_answer_valid($raw), var_export($raw, true));
	}
}

// One row per category whose answer differs from the cookie before it.
function test_consent_answer_changes_lists_what_changed()
{
	pg_assert_same(
		array(
			array('channel' => 'cookie', 'purpose' => 'analytics', 'status' => 'granted'),
			array('channel' => 'cookie', 'purpose' => 'marketing', 'status' => 'granted'),
		),
		pg_consent_answer_changes('', '1.a1.m1'),
		'first answer, accept all'
	);

	pg_assert_same(
		array(array('channel' => 'cookie', 'purpose' => 'marketing', 'status' => 'refused')),
		pg_consent_answer_changes('1.a1.m1', '1.a1.m0'),
		'marketing withdrawn'
	);

	pg_assert_same(
		array(array('channel' => 'cookie', 'purpose' => 'analytics', 'status' => 'refused')),
		pg_consent_answer_changes('garbage', '1.a0'),
		'an unreadable cookie before counts as no answer'
	);

	pg_assert_same(array(), pg_consent_answer_changes('1.a1.m0', '1.m0.a1'), 'same answer, other order');
	pg_assert_same(array(), pg_consent_answer_changes('', '1'), 'nothing optional to answer');
	pg_assert_same(array(), pg_consent_answer_changes('1.a1.m1', '1.a1'), 'a category not in the answer is not a change');
}

// An answer the script could not have written records nothing.
function test_consent_answer_changes_refuses_a_malformed_answer()
{
	foreach (array('', '1.a1.a0', '1.z1', '2.a1', null) as $raw) {
		pg_assert_same(null, pg_consent_answer_changes('', $raw), var_export($raw, true));
	}

	// Longer than any value the script writes: refused before it is split.
	pg_assert_same(null, pg_consent_answer_changes(str_repeat('1.a1', 9), '1.a1'), 'long value before');
	pg_assert_same(null, pg_consent_answer_changes(str_repeat('1.a1', 8 * 1024 * 1024 / 4), '1.a1'), 'very long value before');
	pg_assert_false(pg_consent_answer_valid('1' . str_repeat('.a1', 11)), 'long answer');
	pg_assert_same(array(), pg_consent_answer_changes('1.a1.m1', '1.a1.m1'), 'a value at the usual length');
}

// The endpoint takes posts from the site's own pages only: Sec-Fetch-Site
// when the browser sends it, otherwise the Origin against the host.
function test_consent_answer_same_origin()
{
	pg_assert_true(pg_consent_answer_same_origin(array('HTTP_SEC_FETCH_SITE' => 'same-origin', 'HTTP_HOST' => 'example.com')), 'fetch metadata');
	pg_assert_false(pg_consent_answer_same_origin(array('HTTP_SEC_FETCH_SITE' => 'cross-site', 'HTTP_ORIGIN' => 'https://example.com', 'HTTP_HOST' => 'example.com')), 'cross-site wins over a matching origin');
	pg_assert_false(pg_consent_answer_same_origin(array('HTTP_SEC_FETCH_SITE' => 'same-site', 'HTTP_HOST' => 'example.com')), 'a sibling subdomain');
	pg_assert_true(pg_consent_answer_same_origin(array('HTTP_ORIGIN' => 'https://Example.com', 'HTTP_HOST' => 'example.com')), 'origin, host case');
	pg_assert_true(pg_consent_answer_same_origin(array('HTTP_ORIGIN' => 'http://example.com:8080', 'HTTP_HOST' => 'example.com:8080')), 'origin with a port');
	pg_assert_false(pg_consent_answer_same_origin(array('HTTP_ORIGIN' => 'https://evil.test', 'HTTP_HOST' => 'example.com')), 'other origin');
	pg_assert_false(pg_consent_answer_same_origin(array('HTTP_ORIGIN' => 'https://example.com.evil.test', 'HTTP_HOST' => 'example.com')), 'suffix');
	pg_assert_false(pg_consent_answer_same_origin(array('HTTP_ORIGIN' => 'null', 'HTTP_HOST' => 'example.com')), 'opaque origin');
	pg_assert_false(pg_consent_answer_same_origin(array('HTTP_HOST' => 'example.com')), 'neither header');
	pg_assert_false(pg_consent_answer_same_origin(array('HTTP_ORIGIN' => 'https://example.com')), 'no host');
}

// The notice names the endpoint only when it has something to ask, and the
// script sends the answer from decide(), never from the stored-answer path.
function test_consent_notice_names_the_answer_endpoint()
{
	pg_assert_contains('/consent_answer.php"', pg_consent_markup(consent_test_page_with_categories()), 'with categories');
	pg_assert_contains(' data-log="', pg_consent_markup(consent_test_page_with_categories()), 'attribute');
	pg_assert_false(strpos(pg_consent_markup('<html><head></head><body></body></html>'), 'data-log=') !== false, 'nothing to ask');

	$script = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_consent.js');
	$decide = substr($script, strpos($script, 'function decide('), 600);

	pg_assert_contains('report(', $decide, 'decide reports');
	pg_assert_same(2, substr_count($script, 'report('), 'one definition, one call');
}

// The endpoint counts per address before it writes and reads the signed-in
// user from initialize_user(), not from the session's user name.
function test_consent_answer_endpoint_throttles_before_writing()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/consent_answer.php');
	$throttle = strpos($source, 'pg_consent_answer_throttled()');

	pg_assert_true($throttle !== false, 'throttled');
	pg_assert_true($throttle < strpos($source, 'pg_consent_log_record('), 'before the write');
	pg_assert_contains('429', $source, 'refused with 429');
	pg_assert_contains('USER_EMAIL_ADDRESS', $source, 'user from initialize_user()');
	pg_assert_false(strpos($source, 'sessionusername') !== false, 'no lookup by user name');
}
