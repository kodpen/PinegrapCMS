<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the waiting rows, the texts' jobs and the mark of a translated
 * Design Canvas picture: the rows a page save writes for the pictures it
 * shows (with words, without, another host, a right-to-left language, a
 * binned folder), the job of a picture's texts and its guards, how the mark
 * is set and cleared, and the keeping of a language's picture with a user
 * id or 0.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of one function in a file under the software directory.
function picture_translation_auto_body($file, $function)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, "\nfunction " . $function . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", $start + 1);

	return substr($source, $start, ($end === false) ? strlen($source) : $end - $start);
}

// The site's hosts the tests read addresses against.
function picture_translation_auto_hosts()
{
	return array('example.com' => true, 'www.example.com' => true);
}

// A mark file of its own in the system's temporary folder.
function picture_translation_auto_mark_path()
{
	return rtrim(sys_get_temp_dir(), '/\\') . '/pg_picture_mark_test_' . getmypid() . '_' . mt_rand() . '.mark';
}

// A query that records each statement and answers $rows to a SELECT and
// $affected to anything else.
function picture_translation_auto_query(&$calls, $rows, $affected = 1)
{
	return function ($sql) use (&$calls, $rows, $affected) {
		$calls[] = $sql;

		return (strpos($sql, 'SELECT') === 0) ? $rows : $affected;
	};
}

// Only the languages written left to right are drawn; a code the site does
// not know is taken as left to right, as pg_picture_tr_rtl() takes it.
function test_picture_translation_auto_ltr_languages()
{
	$known = array(
		'de' => array('German', 'Deutsch', 'de_DE', '1.234,56', 'j.n.Y', 0),
		'ar' => array('Arabic', 'Arabic', 'ar_AR', '1,234.56', 'j/n/Y', 1),
		'he' => array('Hebrew', 'Hebrew', 'he_IL', '1,234.56', 'j/n/Y', '1'),
	);

	pg_assert_same(array('de', 'xx'), pg_picture_tr_ltr_languages(array('de', 'ar', 'he', 'xx'), $known), 'right to left left out');
	pg_assert_same(array(), pg_picture_tr_ltr_languages(array(), $known), 'no language');
}

// A page save writes a waiting row in every language for each picture it
// shows that has words, with one read and one insert.
function test_picture_translation_auto_save_rows_with_words()
{
	$calls = array();
	$query = picture_translation_auto_query($calls, array(array('id' => '7', 'folder' => '3')), 2);
	$html = '<img src="/banner.png"><img src="/photo.jpg"><div style="background:url(/banner.png?w=960)"></div>';

	$saved = pg_picture_tr_save_rows($html, array('de', 'fr'), '/', picture_translation_auto_hosts(), $query);

	pg_assert_same(array(7), $saved['files'], 'the picture with words');
	pg_assert_same(2, $saved['inserted'], 'the rows the insert wrote');
	pg_assert_same(2, count($calls), 'one read and one insert');
	pg_assert_contains("f.name IN ('banner.png', 'photo.jpg')", $calls[0], 'every picture the page names, once');
	pg_assert_contains('INSERT IGNORE INTO file_translation', $calls[1], 'a row already there stays as it is');
	pg_assert_contains("('7', 'de', 0,", $calls[1], 'waiting in German');
	pg_assert_contains("('7', 'fr', 0,", $calls[1], 'waiting in French');
}

// A picture with no words (a photo, a picture of no Design Canvas) gets no
// row: the read finds only files with texts in the translation.
function test_picture_translation_auto_save_rows_without_words()
{
	$calls = array();
	$query = picture_translation_auto_query($calls, array());

	$saved = pg_picture_tr_save_rows('<img src="/photo.jpg">', array('de'), '/', picture_translation_auto_hosts(), $query);

	pg_assert_same(array(), $saved['files'], 'no picture');
	pg_assert_same(0, $saved['inserted'], 'nothing written');
	pg_assert_same(1, count($calls), 'the read only');

	$sql = pg_picture_tr_save_sql(array('photo.jpg', "o'neil.png"));
	pg_assert_contains("u.owner_type = 'file' AND u.owner_id = f.id", $sql, 'only a file with words');
	pg_assert_contains("'o\\'neil.png'", $sql, 'names escaped');
	pg_assert_false(strpos($sql, 'file_versions') !== false, 'no document read');
}

// A picture of another host, a page with no picture, a right-to-left
// language only and a binned folder write nothing.
function test_picture_translation_auto_save_rows_left_out()
{
	$calls = array();
	$query = picture_translation_auto_query($calls, array(array('id' => '7', 'folder' => '3')));
	$hosts = picture_translation_auto_hosts();

	$saved = pg_picture_tr_save_rows('<img src="https://cdn.other.com/banner.png">', array('de'), '/', $hosts, $query);
	pg_assert_same(array(), $saved['files'], 'another host');
	pg_assert_same(0, count($calls), 'no query for another host');

	$saved = pg_picture_tr_save_rows('<p>No picture</p>', array('de'), '/', $hosts, $query);
	pg_assert_same(0, count($calls), 'no query for a page with no picture');

	$known = array('ar' => array('Arabic', 'Arabic', 'ar_AR', '1,234.56', 'j/n/Y', 1));
	$saved = pg_picture_tr_save_rows('<img src="/banner.png">', pg_picture_tr_ltr_languages(array('ar'), $known), '/', $hosts, $query);
	pg_assert_same(array(), $saved['files'], 'a right-to-left language only');
	pg_assert_same(0, count($calls), 'no query without a language drawn');

	$saved = pg_picture_tr_save_rows('<img src="/banner.png">', array('de'), '/', $hosts, $query, function ($folder) {
		return $folder === 3;
	});
	pg_assert_same(array(), $saved['files'], 'a picture in the Recycle Bin');
	pg_assert_same(1, count($calls), 'read, nothing inserted');
}

// The visual editor's save calls it with the page's own code, its address
// placeholders filled in, after the page's translate-on-save job; the first
// visit still writes the rows.
function test_picture_translation_auto_save_hooked()
{
	$save = picture_translation_auto_body('includes/fn/designer.php', 'pg_designer_save_page');
	$job = strpos($save, 'pg_tr_auto_update_page(');
	$rows = strpos($save, 'pg_picture_tr_page_saved(');

	pg_assert_true($rows !== false, 'called');
	pg_assert_true($rows > $job, 'after the page\'s own job');
	pg_assert_contains('pg_designer_resolve_output_placeholders($tree_code', $save, 'the page\'s code with its addresses filled in');

	$visit = picture_translation_auto_body('includes/fn/picture_translation.php', 'pg_picture_tr_page_apply');
	pg_assert_contains('INSERT IGNORE INTO file_translation', $visit, 'the first visit still writes them');
}

// A picture's texts are a job of their own scope: its owner only, and no
// extraction of the whole site.
function test_picture_translation_auto_job_scope()
{
	pg_tr_load();

	pg_assert_same(array(array('file', 12)), pg_tr_scope_owners('file:12'), 'the picture\'s texts');
	pg_assert_same("(u.owner_type = 'file' AND u.owner_id = '12')", pg_tr_owner_where(pg_tr_scope_owners('file:12')), 'one owner');
	pg_assert_same(array('pages' => 0, 'segments' => 0), pg_tr_scope_extract('file:12'), 'the saves keep the texts: nothing is extracted');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/translations.php');
	pg_assert_contains("preg_match('/^file:\\d+$/'", $screen, 'the jobs list names such a job');
}

// The job is opened only for a signed-in user, by the page's own lock and
// open-job guard, and only for the languages set to translate on save.
function test_picture_translation_auto_job_guards()
{
	pg_assert_same(0, pg_picture_tr_texts_jobs(array(12), 0), 'never without a signed-in user');
	pg_assert_same(0, pg_picture_tr_texts_jobs(array(), 5), 'no picture');

	$jobs = picture_translation_auto_body('includes/fn/picture_translation.php', 'pg_picture_tr_texts_jobs');
	pg_assert_contains("pg_tr_auto_update(\$auto, 'file:'", $jobs, 'the lock and the open-job guard of translate on save');
	pg_assert_contains('pg_picture_tr_auto_languages()', $jobs, 'the languages set to translate on save');

	$languages = picture_translation_auto_body('includes/fn/picture_translation.php', 'pg_picture_tr_auto_languages');
	pg_assert_contains('pg_tr_auto_languages()', $languages, 'translate on save with a ready engine');
	pg_assert_contains('pg_picture_tr_ltr_languages(', $languages, 'never a right-to-left language');

	$visit = picture_translation_auto_body('includes/fn/picture_translation.php', 'pg_picture_tr_page_pass');
	pg_assert_false(strpos($visit, 'pg_picture_tr_texts_jobs(') !== false, 'never from a visit');

	$sql = pg_picture_tr_pending_files_sql(array(4, 9, 4), array('de', 'fr'));
	pg_assert_contains('u.owner_id IN (4, 9)', $sql, 'the pictures, once each');
	pg_assert_contains("t.language IN ('de', 'fr')", $sql, 'the languages');
	pg_assert_contains('< 2', $sql, 'a text untranslated in one of them');
	pg_assert_same('', pg_picture_tr_pending_files_sql(array(), array('de')), 'no picture, no query');
	pg_assert_same('', pg_picture_tr_pending_files_sql(array(4), array()), 'no language, no query');
}

// A save of a picture with a row opens its own job and sets the mark; each
// save door hands its user over.
function test_picture_translation_auto_canvas_save()
{
	$saved = picture_translation_auto_body('includes/fn/picture_translation.php', 'pg_picture_tr_saved');
	pg_assert_contains('pg_picture_tr_texts_jobs(array($file_id), $user_id)', $saved, 'a job of its own');
	pg_assert_contains('pg_picture_tr_mark_set()', $saved, 'the mark');

	$editor = array(
		'pg_file_versions_after_save'    => '$user_id)',
		'pg_image_editor_canvas_keep'    => '$user_id)',
		'pg_image_editor_action_restore' => '$user[\'id\'])',
	);

	foreach ($editor as $function => $user) {
		$body = picture_translation_auto_body('includes/fn/image_editor.php', $function);
		pg_assert_true((bool) preg_match('/pg_picture_tr_saved\([^;]*' . preg_quote($user, '/') . ';/', $body), $function . ' hands over the user');
	}
}

// The mark is set, read and cleared without a query; a clear does not drop
// a mark set again meanwhile.
function test_picture_translation_auto_mark_moves()
{
	$path = picture_translation_auto_mark_path();

	pg_assert_false(pg_picture_tr_marked($path), 'clear at first');
	pg_assert_same('', pg_picture_tr_mark_token($path), 'no token');

	pg_picture_tr_mark_set($path);
	$token = pg_picture_tr_mark_token($path);
	pg_assert_true(pg_picture_tr_marked($path), 'set');
	pg_assert_true($token !== '', 'a token');

	pg_assert_false(pg_picture_tr_mark_clear('other', $path), 'a token read before another set');
	pg_assert_true(pg_picture_tr_marked($path), 'still set');
	pg_assert_true(pg_picture_tr_mark_clear($token, $path), 'cleared');
	pg_assert_false(pg_picture_tr_marked($path), 'clear');

	pg_assert_true(pg_picture_tr_marked(rtrim(sys_get_temp_dir(), '/\\') . '/pg-no-such-folder-' . mt_rand() . '/picture.mark'), 'set where it cannot be kept, so nothing waits unseen');
	pg_assert_contains('/data/temp/picture_draw_', pg_picture_tr_mark_path(), 'kept in data/temp');

	@unlink($path);
}

// The queue's query decides: a row due sets the mark, none clears it, a row
// that came meanwhile keeps it.
function test_picture_translation_auto_mark_check()
{
	$path = picture_translation_auto_mark_path();
	$calls = array();

	pg_assert_true(pg_picture_tr_mark_check_with(array('de'), picture_translation_auto_query($calls, array(array('file_id' => '7'))), $path), 'a row is due');
	pg_assert_true(pg_picture_tr_marked($path), 'the mark set');
	pg_assert_contains('LIMIT 1', $calls[0], 'one row is enough');
	pg_assert_contains('ft.state IN (0, 2)', $calls[0], 'the queue\'s query');

	pg_assert_false(pg_picture_tr_mark_check_with(array('de'), picture_translation_auto_query($calls, array()), $path), 'nothing due');
	pg_assert_false(pg_picture_tr_marked($path), 'the mark cleared');

	pg_picture_tr_mark_set($path);
	$meanwhile = function ($sql) use ($path) {
		pg_picture_tr_mark_set($path);

		return array();
	};
	pg_picture_tr_mark_check_with(array('de'), $meanwhile, $path);
	pg_assert_true(pg_picture_tr_marked($path), 'a mark set while the query ran stays');

	$calls = array();
	pg_assert_false(pg_picture_tr_mark_check_with(array(), picture_translation_auto_query($calls, array()), $path), 'no language drawn');
	pg_assert_same(0, count($calls), 'no query');
	pg_assert_false(pg_picture_tr_marked($path), 'cleared');

	@unlink($path);
}

// Where the mark is set and cleared: a first visit's waiting row, a save, a
// translation written; the queue and the daily sweep clear it.
function test_picture_translation_auto_mark_places()
{
	$marks = 0;
	$mark = function () use (&$marks) {
		$marks++;
	};
	$calls = array();
	$query = picture_translation_auto_query($calls, array(array('id' => '4', 'name' => 'new.jpg', 'folder' => '0', 'state' => null, 'picture' => null, 'has_uses' => '1')));

	pg_picture_tr_page_apply('<img src="/new.jpg">', 'de', '/', picture_translation_auto_hosts(), false, $query, null, $mark);
	pg_assert_same(1, $marks, 'a first visit\'s waiting row');

	$query = picture_translation_auto_query($calls, array(array('id' => '1', 'name' => 'banner.jpg', 'folder' => '0', 'state' => '1', 'picture' => 'banner-de.jpg', 'has_uses' => '1')));
	pg_picture_tr_page_apply('<img src="/banner.jpg">', 'de', '/', picture_translation_auto_hosts(), false, $query, null, $mark);
	pg_assert_same(1, $marks, 'a drawn picture sets nothing');

	$pass = picture_translation_auto_body('includes/fn/picture_translation.php', 'pg_picture_tr_page_pass');
	pg_assert_contains("'pg_picture_tr_mark_set'", $pass, 'the page pass hands the mark over');

	$saved = picture_translation_auto_body('includes/fn/picture_translation.php', 'pg_picture_tr_page_saved');
	pg_assert_contains('pg_picture_tr_mark_set()', $saved, 'a page save\'s waiting rows');

	$written = picture_translation_auto_body('includes/fn/picture_translation.php', 'pg_picture_tr_translation_written');
	pg_assert_contains('!pg_picture_tr_marked()', $written, 'no query while it is set');
	pg_assert_contains('pg_picture_tr_drawable_use_sql(', $written, 'a row this text may make drawable');
	pg_assert_contains('pg_picture_tr_mark_set()', $written, 'a translation written');

	$sql = pg_picture_tr_drawable_use_sql(5, "d'e");
	pg_assert_contains("u.string_id = '5'", $sql, 'the string');
	pg_assert_contains("ft.language = 'd\\'e'", $sql, 'the language, escaped');
	pg_assert_contains('ft.state IN (0, 2)', $sql, 'a row waiting or out of date');

	$queue = picture_translation_auto_body('includes/fn/picture_translation.php', 'pg_picture_tr_queue');
	pg_assert_contains('pg_picture_tr_mark_clear(', $queue, 'the queue clears it when it finds nothing');

	$prune = picture_translation_auto_body('includes/fn/picture_translation.php', 'pg_picture_tr_prune');
	pg_assert_contains('pg_picture_tr_mark_check()', $prune, 'the daily sweep sets or clears it by the queue');
}

// A language's picture is kept with a user's id or 0 (the server): the
// files row, the upsert with drawn_by, the page cache.
function test_picture_translation_auto_keep()
{
	list($sql, $params) = pg_picture_tr_keep_query(12, 'de', 40, 1, 'abc', 1000, 0);

	pg_assert_contains('ON DUPLICATE KEY UPDATE', $sql, 'an upsert');
	pg_assert_contains('state = 1', $sql, 'drawn');
	pg_assert_contains('drawn_by = VALUES(drawn_by)', $sql, 'by whom');
	pg_assert_same(array(12, 'de', 40, 1, 'abc', 1000, 1000, 0), $params, 'drawn by the server');

	list(, $params) = pg_picture_tr_keep_query('12', 'de', '40', 0, 'abc', 1000, '7');
	pg_assert_same(array(12, 'de', 40, 0, 'abc', 1000, 1000, 7), $params, 'drawn by a user');

	$keep = picture_translation_auto_body('includes/fn/picture_translation.php', 'pg_picture_tr_keep_write');
	pg_assert_contains("array('id' => (int) \$user_id)", $keep, 'the files row by the same user, or 0');
	pg_assert_contains('pg_picture_tr_keep_query(', $keep, 'the upsert');
	pg_assert_contains('pg_page_cache_mark_dirty()', $keep, 'the page cache');

	$answer = picture_translation_auto_body('includes/fn/picture_translation.php', 'pg_picture_tr_draw_answer');
	pg_assert_contains("pg_picture_tr_keep(\$file['row'], \$language, \$drawn, ", $answer, 'the browser\'s draw keeps through it');
	pg_assert_contains("(int) \$user['id']", $answer, 'with the user');
	pg_assert_false(strpos($answer, 'INSERT INTO file_translation') !== false, 'no second upsert');
}

// Two draws of one picture and language at once (a panel tab and the
// server's job) are kept one after the other under a lock, so the second
// writes over the first's files row instead of leaving a second one
// visible in Files.
function test_picture_translation_auto_keep_lock()
{
	$sql = pg_picture_tr_keep_lock_sql(12, 'de');

	pg_assert_contains('DATABASE()', $sql, 'the lock is this database\'s');
	pg_assert_same($sql, pg_picture_tr_keep_lock_sql('12', 'de'), 'one name per picture and language');
	pg_assert_true($sql !== pg_picture_tr_keep_lock_sql(12, 'fr'), 'another language, another lock');
	pg_assert_true($sql !== pg_picture_tr_keep_lock_sql(13, 'de'), 'another picture, another lock');
	pg_assert_false(strpos($sql, "'de'") !== false, 'the language is hashed, not quoted into the SQL');

	$keep = picture_translation_auto_body('includes/fn/picture_translation.php', 'pg_picture_tr_keep');
	$get = strpos($keep, 'GET_LOCK(');
	$write = strpos($keep, 'pg_picture_tr_keep_write(');
	$release = strpos($keep, 'RELEASE_LOCK(');

	pg_assert_true(($get !== false) && ($write !== false) && ($release !== false) && ($get < $write) && ($write < $release), 'locked, written, released');
}
