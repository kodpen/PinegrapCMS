<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the private label screen's save (private_label.php,
 * pg_private_label_config_content() in includes/fn/core.php): every value
 * goes into data/config.php as it was typed and reads back unchanged.
 *
 * The config files are run in a separate PHP process through
 * config_file_test_values_of() (tests/config_file_test.php), because a
 * config.php defines constants and this process cannot define them twice.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A config.php as a fresh installation writes it.
function private_label_config_test_file()
{
	return pg_config_install_content(function ($name) {
		$fields = array('db_host' => 'db', 'db_username' => 'site', 'db_password' => 'p?>w', 'db_database' => 'site');
		return isset($fields[$name]) ? $fields[$name] : '';
	}, '0123456789abcdef0123456789abcdef');
}

// What the constants hold before a save: LOGO_URL and
// CONTROL_PANEL_STYLESHEET_URL have init.php's defaults, the footer links
// are not defined. $defined overrides single constants.
function private_label_config_test_current($defined = array())
{
	$current = array();

	foreach (pg_private_label_config_keys() as $constant) {
		$current[$constant] = null;
	}

	$current['LOGO_URL'] = '/pinegrap/assets/images/logo.png?v=1';
	$current['CONTROL_PANEL_STYLESHEET_URL'] = '/pinegrap/assets/css/backend.src.css?v=1';

	return array_merge($current, $defined);
}

// Values the screen used to change (it deleted every quote) or that would
// break the define line if written as they are.
function private_label_config_test_posted()
{
	return array(
		'logo_url' => "https://cdn.example.test/brand's logo.png?a=1&b=2",
		'control_panel_stylesheet_url' => 'https://cdn.example.test/panel.css?x="1"&y=<2>',
		'footer_link_1_label' => "O'Neil & Sons",
		'footer_link_1_url' => "https://example.test/it's?>here",
		'footer_link_2_label' => 'ends with a backslash\\',
		'footer_link_2_url' => '',
		'footer_link_3_label' => '$1 \\1 {$x}',
		'footer_link_3_url' => 'https://example.test/3',
		'footer_link_4_label' => '0',
		'footer_link_4_url' => '',
		'footer_link_5_label' => 'üñí€ şğı',
		'footer_link_5_url' => '',
	);
}

// Every value is stored as it was typed and comes back from config.php byte
// for byte; an empty one is not written at all.
function test_private_label_config_values_read_back_as_typed()
{
	$posted = private_label_config_test_posted();
	$content = pg_private_label_config_content(private_label_config_test_file(), true, $posted, private_label_config_test_current());

	$expected = array('DB_PASSWORD' => 'p?>w');

	foreach (pg_private_label_config_keys() as $field => $constant) {
		$expected[$constant] = ($posted[$field] === '') ? null : $posted[$field];
	}

	pg_assert_same($expected, config_file_test_values_of($content, array_keys($expected), 'saved'));
	pg_assert_same(1, substr_count($content, "define('FOOTER_LINK_1_LABEL'"), 'one line per setting');
}

// A second save replaces each line; one emptied removes its define, so the
// default logo or no link applies again.
function test_private_label_config_second_save_replaces_and_removes()
{
	$first = pg_private_label_config_content(private_label_config_test_file(), true, private_label_config_test_posted(), private_label_config_test_current());

	$posted = array_merge(private_label_config_test_posted(), array(
		'logo_url' => '',
		'footer_link_1_label' => "Rock 'n' roll",
		'footer_link_3_url' => '',
	));

	$current = private_label_config_test_current(array(
		'LOGO_URL' => "https://cdn.example.test/brand's logo.png?a=1&b=2",
		'FOOTER_LINK_1_LABEL' => "O'Neil & Sons",
		'FOOTER_LINK_3_URL' => 'https://example.test/3',
	));

	$second = pg_private_label_config_content($first, true, $posted, $current);

	pg_assert_same(1, substr_count($second, "define('FOOTER_LINK_1_LABEL'"), 'replaced, not added');

	pg_assert_same(array(
		'LOGO_URL' => null,
		'FOOTER_LINK_1_LABEL' => "Rock 'n' roll",
		'FOOTER_LINK_3_URL' => null,
		'FOOTER_LINK_1_URL' => "https://example.test/it's?>here",
	), config_file_test_values_of($second, array('LOGO_URL', 'FOOTER_LINK_1_LABEL', 'FOOTER_LINK_3_URL', 'FOOTER_LINK_1_URL'), 'second save'));
}

// A value the constant already holds leaves the file as it is, so a define
// written by hand in another form is not given a second line.
function test_private_label_config_unchanged_values_leave_the_file()
{
	$content = "<?php\ndefine(\"FOOTER_LINK_1_LABEL\", \"Help\");\n?>";

	$posted = array('footer_link_1_label' => 'Help');
	$current = private_label_config_test_current(array('FOOTER_LINK_1_LABEL' => 'Help'));

	pg_assert_same($content, pg_private_label_config_content($content, true, $posted, $current), 'untouched');

	// Every box empty on a file without private label lines: the logo and
	// the stylesheet keep their defaults and nothing is written.
	$installed = private_label_config_test_file();

	pg_assert_same($installed, pg_private_label_config_content($installed, true, array(), private_label_config_test_current()), 'defaults');
}

// Turning the private label off removes every one of its defines.
function test_private_label_config_disabled_removes_every_define()
{
	$saved = pg_private_label_config_content(private_label_config_test_file(), true, private_label_config_test_posted(), private_label_config_test_current());

	$off = pg_private_label_config_content($saved, false, private_label_config_test_posted(), private_label_config_test_current());

	pg_assert_same(private_label_config_test_file(), $off, 'back to the installed file');
}

// A text box never sends a control character or a list; one in a forged
// request would put a line break inside the define, where the next save
// could no longer find it.
function test_private_label_config_form_value_drops_control_characters()
{
	pg_assert_same('ab', pg_config_form_value("a\nb"), 'line break');
	pg_assert_same('ab', pg_config_form_value("a\r\x00\x1F\x7Fb"), 'control characters');
	pg_assert_same('x y', pg_config_form_value("  x y \t"), 'surrounding space');
	pg_assert_same("it's \\", pg_config_form_value("it's \\"), 'quote and backslash kept');
	pg_assert_same('', pg_config_form_value(array('x')), 'list');
	pg_assert_same('', pg_config_form_value(null), 'missing');
}

// The footer links as the Appearance pane posts them: footer_link_<n>_label
// and _url in row order, five pairs.
function private_label_links_test_values($links)
{
	$values = array();

	for ($n = 1; $n <= 5; $n++) {
		$link = isset($links[$n - 1]) ? $links[$n - 1] : array('', '');
		$values['footer_link_' . $n . '_label'] = $link[0];
		$values['footer_link_' . $n . '_url'] = $link[1];
	}

	return $values;
}

// The links that hold a label or an address keep their order and close up
// to the top; a link removed or emptied in its drawer moves the ones under
// it up, and every one of the ten fields is named.
function test_private_label_links_order_closes_gaps()
{
	$posted = private_label_links_test_values(array(array('Help', '/help'), array('', ''), array('', '/terms'), array(' ', ' '), array('Blog', '')));
	$posted['logo_url'] = '/logo.png';

	pg_assert_same(private_label_links_test_values(array(array('Help', '/help'), array('', '/terms'), array('Blog', ''))), pg_private_label_links_order($posted), 'packed, other fields left out');
	pg_assert_same(private_label_links_test_values(array()), pg_private_label_links_order(array()), 'nothing posted is five empty links');
	pg_assert_same(private_label_links_test_values(array()), pg_private_label_links_order(array('footer_link_1_label' => array('x'))), 'a list is no value');
}

// Move up, Move down and Remove name a row as posted (1 to 5): the row
// changes place with its neighbour or leaves, and the rest close up.
function test_private_label_links_order_moves_and_removes()
{
	$posted = private_label_links_test_values(array(array('A', '/a'), array('B', '/b'), array('C', '/c')));

	pg_assert_same(private_label_links_test_values(array(array('B', '/b'), array('A', '/a'), array('C', '/c'))), pg_private_label_links_order($posted, 'up:2'), 'B up');
	pg_assert_same(private_label_links_test_values(array(array('A', '/a'), array('C', '/c'), array('B', '/b'))), pg_private_label_links_order($posted, 'down:2'), 'B down');
	pg_assert_same(private_label_links_test_values(array(array('A', '/a'), array('C', '/c'))), pg_private_label_links_order($posted, 'remove:2'), 'B removed, C moves up');
	pg_assert_same(private_label_links_test_values(array(array('B', '/b'), array('C', '/c'))), pg_private_label_links_order($posted, 'remove:1'), 'the first removed');

	foreach (array('up:1', 'down:3', 'down:5', 'remove:4', 'remove:0', 'up:9', 'drop:2', 'up', '') as $action) {
		pg_assert_same($posted, pg_private_label_links_order($posted, $action), $action . ': nothing to do');
	}

	pg_assert_same($posted, pg_private_label_links_order($posted, array('up:2')), 'an action posted as a list is none');

	// A row emptied in its drawer and moved in the same save: the rows are
	// the posted ones, so the move names the row it was pressed on.
	$gap = private_label_links_test_values(array(array('A', '/a'), array('', ''), array('C', '/c')));
	pg_assert_same(private_label_links_test_values(array(array('C', '/c'), array('A', '/a'))), pg_private_label_links_order($gap, 'up:3'), 'C passes the emptied row and A');
	pg_assert_same(private_label_links_test_values(array(array('A', '/a'), array('C', '/c'))), pg_private_label_links_order($gap, 'down:2'), 'an emptied row does not move');
}
