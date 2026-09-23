<?php

namespace local_qualiscope;

defined('MOODLE_INTERNAL') || die();

class referential_seeder {

    /**
     * Scan the referentials/ directory and seed every JSON file
     * whose referential name does not already exist in the DB.
     * Guard: name-only — if (name) exists, the file is skipped.
     */
    public static function seed_all_from_files(): void {
        global $CFG;

        $path = $CFG->dirroot . '/local/qualiscope/referentials';
        if (!is_dir($path)) {
            return;
        }

        $files = glob($path . '/*.json');
        foreach ($files as $file) {
            $def = self::read_referential_file($file);
            if ($def) {
                self::seed_referential($def);
            }
        }
    }

    /**
     * Read a single referential definition from a JSON file.
     */
    public static function read_referential_file(string $filepath): ?array {
        if (!is_readable($filepath)) {
            return null;
        }

        $raw = file_get_contents($filepath);
        if ($raw === false) {
            return null;
        }

        $def = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        if (empty($def['name']) || empty($def['criteria'])) {
            return null;
        }

        return $def;
    }

    /**
     * Seed a referential from an array definition.
     * Skips if a referential with the same name already exists (idempotent by name).
     */
    public static function seed_referential(array $def): void {
        global $DB;

        $existing = $DB->get_record('local_qualiscope_referentials', ['name' => $def['name']]);
        if ($existing) {
            $refid = $existing->id;
            $existing->version = $def['version'] ?? $existing->version;
            $existing->description = $def['description'] ?? $existing->description;
            $existing->timemodified = time();
            $DB->update_record('local_qualiscope_referentials', $existing);
        } else {
            $refid = $DB->insert_record('local_qualiscope_referentials', [
                'name'         => $def['name'],
                'version'      => $def['version'] ?? '',
                'description'  => $def['description'] ?? '',
                'active'       => $def['active'] ?? 1,
                'timecreated'  => time(),
                'timemodified' => time(),
            ]);
        }

        foreach ($def['criteria'] as $c) {
            $existingcrit = $DB->get_record('local_qualiscope_criteria', [
                'referential_id' => $refid,
                'number'         => $c['number'],
            ]);

            if ($existingcrit) {
                $cid = $existingcrit->id;
                $existingcrit->title = $c['title'];
                $existingcrit->description = $c['description'] ?? '';
                $existingcrit->timemodified = time();
                $DB->update_record('local_qualiscope_criteria', $existingcrit);
            } else {
                $cid = $DB->insert_record('local_qualiscope_criteria', [
                    'referential_id' => $refid,
                    'number'         => $c['number'],
                    'title'          => $c['title'],
                    'description'    => $c['description'] ?? '',
                    'timecreated'    => time(),
                    'timemodified'   => time(),
                ]);
            }

            foreach ($c['indicators'] ?? [] as $ind) {
                $existingind = $DB->get_record('local_qualiscope_indicators', [
                    'criterion_id' => $cid,
                    'number'       => $ind['number'],
                ]);

                if ($existingind) {
                    $indid = $existingind->id;
                    $existingind->title = $ind['title'];
                    $existingind->description = $ind['description'] ?? '';
                    $existingind->scope = $ind['scope'] ?? 'course';
                    $existingind->timemodified = time();
                    $DB->update_record('local_qualiscope_indicators', $existingind);
                } else {
                    $indid = $DB->insert_record('local_qualiscope_indicators', [
                        'criterion_id'  => $cid,
                        'number'        => $ind['number'],
                        'title'         => $ind['title'],
                        'description'   => $ind['description'] ?? '',
                        'scope'         => $ind['scope'] ?? 'course',
                        'timecreated'   => time(),
                        'timemodified'  => time(),
                    ]);
                }

                foreach ($ind['checks'] ?? [] as $chk) {
                    $existingchk = $DB->get_record('local_qualiscope_checks', [
                        'indicator_id' => $indid,
                        'name'         => $chk['name'],
                    ]);

                    if ($existingchk) {
                        $existingchk->description = $chk['description'] ?? '';
                        $existingchk->type = $chk['type'];
                        $existingchk->automatic = !empty($chk['automatic']) ? 1 : 0;
                        $existingchk->weight = $chk['weight'] ?? 1;
                        $existingchk->timemodified = time();
                        $DB->update_record('local_qualiscope_checks', $existingchk);
                    } else {
                        $DB->insert_record('local_qualiscope_checks', [
                            'indicator_id' => $indid,
                            'name'         => $chk['name'],
                            'description'  => $chk['description'] ?? '',
                            'type'         => $chk['type'],
                            'automatic'    => !empty($chk['automatic']) ? 1 : 0,
                            'weight'       => $chk['weight'] ?? 1,
                            'timecreated'  => time(),
                            'timemodified' => time(),
                        ]);
                    }
                }
            }
        }
    }
}