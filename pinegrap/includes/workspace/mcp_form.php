<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - each person's own side of the MCP servers (workspace_mcp.php):
 * their own account on every server people sign in to, connected and ended
 * in one place, and a form that runs a tool that reads without Pinegrap AI.
 *
 * The form is drawn from the tool's input schema: a field for each property,
 * a list for an enum, a number field for a number, one line per item for a
 * list of plain values and JSON for anything else. Only a tool that is turned
 * on and runs by itself can be run this way - one that asks first waits for
 * an approval under an answer (mcp_ask.php) and is never run from here. The
 * call goes with the person's own account when they have one, else with the
 * site's, as when Pinegrap AI calls it, and is kept in ws_mcp_calls with via
 * 'form'. What the tool answered is shown to the person and, when they choose
 * a channel they may write in, posted there in their name as a locked
 * message.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The most fields one form shows. */
define('WS_MCP_FORM_MAX_FIELDS', 40);

/** How much of a tool's answer the screen shows, in characters. */
define('WS_MCP_FORM_RESULT_LIMIT', 20000);

/** How much of a tool's answer the message in a channel quotes. */
define('WS_MCP_FORM_MESSAGE_LIMIT', 3000);

/** The most form calls a person makes on one server in a minute. */
define('WS_MCP_FORM_PER_MINUTE', 10);

/**
 * The servers a person sees on their own screen: the turned-on ones.
 *
 * @return array[] by id
 */
function ws_mcp_active_servers()
{
    $out = array();

    foreach (ws_mcp_ready() ? ws_mcp_servers() : array() as $id => $server) {
        if ($server['status'] === 'active') {
            $out[$id] = $server;
        }
    }

    return $out;
}

/**
 * The tools of a server a form may run: turned on, and running without an
 * approval. By name, in name order.
 *
 * @param array $server
 * @return array[]
 */
function ws_mcp_form_tools($server)
{
    $out = array();

    foreach (ws_mcp_tools_on($server) as $name => $tool) {
        if (empty($tool['approve'])) {
            $out[$name] = $tool;
        }
    }

    ksort($out);

    return $out;
}

/**
 * The query of a tool's form on workspace_mcp.php, or of the screen at a
 * server when no tool is given.
 *
 * @param int    $server_id
 * @param string $tool
 * @param int    $channel_id the channel the answer is offered to
 * @return string '?...' with its fragment, '' for the screen itself
 */
function ws_mcp_form_query($server_id = 0, $tool = '', $channel_id = 0)
{
    $query = array();

    if ((int) $server_id > 0) {
        $query['server'] = (int) $server_id;
    }

    if ((string) $tool !== '') {
        $query['tool'] = (string) $tool;
    }

    if ((int) $channel_id > 0) {
        $query['channel'] = (int) $channel_id;
    }

    if (empty($query)) {
        return '';
    }

    return '?' . http_build_query($query) . (((string) $tool !== '') ? '#ws-mcp-form' : '#ws-mcp-' . (int) $server_id);
}

/**
 * The full address of a tool's form, for a message in a channel.
 *
 * @param int    $server_id
 * @param string $tool
 * @param int    $channel_id
 * @return string
 */
function ws_mcp_form_url($server_id = 0, $tool = '', $channel_id = 0)
{
    // Without the fragment: a # in a message is read as a channel.
    return URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/workspace_mcp.php' . preg_replace('/#.*$/', '', ws_mcp_form_query($server_id, $tool, $channel_id));
}

/**
 * What a channel is told after an alias was written while Pinegrap AI cannot
 * answer: that the server's tools that read can be run with a form. '' when
 * the server has none.
 *
 * @param array $server
 * @param int   $channel_id
 * @return string Markdown, with the space before it
 */
function ws_mcp_form_hint($server, $channel_id)
{
    if (($server['status'] !== 'active') || empty(ws_mcp_form_tools($server))) {
        return '';
    }

    return ' ' . lang(array('string' => 'The tools of @{var:1} that read can be run with a form without it: {var:2}', 'vars' => array(
        $server['alias'],
        '[' . str_replace(array('[', ']'), '', lang(array('string' => 'Run a tool of @{var:1}', 'vars' => $server['alias']))) . '](' . ws_mcp_form_url((int) $server['id'], '', $channel_id) . ')',
    )));
}

/**
 * The JSON Schema type of a property, as one word: the first that is not
 * null, from type or from the choices of anyOf / oneOf.
 *
 * @param array $property
 * @return string '' when it says none
 */
function ws_mcp_form_schema_type($property)
{
    $types = array();

    if (isset($property['type'])) {
        $types = is_array($property['type']) ? $property['type'] : array($property['type']);
    } else {
        foreach (array('anyOf', 'oneOf') as $key) {
            foreach ((isset($property[$key]) && is_array($property[$key])) ? $property[$key] : array() as $choice) {
                if (is_array($choice) && isset($choice['type']) && is_string($choice['type'])) {
                    $types[] = $choice['type'];
                }
            }
        }
    }

    foreach ($types as $type) {
        if (is_string($type) && ($type !== 'null')) {
            return $type;
        }
    }

    return '';
}

/**
 * The fields of a tool's form, from its input schema. A property becomes a
 * field of one of these kinds: string (with enum, a list to choose from),
 * integer, number, boolean, list (one plain value per line) and json
 * (anything else, typed as JSON). A property whose name is not a plain word
 * is left out.
 *
 * @param string $schema_json
 * @return array[] name, label, description, type, item_type, enum, required, default
 */
function ws_mcp_form_fields($schema_json)
{
    $schema = json_decode((string) $schema_json, true);
    $properties = (is_array($schema) && isset($schema['properties']) && is_array($schema['properties'])) ? $schema['properties'] : array();
    $required = (is_array($schema) && isset($schema['required']) && is_array($schema['required'])) ? array_map('strval', array_filter($schema['required'], 'is_scalar')) : array();
    $fields = array();

    foreach ($properties as $name => $property) {
        $name = (string) $name;

        if (count($fields) >= WS_MCP_FORM_MAX_FIELDS) {
            break;
        }

        if (!preg_match('/^[A-Za-z0-9_.:@$-]{1,64}$/', $name)) {
            continue;
        }

        $property = is_array($property) ? $property : array();
        $type = ws_mcp_form_schema_type($property);
        $item_type = '';
        $enum = array();

        if (isset($property['enum']) && is_array($property['enum'])) {
            foreach ($property['enum'] as $value) {
                if (is_string($value) || is_int($value) || is_float($value)) {
                    $enum[] = (string) $value;
                }
            }

            // A choice of values that are not all plain is typed as JSON.
            if (count($enum) !== count($property['enum'])) {
                $enum = array();
                $type = 'json';
            }
        }

        if ($type === 'array') {
            $items = (isset($property['items']) && is_array($property['items'])) ? $property['items'] : array();
            $item_type = ws_mcp_form_schema_type($items);
            $type = (in_array($item_type, array('string', 'integer', 'number'), true) && !isset($items['enum'])) ? 'list' : 'json';

            if ($type === 'json') {
                $item_type = '';
            }
        } elseif ($type === 'object') {
            $type = 'json';
        } elseif (!in_array($type, array('string', 'integer', 'number', 'boolean', 'json'), true)) {
            // No type, or one the form does not know: what is typed goes as
            // text, which is what such a property takes most often.
            $type = 'string';
        }

        // A choice offered as a list is a string's; numbers in an enum are
        // sent as numbers again (ws_mcp_form_arguments()).
        if (!empty($enum) && !in_array($type, array('string', 'integer', 'number'), true)) {
            $enum = array();
        }

        $default = '';

        if (array_key_exists('default', $property)) {
            $value = $property['default'];

            if (is_bool($value)) {
                $default = $value ? '1' : '0';
            } elseif (is_scalar($value)) {
                $default = (string) $value;
            } elseif (is_array($value) && ($type === 'list')) {
                $default = implode("\n", array_map('strval', array_filter($value, 'is_scalar')));
            } elseif ($value !== null) {
                $default = (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }

        $title = isset($property['title']) && is_string($property['title']) ? trim($property['title']) : '';

        $fields[] = array(
            'name'        => $name,
            'label'       => mb_substr(($title !== '') ? $title : $name, 0, 80),
            'description' => mb_substr(trim(preg_replace('/\s+/u', ' ', (isset($property['description']) && is_string($property['description'])) ? $property['description'] : '')), 0, 300),
            'type'        => $type,
            'item_type'   => $item_type,
            'enum'        => $enum,
            'required'    => in_array($name, $required, true),
            'default'     => $default,
        );
    }

    return $fields;
}

/**
 * A number as typed ("7", "7,5", "-2"), or null when it is not one.
 *
 * @param string $text
 * @param bool   $whole only a whole number will do
 * @return int|float|null
 */
function ws_mcp_form_number($text, $whole)
{
    $text = str_replace(',', '.', trim((string) $text));

    if ($whole) {
        return preg_match('/^-?[0-9]{1,18}$/', $text) ? (int) $text : null;
    }

    if (!preg_match('/^-?[0-9]{1,18}(\.[0-9]{1,18})?$/', $text)) {
        return null;
    }

    return (strpos($text, '.') === false) ? (int) $text : (float) $text;
}

/**
 * The arguments a form sends, from what was typed: an empty field is left
 * out (a required one is an error), and each value takes its field's type.
 *
 * @param array[] $fields ws_mcp_form_fields()
 * @param array   $input  name => what was typed
 * @return array arguments (name => value), errors (name => text)
 */
function ws_mcp_form_arguments($fields, $input)
{
    $arguments = array();
    $errors = array();

    foreach ($fields as $field) {
        $name = $field['name'];
        $raw = (isset($input[$name]) && is_string($input[$name])) ? $input[$name] : '';
        $text = trim($raw);

        if ($text === '') {
            if ($field['required']) {
                $errors[$name] = lang('Fill this in.');
            }

            continue;
        }

        switch ($field['type']) {
            case 'integer':
            case 'number':
                $number = ws_mcp_form_number($text, ($field['type'] === 'integer'));

                if ($number === null) {
                    $errors[$name] = ($field['type'] === 'integer') ? lang('Enter a whole number.') : lang('Enter a number.');
                } elseif (!empty($field['enum']) && !in_array((string) $number, $field['enum'], true)) {
                    $errors[$name] = lang('Choose one of the values offered.');
                } else {
                    $arguments[$name] = $number;
                }

                break;

            case 'boolean':
                $arguments[$name] = ($text === '1');
                break;

            case 'list':
                $items = array();

                foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
                    $line = trim($line);

                    if ($line === '') {
                        continue;
                    }

                    if ($field['item_type'] === 'string') {
                        $items[] = $line;
                        continue;
                    }

                    $number = ws_mcp_form_number($line, ($field['item_type'] === 'integer'));

                    if ($number === null) {
                        $errors[$name] = ($field['item_type'] === 'integer') ? lang('Enter a whole number on each line.') : lang('Enter a number on each line.');
                        break;
                    }

                    $items[] = $number;
                }

                if (!isset($errors[$name])) {
                    $arguments[$name] = $items;
                }

                break;

            case 'json':
                // Objects stay objects, so {} goes out as {} and not as [].
                $value = json_decode($text);

                if (($value === null) && (strtolower($text) !== 'null')) {
                    $errors[$name] = lang('This is not valid JSON.');
                } else {
                    $arguments[$name] = $value;
                }

                break;

            default:
                if (!empty($field['enum']) && !in_array($text, $field['enum'], true)) {
                    $errors[$name] = lang('Choose one of the values offered.');
                } else {
                    $arguments[$name] = $text;
                }
        }
    }

    return array('arguments' => $arguments, 'errors' => $errors);
}

/**
 * One field of a tool's form.
 *
 * @param array       $field ws_mcp_form_fields()
 * @param string|null $value what was typed before, null for the default
 * @param string      $error
 * @return string
 */
function ws_mcp_form_field_html($field, $value, $error = '')
{
    $id = 'ws_mcp_arg_' . substr(md5($field['name']), 0, 10);
    $name = 'mcp_arg[' . h($field['name']) . ']';
    $value = ($value === null) ? $field['default'] : (string) $value;
    $invalid = ($error !== '') ? ' is-invalid' : '';
    $required = $field['required'] ? ' required' : '';
    // The panel marks the label of a required field itself.
    $label = h($field['label'])
        . (($field['label'] !== $field['name']) ? ' <code class="small text-body-secondary">' . h($field['name']) . '</code>' : '');

    if (!empty($field['enum'])) {
        $options = '<option value="">' . h($field['required'] ? lang('Choose…') : lang('(empty)')) . '</option>';

        foreach ($field['enum'] as $choice) {
            $options .= '<option value="' . h($choice) . '"' . (($choice === $value) ? ' selected' : '') . '>' . h($choice) . '</option>';
        }

        $input = '<select class="form-select form-select-sm' . $invalid . '" name="' . $name . '" id="' . $id . '"' . $required . '>' . $options . '</select>';
    } elseif ($field['type'] === 'boolean') {
        $input = '<select class="form-select form-select-sm' . $invalid . '" name="' . $name . '" id="' . $id . '"' . $required . '>'
            . '<option value="">' . h($field['required'] ? lang('Choose…') : lang('(empty)')) . '</option>'
            . '<option value="1"' . (($value === '1') ? ' selected' : '') . '>' . h(lang('Yes')) . '</option>'
            . '<option value="0"' . (($value === '0') ? ' selected' : '') . '>' . h(lang('No')) . '</option>'
            . '</select>';
    } elseif (in_array($field['type'], array('list', 'json'), true)) {
        $input = '<textarea class="form-control form-control-sm font-monospace' . $invalid . '" name="' . $name . '" id="' . $id . '" rows="3"' . $required . ' spellcheck="false">' . h($value) . '</textarea>';
    } else {
        $number = in_array($field['type'], array('integer', 'number'), true);

        $input = '<input class="form-control form-control-sm' . $invalid . '" type="text"' . ($number ? ' inputmode="decimal"' : '') . ' name="' . $name . '" id="' . $id . '" value="' . h($value) . '"' . $required . ' autocomplete="off">';
    }

    $hint = array();

    if ($field['type'] === 'list') {
        $hint[] = lang('One per line.');
    } elseif ($field['type'] === 'json') {
        $hint[] = lang('As JSON.');
    }

    if ($field['description'] !== '') {
        $hint[] = $field['description'];
    }

    return '
        <div class="mb-2">
            <label class="form-label small mb-1" for="' . $id . '">' . $label . '</label>
            ' . $input . '
            ' . (($error !== '') ? '<div class="invalid-feedback">' . h($error) . '</div>' : '') . '
            ' . (!empty($hint) ? '<div class="form-text">' . h(implode(' ', $hint)) . '</div>' : '') . '
        </div>';
}

/**
 * The message a channel gets with what a tool answered: who ran which tool,
 * and the answer quoted as a block, cut to a length. A tag in the answer
 * would mention somebody here, so its angle bracket is changed, and a fence
 * in it would end the block, so its backticks are.
 *
 * @param string $alias
 * @param string $tool
 * @param string $person
 * @param string $text   what the tool answered
 * @param bool   $failed the tool answered with an error
 * @return string
 */
function ws_mcp_form_message_body($alias, $tool, $person, $text, $failed = false)
{
    $quote = trim(str_replace('```', "'''", preg_replace('/<(?=[@#])/', '‹', (string) $text)));
    $tool = '`' . str_replace('`', '', (string) $tool) . '`';

    $head = $failed
        ? lang(array('string' => '@{var:1}: {var:2} was run with a form by {var:3} and answered with an error.', 'vars' => array($alias, $tool, $person)))
        : lang(array('string' => '@{var:1}: {var:2} was run with a form by {var:3}.', 'vars' => array($alias, $tool, $person)));

    if ($quote === '') {
        return $head;
    }

    $room = max(0, WS_MESSAGE_MAX - mb_strlen($head) - 10);

    if (mb_strlen($quote) > $room) {
        $quote = rtrim(mb_substr($quote, 0, max(0, $room - 1))) . '…';
    }

    return $head . "\n```\n" . $quote . "\n```";
}

/**
 * Runs a tool from its form, with the person's own account when they have
 * one, else with the site's, and keeps the call (ws_mcp_calls, via 'form').
 * With a channel the answer is posted there in the person's name, locked.
 *
 * @param array      $viewer
 * @param array      $server
 * @param array      $tool      ws_mcp_form_tools()
 * @param array      $arguments ws_mcp_form_arguments()
 * @param array|null $channel   where the answer goes, null to keep it here
 * @return array ok, error, text, is_error, message_id
 */
function ws_mcp_form_run($viewer, $server, $tool, $arguments, $channel = null)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'text' => '', 'is_error' => true, 'message_id' => 0);
    };

    if (!function_exists('curl_init')) {
        return $fail(lang('This server cannot make outgoing HTTPS requests (cURL is missing).'));
    }

    $json = (string) json_encode((object) $arguments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if (strlen($json) > 20000) {
        return $fail(lang('What was typed is too long.'));
    }

    $recent = (int) db_value("SELECT COUNT(*) FROM ws_mcp_calls
        WHERE server_id = '" . (int) $server['id'] . "' AND via = 'form' AND requested_by = '" . (int) $viewer['id'] . "' AND created_at > '" . (time() - 60) . "'");

    if ($recent >= WS_MCP_FORM_PER_MINUTE) {
        return $fail(lang('You have run many tools in the last minute. Please wait a moment.'));
    }

    if ($channel && !ws_can_post_channel($viewer, $channel)) {
        return $fail(lang('You cannot post in that channel.'));
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(WS_MCP_SCREEN_TIMEOUT * 3 + 30);
    }

    $conn = ws_mcp_connection($server, WS_MCP_SCREEN_TIMEOUT, (int) $viewer['id']);

    if (($server['auth_kind'] === 'oauth') && empty($conn['headers'])) {
        return $fail(lang(array('string' => 'Connect your own account to @{var:1} first: nobody\'s account is connected there for you.', 'vars' => $server['alias'])));
    }

    $started = microtime(true);
    $now = time();
    $out = pg_mcp_client_call_tool($conn, (string) $tool['name'], json_decode($json));

    // A token the service no longer takes is renewed once (OAuth servers).
    if (!$out['ok'] && ws_mcp_reauthorize($conn, $server, $out['status'] ?? 0)) {
        $out = pg_mcp_client_call_tool($conn, (string) $tool['name'], json_decode($json));
    }

    $duration = (int) round((microtime(true) - $started) * 1000);

    if (!$out['ok']) {
        $error = lang(array('string' => '@{var:1} could not be reached: {var:2}', 'vars' => array($server['alias'], ws_mcp_error_text($server, $out['status'] ?? 0, $out['error'], $conn['person']))));
    } else {
        $error = $out['is_error'] ? lang('The tool answered with an error.') : '';
    }

    ws_mcp_remember($server, $conn, $out['ok'] ? '' : $error);

    db("INSERT INTO ws_mcp_calls (server_id, tool, arguments, via, requested_by, channel_id, status, error, duration_ms, created_at, finished_at)
        VALUES (
            '" . (int) $server['id'] . "',
            '" . e((string) $tool['name']) . "',
            '" . e($json) . "',
            'form',
            '" . (int) $viewer['id'] . "',
            '" . ($channel ? (int) $channel['id'] : 0) . "',
            '" . (($error === '') ? 'done' : 'failed') . "',
            '" . e(mb_substr($error, 0, 250)) . "',
            '" . $duration . "',
            '" . $now . "',
            '" . time() . "')");

    $call_id = (int) mysqli_insert_id(db::$con);

    db("UPDATE ws_mcp_servers SET last_used = '" . time() . "' WHERE id = '" . (int) $server['id'] . "'");

    log_activity(lang(array('string' => 'ran the tool {var:1} of the MCP server @{var:2} with a form', 'vars' => array((string) $tool['name'], $server['alias']))), (string) ($_SESSION['sessionusername'] ?? ''));

    if (!$out['ok']) {
        return $fail($error);
    }

    $text = pg_mcp_client_result_text($out['result'], WS_MCP_FORM_RESULT_LIMIT);
    $message_id = 0;
    $post_error = '';

    if ($channel) {
        $body = ws_mcp_form_message_body($server['alias'], (string) $tool['name'], ws_person_name((int) $viewer['id']), pg_mcp_client_result_text($out['result'], WS_MCP_FORM_MESSAGE_LIMIT), $out['is_error']);
        $sent = ws_message_send($viewer, $channel, $body);

        if ($sent['ok']) {
            $message_id = (int) $sent['message_id'];

            // The answer is the service's, not the person's words: nobody
            // edits it.
            db("UPDATE ws_messages SET locked = 1 WHERE id = '" . $message_id . "'");
            db("UPDATE ws_mcp_calls SET message_id = '" . $message_id . "' WHERE id = '" . $call_id . "'");

            if (function_exists('ws_thread_copy_sync')) {
                ws_thread_copy_sync($message_id);
            }
        } else {
            $post_error = $sent['error'];
        }
    }

    return array(
        'ok'         => true,
        'error'      => ($post_error !== '') ? $post_error : $error,
        'text'       => $text,
        'is_error'   => $out['is_error'],
        'message_id' => $message_id,
    );
}

/**
 * A person's own account on each server people sign in to, for their own
 * screen: whether it works, whether the site's account stands in for it.
 *
 * @param array $viewer
 * @param array $server
 * @return array oauth, mine (account or null), works, site (the site's works)
 */
function ws_mcp_my_connection($viewer, $server)
{
    $oauth = ($server['auth_kind'] === 'oauth');
    $mine = $oauth ? ws_mcp_person_account($server, (int) $viewer['id']) : null;
    $site = $oauth ? ws_mcp_site_account($server) : null;

    return array(
        'oauth' => $oauth,
        'mine'  => $mine,
        'works' => $mine && ($mine['status'] === 'active'),
        'site'  => $site && ($site['status'] === 'active'),
    );
}
