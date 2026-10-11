<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 4 - Online Engagement: what is happening on the site right now.
 *
 * Loaded and called by includes/dashboard/widgets.php; see that file for the
 * contract every widget follows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_WIDGETS')) {
    exit;
}

function pg_dashboard_widget_4($request, $user)
{
    // ── Online Engagement ───────────────────────────────────
    //
    // Was "Whois Online", a flat list of accounts. It now answers
    // the question that list only half answered: is anything
    // happening right now that needs a person?
    //
    // Order is deliberate. Counts first, then anyone waiting for
    // a reply, then who is around to give one, then who is not.
    // A conversation with an unread message is the only row here
    // that costs the business something while it is ignored, so
    // it sits above the roster rather than inside it.
    $user = validate_user();

    if ($user['role'] < 3) {

        $eg_now = time();

        // 20 minutes, the same span the two counters use. The
        // roster is split on this boundary rather than on the
        // 120-second "online" threshold so the tile and the list
        // beneath it cannot disagree — a tile reading 3 above a
        // list showing 1 reads as a fault, not as two different
        // measurements.
        $eg_recent_seconds = 1200;

        $eg_active_visitors = (int) db_value(
            "SELECT COUNT(*) FROM visitors
             WHERE stop_timestamp >= '" . e($eg_now - $eg_recent_seconds) . "'");

        $eg_online_users_count = (int) db_value(
            "SELECT COUNT(*) FROM user
             WHERE user_online_timestamp >= '" . e($eg_now - $eg_recent_seconds) . "'");

        // ── Waiting conversations ───────────────────────────
        //
        // Same scope as the chat panel's own list
        // (pg_chat_conversation_list): mine as either side, plus
        // every site conversation for role 0 administrators.
        //
        // "Waiting" is status open AND a message arrived after my
        // read cursor. Which cursor applies depends on my side,
        // so the side test and the cursor test travel together in
        // each branch — comparing against the wrong cursor would
        // report my own last message as something I owe a reply
        // to.
        $eg_chat_rows = array();
        $eg_chat_available = false;

        require_once(PG_FUNCTIONS_DIR . '/chat.php');

        if (pg_chat_enabled()) {

            $eg_chat_available = true;
            $eg_me = (int) $user['id'];

            $eg_waiting_where =
                "((c.target_user_id = '" . e($eg_me) . "' AND c.last_message_id > c.target_last_read_id)
                  OR (c.initiator_user_id = '" . e($eg_me) . "' AND c.last_message_id > c.initiator_last_read_id)";

            if ((int) $user['role'] === 0) {
                $eg_waiting_where .=
                    " OR (c.channel = 'site' AND c.last_message_id > c.target_last_read_id)";
            }

            $eg_waiting_where .= ')';

            // Oldest first: the visitor who has been waiting
            // longest is the one about to give up. Newest-first
            // would bury exactly that row.
            $eg_chat_rows = db_items(
                "SELECT
                    c.id,
                    c.channel,
                    c.party_name,
                    c.last_message_at,
                    c.last_message_preview,
                    u.user_username AS peer_username,
                    contacts.first_name AS first_name,
                    contacts.last_name AS last_name
                 FROM chat_conversations c
                 LEFT JOIN user u
                    ON u.user_id = IF(c.initiator_user_id = '" . e($eg_me) . "', c.target_user_id, c.initiator_user_id)
                 LEFT JOIN contacts ON contacts.id = u.user_contact
                 WHERE c.status = 'open'
                   AND " . $eg_waiting_where . "
                 ORDER BY c.last_message_at ASC, c.id ASC
                 LIMIT 5");
        }

        $eg_waiting_html = '';

        foreach ($eg_chat_rows as $eg_chat) {

            if ($eg_chat['channel'] == 'site') {
                $eg_title = ($eg_chat['party_name'] != '')
                    ? $eg_chat['party_name']
                    : lang('Visitor') . ' #' . (int) $eg_chat['id'];
            } else {
                $eg_title = pg_chat_display_name(
                    isset($eg_chat['first_name']) ? $eg_chat['first_name'] : '',
                    isset($eg_chat['last_name']) ? $eg_chat['last_name'] : '',
                    isset($eg_chat['peer_username']) ? $eg_chat['peer_username'] : '');
            }

            $eg_waited = $eg_now - (int) $eg_chat['last_message_at'];

            // Past a quarter of an hour with no answer this is no
            // longer a notification, it is a lost conversation.
            $eg_wait_class = ($eg_waited >= 900) ? 'text-danger' : 'text-warning';

            $eg_waiting_html .= '
            <div class="d-flex align-items-center px-2 py-1 pointer"
                 onclick="if (window.pgChatOpenConversation) { window.pgChatOpenConversation(' . (int) $eg_chat['id'] . '); }">
                <div class="me-2 flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle"
                     style="width:34px;height:34px;background:rgba(245,158,11,.12)">
                    <i class="bi bi-chat-dots" style="color:#f59e0b"></i>
                </div>
                <div class="flex-grow-1 overflow-hidden">
                    <div class="d-flex justify-content-between align-items-center" style="gap:6px">
                        <span class="text-truncate fw-semibold" style="font-size:13px">' . h($eg_title) . '</span>
                        <span class="' . $eg_wait_class . ' flex-shrink-0" style="font-size:11px">' . get_relative_time(array('timestamp' => (int) $eg_chat['last_message_at'])) . '</span>
                    </div>
                    <small class="text-muted text-truncate d-block">' . h($eg_chat['last_message_preview']) . '</small>
                </div>
            </div>';
        }

        // ── Roster ──────────────────────────────────────────
        //
        // user_role >= own role: staff see their peers and the
        // ranks below, never above. Unchanged from the widget
        // this replaces.
        $eg_roster = db_items(
            "SELECT
                user.user_id AS id,
                user.user_role AS user_role,
                user.user_username AS username,
                user.user_online_timestamp AS user_online_timestamp,
                contacts.image AS image,
                contacts.file_id AS image_file_id,
                contacts.first_name AS first_name,
                contacts.last_name AS last_name,
                files.name AS image_file_name
             FROM user
             LEFT JOIN contacts ON contacts.id = user.user_contact
             LEFT JOIN files ON files.id = contacts.file_id
             WHERE user.user_role >= '" . e((int) $user['role']) . "'
             ORDER BY user.user_online_timestamp DESC
             LIMIT 30");

        $eg_online_html  = '';
        $eg_offline_html = '';
        $eg_online_shown = 0;
        $eg_offline_shown = 0;

        foreach ($eg_roster as $eg_person) {

            $eg_seen = (int) $eg_person['user_online_timestamp'];

            // Never signed in. There is no presence to report and
            // no last-seen to show, so the row would be three
            // blanks and an avatar.
            if ($eg_seen < 1) {
                continue;
            }

            $eg_presence = pg_chat_presence($eg_seen);
            $eg_is_recent = (($eg_now - $eg_seen) < $eg_recent_seconds);

            if ($eg_presence == 'online') {
                $eg_dot = '#10b981';
                $eg_status_label = lang('Online');
                // Already-escaped HTML in every branch, because
                // get_relative_time() emits a <time> element with
                // an absolute-date tooltip. Escaping the whole
                // thing at the print site would print the tag.
                $eg_seen_text = h(lang('Online'));
            } elseif ($eg_presence == 'away') {
                $eg_dot = '#f59e0b';
                $eg_status_label = lang('Away');
                $eg_seen_text = get_relative_time(array('timestamp' => $eg_seen));
            } else {
                $eg_dot = '#a1a1a1';
                $eg_status_label = lang('Offline');
                $eg_seen_text = get_relative_time(array('timestamp' => $eg_seen));
            }

            switch ((int) $eg_person['user_role']) {
                case 0:
                    $eg_role_color = '#ef4444';
                    break;
                case 1:
                    $eg_role_color = '#8b5cf6';
                    break;
                case 2:
                    $eg_role_color = '#3b82f6';
                    break;
                default:
                    $eg_role_color = '#6b7280';
                    break;
            }

            $eg_avatar = pg_chat_avatar_src(
                isset($eg_person['image']) ? $eg_person['image'] : '',
                isset($eg_person['image_file_id']) ? $eg_person['image_file_id'] : 0,
                isset($eg_person['image_file_name']) ? $eg_person['image_file_name'] : '',
                // Without the name, a person with no photo got the initials
                // avatar's "?" fallback instead of their own letters.
                isset($eg_person['first_name']) ? $eg_person['first_name'] : '',
                isset($eg_person['last_name']) ? $eg_person['last_name'] : '',
                $eg_person['username'],
                $eg_person['id']);

            $eg_name = pg_chat_display_name(
                isset($eg_person['first_name']) ? $eg_person['first_name'] : '',
                isset($eg_person['last_name']) ? $eg_person['last_name'] : '',
                $eg_person['username']);

            // Opening someone's account screen is an edit action:
            // offered to administrators, and otherwise only
            // downward through the hierarchy. Unchanged from the
            // widget this replaces.
            $eg_click = '';
            $eg_pointer = '';

            if (((int) $user['role'] === 0) || ((int) $user['role'] < (int) $eg_person['user_role'])) {
                $eg_click = 'onclick="window.location.href=\'edit_user.php?id=' . (int) $eg_person['id'] . '\'"';
                $eg_pointer = ' pointer';
            }

            // No chat handle against yourself, and none when the
            // pair is not allowed to talk (pg_chat_can_pair: at
            // least one side must be staff).
            $eg_chat_icon = '';

            if ($eg_chat_available
                && ((int) $eg_person['id'] !== (int) $user['id'])
                && pg_chat_can_pair($user['role'], $eg_person['user_role'])
            ) {
                $eg_chat_icon = '
                    <i class="bi bi-chat-text ms-1 flex-shrink-0" style="cursor:pointer"
                       title="' . lang('Start Chat') . '"
                       onclick="event.stopPropagation(); if (window.pgChatOpenWith) { window.pgChatOpenWith(' . (int) $eg_person['id'] . '); }"></i>';
            }

            $eg_row = '
            <div class="d-flex align-items-center px-2 py-1' . $eg_pointer . '" ' . $eg_click . '>
                <div class="position-relative me-2 flex-shrink-0">
                    <img src="' . h($eg_avatar) . '" class="rounded-circle" style="width:34px;height:34px;object-fit:cover;" alt="" />
                    <span class="position-absolute rounded-circle border border-2 border-white" title="' . h($eg_status_label) . '"
                          style="width:10px;height:10px;bottom:0;right:0;background:' . $eg_dot . '"></span>
                </div>
                <div class="flex-grow-1 overflow-hidden">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-truncate me-1 fw-semibold" style="font-size:13px">' . h($eg_name) . '</span>
                        <span class="badge rounded-pill flex-shrink-0" style="background:' . $eg_role_color . '22;color:' . $eg_role_color . ';font-size:10px">' . h(pg_chat_role_label($eg_person['user_role'])) . '</span>
                        ' . $eg_chat_icon . '
                    </div>
                    <small class="text-muted">' . $eg_seen_text . '</small>
                </div>
            </div>';

            if ($eg_is_recent) {
                $eg_online_html .= $eg_row;
                $eg_online_shown++;
            } else {
                $eg_offline_html .= $eg_row;
                $eg_offline_shown++;
            }
        }

        // Section heading. Printed only when the section has rows
        // — an empty "Waiting for a reply" heading would read as a
        // fault, and three empty headings would fill the card.
        $eg_heading = function ($eg_label, $eg_count) {
            return '
            <div class="d-flex align-items-center justify-content-between px-2 pt-2 pb-1">
                <span class="text-muted text-uppercase" style="font-size:10px;letter-spacing:.06em">' . h($eg_label) . '</span>
                <span class="text-muted" style="font-size:10px">' . pg_format_number($eg_count, 0) . '</span>
            </div>';
        };

        // Two panels rather than one column: the counts and the
        // conversations still owed an answer are what the operator
        // acts on, and who happens to be connected is context. They
        // were competing for the same scroll before. .pg-split lays
        // them side by side once the card is wide enough and stacks
        // them again when it is not, so a one-track card still works.
        $eg_chats_panel = '';

        if ($eg_waiting_html !== '') {
            $eg_chats_panel = $eg_heading(lang('Waiting for a reply'), count($eg_chat_rows)) . $eg_waiting_html;
        }

        if ($eg_chats_panel === '') {
            $eg_chats_panel = '
            <div class="text-center py-3">
                <i class="bi bi-chat-left-dots d-block mb-2" style="font-size:20px;opacity:.35"></i>
                <p class="text-muted mb-0" style="font-size:12px">' . lang('Nobody is waiting for a reply.') . '</p>
            </div>';
        }

        $eg_people_panel = '';

        if ($eg_online_html !== '') {
            $eg_people_panel .= $eg_heading(lang('Online'), $eg_online_shown) . $eg_online_html;
        }

        if ($eg_offline_html !== '') {
            $eg_people_panel .= $eg_heading(lang('Offline'), $eg_offline_shown) . $eg_offline_html;
        }

        if ($eg_people_panel === '') {
            $eg_people_panel = '
            <div class="text-center py-4">
                <i class="bi bi-person-dash d-block mb-2" style="font-size:22px;opacity:.35"></i>
                <p class="text-muted mb-0" style="font-size:12px">' . lang(array('string' => 'There is no {var:1} right now.', 'vars' => lang('Online User'))) . '</p>
            </div>';
        }

        $output_data = '
        <div class="card-body p-0 pg-split">
            <div class="pg-split-half">
                <div class="d-flex flex-column gap-1 p-2">
                    <div class="pg-eg-stat" style="--pg-eg-ink:#10b981">
                        <i class="bi bi-people-fill"></i>
                        <span class="pg-eg-stat-value">' . pg_format_number($eg_active_visitors, 0) . '</span>
                        <span class="pg-eg-stat-label">' . lang('Active Visitors') . '</span>
                        <span class="pg-eg-stat-window">' . lang('Last 20 min') . '</span>
                    </div>
                    <div class="pg-eg-stat" style="--pg-eg-ink:#3b82f6">
                        <i class="bi bi-person-gear"></i>
                        <span class="pg-eg-stat-value">' . pg_format_number($eg_online_users_count, 0) . '</span>
                        <span class="pg-eg-stat-label">' . lang('Online Users') . '</span>
                        <span class="pg-eg-stat-window">' . lang('Last 20 min') . '</span>
                    </div>
                </div>
                <div class="border-top mx-2 mb-1">
                    ' . $eg_chats_panel . '
                </div>
            </div>
            <div class="pg-split-half">
                ' . $eg_people_panel . '
            </div>
        </div>';

        $response = array(
            'status' => 'success',
            'message' => 'Action Success',
            'data' => $output_data,
        );
        return $response;
    } else {
        $response = array(
            'status' => 'error',
            'message' => 'Access denied'
        );
        return $response;
    }
}
