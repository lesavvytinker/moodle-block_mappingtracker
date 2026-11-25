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
$string['andsubcategories'] = 'and its subcategories';
$string['categorycourses'] = 'Each course\'s own mapping';
$string['categoryexport'] = 'Export';
$string['categoryfilename'] = 'Mapping tracker - {$a}';
$string['categoryintro'] = 'Every unit delivered in this category, how well its courses cover it between them, and each course\'s own mapping. Use it to check a term or a qualification before an audit.';
$string['categorymapping'] = 'Mapping tracker';
$string['categorynone'] = 'No course in this category links any competencies yet.';
$string['categoryunits'] = 'Units';
$string['categoryunitsintro'] = 'Combined: an item counts as met when the courses delivering the unit map enough activities to it between them, for units split across courses.';
$string['categorywithoutsub'] = 'Show this category only, without its subcategories';
$string['categorywithsub'] = 'Include subcategories';
$string['childrenmet'] = '{$a->met} of {$a->items} met';
$string['combined'] = 'Combined';
$string['competenciesdisabled'] = 'Competencies are turned off on this site.';
$string['countcourses'] = 'Count mapping from these courses';
$string['countcourses_help'] = 'Mapping in other courses (in the categories searched) is shown, but only counts towards targets here if you choose the course. Choose a course when a unit is delivered across both courses (for example, a unit split over two terms). Leave out RPL courses, older intakes and copies. Hidden courses and courses that have ended are never included.';
$string['countcoursesnone'] = 'No other courses link the same units.';
$string['countcoursesnoselection'] = 'None: count this course only';
$string['counthidden'] = 'Count hidden activities';
$string['counthidden_desc'] = 'Count activities that are hidden from learners towards targets. Courses are often built with activities hidden until they are ready. Hidden activities are always shown, with a Hidden label.';
$string['countingfrom'] = 'Also counting mapping from: {$a}. Change this in the block\'s settings.';
$string['countoftarget'] = '{$a->count} of {$a->target}';
$string['defaulttarget'] = 'Default target';
$string['defaulttarget_desc'] = 'How many activities each criterion or evidence item (anything with nothing below it) should be mapped to, unless its wording or a manager says otherwise. 0 means no target.';
$string['deliveredby'] = 'Delivered by';
$string['evidencereporter'] = 'Evidence Reporter is installed: once learners are assessed, see the evidence for each of these items in their evidence summary.';
$string['exportmatrix'] = 'Export mapping matrix';
$string['fixaddandlink'] = 'Add to course and to {$a}';
$string['fixaddtocourse'] = 'Add to this course\'s competencies';
$string['fixedadded'] = 'Added to the course\'s competencies.';
$string['fixedlinked'] = 'Linked. The quiz now gives evidence for this item.';
$string['fixesintro'] = 'Questions are supporting evidence: a quiz only gives evidence for an item once the item is in the quiz\'s own competency settings. Fix these so the quizzes count.';
$string['fixlinkquiz'] = 'Link {$a} to this item';
$string['fixwhat'] = 'What needs doing';
$string['fixwhatadd'] = 'Questions are mapped to it, but it isn\'t one of this course\'s competencies.';
$string['fixwhataddlink'] = '{$a} uses questions mapped to it, but the item isn\'t in this course\'s competencies or the quiz\'s.';
$string['fixwhatlink'] = '{$a} uses questions mapped to it, but doesn\'t have it in its competency settings.';
$string['fixwhatunused'] = 'The question "{$a}" is mapped to it but isn\'t in any quiz in this course.';
$string['flag_notlinkedatquiz'] = 'Quiz not linked';
$string['flag_notused'] = 'Not in a quiz';
$string['flag_random'] = 'Random only';
$string['flag_random_help'] = 'Only drawn at random, so not every learner gets this question.';
$string['framework'] = 'Framework: {$a}';
$string['gaps'] = 'Gaps';
$string['hidden'] = 'Hidden';
$string['item'] = 'Item';
$string['itemsmet'] = 'Items met';
$string['itemswithtargets'] = 'Items with targets';
$string['lookingin'] = 'Looking for mapping in other courses in: {$a}. Change this in the block\'s settings.';
$string['mapped'] = 'Mapped';
$string['mappedactivities'] = 'Mapped activities';
$string['mapping'] = 'Mapping tracker';
$string['mappingintro'] = 'Every item in the units this course delivers, the activities mapped to it, and whether it meets its target. Link activities to competencies in each activity\'s settings, under Competencies.';
$string['mappingtracker:addinstance'] = 'Add a Mapping tracker block';
$string['mappingtracker:settargets'] = 'Set mapping targets';
$string['mappingtracker:view'] = 'View the mapping tracker';
$string['mappingtracker:viewcategory'] = 'View mapping across a category';
$string['matrixfilename'] = 'Mapping matrix - {$a->unit} - {$a->course}';
$string['nocompetencies'] = 'No competencies are linked to this course yet.';
$string['notcounted'] = '(not counted)';
$string['notincourse'] = 'Not added to this course\'s competencies, so no activity can be mapped to it yet.';
$string['outsideunits'] = 'Questions mapped outside these units';
$string['outsideunitsintro'] = 'Questions in this course\'s question banks are mapped to these competencies, which belong to no unit this course delivers.';
$string['outsidewarnings'] = '⚠ {$a} to fix outside its units';
$string['ownmapping'] = 'Met (own activities)';
$string['pluginname'] = 'Mapping tracker';
$string['privacy:metadata:target'] = 'Mapping targets set for competencies.';
$string['privacy:metadata:target:competencyid'] = 'The competency the target is for.';
$string['privacy:metadata:target:target'] = 'How many activities it should be mapped to.';
$string['privacy:metadata:target:timemodified'] = 'When the target was last changed.';
$string['privacy:metadata:target:usermodified'] = 'Who last changed the target.';
$string['questions'] = 'Questions';
$string['questionsheading'] = 'Questions mapped (supporting evidence, not counted):';
$string['questionsnote'] = 'Map questions to competencies in the question bank (Competencies column). Mapped questions are listed under each item but never count towards its target: only the quiz, linked to the item, does.';
$string['questionsonly'] = 'Only questions are mapped to it: link the quiz too.';
$string['searchanywhere'] = 'Anywhere on the site';
$string['searchcategory'] = 'Look for mapping in';
$string['searchcategory_help'] = 'Where to look for the same units mapped in other courses. By default only this course\'s category (and its subcategories) is searched, so courses for other terms or intakes, kept in their own categories, don\'t crowd the page. Choose a broader category, such as a whole term, to see across qualifications. Hidden courses and courses that have ended are always left out. Courses chosen below to count are always included.';
$string['searchowncategory'] = 'This course\'s category ({$a})';
$string['searchsubcategories'] = 'Include subcategories';
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
$string['tofix'] = 'To fix';
$string['totalsmet'] = '{$a->met} of {$a->items} met';
$string['totalsmissing'] = '{$a} not mapped';
$string['totalsunder'] = '{$a} below target';
$string['totalswarnings'] = '{$a} to fix';
$string['unit'] = 'Unit';
$string['usedin'] = 'In: {$a}';
$string['viewcategoryof'] = 'See mapping across the whole category: {$a}';
$string['viewfull'] = 'Open full mapping';
$string['warningsheader'] = '⚠ {$a} to fix';
