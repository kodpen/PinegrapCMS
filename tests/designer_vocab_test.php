<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Bootstrap vocabulary of the designer's smart completion
 * (includes/fn/designer_vocab.php): the stored
 * assets/js/designer_vocab/bootstrap5.json must be exactly what the shipped
 * Bootstrap files give, and the readers behind it must split selectors, find
 * responsive variants, tokens and the JavaScript API correctly.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/designer_vocab.php');

// The vocabulary built from the shipped Bootstrap, built once per run.
function designer_vocab_test_built()
{
	static $vocab = null;

	if ($vocab === null) {
		$bootstrap = PG_FUNCTIONS_DIR . '/assets/lib/bootstrap-5.3.8';
		$css = file_get_contents($bootstrap . '/css/bootstrap.min.css');
		$map = json_decode(file_get_contents($bootstrap . '/js/bootstrap.bundle.min.js.map'), true);
		$vocab = pg_designer_vocab_build($css, $map);
	}

	return $vocab;
}

function test_designer_vocab_stored_file_matches_a_rebuild()
{
	$file = PG_FUNCTIONS_DIR . '/assets/js/designer_vocab/bootstrap5.json';
	$stored = is_file($file) ? file_get_contents($file) : '';

	pg_assert_true($stored === pg_designer_vocab_json(designer_vocab_test_built()), 'bootstrap5.json is out of date: run php tools/build_designer_vocab.php');
}

function test_designer_vocab_version_and_breakpoints()
{
	$vocab = designer_vocab_test_built();

	pg_assert_same('5.3.8', $vocab['version']);
	pg_assert_same(array('sm' => '576px', 'md' => '768px', 'lg' => '992px', 'xl' => '1200px', 'xxl' => '1400px'), $vocab['breakpoints']);
}

function test_designer_vocab_responsive_variants_and_size_modifiers()
{
	$classes = designer_vocab_test_built()['classes'];

	pg_assert_same(array('md', 'd-flex'), $classes['d-md-flex']['r']);
	pg_assert_same(array('lg', 'col-6'), $classes['col-lg-6']['r']);
	pg_assert_same(array('xxl', 'col'), $classes['col-xxl']['r']);
	pg_assert_same(array('sm', 'container'), $classes['container-sm']['r']);
	pg_assert_same(array('md', 'navbar-expand'), $classes['navbar-expand-md']['r']);
	pg_assert_same(array('sm', 'modal-fullscreen-down'), $classes['modal-fullscreen-sm-down']['r']);

	// Size modifiers are not breakpoints: no btn-md, no modal-md.
	pg_assert_false(isset($classes['btn-sm']['r']), 'btn-sm');
	pg_assert_false(isset($classes['modal-lg']['r']), 'modal-lg');
	pg_assert_false(isset($classes['modal-sm']['r']), 'modal-sm');
	pg_assert_false(isset($classes['form-control-lg']['r']), 'form-control-lg');
}

function test_designer_vocab_class_declarations_context_and_states()
{
	$classes = designer_vocab_test_built()['classes'];

	pg_assert_same(array('padding' => '1rem!important'), $classes['p-3']['p']);
	pg_assert_same('flex!important', $classes['d-md-flex']['p']['display']);
	pg_assert_same(array('lg', 'd-none'), $classes['d-lg-none']['r']);
	pg_assert_contains('navbar-nav', $classes['nav-link']['ctx']);
	pg_assert_contains('nav-tabs', $classes['nav-link']['ctx']);
	pg_assert_contains('active', $classes['nav-link']['with']);
	pg_assert_contains('btn-group', $classes['btn']['ctx']);
	pg_assert_contains('was-validated', $classes['form-control']['ctx']);
	pg_assert_contains('is-valid', $classes['form-control']['with']);

	// A class named only as context or state is listed too.
	pg_assert_true(isset($classes['was-validated']), 'was-validated listed');
	pg_assert_true(isset($classes['active']), 'active listed');
}

function test_designer_vocab_tokens()
{
	$tokens = designer_vocab_test_built()['tokens'];

	pg_assert_same('#0d6efd', $tokens['--bs-primary']['v']);
	pg_assert_same('#fff', $tokens['--bs-body-bg']['v']);
	pg_assert_same('#212529', $tokens['--bs-body-bg']['d']);
	pg_assert_contains('btn', $tokens['--bs-btn-bg']['on']);
	pg_assert_contains('btn-primary', $tokens['--bs-btn-bg']['on']);
	pg_assert_false(isset($tokens['--bs-primary']['on']), 'root token has no class');
}

function test_designer_vocab_javascript_components()
{
	$js = designer_vocab_test_built()['js'];

	pg_assert_same(array('alert', 'button', 'carousel', 'collapse', 'dropdown', 'modal', 'offcanvas', 'popover', 'scrollspy', 'tab', 'toast', 'tooltip'), array_keys($js));
	pg_assert_same('Modal', $js['modal']['class']);
	pg_assert_same(array('hidden', 'hide', 'hidePrevented', 'show', 'shown'), $js['modal']['events']);
	pg_assert_same(array('dispose', 'handleUpdate', 'hide', 'show', 'toggle'), $js['modal']['methods']);
	pg_assert_same(array('getInstance', 'getOrCreateInstance'), $js['modal']['static']);
	pg_assert_same(array('(boolean|string)', 'true'), $js['modal']['options']['backdrop']);
	pg_assert_same(array('modal'), $js['modal']['attrs']['data-bs-toggle']);
	pg_assert_same(array('modal'), $js['modal']['attrs']['data-bs-dismiss']);
	pg_assert_true(isset($js['modal']['attrs']['data-bs-target']), 'modal data-bs-target');

	pg_assert_same(array('slid', 'slide'), $js['carousel']['events']);
	pg_assert_same(array('close', 'closed'), $js['alert']['events']);
	pg_assert_same(array('activate'), $js['scrollspy']['events']);
	pg_assert_same(array('dispose', 'show'), $js['tab']['methods']);
	pg_assert_same(array('list', 'pill', 'tab'), $js['tab']['attrs']['data-bs-toggle']);

	// Popover inherits Tooltip's API and overrides its defaults.
	pg_assert_same(array('hidden', 'hide', 'inserted', 'show', 'shown'), $js['popover']['events']);
	pg_assert_contains('setContent', $js['popover']['methods']);
	pg_assert_same("'right'", $js['popover']['options']['placement'][1]);
	pg_assert_same("'top'", $js['tooltip']['options']['placement'][1]);
	pg_assert_true(isset($js['popover']['attrs']['data-bs-content']), 'popover data-bs-content');
	pg_assert_true(isset($js['popover']['attrs']['data-bs-placement']), 'popover inherits data-bs-placement');

	// An attribute Bootstrap writes itself is not offered.
	pg_assert_false(isset($js['tooltip']['attrs']['data-bs-original-title']), 'no data-bs-original-title');
}

function test_designer_vocab_fixed_colour_classes_exist()
{
	$classes = designer_vocab_test_built()['classes'];

	foreach (designer_vocab_test_built()['fixed'] as $fixed => $replacement) {
		pg_assert_true(isset($classes[$fixed]), $fixed . ' exists');
		pg_assert_true(isset($classes[$replacement]), $replacement . ' exists');
	}
}

function test_designer_vocab_css_reader_on_a_small_sheet()
{
	$css = '/* a{} */@charset "UTF-8";.a,.b>.c:not(.x):hover{color:red;background:url("data:x;y")}'
		. '@media (min-width:576px){.a-sm-1{margin:0}}@keyframes k{0%{opacity:0}}.d[data-x="}"]{top:0}';
	$rules = pg_designer_vocab_css_rules($css);

	pg_assert_same(3, count($rules));
	pg_assert_same(array('', '.a,.b>.c:not(.x):hover', array(array('color', 'red'), array('background', 'url("data:x;y")'))), $rules[0]);
	pg_assert_same('@media (min-width:576px)', $rules[1][0]);
	pg_assert_same('.d[data-x="}"]', $rules[2][1]);

	pg_assert_same(array('.navbar-expand-lg', '.navbar-nav', '.nav-link:hover'), pg_designer_vocab_compounds('.navbar-expand-lg .navbar-nav>.nav-link:hover'));
	pg_assert_same(array('.btn-check:checked', '.btn'), pg_designer_vocab_compounds('.btn-check:checked+.btn'));
	pg_assert_same(array('btn', 'active'), pg_designer_vocab_compound_classes('.btn.active:not(.disabled)[class*=".x"]'));
}

function test_designer_vocab_js_reader_on_a_small_source()
{
	$source = "const NAME = 'thing'\nconst EVENT_KEY = '.bs.thing'\n"
		. "const EVENT_SHOW = `show\${EVENT_KEY}`\nconst EVENT_CLICK = `click\${EVENT_KEY}`\n"
		. "const SELECTOR = '[data-bs-toggle=\"thing\"]'\n"
		. "const Default = {\n  fooBar: [1, 2],\n  label: 'a, b'\n}\nconst DefaultType = {\n  fooBar: 'array',\n  label: 'string'\n}\n"
		. "class Thing extends BaseComponent {\n  constructor(element) {\n  }\n\n  open() { // opens it\n    EventHandler.trigger(this._element, EVENT_SHOW)\n    EventHandler.on(this._element, EVENT_CLICK, () => {})\n  }\n\n"
		. "  _private() {\n  }\n\n  static jQueryInterface(config) {\n  }\n}\nenableDismissTrigger(Thing)\n";
	$info = pg_designer_vocab_js_parse($source);

	pg_assert_same('thing', $info['name']);
	pg_assert_same('Thing', $info['class']);
	pg_assert_same('BaseComponent', $info['extends']);
	pg_assert_same(array('open'), $info['methods']);
	pg_assert_same(array(), $info['static']);
	pg_assert_same(array('show'), $info['events']);
	pg_assert_same(array('fooBar' => '[1, 2]', 'label' => "'a, b'"), $info['defaults']);
	pg_assert_same(array('fooBar' => 'array', 'label' => 'string'), $info['types']);
	pg_assert_same(array('data-bs-toggle' => array('thing' => true), 'data-bs-foo-bar' => array(), 'data-bs-label' => array()), $info['attrs']);
	pg_assert_true($info['dismiss'], 'dismiss trigger');
}
