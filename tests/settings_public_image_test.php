<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the public image pickers of Site Settings (Site Icon, App Icon,
 * Default Share Image, Organization Logo): the files they list and the names
 * their saves accept leave out attachments and ERP documents
 * (pg_public_image_files_where(), pg_public_image_choice_verdict() in
 * includes/fn/files.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_public_image_files_where()
{
	pg_assert_same("(attachment = 0) AND (erp_doc_type = '')", pg_public_image_files_where(true));
	pg_assert_same('(attachment = 0)', pg_public_image_files_where(false), 'before the ERP column exists');
}

function test_public_image_choice_verdict()
{
	$types = array('png', 'jpg');
	$public = array('type' => 'png', 'attachment' => '0', 'erp_doc_type' => '');
	$erp = array('type' => 'png', 'attachment' => '0', 'erp_doc_type' => 'quote_signature');
	$attachment = array('type' => 'jpg', 'attachment' => '1', 'erp_doc_type' => '');

	pg_assert_true(pg_public_image_choice_verdict('', '', $types, null), 'nothing chosen');
	pg_assert_true(pg_public_image_choice_verdict('logo.png', '', $types, $public));
	pg_assert_false(pg_public_image_choice_verdict('erp-quotesignature-1.png', '', $types, $erp), 'an ERP document is refused');
	pg_assert_false(pg_public_image_choice_verdict('erp-quotesignature-1.png', 'erp-quotesignature-1.png', $types, $erp), 'also when it was stored before');
	pg_assert_false(pg_public_image_choice_verdict('scan.jpg', '', $types, $attachment), 'an attachment is refused');
	pg_assert_false(pg_public_image_choice_verdict('typed.png', '', $types, null), 'a name no files row carries');
	pg_assert_false(pg_public_image_choice_verdict('doc.pdf', '', $types, array('type' => 'pdf', 'attachment' => '0', 'erp_doc_type' => '')), 'not an image type');
	pg_assert_true(pg_public_image_choice_verdict('https://cdn.example.com/a.png', 'https://cdn.example.com/a.png', $types, null), 'a stored value no file carries is kept');
	pg_assert_true(pg_public_image_choice_verdict('old.gif', 'old.gif', $types, array('type' => 'gif', 'attachment' => '0', 'erp_doc_type' => '')), 'a stored public file of another type is kept');
}

// The four pickers read the shared filter, the four saves the shared check.
function test_public_image_pickers_and_saves_wired()
{
	$prep = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/prep.php');
	pg_assert_same(2, substr_count($prep, 'AND " . pg_public_image_files_where()'), 'the site icon list and the share image, logo and app icon list');
	pg_assert_false(strpos($prep, 'AND (attachment = 0) ORDER BY') !== false, 'no list filters on attachment alone');

	$seo = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/seo.save.php');
	pg_assert_contains("pg_public_image_choice_ok(\$site_icon_input", $seo);
	pg_assert_contains("pg_public_image_choice_ok(\$app_icon_input", $seo);
	pg_assert_contains("pg_public_image_choice_ok(\$og_default_image_input", $seo);

	$general = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/general.save.php');
	pg_assert_contains("pg_public_image_choice_ok(\$organization_logo_input", $general);
}
