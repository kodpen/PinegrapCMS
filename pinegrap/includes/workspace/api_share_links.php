<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - share links in the external API and MCP
 * (includes/workspace/share_links.php): make a link to one item of a message
 * of an agent channel (POST /workspace/messages/{id}/share-links), list a
 * channel's links (GET /workspace/channels/{id}/share-links, never the
 * address) and take one back (POST /workspace/share-links/{id}/revoke). Each
 * runs the panel's own check, so a dry run meets the same refusals. An
 * application key acts as the application, which the channel must allow as
 * an agent; a team devices session or an OAuth connection as the person.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL') && !defined('PG_INIT_LOADED')) {
    exit;
}

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The share link routes, merged into ws_api_routes().
 *
 * @return array[]
 */
function ws_share_link_api_routes()
{
    $id = array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true);
    $who = ' An application key acts as the application, which needs an allowed agent row in the channel; a team devices session or an OAuth connection acts as the person.';

    return array(
        array(
            'id'          => 'workspace.share_links.create',
            'method'      => 'POST',
            'path'        => '/workspace/messages/{id}/share-links',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_share_links_create',
            'dry_run'     => true,
            // The address holds the token, kept only as its hash: a retry
            // with the same Idempotency-Key answers url null, and a lost
            // address is replaced by a new link.
            'idempotency_redact' => array('url'),
            'returns'     => 'WorkspaceShareLinkMade',
            'status'      => 201,
            'summary'     => 'Share an item of an agent channel by link',
            'description' => 'Makes a read-only link to one item of a message in an agent channel: kind file, item_id the message\'s file.id. Anyone with the address reads the item without signing in until the link ends (days 1, 7 or 30; 7 unless given) or is taken back, or the message is deleted or the channel archived. The address is given here once: only its hash is kept. One open link an item, 200 a channel. Needs no proposal.',
            'details'     => 'A discussion of an agent channel counts as the channel.' . $who . ' Refusals: a message of another channel, a kind not offered, an item not in the message, an item with an open link (the message names its end), days not 1, 7 or 30, or a channel at its limit 422 validation_failed; a channel the owner may not post in, or an application the channel does not allow as an agent, 403 forbidden; a message not found or not readable 404; the database not updated yet 503 service_unavailable.',
            'params'      => array(
                $id,
                array('name' => 'kind', 'in' => 'body', 'type' => 'enum', 'values' => ws_share_link_kinds(), 'required' => true, 'description' => 'The kind of item: file (a picture or any file the message carries).'),
                array('name' => 'item_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'required' => true, 'description' => 'The item\'s id: for a file, file.id of the message.'),
                array('name' => 'days', 'in' => 'body', 'type' => 'int', 'min' => 1, 'max' => 30, 'description' => 'How long the link lasts: 1, 7 or 30 days; 7 when left out.'),
            ),
        ),

        array(
            'id'          => 'workspace.share_links.list',
            'method'      => 'GET',
            'path'        => '/workspace/channels/{id}/share-links',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_share_links_list',
            'returns'     => array('list' => 'WorkspaceShareLink'),
            'summary'     => 'List the share links of an agent channel',
            'description' => 'The links to items of a channel the owner of the application may read, newest first: the open ones, or with all true every link still kept (an ended or taken-back one is kept 30 days). Each with its item, who made it, its end, its state and how often it was opened; never the address, which is given only when a link is made.',
            'params'      => array(
                $id,
                array('name' => 'all', 'in' => 'query', 'type' => 'bool', 'description' => 'The ended and taken-back links too.'),
            ),
        ),

        array(
            'id'          => 'workspace.share_links.revoke',
            'method'      => 'POST',
            'path'        => '/workspace/share-links/{id}/revoke',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_share_links_revoke',
            'dry_run'     => true,
            'returns'     => 'WorkspaceShareLink',
            'summary'     => 'Take a share link back',
            'description' => 'Takes back a link of a channel the owner may read: the address stops working at once. Its maker may, and a person who manages the channel; an application takes back only the links it made. A link already taken back answers as it is.',
            'details'     => 'Refusals: somebody else\'s link 403 forbidden; a link not found or of a channel the owner may not read 404; the database not updated yet 503 service_unavailable.',
            'params'      => array($id),
        ),
    );
}

// What POST /workspace/messages/{id}/share-links answers.
function ws_api_share_link_made_schema()
{
    return array('link' => 'WorkspaceShareLink', 'url' => 'string?');
}

// One link as the API shows it (ws_api_share_link_present()).
function ws_api_share_link_schema()
{
    return array(
        'id'          => 'integer',
        'channel_id'  => 'integer',
        'message_id'  => 'integer',
        'kind'        => 'string',
        'item_id'     => 'integer',
        'name'        => 'string',
        'created_by'  => array('kind' => 'string', 'id' => 'integer'),
        'created_at'  => 'datetime',
        'expires_at'  => 'datetime',
        'revoked_at'  => 'datetime?',
        'state'       => 'string',
        'opened_at'   => 'datetime?',
        'opens'       => 'integer',
    );
}

/**
 * One link as the API shows it, without the address. Pure but for
 * api_time().
 *
 * @param array  $link    ws_share_links row
 * @param string $state   ws_share_link_state()
 * @param string $name    the item's name
 * @return array
 */
function ws_api_share_link_present($link, $state, $name)
{
    return array(
        'id'          => (int) $link['id'],
        'channel_id'  => (int) $link['channel_id'],
        'message_id'  => (int) $link['message_id'],
        'kind'        => (string) $link['kind'],
        'item_id'     => (int) $link['item_id'],
        'name'        => (string) $name,
        'created_by'  => array('kind' => (string) $link['created_by_kind'], 'id' => (int) $link['created_by_id']),
        'created_at'  => api_time($link['created_at']),
        'expires_at'  => api_time($link['expires_at']),
        'revoked_at'  => api_time($link['revoked_at']),
        'state'       => (string) $state,
        'opened_at'   => api_time($link['opened_at']),
        'opens'       => (int) $link['opens'],
    );
}

/**
 * A link with its state read now, as the API shows it.
 *
 * @param array $link
 * @return array
 */
function ws_api_share_link_read($link)
{
    $read = ws_share_link_read($link);

    return ws_api_share_link_present($link, $read['state'], is_array($read['message']) ? (string) $read['message']['file_name'] : '');
}

/**
 * Who this call acts as, for a link: the application of a key, the person
 * otherwise (ws_api_message_writer()).
 *
 * @param array $viewer
 * @return array ws_share_link_maker()
 */
function ws_api_share_link_maker($viewer)
{
    $writer = ws_api_message_writer();

    return ws_share_link_maker((int) $viewer['id'], (int) $writer['app_id']);
}

function ws_api_share_links_ready_or_503()
{
    if (!ws_share_links_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }
}

/**
 * Makes a link to an item of a message (ws_share_link_make()).
 */
function ws_api_share_links_create($params)
{
    ws_api_share_links_ready_or_503();

    $viewer = ws_api_viewer();
    $message = ws_api_message_or_404($viewer, $params['id']);
    $maker = ws_api_share_link_maker($viewer);
    $kind = (string) $params['kind'];
    $item_id = (int) $params['item_id'];

    $checked = ws_share_link_make_check($viewer, $message, $kind, $item_id, $params['days'] ?? null, $maker);

    if (!$checked['ok']) {
        if ((int) $checked['status'] === 403) {
            api_fail(403, 'forbidden', $checked['error']);
        }

        if ((int) $checked['status'] === 503) {
            api_fail(503, 'service_unavailable', $checked['error']);
        }

        api_fail_validation($checked['error'], ($checked['field'] !== '') ? $checked['field'] : null);
    }

    api_dry_run_stop('created', 'workspace_share_link', array(
        'channel_id' => (int) $checked['channel']['id'],
        'message_id' => (int) $message['id'],
        'kind'       => $kind,
        'item_id'    => $item_id,
        'expires_at' => api_time($checked['expires_at']),
    ));

    $made = ws_share_link_make($message, $checked, $kind, $item_id, $maker);

    if (!$made['ok']) {
        api_fail_server($made['error']);
    }

    api_ok(array(
        'link' => ws_api_share_link_present($made['link'], 'open', (string) $checked['name']),
        'url'  => $made['url'],
    ), 201);
}

/**
 * The links of a channel, without their addresses.
 */
function ws_api_share_links_list($params)
{
    ws_api_share_links_ready_or_503();

    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);
    $out = array();

    foreach (ws_share_links_of_channel((int) $channel['id'], empty($params['all'])) as $link) {
        $out[] = ws_api_share_link_read($link);
    }

    api_ok_list($out, count($out));
}

/**
 * Takes a link back (ws_share_link_revoke()).
 */
function ws_api_share_links_revoke($params)
{
    ws_api_share_links_ready_or_503();

    $viewer = ws_api_viewer();
    $link = ws_share_link((int) $params['id']);
    $channel = $link ? ws_channel((int) $link['channel_id']) : null;

    if (!$link || !is_array($channel) || !ws_can_read_channel($viewer, $channel)) {
        api_fail_not_found(lang('Link'));
    }

    // The limits of the application Claude works through.
    ws_api_channel_or_404($viewer, $channel['id']);

    if (!ws_share_link_can_revoke($viewer, $link, $channel, ws_api_share_link_maker($viewer))) {
        api_fail(403, 'forbidden', lang('Only who made the link or manages the channel can take it back.'));
    }

    if ((int) $link['revoked_at'] > 0) {
        api_ok(ws_api_share_link_read($link));
    }

    api_dry_run_stop('updated', 'workspace_share_link', array('id' => (int) $link['id'], 'state' => 'revoked'));

    ws_share_link_revoke($link, null, ws_api_share_link_maker($viewer));

    api_ok(ws_api_share_link_read(ws_share_link((int) $link['id'])));
}
