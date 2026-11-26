<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Security Scan's test round: every rule against its planted case and
 * against nothing else; the files and content of a clean install (the
 * shipped tree, every design template, the upload types kept) give no
 * finding; a quarantine and its restore leave the file as it was; a walk
 * cut by the time limit resumes where it stopped; 50,000 files stay inside
 * the job's budget.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A new empty folder under the system temp folder.
function _test_scan_round_folder($name)
{
	$folder = sys_get_temp_dir() . '/pg_scan_round_' . $name . '_' . getmypid();
	_test_scan_round_remove($folder);
	mkdir($folder, 0777, true);

	return $folder;
}

// Remove a folder and everything in it.
function _test_scan_round_remove($folder)
{
	if (!is_dir($folder) || is_link($folder)) {
		return;
	}

	foreach (scandir($folder) as $name) {
		if (($name === '.') || ($name === '..')) {
			continue;
		}

		$path = $folder . '/' . $name;

		if (is_dir($path) && !is_link($path)) {
			_test_scan_round_remove($path);
		} else {
			unlink($path);
		}
	}

	rmdir($folder);
}

// Write files given as relative path => content under $root.
function _test_scan_round_plant($root, array $files)
{
	foreach ($files as $place => $content) {
		$path = $root . '/' . $place;

		if (!is_dir(dirname($path))) {
			mkdir(dirname($path), 0777, true);
		}

		file_put_contents($path, $content);
	}
}

// A whole walk of $roots, as "kind place" => severity.
function _test_scan_round_scan(array $roots, $places, $baseline)
{
	$state = pg_security_files_state(array('baseline' => $baseline));
	$found = array();
	$walk = pg_security_files_advance($state, $roots, $places, function () {
		return false;
	}, function (array $sighting) use (&$found) {
		$found[$sighting['kind'] . ' ' . $sighting['place']] = (int) $sighting['severity'];
	});
	pg_assert_false($walk['stopped']);
	ksort($found);

	return array('found' => $found, 'state' => $walk['state']);
}

// Every leaf string of a value.
function _test_scan_round_strings($value, array &$out)
{
	if (is_string($value)) {
		$out[] = $value;
	} elseif (is_array($value)) {
		foreach ($value as $item) {
			_test_scan_round_strings($item, $out);
		}
	}
}

function test_security_scan_round_each_file_rule_fires_on_its_case_only()
{
	$site = _test_scan_round_folder('site');
	$jpeg = "\xFF\xD8\xFF\xE0" . str_repeat("\x00\x10JFIF<?xml<?=", 64) . "\xFF\xD9";

	_test_scan_round_plant($site . '/software', array(
		'index.php' => '<?php echo 1;',
		'includes/fn/ok.php' => '<?php function ok() { return base64_decode("eA=="); }',
		'includes/.htaccess' => "deny from all\n",
		'data/.htaccess' => "Order deny,allow\nDeny from all\n",
		'data/temp/lang_tr.php' => '<?php return array();',
		'data/config.php' => '<?php define("DB_HOST", "x");',
		'assets/lib/tiny_mce_3_5_10/get_file_list.php' => '<?php echo 1;',
		// Planted
		'wp-shell.php' => '<?php echo "hello";',
		'includes/fn/cache.php' => '<?php @assert($_POST["c"]);',
		'.htaccess' => "AddHandler application/x-httpd-php .jpg\n",
	));
	_test_scan_round_plant($site . '/files', array(
		'photo.jpg' => $jpeg,
		'report.pdf' => '%PDF-1.4 <?php',
		'style-site.css' => 'body{}',
		// Planted
		'avatar.php.jpg' => $jpeg,
		'logo.gif' => "GIF89a<?php system(\$_GET['c']); ?>",
	));
	_test_scan_round_plant($site . '/layouts', array(
		'12.php' => '<?php echo $content; ?>',
		'12.bak.php' => '<?php echo $content; ?>',
		// Planted
		'x.php' => '<?php echo 1;',
	));

	$roots = array('' => $site . '/software', 'data/files' => $site . '/files', 'data/layouts' => $site . '/layouts');
	$reference = array('index.php' => true, 'includes/fn/ok.php' => true, 'includes/.htaccess' => true);
	$baseline = array('.htaccess' => sha1('# the operator\'s rules'), 'includes/.htaccess' => sha1('old'), 'data/.htaccess' => sha1('x'));
	$scan = _test_scan_round_scan($roots, $reference, $baseline);

	pg_assert_same(array(
		'file_php_unknown includes/fn/cache.php' => PG_SECURITY_SEVERITY_HIGH,
		'file_php_unknown wp-shell.php' => PG_SECURITY_SEVERITY_HIGH,
		'file_picture_php data/files/logo.gif' => PG_SECURITY_SEVERITY_HIGH,
		'file_server_config .htaccess' => PG_SECURITY_SEVERITY_MEDIUM,
		'file_shell_mark includes/fn/cache.php' => PG_SECURITY_SEVERITY_CRITICAL,
		'file_upload_executable data/files/avatar.php.jpg' => PG_SECURITY_SEVERITY_CRITICAL,
		'file_upload_executable data/layouts/x.php' => PG_SECURITY_SEVERITY_CRITICAL,
	), $scan['found'], 'each planted file, and only those');
	pg_assert_same(array('.htaccess', 'data/.htaccess', 'includes/.htaccess'), array_keys($scan['state']['configs']), 'every server settings file is in the next baseline');

	// The same site without a reference: only the rules that need none.
	$scan = _test_scan_round_scan($roots, null, $baseline);
	pg_assert_same(array(
		'file_picture_php data/files/logo.gif',
		'file_server_config .htaccess',
		'file_upload_executable data/files/avatar.php.jpg',
		'file_upload_executable data/layouts/x.php',
	), array_keys($scan['found']), 'root and includes/ PHP files are not judged without a reference');
	pg_assert_false(isset($scan['found']['file_shell_mark includes/fn/cache.php']), 'a file in the reference scope is not read without a reference');

	_test_scan_round_remove($site);
}

function test_security_scan_round_server_settings_that_only_deny()
{
	pg_assert_true(pg_security_config_denies_only('data/.htaccess', 'deny from all'));
	pg_assert_true(pg_security_config_denies_only('x/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\ndeny from all\n</IfModule>\n"), 'the quarantine folder');
	pg_assert_true(pg_security_config_denies_only('.htaccess', "# private\r\nOrder Allow,Deny\r\n  Deny   from all\r\nOptions -Indexes\r\n"));
	pg_assert_false(pg_security_config_denies_only('.htaccess', ''), 'empty denies nothing');
	pg_assert_false(pg_security_config_denies_only('.htaccess', "Options -Indexes\n"), 'no deny line');
	pg_assert_false(pg_security_config_denies_only('.htaccess', "deny from all\nAddHandler application/x-httpd-php .jpg"));
	pg_assert_false(pg_security_config_denies_only('.htaccess', "deny from all\nallow from 10.0.0.1"));
	pg_assert_false(pg_security_config_denies_only('.htaccess', "<IfModule mod_php.c>\nphp_value auto_prepend_file x\n</IfModule>\ndeny from all"));
	pg_assert_false(pg_security_config_denies_only('.user.ini', 'deny from all'), 'not an .htaccess');

	// Each folder this software makes for private data gets one of these,
	// after the first run took its baseline: no finding, new or changed.
	$site = _test_scan_round_folder('deny');
	_test_scan_round_plant($site, array(
		'data/workspace_mail/.htaccess' => "deny from all\n",
		'data/accountant/.htaccess' => "Require all denied\n",
		'includes/.htaccess' => "deny from all\n",
	));
	$scan = _test_scan_round_scan(array('' => $site), array(), array('includes/.htaccess' => sha1('older')));
	pg_assert_same(array(), $scan['found']);

	// The same file turned into a handler is reported.
	file_put_contents($site . '/data/accountant/.htaccess', "Require all denied\nSetHandler application/x-httpd-php\n");
	$scan = _test_scan_round_scan(array('' => $site), array(), array('includes/.htaccess' => sha1("deny from all\n")));
	pg_assert_same(array('file_server_config data/accountant/.htaccess' => PG_SECURITY_SEVERITY_MEDIUM), $scan['found']);
	_test_scan_round_remove($site);
}

function test_security_scan_round_named_exceptions()
{
	pg_assert_same(array(), pg_security_file_sightings('data/layouts/12.bak.php', null, array()), 'the 2017.2.2 step\'s layout backup');
	pg_assert_same(1, count(pg_security_file_sightings('data/layouts/12.bak.php.php', null, array())));
	pg_assert_same(1, count(pg_security_file_sightings('data/layouts/x.bak.php', null, array())));
	pg_assert_true(pg_security_php_known_outside_reference('data/config.tmp5f1c2a3b4d5e6.php'), 'config.php while it is written');
	pg_assert_false(pg_security_php_known_outside_reference('data/config.tmpshell.php'));
	pg_assert_false(pg_security_php_known_outside_reference('data/temp/config.tmp5f1c2a3b4d5e6.php'));
	pg_assert_same(1, preg_match('/^config\.tmp[0-9a-f]{13}\.php$/', 'config.' . uniqid('tmp') . '.php'), 'the shape pg_write_config_file() gives it');
}

function test_security_scan_round_the_shipped_tree_gives_no_finding()
{
	$software = PG_FUNCTIONS_DIR;
	$listed = array();
	exec('git -C ' . escapeshellarg($software) . ' ls-files -z . 2>/dev/null', $lines, $code);

	if (($code !== 0) || empty($lines)) {
		pg_assert_true(true, 'no git work tree: skipped');

		return;
	}

	foreach (explode("\0", implode("\n", $lines)) as $place) {
		if (($place !== '') && (strpos($place, 'data/backups/') !== 0)) {
			$listed[$place] = true;
		}
	}

	// The release reference lists every shipped file in its scope.
	$reference = array();

	foreach (array_keys($listed) as $place) {
		if (pg_security_place_in_reference_scope($place)) {
			$reference[$place] = true;
		}
	}

	foreach (array(null, array()) as $baseline) {
		$found = array();
		$files = 0;

		foreach (pg_security_files_roots() as $prefix => $folder) {
			$folder = ($prefix === '') ? $software : $software . '/' . $prefix;

			foreach (pg_security_files_walk($folder, $prefix, '') as $place => $path) {
				if (!isset($listed[$place])) {
					continue;
				}

				$files++;
				$check = pg_security_files_check($place, $path, pg_security_place_in_reference_scope($place) ? isset($reference[$place]) : null, $baseline);

				foreach ($check['sightings'] as $sighting) {
					$found[] = $sighting['kind'] . ' ' . $place;
				}
			}
		}

		pg_assert_true($files > 1000, 'the shipped tree was walked');
		pg_assert_same(array(), $found, ($baseline === null) ? 'a first run' : 'a run after an empty baseline');
	}
}

function test_security_scan_round_template_pictures_in_the_upload_folder()
{
	$checked = 0;

	foreach (glob(PG_FUNCTIONS_DIR . '/includes/design_templates/*/*') as $path) {
		$name = strtolower(basename($path));

		if (preg_match('/^[a-z0-9_-]+\.(jpg|jpeg|png|webp)$/', $name) !== 1) {
			continue;
		}

		// _pg_tpl_catalog_file() copies it in as store-<name>.
		$check = pg_security_files_check('data/files/store-' . $name, $path, null, array());
		pg_assert_same(array(), $check['sightings'], $name);
		$checked++;
	}

	pg_assert_true($checked > 0, 'template pictures found');
}

function test_security_scan_round_uploads_of_every_kept_type()
{
	$kept = array(
		'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'ico', 'bmp', 'tif', 'heic',
		'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'txt', 'csv', 'rtf', 'json', 'xml',
		'zip', 'gz', 'mp3', 'mp4', 'webm', 'mov', 'wav', 'ogg', 'css', 'js', 'woff', 'woff2', 'ttf', 'otf', 'eot', 'html', 'vcf', 'ics',
	);
	$names = array();

	foreach ($kept as $extension) {
		$names[] = 'Report 2026.' . $extension;
	}

	// What the uploader turns a refused name into is kept too.
	foreach (pg_blocked_upload_extensions() as $extension) {
		$names[] = 'shell.' . $extension;
		$names[] = 'shell.' . $extension . '.jpg';
	}

	foreach (pg_blocked_upload_names() as $name) {
		$names[] = $name;
	}

	foreach ($names as $name) {
		$stored = prepare_file_name($name);
		$place = 'data/files/' . $stored;
		pg_assert_same('', pg_security_name_executable($place), $name . ' kept as ' . $stored);
		pg_assert_false(pg_security_name_is_server_config($place), $stored);
		pg_assert_same(array(), pg_security_file_sightings($place, null, array()), $stored);

		// A custom form's upload goes under the same name in the same folder.
		pg_assert_same(array(), pg_security_file_sightings('data/files/' . $stored, null, array('php_tag' => false, 'shell' => '')), $stored);
	}
}

function test_security_scan_round_content_rules_fire_on_their_case_only()
{
	$known = pg_security_content_known_hosts('www.example.com', "script-src 'self' https://cdn.payments.example");
	$cases = array(
		'content_script_host' => '<p>x</p><script src="https://evil.example/a.js"></script>',
		'content_iframe_hidden' => '<iframe src="https://evil.example/x" width="1" height="1"></iframe>',
		'content_redirect' => '<meta http-equiv="refresh" content="0;url=https://evil.example/">',
	);

	foreach ($cases as $kind => $text) {
		pg_assert_same(array($kind => array('evil.example')), pg_security_content_rules($text, $known), $kind);
	}

	$clean = array(
		'<script src="https://www.example.com/a.js"></script><script src="/local.js"></script>',
		'<script src="https://example.com/a.js"></script>',
		'<script src="https://cdn.payments.example/v3/"></script>',
		'<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>',
		'<iframe src="https://www.google.com/maps/embed?pb=1" width="600" height="450" style="border:0;"></iframe>',
		'<iframe src="https://evil.example/" width="600" height="400"></iframe>',
		'<a href="https://evil.example/" onclick="location.href=\'https://evil.example/\'">x</a>',
		'&lt;script src="https://evil.example/a.js"&gt;',
		'<script>window.location = "/thanks";</script>',
		'<div style="overflow:hidden"><iframe src="https://www.youtube.com/embed/x" width="560" height="315"></iframe></div>',
	);

	foreach ($clean as $text) {
		pg_assert_same(array(), pg_security_content_rules($text, $known), $text);
	}
}

function test_security_scan_round_design_templates_give_no_content_finding()
{
	$known = pg_security_content_known_hosts('www.example.com', function_exists('waf_csp_policy') ? (string) waf_csp_policy() : '');
	$checked = 0;

	foreach (glob(PG_FUNCTIONS_DIR . '/includes/design_templates/*.php') as $file) {
		$template = _pg_tpl_include($file);
		$strings = array();
		_test_scan_round_strings($template, $strings);

		// Publish stores the trees as JSON, so the encoded whole is read too.
		$strings[] = json_encode($template);
		$found = array();

		foreach ($strings as $text) {
			foreach (pg_security_content_rules($text, $known) as $kind => $hosts) {
				$found[] = $kind . ' ' . implode(', ', $hosts);
			}
		}

		pg_assert_same(array(), $found, basename($file));
		$checked++;
	}

	pg_assert_true($checked >= 10, 'every template checked');
}

function test_security_scan_round_quarantine_and_restore_leave_the_file_as_before()
{
	$place = 'data/temp/pg_scan_round_' . getmypid() . '.php';
	$path = pg_security_place_disk_path($place);
	$content = '<?php @eval(base64_decode("ZWNobyAxOw=="));';
	$folder = PG_FUNCTIONS_DIR . '/' . PG_SECURITY_QUARANTINE_FOLDER;
	$made_folder = !is_dir($folder);

	file_put_contents($path, $content);
	chmod($path, 0640);
	touch($path, 1700000000);
	clearstatcache();
	$before = array(sha1_file($path), fileperms($path), filemtime($path));
	$row = array('kind' => 'file_shell_mark', 'place' => $place);

	pg_assert_same('', pg_security_quarantine_move($row, 'quarantine'));
	clearstatcache();
	pg_assert_false(file_exists($path), 'moved away');
	$kept = $folder . '/' . pg_security_quarantine_name($place);
	pg_assert_same($content, file_get_contents($kept));
	pg_assert_true(pg_security_config_denies_only($folder . '/.htaccess', file_get_contents($folder . '/.htaccess')), 'the folder answers nothing and is no finding itself');
	pg_assert_true(pg_security_walk_skipped(PG_SECURITY_QUARANTINE_FOLDER), 'the walk does not enter it');

	pg_assert_same('', pg_security_quarantine_move($row, 'restore'));
	clearstatcache();
	pg_assert_same($before, array(sha1_file($path), fileperms($path), filemtime($path)), 'content, mode and time as before');
	pg_assert_false(file_exists($kept));

	// Restore never writes over a file that came back.
	pg_assert_same('', pg_security_quarantine_move($row, 'quarantine'));
	file_put_contents($path, 'new');
	pg_assert_true(pg_security_quarantine_move($row, 'restore') !== '');
	pg_assert_same('new', file_get_contents($path));

	@unlink($kept);
	@unlink($path);

	if ($made_folder) {
		@unlink($folder . '/.htaccess');
		@unlink($folder . '/index.html');
		@rmdir($folder);
	}
}

function test_security_scan_round_quarantine_never_goes_through_a_folder_turned_into_a_link()
{
	$outside = _test_scan_round_folder('link_target');
	$folder = PG_FUNCTIONS_DIR . '/' . PG_SECURITY_QUARANTINE_FOLDER;
	$made_folder = !is_dir($folder);
	$link = PG_FUNCTIONS_DIR . '/data/temp/pg_scan_link_' . getmypid();
	$place = 'data/temp/pg_scan_link_' . getmypid() . '/shell.php';
	$row = array('kind' => 'file_php_unknown', 'place' => $place);

	file_put_contents($outside . '/shell.php', '<?php echo 1;');
	@unlink($link);
	symlink($outside, $link);

	pg_assert_true(pg_security_quarantine_move($row, 'quarantine') !== '', 'a file reached through a link is not moved');
	clearstatcache();
	pg_assert_true(is_file($outside . '/shell.php'), 'the file outside the site stays');

	$kept = pg_security_quarantine_folder() . '/' . pg_security_quarantine_name($place);
	file_put_contents($kept, 'kept');
	unlink($outside . '/shell.php');
	pg_assert_true(pg_security_quarantine_move($row, 'restore') !== '', 'nothing is moved back through a link');
	clearstatcache();
	pg_assert_false(file_exists($outside . '/shell.php'));
	pg_assert_true(pg_security_place_folder_real('data/temp/x.php'), 'a real folder passes');

	@unlink($kept);
	unlink($link);
	_test_scan_round_remove($outside);

	if ($made_folder) {
		@unlink($folder . '/.htaccess');
		@unlink($folder . '/index.html');
		@rmdir($folder);
	}
}

function test_security_scan_round_a_second_quarantine_keeps_the_first_copy()
{
	$place = 'data/temp/pg_scan_twice_' . getmypid() . '.php';
	$path = pg_security_place_disk_path($place);
	$folder = PG_FUNCTIONS_DIR . '/' . PG_SECURITY_QUARANTINE_FOLDER;
	$made_folder = !is_dir($folder);
	$row = array('kind' => 'file_php_unknown', 'place' => $place);
	$name = pg_security_quarantine_name($place);

	file_put_contents($path, 'first');
	pg_assert_same('', pg_security_quarantine_move($row, 'quarantine'));
	file_put_contents($path, 'second');
	pg_assert_same('', pg_security_quarantine_move($row, 'quarantine'), 'the file planted again is moved too');
	pg_assert_same('second', file_get_contents($folder . '/' . $name));

	$earlier = glob($folder . '/' . substr($name, 0, 40) . '.*.quarantine');
	pg_assert_same(1, count($earlier), 'the first copy is kept beside it');
	pg_assert_same('first', file_get_contents($earlier[0]));

	@unlink($earlier[0]);
	@unlink($folder . '/' . $name);
	@unlink($path);

	if ($made_folder) {
		@unlink($folder . '/.htaccess');
		@unlink($folder . '/index.html');
		@rmdir($folder);
	}
}

function test_security_scan_round_a_cut_walk_resumes_where_it_stopped()
{
	$site = _test_scan_round_folder('resume');
	$files = array();

	foreach (array('', 'a', 'a/b', 'a/b/c', 'a.d', 'z') as $folder) {
		for ($i = 0; $i < 9; $i++) {
			$files[ltrim($folder . '/f' . $i . '.php', '/')] = '<?php echo ' . $i . ';';
		}
	}

	$files['up/x.php.jpg'] = 'x';
	_test_scan_round_plant($site . '/software', $files);
	_test_scan_round_plant($site . '/up', array('one.php' => 'x', 'two.jpg' => 'x'));
	$roots = array('' => $site . '/software', 'data/files' => $site . '/up');

	$whole = _test_scan_round_scan($roots, null, array());
	$all = array_keys($whole['found']);

	foreach (array(1, 4, 7, 50) as $every) {
		$state = pg_security_files_state(array('baseline' => array()));
		$found = array();
		$calls = 0;

		do {
			$asked = 0;
			$walk = pg_security_files_advance($state, $roots, null, function () use (&$asked, $every) {
				return $asked++ >= $every;
			}, function (array $sighting) use (&$found) {
				$found[] = $sighting['kind'] . ' ' . $sighting['place'];
			});
			$state = _test_scan_round_json($walk['state']);
			$calls++;
		} while ($walk['stopped'] && ($calls < 1000));

		sort($found);
		pg_assert_same($all, $found, 'cut every ' . $every . ' file(s): each file once');
		pg_assert_same(count($files) + 2, (int) $state['files'], 'every file counted once');
	}

	_test_scan_round_remove($site);
}

// The state as the config_text row gives it back.
function _test_scan_round_json(array $state)
{
	return pg_security_files_state(json_decode(json_encode($state), true));
}

function test_security_scan_round_a_folder_that_cannot_be_read_is_kept_out_of_the_close()
{
	pg_assert_true(pg_security_place_unread('data/files/a/x.php', array('data/files/a')));
	pg_assert_false(pg_security_place_unread('data/files/ab/x.php', array('data/files/a')), 'a folder is no prefix of a name');
	pg_assert_true(pg_security_place_unread('x.php', array('')), 'the software folder unread keeps every place');
	pg_assert_true(pg_security_place_unread('page:12', array('page')), 'a record type that could not be read');
	pg_assert_false(pg_security_place_unread('pregion:12', array('page')));
	pg_assert_false(pg_security_place_unread('x.php', array()));

	$site = _test_scan_round_folder('unread');
	_test_scan_round_plant($site . '/software', array('a/x.php' => '<?php', 'b.php' => '<?php'));
	chmod($site . '/software/a', 0);
	clearstatcache();

	// Only where the folder really cannot be read (not as root).
	if (@scandir($site . '/software/a') === false) {
		$walk = pg_security_files_advance(pg_security_files_state(array('baseline' => array())), array('' => $site . '/software'), null, function () {
			return false;
		}, function () {
		});
		pg_assert_same(array('a'), $walk['state']['unread']);
	}

	chmod($site . '/software/a', 0755);
	$walk = pg_security_files_advance(pg_security_files_state(array('baseline' => array())), array('' => $site . '/software', 'data/files' => $site . '/none'), null, function () {
		return false;
	}, function () {
	});
	pg_assert_same(array(), $walk['state']['unread'], 'a readable folder and an upload folder that is not there');
	_test_scan_round_remove($site);
}

function test_security_scan_round_a_file_finding_carries_the_file_digest()
{
	$site = _test_scan_round_folder('digest');
	_test_scan_round_plant($site, array('a.php' => 'x', '.htaccess' => 'AddHandler php-script .jpg'));

	$check = pg_security_files_check('data/files/a.php', $site . '/a.php', null, array());
	pg_assert_same('.php sha1:' . sha1('x'), $check['sightings'][0]['detail']);

	$check = pg_security_files_check('data/files/.htaccess', $site . '/.htaccess', null, array());
	pg_assert_same('new sha1:' . sha1('AddHandler php-script .jpg'), $check['sightings'][0]['detail']);

	_test_scan_round_remove($site);
}

function test_security_scan_round_fifty_thousand_files_inside_the_budget()
{
	// 10,000 files are walked and the time is scaled to 50,000: the walk is
	// one pass over the names, and making 50,000 files costs the test run
	// more than walking them.
	$site = _test_scan_round_folder('large');
	$count = 10000;

	for ($folder = 0; $folder < 100; $folder++) {
		$path = $site . '/files/' . sprintf('%03d', $folder);
		mkdir($path, 0777, true);

		for ($i = 0; $i < $count / 100; $i++) {
			// Mostly pictures and documents, as a media library is.
			touch($path . '/' . $i . (($i % 10) === 0 ? '.pdf' : '.jpg'));
		}
	}

	$budget = defined('SEO_ANALYZE_TIME_BUDGET') ? (int) SEO_ANALYZE_TIME_BUDGET : 120;
	$start = microtime(true);
	$memory = memory_get_usage();
	$walk = pg_security_files_advance(pg_security_files_state(array('baseline' => array())), array('data/files' => $site . '/files'), null, function () {
		return false;
	}, function () {
	});
	$fifty_thousand = (microtime(true) - $start) * 50000 / $count;

	pg_assert_false($walk['stopped']);
	pg_assert_same($count, $walk['files']);
	pg_assert_true($fifty_thousand < $budget / 4, sprintf('50,000 files in about %.1f s, a quarter of the %d s budget at most', $fifty_thousand, $budget));
	pg_assert_true(memory_get_usage() - $memory < 4 * 1048576, 'the walk keeps no list of the files');

	_test_scan_round_remove($site);
}
