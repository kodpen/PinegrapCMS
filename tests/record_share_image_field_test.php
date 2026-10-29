<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the share image field of a custom form's record pages
 * (custom_form_pages.share_image_field_id): the order the resolver reads the
 * form's fields in (pg_record_share_image_sources()), when a form's records
 * fall back to the default share image (pg_record_share_image_uses_default()),
 * and the field name a design template or the designer saves, turned into the
 * id of one of the form's file fields (pg_cf_share_image_field_id()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function pg_test_share_image_fields()
{
	return array(
		array('id' => '11', 'name' => 'title', 'type' => 'text box', 'rss_field' => 'title'),
		array('id' => '12', 'name' => 'cover_image', 'type' => 'file upload', 'rss_field' => ''),
		array('id' => '13', 'name' => 'attachment', 'type' => 'file upload', 'rss_field' => ''),
		array('id' => '14', 'name' => 'picture_url', 'type' => 'text box', 'rss_field' => 'media'),
	);
}

// The chosen file field first, as an upload only; then the RSS media field,
// whose value decides; nothing else is guessed.
function test_record_share_image_sources_configured_then_media()
{
	pg_assert_same(
		array(array('id' => 12, 'file_only' => true), array('id' => 14, 'file_only' => false)),
		pg_record_share_image_sources(12, pg_test_share_image_fields()));
}

// 0 is the rule from before the setting: the media field alone.
function test_record_share_image_sources_unset_is_media_only()
{
	pg_assert_same(array(array('id' => 14, 'file_only' => false)), pg_record_share_image_sources(0, pg_test_share_image_fields()));
	pg_assert_same(array(array('id' => 14, 'file_only' => false)), pg_record_share_image_sources('', pg_test_share_image_fields()));
}

// A stored id that is no file field of the form (a text field, a field
// since deleted, another form's field) is not read.
function test_record_share_image_sources_ignore_a_field_that_is_no_file_field()
{
	pg_assert_same(array(array('id' => 14, 'file_only' => false)), pg_record_share_image_sources(11, pg_test_share_image_fields()));
	pg_assert_same(array(array('id' => 14, 'file_only' => false)), pg_record_share_image_sources(99, pg_test_share_image_fields()));
}

// The media field chosen as the share image field is read once, by the
// media rule.
function test_record_share_image_sources_media_field_chosen_is_read_once()
{
	$fields = pg_test_share_image_fields();
	$fields[1]['rss_field'] = 'media';
	$fields[3]['rss_field'] = '';
	pg_assert_same(array(array('id' => 12, 'file_only' => false)), pg_record_share_image_sources(12, $fields));
}

// No media field and no chosen field: nothing to read, the default image.
function test_record_share_image_sources_without_media_or_setting()
{
	$fields = pg_test_share_image_fields();
	$fields[3]['rss_field'] = '';
	pg_assert_same(array(), pg_record_share_image_sources(0, $fields));
	pg_assert_same(array(array('id' => 13, 'file_only' => true)), pg_record_share_image_sources(13, $fields));
	pg_assert_same(array(), pg_record_share_image_sources(12, 'not a list'));
}

// The notice: shown only while the form has no media field and no valid
// share image field.
function test_record_share_image_uses_default()
{
	$fields = pg_test_share_image_fields();
	pg_assert_false(pg_record_share_image_uses_default(0, $fields));

	$fields[3]['rss_field'] = '';
	pg_assert_true(pg_record_share_image_uses_default(0, $fields));
	pg_assert_true(pg_record_share_image_uses_default(11, $fields));
	pg_assert_false(pg_record_share_image_uses_default(12, $fields));
}

// A template or the designer names the field; the save stores the id of
// that file field of the form, 0 for anything else.
function test_record_share_image_field_id_from_name()
{
	$fields = pg_test_share_image_fields();
	pg_assert_same(12, pg_cf_share_image_field_id('cover_image', $fields));
	pg_assert_same(12, pg_cf_share_image_field_id('Cover_Image', $fields));
	pg_assert_same(0, pg_cf_share_image_field_id('title', $fields));
	pg_assert_same(0, pg_cf_share_image_field_id('missing', $fields));
	pg_assert_same(0, pg_cf_share_image_field_id('', $fields));
	pg_assert_same(0, pg_cf_share_image_field_id(array('cover_image'), $fields));
}

// The resolver walks the sources in order and hands every upload to the
// open-file check; the chosen field stops at the upload.
function test_record_share_image_resolver_reads_the_sources()
{
	$seo = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/seo.php');

	$start = strpos($seo, 'function pg_resolve_submitted_form_og_image(');
	$body  = substr($seo, $start, strpos($seo, "\nfunction ", $start + 10) - $start);
	pg_assert_contains('foreach (pg_record_share_image_sources(', $body);
	pg_assert_contains('if ($source[\'file_only\']) {', $body);
	$upload = strpos($body, '$image = pg_og_image_from_open_file($media[\'file_id\']);');
	$stop   = strpos($body, 'if ($source[\'file_only\']) {');
	$rich   = strpos($body, 'pg_first_image_url_from_html($data)');
	pg_assert_true($upload !== false && $stop !== false && $rich !== false, 'the three steps are there');
	pg_assert_true($upload < $stop && $stop < $rich, 'the upload, then the file-only stop, then the media value');
}
