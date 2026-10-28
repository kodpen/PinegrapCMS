<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the two profiles of the rich-text filter
 * (includes/fn/editor.php, pg_sanitize_rich_text()): the visitor profile on
 * the site drops classes, layout styles and outside images and marks links
 * as user content; the staff profile keeps today's allow-list; and the
 * panel screens that call it pass the staff profile.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The default is the visitor profile; the staff profile keeps class and style.
function test_rich_text_profiles_class()
{
	$in = '<p class="fixed-top" style="color:red">x</p>';
	pg_assert_same('<p style="color:red">x</p>', pg_sanitize_rich_text($in));
	pg_assert_same('<p style="color:red">x</p>', pg_sanitize_rich_text($in, 'visitor'));
	pg_assert_same($in, pg_sanitize_rich_text($in, 'staff'));
	pg_assert_same('<p style="color:red">x</p>', pg_sanitize_rich_text($in, 'unknown'), 'an unknown profile is the visitor one');
}

// Only formatting properties stay for a visitor; layout and overlays go.
function test_rich_text_profiles_style()
{
	$in = '<div style="position:fixed; top:0; left:0; width:100vw; height:100vh; z-index:9999; color:#c00; text-align:center">x</div>';
	pg_assert_same('<div style="color:#c00; text-align:center">x</div>', pg_sanitize_rich_text($in));
	pg_assert_same($in, pg_sanitize_rich_text($in, 'staff'));

	pg_assert_same('<p style="margin-left:40px">x</p>', pg_sanitize_rich_text('<p style="margin-left:40px">x</p>'), 'editor indent');
	pg_assert_same('<p>x</p>', pg_sanitize_rich_text('<p style="margin-top:-500px">x</p>'), 'negative margin');
	pg_assert_same('<p>x</p>', pg_sanitize_rich_text('<p style="margin-left:-9em">x</p>'), 'negative indent');
	pg_assert_same('<p>x</p>', pg_sanitize_rich_text('<p style="display:none">x</p>'));
	pg_assert_same('<span style="font-family:Arial, sans-serif; background-color:rgb(1, 2, 3)">x</span>',
		pg_sanitize_rich_text('<span style="font-family:Arial, sans-serif;background-color:rgb(1, 2, 3)">x</span>'));
	pg_assert_same('<span>x</span>', pg_sanitize_rich_text('<span style="color:red;background:url(javascript:a())">x</span>'), 'unsafe style as a whole');
}

// Visitor links are marked as user content whatever rel they brought.
function test_rich_text_profiles_links()
{
	$out = pg_sanitize_rich_text('<a href="https://example.com/" rel="author" target="_blank">x</a>');
	pg_assert_same('<a href="https://example.com/" target="_blank" rel="nofollow ugc noopener">x</a>', $out);
	pg_assert_same('<a href="https://example.com/" rel="author">x</a>', pg_sanitize_rich_text('<a href="https://example.com/" rel="author">x</a>', 'staff'));
	pg_assert_same('<a href="{path}a" rel="nofollow ugc noopener">x</a>', pg_sanitize_rich_text('<a href="{path}a">x</a>'));
}

// A visitor image stays only on a relative address or on the site's host.
function test_rich_text_profiles_images()
{
	foreach (array('/files/a.png', 'a.png', '{path}a.png', 'https://www.example.org/files/a.png', 'HTTP://WWW.EXAMPLE.ORG:8080/a.png') as $src) {
		pg_assert_true(_pg_rich_text_image_is_local($src, 'www.example.org'), $src);
	}
	foreach (array('', '//evil.test/a.png', 'https://evil.test/a.png', 'https://www.example.org.evil.test/a.png', 'https://evil.test@www.example.org/a.png', 'ftp://www.example.org/a.png', '/\\evil.test/a.png') as $src) {
		pg_assert_false(_pg_rich_text_image_is_local($src, 'www.example.org'), $src);
	}
	pg_assert_false(_pg_rich_text_image_is_local('https://www.example.org/a.png', ''), 'no host set');

	pg_assert_same('<p>a</p>', pg_sanitize_rich_text('<p>a<img src="https://evil.test/t.gif"></p>'));
	pg_assert_same('<p>a<img src="/files/a.png" alt="b"></p>', pg_sanitize_rich_text('<p>a<img src="/files/a.png" alt="b"></p>'));
	pg_assert_same('<p>a<img src="https://evil.test/t.gif"></p>', pg_sanitize_rich_text('<p>a<img src="https://evil.test/t.gif"></p>', 'staff'));
}

// The staff profile is the shared allow-list: what is unsafe for staff is unsafe for a visitor.
function test_rich_text_profiles_staff_still_filters()
{
	$out = pg_sanitize_rich_text('<p onclick="a()" style="background:url(x)">p</p><script>b()</script><a href="javascript:c()">l</a>', 'staff');
	pg_assert_same('<p>p</p><a>l</a>', $out);
}

// Panel screens and the API description pass the staff profile; the
// submitted form editor filters the stored value before it loads.
function test_rich_text_profiles_staff_callers()
{
	foreach (array('edit_field.php', 'view_fields.php', 'includes/api/resources/calendars.php') as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains(", 'staff')", $source, $file);
	}
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/edit_submitted_form.php');
	pg_assert_contains("prepare_rich_text_editor_content_for_output(pg_sanitize_rich_text(\$field['data'], 'staff'))", $source);
}
