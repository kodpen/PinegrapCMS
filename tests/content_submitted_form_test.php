<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the submitted-form content helpers (includes/fn/content.php):
 * the page row of a custom billing or shipping form and the title row shown
 * under a product form's reference code field.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The express order shipping form row carries only an id; the keys the
// callers read are filled, so PHP 8 raises no undefined-key warning.
function test_submitted_form_page_fills_missing_keys()
{
	pg_assert_same(
		array('id' => '12', 'type' => '', 'form_name' => '', 'form_label_column_width' => ''),
		pg_submitted_form_page(array('id' => '12')),
		'express order shipping form row');
	pg_assert_same(
		array('id' => '', 'type' => '', 'form_name' => '', 'form_label_column_width' => ''),
		pg_submitted_form_page(null),
		'no row');
	pg_assert_same(
		array('id' => '', 'type' => '', 'form_name' => '', 'form_label_column_width' => ''),
		pg_submitted_form_page(array('id' => null, 'type' => null)),
		'LEFT JOIN without a page');
	pg_assert_same(
		array('id' => '4', 'form_name' => 'Gift note', 'form_label_column_width' => '30', 'type' => ''),
		pg_submitted_form_page(array('id' => '4', 'form_name' => 'Gift note', 'form_label_column_width' => '30')),
		'billing form row keeps its values');
}

// The label of the form's title field is escaped like every other label.
function test_form_reference_title_row_escapes_label_and_title()
{
	$row = pg_form_reference_title_row(' class="ff_code"', '', 'width: 30%', 'Name <b> & "Co"', 'A & <i>B</i>');

	pg_assert_true(strpos($row, 'Name &lt;b&gt; &amp; &quot;Co&quot;') !== false, 'label escaped');
	pg_assert_true(strpos($row, 'A &amp; &lt;i&gt;B&lt;/i&gt;') !== false, 'title escaped');
	pg_assert_true(strpos($row, '<b>') === false && strpos($row, '<i>') === false, 'no markup from the values');
	pg_assert_true(strpos($row, '<tr class="ff_code">') !== false, 'row class kept');
	pg_assert_true(strpos($row, 'style="width: 30%"') !== false, 'label width kept');
	pg_assert_same('', pg_form_reference_title_row('', '', '', 'Title', ''), 'no title, no row');
}
