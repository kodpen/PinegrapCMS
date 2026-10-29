<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the visual editor's Options, Appearance and Styles panels:
 * one naming pass ties every unnamed control to its row label (and its
 * screen-size label), section headers and screen-size toggles work from the
 * keyboard and report their state, the Styles inspector names its inputs in
 * the editor language, and no light border sits on the dark options panel.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_panel_names_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function designer_panel_names_function($source, $name)
{
	$start = strpos($source, 'function ' . $name . '(');
	if ($start === false) {
		return '';
	}
	$end = strpos($source, "\n    }\n", $start);
	return substr($source, $start, $end === false ? 4000 : $end - $start);
}

function test_designer_panel_names_pass_ties_labels()
{
	$source = designer_panel_names_source('assets/js/style_designer.src.js');
	$pass   = designer_panel_names_function($source, '_sdNamePropsPanel');

	pg_assert_true($pass !== '', 'the naming pass exists');
	pg_assert_contains("'.sd-sp-bp-item'", $pass, 'screen-size items are named');
	pg_assert_contains("'.sd-sp-bp-lbl'", $pass, 'by their screen-size label');
	pg_assert_contains("'.sd-prop-row, .sd-sp-row'", $pass, 'rows are named by their label');
	pg_assert_contains("setAttribute('aria-labelledby'", $pass, 'through aria-labelledby');
	pg_assert_contains('_sdPropsHasName', $pass, 'a named control is left alone');

	$has = designer_panel_names_function($source, '_sdPropsHasName');
	pg_assert_contains("'aria-label'", $has, 'aria-label counts as a name');
	pg_assert_contains('.labels', $has, 'a label with text counts as a name');
}

function test_designer_panel_names_pass_runs_on_every_render()
{
	$source = designer_panel_names_source('assets/js/style_designer.src.js');
	$init   = designer_panel_names_function($source, '_sdPropsA11yInit');

	pg_assert_contains('MutationObserver', $init, 'the pass follows every redraw of the panel');
	pg_assert_contains("'keydown'", $init, 'one delegated keydown');
	pg_assert_contains("'.sd-sect-hdr'", $init, 'for section headers');
	pg_assert_contains('_sdPropsA11yInit(panel);', designer_panel_names_function($source, 'renderProperties'), 'installed by renderProperties');
}

function test_designer_panel_names_section_headers_are_buttons()
{
	$source = designer_panel_names_source('assets/js/style_designer.src.js');

	foreach (array('sd-props-section-title sd-sect-hdr', 'sd-app-sect-hdr sd-sect-hdr', 'sd-bg-img-grp-hdr sd-sect-hdr') as $cls) {
		pg_assert_true((bool) preg_match('/class="' . preg_quote($cls, '/') . '[^\n]*role="button" tabindex="0" aria-expanded="/', $source), $cls . ' is a keyboard button');
	}
	pg_assert_same(3, substr_count($source, "hdr.setAttribute('aria-expanded', isOpen ? 'true' : 'false');"), 'the three header click handlers keep aria-expanded');
	pg_assert_same(2, substr_count($source, "this.setAttribute('aria-expanded', !isOpen ? 'true' : 'false');"), 'the two toggle click handlers keep aria-expanded');
}

function test_designer_panel_names_toggles_are_named()
{
	$pass = designer_panel_names_function(designer_panel_names_source('assets/js/style_designer.src.js'), '_sdNamePropsPanel');

	pg_assert_contains("_sdT('More settings for {var}'", $pass, 'toggle name');
	pg_assert_contains("'aria-controls'", $pass, 'toggle points at its rows');
}

function test_designer_panel_names_styles_inspector_translated()
{
	$source = designer_panel_names_source('assets/js/style_designer.src.js');

	foreach (array('placeholder="property"', 'placeholder="value"', 'placeholder="selector"', 'margin-left:auto">inline</span>', '#6e7681">overlay</span>') as $raw) {
		pg_assert_true(strpos($source, $raw) === false, $raw . ' is translated');
	}
	pg_assert_contains("esc(_sdT('Inline'))", $source, 'inline header');
	pg_assert_contains("esc(_sdT('Overlay'))", $source, 'overlay header');
	pg_assert_contains('class="sd-styles-decl-toggle" data-decl-idx="\' + i + \'" checked aria-label="\' + _stylesToggleName', $source, 'declaration switch is named');
}

function test_designer_panel_names_no_light_border_on_dark_panel()
{
	$source = designer_panel_names_source('assets/js/style_designer.src.js');

	pg_assert_true(strpos($source, 'border-top:1px solid #e5e7eb') === false, 'dark panel border token');
}
