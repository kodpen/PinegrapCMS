<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_defuse_page_message() and the places that must call it. A
 * liveform message is HTML that the messages node of the visual designer
 * and the messages placeholder of get_page_content.php print into the page
 * before the page is run through PHP and its <!--pg-...--> markers and
 * region tags are expanded, and a message can carry a visitor's text (a
 * refused file name, a typed value).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!class_exists('liveform', false)) {
	require_once(PG_FUNCTIONS_DIR . '/liveform.class.php');
}

// Runs $callback with an empty session and puts the previous session back.
function _test_page_messages_with_session($callback)
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array();

	try {
		$callback();
	} finally {
		if ($saved === null) {
			unset($_SESSION);
		} else {
			$_SESSION = $saved;
		}
	}
}

// A PHP open tag in a message stays on screen but no longer opens PHP.
function test_page_message_defuses_php_open_tag()
{
	pg_assert_same('Sorry, &lt;?php echo 1; ?>.php was not accepted.', pg_defuse_page_message('Sorry, <?php echo 1; ?>.php was not accepted.'));
	pg_assert_same('a &lt;?= $x ?> b', pg_defuse_page_message('a <?= $x ?> b'));
}

// The page pipeline's markers are made inert in any letter case.
function test_page_message_defuses_pipeline_markers()
{
	pg_assert_same('x <!-- pg-custom-php:ZWNobyAxOw==--> y', pg_defuse_page_message('x <!--pg-custom-php:ZWNobyAxOw==--> y'));
	pg_assert_same('<!-- pg-shared-ref:5-->', pg_defuse_page_message('<!--pg-shared-ref:5-->'));
	pg_assert_same('<!-- pg-system-widget:7-->', pg_defuse_page_message('<!--PG-system-widget:7-->'));
	pg_assert_same('<!-- pg-messages-placeholder-->', pg_defuse_page_message('<!--pg-messages-placeholder-->'));
}

// The region tags the page scans for are shown as text, opening and closing.
function test_page_message_defuses_region_tags()
{
	pg_assert_same('&lt;cregion>Footer&lt;/cregion>', pg_defuse_page_message('<cregion>Footer</cregion>'));
	pg_assert_same('&lt;DREGION>x&lt;/dregion>', pg_defuse_page_message('<DREGION>x</dregion>'));
	pg_assert_same('&lt;system>1&lt;/system> &lt;pregion>2&lt;/pregion>', pg_defuse_page_message('<system>1</system> <pregion>2</pregion>'));
	pg_assert_same('&lt;if user="1">a&lt;else>b&lt;/if>', pg_defuse_page_message('<if user="1">a<else>b</if>'));
}

// The markup a message is allowed to carry comes back byte for byte.
function test_page_message_keeps_ordinary_markup()
{
	$html = 'Please <a href="/login?x=1&amp;y=2">sign in</a> or <b>register</b>; 1 < 2. <address>x</address> <admin> <cartoon>';
	pg_assert_same($html, pg_defuse_page_message($html));
	pg_assert_same('', pg_defuse_page_message(null));
	pg_assert_same('', pg_defuse_page_message(''));
}

// Every liveform method that stores a message stores it defused.
function test_page_message_liveform_setters_defuse()
{
	_test_page_messages_with_session(function () {
		$form = new liveform('page_messages_test');
		$bad = 'x <?php echo 1; ?> <!--pg-custom-php:ZWNobyAxOw==--> <cregion>a</cregion>';
		$safe = 'x &lt;?php echo 1; ?> <!-- pg-custom-php:ZWNobyAxOw==--> &lt;cregion>a&lt;/cregion>';

		$form->mark_error('file', $bad);
		$form->add_error($bad);
		$form->add_notice($bad);
		$form->add_warning($bad);
		$form->validate_required_field('empty_field', $bad);

		$stored = $_SESSION['software']['liveforms']['page_messages_test'][0];

		pg_assert_same($safe, $stored['file']['error_message']);
		pg_assert_same($safe, $stored['_form_error_0']['error_message']);
		pg_assert_same(array($safe), $stored['notices']);
		pg_assert_same(array($safe), $stored['warnings']);
		pg_assert_same($safe, $stored['empty_field']['error_message']);
	});
}

// The designer's messages node defuses what it reads from the session, so a
// message that did not come through a liveform method is covered as well.
function test_page_message_designer_node_defuses_session_messages()
{
	_test_page_messages_with_session(function () {
		$_SESSION['software']['liveforms']['raw_form'][0] = array(
			'file' => array('error' => true, 'error_message' => 'E <?php echo 1; ?>'),
			'notices' => array('N <!--pg-custom-php:ZWNobyAxOw==-->'),
			'warnings' => array('W <dregion>x</dregion>'),
		);

		$html = _render_content_html(array('contentType' => 'messages'), '');

		pg_assert_true(strpos($html, '<?') === false, 'no PHP open tag in the rendered messages');
		pg_assert_true(stripos($html, '<!--pg-') === false, 'no live pipeline marker in the rendered messages');
		pg_assert_true(stripos($html, '<dregion') === false, 'no live region tag in the rendered messages');
		pg_assert_true(strpos($html, 'E &lt;?php echo 1; ?>') !== false, 'the error is still shown');
		pg_assert_true(strpos($html, 'N <!-- pg-custom-php:') !== false, 'the notice is still shown');
		pg_assert_true(strpos($html, 'W &lt;dregion>x&lt;/dregion>') !== false, 'the warning is still shown');
	});
}

// The two places that print session messages into a page before it is run
// through PHP send every line through pg_defuse_page_message(): one
// concatenation each for errors, notices and warnings.
function test_page_message_raw_sinks_are_defused()
{
	$files = array(
		PG_FUNCTIONS_DIR . '/get_page_content.php',
		PG_FUNCTIONS_DIR . '/includes/fn/designer.php',
	);

	foreach ($files as $file) {
		$source = (string) file_get_contents($file);
		$name = basename($file);

		pg_assert_same(0, preg_match_all('/<p class="(?:error|notice|warning)">\'\s*\.\s*\$(?!.*pg_defuse_page_message)/', $source), $name . ': a message line printed without pg_defuse_page_message()');
		pg_assert_same(3, preg_match_all('/<p class="(?:error|notice|warning)">\'\s*\.\s*pg_defuse_page_message\(/', $source), $name . ': three defused message lines');
	}
}

// A refused upload's sentence names the file as the visitor sent it. A
// liveform message is HTML, so every door that keeps that sentence in a
// liveform escapes the name first (and with ENT_SUBSTITUTE, so a name that
// is not valid UTF-8 does not empty the whole sentence).
function test_page_message_upload_refusal_escapes_the_name_in_liveforms()
{
	$unescaped = array();
	$escaped = 0;

	foreach (array('add_comment.php', 'custom_form.php', 'add_submitted_form.php', 'edit_submitted_form.php') as $name) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $name);

		if (preg_match('/->(?:mark_error|add_error|add_notice|add_warning)\(\s*[^;]*pg_upload_blocked_message\(\s*\$_FILES/', $source)) {
			$unescaped[] = $name;
		}

		$escaped += preg_match_all('/->mark_error\([^;]*pg_upload_blocked_message\(htmlspecialchars\([^;]*ENT_QUOTES \| ENT_SUBSTITUTE/', $source);
	}

	pg_assert_same(array(), $unescaped, 'liveform doors that pass the raw file name to the message');
	pg_assert_same(4, $escaped, 'the four liveform doors escape the file name');
}

// A message is HTML; shown in a field's inline feedback (a text context) it is
// reduced to its text, read once, so the tags do not show and a double escape
// does not leave entities on screen.
function test_page_message_text_for_inline_feedback()
{
	pg_assert_same('Sorry, <script>x</script>.php was not accepted.', pg_liveform_message_text('Sorry, &lt;script&gt;x&lt;/script&gt;.php was not accepted.'));
	pg_assert_same('rock&roll\'s.php', pg_liveform_message_text('rock&amp;roll&#039;s.php'));
	pg_assert_same('sign in', pg_liveform_message_text('<a href="/login">sign in</a>'));
	pg_assert_same('', pg_liveform_message_text(null));
	pg_assert_same('', pg_liveform_message_text(''));
}

// The two inline-feedback consumers (output_field and the per-field JS map)
// take the message through pg_liveform_message_text(), not the raw stored
// string, so an escaped file name is not escaped a second time.
function test_page_message_inline_feedback_consumers_use_text_helper()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/liveform.class.php');

	pg_assert_same(0, preg_match('/htmlspecialchars\(\$session_field\[\x27error_message\x27\]/', $source), 'output_field escapes the raw message');
	pg_assert_same(0, preg_match('/\$field_errors_for_js\[\$field_name\] = \$field_data\[\x27error_message\x27\];/', $source), 'the JS map carries the raw message');
	pg_assert_true(strpos($source, 'pg_liveform_message_text($session_field[\'error_message\'])') !== false, 'output_field uses the text helper');
	pg_assert_true(strpos($source, 'pg_liveform_message_text($field_data[\'error_message\'])') !== false, 'the JS map uses the text helper');
}

// A posted field named after a message bucket does not reach that bucket:
// add_fields_to_session() keeps it out, so the store-time defuse is not
// bypassed and the page printers read only defused messages.
function test_page_message_reserved_request_keys_are_not_fields()
{
	_test_page_messages_with_session(function () {
		$saved_request = $_REQUEST;
		$_REQUEST = array(
			'warnings' => '<?php echo 1; ?>',
			'notices' => array('x' => '<cregion>a</cregion>'),
			'__prefill' => 'x',
			'_form_error_0' => 'y',
			'real_field' => 'kept',
		);

		try {
			$form = new liveform('reserved_keys_test');
			$form->add_fields_to_session();
			$stored = $_SESSION['software']['liveforms']['reserved_keys_test'][0];

			pg_assert_false(isset($stored['warnings']['value']), 'a posted "warnings" field is not stored');
			pg_assert_false(isset($stored['notices']['value']), 'a posted "notices" field is not stored');
			pg_assert_false(isset($stored['_form_error_0']['value']), 'a posted "_form_error_0" field is not stored');
			pg_assert_same('kept', $stored['real_field']['value'], 'an ordinary field is still stored');
			pg_assert_false(isset($stored['warnings'][0]), 'the warnings list stays empty');
		} finally {
			$_REQUEST = $saved_request;
		}
	});
}
