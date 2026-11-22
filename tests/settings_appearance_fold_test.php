<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Appearance as folded rows: each part's line of state, the
 * rows' order, the pane drawn for a manager and for an administrator, no old
 * switch or hiding switch left, Editor version as a segmented control, and
 * the names that stay (card ids, field names, Reset widgets, the save, the
 * search words).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_SETTINGS_ENTRY')) {
	define('PG_SETTINGS_ENTRY', true);
}

if (!defined('PG_SETTINGS_MENU')) {
	define('PG_SETTINGS_MENU', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/fold.php');
require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/appearance_settings_fold.php');

function settings_appearance_fold_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The pane as appearance.php draws it, with the variables prep.php prepares
// for a reader of this role.
function settings_appearance_fold_test_pane($role)
{
	$user = array('role' => $role);
	$custom_css = 'body { color: red; }';
	$advanced_visual_effects_checked = ' checked="checked"';
	$dashboard_card_shown = pg_dashboard_appearance_allowed($role);
	$dashboard_appearance_ready = true;
	$dashboard_appearance = pg_dashboard_appearance(array('widget_theme' => 'glass', 'panel_backdrop' => 'auto'));
	$dashboard_choice_labels = array('widget_theme' => array('flat' => 'Flat Tint', 'neon' => 'Neon Outline', 'glass' => 'Glass', 'aurora' => 'Aurora'));
	$dashboard_refresh = 60;
	$dashboard_reset_done = false;
	$output_dashboard_theme_options = '';
	$output_dashboard_backdrop_options = '';
	$page_editor_version_latest_checked = ' checked="checked"';
	$page_editor_version_previous_checked = '';
	$page_editor_font_checked = ' checked="checked"';
	$page_editor_font_size_checked = '';
	$page_editor_font_style_checked = '';
	$page_editor_font_color_checked = '';
	$page_editor_background_color_checked = '';
	$spell_checker_engine_info = array('name' => 'Aspell');
	$pg_settings_cards = array();
	$pg_settings_scripts = array();

	include(PG_FUNCTIONS_DIR . '/includes/settings/appearance.php');

	return $pg_settings_cards;
}

function test_settings_appearance_fold_order_and_names()
{
	pg_assert_same(array('theme', 'dashboard', 'editor', 'private-label'), pg_appearance_fold_order(), 'the rows in the description\'s order');

	$names = array();

	foreach (pg_appearance_fold_order() as $key) {
		$face = pg_appearance_fold_face($key);
		$names[] = $face['name'];
		pg_assert_true(strpos($face['icon'], 'bi-') === 0, $key . ': an icon');
		pg_assert_false(stripos($face['name'], 'Enable') === 0, $key . ': a name never starts with Enable');
	}

	pg_assert_same(array(lang('Panel look'), lang('Dashboard'), lang('Rich-text editor'), lang('Private label branding')), $names, 'the rows\' names');
}

function test_settings_appearance_fold_lines()
{
	pg_assert_same(lang('Acrylic on') . ', ' . lang('panel CSS set'), pg_appearance_fold_theme(true, 'a { }')['line'], 'acrylic on, panel CSS set');
	pg_assert_same(lang('Acrylic off') . ', ' . lang('no panel CSS'), pg_appearance_fold_theme(false, "  \n")['line'], 'blank panel CSS is none');

	pg_assert_same(lang(array('string' => '{var:1} cards', 'vars' => 'Glass')) . ' · ' . lang(array('string' => 'refresh every {var:1} seconds', 'vars' => '60')), pg_appearance_fold_dashboard('Glass', 60)['line'], 'the card style and this sign-in\'s refresh');
	pg_assert_same(lang(array('string' => 'Refresh every {var:1} seconds', 'vars' => '30')), pg_appearance_fold_dashboard('', 30)['line'], 'before the upgrade, the refresh alone');

	pg_assert_same(lang('Latest editor'), pg_appearance_fold_editor('latest')['line'], 'latest');
	pg_assert_same(lang('Previous editor'), pg_appearance_fold_editor('previous')['line'], 'previous');

	pg_assert_same(lang('On'), pg_appearance_fold_private_label(true)['line'], 'private label on');
	pg_assert_same(lang('Off'), pg_appearance_fold_private_label(false)['line'], 'private label off');

	foreach (array(pg_appearance_fold_theme(true, ''), pg_appearance_fold_dashboard('', 60), pg_appearance_fold_editor('latest'), pg_appearance_fold_private_label(false)) as $part) {
		pg_assert_false($part['attention'], 'no row asks for attention');
	}
}

function test_settings_appearance_fold_pane_is_rows()
{
	$pane = settings_appearance_fold_test_source('includes/settings/appearance.php');

	pg_assert_contains('$pg_settings_cards[] = pg_settings_fold($appearance_rows);', $pane, 'the pane is one list of folded rows');
	pg_assert_same(1, substr_count($pane, '$pg_settings_cards[]'), 'no card outside the rows');
	pg_assert_same(0, substr_count($pane, 'collapse-switcher'), 'no switch hides fields');
	pg_assert_same(0, substr_count($pane, 'class="switch"'), 'no old switch drawing');
	pg_assert_same(3, substr_count($pane, 'class="pg-switch"'), 'the acrylic effect, the editor tools (one loop) and private label are pg-switch');
	pg_assert_false(strpos($pane, 'spell_check_engine_info') !== false, 'the spell checker is no disabled box');

	foreach (array('Enable Transparent Acrylic Effect', 'Custom CSS', 'Refresh Time', 'Reset Widgets', 'Editor Version', 'Enable private label branding', 'Spell Checker Engine') as $old) {
		pg_assert_false(strpos($pane, "lang('" . $old . "')") !== false, 'no "' . $old . '" label');
	}
}

// The administrator's pane reads the install's paths (the default logo and
// stylesheet), constants other tests decide for themselves: it is drawn in a
// process of its own. '' when it cannot be.
function settings_appearance_fold_test_administrator_pane()
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return '';
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgappearancefold');

	if ($file === false) {
		return '';
	}

	file_put_contents($file, '<?php' . "\n"
		. "define('PG_TEST_RUNNER', true);\n"
		. "define('PATH', '/');\n"
		. "define('SOFTWARE_DIRECTORY', 'pinegrap');\n"
		. "define('OUTPUT_PATH', '/');\n"
		. "define('OUTPUT_SOFTWARE_DIRECTORY', 'pinegrap');\n"
		. 'require ' . var_export(PG_FUNCTIONS_DIR . '/functions.php', true) . ";\n"
		. 'require ' . var_export(__FILE__, true) . ";\n"
		. "echo json_encode(settings_appearance_fold_test_pane(0));\n");

	$out = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$cards = json_decode(implode("\n", $out), true);

	pg_assert_same(0, $code, 'drawn: ' . implode("\n", array_slice($out, 0, 8)));
	pg_assert_true(is_array($cards) && (count($cards) === 1), 'one list of rows');

	return is_array($cards) ? (string) reset($cards) : '';
}

function test_settings_appearance_fold_administrator_pane()
{
	$html = settings_appearance_fold_test_administrator_pane();

	if ($html === '') {
		return;
	}

	foreach (array('theme', 'dashboard', 'editor', 'private-label') as $key) {
		pg_assert_contains('id="pg-fold-' . $key . '"', $html, $key . ': a row');
		pg_assert_contains('<div id="pgset-' . $key . '" class="pg-set-card"', $html, $key . ': its card id kept inside the row');
	}

	pg_assert_true(strpos($html, 'id="pg-fold-theme"') < strpos($html, 'id="pg-fold-dashboard"') && strpos($html, 'id="pg-fold-editor"') < strpos($html, 'id="pg-fold-private-label"'), 'in order');
	pg_assert_contains('aria-expanded="true" aria-controls="pg-fold-theme-body"', $html, 'Panel look arrives open');
	pg_assert_contains(h(lang('Acrylic on') . ', ' . lang('panel CSS set')), $html, 'Panel look says its state');

	// Panel look.
	pg_assert_contains('<label class="pg-text-2" for="advanced_visual_effects">' . lang('Transparent acrylic effect') . '</label>', $html, 'the acrylic switch');
	pg_assert_contains(lang('For everyone who uses the panel. Turn it off if the panel feels slow.'), $html, 'its line says who it reaches');
	pg_assert_contains('<label class="form-label" for="custom_css">' . lang('Panel CSS') . '</label>', $html, 'Custom CSS is Panel CSS');
	pg_assert_contains(lang('Styles the control panel only, never the site.') . ' ' . lang('A whole stylesheet can be set under Private label branding.'), $html, 'its line names the private label stylesheet');

	// Dashboard in two groups, Reset widgets under For everyone.
	$everyone = strpos($html, lang('For everyone') . '</h3>');
	$you = strpos($html, lang('Only for you, until you sign out') . '</h3>');
	pg_assert_true($everyone !== false && $you !== false && $everyone < $you, 'For everyone, then Only for you');
	pg_assert_true($everyone < strpos($html, 'name="dashboard_widget_theme"') && strpos($html, 'name="dashboard_panel_backdrop"') < $you, 'card style and backdrop are for everyone');
	pg_assert_true(strpos($html, 'name="dashboard_reset_widgets" value="1"') > $everyone && strpos($html, 'name="dashboard_reset_widgets"') < $you, 'Reset widgets is for everyone');
	pg_assert_true(strpos($html, 'name="dashboard_refresh"') > $you, 'the refresh time is only for you');
	pg_assert_contains('<label for="dashboard_refresh" class="form-label">' . lang('Refresh every') . '</label>', $html, 'Refresh every');

	// Rich-text editor.
	pg_assert_contains('<div class="pg-seg flex-wrap">', $html, 'Editor version a segmented control');
	pg_assert_contains('<label class="pg-seg-item" for="page_editor_version_latest"><input value="latest" class="visually-hidden" type="radio" id="page_editor_version_latest" name="page_editor_version"  checked="checked">', $html, 'Latest as a segment, checked');

	foreach (array('page_editor_font' => 'Font', 'page_editor_font_size' => 'Font size', 'page_editor_font_style' => 'Font style', 'page_editor_font_color' => 'Text colour button', 'page_editor_background_color' => 'Background colour button') as $name => $label) {
		pg_assert_contains('id="' . $name . '" name="' . $name . '"/>', $html, $name . ' posts under its name');
		pg_assert_contains('<label class="pg-text-2" for="' . $name . '">' . lang($label) . '</label>', $html, $name . ': ' . $label);
	}

	pg_assert_contains('<div class="pg-text-meta">' . h(lang(array('string' => 'Spell checker: {var:1}', 'vars' => 'Aspell'))) . '</div>', $html, 'the spell checker a state line');

	// Private label: its fields drawn while off.
	pg_assert_contains('id="private_label" name="private_label"', $html, 'the switch');
	pg_assert_false(strpos($html, 'id="private_label" name="private_label" aria-describedby="private_label_help" checked') !== false, 'off here');
	pg_assert_contains('<div class="col-12" id="private_label_fields">', $html, 'the fields drawn while off');

	foreach (array('private_label_card', 'logo_url', 'control_panel_stylesheet_url', 'footer_link_1_label', 'footer_link_5_url') as $name) {
		pg_assert_contains('name="' . $name . '"', $html, $name . ' posts under its name');
	}
}

function test_settings_appearance_fold_manager_pane()
{
	$cards = settings_appearance_fold_test_pane(2);
	$html = $cards[0];

	pg_assert_contains('id="pg-fold-theme"', $html, 'Panel look');
	pg_assert_contains('id="pg-fold-editor"', $html, 'Rich-text editor');
	pg_assert_false(strpos($html, 'pgset-dashboard') !== false, 'a manager has no Dashboard row');
	pg_assert_false(strpos($html, 'pgset-private-label') !== false, 'a manager has no Private label row');
	pg_assert_false(strpos($html, 'dashboard_reset_widgets') !== false, 'nor Reset widgets');
	pg_assert_contains(lang('Styles the control panel only, never the site.') . '</div>', $html, 'Panel CSS names no stylesheet a manager cannot reach');
}

function test_settings_appearance_fold_save_and_search_stay()
{
	$save = settings_appearance_fold_test_source('includes/settings/appearance.save.php');

	pg_assert_contains("post_value('dashboard_reset_widgets')", $save, 'Reset widgets is still read by the save');
	pg_assert_false(strpos($save, 'fold') !== false, 'the save knows nothing of rows');

	foreach (array('custom_css', 'advanced_visual_effects', 'page_editor_version', 'dashboard_refresh', 'private_label') as $name) {
		pg_assert_contains("post_value('" . $name . "')", $save, $name . ' still read under its name');
	}

	$source = settings_appearance_fold_test_source('includes/settings/registry.php');
	$start = strpos($source, "\n        'appearance' => array(");
	$appearance = substr($source, $start, strpos($source, "\n        ),\n", $start) - $start);

	pg_assert_contains("'pgset-theme'     => lang('Panel look')", $appearance, 'the section is Panel look');
	pg_assert_contains("'pgset-theme'     => pg_settings_keywords(lang('theme, appearance, color, custom css, panel css, acrylic'))", $appearance, 'the search finds panel css and still custom css');

	$anchors = pg_settings_legacy_anchors();
	pg_assert_same(array('appearance', 'pgset-theme'), $anchors['pgset-theme'], 'an old Theme link lands on Panel look');
}
