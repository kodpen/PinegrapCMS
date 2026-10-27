<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the panel's Bootstrap 6 Avatar (includes/fn/contacts.php): the
 * tone a person's letters are drawn in, the theme class of a tone, the
 * letters and tone a person brief carries, and the markup: letters on a
 * theme colour, a grey square for a login without a name, a picture only
 * for a photo, never an <img> without an address, the presence dot.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/people.php');

function test_avatar_tone_follows_the_seed_and_stays_in_range()
{
	pg_assert_same(pg_avatar_tone('EG', '12'), pg_avatar_tone('XY', '12'), 'the seed, not the name, keeps the tone');
	pg_assert_same(abs(crc32('EG')) % 8, pg_avatar_tone('EG'), 'without a seed the letters pick it');

	for ($i = 0; $i < 50; $i++) {
		$tone = pg_avatar_tone('A', (string) $i);
		pg_assert_true(($tone >= 0) && ($tone <= 7), 'tone ' . $tone . ' is one of eight');
	}
}

function test_avatar_picture_of_letters_keeps_the_same_tone()
{
	$tones = array('#4f6bed', '#0f8a7e', '#c2410c', '#7c3aed', '#be185d', '#0369a1', '#15803d', '#a16207');
	$url = rawurldecode(pg_initials_avatar_url('EG', 'contact', '42'));

	pg_assert_contains("fill='" . $tones[pg_avatar_tone('EG', '42')] . "'", $url, 'e-mail and print draw the tone the panel draws');
}

function test_avatar_theme_class_has_eight_distinct_themes()
{
	$classes = array();

	for ($i = 0; $i < 8; $i++) {
		$classes[] = pg_avatar_theme_class($i);
	}

	pg_assert_same(8, count(array_unique($classes)), 'eight themes');
	pg_assert_same('theme-primary', pg_avatar_theme_class(0));
	pg_assert_same(pg_avatar_theme_class(1), pg_avatar_theme_class(9), 'wraps around');
	pg_assert_same(pg_avatar_theme_class(7), pg_avatar_theme_class(-1), 'a negative place wraps too');
}

function test_avatar_letters_default_to_question_mark()
{
	pg_assert_same(array('initials' => '?', 'tone' => pg_avatar_tone('', 'x')), pg_avatar_letters('', 'x'));
	pg_assert_same('EG', pg_avatar_letters('EG', 3)['initials']);
}

function test_avatar_person_brief_carries_letters_and_tone()
{
	$brief = ws_person_brief(array('id' => 7, 'role' => 2, 'username' => 'erdal', 'first_name' => 'Erdal', 'last_name' => 'Güral', 'online_timestamp' => 0));

	pg_assert_same('EG', $brief['initials']);
	pg_assert_same(pg_avatar_tone('EG', '7'), $brief['tone']);
	pg_assert_same('contact', $brief['avatar_kind']);
}

function test_avatar_markup_letters_on_a_theme()
{
	$html = pg_avatar_markup(array('initials' => 'EG', 'tone' => 3, 'avatar_kind' => 'contact', 'avatar' => pg_initials_avatar_url('EG'), 'name' => 'Erdal <G>'), 'sm');

	pg_assert_contains('class="avatar avatar-sm ' . pg_avatar_theme_class(3) . '"', $html);
	pg_assert_contains('>EG<', $html, 'the letters are text');
	pg_assert_contains('aria-label="Erdal &lt;G&gt;"', $html, 'the name is escaped');
	pg_assert_false(strpos($html, '<img') !== false, 'a picture of letters is not drawn as a picture');
}

function test_avatar_markup_login_without_a_name_is_a_grey_square()
{
	$html = pg_avatar_markup(array('initials' => 'ER', 'tone' => 2, 'avatar_kind' => 'user'));

	pg_assert_contains('avatar-subtle theme-secondary pg-avatar-user', $html);
	pg_assert_contains('aria-hidden="true"', $html, 'no name, no label');
}

function test_avatar_markup_photo_and_no_empty_src()
{
	$photo = pg_avatar_markup(array('initials' => 'EG', 'avatar' => '/files/me.jpg', 'avatar_kind' => 'photo'));
	pg_assert_contains('<img class="avatar-img" src="/files/me.jpg"', $photo);

	$app = pg_avatar_markup(array('initials' => 'AI', 'avatar' => '/assets/images/ws-ai.svg'));
	pg_assert_contains('src="/assets/images/ws-ai.svg"', $app, 'a drawn icon without a kind stays a picture');

	$none = pg_avatar_markup(array('avatar' => '', 'avatar_kind' => 'photo'));
	pg_assert_false(strpos($none, 'src=') !== false, 'no address, no <img>');
	pg_assert_contains('>?<', $none);
}

function test_avatar_markup_presence_dot()
{
	pg_assert_contains('<span class="avatar-status status-online"></span>', pg_avatar_markup(array('initials' => 'EG', 'presence' => 'online')));
	pg_assert_contains('status-away', pg_avatar_markup(array('initials' => 'EG', 'presence' => 'away')));
	pg_assert_false(strpos(pg_avatar_markup(array('initials' => 'EG', 'presence' => 'bogus"')), 'avatar-status') !== false, 'only the three states');
	pg_assert_false(strpos(pg_avatar_markup(array('initials' => 'EG', 'size' => 'huge'), 'huge'), 'avatar-huge') !== false, 'only the four sizes');
}

function test_avatar_javascript_twins_use_the_same_tones()
{
	$themes = array();

	for ($i = 0; $i < 8; $i++) {
		$themes[] = "'" . pg_avatar_theme_class($i) . "'";
	}

	foreach (array('workspace.js', 'chat_backend.src.js', 'workspace_guest.js') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/' . $file);

		pg_assert_true((bool) preg_match('/var AVATAR_THEMES = \[([^\]]*)\]/', $source, $match), $file . ' lists the tones');
		pg_assert_same(implode(', ', $themes), trim($match[1] ?? ''), $file . ': the same eight tones in the same order as pg_avatar_theme_class()');
		pg_assert_contains("indexOf('data:image/svg+xml') !== 0", $source, $file . ': a picture of letters is drawn as letters');
	}
	// The chat's served twin is kept by hand (core/important-files-and-js-twins.md).
	$min = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/chat_backend.min.js');
	pg_assert_contains('"avatar pg-chat-face"', $min, 'chat_backend.min.js draws the Avatar too');
	pg_assert_contains('["theme-primary","theme-info","theme-warning","theme-accent","theme-danger","theme-secondary","theme-success","theme-inverse"]', $min, 'with the same tones');
}

function test_avatar_markup_for_follows_the_name()
{
	pg_assert_contains(pg_avatar_theme_class(pg_avatar_tone('EG', '9')), pg_avatar_markup_for('Erdal', 'Güral', '', 9));
	pg_assert_contains('>EG<', pg_avatar_markup_for('Erdal', 'Güral', '', 9));
	pg_assert_contains('pg-avatar-user', pg_avatar_markup_for('', '', 'Kodpen Ltd', 9), 'no name: the grey square of the username');
	pg_assert_contains('pg-avatar-50', pg_avatar_markup_for('A', 'B', '', 1, 'pg-avatar-50'));
}
