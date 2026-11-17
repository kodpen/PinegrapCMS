/**
 * Pinegrap - Enterprise Website Platform
 *
 * The "Notify me" block of visitor notifications, in the core's Forms &
 * Access group beside the newsletter sign-ups: a band with a heading, a line
 * and the part shown before the visitor subscribes (the button), the part
 * shown once notifications are on (with Stop), the hint shown when the
 * browser has them blocked and the line shown after a try that failed.
 *
 * The block is markup only and its root is hidden: the site's script
 * (assets/js/pg_site_push.js, added by the server while Site Settings,
 * Features, Visitor notifications is on) shows it where the browser can
 * subscribe and switches its parts. On the canvas the root is drawn as it
 * will look before the visitor presses.
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
    };

    var _SD_BLOCK_CSS_NEEDS = {
    };

    var hidden = { name: 'hidden', value: '' };
    // The lines a state brings are spoken when they show (pg_site_push.js).
    var status = { name: 'role', value: 'status' };

    var button = function (text, cls, mark) {
        return kit.createNode('semantic', { tag: 'button', cssClass: cls, text: text, customName: text,
            _attrs: [{ name: 'type', value: 'button' }, { name: mark, value: '' }] });
    };

    // A text node with the attributes the script switches it by.
    var marked = function (node, attrs) {
        node.props._attrs = attrs;
        return node;
    };

    // ── Notify me ───────────────────────────────────────────────────────
    var buildNotify = function () {
        return kit.tag('section', 'py-5 bg-body-tertiary', _sdT('Notify Me'), [
            kit.createNode('container', { fluid: false, cssClass: '' }, [
                kit.div('col-lg-7 mx-auto text-center', _sdT('Notify Me Body'), [
                    kit.icon('bi-bell', 'text-primary mb-3', '32'),
                    kit.h('h2', _sdT('Hear from us on this device'), 'fw-bold mb-2'),
                    kit.p(_sdT('One notification when there is something new. No account and no email address; stop whenever you like.'), 'lead text-body-secondary mb-0'),
                    kit.div('mt-4', _sdT('Before Subscribing'), [
                        button(_sdT('Notify Me'), 'btn btn-primary px-4', 'data-pg-push-on')
                    ], [{ name: 'data-pg-push-ask', value: '' }]),
                    kit.div('mt-4', _sdT('Notifications On'), [
                        marked(kit.p(_sdT('Notifications are on.'), 'fw-semibold mb-2'), [status]),
                        // The body's emphasis colour follows the colour mode
                        // and reads at 13:1 or more on the band in both.
                        button(_sdT('Stop'), 'btn btn-sm px-3 border border-secondary text-body-emphasis', 'data-pg-push-stop')
                    ], [{ name: 'data-pg-push-done', value: '' }, hidden]),
                    marked(kit.p(_sdT('Notifications are blocked for this site in your browser. Allow them in the settings of the browser, then reload the page.'), 'small text-body-secondary mt-4 mb-0'),
                        [{ name: 'data-pg-push-blocked', value: '' }, status, hidden]),
                    marked(kit.p(_sdT('Notifications could not be turned on. Please try again.'), 'small text-danger-emphasis mt-3 mb-0'),
                        [{ name: 'data-pg-push-failed', value: '' }, status, hidden])
                ])
            ])
        ], [{ name: 'data-pg-push', value: '' }, hidden]);
    };

    return {
        prefix: 'newsletter-',
        items: [
            { label: _sdT('Newsletter: Notify Me'), type: 'component', icon: 'bi-bell', extra: { componentType: 'newsletter-notify' },
              desc: _sdT('A button that turns on browser notifications from this site, with Stop once they are on.') }
        ],
        build: function (ctype) {
            if (ctype === 'newsletter-notify') return buildNotify();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
