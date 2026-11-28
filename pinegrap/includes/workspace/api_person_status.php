<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a person's own status in the external API and MCP (people.php,
 * includes/fn/person_status.php): read and set here. Only the person sets
 * it, so the routes answer their own callers only: their team devices
 * session and their own OAuth connection (an MCP client among them); an
 * application's key is refused.
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
 * The status routes, merged into ws_api_routes().
 *
 * @return array[]
 */
function ws_person_status_api_routes()
{
    return array(
        array(
            'id'          => 'workspace.status.read',
            'method'      => 'GET',
            'path'        => '/workspace/status',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_person_status_read',
            'returns'     => 'WorkspacePersonStatus',
            'summary'     => 'Read your own status',
            'description' => 'The status the person signed in has set: kind (available, busy, meeting, out, sleeping, leave), its label, a one-line note, until (Unix time, 0 for no end) with its words in the person\'s time zone ("until 18:00 today"), and at, when it was set. status is null with none set or once its end has passed. Only the person\'s own callers read it: their team devices session or their own OAuth connection; an application key is refused with 403 forbidden. A site whose database has not been upgraded answers 503.',
            'params'      => array(),
        ),
        array(
            'id'          => 'workspace.status.set',
            'method'      => 'POST',
            'path'        => '/workspace/status',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_person_status_set',
            'dry_run'     => true,
            'returns'     => 'WorkspacePersonStatus',
            'summary'     => 'Set your own status',
            'description' => 'Sets or clears the status of the person signed in, as the Status dialog in the panel does: the team sees it beside their name and the channels\' agents read it. kind empty clears it. end is when it clears; left out, the kind\'s own end. The answer is {status}, as GET /workspace/status gives it. A bad field is 422 validation_failed with field kind, note, end or until. Only the person sets it: their team devices session or their own OAuth connection; an application key, or a user_id naming somebody else, is refused with 403 forbidden.',
            'details'     => 'It holds no notification back; quiet hours do that. end: none, 30m, 1h, 4h, today (the coming midnight), morning (the end of the person\'s quiet hours, else 08:00) or at (with until). Default ends: busy and meeting 1h, out 4h, sleeping morning, leave today, available none. Ends are counted in the person\'s own time zone.',
            'params'      => array(
                array('name' => 'kind', 'in' => 'body', 'type' => 'string', 'max_length' => 20, 'description' => 'available, busy, meeting, out, sleeping or leave; empty clears the status.'),
                array('name' => 'note', 'in' => 'body', 'type' => 'string', 'max_length' => 400, 'description' => 'One line people should know, at most 80 characters.'),
                array('name' => 'end', 'in' => 'body', 'type' => 'string', 'max_length' => 10, 'description' => 'none, 30m, 1h, 4h, today, morning or at; left out, the kind\'s own end.'),
                array('name' => 'until', 'in' => 'body', 'type' => 'string', 'max_length' => 40, 'description' => 'With end at: a Unix time or a date and time (ISO 8601; without an offset, in the person\'s time zone), within the coming year.'),
                array('name' => 'user_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'Optional; when given it must be the person signed in.'),
            ),
        ),
    );
}

function ws_api_person_status_read($params)
{
    ws_api_person_only(ws_api_person_status_refusal());

    $viewer = ws_api_viewer();

    if (!ws_person_status_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }

    api_ok(array('status' => ws_person_status_get((int) $viewer['id'])));
}

// The person's own status: the check ws_person_status_save() makes is made first,
// so a dry run meets the same refusals.
function ws_api_person_status_set($params)
{
    ws_api_person_only(ws_api_person_status_refusal());

    $viewer = ws_api_viewer();

    if (!ws_person_status_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }

    $request = array();

    foreach (array('kind', 'note', 'end', 'until') as $key) {
        if (isset($params[$key])) {
            $request[$key] = $params[$key];
        }
    }

    if (isset($params['user_id'])) {
        $request['user_id'] = (int) $params['user_id'];
    }

    $check = ws_person_status_save_check($viewer, $request);

    if (!$check['ok']) {
        ws_api_person_status_refused($check);
    }

    api_dry_run_stop('updated', 'workspace_status', array('status' => $check['present']));

    $result = ws_person_status_save($viewer, $request);

    if (!$result['ok']) {
        ws_api_person_status_refused($result);
    }

    api_ok(array('status' => $result['value']));
}

/**
 * What an application key is told.
 *
 * @return string
 */
function ws_api_person_status_refusal()
{
    return lang('Only the person, from their team devices session or their own connection, can read or set their status; an application key cannot do it for them.');
}

/**
 * A refused save as an API error: a bad field is 422 on that field,
 * anything else (not a team member, another person) 403.
 *
 * @param array $result ok, error, field
 * @return void
 */
function ws_api_person_status_refused($result)
{
    if ((string) ($result['field'] ?? '') !== '') {
        api_fail(422, 'validation_failed', $result['error'], $result['field']);
    }

    api_fail(403, 'forbidden', $result['error']);
}

function ws_api_person_status_schema()
{
    return array('status' => 'WorkspacePersonStatusValue?');
}

function ws_api_person_status_value_schema()
{
    return array('kind' => 'string', 'label' => 'string', 'note' => 'string', 'until' => 'integer', 'until_words' => 'string', 'at' => 'integer');
}
