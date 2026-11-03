<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the review shown when a contact group is switched to rules: the
 * posted choice for its hand, form and product rows
 * (pg_audience_switch_action()), the count line
 * (pg_audience_switch_review_text()) and the choice drawn in the rule card.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_audiences_switch_action()
{
	pg_assert_same('keep', pg_audience_switch_action(array()), 'nothing posted keeps the rows');
	pg_assert_same('keep', pg_audience_switch_action(array('switch_rows' => 'keep')));
	pg_assert_same('remove', pg_audience_switch_action(array('switch_rows' => 'remove')));
	pg_assert_same('to_rule', pg_audience_switch_action(array('switch_rows' => 'to_rule')));
	pg_assert_same('keep', pg_audience_switch_action(array('switch_rows' => 'count')), 'a key of the review that is no action');
	pg_assert_same('keep', pg_audience_switch_action(array('switch_rows' => array('remove'))), 'an array is no choice');
}

function test_audiences_switch_review_text()
{
	pg_assert_same('1,240 by hand, 30 from forms, 12 from products', pg_audience_switch_review_text(array('hand' => 1240, 'form' => 30, 'product' => 12)));
	pg_assert_same('1 by hand, 12 from products', pg_audience_switch_review_text(array('hand' => 1, 'form' => 0, 'product' => 12)), 'a source with no rows is left out');
	pg_assert_same('5 from forms', pg_audience_switch_review_text(array('form' => 5)));
	pg_assert_same('', pg_audience_switch_review_text(array('hand' => 0, 'form' => 0, 'product' => 0)), 'no rows, no line');
}

function test_audiences_switch_review_in_the_card()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/contact_group_form.php');

	pg_assert_contains('name="switch_rows"', $source, 'the card offers the choice');
	pg_assert_contains("'keep' => lang('Keep them')", $source, 'keep is offered, and is the default (test_audiences_switch_action)');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_contact_group.php');

	pg_assert_contains('pg_audience_switch_apply(', $screen, 'the save runs the chosen action');
}
