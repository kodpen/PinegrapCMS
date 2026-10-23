<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the worded attributes of a translated page (alt, title,
 * placeholder, aria-label, aria-description and the tooltip and popover
 * texts): every way a page carries one - a designer node's attributes, a
 * link inside a paragraph's inline text, a custom HTML block, a shared
 * component or a list widget's row template, a product description - gives
 * the value to extraction as a text of its own, and the finished-page pass
 * (pg_tr_render_attrs()) looks up exactly the text extraction stored.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/translate/store.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/extract.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/content.php');

// A lookup standing in for the store: plain source text => translation.
function translate_attribute_texts_lookup($value, $formats)
{
	$table = array(
		'Web tasarım hakkında detaylı bilgi edin.' => 'Learn more about web design.',
		'Ayrıntılar'                               => 'Details',
		'Ekip fotoğrafı'                           => 'Team photo',
		'Adınız'                                   => 'Your name',
		'Mesajınız'                                => 'Your message',
		'Açılır bilgi'                             => 'Popup info',
		'Kısa açıklama'                            => 'Short description',
		'Fiyat & stok'                             => 'Price & stock',
	);

	if (!in_array('text', $formats, true)) {
		return null;
	}

	$normalized = pg_tr_normalize($value, 'text');

	return isset($table[$normalized]) ? array($table[$normalized], 'text') : null;
}

// The texts of segments, as array(text, format, field).
function translate_attribute_texts_rows($segments)
{
	$rows = array();

	foreach ($segments as $segment) {
		$rows[] = array($segment['text'], $segment['format'], $segment['field']);
	}

	return $rows;
}

// One list of attribute names feeds extraction and drawing.
function test_translate_attribute_texts_one_list()
{
	pg_assert_same(array('alt', 'title', 'placeholder', 'aria-label', 'aria-description', 'data-bs-title', 'data-bs-content'), pg_tr_worded_attrs(), 'the list');

	foreach (pg_tr_worded_attrs() as $name) {
		$fields = pg_tr_node_fields('semantic', array('tag' => 'div', '_attrs' => array(array('name' => $name, 'value' => 'Kısa açıklama'))));

		pg_assert_same(array(array('_attrs:' . $name, 'text')), $fields, 'node attribute ' . $name);
		pg_assert_same(array(array('Kısa açıklama', 'text', $name)), pg_tr_html_attr_texts('<span ' . $name . '="Kısa açıklama">x</span>'), 'html attribute ' . $name);
	}
}

// The service card of the report: a paragraph's inline text holds a link
// whose aria-label is a sentence. The inline text keeps it inside its own
// markup, so it is a text of its own too, read by the finished-page pass.
function test_translate_attribute_texts_link_inside_inline_text()
{
	$node = array('type' => 'content', 'props' => array(
		'contentType' => 'paragraph',
		'text'        => 'Hızlı ve güvenli <a class="stretched-link" href="/web-tasarim" aria-label="Web tasarım hakkında detaylı bilgi edin.">web çözümleri</a> sunuyoruz.',
	));

	$rows = array();

	foreach (pg_tr_node_segments($node) as $segment) {
		$rows[] = array($segment['text'], $segment['format'], $segment['field']);
	}

	pg_assert_same(array(
		array('Hızlı ve güvenli <a class="stretched-link" href="/web-tasarim" aria-label="Web tasarım hakkında detaylı bilgi edin.">web çözümleri</a> sunuyoruz.', 'inline', 'text'),
		array('Web tasarım hakkında detaylı bilgi edin.', 'text', 'text@aria-label#0'),
	), $rows, 'paragraph and its link label');

	// An icon link has no words of its own, only its label.
	$icon = array('type' => 'content', 'props' => array('contentType' => 'link', 'text' => '<a href="/iletisim" aria-label="Ayrıntılar"><i class="bi bi-info"></i></a>'));

	pg_assert_same(array(array('Ayrıntılar', 'text', 'text@aria-label#0')), translate_attribute_texts_rows(pg_tr_node_segments($icon)), 'icon link');
}

// A designer node's attributes, every worded one.
function test_translate_attribute_texts_node_attrs()
{
	$node = array('type' => 'semantic', 'props' => array('tag' => 'a', 'text' => 'Ayrıntılar', '_attrs' => array(
		array('name' => 'href', 'value' => '/x'),
		array('name' => 'aria-label', 'value' => 'Web tasarım hakkında detaylı bilgi edin.'),
		array('name' => 'aria-description', 'value' => 'Kısa açıklama'),
		array('name' => 'data-bs-content', 'value' => 'Açılır bilgi'),
	)));

	pg_assert_same(array(
		array('Ayrıntılar', 'text', 'text'),
		array('Web tasarım hakkında detaylı bilgi edin.', 'text', '_attrs:aria-label'),
		array('Kısa açıklama', 'text', '_attrs:aria-description'),
		array('Açılır bilgi', 'text', '_attrs:data-bs-content'),
	), translate_attribute_texts_rows(pg_tr_node_segments($node)), 'node');
}

// A custom HTML block: the words and the attributes, the code left alone.
function test_translate_attribute_texts_custom_html_block()
{
	$html = '<p>Bilgi için <a href="/x" aria-label="Web tasarım hakkında detaylı bilgi edin." title=\'Ayrıntılar\'>tıklayın</a>.</p>'
		. '<img src="/ekip.jpg" alt="Ekip fotoğrafı">'
		. '<form><input type="text" placeholder="Adınız"><textarea placeholder="Mesajınız" title="Değil &lt;b&gt;">Yazın</textarea></form>'
		. '<span translate="no" title="Kodpen Yazılım">Kodpen</span>'
		. '<!-- <img alt="Yorumdaki resim"> -->'
		. '<script>var s = \'<img alt="Betikteki resim">\';</script>';

	pg_assert_same(array(
		array('Bilgi için <a href="/x" aria-label="Web tasarım hakkında detaylı bilgi edin." title="Ayrıntılar">tıklayın</a>.', 'inline', 'html'),
		array('Web tasarım hakkında detaylı bilgi edin.', 'text', 'html@aria-label#0'),
		array('Ayrıntılar', 'text', 'html@title#1'),
		array('Ekip fotoğrafı', 'text', 'html@alt#2'),
		array('Adınız', 'text', 'html@placeholder#3'),
		array('Mesajınız', 'text', 'html@placeholder#4'),
		array('Değil <b>', 'text', 'html@title#5'),
	), translate_attribute_texts_rows(pg_tr_node_segments(array('type' => 'content', 'props' => array('contentType' => 'custom_html', 'html' => $html)))), 'custom html block');

	// An image alone: no words, its alt all the same.
	pg_assert_same(array(array('Ekip fotoğrafı', 'text', 'html@alt#0')),
		translate_attribute_texts_rows(pg_tr_node_segments(array('type' => 'content', 'props' => array('contentType' => 'custom_html', 'html' => '<img src="/e.jpg" alt="Ekip fotoğrafı">')))), 'image alone');
}

// A shared component or system widget: its tree walked whole, the row
// template of a list widget included.
function test_translate_attribute_texts_shared_tree_and_row_template()
{
	$tree = array('type' => 'root', 'children' => array(
		array('type' => 'loop_area', 'props' => array(), 'children' => array(
			array('type' => 'semantic', 'props' => array('tag' => 'a', 'text' => '^^name^^', '_bindings' => array('text' => 'name'), '_attrs' => array(
				array('name' => 'title', 'value' => 'Ayrıntılar'),
			))),
			array('type' => 'content', '_id' => 'n1', 'props' => array('contentType' => 'custom_html', 'html' => '<img src="/i.png" alt="Ekip fotoğrafı">')),
		)),
	));

	$segments = array();
	$refs = array('shared' => array(), 'menu' => array());
	$position = 0;

	pg_tr_walk_tree($tree, $segments, $refs, $position);

	pg_assert_same(array(
		array('Ayrıntılar', 'text', '_attrs:title'),
		array('Ekip fotoğrafı', 'text', 'html@alt#0'),
	), translate_attribute_texts_rows($segments), 'shared tree');
}

// A product description (or any stored field): its attributes too.
function test_translate_attribute_texts_stored_field()
{
	$position = 0;
	$html = '<p>Kumaşı yumuşak.</p><p><img src="/u.jpg" alt="Ekip fotoğrafı"></p>';

	pg_assert_same(array(
		array('Kumaşı yumuşak.', 'text', 'description'),
		array('Ekip fotoğrafı', 'text', 'description@alt#0'),
	), translate_attribute_texts_rows(pg_tr_field_segments('description', $html, $position)), 'description');
}

// Drawn: every worded attribute with a translation is swapped, whatever its
// quotes; a tag marked translate="no", comments and script content are not.
function test_translate_attribute_texts_render()
{
	$lookup = 'translate_attribute_texts_lookup';

	pg_assert_same('<a class="stretched-link" href="/web-tasarim" aria-label="Learn more about web design.">web solutions</a>',
		pg_tr_render_attrs('<a class="stretched-link" href="/web-tasarim" aria-label="Web tasarım hakkında detaylı bilgi edin.">web solutions</a>', $lookup), 'aria-label');

	pg_assert_same('<img src="/e.jpg" alt="Team photo" title="Details">',
		pg_tr_render_attrs('<img src="/e.jpg" alt="Ekip fotoğrafı" title=\'Ayrıntılar\'>', $lookup), 'alt and a single-quoted title');

	pg_assert_same('<button aria-description="Short description" data-bs-content="Popup info" data-bs-title="Details">x</button>',
		pg_tr_render_attrs('<button aria-description="Kısa açıklama" data-bs-content="Açılır bilgi" data-bs-title="Ayrıntılar">x</button>', $lookup), 'description and popover');

	pg_assert_same('<textarea placeholder="Your message">Mesajınız</textarea>',
		pg_tr_render_attrs('<textarea placeholder="Mesajınız">Mesajınız</textarea>', $lookup), 'text area: its tag, not its content');

	pg_assert_same('<span title="Price &amp; stock">x</span>',
		pg_tr_render_attrs('<span title="Fiyat &amp; stok">x</span>', $lookup), 'entities');

	$kept = '<span translate="no" title="Ayrıntılar">x</span><!-- <img alt="Ekip fotoğrafı"> --><script>var a = \'<img alt="Ekip fotoğrafı">\';</script><span data-title="Ayrıntılar">y</span>';

	pg_assert_same($kept, pg_tr_render_attrs($kept, $lookup), 'left alone');
}

// What the page looks up is what extraction stored: the same texts, so the
// same hashes.
function test_translate_attribute_texts_symmetry()
{
	$html = '<p>Bilgi <a href="/x" aria-label="Web  tasarım hakkında detaylı bilgi edin." title="Fiyat &amp; stok">için</a></p>'
		. '<img alt=\'Ekip fotoğrafı\'><input placeholder="Adınız"><div aria-description="Kısa açıklama" data-bs-content="Açılır bilgi"></div>';

	$stored = array();

	foreach (pg_tr_html_segments($html) as $part) {
		if ($part[1] === 'text') {
			$stored[] = pg_tr_hash($part[0], 'text');
		}
	}

	$looked_up = array();

	pg_tr_render_attrs($html, function ($value, $formats) use (&$looked_up) {
		$looked_up[] = pg_tr_hash(pg_tr_normalize($value, 'text'), 'text');

		return null;
	});

	pg_assert_same($stored, $looked_up, 'hashes');
	pg_assert_same(6, count($looked_up), 'count');
}

// A submit, button or reset input shows its value as its label: in stored
// HTML that value is a text too, under the same rule as a node's attributes.
// Any other input's value is what the form posts, and a <button>'s label is
// its content, so neither value is a text.
function test_translate_attribute_texts_button_input_values()
{
	$html = '<form><input type="submit" value="Gönder"><input type=\'reset\' value="Temizle"><input type=button value=\'Vazgeç\'>'
		. '<input type="text" name="ad" value="Ahmet Yılmaz"><input value="Kaydet"><input type="hidden" name="islem" value="Kayıt formu">'
		. '<button type="submit" name="islem" value="Siparişi sil">Sil</button></form>';

	pg_assert_same(array(
		array('Sil', 'text'),
		array('Gönder', 'text', 'value'),
		array('Temizle', 'text', 'value'),
		array('Vazgeç', 'text', 'value'),
	), pg_tr_html_segments($html), 'segments');

	$position = 0;

	pg_assert_same(array(
		array('Sil', 'text', 'html'),
		array('Gönder', 'text', 'html@value#0'),
		array('Temizle', 'text', 'html@value#1'),
		array('Vazgeç', 'text', 'html@value#2'),
	), translate_attribute_texts_rows(pg_tr_field_segments('html', $html, $position)), 'field names');

	// The node path keeps its rule: a button-type input's value is a field.
	pg_assert_same(array(array('_attrs:value', 'text')),
		pg_tr_node_fields('semantic', array('tag' => 'input', '_attrs' => array(array('name' => 'type', 'value' => 'submit'), array('name' => 'value', 'value' => 'Gönder')))), 'node input');
	pg_assert_same(array(),
		pg_tr_node_fields('semantic', array('tag' => 'input', '_attrs' => array(array('name' => 'type', 'value' => 'text'), array('name' => 'value', 'value' => 'Ahmet Yılmaz')))), 'node text input');

	// A repeated type: the browser keeps the first, so both paths do.
	pg_assert_same(array(), pg_tr_html_attr_texts('<input type="hidden" type="submit" name="islem" value="Kayıt formu">'), 'first type hidden');
	pg_assert_same(array(array('Gönder', 'text', 'value')), pg_tr_html_attr_texts('<input type="submit" type="text" value="Gönder">'), 'first type submit');
	pg_assert_same(array(), pg_tr_html_attr_texts('<input type type="submit" value="Gönder">'), 'first type without a value');
	pg_assert_same(array(array('Gönder', 'text', 'value')), pg_tr_html_attr_texts('<input type="&#115;ubmit" value="Gönder">'), 'type with an entity');
	pg_assert_same(array(),
		pg_tr_node_fields('semantic', array('tag' => 'input', '_attrs' => array(array('name' => 'type', 'value' => 'hidden'), array('name' => 'type', 'value' => 'submit'), array('name' => 'value', 'value' => 'Kayıt formu')))), 'node first type hidden');
	pg_assert_same(array(array('_attrs:value', 'text')),
		pg_tr_node_fields('semantic', array('tag' => 'input', '_attrs' => array(array('name' => 'type', 'value' => 'submit'), array('name' => 'type', 'value' => 'text'), array('name' => 'value', 'value' => 'Gönder')))), 'node first type submit');
}

// Drawn: a button-type input's label is swapped; a posted value is not.
function test_translate_attribute_texts_button_input_values_render()
{
	$lookup = function ($value, $formats) {
		$table = array('Gönder' => 'Send', 'Temizle' => 'Clear', 'Ahmet Yılmaz' => 'X', 'Siparişi sil' => 'X', 'Kaydet' => 'X');
		$normalized = pg_tr_normalize($value, 'text');

		return isset($table[$normalized]) ? array($table[$normalized], 'text') : null;
	};

	pg_assert_same('<input type="submit" class="btn" value="Send"><input type=\'reset\' value="Clear">',
		pg_tr_render_attrs('<input type="submit" class="btn" value="Gönder"><input type=\'reset\' value=\'Temizle\'>', $lookup), 'labels');

	$kept = '<input type="text" value="Ahmet Yılmaz"><input value="Kaydet"><button type="submit" value="Siparişi sil">Sil</button><input type="submit" translate="no" value="Gönder">';

	pg_assert_same($kept, pg_tr_render_attrs($kept, $lookup), 'posted values and translate="no"');

	// What the page looks up is what extraction stored.
	$html = '<input type="submit" value="Gönder"><input type="RESET" value="Temizle"><input type="text" value="Ahmet Yılmaz">';
	$stored = array();
	$looked_up = array();

	foreach (pg_tr_html_attr_texts($html) as $attr) {
		$stored[] = pg_tr_hash($attr[0], 'text');
	}

	pg_tr_render_attrs($html, function ($value, $formats) use (&$looked_up) {
		$looked_up[] = pg_tr_hash(pg_tr_normalize($value, 'text'), 'text');

		return null;
	});

	pg_assert_same($stored, $looked_up, 'hashes');
	pg_assert_same(2, count($stored), 'count');
}

// A <button>'s value is posted data and its label is its content: neither a
// node's attributes nor stored HTML give that value as a text. One rule
// decides it for both paths.
function test_translate_attribute_texts_button_value_is_posted_data()
{
	pg_assert_true(pg_tr_input_value_is_label('input', 'submit'), 'submit input');
	pg_assert_true(pg_tr_input_value_is_label('INPUT', ' Reset '), 'reset input, any case');
	pg_assert_false(pg_tr_input_value_is_label('input', 'text'), 'text input');
	pg_assert_false(pg_tr_input_value_is_label('button', 'submit'), 'button element');
	pg_assert_false(pg_tr_input_value_is_label('button', ''), 'button element without a type');

	$button = array('type' => 'semantic', 'props' => array('tag' => 'button', 'text' => 'Sil', '_attrs' => array(
		array('name' => 'type', 'value' => 'submit'),
		array('name' => 'name', 'value' => 'islem'),
		array('name' => 'value', 'value' => 'Siparişi sil'),
	)));

	pg_assert_same(array(array('Sil', 'text', 'text')), translate_attribute_texts_rows(pg_tr_node_segments($button)), 'node button: its text, not its value');

	$input = array('type' => 'semantic', 'props' => array('tag' => 'input', '_attrs' => array(
		array('name' => 'type', 'value' => 'submit'),
		array('name' => 'value', 'value' => 'Gönder'),
	)));

	pg_assert_same(array(array('Gönder', 'text', '_attrs:value')), translate_attribute_texts_rows(pg_tr_node_segments($input)), 'node submit input: its value');
}

// An element marked translate="no" or notranslate keeps its whole subtree
// out of extraction, as the word walk does: translate is inherited.
function test_translate_attribute_texts_marked_subtree_not_extracted()
{
	pg_assert_same(array(),
		pg_tr_html_segments('<div translate="no"><p>Kodpen</p><img alt="Kodpen Yazılım logosu"></div>'), 'translate="no" wrapper');

	pg_assert_same(array(),
		pg_tr_html_segments('<div class="x notranslate"><p><a href="/" title="Kodpen Yazılım">Kodpen</a></p></div>'), 'notranslate wrapper');

	pg_assert_same(array(array('Ayrıntılar', 'text', 'title')),
		pg_tr_html_attr_texts('<div class="notranslate-x"><a title="Ayrıntılar">x</a></div>'), 'notranslate only as a whole class name');

	$node = array('type' => 'content', 'props' => array(
		'contentType' => 'paragraph',
		'text'        => 'Ürünlerimiz <span class="notranslate"><a href="/" title="Kodpen Yazılım">Kodpen</a></span> markasıyla <a href="/x" title="Ayrıntılar">satılır</a>.',
	));

	$fields = array();

	foreach (pg_tr_node_texts('content', $node['props']) as $text) {
		$fields[] = array($text[0], $text[1]);
	}

	pg_assert_same(array(
		array('text', $node['props']['text']),
		array('text@title#0', 'Ayrıntılar'),
	), $fields, 'notranslate span inside inline text');
}

// Drawn the same way: the marked subtree as it is, the HTML after it drawn,
// a nested element of the same name counted, an unclosed one leaving the
// rest alone.
function test_translate_attribute_texts_marked_subtree_not_drawn()
{
	$lookup = 'translate_attribute_texts_lookup';

	pg_assert_same('<div translate="no"><p>Ekip</p><img alt="Ekip fotoğrafı"></div><img alt="Team photo">',
		pg_tr_render_attrs('<div translate="no"><p>Ekip</p><img alt="Ekip fotoğrafı"></div><img alt="Ekip fotoğrafı">', $lookup), 'wrapper, then a sibling');

	pg_assert_same('<div translate="no"><div></div><img alt="Ekip fotoğrafı"></div><img alt="Team photo">',
		pg_tr_render_attrs('<div translate="no"><div></div><img alt="Ekip fotoğrafı"></div><img alt="Ekip fotoğrafı">', $lookup), 'nested same-name element');

	pg_assert_same('<p>Bir <span class="a notranslate"><a title="Ayrıntılar">x</a></span> <a title="Details">y</a></p>',
		pg_tr_render_attrs('<p>Bir <span class="a notranslate"><a title="Ayrıntılar">x</a></span> <a title="Ayrıntılar">y</a></p>', $lookup), 'inline notranslate span');

	pg_assert_same('<img translate="no" alt="Ekip fotoğrafı"><img alt="Team photo">',
		pg_tr_render_attrs('<img translate="no" alt="Ekip fotoğrafı"><img alt="Ekip fotoğrafı">', $lookup), 'a marked void element covers only itself');

	pg_assert_same('<div translate="no"><pre><div></pre><img alt="Ekip fotoğrafı"></div><img alt="Team photo">',
		pg_tr_render_attrs('<div translate="no"><pre><div></pre><img alt="Ekip fotoğrafı"></div><img alt="Ekip fotoğrafı">', $lookup), 'a tag inside protected content is not counted');

	$unclosed = '<section translate="no"><img alt="Ekip fotoğrafı"><img alt="Ekip fotoğrafı">';

	pg_assert_same($unclosed, pg_tr_render_attrs($unclosed, $lookup), 'never closed: the rest alone');
}

// Rich text with no words (an image-only description) still has its
// attributes drawn: extraction stored the alt.
function test_translate_attribute_texts_wordless_rich_text_drawn()
{
	$position = 0;
	$html = '<p><img src="/b.jpg" alt="Ekip fotoğrafı"></p>';

	pg_assert_same(array(array('Ekip fotoğrafı', 'text', 'description@alt#0')),
		translate_attribute_texts_rows(pg_tr_field_segments('description', $html, $position)), 'stored');

	pg_assert_same('<p><img src="/b.jpg" alt="Team photo"></p>',
		pg_tr_render_html_value($html, 'translate_attribute_texts_lookup'), 'drawn');
}

// A variant's inline value in the data script: the worded attributes its
// tags carry are drawn, since the finished-page pass leaves scripts alone.
function test_translate_attribute_texts_variant_inline_value()
{
	$lookup = 'translate_attribute_texts_lookup';
	// Encoded the way the page prints the variant data.
	$data = function ($description) {
		return '<script>window.pgCivVariants = ' . json_encode(array('1' => array('description' => $description, 'sku' => 'A-1')), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . ';</script>';
	};

	pg_assert_same($data('Yumuşak <a href="#" title="Details">pamuk</a> kumaş'),
		pg_tr_render_json_strings($data('Yumuşak <a href="#" title="Ayrıntılar">pamuk</a> kumaş'), $lookup), 'inline value');

	$plain = '<script>window.pgCivVariants = {"1":{"description":"Yumuşak kumaş"}};</script>';

	pg_assert_same($plain, pg_tr_render_json_strings($plain, $lookup), 'a value without a translation is left as it is');
}
