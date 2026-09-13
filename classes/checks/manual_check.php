<?php

namespace local_qualiscope\checks;

defined('MOODLE_INTERNAL') || die();

class manual_check extends base_check {

    public function execute(int $courseid, object $check): array {
        return $this->build_result('verify', get_string('check_manual_description', 'local_qualiscope'));
    }
}