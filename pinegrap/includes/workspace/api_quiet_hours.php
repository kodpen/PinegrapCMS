<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a person's own quiet hours in the external API
 * (notify.php, includes/fn/quiet_hours.php): read on GET /auth/me, set here.
 * The setting is the person's own, so only their team devices session sets
 * it: an application key and an OAuth connection are refused, and the route
 * is no MCP tool.
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
 * The quiet hours route, merged into ws_api_routes().
 *
 * @return array[]
 */
function ws_quiet_hours_api_routes()
{
    return array(
        array(
            'id'          => 'workspace.quiet_hours.set',
            'method'      => 'POST',
            'path'        => '/workspace/quiet-hours',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_quiet_hours_set',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => 'WorkspaceQuietHours',
            'summary'     => 'Set your own quiet hours',
            'description' => 'Sets or clears the quiet hours of the person signed in, as the "Quiet hours" part of a channel\'s info in the panel does: inside the range no Workspace push wakes their devices, it waits for the range\'s end; the inbox, the bell and mail notices are unchanged. start and end are clock times (HH:MM, 24-hour) in the person\'s own time zone; a range whose end comes first runs over midnight. Both empty, or equal, clears the range (quiet_hours null). off_days 1 keeps the days the person does not work quiet all day. The answer is {quiet_hours}, as GET /auth/me gives it. A bad time is 422 validation_failed with field start or end; a site whose database has not been upgraded answers 503. The setting is the person\'s own: only a team devices session (a Bearer token from POST /auth/login) may set it, an application key and an OAuth connection are refused with 403 forbidden, a user_id naming somebody else is refused, and this is no MCP tool.',
            'params'      => array(
                array('name' => 'start', 'in' => 'body', 'type' => 'string', 'max_length' => 5, 'description' => 'When the quiet range starts, HH:MM; empty with end to clear it.'),
                array('name' => 'end', 'in' => 'body', 'type' => 'string', 'max_length' => 5, 'description' => 'When the quiet range ends, HH:MM; empty with start to clear it.'),
                array('name' => 'off_days', 'in' => 'body', 'type' => 'bool', 'description' => 'Also quiet all day on the days the person does not work.'),
                array('name' => 'user_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'Optional; when given it must be the person signed in.'),
            ),
        ),
    );
}

/**
 * The person's own quiet hours, as GET /auth/me gives them: null with no
 * range set. Leaves no key (false) where the column is missing, so the
 * caller can leave the key out.
 *
 * @param int $user_id
 * @return array|null|false
 */
function ws_api_quiet_hours_read($user_id)
{
    if (!function_exists('ws_quiet_hours_ready') || !ws_quiet_hours_ready()) {
        return false;
    }

    return ws_api_quiet_hours_present(ws_quiet_hours_get($user_id));
}

/**
 * A range as the API gives it. Pure.
 *
 * @param array|null $value pg_quiet_hours_decode()
 * @return array|null start, end, off_days
 */
function ws_api_quiet_hours_present($value)
{
    if (!is_array($value)) {
        return null;
    }

    return array(
        'start'    => (string) ($value['start'] ?? ''),
        'end'      => (string) ($value['end'] ?? ''),
        'off_days' => empty($value['off_days']) ? 0 : 1,
    );
}

// The person's own setting: from their team devices session only, and the
// check ws_quiet_hours_save() makes is made first so a dry run meets the
// same refusals.
function ws_api_quiet_hours_set($params)
{
    ws_api_own_words_only(lang('Only the person\'s own team devices session (POST /auth/login) can set their quiet hours; an application key or an OAuth connection cannot do it for them.'));

    $viewer = ws_api_viewer();

    if (!ws_quiet_hours_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }

    $request = array(
        'start'    => $params['start'] ?? '',
        'end'      => $params['end'] ?? '',
        'off_days' => empty($params['off_days']) ? 0 : 1,
    );

    if (isset($params['user_id'])) {
        $request['user_id'] = (int) $params['user_id'];
    }

    $check = pg_quiet_hours_save_check($viewer, $request);

    if (!$check['ok']) {
        ws_api_quiet_hours_refused($check);
    }

    api_dry_run_stop('updated', 'workspace_quiet_hours', array('quiet_hours' => ws_api_quiet_hours_present($check['value'])));

    $result = ws_quiet_hours_save($viewer, $request);

    if (!$result['ok']) {
        ws_api_quiet_hours_refused($result);
    }

    api_ok(array('quiet_hours' => ws_api_quiet_hours_present($result['value'])));
}

/**
 * A refused save as an API error: a bad time is 422 on its field, anything
 * else (not a team member, another person's profile) 403.
 *
 * @param array $result ok, error, field
 * @return void
 */
function ws_api_quiet_hours_refused($result)
{
    if ((string) ($result['field'] ?? '') !== '') {
        api_fail(422, 'validation_failed', $result['error'], $result['field']);
    }

    api_fail(403, 'forbidden', $result['error']);
}

function ws_api_quiet_hours_schema()
{
    return array('quiet_hours' => 'WorkspaceQuietHoursRange?');
}

function ws_api_quiet_hours_range_schema()
{
    return array('start' => 'string', 'end' => 'string', 'off_days' => 'integer');
}
