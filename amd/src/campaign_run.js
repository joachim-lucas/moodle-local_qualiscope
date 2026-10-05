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
            var failed = 0;
            var blocked = 0;

            var progressBar = document.getElementById('runProgressBar');
            var progressText = document.getElementById('runProgressText');
            var currentCourseEl = document.getElementById('runCurrentCourse');
            var spinner = document.getElementById('runSpinner');
            var titleEl = document.getElementById('runTitle');
            var currentCourseClasses = currentCourseEl ? currentCourseEl.className : '';

            if (total === 0) {
                window.location.href = config.targeturl;
                return;
            }

            /**
             * Render the outcome of the run and move on to the campaign results.
             *
             * A run with refused or failed courses must not be announced as a success,
             * otherwise the empty result page looks like a bug instead of a failure.
             *
             * @return {void}
             */
            var finish = function() {
                var incomplete = (failed > 0 || blocked > 0);

                progressBar.style.width = '100%';
                progressBar.textContent = '100%';
                progressBar.classList.remove('progress-bar-animated');
                progressBar.classList.remove('bg-primary');
                progressBar.classList.add(incomplete ? 'bg-danger' : 'bg-success');

                if (spinner) {
                    spinner.style.display = 'none';
                }
                if (titleEl) {
                    titleEl.textContent = incomplete ? strings.partial : strings.finished;
                }
                currentCourseEl.textContent = incomplete
                    ? strings.partialdetail
                        .replace('{$failed}', failed)
                        .replace('{$blocked}', blocked)
                    : strings.redirecting;

                setTimeout(function() {
                    window.location.href = config.targeturl;
                }, 600);
            };

            /**
             * Report a course the service refused to analyse because of the quota.
             *
             * @param {Object} course The course being processed.
             * @return {void}
             */
            var reportBlocked = function(course) {
                blocked++;
                currentCourseEl.className = currentCourseClasses + ' alert-warning';
                currentCourseEl.textContent = strings.courseblocked
                    .replace('{$name}', course.fullname);
            };

            /**
             * Report a course whose analysis did not happen, keeping the cause visible
             * in the browser console and in the panel.
             *
             * @param {Object} course The course being processed.
             * @param {*} cause The rejection reason or unexpected response.
             * @return {void}
             */
            var reportFailure = function(course, cause) {
                failed++;
                if (window.console && window.console.error) {
                    window.console.error('QualiScope: analysis failed for course ' + course.id, cause);
                }
                currentCourseEl.className = currentCourseClasses + ' alert-danger';
                currentCourseEl.textContent = strings.coursefailed
                    .replace('{$name}', course.fullname);
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
                currentCourseEl.className = currentCourseClasses;
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

                // A failing course must not abort the whole campaign, but it must be counted:
                // the final state tells whether the results page is complete or partial.
                Api.runCampaignCourse(config.campaignid, course.id, isFinish)
                    .then(function(response) {
                        if (response && response.status === 'licence_required') {
                            reportBlocked(course);
                        } else if (!response || response.success !== true) {
                            reportFailure(course, response);
                        }
                        return advance();
                    })
                    .catch(function(error) {
                        reportFailure(course, error);
                        return advance();
                    });
            };

            setTimeout(processNext, 200);
        }
    };
});
