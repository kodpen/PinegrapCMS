<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the shared address check validate_email_address()
 * (includes/fn/forms.php): a real top-level domain may be up to 63 letters
 * or an xn-- punycode label, and an address without one is still refused.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Long and punycode top-level domains are real addresses.
function test_email_check_tld_long_accepted()
{
	foreach (array('info@firma.istanbul', 'x@studio.digital', 'y@corp.software', 'a@b.' . str_repeat('k', 63), 'z@site.xn--mgbaam7a8h', 'ayse@example.com', 'a@b.tr') as $address) {
		pg_assert_true(validate_email_address($address), $address);
	}
}

// No top-level domain, a one-letter one, a too long one or a digit one is refused.
function test_email_check_tld_refused()
{
	foreach (array('ali@firma', 'ali@firma.c', 'a@b.' . str_repeat('k', 64), 'a@b.123', 'no at sign', '', 'a@b.com x') as $address) {
		pg_assert_false(validate_email_address($address), $address);
	}
}
