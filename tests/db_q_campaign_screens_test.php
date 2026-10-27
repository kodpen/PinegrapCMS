<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Static checks on the e-mail campaign list and delete screens, the
 * submitted form delete, the e-mail a friend, affiliate, custom form,
 * account profile, billing, confirmation and entrance screens, the order
 * preview gift card queries and the password reset/change screens: their
 * raw queries with values run through db_q() placeholders, not escape()/e()
 * concatenated into the SQL text, every db_q() call passes one value per
 * '?', and no '?' stands inside a quoted SQL literal. The raw queries left
 * are those without values, the profile list completed by $where, and the
 * password UPDATEs completed by pg_password_changed_sql().
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
// arguments, its first argument and, when that argument is a variable, the
// text of the variable's last assignment before the call.
function db_q_campaign_screens_test_calls($path)
{
	$tokens = token_get_all(file_get_contents(PG_FUNCTIONS_DIR . '/' . $path));
	$count = count($tokens);
	$assigned = array();
	$calls = array();

	for ($i = 0; $i < $count; $i++) {
		$token = $tokens[$i];

		if (!is_array($token)) {
			continue;
		}

		if ($token[0] === T_VARIABLE) {
			$j = $i + 1;

			while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
				$j++;
			}

			if ($j < $count && $tokens[$j] === '=') {
				$text = '';

				for ($k = $j + 1; $k < $count && $tokens[$k] !== ';'; $k++) {
					$text .= is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
				}

				$assigned[$token[1]] = $text;
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

		if ($token[1] === 'mysqli_query') {
			array_shift($arguments);
		}

		$sql = $arguments[0];

		if (preg_match('/^\$\w+$/', $sql) && isset($assigned[$sql])) {
			$sql = $assigned[$sql];
		}

		$calls[] = array(
			'line' => $token[2],
			'call' => $token[1],
			'text' => $text . $sql,
			'sql' => $sql,
			'values' => isset($arguments[1]) ? $arguments[1] : '',
		);
	}

	return $calls;
}

function db_q_campaign_screens_test_files()
{
	return array(
		'delete_submitted_forms.php' => 5,
		'delete_email_campaigns.php' => 4,
		'view_email_campaigns.php' => 2,
		'view_email_campaign_history.php' => 2,
		'view_email_campaign.php' => 3,
		'view_email_campaign_profiles.php' => 0,
		'get_email_a_friend_screen_content.php' => 3,
		'email_a_friend.php' => 3,
		'get_affiliate_sign_up_form_screen_content.php' => 2,
		'get_custom_form_screen_content.php' => 2,
		'edit_form_item_view.php' => 3,
		'preview_form.php' => 1,
		'get_my_account_profile.php' => 2,
		'get_billing_information.php' => 1,
		'reset_password.php' => 1,
		'registration_entrance.php' => 0,
		'membership_entrance.php' => 0,
		'order_preview.php' => 2,
		'order_history_delete_order.php' => 2,
		'remove_gift_card_from_order.php' => 1,
		'get_registration_confirmation_screen_content.php' => 1,
		'get_membership_confirmation_screen_content.php' => 1,
		'get_affiliate_welcome_screen_content.php' => 1,
		'change_password.php' => 0,
	);
}

// Clause variables a raw query may still be completed by.
function db_q_campaign_screens_test_built($text)
{
	return (bool) preg_match('/\$where\b|pg_password_changed_sql\(\)/', $text);
}

// The SQL text of a call's string literals, PHP code between them left out.
function db_q_campaign_screens_test_literals($sql)
{
	$text = '';

	foreach (token_get_all('<?php ' . $sql . ';') as $token) {
		if (is_array($token) && in_array($token[0], array(T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE), true)) {
			$text .= $token[1];
		}
	}

	return $text;
}

function test_db_q_campaign_screens_value_queries_use_db_q()
{
	foreach (db_q_campaign_screens_test_files() as $path => $minimum) {
		$db_q = 0;

		foreach (db_q_campaign_screens_test_calls($path) as $call) {
			if ($call['call'] === 'db_q') {
				$db_q++;

				pg_assert_false(
					(bool) preg_match('/(?<![\w>$])(escape|e)\s*\(/', $call['sql']),
					$path . ' line ' . $call['line'] . ' concatenates an escaped value into its db_q() SQL');
				continue;
			}

			if (db_q_campaign_screens_test_built($call['text'])) {
				continue;
			}

			pg_assert_false(
				(bool) preg_match('/(?<![\w>$])(escape|e)\s*\(|\'"\s*\.|\'\$[a-z_]/i', $call['text']),
				$path . ' line ' . $call['line'] . ' writes a value into a raw query');
		}

		pg_assert_true($db_q >= $minimum, $path . ' runs ' . $db_q . ' queries through db_q()');
	}
}

// One value per '?': a literal array(...) of values has as many top-level
// elements as the SQL text has placeholders, and no '?' is quoted.
function test_db_q_campaign_screens_values_fit_placeholders()
{
	foreach (array_keys(db_q_campaign_screens_test_files()) as $path) {
		foreach (db_q_campaign_screens_test_calls($path) as $call) {
			if ($call['call'] !== 'db_q') {
				continue;
			}

			$literals = db_q_campaign_screens_test_literals($call['sql']);

			pg_assert_false(
				(bool) preg_match('/\'\?|\?\'/', $literals),
				$path . ' line ' . $call['line'] . ' quotes a placeholder');

			pg_assert_same(
				0,
				strpos($call['values'], 'array('),
				$path . ' line ' . $call['line'] . ' passes its values as a literal array');

			$depth = 0;
			$elements = 1;

			foreach (token_get_all('<?php ' . $call['values'] . ';') as $token) {
				$piece = is_array($token) ? $token[1] : $token;

				if ($piece === '(' || $piece === '[') {
					$depth++;
				} elseif ($piece === ')' || $piece === ']') {
					$depth--;
				} elseif ($piece === ',' && $depth === 1) {
					$elements++;
				}
			}

			// Only the '?' of the SQL text count: a PHP ternary or '??' adds none.
			pg_assert_same(
				substr_count($literals, '?'),
				$elements,
				$path . ' line ' . $call['line'] . ' passes ' . $elements . ' values for its placeholders');
		}
	}
}

function test_db_q_campaign_screens_binds_as_before()
{
	$escape = function ($value) {
		return addcslashes($value, "\\'\"");
	};

	// The order preview's gift card row: a Givex balance is a float, written
	// quoted as PHP turns it into text, before and after.
	$old_balance = 12.5 * 100;
	$givex = 1;
	pg_assert_same(
		"VALUES ('', '9', 'ABC123', '$old_balance', '$givex')",
		pg_db_bind('VALUES (?, ?, ?, ?, ?)', array((string) '', (string) 9, (string) 'ABC123', (string) $old_balance, (string) $givex), $escape));
	// A username with a quote is escaped once.
	pg_assert_same(
		"SELECT user_email FROM user WHERE user_username = 'o\\'brien'",
		pg_db_bind('SELECT user_email FROM user WHERE user_username = ?', array((string) "o'brien"), $escape));
	// A missing session order id stays an empty quoted string.
	pg_assert_same(
		"DELETE FROM applied_gift_cards WHERE (id = '3') AND (order_id = '')",
		pg_db_bind('DELETE FROM applied_gift_cards WHERE (id = ?) AND (order_id = ?)', array((string) '3', (string) null), $escape));
}
