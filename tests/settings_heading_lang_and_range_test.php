<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Site Settings cards: English brand names inside uppercase
 * card titles carry lang="en" (under lang="tr" an uppercase "i" becomes "İ"),
 * and the percent sliders take their bubble wording from lang('{var:1}%') so
 * Turkish reads "%10".
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function settings_heading_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/' . $file);
}

function test_settings_ranges_take_their_wording_from_lang()
{
	$count = 0;
	foreach (glob(PG_FUNCTIONS_DIR . '/includes/settings/*.php') as $path) {
		$src = (string) file_get_contents($path);
		pg_assert_false(strpos($src, 'data-pg-range="%"') !== false, basename($path) . ': a bare % unit is left');
		$count += preg_match_all('/data-pg-range="/', $src);
		pg_assert_same(
			preg_match_all('/data-pg-range="/', $src),
			substr_count($src, 'data-pg-range="\' . h(lang(\'{var:1}%\')) . \'"'),
			basename($path) . ': a data-pg-range is not filled from lang(\'{var:1}%\')'
		);
	}
	pg_assert_true($count >= 2, 'the General and Features sliders are found');
}

function test_pg_bind_ranges_fills_the_placeholder()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
	pg_assert_true((bool) preg_match('/function pgBindRanges\(scope\) \{(.*?)\n\}/s', $js, $m), 'pgBindRanges is found');
	pg_assert_contains("'{var:1}'", $m[1], 'pgBindRanges handles {var:1}');
	pg_assert_contains('.replace(', $m[1], 'pgBindRanges replaces the placeholder');
}

function test_cron_title_marks_the_os_name_as_english()
{
	$src = settings_heading_source('general.php');
	pg_assert_contains("lang('Cron Jobs') . ' (<span lang=\"en\">' . h(\$output_os_family) . '</span>)", $src, 'the Cron Jobs title');
	pg_assert_false(strpos($src, "' (' . \$output_os_family . ')") !== false, 'an unmarked OS name is left');
}

function test_brand_names_in_titles_are_marked_english()
{
	pg_assert_contains("<span lang=\"en\">' . lang('MailChimp') . '</span>", settings_heading_source('contact.php'), 'the MailChimp title');

	$src = settings_heading_source('security.php');
	pg_assert_contains("'string' => 'Sign in with {var:1}, {var:2} and {var:3}'", $src, 'security.php uses the placeholder key');
	foreach (array('Google', 'Microsoft', 'Apple') as $brand) {
		pg_assert_contains("'<span lang=\"en\">" . $brand . "</span>'", $src, $brand . ' is marked English');
	}
	pg_assert_false(strpos($src, "lang('Sign in with Google, Microsoft and Apple')") !== false, 'the old title is left');
}
