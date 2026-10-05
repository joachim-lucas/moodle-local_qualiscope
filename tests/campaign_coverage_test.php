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
 * Campaign coverage and creation form tests for local_qualiscope.
 *
 * @package    local_qualiscope
 * @category   test
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qualiscope\tests;

use local_qualiscope\campaign_coverage;
use local_qualiscope\form\campaign_create;

/**
 * Campaign coverage and creation form testcase.
 *
 * @package local_qualiscope
 * @covers \local_qualiscope\campaign_coverage
 * @covers \local_qualiscope\form\campaign_create
 */
final class campaign_coverage_test extends \advanced_testcase {
    /**
     * Set up.
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Creates a campaign.
     *
     * @return int The campaign id.
     */
    private function campaign(): int {
        global $DB;

        return (int) $DB->insert_record('local_qualiscope_campaigns', [
            'name' => 'Audit',
            'referential_id' => 0,
            'scope' => 'all',
            'scopeids' => '[]',
            'userid' => get_admin()->id,
            'timecreated' => time(),
            'timemodified' => time(),
            'timecompleted' => 0,
        ]);
    }

    /**
     * Creates a result of a course.
     *
     * @param int $campaignid The campaign.
     * @param int $courseid The course.
     * @param string $status Result status.
     * @param float $ratio Completion ratio.
     * @return int The result id.
     */
    private function addresult(int $campaignid, int $courseid, string $status, float $ratio): int {
        global $DB;

        return (int) $DB->insert_record('local_qualiscope_results', [
            'campaign_id' => $campaignid,
            'courseid' => $courseid,
            'check_id' => 0,
            'indicator_id' => 0,
            'status' => $status,
            'ratio' => $ratio,
            'detail' => '',
            'evidence_count' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Test a campaign without results has no coverage and no analysed course.
     *
     * @return void
     */
    public function test_campaign_without_results_has_no_coverage(): void {
        $campaignid = $this->campaign();

        $coverages = campaign_coverage::for_all_campaigns();

        $this->assertArrayNotHasKey($campaignid, $coverages);
    }

    /**
     * Test the average is the mean of the per course percentages.
     *
     * The list shows the mean of the course coverages, not the ratio over every
     * result: a course with one detected and one missing check is at 50% even
     * when the other courses are at 100%.
     *
     * @return void
     */
    public function test_average_is_the_mean_of_the_course_percentages(): void {
        $campaignid = $this->campaign();
        $good = $this->getDataGenerator()->create_course()->id;
        $half = $this->getDataGenerator()->create_course()->id;

        $this->addresult($campaignid, $good, 'detected', 1.0);
        $this->addresult($campaignid, $good, 'detected', 1.0);
        // Half of the checks missing: 1 of 2 detected.
        $this->addresult($campaignid, $half, 'detected', 1.0);
        $this->addresult($campaignid, $half, 'missing', 0.0);

        $coverages = campaign_coverage::for_all_campaigns();

        $this->assertEquals(2, $coverages[$campaignid]['courses']);
        $this->assertEquals(75, $coverages[$campaignid]['avgcoverage']);
    }

    /**
     * Test a course whose checks are all not applicable is counted but not averaged.
     *
     * @return void
     */
    public function test_course_with_only_na_results_is_counted_but_not_averaged(): void {
        $campaignid = $this->campaign();
        $auditable = $this->getDataGenerator()->create_course()->id;
        $notapplicable = $this->getDataGenerator()->create_course()->id;

        $this->addresult($campaignid, $auditable, 'detected', 1.0);
        $this->addresult($campaignid, $auditable, 'missing', 0.0);
        $this->addresult($campaignid, $notapplicable, 'na', 0.0);
        $this->addresult($campaignid, $notapplicable, 'na', 0.0);

        $coverages = campaign_coverage::for_all_campaigns();

        // Both courses were audited, so both are counted.
        $this->assertEquals(2, $coverages[$campaignid]['courses']);
        // Only the auditable one weighs in, so 50% and not 25%.
        $this->assertEquals(50, $coverages[$campaignid]['avgcoverage']);
    }

    /**
     * Test a campaign whose results are all not applicable has no average.
     *
     * @return void
     */
    public function test_campaign_with_only_na_results_has_no_average(): void {
        $campaignid = $this->campaign();
        $course = $this->getDataGenerator()->create_course()->id;

        $this->addresult($campaignid, $course, 'na', 0.0);
        $this->addresult($campaignid, $course, 'na', 0.0);

        $coverages = campaign_coverage::for_all_campaigns();

        $this->assertEquals(1, $coverages[$campaignid]['courses']);
        $this->assertNull($coverages[$campaignid]['avgcoverage']);
    }

    /**
     * Test every campaign gets its own coverage, not the first one of the table.
     *
     * get_records_sql() keys its rows on the first column, so an aggregate
     * grouped by campaign and course used to collapse to a single row per
     * campaign, and each campaign showed the coverage of whichever course was
     * returned last.
     *
     * @return void
     */
    public function test_each_campaign_gets_its_own_coverage(): void {
        $full = $this->campaign();
        $empty = $this->campaign();
        $course1 = $this->getDataGenerator()->create_course()->id;
        $course2 = $this->getDataGenerator()->create_course()->id;

        $this->addresult($full, $course1, 'detected', 1.0);
        $this->addresult($full, $course2, 'missing', 0.0);
        $this->addresult($empty, $course1, 'na', 0.0);

        $coverages = campaign_coverage::for_all_campaigns();

        $this->assertEquals(2, $coverages[$full]['courses']);
        $this->assertEquals(50, $coverages[$full]['avgcoverage']);
        $this->assertEquals(1, $coverages[$empty]['courses']);
        $this->assertNull($coverages[$empty]['avgcoverage']);
    }

    /**
     * Test the multiselect marker is not taken for a course id.
     *
     * @return void
     */
    public function test_selected_course_ids_drops_the_multiselect_marker(): void {
        $data = (object) ['scopeids' => ['_qf__force_multiselect_submission', '7', '4']];

        $this->assertEquals([7, 4], campaign_create::selected_course_ids($data));
    }

    /**
     * Test an empty picker yields no course id.
     *
     * @return void
     */
    public function test_selected_course_ids_of_an_empty_picker(): void {
        $this->assertEquals(
            [],
            campaign_create::selected_course_ids((object) ['scopeids' => ['_qf__force_multiselect_submission']])
        );
        $this->assertEquals([], campaign_create::selected_course_ids((object) []));
    }

    /**
     * Test the form refuses a campaign scope pointing at an empty list.
     *
     * A campaign listing no category or no course covered nothing, and the
     * analyser silently resolved an empty scope for it.
     *
     * @return void
     */
    public function test_scope_with_an_empty_list_is_refused(): void {
        $form = $this->form();

        $errors = $form->check([
            'name' => 'Audit',
            'referential_id' => 1,
            'scope' => 'category',
            'categoryids' => [],
            'scopeids' => ['_qf__force_multiselect_submission'],
        ]);

        $this->assertArrayHasKey('categoryids', $errors);
        $this->assertSame(
            get_string('campaign_scope_category_required', 'local_qualiscope'),
            $errors['categoryids']
        );

        $errors = $form->check([
            'name' => 'Audit',
            'referential_id' => 1,
            'scope' => 'selected',
            'categoryids' => [],
            'scopeids' => ['_qf__force_multiselect_submission'],
        ]);

        $this->assertArrayHasKey('scopeids', $errors);
        $this->assertSame(
            get_string('campaign_scope_selected_required', 'local_qualiscope'),
            $errors['scopeids']
        );
    }

    /**
     * Test a campaign covering every course needs no list at all.
     *
     * @return void
     */
    public function test_scope_all_needs_no_list(): void {
        $form = $this->form();

        $errors = $form->check([
            'name' => 'Audit',
            'referential_id' => 1,
            'scope' => 'all',
            'categoryids' => [],
            'scopeids' => ['_qf__force_multiselect_submission'],
        ]);

        $this->assertArrayNotHasKey('categoryids', $errors);
        $this->assertArrayNotHasKey('scopeids', $errors);
    }

    /**
     * Test the course picker is an autocomplete excluding the front page.
     *
     * The campaigns page used to load and format every course record of the
     * site to render one option per course.
     *
     * @return void
     */
    public function test_course_picker_is_an_autocomplete_without_the_front_page(): void {
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        $html = $this->form()->render();

        // The core course element renders an empty multiple select fed on demand
        // by core/form-course-selector, and the front page is excluded from it.
        $this->assertStringContainsString('data-fieldtype="autocomplete"', $html);
        $this->assertStringContainsString('id_scopeids', $html);
        $this->assertMatchesRegularExpression('/name="scopeids\[\]"/', $html);
        $this->assertStringContainsString('data-exclude="' . SITEID . '"', $html);
        // The course name is fetched on demand, it is not part of the page.
        $this->assertStringNotContainsString(
            format_string($course->fullname, true, ['context' => $context]),
            $html
        );
    }

    /**
     * Builds the creation form, exposing its validation.
     *
     * @return object The form.
     */
    private function form(): object {
        return new class (
            new \moodle_url('/local/qualiscope/campaigns.php'),
            [
                'referentials' => [(object) ['id' => 1, 'name' => 'Qualiopi', 'version' => 'V9']],
                'categories' => [1 => 'Category'],
            ]
        ) extends campaign_create {
            /**
             * Runs the validation on a submission built by the caller.
             *
             * @param array $data Submitted data.
             * @return array The validation errors.
             */
            public function check(array $data): array {
                return $this->validation($data, []);
            }
        };
    }
}
