<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the visual editor's SEO check on the draft
 * (includes/fn/seo_draft.php): the draft row get_page_content() draws in a
 * background render, which stored findings the check carries over in each
 * mode, and the permission gate the draft shares with the save.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/seo_draft.php');
require_once(PG_FUNCTIONS_DIR . '/includes/designer_access.php');
require_once(PG_FUNCTIONS_DIR . '/seo_structure.php');

function test_seo_draft_apply_replaces_the_drafted_fields_of_its_page()
{
	$row = array('page_id' => '7', 'page_name' => 'hakkinda', 'page_title' => 'Eski', 'page_meta_description' => 'Eski açıklama',
		'page_tree_code' => '<p>eski</p>', 'page_folder' => '3', 'noindex' => '0');
	$draft = array('page_id' => 7, 'page_title' => 'Yeni', 'page_meta_description' => 'Yeni açıklama',
		'page_tree_code' => '<main><h1>Yeni</h1></main>', 'noindex' => 1, 'page_folder' => 99, 'page_name' => 'baska');

	$out = pg_seo_draft_apply($row, $draft);

	pg_assert_same('Yeni', $out['page_title']);
	pg_assert_same('Yeni açıklama', $out['page_meta_description']);
	pg_assert_same('<main><h1>Yeni</h1></main>', $out['page_tree_code']);
	pg_assert_same('1', $out['noindex']);
	pg_assert_same('3', $out['page_folder'], 'the folder is not a draft field');
	pg_assert_same('hakkinda', $out['page_name'], 'the address is not a draft field');
}

function test_seo_draft_apply_leaves_other_pages_and_missing_rows_alone()
{
	$row = array('page_id' => '8', 'page_title' => 'Başka');

	pg_assert_same($row, pg_seo_draft_apply($row, array('page_id' => 7, 'page_title' => 'Yeni')));
	pg_assert_same($row, pg_seo_draft_apply($row, array()));
	pg_assert_same(null, pg_seo_draft_apply(null, array('page_id' => 7, 'page_title' => 'Yeni')));
}

function test_seo_draft_apply_keeps_a_column_the_database_lacks()
{
	$row = array('page_id' => '7', 'page_title' => 'Eski');

	$out = pg_seo_draft_apply($row, array('page_id' => 7, 'page_tree_code' => '<p>x</p>', 'noindex' => 1));

	pg_assert_true(!array_key_exists('noindex', $out), 'an un-migrated row gets no noindex');
	pg_assert_same('<p>x</p>', $out['page_tree_code'], 'the tree code is the draft body');
}

function test_seo_draft_override_is_read_back_and_cleared()
{
	pg_seo_draft_override(array('page_id' => 7, 'page_title' => 'Yeni'));
	pg_assert_same(7, pg_seo_draft_override()['page_id']);
	pg_seo_draft_override(array());
	pg_assert_same(array(), pg_seo_draft_override());
}

function test_seo_check_carries_stored_document_findings_only_for_a_fragment()
{
	$stored = array(
		array('code' => 'title_tag_missing', 'severity' => 'error', 'occurrences' => 1, 'detail' => ''),
		array('code' => 'h1_multiple', 'severity' => 'warning', 'occurrences' => 2, 'detail' => ''),
		array('code' => 'img_no_alt', 'severity' => 'error', 'occurrences' => 3, 'detail' => 'a.jpg'),
		array('code' => 'a11y_img_alt', 'severity' => 'error', 'occurrences' => 3, 'detail' => ''),
	);
	$raw = array('h1_multiple' => array('severity' => 'warning', 'occurrences' => 1, 'detail' => ''));

	$fragment = pg_seo_check_carried($raw, $stored, false);
	pg_assert_same(array('title_tag_missing'), array_keys($fragment), 'document-only codes the fragment did not find');
	pg_assert_same('error', $fragment['title_tag_missing']['severity']);

	pg_assert_same(array(), pg_seo_check_carried($raw, $stored, true), 'a rendered draft answers the document checks itself');
}

function test_seo_check_draft_context_reads_the_editor_fields()
{
	$context = pg_seo_check_draft_context(array('page_title' => 'Yeni', 'sitemap' => '1', 'folder_public' => true, 'folder_archived' => false), 'catalog', true);

	pg_assert_same('Yeni', $context['title']);
	pg_assert_true($context['in_sitemap']);
	pg_assert_true($context['open_graph']);
	pg_assert_true($context['expect_jsonld'], 'a catalog page carries JSON-LD');

	$context = pg_seo_check_draft_context(array('page_title' => '', 'sitemap' => '1', 'folder_public' => false, 'folder_archived' => false), 'standard', false);
	pg_assert_true(!$context['in_sitemap'], 'a private page is not in the site map');
	pg_assert_true(!$context['open_graph']);
	pg_assert_true(!$context['expect_jsonld']);
}

function test_designer_gate_refuses_a_new_page_from_a_content_level_user()
{
	$tree = array('type' => 'root', 'props' => array(), 'children' => array());

	$gate = pg_designer_gate_tree($tree, 0, array('role' => 3));
	pg_assert_true(!$gate['ok']);
	pg_assert_same(lang('You do not have access to add pages.'), $gate['error']);

	$gate = pg_designer_gate_tree($tree, 0, array('role' => 1));
	pg_assert_true($gate['ok'], 'a designer draws any tree');
	pg_assert_same($tree, $gate['tree']);
}
