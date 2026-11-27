<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the language pack helpers (includes/fn/lang_pack.php): the
 * wording check of one value against its key, the keys a language lacks
 * beside Turkish, the figures a shard, and the problems tools/check_lang.php
 * and tools/lang_pack.php --check report for a language other than Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Each rule of the wording check, on made-up pairs.
function test_lang_pack_wording_problems()
{
	pg_assert_same(array(), pg_lang_wording_problems('Hello {var:1|c}', '{var} merhaba'), '{var} and {var:1|c} are the same value');
	pg_assert_same(array('{var} placeholders'), pg_lang_wording_problems('Your {var:1} is saved', 'Gespeichert'), 'a dropped {var:1}');
	pg_assert_same(array('{var} placeholders'), pg_lang_wording_problems('Saved', '{var:1} gespeichert'), 'an added {var:1}');
	pg_assert_same(array(), pg_lang_wording_problems('{var:1} Page{suffix:1}', '{var:1} Seite'), 'a dropped plural suffix');
	pg_assert_same(array('{suffix} placeholders'), pg_lang_wording_problems('{var:1} Page', '{var:1} Seite{suffix:1}'), 'an added suffix');
	pg_assert_same(array('sprintf placeholders'), pg_lang_wording_problems('%d items', 'Einträge'), 'a dropped %d');
	pg_assert_same(array(), pg_lang_wording_problems('Save %1$s to %2$s', '%2$s içine %1$s kaydet'), 'reordered positional placeholders');
	pg_assert_same(array(), pg_lang_wording_problems('100% done', '%100 bitti'), 'a percent sign is not a placeholder');
	pg_assert_same(array('named placeholders'), pg_lang_wording_problems('Step {n} of {total}', 'Schritt {n}'), 'a dropped {total}');
	pg_assert_same(array(), pg_lang_wording_problems('{{name}} and {Color}', '{{name}} und {Farbe}'), 'mustache and example names');
	pg_assert_same(array('tags or entities'), pg_lang_wording_problems('by {var:1}', 'von <strong>{var:1}</strong>'), 'added bold');
	pg_assert_same(array('tags or entities'), pg_lang_wording_problems('(e.g. &lt;a&gt;)', '(z. B. <a>)'), 'escaped markup turned into a tag');
	pg_assert_same(array(), pg_lang_wording_problems('Terms &amp; Conditions', 'Allgemeine Geschäftsbedingungen'), '&amp; may become a word');
	pg_assert_same(array('trailing space'), pg_lang_wording_problems('Error: ', 'Fehler:'), 'a dropped trailing space');
	pg_assert_same(array('leading space'), pg_lang_wording_problems(' more', 'mehr'), 'a dropped leading space');
	pg_assert_same(array('empty wording'), pg_lang_wording_problems('Save', ''), 'an empty value hides the text');
	pg_assert_same(array(), pg_lang_wording_problems('', ''), 'an empty key may stay empty');
}

// The keys Turkish has and the language lacks, in key order, each with its
// Turkish; a shard and a limit cut the list.
function test_lang_pack_missing()
{
	$turkish = array('Save' => 'Kaydet', 'Cancel' => 'İptal', 'Search' => 'Ara', '{var:1} items' => '{var:1} öğe');
	$german = array('Save' => 'Speichern');

	pg_assert_same(
		array(
			array('key' => 'Cancel', 'tr' => 'İptal'),
			array('key' => 'Search', 'tr' => 'Ara'),
			array('key' => '{var:1} items', 'tr' => '{var:1} öğe'),
		),
		pg_lang_pack_missing($turkish, $german)
	);

	pg_assert_same(array(array('key' => 'Search', 'tr' => 'Ara')), pg_lang_pack_missing($turkish, $german, 's'), 'one shard');
	pg_assert_same(array(array('key' => 'Cancel', 'tr' => 'İptal')), pg_lang_pack_missing($turkish, $german, '', 1), 'a limit');
	pg_assert_same(array(), pg_lang_pack_missing($turkish, $turkish), 'nothing missing');

	// A key that is only digits comes back from json_decode() as an integer.
	pg_assert_same(array(array('key' => '404', 'tr' => '404')), pg_lang_pack_missing(array(404 => '404'), array()), 'a numeric key stays a string');
}

// The figures a shard: Turkish keys, translated, missing, keys Turkish lacks.
function test_lang_pack_counts()
{
	$turkish = array('Save' => 'Kaydet', 'Search' => 'Ara', 'Cancel' => 'İptal', '10 days' => '10 gün');
	$german = array('Save' => 'Speichern', 'Cancel' => 'Abbrechen', 'Stray' => 'Streuner');

	pg_assert_same(
		array(
			'0' => array('turkish' => 1, 'translated' => 0, 'missing' => 1, 'extra' => 0),
			'c' => array('turkish' => 1, 'translated' => 1, 'missing' => 0, 'extra' => 0),
			's' => array('turkish' => 2, 'translated' => 1, 'missing' => 1, 'extra' => 1),
		),
		pg_lang_pack_counts($turkish, $german)
	);
}

// A missing key is no fault; a key Turkish lacks and a broken wording are,
// listed in key order.
function test_lang_pack_problems()
{
	$turkish = array('Save' => 'Kaydet', 'Hello {var:1}' => 'Merhaba {var:1}', 'Cancel' => 'İptal');
	$german = array('Save' => 'Speichern', 'Hello {var:1}' => 'Hallo', 'Stray' => 'Streuner');

	pg_assert_same(
		array(
			'{var} placeholders: Hello {var:1}',
			'not a Turkish key: Stray',
		),
		pg_lang_pack_problems($turkish, $german)
	);

	pg_assert_same(array(), pg_lang_pack_problems($turkish, array('Save' => 'Speichern')), 'only missing keys');
}
