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
 * Settings page tests for local_qualiscope.
 *
 * @package    local_qualiscope
 * @category   test
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qualiscope\tests;

/**
 * Settings page testcase.
 *
 * @package local_qualiscope
 */
final class settings_test extends \advanced_testcase {
    /**
     * Set up.
     *
     * @return void
     */
    public function setUp(): void {
        global $CFG;

        parent::setUp();
        $this->resetAfterTest(true);

        // Adminlib reads $CFG as a bare variable in its top level code, so
        // it has to be included with $CFG in scope.
        require_once($CFG->libdir . '/adminlib.php');
    }

    /**
     * Rebuild the admin tree for the current user.
     *
     * @return \admin_root
     */
    private function rebuild_admin_tree(): \admin_root {
        global $ADMIN;

        // The tree is cached in the $ADMIN global, so drop it to force a rebuild.
        $ADMIN = null;

        return \admin_get_root(true, true);
    }

    /**
     * Test the settings page is registered when the user can manage site config.
     *
     * @coversNothing
     * @return void
     */
    public function test_settings_page_registered_for_site_admin(): void {
        $this->setAdminUser();

        $root = $this->rebuild_admin_tree();

        $this->assertNotEmpty($root->locate('local_qualiscope_settings', true));
        $this->assertNotEmpty($root->locate('local_qualiscope', true));
    }

    /**
     * Test the settings page is skipped for users without site config.
     *
     * The licence validation and the referentials query in settings.php are
     * pure overhead for everyone else, since the admin tree is rebuilt on
     * every page load.
     *
     * @coversNothing
     * @return void
     */
    public function test_settings_page_skipped_without_site_config(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $root = $this->rebuild_admin_tree();

        $this->assertEmpty($root->locate('local_qualiscope_settings', true));
        $this->assertEmpty($root->locate('local_qualiscope', true));
    }
}
