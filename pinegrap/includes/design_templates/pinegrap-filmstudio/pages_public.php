<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Film Studio", its public pages: home with the
 * showreel, the services and one page per service, how a production runs,
 * the studio and its spaces, the crew, about and the questions page.
 * Included after parts.php in the same scope (its builders and the
 * studio's data); returns array('pages' => ..., 'widgets' => ...).
 *
 * Every figure comes from parts.php ($studio, $services, $industries,
 * $process, $spaces, $crew, $why_us, $figures, $testimonials), so a change
 * made there reaches every page here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}


// A still in a framed ratio, loaded lazily unless it is the first thing a
// page shows. Films are wide, so the frame is 16:9 unless asked otherwise.
$framed = function ($src, $alt, $ratio = '16x9', $lazy = true) use ($el, $image, $attr) {
    $extra = $lazy ? array('_attrs' => array($attr('loading', 'lazy'))) : array();
    return $el('div', 'ratio ratio-' . $ratio . ' rounded-3 overflow-hidden bg-body-tertiary shadow-sm', lang('Picture Frame'), array(
        $image($src, $alt, 'object-fit-cover', $extra),
    ));
};

// A band of text beside a picture; $flip puts the picture first from lg up.
// The band is a search section under its anchor $id, with the topics a
// visitor may search for that it does not say itself.
$story_band = function ($id, $eyebrow, $title, $texts, $picture, $alt, $flip = false, $more = array(), $topics = '', $class = 'py-5')
    use ($el, $heading, $para, $container, $row, $col, $framed, $searchable) {
    $words = array(
        $para($eyebrow, 'pfm-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'pfm-display h2 mb-3'),
    );
    $last = count($texts) - 1;
    foreach (array_values($texts) as $n => $t) {
        $words[] = $para($t, 'text-body-secondary ' . ($n === $last && empty($more) ? 'mb-0' : 'mb-3'));
    }
    return $el('section', $class, $title, array(
        $container(array(
            $row(array(
                $col(array_merge($words, $more), '', '6', '', '12', $flip ? 'order-lg-last' : ''),
                $col(array($framed($picture, $alt)), '', '6'),
            ), '4', '', 'g-lg-5 align-items-center'),
        )),
    ), $searchable($id, $topics));
};

// A tile with an icon, a title and a line: why clients choose the studio
// and the industries it films for.
$feature_tile = function ($icon_name, $title, $text, $lg = '3', $more = array()) use ($el, $heading, $para, $col, $icon_tile) {
    return $col(array(
        $el('div', 'h-100 p-4 rounded-3 bg-body border shadow-sm', $title, array_merge(array(
            $icon_tile($icon_name, 'mb-3'),
            $heading('h3', $title, 'pfm-display h5 mb-2'),
            $para($text, 'small text-body-secondary mb-0'),
        ), $more)),
    ), '6', $lg);
};

// A band that sends a question to the contact page.
$ask_band = function ($title, $text) use ($el, $heading, $para, $link, $container) {
    return $el('section', 'py-5', lang('Write to Us'), array(
        $container(array(
            $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Text'), array(
                $el('div', '', lang('Text'), array(
                    $heading('h2', $title, 'pfm-display h4 mb-1'),
                    $para($text, 'text-body-secondary mb-0'),
                )),
                $link(lang('Contact Us'), '{{page:contact}}', 'btn btn-primary px-4 text-nowrap'),
            )),
        )),
    ));
};

// The band of figures under a page's opening.
$figures_band = function () use ($el, $container, $figure_row) {
    return $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
        $container(array($figure_row())),
    ));
};

// The cards and tiles of the shared data, in their order.
$why_tiles = array();
foreach ($why_us as $w) $why_tiles[] = $feature_tile($w['icon'], $w['title'], $w['text']);
$service_cols = array();
foreach (array_keys($services) as $key) $service_cols[] = $service_card($key);
$testimonial_cols = array();
foreach (array_keys($testimonials) as $n) $testimonial_cols[] = $testimonial_card($n);
$crew_cols = array();
foreach (array_keys($crew) as $n) $crew_cols[] = $crew_card($n);
$space_cols = array();
foreach (array_keys($spaces) as $key) $space_cols[] = $space_card($key);
$process_items = array();
foreach ($process as $n => $s) $process_items[] = $step($n + 1, $s['title'], $s['text']);
// An industry's tile links the work list filtered on it, in the words the
// Industry list uses.
$industry_tiles = array();
foreach ($industries as $s) {
    $industry_tiles[] = $feature_tile($s['icon'], $s['title'], $s['text'], '4', array(
        $link(lang('See the work'), '{{page:projects}}?' . $query(array('industry' => $s['title'])), 'd-inline-block small fw-semibold link-primary mt-3'),
    ));
}

// ── Home ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('A film and video production studio: commercials, brand films, music videos and documentaries, shot and finished in house.'),
    'tree'             => $page(array(
        // The way in, on a dark band: the studio in a sentence, the two
        // ways on, and the showreel.
        $el('section', 'pfm-hero py-5 overflow-hidden bg-body text-body', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Film and video production'), 'pfm-eyebrow text-uppercase fw-semibold text-primary mb-3'),
                        $heading('h1', lang('Stories worth watching'), 'pfm-display display-3 mb-3'),
                        $para($studio['tagline'] . '.', 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Start a Project'), '{{page:enquiry}}', 'btn btn-primary btn-lg px-4'),
                            $link(lang('See Our Work'), '{{page:projects}}', 'btn btn-outline-light btn-lg px-4'),
                        )),
                    ), '', '5'),
                    $col(array($showreel('showreel', lang('Our showreel'))), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), array_merge($searchable('fm-showreel', lang('showreel, reel, video production, film company')),
            array('_attrs' => array($attr('data-bs-theme', 'dark'))))),

        $figures_band(),

        // The featured projects, read from the Projects collection.
        $el('section', 'py-5', lang('Featured Work'), array(
            $container(array(
                $section_header(lang('Our work'), lang('Films we are proud of'), lang('All Work'), '{{page:projects}}'),
                $widget('home_projects'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('Our Services'), array(
            $container(array(
                $section_header(lang('What we make'), lang('From the first idea to the final cut'), lang('All Services'), '{{page:services}}'),
                $row($service_cols, '4'),
            )),
        ), $searchable('fm-home-services', lang('commercial, advert, brand film, music video, documentary, corporate video, editing'))),

        $el('section', 'py-5', lang('How We Work'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Step by step'), 'pfm-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h2', lang('From the brief to the master'), 'pfm-display h2 mb-4'),
                        $el('ol', 'list-unstyled mb-4', lang('Steps'), $process_items),
                        $link(lang('How We Work'), '{{page:process}}', 'btn btn-outline-secondary px-4'),
                    ), '', '6'),
                    $col(array($framed($photo('home-process', 1280, 720), lang('A director and a camera operator watching a take on a monitor'))), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('fm-home-process', lang('how it works, treatment, pre-production, shoot, edit, delivery'))),

        $el('section', 'py-5 bg-body-tertiary', lang('Why Work with Us'), array(
            $container(array(
                $section_header(lang('The way we work'), lang('Why film with us'), lang('About Us'), '{{page:about}}'),
                $row($why_tiles, '4'),
            )),
        ), $searchable('fm-why-us', lang('treatment, producer, in house, fixed budget, price'))),

        // What clients say: sample words for the studio to replace, and
        // beside them its own Google reviews, which show once Google
        // Reviews is set up under Site Settings.
        $el('section', 'py-5', lang('Testimonials'), array(
            $container(array(
                $section_header(lang('Our clients'), lang('What clients say')),
                $row($testimonial_cols, '4'),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('From the Journal'), array(
            $container(array(
                $section_header(lang('Journal'), lang('Stories from the set'), lang('All Stories'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),
    )),
);

// ── Services ────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'services',
    'name'             => lang('services'),
    'folder'           => 'public',
    'title'            => lang('Our Services'),
    'meta_description' => lang('Commercials, brand films, music videos, documentaries, corporate and event films, and post-production.'),
    'tree'             => $page(array(
        $page_header(lang('Our Services'), lang('Six kinds of film we make, from a six-second spot to a feature documentary.')),
        $el('section', 'py-5', lang('Our Services'), array(
            $container(array(
                $row($service_cols, '4'),
            )),
        ), $searchable('fm-services', lang('video production, filming, advert, brand film, music video, documentary, editing, grading'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Industries'), array(
            $container(array(
                $section_header(lang('Who we film for'), lang('Industries we know'), lang('All Work'), '{{page:projects}}'),
                $row($industry_tiles, '4'),
            )),
        ), $searchable('fm-industries', lang('cars, fashion, food, music, technology, charity'))),
        $ask_band(lang('Not sure what kind of film you need?'), lang('Tell us what the film must do and a producer calls you back within a working day.')),
    )),
);

// One page per service: what it is, what it covers, the work made with it
// and the enquiry with the service chosen. Its address is a translated
// slug, so a Turkish site reads Turkish addresses.
$service_slugs = array(
    'commercials'  => lang('commercials'),
    'brand_films'  => lang('brand-films'),
    'music_videos' => lang('music-videos'),
    'documentary'  => lang('documentaries'),
    'corporate'    => lang('corporate-and-event-films'),
    'post'         => lang('post-production'),
);
foreach ($services as $key => $s) {
    $public_pages[] = array(
        'key'              => 'service_' . $key,
        'name'             => $service_slugs[$key],
        'folder'           => 'public',
        'title'            => $s['title'],
        'meta_description' => $s['summary'],
        'tree'             => $page(array(
            $page_header($s['title'], $s['summary']),
            $el('section', 'py-5', $s['title'], array(
                $container(array(
                    $row(array(
                        $col(array(
                            $icon_tile($s['icon'], 'mb-3'),
                            $para($s['text'], 'lead mb-4'),
                            $heading('h2', lang('What it covers'), 'pfm-display h4 mb-3'),
                            $check_list($s['points'], 'list-unstyled mb-4'),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                $link(lang('Start a Project'), '{{page:enquiry}}?' . $query(array('service' => $s['title'])), 'btn btn-primary px-4'),
                                $link(lang('See the work'), '{{page:projects}}?' . $query(array('service' => $s['title'])), 'btn btn-outline-secondary px-4'),
                            )),
                        ), '', '6'),
                        $col(array($framed($photo('service-' . $key, 1280, 720), $s['title'])), '', '6'),
                    ), '4', '', 'g-lg-5 align-items-center'),
                )),
            ), $searchable('fm-service-' . str_replace('_', '-', $key))),
            $el('section', 'py-5 bg-body-tertiary', lang('How We Work'), array(
                $container(array(
                    $section_header(lang('Step by step'), lang('How we make your film'), lang('How We Work'), '{{page:process}}'),
                    $el('ol', 'list-unstyled mb-0', lang('Steps'), $process_items),
                )),
            )),
        )),
    );
}

// ── How we work ─────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'process',
    'name'             => lang('how-we-work'),
    'folder'           => 'public',
    'title'            => lang('How We Work'),
    'meta_description' => lang('How a production runs with us: brief and treatment, pre-production, the shoot, the edit and delivery.'),
    'tree'             => $page(array(
        $page_header(lang('How We Work'), lang('Five stages from the first call to the master, with one producer all the way.')),
        $el('section', 'py-5', lang('Steps'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('ol', 'list-unstyled mb-4', lang('Steps'), $process_items),
                        $link(lang('Start a Project'), '{{page:enquiry}}', 'btn btn-primary px-4'),
                    ), '', '6'),
                    $col(array($framed($photo('process', 1280, 720), lang('A storyboard pinned on the wall of the production office'))), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('fm-process', lang('how it works, treatment, storyboard, casting, shoot, edit, grade, delivery'))),
        $el('section', 'py-5 bg-body-tertiary', lang('What You Can Count On'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Your budget'), 'pfm-display h3 mb-3'),
                        $check_list(array(
                            lang('A budget for the film we agree, in writing before the shoot'),
                            lang('Extra shoot days or versions are priced before we make them'),
                            lang('Payments in stages, as the film takes shape'),
                        )),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('Your film'), 'pfm-display h3 mb-3'),
                        $check_list(array(
                            lang('Two rounds of changes on every cut'),
                            lang('Music and footage licensed for where the film will be shown'),
                            lang('The master and the project files kept safe after delivery'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('fm-promises', lang('budget, payments, changes, revisions, music licence, archive'))),
        $ask_band(lang('A question about how we work?'), lang('Write to us and a producer answers, usually the same day.')),
    )),
);

// ── The studio ──────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'studio',
    'name'             => lang('the-studio'),
    'folder'           => 'public',
    'title'            => lang('The Studio'),
    'meta_description' => lang('Our sound stage, cyclorama, edit suites and grading and sound suite, for our own films and for hire.'),
    'tree'             => $page(array(
        $page_header(lang('The Studio'), lang('Stages, edit rooms and a grading theatre under one roof, for our own films and for yours.')),
        $el('section', 'py-5', lang('Our Spaces'), array(
            $container(array(
                $section_header(lang('Under one roof'), lang('Our spaces'), lang('Hire the Studio'), '{{page:studio_hire}}'),
                $row($space_cols, '4'),
            )),
        ), $searchable('fm-spaces', lang('sound stage, green screen, infinity cove, edit suite, colour grading, sound mix'))),
        $story_band('fm-hire', lang('For hire'), lang('Shoot in our studio'),
            array(lang('Our stages and suites are free for hire on the days our own crew does not need them. Ask for a space and a date, and we confirm within a working day.'),
                lang('Every hire includes a studio manager on the day; lights, grip and crew can be added.')),
            $photo('studio-hire', 1280, 720), lang('A lit set on the sound stage between takes'), true,
            array($link(lang('Hire the Studio'), '{{page:studio_hire}}', 'btn btn-primary px-4')),
            lang('studio hire, rent, stage rental, price, book'), 'py-5 bg-body-tertiary'),
        $figures_band(),
    )),
);

// ── The crew ────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'crew',
    'name'             => lang('crew'),
    'folder'           => 'public',
    'title'            => lang('The Crew'),
    'meta_description' => lang('The directors, producers, editors and sound designers who make our films.'),
    'tree'             => $page(array(
        $page_header(lang('The Crew'), lang('The people behind the camera, in the edit and at the mixing desk.')),
        $el('section', 'py-5', lang('The Crew'), array(
            $container(array(
                $row($crew_cols, '4'),
            )),
        ), $searchable('fm-crew', lang('people, director, producer, editor, colourist, sound, team'))),
        $story_band('fm-join-us', lang('Crew calls'), lang('Make films with us'),
            array(lang('We hire for each production and keep a core crew all year. Runners learn every part of a shoot, and assistants grow into their own roles.')),
            $photo('crew-join', 1280, 720), lang('A camera assistant pulling focus on set'), true,
            array($link(lang('See the crew calls'), '{{page:careers}}', 'btn btn-primary px-4')),
            lang('jobs, freelance, internship, work for us, crew'), 'py-5 bg-body-tertiary'),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('An independent production company started by a director and a cinematographer: one producer and everything in house.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), $studio['tagline'] . '.'),
        $story_band('fm-our-story', lang('Our story'), lang('One camera and a borrowed light'),
            array($studio['story'], lang('Our own crew writes, shoots and finishes most of our films, so we answer for every frame.')),
            $photo('about-studio', 1280, 720), lang('The crew on set in the studio'), false, array(),
            lang('history, founders, company, who we are')),
        $figures_band(),
        $el('section', 'py-5', lang('Why Work with Us'), array(
            $container(array(
                $section_header(lang('The way we work'), lang('Why film with us')),
                $row($why_tiles, '4'),
            )),
        ), $searchable('fm-about-why', lang('treatment, producer, in house, fixed budget, price'))),
        $el('section', 'py-5 bg-body-tertiary', lang('The Crew'), array(
            $container(array(
                $section_header(lang('Our people'), lang('The people who make your film'), lang('The Crew'), '{{page:crew}}'),
                $row(array_slice($crew_cols, 0, 3), '4'),
            )),
        )),
        $ask_band(lang('Want to make a film with us?'), lang('Write to us about your idea, or see the crew calls open now.')),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Two accordions: before the shoot and during the production. Each
// question is a search section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'pfm-display h4 mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about treatments, budgets, timings, changes, music rights and hiring the studio.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Budgets, timings and life on set. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('fm-faq-before', lang('Before the shoot'), array(
                            array(lang('What does a film cost?'), lang('It depends on the days of shooting, the crew, the cast and the places. After the first call we send a treatment with a first budget, so you know before you commit.'), lang('price, cost, budget, quote, how much')),
                            array(lang('How long does a film take?'), lang('A short online film can be ready in two or three weeks; a commercial with casting and locations usually takes six to eight. We agree the dates in the treatment.'), lang('how long, timing, deadline, schedule')),
                            array(lang('Do you write the idea, or do we?'), lang('Either. We can write the idea and the script from your brief, or shoot a script your agency has written.'), lang('idea, script, agency, creative')),
                            array(lang('How do I follow my enquiry?'), lang('Sending the enquiry opens an account for you. Under My Account you see the state of each enquiry and can write to us there.'), lang('my account, status, follow, message')),
                        )),
                        $faq_group('fm-faq-during', lang('During the production'), array(
                            array(lang('Can we come to the shoot?'), lang('Yes, and we recommend it: the producer keeps a place for you by the monitor and checks each scene with you before we move on.'), lang('set visit, attend, shoot day, client')),
                            array(lang('How many changes can we ask for?'), lang('Two rounds of changes on every cut are in the budget. More rounds are priced before we make them.'), lang('changes, revisions, feedback, amendments')),
                            array(lang('Who owns the film?'), lang('You do, for the uses agreed in the budget. Music, archive footage and actors are licensed for those uses; wider use is agreed and priced first.'), lang('rights, copyright, licence, usage, music')),
                            array(lang('Can we hire the studio without your crew?'), lang('Yes. Ask for a space and a date on the studio hire page; a studio manager is with you on the day.'), lang('studio hire, rent, stage, dry hire')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// The home page's lists: its featured projects (home_projects) come with
// the projects, its Journal stories (home_posts) and the Google reviews
// (google_reviews) with the standard pages; this part places no widget of
// its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
