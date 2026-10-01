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
 * @package local_qualiscope
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
     * Returns a plain text version of a stored string.
     *
     * Course names, section names and referential labels are saved with the raw
     * markup of the filters applied to them. A course named with the multilang
     * filter therefore holds one <span> per language, and the markup has to be
     * resolved before the value is displayed or exported. The leftover HTML and
     * entities are then removed so the result is safe for a template, a
     * spreadsheet cell or a file name.
     *
     * @param string|null $value Raw value.
     * @param \context|null $context Context used to run the filters, system context by default.
     * @return string The value as plain text.
     */
    public static function plain(?string $value, ?\context $context = null): string {
        if ($value === null || $value === '') {
            return '';
        }

        $text = self::resolve_multilang($value, $context ?? \context_system::instance());

        // Filters may legitimately return markup (links, entities), the callers
        // all want a bare text they can escape themselves.
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim($text);
    }

    /**
     * Resolves the multilang markup of a value for the current language.
     *
     * format_string() only runs the multilang filter when that filter is active
     * in the context, and a disabled filter would leave every translation
     * concatenated in the output. The filter is therefore called directly, so a
     * course name stored with the multilang syntax always yields the text of the
     * language being displayed, whatever the site configuration is.
     *
     * @param string $value Raw value.
     * @param \context $context Context of the filter.
     * @return string The value with its multilang markup resolved.
     */
    private static function resolve_multilang(string $value, \context $context): string {
        $filterclass = '\filter_multilang\text_filter';
        if (!class_exists($filterclass)) {
            return $value;
        }

        $resolved = (new $filterclass($context, []))->filter($value);

        return is_string($resolved) ? $resolved : $value;
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
