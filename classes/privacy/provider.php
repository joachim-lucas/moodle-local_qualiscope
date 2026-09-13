<?php

namespace local_qualiscope\privacy;

defined('MOODLE_INTERNAL') || die();

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_qualiopi_campaigns',
            [
                'userid' => 'privacy:metadata:userid',
            ],
            'privacy:metadata:campaigns'
        );

        $collection->add_database_table(
            'local_qualiopi_evidences',
            [
                'userid' => 'privacy:metadata:userid',
            ],
            'privacy:metadata:evidences'
        );

        $collection->add_database_table(
            'local_qualiopi_actions',
            [
                'userid' => 'privacy:metadata:userid',
            ],
            'privacy:metadata:actions'
        );

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        return new contextlist();
    }

    public static function export_user_data(approved_contextlist $contextlist) {
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
    }
}
