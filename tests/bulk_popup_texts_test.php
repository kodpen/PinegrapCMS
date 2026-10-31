<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the bulk edit texts: the Modify Files, Modify Pages and Modify
 * Products popups print their instruction as visible text instead of a hover
 * popover on the header, and the variant sets bulk bar names what it acts on.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function bulk_popup_texts_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_bulk_popup_texts_modify_popups_show_their_instruction()
{
	$popups = array(
		'edit_files.php' => array("<main id=\"content\" class=\"container-fluid\">\n        <p class=\"mt-1 mb-3\">' . lang('You may update the selected Files via the form below. You may leave an option unselected if you do not want to modify a property.') . '</p>", "<span class=\"navbar-text me-auto\">' . lang('Modify Files') . '</span>"),
		'edit_pages.php' => array("<main id=\"content\" class=\"container-fluid\">\n        <p class=\"mt-1 mb-3\">' . lang('You may update various properties below for the selected pages. You may leave a pick list unselected if you do not want to modify a property.') . '</p>", "<span class=\"navbar-text me-auto\">' . lang('Modify Pages') . '</span>"),
		'includes/templates/edit_products.php' => array("<main id=\"content\" class=\"container-fluid\">\n    <p class=\"mt-1 mb-3\"><?=lang(array('string'=>'You may update the selected Products via the form below.' ))?></p>", "<span class=\"navbar-text me-auto\">\n\t\t\t<?=lang(array('string'=>'Modify Products' ))?>"),
	);

	foreach ($popups as $file => $pins) {
		$source = bulk_popup_texts_source($file);

		pg_assert_contains($pins[0], $source, $file . ' shows its instruction above the form');
		pg_assert_contains($pins[1], $source, $file . ' header no longer hides the instruction in a popover');
		pg_assert_false(strpos($source, 'data-bs-content="') !== false, $file . ' keeps a hover-only copy');
	}
}

function test_bulk_popup_texts_variant_sets_bar_acts_on_the_selection()
{
	$source = bulk_popup_texts_source('product_builder.php');

	pg_assert_contains('data-pg-vs-status="enabled"><i class="bi bi-toggle-on me-3"></i>\' . lang(\'Enable Selected\') . \'</button>', $source, 'the enable button names the selection');
	pg_assert_contains('data-pg-vs-status="disabled"><i class="bi bi-toggle-off me-3"></i>\' . lang(\'Disable Selected\') . \'</button>', $source, 'the disable button names the selection');
}
