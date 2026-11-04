<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the document template screen (erp_settings.php): the placeholder
 * names are shown in full, the activity log lines are literal keys the
 * language check can see, and the template actions sit in the screen toolbar.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_settings_templates_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_erp_settings_placeholder_names_are_not_cut()
{
	$source = erp_settings_templates_test_source('erp_settings.php');

	pg_assert_true(strpos($source, '<dt class="col-12 font-monospace fw-normal text-break">{{') !== false, 'the name takes the full width and wraps');
	pg_assert_true(strpos($source, 'text-truncate">{{') === false, 'no ellipsis on a name');
}

function test_erp_settings_log_lines_are_literal_keys()
{
	$cards = erp_settings_templates_test_source('includes/erp/settings_cards.php');
	$screen = erp_settings_templates_test_source('erp_settings.php');

	foreach (array('erp invoice template', 'erp delivery note template', 'erp reconciliation letter template') as $base) {
		pg_assert_true(strpos($cards, "'log_changed' => lang('" . $base . " was changed'),") !== false, $base . ' changed');
		pg_assert_true(strpos($cards, "'log_reset' => lang('" . $base . " was reset'),") !== false, $base . ' reset');
	}

	pg_assert_true(strpos($screen, "\$document['log'] . '") === false, 'no key built at run time');
	pg_assert_true((bool) preg_match('/if \(isset\(\$_POST\[\'reset\'\]\)\) \{\s*log_activity\(\$document\[\'log_reset\'\]/', $screen), 'a reset is logged only on Reset');
}

function test_erp_settings_template_actions_in_the_screen_toolbar()
{
	$screen = erp_settings_templates_test_source('erp_settings.php');

	pg_assert_true(strpos($screen, "\$output_screen_toolbar = \$template_toolbar ? pg_screen_toolbar(\$template_buttons) : '';") !== false, 'the toolbar is drawn by the helper');
	pg_assert_true((bool) preg_match('/<main id="content" class="container-fluid">\s*\' \. \$output_screen_toolbar \. \'/', $screen), 'straight under the heading');
	pg_assert_true(strpos($screen, '<form name="form" id="erp_template"') !== false, 'the buttons reach the form by its id');
	pg_assert_true(strpos($screen, '<nav id="button_bar" class="navigation') === false, 'no bar inside the card');
}
