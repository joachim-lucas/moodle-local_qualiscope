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
 * Tests for the QualiScope accessibility analyser.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qualiscope\tests;

use advanced_testcase;
use local_qualiscope\analyser\accessibility_analyser;

/**
 * Tests for accessibility_analyser static analysis methods.
 *
 * @package    local_qualiscope
 */
final class accessibility_analyser_test extends advanced_testcase {
    /**
     * Test CSS color parsing and contrast ratio calculation.
     *
     * @covers \local_qualiscope\analyser\accessibility_analyser::parse_css_color
     * @covers \local_qualiscope\analyser\accessibility_analyser::calculate_contrast_ratio
     * @return void
     */
    public function test_contrast_ratio(): void {
        $black = accessibility_analyser::parse_css_color('#000000');
        $white = accessibility_analyser::parse_css_color('#ffffff');
        $this->assertEquals(21.0, accessibility_analyser::calculate_contrast_ratio($black, $white), 0.01);

        $lightgray = accessibility_analyser::parse_css_color('#cccccc');
        $this->assertLessThan(
            4.5,
            accessibility_analyser::calculate_contrast_ratio($lightgray, $white)
        );

        $darkblue = accessibility_analyser::parse_css_color('darkblue');
        $this->assertGreaterThanOrEqual(
            4.5,
            accessibility_analyser::calculate_contrast_ratio($darkblue, $white)
        );
    }

    /**
     * Test analyse_contrast detection of failing color styles.
     *
     * @covers \local_qualiscope\analyser\accessibility_analyser::analyse_contrast
     * @return void
     */
    public function test_analyse_contrast(): void {
        $sample = [
            [
                'title' => 'Sample 1',
                'html' => '<p style="color: #333333; background-color: #ffffff;">Accessible text</p>' .
                    '<span style="color: #dddddd;">Inaccessible text</span>',
            ],
        ];
        $result = accessibility_analyser::analyse_contrast($sample);
        $this->assertEquals(2, $result['total']);
        $this->assertEquals(1, $result['compliant']);
        $this->assertEquals(1, $result['failed']);
        $this->assertEquals('verify', $result['status']);
    }

    /**
     * Test analyse_images alt attribute classification.
     *
     * @covers \local_qualiscope\analyser\accessibility_analyser::analyse_images
     * @return void
     */
    public function test_analyse_images(): void {
        $sample = [
            [
                'title' => 'Page 1',
                'html' => '
                    <img src="pic1.png" alt="Schéma explicatif du cycle de l\'eau" />
                    <img src="icon.svg" alt="" />
                    <img src="photo.jpg" alt="photo.jpg" />
                    <img src="banner.jpg" alt="image" />
                    <img src="missing.png" />
                ',
            ],
        ];
        $result = accessibility_analyser::analyse_images($sample);
        $this->assertEquals(5, $result['total']);
        $this->assertEquals(1, $result['pertinent']);
        $this->assertEquals(1, $result['decorative']);
        $this->assertEquals(1, $result['missing']);
        $this->assertEquals(2, $result['non_pertinent']);
        $this->assertEquals(0.4, $result['ratio']);
    }

    /**
     * Test analyse_videos subtitle and track detection.
     *
     * @covers \local_qualiscope\analyser\accessibility_analyser::analyse_videos
     * @return void
     */
    public function test_analyse_videos(): void {
        $sample = [
            [
                'title' => 'Section Video',
                'html' => '
                    <video src="video.mp4">
                        <track kind="subtitles" src="subs.vtt" srclang="fr" label="Français" />
                    </video>
                    <video src="novtt.mp4">
                    </video>
                    <iframe src="https://www.youtube.com/embed/xyz123?cc_load_policy=1"></iframe>
                ',
            ],
        ];
        $result = accessibility_analyser::analyse_videos(0, $sample);
        $this->assertEquals(3, $result['total']);
        $this->assertEquals(2, $result['subtitled']);
        $this->assertEquals(0.67, $result['ratio']);
    }

    /**
     * Test analyse_headings hierarchy detection.
     *
     * @covers \local_qualiscope\analyser\accessibility_analyser::analyse_headings
     * @return void
     */
    public function test_analyse_headings(): void {
        $perfect = [
            [
                'title' => 'Page Good',
                'html' => '<h1>Titre 1</h1><p>Text</p><h2>Titre 2</h2><h3>Titre 3</h3><h2>Autre titre 2</h2>',
            ],
        ];
        $result = accessibility_analyser::analyse_headings($perfect);
        $this->assertEquals(4, $result['total']);
        $this->assertEquals(0, $result['skips']);
        $this->assertEquals('detected', $result['status']);

        $skip = [
            [
                'title' => 'Page Skip',
                'html' => '<h1>Titre principal</h1><h4>Sous titre saute</h4><h2>Titre 2</h2><h3></h3>',
            ],
        ];
        $result = accessibility_analyser::analyse_headings($skip);
        $this->assertEquals(4, $result['total']);
        $this->assertEquals(1, $result['skips']);
        $this->assertEquals(1, $result['empty']);
    }
}
