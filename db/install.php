<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Installation hook: seeds the built-in referentials from JSON files.
 *
 * @return bool Always true.
 */
function xmldb_local_qualiscope_install() {
    \local_qualiscope\referential_seeder::seed_all_from_files();
    return true;
}