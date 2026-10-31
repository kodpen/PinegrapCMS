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
		'add_cart_quote.php',
		'add_erp_quote.php',
		'add_erp_receipt.php',
		'add_field.php',
		'cancel_order.php',
		'change_password.php',
		'chat.php',
		'custom_form.php',
		'delete_styles.php',
		'delete_users.php',
		'duplicate_folder_f.php',
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
		'import_key_codes.php',
		'includes/erp/mail.php',
		'includes/erp/order_bridge.php',
		'includes/erp/order_flow.php',
		'includes/erp/quote_links.php',
		'includes/erp/quotes.php',
		'includes/explorer/handle_backups.php',
		'includes/explorer/handle_bulk_edit.php',
		'includes/explorer/handle_catalog.php',
		'includes/explorer/handle_catalog_edit.php',
		'includes/explorer/handle_file_usage.php',
		'includes/explorer/handle_file_window.php',
		'includes/explorer/handle_files.php',
		'includes/explorer/handle_files_media.php',
		'includes/explorer/handle_folder_access.php',
		'includes/explorer/handle_folders.php',
		'includes/explorer/handle_items.php',
		'includes/explorer/handle_products_bulk.php',
		'includes/explorer/handle_recycle.php',
		'includes/explorer/handle_short_links.php',
		'includes/explorer/handle_upload.php',
		'includes/explorer/handle_zip.php',
		'includes/explorer/recycle_bin.php',
		'includes/fn/carrier_webhook.php',
		'includes/fn/cart_quotes.php',
		'includes/fn/custom_form.php',
		'includes/fn/designer.php',
		'includes/fn/ecommerce.php',
		'includes/fn/forms.php',
		'includes/fn/google_reviews.php',
		'includes/fn/layouts.php',
		'includes/fn/mail.php',
		'includes/fn/mail_queue.php',
		'includes/fn/order_completion.php',
		'includes/fn/returns.php',
		'includes/fn/subscription_renewal.php',
		'includes/fn/subscriptions_account.php',
		'includes/fn/update.php',
		'includes/iys/client.php',
		'includes/panel/commerce.php',
		'includes/panel/shared_component.php',
		'includes/panel/software.php',
		'includes/payments/callback.php',
		'includes/settings/accounting.save.php',
		'includes/settings/commerce.save.php',
		'includes/settings/features.save.php',
		'includes/settings/firewall.save.php',
		'includes/settings/fragment.php',
		'includes/settings/general.save.php',
		'includes/settings/languages.save.php',
		'includes/settings/screen.php',
		'includes/settings/seo.save.php',
		'includes/workspace/ai.php',
		'includes/workspace/api_resources.php',
		'includes/workspace/changes.php',
		'mail_queue.php',
		'mailchimp.php',
		'membership_entrance.php',
		'membership_job.php',
		'my_account_profile.php',
		'order_form.php',
		'order_invoice_print.php',
		'parasut_connect.php',
		'preview_style.php',
		'product_builder.php',
		'recurring_payment_job.php',
		'registration_entrance.php',
		'remove_item_from_cart.php',
		'reset_password.php',
		'retrieve_order.php',
		'seo_score_job.php',
		'seo_structure.php',
		'set_password.php',
		'shipping.php',
		'smtp_settings.php',
		'software_update.php',
		'software_update_check.php',
		'submit_order.php',
		'update_order.php',
		'update_product_group_status.php',
		'update_search_index.php',
		'view_cart_quotes.php',
		'view_folder_and_files_f.php',
		'view_order.php',
		'view_visitor_report.php',
		'waf_unban.php',
		'welcome.php',
	);

	foreach ($files as $file) {
		pg_assert_same(array(), site_log_language_bare_calls(PG_FUNCTIONS_DIR . '/' . $file), $file);
	}

	// Every writer of the external API (its resources, webhooks and sync
	// jobs) runs in an API request, a job or the panel: lang() throughout.
	$api = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PG_FUNCTIONS_DIR . '/includes/api', FilesystemIterator::SKIP_DOTS));

	foreach ($api as $file) {
		if (substr($file->getFilename(), -4) === '.php') {
			pg_assert_same(array(), site_log_language_bare_calls($file->getPathname()), $file->getPathname());
		}
	}

	// The check itself finds a bare literal.
	$sample = tempnam(sys_get_temp_dir(), 'pglog');
	file_put_contents($sample, "<?php\nlog_activity('page was saved');\nlog_activity(lang('page was saved'));\n");
	pg_assert_same(array("2: 'page was saved'"), site_log_language_bare_calls($sample));
	unlink($sample);
}

// A writer without a user stores the literal UNKNOWN pg_log_actor() knows,
// not its translation. view_waf_log.php only reads it: its filter still
// skips the translated UNKNOWN older rows hold.
function test_site_log_writers_store_untranslated_unknown()
{
	$found = array();
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PG_FUNCTIONS_DIR, FilesystemIterator::SKIP_DOTS));

	foreach ($files as $file) {
		$path = str_replace('\\', '/', substr($file->getPathname(), strlen(PG_FUNCTIONS_DIR) + 1));

		if ((substr($path, -4) !== '.php') || (strpos($path, 'assets/lib/') === 0) || ($path === 'view_waf_log.php')) {
			continue;
		}

		if (strpos((string) file_get_contents($file->getPathname()), "lang('UNKNOWN')") !== false) {
			$found[] = $path;
		}
	}

	pg_assert_same(array(), $found);
}

// A payment message's line is built from its parts in the software language;
// a finished line is never rewritten by searching its English text.
function test_site_log_payment_notice_line_from_parts()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/payments/callback.php');

	pg_assert_false(preg_match('/str_replace\(\s*\'[A-Z][a-z]/', $source) === 1, 'no str_replace() on English text');

	$order = array('id' => '438', 'status' => 'incomplete', 'transaction_id' => '');
	$answer = array('success' => true, 'status' => 'paid', 'transaction_id' => 'pi_1', 'amount' => 1000, 'log' => array('Stripe reports that payment pi_1 succeeded'), 'raw' => array());
	$outcome = pg_payment_notify_outcome('stripe', $answer, array('total' => '2000') + $order, 'TRY');

	pg_assert_same('Stripe reports that payment pi_1 succeeded', $outcome['source'], 'the service\'s own line is kept for the caller');
	pg_assert_same(pg_payment_notify_line('stripe', $outcome['source'], 438, 'not_matching'), $outcome['log'][0]);
	pg_assert_same(
		pg_log_lang(array('string' => '{var:1} for order #{var:2}, which was still incomplete. The order was not completed: {var:3}. Check the payment on {var:4}\'s own screen.', 'vars' => array('X', 438, 'why', pg_payment_log_name('stripe')))),
		pg_payment_notify_line('stripe', 'X', 438, 'not_completed', array('reason' => 'why')));
}

// The payment drivers' own log lines, the cancellation's, the cart
// retrieval's, the shipping method refusals', the checkout's gateway
// errors (one text for the visitor through lang(), one for the log), the
// undelivered e-mail's and the subscription engine's are built with
// pg_log_lang(), never from bare English or the visitor's language.
function test_site_log_payment_driver_lines_translated()
{
	$patterns = array(
		'includes/payments/stripe.php'  => '/(\'log\'\s*=>\s*array\(\s*\'|\$line = \'|\[\'log\'\]\[\] = \'|_logged\([^;]*\), array\(\s*\')/',
		'includes/payments/paypal.php'  => '/(\'log\'\s*=>\s*array\(\s*\'|\$line = \'|\[\'log\'\]\[\] = \'|_logged\([^;]*\), array\(\s*\'|^\s*\'PayPal capture)/m',
		'includes/payments/paytr.php'   => '/(\'log\'\s*=>\s*array\(\s*\'|_logged\([^;]*\), array\(\s*\'|\? \' \(test mode\)\')/',
		'includes/payments/iyzico.php'  => '/(\$log\[\] = (lang\(|"|\')|array\(\s*["\']Iyzipay|\[\'log\'\] = array\(\s*\')/',
		'includes/fn/payments.php'      => '/\$log\[\] = \'Auto-refund/',
		'retrieve_order.php'            => '/\$message = \'/',
		'shipping_method.php'           => '/\' \(Order ID: \'/',
		'express_order.php'             => '/\' \(Order ID: \'/',
		'includes/fn/mail.php'          => '/\$log \.?= lang\(/',
		'includes/fn/mail_queue.php'    => '/log_activity\(lang\(/',
		'includes/fn/subscription_renewal.php'  => '/(log_activity\(|\$io\[\'log\'\], )lang\(/',
		'includes/fn/subscriptions_account.php' => '/\$io\[\'log\'\]\(lang\(/',
		'submit_order.php'              => '/lang\(\'(Error message from payment gateway: \'\)|cURL Error Number:)/',
	);

	foreach ($patterns as $file => $pattern) {
		pg_assert_same(0, preg_match($pattern, (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file)), $file);
	}

	// Each gateway error the checkout shows the visitor is logged from the
	// same parts in the software language.
	$checkout = (string) file_get_contents(PG_FUNCTIONS_DIR . '/submit_order.php');
	$shown = substr_count($checkout, '$payment_gateway_error_message = lang($payment_gateway_error);');

	pg_assert_true($shown > 0, 'the checkout builds its gateway errors from parts');
	pg_assert_same($shown, substr_count($checkout, 'log_activity(pg_log_lang($payment_gateway_error)'), 'one log line per error shown');
}

// The lines a visitor's request writes on a designed form (access denied,
// auto-login, key code), on member log-out (logout() from logout.php and
// kiosk.php) and on a quote's guest signature (erp_quote.php) take the
// software language. The panel's own lines in those files stay lang():
// the orphaned form adoption (includes/fn/custom_form.php, reached only from
// the designer), the new guest link (quote_links.php), add_erp_quote.php,
// add_erp_receipt.php and includes/erp/quotes.php.
function test_site_log_visitor_form_logout_signature_lines_translated()
{
	$patterns = array(
		'custom_form.php'              => '/log_activity\(lang\(/',
		'includes/fn/forms.php'        => '/log_activity\(lang\(\'user logged out\'\)/',
		'includes/erp/quote_links.php' => '/log_activity\(lang\(array\(\s*\'string\' => \'erp quote \(\{var:1\}\) was signed by/',
	);

	foreach ($patterns as $file => $pattern) {
		pg_assert_same(0, preg_match($pattern, (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file)), $file);
	}
}

// E-mail a Friend counts its ten e-mails a day in the WAF rate store, not by
// matching the log line's English text, so the line takes the software
// language: the count is read before sending and taken after the e-mail went.
function test_site_log_email_a_friend_limit_in_rate_store()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/email_a_friend.php');

	pg_assert_same(false, strpos($source, 'e-mailed a link%'), 'no log query for the limit');
	pg_assert_same(1, preg_match('/waf_rate_hits\(pg_auth_request_address\(\), \'eaf\', 86400\) >= 10/', $source), 'hits read');
	pg_assert_same(1, preg_match('/waf_rate_record\(pg_auth_request_address\(\), \'eaf\', 86400\)/', $source), 'hit recorded');

	$hits = strpos($source, 'waf_rate_hits(');
	$send = strpos($source, "\nemail(array(");
	$record = strpos($source, 'waf_rate_record(');

	pg_assert_true(($hits !== false) && ($send !== false) && ($record !== false) && ($hits < $send) && ($send < $record), 'read before sending, recorded after');
	pg_assert_same(1, preg_match('/log_activity\(pg_log_lang\(array\(\'string\' => \'{var:1} e-mailed a link \({var:2}\) to a friend\'/', $source), 'log line through pg_log_lang()');
}

// The checkout's automatic invoice (order_flow.php's invoice, delivery note
// and cancellation steps, the auto-invoice lines in order_bridge.php and the
// automatic invoice e-mail in mail.php) and the order completion lines run
// in the visitor's checkout or payment callback as well as from the panel:
// they take the software language, so one checkout logs in one language.
function test_site_log_checkout_auto_invoice_lines_translated()
{
	$patterns = array(
		'includes/erp/order_flow.php'      => '/log_activity\(lang\(/',
		'includes/erp/order_bridge.php'    => '/(log_activity\(lang\(|\? lang\(array\(\'string\' => \'Order \(#\{var:1\}\) was invoiced|: lang\(array\(\'string\' => \'Order \(#\{var:1\}\) was invoiced)/',
		'includes/erp/mail.php'            => '/log_activity\(lang\(/',
		'includes/fn/order_completion.php' => '/log_activity\(lang\(/',
	);

	foreach ($patterns as $file => $pattern) {
		pg_assert_same(0, preg_match($pattern, (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file)), $file);
	}
}

// The lines a visitor's request writes on sign-in (the password form posted
// from a page, forgot password, the registration area, the two-step and SMS
// step, passkeys, social sign-in, the device limit, the account security
// screen), on CAPTCHA refusals, comment submission refusals, event
// reservations, return requests, comment watchers, one-click unsubscribe,
// the member's own order deletion, checkout (shipping rates, address checks,
// the order submission), a cart quote taken into the cart, a chat closed from
// the site widget, a form signature's time stamp and the accountant's
// download link take the software language. The panel's access gates in
// auth.php stay lang().
function test_site_log_visitor_sign_in_and_account_lines_translated()
{
	require_once(__DIR__ . '/helpers/source_reader.php');

	$files = array(
		'mfa.php',
		'passkey.php',
		'account_security.php',
		'device_limit.php',
		'signin.php',
		'reserve_calendar_event.php',
		'order_return.php',
		'email_preferences.php',
		'add_or_remove_watcher.php',
		'add_comment.php',
		'erp_accountant_download.php',
		'delete_order.php',
		'index.php',
		'forgot_password.php',
		'registration_entrance.php',
		'shipping.php',
		'submit_order.php',
		'includes/fn/mfa.php',
		'includes/fn/passkeys.php',
		'includes/fn/signin.php',
		'includes/fn/signature.php',
	);

	foreach ($files as $file) {
		pg_assert_same(0, preg_match('/log_activity\(lang\(/', (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file)), $file);
	}

	pg_assert_same(0, preg_match('/(\$message = lang\(|log_activity\(lang\()/', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/captcha.php')), 'captcha.php');
	pg_assert_same(0, preg_match('/\? lang\(\'user asked for an e-mailed code/', (string) file_get_contents(PG_FUNCTIONS_DIR . '/passkey.php')), 'passkey.php e-mailed code');
	pg_assert_same(0, preg_match('/log_activity\(lang\(\'chat conversation closed/', (string) file_get_contents(PG_FUNCTIONS_DIR . '/chat.php')), 'chat.php closed line');

	foreach (array('pg_device_limit_gate', 'validate_login', 'initialize_user', 'check_banned_ip_addresses') as $function) {
		$source = pg_test_function_source($function);

		pg_assert_true(strpos($source, 'pg_log_lang(') !== false, $function . ' writes through pg_log_lang()');
		pg_assert_same(0, preg_match('/log_activity\(\s*lang\(/', $source), $function);
	}

	pg_assert_same(1, preg_match('/log_activity\(pg_log_lang\(array\(\'string\' => \'cart quote \{var:1\} was added to a cart/', pg_test_function_source('pg_cart_quote_apply')), 'pg_cart_quote_apply()');
}
