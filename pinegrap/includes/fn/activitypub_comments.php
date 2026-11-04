<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Fediverse replies as comments. A Create(Note) that answers one of the
 * site's published objects (a page or a record) or one of the site's own
 * replies becomes a comments row on that thread: comments.remote_actor and
 * remote_id name its sender and its note, the sender's name and address are
 * the comment's name, and the page's comment settings decide whether it is
 * published at once and who is told. A Delete of the note removes it. A
 * reply written on the site to such a comment goes out as a Note to its
 * sender through the outbox queue.
 *
 * The reading of addresses and notes is pure, so the tests check it without
 * a database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/activitypub_moderation.php');

// The longest comment text kept from a note; Mastodon's own limit is 500
// characters, other servers allow more.
if (!defined('PG_AP_REPLY_MAX_TEXT')) {
    define('PG_AP_REPLY_MAX_TEXT', 10000);
}

/**
 * One row of a prepared statement, or null (none, or a failed query: the
 * inbox answers without the error page db_item() would print).
 *
 * @param string $sql
 * @param array  $params
 * @return array|null
 */
function pg_ap_comments_row($sql, $params = array())
{
    $result = db_q($sql, $params);
    $row = $result ? mysqli_fetch_assoc($result) : null;

    return $row ? $row : null;
}

/**
 * What a note's inReplyTo names on this site.
 *
 * @param string $reply_to the address the note answers
 * @param array  $site     pg_ap_site()
 * @return array|null kind 'p' (page id), 'r' (record id), 'c' (comment id),
 *                    or 'url' (page name, an Article's url); null for none
 */
function pg_ap_reply_ref($reply_to, $site)
{
    $reply_to = (string) $reply_to;

    if (!pg_ap_is_ours($reply_to, $site)) {
        return null;
    }

    $urls = pg_ap_urls($site);
    $prefix = $urls['id'] . '/';

    if (strpos($reply_to, $prefix) === 0) {
        if (preg_match('#^([prc])/([1-9][0-9]{0,9})$#', substr($reply_to, strlen($prefix)), $match)) {
            return array('kind' => $match[1], 'id' => (int) $match[2]);
        }

        return null;
    }

    // An Article's url is the page's address: its name, nothing after it.
    $path = rawurldecode((string) substr($reply_to, strlen((string) $site['base'])));

    if (($path === '') || ($path[0] === '@') || preg_match('#[/?\#\s]#', $path)) {
        return null;
    }

    return array('kind' => 'url', 'id' => $path);
}

/**
 * A note's HTML as comment text: paragraphs and line breaks kept as new
 * lines, a leading mention of the site's actor dropped, tags removed,
 * entities decoded, cut to PG_AP_REPLY_MAX_TEXT characters.
 *
 * @param string $html
 * @param array  $site pg_ap_site()
 * @return string
 */
function pg_ap_note_text($html, $site)
{
    $html = (string) $html;

    // The mention of the site that opens a Mastodon reply.
    $urls = pg_ap_urls($site);
    $html = preg_replace('#^\s*(<p[^>]*>)?\s*(<span[^>]*>\s*)?<a[^>]*href="' . preg_quote(htmlspecialchars($urls['id'], ENT_QUOTES, 'UTF-8'), '#') . '"[^>]*>.*?</a>(\s*</span>)?\s*#is', '$1', $html);

    $html = preg_replace('#<br\s*/?>#i', "\n", $html);
    $html = preg_replace('#</p>\s*<p[^>]*>#i', "\n\n", $html);
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim(preg_replace("#[ \t]*\n[ \t]*#", "\n", str_replace("\r", '', $text)));

    if (function_exists('mb_substr')) {
        $text = mb_substr($text, 0, PG_AP_REPLY_MAX_TEXT, 'UTF-8');
    } else {
        $text = substr($text, 0, PG_AP_REPLY_MAX_TEXT);
    }

    return $text;
}

/**
 * A remote actor's address as a handle (@user@host): its preferred user
 * name when known, else the last part of its address.
 *
 * @param string $actor_id
 * @param string $preferred
 * @return string '' when the address has no host
 */
function pg_ap_actor_handle($actor_id, $preferred = '')
{
    $host = strtolower((string) parse_url((string) $actor_id, PHP_URL_HOST));

    if ($host === '') {
        return '';
    }

    $user = trim((string) $preferred);

    if ($user === '') {
        $path = trim((string) parse_url((string) $actor_id, PHP_URL_PATH), '/');
        $user = ltrim((string) substr($path, (int) strrpos('/' . $path, '/')), '@');
    }

    return ($user === '') ? '@' . $host : '@' . $user . '@' . $host;
}

/**
 * The name a remote comment is shown with: the sender's name and handle,
 * cut to the comments.name column (100 characters).
 *
 * @param string $name
 * @param string $handle pg_ap_actor_handle()
 * @return string
 */
function pg_ap_reply_name($name, $handle)
{
    $name = trim(preg_replace('#\s+#u', ' ', strip_tags((string) $name)));
    $out = ($name === '') ? $handle : (($handle === '') ? $name : $name . ' (' . $handle . ')');

    if (function_exists('mb_substr')) {
        return mb_substr($out, 0, 100, 'UTF-8');
    }

    return substr($out, 0, 100);
}

/**
 * The Create(Note) that carries a reply written on the site to a remote
 * comment: public, addressed to the sender, mentioning it.
 *
 * @param array $site    pg_ap_site()
 * @param array $comment id, message, created_timestamp, url (the comment's
 *                       address on the site)
 * @param array $parent  remote_actor, remote_id
 * @return array
 */
function pg_ap_local_reply_activity($site, $comment, $parent)
{
    $urls = pg_ap_urls($site);
    $actor = (string) $parent['remote_actor'];
    $handle = pg_ap_actor_handle($actor);
    $note_id = $urls['id'] . '/c/' . (int) $comment['id'];
    $time = pg_ap_time((int) $comment['created_timestamp']);
    $to = array(PG_AP_PUBLIC);
    $cc = array($urls['followers'], $actor);
    $mention = '<span class="h-card"><a href="' . htmlspecialchars($actor, ENT_QUOTES, 'UTF-8') . '" class="u-url mention">'
        . htmlspecialchars($handle, ENT_QUOTES, 'UTF-8') . '</a></span> ';
    $paragraphs = array();

    foreach (preg_split("#\n\s*\n#", trim(str_replace("\r", '', (string) $comment['message']))) as $paragraph) {
        $paragraphs[] = nl2br(htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8'), false);
    }

    $note = array(
        'id'           => $note_id,
        'type'         => 'Note',
        'attributedTo' => $urls['id'],
        'inReplyTo'    => (string) $parent['remote_id'],
        'content'      => '<p>' . $mention . implode('</p><p>', $paragraphs) . '</p>',
        'mediaType'    => 'text/html',
        'url'          => (string) $comment['url'],
        'published'    => $time,
        'to'           => $to,
        'cc'           => $cc,
        'tag'          => array(array('type' => 'Mention', 'href' => $actor, 'name' => $handle)),
    );

    return array(
        '@context'  => 'https://www.w3.org/ns/activitystreams',
        'id'        => $note_id . '#create',
        'type'      => 'Create',
        'actor'     => $urls['id'],
        'published' => $time,
        'to'        => $to,
        'cc'        => $cc,
        'object'    => $note,
    );
}

/**
 * The thread a reply goes to, found from what it answers: a published page
 * or record, or a published comment written on the site.
 *
 * @param array $ref  pg_ap_reply_ref()
 * @param array $site pg_ap_site()
 * @return array|null page_id, item_type, item_id, parent_id
 */
function pg_ap_reply_thread($ref, $site)
{
    if ($ref['kind'] === 'c') {
        $row = pg_ap_comments_row("SELECT id, page_id, item_type, item_id FROM comments WHERE id = ? AND published = '1' AND remote_actor = ''", array((string) (int) $ref['id']));

        if (!$row) {
            return null;
        }

        return array('page_id' => (int) $row['page_id'], 'item_type' => (string) $row['item_type'], 'item_id' => (int) $row['item_id'],
            'parent_id' => (int) $row['id']);
    }

    $published = pg_ap_published($site, pg_ap_settings());

    if ($ref['kind'] === 'url') {
        foreach ($published as $entry) {
            if (($entry['kind'] === 'p') && ((string) $entry['row']['page_name'] === $ref['id'])) {
                return array('page_id' => (int) $entry['row']['page_id'], 'item_type' => '', 'item_id' => 0, 'parent_id' => 0);
            }
        }

        return null;
    }

    $object_id = pg_ap_object_id($site, $ref['kind'], $ref['id']);

    if (!isset($published[$object_id])) {
        return null;
    }

    $entry = $published[$object_id];

    if ($entry['kind'] === 'p') {
        return array('page_id' => (int) $ref['id'], 'item_type' => '', 'item_id' => 0, 'parent_id' => 0);
    }

    return array('page_id' => (int) $entry['record_page']['page_id'], 'item_type' => 'submitted_form', 'item_id' => (int) $ref['id'], 'parent_id' => 0);
}

/**
 * Stores a Fediverse reply as a comment (called by pg_ap_inbox_apply()).
 * A note already stored, a thread that is not published or not open for
 * new comments, or a note without text is not stored.
 *
 * @param array  $note   the Note, its sender already checked
 * @param string $actor  the sender's address
 * @param array  $site   pg_ap_site()
 * @return string what was done, for the inbox log
 */
function pg_ap_reply_receive($note, $actor, $site)
{
    $note_id = pg_ap_id($note);
    $actor = (string) $actor;

    if (($note_id === '') || (strlen($note_id) > 255) || (strlen($actor) > 255)) {
        return 'reply not stored: no usable note address';
    }

    if (pg_ap_comments_row("SELECT id FROM comments WHERE remote_id = ? AND remote_actor = ? LIMIT 1", array($note_id, $actor))) {
        return 'reply already stored';
    }

    $ref = pg_ap_reply_ref(pg_ap_id(isset($note['inReplyTo']) ? $note['inReplyTo'] : ''), $site);
    $thread = $ref ? pg_ap_reply_thread($ref, $site) : null;

    if (!$thread) {
        return 'reply not stored: it answers nothing published';
    }

    $page = pg_ap_comments_row("SELECT * FROM page WHERE page_id = ?", array((string) (int) $thread['page_id']));
    $settings = pg_comments_settings($page);

    if (!$page || !$settings['enabled']
        || !pg_comments_allow_new($thread['page_id'], $thread['item_type'], $thread['item_id'], $settings['allow_new'])) {
        return 'reply not stored: comments are closed there';
    }

    $text = pg_ap_note_text(isset($note['content']) && is_string($note['content']) ? $note['content'] : '', $site);

    if ($text === '') {
        return 'reply not stored: no text';
    }

    $sender = pg_ap_comments_row("SELECT name FROM ap_followers WHERE actor_id = ?", array($actor));

    if (!$sender) {
        $sender = pg_ap_remote_actor($actor);
    }

    $name = pg_ap_reply_name($sender ? $sender['name'] : '', pg_ap_actor_handle($actor));
    $published = $settings['auto_publish'] ? '1' : '0';

    $columns = '';
    $values = '';

    if (pg_comment_replies_ready()) {
        $columns = ', parent_id';
        $values = ", '" . (int) $thread['parent_id'] . "'";
    }

    $inserted = db_q(
        "INSERT INTO comments (page_id, item_id, item_type, name, message, file_id, published, rating, publish_date_and_time, publish_cancel,
            created_user_id, created_timestamp, last_modified_user_id, last_modified_timestamp, remote_actor, remote_id$columns)
         VALUES (?, ?, ?, ?, ?, '0', ?, '', '', '', '0', UNIX_TIMESTAMP(), '0', UNIX_TIMESTAMP(), ?, ?$values)",
        array((string) $thread['page_id'], (string) $thread['item_id'], (string) $thread['item_type'], $name, $text, $published, $actor, $note_id)
    );

    $comment_id = $inserted ? (int) mysqli_insert_id(db::$con) : 0;

    if ($comment_id <= 0) {
        return 'reply not stored: the comment could not be written';
    }

    pg_ap_reply_stored($comment_id, $thread, $page, $settings, $name, $text, $published);

    return ($published === '1') ? 'reply stored and published' : 'reply stored for moderation';
}

/**
 * What a new remote comment sets off, as a comment written on the site
 * does: scheduled comments set to be cancelled, the panel notification, the
 * record's comment counters, the moderators' mail, the watchers' and the
 * record sender's mails and the page cache.
 *
 * @param int    $comment_id
 * @param array  $thread    pg_ap_reply_thread()
 * @param array  $page      the page row
 * @param array  $settings  pg_comments_settings()
 * @param string $name
 * @param string $text
 * @param string $published '1' or '0'
 */
function pg_ap_reply_stored($comment_id, $thread, $page, $settings, $name, $text, $published)
{
    $page_id = (int) $thread['page_id'];
    $item_type = (string) $thread['item_type'];
    $item_id = (int) $thread['item_id'];
    $page_url = URL_SCHEME . HOSTNAME_SETTING . PATH . get_page_name($page_id)
        . pg_comment_view_query(get_query_string_for_page_url($page['page_type'], $item_id, $item_type), $comment_id) . '#c-' . $comment_id;

    if ($published === '1') {
        db_q("UPDATE comments SET publish_date_and_time = '0000-00-00 00:00:00', publish_cancel = '0'
              WHERE publish_cancel = '1' AND page_id = ? AND item_id = ? AND item_type = ?", array((string) $page_id, (string) $item_id, $item_type));
        pg_comment_refresh_record_info($page_id, $item_type, $item_id);
    }

    create_notification(array('action' => 'new_comment', 'type' => 'success', 'title' => $text, 'send_to' => urlencode($page_url),
        'comment_id' => $comment_id, 'user' => $name));

    if ($settings['admin_email'] !== '') {
        list($subject, $body) = pg_staff_lang(function () use ($settings, $published, $name, $text, $page_url, $comment_id) {
            $label = ($settings['label'] !== '') ? $settings['label'] : lang('Comment');
            $lower = function_exists('mb_strtolower') ? mb_strtolower($label, 'UTF-8') : strtolower($label);
            $intro = ($published === '1')
                ? lang(array('string' => 'The following {var:1} has been added and published', 'vars' => $lower))
                : lang(array('string' => 'The following {var:1} has been added, but it has not been published yet.', 'vars' => $lower));
            $subject = ($published === '1')
                ? lang(array('string' => 'A {var:1} has been added and published.', 'vars' => $lower))
                : lang(array('string' => 'A {var:1} has been added, but it has not been published yet.', 'vars' => $lower));

            $body = rtrim($intro, ':.') . ':' . "\n\n"
                . lang('Display Name') . ': ' . $name . ' (' . lang('From the Fediverse') . ')' . "\n\n"
                . $label . ': ' . $text . "\n\n"
                . URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/edit_comment.php?id=' . $comment_id . "\n\n"
                . lang(array('string' => 'The {var:1} appears at the link below.', 'vars' => $lower)) . "\n\n"
                . $page_url;

            return array($subject, $body);
        });

        email(array(
            'to'                 => $settings['admin_email'],
            'from_name'          => ORGANIZATION_NAME,
            'from_email_address' => EMAIL_ADDRESS,
            'subject'            => $subject,
            'body'               => $body,
            'queue'              => true,
        ));
    }

    if ($published === '1') {
        if (($item_type === 'submitted_form') && ($settings['submitter_email_page_id'] > 0)) {
            send_comment_email_to_custom_form_submitter($comment_id);
        }

        if ($settings['watcher_email_page_id'] > 0) {
            send_comment_email_to_watchers($comment_id);
        }
    }

    if (function_exists('pg_page_cache_mark_dirty')) {
        pg_page_cache_mark_dirty();
    }
}

/**
 * Removes the comment a deleted note became (called by pg_ap_inbox_apply()
 * for a Delete from the note's own server).
 *
 * @param string $note_id
 * @param string $actor
 * @return int how many comments were removed
 */
function pg_ap_reply_deleted($note_id, $actor)
{
    if (((string) $note_id === '') || ((string) $actor === '')) {
        return 0;
    }

    return pg_ap_remove_comments("remote_id = ? AND remote_actor = ?", array((string) $note_id, (string) $actor));
}

/**
 * Sends a reply written on the site to the Fediverse comment it answers:
 * a Create(Note) queued for the sender's inbox. Nothing happens while the
 * feature is off, for an unpublished reply or a parent written on the site.
 *
 * @param int $comment_id
 * @return bool true when a delivery was queued
 */
function pg_ap_reply_send($comment_id)
{
    if (!pg_ap_enabled() || !pg_comment_replies_ready()) {
        return false;
    }

    $comment = pg_ap_comments_row("SELECT id, page_id, item_type, item_id, message, published, parent_id, created_timestamp, remote_actor FROM comments WHERE id = ?", array((string) (int) $comment_id));

    if (!$comment || ($comment['published'] != '1') || ((int) $comment['parent_id'] <= 0) || ($comment['remote_actor'] !== '')) {
        return false;
    }

    $parent = pg_ap_comments_row("SELECT remote_actor, remote_id FROM comments WHERE id = ?", array((string) (int) $comment['parent_id']));

    if (!$parent || ($parent['remote_actor'] === '') || ($parent['remote_id'] === '')) {
        return false;
    }

    $follower = pg_ap_comments_row("SELECT inbox FROM ap_followers WHERE actor_id = ?", array($parent['remote_actor']));
    $inbox = $follower ? (string) $follower['inbox'] : '';

    if ($inbox === '') {
        $remote = pg_ap_remote_actor($parent['remote_actor']);
        $inbox = $remote ? (string) $remote['inbox'] : '';
    }

    if ($inbox === '') {
        return false;
    }

    $site = pg_ap_site();
    $page = pg_ap_comments_row("SELECT page_type FROM page WHERE page_id = ?", array((string) (int) $comment['page_id']));
    $page_type = $page ? (string) $page['page_type'] : '';
    $comment['url'] = $site['base'] . get_page_name($comment['page_id'])
        . pg_comment_view_query(get_query_string_for_page_url($page_type, $comment['item_id'], $comment['item_type']), $comment['id']) . '#c-' . (int) $comment['id'];

    return pg_ap_queue(pg_ap_local_reply_activity($site, $comment, $parent), $inbox);
}

/**
 * The object addresses whose likes and boosts a comment thread shows: a
 * record's thread is its record (r), a page's own thread the page (p). The
 * thread of a product, a product group or an event is never published, so
 * it has none. Pure.
 *
 * @param array  $site      pg_ap_site()
 * @param int    $page_id   the drawn page
 * @param string $item_type the thread's item type ('' for the page's own)
 * @param int    $item_id
 * @return array of object addresses
 */
function pg_ap_thread_objects($site, $page_id, $item_type, $item_id)
{
    if ($item_type === 'submitted_form') {
        return ((int) $item_id > 0) ? array(pg_ap_object_id($site, 'r', (int) $item_id)) : array();
    }

    if (($item_type === '') || ($item_type === null)) {
        return ((int) $page_id > 0) ? array(pg_ap_object_id($site, 'p', (int) $page_id)) : array();
    }

    return array();
}

/**
 * The grouped ap_reactions rows as counts per kind. Pure.
 *
 * @param mixed $rows rows of kind and n
 * @return array like, announce (int)
 */
function pg_ap_reaction_counts_read($rows)
{
    $counts = array('like' => 0, 'announce' => 0);

    foreach (is_array($rows) ? $rows : array() as $row) {
        if (is_array($row) && isset($row['kind'], $row['n']) && isset($counts[$row['kind']]) && is_numeric($row['n'])) {
            $counts[$row['kind']] += max(0, (int) $row['n']);
        }
    }

    return $counts;
}

/**
 * How many likes and boosts the objects have. A failed query (before
 * 11.0.167 made ap_reactions) reads as none.
 *
 * @param array $object_ids
 * @return array like, announce (int)
 */
function pg_ap_reaction_counts($object_ids)
{
    $object_ids = array_values(array_unique(array_filter(array_map('strval', (array) $object_ids), 'strlen')));

    if (!$object_ids) {
        return pg_ap_reaction_counts_read(array());
    }

    $result = db_q(
        "SELECT kind, COUNT(*) AS n FROM ap_reactions WHERE object_id IN (" . implode(', ', array_fill(0, count($object_ids), '?')) . ") GROUP BY kind",
        $object_ids
    );
    $rows = array();

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $rows[] = $row;
    }

    return pg_ap_reaction_counts_read($rows);
}
