// This file is part of Moodle - http://moodle.org/
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
 * Web service wrappers for the QualiScope Ajax endpoints.
 *
 * @module     local_qualiscope/api
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax'], function(Ajax) {
    'use strict';

    return {
        /**
         * Run the analysis for a course within a campaign.
         *
         * @param {number} courseid
         * @param {number} campaignid
         * @return {Promise}
         */
        runAnalysis: function(courseid, campaignid) {
            return Ajax.call([{
                methodname: 'local_qualiscope_run_analysis',
                args: {
                    courseid: courseid,
                    campaignid: campaignid
                }
            }])[0];
        },

        /**
         * Attach an evidence annotation to an analysis result.
         *
         * @param {number} resultid
         * @param {string} title
         * @param {string} annotation
         * @param {string} externalurl
         * @return {Promise}
         */
        addEvidence: function(resultid, title, annotation, externalurl) {
            return Ajax.call([{
                methodname: 'local_qualiscope_add_evidence',
                args: {
                    resultid: resultid,
                    title: title,
                    annotation: annotation,
                    externalurl: externalurl
                }
            }])[0];
        },

        /**
         * Create a corrective action linked to an analysis result.
         *
         * @param {number} resultid
         * @param {number} campaignid
         * @param {number} courseid
         * @param {string} title
         * @param {string} responsible
         * @param {number} duedate
         * @param {number} priority
         * @return {Promise}
         */
        createAction: function(resultid, campaignid, courseid, title, responsible, duedate, priority) {
            return Ajax.call([{
                methodname: 'local_qualiscope_create_action',
                args: {
                    resultid: resultid,
                    campaignid: campaignid,
                    courseid: courseid,
                    title: title,
                    responsible: responsible,
                    duedate: duedate,
                    priority: priority
                }
            }])[0];
        }
    };
});
