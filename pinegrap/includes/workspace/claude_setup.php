<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the illustrated steps for connecting Claude.
 *
 * The "How to connect it" part of the Claude card in the workspace settings
 * (ws_claude_settings_card()): every step has its words and a picture. The
 * names of buttons and fields on claude.ai are written as claude.ai shows
 * them, in English, whatever the language of the panel: they are put into the
 * translated sentence afterwards, so a translation cannot change them.
 *
 * The picture of a step is a screenshot when the site has one in
 * assets/images/ (ws-claude-setup-<step>.png, .webp or .jpg) and otherwise a
 * sketch drawn here, with the part to press marked. claude.ai changes its
 * screens from time to time; a sketch says so under it.
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
 * The picture of a step: the site's screenshot when there is one, the sketch
 * otherwise.
 *
 * @param string $key
 * @param string $host this site's domain, for the sketches that show it
 * @return string HTML
 */
function ws_claude_setup_picture($key, $host)
{
    foreach (array('png', 'webp', 'jpg') as $extension) {
        $file = '/assets/images/ws-claude-setup-' . $key . '.' . $extension;

        if (is_file(PG_FUNCTIONS_DIR . $file)) {
            return '<figure class="ws-claude-shot ws-claude-shot-photo">'
                . '<a href="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . $file) . '" target="_blank" rel="noopener">'
                . '<img src="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . $file . '?v=' . @filemtime(PG_FUNCTIONS_DIR . $file)) . '" alt="" loading="lazy">'
                . '</a></figure>';
        }
    }

    $svg = ws_claude_setup_sketch($key, $host);

    if ($svg === '') {
        return '';
    }

    return '<figure class="ws-claude-shot">' . $svg
        . '<figcaption>' . h(in_array($key, array('app', 'try'), true)
            ? lang('A sketch of the screen.')
            : lang('A sketch of the screen; claude.ai may look a little different.')) . '</figcaption></figure>';
}

/**
 * The sketches, 360 by 200. The classes colour them for the light and the
 * dark panel (backend.src.css, .ws-claude-shot).
 *
 * @param string $key
 * @param string $host
 * @return string SVG
 */
function ws_claude_setup_sketch($key, $host)
{
    $t = function ($x, $y, $text, $class = 's-ink', $size = 10, $anchor = 'start', $weight = 400) {
        return '<text x="' . $x . '" y="' . $y . '" class="' . $class . '" font-size="' . $size . '" text-anchor="' . $anchor . '" font-weight="' . $weight . '">' . h($text) . '</text>';
    };
    $r = function ($x, $y, $w, $h, $class = 's-soft', $rx = 4) {
        return '<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="' . $h . '" rx="' . $rx . '" class="' . $class . '"/>';
    };
    // The part to press: a ring around it.
    $ring = function ($x, $y, $w, $h) {
        return '<rect x="' . ($x - 3) . '" y="' . ($y - 3) . '" width="' . ($w + 6) . '" height="' . ($h + 6) . '" rx="7" class="s-ring"/>';
    };
    $button = function ($x, $y, $w, $text, $primary = false) use ($r, $t) {
        return $r($x, $y, $w, 20, $primary ? 's-acc' : 's-line', 10) . $t($x + $w / 2, $y + 13.5, $text, $primary ? 's-white' : 's-ink', 9.5, 'middle', 600);
    };
    $field = function ($x, $y, $w, $label, $value, $h = 20) use ($r, $t) {
        return $t($x, $y - 4, $label, 's-mute', 8.5, 'start', 600) . $r($x, $y, $w, $h, 's-field', 4) . $t($x + 7, $y + 13, $value, 's-ink', 9);
    };
    $window = function ($address, $body) use ($r, $t) {
        return '<svg viewBox="0 0 360 200" role="img" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">'
            . $r(0.5, 0.5, 359, 199, 's-frame', 10)
            . '<path d="M0.5 10.5a10 10 0 0 1 10-10h339a10 10 0 0 1 10 10V22H0.5z" class="s-bar"/>'
            . '<circle cx="12" cy="11.5" r="3" class="s-dot"/><circle cx="22" cy="11.5" r="3" class="s-dot"/><circle cx="32" cy="11.5" r="3" class="s-dot"/>'
            . $r(46, 5, 268, 13, 's-address', 6) . $t(54, 14.5, $address, 's-mute', 8)
            . $body . '</svg>';
    };

    switch ($key) {

        case 'app':
            return $window($host . '/…/api_settings.php',
                $t(16, 44, lang('Application Access'), 's-ink', 12, 'start', 700)
                . $field(16, 64, 150, lang('Name'), 'Claude')
                . $t(16, 102, lang('Permissions'), 's-mute', 8.5, 'start', 600)
                . $r(16, 108, 150, 76, 's-field', 4)
                . $t(26, 124, '☑ workspace:read', 's-ink', 9) . $t(26, 139, '☑ workspace:write', 's-ink', 9)
                . $t(26, 154, '☑ tasks:read', 's-ink', 9) . $t(26, 169, '☑ tasks:write', 's-ink', 9)
                . $r(186, 60, 158, 124, 's-panel', 6)
                . $t(196, 80, lang('Key'), 's-mute', 8.5, 'start', 600) . $r(196, 85, 138, 18, 's-field', 4) . $t(203, 97.5, 'pg_live_••••••••', 's-ink', 9)
                . $t(196, 122, lang('Secret'), 's-mute', 8.5, 'start', 600) . $r(196, 127, 138, 18, 's-field', 4) . $t(203, 139.5, '••••••••••••', 's-ink', 9)
                . $ring(196, 85, 138, 60)
                . $t(265, 170, lang('Shown once'), 's-warn', 9, 'middle', 600));

        case 'routine':
            return $window('claude.ai/code/routines',
                $t(16, 44, 'Routines', 's-ink', 12, 'start', 700)
                . $button(270, 31, 74, 'New routine', true) . $ring(270, 31, 74, 20)
                . $field(16, 70, 328, 'Name', 'Pinegrap Workspace')
                . $t(16, 106, 'Instructions', 's-mute', 8.5, 'start', 600)
                . $r(16, 110, 328, 58, 's-field', 4)
                . $t(24, 124, 'You are the assistant people call with @Claude…', 's-ink', 9)
                . $r(24, 131, 250, 5, 's-line', 2) . $r(24, 141, 210, 5, 's-line', 2)
                . $r(262, 150, 76, 13, 's-chip', 6) . $t(300, 159.5, 'Model ▾', 's-ink', 8, 'middle', 600)
                . $t(16, 186, 'Repositories', 's-mute', 8.5, 'start', 600) . $r(84, 177, 110, 14, 's-chip', 7) . $t(139, 187, '+ Add repository', 's-ink', 8, 'middle'));

        case 'environment':
            return $window('claude.ai/code/routines',
                '<rect x="0.5" y="22" width="359" height="177.5" class="s-dim"/>'
                . $r(40, 30, 280, 162, 's-dialog', 8)
                . $t(52, 48, 'New cloud environment', 's-ink', 11, 'start', 700)
                . $field(52, 66, 110, 'Name', 'Pinegrap')
                . $field(176, 66, 132, 'Network access', 'Custom ▾') . $ring(176, 66, 132, 20)
                . $field(52, 104, 256, 'Allowed domains', $host) . $ring(52, 104, 256, 20)
                . $t(52, 138, 'Environment variables', 's-mute', 8.5, 'start', 600)
                . $r(52, 142, 256, 24, 's-field', 4) . $t(59, 152, 'PINEGRAP_KEY=…', 's-ink', 8) . $t(59, 162, 'PINEGRAP_SECRET=…', 's-ink', 8)
                . $button(196, 171, 112, 'Create environment', true));

        case 'trigger':
            return $window('claude.ai/code/routines',
                $t(16, 44, 'Select a trigger', 's-ink', 11, 'start', 700)
                . $r(16, 52, 104, 30, 's-panel', 6) . $t(68, 71, 'Schedule', 's-ink', 9.5, 'middle')
                . $r(128, 52, 104, 30, 's-panel', 6) . $t(180, 71, 'GitHub event', 's-ink', 9.5, 'middle')
                . $r(240, 52, 104, 30, 's-acc-soft', 6) . $t(292, 71, 'API', 's-acc-ink', 10, 'middle', 700) . $ring(240, 52, 104, 30)
                . $t(16, 108, 'Connectors', 's-ink', 11, 'start', 700)
                . $r(16, 116, 84, 18, 's-chip', 9) . $t(26, 128.5, 'Slack', 's-ink', 9) . $t(90, 128.5, '×', 's-bad', 11, 'middle', 700)
                . $r(108, 116, 92, 18, 's-chip', 9) . $t(118, 128.5, 'Drive', 's-ink', 9) . $t(190, 128.5, '×', 's-bad', 11, 'middle', 700)
                . $ring(16, 116, 184, 18)
                . $button(284, 168, 60, 'Create', true));

        case 'token':
            return $window('claude.ai/code/routines',
                '<rect x="0.5" y="22" width="359" height="177.5" class="s-dim"/>'
                . $r(30, 34, 300, 150, 's-dialog', 8)
                . $t(42, 54, 'API trigger', 's-ink', 11, 'start', 700)
                . $field(42, 74, 276, 'URL', 'https://api.anthropic.com/v1/…/trig_…/fire') . $ring(42, 74, 276, 20)
                . $r(42, 104, 276, 26, 's-code', 4) . $t(50, 120, 'curl -X POST … -H "Authorization: Bearer …"', 's-mute', 8)
                . $button(42, 146, 96, 'Generate token', true) . $ring(42, 146, 96, 20)
                . $r(148, 146, 170, 20, 's-field', 4) . $t(155, 159.5, 'sk-ant-oat01-••••••••', 's-ink', 9)
                . $t(233, 178, lang('Shown once'), 's-warn', 8.5, 'middle', 600));

        case 'try':
            return $window($host . '/…/workspace.php',
                $t(16, 42, '# ' . lang('team'), 's-ink', 11, 'start', 700)
                . '<circle cx="26" cy="64" r="9" class="s-acc-soft"/>'
                . $r(42, 54, 200, 22, 's-panel', 8) . $t(50, 68.5, '@Claude ' . lang('What is waiting for me today?'), 's-ink', 9)
                . $r(42, 80, 30, 16, 's-chip', 8) . $t(57, 91.5, '👀', 's-ink', 9, 'middle')
                . $ring(42, 80, 30, 16)
                . '<circle cx="26" cy="120" r="9" class="s-claude"/>'
                . $t(42, 114, 'Claude', 's-ink', 9, 'start', 700)
                . $r(42, 119, 250, 44, 's-panel', 8) . $r(52, 129, 200, 5, 's-line', 2) . $r(52, 139, 170, 5, 's-line', 2) . $r(52, 149, 120, 5, 's-line', 2)
                . $r(16, 174, 328, 18, 's-field', 9) . $t(26, 186, lang('Write a message…'), 's-mute', 9));
    }

    return '';
}

/**
 * The steps, in the order they are done.
 *
 * @param string   $base   the panel's address, for the links
 * @param string   $host   this site's domain
 * @param string   $prompt the routine's prompt
 * @param callable $copy   ($id, $value, $rows): a read-only field with a copy button
 * @return string HTML: the items of an <ol>
 */
function ws_claude_setup_guide($base, $host, $prompt, $copy)
{
    $steps = array(
        array(
            'art'   => 'app',
            'title' => h(lang('An application for Claude')),
            'text'  => h(lang('In Application Access, create an application named Claude. Give it read and write on Workspace and on Tasks, and read on the records Claude may look at (orders, customers, products…). Copy its key and secret: they are shown once. Then choose it above.')),
            'extra' => '<a class="btn btn-sm btn-outline-secondary" href="' . h($base . 'api_settings.php') . '"><i class="bi bi-key me-1" aria-hidden="true"></i>' . h(lang('Application Access')) . '</a>',
        ),
        array(
            'art'   => 'routine',
            'title' => h(lang('A routine on claude.ai')),
            'text'  => ws_claude_setup_text(
                lang(array('string' => 'Open {var:1} and press {var:2}. Name it {var:3}, paste this prompt into the {var:4} box and choose a model in the box\'s selector. If the form asks for a repository, any small private repository will do; the routine does not change it.', 'vars' => ws_claude_setup_marks(4))),
                array('claude.ai/code/routines', 'New routine', 'Pinegrap Workspace', 'Instructions')
            ),
            'extra' => '<a class="btn btn-sm btn-outline-secondary mb-2" href="https://claude.ai/code/routines" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>claude.ai/code/routines</a>'
                . $copy('ws-claude-prompt', $prompt, 8),
        ),
        array(
            'art'   => 'environment',
            'title' => h(lang('The routine\'s environment')),
            'text'  => ws_claude_setup_text(
                lang(array('string' => 'Under the {var:1} box, press the cloud button that shows the environment\'s name ({var:2}) and choose {var:3}. Set {var:4} to {var:5}, write this site\'s domain in {var:6} and press {var:7}.', 'vars' => ws_claude_setup_marks(7))),
                array('Instructions', 'Default', 'Add cloud environment', 'Network access', 'Custom', 'Allowed domains', 'Create environment')
            ),
            'more'  => ws_claude_setup_text(
                lang(array('string' => 'The application\'s key and secret: on Team and Enterprise plans write them in {var:1} as two lines, PINEGRAP_KEY=… and PINEGRAP_SECRET=…; everybody who uses the environment can read them. On Pro and Max plans open the environment again with its gear instead and, under {var:2}, press {var:3}: {var:4} {var:5}, this site\'s domain in {var:6}, the key as the user name and the secret as the password, then {var:7}. Claude then works with them without ever seeing them.', 'vars' => ws_claude_setup_marks(7))),
                array('Environment variables', 'API credentials', 'Add credential', 'Credential type', 'Basic', 'Allowed websites', 'Connect')
            ),
            'extra' => $copy('ws-claude-host', $host),
        ),
        array(
            'art'   => 'trigger',
            'title' => h(lang('The API trigger')),
            'text'  => ws_claude_setup_text(
                lang(array('string' => 'Under {var:1} choose {var:2}. At the bottom, under {var:3}, remove every connector: the routine needs none, and a connector left there is one more thing it could use. Then press {var:4}.', 'vars' => ws_claude_setup_marks(4))),
                array('Select a trigger', 'API', 'Connectors', 'Create')
            ),
            'extra' => '',
        ),
        array(
            'art'   => 'token',
            'title' => h(lang('The address and the token')),
            'text'  => ws_claude_setup_text(
                lang(array('string' => 'Open the routine, choose {var:1} in the menu next to its name and open its {var:2} trigger. Copy the URL, press {var:3} and copy the token at once: it is shown only once. Enter both on this card and save.', 'vars' => ws_claude_setup_marks(3))),
                array('Edit', 'API', 'Generate token')
            ),
            'extra' => '',
        ),
        array(
            'art'   => 'try',
            'title' => h(lang('Try it')),
            'text'  => h(lang('Try the connection here, then write @Claude in a channel. The answer comes back under the request in a few minutes; the eye on the request means Claude has taken it.')),
            'extra' => '',
        ),
    );

    $html = '';

    foreach ($steps as $step) {
        $html .= '
            <li class="ws-claude-step">
                <div class="fw-semibold">' . $step['title'] . '</div>
                <div class="small text-body-secondary mb-2">' . $step['text'] . '</div>
                ' . (!empty($step['more']) ? '<div class="small text-body-secondary mb-2">' . $step['more'] . '</div>' : '') . '
                ' . ws_claude_setup_picture($step['art'], $host) . '
                ' . $step['extra'] . '
            </li>';
    }

    return $html;
}
