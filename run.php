<?php

require_once('../../config.php');
require_once($CFG->dirroot . '/local/qualiscope/lib.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$campaignid = optional_param('campaignid', 0, PARAM_INT);
$referentialid = optional_param('referentialid', 0, PARAM_INT);

if ($sesskey = optional_param('sesskey', '', PARAM_RAW)) {
    require_sesskey($sesskey);
}

require_login();

if ($campaignid) {
    $campaign = $DB->get_record('local_qualiscope_campaigns', ['id' => $campaignid], '*', MUST_EXIST);
    $context = context_system::instance();
    require_capability('local/qualiscope:managecampaigns', $context);

    $referentialid = (int) $campaign->referential_id;
    $ajax = optional_param('ajax', 0, PARAM_INT);
    $singlecourseid = optional_param('singlecourseid', 0, PARAM_INT);
    $finish = optional_param('finish', 0, PARAM_INT);
    $direct = optional_param('direct', 0, PARAM_INT);

    if ($ajax && $singlecourseid) {
        $analyser = new \local_qualiscope\analyser\course_analyser($singlecourseid, $campaignid, $referentialid);
        $analyser->run();
        $analyser->save_results($campaignid);

        if ($finish) {
            $campaign->timecompleted = time();
            $campaign->timemodified = time();
            $DB->update_record('local_qualiscope_campaigns', $campaign);
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'ok', 'courseid' => $singlecourseid, 'finished' => (bool)$finish]);
        exit;
    }

    if ($direct) {
        $courseids = \local_qualiscope\analyser\course_analyser::get_campaign_course_ids($campaign);
        foreach ($courseids as $cid) {
            $analyser = new \local_qualiscope\analyser\course_analyser($cid, $campaignid, $referentialid);
            $analyser->run();
            $analyser->save_results($campaignid);
        }
        $campaign->timecompleted = time();
        $campaign->timemodified = time();
        $DB->update_record('local_qualiscope_campaigns', $campaign);
        redirect(new moodle_url('/local/qualiscope/view_campaign.php', ['id' => $campaignid]));
    }

    // Interactive Real-time Progress Page.
    $courseids = \local_qualiscope\analyser\course_analyser::get_campaign_course_ids($campaign);
    $coursesinfo = [];
    if (!empty($courseids)) {
        list($insql, $inparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
        $coursesrecords = $DB->get_records_select('course', "id $insql", $inparams, 'fullname ASC', 'id,fullname');
        foreach ($courseids as $cid) {
            if (isset($coursesrecords[$cid])) {
                $coursesinfo[] = [
                    'id' => $cid,
                    'fullname' => $coursesrecords[$cid]->fullname,
                ];
            }
        }
    }

    $PAGE->set_url(new moodle_url('/local/qualiscope/run.php', ['campaignid' => $campaignid]));
    $PAGE->set_title(get_string('campaign_running_progress_title', 'local_qualiscope') . ' - ' . $campaign->name);
    $PAGE->set_heading($campaign->name);
    $PAGE->set_context($context);

    echo $OUTPUT->header();
    ?>
    <div class="qualiscope-run-progress container py-5">
        <div class="card shadow-sm border-0 mx-auto" style="max-width: 700px;">
            <div class="card-body p-4 text-center">
                <div class="mb-3">
                    <span class="spinner-border text-primary" role="status" id="runSpinner" style="width: 3rem; height: 3rem;"></span>
                </div>
                <h4 class="mb-2" id="runTitle"><?php echo s(get_string('campaign_running_progress_title', 'local_qualiscope')); ?></h4>
                <p class="text-muted mb-4"><?php echo s($campaign->name); ?> &bull; <strong><?php echo count($coursesinfo); ?></strong> <?php echo s(get_string('campaign_courses_analysed', 'local_qualiscope')); ?></p>

                <div class="progress mb-3" style="height: 26px; border-radius: 13px;">
                    <div id="runProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary font-weight-bold"
                         role="progressbar" style="width: 0%; font-size: 13px;">0%</div>
                </div>

                <div id="runProgressText" class="text-muted small mb-3">
                    <?php echo s(get_string('campaign_running_wait', 'local_qualiscope')); ?>
                </div>

                <div id="runCurrentCourse" class="alert alert-light border py-2 small text-truncate">
                    ...
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        var courses = <?php echo json_encode($coursesinfo); ?>;
        var campaignId = <?php echo (int) $campaignid; ?>;
        var sesskey = <?php echo json_encode(sesskey()); ?>;
        var total = courses.length;
        var currentIndex = 0;
        var targetUrl = <?php echo json_encode((new moodle_url('/local/qualiscope/view_campaign.php', ['id' => $campaignid]))->out(false)); ?>;

        var progressBar = document.getElementById('runProgressBar');
        var progressText = document.getElementById('runProgressText');
        var currentCourseEl = document.getElementById('runCurrentCourse');
        var spinner = document.getElementById('runSpinner');
        var titleEl = document.getElementById('runTitle');

        if (total === 0) {
            window.location.href = targetUrl;
            return;
        }

        function processNext() {
            if (currentIndex >= total) {
                progressBar.style.width = '100%';
                progressBar.textContent = '100%';
                progressBar.classList.remove('progress-bar-animated');
                progressBar.classList.add('bg-success');
                if (spinner) spinner.style.display = 'none';
                if (titleEl) titleEl.textContent = 'Analyse terminée avec succès !';
                currentCourseEl.textContent = 'Redirection vers les résultats...';
                setTimeout(function() {
                    window.location.href = targetUrl;
                }, 600);
                return;
            }

            var course = courses[currentIndex];
            var isFinish = (currentIndex === total - 1) ? 1 : 0;
            var pct = Math.round(((currentIndex) / total) * 100);

            progressBar.style.width = pct + '%';
            progressBar.textContent = pct + '%';
            progressText.textContent = 'Traitement du cours ' + (currentIndex + 1) + ' sur ' + total + ' (' + pct + ' %)';
            currentCourseEl.textContent = course.fullname;

            var url = 'run.php?campaignid=' + campaignId +
                      '&singlecourseid=' + course.id +
                      '&ajax=1' +
                      '&finish=' + isFinish +
                      '&sesskey=' + encodeURIComponent(sesskey);

            var xhr = new XMLHttpRequest();
            xhr.open('GET', url, true);
            xhr.onload = function() {
                currentIndex++;
                var nextPct = Math.round((currentIndex / total) * 100);
                progressBar.style.width = nextPct + '%';
                progressBar.textContent = nextPct + '%';
                processNext();
            };
            xhr.onerror = function() {
                // Retry or continue on next course
                currentIndex++;
                processNext();
            };
            xhr.send();
        }

        // Start processing.
        setTimeout(processNext, 200);
    })();
    </script>
    <?php
    echo $OUTPUT->footer();
    exit;
}

if (!$courseid) {
    throw new \moodle_exception('missingparam', 'core', '', 'courseid');
}

require_login($courseid);
$context = context_system::instance();
require_capability('local/qualiscope:managecampaigns', $context);

$analyser = new \local_qualiscope\analyser\course_analyser($courseid, 0, $referentialid ?: null);
$analyser->run();

redirect(new moodle_url('/local/qualiscope/dashboard.php', ['courseid' => $courseid, 'referentialid' => $referentialid]));