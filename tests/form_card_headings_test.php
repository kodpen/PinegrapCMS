<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the heading outline of the create and edit screens built on
 * includes/forms/*.php: card titles are h2 elements, group titles inside a
 * card are h3, and the .hN class keeps the old size. Only dialog titles keep
 * an h4-h6 element.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function form_card_headings_sources()
{
	$sources = array();
	foreach (glob(PG_FUNCTIONS_DIR . '/includes/forms/*.php') as $path) {
		$sources[basename($path)] = (string) file_get_contents($path);
	}
	return $sources;
}

function form_card_headings_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/' . $file);
}

function test_form_card_titles_are_not_divs()
{
	foreach (form_card_headings_sources() as $file => $src) {
		pg_assert_false((bool) preg_match('/<div class="card-header[^"]*\bh5\b/', $src), $file . ': a card title is a <div>');
		pg_assert_false((bool) preg_match('/<span class="[^"]*\bh5\b/', $src), $file . ': a card title is a <span>');
	}
}

function test_form_has_no_low_heading_elements_but_dialog_titles()
{
	foreach (form_card_headings_sources() as $file => $src) {
		preg_match_all('/<h[4-6]\b[^>]*>/', $src, $matches);
		foreach ($matches[0] as $tag) {
			pg_assert_contains('dialog-title', $tag, $file . ': ' . $tag . ' skips heading levels');
		}
	}
}

function test_form_card_titles_are_h2_with_the_same_look()
{
	$files = array(
		'ad_form.php' => 4,
		'arrival_date_form.php' => 5,
		'calendar_event_form.php' => 5,
		'contact_form.php' => 8,
		'email_campaign_profile_form.php' => 6,
		'page_form.php' => 2,
		'product_group_form.php' => 6,
		'shipping_method_form.php' => 5,
		'zone_form.php' => 5,
	);
	foreach ($files as $file => $count) {
		$src = form_card_headings_source($file);
		pg_assert_true(preg_match_all('/<h2 class="card-header[^"]*\b(h5|pg-title-section)\b[^"]*">/', $src) === $count, $file . ': ' . $count . ' card titles are h2.h5');
	}

	$user = form_card_headings_source('user_form.php');
	pg_assert_true(substr_count($user, '<h2 class="pg-title-section mb-0">\' . lang(\'User Rights\') . \'</h2>') === 2, 'user rights card titles are h2 section titles');
}

function test_form_group_titles_are_h3_inside_cards()
{
	$expected = array(
		'ad_region_form.php' => '<h3 class="h5 mt-12">\' . lang(\'Region Behavior\') . \'</h3>',
		'login_region_form.php' => '<h3 class="h5 mt-12">\' . lang(\'Logged In\') . \'</h3>',
		'field_form.php' => '<h3 class="h6 fg-secondary">\' . lang(\'Amount of Rows and Columns to Display\') . \'</h3>',
		'calendar_event_form.php' => '<h3 class="h5" id="multiselect-checkbox-checker-1_title">\' . lang(\'Post to any of my Calendars\') . \'</h3>',
		'shipping_method_form.php' => '<h3 class="h5 fg-secondary mt-5">\' . lang(\'Available on\') . \'</h3>',
		'page_form.php' => '<h3 class="h5">\' . lang(\'Form List Views\') . \'</h3>',
	);
	foreach ($expected as $file => $needle) {
		pg_assert_contains($needle, form_card_headings_source($file), $file . ': group title is h3');
	}
}

function test_shipping_method_site_time_is_not_a_heading()
{
	$src = form_card_headings_source('shipping_method_form.php');
	pg_assert_contains('<div class="h6"><em>\' . lang(array(\'string\'=>\'For reference, the current site time is {var:1}.\'', $src, 'the site time sentence is text');
}
