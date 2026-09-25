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
 * QualiScope Evidence Analyser class.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_qualiscope\analyser;


/**
 * Collects evidence items for an indicator from Moodle course data.
 *
 * @package local_qualiscope
 */
class evidence_analyser {
    /**
     * Gathers automatic evidence from the course for every check of an indicator.
     *
     * @param int $courseid The course id.
     * @param int $indicatorid The indicator id.
     * @return array List of evidence arrays.
     */
    public static function get_moodle_evidences(int $courseid, int $indicatorid): array {
        global $DB;

        $evidences = [];
        $checks = $DB->get_records('local_qualiscope_checks', ['indicator_id' => $indicatorid, 'automatic' => 1]);

        foreach ($checks as $check) {
            $moodlevidence = self::collect_moodle_evidence($courseid, $check);
            $evidences = array_merge($evidences, $moodlevidence);
        }

        return $evidences;
    }

    /**
     * Lists the manual (external) evidences attached to an analysis result.
     *
     * @param int $resultid The result record id.
     * @return array Records from local_qualiscope_evidences.
     */
    public static function get_external_evidences(int $resultid): array {
        global $DB;

        return $DB->get_records('local_qualiscope_evidences', [
            'result_id' => $resultid,
            'type' => 'external',
        ], 'timecreated DESC');
    }

    /**
     * Collects evidence rows matching a single automatic check type.
     *
     * @param int $courseid The course id.
     * @param object $check The check record.
     * @return array List of evidence arrays.
     */
    private static function collect_moodle_evidence(int $courseid, object $check): array {
        global $DB;

        $evidences = [];
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

        switch ($check->type) {
            case 'activity_exists':
                $activities = $DB->get_records_sql(
                    "SELECT cm.*, m.name AS modname
                     FROM {course_modules} cm
                     JOIN {modules} m ON m.id = cm.module
                     WHERE cm.course = :courseid AND cm.visible = 1
                       AND m.name IN ('assign','quiz','workshop','feedback','choice',
                                      'data','lesson','lti','survey','bigbluebuttonbn','jitsi')
                     ORDER BY cm.id ASC",
                    ['courseid' => $courseid]
                );
                foreach ($activities as $act) {
                    $instance = self::get_activity_instance($act);
                    if ($instance) {
                        $evidences[] = [
                            'title' => $instance->name,
                            'source' => $act->modname,
                            'description' => get_string('evidence_activity', 'local_qualiscope', $act->modname),
                        ];
                    }
                }
                break;

            case 'resource_exists':
                $resources = $DB->get_records_sql(
                    "SELECT cm.*, m.name AS modname
                     FROM {course_modules} cm
                     JOIN {modules} m ON m.id = cm.module
                     WHERE cm.course = :courseid AND cm.visible = 1
                       AND m.name IN ('url','folder','file','page','imscp','book','wiki')
                     ORDER BY cm.id ASC",
                    ['courseid' => $courseid]
                );
                foreach ($resources as $res) {
                    $instance = self::get_activity_instance($res);
                    if ($instance) {
                        $evidences[] = [
                            'title' => $instance->name,
                            'source' => $res->modname,
                            'description' => get_string('evidence_resource', 'local_qualiscope', $res->modname),
                        ];
                    }
                }
                break;

            case 'completion_enabled':
                if ($course->enablecompletion) {
                    $evidences[] = [
                        'title' => get_string('evidence_completion_enabled', 'local_qualiscope'),
                        'source' => 'course',
                        'description' => get_string('evidence_completion_desc', 'local_qualiscope'),
                    ];
                }
                $completions = $DB->get_records_sql(
                    "SELECT COUNT(*) as cnt FROM {course_modules}
                     WHERE course = :courseid AND completion > 0",
                    ['courseid' => $courseid]
                );
                $count = reset($completions)->cnt ?? 0;
                if ($count > 0) {
                    $evidences[] = [
                        'title' => get_string('evidence_completion_count', 'local_qualiscope', $count),
                        'source' => 'completion',
                        'description' => get_string('evidence_completion_modules', 'local_qualiscope', $count),
                    ];
                }
                break;

            case 'gradebook_exists':
                $gradeitems = $DB->get_records_sql(
                    "SELECT COUNT(*) as cnt FROM {grade_items}
                     WHERE courseid = :courseid AND itemtype != 'course'",
                    ['courseid' => $courseid]
                );
                $count = reset($gradeitems)->cnt ?? 0;
                if ($count > 0) {
                    $evidences[] = [
                        'title' => get_string('evidence_gradebook_count', 'local_qualiscope', $count),
                        'source' => 'gradebook',
                        'description' => get_string('evidence_gradebook_desc', 'local_qualiscope', $count),
                    ];
                }
                break;

            case 'feedback_exists':
                $feedbacks = $DB->get_records_sql(
                    "SELECT cm.id, f.id AS fid, f.name
                     FROM {course_modules} cm
                     JOIN {feedback} f ON f.id = cm.instance
                     WHERE cm.course = :courseid AND cm.visible = 1 AND cm.module = (
                         SELECT id FROM {modules} WHERE name = 'feedback'
                     )",
                    ['courseid' => $courseid]
                );
                if (empty($feedbacks)) {
                    $evidences[] = [
                        'title' => get_string('check_feedback_none', 'local_qualiscope'),
                        'source' => 'qualiscope',
                        'description' => '',
                        'issue' => true,
                    ];
                    break;
                }
                foreach ($feedbacks as $fb) {
                    $evidences[] = [
                        'title' => $fb->name,
                        'source' => 'feedback',
                        'description' => get_string('evidence_feedback_found', 'local_qualiscope'),
                    ];
                }
                $fids = array_map(function ($fb) {
                    return $fb->fid;
                }, $feedbacks);
                [$fidsql, $fidparams] = $DB->get_in_or_equal($fids, SQL_PARAMS_NAMED, 'fid');
                $itemcount = $DB->get_field_sql(
                    "SELECT COUNT(*) FROM {feedback_item} WHERE feedback $fidsql",
                    $fidparams
                );
                if ($itemcount == 0) {
                    $evidences[] = [
                        'title' => get_string('check_feedback_no_items', 'local_qualiscope', count($feedbacks)),
                        'source' => 'qualiscope',
                        'description' => '',
                        'issue' => true,
                    ];
                } else {
                    $fidswithitems = $DB->get_records_sql(
                        "SELECT DISTINCT feedback
                         FROM {feedback_item}
                         WHERE feedback $fidsql",
                        $fidparams
                    );
                    $fids2 = array_map(function ($r) {
                        return (int) $r->feedback;
                    }, $fidswithitems);
                    [$fidsql2, $fidparams2] = $DB->get_in_or_equal($fids2, SQL_PARAMS_NAMED, 'fid');
                    $responsecount = $DB->get_field_sql(
                        "SELECT COUNT(DISTINCT fc.id)
                         FROM {feedback_completed} fc
                         WHERE fc.feedback $fidsql2",
                        $fidparams2
                    );
                    if ($responsecount == 0) {
                        $evidences[] = [
                            'title' => get_string('check_feedback_no_responses', 'local_qualiscope', [
                                'count' => $itemcount,
                                'activities' => count($feedbacks),
                            ]),
                            'source' => 'qualiscope',
                            'description' => '',
                            'issue' => true,
                        ];
                    } else {
                        $enrolled = count_enrolled_users(\context_course::instance($courseid));
                        $target = (int) ceil(max(1, $enrolled) * 0.33);
                        if ($responsecount < $target) {
                            $evidences[] = [
                                'title' => get_string('check_feedback_insufficient', 'local_qualiscope', [
                                    'activities' => count($feedbacks),
                                    'count' => $itemcount,
                                    'responses' => $responsecount,
                                    'target' => $target,
                                ]),
                                'source' => 'qualiscope',
                                'description' => '',
                                'issue' => true,
                            ];
                        } else {
                            $percentage = (int) round($responsecount * 100 / max(1, $enrolled));
                            $evidences[] = [
                                'title' => get_string('check_feedback_satisfied', 'local_qualiscope', [
                                    'responses' => $responsecount,
                                    'enrolled' => $enrolled,
                                    'percentage' => $percentage,
                                    'target' => $target,
                                ]),
                                'source' => 'qualiscope',
                                'description' => get_string('check_feedback_satisfied_desc', 'local_qualiscope'),
                            ];
                        }
                    }
                }
                break;

            case 'field_exists':
                $hasdesc = !empty($course->summary);
                if ($hasdesc) {
                    $evidences[] = [
                        'title' => get_string('evidence_course_summary', 'local_qualiscope'),
                        'source' => 'course',
                        'description' => format_text(
                            mb_substr(html_to_text($course->summary, 0, false), 0, 200),
                            FORMAT_MOODLE,
                            ['context' => \context_course::instance($courseid)]
                        ),
                    ];
                }
                break;
        }

        return $evidences;
    }

    /**
     * Returns the module instance record for a course module, or null for unsupported modules.
     *
     * @param object $cm A course_modules record with the modname property.
     * @return object|null
     */
    private static function get_activity_instance(object $cm) {
        global $DB;

        $modnameswithinstances = [
            'assign', 'quiz', 'workshop', 'feedback', 'choice',
            'data', 'lesson', 'lti', 'survey', 'bigbluebuttonbn',
            'jitsi', 'url', 'folder', 'file', 'page', 'imscp',
            'book', 'wiki',
        ];

        $modtablename = '{' . $cm->modname . '}';
        if (in_array($cm->modname, $modnameswithinstances)) {
            return $DB->get_record($cm->modname, ['id' => $cm->instance]);
        }
        return null;
    }
}
