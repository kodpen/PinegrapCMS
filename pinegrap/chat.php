<?php
/**
 * PineGrap - Enterprise Website Platform — Live Chat module.
 *
 * All chat logic lives in this file: permission rules, conversation and
 * message CRUD, unread summaries, the backend launcher and the site
 * (frontend) widget rendering. api.php only delegates here through thin
 * dispatchers (pg_chat_handle_backend_action / pg_chat_handle_site_action);
 * the schema and the side model carry both channels.
 *
 * Design rules:
 *  - Pairing rule: min(roleA, roleB) <= 2 — role 3 <-> role 3 cannot chat.
 *    Role hierarchy verified from code: 0=Administrator, 1=Designer,
 *    2=Manager, 3=User (select_user_role / validate_area_access).
 *  - Chat must never break a page: when the schema is missing,
 *    pg_chat_ready() returns false and the feature silently disappears
 *    (waf_schema_ready pattern).
 *  - Message bodies are plain text; 2000 characters max. Output never
 *    contains HTML — the JS side writes text with textContent only.
 *  - Presence: user.user_online_timestamp + the existing 50-second
 *    heartbeat. Thresholds: <120 s online, <1200 s away (heartbeat x2
 *    plus a buffer).
 *
 * @author  Erdal Güral (Kodpen)
 * @license https://opensource.org/licenses/mit-license.html MIT License
 */

// ── Readiness probe ──────────────────────────────────────────────────────

// The schema appears only after the database upgrade is run, which happens
// separately from deploying this code. In that window (or when the upgrade
// is never run) the feature must act disabled without producing errors. The
// result is statically cached for the request; $recheck exists only to
// refresh right after an upgrade.
function pg_chat_ready($recheck = false)
{
    static $ready = null;

    if ($ready !== null && $recheck == false) {
        return $ready;
    }

    $ready = false;

    if (!class_exists('db') || !isset(db::$con) || !db::$con) {
        return $ready;
    }

    $conversations_table = db_item("SHOW TABLES LIKE 'chat_conversations'");
    $messages_table = db_item("SHOW TABLES LIKE 'chat_messages'");

    if ($conversations_table && $messages_table) {
        $ready = true;
    }

    return $ready;
}

// The master switch is absolute: when CHAT_ENABLED is off, nothing runs —
// no launcher, no actions.
function pg_chat_enabled()
{
    return (defined('CHAT_ENABLED') && CHAT_ENABLED && pg_chat_ready());
}

// The typing-indicator columns arrive with the 2026.4.3 upgrade; when the
// code ships first, the feature stays silently disabled (schema probe
// pattern).
function pg_chat_typing_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = (pg_chat_ready() && db_item("SHOW COLUMNS FROM chat_conversations LIKE 'initiator_typing_until'")) ? true : false;
    }

    return $ready;
}

// File/image attachments require the 2026.4.4 columns; without them the
// attach button is never rendered and the upload endpoints politely refuse.
function pg_chat_attachments_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = (pg_chat_ready() && db_item("SHOW COLUMNS FROM chat_messages LIKE 'attachment_file_id'")) ? true : false;
    }

    return $ready;
}

// Fragment that adds the attachment columns/JOIN to message SELECTs only
// when the schema is ready — kept in one place so the four queries can
// never drift apart.
function pg_chat_message_select()
{
    if (pg_chat_attachments_ready()) {
        return array(
            'cols' => ",
                chat_messages.attachment_file_id,
                chat_messages.attachment_kind,
                chat_messages.attachment_name,
                att_files.name AS attachment_path",
            'join' => "
            LEFT JOIN files att_files ON att_files.id = chat_messages.attachment_file_id"
        );
    }

    return array('cols' => '', 'join' => '');
}

// Shared attachment block for payloads. The URL is root-relative via
// files.name (same rule as avatars and router file serving).
function pg_chat_attachment_payload($row)
{
    if (!isset($row['attachment_kind'])
        || $row['attachment_kind'] == ''
        || $row['attachment_kind'] == 'none'
        || (int) $row['attachment_file_id'] <= 0
        || !isset($row['attachment_path'])
        || $row['attachment_path'] == ''
    ) {
        return null;
    }

    return array(
        'kind' => $row['attachment_kind'],
        'name' => ($row['attachment_name'] != '') ? $row['attachment_name'] : $row['attachment_path'],
        'url' => PATH . $row['attachment_path']
    );
}

// ── Attachment upload core ───────────────────────────────────────────────
// Same transport as api.php's upload_file pattern: the file arrives base64
// encoded in the JSON body (no multipart). The stored name is ENTIRELY
// synthetic (chat-<time>-<random>): not a single character is carried over
// from the visitor-supplied name — the IIS non-ASCII filename problem and
// double-extension tricks become impossible by construction. The display
// name is stored separately on the message. EVERYTHING outside the
// extension allowlist is rejected; executable types such as PHP/HTML are
// not on the list. Images are content-checked with getimagesize, other
// files with finfo when available.
// Attachment kind from the extension: 'image' | 'file' | '' (not allowed).
// The extension lists live ONLY here — every server-side decision goes
// through this single function, so the lists cannot drift apart.
function pg_chat_upload_kind($original_name)
{
    $image_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp');
    $file_extensions = array('pdf', 'zip', 'rar', '7z', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv');

    $original_name = trim((string) $original_name);
    $dot_position = mb_strrpos($original_name, '.');

    if ($original_name == '' || $dot_position === false) {
        return '';
    }

    $extension = mb_strtolower(mb_substr($original_name, $dot_position + 1));

    if (in_array($extension, $image_extensions, true)) {
        return 'image';
    }

    if (in_array($extension, $file_extensions, true)) {
        return 'file';
    }

    return '';
}

function pg_chat_store_upload($original_name, $data, $allow_images, $allow_files)
{
    if (!pg_chat_attachments_ready()) {
        return array('error' => lang('Attachments are disabled.'));
    }

    $original_name = trim((string) $original_name);
    $kind = pg_chat_upload_kind($original_name);

    if ($kind == ''
        || ($kind == 'image' && !$allow_images)
        || ($kind == 'file' && !$allow_files)
    ) {
        return array('error' => lang('This file type is not allowed.'));
    }

    $extension = mb_strtolower(mb_substr($original_name, mb_strrpos($original_name, '.') + 1));

    // Base64 decode (same steps as upload_file).
    $binary = explode('base64', (string) $data);
    $binary = str_replace(' ', '+', $binary);
    $binary = str_replace(',', '', $binary);
    $binary = base64_decode(array_pop($binary));

    if ($binary === false || strlen($binary) == 0) {
        return array('error' => lang('This file type is not allowed.'));
    }

    if (strlen($binary) > 5242880) {
        return array('error' => lang('File is too large.'));
    }

    // Synthetic, ASCII-guaranteed stored name + collision check.
    $stored_name = get_unique_name(array(
        'name' => 'chat-' . time() . '-' . mt_rand(1000, 9999) . '.' . $extension,
        'type' => 'file'
    ));

    $stored_path = FILE_DIRECTORY_PATH . '/' . $stored_name;

    $handle = fopen($stored_path, 'w');

    if ($handle === false) {
        return array('error' => lang('Query failed.'));
    }

    fwrite($handle, $binary);
    fclose($handle);

    // Content validation: a non-image is never accepted as an image; for
    // files, MIME is roughly checked with finfo when available (catches
    // executable content).
    if ($kind == 'image') {
        if (@getimagesize($stored_path) === false) {
            @unlink($stored_path);

            return array('error' => lang('This file type is not allowed.'));
        }
    } elseif (function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo) {
            $mime = (string) @finfo_file($finfo, $stored_path);
            @finfo_close($finfo);

            // Any hint of script/HTML/executable content: hard reject.
            if (preg_match('#^(text/html|application/x-php|application/x-httpd|application/x-msdownload|application/x-sh|application/x-executable)#i', $mime)) {
                @unlink($stored_path);

                return array('error' => lang('This file type is not allowed.'));
            }
        }
    }

    db("INSERT INTO files (
            name,
            folder,
            type,
            size,
            user,
            design,
            optimized,
            timestamp)
        VALUES (
            '" . e($stored_name) . "',
            '0',
            '" . e($extension) . "',
            '" . e(filesize($stored_path)) . "',
            '" . e((defined('USER_LOGGED_IN') && USER_LOGGED_IN) ? (int) USER_ID : 0) . "',
            '0',
            '0',
            UNIX_TIMESTAMP())");

    $file_id = mysqli_insert_id(db::$con);

    // Display name: the original name with control characters stripped,
    // shortened.
    $display_name = mb_substr(preg_replace('/[\\x00-\\x1F\\x7F]/', '', $original_name), 0, 120);

    return array(
        'file_id' => (int) $file_id,
        'kind' => $kind,
        'display_name' => $display_name,
        'stored_name' => $stored_name
    );
}

// Writes the attachment into the conversation as a message (empty body;
// preview = file name) and updates the conversation summary. The side
// columns come from the caller.
function pg_chat_insert_attachment_message($conversation, $side, $sender_kind, $sender_user_id, $stored)
{
    $now = time();

    db("INSERT INTO chat_messages
            (conversation_id, sender_kind, sender_user_id, body, created_at, attachment_file_id, attachment_kind, attachment_name)
        VALUES
            ('" . e((int) $conversation['id']) . "', '" . $sender_kind . "', '" . e((int) $sender_user_id) . "', '', '" . e($now) . "',
             '" . e((int) $stored['file_id']) . "', '" . e($stored['kind']) . "', '" . e($stored['display_name']) . "')");

    $message_id = mysqli_insert_id(db::$con);

    $my_read_column = ($side == 'initiator') ? 'initiator_last_read_id' : 'target_last_read_id';
    $my_seen_column = ($side == 'initiator') ? 'initiator_last_seen' : 'target_last_seen';

    db("UPDATE chat_conversations SET
            last_message_id = '" . e($message_id) . "',
            last_message_at = '" . e($now) . "',
            last_message_preview = '" . e(mb_substr($stored['display_name'], 0, 120)) . "',
            " . $my_read_column . " = '" . e($message_id) . "',
            " . $my_seen_column . " = '" . e($now) . "'
        WHERE id = '" . e((int) $conversation['id']) . "'");

    return array(
        'id' => (int) $message_id,
        'conversation_id' => (int) $conversation['id'],
        'sender_kind' => $sender_kind,
        'sender_user_id' => (int) $sender_user_id,
        'kind' => $sender_kind,
        'mine' => true,
        'body' => '',
        'created_at' => $now,
        'attachment' => array(
            'kind' => $stored['kind'],
            'name' => $stored['display_name'],
            'url' => PATH . $stored['stored_name']
        )
    );
}

// ── Permission rules ─────────────────────────────────────────────────────

// Backend pairing rule: at least one side must be staff (role <= 2).
// Admin/designer/manager can chat with everyone; role 3 <-> role 3 is
// never allowed.
function pg_chat_can_pair($role_a, $role_b)
{
    return (min((int) $role_a, (int) $role_b) <= 2);
}

// ── User presentation helpers ────────────────────────────────────────────

function pg_chat_presence($timestamp)
{
    $timestamp = (int) $timestamp;

    if ($timestamp <= 0) {
        return 'offline';
    }

    $ago = time() - $timestamp;

    if ($ago < 120) {
        return 'online';
    }

    if ($ago < 1200) {
        return 'away';
    }

    return 'offline';
}

function pg_chat_role_label($role)
{
    switch ((int) $role) {
        case 0:
            return lang('Administrator');
        case 1:
            return lang('Designer');
        case 2:
            return lang('Manager');
        default:
            return lang('User');
    }
}

// Avatar source uses the same priority as the dashboard's Whois Online
// widget: contacts.file_id > contacts.image > default image. The files name
// comes from the LEFT JOIN in the query, so there is no extra query per row.
function pg_chat_avatar_src($image, $image_file_id, $image_file_name)
{
    if (((int) $image_file_id > 0) && ($image_file_name != '')) {
        return PATH . $image_file_name;
    }

    if ($image != '') {
        return $image;
    }

    return PATH . SOFTWARE_DIRECTORY . '/assets/images/person1.png';
}

function pg_chat_display_name($first_name, $last_name, $username)
{
    $name = trim($first_name . ' ' . $last_name);

    if ($name != '') {
        return $name;
    }

    return $username;
}

// User row: everything the chat panel needs in a single query.
// (email_address feeds the offline-operator email notification.)
function pg_chat_user_row($user_id)
{
    return db_item("
        SELECT
            user.user_id AS id,
            user.user_role AS role,
            user.user_username AS username,
            user.user_online_timestamp AS online_timestamp,
            user.user_email AS email_address,
            contacts.first_name AS first_name,
            contacts.last_name AS last_name,
            contacts.image AS image,
            contacts.file_id AS image_file_id,
            files.name AS image_file_name
        FROM user
        LEFT JOIN contacts ON contacts.id = user.user_contact
        LEFT JOIN files ON files.id = contacts.file_id
        WHERE user.user_id = '" . e((int) $user_id) . "'");
}

function pg_chat_user_brief($row)
{
    if (!$row) {
        return null;
    }

    $first_name = isset($row['first_name']) ? $row['first_name'] : '';
    $last_name = isset($row['last_name']) ? $row['last_name'] : '';
    $image = isset($row['image']) ? $row['image'] : '';
    $image_file_id = isset($row['image_file_id']) ? $row['image_file_id'] : 0;
    $image_file_name = isset($row['image_file_name']) ? $row['image_file_name'] : '';

    return array(
        'id' => (int) $row['id'],
        'name' => pg_chat_display_name($first_name, $last_name, $row['username']),
        'username' => $row['username'],
        'role' => (int) $row['role'],
        'role_label' => pg_chat_role_label($row['role']),
        'avatar' => pg_chat_avatar_src($image, $image_file_id, $image_file_name),
        'presence' => pg_chat_presence(isset($row['online_timestamp']) ? $row['online_timestamp'] : 0)
    );
}

// ── Online user list ─────────────────────────────────────────────────────

// Staff (role <= 2) sees everyone; role 3 sees staff only — the list-side
// counterpart of the pairing rule. Ordered by most recently active; the
// LIMIT is fixed, so query cost stays constant as the table grows.
function pg_chat_online_users()
{
    $where = "user.user_id != '" . e((int) USER_ID) . "'";

    if ((int) USER_ROLE >= 3) {
        $where .= " AND user.user_role <= 2";
    }

    $rows = db_items("
        SELECT
            user.user_id AS id,
            user.user_role AS role,
            user.user_username AS username,
            user.user_online_timestamp AS online_timestamp,
            contacts.first_name AS first_name,
            contacts.last_name AS last_name,
            contacts.image AS image,
            contacts.file_id AS image_file_id,
            files.name AS image_file_name
        FROM user
        LEFT JOIN contacts ON contacts.id = user.user_contact
        LEFT JOIN files ON files.id = contacts.file_id
        WHERE " . $where . "
        ORDER BY user.user_online_timestamp DESC
        LIMIT 100");

    $users = array();

    foreach ($rows as $row) {
        $users[] = pg_chat_user_brief($row);
    }

    return $users;
}

// ── Conversation helpers ─────────────────────────────────────────────────

function pg_chat_conversation($conversation_id)
{
    return db_item("SELECT * FROM chat_conversations WHERE id = '" . e((int) $conversation_id) . "'");
}

// The user's side in a conversation: 'initiator' | 'target' | false.
// In the site channel, role 0 administrators count as the operator (target)
// side so they can see and answer site conversations.
function pg_chat_side($conversation, $user_id)
{
    $user_id = (int) $user_id;

    if ($user_id > 0 && (int) $conversation['initiator_user_id'] === $user_id) {
        return 'initiator';
    }

    if ($user_id > 0 && (int) $conversation['target_user_id'] === $user_id) {
        return 'target';
    }

    if ($conversation['channel'] == 'site' && defined('USER_ROLE') && (int) USER_ROLE === 0) {
        return 'target';
    }

    return false;
}

// Close/delete authority is hierarchical: it always belongs to the higher
// rank. In the backend channel the rule is one line: I have authority when
// my role is LOWER than or EQUAL to the peer's (0 is highest) — between
// admin and manager only the admin, between equal roles both sides. Role 3
// never has authority. In the site channel the staff side (operator +
// role 0) has it.
function pg_chat_can_manage($conversation)
{
    if (!defined('USER_ROLE') || (int) USER_ROLE > 2) {
        return false;
    }

    if ($conversation['channel'] != 'backend') {
        return true;
    }

    if ((int) USER_ROLE === 0) {
        return true;
    }

    $peer_role = db_value("SELECT user_role FROM user WHERE user_id = '" . e(pg_chat_peer_id($conversation, (int) USER_ID)) . "'");

    if ($peer_role === null) {
        return true;
    }

    return ((int) USER_ROLE <= (int) $peer_role);
}

// The peer's user id (backend channel).
function pg_chat_peer_id($conversation, $user_id)
{
    if ((int) $conversation['initiator_user_id'] === (int) $user_id) {
        return (int) $conversation['target_user_id'];
    }

    return (int) $conversation['initiator_user_id'];
}

function pg_chat_message_payload($row)
{
    return array(
        'id' => (int) $row['id'],
        'sender_kind' => $row['sender_kind'],
        'sender_user_id' => (int) $row['sender_user_id'],
        'body' => $row['body'],
        'created_at' => (int) $row['created_at'],
        'attachment' => pg_chat_attachment_payload($row)
    );
}

// Conversation + peer + latest messages: one response on window open.
function pg_chat_conversation_payload($conversation, $with_messages = true)
{
    $me = (int) USER_ID;
    $side = pg_chat_side($conversation, $me);

    $peer = null;

    if ($conversation['channel'] == 'backend') {
        $peer = pg_chat_user_brief(pg_chat_user_row(pg_chat_peer_id($conversation, $me)));
    }

    $my_read_column = ($side == 'initiator') ? 'initiator_last_read_id' : 'target_last_read_id';
    $peer_read_column = ($side == 'initiator') ? 'target_last_read_id' : 'initiator_last_read_id';

    $payload = array(
        'id' => (int) $conversation['id'],
        'channel' => $conversation['channel'],
        'status' => $conversation['status'],
        'side' => $side,
        'peer' => $peer,
        'party_name' => $conversation['party_name'],
        // Visitor IP for the panel header (site channel only).
        'ip_address' => (($conversation['channel'] == 'site') ? (string) $conversation['ip_address'] : ''),
        'last_message_id' => (int) $conversation['last_message_id'],
        'last_message_at' => (int) $conversation['last_message_at'],
        'last_message_preview' => $conversation['last_message_preview'],
        'unread' => ((int) $conversation['last_message_id'] > (int) $conversation[$my_read_column]),
        // Delivered/seen ticks: the peer's read cursor. Compared against my
        // own message ids — once the cursor passes an id, it is "seen".
        'peer_read_id' => (int) $conversation[$peer_read_column],
        // can_manage only decides whether the UI shows the buttons; the real
        // check is repeated server-side on every action (rendering is never
        // an authority check).
        'can_manage' => pg_chat_can_manage($conversation)
    );

    if ($with_messages) {
        $select = pg_chat_message_select();

        $messages = db_items("
            SELECT chat_messages.id, chat_messages.sender_kind, chat_messages.sender_user_id, chat_messages.body, chat_messages.created_at" . $select['cols'] . "
            FROM chat_messages" . $select['join'] . "
            WHERE chat_messages.conversation_id = '" . e((int) $conversation['id']) . "'
            ORDER BY chat_messages.id DESC
            LIMIT 50");

        $messages = array_reverse($messages);

        $payload['messages'] = array();

        foreach ($messages as $message) {
            $payload['messages'][] = pg_chat_message_payload($message);
        }
    }

    return $payload;
}

// ── Opening a conversation (backend) ─────────────────────────────────────

// Finds the open conversation for a pair regardless of direction (null when
// none). Both chat_open and the first message (pg_chat_send) use it — a
// single finder exists so the two paths can never reach different results.
function pg_chat_find_open_backend_conversation($user_a, $user_b)
{
    $user_a = (int) $user_a;
    $user_b = (int) $user_b;

    $conversation = db_item("
        SELECT * FROM chat_conversations
        WHERE channel = 'backend'
            AND status = 'open'
            AND (
                (initiator_user_id = '" . e($user_a) . "' AND target_user_id = '" . e($user_b) . "')
                OR (initiator_user_id = '" . e($user_b) . "' AND target_user_id = '" . e($user_a) . "')
            )
        ORDER BY id DESC
        LIMIT 1");

    return $conversation ? $conversation : null;
}

function pg_chat_open_backend_conversation($target_user_id)
{
    $me = (int) USER_ID;
    $target_user_id = (int) $target_user_id;

    if ($target_user_id <= 0 || $target_user_id === $me) {
        return array('status' => 'error', 'message' => lang('You cannot chat with this user.'));
    }

    $target = pg_chat_user_row($target_user_id);

    if (!$target) {
        return array('status' => 'error', 'message' => lang('You cannot chat with this user.'));
    }

    // Pairing rule at open time; it is verified again on every send.
    if (!pg_chat_can_pair(USER_ROLE, $target['role'])) {
        return array('status' => 'error', 'message' => lang('You cannot chat with this user.'));
    }

    // A single open conversation per pair, regardless of direction.
    $existing = pg_chat_find_open_backend_conversation($me, $target_user_id);

    if ($existing) {
        return array('status' => 'success', 'data' => pg_chat_conversation_payload($existing));
    }

    // The conversation row is created on the FIRST MESSAGE (pg_chat_send).
    // Clicking a user and leaving without typing leaves no empty
    // conversation behind — the draft lives only in the client (id = 0)
    // and never appears in any list.
    return array('status' => 'success', 'data' => array(
        'id' => 0,
        'draft' => true,
        'channel' => 'backend',
        'status' => 'open',
        'side' => 'initiator',
        'peer' => pg_chat_user_brief($target),
        'party_name' => '',
        'last_message_id' => 0,
        'last_message_at' => 0,
        'last_message_preview' => '',
        'unread' => false,
        'can_manage' => ((int) USER_ROLE === 0 || ((int) USER_ROLE <= 2 && (int) USER_ROLE <= (int) $target['role'])),
        'messages' => array()
    ));
}

// ── Sending messages ─────────────────────────────────────────────────────

// Session-based rate limit: at most 30 messages per 60 seconds. The counter
// itself lives in the session; no extra database writes.
function pg_chat_rate_limited()
{
    $now = time();

    if (!isset($_SESSION['chat']['send_times']) || !is_array($_SESSION['chat']['send_times'])) {
        $_SESSION['chat']['send_times'] = array();
    }

    $recent = array();

    foreach ($_SESSION['chat']['send_times'] as $timestamp) {
        if (($now - (int) $timestamp) < 60) {
            $recent[] = (int) $timestamp;
        }
    }

    $_SESSION['chat']['send_times'] = $recent;

    if (count($recent) >= 30) {
        return true;
    }

    $_SESSION['chat']['send_times'][] = $now;

    return false;
}

function pg_chat_send($conversation_id, $body, $target_user_id = 0)
{
    $me = (int) USER_ID;
    $conversation_id = (int) $conversation_id;

    // First message coming from a draft: the conversation row is created
    // only now (chat_open writes no row, so empty conversations are never
    // born). The existing open conversation is looked up first so two tabs
    // sending their first message at the same time cannot create duplicate
    // rows — the same finder as chat_open is used.
    if ($conversation_id <= 0) {
        $target_user_id = (int) $target_user_id;

        if ($target_user_id <= 0 || $target_user_id === $me) {
            return array('status' => 'error', 'message' => lang('You cannot chat with this user.'));
        }

        $target = pg_chat_user_row($target_user_id);

        if (!$target || !pg_chat_can_pair(USER_ROLE, $target['role'])) {
            return array('status' => 'error', 'message' => lang('You cannot chat with this user.'));
        }

        $conversation = pg_chat_find_open_backend_conversation($me, $target_user_id);

        if (!$conversation) {
            db("INSERT INTO chat_conversations
                    (channel, status, initiator_user_id, target_user_id, created_at)
                VALUES
                    ('backend', 'open', '" . e($me) . "', '" . e($target_user_id) . "', '" . e(time()) . "')");

            $conversation = pg_chat_conversation(mysqli_insert_id(db::$con));
        }
    } else {
        $conversation = pg_chat_conversation($conversation_id);
    }

    if (!$conversation) {
        return array('status' => 'error', 'message' => lang('Conversation not found.'));
    }

    $side = pg_chat_side($conversation, $me);

    // Non-participants must not even learn the conversation exists: same
    // "not found".
    if (!$side) {
        return array('status' => 'error', 'message' => lang('Conversation not found.'));
    }

    if ($conversation['status'] != 'open') {
        return array('status' => 'error', 'message' => lang('This conversation is closed.'));
    }

    // The pairing rule is re-verified on every message — roles may have
    // changed after the conversation was opened. Hiding in the UI is not an
    // authority check.
    if ($conversation['channel'] == 'backend') {
        $peer_role = db_value("SELECT user_role FROM user WHERE user_id = '" . e(pg_chat_peer_id($conversation, $me)) . "'");

        if ($peer_role === null || !pg_chat_can_pair(USER_ROLE, $peer_role)) {
            return array('status' => 'error', 'message' => lang('You cannot chat with this user.'));
        }
    }

    $body = trim((string) $body);

    if ($body == '') {
        return array('status' => 'error', 'message' => lang('Message is empty.'));
    }

    if (mb_strlen($body) > 2000) {
        return array('status' => 'error', 'message' => lang('Message is too long.'));
    }

    if (pg_chat_rate_limited()) {
        return array('status' => 'error', 'message' => lang('You are sending messages too quickly. Please wait a moment.'));
    }

    $now = time();

    db("INSERT INTO chat_messages
            (conversation_id, sender_kind, sender_user_id, body, created_at)
        VALUES
            ('" . e((int) $conversation['id']) . "', 'user', '" . e($me) . "', '" . e($body) . "', '" . e($now) . "')");

    $message_id = mysqli_insert_id(db::$con);

    // The summary is kept on the conversation so the list screen never has
    // to touch the message table.
    $preview = mb_substr(str_replace(array("\r", "\n"), ' ', $body), 0, 120);

    $my_read_column = ($side == 'initiator') ? 'initiator_last_read_id' : 'target_last_read_id';
    $my_seen_column = ($side == 'initiator') ? 'initiator_last_seen' : 'target_last_seen';

    db("UPDATE chat_conversations SET
            last_message_id = '" . e($message_id) . "',
            last_message_at = '" . e($now) . "',
            last_message_preview = '" . e($preview) . "',
            " . $my_read_column . " = '" . e($message_id) . "',
            " . $my_seen_column . " = '" . e($now) . "'
        WHERE id = '" . e((int) $conversation['id']) . "'");

    return array('status' => 'success', 'data' => array(
        'id' => (int) $message_id,
        'conversation_id' => (int) $conversation['id'],
        'sender_kind' => 'user',
        'sender_user_id' => $me,
        'body' => $body,
        'created_at' => $now
    ));
}

// ── Poll ─────────────────────────────────────────────────────────────────

function pg_chat_poll($conversation_id, $since_id, $mark_read, $typing = false)
{
    $conversation = pg_chat_conversation($conversation_id);

    if (!$conversation) {
        // The peer may have deleted the conversation; the UI uses this
        // response to end the window gracefully.
        return array('status' => 'error', 'code' => 'gone', 'message' => lang('Conversation not found.'));
    }

    $me = (int) USER_ID;
    $side = pg_chat_side($conversation, $me);

    if (!$side) {
        return array('status' => 'error', 'code' => 'gone', 'message' => lang('Conversation not found.'));
    }

    $since_id = (int) $since_id;

    if ($since_id > 0) {
        $select = pg_chat_message_select();

        $messages = db_items("
            SELECT chat_messages.id, chat_messages.sender_kind, chat_messages.sender_user_id, chat_messages.body, chat_messages.created_at" . $select['cols'] . "
            FROM chat_messages" . $select['join'] . "
            WHERE chat_messages.conversation_id = '" . e((int) $conversation['id']) . "'
                AND chat_messages.id > '" . e($since_id) . "'
            ORDER BY chat_messages.id ASC
            LIMIT 100");
    } else {
        $select = pg_chat_message_select();

        $messages = db_items("
            SELECT chat_messages.id, chat_messages.sender_kind, chat_messages.sender_user_id, chat_messages.body, chat_messages.created_at" . $select['cols'] . "
            FROM chat_messages" . $select['join'] . "
            WHERE chat_messages.conversation_id = '" . e((int) $conversation['id']) . "'
            ORDER BY chat_messages.id DESC
            LIMIT 50");

        $messages = array_reverse($messages);
    }

    $items = array();

    foreach ($messages as $message) {
        $items[] = pg_chat_message_payload($message);
    }

    // Presence is written at most once per 30 seconds, not on every poll
    // (never write on every request). With the condition inside the WHERE,
    // no separate query is needed to read the old value.
    $now = time();
    $my_seen_column = ($side == 'initiator') ? 'initiator_last_seen' : 'target_last_seen';

    db("UPDATE chat_conversations SET " . $my_seen_column . " = '" . e($now) . "'
        WHERE id = '" . e((int) $conversation['id']) . "'
            AND ('" . e($now) . "' - " . $my_seen_column . ") > 30");

    // The client requests mark_read when the window is open and focused;
    // the cursor only moves forward.
    if ($mark_read) {
        $my_read_column = ($side == 'initiator') ? 'initiator_last_read_id' : 'target_last_read_id';

        db("UPDATE chat_conversations SET " . $my_read_column . " = last_message_id
            WHERE id = '" . e((int) $conversation['id']) . "'
                AND last_message_id > " . $my_read_column);
    }

    // The typing signal is not a separate request; it piggybacks on the
    // poll. Columns arrive with 2026.4.3 — silently skipped on installs
    // that have not upgraded.
    $peer_typing = false;

    if (pg_chat_typing_ready()) {
        $my_typing_column = ($side == 'initiator') ? 'initiator_typing_until' : 'target_typing_until';
        $peer_typing_column = ($side == 'initiator') ? 'target_typing_until' : 'initiator_typing_until';

        if ($typing) {
            // 8-second window; not rewritten while the column is still fresh
            // (beyond now+5) — keeps the write count low.
            db("UPDATE chat_conversations SET " . $my_typing_column . " = '" . e($now + 8) . "'
                WHERE id = '" . e((int) $conversation['id']) . "'
                    AND " . $my_typing_column . " < '" . e($now + 5) . "'");
        } else {
            db("UPDATE chat_conversations SET " . $my_typing_column . " = 0
                WHERE id = '" . e((int) $conversation['id']) . "'
                    AND " . $my_typing_column . " > '" . e($now) . "'");
        }

        $peer_typing = (isset($conversation[$peer_typing_column]) && (int) $conversation[$peer_typing_column] > $now);
    }

    $peer = null;

    if ($conversation['channel'] == 'backend') {
        $peer = pg_chat_user_brief(pg_chat_user_row(pg_chat_peer_id($conversation, $me)));
    }

    // Delivered/seen ticks: the peer's read cursor. The row was read at the
    // start of the poll, so it can be at most one round stale — the cursor
    // only moves forward, so the client merges with Math.max.
    $peer_read_column = ($side == 'initiator') ? 'target_last_read_id' : 'initiator_last_read_id';

    return array('status' => 'success', 'data' => array(
        'status' => $conversation['status'],
        'messages' => $items,
        'peer' => $peer,
        'peer_typing' => $peer_typing,
        'peer_read_id' => (int) $conversation[$peer_read_column]
    ));
}

// ── Unread summary ───────────────────────────────────────────────────────

// The badge only needs "open conversations containing unread messages". A
// single OR query could not use an index, so two indexed queries are summed.
function pg_chat_unread_total()
{
    $me = (int) USER_ID;

    $as_target = (int) db_value("
        SELECT COUNT(*) FROM chat_conversations
        WHERE target_user_id = '" . e($me) . "'
            AND status = 'open'
            AND last_message_id > target_last_read_id");

    $as_initiator = (int) db_value("
        SELECT COUNT(*) FROM chat_conversations
        WHERE initiator_user_id = '" . e($me) . "'
            AND status = 'open'
            AND last_message_id > initiator_last_read_id");

    return $as_target + $as_initiator;
}

// ── Conversation list ────────────────────────────────────────────────────

function pg_chat_conversation_list()
{
    $me = (int) USER_ID;

    $where = "((c.initiator_user_id = '" . e($me) . "') OR (c.target_user_id = '" . e($me) . "'))";

    // Role 0 administrators see every conversation in the site channel.
    if ((int) USER_ROLE === 0) {
        $where = "(" . $where . " OR (c.channel = 'site'))";
    }

    $rows = db_items("
        SELECT
            c.id, c.channel, c.status,
            c.initiator_user_id, c.target_user_id, c.party_name, c.ip_address,
            c.last_message_id, c.last_message_at, c.last_message_preview,
            c.initiator_last_read_id, c.target_last_read_id,
            u.user_id AS peer_id,
            u.user_role AS peer_role,
            u.user_username AS peer_username,
            u.user_online_timestamp AS peer_online_timestamp,
            contacts.first_name AS first_name,
            contacts.last_name AS last_name,
            contacts.image AS image,
            contacts.file_id AS image_file_id,
            files.name AS image_file_name,
            op.user_username AS operator_username
        FROM chat_conversations c
        LEFT JOIN user u ON u.user_id = IF(c.initiator_user_id = '" . e($me) . "', c.target_user_id, c.initiator_user_id)
        LEFT JOIN contacts ON contacts.id = u.user_contact
        LEFT JOIN files ON files.id = contacts.file_id
        LEFT JOIN user op ON op.user_id = c.target_user_id
        WHERE " . $where . "
        ORDER BY c.last_message_at DESC, c.id DESC
        LIMIT 50");

    $conversations = array();

    foreach ($rows as $row) {
        $side = ((int) $row['initiator_user_id'] === $me) ? 'initiator' : 'target';
        $my_read = ($side == 'initiator') ? $row['initiator_last_read_id'] : $row['target_last_read_id'];

        if ($row['channel'] == 'site') {
            $title = ($row['party_name'] != '') ? $row['party_name'] : lang('Visitor') . ' #' . (int) $row['id'];
            $peer = null;
        } else {
            $first_name = isset($row['first_name']) ? $row['first_name'] : '';
            $last_name = isset($row['last_name']) ? $row['last_name'] : '';

            $peer = array(
                'id' => (int) $row['peer_id'],
                'name' => pg_chat_display_name($first_name, $last_name, $row['peer_username']),
                'role' => (int) $row['peer_role'],
                'role_label' => pg_chat_role_label($row['peer_role']),
                'avatar' => pg_chat_avatar_src(
                    isset($row['image']) ? $row['image'] : '',
                    isset($row['image_file_id']) ? $row['image_file_id'] : 0,
                    isset($row['image_file_name']) ? $row['image_file_name'] : ''
                ),
                'presence' => pg_chat_presence($row['peer_online_timestamp'])
            );

            $title = $peer['name'];
        }

        $conversations[] = array(
            'id' => (int) $row['id'],
            'channel' => $row['channel'],
            'status' => $row['status'],
            'title' => $title,
            'peer' => $peer,
            // Visitor IP, shown to staff in the conversation header. Site
            // channel only — backend peers are logged-in users, not
            // visitors. Captured once at conversation creation
            // (waf_client_ip, real address behind CDN/proxy).
            'ip_address' => (($row['channel'] == 'site') ? (string) $row['ip_address'] : ''),
            // Which operator a site conversation was addressed to. Since
            // role 0 sees all site conversations, this is carried explicitly
            // so another operator's conversation never looks like it was
            // addressed to the viewer.
            'operator' => (($row['channel'] == 'site') ? array(
                'id' => (int) $row['target_user_id'],
                'username' => (string) $row['operator_username']
            ) : null),
            // Hierarchical authority: only drives button visibility in the
            // window opened from this row (server actions verify on their
            // own).
            'can_manage' => (($row['channel'] == 'site')
                ? ((int) USER_ROLE <= 2)
                : ((int) USER_ROLE === 0 || ((int) USER_ROLE <= 2 && $row['peer_role'] !== null && (int) USER_ROLE <= (int) $row['peer_role']))),
            'preview' => $row['last_message_preview'],
            'last_message_at' => (int) $row['last_message_at'],
            'unread' => ((int) $row['last_message_id'] > (int) $my_read)
        );
    }

    return $conversations;
}

// ── Closing / deleting ───────────────────────────────────────────────────

// Closing: the staff side of the conversation (role <= 2). Role 3 cannot
// close.
function pg_chat_close($conversation_id)
{
    $conversation = pg_chat_conversation($conversation_id);

    if (!$conversation) {
        return array('status' => 'error', 'message' => lang('Conversation not found.'));
    }

    $me = (int) USER_ID;
    $side = pg_chat_side($conversation, $me);

    // Hierarchy: closing authority belongs to the higher (or equal) role.
    if (!$side || !pg_chat_can_manage($conversation)) {
        return array('status' => 'error', 'message' => lang('Access denied'));
    }

    if ($conversation['status'] == 'closed') {
        return array('status' => 'success', 'data' => array('closed' => true));
    }

    $now = time();

    // The closure is also written as a system message so it stays visible
    // in the history.
    db("INSERT INTO chat_messages
            (conversation_id, sender_kind, sender_user_id, body, created_at)
        VALUES
            ('" . e((int) $conversation['id']) . "', 'system', '0', '" . e(lang('Conversation closed.')) . "', '" . e($now) . "')");

    $message_id = mysqli_insert_id(db::$con);

    db("UPDATE chat_conversations SET
            status = 'closed',
            closed_at = '" . e($now) . "',
            closed_by = '" . e($me) . "',
            last_message_id = '" . e($message_id) . "',
            last_message_at = '" . e($now) . "',
            last_message_preview = '" . e(lang('Conversation closed.')) . "'
        WHERE id = '" . e((int) $conversation['id']) . "'");

    log_activity(lang('chat conversation closed') . ' (#' . (int) $conversation['id'] . ')', $_SESSION['sessionusername']);

    return array('status' => 'success', 'data' => array('closed' => true));
}

// Immediate deletion: permanent cleanup without waiting for the retention
// period (privacy/GDPR button). Role 0 can delete any conversation; staff
// (role <= 2) only conversations they are a side of.
function pg_chat_delete($conversation_id)
{
    $conversation = pg_chat_conversation($conversation_id);

    if (!$conversation) {
        return array('status' => 'error', 'message' => lang('Conversation not found.'));
    }

    $me = (int) USER_ID;
    $side = pg_chat_side($conversation, $me);

    // Hierarchy: deletion authority belongs to the higher (or equal) role;
    // role 0 can delete any conversation (top administrator).
    if ((int) USER_ROLE !== 0) {
        if (!$side || !pg_chat_can_manage($conversation)) {
            return array('status' => 'error', 'message' => lang('Access denied'));
        }
    }

    // Linked attachments are cleaned up too: attachment files from the
    // conversation's messages are removed from both disk and the files
    // table (Files screen). Note: a chat attachment must not be reused as
    // site content — deleting the conversation deletes the file as well.
    if (pg_chat_attachments_ready()) {
        $attachment_file_ids = db_values("
            SELECT attachment_file_id FROM chat_messages
            WHERE conversation_id = '" . e((int) $conversation['id']) . "'
                AND attachment_file_id > 0");

        foreach ($attachment_file_ids as $attachment_file_id) {
            $attachment_file = db_item("SELECT name FROM files WHERE id = '" . e((int) $attachment_file_id) . "'");

            if ($attachment_file && $attachment_file['name'] != '') {
                @unlink(FILE_DIRECTORY_PATH . '/' . $attachment_file['name']);
            }

            db("DELETE FROM files WHERE id = '" . e((int) $attachment_file_id) . "'");
        }
    }

    db("DELETE FROM chat_messages WHERE conversation_id = '" . e((int) $conversation['id']) . "'");
    db("DELETE FROM chat_conversations WHERE id = '" . e((int) $conversation['id']) . "'");

    log_activity(lang('chat conversation deleted') . ' (#' . (int) $conversation['id'] . ')', $_SESSION['sessionusername']);

    return array('status' => 'success', 'data' => array('deleted' => true));
}

// ── Empty conversation sweep ─────────────────────────────────────────────

// The normal flow produces no empty conversations (the row is created on
// the first message); this sweep collects legacy leftovers and possible
// race residue. The 5-minute guard avoids accidentally deleting a row whose
// first message is being written right now. Runs on panel open
// (chat_bootstrap); the table is small, the cost negligible.
function pg_chat_cleanup_empty()
{
    db("DELETE FROM chat_conversations
        WHERE last_message_id = 0
            AND created_at < '" . e(time() - 300) . "'");
}

// ── api.php dispatcher ───────────────────────────────────────────────────

// The chat_* cases in api.php delegate here. Session and token checks are
// done in api.php (validate_token); everything left here is application
// logic.
function pg_chat_handle_backend_action($action, $request)
{
    if (!defined('USER_LOGGED_IN') || !USER_LOGGED_IN) {
        return array('status' => 'error', 'message' => lang('Invalid login'));
    }

    if (!pg_chat_enabled()) {
        return array('status' => 'error', 'message' => lang('Chat is not available right now.'));
    }

    $conversation_id = isset($request['conversation_id']) ? (int) $request['conversation_id'] : 0;

    switch ($action) {

        case 'chat_bootstrap':
            pg_chat_cleanup_empty();

            return array('status' => 'success', 'data' => array(
                'me' => array('id' => (int) USER_ID, 'role' => (int) USER_ROLE),
                'unread' => pg_chat_unread_total(),
                'online_users' => pg_chat_online_users(),
                'conversations' => pg_chat_conversation_list()
            ));

        case 'chat_online_users':
            return array('status' => 'success', 'data' => array('online_users' => pg_chat_online_users()));

        case 'chat_open':
            $target_user_id = isset($request['target_user_id']) ? (int) $request['target_user_id'] : 0;
            return pg_chat_open_backend_conversation($target_user_id);

        case 'chat_send':
            $body = isset($request['body']) ? $request['body'] : '';
            $target_user_id = isset($request['target_user_id']) ? (int) $request['target_user_id'] : 0;
            return pg_chat_send($conversation_id, $body, $target_user_id);

        case 'chat_poll':
            $since_id = isset($request['since_id']) ? (int) $request['since_id'] : 0;
            $mark_read = !empty($request['mark_read']);
            $typing = !empty($request['typing']);
            return pg_chat_poll($conversation_id, $since_id, $mark_read, $typing);

        case 'chat_unread_check':
            return array('status' => 'success', 'data' => array('unread' => pg_chat_unread_total()));

        case 'chat_mark_read':
            return pg_chat_poll($conversation_id, isset($request['since_id']) ? (int) $request['since_id'] : 0, true);

        case 'chat_conversations':
            return array('status' => 'success', 'data' => array('conversations' => pg_chat_conversation_list()));

        case 'chat_close':
            return pg_chat_close($conversation_id);

        case 'chat_delete':
            return pg_chat_delete($conversation_id);

        case 'chat_attach':
            $name = isset($request['name']) ? $request['name'] : '';
            $data = isset($request['data']) ? $request['data'] : '';
            return pg_chat_attach($conversation_id, $name, $data);
    }

    return array('status' => 'error', 'message' => 'Unknown action.');
}

// Panel-side attachment upload: EXISTING conversations only (no attaching
// to a draft — a message must be written first). Staff may send both images
// and files freely; the allowlist, size and content checks still apply.
function pg_chat_attach($conversation_id, $name, $data)
{
    $conversation = pg_chat_conversation($conversation_id);

    if (!$conversation) {
        return array('status' => 'error', 'message' => lang('Conversation not found.'));
    }

    $me = (int) USER_ID;
    $side = pg_chat_side($conversation, $me);

    if (!$side) {
        return array('status' => 'error', 'message' => lang('Conversation not found.'));
    }

    if ($conversation['status'] != 'open') {
        return array('status' => 'error', 'message' => lang('This conversation is closed.'));
    }

    if ($conversation['channel'] == 'backend') {
        $peer_role = db_value("SELECT user_role FROM user WHERE user_id = '" . e(pg_chat_peer_id($conversation, $me)) . "'");

        if ($peer_role === null || !pg_chat_can_pair(USER_ROLE, $peer_role)) {
            return array('status' => 'error', 'message' => lang('You cannot chat with this user.'));
        }
    }

    if (pg_chat_rate_limited()) {
        return array('status' => 'error', 'message' => lang('You are sending messages too quickly. Please wait a moment.'));
    }

    // Staff (role <= 2) sends without limits; panel users (role 3) follow
    // the visitor rules: 1 file per conversation, limited images.
    if ((int) USER_ROLE >= 3 && pg_chat_attachments_ready()) {
        $kind = pg_chat_upload_kind($name);

        if ($kind != '') {
            // Role 3 panel users are bound by the visitor image limit too.
            $limit = ($kind == 'file') ? 1 : (defined('CHAT_VISITOR_IMAGE_LIMIT') ? max(1, (int) CHAT_VISITOR_IMAGE_LIMIT) : 5);

            $count = (int) db_value("
                SELECT COUNT(*) FROM chat_messages
                WHERE conversation_id = '" . e((int) $conversation['id']) . "'
                    AND attachment_kind = '" . e($kind) . "'
                    AND sender_user_id = '" . e($me) . "'");

            if ($count >= $limit) {
                return array('status' => 'error', 'message' => lang('File limit reached for this conversation.'));
            }
        }
    }

    $stored = pg_chat_store_upload($name, $data, true, true);

    if (isset($stored['error'])) {
        return array('status' => 'error', 'message' => $stored['error']);
    }

    return array('status' => 'success', 'data' => pg_chat_insert_attachment_message($conversation, $side, 'user', $me, $stored));
}

// ── Backend launcher rendering ───────────────────────────────────────────

// Called by output_footer(). Every string is translated here with lang()
// and handed to JS as JSON config; chat_backend.src.js contains no text.
// The CSS is deliberately inline: the launcher is on every panel page and
// too small to justify a separate CSS request.
function pg_chat_render_backend_launcher()
{
    if (!pg_chat_enabled()) {
        return '';
    }

    // AI chat (Cloudflare AutoRAG chat-page-snippet). Staff only
    // (role <= 2); it sits in the list as an always-online "person" and,
    // when opened, the snippet's own UI runs inside the window. The URLs
    // can be overridden in config.php via CHAT_AI_SCRIPT_URL /
    // CHAT_AI_API_URL.
    $ai = null;

    if ((int) USER_ROLE <= 2) {
        $ai = array(
            'label' => 'AI Chat',
            'script' => defined('CHAT_AI_SCRIPT_URL') ? CHAT_AI_SCRIPT_URL : 'https://f6eda156-883d-45b2-9c7e-e7f09bd50f24.search.ai.cloudflare.com/assets/v0.0.40/search-snippet.es.js',
            'api' => defined('CHAT_AI_API_URL') ? CHAT_AI_API_URL : 'https://f6eda156-883d-45b2-9c7e-e7f09bd50f24.search.ai.cloudflare.com/'
        );
    }

    $config = array(
        'api_url' => PATH . SOFTWARE_DIRECTORY . '/api.php',
        'token' => isset($_SESSION['software']['token']) ? $_SESSION['software']['token'] : '',
        'me' => array('id' => (int) USER_ID, 'role' => (int) USER_ROLE),
        'ai' => $ai,
        'attach' => array('enabled' => pg_chat_attachments_ready(), 'max' => 5242880),
        'poll' => array('badge' => 60, 'list' => 15, 'conversation' => 5),
        'strings' => array(
            'chat' => lang('Chat'),
            'online_users' => lang('Online Users'),
            'conversations' => lang('Conversations'),
            'no_online_users' => lang('No one else is online right now.'),
            'no_conversations' => lang('No conversations yet.'),
            'type_a_message' => lang('Type a message'),
            'send' => lang('Send'),
            'back' => lang('Back'),
            'close_conversation' => lang('Close Conversation'),
            'delete_conversation' => lang('Delete Conversation'),
            'delete_confirm' => lang('This conversation will be permanently deleted. Continue?'),
            'conversation_closed' => lang('This conversation is closed.'),
            'delete' => lang('Delete'),
            'cancel' => lang('Cancel'),
            'loading' => lang('Loading'),
            'online' => lang('Online'),
            'away' => lang('Away'),
            'offline' => lang('Offline'),
            'site' => lang('Site'),
            'panel' => lang('Panel'),
            'start_chat' => lang('Start Chat'),
            'typing' => lang('Typing'),
            'close' => lang('Close'),
            'tab_users' => lang('Users'),
            'tab_conversations' => lang('Conversations'),
            'attach' => lang('Attach File'),
            'file_too_big' => lang('File is too large.'),
            // The "Delivered" language key belongs to shipping delivery —
            // the chat tick needs its own keys.
            'delivered' => lang('Message delivered'),
            'seen' => lang('Message seen'),
            'ip' => lang('IP')
        )
    );

    $js_file = 'assets/chat_backend.' . ENVIRONMENT_SUFFIX . '.js';
    $js_url = PATH . SOFTWARE_DIRECTORY . '/' . $js_file . '?v=' . @filemtime(dirname(__FILE__) . '/' . $js_file);

    $output = '
        <div id="pg-chat-root" class="d-print-none"></div>
        <style>
            #pg-chat-root .pg-chat-launcher { position: fixed; right: 24px; bottom: 24px; z-index: 1080; width: 58px; height: 58px; border-radius: 50%; border: 0; color: #fff; background: linear-gradient(135deg, var(--bs-primary, #0d6efd), #6f42c1); box-shadow: 0 8px 24px rgba(13,110,253,.35); font-size: 22px; transition: transform .15s ease, box-shadow .15s ease; }
            #pg-chat-root .pg-chat-launcher:hover { transform: translateY(-2px) scale(1.04); box-shadow: 0 12px 28px rgba(13,110,253,.45); }
            #pg-chat-root .pg-chat-launcher:focus-visible { outline: 2px solid rgba(13,110,253,.5); outline-offset: 2px; }
            #pg-chat-root .pg-chat-badge { position: absolute; top: -4px; right: -4px; min-width: 20px; height: 20px; border-radius: 10px; background: #dc3545; color: #fff; font-size: 11px; line-height: 20px; padding: 0 5px; display: none; border: 2px solid var(--bs-body-bg, #fff); }
            #pg-chat-root .pg-chat-panel { position: fixed; right: 24px; bottom: 96px; z-index: 1080; width: 372px; max-width: calc(100vw - 32px); height: 544px; max-height: calc(100vh - 128px); display: none; flex-direction: column; overflow: hidden; border: 0; border-radius: 18px; box-shadow: 0 18px 48px rgba(0,0,0,.35); --bs-card-inner-border-radius: calc(var(--bs-border-radius-lg) - (var(--bs-border-width))); }
            #pg-chat-root .pg-chat-panel.pg-chat-open { display: flex; animation: pgChatPop .18s ease-out; }
            @keyframes pgChatPop { from { opacity: 0; transform: translateY(10px) scale(.98); } to { opacity: 1; transform: none; } }
            #pg-chat-root .pg-chat-tabs { gap: 4px; }
            #pg-chat-root .pg-chat-tabs .nav-link { border: 0; border-radius: 9px; color: var(--bs-secondary-color, #6c757d); font-size: 12.5px; font-weight: 600; padding: 4px 10px; }
            #pg-chat-root .pg-chat-tabs .nav-link.active { color: var(--bs-primary, #0d6efd); background: rgba(13,110,253,.15); }
            #pg-chat-root .pg-chat-body { flex: 1 1 auto; overflow-y: auto; overscroll-behavior: contain; }
            #pg-chat-root .pg-chat-row { border-radius: 12px; margin: 2px 6px; cursor: pointer; transition: background .12s ease; }
            #pg-chat-root .pg-chat-row:hover { background: var(--bs-secondary-bg, #e9ecef); }
            #pg-chat-root .pg-chat-avatar { width: 34px; height: 34px; object-fit: cover; }
            #pg-chat-root .pg-chat-ai-avatar { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; background: linear-gradient(135deg, #20c997, #0dcaf0); font-size: 16px; }
            #pg-chat-root .pg-chat-ai-frame { flex: 1 1 auto; overflow-y: auto; overscroll-behavior: contain; }
            #pg-chat-root .pg-chat-ai-frame chat-page-snippet { display: block; height: 100%; }
            #pg-chat-root .pg-chat-dot { width: 10px; height: 10px; bottom: 0; right: 0; }
            #pg-chat-root .pg-chat-dot-online { background: #10b981; }
            #pg-chat-root .pg-chat-dot-away { background: #f59e0b; }
            #pg-chat-root .pg-chat-dot-offline { background: #a1a1a1; }
            #pg-chat-root .pg-chat-bubble { max-width: 80%; white-space: pre-wrap; overflow-wrap: break-word; border-radius: 16px; padding: 7px 11px; font-size: 13px; box-shadow: 0 1px 2px rgba(0,0,0,.08); }
            #pg-chat-root .pg-chat-bubble-me { background: linear-gradient(135deg, var(--bs-primary, #0d6efd), #6f42c1); color: #fff; border-bottom-right-radius: 5px; }
            #pg-chat-root .pg-chat-tick { display: inline-block; margin-left: 7px; font-size: 11px; line-height: 1; color: rgba(255,255,255,.65); letter-spacing: -2px; white-space: nowrap; vertical-align: baseline; }
            #pg-chat-root .pg-chat-tick-seen { color: #9ff3ff; }
            #pg-chat-root .pg-chat-bubble-peer { background: var(--bs-secondary-bg, #e9ecef); color: var(--bs-body-color, #212529); border-bottom-left-radius: 5px; }
            #pg-chat-root .pg-chat-bubble-system { background: transparent; color: var(--bs-secondary-color, #6c757d); font-size: 12px; text-align: center; max-width: 100%; box-shadow: none; }
            #pg-chat-root .pg-chat-compose textarea { resize: none; height: 38px; max-height: 96px; font-size: 13px; border-radius: 10px; }
            #pg-chat-root .pg-chat-compose input[type="file"] { display: none !important; }
            #pg-chat-root .pg-chat-compose .btn { border-radius: 10px; }
            #pg-chat-root .pg-chat-row-unread { font-weight: 600; }
        </style>
        <script type="application/json" id="pg-chat-config">' . encode_json($config) . '</script>
        <script src="' . h($js_url) . '" defer></script>';

    return $output;
}

// ═════════════════════════════════════════════════════════════════════════
// SITE CHANNEL — visitor / member ↔ operator
// ═════════════════════════════════════════════════════════════════════════

// Is site chat enabled as a whole? The master switch (CHAT_ENABLED) is
// absolute; the site bubble additionally requires its own switch and a
// valid operator.
function pg_chat_site_enabled()
{
    return (pg_chat_enabled()
        && defined('CHAT_SITE_ENABLED') && CHAT_SITE_ENABLED
        && defined('CHAT_OPERATOR_USER_ID') && CHAT_OPERATOR_USER_ID > 0);
}

function pg_chat_site_operator()
{
    static $operator = null;
    static $loaded = false;

    if (!$loaded) {
        $loaded = true;
        $operator = pg_chat_user_row(CHAT_OPERATOR_USER_ID);
    }

    return $operator;
}

function pg_chat_site_operator_online()
{
    $operator = pg_chat_site_operator();

    return ($operator && pg_chat_presence($operator['online_timestamp']) == 'online');
}

// The visitor's display name/email. From the contact record for members;
// from the (optional) identity stored in the session for anonymous
// visitors.
function pg_chat_site_identity()
{
    if (defined('USER_LOGGED_IN') && USER_LOGGED_IN) {
        $name = '';

        if (defined('USER_CONTACT_ID') && USER_CONTACT_ID != '') {
            $contact = db_item("SELECT first_name, last_name FROM contacts WHERE id = '" . e((int) USER_CONTACT_ID) . "'");

            if ($contact) {
                $name = trim($contact['first_name'] . ' ' . $contact['last_name']);
            }
        }

        if ($name == '') {
            $name = USER_USERNAME;
        }

        return array('member' => true, 'name' => $name, 'email' => USER_EMAIL_ADDRESS);
    }

    return array(
        'member' => false,
        'name' => isset($_SESSION['chat']['site_name']) ? $_SESSION['chat']['site_name'] : '',
        'email' => isset($_SESSION['chat']['site_email']) ? $_SESSION['chat']['site_email'] : ''
    );
}

// The visitor's OPEN site conversation (null when none). Anonymous
// ownership is established through the id in the session (same pattern as
// the cart's order_id); for members initiator_user_id is permanent.
function pg_chat_site_conversation()
{
    if (defined('USER_LOGGED_IN') && USER_LOGGED_IN) {
        $conversation = db_item("
            SELECT * FROM chat_conversations
            WHERE channel = 'site'
                AND status = 'open'
                AND initiator_user_id = '" . e((int) USER_ID) . "'
            ORDER BY id DESC
            LIMIT 1");

        return $conversation ? $conversation : null;
    }

    if (empty($_SESSION['chat']['site_conversation_id'])) {
        return null;
    }

    $conversation = pg_chat_conversation((int) $_SESSION['chat']['site_conversation_id']);

    if (!$conversation
        || $conversation['channel'] != 'site'
        || $conversation['status'] != 'open'
        || (int) $conversation['initiator_user_id'] !== 0
    ) {
        unset($_SESSION['chat']['site_conversation_id']);

        return null;
    }

    return $conversation;
}

// Does the client-supplied id really belong to this visitor? Non-owners
// must not even learn the conversation exists.
function pg_chat_site_own_conversation($conversation_id)
{
    $conversation = pg_chat_conversation((int) $conversation_id);

    if (!$conversation || $conversation['channel'] != 'site') {
        return null;
    }

    if (defined('USER_LOGGED_IN') && USER_LOGGED_IN) {
        return ((int) $conversation['initiator_user_id'] === (int) USER_ID) ? $conversation : null;
    }

    if (!empty($_SESSION['chat']['site_conversation_id'])
        && (int) $_SESSION['chat']['site_conversation_id'] === (int) $conversation['id']
        && (int) $conversation['initiator_user_id'] === 0
    ) {
        return $conversation;
    }

    return null;
}

function pg_chat_site_message_payload($row)
{
    $mine = false;

    if ($row['sender_kind'] == 'visitor') {
        $mine = true;
    } elseif ($row['sender_kind'] == 'user'
        && defined('USER_LOGGED_IN') && USER_LOGGED_IN
        && (int) $row['sender_user_id'] === (int) USER_ID
    ) {
        $mine = true;
    }

    return array(
        'id' => (int) $row['id'],
        'kind' => $row['sender_kind'],
        'mine' => $mine,
        'body' => $row['body'],
        'created_at' => (int) $row['created_at'],
        'attachment' => pg_chat_attachment_payload($row)
    );
}

function pg_chat_site_messages($conversation_id, $since_id)
{
    $since_id = (int) $since_id;

    $select = pg_chat_message_select();

    if ($since_id > 0) {
        $rows = db_items("
            SELECT chat_messages.id, chat_messages.sender_kind, chat_messages.sender_user_id, chat_messages.body, chat_messages.created_at" . $select['cols'] . "
            FROM chat_messages" . $select['join'] . "
            WHERE chat_messages.conversation_id = '" . e((int) $conversation_id) . "'
                AND chat_messages.id > '" . e($since_id) . "'
            ORDER BY chat_messages.id ASC
            LIMIT 100");
    } else {
        $rows = array_reverse(db_items("
            SELECT chat_messages.id, chat_messages.sender_kind, chat_messages.sender_user_id, chat_messages.body, chat_messages.created_at" . $select['cols'] . "
            FROM chat_messages" . $select['join'] . "
            WHERE chat_messages.conversation_id = '" . e((int) $conversation_id) . "'
            ORDER BY chat_messages.id DESC
            LIMIT 50"));
    }

    $messages = array();

    foreach ($rows as $row) {
        $messages[] = pg_chat_site_message_payload($row);
    }

    return $messages;
}

// Site-side rate limit: at least 1 second between sends, at most 20
// messages per 2 minutes. The counter lives in the session — no extra
// database writes.
function pg_chat_site_rate_limited()
{
    $now = time();

    $last = isset($_SESSION['chat']['site_last_send']) ? (int) $_SESSION['chat']['site_last_send'] : 0;

    if (($now - $last) < 1) {
        return true;
    }

    if (!isset($_SESSION['chat']['site_send_times']) || !is_array($_SESSION['chat']['site_send_times'])) {
        $_SESSION['chat']['site_send_times'] = array();
    }

    $recent = array();

    foreach ($_SESSION['chat']['site_send_times'] as $timestamp) {
        if (($now - (int) $timestamp) < 120) {
            $recent[] = (int) $timestamp;
        }
    }

    if (count($recent) >= 20) {
        $_SESSION['chat']['site_send_times'] = $recent;

        return true;
    }

    $recent[] = $now;
    $_SESSION['chat']['site_send_times'] = $recent;
    $_SESSION['chat']['site_last_send'] = $now;

    return false;
}

// Visitor sent a message. The conversation row is created on the FIRST
// message (same empty-conversation rule as the backend). When the operator
// is offline, ONCE PER CONVERSATION only: system message + bell
// notification + (when enabled) an email to the operator.
function pg_chat_site_send($conversation_id, $body, $page_url)
{
    $identity = pg_chat_site_identity();

    // Anonymous visitors cannot send before solving the captcha.
    // code=captcha makes the client open the puzzle. Never asked of
    // members.
    if (!$identity['member']
        && defined('CHAT_CAPTCHA') && CHAT_CAPTCHA
        && !pg_puzzle_captcha_solved('site_chat')
    ) {
        return array('status' => 'error', 'code' => 'captcha', 'message' => lang('Slide the piece into place to continue'));
    }

    $body = trim((string) $body);

    if ($body == '') {
        return array('status' => 'error', 'message' => lang('Message is empty.'));
    }

    if (mb_strlen($body) > 2000) {
        return array('status' => 'error', 'message' => lang('Message is too long.'));
    }

    if (pg_chat_site_rate_limited()) {
        return array('status' => 'error', 'message' => lang('You are sending messages too quickly. Please wait a moment.'));
    }

    $conversation = null;

    if ((int) $conversation_id > 0) {
        $conversation = pg_chat_site_own_conversation($conversation_id);

        if ($conversation && $conversation['status'] != 'open') {
            $conversation = null;
        }
    }

    if (!$conversation) {
        $conversation = pg_chat_site_conversation();
    }

    $is_new_conversation = false;
    $now = time();

    if (!$conversation) {
        $operator = pg_chat_site_operator();

        if (!$operator) {
            return array('status' => 'error', 'message' => lang('Chat is not available right now.'));
        }

        $ip_address = function_exists('waf_client_ip') ? waf_client_ip() : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');

        db("INSERT INTO chat_conversations
                (channel, status, initiator_user_id, target_user_id, party_name, party_email, ip_address, page_url, created_at)
            VALUES
                ('site', 'open',
                 '" . e($identity['member'] ? (int) USER_ID : 0) . "',
                 '" . e((int) CHAT_OPERATOR_USER_ID) . "',
                 '" . e(mb_substr($identity['name'], 0, 100)) . "',
                 '" . e(mb_substr($identity['email'], 0, 255)) . "',
                 '" . e(mb_substr($ip_address, 0, 45)) . "',
                 '" . e(mb_substr((string) $page_url, 0, 500)) . "',
                 '" . e($now) . "')");

        $conversation = pg_chat_conversation(mysqli_insert_id(db::$con));
        $is_new_conversation = true;

        if (!$identity['member']) {
            $_SESSION['chat']['site_conversation_id'] = (int) $conversation['id'];
        }
    }

    $sender_kind = $identity['member'] ? 'user' : 'visitor';
    $sender_user_id = $identity['member'] ? (int) USER_ID : 0;

    db("INSERT INTO chat_messages
            (conversation_id, sender_kind, sender_user_id, body, created_at)
        VALUES
            ('" . e((int) $conversation['id']) . "', '" . $sender_kind . "', '" . e($sender_user_id) . "', '" . e($body) . "', '" . e($now) . "')");

    $message_id = mysqli_insert_id(db::$con);
    $preview = mb_substr(str_replace(array("\r", "\n"), ' ', $body), 0, 120);

    db("UPDATE chat_conversations SET
            last_message_id = '" . e($message_id) . "',
            last_message_at = '" . e($now) . "',
            last_message_preview = '" . e($preview) . "',
            initiator_last_read_id = '" . e($message_id) . "',
            initiator_last_seen = '" . e($now) . "'
        WHERE id = '" . e((int) $conversation['id']) . "'");

    $messages = array(array(
        'id' => (int) $message_id,
        'kind' => $sender_kind,
        'mine' => true,
        'body' => $body,
        'created_at' => $now
    ));

    if ($is_new_conversation && !pg_chat_site_operator_online()) {
        $offline_note = lang('We are offline right now. Leave a message and we will get back to you.');

        db("INSERT INTO chat_messages
                (conversation_id, sender_kind, sender_user_id, body, created_at)
            VALUES
                ('" . e((int) $conversation['id']) . "', 'system', '0', '" . e($offline_note) . "', '" . e($now) . "')");

        $system_message_id = mysqli_insert_id(db::$con);

        db("UPDATE chat_conversations SET
                last_message_id = '" . e($system_message_id) . "',
                initiator_last_read_id = '" . e($system_message_id) . "'
            WHERE id = '" . e((int) $conversation['id']) . "'");

        $messages[] = array(
            'id' => (int) $system_message_id,
            'kind' => 'system',
            'mine' => false,
            'body' => $offline_note,
            'created_at' => $now
        );

        $operator = pg_chat_site_operator();

        // The bell notification goes to the operator; it stays in the panel
        // until read.
        if (function_exists('create_notification')) {
            create_notification(array(
                'action' => 'custom',
                'title' => lang('New site chat message') . ': ' . h($preview),
                'user' => $operator['username']
            ));
        }

        // Email: silently skipped when no address is configured.
        if (defined('CHAT_OFFLINE_EMAIL') && CHAT_OFFLINE_EMAIL
            && isset($operator['email_address']) && $operator['email_address'] != ''
            && function_exists('email')
        ) {
            // PHPMailer rejects an empty From address, so one must always be
            // supplied — an anonymous visitor may not have entered an email.
            // From is the site's own address (falling back to the operator's)
            // so SPF/DKIM stay valid; the visitor's address, when present and
            // valid, goes into Reply-To so the operator can reply directly.
            $from_email_address = (defined('EMAIL_ADDRESS') && EMAIL_ADDRESS != '')
                ? EMAIL_ADDRESS
                : $operator['email_address'];

            $reply_to = ($identity['email'] != '' && filter_var($identity['email'], FILTER_VALIDATE_EMAIL))
                ? $identity['email']
                : '';

            email(array(
                'to' => $operator['email_address'],
                'from_email_address' => $from_email_address,
                'from_name' => (defined('TITLE') && TITLE != '') ? TITLE : lang('Live Support'),
                'reply_to' => $reply_to,
                'subject' => lang('New site chat message'),
                'body' => $preview . "\n\n" . URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/welcome.php'
            ));
        }
    }

    return array('status' => 'success', 'data' => array(
        'conversation_id' => (int) $conversation['id'],
        'messages' => $messages,
        'operator_online' => pg_chat_site_operator_online()
    ));
}

function pg_chat_site_poll($conversation_id, $since_id, $typing, $seen = true)
{
    $conversation = pg_chat_site_own_conversation($conversation_id);

    if (!$conversation) {
        return array('status' => 'error', 'code' => 'gone', 'message' => lang('Conversation not found.'));
    }

    $now = time();

    // Background poll while the window is CLOSED ($seen = false) serves the
    // badge/sound only: no message bodies are fetched, the read cursor and
    // presence are NOT written — otherwise messages would show as "seen" on
    // the operator side without ever being opened, and the unread badge
    // would never light up.
    if (!$seen) {
        $unread = 0;

        if ((int) $conversation['last_message_id'] > (int) $conversation['initiator_last_read_id']) {
            $unread = (int) db_value("
                SELECT COUNT(*) FROM chat_messages
                WHERE conversation_id = '" . e((int) $conversation['id']) . "'
                    AND id > '" . e((int) $conversation['initiator_last_read_id']) . "'");
        }

        return array('status' => 'success', 'data' => array(
            'status' => $conversation['status'],
            'messages' => array(),
            'operator_online' => pg_chat_site_operator_online(),
            'peer_typing' => false,
            'peer_read_id' => (int) $conversation['target_last_read_id'],
            'unread' => $unread
        ));
    }

    $messages = pg_chat_site_messages($conversation['id'], $since_id);

    // With the window open the poll is already arriving: the read cursor is
    // advanced and presence is written throttled (same rules as the
    // backend).
    db("UPDATE chat_conversations SET initiator_last_read_id = last_message_id
        WHERE id = '" . e((int) $conversation['id']) . "'
            AND last_message_id > initiator_last_read_id");

    db("UPDATE chat_conversations SET initiator_last_seen = '" . e($now) . "'
        WHERE id = '" . e((int) $conversation['id']) . "'
            AND ('" . e($now) . "' - initiator_last_seen) > 30");

    $peer_typing = false;

    if (pg_chat_typing_ready()) {
        if ($typing) {
            db("UPDATE chat_conversations SET initiator_typing_until = '" . e($now + 8) . "'
                WHERE id = '" . e((int) $conversation['id']) . "'
                    AND initiator_typing_until < '" . e($now + 5) . "'");
        } else {
            db("UPDATE chat_conversations SET initiator_typing_until = 0
                WHERE id = '" . e((int) $conversation['id']) . "'
                    AND initiator_typing_until > '" . e($now) . "'");
        }

        $peer_typing = (isset($conversation['target_typing_until']) && (int) $conversation['target_typing_until'] > $now);
    }

    return array('status' => 'success', 'data' => array(
        'status' => $conversation['status'],
        'messages' => $messages,
        'operator_online' => pg_chat_site_operator_online(),
        'peer_typing' => $peer_typing,
        'peer_read_id' => (int) $conversation['target_last_read_id'],
        'unread' => 0
    ));
}

// Visitor attachment upload. Rules:
//  - EXISTING open conversations only (at least one message must have been
//    written first) — a bot cannot upload as its opening move.
//  - Image/file permissions come from the site settings
//    (CHAT_ALLOW_IMAGES/FILES).
//  - Name + email are REQUIRED for anonymous visitors (when skipped, the
//    client shows the fields again as mandatory; the server also validates
//    here) and there is a 1-file-per-visitor limit.
//  - Images are limited per conversation (see below). The captcha must be
//    solved.
function pg_chat_site_attach($conversation_id, $name, $data)
{
    if (!pg_chat_attachments_ready()) {
        return array('status' => 'error', 'message' => lang('Attachments are disabled.'));
    }

    $identity = pg_chat_site_identity();

    if (!$identity['member']
        && defined('CHAT_CAPTCHA') && CHAT_CAPTCHA
        && !pg_puzzle_captcha_solved('site_chat')
    ) {
        return array('status' => 'error', 'code' => 'captcha', 'message' => lang('Slide the piece into place to continue'));
    }

    $conversation = null;

    if ((int) $conversation_id > 0) {
        $conversation = pg_chat_site_own_conversation($conversation_id);
    }

    if (!$conversation) {
        $conversation = pg_chat_site_conversation();
    }

    if (!$conversation || $conversation['status'] != 'open') {
        return array('status' => 'error', 'message' => lang('Write a message first.'));
    }

    if (pg_chat_site_rate_limited()) {
        return array('status' => 'error', 'message' => lang('You are sending messages too quickly. Please wait a moment.'));
    }

    $allow_images = (defined('CHAT_ALLOW_IMAGES') && CHAT_ALLOW_IMAGES) ? true : false;
    $allow_files = (defined('CHAT_ALLOW_FILES') && CHAT_ALLOW_FILES) ? true : false;

    $kind = pg_chat_upload_kind($name);

    if ($kind == '' || ($kind == 'image' && !$allow_images) || ($kind == 'file' && !$allow_files)) {
        return array('status' => 'error', 'message' => lang('This file type is not allowed.'));
    }

    // Identity requirement: an anonymous visitor must have provided name +
    // email for EVERY attachment kind (images included). The client shows
    // the fields again; this check is the final gate, independent of the
    // client.
    if (!$identity['member'] && ($identity['name'] == '' || $identity['email'] == '')) {
        return array('status' => 'error', 'code' => 'identity', 'message' => lang('Please enter your name and email before sending a file.'));
    }

    // The visitor side's existing attachments in this conversation (for
    // the limits).
    $sender_condition = $identity['member']
        ? "sender_kind = 'user' AND sender_user_id = '" . e((int) USER_ID) . "'"
        : "sender_kind = 'visitor'";

    if ($kind == 'file') {
        $file_count = (int) db_value("
            SELECT COUNT(*) FROM chat_messages
            WHERE conversation_id = '" . e((int) $conversation['id']) . "'
                AND attachment_kind = 'file'
                AND " . $sender_condition);

        if ($file_count >= 1) {
            return array('status' => 'error', 'message' => lang('File limit reached for this conversation.'));
        }
    } else {
        // The image limit comes from the site settings (default 5, min 1).
        $image_limit = defined('CHAT_VISITOR_IMAGE_LIMIT') ? max(1, (int) CHAT_VISITOR_IMAGE_LIMIT) : 5;

        $image_count = (int) db_value("
            SELECT COUNT(*) FROM chat_messages
            WHERE conversation_id = '" . e((int) $conversation['id']) . "'
                AND attachment_kind = 'image'
                AND " . $sender_condition);

        if ($image_count >= $image_limit) {
            return array('status' => 'error', 'message' => lang('File limit reached for this conversation.'));
        }
    }

    $stored = pg_chat_store_upload($name, $data, $allow_images, $allow_files);

    if (isset($stored['error'])) {
        return array('status' => 'error', 'message' => $stored['error']);
    }

    $sender_kind = $identity['member'] ? 'user' : 'visitor';
    $sender_user_id = $identity['member'] ? (int) USER_ID : 0;

    $payload = pg_chat_insert_attachment_message($conversation, 'initiator', $sender_kind, $sender_user_id, $stored);
    $payload['operator_online'] = pg_chat_site_operator_online();

    return array('status' => 'success', 'data' => $payload);
}

// CSRF for site actions: everything except bootstrap requires the session
// token (initialize_token() generates one for every visitor; bootstrap
// hands it to the client).
function pg_chat_site_token_ok($request)
{
    return (isset($request['token'])
        && isset($_SESSION['software']['token'])
        && $_SESSION['software']['token'] != ''
        && (string) $request['token'] === (string) $_SESSION['software']['token']);
}

function pg_chat_handle_site_action($action, $request)
{
    if (!pg_chat_site_enabled()) {
        return array('status' => 'error', 'message' => lang('Chat is not available right now.'));
    }

    if ($action != 'site_chat_bootstrap' && !pg_chat_site_token_ok($request)) {
        return array('status' => 'error', 'message' => 'Invalid token.');
    }

    $conversation_id = isset($request['conversation_id']) ? (int) $request['conversation_id'] : 0;

    switch ($action) {

        case 'site_chat_bootstrap':
            $identity = pg_chat_site_identity();
            $conversation = pg_chat_site_conversation();

            $data = array(
                'token' => isset($_SESSION['software']['token']) ? $_SESSION['software']['token'] : '',
                'operator_online' => pg_chat_site_operator_online(),
                'welcome' => defined('CHAT_WELCOME_MESSAGE') ? CHAT_WELCOME_MESSAGE : '',
                'me' => $identity,
                'captcha_required' => (!$identity['member']
                    && defined('CHAT_CAPTCHA') && CHAT_CAPTCHA
                    && !pg_puzzle_captcha_solved('site_chat')),
                'conversation' => null
            );

            if ($conversation) {
                $data['conversation'] = array(
                    'id' => (int) $conversation['id'],
                    'status' => $conversation['status'],
                    'peer_read_id' => (int) $conversation['target_last_read_id'],
                    'messages' => pg_chat_site_messages($conversation['id'], 0)
                );
            }

            return array('status' => 'success', 'data' => $data);

        case 'site_chat_captcha':
            $op = isset($request['op']) ? $request['op'] : 'challenge';

            if ($op == 'verify') {
                return array('status' => 'success', 'data' => pg_puzzle_captcha_verify('site_chat', isset($request['position']) ? $request['position'] : -1));
            }

            return array('status' => 'success', 'data' => pg_puzzle_captcha_challenge('site_chat'));

        case 'site_chat_send':
            $body = isset($request['body']) ? $request['body'] : '';
            $page_url = isset($request['page_url']) ? $request['page_url'] : '';

            return pg_chat_site_send($conversation_id, $body, $page_url);

        case 'site_chat_poll':
            $since_id = isset($request['since_id']) ? (int) $request['since_id'] : 0;
            $typing = !empty($request['typing']);

            // When seen is absent (older cached client) the window is
            // assumed open — legacy behavior is preserved exactly.
            $seen = !isset($request['seen']) || !empty($request['seen']);

            return pg_chat_site_poll($conversation_id, $since_id, $typing, $seen);

        case 'site_chat_attach':
            $name = isset($request['name']) ? $request['name'] : '';
            $data = isset($request['data']) ? $request['data'] : '';

            return pg_chat_site_attach($conversation_id, $name, $data);

        case 'site_chat_update_identity':
            // A member's identity comes from the account; this endpoint is
            // for anonymous visitors.
            if (defined('USER_LOGGED_IN') && USER_LOGGED_IN) {
                return array('status' => 'success', 'data' => array('saved' => false));
            }

            $name = mb_substr(trim((string) (isset($request['name']) ? $request['name'] : '')), 0, 100);
            $email_address = trim((string) (isset($request['email']) ? $request['email'] : ''));

            if ($email_address != '' && !filter_var($email_address, FILTER_VALIDATE_EMAIL)) {
                $email_address = '';
            }

            $_SESSION['chat']['site_name'] = $name;
            $_SESSION['chat']['site_email'] = mb_substr($email_address, 0, 255);

            $conversation = pg_chat_site_conversation();

            if ($conversation) {
                db("UPDATE chat_conversations SET
                        party_name = '" . e($_SESSION['chat']['site_name']) . "',
                        party_email = '" . e($_SESSION['chat']['site_email']) . "'
                    WHERE id = '" . e((int) $conversation['id']) . "'");
            }

            return array('status' => 'success', 'data' => array('saved' => true));
    }

    return array('status' => 'error', 'message' => 'Unknown action.');
}

// ── Site bubble rendering ────────────────────────────────────────────────
// Called by get_page_content.php just before </body>. Frontend pages have
// no Bootstrap/jQuery guarantee: the CSS is self-contained under the
// pg-chat-site (pgcs-*) prefix, the JS lives in its own file
// (chat_site.*.js) and runs independently over XMLHttpRequest. Theme,
// color and icon come from the site settings; a visitor who never opens
// the chat causes no extra request.
function pg_chat_render_site_widget()
{
    if (!pg_chat_site_enabled()) {
        return '';
    }

    // The bubble is not rendered while the operator browses the site —
    // chatting with themselves is pointless; they already use the panel
    // launcher.
    if (defined('USER_LOGGED_IN') && USER_LOGGED_IN && (int) USER_ID === (int) CHAT_OPERATOR_USER_ID) {
        return '';
    }

    $identity = pg_chat_site_identity();

    // A single indexed read so visitors with a conversation get the unread
    // badge on the bubble — anonymous visitors who never used the chat
    // cause no query at all (nothing is looked up without a session id).
    $has_conversation = false;
    $unread = 0;
    $conversation_id = 0;

    if ((defined('USER_LOGGED_IN') && USER_LOGGED_IN) || !empty($_SESSION['chat']['site_conversation_id'])) {
        $conversation = pg_chat_site_conversation();

        if ($conversation) {
            $has_conversation = true;
            $conversation_id = (int) $conversation['id'];

            // Badge count: only counted when the cursor is behind. Handing
            // the id to the client does not loosen ownership — every
            // request re-validates against the session
            // (pg_chat_site_own_conversation).
            if ((int) $conversation['last_message_id'] > (int) $conversation['initiator_last_read_id']) {
                $unread = (int) db_value("
                    SELECT COUNT(*) FROM chat_messages
                    WHERE conversation_id = '" . e($conversation_id) . "'
                        AND id > '" . e((int) $conversation['initiator_last_read_id']) . "'");
            }
        }
    }

    $theme = defined('CHAT_WIDGET_THEME') ? CHAT_WIDGET_THEME : 'auto';

    if (!in_array($theme, array('auto', 'light', 'dark'), true)) {
        $theme = 'auto';
    }

    $color = defined('CHAT_WIDGET_COLOR') ? CHAT_WIDGET_COLOR : '#0d6efd';

    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        $color = '#0d6efd';
    }

    $icon = defined('CHAT_WIDGET_ICON') ? CHAT_WIDGET_ICON : 'chat';

    if (!in_array($icon, array('chat', 'support', 'help'), true)) {
        $icon = 'chat';
    }

    // Bubble label from the settings (2026.4.6), e.g. "Technical Support",
    // "Sales". Empty falls back to the language file's "Live Support".
    $widget_label = (defined('CHAT_WIDGET_TITLE') && trim((string) CHAT_WIDGET_TITLE) != '')
        ? trim((string) CHAT_WIDGET_TITLE)
        : lang('Live Support');

    $config = array(
        'api_url' => PATH . SOFTWARE_DIRECTORY . '/api.php',
        'token' => isset($_SESSION['software']['token']) ? $_SESSION['software']['token'] : '',
        'member' => $identity['member'],
        'name' => $identity['name'],
        'email' => $identity['email'],
        'welcome' => defined('CHAT_WELCOME_MESSAGE') ? CHAT_WELCOME_MESSAGE : '',
        'has_conversation' => $has_conversation,
        'conversation_id' => $conversation_id,
        'unread' => $unread,
        'theme' => $theme,
        'attach' => array(
            'images' => (defined('CHAT_ALLOW_IMAGES') && CHAT_ALLOW_IMAGES && pg_chat_attachments_ready()) ? true : false,
            'files' => (defined('CHAT_ALLOW_FILES') && CHAT_ALLOW_FILES && pg_chat_attachments_ready()) ? true : false,
            'max' => 5242880
        ),
        'strings' => array(
            'title' => (defined('TITLE') && TITLE != '') ? TITLE : $widget_label,
            'subtitle' => $widget_label,
            'online' => lang('Online'),
            'offline' => lang('Offline'),
            'offline_note' => lang('We are offline right now. Leave a message and we will get back to you.'),
            'placeholder' => lang('Write your message'),
            'send' => lang('Send'),
            'name_placeholder' => lang('Your name (optional)'),
            'email_placeholder' => lang('Your email (optional)'),
            'captcha_hint' => lang('Slide the piece into place to continue'),
            'captcha_fail' => lang('Verification failed. Please try again.'),
            'captcha_locked' => lang('Too many attempts. Please wait a moment.'),
            'typing' => lang('Typing'),
            'closed' => lang('This conversation is closed.'),
            'unavailable' => lang('Chat is not available right now.'),
            'attach' => lang('Attach File'),
            'file_too_big' => lang('File is too large.'),
            'file_type' => lang('This file type is not allowed.'),
            'file_identity' => lang('Please enter your name and email before sending a file.'),
            'write_first' => lang('Write a message first.'),
            // "Delivered" is the shipping-delivery language key; the chat
            // tick uses its own keys.
            'delivered' => lang('Message delivered'),
            'seen' => lang('Message seen'),
            'new_chat' => lang('Start New Chat')
        )
    );

    $js_file = 'chat_site.' . ENVIRONMENT_SUFFIX . '.js';
    $js_url = PATH . SOFTWARE_DIRECTORY . '/' . $js_file . '?v=' . @filemtime(dirname(__FILE__) . '/' . $js_file);

    // Bubble icon: inline SVG — no external request, works in every theme.
    $icons = array(
        'chat' => '<svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor" aria-hidden="true"><path d="M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H8l-5 4V6a2 2 0 0 1 2-2z"/></svg>',
        'support' => '<svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor" aria-hidden="true"><path d="M12 2a8 8 0 0 0-8 8v5a3 3 0 0 0 3 3h1v-7H6v-1a6 6 0 1 1 12 0v1h-2v7h2a4.4 4.4 0 0 1-3.6 2.1 1.5 1.5 0 1 0 .1 1.9A6.4 6.4 0 0 0 20 16.6V10a8 8 0 0 0-8-8z"/></svg>',
        'help' => '<svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm1 17h-2v-2h2v2zm1.8-7.3-.9.9c-.6.6-.9 1-.9 2.4h-2v-.5c0-1.1.3-1.9.9-2.5l1.2-1.2a1.9 1.9 0 0 0-1.3-3.3 2 2 0 0 0-2 2h-2a4 4 0 1 1 7 2.2z"/></svg>'
    );

    $output = '
<div id="pg-chat-site" data-theme="' . h($theme) . '" style="--pgcs-accent: ' . h($color) . ';"></div>
<style>
#pg-chat-site { position: fixed; z-index: 2147483000; right: 20px; bottom: 20px; font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif; font-size: 14px; line-height: 1.4;
  --pgcs-bg: #ffffff; --pgcs-text: #1f2329; --pgcs-muted: #6b7280; --pgcs-border: #e5e7eb; --pgcs-peer-bubble: #f0f2f5; }
#pg-chat-site[data-theme="dark"] { --pgcs-bg: #1f2329; --pgcs-text: #e5e7eb; --pgcs-muted: #9ca3af; --pgcs-border: #374151; --pgcs-peer-bubble: #2b3138; }
#pg-chat-site * { box-sizing: border-box; margin: 0; padding: 0; }
#pg-chat-site .pgcs-bubble { width: 56px; height: 56px; border: 0; border-radius: 50%; cursor: pointer; color: #fff; background: var(--pgcs-accent); box-shadow: 0 6px 20px rgba(0,0,0,.28); display: flex; align-items: center; justify-content: center; position: relative; }
#pg-chat-site .pgcs-badge { position: absolute; top: -5px; right: -5px; min-width: 18px; height: 18px; border-radius: 9px; background: #dc3545; color: #fff; font-size: 11px; font-weight: 700; line-height: 14px; padding: 0 4px; border: 2px solid #fff; display: none; align-items: center; justify-content: center; }
#pg-chat-site .pgcs-window { position: absolute; right: 0; bottom: 70px; width: 340px; max-width: calc(100vw - 32px); height: 480px; max-height: calc(100vh - 110px); background: var(--pgcs-bg); color: var(--pgcs-text); border: 1px solid var(--pgcs-border); border-radius: 14px; box-shadow: 0 12px 40px rgba(0,0,0,.25); display: none; flex-direction: column; overflow: hidden; overscroll-behavior: contain; }
#pg-chat-site.pgcs-open .pgcs-window { display: flex; }
#pg-chat-site .pgcs-header { background: var(--pgcs-accent); color: #fff; padding: 12px 44px 12px 14px; position: relative; }
#pg-chat-site .pgcs-close { position: absolute; right: 8px; top: 10px; width: 30px; height: 30px; border: 0; background: transparent; color: #fff; font-size: 17px; line-height: 1; cursor: pointer; opacity: .85; }
#pg-chat-site .pgcs-close:hover { opacity: 1; }
#pg-chat-site .pgcs-header strong { display: block; font-size: 15px; }
#pg-chat-site .pgcs-status { font-size: 12px; opacity: .9; }
#pg-chat-site .pgcs-offline-note { background: #fff3cd; color: #664d03; font-size: 12px; padding: 8px 12px; border-bottom: 1px solid var(--pgcs-border); }
#pg-chat-site[data-theme="dark"] .pgcs-offline-note { background: #4a3f12; color: #ffe69c; }
#pg-chat-site .pgcs-messages { flex: 1 1 auto; overflow-y: auto; padding: 10px 12px; overscroll-behavior: contain; }
#pg-chat-site .pgcs-row { display: flex; margin-bottom: 6px; }
#pg-chat-site .pgcs-msg { max-width: 82%; padding: 7px 11px; border-radius: 14px; white-space: pre-wrap; overflow-wrap: break-word; font-size: 13px; }
#pg-chat-site .pgcs-mine { margin-left: auto; background: var(--pgcs-accent); color: #fff; border-bottom-right-radius: 4px; }
#pg-chat-site .pgcs-tick { display: inline-block; margin-left: 7px; font-size: 11px; line-height: 1; color: rgba(255,255,255,.65); letter-spacing: -2px; white-space: nowrap; vertical-align: baseline; }
#pg-chat-site .pgcs-tick-seen { color: #9ff3ff; }
#pg-chat-site .pgcs-newchat { display: block; margin: 2px auto 6px; border: 1px solid var(--pgcs-border); background: var(--pgcs-bg); color: var(--pgcs-accent); border-radius: 999px; padding: 5px 14px; font-size: 12px; font-weight: 600; cursor: pointer; }
#pg-chat-site .pgcs-newchat:hover { border-color: var(--pgcs-accent); }
#pg-chat-site .pgcs-peer { margin-right: auto; background: var(--pgcs-peer-bubble); color: var(--pgcs-text); border-bottom-left-radius: 4px; }
#pg-chat-site .pgcs-system { margin: 0 auto; background: transparent; color: var(--pgcs-muted); font-size: 12px; text-align: center; }
#pg-chat-site .pgcs-typing { font-size: 12px; color: var(--pgcs-muted); padding: 0 14px 4px; display: none; }
#pg-chat-site .pgcs-identity { display: flex; flex-direction: column; gap: 6px; padding: 8px 12px; border-top: 1px solid var(--pgcs-border); }
#pg-chat-site .pgcs-identity input { width: 100%; border: 1px solid var(--pgcs-border); background: var(--pgcs-bg); color: var(--pgcs-text); border-radius: 8px; padding: 6px 9px; font-size: 12px; }
#pg-chat-site .pgcs-compose { display: flex; gap: 8px; padding: 10px 12px; border-top: 1px solid var(--pgcs-border); }
#pg-chat-site .pgcs-attach { flex-shrink: 0; border: 1px solid var(--pgcs-border); background: var(--pgcs-bg); color: var(--pgcs-muted); border-radius: 8px; width: 38px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
#pg-chat-site .pgcs-attach span { font-size: 20px; line-height: 1; font-weight: 600; }
/* The raw file input stays hidden in EVERY theme: some themes set display
   on inputs with !important, so an inline style is not enough — id +
   !important + off-screen. */
#pg-chat-site .pgcs-compose input[type="file"] { display: none !important; position: absolute !important; left: -9999px !important; width: 1px !important; height: 1px !important; opacity: 0 !important; }
#pg-chat-site .pgcs-msg img { max-width: 100%; border-radius: 10px; display: block; cursor: pointer; }
#pg-chat-site .pgcs-msg a.pgcs-file { display: inline-flex; align-items: center; gap: 6px; color: inherit; text-decoration: underline; word-break: break-all; }
#pg-chat-site .pgcs-identity input.pgcs-required { border-color: #dc3545; }
#pg-chat-site .pgcs-compose textarea { flex: 1 1 auto; resize: none; height: 38px; max-height: 90px; border: 1px solid var(--pgcs-border); background: var(--pgcs-bg); color: var(--pgcs-text); border-radius: 8px; padding: 8px 10px; font-size: 13px; font-family: inherit; }
#pg-chat-site .pgcs-send { flex-shrink: 0; border: 0; border-radius: 8px; background: var(--pgcs-accent); color: #fff; width: 40px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
#pg-chat-site .pgcs-captcha { padding: 12px 14px; border-top: 1px solid var(--pgcs-border); display: none; }
#pg-chat-site .pgcs-captcha-hint { font-size: 12px; color: var(--pgcs-muted); margin-bottom: 8px; }
#pg-chat-site .pgcs-cimg { position: relative; height: 96px; border-radius: 10px; overflow: hidden; background: var(--pgcs-peer-bubble); touch-action: none; user-select: none; }
#pg-chat-site .pgcs-hole { position: absolute; top: 24px; width: 48px; height: 48px; background: rgba(0,0,0,.55); }
#pg-chat-site .pgcs-piece { position: absolute; top: 24px; left: 0; width: 48px; height: 48px; cursor: grab; touch-action: none; filter: drop-shadow(0 2px 5px rgba(0,0,0,.45)); }
#pg-chat-site .pgcs-cimg.pgcs-solved .pgcs-hole { display: none; }
#pg-chat-site .pgcs-piece.pgcs-snap { transition: left .18s ease-out; }
#pg-chat-site .pgcs-error { color: #dc3545; font-size: 12px; margin-top: 6px; display: none; }
@media (max-width: 480px) {
  /* Full-screen window on phones: the header stays FIXED at the top, only
     the message area scrolls. 100dvh shrinks to the visible area when the
     keyboard opens — the window is not pushed off screen (older browsers
     without dvh support fall back to 100%). */
  #pg-chat-site .pgcs-window { position: fixed; top: 0; left: 0; right: 0; bottom: 0; width: 100%; max-width: 100%; height: 100%; height: 100dvh; max-height: none; border-radius: 0; }
  #pg-chat-site.pgcs-open .pgcs-bubble { display: none; }
}
</style>
<script type="application/json" id="pg-chat-site-config">' . encode_json($config) . '</script>
<script type="text/template" id="pg-chat-site-icon">' . $icons[$icon] . '</script>
<script src="' . h($js_url) . '" defer></script>';

    return $output;
}
