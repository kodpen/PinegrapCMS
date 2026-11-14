<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the content part of an e-mail campaign
 * (includes/fn/campaign_content.php): the body kinds and their formats,
 * the footer taken off a pasted body, "the design changed", and the card's
 * markup in the add, edit and read modes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once PG_FUNCTIONS_DIR . '/includes/fn/campaign_content.php';

function test_campaign_content_kinds_and_formats()
{
	pg_assert_same(array('design', 'html', 'text'), array_keys(pg_campaign_content_kinds()), 'three kinds in order');
	pg_assert_same('text', pg_campaign_content_kind('plain_text', 12), 'plain text whatever the page');
	pg_assert_same('design', pg_campaign_content_kind('html', 12), 'HTML with a page is a design');
	pg_assert_same('html', pg_campaign_content_kind('html', 0), 'HTML without a page was pasted');
	pg_assert_same('html', pg_campaign_content_kind('html', ''), 'an empty page id too');
	pg_assert_same('plain_text', pg_campaign_content_format('text'), 'text format');
	pg_assert_same('html', pg_campaign_content_format('design'), 'design format');
	pg_assert_same('html', pg_campaign_content_format('html'), 'pasted format');
	pg_assert_same('html', pg_campaign_content_posted_kind(array('body_kind' => 'html')), 'a posted kind');
	pg_assert_same(null, pg_campaign_content_posted_kind(array('body_kind' => 'other')), 'an unknown kind');
	pg_assert_same(null, pg_campaign_content_posted_kind(array('body_kind' => array('html'))), 'an array');
	pg_assert_same(null, pg_campaign_content_posted_kind(array()), 'none');
}

function test_campaign_content_without_footer()
{
	$body = '<html><body><p>Hi</p><div class="software_email_footer" style="color: #666">'
		. '<a href="x?r=<reference_code></reference_code>">View</a><br>Org<br></div></body></html>';

	pg_assert_same('<html><body><p>Hi</p></body></html>', pg_campaign_content_without_footer($body), 'the footer goes');
	pg_assert_same('<p>Hi</p>', pg_campaign_content_without_footer('<p>Hi</p>'), 'nothing to take');
	pg_assert_same('<div class="other">x</div>', pg_campaign_content_without_footer('<div class="other">x</div>'), 'another div stays');
}

function test_campaign_content_design_changed()
{
	pg_assert_true(pg_campaign_content_design_changed(200, 100), 'changed after the save');
	pg_assert_false(pg_campaign_content_design_changed(100, 200), 'not after');
	pg_assert_false(pg_campaign_content_design_changed(100, 100), 'the same second');
	pg_assert_false(pg_campaign_content_design_changed(0, 100), 'no time known');
}

function test_campaign_content_card_add()
{
	$html = pg_campaign_content_card(array('mode' => 'add', 'kind' => 'design', 'page_field' => '<div class="pg-pfield">PICKER</div>', 'text' => "a<b"));

	pg_assert_contains('<input type="radio" class="pg-campaign-kind" name="body_kind" value="design" id="body_kind_design" checked>', $html, 'the design is chosen');
	pg_assert_contains('value="html" id="body_kind_html">', $html, 'pasted HTML offered');
	pg_assert_contains('value="text" id="body_kind_text">', $html, 'plain text offered');
	pg_assert_contains('<div class="pg-seg pg-campaign-kinds" role="radiogroup"', $html, 'the kinds as a segmented control');
	pg_assert_contains('<label class="pg-field-label" for="page_id">Page</label><div class="pg-pfield">PICKER</div>', $html, 'the page is the picker field the screen passes');
	pg_assert_false(strpos($html, '<select') !== false, 'no list of every page');
	pg_assert_contains('data-base="', $html, 'the frame knows the site address a picked page name is added to');
	pg_assert_contains('<textarea class="pg-input pg-campaign-code" name="html_body"', $html, 'the HTML field');
	pg_assert_contains('placeholder="Type E-mail body here...">a&lt;b</textarea>', $html, 'the text field, escaped');
	pg_assert_contains('id="body_design_edit" target="_blank" rel="noopener" href="#" hidden', $html, 'no edit link before a page is chosen');
	pg_assert_contains('src="about:blank" hidden></iframe>', $html, 'an empty frame waits');
	pg_assert_contains('for="body_preview_phone"', $html, 'the phone preview switch');
	pg_assert_false(strpos($html, 'take_design') !== false, 'no "take again" on create');
}

function test_campaign_content_card_edit_design_changed()
{
	$html = pg_campaign_content_card(array('mode' => 'edit', 'kind' => 'design', 'subject' => 'Sale', 'page_name' => 'news', 'edit_url' => 'edit_page.php?id=3', 'preview' => 'view_email_campaign.php?id=9', 'changed' => true));

	pg_assert_same(1, substr_count($html, 'name="body_kind"'), 'only the stored kind');
	pg_assert_false(strpos($html, 'pg-campaign-kinds') !== false, 'no choice of kind after create');
	pg_assert_contains('value="Sale"', $html, 'the subject');
	pg_assert_contains('href="edit_page.php?id=3"><i class="bi bi-brush"', $html, 'edit the design');
	pg_assert_contains('src="view_email_campaign.php?id=9"', $html, 'the stored body is previewed');
	pg_assert_contains('<button type="submit" class="pg-button pg-button-quiet pg-button-sm" name="take_design" value="1">', $html, 'take the design again');
	pg_assert_false(strpos($html, 'name="page_id"') !== false, 'the page is not chosen again');
}

function test_campaign_content_card_edit_pasted_and_read()
{
	$pasted = pg_campaign_content_card(array('mode' => 'edit', 'kind' => 'html', 'html' => '<p>Hi</p>', 'preview' => 'view_email_campaign.php?id=9'));
	pg_assert_contains('id="html_body" spellcheck="false" placeholder=', $pasted, 'the pasted HTML can be edited');
	pg_assert_contains('&lt;p&gt;Hi&lt;/p&gt;</textarea>', $pasted, 'escaped in its field');
	pg_assert_contains('src="view_email_campaign.php?id=9"', $pasted, 'with its preview under it');

	$read = pg_campaign_content_card(array('mode' => 'read', 'kind' => 'text', 'subject' => 'Done', 'text' => 'Body'));
	pg_assert_contains('<p class="pg-text-2 mb-0">Done</p>', $read, 'the subject as text');
	pg_assert_contains(' readonly placeholder=', $read, 'the body cannot be changed');
	pg_assert_false(strpos($read, 'name="subject"') !== false, 'no subject field');
	pg_assert_false(strpos($read, 'body_preview_phone"><i') !== false, 'no phone preview for plain text');
}

function test_campaign_content_add_screen_uses_the_page_view_picker()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/add_email_campaign.php');

	pg_assert_contains("pg_picker_field('page_view', 'page_id', \$output_page_id", $source, 'the design is the page picker field of kind page_view, as the campaign profile\'s Page');
	pg_assert_contains('pg_picker_script()', $source, 'with the picker\'s script');
	pg_assert_false(strpos($source, 'ORDER BY page.page_name') !== false, 'every page is no longer read into a list');

	$script = pg_campaign_content_script();
	pg_assert_contains('input[data-pg-campaign-page]', $script, 'the frame follows the picked page');
	pg_assert_contains('"edit_page.php?id=" + encodeURIComponent(input.value)', $script, 'and so does Edit the design');
}
