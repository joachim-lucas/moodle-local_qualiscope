<?php

namespace local_qualiscope\task;

defined('MOODLE_INTERNAL') || die();

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
