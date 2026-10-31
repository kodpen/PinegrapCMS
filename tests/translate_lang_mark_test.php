<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the source-language mark on a node drawn untranslated:
 * pg_tr_render_lang_mark() in includes/translate/render.php sets it from the
 * per-field states pg_tr_render_text() reports, and pg_tr_lang_attr_props()
 * in includes/fn/translate.php turns it into a lang attribute.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/translate/render.php');

// A text to translate with no translation is drawn in the source language,
// and its node says so.
function test_translate_lang_mark_marks_an_untranslated_text()
{
	$props = pg_tr_render_lang_mark(array('text' => 'Rehber'), array('text' => 'missing'), 'en', 'tr');

	pg_assert_same('tr', $props['_tr_lang']);
}

// A translated text, a value that is not a text to translate and a page in
// the source language get nothing.
function test_translate_lang_mark_leaves_the_rest_alone()
{
	pg_assert_same(array('text' => 'Guide'), pg_tr_render_lang_mark(array('text' => 'Guide'), array('text' => 'translated'), 'en', 'tr'), 'translated');
	pg_assert_same(array('text' => 'KT-1'), pg_tr_render_lang_mark(array('text' => 'KT-1'), array('text' => 'skipped'), 'en', 'tr'), 'not a text');
	pg_assert_same(array('text' => 'Rehber'), pg_tr_render_lang_mark(array('text' => 'Rehber'), array('text' => 'missing'), 'tr', 'tr'), 'source page');
	pg_assert_same(array('text' => 'Rehber'), pg_tr_render_lang_mark(array('text' => 'Rehber'), array('text' => 'missing'), '', 'tr'), 'no language');
}

// Only the node's own content texts count: an attribute text, a condition
// or a link caption does not mark it, and a translated content text keeps
// the mark off.
function test_translate_lang_mark_follows_content_texts_only()
{
	$states = array('alt' => 'missing', '_attrs:title' => 'missing', '_cond:show_if' => 'missing', '_labelDetail' => 'missing');
	pg_assert_false(isset(pg_tr_render_lang_mark(array(), $states, 'en', 'tr')['_tr_lang']), 'attribute texts');

	$card = pg_tr_render_lang_mark(array(), array('title' => 'translated', 'text' => 'missing'), 'en', 'tr');
	pg_assert_false(isset($card['_tr_lang']), 'one translated content text');

	$card = pg_tr_render_lang_mark(array(), array('title' => 'missing', 'text' => 'skipped'), 'en', 'tr');
	pg_assert_same('tr', $card['_tr_lang'], 'missing beside a non-text');
}

// The mark becomes a lang attribute and leaves the props.
function test_translate_lang_mark_becomes_an_attribute()
{
	$props = pg_tr_lang_attr_props(array('text' => 'Rehber', '_tr_lang' => 'tr', '_attrs' => array(array('name' => 'role', 'value' => 'note'))));

	pg_assert_false(isset($props['_tr_lang']));
	pg_assert_same(array(array('name' => 'role', 'value' => 'note'), array('name' => 'lang', 'value' => 'tr')), $props['_attrs']);
	pg_assert_same(array('text' => 'x'), pg_tr_lang_attr_props(array('text' => 'x')), 'no mark');
}

// A lang set on the node in the designer wins.
function test_translate_lang_mark_user_lang_wins()
{
	$props = pg_tr_lang_attr_props(array('_tr_lang' => 'tr', '_attrs' => array(array('name' => 'LANG', 'value' => 'de'))));

	pg_assert_same(array(array('name' => 'LANG', 'value' => 'de')), $props['_attrs']);
	pg_assert_false(isset($props['_tr_lang']));
}

// The drawing rules changed, so the drawn bodies are drawn again once.
function test_translate_lang_mark_rules_changed()
{
	pg_assert_same('2026.11.0-2', pg_tr_render_rules());
}
