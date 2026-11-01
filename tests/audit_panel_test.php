<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the editor's audit panel on the server side: the node marks the
 * audit render writes (and only it), the nodes the accessibility checks
 * report from them, and the registry rows the panel lists its concepts by.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/a11y.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/seo_draft.php');
require_once(PG_FUNCTIONS_DIR . '/seo_structure.php');

function audit_panel_test_tree()
{
	return array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', '_id' => 'n_main', 'props' => array('tag' => 'main'), 'children' => array(
			array('type' => 'content', '_id' => 'n_img', 'props' => array('contentType' => 'image', 'src' => 'kapi.jpg', 'alt' => ''), 'children' => array()),
		)),
	));
}

function test_audit_render_marks_nodes_only_while_on()
{
	$plain = generate_style_code_from_tree(audit_panel_test_tree(), 'x');
	pg_assert_true(strpos($plain, 'data-pg-node') === false, 'a saved page carries no node marks');

	pg_designer_audit_render(true);
	try {
		$marked = generate_style_code_from_tree(audit_panel_test_tree(), 'x');
	} finally {
		pg_designer_audit_render(false);
	}
	pg_assert_true(strpos($marked, 'data-pg-node="n_main"') !== false, $marked);
	pg_assert_true(!pg_designer_audit_render(), 'the flag is off again');

	$again = generate_style_code_from_tree(audit_panel_test_tree(), 'x');
	pg_assert_same($plain, $again);
}

function test_a11y_findings_name_the_marked_nodes()
{
	$html = '<!DOCTYPE html><html lang="tr"><head><title>T</title></head><body><main data-pg-node="n_main">'
		. '<div data-pg-node="n_box"><img src="a.jpg"></div><img src="b.jpg" data-pg-node="n_img">'
		. '<a href="/x" data-pg-node="n_link"></a><img src="c.jpg"></main></body></html>';

	$findings = pg_a11y_check_html($html);

	pg_assert_same(array('n_box', 'n_img', 'n_main'), $findings['a11y_img_alt']['nodes'], 'the nearest marked element, once each');
	pg_assert_same(3, $findings['a11y_img_alt']['occurrences']);

	pg_assert_same(array('n_link'), $findings['a11y_link_name']['nodes']);
	$findings = pg_a11y_check_html('<p><img src="a.jpg"></p><p data-pg-node="n_p">x</p>');
	pg_assert_true(!isset($findings['a11y_img_alt']['nodes']), 'an element with no marked ancestor names no node');
}

function test_a11y_findings_without_marks_keep_their_shape()
{
	$findings = pg_a11y_check_html('<p><img src="a.jpg"></p>');

	pg_assert_same(array('code', 'severity', 'occurrences', 'detail'), array_keys($findings['a11y_img_alt']), 'what the report stores is unchanged');
}

function test_audit_panel_concept_rows()
{
	$rows = pg_seo_check_concepts();
	$by = array();
	foreach ($rows as $row) {
		$by[$row['concept']] = $row;
	}

	pg_assert_same(count(pg_audit_rules()), count($rows));
	pg_assert_same(array('concept', 'label', 'seo', 'seo_severity', 'a11y', 'a11y_severity', 'criterion', 'level', 'editor', 'browser'), array_keys($by['img_alt']));
	pg_assert_same('error', $by['img_alt']['seo_severity']);
	pg_assert_same('1.1.1', $by['img_alt']['criterion']);
	pg_assert_same(pg_a11y_issue_label('a11y_img_alt'), $by['img_alt']['label']);
	pg_assert_same(array('empty_btn'), $by['button_name']['editor']);
	pg_assert_same('', $by['dup_id']['label'], 'an editor-only concept is labelled by the editor');
	pg_assert_true($by['contrast']['browser']);
}

function test_audit_panel_a11y_rows()
{
	$rows = pg_seo_check_a11y_rows(array(
		'a11y_img_alt' => array('code' => 'a11y_img_alt', 'severity' => 'error', 'occurrences' => 2, 'detail' => 'a.jpg', 'nodes' => array('n_img')),
		'a11y_html_lang' => array('code' => 'a11y_html_lang', 'severity' => 'error', 'occurrences' => 1, 'detail' => ''),
	));

	pg_assert_same(2, count($rows));
	pg_assert_same('img_alt', $rows[0]['concept']);
	pg_assert_same(array('n_img'), $rows[0]['nodes']);
	pg_assert_same('1.1.1', $rows[0]['criterion']);
	pg_assert_same('A', $rows[0]['level']);
	pg_assert_same(array(), $rows[1]['nodes']);
	pg_assert_same(pg_a11y_issue_label('a11y_html_lang'), $rows[1]['label']);
}
