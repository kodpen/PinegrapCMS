<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Social Community", its reports and e-mails: Report a
 * Post (the Reports form), the member's own report page and My Reports; the
 * moderators' reported posts, one report with their notes under it and
 * every member post with the hidden ones; the e-mails of the Reports form,
 * the new comment and new reply e-mails, and the widgets that draw them. Included after parts.php and mail.php in the same scope;
 * returns array('pages' => ..., 'widgets' => ...).
 *
 * A report is a record of the Reports form, sent by a signed-in member.
 * The post page's Report link opens the form with the post's address in
 * its first field (?post=, the field's starting value): the member can
 * change it, so it is only what they reported, printed as text and never
 * trusted as a link. Its Status is an office-use-only field the moderators
 * set in the control panel; their notes under it are the staff report
 * page's comments, e-mailed to nobody. The member who wrote the post is
 * never told who reported it.
 *
 * Staff take a post off the site by setting its Status to Hidden: every
 * public list and the post page carry the live rule (status is not equal
 * to Hidden), the staff's list of posts shows every status.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$report_pages = array();

// Why a member reports a post, and the statuses a report moves through.
$report_reasons = array(
    lang('Spam'),
    lang('Harassment or abuse'),
    lang('A photo that is not theirs'),
    lang('Private details or a person without consent'),
    lang('Nudity or violence'),
    lang('Something else'),
);
$report_statuses = array(lang('Open'), lang('Resolved'), lang('Dismissed'));
// The Status of the Posts form that takes a post off the site.
$post_hidden = lang('Hidden');

// A filter row of a list: the field, the operator and the value.
$report_filter = function ($field, $operator, $value) {
    return array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => '');
};

// The sample report the rows, the report pages and the e-mails show in the
// editor and the installer's preview: a post's address under the post
// page's own name, sent by a sample member.
$report_sample = array(
    'status'  => $report_statuses[0],
    'reason'  => $report_reasons[0],
    'post'    => '/' . lang('post') . '?r=AbC123xYz9',
    'details' => lang('The caption links to a shop and the same photo was posted five times today.'),
    'note'    => lang('Hid the post and wrote to the member.'),
    'by'      => $members['tom']['name'],
    'account' => $members['tom']['handle'],
);
// The sample post of the staff's list.
$staff_post_sample = $social_posts['ayla'][0];

// ── Page pieces ─────────────────────────────────────────────────────────

// A side card: a title and what goes under it.
$report_side_card = function ($title, $children, $class = 'p-4 rounded-4 bg-body-tertiary mb-4') use ($el, $heading) {
    return $el('div', $class, $title, array_merge(array(
        $heading('h2', $title, 'h6 fw-bold mb-3'),
    ), $children));
};

// A list section of a staff page: its heading and line, then the list.
$report_section = function ($id, $title, $lead, $widget_key) use ($el, $heading, $para, $widget) {
    return $el('section', 'mb-5', $title, array(
        $heading('h2', $title, 'h4 fw-semibold mb-1'),
        $para($lead, 'small text-body-secondary mb-3'),
        $widget($widget_key),
    ), array('id' => $id));
};

// ── Pages ───────────────────────────────────────────────────────────────

// Report a Post: the Reports form, in the registration folder. What is sent
// opens the report's own page for the member.
$report_pages[] = array(
    'key'     => 'report',
    'name'    => lang('report-a-post'),
    'folder'  => 'registration',
    'title'   => lang('Report a post'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Post Reports'),
        'confirmation_message' => lang('Thank you. A moderator will look at the post; you can follow your report under My Reports.'),
        'confirmation_page_id' => '{{tab:report_view}}',
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('A post was reported: ^^reason^^'),
        'notify_page_id'       => '{{tab:email_report_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your report'),
        'confirm_page_id'      => '{{tab:email_report_received}}',
    ),
    'tree'    => $page(array(
        $page_header(lang('Report a post'), lang('Tell the moderators about a post that breaks the community guidelines. Only the moderators see your report.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('report_form'))),
                        )),
                    ), '', '8'),
                    $col(array(
                        $report_side_card(lang('What happens next'), array(
                            $check_list(array(
                                lang('A moderator reads every report.'),
                                lang('A post that breaks the guidelines is taken off the site.'),
                                lang('The member who shared it is not told who reported it.'),
                            ), 'list-unstyled small mb-0'),
                        )),
                        $report_side_card(lang('Something urgent?'), array(
                            $para(lang('If someone is in danger, contact the local emergency services first.'), 'small text-body-secondary mb-3'),
                            $link(lang('Community Guidelines'), '{{page:guidelines}}', 'btn btn-sm btn-outline-primary rounded-pill px-3'),
                        ), 'p-4 rounded-4 border'),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// One report, for the member who sent it: what they reported and its
// status. The moderators' notes stay on their own page.
$report_pages[] = array(
    'key'     => 'report_view',
    'name'    => lang('my-report'),
    'folder'  => 'registration',
    'title'   => lang('Your Report'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $member_page(array($widget('report_detail'))),
);

// My Reports: the member's reports, newest first (for the staff, every one).
$report_pages[] = array(
    'key'     => 'my_reports',
    'name'    => lang('my-reports'),
    'folder'  => 'registration',
    'title'   => lang('My Reports'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $member_page(array(
        $page_header(lang('My Reports'), lang('The posts you reported to the moderators and what became of them, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_reports_list')), '', '8'),
                    $col(array(
                        $report_side_card(lang('Seen a post that breaks the rules?'), array(
                            $para(lang('Open the post and choose Report under its photo: the form opens with the post filled in.'), 'small text-body-secondary mb-3'),
                            $link(lang('Community Guidelines'), '{{page:guidelines}}', 'btn btn-sm btn-primary rounded-pill px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    )),
);

// The reported posts: the open ones, the oldest first, then every report
// with a search. Each opens the report's page for the moderators.
$report_pages[] = array(
    'key'     => 'staff_reports',
    'name'    => lang('reported-posts'),
    'folder'  => 'private',
    'title'   => lang('All Reports'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $staff_page(array(
        $page_header(lang('All Reports'), lang('What members reported to the moderators. Open a report, look at the post, write a note, then resolve or dismiss it.')),
        $el('div', 'py-5', lang('Content'), array(
            $container(array(
                $report_section('open-reports', lang('Open reports'), lang('Reports nobody has dealt with yet, the oldest first.'), 'staff_reports_open'),
                $report_section('every-report', lang('Every report'), lang('Every report in any status, the newest first, with a search.'), 'staff_reports_all'),
            )),
        )),
    )),
);

// One report, for the moderators: what the member reported and why, and
// the moderators' notes under it (published at once, e-mailed to nobody).
$report_pages[] = array(
    'key'      => 'staff_report',
    'name'     => lang('report'),
    'folder'   => 'private',
    'title'    => lang('Reported post'),
    'search'   => false,
    'sitemap'  => false,
    'noindex'  => true,
    'comments' => array(
        'label'          => lang('Note'),
        'allow_new'      => true,
        'auto_publish'   => true,
        'show_date'      => true,
        'login'          => true,
        'attachments'    => false,
        'select_name'    => false,
        'message'        => lang('Notes are for the moderators only: say what you found and what you did.'),
        'closed_message' => lang('This report takes no new notes.'),
    ),
    'tree'     => $staff_page(array(
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $widget('staff_report_view'),
                        $el('div', 'd-print-none', lang('Notes'), array($widget('report_notes'))),
                    ), '', '8'),
                    $col(array(
                        $report_side_card(lang('Handling a report'), array(
                            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('Steps'), array(
                                $el('li', 'mb-2', lang('Step'), array(), array('text' => lang('Copy the post\'s address into your browser and look at the post.'))),
                                $el('li', 'mb-2', lang('Step'), array(), array('text' => lang('If it breaks the guidelines, set the post to Hidden from All Member Posts.'))),
                                $el('li', 'mb-2', lang('Step'), array(), array('text' => lang('Write a note below: what you found and what you did.'))),
                                $el('li', '', lang('Step'), array(), array('text' => lang('Set the report to Resolved or Dismissed with Change Status.'))),
                            )),
                        )),
                        $report_side_card(lang('Where to act'), array(
                            $link(lang('All Member Posts'), '{{page:staff_posts}}', 'btn btn-sm btn-outline-primary rounded-pill px-3'),
                        ), 'p-4 rounded-4 border'),
                    ), '', '4', '', '12', 'd-print-none'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    )),
);

// Every member post for the staff: the hidden ones first, then all of them,
// the newest first, with a search. A row's pencil opens the record, where
// the Status hides or shows it; a row links no post page, because the post
// page keeps the live rule for everyone and would not show a hidden one.
$report_pages[] = array(
    'key'     => 'staff_posts',
    'name'    => lang('all-member-posts'),
    'folder'  => 'private',
    'title'   => lang('All Member Posts'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $staff_page(array(
        $page_header(lang('All Member Posts'), lang('Every post members shared, in any status. Set a post to Hidden to take it off the site, or feature it on the home page.')),
        $el('div', 'py-5', lang('Content'), array(
            $container(array(
                $report_section('hidden-posts', lang('Hidden posts'), lang('Posts taken off the site, the most recently changed first.'), 'staff_posts_hidden'),
                $report_section('every-post', lang('Every post'), lang('Every post in any status, the newest first, with a search.'), 'staff_posts_all'),
            )),
        )),
    )),
);

// The e-mails of the Reports form: what the moderators receive and the copy
// the member gets.
$report_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_report_new',
    'name'  => lang('email-new-report'),
    'title' => lang('New Report E-mail'),
    'tree'  => $mail_page(array($widget('email_report_staff'))),
));
$report_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_report_received',
    'name'  => lang('email-report-received'),
    'title' => lang('Report Received E-mail'),
    'tree'  => $mail_page(array($widget('email_report_member'))),
));
// The comment e-mails. Their Comments widget draws the comment in the card
// of mail.php, with its View or Reply button; the System region after it
// keeps the fixed comment for an e-mail the widget does not draw (a card
// edited so it lost its link to the comment).
$report_mail_system = array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array());
// A new comment on a post: sent to the members who follow its thread (who
// saved it, the member who shared it among them).
$report_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_comment',
    'name'  => lang('email-new-comment'),
    'title' => lang('New Comment E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new comment')),
        $mail_para(lang('Someone commented on a post you shared or saved. You can read it below and reply on the post.')),
        $widget('email_comment_card'),
        $report_mail_system,
    )),
));
// A new reply in a conversation with the community's team: sent to the
// member who started it.
$report_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new reply')),
        $mail_para(lang('The team answered a message you sent us. You can read it below and reply in the conversation.')),
        $widget('email_comment_card'),
        $report_mail_system,
    )),
));

// ── Widget pieces ───────────────────────────────────────────────────────

// A report in a list: its reason (opening the report's page), its status,
// the post's address as text, who sent it (on the staff's lists) and when.
$report_row = function ($staff) use ($el, $span, $icon, $bind, $report_sample) {
    $details = array(
        $el('span', 'd-inline-flex align-items-center gap-1 text-break font-monospace', lang('Address of the post'), array(
            $icon('bi-link-45deg'), $span($report_sample['post'], '', lang('Value'), $bind('post')),
        )),
    );
    if ($staff) {
        $details[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Reported by'), array(
            $icon('bi-person'), $span($report_sample['by'], 'pg-field-value', lang('Name'), $bind('submitter_name')),
        ));
    }
    $details[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Sent'), array(
        $icon('bi-calendar3'), $span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time')),
    ));
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Report Row'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $span($report_sample['reason'], 'fw-semibold', lang('Reason'), $bind('reason')),
            $span($report_sample['status'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $details),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};

// A member post in the staff's list: the photo, the caption, who shared it,
// the topic, the status and the date. Not a link: see staff_posts.
$staff_post_row = function ($status) use ($el, $span, $icon, $image, $bind, $photo, $staff_post_sample, $members, $topics) {
    return $el('div', 'list-group-item d-flex gap-3 p-3', lang('Post Row'), array(
        $el('span', 'flex-shrink-0 rounded-3 overflow-hidden bg-body-tertiary psc-thumb', lang('Picture Frame'), array(
            $image($photo('post-ayla-1', 160, 160), $staff_post_sample['alt'], 'object-fit-cover w-100 h-100',
                array('_bindings' => array('src' => 'photo', 'alt' => 'alt_text'))),
        )),
        $el('div', 'flex-grow-1 overflow-hidden', lang('Post Info'), array(
            $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-1', lang('Top Line'), array(
                $span($staff_post_sample['caption'], 'fw-semibold text-truncate', lang('Caption'), $bind('caption')),
                $span($status, 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
            )),
            $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), array(
                $el('span', 'd-inline-flex align-items-center gap-1', lang('Shared by'), array(
                    $icon('bi-person'), $span($members['ayla']['name'], 'pg-field-value', lang('Name'), $bind('submitter_name')),
                )),
                $el('span', 'd-inline-flex align-items-center gap-1', lang('Topic'), array(
                    $icon('bi-grid'), $span($topics['photo']['title'], '', lang('Topic'), $bind('topic')),
                )),
                $el('span', 'd-inline-flex align-items-center gap-1', lang('Sent'), array(
                    $icon('bi-calendar3'), $span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time')),
                )),
                $el('span', 'd-inline-flex align-items-center gap-1', lang('Comments'), array(
                    $icon('bi-chat'), $span((string)$staff_post_sample['comments'], '', lang('Comment Count'), $bind('number_of_comments')),
                )),
            )),
        )),
    ));
};

// A list of records on a member or staff page. No live rule: these lists
// sit on the member's own page or the staff's and show every status.
$report_list = function ($page_key, $form_key, $view_key, $direction, $ipp, $filters, $empty, $row_node, $extra = array()) use ($widget_root, $el, $loop) {
    $config = array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:' . $form_key . '}}',
        'detail_page_id'      => '{{tab:' . $view_key . '}}',
        'items_per_page'      => $ipp,
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => $direction,
    ), $extra);
    if ($filters) $config['filters'] = $filters;
    $config['empty_message'] = $empty;
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-list'),
        'config' => $config,
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('List'), array($loop(array($row_node)))),
        )),
    );
};

// A detail of a report's card: the label, then the value's nodes.
$report_fact = function ($label, $children, $class = '') use ($el) {
    return array(
        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => $label)),
        $el('dd', trim('col-sm-8 ' . $class), $label, $children),
    );
};
// A long text the report carries, in a soft box that leaves when empty.
$report_words = function ($label, $sample, $field) use ($el, $para, $bind) {
    return $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', $label, array(
        $para($label, 'small fw-semibold text-body-secondary mb-2'),
        $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $sample), $bind($field))),
    ));
};
// A file the report carries, as a link that opens it.
$report_file = function () use ($link) {
    return array($link('screenshot.png', '#', '', array(), array('target' => '_blank', 'rel' => 'noopener',
        '_bindings' => array('href' => 'screenshot', 'text' => 'screenshot'))));
};

// A report's record view, as the nodes of the widget: the not-found notice,
// then the way back (and the staff's Change Status), the reason and status,
// the facts and the words.
$report_record = function ($back_key, $back_text, $missing, $staff, $more) use ($el, $heading, $para, $link, $span, $icon, $bind, $bind_date, $show_when, $edit_button, $report_sample, $report_fact, $report_words, $report_file) {
    $facts = array_merge(
        $report_fact(lang('Address of the post'), array($span($report_sample['post'], 'font-monospace', lang('Value'), $bind('post'))), 'text-break'),
        $staff ? array_merge(
            $report_fact(lang('Reported by'), array($span($report_sample['by'], 'pg-field-value', lang('Name'), $bind('submitter_name')))),
            $report_fact(lang('Account'), array($span($report_sample['account'], 'pg-field-value', lang('User Name'), $bind('submitter'))))
        ) : array(),
        $report_fact(lang('Screenshot'), $report_file(), 'text-break pg-hide-if-empty'),
        $report_fact(lang('Sent'), array($span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time')))),
        $staff ? $report_fact(lang('Last activity'), array($span(lang('2 hours ago'), '', lang('Time'), $bind_date('newest_activity_date_and_time', 'relative')))) : array(),
        $report_fact(lang('Reference Code'), array($span('ABC123', 'font-monospace', lang('Reference Code'), $bind('reference_code'))), 'mb-0')
    );
    $toolbar = array(
        $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
            $icon('bi-arrow-left'),
            $span($back_text, '', lang('Link Text')),
        ), array('href' => '{{page:' . $back_key . '}}')),
    );
    if ($staff) $toolbar[] = $edit_button(lang('Change Status'));

    return array(
        $el('div', 'text-center py-5', lang('Not Found'), array(
            $icon('bi-flag', 'display-5 text-body-tertiary'),
            $heading('h1', lang('Report not found'), 'h3 fw-semibold mt-3'),
            $para($missing, 'text-body-secondary mb-4', $bind('not_found')),
            $link($back_text, '{{page:' . $back_key . '}}', 'btn btn-primary rounded-pill px-4'),
        ), $show_when('record_not_found')),
        $el('article', '', lang('Report'), array_merge(array(
            $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), $toolbar),
            $el('header', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-4', lang('Report Header'), array(
                $el('div', '', lang('Title'), array(
                    $para(lang('Reported post'), 'psc-eyebrow text-uppercase fw-semibold text-primary mb-1'),
                    $heading('h1', $report_sample['reason'], 'h3 fw-semibold mb-0', $bind('reason')),
                )),
                $span($report_sample['status'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
            )),
            $el('div', 'card border-0 shadow-sm rounded-4 mb-4', lang('Report Card'), array(
                $el('div', 'card-body p-4', lang('Card Body'), array(
                    $el('dl', 'row small mb-0', lang('Details'), $facts),
                )),
            )),
            $report_words(lang('Details of the report'), $report_sample['details'], 'details'),
        ), $more), $show_when('record_found')),
    );
};

$report_missing = lang('This report could not be found, or it is not yours to see.');

$report_widgets = array(

    // The Reports form: the post's address (the post page's Report link
    // fills it from ?post=), the reason, the details and a screenshot; then
    // the moderators' fields. A new report is Open.
    'report_form' => array(
        'page'   => 'report',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $field('sc-report-post', lang('Address of the post'), $input('sc-report-post', 'text', 'post', 'off', true, array('url_param' => 'post'),
                array($attr('maxlength', '300'), $attr('inputmode', 'url'))), 'mb-3',
                lang('Opened from a post, this holds its address. To report a comment, paste the link its Share button copies instead.')),
            $field('sc-report-reason', lang('Reason'), $select('sc-report-reason', 'reason', lang('Choose a reason…'), $report_reasons)),
            $field('sc-report-details', lang('Details (optional)'), $textarea('sc-report-details', 'details', 5, false), 'mb-3',
                lang('What should the moderators look at? Only the moderators read this.')),
            $field('sc-report-screenshot', lang('Screenshot (optional)'), $file('sc-report-screenshot', 'screenshot', 'private', 'image/*,.pdf'), 'mb-3',
                lang('A picture of what you saw, or a PDF. Only the moderators can open it.')),
            // For the moderators only.
            $field('sc-report-status', lang('Status'), $select('sc-report-status', 'status', '', $report_statuses, false, $report_statuses[0], array('office_use_only' => true))),
            $field('sc-report-note', lang('Moderator note'), $textarea('sc-report-note', 'staff_note', 3, false, array('office_use_only' => true))),
            $captcha(),
            $submit(lang('Send the report')),
            $loop(array()),
        )),
    ),

    // The member's report: what they sent and its status.
    'report_detail' => array(
        'page'   => 'report_view',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:report}}',
            'access_control'      => 'submitter_only',
            'title_field'         => 'reason',
            'not_found_message'   => $report_missing,
        ),
        'tree'   => $widget_root(array($container(array(
            $row(array(
                $col($report_record('my_reports', lang('My Reports'), $report_missing, false, array(
                    $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                        $icon('bi-check-circle', 'mt-1'),
                        $span(lang('Thank you, your report has reached the moderators. This page shows its status.'), '', lang('Text')),
                    ), array_merge(array('_attrs' => array($attr('role', 'status'))), $show_when('submitted_this_session'))),
                    $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                        $icon('bi-shield-check', 'text-primary mt-1'),
                        $span(lang('The member who shared the post is not told who reported it. A question about your report? Write to us from the contact page.'), '', lang('Text')),
                    )),
                )), '', '8'),
            ), '', 'center'),
        ), 'py-5'))),
    ),

    'my_reports_list' => $report_list('my_reports', 'report', 'report_view', 'DESC', 20, array(),
        lang('You have not reported a post. Thank you for keeping the community kind.'), $report_row(false), array(
            'viewer_filter'           => true,
            'viewer_filter_submitter' => true,
            'viewer_filter_watcher'   => false,
            'viewer_filter_editor'    => true,
        )),

    // The moderators' lists. Only the last one, every report, pages through.
    'staff_reports_open' => $report_list('staff_reports', 'report', 'staff_report', 'ASC', 50,
        array($report_filter('status', 'is equal to', $report_statuses[0])),
        lang('No report is waiting.'), $report_row(true), array('max_results' => 50)),
    'staff_reports_all' => $report_list('staff_reports', 'report', 'staff_report', 'DESC', 20, array(),
        lang('No report yet.'), $report_row(true), array(
            'search_enabled' => true,
            'search_fields'  => array('post', 'reason', 'details', 'status', 'staff_note'),
            'search_width'   => 'full',
            'search_label'   => lang('Search by post, reason, details, status or note…'),
        )),

    // One report, for the moderators alone: the private page lets them in.
    // The post is printed as text: it is what the member typed.
    'staff_report_view' => array(
        'page'   => 'staff_report',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:report}}',
            'access_control'      => 'submitter_only',
            'title_field'         => 'reason',
            'title_suffix'        => lang('| All Reports'),
            'not_found_message'   => lang('This report could not be found.'),
        ),
        'tree'   => $widget_root($report_record('staff_reports', lang('All Reports'), lang('This report could not be found.'), true, array(
            $para(lang('To look at the post, copy its address into your browser: it is what the member typed, so it is never a link.'), 'small text-body-secondary mb-4'),
            $report_words(lang('Moderator note'), $report_sample['note'], 'staff_note'),
            $el('div', 'border-top pt-4 d-print-none', lang('Notes'), array(
                $heading('h2', lang('Notes'), 'h5 fw-semibold mb-1'),
                $para(lang('Notes are for the moderators only: say what you found and what you did.'), 'small text-body-secondary mb-3'),
            )),
        ))),
    ),

    // The moderators' notes under a report: oldest first, all on one page,
    // never featured, e-mailed to nobody.
    'report_notes' => array(
        'page'   => 'staff_report',
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
            'title'     => lang('Notes'),
            'empty'     => lang('No notes yet.'),
            'heading'   => lang('Add a note'),
            'label'     => lang('Your note'),
            'sign_in'   => lang('Sign in to add a note.'),
            'submit'    => lang('Add a note'),
            'watch_box' => '',
            'follow'    => '',
            'unfollow'  => '',
            'close'     => lang('Stop new notes'),
            'reopen'    => lang('Allow new notes'),
            'reply'     => false,
            'watch'     => false,
        )),
    ),

    // Every member post for the staff, in any status: no live rule here.
    'staff_posts_hidden' => $report_list('staff_posts', 'post_new', 'post', 'DESC', 20,
        array($report_filter('status', 'is equal to', $post_hidden)),
        lang('No post is hidden.'), $staff_post_row($post_hidden),
        array('order_by_field' => 'last_modified_date_and_time')),
    'staff_posts_all' => $report_list('staff_posts', 'post_new', 'post', 'DESC', 24, array(),
        lang('No member has shared a post yet.'), $staff_post_row(lang('Live')), array(
            'search_enabled' => true,
            'search_fields'  => array('caption', 'tags', 'topic', 'place', 'status'),
            'search_width'   => 'full',
            'search_label'   => lang('Search by caption, tag, topic, place or status…'),
        )),

    // The e-mails of the Reports form, drawn for the report just sent.
    // Opened by its address instead (?r=), the page shows the report only to
    // whoever sent it and to the moderators.
    'email_report_staff' => array(
        'page'   => 'email_report_new',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:report}}',
            'access_control'      => 'submitter_only',
            'not_found_message'   => lang('The report could not be found.'),
        ),
        'tree'   => $widget_root(array(
            $mail_heading(lang('A post was reported')),
            $mail_para(lang('A member reported a post in the community. Look at the post, hide it if it breaks the guidelines, then write a note under the report and set its Status.')),
            $mail_box(array(
                $mail_value(lang('Reason'), $report_sample['reason'], 'reason'),
                $mail_value(lang('Address of the post'), $report_sample['post'], 'post'),
                $mail_value(lang('Details of the report'), $report_sample['details'], 'details'),
                $mail_value(lang('Reported by'), $report_sample['by'], 'submitter_name'),
                $mail_value(lang('Account'), $report_sample['account'], 'submitter'),
            ), lang('Report Details')),
            $mail_small(lang('The address is what the member typed: copy it into your browser rather than following it.')),
            $mail_button(lang('Open the report'), '{{page:staff_report}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_report_member' => array(
        'page'   => 'email_report_received',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:report}}',
            'access_control'      => 'submitter_only',
            'not_found_message'   => lang('The report could not be found.'),
        ),
        'tree'   => $widget_root(array(
            $mail_heading(lang('Thank you for your report')),
            $mail_greeting('submitter_name', $report_sample['by']),
            $mail_para(lang('It reached the moderators. A moderator reads every report and takes off the site the posts that break the community guidelines.')),
            $mail_box(array(
                $mail_value(lang('Address of the post'), $report_sample['post'], 'post'),
                $mail_value(lang('Reason'), $report_sample['reason'], 'reason'),
            ), lang('Your Report')),
            $mail_para(lang('The member who shared the post is not told who reported it.')),
            $mail_button(lang('Open my report'), '{{page:report_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),

    // The comment of the comment e-mails: placed on the new comment and the
    // new reply e-mail, drawn for the comment the e-mail is about, flat and
    // alone. The pages that name these e-mails keep their comment settings.
    'email_comment_card' => array(
        'page'   => 'email_comment',
        'slug'   => lang('comments'),
        'config' => array(
            'regionType' => 'comments',
            'order'      => 'oldest',
            'featured'   => 'none',
            'layout'     => 'flat',
            'max_depth'  => 1,
            'per_page'   => 0,
        ),
        'tree'   => $mail_comment_tree,
    ),
);

return array('pages' => $report_pages, 'widgets' => $report_widgets);
