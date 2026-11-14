<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Communication area of the panel (main menu row 9 under Marketing):
 * its tabs (Incoming e-mail and the five of the messages), who may open each, the tab strip every tab screen prints
 * under its heading, the menu row's panel and the rows that lead from a tab
 * to the screen where its job is done today.
 *
 * Each tab is a screen of its own file with its own gate: Campaigns is
 * view_email_campaigns.php, the others are messages_*.php. A tab its viewer
 * may not open is left out of the strip and the menu panel, so neither leads
 * to "Access denied".
 *
 * Loaded where it is needed (require_once), not by functions.php.
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
 * The tabs in their order. 'gate' names the rule the tab's screen asks:
 * 'mail' is ws_mail_list_visible() (a mailbox writes where the person reads,
 * whatever their campaign rights), 'email' is validate_email_access() (role
 * <= 2 or the Campaign Manager right), 'manager' is role <= 2. Labels are
 * English; the strip translates.
 *
 * @return array key => array(file, label, icon, gate)
 */
function pg_messages_area_tabs()
{
    return array(
        'mail'      => array('file' => 'messages_mail.php', 'label' => 'Incoming e-mail', 'icon' => 'bi-envelope', 'gate' => 'mail'),
        'campaigns' => array('file' => 'view_email_campaigns.php', 'label' => 'Campaigns', 'icon' => 'bi-megaphone', 'gate' => 'email'),
        'automatic' => array('file' => 'messages_automatic.php', 'label' => 'Automatic sends', 'icon' => 'bi-diagram-3', 'gate' => 'email'),
        'templates' => array('file' => 'messages_templates.php', 'label' => 'Templates', 'icon' => 'bi-card-text', 'gate' => 'email'),
        'sends'     => array('file' => 'messages_sends.php', 'label' => 'Sends', 'icon' => 'bi-send', 'gate' => 'email'),
        'channels'  => array('file' => 'messages_channels.php', 'label' => 'Channels', 'icon' => 'bi-broadcast', 'gate' => 'manager'),
    );
}

/**
 * Whether a person passes a tab's gate.
 *
 * @param string $gate          'mail', 'email' or 'manager'
 * @param int    $role          0 administrator to 3 user
 * @param bool   $manage_emails the Campaign Manager right
 * @param bool   $mail          the person reads a mailbox (ws_mail_list_visible())
 * @return bool
 */
function pg_messages_gate_allows($gate, $role, $manage_emails, $mail = false)
{
    $role = (int) $role;

    if ($gate === 'mail') {
        return (bool) $mail;
    }

    if ($gate === 'manager') {
        return $role <= 2;
    }

    if ($gate === 'email') {
        return ($role <= 2) || (bool) $manage_emails;
    }

    return false;
}

/**
 * The keys of the tabs a person may open, in the strip's order.
 *
 * @param int  $role
 * @param bool $manage_emails
 * @param bool $mail          the person reads a mailbox
 * @return array
 */
function pg_messages_tabs_for($role, $manage_emails, $mail = false)
{
    $keys = array();

    foreach (pg_messages_area_tabs() as $key => $tab) {
        if (pg_messages_gate_allows($tab['gate'], $role, $manage_emails, $mail)) {
            $keys[] = $key;
        }
    }

    return $keys;
}

/**
 * The same for a signed-in user row (validate_user()).
 *
 * @param array     $user
 * @param bool|null $mail whether they read a mailbox; null asks
 *                        pg_messages_mail_visible()
 * @return array
 */
function pg_messages_tabs_for_user($user, $mail = null)
{
    $role = isset($user['role']) ? (int) $user['role'] : 3;
    $manage_emails = !empty($user['manage_emails']);

    if ($mail === null) {
        $mail = pg_messages_mail_visible($user);
    }

    return pg_messages_tabs_for($role, $manage_emails, $mail);
}

/**
 * Whether the signed-in person may open Incoming e-mail: the Workspace is on
 * and installed, they are on its team and a mailbox writes where they read
 * (ws_mail_list_visible(), the rule the Workspace's E-mails entry asked).
 * Asked once a request; the Workspace's files load only when it is on.
 *
 * @param array $user validate_user()
 * @return bool
 */
function pg_messages_mail_visible($user)
{
    static $seen = array();

    $id = isset($user['id']) ? (int) $user['id'] : 0;

    if ($id <= 0 || !defined('WORKSPACE_ENABLED') || !WORKSPACE_ENABLED) {
        return false;
    }

    if (!isset($seen[$id])) {
        require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

        $seen[$id] = ws_ready() && ws_enabled() && function_exists('ws_mail_list_visible')
            && ws_mail_list_visible(ws_viewer($user));
    }

    return $seen[$id];
}

/**
 * The open e-mail conversations unread for the signed-in person: the number
 * on the Communication row of the menu (it was the Workspace's E-mails
 * tile's). 0 for anybody who may not open Incoming e-mail.
 *
 * @param array $user validate_user()
 * @return int
 */
function pg_messages_mail_unread($user)
{
    if (!pg_messages_mail_visible($user) || !function_exists('ws_mail_unread_count')) {
        return 0;
    }

    return ws_mail_unread_count(ws_viewer($user));
}

/**
 * Where the Workspace's old E-mails address (workspace_mail.php) leads:
 * Incoming e-mail with the same words (conversation, view, filters, page).
 * Only plain words are carried; a list in the address is dropped. Pure.
 *
 * @param array $query the old address's words ($_GET)
 * @return string relative to the panel
 */
function pg_messages_mail_door($query)
{
    $keep = array();

    foreach ((array) $query as $key => $value) {
        if (is_string($key) && is_scalar($value) && ((string) $value !== '')) {
            $keep[$key] = (string) $value;
        }
    }

    return 'messages_mail.php' . ($keep ? '?' . http_build_query($keep, '', '&', PHP_QUERY_RFC3986) : '');
}

/**
 * The tab strip under a tab screen's heading, drawn as the segmented control
 * the flow window has for Canvas and List: links between screens, so the
 * open one carries aria-current="page" (not role=tab: each tab is its own
 * address). It stays one line at a phone's width: there only the open tab
 * shows its label, the others their icon (the label kept for screen readers
 * and as the title). Empty when the viewer may open fewer than two tabs.
 *
 * @param string $active a key of pg_messages_area_tabs(), or '' for none
 * @param array  $keys   the viewer's tabs (pg_messages_tabs_for_user())
 * @param string $prefix the address before a file name
 * @return string
 */
function pg_messages_tab_strip($active, $keys, $prefix = '')
{
    $tabs = pg_messages_area_tabs();
    $items = '';
    $count = 0;

    foreach ($keys as $key) {
        if (!isset($tabs[$key])) {
            continue;
        }

        $tab = $tabs[$key];
        $label = h(lang($tab['label']));
        $current = ($key === $active);
        $count++;

        $items .= '<a class="pg-seg-item' . ($current ? ' is-active' : '') . '"' . ($current ? ' aria-current="page"' : '')
            . ' href="' . h($prefix . $tab['file']) . '" title="' . $label . '">'
            . '<i class="bi ' . h($tab['icon']) . '" aria-hidden="true"></i><span class="pg-messages-tab-label">' . $label . '</span></a>';
    }

    if ($count < 2) {
        return '';
    }

    return '<nav class="pg-messages-tabs" aria-label="' . h(lang('Communication')) . '"><div class="pg-seg">' . $items . '</div></nav>';
}

/**
 * A filter of an area list as a segmented control of links (look A): each
 * choice an address keeping the other filters, the chosen one raised. A
 * choice equal to $default leaves its parameter out of the address.
 *
 * @param string $name    the query parameter
 * @param array  $options value => label (plain text)
 * @param string $current the chosen value
 * @param string $default the value the screen takes without the parameter
 * @param string $file    the screen's address
 * @param array  $keep    the other filters, name => value ('' left out)
 * @param string $label   the group's accessible name
 * @return string
 */
function pg_messages_seg_filter($name, $options, $current, $default, $file, $keep, $label)
{
    $items = '';

    foreach ($options as $value => $text) {
        $value = (string) $value;
        $params = $keep;
        $params[$name] = ($value === (string) $default) ? '' : $value;
        $query = http_build_query(array_filter($params, 'strlen'));
        $chosen = ($value === (string) $current);

        $items .= '<a class="pg-seg-item' . ($chosen ? ' is-active" aria-current="true"' : '"')
            . ' href="' . h($file . (($query !== '') ? '?' . $query : '')) . '">' . h((string) $text) . '</a>';
    }

    return '<div class="pg-seg" role="group" aria-label="' . h($label) . '">' . $items . '</div>';
}

/**
 * A notice of an area screen as the drawing has it: an inner card row with
 * a tone tile, the sentence, and the way to fix it as a quiet button at the
 * right. Under the tab strip, never above it.
 *
 * @param string $text   the sentence (plain text)
 * @param string $button the button's label ('' for none)
 * @param string $url    its address
 * @param string $tone   a .pg-tone-* name
 * @param string $role   'status' or 'alert'
 * @return string
 */
function pg_messages_note($text, $button = '', $url = '', $tone = 'amber', $role = 'status')
{
    return '<div class="pg-card-inner pg-messages-note" role="' . h($role) . '"><div class="pg-card-inner-row">'
        . '<span class="pg-icon-tile pg-tone-' . h($tone) . '" aria-hidden="true"><span class="bi bi-exclamation-triangle"></span></span>'
        . '<div class="pg-text-2 flex-grow-1 min-w-0">' . h($text) . '</div>'
        . ((($button !== '') && ($url !== '')) ? '<a class="pg-button pg-button-quiet pg-button-sm" href="' . h($url) . '">' . h($button) . '</a>' : '')
        . '</div></div>';
}

/**
 * A campaign's progress as the drawing has it: a slim bar of the done share
 * with its figure beside it, a note under them. The bar is a progressbar for
 * screen readers. Nothing done of nothing draws an empty bar.
 *
 * @param int    $done
 * @param int    $total
 * @param string $figure    the figure beside the bar (plain text)
 * @param string $note_html the line under it, markup ('' for none)
 * @return string
 */
function pg_messages_progress_html($done, $total, $figure, $note_html = '')
{
    $done = max(0, (int) $done);
    $total = max(0, (int) $total);
    $share = ($total > 0) ? min(100, round($done / $total * 100, 1)) : 0;
    $full = ($total > 0) && ($done >= $total);

    return '<div class="pg-messages-progress">'
        . '<div class="pg-messages-progress-line">'
        . '<span class="pg-messages-progress-track' . ($full ? ' is-full' : '') . '" role="progressbar" aria-valuemin="0" aria-valuemax="' . $total . '" aria-valuenow="' . min($done, $total) . '" aria-label="' . h((string) $figure) . '">'
        . '<span class="pg-messages-progress-fill" style="width: ' . $share . '%"></span></span>'
        . '<span class="pg-messages-progress-figure">' . h((string) $figure) . '</span>'
        . '</div>'
        . (((string) $note_html !== '') ? '<div class="pg-messages-progress-note">' . $note_html . '</div>' : '')
        . '</div>';
}

/**
 * The strip for the signed-in user on a panel screen.
 *
 * @param string $active
 * @param array  $user
 * @return string
 */
function pg_messages_tab_strip_for($active, $user)
{
    return pg_messages_tab_strip($active, pg_messages_tabs_for_user($user), OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/');
}

/**
 * The entries of menu row 9's panel: the tabs the person may open, then
 * "New campaign". Markup in the menu's single-quoted attribute style.
 *
 * @param array  $keys   the viewer's tabs
 * @param string $prefix the address before a file name
 * @param string $target the menu's target attribute (may be '')
 * @return string
 */
function pg_messages_menu_entries($keys, $prefix, $target = '')
{
    $tabs = pg_messages_area_tabs();
    $class = 'btn btn-link pg-link-body-emphasis text-start text-decoration-none text-truncate bi bi-me-2 ';
    $html = '';

    foreach ($keys as $key) {
        if (!isset($tabs[$key])) {
            continue;
        }

        $html .= '<a href=\'' . h($prefix . $tabs[$key]['file']) . '\'' . $target . ' class=\'' . $class . h($tabs[$key]['icon']) . '\'>' . h(lang($tabs[$key]['label'])) . '</a>';
    }

    if (in_array('campaigns', $keys, true)) {
        $html .= '<hr class=\'divider my-3\' />';
        $html .= '<a href=\'' . h($prefix . 'add_email_campaign.php') . '\'' . $target . ' class=\'' . $class . 'bi-plus-lg\'>' . h(lang('New campaign')) . '</a>';
    }

    return $html;
}

/**
 * Rows that lead from a tab to the screen where a job is done today, drawn
 * as look A list rows inside a card. A row without a title or an address is
 * left out.
 *
 * Each row: title, text, url, icon (bi-…), tone (a .pg-tone-* name).
 *
 * @param string $heading the card's section title
 * @param array  $rows
 * @return string '' when no row is left
 */
function pg_messages_place_rows($heading, $rows)
{
    $html = '';

    foreach ($rows as $row) {
        if (!is_array($row) || empty($row['title']) || empty($row['url'])) {
            continue;
        }

        $icon = !empty($row['icon']) ? (string) $row['icon'] : 'bi-box-arrow-in-right';
        $tone = !empty($row['tone']) ? (string) $row['tone'] : 'gray';
        $text = isset($row['text']) ? (string) $row['text'] : '';

        $html .= '<div class="pg-list-row">'
            . '<span class="pg-icon-tile pg-tone-' . h($tone) . '" aria-hidden="true"><span class="bi ' . h($icon) . '"></span></span>'
            . '<div class="pg-list-row-main">'
            . '<div class="pg-list-row-head"><a class="pg-list-row-title" href="' . h((string) $row['url']) . '">' . h((string) $row['title']) . '</a></div>'
            . ($text !== '' ? '<div class="pg-list-row-text">' . h($text) . '</div>' : '')
            . '</div></div>';
    }

    if ($html === '') {
        return '';
    }

    return '<section class="pg-card mb-5"><h2 class="pg-title-section">' . h($heading) . '</h2>' . $html . '</section>';
}

/**
 * A whole tab screen: the page shell headed "Communication", the tab strip
 * with $key open, the body, the footer. The caller has passed the tab's gate.
 *
 * @param string $key         a key of pg_messages_area_tabs()
 * @param array  $user        the signed-in user
 * @param string $description the line under the heading
 * @param string $body        markup under the strip
 * @return string
 */
function pg_messages_tab_page($key, $user, $description, $body)
{
    $tabs = pg_messages_area_tabs();
    $label = isset($tabs[$key]) ? lang($tabs[$key]['label']) : lang('Communication');

    return pg_page_shell(array(
            'title' => $label,
            'extra classes' => 'campaign messages-area',
            'icon' => 'campaign',
            'heading' => lang('Communication'),
            'heading_description' => $description,
        ))
        . '<main id="content" class="container-fluid"><div class="row"><div class="col-12">'
        . pg_messages_tab_strip_for($key, $user)
        . $body
        . '</div></div></main>'
        . output_footer();
}

/**
 * The worst of the sending domain's record states (SPF, DKIM, DMARC):
 * error over warning over unknown over ok. No state reads as unknown.
 *
 * @param array $states 'ok', 'warning', 'error' or 'unknown' each
 * @return string
 */
function pg_messages_health_worst($states)
{
    $rank = array('ok' => 0, 'unknown' => 1, 'warning' => 2, 'error' => 3);
    $worst = '';

    foreach ((array) $states as $state) {
        $state = isset($rank[$state]) ? $state : 'unknown';

        if (($worst === '') || ($rank[$state] > $rank[$worst])) {
            $worst = $state;
        }
    }

    return ($worst === '') ? 'unknown' : $worst;
}

/**
 * The Channels tab's four cards from the facts read now
 * (pg_messages_channel_facts()). Each card: key, title, icon, tone, state
 * (on, check, off), state label, line, url. Pure, so tested without a site.
 *
 * Facts:
 *   email     from, via (transport or server), domain, health (ok, warning,
 *             error, unknown)
 *   sms       provider (label, '' when none is complete), sender, started
 *             (an account is switched on but incomplete)
 *   whatsapp  ready, number, name, started
 *   iys       ready, brand, test, started
 *
 * @param array $facts
 * @param array $urls  key => settings address
 * @return array
 */
function pg_messages_channel_cards($facts, $urls)
{
    $get = function ($channel, $key, $default = '') use ($facts) {
        return (isset($facts[$channel]) && is_array($facts[$channel]) && isset($facts[$channel][$key])) ? $facts[$channel][$key] : $default;
    };
    $url = function ($key) use ($urls) {
        return isset($urls[$key]) ? (string) $urls[$key] : '';
    };
    $join = function ($parts) {
        return implode(' · ', array_values(array_filter(array_map('strval', $parts), 'strlen')));
    };

    // E-mail always sends; its state is the sending domain's health.
    $health = (string) $get('email', 'health', 'unknown');
    $health_states = array(
        'ok'      => array('on', lang('Good')),
        'warning' => array('check', lang('Check')),
        'error'   => array('check', lang('Missing or wrong')),
        'unknown' => array('off', lang('Could not check')),
    );
    $email_state = isset($health_states[$health]) ? $health_states[$health] : $health_states['unknown'];
    $domain = (string) $get('email', 'domain');
    $from = (string) $get('email', 'from');
    $cards = array();

    $cards[] = array(
        'key' => 'email', 'title' => lang('E-mail'), 'icon' => 'bi-envelope', 'tone' => 'blue',
        'state' => $email_state[0], 'state_label' => $email_state[1],
        'line' => $join(array(
            ($from !== '') ? lang(array('string' => 'Sender: {var:1}', 'vars' => array($from))) : lang('No sender address'),
            (string) $get('email', 'via'),
            ($domain !== '') ? lang(array('string' => 'Sending domain {var:1}', 'vars' => array($domain))) : '',
        )),
        'url' => $url('email'),
    );

    $provider = (string) $get('sms', 'provider');
    $sender = (string) $get('sms', 'sender');
    $cards[] = pg_messages_channel_card('sms', lang('SMS'), 'bi-chat-dots', 'amber',
        ($provider !== ''), (bool) $get('sms', 'started', false),
        ($provider !== '') ? $join(array($provider, ($sender !== '') ? lang(array('string' => 'Sender: {var:1}', 'vars' => array($sender))) : '')) : lang('No provider chosen'),
        $url('sms'));

    $cards[] = pg_messages_channel_card('whatsapp', lang('WhatsApp'), 'bi-whatsapp', 'teal',
        (bool) $get('whatsapp', 'ready', false), (bool) $get('whatsapp', 'started', false),
        $get('whatsapp', 'ready', false) ? $join(array((string) $get('whatsapp', 'number'), (string) $get('whatsapp', 'name'))) : lang('No business number connected'),
        $url('whatsapp'));

    $brand = (string) $get('iys', 'brand');
    $cards[] = pg_messages_channel_card('iys', lang('İYS'), 'bi-shield-check', 'violet',
        (bool) $get('iys', 'ready', false), (bool) $get('iys', 'started', false),
        $get('iys', 'ready', false) ? $join(array(($brand !== '') ? lang(array('string' => 'Brand code {var:1}', 'vars' => array($brand))) : '', $get('iys', 'test', false) ? lang('Test environment') : '')) : lang('Consent is not kept in step with İYS'),
        $url('iys'));

    return $cards;
}

/**
 * One switched channel's card: Connected, Incomplete (switched on, a field
 * missing) or Off.
 *
 * @return array
 */
function pg_messages_channel_card($key, $title, $icon, $tone, $ready, $started, $line, $url)
{
    if ($ready) {
        $state = array('on', lang('Connected'));
    } elseif ($started) {
        $state = array('check', lang('Incomplete'));
    } else {
        $state = array('off', lang('Off'));
    }

    return array('key' => $key, 'title' => $title, 'icon' => $icon, 'tone' => $tone, 'state' => $state[0], 'state_label' => $state[1], 'line' => $line, 'url' => $url);
}

/**
 * The cards as markup: a padded section card holding a grid of inner cards,
 * two to a row from md up and all as high as the highest, each card a link
 * to its settings with its state as a pill.
 *
 * @param array $cards pg_messages_channel_cards()
 * @return string
 */
function pg_messages_channel_cards_html($cards)
{
    $pill_tones = array('on' => 'teal', 'check' => 'amber', 'off' => 'gray');
    $html = '';

    foreach ($cards as $card) {
        $tone = isset($pill_tones[$card['state']]) ? $pill_tones[$card['state']] : 'gray';

        $html .= '<a class="pg-card-inner pg-messages-channel" href="' . h($card['url']) . '"><div class="pg-card-inner-row">'
            . '<span class="pg-icon-tile pg-icon-tile-lg pg-tone-' . h($card['tone']) . '" aria-hidden="true"><span class="bi ' . h($card['icon']) . '"></span></span>'
            . '<div class="flex-grow-1 min-w-0">'
            . '<div class="pg-messages-channel-head"><span class="pg-list-row-title">' . h($card['title']) . '</span>'
            . '<span class="pg-pill pg-tone-' . $tone . '">' . h($card['state_label']) . '</span></div>'
            . '<div class="pg-text-meta text-break">' . h($card['line']) . '</div>'
            . '</div>'
            . '</div></a>';
    }

    return '<section class="pg-card pg-messages-card"><div class="pg-messages-card-head"><div class="pg-messages-card-heading"><h2 class="pg-title-section">' . h(lang('Channels')) . '</h2></div></div>'
        . '<div class="pg-messages-channels">' . $html . '</div></section>';
}

/**
 * What the Channels tab shows, read now. The sending domain's records are
 * looked up in DNS as SMTP Settings does on each visit.
 *
 * @return array pg_messages_channel_cards()'s facts
 */
function pg_messages_channel_facts()
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_domain_health.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/iys/client.php');

    $health = pg_mail_domain_health();
    $transports = pg_mail_transports();
    $transport = pg_mail_transport();

    if (isset($transports[$transport])) {
        $via = $transports[$transport]['label'];
    } elseif (defined('SYSTEM_SMTP_HOSTNAME') && (string) SYSTEM_SMTP_HOSTNAME !== '') {
        $via = (string) SYSTEM_SMTP_HOSTNAME;
    } else {
        $via = lang('the server\'s own mail');
    }

    $facts = array(
        'email' => array(
            'from'   => defined('EMAIL_ADDRESS') ? (string) EMAIL_ADDRESS : '',
            'via'    => $via,
            'domain' => (string) $health['domain'],
            'health' => pg_messages_health_worst(array($health['spf']['state'], $health['dkim']['state'], $health['dmarc']['state'])),
        ),
    );

    $provider = pg_sms_provider();
    $providers = pg_sms_providers();
    $started = false;

    foreach (array_keys($providers) as $key) {
        $app = pg_sms_app($key);
        $started = $started || $app['enabled'];
    }

    $facts['sms'] = array(
        'provider' => ($provider !== '') ? $providers[$provider]['label'] : '',
        'sender'   => ($provider !== '') ? pg_sms_app($provider)['sender'] : '',
        'started'  => $started,
    );

    $whatsapp = pg_whatsapp_app();
    $facts['whatsapp'] = array(
        'ready'   => pg_whatsapp_ready(),
        'number'  => $whatsapp['display_phone_number'],
        'name'    => $whatsapp['verified_name'],
        'started' => $whatsapp['enabled'],
    );

    $iys = pg_iys_app();
    $facts['iys'] = array(
        'ready'   => pg_iys_app_complete($iys),
        'brand'   => is_array($iys) ? $iys['brand_code'] : '',
        'test'    => is_array($iys) && ($iys['env'] === 'test'),
        'started' => is_array($iys) && $iys['enabled'],
    );

    return $facts;
}
