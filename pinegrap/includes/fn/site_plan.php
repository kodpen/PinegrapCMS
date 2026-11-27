<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The plan of pages of the "Describe it" start of the New design window:
 * one Pinegrap AI call turns the brief (includes/fn/site_brief.php) into
 * the site's pages (name, address, purpose, sections in words, place in
 * the menu) and its theme as the answers to the design variables' five
 * questions. The answer is checked and cut here; the person approves the
 * plan in the browser before anything is made. Nothing is stored on the
 * server.
 *
 * The request counts as one draft of "Write with AI" and keeps the pace of
 * the Sketch tool's AI actions (includes/fn/sketch_ai.php), which the run
 * that follows the plan uses page after page.
 *
 * Memory: docs/memory/designer/site-plan-request.md
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/site_brief.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/sketch_ai.php');

// Pages one plan may hold: as many as a brief may ask for.
define('PG_SITE_PLAN_PAGES', 12);
// Sections one page of the plan may describe.
define('PG_SITE_PLAN_SECTIONS', 10);

/**
 * The longest text of each part of a plan's page, in characters. A page's
 * name takes what a brief's page takes; a section's words are what one
 * band of "Describe the page" is told. Pure.
 *
 * @return array name, purpose, section, address
 */
function pg_site_plan_limits()
{
    return array('name' => 40, 'purpose' => 200, 'section' => 160, 'address' => 60);
}

/**
 * The places a page may take in the menu, the first the default. Pure.
 *
 * @return string[]
 */
function pg_site_plan_menus()
{
    return array('main', 'footer', 'none');
}

/**
 * A page's address: lower case letters, digits and hyphens, Turkish letters
 * and the common accents folded, at most 60 characters; 'page' when
 * nothing is left. Pure.
 *
 * @param mixed $text
 * @return string
 */
function pg_site_plan_address($text)
{
    $text = is_scalar($text) ? mb_strtolower(trim((string) $text), 'UTF-8') : '';
    $text = strtr($text, array(
        'ı' => 'i', 'ğ' => 'g', 'ü' => 'u', 'ş' => 's', 'ö' => 'o', 'ç' => 'c',
        'â' => 'a', 'î' => 'i', 'û' => 'u', 'á' => 'a', 'à' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'í' => 'i', 'ó' => 'o',
        'ú' => 'u', 'ñ' => 'n', 'ß' => 'ss',
    ));
    // The dotted capital I lowers to "i" with a combining dot.
    $text = str_replace("i\xCC\x87", 'i', $text);
    $text = trim((string) preg_replace('/[^a-z0-9]+/', '-', $text), '-');
    $text = rtrim(substr($text, 0, pg_site_plan_limits()['address']), '-');

    return ($text === '') ? 'page' : $text;
}

/**
 * The pages of a plan's answer, checked and cut: a page needs a name and
 * one section at least; its address comes from the answer or else the
 * name and is never used twice (a second one gets -2, -3, …); an unknown
 * menu place is the main menu. At most PG_SITE_PLAN_PAGES pages of
 * PG_SITE_PLAN_SECTIONS sections each. Pure.
 *
 * @param mixed $answer {"pages": [{name, address, purpose, sections, menu}]}
 * @return array
 */
function pg_site_plan_pages($answer)
{
    $limits = pg_site_plan_limits();
    $menus = pg_site_plan_menus();
    $out = array();
    $taken = array();

    foreach ((is_array($answer) && isset($answer['pages']) && is_array($answer['pages'])) ? $answer['pages'] : array() as $p) {
        if (count($out) >= PG_SITE_PLAN_PAGES) {
            break;
        }

        if (!is_array($p)) {
            continue;
        }

        $name = pg_sketch_ai_plain(isset($p['name']) ? $p['name'] : '', $limits['name']);
        $sections = array();

        foreach ((isset($p['sections']) && is_array($p['sections'])) ? $p['sections'] : array() as $words) {
            $words = pg_sketch_ai_plain($words, $limits['section']);

            if ($words !== '' && count($sections) < PG_SITE_PLAN_SECTIONS) {
                $sections[] = $words;
            }
        }

        if ($name === '' || !$sections) {
            continue;
        }

        $address = pg_site_plan_address((isset($p['address']) && is_scalar($p['address']) && trim((string) $p['address']) !== '') ? $p['address'] : $name);
        $base = $address;

        for ($n = 2; isset($taken[$address]); $n++) {
            $address = $base . '-' . $n;
        }

        $taken[$address] = true;
        $menu = (isset($p['menu']) && is_string($p['menu']) && in_array($p['menu'], $menus, true)) ? $p['menu'] : $menus[0];

        $out[] = array(
            'name'     => $name,
            'address'  => $address,
            'purpose'  => pg_sketch_ai_plain(isset($p['purpose']) ? $p['purpose'] : '', $limits['purpose']),
            'sections' => $sections,
            'menu'     => $menu,
        );
    }

    return $out;
}

/**
 * The plan's theme as the answers to the design variables' five questions
 * (pg_design_variables_answers()): an unknown choice is skipped (''). The
 * brief's brand colour, when given, wins over the model's. Pure.
 *
 * @param mixed  $answer {"theme": {brand, accent, look, type, room}}
 * @param string $colour the brief's brand colour, '' for none
 * @return array brand, accent, look, type, room
 */
function pg_site_plan_theme($answer, $colour)
{
    $theme = (is_array($answer) && isset($answer['theme']) && is_array($answer['theme'])) ? $answer['theme'] : array();

    if ((string) $colour !== '') {
        $theme['brand'] = (string) $colour;
    }

    return pg_design_variables_answers($theme);
}

/**
 * The drafts of "Write with AI" a run of the plan takes: one a page for its
 * sketch ("Describe the page") and one a section for its texts. The plan's
 * own request is already counted. Pure.
 *
 * @param array $pages pg_site_plan_pages()
 * @return int
 */
function pg_site_plan_cost($pages)
{
    $cost = 0;

    foreach (is_array($pages) ? $pages : array() as $p) {
        $cost += 1 + ((isset($p['sections']) && is_array($p['sections'])) ? count($p['sections']) : 0);
    }

    return $cost;
}

/**
 * The request's brief, checked as the pane checks it (pg_site_brief_check()):
 * error '' when it can be asked, else the words of its first mistake. The
 * brief may come as an array or as JSON.
 *
 * @param array $request brief
 * @return array brief, error
 */
function pg_site_plan_input($request)
{
    $raw = (is_array($request) && isset($request['brief'])) ? $request['brief'] : null;

    if (is_string($raw)) {
        $raw = json_decode($raw, true);
    }

    if (!is_array($raw)) {
        return array('brief' => array(), 'error' => lang('Invalid request.'));
    }

    $check = pg_site_brief_check($raw);

    return array('brief' => $check['brief'], 'error' => $check['errors'] ? (string) reset($check['errors']) : '');
}

/**
 * The model's messages for a checked brief.
 *
 * @param array  $brief    pg_site_brief_check()'s brief
 * @param string $language the pages' language, by name
 * @return array
 */
function pg_site_plan_messages($brief, $language)
{
    $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    $q = pg_design_variables_questions();
    $limits = pg_site_plan_limits();

    $rules = array(
        'You plan a small website from its owner\'s brief, the way a web designer plans the pages before drawing them.',
        'Give one page for each page in the brief\'s list, in that order, and no page the owner did not ask for. A page name is in ' . $language . ', at most ' . $limits['name'] . ' characters.',
        'address is the page\'s web address: lower case Latin letters, digits and hyphens, from its name.',
        'purpose is one sentence in ' . $language . ' on what the page is for and who reads it.',
        'sections are the page\'s bands from top to bottom after the header and before the footer, 3 to 8 of them, each one sentence in ' . $language . ' that says what the band shows, for example an intro with a button, three cards, a text beside a picture, a gallery, a contact form, a call to action.',
        'menu is "main" for a page in the main menu, "footer" for a page only linked in the footer, "none" for neither.',
        'Write for the business in the brief, in its tone. Never invent prices, discounts, specifications, delivery times, guarantees, names of people or contact details.',
        'theme answers five questions: brand is the brand colour as #rrggbb; accent is a second colour as #rrggbb or "auto"; look is one of ' . implode(', ', array_keys(pg_design_looks())) . '; type is one of ' . implode(', ', array_keys($q['types'])) . '; room is one of ' . implode(', ', array_keys($q['rooms'])) . '.',
    );
    $rules = array_merge($rules, pg_ai_prompt_rules('writer', ''));
    $rules[] = 'Answer only with JSON: {"pages": [{"name": "", "address": "", "purpose": "", "sections": [""], "menu": "main"}], "theme": {"brand": "#000000", "accent": "auto", "look": "", "type": "", "room": ""}}.';

    $data = array(
        'name'      => $brief['name'],
        'what'      => $brief['what'],
        'kind'      => $brief['kind'],
        'customers' => $brief['customers'],
        'tone'      => $brief['tone'],
        'pages'     => $brief['pages'],
        'colour'    => $brief['colour'],
    );

    return array(
        array('role' => 'system', 'content' => implode("\n", $rules)),
        array('role' => 'user', 'content' => (string) json_encode($data, $flags)),
    );
}

/**
 * The New design window's request (designer/site_plan): the plan of pages
 * for a brief, with the day's drafts: cap, left after this request, and
 * run, what making the plan would take. off is true when Pinegrap AI is
 * not there, so the window can show the start disabled.
 *
 * @param array $request brief
 * @param array $user    validate_user()
 * @return array the JSON answer
 */
function pg_site_plan_request($request, $user)
{
    $fail = function ($message, $off = false) {
        return array('status' => 'error', 'message' => $message, 'off' => $off);
    };

    $problem = pg_ai_write_problem();

    if ($problem !== '') {
        return $fail(lang('Pinegrap AI is off.') . ' ' . $problem, true);
    }

    $in = pg_site_plan_input($request);

    if ($in['error'] !== '') {
        return $fail($in['error']);
    }

    $state = pg_ai_state();

    if (!$state['ready']) {
        return $fail($state['reason']);
    }

    // The Sketch tool's pace: the run that follows uses those requests.
    if (function_exists('waf_rate_exceeded') && waf_rate_exceeded('sketch_ai|' . (int) $user['id'], 'sketch_ai', PG_SKETCH_AI_PER_MINUTE, 60)) {
        return $fail(lang('You are asking for drafts too quickly. Please wait a moment.'));
    }

    $cap = pg_ai_write_daily_cap();
    $take = pg_ai_write_usage_take($cap);

    if (!$take['taken']) {
        return $fail(lang(array('string' => 'This site has used its {var:1} drafts for today. Writing with AI is open again tomorrow.', 'vars' => $cap)));
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    if (function_exists('set_time_limit') && function_exists('ws_ai_call_timeout')) {
        @set_time_limit(ws_ai_call_timeout() + 30);
    }

    $reply = pg_ai_chat(pg_site_plan_messages($in['brief'], pg_ai_write_site_language()['name']), array(
        'temperature' => 0.5,
        'max_tokens'  => 4000,
    ));

    if (!$reply['ok']) {
        return $fail($reply['error']);
    }

    $answer = pg_sketch_ai_json($reply['text']);
    $pages = pg_site_plan_pages($answer);

    if (!$pages) {
        return $fail(lang('The plan of pages could not be made. Please try again.'));
    }

    return array(
        'status' => 'success',
        'plan'   => array('pages' => $pages, 'theme' => pg_site_plan_theme($answer, $in['brief']['colour'])),
        'drafts' => array(
            'cap'  => $cap,
            'left' => max(0, $cap - (int) $take['count']),
            'run'  => pg_site_plan_cost($pages),
        ),
    );
}
