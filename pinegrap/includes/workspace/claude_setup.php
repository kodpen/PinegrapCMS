<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the steps for connecting Claude.
 *
 * The drawer "Set up on claude.ai" of Claude's row in Site Settings
 * (ws_settings_claude_setup_drawer()): five numbered steps, each a title, one
 * sentence and the values to copy. The names of buttons and fields on
 * claude.ai are written as claude.ai shows them, in English, whatever the
 * language of the panel: they are put into the translated sentence
 * afterwards, so a translation cannot change them.
 *
 * The routine address and the token, which step 4 has pasted, are typed on
 * Claude's page of Connections (connections.php?service=claude), in the card
 * ws_claude_connection_card_html() draws.
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
 * A translated sentence with claude.ai's own names put into it. The sentence
 * is translated with [[1]], [[2]]… in place of the names and escaped; the
 * names go in afterwards, escaped and marked as English.
 *
 * @param string   $translated lang() of the sentence, called with the markers
 * @param string[] $names
 * @return string HTML
 */
function ws_claude_setup_text($translated, $names)
{
    $html = h($translated);

    foreach (array_values($names) as $index => $name) {
        $html = str_replace(h('[[' . ($index + 1) . ']]'), '<b class="ws-ui" lang="en">' . h($name) . '</b>', $html);
    }

    return $html;
}

/**
 * The markers a sentence is translated with.
 *
 * @param int $count
 * @return string[]
 */
function ws_claude_setup_marks($count)
{
    $marks = array();

    for ($i = 1; $i <= $count; $i++) {
        $marks[] = '[[' . $i . ']]';
    }

    return $marks;
}

/**
 * Where the application's key and secret go on claude.ai: the environment
 * step of the guide, and the window that shows a new secret once.
 *
 * @return string HTML
 */
function ws_claude_setup_credentials_text()
{
    return ws_claude_setup_text(
        lang(array('string' => 'The application\'s key and secret: on Team and Enterprise plans write them in {var:1} as two lines, PINEGRAP_KEY=… and PINEGRAP_SECRET=…; everybody who uses the environment can read them. On Pro and Max plans open the environment again with its gear instead and, under {var:2}, press {var:3}: {var:4} {var:5}, this site\'s domain in {var:6}, the key as the user name and the secret as the password, then {var:7}. Claude then works with them without ever seeing them.', 'vars' => ws_claude_setup_marks(7))),
        array('Environment variables', 'API credentials', 'Add credential', 'Credential type', 'Basic', 'Allowed websites', 'Connect')
    );
}


/**
 * The Copy button of a value or a code box, and the status line a screen
 * reader hears beside it (ws_claude_copy_script() copies the field).
 *
 * @param string $id   the field it copies
 * @param string $name what it copies, for the button's name
 * @return string HTML
 */
function ws_claude_setup_copy_button($id, $name)
{
    return '<button type="button" class="pg-button pg-button-quiet pg-button-sm no-popover ws-claude-copy-button" data-ws-copy-field="' . h($id) . '" aria-label="' . h(lang('Copy') . ': ' . $name) . '"><i class="bi bi-clipboard" aria-hidden="true"></i>' . h(lang('Copy')) . '</button>'
        . '<span class="visually-hidden" role="status" aria-live="polite" data-copied="' . h(lang('Copied')) . '" data-refused="' . h(lang('The browser did not let the text be copied. Select it and copy it from here.')) . '"></span>';
}

/**
 * A value to copy as a value row: its name, the value in a read-only field
 * the Copy button reads, and Copy at the row's end.
 *
 * @param string $id
 * @param string $name
 * @param string $value
 * @return string HTML
 */
function ws_claude_setup_value($id, $name, $value)
{
    return '<div class="pg-value-row ws-claude-value">'
        . '<label class="pg-value-row-name ws-claude-value-name" for="' . h($id) . '">' . h($name) . '</label>'
        . '<input class="ws-claude-value-field font-monospace" type="text" id="' . h($id) . '" value="' . h($value) . '" readonly>'
        . ws_claude_setup_copy_button($id, $name)
        . '</div>';
}

/**
 * A text to copy as a code box, Copy in its corner.
 *
 * @param string $id
 * @param string $name
 * @param string $value
 * @return string HTML
 */
function ws_claude_setup_code($id, $name, $value)
{
    $rows = max(3, min(6, substr_count($value, "\n") + 1));

    return '<div class="ws-claude-code">'
        . '<textarea class="font-monospace" id="' . h($id) . '" rows="' . $rows . '" aria-label="' . h($name) . '" spellcheck="false" wrap="off" readonly>' . h($value) . '</textarea>'
        . ws_claude_setup_copy_button($id, $name)
        . '</div>';
}

/**
 * The five steps of the drawer "Set up on claude.ai", in the order they are
 * done: a title, one sentence, and the values to copy. Pure but for lang().
 *
 * @param string $host   this site's domain
 * @param string $prompt the routine's short prompt (ws_claude_bootstrap_prompt())
 * @param string $key    the chosen application's key, '' when none is chosen
 * @return string HTML: the items of the drawer's stepper
 */
function ws_claude_setup_guide($host, $prompt, $key = '')
{
    $routines = 'https://claude.ai/code/routines';
    $values = ws_claude_setup_value('ws-claude-host', lang('Domain'), $host)
        . (($key !== '') ? ws_claude_setup_value('ws-claude-setup-key', lang('Key'), $key) : '');

    $steps = array(
        array(
            'title' => h(lang('Create the routine')),
            'text'  => str_replace(
                '<b class="ws-ui" lang="en">claude.ai/code/routines</b>',
                '<a class="ws-ui" lang="en" href="' . h($routines) . '" target="_blank" rel="noopener">claude.ai/code/routines</a>',
                ws_claude_setup_text(
                    lang(array('string' => 'On {var:1} press {var:2}, name it {var:3} and paste this text into {var:4} as it is.', 'vars' => ws_claude_setup_marks(4))),
                    array('claude.ai/code/routines', 'New routine', 'Pinegrap Workspace', 'Instructions')
                )
            ),
            'extra' => ws_claude_setup_code('ws-claude-prompt', lang('The prompt for the routine'), $prompt)
                . '<div class="pg-field-help">' . h(lang('It reads the rest of its instructions from this site at every run. If the form asks for a repository, any small private one will do.')) . '</div>',
        ),
        array(
            'title' => h(lang('Set up its environment')),
            'text'  => ws_claude_setup_text(
                lang(array('string' => 'Under {var:1} open the environment menu and choose {var:2}. Set {var:3} to {var:4}, add this domain under {var:5} and press {var:6}.', 'vars' => ws_claude_setup_marks(6))),
                array('Instructions', 'Add cloud environment', 'Network access', 'Custom', 'Allowed domains', 'Create environment')
            ),
            // Where the key and secret go differs by plan: folded, so the step
            // stays one sentence (the row's fold script opens it).
            'extra' => '<div class="pg-card-inner ws-claude-values">' . $values . '</div>'
                . '<button type="button" class="pg-gather-toggle" data-ws-claude-fold aria-expanded="false" aria-controls="ws-claude-setup-credentials">' . h(lang('Where do the key and secret go?')) . '<span class="bi bi-chevron-down" aria-hidden="true"></span></button>'
                . '<div class="pg-field-help mt-2" id="ws-claude-setup-credentials" hidden>' . ws_claude_setup_credentials_text() . '</div>',
        ),
        array(
            'title' => h(lang('Add the API trigger')),
            'text'  => ws_claude_setup_text(
                lang(array('string' => 'Under {var:1} choose {var:2}, remove every connector under {var:3} and press {var:4}.', 'vars' => ws_claude_setup_marks(4))),
                array('Select a trigger', 'API', 'Connectors', 'Create')
            ),
            'extra' => '',
        ),
        array(
            'title' => h(lang('Paste the routine address and the token in Connections')),
            'text'  => ws_claude_setup_text(
                lang(array('string' => 'Copy the URL of the routine\'s {var:1} trigger, press {var:2} and copy the token at once: both go into Claude\'s page of Connections.', 'vars' => ws_claude_setup_marks(2))),
                array('API', 'Generate token')
            ),
            'extra' => '<a class="pg-button pg-button-quiet pg-button-sm mt-3" href="' . h(ws_claude_connections_url()) . '" target="_blank" rel="noopener">'
                . h(lang('Open Claude in Connections')) . ' <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>',
        ),
        array(
            'title' => h(lang('Try')),
            'text'  => h(lang('Save, then press Try. When the row says Ready, write @claude in a channel: the answer comes under the request in a few minutes, and the eyes reaction (👀) on the request means Claude has taken it.')),
            'extra' => '',
        ),
    );

    $html = '';

    // One child in each item: the stepper's grid has two columns, the number
    // and the text, and would put a second child into the number's column.
    foreach ($steps as $step) {
        $html .= '
            <li class="stepper-item active ws-claude-step">
                <div class="ws-claude-step-body">
                    <div class="ws-claude-step-title">' . $step['title'] . '</div>
                    <p class="pg-text-2 fg-2 mb-0">' . $step['text'] . '</p>
                    ' . $step['extra'] . '
                </div>
            </li>';
    }

    return $html;
}

/**
 * The address of Claude's page on Connections, where the routine address and
 * the token are typed. Pure but for the path constants.
 *
 * @return string
 */
function ws_claude_connections_url()
{
    return (defined('PATH') ? PATH : '') . (defined('SOFTWARE_DIRECTORY') ? SOFTWARE_DIRECTORY : '')
        . '/connections.php?service=claude';
}

/**
 * The card "Connection details" of Claude's page on Connections: the
 * routine's address and its token, as step 4 of "Set up on claude.ai" has
 * them pasted, then Save and "Save and try". The token is never drawn back:
 * a stored one is dots in the empty field, the eye shows what is typed, the
 * bin ticks claude_token_clear, which removes it at the Save
 * (ws_claude_fold_script() binds both). Pure but for lang().
 *
 * @param array $v routine_url, token_set (bool), token_field (the form's
 *                 CSRF field), can_seal (bool: the site can encrypt the
 *                 token), https (bool: the site is opened over HTTPS)
 * @return string HTML
 */
function ws_claude_connection_card_html($v)
{
    $stored = !empty($v['token_set']);
    $can_seal = !empty($v['can_seal']);
    $notes = '';

    if (empty($v['https'])) {
        $notes .= '<div class="alert theme-warning small py-3 mb-4">' . h(lang('This site is not opened over HTTPS, so the routine cannot reach it.')) . '</div>';
    }

    if (!$can_seal) {
        $notes .= '<div class="alert theme-warning small py-3 mb-4">' . h(lang('The token cannot be stored: encryption is not available on this site.')) . '</div>';
    }

    $clear = $stored
        ? '<button type="button" class="pg-icon-button pg-icon-button-sm no-popover" data-ws-claude-clear="ws_claude_token_clear" aria-pressed="false" data-stored="' . h('••••••••••••••••') . '" data-cleared="' . h(lang('The stored token is removed when you save.')) . '" title="' . h(lang('Remove the stored token')) . '" aria-label="' . h(lang('Remove the stored token')) . '"><i class="bi bi-trash" aria-hidden="true"></i></button>'
            . '<input class="d-none" type="checkbox" name="claude_token_clear" value="1" id="ws_claude_token_clear" tabindex="-1" aria-hidden="true">'
        : '';

    return '
        <section class="pg-card p-5 mb-6" id="connections-details" aria-labelledby="connections-page-details">
            <h2 class="pg-title-section mb-1" id="connections-page-details">' . h(lang('Connection details')) . '</h2>
            <p class="pg-card-sub mb-4">' . h(lang('The address and the token of the API trigger of your routine on claude.ai, which answers @claude in the channels.')) . '</p>
            ' . $notes . '
            <form method="post" action="connections.php" class="connections-app-form">
                ' . (string) ($v['token_field'] ?? '') . '
                <input type="hidden" name="connections_action" value="claude_save">
                <input type="hidden" name="connections_return" value="claude">
                <div>
                    <label class="pg-field-label" for="ws_claude_routine_url">' . h(lang('Routine address')) . '</label>
                    <input class="pg-input font-monospace" type="url" name="claude_routine_url" id="ws_claude_routine_url" value="' . h((string) ($v['routine_url'] ?? '')) . '" maxlength="500" placeholder="https://api.anthropic.com/v1/claude_code/routines/trig_…/fire" autocomplete="off" spellcheck="false" aria-describedby="ws_claude_routine_url-help">
                    <div class="pg-field-help" id="ws_claude_routine_url-help">' . ws_claude_setup_text(lang(array('string' => 'The URL of the routine\'s {var:1} trigger on claude.ai.', 'vars' => ws_claude_setup_marks(1))), array('API')) . '</div>
                </div>
                <div>
                    <label class="pg-field-label" for="ws_claude_token">' . h(lang('Token')) . '</label>
                    <span class="pg-input ws-claude-token"><input type="password" name="claude_token" id="ws_claude_token" value="" maxlength="500" autocomplete="new-password" spellcheck="false" placeholder="' . h($stored ? '••••••••••••••••' : 'sk-ant-oat01-…') . '" aria-describedby="ws_claude_token-help">'
                    . '<button type="button" class="pg-icon-button pg-icon-button-sm no-popover" data-ws-claude-eye="ws_claude_token" aria-pressed="false" title="' . h(lang('Show the token')) . '" aria-label="' . h(lang('Show the token')) . '"><i class="bi bi-eye" aria-hidden="true"></i></button>' . $clear . '</span>
                    <div class="pg-field-help" id="ws_claude_token-help">' . h($stored ? lang('Stored and never shown again. Leave it empty to keep it.') : lang('Shown once on claude.ai when the token is generated. It is stored encrypted.')) . '</div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <button type="submit" name="submit_save" value="1" class="pg-button pg-button-sm pg-button-fill"' . ($can_seal ? '' : ' disabled') . '>' . h(lang('Save')) . '</button>
                    <button type="submit" name="submit_try" value="1" class="pg-button pg-button-sm pg-button-quiet"' . ($can_seal ? '' : ' disabled') . '>' . h(lang('Save and try')) . '</button>
                </div>
            </form>
        </section>';
}

/**
 * The routine's fields posted from Claude's page on Connections, in the
 * shape ws_claude_settings_save() takes: the switch, the application and
 * the summary as stored, so the save writes only the address and the token;
 * app_kept says the application is passed on, not chosen, so a linked one
 * deleted in Application Access does not refuse the save. Pure.
 *
 * @param array $source $_POST
 * @param array $config ws_claude_config()
 * @return array as ws_claude_settings_input(), with app_kept true
 */
function ws_claude_routine_input($source, $config)
{
    $url = $source['claude_routine_url'] ?? '';
    $token = $source['claude_token'] ?? '';
    $clear = $source['claude_token_clear'] ?? '';

    return array(
        'enabled'         => !empty($config['enabled']),
        'app_id'          => (int) ($config['app_id'] ?? 0),
        'app_kept'        => true,
        'routine_url'     => is_scalar($url) ? trim((string) $url) : '',
        'token'           => is_scalar($token) ? trim((string) $token) : '',
        'token_clear'     => is_scalar($clear) && ((string) $clear !== '') && ((string) $clear !== '0'),
        'summary_hour'    => null,
        'summary_channel' => 0,
    );
}
