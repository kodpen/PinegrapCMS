<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for get_help_url.php: every panel screen at the root that draws a
 * header (pg_page_shell() or output_header()) has a documentation topic,
 * either as a row in the table or as a 'help' => 'page#anchor' argument, so
 * none of them falls back to the introduction.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/get_help_url.php');

// Screens that draw a header but need no topic of their own: script name
// without ".php" => the reason (a popup, a redirect-only screen, a private
// screen). Keep it short; a real screen belongs in the table.
function help_url_test_exempt()
{
	return array();
}

// The root scripts that draw a panel header, as script name => source.
function help_url_test_screens()
{
	$screens = array();

	foreach (glob(PG_FUNCTIONS_DIR . '/*.php') as $file) {
		$source = (string) file_get_contents($file);

		if (preg_match('/\b(pg_page_shell|output_header)\s*\(/', $source)) {
			$screens[basename($file, '.php')] = $source;
		}
	}

	ksort($screens);

	return $screens;
}

// True when the source passes its own topic to the header.
function help_url_test_has_help_argument($source)
{
	return (bool) preg_match('/[\'"]help[\'"]\s*=>\s*[\'"][a-z0-9_-]+(#[a-z0-9_-]+)?[\'"]/', $source);
}

// True when the table has a row for the screen: anything else falls back to
// the bare introduction page.
function help_url_test_has_row($screen)
{
	return get_help_url(array('screen' => $screen)) !== 'https://pinegrap.com/introduction';
}

function test_help_url_every_header_screen_has_a_topic()
{
	$exempt = help_url_test_exempt();
	$screens = help_url_test_screens();

	pg_assert_true(count($screens) > 100, 'the sweep finds the panel screens');

	foreach ($screens as $screen => $source) {
		$covered = help_url_test_has_row($screen)
			|| help_url_test_has_help_argument($source)
			|| isset($exempt[$screen]);

		pg_assert_true($covered, $screen . '.php draws a header: add a row to get_help_url.php or pass \'help\' => \'page#anchor\'');
	}
}

function test_help_url_exempt_list_holds_only_uncovered_header_screens()
{
	$screens = help_url_test_screens();

	foreach (help_url_test_exempt() as $screen => $reason) {
		pg_assert_true(trim((string) $reason) !== '', $screen . ' gives a reason');
		pg_assert_true(isset($screens[$screen]), $screen . '.php still draws a header');
		pg_assert_false(help_url_test_has_row($screen), $screen . ' has a row and needs no exemption');
	}
}

function test_help_url_help_argument_form_is_recognised()
{
	pg_assert_true(help_url_test_has_help_argument("pg_page_shell(array('help' => 'workspace-cont#flows'));"));
	pg_assert_true(help_url_test_has_help_argument('output_header(array("help" => "commerce"));'));
	pg_assert_false(help_url_test_has_help_argument("pg_page_shell(array('help' => \$topic));"));
	pg_assert_false(help_url_test_has_help_argument("pg_page_shell(array('help' => 'Not A Topic'));"));
}

function test_help_url_new_workspace_rows()
{
	pg_assert_same('https://pinegrap.com/workspace-cont#flows', get_help_url(array('screen' => 'workspace_flows')));
	pg_assert_same('https://pinegrap.com/tr/workspace-cont#workflow-tables', get_help_url(array('screen' => 'workspace_wtables', 'languageinfo' => 'tr')));
}
