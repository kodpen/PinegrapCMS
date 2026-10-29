<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins: boxes the server refuses blank carry required (so the panel
 * script gives their label the required marker) and the Delete button in
 * the same form skips that check; the Form Editor box is named by its
 * label; the SEO character counter is tied to its field and says the count,
 * the target range and the state in words.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function required_markers_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function required_markers_field_call($source, $name)
{
	// The liveform call that prints the field: from the output_field( before
	// its name to the end of that statement line.
	if (!preg_match('/\'name\'\s*=>\s*\'' . preg_quote($name, '/') . '\'/', $source, $match, PREG_OFFSET_CAPTURE)) {
		return '';
	}

	$at = $match[0][1];
	$start = strrpos(substr($source, 0, $at), 'output_field(');
	if ($start === false || !preg_match('/[\)\]]\)\s*\./', $source, $close, PREG_OFFSET_CAPTURE, $at)) {
		return '';
	}

	return substr($source, $start, $close[0][1] - $start + 2);
}

function test_required_markers_on_boxes_save_needs()
{
	$fields = array(
		'view_order_report.php' => 'name',
		'view_visitor_report.php' => 'name',
		'edit_theme_file.php' => 'name',
		'edit_comment.php' => 'message',
		'find_and_replace.php' => 'find',
	);

	foreach ($fields as $path => $name) {
		$call = required_markers_field_call(required_markers_source($path), $name);
		pg_assert_true($call !== '', $path . ': field ' . $name . ' not found');
		pg_assert_true((bool) preg_match('/\'required\'\s*=>\s*\'required\'/', $call), $path . ': field ' . $name . ' is not marked required');
	}
}

function test_required_markers_delete_skips_validation()
{
	$paths = array(
		'view_order_report.php',
		'view_visitor_report.php',
		'edit_theme_file.php',
		'edit_comment.php',
	);

	foreach ($paths as $path) {
		$source = required_markers_source($path);
		pg_assert_true((bool) preg_match_all('/<button type="submit"[^>]*value="Delete"[^>]*>/', $source, $matches), $path . ': no Delete button found');

		foreach ($matches[0] as $tag) {
			pg_assert_true(strpos($tag, 'formnovalidate') !== false, $path . ': Delete button without formnovalidate');
		}
	}
}

function test_required_markers_form_editor_label()
{
	$source = required_markers_source('edit_submitted_form.php');
	$call = required_markers_field_call($source, 'form_editor_username');

	pg_assert_true((bool) preg_match('/\'id\'\s*=>\s*\'form_editor_username\'/', $call), 'Form Editor box has no id');
	pg_assert_true(strpos($source, '<label for="form_editor_username"') !== false, 'Form Editor label does not point at its box');
}

function test_required_markers_seo_counter_words()
{
	$source = required_markers_source('assets/js/backend.src.js');
	$start = strpos($source, 'function renderSeoCounter(');
	pg_assert_true($start !== false, 'renderSeoCounter not found');
	$body = substr($source, $start, 3000);

	pg_assert_true(strpos($body, 'aria-describedby') !== false, 'counter is not tied to its field');
	pg_assert_true(strpos($body, 'aria-hidden') !== false, 'counter glyph is read aloud');
	pg_assert_true(strpos($body, 'visually-hidden') !== false, 'counter has no sentence for screen readers');

	$keys = array(
		'No text yet. Aim for {var:1} to {var:2} characters.',
		'{var:1} characters, too short. Aim for {var:2} to {var:3}.',
		'{var:1} characters, good length. Aim for {var:2} to {var:3}.',
		'{var:1} characters, too long. Aim for {var:2} to {var:3}.',
	);
	$map = required_markers_source('includes/fn/output.php');

	foreach ($keys as $key) {
		pg_assert_true(strpos($body, "pgLang('" . $key . "')") !== false, 'counter does not say: ' . $key);
		pg_assert_true(strpos($map, "'" . $key . "' => lang('" . $key . "')") !== false, 'panel translate map lacks: ' . $key);
	}
}
