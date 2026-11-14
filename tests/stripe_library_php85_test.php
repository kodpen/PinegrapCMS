<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the bundled classic Stripe library (1.17.2, used by the
 * 'Stripe' gateway in submit_order.php) stays quiet on PHP 8.1 to 8.5. The
 * library is patched locally: ArrayAccess and IteratorAggregate methods carry
 * #[\ReturnTypeWillChange], the error classes declare their properties,
 * "${var}" interpolation is "{$var}", utf8_encode() is mb_convert_encoding()
 * and the cURL handle is unset instead of passed to curl_close(). The test
 * runner lets a deprecation raised inside a bundled library through, so the
 * library is loaded and exercised in a PHP process of its own that reports
 * every error.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_stripe_library_loads_and_runs_without_notices()
{
	if (!function_exists('mb_convert_encoding')) {
		return;
	}

	$library = PG_FUNCTIONS_DIR . '/includes/stripe/lib/Stripe.php';
	$script = tempnam(sys_get_temp_dir(), 'pg_stripe');
	$code = '<?php
error_reporting(E_ALL);
ini_set("display_errors", "1");
set_error_handler(function ($severity, $message, $file, $line) {
	echo "ERROR " . $message . " (" . basename($file) . ":" . $line . ")\n";
	return true;
});
require ' . var_export($library, true) . ';
$out = array();
$object = Stripe_Object::constructFrom(array("id" => "ch_1", "object" => "charge", "card" => array("id" => "card_1", "object" => "card")), "key");
$object["metadata"] = "x";
$out[] = isset($object["metadata"]) ? "set" : "unset";
unset($object["metadata"]);
$out[] = isset($object["metadata"]) ? "set" : "unset";
$out[] = $object["id"];
$out[] = Stripe_Charge::classUrl("Stripe_Charge");
$out[] = Stripe_Account::classUrl("Stripe_Account");
foreach (new Stripe_Util_Set(array("a")) as $member) {
	$out[] = $member;
}
$out[] = bin2hex(Stripe_ApiRequestor::utf8("caf\xe9"));
$out[] = Stripe_ApiRequestor::utf8("café");
$card = new Stripe_CardError("declined", "number", "card_declined", 402, "body", array());
$out[] = $card->param . "|" . $card->getCode() . "|" . $card->getHttpStatus() . "|" . $card->getHttpBody();
$invalid = new Stripe_InvalidRequestError("bad", "amount", 400, "body", array());
$out[] = $invalid->param . "|" . $invalid->getHttpStatus();
echo "OUT " . implode(",", $out) . "\n";
';
	file_put_contents($script, $code);
	$output = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1');
	unlink($script);

	pg_assert_same('OUT set,unset,ch_1,/v1/charges,/v1/account,a,636166c3a9,café,number|card_declined|402|body,amount|400', trim($output));
}

function test_stripe_library_calls_no_deprecated_function()
{
	$found = array();
	$directory = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PG_FUNCTIONS_DIR . '/includes/stripe/lib', FilesystemIterator::SKIP_DOTS));

	foreach ($directory as $file) {
		if (substr($file->getFilename(), -4) !== '.php') {
			continue;
		}

		foreach (token_get_all((string) file_get_contents($file->getPathname())) as $token) {
			if (is_array($token) && ($token[0] === T_STRING) && in_array(strtolower($token[1]), array('curl_close', 'utf8_encode', 'utf8_decode'), true)) {
				$found[] = $file->getFilename() . ':' . $token[2] . ' ' . $token[1];
			} elseif (is_array($token) && ($token[0] === T_DOLLAR_OPEN_CURLY_BRACES)) {
				$found[] = $file->getFilename() . ':' . $token[2] . ' ${';
			}
		}
	}

	pg_assert_same(array(), $found, 'deprecated calls in the Stripe library');
}
