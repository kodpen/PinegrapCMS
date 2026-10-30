<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * get_absolute_time() returns a <time> element unless it is asked for
 * 'format' => 'plain_text'. A date passed through h(), a lang() var or a
 * string escaped later must be plain text, or the escaped tag shows as
 * text on the screen. These checks pin the source of those calls.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Files that still print a date element through h(); the check skips them.
// view_order.php: two arrival and delivery dates.
// api_settings.php: a device's "since", an application's created date and
// an OAuth client's "Registered on".
function absolute_time_text_skipped_files()
{
	return array('view_order.php', 'api_settings.php');
}

function absolute_time_text_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// Returns the source with its comments blanked out, line numbers kept.
function absolute_time_text_code($source)
{
	$code = '';
	foreach (token_get_all($source) as $token) {
		if (is_array($token)) {
			$code .= in_array($token[0], array(T_COMMENT, T_DOC_COMMENT), true) ? preg_replace('/[^\n]/', ' ', $token[1]) : $token[1];
		} else {
			$code .= $token;
		}
	}
	return $code;
}

// Returns the argument list of the call whose opening parenthesis is at
// $open, up to its balanced closing parenthesis.
function absolute_time_text_arguments($source, $open)
{
	$depth = 0;
	$length = strlen($source);
	for ($i = $open; $i < $length; $i++) {
		if ($source[$i] === '(') {
			$depth++;
		} elseif ($source[$i] === ')') {
			$depth--;
			if ($depth === 0) {
				return substr($source, $open, $i - $open + 1);
			}
		}
	}
	return substr($source, $open);
}

// Tells whether the get_absolute_time( at $offset is escaped where it
// stands: an argument of h(), or a lang() var ('vars' => the call, or an
// item of the 'vars' => array(...) list).
function absolute_time_text_is_escaped($source, $offset)
{
	if (preg_match("/(?:\\bh\\(|'vars'\\s*=>)\\s*\$/", substr($source, max(0, $offset - 200), min(200, $offset)))) {
		return true;
	}
	$depth = 0;
	for ($i = $offset - 1; $i >= 0; $i--) {
		if ($source[$i] === ')') {
			$depth++;
		} elseif ($source[$i] === '(') {
			if ($depth === 0) {
				$before = substr($source, max(0, $i - 200), min(200, $i));
				return (bool) preg_match("/(?:\\bh|'vars'\\s*=>\\s*array)\\s*\$/", $before);
			}
			$depth--;
		}
	}
	return false;
}

function test_absolute_time_text_escaped_calls_take_plain_text()
{
	$root = PG_FUNCTIONS_DIR;
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
	$checked = 0;
	foreach ($iterator as $file) {
		if ($file->getExtension() !== 'php') {
			continue;
		}
		$relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
		if (strpos($relative, 'data/') === 0 || strpos($relative, 'includes/local/') === 0 || in_array($relative, absolute_time_text_skipped_files(), true)) {
			continue;
		}
		$source = (string) file_get_contents($file->getPathname());
		if (strpos($source, 'get_absolute_time(') === false) {
			continue;
		}
		$source = absolute_time_text_code($source);
		$offset = 0;
		while (($offset = strpos($source, 'get_absolute_time(', $offset)) !== false) {
			$open = $offset + strlen('get_absolute_time');
			if (($offset === 0 || !preg_match('/[\w>$]/', $source[$offset - 1])) && absolute_time_text_is_escaped($source, $offset)) {
				$line = substr_count(substr($source, 0, $offset), "\n") + 1;
				pg_assert_contains("'format' => 'plain_text'", absolute_time_text_arguments($source, $open), $relative . ':' . $line . ' escapes a get_absolute_time() element');
				$checked++;
			}
			$offset = $open;
		}
	}
	pg_assert_true($checked > 5, 'escaped get_absolute_time() calls were found');
}

function test_absolute_time_text_dates_escaped_later_take_plain_text()
{
	$pins = array(
		'includes/fn/geo.php' => array("get_absolute_time(array('timestamp' => (int) \$status['database']['built_at'], 'type' => 'date', 'format' => 'plain_text'))"),
		'chat.php' => array(
			"get_absolute_time(array('timestamp' => \$timestamp, 'type' => 'date', 'format' => 'plain_text'))",
			"get_absolute_time(array('timestamp' => \$timestamp, 'format' => 'plain_text'))",
		),
		'print_order.php' => array("\$output_arrival_date = get_absolute_time(array('timestamp' => strtotime(\$ship_tos[\$key]['arrival_date']), 'type' => 'date', 'format' => 'plain_text'));"),
		'includes/fn/widgets_order_preview.php' => array("get_absolute_time(array('timestamp' => strtotime((string)\$r['arrival_date']), 'type' => 'date', 'size' => 'long', 'format' => 'plain_text'))"),
		'includes/fn/widgets_shipping_method.php' => array("get_absolute_time(array('timestamp' => strtotime((string)\$recipient['arrival_date']), 'type' => 'date', 'size' => 'long', 'format' => 'plain_text'))"),
		'view_visitor_report.php' => array("get_absolute_time(array('timestamp' => \$row['start_timestamp'], 'type' => 'date and time', 'timezone_type' => 'site', 'format' => 'plain_text'))"),
	);
	foreach ($pins as $file => $needles) {
		$source = absolute_time_text_source($file);
		foreach ($needles as $needle) {
			pg_assert_contains($needle, $source, $file);
		}
	}
}

function test_absolute_time_text_sessions_print_the_element()
{
	pg_assert_same(false, strpos(absolute_time_text_source('view_sessions.php'), 'h(get_absolute_time('), 'view_sessions.php escapes a date element');
}

function test_absolute_time_text_plain_text_has_no_markup()
{
	if (!defined('TIME_FORMAT')) {
		define('TIME_FORMAT', 'twenty_four_hours');
	}
	if (!defined('DATE_FORMAT')) {
		define('DATE_FORMAT', 'day_month');
	}
	$plain = get_absolute_time(array('timestamp' => 1760000000, 'type' => 'date', 'format' => 'plain_text'));
	pg_assert_true($plain !== '' && strpos($plain, '<') === false, 'plain_text returns text only');
	pg_assert_same(0, strpos(get_absolute_time(array('timestamp' => 1760000000, 'type' => 'date')), '<time datetime='), 'the default is a <time> element');
}
