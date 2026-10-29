<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The on-demand loader of the date-and-time control on site pages
 * (includes/fn/site_datetime.php): a page with a date hook gets the
 * library's stylesheet, the site rules, the options and the tags once; a
 * page without one, or with the panel's tags, is left as it is; the
 * vendored stylesheet is the release file.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_site_datetime_pattern()
{
	foreach (array('<input data-pg-date>', '<input data-pg-date="plain">', '<input type="text" data-pg-datetime value="">', '<input data-pg-time/>', '<fieldset data-pg-date-selects>', '<input DATA-PG-DATE >') as $hooked) {
		pg_assert_same(1, preg_match(pg_site_datetime_pattern(), $hooked), $hooked . ' is a hook');
	}
	foreach (array('<input data-pg-datex>', '<input data-pg-fx="fade">', '<p>data-pg-date</p>', '<input data-pg-chips>', '<input data-pg-time-zone="x">') as $plain) {
		pg_assert_same(0, preg_match(pg_site_datetime_pattern(), $plain), $plain . ' is no hook');
	}
}

function test_site_datetime_inject_off()
{
	$plain = '<html><head></head><body><input name="d" class="datepicker"></body></html>';
	pg_assert_same($plain, pg_site_datetime_inject($plain), 'a page without a hook is left as it is');

	$fx = '<html><head></head><body><p data-pg-fx="fade">x</p></body></html>';
	pg_assert_same($fx, pg_site_datetime_inject($fx), 'another data-pg-* marker is no hook');

	$panel = '<html><head></head><body><input data-pg-date><script src="/pinegrap/assets/js/pg_datetime.js?v=1"></script></body></html>';
	pg_assert_same($panel, pg_site_datetime_inject($panel), 'a page that printed the panel tags keeps them alone');
}

function test_site_datetime_inject_on()
{
	$page = '<html><head><title>t</title></head><body><input id="d" data-pg-date><p>&lt;/body&gt;</p></body></html>';
	$once = pg_site_datetime_inject($page);

	pg_assert_same('<html><head><title>t</title>' . pg_site_datetime_head() . '</head><body><input id="d" data-pg-date><p>&lt;/body&gt;</p>' . pg_site_datetime_scripts(true) . '</body></html>', $once, 'styles before </head>, options and scripts before the last </body>');
	pg_assert_same($once, pg_site_datetime_inject($once), 'a second call adds nothing');

	$head = pg_site_datetime_head();
	pg_assert_contains('/assets/lib/vanilla-calendar-pro/index.css?v=', $head, 'the library\'s own stylesheet, light and dark');
	pg_assert_contains('/assets/css/pg_datetime_site.css?v=', $head, 'and the site rules of the control');
	pg_assert_same(false, strpos($head, 'pg_datetime.css?'), 'not the panel stylesheet, which reads panel tokens');

	$scripts = pg_site_datetime_scripts(true);
	pg_assert_contains('var datetimepicker_options', $scripts, 'the options when the page has none');
	pg_assert_true(strpos($scripts, 'datetimepicker_options') < strpos($scripts, 'pg_datetime.js') && strpos($scripts, 'pg_datetime.js') < strpos($scripts, 'pg_fields.js'), 'options, then the control, then the hooks');
	pg_assert_contains('data-pg-calendar-src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/vanilla-calendar-pro/index.js?v=', $scripts, 'the library is loaded once by the control');
	pg_assert_same(0, substr_count($scripts, '<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/'), 'with no tag of its own');
	pg_assert_same(false, strpos($scripts, ' defer'), 'run in order before DOMContentLoaded, when pg_fields.js draws the fields');

	$jquery_ui = '<html><head></head><body><script>var datetimepicker_options = {timeText: "Saat"};</script><input data-pg-time></body></html>';
	$kept = pg_site_datetime_inject($jquery_ui);
	pg_assert_same(1, substr_count($kept, 'var datetimepicker_options'), 'options a jQuery UI picker printed are not written over');
	pg_assert_contains('pg_fields.js', $kept);

	pg_assert_same(pg_site_datetime_head() . '<input data-pg-datetime>' . pg_site_datetime_scripts(true), pg_site_datetime_inject('<input data-pg-datetime>'), 'a fragment');
	pg_assert_contains('pg_site_datetime_inject($content)', (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php'), 'the published page asks for it');
}

function test_site_datetime_settings()
{
	$settings = pg_site_datetime_settings();

	pg_assert_same(true, $settings['site'], 'the control knows the page has no Bootstrap 6');
	pg_assert_same('btn btn-sm btn-primary', $settings['button'], 'a Done button in Bootstrap 5 classes');
	pg_assert_same(array_keys(pg_calendar_settings()), array_values(array_diff(array_keys($settings), array('site', 'button'))), 'with the clock and words of the panel');
	pg_assert_contains(h(json_encode($settings)), pg_site_datetime_scripts(false), 'printed on the control\'s tag');
	pg_assert_same(false, strpos(pg_site_datetime_scripts(false), 'datetimepicker_options'), 'no options when the page has them');
}

function test_site_datetime_library_stylesheet_is_the_release_file()
{
	$css = (string) @file_get_contents(PG_FUNCTIONS_DIR . '/assets/lib/vanilla-calendar-pro/index.css');

	pg_assert_same(0, strpos($css, '/*! name: vanilla-calendar-pro v3.4.0 |'), 'index.css opens with the banner of the library version');
	// styles/index.css of the npm package; an update copies it with index.js
	// and changes this hash (core/vanilla-calendar-library.md).
	pg_assert_same('856fd50a667cfb58577060358a9a9cbae108cd05f954dccbfbca6fbe4f6b0d24', hash('sha256', $css), 'index.css is the npm release file byte for byte');
	pg_assert_contains('[data-vc-theme=dark]', $css, 'with its dark theme');
	pg_assert_contains('[data-vc=time]', $css, 'and its time row');
}

function test_site_datetime_bind_marker()
{
	pg_assert_same('', pg_site_datetime_bind(array()), 'no ids, no marker');
	pg_assert_same('', pg_site_datetime_bind(array('date' => array(), 'datetime' => array())), 'empty lists, no marker');

	$mark = pg_site_datetime_bind(array('date' => array(12, '13'), 'time' => array('t"1'), 'other' => array('x')));
	pg_assert_same('<span hidden data-pg-date-bind="' . h(json_encode(array('date' => array('12', '13'), 'time' => array('t"1')))) . '"></span>', $mark, 'ids per kind, as text, escaped; unknown kinds dropped');
	pg_assert_same(1, preg_match(pg_site_datetime_pattern(), $mark), 'the marker brings the control');
	pg_assert_contains('pg_fields.js', pg_site_datetime_inject('<form><input id="12">' . $mark . '</form>'));
}

function test_site_datetime_renderers_hook_their_fields()
{
	$source = function ($path) {
		return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
	};

	// A custom layout's inputs come from the site's database: bound by id.
	$screen = $source('get_custom_form_screen_content.php');
	pg_assert_contains("\$system .= pg_site_datetime_bind(array(", $screen, 'the custom form screen binds its fields by id');
	pg_assert_contains("'time' => \$time_fields", $screen, 'time fields too, on the time row alone');

	foreach (array('get_form_item_view.php', 'get_form_list_view.php') as $path) {
		foreach (array("'data-pg-date' => ''", "'data-pg-datetime' => ''", "'data-pg-time' => ''") as $hook) {
			pg_assert_contains($hook, $source($path), $path . ' hooks ' . $hook);
		}
	}

	pg_assert_contains("'data-pg-datetime' => '')) . ' &nbsp; ' .", $source('get_page_content.php'), 'the classic comment publish time');
	pg_assert_contains('name="publish_date_and_time" maxlength="19" size="21" data-pg-datetime', $source('includes/fn/widgets_comments.php'), 'the comments widget publish time');

	foreach (array('get_custom_form_screen_content.php', 'get_form_item_view.php', 'get_form_list_view.php', 'includes/fn/content.php', 'includes/fn/widgets_comments.php') as $path) {
		pg_assert_false((bool) preg_match('/\\.(date)?(time)?picker\\(datetimepicker_options\\)/', $source($path)), $path . ' calls no jQuery UI picker');
	}

	foreach (array('frontend.src.js', 'frontend.min.js') as $path) {
		$js = $source($path);
		pg_assert_false(strpos($js, '.datetimepicker('), $path . ': the comment publish time is no jQuery UI picker');
		pg_assert_contains('window.pgFields.focus(', $js, $path . ': focus lands on the box people type in');
	}
}

function test_site_datetime_checkout_renderers_hook_their_fields()
{
	$source = function ($path) {
		return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
	};

	// The classic pages print the hook on their own boxes: recurring start
	// dates, gift card delivery dates, custom arrival dates.
	foreach (array(
		'get_shopping_cart.php' => 2,
		'get_express_order.php' => 3,
		'get_order_form.php' => 1,
		'get_shipping_address_and_arrival.php' => 1,
	) as $path => $count) {
		pg_assert_same($count, substr_count($source($path), "'data-pg-date' => ''"), $path . ' hooks its own date boxes');
	}

	// A custom layout's boxes come from the site's files: bound by id, a time
	// box on the time row alone, a custom arrival date among the dates.
	foreach (array('get_shopping_cart.php', 'get_express_order.php', 'get_billing_information.php', 'get_shipping_address_and_arrival.php') as $path) {
		pg_assert_contains('$system .= pg_site_datetime_bind(array(', $source($path), $path . ' binds its layout\'s boxes by id');
		pg_assert_contains("'time' => \$time_fields,", $source($path), $path . ' binds its time boxes too');
	}
	pg_assert_contains("\$system .= pg_site_datetime_bind(array('date' => \$start_date_fields));", $source('get_order_form.php'), 'the order form binds its start dates');
	pg_assert_contains("'time_fields' => \$time_fields,", $source('includes/fn/custom_form.php'), 'prepare_custom_form() names the time boxes of a custom shipping or billing form');
	pg_assert_contains("\$date_fields[] = \$prefix . 'custom_arrival_date_' . \$custom_arrival_date;", $source('get_express_order.php'), 'the express order binds its custom arrival dates');
	pg_assert_contains("\$date_fields[] = 'custom_arrival_date_' . \$custom_arrival_date;", $source('get_shipping_address_and_arrival.php'), 'the shipping page binds its custom arrival dates');

	// The page brings the picker options with the control
	// (pg_site_datetime_inject()); no jQuery UI picker is left.
	foreach (array('get_shopping_cart.php', 'get_express_order.php', 'get_billing_information.php', 'get_shipping_address_and_arrival.php', 'get_order_form.php') as $path) {
		pg_assert_false((bool) preg_match('/\\.(date)?(time)?picker\\(/', $source($path)), $path . ' calls no jQuery UI picker');
		pg_assert_false(strpos($source($path), 'get_date_picker_format()') !== false, $path . ' prints no picker options of its own');
	}

	foreach (array('frontend.src.js', 'frontend.min.js') as $path) {
		$js = $source($path);
		pg_assert_false(strpos($js, 'datetimepicker_options') !== false, $path . ': no jQuery UI picker is set up');
		pg_assert_contains('software.focus_date_field(document.getElementById(', $js, $path . ': the custom arrival date\'s radio opens its calendar');
		pg_assert_false(strpos($js, '.attr("checked"') !== false || strpos($js, ".attr('checked'") !== false, $path . ': the radio is read and ticked by its checked property');
	}
}
