<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Recruitment Agency", its public pages: home, the
 * sectors and one page per sector, the cities, the pages for candidates and
 * for employers, about, the consultants and the questions page. Included
 * after parts.php in the same scope (its builders and the agency's data);
 * returns array('pages' => ..., 'widgets' => ...).
 *
 * Every figure comes from parts.php ($agency, $sectors, $cities,
 * $consultants, $candidate_steps, $employer_steps, $employer_services,
 * $why_us, $figures, $testimonials), so a change made there reaches every
 * page here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}


// A picture in a framed ratio, loaded lazily unless it is the first thing
// a page shows.
$framed = function ($src, $alt, $ratio = '4x3', $lazy = true) use ($el, $image, $attr) {
    $extra = $lazy ? array('_attrs' => array($attr('loading', 'lazy'))) : array();
    return $el('div', 'ratio ratio-' . $ratio . ' rounded-4 overflow-hidden bg-body-tertiary shadow-sm', lang('Picture Frame'), array(
        $image($src, $alt, 'object-fit-cover', $extra),
    ));
};

// A band of text beside a picture; $flip puts the picture first from lg up.
// The band is a search section under its anchor $id, with the topics a
// visitor may search for that it does not say itself.
$story_band = function ($id, $eyebrow, $title, $texts, $picture, $alt, $flip = false, $more = array(), $topics = '', $class = 'py-5')
    use ($el, $heading, $para, $container, $row, $col, $framed, $searchable) {
    $words = array(
        $para($eyebrow, 'prc-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'prc-display h2 mb-3'),
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

// A tile with an icon, a title and a line: why people choose the agency.
$feature_tile = function ($icon_name, $title, $text, $lg = '3') use ($el, $heading, $para, $col, $icon_tile) {
    return $col(array(
        $el('div', 'h-100 p-4 rounded-4 bg-body border shadow-sm', $title, array(
            $icon_tile($icon_name, 'mb-3'),
            $heading('h3', $title, 'prc-display h5 mb-2'),
            $para($text, 'small text-body-secondary mb-0'),
        )),
    ), '6', $lg);
};

// A band that sends a question to the contact page.
$ask_band = function ($title, $text) use ($el, $heading, $para, $link, $container) {
    return $el('section', 'py-5', lang('Write to Us'), array(
        $container(array(
            $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Text'), array(
                $el('div', '', lang('Text'), array(
                    $heading('h2', $title, 'prc-display h4 mb-1'),
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

// The steps of one side (candidates or employers) as a numbered list.
$steps_list = function ($steps) use ($el, $step) {
    $items = array();
    foreach ($steps as $n => $s) $items[] = $step($n + 1, $s['title'], $s['text']);
    return $el('ol', 'list-unstyled mb-4', lang('Steps'), $items);
};

// A row of small links that open the job list filtered: by city on the
// home's opening, by city on a sector's page.
$filter_links = function ($label, $param, $titles) use ($el, $span, $link, $query) {
    $children = array($span($label, 'small fw-semibold text-body-secondary me-1', lang('Label')));
    foreach ($titles as $t) {
        $children[] = $link($t, '{{page:jobs}}?' . $query(array($param => $t)), 'btn btn-sm btn-outline-secondary rounded-pill');
    }
    return $el('div', 'd-flex flex-wrap align-items-center gap-2', $label, $children);
};

// The cards and tiles of the shared data, in their order.
$why_tiles = array();
foreach ($why_us as $w) $why_tiles[] = $feature_tile($w['icon'], $w['title'], $w['text']);
$sector_cols = array();
foreach (array_keys($sectors) as $key) $sector_cols[] = $sector_card($key);
$city_cols = array();
foreach (array_keys($cities) as $key) $city_cols[] = $city_card($key);
$service_cols = array();
foreach (array_keys($employer_services) as $key) $service_cols[] = $service_card($key);
$consultant_cols = array();
foreach (array_keys($consultants) as $n) $consultant_cols[] = $consultant_card($n);
$testimonial_cols = array();
$employer_testimonial_cols = array();
$candidate_testimonial_cols = array();
foreach ($testimonials as $n => $t) {
    $testimonial_cols[] = $testimonial_card($n);
    if ($t['kind'] === 'employer') $employer_testimonial_cols[] = $testimonial_card($n);
    else $candidate_testimonial_cols[] = $testimonial_card($n);
}

// ── Home ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('A recruitment agency for six sectors in five cities: find a job for free, or receive a shortlist of checked candidates in five working days.'),
    'tree'             => $page(array(
        // The way in: the agency in a sentence, the two ways on, the jobs
        // by city and a photo of an interview.
        $el('section', 'prc-hero py-5 overflow-hidden', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Recruitment agency'), 'prc-eyebrow text-uppercase fw-semibold text-primary mb-3'),
                        $heading('h1', lang('Your next job starts with a conversation'), 'prc-display display-4 mb-3'),
                        $para($agency['tagline'] . '.', 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2 mb-4', lang('Buttons'), array(
                            $link(lang('Find a Job'), '{{page:jobs}}', 'btn btn-primary btn-lg px-4'),
                            $link(lang('Hire Staff'), '{{page:hire}}', 'btn btn-outline-secondary btn-lg px-4'),
                        )),
                        $filter_links(lang('Jobs in'), 'city', $city_titles),
                    ), '', '6'),
                    $col(array(
                        $framed($photo('hero', 1400, 1050), lang('A consultant and a candidate talking across a table'), '4x3', false),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('rc-home-hero', lang('jobs, vacancies, careers, recruitment agency, employment agency, headhunter'))),

        $figures_band(),

        $el('section', 'py-5', lang('Sectors'), array(
            $container(array(
                $section_header(lang('Where we recruit'), lang('Six sectors, each with its own consultant'), lang('All Sectors'), '{{page:sectors}}'),
                $row($sector_cols, '4'),
            )),
        ), $searchable('rc-home-sectors', lang('technology, finance, engineering, healthcare, sales, logistics'))),

        // The featured jobs, read from the Jobs collection.
        $el('section', 'py-5 bg-body-tertiary', lang('Featured Jobs'), array(
            $container(array(
                $section_header(lang('Open now'), lang('Featured jobs'), lang('All Jobs'), '{{page:jobs}}'),
                $widget('home_jobs'),
            )),
        )),

        // The two sides, each with its own steps and its own way in.
        $el('section', 'py-5', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'h-100 p-4 p-lg-5 rounded-4 border', lang('For Candidates'), array(
                            $para(lang('For candidates'), 'prc-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                            $heading('h2', lang('Find your next job'), 'prc-display h3 mb-4'),
                            $steps_list($candidate_steps),
                            $link(lang('Send Your CV'), '{{page:apply}}', 'btn btn-primary px-4'),
                        )),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'h-100 p-4 p-lg-5 rounded-4 bg-body-tertiary', lang('For Employers'), array(
                            $para(lang('For employers'), 'prc-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                            $heading('h2', lang('Find your next hire'), 'prc-display h3 mb-4'),
                            $steps_list($employer_steps),
                            $link(lang('Hire Staff'), '{{page:hire}}', 'btn btn-outline-primary px-4'),
                        )),
                    ), '', '6'),
                ), '4'),
            )),
        ), $searchable('rc-home-how', lang('how it works, apply, interview, shortlist, placement'))),

        $el('section', 'py-5 bg-body-tertiary', lang('Why Us'), array(
            $container(array(
                $section_header(lang('The way we work'), lang('Why people choose us'), lang('About Us'), '{{page:about}}'),
                $row($why_tiles, '4'),
            )),
        ), $searchable('rc-home-why', lang('free, specialist, checked candidates, reply, reliable'))),

        // What people say: sample words for the agency to replace, and
        // beside them its own Google reviews, which show once Google Reviews
        // is set up under Site Settings.
        $el('section', 'py-5', lang('Testimonials'), array(
            $container(array(
                $section_header(lang('Candidates and employers'), lang('What people say')),
                $row($testimonial_cols, '4'),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('Career Advice'), array(
            $container(array(
                $section_header(lang('Career Advice'), lang('Guides for your job search'), lang('All Guides'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),
    )),
);

// ── Sectors ─────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'sectors',
    'name'             => lang('sectors'),
    'folder'           => 'public',
    'title'            => lang('Sectors'),
    'meta_description' => lang('Technology, finance, engineering, healthcare, sales and logistics: the sectors we recruit for, each with its own consultant.'),
    'tree'             => $page(array(
        $page_header(lang('Sectors'), lang('Each sector has a consultant who has worked in it and knows its employers.')),
        $el('section', 'py-5', lang('Sectors'), array(
            $container(array(
                $row($sector_cols, '4'),
            )),
        ), $searchable('rc-sectors', lang('technology, finance, engineering, healthcare, sales, logistics, industries'))),
        $figures_band(),
        $ask_band(lang('Recruiting in another sector?'), lang('Write to us: our consultants recruit for many roles beyond these six sectors.')),
    )),
);

// One page per sector: what the agency knows of it, the roles it fills,
// its consultant, the jobs by city and the two ways in with the sector
// chosen. Its address is a translated slug, so a Turkish site reads
// Turkish addresses.
$sector_slugs = array(
    'technology'  => lang('technology-jobs'),
    'finance'     => lang('finance-and-accounting-jobs'),
    'engineering' => lang('engineering-and-manufacturing-jobs'),
    'healthcare'  => lang('healthcare-jobs'),
    'sales'       => lang('sales-and-marketing-jobs'),
    'logistics'   => lang('logistics-and-supply-chain-jobs'),
);
foreach ($sectors as $key => $s) {
    $consultant = array_search($consultant_of($key), $consultants, true);
    $public_pages[] = array(
        'key'              => 'sector_' . $key,
        'name'             => $sector_slugs[$key],
        'folder'           => 'public',
        'title'            => $s['title'],
        'meta_description' => $s['summary'] . ' ' . lang('Open jobs and recruitment for employers.'),
        'tree'             => $page(array(
            $page_header($s['title'], $s['summary']),
            $el('section', 'py-5', $s['title'], array(
                $container(array(
                    $row(array(
                        $col(array(
                            $icon_tile($s['icon'], 'mb-3'),
                            $para($s['text'], 'lead mb-4'),
                            $heading('h2', lang('Roles we fill'), 'prc-display h4 mb-3'),
                            $check_list($s['roles'], 'list-unstyled mb-4'),
                            $el('div', 'd-flex flex-wrap gap-2 mb-4', lang('Buttons'), array(
                                $link(lang('See the Jobs'), '{{page:jobs}}?' . $query(array('sector' => $s['title'])), 'btn btn-primary px-4'),
                                $link(lang('Hire Staff'), '{{page:hire}}?' . $query(array('sector' => $s['title'])), 'btn btn-outline-secondary px-4'),
                            )),
                            $filter_links(lang('Jobs in'), 'city', $city_titles),
                        ), '', '7'),
                        $col(array(
                            $para(lang('Your consultant'), 'prc-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                            $row(array($consultant_card($consultant, '', '')), '4'),
                        ), '', '5'),
                    ), '4', '', 'g-lg-5'),
                )),
            ), $searchable('rc-sector-' . $key)),
            $el('section', 'py-5 bg-body-tertiary', lang('How It Works'), array(
                $container(array(
                    $row(array(
                        $col(array(
                            $heading('h2', lang('Looking for a job?'), 'prc-display h4 mb-3'),
                            $steps_list($candidate_steps),
                        ), '', '6'),
                        $col(array(
                            $heading('h2', lang('Looking for staff?'), 'prc-display h4 mb-3'),
                            $steps_list($employer_steps),
                        ), '', '6'),
                    ), '4', '', 'g-lg-5'),
                )),
            )),
        )),
    );
}

// ── Cities ──────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'cities',
    'name'             => lang('cities'),
    'folder'           => 'public',
    'title'            => lang('Cities'),
    'meta_description' => lang('Our offices and the jobs open in each city, with remote and hybrid roles as well.'),
    'tree'             => $page(array(
        $page_header(lang('Cities'), lang('An office in each city, with consultants who know its employers.')),
        $el('section', 'py-5', lang('Cities'), array(
            $container(array(
                $row($city_cols, '4'),
            )),
        ), $searchable('rc-cities', lang('locations, offices, jobs near me, remote, hybrid'))),
        $ask_band(lang('Want to work somewhere else?'), lang('Send an open application: many of our jobs are remote or hybrid.')),
    )),
);

// ── Candidates ──────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'candidates',
    'name'             => lang('candidates'),
    'folder'           => 'public',
    'title'            => lang('For Candidates'),
    'meta_description' => lang('How we help you find a job for free: a consultant who knows your sector, interview preparation and support after you start.'),
    'tree'             => $page(array(
        $page_header(lang('For Candidates'), lang('Our service is free for you, from the first call to your first months in the new job.')),
        $el('section', 'py-5', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $steps_list($candidate_steps),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Find a Job'), '{{page:jobs}}', 'btn btn-primary px-4'),
                            $link(lang('Send an Open Application'), '{{page:apply}}', 'btn btn-outline-secondary px-4'),
                        )),
                    ), '', '6'),
                    $col(array($framed($photo('candidates', 1200, 900), lang('A candidate preparing for an interview with a consultant'))), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('rc-candidates', lang('apply, CV, resume, interview, job search, free'))),
        $el('section', 'py-5 bg-body-tertiary', lang('What You Can Count On'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Your CV'), 'prc-display h3 mb-3'),
                        $check_list(array(
                            lang('Read by a consultant, not only by a program'),
                            lang('Sent to an employer only with your agreement'),
                            lang('Kept private and deleted when you ask'),
                        )),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('Your application'), 'prc-display h3 mb-3'),
                        $check_list(array(
                            lang('An answer to every application, even when it is no'),
                            lang('Its state and our messages under My Account'),
                            lang('Feedback after every interview'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('rc-candidate-promises', lang('privacy, data, feedback, status, my account'))),
        $story_band('rc-career-advice', lang('Career Advice'), lang('Guides for every step of your search'),
            array(lang('How to write a CV, prepare for an interview, ask for the right salary and leave a job well: our consultants write down what they tell candidates every day.')),
            $photo('candidates-guides', 1200, 900), lang('A notebook and a laptop with a CV on the screen'), true,
            array($link(lang('Read the Guides'), '{{page:blog}}', 'btn btn-primary px-4')),
            lang('CV tips, interview tips, salary, cover letter'), 'py-5'),
        $el('section', 'py-5 bg-body-tertiary', lang('Testimonials'), array(
            $container(array(
                $section_header(lang('Candidates'), lang('People we placed')),
                $row($candidate_testimonial_cols, '4'),
            )),
        )),
    )),
);

// ── Employers ───────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'employers',
    'name'             => lang('employers'),
    'folder'           => 'public',
    'title'            => lang('For Employers'),
    'meta_description' => lang('Permanent recruitment, temporary staff, executive search and volume hiring, with a shortlist in five working days.'),
    'tree'             => $page(array(
        $page_header(lang('For Employers'), lang('Tell us who you need: a consultant who knows your sector sends you a shortlist of checked candidates.')),
        $el('section', 'py-5', lang('Our Services'), array(
            $container(array(
                $section_header(lang('What we offer'), lang('Four ways we help you hire')),
                $row($service_cols, '4'),
            )),
        ), $searchable('rc-employer-services', lang('permanent, temporary staff, executive search, headhunting, volume hiring, fees'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Step by step'), 'prc-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h2', lang('From your call to your new colleague'), 'prc-display h2 mb-4'),
                        $steps_list($employer_steps),
                        $link(lang('Hire Staff'), '{{page:hire}}', 'btn btn-primary px-4'),
                    ), '', '6'),
                    $col(array($framed($photo('employers', 1200, 900), lang('A consultant presenting a shortlist to a hiring manager'))), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('rc-employer-how', lang('shortlist, interview, placement, replacement, guarantee'))),
        $figures_band(),
        $el('section', 'py-5', lang('Testimonials'), array(
            $container(array(
                $section_header(lang('Employers'), lang('Companies that hire with us')),
                $row($employer_testimonial_cols, '4'),
            )),
        )),
        $ask_band(lang('A question about fees or terms?'), lang('Write to us and a consultant answers within a working day.')),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('A recruitment agency started by three recruiters: specialist consultants, an answer to every applicant and a service free for candidates.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), $agency['tagline'] . '.'),
        $story_band('rc-our-story', lang('Our story'), lang('One office and one phone line'),
            array($agency['story'], $agency['licence'] . '.'),
            $photo('about-office', 1200, 900), lang('The agency\'s consultants in their office'), false, array(),
            lang('history, founders, company, who we are, licence')),
        $figures_band(),
        $el('section', 'py-5', lang('Why Us'), array(
            $container(array(
                $section_header(lang('The way we work'), lang('Why people choose us')),
                $row($why_tiles, '4'),
            )),
        ), $searchable('rc-about-why', lang('free, specialist, checked candidates, reply, reliable'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Our Consultants'), array(
            $container(array(
                $section_header(lang('Our people'), lang('The consultants you will talk to'), lang('Our Consultants'), '{{page:team}}'),
                $row(array_slice($consultant_cols, 0, 3), '4'),
            )),
        )),
        $ask_band(lang('Want to talk to us?'), lang('Write to us, or call the office nearest to you.')),
    )),
);

// ── Team ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'team',
    'name'             => lang('consultants'),
    'folder'           => 'public',
    'title'            => lang('Our Consultants'),
    'meta_description' => lang('The recruitment consultants of each sector: who they are, what they did before and the jobs they recruit for.'),
    'tree'             => $page(array(
        $page_header(lang('Our Consultants'), lang('One consultant for each sector, each of whom has worked in it.')),
        $el('section', 'py-5', lang('Our Consultants'), array(
            $container(array(
                $row($consultant_cols, '4'),
            )),
        ), $searchable('rc-team', lang('people, recruiters, consultants, staff, contact person'))),
        $story_band('rc-join-us', lang('Work with us'), lang('Become a recruitment consultant'),
            array(lang('Have you worked in one of our sectors and like helping people find their place? We train people from industry to become consultants.')),
            $photo('team-join', 1200, 900), lang('Two consultants planning their week'), true,
            array($link(lang('Contact Us'), '{{page:contact}}', 'btn btn-primary px-4')),
            lang('work for us, careers at the agency, recruiter jobs'), 'py-5 bg-body-tertiary'),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Two accordions: for candidates and for employers. Each question is a
// search section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'prc-display h4 mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers for candidates and employers: fees, CVs, applications, interviews, shortlists and replacements.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('For candidates and for employers. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('rc-faq-candidates', lang('For candidates'), array(
                            array(lang('Do I pay anything?'), lang('No. Our service is always free for candidates; the employer pays us when someone starts.'), lang('fee, cost, free, charge')),
                            array(lang('Can I apply without a job in mind?'), lang('Yes. Send an open application with your CV and the sector and city you want, and a consultant calls you when a job fits.'), lang('open application, register CV, speculative')),
                            array(lang('Will my CV be sent to employers without asking me?'), lang('Never. We tell you about each job and send your CV only to the employers you agree to.'), lang('privacy, consent, CV, data')),
                            array(lang('How do I follow my application?'), lang('Sending an application opens an account for you. Under My Account you see the state of each application and can write to your consultant there.'), lang('my account, status, follow, message')),
                        )),
                        $faq_group('rc-faq-employers', lang('For employers'), array(
                            array(lang('How quickly will I see candidates?'), lang('For most roles we send a shortlist of three to five checked candidates within five working days.'), lang('shortlist, how long, time to hire')),
                            array(lang('When do we pay?'), lang('For a permanent hire, only when the candidate starts. Temporary staff are invoiced for the hours they work.'), lang('fee, invoice, payment, terms')),
                            array(lang('What if the new hire leaves?'), lang('If a permanent hire leaves within three months, we find a replacement free of charge.'), lang('guarantee, replacement, rebate')),
                            array(lang('How do I follow my staff request?'), lang('Sending a staff request opens an account for you. Under My Account you see its state and our messages, and can answer there.'), lang('my account, status, follow, message')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// The home page's lists: its featured jobs (home_jobs) come with the jobs,
// its guides (home_posts) and the Google reviews (google_reviews) with the
// standard pages; this part places no widget of its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
