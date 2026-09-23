<?php

namespace local_qualiscope\checks;

defined('MOODLE_INTERNAL') || die();

/**
 * Represents a manual indicator validated by an external documentary evidence.
 *
 * @package local_qualiscope
 */
class manual_check extends base_check {

    /**
     * Returns a placeholder verify result; validation happens through the evidence panel.
     *
     * @param int $courseid The course id to audit.
     * @param object $check The check record.
     * @return array Result with status and detail keys.
     */
    public function execute(int $courseid, object $check): array {
        return $this->build_result('verify', get_string('check_manual_description', 'local_qualiscope'));
    }
}