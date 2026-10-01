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
 * QualiScope Evidence Upload Form class.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qualiscope\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form used to attach a documentary evidence file or an external link to an analysis result.
 *
 * @package local_qualiscope
 */
class evidence_upload extends \moodleform {
    /**
     * File extensions accepted as documentary evidence.
     *
     * Active content (html, svg, js, ...) is refused on purpose so that an uploaded file can
     * never be interpreted in the Moodle origin.
     *
     * @var string[]
     */
    public const ACCEPTED_TYPES = [
        '.pdf',
        '.doc',
        '.docx',
        '.odt',
        '.rtf',
        '.txt',
        '.csv',
        '.xls',
        '.xlsx',
        '.ods',
        '.ppt',
        '.pptx',
        '.odp',
        '.jpg',
        '.jpeg',
        '.png',
        '.gif',
        '.webp',
        '.zip',
    ];

    /**
     * Defines the form structure.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'resultid', $this->_customdata['resultid']);
        $mform->setType('resultid', PARAM_INT);

        $mform->addElement('text', 'title', get_string('upload_title', 'local_qualiscope'), [
            'required' => true,
            'maxlength' => 255,
        ]);
        $mform->setType('title', PARAM_TEXT);

        $mform->addElement(
            'filemanager',
            'evidencefile',
            get_string('upload_file', 'local_qualiscope'),
            get_string('upload_filetypes_desc', 'local_qualiscope'),
            [
                'maxfiles' => 1,
                'subdirs' => 0,
                'maxbytes' => $this->_customdata['maxbytes'],
                'accepted_types' => self::ACCEPTED_TYPES,
            ]
        );
        $mform->setDefault('evidencefile', file_get_submitted_draft_itemid('evidencefile'));

        $mform->addElement('url', 'externalurl', get_string('upload_external_url', 'local_qualiscope'), [
            'optional' => true,
            'maxlength' => 1333,
        ]);
        $mform->setType('externalurl', PARAM_URL);

        $mform->addElement('textarea', 'annotation', get_string('upload_annotation', 'local_qualiscope'), [
            'rows' => 3,
            'maxlength' => 1000,
        ]);
        // A textarea defaults to PARAM_RAW; the annotation is plain text, and
        // PARAM_TEXT keeps the line breaks while dropping any markup.
        $mform->setType('annotation', PARAM_TEXT);

        $this->add_action_buttons(true, get_string('upload_submit', 'local_qualiscope'));
    }

    /**
     * Validates the submitted data.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Error messages keyed by element name.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($data['externalurl']) && empty($files['evidencefile'])) {
            $errors['evidencefile'] = get_string('upload_evidence_required', 'local_qualiscope');
        }

        return $errors;
    }
}
