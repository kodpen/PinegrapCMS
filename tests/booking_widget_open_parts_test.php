<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the booking widget's parts on the designer's canvas: the steps
 * and the details form keep their children on the page, the parts the
 * script fills get a sample drawn from the script's own words, the policy's
 * closing words and sample times on the site's clock
 * (pg_booking_canvas_sample()), and the editor's wiring.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_booking_canvas_sample_words_are_the_scripts()
{
	$sample = pg_booking_canvas_sample(array('zone' => 'UTC', 'hold_minutes' => 15, 'auto_confirm' => true), array());

	pg_assert_same(pg_booking_widget_words(), $sample['words'], 'the canvas draws the words the script writes');
	pg_assert_same(15, $sample['hold']);
	pg_assert_same('UTC', $sample['zone']);
}

function test_booking_canvas_sample_closing_words_follow_the_policy()
{
	$confirmed = pg_booking_canvas_sample(array('zone' => 'UTC', 'hold_minutes' => 10, 'auto_confirm' => true), array());
	$requested = pg_booking_canvas_sample(array('zone' => 'UTC', 'hold_minutes' => 10, 'auto_confirm' => false), array());
	$own = pg_booking_canvas_sample(array('zone' => 'UTC', 'hold_minutes' => 10, 'auto_confirm' => false), array('done' => 'See you soon.'));

	pg_assert_same(pg_booking_widget_done_text('confirmed', array()), $confirmed['done']);
	pg_assert_same(pg_booking_widget_done_text('requested', array()), $requested['done']);
	pg_assert_false($confirmed['done'] === $requested['done'], 'a booking and a request end in other words');
	pg_assert_same('See you soon.', $own['done'], 'the site\'s own words come first');
}

function test_booking_canvas_sample_times_on_the_site_clock()
{
	$sample = pg_booking_canvas_sample(array('zone' => 'Europe/Istanbul', 'hold_minutes' => 10), array());

	pg_assert_same(6, count($sample['times']));
	$moment = new DateTime('2026-01-15 09:00', new DateTimeZone('Europe/Istanbul'));
	pg_assert_same(pg_booking_time_label($moment->getTimestamp(), 'Europe/Istanbul'), $sample['times'][0], 'labelled as the slot answers label them');

	$broken = pg_booking_canvas_sample(array('zone' => 'Nowhere/Else', 'hold_minutes' => 10), array());
	pg_assert_same('UTC', $broken['zone'], 'an unknown zone falls back to UTC');
	pg_assert_same(6, count($broken['times']));
}

function test_booking_parts_keep_their_children_on_the_page()
{
	$node = function ($tag, $props, $children = array()) {
		return array('type' => 'semantic', 'props' => array('tag' => $tag) + $props, 'children' => $children);
	};
	$tree = json_encode(array('type' => 'root', 'props' => array(), 'children' => array(
		$node('div', array('_bindings' => array('section' => 'booking_step_when')), array(
			$node('h2', array('text' => 'Pick a day')),
			$node('div', array('_bindings' => array('section' => 'booking_calendar'))),
		)),
		$node('form', array('_bindings' => array('section' => 'booking_form')), array(
			$node('label', array('text' => 'Your name')),
		)),
	)));
	$services = array(array('id' => 3, 'name' => 'Cut', 'description' => '', 'length_minutes' => 45, 'price' => 0));
	$staff = array(array('id' => 9, 'user_id' => 1, 'name' => 'Ayşe', 'service_ids' => '[3]', 'enabled' => 1));

	$html = _render_system_widget_booking($tree, 51, array(), 'preview', array($services, $staff, ''));

	pg_assert_contains('Pick a day', $html, 'a step prints its designed heading');
	pg_assert_contains('Your name', $html, 'the details form prints its designed fields');
	pg_assert_contains('data-pg-booking-part="calendar"', $html);
}

function test_booking_canvas_wiring()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_booking.js');
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');

	$keys = function ($name) use ($js) {
		pg_assert_true(preg_match('/var ' . $name . ' = \{([^}]*)\}/', $js, $m) === 1, $name . ' is defined');
		preg_match_all('/(booking_\w+):/', $m[1], $found);
		return $found[1];
	};
	$kept = $keys('_SD_BOOKING_KEEPS_CHILDREN');
	$filled = $keys('_SD_BOOKING_FILLED');

	$all = array_merge($kept, $filled);
	sort($all);
	$parts = pg_booking_widget_parts();
	sort($parts);
	pg_assert_same($parts, $all, 'every part is either kept or filled, none twice');
	pg_assert_same(array(), array_values(array_intersect($kept, $filled)));

	pg_assert_true(preg_match("/b\\.className = '([^']+)'/", $script, $m) === 1, 'the script names its button class');
	pg_assert_contains("var SD_BOOKING_BUTTON_CLASS = '" . $m[1] . "';", $js, 'the canvas draws the script\'s buttons');
	pg_assert_contains("fill(self.w.times, { day: self.dayLabel(iso) }) + '. ' + fill(self.w.zoneNote", $script, 'the status line ends its first sentence');
	pg_assert_contains("_sdBookingFill(w.times, { day: day }) + '. ' + _sdBookingFill(w.zoneNote", $js, 'the canvas joins the status line as the script does');
	pg_assert_contains('_SD_BOOKING_KEEPS_CHILDREN[_sec]', $js, 'a kept part gets no warning');
	pg_assert_contains('_sdBookingSectionSample(_sec, doc)', $js);
	pg_assert_contains("'booking' => (function_exists('pg_booking_canvas_sample')", $screen);
}
