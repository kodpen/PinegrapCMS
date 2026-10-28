<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source tests for panel labels that once pointed at another field or at an
 * id that does not exist: each <label for> must name the field it captions,
 * so a screen reader announces that field under its own label.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * The label text, the id its for must name, and the file. The id must also
 * be present in the same file, as id="…" or 'id'=>'…'.
 */
function test_panel_labels_point_at_their_field()
{
	$root = dirname(__DIR__) . '/pinegrap/';
	$cases = array(
		array('includes/forms/page_form.php', 'Quiz Pass Percentage', 'custom_form_quiz_pass_percentage'),
		array('edit_menu_item.php', 'Position', 'sort_order'),
		array('edit_page.php', 'Promote on Keyword', 'search_keywords'),
		array('view_shipping_report.php', 'Status', 'status'),
		array('includes/forms/container_form.php', 'Weight', 'weight'),
	);
	foreach ($cases as $case) {
		list($file, $text, $id) = $case;
		$src = (string) file_get_contents($root . $file);
		$found = preg_match('/<label\b[^>]*\bfor="([^"]*)"[^>]*>\'\s*\.\s*lang\(\'' . preg_quote($text, '/') . '\'\)/', $src, $m);
		pg_assert_true($found === 1, $file . ': label "' . $text . '" has a for');
		pg_assert_same($id, $found ? $m[1] : '', $file . ': label "' . $text . '" for');
		pg_assert_true(preg_match('/id="' . preg_quote($id, '/') . '"|\'id\'\s*=>\s*\'' . preg_quote($id, '/') . '\'/', $src) === 1, $file . ': #' . $id . ' exists');
	}
}

/**
 * The container's kg field is swapped in for the pound field by script and
 * shares the Weight label through aria-labelledby.
 */
function test_panel_container_weight_kg_named()
{
	$src = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/includes/forms/container_form.php');
	pg_assert_contains('id="weight_label"', $src, 'container_form.php: Weight label id');
	pg_assert_true(preg_match('/<input[^>]*id="weightkg"[^>]*aria-labelledby="weight_label"/', $src) === 1, 'container_form.php: #weightkg aria-labelledby');
}
