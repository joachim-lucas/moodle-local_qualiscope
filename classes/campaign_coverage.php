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
 * QualiScope Campaign Coverage class.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qualiscope;

/**
 * Reads the coverage of the campaigns for the campaign list.
 *
 * @package local_qualiscope
 */
class campaign_coverage {
    /**
     * Returns the analysed course count and the average coverage of every campaign.
     *
     * A campaign holds one result row per course and check, so loading them to
     * group them in PHP costs campaigns * courses * checks rows per visit. The
     * nested aggregation below returns a single row per campaign instead.
     *
     * The inner query produces the coverage of each course, the outer one
     * averages it, which is what the list displays: the mean of the per course
     * percentages, not the ratio over every result. A course whose checks are
     * all not applicable is counted as analysed but left out of the average, and
     * the division is forced to a float so the result does not depend on how the
     * database rounds an integer division.
     *
     * @return array Campaign id as key, with a courses count and an average percentage,
     *               the latter null when no course of the campaign was auditable.
     */
    public static function for_all_campaigns(): array {
        global $DB;

        $rows = $DB->get_records_sql(
            "SELECT campaign_id,
                    COUNT(*) AS courses,
                    AVG(CASE WHEN applicable > 0 THEN weighted * 100.0 / applicable END) AS avgcoverage
               FROM (SELECT campaign_id,
                            courseid,
                            COUNT(CASE WHEN status <> 'na' THEN 1 END) AS applicable,
                            SUM(CASE WHEN status <> 'na' THEN CASE WHEN ratio > 0 THEN ratio
                                    WHEN status = 'detected' THEN 1 ELSE 0 END
                                    ELSE 0 END) AS weighted
                       FROM {local_qualiscope_results}
                   GROUP BY campaign_id, courseid) q
           GROUP BY campaign_id"
        );

        $coverages = [];
        foreach ($rows as $row) {
            $coverages[(int) $row->campaign_id] = [
                'courses' => (int) $row->courses,
                'avgcoverage' => $row->avgcoverage === null
                    ? null
                    : (int) round((float) $row->avgcoverage),
            ];
        }

        return $coverages;
    }
}
