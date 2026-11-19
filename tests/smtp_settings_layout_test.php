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

// SMTP Settings' five cards are Site Settings › E-mail sending's, in the
// screen's order, each under its section id; the Sending domain card comes
// unwrapped inside its section.
function test_smtp_settings_wide_layout()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/mail_sending.php');
	$at = -1;

	foreach (array('pgset-mail-transport', 'pgset-mail-system', 'pgset-mail-campaign', 'pgset-mail-dkim', 'pgset-mail-domain') as $section) {
		$here = strpos($source, '<div id="' . $section . '" class="pg-set-card">');
		pg_assert_true(($here !== false) && ($here > $at), $section . ' in its place');
		$at = $here;
	}

	pg_assert_contains('pg_mail_domain_health_card(pg_mail_domain_health(), false)', $source, 'the Sending domain card comes unwrapped');
	pg_assert_contains('id="mail_transport_card"', $source, 'the old anchor of the Sending service card stays');
	pg_assert_false(strpos($source, 'class="buttons navigation') !== false, 'no save bar of its own: the dialog and the page have theirs');
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
