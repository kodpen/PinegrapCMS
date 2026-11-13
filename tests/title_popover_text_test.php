<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source scan for the panel's hover popovers: a popover with only a title
 * shows that title as text, and only a title + data-bs-content popover is
 * HTML. The two screens whose popover text went through a second decoding
 * keep the escapes that let it read the same as plain text.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The body of pgBindTitlePopovers() in backend.src.js.
function title_popover_test_binder()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
	$start = strpos($source, 'function pgBindTitlePopovers(');
	pg_assert_true($start !== false, 'pgBindTitlePopovers() is in backend.src.js');
	$end = strpos($source, "\n}\n", $start);

	return substr($source, $start, $end - $start);
}

function test_title_popover_binder_ties_html_to_content()
{
	$binder = title_popover_test_binder();

	pg_assert_same(2, substr_count($binder, "html: this.hasAttribute('data-bs-content')"), 'both binds read html from data-bs-content');
	pg_assert_same(0, preg_match('/html:\s*true/', $binder), 'no bind sets html: true on its own');
}

function test_title_popover_min_bundle_follows()
{
	$min = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.min.js');

	pg_assert_true(strpos($min, 'hasAttribute("data-bs-content")') !== false || strpos($min, "hasAttribute('data-bs-content')") !== false, 'backend.min.js is rebuilt');
}

function test_title_popover_view_fields_information_title_is_text()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/field_list.php');

	pg_assert_true(strpos($source, "title=\"' . h(pg_liveform_message_text(\$text)) . '\"") !== false, 'information preview title is its text, entities resolved once');
	pg_assert_true(strpos($source, 'h(strip_tags(') === false, 'no strip_tags() title left');
}

