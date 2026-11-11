<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Craft Brewery", its beers: the staff page that adds a
 * beer (the Beers form, a collection, with the sample beers written in
 * when it is made), the beer list with its style filter, the beer page
 * with the strength and bitterness, the tasting notes, the food pairing,
 * the malts and hops and the drinkers' notes as comments with stars, and
 * the list the home page places. Included after parts.php in the same
 * scope; returns array('pages' => ..., 'widgets' => ...).
 *
 * A beer is a record of the Beers form. Style and availability are pick
 * lists whose answers are the words parts.php gives, so a link with
 * ?style= filters the list by the words a visitor sees. The strength and
 * the bitterness are one line of words, as the cards print it; a seasonal
 * beer names its season in its summary, never a month. Featured is the
 * brewery's own choice (office use only): the home page shows the
 * featured beers.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$beer_pages = array();

// The cards of a list, in rows of three.
$beer_grid = function () use ($widget_root, $row, $loop, $beer_card) {
    return $widget_root(array($row(array($loop(array($beer_card()))), '4')));
};

// ── Pages ───────────────────────────────────────────────────────────────

// Every beer, narrowed by style from the address.
$beer_pages[] = array(
    'key'              => 'beers',
    'name'             => lang('beers'),
    'folder'           => 'public',
    'title'            => lang('Our Beers'),
    'meta_description' => lang('Every beer we brew with its style, strength and bitterness, the tasting notes and what to eat with it.'),
    'tree'             => $page(array(
        $page_header(lang('Our Beers'), lang('Brewed in small batches by the harbour. Choose a style to narrow the list.')),
        $el('section', 'py-5', lang('Our Beers'), array(
            $container(array(
                $bw_filter_bar('beers', lang('Beer Style'), 'style', $style_titles),
                $widget('beers_list'),
                $el('p', 'mt-4 mb-0', lang('Text'), array(
                    $link(lang('See what is on tap in the taproom today'), '{{page:taproom}}', 'fw-semibold link-primary'),
                )),
            )),
        )),
    )),
);

// One beer: the record and under it the drinkers' notes with their stars,
// which the brewery is told about.
$beer_pages[] = array(
    'key'              => 'beer',
    'name'             => lang('beer'),
    'folder'           => 'public',
    'title'            => lang('Beer Details'),
    'meta_description' => lang('A beer of the brewery: its style, strength and bitterness, the tasting notes, the food pairing and what drinkers say.'),
    'sitemap'          => false,
    'comments'         => array('label' => lang('Note'), 'allow_new' => true, 'rating' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                                'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('A drinker wrote a note about a beer'),
                                'notify_email' => '{{site_email}}'),
    'tree'             => $page(array(
        $widget('beer_detail'),
        $comments_section(lang('Drinkers\' Notes'), 'beer_notes'),
    )),
);

// The staff page that adds a beer. The form is the Beers collection; the
// sample beers are written in when the form is made, so the lists do not
// open empty (pg_cf_seed_sample_records()).
$sample_beers = array();
foreach ($beers as $key => $b) {
    $sample_beers[] = array(
        'name'         => $b['name'],
        'style'        => $styles[$b['style']]['title'],
        'availability' => $availabilities[$b['availability']]['title'],
        'strength'     => $strength_text($key),
        'summary'      => $b['summary'],
        'notes'        => $b['notes'],
        'pairing'      => $b['pairing'],
        'grain'        => $b['grain'],
        'picture'      => $photo('beer-' . $key, 1200, 900),
        'featured'     => $b['featured'] ? $bw_featured_choices[0] : $bw_featured_choices[1],
    );
}
$beer_pages[] = array(
    'key'     => 'beer_new',
    'name'    => lang('new-beer'),
    'folder'  => 'private',
    'title'   => lang('New Beer'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Brewery Beers'),
        'collection'           => 1,
        'confirmation_message' => lang('The beer is published.'),
        'confirmation_page_id' => '{{tab:beers}}',
        'sample_records'       => $sample_beers,
        // The picture is the beer's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'picture',
    ),
    'tree'    => $bw_form_page(lang('New Beer'),
        lang('Add the beer with its picture and tasting notes; it appears in the beer list as soon as you send it. Mark it featured from its Edit button.'),
        'beer_form'),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$beer_widgets = array(

    // The staff's Beers form: one beer. Availability starts as brewed all
    // year, featured as No.
    'beer_form' => array(
        'page'   => 'beer_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The beer')),
            $field('bw-beer-name', lang('Beer Name'), pg_cf_with_role($input('bw-beer-name', 'text', 'name', 'off'), 'title')),
            $row(array(
                $col(array($field('bw-beer-style', lang('Beer Style'), pg_cf_with_role($select('bw-beer-style', 'style', lang('Choose a beer style…'), $style_titles), 'category'))), '6'),
                $col(array($field('bw-beer-availability', lang('Beer Availability'), $select('bw-beer-availability', 'availability', '', $availability_titles, true, $availability_titles[0]), 'mb-3',
                    lang('A seasonal beer says its season in the summary, never a month.'))), '6'),
            ), '3'),
            $field('bw-beer-strength', lang('Strength and bitterness'), $input('bw-beer-strength', 'text', 'strength', 'off'), 'mb-3',
                sprintf(lang('As the cards show it, for example %s.'), $strength_text('lighthouse'))),
            $form_step(2, lang('Words')),
            $field('bw-beer-summary', lang('Summary'), pg_cf_with_role($textarea('bw-beer-summary', 'summary', 2), 'description'), 'mb-3',
                lang('Two lines for the cards and the top of the beer page.')),
            $field('bw-beer-notes', lang('Tasting Notes'), $textarea('bw-beer-notes', 'notes', 3)),
            $field('bw-beer-pairing', lang('Food Pairing'), $textarea('bw-beer-pairing', 'pairing', 2, false)),
            $field('bw-beer-grain', lang('Malts and Hops'), $input('bw-beer-grain', 'text', 'grain', 'off', false)),
            $form_step(3, lang('Picture')),
            $field('bw-beer-picture', lang('Picture'), pg_cf_with_role($file('bw-beer-picture', 'picture', 'public', 'image/*'), 'media'), 'mb-3',
                lang('A landscape photo of the beer in its glass: the cards and the share image.')),
            $field('bw-beer-featured', lang('Featured'), $select('bw-beer-featured', 'featured', '', $bw_featured_choices, false, $bw_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured beer is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every beer, filtered by the address (style), by name.
    'beers_list' => array(
        'page'   => 'beers',
        'slug'   => lang('form-list'),
        'config' => $bw_list_config('beer_new', 'beer', 'name', 'ASC',
            lang('No beer of this style on the list right now. Ask at the bar what is on tap.'), array(
            'items_per_page' => 12,
            'filters'        => array($bw_filter('style', 'is equal to', '', 'style')),
        )),
        'tree'   => $beer_grid(),
    ),

    // The home page's featured beers, by name, three at most.
    'home_beers' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $bw_list_config('beer_new', 'beer', 'name', 'ASC', lang('No featured beers yet.'), array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($bw_filter('featured', 'is equal to', $bw_featured_choices[0])),
        )),
        'tree'   => $beer_grid(),
    ),

    // The drinkers' notes under a beer with their stars, newest first, one
    // level of answers so the brewers can reply.
    'beer_notes' => array(
        'page'   => 'beer',
        'slug'   => lang('comments'),
        'config' => array(
            'regionType' => 'comments',
            'order'      => 'newest',
            'featured'   => 'none',
            'layout'     => 'threaded',
            'max_depth'  => 1,
            'per_page'   => 10,
        ),
        'tree'   => $comment_thread(array(
            'title'     => lang('Drinkers\' Notes'),
            'empty'     => lang('Nobody has written about this beer yet. Tasted it? Tell us what you think.'),
            'heading'   => lang('Write a note about this beer'),
            'label'     => lang('Your note'),
            'sign_in'   => lang('Sign in to write a note.'),
            'submit'    => lang('Send note'),
            'watch_box' => lang('Notify me when a new note is added.'),
            'follow'    => lang('Notify me of new notes'),
            'unfollow'  => lang('Stop notifying me'),
            'close'     => lang('Stop new notes'),
            'reopen'    => lang('Allow new notes'),
            'reply'     => true,
            'watch'     => true,
        )),
    ),
);

// A fact of the beer page: an icon, the label and the bound value.
$beer_fact = function ($icon_name, $label, $token, $sample) use ($el, $icon, $span, $bind) {
    return $el('p', 'pg-hide-if-empty d-flex align-items-start gap-2 mb-2', $label, array(
        $icon($icon_name, 'text-primary'),
        $span($label, 'text-body-secondary', lang('Label')),
        $span($sample, 'pg-field-value fw-semibold', lang('Value'), $bind($token)),
    ));
};

// A figure of the beer page, the Fresh Release's fact: its label over the
// record's value; the fact leaves when the record has none.
$beer_figure = function ($n, $label, $token, $sample) use ($el, $span, $bind) {
    return $el('div', 'pg-hide-if-empty col border-top border-2 pt-3', str_replace('{var}', $n, lang('Fact {var}')), array(
        $el('dt', 'small fw-normal text-body-secondary mb-1', lang('Fact Label'), array($span($label, '', lang('Label')))),
        $el('dd', 'pg-brewery-figure fs-4 fw-bold lh-sm mb-0', lang('Fact Figure'), array($span($sample, 'pg-field-value', lang('Value'), $bind($token)))),
    ));
};

// One beer, drawn on the editor's Brewery Release block: the picture with
// the sticker that says how long the beer stays, moving slower than the
// page; the name very large, the summary, the style and the strength as
// the release's figures, the malts and hops, the tasting notes and the
// food pairing. The picture's stage leaves with the sticker when the
// record has no picture, so the sticker never hangs over the toolbar.
$beer_sample_key = 'lighthouse';
$beer_sample = $beers[$beer_sample_key];
$beer_avail = $availabilities[$beer_sample['availability']];
$beer_not_found = lang('This beer could not be found. It may have been renamed or taken off the list.');
$beer_widgets['beer_detail'] = array(
    'page'   => 'beer',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:beer_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $beer_not_found,
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $bw_not_found('bi-cup', lang('Beer not found'), $beer_not_found, lang('See all our beers'), 'beers'),
            $el('article', '', lang('Beer Details'), array(
                $bw_toolbar('beers', lang('All Beers')),
                $row(array(
                    $col(array(
                        $fx($el('div', 'pg-hide-if-empty px-4 px-lg-0 mb-4 mb-lg-0', lang('Picture Stage'), array(
                            $fx($el('span', 'pg-field-value d-block', lang('Picture Frame'), array(
                                $image($photo('beer-' . $beer_sample_key, 960, 1200), $beer_sample['name'], 'pg-brewery-photo w-100 object-fit-cover rounded-4 shadow',
                                    array('_bindings' => array('src' => 'picture', 'alt' => 'name'))),
                            )), array('effect' => 'fade', 'trigger' => 'load', 'duration' => 1200, 'delay' => 200)),
                            $el('div', 'pg-hide-if-empty pg-brewery-sticker text-bg-primary small fw-bold text-uppercase shadow', lang('Sticker'), array(
                                $span($beer_avail['title'], 'pg-field-value', lang('Beer Availability'), $bind('availability')),
                            )),
                        )), array('effect' => 'parallax', 'speed' => 15)),
                    ), '', '5'),
                    $col(array(
                        $fx($heading('h1', $beer_sample['name'], 'display-2 fw-bold text-break lh-1 mb-3', $bind('name')),
                            array('effect' => 'slide-up', 'trigger' => 'load', 'duration' => 900)),
                        $fx($para($beer_sample['summary'], 'lead text-body-secondary mb-4', $bind('summary')),
                            array('effect' => 'fade', 'trigger' => 'load', 'duration' => 800, 'delay' => 400)),
                        $fx($el('dl', 'row row-cols-1 row-cols-sm-2 g-3 mb-4', lang('Release Facts'), array(
                            $beer_figure(1, lang('Beer Style'), 'style', $styles[$beer_sample['style']]['title']),
                            $beer_figure(2, lang('Strength and bitterness'), 'strength', $strength_text($beer_sample_key)),
                        )), array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 600, 'stagger' => 120)),
                        $beer_fact('bi-flower1', lang('Malts and Hops'), 'grain', $beer_sample['grain']),
                        $fx($el('div', 'mt-4', lang('Tasting'), array(
                            $bw_long_field(lang('Tasting Notes'), 'notes', $beer_sample['notes']),
                            $bw_long_field(lang('Food Pairing'), 'pairing', $beer_sample['pairing']),
                        )), $fade_in),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Visit the Taproom'), '{{page:taproom}}', 'btn btn-primary rounded-pill px-4'),
                            $link(lang('Book a Tour'), '{{page:tour_booking}}', 'btn btn-outline-primary rounded-pill px-4'),
                        )),
                    ), '', '6', '', '12', 'offset-lg-1'),
                ), '4', '', 'g-lg-5'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $beer_pages, 'widgets' => $beer_widgets);
