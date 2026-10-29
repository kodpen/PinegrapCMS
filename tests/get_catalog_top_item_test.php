<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the top item of the catalog page (get_catalog.php): the top
 * item's HTML is built from the top item's own variables only, and the
 * featured items list starts empty in the branch that prints it, so no
 * variable is read before it is assigned.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function get_catalog_top_item_test_source()
{
	return file_get_contents(PG_FUNCTIONS_DIR . '/get_catalog.php');
}

// The part of get_catalog.php between the edit container of the top item
// and the start of the featured items loop.
function get_catalog_top_item_test_section()
{
	$source = get_catalog_top_item_test_source();
	$start = strpos($source, "\$output_edit_container_end = '</div>';");
	$end = $start === false ? false : strpos($source, 'foreach ($featured_items as $key => $featured_item)', $start);

	pg_assert_true($start !== false && $end !== false && $start < $end);

	return substr($source, $start, $end - $start);
}

function test_get_catalog_top_item_reads_no_unassigned_variable()
{
	$section = get_catalog_top_item_test_section();
	$before_reset = substr($section, 0, strpos($section, "\$output_featured_items = '';"));

	pg_assert_false(strpos($before_reset, '$output_featured_items') !== false);
	pg_assert_false(strpos($before_reset, '$output_image') !== false);
	pg_assert_false(strpos($before_reset, '$output_short_description') !== false);
	pg_assert_false(strpos($before_reset, '$output_price') !== false);
}

function test_get_catalog_top_item_html_is_unchanged()
{
	$section = get_catalog_top_item_test_section();

	pg_assert_contains("                // prepare to output top item
                \$output_top_item =
                    '<div class=\"top_item\">
                        <div class=\"heading\" style=\"border: none\"><h4>' . \$output_top_item_heading . '</h4></div>
                        ' . \$output_edit_container_start . '
                        ' . \$output_top_item_full_description . '
                        ' . \$output_top_item_keywords . '
                        ' . \$output_top_item_price . '
                        ' . \$output_top_item_more_detail . '
                        ' . \$output_edit_container_end . '
                    </div>';
                
                // if there is more than one featured and new items, then prepare to output table for them
                if ((count(\$featured_items) + count(\$new_items)) > 1) {
                    \$output_featured_items = '';
", $section);
}
