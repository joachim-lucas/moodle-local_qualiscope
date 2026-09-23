<?php

namespace local_qualiscope\exporter;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filestorage/zip_packer.php');

/**
 * Auditor Evidence Dossier ZIP archive generator.
 * Creates an organized folder structure: Critere_01/.../Indicateur_01/... with evidence summaries and PDF report.
 *
 * @package local_qualiscope
 */
class zip_generator {

    /** @var object Course record */
    private $course;

    /** @var object Referential record */
    private $referential;

    /** @var array Summary statistics */
    private $summary;

    /** @var array Criteria summary */
    private $criteriasummary;

    /** @var array Results list */
    private $results;

    /**
     * Constructor.
     *
     * @param object $course The course record.
     * @param object $referential The referential record.
     * @param array $summary Global summary statistics.
     * @param array $criteriasummary Per-criterion summary data.
     * @param array $results List of check results.
     */
    public function __construct(object $course, object $referential, array $summary, array $criteriasummary, array $results) {
        $this->course = $course;
        $this->referential = $referential;
        $this->summary = $summary;
        $this->criteriasummary = $criteriasummary;
        $this->results = $results;
    }

    /**
     * Generates a temporary zip file containing the full auditor dossier.
     *
     * @return string Path to the temporary zip archive file.
     */
    public function generate(): string {
        global $DB, $CFG;

        $files = [];

        // 1. Generate full PDF audit report and add to root of ZIP
        $pdfgen = new \local_qualiscope\exporter\pdf_generator(
            $this->course,
            $this->referential,
            $this->summary,
            $this->criteriasummary,
            $this->results
        );
        $pdfbytes = $pdfgen->generate();
        $files['00_Rapport_Audit_QualiScope.pdf'] = $pdfbytes;

        // 2. Generate a main README / Index for the auditor
        $readme = "# DOSSIER DE PREUVES QUALIOPI - QUALISCOPE\n\n";
        $readme .= "Formation / Cours : " . $this->course->fullname . " (" . $this->course->shortname . ")\n";
        $readme .= "Référentiel : " . $this->referential->name . " " . $this->referential->version . "\n";
        $readme .= "Date d'audit : " . userdate(time(), get_string('strftimedatetime', 'langconfig')) . "\n";
        $readme .= "Niveau de conformité global Moodle : " . $this->summary['percentage'] . " %\n\n";
        $readme .= "## SYNTHÈSE DES CRITÈRES\n";

        foreach ($this->criteriasummary as $c) {
            $critobj = $c['criteria'];
            $pctstr = $c['percentage'] !== null ? $c['percentage'] . ' %' : 'Preuves manuelles / Externes uniquement';
            $readme .= "- Critère " . (int) $critobj->number . " : " . $critobj->title . " => " . $pctstr . "\n";
        }
        $readme .= "\nCe dossier classe les indicateurs par sous-dossiers Critere_XX / Indicateur_YY avec les fiches de preuves et données Moodle.\n";
        $files['00_INDEX_AUDITEUR.txt'] = $readme;

        // Map results by check ID
        $resultmap = [];
        foreach ($this->results as $res) {
            $resultmap[$res['check']->id] = $res;
        }

        $criteria = $DB->get_records('local_qualiscope_criteria', ['referential_id' => $this->referential->id], 'number ASC');
        $indicators = $DB->get_records_sql(
            "SELECT i.* FROM {local_qualiscope_indicators} i
             JOIN {local_qualiscope_criteria} c ON c.id = i.criterion_id
             WHERE c.referential_id = :refid ORDER BY c.number ASC, i.number ASC",
            ['refid' => $this->referential->id]
        );
        $checks = $DB->get_records_sql(
            "SELECT ch.* FROM {local_qualiscope_checks} ch
             JOIN {local_qualiscope_indicators} i ON i.id = ch.indicator_id
             JOIN {local_qualiscope_criteria} c ON c.id = i.criterion_id
             WHERE c.referential_id = :refid ORDER BY c.number ASC, i.number ASC, ch.id ASC",
            ['refid' => $this->referential->id]
        );

        $indicatorsbycriterion = [];
        foreach ($indicators as $ind) {
            $indicatorsbycriterion[$ind->criterion_id][] = $ind;
        }
        $checksbyindicator = [];
        foreach ($checks as $chk) {
            $checksbyindicator[$chk->indicator_id][] = $chk;
        }

        foreach ($criteria as $criterion) {
            $critfolder = sprintf("Critere_%02d", (int) $criterion->number);

            foreach ($indicatorsbycriterion[$criterion->id] ?? [] as $indicator) {
                $indfolder = sprintf("%s/Indicateur_%02d", $critfolder, (int) $indicator->number);
                $indchecks = $checksbyindicator[$indicator->id] ?? [];

                $inddoc = "# FICHE DE PREUVE - INDICATEUR " . (int) $indicator->number . "\n";
                $inddoc .= "Titre : " . $indicator->title . "\n";
                $inddoc .= "Exigence : " . $indicator->description . "\n";
                $inddoc .= "Scope : " . $indicator->scope . "\n\n";
                $inddoc .= "## CONTRÔLES QUALISCOPE MOODLE\n\n";

                if (empty($indchecks)) {
                    $inddoc .= "- Aucun contrôle automatique. Preuve documentaire / externe attendue.\n";
                } else {
                    foreach ($indchecks as $check) {
                        $res = $resultmap[$check->id] ?? null;
                        $inddoc .= "### " . $check->name . "\n";
                        $inddoc .= "- Description : " . $check->description . "\n";
                        if ($check->automatic && $res) {
                            $inddoc .= "- Statut Moodle : " . get_string('status_' . $res['status'], 'local_qualiscope') . "\n";
                            $ratio = $res['ratio'] ?? ($res['status'] === 'detected' ? 1.0 : 0.0);
                            $inddoc .= "- Conformité calculée : " . round($ratio * 100) . " %\n";
                            $inddoc .= "- Traces & Détails : " . ($res['detail'] ?? 'N/A') . "\n\n";
                        } else {
                            $inddoc .= "- Statut : Preuve manuelle / externe à joindre.\n\n";
                        }
                    }
                }

                $files[$indfolder . '/Fiche_Preuve.txt'] = $inddoc;
            }
        }

        // Package into ZIP using Moodle zip_packer
        $zipper = new \zip_packer();
        $tempdir = make_temp_directory('qualiscope_dossier');
        $tempzip = $tempdir . '/dossier_' . uniqid('', true) . '.zip';

        // Convert virtual array into filesystem items or pass directly
        $fileentries = [];
        foreach ($files as $relpath => $content) {
            $temppath = $tempdir . '/' . md5($relpath);
            file_put_contents($temppath, $content);
            $fileentries[$relpath] = $temppath;
        }

        $zipper->archive_to_pathname($fileentries, $tempzip);

        // Cleanup individual temp files
        foreach ($fileentries as $tmp) {
            @unlink($tmp);
        }

        return $tempzip;
    }
}
