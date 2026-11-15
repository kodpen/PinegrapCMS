<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the save bar's button order (ghost, quiet, filled, in the markup)
 * and the hidden first submit that keeps Return on the save once the delete
 * or a quiet action comes first (pg_save_bar_return_button()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/save_bar.php');

/**
 * A bar button's role as the stylesheet draws it: G a danger ghost, F the
 * filled save (solid success or primary), Q every other button.
 */
function save_bar_order_test_role($classes)
{
	if (preg_match('/(^|\s)theme-danger(\s|$)/', $classes)) {
		return 'G';
	}

	if (preg_match('/(^|\s)btn-solid(\s|$)/', $classes) && preg_match('/(^|\s)theme-(success|primary)(\s|$)/', $classes)) {
		return 'F';
	}

	return 'Q';
}

/**
 * The roles of the buttons written in a piece of markup, in order.
 */
function save_bar_order_test_roles($markup)
{
	preg_match_all('/<(?:a|button)\b[^>]*\sclass="((?:[^"]* )?btn(?: [^"]*)?)"/', $markup, $matches);

	$roles = '';

	foreach ($matches[1] as $classes) {
		$roles .= save_bar_order_test_role($classes);
	}

	return $roles;
}

function test_save_bar_order_return_button_markup()
{
	$html = pg_save_bar_return_button();

	pg_assert_same('<button type="submit" class="visually-hidden" tabindex="-1" aria-hidden="true" data-pg-return-save></button>', $html, 'nameless, hidden, out of the tab order');
	pg_assert_true(strpos($html, 'name=') === false, 'posts no button name of its own');

	pg_assert_contains(' form="a&quot;b"', pg_save_bar_return_button('a"b'), 'form attribute escaped');
}

function test_save_bar_order_script_hands_return_to_the_save()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');

	pg_assert_contains("\$(document).on('click', 'button[data-pg-return-save]', function (event) {", $js, 'the hidden button has its handler');
	pg_assert_contains("\$bar.find('[data-pg-save-shortcut]')", $js, 'a marked save comes first');
	pg_assert_contains("\$bar.find('.btn-solid.theme-success, .btn-solid.theme-primary')", $js, 'then the first filled button');
	pg_assert_contains('target = pgSaveShortcutButton($(this.form));', $js, 'then the form\'s save button');
}

function test_save_bar_order_roles_of_the_recipes()
{
	pg_assert_same('GQF', save_bar_order_test_roles(
		'<button class="btn my-1 btn-solid theme-danger">D</button>'
		. '<a class="btn my-1 btn-outline theme-secondary" href="#">C</a>'
		. '<button class="btn my-1  btn-solid theme-success ">S</button>'
		. '<span class="pg-btn-label">x</span>'
	), 'danger ghost, quiet, fill; a label span is no button');
	pg_assert_same('Q', save_bar_order_test_roles('<button class="btn my-1 btn-outline theme-success">A</button>'), 'an outline success is quiet');
}

function test_save_bar_order_inline_bars()
{
	// Kept screens (decisions-made-panel-3.md) and bars an open step still
	// changes keep their order for now.
	$left = array(
		'product_builder.php',
		'includes/templates/edit_offer.php',
		'edit_comment.php',
		'edit_erp_invoice.php',
	);
	$files = array_merge(glob(PG_FUNCTIONS_DIR . '/*.php'), glob(PG_FUNCTIONS_DIR . '/includes/*/*.php'));

	foreach ($files as $file) {
		$relative = substr($file, strlen(PG_FUNCTIONS_DIR) + 1);

		if (in_array($relative, $left, true)) {
			continue;
		}

		$source = (string) file_get_contents($file);
		$offset = 0;

		while (($start = strpos($source, '<nav class="buttons navigation', $offset)) !== false) {
			$end = strpos($source, '</nav>', $start);
			$end = ($end === false) ? strlen($source) : $end;
			$bar = substr($source, $start, $end - $start);
			$roles = save_bar_order_test_roles($bar);

			pg_assert_true(preg_match('/^G*Q*F*$/', $roles) === 1, $relative . ' bar ' . $roles . ' runs ghost, quiet, filled');

			$offset = $end;
		}
	}
}

function test_save_bar_order_screens_that_pass_their_buttons()
{
	// Screens that hand their buttons to a form layout or pg_save_bar(): the
	// buttons after the hidden first submit run ghost, quiet, filled.
	$screens = array(
		'edit_ad.php' => 'GF', 'edit_arrival_date.php' => 'GF', 'edit_common_region.php' => 'GF',
		'edit_container.php' => 'GF', 'edit_contact.php' => 'GF', 'edit_country.php' => 'GF',
		'edit_calendar_event_location.php' => 'GF', 'edit_designer_region.php' => 'GF',
		'edit_dynamic_region.php' => 'GF', 'edit_field.php' => 'GF', 'edit_login_region.php' => 'GF',
		'edit_menu.php' => 'GF', 'edit_menu_item.php' => 'GF', 'edit_product_attribute.php' => 'GF',
		'edit_referral_source.php' => 'GF', 'edit_ship_date_adjustment.php' => 'GF',
		'edit_short_link.php' => 'GF', 'edit_state.php' => 'GF', 'edit_tax_zone.php' => 'GF',
		'edit_verified_shipping_address.php' => 'GF', 'edit_zone.php' => 'GF',
		'edit_currency.php' => 'GF', 'edit_design_file.php' => 'GF', 'edit_theme_file.php' => 'GQF',
		'edit_theme_css.php' => 'QFF', 'edit_javascript.php' => 'QFF', 'editor_edit_file.php' => 'QFF',
		'edit_file.php' => 'GQFF', 'import_design.php' => 'QF',
	);

	foreach ($screens as $screen => $expected) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $screen);
		$at = strpos($source, 'pg_save_bar_return_button()');

		pg_assert_true($at !== false, $screen . ' starts its bar with the hidden submit');

		// The buttons follow on the next lines, up to the call's end.
		$tail = substr($source, $at, 6000);
		$stop = preg_match('/\n\s*\)|\';\n/', $tail, $m, PREG_OFFSET_CAPTURE) ? $m[0][1] : strlen($tail);

		pg_assert_same($expected, save_bar_order_test_roles(substr($tail, 0, $stop)), $screen . ' bar order');
	}
}
