<?php

namespace local_qualiscope\analyser;

defined('MOODLE_INTERNAL') || die();

class course_analyser {

    private $courseid;
    private $course;
    private $results = [];
    private $campaignid;
    private $referentialid;

    public function __construct(int $courseid, int $campaignid = 0, ?int $referentialid = null) {
        global $DB;
        $this->courseid = $courseid;
        $this->course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $this->campaignid = $campaignid;
        $this->referentialid = $referentialid ?: self::get_default_referential_id();
    }

    /**
     * Returns the id of the first active referential, or null if none.
     */
    public static function get_default_referential_id(): ?int {
        global $DB;

        $cfg = (int) get_config('local_qualiscope', 'defaultreferential');
        if ($cfg && $DB->record_exists('local_qualiopi_referentials', ['id' => $cfg, 'active' => 1])) {
            return $cfg;
        }

        $ref = $DB->get_records('local_qualiopi_referentials', ['active' => 1], 'id ASC', 'id', 0, 1);
        if ($ref) {
            $first = reset($ref);
            return (int) $first->id;
        }
        return null;
    }

    public function get_referentialid(): ?int {
        return $this->referentialid;
    }

    /**
     * Resolves the course ids covered by a campaign scope.
     *
     * @param \stdClass $campaign
     * @return int[]
     */
    public static function get_campaign_course_ids(\stdClass $campaign): array {
        global $DB;

        $scope = $campaign->scope ?? 'all';
        $scopeids = json_decode($campaign->scopeids ?? '', true) ?: [];
        $siteid = SITEID;

        switch ($scope) {
            case 'category':
                if (!$scopeids) {
                    return [];
                }
                list($insql, $inparams) = $DB->get_in_or_equal($scopeids, SQL_PARAMS_NAMED, 'cat');
                $inparams['siteid'] = $siteid;
                return $DB->get_fieldset_sql(
                    "SELECT id FROM {course} WHERE visible = 1 AND id <> :siteid AND category $insql ORDER BY id ASC",
                    $inparams
                );

            case 'selected':
                if (!$scopeids) {
                    return [];
                }
                list($insql, $inparams) = $DB->get_in_or_equal($scopeids, SQL_PARAMS_NAMED, 'cid');
                $inparams['siteid'] = $siteid;
                return $DB->get_fieldset_sql(
                    "SELECT id FROM {course} WHERE visible = 1 AND id <> :siteid AND id $insql ORDER BY id ASC",
                    $inparams
                );

            default:
                return $DB->get_fieldset_sql(
                    "SELECT id FROM {course} WHERE visible = 1 AND id <> $siteid ORDER BY id ASC"
                );
        }
    }

    public function run(): array {
        global $DB;

        if ($this->referentialid === null) {
            return [];
        }

        $checks = $DB->get_records_sql(
            "SELECT c.* FROM {local_qualiopi_checks} c
             JOIN {local_qualiopi_indicators} i ON i.id = c.indicator_id
             JOIN {local_qualiopi_criteria} cr ON cr.id = i.criterion_id
             WHERE c.automatic = 1 AND cr.referential_id = :refid",
            ['refid' => $this->referentialid]
        );
        $results = [];

        foreach ($checks as $check) {
            $indicator = $DB->get_record('local_qualiopi_indicators', ['id' => $check->indicator_id]);
            $analyser = $this->get_check_analyser($check->type);
            if ($analyser) {
                $result = $analyser->execute($this->courseid, $check);
                $result['check'] = $check;
                $result['indicator'] = $indicator;
                $results[] = $result;
            }
        }

        $this->results = $results;
        return $results;
    }

    public function get_summary(): array {
        $summary = [
            'total' => 0,
            'detected' => 0,
            'verify' => 0,
            'missing' => 0,
            'na' => 0,
            'percentage' => 0,
        ];

        $weighted = 0.0;

        foreach ($this->results as $result) {
            $summary['total']++;
            switch ($result['status']) {
                case 'detected':
                    $summary['detected']++;
                    break;
                case 'verify':
                    $summary['verify']++;
                    break;
                case 'missing':
                    $summary['missing']++;
                    break;
                case 'na':
                    $summary['na']++;
                    break;
            }

            if ($result['status'] !== 'na') {
                $weighted += $result['ratio'] ?? ($result['status'] === 'detected' ? 1.0 : 0.0);
            }
        }

        $applicable = $summary['total'] - $summary['na'];
        if ($applicable > 0) {
            $summary['percentage'] = (int) round(($weighted * 100) / $applicable);
        }

        return $summary;
    }

    public function get_criteria_summary(): array {
        global $DB;

        if ($this->referentialid === null) {
            return [];
        }

        $criteria = $DB->get_records('local_qualiopi_criteria', ['referential_id' => $this->referentialid], 'number ASC');
        $criteriasummary = [];
        foreach ($criteria as $c) {
            $criteriasummary[$c->id] = [
                'criteria' => $c,
                'id' => $c->id,
                'total' => 0,
                'detected' => 0,
                'verify' => 0,
                'missing' => 0,
                'na' => 0,
                'weighted' => 0.0,
                'percentage' => null,
                'manualonly' => true,
                'results' => [],
            ];
        }

        foreach ($this->results as $result) {
            $cid = $result['indicator']->criterion_id;
            if (!isset($criteriasummary[$cid])) {
                $criteria = $DB->get_record('local_qualiopi_criteria', ['id' => $cid]);
                $criteriasummary[$cid] = [
                    'criteria' => $criteria,
                    'id' => $cid,
                    'total' => 0,
                    'detected' => 0,
                    'verify' => 0,
                    'missing' => 0,
                    'na' => 0,
                    'weighted' => 0.0,
                    'percentage' => null,
                    'manualonly' => true,
                    'results' => [],
                ];
            }
            $entry = &$criteriasummary[$cid];
            $entry['manualonly'] = false;
            $entry['total']++;
            $entry['results'][] = $result;
            switch ($result['status']) {
                case 'detected':
                    $entry['detected']++;
                    break;
                case 'verify':
                    $entry['verify']++;
                    break;
                case 'missing':
                    $entry['missing']++;
                    break;
                case 'na':
                    $entry['na']++;
                    break;
            }
            if ($result['status'] !== 'na') {
                $entry['weighted'] += $result['ratio'] ?? ($result['status'] === 'detected' ? 1.0 : 0.0);
            }
        }

        foreach ($criteriasummary as &$entry) {
            if ($entry['manualonly']) {
                $entry['percentage'] = null;
                continue;
            }
            $applicable = $entry['total'] - $entry['na'];
            if ($applicable > 0) {
                $entry['percentage'] = (int) round(($entry['weighted'] * 100) / $applicable);
            }
        }

        return $criteriasummary;
    }

    public function save_result(array $result): int {
        global $DB;

        $existing = $DB->get_record('local_qualiopi_results', [
            'campaign_id' => $this->campaignid,
            'courseid' => $this->courseid,
            'check_id' => $result['check']->id,
        ]);
        if ($existing) {
            $existing->status = $result['status'];
            $existing->detail = $result['detail'] ?? '';
            $existing->ratio = $result['ratio'] ?? ($result['status'] === 'detected' ? 1.0 : 0.0);
            $existing->timemodified = time();
            $DB->update_record('local_qualiopi_results', $existing);
            return (int) $existing->id;
        }

        $record = new \stdClass();
        $record->campaign_id = $this->campaignid;
        $record->courseid = $this->courseid;
        $record->check_id = $result['check']->id;
        $record->indicator_id = $result['indicator']->id;
        $record->status = $result['status'];
        $record->detail = $result['detail'] ?? '';
        $record->ratio = $result['ratio'] ?? ($result['status'] === 'detected' ? 1.0 : 0.0);
        $record->evidence_count = 0;
        $record->timecreated = time();
        $record->timemodified = time();
        return (int) $DB->insert_record('local_qualiopi_results', $record);
    }

    public function save_results(int $campaignid): void {
        $this->campaignid = $campaignid;
        foreach ($this->results as $result) {
            $this->save_result($result);
        }
    }

    private function get_check_analyser(string $type) {
        $map = [
            'activity_exists'   => new \local_qualiscope\checks\activity_check(),
            'resource_exists'   => new \local_qualiscope\checks\resource_check(),
            'completion_enabled' => new \local_qualiscope\checks\completion_check(),
            'gradebook_exists'  => new \local_qualiscope\checks\gradebook_check(),
            'feedback_exists'   => new \local_qualiscope\checks\feedback_check(),
            'field_exists'      => new \local_qualiscope\checks\field_check(),
        ];
        return $map[$type] ?? null;
    }
}
