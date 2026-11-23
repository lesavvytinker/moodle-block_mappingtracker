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

    /** Words for numbers in "at least two". */
    private const NUMBERS = ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7,
        'eight' => 8, 'nine' => 9, 'ten' => 10];

    /**
     * The coverage of every unit the course delivers.
     *
     * @param int $courseid
     * @param int[] $countcourseids Other courses whose mapping counts towards targets here
     * @return \stdClass[] unitid => unit: competency, framework, nodes (in tree order), totals
     */
    public static function for_course(int $courseid, array $countcourseids = []): array {
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

        foreach ($units as $unitid => $unit) {
            $totals = (object) ['items' => 0, 'met' => 0, 'under' => 0, 'missing' => 0];
            foreach ($unit->nodes as $id => $node) {
                $node->incourse = isset($incourse[$id]);
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
                    $othercourses[$cid] ??= self::other_course($cid);
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
     * @return array courseid => course name
     */
    public static function sharing_courses(int $courseid): array {
        global $DB;
        $courses = [];
        $units = self::for_course($courseid);
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
                if ($course = self::other_course((int) $cid)) {
                    $courses[$course->id] = $course->fullname;
                }
            }
        }
        \core_collator::asort($courses);
        return $courses;
    }

    /**
     * The courses chosen to count in this course's block.
     *
     * @param int $courseid
     * @return int[]
     */
    public static function counted_courses(int $courseid): array {
        global $DB;
        $context = \context_course::instance($courseid);
        $instance = $DB->get_record(
            'block_instances',
            ['blockname' => 'mappingtracker', 'parentcontextid' => $context->id],
            'id, configdata',
            IGNORE_MULTIPLE
        );
        if (!$instance || !$instance->configdata) {
            return [];
        }
        $config = unserialize_object(base64_decode($instance->configdata));
        return array_map('intval', array_values((array) ($config->countcourses ?? [])));
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
     * Another course, unless it's hidden or has ended (old intakes and archived copies).
     *
     * @param int $courseid
     * @return \stdClass|null
     */
    private static function other_course(int $courseid): ?\stdClass {
        global $DB;
        $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname, shortname, visible, enddate');
        if (!$course || !$course->visible || ($course->enddate && $course->enddate < time())) {
            return null;
        }
        $course->fullname = format_string($course->fullname, true, ['context' => \context_course::instance($courseid)]);
        return $course;
    }
}
