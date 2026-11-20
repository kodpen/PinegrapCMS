/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Pop-ups blocks: four ready contents for a designed auto dialog (a
 * shared component an auto dialog shows): an offer code with a Copy
 * button, a newsletter sign-up, an announcement bar and a cart reminder.
 * Each is markup only. Its close buttons carry data-pg-popup-close and its
 * main action data-pg-popup-take, which the site's dialog script reads
 * (frontend.src.js open_dialog(), show_auto_dialog()): a press on the first
 * closes the dialog, on the second counts the pop-up as taken up. The offer
 * code is the text of the element marked data-pg-popup-code; the button
 * marked data-pg-popup-copy copies it and shows its data-pg-popup-copied
 * text in its data-pg-popup-copy-label element for a moment.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.registerBlockFamily(function (kit) {
    'use strict';
    var _sdT = kit._sdT;

    var _SD_BLOCK_CSS = {
        // A centred or corner pop-up keeps a readable measure; the dialog
        // sizes itself to it within the window.
        'pg-popup-card': '.pg-popup-card { max-width: 28rem; }',
        // The offer code in a dashed frame, like a coupon: Bootstrap's
        // .border reads its style from this variable.
        'pg-popup-code': '.pg-popup-code { --bs-border-style: dashed; letter-spacing: .08em; }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'popup-offer':      ['pg-popup-card', 'pg-popup-code'],
        'popup-newsletter': ['pg-popup-card'],
        'popup-cart':       ['pg-popup-card']
    };

    var mark = function (name) { return { name: 'data-pg-popup-' + name, value: '' }; };

    // The cross in the top right corner. The dialog leaves out its own
    // close button when the content brings one.
    var closeCross = function () {
        return kit.tag('button', 'btn-close position-absolute top-0 end-0 m-3', _sdT('Close Button'), [], [
            { name: 'type', value: 'button' },
            { name: 'aria-label', value: _sdT('Close') },
            mark('close')
        ]);
    };

    // A quiet second way out under the main action.
    var closeLink = function (text) {
        return kit.tag('button', 'btn btn-link btn-sm text-body-secondary mt-2', _sdT('No Thanks Button'), [
            kit.createNode('semantic', { tag: 'span', cssClass: '', text: text, customName: _sdT('Label') }, [])
        ], [{ name: 'type', value: 'button' }, mark('close')]);
    };

    // The main action as a link button.
    var takeLink = function (text, cls) {
        return kit.createNode('semantic', { tag: 'a', cssClass: cls, href: '#', target: '', text: text,
            customName: _sdT('Main Action'), _attrs: [mark('take')] });
    };

    var buildOffer = function () {
        return kit.div('pg-popup-card position-relative p-4 p-md-5 text-center', _sdT('Pop-up: Offer Code'), [
            closeCross(),
            kit.icon('bi-gift', 'text-primary d-block mb-3', '40'),
            kit.h('h2', _sdT('10% off your first order'), 'h3 fw-bold mb-2'),
            kit.p(_sdT('Use this code at checkout. It is valid until the end of the month.'), 'text-body-secondary mb-4'),
            kit.div('d-flex flex-wrap align-items-center justify-content-center gap-2', _sdT('Offer Code Row'), [
                kit.createNode('semantic', { tag: 'span', cssClass: 'pg-popup-code px-3 py-2 border border-2 border-primary rounded font-monospace fs-4 fw-bold',
                    text: 'WELCOME10', customName: _sdT('Offer Code'), _attrs: [mark('code')] }, []),
                kit.tag('button', 'btn btn-primary btn-lg d-inline-flex align-items-center gap-2', _sdT('Copy Button'), [
                    kit.createNode('content', { contentType: 'icon', iconName: 'bi-copy', cssClass: '', fontSize: '',
                        _attrs: [{ name: 'aria-hidden', value: 'true' }] }),
                    kit.createNode('semantic', { tag: 'span', cssClass: '', text: _sdT('Copy'), customName: _sdT('Label'),
                        _attrs: [mark('copy-label')] }, [])
                ], [
                    { name: 'type', value: 'button' },
                    mark('copy'),
                    { name: 'data-pg-popup-copied', value: _sdT('Copied') },
                    mark('take')
                ])
            ]),
            closeLink(_sdT('No thanks'))
        ]);
    };

    var buildNewsletter = function () {
        var id = kit.uid('popnl');
        return kit.div('pg-popup-card position-relative p-4 p-md-5 text-center', _sdT('Pop-up: Newsletter'), [
            closeCross(),
            kit.icon('bi-envelope-heart', 'text-primary d-block mb-3', '40'),
            kit.h('h2', _sdT('Get our news first'), 'h3 fw-bold mb-2'),
            kit.p(_sdT('New arrivals and offers in your inbox, once a week.'), 'text-body-secondary mb-4'),
            kit.tag('form', 'd-flex flex-column flex-sm-row gap-2', _sdT('Newsletter Form'), [
                kit.tag('label', 'visually-hidden', _sdT('Email Label'), [
                    kit.createNode('semantic', { tag: 'span', cssClass: '', text: _sdT('Email address'), customName: _sdT('Label') }, [])
                ], [{ name: 'for', value: id + '_email' }]),
                kit.tag('input', 'form-control flex-grow-1', _sdT('Email Input'), [], [
                    { name: 'type', value: 'email' },
                    { name: 'id', value: id + '_email' },
                    { name: 'name', value: id + '_email' },
                    { name: 'placeholder', value: _sdT('Your email address') },
                    { name: 'autocomplete', value: 'email' },
                    { name: 'required', value: 'required' }
                ]),
                kit.tag('button', 'btn btn-primary', _sdT('Subscribe Button'), [
                    kit.createNode('semantic', { tag: 'span', cssClass: '', text: _sdT('Subscribe'), customName: _sdT('Label') }, [])
                ], [{ name: 'type', value: 'submit' }, mark('take')])
            ], [{ name: 'method', value: 'post' }]),
            kit.p(_sdT('No spam, unsubscribe any time.'), 'small text-body-secondary mt-3 mb-0'),
            closeLink(_sdT('Not now'))
        ]);
    };

    // A bar for the top or bottom place: one line, the cross at its end.
    var buildAnnouncement = function () {
        return kit.div('position-relative d-flex flex-wrap align-items-center justify-content-center gap-2 gap-sm-3 py-2 ps-3 pe-5 text-bg-primary text-center', _sdT('Pop-up: Announcement Bar'), [
            kit.icon('bi-megaphone', '', '20'),
            kit.p(_sdT('Free delivery on every order this week.'), 'mb-0 fw-semibold'),
            takeLink(_sdT('Shop now'), 'btn btn-light btn-sm'),
            kit.tag('button', 'btn-close btn-close-white position-absolute top-50 end-0 translate-middle-y me-3', _sdT('Close Button'), [], [
                { name: 'type', value: 'button' },
                { name: 'aria-label', value: _sdT('Close') },
                mark('close')
            ])
        ]);
    };

    // For the cart audience in the corner place.
    var buildCart = function () {
        return kit.div('pg-popup-card position-relative p-4', _sdT('Pop-up: Cart Waiting'), [
            closeCross(),
            kit.div('d-flex align-items-start gap-3 pe-4', _sdT('Cart Message'), [
                kit.icon('bi-cart-check', 'text-primary flex-shrink-0', '32'),
                kit.div('', _sdT('Cart Text'), [
                    kit.h('h2', _sdT('Your cart is waiting'), 'h5 fw-bold mb-1'),
                    kit.p(_sdT('The items you picked are still in your cart. Finish your order before they sell out.'), 'small text-body-secondary mb-3'),
                    takeLink(_sdT('Back to my cart'), 'btn btn-primary btn-sm')
                ])
            ])
        ]);
    };

    return {
        prefix: 'popup-',
        group: { title: _sdT('Pop-ups'), icon: 'bi-window-stack' },
        items: [
            { label: _sdT('Pop-up: Offer Code'),       type: 'component', icon: 'bi-ticket-perforated', extra: { componentType: 'popup-offer' },
              desc: _sdT('A pop-up with an offer code in a dashed frame and a Copy button, for a designed auto dialog.') },
            { label: _sdT('Pop-up: Newsletter'),       type: 'component', icon: 'bi-envelope-heart',    extra: { componentType: 'popup-newsletter' },
              desc: _sdT('A pop-up that asks for an email address to join the newsletter, for a designed auto dialog.') },
            { label: _sdT('Pop-up: Announcement Bar'), type: 'component', icon: 'bi-megaphone',         extra: { componentType: 'popup-announcement' },
              desc: _sdT('A one-line bar with a short notice and a link, for an auto dialog placed at the top or bottom.') },
            { label: _sdT('Pop-up: Cart Waiting'),     type: 'component', icon: 'bi-cart-check',        extra: { componentType: 'popup-cart' },
              desc: _sdT('A small card that reminds a visitor of the items left in the cart, for an auto dialog in the corner.') }
        ],
        build: function (ctype) {
            if (ctype === 'popup-offer') return buildOffer();
            if (ctype === 'popup-newsletter') return buildNewsletter();
            if (ctype === 'popup-announcement') return buildAnnouncement();
            if (ctype === 'popup-cart') return buildCart();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
