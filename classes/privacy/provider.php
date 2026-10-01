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
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy API implementation for local_qualiscope.
 *
 * Campaigns are site wide and live in the system context. Results, corrective actions and
 * evidences belong to a course, and the evidence files are stored in the course context, so
 * the provider only ever acts on the context it is given.
 *
 * @package local_qualiscope
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    core_userlist_provider {
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
                'source' => 'privacy:metadata:evidences:source',
                'title' => 'privacy:metadata:evidences:title',
                'description' => 'privacy:metadata:evidences:description',
                'externalurl' => 'privacy:metadata:evidences:externalurl',
                'filename' => 'privacy:metadata:evidences:filename',
                'annotation' => 'privacy:metadata:annotation',
                'userid' => 'privacy:metadata:userid',
            ],
            'privacy:metadata:evidences'
        );

        $collection->add_database_table(
            'local_qualiscope_actions',
            [
                'title' => 'privacy:metadata:actions:title',
                'description' => 'privacy:metadata:actions:description',
                'responsible' => 'privacy:metadata:responsible',
                'userid' => 'privacy:metadata:userid',
            ],
            'privacy:metadata:actions'
        );

        $collection->add_subsystem_link(
            'core_files',
            [
                ['filearea' => 'evidence'],
            ],
            'privacy:metadata:evidences:files'
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
        global $DB;

        $contextlist = new contextlist();

        if ($DB->record_exists('local_qualiscope_campaigns', ['userid' => $userid])) {
            $contextlist->add_system_context();
        }

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {local_qualiscope_actions} a
                    ON a.courseid = ctx.instanceid
                 WHERE ctx.contextlevel = :courselevel AND a.courseid > 0 AND a.userid = :userid1";

        $contextlist->add_from_sql($sql, [
            'courselevel' => CONTEXT_COURSE,
            'userid1' => $userid,
        ]);

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {local_qualiscope_results} r
                    ON r.courseid = ctx.instanceid
                  JOIN {local_qualiscope_evidences} e
                    ON e.result_id = r.id
                 WHERE ctx.contextlevel = :courselevel AND r.courseid > 0 AND e.userid = :userid2";

        $contextlist->add_from_sql($sql, [
            'courselevel' => CONTEXT_COURSE,
            'userid2' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Splits a context list into the system context and the list of course ids it covers.
     *
     * @param array $contexts The contexts, keyed by context id.
     * @return array With a 'system' boolean and a 'courses' list of course ids.
     */
    private static function split_contexts(array $contexts): array {
        $systemcontext = \context_system::instance();
        $courseids = [];

        foreach ($contexts as $context) {
            if ($context->id == $systemcontext->id) {
                $system = true;
                continue;
            }
            if ($context->contextlevel == CONTEXT_COURSE) {
                $courseids[] = (int) $context->instanceid;
            }
        }

        return [
            'system' => $system ?? false,
            'courses' => array_values(array_unique($courseids)),
        ];
    }

    /**
     * Exports personal data for the user in the given contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $scopes = self::split_contexts($contextlist->get_contexts());

        if ($scopes['system']) {
            self::export_campaigns($userid, \context_system::instance());
        }

        if (empty($scopes['courses'])) {
            return;
        }

        [$csql, $params] = $DB->get_in_or_equal($scopes['courses'], SQL_PARAMS_NAMED, 'course');
        $params['userid'] = $userid;

        $evidences = $DB->get_records_sql(
            "SELECT e.*, r.courseid
               FROM {local_qualiscope_evidences} e
               JOIN {local_qualiscope_results} r ON r.id = e.result_id
              WHERE e.userid = :userid AND r.courseid $csql",
            $params
        );

        $fs = get_file_storage();
        foreach ($evidences as $evidence) {
            $context = \context_course::instance($evidence->courseid);

            $data = new \stdClass();
            $data->type = $evidence->type;
            $data->source = $evidence->source;
            $data->title = $evidence->title;
            $data->description = $evidence->description;
            $data->externalurl = $evidence->externalurl;
            $data->filename = $evidence->filename;
            $data->annotation = $evidence->annotation;
            $data->timecreated = transform::datetime($evidence->timecreated);
            $data->timemodified = transform::datetime($evidence->timemodified);

            $path = [get_string('privacy:metadata:evidences', 'local_qualiscope'), '#' . $evidence->id];

            writer::with_context($context)->export_data($path, $data);

            $file = self::get_evidence_file($fs, $evidence);
            if ($file) {
                writer::with_context($context)->export_file($path, $file);
            }
        }

        $actions = $DB->get_records_select(
            'local_qualiscope_actions',
            "userid = :userid AND courseid $csql",
            $params
        );

        foreach ($actions as $action) {
            $context = \context_course::instance($action->courseid);

            $data = new \stdClass();
            $data->title = $action->title;
            $data->description = $action->description;
            $data->responsible = $action->responsible;
            $data->duedate = $action->duedate ? transform::datetime($action->duedate) : null;
            $data->priority = $action->priority;
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
     * Exports the campaigns created by a user in the system context.
     *
     * @param int $userid The user id.
     * @param \context $context The system context.
     */
    private static function export_campaigns(int $userid, \context $context) {
        global $DB;

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
    }

    /**
     * Deletes personal data for all users in the given context.
     *
     * @param \context $context The context to purge.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel == CONTEXT_SYSTEM) {
            self::delete_results($DB->get_fieldset('local_qualiscope_results', 'id'));
            $DB->delete_records('local_qualiscope_actions');
            $DB->delete_records('local_qualiscope_campaigns');
            return;
        }

        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        $courseid = (int) $context->instanceid;
        self::delete_results($DB->get_fieldset_select(
            'local_qualiscope_results',
            'id',
            'courseid = :courseid',
            ['courseid' => $courseid]
        ));
        $DB->delete_records('local_qualiscope_actions', ['courseid' => $courseid]);
    }

    /**
     * Deletes personal data for the user in the given contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = $contextlist->get_user()->id;
        $scopes = self::split_contexts($contextlist->get_contexts());

        if ($scopes['system']) {
            self::delete_campaigns_of_users([$userid]);
        }

        self::delete_course_data_of_users([$userid], $scopes['courses']);
    }

    /**
     * Returns the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist to populate.
     */
    public static function get_users_in_context(userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if ($context->contextlevel == CONTEXT_SYSTEM) {
            $userlist->add_from_sql(
                'userid',
                'SELECT DISTINCT userid FROM {local_qualiscope_campaigns}',
                []
            );
            return;
        }

        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        $courseid = (int) $context->instanceid;

        $userlist->add_from_sql(
            'userid',
            'SELECT DISTINCT userid
               FROM {local_qualiscope_actions}
              WHERE courseid = :courseid',
            ['courseid' => $courseid]
        );

        $userlist->add_from_sql(
            'userid',
            'SELECT DISTINCT e.userid
               FROM {local_qualiscope_evidences} e
               JOIN {local_qualiscope_results} r ON r.id = e.result_id
              WHERE r.courseid = :courseid',
            ['courseid' => $courseid]
        );
    }

    /**
     * Deletes multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        $scopes = self::split_contexts([$userlist->get_context()]);

        if ($scopes['system']) {
            self::delete_campaigns_of_users($userids);
        }

        self::delete_course_data_of_users($userids, $scopes['courses']);
    }

    /**
     * Deletes the campaigns of the given users with every result attached to them.
     *
     * @param array $userids The user ids.
     */
    private static function delete_campaigns_of_users(array $userids) {
        global $DB;

        $userids = array_values(array_unique(array_map('intval', $userids)));
        if (empty($userids)) {
            return;
        }

        [$usql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'user');
        $campaignids = $DB->get_fieldset_select('local_qualiscope_campaigns', 'id', "userid $usql", $params);

        if (!empty($campaignids)) {
            [$csql, $cparams] = $DB->get_in_or_equal($campaignids, SQL_PARAMS_NAMED, 'campaign');
            self::delete_results($DB->get_fieldset_select(
                'local_qualiscope_results',
                'id',
                "campaign_id $csql",
                $cparams
            ));
            $DB->delete_records_select('local_qualiscope_actions', "campaign_id $csql", $cparams);
            $DB->delete_records_select('local_qualiscope_campaigns', "id $csql", $cparams);
        }
    }

    /**
     * Deletes the evidences, evidence files and corrective actions the given users own in the given courses.
     *
     * @param array $userids The user ids.
     * @param array $courseids The course ids.
     */
    private static function delete_course_data_of_users(array $userids, array $courseids) {
        global $DB;

        $userids = array_values(array_unique(array_map('intval', $userids)));
        $courseids = array_values(array_unique(array_map('intval', $courseids)));

        if (empty($userids) || empty($courseids)) {
            return;
        }

        [$usql, $uparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'user');
        [$csql, $cparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');
        $params = array_merge($uparams, $cparams);

        $evidenceids = $DB->get_fieldset_sql(
            "SELECT e.id
               FROM {local_qualiscope_evidences} e
               JOIN {local_qualiscope_results} r ON r.id = e.result_id
              WHERE e.userid $usql AND r.courseid $csql",
            $params
        );

        self::delete_evidence_files($evidenceids);

        if (!empty($evidenceids)) {
            [$esql, $eparams] = $DB->get_in_or_equal($evidenceids, SQL_PARAMS_NAMED, 'evidence');
            $DB->delete_records_select('local_qualiscope_evidences', "id $esql", $eparams);
        }

        $DB->delete_records_select('local_qualiscope_actions', "userid $usql AND courseid $csql", $params);
    }

    /**
     * Deletes the given results with the evidences, evidence files and actions attached to them.
     *
     * @param array $resultids The result ids.
     */
    private static function delete_results(array $resultids) {
        global $DB;

        $resultids = array_values(array_unique(array_filter(array_map('intval', $resultids))));
        if (empty($resultids)) {
            return;
        }

        [$rsql, $params] = $DB->get_in_or_equal($resultids, SQL_PARAMS_NAMED, 'result');

        self::delete_evidence_files($DB->get_fieldset_select(
            'local_qualiscope_evidences',
            'id',
            "result_id $rsql",
            $params
        ));

        $DB->delete_records_select('local_qualiscope_evidences', "result_id $rsql", $params);
        $DB->delete_records_select('local_qualiscope_actions', "result_id $rsql", $params);
        $DB->delete_records_select('local_qualiscope_results', "id $rsql", $params);
    }

    /**
     * Deletes the files attached to the given evidence records.
     *
     * @param array $evidenceids The evidence ids.
     */
    private static function delete_evidence_files(array $evidenceids) {
        global $DB;

        $evidenceids = array_values(array_unique(array_filter(array_map('intval', $evidenceids))));
        if (empty($evidenceids)) {
            return;
        }

        [$esql, $params] = $DB->get_in_or_equal($evidenceids, SQL_PARAMS_NAMED, 'evidence');

        $rows = $DB->get_records_sql(
            "SELECT e.id, e.result_id, e.filepath, e.filename, r.courseid
               FROM {local_qualiscope_evidences} e
               JOIN {local_qualiscope_results} r ON r.id = e.result_id
              WHERE e.id $esql",
            $params
        );

        $fs = get_file_storage();
        foreach ($rows as $row) {
            $file = self::get_evidence_file($fs, $row);
            if ($file) {
                $file->delete();
            }
        }
    }

    /**
     * Returns the stored file of an evidence record, or null when there is none.
     *
     * @param \stored_file $fs The file storage.
     * @param \stdClass $evidence The evidence row, with a courseid property.
     * @return \stored_file|null
     */
    private static function get_evidence_file($fs, $evidence) {
        if (empty($evidence->filename) || (int) $evidence->courseid <= 0) {
            return null;
        }

        $filepath = empty($evidence->filepath) ? '/' : $evidence->filepath;
        $context = \context_course::instance((int) $evidence->courseid);

        return $fs->get_file(
            $context->id,
            'local_qualiscope',
            'evidence',
            (int) $evidence->result_id,
            $filepath,
            $evidence->filename
        );
    }
}
