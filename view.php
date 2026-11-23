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

/**
 * The full mapping for a course: every item of every unit, its mapped activities, and its target.
 *
 * @package    block_mappingtracker
 * @copyright  2026 Daniel Maloney
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use block_mappingtracker\local\coverage;
use block_mappingtracker\local\display;

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('block/mappingtracker:view', $context);
$cansettargets = has_capability('block/mappingtracker:settargets', context_system::instance());

$url = new moodle_url('/blocks/mappingtracker/view.php', ['courseid' => $courseid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('mapping', 'block_mappingtracker') . ': ' . format_string($course->shortname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('mapping', 'block_mappingtracker'), $url);

if ($action === 'settarget' && $cansettargets && confirm_sesskey()) {
    $competencyid = required_param('competencyid', PARAM_INT);
    $raw = trim(optional_param('target', '', PARAM_RAW_TRIMMED));
    $reset = optional_param('reset', '', PARAM_RAW);
    if (!$DB->record_exists('competency', ['id' => $competencyid])) {
        throw new moodle_exception('invalidrecord', 'error', '', 'competency');
    }
    coverage::set_target($competencyid, ($reset !== '' || $raw === '') ? null : max(0, (int) $raw));
    redirect(
        new moodle_url($url, [], 'item-' . $competencyid),
        get_string('targetsaved', 'block_mappingtracker'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$countcourses = coverage::counted_courses($courseid);
$units = \core_competency\api::is_enabled() ? coverage::for_course($courseid, $countcourses) : [];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('mapping', 'block_mappingtracker'));
echo html_writer::tag('p', get_string('mappingintro', 'block_mappingtracker'));

if (!$units) {
    echo $OUTPUT->notification(get_string('nocompetencies', 'block_mappingtracker'), 'info');
    echo html_writer::link(
        new moodle_url('/admin/tool/lp/coursecompetencies.php', ['courseid' => $courseid]),
        get_string('addcompetencies', 'block_mappingtracker')
    );
    echo $OUTPUT->footer();
    die;
}

if ($countcourses) {
    $names = array_map(fn($id) => format_string((string) $DB->get_field('course', 'fullname', ['id' => $id])), $countcourses);
    echo html_writer::tag(
        'p',
        get_string('countingfrom', 'block_mappingtracker', implode(', ', array_filter($names))),
        ['class' => 'small text-muted']
    );
}

$sourcelabels = [
    'set' => get_string('targetset', 'block_mappingtracker'),
    'suggested' => get_string('targetsuggested', 'block_mappingtracker'),
    'default' => get_string('targetdefault', 'block_mappingtracker'),
    'none' => '',
];
foreach ($units as $unitid => $unit) {
    echo html_writer::start_div('block_mappingtracker-unitsection mb-5', ['id' => 'unit-' . $unitid]);
    echo $OUTPUT->heading(format_string($unit->competency->shortname), 3);
    if ($unit->framework) {
        echo html_writer::div(get_string(
            'framework',
            'block_mappingtracker',
            format_string($unit->framework->shortname)
        ), 'small text-muted');
    }
    echo display::bar($unit);
    echo html_writer::div(display::totals($unit), 'mb-2');
    echo html_writer::div(
        $OUTPUT->download_dataformat_selector(
            get_string('exportmatrix', 'block_mappingtracker'),
            new moodle_url('/blocks/mappingtracker/export.php'),
            'dataformat',
            ['courseid' => $courseid, 'unitid' => $unitid]
        ),
        'mb-3'
    );

    $table = new html_table();
    $table->attributes['class'] = 'generaltable block_mappingtracker-table';
    $table->head = [get_string('item', 'block_mappingtracker'), get_string('mappedactivities', 'block_mappingtracker'),
        get_string('mapped', 'block_mappingtracker'), get_string('target', 'block_mappingtracker')];
    foreach ($unit->nodes as $id => $node) {
        $name = html_writer::span(s(display::name($node, true)), $node->children ? 'fw-bold font-weight-bold' : '');
        if ($node->children && $node->childtotals->items) {
            $name .= html_writer::div(get_string('childrenmet', 'block_mappingtracker', $node->childtotals), 'small text-muted');
        }
        if (!$node->incourse && in_array($node->status, [coverage::MISSING, coverage::UNDER], true)) {
            $name .= html_writer::div(get_string('notincourse', 'block_mappingtracker'), 'small text-danger');
        }
        $cell = html_writer::div($name, '', ['style' => 'padding-left: ' . ($node->depth * 1.25) . 'rem']);

        $mapped = display::activities($node->activities);
        foreach ($node->elsewhere as $other) {
            $mapped .= html_writer::div(get_string(
                $other->counted ? 'alsomappedcounted' : 'alsomapped',
                'block_mappingtracker',
                html_writer::link(new moodle_url('/course/view.php', ['id' => $other->course->id]), $other->course->fullname)
            ) .
                display::activities($other->activities, $other->counted), 'small text-muted mt-1 block_mappingtracker-elsewhere');
        }

        $target = $node->target === null ? '' : (string) $node->target;
        $targetcell = html_writer::div($sourcelabels[$node->targetsource], 'small text-muted');
        $leaf = !$node->children;
        if ($cansettargets && ($leaf || $node->targetsource === 'set')) {
            $form = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'settarget']) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'competencyid', 'value' => $id]) .
                html_writer::label(
                    get_string('targetfor', 'block_mappingtracker', display::name($node)),
                    'target-' . $id,
                    false,
                    ['class' => 'sr-only visually-hidden']
                ) .
                html_writer::empty_tag('input', ['type' => 'number', 'min' => 0, 'max' => 99, 'name' => 'target',
                    'id' => 'target-' . $id, 'value' => $target, 'class' => 'form-control form-control-sm d-inline-block',
                    'style' => 'width: 4.5rem']) . ' ' .
                html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('save'),
                    'class' => 'btn btn-sm btn-secondary']);
            if ($node->targetsource === 'set') {
                $form .= ' ' . html_writer::empty_tag('input', ['type' => 'submit', 'name' => 'reset',
                    'value' => get_string('targetreset', 'block_mappingtracker'), 'class' => 'btn btn-sm btn-link']);
            }
            $targetcell = html_writer::tag('form', $form, ['method' => 'post', 'action' => $url->out(false),
                'class' => 'd-flex flex-wrap align-items-center gap-1']) . $targetcell;
        } else if ($leaf || $target !== '') {
            $targetcell = html_writer::div($target === '' ? '-' : $target) . $targetcell;
        } else {
            $targetcell = '';
        }
        // Parents with nothing mapped to them directly and no target of their own just summarise their children.
        $badge = (!$leaf && !$node->count && $node->status === coverage::NOTARGET) ? '' : display::badge($node);

        $row = new html_table_row([$cell, $mapped, $badge, $targetcell]);
        $row->id = 'item-' . $id;
        $row->attributes['class'] = 'block_mappingtracker-' . $node->status;
        $table->data[] = $row;
    }
    echo html_writer::table($table);
    echo html_writer::end_div();
}

if (core_component::get_component_directory('report_evidencereporter')) {
    echo html_writer::tag('p', get_string('evidencereporter', 'block_mappingtracker'), ['class' => 'small text-muted']);
}
echo $OUTPUT->footer();
