<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Scheduled actions move into Flows with nothing lost: every key a scheduled
 * action has (what it is made of, its "when", the events, the conditions,
 * the twelve steps, each option they read and the values one takes from a
 * list, the webhook's body, the outcomes, the chain limits, the
 * placeholders, the states, the news it gives, how it writes a record, its
 * card - the buttons, the rest it draws, where it is drawn -, the list, the
 * panel routes, the ways into its form, Pinegrap AI's drafts, the links to
 * it, the inbox kinds the API shows) is listed in the parity table below
 * with the merge slice and the box that keeps it, or 'form' with the reason
 * it stays in the form. The keys are read from the code - by calling the
 * functions that run without a database, and from their source where a
 * table probe or a constant hides some (tests/helpers/source_reader.php) -
 * so a key added to the code and not to the table fails here, and so does a
 * key the table lists that the code no longer has. A reader that models
 * source it cannot call throws when that source takes a shape it does not
 * read (an entry written another way, a new condition), rather than miss
 * what it adds.
 *
 * The slices are those of the merge plan: 1 one screen, 2 show on canvas,
 * 3 parity boxes, 4 new rules on the canvas, 5 convert to flow.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The module files hold functions and constants only; loading them runs nothing.
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// ─── Reading the code ───────────────────────────────────────────────────

// A file of the product tree, read once.
function workspace_flows_parity_file($relative)
{
	static $files = array();

	if (!isset($files[$relative])) {
		$files[$relative] = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
	}

	return $files[$relative];
}

// The source of a loaded function, read once.
function workspace_flows_parity_source($function)
{
	static $sources = array();

	if (!isset($sources[$function])) {
		$sources[$function] = pg_test_function_source($function);
	}

	return $sources[$function];
}

// The branches of the switch statements in a piece of code: case => the
// code up to the next case (the last up to the end); 'default' for default.
function workspace_flows_parity_cases($source)
{
	preg_match_all("/^\s*(?:case '([a-z_.]+)'|(default)):/m", $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

	$out = array();

	foreach ($matches as $index => $match) {
		$start = $match[0][1] + strlen($match[0][0]);
		$end = isset($matches[$index + 1]) ? $matches[$index + 1][0][1] : strlen($source);
		$name = (isset($match[2]) && ($match[2][1] >= 0)) ? 'default' : $match[1][0];
		$out[$name] = substr($source, $start, $end - $start);
	}

	return $out;
}

// The keys a piece of code reads from an array variable: $variable['key'].
function workspace_flows_parity_reads($source, $variable)
{
	preg_match_all('/\$' . preg_quote($variable, '/') . "\['([a-z_]+)'\]/", $source, $matches);

	return array_values(array_unique($matches[1]));
}

// The literal written right after a marker in some code (the list of an
// in_array(), an array assigned), read as source_reader.php reads literals.
function workspace_flows_parity_list_after($source, $marker)
{
	$at = strpos($source, $marker);

	if ($at === false) {
		throw new RuntimeException('Not found in the source: ' . $marker);
	}

	$tokens = pg_test_source_tokens(substr($source, $at + strlen($marker)));
	$i = 0;

	return pg_test_source_value($tokens, $i);
}

// Where the bracket opened at $open closes in JavaScript code, stepping over
// strings and comments.
function workspace_flows_parity_js_close($code, $open)
{
	$pairs = array('(' => ')', '{' => '}', '[' => ']');
	$stack = array();
	$length = strlen($code);

	for ($i = $open; $i < $length; $i++) {
		$char = $code[$i];

		if (($char === '/') && (($code[$i + 1] ?? '') === '/')) {
			$i = strpos($code, "\n", $i);

			if ($i === false) {
				break;
			}

			continue;
		}

		if (($char === '/') && (($code[$i + 1] ?? '') === '*')) {
			$end = strpos($code, '*/', $i + 2);

			if ($end === false) {
				break;
			}

			$i = $end + 1;
			continue;
		}

		if (($char === "'") || ($char === '"') || ($char === '`')) {
			for ($i++; ($i < $length) && ($code[$i] !== $char); $i++) {
				if ($code[$i] === '\\') {
					$i++;
				}
			}

			continue;
		}

		if (isset($pairs[$char])) {
			$stack[] = $pairs[$char];
		} elseif (in_array($char, $pairs, true)) {
			if (array_pop($stack) !== $char) {
				throw new RuntimeException('Brackets do not match near offset ' . $i . '.');
			}

			if (empty($stack)) {
				return $i;
			}
		}
	}

	throw new RuntimeException('A bracket that never closes at offset ' . $open . '.');
}

// The code of a JavaScript function: from "function name(" to its brace.
function workspace_flows_parity_js_function($relative, $name)
{
	$code = workspace_flows_parity_file($relative);
	$start = strpos($code, 'function ' . $name . '(');

	if ($start === false) {
		throw new RuntimeException($relative . ' has no function ' . $name . '().');
	}

	$open = strpos($code, '{', strpos($code, ')', $start));

	return substr($code, $start, workspace_flows_parity_js_close($code, $open) - $start + 1);
}

// The arguments of every call of a JavaScript function in some code, each
// call a list of its arguments as written (runs of whitespace made one),
// keyed by the offset where the call starts.
function workspace_flows_parity_js_calls($code, $name)
{
	$calls = array();
	$offset = 0;

	while (preg_match('/(?<![A-Za-z0-9_.$])' . preg_quote($name, '/') . '\(/', $code, $match, PREG_OFFSET_CAPTURE, $offset)) {
		$open = $match[0][1] + strlen($match[0][0]) - 1;
		$close = workspace_flows_parity_js_close($code, $open);
		$offset = $close;

		if (substr($code, max(0, $match[0][1] - 9), 9) === 'function ') {
			continue;
		}

		$args = array();
		$depth = 0;
		$current = '';
		$inner = substr($code, $open + 1, $close - $open - 1);

		for ($i = 0; $i < strlen($inner); $i++) {
			$char = $inner[$i];

			if (in_array($char, array("'", '"', '`'), true)) {
				$end = $i;

				for ($end++; ($end < strlen($inner)) && ($inner[$end] !== $char); $end++) {
					if ($inner[$end] === '\\') {
						$end++;
					}
				}

				$current .= substr($inner, $i, $end - $i + 1);
				$i = $end;
				continue;
			}

			if (in_array($char, array('(', '{', '['), true)) {
				$depth++;
			} elseif (in_array($char, array(')', '}', ']'), true)) {
				$depth--;
			}

			if (($char === ',') && ($depth === 0)) {
				$args[] = trim(preg_replace('/\s+/', ' ', $current));
				$current = '';
				continue;
			}

			$current .= $char;
		}

		$args[] = trim(preg_replace('/\s+/', ' ', $current));
		$calls[$match[0][1]] = $args;
	}

	return $calls;
}

// The name of the innermost named JavaScript function (function name() or
// name: function ()) whose body holds an offset of some code; '' for none.
function workspace_flows_parity_js_enclosing($code, $offset)
{
	preg_match_all('/(?:\bfunction\s+([A-Za-z_$][\w$]*)\s*\(|(?<![\w$.])([A-Za-z_$][\w$]*)\s*:\s*function\s*\()/', substr($code, 0, $offset), $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

	foreach (array_reverse($found) as $match) {
		$name = ((($match[2][1] ?? -1) >= 0) && ($match[2][0] !== '')) ? $match[2][0] : $match[1][0];
		$open = strpos($code, '{', strpos($code, ')', $match[0][1]));

		if (workspace_flows_parity_js_close($code, $open) > $offset) {
			return $name;
		}
	}

	return '';
}

// The keys of the labels some JavaScript code writes with t('key').
function workspace_flows_parity_t_keys($code)
{
	preg_match_all("/\bt\('([a-z_]+)'[,)]/", $code, $keys);

	return array_values(array_unique($keys[1]));
}

// The fields of a variable some JavaScript code reads: variable.field (not
// other.variable.field).
function workspace_flows_parity_js_fields($code, $variable)
{
	preg_match_all('/(?<![A-Za-z0-9_$.])' . preg_quote($variable, '/') . '\.([a-z_]+)/', $code, $fields);

	return array_values(array_unique($fields[1]));
}

// The index of the token before $i that is not whitespace, -1 for none.
function workspace_flows_parity_token_before(array $tokens, $i)
{
	for ($i--; $i >= 0; $i--) {
		if (!is_array($tokens[$i]) || ($tokens[$i][0] !== T_WHITESPACE)) {
			return $i;
		}
	}

	return -1;
}

// The entries a function writes into an array variable as literals: the
// literal it is set to ($var = array(...)), those added to it ($var +=
// array(...)) and the entries set one at a time ($var['key'] = array(...)),
// read as source_reader.php reads literals, however many lines an entry
// takes and in whatever order its keys come. Any other write to the variable
// (a key worked out by code, a nested key, a merge, an unset) throws: the
// reader would miss what it adds or takes away, so it has to learn it first.
function workspace_flows_parity_literal_entries($function, $variable)
{
	$tokens = pg_test_source_tokens(workspace_flows_parity_source($function));
	$count = count($tokens);
	$assignments = array('=', '+=', '-=', '*=', '/=', '.=', '%=', '|=', '&=', '^=', '<<=', '>>=', '**=', '??=');
	$out = array();

	for ($i = 0; $i < $count; $i++) {
		if (!is_array($tokens[$i]) || ($tokens[$i][0] !== T_VARIABLE) || ($tokens[$i][1] !== $variable)) {
			continue;
		}

		$before = workspace_flows_parity_token_before($tokens, $i);
		$previous = ($before >= 0) ? $tokens[$before] : null;
		$call = ($previous === '(') ? workspace_flows_parity_token_before($tokens, $before) : -1;
		$unset = ($call >= 0) && is_array($tokens[$call]) && ($tokens[$call][0] === T_UNSET);
		$static = is_array($previous) && ($previous[0] === T_STATIC);

		// Its subscripts: a literal key, or null for code.
		$keys = array();
		$j = $i + 1;
		pg_test_source_skip($tokens, $j);

		while (($tokens[$j] ?? null) === '[') {
			$inner = array();
			$depth = 0;

			for ($j++; isset($tokens[$j]); $j++) {
				if ($tokens[$j] === '[') {
					$depth++;
				} elseif ($tokens[$j] === ']') {
					if ($depth === 0) {
						break;
					}

					$depth--;
				}

				if (!is_array($tokens[$j]) || ($tokens[$j][0] !== T_WHITESPACE)) {
					$inner[] = $tokens[$j];
				}
			}

			$keys[] = ((count($inner) === 1) && is_array($inner[0]) && ($inner[0][0] === T_CONSTANT_ENCAPSED_STRING)) ? pg_test_source_string($inner[0][1]) : null;
			$j++;
			pg_test_source_skip($tokens, $j);
		}

		$operator = isset($tokens[$j]) ? pg_test_source_text($tokens[$j]) : '';
		$written = $unset || (($previous !== null) && (pg_test_source_text($previous) === '&')) || in_array($operator, $assignments, true);

		if (!$written) {
			continue;
		}

		$at = $j + 1;
		pg_test_source_skip($tokens, $at);
		$literal = !$unset && pg_test_source_array_starts($tokens, $at);

		if ($static && empty($keys) && ($operator === '=')) {
			continue;
		}

		if ($literal && empty($keys) && in_array($operator, array('=', '+='), true)) {
			$value = pg_test_source_value($tokens, $at);
			$out = ($operator === '=') ? $value : $out + $value;
			continue;
		}

		if ($literal && (count($keys) === 1) && is_string($keys[0]) && ($operator === '=')) {
			$out[$keys[0]] = pg_test_source_value($tokens, $at);
			continue;
		}

		throw new RuntimeException($function . '() writes ' . $variable . ' in a way workspace_flows_parity_literal_entries() does not read (' . ($unset ? 'unset' : $variable . str_repeat('[...]', count($keys)) . ' ' . $operator) . '): teach it to read that.');
	}

	return $out;
}

// The functions some PHP code calls by name (not methods, not the function
// it declares).
function workspace_flows_parity_php_calls($code)
{
	$tokens = pg_test_source_tokens($code);
	$out = array();

	foreach ($tokens as $i => $token) {
		if (!is_array($token) || ($token[0] !== T_STRING)) {
			continue;
		}

		$next = $i + 1;
		pg_test_source_skip($tokens, $next);
		$before = workspace_flows_parity_token_before($tokens, $i);

		if ((($tokens[$next] ?? null) === '(') && !(($before >= 0) && is_array($tokens[$before]) && in_array($tokens[$before][0], array(T_FUNCTION, T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NEW), true))) {
			$out[] = $token[1];
		}
	}

	return array_values(array_unique($out));
}

// The Workspace functions some PHP code hands a whole array variable to (not
// one of its values), each with the name it gives it, and on into those they
// hand it to in turn: [function, parameter] (parameter without its $).
function workspace_flows_parity_handed($code, $variable, array &$seen)
{
	$tokens = pg_test_source_tokens($code);
	$count = count($tokens);
	$out = array();

	for ($i = 0; $i < $count; $i++) {
		if (!is_array($tokens[$i]) || ($tokens[$i][0] !== T_STRING) || (strpos($tokens[$i][1], 'ws_') !== 0)) {
			continue;
		}

		$before = workspace_flows_parity_token_before($tokens, $i);
		$j = $i + 1;
		pg_test_source_skip($tokens, $j);

		if ((($tokens[$j] ?? null) !== '(') || (($before >= 0) && is_array($tokens[$before]) && in_array($tokens[$before][0], array(T_FUNCTION, T_OBJECT_OPERATOR, T_DOUBLE_COLON), true))) {
			continue;
		}

		// The arguments, each a list of its tokens, up to the bracket that
		// closes the call.
		$args = array(array());
		$depth = 0;

		for ($j++; $j < $count; $j++) {
			$token = $tokens[$j];

			if (in_array($token, array('(', '[', '{'), true) || (is_array($token) && in_array($token[0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) {
				$depth++;
			} elseif (in_array($token, array(')', ']', '}'), true)) {
				if ($depth === 0) {
					break;
				}

				$depth--;
			} elseif (($token === ',') && ($depth === 0)) {
				$args[] = array();
				continue;
			}

			$args[count($args) - 1][] = $token;
		}

		foreach ($args as $index => $arg) {
			$whole = false;

			foreach ($arg as $k => $token) {
				if (is_array($token) && ($token[0] === T_VARIABLE) && ($token[1] === '$' . $variable)) {
					$next = $k + 1;
					pg_test_source_skip($arg, $next);
					$whole = $whole || (($arg[$next] ?? null) !== '[');
				}
			}

			if (!$whole) {
				continue;
			}

			$function = $tokens[$i][1];

			if (!function_exists($function)) {
				throw new RuntimeException('$' . $variable . ' is handed to ' . $function . '(), which the test does not load: load it, so the keys it reads are read.');
			}

			$parameters = (new ReflectionFunction($function))->getParameters();

			if (!isset($parameters[$index])) {
				throw new RuntimeException('$' . $variable . ' is handed to ' . $function . '() as an argument it has no parameter for.');
			}

			$name = $parameters[$index]->getName();

			if (isset($seen[$function . '$' . $name])) {
				continue;
			}

			$seen[$function . '$' . $name] = true;
			$out[] = array($function, $name);
			$out = array_merge($out, workspace_flows_parity_handed(workspace_flows_parity_source($function), $name, $seen));
		}
	}

	return $out;
}

// The control flow of some PHP code as the words that steer it (if,
// foreach, continue, return...), for a reader that models the conditions of
// a function: a condition added changes it.
function workspace_flows_parity_control_flow($code)
{
	$words = array();

	foreach (pg_test_source_tokens($code) as $token) {
		if (is_array($token) && in_array($token[0], array(T_IF, T_ELSEIF, T_ELSE, T_FOREACH, T_FOR, T_WHILE, T_DO, T_SWITCH, T_CASE, T_DEFAULT, T_CONTINUE, T_BREAK, T_RETURN, T_UNSET, T_GOTO, T_THROW), true)) {
			$words[] = strtolower($token[1]);
		} elseif ($token === '?') {
			$words[] = '?';
		}
	}

	return implode(' ', $words);
}

// The functions of some PHP code (a whole file) whose code matches a
// pattern: for each match, the last function started before it.
function workspace_flows_parity_functions_at($code, $pattern)
{
	preg_match_all($pattern, $code, $matches, PREG_OFFSET_CAPTURE);

	$out = array();

	foreach ($matches[0] as $match) {
		preg_match_all('/^\s*function\s+([A-Za-z0-9_]+)\s*\(/m', substr($code, 0, $match[1]), $functions);
		$out[] = empty($functions[1]) ? '(outside a function)' : end($functions[1]);
	}

	return array_values(array_unique($out));
}

// The PHP files of the product: those at its root and under includes/, the
// vendored libraries left out (as tools/lint.php leaves them out).
function workspace_flows_parity_product_files()
{
	static $files = null;

	if ($files === null) {
		$root = realpath(PG_FUNCTIONS_DIR);
		$vendored = array();

		foreach (array('phpmailer', 'stripe', 'iyzipay-php', 'phpexcel', 'boxpacker', 'dompdf') as $folder) {
			$vendored[] = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR;
		}

		$files = glob($root . DIRECTORY_SEPARATOR . '*.php');

		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . DIRECTORY_SEPARATOR . 'includes', FilesystemIterator::SKIP_DOTS)) as $file) {
			$path = $file->getPathname();

			if (!$file->isFile() || (substr($path, -4) !== '.php')) {
				continue;
			}

			foreach ($vendored as $prefix) {
				if (strpos($path, $prefix) === 0) {
					continue 2;
				}
			}

			$files[] = $path;
		}

		sort($files);
	}

	return $files;
}

// Every function of the Workspace module whose source names a word.
function workspace_flows_parity_functions_naming($word)
{
	static $sources = null;

	if ($sources === null) {
		$sources = array();
		$folder = realpath(PG_FUNCTIONS_DIR . '/includes/workspace') . DIRECTORY_SEPARATOR;

		foreach (get_defined_functions()['user'] as $function) {
			$reflection = new ReflectionFunction($function);

			if (strpos((string) $reflection->getFileName(), $folder) === 0) {
				$sources[$reflection->getName()] = workspace_flows_parity_source($reflection->getName());
			}
		}

		ksort($sources);
	}

	$out = array();

	foreach ($sources as $function => $source) {
		if (preg_match('/\b' . preg_quote($word, '/') . '\b/', $source) && (strpos($source, 'function ' . $word . '(') === false)) {
			$out[] = $function;
		}
	}

	return $out;
}

// ─── The keys, as the code has them ─────────────────────────────────────

// The branches of ws_scheduled_rule_input(): one per kind of rule.
function workspace_flows_parity_rule_cases()
{
	return workspace_flows_parity_cases(workspace_flows_parity_source('ws_scheduled_rule_input'));
}

// What an action is made of: the keys the form sends (data.key, read by
// ws_scheduled_input() and ws_scheduled_save()), those of a follow-up
// (follow.key), the row the check gives back to be written (row.key) and
// the columns ws_scheduled_decode() reads as JSON (column.key).
function workspace_flows_parity_read_action()
{
	$input = workspace_flows_parity_source('ws_scheduled_input');
	$out = array();

	foreach (array_merge(workspace_flows_parity_reads($input, 'data'), workspace_flows_parity_reads(workspace_flows_parity_source('ws_scheduled_save'), 'data')) as $key) {
		$out[] = 'data.' . $key;
	}

	foreach (workspace_flows_parity_reads($input, 'item') as $key) {
		$out[] = 'follow.' . $key;
	}

	$at = strrpos($input, "'row' => ");
	$tokens = pg_test_source_tokens(substr($input, (int) $at + strlen("'row' => ")));
	$i = 0;
	$row = ($at === false) ? null : pg_test_source_value($tokens, $i);

	if (!is_array($row)) {
		throw new RuntimeException('ws_scheduled_input() no longer ends with \'row\' => array(...): teach workspace_flows_parity_read_action() where its row is.');
	}

	foreach (array_keys($row) as $key) {
		$out[] = 'row.' . $key;
	}

	foreach (workspace_flows_parity_list_after(workspace_flows_parity_source('ws_scheduled_decode'), 'foreach (') as $column) {
		$out[] = 'column.' . $column;
	}

	return array_values(array_unique($out));
}

// The kinds of rule that say when an action runs (one per action).
function workspace_flows_parity_read_when()
{
	return workspace_flows_parity_list_after(workspace_flows_parity_source('ws_scheduled_input'), "in_array(\$checked['rule']['type'], ");
}

function workspace_flows_parity_read_repeats()
{
	return array_keys(ws_scheduled_repeats());
}

// The keys each kind of rule reads from what the form sends: kind.key.
function workspace_flows_parity_read_rule_options()
{
	$out = array();

	foreach (workspace_flows_parity_rule_cases() as $kind => $code) {
		foreach (workspace_flows_parity_reads($code, 'rule') as $key) {
			$out[] = $kind . '.' . $key;
		}
	}

	return $out;
}

// The events of ws_events_catalog() as written: event => [filter, channel].
// Some are only there with the shop, the approvals or the guests.
function workspace_flows_parity_events()
{
	$out = array();

	foreach (workspace_flows_parity_literal_entries('ws_events_catalog', '$catalog') as $event => $info) {
		$filter = is_array($info) ? ($info['filter'] ?? '') : null;
		$channel = is_array($info) ? ($info['channel'] ?? false) : null;

		if (!is_string($filter) || !is_bool($channel)) {
			throw new RuntimeException('The filter or the channel of ' . $event . ' in ws_events_catalog() is worked out by code: teach workspace_flows_parity_events() to read it.');
		}

		$out[$event] = array($filter, $channel);
	}

	return $out;
}

function workspace_flows_parity_read_events()
{
	return array_keys(workspace_flows_parity_events());
}

// The filters an event rule takes: the catalogue's filters, and channel.
function workspace_flows_parity_read_event_filters()
{
	$out = array();

	foreach (workspace_flows_parity_events() as $info) {
		if ($info[0] !== '') {
			$out[] = $info[0];
		}

		if ($info[1]) {
			$out[] = 'channel';
		}
	}

	return array_values(array_unique($out));
}

// The values of the filters that take one from a list: filter=value, the
// lists as ws_scheduled_rule_input() names them ($filter_values).
function workspace_flows_parity_read_event_filter_values()
{
	$out = array();
	$lists = pg_test_source_array_in(workspace_flows_parity_rule_cases()['event'], '$filter_values');

	foreach ($lists as $filter => $expression) {
		if (!($expression instanceof PgTestSourceExpression) || !preg_match('/(ws_[a-z_]+)\(\)\s*(?::|$)/', $expression->code, $call)) {
			throw new RuntimeException('The values of the ' . $filter . ' filter are not read from a function: teach workspace_flows_parity_read_event_filter_values() to read them.');
		}

		foreach (array_keys(call_user_func($call[1])) as $value) {
			$out[] = $filter . '=' . $value;
		}
	}

	return $out;
}

// The kinds of rule that are conditions: all but the "when" kinds.
function workspace_flows_parity_read_condition_kinds()
{
	return array_values(array_diff(array_keys(workspace_flows_parity_rule_cases()), workspace_flows_parity_read_when()));
}

// The kinds of record a condition looks at, with their fields, as
// ws_scheduled_record_types() builds them from the change catalogue (read
// from source: its probes drop kinds a site does not run): the kinds it
// skips left out, a form's answers left out, the task's own fields.
function workspace_flows_parity_record_types()
{
	$source = workspace_flows_parity_source('ws_scheduled_record_types');

	// The function as this reader models it: one loop over the change
	// catalogue that skips the kinds of one in_array() and drops the fields
	// of one kind, then the task's own. A condition, a skip or a write added
	// to it changes this, and the reader has to learn it first.
	$flow = workspace_flows_parity_control_flow($source);
	preg_match_all('/\$out\[[^\]]*\]\s*=(?!=)/', $source, $writes);
	preg_match_all("/\\\$field\[0\]\s*!==\s*'([a-z_]+)'/", $source, $dropped);

	if (($flow !== 'foreach if continue return return') || (count($writes[0]) !== 2) || (count($dropped[1]) !== 1)) {
		throw new RuntimeException('ws_scheduled_record_types() is no longer the loop workspace_flows_parity_record_types() models (control flow "' . $flow . '", ' . count($writes[0]) . ' writes to $out): teach the reader what it does now.');
	}

	$skipped = workspace_flows_parity_list_after($source, 'in_array($type, ');
	$out = array();

	foreach (pg_test_ws_change_types() as $type => $info) {
		if (in_array($type, $skipped, true)) {
			continue;
		}

		$out[$type] = array();

		foreach ($info['fields'] as $name => $field) {
			if ($field[0] !== $dropped[1][0]) {
				$out[$type][$name] = $field;
			}
		}
	}

	$task = workspace_flows_parity_list_after($source, "\$out['task'] = ");
	$out['task'] = $task['fields'];

	return $out;
}

function workspace_flows_parity_read_record_types()
{
	return array_keys(workspace_flows_parity_record_types());
}

// The fields a condition can look at: type.field, those with a column (the
// rule refuses the others).
function workspace_flows_parity_read_record_fields()
{
	$out = array();

	foreach (workspace_flows_parity_record_types() as $type => $fields) {
		foreach ($fields as $name => $field) {
			if ($field[3] !== '') {
				$out[] = $type . '.' . $name;
			}
		}
	}

	return $out;
}

function workspace_flows_parity_read_operators()
{
	return array_keys(ws_scheduled_operators());
}

// The counts of ws_scheduled_metrics() as written (the task time, shop and
// ERP counts are only there where the site has them).
function workspace_flows_parity_read_metrics()
{
	return array_keys(workspace_flows_parity_literal_entries('ws_scheduled_metrics', '$metrics'));
}

function workspace_flows_parity_read_count_operators()
{
	return ws_scheduled_count_operators();
}

// The steps: the branches of ws_scheduled_do().
function workspace_flows_parity_read_steps()
{
	return array_keys(workspace_flows_parity_cases(workspace_flows_parity_source('ws_scheduled_do')));
}

// The code of each step, with the name it gives its action: where the
// form's input is checked (ws_scheduled_action_input()), where it runs
// (ws_scheduled_do()), and the Workspace functions either hands the whole
// action to, however deep: step => [[code, variable], ...].
function workspace_flows_parity_step_code()
{
	static $out = null;

	if ($out !== null) {
		return $out;
	}

	$input = workspace_flows_parity_cases(workspace_flows_parity_source('ws_scheduled_action_input'));
	$out = array();

	foreach (workspace_flows_parity_cases(workspace_flows_parity_source('ws_scheduled_do')) as $step => $code) {
		$parts = array(array($input[$step] ?? '', 'action'), array($code, 'action'));
		$seen = array();

		foreach (array($input[$step] ?? '', $code) as $piece) {
			foreach (workspace_flows_parity_handed($piece, 'action', $seen) as $handed) {
				$parts[] = array(workspace_flows_parity_source($handed[0]), $handed[1]);
			}
		}

		$out[$step] = $parts;
	}

	return $out;
}

// The keys each step reads from its action, in all its code: step.key.
function workspace_flows_parity_read_step_options()
{
	$out = array();

	foreach (workspace_flows_parity_step_code() as $step => $parts) {
		$keys = array();

		foreach ($parts as $part) {
			$keys = array_merge($keys, workspace_flows_parity_reads($part[0], $part[1]));
		}

		foreach (array_unique($keys) as $key) {
			$out[] = $step . '.' . $key;
		}
	}

	return $out;
}

// The values a step option takes from a list written in the code: an option
// compared as (string) ($action['mode'] ?? 'status') === 'ssl', its default,
// the values it is compared with and those the check writes ('mode' =>
// 'ssl'): step.key=value. Such an option read any other way throws, so a
// value added there is not missed.
function workspace_flows_parity_read_step_option_values()
{
	$out = array();

	foreach (workspace_flows_parity_step_code() as $step => $parts) {
		$values = array();

		foreach ($parts as $part) {
			preg_match_all('/\$' . preg_quote($part[1], '/') . "\['([a-z_]+)'\]\s*\?\?\s*'([a-z_]*)'\s*\)\s*[!=]==\s*'([a-z_]+)'/", $part[0], $compared, PREG_SET_ORDER);

			foreach ($compared as $match) {
				$values[$match[1]][] = $match[3];

				if ($match[2] !== '') {
					$values[$match[1]][] = $match[2];
				}
			}
		}

		foreach (array_keys($values) as $key) {
			foreach ($parts as $part) {
				$variable = preg_quote($part[1], '/');
				$reads = preg_match_all('/\$' . $variable . "\['" . $key . "'\]/", $part[0]);
				$known = preg_match_all('/\$' . $variable . "\['" . $key . "'\]\s*\?\?\s*'[a-z_]*'\s*\)\s*[!=]==\s*'[a-z_]+'/", $part[0]);

				if ($reads !== $known) {
					throw new RuntimeException('The ' . $step . ' step reads ' . $key . ' other than as ($' . $part[1] . "['" . $key . "'] ?? '...') === '...': teach workspace_flows_parity_read_step_option_values() to read its values.");
				}

				preg_match_all("/'" . $key . "'\s*=>\s*'([a-z_]+)'/", $part[0], $written);
				$values[$key] = array_merge($values[$key], $written[1]);
			}

			foreach (array_unique($values[$key]) as $value) {
				$out[] = $step . '.' . $key . '=' . $value;
			}
		}
	}

	return $out;
}

// What a change step can do: kind.verb, the change catalogue's kinds but the
// ones ws_scheduled_change_skipped() names, and of their actions update and
// delete (read from source, as the change catalogue is).
function workspace_flows_parity_read_change_kinds()
{
	$check = workspace_flows_parity_cases(workspace_flows_parity_source('ws_scheduled_action_input'))['change'];

	// A kind refused by name, in the check or in the run, or a verb other
	// than update looked at by name, would not be seen here: the reader has
	// to learn it first.
	foreach (array(array($check, 'record_type'), array(workspace_flows_parity_source('ws_scheduled_change'), 'type')) as $part) {
		if (preg_match('/\$' . $part[1] . "\s*[!=]==?\s*'|'[a-z_]*'\s*[!=]==?\s*\\$" . $part[1] . '\b|in_array\(\s*\$' . $part[1] . '\s*,\s*(?:array\(|\[)/', $part[0], $match)) {
			throw new RuntimeException('A change step refuses a kind by name (' . $match[0] . '): teach workspace_flows_parity_read_change_kinds() to leave it out.');
		}
	}

	preg_match_all("/\\\$verb\s*[!=]==?\s*'([a-z_]+)'/", $check, $verbs_named);

	if ($verbs_named[0] !== array("\$verb === 'update'")) {
		throw new RuntimeException('The change step looks at its verbs by name in a new way (' . implode(', ', $verbs_named[0]) . '): teach workspace_flows_parity_read_change_kinds() to read it.');
	}

	$verbs = workspace_flows_parity_list_after($check, "array_intersect(\$types[\$record_type]['actions'], ");
	$out = array();

	foreach (pg_test_ws_change_types() as $type => $info) {
		if (in_array($type, ws_scheduled_change_skipped(), true)) {
			continue;
		}

		foreach (array_intersect($info['actions'], $verbs) as $verb) {
			$out[] = $type . '.' . $verb;
		}
	}

	return $out;
}

function workspace_flows_parity_read_digest_filters()
{
	return array_keys(ws_scheduled_digest_filters());
}

function workspace_flows_parity_read_webhook_formats()
{
	return array_keys(ws_scheduled_webhook_formats());
}

// The body a webhook step sends, per format: format.key, nested keys with a
// dot; the generic body is the switch's default.
function workspace_flows_parity_read_webhook_body()
{
	$out = array();
	$flatten = function ($prefix, $array) use (&$flatten, &$out) {
		foreach ($array as $key => $value) {
			if (is_array($value)) {
				$flatten($prefix . $key . '.', $value);
			} else {
				$out[] = $prefix . $key;
			}
		}
	};

	foreach (workspace_flows_parity_cases(workspace_flows_parity_source('ws_scheduled_webhook')) as $format => $code) {
		$flatten((($format === 'default') ? 'generic' : $format) . '.', pg_test_source_array_in($code, '$payload'));
	}

	return $out;
}

function workspace_flows_parity_read_outcomes()
{
	return array_keys(ws_scheduled_outcomes());
}

// The limits scheduled.php defines: NAME=value.
function workspace_flows_parity_read_limits()
{
	preg_match_all("/^define\('(WS_SCHEDULED_[A-Z_]+)',\s*([0-9]+)\);/m", workspace_flows_parity_file('includes/workspace/scheduled.php'), $matches, PREG_SET_ORDER);

	$out = array();

	foreach ($matches as $match) {
		$out[] = $match[1] . '=' . $match[2];
	}

	return $out;
}

// Where the chain limits hold, and where a run hands its depth to the events
// it causes: limit in function.
function workspace_flows_parity_read_limit_uses()
{
	$out = array();

	foreach (array('WS_SCHEDULED_CHAIN_DEPTH', 'WS_SCHEDULED_CHAIN_HOURLY', 'WS_SCHEDULED_MAX_FOLLOW', 'ws_event_depth') as $word) {
		foreach (workspace_flows_parity_functions_naming($word) as $function) {
			$out[] = $word . ' in ' . $function;
		}
	}

	return $out;
}

// The placeholders: those the form lists, those ws_scheduled_fill() and
// ws_events_fill_values() fill. A count is {{count:<key>}}.
function workspace_flows_parity_read_tokens()
{
	$tokens = array_keys(ws_scheduled_placeholders());

	foreach (array('ws_scheduled_fill', 'ws_events_fill_values') as $function) {
		$source = workspace_flows_parity_source($function);
		preg_match_all("/'(\{\{[a-z_]+\}\})'/", $source, $literal);
		$tokens = array_merge($tokens, $literal[1]);

		if (preg_match('/\\\\\{\\\\\{\(([a-z_|]+)\)\\\\\}\\\\\}/', $source, $group)) {
			foreach (explode('|', $group[1]) as $name) {
				$tokens[] = '{{' . $name . '}}';
			}
		}

		if (strpos($source, '\{\{count:') !== false) {
			$tokens[] = '{{count:<key>}}';
		}
	}

	$out = array();

	foreach ($tokens as $token) {
		$out[] = preg_match('/^\{\{count:/', $token) ? '{{count:<key>}}' : $token;
	}

	return array_values(array_unique($out));
}

function workspace_flows_parity_read_states()
{
	return array_keys(ws_scheduled_states());
}

// The statements of the product that delete rows of ws_scheduled_runs or
// drop the table, by where they are: file function.
function workspace_flows_parity_runs_deletes()
{
	$root = realpath(PG_FUNCTIONS_DIR);
	$out = array();

	foreach (workspace_flows_parity_product_files() as $path) {
		$code = (string) file_get_contents($path);

		if (strpos($code, 'ws_scheduled_runs') === false) {
			continue;
		}

		foreach (workspace_flows_parity_functions_at($code, '/\bDELETE\b[^;]*?\bws_scheduled_runs\b|\bTRUNCATE\s+(?:TABLE\s+)?`?ws_scheduled_runs\b|\bDROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?`?ws_scheduled_runs\b/') as $function) {
			$out[] = str_replace('\\', '/', substr($path, strlen($root) + 1)) . ' ' . $function;
		}
	}

	return $out;
}

// How a run tells about itself (ws_scheduled_report()): every function it
// calls but those that only build its words; and that the runs of an action
// are never deleted (only a scheduled message's runs are, with the message).
function workspace_flows_parity_read_news()
{
	$out = array_values(array_diff(workspace_flows_parity_php_calls(workspace_flows_parity_source('ws_scheduled_report')), array('lang', 'implode', 'ws_person_name', 'function_exists')));
	$deletes = array_diff(workspace_flows_parity_runs_deletes(), array(
		'includes/workspace/scheduled_messages.php ws_scheduled_message_delete',
		'includes/workspace/scheduled_messages.php ws_scheduled_message_run',
	));

	if (empty($deletes)) {
		$out[] = 'ws_scheduled_runs kept';
	}

	foreach ($deletes as $where) {
		$out[] = 'ws_scheduled_runs deleted in ' . $where;
	}

	return $out;
}

// How a change step writes a record (ws_scheduled_change()): the source the
// writers name, the kind of message left under the card, its own log line.
function workspace_flows_parity_read_record_writes()
{
	$source = workspace_flows_parity_source('ws_scheduled_change');
	$out = array();

	preg_match_all("/ws_change_source\(array\('kind'\s*=>\s*'([a-z_]+)'/", $source, $kinds);

	foreach ($kinds[1] as $kind) {
		$out[] = 'ws_change_source ' . $kind;
	}

	preg_match_all("/ws_message_send\([^;]*'kind'\s*=>\s*'([a-z_]+)'/", $source, $messages);

	foreach ($messages[1] as $kind) {
		$out[] = 'ws_message_send ' . $kind;
	}

	foreach (array('ws_change_write', 'log_activity') as $call) {
		if (preg_match('/\b' . $call . '\(/', $source)) {
			$out[] = $call;
		}
	}

	return $out;
}

// The buttons of a card (scheduledCard() in workspace.js), by the key of
// their label.
function workspace_flows_parity_read_card_buttons()
{
	$out = array();

	foreach (workspace_flows_parity_js_calls(workspace_flows_parity_js_function('assets/js/workspace.js', 'scheduledCard'), 'button') as $args) {
		preg_match_all("/\bt\('([a-z_]+)'\)/", $args[1] ?? '', $labels);
		$out = array_merge($out, $labels[1]);
	}

	return array_values(array_unique($out));
}

// What a card draws besides its buttons (scheduledCard() in workspace.js):
// the keys of its labels (label key) and the fields of the action it reads
// (item.field).
function workspace_flows_parity_read_card_parts()
{
	$card = workspace_flows_parity_js_function('assets/js/workspace.js', 'scheduledCard');
	$out = array();

	foreach (array_diff(workspace_flows_parity_t_keys($card), workspace_flows_parity_read_card_buttons()) as $key) {
		$out[] = 'label ' . $key;
	}

	foreach (workspace_flows_parity_js_fields($card, 'item') as $field) {
		$out[] = 'item.' . $field;
	}

	return $out;
}

// What the list of scheduled actions draws around the cards (showScheduled()
// in workspace.js): its labels (the state chips, "in #channel / in note",
// the tabs) and the fields of an action it reads.
function workspace_flows_parity_read_list_parts()
{
	$js = workspace_flows_parity_file('assets/js/workspace.js');

	if (!preg_match('/\n(\s*)showScheduled: function \(/', $js, $match, PREG_OFFSET_CAPTURE)) {
		throw new RuntimeException('workspace.js has no showScheduled: function ().');
	}

	$start = $match[0][1];
	$open = strpos($js, '{', strpos($js, ')', $start));
	$list = substr($js, $start, workspace_flows_parity_js_close($js, $open) - $start + 1);
	$out = array();

	foreach (workspace_flows_parity_t_keys($list) as $key) {
		$out[] = 'label ' . $key;
	}

	foreach (workspace_flows_parity_js_fields($list, 'item') as $field) {
		$out[] = 'item.' . $field;
	}

	return $out;
}

// Where a card is drawn: each scheduledCard() call of workspace.js, by what
// it draws and the function it is drawn in.
function workspace_flows_parity_read_card_places()
{
	$js = workspace_flows_parity_file('assets/js/workspace.js');
	$out = array();

	foreach (workspace_flows_parity_js_calls($js, 'scheduledCard') as $offset => $args) {
		$out[] = 'scheduledCard(' . $args[0] . ') in ' . workspace_flows_parity_js_enclosing($js, $offset) . '()';
	}

	return $out;
}

// The panel routes of scheduled actions and messages: the branches of the
// Workspace's action switch (includes/workspace/actions.php) and the api()
// calls of workspace.js that ask them.
function workspace_flows_parity_read_routes()
{
	preg_match_all("/^\s*case '(ws_scheduled_[a-z_]+)':/m", workspace_flows_parity_file('includes/workspace/actions.php'), $cases);
	preg_match_all("/\bapi\('(ws_scheduled_[a-z_]+)'/", workspace_flows_parity_file('assets/js/workspace.js'), $calls);

	$out = array();

	foreach (array_unique($cases[1]) as $route) {
		$out[] = 'route ' . $route;
	}

	foreach (array_unique($calls[1]) as $route) {
		$out[] = 'api ' . $route;
	}

	return $out;
}

// The ready-made actions the form offers (saTemplates() in workspace.js and
// the events' in workspace_events.js), by key.
function workspace_flows_parity_read_templates()
{
	preg_match_all("/\bkey: '([a-z_]+)'/", workspace_flows_parity_js_function('assets/js/workspace.js', 'saTemplates') . workspace_flows_parity_js_function('assets/js/workspace_events.js', 'templates'), $keys);

	return array_values(array_unique($keys[1]));
}

// The places that open the form (scheduledForm() in workspace.js), by what
// they open it with: its first argument, and the preset's key when it has
// one.
function workspace_flows_parity_read_entry_points()
{
	$out = array();

	foreach (workspace_flows_parity_js_calls(workspace_flows_parity_file('assets/js/workspace.js'), 'scheduledForm') as $args) {
		$key = $args[0];

		if (preg_match("/^\{ key: '([a-z_]+)'/", $args[3] ?? '', $preset)) {
			$key .= ' (' . $preset[1] . ')';
		}

		$out[] = $key;
	}

	return $out;
}

// Pinegrap AI drafting a scheduled action: its tool, as named
// (ws_ai_tool_names()), offered to the model and handled (ai.php), and the
// functions that treat a draft of the kind 'scheduled' on its own (the API's
// list of drafts, applying a draft).
function workspace_flows_parity_read_ai_drafts()
{
	$out = array();

	foreach (ws_ai_tool_names() as $tool) {
		if (strpos($tool, 'scheduled') !== false) {
			$out[] = 'tool ' . $tool;
		}
	}

	$ai = workspace_flows_parity_file('includes/workspace/ai.php');
	preg_match_all("/\\\$tool\('([a-z_]*scheduled[a-z_]*)'/", $ai, $offered);
	preg_match_all("/^\s*case '([a-z_]*scheduled[a-z_]*)':/m", $ai, $handled);

	foreach (array_unique($offered[1]) as $tool) {
		$out[] = 'offered ' . $tool;
	}

	foreach (array_unique($handled[1]) as $tool) {
		$out[] = 'handled ' . $tool;
	}

	foreach (array('includes/workspace/api_resources.php', 'includes/workspace/changes.php', 'includes/workspace/ai.php', 'includes/workspace/scheduled_draft.php') as $file) {
		foreach (workspace_flows_parity_functions_at(workspace_flows_parity_file($file), "/\\\$type\s*===\s*'scheduled'/") as $function) {
			$out[] = 'draft kind scheduled in ' . $function;
		}
	}

	return $out;
}

// The entries of ws_nav_groups() with a key (the rail of the other Workspace
// screens, which ws_screen_rail() draws with data-ws-nav from the key), by
// their label: each array literal of its source, wherever it is written.
function workspace_flows_parity_nav_entries($key)
{
	$tokens = pg_test_source_tokens(workspace_flows_parity_source('ws_nav_groups'));
	$out = array();

	foreach ($tokens as $i => $token) {
		if (!is_array($token) || ($token[0] !== T_ARRAY) || !pg_test_source_array_starts($tokens, $i)) {
			continue;
		}

		$at = $i;
		$entry = pg_test_source_value($tokens, $at);

		if (is_array($entry) && (($entry['key'] ?? null) === $key)) {
			$label = $entry['label'] ?? null;
			$out[] = (($label instanceof PgTestSourceExpression) && preg_match("/^lang\('([^']+)'\)$/", $label->code, $text)) ? $text[1] : var_export($label, true);
		}
	}

	return $out;
}

// The ways a person is brought to scheduled actions: inbox lines
// (ws_inbox_describe()), the ?view=scheduled address, the rail's entries,
// the sidebar's link, the tour's step.
function workspace_flows_parity_read_links_in()
{
	$out = array();

	foreach (array_keys(workspace_flows_parity_cases(workspace_flows_parity_source('ws_inbox_describe'))) as $kind) {
		if (strpos($kind, 'scheduled') !== false) {
			$out[] = 'inbox ' . $kind;
		}
	}

	$js = workspace_flows_parity_file('assets/js/workspace.js');

	if (preg_match("/query\.view === 'scheduled'/", $js)) {
		$out[] = '?view=scheduled';
	}

	foreach (workspace_flows_parity_nav_entries('scheduled') as $label) {
		$out[] = 'rail data-ws-nav="scheduled" ' . $label;
	}

	// The workspace.php sidebar: the link() calls that open the scheduled
	// screen, with the data-ws-nav their icon gives them (navKeys), by the
	// keys of their label.
	if (!preg_match('/var navKeys = \{([^}]*)\}/', $js, $map)) {
		throw new RuntimeException('workspace.js no longer has var navKeys = {...}: teach workspace_flows_parity_read_links_in() where data-ws-nav comes from.');
	}

	preg_match_all("/'([^']+)'\s*:\s*'([^']+)'/", $map[1], $pairs);
	$nav = array_combine($pairs[1], $pairs[2]);

	foreach (workspace_flows_parity_js_calls($js, 'link') as $args) {
		if (strpos($args[3] ?? '', 'showScheduled(') === false) {
			continue;
		}

		foreach (workspace_flows_parity_t_keys($args[1]) as $label) {
			$out[] = 'sidebar data-ws-nav="' . ($nav[trim($args[2], "'")] ?? '') . '" ' . $label;
		}
	}

	preg_match_all("/'art'\s*=>\s*'([a-z_]*scheduled[a-z_]*)',\s*'target'\s*=>\s*array\('([^']+)'/", workspace_flows_parity_source('ws_tour_steps'), $steps, PREG_SET_ORDER);

	foreach ($steps as $step) {
		$out[] = 'tour ' . $step[1] . ' ' . $step[2];
	}

	return $out;
}

// The inbox kinds of scheduled actions the external API names (the inbox
// route's description in includes/workspace/api.php).
function workspace_flows_parity_read_inbox_kinds()
{
	if (!preg_match('/kind is one of ([a-z_, ]+(?: and [a-z_]+)?)/', workspace_flows_parity_file('includes/workspace/api.php'), $match)) {
		throw new RuntimeException('The inbox route of includes/workspace/api.php no longer says "kind is one of ...".');
	}

	return array_values(array_filter(preg_split('/,\s*|\s+and\s+/', trim($match[1])), function ($kind) {
		return strpos($kind, 'scheduled') !== false;
	}));
}

// ─── The parity table ───────────────────────────────────────────────────

// Keys with a common prefix: workspace_flows_parity_prefixed('task', 'status
// priority') is task.status and task.priority; a prefix ending in "=" is
// joined without the dot.
function workspace_flows_parity_prefixed($prefix, $words)
{
	$out = array();
	$joint = (substr($prefix, -1) === '=') ? '' : '.';

	foreach (preg_split('/\s+/', trim($words)) as $word) {
		$out[] = $prefix . $joint . $word;
	}

	return $out;
}

/**
 * The groups of keys: the row of the parity plan each belongs to, the slices
 * that row names (a key may also stay in the form), the function that reads
 * the keys from the code, and where it reads them.
 *
 * @return array group => [row, slices, reader, where]
 */
function workspace_flows_parity_groups()
{
	return array(
		'action'              => array('Action', array(1, 2, 3), 'workspace_flows_parity_read_action', 'the $data and follow-up keys of ws_scheduled_input() and ws_scheduled_save(), the row it gives back, the columns of ws_scheduled_decode()'),
		'when'                => array('When', array(3), 'workspace_flows_parity_read_when', 'the "when" kinds of ws_scheduled_input()'),
		'repeats'             => array('When', array(3), 'workspace_flows_parity_read_repeats', 'ws_scheduled_repeats()'),
		'rule_options'        => array('When, Conditions', array(3), 'workspace_flows_parity_read_rule_options', 'the $rule keys of each branch of ws_scheduled_rule_input()'),
		'events'              => array('When', array(3), 'workspace_flows_parity_read_events', 'ws_events_catalog() as written'),
		'event_filters'       => array('Event filters', array(3), 'workspace_flows_parity_read_event_filters', 'the filter and channel of each event of ws_events_catalog()'),
		'event_filter_values' => array('Event filters', array(3), 'workspace_flows_parity_read_event_filter_values', 'the lists of $filter_values in ws_scheduled_rule_input()'),
		'condition_kinds'     => array('Conditions', array(3), 'workspace_flows_parity_read_condition_kinds', 'the branches of ws_scheduled_rule_input() that are not "when" kinds'),
		'record_types'        => array('Conditions', array(3), 'workspace_flows_parity_read_record_types', 'ws_scheduled_record_types() over the change catalogue as written'),
		'record_fields'       => array('Conditions', array(3), 'workspace_flows_parity_read_record_fields', 'the fields with a column of ws_scheduled_record_types()'),
		'operators'           => array('Conditions', array(3), 'workspace_flows_parity_read_operators', 'ws_scheduled_operators()'),
		'metrics'             => array('Conditions', array(3), 'workspace_flows_parity_read_metrics', 'ws_scheduled_metrics() as written'),
		'count_operators'     => array('Conditions', array(3), 'workspace_flows_parity_read_count_operators', 'ws_scheduled_count_operators()'),
		'steps'               => array('Steps', array(3), 'workspace_flows_parity_read_steps', 'the branches of ws_scheduled_do()'),
		'step_options'        => array('Steps', array(3), 'workspace_flows_parity_read_step_options', 'the $action keys of ws_scheduled_action_input(), ws_scheduled_do() and the functions they hand the action to'),
		'step_option_values'  => array('Steps', array(3), 'workspace_flows_parity_read_step_option_values', 'the values a step option is compared with in the code of its step'),
		'change_kinds'        => array('Steps', array(3), 'workspace_flows_parity_read_change_kinds', 'the change catalogue as written, minus ws_scheduled_change_skipped(), update and delete'),
		'digest_filters'      => array('Steps', array(3), 'workspace_flows_parity_read_digest_filters', 'ws_scheduled_digest_filters()'),
		'webhook_formats'     => array('Webhook body', array(3, 5), 'workspace_flows_parity_read_webhook_formats', 'ws_scheduled_webhook_formats()'),
		'webhook_body'        => array('Webhook body', array(3, 5), 'workspace_flows_parity_read_webhook_body', 'the $payload of each format in ws_scheduled_webhook()'),
		'outcomes'            => array('Follow-ups', array(3), 'workspace_flows_parity_read_outcomes', 'ws_scheduled_outcomes()'),
		'limits'              => array('Chains', array(3), 'workspace_flows_parity_read_limits', 'the WS_SCHEDULED_* constants of scheduled.php'),
		'limit_uses'          => array('Chains', array(3), 'workspace_flows_parity_read_limit_uses', 'the Workspace functions that name a chain limit or ws_event_depth()'),
		'tokens'              => array('Tokens', array(3), 'workspace_flows_parity_read_tokens', 'ws_scheduled_placeholders(), ws_scheduled_fill(), ws_events_fill_values()'),
		'states'              => array('States', array(5), 'workspace_flows_parity_read_states', 'ws_scheduled_states()'),
		'news'                => array('News', array(5), 'workspace_flows_parity_read_news', 'what ws_scheduled_report() calls; the deletes of ws_scheduled_runs in the product'),
		'record_writes'       => array('Record writes', array(3), 'workspace_flows_parity_read_record_writes', 'ws_scheduled_change()'),
		'card_buttons'        => array('Screen', array(1), 'workspace_flows_parity_read_card_buttons', 'the button() labels of scheduledCard() in workspace.js'),
		'card_parts'          => array('Screen', array(1), 'workspace_flows_parity_read_card_parts', 'the other labels and the item fields of scheduledCard() in workspace.js'),
		'list_parts'          => array('Screen', array(1), 'workspace_flows_parity_read_list_parts', 'the labels and the item fields of showScheduled() in workspace.js'),
		'card_places'         => array('Screen', array(1), 'workspace_flows_parity_read_card_places', 'the scheduledCard() calls of workspace.js'),
		'routes'              => array('Screen', array(1), 'workspace_flows_parity_read_routes', 'the ws_scheduled_* branches of includes/workspace/actions.php and the api() calls of workspace.js'),
		'templates'           => array('Screen', array(1), 'workspace_flows_parity_read_templates', 'saTemplates() in workspace.js and templates() in workspace_events.js'),
		'entry_points'        => array('Entry points', array(1), 'workspace_flows_parity_read_entry_points', 'the scheduledForm() calls of workspace.js'),
		'ai_drafts'           => array('Entry points', array(1, 4), 'workspace_flows_parity_read_ai_drafts', 'Pinegrap AI\'s tool in ai.php and the functions that treat a draft of the kind scheduled'),
		'links_in'            => array('Links in', array(1), 'workspace_flows_parity_read_links_in', 'ws_inbox_describe(), ws_nav_groups(), workspace.js, ws_tour_steps()'),
		'inbox_kinds'         => array('API', array(5), 'workspace_flows_parity_read_inbox_kinds', 'the inbox route of includes/workspace/api.php'),
	);
}

/**
 * The parity table: per group, lines of [keys, slice, what keeps it]. The
 * slice is the merge slice that keeps the keys (1 one screen, 2 show on
 * canvas, 3 parity boxes, 4 new rules, 5 convert); what keeps them is the
 * box, or the part of the screen, that does. 'form' instead of a slice keeps
 * a key in the scheduled action's form, and its text says why.
 *
 * "The anchor" is the hidden ws_scheduled_actions row of a flow's trigger
 * box (plans/workspace-flows-engine.md); "steps.php" the shared steps the
 * engine plan proposes, which ws_scheduled_do() delegates to.
 *
 * @return array group => array of [keys, slice, keeper]
 */
function workspace_flows_parity_table()
{
	return array(
		'action' => array(
			array(array('data.name', 'row.name'), 1, 'Scheduled actions section in Flows: the name on the card and in the list'),
			array(array('data.id'), 1, 'Edit on the card saves the same action'),
			array(array('data.channel_id', 'data.note_id'), 1, 'where it lives: its card under a message in a channel, or in a note\'s strip'),
			array(array('data.rules', 'row.rules', 'column.rules'), 3, 'the trigger box and the condition boxes on the anchor'),
			array(array('row.next_run_at'), 3, 'schedule trigger box: its next run (ws_scheduled_next_run())'),
			array(array('data.action', 'row.action', 'column.action'), 3, 'the step box'),
			array(array('data.follow', 'row.follow', 'column.follow', 'follow.on', 'follow.do'), 3, 'outcome arrows: the outcome and the step that follows it'),
			array(array('column.then_action'), 2, 'the one follow-up of a 5.81 row, which ws_scheduled_decode() reads as a follow-up on done: the graph and convert (slice 5) read rows through it'),
			array(array('data.then_action'), 'form', 'the one follow-up an old form sends, which the check takes as a follow-up on done'),
		),
		'when' => array(
			array(array('at'), 3, 'schedule trigger box on the anchor'),
			array(array('trigger'), 3, 'by hand or chain only trigger box on the anchor (no time of its own)'),
			array(array('join'), 3, 'join trigger box on the anchor: a start per newcomer, unarchived public or private channels (ws_scheduled_greets())'),
			array(array('event'), 3, 'event trigger box on the anchor'),
		),
		'repeats' => array(
			array(array('none', 'minutes', 'hourly', 'daily', 'weekly', 'monthly', 'yearly'), 3, 'schedule trigger box: how it repeats (ws_scheduled_next_run())'),
			array(array('workdays'), 3, 'schedule trigger box: working days, through ws_day_off()'),
		),
		'rule_options' => array(
			array(workspace_flows_parity_prefixed('at', 'date time repeat every days until'), 3, 'schedule trigger box option'),
			array(array('join.channel_id'), 3, 'join trigger box: one channel, or any public one (0)'),
			array(workspace_flows_parity_prefixed('event', 'event page_id status channel_id'), 3, 'event trigger box option'),
			array(array('weekdays.days'), 3, 'condition box: days of the week'),
			array(workspace_flows_parity_prefixed('hours', 'from to'), 3, 'condition box: hours, past midnight too (ws_scheduled_hours_hold())'),
			array(workspace_flows_parity_prefixed('metric', 'metric op value param'), 3, 'condition box: a count, its channel or threshold, since the last run'),
			array(workspace_flows_parity_prefixed('record', 'record id field op value'), 3, 'condition box: a field of one record'),
		),
		'events' => array(
			array(array('order.created', 'order.status_changed', 'order.shipped', 'order.delivered', 'order.cancelled', 'order.return.requested', 'stock.low'), 3, 'event trigger box: shop events (with the shop only)'),
			array(array('customer.created', 'customer.updated', 'form.submitted'), 3, 'event trigger box: site events'),
			array(array('workspace.task.completed', 'workspace.task.status_changed', 'workspace.task.overdue', 'workspace.approval.decided', 'workspace.guest.message'), 3, 'event trigger box: Workspace events, each checked against what the creator reads (ws_events_creator_sees())'),
		),
		'event_filters' => array(
			array(array('page'), 3, 'event trigger box: which form'),
			array(array('status'), 3, 'event trigger box: the status an order moves to'),
			array(array('task_status'), 3, 'event trigger box: the status a task moves to'),
			array(array('outcome'), 3, 'event trigger box: how an approval request ended'),
			array(array('channel'), 3, 'event trigger box: a Workspace event in one channel'),
		),
		'event_filter_values' => array(
			array(workspace_flows_parity_prefixed('status=', 'incomplete complete exported cancelled'), 3, 'event trigger box: order status (ws_events_order_statuses())'),
			array(workspace_flows_parity_prefixed('task_status=', 'todo doing waiting done cancelled'), 3, 'event trigger box: task status (ws_task_statuses())'),
			array(workspace_flows_parity_prefixed('outcome=', 'approved rejected expired withdrawn'), 3, 'event trigger box: approval outcome (ws_events_approval_outcomes())'),
		),
		'condition_kinds' => array(
			array(array('workday'), 3, 'condition box: a working day (ws_day_off()); no is skipped, the reason kept'),
			array(array('weekdays'), 3, 'condition box: days of the week; no is skipped, the reason kept'),
			array(array('hours'), 3, 'condition box: hours of the day; no is skipped, the reason kept'),
			array(array('metric'), 3, 'condition box: a count; no is skipped, the reason kept'),
			array(array('record'), 3, 'condition box: a record\'s field; no is skipped, the reason kept'),
		),
		'record_types' => array(
			array(array('product', 'stock', 'order', 'contact', 'erp_account', 'product_group', 'user', 'page', 'file', 'offer', 'form', 'calendar_event'), 3, 'condition box: a kind of record of the change catalogue'),
			array(array('task'), 3, 'condition box: a task (status, priority, due date)'),
		),
		'record_fields' => array(
			array(workspace_flows_parity_prefixed('product', 'name title short_description full_description details meta_description meta_keywords keywords brand gtin mpn google_product_category price enabled taxable tax_rate vat_exemption_code shippable free_shipping extra_shipping_cost weight length width height track_stock backorder out_of_stock_message minimum_quantity maximum_quantity reward_points order_receipt_message custom_field_1 custom_field_2 custom_field_3 custom_field_4 notes'), 3, 'condition box: a field of a product'),
			array(array('stock.quantity'), 3, 'condition box: a product\'s stock'),
			array(workspace_flows_parity_prefixed('order', 'status notes po_number custom_field_1 custom_field_2 tracking_numbers'), 3, 'condition box: a field of an order'),
			array(workspace_flows_parity_prefixed('contact', 'salutation first_name last_name suffix nickname company title department office_location email opt_in phone business_phone business_fax website address_1 address_2 city state zip country home_address_1 home_address_2 home_city home_state home_zip home_country home_phone home_fax lead_source tax_number tax_office description member_id expiration_date'), 3, 'condition box: a field of a contact'),
			array(workspace_flows_parity_prefixed('erp_account', 'title email phone address district city state postcode tax_number tax_office payment_days status notes country_code currency credit_limit invoice_email invoice_mail overdue_notify_days overdue_notify_customer'), 3, 'condition box: a field of an ERP account'),
			array(workspace_flows_parity_prefixed('product_group', 'name title short_description full_description meta_description meta_keywords keywords enabled'), 3, 'condition box: a field of a product group'),
			array(workspace_flows_parity_prefixed('user', 'role email'), 3, 'condition box: a field of a user'),
			array(workspace_flows_parity_prefixed('page', 'title meta_description search search_keywords sitemap noindex meta_keywords comments comments_open comments_publish comments_login'), 3, 'condition box: a field of a page'),
			array(workspace_flows_parity_prefixed('file', 'description folder_id content'), 3, 'condition box: a field of a file'),
			array(workspace_flows_parity_prefixed('offer', 'status description start_date end_date'), 3, 'condition box: a field of an offer'),
			array(array('form.complete'), 3, 'condition box: a form submission (its answers are a set, not a field)'),
			array(workspace_flows_parity_prefixed('calendar_event', 'name short_description location start_time end_time all_day published'), 3, 'condition box: a field of a calendar event'),
			array(workspace_flows_parity_prefixed('task', 'status priority due_date'), 3, 'condition box: a field of a task'),
		),
		'operators' => array(
			array(array('eq', 'neq', 'empty', 'not_empty', 'gt', 'gte', 'lt', 'lte', 'contains'), 3, 'condition box: how a field compares'),
			array(array('within', 'overdue'), 3, 'condition box: on dates only'),
		),
		'metrics' => array(
			array(array('tasks_open', 'tasks_overdue', 'tasks_due_today', 'tasks_done_today', 'time_logged_week'), 3, 'condition box and report box: Workspace counts, one channel or all'),
			array(array('forms_new', 'comments_pending', 'visitors_yesterday', 'views_yesterday'), 3, 'condition box and report box: site counts, new ones since the last run'),
			array(array('orders_new', 'orders_today', 'sales_today', 'stock_low'), 3, 'condition box and report box: shop counts (with the shop only), stock against a threshold'),
			array(array('invoices_overdue', 'invoices_overdue_amount'), 3, 'condition box and report box: ERP counts (with the ERP only)'),
		),
		'count_operators' => array(
			array(array('gt', 'gte', 'lt', 'lte', 'eq', 'neq'), 3, 'condition box: how a count compares'),
		),
		'steps' => array(
			array(array('post'), 3, 'post box (steps.php)'),
			array(array('email'), 3, 'email box (steps.php)'),
			array(array('notify'), 3, 'notify box (steps.php)'),
			array(array('task'), 3, 'task box without waiting (steps.php); the waiting task box is the flow\'s own'),
			array(array('task_digest'), 3, 'task e-mail box (steps.php)'),
			array(array('webhook'), 3, 'webhook box (steps.php)'),
			array(array('web_check'), 3, 'web check box (steps.php)'),
			array(array('report'), 3, 'report box (steps.php)'),
			array(array('trigger'), 3, 'start box: another action or a flow (steps.php)'),
			array(array('change'), 3, 'change box (steps.php)'),
			array(array('approval'), 3, 'approval box (F5)'),
			array(array('checkin'), 3, 'check-in box (steps.php)'),
		),
		'step_options' => array(
			array(array('post.channel_id'), 3, 'post box: a channel, or the channel joined (-1)'),
			array(array('post.body'), 3, 'post box: the message'),
			array(workspace_flows_parity_prefixed('email', 'to subject body'), 3, 'email box: addresses, subject, text'),
			array(array('email.page_id'), 3, 'email box: a site page as HTML (get_page_content()), its title the subject when empty'),
			array(workspace_flows_parity_prefixed('notify', 'user_ids text'), 3, 'notify box: people, text'),
			array(workspace_flows_parity_prefixed('task', 'title description priority channel_id assignees due_in'), 3, 'task box: title, text, priority, channel, people, due in days'),
			array(workspace_flows_parity_prefixed('task_digest', 'mode channel_id only user_ids subject intro'), 3, 'task e-mail box: mine or a channel\'s, which tasks, people (default the assignees or members), subject, intro'),
			array(workspace_flows_parity_prefixed('task_digest', 'missed ai'), 3, 'task e-mail box: what was missed, with an AI recap only in a background run (WS_RECAP_MAIL_AI_BUDGET)'),
			array(workspace_flows_parity_prefixed('webhook', 'url format text'), 3, 'webhook box: address, format, text'),
			array(workspace_flows_parity_prefixed('web_check', 'url mode redirect contains max_ms'), 3, 'web check box: status (redirect, contains, slowest answer)'),
			array(array('web_check.days'), 3, 'web check box: certificate days left (ssl)'),
			array(workspace_flows_parity_prefixed('report', 'title metrics channel_id to'), 3, 'report box: title, counts, channel and addresses'),
			array(array('trigger.target_id'), 3, 'start box: the action or flow started; itself again is a retry'),
			array(array('trigger.delay'), 3, 'delay box (F5): minutes before the start'),
			array(workspace_flows_parity_prefixed('change', 'record action id fields'), 3, 'change box: kind, update or delete, record, fields'),
			array(workspace_flows_parity_prefixed('approval', 'channel_id title text approvers rule'), 3, 'approval box: channel, title, text, people asked, rule'),
			array(workspace_flows_parity_prefixed('checkin', 'channel_id question'), 3, 'check-in box: channel, question'),
		),
		'step_option_values' => array(
			array(array('web_check.mode=status'), 3, 'web check box: the answer (redirect, contains, slowest answer)'),
			array(array('web_check.mode=ssl'), 3, 'web check box: the certificate (days left)'),
			array(array('task_digest.mode=mine'), 3, 'task e-mail box: each person their own tasks'),
			array(array('task_digest.mode=channel'), 3, 'task e-mail box: the tasks of a channel'),
		),
		'change_kinds' => array(
			array(array('product.update', 'product.delete', 'stock.update', 'order.update', 'product_group.update', 'product_group.delete'), 3, 'change box: shop records'),
			array(array('contact.update', 'contact.delete', 'erp_account.update', 'form.update'), 3, 'change box: contacts, ERP accounts, form submissions'),
			array(array('user.update', 'page.update', 'page.delete', 'file.update', 'file.delete', 'offer.update', 'offer.delete', 'calendar_event.update', 'calendar_event.delete'), 3, 'change box: users, pages, files, offers, calendar events'),
		),
		'digest_filters' => array(
			array(array('open', 'week', 'overdue', 'stale'), 3, 'task e-mail box: which tasks'),
		),
		'webhook_formats' => array(
			array(array('generic'), 3, 'webhook box: the run in JSON'),
			array(array('text', 'discord'), 3, 'webhook box: a chat message'),
		),
		'webhook_body' => array(
			array(workspace_flows_parity_prefixed('generic', 'event site action.name action.run text result status source at'), 3, 'webhook box: the same body (an outside contract: n8n, Zapier)'),
			array(array('generic.action.id'), 5, 'converted anchor keeps its id, so action.id does not change'),
			array(array('text.text', 'discord.content'), 3, 'webhook box: the chat message'),
		),
		'outcomes' => array(
			array(array('done'), 3, 'arrow on done'),
			array(array('failed', 'skipped', 'always'), 3, 'arrows on failed, skipped and always'),
		),
		'limits' => array(
			array(array('WS_SCHEDULED_CHAIN_DEPTH=8'), 3, 'depth through flow runs and their events: at most 8 follow one another'),
			array(array('WS_SCHEDULED_CHAIN_HOURLY=60'), 3, 'anchor: 60 starts an hour (chain, join, event)'),
			array(array('WS_SCHEDULED_MAX_FOLLOW=5'), 3, 'at most 5 outcome arrows, done in order; one failing fails a good run'),
			array(array('WS_SCHEDULED_MIN_MINUTES=5'), 3, 'schedule trigger box: every 5 minutes at the most'),
			array(array('WS_SCHEDULED_MAX_RECIPIENTS=20'), 3, 'email box and report box: 20 addresses'),
			array(array('WS_SCHEDULED_LOCK=600'), 3, 'anchor: a run holds it 10 minutes (running_at)'),
			array(array('WS_SCHEDULED_BATCH=5'), 3, 'driver: ws_scheduled_run() takes 5 anchors a request'),
		),
		'limit_uses' => array(
			array(array('WS_SCHEDULED_CHAIN_DEPTH in ws_events_start'), 3, 'event trigger box: an event at depth 8 starts nothing'),
			array(array('WS_SCHEDULED_CHAIN_DEPTH in ws_scheduled_start'), 3, 'start box: refused at depth 8'),
			array(array('WS_SCHEDULED_CHAIN_DEPTH in ws_scheduled_run'), 3, 'driver: rounds of starts that wait for nothing in one run'),
			array(array('WS_SCHEDULED_CHAIN_HOURLY in ws_events_start'), 3, 'event trigger box: 60 starts an hour'),
			array(array('WS_SCHEDULED_CHAIN_HOURLY in ws_scheduled_joined'), 3, 'join trigger box: 60 starts an hour'),
			array(array('WS_SCHEDULED_CHAIN_HOURLY in ws_scheduled_start'), 3, 'start box: 60 starts an hour'),
			array(array('WS_SCHEDULED_MAX_FOLLOW in ws_scheduled_input'), 3, 'at most 5 outcome arrows'),
			array(array('WS_SCHEDULED_CHAIN_DEPTH in ws_scheduled_js_config', 'WS_SCHEDULED_MAX_FOLLOW in ws_scheduled_js_config'), 'form', 'what the form tells about the limits; the canvas has its own (WS_FLOW_*)'),
			array(array('ws_event_depth in ws_scheduled_execute'), 3, 'depth through flow runs: a run hands its depth to the events it causes'),
			array(array('ws_event_depth in ws_event'), 3, 'depth through events: a Workspace event carries the depth of the run that caused it'),
		),
		'tokens' => array(
			array(array('{{date}}', '{{time}}', '{{weekday}}', '{{name}}', '{{run}}', '{{count:<key>}}'), 3, 'every box text (ws_scheduled_fill())'),
			array(array('{{result}}', '{{status}}'), 3, 'outcome arrows: what the step did and how it went'),
			array(array('{{source}}', '{{source_result}}'), 3, 'start box: the action that started this one'),
			array(array('{{newcomer}}', '{{newcomer_name}}', '{{channel}}'), 3, 'join trigger box: who joined, where'),
			array(array('{{event}}', '{{record}}', '{{record_title}}', '{{record_link}}', '{{record_status}}', '{{customer}}', '{{actor}}', '{{message}}', '{{amount}}', '{{form_fields}}'), 3, 'event trigger box: what happened, to what (ws_events_fill_values())'),
		),
		'states' => array(
			array(array('active', 'paused', 'done', 'failed', 'cancelled'), 5, 'converted anchor keeps its own state (flows: draft, live, paused, binned)'),
		),
		'news' => array(
			array(array('ws_message_system'), 5, 'converted anchor: the line under the card, on a first run, a new outcome, a single action or by hand'),
			array(array('ws_message_touch'), 5, 'converted anchor: its card drawn again'),
			array(array('ws_notify'), 5, 'converted anchor: the creator\'s scheduled_failed when it newly fails'),
			array(array('log_activity'), 5, 'converted anchor: the Site Log line'),
			array(array('ws_scheduled_runs kept'), 5, 'converted anchor: a flow run writes its outcome to the anchor\'s run, never swept'),
		),
		'record_writes' => array(
			array(array('ws_change_source scheduled'), 3, 'change box: the record says "by the scheduled action “X”"'),
			array(array('ws_message_send decision'), 3, 'change box: the locked decision under the card'),
			array(array('ws_change_write'), 3, 'change box: written through changes.php with the creator\'s rights'),
			array(array('log_activity'), 3, 'change box: the run logs its own line'),
		),
		'card_buttons' => array(
			array(array('edit'), 1, 'Scheduled actions section in Flows: Edit opens the form'),
			array(array('sa_pause', 'sa_resume'), 1, 'Scheduled actions section in Flows: Pause and Resume'),
			array(array('sa_run_now'), 1, 'Scheduled actions section in Flows: Run now'),
			array(array('sa_duplicate'), 1, 'Scheduled actions section in Flows: Duplicate'),
			array(array('sa_history'), 1, 'Scheduled actions section in Flows: History (by hand, started by)'),
			array(array('sa_cancel'), 1, 'Scheduled actions section in Flows: Cancel'),
		),
		'card_parts' => array(
			array(array('item.name', 'item.status', 'item.status_label'), 1, 'card: its name and state'),
			array(array('item.when', 'item.next', 'label sa_next', 'item.repeats', 'item.joins', 'item.on_event', 'item.trigger_only'), 1, 'card: when it runs and next, with the icon of its kind'),
			array(array('item.staff', 'label sa_staff_only'), 1, 'card: someone who is not staff sees its name, time and state only'),
			array(array('item.conditions', 'item.action_text', 'item.follow'), 1, 'card: its conditions, step and follow-ups in words'),
			array(array('item.starters', 'label sa_started_by'), 1, 'card: the actions that start it (ws_scheduled_starters())'),
			array(array('item.last', 'label sa_last'), 1, 'card: its last run'),
			array(array('item.creator', 'item.created', 'label sa_by', 'item.runs', 'label sa_runs'), 1, 'card: who wrote it and when, how many runs'),
			array(array('item.can_edit', 'item.id', 'item.data', 'item.channel', 'label sa_run_now_confirm', 'label sa_copy_of', 'label sa_cancel_confirm'), 1, 'card buttons: who may edit, the questions they ask, the copy Duplicate makes'),
			array(array('label sa_no_history', 'label sa_by_hand'), 1, 'card: History lines (by hand, started by)'),
		),
		'list_parts' => array(
			array(array('label sa_title', 'label sa_new_long', 'label sa_help', 'label sa_empty'), 1, 'Scheduled actions section in Flows: title, New, help, an empty list'),
			array(array('label sa_all', 'label sa_state_active', 'label sa_state_paused', 'label sa_state_done', 'label sa_state_failed', 'label sa_state_cancelled'), 1, 'Scheduled actions section in Flows: the state chips'),
			array(array('label sa_in_channel', 'label sa_in_note', 'item.channel', 'item.note', 'item.message_id'), 1, 'Scheduled actions section in Flows: "in #channel / in note", linked to the card'),
			array(array('label sm_title', 'label sm_mine_help'), 1, '?view=scheduled keeps the Messages tab (scheduled messages are not moved)'),
		),
		'card_places' => array(
			array(array('scheduledCard(item) in load()'), 1, 'Scheduled actions section in Flows: the list'),
			array(array('scheduledCard(message.scheduled) in messageNode()'), 1, 'the card under its message in the channel, kept where written'),
			array(array('scheduledCard(item) in drawScheduled()'), 1, 'the note strip, kept where written'),
		),
		'routes' => array(
			array(array('route ws_scheduled_list', 'api ws_scheduled_list'), 1, 'Scheduled actions section in Flows: the list, by state'),
			array(array('route ws_scheduled_get', 'api ws_scheduled_get', 'route ws_scheduled_status', 'api ws_scheduled_status', 'route ws_scheduled_run_now', 'api ws_scheduled_run_now'), 1, 'card buttons: History, Pause/Resume and Cancel, Run now'),
			array(array('route ws_scheduled_targets', 'api ws_scheduled_targets', 'route ws_scheduled_preview', 'api ws_scheduled_preview', 'route ws_scheduled_draft', 'api ws_scheduled_draft', 'route ws_scheduled_save', 'api ws_scheduled_save'), 1, 'the form: what it can start, the sentence and next runs, the AI sentence, Save'),
			array(array('route ws_scheduled_messages', 'api ws_scheduled_messages', 'route ws_scheduled_message_save', 'api ws_scheduled_message_save', 'route ws_scheduled_message_delete', 'api ws_scheduled_message_delete', 'route ws_scheduled_message_send', 'api ws_scheduled_message_send'), 'form', 'scheduled messages\' routes: scheduled messages are not moved into Flows'),
		),
		'templates' => array(
			array(array('welcome', 'task_mail', 'morning', 'digest', 'uptime', 'ssl', 'overdue', 'forms', 'stock', 'orders', 'invoices', 'monthly', 'chain'), 1, 'scheduledForm() templates, kept in the form (canvas starters come in slice 4)'),
			array(array('order_event', 'form_event', 'stock_event', 'overdue_event', 'approval_event'), 1, 'scheduledForm() event templates, kept in the form (canvas starters come in slice 4)'),
		),
		'entry_points' => array(
			array(array('{}'), 1, 'New on the list, in the Scheduled actions section of Flows'),
			array(array('{ channel_id: self.channel.id }'), 1, 'composer insert menu (not in discussions) still opens the form'),
			array(array('{ note_id: current.id }'), 1, 'note toolbar (note edit right) still opens the form'),
			array(array('{ channel_id: change.draft.channel_id } (sentence)'), 1, '@ai draft Open still opens the form'),
			array(array('context', 'context (sentence)'), 1, 'inside the form: a template picked, an AI sentence filled in'),
			array(array('null'), 1, 'Edit on the card'),
			array(array('item.channel ? { channel_id: item.channel.id } : {}'), 1, 'Duplicate on the card'),
		),
		'ai_drafts' => array(
			array(array('tool propose_scheduled_action', 'offered propose_scheduled_action', 'handled propose_scheduled_action'), 4, 'AI drafts on the canvas (F8); until then Pinegrap AI drafts into the form, opened by @ai draft Open'),
			array(array('draft kind scheduled in ws_changes_map', 'draft kind scheduled in ws_api_change_fields_present', 'draft kind scheduled in ws_api_change_record_present'), 4, 'AI drafts on the canvas (F8): a draft of the kind scheduled waits on its card and in the API\'s list of drafts'),
			array(array('draft kind scheduled in ws_change_apply_check'), 4, 'AI drafts on the canvas (F8): a draft is saved where it is opened, never applied as a change'),
		),
		'links_in' => array(
			array(array('?view=scheduled'), 1, 'staff land on the Scheduled actions section of Flows, their Messages tab kept; others on Scheduled messages'),
			array(array('rail data-ws-nav="scheduled" Scheduled actions'), 1, 'rail of the other Workspace screens (ws_nav_groups()): staff get one Flows entry in its place'),
			array(array('rail data-ws-nav="scheduled" Scheduled messages'), 1, 'rail of the other Workspace screens: others keep Scheduled messages'),
			array(array('sidebar data-ws-nav="scheduled" sa_title'), 1, 'workspace.php sidebar: staff\'s entry gives way to the Flows one'),
			array(array('sidebar data-ws-nav="scheduled" sm_title'), 1, 'workspace.php sidebar: others keep Scheduled messages'),
			array(array('tour scheduled #ws-root [data-ws-nav="scheduled"]'), 1, 'tour step on the workspace.php sidebar entry (optional: it drops silently), moved with it'),
			array(array('inbox scheduled_notice'), 1, 'lands on the card in its channel'),
			array(array('inbox scheduled_failed'), 1, 'lands on the card, or on ?view=scheduled without one'),
			array(array('inbox scheduled_message'), 1, 'lands on ?view=scheduled, the Messages tab'),
		),
		'inbox_kinds' => array(
			array(array('scheduled_notice'), 5, 'kept for a converted anchor, with its text'),
			array(array('scheduled_failed'), 5, 'kept for a converted anchor'),
			array(array('scheduled_message'), 'form', 'a scheduled message\'s line: scheduled messages are not moved into Flows'),
		),
	);
}

// ─── Tests ──────────────────────────────────────────────────────────────

// The table's lines of a group: key => [slice, keeper].
function workspace_flows_parity_expanded($group)
{
	$out = array();

	foreach (workspace_flows_parity_table()[$group] as $line) {
		foreach ($line[0] as $key) {
			$out[$key] = array($line[1], $line[2]);
		}
	}

	return $out;
}

// Every group has its reader and its lines; each line names keys, a slice of
// its row (or 'form') and what keeps them; no key is listed twice.
function test_workspace_flows_parity_table_is_well_formed()
{
	$groups = workspace_flows_parity_groups();
	$table = workspace_flows_parity_table();

	pg_assert_same(array_keys($groups), array_keys($table), 'the table has lines for each group, in the same order');

	foreach ($table as $group => $lines) {
		$row = $groups[$group] ?? array('?', array(), '', '');
		$seen = array();

		pg_assert_true(function_exists($row[2]), $group . ': its reader exists');

		foreach ($lines as $index => $line) {
			$label = $group . ' line ' . ($index + 1);

			pg_assert_true(is_array($line) && (count($line) === 3), $label . ': [keys, slice, what keeps them]');
			pg_assert_true(is_array($line[0]) && !empty($line[0]), $label . ': keys');
			pg_assert_true(($line[1] === 'form') || in_array($line[1], $row[1], true), $label . ': slice ' . var_export($line[1], true) . ' is one the ' . $row[0] . ' row names (' . implode(', ', $row[1]) . ') or form');
			pg_assert_true(is_string($line[2]) && (trim($line[2]) !== ''), $label . ': what keeps the keys, or why they stay in the form');

			foreach ((array) $line[0] as $key) {
				pg_assert_false(isset($seen[$key]), $group . ': ' . $key . ' is listed once');
				$seen[$key] = true;
			}
		}
	}
}

// Each key the code has is in the table, and each key in the table is still
// in the code.
function test_workspace_flows_parity_every_key_is_mapped()
{
	foreach (workspace_flows_parity_groups() as $group => $row) {
		$keys = call_user_func($row[2]);
		$mapped = array_keys(workspace_flows_parity_expanded($group));

		pg_assert_true(!empty($keys), $group . ': ' . $row[3] . ' gives keys');

		pg_assert_same(array(), array_values(array_diff($keys, $mapped)),
			$row[0] . ' (' . $group . '): keys in ' . $row[3] . ' that workspace_flows_parity_table() does not map; give each the merge slice and box that keep it, or \'form\' and why');

		pg_assert_same(array(), array_values(array_diff($mapped, $keys)),
			$row[0] . ' (' . $group . '): keys of workspace_flows_parity_table() no longer in ' . $row[3] . '; take them out, or move them where the code has them now');
	}
}

// Twelve steps, the same in the form's check and in the run.
function test_workspace_flows_parity_twelve_steps()
{
	$run = workspace_flows_parity_read_steps();
	$input = array_keys(workspace_flows_parity_cases(workspace_flows_parity_source('ws_scheduled_action_input')));

	pg_assert_same(12, count($run), 'ws_scheduled_do() has 12 steps');
	sort($run);
	sort($input);
	pg_assert_same($run, $input, 'ws_scheduled_action_input() checks the steps ws_scheduled_do() runs');
}

// Every placeholder the form lists is filled, and every one filled is listed.
function test_workspace_flows_parity_placeholders_are_filled()
{
	$listed = array();

	foreach (array_keys(ws_scheduled_placeholders()) as $token) {
		$listed[] = preg_match('/^\{\{count:/', $token) ? '{{count:<key>}}' : $token;
	}

	$filled = array();

	foreach (array('ws_scheduled_fill', 'ws_events_fill_values') as $function) {
		$source = workspace_flows_parity_source($function);
		preg_match_all("/'(\{\{[a-z_]+\}\})'/", $source, $literal);
		$filled = array_merge($filled, $literal[1]);

		if (strpos($source, '\{\{count:') !== false) {
			$filled[] = '{{count:<key>}}';
		}
	}

	sort($listed);
	$filled = array_values(array_unique($filled));
	sort($filled);

	pg_assert_same($listed, $filled, 'ws_scheduled_placeholders() lists what ws_scheduled_fill() and ws_events_fill_values() fill');
}

// What the run reads of a rule is what the form's check takes: the
// conditions (ws_scheduled_rules_hold()), the time (ws_scheduled_next_run()),
// the event (ws_events_rule_matches()) and the join (ws_scheduled_joined()).
function test_workspace_flows_parity_rule_reads_are_known()
{
	$input = array();

	foreach (workspace_flows_parity_rule_cases() as $kind => $code) {
		$input[$kind] = workspace_flows_parity_reads($code, 'rule');
	}

	foreach (workspace_flows_parity_cases(workspace_flows_parity_source('ws_scheduled_rules_hold')) as $kind => $code) {
		pg_assert_same(array(), array_values(array_diff(workspace_flows_parity_reads($code, 'rule'), $input[$kind] ?? array())), 'ws_scheduled_rules_hold() reads of a ' . $kind . ' rule only what the form takes');
	}

	foreach (array('ws_scheduled_next_run' => 'at', 'ws_events_rule_matches' => 'event', 'ws_scheduled_joined' => 'join') as $function => $kind) {
		pg_assert_same(array(), array_values(array_diff(workspace_flows_parity_reads(workspace_flows_parity_source($function), 'rule'), array_merge($input[$kind], array('type')))), $function . '() reads of a ' . $kind . ' rule only what the form takes');
	}
}

// The webhook's switch has a body for each format the form offers, and the
// "when" kinds are the same where a rule is read: the form's check, the rule
// that starts an action without a time, the card.
function test_workspace_flows_parity_lists_agree()
{
	$bodies = array();

	foreach (array_keys(workspace_flows_parity_cases(workspace_flows_parity_source('ws_scheduled_webhook'))) as $format) {
		$bodies[] = ($format === 'default') ? 'generic' : $format;
	}

	sort($bodies);
	$formats = workspace_flows_parity_read_webhook_formats();
	sort($formats);
	pg_assert_same($formats, $bodies, 'ws_scheduled_webhook() has a body for each of ws_scheduled_webhook_formats()');

	$when = workspace_flows_parity_read_when();
	pg_assert_same(array_values(array_diff($when, array('at'))), workspace_flows_parity_list_after(workspace_flows_parity_source('ws_scheduled_event_rule'), "in_array((\$rule['type'] ?? ''), "), 'ws_scheduled_event_rule(): the "when" kinds without a time');
	pg_assert_same($when, workspace_flows_parity_list_after(workspace_flows_parity_source('ws_scheduled_present'), "in_array((\$rule['type'] ?? ''), "), 'ws_scheduled_present() leaves the "when" kinds out of the conditions');
}
