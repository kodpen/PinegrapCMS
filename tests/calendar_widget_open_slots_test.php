<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the calendar widgets' sections on the designer canvas: the
 * calendar view's picker and the event view's Reserve button are built by
 * pure builders (includes/fn/widgets_calendar.php), and the canvas draws
 * their twins from assets/js/style_designer.src.js, cut out and run under
 * Node (skipped on a machine without Node) and compared byte for byte. The
 * month grid section opens as the grid lists; the editor's wiring.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function calendar_open_slots_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// The body of the named function, up to the next function at the same indentation.
function calendar_open_slots_test_function($source, $name)
{
	$from = strpos($source, '    function ' . $name . '(');
	pg_assert_true($from !== false, 'the designer has ' . $name . '()');
	$to = strpos($source, "\n    function ", $from + 10);
	pg_assert_true($to !== false, $name . '() has an end');

	return substr($source, $from, $to - $from);
}

// Runs a JavaScript expression over the cut twins; null without Node.
function calendar_open_slots_test_run($expr)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = calendar_open_slots_test_source();
	$script = "function esc(s) { if (!s && s !== 0) return ''; return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\"/g, '&quot;'); }\n"
		. "function _sdT(s) { return s; }\n";
	foreach (array('_sdCalendarPickerHtml', '_sdCalendarReserveHtml') as $fn) {
		$script .= calendar_open_slots_test_function($source, $fn) . "\n";
	}
	$script .= 'console.log(JSON.stringify(' . $expr . '));';
	$file = tempnam(sys_get_temp_dir(), 'pgcalopen');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

function test_calendar_picker_twin_matches_the_site()
{
	$calendars = array(array('id' => 3, 'name' => 'Events & talks'), array('id' => 7, 'name' => 'Workshops'));
	$hidden = '<input type="hidden" name="date" value="11-01-2026">';

	$site = pg_sw_calendar_picker_html('/calendar', $hidden, $calendars, 7, 'weekly');
	pg_assert_contains('<option value="7" selected>Workshops</option>', $site, 'the visitor\'s calendar stays chosen');
	pg_assert_contains('<option value="weekly" selected>', $site);
	pg_assert_contains('Events &amp; talks', $site, 'a name is escaped');

	$one = pg_sw_calendar_picker_html('/calendar', '', array(array('id' => 3, 'name' => 'Events')), 0, 'monthly');
	pg_assert_false(strpos($one, 'name="calendar_id"') !== false, 'one calendar: no calendar choice');

	$drawn = calendar_open_slots_test_run('[_sdCalendarPickerHtml("/calendar", ' . json_encode($hidden) . ', [{ id: 3, name: "Events & talks" }, { id: 7, name: "Workshops" }], 7, "weekly"), '
		. '_sdCalendarPickerHtml("/calendar", "", [{ id: 3, name: "Events" }], 0, "monthly")]');

	if ($drawn !== null) {
		pg_assert_same($site, $drawn[0], 'the canvas picker is the site\'s');
		pg_assert_same($one, $drawn[1], 'with one calendar too');
	}
}

function test_calendar_reserve_twin_matches_the_site()
{
	$site = pg_sw_calendar_reserve_form_html('/pinegrap/reserve_calendar_event.php', '', '/event?id=4', 12, 4, 2, 'Book a "seat"');
	pg_assert_contains('name="calendar_event_id" value="4"', $site);
	pg_assert_contains('>Book a &quot;seat&quot;</button>', $site, 'the label is escaped');

	$drawn = calendar_open_slots_test_run('_sdCalendarReserveHtml("/pinegrap/reserve_calendar_event.php", "", "/event?id=4", 12, 4, 2, "Book a \"seat\"")');

	if ($drawn !== null) {
		pg_assert_same($site, $drawn, 'the canvas Reserve button is the site\'s');
	}
}

function test_calendar_renderers_use_the_builders()
{
	$widgets = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');

	pg_assert_contains('$picker = pg_sw_calendar_picker_html($path, _pg_sw_hidden_query_inputs(array(\'calendar_id\', \'view\')), $calendars, $calendar_id, $view);', $widgets);
	pg_assert_contains('$reserve_form = pg_sw_calendar_reserve_form_html(', $widgets);
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/fn/widgets_calendar.php');", (string) file_get_contents(PG_FUNCTIONS_DIR . '/functions.php'));
}

function test_calendar_editor_wiring()
{
	$js = calendar_open_slots_test_source();

	pg_assert_contains("|| (kind === 'calendar_view' && section === 'month_grid')", $js, 'the month grid opens as the grid lists');
	pg_assert_contains("(b.section === 'month_grid') ? _sdCalendarGridFromSection(bs) : _sdPagingListStarter(bs)", $js);
	pg_assert_contains("_sdCalendarListStarter('calendar.weeks', bs)", calendar_open_slots_test_function($js, '_sdCalendarGridFromSection'));
	pg_assert_contains("' pg-sw-calendar-grid'", $js, 'the designed grid keeps the classic grid\'s class');
	pg_assert_contains('_sdCalendarSectionSample(_getSystemWidgetRegionType(node), _sec, doc, _sysWidgetRenderCfg)', $js);
	pg_assert_contains("if (c.default_view === 'upcoming') return '';", calendar_open_slots_test_function($js, '_sdCalendarSectionHtml'), 'no picker in the upcoming view, as on the site');
}
