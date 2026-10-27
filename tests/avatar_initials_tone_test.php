<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the letters and colour a person without a picture is drawn with
 * in the panel (includes/fn/contacts.php, includes/workspace/people.php): the
 * initials of one-word, multi-word, Turkish and e-mail-only names, the tone
 * index a person keeps whatever page draws them, and what a people brief
 * hands the screens.
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

function test_avatar_initials_tone_letters_of_names()
{
	pg_assert_same('AY', pg_initials('Ayşe', 'Yılmaz'), 'first and last name');
	pg_assert_same('ÇŞ', pg_initials('çağrı', 'şahin'), 'Turkish first letters are upper-cased');
	pg_assert_same('Ö', pg_initials('', 'Öztürk'), 'a last name alone');
	pg_assert_same('M', pg_initials('Mehmet', ''), 'one word');
	pg_assert_same('AK', pg_initials('Ali Rıza Kaya', ''), 'many words: the first and the last');
	pg_assert_same('İK', pg_initials('İsmail', 'Kurt'), 'a dotted capital stays');
	pg_assert_same('ED', pg_initials('', '', 'erdal.dogan@example.com'), 'an e-mail-only login: the first two parts');
	pg_assert_same('JO', pg_initials('', '', 'jo@'), 'a one-part login: its first two letters');
	pg_assert_same('?', pg_initials('', '', ''), 'nothing to read');
}

function test_avatar_initials_tone_index_is_stable_and_in_range()
{
	for ($i = 1; $i <= 50; $i++) {
		$tone = pg_avatar_tone($i);

		pg_assert_true(is_int($tone) && ($tone >= 0) && ($tone < 8), 'seed ' . $i . ' gives one of eight tones');
		pg_assert_same($tone, pg_avatar_tone((string) $i), 'seed ' . $i . ' as a number or a string');
	}

	pg_assert_same(pg_avatar_tone('guest7'), pg_avatar_tone('guest7'), 'the same seed, the same tone');
	pg_assert_same(-1, pg_avatar_tone(''), 'no seed: no tone, the neutral avatar');
}

function test_avatar_initials_tone_matches_the_picture_of_letters()
{
	// The data: picture e-mail, PDF and print keep must show the same colour
	// the panel's avatar shows for that person.
	$tones = array('#4f6bed', '#0f8a7e', '#c2410c', '#7c3aed', '#be185d', '#0369a1', '#15803d', '#a16207');

	foreach (array('12', 'guest3', 'app9') as $seed) {
		pg_assert_contains(rawurlencode($tones[pg_avatar_tone($seed)]), pg_initials_avatar_url('AB', 'contact', $seed), 'seed ' . $seed);
	}
}

function test_avatar_initials_tone_people_brief()
{
	$named = ws_person_brief(array('id' => 42, 'role' => 2, 'username' => 'ayse', 'first_name' => 'Ayşe', 'last_name' => 'Çelik', 'image' => '', 'image_file_id' => 0, 'image_file_name' => ''));

	pg_assert_same('AÇ', $named['initials'], 'a named person\'s letters');
	pg_assert_same(pg_avatar_tone(42), $named['tone'], 'their tone comes from their id');
	pg_assert_same('contact', $named['avatar_kind'], 'drawn as letters');

	$login = ws_person_brief(array('id' => 43, 'role' => 3, 'username' => 'info@example.com', 'first_name' => '', 'last_name' => '', 'image' => ''));

	pg_assert_same('IE', $login['initials'], 'a login without a name: letters of the username');
	pg_assert_same(-1, $login['tone'], 'and the neutral avatar, so the missing name shows');

	$photo = ws_person_brief(array('id' => 44, 'role' => 2, 'username' => 'm', 'first_name' => 'Mert', 'last_name' => 'Ak', 'image' => 'https://example.com/m.png'));

	pg_assert_same('photo', $photo['avatar_kind'], 'a picture is drawn');
	pg_assert_same('MA', $photo['initials'], 'with letters behind it should it fail to load');
}

function test_avatar_initials_tone_theme_classes_match_the_screen()
{
	pg_assert_same('theme-primary', pg_avatar_theme(0), 'tone 0');
	pg_assert_same('theme-inverse', pg_avatar_theme(7), 'tone 7');
	pg_assert_same('', pg_avatar_theme(-1), 'the neutral avatar has no theme');
	pg_assert_same('', pg_avatar_theme(8), 'past the list');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	pg_assert_true((bool) preg_match('/var AVATAR_TONES = \[([^\]]*)\]/', $js, $match), 'workspace.js names its tones');

	$names = array_map(function ($name) { return 'theme-' . trim($name, " '"); }, explode(',', $match[1] ?? ''));

	for ($tone = 0; $tone < 8; $tone++) {
		pg_assert_same(pg_avatar_theme($tone), $names[$tone] ?? '', 'tone ' . $tone . ' is the same theme in PHP and JavaScript');
	}
}

function test_avatar_initials_tone_panel_markup()
{
	$letters = ws_avatar_html(array('avatar' => 'data:image/svg+xml,x', 'avatar_kind' => 'contact', 'initials' => 'ÇŞ', 'tone' => 3));

	pg_assert_same('<span class="avatar ws-avatar theme-accent" aria-hidden="true">ÇŞ</span>', $letters, 'letters in their tone, not the picture of letters');

	$photo = ws_avatar_html(array('avatar' => '/files/a"b.png', 'avatar_kind' => 'photo', 'initials' => 'AB', 'tone' => 1), 'x');

	pg_assert_contains('<img class="avatar-img" src="/files/a&quot;b.png"', $photo, 'a picture, escaped');
	pg_assert_contains('class="avatar ws-avatar x"', $photo, 'with the classes asked for');

	$login = ws_avatar_html(array('avatar' => 'data:x', 'avatar_kind' => 'user', 'initials' => 'IE', 'tone' => -1));

	pg_assert_same('<span class="avatar ws-avatar ws-avatar-square" aria-hidden="true">IE</span>', $login, 'a login without a name: neutral and square');

	$nothing = ws_avatar_html(array('id' => 9, 'name' => 'x', 'avatar' => ''));

	pg_assert_same('<span class="avatar ws-avatar" aria-hidden="true">?</span>', $nothing, 'no picture and no letters: never an empty src');
}
