<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the poll box's layout in assets/js/workspace.js when the
 * question carries a picture of its own: pollShape() and pollLayout() run
 * in Node; the message's picture drawn in the box and never also as its
 * file card; the viewer opening on the question's picture.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The text of one method of the channel screen in workspace.js, or ''.
function workspace_poll_question_box_test_method($name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	if (!preg_match('/\n        (' . preg_quote($name, '/') . ': function \([^)]*\) \{\n.*?\n        \}),\n/s', $source, $match)) {
		return '';
	}

	return $match[1];
}

// Runs the layout methods in Node with the script given, which prints its
// result as JSON; null where Node is not there.
function workspace_poll_question_box_test_node($script)
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

	$methods = array();

	foreach (array('pollShape', 'pollQuestionPicture', 'pollLayout') as $name) {
		$method = workspace_poll_question_box_test_method($name);
		pg_assert_true($method !== '', 'workspace.js has ' . $name . '()');
		$methods[] = $method;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgpollbox');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, "var S = {\n" . implode(",\n", $methods) . "\n};\n" . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

// The layouts of the cases below, keyed by name.
function workspace_poll_question_box_test_layouts()
{
	return workspace_poll_question_box_test_node(<<<'JS'
var pic = { url: 'p.png', image: true };
var plain = [{ id: 1, label: 'Yes' }, { id: 2, label: 'No' }];
var other = plain.concat([{ id: 3, label: 'Other', other: true }]);
var thumbs = [{ id: 1, label: 'A', picture: pic }, { id: 2, label: 'B' }];
var allPictured = [{ id: 1, label: 'A', picture: pic }, { id: 2, label: 'B', picture: pic }];
var ask = function (extra) { return Object.assign({ agent_question: true, can_answer: true }, extra); };
var out = {
    one_other: S.pollLayout(ask({ options: other }), pic),
    several: S.pollLayout(ask({ options: plain, multiple: true }), pic),
    one_click: S.pollLayout(ask({ options: plain }), pic),
    thumbs: S.pollLayout(ask({ options: thumbs }), pic),
    answered: S.pollLayout(ask({ options: other, answered_by: { name: 'Ali' }, can_answer: false }), pic),
    withdrawn: S.pollLayout(ask({ options: other, withdrawn: true, can_answer: false }), pic),
    not_asked: S.pollLayout(ask({ options: other, can_answer: false }), pic),
    person: S.pollLayout({ options: other, can_vote: true }, pic),
    person_split: S.pollLayout({ options: allPictured }, pic),
    none_split: S.pollLayout({ options: allPictured }, null),
    none_rows: S.pollLayout({ options: thumbs }, null),
    none_text: S.pollLayout({ options: plain }, null),
    picture_of: [
        S.pollQuestionPicture({ poll: { id: 1 }, file: { url: 'q.png', image: true } }),
        S.pollQuestionPicture({ poll: { id: 1 }, file: { url: 'q.pdf', image: false } }),
        S.pollQuestionPicture({ file: { url: 'q.png', image: true } }),
        S.pollQuestionPicture({ poll: { id: 1 }, deleted: true, file: { url: 'q.png', image: true } }),
        S.pollQuestionPicture({ poll: { id: 1 }, file: null })
    ]
};
console.log(JSON.stringify(out));
JS
	);
}

// A question with a picture of its own takes the "question" shape whatever
// else the poll has; options keep their own pictures as thumbnails.
function test_workspace_poll_question_picture_shape()
{
	$all = workspace_poll_question_box_test_layouts();

	if ($all === null) {
		return;
	}

	foreach (array('one_other', 'several', 'one_click', 'thumbs', 'answered', 'withdrawn', 'person', 'person_split') as $case) {
		pg_assert_same('question', $all[$case]['shape'], $case . ': the question\'s picture leads');
		pg_assert_same('question', $all[$case]['figure'], $case . ': the large picture is the question\'s');
		pg_assert_true($all[$case]['thumbs'], $case . ': options show their own as thumbnails');
	}
}

// Without a picture of its own the three approved shapes stay.
function test_workspace_poll_question_picture_none_keeps_three_shapes()
{
	$all = workspace_poll_question_box_test_layouts();

	if ($all === null) {
		return;
	}

	pg_assert_same(array('split', 'option', false), array($all['none_split']['shape'], $all['none_split']['figure'], $all['none_split']['thumbs']));
	pg_assert_same(array('rows', '', true), array($all['none_rows']['shape'], $all['none_rows']['figure'], $all['none_rows']['thumbs']));
	pg_assert_same(array('text', '', false), array($all['none_text']['shape'], $all['none_text']['figure'], $all['none_text']['thumbs']));
}

// An agent question: "Other" stays the words field with one Send; one answer
// without "Other" stays one click; several answers go with Send.
function test_workspace_poll_question_picture_answering()
{
	$all = workspace_poll_question_box_test_layouts();

	if ($all === null) {
		return;
	}

	pg_assert_same(array(true, false, true, false), array($all['one_other']['other_field'], $all['one_other']['direct'], $all['one_other']['send'], $all['one_other']['other_row']));
	pg_assert_same(array(false, false, true), array($all['several']['other_field'], $all['several']['direct'], $all['several']['send']));
	pg_assert_same(array(false, true, false), array($all['one_click']['other_field'], $all['one_click']['direct'], $all['one_click']['send']));
	pg_assert_same(array(false, false, true), array($all['not_asked']['other_field'], $all['not_asked']['send'], $all['not_asked']['other_row']), 'a reader not asked sees "Other" as a row');
	pg_assert_same(array(false, true), array($all['person']['other_field'], $all['person']['other_row']), 'a person\'s poll keeps "Other" as a row');
}

// Answered keeps the picture beside the chosen rows; taken back greys it.
function test_workspace_poll_question_picture_answered_and_taken_back()
{
	$all = workspace_poll_question_box_test_layouts();

	if ($all === null) {
		return;
	}

	pg_assert_same(array(true, false, false), array($all['answered']['answered'], $all['answered']['withdrawn'], $all['answered']['send']));
	pg_assert_same(array(false, true, false), array($all['withdrawn']['answered'], $all['withdrawn']['withdrawn'], $all['withdrawn']['send']));
}

// The question's picture is the poll message's own image file, never a
// deleted message's and never another kind of file.
function test_workspace_poll_question_picture_of_message()
{
	$all = workspace_poll_question_box_test_layouts();

	if ($all === null) {
		return;
	}

	pg_assert_same(array('url' => 'q.png', 'image' => true), $all['picture_of'][0]);
	pg_assert_same(array(null, null, null, null), array_slice($all['picture_of'], 1));
}

// The message draws the picture once: in the box, not also as a file card;
// the large picture opens the viewer on it, the options' pictures after it.
function test_workspace_poll_question_picture_drawn_once()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$pictures = workspace_poll_question_box_test_method('pollPictures');

	pg_assert_true(strpos($source, 'if (message.file && !self.pollQuestionPicture(message)) {') !== false, 'the file card leaves out the question\'s picture');
	pg_assert_true(strpos($pictures, 'images.push({ src: question.src, alt: question.alt, caption: question.alt });') !== false, 'the question\'s picture comes first in the viewer');
	pg_assert_true(substr_count($source, 'self.pollQuestionFigure(') >= 3, 'both boxes and the answered box draw the question\'s figure');
}

// The figure's text is translated.
function test_workspace_poll_question_picture_text()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php');

	pg_assert_true(strpos($source, "'poll_question_picture_open' => lang('Open the question\\'s picture')") !== false, 'the figure\'s name is a translated text');
}
