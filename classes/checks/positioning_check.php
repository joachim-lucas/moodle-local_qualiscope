<?php

namespace local_qualiscope\checks;

defined('MOODLE_INTERNAL') || die();

class positioning_check extends base_check {

    public function execute(int $courseid, object $check): array {
        global $DB;

        // Search for positioning activities: quiz, feedback, questionnaire, survey with positioning keywords or in section 0 / early.
        $sql = "SELECT cm.id, cm.section, cm.added, m.name as modname,
                       CASE
                           WHEN m.name = 'quiz' THEN q.name
                           WHEN m.name = 'feedback' THEN fb.name
                           WHEN m.name = 'assign' THEN a.name
                           ELSE ''
                       END as itemname
                FROM {course_modules} cm
                JOIN {modules} m ON m.id = cm.module
                LEFT JOIN {quiz} q ON m.name = 'quiz' AND q.id = cm.instance
                LEFT JOIN {feedback} fb ON m.name = 'feedback' AND fb.id = cm.instance
                LEFT JOIN {assign} a ON m.name = 'assign' AND a.id = cm.instance
                WHERE cm.course = :courseid AND cm.visible = 1
                  AND m.name IN ('quiz', 'feedback', 'assign', 'survey', 'questionnaire')";

        $modules = $DB->get_records_sql($sql, ['courseid' => $courseid]);

        $positioningfound = [];
        $keywords = ['positionnement', 'initial', 'test d\'entrée', 'test d\'entree', 'diagnostic', 'prérequis', 'prerequis', 'auto-évaluation', 'auto-evaluation', 'évaluation initiale', 'evaluation initiale', 'niveau'];

        foreach ($modules as $mod) {
            $name = mb_strtolower($mod->itemname ?? '');
            $ispos = false;
            foreach ($keywords as $kw) {
                if (mb_strpos($name, $kw) !== false) {
                    $ispos = true;
                    break;
                }
            }
            // If situated in section 0 or 1 with quiz/diagnostic type
            if ($ispos || ($mod->section <= 1 && ($mod->modname === 'quiz' || $mod->modname === 'feedback'))) {
                $positioningfound[] = $mod;
            }
        }

        if (!empty($positioningfound)) {
            $count = count($positioningfound);
            return $this->build_result(
                'detected',
                get_string('check_positioning_found', 'local_qualiscope', $count),
                1.0
            );
        }

        return $this->build_result(
            'verify',
            get_string('check_positioning_none', 'local_qualiscope'),
            0.0
        );
    }
}
