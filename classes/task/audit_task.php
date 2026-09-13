<?php

namespace local_qualiscope\task;

defined('MOODLE_INTERNAL') || die();

class audit_task extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('audit_task', 'local_qualiscope');
    }

    public function execute(): void {
        global $DB;

        $campaigns = $DB->get_records('local_qualiopi_campaigns', ['timecompleted' => 0], 'id ASC');

        foreach ($campaigns as $campaign) {
            $this->process_campaign($campaign);
        }
    }

    private function process_campaign(object $campaign): void {
        global $DB;

        $scopeids = \local_qualiscope\analyser\course_analyser::get_campaign_course_ids($campaign);

        foreach ($scopeids as $courseid) {
            $analyser = new \local_qualiscope\analyser\course_analyser($courseid, $campaign->id, (int) $campaign->referential_id);
            $analyser->run();
            $analyser->save_results($campaign->id);
        }

        $DB->update_record('local_qualiopi_campaigns', [
            'id' => $campaign->id,
            'timecompleted' => time(),
            'timemodified' => time(),
        ]);
    }
}
