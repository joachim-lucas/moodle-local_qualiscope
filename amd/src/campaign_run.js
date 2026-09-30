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
 * Real-time progress loop of a QualiScope campaign run.
 *
 * Each course of the campaign is analysed through the local_qualiscope_run_campaign_course
 * web service, so no session key has to travel in a URL.
 *
 * @module     local_qualiscope/campaign_run
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['local_qualiscope/api'], function(Api) {
    'use strict';

    return {
        /**
         * Walk the campaign course by course, updating the progress bar as it goes.
         *
         * @param {Object} config Campaign id, course list, target url and translated strings.
         * @return {void}
         */
        init: function(config) {
            var courses = config.courses || [];
            var strings = config.strings || {};
            var total = courses.length;
            var currentIndex = 0;

            var progressBar = document.getElementById('runProgressBar');
            var progressText = document.getElementById('runProgressText');
            var currentCourseEl = document.getElementById('runCurrentCourse');
            var spinner = document.getElementById('runSpinner');
            var titleEl = document.getElementById('runTitle');

            if (total === 0) {
                window.location.href = config.targeturl;
                return;
            }

            /**
             * Render the completion state and move on to the campaign results.
             *
             * @return {void}
             */
            var finish = function() {
                progressBar.style.width = '100%';
                progressBar.textContent = '100%';
                progressBar.classList.remove('progress-bar-animated');
                progressBar.classList.add('bg-success');
                if (spinner) {
                    spinner.style.display = 'none';
                }
                if (titleEl) {
                    titleEl.textContent = strings.finished;
                }
                currentCourseEl.textContent = strings.redirecting;
                setTimeout(function() {
                    window.location.href = config.targeturl;
                }, 600);
            };

            /**
             * Analyse the next course of the campaign, then queue the following one.
             *
             * @return {void}
             */
            var processNext = function() {
                if (currentIndex >= total) {
                    finish();
                    return;
                }

                var course = courses[currentIndex];
                var isFinish = (currentIndex === total - 1) ? 1 : 0;
                var pct = Math.round((currentIndex / total) * 100);

                progressBar.style.width = pct + '%';
                progressBar.textContent = pct + '%';
                progressText.textContent = strings.courseprogress
                    .replace('{$current}', currentIndex + 1)
                    .replace('{$total}', total)
                    .replace('{$percentage}', pct);
                currentCourseEl.textContent = course.fullname;

                /**
                 * Advance the progress bar and continue with the next course.
                 *
                 * @return {void}
                 */
                var advance = function() {
                    currentIndex++;
                    var nextPct = Math.round((currentIndex / total) * 100);
                    progressBar.style.width = nextPct + '%';
                    progressBar.textContent = nextPct + '%';
                    processNext();
                };

                // A failing course must not abort the whole campaign.
                Api.runCampaignCourse(config.campaignid, course.id, isFinish)
                    .then(function() {
                        return advance();
                    })
                    .catch(function() {
                        return advance();
                    });
            };

            setTimeout(processNext, 200);
        }
    };
});
