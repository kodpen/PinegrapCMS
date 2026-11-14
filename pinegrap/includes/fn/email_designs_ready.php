<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The ready e-mail designs: five page trees in code, built from the mail
 * blocks of includes/fn/email_designs.php (the same parts the E-mail group
 * of the designer's palette offers). "Use this design" opens one as a new
 * e-mail design; the Templates tab shows each with a preview drawn from its
 * tree and passed through the mail pass, as a campaign stores it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The ready designs in the order the Templates tab lists them.
 *
 * @return array key => title, text, icon (English; lang() when printed)
 */
function pg_email_ready_designs()
{
    return array(
        'newsletter'   => array('title' => 'Newsletter', 'text' => 'News, a story with a picture and a link to read more.', 'icon' => 'bi-newspaper'),
        'sale'         => array('title' => 'Sale or launch', 'text' => 'A headline offer with two product cards and a button to the shop.', 'icon' => 'bi-tag'),
        'announcement' => array('title' => 'Announcement', 'text' => 'One piece of news told plainly, with a button.', 'icon' => 'bi-megaphone'),
        'welcome'      => array('title' => 'Welcome e-mail', 'text' => 'A greeting by name for a new member or subscriber.', 'icon' => 'bi-person-raised-hand'),
        'order'        => array('title' => 'After an order', 'text' => 'Thanks by name, a product card and a way back to the shop.', 'icon' => 'bi-bag-check'),
    );
}

/**
 * Whether a key names a ready design.
 *
 * @param mixed $key
 * @return bool
 */
function pg_email_ready_design_valid($key)
{
    return is_string($key) && array_key_exists($key, pg_email_ready_designs());
}

/**
 * A heading block.
 *
 * @param string $text
 * @param string $tag
 * @return array
 */
function pg_mail_part_heading($text, $tag = 'h1')
{
    return pg_mail_block_node($tag, 'heading', lang('Heading'), array(), array('text' => $text));
}

/**
 * A paragraph block.
 *
 * @param string $text
 * @return array
 */
function pg_mail_part_text($text)
{
    return pg_mail_block_node('p', 'text', lang('Text'), array(), array('text' => $text));
}

/**
 * A centred button: a link in its own row.
 *
 * @param string $text
 * @param string $href
 * @return array
 */
function pg_mail_part_button($text, $href = '/')
{
    return pg_mail_block_node('p', 'button_row', lang('Button'), array(
        pg_mail_block_node('a', 'button', lang('Button link'), array(), array('text' => $text, 'href' => $href, 'target' => '')),
    ));
}

/**
 * A picture the width of the frame's content.
 *
 * @param string $seed
 * @param string $alt
 * @return array
 */
function pg_mail_part_picture($seed, $alt)
{
    return pg_mail_block_node('img', 'picture', lang('Picture'), array(), array(
        'src' => 'https://picsum.photos/seed/' . $seed . '/1200/600',
        'alt' => $alt,
        '_attrs' => array(array('name' => 'width', 'value' => '552')),
    ));
}

/**
 * The header part: the logo, one line and a divider.
 *
 * @param string $line
 * @return array
 */
function pg_mail_part_header($line)
{
    return pg_mail_block_node('div', 'header', lang('E-mail header'), array(
        pg_mail_block_node('img', 'logo', lang('Logo'), array(), array(
            'src' => 'https://picsum.photos/seed/pglogo/320/96',
            'alt' => lang('Your logo'),
            '_attrs' => array(array('name' => 'width', 'value' => '160')),
        )),
        pg_mail_part_text($line),
        pg_mail_block_node('hr', 'divider', lang('Divider')),
    ));
}

/**
 * A product card written by hand.
 *
 * @param string $seed
 * @param string $name
 * @param string $price
 * @return array
 */
function pg_mail_part_product($seed, $name, $price)
{
    return pg_mail_block_node('div', 'product', lang('Product card'), array(
        pg_mail_block_node('img', 'picture', lang('Product picture'), array(), array(
            'src' => 'https://picsum.photos/seed/' . $seed . '/600/600',
            'alt' => $name,
            '_attrs' => array(array('name' => 'width', 'value' => '240')),
        )),
        pg_mail_part_heading($name, 'h2'),
        pg_mail_block_node('p', 'price', lang('Price'), array(), array('text' => $price)),
        pg_mail_part_button(lang('See the product')),
    ));
}

/**
 * Two columns side by side that stack on a phone.
 *
 * @param array $left  blocks
 * @param array $right blocks
 * @return array
 */
function pg_mail_part_columns($left, $right)
{
    $column = function ($n, $blocks) {
        return pg_mail_block_node('td', 'column', lang(array('string' => 'Column {var:1}', 'vars' => array($n))), $blocks, array(
            'cssClass' => 'pg-mail-col',
            '_attrs' => array(array('name' => 'width', 'value' => '50%'), array('name' => 'valign', 'value' => 'top')),
        ));
    };

    return pg_mail_block_node('table', 'columns', lang('Two columns'), array(
        array('type' => 'semantic', 'props' => array('tag' => 'tbody', 'cssClass' => '', 'customName' => ''), 'children' => array(
            array('type' => 'semantic', 'props' => array('tag' => 'tr', 'cssClass' => '', 'customName' => ''), 'children' => array(
                $column(1, $left),
                $column(2, $right),
            )),
        )),
    ), array('_attrs' => pg_mail_table_attrs('100%')));
}

/**
 * The footer part: the web version, the address and the preferences and
 * unsubscribe links, filled when a campaign is stored (pg_mail_footer_fill()).
 *
 * @return array
 */
function pg_mail_part_footer()
{
    $link = function ($name, $label, $anchor) {
        return pg_mail_block_node('a', 'footer_link', $name, array(), array('text' => $label, 'href' => '#' . $anchor, 'target' => ''));
    };

    return pg_mail_block_node('div', 'footer', lang('E-mail footer'), array(
        pg_mail_block_node('p', 'footer_text', lang('Web version'), array(
            $link(lang('Web version link'), lang('View this email at our site'), 'pgmail-web'),
        )),
        pg_mail_block_node('p', 'footer_text', lang('Address'), array(), array(
            'text' => lang('Your organization\'s name and address are written here when the campaign is saved.'),
            '_attrs' => array(array('name' => 'data-pg-mail-address', 'value' => '')),
        )),
        pg_mail_block_node('p', 'footer_text', lang('Preferences'), array(
            $link(lang('Preferences link'), lang('Update email preferences'), 'pgmail-preferences'),
            $link(lang('Unsubscribe link'), lang('Unsubscribe'), 'pgmail-unsubscribe'),
        )),
    ), array('_attrs' => array(array('name' => 'data-pg-mail-footer', 'value' => ''))));
}

/**
 * The tree of a ready design: a root carrying props.pgEmail and the frame
 * with the design's blocks, the footer last. null for an unknown key.
 *
 * @param string $key
 * @return array|null
 */
function pg_email_ready_design_tree($key)
{
    if (!pg_email_ready_design_valid($key)) {
        return null;
    }

    switch ($key) {
        case 'newsletter':
            $blocks = array(
                pg_mail_part_header(lang('News from our shop')),
                pg_mail_part_heading(lang('What is new this month')),
                pg_mail_part_text(lang('A short opening that tells your readers what this issue holds.')),
                pg_mail_part_picture('pgnews', lang('Describe the picture')),
                pg_mail_part_heading(lang('The story of the month'), 'h2'),
                pg_mail_part_text(lang('Two or three sentences about the story; the rest waits on your site.')),
                pg_mail_part_button(lang('Read more')),
            );
            break;

        case 'sale':
            $blocks = array(
                pg_mail_part_header(lang('News from our shop')),
                pg_mail_part_heading(lang('Up to 30% off this week')),
                pg_mail_part_text(lang('Say what is on sale, until when, and for whom.')),
                pg_mail_part_columns(
                    array(pg_mail_part_product('pgsale1', lang('Product name'), lang('Price'))),
                    array(pg_mail_part_product('pgsale2', lang('Product name'), lang('Price')))
                ),
                pg_mail_part_button(lang('Shop the sale')),
            );
            break;

        case 'announcement':
            $blocks = array(
                pg_mail_part_header(lang('News from our shop')),
                pg_mail_part_heading(lang('We have news')),
                pg_mail_part_text(lang('Tell the news in the first sentence; the details can follow.')),
                pg_mail_part_text(lang('A second paragraph for what your readers should do or know.')),
                pg_mail_part_button(lang('Find out more')),
            );
            break;

        case 'welcome':
            $blocks = array(
                pg_mail_part_header(lang('News from our shop')),
                pg_mail_part_text(lang('Hello ^^first_name^^,')),
                pg_mail_part_heading(lang('Welcome aboard')),
                pg_mail_part_text(lang('Thank you for joining us. Here is what you can look forward to.')),
                pg_mail_part_button(lang('Visit our site')),
            );
            break;

        default:
            $blocks = array(
                pg_mail_part_header(lang('News from our shop')),
                pg_mail_part_text(lang('Hello ^^first_name^^,')),
                pg_mail_part_heading(lang('Thank you for your order')),
                pg_mail_part_text(lang('We hope you enjoy it. Here is something that goes well with it.')),
                pg_mail_part_product('pgorder', lang('Product name'), lang('Price')),
                pg_mail_part_button(lang('Back to the shop')),
            );
    }

    $blocks[] = pg_mail_part_footer();

    return array(
        'type'     => 'root',
        'props'    => array('pgEmail' => 1),
        'children' => array(pg_mail_frame_tree($blocks)),
    );
}

/**
 * A ready design drawn as it would be sent: its tree drawn as a page body
 * and passed through the mail pass. For the Templates tab's previews.
 *
 * @param string $key
 * @return string '' for an unknown key
 */
function pg_email_ready_design_html($key)
{
    $tree = pg_email_ready_design_tree($key);

    if ($tree === null) {
        return '';
    }

    $pass = pg_mail_pass(generate_style_code_from_tree($tree, ''));

    return $pass['html'];
}
