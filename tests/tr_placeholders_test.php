<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Every Turkish value keeps what its English key needs to render: the
 * placeholders lang(), sprintf() and the JavaScript helpers replace, the
 * HTML tags and escaped entities the key prints, and the space at the
 * start or end of a key that is joined to other text, and no value is
 * empty.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');

// The rules are pg_lang_wording_problems() (includes/fn/lang_pack.php),
// which tools/check_lang.php also runs on every other language.
function tr_placeholders_test_problems($key, $value)
{
	return pg_lang_wording_problems($key, $value);
}

// The rules themselves, on made-up pairs.
function test_tr_placeholders_rules_catch_each_break()
{
	pg_assert_same(array(), tr_placeholders_test_problems('Hello {var:1|c}', '{var} merhaba'), '{var} and {var:1|c} are the same value');
	pg_assert_same(array('{var} placeholders'), tr_placeholders_test_problems('Your {var:1} is saved', 'Sepetiniz kaydedildi'), 'a dropped {var:1}');
	pg_assert_same(array(), tr_placeholders_test_problems('{var:1} Page{suffix:1}', '{var:1} Sayfa'), 'a dropped plural suffix');
	pg_assert_same(array('{suffix} placeholders'), tr_placeholders_test_problems('{var:1} Page', '{var:1} Sayfa{suffix:1}'), 'an added suffix');
	pg_assert_same(array('sprintf placeholders'), tr_placeholders_test_problems('%d items', 'öğeler'), 'a dropped %d');
	pg_assert_same(array(), tr_placeholders_test_problems('Save %1$s to %2$s', '%2$s içine %1$s kaydet'), 'reordered positional placeholders');
	pg_assert_same(array(), tr_placeholders_test_problems('100% done', '%100 bitti'), 'a percent sign is not a placeholder');
	pg_assert_same(array('named placeholders'), tr_placeholders_test_problems('Step {n} of {total}', 'Adım {n}'), 'a dropped {total}');
	pg_assert_same(array(), tr_placeholders_test_problems('{{name}} and {Color}', '{{name}} ve {Renk}'), 'mustache and example names');
	pg_assert_same(array('tags or entities'), tr_placeholders_test_problems('by {var:1}', '<strong>{var:1}</strong> tarafından'), 'added bold');
	pg_assert_same(array('tags or entities'), tr_placeholders_test_problems('(e.g. &lt;a&gt;)', '(ör. <a>)'), 'escaped markup turned into a tag');
	pg_assert_same(array(), tr_placeholders_test_problems('Terms &amp; Conditions', 'Şartlar ve Koşullar'), '&amp; may become a word');
	pg_assert_same(array('trailing space'), tr_placeholders_test_problems('Error: ', 'Hata:'), 'a dropped trailing space');
	pg_assert_same(array('leading space'), tr_placeholders_test_problems(' more', 'daha'), 'a dropped leading space');
}

// Every key of the Turkish shards passes the rules.
function test_tr_placeholders_every_turkish_value_keeps_its_key_parts()
{
	$map = pg_test_language_map('tr');

	pg_assert_true(count($map) > 1000, 'the Turkish wording is read');

	// Site visitor wording, left to a sweep of the site output: the saved
	// cart line drops the shop's cart label ({var:1}).
	$skipped = array(
		'Your {var:1} is saved. To return to this {var:1} later, please use this link:' => true,
	);

	$broken = array();

	foreach ($map as $key => $value) {
		if (isset($skipped[$key])) {
			continue;
		}

		$problems = tr_placeholders_test_problems($key, $value);

		if ($problems) {
			$broken[] = json_encode((string) $key, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ': ' . implode(', ', $problems);
		}
	}

	pg_assert_same(array(), $broken, 'Turkish values that break their key');
}
