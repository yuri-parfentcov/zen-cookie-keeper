<?php
/**
 * Custom-cookie form (modal). Included by the registry screen.
 *
 * @package Zen_Cookie_Keeper
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="zenck-custom-modal" class="zenck-modal" style="display:none;">
    <div class="zenck-modal-box">
        <h2><?php esc_html_e('Add custom cookie', 'zen-cookie-keeper'); ?></h2>
        <p class="zenck-modal-err" id="zenck-custom-err"></p>

        <p>
            <label><?php esc_html_e('Cookie name', 'zen-cookie-keeper'); ?><br>
                <input type="text" id="zenck-c-name" class="regular-text" placeholder="_my_cookie">
            </label>
        </p>
        <p>
            <label><?php esc_html_e('Consent bucket (required)', 'zen-cookie-keeper'); ?><br>
                <select id="zenck-c-bucket">
                    <option value=""><?php esc_html_e('— choose —', 'zen-cookie-keeper'); ?></option>
                    <option value="analytics"><?php esc_html_e('Analytics', 'zen-cookie-keeper'); ?></option>
                    <option value="advertising"><?php esc_html_e('Advertising', 'zen-cookie-keeper'); ?></option>
                </select>
            </label>
        </p>
        <p>
            <label><?php esc_html_e('Source', 'zen-cookie-keeper'); ?><br>
                <select id="zenck-c-source">
                    <option value="capture"><?php esc_html_e('Capture (read from browser)', 'zen-cookie-keeper'); ?></option>
                    <option value="mint"><?php esc_html_e('Mint (build from URL parameter)', 'zen-cookie-keeper'); ?></option>
                    <option value="both"><?php esc_html_e('Both', 'zen-cookie-keeper'); ?></option>
                </select>
            </label>
        </p>
        <p>
            <label><?php esc_html_e('URL parameter (for mint, optional)', 'zen-cookie-keeper'); ?><br>
                <input type="text" id="zenck-c-param" class="regular-text" placeholder="myclid">
            </label>
        </p>
        <p>
            <label><?php esc_html_e('Lifetime (days)', 'zen-cookie-keeper'); ?><br>
                <input type="number" id="zenck-c-lifetime" class="small-text" value="90" min="1">
            </label>
        </p>
        <p>
            <label><input type="checkbox" id="zenck-c-httponly"> <?php esc_html_e('HttpOnly (only if no browser script needs to read it)', 'zen-cookie-keeper'); ?></label>
        </p>
        <p>
            <button class="button button-primary" id="zenck-c-save"><?php esc_html_e('Add cookie', 'zen-cookie-keeper'); ?></button>
            <button class="button" id="zenck-c-cancel"><?php esc_html_e('Cancel', 'zen-cookie-keeper'); ?></button>
        </p>
    </div>
</div>
