<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the parts a poll's option carries (poll_parts.php): links,
 * description, picture, the recommended mark, the "Other" row and the
 * voter's words on it, how a plain poll still reads, and the API's answer.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/poll_parts.php');

// Links: http(s) only, a missing label is the address, at most three.
function test_ws_poll_links_clean()
{
	pg_assert_same(array(), ws_poll_links_clean(null)['links'], 'none');

	$clean = ws_poll_links_clean(array('https://example.com/a', array('url' => 'http://example.com/b', 'label' => '  Önizleme  '), array('url' => '')));
	pg_assert_true($clean['ok']);
	pg_assert_same(array(array('url' => 'https://example.com/a', 'label' => 'https://example.com/a'), array('url' => 'http://example.com/b', 'label' => 'Önizleme')), $clean['links']);

	pg_assert_false(ws_poll_links_clean(array('javascript:alert(1)'))['ok'], 'no script address');
	pg_assert_false(ws_poll_links_clean(array('ftp://example.com'))['ok'], 'http or https only');
	pg_assert_false(ws_poll_links_clean('https://example.com')['ok'], 'a list');
	pg_assert_false(ws_poll_links_clean(array('https://a.example', 'https://b.example', 'https://c.example', 'https://d.example'))['ok'], 'three at most');
}

// A text is a plain option; an object carries its parts; "Other" is named.
function test_ws_poll_option_part()
{
	$plain = ws_poll_option_part('  Elma   kırmızı ');
	pg_assert_same(array('id' => 0, 'label' => 'Elma kırmızı', 'description' => '', 'file_id' => 0, 'links' => array(), 'recommended' => false, 'other' => false), $plain['part']);
	pg_assert_false(ws_poll_part_has_extras($plain['part']), 'nothing beyond the label');

	$full = ws_poll_option_part(array('id' => '7', 'label' => 'Kapak', 'description' => str_repeat('a', 600), 'file_id' => '12', 'recommended' => 1, 'links' => array('https://example.com')));
	pg_assert_same(7, $full['part']['id']);
	pg_assert_same(500, mb_strlen($full['part']['description']), 'a description is cut to 500');
	pg_assert_same(12, $full['part']['file_id']);
	pg_assert_true($full['part']['recommended']);
	pg_assert_true(ws_poll_part_has_extras($full['part']));

	pg_assert_same(lang('Other'), ws_poll_option_part(array('other' => true))['part']['label'], 'the Other row is named');
	pg_assert_false(ws_poll_option_part(array('label' => 'x', 'links' => array('mailto:a@b.c')))['ok'], 'a bad link refuses the option');
}

// Two real options at least, one Other and one recommended at most.
function test_ws_poll_parts_list_rules()
{
	$listed = ws_poll_parts_list(array('Elma', 'elma', '', 'Armut', array('other' => true)));
	pg_assert_true($listed['ok']);
	pg_assert_same(array('Elma', 'Armut', lang('Other')), array_column($listed['parts'], 'label'), 'blank and repeated labels left out');

	pg_assert_false(ws_poll_parts_list(array('Elma', array('other' => true)))['ok'], 'Other does not count toward the two');
	pg_assert_false(ws_poll_parts_list(array('Elma', 'Armut', array('label' => 'Başka', 'other' => true), array('label' => 'Diğer yanıt', 'other' => true)))['ok'], 'one Other');
	pg_assert_false(ws_poll_parts_list(array(array('label' => 'Elma', 'recommended' => true), array('label' => 'Armut', 'recommended' => true)))['ok'], 'one recommended');
	pg_assert_same('options', ws_poll_parts_list(array('Elma'))['field']);
	pg_assert_false(ws_poll_parts_list(array_map('strval', range(1, 11)))['ok'], 'ten at most');
}

// ws_poll_input() keeps the labels a plain poll always had, and the parts.
function test_ws_poll_input_keeps_labels_and_parts()
{
	$checked = ws_poll_input(array('question' => 'Hangisi?', 'options' => array('Elma', array('label' => 'Armut', 'description' => 'Sulu'))));
	pg_assert_true($checked['ok']);
	pg_assert_same(array('Elma', 'Armut'), $checked['options']);
	pg_assert_same('Sulu', $checked['parts'][1]['description']);
}

// The voter's words: required on the Other row only.
function test_ws_poll_other_check()
{
	pg_assert_same('Kendi fikrim', ws_poll_other_text("  Kendi \n fikrim "));
	pg_assert_same('', ws_poll_other_text(array('x')), 'a list is no text');
	pg_assert_same(255, mb_strlen(ws_poll_other_text(str_repeat('b', 300))));

	pg_assert_true(ws_poll_other_check(array(3), 9, '')['ok'], 'another option needs no words');
	pg_assert_true(ws_poll_other_check(array(9), 0, '')['ok'], 'no Other row');
	pg_assert_true(ws_poll_other_check(array(9), 9, 'Benim')['ok']);

	$refused = ws_poll_other_check(array(3, 9), 9, '');
	pg_assert_false($refused['ok']);
	pg_assert_same('other_text', $refused['field']);
}

// A row made before the upgrade reads as a plain option.
function test_ws_poll_part_of_row_before_and_after()
{
	$before = ws_poll_part_of_row(array('id' => 4, 'label' => 'Elma'), false);
	pg_assert_false(ws_poll_part_has_extras($before), 'a poll made before draws as it did');

	$after = ws_poll_part_of_row(array('id' => 4, 'label' => 'Başka', 'description' => '', 'file_id' => 0, 'links' => '[{"url":"https://example.com","label":"x"}]', 'recommended' => 0, 'other' => 1));
	pg_assert_true($after['other']);
	pg_assert_same(array(array('url' => 'https://example.com', 'label' => 'x')), $after['links']);
	pg_assert_same(array(), ws_poll_part_of_row(array('id' => 4, 'label' => 'x', 'links' => null))['links'], 'NULL links');
}

// The API answers the parts in the declared order, empty for a plain option.
function test_ws_api_poll_option_parts_present()
{
	$option = ws_api_poll_option_present(array('id' => 4, 'label' => 'Elma', 'count' => 1, 'voters' => array(array('id' => 2, 'name' => 'Ayşe'))));
	pg_assert_same(array_keys(ws_api_poll_schema()['options'][0]), array_keys($option), 'declared fields in order');
	pg_assert_same('', $option['description']);
	pg_assert_same(null, $option['picture'], 'no picture');
	pg_assert_false($option['other']);
	pg_assert_same('', $option['voters'][0]['text']);

	$other = ws_api_poll_option_present(array('id' => 9, 'label' => 'Başka', 'count' => 1, 'other' => true, 'my_text' => 'Benim',
		'voters' => array(array('id' => 2, 'name' => 'Ayşe', 'text' => 'Benim')), 'picture' => array('file_id' => 5, 'name' => 'a.png', 'url' => '/a.png')));
	pg_assert_true($other['other']);
	pg_assert_same('Benim', $other['voters'][0]['text']);
	pg_assert_same(5, $other['picture']['file_id']);
}

// The create and the vote rehearse the new checks before a dry run stops.
function test_ws_api_polls_parts_checked_before_dry_run()
{
	$read = function ($function) {
		$reflection = new ReflectionFunction($function);
		$lines = file($reflection->getFileName());

		return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
	};

	$create = $read('ws_api_polls_create');
	pg_assert_true(strpos($create, 'ws_poll_parts_store_check(') < strpos($create, 'api_dry_run_stop('), 'the picture check');

	$vote = $read('ws_api_polls_vote');
	pg_assert_true(strpos($vote, 'ws_poll_other_check(') < strpos($vote, 'api_dry_run_stop('), 'the Other words');
	pg_assert_contains('ws_poll_parts_store_check(', $read('ws_poll_edit_check'), 'the change keeps the same rules');
}
