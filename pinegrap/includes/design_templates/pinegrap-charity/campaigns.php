<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Charity", its campaigns: the staff page that adds a
 * campaign (the Campaigns form, a collection, with the campaigns of
 * parts.php written in as its records when it is made), the campaign list
 * with its cause filter, a campaign's own page with its progress and its
 * updates, and the home page's list of featured campaigns. Included after
 * parts.php, mail.php and forms.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A campaign is a record of the Campaigns form. Its cause is a pick list
 * whose answers are the causes' titles, so the cause links of the other
 * pages (?cause=<title>) filter the list. The goal and what is raised are
 * the words the cards show; the percent is a number the staff update as
 * gifts come in, and the progress bars read it. Featured is the staff's
 * (office use only): a featured campaign is shown on the home page. The
 * updates are the campaign page's comments: the staff write them, a
 * signed-in visitor may ask a question that waits for the staff, and
 * anyone may follow them by e-mail.
 *
 * Every page here stands without the shop: a campaign's Donate button
 * opens the donate page with the campaign named (?campaign=), where its
 * gift and the pledge form are.
 *
 * parts.php keeps the sample campaigns in $campaigns, so every name here
 * starts with campaign_ and none is $campaigns.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$campaign_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// The answers of the Featured list: the first marks a campaign for the home.
$campaign_featured_choices = array(lang('Yes'), lang('No'));
$campaign_cause_titles = array();
foreach ($causes as $c) $campaign_cause_titles[] = $c['title'];

// The campaign the sample nodes show before a record fills them.
$campaign_sample_key = 'wells';
$campaign_sample = $campaigns[$campaign_sample_key];
$campaign_sample_goal = $money($campaign_sample['goal']);
$campaign_sample_raised = $money($raised($campaign_sample['goal'], $campaign_sample['percent']));

// A progress bar filled from the record: the percent in its value, and in
// its words for a browser that draws no bar.
$campaign_progress = function ($label) use ($progress) {
    $bar = $progress('{{percent}}', $label);
    $bar['props']['_bindings'] = array('text' => 'percent');
    return $bar;
};

// What a campaign has raised of its goal, from the record.
$campaign_figures = function ($class) use ($el, $span, $bind, $campaign_sample_goal, $campaign_sample_raised) {
    return $el('p', 'd-flex justify-content-between ' . $class, lang('Figures'), array(
        $span($campaign_sample_raised, 'fw-bold pch-figure', lang('Raised'), $bind('raised')),
        $span($campaign_sample_goal, 'text-body-secondary pch-figure', lang('Goal'), $bind('goal')),
    ));
};

// A campaign of a list: its cover, cause and place, title, line, progress
// and figures, opening the campaign's page. The record version of
// parts.php's sample card.
$campaign_record_card = function () use ($el, $para, $link, $image, $span, $col, $bind, $photo, $causes, $campaign_sample, $campaign_progress, $campaign_figures) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden position-relative pch-lift', lang('Campaign Card'), array(
            $el('div', 'ratio ratio-16x9 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('campaign-wells', 800, 450), $campaign_sample['title'], 'object-fit-cover',
                    array('_bindings' => array('src' => 'cover', 'alt' => 'title'))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('p', 'small text-body-secondary mb-1', lang('Cause'), array(
                    $span($causes[$campaign_sample['cause']]['title'], '', lang('Cause'), $bind('cause')),
                    $span(' · ', '', lang('Text')),
                    $span($campaign_sample['place'], '', lang('Place'), $bind('place')),
                )),
                $el('h3', 'h5 fw-bold mb-2 position-static', lang('Title'), array(
                    $link($campaign_sample['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $para($campaign_sample['summary'], 'small text-body-secondary mb-3', $bind('summary')),
                $el('div', 'mt-auto', lang('Progress'), array(
                    $campaign_progress($campaign_sample['title']),
                    $campaign_figures('small mt-2 mb-0'),
                )),
            )),
        )),
    ), '6', '4');
};

// A list's settings: the Campaigns form, the campaign page, newest first.
$campaign_list_config = function ($more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:campaign_new}}',
        'detail_page_id'      => '{{tab:campaign}}',
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
    ), $more);
};
$campaign_grid = function () use ($widget_root, $row, $loop, $campaign_record_card) {
    return $widget_root(array($row(array($loop(array($campaign_record_card()))), '4')));
};

// The links above the list: every campaign, then one per cause, each the
// address a cause link of the other pages opens.
$campaign_cause_bar = function () use ($el, $link, $span, $attr, $causes, $campaigns_in) {
    $children = array(
        $span(lang('Cause'), 'small fw-semibold text-body-secondary me-1', lang('Label')),
        $link(lang('All'), '{{page:campaigns}}', 'btn btn-sm btn-outline-secondary rounded-pill'),
    );
    foreach (array_keys($causes) as $key) {
        $children[] = $link($causes[$key]['title'], $campaigns_in($key), 'btn btn-sm btn-outline-secondary rounded-pill');
    }
    return $el('nav', 'd-flex flex-wrap align-items-center gap-2 mb-4', lang('Filters'), $children,
        array('_attrs' => array($attr('aria-label', lang('Campaigns by cause')))));
};

// ── Pages ───────────────────────────────────────────────────────────────

$campaign_pages[] = array(
    'key'              => 'campaigns',
    'name'             => lang('campaigns'),
    'folder'           => 'public',
    'title'            => lang('Campaigns'),
    'meta_description' => lang('The campaigns open now, for clean water, education, food, health and emergency relief: where each stands and how to give to it.'),
    'tree'             => $page(array(
        $page_header(lang('Campaigns'),
            lang('Each campaign has a goal, a place and a team in the field. Choose a cause to narrow the list, and follow a campaign to hear what it builds.')),
        $el('section', 'py-5', lang('Campaigns'), array(
            $container(array($campaign_cause_bar(), $widget('campaigns_list'))),
        ), $searchable('campaign-list', lang('campaigns, appeals, projects, goal, progress'))),
        $shared('donate_band'),
    )),
);

// One campaign: its cover, cause and place, title and line, progress and
// figures with the Donate button, its story, then its updates.
$campaign_pages[] = array(
    'key'              => 'campaign',
    'name'             => lang('campaign'),
    'folder'           => 'public',
    'title'            => lang('Campaign'),
    'meta_description' => lang('A campaign of ours: where it works, what it builds, how far it has come and how to give to it.'),
    'sitemap'          => false,
    'comments'         => array('label' => lang('Update'), 'allow_new' => true, 'auto_publish' => false, 'show_date' => true, 'login' => true,
                                'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is news from a campaign you follow'),
                                'notify_email' => '{{site_email}}'),
    'tree'             => $page(array(
        $widget('campaign_detail'),
        $comments_section(lang('Updates'), 'campaign_updates'),
        $shared('donate_band'),
    )),
);

// The staff page that adds a campaign. The form is the Campaigns
// collection; the campaigns of parts.php are written in when the form is
// made, so the lists do not open empty (pg_cf_seed_sample_records()).
$campaign_samples = array();
foreach ($campaigns as $key => $c) {
    $campaign_samples[] = array(
        'title'    => $c['title'],
        'cause'    => $causes[$c['cause']]['title'],
        'place'    => $c['place'],
        'summary'  => $c['summary'],
        'story'    => $c['story'],
        'cover'    => $photo('campaign-' . $key, 1200, 675),
        'goal'     => $money($c['goal']),
        'raised'   => $money($raised($c['goal'], $c['percent'])),
        'percent'  => (string)$c['percent'],
        'featured' => !empty($c['featured']) ? $campaign_featured_choices[0] : $campaign_featured_choices[1],
    );
}
$campaign_pages[] = array(
    'key'     => 'campaign_new',
    'name'    => lang('new-campaign'),
    'folder'  => 'private',
    'title'   => lang('New Campaign'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Campaigns'),
        'collection'           => 1,
        'confirmation_message' => lang('The campaign is published.'),
        'confirmation_page_id' => '{{tab:campaigns}}',
        'sample_records'       => $campaign_samples,
        // The cover is the campaign's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'cover',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Campaign'), lang('Add the campaign with its cover and story; it appears in the list as soon as you send it. Update what is raised and the percent from the campaign\'s Edit button as gifts come in.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('campaign_form'))),
                        )),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$campaign_missing = lang('This campaign could not be found. It may have ended; see the campaigns open now.');

$campaign_widgets = array(

    // The staff's Campaigns form: one campaign. Featured starts No and is
    // the staff's (office use only).
    'campaign_form' => array(
        'page'   => 'campaign_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The campaign')),
            $field('ch-cp-title', lang('Title'), pg_cf_with_role($input('ch-cp-title', 'text', 'title', 'off'), 'title'), 'mb-3',
                lang('A few words that say what the campaign builds, for example Ten Wells for the Dry Valley.')),
            $row(array(
                $col(array($field('ch-cp-cause', lang('Cause'), pg_cf_with_role($select('ch-cp-cause', 'cause', lang('Choose a cause…'), $campaign_cause_titles), 'category'))), '6'),
                $col(array($field('ch-cp-place', lang('Place'), $input('ch-cp-place', 'text', 'place', 'off'))), '6'),
            ), '3'),
            $field('ch-cp-summary', lang('Summary'), pg_cf_with_role($textarea('ch-cp-summary', 'summary', 2), 'description'), 'mb-3',
                lang('One sentence for the cards and under the title.')),
            $field('ch-cp-story', lang('Story'), $textarea('ch-cp-story', 'story', 6), 'mb-3',
                lang('Who the campaign is for, what it builds and what a gift pays for.')),
            $field('ch-cp-cover', lang('Cover'), pg_cf_with_role($file('ch-cp-cover', 'cover', 'public', 'image/*'), 'media'), 'mb-3',
                lang('A wide photo from the field, shown on the cards and at the top of the campaign\'s page.')),
            $form_step(2, lang('Goal and progress')),
            $row(array(
                $col(array($field('ch-cp-goal', lang('Goal'), $input('ch-cp-goal', 'text', 'goal', 'off'), 'mb-3', lang('As the cards show it, for example $60,000.'))), '6'),
                $col(array($field('ch-cp-raised', lang('Raised'), $input('ch-cp-raised', 'text', 'raised', 'off', false), 'mb-3', lang('As the cards show it, for example $43,200.'))), '6'),
                $col(array($field('ch-cp-percent', lang('Percent raised'),
                    $input('ch-cp-percent', 'number', 'percent', 'off', true, array('kind' => 'number'), array($attr('min', '0'), $attr('max', '100'), $attr('step', '1'))),
                    'mb-3', lang('From 0 to 100: how full the progress bars are.'))), '6'),
                $col(array($field('ch-cp-featured', lang('Featured'), $select('ch-cp-featured', 'featured', '', $campaign_featured_choices, false, $campaign_featured_choices[1], array('office_use_only' => true)),
                    'mb-3', lang('A featured campaign is shown on the home page.'))), '6'),
            ), '3'),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every campaign, filtered by the cause in the address, with a search
    // over their words.
    'campaigns_list' => array(
        'page'   => 'campaigns',
        'slug'   => lang('form-list'),
        'config' => $campaign_list_config(array(
            'items_per_page' => 12,
            'filters'        => array(array('field' => 'cause', 'operator' => 'is equal to', 'value' => '',
                                            'dynamic_value' => 'url parameter', 'dynamic_value_attribute' => 'cause')),
            'search_enabled' => true,
            'search_fields'  => array('title', 'cause', 'place', 'summary', 'story'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the campaigns: a place, a cause, a word...'),
            'empty_message'  => lang('No campaign of this cause is open right now. Give where it is needed most, and we put your gift to work.'),
        )),
        'tree'   => $campaign_grid(),
    ),

    // The home page's featured campaigns, three at most.
    'home_campaigns' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $campaign_list_config(array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array(array('field' => 'featured', 'operator' => 'is equal to', 'value' => $campaign_featured_choices[0], 'dynamic_value' => '')),
            'empty_message'  => lang('No featured campaigns yet.'),
        )),
        'tree'   => $campaign_grid(),
    ),

    // One campaign: the way back and the Edit button, the cause and place,
    // the title and line, the cover, the story, and beside them the
    // progress, the figures and the ways to give.
    'campaign_detail' => array(
        'page'   => 'campaign',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:campaign_new}}',
            'access_control'      => 'public',
            'not_found_message'   => $campaign_missing,
        ),
        'tree'   => $widget_root(array(
            $container(array(
                $el('div', 'text-center py-5', lang('Not Found'), array(
                    $icon('bi-heart', 'display-5 text-body-tertiary'),
                    $heading('h2', lang('Campaign not found'), 'h3 fw-semibold mt-3'),
                    $para($campaign_missing, 'text-body-secondary mb-4', $bind('not_found')),
                    $link(lang('See our campaigns'), '{{page:campaigns}}', 'btn btn-primary rounded-pill px-4'),
                ), $show_when('record_not_found')),
                $el('article', '', lang('Campaign'), array(
                    $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                        $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                            $icon('bi-arrow-left'),
                            $span(lang('All Campaigns'), '', lang('Link Text')),
                        ), array('href' => '{{page:campaigns}}')),
                        $edit_button(),
                    )),
                    $el('p', 'd-flex flex-wrap align-items-center gap-2 small mb-2', lang('Cause'), array(
                        $link($causes[$campaign_sample['cause']]['title'], '#', 'badge rounded-pill bg-primary-subtle text-primary-emphasis text-decoration-none', array(),
                            array_merge($page_link('campaigns', array('cause' => 'cause')), array('_bindings' => array('text' => 'cause', 'href' => '__page_link')))),
                        $el('span', 'd-inline-flex align-items-center gap-1 text-body-secondary', lang('Place'), array(
                            $icon('bi-geo-alt'),
                            $span($campaign_sample['place'], '', lang('Place'), $bind('place')),
                        )),
                    )),
                    $heading('h1', $campaign_sample['title'], 'display-6 fw-semibold mb-2', $bind('title')),
                    $para($campaign_sample['summary'], 'lead text-body-secondary mb-4 pg-hide-if-empty', $bind('summary')),
                    $row(array(
                        $col(array(
                            $optional_image('cover', 'title', 'rounded-4 object-fit-cover', 'ratio ratio-16x9 mb-4',
                                $photo('campaign-' . $campaign_sample_key, 1200, 675), $campaign_sample['title']),
                            $el('div', 'pg-hide-if-empty mb-4', lang('Story'), array(
                                $heading('h2', lang('The story'), 'h5 fw-semibold mb-2'),
                                $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $campaign_sample['story']), $bind('story'))),
                            )),
                        ), '', '8'),
                        $col(array(
                            $el('aside', 'card border-0 shadow-sm rounded-4', lang('Give to This Campaign'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $para(lang('Raised so far'), 'small text-uppercase fw-semibold text-body-secondary mb-1'),
                                    $para($campaign_sample_raised, 'h2 fw-bold text-primary mb-1 pch-figure', $bind('raised')),
                                    $el('p', 'small text-body-secondary mb-3', lang('Goal'), array(
                                        $span(lang('of a goal of'), 'me-1', lang('Text')),
                                        $span($campaign_sample_goal, 'fw-semibold pch-figure', lang('Goal'), $bind('goal')),
                                    )),
                                    $campaign_progress($campaign_sample['title']),
                                    $el('p', 'small text-body-secondary mt-2 mb-4', lang('Percent'), array(
                                        $span((string)$campaign_sample['percent'], 'fw-semibold', lang('Percent'), $bind('percent')),
                                        $span(lang('% of the goal'), '', lang('Text')),
                                    )),
                                    // Opens the donate page with this campaign
                                    // named (?campaign=): its gift and the
                                    // pledge form are there.
                                    $link(lang('Donate to This Campaign'), '#', 'btn btn-primary rounded-pill w-100 mb-2', array(),
                                        $page_link('donate', array('campaign' => 'title'))),
                                    $link(lang('Where the money goes'), '{{page:transparency}}', 'btn btn-link link-secondary w-100 mb-3'),
                                    $el('p', 'd-flex gap-2 small text-body-secondary mb-0', lang('Note'), array(
                                        $icon('bi-info-circle', 'mt-1'),
                                        $span(lang('The figures are updated by our team as gifts arrive. Follow the campaign below to hear what it builds.'), '', lang('Text')),
                                    )),
                                )),
                            )),
                        ), '', '4'),
                    ), '4', '', 'g-lg-5'),
                ), $show_when('record_found')),
            ), 'py-5'),
        )),
    ),

    // The campaign's updates: the page's Comments widget, newest first, ten
    // a page. The staff publish theirs; a visitor's question waits for
    // them. Anyone may follow the updates by e-mail.
    'campaign_updates' => array(
        'page'   => 'campaign',
        'slug'   => lang('comments'),
        'config' => array(
            'regionType' => 'comments',
            'order'      => 'newest',
            'featured'   => 'none',
            'layout'     => 'flat',
            'max_depth'  => 1,
            'per_page'   => 10,
        ),
        'tree'   => $comment_thread(array(
            'title'     => lang('Updates'),
            'empty'     => lang('No updates yet. Follow the campaign to hear the first news from the field.'),
            'heading'   => lang('Write to the campaign team'),
            'label'     => lang('Your message'),
            'sign_in'   => lang('Sign in to write to the campaign team.'),
            'submit'    => lang('Send message'),
            'watch_box' => lang('E-mail me the updates of this campaign'),
            'follow'    => lang('Follow this campaign'),
            'unfollow'  => lang('Stop following'),
            'close'     => lang('Stop new messages'),
            'reopen'    => lang('Allow new messages'),
            'reply'     => false,
            'watch'     => true,
        )),
    ),
);

return array('pages' => $campaign_pages, 'widgets' => $campaign_widgets);
