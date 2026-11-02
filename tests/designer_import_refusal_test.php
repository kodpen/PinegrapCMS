<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * designer_import.php answers the designer's fetch() in JSON. A missing
 * session, a role below designer or a token that does not match the session
 * is refused in JSON with its status and a message the editor shows, not the
 * login page or an HTML error page (read as "Import failed (200).").
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/designer_import.php');

function test_designer_import_refusal()
{
	pg_assert_same(null, pg_designer_import_refusal(true, 1, 'abc', 'abc'), 'a designer with the session token passes');
	pg_assert_same(null, pg_designer_import_refusal(true, 0, 'abc', 'abc'), 'an administrator passes');
	pg_assert_same(array(401, lang('Your session has expired. Reload the page and sign in again.')), pg_designer_import_refusal(false, 3, 'abc', 'abc'), 'no session: sign in again');
	pg_assert_same(array(403, lang('Access denied.')), pg_designer_import_refusal(true, 2, 'abc', 'abc'), 'a manager is below designer');
	pg_assert_same(array(403, lang('Your session expired. Reload the page and try again.')), pg_designer_import_refusal(true, 1, 'abc', 'abd'), 'a wrong token');
	pg_assert_same(array(403, lang('Your session expired. Reload the page and try again.')), pg_designer_import_refusal(true, 1, '', ''), 'no token in the session');
	pg_assert_same(array(403, lang('Your session expired. Reload the page and try again.')), pg_designer_import_refusal(true, 1, 'abc', array('abc')), 'a token sent as an array');
}

function test_designer_import_refuses_before_html_gates()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/designer_import.php');
	$refusal = strpos($source, 'pg_designer_import_refusal(');

	pg_assert_true($refusal !== false, 'the endpoint asks the JSON refusal');
	pg_assert_true($refusal < strpos($source, 'validate_user()'), 'before validate_user() sends the login page');
}
