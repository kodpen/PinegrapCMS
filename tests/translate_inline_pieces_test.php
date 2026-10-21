<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the way stored HTML (a custom HTML block, a product description,
 * a confirmation message) is cut into the texts a translator gets and drawn
 * back: pg_tr_html_segments(), pg_tr_inline_pieces(),
 * pg_tr_inline_restore_attrs() and pg_tr_render_inline() in
 * includes/translate/content.php. An element a translated text cannot carry
 * (an image, a form control, a button) and the attributes the inline cleaner
 * takes off a tag (id, data-*) stay on the translated page.
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
require_once(PG_FUNCTIONS_DIR . '/includes/translate/content.php');

// A lookup standing in for the store: source text => translation, by format.
function translate_inline_pieces_lookup($value, $formats)
{
	$table = array(
		'text' => array(
			'KVKK metnini okudum' => 'I have read the privacy notice',
			'Bize yazın'          => 'Write to us',
			'Gönder'              => 'Send',
		),
		'inline' => array(
			'Ürünümüz <b>harika</b>.'                          => 'Our product is <b>great</b>.',
			'Ayrıntılar için <a href="#detay">tıklayın</a>.'   => 'For details <a href="#detay">click here</a>.',
		),
	);

	foreach ($formats as $format) {
		$normalized = pg_tr_normalize($value, $format);

		if (isset($table[$format][$normalized])) {
			return array($table[$format][$normalized], $format);
		}
	}

	return null;
}

// An image, a check box and a button are not part of the text: the texts
// around them are what the translator gets, the button's label one of its own.
function test_translate_inline_pieces_segments_leave_elements_out_of_the_text()
{
	$html = '<p><img src="/pinegrap/assets/images/logo.png" alt="Logo"> Ürünümüz <b>harika</b>.</p>'
		. '<form method="post"><label><input type="checkbox" name="kvkk" required> KVKK metnini okudum</label>'
		. '<p>Bize yazın <button type="submit">Gönder</button></p></form>';

	pg_assert_same(array(
		array('Ürünümüz <b>harika</b>.', 'inline'),
		array('KVKK metnini okudum', 'text'),
		array('Bize yazın', 'text'),
		array('Gönder', 'text'),
	), pg_tr_html_segments($html), 'segments');
}

// A block of words and inline tags alone is one text, as before.
function test_translate_inline_pieces_segments_keep_a_plain_block_whole()
{
	pg_assert_same(array(array('Ayrıntılar için <a href="#detay">tıklayın</a>.', 'inline')),
		pg_tr_html_segments('<p>Ayrıntılar için <a href="#detay" data-bs-toggle="collapse" id="lnk">tıklayın</a>.</p>'), 'inline tags kept');
	pg_assert_same(array(array('Kumaşı yumuşak.', 'text')),
		pg_tr_html_segments('<p><img src="/u.jpg" alt="Ürün"> Kumaşı yumuşak.</p>'), 'image at the start of a paragraph');
	pg_assert_same(array(array('Bir <b>iki</b> üç', 'inline')), pg_tr_html_segments('Bir <b>iki</b> üç'), 'no block at all');
}

// The cuts: elements outside the inline set, the whole of an element whose
// content is not words, and the inline tags of a stretch they leave unclosed.
function test_translate_inline_pieces_cuts()
{
	pg_assert_same(array(array('text', 'Bir <b>iki</b> üç')), pg_tr_inline_pieces('Bir <b>iki</b> üç'), 'nothing to cut');

	pg_assert_same(array(
		array('keep', '<img src="a.png">'),
		array('text', ' Yazı '),
		array('keep', '<input type="checkbox">'),
	), pg_tr_inline_pieces('<img src="a.png"> Yazı <input type="checkbox">'), 'void elements');

	pg_assert_same(array(
		array('text', 'Önce '),
		array('keep', '<select name="s"><option>Kırmızı</option></select>'),
		array('text', ' sonra'),
	), pg_tr_inline_pieces('Önce <select name="s"><option>Kırmızı</option></select> sonra'), 'an element whose content is not words');

	pg_assert_same(array(
		array('keep', '<b>'),
		array('text', 'Bize '),
		array('keep', '<button>'),
		array('text', 'Gönder'),
		array('keep', '</button>'),
		array('text', ' yazın'),
		array('keep', '</b>'),
	), pg_tr_inline_pieces('<b>Bize <button>Gönder</button> yazın</b>'), 'inline tags left open by a cut');

	pg_assert_same(array(
		array('text', 'Bir '),
		array('keep', '<!-- not -->'),
		array('text', ' iki'),
	), pg_tr_inline_pieces('Bir <!-- not --> iki'), 'a comment');
}

// The attributes the cleaner took off go back on the translation's tags, in
// order, and nothing else changes.
function test_translate_inline_pieces_restore_attrs()
{
	pg_assert_same('For details <a href="#detay" data-bs-toggle="collapse" id="lnk">click here</a>.',
		pg_tr_inline_restore_attrs('Ayrıntılar için <a href="#detay" data-bs-toggle="collapse" id="lnk">tıklayın</a>.', 'For details <a href="#detay">click here</a>.'), 'link attributes');

	pg_assert_same('<a href="/a" id="one">One</a> and <a href="/a" id="two">two</a>',
		pg_tr_inline_restore_attrs('<a href="/a" id="one">Bir</a> ve <a href="/a" id="two">iki</a>', '<a href="/a">One</a> and <a href="/a">two</a>'), 'the same tag twice, in order');

	pg_assert_same('Our product is <b>great</b>.',
		pg_tr_inline_restore_attrs('Ürünümüz <b>harika</b>.', 'Our product is <b>great</b>.'), 'nothing taken off');

	pg_assert_same('A<br data-x="1">B', pg_tr_inline_restore_attrs('a<br data-x="1">b', 'A<br>B'), 'a void tag');
}

// Drawn back: the words change, the elements and the attributes stay.
function test_translate_inline_pieces_render_keeps_elements()
{
	$lookup = 'translate_inline_pieces_lookup';

	pg_assert_same('<img src="/logo.png" alt="Logo"> Our product is <b>great</b>.',
		pg_tr_render_inline('<img src="/logo.png" alt="Logo"> Ürünümüz <b>harika</b>.', $lookup), 'image');

	pg_assert_same('<input type="checkbox" name="kvkk" required> I have read the privacy notice',
		pg_tr_render_inline('<input type="checkbox" name="kvkk" required> KVKK metnini okudum', $lookup), 'check box');

	pg_assert_same('Write to us <button type="submit">Send</button>',
		pg_tr_render_inline('Bize yazın <button type="submit">Gönder</button>', $lookup), 'button');

	pg_assert_same('For details <a href="#detay" data-bs-toggle="collapse" data-bs-target="#detay" id="lnk">click here</a>.',
		pg_tr_render_inline('Ayrıntılar için <a href="#detay" data-bs-toggle="collapse" data-bs-target="#detay" id="lnk">tıklayın</a>.', $lookup), 'link attributes');

	pg_assert_same(null, pg_tr_render_inline('<img src="/x.png"> Çevirisi yok', $lookup), 'nothing translated');
}
