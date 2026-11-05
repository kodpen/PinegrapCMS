<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Mailchimp API key on the Communication settings card
 * (includes/fn/mailchimp_key.php): the data center read from the key is a
 * plain host label, so the key is never sent to another host, and an empty
 * box keeps the stored key. No database and no Mailchimp.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A key-shaped test value, built at run time so no literal looks like a key.
function mailchimp_key_test_value($dc, $fill = 'a')
{
	return str_repeat($fill, 32) . '-' . $dc;
}

// The data center is the label after the last dash of a real key.
function test_mailchimp_dc_from_key()
{
	pg_assert_same('us6', pg_mailchimp_dc(mailchimp_key_test_value('us6')));
	pg_assert_same('us21', pg_mailchimp_dc(' ' . mailchimp_key_test_value('us21') . ' '));
}

// Anything that would put another host into the request address gives ''.
function test_mailchimp_dc_refuses_other_hosts()
{
	foreach (array(
		'',
		str_repeat('a', 32),
		'abc-evil.example/x?',
		'abc-evil.example#',
		'abc-us6.evil.example',
		'abc-user@evil.example',
		'abc-us6:8080',
		'abc-US6',
		"abc-us6\nevil",
		'abc-',
	) as $key) {
		pg_assert_same('', pg_mailchimp_dc($key), 'refused: ' . json_encode($key));
	}
}

// An empty box keeps the stored key; a typed key replaces it; the remove
// tick clears it.
function test_mailchimp_key_for_save()
{
	$stored = mailchimp_key_test_value('us6');
	$new = mailchimp_key_test_value('us21', 'b');

	pg_assert_same($stored, pg_mailchimp_key_for_save('', $stored, false));
	pg_assert_same($stored, pg_mailchimp_key_for_save('   ', $stored, false));
	pg_assert_same($new, pg_mailchimp_key_for_save(' ' . $new . ' ', $stored, false));
	pg_assert_same('', pg_mailchimp_key_for_save('', $stored, true));
	pg_assert_same($new, pg_mailchimp_key_for_save($new, $stored, true), 'a typed key wins over the tick');
	pg_assert_same('', pg_mailchimp_key_for_save(array('x'), '', false), 'a list is no key');
}
