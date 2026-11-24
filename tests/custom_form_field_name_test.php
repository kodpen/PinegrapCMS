<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a designed form field's name in the visual designer: a rename
 * is refused when another field has the name as the database compares it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// 'sehir' and 'şehir' are one name to form_fields.name (utf8mb4_unicode_ci):
// the rename's clash check folds marks as well as case.
function test_custom_form_rename_clash_folds_diacritics()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_true(strpos($js, "function _cfNameFold(name)") !== false, 'the fold helper');
	pg_assert_true(strpos($js, ".normalize('NFD').replace(/[\\u0300-\\u036f]/g, '')") !== false, 'marks are dropped');

	$start = strpos($js, "on('sd-cf-name', 'change'");
	$body = substr($js, $start, strpos($js, "on('sd-cf-required'", $start) - $start);
	pg_assert_true(strpos($body, '_cfNameFold(f.key) === _cfNameFold(v)') !== false, 'the rename compares folded names');
}
