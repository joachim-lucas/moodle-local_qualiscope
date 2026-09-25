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

namespace local_qualiscope;

/**
 * Localisation helpers for referential records.
 *
 * The class lives in classes/ so Moodle's autoloader makes it available on
 * every page (settings, plugin pages, CLI, AJAX) without depending on the
 * plugin lib.php being loaded.
 *
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /**
     * Returns the localized value of a referential record field.
     *
     * Referential definitions are stored in French (the base fields), with an
     * English counterpart available in the *_en fields. When the current
     * language is English and an English value exists, it is returned.
     *
     * @param \stdClass $record A referential record (criterion, indicator or check).
     * @param string $field Base field name ('title', 'name' or 'description').
     * @return string The localized value, falling back to the base (French) value.
     */
    public static function localized(\stdClass $record, string $field): string {
        $value = $record->$field ?? '';
        $enfield = $field . '_en';
        if (str_starts_with(current_language(), 'en') && !empty($record->$enfield)) {
            return $record->$enfield;
        }
        return $value;
    }

    /**
     * Returns a copy of a referential record with its display fields localized.
     *
     * @param \stdClass $record A referential record (criterion, indicator or check).
     * @return \stdClass The record copy with localized title, name and description.
     */
    public static function localize_record(\stdClass $record): \stdClass {
        if (!str_starts_with(current_language(), 'en')) {
            return $record;
        }
        $copy = clone $record;
        foreach (['title', 'name', 'description'] as $field) {
            $enfield = $field . '_en';
            if (!empty($copy->$enfield)) {
                $copy->$field = $copy->$enfield;
            }
        }
        return $copy;
    }
}
