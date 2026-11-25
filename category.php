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
 * Mapping across a category: each unit's coverage by the courses that deliver it, and each course's own.
 *
 * @package    block_mappingtracker
 * @copyright  2026 Daniel Maloney
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use block_mappingtracker\local\coverage;
use block_mappingtracker\local\display;

$categoryid = required_param('categoryid', PARAM_INT);
$subcategories = optional_param('subcategories', 1, PARAM_BOOL);
$dataformat = optional_param('dataformat', '', PARAM_ALPHA);

$category = core_course_category::get($categoryid, MUST_EXIST, true);
$context = context_coursecat::instance($categoryid);
require_login();
require_capability('block/mappingtracker:viewcategory', $context);

$url = new moodle_url('/blocks/mappingtracker/category.php', ['categoryid' => $categoryid,
    'subcategories' => (int) $subcategories]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('coursecategory');
$PAGE->set_title(get_string('categorymapping', 'block_mappingtracker') . ': ' . $category->get_formatted_name());
$PAGE->set_heading($category->get_formatted_name());

$data = \core_competency\api::is_enabled() ? coverage::for_category($categoryid, (bool) $subcategories)
    : (object) ['courses' => [], 'units' => []];
$str = fn($id, $a = null) => get_string($id, 'block_mappingtracker', $a);
$cell = fn(\stdClass $t) => get_string('countoftarget', 'block_mappingtracker', ['count' => $t->met, 'target' => $t->items]);

if ($dataformat) {
    $columns = [get_string('course'), $str('unit'), $str('itemsmet'), $str('itemswithtargets'), $str('status_under'),
        $str('status_missing'), $str('tofix')];
    $rows = [];
    foreach ($data->courses as $c) {
        foreach ($c->units as $unitid => $u) {
            $rows[] = [format_string($c->course->shortname), format_string($data->units[$unitid]->competency->shortname),
                $u->totals->met, $u->totals->items, $u->totals->under, $u->totals->missing, $u->totals->warnings];
        }
    }
    foreach ($data->units as $unit) {
        $rows[] = [$str('combined'), format_string($unit->competency->shortname), $unit->totals->met, $unit->totals->items,
            $unit->totals->under, $unit->totals->missing, $unit->totals->warnings];
    }
    \core\dataformat::download_data(
        clean_filename($str('categoryfilename', $category->get_formatted_name())),
        $dataformat,
        $columns,
        new ArrayIterator($rows)
    );
    die;
}

echo $OUTPUT->header();
echo $OUTPUT->heading($str('categorymapping'));
echo html_writer::tag('p', $str('categoryintro'));
echo html_writer::div(html_writer::link(
    new moodle_url($url, ['subcategories' => (int) !$subcategories]),
    $str($subcategories ? 'categorywithoutsub' : 'categorywithsub')
), 'small mb-3');

if (!$data->courses) {
    echo $OUTPUT->notification($str('categorynone'), 'info');
    echo $OUTPUT->footer();
    die;
}

// Units: combined across the courses that deliver them.
echo $OUTPUT->heading($str('categoryunits'), 3);
echo html_writer::tag('p', $str('categoryunitsintro'), ['class' => 'small text-muted']);
$table = new html_table();
$table->attributes['class'] = 'generaltable block_mappingtracker-category';
$table->head = [$str('unit'), $str('deliveredby'), $str('combined'), $str('gaps'), $str('tofix')];
foreach ($data->units as $unit) {
    $links = array_map(fn($cid) => html_writer::link(new moodle_url(
        '/blocks/mappingtracker/view.php',
        ['courseid' => $cid],
        'unit-' . $unit->competency->id
    ), format_string($data->courses[$cid]->course->shortname)), $unit->courses);
    $t = $unit->totals;
    $gaps = [];
    if ($t->under) {
        $gaps[] = $str('totalsunder', $t->under);
    }
    if ($t->missing) {
        $gaps[] = $str('totalsmissing', $t->missing);
    }
    $table->data[] = [
        format_string($unit->competency->shortname),
        implode(', ', $links),
        display::bar($unit) . html_writer::span($cell($t), 'small'),
        $gaps ? implode(' · ', $gaps) : html_writer::span($str('allmet'), 'text-success'),
        $t->warnings ? html_writer::span('⚠ ' . $t->warnings, 'text-warning fw-bold font-weight-bold') : '',
    ];
}
echo html_writer::table($table);

// Courses: each one's own coverage.
echo $OUTPUT->heading($str('categorycourses'), 3, 'mt-5');
$table = new html_table();
$table->attributes['class'] = 'generaltable block_mappingtracker-category';
$table->head = [get_string('course'), $str('unit'), $str('ownmapping'), $str('gaps'), $str('tofix')];
foreach ($data->courses as $courseid => $c) {
    $first = true;
    foreach ($c->units as $unitid => $u) {
        $t = $u->totals;
        $gaps = [];
        if ($t->under) {
            $gaps[] = $str('totalsunder', $t->under);
        }
        if ($t->missing) {
            $gaps[] = $str('totalsmissing', $t->missing);
        }
        $coursecell = '';
        if ($first) {
            $coursecell = html_writer::link(
                new moodle_url('/blocks/mappingtracker/view.php', ['courseid' => $courseid]),
                format_string($c->course->fullname)
            );
            if (!$c->course->visible) {
                $coursecell .= ' ' . html_writer::span(get_string('hidden', 'block_mappingtracker'), 'badge bg-secondary');
            }
            if ($c->outsidewarnings) {
                $coursecell .= html_writer::div($str('outsidewarnings', $c->outsidewarnings), 'small text-warning');
            }
        }
        $table->data[] = [$coursecell, format_string($data->units[$unitid]->competency->shortname), $cell($t),
            $gaps ? implode(' · ', $gaps) : html_writer::span($str('allmet'), 'text-success'),
            $t->warnings ? html_writer::span('⚠ ' . $t->warnings, 'text-warning fw-bold font-weight-bold') : ''];
        $first = false;
    }
}
echo html_writer::table($table);
echo $OUTPUT->download_dataformat_selector($str('categoryexport'), $url, 'dataformat');
echo $OUTPUT->footer();
