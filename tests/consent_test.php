<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the cookie consent helpers in includes/fn/consent.php: reading
 * the pg_consent cookie value, holding a script back for a category, and
 * finding the scripts a page holds back on its own.
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

// On a page in a language with no language file only the cookie window is
// read from the site's translations; the rest of the software's wording
// never is, whatever the request.
function test_consent_only_the_cookie_window_is_translated()
{
	pg_assert_same(null, pg_tr_ui_text('Cancel'), 'a word of the dynamic code block');
	pg_assert_same(null, pg_tr_ui_text('Deactivate Fullscreen Mode'), 'a word of the toolbar');
	pg_assert_true(in_array('Accept all', pg_consent_ui_keys(), true), 'a word of the cookie window is listed');
}
