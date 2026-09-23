<?php

namespace local_qualiscope\analyser;

defined('MOODLE_INTERNAL') || die();

class evidence_analyser {

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

    public static function get_external_evidences(int $resultid): array {
        global $DB;

        return $DB->get_records('local_qualiscope_evidences', [
            'result_id' => $resultid,
            'type' => 'external',
        ], 'timecreated DESC');
    }

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
                     WHERE cm.course = :courseid AND cm.visible = 1 AND m.name IN ('assign','quiz','workshop','feedback','choice','data','lesson','lti','survey','bigbluebuttonbn','jitsi')
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
                     WHERE cm.course = :courseid AND cm.visible = 1 AND m.name IN ('url','folder','file','page','imscp','book','wiki')
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
                $fids = array_map(function ($fb) { return $fb->fid; }, $feedbacks);
                list($fidsql, $fidparams) = $DB->get_in_or_equal($fids, SQL_PARAMS_NAMED, 'fid');
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
                    $fids2 = array_map(function ($r) { return (int) $r->feedback; }, $fidswithitems);
                    list($fidsql2, $fidparams2) = $DB->get_in_or_equal($fids2, SQL_PARAMS_NAMED, 'fid');
                    $responsecount = $DB->get_field_sql(
                        "SELECT COUNT(DISTINCT fc.id)
                         FROM {feedback_completed} fc
                         WHERE fc.feedback $fidsql2",
                        $fidparams2
                    );
                    if ($responsecount == 0) {
                        $evidences[] = [
                            'title' => get_string('check_feedback_no_responses', 'local_qualiscope', ['count' => $itemcount, 'activities' => count($feedbacks)]),
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
                        'description' => mb_substr($course->summary, 0, 200),
                    ];
                }
                break;
        }

        return $evidences;
    }

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
