<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Bootstrap 5 to 6 class rules in tools/bs6_rules.php: the
 * breakpoint split, the class map (sizes kept, renames, reviews), class
 * lists with code inside, the contexts found in PHP and JS source, the
 * line filter of --diff, the panel/site scope and the dist class reader.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(dirname(__DIR__) . '/tools/bs6_rules.php');

// Tokens only, for short assertions.
function bs6_test_tokens($token)
{
	$mapped = bs6_map_token($token);
	return $mapped['review'] === null ? implode(' ', $mapped['tokens']) : 'REVIEW';
}

function test_bs6_split_responsive()
{
	pg_assert_same(array('md', 'col-6'), bs6_split_responsive('col-md-6'));
	pg_assert_same(array('md', 'col'), bs6_split_responsive('col-md'), 'bare col breakpoint');
	pg_assert_same(array('sm', 'container'), bs6_split_responsive('container-sm'));
	pg_assert_same(array('lg', 'sticky-top'), bs6_split_responsive('sticky-lg-top'));
	pg_assert_same(array('lg', 'navbar-expand'), bs6_split_responsive('navbar-expand-lg'));
	pg_assert_same(array('2xl', 'col-4'), bs6_split_responsive('col-xxl-4'), 'xxl is 2xl');
	pg_assert_same(array('md', 'justify-content-between'), bs6_split_responsive('justify-content-md-between'));
	pg_assert_same(array('md', 'pt-0'), bs6_split_responsive('pt-md-0'));
	pg_assert_same(null, bs6_split_responsive('text-lg'), 'v6 font size, not a v5 breakpoint');
	pg_assert_same(null, bs6_split_responsive('btn-sm'), 'size suffix');
	pg_assert_same(null, bs6_split_responsive('form-control-lg'), 'size suffix');
	pg_assert_same(null, bs6_split_responsive('d-print-none'));
	pg_assert_same(null, bs6_split_responsive('pg-toolbar'));
}

// Sizes stay as they are on screen: v6 gives the scales other values.
function test_bs6_map_keeps_sizes()
{
	pg_assert_same('p-3', bs6_test_tokens('p-2'));
	pg_assert_same('p-5', bs6_test_tokens('p-3'));
	pg_assert_same('mb-7', bs6_test_tokens('mb-4'));
	pg_assert_same('py-12', bs6_test_tokens('py-5'));
	pg_assert_same('m-0', bs6_test_tokens('m-0'));
	pg_assert_same('mx-auto', bs6_test_tokens('mx-auto'), 'auto is not on the scale');
	pg_assert_same('g-5', bs6_test_tokens('g-3'), 'gutters share the spacer scale');
	pg_assert_same('gap-3', bs6_test_tokens('gap-2'));
	pg_assert_same('md:p-5', bs6_test_tokens('p-md-3'));
	pg_assert_same('md:mb-0', bs6_test_tokens('mb-md-0'));
	pg_assert_same('ms--1', bs6_test_tokens('ms-n1'));
	pg_assert_same('me--2', bs6_test_tokens('me-n2'));
	pg_assert_same('REVIEW', bs6_test_tokens('mt-n1'), 'v6 has no block negative margins');
	pg_assert_same('fs-4xl', bs6_test_tokens('fs-1'));
	pg_assert_same('fs-xl', bs6_test_tokens('fs-4'));
	pg_assert_same('fs-md', bs6_test_tokens('fs-6'));
	pg_assert_same('rounded-4', bs6_test_tokens('rounded'), 'v5 plain radius is 0.375rem');
	pg_assert_same('rounded-3', bs6_test_tokens('rounded-1'));
	pg_assert_same('rounded-5', bs6_test_tokens('rounded-3'));
	pg_assert_same('rounded-9', bs6_test_tokens('rounded-4'));
	pg_assert_same('rounded-top-3', bs6_test_tokens('rounded-top-1'));
	pg_assert_same('rounded-end-4', bs6_test_tokens('rounded-end'));
	pg_assert_same('REVIEW', bs6_test_tokens('rounded-5'), 'no v6 radius over 1rem');
	pg_assert_same('rounded-pill', bs6_test_tokens('rounded-pill'));
}

function test_bs6_map_renames()
{
	pg_assert_same('fg-secondary', bs6_test_tokens('text-muted'));
	pg_assert_same('fg-secondary', bs6_test_tokens('text-body-secondary'));
	pg_assert_same('fg-danger', bs6_test_tokens('text-danger'));
	pg_assert_same('fg-emphasis-warning', bs6_test_tokens('text-warning-emphasis'));
	pg_assert_same('bg-warning fg-contrast-warning', bs6_test_tokens('text-bg-warning'));
	pg_assert_same('bg-subtle-info', bs6_test_tokens('bg-info-subtle'));
	pg_assert_same('border-subtle-danger', bs6_test_tokens('border-danger-subtle'));
	pg_assert_same('btn-solid theme-primary', bs6_test_tokens('btn-primary'));
	pg_assert_same('btn-outline theme-secondary', bs6_test_tokens('btn-outline-secondary'));
	pg_assert_same('theme-danger', bs6_test_tokens('alert-danger'));
	pg_assert_same('form-control', bs6_test_tokens('form-select'));
	pg_assert_same('form-control-sm', bs6_test_tokens('form-select-sm'));
	pg_assert_same('', bs6_test_tokens('has-validation'), 'dropped');
	pg_assert_same('d-flow-root', bs6_test_tokens('clearfix'));
	pg_assert_same('lh-md', bs6_test_tokens('lh-base'));
	pg_assert_same('max-w-100', bs6_test_tokens('mw-100'));
	pg_assert_same('tooltip invalid-tooltip', bs6_test_tokens('invalid-tooltip'));
	pg_assert_same('hover:underline-offset-2', bs6_test_tokens('link-offset-2-hover'));
	pg_assert_same('underline-50', bs6_test_tokens('link-underline-opacity-50'));
	pg_assert_same('hover:link-10', bs6_test_tokens('link-opacity-10-hover'));
	pg_assert_same('REVIEW', bs6_test_tokens('link-opacity-75-hover'), 'step 75 does not exist in v6');
	pg_assert_same('md:d-none', bs6_test_tokens('d-md-none'));
	pg_assert_same('2xl:col-3', bs6_test_tokens('col-xxl-3'));
	pg_assert_same('lg:navbar-expand', bs6_test_tokens('navbar-expand-lg'));
	pg_assert_same('md:text-start', bs6_test_tokens('text-md-start'));
}

function test_bs6_map_reviews_and_untouched()
{
	foreach (array('btn-light', 'text-dark', 'bg-light', 'alert-dark', 'text-reset', 'bg-body-tertiary',
		'display-4', 'lead', 'btn-close-white', 'alert-dismissible', 'needs-validation', 'd-print-none',
		'modal', 'modal-dialog', 'offcanvas-end', 'dropdown-menu', 'dropdown-toggle', 'form-check-input',
		'form-switch', 'btn-check', 'accordion-button', 'form-range', 'opacity-50-hover', 'link-primary',
		'text-opacity-75') as $token) {
		$mapped = bs6_map_token($token);
		pg_assert_true($mapped['review'] !== null, $token . ' needs a hand');
		pg_assert_same(array($token), $mapped['tokens'], $token . ' is left as it is');
	}

	foreach (array('btn', 'btn-sm', 'btn-link', 'card', 'd-flex', 'text-start', 'text-truncate', 'fw-bold',
		'w-100', 'small', 'pg-toolbar', 'ws-avatar', 'badge', 'shadow-sm', 'col-6', 'col', 'row', 'show') as $token) {
		pg_assert_same($token, bs6_test_tokens($token), $token . ' is the same in v6');
	}
}

function test_bs6_convert_class_list()
{
	$r = bs6_convert_class_list('btn btn-primary btn-sm me-2');
	pg_assert_same('btn btn-solid theme-primary btn-sm me-3', $r['value']);
	pg_assert_same(2, $r['changed']);

	$r = bs6_convert_class_list('badge bg-success');
	pg_assert_same('badge theme-success', $r['value'], 'a badge takes the theme class');

	$r = bs6_convert_class_list('badge text-bg-danger rounded-pill');
	pg_assert_same('badge theme-danger rounded-pill', $r['value']);

	$r = bs6_convert_class_list('bg-success p-2');
	pg_assert_same('bg-success p-3', $r['value'], 'no badge: the background stays');

	pg_assert_same('form-control', bs6_convert_class_list('form-control has-validation')['value']);
	pg_assert_same('form-control', bs6_convert_class_list('has-validation form-control')['value']);
	pg_assert_same('a b', bs6_convert_class_list('a has-validation b')['value']);
	pg_assert_same(' form-control ', bs6_convert_class_list(' form-select ')['value'], 'outer spacing kept');
	pg_assert_same('btn btn-solid theme-primary', bs6_convert_class_list('btn btn-solid btn-primary')['value'], 'no duplicate class');

	// Code inside the list: complete tokens change, code stays as written.
	$r = bs6_convert_class_list("btn btn-' . \$variant . ' me-2");
	pg_assert_same("btn btn-' . \$variant . ' me-3", $r['value']);
	pg_assert_same(array(array("btn-'", 'class built in code from a Bootstrap 5 stem')), $r['reviews']);

	$r = bs6_convert_class_list('${cls} text-muted');
	pg_assert_same('${cls} fg-secondary', $r['value']);

	$r = bs6_convert_class_list('btn btn-primary btn-outline-danger');
	pg_assert_same(1, count($r['reviews']), 'two theme classes are reported');

	$r = bs6_convert_class_list('pg-toolbar ws-avatar');
	pg_assert_same('pg-toolbar ws-avatar', $r['value']);
	pg_assert_same(0, $r['changed']);
}

function test_bs6_convert_source_contexts()
{
	$php = "<?php\n"
		. "echo '<div class=\"d-flex p-3\">' . \$x . '</div>';\n"
		. "\$html = \"<span class=\\\"text-muted\\\">\";\n"
		. "\$f = array('type' => 'select', 'class' => 'form-select', 'action_bar_class' => 'mb-3');\n"
		. "\$class = 'p-3';\n";
	$r = bs6_convert_source($php);

	pg_assert_contains('class="d-flex p-5"', $r['source']);
	pg_assert_contains('class=\\"fg-secondary\\"', $r['source'], 'escaped quotes');
	pg_assert_contains("'class' => 'form-control'", $r['source']);
	pg_assert_contains("'action_bar_class' => 'mb-5'", $r['source'], 'keys ending in class');
	pg_assert_contains("\$class = 'p-3';", $r['source'], 'a PHP variable is code, not markup');
	pg_assert_same(4, $r['changed']);

	$js = "el.classList.add('d-none', 'text-muted');\n"
		. "\$('#x').addClass('text-danger').removeClass('btn-primary');\n"
		. "node.className = 'btn btn-outline-danger';\n"
		. "var o = { className: 'p-2', label: 'p-2' };\n"
		. "var h = '<a class=\"' + cls + ' ms-2\">';\n";
	$r = bs6_convert_source($js);

	pg_assert_contains("classList.add('d-none', 'fg-secondary')", $r['source']);
	pg_assert_contains("addClass('fg-danger')", $r['source']);
	pg_assert_contains("removeClass('btn-primary')", $r['source'], 'a split class stays in a class API call');
	pg_assert_contains("className = 'btn btn-outline theme-danger'", $r['source']);
	pg_assert_contains("{ className: 'p-3', label: 'p-2' }", $r['source'], 'only the class key');
	pg_assert_contains("cls + ' ms-3\"", $r['source']);
}

// --diff converts only the lines a branch added.
function test_bs6_convert_source_lines()
{
	$source = "<p class=\"text-muted\"></p>\n<p class=\"text-muted\"></p>\n<p class=\"text-muted\"></p>\n";
	$r = bs6_convert_source($source, array(2 => true));

	pg_assert_same("<p class=\"text-muted\"></p>\n<p class=\"fg-secondary\"></p>\n<p class=\"text-muted\"></p>\n", $r['source']);
	pg_assert_same(1, $r['changed']);

	$r = bs6_convert_source("<i class=\"modal\"></i>\n<b class=\"p-3\"></b>\n");
	pg_assert_same(array(array(1, 'modal', 'modal becomes a <dialog class="dialog">: structural')), $r['reviews']);
}

function test_bs6_scope_kind()
{
	$cases = array(
		'view_orders.php' => 'panel',
		'edit_user.php' => 'panel',
		'install/index.php' => 'panel',
		'assets/js/backend.src.js' => 'panel',
		'includes/templates/edit_offer.php' => 'panel',
		'get_erp_drawer.php' => 'panel',
		'get_page.php' => 'site',
		'get_shopping_cart.php' => 'site',
		'my_account.php' => 'site',
		'frontend.src.js' => 'site',
		'includes/fn/widgets_cart.php' => 'site',
		'includes/fn/widgets_order_preview.php' => 'site',
		'includes/fn/widgets_lists_cart.php' => 'site',
		'includes/templates/membership_entrance.php' => 'site',
		'includes/design_templates/forum/home.php' => 'site',
		'assets/js/pg_consent.js' => 'site',
		'email_campaign_job.php' => 'site',
		'includes/fn/output.php' => 'shared',
		'liveform.class.php' => 'shared',
		'assets/js/style_designer.js' => 'editor',
		'includes/designer_screen.php' => 'editor',
		'assets/js/pg_style_predict.js' => 'editor',
		'includes/fn/designer_vocab.php' => 'editor',
		'assets/lib/bootstrap-6/js/bootstrap.js' => 'vendor',
		'includes/dompdf/src/Dompdf.php' => 'vendor',
		'assets/js/workspace.min.js' => 'vendor',
		'assets/js/pg_ui.js' => 'adapter',
		'images/logo.png' => 'other',
	);

	foreach ($cases as $path => $kind) {
		pg_assert_same($kind, bs6_scope_kind($path), $path);
	}
}

function test_bs6_dist_classes()
{
	$classes = bs6_dist_classes('.btn-solid{} .md\:col-6{} .\32 xl\:col-3,.p-5:hover{}');

	pg_assert_true(isset($classes['btn-solid']));
	pg_assert_true(isset($classes['md:col-6']));
	pg_assert_true(isset($classes['2xl:col-3']), 'hex escape of a leading digit');
	pg_assert_true(isset($classes['p-5']));
}

// Every v6 class the rules produce for common v5 classes is in the shipped
// dist: a rule that writes a class the dist does not define fails here.
function test_bs6_targets_exist_in_dist()
{
	$css = PG_FUNCTIONS_DIR . '/assets/lib/bootstrap-6/css/bootstrap.css';
	pg_assert_true(is_file($css), 'dist stylesheet');
	$dist = bs6_dist_classes((string) file_get_contents($css));

	$inputs = array(
		'p-2', 'p-3', 'p-4', 'p-5', 'mt-2', 'mb-3', 'ms-4', 'me-5', 'px-3', 'py-2', 'g-3', 'gx-2', 'gy-4', 'gap-3',
		'row-gap-2', 'column-gap-3', 'ms-n1', 'me-n2', 'fs-1', 'fs-2', 'fs-3', 'fs-4', 'fs-5', 'fs-6', 'rounded', 'rounded-1',
		'rounded-2', 'rounded-3', 'rounded-4', 'rounded-top', 'rounded-start-2', 'text-muted', 'text-body-secondary',
		'text-primary', 'text-success', 'text-danger', 'text-warning', 'text-info', 'text-secondary', 'text-body', 'text-white',
		'text-black', 'text-primary-emphasis', 'text-bg-primary', 'text-bg-danger', 'bg-success-subtle', 'border-warning-subtle',
		'btn-primary', 'btn-secondary', 'btn-outline-danger', 'alert-info', 'form-select', 'form-select-sm', 'clearfix',
		'lh-base', 'mh-100', 'mw-100', 'valid-tooltip', 'link-offset-1', 'link-offset-2-hover', 'link-underline-primary',
		'link-underline-opacity-50', 'link-opacity-10-hover', 'col-md-6', 'col-lg', 'col-xl-auto', 'col-xxl-3', 'offset-md-2',
		'order-lg-1', 'row-cols-md-3', 'd-md-none', 'd-lg-flex', 'flex-md-row', 'justify-content-md-between',
		'align-items-lg-center', 'text-md-end', 'float-lg-end', 'p-md-3', 'mb-lg-0', 'g-md-4', 'gap-lg-2', 'container-sm',
		'navbar-expand-lg', 'table-responsive-md', 'list-group-horizontal-md', 'sticky-md-top',
	);

	foreach ($inputs as $input) {
		$mapped = bs6_map_token($input);
		pg_assert_same(null, $mapped['review'], $input . ' maps without a review');

		foreach ($mapped['tokens'] as $target) {
			pg_assert_true(isset($dist[$target]), $input . ' gives ' . $target . ', which the dist defines');
		}
	}
}

// `git diff -U0` gives the added range in each hunk header.
function test_bs6_parse_added_lines()
{
	$diff = "diff --git a/pinegrap/a.php b/pinegrap/a.php\n"
		. "--- a/pinegrap/a.php\n+++ b/pinegrap/a.php\n"
		. "@@ -3 +3 @@\n-x\n+y\n"
		. "@@ -10,0 +11,2 @@\n+p\n+q\n"
		. "@@ -20,2 +22,0 @@\n-gone\n-gone\n"
		. "diff --git a/pinegrap/new.js b/pinegrap/new.js\n"
		. "--- /dev/null\n+++ b/pinegrap/new.js\n@@ -0,0 +1,3 @@\n+1\n+2\n+3\n"
		. "diff --git a/pinegrap/old.php b/pinegrap/old.php\n"
		. "--- a/pinegrap/old.php\n+++ /dev/null\n@@ -1,2 +0,0 @@\n-a\n-b\n";

	pg_assert_same(array(
		'pinegrap/a.php' => array(3 => true, 11 => true, 12 => true),
		'pinegrap/new.js' => array(1 => true, 2 => true, 3 => true),
	), bs6_parse_added_lines($diff));
}

// Code inside a class list is never edited: a ternary's ':' and a class that
// both branches name stay where they are.
function test_bs6_convert_class_list_keeps_code()
{
	$value = "' . ((\$b > 0) ? 'text-success' : ((\$b < 0) ? 'text-danger' : '')) . '";
	pg_assert_same($value, bs6_convert_class_list($value)['value'], 'ternary colons kept');

	$value = "x ' . (\$a ? ' p-3 foo' : ' foo bar') . ' foo";
	pg_assert_same("x ' . (\$a ? ' p-5 foo' : ' foo bar') . ' foo", bs6_convert_class_list($value)['value'], 'no de-duplication across code');

	$r = bs6_convert_class_list("btn ' . (\$x ? 'a' : 'b') . ' btn-primary btn-outline-danger");
	pg_assert_same(array(), $r['reviews'], 'theme count only for lists without code');
}

// A v5 class quoted inside the code of a class list is reported with the
// class it should become.
function test_bs6_convert_class_list_reports_code_classes()
{
	$r = bs6_convert_class_list("' . (\$ok ? 'text-success' : 'p-3') . '");
	pg_assert_same(array(
		array('text-success -> fg-success', 'class inside code: convert it by hand'),
		array('p-3 -> p-5', 'class inside code: convert it by hand'),
	), $r['reviews']);
	pg_assert_same(0, $r['changed']);
}

// classList takes one class per argument, and toggling or removing one
// half of a split class breaks the other variants: a class API call keeps a
// class that becomes two and reports it.
function test_bs6_class_api_keeps_split_classes()
{
	$js = "btn.classList.toggle('btn-warning', n > 0);\n"
		. "line.classList.toggle('text-danger', over);\n"
		. "\$('#t').toggleClass('btn-outline-secondary', !on).addClass('mb-3');\n"
		. "note.className = 'badge text-bg-success';\n";
	$r = bs6_convert_source($js);

	pg_assert_contains("classList.toggle('btn-warning', n > 0)", $r['source']);
	pg_assert_contains("classList.toggle('fg-danger', over)", $r['source']);
	pg_assert_contains("toggleClass('btn-outline-secondary', !on).addClass('mb-5')", $r['source']);
	pg_assert_contains("className = 'badge theme-success'", $r['source'], 'a full class list may change shape');
	pg_assert_same(array(
		array(1, 'btn-warning -> btn-solid theme-warning', 'a class API call takes one class: convert it by hand'),
		array(3, 'btn-outline-secondary -> btn-outline theme-secondary', 'a class API call takes one class: convert it by hand'),
	), $r['reviews']);
}
