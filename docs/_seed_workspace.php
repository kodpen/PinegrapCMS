<?php
/**
 * Development helper: fills the Workspace of a development site with sample
 * content for screenshots - departments, channel groups, channels, messages
 * with mentions, record tags, tables, checklists and polls, decisions, tasks,
 * plan items, notes and a guest room. It writes through the module's own
 * functions (ws_channel_create, ws_message_send, ws_task_create, ...), so
 * inbox entries, refs and unread counts come out as they would in daily use;
 * only the timestamps are moved back afterwards so the screens show a team
 * that has been at work for two weeks.
 *
 * Runs once: a second call reports that the sample content is already there.
 * ?lang=en switches the panel language to English (?lang=tr back), for
 * screenshots of an English-speaking team.
 *
 * Not part of the product (the name is ignored by git). Administrator on a
 * development host only.
 */

include('init.php');

$user = validate_user();

header('Content-Type: text/plain; charset=utf-8');

$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$host = preg_replace('/:[0-9]+$/', '', $host);
$dev_host = in_array($host, array('localhost', '127.0.0.1', '::1'), true)
    || (substr($host, -5) === '.test') || (substr($host, -6) === '.local')
    || (strpos($host, 'dev.') === 0);

if (((int) $user['role'] !== 0) || !$dev_host) {
    http_response_code(403);
    exit('Bu araç yalnız geliştirme sitesinde, bir yönetici tarafından çalıştırılabilir.');
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

// ─── Panel language switch (optional, independent of the seeding) ─────────
if (isset($_GET['lang']) && in_array($_GET['lang'], array('en', 'tr'), true)) {
    db("UPDATE config SET software_language = '" . e($_GET['lang']) . "'");
    echo "config.software_language = " . $_GET['lang'] . "\n";
    if (!isset($_GET['seed'])) {
        exit;
    }
}

if (!function_exists('ws_ready') || !ws_ready()) {
    exit("Workspace is not switched on (Site Settings > Features > Enable Workspace).\n");
}

// ?wipe=1 empties every workspace table (channels, messages, tasks, plan
// items, notes, departments, groups, guests...) so the seed can run again.
// Development site only; the accounts and contacts stay.
if (isset($_GET['wipe'])) {
    $tables = (array) db_values("SHOW TABLES LIKE 'ws\\_%'");
    foreach ($tables as $table) {
        db("TRUNCATE TABLE `" . preg_replace('/[^a-z0-9_]/', '', (string) $table) . "`");
    }
    echo 'Wiped ' . count($tables) . " workspace tables.\n";
    if (!isset($_GET['seed'])) {
        exit;
    }
}

$seed_started = time();
$marker = 'autumn-catalog';

if (ws_channel_name_taken($marker)) {
    exit("Already seeded: the channel '" . $marker . "' exists. Nothing was changed.\n");
}

set_time_limit(300);
mb_internal_encoding('UTF-8');

// ─── Helpers ──────────────────────────────────────────────────────────────

$out = array();
function say($line) { echo $line . "\n"; @ob_flush(); @flush(); }

/** A timestamp N days ago at H:M, in the site's timezone. */
function at($days_ago, $hm) {
    list($h, $m) = explode(':', $hm);
    return mktime((int) $h, (int) $m, 0, (int) date('n'), (int) date('j') - (int) $days_ago, (int) date('Y'));
}
/** Y-m-d for N days from today (negative = past). */
function day($offset) {
    return date('Y-m-d', mktime(12, 0, 0, (int) date('n'), (int) date('j') + (int) $offset, (int) date('Y')));
}
/** Tokens. */
function U($id) { return '<@user:' . (int) $id . '>'; }
function T($type, $id) { return '<#' . $type . ':' . (int) $id . '>'; }

/** Sends a message as somebody and moves it back in time. */
function post($viewer, $channel, $body, $when, $options = array()) {
    $r = ws_message_send($viewer, $channel, $body, $options);
    if (!$r['ok']) { say('  ! message failed: ' . $r['error'] . ' — ' . mb_substr($body, 0, 60)); return 0; }
    $id = (int) $r['message_id'];
    $marked = in_array(($options['kind'] ?? 'message'), array('note', 'decision'), true);
    db("UPDATE ws_messages SET created_at = '" . (int) $when . "'" . ($marked ? ", marked_at = '" . (int) $when . "'" : '') . " WHERE id = '" . $id . "'");
    db("UPDATE ws_channels SET last_message_at = '" . (int) $when . "' WHERE id = '" . (int) $channel['id'] . "' AND last_message_at < '" . (int) $when . "'");
    // Inbox lines written for this message follow its time.
    if (function_exists('waf_table_has_column') && waf_table_has_column('ws_inbox', 'message_id')) {
        db("UPDATE ws_inbox SET created_at = '" . (int) $when . "' WHERE message_id = '" . $id . "'");
    }
    return $id;
}

/** Moves the lines the module wrote by itself since $since_id (joins, pins, poll results, completions) back to $when. */
function settle($since_id, $when) {
    global $seed_started;
    db("UPDATE ws_messages SET created_at = '" . (int) $when . "', marked_at = IF(marked_at > 0, '" . (int) $when . "', 0)
        WHERE id > '" . (int) $since_id . "' AND created_at >= '" . (int) $seed_started . "'");
    db("UPDATE ws_channels c SET c.last_message_at = (SELECT COALESCE(MAX(m.created_at), c.created_at) FROM ws_messages m WHERE m.channel_id = c.id)");
}
function last_id() { return (int) db_value("SELECT MAX(id) FROM ws_messages"); }

function react($viewer, $message_id, $emoji) {
    $m = ws_message($message_id);
    if ($m) { ws_reaction_set($viewer, $m, $emoji, 0, true); }
}

function decide($viewer, $channel, $body, $when) {
    return post($viewer, $channel, $body, $when, array('kind' => 'decision'));
}

function task($viewer, $data, $created) {
    $r = ws_task_create($viewer, $data);
    if (!$r['ok']) { say('  ! task failed: ' . $r['error'] . ' (' . $r['field'] . ') — ' . $data['title']); return 0; }
    $id = (int) $r['task_id'];
    db("UPDATE ws_tasks SET created_at = '" . (int) $created . "', updated_at = '" . (int) $created . "' WHERE id = '" . $id . "'");
    db("UPDATE ws_messages SET created_at = '" . (int) $created . "' WHERE task_id = '" . $id . "' AND kind = 'task'");
    return $id;
}

function finish($viewer, $task_id, $status, $when) {
    $t = ws_task($task_id);
    if (!$t) { return; }
    $since = last_id();
    $r = ws_task_set_status($viewer, $t, $status);
    if (!$r['ok']) { say('  ! status failed: ' . $r['error']); return; }
    settle($since, $when);
    db("UPDATE ws_tasks SET updated_at = '" . (int) $when . "'" . (($status === 'done') ? ", completed_at = '" . (int) $when . "'" : '') . " WHERE id = '" . (int) $task_id . "'");
}

function plan($viewer, $data) {
    $r = ws_event_save($viewer, $data);
    if (!$r['ok']) { say('  ! plan item failed: ' . $r['error'] . ' (' . $r['field'] . ') — ' . $data['title']); }
    return (int) ($r['event_id'] ?? 0);
}

// ─── The team ─────────────────────────────────────────────────────────────

$admin = ws_viewer($user);
$me = (int) $user['id'];

// Five sample staff accounts of their own, made through the product's own
// function with a random password nobody is told (they are never signed
// into; the seed writes in their name through the module's functions). The
// site's other accounts are left alone.
$staff = array(
    array('Ayşe', 'Demir', 'ayse', 2), array('Mehmet', 'Kaya', 'mehmet', 2), array('Zeynep', 'Arslan', 'zeynep', 2),
    array('Can', 'Yıldız', 'can', 2), array('Elif', 'Şahin', 'elif', 3),
);
$rows = array();
foreach ($staff as $s) {
    $row = db_item("SELECT u.user_id AS id, u.user_role AS role, u.user_username AS username, c.first_name, c.last_name
        FROM user u LEFT JOIN contacts c ON c.id = u.user_contact WHERE u.user_username = '" . e($s[2]) . "' LIMIT 1");
    if (!is_array($row)) {
        db("INSERT INTO contacts (first_name, last_name, company, user, timestamp)
            VALUES ('" . e($s[0]) . "', '" . e($s[1]) . "', 'Çam Ev & Yaşam', '" . $me . "', UNIX_TIMESTAMP())");
        $contact_id = (int) mysqli_insert_id(db::$con);
        $new_id = create_member_user(array(
            'email'      => $s[2] . '@example.com',
            'username'   => $s[2],
            'password'   => bin2hex(random_bytes(24)),
            'contact_id' => $contact_id,
            'role'       => (string) $s[3],
        ));
        say('Created sample account: ' . $s[0] . ' ' . $s[1] . ' (' . $s[2] . ', role ' . $s[3] . ')');
        $row = array('id' => (int) $new_id, 'role' => $s[3], 'username' => $s[2], 'first_name' => $s[0], 'last_name' => $s[1]);
    }
    $rows[] = $row;
}

// After a wipe, only the sample team is in the Workspace: the rights an
// earlier run gave to other User accounts are taken back.
if (isset($_GET['wipe'])) {
    db("UPDATE user SET manage_workspace = 0, manage_workspace_assign = 0, manage_workspace_board = 0, manage_workspace_settings = 0
        WHERE user_role >= 3 AND user_username NOT IN ('ayse', 'mehmet', 'zeynep', 'can', 'elif')");
}

// Users (role 3) join the team through their Workspace rights.
foreach ($rows as $row) {
    if ((int) $row['role'] >= 3) {
        db("UPDATE user SET manage_workspace = 1, manage_workspace_assign = 1, manage_workspace_board = 1
            WHERE user_id = '" . (int) $row['id'] . "'");
    }
    db("DELETE FROM ws_profiles WHERE user_id = '" . (int) $row['id'] . "'");
}

$ids = array_map(function ($r) { return (int) $r['id']; }, $rows);
$p = array_pad($ids, 5, $ids[count($ids) - 1]);   // p[0]..p[4], repeating the last when fewer
$v = array();
foreach (array_unique($p) as $id) {
    $v[$id] = ws_viewer((array) pg_load_user_row($id));
}
$V = function ($i) use ($p, $v) { return $v[$p[$i]]; };

say('Team: admin #' . $me . ' + ' . implode(', ', array_map(function ($r) { return '#' . $r['id'] . ' ' . trim($r['first_name'] . ' ' . $r['last_name']) . ' (' . $r['username'] . ', role ' . $r['role'] . ')'; }, $rows)));

// Titles and working hours.
$titles = array($me => 'Owner', $p[0] => 'Sales lead', $p[1] => 'Designer', $p[2] => 'Customer support', $p[3] => 'Warehouse', $p[4] => 'Marketing');
foreach ($titles as $uid => $title) {
    $day = ($uid === $p[2]) ? 360 : 0;   // support works six-hour days
    db("INSERT INTO ws_profiles (user_id, title, day_minutes, workdays, excluded, updated_at)
        VALUES ('" . (int) $uid . "', '" . e($title) . "', '" . $day . "', 0, 0, '" . time() . "')
        ON DUPLICATE KEY UPDATE title = VALUES(title), day_minutes = VALUES(day_minutes), excluded = 0");
}

// Departments.
$departments = array(
    'Sales'      => array('#0d6efd', array($p[0], $p[2]), array($p[0])),
    'Production' => array('#d63384', array($p[1], $me), array($p[1])),
    'Warehouse'  => array('#198754', array($p[3]), array($p[3])),
    'Marketing'  => array('#fd7e14', array($p[4]), array($p[4])),
);
$dept = array();
$sort = (int) db_value("SELECT MAX(sort) FROM ws_departments");
foreach ($departments as $name => $spec) {
    $existing = (int) db_value("SELECT id FROM ws_departments WHERE name = '" . e($name) . "' AND archived = 0");
    if ($existing > 0) { $dept[$name] = $existing; continue; }
    $sort++;
    db("INSERT INTO ws_departments (name, color, sort, created_at) VALUES ('" . e($name) . "', '" . $spec[0] . "', '" . $sort . "', '" . time() . "')");
    $dept[$name] = (int) mysqli_insert_id(db::$con);
    foreach (array_unique($spec[1]) as $uid) {
        $primary = ((int) db_value("SELECT COUNT(*) FROM ws_department_members WHERE user_id = '" . (int) $uid . "' AND is_primary = 1") === 0) ? 1 : 0;
        db("INSERT IGNORE INTO ws_department_members (department_id, user_id, is_lead, is_primary)
            VALUES ('" . $dept[$name] . "', '" . (int) $uid . "', '" . (in_array($uid, $spec[2], true) ? 1 : 0) . "', '" . $primary . "')");
    }
}
say('Departments: ' . implode(', ', array_keys($dept)));

// ─── Records to talk about ────────────────────────────────────────────────

$orders   = array_map('intval', (array) db_values("SELECT id FROM orders ORDER BY id DESC LIMIT 4"));
$products = array_map('intval', (array) db_values("SELECT id FROM products ORDER BY id DESC LIMIT 4"));
// A tag when the record exists, plain words when the site has none yet.
$o = function ($i) use ($orders) { return isset($orders[$i]) ? T('order', $orders[$i]) : 'order **' . (1041 + $i) . '**'; };
$product_names = array('the linen throw', 'the ceramic bowl set', 'the wool cushion', 'the candle set');
$pr = function ($i) use ($products, $product_names) { return isset($products[$i]) ? T('product', $products[$i]) : $product_names[$i]; };

// The customer of the private channel: a contact of its own.
$customer_contact = (int) db_value("SELECT id FROM contacts WHERE company = 'Acme Hotels' LIMIT 1");
if ($customer_contact <= 0) {
    db("INSERT INTO contacts (first_name, last_name, company, title, business_city, business_phone, user, timestamp)
        VALUES ('Selin', 'Yalçın', 'Acme Hotels', 'Purchasing', 'Antalya', '0242 000 00 00', '" . $me . "', UNIX_TIMESTAMP())");
    $customer_contact = (int) mysqli_insert_id(db::$con);
}

say('Records: orders ' . implode(',', $orders) . ' · products ' . implode(',', $products) . ' · customer contact #' . $customer_contact);

// ─── Groups ───────────────────────────────────────────────────────────────

$g_customers = 0; $g_projects = 0;
if (function_exists('ws_groups_ready') && ws_groups_ready()) {
    $r = ws_group_save($admin, array('id' => 0, 'name' => 'Customers', 'color' => 2, 'parent_id' => 0));
    $g_customers = (int) ($r['id'] ?? 0);
    $r = ws_group_save($admin, array('id' => 0, 'name' => 'Projects', 'color' => 5, 'parent_id' => 0));
    $g_projects = (int) ($r['id'] ?? 0);
    say('Groups: Customers #' . $g_customers . ', Projects #' . $g_projects);
}

// ─── Channels ─────────────────────────────────────────────────────────────

function make_channel($viewer, $data, $created) {
    $r = ws_channel_create($viewer, $data);
    if (!$r['ok']) { say('  ! channel failed: ' . $r['error'] . ' — ' . $data['name']); return null; }
    $id = (int) $r['channel_id'];
    db("UPDATE ws_channels SET created_at = '" . (int) $created . "', last_message_at = '" . (int) $created . "' WHERE id = '" . $id . "'");
    db("UPDATE ws_messages SET created_at = '" . (int) $created . "' WHERE channel_id = '" . $id . "'");
    return ws_channel($id);
}

$everyone = array_unique(array_merge(array($me), $p));

$c_catalog = make_channel($admin, array('name' => 'autumn-catalog', 'topic' => 'Autumn 2026 collection: photos, texts, prices, launch', 'department_id' => $dept['Production'], 'members' => $everyone, 'color' => 5, 'group_id' => $g_projects), at(13, '09:12'));
$c_orders  = make_channel($V(0), array('name' => 'orders-desk', 'topic' => 'Orders that need a human: delays, changes, courtesy calls', 'department_id' => $dept['Sales'], 'members' => array($me, $p[0], $p[2], $p[3]), 'color' => 1), at(12, '10:40'));
$c_site    = make_channel($admin, array('name' => 'website-refresh', 'topic' => 'Homepage and catalog pages for the autumn season', 'department_id' => $dept['Production'], 'members' => array($me, $p[1], $p[4]), 'color' => 7, 'group_id' => $g_projects), at(11, '14:05'));
$c_acme    = make_channel($admin, array('name' => 'acme-hotels', 'kind' => 'private', 'topic' => 'Acme Hotels: room textiles, monthly invoicing', 'department_id' => $dept['Sales'], 'members' => array($me, $p[0], $p[3]), 'color' => 3, 'group_id' => $g_customers, 'customer_type' => 'contact', 'customer_id' => $customer_contact), at(9, '11:20'));
$c_general = ws_channel((int) db_value("SELECT id FROM ws_channels WHERE name IN ('general', 'genel') AND archived_at = 0 ORDER BY id LIMIT 1"));
if (!$c_general) {
    $c_general = make_channel($admin, array('name' => 'general', 'topic' => 'The whole team', 'members' => $everyone), at(14, '09:00'));
} else {
    $since = last_id();
    ws_channel_add_members($admin, $c_general, $everyone, false);
    settle($since, at(14, '09:02'));
}

say('Channels: autumn-catalog #' . $c_catalog['id'] . ', orders-desk #' . $c_orders['id'] . ', website-refresh #' . $c_site['id'] . ', acme-hotels #' . $c_acme['id'] . ', general #' . $c_general['id']);

// Pinned for the admin.
ws_channel_pin($admin, $c_catalog, true);
ws_channel_pin($admin, $c_orders, true);

// Summary of the main channel.
ws_channel_set_summary($admin, $c_catalog,
    "**Autumn 2026 collection** — 14 new products, launch on 12 October.\n\n" .
    "Who does what: photos " . U($p[1]) . ", texts " . U($p[4]) . ", prices and stock " . U($p[0]) . " with " . U($p[3]) . ".\n\n" .
    "- [x] Product list agreed\n- [x] Packaging decided (kraft)\n- [ ] Photo shoot\n- [ ] Descriptions and prices\n- [ ] Newsletter and homepage\n\n" .
    "Rule of the channel: a decision goes in with /decision, so it ends up on the timeline.");

// ─── general ──────────────────────────────────────────────────────────────

$m = post($admin, $c_general, "Welcome to the Workspace. Customer talk goes in the customer's channel, project talk in the project's, and everything that is a decision is marked as one — that way nobody has to scroll back two weeks to find out what we agreed.", at(14, '09:05'));
react($V(0), $m, '👍'); react($V(1), $m, '👍'); react($V(3), $m, '🎉');
decide($admin, $c_general, "Friday stand-up moves to 09:30 so the warehouse can join after the morning dispatch.", at(11, '09:31'));
post($V(4), $c_general, "Newsletter open rate for September: **41%**, best so far. The autumn teaser did it — " . U($p[1]) . " the visual worked.", at(6, '16:10'));
$m = post($V(3), $c_general, "Anadolu Kargo picks up at 15:00 from this week, not 16:30. Orders confirmed after 14:00 go out the next day.", at(5, '08:45'));
react($V(0), $m, '👀'); react($admin, $m, '👍');
if (function_exists('ws_poll_create')) {
    $r = ws_poll_create($V(4), $c_general, array('question' => 'Team lunch on Friday — where?', 'options' => array('The kebab place', 'Fish by the pier', 'Order in and eat on the terrace'), 'multiple' => 0, 'anonymous' => 0, 'closes_at' => 0));
    if ($r['ok']) {
        db("UPDATE ws_messages SET created_at = '" . at(1, '11:20') . "' WHERE id = '" . (int) $r['message_id'] . "'");
        $poll = ws_poll((int) $r['poll_id']);
        $opts = array_map('intval', (array) db_values("SELECT id FROM ws_poll_options WHERE poll_id = '" . (int) $r['poll_id'] . "' ORDER BY id"));
        if (count($opts) >= 3) {
            ws_poll_vote($V(0), $poll, array($opts[1]));
            ws_poll_vote($V(1), $poll, array($opts[1]));
            ws_poll_vote($V(3), $poll, array($opts[0]));
            ws_poll_vote($admin, $poll, array($opts[2]));
        }
    } else {
        say('  ! poll failed: ' . $r['error']);
    }
}
post($V(2), $c_general, U($me) . " the two form submissions from the weekend are answered; one of them wants a quote for 40 sets, I moved it to " . T('channel', $c_orders['id']) . ".", at(0, '09:14'));

// ─── autumn-catalog ───────────────────────────────────────────────────────

$m1 = post($admin, $c_catalog, "Kick-off. Fourteen products, launch on **12 October**. " . U($p[1]) . " photos, " . U($p[4]) . " texts, " . U($p[0]) . " prices. The product list is attached to the summary tab.", at(13, '09:20'));
post($V(0), $c_catalog, "Prices: I will base them on last year's autumn line plus 8%. Two of last year's items stay where they are — " . $pr(0) . " and " . $pr(1) . " — the demand was fine.", at(13, '10:02'));
$m = post($V(1), $c_catalog, "Studio B is free on Thursday and Friday. I need the samples in the studio by Wednesday evening — " . U($p[3]) . " can you bring them?", at(12, '15:40'));
$reply = post($V(3), $c_catalog, "Yes, Wednesday 16:00. Two boxes, I will add the kraft samples too.", at(12, '15:52'), array('parent_id' => $m));
react($V(1), $reply, '🙏');
decide($V(0), $c_catalog, "Packaging will be kraft boxes, not white cardboard. Cheaper, and it matches the season.", at(9, '11:05'));
if (function_exists('ws_poll_create')) {
    $r = ws_poll_create($V(1), $c_catalog, array('question' => 'Which day for the photo shoot?', 'options' => array('Wednesday 30 September', 'Thursday 1 October', 'Friday 2 October'), 'multiple' => 0, 'anonymous' => 0, 'closes_at' => 0));
    if ($r['ok']) {
        db("UPDATE ws_messages SET created_at = '" . at(8, '09:30') . "' WHERE id = '" . (int) $r['message_id'] . "'");
        $poll = ws_poll((int) $r['poll_id']);
        $opts = array_map('intval', (array) db_values("SELECT id FROM ws_poll_options WHERE poll_id = '" . (int) $r['poll_id'] . "' ORDER BY id"));
        if (count($opts) >= 3) {
            ws_poll_vote($admin, $poll, array($opts[1]));
            ws_poll_vote($V(0), $poll, array($opts[1]));
            ws_poll_vote($V(3), $poll, array($opts[1]));
            ws_poll_vote($V(4), $poll, array($opts[2]));
            $since = last_id();
            ws_poll_close($V(1), ws_poll((int) $r['poll_id']));
            settle($since, at(8, '13:58'));
        }
    }
}
decide($admin, $c_catalog, "Photo shoot on Thursday 1 October, studio B, from 09:00. " . U($p[1]) . " leads, " . U($p[3]) . " brings the samples on Wednesday.", at(8, '14:15'));
$sched = post($V(1), $c_catalog,
    "::: Photo shoot schedule\n" .
    "| Time | Product | Set | Who |\n|---|---|---|---|\n" .
    "| 09:00 | Linen throws (3 colours) | Sofa, daylight | " . U($p[1]) . " |\n" .
    "| 10:30 | Ceramic bowls, set of 4 | Table, top view | " . U($p[1]) . " |\n" .
    "| 12:00 | Kraft packaging | White table | " . U($p[3]) . " |\n" .
    "| 14:00 | Wool cushions | Sofa, warm light | " . U($p[1]) . " |\n" .
    "| 15:30 | Candle set | Shelf, dusk | " . U($p[1]) . " |",
    at(7, '10:10'));
react($admin, $sched, '👍'); react($V(0), $sched, '👍'); react($V(4), $sched, '🎉');
if (function_exists('ws_channel_pin_message') && $sched) { $since = last_id(); ws_channel_pin_message($admin, $c_catalog, $sched); settle($since, at(7, '10:14')); }
post($V(4), $c_catalog,
    "::: Price sheet (draft)\n" .
    "| Product | Cost | Margin | Price |\n|---|---|---|---|\n" .
    "| Linen throw | 420 | 45% | =ROUND(B2/(1-C2);0) |\n" .
    "| Ceramic bowl set | 310 | 50% | =ROUND(B3/(1-C3);0) |\n" .
    "| Wool cushion | 180 | 55% | =ROUND(B4/(1-C4);0) |\n" .
    "| Candle set | 95 | 60% | =ROUND(B5/(1-C5);0) |\n" .
    "| **Total** | =SUM(B2:B5) | | =SUM(D2:D5) |\n\n" .
    "The formulas do the rounding; change a margin and the price follows. " . U($p[0]) . " have a look before I put them in the catalog.",
    at(4, '11:45'));
post($V(0), $c_catalog, "Looks right. The candle set can go to 60% — it is a gift item, nobody compares it.", at(4, '13:20'));
$m = post($admin, $c_catalog, "Launch checklist — tick as you go:\n- [x] Product list agreed\n- [x] Packaging decided\n- [x] Photo shoot planned\n- [ ] Photos edited and uploaded\n- [ ] Descriptions written\n- [ ] Prices in the catalog\n- [ ] Newsletter draft\n- [ ] Homepage hero", at(4, '16:30'));
post($V(1), $c_catalog, "First batch of edited photos is in the channel's Files tab, folder *autumn-2026*. Throws and bowls are done; cushions tomorrow.", at(1, '17:48'));
post($V(4), $c_catalog, U($me) . " descriptions for the first six are ready in my notes — I shared the note with you. Can you check the tone before I paste them into the products?", at(0, '08:52'));

// ─── orders-desk ──────────────────────────────────────────────────────────

post($V(0), $c_orders, "This channel is for orders that need a person: a delay, a change of address, a customer who called. Tag the order so the conversation shows up on the order screen too.", at(12, '10:45'));
decide($V(0), $c_orders, "Orders over ₺5.000 get a courtesy call before they ship. Sales makes the call, warehouse waits for the OK.", at(8, '10:50'));
post($V(2), $c_orders, "Customer of " . $o(0) . " called: she wants the second cushion in terracotta instead of sand. Stock says 3 left. " . U($p[3]) . " can you swap before it is packed?", at(2, '10:05'));
$m = post($V(3), $c_orders, "Swapped and repacked. Label reprinted, goes out with today's pickup.", at(2, '11:30'));
react($V(2), $m, '✅');
post($V(0), $c_orders, $o(1) . " is over ₺5.000 — courtesy call is on my list for tomorrow morning. " . $o(2) . " asked for an invoice to a company name, sending it to the ERP.", at(1, '15:12'));
post($V(2), $c_orders, U($me) . " " . $o(3) . " paid twice by mistake (bank transfer). Refund from the order screen, or do you want to keep it as credit?", at(0, '09:40'));

// ─── website-refresh ──────────────────────────────────────────────────────

post($admin, $c_site, "Two things for the season: a new homepage hero with the autumn visual, and a catalog page that groups the collection on one screen. Both in the Visual Page Editor, on the existing design.", at(11, '14:10'));
decide($admin, $c_site, "Homepage hero switches to the autumn visual on 8 October, four days before launch, so the newsletter can link to it.", at(7, '09:20'));
post($V(1), $c_site, "Hero draft is on the design's *autumn* page. I used the catalog widget bound to the new product group, so the cards fill themselves when the products are published.", at(3, '16:05'));
post($V(4), $c_site, "Copy for the hero: **The house gets warmer.** Subtitle: *Linen, wool and clay for the long evenings.* Short enough for the phone.", at(1, '10:15'));
react($admin, (int) db_value("SELECT MAX(id) FROM ws_messages WHERE channel_id = '" . (int) $c_site['id'] . "'"), '👍');

// ─── acme-hotels (customer channel) ───────────────────────────────────────

post($V(0), $c_acme, "Acme Hotels: 60 rooms, they buy throws and cushions twice a year. Contact is Selin at reception, accounts is a different person.", at(9, '11:25'));
post($V(0), $c_acme, "Accounts contact at Acme: Deniz, ext. 204. Invoices go to muhasebe@ — never to reception.", at(9, '11:27'), array('kind' => 'note'));
decide($V(0), $c_acme, "Acme is invoiced monthly, on the 1st, one invoice for all deliveries of the month. Payment term 30 days.", at(6, '15:40'));
post($V(3), $c_acme, "September delivery went out today: 24 throws, 40 cushions. Delivery note is in the ERP, linked to their account.", at(1, '14:22'));
post($V(0), $c_acme, U($me) . " they asked whether we can do a custom embroidered label for the next batch. I said we would come back by Friday — can production do it?", at(0, '10:03'));

// ─── Tasks ────────────────────────────────────────────────────────────────

$t = array();
$t[] = task($admin, array('title' => 'Photograph the autumn collection', 'description' => "Studio B. Schedule is pinned in " . T('channel', $c_catalog['id']) . ".", 'status' => 'doing', 'priority' => 'high', 'start_date' => day(-1), 'due_date' => day(3), 'estimate_minutes' => 480, 'department_id' => $dept['Production'], 'channel_id' => $c_catalog['id'], 'assignees' => array($p[1]), 'refs' => array($pr(0), $pr(1))), at(8, '14:20'));
$t[] = task($admin, array('title' => 'Write descriptions for the 14 new products', 'description' => "Tone: warm, short, no superlatives. Six are drafted in the shared note.", 'status' => 'doing', 'priority' => 'normal', 'start_date' => day(-4), 'due_date' => day(7), 'estimate_minutes' => 360, 'department_id' => $dept['Marketing'], 'channel_id' => $c_catalog['id'], 'assignees' => array($p[4])), at(8, '14:22'));
$t[] = task($V(0), array('title' => 'Courtesy call for ' . (isset($orders[1]) ? 'order ' . $orders[1] : 'the large order'), 'description' => "Over ₺5.000 — call before it ships. " . $o(1), 'status' => 'todo', 'priority' => 'urgent', 'due_date' => day(-1), 'estimate_minutes' => 30, 'department_id' => $dept['Sales'], 'channel_id' => $c_orders['id'], 'assignees' => array($p[0]), 'refs' => array($o(1))), at(2, '15:15'));
$t[] = task($V(3), array('title' => 'Restock kraft boxes (minimum 500)', 'description' => "Supplier: Ege Ambalaj. Last batch took 4 days.", 'status' => 'todo', 'priority' => 'high', 'due_date' => day(2), 'estimate_minutes' => 60, 'department_id' => $dept['Warehouse'], 'channel_id' => 0, 'assignees' => array($p[3])), at(5, '09:10'));
$t[] = task($V(2), array('title' => 'Update the Anadolu Kargo cut-off time on the shipping page', 'description' => "15:00 pickup from this week.", 'status' => 'doing', 'priority' => 'normal', 'due_date' => day(1), 'estimate_minutes' => 120, 'department_id' => $dept['Sales'], 'channel_id' => 0, 'assignees' => array($p[2])), at(5, '09:00'));
$t[] = task($admin, array('title' => 'Prepare the Q4 campaign brief', 'description' => "Newsletter series + two offers. Draft in " . T('channel', $c_site['id']) . ".", 'status' => 'todo', 'priority' => 'normal', 'start_date' => day(6), 'due_date' => day(10), 'estimate_minutes' => 600, 'department_id' => $dept['Marketing'], 'channel_id' => $c_site['id'], 'assignees' => array($p[4])), at(3, '11:00'));
$t[] = task($admin, array('title' => 'Fix the coupon message on the checkout page', 'description' => "Says 'invalid' for a valid code when the cart is under the minimum. Should say the minimum.", 'status' => 'waiting', 'priority' => 'high', 'due_date' => day(1), 'estimate_minutes' => 90, 'department_id' => $dept['Production'], 'channel_id' => 0, 'assignees' => array($me)), at(6, '17:30'));
$t[] = task($V(0), array('title' => 'Send the accountant pack for September', 'description' => "ERP > Accountant Pack. Lock the period after sending.", 'status' => 'todo', 'priority' => 'normal', 'due_date' => day(6), 'estimate_minutes' => 60, 'department_id' => $dept['Sales'], 'channel_id' => 0, 'assignees' => array($p[0])), at(1, '09:05'));
$t[] = task($V(3), array('title' => 'Stock count — warehouse B', 'description' => "Full count before the launch stock arrives.", 'status' => 'todo', 'priority' => 'high', 'start_date' => day(6), 'due_date' => day(8), 'estimate_minutes' => 1200, 'department_id' => $dept['Warehouse'], 'channel_id' => 0, 'assignees' => array($p[3])), at(1, '09:30'));
$t[] = task($admin, array('title' => 'Homepage hero: autumn visual and copy', 'description' => "Visual from the shoot, copy from " . U($p[4]) . ". Publish on 8 October.", 'status' => 'todo', 'priority' => 'normal', 'start_date' => day(6), 'due_date' => day(9), 'estimate_minutes' => 180, 'department_id' => $dept['Production'], 'channel_id' => $c_site['id'], 'assignees' => array($p[1], $me)), at(3, '16:10'));
$t[] = task($V(0), array('title' => 'Confirm the kraft packaging quote with Ege Ambalaj', 'description' => "Guest room with Selin Kaya is open; she sends the final price.", 'status' => 'todo', 'priority' => 'high', 'due_date' => day(3), 'estimate_minutes' => 45, 'department_id' => $dept['Sales'], 'channel_id' => $c_catalog['id'], 'assignees' => array($p[0])), at(2, '12:00'));
$t[] = task($admin, array('title' => 'Embroidered label for Acme: is it feasible?', 'description' => "Answer by Friday. " . T('channel', $c_acme['id']), 'status' => 'todo', 'priority' => 'normal', 'due_date' => day(3), 'estimate_minutes' => 60, 'department_id' => $dept['Production'], 'channel_id' => 0, 'assignees' => array($me, $p[1])), at(0, '10:10'));
// Finished ones.
$d1 = task($V(2), array('title' => 'Reply to the open form submissions', 'status' => 'todo', 'priority' => 'normal', 'due_date' => day(-4), 'estimate_minutes' => 60, 'department_id' => $dept['Sales'], 'channel_id' => 0, 'assignees' => array($p[2])), at(6, '09:00'));
finish($V(2), $d1, 'done', at(4, '11:10'));
$d2 = task($admin, array('title' => 'Set up the abandoned-cart e-mail', 'status' => 'todo', 'priority' => 'normal', 'due_date' => day(-5), 'estimate_minutes' => 120, 'department_id' => $dept['Marketing'], 'channel_id' => 0, 'assignees' => array($me)), at(9, '10:00'));
finish($admin, $d2, 'done', at(5, '16:45'));
$d3 = task($V(1), array('title' => 'Choose the kraft box supplier', 'status' => 'todo', 'priority' => 'high', 'due_date' => day(-7), 'estimate_minutes' => 90, 'department_id' => $dept['Production'], 'channel_id' => $c_catalog['id'], 'assignees' => array($p[1], $p[3])), at(12, '16:00'));
finish($V(1), $d3, 'done', at(9, '12:00'));
say('Tasks: ' . count(array_filter($t)) . ' open, 3 finished');

// ─── Plan items ───────────────────────────────────────────────────────────

plan($admin, array('id' => 0, 'kind' => 'leave', 'scope' => 'people', 'people' => array($p[4]), 'title' => 'Leave', 'start' => day(2), 'end' => day(3), 'all_day' => 1));
plan($admin, array('id' => 0, 'kind' => 'meeting', 'scope' => 'company', 'title' => 'Weekly planning', 'start' => day(6), 'end' => day(6), 'start_time' => '10:00', 'end_time' => '11:00', 'all_day' => 0));
plan($V(0), array('id' => 0, 'kind' => 'visit', 'scope' => 'people', 'people' => array($p[0]), 'title' => 'Acme Hotels — showroom visit', 'start' => day(1), 'end' => day(1), 'start_time' => '14:00', 'end_time' => '16:30', 'all_day' => 0));
plan($admin, array('id' => 0, 'kind' => 'meeting', 'scope' => 'department', 'department_id' => $dept['Production'], 'title' => 'Launch review', 'start' => day(9), 'end' => day(9), 'start_time' => '15:00', 'end_time' => '16:00', 'all_day' => 0));
plan($admin, array('id' => 0, 'kind' => 'holiday', 'scope' => 'company', 'title' => 'Republic Day', 'start' => date('Y') . '-10-29', 'end' => date('Y') . '-10-29', 'all_day' => 1));
say('Plan items: leave, meetings, a visit, a holiday');

// ─── Notes ────────────────────────────────────────────────────────────────

if (function_exists('ws_notes_ready') && ws_notes_ready()) {
    $r = ws_note_save($V(4), array('note_id' => 0, 'title' => 'Autumn descriptions — first six', 'pinned' => 0, 'body' =>
        "Tone: warm, short, no superlatives.\n\n" .
        "**Linen throw** — Stonewashed linen, 130×180. Softer every wash; the colour stays.\n\n" .
        "**Ceramic bowl set** — Four bowls, thrown by hand in Kütahya. No two rims alike.\n\n" .
        "**Wool cushion** — Undyed wool, a linen back, a hidden zip.\n\n" .
        "**Candle set** — Three soy candles: fig, cedar, black tea. Forty hours each.\n\n" .
        "- [x] Throw\n- [x] Bowls\n- [x] Cushion\n- [ ] Candles: check the burn time with the supplier\n- [ ] Two more"));
    if ($r['ok']) {
        $note = ws_note((int) $r['note_id']);
        ws_note_share_people($V(4), $note, array($me), true);
        db("UPDATE ws_notes SET created_at = '" . at(1, '17:00') . "', updated_at = '" . at(0, '08:50') . "' WHERE id = '" . (int) $note['id'] . "'");
    }
    $r = ws_note_save($admin, array('note_id' => 0, 'title' => 'Launch budget', 'pinned' => 1, 'body' =>
        "What the autumn launch costs, before the newsletter goes out.\n\n" .
        "```calc\nPhotos = 4500\nPackaging = 12 * 480\nNewsletter = 1200\nAds = 6000\nTotal = Photos + Packaging + Newsletter + Ads\nPer product = ROUND(Total / 14; 0)\n```\n\n" .
        "Ads can move to November if the organic reach holds."));
    if ($r['ok']) {
        db("UPDATE ws_notes SET created_at = '" . at(6, '18:10') . "', updated_at = '" . at(2, '09:30') . "' WHERE id = '" . (int) $r['note_id'] . "'");
    }
    say('Notes: 2 (one shared with the admin)');
}

// ─── A guest room ─────────────────────────────────────────────────────────

if (function_exists('ws_guests_ready') && ws_guests_ready()) {
    $r = ws_guest_start($admin, array('guest_name' => 'Selin Kaya', 'topic' => 'Kraft packaging quote', 'mode' => 'timed', 'duration' => 604800, 'members' => array($p[0])));
    if ($r['ok']) {
        $room = ws_channel((int) $r['channel_id']);
        $guest = ws_guest_for_channel($room['id']);
        db("UPDATE ws_channels SET created_at = '" . at(3, '10:30') . "' WHERE id = '" . (int) $room['id'] . "'");
        db("UPDATE ws_messages SET created_at = '" . at(3, '10:30') . "' WHERE channel_id = '" . (int) $room['id'] . "'");
        post($admin, $room, "Hello Selin, thanks for joining. We need 500 kraft boxes, 22×16×8 cm, one-colour print on the lid. Delivery by 7 October — is that possible?", at(3, '10:32'));
        if ($guest) {
            foreach (array(
                array(at(3, '11:15'), "Hello! Yes, 500 pieces by the 7th is fine. One-colour print: ₺9,40 per box, plate cost ₺650 once. Do you want the sample first?"),
                array(at(1, '09:05'), "Sample went out yesterday with the courier. Final price for 500 pieces with print: **₺5.350** + VAT, delivery included."),
            ) as $g) {
                db("INSERT INTO ws_messages (channel_id, parent_id, sender_kind, sender_id, kind, body, created_at)
                    VALUES ('" . (int) $room['id'] . "', 0, 'guest', '" . (int) $guest['id'] . "', 'message', '" . e($g[1]) . "', '" . (int) $g[0] . "')");
                db("UPDATE ws_channels SET last_message_id = '" . (int) mysqli_insert_id(db::$con) . "', last_message_at = '" . (int) $g[0] . "' WHERE id = '" . (int) $room['id'] . "'");
            }
            db("UPDATE ws_guests SET last_seen_at = '" . at(1, '09:05') . "' WHERE id = '" . (int) $guest['id'] . "'");
        }
        post($V(0), $room, "Thank you Selin — the sample arrived this morning, it looks good. We will confirm the order by Friday.", at(0, '10:20'));
        say('Guest room: ' . $room['name'] . ' (link ' . $r['url'] . ')');
    } else {
        say('  ! guest room failed: ' . $r['error']);
    }
}

// ─── Done ─────────────────────────────────────────────────────────────────

if (function_exists('ws_channel_membership_forget')) { ws_channel_membership_forget(); }
settle(0, at(0, '08:30'));

say("\nDone. Open the Workspace: workspace.php");
say("Panel language now: " . (string) db_value("SELECT software_language FROM config") . " (switch with ?lang=en / ?lang=tr)");
