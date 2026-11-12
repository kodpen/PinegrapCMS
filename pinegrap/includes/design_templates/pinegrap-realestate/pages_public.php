<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Real Estate Agency", its public pages: home, the
 * neighbourhoods and one page for each, the agents, the services, about,
 * the questions clients ask and the buyer's guide. Included after
 * parts.php in the same scope (its builders and the agency's data);
 * returns the list of pages. The listing lists these pages place
 * (home_listings, area_listings_<key>) are the widgets of listings.php.
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

// ── Pieces the pages share ──────────────────────────────────────────────

// A neighbourhood card, linked to its own page: a photo, the name, a line
// and the typical price.
$area_card = function ($key) use ($el, $heading, $para, $span, $icon, $image, $col, $attr, $areas, $photo) {
    $a = $areas[$key];
    return $col(array(
        $el('a', 'pre-lift card h-100 border-0 shadow-sm overflow-hidden text-decoration-none link-body-emphasis', $a['title'], array(
            $el('div', 'ratio ratio-16x9 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('area-' . $key, 800, 450), $a['title'], 'card-img-top object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $el('h3', 'h5 fw-semibold d-flex align-items-center gap-2 mb-2', lang('Title'), array(
                    $icon($a['icon'], 'text-primary'),
                    $span($a['title'], '', lang('Text')),
                )),
                $para($a['text'], 'small text-body-secondary mb-3'),
                $para($a['price'], 'small fw-semibold mb-0'),
            )),
        ), array('href' => '{{page:area_' . $key . '}}')),
    ), '6', '4');
};

// The agency's figures in a row.
$figure_row = function ($class = '') use ($el, $para, $row, $col, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', $f['label'], array(
                $para($f['value'], 'display-6 fw-semibold text-primary mb-1'),
                $para($f['label'], 'small text-body-secondary mb-0'),
            )),
        ), '3', '', '', '6');
    }
    return $el('div', $class, lang('Figures'), array($row($cols, '4')));
};

// A service card: what the agency does, its points and the way to start.
$service_card = function ($key, $lg = '6') use ($el, $heading, $para, $link, $icon_tile, $check_list, $col, $searchable, $services) {
    $s = $services[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-3', $s['title'], array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($s['icon'], 'mb-3 align-self-start'),
                $heading('h3', $s['title'], 'h5 fw-semibold mb-2'),
                $para($s['text'], 'text-body-secondary mb-3'),
                $check_list($s['points'], 'list-unstyled small mb-4'),
                $link($s['page'] === 'listings' ? lang('See the listings') : ($s['page'] === 'contact' ? lang('Write to us') : lang('Ask for a valuation')),
                    '{{page:' . $s['page'] . '}}', 'btn btn-outline-primary align-self-start mt-auto'),
            )),
        ), $searchable('service-' . $key)),
    ), '6', $lg);
};

// Quick ways into the lists: the deals and the types, each a link that
// opens the listings with the filter set (?deal=, ?type=).
$quick_search = function () use ($el, $link, $icon, $span, $query, $deals, $property_types) {
    $chips = array();
    foreach ($property_types as $t) {
        $chips[] = $el('a', 'btn btn-sm btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-1', $t['title'], array(
            $icon($t['icon'], 'text-primary'),
            $span($t['title'], '', lang('Link Text')),
        ), array('href' => '{{page:listings}}?' . $query(array('type' => $t['title']))));
    }
    return $el('div', 'card border-0 shadow p-3 p-lg-4', lang('Search Box'), array(
        $el('div', 'd-flex flex-wrap gap-2 mb-3', lang('Buttons'), array(
            $link($deals['sale'], '{{page:buy}}', 'btn btn-primary px-4'),
            $link($deals['rent'], '{{page:rent}}', 'btn btn-outline-primary px-4'),
            $link(lang('All Listings'), '{{page:listings}}', 'btn btn-link link-body-emphasis'),
        )),
        $el('div', 'd-flex flex-wrap gap-2', lang('Property Types'), $chips),
    ));
};

// What clients say.
$testimonial = function ($quote, $who, $what) use ($el, $para, $stars, $col) {
    return $col(array(
        $el('figure', 'h-100 p-4 rounded-3 bg-body mb-0', lang('Testimonial'), array(
            $stars(),
            $el('blockquote', 'mb-3', lang('Quote'), array($para($quote, 'mb-0'))),
            $el('figcaption', 'small', lang('Author'), array(
                $para($who, 'fw-semibold mb-0'),
                $para($what, 'text-body-secondary mb-0'),
            )),
        )),
    ), '', '4');
};

// How buying goes, from the first viewing to the keys.
$buying_steps = function () use ($el, $step) {
    return $el('ol', 'list-unstyled mb-0', lang('List'), array(
        $step(1, lang('Ask for a viewing'), lang('Choose a day and a time of day on the listing; an agent confirms within a working day.')),
        $step(2, lang('See it with an agent'), lang('We show you the home and the street, and answer what the photos cannot.')),
        $step(3, lang('Make an offer'), lang('We check the title deed and zoning, then take your offer to the owner.')),
        $step(4, lang('Collect the keys'), lang('Contract, title deed transfer and the keys, with your agent beside you.')),
    ));
};

$area_cards = array();
foreach (array_keys($areas) as $key) $area_cards[] = $area_card($key);
$agent_cards = array();
foreach (array_keys($agents) as $key) $agent_cards[] = $agent_card($key);

// ── Home ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('Homes to buy and to rent in the city\'s best neighbourhoods, local agents who know every street and a free valuation for owners.'),
    'tree'             => $page(array(
        // The way in: the words and the quick search over a photo of the bay.
        $el('section', 'pre-hero position-relative py-5 overflow-hidden', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Estate agents · Istanbul'), 'pre-eyebrow text-uppercase fw-semibold text-primary mb-3'),
                        $heading('h1', lang('Find the home that fits your life'), 'display-4 fw-semibold mb-3'),
                        $para(lang('Flats by the sea, stone houses in the old town and family homes by the park, shown by agents who live in the neighbourhood.'), 'lead text-body-secondary mb-4'),
                        $quick_search(),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden shadow-sm', lang('Picture Frame'), array(
                            $image($photo('hero', 1200, 900), lang('A bright living room with a view over the bay'), 'object-fit-cover'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        $el('section', 'py-4 border-top border-bottom', lang('Figures'), array(
            $container(array($figure_row())),
        )),

        $el('section', 'py-5', lang('Featured Listings'), array(
            $container(array(
                $section_header(lang('Just listed'), lang('Featured homes'), lang('All listings'), '{{page:listings}}'),
                $widget('home_listings'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('Neighbourhoods'), array(
            $container(array(
                $section_header(lang('Where to live'), lang('Our neighbourhoods'), lang('All neighbourhoods'), '{{page:neighbourhoods}}'),
                $row($area_cards, '4'),
            )),
        )),

        $el('section', 'py-5', lang('Services'), array(
            $container(array(
                $section_header(lang('What we do'), lang('Buy, sell, let or manage'), lang('All services'), '{{page:services}}'),
                $row(array($service_card('sell', '3'), $service_card('let', '3'), $service_card('manage', '3'), $service_card('buy', '3')), '4'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Buying with us'), 'small text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('From the first viewing to the keys'), 'h3 fw-semibold mb-3'),
                        $para(lang('No account needed to begin: the viewing form opens one for you, so you can follow your requests and write to your agent afterwards.'), 'text-body-secondary mb-4'),
                        $link(lang('Read the buyer\'s guide'), '{{page:guide}}', 'btn btn-outline-primary px-4'),
                    ), '', '5'),
                    $col(array($buying_steps()), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        $el('section', 'py-5', lang('Our Agents'), array(
            $container(array(
                $section_header(lang('The team'), lang('Your local agents'), lang('All agents'), '{{page:agents}}'),
                $row($agent_cards, '4'),
            )),
        )),

        // What clients say: sample words for the agency to replace, and
        // beside them its own Google reviews, which show once Google Reviews
        // is set up under Site Settings.
        $el('section', 'py-5 bg-body-tertiary', lang('Testimonials'), array(
            $container(array(
                $section_header(lang('Our clients'), lang('What clients say')),
                $row(array(
                    $testimonial(lang('“Deniz priced our flat right the first time; it sold in three weeks to a buyer who had already seen the paperwork.”'), lang('Murat and Elif T.'), lang('Sold in Seafront')),
                    $testimonial(lang('“We saw six houses in one morning and Leyla had checked every title deed before we asked.”'), lang('Anna P.'), lang('Bought in the Old Town')),
                    $testimonial(lang('“We live abroad; Emre finds the tenants, arranges the repairs and sends a statement every month.”'), lang('The Yıldız family'), lang('Managed in Parkside')),
                ), '4'),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5 border-top', lang('Market News'), array(
            $container(array(
                $section_header(lang('From the market'), lang('Market News'), lang('All articles'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),

        $shared('valuation_band'),
    )),
);

// ── Neighbourhoods ──────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'neighbourhoods',
    'name'             => lang('neighbourhoods'),
    'folder'           => 'public',
    'title'            => lang('Neighbourhoods'),
    'meta_description' => lang('Seafront, Old Town, Parkside, Hillside and the Business District: what living in each is like and what homes cost there.'),
    'tree'             => $page(array(
        $page_header(lang('Neighbourhoods'), lang('Five parts of the city we know street by street: what living there is like, and what a home costs.')),
        $el('section', 'py-5', lang('Neighbourhoods'), array(
            $container(array($row($area_cards, '4'))),
        )),
        $shared('valuation_band'),
    )),
);

// ── One page for each neighbourhood ─────────────────────────────────────
// What living there is like, its typical prices, the agent who knows it,
// its listings and the way to see all of them. The page names (addresses)
// follow the site's language.
$area_slugs = array(
    'seafront' => lang('seafront'),
    'oldtown'  => lang('old-town'),
    'parkside' => lang('parkside'),
    'hillside' => lang('hillside'),
    'business' => lang('business-district'),
);
foreach ($areas as $key => $a) {
    $area_agents = array();
    foreach ($agents as $akey => $ag) {
        if (in_array($key, $ag['areas'], true)) $area_agents[] = $agent_card($akey);
    }
    $agent = $agents[$a['agent']];
    $all = '{{page:listings}}?' . $query(array('area' => $a['title']));

    $public_pages[] = array(
        'key'              => 'area_' . $key,
        'name'             => $area_slugs[$key],
        'folder'           => 'public',
        'title'            => $a['title'],
        'meta_description' => $a['text'],
        'tree'             => $page(array(
            $page_header($a['title'], $a['text'], array(
                $el('div', 'd-flex flex-wrap gap-2 mt-4', lang('Buttons'), array(
                    $link(lang('Listings here'), $all, 'btn btn-primary px-4'),
                    $link(lang('Free Valuation'), '{{page:valuation}}?' . $query(array('area' => $a['title'])), 'btn btn-outline-secondary px-4'),
                )),
            )),
            $el('section', 'py-5', lang('Living Here'), array(
                $container(array(
                    $row(array(
                        $col(array(
                            $el('div', 'ratio ratio-16x9 rounded-3 overflow-hidden mb-4', lang('Picture Frame'), array(
                                $image($photo('area-' . $key, 1200, 675), $a['title'], 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                            )),
                            $para(lang('Living here'), 'pre-eyebrow text-uppercase fw-semibold text-primary mb-1'),
                            $heading('h2', lang('What the neighbourhood offers'), 'h3 fw-semibold mb-3'),
                            $check_list($a['highlights']),
                        ), '', '7'),
                        $col(array(
                            $el('aside', 'card border-0 bg-body-tertiary rounded-3', lang('Local Agent'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $para(lang('Typical prices'), 'small text-uppercase fw-semibold text-body-secondary mb-2'),
                                    $para($a['price'], 'fw-semibold mb-4'),
                                    $para(lang('Your agent here'), 'small text-uppercase fw-semibold text-body-secondary mb-2'),
                                    $heading('h3', $agent['name'], 'h5 fw-semibold mb-1'),
                                    $para($agent['role'], 'small text-primary mb-3'),
                                    $link($agent['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $agent['phone']), 'd-block small link-body-emphasis text-decoration-none mb-3'),
                                    $link(lang('Ask for a valuation'), '{{page:valuation}}?' . $query(array('area' => $a['title'], 'agent' => $agent['name'])), 'btn btn-sm btn-outline-primary'),
                                )),
                            )),
                        ), '', '5'),
                    ), '4', '', 'g-lg-5'),
                )),
            ), $searchable('living-here', $a['topics'])),
            $el('section', 'py-5 bg-body-tertiary', lang('Listings'), array(
                $container(array(
                    $section_header(lang('On the market'), lang('Homes in this neighbourhood'), lang('All listings here'), $all),
                    $widget('area_listings_' . $key),
                )),
            )),
            $el('section', 'py-5', lang('Our Agents'), array(
                $container(array(
                    $section_header(lang('The team'), lang('Agents who know it')),
                    $row($area_agents, '4'),
                )),
            )),
            $shared('valuation_band'),
        )),
    );
}

// ── Agents ──────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'agents',
    'name'             => lang('agents'),
    'folder'           => 'public',
    'title'            => lang('Our Agents'),
    'meta_description' => lang('The agency\'s agents: the neighbourhoods they know, the languages they speak and how to reach them.'),
    'tree'             => $page(array(
        $page_header(lang('Our Agents'), lang('Every client has one agent who answers the phone, and every agent knows their streets.')),
        $el('section', 'py-5', lang('Agents'), array(
            $container(array(
                $row($agent_cards, '4'),
                $el('div', 'alert alert-light border d-flex gap-2 mt-4 mb-0', lang('Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('No preference? Choose "No preference" on the form and the agent of the neighbourhood takes your request.'), '', lang('Text')),
                )),
            )),
        ), $searchable('agents', lang('agent, realtor, team, languages'))),
        $shared('valuation_band'),
    )),
);

// ── Services ────────────────────────────────────────────────────────────

$service_cards = array();
foreach (array_keys($services) as $key) $service_cards[] = $service_card($key);

$public_pages[] = array(
    'key'              => 'services',
    'name'             => lang('services'),
    'folder'           => 'public',
    'title'            => lang('Services'),
    'meta_description' => lang('Selling, letting, property management and finding a home: what the agency does for owners, buyers and tenants.'),
    'tree'             => $page(array(
        $page_header(lang('Services'), lang('For owners who sell or let, for buyers and tenants, and for those who want someone to look after their home.')),
        $el('section', 'py-5', lang('Services'), array(
            $container(array($row($service_cards, '4'))),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('Our Fees'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Good to know'), 'small text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('Our fees, in plain words'), 'h3 fw-semibold mb-3'),
                        $check_list(array(
                            lang('Selling: a share of the price, agreed in writing, paid only when the title deed is transferred'),
                            lang('Letting: one month\'s rent, paid when the contract is signed'),
                            lang('Management: a share of the monthly rent, with repairs passed on at cost'),
                            lang('Valuations and viewings are always free'),
                        )),
                    ), '', '7'),
                    $col(array($figure_row()), '', '5'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('fees', lang('fee, commission, cost, price'))),
        $shared('valuation_band'),
    )),
);

// ── About ───────────────────────────────────────────────────────────────
$value = function ($icon_name, $title, $text) use ($el, $heading, $para, $icon_tile, $col) {
    return $col(array(
        $el('div', 'h-100', $title, array(
            $icon_tile($icon_name, 'mb-3'),
            $heading('h3', $title, 'h5 fw-semibold mb-2'),
            $para($text, 'text-body-secondary mb-0'),
        )),
    ), '6', '3');
};

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About'),
    'meta_description' => lang('How the agency began, how we work and what owners, buyers and tenants can expect from us.'),
    'tree'             => $page(array(
        $page_header(lang('About'), lang('A local agency founded on a simple idea: an honest price and an agent who knows the street.')),
        $el('section', 'py-5', lang('Our Story'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'ratio ratio-4x3 rounded-3 overflow-hidden', lang('Picture Frame'), array(
                            $image($photo('about', 1200, 900), lang('The agency\'s shop front on a sunny street'), 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                        )),
                    ), '', '6'),
                    $col(array(
                        $para(lang('Our story'), 'small text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('Eighteen years on the same street'), 'h3 fw-semibold mb-3'),
                        $para(lang('Deniz Aydın opened the office in 2008 with one desk and a list of flats by the sea. Today four agents cover five neighbourhoods, and many of our sellers first came to us as buyers.'), 'text-body-secondary'),
                        $para(lang('We list fewer homes than the big portals, and we know each one: we have been inside, measured the rooms and read the title deed.'), 'text-body-secondary mb-0'),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('our-story', lang('story, founder, history'))),
        $el('section', 'py-4 border-top border-bottom', lang('Figures'), array(
            $container(array($figure_row())),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('Our Values'), array(
            $container(array(
                $section_header(lang('How we work'), lang('Our principles')),
                $row(array(
                    $value('bi-tag', lang('Honest prices'), lang('A price we can explain with recent sales, never one set to win the listing.')),
                    $value('bi-file-earmark-check', lang('Checked papers'), lang('Title deed and zoning read before a home goes on the market.')),
                    $value('bi-telephone', lang('One agent'), lang('The same person from the first call to the keys, and a phone that is answered.')),
                    $value('bi-camera', lang('True photos'), lang('Photos and plans that show the home as it is, rooms measured on site.')),
                ), '4'),
            )),
        ), $searchable('values', lang('honesty, trust, title deed, photos'))),
        $shared('valuation_band'),
    )),
);

// ── FAQ ─────────────────────────────────────────────────────────────────
$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Questions and Answers'),
    'meta_description' => lang('Answers to what buyers, tenants and owners ask: viewings, offers, deposits, fees and valuations.'),
    'tree'             => $page(array(
        $page_header(lang('Questions and Answers'), lang('What clients ask before they come to us. Anything else? Write to us and the answer comes to your inbox.')),
        $el('section', 'py-5', lang('FAQ'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'accordion', lang('Questions'), array(
                            $faq(1, lang('How do I book a viewing?'), lang('Open the listing and press "Ask for a viewing". Choose a day and a time of day; an agent confirms within a working day.'), lang('viewing, visit, appointment, see the house')),
                            $faq(2, lang('Do buyers pay a fee?'), lang('Not on our own listings. On a home we find for you elsewhere, the fee is agreed in writing before the first viewing.'), lang('fee, commission, buyer, cost')),
                            $faq(3, lang('How is a valuation done?'), lang('An agent visits, measures the rooms, reads the title deed and compares recent sales nearby. You get a price in writing, free of charge.'), lang('valuation, price, worth, appraisal')),
                            $faq(4, lang('What deposit does a tenant pay?'), lang('Usually one or two months\' rent, written in the contract and returned at the end of the lease once the keys are back.'), lang('deposit, rent, tenant, lease')),
                            $faq(5, lang('Can you help with a mortgage?'), lang('Yes. We work with several banks and can tell you what a lender will need before you make an offer.'), lang('mortgage, loan, bank, credit')),
                            $faq(6, lang('How do I follow my requests?'), lang('The viewing and valuation forms open an account for you. Your requests\' pages show their status, and you can write to your agent there.'), lang('status, follow, account, message')),
                        ), array('id' => 're-faq')),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
        $shared('valuation_band'),
    )),
);

// ── Buyer's guide ───────────────────────────────────────────────────────
// The steps of a purchase and what to check at each, one search section
// per part so a search for "title deed" opens the page at it.
$guide_part = function ($id, $title, $points, $topics) use ($el, $heading, $check_list, $searchable) {
    return $el('section', 'mb-5', $title, array(
        $heading('h2', $title, 'h4 fw-semibold mb-3'),
        $check_list($points),
    ), $searchable($id, $topics));
};

$public_pages[] = array(
    'key'              => 'guide',
    'name'             => lang('buyers-guide'),
    'folder'           => 'public',
    'title'            => lang('Buyer\'s Guide'),
    'meta_description' => lang('Buying a home step by step: the budget, viewings, the offer, the title deed checks and the day you collect the keys.'),
    'tree'             => $page(array(
        $page_header(lang('Buyer\'s Guide'), lang('What to do, and what to check, from the first budget to the day you collect the keys.')),
        $el('section', 'py-5', lang('Guide'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $guide_part('budget', lang('1. Set your budget'), array(
                            lang('Add taxes, the title deed fee and moving costs to the price'),
                            lang('Ask a bank what it would lend before you start viewing'),
                            lang('Keep a reserve for the first repairs'),
                        ), lang('budget, mortgage, costs, tax')),
                        $guide_part('viewings', lang('2. View with care'), array(
                            lang('See the home in daylight and once in the evening'),
                            lang('Look at the building\'s shared parts, the roof and the damp corners'),
                            lang('Ask what the monthly building fees are'),
                        ), lang('viewing, visit, inspection')),
                        $guide_part('checks', lang('3. Check the papers'), array(
                            lang('The title deed: owner, shares and any mortgage on it'),
                            lang('Zoning and the building\'s occupancy permit'),
                            lang('Earthquake safety report for older buildings'),
                        ), lang('title deed, zoning, permit, earthquake')),
                        $guide_part('offer', lang('4. Offer and contract'), array(
                            lang('Make the offer in writing, through your agent'),
                            lang('Agree the deposit and the date of transfer'),
                            lang('Read the contract with a lawyer if anything is unclear'),
                        ), lang('offer, contract, deposit, negotiate')),
                        $guide_part('keys', lang('5. Transfer and keys'), array(
                            lang('Transfer at the land registry, with the price paid on the day'),
                            lang('Move the electricity, water and gas into your name'),
                            lang('Collect the keys and read the meters with your agent'),
                        ), lang('land registry, transfer, keys, utilities')),
                    ), '', '7'),
                    $col(array(
                        $el('aside', 'card border-0 bg-body-tertiary rounded-3 position-sticky top-0', lang('Ask an Agent'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $heading('h2', lang('From the first viewing to the keys'), 'h5 fw-semibold mb-3'),
                                $buying_steps(),
                                $link(lang('See the listings'), '{{page:listings}}', 'btn btn-primary w-100 mt-2'),
                            )),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
        $shared('valuation_band'),
    )),
);

return $public_pages;
