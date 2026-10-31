<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The search of the Site Settings dialog and the headings over its list: the
 * search index built from the registry and the labels the cards draw, the
 * four group headings in the list's order, and the palette keywords that
 * missed a card or found the wrong one.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_SETTINGS_MENU')) {
	define('PG_SETTINGS_MENU', true);
}

if (!defined('PG_SETTINGS_ENTRY')) {
	define('PG_SETTINGS_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/settings/search.php');
require_once __DIR__ . '/helpers/language_map.php';

function settings_search_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The category keys of the registry and the group each one names, in the
// order of the list. pg_settings_categories() asks the database, so the
// source is read rather than called.
function settings_search_test_registry_groups()
{
	$source = settings_search_test_source('includes/settings/registry.php');

	preg_match_all("/\n        '([a-z_]+)' => array\(\n            'label' => .*?\n            'group' => '([a-z_]+)',/", $source, $match);

	return array_combine($match[1], $match[2]);
}

// Each section's keyword lists as the registry writes them: section id =>
// the English sources passed to lang().
function settings_search_test_keyword_sources()
{
	$source = settings_search_test_source('includes/settings/registry.php');
	$lists = array();

	preg_match_all("/'(pgset-[a-z0-9-]+)'\s*=>\s*pg_settings_keywords\(/", $source, $match, PREG_OFFSET_CAPTURE);

	foreach ($match[0] as $i => $whole) {
		$start = $whole[1];
		$end = strpos($source, "),\n", $start);
		$segment = substr($source, $start, $end - $start);

		preg_match_all("/lang\('((?:[^'\\\\]|\\\\.)*)'\)/", $segment, $texts);

		$lists[$match[1][$i][0]] = array_map('stripslashes', $texts[1]);
	}

	return $lists;
}

// A section's terms in one language, as pg_settings_keywords() returns them.
function settings_search_test_terms($sources, $code)
{
	$map = ($code === 'en') ? array() : pg_test_language_map($code);
	$texts = array();

	foreach ($sources as $source) {
		$texts[] = isset($map[$source]) ? $map[$source] : $source;
	}

	return pg_settings_keywords(implode(', ', $texts));
}

// The palette's own test (actionMatches() in backend.src.js): a term that
// starts with the query, or a query that starts with the term.
function settings_search_test_palette_hits($query, $terms)
{
	foreach ($terms as $term) {
		if ((strpos($term, $query) === 0) || (strpos($query, $term) === 0)) {
			return true;
		}
	}

	return false;
}

function test_settings_search_groups_in_order()
{
	pg_assert_same(
		array('site', 'security', 'selling', 'running'),
		array_keys(pg_settings_groups()),
		'the four headings, in the order of the list');

	pg_assert_same('Running the site', pg_settings_groups()['running'], 'the last heading');
}

function test_settings_search_every_category_names_its_group()
{
	pg_assert_same(array(
		'general'     => 'site',
		'appearance'  => 'site',
		'features'    => 'site',
		'seo'         => 'site',
		'privacy'     => 'site',
		'languages'   => 'site',
		'contact'     => 'site',
		'security'    => 'security',
		'firewall'    => 'security',
		'commerce'    => 'selling',
		'accounting'  => 'selling',
		'api'         => 'running',
		'maintenance' => 'running',
	), settings_search_test_registry_groups(), 'each category under its heading, in today\'s order');
}

function test_settings_search_grouped_skips_an_empty_group_and_keeps_the_order()
{
	$categories = array(
		'general'  => array('label' => 'General', 'group' => 'site'),
		'seo'      => array('label' => 'SEO', 'group' => 'site'),
		'commerce' => array('label' => 'Ecommerce', 'group' => 'selling'),
		'api'      => array('label' => 'Integrations', 'group' => 'running'),
	);

	$grouped = pg_settings_grouped($categories);

	pg_assert_same(array('site', 'selling', 'running'), array_keys($grouped), 'Security has no row and no heading');
	pg_assert_same(array('general', 'seo'), $grouped['site']['categories'], 'rows under Site');
	pg_assert_same('Selling', $grouped['selling']['label'], 'heading label');
}

function test_settings_search_grouped_puts_an_unnamed_group_last()
{
	$grouped = pg_settings_grouped(array(
		'extra'   => array('label' => 'Extra'),
		'general' => array('label' => 'General', 'group' => 'site'),
	));

	pg_assert_same(array('site', 'running'), array_keys($grouped), 'a category without a group goes with the last heading');
	pg_assert_same(array('extra'), $grouped['running']['categories'], 'under Running the site');
}

function test_settings_search_card_labels_from_the_drawn_cards()
{
	$html = '
		<div id="pgset-features" class="pg-set-card">
			<h3 class="pg-set-card-title">Feature Options</h3>
			<label for="a">Prefetch Menu Links</label>
			<label class="x" for="b"><span>Speculation</span> &amp; Rules:</label>
			<div id="pgsub-inner"><label for="c">Prefetch Menu Links</label></div>
			<label for="d">   </label>
		</div>
		<div id="pgset-images" class="pg-set-card">
			<legend>Image Quality *</legend>
			<label for="e">WebP</label>
		</div>
		<div id="pgset-unlisted"><label>Not a card of ours</label></div>';

	$labels = pg_settings_card_labels($html, array('pgset-features', 'pgset-images'));

	pg_assert_same(array('Feature Options', 'Prefetch Menu Links', 'Speculation & Rules'), $labels['pgset-features'], 'heading, labels, repeats dropped');
	pg_assert_same(array('Image Quality', 'WebP'), $labels['pgset-images'], 'a legend, stops at an id not on the list');
}

function test_settings_search_card_labels_skip_a_sentence()
{
	$labels = pg_settings_card_labels('<div id="pgset-x"><label>' . str_repeat('word ', 30) . '</label><label>Short</label></div>', array('pgset-x'));

	pg_assert_same(array('Short'), $labels['pgset-x'], 'a paragraph-long label is not a name');
}

function test_settings_search_index_from_registry_and_labels()
{
	$categories = array(
		'features' => array(
			'label' => 'Features',
			'sections' => array('pgset-features' => 'Feature Options', 'pgset-images' => 'Image Optimization'),
			'keywords' => array('pgset-features' => array('cart', 'prefetch')),
		),
	);

	$index = pg_settings_search_index($categories, array(
		'features' => array('pgset-features' => array('Feature Options', 'Prefetch Menu Links')),
	));

	pg_assert_same(array(
		array('pane' => 'features', 'card' => '', 'title' => 'Features', 'place' => '', 'keys' => array(), 'fields' => array()),
		array('pane' => 'features', 'card' => 'pgset-features', 'title' => 'Feature Options', 'place' => 'Features', 'keys' => array('cart', 'prefetch'), 'fields' => array('Prefetch Menu Links')),
		array('pane' => 'features', 'card' => 'pgset-images', 'title' => 'Image Optimization', 'place' => 'Features', 'keys' => array(), 'fields' => array()),
	), $index, 'a category row, then its cards with their keywords and the labels beside the title');
}

function test_settings_search_index_only_lists_the_registry_sections()
{
	$index = pg_settings_search_index(
		array('appearance' => array('label' => 'Appearance', 'sections' => array('pgset-theme' => 'Theme'), 'keywords' => array())),
		array('appearance' => array('pgset-private-label' => array('Logo'), 'pgset-theme' => array('Acrylic'))));

	pg_assert_same(2, count($index), 'a card the registry left out for this role has no row');
	pg_assert_same(array('Acrylic'), $index[1]['fields'], 'the listed card keeps its labels');
}

function test_settings_search_logo_no_longer_finds_e_invoice()
{
	$lists = settings_search_test_keyword_sources();

	foreach (array('en', 'tr') as $code) {
		pg_assert_false(settings_search_test_palette_hits('logo', settings_search_test_terms($lists['pgset-invoice'], $code)), 'logo on E-Invoice (' . $code . ')');
		pg_assert_true(settings_search_test_palette_hits('logo', settings_search_test_terms($lists['pgset-organization'], $code)), 'logo on Organization (' . $code . ')');
	}
}

function test_settings_search_missed_words_find_their_card()
{
	$lists = settings_search_test_keyword_sources();

	$cases = array(
		'favicon'    => 'pgset-site-icon',
		'kvkk'       => 'pgset-cookies',
		'workspace'  => 'pgset-integration-links',
		'prefetch'   => 'pgset-features',
		'encryption' => 'pgset-encryption',
	);

	foreach ($cases as $query => $section) {
		foreach (array('en', 'tr') as $code) {
			pg_assert_true(settings_search_test_palette_hits($query, settings_search_test_terms($lists[$section], $code)), $query . ' on ' . $section . ' (' . $code . ')');
		}
	}
}

function test_settings_search_the_dialog_draws_headings_and_a_search_box()
{
	$modal = settings_search_test_source('includes/settings/modal.php');

	pg_assert_contains('pg_settings_grouped(', $modal);
	pg_assert_contains('class="pg-sm-group"', $modal);
	pg_assert_contains('id="pg_settings_modal_search"', $modal);
	pg_assert_contains("lang('Search settings')", $modal);

	$pane = settings_search_test_source('settings_pane.php');

	pg_assert_contains('pg_settings_search_build(', $pane);
}
