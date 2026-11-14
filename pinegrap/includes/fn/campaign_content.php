<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The content part of an e-mail campaign (New campaign, Edit Campaign): the
 * subject, then the body as a design (a page drawn as an e-mail), HTML
 * pasted from another tool, or plain text, with a wide and a phone preview
 * of what will be sent. Drawn as the Communication area's drawing has it:
 * the subject and the mail on the left, how the body is made on the right.
 *
 * The body kind is chosen with radio buttons that come before the card's
 * grid, so CSS alone shows the chosen kind's parts and raises its segment;
 * the phone preview is a checkbox the same way. Nothing needs a script but
 * the design's frame, which follows the chosen page.
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
 * The body kinds in the segmented control's order, with their labels.
 *
 * @return array kind => English label
 */
function pg_campaign_content_kinds()
{
    return array(
        'design' => 'Design',
        'html'   => 'Paste HTML',
        'text'   => 'Plain text',
    );
}

/**
 * A stored campaign's body kind: plain text, a body drawn from a page, or
 * HTML stored without a page (pasted).
 *
 * @param string     $format  plain_text or html
 * @param int|string $page_id
 * @return string design, html or text
 */
function pg_campaign_content_kind($format, $page_id)
{
    if ((string) $format !== 'html') {
        return 'text';
    }

    return ((int) $page_id > 0) ? 'design' : 'html';
}

/**
 * The kind a form posted, or null when it is not one of the kinds.
 *
 * @param array $post
 * @return string|null
 */
function pg_campaign_content_posted_kind($post)
{
    $kind = (isset($post['body_kind']) && is_string($post['body_kind'])) ? $post['body_kind'] : '';

    return array_key_exists($kind, pg_campaign_content_kinds()) ? $kind : null;
}

/**
 * The format column's value for a kind.
 *
 * @param string $kind
 * @return string html or plain_text
 */
function pg_campaign_content_format($kind)
{
    return ($kind === 'text') ? 'plain_text' : 'html';
}

/**
 * A stored HTML body without the fixed campaign footer
 * (div.software_email_footer, which holds no other div), so a pasted body
 * edited again gets it once.
 *
 * @param string $html
 * @return string
 */
function pg_campaign_content_without_footer($html)
{
    return (string) preg_replace('#<div class="software_email_footer"[^>]*>.*?</div>#is', '', (string) $html);
}

/**
 * Whether the design was changed after the body was taken from it.
 *
 * @param int $page_timestamp   the page's last change
 * @param int $stored_timestamp the campaign's last save
 * @return bool
 */
function pg_campaign_content_design_changed($page_timestamp, $stored_timestamp)
{
    return ((int) $page_timestamp > 0) && ((int) $page_timestamp > (int) $stored_timestamp);
}

/**
 * The preview: a frame of the mail, on the phone preview 375 px wide.
 *
 * @param string $src   the frame's address ('' for none yet)
 * @param string $empty what shows while there is nothing to preview
 * @return string
 */
function pg_campaign_content_preview($src, $empty)
{
    return '<div class="pg-campaign-preview">'
        . '<iframe class="pg-campaign-frame" id="body_preview_iframe" title="' . h(lang('Body Preview')) . '" data-base="' . h(defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . '" src="' . h(($src !== '') ? $src : 'about:blank') . '"' . (($src === '') ? ' hidden' : '') . '></iframe>'
        . '<p class="pg-campaign-preview-empty pg-text-2"' . (($src !== '') ? ' hidden' : '') . '>' . h($empty) . '</p>'
        . '</div>';
}

/**
 * The content card.
 *
 * Options:
 *   mode        add (the kind is chosen), edit (the kind is the stored
 *               one) or read (a completed campaign: nothing is a field)
 *   kind        design, html or text
 *   subject     the subject
 *   page_field  the page picker field of the design (add; kind page_view,
 *               posted as page_id)
 *   page_name   the design's page name (edit, read)
 *   edit_url    where the design is edited ('' when the user may not)
 *   html        the pasted HTML (html kind)
 *   text        the plain text (text kind)
 *   preview     the preview frame's address ('' for none yet)
 *   changed     the design changed after the body was stored (edit)
 *
 * @param array $o
 * @return string
 */
function pg_campaign_content_card($o)
{
    $o += array('mode' => 'add', 'kind' => 'design', 'subject' => '', 'page_field' => '', 'page_name' => '', 'edit_url' => '', 'html' => '', 'text' => '', 'preview' => '', 'changed' => false, 'new_design' => false);
    $mode = $o['mode'];
    $kinds = pg_campaign_content_kinds();
    $kind = array_key_exists($o['kind'], $kinds) ? $o['kind'] : 'design';
    $read = ($mode === 'read');

    // The kinds offered: all three on add, the stored one after.
    $offered = ($mode === 'add') ? array_keys($kinds) : array($kind);

    $radios = '';
    $segments = '';

    foreach ($offered as $key) {
        $radios .= '<input type="radio" class="pg-campaign-kind" name="body_kind" value="' . $key . '" id="body_kind_' . $key . '"' . (($key === $kind) ? ' checked' : '') . '>';
        $segments .= '<label class="pg-seg-item" for="body_kind_' . $key . '">' . h(lang($kinds[$key])) . '</label>';
    }

    $subject = $read
        ? '<p class="pg-text-2 mb-0">' . h($o['subject']) . '</p>'
        : '<input class="pg-input" type="text" name="subject" id="subject" maxlength="255" required value="' . h($o['subject']) . '" placeholder="' . h(lang('Subject')) . '">';

    // Left: the subject, then the mail as it will look (design), or the
    // field the body is written in (pasted HTML, plain text).
    $main = '<div class="pg-campaign-field">'
        . ($read ? '<div class="pg-field-label">' . h(lang('Subject')) . '</div>' : '<label class="pg-field-label" for="subject">' . h(lang('Subject')) . '</label>')
        . $subject . '</div>';

    if (in_array('design', $offered, true)) {
        $main .= '<div class="pg-campaign-part is-design"><div class="pg-field-label">' . h(lang('E-mail')) . '</div>'
            . pg_campaign_content_preview(($kind === 'design') ? $o['preview'] : '', lang('Choose the page the e-mail is drawn from.')) . '</div>';
    }

    if (in_array('html', $offered, true)) {
        $main .= '<div class="pg-campaign-part is-html">'
            . '<label class="pg-field-label" for="html_body">' . h(lang('HTML')) . '</label>'
            . '<textarea class="pg-input pg-campaign-code" name="html_body" id="html_body" spellcheck="false"' . ($read ? ' readonly' : '') . ' placeholder="' . h(lang('Paste the e-mail\'s HTML here')) . '">' . h($o['html']) . '</textarea>'
            . ((($mode !== 'add') && ($o['preview'] !== '')) ? '<div class="pg-field-label mt-4">' . h(lang('E-mail')) . '</div>' . pg_campaign_content_preview($o['preview'], '') : '')
            . '</div>';
    }

    if (in_array('text', $offered, true)) {
        $main .= '<div class="pg-campaign-part is-text">'
            . '<label class="pg-field-label" for="email_body">' . h(lang('Body')) . '</label>'
            . '<textarea class="pg-input pg-campaign-text" name="body" id="email_body"' . ($read ? ' readonly' : '') . ' placeholder="' . h(lang('Type E-mail body here')) . '...">' . h($o['text']) . '</textarea>'
            . '</div>';
    }

    // Right: how the body is made.
    $side = '<div class="pg-field-label">' . h(lang('Body')) . '</div>'
        . (($mode === 'add') ? '<div class="pg-seg pg-campaign-kinds" role="radiogroup" aria-label="' . h(lang('Body')) . '">' . $segments . '</div>' : '<p class="pg-text-2 mb-3">' . h(lang($kinds[$kind])) . '</p>');

    if (in_array('design', $offered, true)) {
        $side .= '<div class="pg-campaign-part is-design">';

        if ($mode === 'add') {
            $side .= '<label class="pg-field-label" for="page_id">' . h(lang('Page')) . '</label>' . $o['page_field'];

            // A new e-mail design opens in the visual editor beside this
            // screen; once saved it is picked here like any page.
            if ($o['new_design']) {
                $side .= '<a class="pg-button pg-button-quiet pg-button-sm pg-campaign-new-design" target="_blank" rel="noopener" href="add_system_style.php?start=email"><i class="bi bi-envelope-plus" aria-hidden="true"></i>' . h(lang('New e-mail design')) . '</a>';
            }
        } else {
            $side .= '<p class="pg-text-2 mb-0"><span class="pg-text-meta">' . h(lang('Page')) . '</span><br>' . h($o['page_name']) . '</p>';
        }

        $side .= '<a class="pg-button pg-button-fill pg-campaign-edit" id="body_design_edit" target="_blank" rel="noopener" href="' . h(($o['edit_url'] !== '') ? $o['edit_url'] : '#') . '"' . (($o['edit_url'] === '') ? ' hidden' : '') . '><i class="bi bi-brush" aria-hidden="true"></i>' . h(lang('Edit the design')) . '</a>';

        if ($o['changed'] && ($mode === 'edit')) {
            $side .= '<div class="pg-campaign-changed" role="status"><span class="pg-text-2">' . h(lang('The design was changed after this e-mail was stored. The stored e-mail is what will be sent.')) . '</span>'
                . '<button type="submit" class="pg-button pg-button-quiet pg-button-sm" name="take_design" value="1"><i class="bi bi-arrow-repeat" aria-hidden="true"></i>' . h(lang('Take the design again')) . '</button></div>';
        }

        $side .= '</div>';
    }

    if (in_array('html', $offered, true)) {
        $side .= '<p class="pg-campaign-part is-html pg-campaign-hint">' . h(lang('Cleaned when saved: scripts, forms, frames and outside style sheets are taken out; inline styles, phone rules and https pictures stay.')) . '</p>';
    }

    if (in_array('text', $offered, true)) {
        $side .= '<p class="pg-campaign-part is-text pg-campaign-hint">' . h(lang('Sent as plain text, without pictures or links that are not written out.')) . '</p>';
    }

    // The phone preview: the frame narrows to a phone's width. On create
    // only a design has a frame; after it every stored HTML body has one.
    $phone = '<label class="pg-button pg-button-quiet pg-campaign-phone" for="body_preview_phone"><i class="bi bi-phone" aria-hidden="true"></i>' . h(lang('Preview on a phone')) . '</label>';

    if ($mode === 'add') {
        $side .= '<div class="pg-campaign-part is-design">' . $phone . '</div>';
    } elseif ($o['preview'] !== '') {
        $side .= $phone;
    }

    if (in_array('design', $offered, true)) {
        $side .= '<p class="pg-campaign-part is-design pg-campaign-hint">' . h(lang('The page is drawn as an e-mail when the campaign is saved; what is saved is what will be sent.')) . '</p>';
    }

    return '<div class="col-12"><section class="pg-card pg-campaign-content mb-7" data-kind="' . h($kind) . '">'
        . '<h2 class="pg-title-section">' . h(lang('Content')) . '</h2>'
        . $radios
        . '<input type="checkbox" class="pg-campaign-phone-switch" id="body_preview_phone">'
        . '<div class="pg-campaign-grid"><div class="pg-campaign-main">' . $main . '</div><div class="pg-campaign-side">' . $side . '</div></div>'
        . '</section></div>';
}

/**
 * The script of the add screen's design part: the frame and "Edit the
 * design" follow the page picked in the picker field. The field's hidden
 * input (data-pg-campaign-page) holds the page id and fires change; its
 * text input (id page_id) shows the page's name, which the frame's address
 * is made of, as the campaign profile's preview does. edit_page.php sends a
 * designer page on to the visual editor and refuses a page the user may not
 * edit.
 *
 * @return string
 */
function pg_campaign_content_script()
{
    return '<script>
    (function () {
        document.addEventListener("change", function (event) {
            var input = event.target;

            if (!input || !input.matches || !input.matches("input[data-pg-campaign-page]")) {
                return;
            }

            var frame = document.getElementById("body_preview_iframe");
            var name = document.getElementById("page_id");
            var edit = document.getElementById("body_design_edit");
            var picked = (input.value !== "") && name && (name.value !== "");
            var empty = frame ? frame.parentNode.querySelector(".pg-campaign-preview-empty") : null;

            if (frame) {
                frame.src = picked ? frame.getAttribute("data-base") + encodeURIComponent(name.value) + "?edit=no&email=true" : "about:blank";
                frame.hidden = !picked;
            }

            if (empty) {
                empty.hidden = picked;
            }

            if (edit) {
                edit.href = picked ? "edit_page.php?id=" + encodeURIComponent(input.value) : "#";
                edit.hidden = !picked;
            }
        });
    })();
    </script>';
}
