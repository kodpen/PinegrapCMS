<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Online Courses", its learning pages: each course's
 * classroom and lesson pages, My Courses, and the members' library, with
 * the questions under every lesson on the Comments widget. Included after
 * parts.php in the same scope (its builders and the school's data);
 * returns array('pages' => ..., 'widgets' => ...).
 *
 * Access is the folders': a course's classroom and lessons sit in the
 * course's own private folder (granted by its product once the order is
 * paid, or by the staff), except the first lesson, the free preview, which
 * is public; the library sits in the membership folder. My Courses lists
 * every course and the folder decides which classroom opens.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$learn_pages = array();
$learn_widgets = array();

// A lesson's video: the school puts its own file (or replaces the player
// with its video host's embed); until then the picture shows.
$lesson_video = function ($seed, $title) use ($el, $attr, $photo, $para) {
    return $el('div', 'ratio ratio-16x9 rounded-4 overflow-hidden bg-dark mb-4', lang('Video Frame'), array(
        $el('video', 'w-100 h-100 object-fit-cover', lang('Video'), array(
            $para(lang('Your browser cannot play this video.'), 'text-white p-3'),
        ), array('_attrs' => array(
            $attr('controls', ''), $attr('preload', 'none'), $attr('playsinline', ''),
            $attr('poster', $photo($seed, 1280, 720)), $attr('title', $title),
        ))),
    ));
};

// The list of a course's lessons by module, the current one marked; each
// links to its lesson page.
$lesson_nav = function ($course_key, $current = '') use ($courses, $el, $heading, $link, $icon, $span, $course_lessons) {
    $first = $course_lessons($course_key)[0]['key'];
    $groups = array();
    foreach ($courses[$course_key]['modules'] as $mn => $m) {
        $items = array();
        foreach ($m['lessons'] as $l) {
            $is_current = ($l['key'] === $current);
            $items[] = $el('a', 'list-group-item list-group-item-action d-flex align-items-center gap-2 small' . ($is_current ? ' active' : ''), $l['title'], array(
                $icon($l['key'] === $first ? 'bi-unlock' : 'bi-play-circle'),
                $span($l['title'], 'flex-grow-1', lang('Lesson Title')),
                $span(sprintf(lang('%d min'), $l['minutes']), 'text-nowrap', lang('Length')),
            ), array_merge(array('href' => '{{page:lesson_' . $l['key'] . '}}'), $is_current ? array('_attrs' => array(array('name' => 'aria-current', 'value' => 'page'))) : array()));
        }
        $groups[] = $el('div', 'mb-3', $m['title'], array(
            $heading('h3', sprintf(lang('Module %d: %s'), $mn + 1, $m['title']), 'h6 text-uppercase small fw-bold text-body-secondary mb-2'),
            $el('div', 'list-group', lang('Lessons'), $items),
        ));
    }
    return $el('nav', '', lang('Lessons'), $groups, array('_attrs' => array(array('name' => 'aria-label', 'value' => lang('Lessons')))));
};

// The questions under a lesson: the lesson page's own thread. Newest
// first, answers under the question, the instructor's answer pinned on
// top; students may follow the thread.
$questions_widget = function ($page_key) use ($comment_thread) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('questions'),
        'config' => array(
            'regionType' => 'comments',
            'order'      => 'newest',
            'featured'   => 'first',
            'layout'     => 'threaded',
            'max_depth'  => 2,
            'per_page'   => 20,
        ),
        'tree'   => $comment_thread(array(
            'title'     => lang('Questions'),
            'empty'     => lang('No questions yet. Stuck somewhere? Ask here and the instructor answers.'),
            'heading'   => lang('Ask a question'),
            'label'     => lang('Your question'),
            'sign_in'   => lang('Sign in to ask a question.'),
            'submit'    => lang('Ask'),
            'watch_box' => lang('Notify me when someone answers.'),
            'follow'    => lang('Notify me of new answers'),
            'unfollow'  => lang('Stop notifying me'),
            'close'     => lang('Stop new questions'),
            'reopen'    => lang('Allow new questions'),
            'reply'     => true,
            'watch'     => true,
        )),
    );
};

foreach ($courses as $key => $c) {
    $lessons = $course_lessons($key);
    $teacher = $instructors[$c['instructor']];

    // ── The classroom ───────────────────────────────────────────────────
    $learn_pages[] = array(
        'key'              => 'learn_' . $key,
        'name'             => $c['slug'] . '-' . lang('classroom'),
        'folder'           => $c['folder'],
        'title'            => sprintf(lang('%s: Classroom'), $c['title']),
        'meta_description' => $c['summary'],
        'search'           => false,
        'sitemap'          => false,
        'tree'             => $page(array(
            $page_header($c['title'], lang('Your classroom: the lessons in order, your project and where to ask.'), array(
                $el('div', 'd-flex flex-wrap gap-2 mt-3', lang('Buttons'), array(
                    $link(lang('Start the first lesson'), '{{page:lesson_' . $lessons[0]['key'] . '}}', 'btn btn-primary px-4'),
                    $link(lang('My Courses'), '{{page:my_courses}}', 'btn btn-outline-secondary px-4'),
                )),
            )),
            $el('section', 'py-5', lang('Classroom'), array(
                $container(array(
                    $row(array(
                        $col(array(
                            $heading('h2', lang('Lessons'), 'h4 fw-bold mb-3'),
                            $lesson_nav($key),
                        ), '', '7'),
                        $col(array(
                            $el('div', 'card border-0 bg-body-tertiary rounded-4 mb-4', lang('Assignment'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $heading('h2', lang('Your project'), 'h5 fw-bold mb-2'),
                                    $para($c['assignment'], 'mb-3'),
                                    $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                        $link(lang('Send your project'), '{{page:assignment}}?course=' . rawurlencode($c['title']), 'btn btn-primary'),
                                        $link(lang('My Assignments'), '{{page:my_assignments}}', 'btn btn-outline-secondary'),
                                    )),
                                )),
                            )),
                            $el('div', 'card border-0 shadow-sm rounded-4', lang('Instructor'), array(
                                $el('div', 'card-body d-flex gap-3 align-items-start p-4', lang('Card Body'), array(
                                    $image($portrait($teacher['name'], 128), $teacher['name'], 'rounded-circle object-fit-cover flex-shrink-0', array('width' => '64', 'height' => '64')),
                                    $el('div', '', lang('Text'), array(
                                        $heading('h2', $teacher['name'], 'h6 fw-bold mb-1'),
                                        $para(lang('Ask under any lesson: questions are answered within two working days. For anything else, write to us.'), 'small text-body-secondary mb-2'),
                                        $link(lang('Write to us'), '{{page:contact}}', 'small fw-semibold'),
                                    )),
                                )),
                            )),
                        ), '', '5'),
                    ), '4', '', 'g-lg-5'),
                )),
            )),
        ), false),
    );

    // ── The lessons ─────────────────────────────────────────────────────
    foreach ($lessons as $n => $l) {
        $is_preview = ($n === 0);
        $page_key = 'lesson_' . $l['key'];
        $steps = array();
        if ($n > 0) {
            $steps[] = $link(lang('Previous lesson'), '{{page:lesson_' . $lessons[$n - 1]['key'] . '}}', 'btn btn-outline-secondary');
        }
        if ($n < count($lessons) - 1) {
            $steps[] = $link(lang('Next lesson'), '{{page:lesson_' . $lessons[$n + 1]['key'] . '}}', 'btn btn-primary ms-auto');
        } else {
            $steps[] = $link(lang('Send your project'), '{{page:assignment}}?course=' . rawurlencode($c['title']), 'btn btn-primary ms-auto');
        }
        // The preview ends with the way in, the other lessons with the way
        // back to the classroom.
        $aside = $is_preview
            ? $el('div', 'alert alert-primary d-flex flex-column flex-sm-row align-items-sm-center gap-3 mt-4', lang('Enrol'), array(
                $el('div', 'flex-grow-1', lang('Text'), array(
                    $heading('h2', lang('Liked this lesson?'), 'h6 fw-bold mb-1'),
                    $para(sprintf(lang('The other %d lessons, the project and the instructor\'s feedback come with the course.'), count($lessons) - 1), 'small mb-0'),
                )),
                $link(lang('See the course'), '{{page:course_' . $key . '}}', 'btn btn-primary text-nowrap'),
            ))
            : $link(lang('Back to the classroom'), '{{page:learn_' . $key . '}}', 'd-inline-block small mt-4');

        $learn_pages[] = array(
            'key'              => $page_key,
            'name'             => $c['slug'] . '-' . ($n + 1),
            'folder'           => $is_preview ? 'public' : $c['folder'],
            'title'            => $l['title'],
            'meta_description' => $l['text'],
            'search'           => $is_preview,
            'sitemap'          => $is_preview,
            'comments'         => array('label' => lang('Question'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new answer under a lesson'),
                                        'notify_email' => '{{site_email}}'),
            'tree'             => $page(array(
                $el('section', 'py-4 py-lg-5', lang('Lesson'), array(
                    $container(array(
                        $row(array(
                            $col(array(
                                $el('nav', '', lang('Breadcrumb'), array(
                                    $el('ol', 'breadcrumb small mb-2', lang('Breadcrumb List'), array(
                                        $el('li', 'breadcrumb-item', lang('Breadcrumb Item'), array($link($c['title'], '{{page:course_' . $key . '}}', 'link-secondary text-decoration-none'))),
                                        $el('li', 'breadcrumb-item active', lang('Breadcrumb Item'), array(), array('text' => $l['module'], '_attrs' => array($attr('aria-current', 'page')))),
                                    )),
                                ), array('_attrs' => array($attr('aria-label', lang('Breadcrumb'))))),
                                $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-2', lang('Badges'), $is_preview
                                    ? array($span(lang('Free preview'), 'badge rounded-pill text-bg-success', lang('Preview')), $span(sprintf(lang('%d min'), $l['minutes']), 'small text-body-secondary', lang('Length')))
                                    : array($span(sprintf(lang('Lesson %d of %d'), $n + 1, count($lessons)), 'small fw-semibold text-primary', lang('Lesson Number')), $span(sprintf(lang('%d min'), $l['minutes']), 'small text-body-secondary', lang('Length')))),
                                $heading('h1', $l['title'], 'h2 fw-bold mb-4'),
                                $lesson_video('lesson-' . $l['key'], $l['title']),
                                $el('div', 'mb-4', lang('Lesson Text'), array(
                                    $para($l['text'], 'lead'),
                                    $para(lang('Watch the video first, then try it yourself before you read on. Every lesson ends with a small task: do it, and ask below if something does not work.'), 'text-body-secondary'),
                                ), $is_preview ? $searchable('lesson-text', $subjects[$c['subject']]['topics']) : array()),
                                $el('div', 'd-flex flex-wrap gap-2', lang('Lesson Steps'), $steps),
                                $aside,
                            ), '', '8'),
                            $col(array(
                                $el('div', 'position-sticky top-0 pt-lg-2', lang('Course Lessons'), array(
                                    $heading('h2', $c['title'], 'h6 fw-bold mb-3'),
                                    $lesson_nav($key, $l['key']),
                                )),
                            ), '', '4'),
                        ), '4', '', 'g-lg-5'),
                    )),
                )),
                $comments_section(lang('Questions'), 'questions_' . $l['key']),
            ), false),
        );
        $learn_widgets['questions_' . $l['key']] = $questions_widget($page_key);
    }
}

// ── My Courses ──────────────────────────────────────────────────────────
// Every course with the way into its classroom; the course's folder lets
// in whoever is enrolled, and shows the sign-in or access notice to the
// others.
$my_course_cards = array();
foreach ($courses as $key => $c) {
    $my_course_cards[] = $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden', $c['title'], array(
            $el('div', 'ratio ratio-16x9 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('course-' . $key, 800, 450), $c['title'], 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('div', 'd-flex flex-wrap gap-2 mb-2', lang('Badges'), array($subject_badge($c['subject']), $level_badge($c['level']))),
                $heading('h2', $c['title'], 'h5 fw-bold mb-3'),
                $el('div', 'd-grid gap-2 mt-auto', lang('Buttons'), array(
                    $link(lang('Open the classroom'), '{{page:learn_' . $key . '}}', 'btn btn-primary'),
                    $link(lang('About the course'), '{{page:course_' . $key . '}}', 'btn btn-link btn-sm'),
                )),
            )),
        )),
    ), '6', '4');
}

$learn_pages[] = array(
    'key'              => 'my_courses',
    'name'             => lang('my-courses'),
    'folder'           => 'registration',
    'title'            => lang('My Courses'),
    'meta_description' => lang('The classrooms of the courses you are enrolled in.'),
    'search'           => false,
    'sitemap'          => false,
    'tree'             => $page(array(
        $page_header(lang('My Courses'), lang('Open the classroom of a course you are enrolled in, or see a course\'s page to enrol.')),
        $el('section', 'py-5', lang('Courses'), array(
            $container(array(
                $row($my_course_cards, '4'),
                $el('div', 'd-flex flex-wrap gap-3 mt-5 pt-4 border-top small', lang('More'), array(
                    $link(lang('My Assignments'), '{{page:my_assignments}}', 'fw-semibold'),
                    $link(lang('Members\' Library'), '{{page:library}}', 'fw-semibold'),
                    $link(lang('Inbox'), '{{page:inbox}}', 'fw-semibold'),
                )),
            )),
        )),
    ), false),
);

// ── The members' library ────────────────────────────────────────────────
$library_cards = array();
foreach ($library as $n => $item) {
    $library_cards[] = $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden', $item['title'], array(
            $lesson_video('library-' . ($n + 1), $item['title']),
            $el('div', 'card-body pt-0 px-4 pb-4', lang('Card Body'), array(
                $el('p', 'd-flex align-items-center gap-2 small text-primary mb-2', lang('Kind'), array($icon($item['icon']), $span($item['length'], '', lang('Length')))),
                $heading('h2', $item['title'], 'h5 fw-bold mb-2'),
                $para($item['text'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), '6', '4');
}

$learn_pages[] = array(
    'key'              => 'library',
    'name'             => lang('members-library'),
    'folder'           => 'members',
    'title'            => lang('Members\' Library'),
    'meta_description' => lang('Recordings of the monthly live sessions and workshops, for members.'),
    'search'           => false,
    'sitemap'          => false,
    'tree'             => $page(array(
        $page_header(lang('Members\' Library'), lang('The monthly live sessions and workshops, recorded for members. A new one every month.')),
        $el('section', 'py-5', lang('Library'), array(
            $container(array(
                $row($library_cards, '4'),
                $el('div', 'p-4 rounded-4 bg-primary-subtle d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mt-5', lang('Live Sessions'), array(
                    $el('div', '', lang('Text'), array(
                        $heading('h2', lang('Join the next live session'), 'h5 fw-bold mb-1'),
                        $para(lang('Members get the link by e-mail a week before. Send your question in advance and it is answered first.'), 'small mb-0'),
                    )),
                    $link(lang('Send a question'), '{{page:contact}}', 'btn btn-primary px-4 text-nowrap'),
                )),
            )),
        )),
    ), false),
);

return array('pages' => $learn_pages, 'widgets' => $learn_widgets);
