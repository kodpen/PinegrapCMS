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

// Runs the dialog's matcher (fold(), wordStarts(), score(), rankHits() of
// backend.src.js) over rows under Node; the titles of the hits in order, or
// null when Node is missing (skipped).
function settings_search_test_rank($rows, $query)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return null;
	}

	$js = settings_search_test_source('assets/js/backend.src.js');
	$code_js = '';

	foreach (array('fold', 'wordStarts', 'score', 'rankHits') as $name) {
		$start = strpos($js, "\n    function " . $name . '(');
		$end = ($start === false) ? false : strpos($js, "\n    }\n", $start);
		if (!pg_assert_true(($start !== false) && ($end !== false), 'backend.src.js has ' . $name . '()')) {
			return null;
		}
		$code_js .= substr($js, $start, $end - $start + 6) . "\n";
	}

	$code_js .= 'var q = fold(' . json_encode($query) . ').replace(/\s+/g, " ").trim();' . "\n"
		. 'console.log(JSON.stringify(rankHits(' . json_encode($rows) . ', q).map(function (h) { return h.row.title; })));';

	$file = tempnam(sys_get_temp_dir(), 'pgsss');
	file_put_contents($file, $code_js);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));
	$res = json_decode(implode("\n", $out), true);

	return is_array($res) ? $res : null;
}

function settings_search_test_row($title, $keys, $fields = array())
{
	return array('pane' => 'p', 'card' => 'c', 'title' => $title, 'place' => 'Place', 'keys' => $keys, 'fields' => $fields);
}

// A card listed earlier whose keyword is only the query's first word no
// longer comes before the card whose field label or keyword is the query.
function test_settings_search_full_field_name_ranks_its_own_card_first()
{
	$rows = array(
		settings_search_test_row('Tarih ve Saat', array('zaman', 'saat dilimi')),
		settings_search_test_row('Yazılım', array('dil', 'sürüm')),
		settings_search_test_row('Kuruluş', array('logo', 'kuruluş')),
		settings_search_test_row('İlgili ekranlar', array('çalışma alanı')),
		settings_search_test_row('Kampanyalar', array('alt bilgi')),
		settings_search_test_row('Google, Microsoft ve Apple ile Giriş', array('google', 'oauth')),
		settings_search_test_row('İmza Zaman Damgası', array('zaman damgası', 'tsa')),
		settings_search_test_row('Site Dilleri', array('diller'), array('Dil Ekle')),
		settings_search_test_row('Özellik Seçenekleri', array('özellik'), array('Çalışma Alanını Etkinleştir')),
		settings_search_test_row('Özel Marka Kimliği', array('private label'), array('Logo URL', 'Alt Bilgi Bağlantısı 1 Etiketi')),
		settings_search_test_row('Google Yorumları', array('google reviews', 'places api')),
	);

	$cases = array(
		'zaman damgası sunucu'          => 'İmza Zaman Damgası',
		'dil ekle'                      => 'Site Dilleri',
		'çalışma alanını etkinleştir'   => 'Özellik Seçenekleri',
		'logo url'                      => 'Özel Marka Kimliği',
		'alt bilgi bağlantısı 1 etiketi' => 'Özel Marka Kimliği',
		'google reviews'                => 'Google Yorumları',
		'ZAMAN DAMGASI SUNUCU'          => 'İmza Zaman Damgası',
	);

	foreach ($cases as $query => $first) {
		$titles = settings_search_test_rank($rows, $query);
		if ($titles === null) {
			return;
		}
		pg_assert_same($first, isset($titles[0]) ? $titles[0] : null, $query . ' finds its own card first');
	}

	// The short keyword still finds its card, after the better hit.
	$titles = settings_search_test_rank($rows, 'logo url');
	pg_assert_true(in_array('Kuruluş', $titles, true), 'logo url still lists the card keyed "logo"');
}

// The order between the ranks that were right stays: a name, then a keyword
// the query starts, then a field label; one rank's exact text comes first.
function test_settings_search_name_still_beats_keyword_and_field()
{
	$rows = array(
		settings_search_test_row('Uyarılar Paneli', array(), array('Önbellek')),
		settings_search_test_row('Hız', array('önbellek ayarları')),
		settings_search_test_row('Önbellek', array()),
	);

	pg_assert_same(array('Önbellek', 'Hız', 'Uyarılar Paneli'), settings_search_test_rank($rows, 'önbellek'), 'name, keyword, field');

	$rows = array(
		settings_search_test_row('A', array('smtp sunucusu')),
		settings_search_test_row('B', array('smtp')),
	);

	pg_assert_same(array('B', 'A'), settings_search_test_rank($rows, 'smtp'), 'an exact keyword before a longer one');
}

function settings_search_test_tool_groups()
{
	return array(
		array('label' => 'Monitoring', 'icon' => 'bi-activity', 'items' => array(
			array('label' => 'Site Log', 'icon' => 'bi-card-list', 'url' => 'view_log.php'),
		)),
		array('label' => 'Empty', 'icon' => 'bi-x', 'items' => array()),
		array('label' => 'Jobs', 'icon' => 'bi-tools', 'items' => array(
			array('label' => 'System Informations', 'icon' => 'bi-info-circle', 'url' => 'si.php', 'target' => '_blank'),
			array('label' => 'Clean Up', 'icon' => 'bi-eraser', 'url' => 'clean_up.php'),
			array('label' => 'Purge Cache', 'icon' => 'bi-trash3', 'url' => 'purge_cache.php?token=x', 'confirm' => 'Sure?'),
		)),
	);
}

// The System tools row and every tool the reader may open are in the index,
// each with the screen it opens.
function test_settings_search_index_lists_the_system_tools()
{
	$rows = pg_settings_search_tool_rows(settings_search_test_tool_groups(), '/pg/');

	pg_assert_same(5, count($rows), 'the screen and four tools');
	pg_assert_same('System tools', $rows[0]['title']);
	pg_assert_same('/pg/system_tools.php', $rows[0]['url']);
	pg_assert_true(in_array('sistem araçları', $rows[0]['keys'], true), 'the screen keeps its Turkish word');

	pg_assert_same('Site Log', $rows[1]['title']);
	pg_assert_same('/pg/view_log.php', $rows[1]['url']);
	pg_assert_same('System tools › Monitoring', $rows[1]['place']);
	pg_assert_same('', $rows[1]['pane'], 'a tool opens no pane');

	pg_assert_same('_blank', $rows[2]['target'], 'System Informations keeps its new tab');
	pg_assert_same('/pg/system_tools.php', $rows[4]['url'], 'a tool that asks first opens the screen');

	pg_assert_true(in_array('temizle', $rows[3]['keys'], true), 'Clean Up keeps temizle');
	pg_assert_true(!in_array('tem', $rows[3]['keys'], true), 'the palette shorthand tem is left out');
	pg_assert_true(!in_array('sis', $rows[0]['keys'], true), 'the palette shorthand sis is left out');

	pg_assert_same(array(), pg_settings_search_tool_rows(array(array('label' => 'X', 'icon' => 'bi-x', 'items' => array())), '/pg/'), 'no tool, no row');
}

function test_settings_search_build_appends_the_tools_and_hits_link_out()
{
	$search = settings_search_test_source('includes/settings/search.php');

	pg_assert_contains('pg_settings_search_tool_rows(pg_settings_tool_groups($user)', $search);

	$js = settings_search_test_source('assets/js/backend.src.js');

	pg_assert_contains('if (hit.row.url) {', $js);
	pg_assert_contains("\$(el).on('click', 'button.pg-sm-hit'", $js);

	$panel = settings_search_test_source('includes/panel/search.php');

	pg_assert_contains('pg_settings_tool_keys()', $panel);
	pg_assert_true(strpos($panel, 'function pg_panel_search_tool_keys(') === false, 'one list of tool words, in the registry');
}

// Every card's Turkish keyword list keeps its English terms: an operator
// types "private label" or "audit" whatever the panel language.
function test_settings_search_turkish_keywords_keep_the_english_terms()
{
	foreach (settings_search_test_keyword_sources() as $section => $sources) {
		$english = settings_search_test_terms($sources, 'en');
		$turkish = settings_search_test_terms($sources, 'tr');

		pg_assert_same(array(), array_values(array_diff($english, $turkish)), $section . ' keeps its English terms in Turkish');
	}

	$terms = settings_search_test_terms(settings_search_test_keyword_sources()['pgset-private-label'], 'tr');

	foreach (array('private label', 'white label', 'özel etiket') as $query) {
		pg_assert_true(settings_search_test_palette_hits($query, $terms), $query . ' finds Private Label Branding in Turkish');
	}
}
