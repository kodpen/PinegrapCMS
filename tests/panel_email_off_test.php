<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Cloudflare Email Address Obfuscation exemption of the panel
 * chrome (includes/fn/output.php): both panel headers open the exempt part
 * right after <body>, and both footers close it right before </body>.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of one function of output.php, up to the next one.
function panel_email_off_test_function($name)
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/output.php');

	$from = strpos($source, 'function ' . $name . '(');

	if ($from === false) {
		return '';
	}

	$to = strpos($source, "\nfunction ", $from + 1);

	return ($to === false) ? substr($source, $from) : substr($source, $from, $to - $from);
}

// The comments are the ones Cloudflare documents.
function test_panel_email_off_markers()
{
	pg_assert_same('<!--email_off-->', pg_output_email_off_open());
	pg_assert_same('<!--/email_off-->', pg_output_email_off_close());
}

// The secure footer closes the exempt part before </body>.
function test_panel_email_off_footer_secure()
{
	$footer = output_footer_secure();

	$close = strpos($footer, '<!--/email_off-->');

	pg_assert_true($close !== false, 'closing comment present');
	pg_assert_true($close < strpos($footer, '</body>'), 'closed before </body>');
}

// Both headers open it straight after their <body> tag, both footers close it
// before </body>.
function test_panel_email_off_layout_functions()
{
	foreach (array('output_header', 'output_header_secure') as $name) {
		$source = panel_email_off_test_function($name);

		pg_assert_true((bool) preg_match('/<body[^\n]*\n\s*\' \. pg_output_email_off_open\(\)/', $source), $name . ' opens after <body>');
	}

	foreach (array('output_footer', 'output_footer_secure') as $name) {
		$source = panel_email_off_test_function($name);

		pg_assert_true((bool) preg_match('/pg_output_email_off_close\(\) \. \'\s*<\/body>/', $source), $name . ' closes before </body>');
	}
}
