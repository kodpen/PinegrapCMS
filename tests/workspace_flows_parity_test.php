<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Scheduled actions move into Flows with nothing lost: every key a scheduled
 * action has (what it is made of and what the form sends for it, its
 * "when", the events, the conditions, the thirteen steps, each option they
 * read and the values one takes from a list, the fields a change writes,
 * the webhook's body, the outcomes, the chain limits and the caps written
 * as numbers, what starts and runs it, the placeholders, the states, the
 * news it gives, how it writes a record, its card - the buttons, the rest it
 * draws, where it is drawn -, the list, the panel routes, the ways into its
 * form, Pinegrap AI's drafts, the links to it, the inbox kinds the API
 * shows) is listed in the parity table below with the slice of the move and
 * the box that keeps it, or 'form' with the reason it stays in the form.
 * The keys are read from the code - by calling the
 * functions that run without a database, and from their source where a
 * table probe or a constant hides some (tests/helpers/source_reader.php) -
 * so a key added to the code and not to the table fails here, and so does a
 * key the table lists that the code no longer has. A reader that models
 * source it cannot call throws when that source takes a shape it does not
 * read (an entry written another way, a new condition), rather than miss
 * what it adds.
 *
 * The slices number the stages of the move: 1 one screen, 2 show on canvas,
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

// The keys a piece of code reads from an array variable: $variable['key'] or
// $variable["key"]. A key worked out by code ($variable[$name]) throws: the
// reader cannot tell which key that is.
function workspace_flows_parity_reads($source, $variable)
{
	$name = preg_quote($variable, '/');

	if (preg_match('/\$' . $name . '\[(?!\s*(?:\'[A-Za-z0-9_]+\'|"[A-Za-z0-9_]+")\s*\])(?!\s*\])[^\]]*\]?/', $source, $code)) {
		throw new RuntimeException('$' . $variable . ' is read with a key worked out by code (' . $code[0] . '): teach workspace_flows_parity_reads() which keys that reads.');
	}

	preg_match_all('/\$' . $name . '\[\s*(?:\'([A-Za-z0-9_]+)\'|"([A-Za-z0-9_]+)")\s*\]/', $source, $matches, PREG_SET_ORDER);

	$out = array();

	foreach ($matches as $match) {
		$out[] = ($match[1] !== '') ? $match[1] : $match[2];
	}

	return array_values(array_unique($out));
}

// A key of an array variable in a pattern, in either kind of quotes:
// $variable['key'] or $variable["key"]; $key is a pattern itself, and a
// group in it is the pattern's only group.
function workspace_flows_parity_subscript($variable, $key)
{
	return '\$' . preg_quote($variable, '/') . '\[\s*[\'"]' . $key . '[\'"]\s*\]';
}

// The keys in a list, each one a repeat of an earlier one numbered: a, b,
// a #2. A reader whose keys are places (a call, a label) keys them so, so a
// second place written the same way is a key of its own, and a place taken
// away while its twin stays is missed too.
function workspace_flows_parity_numbered(array $keys)
{
	$seen = array();
	$out = array();

	foreach ($keys as $key) {
		$seen[$key] = ($seen[$key] ?? 0) + 1;
		$out[] = ($seen[$key] > 1) ? $key . ' #' . $seen[$key] : $key;
	}

	return $out;
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

// The named JavaScript functions (function name() or name: function ())
// whose bodies hold an offset of some code, the innermost first, as
// "name() in outer()"; '' for none.
function workspace_flows_parity_js_enclosing($code, $offset)
{
	preg_match_all('/(?:\bfunction\s+([A-Za-z_$][\w$]*)\s*\(|(?<![\w$.])([A-Za-z_$][\w$]*)\s*:\s*function\s*\()/', substr($code, 0, $offset), $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

	$names = array();

	foreach (array_reverse($found) as $match) {
		$name = ((($match[2][1] ?? -1) >= 0) && ($match[2][0] !== '')) ? $match[2][0] : $match[1][0];
		$open = strpos($code, '{', strpos($code, ')', $match[0][1]));

		if (workspace_flows_parity_js_close($code, $open) > $offset) {
			$names[] = $name . '()';
		}
	}

	return implode(' in ', $names);
}

// The keys of the labels some JavaScript code writes with t('key').
function workspace_flows_parity_t_keys($code)
{
	return array_values(array_unique(workspace_flows_parity_t_uses($code)));
}

// Each label some JavaScript code writes with t('key'), as often as it
// writes it, in order.
function workspace_flows_parity_t_uses($code)
{
	preg_match_all("/\bt\('([a-z_]+)'[,)]/", $code, $keys);

	return $keys[1];
}

// The fields of a variable some JavaScript code reads, with the fields of
// those: variable.field, variable.field.inner (not other.variable.field; a
// method called on one, such as .map(), is not a field).
function workspace_flows_parity_js_fields($code, $variable)
{
	preg_match_all('/(?<![A-Za-z0-9_$.])' . preg_quote($variable, '/') . '((?:\.[A-Za-z_][A-Za-z0-9_]*)+)(\s*\()?/', $code, $paths, PREG_SET_ORDER);

	$out = array();

	foreach ($paths as $path) {
		$fields = explode('.', substr($path[1], 1));

		if (!empty($path[2])) {
			array_pop($fields);
		}

		if (!empty($fields)) {
			$out[] = implode('.', $fields);
		}
	}

	return array_values(array_unique($out));
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

// Every write some PHP code (its tokens, pg_test_source_tokens()) makes to a
// variable, in order: its subscripts as literal keys (null for a key worked
// out by code), the same as written ('$type', "'task'"), the operator ('='
// or '+=', 'unset', '&' for a reference taken), whether it is a static
// declaration, and where the value written starts.
function workspace_flows_parity_writes(array $tokens, $variable)
{
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
		$reference = ($previous !== null) && (pg_test_source_text($previous) === '&');

		// Its subscripts: a literal key, or null for code.
		$keys = array();
		$written_as = array();
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
			$written_as[] = implode('', array_map('pg_test_source_text', $inner));
			$j++;
			pg_test_source_skip($tokens, $j);
		}

		$operator = isset($tokens[$j]) ? pg_test_source_text($tokens[$j]) : '';

		if (!$unset && !$reference && !in_array($operator, $assignments, true)) {
			continue;
		}

		$at = $j + 1;
		pg_test_source_skip($tokens, $at);

		$out[] = array(
			'keys'       => $keys,
			'written_as' => $written_as,
			'operator'   => $unset ? 'unset' : ($reference ? '&' : $operator),
			'static'     => is_array($previous) && ($previous[0] === T_STATIC),
			'value'      => $at,
		);
	}

	return $out;
}

// The entries a function writes into an array variable as literals: the
// literal it is set to ($var = array(...)), those added to it ($var +=
// array(...)) and the entries set one at a time under a literal key
// ($var['key'] = ..., the value a literal or the code that works it out),
// read as source_reader.php reads literals, however many lines an entry
// takes and in whatever order its keys come. Any other write to the variable
// (a key worked out by code, a nested key, a merge, an unset) throws: the
// reader would miss what it adds or takes away, so it has to learn it first.
function workspace_flows_parity_literal_entries($function, $variable)
{
	return workspace_flows_parity_literal_entries_in(workspace_flows_parity_source($function), $variable, $function . '()');
}

// The same for a piece of PHP code; $label names it in the error.
function workspace_flows_parity_literal_entries_in($code, $variable, $label)
{
	$tokens = pg_test_source_tokens($code);
	$out = array();

	foreach (workspace_flows_parity_writes($tokens, $variable) as $write) {
		$at = $write['value'];
		$keys = $write['keys'];
		$operator = $write['operator'];

		if ($write['static'] && empty($keys) && ($operator === '=')) {
			continue;
		}

		if (empty($keys) && in_array($operator, array('=', '+='), true) && pg_test_source_array_starts($tokens, $at)) {
			$value = pg_test_source_value($tokens, $at);
			$out = ($operator === '=') ? $value : $out + $value;
			continue;
		}

		if ((count($keys) === 1) && is_string($keys[0]) && ($operator === '=')) {
			$out[$keys[0]] = pg_test_source_value($tokens, $at);
			continue;
		}

		throw new RuntimeException($label . ' writes ' . $variable . ' in a way workspace_flows_parity_literal_entries() does not read (' . (($operator === 'unset') ? 'unset' : $variable . str_repeat('[...]', count($keys)) . ' ' . $operator) . '): teach it to read that.');
	}

	return $out;
}

// The ways through the first switch statement of some PHP code: for each
// branch (as workspace_flows_parity_cases() names them), the code before the
// switch, the branch's own code and the code after the switch, one after
// the other, so a reader sees every write a run through that branch makes.
function workspace_flows_parity_switch_paths($source)
{
	$tokens = token_get_all('<?php ' . $source);
	array_shift($tokens);
	$count = count($tokens);
	$text = function ($from, $to) use ($tokens) {
		return implode('', array_map('pg_test_source_text', array_slice($tokens, $from, max(0, $to - $from))));
	};

	for ($start = 0; ($start < $count) && !(is_array($tokens[$start]) && ($tokens[$start][0] === T_SWITCH)); $start++) {
	}

	// The body: from the brace after the condition to the brace that closes it.
	for ($open = $start; ($open < $count) && ($tokens[$open] !== '{'); $open++) {
	}

	$depth = 0;

	for ($close = $open; $close < $count; $close++) {
		$token = $tokens[$close];

		if (($token === '{') || (is_array($token) && in_array($token[0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) {
			$depth++;
		} elseif (($token === '}') && (--$depth === 0)) {
			break;
		}
	}

	if ($close >= $count) {
		throw new RuntimeException('No switch statement that closes in: ' . substr(trim($source), 0, 80));
	}

	$out = array();

	foreach (workspace_flows_parity_cases($text($open + 1, $close)) as $case => $code) {
		$out[$case] = $text(0, $start) . "\n" . $code . "\n" . $text($close + 1, $count);
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

// The arguments of the call of the function named at token $i, each a list
// of its tokens, up to the bracket that closes the call; null when $i is no
// call (a declaration, a method, a name not followed by a bracket).
function workspace_flows_parity_php_args(array $tokens, $i)
{
	$count = count($tokens);
	$before = workspace_flows_parity_token_before($tokens, $i);
	$j = $i + 1;
	pg_test_source_skip($tokens, $j);

	if ((($tokens[$j] ?? null) !== '(') || (($before >= 0) && is_array($tokens[$before]) && in_array($tokens[$before][0], array(T_FUNCTION, T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NEW), true))) {
		return null;
	}

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

	return $args;
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

		$args = workspace_flows_parity_php_args($tokens, $i);

		if ($args === null) {
			continue;
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

// Checks that some PHP code uses the whole of an array variable (not one of
// its keys) only in the two ways the step-option reader follows: as an
// argument of its own to a Workspace function, which
// workspace_flows_parity_handed() follows into (ws_scheduled_notify($viewer,
// $action, $run)), or as a parameter in a Workspace function's own
// signature. Any other use throws - copied to another variable, cast, looped
// over, extract()ed, asked array_key_exists(), taken into a closure, put in
// an array literal -, since the keys read through it would be missed.
function workspace_flows_parity_whole_uses($code, $variable)
{
	$tokens = pg_test_source_tokens($code);
	$count = count($tokens);
	$open = array();

	for ($i = 0; $i < $count; $i++) {
		$token = $tokens[$i];

		if (in_array($token, array('(', '[', '{'), true) || (is_array($token) && in_array($token[0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) {
			$open[] = $i;
			continue;
		}

		if (in_array($token, array(')', ']', '}'), true)) {
			array_pop($open);
			continue;
		}

		if (!is_array($token) || ($token[0] !== T_VARIABLE) || ($token[1] !== '$' . $variable)) {
			continue;
		}

		$next = $i + 1;
		pg_test_source_skip($tokens, $next);

		// One of its keys, which workspace_flows_parity_reads() reads.
		if (($tokens[$next] ?? null) === '[') {
			continue;
		}

		// The innermost bracket around it: the call, or the signature, it is in.
		$bracket = empty($open) ? -1 : end($open);
		$call = (($bracket >= 0) && ($tokens[$bracket] === '(')) ? workspace_flows_parity_token_before($tokens, $bracket) : -1;
		$named = ($call >= 0) && is_array($tokens[$call]) && ($tokens[$call][0] === T_STRING) && (strpos($tokens[$call][1], 'ws_') === 0);
		$keyword = $named ? workspace_flows_parity_token_before($tokens, $call) : -1;
		$declared = ($keyword >= 0) && is_array($tokens[$keyword]) && ($tokens[$keyword][0] === T_FUNCTION);
		$previous = workspace_flows_parity_token_before($tokens, $i);
		$alone = in_array($tokens[$previous] ?? null, array('(', ','), true) && in_array($tokens[$next] ?? null, array(')', ','), true);

		if ($declared || ($named && $alone)) {
			continue;
		}

		$around = implode('', array_map('pg_test_source_text', array_slice($tokens, max(0, $i - 8), 16)));

		throw new RuntimeException('$' . $variable . ' is used whole in a way the step-option reader does not follow (' . trim(preg_replace('/\s+/', ' ', $around)) . '): read its keys as $' . $variable . '[\'key\'], hand the whole of it to a ws_ function, or teach workspace_flows_parity_read_step_options() to read that shape.');
	}
}

// The values of an array variable some PHP code hands on to a Workspace
// function under their own key in an array literal, as ws_approval_input(
// ..., array('rule' => (string) ($action['rule'] ?? 'any'))) does:
// [function, parameter, key, default] ('' for none), the parameter without
// its $.
function workspace_flows_parity_handed_by_name($code, $variable)
{
	$tokens = pg_test_source_tokens($code);
	$out = array();

	foreach ($tokens as $i => $token) {
		if (!is_array($token) || ($token[0] !== T_STRING) || (strpos($token[1], 'ws_') !== 0) || !function_exists($token[1])) {
			continue;
		}

		$args = workspace_flows_parity_php_args($tokens, $i);

		if ($args === null) {
			continue;
		}

		$parameters = (new ReflectionFunction($token[1]))->getParameters();

		foreach ($args as $index => $arg) {
			$arg = array_values(array_filter($arg, function ($part) {
				return !is_array($part) || ($part[0] !== T_WHITESPACE);
			}));
			$arg[] = ')';
			$at = 0;

			if (!isset($parameters[$index]) || !pg_test_source_array_starts($arg, 0)) {
				continue;
			}

			$literal = pg_test_source_value($arg, $at);

			if (!is_array($literal) || ($arg[$at] ?? null) !== ')') {
				continue;
			}

			foreach ($literal as $key => $value) {
				$code_of = ($value instanceof PgTestSourceExpression) ? $value->code : '';

				if (is_string($key) && preg_match('/' . workspace_flows_parity_subscript($variable, preg_quote($key, '/')) . "(?:\s*\?\?\s*'([a-z_]*)')?/", $code_of, $match)) {
					$out[] = array($token[1], $parameters[$index]->getName(), $key, $match[1] ?? '');
				}
			}
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

// The first argument of a call of a function as a call site's key shows it:
// as written, runs of whitespace made one, or empty when it is the first
// parameter's default, written as its constant or its value, so
// ws_scheduled_run(WS_SCHEDULED_BATCH) is the same place as
// ws_scheduled_run(): writing a default out changes nothing the call does.
function workspace_flows_parity_first_argument($function, $written)
{
	$written = trim(preg_replace('/\s+/', ' ', (string) $written));

	if (($written === '') || !function_exists($function)) {
		return $written;
	}

	$parameters = (new ReflectionFunction($function))->getParameters();

	if (empty($parameters) || !$parameters[0]->isDefaultValueAvailable()) {
		return $written;
	}

	$defaults = array(var_export($parameters[0]->getDefaultValue(), true));

	if ($parameters[0]->isDefaultValueConstant()) {
		$defaults[] = ltrim((string) $parameters[0]->getDefaultValueConstantName(), '\\');
	}

	return in_array($written, $defaults, true) ? '' : $written;
}

// The calls of a function in the product's PHP files, comments left out, by
// where they are: "name(first argument) in file", then the function they
// are in (none outside a function) and the case of the switch they come
// under there, if any ("case 'ws_tick'"); a second call in the same place
// written the same way numbered (workspace_flows_parity_numbered()). The
// first argument is keyed by workspace_flows_parity_first_argument(); the
// others are not keyed, so a call given a further argument stays the same
// place.
function workspace_flows_parity_php_call_sites($name)
{
	$root = realpath(PG_FUNCTIONS_DIR);
	$out = array();

	foreach (workspace_flows_parity_product_files() as $path) {
		$code = (string) file_get_contents($path);

		if (strpos($code, $name) === false) {
			continue;
		}

		$tokens = array_values(array_filter(token_get_all($code), function ($token) {
			return !is_array($token) || !in_array($token[0], array(T_COMMENT, T_DOC_COMMENT), true);
		}));
		$file = str_replace('\\', '/', substr($path, strlen($root) + 1));
		$depth = 0;
		$functions = array();
		$cases = array();
		$pending = '';

		foreach ($tokens as $i => $token) {
			if (($token === '{') || (is_array($token) && in_array($token[0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) {
				$depth++;

				if ($pending !== '') {
					$functions[] = array($pending, $depth);
					$pending = '';
				}

				continue;
			}

			if ($token === '}') {
				$depth--;

				while (!empty($functions) && (end($functions)[1] > $depth)) {
					array_pop($functions);
				}

				foreach (array_keys($cases) as $level) {
					if ($level > $depth) {
						unset($cases[$level]);
					}
				}

				continue;
			}

			// A declaration without a body (abstract, an interface's).
			if ($token === ';') {
				$pending = '';
			}

			if (!is_array($token)) {
				continue;
			}

			$next = $i + 1;
			pg_test_source_skip($tokens, $next);

			if ($token[0] === T_FUNCTION) {
				if (($tokens[$next] ?? null) === '&') {
					$next++;
					pg_test_source_skip($tokens, $next);
				}

				if (is_array($tokens[$next] ?? null) && ($tokens[$next][0] === T_STRING)) {
					$pending = $tokens[$next][1];
				}

				continue;
			}

			if (($token[0] === T_CASE) && is_array($tokens[$next] ?? null) && ($tokens[$next][0] === T_CONSTANT_ENCAPSED_STRING)) {
				$cases[$depth] = 'case ' . $tokens[$next][1];
				continue;
			}

			if (($token[0] !== T_STRING) || ($token[1] !== $name)) {
				continue;
			}

			$args = workspace_flows_parity_php_args($tokens, $i);

			if ($args === null) {
				continue;
			}

			$first = workspace_flows_parity_first_argument($name, implode('', array_map('pg_test_source_text', $args[0])));
			$where = array($file);

			if (!empty($functions)) {
				$where[] = end($functions)[0];
			}

			if (!empty($cases)) {
				ksort($cases);
				$where[] = end($cases);
			}

			$out[] = $name . '(' . $first . ') in ' . implode(' ', $where);
		}
	}

	return workspace_flows_parity_numbered($out);
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

// What an action is made of: the keys a draft sends (data.key, read by
// ws_scheduled_input() and ws_scheduled_save_check()), those of a follow-up
// (follow.key), the row the check gives back to be written (row.key) and
// the columns ws_scheduled_decode() reads as JSON (column.key).
function workspace_flows_parity_read_action()
{
	$input = workspace_flows_parity_source('ws_scheduled_input');
	$out = array();

	foreach (array_merge(workspace_flows_parity_reads($input, 'data'), workspace_flows_parity_reads(workspace_flows_parity_source('ws_scheduled_save_check'), 'data')) as $key) {
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
// lists as ws_scheduled_rule_input() names them ($filter_values, every
// write to it read).
function workspace_flows_parity_read_event_filter_values()
{
	$out = array();
	$lists = workspace_flows_parity_literal_entries_in(workspace_flows_parity_rule_cases()['event'], '$filter_values', 'The event branch of ws_scheduled_rule_input()');

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
	// to it (any write to $out, a nested key or an unset too) changes this,
	// and the reader has to learn it first.
	$flow = workspace_flows_parity_control_flow($source);
	$writes = array();

	foreach (workspace_flows_parity_writes(pg_test_source_tokens($source), '$out') as $write) {
		$writes[] = '$out' . (empty($write['written_as']) ? '' : '[' . implode('][', $write['written_as']) . ']') . ' ' . $write['operator'];
	}

	preg_match_all("/\\\$field\[0\]\s*!==\s*'([a-z_]+)'/", $source, $dropped);

	if (($flow !== 'foreach if continue return return') || ($writes !== array('$out =', '$out[$type] =', "\$out['task'] =")) || (count($dropped[1]) !== 1)) {
		throw new RuntimeException('ws_scheduled_record_types() is no longer the loop workspace_flows_parity_record_types() models (control flow "' . $flow . '", writes ' . implode(', ', $writes) . '): teach the reader what it does now.');
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

// The keys each step reads from its action, in all its code: step.key; and
// the keys of the items of a list option, read in a loop over it:
// step.key.item_key (report.metrics.param). The action is followed only
// where workspace_flows_parity_whole_uses() allows a use of the whole of it.
function workspace_flows_parity_read_step_options()
{
	$out = array();

	foreach (workspace_flows_parity_step_code() as $step => $parts) {
		$keys = array();

		foreach ($parts as $part) {
			workspace_flows_parity_whole_uses($part[0], $part[1]);
			$keys = array_merge($keys, workspace_flows_parity_reads($part[0], $part[1]));

			foreach (workspace_flows_parity_loop_items($part[0], $part[1]) as $item) {
				$keys[] = $item;
			}
		}

		foreach (array_unique($keys) as $key) {
			$out[] = $step . '.' . $key;
		}
	}

	return $out;
}

// The keys some PHP code reads from the items of a list in an array
// variable, in the body of a loop over it: foreach ((array) $action['metrics']
// as $item) { ... $item['param'] ... } is metrics.param.
function workspace_flows_parity_loop_items($code, $variable)
{
	preg_match_all('/\bforeach\s*\(([^;{]*?)\s+as\s+(?:\$[A-Za-z_]\w*\s*=>\s*)?\$([A-Za-z_]\w*)\s*\)\s*\{/', $code, $loops, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

	$out = array();

	foreach ($loops as $loop) {
		$lists = workspace_flows_parity_reads($loop[1][0], $variable);

		if (empty($lists)) {
			continue;
		}

		// The body, from its brace to the one that closes it (PHP's brackets,
		// strings and comments step over as JavaScript's do here).
		$open = $loop[0][1] + strlen($loop[0][0]) - 1;
		$body = substr($code, $open, workspace_flows_parity_js_close($code, $open) - $open + 1);

		foreach ($lists as $list) {
			foreach (workspace_flows_parity_reads($body, $loop[2][0]) as $key) {
				$out[] = $list . '.' . $key;
			}
		}
	}

	return array_values(array_unique($out));
}

// The values a step option takes from a list written in the code:
// step.key=value. Three shapes are read:
// - compared: (string) ($action['mode'] ?? 'status') === 'ssl', its default,
//   the values it is compared with and those the check writes ('mode' =>
//   'ssl');
// - handed on by name to a Workspace function that compares it there:
//   ws_approval_input(..., array('rule' => (string) ($action['rule'] ??
//   'any'))), where ($data['rule'] ?? 'any') === 'all', and on into the
//   functions that one hands the array to;
// - kept to a list: $priority = (string) ($action['priority'] ?? 'normal')
//   checked with isset(ws_task_priorities()[$priority]): the list's keys.
// An option compared or handed on that is read any other way throws, so a
// value added there is not missed; one kept to a list can take no other
// value, however the run reads it.
function workspace_flows_parity_read_step_option_values()
{
	$compare = function ($variable, $key) {
		return '/' . workspace_flows_parity_subscript($variable, $key) . "\s*\?\?\s*'([a-z_]*)'\s*\)\s*[!=]==\s*'([a-z_]+)'/";
	};
	$out = array();

	foreach (workspace_flows_parity_step_code() as $step => $parts) {
		$values = array();
		$listed = array();
		$known = array();
		$elsewhere = array();

		foreach ($parts as $index => $part) {
			preg_match_all($compare($part[1], '([a-z_]+)'), $part[0], $compared, PREG_SET_ORDER);

			foreach ($compared as $match) {
				$values[$match[1]][] = $match[3];

				if ($match[2] !== '') {
					$values[$match[1]][] = $match[2];
				}

				$known[$index][$match[1]] = ($known[$index][$match[1]] ?? 0) + 1;
			}

			foreach (workspace_flows_parity_handed_by_name($part[0], $part[1]) as $handed) {
				list($function, $parameter, $key, $default) = $handed;
				$seen = array();
				$targets = array_merge(array(array($function, $parameter)), workspace_flows_parity_handed(workspace_flows_parity_source($function), $parameter, $seen));
				$found = array();

				foreach ($targets as $target) {
					preg_match_all($compare($target[1], preg_quote($key, '/')), workspace_flows_parity_source($target[0]), $there, PREG_SET_ORDER);

					foreach ($there as $match) {
						$found[] = $match[2];

						if ($match[1] !== '') {
							$found[] = $match[1];
						}
					}
				}

				// Handed on but compared nowhere: not an option from a list.
				if (empty($found)) {
					continue;
				}

				$values[$key] = array_merge($values[$key] ?? array(), $found, ($default !== '') ? array($default) : array());
				$known[$index][$key] = ($known[$index][$key] ?? 0) + 1;

				foreach ($targets as $target) {
					$elsewhere[] = array($target[0], $target[1], $key);
				}
			}

			preg_match_all('/\$([a-z_]+)\s*=\s*\(string\)\s*\(' . workspace_flows_parity_subscript($part[1], '([a-z_]+)') . "\s*\?\?\s*'([a-z_]*)'\s*\);/", $part[0], $assigned, PREG_SET_ORDER);

			foreach ($assigned as $match) {
				if (preg_match('/\bisset\(\s*(ws_[a-z_]+)\(\)\[\$' . $match[1] . '\]\s*\)/', $part[0], $list)) {
					$values[$match[2]] = array_merge($values[$match[2]] ?? array(), array_map('strval', array_keys(call_user_func($list[1]))), ($match[3] !== '') ? array($match[3]) : array());
					$listed[$match[2]] = true;
				}
			}
		}

		foreach (array_keys($values) as $key) {
			foreach ($parts as $index => $part) {
				if (empty($listed[$key])) {
					$reads = preg_match_all('/' . workspace_flows_parity_subscript($part[1], $key) . '/', $part[0]);

					if ($reads !== ($known[$index][$key] ?? 0)) {
						throw new RuntimeException('The ' . $step . ' step reads ' . $key . ' other than as ($' . $part[1] . "['" . $key . "'] ?? '...') === '...' or handed on by name to where it is compared so: teach workspace_flows_parity_read_step_option_values() to read its values.");
					}
				}

				preg_match_all("/'" . $key . "'\s*=>\s*'([a-z_]+)'/", $part[0], $written);
				$values[$key] = array_merge($values[$key], $written[1]);
			}

			foreach ($elsewhere as $target) {
				if ($target[2] !== $key) {
					continue;
				}

				$source = workspace_flows_parity_source($target[0]);

				if (preg_match_all('/' . workspace_flows_parity_subscript($target[1], $key) . '/', $source) !== preg_match_all($compare($target[1], $key), $source)) {
					throw new RuntimeException($target[0] . '() reads the ' . $key . ' the ' . $step . ' step hands it other than as ($' . $target[1] . "['" . $key . "'] ?? '...') === '...': teach workspace_flows_parity_read_step_option_values() to read its values.");
				}
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

// The fields a change step can write that no condition looks at: of each
// kind it updates (workspace_flows_parity_read_change_kinds()), the fields
// ws_change_fields_for() gives an update, called on the change catalogue as
// written, but those of workspace_flows_parity_read_record_fields(), which
// the change box writes as well: kind.field.
function workspace_flows_parity_read_change_fields()
{
	$check = workspace_flows_parity_cases(workspace_flows_parity_source('ws_scheduled_action_input'))['change'];

	if (substr_count($check, "ws_change_fields_for(\$types[\$record_type], 'update')") !== 1) {
		throw new RuntimeException('The change step no longer takes its fields from ws_change_fields_for($types[$record_type], \'update\'): teach workspace_flows_parity_read_change_fields() where they come from.');
	}

	$types = pg_test_ws_change_types();
	$conditions = workspace_flows_parity_read_record_fields();
	$out = array();

	foreach (workspace_flows_parity_read_change_kinds() as $change) {
		list($type, $verb) = explode('.', $change, 2);

		if ($verb !== 'update') {
			continue;
		}

		foreach (array_keys(ws_change_fields_for($types[$type], 'update')) as $field) {
			if (!in_array($type . '.' . $field, $conditions, true)) {
				$out[] = $type . '.' . $field;
			}
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
// dot; the generic body is the switch's default. Every write to $payload on
// the way through a format's branch (before the switch, in the branch, after
// it) is read: a key set on its own is a key, any other write throws.
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

	foreach (workspace_flows_parity_switch_paths(workspace_flows_parity_source('ws_scheduled_webhook')) as $format => $code) {
		$name = ($format === 'default') ? 'generic' : $format;
		$flatten($name . '.', workspace_flows_parity_literal_entries_in($code, '$payload', 'ws_scheduled_webhook() (' . $name . ')'));
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
	return workspace_flows_parity_limits_in(workspace_flows_parity_file('includes/workspace/scheduled.php'));
}

// The WS_SCHEDULED_* constants a PHP file (its whole text) defines, by
// define() or const, comments left out, in the order they come: NAME=value,
// the value as PHP works it out (the file is loaded), so a limit written as
// 10 * 60 is read as 600, however the call is spaced or quoted. A define()
// whose name is worked out by code throws, since it may name a limit, and so
// does one the test has not loaded.
function workspace_flows_parity_limits_in($code)
{
	$tokens = array_values(array_filter(token_get_all($code), function ($token) {
		return !is_array($token) || !in_array($token[0], array(T_COMMENT, T_DOC_COMMENT), true);
	}));
	$out = array();

	foreach ($tokens as $i => $token) {
		if (!is_array($token)) {
			continue;
		}

		$name = null;

		if (($token[0] === T_STRING) && (strtolower($token[1]) === 'define')) {
			$args = workspace_flows_parity_php_args($tokens, $i);

			if ($args === null) {
				continue;
			}

			$first = array_values(array_filter($args[0], function ($part) {
				return !is_array($part) || ($part[0] !== T_WHITESPACE);
			}));

			if ((count($first) !== 1) || !is_array($first[0]) || ($first[0][0] !== T_CONSTANT_ENCAPSED_STRING)) {
				throw new RuntimeException('A define() whose name is worked out by code (' . trim(implode('', array_map('pg_test_source_text', $args[0]))) . '): teach workspace_flows_parity_limits_in() which constant that defines.');
			}

			$name = pg_test_source_string($first[0][1]);
		} elseif ($token[0] === T_CONST) {
			$next = $i + 1;
			pg_test_source_skip($tokens, $next);
			$name = (is_array($tokens[$next] ?? null) && ($tokens[$next][0] === T_STRING)) ? $tokens[$next][1] : null;
		}

		if (($name === null) || (strpos($name, 'WS_SCHEDULED_') !== 0)) {
			continue;
		}

		if (!defined($name)) {
			throw new RuntimeException($name . ' is defined in the code but not loaded by the test: load the file that defines it, so its value is read.');
		}

		$out[] = $name . '=' . var_export(constant($name), true);
	}

	return $out;
}

// The caps written as numbers in one place of the code, in the order they
// come, a repeat numbered: a value kept to at most N (min(N, ...)): "place
// min N"; a text or a list cut to its first N (..., 0, N)): "place first N";
// a text's length or a list's count compared with a number (mb_strlen(...)
// > N, strlen(), count()): "place length > N", "place count > N", a number
// written first turned round (N < count(...) is count > N). The comparison
// is kept as written, since only the code around it tells a longest from a
// shortest.
function workspace_flows_parity_caps_in($place, $code)
{
	$measure = '\b(?<measure>mb_strlen|strlen|count)\s*(?<args>\((?:[^()]++|(?&args))*\))';
	$turned = array('<' => '>', '>' => '<', '<=' => '>=', '>=' => '<=');
	$found = array();

	preg_match_all('/\bmin\(\s*(\d+)\s*,/', $code, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

	foreach ($matches as $match) {
		$found[$match[0][1]] = 'min ' . $match[1][0];
	}

	preg_match_all('/,\s*0\s*,\s*(\d+)\s*\)/', $code, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

	foreach ($matches as $match) {
		$found[$match[0][1]] = 'first ' . $match[1][0];
	}

	preg_match_all('/' . $measure . '\s*(?<operator><=|>=|<|>)\s*(?<number>\d+)/', $code, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

	foreach ($matches as $match) {
		$found[$match[0][1]] = (($match['measure'][0] === 'count') ? 'count ' : 'length ') . $match['operator'][0] . ' ' . $match['number'][0];
	}

	preg_match_all('/(?<![\w$.])(?<number>\d+)\s*(?<operator><=|>=|<|>)\s*' . $measure . '/', $code, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

	foreach ($matches as $match) {
		$found[$match[0][1]] = (($match['measure'][0] === 'count') ? 'count ' : 'length ') . $turned[$match['operator'][0]] . ' ' . $match['number'][0];
	}

	ksort($found);
	$keys = array();

	foreach ($found as $cap) {
		$keys[] = $place . ' ' . $cap;
	}

	return workspace_flows_parity_numbered($keys);
}

// The caps written as numbers (workspace_flows_parity_caps_in()) in the code
// that checks an action, which no WS_SCHEDULED_* constant names. The places:
// the action's own check (action, ws_scheduled_input()), each branch of a
// rule's check (rule kind) and of a step's (step kind), and the
// ws_scheduled_* functions those call.
function workspace_flows_parity_read_caps()
{
	$checks = array('ws_scheduled_input', 'ws_scheduled_rule_input', 'ws_scheduled_action_input');
	$places = array('action' => workspace_flows_parity_source('ws_scheduled_input'));

	foreach (workspace_flows_parity_rule_cases() as $kind => $code) {
		$places['rule ' . $kind] = $code;
	}

	foreach (workspace_flows_parity_cases(workspace_flows_parity_source('ws_scheduled_action_input')) as $step => $code) {
		$places['step ' . $step] = $code;
	}

	$helpers = array();

	foreach ($places as $code) {
		foreach (workspace_flows_parity_php_calls($code) as $function) {
			if ((strpos($function, 'ws_scheduled_') === 0) && !in_array($function, $checks, true)) {
				$helpers[$function] = true;
			}
		}
	}

	ksort($helpers);

	foreach (array_keys($helpers) as $function) {
		$places[$function . '()'] = workspace_flows_parity_source($function);
	}

	$out = array();

	foreach ($places as $place => $code) {
		$out = array_merge($out, workspace_flows_parity_caps_in($place, $code));
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

// The code that fills the placeholders: ws_scheduled_fill(),
// ws_events_fill_values() and the Workspace functions they hand the values
// they build to (ws_events_own_values()), each as [name, source].
function workspace_flows_parity_fill_code()
{
	$out = array();

	foreach (array('ws_scheduled_fill', 'ws_events_fill_values') as $function) {
		$source = workspace_flows_parity_source($function);
		$out[] = array($function, $source);
		$seen = array();

		foreach (workspace_flows_parity_handed($source, 'values', $seen) as $handed) {
			$out[] = array($handed[0], workspace_flows_parity_source($handed[0]));
		}
	}

	return $out;
}

// The placeholders that code fills: the literals ('{{date}}'), those a
// pattern asks for (\{\{(event|record)\}\}), a count by its key
// ({{count:<key>}}) and, where the count's pattern takes one, a count with
// its threshold ({{count:<key>:<param>}}).
function workspace_flows_parity_filled_tokens()
{
	$tokens = array();

	foreach (workspace_flows_parity_fill_code() as $fill) {
		$source = $fill[1];
		preg_match_all("/'(\{\{[a-z_]+\}\})'/", $source, $literal);
		$tokens = array_merge($tokens, $literal[1]);

		if (preg_match('/\\\\\{\\\\\{\(([a-z_|]+)\)\\\\\}\\\\\}/', $source, $group)) {
			foreach (explode('|', $group[1]) as $name) {
				$tokens[] = '{{' . $name . '}}';
			}
		}

		$at = strpos($source, '\{\{count:');

		if ($at !== false) {
			$tokens[] = '{{count:<key>}}';
			$end = strpos($source, '\}\}', $at);

			if (($end !== false) && (strpos(substr($source, $at, $end - $at), '(?::(\d+))') !== false)) {
				$tokens[] = '{{count:<key>:<param>}}';
			}
		}
	}

	return array_values(array_unique($tokens));
}

// The placeholders: those the form lists, those the fill code fills. A count
// the form lists is {{count:<key>}}.
function workspace_flows_parity_read_tokens()
{
	$out = array();

	foreach (array_merge(array_keys(ws_scheduled_placeholders()), workspace_flows_parity_filled_tokens()) as $token) {
		$out[] = preg_match('/^\{\{count:[a-z_]+\}\}$/', $token) ? '{{count:<key>}}' : $token;
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
// its labels, each as often as it writes it (label key, label key #2: a
// label written in two places is two keys), but the one use of each button's
// own label; the fields of the action it reads (item.field, item.last.status)
// and those of what its callbacks are handed: a follow-up, a starter, a
// History run, an answer (follow.on, run.source, data.item.history).
function workspace_flows_parity_read_card_parts()
{
	$card = workspace_flows_parity_js_function('assets/js/workspace.js', 'scheduledCard');
	$labels = workspace_flows_parity_t_uses($card);
	$out = array();

	foreach (workspace_flows_parity_read_card_buttons() as $button) {
		$at = array_search($button, $labels, true);

		if ($at !== false) {
			unset($labels[$at]);
		}
	}

	foreach (workspace_flows_parity_numbered(array_values($labels)) as $key) {
		$out[] = 'label ' . $key;
	}

	foreach (workspace_flows_parity_js_fields($card, 'item') as $field) {
		$out[] = 'item.' . $field;
	}

	// Each callback's own parameter, read in its own body.
	preg_match_all('/\bfunction\s*\(\s*([A-Za-z_$][\w$]*)\s*\)\s*\{/', $card, $callbacks, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

	foreach ($callbacks as $callback) {
		$open = $callback[0][1] + strlen($callback[0][0]) - 1;
		$body = substr($card, $open, workspace_flows_parity_js_close($card, $open) - $open + 1);

		foreach (workspace_flows_parity_js_fields($body, $callback[1][0]) as $field) {
			$out[] = $callback[1][0] . '.' . $field;
		}
	}

	return array_values(array_unique($out));
}

// What the list of scheduled actions draws around the cards (showScheduled()
// in workspace.js, and scheduledList(), which it and the Scheduled actions
// section of Flows draw the list with): its labels (the state chips, "in
// #channel / in note", the tabs) and the fields of an action it reads.
function workspace_flows_parity_read_list_parts()
{
	$js = workspace_flows_parity_file('assets/js/workspace.js');
	$list = '';

	foreach (array('/\n(\s*)showScheduled: function \(/' => 'showScheduled: function ()', '/\n(\s*)function scheduledList\(/' => 'function scheduledList()') as $pattern => $name) {
		if (!preg_match($pattern, $js, $match, PREG_OFFSET_CAPTURE)) {
			throw new RuntimeException('workspace.js has no ' . $name . '.');
		}

		$start = $match[0][1];
		$open = strpos($js, '{', strpos($js, ')', $start));
		$list .= substr($js, $start, workspace_flows_parity_js_close($js, $open) - $start + 1);
	}

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
// it draws and the functions it is drawn in (a second call written the same
// way in the same place numbered).
function workspace_flows_parity_read_card_places()
{
	$js = workspace_flows_parity_file('assets/js/workspace.js');
	$out = array();

	foreach (workspace_flows_parity_js_calls($js, 'scheduledCard') as $offset => $args) {
		$out[] = 'scheduledCard(' . $args[0] . ') in ' . workspace_flows_parity_js_enclosing($js, $offset);
	}

	return workspace_flows_parity_numbered($out);
}

// The panel routes of scheduled actions and messages: the branches of the
// Workspace's action switch (includes/workspace/actions.php), the rows of
// pg_panel_actions() that let api.php take them, and the api() calls of
// workspace.js that ask them.
function workspace_flows_parity_read_routes()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');

	preg_match_all("/^\s*case '(ws_scheduled_[a-z_]+)':/m", workspace_flows_parity_file('includes/workspace/actions.php'), $cases);
	preg_match_all("/\bapi\('(ws_scheduled_[a-z_]+)'/", workspace_flows_parity_file('assets/js/workspace.js'), $calls);

	$out = array();

	foreach (array_unique($cases[1]) as $route) {
		$out[] = 'route ' . $route;
	}

	foreach (array_keys(pg_panel_actions()) as $route) {
		if (strpos($route, 'ws_scheduled_') === 0) {
			$out[] = 'panel ' . $route;
		}
	}

	foreach (array_unique($calls[1]) as $route) {
		$out[] = 'api ' . $route;
	}

	return $out;
}

// What the routes of scheduled actions take: the keys each reads from the
// request (ws_scheduled_status.key); the scheduled messages' routes are left
// out.
function workspace_flows_parity_read_request()
{
	$out = array();

	foreach (workspace_flows_parity_cases(workspace_flows_parity_file('includes/workspace/actions.php')) as $route => $code) {
		if ((strpos($route, 'ws_scheduled_') !== 0) || (strpos($route, 'message') !== false)) {
			continue;
		}

		foreach (workspace_flows_parity_reads($code, 'request') as $key) {
			$out[] = $route . '.' . $key;
		}
	}

	return $out;
}

// Pinegrap AI drafting a scheduled action: its tool, as named
// (ws_ai_tool_names()), offered to the model and handled (ai.php), and the
// functions that treat a draft of the kind 'scheduled' on its own: by its
// kind ($type === 'scheduled', the API's list of drafts, applying one), by
// its row (record_type = 'scheduled', marking it saved) or writing one
// ('type' => 'scheduled').
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
		foreach (workspace_flows_parity_functions_at(workspace_flows_parity_file($file), "/\\\$type\s*===\s*'scheduled'|\brecord_type\s*=\s*'scheduled'|'type'\s*=>\s*'scheduled'/") as $function) {
			$out[] = 'draft kind scheduled in ' . $function;
		}
	}

	return $out;
}

// The entries of ws_nav_groups() with a key (the Workspace list of the main
// menu, which assets/js/workspace_side.js draws with data-ws-nav from the
// key), by their label: each array literal of its source, wherever it is
// written.
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
// (ws_inbox_describe()), the ?view=scheduled address, the entries of the
// main menu's Workspace list, the channel screen's in-place answer to them,
// the tour's step.
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
		$out[] = 'menu data-ws-nav="scheduled" ' . $label;
	}

	// The list draws every place with data-ws-nav from its key; the channel
	// screen answers the scheduled one in place (app.sideHooks()).
	$side = workspace_flows_parity_file('assets/js/workspace_side.js');

	if (!preg_match("/setAttribute\('data-ws-nav', item\.key\)/", $side)) {
		throw new RuntimeException('workspace_side.js no longer draws data-ws-nav from the ws_nav_groups() key: teach workspace_flows_parity_read_links_in() where it comes from.');
	}

	$out[] = 'menu data-ws-nav from the ws_nav_groups() key';

	if (preg_match('/\bscheduled\s*:\s*function\s*\(\)\s*\{\s*self\.showScheduled\(/', $js)) {
		$out[] = 'menu in place showScheduled';
	}

	preg_match_all("/'art'\s*=>\s*'([a-z_]*scheduled[a-z_]*)',\s*'target'\s*=>\s*array\('([^']+)'/", workspace_flows_parity_source('ws_tour_steps'), $steps, PREG_SET_ORDER);

	foreach ($steps as $step) {
		$out[] = 'tour ' . $step[1] . ' ' . $step[2];
	}

	return $out;
}

// What starts and runs an action, by where it is called in the product: the
// drivers (ws_scheduled_run(), with what it is asked to take), what tells a
// screen one is due (ws_scheduled_due()), the run of one action
// (ws_scheduled_execute()), the events taken in and started on
// (ws_events_process(), ws_events_start()), a join's starts
// (ws_scheduled_joined()) and the Workspace's own events as they happen
// (ws_event(), by event).
function workspace_flows_parity_read_starts()
{
	$out = array();

	foreach (array('ws_scheduled_run', 'ws_scheduled_due', 'ws_scheduled_execute', 'ws_events_process', 'ws_events_start', 'ws_scheduled_joined', 'ws_event') as $function) {
		$out = array_merge($out, workspace_flows_parity_php_call_sites($function));
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
 * The groups of keys: the row each belongs to (Action, When, Conditions,
 * Steps...), the slices that row allows (a key may also stay in the form),
 * the function that reads the keys from the code, and where it reads them.
 *
 * @return array group => [row, slices, reader, where]
 */
function workspace_flows_parity_groups()
{
	return array(
		'action'              => array('Action', array(1, 2, 3), 'workspace_flows_parity_read_action', 'the $data and follow-up keys of ws_scheduled_input() and ws_scheduled_save_check(), the row it gives back, the columns of ws_scheduled_decode()'),
		'request'             => array('Action', array(1, 2, 3), 'workspace_flows_parity_read_request', 'the $request keys of the scheduled actions\' routes in includes/workspace/actions.php'),
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
		'step_option_values'  => array('Steps', array(3), 'workspace_flows_parity_read_step_option_values', 'the values a step option is compared with in the code of its step, where it is handed on by name, or the list that keeps it'),
		'change_kinds'        => array('Steps', array(3), 'workspace_flows_parity_read_change_kinds', 'the change catalogue as written, minus ws_scheduled_change_skipped(), update and delete'),
		'change_fields'       => array('Steps', array(3), 'workspace_flows_parity_read_change_fields', 'the update fields (ws_change_fields_for()) of the kinds a change step updates, but those a condition reads'),
		'digest_filters'      => array('Steps', array(3), 'workspace_flows_parity_read_digest_filters', 'ws_scheduled_digest_filters()'),
		'webhook_formats'     => array('Webhook body', array(3, 5), 'workspace_flows_parity_read_webhook_formats', 'ws_scheduled_webhook_formats()'),
		'webhook_body'        => array('Webhook body', array(3, 5), 'workspace_flows_parity_read_webhook_body', 'the $payload of each format in ws_scheduled_webhook()'),
		'outcomes'            => array('Follow-ups', array(3), 'workspace_flows_parity_read_outcomes', 'ws_scheduled_outcomes()'),
		'limits'              => array('Chains', array(3), 'workspace_flows_parity_read_limits', 'the WS_SCHEDULED_* constants of scheduled.php'),
		'caps'                => array('When, Conditions, Steps', array(3), 'workspace_flows_parity_read_caps', 'the caps written as numbers in ws_scheduled_input(), ws_scheduled_rule_input(), ws_scheduled_action_input() and the ws_scheduled_* functions they call'),
		'limit_uses'          => array('Chains', array(3), 'workspace_flows_parity_read_limit_uses', 'the Workspace functions that name a chain limit or ws_event_depth()'),
		'starts'              => array('When', array(3), 'workspace_flows_parity_read_starts', 'the calls in the product of ws_scheduled_run(), ws_scheduled_due(), ws_scheduled_execute(), ws_events_process(), ws_events_start(), ws_scheduled_joined() and ws_event()'),
		'tokens'              => array('Tokens', array(3), 'workspace_flows_parity_read_tokens', 'ws_scheduled_placeholders(), ws_scheduled_fill(), ws_events_fill_values() and the functions they hand their values to'),
		'states'              => array('States', array(5), 'workspace_flows_parity_read_states', 'ws_scheduled_states()'),
		'news'                => array('News', array(5), 'workspace_flows_parity_read_news', 'what ws_scheduled_report() calls; the deletes of ws_scheduled_runs in the product'),
		'record_writes'       => array('Record writes', array(3), 'workspace_flows_parity_read_record_writes', 'ws_scheduled_change()'),
		'card_buttons'        => array('Screen', array(1, 2), 'workspace_flows_parity_read_card_buttons', 'the button() labels of scheduledCard() in workspace.js'),
		'card_parts'          => array('Screen', array(1), 'workspace_flows_parity_read_card_parts', 'the other labels (each use), the item fields and those of its callbacks\' parameters in scheduledCard() of workspace.js'),
		'list_parts'          => array('Screen', array(1), 'workspace_flows_parity_read_list_parts', 'the labels and the item fields of showScheduled() and scheduledList() in workspace.js'),
		'card_places'         => array('Screen', array(1), 'workspace_flows_parity_read_card_places', 'the scheduledCard() calls of workspace.js and the functions they are in'),
		'routes'              => array('Screen', array(1), 'workspace_flows_parity_read_routes', 'the ws_scheduled_* branches of includes/workspace/actions.php, their pg_panel_actions() rows and the api() calls of workspace.js'),
		'ai_drafts'           => array('Entry points', array(1, 4), 'workspace_flows_parity_read_ai_drafts', 'Pinegrap AI\'s tool in ai.php and the functions that treat, mark or write a draft of the kind scheduled'),
		'links_in'            => array('Links in', array(1), 'workspace_flows_parity_read_links_in', 'ws_inbox_describe(), ws_nav_groups(), workspace.js, ws_tour_steps()'),
		'inbox_kinds'         => array('API', array(5), 'workspace_flows_parity_read_inbox_kinds', 'the inbox route of includes/workspace/api.php'),
	);
}

/**
 * The parity table: per group, lines of [keys, slice, what keeps it]. The
 * slice is the stage of the move that keeps the keys (1 one screen, 2 show on
 * canvas, 3 parity boxes, 4 new rules, 5 convert); what keeps them is the
 * box, or the part of the screen, that does. 'form' instead of a slice keeps
 * a key in the scheduled action's form, and its text says why.
 *
 * "The anchor" is the hidden ws_scheduled_actions row behind a flow's
 * trigger box; "steps.php" is the step code to be shared by flows and
 * scheduled actions, which ws_scheduled_do() is to hand its steps to (not
 * written yet); "the flows' own" boxes are those of the flow engine, not
 * of scheduled actions.
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
		'request' => array(
			array(array('ws_scheduled_list.status', 'ws_scheduled_list.channel_id'), 1, 'Scheduled actions section in Flows: the list by state chip, or one channel\'s'),
			array(array('ws_scheduled_get.id'), 1, 'History on the card'),
			array(array('ws_scheduled_status.id', 'ws_scheduled_status.status'), 1, 'Pause/Resume and Cancel on the card'),
			array(array('ws_scheduled_run_now.id'), 1, 'Run now on the card'),
		),
		'when' => array(
			array(array('at'), 3, 'schedule trigger box on the anchor'),
			array(array('trigger'), 3, 'a flow started by hand, or by another flow\'s start a flow box (ws_flow_chain_start())'),
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
			array(array('workspace.task.completed', 'workspace.task.status_changed', 'workspace.task.overdue', 'workspace.approval.decided', 'workspace.guest.message', 'workspace.mail.received'), 3, 'event trigger box: Workspace events, each checked against what the creator reads (ws_events_creator_sees())'),
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
			array(array('approval'), 3, 'approval box, the flows\' own'),
			array(array('checkin'), 3, 'check-in box (steps.php)'),
			array(array('lead'), 3, 'lead box (steps.php): a submitted form\'s customer into a channel, reused or opened with the New customer template'),
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
			array(workspace_flows_parity_prefixed('report.metrics', 'key param'), 3, 'report box: each count by its key, with its channel or threshold'),
			array(array('trigger.target_id'), 3, 'start box: the action or flow started; itself again is a step box\'s own retry (retry, retry_after: up to WS_FLOW_RETRY_MAX more tries, minutes before each)'),
			array(array('trigger.delay'), 3, 'delay box, the flows\' own: minutes before the start'),
			array(workspace_flows_parity_prefixed('change', 'record action id fields'), 3, 'change box: kind, update or delete, record, fields'),
			array(workspace_flows_parity_prefixed('approval', 'channel_id title text approvers rule'), 3, 'approval box: channel, title, text, people asked, rule'),
			array(array('approval.closes_at'), 3, 'approval box: its deadline, closes_in hours from when the box runs (the flows\' own; the scheduled form sets none)'),
			array(workspace_flows_parity_prefixed('checkin', 'channel_id question'), 3, 'check-in box: channel, question'),
			array(workspace_flows_parity_prefixed('lead', 'private user_ids'), 3, 'lead box: a private channel, the people put in a channel it opens'),
		),
		'step_option_values' => array(
			array(array('web_check.mode=status'), 3, 'web check box: the answer (redirect, contains, slowest answer)'),
			array(array('web_check.mode=ssl'), 3, 'web check box: the certificate (days left)'),
			array(array('task_digest.mode=mine'), 3, 'task e-mail box: each person their own tasks'),
			array(array('task_digest.mode=channel'), 3, 'task e-mail box: the tasks of a channel'),
			array(workspace_flows_parity_prefixed('task_digest.only=', 'open week overdue stale'), 3, 'task e-mail box: which tasks (ws_scheduled_digest_filters())'),
			array(workspace_flows_parity_prefixed('task.priority=', 'low normal high urgent'), 3, 'task box: the priority (ws_task_priorities())'),
			array(workspace_flows_parity_prefixed('webhook.format=', 'generic text discord'), 3, 'webhook box: the format (ws_scheduled_webhook_formats())'),
			array(workspace_flows_parity_prefixed('approval.rule=', 'any all'), 3, 'approval box: one approval or all of them (ws_approval_input(), ws_approval_rules())'),
		),
		'change_kinds' => array(
			array(array('product.update', 'product.delete', 'stock.update', 'order.update', 'product_group.update', 'product_group.delete'), 3, 'change box: shop records'),
			array(array('contact.update', 'contact.delete', 'erp_account.update', 'form.update'), 3, 'change box: contacts, ERP accounts, form submissions'),
			array(array('user.update', 'page.update', 'page.delete', 'file.update', 'file.delete', 'offer.update', 'offer.delete', 'calendar_event.update', 'calendar_event.delete'), 3, 'change box: users, pages, files, offers, calendar events'),
		),
		'change_fields' => array(
			array(array('order.cancellation_reason'), 3, 'change box: the reason an order is cancelled (no column of its own, so no condition reads it)'),
			array(array('form.answers'), 3, 'change box: a submission\'s answers, a set of fields (no condition reads them)'),
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
			array(array('generic.flow'), 3, 'webhook box: which flow, run and box called (flows only)'),
			array(array('text.text', 'discord.content'), 3, 'webhook box: the chat message'),
		),
		'outcomes' => array(
			array(array('done'), 3, 'arrow on done'),
			array(array('failed', 'skipped', 'always'), 3, 'arrows on failed (a step box\'s own), skipped (a condition box\'s no) and always (ws_flow_edge_takes(): any end of the box)'),
		),
		'limits' => array(
			array(array('WS_SCHEDULED_CHAIN_DEPTH=8'), 3, 'depth through flow runs and their events: at most 8 follow one another'),
			array(array('WS_SCHEDULED_CHAIN_HOURLY=60'), 3, 'anchor: 60 starts an hour (chain, join, event)'),
			array(array('WS_SCHEDULED_MAX_FOLLOW=5'), 3, 'outcome arrows: a box has as many as it needs, each box after it a box of the run; one that fails without an arrow on failed or always fails the run'),
			array(array('WS_SCHEDULED_MIN_MINUTES=5'), 3, 'schedule trigger box: every 5 minutes at the most'),
			array(array('WS_SCHEDULED_MAX_RECIPIENTS=20'), 3, 'email box and report box: 20 addresses'),
			array(array('WS_SCHEDULED_LOCK=600'), 3, 'anchor: a run holds it 10 minutes (running_at)'),
			array(array('WS_SCHEDULED_LEAD_HOURLY=20'), 3, 'lead box: at most 20 customer channels opened an hour by the owner'),
			array(array('WS_SCHEDULED_BATCH=5'), 3, 'driver: ws_scheduled_run() takes 5 anchors a request by default (ws_tick, job.php); the Workspace job asks for 20 (see starts)'),
		),
		'caps' => array(
			array(array('action first 160'), 3, 'the anchor: a name of at most 160 characters'),
			array(array('action first 12'), 3, 'at most 12 rules: the trigger box and its condition boxes'),
			array(array('action count > 1'), 'form', 'a site without the 5.85 chains takes one follow-up only (ws_scheduled_chains_ready()); Flows need far later tables (11.0.80), so no flow meets it'),
			array(array('rule at min 720', 'rule at min 24', 'rule at min 52', 'rule at min 365'), 3, 'schedule trigger box: every at most 720 minutes, 24 hours, 52 weeks, 365 days, months or years'),
			array(array('rule record first 500', 'rule record min 3650'), 3, 'condition box: a value of at most 500 characters; within at most 3650 days'),
			array(array('step email first 250', 'step email first 20000'), 3, 'email box: subject 250, text 20000 characters'),
			array(array('step notify first 500'), 3, 'notify box: 500 characters'),
			array(array('step task first 200', 'step task first 8000', 'step task min 3650'), 3, 'task box: title 200, text 8000 characters, due in at most 3650 days'),
			array(array('step task_digest first 250', 'step task_digest first 2000'), 3, 'task e-mail box: subject 250, intro 2000 characters'),
			array(array('step webhook first 4000'), 3, 'webhook box: text 4000 characters'),
			array(array('step web_check min 365', 'step web_check first 200', 'step web_check min 60000'), 3, 'web check box: at most 365 days left asked for, a text of 200 characters, 60000 ms'),
			array(array('step report first 160', 'step report first 20'), 3, 'report box: title 160 characters, at most 20 counts'),
			array(array('step trigger min 10080'), 3, 'delay box, the flows\' own: at most 10080 minutes (a week)'),
			array(array('ws_scheduled_people() first 50'), 3, 'notify, task and task e-mail boxes: at most 50 people'),
			array(array('ws_scheduled_url_input() length > 2000'), 3, 'webhook and web check boxes: an address of at most 2000 characters'),
		),
		'limit_uses' => array(
			array(array('WS_SCHEDULED_CHAIN_DEPTH in ws_events_start'), 3, 'event trigger box: an event at depth 8 starts nothing'),
			array(array('WS_SCHEDULED_CHAIN_DEPTH in ws_scheduled_start'), 3, 'start box: refused at depth 8'),
			array(array('WS_SCHEDULED_CHAIN_DEPTH in ws_flow_chain_refusal', 'WS_SCHEDULED_CHAIN_DEPTH in ws_flow_chain_refusal_text'), 3, 'start a flow box: a run whose box starts another flow is refused at depth 8, and says so'),
			array(array('WS_SCHEDULED_CHAIN_DEPTH in ws_scheduled_run'), 3, 'driver: rounds of starts that wait for nothing in one run'),
			array(array('WS_SCHEDULED_CHAIN_HOURLY in ws_events_start'), 3, 'event trigger box: 60 starts an hour'),
			array(array('WS_SCHEDULED_CHAIN_HOURLY in ws_scheduled_joined'), 3, 'join trigger box: 60 starts an hour'),
			array(array('WS_SCHEDULED_CHAIN_HOURLY in ws_scheduled_start'), 3, 'start box: 60 starts an hour'),
			array(array('WS_SCHEDULED_MAX_FOLLOW in ws_scheduled_input'), 3, 'outcome arrows: no cap of 5 in a flow, whose boxes are bounded by WS_FLOW_MAX_NODES'),
			array(array('ws_event_depth in ws_scheduled_execute'), 3, 'depth through flow runs: a run hands its depth to the events it causes'),
			array(array('ws_event_depth in ws_event'), 3, 'depth through events: a Workspace event carries the depth of the run that caused it'),
			array(array('ws_event_depth in ws_step_run'), 3, 'depth through step boxes: a flow\'s step says its run\'s depth while it works, so the Workspace and site events it causes carry it'),
		),
		'starts' => array(
			array(array('ws_scheduled_run() in includes/workspace/actions.php ws_handle_action case \'ws_tick\''), 3, 'driver: an open screen\'s ws_tick runs what is due, anchors too'),
			array(array('ws_scheduled_run() in job.php'), 3, 'driver: the general job, when ws_scheduled_due() says one is due; it carries the flows\' runs on with a job\'s time (WS_FLOW_BUDGET_JOB)'),
			array(array('ws_scheduled_run(20) in workspace_recurring_job.php'), 3, 'driver: the Workspace job, 20 a run, the flows\' runs with a job\'s time (WS_FLOW_BUDGET_JOB)'),
			array(array('ws_scheduled_due() in includes/workspace/actions.php ws_handle_action case \'ws_sync\'', 'ws_scheduled_due() in job.php'), 3, 'driver: ws_sync tells the screen one is due (scheduled_due); the general job asks first'),
			array(array('ws_scheduled_due() in includes/workspace/actions.php ws_handle_action case \'ws_flow_run_get\''), 3, 'a flow run\'s view says whether something is due (due), so the canvas sends ws_tick, as a channel screen does after ws_sync'),
			array(array('ws_scheduled_execute($scheduled[\'id\']) in includes/workspace/actions.php ws_handle_action case \'ws_scheduled_run_now\''), 3, 'the anchor runs on ws_scheduled_execute() until converted: Run now'),
			array(array('ws_scheduled_execute($scheduled[\'id\']) in includes/workspace/actions.php ws_handle_action case \'ws_scheduled_message_send\''), 'form', 'a scheduled message sent now: scheduled messages are not moved into Flows'),
			array(array('ws_scheduled_execute((int) $id) in includes/workspace/scheduled.php ws_scheduled_run'), 3, 'driver: each anchor whose time has come'),
			array(array('ws_scheduled_execute((int) $target[\'id\']) in includes/workspace/scheduled.php ws_scheduled_queue_take'), 3, 'start box, event and join trigger boxes: the waiting starts, taken in turn'),
			array(array('ws_events_process() in includes/workspace/scheduled.php ws_scheduled_run', 'ws_events_start((int) $row[\'id\']) in includes/workspace/watch.php ws_events_process'), 3, 'event trigger box: the events kept (pg_event_record(), ws_event()) taken in on each run, each starting the actions that wait on it'),
			array(array('ws_scheduled_joined(ws_channel($channel[\'id\'])) in includes/workspace/channels.php ws_channel_add_members', 'ws_scheduled_joined($channel) in includes/workspace/channels.php ws_channel_join'), 3, 'join trigger box: people added to a channel, or joining one'),
			array(array('ws_event(\'workspace.task.completed\') in includes/workspace/tasks.php ws_task_set_status', 'ws_event(\'workspace.task.status_changed\') in includes/workspace/tasks.php ws_task_set_status', 'ws_event(\'workspace.task.overdue\') in includes/workspace/reminders.php ws_task_overdue_run', 'ws_event(\'workspace.approval.decided\') in includes/workspace/approvals.php ws_approval_settle', 'ws_event(\'workspace.guest.message\') in includes/workspace/guests.php ws_guest_send', 'ws_event(\'workspace.mail.received\') in includes/workspace/mail.php ws_mail_ingest'), 3, 'event trigger box: the Workspace\'s own events, kept where they happen'),
		),
		'tokens' => array(
			array(array('{{date}}', '{{time}}', '{{weekday}}', '{{name}}', '{{run}}', '{{count:<key>}}'), 3, 'every box text (ws_scheduled_fill())'),
			array(array('{{count:<key>:<param>}}'), 3, 'every box text: a count with its channel or threshold ({{count:stock_low:5}} of the stock template)'),
			array(array('{{result}}', '{{status}}'), 3, 'outcome arrows: what the step did and how it went'),
			array(array('{{source}}', '{{source_result}}'), 3, 'start box: the action that started this one; in a flow a start a flow box started, that flow and the sentence of the box before it (ws_step_trigger_context())'),
			array(array('{{newcomer}}', '{{newcomer_name}}', '{{channel}}'), 3, 'join trigger box: who joined, where'),
			array(array('{{event}}', '{{record}}', '{{record_title}}', '{{record_link}}', '{{record_status}}', '{{customer}}', '{{actor}}', '{{message}}', '{{amount}}', '{{form_fields}}'), 3, 'event trigger box: what happened, to what (ws_events_fill_values())'),
		),
		'states' => array(
			array(array('active', 'paused', 'done', 'failed', 'cancelled'), 5, 'converted anchor keeps its own state (flows: draft, live, paused, binned)'),
		),
		'news' => array(
			array(array('ws_scheduled_anchor_hidden'), 5, 'only a hidden anchor is silent; a converted one reports once its flow run ends (ws_flow_anchor_run_ended())'),
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
			array(array('edit'), 1, 'Edit on the card: opens its flow, a row not converted yet converted first, or its problems and Duplicate (ws_flow_scheduled_edit)'),
			array(array('sa_pause', 'sa_resume'), 1, 'Scheduled actions section in Flows: Pause and Resume'),
			array(array('sa_run_now'), 1, 'Scheduled actions section in Flows: Run now'),
			array(array('sa_duplicate'), 1, 'Duplicate on the card: a new draft flow of one\'s own, opened on the canvas (ws_flow_scheduled_copy)'),
			array(array('sa_history'), 1, 'Scheduled actions section in Flows: History (by hand, started by)'),
			array(array('sa_cancel'), 1, 'Scheduled actions section in Flows: Cancel'),
			array(array('sa_show_canvas'), 2, 'Show on canvas: the action drawn read-only (ws_scheduled_graph()), in the Scheduled actions section of Flows only'),
		),
		'card_parts' => array(
			array(array('item.name', 'item.status', 'item.status_label'), 1, 'card: its name and state'),
			array(array('item.when', 'item.next', 'label sa_next', 'item.repeats', 'item.joins', 'item.on_event', 'item.trigger_only'), 1, 'card: when it runs and next, with the icon of its kind'),
			array(array('item.staff', 'label sa_staff_only'), 1, 'card: someone who is not staff sees its name, time and state only'),
			array(array('item.conditions', 'item.action_text', 'item.follow'), 1, 'card: its conditions, step and follow-ups in words'),
			array(array('item.flow', 'item.flow.id'), 1, 'card of a converted action: Edit opens its flow on the canvas; no read-only drawing'),
			array(array('follow.on', 'follow.label', 'follow.text', 'follow.target'), 1, 'card: each follow-up, by outcome, in words, a chain icon when it starts another action'),
			array(array('item.starters', 'starter.name', 'label sa_started_by'), 1, 'card: the actions that start it (ws_scheduled_starters())'),
			array(array('item.last', 'item.last.status', 'item.last.label', 'item.last.time', 'item.last.text', 'label sa_last'), 1, 'card: its last run: how it went, when, what it did'),
			array(array('item.creator', 'item.creator.name', 'item.created', 'label sa_by', 'item.runs', 'label sa_runs'), 1, 'card: who wrote it and when, how many runs'),
			array(array('item.can_edit', 'item.id', 'data.item', 'label sa_run_now_confirm', 'label sa_run_now', 'label sa_cancel_confirm', 'label sa_cancel'), 1, 'card buttons: who may edit, the questions Run now and Cancel ask, the card drawn again'),
			array(array('data.item.history', 'run.status', 'run.label', 'run.time', 'run.text', 'label sa_no_history'), 1, 'card: History lines, how each run went, when, what it did'),
			array(array('run.by_hand', 'label sa_by_hand', 'run.source', 'label sa_started_by #2'), 1, 'card: History lines, by hand or started by another action'),
		),
		'list_parts' => array(
			array(array('label sa_title', 'label sa_help', 'label sa_empty'), 1, 'Scheduled actions section in Flows: title, help, an empty list; no New, a new rule is a flow'),
			array(array('label sa_all', 'label sa_state_active', 'label sa_state_paused', 'label sa_state_done', 'label sa_state_failed', 'label sa_state_cancelled'), 1, 'Scheduled actions section in Flows: the state chips'),
			array(array('label sa_in_channel', 'label sa_in_note', 'item.channel', 'item.channel.id', 'item.channel.name', 'item.note', 'item.note.id', 'item.note.name', 'item.message_id'), 1, 'Scheduled actions section in Flows: "in #channel / in note", linked to the card'),
			array(array('label sm_title', 'label sm_mine_help'), 1, '?view=scheduled keeps the Messages tab (scheduled messages are not moved)'),
			array(array('label fl_sa_moved'), 1, 'staff\'s Messages tab links to the Scheduled actions section of Flows'),
		),
		'card_places' => array(
			array(array('scheduledCard(item) in load() in scheduledList()'), 1, 'Scheduled actions section in Flows: the list'),
			array(array('scheduledCard(message.scheduled) in messageNode()'), 1, 'the card under its message in the channel, kept where written'),
			array(array('scheduledCard(item) in drawScheduled() in startNotes()'), 1, 'the note strip, kept where written'),
		),
		'routes' => array(
			array(array('route ws_scheduled_list', 'panel ws_scheduled_list', 'api ws_scheduled_list'), 1, 'Scheduled actions section in Flows: the list, by state'),
			array(array('route ws_scheduled_get', 'panel ws_scheduled_get', 'api ws_scheduled_get', 'route ws_scheduled_status', 'panel ws_scheduled_status', 'api ws_scheduled_status', 'route ws_scheduled_run_now', 'panel ws_scheduled_run_now', 'api ws_scheduled_run_now'), 1, 'card buttons: History, Pause/Resume and Cancel, Run now'),
			array(array('route ws_scheduled_messages', 'panel ws_scheduled_messages', 'api ws_scheduled_messages', 'route ws_scheduled_message_save', 'panel ws_scheduled_message_save', 'api ws_scheduled_message_save', 'route ws_scheduled_message_delete', 'panel ws_scheduled_message_delete', 'api ws_scheduled_message_delete', 'route ws_scheduled_message_send', 'panel ws_scheduled_message_send', 'api ws_scheduled_message_send'), 'form', 'scheduled messages\' routes: scheduled messages are not moved into Flows'),
		),
		'ai_drafts' => array(
			array(array('tool propose_scheduled_action', 'offered propose_scheduled_action', 'handled propose_scheduled_action'), 4, 'AI drafts on the canvas; until then Pinegrap AI drafts into the form, opened by @ai draft Open'),
			array(array('draft kind scheduled in ws_scheduled_draft_ai_store'), 4, 'AI drafts on the canvas: Pinegrap AI\'s drafts kept under its answer'),
			array(array('draft kind scheduled in ws_changes_map', 'draft kind scheduled in ws_scheduled_draft_change_entry', 'draft kind scheduled in ws_api_change_fields_present', 'draft kind scheduled in ws_api_change_record_present'), 4, 'AI drafts on the canvas: a draft of the kind scheduled waits on its card and in the API\'s list of drafts'),
			array(array('draft kind scheduled in ws_change_apply_check'), 4, 'AI drafts on the canvas: a draft is saved where it is opened, never applied as a change'),
			array(array('draft kind scheduled in ws_scheduled_draft_flow_open'), 4, 'AI drafts on the canvas (4c2): a draft card opens as a new flow (ws_flow_graph_from_scheduled()) and is marked applied with the flow'),
			array(array('draft kind scheduled in ws_changes_input'), 4, 'AI drafts on the canvas: an application\'s proposed scheduled action, rehearsed by the save\'s check, is kept as a flow draft card (ws_scheduled_draft_proposal_row(), C5b)'),
		),
		'links_in' => array(
			array(array('?view=scheduled'), 1, 'staff land on the Scheduled actions section of Flows, their Messages tab kept; others on Scheduled messages'),
			array(array('menu data-ws-nav="scheduled" Scheduled actions'), 1, 'the Workspace list of the main menu (ws_nav_groups()): staff get one Flows entry in its place'),
			array(array('menu data-ws-nav="scheduled" Scheduled messages'), 1, 'the Workspace list of the main menu: others keep Scheduled messages, and so do staff once Flows lists their actions'),
			array(array('menu data-ws-nav from the ws_nav_groups() key'), 1, 'workspace_side.js draws each place with data-ws-nav from its key, so the list has one source'),
			array(array('menu in place showScheduled'), 1, 'the channel screen opens the scheduled entry in place (app.sideHooks())'),
			array(array('tour scheduled #ws-menu-side [data-ws-nav="scheduled"]'), 1, 'tour step on the menu list\'s entry (optional: it drops silently), moved with it'),
			array(array('tour scheduled #ws-menu-side [data-ws-nav="flows"]'), 1, 'tour step on the Flows entry of the menu list once Flows lists staff\'s scheduled actions'),
			array(array('inbox scheduled_notice'), 1, 'lands on the card in its channel'),
			array(array('inbox scheduled_failed'), 1, 'lands on the card, or on ?view=scheduled without one'),
			array(array('inbox scheduled_message'), 1, 'a scheduled message that failed lands where scheduled messages are: others on ?view=scheduled; for staff the link has to name the Messages tab, since today ?view=scheduled opens their actions part on a fresh load and from slice 1 it opens Flows'),
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

		// A key given twice would hide a second place behind the first: a
		// reader numbers its repeats (workspace_flows_parity_numbered()).
		pg_assert_same(array(), array_values(array_unique(array_diff_assoc($keys, array_unique($keys)))), $row[0] . ' (' . $group . '): ' . $row[3] . ' gives each key once');

		pg_assert_same(array(), array_values(array_diff($keys, $mapped)),
			$row[0] . ' (' . $group . '): keys in ' . $row[3] . ' that workspace_flows_parity_table() does not map; give each the slice and box that keep it, or \'form\' and why');

		pg_assert_same(array(), array_values(array_diff($mapped, $keys)),
			$row[0] . ' (' . $group . '): keys of workspace_flows_parity_table() no longer in ' . $row[3] . '; take them out, or move them where the code has them now');
	}
}

// Twelve steps, the same in the form's check and in the run.
function test_workspace_flows_parity_thirteen_steps()
{
	$run = workspace_flows_parity_read_steps();
	$input = array_keys(workspace_flows_parity_cases(workspace_flows_parity_source('ws_scheduled_action_input')));

	pg_assert_same(13, count($run), 'ws_scheduled_do() has 13 steps');
	sort($run);
	sort($input);
	pg_assert_same($run, $input, 'ws_scheduled_action_input() checks the steps ws_scheduled_do() runs');
}

// Every placeholder the form lists is filled, and every one filled is listed
// (a count's threshold, {{count:<key>:<param>}}, is the count the form lists
// by its key).
function test_workspace_flows_parity_placeholders_are_filled()
{
	$listed = array();

	foreach (array_keys(ws_scheduled_placeholders()) as $token) {
		$listed[] = preg_match('/^\{\{count:/', $token) ? '{{count:<key>}}' : $token;
	}

	$filled = array_values(array_diff(workspace_flows_parity_filled_tokens(), array('{{count:<key>:<param>}}')));

	sort($listed);
	sort($filled);

	pg_assert_same($listed, $filled, 'ws_scheduled_placeholders() lists what ws_scheduled_fill(), ws_events_fill_values() and the functions they hand their values to fill');
}

// Every event an action can start on reaches the Workspace: one of the
// site's events it keeps from pg_announce() (pg_event_workspace_events()),
// or one of its own it keeps through ws_event() (ws_events_own()).
function test_workspace_flows_parity_events_reach_actions()
{
	$kept = array_merge(pg_event_workspace_events(), ws_events_own());

	pg_assert_same(array(), array_values(array_diff(workspace_flows_parity_read_events(), $kept)), 'the events of ws_events_catalog() are kept by pg_event_workspace_events() or ws_events_own()');
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

// ─── The readers themselves ─────────────────────────────────────────────

// A call that writes out its first parameter's default is the same place as
// one that leaves it out; any other first argument is part of its key.
function test_workspace_flows_parity_default_argument_is_no_new_place()
{
	pg_assert_same('', workspace_flows_parity_first_argument('ws_scheduled_run', 'WS_SCHEDULED_BATCH'), 'the default written as its constant');
	pg_assert_same('', workspace_flows_parity_first_argument('ws_scheduled_run', (string) WS_SCHEDULED_BATCH), 'the default written as its value');
	pg_assert_same('', workspace_flows_parity_first_argument('ws_scheduled_run', ''), 'no argument');
	pg_assert_same('20', workspace_flows_parity_first_argument('ws_scheduled_run', '20'), 'another batch');
	pg_assert_same("'workspace.task.completed'", workspace_flows_parity_first_argument('ws_event', "'workspace.task.completed'"), 'an argument with no default');
	pg_assert_same('(int) $id', workspace_flows_parity_first_argument('ws_scheduled_execute', "(int)\n\t\$id"), 'runs of whitespace made one');
}

// A cap written as a comparison of a text's length or a list's count with a
// number is read, whichever side the number is on, besides min(N, ...) and
// a cut to the first N.
function test_workspace_flows_parity_caps_read_comparisons()
{
	pg_assert_same(array('x length > 2000'), workspace_flows_parity_caps_in('x', 'if (($url === \'\') || (mb_strlen($url) > 2000)) {'), 'mb_strlen(...) > N');
	pg_assert_same(array('x length >= 301'), workspace_flows_parity_caps_in('x', 'if (strlen(trim((string) ($data[\'name\'] ?? \'\'))) >= 301) {'), 'strlen() around nested brackets');
	pg_assert_same(array('x count > 50'), workspace_flows_parity_caps_in('x', 'if (50 < count($ids)) {'), 'a number first is turned round');
	pg_assert_same(array('x min 720', 'x first 160', 'x count > 1', 'x first 160 #2'), workspace_flows_parity_caps_in('x', 'min(720, $a); mb_substr($n, 0, 160); if (count($given) > 1) {} mb_substr($m, 0, 160);'), 'every shape, a repeat numbered');
	pg_assert_same(array(), workspace_flows_parity_caps_in('x', 'if (mb_strlen($body) > WS_MESSAGE_MAX) {} if (count($a) === 0) {}'), 'a constant or an equality is no cap written as a number');
}

// The limits are read from every define() and const of the file, the value
// as PHP works it out; a define whose name is worked out by code throws.
function test_workspace_flows_parity_limits_read_any_define()
{
	pg_assert_same(
		array('WS_SCHEDULED_LOCK=600', 'WS_SCHEDULED_BATCH=5', 'WS_SCHEDULED_CHAIN_DEPTH=8'),
		workspace_flows_parity_limits_in("<?php\n define( \"WS_SCHEDULED_LOCK\" , 10 * 60 );\nconst WS_SCHEDULED_BATCH = 5;\ndefine('WS_OTHER', 1);\n// define('WS_SCHEDULED_GONE', 1);\ndefine('WS_SCHEDULED_CHAIN_DEPTH', 8);\n"),
		'spaces, double quotes, an expression and const are read; other names and comments are not'
	);

	$thrown = '';

	try {
		workspace_flows_parity_limits_in("<?php\ndefine('WS_SCHEDULED_' . \$name, 3);\n");
	} catch (RuntimeException $exception) {
		$thrown = $exception->getMessage();
	}

	pg_assert_contains('worked out by code', $thrown, 'a name worked out by code throws');
}

// The step-option reader follows a step's whole action only where it is an
// argument of its own to a Workspace function or a parameter of one; any
// other use throws, since the keys read through it would be missed.
function test_workspace_flows_parity_whole_action_uses()
{
	$followed = array(
		'ws_scheduled_notify($viewer, $action, $run); return $action[\'text\'];',
		'function ws_scheduled_step($scheduled, array &$action = array()) { return $action["text"]; }',
	);

	foreach ($followed as $code) {
		$thrown = '';

		try {
			workspace_flows_parity_whole_uses($code, 'action');
		} catch (RuntimeException $exception) {
			$thrown = $exception->getMessage();
		}

		pg_assert_same('', $thrown, 'followed: ' . $code);
	}

	$refused = array(
		'$step = $action; if (!empty($step[\'silent\'])) {}',
		'if (array_key_exists(\'silent\', $action)) {}',
		'extract($action);',
		'$step = (array) $action;',
		'foreach ($action as $key => $value) {}',
		'$send = function () use ($action) {};',
		'ws_scheduled_notify($viewer, array($action), $run);',
	);

	foreach ($refused as $code) {
		$thrown = '';

		try {
			workspace_flows_parity_whole_uses($code, 'action');
		} catch (RuntimeException $exception) {
			$thrown = $exception->getMessage();
		}

		pg_assert_contains('$action is used whole', $thrown, 'refused: ' . $code);
	}
}

// A new rule is made on the canvas: the Scheduled actions section puts New
// flow in the toolbar, and the list offers no New anywhere.
function test_workspace_flows_parity_scheduled_section_new_is_a_flow()
{
	$flow = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');
	$start = strpos($flow, 'function showScheduled(');

	pg_assert_true($start !== false, 'showScheduled() in workspace_flow.js');

	$body = substr($flow, $start, strpos($flow, "\n    }\n", $start) - $start);

	pg_assert_true(strpos($body, 'newFlowButton();') !== false, 'New flow in the section\'s toolbar');
	pg_assert_true(strpos($body, 'K.scheduledList(ui.scheduled);') !== false, 'the list without its New');

	$channels = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$start = strpos($channels, 'function scheduledList(body) {');

	pg_assert_true($start !== false);
	pg_assert_false(strpos(substr($channels, $start, strpos($channels, 'function load()', $start) - $start), 'sa_new_long') !== false, 'no New in the list');
}

// Hidden and converted anchors (slice 5): the starts by event and join read
// every anchor, hidden or converted, through ws_scheduled_actions_only();
// the filter that keeps hidden anchors out of the lists of scheduled actions
// is never among them, or a flow started on an event or a join would stop.
function test_workspace_flows_parity_anchor_filters()
{
	foreach (array('ws_events_start', 'ws_scheduled_joined', 'ws_events_awaited') as $function) {
		$source = workspace_flows_parity_source($function);

		pg_assert_contains('ws_scheduled_actions_only()', $source, $function);
		pg_assert_false(strpos($source, 'ws_scheduled_anchors_out') !== false, $function);
	}

	foreach (array('ws_scheduled_list', 'ws_scheduled_targets') as $function) {
		pg_assert_contains('ws_scheduled_anchors_out()', workspace_flows_parity_source($function), $function);
	}

	pg_assert_false(strpos(workspace_flows_parity_source('ws_scheduled_actions_only'), 'anchor') !== false);
}
