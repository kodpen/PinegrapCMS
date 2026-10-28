<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the classic front-end form renderers print a field's label as
 * text: fields.label is plain text, so markup typed into it must reach the
 * page escaped, never as HTML.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_form_label_escaping_classic_renderers()
{
	$files = array(
		'includes/fn/content.php'                           => 13,
		'get_form_item_view.php'                            => 10,
		'get_custom_form_confirmation_screen_content.php'   => 1,
	);

	foreach ($files as $file => $count) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_same(0, preg_match('/\' \. \$(?:form_)?field\[\'label\'\] \. \'/', $source), $file . ': a label printed raw');
		pg_assert_same($count, preg_match_all('/h\(\$(?:form_)?field\[\'label\'\]\)/', $source), $file . ': every label through h()');
	}
}
