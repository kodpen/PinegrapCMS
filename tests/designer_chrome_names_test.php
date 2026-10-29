<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the visual editor's own dialogs: the Select Page and Import
 * dialogs follow the dark theme their inline background draws, the three-way
 * question dialog prints Bootstrap 6 button classes, close buttons carry a
 * name, the New File labels point at their fields, and chrome texts and
 * percent figures go through _sdT().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_chrome_names_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_designer_chrome_names_dark_dialogs_carry_theme()
{
	$source = designer_chrome_names_source('includes/designer_screen.php');

	foreach (array('sdPickPageModal', 'sdImportModal') as $id) {
		pg_assert_true((bool) preg_match('/<dialog [^>]*id="' . $id . '"[^>]*data-bs-theme="dark"/', $source), $id . ' is dark themed');
	}
}

function test_designer_chrome_names_choose_buttons_are_bs6()
{
	$source = designer_chrome_names_source('assets/js/style_designer.src.js');

	pg_assert_true(strpos($source, "'btn btn-sm btn-' + esc(c.variant") === false, 'no v5 btn-<variant> class in sdChoose');
	pg_assert_contains("function _sdChoiceBtnClass(variant)", $source, 'variant mapper');
	pg_assert_contains("'btn-outline theme-'", $source, 'outline variant maps to btn-outline theme-*');
	pg_assert_contains("'btn-solid theme-'", $source, 'solid variant maps to btn-solid theme-*');
}

function test_designer_chrome_names_close_buttons_are_named()
{
	$source = designer_chrome_names_source('assets/js/style_designer.src.js');

	pg_assert_true(strpos($source, '<button type="button" class="btn-close" data-bs-dismiss="dialog"></button>') === false, 'every dialog btn-close has a name');
	foreach (array('sd-gf-modal-close', 'sd-as-co-close', 'sd-fp-close', 'sd-snap-close-btn') as $id) {
		pg_assert_true((bool) preg_match('/id="' . $id . '"[^>]*aria-label="\' \+ esc\(_sdT\(\'Close\'\)\)/', $source), $id . ' is named');
	}
}

function test_designer_chrome_names_new_file_labels_bound()
{
	$source = designer_chrome_names_source('assets/js/style_designer.src.js');

	foreach (array('sd-as-co-name', 'sd-as-co-folder', 'sd-as-co-desc') as $id) {
		pg_assert_contains('<label class="sd-as-co-label" for="' . $id . '">', $source, $id . ' label');
	}
}

function test_designer_chrome_names_design_chooser_error_tone()
{
	$source = designer_chrome_names_source('includes/fn/designer.php');

	pg_assert_true(strpos($source, 'list-group-item small text-danger') === false, 'no v5 text-danger');
	pg_assert_contains('list-group-item small fg-danger', $source, 'fg-danger failure row');
}

function test_designer_chrome_names_texts_translated()
{
	$source = designer_chrome_names_source('assets/js/style_designer.src.js');

	foreach (array('Dropdown', 'Nav', 'Navbar') as $text) {
		pg_assert_true(strpos($source, "labelEl.textContent = '" . $text . "';") === false, $text . ' context label translated');
	}
	pg_assert_true(strpos($source, "'Radial') + ' Gradient'") === false, 'gradient trigger label translated');
	pg_assert_true(strpos($source, "label = 'None';") === false, 'None trigger label translated');
	pg_assert_true(strpos($source, 'placeholder="id of labelling element"') === false, 'labelledby placeholder translated');
	pg_assert_true(strpos($source, 'placeholder="id of describing element"') === false, 'describedby placeholder translated');
	pg_assert_contains("[[_sdT('Container'), 'container'], [_sdT('Row'), 'row'], [_sdT('Column'), 'col']]", $source, 'palette layout names translated');
	pg_assert_contains("return _sdT('{var:1}%', value);", $source, 'percent bubble localised');
	pg_assert_true(strpos($source, "Math.round(canvasZoom * 100) + '%'") === false, 'zoom label localised');
}
