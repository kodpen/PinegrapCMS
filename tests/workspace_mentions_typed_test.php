<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a mention typed by hand (includes/workspace/refs.php,
 * ws_mentions_typed()): an agent's handle or a single team member's
 * username typed after @ becomes the tag a pick from the @ list writes;
 * everything else stays as typed. And the change of a message: a typed
 * handle left alone asks nobody again once both texts are converted.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/refs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/render.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/messages.php');

// The channel's @ list: two agents with an application (picker entries),
// and the team with who may read the channel.
function workspace_mentions_typed_fixture()
{
	return array(
		'agents' => array(
			array('label' => '@mimar', 'token' => '<@app:7>'),
			array('label' => '@prmerge', 'token' => '<@app:9>'),
		),
		'people' => array(
			array('id' => 3, 'username' => 'erdal', 'reads' => true),
			array('id' => 4, 'username' => 'Şükrü', 'reads' => true),
			array('id' => 5, 'username' => 'ayse.yilmaz', 'reads' => true),
			array('id' => 6, 'username' => 'deniz', 'reads' => true),
			array('id' => 8, 'username' => 'Deniz', 'reads' => true),
			array('id' => 10, 'username' => 'gizli', 'reads' => false),
			array('id' => 11, 'username' => 'mimar', 'reads' => true),
		),
	);
}

function workspace_mentions_typed_run($body)
{
	$f = workspace_mentions_typed_fixture();

	return ws_mentions_typed($body, $f['agents'], $f['people']);
}

// A handle in any case becomes the agent's tag, looked up before a
// username (a person named like an agent is not taken).
function test_workspace_mentions_typed_handle()
{
	pg_assert_same('<@app:7> bak', workspace_mentions_typed_run('@mimar bak'), 'a handle');
	pg_assert_same('<@app:7> ve <@app:9>', workspace_mentions_typed_run('@MIMAR ve @PrMerge'), 'in any case');
	pg_assert_same('soru <@app:7>', workspace_mentions_typed_run('soru @mimar'), 'a handle before a username of the same name');
}

// A username, with or without the Turkish letters and in any case, when
// exactly one team member has it and may read the channel.
function test_workspace_mentions_typed_username()
{
	pg_assert_same('<@user:3> merhaba', workspace_mentions_typed_run('@Erdal merhaba'), 'a username');
	pg_assert_same('<@user:4> ve <@user:4>', workspace_mentions_typed_run('@şükrü ve @sukru'), 'with and without the Turkish letters');
	pg_assert_same('<@user:5> geldi', workspace_mentions_typed_run('@ayse.yilmaz geldi'), 'a username with a dot');
	pg_assert_same('@deniz bak', workspace_mentions_typed_run('@deniz bak'), 'two people share it: left as typed');
	pg_assert_same('@gizli bak', workspace_mentions_typed_run('@gizli bak'), 'a person who may not read the channel');
	pg_assert_same('@erd @ayse bak', workspace_mentions_typed_run('@erd @ayse bak'), 'a prefix stays');
}

// Punctuation after the name stays after the tag.
function test_workspace_mentions_typed_punctuation()
{
	pg_assert_same('<@user:3>, bak', workspace_mentions_typed_run('@erdal, bak'), 'a comma');
	pg_assert_same('Tamam <@app:7>.', workspace_mentions_typed_run('Tamam @mimar.'), 'a full stop');
	pg_assert_same('<@app:9>: hazır', workspace_mentions_typed_run('@prmerge: hazır'), 'a colon');
	pg_assert_same('<@user:5>.', workspace_mentions_typed_run('@ayse.yilmaz.'), 'a full stop after a dotted username');
	pg_assert_same('(<@app:7>)', workspace_mentions_typed_run('(@mimar)'), 'in brackets');
}

// The words everyone, the assistants and what is not a mention stay.
function test_workspace_mentions_typed_left_alone()
{
	pg_assert_same('@herkes @everyone @hepsi @ai @claude', workspace_mentions_typed_run('@herkes @everyone @hepsi @ai @claude'), 'everyone and the assistants');
	pg_assert_same('erdal@mimar.com', workspace_mentions_typed_run('erdal@mimar.com'), 'an e-mail address');
	pg_assert_same('a-@mimar x.@mimar', workspace_mentions_typed_run('a-@mimar x.@mimar'), 'after a hyphen or a dot');
	pg_assert_same('<@user:3> <@app:7>', workspace_mentions_typed_run('<@user:3> <@app:7>'), 'a token stays a token');
	pg_assert_same('@satis', workspace_mentions_typed_run('@satis'), 'a department or an unknown name');
	pg_assert_same('@sonnet bak', ws_mentions_typed('@sonnet bak', array(array('label' => '@sonnet', 'token' => '')), array()), 'a model agent without an application');
	pg_assert_same('', ws_mentions_typed('', array(), array()), 'empty');
}

// Never inside code or a link's address.
function test_workspace_mentions_typed_code_and_links()
{
	pg_assert_same('`@mimar` <@app:7>', workspace_mentions_typed_run('`@mimar` @mimar'), 'an inline code span');
	pg_assert_same("```\n@mimar\n```\n<@app:7>", workspace_mentions_typed_run("```\n@mimar\n```\n@mimar"), 'a fenced block');
	pg_assert_same('[<@app:7>](https://x.com/@mimar)', workspace_mentions_typed_run('[@mimar](https://x.com/@mimar)'), 'a link\'s address');
	pg_assert_same('https://medium.com/@erdal <@user:3>', workspace_mentions_typed_run('https://medium.com/@erdal @erdal'), 'a bare address');
}

// A display name: only an exact username counts, so "@Erdal Güral" takes
// the username and leaves the surname; a name nobody has as a username
// stays.
function test_workspace_mentions_typed_display_name()
{
	pg_assert_same('<@user:3> Güral', workspace_mentions_typed_run('@Erdal Güral'), 'the username part');
	pg_assert_same('@Ayşe Yılmaz', workspace_mentions_typed_run('@Ayşe Yılmaz'), 'a display name that is no username');
}

// A change: the old text held a typed handle, the new one keeps it and
// adds words; once both are converted no tag is added, so nobody is asked
// again. A new handle is still added.
function test_workspace_mentions_typed_change()
{
	$old = workspace_mentions_typed_run('@mimar bak');
	$same = workspace_mentions_typed_run('@mimar bak lütfen');
	$more = workspace_mentions_typed_run('@mimar bak @prmerge');

	pg_assert_same('', ws_message_tags_added($old, $same), 'the same mention asks nobody again');
	pg_assert_same('<@app:9>', ws_message_tags_added($old, $more), 'a new mention is added');
	pg_assert_same('<@app:7>', ws_message_tags_added('@mimar bak', $same), 'unconverted old text would ask again');
}
