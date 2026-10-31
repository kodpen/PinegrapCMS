<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the start address of Import My Site (import_design.php): a typed
 * http or https scheme in any case is lower-cased, http:// is added only when
 * no scheme is there, and the crawler still refuses every other scheme.
 *
 * import_design.php runs a request when it is loaded, so the two functions
 * are read out of the file by name and only they are declared here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Declares the named functions of import_design.php, nothing else of it.
function _test_idsu_load($names)
{
	$tokens = token_get_all(file_get_contents(PG_FUNCTIONS_DIR . '/import_design.php'));
	$count = count($tokens);

	for ($i = 0; $i < $count; $i++) {
		if (!is_array($tokens[$i]) || ($tokens[$i][0] !== T_FUNCTION)) {
			continue;
		}

		$j = $i + 1;
		while (($j < $count) && is_array($tokens[$j]) && ($tokens[$j][0] === T_WHITESPACE)) {
			$j++;
		}

		if (!is_array($tokens[$j]) || ($tokens[$j][0] !== T_STRING) || !in_array($tokens[$j][1], $names, true) || function_exists($tokens[$j][1])) {
			continue;
		}

		$source = '';
		$depth = 0;
		$opened = false;

		for ($k = $i; $k < $count; $k++) {
			$text = is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
			$source .= $text;

			if (($text === '{') || (is_array($tokens[$k]) && in_array($tokens[$k][0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) {
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
	}
}

_test_idsu_load(array('import_design_start_url', 'import_design_url_is_web'));

function test_import_design_start_url_capitalised_scheme_lower_cased()
{
	pg_assert_same('https://example.com', import_design_start_url('Https://example.com'), 'Https');
	pg_assert_same('https://example.com/Path', import_design_start_url('HTTPS://example.com/Path'), 'HTTPS');
	pg_assert_same('http://Example.com', import_design_start_url('HTTP://Example.com'), 'HTTP');
}

function test_import_design_start_url_lower_case_scheme_unchanged()
{
	pg_assert_same('https://www.example.com/', import_design_start_url('https://www.example.com/'));
	pg_assert_same('http://example.com', import_design_start_url('http://example.com'));
}

function test_import_design_start_url_no_scheme_gets_http()
{
	pg_assert_same('http://example.com', import_design_start_url('example.com'));
	pg_assert_same('http://www.example.com/a', import_design_start_url('www.example.com/a'));
}

function test_import_design_url_is_web_any_case()
{
	pg_assert_true(import_design_url_is_web('https://example.com'), 'https');
	pg_assert_true(import_design_url_is_web('HTTPS://example.com'), 'HTTPS');
	pg_assert_true(import_design_url_is_web('Http://example.com'), 'Http');
}

function test_import_design_url_is_web_refuses_other_schemes()
{
	pg_assert_false(import_design_url_is_web('file:///etc/passwd'), 'file');
	pg_assert_false(import_design_url_is_web('php://filter/resource=x'), 'php');
	pg_assert_false(import_design_url_is_web('ftp://example.com'), 'ftp');
	pg_assert_false(import_design_url_is_web(' https://example.com'), 'leading space');
	pg_assert_false(import_design_url_is_web('https:/example.com'), 'one slash');
}
