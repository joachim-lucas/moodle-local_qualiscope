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
 * Documentation consistency tests for local_qualiscope.
 *
 * The Requirements section of the README is the only place where the supported
 * Moodle release is written out in words, so it drifts from version.php as soon
 * as one of the two is edited on its own. The README claimed Moodle 5.2 or
 * later while version.php required 4.5, which told administrators on 4.5 to 5.1
 * that the plugin did not support their site.
 *
 * @package    local_qualiscope
 * @category   test
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qualiscope\tests;

/**
 * Documentation consistency testcase.
 *
 * @package local_qualiscope
 */
final class readme_test extends \advanced_testcase {
    /**
     * Moodle branch version numbers, and the PHP minimum of that branch.
     *
     * Bumping $plugin->requires means adding the release here, otherwise
     * test_version_floor_is_the_one_the_readme_documents() fails on purpose
     * rather than silently accepting an unknown release.
     *
     * @var array<string, array{int, string}>
     */
    const MOODLE_RELEASES = [
        '4.5' => [2024100700, '8.1'],
        '5.2' => [2026042000, '8.1'],
    ];

    /**
     * Returns the absolute path of a file shipped with the plugin.
     *
     * @param string $relative Path relative to the plugin directory.
     * @return string The absolute path.
     */
    protected function plugin_filepath(string $relative): string {
        global $CFG;

        return $CFG->dirroot . '/local/qualiscope/' . $relative;
    }

    /**
     * Returns the declared Moodle floor, without loading version.php.
     *
     * @return int The value of $plugin->requires.
     */
    protected function declared_moodle_floor(): int {
        $source = file_get_contents($this->plugin_filepath('version.php'));
        $this->assertNotFalse($source);
        $this->assertSame(
            1,
            preg_match('/\$plugin->requires\s*=\s*(\d+)/', $source, $matches),
            'version.php must declare $plugin->requires.'
        );

        return (int) $matches[1];
    }

    /**
     * Returns the Requirements section of the README.
     *
     * @return string The lines of the section.
     */
    protected function readme_requirements(): string {
        $readme = file_get_contents($this->plugin_filepath('README.md'));
        $this->assertNotFalse($readme);
        $this->assertSame(
            1,
            preg_match('/^##\s*Requirements\s*$(.*?)(?=^##\s|\z)/ms', $readme, $matches),
            'README.md must keep a Requirements section.'
        );

        return $matches[1];
    }

    /**
     * Test the Requirements section documents the Moodle release version.php requires.
     *
     * @coversNothing
     * @return void
     */
    public function test_version_floor_is_the_one_the_readme_documents(): void {
        $floor = $this->declared_moodle_floor();
        $releases = array_keys(self::MOODLE_RELEASES);
        $documented = [];

        foreach (self::MOODLE_RELEASES as $release => [$version]) {
            if ($version === $floor) {
                $documented[] = $release;
            }
        }

        $this->assertNotEmpty(
            $documented,
            "version.php now requires {$floor}, which is unknown to this test. Add it to "
                . 'MOODLE_RELEASES (' . implode(', ', $releases) . ') and fix README.md.'
        );

        $requirements = $this->readme_requirements();
        foreach ($documented as $release) {
            $this->assertMatchesRegularExpression(
                '/Moodle\s+' . preg_quote($release, '/') . '\b/',
                $requirements,
                "README.md should document Moodle {$release}, the release version.php requires."
            );
        }
    }

    /**
     * Test the Requirements section does not raise the floor above version.php.
     *
     * This is the regression itself: the README asked for "Moodle 5.2 or later"
     * while version.php required 4.5.
     *
     * @coversNothing
     * @return void
     */
    public function test_readme_does_not_require_a_newer_moodle_than_version_php(): void {
        $floor = $this->declared_moodle_floor();
        $requirements = $this->readme_requirements();

        $this->assertSame(
            1,
            preg_match('/Moodle\s+(\d+\.\d+)\s+or\s+later/', $requirements, $matches),
            'README.md should state the minimum Moodle release as "Moodle X.Y or later".'
        );

        $claimed = $matches[1];
        $this->assertArrayHasKey(
            $claimed,
            self::MOODLE_RELEASES,
            "README.md claims Moodle {$claimed}, which is unknown to this test. Add it to MOODLE_RELEASES."
        );

        $this->assertGreaterThanOrEqual(
            self::MOODLE_RELEASES[$claimed][0],
            $floor,
            "README.md asks for Moodle {$claimed} or later, but version.php only requires {$floor}. "
                . 'Administrators on an older supported release would be told the plugin does not support their site.'
        );
    }

    /**
     * Test the documented PHP minimum is the one of the oldest supported Moodle.
     *
     * Moodle 4.5 runs on PHP 8.1 and Moodle 5.0 raised the minimum to 8.2. Asking
     * for 8.2 wrongly excluded the Moodle 4.5 installations running 8.1.
     *
     * @coversNothing
     * @return void
     */
    public function test_documented_php_minimum_matches_the_oldest_supported_moodle(): void {
        $floor = $this->declared_moodle_floor();
        $requirements = $this->readme_requirements();

        $this->assertSame(
            1,
            preg_match('/PHP\s+(\d+\.\d+)\+/', $requirements, $matches),
            'README.md should state the minimum PHP as "PHP X.Y+".'
        );

        $minimum = $matches[1];
        foreach (self::MOODLE_RELEASES as $release => [$version, $php]) {
            if ($version !== $floor) {
                continue;
            }
            $this->assertSame(
                $php,
                $minimum,
                "Moodle {$release} needs PHP {$php}, so README.md should not ask for a higher one. "
                    . 'The plugin adds no PHP requirement of its own.'
            );
        }
    }

    /**
     * Test the plugin really does declare no third party dependency.
     *
     * README.md promises no third party library is required, which is only true
     * while no composer manifest ships with the plugin.
     *
     * @coversNothing
     * @return void
     */
    public function test_no_third_party_dependency_is_shipped(): void {
        foreach (['composer.json', 'composer.lock', 'vendor'] as $unexpected) {
            $this->assertFileDoesNotExist(
                $this->plugin_filepath($unexpected),
                "README.md promises no third party library, but {$unexpected} is present."
            );
        }
    }
}
