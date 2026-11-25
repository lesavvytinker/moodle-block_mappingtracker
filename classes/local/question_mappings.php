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

use context;
use moodle_url;

/**
 * Questions mapped to competencies, read from whichever question bank plugin holds the mappings: Question competencies
 * (qbank_competencies) or Question Bank Competency Mapping (qbank_comp_ext). Both can be installed; their mappings are
 * combined. Mappings are read per question bank entry, so a mapping on any version of a question counts for every
 * version.
 *
 * @package    block_mappingtracker
 * @copyright  2026 Daniel Maloney
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_mappings {
    /** @var string Question competencies: one row per question bank entry and competency. */
    public const QBANK_COMPETENCIES = 'qbank_competencies';

    /** @var string Question Bank Competency Mapping: one row per question version, course and competency. */
    public const QBANK_COMP_EXT = 'qbank_comp_ext';

    /**
     * The mapping plugins installed and enabled on this site.
     *
     * @return string[] Component names
     */
    public static function get_sources(): array {
        global $DB;
        $tables = [self::QBANK_COMPETENCIES => 'qbank_competencies', self::QBANK_COMP_EXT => 'qbank_comp_ext_qmap'];
        $found = [];
        foreach ($tables as $component => $table) {
            if (\core_component::get_component_directory($component) === null || !get_config($component, 'version')) {
                continue;
            }
            if (!\core\plugininfo\qbank::is_plugin_enabled($component) || !$DB->get_manager()->table_exists($table)) {
                continue;
            }
            $found[] = $component;
        }
        return $found;
    }

    /**
     * Is any mapping plugin installed and enabled?
     *
     * @return bool
     */
    public static function is_available(): bool {
        return (bool) self::get_sources();
    }

    /**
     * A subquery of every mapping, as (questionbankentryid, competencyid) pairs, to use as a table: "{$sql} qcm".
     *
     * @return string|null SQL in brackets, with no parameters, or null with no mapping plugin
     */
    public static function get_sql(): ?string {
        $parts = [];
        foreach (self::get_sources() as $component) {
            if ($component === self::QBANK_COMPETENCIES) {
                $parts[] = "SELECT qbcm.questionbankentryid, qbcm.competencyid FROM {qbank_competencies} qbcm";
            } else if ($component === self::QBANK_COMP_EXT) {
                $parts[] = "SELECT qbxv.questionbankentryid, qbxm.competencyid
                              FROM {qbank_comp_ext_qmap} qbxm
                              JOIN {question_versions} qbxv ON qbxv.questionid = qbxm.questionid";
            }
        }
        return $parts ? '(' . implode(' UNION ', $parts) . ')' : null;
    }

    /**
     * Link to preview a question.
     *
     * @param int $questionid
     * @param context $bankcontext
     * @return moodle_url
     */
    public static function preview_url(int $questionid, context $bankcontext): moodle_url {
        return \qbank_previewquestion\helper::question_preview_url($questionid, null, null, null, null, $bankcontext);
    }

    /**
     * Mapped questions in a course's question banks (its question banks and its quizzes' own banks): where each
     * question is used in the course's quizzes. Questions are supporting evidence only: they never count as an
     * activity instance of a competency.
     *
     * @param int $courseid
     * @return array competencyid => stdClass:
     *     activitycmids int[] (activities in the course linked to the competency),
     *     incourse bool (the competency is one of the course's competencies),
     *     questiononly bool (mapped to questions but no activity in the course is linked to it),
     *     questions stdClass[]: entryid, questionid (latest version), name, contextid,
     *         quizzes stdClass[] (cmid, random bool, linked bool)
     */
    public static function get_course_overview(int $courseid): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/question/engine/lib.php');

        // Mapped entries whose question category is in one of the course's banks.
        $params = ['modlevel' => CONTEXT_MODULE, 'courselevel' => CONTEXT_COURSE, 'c1' => $courseid, 'c2' => $courseid];
        $table = self::get_sql();
        if ($table === null) {
            return [];
        }
        $sql = "SELECT qcm.competencyid, qcm.questionbankentryid AS entryid, qc.contextid
                  FROM {$table} qcm
                  JOIN {question_bank_entries} qbe ON qbe.id = qcm.questionbankentryid
                  JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                  JOIN {context} ctx ON ctx.id = qc.contextid
                 WHERE (ctx.contextlevel = :modlevel
                        AND ctx.instanceid IN (SELECT bcm.id FROM {course_modules} bcm WHERE bcm.course = :c1))
                    OR (ctx.contextlevel = :courselevel AND ctx.instanceid = :c2)";
        $mappings = [];
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $row) {
            $mappings[] = $row;
        }
        $rs->close();
        if (!$mappings) {
            return [];
        }
        $entryids = array_values(array_unique(array_map(fn($m) => (int) $m->entryid, $mappings)));

        // Latest version of each entry, for its name and links.
        [$insql, $inparams] = $DB->get_in_or_equal($entryids, SQL_PARAMS_NAMED, 'qle');
        $latest = $DB->get_records_sql(
            "SELECT qv.questionbankentryid, q.id, q.name
                                          FROM {question_versions} qv
                                          JOIN {question} q ON q.id = qv.questionid
                                         WHERE qv.questionbankentryid {$insql}
                                           AND qv.version = (SELECT MAX(qv2.version) FROM {question_versions} qv2
                                                              WHERE qv2.questionbankentryid = qv.questionbankentryid)",
            $inparams
        );

        // Where the entries are used in the course's quizzes: fixed slots, and random slots that can draw them.
        $usage = [];
        $quizmodule = (int) $DB->get_field('modules', 'id', ['name' => 'quiz']);
        $slotsql = "FROM {quiz_slots} qs
                    JOIN {quiz} qz ON qz.id = qs.quizid
                    JOIN {course_modules} cm ON cm.instance = qz.id AND cm.module = :quizmodule
                    JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modlevel";
        $slotparams = ['quizmodule' => $quizmodule, 'modlevel' => CONTEXT_MODULE, 'courseid' => $courseid];
        $fixed = $DB->get_recordset_sql(
            "SELECT qs.id AS slotid, qr.questionbankentryid AS entryid, cm.id AS cmid
                                           {$slotsql}
                                           JOIN {question_references} qr ON qr.usingcontextid = ctx.id
                                                AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
                                                AND qr.itemid = qs.id
                                          WHERE qz.course = :courseid AND qr.questionbankentryid {$insql}",
            $slotparams + $inparams
        );
        foreach ($fixed as $row) {
            $usage[(int) $row->entryid][(int) $row->cmid] = false;
        }
        $fixed->close();

        $randoms = $DB->get_records_sql("SELECT qs.id AS slotid, qsr.filtercondition, cm.id AS cmid
                                           {$slotsql}
                                           JOIN {question_set_references} qsr ON qsr.usingcontextid = ctx.id
                                                AND qsr.component = 'mod_quiz' AND qsr.questionarea = 'slot'
                                                AND qsr.itemid = qs.id
                                          WHERE qz.course = :courseid", $slotparams);
        if ($randoms) {
            $entrybyquestion = [];
            $loaded = [];
            $loader = new \core_question\local\bank\random_question_loader(new \qubaid_list([]));
            foreach ($randoms as $random) {
                $condition = json_decode((string) $random->filtercondition, true);
                if (empty($condition['filter'])) {
                    continue;
                }
                $key = md5(json_encode($condition['filter']));
                if (!isset($loaded[$key])) {
                    try {
                        $questionids = array_keys($loader->get_filtered_questions($condition['filter'], 0, 0, ['id']));
                    } catch (\Throwable $e) {
                        $questionids = [];
                    }
                    $loaded[$key] = self::entries_for_questions($questionids, $entrybyquestion);
                }
                foreach ($loaded[$key] as $entryid) {
                    if (in_array($entryid, $entryids) && !isset($usage[$entryid][(int) $random->cmid])) {
                        $usage[$entryid][(int) $random->cmid] = true;
                    }
                }
            }
        }

        // Activities in the course linked to each competency, and the course's competencies.
        $competencyids = array_values(array_unique(array_map(fn($m) => (int) $m->competencyid, $mappings)));
        [$cinsql, $cinparams] = $DB->get_in_or_equal($competencyids, SQL_PARAMS_NAMED, 'qlc');
        $activitylinks = [];
        $rs = $DB->get_recordset_sql("SELECT mc.id, mc.competencyid, mc.cmid
                                        FROM {competency_modulecomp} mc
                                        JOIN {course_modules} cm ON cm.id = mc.cmid
                                       WHERE cm.course = :courseid AND cm.deletioninprogress = 0
                                         AND mc.competencyid {$cinsql}", ['courseid' => $courseid] + $cinparams);
        foreach ($rs as $link) {
            $activitylinks[(int) $link->competencyid][(int) $link->cmid] = (int) $link->cmid;
        }
        $rs->close();
        $incourse = array_map('intval', $DB->get_fieldset_select(
            'competency_coursecomp',
            'competencyid',
            "courseid = :courseid AND competencyid {$cinsql}",
            ['courseid' => $courseid] + $cinparams
        ));

        $result = [];
        foreach ($competencyids as $competencyid) {
            $cmids = array_values($activitylinks[$competencyid] ?? []);
            $result[$competencyid] = (object) [
                'activitycmids' => $cmids,
                'incourse' => in_array($competencyid, $incourse),
                'questiononly' => !$cmids,
                'questions' => [],
            ];
        }
        foreach ($mappings as $mapping) {
            $entryid = (int) $mapping->entryid;
            $competencyid = (int) $mapping->competencyid;
            $quizzes = [];
            foreach ($usage[$entryid] ?? [] as $cmid => $random) {
                $quizzes[] = (object) ['cmid' => $cmid, 'random' => $random,
                    'linked' => isset($activitylinks[$competencyid][$cmid])];
            }
            $result[$competencyid]->questions[] = (object) [
                'entryid' => $entryid,
                'questionid' => isset($latest[$entryid]) ? (int) $latest[$entryid]->id : 0,
                'name' => isset($latest[$entryid]) ? $latest[$entryid]->name : '',
                'contextid' => (int) $mapping->contextid,
                'quizzes' => $quizzes,
            ];
        }
        return $result;
    }

    /**
     * Question bank entries of the given questions.
     *
     * @param int[] $questionids
     * @param array $cache questionid => entryid, filled as it goes
     * @return int[] Entry IDs
     */
    private static function entries_for_questions(array $questionids, array &$cache): array {
        global $DB;
        $missing = array_values(array_diff(array_map('intval', $questionids), array_keys($cache)));
        foreach (array_chunk($missing, 1000) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'qeq');
            foreach (
                $DB->get_records_select(
                    'question_versions',
                    "questionid {$insql}",
                    $params,
                    '',
                    'questionid, questionbankentryid'
                ) as $version
            ) {
                $cache[(int) $version->questionid] = (int) $version->questionbankentryid;
            }
        }
        $entries = [];
        foreach ($questionids as $questionid) {
            if (isset($cache[(int) $questionid])) {
                $entries[] = $cache[(int) $questionid];
            }
        }
        return array_values(array_unique($entries));
    }
}
