<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for voice messages (assets/js/workspace_voice.js,
 * includes/workspace/voice.php): the extensions a recording is saved with are
 * sounds to a channel (never .webm, which a channel draws as a video),
 * get_file.php hands them out with a sound type, and every text the script
 * asks for is given to it and translated.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/files.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/messages.php');

function ws_voice_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The body of a function of the script, up to the next function.
function ws_voice_test_function($source, $name)
{
	$from = strpos($source, 'function ' . $name . '(');

	if ($from === false) {
		return '';
	}

	$to = strpos($source, "\n    function ", $from + 1);

	return ($to === false) ? substr($source, $from) : substr($source, $from, $to - $from);
}

// Every extension the recorder may save with: those of its list of
// containers and those extensionOf() answers for the type a browser made.
function ws_voice_test_extensions()
{
	$source = ws_voice_test_source('assets/js/workspace_voice.js');

	preg_match_all("/\\['audio\\/[^']+',\\s*'([a-z0-9]+)'\\]/", $source, $listed);
	preg_match_all("/return '([a-z0-9]+)';/", ws_voice_test_function($source, 'extensionOf'), $answered);

	return array_values(array_unique(array_merge($listed[1], $answered[1])));
}

// A recording lands in the channel as a sound: .weba, .ogg and .m4a, never
// .webm. A file of the channel is handed out to be played in place, and
// nothing refuses it.
function test_ws_voice_extensions_are_sounds()
{
	$extensions = ws_voice_test_extensions();

	foreach (array('weba', 'ogg', 'm4a') as $expected) {
		pg_assert_true(in_array($expected, $extensions, true), 'the recorder saves .' . $expected);
	}

	pg_assert_false(in_array('webm', $extensions, true), 'a recording is never saved as .webm');

	foreach ($extensions as $extension) {
		$name = 'voice-20261024-093015.' . $extension;
		$type = ws_file_type($name);

		pg_assert_same('audio', ws_file_kind($name), $name);
		pg_assert_false(ws_upload_refused($name), $name . ' is taken');
		pg_assert_true($type['inline'], $name . ' plays in place');
		pg_assert_same(0, strpos($type['mime'], 'audio/'), $name . ' ' . $type['mime']);
	}
}

// get_file.php, which serves a channel's files by their address, gives
// every recording's extension, and the other sounds a channel plays, a type
// of its own: without one a file goes out as an attachment to be saved.
function test_ws_voice_get_file_has_the_sound_types()
{
	$source = ws_voice_test_source('get_file.php');

	foreach (array_merge(ws_voice_test_extensions(), array('oga', 'opus', 'aac')) as $extension) {
		pg_assert_true((bool) preg_match("/'" . $extension . "'\\s*=>\\s*'audio\\//", $source), 'get_file.php types .' . $extension);
	}

	pg_assert_true((bool) preg_match("/'webm'\\s*=>\\s*'video\\/webm'/", $source), 'get_file.php types .webm as a video');
}

// Every text the script asks for is one the screen gives it, and every
// English source of voice.php is a Turkish key.
function test_ws_voice_texts_are_given_and_translated()
{
	$script = ws_voice_test_source('assets/js/workspace_voice.js');
	$strings = ws_voice_test_source('includes/workspace/voice.php');
	$screen = ws_voice_test_source('includes/workspace/screen.php');
	$tr = pg_test_language_map();

	preg_match_all("/\\bt\\('([a-z_]+)'/", $script, $asked);
	pg_assert_true(count($asked[1]) > 5, 'the script asks for its texts');

	foreach (array_unique($asked[1]) as $key) {
		$given = (strpos($strings, "'" . $key . "'") !== false) || (strpos($screen, "'" . $key . "'") !== false);

		pg_assert_true($given, $key . ' is given to the script');
	}

	preg_match_all("/(?:lang|ws_js_template)\\('((?:[^'\\\\]|\\\\.)+)'/", $strings, $sources);
	pg_assert_true(count($sources[1]) >= 10, 'voice.php has its texts');

	foreach ($sources[1] as $source) {
		pg_assert_true(isset($tr[stripslashes($source)]), $source . ' has a Turkish text');
	}
}

// A voice reply answers a message only of its own channel or discussion, not
// deleted, and of the conversation as it is now; anything else gets the
// refusal the API answers 422 on parent_id with. No parent asks nothing.
function test_ws_voice_reply_parent_refusal()
{
	$parent = array('id' => 50, 'channel_id' => 7, 'deleted_at' => 0);
	$missing = lang('That message could not be found.');

	pg_assert_same('', ws_message_parent_refusal($parent, 7, 0), 'a message of the channel');
	pg_assert_same('', ws_message_parent_refusal($parent, 7, 49), 'a message just after a clear');
	pg_assert_same($missing, ws_message_parent_refusal(null, 7, 0), 'no such message');
	pg_assert_same($missing, ws_message_parent_refusal($parent, 8, 0), 'a message of another channel');
	pg_assert_same($missing, ws_message_parent_refusal(array('deleted_at' => 1790000000) + $parent, 7, 0), 'a deleted message');
	pg_assert_same(lang('This message is in an earlier version of the conversation, which is kept as it was.'),
		ws_message_parent_refusal($parent, 7, 50), 'a message cleared away');
	pg_assert_same('', ws_message_parent_check(array('id' => 7), 0), 'no parent');
}

// The panel's upload sends the parent a recording answers, and the player
// offers its own microphone only when the screen gives it an answer to make.
function test_ws_voice_reply_reaches_the_upload()
{
	$screen = ws_voice_test_source('assets/js/workspace.js');
	$voice = ws_voice_test_source('assets/js/workspace_voice.js');

	pg_assert_true((bool) preg_match("/api\\('ws_attach', \\{[^}]*parent_id: settings\\.parent_id/s", $screen), 'ws_attach carries parent_id');
	pg_assert_contains('parent_id: parent ? parent.id : 0', $screen, 'a recording answers the reply bar\'s message');
	pg_assert_contains('if (options && options.reply)', ws_voice_test_function($voice, 'player'), 'the reply microphone needs an answer to make');
}
