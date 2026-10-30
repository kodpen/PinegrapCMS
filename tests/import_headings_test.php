<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for reading the first row and the data rows of an import file:
 * pg_import_heading() (includes/fn/ecommerce.php), which the products and
 * campaign profile importers match their column names through, and
 * pg_import_row_is_blank() (includes/fn/forms.php), which keeps a blank line
 * of a submitted forms file from becoming an empty submitted form. Also that
 * the three importers call them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A heading typed by hand or saved by a spreadsheet matches the column name
// whatever its case, surrounding spaces and word separator.
function test_import_heading_matches_regardless_of_case_and_spacing()
{
	pg_assert_same('name', pg_import_heading('name'), 'already a column name');
	pg_assert_same('name', pg_import_heading('Name'), 'capital');
	pg_assert_same('price', pg_import_heading(' PRICE '), 'upper case and spaces');
	pg_assert_same('short_description', pg_import_heading('Short Description'), 'space between words');
	pg_assert_same('extra_shipping_cost', pg_import_heading('extra-shipping  cost'), 'dash and two spaces');
	pg_assert_same('from_email_address', pg_import_heading("From_Email_Address\t"), 'underscores kept, tab trimmed');
	pg_assert_same('', pg_import_heading(''), 'empty heading');
	pg_assert_same('', pg_import_heading(null), 'missing heading');
}

// A UTF-8 heading is lower-cased as UTF-8, not byte by byte.
function test_import_heading_lowers_utf8()
{
	pg_assert_same('özel_ürün', pg_import_heading('ÖZEL ÜRÜN'), 'Turkish letters');
}

// fgetcsv() gives array(null) for a blank line and empty strings for a row
// of commas; neither is a row of data.
function test_import_row_is_blank()
{
	pg_assert_true(pg_import_row_is_blank(array(null)), 'blank line');
	pg_assert_true(pg_import_row_is_blank(array('', '')), 'row of commas');
	pg_assert_true(pg_import_row_is_blank(array(' ', "\t")), 'spaces only');
	pg_assert_true(pg_import_row_is_blank(array()), 'no cells');
	pg_assert_true(!pg_import_row_is_blank(array('', 'a@x.com')), 'one cell filled');
	pg_assert_true(!pg_import_row_is_blank(array('0')), 'zero is a value');
}

// The importers go through the helpers: products and campaign profiles
// match headings with pg_import_heading(), products refuse a file without a
// name column, submitted forms pass over a blank row before the INSERT.
function test_import_screens_use_heading_and_blank_row_helpers()
{
	$products = file_get_contents(PG_FUNCTIONS_DIR . '/import_products.php');
	pg_assert_true(strpos($products, 'switch (pg_import_heading($column_name))') !== false, 'products heading switch');
	pg_assert_true(strpos($products, "in_array('name', \$column_names, true)") !== false, 'products name column check');

	$profiles = file_get_contents(PG_FUNCTIONS_DIR . '/import_email_campaign_profiles.php');
	pg_assert_true(strpos($profiles, 'pg_import_heading($name)') !== false, 'campaign profile headings');
	pg_assert_true(strpos($profiles, "if (!isset(\$columns['name']))") !== false, 'campaign profile name guard');

	$forms = file_get_contents(PG_FUNCTIONS_DIR . '/import_submitted_forms.php');
	$blank = strpos($forms, 'pg_import_row_is_blank($row)');
	$insert = strpos($forms, 'INSERT INTO forms');
	pg_assert_true(($blank !== false) && ($insert !== false) && ($blank < $insert), 'blank row passed over before the insert');
}
