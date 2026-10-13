<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Connecting an outside account (includes/fn/connect.php).
 *
 * POST: a panel screen starts a connection (provider, purpose, owner, the
 * screen to come back to); the browser is sent to the provider.
 *
 * GET: the provider sends the browser back here with a code, which is traded
 * for tokens; the browser then returns to the screen it came from with a
 * message. This address is the redirect address registered in every
 * provider's console (pg_connect_redirect_uri()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

if (pg_post_body_discarded()) {
	output_error(lang('The request was too large for this server and arrived empty.'), 413);
}

$user = validate_user();

$back = function ($return) {
	header('Location: ' . URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/' . pg_connect_return_path($return));
	exit();
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	validate_token_field();

	$owner = (isset($_POST['owner']) && ($_POST['owner'] === 'person')) ? 'person' : 'site';

	// The site's own accounts serve every feature, so connecting one is the
	// same right as Application Access; a person's own account is theirs.
	if ($owner === 'site') {
		validate_area_access($user, 'manager');
	}

	$return = isset($_POST['return']) ? (string) $_POST['return'] : '';

	$start = pg_connect_start(
		isset($_POST['provider']) ? (string) $_POST['provider'] : '',
		isset($_POST['purpose']) ? (string) $_POST['purpose'] : '',
		$owner,
		$user,
		$return,
		isset($_POST['connection_id']) ? (int) $_POST['connection_id'] : 0);

	if (!$start['ok']) {
		pg_connect_flash('error', $start['error']);
		$back($return);
	}

	header('Location: ' . $start['url']);
	exit();

}

$result = pg_connect_finish($_GET, $user);

if ($result['ok']) {
	pg_connect_flash('notice', lang(array('string' => '{var:1} is connected.', 'vars' => $result['label'])));
} else {
	pg_connect_flash('error', $result['error']);
}

$back($result['return']);
