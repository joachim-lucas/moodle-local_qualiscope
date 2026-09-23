<?php

namespace local_qualiscope\checks;

defined('MOODLE_INTERNAL') || die();

/**
 * Base class for automated course audit checks.
 *
 * @package local_qualiscope
 */
abstract class base_check {

    /**
     * Runs the check against the given course.
     *
     * @param int $courseid The course id to audit.
     * @param object $check The check record.
     * @return array Result with status, detail and optional ratio keys.
     */
    abstract public function execute(int $courseid, object $check): array;

    /**
     * Builds a normalised check result array, clamping the ratio between 0 and 1.
     *
     * @param string $status Status code (detected, verify, missing, na).
     * @param string $detail Human-readable detail for the result.
     * @param float|null $ratio Optional compliance ratio between 0 and 1.
     * @return array
     */
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
