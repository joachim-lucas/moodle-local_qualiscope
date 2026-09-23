<?php

// Standalone test script for accessibility_analyser logic

if (!defined('MOODLE_INTERNAL')) {
    define('MOODLE_INTERNAL', true);
}

// Mock get_string and s functions if not in full moodle runtime
if (!function_exists('get_string')) {
    /**
     * Minimal get_string mock for standalone execution.
     *
     * @param string $identifier The string identifier.
     * @param string $component The component.
     * @param mixed $a Optional placeholder value.
     * @return string
     */
    function get_string($identifier, $component = '', $a = null) {
        if (is_array($a) || is_object($a)) {
            $a = (array) $a;
            $res = $identifier;
            foreach ($a as $k => $v) {
                if (is_scalar($v)) {
                    $res .= " [$k=$v]";
                }
            }
            return $res;
        }
        return $identifier . ($a !== null ? " ($a)" : "");
    }
}

if (!function_exists('s')) {
    /**
     * Minimal output escaping mock for standalone execution.
     *
     * @param mixed $var Value to escape.
     * @return string
     */
    function s($var) {
        return htmlspecialchars((string)$var, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

require_once __DIR__ . '/../classes/analyser/accessibility_analyser.php';

use local_qualiscope\analyser\accessibility_analyser;

/**
 * Asserts a condition and exits with an error when it fails.
 *
 * @param bool $cond The condition to check.
 * @param string $msg The message to display.
 * @return void
 */
function assert_true($cond, $msg) {
    if (!$cond) {
        echo "FAIL: $msg\n";
        exit(1);
    }
    echo "PASS: $msg\n";
}

echo "=== Running Accessibility Analyser Tests ===\n\n";

// 1. Test Color Contrast
echo "--- 1. Testing Color Parsing & Contrast Ratio ---\n";
$black = accessibility_analyser::parse_css_color('#000000');
$white = accessibility_analyser::parse_css_color('#ffffff');
$ratio_bw = accessibility_analyser::calculate_contrast_ratio($black, $white);
assert_true($ratio_bw == 21.0, "Black on White contrast ratio is 21.0 (got $ratio_bw)");

$lightgray = accessibility_analyser::parse_css_color('#cccccc');
$ratio_gw = accessibility_analyser::calculate_contrast_ratio($lightgray, $white);
assert_true($ratio_gw < 4.5, "Light gray #cccccc on White fails WCAG AA (got $ratio_gw < 4.5)");

$darkblue = accessibility_analyser::parse_css_color('darkblue');
$ratio_db = accessibility_analyser::calculate_contrast_ratio($darkblue, $white);
assert_true($ratio_db >= 4.5, "Dark blue on white passes WCAG AA (got $ratio_db >= 4.5)");

// Test analyse_contrast with HTML
$sample_contrast_html = [
    ['title' => 'Sample 1', 'html' => '<p style="color: #333333; background-color: #ffffff;">Accessible text</p><span style="color: #dddddd;">Inaccessible text</span>']
];
$contrast_res = accessibility_analyser::analyse_contrast($sample_contrast_html);
assert_true($contrast_res['total'] === 2, "Found 2 colored elements");
assert_true($contrast_res['compliant'] === 1, "1 compliant color style");
assert_true($contrast_res['failed'] === 1, "1 failed color style");
assert_true($contrast_res['status'] === 'verify', "Status is verify when partial (got {$contrast_res['status']})");

// 2. Test Image Alt Attributes
echo "\n--- 2. Testing Image Alt Attributes ---\n";
$sample_images_html = [
    [
        'title' => 'Page 1',
        'html' => '
            <img src="pic1.png" alt="Schéma explicatif du cycle de l\'eau" />
            <img src="icon.svg" alt="" />
            <img src="photo.jpg" alt="photo.jpg" />
            <img src="banner.jpg" alt="image" />
            <img src="missing.png" />
        '
    ]
];
$images_res = accessibility_analyser::analyse_images($sample_images_html);
assert_true($images_res['total'] === 5, "Total images is 5 (got {$images_res['total']})");
assert_true($images_res['pertinent'] === 1, "Pertinent alt count is 1 (got {$images_res['pertinent']})");
assert_true($images_res['decorative'] === 1, "Decorative alt count is 1 (got {$images_res['decorative']})");
assert_true($images_res['missing'] === 1, "Missing alt count is 1 (got {$images_res['missing']})");
assert_true($images_res['non_pertinent'] === 2, "Non-pertinent alt count is 2 (got {$images_res['non_pertinent']})");
assert_true($images_res['ratio'] === 0.4, "Ratio is 2/5 = 0.4 (got {$images_res['ratio']})");

// 3. Test Video Captions
echo "\n--- 3. Testing Video Subtitles & Tracks ---\n";
$sample_videos_html = [
    [
        'title' => 'Section Video',
        'html' => '
            <video src="video.mp4">
                <track kind="subtitles" src="subs.vtt" srclang="fr" label="Français" />
            </video>
            <video src="novtt.mp4">
            </video>
            <iframe src="https://www.youtube.com/embed/xyz123?cc_load_policy=1"></iframe>
        '
    ]
];
$videos_res = accessibility_analyser::analyse_videos(0, $sample_videos_html);
assert_true($videos_res['total'] === 3, "Total videos is 3 (got {$videos_res['total']})");
assert_true($videos_res['subtitled'] === 2, "Subtitled videos is 2 (got {$videos_res['subtitled']})");
assert_true($videos_res['ratio'] === 0.67, "Ratio is 2/3 = 0.67 (got {$videos_res['ratio']})");

// 4. Test Heading Hierarchy
echo "\n--- 4. Testing Heading Hierarchy (h1-h6) ---\n";
$sample_headings_perfect = [
    [
        'title' => 'Page Good',
        'html' => '<h1>Titre 1</h1><p>Text</p><h2>Titre 2</h2><h3>Titre 3</h3><h2>Autre titre 2</h2>'
    ]
];
$headings_res1 = accessibility_analyser::analyse_headings($sample_headings_perfect);
assert_true($headings_res1['total'] === 4, "Total headings is 4");
assert_true($headings_res1['skips'] === 0, "No skips in valid hierarchy");
assert_true($headings_res1['status'] === 'detected', "Status is detected for perfect headings");

$sample_headings_skip = [
    [
        'title' => 'Page Skip',
        'html' => '<h1>Titre principal</h1><h4>Sous titre saute</h4><h2>Titre 2</h2><h3></h3>'
    ]
];
$headings_res2 = accessibility_analyser::analyse_headings($sample_headings_skip);
assert_true($headings_res2['total'] === 4, "Total headings is 4");
assert_true($headings_res2['skips'] === 1, "1 skip detected (H1 -> H4)");
assert_true($headings_res2['empty'] === 1, "1 empty heading detected (<h3></h3>)");

echo "\nAll accessibility analyser tests passed successfully!\n";
