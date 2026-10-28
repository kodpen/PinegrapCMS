<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for jQuery UI leaving the panel: the panel header prints no
 * jQuery UI sheet or script and backend.src.js sets up no jQuery UI widget.
 * Every other PHP and JS file outside the site renderers and the unlinked
 * theme designer calls no jQuery UI widget, so a screen cannot depend on the
 * library the header no longer loads. Site pages keep loading jQuery UI on their own.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function bs6_jquery_ui_panel_test_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

// A jQuery UI widget or helper called on a jQuery object, or the $.ui and
// $.widget namespaces. Own helpers named like a widget (h.button(), K.button())
// are not chained off a call, so they do not match.
function bs6_jquery_ui_panel_test_pattern()
{
	return '/\)\s*\.\s*(dialog|sortable|draggable|droppable|resizable|selectable|autocomplete|datepicker|datetimepicker|timepicker|slider|spinner|accordion|menu|button|buttonset|tabs|progressbar|effect|switchClass|disableSelection|enableSelection|uniqueId|position|tooltip|popover)\s*\(|(\$|jQuery)\.(ui|widget|datepicker|timepicker)\b/';
}

function test_bs6_jquery_ui_panel_header_loads_no_jquery_ui()
{
	$source = bs6_jquery_ui_panel_test_source('includes/fn/output.php');
	$start = strpos($source, 'function output_control_panel_header_includes(');
	pg_assert_true($start !== false, 'output.php defines output_control_panel_header_includes()');
	$end = strpos($source, "\nfunction ", (int) $start + 10);
	$body = substr($source, (int) $start, $end === false ? null : (int) $end - (int) $start);

	pg_assert_false(stripos($body, 'Jquery/ui/') !== false, 'the panel header loads nothing from assets/lib/Jquery/ui');
	pg_assert_false(stripos($body, 'jquery-ui') !== false, 'the panel header prints no jQuery UI sheet or script');
	pg_assert_contains('/assets/lib/Jquery/jquery.min.js', $body, 'the panel header still loads jQuery');
}

function test_bs6_jquery_ui_panel_backend_js()
{
	$js = bs6_jquery_ui_panel_test_source('assets/js/backend.src.js');
	pg_assert_false(strpos($js, 'pg-sortable') !== false, 'backend.src.js has no .pg-sortable set-up');
	pg_assert_false((bool) preg_match(bs6_jquery_ui_panel_test_pattern(), $js), 'backend.src.js calls no jQuery UI widget');
}

function test_bs6_jquery_ui_panel_files_call_no_widget()
{
	// Site renderers and site scripts keep jQuery UI (sites stay on
	// Bootstrap 5.3.8 + jQuery UI); the theme designer stays on the disk
	// unlinked. content.php draws both the panel and the site form fields.
	$allowed = array(
		'frontend.src.js',
		'get_billing_information.php',
		'get_custom_form_screen_content.php',
		'get_express_order.php',
		'get_form_item_view.php',
		'get_form_list_view.php',
		'get_order_form.php',
		'get_page_content.php',
		'get_shipping_address_and_arrival.php',
		'get_shopping_cart.php',
		'get_theme_designer_module.php',
		'includes/fn/content.php',
		'includes/fn/widgets_order_form.php',
		'theme_designer.php',
	);

	$root = dirname(__DIR__) . '/pinegrap/';
	$found = array();
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
	foreach ($iterator as $file) {
		$extension = $file->getExtension();
		if ($extension !== 'php' && $extension !== 'js') {
			continue;
		}
		$path = str_replace('\\', '/', substr($file->getPathname(), strlen($root)));
		// Vendored libraries, minified twins (their sources are scanned) and
		// the site theme backups are not panel code.
		if (strpos($path, 'assets/lib/') === 0 || strpos($path, 'data/') === 0 || substr($path, -7) === '.min.js') {
			continue;
		}
		if (preg_match(bs6_jquery_ui_panel_test_pattern(), (string) file_get_contents($file->getPathname()))) {
			$found[] = $path;
		}
	}

	$unexpected = array_values(array_diff($found, $allowed));
	pg_assert_same(array(), $unexpected, 'no panel file calls a jQuery UI widget');
}

function test_bs6_jquery_ui_panel_css_styles_no_ui_class()
{
	// Comments may still name jQuery UI; only selectors count. A selector is
	// the text before a "{", so property values (url(), var()) are skipped.
	$css = bs6_jquery_ui_panel_test_source('assets/css/backend.src.css');
	$css = (string) preg_replace('~/\*.*?\*/~s', '', $css);
	preg_match_all('/(?:^|[{};])\s*([^{};]+)\{/', $css, $selectors);
	$found = array();
	foreach ($selectors[1] as $selector) {
		if (preg_match('/[.#]ui-[\w-]+/', $selector, $match)) {
			$found[] = $match[0];
		}
	}
	pg_assert_same(array(), $found, 'backend.src.css styles no jQuery UI class or id');
}
