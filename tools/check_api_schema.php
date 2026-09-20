<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Checks that the external API describes what it answers with.
 *
 * The OpenAPI document is what generated clients, Postman collections and
 * contract tests are built from. A route whose 200 carries no schema produces
 * a client that knows the address and nothing about the answer, and a test
 * suite that asserts fields the endpoint never had - which is how a working
 * endpoint comes back as a page of red assertions.
 *
 * Three contracts are enforced:
 *   1. Every route in api_schema() declares 'returns', except the few that
 *      answer with a document rather than a record (the OpenAPI file itself,
 *      the console page and its fragment) or with no content at all.
 *   2. Every object name a route or a declaration refers to is registered in
 *      api_openapi_objects() and the function behind it exists.
 *   3. Every field a presenter puts in its answer is declared. This is the one
 *      that rots: a field added to api_product_present() without a line in
 *      api_product_schema() is a field no client can see.
 *
 * Static on purpose - it reads the files rather than running the API, so it
 * needs no database, no credentials and no site.
 *
 * Usage:  php tools/check_api_schema.php [directory]   (default: pinegrap/)
 * Exit:   0 when everything is declared, 1 otherwise.
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

$root = isset($argv[1]) ? rtrim($argv[1], '/\\') : 'pinegrap';

if (!is_dir($root . '/includes/api')) {
	fwrite(STDERR, "No API folder under " . $root . "\n");
	exit(1);
}

// Routes that answer with something other than a record: the OpenAPI document
// itself, the console page, the fragment it loads, and the delete that answers
// 204 with an empty body.
$without_body = array('openapi', 'docs', 'docs.endpoints', 'webhooks.delete');

// Which presenter builds the shape each declaration describes. Only the pairs
// that exist - meta and the stock endpoints build their answers inline.
$presenters = array(
	'api_product_schema'       => 'api_product_present',
	'api_product_group_schema' => 'api_product_group_present',
	'api_order_schema'         => 'api_order_present',
	'api_customer_schema'      => 'api_customer_present',
	'api_page_schema'          => 'api_page_present',
	'api_file_schema'          => 'api_file_present',
	'api_offer_schema'         => 'api_offer_present',
	'api_webhook_schema'       => 'api_webhook_present',
	'api_seo_schema'           => 'api_seo_block',
);

$problems = array();
$warnings = array();

/**
 * The source of one function, from its signature to its closing brace.
 */
function pg_api_function_body($source, $name)
{
	if (!preg_match('/function\s+' . preg_quote($name, '/') . '\s*\(/', $source, $found, PREG_OFFSET_CAPTURE)) {
		return null;
	}

	$start = strpos($source, '{', $found[0][1]);

	if ($start === false) {
		return null;
	}

	$depth = 0;
	$length = strlen($source);

	for ($i = $start; $i < $length; $i++) {
		if ($source[$i] === '{') { $depth++; }
		elseif ($source[$i] === '}') {
			$depth--;
			if ($depth === 0) {
				return substr($source, $start, $i - $start + 1);
			}
		}
	}

	return null;
}

/**
 * The keys a function names, at the outermost level of the array it builds.
 *
 * Tokenised rather than scanned for quotes: a comment with an apostrophe in it
 * ("the shop's own copy") reads as the start of a string to anything simpler,
 * and everything after it is then misread - which is exactly the kind of quiet
 * wrong answer a checker must not give.
 *
 * Depth is counted over parentheses, so a nested array inside a field does not
 * contribute its own keys. Assignments made after the array was built
 * ($out['extra'] = ...) count too: a field added that way is as visible to a
 * client as one written inside the literal.
 */
function pg_api_array_keys($body, $base_depth = 1)
{
	$tokens = token_get_all('<?php ' . $body);

	$clean = array();

	foreach ($tokens as $token) {

		if (is_array($token) && in_array($token[0], array(T_COMMENT, T_DOC_COMMENT, T_WHITESPACE), true)) {
			continue;
		}

		$clean[] = $token;

	}

	$keys = array();
	$depth = 0;
	$count = count($clean);

	for ($i = 0; $i < $count; $i++) {

		$token = $clean[$i];

		if ($token === '(') { $depth++; continue; }
		if ($token === ')') { $depth--; continue; }

		if (!is_array($token) || ($token[0] !== T_CONSTANT_ENCAPSED_STRING)) { continue; }

		$word = trim($token[1], "'\"");

		if (!preg_match('/^[a-z][a-z0-9_]*$/', $word)) { continue; }

		$next = isset($clean[$i + 1]) ? $clean[$i + 1] : null;

		// 'name' => value, at the level the literal itself sits on.
		if (($depth === $base_depth) && is_array($next) && ($next[0] === T_DOUBLE_ARROW)) {
			$keys[$word] = true;
			continue;
		}

		// $out['name'] = value, written after the literal was built.
		$previous = isset($clean[$i - 1]) ? $clean[$i - 1] : null;
		$after = isset($clean[$i + 2]) ? $clean[$i + 2] : null;

		if (($previous === '[') && ($next === ']') && ($after === '=')) {

			$holder = isset($clean[$i - 2]) ? $clean[$i - 2] : null;

			if (is_array($holder) && ($holder[0] === T_VARIABLE)) {
				$keys[$word] = true;
			}

		}

	}

	return array_keys($keys);
}

// ── The routes ───────────────────────────────────────────────────────────
$schema_source = file_get_contents($root . '/includes/api/schema.php');

// A route block, not every 'id' => in the file: an inline shape that happens to
// describe an id field is not a route.
// A module contributes its own routes from its own file (see
// includes/api/modules.php). They answer on the same API, so they are read here
// too and held to the same contract.
$module_sources = '';

foreach (glob($root . '/includes/*/api.php') as $module_file) {

	$module_sources .= "\n" . file_get_contents($module_file);

}

$blocks = preg_split("/'id'\s*=> '/", $schema_source . $module_sources);

array_shift($blocks);

$routes = 0;
$referenced = array();
$dry_flagged = 0;

// Where the handlers live: the API's own resource files and whatever a module
// keeps beside its declaration.
$handler_sources = '';

foreach (array_merge(
	glob($root . '/includes/api/resources/*.php'),
	glob($root . '/includes/*/api.php'),
	glob($root . '/includes/*/api_*.php')) as $handler_file) {

	$handler_sources .= "\n" . file_get_contents($handler_file);

}

foreach ($blocks as $block) {

	$id = substr($block, 0, strpos($block, "'"));

	$head = substr($block, 0, 400);

	if ((strpos($head, "'method'") === false) || (strpos($head, "'handler'") === false)) {

		continue;

	}

	$routes++;

	// A route that advertises a rehearsal must have a line to stop on.
	//
	// X-Dry-Run is offered per route, and the offer is the whole promise: a
	// handler that takes the header and writes anyway is worse than one that
	// refuses it, because the caller asked precisely so that nothing would
	// happen. The flag and the stop are in different files, which is exactly
	// the pair that drifts, so they are checked against each other here.
	if (strpos($block, "'dry_run' => true") !== false) {

		$dry_flagged++;

		if (preg_match("/'handler'\s*=> '([a-z_]+)'/", $head, $found)) {

			$body = pg_api_function_body($handler_sources, $found[1]);

			if ($body === '') {

				$problems[] = $id . ': declares dry_run but ' . $found[1] . '() was not found to check.';

			} elseif ((strpos($body, 'api_dry_run_stop(') === false)
				&& (strpos($body, 'api_dry_run_requested(') === false)) {

				$problems[] = $id . ': declares dry_run, but ' . $found[1] . '() never stops for it - X-Dry-Run would write.';

			}

		}

	}

	$declares = (strpos($block, "'returns'") !== false);

	if (!$declares && !in_array($id, $without_body, true)) {
		$problems[] = $id . ': no \'returns\' - nothing describes what this endpoint answers with.';
		continue;
	}

	if (!$declares) {
		continue;
	}

	$line_start = strpos($block, "'returns'");
	$line = substr($block, $line_start, strpos($block, "\n", $line_start) - $line_start);

	if (preg_match_all("/'([A-Z][A-Za-z]*)(\[\])?'/", $line, $names)) {
		foreach ($names[1] as $name) { $referenced[$name] = $id; }
	}

}

// ── The registry ─────────────────────────────────────────────────────────
$openapi_source = file_get_contents($root . '/includes/api/openapi.php');

$registry_body = pg_api_function_body($openapi_source, 'api_openapi_objects');

$registry = array();

if ($registry_body === null) {
	$problems[] = 'api_openapi_objects() is missing from includes/api/openapi.php.';
} elseif (preg_match_all("/'([A-Za-z]+)'\s*=> '([a-z_]+)'/", $registry_body, $pairs, PREG_SET_ORDER)) {
	foreach ($pairs as $pair) { $registry[$pair[1]] = $pair[2]; }
}

// The modules' own objects, declared the same way in their own files.
foreach (glob($root . '/includes/*/api.php') as $module_file) {

	$module_source = file_get_contents($module_file);

	if (!preg_match('/function\s+([a-z_]*openapi_objects)\s*\(/', $module_source, $named)) {
		continue;
	}

	$module_body = pg_api_function_body($module_source, $named[1]);

	if (($module_body !== null) && preg_match_all("/'([A-Za-z]+)'\s*=> '([a-z_]+)'/", $module_body, $pairs, PREG_SET_ORDER)) {
		foreach ($pairs as $pair) { $registry[$pair[1]] = $pair[2]; }
	}

}

// Names that are built by the document itself rather than declared by a file.
$built_in = array('Paging', 'Error');

foreach ($referenced as $name => $route_id) {
	if (!isset($registry[$name]) && !in_array($name, $built_in, true)) {
		$problems[] = $route_id . ': returns ' . $name . ', which no entry in api_openapi_objects() declares.';
	}
}

// ── The declarations, and the presenters beside them ─────────────────────
$sources = array();

foreach (array_merge(
	glob($root . '/includes/api/*.php'),
	glob($root . '/includes/api/resources/*.php'),
	// A module's own files, by the naming the seam expects: api.php declares
	// what it contributes, api_*.php holds the code behind it.
	glob($root . '/includes/*/api*.php')
) as $file) {
	$sources[$file] = file_get_contents($file);
}

$checked = 0;

foreach ($registry as $name => $declare) {

	$body = null;

	foreach ($sources as $file => $source) {
		$found = pg_api_function_body($source, $declare);
		if ($found !== null) { $body = $found; $file_of = $file; break; }
	}

	if ($body === null) {
		$problems[] = $name . ': api_openapi_objects() names ' . $declare . '(), which does not exist.';
		continue;
	}

	$declared = pg_api_array_keys($body);

	if (empty($declared)) {
		$problems[] = $declare . '(): declares no fields.';
		continue;
	}

	$checked++;

	// Every object this declaration points at has to be registered too.
	if (preg_match_all("/'([A-Z][A-Za-z]*)(\[\])?'/", $body, $names)) {
		foreach ($names[1] as $referred) {
			if (!isset($registry[$referred]) && !in_array($referred, $built_in, true)) {
				$problems[] = $declare . '(): refers to ' . $referred . ', which is not registered.';
			}
		}
	}

	if (!isset($presenters[$declare])) {
		continue;
	}

	$presenter = $presenters[$declare];
	$presenter_body = null;

	foreach ($sources as $source) {
		$found = pg_api_function_body($source, $presenter);
		if ($found !== null) { $presenter_body = $found; break; }
	}

	if ($presenter_body === null) {
		$warnings[] = $declare . '(): its presenter ' . $presenter . '() was not found; the field list was not compared.';
		continue;
	}

	$built = pg_api_array_keys($presenter_body);

	$undeclared = array_diff($built, $declared);

	if (!empty($undeclared)) {
		$problems[] = $presenter . '() answers with ' . implode(', ', $undeclared)
			. ' - add ' . (count($undeclared) === 1 ? 'that line' : 'those lines') . ' to ' . $declare . '().';
	}

	$stale = array_diff($declared, $built);

	if (!empty($stale)) {
		$warnings[] = $declare . '() declares ' . implode(', ', $stale)
			. ', which ' . $presenter . '() does not build - fine when the endpoint adds them, stale otherwise.';
	}

}

// ── The error codes ──────────────────────────────────────────────────────
// A code is what a client branches on, so one that appears in an answer and
// nowhere in the catalogue is a code nobody can look up. The 409s are exempt:
// they are named by the endpoint that can produce them, because
// 'order_already_shipped' means nothing on a page about pages.
$catalogue_body = pg_api_function_body($schema_source, 'api_error_catalogue');

$documented = array();

if ($catalogue_body === null) {

	$problems[] = 'api_error_catalogue() is missing from includes/api/schema.php.';

} elseif (preg_match_all("/'([a-z_]+)'\s*=> array\(/", $catalogue_body, $found)) {

	$documented = $found[1];

}

$used = array();

foreach ($sources as $file => $source) {

	if (preg_match_all("/api_fail\(\s*([0-9]{3})\s*,\s*'([a-z_]+)'/", $source, $calls, PREG_SET_ORDER)) {

		foreach ($calls as $call) {

			if ((int)$call[1] === 409) { continue; }

			$used[$call[2]] = (int)$call[1];

		}

	}

}

foreach ($used as $code => $status) {

	if (!in_array($code, $documented, true)) {

		$problems[] = 'api_fail() answers ' . $code . ' (' . $status . '), which api_error_catalogue() does not explain.';

	}

}

foreach ($documented as $code) {

	if (!isset($used[$code]) && !in_array($code, array('idempotency_key_reused'), true)) {

		$warnings[] = 'api_error_catalogue() explains ' . $code . ', which nothing answers with any more.';

	}

}

// ── Report ───────────────────────────────────────────────────────────────
foreach ($warnings as $warning) {
	echo "WARN  " . $warning . "\n";
}

foreach ($problems as $problem) {
	echo "FAIL  " . $problem . "\n";
}

echo "\nChecked " . $routes . " route(s), " . $checked . " object declaration(s), "
	. count($used) . " error code(s) and " . $dry_flagged . " rehearsable write(s).\n";

if (empty($problems)) {
	echo "OK: every endpoint says what it answers with.\n";
	exit(0);
}

echo "FAILED: " . count($problems) . " problem(s).\n";
exit(1);
