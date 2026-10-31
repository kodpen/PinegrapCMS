<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The software language a request outside init.php speaks
 * (pg_software_language_pick() in includes/fn/lang.php), and that
 * get_file.php loads lang() before it writes a site log line.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The enforced language first, then the stored one, then the default.
function test_lang_loader_pick_order()
{
	pg_assert_same('tr', pg_software_language_pick('tr', 'en', 'en'));
	pg_assert_same('en', pg_software_language_pick('', 'en', 'tr'), 'the stored language before the default');
	pg_assert_same('tr', pg_software_language_pick('', '', 'tr'));
	pg_assert_same('tr', pg_software_language_pick(null, 'undefined', 'tr'), 'an old screen\'s "undefined" is no language');
	pg_assert_same('', pg_software_language_pick('', '', ''), 'none set: lang() speaks English');
	pg_assert_same('', pg_software_language_pick(false, null, null));
}

// get_file.php is dispatched without functions.php: it loads the module
// itself, and defines the language before the first log line.
function test_lang_loader_get_file_loads_it()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_file.php');
	$load = strpos($source, "require_once(PG_FUNCTIONS_DIR . '/includes/fn/lang.php');");
	$define = strpos($source, 'pg_software_language_define(');
	$first_log = strpos($source, 'log_activity(pg_log_lang(');

	pg_assert_true($load !== false, 'get_file.php loads includes/fn/lang.php');
	pg_assert_true(strpos($source, "require_once(PG_FUNCTIONS_DIR . '/includes/fn/lang_files.php');") !== false, 'and the cache helpers pg_lang_map() uses');
	pg_assert_true(($define !== false) && ($define > $load), 'the language is defined after the load');
	pg_assert_true(($first_log !== false) && ($first_log > $define), 'before the first log line');
}
