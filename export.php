<?php

require_once('../../config.php');
require_once($CFG->dirroot . '/local/qualiscope/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$referentialid = optional_param('referentialid', 0, PARAM_INT);
$format = optional_param('format', 'xlsx', PARAM_ALPHA);
$sesskey = optional_param('sesskey', '', PARAM_RAW);
if ($sesskey !== '') {
    require_sesskey($sesskey);
}

require_login($courseid);
$context = context_course::instance($courseid);
require_capability('local/qualiscope:viewaudit', $context);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

if (!$referentialid) {
    $referentialid = \local_qualiscope\analyser\course_analyser::get_default_referential_id() ?? 0;
}
$referential = $referentialid ? $DB->get_record('local_qualiopi_referentials', ['id' => $referentialid]) : null;
if (!$referential) {
    throw new moodle_exception('invalidreferential', 'local_qualiscope');
}

$analyser = new \local_qualiscope\analyser\course_analyser($courseid, 0, $referentialid);
$results = $analyser->run();
$summary = $analyser->get_summary();
$criteriasummary = $analyser->get_criteria_summary();

$rawfilename = 'qualiscope_' . clean_filename($course->shortname) . '_' . date('Ymd');
$rawfilename = preg_replace('/[\r\n\t]+/', '', $rawfilename);
$basefilename = \core_text::substr($rawfilename, 0, 60);

if ($format === 'pdf') {
    $pdfgen = new \local_qualiscope\exporter\pdf_generator($course, $referential, $summary, $criteriasummary, $results);
    $pdfbytes = $pdfgen->generate();
    $filename = $basefilename . '.pdf';

    send_headers('application/pdf', true);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdfbytes));
    echo $pdfbytes;
    exit;
}

if ($format === 'zip') {
    $zipgen = new \local_qualiscope\exporter\zip_generator($course, $referential, $summary, $criteriasummary, $results);
    $zippath = $zipgen->generate();
    $filename = $basefilename . '_dossier_preuves.zip';

    send_temp_file($zippath, $filename);
    exit;
}

// Default: XLSX export
$resultmap = [];
foreach ($results as $result) {
    $resultmap[$result['check']->id] = $result;
}

$criteria = $DB->get_records('local_qualiopi_criteria', ['referential_id' => $referentialid], 'number ASC');
$indicators = $DB->get_records_sql(
    "SELECT i.* FROM {local_qualiopi_indicators} i
     JOIN {local_qualiopi_criteria} c ON c.id = i.criterion_id
     WHERE c.referential_id = :refid ORDER BY c.number ASC, i.number ASC",
    ['refid' => $referentialid]
);
$checks = $DB->get_records_sql(
    "SELECT ch.* FROM {local_qualiopi_checks} ch
     JOIN {local_qualiopi_indicators} i ON i.id = ch.indicator_id
     JOIN {local_qualiopi_criteria} c ON c.id = i.criterion_id
     WHERE c.referential_id = :refid ORDER BY c.number ASC, i.number ASC, ch.id ASC",
    ['refid' => $referentialid]
);

$indicatorsbycriterion = [];
foreach ($indicators as $indicator) {
    $indicatorsbycriterion[$indicator->criterion_id][] = $indicator;
}
$checksbyindicator = [];
foreach ($checks as $check) {
    $checksbyindicator[$check->indicator_id][] = $check;
}

$writer = new \local_qualiscope\exporter\xlsx_writer(
    get_string('pluginname', 'local_qualiscope'),
    [38, 42, 46, 22, 12, 70]
);

$writer->add_row([get_string('export_report_title', 'local_qualiscope')], true);
$writer->add_row([get_string('export_course', 'local_qualiscope') . ' ' . $course->fullname]);
$writer->add_row([get_string('export_referential', 'local_qualiscope') . ' ' . $referential->name . ' ' . $referential->version]);
$writer->add_row([get_string('export_generated', 'local_qualiscope') . ' ' . userdate(time(), get_string('strftimedatetime', 'langconfig'))]);
$writer->add_row([get_string('export_global_rate', 'local_qualiscope') . ' ' . $summary['percentage'] . ' %']);
$writer->add_row([get_string('export_breakdown', 'local_qualiscope') . ' ' .
    get_string('dashboard_detected', 'local_qualiscope') . ' ' . $summary['detected'] . ' • ' .
    get_string('dashboard_verify', 'local_qualiscope') . ' ' . $summary['verify'] . ' • ' .
    get_string('dashboard_missing', 'local_qualiscope') . ' ' . $summary['missing'] . ' • ' .
    get_string('dashboard_na', 'local_qualiscope') . ' ' . $summary['na']]);
$writer->add_row([]);
$writer->add_row([
    get_string('export_col_criterion', 'local_qualiscope'),
    get_string('export_col_indicator', 'local_qualiscope'),
    get_string('export_col_check', 'local_qualiscope'),
    get_string('export_col_status', 'local_qualiscope'),
    get_string('export_col_compliance', 'local_qualiscope'),
    get_string('export_col_detail', 'local_qualiscope'),
], true);

$hasstatus = false;
foreach ($criteria as $criterion) {
    foreach ($indicatorsbycriterion[$criterion->id] ?? [] as $indicator) {
        $criterionlabel = (int) $criterion->number . ' — ' . $criterion->title;
        $indicatorlabel = (int) $criterion->number . '.' . (int) $indicator->number . ' — ' . $indicator->title;
        $indicatorchecks = $checksbyindicator[$indicator->id] ?? [];

        if (!$indicatorchecks) {
            $writer->add_row([
                $criterionlabel,
                $indicatorlabel,
                get_string('criteria_manual_only', 'local_qualiscope'),
                get_string('dashboard_manual_only', 'local_qualiscope'),
                '—',
                '',
            ]);
            continue;
        }

        foreach ($indicatorchecks as $check) {
            $result = $resultmap[$check->id] ?? null;
            if ($check->automatic && $result) {
                $status = get_string('status_' . $result['status'], 'local_qualiscope');
                if ($result['status'] === 'na') {
                    $compliance = '—';
                } else {
                    $ratio = $result['ratio'] ?? ($result['status'] === 'detected' ? 1.0 : 0.0);
                    $compliance = (int) round($ratio * 100) . ' %';
                }
                $detail = $result['detail'] ?? '';
            } else if ($check->automatic) {
                $status = '—';
                $compliance = '—';
                $detail = $check->description ?? '';
            } else {
                $status = get_string('dashboard_manual_only', 'local_qualiscope');
                $compliance = '—';
                $detail = $check->description ?? '';
            }
            $writer->add_row([
                $criterionlabel,
                $indicatorlabel,
                $check->name,
                $status,
                $compliance,
                $detail,
            ]);
        }
    }
}

$rawfilename = 'qualiscope_' . clean_filename($course->shortname) . '_' . date('Ymd');
$rawfilename = preg_replace('/[\r\n\t]+/', '', $rawfilename);
$filename = \core_text::substr($rawfilename, 0, 60) . '.xlsx';

$bytes = $writer->get_bytes();
send_headers('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', true);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($bytes));
echo $bytes;
exit;