<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The mail pass: what a designed e-mail body goes through after the e-mail
 * render of its page, before it is stored as a campaign's body. Mail
 * programs drop linked style sheets and most read no class rules, so a
 * designed body carries its look inline; the pass takes out what would only
 * weigh (stylesheet links, the page's CSS, scripts), writes every
 * var(--x, fallback) as its fallback (no mail program knows the page's
 * custom properties), puts in one small head style for phones and says
 * whether the design draws its own footer and whether the result is over
 * the size Gmail clips at. Pure: no database, no files.
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
 * The size from which Gmail shows a mail cut, with "View entire message",
 * in bytes.
 *
 * @return int
 */
function pg_mail_pass_clip_bytes()
{
    return 102 * 1024;
}

/**
 * The style the pass puts in the head: on a phone a frame takes the whole
 * width, columns stack and a part marked for wide screens only is hidden.
 * Inline styles carry everything else, so a program that drops this style
 * still shows the design, only less fitted to a phone.
 *
 * @return string
 */
function pg_mail_pass_head_style()
{
    return '<style>'
        . '@media (max-width: 620px) {'
        . '.pg-mail-frame { width: 100% !important; max-width: 100% !important; }'
        . '.pg-mail-col { display: block !important; width: 100% !important; max-width: 100% !important; }'
        . '.pg-mail-wide-only { display: none !important; }'
        . '}'
        . '</style>';
}

/**
 * Every var(--name, fallback) written as its fallback, the innermost first,
 * so var(--a, var(--b, #123)) becomes #123. A var() without a fallback is
 * left as it is: there is nothing to write in its place.
 *
 * @param string $text
 * @return string
 */
function pg_mail_pass_var_fallbacks($text)
{
    $text = (string) $text;
    $pattern = '/var\(\s*--[A-Za-z0-9_-]+\s*,\s*((?:[^()]|\([^()]*\))*?)\s*\)/';

    for ($round = 0; $round < 5; $round++) {
        $next = preg_replace_callback($pattern, function ($m) {
            return trim($m[1]);
        }, $text);

        if (($next === null) || ($next === $text)) {
            break;
        }

        $text = $next;
    }

    return $text;
}

/**
 * The pass. The design marks its own footer block with data-pg-mail-footer;
 * the caller then leaves the fixed campaign footer out.
 *
 * @param string $html the e-mail render of a design (a whole document)
 * @return array html, has_footer, bytes, clipped (over pg_mail_pass_clip_bytes())
 */
function pg_mail_pass($html)
{
    $html = (string) $html;

    // What only weighs: scripts and style sheets of the page, linked or not.
    $html = (string) preg_replace('#<script\b[^>]*>.*?</script\s*>#is', '', $html);
    $html = (string) preg_replace('#<style\b[^>]*>.*?</style\s*>#is', '', $html);
    $html = (string) preg_replace('#<link\b[^>]*>#i', '', $html);

    $html = pg_mail_pass_var_fallbacks($html);

    if (preg_match('#</head\s*>#i', $html)) {
        $html = (string) preg_replace('#</head\s*>#i', pg_mail_pass_head_style() . '</head>', $html, 1);
    } elseif (preg_match('#<body\b#i', $html)) {
        $html = (string) preg_replace('#<body\b#i', '<head>' . pg_mail_pass_head_style() . '</head><body', $html, 1);
    } else {
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8">' . pg_mail_pass_head_style() . '</head><body>' . $html . '</body></html>';
    }

    // Blank lines the removals left.
    $html = (string) preg_replace("/\n\s*\n+/", "\n", $html);
    $bytes = strlen($html);

    return array(
        'html'       => $html,
        'has_footer' => (bool) preg_match('/\sdata-pg-mail-footer[\s=>]/i', $html),
        'bytes'      => $bytes,
        'clipped'    => $bytes > pg_mail_pass_clip_bytes(),
    );
}
