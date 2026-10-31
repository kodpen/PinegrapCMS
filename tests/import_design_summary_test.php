<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the summary the final Import of Import My Site
 * (import_design.php) ends with: how many items were imported, and which
 * scanned items were left out (download failed or file name refused), up to
 * five addresses, escaped, then "and N more".
 *
 * import_design.php runs a request when it is loaded, so the helper is read
 * out of the file by name, as tests/import_design_fetch_test.php does.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!function_exists('import_design_import_summary')) {
	$_test_ids_tokens = token_get_all(file_get_contents(PG_FUNCTIONS_DIR . '/import_design.php'));
	$_test_ids_count = count($_test_ids_tokens);

	for ($i = 0; $i < $_test_ids_count; $i++) {
		if (!is_array($_test_ids_tokens[$i]) || ($_test_ids_tokens[$i][0] !== T_FUNCTION)) {
			continue;
		}

		$j = $i + 1;
		while (($j < $_test_ids_count) && is_array($_test_ids_tokens[$j]) && ($_test_ids_tokens[$j][0] === T_WHITESPACE)) {
			$j++;
		}

		if (!is_array($_test_ids_tokens[$j]) || ($_test_ids_tokens[$j][1] !== 'import_design_import_summary')) {
			continue;
		}

		// From "function" to the brace that closes its body.
		$source = '';
		$depth = 0;
		$opened = false;

		for ($k = $i; $k < $_test_ids_count; $k++) {
			$text = is_array($_test_ids_tokens[$k]) ? $_test_ids_tokens[$k][1] : $_test_ids_tokens[$k];
			$source .= $text;

			if (($text === '{') || (is_array($_test_ids_tokens[$k]) && in_array($_test_ids_tokens[$k][0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) {
				$depth++;
				$opened = true;
			} elseif ($text === '}') {
				$depth--;

				if ($opened && ($depth === 0)) {
					break;
				}
			}
		}

		eval($source);
		break;
	}
}

function _test_ids_skipped($count, $reason = 'download')
{
	$skipped = array();

	for ($i = 1; $i <= $count; $i++) {
		$skipped[] = array('url' => 'https://example.com/p' . $i . '.png', 'reason' => $reason);
	}

	return $skipped;
}

function test_import_summary_all_imported_has_no_warning()
{
	$summary = import_design_import_summary(12, array());

	pg_assert_false($summary['failed'], 'something was imported');
	pg_assert_contains('12', $summary['message'], 'count in the message');
	pg_assert_same('', $summary['warning'], 'no warning when nothing was skipped');
	pg_assert_contains('12', $summary['text'], 'command-line text names the count');
}

function test_import_summary_nothing_imported_fails()
{
	$summary = import_design_import_summary(0, _test_ids_skipped(2));

	pg_assert_true($summary['failed'], 'nothing imported is a failure');
	pg_assert_contains('https://example.com/p2.png', $summary['warning'], 'skipped addresses still listed');
}

function test_import_summary_lists_five_then_more()
{
	$summary = import_design_import_summary(3, _test_ids_skipped(8));

	for ($i = 1; $i <= 5; $i++) {
		pg_assert_contains('https://example.com/p' . $i . '.png', $summary['warning'], 'address ' . $i . ' listed');
	}

	pg_assert_false(strpos($summary['warning'], 'p6.png') !== false, 'the sixth address is not listed');
	pg_assert_contains('3', $summary['warning'], 'the rest counted');
	pg_assert_contains('p8.png', $summary['text'], 'command-line text lists every skipped address');
}

function test_import_summary_escapes_addresses_and_names_reason()
{
	$summary = import_design_import_summary(1, array(
		array('url' => 'https://example.com/a.php?x=<b>"', 'reason' => 'name'),
		array('url' => 'https://example.com/b.css', 'reason' => 'download'),
	));

	pg_assert_false(strpos($summary['warning'], '<b>') !== false, 'address is escaped');
	pg_assert_contains('&lt;b&gt;', $summary['warning'], 'escaped address shown');
	pg_assert_contains(lang('file name not allowed'), $summary['warning'], 'refused name reason');
	pg_assert_contains(lang('download failed'), $summary['warning'], 'download reason');
}
