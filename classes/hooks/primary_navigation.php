<?php
// This file is part of the QualiScope plugin for Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * QualiScope Primary Navigation class.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_qualiscope\hooks;


/**
 * Adds the campaigns link to the site primary navigation.
 */
class primary_navigation {
    /**
     * Callback for \core\hook\navigation\primary_extend.
     *
     * @param \core\hook\navigation\primary_extend $hook
     */
    public static function extend(\core\hook\navigation\primary_extend $hook): void {
        if (!isloggedin() || isguestuser()) {
            return;
        }

        if (!has_capability('local/qualiscope:managecampaigns', \context_system::instance())) {
            return;
        }

        $view = $hook->get_primaryview();
        $view->add(
            get_string('nav_campaigns', 'local_qualiscope'),
            new \moodle_url('/local/qualiscope/campaigns.php'),
            \core\navigation\navigation_node::TYPE_CUSTOM,
            null,
            'qualiscope-campaigns'
        );
    }
}
