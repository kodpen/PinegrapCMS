<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Every module under includes/fn/ starts with the gate that stops it when it
 * is requested as a URL: functions.php (or a test, tool or entry point that
 * defines PG_FUNCTIONS_DIR) is the only way in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_fn_modules_carry_the_gate()
{
	$missing = array();

	foreach (glob(PG_FUNCTIONS_DIR . '/includes/fn/*.php') as $file) {
		$code = (string) file_get_contents($file);

		// The gate stops the file before it declares or defines anything.
		$gate = strpos($code, "defined('PG_FUNCTIONS_DIR')");
		$first = preg_match('~^\s*(?:function|define|const|class|if \(!defined\(\'PG_(?!FUNCTIONS_DIR)|require)~m', $code, $m, PREG_OFFSET_CAPTURE) ? $m[0][1] : strlen($code);

		if ($gate === false || $gate > $first) {
			$missing[] = basename($file);
		}
	}

	pg_assert_same(array(), $missing, 'includes/fn/ modules without the PG_FUNCTIONS_DIR gate');
}
