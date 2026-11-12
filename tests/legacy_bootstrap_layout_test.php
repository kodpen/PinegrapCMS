<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Bootstrap 3 to 5.3.8 converter on a page type's markup
 * (includes/fn/legacy_bootstrap.php): the rules a class list takes where no
 * Legacy theme styles the markup.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_bootstrap.php');

function test_lbs_layout_rules_of_a_class_list()
{
	// Without a Legacy theme the names 5.3.8 owns are its own utilities.
	pg_assert_same('legacy-bg-primary text-end', pg_lbs_classes('bg-primary text-right')['class']);
	$r = pg_lbs_classes('bg-primary text-right overflow-hidden', array('theme_names' => false));
	pg_assert_same('bg-primary text-end overflow-hidden', $r['class']);
	pg_assert_same(array('text-right' => 1), $r['converted']);

	// A kept name stays as written and is counted; the rest is converted.
	$r = pg_lbs_classes('label pull-right hidden', array('keep' => array('label', 'hidden')));
	pg_assert_same('label float-end hidden', $r['class']);
	pg_assert_same(array('label' => 1, 'hidden' => 1), $r['kept']);
	pg_assert_same(array('pull-right' => 1), $r['converted']);
	// A kept visibility name is left out of the element's display set.
	pg_assert_same('hide d-none d-sm-block', pg_lbs_classes('hide hidden-xs', array('keep' => array('hide')))['class']);
	pg_assert_same('glyphicon bi bi-check-lg', pg_lbs_classes('glyphicon glyphicon-ok', array('keep' => array('glyphicon')))['class']);

	// A name written beside its 5.3.8 names: found by both, once.
	$r = pg_lbs_classes('form-group row', array('beside' => array('form-group')));
	pg_assert_same('mb-3 form-group row', $r['class']);
	pg_assert_same(array('form-group' => 1), $r['converted']);
	$again = pg_lbs_classes($r['class'], array('beside' => array('form-group')));
	pg_assert_same($r['class'], $again['class']);
	pg_assert_same(array(), $again['converted']);
	pg_assert_same('bi bi-check-lg glyphicon-ok glyphicon', pg_lbs_classes('glyphicon-ok glyphicon', array('beside' => array('glyphicon-ok', 'glyphicon')))['class']);
}
