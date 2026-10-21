<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the account sync's own linking of provider cards to accounts
 * (erp_edoc_accounts_autolink(), includes/erp/edoc/account_sync.php): only
 * active cards are linked and only active cards make a number shared, so an
 * archived card is never linked on its own and an archived duplicate does not
 * keep the active card from linking. The function works on the database; its
 * two queries are read here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_erp_edoc_accounts_autolink_takes_active_cards_only()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/edoc/account_sync.php');
	$from = strpos($source, 'function erp_edoc_accounts_autolink(');
	$section = substr($source, $from, strpos($source, "\n}\n", $from) - $from);

	pg_assert_contains("AND status <> 'ignored' AND tax_number <> '' AND is_active = 1\n        GROUP BY tax_number HAVING COUNT(*) > 1", $section, 'a number is shared by active cards only');
	pg_assert_contains("AND status = 'new' AND no_auto_link = 0 AND tax_number <> '' AND is_active = 1", $section, 'only an active card links itself');
}
