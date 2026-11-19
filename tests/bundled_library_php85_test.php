<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the bundled dompdf, PHPMailer, iyzico SDK, PHPExcel and
 * BoxPacker stay quiet on PHP 8.5. They call handle-freeing functions that PHP 8.5 deprecates
 * (imagedestroy(), xml_parser_free(), curl_close(), openssl_pkey_free()) only
 * behind a PHP 7 check, and a library update that drops one would log a
 * deprecation on every PDF render, DKIM-signed mail or iyzico call.
 * Non-canonical casts such as (boolean), implicitly nullable parameters
 * (PHP 8.4) and a read of $http_response_header before the function writes
 * it are reported when a file is compiled, so none may appear in the loaded
 * code.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The library folders the product loads. includes/phpmailer/class.*.php are
// PHPMailer 5.2 files no code loads (see the last test), so they are left out.
function pg_test_bundled_php85_files()
{
	$files = array();

	foreach (array('/includes/dompdf', '/includes/phpmailer/src', '/includes/iyzipay-php/src', '/includes/phpexcel', '/includes/boxpacker') as $folder) {
		$directory = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PG_FUNCTIONS_DIR . $folder, FilesystemIterator::SKIP_DOTS));

		foreach ($directory as $file) {
			if (substr($file->getFilename(), -4) === '.php') {
				$files[] = $file->getPathname();
			}
		}
	}

	sort($files);

	return $files;
}

function pg_test_bundled_php85_name($path)
{
	return ltrim(str_replace('\\', '/', substr($path, strlen(PG_FUNCTIONS_DIR))), '/');
}

function test_bundled_libraries_guard_deprecated_free_calls()
{
	$free = array('imagedestroy', 'xml_parser_free', 'curl_close', 'curl_share_close', 'curl_multi_close', 'finfo_close', 'openssl_pkey_free', 'openssl_free_key', 'openssl_x509_free');
	$unguarded = array();
	$calls = 0;

	foreach (pg_test_bundled_php85_files() as $path) {
		$source = (string) file_get_contents($path);
		$lines = explode("\n", $source);
		$tokens = token_get_all($source);
		$count = count($tokens);

		for ($i = 0; $i < $count; $i++) {
			if (!is_array($tokens[$i]) || ($tokens[$i][0] !== T_STRING) || !in_array(strtolower($tokens[$i][1]), $free, true)) {
				continue;
			}

			$next = $i + 1;

			while (($next < $count) && is_array($tokens[$next]) && ($tokens[$next][0] === T_WHITESPACE)) {
				$next++;
			}

			$previous = $i - 1;

			while (($previous > 0) && is_array($tokens[$previous]) && ($tokens[$previous][0] === T_WHITESPACE)) {
				$previous--;
			}

			// Only a call: not a method of the same name, not a declaration.
			if (($next >= $count) || ($tokens[$next] !== '(')
				|| (is_array($tokens[$previous]) && in_array($tokens[$previous][0], array(T_FUNCTION, T_OBJECT_OPERATOR, T_DOUBLE_COLON), true))) {
				continue;
			}

			$calls++;
			$line = $tokens[$i][2];
			$context = implode("\n", array_slice($lines, max(0, $line - 3), 3));

			if (!preg_match('/PHP_MAJOR_VERSION\s*<\s*8\b|PHP_VERSION_ID\s*<\s*80000\b/', $context)) {
				$unguarded[] = pg_test_bundled_php85_name($path) . ':' . $line . ' ' . $tokens[$i][1] . '()';
			}
		}
	}

	pg_assert_true($calls > 0, 'the scan finds the libraries\' free calls');
	pg_assert_same(array(), $unguarded, 'free calls without a PHP 7 guard');
}

function test_bundled_libraries_use_canonical_casts()
{
	$found = array();

	foreach (pg_test_bundled_php85_files() as $path) {
		foreach (token_get_all((string) file_get_contents($path)) as $token) {
			if (!is_array($token) || !in_array($token[0], array(T_INT_CAST, T_BOOL_CAST, T_DOUBLE_CAST, T_STRING_CAST), true)) {
				continue;
			}

			$cast = strtolower(preg_replace('/[\s()]/', '', $token[1]));

			if (!in_array($cast, array('int', 'bool', 'float', 'string'), true)) {
				$found[] = pg_test_bundled_php85_name($path) . ':' . $token[2] . ' ' . $token[1];
			}
		}
	}

	pg_assert_same(array(), $found, 'non-canonical casts');
}

// PHP 8.4 reports "Type $x = null" without the "?" when the file is compiled.
function test_bundled_libraries_mark_nullable_parameters()
{
	$found = array();
	// T_FN (7.4) and the qualified-name tokens (8.0) do not exist on PHP 7.1.
	$function_tokens = array(T_FUNCTION);
	$type_tokens = array(T_STRING, T_ARRAY, T_CALLABLE);

	foreach (array('T_FN') as $name) {
		if (defined($name)) {
			$function_tokens[] = constant($name);
		}
	}

	foreach (array('T_NAME_QUALIFIED', 'T_NAME_FULLY_QUALIFIED') as $name) {
		if (defined($name)) {
			$type_tokens[] = constant($name);
		}
	}

	foreach (pg_test_bundled_php85_files() as $path) {
		$tokens = array();

		foreach (token_get_all((string) file_get_contents($path)) as $token) {
			if (!is_array($token) || !in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
				$tokens[] = $token;
			}
		}

		$count = count($tokens);

		for ($i = 0; $i < $count; $i++) {
			if (!is_array($tokens[$i]) || !in_array($tokens[$i][0], $function_tokens, true)) {
				continue;
			}

			while (($i < $count) && ($tokens[$i] !== '(')) {
				$i++;
			}

			for ($depth = 0; $i < $count; $i++) {
				if ($tokens[$i] === '(') {
					$depth++;
				} elseif ($tokens[$i] === ')') {
					if (--$depth === 0) {
						break;
					}
				}

				if (!is_array($tokens[$i]) || ($tokens[$i][0] !== T_VARIABLE) || !isset($tokens[$i + 2])
					|| ($tokens[$i + 1] !== '=') || !is_array($tokens[$i + 2]) || (strtolower($tokens[$i + 2][1]) !== 'null')) {
					continue;
				}

				$type = $i - 1;

				// Skip a by-reference "&" or a variadic "...".
				if (($tokens[$type] === '&') || (is_array($tokens[$type]) && ($tokens[$type][0] === T_ELLIPSIS))) {
					$type--;
				}

				$typed = is_array($tokens[$type]) && in_array($tokens[$type][0], $type_tokens, true);

				if ($typed && ($tokens[$type - 1] !== '?') && ($tokens[$type - 1] !== '|')) {
					$found[] = pg_test_bundled_php85_name($path) . ':' . $tokens[$i][2] . ' ' . $tokens[$type][1] . ' ' . $tokens[$i][1];
				}
			}
		}
	}

	pg_assert_same(array(), $found, 'implicitly nullable parameters');
}

// The iyzico SDK's request parameters were marked "?" for PHP 8.4; each still
// takes null, and the three IyzipayResource ones stay required, as PHP had
// treated them (a default before a required parameter is no default).
function test_iyzico_sdk_nullable_parameters_keep_their_meaning()
{
	require_once PG_FUNCTIONS_DIR . '/includes/iyzipay-php/IyzipayBootstrap.php';
	IyzipayBootstrap::init();

	$methods = array(
		array('Iyzipay\IyzipayResource', 'getHttpHeadersV2', 1, false),
		array('Iyzipay\IyzipayResource', 'getHttpHeadersIsV2', 1, false),
		array('Iyzipay\IyzipayResource', 'prepareAuthorizationStringV2', 1, false),
		array('Iyzipay\IyziAuthV2Generator', 'generateAuthContent', 4, true),
		array('Iyzipay\IyziAuthV2Generator', 'getHmacSHA256Signature', 3, true),
		array('Iyzipay\IyziAuthV2Generator', 'getPayload', 1, true),
		array('Iyzipay\JsonBuilder', 'addArray', 1, true),
		array('Iyzipay\RequestStringBuilder', 'appendArray', 1, true),
	);

	foreach ($methods as $method) {
		$parameter = (new ReflectionMethod($method[0], $method[1]))->getParameters()[$method[2]];
		$label = $method[0] . '::' . $method[1] . '() $' . $parameter->getName();

		pg_assert_true($parameter->allowsNull(), $label . ' takes null');
		pg_assert_same($method[3], $parameter->isOptional(), $label . ' optional');
	}

	// Without a request, the payload is the URI's path from /v2 on.
	pg_assert_same('/v2/subscription/products', \Iyzipay\IyziAuthV2Generator::getPayload('https://sandbox-api.iyzipay.com/v2/subscription/products'), 'payload without a request');
}

// PHP 8.5 reports $http_response_header unless the function has written the
// variable before it reads it (dompdf takes it from Helpers::getFileContent()).
function test_bundled_libraries_write_http_response_header_first()
{
	$found = array();

	foreach (pg_test_bundled_php85_files() as $path) {
		$source = (string) file_get_contents($path);

		if (strpos($source, '$http_response_header') === false) {
			continue;
		}

		$tokens = token_get_all($source);
		$count = count($tokens);
		$depth = 0;
		$function_depths = array();
		$written = array();

		for ($i = 0; $i < $count; $i++) {
			$token = $tokens[$i];

			if (is_array($token) && ($token[0] === T_FUNCTION)) {
				// The body opens at the next "{"; a declaration without one
				// (an abstract method) ends at ";".
				for ($j = $i + 1; $j < $count; $j++) {
					if (($tokens[$j] === '{') || ($tokens[$j] === ';')) {
						break;
					}
				}

				if (($j < $count) && ($tokens[$j] === '{')) {
					$function_depths[] = $depth + 1;
					$written[] = false;
				}

				continue;
			}

			if (($token === '{') || (is_array($token) && in_array($token[0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) {
				$depth++;
				continue;
			}

			if ($token === '}') {
				if ($function_depths && (end($function_depths) === $depth)) {
					array_pop($function_depths);
					array_pop($written);
				}

				$depth--;
				continue;
			}

			if (!is_array($token) || ($token[0] !== T_VARIABLE) || ($token[1] !== '$http_response_header') || !$function_depths) {
				continue;
			}

			$top = count($written) - 1;

			if ($written[$top]) {
				continue;
			}

			// A write: "$var =" or a destructuring "[..., $var] =" / "list(..., $var) =".
			$k = $i + 1;

			while (($k < $count) && (is_array($tokens[$k]) ? in_array($tokens[$k][0], array(T_WHITESPACE), true) : in_array($tokens[$k], array(']', ')'), true))) {
				$k++;
			}

			if (($k < $count) && ($tokens[$k] === '=')) {
				$written[$top] = true;
			} else {
				$found[] = pg_test_bundled_php85_name($path) . ':' . $token[2];
			}
		}
	}

	pg_assert_same(array(), $found, 'reads of $http_response_header before a write');
}

// mail.php loads PHPMailer 6 from includes/phpmailer/src; the 5.2 files beside
// it carry (boolean) casts and unguarded openssl_pkey_free() calls and must
// stay unloaded.
function test_phpmailer_5_files_are_not_loaded()
{
	$found = array();
	$directory = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PG_FUNCTIONS_DIR, FilesystemIterator::SKIP_DOTS));

	foreach ($directory as $file) {
		if (substr($file->getFilename(), -4) !== '.php') {
			continue;
		}

		$name = pg_test_bundled_php85_name($file->getPathname());

		if ((strpos($name, 'includes/phpmailer/') === 0) || (strpos($name, 'data/') === 0)) {
			continue;
		}

		$source = (string) file_get_contents($file->getPathname());

		if (preg_match('/class\.phpmailer\.php|class\.smtp\.php|PHPMailerAutoload/', $source)) {
			$found[] = $name;
		}
	}

	pg_assert_same(array(), $found, 'files loading PHPMailer 5');
}
