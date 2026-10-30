<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Log lines in the panel language: who a row without a user is shown
 * as (pg_log_actor()), the software language for lines a visitor's request
 * writes (pg_log_lang()), and that the writers already moved over pass no
 * bare English text to log_activity().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A row names its user; without one it is a scheduled job (no address) or a
// visitor nobody knows (an address). The UNKNOWN older writers stored reads
// the same as no user.
function test_site_log_actor_without_user()
{
	pg_assert_same('admin', pg_log_actor('admin', ''));
	pg_assert_same('[SYSTEM]', pg_log_actor('[SYSTEM]', ''), 'an error_log entry keeps its label');
	pg_assert_same(lang('Scheduled job'), pg_log_actor('', ''));
	pg_assert_same(lang('Scheduled job'), pg_log_actor('UNKNOWN', ''));
	pg_assert_same(lang('Scheduled job'), pg_log_actor(null, null));
	pg_assert_same(lang('Unknown'), pg_log_actor('', '198.51.100.7'));
}

// Outside a page drawn in another language the line is lang()'s, and the
// translation hold is let go again.
function test_site_log_lang_matches_lang()
{
	$properties = array('string' => 'access denied to private folder ({var:1})', 'vars' => array('docs'));

	pg_assert_same(lang($properties), pg_log_lang($properties));

	if (function_exists('pg_tr_suspend')) {
		pg_assert_false(pg_tr_suspend(), 'the hold is released');
	}
}

/**
 * The log_activity() calls in a file whose text is a bare string literal (or
 * starts with one) instead of a lang() / pg_log_lang() call.
 *
 * @param string $path
 * @return array "line: code" entries
 */
function site_log_language_bare_calls($path)
{
	$tokens = token_get_all(file_get_contents($path));
	$count = count($tokens);
	$found = array();

	for ($i = 0; $i < $count; $i++) {
		if (!is_array($tokens[$i]) || ($tokens[$i][0] !== T_STRING) || ($tokens[$i][1] !== 'log_activity')) {
			continue;
		}

		$j = $i + 1;

		while (isset($tokens[$j]) && is_array($tokens[$j]) && ($tokens[$j][0] === T_WHITESPACE)) {
			$j++;
		}

		if (!isset($tokens[$j]) || ($tokens[$j] !== '(')) {
			continue;
		}

		$j++;

		while (isset($tokens[$j]) && is_array($tokens[$j]) && in_array($tokens[$j][0], array(T_WHITESPACE, T_COMMENT), true)) {
			$j++;
		}

		if (($tokens[$j] === '"') || (is_array($tokens[$j]) && in_array($tokens[$j][0], array(T_CONSTANT_ENCAPSED_STRING, T_START_HEREDOC), true))) {
			$found[] = $tokens[$i][2] . ': ' . (is_array($tokens[$j]) ? $tokens[$j][1] : '"');
		}
	}

	return $found;
}

// The writers moved to the panel language stay there.
function test_site_log_writers_pass_no_bare_english()
{
	$files = array(
		'add_field.php',
		'cancel_order.php',
		'change_password.php',
		'edit_contact.php',
		'edit_pages.php',
		'edit_theme_file.php',
		'editor_add_file.php',
		'email_a_friend.php',
		'email_campaign_job.php',
		'forgot_password.php',
		'get_file.php',
		'get_photo_gallery.php',
		'get_set_password.php',
		'import_design.php',
		'includes/explorer/recycle_bin.php',
		'includes/fn/carrier_webhook.php',
		'includes/fn/ecommerce.php',
		'includes/fn/google_reviews.php',
		'includes/fn/order_completion.php',
		'includes/fn/update.php',
		'includes/iys/client.php',
		'includes/panel/commerce.php',
		'includes/panel/software.php',
		'membership_entrance.php',
		'membership_job.php',
		'my_account_profile.php',
		'order_form.php',
		'order_invoice_print.php',
		'preview_style.php',
		'recurring_payment_job.php',
		'remove_item_from_cart.php',
		'reset_password.php',
		'retrieve_order.php',
		'set_password.php',
		'shipping.php',
		'smtp_settings.php',
		'software_update.php',
		'software_update_check.php',
		'submit_order.php',
		'update_order.php',
		'update_product_group_status.php',
		'view_order.php',
		'view_visitor_report.php',
		'welcome.php',
	);

	foreach ($files as $file) {
		pg_assert_same(array(), site_log_language_bare_calls(PG_FUNCTIONS_DIR . '/' . $file), $file);
	}

	// The check itself finds a bare literal.
	$sample = tempnam(sys_get_temp_dir(), 'pglog');
	file_put_contents($sample, "<?php\nlog_activity('page was saved');\nlog_activity(lang('page was saved'));\n");
	pg_assert_same(array("2: 'page was saved'"), site_log_language_bare_calls($sample));
	unlink($sample);
}

// A writer without a user stores the literal UNKNOWN pg_log_actor() knows,
// not its translation.
function test_site_log_writers_store_untranslated_unknown()
{
	$files = array(
		'includes/api/resources/account.php',
		'includes/fn/captcha.php',
		'includes/fn/passkeys.php',
		'index.php',
	);

	foreach ($files as $file) {
		pg_assert_false(strpos((string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file), "lang('UNKNOWN')"), $file);
	}
}
