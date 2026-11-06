<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Charity", its public pages: home, about and mission, our
 * work by cause, impact, transparency and the questions page. Included
 * after parts.php in the same scope (its builders and the charity's data);
 * returns array('pages' => ..., 'widgets' => ...).
 *
 * Every page here stands without the shop: the campaign cards are samples
 * that open the campaigns of their cause, the gift cards open the donate
 * page, and every figure (causes, campaigns, gift amounts, impact,
 * spending, promises) comes from parts.php, so a change made there reaches
 * every page here. The pages place list widgets of other parts:
 * home_campaigns (the campaigns), home_events (the events), home_posts
 * (the News) and reports (the annual reports, on the transparency page).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$public_pages = array();

// A picture that loads when it comes near the screen.
$lazy = array('_attrs' => array($attr('loading', 'lazy')));

// A band of text beside a picture; $flip puts the picture first from lg up.
// The band is a search section under its anchor $id, with the topics a
// visitor may search for that it does not say itself.
$charity_band = function ($id, $eyebrow, $title, $text, $points, $picture, $alt, $flip = false, $topics = '', $class = 'py-5')
    use ($el, $heading, $para, $image, $container, $row, $col, $check_list, $searchable, $lazy) {
    $text_col = $col(array(
        $para($eyebrow, 'pch-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'h2 fw-semibold mb-3'),
        $para($text, 'lead text-body-secondary mb-4'),
        $check_list($points),
    ), '', '6', '', '12', $flip ? 'order-lg-last' : '');
    $picture_col = $col(array(
        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden bg-body-tertiary', lang('Picture Frame'), array(
            $image($picture, $alt, 'object-fit-cover', $lazy),
        )),
    ), '', '6');
    return $el('section', $class, $title, array(
        $container(array(
            $row(array($text_col, $picture_col), '4', '', 'g-lg-5 align-items-center'),
        )),
    ), $searchable($id, $topics));
};

// Three tiles of an icon, a title and a line, in a row.
$charity_tiles = function ($items) use ($el, $heading, $para, $col, $row, $icon_tile) {
    $cols = array();
    foreach ($items as $v) {
        $cols[] = $col(array(
            $el('div', 'h-100 p-4 rounded-4 bg-body-tertiary', $v['title'], array(
                $icon_tile($v['icon'], 'mb-3'),
                $heading('h3', $v['title'], 'h5 fw-semibold mb-2'),
                $para($v['text'], 'small text-body-secondary mb-0'),
            )),
        ), '', '4');
    }
    return $row($cols, '4');
};

// Numbered steps, as an ordered list.
$charity_step_list = function ($steps) use ($el, $step) {
    $items = array();
    foreach (array_values($steps) as $n => $s) $items[] = $step($n + 1, $s[0], $s[1]);
    return $el('ol', 'list-unstyled mb-0', lang('Steps'), $items);
};

// A group of questions under its title; each question is a search section
// of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'h4 fw-semibold mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
    ));
};

// A strip that sends the visitor on: a title, a line and one button.
$charity_strip = function ($title, $text, $button, $href, $class = 'py-5') use ($el, $heading, $para, $link, $container) {
    return $el('section', $class, $title, array(
        $container(array(
            $el('div', 'p-4 p-lg-5 rounded-4 bg-body-tertiary d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Text'), array(
                $el('div', '', lang('Text'), array(
                    $heading('h2', $title, 'h4 fw-semibold mb-1'),
                    $para($text, 'text-body-secondary mb-0'),
                )),
                $link($button, $href, 'btn btn-primary rounded-pill px-4 text-nowrap'),
            )),
        )),
    ));
};

// The cause tiles, in the order of the menu, and the gift cards.
$cause_tiles = array();
foreach (array_keys($causes) as $key) $cause_tiles[] = $cause_tile($key);
$gift_cards = array();
foreach (array_keys($gift_amounts) as $n) $gift_cards[] = $gift_card($n);

// How a gift becomes a well or a classroom, in the order the home page and
// the impact page tell it.
$gift_steps = array(
    array(lang('You give'), lang('Once, to a campaign or where it is needed most, through the site\'s secure checkout.')),
    array(lang('We plan with the village'), lang('Every project is chosen and run with the people who will use it.')),
    array(lang('Local teams build it'), lang('Builders, teachers and nurses from the area do the work, and keep it going.')),
    array(lang('You hear what it built'), lang('Photos and figures from the field in our News, and every account in our reports.')),
);

// ── Home ────────────────────────────────────────────────────────────────
$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('A charity for clean water, schooling, food, health and emergency relief: our campaigns, how to give, volunteer and see where every gift goes.'),
    'tree'             => $page(array(
        // The mission in one line and the two ways to help.
        $el('section', 'py-5 bg-body-tertiary overflow-hidden pch-hero', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Our mission'), 'pch-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h1', lang('Water, school and a fair start for every family'), 'display-4 fw-semibold mb-3'),
                        $para(lang('We work with villages and towns on what they need most, and we show you what every gift built.'), 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Donate'), '{{page:donate}}', 'btn btn-primary btn-lg rounded-pill px-4'),
                            $link(lang('See our campaigns'), '{{page:campaigns}}', 'btn btn-outline-primary btn-lg rounded-pill px-4'),
                        )),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden bg-body-tertiary', lang('Picture Frame'), array(
                            $image($photo('hero', 1200, 900), lang('Children filling cans with water at a new village well'), 'object-fit-cover'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        // What the charity has done, in four figures.
        $el('section', 'py-5 border-bottom', lang('Impact'), array(
            $container(array($impact_row())),
        )),

        // The campaigns the home page puts first: the Campaigns list.
        $el('section', 'py-5', lang('Campaigns'), array(
            $container(array(
                $section_header(lang('Give where it counts'), lang('Our campaigns'), lang('See our campaigns'), '{{page:campaigns}}'),
                $widget('home_campaigns'),
            )),
        )),

        // The causes, each opening its part of the our-work page.
        $el('section', 'py-5 bg-body-tertiary', lang('Our Work'), array(
            $container(array(
                $section_header(lang('What we do'), lang('Five ways we help'), lang('Our Work'), '{{page:our_work}}'),
                $row($cause_tiles, '3'),
            )),
        )),

        // What a gift pays for.
        $el('section', 'py-5', lang('Gift Amounts'), array(
            $container(array(
                $section_header(lang('Your gift'), lang('What your gift can do'), lang('Donate'), '{{page:donate}}'),
                $row($gift_cards, '4'),
            )),
        )),

        // How a gift becomes something built, beside a picture.
        $el('section', 'py-5 bg-body-tertiary', lang('How it works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden bg-body-tertiary', lang('Picture Frame'), array(
                            $image($photo('build', 1200, 900), lang('Villagers and a builder lining a well with stone'), 'object-fit-cover', $lazy),
                        )),
                    ), '', '6'),
                    $col(array(
                        $para(lang('How it works'), 'pch-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h2', lang('From your gift to the field'), 'h2 fw-semibold mb-4'),
                        $charity_step_list($gift_steps),
                        $link(lang('Where the money goes'), '{{page:transparency}}', 'btn btn-outline-primary rounded-pill px-4 mt-4'),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        // What every donor can count on.
        $el('section', 'py-5 border-bottom', lang('Promises'), array(
            $container(array($promise_row())),
        )),

        // The next events: the Events list.
        $el('section', 'py-5', lang('Events'), array(
            $container(array(
                $section_header(lang('Join us'), lang('Coming up'), lang('All events'), '{{page:events}}'),
                $widget('home_events'),
            )),
        )),

        // The newest from the field.
        $el('section', 'py-5 bg-body-tertiary', lang('News'), array(
            $container(array(
                $section_header(lang('News'), lang('News from the field'), lang('All news'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),

        $shared('donate_band'),
    )),
);

// ── About ───────────────────────────────────────────────────────────────
$about_values = array(
    array('icon' => 'bi-people', 'title' => lang('With, not for'), 'text' => lang('Every project is chosen, built and kept by the people who use it.')),
    array('icon' => 'bi-tools', 'title' => lang('Built to last'), 'text' => lang('A well that works in ten years matters more than one opened this week.')),
    array('icon' => 'bi-eye', 'title' => lang('Open books'), 'text' => lang('We publish where every gift goes, and our audited accounts every year.')),
);

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('Who we are: our mission, how we work with villages and towns, our values and the people behind the charity.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), lang('A charity that works with villages and towns on water, school, food, health and help in a disaster.')),
        $el('section', 'py-5', lang('Our Mission'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Our mission'), 'h3 fw-semibold mb-3'),
                        $para(lang('We believe that every family should have safe water, every child a school and every mother a clinic within reach. We work towards it one village at a time.'), 'text-body-secondary'),
                        $para(lang('We do not build and leave: local teams run every project, and the village keeps what is built. That is why our wells still pump and our classrooms are still full.'), 'text-body-secondary mb-0'),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden bg-body-tertiary', lang('Picture Frame'), array(
                            $image($photo('about', 1200, 900), lang('Our field team meeting with village elders under a tree'), 'object-fit-cover', $lazy),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('mission', lang('mission, vision, who we are, charity, nonprofit'))),
        $el('section', 'py-5 border-top', lang('Impact'), array(
            $container(array($impact_row())),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('Our Values'), array(
            $container(array(
                $section_header(lang('What we stand for'), lang('How we work'), lang('Transparency'), '{{page:transparency}}'),
                $charity_tiles($about_values),
            )),
        ), $searchable('values', lang('values, principles, local, sustainable'))),
        $charity_band('team', lang('Our people'), lang('A small office, a large team in the field'),
            lang('Most of our people live where they work: engineers, teachers, nurses and organisers from the regions we serve, with a small office that keeps the books.'),
            array(lang('Field teams in every region we work in'), lang('A board of trustees who give their time unpaid'), lang('Hundreds of volunteers at home and abroad')),
            $photo('team', 1200, 900), lang('The field team in front of a finished classroom'), false, lang('team, staff, trustees, board, people')),
        $charity_strip(lang('Partners and press'), lang('Companies, foundations and journalists: write to us and the right person answers.'), lang('Contact Us'), '{{page:contact}}'),
        $shared('donate_band'),
    )),
);

// ── Our work ────────────────────────────────────────────────────────────
// A section for each cause (#cause-<key>, the anchor the footer and the
// tiles open): its picture and line, its campaigns and the link to the
// campaign list filtered to it.
$cause_links = array();
$cause_sections = array();
$band_n = 0;
foreach ($causes as $key => $c) {
    $cause_links[] = $el('li', 'nav-item', lang('Nav Item'), array($link($c['title'], '#cause-' . $key, 'nav-link px-0')));
    $cause_cards = array();
    foreach ($campaigns as $campaign_key => $campaign) {
        if ($campaign['cause'] === $key) $cause_cards[] = $campaign_card($campaign_key);
    }
    $cause_sections[] = $el('section', 'py-5' . ($band_n % 2 === 1 ? ' bg-body-tertiary' : ''), $c['title'], array(
        $container(array(
            $row(array(
                $col(array(
                    $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden bg-body-tertiary', lang('Picture Frame'), array(
                        $image($photo('cause-' . $key, 800, 600), $c['title'], 'object-fit-cover', $lazy),
                    )),
                ), '', '4'),
                $col(array(
                    $el('h2', 'h3 fw-semibold d-flex align-items-center gap-2 mb-2', lang('Title'), array(
                        $icon($c['icon'], 'text-primary'),
                        $span($c['title'], '', lang('Text')),
                    )),
                    $para($c['text'], 'lead text-body-secondary mb-3'),
                    $link(lang('See the campaigns'), $campaigns_in($key), 'btn btn-outline-primary rounded-pill px-4'),
                ), '', '8'),
            ), '4', '', 'g-lg-5 align-items-center mb-4'),
            $row($cause_cards, '4'),
        )),
    ), $searchable('cause-' . $key, $c['topics']));
    $band_n++;
}

$public_pages[] = array(
    'key'              => 'our_work',
    'name'             => lang('our-work'),
    'folder'           => 'public',
    'title'            => lang('Our Work'),
    'meta_description' => lang('Clean water, education, food and farming, health and emergency relief: what we do for each and the campaigns open now.'),
    'tree'             => $page(array_merge(
        array($page_header(lang('Our Work'), lang('Five causes, one way of working: with the people who will use what is built.'), array(
            $el('nav', 'mt-3', lang('Jump to'), array(
                $el('ul', 'nav column-gap-4 small', lang('Jump Links'), $cause_links),
            ), array('_attrs' => array($attr('aria-label', lang('Our Work'))))),
        ))),
        $cause_sections,
        array($shared('donate_band'))
    )),
);

// ── Impact ──────────────────────────────────────────────────────────────
$public_pages[] = array(
    'key'              => 'impact',
    'name'             => lang('impact'),
    'folder'           => 'public',
    'title'            => lang('Our Impact'),
    'meta_description' => lang('What our donors and volunteers have built: safe water, children in school, villages we work with, and how a gift reaches the field.'),
    'tree'             => $page(array(
        $page_header(lang('Our Impact'), lang('What our donors and volunteers have built, in figures and in the words of the people who use it.')),
        $el('section', 'py-5', lang('Impact'), array(
            $container(array($impact_row())),
        ), $searchable('figures', lang('results, figures, numbers, achievements'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How it works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('From your gift to the field'), 'h3 fw-semibold mb-4'),
                        $charity_step_list($gift_steps),
                    ), '', '7'),
                    $col(array(
                        $el('blockquote', 'p-4 rounded-4 bg-body mb-0', lang('Quote'), array(
                            $para(lang('Before the well, my daughters walked to the river before school. Now they are in class on time, and so am I: I teach there.'), 'lead mb-3'),
                            $para(lang('A teacher in the Dry Valley'), 'small text-body-secondary mb-0'),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('how-it-works', lang('how donations work, where gifts go, process'))),
        $charity_band('water-story', lang('Clean Water'), lang('Water within a short walk'),
            lang('A well with a pump the village keeps itself gives a family back the hours it spent carrying water, and keeps the children well enough to learn.'),
            array(lang('Each well serves about four hundred people'), lang('A village committee looks after every pump'), lang('We check every well we built, every year')),
            $photo('water', 1200, 900), lang('A woman pumping water at a well'), false, lang('water, wells, results')),
        $charity_band('school-story', lang('Education'), lang('A classroom for every grade'),
            lang('New classrooms, books and trained teachers keep children in school longer, and a hot lunch keeps them there all day.'),
            array(lang('Classrooms built with the parents'), lang('Teachers trained and paid by the district'), lang('School meals cooked from the parents\' gardens')),
            $photo('school', 1200, 900), lang('Children reading at their desks in a new classroom'), true, lang('schools, education, results'), 'py-5 bg-body-tertiary'),
        $charity_strip(lang('See where the money goes'), lang('What we spend on programmes, raising funds and running the charity, and our annual reports.'), lang('Transparency'), '{{page:transparency}}'),
        $shared('donate_band'),
    )),
);

// ── Transparency ────────────────────────────────────────────────────────
$transparency_points = array(
    lang('Our accounts are audited by an independent firm every year'),
    lang('We publish the annual report and the accounts on this page'),
    lang('A gift to a campaign goes to that campaign; what is left when it is done goes where it is needed most'),
    lang('We never sell or share the details of our donors'),
);

$public_pages[] = array(
    'key'              => 'transparency',
    'name'             => lang('transparency'),
    'folder'           => 'public',
    'title'            => lang('Transparency'),
    'meta_description' => lang('Where every gift goes: what we spend on programmes, raising funds and running the charity, our audited accounts and annual reports.'),
    'tree'             => $page(array(
        $page_header(lang('Transparency'), lang('Where every gift goes, and the reports and accounts that show it.')),
        $el('section', 'py-5', lang('Spending'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Where the money goes'), 'h3 fw-semibold mb-4'),
                        $spending_bars(),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('Our Promise'), array(
                            $heading('h2', lang('Our promise to donors'), 'h5 fw-semibold mb-3'),
                            $check_list($transparency_points),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('spending', lang('spending, costs, overheads, administration, percentage'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Reports'), array(
            $container(array(
                $section_header(lang('Reports'), lang('Annual reports and accounts')),
                $widget('reports'),
            )),
        ), $searchable('reports', lang('annual report, accounts, audit, financial statements'))),
        $charity_strip(lang('A question about our accounts?'), lang('Write to us: our finance team answers every question.'), lang('Contact Us'), '{{page:contact}}'),
        $shared('donate_band'),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about giving, receipts, where gifts go, volunteering and events.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Giving, receipts, volunteering and events. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('ch-faq-giving', lang('Giving'), array(
                            array(lang('How do I give?'), lang('From the donate page: choose a campaign or where it is needed most, type the amount in the cart and pay at the checkout. You can also give by bank transfer.'), lang('donate, give, pay, card')),
                            array(lang('Will I get a receipt?'), lang('Yes: the receipt for your gift is sent by e-mail as soon as it arrives, and stays in your account.'), lang('receipt, tax, invoice')),
                            array(lang('Can I give to one campaign?'), lang('Yes. A gift to a campaign goes to that campaign; what is left when it is done goes where it is needed most.'), lang('campaign, restricted, earmark')),
                            array(lang('Can I give without an account?'), lang('Yes. An account only keeps your receipts and your volunteering in one place.'), lang('account, guest, sign in')),
                        )),
                        $faq_group('ch-faq-money', lang('Where the money goes'), array(
                            array(lang('How much of my gift reaches the field?'), lang('84% of what we spend goes to the programmes in the field; the transparency page shows the rest.'), lang('overheads, costs, percentage')),
                            array(lang('Are your accounts audited?'), lang('Yes, by an independent firm every year. The reports are on the transparency page.'), lang('audit, accounts, annual report')),
                        )),
                        $faq_group('ch-faq-help', lang('Volunteering and events'), array(
                            array(lang('How can I volunteer?'), lang('Tell us how you would like to help from the volunteer page; we answer everyone and you can follow it from your account.'), lang('volunteer, help, time')),
                            array(lang('Do I need experience?'), lang('No. We need drivers, cooks, translators and people to talk to donors as much as engineers and nurses.'), lang('skills, experience, requirements')),
                            array(lang('How do I join an event?'), lang('Every event has a Sign up button that opens a short form: no ticket and no seat to choose.'), lang('events, sign up, register')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
        $charity_strip(lang('Still have a question?'), lang('Write to us: we answer every message.'), lang('Contact Us'), '{{page:contact}}', 'pb-5'),
    )),
);

// The pages' lists (home_campaigns, home_events, home_posts, reports)
// belong to the parts that build those lists; this part places no widget
// of its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
