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
 * QualiScope Evidence class.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qualiscope;

/**
 * Creates the evidence records and owns their files.
 *
 * Every evidence stores its file in its own file area, itemid being the
 * evidence id. Earlier releases shared one area per result (itemid = result id),
 * which file_save_draft_area_files() makes unusable for anything but the last
 * upload: saving into a shared area drops every stored file that is not part of
 * the new draft, so attaching a second evidence erased the file of the first
 * one, and submitting a link-only evidence erased them all.
 *
 * @package local_qualiscope
 */
class evidence {
    /**
     * File area holding the evidence documents.
     *
     * @var string
     */
    public const FILEAREA = 'evidence';

    /**
     * Returns the single file of a draft area, or null when it holds none.
     *
     * A filemanager element does not report its files through the $files array of
     * moodleform, which only ever carries $_FILES: its value is the id of a draft
     * area instead, and that area is empty as long as the user picked no file.
     * file_get_drafarea_files() is not used because it builds a browsable listing
     * for the file picker rather than a plain list of stored files.
     *
     * @param int $itemid Draft area id.
     * @return \stored_file|null The file, or null when the draft area is empty.
     */
    public static function get_draft_file(int $itemid): ?\stored_file {
        global $USER;

        if ($itemid <= 0 || empty($USER->id)) {
            return null;
        }

        $usercontext = \context_user::instance($USER->id);
        $files = get_file_storage()->get_area_files(
            $usercontext->id,
            'user',
            'draft',
            $itemid,
            'id',
            false
        );

        foreach ($files as $file) {
            if (!$file->is_directory()) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Attaches an evidence to a result, storing the submitted file under its own itemid.
     *
     * @param int $resultid The result the evidence documents.
     * @param \stdClass $data Cleaned form data, holding title, annotation, externalurl and evidencefile.
     * @param int $userid The user attaching the evidence.
     * @param \context $context Course context the file is stored in.
     * @param int $maxbytes Maximum accepted file size for the course.
     * @return int The id of the created evidence.
     */
    public static function create(
        int $resultid,
        \stdClass $data,
        int $userid,
        \context $context,
        int $maxbytes
    ): int {
        global $DB;

        $record = new \stdClass();
        $record->result_id = $resultid;
        $record->type = 'external';
        $record->title = $data->title;
        $record->annotation = $data->annotation;
        $record->externalurl = $data->externalurl;
        $record->userid = $userid;
        $record->timecreated = time();
        $record->timemodified = time();

        // The row is inserted first so that its id can be used as the file area
        // itemid: the file must live in an area of its own, otherwise saving it
        // would delete the files of the evidences attached earlier.
        $evidenceid = (int) $DB->insert_record('local_qualiscope_evidences', $record);

        $draftitemid = (int) ($data->evidencefile ?? 0);
        $draftfile = self::get_draft_file($draftitemid);
        if ($draftfile !== null) {
            file_save_draft_area_files(
                $draftitemid,
                $context->id,
                'local_qualiscope',
                self::FILEAREA,
                $evidenceid,
                ['subdirs' => 0, 'maxbytes' => $maxbytes]
            );

            // The area now holds this evidence only, so the stored name is the draft name.
            $record->id = $evidenceid;
            $record->filepath = $draftfile->get_filepath();
            $record->filename = $draftfile->get_filename();
            $DB->update_record('local_qualiscope_evidences', $record);
        }

        return $evidenceid;
    }

    /**
     * Returns the stored document of an evidence record, or null when it has none.
     *
     * @param \file_storage $fs The file storage.
     * @param \stdClass $record The evidence row, with a courseid property.
     * @return \stored_file|null The file, or null when the evidence is link only.
     */
    public static function get_file(\file_storage $fs, \stdClass $record): ?\stored_file {
        if (empty($record->filename) || (int) $record->courseid <= 0) {
            return null;
        }

        $context = \context_course::instance((int) $record->courseid);
        $filepath = empty($record->filepath) ? '/' : $record->filepath;

        // The file storage reports a miss with false, not with null.
        $file = $fs->get_file(
            $context->id,
            'local_qualiscope',
            self::FILEAREA,
            (int) $record->id,
            $filepath,
            $record->filename
        );

        return $file ?: null;
    }
}
