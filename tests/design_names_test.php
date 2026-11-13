<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the names of the two editors in every text: the image editor is
 * the Design Canvas ("Tasarım Tuvali"), the visual page editor the Page
 * Design Studio ("Sayfa Tasarım Stüdyosu"), and designs are Designs, not
 * Visual Designs. Every text shown goes through lang() or _sdT() and so has
 * a key in the Turkish shards (tools/check_lang.php), which makes the
 * shards the place to look: no English key and no Turkish value may use an
 * old name. A third-party product keeps its own name.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Every key => value of the Turkish shards.
function _design_names_turkish()
{
	$all = array();

	foreach (glob(PG_FUNCTIONS_DIR . '/includes/local/tr/*.json') as $file) {
		$shard = json_decode((string) file_get_contents($file), true);

		if (is_array($shard)) {
			$all += $shard;
		}
	}

	return $all;
}

// Names of other makers' products, which keep their own words.
function _design_names_third_party()
{
	return array('Pintura Image Editor');
}

function test_design_names_no_english_text_uses_an_old_name()
{
	$turkish = _design_names_turkish();
	$old = array();

	pg_assert_true(count($turkish) > 1000, 'the shards were read');

	foreach (array_keys($turkish) as $key) {
		if (!in_array($key, _design_names_third_party(), true) && preg_match('/\b(image editor|visual page editor|visual designer|visual designs?)\b/i', $key)) {
			$old[] = $key;
		}
	}

	pg_assert_same(array(), $old, 'English texts with an old name');
}

function test_design_names_no_turkish_text_uses_an_old_name()
{
	$old = array();

	foreach (_design_names_turkish() as $key => $value) {
		if (!in_array($key, _design_names_third_party(), true) && preg_match('/görsel (düzenleyici|sayfa editör|tasarımcı|tasarımlar)/iu', (string) $value)) {
			$old[] = $key . ' => ' . $value;
		}
	}

	pg_assert_same(array(), $old, 'Turkish texts with an old name');
}

function test_design_names_the_new_names_have_their_turkish()
{
	$turkish = _design_names_turkish();

	pg_assert_same('Tasarım Tuvali', $turkish['Design Canvas'] ?? null, 'Design Canvas');
	pg_assert_same('Sayfa Tasarım Stüdyosu', $turkish['Page Design Studio'] ?? null, 'Page Design Studio');
	pg_assert_same('Tasarımlar', $turkish['Designs'] ?? null, 'Designs');
}
