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
 * lang().
 *
 * @param array      $link        prototype_links row
 * @param string     $page_name
 * @param array|null $standing    the standing verdict row
 * @param string     $fingerprint the draft's fingerprint now
 * @return array|null
 */
function pg_proto_viewer_review_data($link, $page_name, $standing, $fingerprint)
{
    $approval = pg_proto_link_approval($link);

    if (!$approval['approval']) {
        return null;
    }

    $limits = pg_proto_comment_limits();

    return array(
        'page' => (string) $page_name,
        'verdict' => is_array($standing) ? pg_proto_verdict_public($standing, $fingerprint) : null,
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

    return pg_proto_viewer_review_data($link, $page_name, pg_proto_verdict_standing((int) $link['id'], (int) $page_id),
        pg_proto_draft_fingerprint_of_row(pg_proto_verdict_draft_row($page_id)));
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

/* ---------------------------------------------------------------------------
   The approval hold: "Publish only after approval"
   --------------------------------------------------------------------------- */

/**
 * The link that holds a draft back from the site, or null: a link of the
 * draft's design (no bundle link) asking approval with "Publish only after
 * approval", neither revoked nor expired, that shows the page, and whose
 * standing verdict on it does not approve the draft as it is now. Every such
 * link must approve; the first one still waiting is named. Pure.
 *
 * A third hold beside an open experiment's variant and a release bundle's
 * draft (pg_experiment_variant_hold_message()), read only where a draft goes
 * live: it never stops a draft being binned or deleted.
 *
 * @param array  $links       prototype_links rows of the page's design, by id
 * @param array  $verdicts    prototype_verdicts rows of the page
 * @param string $fingerprint the draft's fingerprint now
 * @param string $access      the access control of the folder the draft goes live in
 * @param int    $now
 * @return array|null link_id, hint, approver_name
 */
function pg_proto_approval_hold_pick($links, $verdicts, $fingerprint, $access, $now)
{
    foreach ((array) $links as $link) {
        if (!is_array($link) || !empty($link['bundle_id']) || !empty($link['revoked_at'])) {
            continue;
        }

        $expires = isset($link['expires_at']) ? (int) $link['expires_at'] : 0;
        $approval = pg_proto_link_approval($link);

        if (!$approval['publish_rule'] || (($expires > 0) && ($expires <= (int) $now))
            || !pg_proto_link_page_shown(isset($link['audience']) ? $link['audience'] : '', $access)) {
            continue;
        }

        $id = (int) $link['id'];
        $own = array();

        foreach ((array) $verdicts as $verdict) {
            if (is_array($verdict) && isset($verdict['link_id']) && ((int) $verdict['link_id'] === $id)) {
                $own[] = $verdict;
            }
        }

        $standing = null;

        foreach (pg_proto_verdicts_newest($own) as $row) {
            $standing = $row;
        }

        if (!pg_proto_verdict_approves($standing, (string) $fingerprint)) {
            return array(
                'link_id' => $id,
                'hint' => isset($link['token_hint']) ? (string) $link['token_hint'] : '',
                'approver_name' => $approval['approver_name'],
            );
        }
    }

    return null;
}

/**
 * The approval holds of a list of pages: page id => pg_proto_approval_hold_pick()
 * with the page's name, for the drafts a link holds; the others are left out.
 *
 * @param array    $page_ids
 * @param int|null $now
 * @return array
 */
function pg_proto_approval_holds($page_ids, $now = null)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) $page_ids))));
    $out = array();

    if (!$ids || !pg_proto_approval_ready() || !pg_page_draft_ready()) {
        return $out;
    }

    $now = ($now === null) ? time() : (int) $now;
    $pages = array();

    foreach ((array) db_items("SELECT * FROM page WHERE page_id IN (" . implode(',', $ids) . ")") as $row) {
        if (pg_page_is_draft($row['page_folder'])) {
            $pages[(int) $row['page_id']] = $row;
        }
    }

    $styles = array();

    foreach ($pages as $row) {
        $styles[(int) $row['page_style']] = true;
    }

    if (!$styles) {
        return $out;
    }

    $links = array();

    foreach ((array) db_items("SELECT * FROM prototype_links
        WHERE style_id IN (" . implode(',', array_keys($styles)) . ") AND approval = 1 AND publish_rule = 1 AND revoked_at = 0
        ORDER BY id") as $link) {
        $links[(int) $link['style_id']][] = $link;
    }

    foreach ($pages as $id => $row) {
        $own = isset($links[(int) $row['page_style']]) ? $links[(int) $row['page_style']] : array();

        if (!$own) {
            continue;
        }

        $hold = pg_proto_approval_hold_pick($own, pg_proto_verdicts_of_page($id), pg_proto_draft_fingerprint_of_row($row),
            pg_proto_link_page_access($id), $now);

        if ($hold) {
            $hold['page_name'] = (string) $row['page_name'];
            $out[$id] = $hold;
        }
    }

    return $out;
}

/**
 * The link that holds one draft back from the site, or null.
 *
 * @param int $page_id
 * @return array|null link_id, hint, approver_name, page_name
 */
function pg_proto_approval_hold($page_id)
{
    $holds = pg_proto_approval_holds(array((int) $page_id));

    return isset($holds[(int) $page_id]) ? $holds[(int) $page_id] : null;
}

/**
 * Why a held draft stays off the site, naming the approver and the link by
 * its hint. Pure but for lang().
 *
 * @param string $page_name
 * @param array  $hold pg_proto_approval_hold_pick()
 * @return string
 */
function pg_proto_approval_hold_text($page_name, $hold)
{
    $hint = isset($hold['hint']) ? (string) $hold['hint'] : '';
    $name = isset($hold['approver_name']) ? (string) $hold['approver_name'] : '';

    if ($name === '') {
        return lang(array(
            'string' => 'The page "{var:1}" waits for approval on the prototype link {var:2}…, so it stays a draft until it is approved.',
            'vars' => array((string) $page_name, $hint)));
    }

    return lang(array(
        'string' => 'The page "{var:1}" waits for {var:2} to approve it on the prototype link {var:3}…, so it stays a draft until then.',
        'vars' => array((string) $page_name, $name, $hint)));
}

/**
 * The refusal of a held draft going live; '' when no link holds it.
 *
 * @param int    $page_id
 * @param string $page_name
 * @return string
 */
function pg_proto_approval_hold_message($page_id, $page_name)
{
    $hold = pg_proto_approval_hold($page_id);

    return $hold ? pg_proto_approval_hold_text($page_name, $hold) : '';
}

/**
 * The reason an administrator (role 0) gave for publishing a held draft
 * anyway (the save's approval_override), on one line and at most 500
 * characters; '' for anybody else or without a reason. Pure.
 *
 * @param array $page the save's page fields
 * @param array $user
 * @return string
 */
function pg_proto_approval_override_reason($page, $user)
{
    if (!is_array($user) || !isset($user['role']) || ((string) $user['role'] !== '0')) {
        return '';
    }

    $reason = (is_array($page) && isset($page['approval_override'])) ? pg_proto_comment_plain($page['approval_override'], false) : null;

    if (!is_string($reason)) {
        return '';
    }

    $reason = trim((string) preg_replace('/\s+/u', ' ', $reason));

    return mb_substr($reason, 0, 500, 'UTF-8');
}

/**
 * Writes the Site Log line of a held draft published anyway: the page, the
 * link by its id and hint, the reason.
 *
 * @param string $page_name
 * @param array  $hold
 * @param string $reason
 */
function pg_proto_approval_override_log($page_name, $hold, $reason)
{
    log_activity(lang(array(
        'string' => 'page ({var:1}) was published without the approval prototype link #{var:2} ({var:3}…) asks for: {var:4}',
        'vars' => array((string) $page_name, (int) $hold['link_id'], (string) $hold['hint'], (string) $reason))),
        isset($_SESSION['sessionusername']) ? $_SESSION['sessionusername'] : '');
}

/**
 * A tab page's held state in the visual editor for a draft a link holds:
 * the Publish sheet's row "Waiting for approval", its tooltip naming the
 * approver and the link, and whether this person may publish it anyway
 * (role 0). Shaped as pg_experiment_editor_page_state()'s entries, without
 * a status, so the top bar shows no experiment chip. Pure but for lang().
 *
 * @param array  $hold         pg_proto_approval_hold_pick()
 * @param string $page_name
 * @param bool   $may_override
 * @return array
 */
function pg_proto_approval_editor_state($hold, $page_name, $may_override)
{
    return array(
        'role' => 'approval',
        'held' => true,
        'label' => lang('Waiting for approval'),
        'hold_text' => pg_proto_approval_hold_text($page_name, $hold),
        'override' => (bool) $may_override,
    );
}
