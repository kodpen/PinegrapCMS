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
