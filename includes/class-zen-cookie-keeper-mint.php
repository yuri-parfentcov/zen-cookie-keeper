<?php
/**
 * Mint engine for Zen Cookie Keeper.
 *
 * Constructs a cookie value server-side from a landing-URL click-param per the
 * platform's documented format. This is a server write (not a document.cookie
 * write), so the 24h link-decoration cap does not apply.
 *
 * @package Zen_Cookie_Keeper
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zen_Cookie_Keeper_Mint {

    /**
     * Mint a value for a cookie from the supplied URL params.
     *
     * @param string $cookie_name
     * @param array  $url_params  Sanitised landing params, name => value.
     * @return string '' if nothing to mint (no matching param present).
     */
    public static function mint($cookie_name, $url_params) {
        $rule = Zen_Cookie_Keeper_Formats::rule_for($cookie_name);
        if (!$rule || (isset($rule['source']) && $rule['source'] === 'capture')) {
            return '';
        }

        $params   = isset($rule['param']) && is_array($rule['param']) ? $rule['param'] : array();
        $template = isset($rule['template']) ? $rule['template'] : '{value}';

        $value = '';
        foreach ($params as $p) {
            if (isset($url_params[$p]) && $url_params[$p] !== '') {
                $value = $url_params[$p];
                break;
            }
        }
        if ($value === '') {
            return '';
        }

        $minted = strtr($template, array(
            '{ts}'    => (string) time(),
            '{value}' => $value,
        ));

        // The minted value must satisfy its own format gate.
        if (!Zen_Cookie_Keeper_Formats::validate_value($cookie_name, $minted)) {
            return '';
        }
        return $minted;
    }
}
