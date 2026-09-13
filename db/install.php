<?php

defined('MOODLE_INTERNAL') || die();

function xmldb_local_qualiscope_install() {
    \local_qualiscope\referential_seeder::seed_all_from_files();
    return true;
}