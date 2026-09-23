<?php

namespace local_qualiscope\help;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper class providing comprehensive methodological and auditing documentation
 * for each referential, criterion, indicator and check type in QualiScope.
 */
class referential_guide {

    /**
     * Get detailed explanation of how a specific check type operates in Moodle.
     *
     * @param string $checktype
     * @return array
     */
    public static function get_check_type_documentation(string $checktype): array {
        $docs = [
            'field_exists' => [
                'name' => get_string('check_doc_field_name', 'local_qualiscope'),
                'moodle_target' => get_string('check_doc_field_target', 'local_qualiscope'),
                'calculation' => get_string('check_doc_field_calc', 'local_qualiscope'),
                'badge' => 'primary',
            ],
            'resource_exists' => [
                'name' => get_string('check_doc_resource_name', 'local_qualiscope'),
                'moodle_target' => get_string('check_doc_resource_target', 'local_qualiscope'),
                'calculation' => get_string('check_doc_resource_calc', 'local_qualiscope'),
                'badge' => 'primary',
            ],
            'activity_exists' => [
                'name' => get_string('check_doc_activity_name', 'local_qualiscope'),
                'moodle_target' => get_string('check_doc_activity_target', 'local_qualiscope'),
                'calculation' => get_string('check_doc_activity_calc', 'local_qualiscope'),
                'badge' => 'primary',
            ],
            'gradebook_exists' => [
                'name' => get_string('check_doc_gradebook_name', 'local_qualiscope'),
                'moodle_target' => get_string('check_doc_gradebook_target', 'local_qualiscope'),
                'calculation' => get_string('check_doc_gradebook_calc', 'local_qualiscope'),
                'badge' => 'primary',
            ],
            'feedback_exists' => [
                'name' => get_string('check_doc_feedback_name', 'local_qualiscope'),
                'moodle_target' => get_string('check_doc_feedback_target', 'local_qualiscope'),
                'calculation' => get_string('check_doc_feedback_calc', 'local_qualiscope'),
                'badge' => 'primary',
            ],
            'completion_exists' => [
                'name' => get_string('check_doc_completion_name', 'local_qualiscope'),
                'moodle_target' => get_string('check_doc_completion_target', 'local_qualiscope'),
                'calculation' => get_string('check_doc_completion_calc', 'local_qualiscope'),
                'badge' => 'primary',
            ],
            'completion_enabled' => [
                'name' => get_string('check_doc_completion_name', 'local_qualiscope'),
                'moodle_target' => get_string('check_doc_completion_target', 'local_qualiscope'),
                'calculation' => get_string('check_doc_completion_calc', 'local_qualiscope'),
                'badge' => 'primary',
            ],
            'competency_exists' => [
                'name' => get_string('check_doc_competency_name', 'local_qualiscope'),
                'moodle_target' => get_string('check_doc_competency_target', 'local_qualiscope'),
                'calculation' => get_string('check_doc_competency_calc', 'local_qualiscope'),
                'badge' => 'primary',
            ],
            'positioning_exists' => [
                'name' => get_string('check_doc_positioning_name', 'local_qualiscope'),
                'moodle_target' => get_string('check_doc_positioning_target', 'local_qualiscope'),
                'calculation' => get_string('check_doc_positioning_calc', 'local_qualiscope'),
                'badge' => 'primary',
            ],
            'stats_exists' => [
                'name' => get_string('check_doc_stats_name', 'local_qualiscope'),
                'moodle_target' => get_string('check_doc_stats_target', 'local_qualiscope'),
                'calculation' => get_string('check_doc_stats_calc', 'local_qualiscope'),
                'badge' => 'primary',
            ],
            'engagement_exists' => [
                'name' => get_string('check_doc_engagement_name', 'local_qualiscope'),
                'moodle_target' => get_string('check_doc_engagement_target', 'local_qualiscope'),
                'calculation' => get_string('check_doc_engagement_calc', 'local_qualiscope'),
                'badge' => 'primary',
            ],
            'alignment_exists' => [
                'name' => get_string('check_doc_alignment_name', 'local_qualiscope'),
                'moodle_target' => get_string('check_doc_alignment_target', 'local_qualiscope'),
                'calculation' => get_string('check_doc_alignment_calc', 'local_qualiscope'),
                'badge' => 'primary',
            ],
            'accessibility_exists' => [
                'name' => get_string('check_doc_accessibility_name', 'local_qualiscope'),
                'moodle_target' => get_string('check_doc_accessibility_target', 'local_qualiscope'),
                'calculation' => get_string('check_doc_accessibility_calc', 'local_qualiscope'),
                'badge' => 'info',
            ],
            'wcag_images_exists' => [
                'name' => get_string('check_doc_wcag_images_name', 'local_qualiscope'),
                'moodle_target' => get_string('check_doc_wcag_images_target', 'local_qualiscope'),
                'calculation' => get_string('check_doc_wcag_images_calc', 'local_qualiscope'),
                'badge' => 'info',
            ],
            'wcag_media_exists' => [
                'name' => get_string('check_doc_wcag_media_name', 'local_qualiscope'),
                'moodle_target' => get_string('check_doc_wcag_media_target', 'local_qualiscope'),
                'calculation' => get_string('check_doc_wcag_media_calc', 'local_qualiscope'),
                'badge' => 'info',
            ],
            'wcag_headings_exists' => [
                'name' => get_string('check_doc_wcag_headings_name', 'local_qualiscope'),
                'moodle_target' => get_string('check_doc_wcag_headings_target', 'local_qualiscope'),
                'calculation' => get_string('check_doc_wcag_headings_calc', 'local_qualiscope'),
                'badge' => 'info',
            ],
            'wcag_contrast_exists' => [
                'name' => get_string('check_doc_wcag_contrast_name', 'local_qualiscope'),
                'moodle_target' => get_string('check_doc_wcag_contrast_target', 'local_qualiscope'),
                'calculation' => get_string('check_doc_wcag_contrast_calc', 'local_qualiscope'),
                'badge' => 'info',
            ],
            'manual' => [
                'name' => get_string('check_doc_manual_name', 'local_qualiscope'),
                'moodle_target' => get_string('check_doc_manual_target', 'local_qualiscope'),
                'calculation' => get_string('check_doc_manual_calc', 'local_qualiscope'),
                'badge' => 'secondary',
            ],
        ];

        return $docs[$checktype] ?? [
            'name' => $checktype,
            'moodle_target' => get_string('check_doc_default_target', 'local_qualiscope'),
            'calculation' => get_string('check_doc_default_calc', 'local_qualiscope'),
            'badge' => 'secondary',
        ];
    }

    /**
     * Build the structured guide dataset for a given referential.
     *
     * @param int $referentialid
     * @return array
     */
    public static function get_referential_guide_data(int $referentialid): array {
        global $DB;

        $referential = $DB->get_record('local_qualiscope_referentials', ['id' => $referentialid], '*', MUST_EXIST);
        $criteria = $DB->get_records('local_qualiscope_criteria', ['referential_id' => $referentialid], 'number ASC');

        $criteriadata = [];

        foreach ($criteria as $criterion) {
            $indicators = $DB->get_records('local_qualiscope_indicators', ['criterion_id' => $criterion->id], 'number ASC');
            $indicatorsdata = [];

            $criteriontotalweight = 0;
            $criterionautocheckcount = 0;
            $criterionmanualcount = 0;

            foreach ($indicators as $indicator) {
                $checks = $DB->get_records('local_qualiscope_checks', ['indicator_id' => $indicator->id], 'id ASC');
                $checksdata = [];

                $indicatortotalweight = 0;
                $indicatorautocheckcount = 0;
                $indicatormanualcount = 0;

                foreach ($checks as $check) {
                    $doc = self::get_check_type_documentation($check->type);
                    $isautomatic = (bool) $check->automatic;
                    $weight = (int) ($check->weight ?: 1);

                    if ($isautomatic) {
                        $indicatortotalweight += $weight;
                        $indicatorautocheckcount++;
                        $criteriontotalweight += $weight;
                        $criterionautocheckcount++;
                    } else {
                        $indicatormanualcount++;
                        $criterionmanualcount++;
                    }

                    $checksdata[] = [
                        'id' => $check->id,
                        'name' => $check->name,
                        'description' => $check->description,
                        'type' => $check->type,
                        'weight' => $weight,
                        'isautomatic' => $isautomatic,
                        'ismanual' => !$isautomatic,
                        'doc_name' => $doc['name'],
                        'doc_target' => $doc['moodle_target'],
                        'doc_calc' => $doc['calculation'],
                        'doc_badge' => $doc['badge'],
                    ];
                }

                $moodlelevel = $indicator->moodle_level ?? 'partial';
                $moodlelevelbadge = 'secondary';
                $moodlelevellabel = get_string('moodle_level_manual', 'local_qualiscope');

                if ($moodlelevel === 'strong') {
                    $moodlelevelbadge = 'success';
                    $moodlelevellabel = get_string('moodle_level_strong', 'local_qualiscope');
                } else if ($moodlelevel === 'partial') {
                    $moodlelevelbadge = 'warning';
                    $moodlelevellabel = get_string('moodle_level_partial', 'local_qualiscope');
                } else if ($moodlelevel === 'documentary') {
                    $moodlelevelbadge = 'info';
                    $moodlelevellabel = get_string('moodle_level_documentary', 'local_qualiscope');
                }

                $scope = $indicator->scope ?? 'course';
                $scopelabel = $scope === 'course' ? get_string('scope_course', 'local_qualiscope') : get_string('scope_organisation', 'local_qualiscope');

                $indicatorsdata[] = [
                    'id' => $indicator->id,
                    'number' => $indicator->number,
                    'title' => $indicator->title,
                    'description' => $indicator->description,
                    'scope' => $scope,
                    'scopelabel' => $scopelabel,
                    'moodle_level' => $moodlelevel,
                    'moodlelevelbadge' => $moodlelevelbadge,
                    'moodlelevellabel' => $moodlelevellabel,
                    'checks' => $checksdata,
                    'checkscount' => count($checksdata),
                    'autocheckcount' => $indicatorautocheckcount,
                    'manualcount' => $indicatormanualcount,
                    'has_auto_checks' => $indicatorautocheckcount > 0,
                    'is_manual_only' => $indicatorautocheckcount === 0,
                    'totalweight' => $indicatortotalweight,
                ];
            }

            $criteriadata[] = [
                'id' => $criterion->id,
                'number' => $criterion->number,
                'title' => $criterion->title,
                'description' => $criterion->description,
                'indicators' => $indicatorsdata,
                'indicatorscount' => count($indicatorsdata),
                'autocheckcount' => $criterionautocheckcount,
                'manualcount' => $criterionmanualcount,
                'totalweight' => $criteriontotalweight,
                'has_auto_checks' => $criterionautocheckcount > 0,
            ];
        }

        return [
            'referential' => $referential,
            'criteria' => $criteriadata,
            'totalcriteria' => count($criteriadata),
        ];
    }
}
