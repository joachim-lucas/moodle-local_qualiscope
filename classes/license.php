<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * QualiScope License class.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_qualiscope;


/**
 * Handles offline (signed) licence keys for QualiScope.
 *
 * A licence key is a base64url payload plus its detached signature:
 *     payload = {"v":1, "site":"<sitehash>", "issue":<ts>, "expiry":<ts>}
 *     key     = base64url(json) . '.' . base64url(sodium_signature)
 *
 * Verification is fully offline: the public key is embedded, the key is
 * bound to the current site (wwwroot + siteidentifier hash) and expires
 * at the payload "expiry" timestamp. Any tampering breaks the signature.
 *
 * @package local_qualiscope
 */
class license {
    /**
     * Public key (hex) used to verify licence signatures.
     *
     * @var string
     */
    public const PUBLIC_KEY = '7c6a299cf036bc0ff1fe8f4c8ff84ab7bf118518a77766e74dce73e8bd0a2891';

    /**
     * Payload format version currently accepted.
     */
    public const PAYLOAD_VERSION = 1;

    /**
     * Grace period in seconds granted after expiry before the licence stops
     * being honoured. Set to 0 for a hard expiry (key valid one year, no more).
     *
     * @var int
     */
    public const GRACE_SECONDS = 0;

    /**
     * Config key holding the licence key.
     *
     * @var string
     */
    public const CONFIG_KEY = 'licensekey';

    /**
     * Base64url encode.
     *
     * @param string $data Binary data.
     * @return string
     */
    public static function b64u_encode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64url decode.
     *
     * @param string $data Encoded string.
     * @return string|false The raw bytes or false on invalid input.
     */
    public static function b64u_decode(string $data) {
        $data = strtr($data, '-_', '+/');
        $pad = strlen($data) % 4;
        if ($pad) {
            $data .= str_repeat('=', 4 - $pad);
        }
        return base64_decode($data, true);
    }

    /**
     * SHA-256 identifier of the current site, used to bind licences.
     *
     * @return string The site hash (64 hex chars).
     */
    public static function site_hash(): string {
        global $CFG;
        return hash('sha256', (string) $CFG->wwwroot . (string) $CFG->siteidentifier);
    }

    /**
     * Verification public key, as raw bytes.
     *
     * @return string|null The public key bytes, or null when unavailable.
     */
    public static function public_key(): ?string {
        $hex = self::PUBLIC_KEY;
        $override = get_config('local_qualiscope', 'licensepublickey');
        if ($override) {
            $hex = $override;
        }
        $raw = hex2bin((string) $hex);
        return $raw === false ? null : $raw;
    }

    /**
     * Validate a licence key against the current site.
     *
     * @param string      $key       The licence key.
     * @param string|null $sitehash  Optional site hash; defaults to the current site.
     * @param string|null $publickey Optional public key bytes; defaults to the embedded key.
     * @return array Associative array with 'valid', 'code' and 'expiry'.
     */
    public static function validate(string $key, ?string $sitehash = null, ?string $publickey = null): array {
        $key = trim($key);
        if ($key === '') {
            return ['valid' => false, 'code' => 'missing', 'expiry' => null];
        }

        $parsed = self::parse_key($key);
        if ($parsed === null) {
            return ['valid' => false, 'code' => 'malformed', 'expiry' => null];
        }

        $pubkey = $publickey ?? self::public_key();
        if ($pubkey === null || !sodium_crypto_sign_verify_detached($parsed['sig'], $parsed['payload'], $pubkey)) {
            return ['valid' => false, 'code' => 'bad_signature', 'expiry' => null];
        }

        return self::check_payload($parsed['data'], $sitehash);
    }

    /**
     * Split, decode and interpret a licence key payload.
     *
     * @param string $key The licence key.
     * @return array|null Array with 'payload', 'sig' and 'data', or null when malformed.
     */
    private static function parse_key(string $key): ?array {
        $parts = explode('.', $key);
        if (count($parts) !== 2) {
            return null;
        }

        $payload = self::b64u_decode($parts[0]);
        $sig = self::b64u_decode($parts[1]);
        if ($payload === false || $sig === false) {
            return null;
        }

        $data = json_decode($payload, true);
        if (!is_array($data) || ($data['v'] ?? null) !== self::PAYLOAD_VERSION) {
            return null;
        }

        return ['payload' => $payload, 'sig' => $sig, 'data' => $data];
    }

    /**
     * Check a signed payload against the site binding and the expiry deadline.
     *
     * @param array       $data     The decoded payload.
     * @param string|null $sitehash Site hash; defaults to the current site.
     * @return array Associative array with 'valid', 'code' and 'expiry'.
     */
    private static function check_payload(array $data, ?string $sitehash): array {
        $sitehash = $sitehash ?? self::site_hash();
        if (!isset($data['site']) || $data['site'] !== $sitehash) {
            return ['valid' => false, 'code' => 'wrong_site', 'expiry' => null];
        }

        $expiry = (int) ($data['expiry'] ?? 0);
        if ($expiry + self::GRACE_SECONDS <= time()) {
            return ['valid' => false, 'code' => 'expired', 'expiry' => $expiry];
        }

        return ['valid' => true, 'code' => 'ok', 'expiry' => $expiry];
    }

    /**
     * The licence key currently stored in the plugin configuration.
     *
     * @return string The stored key.
     */
    public static function stored_key(): string {
        return (string) get_config('local_qualiscope', self::CONFIG_KEY);
    }

    /**
     * Whether the site has a valid, unexpired licence.
     *
     * @return bool True when licensed.
     */
    public static function is_licensed(): bool {
        return self::validate(self::stored_key())['valid'];
    }

    /**
     * Expiry timestamp of the current licence, if valid.
     *
     * @return int|null The expiry timestamp or null when unlicensed.
     */
    public static function expiry(): ?int {
        $result = self::validate(self::stored_key());
        return $result['valid'] ? $result['expiry'] : null;
    }
}
