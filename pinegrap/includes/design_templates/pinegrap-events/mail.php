<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Events and Conferences", its e-mail builders: the page
 * an e-mail is drawn on, its heading, words, button, lines of details and
 * greeting, the card of the comment a reply e-mail is about, and the header
 * and footer every e-mail repeats. Included after parts.php in the same
 * scope.
 *
 * Every colour is written inline for mail programs that drop the
 * stylesheet; a theme colour keeps its fallback inside var().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$mail_style = function ($css) use ($attr) {
    return array('_attrs' => array($attr('style', $css)));
};
$mail_page = function ($children) use ($el, $shared, $mail_style) {
    return array('type' => 'root', 'props' => array('cssClass' => ''), 'children' => array(
        $el('div', 'bg-body-tertiary py-4 px-3', lang('E-mail Frame'), array(
            $el('div', 'mx-auto bg-body border rounded-3 overflow-hidden', lang('E-mail Card'), array(
                $shared('email_header'),
                $el('div', 'p-4', lang('E-mail Body'), $children, $mail_style('padding:24px')),
                $shared('email_footer'),
            ), $mail_style('max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #e0e7ff;border-radius:10px;overflow:hidden')),
        ), $mail_style('background:#eef2ff;padding:24px 12px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#1e1b4b')),
    ));
};
$mail_heading = function ($text) use ($heading, $attr) {
    return $heading('h1', $text, 'h4 fw-bold mb-3', array('_attrs' => array($attr('style', 'font-size:22px;font-weight:700;margin:0 0 16px;color:#1e1b4b'))));
};
$mail_para = function ($text, $class = 'mb-3', $style = 'margin:0 0 16px') use ($para, $attr) {
    return $para($text, $class, array('_attrs' => array($attr('style', $style))));
};
$mail_button = function ($text, $href, $extra = array()) use ($el, $link, $mail_style, $attr) {
    return $el('p', 'my-4', lang('Button'), array(
        $link($text, $href, 'btn btn-primary px-4', array($attr('style',
            'display:inline-block;padding:10px 22px;border-radius:6px;font-weight:600;text-decoration:none;'
            . 'background-color:#4338ca;background-color:var(--bs-primary,#4338ca);color:#ffffff;color:var(--pg-on-primary,#ffffff)')), $extra),
    ), $mail_style('margin:24px 0'));
};
// A line of details: the label, then the value; the line leaves when the
// value is empty.
$mail_line = function ($label, $values) use ($el, $span, $mail_style, $attr) {
    return $el('p', 'pg-hide-if-empty mb-1', lang('Line'), array(
        $span($label . ': ', 'text-body-secondary', lang('Label'), array('_attrs' => array($attr('style', 'color:#64748b')))),
        $el('span', 'pg-field-value fw-semibold', lang('Value'), $values),
    ), $mail_style('margin:0 0 4px'));
};
$mail_box = function ($children, $name) use ($el, $mail_style) {
    return $el('div', 'bg-body-tertiary rounded-3 p-3 my-3', $name, $children,
        $mail_style('background:#eef2ff;border-radius:8px;padding:16px;margin:16px 0'));
};
$mail_greeting = function () use ($el, $span, $bind, $mail_style) {
    return $el('p', 'mb-3', lang('Greeting'), array(
        $span(lang('Hello') . ' ', '', lang('Text')),
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
    ), $mail_style('margin:0 0 16px'));
};

// The e-mail pages' settings: a private folder, out of the search and the
// sitemap.
$mail_settings = array('folder' => 'private', 'search' => false, 'sitemap' => false, 'noindex' => true);

// The header and footer of every e-mail, each one shared component.
$email_header_tree = function () use ($el, $link, $attr, $mail_style) {
    return $el('div', 'px-4 py-3 border-bottom', lang('E-mail Header'), array(
        $link('{{site_name}}', '{{page:home}}', 'fs-5 fw-semibold text-decoration-none link-body-emphasis',
            array($attr('style', 'font-size:20px;font-weight:600;color:#4338ca;text-decoration:none'))),
    ), $mail_style('padding:16px 24px;border-bottom:1px solid #e0e7ff'));
};
$email_footer_tree = function () use ($el, $para, $link, $attr, $mail_style, $venue) {
    return $el('div', 'px-4 py-3 border-top small text-body-secondary', lang('E-mail Footer'), array(
        $para('© {{year}} {{site_name}}', 'mb-1', array('_attrs' => array($attr('style', 'margin:0 0 4px')))),
        $para($venue['address'], 'mb-1', array('_attrs' => array($attr('style', 'margin:0 0 4px')))),
        $link(lang('Visit our website'), '{{page:home}}', 'link-secondary', array($attr('style', 'color:#64748b'))),
    ), $mail_style('padding:16px 24px;border-top:1px solid #e0e7ff;font-size:13px;color:#64748b'));
};

// The comment an e-mail is about, in a box: who wrote it and when, its
// stars, the comment it answers, its words and its file, then its View or
// Reply button. The button is bound to the comment's own address, with no
// flag over it: an e-mail whose card has no such link keeps the fixed
// comment the System region carries. No form, no control; every colour
// written for mail programs that drop the stylesheet, with its fallback
// inside var().
$mail_comment_tree = $widget_root(array(
    $el('div', '', lang('Comment List'), array(
        $loop(array(
            $mail_box(array(
                $el('p', 'mb-1', lang('Name Line'), array(
                    $span(lang('Jane Cooper'), 'fw-semibold', lang('Name'), array_merge($bind('__comment_name'), $mail_style('font-weight:600;color:#1e1b4b'))),
                    $span('·', '', lang('Separator'), array('_attrs' => array($attr('aria-hidden', 'true'), $attr('style', 'color:#64748b;margin:0 6px')))),
                    $span(lang('Date'), 'text-body-secondary', lang('Date'), array_merge($bind('__comment_date'), $mail_style('color:#64748b'))),
                ), $mail_style('margin:0 0 8px')),
                $el('div', '', lang('Stars'), pg_sw_part_comment_stars(), array_merge(
                    $show_when('comment_has_rating'), $mail_style('color:#b45309;margin:0 0 8px'))),
                $el('blockquote', '', lang('Quote'), array(
                    $el('p', 'small mb-1', lang('In reply to'), array(
                        $span(lang('In reply to'), 'me-1', lang('Label'), $mail_style('margin-right:4px')),
                        $span(lang('Jane Cooper'), 'fw-semibold', lang('Name'), $bind('__comment_parent_name')),
                    ), $mail_style('font-size:13px;margin:0 0 4px')),
                    $el('p', 'mb-0 pg-hide-if-empty', lang('Quoted text'), array(), array_merge(
                        array('text' => lang('Quoted text'), '_bindings' => array('text' => '__comment_quote')), $mail_style('margin:0'))),
                ), array_merge($show_when('comment_has_quote'),
                    $mail_style('margin:0 0 12px;padding:0 0 0 12px;border-left:3px solid #e0e7ff;color:#64748b'))),
                $el('div', '', lang('Message'), array(), array('text' => lang('Comment text'), '_bindings' => array('text' => '__comment_message_html'))),
                $el('p', 'small mt-2 mb-0', lang('Attachment'), array(
                    $link('file.pdf', '#', '', array($attr('style', 'color:#4338ca;color:var(--bs-link-color,#4338ca)')),
                        array('target' => '_blank', 'rel' => 'noopener', '_bindings' => array('href' => '__comment_file_url', 'text' => '__comment_file_name'))),
                ), array_merge($show_when('comment_has_file'), $mail_style('font-size:13px;margin:8px 0 0'))),
            ), lang('Comment')),
            $mail_button(lang('View or Reply'), '#', array('_bindings' => array('href' => '__comment_url'))),
        )),
    )),
));
