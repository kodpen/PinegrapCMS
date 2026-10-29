<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the names screen readers get on the state, ship date adjustment,
 * shipping method, product group and product forms: labels point at their
 * own field, weekday sets, rate tiers and rate boxes are named groups, and
 * radio groups sit under a legend.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function commerce_form_group_names_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_state_country_label_points_at_the_country_select()
{
	$src = commerce_form_group_names_source('includes/forms/state_form.php');

	pg_assert_contains('<label for="country_id" class="form-label">\' . lang(\'Country\') . \'</label>', $src, 'Country names the country select');
	pg_assert_true(substr_count($src, '<label for="name"') === 1, 'the name box has one label');
}

function test_ship_date_adjustment_pair_is_a_named_group()
{
	$src = commerce_form_group_names_source('includes/forms/ship_date_adjustment_form.php');

	pg_assert_contains('<div class="form-label" id="adjustment_label">\' . lang(\'Adjustment\') . \'</div>', $src, 'Adjustment is a group title, not an orphan label');
	pg_assert_contains('<div class="input-group w-auto" role="group" aria-labelledby="adjustment_label">', $src, 'the input group is named Adjustment');
	pg_assert_contains('aria-labelledby="adjustment_label adjustment_days_unit"', $src, 'the days box is named');
	pg_assert_contains('<span class="input-group-text" id="adjustment_days_unit">\' . lang(\'days\') . \'</span>', $src, 'the unit has an id');
	pg_assert_contains('aria-labelledby="adjustment_label adjustment_type_label"', $src, 'the type select is named');
	pg_assert_contains('<span class="visually-hidden" id="adjustment_type_label">\' . lang(\'Direction\') . \'</span>', $src, 'the type select has its own word');
	pg_assert_true(strpos($src, '<label class="form-label">\' . lang(\'Adjustment\')') === false, 'no orphan Adjustment label');
}

function test_shipping_method_day_sets_are_named_groups()
{
	$src = commerce_form_group_names_source('includes/forms/shipping_method_form.php');

	foreach (array('Handling on', 'Ships on', 'Available on') as $heading) {
		pg_assert_contains('role="group" aria-label="\' . h(lang(\'' . $heading . '\')) . \'"', $src, $heading . ' names its day boxes');
	}
	pg_assert_contains('<h3 class="h5 fg-secondary">\' . lang(\'Handling on\') . \'</h3>', $src, 'the Handling on heading is unchanged');
	pg_assert_contains('<h3 class="h5 fg-secondary mt-5">\' . lang(\'Ships on\') . \'</h3>', $src, 'the Ships on heading is unchanged');
}

function test_shipping_method_rate_tiers_and_rate_boxes_are_named_groups()
{
	$src = commerce_form_group_names_source('includes/forms/shipping_method_form.php');

	pg_assert_contains('<div class="col-12 my-1" role="group" aria-label="\' . h(lang(array(\'string\' => \'Base Rate {var:1}\', \'vars\' => $number))) . \'">', $src, 'each tier row is named with its number');
	pg_assert_contains('<div class="border-1 border rounded-4 p-3 m-1" role="group" aria-labelledby="\' . $rate . \'_label">', $src, 'each rate box is named by its rate');
	pg_assert_contains('<label class="form-label" id="\' . $rate . \'_label" for="\' . $rate . \'">\' . $label . \'</label>', $src, 'the rate label has an id');
}

function test_product_group_display_type_is_a_legend()
{
	$src = commerce_form_group_names_source('includes/forms/product_group_form.php');

	pg_assert_contains('<legend class="form-label fs-md">\' . lang(\'Display Type\') . \'</legend>', $src, 'Display Type is a legend');
	pg_assert_true(strpos($src, '<label class="form-label">\' . lang(\'Display Type\')') === false, 'no orphan Display Type label');
	pg_assert_contains('aria-describedby="display_type_browse_help"', $src, 'Browse carries its explanation');
	pg_assert_contains('aria-describedby="display_type_select_help"', $src, 'Select carries its explanation');
	pg_assert_contains('data-bs-target="#display_type_select_row"', $src, 'Select still opens its row');
}

function test_gift_card_email_format_is_a_legend()
{
	$src = commerce_form_group_names_source('product_builder.php');

	pg_assert_contains('<legend class="form-label fs-md">\' . lang(\'Format\') . \'</legend>', $src, 'Format is a legend');
	pg_assert_true(strpos($src, '<label class="form-label">\' . lang(\'Format\')') === false, 'no orphan Format label');
	pg_assert_contains('data-bs-target="#gift_card_email_format_html_row"', $src, 'HTML still opens its row');
}
