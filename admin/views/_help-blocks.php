<?php
/**
 * Reusable, plain-language help blocks (embedded help — no external docs).
 *
 * @package Zen_Cookie_Keeper
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('zen_cookie_keeper_help')) {
    /**
     * Echo a named help block.
     *
     * @param string $key why|replaces|consent
     */
    function zen_cookie_keeper_help($key) {
        $blocks = array(
            'why' => array(
                'title' => __('Why this exists', 'zen-cookie-keeper'),
                'body'  => __('Safari and Firefox now delete marketing and analytics cookies after 7 days (24 hours on ad clicks) when those cookies are set by JavaScript. So a visitor who comes back a week later looks brand-new, paid-ad attribution breaks before the sale, and your reports over-count "Direct" traffic. This plugin sets those cookies from your server instead, which the browser trusts for the full intended lifetime.', 'zen-cookie-keeper'),
            ),
            'replaces' => array(
                'title' => __('What this replaces from server-side GTM', 'zen-cookie-keeper'),
                'body'  => __('Server-side GTM solves the same cookie problem, but it needs a separate always-on container, a custom subdomain, SSL, and a monthly bill — for what is really a single HTTP header. This plugin writes that same cookie from your own WordPress backend, free, with no container. Because the cookie is set from the same origin and IP as your site, it is unambiguously first-party and stays clear of the browser limits that a container on a different network runs into.', 'zen-cookie-keeper'),
            ),
            'consent' => array(
                'title' => __('How consent works here', 'zen-cookie-keeper'),
                'body'  => __('Consent is purpose-specific: analytics and advertising are asked for, and granted, separately. The plugin writes nothing until the matching bucket is granted (it reads this from your existing cookie banner via Google Consent Mode). Each cookie\'s lifetime must match what your privacy policy says. Withdrawal and erasure happen through your consent banner or a deletion request — not by a visitor clearing their browser.', 'zen-cookie-keeper'),
            ),
        );
        if (empty($blocks[$key])) {
            return;
        }
        echo '<div class="zenck-help"><h3>' . esc_html($blocks[$key]['title']) . '</h3><p>' . esc_html($blocks[$key]['body']) . '</p></div>';
    }
}
