<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the audit rule registry (includes/fn/audit_rules.php): the
 * entry shape, the SEO severities, accessibility rules and document-only
 * codes read from it exactly as they were written before, and the source
 * scan of tools/check_audit_rules.php over small pieces of code.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/audit_rules.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/a11y.php');
require_once(PG_FUNCTIONS_DIR . '/seo_structure.php');
require_once(dirname(PG_FUNCTIONS_DIR) . '/tools/check_audit_rules.php');

function test_audit_rules_entry_shape()
{
	$seen = array('seo' => array(), 'a11y' => array());

	foreach (pg_audit_rules() as $concept => $rule) {
		pg_assert_same(array('seo', 'a11y', 'severity', 'criterion', 'level', 'editor', 'document', 'browser'), array_keys($rule), $concept);
		pg_assert_true($rule['seo'] !== '' || $rule['a11y'] !== '' || $rule['editor'], $concept . ' names a check');

		foreach (array('seo', 'a11y') as $scope) {
			if ($rule[$scope] === '') {
				pg_assert_true(!isset($rule['severity'][$scope]), $concept . ' has no ' . $scope . ' severity without a code');
				pg_assert_true(!in_array($scope, $rule['document'], true), $concept);
				continue;
			}
			pg_assert_true(!isset($seen[$scope][$rule[$scope]]), $rule[$scope] . ' belongs to one concept');
			$seen[$scope][$rule[$scope]] = true;
			pg_assert_true(in_array($rule['severity'][$scope], array('error', 'warning', 'notice'), true), $concept);
		}

		if ($rule['a11y'] !== '') {
			pg_assert_true(isset(pg_a11y_criteria()[$rule['criterion']]), $concept . ' reads a known criterion');
			pg_assert_true(in_array($rule['level'], array('A', 'AA'), true), $concept);
		}
		pg_assert_true(is_bool($rule['browser']), $concept);
	}
}

function test_audit_rules_keep_seo_severities()
{
	pg_assert_same(array(
		'title_tag_missing' => 'error', 'title_tag_multiple' => 'warning', 'meta_description_tag_missing' => 'error',
		'canonical_missing' => 'warning', 'canonical_multiple' => 'warning', 'noindex_but_in_sitemap' => 'error',
		'viewport_missing' => 'notice', 'og_incomplete' => 'notice',
		'h1_missing' => 'error', 'h1_multiple' => 'warning', 'h1_empty' => 'error', 'h1_equals_title' => 'notice',
		'h1_too_long' => 'notice', 'heading_starts_at_h2' => 'warning', 'heading_level_skip' => 'warning',
		'heading_empty' => 'warning', 'heading_is_paragraph' => 'notice', 'heading_duplicate_text' => 'notice',
		'p_contains_block' => 'error', 'p_empty' => 'notice', 'br_as_paragraph' => 'notice',
		'presentational_tag' => 'warning', 'bold_italic_tag' => 'notice', 'inline_style_heavy' => 'notice',
		'list_invalid_child' => 'error', 'table_no_header' => 'notice',
		'img_no_alt' => 'error', 'img_alt_is_filename' => 'warning', 'img_no_dimensions' => 'notice', 'img_no_lazy' => 'notice',
		'render_failed' => 'notice', 'link_empty' => 'error', 'link_empty_href' => 'warning',
		'link_generic_anchor' => 'notice', 'link_blank_no_noopener' => 'warning',
		'html_lang_missing' => 'warning', 'main_missing' => 'notice', 'main_multiple' => 'warning',
		'landmark_missing' => 'notice', 'input_no_label' => 'warning', 'iframe_no_title' => 'notice', 'button_empty' => 'warning',
		'thin_content' => 'warning', 'low_text_ratio' => 'notice',
		'jsonld_invalid' => 'error', 'jsonld_missing_type' => 'warning', 'jsonld_missing' => 'notice',
		'product_schema_incomplete' => 'notice', 'h1_only_in_template' => 'warning',
	), pg_audit_sorted(pg_seo_structure_severities(), array(
		'title_tag_missing', 'title_tag_multiple', 'meta_description_tag_missing', 'canonical_missing', 'canonical_multiple',
		'noindex_but_in_sitemap', 'viewport_missing', 'og_incomplete', 'h1_missing', 'h1_multiple', 'h1_empty',
		'h1_equals_title', 'h1_too_long', 'heading_starts_at_h2', 'heading_level_skip', 'heading_empty',
		'heading_is_paragraph', 'heading_duplicate_text', 'p_contains_block', 'p_empty', 'br_as_paragraph',
		'presentational_tag', 'bold_italic_tag', 'inline_style_heavy', 'list_invalid_child', 'table_no_header',
		'img_no_alt', 'img_alt_is_filename', 'img_no_dimensions', 'img_no_lazy', 'render_failed', 'link_empty',
		'link_empty_href', 'link_generic_anchor', 'link_blank_no_noopener', 'html_lang_missing', 'main_missing',
		'main_multiple', 'landmark_missing', 'input_no_label', 'iframe_no_title', 'button_empty', 'thin_content',
		'low_text_ratio', 'jsonld_invalid', 'jsonld_missing_type', 'jsonld_missing', 'product_schema_incomplete',
		'h1_only_in_template',
	)));
}

// $map with its keys in $order, the keys $order does not name last.
function pg_audit_sorted($map, $order)
{
	$sorted = array();
	foreach ($order as $key) {
		if (isset($map[$key])) {
			$sorted[$key] = $map[$key];
		}
	}
	return $sorted + $map;
}

function test_audit_rules_keep_a11y_rules_in_report_order()
{
	$rules = array(
		'a11y_page_title'        => array('error',   '2.4.2', 'A',  true),
		'a11y_html_lang'         => array('error',   '3.1.1', 'A',  true),
		'a11y_main_missing'      => array('warning', '2.4.1', 'A',  true),
		'a11y_heading_order'     => array('warning', '1.3.1', 'A',  false),
		'a11y_img_alt'           => array('error',   '1.1.1', 'A',  false),
		'a11y_form_label'        => array('error',   '1.3.1', 'A',  false),
		'a11y_idref_broken'      => array('warning', '1.3.1', 'A',  false),
		'a11y_error_association' => array('warning', '3.3.1', 'A',  false),
		'a11y_link_name'         => array('error',   '2.4.4', 'A',  false),
		'a11y_link_generic'      => array('notice',  '2.4.4', 'A',  false),
		'a11y_button_name'       => array('error',   '4.1.2', 'A',  false),
		'a11y_frame_title'       => array('error',   '4.1.2', 'A',  false),
		'a11y_media_autoplay'    => array('warning', '1.4.2', 'A',  false),
		'a11y_contrast'          => array('warning', '1.4.3', 'AA', false),
		'a11y_target_size'       => array('notice',  '2.5.8', 'AA', false),
	);
	$expected = array();
	foreach ($rules as $code => $r) {
		$expected[$code] = array('severity' => $r[0], 'criterion' => $r[1], 'level' => $r[2], 'document' => $r[3]);
	}

	pg_assert_same($expected, pg_a11y_rules());
}

function test_audit_rules_keep_seo_document_only_codes()
{
	$codes = pg_seo_document_only_codes();
	sort($codes);

	pg_assert_same(array(
		'h1_missing', 'h1_multiple', 'heading_duplicate_text', 'heading_level_skip', 'heading_starts_at_h2',
		'html_lang_missing', 'jsonld_missing', 'main_missing', 'meta_description_tag_missing', 'og_incomplete',
		'thin_content', 'title_tag_missing', 'title_tag_multiple',
	), $codes);
}

function test_audit_rules_pair_the_shared_concepts()
{
	$rules = pg_audit_rules();

	pg_assert_same(array('img_no_alt', 'a11y_img_alt', array('img_alt')), array($rules['img_alt']['seo'], $rules['img_alt']['a11y'], $rules['img_alt']['editor']));
	pg_assert_same(array('empty_btn'), $rules['link_name']['editor']);
	pg_assert_same(array('empty_btn'), $rules['button_name']['editor']);
	pg_assert_same('heading_level_skip', $rules['heading_order']['seo']);
	pg_assert_same(array('seo'), $rules['heading_order']['document'], 'the heading order is checked on fragments by the report');
	pg_assert_true($rules['contrast']['browser'] && $rules['target_size']['browser'], 'contrast and target size need a browser');
	pg_assert_same('main_landmark', pg_audit_rule_concept('a11y', 'a11y_main_missing'));
	pg_assert_same('main_landmark', pg_audit_rule_concept('seo', 'main_missing'));
	pg_assert_same(array('link_name', 'button_name'), pg_audit_rule_concepts_of_editor('empty_btn'));
	pg_assert_same('', pg_audit_rule_concept('seo', 'no_such_code'));
}

function test_check_audit_rules_reads_the_editor_rules()
{
	$js = "var before = { id: 'menu_item', label: _sdT('x') };\n"
		. "    var _validationRules = [\n"
		. "        { id: 'img_alt', label: _sdT('img alt is missing'),\n"
		. "        { id: 'heading_empty', label: _sdT('Empty heading'), seo: 'heading_empty',\n"
		. "        if (h1 === 0) out.push({ rule: 'h1_missing', label: _sdT('The page has no H1'), seo: 'h1_missing' });\n"
		. "                rule: 'low_contrast',\n"
		. "    // ── Canvas badges\n"
		. "var after = { rule: 'not_a_rule', seo: 'canonical_missing' };\n";

	pg_assert_same(array(
		'img_alt' => '', 'heading_empty' => 'heading_empty', 'h1_missing' => 'h1_missing', 'low_contrast' => '',
	), pg_check_audit_editor_rules($js));
}

function test_check_audit_rules_names_what_is_missing()
{
	$js = "var _validationRules = [\n"
		. "{ id: 'img_alt', label: _sdT('a'),\n"
		. "{ id: 'brand_new', label: _sdT('b'),\n"
		. "{ id: 'heading_empty', label: _sdT('c'), seo: 'h1_missing',\n"
		. "// ── Canvas badges\n";
	$seo = "\$add('img_no_alt'); \$add('heading_empty'); \$findings['h1_missing'] = 1;";

	$problems = pg_check_audit_rules_problems(pg_audit_rules(), $js, $seo, array('pg_a11y_rule_img_alt'));

	pg_assert_true(in_array('editor rule brand_new has no registry entry', $problems, true), implode("\n", $problems));
	pg_assert_true(in_array('editor rule heading_empty says seo: h1_missing, its registry entry says heading_empty', $problems, true), implode("\n", $problems));
	pg_assert_true(in_array('SEO code link_empty (link_name) has no checker in seo_structure.php', $problems, true), implode("\n", $problems));
	pg_assert_true(in_array('accessibility code a11y_link_name (link_name) has no pg_a11y_rule_link_name()', $problems, true), implode("\n", $problems));
	pg_assert_true(in_array('editor rule empty_btn (link_name) is not in style_designer.src.js', $problems, true), implode("\n", $problems));
	pg_assert_true(!in_array('editor rule img_alt has no registry entry', $problems, true));
}

function test_check_audit_rules_passes_on_the_tree()
{
	$root = dirname(PG_FUNCTIONS_DIR);
	$problems = pg_check_audit_rules_run($root . '/pinegrap');

	pg_assert_same(array(), $problems, implode("\n", $problems));
}
