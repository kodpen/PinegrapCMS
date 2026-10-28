<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_design_ai_known_class() (includes/designer_ai.php): a class
 * the shipped Bootstrap 5 vocabulary holds is known, a misspelt or made-up
 * one is not; state, site (pg-*) and Bootstrap Icons classes stay known, a
 * custom design knows none, and without the vocabulary file the class-family
 * check decides.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/designer_ai.php');

function test_designer_ai_known_class_real_bootstrap_classes_are_known()
{
	foreach (array('d-flex', 'col-md-6', 'btn-outline-primary', 'text-body-secondary', 'container', 'row', 'h1', 'icon-link') as $class) {
		pg_assert_true(pg_design_ai_known_class($class, 'bootstrap5'), $class . ' is a Bootstrap class');
	}
}

function test_designer_ai_known_class_state_site_and_icon_classes_are_known()
{
	foreach (array('active', 'show', 'collapsed', 'is-valid', 'pg-anything', 'pg-hero-title', 'bi', 'bi-house', 'bi-arrow-right-circle') as $class) {
		pg_assert_true(pg_design_ai_known_class($class, 'bootstrap5'), $class . ' is known');
	}
}

function test_designer_ai_known_class_invented_and_misspelt_classes_are_unknown()
{
	foreach (array('text-neon', 'card-glow', 'btn-primry', 'd-flx', 'neon-glow', 'navbar-glass', 'col-md-13', 'pg-', 'bi-') as $class) {
		pg_assert_false(pg_design_ai_known_class($class, 'bootstrap5'), $class . ' is not a Bootstrap class');
	}
}

function test_designer_ai_known_class_custom_design_knows_none()
{
	foreach (array('d-flex', 'active', 'pg-anything', 'bi-house') as $class) {
		pg_assert_false(pg_design_ai_known_class($class, 'custom'), $class . ' on a custom design');
	}
}

function test_designer_ai_known_class_without_vocabulary_falls_back_to_families()
{
	$missing = PG_FUNCTIONS_DIR . '/assets/js/designer_vocab/missing.json';

	pg_assert_same(null, pg_design_ai_vocab_classes($missing));

	// The class-family check: a real class and a made-up one of a known
	// family pass, a name of no family does not.
	pg_assert_true(pg_design_ai_known_class('d-flex', 'bootstrap5', $missing));
	pg_assert_true(pg_design_ai_known_class('text-neon', 'bootstrap5', $missing));
	pg_assert_true(pg_design_ai_known_class('pg-anything', 'bootstrap5', $missing));
	pg_assert_false(pg_design_ai_known_class('neon-glow', 'bootstrap5', $missing));
	pg_assert_false(pg_design_ai_known_class('d-flex', 'custom', $missing));
}

function test_designer_ai_known_class_reads_the_shipped_vocabulary()
{
	$classes = pg_design_ai_vocab_classes();

	pg_assert_true(is_array($classes) && isset($classes['btn-outline-primary']), 'the shipped vocabulary is read');
	pg_assert_true(!isset($classes['text-neon']));
}
