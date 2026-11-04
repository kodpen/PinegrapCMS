<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source tests for the faces the Workspace scripts draw with avatar(): a
 * screen adds its own class beside Bootstrap's .avatar and never writes
 * className over it, since .avatar is what holds a photo to the face's size
 * (.avatar > .avatar-img). Without it a person's photo was drawn at its own
 * size, a 1080 px picture covering the messages under a check-in answer.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Each place in a workspace script where a face made by avatar() has its
// className written over: "file: variable". Pure.
function workspace_avatar_face_test_overwrites($file, $source)
{
	$found = array();

	preg_match_all('/\bvar\s+([A-Za-z_$][\w$]*)\s*=\s*(?:[A-Za-z_$][\w$]*\.)?avatar\(/', $source, $faces);

	foreach (array_unique($faces[1]) as $name) {
		if (preg_match('/(?<![\w$.])' . preg_quote($name, '/') . '\.className\s*=(?!=)/', $source)) {
			$found[] = $file . ': ' . $name;
		}
	}

	return $found;
}

function test_workspace_avatar_face_finder_reads_an_overwrite()
{
	pg_assert_same(array('x.js: face'), workspace_avatar_face_test_overwrites('x.js', "var face = h.avatar(answer);\nface.className = 'ws-checkin-face';"), 'a className written over is found');
	pg_assert_same(array(), workspace_avatar_face_test_overwrites('x.js', "var face = h.avatar(answer);\nface.classList.add('ws-checkin-face');"), 'a class added beside is fine');
	pg_assert_same(array(), workspace_avatar_face_test_overwrites('x.js', "var face = avatar(a);\nif (face.className === 'avatar') {}"), 'a comparison is no write');
}

function test_workspace_avatar_faces_keep_the_avatar_class()
{
	$files = glob(PG_FUNCTIONS_DIR . '/assets/js/workspace*.js');
	$found = array();

	pg_assert_true(count($files) > 5, 'the workspace scripts are found');

	foreach ($files as $path) {
		if (substr($path, -7) === '.min.js') {
			continue;
		}

		$found = array_merge($found, workspace_avatar_face_test_overwrites(basename($path), (string) file_get_contents($path)));
	}

	pg_assert_same(array(), $found, 'no avatar() face loses .avatar to a className write');
}

function test_workspace_checkin_face_sized_by_the_avatar_variable()
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');

	pg_assert_true((bool) preg_match('/\.ws-checkin-face \{[^}]*--bs-avatar-size:/', $css), 'the check-in answer face is sized through --bs-avatar-size');
}
