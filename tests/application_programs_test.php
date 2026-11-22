<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the application programme settings that need no database
 * (includes/fn/application_programs.php): the period and what it refuses,
 * the settings read from the screen's post, what keeps a programme from
 * opening, the answers an entry is checked with and the reasons' words.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_application_programs_period_state_follows_status_and_dates()
{
	$program = array('status' => 1, 'opens_at' => 1000, 'closes_at' => 2000);

	pg_assert_same('before', pg_application_period_state($program, 999));
	pg_assert_same('open', pg_application_period_state($program, 1000));
	pg_assert_same('open', pg_application_period_state($program, 1999));
	pg_assert_same('closed', pg_application_period_state($program, 2000));

	// A draft works as a form with no programme; a closed one refuses
	// whatever its dates say; no dates means open while it is open.
	pg_assert_same('none', pg_application_period_state(array('status' => 0, 'opens_at' => 0, 'closes_at' => 0), 5));
	pg_assert_same('closed', pg_application_period_state(array('status' => 2, 'opens_at' => 0, 'closes_at' => 0), 5));
	pg_assert_same('open', pg_application_period_state(array('status' => 1, 'opens_at' => 0, 'closes_at' => 0), 5));
}

function test_application_programs_a_saved_draft_is_finished_until_the_close()
{
	pg_assert_false(pg_application_period_refuses('open', false));
	pg_assert_false(pg_application_period_refuses('none', false));
	pg_assert_true(pg_application_period_refuses('before', false));
	pg_assert_true(pg_application_period_refuses('closed', false));

	pg_assert_false(pg_application_period_refuses('open', true));
	pg_assert_false(pg_application_period_refuses('before', true));
	pg_assert_true(pg_application_period_refuses('closed', true));
}

function test_application_programs_rules_are_read_from_a_row_and_written_back()
{
	pg_assert_same(array('type' => 'file', 'field' => 'cv'), pg_application_rule_from_input('file', 'cv', 'ignored'));
	pg_assert_same(array('type' => 'range', 'field' => 'age', 'min' => 18.0, 'max' => 30.0), pg_application_rule_from_input('range', 'age', ' 18 - 30 '));
	pg_assert_same(array('type' => 'range', 'field' => 'age', 'min' => 18.0, 'max' => null), pg_application_rule_from_input('range', 'age', '18-'));
	pg_assert_same(array('type' => 'range', 'field' => 'age', 'min' => null, 'max' => 2.5), pg_application_rule_from_input('range', 'age', '-2,5'));
	pg_assert_same(array('type' => 'choice', 'field' => 'city', 'values' => array('Ankara', 'İzmir')), pg_application_rule_from_input('choice', 'city', 'Ankara, İzmir,,'));

	// Nothing usable: no field, an unknown type, a range with no number,
	// a list with no value.
	pg_assert_same(null, pg_application_rule_from_input('file', '', ''));
	pg_assert_same(null, pg_application_rule_from_input('other', 'cv', ''));
	pg_assert_same(false, pg_application_rule_from_input('range', 'age', 'old'));
	pg_assert_same(false, pg_application_rule_from_input('choice', 'city', ' , '));

	pg_assert_same('18-30', pg_application_rule_value_text(array('type' => 'range', 'field' => 'age', 'min' => 18.0, 'max' => 30.0)));
	pg_assert_same('18-', pg_application_rule_value_text(array('type' => 'range', 'field' => 'age', 'min' => 18, 'max' => null)));
	pg_assert_same('Ankara, İzmir', pg_application_rule_value_text(array('type' => 'choice', 'field' => 'city', 'values' => array('Ankara', 'İzmir'))));
	pg_assert_same('', pg_application_rule_value_text(array('type' => 'file', 'field' => 'cv')));
}

function test_application_programs_input_reads_the_screen_post()
{
	$fields = array('cv' => 'file upload', 'age' => 'text box', 'city' => 'pick list', 'name' => 'text box');
	$post = array(
		'name' => '  Young artists 2027 ',
		'opens' => '2027-01-10T09:00',
		'closes' => '2027-02-10T17:30',
		'outside_text' => 'Entries are closed.',
		'category_field' => 'city',
		'rule_field' => array('cv', 'age', 'nope', ''),
		'rule_type' => array('file', 'range', 'file', 'choice'),
		'rule_value' => array('', '18-30', '', ''),
		'rounds' => array(
			array('cid' => array('c1', '', 'c1'), 'cname' => array('Idea', 'Craft', 'Fit'), 'cweight' => array('50', '30', '20'), 'reviewers' => '3', 'ends' => '2027-03-01T00:00'),
			array('cid' => array(''), 'cname' => array(''), 'cweight' => array(''), 'reviewers' => '0', 'ends' => ''),
		),
		'blind' => '1',
		'blind_fields' => array('age', 'gone'),
		'waitlist_auto' => '1',
		'texts' => array('accepted' => array('subject' => 'Welcome', 'body' => 'You are in.'), 'rejected' => array('subject' => '', 'body' => ''), 'other' => array('subject' => 'x')),
	);

	$input = pg_application_program_input($post, $fields);

	pg_assert_same(array(), $input['errors']);
	$program = $input['program'];
	pg_assert_same('Young artists 2027', $program['name']);
	pg_assert_same(strtotime('2027-01-10 09:00'), $program['opens_at']);
	pg_assert_same(strtotime('2027-02-10 17:30'), $program['closes_at']);
	pg_assert_same('city', $program['category_field']);
	pg_assert_same(array(
		array('type' => 'file', 'field' => 'cv'),
		array('type' => 'range', 'field' => 'age', 'min' => 18.0, 'max' => 30.0),
	), $program['eligibility']);

	// Ids are kept, a missing or repeated one gets the next free one; a round
	// with no criterion is dropped; reviewers are held to 1 to 10.
	pg_assert_same(1, count($program['rounds']));
	pg_assert_same(array(
		array('id' => 'c1', 'name' => 'Idea', 'weight' => 50),
		array('id' => 'c2', 'name' => 'Craft', 'weight' => 30),
		array('id' => 'c3', 'name' => 'Fit', 'weight' => 20),
	), $program['rounds'][0]['criteria']);
	pg_assert_same(3, $program['rounds'][0]['reviewers']);
	pg_assert_same(strtotime('2027-03-01 00:00'), $program['rounds'][0]['ends_at']);
	pg_assert_same(3, $program['reviewers_per_entry']);

	pg_assert_same(1, $program['blind']);
	pg_assert_same(array('age'), $program['blind_fields']);
	pg_assert_same(1, $program['waitlist_auto']);
	pg_assert_same(0, pg_application_program_input(array('name' => 'x'), $fields)['program']['waitlist_auto']);
	pg_assert_same(array(
		'outside' => 'Entries are closed.',
		'accepted' => array('subject' => 'Welcome', 'body' => 'You are in.'),
		'rejected' => array('subject' => '', 'body' => ''),
		'waitlisted' => array('subject' => '', 'body' => ''),
	), $program['texts']);
}

function test_application_programs_input_refuses_what_cannot_be_saved()
{
	$input = pg_application_program_input(array(
		'name' => ' ',
		'opens' => '2027-02-10T00:00',
		'closes' => '2027-01-10T00:00',
		'rule_field' => array('age'),
		'rule_type' => array('range'),
		'rule_value' => array('young'),
		'rounds' => array(array('cid' => array(''), 'cname' => array('Idea'), 'cweight' => array('0'), 'reviewers' => '2', 'ends' => '')),
	), array('age' => 'text box'));

	pg_assert_same(4, count($input['errors']));
	pg_assert_contains('name', $input['errors'][0]);
	pg_assert_contains('closes', $input['errors'][1]);
	pg_assert_contains('18-30', $input['errors'][2]);
	pg_assert_contains('1 to 100', $input['errors'][3]);

	// A category field must be a choice field of the form.
	$input = pg_application_program_input(array('name' => 'A', 'category_field' => 'age'), array('age' => 'text box'));
	pg_assert_same('', $input['program']['category_field']);
}

function test_application_programs_missing_lists_what_keeps_it_from_opening()
{
	$ready = array(
		'closes_at' => 2000,
		'rounds' => array(array('criteria' => array(array('id' => 'c1', 'name' => 'Idea', 'weight' => 60), array('id' => 'c2', 'name' => 'Craft', 'weight' => 40)))),
	);

	pg_assert_same(array(), pg_application_program_missing($ready));
	pg_assert_same(array('period'), pg_application_program_missing(array('closes_at' => 0) + $ready));
	pg_assert_same(array('period', 'criteria'), pg_application_program_missing(array('closes_at' => 0, 'rounds' => array())));

	$uneven = $ready;
	$uneven['rounds'][0]['criteria'][1]['weight'] = 30;
	pg_assert_same(array('criteria'), pg_application_program_missing($uneven));
	pg_assert_same(90, pg_application_weights_sum($uneven['rounds'][0]['criteria']));
}

function test_application_programs_entry_answers_group_by_field_name()
{
	$answers = pg_application_entry_answers(array(
		array('name' => 'age', 'data' => '24', 'file_id' => 0),
		array('name' => 'topics', 'data' => 'Paint', 'file_id' => 0),
		array('name' => 'topics', 'data' => 'Clay', 'file_id' => 0),
		array('name' => 'cv', 'data' => '', 'file_id' => 77),
		array('name' => 'photo', 'data' => '', 'file_id' => 0),
	));

	pg_assert_same(array('age' => '24', 'topics' => array('Paint', 'Clay'), 'cv' => '77', 'photo' => ''), $answers);
}

function test_application_programs_reasons_are_told_in_plain_words()
{
	pg_assert_same('Age: no answer', pg_application_reason_text(array('code' => 'missing', 'field' => 'age'), 'Age'));
	pg_assert_same('Age: above the range', pg_application_reason_text(array('code' => 'above', 'field' => 'age'), 'Age'));
	pg_assert_same('age: not one of the choices allowed', pg_application_reason_text(array('code' => 'not_listed', 'field' => 'age'), ''));
}
