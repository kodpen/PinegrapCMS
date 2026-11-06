<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Photography Studio", its client area: the public page
 * that explains the private galleries, the sample proofing gallery in its
 * own private folder, the proof selection form, the selection's own page
 * with its messages, My Selections and the staff's list, the two e-mails
 * of the selection form, and the widgets that draw them. Included after
 * parts.php and mail.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A client's gallery is a page in a private folder. The studio duplicates
 * the sample folder (client_sample) for each client, puts the proofs and
 * the edited photos in it and gives that client the folder in the panel;
 * files in a private folder are served only to whoever may open it.
 *
 * A selection is a record of the Proof Selections form: the gallery (what
 * the gallery's own button sends, ?gallery=), the frame numbers the client
 * chose and what to retouch. The form is in the registration folder, so
 * the client sends it signed in and finds it under My Selections. Its
 * Status is an office-use-only field the studio changes in the panel; the
 * messages under it are the page's comments, mailed to the client and to
 * the studio, and may carry the finished files.
 *
 * The helpers of this file are named sel_…, so they never take the place
 * of a helper another page file of the folder defines in the same scope.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$client_pages = array();

// The sample gallery's name: the words its button sends to the selection
// form. The studio writes the client's own in each copy of the folder.
$sel_sample_gallery = lang('Sample Client Gallery');
$sel_sample_frames = sprintf(lang('Frame %02d'), 3) . ', ' . sprintf(lang('Frame %02d'), 7) . ', ' . sprintf(lang('Frame %02d'), 12);
$sel_sample_retouch = lang('Frame 07: a little warmer, please, and the stray hair on the left removed.');
$sel_missing = lang('This selection could not be found, or it is not yours to see.');

// ── Pieces ──────────────────────────────────────────────────────────────

// A card of the client area: an icon, a title, a line and its link.
$sel_card = function ($icon_name, $title, $text, $link_text, $href) use ($el, $heading, $para, $link, $col, $icon_tile) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 pph-lift', lang('Client Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($icon_name, 'mb-3'),
                $heading('h2', $title, 'pph-display h5 mb-2'),
                $para($text, 'small text-body-secondary mb-3'),
                $link($link_text, $href, 'stretched-link fw-semibold link-primary text-decoration-none small mt-auto'),
            )),
        )),
    ), '6', '3');
};

// ── Pages ───────────────────────────────────────────────────────────────

// The client area: how a private gallery works, and the ways into it.
$client_pages[] = array(
    'key'              => 'client_area',
    'name'             => lang('client-area'),
    'folder'           => 'public',
    'title'            => lang('Client Area'),
    'meta_description' => lang('Your photos in a private online gallery: open it signed in, choose your favourites by frame number and download the edited photos.'),
    'tree'             => $page(array(
        $page_header(lang('Client Area'),
            lang('Your photos wait in a private gallery that only you can open after signing in. Choose your favourites there, send us their frame numbers and download the edited photos when they are ready.'),
            array($el('div', 'd-flex flex-wrap gap-2 mt-4', lang('Buttons'), array(
                $link(lang('Sign in'), '{{page:login}}', 'btn btn-primary rounded-pill px-4'),
                $link(lang('My Account'), '{{page:my_account}}', 'btn btn-outline-primary rounded-pill px-4'),
            )))),
        $el('section', 'py-5', lang('Ways In'), array(
            $container(array(
                $row(array(
                    $sel_card('bi-images', lang('Your private gallery'), lang('After your shoot we send you the link of your own gallery; it opens only for you.'),
                        lang('See a sample gallery'), '{{page:proofing_sample}}'),
                    $sel_card('bi-check2-square', lang('Send your selection'), lang('Write the frame numbers you love and anything to retouch; we edit them by hand.'),
                        lang('Choose your photos'), '{{page:selection}}'),
                    $sel_card('bi-collection', lang('My Selections'), lang('Your selections, their status and the studio\'s answers, with the finished files.'),
                        lang('Open My Selections'), '{{page:my_selections}}'),
                    $sel_card('bi-calendar-event', lang('My Bookings'), lang('Your shoot booking requests and the dates the studio holds for you.'),
                        lang('Open My Bookings'), '{{page:my_bookings}}'),
                ), '4'),
            )),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('How Proofing Works'), array(
            $container(array(
                $section_header(lang('Proofing'), lang('From your proofs to your photos')),
                $row(array(
                    $col(array($step(1, lang('Open your gallery'), lang('Within two weeks of the shoot your proofs are in your private gallery, each with a frame number.'))), '6', '3'),
                    $col(array($step(2, lang('Choose your favourites'), lang('Note the frame numbers you love; the package says how many edited photos it includes.'))), '6', '3'),
                    $col(array($step(3, lang('Send the selection'), lang('Send the numbers with the selection form, with anything you want retouched.'))), '6', '3'),
                    $col(array($step(4, lang('Download your photos'), lang('We tell you when they are ready: download them from your gallery or the selection\'s page.'))), '6', '3'),
                ), '4'),
            )),
        ), $searchable('ph-proofing', lang('proofing, private gallery, download, selection, frame number'))),
    )),
);

// The sample proofing gallery, in the folder the studio duplicates for
// each client. The note tells the studio how; the proofs carry their frame
// numbers and download links.
$sel_frames = array();
for ($n = 1; $n <= 12; $n++) $sel_frames[] = $proof_frame($n);
$client_pages[] = array(
    'key'     => 'proofing_sample',
    'name'    => lang('sample-client-gallery'),
    'folder'  => 'client_sample',
    'title'   => $sel_sample_gallery,
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $page(array(
        $page_header($sel_sample_gallery, lang('Your proofs, each with its frame number. Choose your favourites and send us their numbers; the edited photos come back here to download.'),
            array($el('div', 'd-flex flex-wrap gap-2 mt-4', lang('Buttons'), array(
                $link(lang('Send my selection'), '{{page:selection}}?' . $query(array('gallery' => $sel_sample_gallery)), 'btn btn-primary rounded-pill px-4'),
                $link(lang('My Selections'), '{{page:my_selections}}', 'btn btn-outline-primary rounded-pill px-4'),
            )))),
        $el('section', 'py-5', lang('Proofs'), array(
            $container(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small mb-4', lang('Studio Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('For the studio: duplicate this folder for each client, rename the page and its button\'s gallery name, put the client\'s photos and a ZIP of them in it, then give the client the folder in the control panel. Delete this note in each copy.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $row($sel_frames, '4'),
            )),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('Downloads'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Your edited photos'), 'pph-display h4 mb-2'),
                        $para(lang('Once your selection is edited, the full-size photos and a ZIP of all of them are linked here, and on your selection\'s page. Keep a copy: the gallery stays open for a year.'), 'text-body-secondary mb-0'),
                    ), '', '8'),
                    $col(array(
                        $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                            $link(lang('Send my selection'), '{{page:selection}}?' . $query(array('gallery' => $sel_sample_gallery)), 'btn btn-primary rounded-pill px-4'),
                        )),
                    ), '', '4'),
                ), '4', '', 'align-items-center'),
            )),
        )),
    ), false),
);

// The selection form. What is sent opens the selection's own page.
$client_pages[] = array(
    'key'              => 'selection',
    'name'             => lang('photo-selection'),
    'folder'           => 'registration',
    'title'            => lang('Send Your Selection'),
    'search'           => false,
    'sitemap'          => false,
    'form'             => array(
        'form_name'            => lang('Proof Selections'),
        'confirmation_message' => lang('Thank you, your selection has reached us. We tell you when the photos are ready.'),
        'confirmation_page_id' => '{{tab:selection_view}}',
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New photo selection: ^^gallery^^'),
        'notify_page_id'       => '{{tab:email_selection_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your photo selection'),
        'confirm_page_id'      => '{{tab:email_selection_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Send Your Selection'),
            lang('Write the frame numbers of the photos you love and anything you want retouched. We edit them by hand and tell you when they are ready.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('selection_form'))),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('Good to know'), array(
                            $heading('h2', lang('Good to know'), 'pph-display h5 mb-3'),
                            $check_list(array(
                                lang('Your package says how many edited photos it includes; more can be added at a price per photo.'),
                                lang('Edited photos are ready within two weeks of your selection.'),
                                lang('Changed your mind? Write to us under your selection before we start editing.'),
                            ), 'list-unstyled small mb-0'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// One selection: the frames chosen, the status and the messages under it.
// The studio attaches the finished files to its messages.
$client_pages[] = array(
    'key'      => 'selection_view',
    'name'     => lang('photo-selection-view'),
    'folder'   => 'registration',
    'title'    => lang('Photo Selection'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'attachments' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your photo selection'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('selection_detail'),
        $comments_section(lang('Messages'), 'selection_messages'),
    ), false),
);

// My Selections: the client's own selections (for the staff, everybody's).
$client_pages[] = array(
    'key'     => 'my_selections',
    'name'    => lang('my-selections'),
    'folder'  => 'registration',
    'title'   => lang('My Selections'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Selections'), lang('Your photo selections, their status and the studio\'s answers with the finished files, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_selections_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('Client Area'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-images', 'text-primary'),
                                $span(lang('Your galleries'), '', lang('Text')),
                            )),
                            $para(lang('Open a gallery from the link we sent you, or from the Client Area.'), 'small text-body-secondary mb-3'),
                            $link(lang('Open the Client Area'), '{{page:client_area}}', 'btn btn-sm btn-primary rounded-pill px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// The staff's list of every selection, newest first, with a search.
$client_pages[] = array(
    'key'     => 'staff_selections',
    'name'    => lang('all-photo-selections'),
    'folder'  => 'private',
    'title'   => lang('Proof Selections'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_page(array(
        $page_header(lang('Proof Selections'), lang('Every photo selection, newest first: set the status, and attach the finished files in a message under each.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget('staff_selections_list'))),
        )),
    )),
);

// The e-mails of the selection form: what the studio receives and the
// reply to the client. A message under a selection goes out with the new
// message e-mail (email_reply) the record page names.
$client_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_selection_new',
    'name'  => lang('email-new-photo-selection'),
    'title' => lang('New Photo Selection E-mail'),
    'tree'  => $mail_page(array($widget('email_selection_admin'))),
));
$client_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_selection_received',
    'name'  => lang('email-photo-selection-received'),
    'title' => lang('Photo Selection Received E-mail'),
    'tree'  => $mail_page(array($widget('email_selection_client'))),
));

// ── Widget pieces ───────────────────────────────────────────────────────

$sel_person = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};

// A selection in a list: the gallery, the status, the frames and the
// prints, linked to its page. The staff's row starts with the client's
// name; the client's own adds the messages.
$sel_row = function ($staff = false) use ($el, $span, $icon, $bind, $sel_person, $sel_sample_gallery, $sel_sample_frames, $print_choices, $selection_statuses) {
    $item = function ($icon_name, $name, $children) use ($el, $icon) {
        return $el('span', 'd-inline-flex align-items-center gap-1', $name, array_merge(array($icon($icon_name)), $children));
    };
    $items = array(
        $item('bi-check2-square', lang('Frames'), array($span($sel_sample_frames, '', lang('Frames'), $bind('frames')))),
        $item('bi-printer', lang('Prints'), array($span($print_choices[0], '', lang('Prints'), $bind('prints')))),
    );
    if ($staff) {
        $title = $el('span', 'fw-semibold', lang('Client'), array_merge($sel_person(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($sel_sample_gallery, 'text-body-secondary fw-normal', lang('Gallery'), $bind('gallery')),
        )));
    } else {
        $items[] = $item('bi-chat-left-text', lang('Messages'), array($span('0', '', lang('Reply Count'), $bind('number_of_comments'))));
        $title = $span($sel_sample_gallery, 'fw-semibold', lang('Gallery'), $bind('gallery'));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Photo Selection'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $span($selection_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $items),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};

$sel_detail = function ($label, $class, $name, $value, $children = false) use ($el) {
    return array(
        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => $label)),
        $el('dd', trim('col-sm-8 ' . $class), $name, $children ? $value : array(), $children ? array() : $value),
    );
};
$sel_bound = function ($sample, $field) use ($bind) {
    return array_merge(array('text' => $sample), $bind($field));
};

// A list of selections: the client's own (the staff see them all), or the
// staff's with a search.
$sel_list = function ($page_key, $staff, $empty) use ($widget_root, $el, $loop, $sel_row) {
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:selection}}',
        'detail_page_id'      => '{{tab:selection_view}}',
        'items_per_page'      => 20,
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
    );
    if ($staff) {
        $config += array(
            'search_enabled' => true,
            'search_fields'  => array('first_name', 'last_name', 'email', 'gallery', 'frames', 'status'),
            'search_width'   => 'half',
            'search_label'   => lang('Search by client, e-mail, gallery, frame or status...'),
        );
    } else {
        $config += array(
            'viewer_filter'           => true,
            'viewer_filter_submitter' => true,
            'viewer_filter_watcher'   => true,
            'viewer_filter_editor'    => true,
        );
    }
    $config['empty_message'] = $empty;
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-list'),
        'config' => $config,
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Selection List'), array($loop(array($sel_row($staff))))),
        )),
    );
};

// The lines of a selection, for its e-mails.
$sel_mail_selection = function () use ($mail_box, $mail_line, $span, $bind, $sel_sample_gallery, $sel_sample_frames, $sel_sample_retouch, $print_choices) {
    return $mail_box(array(
        $mail_line(lang('Gallery'), array($span($sel_sample_gallery, '', lang('Gallery'), $bind('gallery')))),
        $mail_line(lang('Frames'), array($span($sel_sample_frames, '', lang('Frames'), $bind('frames')))),
        $mail_line(lang('Retouching'), array($span($sel_sample_retouch, '', lang('Retouching'), $bind('retouch')))),
        $mail_line(lang('Prints'), array($span($print_choices[0], '', lang('Prints'), $bind('prints')))),
    ), lang('Photo Selection'));
};
$sel_mail_client = function () use ($mail_box, $mail_line, $span, $bind, $sel_person, $contact_choices) {
    return $mail_box(array(
        $mail_line(lang('Client'), $sel_person()),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
        $mail_line(lang('Reach me by'), array($span($contact_choices[0][1], '', lang('Reach me by'), $bind('reach_by')))),
    ), lang('Client'));
};
$sel_mail_reference = function () use ($el, $span, $bind, $mail_style) {
    return $el('p', 'small text-body-secondary mt-4 mb-0', lang('Reference'), array(
        $span(lang('Reference Code') . ': ', '', lang('Label')),
        $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
    ), $mail_style('font-size:13px;color:#4b5563;margin:24px 0 0'));
};

// The form detail settings of an e-mail page's widget (any reader: the
// e-mail is drawn for the submission just sent) and of the selection
// page's widget (the client's own, and the staff's).
$sel_detail_config = function ($page_key, $access) use ($sel_missing) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:selection}}',
            'access_control'      => $access,
            'not_found_message'   => $sel_missing,
        ),
    );
};

$client_widgets = array(

    // The selection: the fields are the page's form. The gallery starts
    // with what the gallery's button sends (?gallery=); the name and the
    // e-mail with the signed-in client's. The Status is the studio's
    // (office use only): every selection starts as Selection received.
    'selection_form' => array(
        'page'   => 'selection',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Your photos')),
            $field('ph-sel-gallery', lang('Gallery'), $input('ph-sel-gallery', 'text', 'gallery', 'off', true, array('url_param' => 'gallery')), 'mb-3',
                lang('The name at the top of your private gallery.')),
            $field('ph-sel-frames', lang('Frames'), $textarea('ph-sel-frames', 'frames', 3, true), 'mb-3',
                lang('The frame numbers you chose, separated by commas, for example Frame 03, Frame 07.')),
            $field('ph-sel-retouch', lang('Retouching'), $textarea('ph-sel-retouch', 'retouch', 3, false), 'mb-3',
                lang('Anything to change on a frame: warmer, black and white, a stray hair or a sign to remove.')),
            $field('ph-sel-prints', lang('Prints'), $select('ph-sel-prints', 'prints', '', $print_choices, false, $print_choices[0])),
            $form_step(2, lang('How to reach you')),
            $row(array(
                $col(array($field('ph-sel-first-name', lang('First Name'), $input('ph-sel-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('ph-sel-last-name', lang('Last Name'), $input('ph-sel-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('ph-sel-email', lang('Email'), $input('ph-sel-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('ph-sel-phone', lang('Phone'), $input('ph-sel-phone', 'tel', 'phone', 'tel', false, array('contact_field' => 'mobile_phone')))), '6'),
            ), '3'),
            $radios('ph-sel-reach', 'reach_by', lang('Reach me by'), $contact_choices, $contact_choices[0][0]),
            $field('ph-sel-status', lang('Status'), $select('ph-sel-status', 'status', '', $selection_statuses, false, $selection_statuses[0], array('office_use_only' => true))),
            $agree('ph-sel-consent', 'consent', lang('I agree that the studio keeps my selection and my details to edit and deliver my photos, as its privacy notice explains.'), true),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send my selection'), 'btn btn-primary btn-lg rounded-pill px-4'),
                $para(lang('Edited photos are ready within two weeks of your selection.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One selection: the frames, the status and what to retouch; the
    // studio's answers and the finished files follow as comments.
    'selection_detail' => $sel_detail_config('selection_view', 'submitter_only') + array(
        'tree' => array('type' => 'root', 'props' => array(), 'children' => array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon('bi-images', 'display-5 text-body-tertiary'),
                            $heading('h2', lang('Selection not found'), 'pph-display h3 mt-3'),
                            $para($sel_missing, 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('My Selections'), '{{page:my_selections}}', 'btn btn-primary rounded-pill px-4'),
                        ), $show_when('record_not_found')),
                        $el('div', '', lang('Record'), array(
                            $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                                $icon('bi-check-circle', 'mt-1'),
                                $span(lang('Thank you, your selection has reached us. This page shows its status; the finished photos and our messages come below.'), '', lang('Text')),
                            ), array_merge(array('_attrs' => array($attr('role', 'status'))), $show_when('submitted_this_session'))),
                            $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                                $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                                    $icon('bi-arrow-left'),
                                    $span(lang('My Selections'), '', lang('Link Text')),
                                ), array('href' => '{{page:my_selections}}')),
                                $edit_button(lang('Change Status')),
                            )),
                            $el('div', 'card border-0 shadow-sm rounded-4 mb-4', lang('Selection Card'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                        $el('div', '', lang('Words'), array(
                                            $para(lang('Photo Selection'), 'pph-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                            $heading('h1', $sel_sample_gallery, 'pph-display h3 mb-0', $bind('gallery')),
                                        )),
                                        $span($selection_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                    )),
                                    $el('dl', 'row small mb-0', lang('Details'), array_merge(
                                        $sel_detail(lang('Frames'), '', lang('Frames'), $sel_bound($sel_sample_frames, 'frames')),
                                        $sel_detail(lang('Prints'), '', lang('Prints'), $sel_bound($print_choices[0], 'prints')),
                                        $sel_detail(lang('Client'), '', lang('Client'), $sel_person(), true),
                                        $sel_detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $sel_bound('ABC123', 'reference_code'))
                                    )),
                                )),
                            )),
                            $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', lang('Retouching'), array(
                                $para(lang('Retouching'), 'small fw-semibold text-body-secondary mb-2'),
                                $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $sel_bound($sel_sample_retouch, 'retouch')),
                            )),
                            $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                                $icon('bi-chat-left-text', 'text-primary mt-1'),
                                $span(lang('Another frame, a change to the retouching or a question? Write to us below before we start editing.'), '', lang('Text')),
                            )),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // The messages under a selection: the page's own Comments widget, shown
    // only to whoever may read the selection above it. Oldest first, all on
    // one page; the studio's messages carry the finished files.
    'selection_messages' => array(
        'page'   => 'selection_view',
        'slug'   => lang('comments'),
        'config' => array(
            'regionType' => 'comments',
            'order'      => 'oldest',
            'featured'   => 'none',
            'layout'     => 'flat',
            'max_depth'  => 1,
            'per_page'   => 0,
        ),
        'tree'   => $comment_thread(array(
            'title'     => lang('Messages'),
            'empty'     => lang('No messages yet. The studio writes here when your photos are ready, with the files to download.'),
            'heading'   => lang('Write to us'),
            'label'     => lang('Your message'),
            'sign_in'   => lang('Sign in to write to us.'),
            'submit'    => lang('Send message'),
            'watch_box' => '',
            'follow'    => '',
            'unfollow'  => '',
            'close'     => lang('Stop new messages'),
            'reopen'    => lang('Allow new messages'),
            'reply'     => false,
            'watch'     => false,
        )),
    ),

    'my_selections_list' => $sel_list('my_selections', false,
        lang('No selections yet. Open your gallery and send us the frames you love.')),

    'staff_selections_list' => $sel_list('staff_selections', true, lang('No selections yet.')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_selection_admin' => $sel_detail_config('email_selection_new', 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new photo selection')),
            $mail_para(lang('A client sent the frames to edit. Set the status on the selection\'s page and attach the finished files in a message there; it reaches the client by e-mail.')),
            $sel_mail_selection(),
            $sel_mail_client(),
            $mail_button(lang('Open the selection'), '{{page:selection_view}}?r=^^reference_code^^'),
            $sel_mail_reference(),
        )),
    ),
    'email_selection_client' => $sel_detail_config('email_selection_received', 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your photo selection')),
            $mail_greeting(),
            $mail_para(lang('Thank you. We edit the frames you chose by hand and write to you on the selection\'s page when they are ready, with the files to download.')),
            $sel_mail_selection(),
            $mail_button(lang('Open my selection'), '{{page:selection_view}}?r=^^reference_code^^'),
        )),
    ),
);

return array('pages' => $client_pages, 'widgets' => $client_widgets);
