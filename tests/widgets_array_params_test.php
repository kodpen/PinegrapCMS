<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the record view's ?r= and the order view's ?cancelled= are read
 * only when the address gives a single value: ?r[]=x or ?cancelled[]=1 is
 * no value, not an "Array to string conversion" warning.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_widgets_array_params_guarded()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');

	pg_assert_same(0, preg_match('/isset\(\$_GET\[\'r\'\]\) \? trim\(\(string\)\$_GET\[\'r\'\]\)/', $source), 'widgets.php: ?r= cast without is_scalar()');
	pg_assert_same(0, preg_match('/isset\(\$_GET\[\'cancelled\'\]\) \? \(string\)\$_GET\[\'cancelled\'\]/', $source), 'widgets.php: ?cancelled= cast without is_scalar()');
}
