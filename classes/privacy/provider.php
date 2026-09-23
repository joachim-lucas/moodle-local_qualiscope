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
