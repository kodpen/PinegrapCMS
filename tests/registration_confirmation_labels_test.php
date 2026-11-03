<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the default registration confirmation content: its labels and
 * its button go through lang(), as the membership twin's do, so a Turkish
 * site does not show "Email", "Username" and "Continue" in English.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_registration_confirmation_labels_translated()
{
	foreach (array('registration', 'membership') as $kind) {
		$file = 'get_' . $kind . '_confirmation_screen_content.php';
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains("lang('Email') . ': '", $source, $file . ': Email label');
		pg_assert_contains("lang('Username') . ': '", $source, $file . ': Username label');
		pg_assert_contains("class=\"software_button_primary\">' . lang('Continue') . '</a>", $source, $file . ': Continue button');
		pg_assert_true(strpos($source, "'Email: '") === false, $file . ': no English-only Email label');
	}
}
