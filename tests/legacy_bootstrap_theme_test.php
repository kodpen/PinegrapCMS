<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a Legacy design's Theme file read as a palette and a look
 * (includes/fn/legacy_bootstrap_theme.php): colour values, the starting
 * sites' colour sheets and theme, fonts, the look's stylesheet, the card and
 * the screen's wiring.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_bootstrap_theme.php');

function test_lbs_theme_hex()
{
	pg_assert_same('#47b475', pg_lbs_theme_hex('#47B475'));
	pg_assert_same('#ffffff', pg_lbs_theme_hex('#fff !important'));
	pg_assert_same('#0000ff', pg_lbs_theme_hex('Blue'));
	pg_assert_same('#8a2be2', pg_lbs_theme_hex('BlueViolet'));
	pg_assert_same('#222222', pg_lbs_theme_hex('rgba(34, 34, 34, 0.9)'));
	pg_assert_same('#0a141e', pg_lbs_theme_hex('rgb(10,20,30)'));
	pg_assert_same(null, pg_lbs_theme_hex('rgba(0, 0, 0, 0)'), 'a see-through colour is none');
	pg_assert_same(null, pg_lbs_theme_hex('transparent'));
	pg_assert_same(null, pg_lbs_theme_hex('var(--x)'));
	pg_assert_same(null, pg_lbs_theme_hex('#12345'));
	pg_assert_same(148, count(pg_lbs_theme_named_colors()));
	pg_assert_true(pg_lbs_theme_hsl('#808080')['s'] < 0.01);
	pg_assert_true(pg_lbs_theme_hsl('#47b475')['s'] > 0.4);
}

function test_lbs_theme_read_colour_sheet()
{
	// FoundryGreen.css, the starting sites' colour sheet (the Theme file).
	$green = "/*Primary colors */\na {\n  color: #47b475;\n}\n.bg-primary {\n  background: #47b475 !important;\n}\n"
		. ".btn,\na.btn:visited {\n  color: #47b475;\n  border-color: #47b475;\n  background-color: #fff;\n}\n.bg-dark { background: #292929; }";
	$theme = "body { font-family: \"Lato\", \"Helvetica Neue\", Helvetica, Arial, sans-serif; color: #777; }\n"
		. "h1, h2, h3, h4, h5, h6, .h1 { font-family: \"Raleway\", \"Helvetica Neue\", Helvetica, Arial, sans-serif; }\n"
		. "@media (max-width: 767px) { .bg-primary { background: #e31d3b; } }\n.has-dropdown:after { font-family: 'themify'; }";
	pg_assert_same(array('primary' => '#47b475', 'secondary' => '#292929',
		'body' => '"Lato", "Helvetica Neue", Helvetica, Arial, sans-serif', 'heading' => '"Raleway", "Helvetica Neue", Helvetica, Arial, sans-serif'),
		pg_lbs_theme_read(array($green, $theme)), 'the Theme file first, the fonts from the next sheet');

	$blue = "a { color: Blue; }\n.label { background: Blue; }";
	$read = pg_lbs_theme_read(array($blue));
	pg_assert_same('#0000ff', $read['primary'], 'a colour name');
	pg_assert_same('#292929', $read['secondary'], 'no dark colour: a dark grey');
	pg_assert_same('', $read['body']);

	// No rule names the colour: the most used colour that is not a grey.
	$plain = ".x { color: #e31d3b } .y { border-color: #e31d3b } .z { background: #fff } .w { color: #3355ff } body { color: #222 }";
	pg_assert_same(array('primary' => '#e31d3b', 'secondary' => '#222222', 'body' => '', 'heading' => ''), pg_lbs_theme_read(array($plain)));
	pg_assert_same(array('primary' => '', 'secondary' => '', 'body' => '', 'heading' => ''), pg_lbs_theme_read(array('.a { color: #777 }')), 'greys only: no palette');
	pg_assert_same('#47b475', pg_lbs_theme_read(array('a { color: #ccc } .btn-primary { background-color: #47b475 }'))['primary'], 'a grey link colour is passed over');
}

function test_lbs_theme_font_and_look()
{
	pg_assert_same('"Lato", Arial, sans-serif', pg_lbs_theme_font('"Lato",Arial ,  sans-serif !important'));
	pg_assert_same('', pg_lbs_theme_font("'themify'"));
	pg_assert_same('', pg_lbs_theme_font('x; } body { color: red'));
	pg_assert_same('', pg_lbs_theme_font('inherit'));

	$css = pg_lbs_theme_look_css('home-1 seti', '"Lato", sans-serif', '"Raleway", sans-serif');
	pg_assert_same(1, preg_match('~^/\*! pg-theme \{"kind":"look","name":"home-1 seti","base":"' . pg_design_default_look() . '","controls":\{\}\} \*/~', $css));
	pg_assert_contains('--pg-font-body: "Lato", sans-serif;', $css);
	pg_assert_contains('--pg-font-heading: "Raleway", sans-serif;', $css);
	pg_assert_contains('--pg-font-heading: var(--pg-font-body);', pg_lbs_theme_look_css('x', '"Lato"', ''), 'no heading font: the text font');
	$plain = pg_lbs_theme_look_css('x', '', '');
	$base = pg_design_look_tokens(pg_design_default_look());
	pg_assert_contains('--pg-font-body: ' . $base['--pg-font-body'] . ';', $plain, 'no font: the look keeps its own');
}

function test_lbs_theme_card()
{
	$record = array('trial' => 30, 'name' => 'T');
	$none = pg_lbs_theme_card_html(12, $record, array('palette' => '', 'look' => ''));
	pg_assert_contains('name="bootstrap_theme" value="make"', $none);
	pg_assert_contains('name="design" value="12"', $none);
	pg_assert_contains('id="ltd-theme-12"', $none);
	pg_assert_same('', pg_lbs_theme_card_html(12, $record, null), 'no look columns: nothing offered');

	$record['theme'] = array('palette' => 5, 'look' => 6, 'primary' => '#47b475', 'secondary' => '#292929', 'body' => '"Lato"', 'heading' => '"<b>"');
	$worn = pg_lbs_theme_card_html(12, $record, array('palette' => 'file-5', 'look' => 'file-6'));
	pg_assert_false(strpos($worn, 'bootstrap_theme') !== false, 'made once');
	pg_assert_contains('#47b475', $worn);
	pg_assert_contains('&quot;&lt;b&gt;&quot;', $worn, 'escaped');
	pg_assert_contains(h(lang('The trial design wears them. In the Page Design Studio, Settings, the Theme file can now be taken off.')), $worn);
	pg_assert_contains(h(lang('The trial design wears another palette or look now; both stay in the Themes tab.')), pg_lbs_theme_card_html(12, $record, array('palette' => 'file-9', 'look' => 'file-6')));
}

function test_lbs_theme_wiring()
{
	$screen = (string)file_get_contents(PG_FUNCTIONS_DIR . '/legacy_to_designer.php');
	$post = strpos($screen, "if (isset(\$_POST['bootstrap_theme']))");
	pg_assert_true($post !== false, 'the screen has the branch');
	pg_assert_true(strpos($screen, 'validate_token_field()') < $post, 'the token is checked first');
	pg_assert_true(strpos($screen, 'pg_lbs_theme_make(', $post) < strpos($screen, 'pg_ltd_plan(', $post), 'it redirects before the dry run');
	pg_assert_contains("'theme' => \$trial !== null ? pg_lbs_theme_card_html(", $screen);
	$module = (string)file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/legacy_bootstrap_theme.php');
	$make = strpos($module, 'function pg_lbs_theme_make(');
	pg_assert_true(strpos($module, "pg_design_theme_save_file('palette'", $make) < strpos($module, 'UPDATE style SET style_palette', $make), 'the files are saved before the trial wears them');
	pg_assert_true(strpos($module, "pg_design_theme_header(array('kind' => 'palette'", $make) !== false, 'the palette carries its header, as the editor saves one');
}
