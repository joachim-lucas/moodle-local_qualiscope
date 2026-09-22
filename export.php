<?php

require_once('../../config.php');
require_once($CFG->dirroot . '/local/qualiscope/lib.php');

$campaignid = optional_param('campaignid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$referentialid = optional_param('referentialid', 0, PARAM_INT);
$format = optional_param('format', 'xlsx', PARAM_ALPHA);
$sesskey = optional_param('sesskey', '', PARAM_RAW);
if ($sesskey !== '') {
    require_sesskey($sesskey);
}

if ($campaignid) {
    require_login();
    $context = context_system::instance();
    require_capability('local/qualiscope:managecampaigns', $context);

    $campaign = $DB->get_record('local_qualiopi_campaigns', ['id' => $campaignid], '*', MUST_EXIST);
    $referential = $DB->get_record('local_qualiopi_referentials', ['id' => $campaign->referential_id], '*', MUST_EXIST);

    $results = $DB->get_records('local_qualiopi_results', ['campaign_id' => $campaignid], 'courseid ASC');

    $bycourse = [];
    foreach ($results as $r) {
        if (!isset($bycourse[$r->courseid])) {
            $c = $DB->get_record('course', ['id' => $r->courseid], 'id,fullname,shortname');
            if (!$c) {
                continue;
            }
            $bycourse[$r->courseid] = [
                'course' => $c,
                'total' => 0,
                'detected' => 0,
                'verify' => 0,
                'missing' => 0,
                'na' => 0,
                'weighted' => 0.0,
            ];
        }
        $bycourse[$r->courseid]['total']++;
        switch ($r->status) {
            case 'detected': $bycourse[$r->courseid]['detected']++; break;
            case 'verify':   $bycourse[$r->courseid]['verify']++; break;
            case 'missing':  $bycourse[$r->courseid]['missing']++; break;
            case 'na':       $bycourse[$r->courseid]['na']++; break;
        }
        if ($r->status !== 'na') {
            $bycourse[$r->courseid]['weighted'] += (float) $r->ratio > 0
                ? (float) $r->ratio
                : ($r->status === 'detected' ? 1.0 : 0.0);
        }
    }

    $totals = ['total' => 0, 'detected' => 0, 'verify' => 0, 'missing' => 0, 'na' => 0, 'weighted' => 0.0];
    $coursesdata = [];
    foreach ($bycourse as &$entry) {
        $applicable = $entry['total'] - $entry['na'];
        $entry['percentage'] = $applicable > 0 ? (int) round(($entry['weighted'] * 100) / $applicable) : 0;

        $totals['total'] += $entry['total'];
        $totals['detected'] += $entry['detected'];
        $totals['verify'] += $entry['verify'];
        $totals['missing'] += $entry['missing'];
        $totals['na'] += $entry['na'];
        $totals['weighted'] += $entry['weighted'];

        $coursesdata[] = [
            'courseid' => $entry['course']->id,
            'coursename' => $entry['course']->fullname,
            'courseshortname' => $entry['course']->shortname,
            'percentage' => $entry['percentage'],
            'detected' => $entry['detected'],
            'verify' => $entry['verify'],
            'missing' => $entry['missing'],
            'na' => $entry['na'],
        ];
    }
    unset($entry);

    $applicable = $totals['total'] - $totals['na'];
    $globalpercentage = $applicable > 0 ? (int) round(($totals['weighted'] * 100) / $applicable) : 0;
    $totals['score'] = $globalpercentage;

    // Criteria & Indicators.
    $criteria_records = $DB->get_records('local_qualiopi_criteria', ['referential_id' => $campaign->referential_id], 'number ASC');
    $indicators_records = $DB->get_records_sql(
        "SELECT i.* FROM {local_qualiopi_indicators} i
         JOIN {local_qualiopi_criteria} c ON c.id = i.criterion_id
         WHERE c.referential_id = :refid
         ORDER BY c.number ASC, i.number ASC",
        ['refid' => $campaign->referential_id]
    );

    $results_by_indicator = [];
    foreach ($results as $r) {
        $results_by_indicator[$r->indicator_id][] = $r;
    }

    $criteriadata = [];
    $weakpoints = [];

    foreach ($criteria_records as $crit) {
        $criteriadata[$crit->id] = [
            'id' => $crit->id,
            'number' => $crit->number,
            'title' => $crit->title,
            'indicators' => [],
            'total' => 0,
            'detected' => 0,
            'verify' => 0,
            'missing' => 0,
            'na' => 0,
            'weighted' => 0.0,
            'percentage' => null,
            'manualonly' => true,
        ];
    }

    foreach ($indicators_records as $ind) {
        $ind_results = $results_by_indicator[$ind->id] ?? [];
        $total = count($ind_results);
        $detected = 0;
        $verify = 0;
        $missing = 0;
        $na = 0;
        $weighted = 0.0;

        foreach ($ind_results as $r) {
            switch ($r->status) {
                case 'detected': $detected++; break;
                case 'verify':   $verify++; break;
                case 'missing':  $missing++; break;
                case 'na':       $na++; break;
            }
            if ($r->status !== 'na') {
                $weighted += (float) $r->ratio > 0 ? (float) $r->ratio : ($r->status === 'detected' ? 1.0 : 0.0);
            }
        }

        $app = $total - $na;
        $percentage = $app > 0 ? (int) round(($weighted * 100) / $app) : null;
        $failing_courses = $missing + $verify;

        $ind_item = [
            'id' => $ind->id,
            'number' => $ind->number,
            'title' => $ind->title,
            'total' => $total,
            'detected' => $detected,
            'verify' => $verify,
            'missing' => $missing,
            'na' => $na,
            'applicable' => $app,
            'percentage' => $percentage !== null ? $percentage : 0,
            'haspercentage' => $percentage !== null,
            'failingcourses' => $failing_courses,
            'criterion_number' => $criteria_records[$ind->criterion_id]->number ?? '',
            'criterion_title' => $criteria_records[$ind->criterion_id]->title ?? '',
        ];

        if (isset($criteriadata[$ind->criterion_id])) {
            $criteriadata[$ind->criterion_id]['indicators'][] = $ind_item;
            if ($total > 0) {
                $criteriadata[$ind->criterion_id]['manualonly'] = false;
            }
            $criteriadata[$ind->criterion_id]['total'] += $total;
            $criteriadata[$ind->criterion_id]['detected'] += $detected;
            $criteriadata[$ind->criterion_id]['verify'] += $verify;
            $criteriadata[$ind->criterion_id]['missing'] += $missing;
            $criteriadata[$ind->criterion_id]['na'] += $na;
            $criteriadata[$ind->criterion_id]['weighted'] += $weighted;
        }

        if ($app > 0) {
            $weakpoints[] = $ind_item;
        }
    }

    foreach ($criteriadata as &$cdata) {
        $capp = $cdata['total'] - $cdata['na'];
        if ($capp > 0) {
            $cdata['percentage'] = (int) round(($cdata['weighted'] * 100) / $capp);
        }
    }
    unset($cdata);

    usort($weakpoints, function($a, $b) {
        if ($a['percentage'] === $b['percentage']) {
            return $b['failingcourses'] <=> $a['failingcourses'];
        }
        return $a['percentage'] <=> $b['percentage'];
    });

    $weakpoints_filtered = array_values(array_filter($weakpoints, function($item) {
        return $item['percentage'] < 100;
    }));

    $rawfilename = 'qualiscope_campagne_' . clean_filename($campaign->name) . '_' . date('Ymd');
    $rawfilename = preg_replace('/[\r\n\t]+/', '', $rawfilename);
    $basefilename = \core_text::substr($rawfilename, 0, 60);

    if ($format === 'pdf') {
        $pdfgen = new \local_qualiscope\exporter\campaign_pdf_generator(
            $campaign,
            $referential,
            $totals,
            $coursesdata,
            array_values($criteriadata),
            $weakpoints_filtered
        );
        $pdfbytes = $pdfgen->generate();
        $filename = $basefilename . '.pdf';

        send_headers('application/pdf', true);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfbytes));
        echo $pdfbytes;
        exit;
    }

    if ($format === 'csv') {
        $filename = $basefilename . '.csv';
        send_headers('text/csv; charset=UTF-8', true);
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($out, [get_string('campaign_report_subtitle', 'local_qualiscope')]);
        fputcsv($out, [get_string('campaign_name', 'local_qualiscope'), $campaign->name]);
        fputcsv($out, [get_string('campaign_referential', 'local_qualiscope'), $referential->name . ' ' . $referential->version]);
        fputcsv($out, [get_string('export_generated', 'local_qualiscope'), userdate(time())]);
        fputcsv($out, [get_string('export_global_rate', 'local_qualiscope'), $globalpercentage . ' %']);
        fputcsv($out, [
            get_string('dashboard_detected', 'local_qualiscope') . ': ' . $totals['detected'],
            get_string('dashboard_verify', 'local_qualiscope') . ': ' . $totals['verify'],
            get_string('dashboard_missing', 'local_qualiscope') . ': ' . $totals['missing'],
            get_string('dashboard_na', 'local_qualiscope') . ': ' . $totals['na']
        ]);
        fputcsv($out, []);

        fputcsv($out, ['--- ' . strtoupper(get_string('campaign_tab_courses', 'local_qualiscope')) . ' ---']);
        fputcsv($out, [
            'ID',
            get_string('campaign_course', 'local_qualiscope'),
            get_string('dashboard_score', 'local_qualiscope'),
            get_string('dashboard_detected', 'local_qualiscope'),
            get_string('dashboard_verify', 'local_qualiscope'),
            get_string('dashboard_missing', 'local_qualiscope'),
            get_string('dashboard_na', 'local_qualiscope'),
        ]);
        foreach ($coursesdata as $c) {
            fputcsv($out, [
                $c['courseid'],
                $c['coursename'],
                $c['percentage'] . ' %',
                $c['detected'],
                $c['verify'],
                $c['missing'],
                $c['na'],
            ]);
        }
        fputcsv($out, []);

        fputcsv($out, ['--- ' . strtoupper(get_string('campaign_tab_macro', 'local_qualiscope')) . ' ---']);
        fputcsv($out, [
            get_string('campaign_criterion', 'local_qualiscope'),
            get_string('campaign_indicator', 'local_qualiscope'),
            get_string('description', 'core'),
            get_string('campaign_compliance_rate', 'local_qualiscope'),
            get_string('dashboard_detected', 'local_qualiscope'),
            get_string('dashboard_verify', 'local_qualiscope'),
            get_string('dashboard_missing', 'local_qualiscope'),
            get_string('dashboard_na', 'local_qualiscope'),
        ]);
        foreach ($criteriadata as $crit) {
            foreach ($crit['indicators'] as $ind) {
                fputcsv($out, [
                    'C' . $crit['number'] . ' - ' . $crit['title'],
                    'I' . $ind['number'],
                    $ind['title'],
                    $ind['haspercentage'] ? $ind['percentage'] . ' %' : get_string('dashboard_manual_only', 'local_qualiscope'),
                    $ind['detected'],
                    $ind['verify'],
                    $ind['missing'],
                    $ind['na'],
                ]);
            }
        }
        fclose($out);
        exit;
    }

    // Default XLSX for campaign.
    $writer = new \local_qualiscope\exporter\xlsx_writer(
        get_string('pluginname', 'local_qualiscope'),
        [30, 45, 18, 12, 12, 12, 12]
    );

    $writer->add_row([get_string('campaign_report_subtitle', 'local_qualiscope')], true);
    $writer->add_row([get_string('campaign_name', 'local_qualiscope') . ' : ' . $campaign->name]);
    $writer->add_row([get_string('campaign_referential', 'local_qualiscope') . ' : ' . $referential->name . ' ' . $referential->version]);
    $writer->add_row([get_string('export_generated', 'local_qualiscope') . ' : ' . userdate(time(), get_string('strftimedatetime', 'langconfig'))]);
    $writer->add_row([get_string('export_global_rate', 'local_qualiscope') . ' ' . $globalpercentage . ' %']);
    $writer->add_row([get_string('export_breakdown', 'local_qualiscope') . ' ' .
        get_string('dashboard_detected', 'local_qualiscope') . ' ' . $totals['detected'] . ' • ' .
        get_string('dashboard_verify', 'local_qualiscope') . ' ' . $totals['verify'] . ' • ' .
        get_string('dashboard_missing', 'local_qualiscope') . ' ' . $totals['missing'] . ' • ' .
        get_string('dashboard_na', 'local_qualiscope') . ' ' . $totals['na']]);
    $writer->add_row([]);

    $writer->add_row(['[ ' . get_string('campaign_tab_courses', 'local_qualiscope') . ' ]'], true);
    $writer->add_row([
        'ID',
        get_string('campaign_course', 'local_qualiscope'),
        get_string('dashboard_score', 'local_qualiscope'),
        get_string('dashboard_detected', 'local_qualiscope'),
        get_string('dashboard_verify', 'local_qualiscope'),
        get_string('dashboard_missing', 'local_qualiscope'),
        get_string('dashboard_na', 'local_qualiscope'),
    ], true);

    foreach ($coursesdata as $c) {
        $writer->add_row([
            (string) $c['courseid'],
            $c['coursename'],
            $c['percentage'] . ' %',
            (string) $c['detected'],
            (string) $c['verify'],
            (string) $c['missing'],
            (string) $c['na'],
        ]);
    }
    $writer->add_row([]);

    $writer->add_row(['[ ' . get_string('campaign_tab_macro', 'local_qualiscope') . ' ]'], true);
    $writer->add_row([
        get_string('campaign_criterion', 'local_qualiscope'),
        get_string('campaign_indicator', 'local_qualiscope'),
        get_string('description', 'core'),
        get_string('campaign_compliance_rate', 'local_qualiscope'),
        get_string('dashboard_detected', 'local_qualiscope'),
        get_string('dashboard_verify', 'local_qualiscope'),
        get_string('dashboard_missing', 'local_qualiscope'),
    ], true);

    foreach ($criteriadata as $crit) {
        foreach ($crit['indicators'] as $ind) {
            $writer->add_row([
                'C' . $crit['number'] . ' - ' . $crit['title'],
                'I' . $ind['number'],
                $ind['title'],
                $ind['haspercentage'] ? $ind['percentage'] . ' %' : get_string('dashboard_manual_only', 'local_qualiscope'),
                (string) $ind['detected'],
                (string) $ind['verify'],
                (string) $ind['missing'],
            ]);
        }
    }

    $filename = $basefilename . '.xlsx';
    $bytes = $writer->get_bytes();
    send_headers('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', true);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($bytes));
    echo $bytes;
    exit;
}

if (!$courseid) {
    throw new moodle_exception('missingparam', 'error', '', 'courseid');
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