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
 * QualiScope Campaign Create Form class.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qualiscope\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Form used to create an audit campaign.
 *
 * The course picker is the core course element, which searches the courses
 * through core/form-course-selector instead of shipping every course of the
 * site to the browser. Course categories are few enough to be listed, so they
 * stay a plain multiple select.
 *
 * @package local_qualiscope
 */
class campaign_create extends \moodleform {
    /**
     * CSS class of the block holding the category picker.
     *
     * The scope switch in amd/src/forms.js toggles those blocks.
     *
     * @var string
     */
    public const CLASS_CATEGORIES = 'qualiscope-scope-categories';

    /**
     * CSS class of the block holding the course picker.
     *
     * @var string
     */
    public const CLASS_COURSES = 'qualiscope-scope-courses';

    /**
     * Defines the form structure.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'name', get_string('campaign_name', 'local_qualiscope'), [
            'required' => true,
            'maxlength' => 255,
        ]);
        $mform->setType('name', PARAM_TEXT);

        $referentialoptions = [];
        foreach ($this->_customdata['referentials'] as $referential) {
            $referentialoptions[$referential->id] = $referential->name . ' ' . $referential->version;
        }
        $mform->addElement(
            'select',
            'referential_id',
            get_string('campaign_referential', 'local_qualiscope'),
            $referentialoptions
        );
        $mform->setType('referential_id', PARAM_INT);

        $mform->addElement('select', 'scope', get_string('campaign_scope', 'local_qualiscope'), [
            'all' => get_string('campaign_scope_all', 'local_qualiscope'),
            'category' => get_string('campaign_scope_category', 'local_qualiscope'),
            'selected' => get_string('campaign_scope_selected', 'local_qualiscope'),
        ]);
        $mform->setType('scope', PARAM_ALPHA);

        // The two pickers share no name: they used to write into one scopeids[]
        // parameter, so a category id and a course id were indistinguishable.
        $mform->addElement('group', 'scope-categories', get_string('campaign_scope_category', 'local_qualiscope'), [
            $mform->createElement(
                'select',
                'categoryids',
                get_string('campaign_scope_category', 'local_qualiscope'),
                $this->_customdata['categories'],
                'size=5'
            ),
        ], '', false, ['class' => self::CLASS_CATEGORIES]);
        $mform->setType('categoryids', PARAM_INT);

        $mform->addElement('group', 'scope-courses', get_string('campaign_scope_selected', 'local_qualiscope'), [
            $mform->createElement('course', 'scopeids', get_string('campaign_scope_selected', 'local_qualiscope'), [
                'multiple' => true,
                // The front page is not a course to audit.
                'exclude' => SITEID,
            ]),
        ], '', false, ['class' => self::CLASS_COURSES]);
        $mform->setType('scopeids', PARAM_INT);

        $this->add_action_buttons(false, get_string('campaign_submit', 'local_qualiscope'));
    }

    /**
     * Validates the submitted data.
     *
     * A campaign whose scope is a list of categories or of courses is useless
     * with an empty list, and the list is only reachable through the element
     * matching the selected scope, so it is checked here.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Error messages keyed by element name.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (($data['scope'] ?? '') === 'category' && empty($data['categoryids'])) {
            $errors['categoryids'] = get_string('campaign_scope_category_required', 'local_qualiscope');
        }

        if (($data['scope'] ?? '') === 'selected' && empty(self::selected_course_ids($data))) {
            $errors['scopeids'] = get_string('campaign_scope_selected_required', 'local_qualiscope');
        }

        return $errors;
    }

    /**
     * Returns the course ids picked in the autocomplete.
     *
     * Moodle submits the _qf__force_multiselect_submission marker when the
     * multiple picker was left empty, which is not a course id.
     *
     * @param \stdClass|array $data The submitted data.
     * @return int[] The selected course ids.
     */
    public static function selected_course_ids($data): array {
        $submitted = (array) ($data->scopeids ?? []);

        return array_values(array_filter(array_map('intval', $submitted)));
    }
}
