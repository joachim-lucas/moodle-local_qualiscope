<?php

namespace local_qualiscope\privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy API implementation for local_qualiscope.
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
                'userid' => 'privacy:metadata:userid',
            ],
            'privacy:metadata:campaigns'
        );

        $collection->add_database_table(
            'local_qualiscope_evidences',
            [
                'userid' => 'privacy:metadata:userid',
            ],
            'privacy:metadata:evidences'
        );

        $collection->add_database_table(
            'local_qualiscope_actions',
            [
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
        return new contextlist();
    }

    /**
     * Exports personal data for the user in the given contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
    }

    /**
     * Deletes personal data for all users in the given context.
     *
     * @param \context $context The context to purge.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
    }

    /**
     * Deletes personal data for the user in the given contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
    }
}
