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
 * QualiScope Alignment Check class.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_qualiscope\checks;


/**
 * Check for constructive alignment: Objectives/Competencies <-> Activities <-> Graded Assessments (Qualiopi Indicators 5, 6 & 11).
 *
 * @package local_qualiscope
 */
class alignment_check extends base_check {
    /**
     * Executes the constructive alignment check on the course.
     *
     * @param int $courseid The course id to audit.
     * @param object $check The check record.
     * @return array Result with status, detail and optional ratio keys.
     */
    public function execute(int $courseid, object $check): array {
        global $DB;

        $score = 0;
        $maxscore = 100;
        $details = [];

        // 1. Objectives / Competencies definition (25 pts)
        $coursecompcount = (int) $DB->count_records('competency_coursecomp', ['courseid' => $courseid]);
        $course = $DB->get_record('course', ['id' => $courseid]);
        $hassummaryobjectives = false;

        if (
            $course && (
            stripos($course->summary, 'objectif') !== false ||
            stripos($course->summary, 'compétence') !== false ||
            stripos($course->summary, 'programme') !== false
            )
        ) {
            $hassummaryobjectives = true;
        }

        if ($coursecompcount >= 3) {
            $score += 25;
            $details[] = get_string('alignment_competencies_structured', 'local_qualiscope', $coursecompcount);
        } else if ($coursecompcount > 0) {
            $score += 15 + ($coursecompcount * 3);
            $details[] = get_string('alignment_competencies_few', 'local_qualiscope', $coursecompcount);
        } else if ($hassummaryobjectives) {
            $score += 12;
            $details[] = get_string('alignment_competencies_textual', 'local_qualiscope');
        } else {
            $details[] = get_string('alignment_competencies_none', 'local_qualiscope');
        }

        // 2. Learning activities & modules mapping (25 pts)
        $learningmodules = (int) $DB->count_records_select(
            'course_modules',
            'course = :courseid AND visible = 1',
            ['courseid' => $courseid]
        );

        if ($learningmodules >= 8) {
            $score += 25;
            $details[] = get_string('alignment_activities_rich', 'local_qualiscope', $learningmodules);
        } else if ($learningmodules >= 3) {
            $score += (int) round(($learningmodules / 8) * 25);
            $details[] = get_string('alignment_activities_moderate', 'local_qualiscope', $learningmodules);
        } else if ($learningmodules > 0) {
            $score += 8;
            $details[] = get_string('alignment_activities_few', 'local_qualiscope', $learningmodules);
        } else {
            $details[] = get_string('alignment_activities_none', 'local_qualiscope');
        }

        // 3. Graded Assessments in Gradebook (25 pts)
        $gradeitems = $DB->get_records_select(
            'grade_items',
            'courseid = :courseid AND itemtype = :itemtype',
            ['courseid' => $courseid, 'itemtype' => 'mod']
        );
        $gradeitemscount = count($gradeitems);

        if ($gradeitemscount >= 4) {
            $score += 25;
            $details[] = get_string('alignment_assessments_multiple', 'local_qualiscope', $gradeitemscount);
        } else if ($gradeitemscount >= 1) {
            $score += 10 + ($gradeitemscount * 4);
            $details[] = get_string('alignment_assessments_few', 'local_qualiscope', $gradeitemscount);
        } else {
            $details[] = get_string('alignment_assessments_none', 'local_qualiscope');
        }

        // 4. Assessment Quality: Rubrics / Marking Guides / Feedback / Linked module competencies (25 pts)
        $subscore = 0;
        // Check rubrics or marking guides in grading definitions.
        $rubriccount = (int) $DB->count_records_sql(
            "SELECT COUNT(gd.id)
             FROM {grading_definitions} gd
             JOIN {grading_areas} ga ON ga.id = gd.areaid
             JOIN {context} ctx ON ctx.id = ga.contextid
             JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = 70
             WHERE cm.course = :courseid AND gd.status = 20",
            ['courseid' => $courseid]
        );

        // Check module linked competencies.
        $modcompcount = (int) $DB->count_records_sql(
            "SELECT COUNT(mc.id)
             FROM {competency_modulecomp} mc
             JOIN {course_modules} cm ON cm.id = mc.cmid
             WHERE cm.course = :courseid",
            ['courseid' => $courseid]
        );

        // Check quiz feedback configuration.
        $quizfeedbackcount = (int) $DB->count_records_sql(
            "SELECT COUNT(qf.id)
             FROM {quiz_feedback} qf
             JOIN {quiz} q ON q.id = qf.quizid
             WHERE q.course = :courseid AND " . $DB->sql_isnotempty('quiz_feedback', 'feedbacktext', true, true),
            ['courseid' => $courseid]
        );

        if ($rubriccount > 0) {
            $subscore += 12;
            $details[] = get_string('alignment_rubrics_found', 'local_qualiscope', $rubriccount);
        }
        if ($modcompcount > 0) {
            $subscore += 10;
            $details[] = get_string('alignment_modcomp_found', 'local_qualiscope', $modcompcount);
        }
        if ($quizfeedbackcount > 0) {
            $subscore += 8;
            $details[] = get_string('alignment_quizfeedback_found', 'local_qualiscope', $quizfeedbackcount);
        }

        if ($subscore === 0 && $gradeitemscount > 0) {
            // Partial credit if grade items have pass grades configured.
            $passgrades = 0;
            foreach ($gradeitems as $gi) {
                if ($gi->gradepass > 0) {
                    $passgrades++;
                }
            }
            if ($passgrades > 0) {
                $subscore += 6;
                $details[] = get_string('alignment_passgrades_found', 'local_qualiscope', $passgrades);
            }
        }

        $score += min(25, $subscore);

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
