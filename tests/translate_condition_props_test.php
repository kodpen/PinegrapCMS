<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_tr_condition_props() in includes/fn/translate.php: the first
 * node drawn on a request in another language may carry a value condition,
 * before anything else has loaded the translation subsystem.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs the call in a fresh process, where only the module itself is loaded
// (the test runner has the subsystem's files loaded already).
function test_translate_condition_props_loads_the_subsystem_first()
{
	$script = sys_get_temp_dir() . '/pg_tr_cond_' . getmypid() . '_' . mt_rand() . '.php';
	$code = "<?php\n"
		. "define('PG_FUNCTIONS_DIR', " . var_export(PG_FUNCTIONS_DIR, true) . ");\n"
		. "define('FRONTEND_LANGUAGE', 'de');\n"
		. "define('TRANSLATION_READY', true);\n"
		. "define('TRANSLATION_SOURCE_LANGUAGE', 'en');\n"
		. "function db_items(\$query) { return (strpos(\$query, 'site_languages') !== false) ? array(array('code' => 'de', 'prefix' => '', 'enabled' => 1, 'sort_order' => 1)) : array(); }\n"
		. "require PG_FUNCTIONS_DIR . '/includes/fn/translate.php';\n"
		. "\$props = pg_tr_condition_props(array('text' => 'Hallo'), 'content');\n"
		. "echo (\$props === array('text' => 'Hallo')) ? 'ok' : 'changed';\n";

	file_put_contents($script, $code);
	$output = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1');
	unlink($script);

	pg_assert_same('ok', trim($output));
}
