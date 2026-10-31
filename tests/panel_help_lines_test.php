<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that panel help lines state the rule the code applies: the
 * submitted form time help follows the site clock, the Optimize Images
 * shrink option names the longest edge, the contact Affiliate Code help says
 * a code is generated only for an approved affiliate, and the product Add
 * Comment Name help says a blank name posts as Anonymous.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_panel_help_lines_state_the_rule_the_code_applies()
{
	$root = dirname(__DIR__) . '/pinegrap/';

	$cases = array(
		'includes/forms/submitted_form_form.php' => array(
			'absent' => "lang('Format: h:mm AM/PM')",
			'present' => "lang(array('string' => 'Format: {var:1}', 'vars' => array(pg_time_format_help())))",
		),
		'edit_files.php' => array(
			'absent' => 'shrink anything wider than',
			'present' => 'Compress, and shrink any image whose longest edge is over {var:1} pixels',
		),
		'includes/forms/contact_form.php' => array(
			'absent' => 'leave blank to automatically generate code',
			'present' => 'leave blank to generate a code when the affiliate is approved',
		),
		'product_builder.php' => array(
			'absent' => 'Leave blank to use the name on the order.',
			'present' => 'Leave blank to post as Anonymous.',
		),
	);

	foreach ($cases as $file => $case) {
		$source = file_get_contents($root . $file);
		pg_assert_false(strpos($source, $case['absent']) !== false, $file . ' no longer states the old rule');
		pg_assert_true(strpos($source, $case['present']) !== false, $file . ' states the rule the code applies');
	}
}
