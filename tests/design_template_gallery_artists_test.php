<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Art Gallery design template's artists and works
 * (includes/design_templates/pinegrap-gallery/artists.php): the pages and
 * widgets, the Artists and Works collections with a sample per record
 * whose values fit their fields, the work's artist and exhibition as
 * references named by title, the artist's page listing the artist's own
 * works, the filters reading what the medium bars and the record pages
 * send, the work's page opening the enquiry on its title, every page link
 * naming a template page, ids and classes that are the template's own, no
 * fixed dates and every literal in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template (_test_gl_page_keys()) and the
// node walker (_test_gl_nodes()) live in the parts test.
require_once __DIR__ . '/design_template_gallery_test.php';

// parts.php, cards.php, the public pages and artists.php in one scope, as the template file
// reads them. Read once per run.
function _test_gla_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gallery';
	include $dir . '/parts.php';
	include $dir . '/cards.php';
	$public = include $dir . '/pages_public.php';
	$part = include $dir . '/artists.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$home = null;
	foreach ($public['pages'] as $p) {
		if ($p['key'] === 'home') $home = $p['tree'];
	}
	$out = array('part' => $part, 'home' => $home, 'pages' => $pages, 'widgets' => $part['widgets'], 'artists' => $artists, 'works' => $works,
		'exhibitions' => $exhibitions, 'mediums' => $medium_titles, 'availability' => $availability_titles,
		'work_titles' => $work_titles, 'work_choices' => $work_choices, 'work_price' => $work_price);
	return $out;
}

// The name, options and form settings of every form control of a tree.
function _test_gla_controls($tree)
{
	$nodes = array();
	_test_gl_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		$tag = $n['props']['tag'] ?? '';
		if (!in_array($tag, array('input', 'select', 'textarea'), true)) continue;
		$name = '';
		foreach ((array)($n['props']['_attrs'] ?? array()) as $a) {
			if ($a['name'] === 'name') $name = $a['value'];
		}
		$options = array();
		foreach ((array)($n['children'] ?? array()) as $o) {
			foreach ((array)($o['props']['_attrs'] ?? array()) as $a) {
				if ($a['name'] === 'value' && $a['value'] !== '') $options[] = $a['value'];
			}
		}
		$out[$name] = array('tag' => $tag, 'options' => $options, 'cf' => $n['props']['_cf'] ?? array());
	}
	return $out;
}

// The query of each link of a tree that opens the given page with one.
function _test_gla_queries($tree, $page)
{
	$nodes = array();
	_test_gl_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, '{{page:' . $page . '}}?') !== 0) continue;
		parse_str(substr($href, strlen('{{page:' . $page . '}}?')), $q);
		$out[] = $q;
	}
	return $out;
}

// The page links (__page_link) of a tree: page key => address name => token.
function _test_gla_page_links($tree)
{
	$nodes = array();
	_test_gl_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		if (($n['props']['_bindings']['href'] ?? '') !== '__page_link') continue;
		$spec = $n['props']['_bindParams']['href'];
		pg_assert_true((bool)preg_match('/^\{\{tab:([a-z_]+)\}\}$/', $spec['page_id'], $m), 'a page link names a tab');
		foreach ($spec['params'] as $p) $out[$m[1]][$p['name']] = $p['token'];
	}
	return $out;
}

function test_gallery_artists_pages_and_widgets()
{
	$d = _test_gla_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('artists', 'artist', 'works', 'work', 'artist_new', 'work_new'), array_keys($d['pages']));
	pg_assert_same(array('artist_form', 'work_form', 'artists_list', 'home_artists', 'artist_works', 'works_list', 'artist_detail', 'work_detail'),
		array_keys($d['widgets']));
	$known = array_flip(_test_gl_page_keys());
	foreach ($d['pages'] as $key => $p) {
		pg_assert_true(isset($known[$key]), "$key is a planned page");
		pg_assert_same(substr($key, -4) === '_new' ? 'private' : 'public', $p['folder'], "$key: its folder");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
	}
	foreach (array('artist_new', 'work_new') as $key) pg_assert_true(!empty($d['pages'][$key]['noindex']), "$key is not indexed");
	foreach ($d['widgets'] as $key => $w) {
		if ($w['page'] === 'home') continue;
		pg_assert_true(isset($d['pages'][$w['page']]), "$key sits on a page of this part");
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
	pg_assert_same('home', $d['widgets']['home_artists']['page'], 'the home places the artists');
	pg_assert_contains('"templateWidget":"home_artists"', json_encode($d['home']), 'the home page places home_artists');
}

// The Artists form is a collection with a sample per artist whose values
// fill their fields; the medium is an answer of the list the bars use.
function test_gallery_artists_form_and_samples()
{
	$d = _test_gla_load();
	$form = $d['pages']['artist_new']['form'];
	pg_assert_same(1, $form['collection'], 'a collection');
	pg_assert_same(count($d['artists']), count($form['sample_records']), 'one sample per artist');
	$controls = _test_gla_controls($d['widgets']['artist_form']['tree']);
	$fields = array('name', 'medium', 'based', 'summary', 'statement', 'bio', 'photo', 'photo_1', 'photo_2', 'photo_3');
	pg_assert_same($fields, array_keys($controls), 'the fields of the form');
	foreach (array('photo', 'photo_1', 'photo_2', 'photo_3') as $f) pg_assert_same('image', $controls[$f]['cf']['kind'] ?? '', "$f is an image");
	pg_assert_same('title', $controls['name']['cf']['rss_field'] ?? '', 'the name is the title');
	pg_assert_same($d['mediums'], $controls['medium']['options'], 'the mediums');
	pg_assert_true(isset($controls[$form['share_image_field']]), 'the share image is a field');
	foreach ($form['sample_records'] as $n => $r) {
		pg_assert_same($fields, array_keys($r), "sample $n: every field");
		foreach ($r as $name => $value) pg_assert_true($value !== '', "sample $n: $name filled");
		pg_assert_true(in_array($r['medium'], $d['mediums'], true), "sample $n: a medium of the list");
	}
}

// The Works form: the artist and the exhibition are references into the
// other forms, each sample names them by title, availability is the
// gallery's own field and every pick list value is an answer.
function test_gallery_works_form_and_samples()
{
	$d = _test_gla_load();
	$form = $d['pages']['work_new']['form'];
	pg_assert_same(1, $form['collection'], 'a collection');
	pg_assert_same(count($d['works']), count($form['sample_records']), 'one sample per work');
	$controls = _test_gla_controls($d['widgets']['work_form']['tree']);
	$fields = array('title', 'artist', 'exhibition', 'medium', 'size', 'technique', 'price', 'availability', 'photo');
	pg_assert_same($fields, array_keys($controls), 'the fields of the form');
	pg_assert_same(array('kind' => 'reference', 'ref' => '{{tab:artist_new}}', 'rss_field' => 'category'), $controls['artist']['cf'], 'the artist is a reference');
	pg_assert_same(array('kind' => 'reference', 'ref' => '{{tab:exhibition_new}}'), $controls['exhibition']['cf'], 'the exhibition is a reference');
	pg_assert_same('image', $controls['photo']['cf']['kind'] ?? '', 'the photograph is an image');
	pg_assert_true(!empty($controls['availability']['cf']['office_use_only']), 'availability is the gallery\'s own');
	pg_assert_same($d['availability'], $controls['availability']['options'], 'the three states');
	pg_assert_same($d['work_titles'], array_slice($d['work_choices'], 0, count($d['work_titles'])), 'the enquiry offers the samples\' titles');
	$artist_names = array();
	foreach ($d['artists'] as $a) $artist_names[] = $a['name'];
	$exhibition_titles = array();
	foreach ($d['exhibitions'] as $x) $exhibition_titles[] = $x['title'];
	$by_artist = array();
	foreach ($form['sample_records'] as $n => $r) {
		foreach ($fields as $f) {
			if ($f === 'exhibition' && !isset($r[$f])) continue;
			pg_assert_true(isset($r[$f]) && $r[$f] !== '', "sample $n: $f filled");
		}
		pg_assert_same(array(), array_diff(array_keys($r), $fields), "sample $n: no other field");
		pg_assert_true(in_array($r['artist'], $artist_names, true), "sample $n: the artist by name");
		if (isset($r['exhibition'])) pg_assert_true(in_array($r['exhibition'], $exhibition_titles, true), "sample $n: the exhibition by title");
		pg_assert_true(in_array($r['medium'], $d['mediums'], true), "sample $n: a medium of the list");
		pg_assert_true(in_array($r['availability'], $d['availability'], true), "sample $n: a state of the list");
		$by_artist[$r['artist']] = true;
	}
	pg_assert_same(count($d['artists']), count($by_artist), 'every artist has a work');
}

// The artist's page lists the works of the artist it shows; the works
// page takes ?artist= and ?medium=, the artists page ?medium=, and every
// medium bar link sends an answer of the Medium lists.
function test_gallery_artists_filters()
{
	$d = _test_gla_load();
	$f = $d['widgets']['artist_works']['config']['filters'];
	pg_assert_same(array(array('field' => 'artist', 'operator' => 'is equal to', 'value' => '', 'dynamic_value' => 'shown record', 'dynamic_value_attribute' => '')), $f,
		'the artist\'s own works');
	$params = function ($key) use ($d) {
		$out = array();
		foreach ($d['widgets'][$key]['config']['filters'] as $row) {
			if ($row['dynamic_value'] === 'url parameter') $out[$row['dynamic_value_attribute']] = $row['field'];
		}
		return $out;
	};
	pg_assert_same(array('medium' => 'medium'), $params('artists_list'), 'artists by medium');
	pg_assert_same(array('artist' => 'artist', 'medium' => 'medium'), $params('works_list'), 'works by artist and medium');
	pg_assert_same(array(), $d['widgets']['home_artists']['config']['filters'] ?? array(), 'the home shows every artist');
	foreach (array('artists', 'works') as $page) {
		$found = array();
		foreach (_test_gla_queries($d['pages'][$page]['tree'], $page) as $q) {
			pg_assert_same(array('medium'), array_keys($q), "$page: a medium link");
			pg_assert_true(in_array($q['medium'], $d['mediums'], true), "$page: '{$q['medium']}' is an answer");
			$found[$q['medium']] = true;
		}
		pg_assert_same(count($d['mediums']), count($found), "$page: a link per medium");
	}
	foreach (array('artists_list', 'home_artists') as $key) pg_assert_same('name', $d['widgets'][$key]['config']['order_by_field'], "$key: by name");
}

// The record pages send what the lists and the enquiry read: the artist's
// code to the works, the work's title to the enquiry, the work's artist
// (the code its field holds) to the works; the work's artist prints by
// name through the reference.
function test_gallery_artists_record_pages()
{
	$d = _test_gla_load();
	pg_assert_same(array('works' => array('artist' => 'reference_code')), _test_gla_page_links($d['widgets']['artist_detail']['tree']), 'the artist\'s works');
	pg_assert_same(array('enquire' => array('artwork' => 'title'), 'works' => array('artist' => 'artist')),
		_test_gla_page_links($d['widgets']['work_detail']['tree']), 'the enquiry and the artist\'s other works');
	$json = json_encode($d['widgets']['work_detail']['tree']);
	pg_assert_contains('"show":"name"', $json, 'the artist by name');
	pg_assert_contains('"page_id":"{{tab:artist_new}}"', $json, 'through the Artists form');
	foreach (array('artist_detail', 'work_detail') as $key) {
		$c = $d['widgets'][$key]['config'];
		pg_assert_same('form_item_view', $c['regionType'], "$key: a record view");
		pg_assert_same('public', $c['access_control'], "$key: open to anyone");
		$tree = json_encode($d['widgets'][$key]['tree']);
		pg_assert_contains('record_not_found', $tree, "$key: a not found state");
		pg_assert_contains('can_edit', $tree, "$key: the Edit button");
	}
	foreach (array('statement', 'bio', 'medium', 'based', 'name') as $t) {
		pg_assert_contains('"text":"' . $t . '"', json_encode($d['widgets']['artist_detail']['tree']), "the artist's page reads $t");
	}
	foreach (array('title', 'technique', 'size', 'price', 'availability') as $t) {
		pg_assert_contains('"text":"' . $t . '"', $json, "the work's page reads $t");
	}
	// The detail pages are lists' record pages.
	foreach (array('artists_list' => 'artist', 'home_artists' => 'artist', 'artist_works' => 'work', 'works_list' => 'work') as $key => $page) {
		pg_assert_same('{{tab:' . $page . '}}', $d['widgets'][$key]['config']['detail_page_id'], "$key opens $page");
	}
}

function test_gallery_artists_links_classes_and_dates()
{
	$d = _test_gla_load();
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p['tree'];
	foreach ($d['widgets'] as $w) $trees[] = $w['tree'];
	$json = json_encode($trees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	$known = array_flip(_test_gl_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json . json_encode($d['widgets']), $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "link {{page:$key}} names a template page");
	foreach (_test_gl_shop_keys() as $key) pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no link to $key");
	$nodes = array();
	foreach ($trees as $t) _test_gl_nodes($t, $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(prx|pmz|pph|pvt|pre|pev|pth)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
			pg_assert_false(strpos($c, 'ratio-') === 0 && !in_array($c, array('ratio-1x1', 'ratio-4x3', 'ratio-16x9', 'ratio-21x9'), true), "a Bootstrap ratio ($c)");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'gl-') === 0, "id $id is the template's");
	}
	$samples = json_encode(array($d['pages']['artist_new']['form'], $d['pages']['work_new']['form']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	foreach (array($json, $samples) as $text) {
		pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $text), 'no year in the sample');
		pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $text), 'no month in the sample');
	}
}

// Every literal has its Turkish; an artist is "sanatçı", a work "eser".
function test_gallery_artists_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gallery/artists.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	pg_assert_true(count($m[1]) > 50, 'literals found');
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		// An address (artist, new-artist) is written without the Turkish letters.
		if (preg_match('/\bartists?\b/i', $key) && !preg_match('/^[a-z0-9-]+$/', $key)) pg_assert_true(mb_stripos($map[$key], 'sanatçı', 0, 'UTF-8') !== false, "'$key' says sanatçı");
	}
}
