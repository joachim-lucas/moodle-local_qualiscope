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
 * License tests for local_qualiscope.
 *
 * @package    local_qualiscope
 * @category   test
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qualiscope\tests;

use local_qualiscope\license;

/**
 * Licence key testcase.
 *
 * @package local_qualiscope
 */
final class license_test extends \advanced_testcase {
    /**
     * Secret key used by the tests to sign payloads.
     *
     * @var string
     */
    private string $secretkey;

    /**
     * Public key used by the tests to verify payloads.
     *
     * @var string
     */
    private string $publickey;

    /**
     * Set up test keypair, overrides the plugin public key for validation.
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $keypair = sodium_crypto_sign_keypair();
        $this->secretkey = sodium_crypto_sign_secretkey($keypair);
        $this->publickey = sodium_crypto_sign_publickey($keypair);
        set_config('licensepublickey', bin2hex($this->publickey), 'local_qualiscope');
    }

    /**
     * Build a signed licence key for the given values.
     *
     * @param string $sitehash The site hash the key is bound to.
     * @param int    $expiry   Expiry timestamp.
     * @param int    $issue    Issue timestamp.
     * @return string The licence key.
     */
    private function makekey(string $sitehash, int $expiry, int $issue = 0): string {
        $issue = $issue ?: time();
        $payload = json_encode([
            'v' => 1,
            'site' => $sitehash,
            'issue' => $issue,
            'expiry' => $expiry,
        ], JSON_UNESCAPED_SLASHES);
        $sig = sodium_crypto_sign_detached($payload, $this->secretkey);
        return license::b64u_encode($payload) . '.' . license::b64u_encode($sig);
    }

    /**
     * Test that an unexpired key bound to the current site is accepted.
     *
     * @covers \local_qualiscope\license::is_licensed
     * @covers \local_qualiscope\license::validate
     * @return void
     */
    public function test_valid_key(): void {
        set_config(license::CONFIG_KEY, $this->makekey(license::site_hash(), time() + YEARSECS), 'local_qualiscope');

        $this->assertTrue(license::is_licensed());
        $this->assertSame('ok', license::validate(license::stored_key())['code']);
    }

    /**
     * Test that a licence valid for exactly one year is accepted, and refused
     * as soon as its one-year expiry has passed.
     *
     * @covers \local_qualiscope\license::validate
     * @return void
     */
    public function test_one_year_expiry_boundary(): void {
        $sitehash = license::site_hash();

        // A licence valid for exactly one year is accepted.
        $onepyear = time() + YEARSECS;
        set_config(license::CONFIG_KEY, $this->makekey($sitehash, $onepyear), 'local_qualiscope');
        $this->assertTrue(license::is_licensed());

        // At the expiry instant the licence is already refused (no grace).
        set_config(license::CONFIG_KEY, $this->makekey($sitehash, time()), 'local_qualiscope');
        $this->assertFalse(license::is_licensed());
        $this->assertSame('expired', license::validate(license::stored_key())['code']);
    }

    /**
     * Test that an expired key is refused even with a valid signature.
     *
     * @covers \local_qualiscope\license::validate
     * @return void
     */
    public function test_expired_key(): void {
        set_config(license::CONFIG_KEY, $this->makekey(license::site_hash(), time() - 1), 'local_qualiscope');

        $result = license::validate(license::stored_key());
        $this->assertFalse($result['valid']);
        $this->assertSame('expired', $result['code']);
        $this->assertFalse(license::is_licensed());
    }

    /**
     * Test that a key valid on another site is refused.
     *
     * @covers \local_qualiscope\license::validate
     * @return void
     */
    public function test_wrong_site(): void {
        set_config(license::CONFIG_KEY, $this->makekey('not-this-site', time() + YEARSECS), 'local_qualiscope');

        $result = license::validate(license::stored_key());
        $this->assertFalse($result['valid']);
        $this->assertSame('wrong_site', $result['code']);
    }

    /**
     * Test that a tampered payload is refused (signature mismatch).
     *
     * @covers \local_qualiscope\license::validate
     * @return void
     */
    public function test_tampered_key(): void {
        $key = $this->makekey(license::site_hash(), time() + YEARSECS);
        $parts = explode('.', $key);

        // Bump the expiry inside the (signed) payload and keep the old signature.
        $payload = license::b64u_decode($parts[0]);
        $data = json_decode((string) $payload, true);
        $data['expiry'] = time() + YEARSECS * 10;
        $parts[0] = license::b64u_encode(json_encode($data, JSON_UNESCAPED_SLASHES));
        set_config(license::CONFIG_KEY, implode('.', $parts), 'local_qualiscope');

        $result = license::validate(license::stored_key());
        $this->assertFalse($result['valid']);
        $this->assertSame('bad_signature', $result['code']);
    }

    /**
     * Test that gibberish is refused as malformed.
     *
     * @covers \local_qualiscope\license::validate
     * @return void
     */
    public function test_malformed_key(): void {
        set_config(license::CONFIG_KEY, 'not-a-license', 'local_qualiscope');

        $result = license::validate(license::stored_key());
        $this->assertFalse($result['valid']);
        $this->assertSame('malformed', $result['code']);
        $this->assertFalse(license::is_licensed());
    }

    /**
     * Test that an empty stored key means unlicensed.
     *
     * @covers \local_qualiscope\license::is_licensed
     * @return void
     */
    public function test_no_key(): void {
        set_config(license::CONFIG_KEY, '', 'local_qualiscope');

        $this->assertFalse(license::is_licensed());
        $this->assertNull(license::expiry());
    }

    /**
     * Test the site hash is deterministic and depends on wwwroot and identifier.
     *
     * @covers \local_qualiscope\license::site_hash
     * @return void
     */
    public function test_site_hash(): void {
        global $CFG;

        $expected = hash('sha256', (string) $CFG->wwwroot . (string) $CFG->siteidentifier);
        $this->assertSame($expected, license::site_hash());
        $this->assertSame(64, strlen(license::site_hash()));
    }
}
