<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Pins, by source, that every string the panel search (Ctrl+K) passes to its
 * t() helper in backend.src.js is a key of the panel's JavaScript translation
 * table ($translate in includes/fn/output.php). t() reads only that table and
 * falls back to the English key, so a missing entry showed "No Search Result."
 * in English on a Turkish panel.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_panel_search_t_keys_are_in_the_translate_table()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
	$php = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/output.php');

	preg_match_all("/(?<![\\w.])t\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $js, $m);
	$keys = array_unique($m[1]);
	pg_assert_true(in_array('No Search Result', $keys, true), 'search no-result text goes through t()');

	foreach ($keys as $key) {
		pg_assert_contains("'" . $key . "' => lang('" . $key . "')", $php, 'translate table: ' . $key);
	}
}
