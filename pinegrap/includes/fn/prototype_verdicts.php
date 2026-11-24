<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The store of a prototype share link asking approval (2026.11.0, 11.0.276):
 * the link's five approval columns (approval, publish_rule, approver_name,
 * approver_email, answer_by) and the prototype_verdicts table, one row per
 * "Approve this page" or "Request changes" a guest sent. The rules a verdict
 * is checked and read by are pure and live in prototype_approval.php.
 *
 * A verdict is deleted with its design (pg_proto_comments_delete_design())
 * and with its link's comments (the Share prototype dialog's Delete
 * comments, pg_proto_comments_delete_link()): a later design or link may be
 * given the same id.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * Does the database carry verdicts and the links' approval columns
 * (2026.11.0, 11.0.276)?
 *
 * @return bool
 */
function pg_proto_approval_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_schema_has('prototype_verdicts', 'fingerprint') && pg_schema_has('prototype_links', 'answer_by');
    }

    return $ready;
}

/**
 * A link row's approval settings as the code reads them: a link written
 * before the step, or a column holding nothing, asks nothing. Pure.
 *
 * @param array $link prototype_links row
 * @return array approval (bool), publish_rule (bool), approver_name,
 *               approver_email, answer_by (YYYY-MM-DD or '')
 */
function pg_proto_link_approval($link)
{
    $link = is_array($link) ? $link : array();
    $answer_by = isset($link['answer_by']) ? (string) $link['answer_by'] : '';

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $answer_by) || ($answer_by === '0000-00-00')) {
        $answer_by = '';
    }

    $approval = !empty($link['approval']);

    return array(
        'approval'       => $approval,
        'publish_rule'   => $approval && !empty($link['publish_rule']),
        'approver_name'  => isset($link['approver_name']) ? (string) $link['approver_name'] : '',
        'approver_email' => isset($link['approver_email']) ? (string) $link['approver_email'] : '',
        'answer_by'      => $answer_by,
    );
}

/**
 * Whether a link asks for approval: its column is on and the store is there.
 *
 * @param array $link prototype_links row
 * @return bool
 */
function pg_proto_link_asks_approval($link)
{
    $approval = pg_proto_link_approval($link);

    return $approval['approval'] && pg_proto_approval_ready();
}

/**
 * A node fingerprint map as the column keeps it (JSON, node id => sha1), and
 * back. A map that is not one reads as empty. Pure.
 *
 * @param array $nodes pg_proto_node_fingerprints()
 * @return string
 */
function pg_proto_verdict_nodes_encode($nodes)
{
    $clean = array();

    foreach (is_array($nodes) ? $nodes : array() as $id => $hash) {
        if (is_string($id) && pg_proto_comment_node_shape($id) && is_string($hash) && preg_match('/^[0-9a-f]{40}$/D', $hash)) {
            $clean[$id] = $hash;
        }
    }

    return $clean ? (string) json_encode($clean) : '{}';
}

/**
 * The node fingerprints a verdict row keeps. Pure.
 *
 * @param array $row prototype_verdicts row
 * @return array node id => sha1
 */
function pg_proto_verdict_nodes($row)
{
    $json = (is_array($row) && isset($row['node_fingerprints'])) ? (string) $row['node_fingerprints'] : '';
    $nodes = ($json !== '') ? json_decode($json, true) : null;
    $clean = array();

    foreach (is_array($nodes) ? $nodes : array() as $id => $hash) {
        if (is_string($id) && pg_proto_comment_node_shape($id) && is_string($hash) && preg_match('/^[0-9a-f]{40}$/D', $hash)) {
            $clean[$id] = $hash;
        }
    }

    return $clean;
}

/**
 * The row a checked verdict is written as; null when a part is missing.
 * Pure.
 *
 * @param array  $checked     pg_proto_verdict_check()'s answer without error
 * @param array  $link        prototype_links row (id, style_id)
 * @param int    $page_id     the draft page reviewed
 * @param string $fingerprint pg_proto_draft_fingerprint() of the draft now
 * @param array  $nodes       pg_proto_node_fingerprints() of the draft now
 * @return array|null
 */
function pg_proto_verdict_row($checked, $link, $page_id, $fingerprint, $nodes)
{
    if (!is_array($checked) || !empty($checked['error']) || !is_array($link)) {
        return null;
    }

    $verdict = isset($checked['verdict']) ? (string) $checked['verdict'] : '';
    $link_id = isset($link['id']) ? (int) $link['id'] : 0;
    $style_id = isset($link['style_id']) ? (int) $link['style_id'] : 0;

    if (!in_array($verdict, pg_proto_verdict_kinds(), true) || ($link_id <= 0) || ($style_id <= 0) || ((int) $page_id <= 0)
        || !is_string($fingerprint) || !preg_match('/^[0-9a-f]{40}$/D', $fingerprint)) {
        return null;
    }

    return array(
        'link_id'           => $link_id,
        'style_id'          => $style_id,
        'page_id'           => (int) $page_id,
        'verdict'           => $verdict,
        'author_name'       => isset($checked['name']) ? (string) $checked['name'] : '',
        'note'              => isset($checked['note']) ? (string) $checked['note'] : '',
        'fingerprint'       => $fingerprint,
        'node_fingerprints' => pg_proto_verdict_nodes_encode($nodes),
    );
}

/**
 * Writes a verdict (pg_proto_verdict_row()) and gives its id; 0 when it could
 * not be written.
 *
 * @param array $row
 * @param int   $now
 * @return int
 */
function pg_proto_verdict_save($row, $now = 0)
{
    if (!is_array($row) || !pg_proto_approval_ready()) {
        return 0;
    }

    $now = ((int) $now > 0) ? (int) $now : time();

    $ok = db_q(
        "INSERT INTO prototype_verdicts (link_id, style_id, page_id, verdict, author_name, note, fingerprint, node_fingerprints, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        array(
            (int) $row['link_id'], (int) $row['style_id'], (int) $row['page_id'], (string) $row['verdict'],
            (string) $row['author_name'], (string) $row['note'], (string) $row['fingerprint'],
            (string) $row['node_fingerprints'], $now,
        )
    );

    return $ok ? (int) mysqli_insert_id(db::$con) : 0;
}

/**
 * Every verdict of a link, by page and in the order given (idx_link_page);
 * pg_proto_verdicts_newest() picks the standing one of each page.
 *
 * @param int $link_id
 * @return array[]
 */
function pg_proto_verdicts_of_link($link_id)
{
    if (((int) $link_id <= 0) || !pg_proto_approval_ready()) {
        return array();
    }

    $rows = db_items("SELECT * FROM prototype_verdicts WHERE link_id = '" . (int) $link_id . "' ORDER BY page_id, id");

    return is_array($rows) ? $rows : array();
}

/**
 * Every verdict of a draft page, of every link, in the order given
 * (idx_page).
 *
 * @param int $page_id
 * @return array[]
 */
function pg_proto_verdicts_of_page($page_id)
{
    if (((int) $page_id <= 0) || !pg_proto_approval_ready()) {
        return array();
    }

    $rows = db_items("SELECT * FROM prototype_verdicts WHERE page_id = '" . (int) $page_id . "' ORDER BY id");

    return is_array($rows) ? $rows : array();
}

/**
 * Deletes every verdict of a link (with the link's comments).
 *
 * @param int $link_id
 * @return int how many rows were deleted
 */
function pg_proto_verdicts_delete_link($link_id)
{
    if (((int) $link_id <= 0) || !pg_proto_approval_ready()) {
        return 0;
    }

    db("DELETE FROM prototype_verdicts WHERE link_id = '" . (int) $link_id . "'");

    return max(0, (int) mysqli_affected_rows(db::$con));
}

/**
 * The statement that deletes the verdicts of designs; '' for no design.
 *
 * @param int|int[] $style_ids
 * @return string
 */
function pg_proto_verdicts_delete_design_sql($style_ids)
{
    $ids = array();

    foreach ((array) $style_ids as $id) {
        if ((int) $id > 0) {
            $ids[(int) $id] = (int) $id;
        }
    }

    return $ids ? "DELETE FROM prototype_verdicts WHERE style_id IN (" . implode(',', $ids) . ")" : '';
}

/**
 * Deletes the verdicts of designs whose style rows are being deleted (with
 * their comments).
 *
 * @param int|int[] $style_ids
 * @return int how many rows were deleted
 */
function pg_proto_verdicts_delete_design($style_ids)
{
    $sql = pg_proto_verdicts_delete_design_sql($style_ids);

    if (($sql === '') || !pg_proto_approval_ready()) {
        return 0;
    }

    db($sql);

    return max(0, (int) mysqli_affected_rows(db::$con));
}

/* ---------------------------------------------------------------------------
   The viewer's "Review this page" and its door
   --------------------------------------------------------------------------- */

/**
 * One verdict as the viewer and the designer get it: the verdict, whether it
 * was given to an older version of the draft, when, and the name typed.
 * Pure.
 *
 * @param array  $row         prototype_verdicts row
 * @param string $fingerprint the draft's fingerprint now
 * @return array
 */
function pg_proto_verdict_public($row, $fingerprint)
{
    $state = pg_proto_verdict_state($row, $fingerprint);

    return array(
        'id' => (int) $row['id'],
        'verdict' => $state['state'],
        'older' => $state['older'],
        'name' => (string) $row['author_name'],
        'note' => (string) $row['note'],
        'created_at' => (int) $row['created_at'],
    );
}

/**
 * Whether a post to the comment door is a verdict rather than a comment.
 * Pure.
 *
 * @param array $post
 * @return bool
 */
function pg_proto_verdict_is_post($post)
{
    return is_array($post) && isset($post['verdict']);
}

/**
 * A draft page's row, for its fingerprints; null when it is gone.
 *
 * @param int $page_id
 * @return array|null
 */
function pg_proto_verdict_draft_row($page_id)
{
    if ((int) $page_id <= 0) {
        return null;
    }

    $row = db_item("SELECT * FROM page WHERE page_id = '" . (int) $page_id . "' LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * The verdict that stands on one page for one link; null for none.
 *
 * @param int $link_id
 * @param int $page_id
 * @return array|null
 */
function pg_proto_verdict_standing($link_id, $page_id)
{
    if (((int) $link_id <= 0) || ((int) $page_id <= 0) || !pg_proto_approval_ready()) {
        return null;
    }

    $rows = db_items("SELECT * FROM prototype_verdicts WHERE link_id = '" . (int) $link_id . "' AND page_id = '" . (int) $page_id . "' ORDER BY id DESC LIMIT 20");
    $newest = pg_proto_verdicts_newest(is_array($rows) ? $rows : array());

    return isset($newest[(int) $page_id]) ? $newest[(int) $page_id] : null;
}

/**
 * The review box's settings for the viewer of a link asking approval: the
 * page's name, the verdict that stands (pg_proto_verdict_public()) or null,
 * the limits and the words. Null for a link asking nothing. Pure but for
 * lang(). `changes` is what "Show what changed" draws for a verdict given to
 * an older version (pg_proto_viewer_changes()), null otherwise.
 *
 * @param array      $link        prototype_links row
 * @param string     $page_name
 * @param array|null $standing    the standing verdict row
 * @param string     $fingerprint the draft's fingerprint now
 * @param array|null $changes     marks, removed
 * @return array|null
 */
function pg_proto_viewer_review_data($link, $page_name, $standing, $fingerprint, $changes = null)
{
    $approval = pg_proto_link_approval($link);

    if (!$approval['approval']) {
        return null;
    }

    $limits = pg_proto_comment_limits();

    return array(
        'page' => (string) $page_name,
        'verdict' => is_array($standing) ? pg_proto_verdict_public($standing, $fingerprint) : null,
        'changes' => is_array($changes) ? $changes : null,
        'nameMax' => (int) $limits['name'],
        'noteMax' => (int) $limits['text'],
        'strings' => array(
            'review' => lang('Review this page'),
            'approve' => lang('Approve this page'),
            'changes' => lang('Request changes'),
            'note' => lang('Note'),
            'noteOptional' => lang('Note (optional)'),
            'noteNeeded' => lang('Write what should change.'),
            'choose' => lang('Choose Approve this page or Request changes.'),
            'approved' => lang('You approved this page on {date}'),
            'requested' => lang('You asked for changes on {date}'),
            'changed' => lang('Changed since your last review'),
            'show' => lang('Show what changed'),
            'hide' => lang('Hide what changed'),
            'removed' => lang('Not on the page any more'),
            'changesHint' => lang('Outlined: changed since your last review.'),
        ),
    );
}

/**
 * The review box's settings for the page a link's viewer draws
 * (pg_proto_viewer_review_data()); null for a link asking nothing.
 *
 * @param array  $link
 * @param int    $page_id
 * @param string $page_name
 * @return array|null
 */
function pg_proto_viewer_review($link, $page_id, $page_name)
{
    if (!pg_proto_link_asks_approval($link) || ((int) $page_id <= 0)) {
        return null;
    }

    $standing = pg_proto_verdict_standing((int) $link['id'], (int) $page_id);
    $draft = pg_proto_verdict_draft_row($page_id);
    $fingerprint = pg_proto_draft_fingerprint_of_row($draft);
    $changes = null;

    // Round two: the nodes changed since the verdict, from the node marks
    // kept with it and the draft's tree now.
    if (is_array($standing) && is_array($draft)) {
        $state = pg_proto_verdict_state($standing, $fingerprint);
        $changes = pg_proto_viewer_changes(pg_proto_verdict_nodes($standing),
            pg_proto_node_fingerprints(isset($draft['page_tree_json']) ? (string) $draft['page_tree_json'] : ''), $state['older']);
    }

    return pg_proto_viewer_review_data($link, $page_name, $standing, $fingerprint, $changes);
}

/**
 * The draft fingerprints of the given pages, read from their rows: page id
 * => fingerprint. A page with no row is left out.
 *
 * @param int[] $page_ids
 * @return array
 */
function pg_proto_draft_fingerprints($page_ids)
{
    $ids = array();

    foreach ((array) $page_ids as $id) {
        if ((int) $id > 0) {
            $ids[(int) $id] = (int) $id;
        }
    }

    if (!$ids) {
        return array();
    }

    $rows = db_items("SELECT * FROM page WHERE page_id IN (" . implode(',', $ids) . ")");
    $out = array();

    foreach (is_array($rows) ? $rows : array() as $row) {
        $out[(int) $row['page_id']] = pg_proto_draft_fingerprint_of_row($row);
    }

    return $out;
}

/**
 * Each draft page's mark for one link asking approval
 * (pg_proto_review_page_states()), for the given draft pages; empty for a
 * link asking nothing.
 *
 * @param array $link     prototype_links row
 * @param int[] $page_ids the design's draft pages
 * @return array page id => state
 */
function pg_proto_link_review_states($link, $page_ids)
{
    if (!is_array($link) || !pg_proto_link_asks_approval($link) || !$page_ids) {
        return array();
    }

    $fingerprints = array();
    $found = pg_proto_draft_fingerprints($page_ids);

    // Pages keep the order given; one whose row is gone has no fingerprint.
    foreach ((array) $page_ids as $id) {
        $fingerprints[(int) $id] = isset($found[(int) $id]) ? $found[(int) $id] : '';
    }

    return pg_proto_review_page_states(pg_proto_verdicts_of_link((int) $link['id']), $fingerprints);
}

/**
 * Every verdict of a design (idx_style), oldest first.
 *
 * @param int $style_id
 * @return array[]
 */
function pg_proto_verdicts_of_style($style_id)
{
    if (((int) $style_id <= 0) || !pg_proto_approval_ready()) {
        return array();
    }

    $rows = db_items("SELECT * FROM prototype_verdicts WHERE style_id = '" . (int) $style_id . "' ORDER BY id LIMIT 5000");

    return is_array($rows) ? $rows : array();
}

/**
 * The newest verdict id of a design, 0 for none: added to the comments'
 * stamp, so the designer's list is fetched again when a verdict comes.
 *
 * @param int $style_id
 * @return int
 */
function pg_proto_verdicts_style_stamp($style_id)
{
    if (((int) $style_id <= 0) || !pg_proto_approval_ready()) {
        return 0;
    }

    return (int) db_value('SELECT MAX(id) FROM prototype_verdicts WHERE style_id = ' . (int) $style_id);
}

/**
 * The designer's Comments list with the verdicts: each page group gets
 * `verdicts`, the standing one of each link (pg_proto_verdict_public() with
 * link_id and link_hint), in link order. A page with verdicts and no
 * comment gets a group of its own. Groups are ordered as $names (the page
 * names in their order); a page without a name (deleted) comes last. Pure.
 *
 * @param array[] $groups       pg_proto_comment_list_pages()
 * @param array[] $rows         prototype_verdicts rows of the design
 * @param array   $names        page id => name, in the list's order
 * @param array   $hints        link id => token hint
 * @param array   $fingerprints page id => the draft's fingerprint now
 * @return array[]
 */
function pg_proto_comment_list_verdicts($groups, $rows, $names, $hints, $fingerprints)
{
    $by_link = array();

    foreach ((array) $rows as $row) {
        if (is_array($row) && isset($row['link_id'])) {
            $by_link[(int) $row['link_id']][] = $row;
        }
    }

    ksort($by_link);
    $verdicts = array();

    foreach ($by_link as $link_id => $link_rows) {
        foreach (pg_proto_verdicts_newest($link_rows) as $page_id => $row) {
            $item = pg_proto_verdict_public($row, isset($fingerprints[$page_id]) ? (string) $fingerprints[$page_id] : '');
            $item['link_id'] = (int) $link_id;
            $item['link_hint'] = isset($hints[$link_id]) ? (string) $hints[$link_id] : '';
            $verdicts[(int) $page_id][] = $item;
        }
    }

    $by_page = array();

    foreach ((array) $groups as $group) {
        $group['verdicts'] = array();
        $by_page[(int) $group['page_id']] = $group;
    }

    foreach ($verdicts as $page_id => $list) {
        if (!isset($by_page[$page_id])) {
            $by_page[$page_id] = array(
                'page_id' => $page_id,
                'name' => isset($names[$page_id]) ? (string) $names[$page_id] : '',
                'open' => 0,
                'resolved' => 0,
                'links' => 0,
                'threads' => array(),
            );
        }

        $by_page[$page_id]['verdicts'] = $list;
    }

    $out = array();

    foreach ((array) $names as $page_id => $name) {
        if (isset($by_page[(int) $page_id])) {
            $out[] = $by_page[(int) $page_id];
            unset($by_page[(int) $page_id]);
        }
    }

    ksort($by_page);

    foreach ($by_page as $group) {
        $out[] = $group;
    }

    return $out;
}

/**
 * Takes a verdict that passed the comment door's gates
 * (pg_proto_comment_door()): the link must ask approval, the fields are
 * checked (pg_proto_verdict_check()), the address counted in the comments'
 * burst, and the verdict sealed with the draft's fingerprints as it is now.
 * Then the link's maker hears of it in the bell and the approver gets a
 * receipt.
 *
 * @param array $link    prototype_links row
 * @param int   $page_id a draft page of the link's design it shows
 * @param array $post
 * @param int   $now
 * @return array status, error ('' when written), verdict (public shape)
 */
function pg_proto_verdict_take($link, $page_id, $post, $now)
{
    $refuse = function ($code) {
        return array('status' => pg_proto_comment_error_status($code), 'error' => $code, 'verdict' => null);
    };

    if (!pg_proto_link_asks_approval($link)) {
        return $refuse('approval_off');
    }

    $checked = pg_proto_verdict_check(array(
        'verdict' => isset($post['verdict']) ? $post['verdict'] : null,
        'name' => isset($post['name']) ? $post['name'] : null,
        'note' => isset($post['note']) ? $post['note'] : '',
    ));

    if ($checked['error'] !== '') {
        return $refuse($checked['error']);
    }

    if (pg_proto_comment_limit_refusal(pg_proto_comment_burst(), 0) !== '') {
        return $refuse('rate');
    }

    $draft = pg_proto_verdict_draft_row($page_id);
    $fingerprint = pg_proto_draft_fingerprint_of_row($draft);
    $row = $draft ? pg_proto_verdict_row($checked, $link, $page_id, $fingerprint, pg_proto_node_fingerprints((string) $draft['page_tree_json'])) : null;
    $id = $row ? pg_proto_verdict_save($row, $now) : 0;
    $saved = ($id > 0) ? db_item("SELECT * FROM prototype_verdicts WHERE id = '" . (int) $id . "'") : null;

    if (!is_array($saved)) {
        return array('status' => 500, 'error' => 'save', 'verdict' => null);
    }

    $page_name = (string) $draft['page_name'];
    pg_proto_verdict_bell($link, $saved, $page_name);
    pg_proto_verdict_receipt($link, $saved, $page_name);

    return array('status' => 201, 'error' => '', 'verdict' => pg_proto_verdict_public($saved, $fingerprint));
}

/* ---------------------------------------------------------------------------
   The bell line to the link's maker and the approver's receipt
   --------------------------------------------------------------------------- */

/**
 * The bell line's words for a verdict, in the reader's language. An empty
 * name or page (a deleted verdict or page) gives a text without them. Pure
 * but for lang().
 *
 * @param string $verdict    approved or changes_requested
 * @param string $page_name
 * @param string $guest_name
 * @return array short, brief, title
 */
function pg_proto_verdict_bell_words($verdict, $page_name, $guest_name)
{
    $approved = ((string) $verdict === 'approved');
    $page_name = (string) $page_name;
    $guest_name = (string) $guest_name;
    $short = $approved ? lang('Prototype page approved') : lang('Changes requested on a prototype');

    if (($page_name !== '') && ($guest_name !== '')) {
        $brief = lang(array('string' => '{var:1} on {var:2}', 'vars' => array($guest_name, $page_name)));
        $title = $approved
            ? lang(array('string' => '{var:1} approved the prototype of {var:2}.', 'vars' => array($guest_name, $page_name)))
            : lang(array('string' => '{var:1} asked for changes on the prototype of {var:2}.', 'vars' => array($guest_name, $page_name)));
    } else {
        $brief = ($page_name !== '') ? $page_name : $short;
        $title = $short;
    }

    return array('short' => $short, 'brief' => $brief, 'title' => $title);
}

/**
 * Tells the link's maker of a verdict: one bell line for each, addressed to
 * them, referring to the verdict. The comments' burst bounds how many a guest
 * can send.
 *
 * @param array  $link
 * @param array  $row       the saved prototype_verdicts row
 * @param string $page_name
 * @return bool whether a line was written
 */
function pg_proto_verdict_bell($link, $row, $page_name)
{
    $maker = (is_array($link) && isset($link['created_by'])) ? (int) $link['created_by'] : 0;

    if (($maker <= 0) || !is_array($row)) {
        return false;
    }

    include_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');

    if (!pg_notification_targets_available()) {
        return false;
    }

    return (bool) db_q("INSERT INTO notifications (action, type, title, target_user_id, reference_id, user, readed, timestamp)
        VALUES ('prototype_verdict', 'info', ?, ?, ?, '', '0', UNIX_TIMESTAMP())",
        array(mb_substr((string) $page_name, 0, 250, 'UTF-8'), $maker, (int) $row['id']));
}

/**
 * A verdict bell line's words and address as the bell draws them: the
 * verdict, the guest's name and the design read through reference_id, the
 * page's name from the title.
 *
 * @param array $notification
 * @return array short, brief, title, url
 */
function pg_proto_verdict_bell_display($notification)
{
    $id = isset($notification['reference_id']) ? (int) $notification['reference_id'] : 0;
    $row = (($id > 0) && pg_proto_approval_ready()) ? db_item("SELECT * FROM prototype_verdicts WHERE id = '" . $id . "'") : null;
    $row = is_array($row) ? $row : null;
    $words = pg_proto_verdict_bell_words($row ? (string) $row['verdict'] : '', isset($notification['title']) ? (string) $notification['title'] : '', $row ? (string) $row['author_name'] : '');

    return $words + array('url' => $row ? pg_proto_comment_bell_url($row['style_id'], $row['page_id']) : 'view_pages.php');
}

/**
 * The receipt mailed to the approver for a verdict: its subject and plain
 * text, with the link's address to look again ('' when it cannot be read
 * back) and never its password. Pure but for lang().
 *
 * @param array  $row       prototype_verdicts row
 * @param string $page_name
 * @param string $design    the design's name
 * @param string $date      the verdict's day as the site writes it
 * @param string $url       the link's address, or ''
 * @return array subject, body
 */
function pg_proto_verdict_receipt_words($row, $page_name, $design, $date, $url)
{
    $approved = ((string) $row['verdict'] === 'approved');
    $vars = array((string) $page_name, (string) $design, (string) $date);
    $subject = $approved
        ? lang(array('string' => 'You approved {var:1}', 'vars' => $vars))
        : lang(array('string' => 'You asked for changes on {var:1}', 'vars' => $vars));
    $lines = array(
        lang(array('string' => 'Hello {var:1},', 'vars' => array((string) $row['author_name']))),
        '',
        $approved
            ? lang(array('string' => 'You approved the page {var:1} of {var:2} on {var:3}.', 'vars' => $vars))
            : lang(array('string' => 'You asked for changes on the page {var:1} of {var:2} on {var:3}.', 'vars' => $vars)),
    );

    if ((string) $row['note'] !== '') {
        $lines[] = '';
        $lines[] = lang('Your note:');
        $lines[] = (string) $row['note'];
    }

    if ((string) $url !== '') {
        $lines[] = '';
        $lines[] = lang('To look at the prototype again:');
        $lines[] = (string) $url;
    }

    $lines[] = '';
    $lines[] = lang('This e-mail answers your review only.');

    return array('subject' => $subject, 'body' => implode("\n", $lines));
}

/**
 * Mails the receipt of a verdict to the approver's address the designer
 * typed on the link; nothing when there is none.
 *
 * @param array  $link
 * @param array  $row       the saved prototype_verdicts row
 * @param string $page_name
 * @return bool whether a mail was handed on
 */
function pg_proto_verdict_receipt($link, $row, $page_name)
{
    $approval = pg_proto_link_approval($link);
    $to = $approval['approver_email'];

    if (($to === '') || (filter_var($to, FILTER_VALIDATE_EMAIL) === false) || !function_exists('email')) {
        return false;
    }

    $token = pg_proto_link_token($link);
    $design = pg_proto_link_design_name((int) $link['style_id']);
    $words = pg_proto_verdict_receipt_words($row, $page_name, ($design !== null) ? $design : '',
        date('Y-m-d', (int) $row['created_at']), ($token !== '') ? pg_proto_link_url($token) : '');

    return (bool) email(array(
        'to' => $to,
        'to_name' => $approval['approver_name'],
        'from_name' => ORGANIZATION_NAME,
        'from_email_address' => EMAIL_ADDRESS,
        'subject' => $words['subject'],
        'format' => 'plain_text',
        'body' => $words['body'],
        'queue' => true,
    ));
}
