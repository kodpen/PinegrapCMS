<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Keeps the function copies in get_file.php to the ones that are meant to be
 * there.
 *
 * router.php dispatches get_file.php without loading functions.php, so the
 * file carries local copies of the few helpers it needs. Code both sides must
 * run identically belongs in includes/authentication.php, which both include;
 * a new local copy of a function that already exists behind functions.php is
 * a second implementation that will drift from the first. This check lists
 * every function get_file.php defines and reports any that is also defined in
 * functions.php, includes/authentication.php or includes/fn/*.php, unless it
 * is one of the copies named in $intended below (each with its reason in
 * docs/_get_file_kopyalar.md).
 *
 * Usage:  php tools/check_copies.php
 * Exit:   0 when every duplicate is an intended copy, 1 otherwise. A name in
 *         $intended that is no longer a duplicate is reported as WARN (the
 *         list is out of date) without failing.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit('Forbidden');
}

// Copies get_file.php keeps on purpose. get_file.php never loads functions.php,
// and these either have a file-serving behaviour of their own (output_error()
// answers with the status code and plain HTML, without the site's error page),
// or a smaller body than the original that is all a file request needs.
$intended = array(
	'output_error',
	'db',
	'db_value',
	'db_item',
	'db_items',
	'escape',
	'log_activity',
	'initialize_user',
	'get_request_uri',
	'check_if_request_is_secure',
	'check_proxy_ssl_headers',
);

$repository = dirname(__DIR__);
$product = $repository . '/pinegrap';

$subject = $product . '/get_file.php';

$others = array_merge(
	array($product . '/functions.php', $product . '/includes/authentication.php'),
	glob($product . '/includes/fn/*.php')
);

// Names of the functions a file declares, lower-cased (PHP function names are
// case-insensitive). Read with the tokenizer so that a "function x(" inside a
// comment or a string is not a declaration; class methods and closures are
// skipped, because neither can collide with a global function.
function check_copies_declared_functions($path)
{
	$tokens = token_get_all(file_get_contents($path));
	$count = count($tokens);

	$names = array();
	$depth = 0;
	$class_depths = array();
	$class_pending = false;

	for ($i = 0; $i < $count; $i++) {

		$token = $tokens[$i];

		if (!is_array($token)) {

			if ($token === '{') {
				$depth++;

				if ($class_pending) {
					$class_depths[] = $depth;
					$class_pending = false;
				}

			} elseif ($token === '}') {

				if ($class_depths && (end($class_depths) === $depth)) {
					array_pop($class_depths);
				}

				$depth--;
			}

			continue;
		}

		// "${", "{$" and the like open a brace the closing "}" will balance.
		if (($token[0] === T_CURLY_OPEN) || ($token[0] === T_DOLLAR_OPEN_CURLY_BRACES)) {
			$depth++;
			continue;
		}

		if (in_array($token[0], array(T_CLASS, T_INTERFACE, T_TRAIT), true)) {

			// Foo::class is not a declaration.
			$previous = $i - 1;

			while (($previous >= 0) && is_array($tokens[$previous]) && ($tokens[$previous][0] === T_WHITESPACE)) {
				$previous--;
			}

			if (($previous < 0) || !is_array($tokens[$previous]) || ($tokens[$previous][0] !== T_DOUBLE_COLON)) {
				$class_pending = true;
			}

			continue;
		}

		if (($token[0] !== T_FUNCTION) || $class_depths) {
			continue;
		}

		// The next meaningful token is the name; "&" for a by-reference
		// return comes first, "(" means a closure.
		for ($next = $i + 1; $next < $count; $next++) {

			$candidate = $tokens[$next];

			if (is_array($candidate) && ($candidate[0] === T_WHITESPACE)) {
				continue;
			}

			if ($candidate === '&' || (is_array($candidate) && ($candidate[1] === '&'))) {
				continue;
			}

			if (is_array($candidate) && ($candidate[0] === T_STRING)) {
				$names[strtolower($candidate[1])] = true;
			}

			break;
		}
	}

	return array_keys($names);
}

function check_copies_relative($path, $repository)
{
	return ltrim(str_replace('\\', '/', substr($path, strlen($repository))), '/');
}

if (!is_file($subject)) {
	fwrite(STDERR, "Not found: $subject\n");
	exit(1);
}

$local = check_copies_declared_functions($subject);

$defined_elsewhere = array();

foreach ($others as $path) {

	foreach (check_copies_declared_functions($path) as $name) {
		$defined_elsewhere[$name][] = check_copies_relative($path, $repository);
	}
}

$intended_lower = array_map('strtolower', $intended);

$failures = 0;
$warnings = 0;
$duplicates = 0;

foreach ($local as $name) {

	if (!isset($defined_elsewhere[$name])) {
		continue;
	}

	$duplicates++;

	if (in_array($name, $intended_lower, true)) {
		continue;
	}

	foreach ($defined_elsewhere[$name] as $file) {
		echo "FAIL: $name is defined in get_file.php and $file\n";
		$failures++;
	}
}

foreach ($intended_lower as $name) {

	if (!in_array($name, $local, true)) {
		echo "WARN: $name is listed as an intended copy but get_file.php no longer defines it; remove it from \$intended\n";
		$warnings++;
	} elseif (!isset($defined_elsewhere[$name])) {
		echo "WARN: $name is listed as an intended copy but is no longer defined outside get_file.php; remove it from \$intended\n";
		$warnings++;
	}
}

if ($failures > 0) {
	echo "\n$failures duplicate definition(s). Move shared code to includes/authentication.php, or list the copy in \$intended with its reason in docs/_get_file_kopyalar.md.\n";
	exit(1);
}

echo 'OK: get_file.php defines ' . count($local) . ' functions; ' . $duplicates . ' are also defined behind functions.php, all of them intended copies' . (($warnings > 0) ? " ($warnings warning(s))" : '') . ".\n";
exit(0);
