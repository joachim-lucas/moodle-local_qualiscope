<?php

namespace local_qualiscope\checks;

defined('MOODLE_INTERNAL') || die();

/**
 * Check for learner engagement, activity tracking, and dropout prevention (Qualiopi Indicators 10 & 12).
 *
 * @package local_qualiscope
 */
class engagement_check extends base_check {

    /**
     * Executes the learner engagement check on the course.
     *
     * @param int $courseid The course id to audit.
     * @param object $check The check record.
     * @return array Result with status, detail and optional ratio keys.
     */
    public function execute(int $courseid, object $check): array {
        global $DB;

        $context = \context_course::instance($courseid);
        $enrolledcount = count_enrolled_users($context);
        $course = $DB->get_record('course', ['id' => $courseid]);

        $score = 0;
        $maxscore = 100;
        $details = [];

        // 1. Completion tracking enabled at course level (20 pts)
        if (!empty($course->enablecompletion)) {
            $score += 20;
            $details[] = get_string('engagement_completion_enabled', 'local_qualiscope');
        } else {
            $details[] = get_string('engagement_completion_disabled', 'local_qualiscope');
        }

        // 2. Activities with tracking criteria configured (20 pts)
        $completedmodules = $DB->count_records_select(
            'course_modules',
            'course = :courseid AND completion > 0 AND visible = 1',
            ['courseid' => $courseid]
        );
        if ($completedmodules >= 5) {
            $score += 20;
            $details[] = get_string('engagement_modules_many', 'local_qualiscope', $completedmodules);
        } else if ($completedmodules > 0) {
            $score += (int) round(($completedmodules / 5) * 20);
            $details[] = get_string('engagement_modules_few', 'local_qualiscope', $completedmodules);
        } else {
            $details[] = get_string('engagement_modules_none', 'local_qualiscope');
        }

        // 3. Learner Activity & Inactivity rate (25 pts)
        if ($enrolledcount > 0) {
            $now = time();
            $cutoff = $now - (14 * 86400); // 14 days inactivity threshold

            // Check users with last access
            $sql = "SELECT COUNT(DISTINCT u.id)
                    FROM {user} u
                    JOIN {user_enrolments} ue ON ue.userid = u.id
                    JOIN {enrol} e ON e.id = ue.enrolid
                    LEFT JOIN {user_lastaccess} ula ON ula.userid = u.id AND ula.courseid = e.courseid
                    WHERE e.courseid = :courseid AND ue.status = 0
                      AND (ula.timeaccess IS NOT NULL AND ula.timeaccess >= :cutoff)";

            $activeusers = (int) $DB->count_records_sql($sql, ['courseid' => $courseid, 'cutoff' => $cutoff]);
            $activerate = round(($activeusers / $enrolledcount) * 100);

            if ($activerate >= 70) {
                $score += 25;
            } else if ($activerate >= 40) {
                $score += 15;
            } else if ($activerate > 0) {
                $score += 8;
            }
            $details[] = get_string('engagement_active_rate', 'local_qualiscope', [
                'active' => $activeusers,
                'total' => $enrolledcount,
                'rate' => $activerate,
            ]);
        } else {
            // If no enrolled users yet, give partial credit for structural readiness
            $score += 10;
            $details[] = get_string('engagement_no_students_structure_only', 'local_qualiscope');
        }

        // 4. Communication & support channels (Forums / Messages) (20 pts)
        $forumcount = $DB->count_records('forum', ['course' => $courseid]);
        if ($forumcount > 0) {
            $discussions = $DB->count_records_sql(
                "SELECT COUNT(d.id) FROM {forum_discussions} d
                 JOIN {forum} f ON f.id = d.forum
                 WHERE f.course = :courseid",
                ['courseid' => $courseid]
            );
            if ($discussions > 0) {
                $score += 20;
                $details[] = get_string('engagement_forums_active', 'local_qualiscope', [
                    'forums' => $forumcount,
                    'discussions' => $discussions,
                ]);
            } else {
                $score += 12;
                $details[] = get_string('engagement_forums_empty', 'local_qualiscope', $forumcount);
            }
        } else {
            $details[] = get_string('engagement_forums_none', 'local_qualiscope');
        }

        // 5. Individual accommodations / Extension overrides (15 pts)
        $assignoverrides = $DB->count_records_sql(
            "SELECT COUNT(o.id) FROM {assign_overrides} o
             JOIN {assign} a ON a.id = o.assignid
             WHERE a.course = :courseid",
            ['courseid' => $courseid]
        );
        $quizoverrides = $DB->count_records_sql(
            "SELECT COUNT(o.id) FROM {quiz_overrides} o
             JOIN {quiz} q ON q.id = o.quiz
             WHERE q.course = :courseid",
            ['courseid' => $courseid]
        );
        $totaloverrides = $assignoverrides + $quizoverrides;

        if ($totaloverrides > 0) {
            $score += 15;
            $details[] = get_string('engagement_overrides_found', 'local_qualiscope', $totaloverrides);
        } else {
            // Badges or custom milestones
            $badgecount = $DB->count_records('badge', ['courseid' => $courseid, 'status' => 1]);
            if ($badgecount > 0) {
                $score += 10;
                $details[] = get_string('engagement_badges_found', 'local_qualiscope', $badgecount);
            } else {
                $details[] = get_string('engagement_overrides_none', 'local_qualiscope');
            }
        }

        $ratio = min(1.0, max(0.0, round($score / $maxscore, 2)));

        if ($ratio >= 0.70) {
            $status = 'detected';
        } else if ($ratio >= 0.30) {
            $status = 'verify';
        } else {
            $status = 'missing';
        }

        $detailstr = implode(' | ', $details);

        return $this->build_result($status, $detailstr, $ratio);
    }
}
