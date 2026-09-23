<?php

namespace local_qualiscope\analyser;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper class for automated WCAG 2.1 / RGAA accessibility checks.
 *
 * Covers:
 * 1. Image alt attributes presence and pertinence.
 * 2. Video captions and subtitles (.vtt, tracks, transcripts).
 * 3. HTML heading structure hierarchy (<h1> to <h6>) without skipping levels.
 * 4. Color contrast ratio in rich-text contents (WCAG AA >= 4.5:1).
 */
class accessibility_analyser {

    /**
     * Standard color map for CSS named colors.
     */
    private static $namedcolors = [
        'black' => [0, 0, 0],
        'white' => [255, 255, 255],
        'red' => [255, 0, 0],
        'lime' => [0, 255, 0],
        'blue' => [0, 0, 255],
        'yellow' => [255, 255, 0],
        'cyan' => [0, 255, 255],
        'aqua' => [0, 255, 255],
        'magenta' => [255, 0, 255],
        'fuchsia' => [255, 0, 255],
        'silver' => [192, 192, 192],
        'gray' => [128, 128, 128],
        'grey' => [128, 128, 128],
        'maroon' => [128, 0, 0],
        'olive' => [128, 128, 0],
        'green' => [0, 128, 0],
        'purple' => [128, 0, 128],
        'teal' => [0, 128, 128],
        'navy' => [0, 0, 128],
        'orange' => [255, 165, 0],
        'darkgray' => [169, 169, 169],
        'darkgrey' => [169, 169, 169],
        'lightgray' => [211, 211, 211],
        'lightgrey' => [211, 211, 211],
        'darkblue' => [0, 0, 139],
        'darkgreen' => [0, 100, 0],
        'darkred' => [139, 0, 0],
        'gold' => [255, 215, 0],
    ];

    /**
     * Extract rich-text HTML contents from course summary, sections, pages, labels and activities.
     *
     * @param int $courseid
     * @return array List of content items with keys: source, title, html
     */
    public static function get_course_rich_text_contents(int $courseid): array {
        global $DB;

        $contents = [];

        // 1. Course summary
        $course = $DB->get_record('course', ['id' => $courseid]);
        if ($course && !empty(trim(strip_tags($course->summary)))) {
            $contents[] = [
                'source' => 'course_summary',
                'title' => get_string('course') . ' (' . $course->fullname . ')',
                'html' => $course->summary,
            ];
        }

        // 2. Section summaries
        $sections = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC');
        foreach ($sections as $section) {
            if (!empty(trim(strip_tags($section->summary)))) {
                $secname = !empty($section->name) ? $section->name : ('Section ' . $section->section);
                $contents[] = [
                    'source' => 'section_summary',
                    'title' => $secname,
                    'html' => $section->summary,
                ];
            }
        }

        // 3. Mod Page
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('page')) {
            $pages = $DB->get_records('page', ['course' => $courseid]);
            foreach ($pages as $p) {
                if (!empty($p->content)) {
                    $contents[] = [
                        'source' => 'page',
                        'title' => 'Page : ' . $p->name,
                        'html' => $p->content,
                    ];
                }
                if (!empty($p->intro) && trim(strip_tags($p->intro)) !== '') {
                    $contents[] = [
                        'source' => 'page_intro',
                        'title' => 'Page intro : ' . $p->name,
                        'html' => $p->intro,
                    ];
                }
            }
        }

        // 4. Mod Label
        if ($dbman->table_exists('label')) {
            $labels = $DB->get_records('label', ['course' => $courseid]);
            foreach ($labels as $l) {
                if (!empty($l->intro)) {
                    $contents[] = [
                        'source' => 'label',
                        'title' => 'Zone de texte : ' . ($l->name ?: 'Label'),
                        'html' => $l->intro,
                    ];
                }
            }
        }

        // 5. Mod Book chapters
        if ($dbman->table_exists('book') && $dbman->table_exists('book_chapters')) {
            $books = $DB->get_records('book', ['course' => $courseid]);
            foreach ($books as $b) {
                $chapters = $DB->get_records('book_chapters', ['bookid' => $b->id]);
                foreach ($chapters as $ch) {
                    if (!empty($ch->content)) {
                        $contents[] = [
                            'source' => 'book_chapter',
                            'title' => 'Livre : ' . $b->name . ' - ' . $ch->title,
                            'html' => $ch->content,
                        ];
                    }
                }
            }
        }

        // 6. Other standard activities intros
        $standardmods = ['assign', 'quiz', 'forum', 'resource', 'url', 'lesson', 'feedback', 'choice', 'glossary', 'h5pactivity'];
        foreach ($standardmods as $modname) {
            if ($dbman->table_exists($modname)) {
                $records = $DB->get_records($modname, ['course' => $courseid]);
                foreach ($records as $r) {
                    if (!empty($r->intro) && trim(strip_tags($r->intro)) !== '') {
                        $contents[] = [
                            'source' => $modname,
                            'title' => ucfirst($modname) . ' : ' . ($r->name ?? ''),
                            'html' => $r->intro,
                        ];
                    }
                }
            }
        }

        return $contents;
    }

    /**
     * Analyse image alt attributes across rich-text contents.
     *
     * @param array $contents
     * @return array
     */
    public static function analyse_images(array $contents): array {
        $totalimages = 0;
        $pertinent = 0;
        $decorative = 0;
        $missingalt = 0;
        $nonpertinent = 0;
        $details = [];

        // Regex pattern for image file names and generic non-descriptive placeholders
        $filenamepattern = '/\.(png|jpe?g|gif|webp|svg|bmp|tiff|ico)$/i';
        $genericpattern = '/^(image|photo|capture|screenshot|sans titre|untitled|dessin|visuel|figure|img|dsc|pic|picture|icon|icone)[_\s\-\d]*$/iu';
        $punctpattern = '/^[\s\.\-_*#\?\/\\:;!~+=]+$/u';

        foreach ($contents as $item) {
            $html = $item['html'] ?? '';
            if (!preg_match_all('/<img\b([^>]*)>/i', $html, $matches, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($matches as $match) {
                $totalimages++;
                $attrstring = $match[1];

                // Check for alt attribute
                if (preg_match('/\balt\s*=\s*(["\'])(.*?)\1/i', $attrstring, $altmatch)) {
                    $alttext = trim($altmatch[2]);
                    if ($alttext === '') {
                        // Empty alt="" is valid for decorative images according to WCAG
                        $decorative++;
                    } else if (preg_match($filenamepattern, $alttext) || preg_match($genericpattern, $alttext) || preg_match($punctpattern, $alttext) || mb_strlen($alttext) < 2) {
                        $nonpertinent++;
                        $details[] = get_string('wcag_image_alt_nonpertinent_item', 'local_qualiscope', [
                            'source' => $item['title'],
                            'alt' => s(mb_substr($alttext, 0, 30)),
                        ]);
                    } else {
                        $pertinent++;
                    }
                } else if (preg_match('/\balt\s*=\s*""|\balt\s*=\s*\'\'/i', $attrstring)) {
                    $decorative++;
                } else if (preg_match('/\brole\s*=\s*["\']presentation["\']|\baria-hidden\s*=\s*["\']true["\']/i', $attrstring)) {
                    $decorative++;
                } else {
                    $missingalt++;
                    $details[] = get_string('wcag_image_alt_missing_item', 'local_qualiscope', $item['title']);
                }
            }
        }

        if ($totalimages === 0) {
            return [
                'total' => 0,
                'pertinent' => 0,
                'decorative' => 0,
                'missing' => 0,
                'non_pertinent' => 0,
                'ratio' => 1.0,
                'status' => 'detected',
                'summary' => get_string('wcag_images_none_found', 'local_qualiscope'),
                'details' => $details,
            ];
        }

        $compliant = $pertinent + $decorative;
        $ratio = round($compliant / $totalimages, 2);

        if ($ratio >= 0.85) {
            $status = 'detected';
        } else if ($ratio >= 0.40) {
            $status = 'verify';
        } else {
            $status = 'missing';
        }

        $summary = get_string('wcag_images_summary', 'local_qualiscope', [
            'compliant' => $compliant,
            'total' => $totalimages,
            'pertinent' => $pertinent,
            'decorative' => $decorative,
            'missing' => $missingalt,
            'nonpertinent' => $nonpertinent,
        ]);

        return [
            'total' => $totalimages,
            'pertinent' => $pertinent,
            'decorative' => $decorative,
            'missing' => $missingalt,
            'non_pertinent' => $nonpertinent,
            'ratio' => $ratio,
            'status' => $status,
            'summary' => $summary,
            'details' => $details,
        ];
    }

    /**
     * Analyse video captions, tracks and subtitles across course.
     *
     * @param int $courseid
     * @param array $contents
     * @return array
     */
    public static function analyse_videos(int $courseid, array $contents): array {
        global $DB;

        $totalvideos = 0;
        $subtitledvideos = 0;
        $details = [];

        // 1. Inspect HTML <video> and <iframe> tags
        foreach ($contents as $item) {
            $html = $item['html'] ?? '';

            // Check <video> tags
            if (preg_match_all('/<video\b([^>]*)>(.*?)<\/video>/is', $html, $videomatches, PREG_SET_ORDER)) {
                foreach ($videomatches as $vm) {
                    $totalvideos++;
                    $videobody = $vm[2];
                    $hascaption = false;

                    if (preg_match('/<track\b[^>]*(kind=["\'](subtitles|captions)["\']|\.vtt|\.srt)/i', $videobody)) {
                        $hascaption = true;
                    }

                    if ($hascaption) {
                        $subtitledvideos++;
                    } else {
                        $details[] = get_string('wcag_video_notracks_item', 'local_qualiscope', $item['title']);
                    }
                }
            }

            // Check embedded iframes (e.g. YouTube, Vimeo, Peertube)
            if (preg_match_all('/<iframe\b([^>]*)>/i', $html, $iframematches, PREG_SET_ORDER)) {
                foreach ($iframematches as $im) {
                    $attrs = $im[1];
                    if (preg_match('/src=["\']([^"\']*(?:youtube|youtu\.be|vimeo|dailymotion|peertube|kaltura|panopto)[^"\']*)["\']/i', $attrs, $srcmatch)) {
                        $totalvideos++;
                        $url = $srcmatch[1];
                        $hascaption = false;

                        // Check URL params like cc_load_policy=1
                        if (stripos($url, 'cc_load_policy=1') !== false || stripos($url, 'subtitles') !== false) {
                            $hascaption = true;
                        }

                        // Check if surrounding content in the same block mentions transcripts / subtitles
                        if (!$hascaption && preg_match('/(sous-titre|transcription|transcript|vtt|audiodescription)/i', $html)) {
                            $hascaption = true;
                        }

                        if ($hascaption) {
                            $subtitledvideos++;
                        } else {
                            $details[] = get_string('wcag_video_embed_notracks_item', 'local_qualiscope', $item['title']);
                        }
                    }
                }
            }
        }

        // 2. Check course files for video and subtitle files in mdl_files
        if ($courseid > 0 && class_exists('\context_course') && isset($DB)) {
            try {
                $context = \context_course::instance($courseid);
                $videofiles = (int) $DB->count_records_sql(
                    "SELECT COUNT(f.id)
                     FROM {files} f
                     WHERE f.contextid = :contextid
                       AND f.filename <> '.'
                       AND (f.mimetype LIKE 'video/%' OR f.filename LIKE '%.mp4' OR f.filename LIKE '%.webm')",
                    ['contextid' => $context->id]
                );

                $subtitlefiles = (int) $DB->count_records_sql(
                    "SELECT COUNT(f.id)
                     FROM {files} f
                     WHERE f.contextid = :contextid
                       AND f.filename <> '.'
                       AND (f.filename LIKE '%.vtt' OR f.filename LIKE '%.srt' OR f.mimetype = 'text/vtt')",
                    ['contextid' => $context->id]
                );

                if ($videofiles > 0 && $totalvideos === 0) {
                    $totalvideos += $videofiles;
                    $subtitledvideos += min($videofiles, $subtitlefiles);
                } else if ($subtitlefiles > 0 && $subtitledvideos < $totalvideos) {
                    $subtitledvideos = min($totalvideos, $subtitledvideos + $subtitlefiles);
                }
            } catch (\Throwable $e) {
                // Ignore context lookup issues in edge cases or standalone runs.
            }
        }

        if ($totalvideos === 0) {
            return [
                'total' => 0,
                'subtitled' => 0,
                'ratio' => 1.0,
                'status' => 'detected',
                'summary' => get_string('wcag_videos_none_found', 'local_qualiscope'),
                'details' => [],
            ];
        }

        $ratio = round($subtitledvideos / $totalvideos, 2);
        if ($ratio >= 0.75) {
            $status = 'detected';
        } else if ($ratio >= 0.30) {
            $status = 'verify';
        } else {
            $status = 'missing';
        }

        $summary = get_string('wcag_videos_summary', 'local_qualiscope', [
            'subtitled' => $subtitledvideos,
            'total' => $totalvideos,
        ]);

        return [
            'total' => $totalvideos,
            'subtitled' => $subtitledvideos,
            'ratio' => $ratio,
            'status' => $status,
            'summary' => $summary,
            'details' => $details,
        ];
    }

    /**
     * Analyse heading hierarchy (<h1> to <h6>) without skipping levels.
     *
     * @param array $contents
     * @return array
     */
    public static function analyse_headings(array $contents): array {
        $totalheadings = 0;
        $emptyheadings = 0;
        $skips = 0;
        $details = [];

        foreach ($contents as $item) {
            $html = $item['html'] ?? '';
            if (!preg_match_all('/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', $html, $matches, PREG_SET_ORDER)) {
                continue;
            }

            $previouslevel = null;
            foreach ($matches as $match) {
                $totalheadings++;
                $level = (int) $match[1];
                $headingtext = trim(html_entity_decode(strip_tags($match[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $headingtext = preg_replace('/\s+/u', ' ', $headingtext);

                if ($headingtext === '' || $headingtext === '&nbsp;') {
                    $emptyheadings++;
                    $details[] = get_string('wcag_heading_empty_item', 'local_qualiscope', [
                        'level' => 'H' . $level,
                        'source' => $item['title'],
                    ]);
                    continue;
                }

                if ($previouslevel !== null) {
                    // Check if level skipped downwards (e.g. H1 to H3 skipping H2, H2 to H4 skipping H3)
                    if ($level > $previouslevel + 1) {
                        $skips++;
                        $details[] = get_string('wcag_heading_skip_item', 'local_qualiscope', [
                            'prev' => 'H' . $previouslevel,
                            'curr' => 'H' . $level,
                            'source' => $item['title'],
                        ]);
                    }
                }

                $previouslevel = $level;
            }
        }

        if ($totalheadings === 0) {
            return [
                'total' => 0,
                'empty' => 0,
                'skips' => 0,
                'ratio' => 0.60,
                'status' => 'verify',
                'summary' => get_string('wcag_headings_none_found', 'local_qualiscope'),
                'details' => [],
            ];
        }

        $issues = $emptyheadings + $skips;
        $ratio = max(0.0, min(1.0, round(1.0 - ($issues / max(1, $totalheadings)), 2)));

        if ($issues === 0) {
            $status = 'detected';
            $summary = get_string('wcag_headings_structured_perfect', 'local_qualiscope', $totalheadings);
        } else if ($ratio >= 0.60) {
            $status = 'verify';
            $summary = get_string('wcag_headings_structured_partial', 'local_qualiscope', [
                'total' => $totalheadings,
                'skips' => $skips,
                'empty' => $emptyheadings,
            ]);
        } else {
            $status = 'missing';
            $summary = get_string('wcag_headings_structured_flawed', 'local_qualiscope', [
                'total' => $totalheadings,
                'issues' => $issues,
            ]);
        }

        return [
            'total' => $totalheadings,
            'empty' => $emptyheadings,
            'skips' => $skips,
            'ratio' => $ratio,
            'status' => $status,
            'summary' => $summary,
            'details' => $details,
        ];
    }

    /**
     * Analyse color contrast in rich-text content according to WCAG 2.1 AA (minimum ratio 4.5:1).
     *
     * @param array $contents
     * @return array
     */
    public static function analyse_contrast(array $contents): array {
        $totalcolorstyles = 0;
        $compliantstyles = 0;
        $failedstyles = 0;
        $details = [];

        foreach ($contents as $item) {
            $html = $item['html'] ?? '';

            // 1. Inline styles: style="..."
            if (preg_match_all('/style\s*=\s*(["\'])(.*?)\1/is', $html, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $style = $m[2];
                    $fgcolor = null;
                    $bgcolor = [255, 255, 255]; // default background is white in standard Moodle themes

                    if (preg_match('/(?<![a-z\-])color\s*:\s*([^;]+)/i', $style, $cm)) {
                        $fgcolor = self::parse_css_color(trim($cm[1]));
                    }
                    if (preg_match('/background(?:-color)?\s*:\s*([^;]+)/i', $style, $bm)) {
                        $parsedbg = self::parse_css_color(trim($bm[1]));
                        if ($parsedbg !== null) {
                            $bgcolor = $parsedbg;
                        }
                    }

                    if ($fgcolor !== null) {
                        $totalcolorstyles++;
                        $ratio = self::calculate_contrast_ratio($fgcolor, $bgcolor);
                        if ($ratio >= 4.5) {
                            $compliantstyles++;
                        } else {
                            $failedstyles++;
                            $details[] = get_string('wcag_contrast_failed_item', 'local_qualiscope', [
                                'ratio' => number_format($ratio, 1),
                                'fg' => self::rgb_to_hex($fgcolor),
                                'bg' => self::rgb_to_hex($bgcolor),
                                'source' => $item['title'],
                            ]);
                        }
                    }
                }
            }

            // 2. Deprecated <font color="..."> tags
            if (preg_match_all('/<font\b[^>]*\bcolor\s*=\s*(["\'])(.*?)\1/is', $html, $fontmatches, PREG_SET_ORDER)) {
                foreach ($fontmatches as $fm) {
                    $fgcolor = self::parse_css_color(trim($fm[2]));
                    if ($fgcolor !== null) {
                        $totalcolorstyles++;
                        $bgcolor = [255, 255, 255];
                        $ratio = self::calculate_contrast_ratio($fgcolor, $bgcolor);
                        if ($ratio >= 4.5) {
                            $compliantstyles++;
                        } else {
                            $failedstyles++;
                            $details[] = get_string('wcag_contrast_failed_item', 'local_qualiscope', [
                                'ratio' => number_format($ratio, 1),
                                'fg' => self::rgb_to_hex($fgcolor),
                                'bg' => self::rgb_to_hex($bgcolor),
                                'source' => $item['title'],
                            ]);
                        }
                    }
                }
            }
        }

        if ($totalcolorstyles === 0) {
            return [
                'total' => 0,
                'compliant' => 0,
                'failed' => 0,
                'ratio' => 1.0,
                'status' => 'detected',
                'summary' => get_string('wcag_contrast_default_theme', 'local_qualiscope'),
                'details' => [],
            ];
        }

        $ratio = round($compliantstyles / $totalcolorstyles, 2);
        if ($ratio >= 0.85) {
            $status = 'detected';
        } else if ($ratio >= 0.40) {
            $status = 'verify';
        } else {
            $status = 'missing';
        }

        $summary = get_string('wcag_contrast_summary', 'local_qualiscope', [
            'compliant' => $compliantstyles,
            'total' => $totalcolorstyles,
            'failed' => $failedstyles,
        ]);

        return [
            'total' => $totalcolorstyles,
            'compliant' => $compliantstyles,
            'failed' => $failedstyles,
            'ratio' => $ratio,
            'status' => $status,
            'summary' => $summary,
            'details' => $details,
        ];
    }

    /**
     * Run all 4 WCAG / RGAA audits and combine their findings.
     *
     * @param int $courseid
     * @return array
     */
    public static function analyse_all(int $courseid): array {
        $contents = self::get_course_rich_text_contents($courseid);

        $images = self::analyse_images($contents);
        $videos = self::analyse_videos($courseid, $contents);
        $headings = self::analyse_headings($contents);
        $contrast = self::analyse_contrast($contents);

        // Calculate weighted score across the 4 checks
        $overallratio = round(
            ($images['ratio'] * 0.30) +
            ($videos['ratio'] * 0.25) +
            ($headings['ratio'] * 0.25) +
            ($contrast['ratio'] * 0.20),
            2
        );

        if ($overallratio >= 0.75) {
            $status = 'detected';
        } else if ($overallratio >= 0.35) {
            $status = 'verify';
        } else {
            $status = 'missing';
        }

        $parts = [
            $images['summary'],
            $videos['summary'],
            $headings['summary'],
            $contrast['summary'],
        ];

        return [
            'status' => $status,
            'ratio' => $overallratio,
            'summary' => implode(' | ', $parts),
            'images' => $images,
            'videos' => $videos,
            'headings' => $headings,
            'contrast' => $contrast,
            'contents_count' => count($contents),
        ];
    }

    /**
     * Parse CSS color string into [R, G, B].
     *
     * @param string $colorstr
     * @return int[]|null
     */
    public static function parse_css_color(string $colorstr): ?array {
        $c = strtolower(trim($colorstr));

        // Hex #RGB or #RRGGBB
        if (preg_match('/^#([0-9a-f]{3,8})$/i', $c, $m)) {
            $hex = $m[1];
            if (strlen($hex) === 3) {
                return [
                    hexdec(str_repeat(substr($hex, 0, 1), 2)),
                    hexdec(str_repeat(substr($hex, 1, 1), 2)),
                    hexdec(str_repeat(substr($hex, 2, 1), 2)),
                ];
            } else if (strlen($hex) >= 6) {
                return [
                    hexdec(substr($hex, 0, 2)),
                    hexdec(substr($hex, 2, 2)),
                    hexdec(substr($hex, 4, 2)),
                ];
            }
        }

        // rgb(r, g, b) or rgba(r, g, b, a)
        if (preg_match('/rgba?\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i', $c, $m)) {
            return [(int) $m[1], (int) $m[2], (int) $m[3]];
        }

        // Named colors
        if (isset(self::$namedcolors[$c])) {
            return self::$namedcolors[$c];
        }

        return null;
    }

    /**
     * Calculate relative luminance for an sRGB color.
     *
     * @param int[] $rgb [R, G, B] with values 0-255
     * @return float
     */
    public static function calculate_relative_luminance(array $rgb): float {
        $linear = [];
        foreach ($rgb as $val) {
            $v = max(0, min(255, $val)) / 255.0;
            $linear[] = ($v <= 0.04045) ? ($v / 12.92) : pow(($v + 0.055) / 1.055, 2.4);
        }

        return (0.2126 * $linear[0]) + (0.7152 * $linear[1]) + (0.0722 * $linear[2]);
    }

    /**
     * Calculate contrast ratio between two colors (WCAG 2.1).
     *
     * @param int[] $fg [R, G, B]
     * @param int[] $bg [R, G, B]
     * @return float Ratio (e.g. 4.5, 7.1, 21.0)
     */
    public static function calculate_contrast_ratio(array $fg, array $bg): float {
        $l1 = self::calculate_relative_luminance($fg);
        $l2 = self::calculate_relative_luminance($bg);

        $lighter = max($l1, $l2);
        $darker = min($l1, $l2);

        return round(($lighter + 0.05) / ($darker + 0.05), 2);
    }

    /**
     * Convert RGB array to hex string.
     *
     * @param int[] $rgb
     * @return string
     */
    public static function rgb_to_hex(array $rgb): string {
        return sprintf('#%02X%02X%02X', $rgb[0], $rgb[1], $rgb[2]);
    }
}
