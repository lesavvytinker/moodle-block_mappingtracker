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
 * Strings for Mapping tracker.
 *
 * @package    block_mappingtracker
 * @copyright  2026 Daniel Maloney
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addcompetencies'] = 'Add competencies to this course';
$string['allmet'] = 'All targets met.';
$string['alsomapped'] = 'Also mapped in {$a} (not counted here):';
$string['alsomappedcounted'] = 'Also mapped in {$a} (counted here):';
$string['andmore'] = 'and {$a} more';
$string['childrenmet'] = '{$a->met} of {$a->items} met';
$string['competenciesdisabled'] = 'Competencies are turned off on this site.';
$string['countcourses'] = 'Count mapping from these courses';
$string['countcourses_help'] = 'Mapping in other courses that link the same units is always shown, but only counts towards targets here if you choose the course. Choose a course when a unit is delivered across both courses (for example, a unit split over two terms). Leave out RPL courses, older intakes and copies. Hidden courses and courses that have ended are never included.';
$string['countcoursesnone'] = 'No other courses link the same units.';
$string['countcoursesnoselection'] = 'None: count this course only';
$string['counthidden'] = 'Count hidden activities';
$string['counthidden_desc'] = 'Count activities that are hidden from learners towards targets. Courses are often built with activities hidden until they are ready. Hidden activities are always shown, with a Hidden label.';
$string['countingfrom'] = 'Also counting mapping from: {$a}. Change this in the block\'s settings.';
$string['countoftarget'] = '{$a->count} of {$a->target}';
$string['defaulttarget'] = 'Default target';
$string['defaulttarget_desc'] = 'How many activities each criterion or evidence item (anything with nothing below it) should be mapped to, unless its wording or a manager says otherwise. 0 means no target.';
$string['evidencereporter'] = 'Evidence Reporter is installed: once learners are assessed, see the evidence for each of these items in their evidence summary.';
$string['exportmatrix'] = 'Export mapping matrix';
$string['framework'] = 'Framework: {$a}';
$string['hidden'] = 'Hidden';
$string['item'] = 'Item';
$string['mapped'] = 'Mapped';
$string['mappedactivities'] = 'Mapped activities';
$string['mapping'] = 'Mapping tracker';
$string['mappingintro'] = 'Every item in the units this course delivers, the activities mapped to it, and whether it meets its target. Link activities to competencies in each activity\'s settings, under Competencies.';
$string['mappingtracker:addinstance'] = 'Add a Mapping tracker block';
$string['mappingtracker:settargets'] = 'Set mapping targets';
$string['mappingtracker:view'] = 'View the mapping tracker';
$string['matrixfilename'] = 'Mapping matrix - {$a->unit} - {$a->course}';
$string['nocompetencies'] = 'No competencies are linked to this course yet.';
$string['notcounted'] = '(not counted)';
$string['notincourse'] = 'Not added to this course\'s competencies, so no activity can be mapped to it yet.';
$string['pluginname'] = 'Mapping tracker';
$string['privacy:metadata:target'] = 'Mapping targets set for competencies.';
$string['privacy:metadata:target:competencyid'] = 'The competency the target is for.';
$string['privacy:metadata:target:target'] = 'How many activities it should be mapped to.';
$string['privacy:metadata:target:timemodified'] = 'When the target was last changed.';
$string['privacy:metadata:target:usermodified'] = 'Who last changed the target.';
$string['sharedwith'] = 'Shared with {$a}';
$string['status'] = 'Status';
$string['status_met'] = 'Target met';
$string['status_missing'] = 'Not mapped';
$string['status_notarget'] = 'No target';
$string['status_under'] = 'Below target';
$string['suggesttargets'] = 'Suggest targets from wording';
$string['suggesttargets_desc'] = 'When an item says "at least two" (or any number), make that its target unless a manager has set one.';
$string['target'] = 'Target';
$string['targetdefault'] = 'Default';
$string['targetfor'] = 'Target for {$a}';
$string['targetreset'] = 'Reset';
$string['targetsaved'] = 'Target saved. It applies wherever this unit is delivered.';
$string['targetset'] = 'Set';
$string['targetsuggested'] = 'From wording';
$string['totalsmet'] = '{$a->met} of {$a->items} met';
$string['totalsmissing'] = '{$a} not mapped';
$string['totalsunder'] = '{$a} below target';
$string['viewfull'] = 'Open full mapping';
