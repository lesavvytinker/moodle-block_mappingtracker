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
 * Export a unit's mapping matrix: items down the side, activities across the top.
 *
 * @package    block_mappingtracker
 * @copyright  2026 Daniel Maloney
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use block_mappingtracker\local\coverage;
use block_mappingtracker\local\display;

$courseid = required_param('courseid', PARAM_INT);
$unitid = required_param('unitid', PARAM_INT);
$dataformat = required_param('dataformat', PARAM_ALPHA);

$course = get_course($courseid);
require_login($course);
require_capability('block/mappingtracker:view', context_course::instance($courseid));

$units = coverage::for_course($courseid, coverage::counted_courses($courseid));
if (!isset($units[$unitid])) {
    throw new moodle_exception('invalidrecord', 'error', '', 'competency');
}
[$columns, $rows] = coverage::matrix($units[$unitid], $course);
$filename = clean_filename(get_string('matrixfilename', 'block_mappingtracker', [
    'unit' => $units[$unitid]->competency->idnumber ?: $units[$unitid]->competency->shortname,
    'course' => $course->shortname,
]));
\core\dataformat::download_data($filename, $dataformat, $columns, new ArrayIterator($rows));
