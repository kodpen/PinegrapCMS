<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Scheduled actions move into Flows with nothing lost: every key a scheduled
 * action has (its "when", the events, the conditions, the twelve steps and
 * each option they read, the webhook's body, the outcomes, the chain limits,
 * the placeholders, the states, the news it gives, how it writes a record,
 * the buttons of its card, the ways into its form and the links to it, the
 * inbox kinds the API shows) is listed in the parity table below with the
 * merge slice and the box that keeps it, or 'form' with the reason it stays
 * in the form. The keys are read from the code - by calling the functions
 * that run without a database, and from their source where a table probe or
 * a constant hides some (tests/helpers/source_reader.php) - so a key added
 * to the code and not to the table fails here, and so does a key the table
 * lists that the code no longer has.
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
// call a list of its arguments as written (runs of whitespace made one).
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
		$calls[] = $args;
	}

	return $calls;
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
	preg_match_all("/^\s*(?:\\\$catalog\['([a-z_.]+)'\]\s*=|'([a-z_.]+)'\s*=>)\s*array\('label'([^\n]*)$/m", workspace_flows_parity_source('ws_events_catalog'), $matches, PREG_SET_ORDER);

	$out = array();

	foreach ($matches as $match) {
		$event = ($match[1] !== '') ? $match[1] : $match[2];
		preg_match("/'filter'\s*=>\s*'([a-z_]*)'/", $match[3], $filter);
		$out[$event] = array((string) ($filter[1] ?? ''), (bool) preg_match("/'channel'\s*=>\s*true/", $match[3]));
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
	$skipped = workspace_flows_parity_list_after($source, 'in_array($type, ');
	$out = array();

	foreach (pg_test_ws_change_types() as $type => $info) {
		if (in_array($type, $skipped, true)) {
			continue;
		}

		$out[$type] = array();

		foreach ($info['fields'] as $name => $field) {
			if ($field[0] !== 'answers') {
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
	preg_match_all("/^\s*(?:\\\$metrics\['([a-z_]+)'\]\s*=|'([a-z_]+)'\s*=>)\s*array\('label'/m", workspace_flows_parity_source('ws_scheduled_metrics'), $matches, PREG_SET_ORDER);

	$out = array();

	foreach ($matches as $match) {
		$out[] = ($match[1] !== '') ? $match[1] : $match[2];
	}

	return $out;
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

// The keys each step reads from its action: where the form's input is
// checked (ws_scheduled_action_input()), where it runs (ws_scheduled_do())
// and in the function the run hands it to: step.key.
function workspace_flows_parity_read_step_options()
{
	$input = workspace_flows_parity_cases(workspace_flows_parity_source('ws_scheduled_action_input'));
	$out = array();

	foreach (workspace_flows_parity_cases(workspace_flows_parity_source('ws_scheduled_do')) as $step => $code) {
		$keys = array_merge(workspace_flows_parity_reads($input[$step] ?? '', 'action'), workspace_flows_parity_reads($code, 'action'));

		preg_match_all('/\b(ws_scheduled_[a-z_]+)\(([^()]*)\)/', $code, $calls, PREG_SET_ORDER);

		foreach ($calls as $call) {
			if (preg_match('/\$action\b/', $call[2])) {
				$keys = array_merge($keys, workspace_flows_parity_reads(workspace_flows_parity_source($call[1]), 'action'));
			}
		}

		foreach (array_unique($keys) as $key) {
			$out[] = $step . '.' . $key;
		}
	}

	return $out;
}

// What a change step can do: kind.verb, the change catalogue's kinds but the
// ones ws_scheduled_change_skipped() names, and of their actions update and
// delete (read from source, as the change catalogue is).
function workspace_flows_parity_read_change_kinds()
{
	$verbs = workspace_flows_parity_list_after(workspace_flows_parity_cases(workspace_flows_parity_source('ws_scheduled_action_input'))['change'], "array_intersect(\$types[\$record_type]['actions'], ");
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

// How a run tells about itself (ws_scheduled_report()): the functions it
// writes with; and that the runs of an action are never deleted.
function workspace_flows_parity_read_news()
{
	preg_match_all('/(?<!function )\b(ws_[a-z_]+|log_activity)\s*\(/', workspace_flows_parity_source('ws_scheduled_report'), $calls);

	$out = array_values(array_diff(array_unique($calls[1]), array('ws_person_name')));

	if (!preg_match('/DELETE\s+FROM\s+ws_scheduled_runs/i', workspace_flows_parity_file('includes/workspace/scheduled.php'))) {
		$out[] = 'ws_scheduled_runs kept';
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

// The ways a person is brought to scheduled actions: inbox lines
// (ws_inbox_describe()), the ?view=scheduled address, the rail's entry, the
// tour's step.
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

	if (preg_match("/'bi-alarm': 'scheduled'/", $js)) {
		$out[] = 'rail data-ws-nav="scheduled"';
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
		'step_options'        => array('Steps', array(3), 'workspace_flows_parity_read_step_options', 'the $action keys of ws_scheduled_action_input(), ws_scheduled_do() and the functions it hands a step to'),
		'change_kinds'        => array('Steps', array(3), 'workspace_flows_parity_read_change_kinds', 'the change catalogue as written, minus ws_scheduled_change_skipped(), update and delete'),
		'digest_filters'      => array('Steps', array(3), 'workspace_flows_parity_read_digest_filters', 'ws_scheduled_digest_filters()'),
		'webhook_formats'     => array('Webhook body', array(3, 5), 'workspace_flows_parity_read_webhook_formats', 'ws_scheduled_webhook_formats()'),
		'webhook_body'        => array('Webhook body', array(3, 5), 'workspace_flows_parity_read_webhook_body', 'the $payload of each format in ws_scheduled_webhook()'),
		'outcomes'            => array('Follow-ups', array(3), 'workspace_flows_parity_read_outcomes', 'ws_scheduled_outcomes()'),
		'limits'              => array('Chains', array(3), 'workspace_flows_parity_read_limits', 'the WS_SCHEDULED_* constants of scheduled.php'),
		'limit_uses'          => array('Chains', array(3), 'workspace_flows_parity_read_limit_uses', 'the Workspace functions that name a chain limit or ws_event_depth()'),
		'tokens'              => array('Tokens', array(3), 'workspace_flows_parity_read_tokens', 'ws_scheduled_placeholders(), ws_scheduled_fill(), ws_events_fill_values()'),
		'states'              => array('States', array(5), 'workspace_flows_parity_read_states', 'ws_scheduled_states()'),
		'news'                => array('News', array(5), 'workspace_flows_parity_read_news', 'what ws_scheduled_report() calls; no DELETE of ws_scheduled_runs in scheduled.php'),
		'record_writes'       => array('Record writes', array(3), 'workspace_flows_parity_read_record_writes', 'ws_scheduled_change()'),
		'card_buttons'        => array('Screen', array(1), 'workspace_flows_parity_read_card_buttons', 'the button() labels of scheduledCard() in workspace.js'),
		'templates'           => array('Screen', array(1), 'workspace_flows_parity_read_templates', 'saTemplates() in workspace.js and templates() in workspace_events.js'),
		'entry_points'        => array('Entry points', array(1), 'workspace_flows_parity_read_entry_points', 'the scheduledForm() calls of workspace.js'),
		'links_in'            => array('Links in', array(1), 'workspace_flows_parity_read_links_in', 'ws_inbox_describe(), workspace.js, ws_tour_steps()'),
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
		'links_in' => array(
			array(array('?view=scheduled'), 1, 'staff land on the Scheduled actions section of Flows, their Messages tab kept; others on Scheduled messages'),
			array(array('rail data-ws-nav="scheduled"'), 1, 'staff get one Flows rail entry; others keep Scheduled messages'),
			array(array('tour scheduled #ws-root [data-ws-nav="scheduled"]'), 1, 'tour step on the rail entry (optional: it drops silently)'),
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
