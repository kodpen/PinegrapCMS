<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the typed fill of system widget tokens
 * (includes/fn/widgets_fill.php) and the fills that use it: a token's
 * %%...%% suffix is a text format (capitals, decimals, shortening, yes/no,
 * line breaks) or a date format, it never reaches the page, a template
 * without a suffix fills byte for byte as before, and the editor's Format
 * row offers what the server reads.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The constants prepare_form_data_for_output() and get_relative_time() read
// on a request; other test files define the same ones the same way.
function _widget_fill_constants()
{
	if (!defined('DATE_FORMAT')) {
		define('DATE_FORMAT', 'day_month');
	}
	if (!defined('TIME_FORMAT')) {
		define('TIME_FORMAT', 'twenty_four_hours');
	}
}

function _widget_fill_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// One function of style_designer.js, from its line to its closing brace at
// the same indentation; '' when it is not found.
function _widget_fill_js_function($source, $name)
{
	$from = strpos($source, "\n    function " . $name . '(');
	if ($from === false) {
		return '';
	}
	$to = strpos($source, "\n    }\n", $from);
	return ($to === false) ? '' : substr($source, $from, $to + 7 - $from);
}

// The source of a PHP function, read through reflection.
function _widget_fill_php_function($name)
{
	$fn = new ReflectionFunction($name);
	$lines = file($fn->getFileName());
	return implode('', array_slice($lines, $fn->getStartLine() - 1, $fn->getEndLine() - $fn->getStartLine() + 1));
}

// The fills before this change: strtr() for _pg_sw_fill_tokens(), the
// longest-first str_replace() loop and the sweep for _pg_member_apply_tokens()
// and the custom form.
function _widget_fill_old_strtr($html, $values)
{
	$map = array();
	foreach ($values as $k => $v) {
		$map['^^' . $k . '^^'] = (string) $v;
	}
	return strtr($html, $map);
}

function _widget_fill_old_member($html, $values)
{
	$keys = array_keys($values);
	usort($keys, function ($a, $b) { return strlen($b) - strlen($a); });
	foreach ($keys as $k) {
		$html = str_replace('^^' . $k . '^^', (string) $values[$k], $html);
	}
	return pg_sw_sweep_tokens($html);
}

// -- a suffix never reaches the page ------------------------------------------

function test_widget_fill_suffix_is_not_printed()
{
	$html = '<p>^^__d^^%%Y-m-d%%</p>';
	$typed = array('__d' => array('type' => 'date', 'data' => '2026-11-03'));

	pg_assert_same('<p>3 Kasım</p>', _pg_sw_fill_tokens($html, array('__d' => '3 Kasım')), 'untyped: the value, the suffix gone');
	pg_assert_same('<p>2026-11-03</p>', _pg_sw_fill_tokens($html, array('__d' => '3 Kasım'), $typed), 'typed: the date in the format');
	pg_assert_same('<p>3 Kasım</p>', _pg_member_apply_tokens($html, array('__d' => '3 Kasım')), 'member widgets');
	pg_assert_same('<p></p>', _pg_member_apply_tokens($html, array('__x' => 'y')), 'an unanswered token goes with its suffix');
}

// The custom form fills through the member widgets' fill, so it reads the
// same suffixes and never prints one.
function test_widget_fill_custom_form_uses_member_fill()
{
	$source = _widget_fill_php_function('_render_system_widget_custom_form');
	pg_assert_contains('return _pg_member_apply_tokens($rendered, $values);', $source);
	pg_assert_false(strpos($source, "str_replace('^^' . \$k . '^^'") !== false, 'no fill of its own');
}

// -- byte for byte without a suffix -------------------------------------------

function test_widget_fill_byte_identical_without_suffix()
{
	$templates = array(
		'<a href="^^__url^^" title="^^__t^^">^^__t^^ &amp; ^^__missing^^</a><i>^^__x^^</i>',
		'<img src="^^__src^^" alt="^^__t^^"><span data-x="^^__t^^">^^__url^^^^__t^^</span>',
		'^^^__t^^^ %%d.m.Y%% ^^__t^^ %%x%%',
		'',
		'<p>no token at all</p>',
	);
	$values = array(
		'__url'   => '/a?b=1&amp;c=2',
		'__t'     => 'A &quot;B&quot; &lt;i&gt;',
		'__x'     => '^^__t^^',
		'__src'   => '',
		'__t_two' => 'longer key',
	);
	foreach ($templates as $i => $html) {
		pg_assert_same(_widget_fill_old_strtr($html, $values), _pg_sw_fill_tokens($html, $values), 'strtr ' . $i);
		pg_assert_same(_widget_fill_old_member($html, $values), _pg_member_apply_tokens($html, $values), 'member loop ' . $i);
	}
	pg_assert_same('<p>^^__a^^</p>', _pg_sw_fill_tokens('<p>^^__a^^</p>', array()), 'an empty map changes nothing');
}

// A template that holds a suffix fills its plain tokens as strtr() did, and
// never rescans a value it wrote.
function test_widget_fill_typed_matches_strtr_for_plain_tokens()
{
	$plain  = '<a href="^^__url^^">^^__t^^</a><i>^^__x^^</i>';
	$values = array('__url' => '/a?b=1&amp;c=2', '__t' => 'Ankara &amp; Co', '__x' => '^^__t^^%%upper%%');
	pg_assert_same(
		_widget_fill_old_strtr($plain, $values) . '<b>ANKARA &amp; CO</b>',
		_pg_sw_fill_tokens($plain . '<b>^^__t^^%%upper%%</b>', $values)
	);
}

// -- the suffix ---------------------------------------------------------------

function test_widget_fill_parse()
{
	pg_assert_same(array(array('upper', null)), pg_sw_fill_parse('upper'));
	pg_assert_same(array(array('number', 2)), pg_sw_fill_parse('number:2'));
	pg_assert_same(array(array('upper', null), array('truncate', 5)), pg_sw_fill_parse('upper truncate:5'), 'a chain');
	pg_assert_same(array(array('lower', null), array('nl2br', null)), pg_sw_fill_parse('  lower   nl2br '), 'spaces around and between');
	pg_assert_same(array(array('yesno', null)), pg_sw_fill_parse('yesno'));
	pg_assert_same(array(array('url', null)), pg_sw_fill_parse('url'));

	foreach (array('number', 'truncate') as $word) {
		pg_assert_same(null, pg_sw_fill_parse($word), $word . ' without its number');
	}
	foreach (array('upper:2', 'yesno:1', 'truncate:x', 'number:-1', 'number:2.5', 'upper bold', 'bold', 'Upper', 'upper,lower') as $word) {
		pg_assert_same(null, pg_sw_fill_parse($word), $word);
	}
	foreach (array('', ' ', 'd.m.Y', 'Y-m-d', 'j F Y, H:i', 'relative', 'D', 'l jS F') as $format) {
		pg_assert_same(null, pg_sw_fill_parse($format), 'date format ' . var_export($format, true));
	}

	pg_assert_same(array(array('number', 6)), pg_sw_fill_parse('number:9'), 'decimals clamped to 6');
	pg_assert_same(array(array('number', 0)), pg_sw_fill_parse('number:0'));
	pg_assert_same(array(array('truncate', 1)), pg_sw_fill_parse('truncate:0'), 'characters clamped to 1');
	pg_assert_same(array(array('truncate', 1000)), pg_sw_fill_parse('truncate:5000'), 'characters clamped to 1000');
	pg_assert_same(array(array('truncate', 1000)), pg_sw_fill_parse('truncate:99999999999999999999999'), 'a huge number');

	$keys = array_keys(pg_sw_fill_filters());
	pg_assert_same(array('upper', 'lower', 'capitalize', 'number', 'truncate', 'yesno', 'nl2br', 'url'), $keys, 'the filters in order');
}

// Upper, lower and first-letter capitals know the Turkish i: the dotted and
// the dotless one are different letters with capitals of their own.
function test_widget_fill_case()
{
	pg_assert_same('İSTANBUL', pg_sw_fill_case('istanbul', 'upper', 'tr'));
	pg_assert_same('ırmak', pg_sw_fill_case('IRMAK', 'lower', 'tr'));
	pg_assert_same('ILIK', pg_sw_fill_case('ılık', 'upper', 'tr'));
	pg_assert_same('istanbul', pg_sw_fill_case('İSTANBUL', 'lower', 'tr'));
	pg_assert_same('İstanbul', pg_sw_fill_case('istanbul', 'capitalize', 'tr'));
	pg_assert_same('Ilık hava', pg_sw_fill_case('ılık hava', 'capitalize', 'tr'));
	pg_assert_same('İSTANBUL', pg_sw_fill_case('istanbul', 'upper', 'az'), 'Azerbaijani');
	pg_assert_same('İSTANBUL', pg_sw_fill_case('istanbul', 'upper', 'tr-TR'), 'a region after the language');

	pg_assert_same('ISTANBUL', pg_sw_fill_case('istanbul', 'upper', 'en'));
	pg_assert_same('irmak', pg_sw_fill_case('IRMAK', 'lower', 'en'));
	pg_assert_same('ILIK', pg_sw_fill_case('ılık', 'upper', 'en'));
	pg_assert_same('Istanbul', pg_sw_fill_case('istanbul', 'capitalize', 'en'));
	pg_assert_same('ÇAĞRI ÖĞÜŞ', pg_sw_fill_case('çağrı öğüş', 'upper', 'tr'));

	pg_assert_same('"Quoted" words', pg_sw_fill_case('"quoted" words', 'capitalize', 'en'), 'the first letter, after a quote');
	pg_assert_same('12 Angry men', pg_sw_fill_case('12 angry men', 'capitalize', 'en'), 'the first letter, after figures');
	pg_assert_same('', pg_sw_fill_case('', 'capitalize', 'en'));
	pg_assert_same('Mixed', pg_sw_fill_case('Mixed', 'other', 'en'), 'an unknown mode changes nothing');
}

function test_widget_fill_apply()
{
	$upper = array(array('upper', null));
	pg_assert_same('A &amp; B', pg_sw_fill_apply('a & b', $upper, 'en'), 'escaped once, after the filter');
	pg_assert_same('&lt;B&gt;', pg_sw_fill_apply('<b>', $upper, 'en'), 'markup in plain text is text');

	$two = array(array('number', 2));
	pg_assert_same(pg_format_number(12.5, 2), pg_sw_fill_apply('12,5', $two, 'en'));
	pg_assert_same(pg_format_number(-12.5, 2), pg_sw_fill_apply('-₺12,50', $two, 'en'), 'an amount with a sign');
	pg_assert_same(pg_format_number(7, 0), pg_sw_fill_apply('7.4', array(array('number', 0)), 'en'));
	pg_assert_same('Kat 2', pg_sw_fill_apply('Kat 2', $two, 'en'), 'no number: as it is');
	pg_assert_same('', pg_sw_fill_apply('', $two, 'en'));

	$five = array(array('truncate', 5));
	pg_assert_same('Merha…', pg_sw_fill_apply('Merhaba dünya', $five, 'en'));
	pg_assert_same('dünya', pg_sw_fill_apply('dünya', $five, 'en'), 'exactly five: not cut');
	pg_assert_same('abc', pg_sw_fill_apply('abc', $five, 'en'), 'shorter: not cut');
	pg_assert_same('a &amp;…', pg_sw_fill_apply('a & b & c', array(array('truncate', 3)), 'en'), 'cut before escaping');

	$yesno = array(array('yesno', null));
	foreach (array('', '0', 'no', 'NO', 'False', 'off', ' 0 ') as $no) {
		pg_assert_same(h(lang('No')), pg_sw_fill_apply($no, $yesno, 'en'), var_export($no, true));
	}
	foreach (array('1', 'yes', 'on', 'evet', '2') as $yes) {
		pg_assert_same(h(lang('Yes')), pg_sw_fill_apply($yes, $yesno, 'en'), var_export($yes, true));
	}

	pg_assert_same("a&lt;b<br />\nc", pg_sw_fill_apply("a<b\nc", array(array('nl2br', null)), 'en'), 'line breaks after escaping');
	pg_assert_same('a%20b%26c%2F', pg_sw_fill_apply('a b&c/', array(array('url', null)), 'en'));
	pg_assert_same('MERHABA…', pg_sw_fill_apply('Merhaba dünya', array(array('truncate', 7), array('upper', null)), 'en'), 'left to right');
	pg_assert_same('İ…', pg_sw_fill_apply('istanbul', array(array('upper', null), array('truncate', 1)), 'tr'));
	pg_assert_same('&#94;&#94;X&#94;&#94;', pg_sw_fill_apply('^^x^^', $upper, 'en'), 'carets as entities: no token is formed');
}

function test_widget_fill_value()
{
	_widget_fill_constants();

	pg_assert_same('A &amp; B', pg_sw_fill_value('a &amp; b', 'upper'), 'decoded, filtered, escaped once');
	pg_assert_same('&lt;B&gt;', pg_sw_fill_value('&lt;b&gt;', 'upper'));
	pg_assert_same('O&#039;NEIL', pg_sw_fill_value('O&#039;Neil', 'upper'));
	$protected = pg_comments_widget_escape(array('__comment_name' => '^^__comments_title^^ me'));
	pg_assert_same('&#94;&#94;__COMMENTS_TITLE&#94;&#94; ME', pg_sw_fill_value($protected['__comment_name'], 'upper'), 'a comment keeps its carets escaped');
	pg_assert_same('<b>&#94;&#94;__COMMENTS_TITLE&#94;&#94; ME</b>', pg_sw_sweep_tokens(_pg_sw_fill_tokens('<b>^^__comment_name^^%%upper%%</b>', $protected)), 'and the sweep leaves it');
	pg_assert_same('<a href="/x">y</a>', pg_sw_fill_value('<a href="/x">y</a>', 'upper'), 'markup comes back as it is');
	pg_assert_same('<span title="a">b</span>', pg_sw_fill_value('<span title="a">b</span>', 'truncate:1'));
	pg_assert_same("A &amp; B<br />\nC", pg_sw_fill_value("a &amp; b<br />\nc", 'upper'), 'the line breaks the form views add stay');
	pg_assert_same("A<br />\nB", pg_sw_fill_value("a<br />\nb", 'upper nl2br'), 'and are not doubled');

	pg_assert_same('3 Kasım', pg_sw_fill_value('3 Kasım', 'Y-m-d'), 'a date format without a type: the value');
	pg_assert_same('3 Kasım', pg_sw_fill_value('3 Kasım', ''), 'an empty suffix');
	$typed = array('type' => 'date', 'data' => '2026-11-03');
	pg_assert_same('2026-11-03', pg_sw_fill_value('3 Kasım', 'Y-m-d', $typed));
	pg_assert_same('03.11.2026', pg_sw_fill_value('3 Kasım', 'd.m.Y', $typed));
	pg_assert_same('3 Kasım', pg_sw_fill_value('3 Kasım', 'Y-m-d', array('type' => 'text box', 'data' => '2026-11-03')), 'not a date type');
	pg_assert_same('', pg_sw_fill_value('', 'Y-m-d', array('type' => 'date', 'data' => '')), 'no date');

	$relative = pg_sw_fill_value('3 Kasım', 'relative', $typed);
	pg_assert_same(prepare_form_data_for_output('2026-11-03', 'date', true, 'relative'), $relative, 'relative: the form views\' tooltip');
	pg_assert_contains('<', $relative, 'relative is HTML');
}

// -- one pass over the template -----------------------------------------------

function test_widget_fill_typed_unknown_and_sweep()
{
	$html = '<p>^^__a^^%%upper%%|^^__b^^%%upper%%|^^__c^^</p>';
	$filled = pg_sw_fill_typed($html, array('__a' => 'x'));
	pg_assert_same('<p>X|^^__b^^%%upper%%|^^__c^^</p>', $filled, 'an unknown token keeps its suffix');
	pg_assert_same('<p>X||</p>', pg_sw_sweep_tokens($filled), 'the sweep takes it with the suffix');

	pg_assert_same('^^__b^^ b', pg_sw_fill_typed('^^__a^^ ^^__b^^%%lower%%', array('__a' => '^^__b^^', '__b' => 'B')), 'a value is never read again');
	pg_assert_same('^^__A^^%%upper%%', pg_sw_fill_typed('^^__A^^%%upper%%', array('__a' => 'x')), 'keys match in their case');
	pg_assert_same('X', pg_sw_fill_typed('^^7^^%%upper%%', array(7 => 'x')), 'a numeric key');
	pg_assert_same('2026-11-03 x', pg_sw_fill_typed('^^__d^^%%Y-m-d%% ^^__d^^', array('__d' => 'x'), array('__d' => array('type' => 'date', 'data' => '2026-11-03'))), 'each copy its own suffix');
}

// A condition reads the carrier, which holds the plain value: a filter
// changes what the node shows, never what the condition compares.
function test_widget_fill_condition_reads_the_plain_value()
{
	$scope = array('kind' => 'calendar_view');
	$spec = array('token' => '__c', 'op' => 'eq', 'operand' => 'Ankara');
	$html = pg_sw_finish_wrap('<p>^^__c^^%%truncate:2%%</p>', $spec);

	$filled = _pg_sw_fill_tokens($html, array('__c' => 'Ankara'));
	pg_assert_contains('-->Ankara<!--', $filled, 'the carrier holds the plain value');
	pg_assert_same('<p>An…</p>', pg_sw_finish_widget_html($filled, $scope), 'kept by the plain value, shown shortened');

	$filled = _pg_member_apply_tokens($html, array('__c' => 'Ankara'));
	pg_assert_same('<p>An…</p>', pg_sw_finish_widget_html($filled, $scope), 'the member fill');

	$filled = _pg_sw_fill_tokens($html, array('__c' => 'Ankaralı'));
	pg_assert_same('', pg_sw_finish_widget_html($filled, $scope), 'another value is cut');
}

// -- the form views' fill -----------------------------------------------------

function test_widget_fill_apply_tokens()
{
	_widget_fill_constants();

	$city = array('city' => array('data' => 'Ankara & Co', 'type' => 'text box'));
	pg_assert_same('<p>ANKARA &amp; CO</p>', pg_sw_apply_tokens('<p>^^city^^%%upper%%</p>', array(), $city), 'a text field');
	pg_assert_same('<p>Ankara &amp; Co</p>', pg_sw_apply_tokens('<p>^^city^^</p>', array(), $city), 'no suffix: as before');
	pg_assert_same('<p>Ankara &amp; Co</p>', pg_sw_apply_tokens('<p>^^city^^%%d.m.Y%%</p>', array(), $city), 'a date format on text: swallowed as before');

	pg_assert_same('<a href="?q=a%20b%26c">', pg_sw_apply_tokens('<a href="?q=^^__q^^%%url%%">', array(), array(), array('__q' => h('a b&c'))), 'an $extra value');
	pg_assert_same('<b>&lt;i&gt;</b>', pg_sw_apply_tokens('<b>^^__q^^%%lower%%</b>', array(), array(), array('__q' => h('<I>'))), 'an $extra value escaped once');
	pg_assert_same('<a href="/x">y</a>', pg_sw_apply_tokens('^^__q^^%%upper%%', array(), array(), array('__q' => '<a href="/x">y</a>')), 'an $extra value with markup');

	$date = array('d' => array('data' => '2026-11-03', 'type' => 'date'));
	pg_assert_same('03.11.2026', pg_sw_apply_tokens('^^d^^%%d.m.Y%%', array(), $date), 'a date format, as before');
	pg_assert_same(
		prepare_form_data_for_output('2026-11-03', 'date', true, pg_sw_default_date_format('date')),
		pg_sw_apply_tokens('^^d^^%%upper%%', array(), $date),
		'a filter on a date: the default format, then the filter'
	);

	$rich = array('body' => array('data' => '<p>hi</p>', 'type' => 'text area', 'wysiwyg' => 1));
	pg_assert_same(pg_defuse_php_tags(pg_sanitize_rich_text('<p>hi</p>')), pg_sw_apply_tokens('^^body^^%%upper%%', array(), $rich), 'rich text comes back as it is');

	$lines = array('note' => array('data' => "one\ntwo", 'type' => 'text area'));
	pg_assert_same("ONE<br />\nTWO", pg_sw_apply_tokens('^^note^^%%upper%%', array(), $lines), 'a text area keeps its lines');

	pg_assert_same(pg_format_number(12, 2), pg_sw_apply_tokens('^^number_of_views^^%%number:2%%', array('number_of_views' => '12'), array()), 'a counter with decimals');
	pg_assert_same('X|x', pg_sw_apply_tokens('^^a^^%%upper%%|^^a^^', array(), array('a' => array('data' => 'x', 'type' => 'text box'))), 'each copy its own suffix');
}

// -- calendar dates -----------------------------------------------------------

function test_widget_fill_calendar_event_times()
{
	// A timed event carries its time, so time letters show when it starts.
	$start = mktime(14, 0, 0, 10, 5, 2026);
	$end = mktime(16, 30, 0, 10, 7, 2026);
	$at = array('type' => 'date and time', 'data' => '2026-10-05 14:00:00');
	pg_assert_same(array(
		'__event_date'       => $at,
		'__event_start_date' => $at,
		'__event_end_date'   => array('type' => 'date and time', 'data' => '2026-10-07 16:30:00'),
	), _pg_sw_calendar_event_times($start, $end));
	pg_assert_same(array('__event_date' => $at, '__event_start_date' => $at), _pg_sw_calendar_event_times($start), 'no end');
	pg_assert_same(array('__event_date' => $at, '__event_start_date' => $at), _pg_sw_calendar_event_times($start, false), 'an end strtotime() could not read');

	// An all-day event is a date.
	$day = array('type' => 'date', 'data' => '2026-10-05');
	$all_day = _pg_sw_calendar_event_times(mktime(0, 0, 0, 10, 5, 2026), mktime(0, 0, 0, 10, 6, 2026), true);
	pg_assert_same(array(
		'__event_date'       => $day,
		'__event_start_date' => $day,
		'__event_end_date'   => array('type' => 'date', 'data' => '2026-10-06'),
	), $all_day, 'all day');
	_widget_fill_constants();
	pg_assert_same('<b>05.10.2026</b>', _pg_sw_fill_tokens('<b>^^__event_date^^%%d.m.Y%%</b>', array('__event_date' => '5 Oct'), $all_day));

	foreach (array('_render_system_widget_calendar_view', '_render_system_widget_calendar_event_view') as $renderer) {
		$code = _widget_fill_php_function($renderer);
		pg_assert_contains('_pg_sw_calendar_event_times(', $code, $renderer . ' types its dates');
		pg_assert_contains('all_day', substr($code, strpos($code, '_pg_sw_calendar_event_times(')), $renderer . ' says whether the event is all day');
	}
	pg_assert_contains("event_start_time", _widget_fill_php_function('_render_system_widget_calendar_view'), 'the calendar row types the start with its time');
}

// A date and time with a format reads USER_LOGGED_IN and USER_TIMEZONE,
// which the tests after this one must not find defined, so the timed
// fills run in a PHP process of their own.
function test_widget_fill_calendar_event_times_formatted()
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return;
	}
	$file = @tempnam(sys_get_temp_dir(), 'pgfill');
	if ($file === false) {
		return;
	}

	file_put_contents($file, '<?php' . "\n"
		. "define('USER_LOGGED_IN', false);\n"
		. "define('USER_TIMEZONE', '');\n"
		. "define('DATE_FORMAT', 'day_month');\n"
		. "define('TIME_FORMAT', 'twenty_four_hours');\n"
		. 'require ' . var_export(PG_FUNCTIONS_DIR . '/functions.php', true) . ";\n"
		. '$t = _pg_sw_calendar_event_times(mktime(14, 0, 0, 10, 5, 2026), mktime(16, 30, 0, 10, 7, 2026));' . "\n"
		. 'echo json_encode(array(' . "\n"
		. '  _pg_sw_fill_tokens(\'<b>^^__event_date^^%%d.m.Y H:i%%</b>\', array(\'__event_date\' => \'5 Oct\'), $t),' . "\n"
		. '  _pg_sw_fill_tokens(\'<b>^^__event_end_date^^%%d.m.Y H:i%%</b>\', array(\'__event_end_date\' => \'7 Oct\'), $t),' . "\n"
		. '  _pg_sw_fill_tokens(\'<b>^^__event_start_date^^%%Y-m-d%%</b>\', array(\'__event_start_date\' => \'5 Oct\'), $t),' . "\n"
		. '  _pg_sw_fill_tokens(\'<b>^^__event_date^^%%relative%%</b>\', array(\'__event_date\' => \'5 Oct\'), $t)' . "\n"
		. '));' . "\n");

	$out = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$printed = implode("\n", $out);

	pg_assert_same(0, $code, 'filled: ' . $printed);
	$answer = json_decode($printed, true);
	pg_assert_true(is_array($answer) && count($answer) === 4, 'four fills: ' . $printed);
	pg_assert_same('<b>05.10.2026 14:00</b>', $answer[0], 'a 14:00 start with its time');
	pg_assert_same('<b>07.10.2026 16:30</b>', $answer[1], 'the end with its time');
	pg_assert_same('<b>2026-10-05</b>', $answer[2], 'a date format without time letters');
	pg_assert_contains('<', substr($answer[3], 3, -4), 'relative: the tooltip HTML');
	pg_assert_false(strpos($answer[3], '%%') !== false, 'relative: no suffix');
}

// -- the editor ---------------------------------------------------------------

// The block of the editor's Format row: the lists and the functions that
// read them, in one piece.
function _widget_fill_js_block($js)
{
	$from = strpos($js, "\n    var _SD_TEXT_FILTERS = [");
	$fn = strpos($js, "\n    function _sdFormatRows(");
	if ($from === false || $fn === false) {
		return '';
	}
	$to = strpos($js, "\n    }\n", $fn);
	return ($to === false) ? '' : substr($js, $from, $to + 7 - $from);
}

// The statement 'var NAME = [ ... ];' (or '{ ... }', '(function () { ... })()')
// of style_designer.js, read to the bracket that closes the one it opens with.
function _widget_fill_js_literal($js, $name)
{
	$head = "\n    var " . $name . ' = ';
	$from = strpos($js, $head);
	if ($from === false) {
		return '';
	}
	$at = $from + strlen($head);
	$pairs = array('[' => ']', '{' => '}', '(' => ')');
	$open = $js[$at];
	if (!isset($pairs[$open])) {
		return '';
	}
	$depth = 0;
	for ($limit = strlen($js); $at < $limit; $at++) {
		if ($js[$at] === $open) {
			$depth++;
		} elseif ($js[$at] === $pairs[$open] && --$depth === 0) {
			break;
		}
	}
	$end = strpos($js, ";\n", $at);
	return ($end === false) ? '' : substr($js, $from, $end + 1 - $from);
}

function test_widget_fill_php_js_parity()
{
	$js = _widget_fill_source('assets/js/style_designer.js');
	pg_assert_true(_widget_fill_js_block($js) !== '', 'the Format row block is found');

	$filters = _widget_fill_js_literal($js, '_SD_TEXT_FILTERS');
	preg_match_all("/\\['([a-z0-9]*)',/", $filters, $m);
	$server = array_values(array_diff(array_keys(pg_sw_fill_filters()), array('url')));
	pg_assert_same(array_merge(array(''), $server), $m[1], 'the editor lists the server\'s filters, url aside');

	$args = _widget_fill_js_literal($js, '_SD_FILTER_ARG');
	foreach (pg_sw_fill_filters() as $name => $filter) {
		if (!$filter['arg']) {
			pg_assert_false(strpos($args, $name . ':') !== false, $name . ' takes no number');
			continue;
		}
		pg_assert_contains($name . ': { min: ' . $filter['min'] . ', max: ' . $filter['max'] . ',', $args, $name . ' range');
	}

	$types = _widget_fill_js_literal($js, 'SW_TYPES');
	preg_match_all("/\\{ type: '([a-z_]+)'/", $types, $t);
	pg_assert_true(count($t[1]) >= 30, 'SW_TYPES is read');
	// The kinds whose fill reads no format (the favourites list, the recent
	// comments list): the editor offers them no Format row.
	$part_two = array('wishlist', 'comments_recent');
	$kinds = _widget_fill_js_literal($js, '_SD_FILTER_KINDS');
	preg_match_all('/\b([a-z_]+): 1\b/', $kinds, $k);
	$expected = array_values(array_diff($t[1], $part_two));
	$listed = $k[1];
	sort($expected);
	sort($listed);
	pg_assert_same($expected, $listed, 'every kind whose fill reads a format');

	$dates = _widget_fill_js_literal($js, 'SW_DATE_FIELD_TOKENS');
	foreach (array('__event_date', '__event_start_date', '__event_end_date', '__order_date', '__arrival_date', '__withdrawal_deadline') as $token) {
		pg_assert_contains('out.' . $token . ' = true', $dates, $token . ' is a date');
	}
}

// Runs the editor's Format row, its filter reading and _sdNodeSetBinding()
// under Node with the panel helpers stubbed; null when Node is not
// installed (the test is skipped).
function _widget_fill_run_js($script)
{
	if (!function_exists('exec')) {
		return null;
	}
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return null;
	}

	$js = _widget_fill_source('assets/js/style_designer.js');
	$block = _widget_fill_js_block($js);
	$dates = _widget_fill_js_literal($js, 'SW_DATE_FIELD_TOKENS');
	$bindfmt = _widget_fill_js_function($js, '_sdBindFormatRow');
	$setter = _widget_fill_js_function($js, '_sdNodeSetBinding');
	$kind = _widget_fill_js_function($js, '_swNameKind');
	$kind_of = _widget_fill_js_function($js, '_swKindOf');
	pg_assert_true($block !== '' && $dates !== '' && $bindfmt !== '' && $setter !== '' && $kind !== '' && $kind_of !== '', 'the editor code is found');

	$stubs = "function _sdT(k, v) { if (v === undefined) return k; var l = Array.isArray(v) ? v : [v]; return k.replace(/\\{var\\}/g, String(l[0])); }\n"
		. "function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\"/g, '&quot;'); }\n"
		. "function row(label, ctrl) { return '<row label=\"' + label + '\">' + ctrl + '</row>'; }\n"
		. "var _sharedCache = {}, _sharedDirty = {};\n"
		. "function _isInsideSystemWidget(node) { return (node && node.sid) ? node.sid : 0; }\n"
		. "function _sdFormFieldIsDate() { return false; }\n"
		. "var SW_STANDARD_FIELD_OPTIONS = [['submitted_date_and_time', 'Submitted', 'date'], ['reference_code', 'Reference', 'text']];\n";

	$file = tempnam(sys_get_temp_dir(), 'pgfill');
	file_put_contents($file, $stubs . $dates . ";\n" . $block . $bindfmt . $setter . $kind_of . $kind . "\n" . $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	$answer = json_decode($printed, true);
	pg_assert_true(($printed !== '') && (json_last_error() === JSON_ERROR_NONE), 'Node printed JSON: ' . (($printed === '') ? '(nothing)' : $printed));
	return $answer;
}

function test_widget_fill_editor_twins()
{
	$inputs = array('upper', 'number:2', 'number', 'truncate:x', 'd.m.Y', 'relative', '', 'number:9', 'truncate:0', 'upper:1', 'nl2br', 'Upper');
	$script = "var kinds = { 1: 'catalog_listing', 2: 'calendar_view', 3: 'form_list_view', 4: 'wishlist', 6: 'comments', 7: 'blog_list', 8: 'submitted_forms_list', 9: 'form_list', 10: '' };\n"
		. "Object.keys(kinds).forEach(function (sid) { _sharedCache[sid] = { system_region_config: JSON.stringify({ regionType: kinds[sid] }) }; });\n"
		. "_sharedCache[5] = { system_region_config: '{}' };\n"
		. "function node(sid, token, fmt) { var p = {}; if (token) p._bindings = { text: token }; if (fmt !== undefined) p._bindFormats = { text: fmt }; return { sid: sid, props: p }; }\n"
		. "var out = {};\n"
		. "out.catalog = _sdFormatRows(node(1, '__name'));\n"
		. "out.calendarDate = _sdFormatRows(node(2, '__event_date', 'Y-m-d'));\n"
		. "out.calendarText = _sdFormatRows(node(2, '__event_title'));\n"
		. "out.formListDate = _sdFormatRows(node(3, 'submitted_date_and_time'));\n"
		. "out.formList = _sdFormatRows(node(3, 'section'));\n"
		. "out.formListNumber = _sdFormatRows(node(3, 'section', 'number:3'));\n"
		. "out.written = _sdFormatRows(node(3, 'section', 'upper truncate:5'));\n"
		. "out.oldFormList = _sdFormatRows(node(5, 'section', 'upper'));\n"
		. "out.aliases = [7, 8, 9].map(function (sid) { return _sdFormatRows(node(sid, 'section', 'upper')); });\n"
		. "out.aliasDate = _sdFormatRows(node(7, 'submitted_date_and_time'));\n"
		. "out.emptyKind = _sdFormatRows(node(10, 'section'));\n"
		. "out.unknownKind = _sdFormatRows(node(4, '__product_name'));\n"
		. "out.commentsDate = _sdFormatRows(node(6, '__comment_datetime'));\n"
		. "out.noBinding = _sdFormatRows(node(3, ''));\n"
		. "out.outside = _sdFormatRows({ props: { _bindings: { text: 'section' } } });\n"
		. "out.parse = " . json_encode($inputs) . ".map(_sdParseFilter);\n"
		. "var n = node(3, 'section', 'upper');\n"
		. "_sdNodeSetBinding(n, 'text', 'section'); out.same = n.props._bindFormats ? n.props._bindFormats.text : null;\n"
		. "_sdNodeSetBinding(n, 'text', 'reference_code'); out.changed = n.props._bindFormats ? n.props._bindFormats.text : null;\n"
		. "out.dirty = !!_sharedDirty[3];\n"
		. "var d = node(2, '__event_date', 'Y-m-d');\n"
		. "_sdNodeSetBinding(d, 'text', '__event_start_date'); out.dateToDate = d.props._bindFormats ? d.props._bindFormats.text : null;\n"
		. "_sdNodeSetBinding(d, 'text', '__event_title'); out.dateToText = d.props._bindFormats ? d.props._bindFormats.text : null;\n"
		. "var e = node(2, '__event_date', 'upper');\n"
		. "_sdNodeSetBinding(e, 'text', '__event_start_date'); out.filterToDate = e.props._bindFormats ? e.props._bindFormats.text : null;\n"
		. "var g = node(3, 'submitted_date_and_time', 'd.m.Y');\n"
		. "_sdNodeSetBinding(g, 'text', 'submitted_date_and_time'); out.dateSame = g.props._bindFormats ? g.props._bindFormats.text : null;\n"
		. "_sdNodeSetBinding(g, 'text', ''); out.unbound = g.props._bindFormats ? g.props._bindFormats.text : null;\n"
		. "console.log(JSON.stringify(out));\n";

	$answer = _widget_fill_run_js($script);
	if ($answer === null) {
		return;
	}

	pg_assert_contains('label="Format"', $answer['catalog'], 'catalog_listing reads a format');
	pg_assert_contains('label="Date format"', $answer['calendarDate'], 'calendar dates take a date format');
	pg_assert_contains('class="form-control form-control-sm mt-1 sd-sw-bindfmt"', $answer['calendarDate']);
	pg_assert_contains('value="Y-m-d"', $answer['calendarDate']);
	pg_assert_contains('sd-sw-bindfilter', $answer['calendarText'], 'a calendar text takes a filter');
	pg_assert_same('', $answer['formListDate'], 'the form views draw their date row themselves');
	pg_assert_contains('label="Format"', $answer['formList']);
	pg_assert_contains('<select class="form-select form-select-sm sd-sw-bindfilter"', $answer['formList']);
	pg_assert_contains('<option value="" selected>As it is</option>', $answer['formList'], 'no format: as it is');
	pg_assert_contains('style="display:none"', $answer['formList'], 'no number box');
	pg_assert_contains('<option value="number" selected>', $answer['formListNumber']);
	pg_assert_contains('value="3"', $answer['formListNumber'], 'the number box holds the decimals');
	pg_assert_contains('min="0" max="6"', $answer['formListNumber']);
	pg_assert_contains('data-written="1" selected>Written: upper truncate:5</option>', $answer['written'], 'a hand-written chain is shown and kept');
	pg_assert_false(strpos($answer['written'], '<option value="" selected>') !== false, 'not shown as as it is');
	pg_assert_contains('<option value="upper" selected>', $answer['oldFormList'], 'a widget saved without a kind is a form list');
	foreach (array('blog_list', 'submitted_forms_list', 'form_list') as $i => $alias) {
		pg_assert_contains('<option value="upper" selected>', $answer['aliases'][$i], $alias . ' is a form list');
	}
	pg_assert_same('', $answer['aliasDate'], 'an alias draws its date row in the binding rows, as the form list');
	pg_assert_same('', $answer['emptyKind'], 'the empty widget offers nothing');
	pg_assert_same('', $answer['unknownKind'], 'a kind the list does not name offers nothing');
	pg_assert_contains('label="Date format"', $answer['commentsDate'], 'the comment date');
	pg_assert_same('', $answer['noBinding']);
	pg_assert_same('', $answer['outside']);
	pg_assert_contains('A condition compares the value before its format.', $answer['formList'], 'the hint');
	pg_assert_contains('the first card on the canvas shows the value without it', $answer['formList'], 'the hint names the card the canvas draws');
	pg_assert_contains('A value with formatting of its own', $answer['formList'], 'the hint names values a filter leaves');

	foreach ($inputs as $i => $input) {
		$php = pg_sw_fill_parse($input);
		$expected = ($php === null || count($php) !== 1) ? null : array('name' => $php[0][0], 'arg' => $php[0][1]);
		pg_assert_same($expected, $answer['parse'][$i], 'parse ' . var_export($input, true));
	}

	pg_assert_same('upper', $answer['same'], 'the same token again keeps the filter');
	pg_assert_same(null, $answer['changed'], 'another token drops it');
	pg_assert_true($answer['dirty'], 'the widget is marked');
	pg_assert_same('Y-m-d', $answer['dateToDate'], 'a date format moves to another date');
	pg_assert_same(null, $answer['dateToText'], 'but not to a text');
	pg_assert_same(null, $answer['filterToDate'], 'a filter does not move to another token');
	pg_assert_same('d.m.Y', $answer['dateSame'], 'the same date token again');
	pg_assert_same(null, $answer['unbound'], 'no binding, no format');
}

function test_widget_fill_style_designer_parses()
{
	if (!function_exists('exec')) {
		return;
	}
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return;
	}
	$out = array();
	exec('node --check ' . escapeshellarg(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js') . ' 2>&1', $out, $code);
	pg_assert_same(0, $code, implode("\n", $out));
}
