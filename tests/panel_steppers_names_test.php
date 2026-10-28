<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the catalog screens' accessible names: every number
 * stepper button carries a translated Decrease / Increase name instead of
 * its icon ligature, the offer editor's tag remove link names the tag, the
 * Arrival Date Code label points at its field, and the save bars and
 * toolbars of these screens carry translated landmark names.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function panel_steppers_names_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_panel_steppers_names_every_stepper_button_is_named()
{
	$root = dirname(__DIR__) . '/pinegrap';
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
	$found = 0;

	foreach ($iterator as $file) {
		$path = $file->getPathname();

		if (substr($path, -4) !== '.php' || strpos($path, '/vendor/') !== false) {
			continue;
		}

		$source = (string) file_get_contents($path);

		if (!preg_match_all('/<button[^>]*class="[^"]*material-icons (minus|plus)[^"]*"(?:[^>"]|"[^"]*")*>/', $source, $matches, PREG_SET_ORDER)) {
			continue;
		}

		$relative = substr($path, strlen($root) + 1);

		foreach ($matches as $match) {
			$found++;
			$expected = ($match[1] === 'minus')
				? "aria-label=\"' . h(lang('Decrease')) . '\""
				: "aria-label=\"' . h(lang('Increase')) . '\"";

			if (strpos($relative, 'includes/templates/') === 0) {
				$expected = ($match[1] === 'minus')
					? 'aria-label="<?=h(lang(\'Decrease\'))?>"'
					: 'aria-label="<?=h(lang(\'Increase\'))?>"';
			}

			pg_assert_contains($expected, $match[0], $relative);
		}
	}

	// The folder screens share one stepper pair (includes/forms/folder_form.php).
	pg_assert_true($found >= 16, 'expected the panel steppers, found ' . $found);
}

function test_panel_steppers_names_offer_tag_remove_link()
{
	$source = panel_steppers_names_source('assets/js/offer_editor.js');

	pg_assert_contains("aria-label=\"' + esc(L.remove + ': ' + label) + '\"", $source);
	pg_assert_contains('<span aria-hidden="true">&times;</span></a>', $source);
}

function test_panel_steppers_names_arrival_date_code_label()
{
	$source = panel_steppers_names_source('includes/forms/arrival_date_form.php');

	pg_assert_contains('<label for="code"', $source);
	pg_assert_false(strpos($source, '<label for="name" class="form-label">\' . lang(\'Arrival Date Code\')') !== false, 'Arrival Date Code label points at the Name field');
}

function test_panel_steppers_names_translated_landmarks()
{
	$paths = array(
		'add_currency.php', 'edit_currency.php', 'add_zone.php', 'edit_zone.php',
		'add_tax_zone.php', 'edit_tax_zone.php', 'add_shipping_method.php', 'edit_shipping_method.php',
		'add_gift_card.php', 'edit_gift_card.php', 'view_gift_cards.php',
		'add_arrival_date.php', 'edit_arrival_date.php',
		'add_ship_date_adjustment.php', 'edit_ship_date_adjustment.php',
		'add_verified_shipping_address.php', 'edit_verified_shipping_address.php',
		'add_product_attribute.php', 'edit_product_attribute.php',
		'add_folder.php', 'edit_folder.php', 'view_order.php',
		'add_country.php', 'edit_country.php',
		'includes/templates/edit_products.php', 'product_builder.php',
	);

	foreach ($paths as $path) {
		$source = panel_steppers_names_source($path);
		pg_assert_false((bool) preg_match('/aria-label="(data edit buttons ?|Button Bar)"/', $source), $path . ': untranslated landmark name');
	}
}
