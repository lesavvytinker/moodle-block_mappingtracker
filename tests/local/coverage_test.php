<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

declare(strict_types=1);

namespace block_mappingtracker\local;

#[\PHPUnit\Framework\Attributes\CoversClass(coverage::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(display::class)]
/**
 * Tests for mapping coverage.
 *
 * @package    block_mappingtracker
 * @copyright  2026 Daniel Maloney
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_mappingtracker\local\coverage
 * @covers     \block_mappingtracker\local\display
 */
final class coverage_test extends \advanced_testcase {
    /**
     * A unit (U1) with element E1 (criteria 1.1, 1.2) and performance evidence PE1 ("at least two layouts"); a second
     * unit (U2) with one criterion. Course A links both units; course B (an RPL course) links U1.
     *
     * @return array
     */
    private function setup_data(): array {
        $gen = $this->getDataGenerator();
        /** @var \core_competency_generator $cgen */
        $cgen = $gen->get_plugin_generator('core_competency');
        $framework = $cgen->create_framework();
        $fwid = $framework->get('id');
        $c = fn(array $r) => $cgen->create_competency($r + ['competencyframeworkid' => $fwid]);
        $u1 = $c(['shortname' => 'U1 Layouts', 'idnumber' => 'U1']);
        $e1 = $c(['shortname' => '1 Analyse', 'idnumber' => 'U1.E1', 'parentid' => $u1->get('id')]);
        $pc11 = $c(['shortname' => '1.1 Confirm', 'idnumber' => 'U1.PC1.1', 'parentid' => $e1->get('id')]);
        $pc12 = $c(['shortname' => '1.2 Evaluate', 'idnumber' => 'U1.PC1.2', 'parentid' => $e1->get('id')]);
        $pe1 = $c(['shortname' => 'PE1 Produce layouts', 'idnumber' => 'U1.PE1', 'parentid' => $u1->get('id'),
            'description' => '<p>Produce and edit at least two layouts</p>']);
        $u2 = $c(['shortname' => 'U2 Typography', 'idnumber' => 'U2']);
        $pc21 = $c(['shortname' => '1.1 Research type', 'idnumber' => 'U2.PC1.1', 'parentid' => $u2->get('id')]);

        $a = $gen->create_course(['shortname' => 'A', 'fullname' => 'Course A']);
        $b = $gen->create_course(['shortname' => 'B', 'fullname' => 'Course B RPL']);
        foreach ([$u1, $e1, $pc11, $pe1, $u2, $pc21] as $comp) {
            \core_competency\api::add_competency_to_course($a->id, $comp->get('id'));
        }
        foreach ([$u1, $pc12] as $comp) {
            \core_competency\api::add_competency_to_course($b->id, $comp->get('id'));
        }
        $task1 = $gen->create_module('assign', ['course' => $a->id, 'name' => 'Task 1']);
        $task2 = $gen->create_module('assign', ['course' => $a->id, 'name' => 'Task 2', 'visible' => 0]);
        $portfolio = $gen->create_module('assign', ['course' => $b->id, 'name' => 'Portfolio']);
        $map = fn($cm, $comp) => \core_competency\api::add_competency_to_course_module($cm->cmid, $comp->get('id'));
        $map($task1, $pc11);
        $map($task1, $pe1);
        $map($task1, $pc21);
        $map($task2, $pe1);
        $map($portfolio, $pc12);
        return compact('u1', 'e1', 'pc11', 'pc12', 'pe1', 'u2', 'pc21', 'a', 'b', 'task1', 'task2', 'portfolio');
    }

    /**
     * Counts, targets from the default and the wording, gaps and totals.
     */
    public function test_for_course(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $d = $this->setup_data();
        $units = coverage::for_course((int) $d['a']->id);

        $this->assertSame([$d['u1']->get('id'), $d['u2']->get('id')], array_keys($units));
        $nodes = $units[$d['u1']->get('id')]->nodes;
        // Tree order: unit, element, criteria, then PE1.
        $this->assertSame([$d['u1']->get('id'), $d['e1']->get('id'), $d['pc11']->get('id'), $d['pc12']->get('id'),
            $d['pe1']->get('id')], array_keys($nodes));

        $pc11 = $nodes[$d['pc11']->get('id')];
        $this->assertSame([1, 1, 'default', coverage::MET], [$pc11->count, $pc11->target, $pc11->targetsource, $pc11->status]);
        $this->assertSame(['U2 Typography'], array_values($pc11->activities[0]->sharedwith));

        // PE1 asks for "at least two": the hidden Task 2 counts by default.
        $pe1 = $nodes[$d['pe1']->get('id')];
        $this->assertSame([2, 2, 'suggested', coverage::MET], [$pe1->count, $pe1->target, $pe1->targetsource, $pe1->status]);
        $this->assertFalse($pe1->activities[1]->visible);

        // 1.2 is only mapped in the RPL course: shown, not counted, and not added to course A.
        $pc12 = $nodes[$d['pc12']->get('id')];
        $this->assertSame([0, coverage::MISSING], [$pc12->count, $pc12->status]);
        $this->assertFalse($pc12->incourse);
        $this->assertCount(1, $pc12->elsewhere);
        $this->assertFalse($pc12->elsewhere[0]->counted);
        $this->assertSame('Portfolio', $pc12->elsewhere[0]->activities[0]->name);

        // Parents summarise; the unit's totals count items with targets.
        $this->assertNull($nodes[$d['e1']->get('id')]->target);
        $this->assertEquals((object) ['items' => 2, 'met' => 1], $nodes[$d['e1']->get('id')]->childtotals);
        $this->assertEquals(
            (object) ['items' => 3, 'met' => 2, 'under' => 0, 'missing' => 1, 'warnings' => 0],
            $units[$d['u1']->get('id')]->totals
        );
        $this->assertSame([$d['pc12']->get('id')], array_map(
            fn($n) => (int) $n->competency->id,
            coverage::gaps($units[$d['u1']->get('id')])
        ));
    }

    /**
     * Settings: hidden activities, chosen courses, the default target and suggestions.
     */
    public function test_settings_and_counted_courses(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $d = $this->setup_data();
        $u1 = $d['u1']->get('id');

        set_config('counthidden', 0, 'block_mappingtracker');
        $pe1 = coverage::for_course((int) $d['a']->id)[$u1]->nodes[$d['pe1']->get('id')];
        $this->assertSame([1, coverage::UNDER], [$pe1->count, $pe1->status]);

        $pc12 = coverage::for_course((int) $d['a']->id, [(int) $d['b']->id])[$u1]->nodes[$d['pc12']->get('id')];
        $this->assertSame([1, coverage::MET], [$pc12->count, $pc12->status]);
        $this->assertTrue($pc12->elsewhere[0]->counted);

        set_config('suggesttargets', 0, 'block_mappingtracker');
        set_config('defaulttarget', 3, 'block_mappingtracker');
        $pe1 = coverage::for_course((int) $d['a']->id)[$u1]->nodes[$d['pe1']->get('id')];
        $this->assertSame([3, 'default'], [$pe1->target, $pe1->targetsource]);

        set_config('defaulttarget', 0, 'block_mappingtracker');
        $pc11 = coverage::for_course((int) $d['a']->id)[$u1]->nodes[$d['pc11']->get('id')];
        $this->assertSame([null, coverage::NOTARGET], [$pc11->target, $pc11->status]);

        // Hidden or finished courses are left out altogether.
        update_course((object) ['id' => $d['b']->id, 'visible' => 0]);
        $pc12 = coverage::for_course((int) $d['a']->id)[$u1]->nodes[$d['pc12']->get('id')];
        $this->assertSame([], $pc12->elsewhere);
        $this->assertSame([], coverage::sharing_courses((int) $d['a']->id));
    }

    /**
     * Targets set by a manager win, and can be reset.
     */
    public function test_set_target(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $d = $this->setup_data();
        $u1 = $d['u1']->get('id');
        coverage::set_target((int) $d['pc11']->get('id'), 2);
        coverage::set_target((int) $d['e1']->get('id'), 1);
        $nodes = coverage::for_course((int) $d['a']->id)[$u1]->nodes;
        $this->assertSame([2, 'set', coverage::UNDER], [$nodes[$d['pc11']->get('id')]->target,
            $nodes[$d['pc11']->get('id')]->targetsource, $nodes[$d['pc11']->get('id')]->status]);
        $this->assertSame(coverage::MISSING, $nodes[$d['e1']->get('id')]->status);

        coverage::set_target((int) $d['pc11']->get('id'), 3);
        coverage::set_target((int) $d['pc11']->get('id'), null);
        $nodes = coverage::for_course((int) $d['a']->id)[$u1]->nodes;
        $this->assertSame([1, 'default'], [$nodes[$d['pc11']->get('id')]->target,
            $nodes[$d['pc11']->get('id')]->targetsource]);
    }

    /**
     * Wording gives targets.
     */
    public function test_suggested_target(): void {
        $s = fn(string $text) => coverage::suggested_target((object) ['shortname' => $text, 'description' => '']);
        $this->assertSame(2, $s('PE1 Produce at least two drawings'));
        $this->assertSame(3, $s('Complete at least 3 observations'));
        $this->assertSame(1, $s('research at least one or more new ways'));
        $this->assertNull($s('Evidence must be provided at least once'));
        $this->assertNull($s('Evaluate design brief specifications'));
    }

    /**
     * The matrix: activities across, items down, with ticks, counts and targets.
     */
    public function test_matrix(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $d = $this->setup_data();
        $unit = coverage::for_course((int) $d['a']->id)[$d['u1']->get('id')];
        [$columns, $rows] = coverage::matrix($unit, $d['a']);
        $this->assertSame(['ID number', 'Item', 'Task 1 (Assignment)', 'Task 2 (Assignment)',
            'B: Portfolio (Assignment) (not counted)', 'Mapped', 'Target', 'Status'], $columns);
        $this->assertSame(['U1.PC1.1', '        1.1 Confirm', '✓', '', '', 1, 1, 'Target met'], $rows[2]);
        $this->assertSame(['U1.PC1.2', '        1.2 Evaluate', '', '', '✓', 0, 1, 'Not mapped'], $rows[3]);
        $this->assertSame(['U1.PE1', '    PE1 Produce layouts', '✓', '✓', '', 2, 2, 'Target met'], $rows[4]);
    }

    /**
     * The block's content: a summary per unit and the gaps.
     */
    public function test_block_display(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $d = $this->setup_data();
        $html = display::block((int) $d['a']->id, coverage::for_course((int) $d['a']->id));
        $this->assertStringContainsString('U1 Layouts', $html);
        $this->assertStringContainsString('2 of 3 met · 1 not mapped', $html);
        $this->assertStringContainsString('1.2 Evaluate', $html);
        $this->assertStringContainsString('All targets met.', $html);
    }

    /**
     * The courses chosen in the block's settings.
     */
    public function test_counted_courses(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $d = $this->setup_data();
        $this->assertSame([], coverage::counted_courses((int) $d['a']->id));
        $this->assertSame([(int) $d['b']->id => 'Course B RPL'], coverage::sharing_courses((int) $d['a']->id));
        $DB->insert_record('block_instances', ['blockname' => 'mappingtracker',
            'parentcontextid' => \context_course::instance($d['a']->id)->id, 'showinsubcontexts' => 0,
            'pagetypepattern' => 'course-view-*', 'defaultregion' => 'side-pre', 'defaultweight' => 0,
            'configdata' => base64_encode(serialize((object) ['countcourses' => [(string) $d['b']->id]])),
            'timecreated' => time(), 'timemodified' => time()]);
        $this->assertSame([(int) $d['b']->id], coverage::counted_courses((int) $d['a']->id));
    }

    /**
     * Looking for mapping only in a category: this course's (and its subcategories) by default, a chosen one, or
     * anywhere; courses chosen to count are always included.
     */
    public function test_search_categories(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $d = $this->setup_data();
        $term = $this->getDataGenerator()->create_category(['name' => 'Term 4 2027']);
        $qual = $this->getDataGenerator()->create_category(['name' => 'Cert IV', 'parent' => $term->id]);
        $old = $this->getDataGenerator()->create_category(['name' => 'Term 4 2026']);
        move_courses([$d['a']->id], $qual->id);
        move_courses([$d['b']->id], $old->id);
        $a = (int) $d['a']->id;
        $u1 = $d['u1']->get('id');
        $pc12 = fn(array $countcourses, ?array $categories) => coverage::for_course(
            $a,
            $countcourses,
            $categories
        )[$u1]->nodes[$d['pc12']->get('id')];

        // Default: this course's category, and B is in last year's category.
        $default = coverage::search_categories($a);
        $this->assertSame([(int) $qual->id], $default);
        $this->assertSame([], $pc12([], $default)->elsewhere);
        $this->assertSame([], coverage::sharing_courses($a, $default));

        // Anywhere: B shows.
        $this->assertNull(coverage::search_categories($a, (object) ['searchcategory' => coverage::SEARCH_ANYWHERE]));
        $this->assertCount(1, $pc12([], null)->elsewhere);

        // A chosen category with its subcategories.
        move_courses([$d['b']->id], $term->id);
        $termwide = coverage::search_categories($a, (object) ['searchcategory' => $term->id, 'subcategories' => 1]);
        $this->assertEqualsCanonicalizing([(int) $term->id, (int) $qual->id], $termwide);
        $this->assertCount(1, $pc12([], $termwide)->elsewhere);
        $this->assertSame([(int) $qual->id], coverage::search_categories($a, (object) ['subcategories' => 0]));

        // A course chosen to count is included wherever it is.
        move_courses([$d['b']->id], $old->id);
        $counted = $pc12([(int) $d['b']->id], $default);
        $this->assertTrue($counted->elsewhere[0]->counted);
        $this->assertSame(coverage::MET, $counted->status);
    }

    /**
     * Mapped questions: listed under their items, never counted, flagged when their quiz isn't linked or they're in no
     * quiz, and fixed in one go; competencies outside the course's units are listed too.
     */
    public function test_questions(): void {
        global $CFG, $DB;
        if (!coverage::questions_available()) {
            $this->markTestSkipped('Needs the Question competencies plugin (qbank_competencies).');
        }
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $d = $this->setup_data();
        $gen = $this->getDataGenerator();
        $a = (int) $d['a']->id;
        $u1 = $d['u1']->get('id');
        $quiz = $gen->create_module('quiz', ['course' => $a, 'name' => 'Knowledge quiz']);
        $qgen = $gen->get_plugin_generator('core_question');
        $category = $qgen->create_question_category(['contextid' => \context_module::instance($quiz->cmid)->id]);
        $inquiz = $qgen->create_question('truefalse', null, ['category' => $category->id, 'name' => 'Copyright basics']);
        $spare = $qgen->create_question('truefalse', null, ['category' => $category->id, 'name' => 'Spare question']);
        quiz_add_quiz_question($inquiz->id, $DB->get_record('quiz', ['id' => $quiz->id]));
        $api = '\qbank_competencies\local\api';
        $other = $gen->get_plugin_generator('core_competency')->create_competency([
            'competencyframeworkid' => $d['u1']->get('competencyframeworkid'), 'shortname' => 'U3 Elsewhere',
            'idnumber' => 'U3']);
        $api::set_competencies($api::get_entryid($inquiz->id), [(int) $d['pc11']->get('id'), (int) $other->get('id')]);
        $api::set_competencies($api::get_entryid($spare->id), [(int) $d['pe1']->get('id')]);

        $units = coverage::for_course($a);
        $pc11 = $units[$u1]->nodes[$d['pc11']->get('id')];
        $this->assertSame(1, $pc11->count, 'Questions never count.');
        $this->assertSame('Copyright basics', $pc11->questions[0]->name);
        $this->assertFalse($pc11->questions[0]->quizzes[0]->linked);
        $this->assertCount(1, $pc11->fixes);
        $this->assertSame((int) $quiz->cmid, $pc11->fixes[0]->cmid);
        $pe1 = $units[$u1]->nodes[$d['pe1']->get('id')];
        $this->assertTrue($pe1->questions[0]->unused);
        $this->assertSame(1, $pe1->unusedquestions);
        $this->assertSame(2, $units[$u1]->totals->warnings);
        $this->assertStringContainsString('⚠ 3 to fix', display::block($a, $units));

        // A competency outside the course's units, and not in the course.
        $outside = coverage::questions_outside_units($a, $units);
        $this->assertSame([(int) $other->get('id')], array_keys($outside));
        $this->assertFalse($outside[$other->get('id')]->incourse);
        $this->assertSame((int) $quiz->cmid, $outside[$other->get('id')]->fixes[0]->cmid);

        // Fixing links the quiz (adding the competency to the course first where needed).
        coverage::fix($a, (int) $d['pc11']->get('id'), (int) $quiz->cmid);
        coverage::fix($a, (int) $other->get('id'), (int) $quiz->cmid);
        $this->assertTrue($DB->record_exists('competency_coursecomp', ['courseid' => $a, 'competencyid' => $other->get('id')]));
        $units = coverage::for_course($a);
        $pc11 = $units[$u1]->nodes[$d['pc11']->get('id')];
        $this->assertSame([], $pc11->fixes);
        $this->assertSame(2, $pc11->count, 'The linked quiz now counts, as an activity.');
        $this->assertSame(1, $units[$u1]->totals->warnings);
    }

    /**
     * A category: each course's own coverage, and units combined across the courses that deliver them.
     */
    public function test_for_category(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $d = $this->setup_data();
        $gen = $this->getDataGenerator();
        $term = $gen->create_category(['name' => 'Term 1']);
        $sub = $gen->create_category(['name' => 'RPL', 'parent' => $term->id]);
        move_courses([$d['a']->id], $term->id);
        move_courses([$d['b']->id], $sub->id);
        $u1 = $d['u1']->get('id');

        $all = coverage::for_category((int) $term->id);
        $this->assertEqualsCanonicalizing([(int) $d['a']->id, (int) $d['b']->id], array_keys($all->courses));
        $this->assertSame([(int) $d['a']->id, (int) $d['b']->id], $all->units[$u1]->courses);
        // Course A misses 1.2; course B (the RPL course) maps it: met between them.
        $this->assertSame(2, $all->courses[$d['a']->id]->units[$u1]->totals->met);
        $this->assertSame(1, $all->courses[$d['b']->id]->units[$u1]->totals->met);
        $this->assertSame(3, $all->units[$u1]->totals->met);
        $this->assertSame(0, $all->units[$u1]->totals->missing);

        $termonly = coverage::for_category((int) $term->id, false);
        $this->assertSame([(int) $d['a']->id], array_keys($termonly->courses));
        $this->assertSame(1, $termonly->units[$u1]->totals->missing);
    }
}
