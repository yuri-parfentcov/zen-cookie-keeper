<?php
/**
 * Anchor cookie lifecycle for Zen Cookie Keeper.
 *
 * The anchor is the durable, server-set, HttpOnly identity carrier. It is NEVER
 * written or read from JavaScript. It is not a catalog row: its existence is
 * COMPUTED — it should exist only while at least one consented bucket has an
 * enabled cookie under it, and is purged when nothing remains to restore.
 *
 * @package Zen_Cookie_Keeper
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zen_Cookie_Keeper_Anchor {

    /**
     * Hash a raw anchor token for storage (the raw token lives only in the
     * browser cookie; the DB stores only this hash).
     */
    public static function hash_token($token) {
        return hash('sha256', wp_salt('auth') . '|anchor|' . $token);
    }

    /**
     * Read the incoming raw anchor token from the request, if present and
     * well-formed. Works for both the page render and the REST request.
     *
     * @return string '' if none.
     */
    public static function incoming_token() {
        $name = ZEN_COOKIE_KEEPER_ANCHOR_NAME;
        if (empty($_COOKIE[$name])) {
            return '';
        }
        $token = sanitize_text_field(wp_unslash($_COOKIE[$name]));
        return self::is_valid_token($token) ? $token : '';
    }

    public static function is_valid_token($token) {
        return is_string($token) && preg_match('/^[A-Za-z0-9]{43,86}$/', $token);
    }

    /**
     * Resolve the existing anchor row for the incoming token, if any.
     *
     * @return array|null Store row (ARRAY_A) plus 'token' key, or null.
     */
    public static function resolve() {
        $token = self::incoming_token();
        if ($token === '') {
            return null;
        }
        $row = Zen_Cookie_Keeper_Store::instance()->get_anchor_by_hash(self::hash_token($token));
        if (!$row) {
            return null;
        }
        $row['token'] = $token;
        return $row;
    }

    /**
     * Whether an anchor should exist at all, given consent + catalog. A carrier
     * with no payload is meaningless.
     *
     * @param array $granted ['analytics'=>bool, 'advertising'=>bool]
     * @return bool
     */
    public static function should_exist($granted) {
        foreach ($granted as $bucket => $ok) {
            if ($ok && count(Zen_Cookie_Keeper_Registry::enabled_cookies($bucket)) > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * The anchor TTL: as long as the longest declared lifetime among enabled
     * cookies in the granted buckets (so it outlives nothing it must carry).
     *
     * @return int seconds
     */
    public static function ttl_for($granted) {
        $ttl = 0;
        foreach ($granted as $bucket => $ok) {
            if (!$ok) {
                continue;
            }
            foreach (Zen_Cookie_Keeper_Registry::enabled_cookies($bucket) as $spec) {
                $ttl = max($ttl, (int) $spec['lifetime']);
            }
        }
        return $ttl > 0 ? $ttl : 90 * DAY_IN_SECONDS;
    }

    /**
     * Mint a fresh anchor: cryptographically random token, persisted row.
     *
     * @return array ['token'=>raw, 'id'=>int, 'spec'=>setcookie-spec]
     */
    public static function mint($granted, $is_bot = false, $ja4 = '') {
        $token = self::random_token();
        $ttl   = self::ttl_for($granted);

        $store = Zen_Cookie_Keeper_Store::instance();
        $id    = $store->insert_anchor(
            self::hash_token($token),
            Zen_Cookie_Keeper_Sites::registrable_domain(),
            Zen_Cookie_Keeper_IP::hash_ip(),
            Zen_Cookie_Keeper_IP::hash_ua(),
            $is_bot,
            $ja4,
            $ttl
        );

        if (!$id) {
            return null;
        }

        $store->insert_audit($id, 'anchor_mint', array('domain' => Zen_Cookie_Keeper_Sites::registrable_domain()));

        return array(
            'token' => $token,
            'id'    => (int) $id,
            'spec'  => self::cookie_spec($token, $ttl),
        );
    }

    /**
     * Build the Set-Cookie spec for the anchor (always HttpOnly + Secure + Lax).
     *
     * @return array
     */
    public static function cookie_spec($token, $ttl) {
        return array(
            'name'  => ZEN_COOKIE_KEEPER_ANCHOR_NAME,
            'value' => $token,
            'opts'  => array(
                'max_age'  => (int) $ttl,
                'httponly' => true,
                'secure'   => true,
                'samesite' => 'Lax',
            ),
        );
    }

    /**
     * Purge an anchor: expire the browser cookie + remove all stored data.
     *
     * @return array Expiry Set-Cookie spec for the response.
     */
    public static function purge($anchor_id) {
        $store = Zen_Cookie_Keeper_Store::instance();
        $store->insert_audit($anchor_id, 'anchor_purge');
        $store->purge_anchor($anchor_id);
        return Zen_Cookie_Keeper_Emitter::expire_spec(ZEN_COOKIE_KEEPER_ANCHOR_NAME, true);
    }

    /**
     * 32 random bytes, URL-safe base64 without padding (43 chars).
     */
    private static function random_token() {
        $bytes = random_bytes(32);
        return rtrim(strtr(base64_encode($bytes), '+/', 'AB'), '=');
    }
}
