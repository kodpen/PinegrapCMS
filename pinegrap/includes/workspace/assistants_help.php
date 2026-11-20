<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the help of Site Settings > Assistants and agents: one line
 * for each of Pinegrap AI, Workspace agents, MCP servers, Voice and Canvas
 * templates, with "How does this work?" opening a drawer of numbered steps
 * that holds what more there is to say. Claude's row has its own set-up
 * drawer (includes/workspace/claude.php). The lines and steps are pure but
 * for lang(); the drawers are drawn by includes/settings/workspace_assistants.php.
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
 * The parts with a help line and a guide, in the rows' order. A key is its
 * card's id without pgset-.
 *
 * @return string[]
 */
function ws_assistants_help_keys()
{
    return array('ws-ai', 'ws-agents', 'ws-mcp', 'ws-voice', 'ws-canvas-templates');
}

/**
 * A part's one line of help: what the person gets, in plain words.
 *
 * @param string $key one of ws_assistants_help_keys()
 * @return string '' for another part
 */
function ws_assistants_help_line($key)
{
    switch ($key) {
        case 'ws-ai':
            return lang('Write @ai in a channel and Pinegrap AI answers there, through your Pinegrap Premium licence.');
        case 'ws-agents':
            return lang('An agent answers in the channels it is added to when someone writes its @handle.');
        case 'ws-mcp':
            return lang('Connect another service\'s tools under an alias, then ask in a channel: "@github which pull requests wait for a review?"');
        case 'ws-voice':
            return lang('Voice messages in the channels are written out as text, so they can be read and searched.');
        case 'ws-canvas-templates':
            return lang('An agent makes a new canvas from a template, filling its named layers with words and pictures.');
    }

    return '';
}

/**
 * The title of a part's guide drawer.
 *
 * @param string $key one of ws_assistants_help_keys()
 * @return string
 */
function ws_assistants_guide_title($key)
{
    switch ($key) {
        case 'ws-ai':
            return lang('How Pinegrap AI works');
        case 'ws-agents':
            return lang('How agents work');
        case 'ws-mcp':
            return lang('How MCP servers work');
        case 'ws-voice':
            return lang('How voice transcripts work');
        case 'ws-canvas-templates':
            return lang('How canvas templates work');
    }

    return '';
}

/**
 * A part's guide as numbered steps. A step may lead to another screen:
 * link is array(href, icon, label), the href relative to the panel.
 *
 * @param string $key one of ws_assistants_help_keys()
 * @return array[] each title, text and optionally link; empty for another part
 */
function ws_assistants_guide_steps($key)
{
    $access = array('api_settings.php', 'bi-key', lang('Application Access'));

    switch ($key) {
        case 'ws-ai':
            return array(
                array(
                    'title' => lang('An application for Pinegrap AI'),
                    'text'  => lang('Press Create Pinegrap AI\'s key in its row: it makes the application Pinegrap AI writes through, without a secret, so it cannot sign in anywhere. To use another one, make it in Application Access and choose it under Use another application.'),
                    'link'  => $access,
                ),
                array(
                    'title' => lang('The licence key'),
                    'text'  => lang('Enter the subscription key of your Pinegrap Premium licence under Site Settings › General › Software. It is checked against the site\'s address: a key that does not match or has expired does not work.'),
                    'link'  => array(function_exists('pg_settings_link') ? pg_settings_link('general', 'pgset-software') : 'settings.php', 'bi-gear', lang('Site Settings › General › Software')),
                ),
                array(
                    'title' => lang('Switch it on'),
                    'text'  => lang('What is written in a channel where Pinegrap AI is asked is sent to the model at ai.pinegrap.com. Public channels are open to it, private ones only when their manager allows it.'),
                ),
                array(
                    'title' => lang('Try it'),
                    'text'  => lang('Try the connection here, then write @ai in a channel. The eye on the request means Pinegrap AI has taken it; the answer comes under the request, usually within a minute.'),
                ),
            );

        case 'ws-agents':
            return array(
                array(
                    'title' => lang('Make its application'),
                    'text'  => lang('In Application Access, make an application for the agent: the agent reads and writes with that application\'s permissions.'),
                    'link'  => $access,
                ),
                array(
                    'title' => lang('Add the agent'),
                    'text'  => lang('Press Add an agent, give it a handle and choose its application. Poll suits a session that stays open; push starts a new run on each knock.'),
                ),
                array(
                    'title' => lang('Add it to a channel'),
                    'text'  => lang('In each channel its manager adds it and chooses when it is woken and what it may do by itself.'),
                ),
            );

        case 'ws-mcp':
            $ask = lang('Written in a channel where Pinegrap AI may be asked, the alias is answered by Pinegrap AI with the server\'s tools.');

            if (function_exists('ws_mcp_agent_ready') && ws_mcp_agent_ready()) {
                $ask .= ' ' . lang('A server that is an agent itself can answer on its own, without Pinegrap AI: choose the tool that takes the messages under Tools and settings.');
            }

            return array(
                array(
                    'title' => lang('Add a server'),
                    'text'  => lang('Press Add a server: a ready-made connector takes one click, any other is added by its address.'),
                ),
                array(
                    'title' => lang('Turn its tools on'),
                    'text'  => lang('The tools that only read are turned on when the server\'s tools are first read; turn the others on under Tools and settings.'),
                ),
                array(
                    'title' => lang('Ask in a channel'),
                    'text'  => $ask,
                ),
                array(
                    'title' => lang('Your own account'),
                    'text'  => lang('On a server people sign in to (OAuth), everybody may connect their own account, and tools that write can be turned on too: each call waits under the answer until the person who asked approves it, and runs with their own account.'),
                    'link'  => array('workspace_mcp.php', 'bi-person-gear', lang('My MCP connections')),
                ),
            );

        case 'ws-voice':
            return array(
                array(
                    'title' => lang('Choose the service'),
                    'text'  => lang('Choose OpenAI, or the address of any service that answers like OpenAI\'s /audio/transcriptions, and give its key.'),
                ),
                array(
                    'title' => lang('Switch it on'),
                    'text'  => lang(array(
                        'string' => 'The sound of a voice message is sent to the chosen service and its text is kept beside the message. Public channels are written out, private ones only when their manager allows it. Recordings longer than {var:1} minutes are not sent.',
                        'vars'   => (int) ((defined('WS_VOICE_MAX_SECONDS') ? WS_VOICE_MAX_SECONDS : 300) / 60),
                    )),
                ),
            );

        case 'ws-canvas-templates':
            return array(
                array(
                    'title' => lang('Make a template'),
                    'text'  => lang('Open a canvas from Files in the image editor and choose Save as template in the save menu.'),
                ),
                array(
                    'title' => lang('Name its layers'),
                    'text'  => lang('An agent fills the named layers with words and pictures; a template without named layers can only be copied.'),
                ),
                array(
                    'title' => lang('Take one off the list'),
                    'text'  => lang('Untick it here and save.'),
                ),
            );
    }

    return array();
}

/**
 * A part's line of help with the button that opens its guide drawer.
 *
 * @param string $key one of ws_assistants_help_keys()
 * @return string HTML; '' for another part
 */
function ws_assistants_help_html($key)
{
    $line = ws_assistants_help_line($key);

    if ($line === '') {
        return '';
    }

    $id = 'pgset-' . $key . '-guide';

    return '<p class="pg-text-2 fg-secondary ws-assist-help">' . h($line)
        . ' <button type="button" class="btn btn-link p-0 align-baseline pg-text-2" data-bs-toggle="drawer" data-bs-target="#' . h($id) . '" aria-controls="' . h($id) . '">' . h(lang('How does this work?')) . '</button></p>';
}

/**
 * A part's guide drawer: its steps numbered, each with its link.
 *
 * @param string $key       one of ws_assistants_help_keys()
 * @param bool   $in_dialog drawn in the settings dialog
 * @return string HTML; '' for a part without a guide
 */
function ws_assistants_guide_drawer($key, $in_dialog)
{
    $steps = ws_assistants_guide_steps($key);

    if (empty($steps)) {
        return '';
    }

    $items = '';

    foreach ($steps as $step) {
        $link = '';

        if (!empty($step['link'])) {
            $link = '<a class="pg-button pg-button-quiet pg-button-sm" href="' . h($step['link'][0]) . '"><i class="bi ' . h($step['link'][1]) . '" aria-hidden="true"></i>' . h($step['link'][2]) . '</a>';
        }

        $items .= '
                        <li class="ws-claude-step">
                            <div class="pg-field-label">' . h($step['title']) . '</div>
                            <div class="pg-text-2 fg-secondary' . (($link !== '') ? ' mb-3' : '') . '">' . h($step['text']) . '</div>
                            ' . $link . '
                        </li>';
    }

    $close = '<button type="button" class="pg-button pg-button-quiet pg-button-sm ms-auto" ' . ($in_dialog ? 'data-pgdrawer-close' : 'data-bs-dismiss="drawer"') . '>' . h(lang('Close')) . '</button>';

    return pg_settings_drawer('pgset-' . $key . '-guide', h(ws_assistants_guide_title($key)), '<ol class="ws-claude-steps mb-0">' . $items . '</ol>', $in_dialog, $close);
}
