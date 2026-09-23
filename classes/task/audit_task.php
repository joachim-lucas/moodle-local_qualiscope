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
 * QualiScope Audit Task class.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_qualiscope\task;


/**
 * Scheduled task that runs pending audit campaigns in the background.
 *
 * @package local_qualiscope
 */
class audit_task extends \core\task\scheduled_task {
    /**
     * Returns the human-readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('audit_task', 'local_qualiscope');
    }

    /**
     * Processes every campaign awaiting completion.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        $campaigns = $DB->get_records('local_qualiscope_campaigns', ['timecompleted' => 0], 'id ASC');

        foreach ($campaigns as $campaign) {
            $this->process_campaign($campaign);
        }
    }

    /**
     * Audits every course in the campaign scope and marks it completed.
     *
     * @param object $campaign Campaign record.
     * @return void
     */
    private function process_campaign(object $campaign): void {
        global $DB;

        $scopeids = \local_qualiscope\analyser\course_analyser::get_campaign_course_ids($campaign);

        foreach ($scopeids as $courseid) {
            $analyser = new \local_qualiscope\analyser\course_analyser($courseid, $campaign->id, (int) $campaign->referential_id);
            $analyser->run();
            $analyser->save_results($campaign->id);
        }

        $DB->update_record('local_qualiscope_campaigns', [
            'id' => $campaign->id,
            'timecompleted' => time(),
            'timemodified' => time(),
        ]);
    }
}
