<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a player's address bound to a record field: src on audio, video
 * and source and poster on video printed by _render_tree_node()
 * (pg_media_bound_attrs(), includes/fn/designer.php); the value filled by
 * pg_sw_apply_tokens() under the bound address rule, an empty value printing
 * no address (pg_sw_drop_media_address()); pg_sw_has_content() judging a
 * player by its address (includes/fn/widgets.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _bound_media_node($tag, $bindings, $attrs = array(), $children = array())
{
	return array('type' => 'semantic', 'props' => array('tag' => $tag, '_bindings' => $bindings, '_attrs' => $attrs), 'children' => $children);
}

function _bound_media_fill($template, $value)
{
	if (!defined('DATE_FORMAT')) define('DATE_FORMAT', 'day_month');
	if (!defined('TIME_FORMAT')) define('TIME_FORMAT', 'twenty_four_hours');
	$custom = array('audio' => array('data' => $value, 'type' => 'text box', 'wysiwyg' => 0));
	return pg_sw_apply_tokens($template, array(), $custom);
}

function test_bound_media_audio_prints_bound_src()
{
	$html = _render_tree_node(_bound_media_node('audio', array('src' => 'audio'), array(
		array('name' => 'controls', 'value' => ''), array('name' => 'preload', 'value' => 'none'),
	)), 0);
	pg_assert_contains('<audio src="^^audio^^"', $html, 'the bound address is the src');
	pg_assert_contains('preload="none"', $html, 'the panel attributes stay');
	pg_assert_contains('data-pg-bind="src:audio"', $html);
}

function test_bound_media_binding_wins_over_typed_src()
{
	$html = _render_tree_node(_bound_media_node('audio', array('src' => 'audio'), array(array('name' => 'src', 'value' => '/old.mp3'))), 0);
	pg_assert_same(1, substr_count($html, ' src="'), 'one src');
	pg_assert_false(strpos($html, '/old.mp3') !== false, 'the typed address gives way');
}

function test_bound_media_video_poster_and_source()
{
	$html = _render_tree_node(_bound_media_node('video', array('poster' => 'cover'), array(), array(
		_bound_media_node('source', array('src' => 'clip')),
	)), 0);
	pg_assert_contains('<video poster="^^cover^^"', $html);
	pg_assert_contains('<source src="^^clip^^"', $html);
	pg_assert_contains('data-pg-bind="poster:cover"', $html);
}

function test_bound_media_unbound_player_unchanged()
{
	$html = _render_tree_node(array('type' => 'semantic', 'props' => array('tag' => 'audio', 'src' => '/x.mp3',
		'_attrs' => array(array('name' => 'src', 'value' => '/a.mp3'))), 'children' => array()), 0);
	pg_assert_contains('<audio src="/a.mp3"', $html, 'the Attributes panel src prints as before');
	pg_assert_false(strpos($html, '/x.mp3') !== false, 'a prop without a binding prints nothing');
	pg_assert_same(array(), pg_media_bound_attrs('div', array('_bindings' => array('src' => 'a'), 'src' => '^^a^^')), 'other tags take none');
	pg_assert_same(array(), pg_media_bound_attrs('audio', array('_bindings' => array('poster' => 'a'), 'poster' => '^^a^^')), 'no poster on audio');
}

function test_bound_media_address_rule()
{
	$tpl = '<audio src="^^audio^^" controls preload="none"></audio>';
	pg_assert_same('<audio src="#" controls preload="none"></audio>', _bound_media_fill($tpl, 'javascript:alert(1)'), 'javascript:');
	pg_assert_same('<video poster="#"></video>', _bound_media_fill('<video poster="^^audio^^"></video>', 'data:image/svg+xml,x'), 'data: poster');
	pg_assert_same('<audio src="https://cdn.example.com/e1.mp3" controls preload="none"></audio>', _bound_media_fill($tpl, 'https://cdn.example.com/e1.mp3'), 'a web address');
}

function test_bound_media_empty_value_prints_no_address()
{
	pg_assert_same('<audio controls preload="none"></audio>', _bound_media_fill('<audio src="^^audio^^" controls preload="none"></audio>', ''), 'audio');
	pg_assert_same('<video class="w-100"></video>', _bound_media_fill('<video class="w-100" poster="^^audio^^"></video>', ''), 'poster');
	pg_assert_same('<audio><source type="audio/mpeg"></audio>', _bound_media_fill('<audio><source src="^^audio^^" type="audio/mpeg"></audio>', ''), 'source');
	pg_assert_same('<img src="">', _bound_media_fill('<img src="^^audio^^">', ''), 'a picture keeps its empty src');
	pg_assert_same('<audio title="">', _bound_media_fill('<audio title="^^audio^^">', ''), 'another attribute stays');
	pg_assert_same('<a href="">x</a>', _bound_media_fill('<a href="^^audio^^">x</a>', ''), 'a link stays');
}

function test_bound_media_has_content_reads_the_address()
{
	pg_assert_false(pg_sw_has_content('<audio controls preload="none"></audio>'), 'a player without an address');
	pg_assert_false(pg_sw_has_content('<audio controls><p>Your browser cannot play this recording.</p></audio>'), 'fallback words do not count');
	pg_assert_true(pg_sw_has_content('<audio src="/e1.mp3" controls></audio>'), 'an address of its own');
	pg_assert_true(pg_sw_has_content('<video><source src="/c.mp4" type="video/mp4"></video>'), 'an address of a source');
	pg_assert_false(pg_sw_has_content('<video poster="/p.jpg"></video>'), 'a poster alone is no media');
	pg_assert_true(pg_sw_has_content('<audio></audio><p>Words</p>'), 'words beside it count');
	pg_assert_true(pg_sw_has_content('<iframe src="/v"></iframe>'), 'a frame still counts');
}

function test_bound_media_hide_if_empty_judges_the_player()
{
	$box = '<div class="pg-hide-if-empty"><p>Episode 12</p><audio class="pg-field-value" %s controls><p>No player.</p></audio></div>';
	pg_assert_same('', pg_sw_hide_empty_fields(sprintf($box, '')), 'a box whose player has no address goes');
	$full = sprintf($box, 'src="/e12.mp3"');
	pg_assert_same($full, pg_sw_hide_empty_fields($full), 'a box whose player has an address stays');
	pg_assert_same('<p>x</p>', pg_sw_hide_empty_fields('<p>x</p><audio class="pg-hide-if-empty" controls><p>No player.</p></audio>'), 'a marked player without an address goes');
	$own = '<audio class="pg-hide-if-empty" src="/a.mp3" controls></audio>';
	pg_assert_same($own, pg_sw_hide_empty_fields($own), 'a marked player with an address stays');
}
