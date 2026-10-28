<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Sample records for tools/screenshots/shoot.js on a sandbox instance
 * (tools/setup_sandbox.sh). The starter site already brings products,
 * product groups, offers and files; this adds what it lacks: a second
 * panel user and a Workspace channel with messages and tasks, and turns the
 * Workspace module on. Running it again changes nothing: every record is
 * found by its fixed name first. Prints the ids the screen paths use as one
 * JSON line, last.
 *
 * Writes to the database named in pinegrap/data/config.php: run it only on
 * a sandbox, never on a development machine or a live site.
 *
 * Usage:  php tools/screenshots/seed.php
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit('Forbidden');
}

$config_file = dirname(__DIR__, 2) . '/pinegrap/data/config.php';

if (!is_file($config_file)) {
	fwrite(STDERR, "No pinegrap/data/config.php: set up the instance with tools/setup_sandbox.sh first.\n");
	exit(1);
}

require $config_file;

// The sandbox database setup_sandbox.sh creates; anything else is refused so
// a stray run cannot write sample records into a real site.
if (DB_DATABASE !== 'pinegrap_sandbox' && getenv('PG_SCREENSHOT_SEED_ANY_DB') !== '1') {
	fwrite(STDERR, 'Refusing to seed ' . DB_DATABASE . ": not the sandbox database (set PG_SCREENSHOT_SEED_ANY_DB=1 to override).\n");
	exit(1);
}

mysqli_report(MYSQLI_REPORT_OFF);

$db = mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE);

if (!$db) {
	fwrite(STDERR, 'Database connection failed: ' . mysqli_connect_error() . "\n");
	exit(1);
}

mysqli_set_charset($db, 'utf8mb4');

function seed_query($sql)
{
	global $db;

	$result = mysqli_query($db, $sql);

	if ($result === false) {
		fwrite(STDERR, 'Query failed: ' . mysqli_error($db) . "\n" . $sql . "\n");
		exit(1);
	}

	return $result;
}

function seed_value($sql)
{
	$row = mysqli_fetch_row(seed_query($sql));
	return $row ? $row[0] : null;
}

function seed_escape($value)
{
	global $db;
	return mysqli_real_escape_string($db, (string) $value);
}

// Inserts a row from a column => value map and returns its id.
function seed_insert($table, $values)
{
	global $db;

	$columns = array();
	$escaped = array();

	foreach ($values as $column => $value) {
		$columns[] = '`' . $column . '`';
		$escaped[] = ($value === null) ? 'NULL' : "'" . seed_escape($value) . "'";
	}

	seed_query('INSERT INTO `' . $table . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $escaped) . ')');

	return (int) mysqli_insert_id($db);
}

$now = time();

$admin_id = (int) seed_value("SELECT user_id FROM user WHERE user_username = 'admin' LIMIT 1");

if (!$admin_id) {
	fwrite(STDERR, "No admin user: the instance was not set up with tools/setup_sandbox.sh.\n");
	exit(1);
}

// -- second panel user ----------------------------------------------------------

$user_id = (int) seed_value("SELECT user_id FROM user WHERE user_username = 'ayse.yilmaz' LIMIT 1");

if (!$user_id) {
	$user_id = seed_insert('user', array(
		'user_username' => 'ayse.yilmaz',
		'user_email' => 'ayse.yilmaz@example.test',
		'user_password' => password_hash('sandbox-user', PASSWORD_DEFAULT),
		// The hash's kind (pg_password_verify()): 2 is password_hash(); the
		// column's default 0 reads it as an MD5 and the account never signs in.
		'user_password_algo' => 2,
		'user_role' => 3,
		'user_timestamp' => $now,
	));
}

// -- Workspace -------------------------------------------------------------------

seed_query('UPDATE config SET workspace_enabled = 1');

$channel_id = (int) seed_value("SELECT id FROM ws_channels WHERE name = 'urun-lansmani' LIMIT 1");

if (!$channel_id) {
	$channel_id = seed_insert('ws_channels', array(
		'name' => 'urun-lansmani',
		'kind' => 'public',
		'topic' => 'Sonbahar koleksiyonunun lansman hazırlıkları',
		'owner_user_id' => $admin_id,
		'created_by' => $admin_id,
		'created_at' => $now - 86400 * 3,
	));

	foreach (array($admin_id => 'owner', $user_id => 'member') as $member => $role) {
		seed_insert('ws_channel_members', array(
			'channel_id' => $channel_id,
			'user_id' => $member,
			'role' => $role,
			'joined_at' => $now - 86400 * 3,
		));
	}

	$messages = array(
		array($admin_id, 'Lansman sayfasının ilk taslağı hazır, göz atabilir misin?', 7200),
		array($user_id, 'Baktım, ürün görsellerini yeniledim. Fiyat tablosunu yarın tamamlarım.', 5400),
		array($admin_id, 'Harika. Kampanya e-postasını da perşembe gönderelim.', 3600),
		array($user_id, 'Tamam, metni bugün akşama kadar paylaşırım.', 1800),
	);

	$last_id = 0;
	$last_at = 0;

	foreach ($messages as $message) {
		$last_at = $now - $message[2];
		$last_id = seed_insert('ws_messages', array(
			'channel_id' => $channel_id,
			'sender_kind' => 'user',
			'sender_id' => $message[0],
			'kind' => 'message',
			'body' => $message[1],
			'created_at' => $last_at,
			'touched_at' => $last_at,
		));
	}

	seed_query('UPDATE ws_channels SET last_message_id = ' . $last_id . ', last_message_at = ' . $last_at . ' WHERE id = ' . $channel_id);

	$tasks = array(
		array('Lansman sayfası metnini tamamla', 'doing', 'high', 2, $user_id),
		array('Ürün görsellerini optimize et', 'todo', 'normal', 4, $user_id),
		array('Kampanya e-postasını planla', 'todo', 'normal', 5, $admin_id),
		array('Fiyat listesini güncelle', 'done', 'low', -1, $admin_id),
	);

	foreach ($tasks as $task) {
		$task_id = seed_insert('ws_tasks', array(
			'title' => $task[0],
			'channel_id' => $channel_id,
			'creator_id' => $admin_id,
			'status' => $task[1],
			'priority' => $task[2],
			'due_date' => date('Y-m-d', $now + 86400 * $task[3]),
			'completed_at' => ($task[1] === 'done') ? $now - 3600 : 0,
			'completed_by' => ($task[1] === 'done') ? $task[4] : 0,
			'created_at' => $now - 86400 * 2,
			'updated_at' => $now - 3600,
		));

		seed_insert('ws_task_assignees', array(
			'task_id' => $task_id,
			'user_id' => $task[4],
			'assigned_by' => $admin_id,
			'assigned_at' => $now - 86400 * 2,
		));
	}
}

// -- records the starter site brings ----------------------------------------------

$product_id = (int) seed_value('SELECT id FROM products ORDER BY id LIMIT 1');
$offer_id = (int) seed_value('SELECT id FROM offers ORDER BY id LIMIT 1');

echo json_encode(array(
	'user_id' => $user_id,
	'channel_id' => $channel_id,
	'product_id' => $product_id,
	'offer_id' => $offer_id,
)) . "\n";
