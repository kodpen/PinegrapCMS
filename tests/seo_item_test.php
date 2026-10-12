<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_sw_item_text() in includes/fn/seo.php: the plain text a
 * product or product group page takes its <title> and meta description from
 * when the item has no SEO title or description of its own.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Tags and entities go, words that tags kept apart stay apart, white space folds.
function test_seo_item_text_flattens_html()
{
	pg_assert_same('Tek Taş Yüzük', pg_sw_item_text('<p>Tek <strong>Taş</strong>&nbsp;Yüzük</p>'), 'inline markup and nbsp');
	pg_assert_same('Ring Gold & silver', pg_sw_item_text("<p>Ring</p><p>Gold &amp; silver</p>"), 'paragraphs');
	pg_assert_same('One two', pg_sw_item_text("One<br>two"), 'line break');
	pg_assert_same('Text', pg_sw_item_text("<style>p{}</style><script>x()</script>Text"), 'script and style dropped');
	pg_assert_same('', pg_sw_item_text(''), 'empty');
	pg_assert_same('', pg_sw_item_text(null), 'null');
}

// Without a limit the whole text comes back.
function test_seo_item_text_keeps_short_text()
{
	pg_assert_same('Polarized lenses.', pg_sw_item_text('Polarized lenses.', 160), 'under the limit');
}

// Over the limit it is cut at a word, the trailing punctuation dropped, and
// an ellipsis added; the result never exceeds the limit.
function test_seo_item_text_cuts_at_a_word()
{
	$text = str_repeat('word ', 50);
	$out = pg_sw_item_text($text, 30);

	pg_assert_true(mb_strlen($out, 'UTF-8') <= 30, 'within the limit');
	pg_assert_same("word word word word word word\u{2026}", $out, 'cut at a word');

	$out = pg_sw_item_text('Çok uzun bir açıklama, gerçekten çok uzun.', 20);
	pg_assert_same("Çok uzun bir\u{2026}", $out, 'multibyte, comma dropped');
}

// One long word is cut where it has to be.
function test_seo_item_text_cuts_a_long_word()
{
	pg_assert_same("abcdefghi\u{2026}", pg_sw_item_text(str_repeat('abcdefghij', 3), 10), 'no space to cut at');
}
