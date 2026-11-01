<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for three visual page editor fixes: a list starter's card goes into
 * the widget's own loop (never into a list with a source such as the
 * catalog's breadcrumb), the draft status line of the site log is written
 * only for a page that changes between on the site and draft
 * (pg_designer_page_draft_log()), and the theme panel draws no copy/paste
 * buttons that do nothing.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function page_editor_fixes_test_source()
{
	static $source = null;

	if ($source === null) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	}

	return $source;
}

// The body of the named function in the designer source, up to the next
// top-level function of the module.
function page_editor_fixes_test_fn($name)
{
	$src = page_editor_fixes_test_source();
	$at  = strpos($src, 'function ' . $name . '(');
	pg_assert_true($at !== false, $name . ' exists');
	$end = strpos($src, "\n    function ", $at + 10);

	return substr($src, $at, ($end === false) ? 4000 : $end - $at);
}

// -- list starter -------------------------------------------------------------

// The starter's loop is found past lists with a source (the catalog's
// breadcrumb comes before its product loop in the default tree).
function test_page_editor_fixes_starter_skips_sourced_list()
{
	$fn = page_editor_fixes_test_fn('_sdLgStarterTree');
	pg_assert_true(strpos($fn, '_sdListIsSourced(n)') !== false, 'the walk skips a list with a source');
}

// -- draft log line -----------------------------------------------------------

// A new page saved as a draft was never on the site: no status line.
function test_page_editor_fixes_new_draft_logs_nothing()
{
	pg_assert_same('', pg_designer_page_draft_log(false, true, true));
}

// A new page saved on the site: its "was created" line says it all.
function test_page_editor_fixes_new_page_on_site_logs_nothing()
{
	pg_assert_same('', pg_designer_page_draft_log(false, false, true));
}

// A stored page on the site saved as a draft is taken off the site.
function test_page_editor_fixes_page_taken_off()
{
	pg_assert_same('off', pg_designer_page_draft_log(false, true, false));
}

// A stored draft saved on the site is published.
function test_page_editor_fixes_draft_published()
{
	pg_assert_same('on', pg_designer_page_draft_log(true, false, false));
}

// No change between on the site and draft: no line.
function test_page_editor_fixes_unchanged_logs_nothing()
{
	pg_assert_same('', pg_designer_page_draft_log(true, true, false));
	pg_assert_same('', pg_designer_page_draft_log(false, false, false));
}

// -- theme panel --------------------------------------------------------------

// Each theme group keeps its reset button and draws no copy/paste buttons.
function test_page_editor_fixes_theme_group_no_copy_paste()
{
	$src = page_editor_fixes_test_source();
	pg_assert_true(strpos($src, 'sd-theme-grp-act" data-act="reset-all"') !== false, 'reset button stays');
	pg_assert_true(strpos($src, 'sd-theme-grp-act" data-act="copy"') === false, 'no copy button');
	pg_assert_true(strpos($src, 'sd-theme-grp-act" data-act="paste"') === false, 'no paste button');
}
