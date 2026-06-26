<?php
/**
 * Mint / capture format rules for Zen Cookie Keeper.
 *
 * Platform cookie formats (_fbc, _gcl_*, msclkid, …) drift over time, so the
 * rules live in an updatable option, not hardcoded constants. Each rule carries:
 *   - param:       ordered URL click-params to read for a mint (first present wins)
 *   - template:    how to compose the minted value ({ts} = mint time, {value} = param)
 *   - value_regex: format gate used to validate BOTH a minted value and a
 *                  captured value posted by the companion (anti-injection)
 *   - source:      'mint' | 'capture' (capture rules carry value_regex only)
 *
 * @package Zen_Cookie_Keeper
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zen_Cookie_Keeper_Formats {

    /**
     * The seeded default rule set. Single source of truth for activation seed
     * and for any "reset to defaults" action.
     *
     * @return array
     */
    public static function default_rules() {
        return array(
            '_fbc' => array(
                'source'      => 'mint',
                'param'       => array('fbclid'),
                'template'    => 'fb.1.{ts}.{value}',
                'value_regex' => '/^fb\.1\.\d+\.[A-Za-z0-9_.-]+$/',
            ),
            '_gcl_aw' => array(
                'source'      => 'mint',
                'param'       => array('gclid', 'wbraid', 'gbraid'),
                'template'    => 'GCL.{ts}.{value}',
                'value_regex' => '/^GCL\.\d+\.[A-Za-z0-9_-]+$/',
            ),
            '_gcl_dc' => array(
                'source'      => 'mint',
                'param'       => array('dclid'),
                'template'    => 'GCL.{ts}.{value}',
                'value_regex' => '/^GCL\.\d+\.[A-Za-z0-9_-]+$/',
            ),
            '_uetmsclkid' => array(
                'source'      => 'mint',
                'param'       => array('msclkid'),
                'template'    => '{value}',
                'value_regex' => '/^[a-f0-9]{16,64}$/i',
            ),
            'li_fat_id' => array(
                'source'      => 'mint',
                'param'       => array('li_fat_id'),
                'template'    => '{value}',
                'value_regex' => '/^[A-Za-z0-9_-]{1,128}$/',
            ),
            '_ga' => array(
                'source'      => 'capture',
                'value_regex' => '/^GA\d\.\d\.\d+\.\d+$/',
            ),
            '_fbp' => array(
                'source'      => 'capture',
                'value_regex' => '/^fb\.1\.\d+\.\d+$/',
            ),
            '_ttp' => array(
                'source'      => 'capture',
                'value_regex' => '/^[A-Za-z0-9_.-]{1,128}$/',
            ),
        );
    }

    /**
     * Current (admin-editable) rules, falling back to defaults.
     *
     * @return array
     */
    public static function rules() {
        $rules = get_option('zen_cookie_keeper_mint_formats', array());
        if (!is_array($rules) || empty($rules)) {
            return self::default_rules();
        }
        return $rules;
    }

    public static function rule_for($cookie_name) {
        $rules = self::rules();
        return isset($rules[$cookie_name]) ? $rules[$cookie_name] : null;
    }

    /**
     * Validate a value (minted or captured) against the cookie's format gate.
     * Cookies without a known regex (e.g. arbitrary custom cookies) pass a
     * conservative generic gate.
     *
     * @return bool
     */
    public static function validate_value($cookie_name, $value, $custom_regex = '') {
        if (!is_string($value) || $value === '' || strlen($value) > 512) {
            return false;
        }
        $regex = $custom_regex;
        if ($regex === '') {
            $rule  = self::rule_for($cookie_name);
            $regex = ($rule && !empty($rule['value_regex'])) ? $rule['value_regex'] : '';
        }
        if ($regex === '') {
            // Generic safe gate: cookie-value characters only, no control bytes,
            // no separators that could split a header.
            return (bool) preg_match('/^[A-Za-z0-9_.\-]+$/', $value);
        }
        return (bool) @preg_match($regex, $value);
    }
}
