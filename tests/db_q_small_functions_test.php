<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Static checks on the calendar, contact group, visitor and small function
 * files of the db_q() tail (calendar views and screens, contact groups,
 * visitor screens, developer lock, file name check, robots.txt, record
 * files, design template install, security settings, the WAF rescue, SMS,
 * mail queue, sign-in, consent log, passkeys, form subscription, backup,
 * AI crawlers, MCP and API discovery, marketplace order import, idempotency
 * and the ERP ledger and audit): their raw queries with values run through
 * db_q() placeholders, not escape()/e() concatenated into the SQL text, and
 * every db_q() call passes one value per '?'. The raw queries left are
 * those without values, the generic runners (pg_record_file_rows(),
 * erp_query()) and those completed by a clause built elsewhere, which may
 * hold a '?': $where_calendar_id, the list sort $order_by, the visitor text
 * joins, the visitor address clear and the subscription writes over $where.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Each mysqli_query() / db_q() call of a file with the text of its
// arguments, its first argument and, when the SQL is $query, the text of
// that variable's last assignment before the call.
function db_q_small_functions_test_calls($path)
{
	$tokens = token_get_all(file_get_contents(PG_FUNCTIONS_DIR . '/' . $path));
	$count = count($tokens);
	$assignments = array();
	$calls = array();

	for ($i = 0; $i < $count; $i++) {
		$token = $tokens[$i];

		if (!is_array($token)) {
			continue;
		}

		if ($token[0] === T_VARIABLE && preg_match('/^\$(query|query2|db_query)$/', $token[1])) {
			$j = $i + 1;

			while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
				$j++;
			}

			if ($j < $count && $tokens[$j] === '=') {
				$text = '';

				for ($k = $j + 1; $k < $count && $tokens[$k] !== ';'; $k++) {
					$text .= is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
				}

				$assignments[$token[1]] = $text;
			}

			continue;
		}

		if ($token[0] !== T_STRING || !in_array($token[1], array('mysqli_query', 'db_q'), true)) {
			continue;
		}

		$j = $i + 1;

		while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
			$j++;
		}

		if ($j >= $count || $tokens[$j] !== '(') {
			continue;
		}

		// Arguments split at the commas of the call's own level.
		$depth = 0;
		$text = '';
		$arguments = array('');

		for ($k = $j; $k < $count; $k++) {
			$piece = is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];

			if ($piece === '(' || $piece === '[') {
				$depth++;
			} elseif ($piece === ')' || $piece === ']') {
				$depth--;
			}

			$text .= $piece;

			if ($depth === 0) {
				break;
			}

			if ($depth === 1 && $piece === ',') {
				$arguments[] = '';
			} elseif ($k > $j) {
				$arguments[count($arguments) - 1] .= $piece;
			}
		}

		$arguments = array_map('trim', $arguments);
		$sql = $text;

		$variable = '';

		if (preg_match('/^\(\s*(db::\$con\s*,\s*)?(\$(query|query2|db_query))\b/', $text, $match) && isset($assignments[$match[2]])) {
			$variable = $match[2];
			$sql .= $assignments[$variable];
		}

		$calls[] = array(
			'line' => $token[2],
			'call' => $token[1],
			'text' => $sql,
			'sql' => ($variable !== '' && $arguments[0] === $variable) ? $assignments[$variable] : $arguments[0],
			'values' => isset($arguments[1]) ? $arguments[1] : '',
		);
	}

	return $calls;
}

// Clause variables a raw query may still be completed by.
function db_q_small_functions_test_built($text)
{
	return (bool) preg_match('/\$(where|where_calendar_id|order_by)\b|pg_visitor_text_joins\s*\(|pg_visitor_ip_clear_sql\s*\(/', $text);
}

// File => the least number of queries it runs through db_q().
function db_q_small_functions_test_files()
{
	return array(
		'get_calendar_view_screen_content.php' => 1,
		'get_calendar_event_view_screen_content.php' => 1,
		'calendars.php' => 0,
		'add_calendar.php' => 2,
		'add_calendar_event_location.php' => 1,
		'includes/forms/calendar_event_location_form.php' => 1,
		'view_calendars.php' => 0,
		'view_calendar_event_locations.php' => 0,
		'add_contact_group.php' => 1,
		'view_contact_groups.php' => 0,
		'organize_contacts.php' => 0,
		'view_visitor_reports.php' => 0,
		'view_visitor.php' => 0,
		'developer_lock.php' => 2,
		'check_if_file_exists.php' => 1,
		'get_robots.txt.php' => 0,
		'includes/record_files.php' => 0,
		'includes/install_design_template.php' => 1,
		'includes/settings/security.save.php' => 1,
		'includes/settings/registry.php' => 0,
		'includes/fn/waf_rescue.php' => 1,
		'includes/fn/visitor_ip.php' => 0,
		'includes/fn/sms.php' => 1,
		'includes/fn/signin.php' => 1,
		'includes/fn/privacy.php' => 1,
		'includes/fn/passkeys.php' => 1,
		'includes/fn/mail_queue.php' => 1,
		'includes/fn/form_subscription.php' => 0,
		'includes/fn/backup.php' => 0,
		'includes/fn/ai_crawlers.php' => 1,
		'includes/mcp/protocol.php' => 0,
		'includes/api/resources/discovery.php' => 0,
		'includes/api/outbound/orders.php' => 1,
		'includes/api/idempotency.php' => 1,
		'includes/erp/ledger.php' => 0,
		'includes/erp/audit.php' => 1,
	);
}

function test_db_q_small_functions_value_queries_use_db_q()
{
	foreach (db_q_small_functions_test_files() as $path => $minimum) {
		$db_q = 0;

		foreach (db_q_small_functions_test_calls($path) as $call) {
			if ($call['call'] === 'db_q') {
				$db_q++;

				pg_assert_false(
					(bool) preg_match('/(?<![\w>$])(escape|e|mysqli_real_escape_string)\s*\(/', $call['sql']),
					$path . ' line ' . $call['line'] . ' concatenates an escaped value into its db_q() SQL');
				continue;
			}

			if (db_q_small_functions_test_built($call['text'])) {
				continue;
			}

			pg_assert_false(
				(bool) preg_match('/(?<![\w>$])(escape|e|mysqli_real_escape_string)\s*\(|\'"\s*\.|\'\$[a-z_]/i', $call['text']),
				$path . ' line ' . $call['line'] . ' writes a value into a raw query');
		}

		pg_assert_true($db_q >= $minimum, $path . ' runs ' . $db_q . ' queries through db_q()');
	}
}

// One value per '?': a literal array(...) of values has as many top-level
// elements as the SQL text has placeholders.
function test_db_q_small_functions_values_fit_placeholders()
{
	foreach (array_keys(db_q_small_functions_test_files()) as $path) {
		foreach (db_q_small_functions_test_calls($path) as $call) {
			if ($call['call'] !== 'db_q' || strpos($call['values'], 'array(') !== 0) {
				continue;
			}

			$tokens = token_get_all('<?php ' . $call['values'] . ';');
			$depth = 0;
			$elements = 1;
			$trailing = false;

			foreach ($tokens as $token) {
				$piece = is_array($token) ? $token[1] : $token;

				if (is_array($token) && $token[0] === T_WHITESPACE) {
					continue;
				}

				if ($piece === '(' || $piece === '[') {
					$depth++;
				} elseif ($piece === ')' || $piece === ']') {
					// A comma right before the closing parenthesis adds no value.
					if ($depth === 1 && $trailing) {
						$elements--;
					}
					$depth--;
				} elseif ($piece === ',' && $depth === 1) {
					$elements++;
					$trailing = true;
					continue;
				}

				$trailing = false;
			}

			// Only the '?' of the SQL text count: a PHP ternary or '??' adds none.
			$placeholders = 0;

			foreach (token_get_all('<?php ' . $call['sql'] . ';') as $token) {
				if (is_array($token) && in_array($token[0], array(T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE), true)) {
					$placeholders += substr_count($token[1], '?');
				}
			}

			pg_assert_same(
				$placeholders,
				$elements,
				$path . ' line ' . $call['line'] . ' passes ' . $elements . ' values for its placeholders');
		}
	}
}

function test_db_q_small_functions_binds_as_before()
{
	$escape = function ($value) {
		return addcslashes($value, "\\'\"");
	};

	// The consent log and passkey rows: one quoted value per column, an int
	// quoted as before, null as ''.
	$row = array('channel' => 'email', 'contact_id' => 5, 'note' => null, 'name' => "O'Neil");
	pg_assert_same(
		"INSERT INTO consent_log (channel, contact_id, note, name) VALUES ('email', '5', '', 'O\\'Neil')",
		pg_db_bind("INSERT INTO consent_log (" . implode(', ', array_keys($row)) . ") VALUES (" . implode(', ', array_fill(0, count($row), '?')) . ")", array_map('strval', array_values($row)), $escape));

	// An SMS without a reference, an empty audit description and a missing
	// detail keep NULL; with one they keep the quoted text.
	pg_assert_same(
		"INSERT INTO t (a, reference) VALUES ('7', NULL)",
		pg_db_bind('INSERT INTO t (a, reference) VALUES (?, ?)', array((string) (int) '7', null), $escape));
	pg_assert_same(
		"INSERT INTO t (a, reference) VALUES ('7', 'r-1')",
		pg_db_bind('INSERT INTO t (a, reference) VALUES (?, ?)', array((string) (int) '7', 'r-1'), $escape));

	// The WAF unban keeps its list type literal after the placeholder.
	pg_assert_same(
		"DELETE FROM banned_ip_addresses\n             WHERE ip_address = '1.2.3.4' AND list_type = 'block'",
		pg_db_bind("DELETE FROM banned_ip_addresses\n             WHERE ip_address = ?" . " AND list_type = 'block'", array('1.2.3.4'), $escape));
}
