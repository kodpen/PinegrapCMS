<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the rule that lets a channel line follow the one before it
 * (sameSender() in assets/js/workspace.js): every application, guest and
 * outside mail sender carries id 0, so two of them must still read as two
 * authors. Run with Node when it is there.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The function's source, cut out of workspace.js.
function pg_message_follow_test_source()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	return preg_match('/\n    function sameSender\(a, b\) \{\n.*?\n    \}\n/s', $js, $match) ? $match[0] : '';
}

// Runs the cases with Node; null without Node.
function pg_message_follow_test_run($cases)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgfollow');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, pg_message_follow_test_source() . "\nprocess.stdout.write(JSON.stringify(" . json_encode($cases) . ".map(function (c) { return sameSender(c[0], c[1]); })));\n");
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return json_decode(implode("\n", $out), true);
}

function test_workspace_message_follow_rule_is_used()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	pg_assert_true(pg_message_follow_test_source() !== '', 'sameSender() is in workspace.js');
	pg_assert_contains("previous.kind !== 'system' && sameSender(previous, message)", $js, 'the follow rule reads it');
}

function test_workspace_message_follow_same_sender()
{
	$user = function ($id) {
		return array('sender_kind' => 'user', 'sender' => array('id' => $id, 'name' => 'Ayşe'));
	};
	$app = function ($app_id, $name) {
		return array('sender_kind' => 'app', 'sender' => array('id' => 0, 'app_id' => $app_id, 'name' => $name));
	};
	$guest = function ($guest_id) {
		return array('sender_kind' => 'guest', 'sender' => array('id' => 0, 'guest_id' => $guest_id, 'name' => 'Guest'));
	};
	$mail = function ($email) {
		return array('sender_kind' => 'mail', 'sender' => array('id' => 0, 'name' => 'Mail', 'title' => $email));
	};

	$cases = array(
		'one person'                      => array(array($user(4), $user(4)), true),
		'two people'                      => array(array($user(4), $user(5)), false),
		'one application'                 => array(array($app(13, '@mimar'), $app(13, '@mimar')), true),
		'two applications'                => array(array($app(13, '@mimar'), $app(14, '@prmerge')), false),
		'an application after a person'   => array(array($user(0), $app(13, '@mimar')), false),
		'two OAuth clients of one app'    => array(array($app(13, 'Claude Code'), $app(13, 'Cursor')), false),
		'one guest'                       => array(array($guest(2), $guest(2)), true),
		'two guests'                      => array(array($guest(2), $guest(3)), false),
		'one mail address'                => array(array($mail('a@x.example'), $mail('a@x.example')), true),
		'two mail addresses'              => array(array($mail('a@x.example'), $mail('b@x.example')), false),
		'no sender'                       => array(array(array('sender_kind' => 'app'), $app(13, '@mimar')), false),
	);

	$result = pg_message_follow_test_run(array_values(array_map(function ($case) {
		return $case[0];
	}, $cases)));

	if ($result === null) {
		pg_assert_true(true, 'Node is not available: the cases are not run');
		return;
	}

	pg_assert_true(is_array($result) && (count($result) === count($cases)), 'Node answered every case');

	$i = 0;

	foreach ($cases as $name => $case) {
		pg_assert_same($case[1], $result[$i++] ?? null, $name);
	}
}
