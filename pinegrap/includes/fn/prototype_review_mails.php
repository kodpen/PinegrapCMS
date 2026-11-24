<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The mails to the approver of a prototype link asking approval, beside the
 * receipt of a verdict (pg_proto_verdict_receipt()): the answer mail, sent
 * when a designer answers a comment on the link, at most one an hour per
 * link; and the "Answer by" reminder, sent by the general job on the day
 * before the link's date while pages are still undecided. Both go only to the
 * address the designer typed on the link, carry the link's address when the
 * site key reads it back, and never its password.
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
 * The pages still undecided for one link: no standing verdict, or one given
 * to an earlier version of the draft (its fingerprint is not the draft's
 * now). Approve and Request changes on the current draft are both decisions.
 * Pure.
 *
 * @param int[]  $page_ids     the pages the link shows, in order
 * @param array  $newest       page id => standing verdict row (pg_proto_verdicts_newest())
 * @param array  $fingerprints page id => the draft's fingerprint now
 * @return int[]
 */
function pg_proto_undecided_pages($page_ids, $newest, $fingerprints)
{
    $out = array();

    foreach ((array) $page_ids as $page_id) {
        $page_id = (int) $page_id;
        $verdict = isset($newest[$page_id]) ? $newest[$page_id] : null;
        $state = pg_proto_verdict_state($verdict, isset($fingerprints[$page_id]) ? (string) $fingerprints[$page_id] : '');

        if (($state['state'] === 'not_reviewed') || $state['older']) {
            $out[] = $page_id;
        }
    }

    return $out;
}

/**
 * Whether the general job's reminder pass runs now: between 09:00 and 20:00
 * site time, so no approver is written to at night, and once a day (the
 * last pass was on an earlier day). Pure.
 *
 * @param int $now
 * @param int $last the time of the last pass, 0 for none
 * @return bool
 */
function pg_proto_reminder_pass_open($now, $last)
{
    $hour = (int) date('G', (int) $now);

    if (($hour < 9) || ($hour >= 20)) {
        return false;
    }

    return ((int) $last <= 0) || (date('Y-m-d', (int) $last) !== date('Y-m-d', (int) $now));
}

/**
 * The answer mail's subject and plain-text body. Pure but for lang().
 *
 * @param string $name    the approver's name
 * @param string $design  the design's name
 * @param int    $answers the designer's answers since the guests last wrote
 * @param string $url     the link's address, '' when it cannot be read back
 * @return array subject, body
 */
function pg_proto_answer_mail_words($name, $design, $answers, $url)
{
    $answers = max(1, (int) $answers);
    $lines = array(
        lang(array('string' => 'Hello {var:1},', 'vars' => array((string) $name))),
        '',
        ($answers === 1)
            ? lang(array('string' => 'There is a new answer to the comments on the prototype of {var:1}.', 'vars' => array((string) $design)))
            : lang(array('string' => 'There are {var:1} new answers to the comments on the prototype of {var:2}.', 'vars' => array($answers, (string) $design))),
    );

    if ((string) $url !== '') {
        $lines[] = '';
        $lines[] = lang('To read them and look at the prototype again:');
        $lines[] = (string) $url;
    }

    $lines[] = '';
    $lines[] = lang('This e-mail answers your review only.');

    return array(
        'subject' => lang(array('string' => 'New answers on the prototype of {var:1}', 'vars' => array((string) $design))),
        'body' => implode("\n", $lines),
    );
}

/**
 * The "Answer by" reminder's subject and plain-text body. Pure but for
 * lang().
 *
 * @param string $name      the approver's name
 * @param string $design    the design's name
 * @param string $answer_by the link's "Answer by" date
 * @param int    $undecided pages still undecided
 * @param string $url       the link's address, '' when it cannot be read back
 * @return array subject, body
 */
function pg_proto_reminder_mail_words($name, $design, $answer_by, $undecided, $url)
{
    $undecided = max(1, (int) $undecided);
    $lines = array(
        lang(array('string' => 'Hello {var:1},', 'vars' => array((string) $name))),
        '',
        lang(array('string' => 'Your answer on the prototype of {var:1} is asked for by {var:2}.', 'vars' => array((string) $design, (string) $answer_by))),
        ($undecided === 1)
            ? lang('One page is still waiting for "Approve this page" or "Request changes".')
            : lang(array('string' => '{var:1} pages are still waiting for "Approve this page" or "Request changes".', 'vars' => array($undecided))),
    );

    if ((string) $url !== '') {
        $lines[] = '';
        $lines[] = lang('To review the pages:');
        $lines[] = (string) $url;
    }

    $lines[] = '';
    $lines[] = lang('This e-mail answers your review only.');

    return array(
        'subject' => lang(array('string' => 'Answer by {var:1}: the prototype of {var:2}', 'vars' => array((string) $answer_by, (string) $design))),
        'body' => implode("\n", $lines),
    );
}

/**
 * Hands a plain-text mail to the approver of a link, queued, from the site's
 * address.
 *
 * @param array $approval pg_proto_link_approval()
 * @param array $words    subject, body
 * @return bool
 */
function pg_proto_review_mail_send($approval, $words)
{
    if (!function_exists('email')) {
        return false;
    }

    return (bool) email(array(
        'to' => $approval['approver_email'],
        'to_name' => $approval['approver_name'],
        'from_name' => ORGANIZATION_NAME,
        'from_email_address' => EMAIL_ADDRESS,
        'subject' => $words['subject'],
        'format' => 'plain_text',
        'body' => $words['body'],
        'queue' => true,
    ));
}

/**
 * The link's address for a mail, '' when the site key cannot read it back.
 *
 * @param array $link
 * @return string
 */
function pg_proto_review_mail_url($link)
{
    $token = pg_proto_link_token($link);

    return ($token !== '') ? pg_proto_link_url($token) : '';
}

/**
 * The designer's answers on a link since its guests last wrote (a comment or
 * a verdict): what the answer mail counts.
 *
 * @param int $link_id
 * @return int
 */
function pg_proto_answers_since_guest($link_id)
{
    $link_id = (int) $link_id;
    $since = max(
        (int) db_value("SELECT MAX(created_at) FROM prototype_comments WHERE link_id = '" . $link_id . "' AND user_id = '0'"),
        (int) db_value("SELECT MAX(created_at) FROM prototype_verdicts WHERE link_id = '" . $link_id . "'")
    );

    return (int) db_value("SELECT COUNT(*) FROM prototype_comments
        WHERE link_id = '" . $link_id . "' AND user_id > 0 AND parent_id > 0 AND created_at >= '" . $since . "'");
}

/**
 * Tells the approver of a link that a designer answered: called after an
 * answer is saved (pg_proto_comment_answer()). Only for a live link asking
 * approval with an address; at most one mail an hour per link, a firewall
 * rate counter with the link's id as its subject (as the bell line to the
 * link's maker is counted), so no column keeps it. Answers written within
 * the hour are counted by the next mail.
 *
 * @param int $link_id
 * @param int $now
 * @return bool whether a mail was handed on
 */
function pg_proto_answer_mail($link_id, $now = 0)
{
    $now = ((int) $now > 0) ? (int) $now : time();

    if (!pg_proto_approval_ready()) {
        return false;
    }

    $link = pg_proto_link_get((int) $link_id);

    if (($link === null) || (pg_proto_link_state($link, $now) !== 'ok') || !pg_proto_link_asks_approval($link)) {
        return false;
    }

    $approval = pg_proto_link_approval($link);
    $answers = pg_proto_answers_since_guest((int) $link['id']);

    // Checked before the counter, so a link without an address never spends
    // its hour.
    if (!pg_proto_answer_mail_due($approval['approver_email'], $answers, false)) {
        return false;
    }

    $rate = pg_proto_answer_mail_rate();
    $exceeded = function_exists('waf_rate_exceeded')
        && waf_rate_exceeded((string) (int) $link['id'], 'prototype-answer-mail', $rate['limit'], $rate['window']);

    if (!pg_proto_answer_mail_due($approval['approver_email'], $answers, $exceeded)) {
        return false;
    }

    $design = pg_proto_link_design_name((int) $link['style_id']);

    return pg_proto_review_mail_send($approval, pg_proto_answer_mail_words(
        $approval['approver_name'], ($design !== null) ? $design : '', $answers, pg_proto_review_mail_url($link)));
}

/**
 * The pages of a link that are still undecided: the design's draft pages
 * the link shows, without a standing verdict or with one given to an earlier
 * version.
 *
 * @param array $link prototype_links row
 * @return int[]
 */
function pg_proto_link_undecided_pages($link)
{
    $page_ids = array();
    $fingerprints = array();

    foreach (pg_proto_design_pages((int) $link['style_id']) as $page) {
        $page_id = (int) $page['id'];

        if (!pg_proto_link_page_shown((string) $link['audience'], pg_proto_link_page_access($page_id))) {
            continue;
        }

        $page_ids[] = $page_id;
        $fingerprints[$page_id] = pg_proto_draft_fingerprint_of_row(pg_proto_verdict_draft_row($page_id));
    }

    return pg_proto_undecided_pages($page_ids, pg_proto_verdicts_newest(pg_proto_verdicts_of_link((int) $link['id'])), $fingerprints);
}

/**
 * The general job's "Answer by" pass: on the day before a link's date, once
 * between 09:00 and 20:00 site time, the approver of every live link asking
 * approval with pages still undecided is reminded. The pass time is claimed
 * in config_text before anything is sent (pg_config_text_claim()), so two
 * overlapping runs never both send and a pass that dies loses its reminders
 * rather than sending them twice. A date set after the day's pass gets no
 * reminder.
 *
 * @param int $now
 * @return int how many reminders went out
 */
function pg_proto_answer_by_reminders_run($now)
{
    $now = (int) $now;

    if (!pg_proto_approval_ready() || !function_exists('pg_config_text_claim')) {
        return 0;
    }

    $stored = pg_config_text('prototype_reminders_at');
    $last = ($stored !== '') ? (int) $stored : 0;

    if (!pg_proto_reminder_pass_open($now, $last)) {
        return 0;
    }

    if (!pg_config_text_claim('prototype_reminders_at', $stored, (string) $now)) {
        return 0;
    }

    $tomorrow = date('Y-m-d', mktime(12, 0, 0, (int) date('n', $now), (int) date('j', $now) + 1, (int) date('Y', $now)));
    $links = db_items(
        "SELECT * FROM prototype_links
        WHERE
            approval = '1'
            AND answer_by = '" . $tomorrow . "'
            AND approver_email != ''
            AND revoked_at = '0'
            AND bundle_id = '0'
        ORDER BY id
        LIMIT 500");
    $sent = 0;

    foreach ((array) $links as $link) {
        if (pg_proto_link_state($link, $now) !== 'ok') {
            continue;
        }

        $approval = pg_proto_link_approval($link);

        if (filter_var($approval['approver_email'], FILTER_VALIDATE_EMAIL) === false) {
            continue;
        }

        $undecided = count(pg_proto_link_undecided_pages($link));

        // Already sent today is ruled out by the pass itself.
        if (!pg_proto_answer_by_reminder_due($approval['answer_by'], $now, $undecided, '')) {
            continue;
        }

        $design = pg_proto_link_design_name((int) $link['style_id']);

        if (pg_proto_review_mail_send($approval, pg_proto_reminder_mail_words($approval['approver_name'],
            ($design !== null) ? $design : '', $approval['answer_by'], $undecided, pg_proto_review_mail_url($link)))) {
            $sent++;
        }
    }

    return $sent;
}
