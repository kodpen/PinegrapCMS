<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * SMTP Settings on the wide edit layout: the settings cards in the form
 * column, the Sending domain card alone in the status column, the save bar
 * in its own sticky column.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once PG_FUNCTIONS_DIR . '/includes/fn/mail_domain_health.php';

function test_smtp_settings_wide_layout()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/smtp_settings.php');

	$form = strpos($source, 'col-12 lg:col-8 2xl:col-9 order-1');
	$status = strpos($source, 'col-12 lg:col-4 2xl:col-3 order-2');
	$bar = strpos($source, 'col-12 order-3 position-sticky');

	pg_assert_true($form !== false && $status !== false && $bar !== false, 'form column, status column and save bar column');
	pg_assert_true($form < $status && $status < $bar, 'the status column follows the form column, the save bar follows both');
	pg_assert_true(strpos($source, "'Sending service'") < $status && strpos($source, '$dkim_txt_preview . \'') < $status, 'the settings cards sit in the form column');
	pg_assert_contains('pg_mail_domain_health_card(pg_mail_domain_health(), false)', $source, 'the Sending domain card goes into the status column unwrapped');
	pg_assert_true(strpos($source, 'pg_mail_domain_health_card(') > $status, 'the Sending domain card sits in the status column');
	pg_assert_same(1, substr_count($source, 'class="buttons navigation'), 'one save bar');
}

function test_mail_domain_health_card_column_wrapper()
{
	$health = array(
		'domain' => 'example.com',
		'selector' => 'pg',
		'rates' => array('judged' => false, 'sent' => 0, 'state' => 'ok'),
	);

	foreach (array('spf', 'dkim', 'dmarc') as $record) {
		$health[$record] = array('state' => 'ok', 'found' => '', 'copy' => '', 'reason' => '');
	}

	$wrapped = pg_mail_domain_health_card($health);
	$alone = pg_mail_domain_health_card($health, false);

	pg_assert_contains('<div class="col-12">', $wrapped, 'inside a row the card gets its column');
	pg_assert_false(strpos($alone, 'col-12') !== false, 'in a column of its own the card comes alone');
	pg_assert_contains('id="mail_domain_card"', $alone, 'the ops alert link target stays');
}
