<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Every Turkish value keeps what its English key needs to render: the
 * placeholders lang(), sprintf() and the JavaScript helpers replace, the
 * HTML tags and escaped entities the key prints, and the space at the
 * start or end of a key that is joined to other text.
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

// The positions of the {var…} placeholders of $text, sorted and unique.
// {var} and its |c/|u/|l forms count as position 1; the modifier changes
// the case of the value, not which value is put in.
function tr_placeholders_test_vars($text)
{
	preg_match_all('/\{var(?::(\d+)|\|(\d+))?(?:\|[cul])?\}/', $text, $matches, PREG_SET_ORDER);

	$positions = array();

	foreach ($matches as $match) {
		$position = '1';

		if (isset($match[1]) && $match[1] !== '') {
			$position = $match[1];
		} elseif (isset($match[2]) && $match[2] !== '') {
			$position = $match[2];
		}

		$positions[$position] = true;
	}

	$positions = array_keys($positions);
	sort($positions);

	return $positions;
}

// The {suffix…} placeholders of $text, sorted and unique.
function tr_placeholders_test_suffixes($text)
{
	preg_match_all('/\{suffix(?::\d+)?\}/', $text, $matches);

	$suffixes = array_values(array_unique($matches[0]));
	sort($suffixes);

	return $suffixes;
}

// The sprintf() placeholders of $text (%s, %d, %1$s, %.2f), sorted.
function tr_placeholders_test_printf($text)
{
	preg_match_all('/%(?:\d+\$)?(?:\.\d+)?[sdf]\b/', $text, $matches);

	$placeholders = $matches[0];
	sort($placeholders);

	return $placeholders;
}

// The lowercase named tokens of $text ({name}, {n}, {position}, {1}),
// sorted and unique. {{mustache}} templates and capitalised example names
// such as {Color} are wording, not code placeholders.
function tr_placeholders_test_named($text)
{
	preg_match_all('/(?<!\{)\{([a-z][a-z0-9_]*|\d+)\}(?!\})/', $text, $matches);

	$names = array();

	foreach ($matches[1] as $name) {
		if ($name !== 'var' && $name !== 'suffix') {
			$names[$name] = true;
		}
	}

	$names = array_keys($names);
	sort($names);

	return $names;
}

// The HTML tags of $text as "name" and "/name", and the escaped entities
// that print markup as text (&lt; &gt; &quot; and numeric ones), sorted.
// &amp; and &nbsp; may become words in a translation.
function tr_placeholders_test_markup($text)
{
	preg_match_all('/<(\/?)([a-zA-Z][a-zA-Z0-9]*)\b/', $text, $tags, PREG_SET_ORDER);
	preg_match_all('/&(?:lt|gt|quot|#\d+|#x[0-9a-fA-F]+);/', $text, $entities);

	$markup = array();

	foreach ($tags as $tag) {
		$markup[] = $tag[1] . strtolower($tag[2]);
	}

	foreach ($entities[0] as $entity) {
		$markup[] = $entity;
	}

	sort($markup);

	return $markup;
}

// The problems of one value against its key; array() when it is fine.
function tr_placeholders_test_problems($key, $value)
{
	$key = (string) $key;
	$value = (string) $value;
	$problems = array();

	if (tr_placeholders_test_vars($key) !== tr_placeholders_test_vars($value)) {
		$problems[] = '{var} placeholders';
	}

	// A Turkish value may drop an English plural suffix, never add one.
	if (array_diff(tr_placeholders_test_suffixes($value), tr_placeholders_test_suffixes($key))) {
		$problems[] = '{suffix} placeholders';
	}

	if (tr_placeholders_test_printf($key) !== tr_placeholders_test_printf($value)) {
		$problems[] = 'sprintf placeholders';
	}

	if (tr_placeholders_test_named($key) !== tr_placeholders_test_named($value)) {
		$problems[] = 'named placeholders';
	}

	if (tr_placeholders_test_markup($key) !== tr_placeholders_test_markup($value)) {
		$problems[] = 'tags or entities';
	}

	if (preg_match('/^\s/u', $key) && !preg_match('/^\s/u', $value)) {
		$problems[] = 'leading space';
	}

	if (preg_match('/\s$/u', $key) && !preg_match('/\s$/u', $value)) {
		$problems[] = 'trailing space';
	}

	return $problems;
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

	$broken = array();

	foreach ($map as $key => $value) {
		$problems = tr_placeholders_test_problems($key, $value);

		if ($problems) {
			$broken[] = json_encode((string) $key, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ': ' . implode(', ', $problems);
		}
	}

	pg_assert_same(array(), $broken, 'Turkish values that break their key');
}
