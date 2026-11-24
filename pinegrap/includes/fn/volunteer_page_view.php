<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The markup of a volunteer's own shifts (pg_volunteer_self_view()): each
 * programme with "My next shifts" and their Cancel, Offer my place or Keep
 * my place, "Shifts for you" with Sign up, Join the waitlist or Take this
 * place, and the documents' dates. Drawn twice: in the site's look
 * (Bootstrap 5.3.8, the classes of widgets_volunteering.php) for the
 * my_account widget's "volunteering" section, and in look A for the signed
 * page volunteer.php. Every button is a small POST
 * form to volunteer.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_signup.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_board_view.php');

/**
 * The classes of look A (the signed page). Pure.
 *
 * @return array
 */
function pg_volunteer_page_secure_classes()
{
    return array(
        'box'    => 'pg-card mb-7',
        'body'   => 'p-7',
        'list'   => 'pg-card-inner mb-5',
        'item'   => 'pg-card-inner-row d-flex flex-wrap justify-content-between align-items-center gap-3',
        'meta'   => 'small fg-secondary',
        'pill'   => 'pg-pill pg-tone-',
        'tones'  => array('ok' => 'teal', 'wait' => 'amber', 'info' => 'blue', 'off' => 'gray'),
        'quiet'  => 'pg-button pg-button-sm pg-button-quiet',
        'fill'   => 'pg-button pg-button-sm pg-button-fill',
        'danger' => 'pg-button pg-button-sm pg-button-danger',
        'check'  => 'd-flex align-items-center gap-3 mb-0',
        'switch' => 'pg-switch',
        'label'  => '',
        'select' => 'pg-input w-auto',
    );
}

/**
 * A sign-up's state in the volunteer's words, with its tone. Pure.
 *
 * @param array $signup state, offered_at, swap_from; asked
 * @return array label, tone (ok, wait, info)
 */
function pg_volunteer_signup_label(array $signup)
{
    if ((string) $signup['state'] === 'waiting') {
        return ((int) $signup['swap_from'] > 0) ? array(lang('Waiting for approval'), 'info') : array(lang('On the waitlist'), 'wait');
    }

    if ((int) $signup['offered_at'] > 0) {
        return !empty($signup['asked']) ? array(lang('Someone asked to take it'), 'info') : array(lang('Place offered'), 'wait');
    }

    return array(lang('Signed up'), 'ok');
}

/**
 * One button as its own POST form to volunteer.php.
 *
 * @param array  $form    action, hidden (the fields every form carries)
 * @param string $class
 * @param string $label
 * @param array  $fields  name => value
 * @param string $confirm a question asked before sending, '' for none
 * @return string HTML
 */
function _pg_volunteer_page_button(array $form, $class, $label, array $fields, $confirm = '')
{
    $html = '<form method="post" action="' . h($form['action']) . '" class="d-inline">' . $form['hidden'];

    foreach ($fields as $name => $value) {
        $html .= '<input type="hidden" name="' . h($name) . '" value="' . h((string) $value) . '">';
    }

    if ($confirm === '') {
        return $html . '<button type="submit" class="' . $class . '">' . h($label) . '</button></form>';
    }

    // Asked twice without a script: the button that sends shows only once
    // the volunteer opened this.
    return '<details><summary class="' . $class . '">' . h($label) . '</summary>' . $html
        . '<p class="small my-2">' . h($confirm) . '</p><button type="submit" class="' . $class . '">' . h(lang('Yes, cancel')) . '</button></form></details>';
}

/**
 * The volunteer's programmes with their shifts and documents.
 *
 * @param array $views   pg_volunteer_self_view() each
 * @param array $form    action, hidden
 * @param array $classes pg_volunteer_page_secure_classes() or the site's
 *                       (pg_volunteer_page_site_classes())
 * @return string HTML, '' without a programme
 */
function pg_volunteer_page_html(array $views, array $form, array $classes)
{
    $c = $classes;
    $pill = function ($label, $tone) use ($c) {
        return '<span class="' . $c['pill'] . $c['tones'][$tone] . '">' . h($label) . '</span>';
    };
    $html = '';

    foreach ($views as $view) {
        $programme = $view['programme'];
        $volunteer = $view['volunteer'];
        $profile = array('p' => (int) $volunteer['id']);
        $state_note = array(
            'applied' => lang('Your application is being reviewed. You can sign up for shifts once it is approved.'),
            'paused'  => lang('Your volunteering is paused. Ask the coordinator to make it active again.'),
        );
        $html .= '<section class="' . $c['box'] . '"><div class="' . $c['body'] . '">'
            . '<h2 class="h5 mb-3">' . h($programme['name']) . '</h2>';

        if (isset($state_note[$volunteer['state']])) {
            $html .= '<p class="' . $c['meta'] . '">' . h($state_note[$volunteer['state']]) . '</p>';
        } elseif ($programme['state'] === 'paused') {
            $html .= '<p class="' . $c['meta'] . '">' . h(lang('This programme takes no new sign-ups for now.')) . '</p>';
        }

        $html .= '<h3 class="h6 mb-2">' . h(lang('My next shifts')) . '</h3>';
        $rows = '';

        foreach ($view['mine'] as $signup) {
            $label = pg_volunteer_signup_label($signup);
            $fields = $profile + array('shift' => (int) $signup['shift_id']);
            $buttons = '';

            foreach ($signup['actions'] as $action) {
                if ($action === 'cancel') {
                    $buttons .= _pg_volunteer_page_button($form, $c['danger'], ($signup['state'] === 'waiting') ? lang('Leave the waitlist') : lang('Cancel'), $fields + array('action' => 'cancel'),
                        ($signup['state'] === 'signed') ? lang('Cancel this shift? Your place goes to the next volunteer.') : '');
                } elseif ($action === 'offer') {
                    $buttons .= _pg_volunteer_page_button($form, $c['quiet'], lang('Offer my place'), $fields + array('action' => 'offer'));
                } elseif ($action === 'keep') {
                    $buttons .= _pg_volunteer_page_button($form, $c['quiet'], lang('Keep my place'), $fields + array('action' => 'keep'));
                }
            }

            $rows .= '<div class="' . $c['item'] . '"><span><span class="fw-semibold">' . h(pg_volunteer_shift_when($signup['shift'])) . '</span>'
                . '<span class="d-block ' . $c['meta'] . '">' . h(pg_volunteer_role_name($programme, $signup['role'])) . ' ' . $pill($label[0], $label[1]) . '</span></span>'
                . (($buttons !== '') ? '<span class="d-flex flex-wrap gap-2">' . $buttons . '</span>' : '') . '</div>';
        }

        $html .= ($rows !== '') ? '<div class="' . $c['list'] . '">' . $rows . '</div>' : '<p class="' . $c['meta'] . '">' . h(lang('No shifts yet.')) . '</p>';

        if (($programme['state'] === 'open') && ($volunteer['state'] === 'active')) {
            $html .= '<h3 class="h6 mb-2">' . h(lang('Shifts for you')) . '</h3>';
            $rows = '';
            $words = array('sign_up' => lang('Sign up'), 'waitlist' => lang('Join the waitlist'), 'take' => lang('Take this place'));

            foreach ($view['open'] as $open) {
                $buttons = '';

                foreach ($open['choices'] as $choice) {
                    $buttons .= _pg_volunteer_page_button($form, ($choice['action'] === 'sign_up') ? $c['fill'] : $c['quiet'], $words[$choice['action']] . ' · ' . pg_volunteer_role_name($programme, $choice['role']),
                        $profile + array('shift' => (int) $open['shift']['id'], 'action' => $choice['action'], 'role' => $choice['role'], 'target' => (int) $choice['signup']));
                }

                $rows .= '<div class="' . $c['item'] . '"><span><span class="fw-semibold">' . h(pg_volunteer_shift_when($open['shift'])) . '</span>'
                    . (($open['shift']['note'] !== '') ? '<span class="d-block ' . $c['meta'] . '">' . h($open['shift']['note']) . '</span>' : '') . '</span>'
                    . '<span class="d-flex flex-wrap gap-2">' . $buttons . '</span></div>';
            }

            $html .= ($rows !== '') ? '<div class="' . $c['list'] . '">' . $rows . '</div>' : '<p class="' . $c['meta'] . '">' . h(lang('No shifts for you right now.')) . '</p>';
        }

        if ($view['documents']) {
            $html .= '<h3 class="h6 mb-2">' . h(lang('My documents')) . '</h3><div class="' . $c['list'] . ' mb-0">';

            foreach ($view['documents'] as $document) {
                $html .= '<div class="' . $c['item'] . '"><span>' . h($document['kind']) . '</span><span class="' . $c['meta'] . '">'
                    . h(($document['valid_until'] !== null) ? lang(array('string' => 'Valid until {var:1}', 'vars' => array(get_absolute_time(array('timestamp' => strtotime($document['valid_until']), 'type' => 'date', 'format' => 'plain_text'))))) : lang('No end date')) . '</span></div>';
            }

            $html .= '</div>';
        }

        $html .= pg_volunteer_calls_form_html($form, $c, $volunteer, $programme['call_channels']);
        $html .= '</div></section>';
    }

    return $html;
}

/**
 * The volunteer's "Shift calls" switch with the channel chosen, as one POST
 * form to volunteer.php; '' when the programme makes no calls. The
 * day-before reminder goes whatever it says.
 *
 * @param array $form      action, hidden
 * @param array $c         the classes
 * @param array $volunteer id, calls, call_channel
 * @param array $channels  the programme's call_channels
 * @return string HTML
 */
function pg_volunteer_calls_form_html(array $form, array $c, array $volunteer, array $channels)
{
    if (!$channels) {
        return '';
    }

    $words = array('email' => lang('E-mail'), 'sms' => lang('SMS'), 'whatsapp' => lang('WhatsApp'));
    $id = 'vo_calls_' . (int) $volunteer['id'];
    $options = '';

    foreach ($channels as $channel) {
        $options .= '<option value="' . h($channel) . '"' . (((string) $volunteer['call_channel'] === $channel) ? ' selected' : '') . '>' . h($words[$channel] ?? $channel) . '</option>';
    }

    return '<h3 class="h6 mt-5 mb-2">' . h(lang('Shift calls')) . '</h3>'
        . '<form method="post" action="' . h($form['action']) . '" class="d-flex flex-wrap align-items-center gap-3">' . $form['hidden']
        . '<input type="hidden" name="p" value="' . (int) $volunteer['id'] . '"><input type="hidden" name="action" value="calls">'
        . '<span class="' . $c['check'] . '"><input class="' . $c['switch'] . '" type="checkbox" role="switch" name="calls" value="1" id="' . $id . '"' . (!empty($volunteer['calls']) ? ' checked' : '') . '>'
        . '<label class="' . $c['label'] . '" for="' . $id . '">' . h(lang('Ask me when a shift still needs volunteers')) . '</label></span>'
        . ((count($channels) > 1) ? '<select name="channel" class="' . $c['select'] . '" aria-label="' . h(lang('Channel')) . '">' . $options . '</select>' : '<input type="hidden" name="channel" value="' . h($channels[0]) . '">')
        . '<button type="submit" class="' . $c['quiet'] . '">' . h(lang('Save')) . '</button></form>'
        . '<p class="' . $c['meta'] . ' mt-2 mb-0">' . h(lang('The reminder the day before your shift comes either way.')) . '</p>';
}

/**
 * What an action's outcome tells the volunteer. Pure.
 *
 * @param string $answer pg_volunteer_self_act()
 * @return array text, ok
 */
function pg_volunteer_answer_text($answer)
{
    $texts = array(
        'signed'    => lang('You are signed up for the shift.'),
        'waiting'   => lang('You are on the waitlist. We will e-mail you when a place opens.'),
        'asked'     => lang('You asked to take the place. The coordinator will answer.'),
        'cancelled' => lang('Your place was cancelled.'),
        'offered'   => lang('Your place is offered to the other volunteers. You keep it until someone takes it.'),
        'kept'      => lang('You kept your place.'),
        'calls_saved' => lang('Your shift calls choice was saved.'),
    );

    return isset($texts[$answer]) ? array($texts[$answer], true) : array(lang('This could not be done: the shift changed meanwhile. Please check it and try again.'), false);
}
