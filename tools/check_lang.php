<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Checks that every translation key used in the code exists in the language
 * file, and that the designer's keys follow the literal-only rule.
 *
 * Two contracts are enforced:
 *   1. lang('English text') in PHP and _sdT('English text') in the style
 *      designer must resolve to a key present in includes/local/tr.json.
 *   2. _sdT() takes a single-quoted literal only. The server scans the file
 *      for that exact pattern, so _sdT("x"), _sdT($x) and _sdT('a' . $b)
 *      silently ship untranslated text.
 *
 * Usage:  php tools/check_lang.php [directory]     (default: pinegrap/)
 * Exit:   0 when everything resolves, 1 otherwise.
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

$skip_directories = array(
	'includes/phpmailer',
	'includes/stripe',
	'includes/iyzipay-php',
	'includes/phpexcel',
	'includes/boxpacker',
	'assets/lib',
	'data/backups',
	'data/temp',
	'data/cache',
);

$language_file = 'includes/local/tr.json';

$designer_file = 'assets/js/style_designer.js';

$root = isset($argv[1]) ? rtrim($argv[1], '/\\') : dirname(__DIR__) . '/pinegrap';

$root_real = realpath($root);

if ($root_real === false) {
	fwrite(STDERR, "Directory not found: $root\n");
	exit(1);
}

// -- the language file is the single source of truth -------------------------

$language_path = $root_real . '/' . $language_file;

if (!is_file($language_path)) {
	fwrite(STDERR, "Language file not found: $language_path\n");
	exit(1);
}

$translations = json_decode(file_get_contents($language_path), true);

if (!is_array($translations)) {
	fwrite(STDERR, 'Language file is not valid JSON: ' . json_last_error_msg() . "\n");
	exit(1);
}

// -- helpers -----------------------------------------------------------------

// A single-quoted PHP/JS literal, with escapes, as written in the source.
$literal = "'((?:[^'\\\\]|\\\\.)*)'";

function unescape_single_quoted($value) {
	return str_replace(array("\\'", '\\\\'), array("'", '\\'), $value);
}

// Comments are not code: a lang() example in a docblock is documentation, and
// flagging it sends people chasing keys that were never meant to exist.
// Blanked-out text keeps its newlines so reported line numbers stay true.
function strip_php_comments($contents) {

	$tokens = @token_get_all($contents);

	if (!is_array($tokens)) {
		return $contents;
	}

	$stripped = '';

	foreach ($tokens as $token) {

		if (!is_array($token)) {
			$stripped .= $token;
			continue;
		}

		if (($token[0] === T_COMMENT) || ($token[0] === T_DOC_COMMENT)) {
			$stripped .= str_repeat("\n", substr_count($token[1], "\n"));
			continue;
		}

		$stripped .= $token[1];

	}

	return $stripped;

}

// The JavaScript side gets a line-based pass rather than a tokenizer: blanking
// whole comment lines cannot be fooled by a quoted "/*" inside the CSS this
// file generates.
function strip_js_comment_lines($contents) {

	$lines = explode("\n", $contents);

	foreach ($lines as $number => $line) {

		$start = ltrim($line);

		if ((strpos($start, '//') === 0) || (strpos($start, '*') === 0) || (strpos($start, '/*') === 0)) {
			$lines[$number] = '';
		}

	}

	return implode("\n", $lines);

}

function is_skipped($path, $prefixes) {

	foreach ($prefixes as $prefix) {

		if (strpos($path, $prefix) === 0) {
			return true;
		}

	}

	return false;

}

$skip_real = array();

foreach ($skip_directories as $relative) {

	$path = realpath($root_real . '/' . $relative);

	if ($path !== false) {
		$skip_real[] = $path . DIRECTORY_SEPARATOR;
	}

}

// -- collect the keys the code asks for --------------------------------------

$used = array();      // key => "file:line" of its first use
$violations = array();

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($root_real, FilesystemIterator::SKIP_DOTS),
	RecursiveIteratorIterator::LEAVES_ONLY
);

$scanned = 0;

foreach ($iterator as $file) {

	$extension = strtolower($file->getExtension());

	if (($extension !== 'php') && ($extension !== 'js')) {
		continue;
	}

	$path = $file->getPathname();

	if (is_skipped($path, $skip_real)) {
		continue;
	}

	$relative = str_replace($root_real . DIRECTORY_SEPARATOR, '', $path);

	$is_designer = (str_replace('\\', '/', $relative) === $designer_file);

	// Minified twins are generated from their .src.js, so they are not scanned.
	if (($extension === 'js') && (!$is_designer)) {
		continue;
	}

	$contents = file_get_contents($path);

	$contents = ($extension === 'php') ? strip_php_comments($contents) : strip_js_comment_lines($contents);

	$scanned++;

	$patterns = array();

	if ($extension === 'php') {
		$patterns['lang'] = '/\blang\(\s*' . $literal . '/';
		$patterns['lang array'] = "/'string'\s*=>\s*" . $literal . '/';
	}

	if ($is_designer) {
		$patterns['_sdT'] = '/\b_sdT\(\s*' . $literal . '/';
	}

	foreach ($patterns as $matches_of => $pattern) {

		if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE)) {

			foreach ($matches[1] as $match) {

				$key = unescape_single_quoted($match[0]);

				if (!isset($used[$key])) {
					$line = substr_count($contents, "\n", 0, $match[1]) + 1;
					$used[$key] = $relative . ':' . $line;
				}

			}

		}

	}

	// _sdT() with anything other than a single-quoted literal is invisible to
	// the server-side scanner, so the string never reaches the translator.
	if ($is_designer) {

		// The declaration itself is not a call.
		if (preg_match_all('/(?<!function )\b_sdT\(\s*(?!\')(.{0,40})/', $contents, $matches, PREG_OFFSET_CAPTURE)) {

			foreach ($matches[1] as $match) {
				$line = substr_count($contents, "\n", 0, $match[1]) + 1;
				$violations[] = $relative . ':' . $line . '  _sdT() needs a single-quoted literal: _sdT(' . trim(substr($match[0], 0, 30)) . '…';
			}

		}

	}

}

// -- report ------------------------------------------------------------------

$missing = array();

foreach ($used as $key => $where) {

	if (!array_key_exists($key, $translations)) {
		$missing[$key] = $where;
	}

}

foreach ($violations as $violation) {
	echo 'RULE  ' . $violation . "\n";
}

foreach ($missing as $key => $where) {
	echo 'MISS  ' . $where . "\n";
	echo '      not in ' . $language_file . ': ' . $key . "\n";
}

echo "\n";
echo 'Scanned ' . $scanned . ' file(s); ' . count($used) . ' key(s) used, ' . count($translations) . ' key(s) defined.' . "\n";

if ((count($missing) > 0) || (count($violations) > 0)) {
	echo 'FAILED: ' . count($missing) . ' missing key(s), ' . count($violations) . ' rule violation(s).' . "\n";
	exit(1);
}

echo "OK: every key resolves.\n";
exit(0);
