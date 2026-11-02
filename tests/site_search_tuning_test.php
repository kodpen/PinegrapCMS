<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for site search tuning (includes/fn/site_search_tuning.php): the
 * synonym and promoted-result boxes read and written back, the searches a
 * query is expanded to, the promoted results of a query, and how both change
 * the widget's results (pg_search_tuning_apply(), with stub searches).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_search_synonyms_parse_reads_one_group_per_line()
{
	$groups = pg_search_synonyms_parse("Kargo, Teslimat , gönderi\r\n\nkahve\nİade,iade, return\n , ,");
	pg_assert_same(array(array('kargo', 'teslimat', 'gönderi'), array('iade', 'return')), $groups,
		'folded, each word once, a line of one word is no group');
	pg_assert_same(array(), pg_search_synonyms_parse(array('x')), 'a posted array is nothing');
	pg_assert_same("kargo, teslimat, gönderi\niade, return", pg_search_synonyms_format($groups));
	pg_assert_same($groups, pg_search_synonyms_parse(pg_search_synonyms_format($groups)), 'the box reads back the same');

	$many = pg_search_synonyms_parse(str_repeat("a, b\n", 300));
	pg_assert_same(PG_SEARCH_SYNONYM_GROUPS_MAX, count($many));
	$wide = pg_search_synonyms_parse(implode(',', range(1, 50)));
	pg_assert_same(PG_SEARCH_SYNONYM_WORDS_MAX, count($wide[0]));
}

function test_search_expand_whole_query_and_single_words()
{
	$groups = array(array('kargo', 'teslimat', 'gönderi'), array('ücret', 'fiyat'));

	pg_assert_same(array('kargo', 'teslimat', 'gönderi'), pg_search_expand('Kargo', $groups));
	pg_assert_same(array('kahve'), pg_search_expand('kahve', $groups), 'no group, only the typed search');
	pg_assert_same(array(), pg_search_expand('  ', $groups));
	pg_assert_same(array('kargo ücret', 'teslimat ücret', 'gönderi ücret', 'kargo fiyat'),
		pg_search_expand('kargo ücret', $groups), 'a word is replaced, at most four searches');
	pg_assert_same(array('kargo', 'teslimat'), pg_search_expand('kargo', $groups, 2));
	pg_assert_same(array('kargo'), pg_search_expand('kargo', 'not a list'));

	// A phrase in a group matches the whole query.
	pg_assert_same(array('iade süresi', 'cayma hakkı'), pg_search_expand('İade  süresi', array(array('iade süresi', 'cayma hakkı'))));
}

function test_search_promoted_parse_keeps_paths_on_this_site()
{
	$entries = pg_search_promoted_parse(
		"İade, return => /iade-kosullari | İade Koşulları\n"
		. "kahve => urunler/kahve?renk=1\n"
		. "x => https://example.com/\n"
		. "y => //example.com\n"
		. "z => ../config\n"
		. "w => a\\b\n"
		. "v => javascript:alert(1)\n"
		. "no arrow here\n"
		. " => sayfa\n"
		. "u => \n");

	pg_assert_same(2, count($entries), 'only the two paths on this site');
	pg_assert_same(array('terms' => array('iade', 'return'), 'url' => 'iade-kosullari', 'title' => 'İade Koşulları'), $entries[0]);
	pg_assert_same(array('terms' => array('kahve'), 'url' => 'urunler/kahve?renk=1', 'title' => ''), $entries[1]);

	pg_assert_same("iade, return => iade-kosullari | İade Koşulları\nkahve => urunler/kahve?renk=1", pg_search_promoted_format($entries));
	pg_assert_same($entries, pg_search_promoted_parse(pg_search_promoted_format($entries)), 'the box reads back the same');
}

function test_search_promoted_for_matches_a_whole_term()
{
	$entries = array(
		array('terms' => array('iade', 'return'), 'url' => 'iade', 'title' => ''),
		array('terms' => array('iade'), 'url' => 'iade', 'title' => 'again'),
		array('terms' => array('iade'), 'url' => 'sss', 'title' => ''),
	);

	pg_assert_same(array('iade', 'sss'), array_column(pg_search_promoted_for(' İADE ', $entries), 'url'), 'each address once, in order');
	pg_assert_same(array(), pg_search_promoted_for('iade kosullari', $entries), 'a longer search is no match');
	pg_assert_same(array(), pg_search_promoted_for('', $entries));
}

function site_search_tuning_test_row($url)
{
	return array('title' => $url, 'url' => $url);
}

function test_search_tuning_apply_adds_synonym_results_and_pins_promoted()
{
	$found = array(
		'featured' => array(),
		'catalog'  => array(site_search_tuning_test_row('/p/1')),
		'pages'    => array(site_search_tuning_test_row('/kargo'), site_search_tuning_test_row('/iade')),
		'limited'  => false,
	);
	$asked = array();
	$collect = function ($q) use (&$asked) {
		$asked[] = $q;
		return array(
			'featured' => array(),
			'catalog'  => array(),
			'pages'    => array(site_search_tuning_test_row('/kargo'), site_search_tuning_test_row('/' . $q)),
			'limited'  => ($q === 'gönderi'),
		);
	};
	$row = function ($entry) {
		return ($entry['url'] === 'gizli') ? null : array('title' => 'R', 'url' => '/' . $entry['url'], 'promoted' => true);
	};

	$out = pg_search_tuning_apply('Kargo', $found, $collect,
		array(array('kargo', 'teslimat', 'gönderi')),
		array(array('terms' => array('kargo'), 'url' => 'iade'), array('terms' => array('kargo'), 'url' => 'gizli')),
		$row);

	pg_assert_same(array('teslimat', 'gönderi'), $asked, 'each other word searched once');
	pg_assert_same(array('/iade'), array_column($out['featured'], 'url'), 'the promoted address first; one the visitor may not see is left out');
	pg_assert_same(array('/kargo', '/teslimat', '/gönderi'), array_column($out['pages'], 'url'), 'synonym results after the typed ones, each once, the pinned one moved up');
	pg_assert_same(array('/p/1'), array_column($out['catalog'], 'url'));
	pg_assert_true($out['limited'], 'a cut synonym search says so');

	$asked = array();
	$same = pg_search_tuning_apply('kahve', $found, $collect, array(), array(), $row);
	pg_assert_same($found, $same, 'nothing set, nothing changed');
	pg_assert_same(array(), $asked);
}

function test_search_tuning_is_applied_and_edited()
{
	$widgets = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');
	pg_assert_contains("return pg_search_tuning_apply(\$query, \$found, function (\$alt) use (\$cfg, \$advanced) {", $widgets);
	pg_assert_contains('function _pg_sw_search_collect_one($query, $cfg, $advanced = null)', $widgets);

	$card = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/features.php');
	pg_assert_contains('name="search_tuning_card"', $card);
	pg_assert_contains('h($search_synonyms_text)', $card);
	pg_assert_contains('h($search_promoted_text)', $card);

	$save = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/features.save.php');
	pg_assert_contains("array_key_exists('search_tuning_card', \$_POST)", $save, 'a screen without the card keeps the rows');
}
