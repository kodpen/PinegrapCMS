<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a volunteer's own document upload
 * (includes/fn/volunteer_documents.php): the typed kind and date, the file's
 * type, size and first bytes, the stored name, the "Upload a new one" form;
 * and that volunteer document files stay out of the panel search and the
 * image editor's file pickers.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/widgets_volunteering.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/image_editor.php');

function test_volunteer_doc_input_check_takes_a_listed_kind_and_a_coming_date()
{
	$kinds = array('Criminal record', 'First-aid card');
	$ok = pg_volunteer_doc_input_check(array('kind' => ' First-aid card ', 'number' => ' A  12 ', 'valid_until' => '2027-03-01'), $kinds, '2026-11-24');

	pg_assert_same('', $ok['answer'], 'a listed kind and a later date');
	pg_assert_same('First-aid card', $ok['kind'], 'the kind trimmed');
	pg_assert_same('A 12', $ok['number'], 'the number with one space');
	pg_assert_same('', pg_volunteer_doc_input_check(array('kind' => 'Criminal record', 'valid_until' => '2026-11-24'), $kinds, '2026-11-24')['answer'], 'today is still valid');
	pg_assert_same('doc_kind', pg_volunteer_doc_input_check(array('kind' => 'Health report', 'valid_until' => '2027-01-01'), $kinds, '2026-11-24')['answer'], 'a kind no role needs');
	pg_assert_same('doc_kind', pg_volunteer_doc_input_check(array('kind' => array('x'), 'valid_until' => '2027-01-01'), $kinds, '2026-11-24')['answer'], 'an array is no kind');
	pg_assert_same('doc_date', pg_volunteer_doc_input_check(array('kind' => 'Criminal record', 'valid_until' => '2026-11-23'), $kinds, '2026-11-24')['answer'], 'a past date');
	pg_assert_same('doc_date', pg_volunteer_doc_input_check(array('kind' => 'Criminal record', 'valid_until' => '2027-02-30'), $kinds, '2026-11-24')['answer'], 'no such day');
	pg_assert_same('doc_date', pg_volunteer_doc_input_check(array('kind' => 'Criminal record', 'valid_until' => ''), $kinds, '2026-11-24')['answer'], 'no date');
	pg_assert_same('doc_date', pg_volunteer_doc_input_check(array('kind' => 'Criminal record', 'valid_until' => '2099-01-01'), $kinds, '2026-11-24')['answer'], 'more than 50 years ahead');
	pg_assert_same(100, mb_strlen(pg_volunteer_doc_input_check(array('kind' => 'Criminal record', 'number' => str_repeat('ş', 150), 'valid_until' => '2027-01-01'), $kinds, '2026-11-24')['number']), 'the number cut to 100');
}

function test_volunteer_doc_file_check_takes_a_pdf_or_a_picture_within_the_cap()
{
	$file = function ($name, $size = 1000, $error = UPLOAD_ERR_OK) {
		return array('name' => $name, 'size' => $size, 'error' => $error, 'tmp_name' => '/tmp/x');
	};

	pg_assert_same(array('answer' => '', 'extension' => 'pdf'), pg_volunteer_doc_file_check($file('Record.PDF'), 0), 'a PDF, any case');
	pg_assert_same('jpeg', pg_volunteer_doc_file_check($file('card.jpeg'), 0)['extension'], 'a picture');
	pg_assert_same('doc_file', pg_volunteer_doc_file_check($file('card.gif'), 0)['answer'], 'a GIF is not taken');
	pg_assert_same('doc_file', pg_volunteer_doc_file_check($file('page.php.pdf.php'), 0)['answer'], 'a script');
	pg_assert_same('doc_file', pg_volunteer_doc_file_check($file('noextension'), 0)['answer'], 'no extension');
	pg_assert_same('doc_missing', pg_volunteer_doc_file_check($file('', 0, UPLOAD_ERR_NO_FILE), 0)['answer'], 'no file chosen');
	pg_assert_same('doc_missing', pg_volunteer_doc_file_check(null, 0)['answer'], 'no upload at all');
	pg_assert_same('doc_missing', pg_volunteer_doc_file_check($file('a.pdf', 10, UPLOAD_ERR_PARTIAL), 0)['answer'], 'a part of a file');
	pg_assert_same('doc_size', pg_volunteer_doc_file_check($file('a.pdf', 0, UPLOAD_ERR_INI_SIZE), 0)['answer'], 'over the server limit');
	pg_assert_same('doc_size', pg_volunteer_doc_file_check($file('a.pdf', 2001), 2000)['answer'], 'over the server\'s file limit');
	pg_assert_same('doc_size', pg_volunteer_doc_file_check($file('a.pdf', PG_VOLUNTEER_DOC_MAX_BYTES + 1), 0)['answer'], 'over the document cap');
	pg_assert_same(2000, pg_volunteer_doc_cap(2000), 'the server\'s lower limit');
	pg_assert_same(PG_VOLUNTEER_DOC_MAX_BYTES, pg_volunteer_doc_cap(0), 'the cap with no server limit');
}

function test_volunteer_doc_sniff_matches_the_first_bytes_to_the_extension()
{
	pg_assert_true(pg_volunteer_doc_sniff("%PDF-1.7\n", 'pdf'), 'a PDF');
	pg_assert_true(pg_volunteer_doc_sniff("\xFF\xD8\xFF\xE0", 'jpg'), 'a JPEG');
	pg_assert_true(pg_volunteer_doc_sniff("\x89PNG\r\n\x1A\n\0\0", 'png'), 'a PNG');
	pg_assert_true(pg_volunteer_doc_sniff("RIFF\x10\0\0\0WEBPVP8 ", 'webp'), 'a WebP');
	pg_assert_false(pg_volunteer_doc_sniff('<html><script>', 'pdf'), 'a page named .pdf');
	pg_assert_false(pg_volunteer_doc_sniff("%PDF-1.7\n", 'png'), 'a PDF named .png');
	pg_assert_false(pg_volunteer_doc_sniff("RIFF\x10\0\0\0WAVE", 'webp'), 'a sound named .webp');
	pg_assert_false(pg_volunteer_doc_sniff('', 'pdf'), 'an empty file');
	pg_assert_false(pg_volunteer_doc_sniff("%PDF-1.7\n", 'gif'), 'a type not taken');
}

function test_volunteer_doc_file_name_carries_the_prefix_and_nothing_personal()
{
	pg_assert_same('volunteer-doc-12-a1b2c3.pdf', pg_volunteer_doc_file_name(12, 'A1B2C3', 'pdf'), 'prefix, id, random part');
	pg_assert_same('volunteer-doc-3-ab.png', pg_volunteer_doc_file_name('3', 'a/b..', 'png'), 'only hex in the random part');
}

function test_volunteer_doc_form_is_a_multipart_post_with_the_programme_kinds()
{
	$form = array('action' => '/volunteer.php', 'hidden' => '<input type="hidden" name="t" value="x">');
	$html = pg_volunteer_doc_form_html($form, pg_volunteer_page_secure_classes(), array('id' => 9), array('Criminal record', 'A<b>'));

	pg_assert_contains('enctype="multipart/form-data"', $html, 'multipart');
	pg_assert_contains('<input type="hidden" name="p" value="9"><input type="hidden" name="action" value="document">', $html, 'the profile and the action');
	pg_assert_contains('<input type="hidden" name="t" value="x">', $html, 'the page\'s own fields');
	pg_assert_contains('<option value="Criminal record">Criminal record</option>', $html, 'a kind');
	pg_assert_contains('<option value="A&lt;b&gt;">A&lt;b&gt;</option>', $html, 'a kind escaped');
	pg_assert_contains('name="valid_until"', $html, 'the date');
	pg_assert_contains('type="file"', $html, 'the file');
	pg_assert_contains('accept=".pdf,.jpg,.jpeg,.png,.webp,', $html, 'the types offered');
	pg_assert_same('', pg_volunteer_doc_form_html($form, pg_volunteer_page_secure_classes(), array('id' => 9), array()), 'no form when no role needs a document');
	pg_assert_contains('form-control form-control-sm', pg_volunteer_doc_form_html($form, pg_volunteer_page_site_classes(), array('id' => 9), array('X')), 'the site\'s look in My Account');
}

function test_volunteer_doc_answers_have_words()
{
	foreach (array('doc_kind', 'doc_date', 'doc_missing', 'doc_file', 'doc_size', 'doc_full', 'doc_failed') as $answer) {
		$text = pg_volunteer_answer_text($answer);
		pg_assert_false($text[1], $answer . ' is a refusal');
		pg_assert_false(strpos($text[0], 'the shift changed') !== false, $answer . ' has its own words');
	}

	pg_assert_true(pg_volunteer_answer_text('doc_saved')[1], 'saved is fine');
}

function test_volunteer_doc_files_stay_out_of_the_search_and_the_pickers()
{
	pg_assert_same(" AND (name NOT LIKE 'volunteer-doc-%')", pg_volunteer_doc_files_sql(), 'the condition');
	pg_assert_same(" AND (files.name NOT LIKE 'volunteer-doc-%')", pg_volunteer_doc_files_sql('files.name'), 'a qualified column');
	pg_assert_contains("NOT LIKE 'volunteer-doc-%'", pg_image_editor_sticker_where('', null, array(7), true), 'the sticker picker');
	pg_assert_contains("NOT LIKE 'volunteer-doc-%'", pg_image_editor_sound_where('', null, array(7), true), 'the sound and clip picker');
	pg_assert_contains('$file_where .= pg_volunteer_doc_files_sql();', file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/search.php'), 'the panel search');
	// The shared picker and the editor's lists take no attachment, which a
	// document file always is.
	pg_assert_contains('(attachment = 0)', pg_picker_files_where(array('design' => true), '', array(), null), 'the shared picker');
}
