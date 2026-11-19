<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the caller's own meeting link in the external API and MCP:
 * what the screen "My meeting link" (workspace_meeting_link.php) shows a
 * booking staff member, read only. Making or removing the short address
 * and saving the meeting address stay on the screen.
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
 * The meeting link route, merged into ws_api_routes().
 *
 * @return array[]
 */
function ws_meeting_link_api_routes()
{
    return array(
        array(
            'id'          => 'workspace.meeting_link.get',
            'method'      => 'GET',
            'path'        => '/workspace/meeting-link',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_meeting_link_get',
            'returns'     => 'WorkspaceMeetingLink',
            'summary'     => 'The owner\'s own meeting link',
            'description' => 'What the screen My meeting link shows the owner when they are booking staff: bookable (they have services to be booked for), link_url (the address a visitor books them through; null when not bookable or the site has no encryption key), short_url (the live short address of the site that points at the link, null when there is none), meeting_url (the address of their online meeting room, empty for none) and services (their services, each offered on the link or not, reason deposit when it takes a deposit; price in minor units). Read only: the short address and the meeting address are changed on the screen. The person only: an application key gets 403 forbidden. An owner who is not booking staff gets 404 not_found.',
            'params'      => array(),
        ),
    );
}

function ws_api_meeting_link_get($params)
{
    ws_api_viewer();
    ws_api_person_only(lang('Only the person, signed in on a device, can read their meeting link; an application cannot read it for them.'));

    $app = api_current_app();
    $user_id = (int) (($app['owner'] ?? array())['id'] ?? 0);
    // The screen's own reading: null for anyone who is no booking staff.
    $member = pg_booking_link_screen_member($user_id);

    if ($member === null) {
        api_fail_not_found(lang('Meeting link'));
    }

    $bookable = pg_booking_link_bookable($member);
    $link_url = $bookable ? (string) pg_booking_link_url($member) : '';

    // Both paths the screen looks for; the screen, not a read, puts an old
    // row's path right.
    $key = pg_booking_key();
    $short = pg_booking_link_short_link(array(
        pg_booking_link_short_target($member, $key, PATH, SOFTWARE_DIRECTORY),
        pg_booking_link_short_target($member, $key, PATH, ''),
    ));

    list($all_services) = pg_booking_widget_data();

    api_ok(ws_api_meeting_link_present(
        $bookable,
        $link_url,
        $short ? (string) $short['name'] : '',
        URL_SCHEME . HOSTNAME_SETTING . PATH,
        pg_booking_link_meeting_url($user_id),
        pg_booking_link_screen_services($all_services, $member)
    ));
}

/**
 * The meeting link as the API gives it: a short address only beside a
 * link, as the screen shows it. Pure.
 *
 * @param bool   $bookable
 * @param string $link_url    pg_booking_link_url(), '' for none
 * @param string $short_name  the short link's name, '' for none
 * @param string $site_base   the site's address up to its path
 * @param string $meeting_url
 * @param array  $services    pg_booking_link_screen_services()
 * @return array
 */
function ws_api_meeting_link_present($bookable, $link_url, $short_name, $site_base, $meeting_url, $services)
{
    $link_url = $bookable ? (string) $link_url : '';
    $rows = array();

    foreach ((array) $services as $service) {
        $offered = !empty($service['offered']);

        $rows[] = array(
            'id'             => (int) $service['id'],
            'name'           => (string) $service['name'],
            'length_minutes' => (int) $service['length_minutes'],
            'price'          => (int) round((float) ($service['price'] ?? 0)),
            'offered'        => $offered,
            'reason'         => $offered ? '' : 'deposit',
        );
    }

    return array(
        'bookable'    => (bool) $bookable,
        'link_url'    => ($link_url !== '') ? $link_url : null,
        'short_url'   => (($link_url !== '') && ((string) $short_name !== '')) ? (string) $site_base . encode_url_path((string) $short_name) : null,
        'meeting_url' => (string) $meeting_url,
        'services'    => $rows,
    );
}

function ws_api_meeting_link_schema()
{
    return array(
        'bookable'    => 'boolean',
        'link_url'    => 'string?',
        'short_url'   => 'string?',
        'meeting_url' => 'string',
        'services'    => 'WorkspaceMeetingLinkService[]',
    );
}

function ws_api_meeting_link_service_schema()
{
    return array(
        'id'             => 'integer',
        'name'           => 'string',
        'length_minutes' => 'integer',
        'price'          => 'integer',
        'offered'        => 'boolean',
        'reason'         => 'string',
    );
}
