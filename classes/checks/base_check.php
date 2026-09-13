<?php

namespace local_qualiscope\checks;

defined('MOODLE_INTERNAL') || die();

abstract class base_check {

    abstract public function execute(int $courseid, object $check): array;

    protected function build_result(string $status, string $detail = '', ?float $ratio = null): array {
        $result = [
            'status' => $status,
            'detail' => $detail,
        ];
        if ($ratio !== null) {
            $result['ratio'] = max(0.0, min(1.0, $ratio));
        }
        return $result;
    }
}
