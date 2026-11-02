<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Default Share Image warning names each form once and told apart,
 * leaves out forms in the Recycle Bin and writes only the first few names.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_seo_default_image_forms_text_names_the_first_few()
{
	pg_assert_same('', pg_record_forms_sharing_default_image_text(array()));
	pg_assert_same('A, B', pg_record_forms_sharing_default_image_text(array(7 => 'A', 9 => 'B')));
	pg_assert_same('A, B, C, D, E', pg_record_forms_sharing_default_image_text(array('A', 'B', 'C', 'D', 'E')), 'five fit without a count');
	pg_assert_same(
		'A, B, C, D, E ' . lang(array('string' => 'and {var:1} more', 'vars' => array(2))),
		pg_record_forms_sharing_default_image_text(array('A', 'B', 'C', 'D', 'E', 'F', 'G')));
	pg_assert_same('A ' . lang(array('string' => 'and {var:1} more', 'vars' => array(1))), pg_record_forms_sharing_default_image_text(array('A', 'B'), 1));
}

function test_seo_default_image_forms_told_apart_and_binned_left_out()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/seo.php');
	$start = strpos($source, 'function pg_record_forms_sharing_default_image()');
	$body = substr($source, $start, strpos($source, 'function pg_record_forms_sharing_default_image_text') - $start);

	pg_assert_contains('pg_submitted_form_list_labels($forms)', $body, 'same-named forms get their page name');
	pg_assert_contains("isset(\$bin_folders[(int) \$form['page_folder']])", $body, 'binned forms are left out');
	pg_assert_contains("' (#' . \$form['id'] . ')'", $body, 'a label still equal gets its id');

	$prep = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/prep.php');
	pg_assert_contains('pg_record_forms_sharing_default_image_text($og_default_forms)', $prep, 'the warning writes the short list');
}
