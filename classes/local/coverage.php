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

namespace block_mappingtracker\local;

/**
 * Mapping coverage: how many activities are mapped to each item of the units a course delivers, against targets.
 *
 * Moodle links competencies to whole activities (course modules), so an "instance" of mapping is one activity linked
 * directly to the item. A unit is the top of a competency's tree in its framework.
 *
 * @package    block_mappingtracker
 * @copyright  2026 Daniel Maloney
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coverage {
    /** Targets table. */
    public const TABLE = 'block_mappingtracker_target';

    /** Status: target met. */
    public const MET = 'met';
    /** Status: mapped, but below target. */
    public const UNDER = 'under';
    /** Status: target set, nothing mapped. */
    public const MISSING = 'missing';
    /** Status: no target (counts shown for information). */
    public const NOTARGET = 'notarget';

    /** Search setting: this course's category. */
    public const SEARCH_OWN_CATEGORY = 0;
    /** Search setting: anywhere on the site. */
    public const SEARCH_ANYWHERE = -1;

    /** Words for numbers in "at least two". */
    private const NUMBERS = ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7,
        'eight' => 8, 'nine' => 9, 'ten' => 10];

    /**
     * The coverage of every unit the course delivers.
     *
     * @param int $courseid
     * @param int[] $countcourseids Other courses whose mapping counts towards targets here
     * @param int[]|null $categoryids Only look for mapping in other courses in these categories (null: anywhere).
     *     Courses chosen to count are always included.
     * @return \stdClass[] unitid => unit: competency, framework, nodes (in tree order), totals
     */
    public static function for_course(int $courseid, array $countcourseids = [], ?array $categoryids = null): array {
        global $DB;
        $coursecomps = $DB->get_fieldset_select('competency_coursecomp', 'competencyid', 'courseid = ?', [$courseid]);
        if (!$coursecomps) {
            return [];
        }
        $incourse = array_fill_keys(array_map('intval', $coursecomps), true);

        // The units: the roots of the course's competencies.
        $rootids = [];
        [$in, $params] = $DB->get_in_or_equal(array_keys($incourse));
        foreach ($DB->get_records_select('competency', "id $in", $params, '', 'id, path') as $comp) {
            $rootids[self::root_of($comp)] = true;
        }

        // Every item in those units, in tree order.
        $units = [];
        $allids = [];
        foreach (array_keys($rootids) as $rootid) {
            $root = $DB->get_record('competency', ['id' => $rootid]);
            if (!$root) {
                continue;
            }
            $descendants = $DB->get_records_select(
                'competency',
                $DB->sql_like('path', ':path'),
                ['path' => $root->path . $root->id . '/%'],
                'sortorder, id'
            );
            $nodes = self::tree_order($root, $descendants);
            $units[$rootid] = (object) [
                'competency' => $root,
                'framework' => $DB->get_record(
                    'competency_framework',
                    ['id' => $root->competencyframeworkid],
                    'id, shortname, idnumber'
                ),
                'nodes' => $nodes,
            ];
            $allids = array_merge($allids, array_keys($nodes));
        }
        if (!$units) {
            return [];
        }

        // Which activities, anywhere, are linked to which items.
        [$in, $params] = $DB->get_in_or_equal($allids);
        $links = $DB->get_recordset_sql("SELECT mc.id, mc.competencyid, cm.id AS cmid, cm.course
                                           FROM {competency_modulecomp} mc
                                           JOIN {course_modules} cm ON cm.id = mc.cmid AND cm.deletioninprogress = 0
                                          WHERE mc.competencyid $in", $params);
        $bycomp = [];
        foreach ($links as $link) {
            $bycomp[$link->competencyid][(int) $link->course][] = (int) $link->cmid;
        }
        $links->close();

        $targets = $DB->get_records_select_menu(self::TABLE, "competencyid $in", $params, '', 'competencyid, target');
        $shared = self::shared_units($courseid);
        $counthidden = (bool) get_config('block_mappingtracker', 'counthidden');
        $countcourses = array_fill_keys(array_map('intval', $countcourseids), true);
        $othercourses = [];
        $questions = self::question_overview($courseid);

        foreach ($units as $unitid => $unit) {
            $totals = (object) ['items' => 0, 'met' => 0, 'under' => 0, 'missing' => 0, 'warnings' => 0];
            foreach ($unit->nodes as $id => $node) {
                $node->incourse = isset($incourse[$id]);
                self::add_questions($node, $questions[$id] ?? null, $courseid);
                $totals->warnings += count($node->fixes) + $node->unusedquestions;
                [$node->target, $node->targetsource] = self::target_for($node, $targets);
                $node->suggested = self::suggested_target($node->competency);
                $node->activities = [];
                $node->elsewhere = [];
                $node->count = 0;
                foreach ($bycomp[$id] ?? [] as $cid => $cmids) {
                    if ($cid === $courseid) {
                        foreach ($cmids as $cmid) {
                            $activity = self::activity($courseid, $cmid);
                            if (!$activity) {
                                continue;
                            }
                            $activity->sharedwith = array_values(array_diff_key($shared[$cmid] ?? [], [$unitid => 1]));
                            $activity->counted = $activity->visible || $counthidden;
                            $node->count += (int) $activity->counted;
                            $node->activities[] = $activity;
                        }
                        continue;
                    }
                    $othercourses[$cid] ??= self::other_course($cid, isset($countcourses[$cid]) ? null : $categoryids);
                    if (!$othercourses[$cid]) {
                        continue;
                    }
                    $counted = isset($countcourses[$cid]);
                    $activities = array_values(array_filter(array_map(fn($cmid) => self::activity($cid, $cmid), $cmids)));
                    if (!$activities) {
                        continue;
                    }
                    foreach ($activities as $activity) {
                        $activity->counted = $counted && ($activity->visible || $counthidden);
                        $node->count += (int) $activity->counted;
                    }
                    $node->elsewhere[] = (object) ['course' => $othercourses[$cid], 'counted' => $counted,
                        'activities' => $activities];
                }
                $node->status = self::status($node->count, $node->target);
                if ($node->status !== self::NOTARGET) {
                    $totals->items++;
                    $totals->{$node->status}++;
                }
            }
            // Parents summarise their children.
            foreach ($unit->nodes as $node) {
                $node->childtotals = (object) ['items' => 0, 'met' => 0];
                foreach ($node->children as $childid) {
                    $child = $unit->nodes[$childid];
                    if ($child->status !== self::NOTARGET) {
                        $node->childtotals->items++;
                        $node->childtotals->met += (int) ($child->status === self::MET);
                    }
                }
            }
            $unit->totals = $totals;
        }
        uasort($units, fn($a, $b) => strcmp($a->competency->shortname, $b->competency->shortname));
        return $units;
    }

    /**
     * Coverage across a category: each course's own coverage of each unit (its own activities only), and each unit's
     * combined coverage across every course in the category that delivers it, so a unit split across courses reads
     * as covered when its courses cover it between them.
     *
     * @param int $categoryid
     * @param bool $subcategories
     * @return \stdClass courses: courseid => {course, units: unitid => {totals}}, units: unitid => {competency,
     *     framework, courses: courseid[], totals}
     */
    public static function for_category(int $categoryid, bool $subcategories = true): \stdClass {
        global $DB;
        $path = $DB->get_field('course_categories', 'path', ['id' => $categoryid], MUST_EXIST);
        $where = 'c.category = :cat';
        $params = ['cat' => $categoryid];
        if ($subcategories) {
            $where = '(' . $where . ' OR ' . $DB->sql_like('cc.path', ':path') . ')';
            $params['path'] = $path . '/%';
        }
        $courses = $DB->get_records_sql("SELECT c.id, c.fullname, c.shortname, c.visible, c.category, c.enddate
                                           FROM {course} c
                                           JOIN {course_categories} cc ON cc.id = c.category
                                          WHERE $where
                                       ORDER BY cc.sortorder, c.sortorder", $params);
        $result = (object) ['courses' => [], 'units' => []];
        $combined = [];
        foreach ($courses as $course) {
            // Own activities only: no other course is searched or counted here.
            $units = self::for_course((int) $course->id, [], []);
            if (!$units) {
                continue;
            }
            $outside = self::questions_outside_units((int) $course->id, $units);
            $result->courses[$course->id] = (object) ['course' => $course, 'units' => [],
                'outsidewarnings' => array_sum(array_map(fn($n) => count($n->fixes) + $n->unusedquestions, $outside))];
            foreach ($units as $unitid => $unit) {
                $result->courses[$course->id]->units[$unitid] = (object) ['totals' => $unit->totals];
                $result->units[$unitid] ??= (object) ['competency' => $unit->competency, 'framework' => $unit->framework,
                    'courses' => [], 'warnings' => 0];
                $result->units[$unitid]->courses[] = (int) $course->id;
                $result->units[$unitid]->warnings += $unit->totals->warnings;
                foreach ($unit->nodes as $id => $node) {
                    $combined[$unitid][$id] ??= ['target' => $node->target, 'count' => 0];
                    $combined[$unitid][$id]['count'] += $node->count;
                }
            }
        }
        foreach ($result->units as $unitid => $unit) {
            $totals = (object) ['items' => 0, 'met' => 0, 'under' => 0, 'missing' => 0, 'warnings' => $unit->warnings];
            foreach ($combined[$unitid] as $item) {
                $status = self::status($item['count'], $item['target']);
                if ($status !== self::NOTARGET) {
                    $totals->items++;
                    $totals->$status++;
                }
            }
            $unit->totals = $totals;
        }
        uasort($result->units, fn($a, $b) => strcmp($a->competency->shortname, $b->competency->shortname));
        return $result;
    }

    /**
     * Whether question mappings (the Question competencies plugin, qbank_competencies) are available.
     *
     * @return bool
     */
    public static function questions_available(): bool {
        if (!class_exists('\\qbank_competencies\\local\\api') || !get_config('qbank_competencies', 'version')) {
            return false;
        }
        return !class_exists('\\core\\plugininfo\\qbank') || \core\plugininfo\qbank::is_plugin_enabled('qbank_competencies');
    }

    /**
     * The course's question mappings, from the Question competencies plugin.
     *
     * @param int $courseid
     * @return array competencyid => overview (see qbank_competencies' get_course_overview()), or [] without the plugin
     */
    public static function question_overview(int $courseid): array {
        if (!self::questions_available()) {
            return [];
        }
        try {
            return \qbank_competencies\local\api::get_course_overview($courseid);
        } catch (\Throwable $e) {
            debugging('Mapping tracker could not read question mappings: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }

    /**
     * Competencies that questions in the course are mapped to but that belong to no unit the course delivers.
     *
     * @param int $courseid
     * @param \stdClass[] $units From {@see for_course()}
     * @return \stdClass[] node-like objects: competency, incourse, questions, fixes, unusedquestions
     */
    public static function questions_outside_units(int $courseid, array $units): array {
        global $DB;
        $inunits = [];
        foreach ($units as $unit) {
            $inunits += $unit->nodes;
        }
        $outside = [];
        foreach (self::question_overview($courseid) as $competencyid => $overview) {
            if (isset($inunits[$competencyid])) {
                continue;
            }
            $competency = $DB->get_record('competency', ['id' => $competencyid]);
            if (!$competency) {
                continue;
            }
            $node = (object) ['competency' => $competency, 'incourse' => (bool) $overview->incourse, 'children' => []];
            self::add_questions($node, $overview, $courseid);
            $outside[$competencyid] = $node;
        }
        return $outside;
    }

    /**
     * Give a node its mapped questions, the quizzes that use them, and what needs fixing: quizzes that use a question
     * mapped to the item but don't have the item in their competency settings, and the item missing from the course.
     * Questions never count towards a target.
     *
     * @param \stdClass $node
     * @param \stdClass|null $overview From {@see question_overview()}
     * @param int $courseid
     */
    private static function add_questions(\stdClass $node, ?\stdClass $overview, int $courseid): void {
        $node->questions = [];
        $node->fixes = [];
        $node->unusedquestions = 0;
        if (!$overview) {
            return;
        }
        foreach ($overview->questions as $question) {
            $quizzes = [];
            foreach ($question->quizzes as $quiz) {
                $activity = self::activity($courseid, (int) $quiz->cmid);
                if (!$activity) {
                    continue;
                }
                $activity->random = (bool) $quiz->random;
                $activity->linked = (bool) $quiz->linked;
                $quizzes[] = $activity;
                if (!$quiz->linked) {
                    $node->fixes[(int) $quiz->cmid] = (object) ['cmid' => (int) $quiz->cmid, 'name' => $activity->name,
                        'url' => $activity->url];
                }
            }
            $context = \context::instance_by_id((int) $question->contextid, IGNORE_MISSING);
            $node->questions[] = (object) [
                'name' => format_string($question->name),
                'previewurl' => ($context && $question->questionid)
                    ? \qbank_competencies\local\api::preview_url((int) $question->questionid, $context)->out(false) : null,
                'editurl' => $question->questionid
                    ? \qbank_competencies\local\api::edit_url((int) $question->questionid)->out(false) : null,
                'quizzes' => $quizzes,
                'unused' => !$quizzes,
                'random' => $quizzes && !array_filter($quizzes, fn($q) => !$q->random),
            ];
            $node->unusedquestions += (int) !$quizzes;
        }
        $node->fixes = array_values($node->fixes);
        // Mapped questions, but the item isn't one of the course's competencies: nothing can be linked to it yet.
        if (!$node->incourse && $node->questions && !$node->fixes) {
            $node->fixes[] = (object) ['cmid' => 0, 'name' => null, 'url' => null];
        }
    }

    /**
     * Fix a flag: add the item to the course's competencies if needed, then to the quiz's.
     *
     * @param int $courseid
     * @param int $competencyid
     * @param int $cmid 0 to only add the item to the course
     */
    public static function fix(int $courseid, int $competencyid, int $cmid): void {
        global $DB;
        if (!$DB->record_exists('competency_coursecomp', ['courseid' => $courseid, 'competencyid' => $competencyid])) {
            \core_competency\api::add_competency_to_course($courseid, $competencyid);
        }
        if ($cmid) {
            $cm = get_coursemodule_from_id('', $cmid, $courseid, false, MUST_EXIST);
            if (!$DB->record_exists('competency_modulecomp', ['cmid' => $cm->id, 'competencyid' => $competencyid])) {
                \core_competency\api::add_competency_to_course_module($cm, $competencyid);
            }
        }
    }

    /**
     * The items short of their targets, gaps (nothing mapped) first.
     *
     * @param \stdClass $unit From {@see for_course()}
     * @return \stdClass[] nodes
     */
    public static function gaps(\stdClass $unit): array {
        $gaps = array_filter($unit->nodes, fn($n) => in_array($n->status, [self::MISSING, self::UNDER], true));
        uasort($gaps, fn($a, $b) => [$a->status !== self::MISSING, $a->order] <=> [$b->status !== self::MISSING, $b->order]);
        return array_values($gaps);
    }

    /**
     * A unit's mapping matrix: one row per item, one column per mapped activity (this course first, in course order,
     * then other courses' activities), with the count, target and status.
     *
     * @param \stdClass $unit From {@see for_course()}
     * @param \stdClass $course
     * @return array [columns, rows]
     */
    public static function matrix(\stdClass $unit, \stdClass $course): array {
        $str = fn($id, $a = null) => get_string($id, 'block_mappingtracker', $a);
        $here = [];
        $elsewhere = [];
        foreach ($unit->nodes as $node) {
            foreach ($node->activities as $activity) {
                $here[$activity->cmid] = $activity->name . ' (' . $activity->modtitle . ')';
            }
            foreach ($node->elsewhere as $other) {
                foreach ($other->activities as $activity) {
                    $elsewhere[$activity->cmid] = $other->course->shortname . ': ' . $activity->name . ' (' .
                        $activity->modtitle . ')' . ($other->counted ? '' : ' ' . $str('notcounted'));
                }
            }
        }
        // Activities in the order they appear in the course.
        $order = array_flip(array_keys(get_fast_modinfo($course)->cms));
        uksort($here, fn($a, $b) => ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX));
        $activitycolumns = $here + $elsewhere;

        $columns = array_merge(
            [get_string('idnumber'), $str('item')],
            array_values($activitycolumns),
            [$str('mapped'), $str('target'), $str('status')]
        );
        $rows = [];
        foreach ($unit->nodes as $node) {
            $mapped = [];
            foreach ($node->activities as $activity) {
                $mapped[$activity->cmid] = true;
            }
            foreach ($node->elsewhere as $other) {
                foreach ($other->activities as $activity) {
                    $mapped[$activity->cmid] = true;
                }
            }
            $row = [$node->competency->idnumber, str_repeat('    ', $node->depth) . display::name($node, true)];
            foreach (array_keys($activitycolumns) as $cmid) {
                $row[] = isset($mapped[$cmid]) ? '✓' : '';
            }
            // Parents with nothing mapped to them and no target of their own are headings.
            $row[] = ($node->children && !$node->count && $node->target === null) ? '' : $node->count;
            $row[] = $node->target ?? '';
            $row[] = $node->status === self::NOTARGET ? '' : $str('status_' . $node->status);
            $rows[] = $row;
        }
        return [$columns, $rows];
    }

    /**
     * Other courses that link competencies from the same units, for choosing which ones count.
     *
     * @param int $courseid
     * @param int[]|null $categoryids Only courses in these categories (null: anywhere)
     * @return array courseid => course name
     */
    public static function sharing_courses(int $courseid, ?array $categoryids = null): array {
        global $DB;
        $courses = [];
        $units = self::for_course($courseid, [], $categoryids);
        foreach ($units as $unit) {
            foreach ($unit->nodes as $node) {
                foreach ($node->elsewhere as $other) {
                    $courses[$other->course->id] = $other->course->fullname;
                }
            }
        }
        // Courses that link the units without any mapped activities yet can still be chosen.
        $unitids = array_keys($units);
        if ($unitids) {
            [$in, $params] = $DB->get_in_or_equal($unitids);
            $sql = "SELECT DISTINCT cc.courseid
                      FROM {competency_coursecomp} cc
                      JOIN {competency} c ON c.id = cc.competencyid
                     WHERE (c.id $in";
            foreach ($unitids as $unitid) {
                $sql .= ' OR ' . $DB->sql_like('c.path', '?');
                $params[] = '%/' . $unitid . '/%';
            }
            $sql .= ') AND cc.courseid <> ?';
            $params[] = $courseid;
            foreach ($DB->get_fieldset_sql($sql, $params) as $cid) {
                if ($course = self::other_course((int) $cid, $categoryids)) {
                    $courses[$course->id] = $course->fullname;
                }
            }
        }
        \core_collator::asort($courses);
        return $courses;
    }

    /**
     * The settings of this course's block (empty if there's no block).
     *
     * @param int $courseid
     * @return \stdClass
     */
    public static function block_config(int $courseid): \stdClass {
        global $DB;
        $context = \context_course::instance($courseid);
        $instance = $DB->get_record(
            'block_instances',
            ['blockname' => 'mappingtracker', 'parentcontextid' => $context->id],
            'id, configdata',
            IGNORE_MULTIPLE
        );
        if (!$instance || !$instance->configdata) {
            return new \stdClass();
        }
        return (object) unserialize_object(base64_decode($instance->configdata));
    }

    /**
     * The courses chosen to count in this course's block.
     *
     * @param int $courseid
     * @param \stdClass|null $config The block's settings, if already loaded
     * @return int[]
     */
    public static function counted_courses(int $courseid, ?\stdClass $config = null): array {
        $config ??= self::block_config($courseid);
        return array_map('intval', array_values((array) ($config->countcourses ?? [])));
    }

    /**
     * Where to look for mapping in other courses, from the block's settings: by default this course's category and
     * its subcategories, so older intakes in other categories don't crowd the page.
     *
     * @param int $courseid
     * @param \stdClass|null $config The block's settings, if already loaded
     * @return int[]|null Category ids, or null for anywhere on the site
     */
    public static function search_categories(int $courseid, ?\stdClass $config = null): ?array {
        global $DB;
        $config ??= self::block_config($courseid);
        $category = (int) ($config->searchcategory ?? self::SEARCH_OWN_CATEGORY);
        if ($category === self::SEARCH_ANYWHERE) {
            return null;
        }
        if ($category === self::SEARCH_OWN_CATEGORY || !$DB->record_exists('course_categories', ['id' => $category])) {
            $category = (int) $DB->get_field('course', 'category', ['id' => $courseid]);
        }
        $ids = [$category];
        if ((int) ($config->subcategories ?? 1)) {
            $path = $DB->get_field('course_categories', 'path', ['id' => $category]);
            $ids = array_merge($ids, array_map('intval', $DB->get_fieldset_select(
                'course_categories',
                'id',
                $DB->sql_like('path', '?'),
                [$path . '/%']
            )));
        }
        return $ids;
    }

    /**
     * Set or clear an item's target (for every course delivering its unit).
     *
     * @param int $competencyid
     * @param int|null $target Null to go back to the default or suggested target
     */
    public static function set_target(int $competencyid, ?int $target): void {
        global $DB, $USER;
        $existing = $DB->get_record(self::TABLE, ['competencyid' => $competencyid]);
        if ($target === null) {
            if ($existing) {
                $DB->delete_records(self::TABLE, ['id' => $existing->id]);
            }
            return;
        }
        $record = (object) ['competencyid' => $competencyid, 'target' => max(0, $target), 'usermodified' => $USER->id,
            'timemodified' => time()];
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record(self::TABLE, $record);
        } else {
            $record->timecreated = time();
            $DB->insert_record(self::TABLE, $record);
        }
    }

    /**
     * The number an item's wording asks for, e.g. "at least two layouts" => 2.
     *
     * @param \stdClass $competency
     * @return int|null
     */
    public static function suggested_target(\stdClass $competency): ?int {
        $text = $competency->shortname . ' ' . strip_tags((string) $competency->description);
        if (!preg_match('/\bat\s+least\s+(\d+|' . implode('|', array_keys(self::NUMBERS)) . ')\b/i', $text, $m)) {
            return null;
        }
        $n = is_numeric($m[1]) ? (int) $m[1] : self::NUMBERS[strtolower($m[1])];
        return $n > 0 ? $n : null;
    }

    /**
     * A competency's unit: the top of its tree.
     *
     * @param \stdClass $competency With path
     * @return int
     */
    public static function root_of(\stdClass $competency): int {
        $ids = array_values(array_filter(explode('/', trim((string) $competency->path, '/'))));
        return $ids ? (int) $ids[0] : (int) $competency->id;
    }

    /**
     * Status from a count and target.
     *
     * @param int $count
     * @param int|null $target
     * @return string
     */
    public static function status(int $count, ?int $target): string {
        if (!$target) {
            return self::NOTARGET;
        }
        return $count >= $target ? self::MET : ($count ? self::UNDER : self::MISSING);
    }

    /**
     * An item's target: set by a manager, otherwise suggested by its wording, otherwise the site default. Only items
     * with nothing below them (criteria, evidence items) get a default; parents summarise their children.
     *
     * @param \stdClass $node
     * @param array $targets competencyid => target
     * @return array [target or null, source: set, suggested, default or none]
     */
    private static function target_for(\stdClass $node, array $targets): array {
        if (isset($targets[$node->competency->id])) {
            return [(int) $targets[$node->competency->id], 'set'];
        }
        if ($node->children) {
            return [null, 'none'];
        }
        if (get_config('block_mappingtracker', 'suggesttargets') && ($suggested = self::suggested_target($node->competency))) {
            return [$suggested, 'suggested'];
        }
        $default = (int) get_config('block_mappingtracker', 'defaulttarget');
        return [$default ?: null, $default ? 'default' : 'none'];
    }

    /**
     * Nodes in tree order with depth (0 for the unit) and children.
     *
     * @param \stdClass $root
     * @param \stdClass[] $descendants
     * @return \stdClass[] id => node
     */
    private static function tree_order(\stdClass $root, array $descendants): array {
        $children = [];
        foreach ($descendants as $comp) {
            $children[(int) $comp->parentid][] = $comp;
        }
        $nodes = [];
        $walk = function (\stdClass $comp, int $depth) use (&$walk, &$nodes, $children) {
            $nodes[(int) $comp->id] = (object) [
                'competency' => $comp,
                'depth' => $depth,
                'order' => count($nodes),
                'children' => array_map(fn($c) => (int) $c->id, $children[(int) $comp->id] ?? []),
            ];
            foreach ($children[(int) $comp->id] ?? [] as $child) {
                $walk($child, $depth + 1);
            }
        };
        $walk($root, 0);
        return $nodes;
    }

    /**
     * For each activity in the course, the units (by name) it's mapped to.
     *
     * @param int $courseid
     * @return array cmid => [unitid => unit shortname]
     */
    private static function shared_units(int $courseid): array {
        global $DB;
        $rows = $DB->get_records_sql("SELECT mc.id, mc.cmid, c.id AS competencyid, c.path
                                        FROM {competency_modulecomp} mc
                                        JOIN {course_modules} cm ON cm.id = mc.cmid
                                        JOIN {competency} c ON c.id = mc.competencyid
                                       WHERE cm.course = ?", [$courseid]);
        $units = [];
        $names = [];
        foreach ($rows as $row) {
            $unitid = self::root_of($row);
            $names[$unitid] ??= format_string((string) $DB->get_field('competency', 'shortname', ['id' => $unitid]));
            $units[(int) $row->cmid][$unitid] = $names[$unitid];
        }
        return $units;
    }

    /**
     * An activity's name, type, link and visibility.
     *
     * @param int $courseid
     * @param int $cmid
     * @return \stdClass|null
     */
    private static function activity(int $courseid, int $cmid): ?\stdClass {
        $modinfo = get_fast_modinfo($courseid);
        $cm = $modinfo->cms[$cmid] ?? null;
        if (!$cm || $cm->deletioninprogress) {
            return null;
        }
        return (object) [
            'cmid' => $cmid,
            'name' => $cm->get_formatted_name(),
            'modname' => $cm->modname,
            'modtitle' => get_string('modulename', $cm->modname),
            'url' => $cm->url ? $cm->url->out(false) : null,
            'visible' => (bool) $cm->visible,
        ];
    }

    /**
     * Another course, unless it's hidden, has ended (old intakes and archived copies) or is outside the categories
     * being searched.
     *
     * @param int $courseid
     * @param int[]|null $categoryids Null for anywhere
     * @return \stdClass|null
     */
    private static function other_course(int $courseid, ?array $categoryids = null): ?\stdClass {
        global $DB;
        $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname, shortname, category, visible, enddate');
        if (!$course || !$course->visible || ($course->enddate && $course->enddate < time())) {
            return null;
        }
        if ($categoryids !== null && !in_array((int) $course->category, $categoryids, true)) {
            return null;
        }
        $course->fullname = format_string($course->fullname, true, ['context' => \context_course::instance($courseid)]);
        return $course;
    }
}
