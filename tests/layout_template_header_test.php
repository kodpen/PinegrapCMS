<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_layout_strip_template_header() (includes/fn/layouts.php): a
 * generated layout holds the view template's markup without the file header
 * and the PG_INIT_LOADED gate, and Generate Layout refuses a page type that
 * has no layout template.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/layouts.php');

function test_layout_strip_template_header()
{
	$header = "<?php\n/**\n * Pinegrap - Enterprise Website Platform\n */\n\n// This file is a view partial.\n// Requested directly it dies.\nif (!defined('PG_INIT_LOADED')) {\n    http_response_code(403);\n    exit;\n}\n?>\n";

	pg_assert_same("<form>\n</form>\n", pg_layout_strip_template_header($header . "<form>\n</form>\n"));
	pg_assert_same("<div></div>", pg_layout_strip_template_header(str_replace("\n", "\r\n", $header) . "<div></div>"), 'CRLF line endings');
	pg_assert_same("<?=\$messages?>\n", pg_layout_strip_template_header("<?=\$messages?>\n"), 'a text without the header is kept');

	$middle = "<p>a</p>\n" . $header;
	pg_assert_same($middle, pg_layout_strip_template_header($middle), 'only a header at the start is removed');

	$code = "<?php\n/**\n * x\n */\nif (!defined('PG_INIT_LOADED')) {\n    exit;\n}\n\$a = 1;\n?>\n<p></p>";
	pg_assert_same($code, pg_layout_strip_template_header($code), 'a block that goes on with code is kept');
}

function test_layout_templates_lose_their_header()
{
	foreach (array('login', 'custom_form', 'express_order', 'billing_information') as $name) {
		$path = PG_FUNCTIONS_DIR . '/includes/templates/' . $name . '.php';
		$stripped = pg_layout_strip_template_header(file_get_contents($path));

		pg_assert_true(strpos($stripped, 'PG_INIT_LOADED') === false, $name . ' loses its gate');
		pg_assert_true(strpos($stripped, 'Enterprise Website Platform') === false, $name . ' loses its header');
	}
}

function test_generate_layout_refuses_types_without_a_layout()
{
	$screen = file_get_contents(PG_FUNCTIONS_DIR . '/generate_layout.php');
	pg_assert_true(strpos($screen, 'check_if_page_type_supports_layout($page[\'type\'])') !== false, 'Generate Layout checks the page type');

	$content = file_get_contents(PG_FUNCTIONS_DIR . '/generate_layout_content.php');
	pg_assert_true(strpos($content, 'is_file($template_path)') !== false, 'a missing template is not read');
	pg_assert_same(2, substr_count($content, 'pg_layout_strip_template_header('), 'the page template and nested templates are stripped');
}
