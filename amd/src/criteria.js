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
 * Criteria accordion behaviour for the QualiScope dashboard.
 *
 * @module     local_qualiscope/criteria
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['theme_boost/bootstrap/collapse'], function(Collapse) {

    'use strict';

    /**
     * Whether every pane is currently visible.
     *
     * @param {Element[]} panes
     * @return {boolean}
     */
    function allOpen(panes) {
        return panes.every(function(pane) {
            return pane.classList.contains('show');
        });
    }

    /**
     * Refresh the toggle-all button label and style.
     *
     * @param {Element|null} btn
     * @param {Element[]} panes
     */
    function updateButton(btn, panes) {
        if (!btn) {
            return;
        }
        if (panes.length && allOpen(panes)) {
            btn.textContent = btn.dataset.labelClose;
            btn.classList.remove('btn-outline-primary');
            btn.classList.add('btn-primary');
        } else {
            btn.textContent = btn.dataset.labelOpen;
            btn.classList.add('btn-outline-primary');
            btn.classList.remove('btn-primary');
        }
    }

    return {
        /**
         * Initialise the accordion.
         */
        init: function() {
            var container = document.querySelector('.qualiscope-dashboard');
            if (!container) {
                return;
            }

            var accordion = container.querySelector('[data-qualiscope="criteria-accordion"]');
            if (!accordion) {
                return;
            }

            var panes = Array.prototype.slice.call(accordion.querySelectorAll('.collapse'));
            if (!panes.length) {
                return;
            }

            var btn = container.querySelector('[data-qualiscope="criteria-toggleall"]');
            var openingAll = false;

            // Only one criterion opened at a time when toggled manually.
            accordion.addEventListener('show.bs.collapse', function(e) {
                if (openingAll) {
                    return;
                }
                panes.forEach(function(pane) {
                    if (pane !== e.target && pane.classList.contains('show')) {
                        Collapse.getOrCreateInstance(pane).hide();
                    }
                });
            });

            accordion.addEventListener('shown.bs.collapse', function() {
                updateButton(btn, panes);
            });
            accordion.addEventListener('hidden.bs.collapse', function() {
                updateButton(btn, panes);
            });

            if (btn) {
                btn.addEventListener('click', function() {
                    var open = allOpen(panes);

                    openingAll = !open;
                    panes.forEach(function(pane) {
                        var instance = Collapse.getOrCreateInstance(pane);
                        if (open) {
                            instance.hide();
                        } else if (!pane.classList.contains('show')) {
                            instance.show();
                        }
                    });
                    openingAll = false;

                    updateButton(btn, panes);
                });
            }
        }
    };
});