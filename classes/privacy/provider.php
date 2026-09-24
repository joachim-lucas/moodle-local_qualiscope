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
 * QualiScope Provider class.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qualiscope\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;

/**
 * Privacy API implementation for local_qualiscope.
 *
 * All personal data lives in the system context: the plugin only stores
 * the userids of campaign creators, evidence uploaders and action creators.
 *
 * @package local_qualiscope
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describes the personal data stored by the plugin.
     *
     * @param collection $collection The metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_qualiscope_campaigns',
            [
                'name' => 'privacy:metadata:campaigns:name',
                'userid' => 'privacy:metadata:userid',
            ],
            'privacy:metadata:campaigns'
        );

        $collection->add_database_table(
            'local_qualiscope_evidences',
            [
                'title' => 'privacy:metadata:evidences:title',
                'annotation' => 'privacy:metadata:annotation',
                'userid' => 'privacy:metadata:userid',
            ],
            'privacy:metadata:evidences'
        );

        $collection->add_database_table(
            'local_qualiscope_actions',
            [
                'title' => 'privacy:metadata:actions:title',
                'responsible' => 'privacy:metadata:responsible',
                'userid' => 'privacy:metadata:userid',
            ],
            'privacy:metadata:actions'
        );

        return $collection;
    }

    /**
     * Returns the contexts holding personal data for the user.
     *
     * @param int $userid The user id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        if (self::userhasdata($userid)) {
            $contextlist->add_system_context();
        }

        return $contextlist;
    }

    /**
     * Whether the given user has any personal data stored by the plugin.
     *
     * @param int $userid The user id.
     * @return bool
     */
    private static function userhasdata(int $userid): bool {
        global $DB;

        return $DB->record_exists('local_qualiscope_campaigns', ['userid' => $userid])
            || $DB->record_exists('local_qualiscope_evidences', ['userid' => $userid])
            || $DB->record_exists('local_qualiscope_actions', ['userid' => $userid]);
    }

    /**
     * Exports personal data for the user in the given contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $context = \context_system::instance();

        $campaigns = $DB->get_records('local_qualiscope_campaigns', ['userid' => $userid]);
        foreach ($campaigns as $campaign) {
            $data = new \stdClass();
            $data->name = $campaign->name;
            $data->scope = $campaign->scope;
            $data->timecreated = transform::datetime($campaign->timecreated);
            $data->timemodified = transform::datetime($campaign->timemodified);
            $data->timecompleted = $campaign->timecompleted ? transform::datetime($campaign->timecompleted) : null;

            writer::with_context($context)->export_data(
                [get_string('privacy:metadata:campaigns', 'local_qualiscope'), '#' . $campaign->id],
                $data
            );
        }

        $evidences = $DB->get_records('local_qualiscope_evidences', ['userid' => $userid]);
        foreach ($evidences as $evidence) {
            $data = new \stdClass();
            $data->type = $evidence->type;
            $data->source = $evidence->source;
            $data->title = $evidence->title;
            $data->description = $evidence->description;
            $data->annotation = $evidence->annotation;
            $data->timecreated = transform::datetime($evidence->timecreated);
            $data->timemodified = transform::datetime($evidence->timemodified);

            writer::with_context($context)->export_data(
                [get_string('privacy:metadata:evidences', 'local_qualiscope'), '#' . $evidence->id],
                $data
            );
        }

        $actions = $DB->get_records('local_qualiscope_actions', ['userid' => $userid]);
        foreach ($actions as $action) {
            $data = new \stdClass();
            $data->title = $action->title;
            $data->description = $action->description;
            $data->responsible = $action->responsible;
            $data->duedate = $action->duedate ? transform::datetime($action->duedate) : null;
            $data->status = $action->status;
            $data->timecreated = transform::datetime($action->timecreated);
            $data->timemodified = transform::datetime($action->timemodified);

            writer::with_context($context)->export_data(
                [get_string('privacy:metadata:actions', 'local_qualiscope'), '#' . $action->id],
                $data
            );
        }
    }

    /**
     * Deletes personal data for all users in the given context.
     *
     * @param \context $context The context to purge.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        $DB->delete_records('local_qualiscope_evidences');
        $DB->delete_records('local_qualiscope_actions');
        $DB->delete_records('local_qualiscope_results');
        $DB->delete_records('local_qualiscope_campaigns');
    }

    /**
     * Deletes personal data for the user in the given contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        $campaignids = $DB->get_fieldset_select('local_qualiscope_campaigns', 'id', 'userid = :userid', ['userid' => $userid]);
        if (!empty($campaignids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($campaignids, SQL_PARAMS_NAMED, 'campaign');
            $inparams['userid'] = $userid;

            $resultids = $DB->get_fieldset_sql(
                "SELECT id FROM {local_qualiscope_results} WHERE campaign_id $insql",
                $inparams
            );

            if (!empty($resultids)) {
                [$rsql, $rparams] = $DB->get_in_or_equal($resultids, SQL_PARAMS_NAMED, 'result');
                $DB->delete_records_select('local_qualiscope_evidences', "result_id $rsql", $rparams);
                $DB->delete_records_select('local_qualiscope_actions', "result_id $rsql", $rparams);
                $DB->delete_records_select('local_qualiscope_results', "id $rsql", $rparams);
            }

            $DB->delete_records_select('local_qualiscope_campaigns', 'userid = :userid', ['userid' => $userid]);
        }

        $DB->delete_records('local_qualiscope_evidences', ['userid' => $userid]);
        $DB->delete_records('local_qualiscope_actions', ['userid' => $userid]);
    }
}
