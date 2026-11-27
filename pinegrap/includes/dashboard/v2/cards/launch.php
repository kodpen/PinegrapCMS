<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The set-up card: the steps before going live for the site's purpose, each
 * marked from the setting that decides it (includes/fn/launch_journey_site.php
 * reads the facts, includes/fn/launch_journey.php judges them), blockers
 * first, with "Do it" opening the screen where the step is done. While no
 * purpose is stored the card asks for one ("What is this site for?").
 *
 * When every step is done or hidden and the go-live check has no blocker
 * left the journey ends by itself: the card is closed and answers denied, which takes it off the dashboard; Site Settings
 * › General brings it back.
 *
 * Under the steps, while Pinegrap AI can write, the line "Make your site
 * from a description" opens the New design window on its "Describe it" start.
 *
 * The card's foot, "Ready to go live?", opens the go-live check: "Must be
 * fixed" and "Worth a look", and "Go live", which turns maintenance mode off
 * and removes the robots.txt rule closing the whole site itself
 * (pg_launch_journey_go_live()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

/**
 * The card's texts.
 *
 * @return array
 */
function pg_dashboard_v2_launch_labels()
{
    return array(
        'do_it'    => lang('Do it'),
        'done'     => lang('Done'),
        'not_here' => lang('Not for this site'),
        'more'     => lang('More for {var:1}'),
        'menu'     => lang('Set-up options'),
        'purpose'  => lang('Change purpose'),
        'hide'     => lang('Hide set-up'),
        'ask'      => lang('What is this site for?'),
        'ask_line' => lang('The set-up steps follow what the site is for. You can change it later in Site Settings › General.'),
        'choose'   => lang('Choose'),
        'later'    => lang('Later'),
        'close'    => lang('Close'),
        'failed'   => lang('The change could not be saved.'),
        'blocker'  => lang('Must be fixed'),
        'ready'    => lang('Ready to go live?'),
        'check'    => lang('Go-live check'),
        'must'     => lang('Must be fixed'),
        'worth'    => lang('Worth a look'),
        'nothing'  => lang('Nothing to fix'),
        'fix'      => lang('Fix'),
        'go_live'  => lang('Go live'),
        'cancel'   => lang('Cancel'),
        'changes'  => lang('Go live changes this itself:'),
        'left'     => lang('These are not fixed yet:'),
        'anyway'   => lang('I know, go live anyway'),
        'live'     => lang('Live since {var:1}'),
        // The agency summary's drawer.
        'summary'      => lang('Agency summary'),
        'summary_line' => lang('What the customer sees on their link, and what they answered.'),
        'need'         => lang('What we need from you'),
        'no_lines'     => lang('No lines yet. Add what the customer should send: a logo, texts, domain access.'),
        'add'          => lang('Add a line'),
        'add_hint'     => lang('For example: your logo as SVG or PNG'),
        'remove'       => lang('Remove'),
        'answer_kind'  => lang('Answer'),
        'kind_text'    => lang('Text'),
        'kind_one'     => lang('One file'),
        'kind_several' => lang('Several files'),
        'kind_ask'     => lang('What the customer sent for this line is cleared from it. Files already sent stay in the From the customer folder.'),
        'kind_change'  => lang('Change'),
        'link'         => lang('Link for the customer'),
        'copy'         => lang('Copy'),
        'withdraw'     => lang('Withdraw link'),
        'withdraw_ask' => lang('The customer\'s link stops working at once. Their answers are kept.'),
        'expiry'       => lang('The link works for'),
        'days'         => lang('{var:1} days'),
        'make'         => lang('Make a new link'),
        'loading'      => lang('Loading…'),
        'no_key'       => lang('The agency summary needs the site\'s encryption key and the latest upgrade.'),
    );
}

/**
 * @param array $user
 * @param int   $period
 * @param int   $compare
 * @return array
 */
function pg_dashboard_v2_card_launch($user, $period, $compare)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/site_brief.php');
    $stored = pg_launch_journey_stored();

    if (!pg_dashboard_v2_launch_gate($user['role'], $stored['closed'])) {
        return array('status' => 'denied', 'data' => array());
    }

    $purpose = ($stored['purpose'] !== '') ? $stored['purpose'] : 'other';
    $facts = pg_launch_journey_facts($user, $purpose);
    $card = pg_launch_journey_card($purpose, $facts, $stored['hidden']);

    // Every step done or hidden and nothing left blocking going live: the
    // journey ends by itself. With maintenance mode on the card stays, so
    // "Go live" is there to turn it off.
    if (($stored['purpose'] !== '') && pg_launch_journey_card_closes($purpose, $facts, $stored['hidden'])) {
        pg_launch_journey_store(array('closed' => true));
        return array('status' => 'denied', 'data' => array());
    }

    $purposes = array();
    foreach (pg_launch_journey_purpose_cards() as $key => $choice) {
        $purposes[] = array('key' => $key) + $choice;
    }

    return array('status' => 'success', 'data' => array(
        'head'     => $card['head'],
        'status'   => $card['status'],
        'rows'     => $card['rows'],
        // No purpose yet: the dialog opens by itself, with a template's
        // suggestion picked.
        'ask'      => ($stored['purpose'] === ''),
        'purpose'  => ($stored['purpose'] !== '') ? $stored['purpose'] : $stored['suggested'],
        'purposes' => $purposes,
        'labels'   => pg_dashboard_v2_launch_labels(),
        // "Make your site from a description" under the steps, while
        // Pinegrap AI can write; the New design window's brief takes the
        // stored purpose (pg_designer_describe_pane()).
        'describe' => pg_launch_journey_describe_line(pg_site_brief_problem()),
        // The foot: "Ready to go live?" and its check, or the go-live time.
        'golive'   => pg_launch_journey_golive_view($purpose, $facts, $stored['hidden'], (string) ($_SESSION['software']['token'] ?? '')),
        'live_at'  => ($stored['live_at'] > 0) ? get_absolute_time(array('timestamp' => $stored['live_at'], 'format' => 'plain_text', 'size' => 'short')) : '',
    ));
}
