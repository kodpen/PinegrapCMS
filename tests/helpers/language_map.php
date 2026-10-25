<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The wording a test checks translations against: key => text of a
 * language as lang() reads it, through pg_lang_map() (the shards in
 * includes/local/<code>/, else the single includes/local/<code>.json).
 * Tests never open a language file by its path, so they follow the layout
 * the software reads. Read once per run and language: the Turkish map is
 * about 2.9 MB of JSON.
 *
 * Not a test file (tools/test.php runs tests/*_test.php only): a test
 * requires it with require_once(__DIR__ . '/helpers/language_map.php').
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * The language map of $code, key => text; array() when the language has no
 * file.
 *
 * @param string $code
 * @return array
 */
function pg_test_language_map($code = 'tr')
{
	static $maps = array();

	$code = (string) $code;

	if (!isset($maps[$code])) {
		$maps[$code] = pg_lang_map($code);
	}

	return $maps[$code];
}
