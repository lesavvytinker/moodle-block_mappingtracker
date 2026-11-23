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
 * Block settings: which other courses' mapping counts here.
 *
 * @package    block_mappingtracker
 * @copyright  2026 Daniel Maloney
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_mappingtracker_edit_form extends block_edit_form {
    /**
     * Form fields.
     *
     * @param MoodleQuickForm $mform
     */
    protected function specific_definition($mform) {
        global $DB;
        $mform->addElement('header', 'confighdr', get_string('blocksettings', 'block'));
        $courseid = (int) $this->page->course->id;
        $coursecategory = core_course_category::get($this->page->course->category, IGNORE_MISSING, true);
        $options = [
            \block_mappingtracker\local\coverage::SEARCH_OWN_CATEGORY => get_string(
                'searchowncategory',
                'block_mappingtracker',
                $coursecategory ? $coursecategory->get_formatted_name() : ''
            ),
            \block_mappingtracker\local\coverage::SEARCH_ANYWHERE => get_string('searchanywhere', 'block_mappingtracker'),
        ] + core_course_category::make_categories_list();
        $mform->addElement('select', 'config_searchcategory', get_string('searchcategory', 'block_mappingtracker'), $options);
        $mform->setDefault('config_searchcategory', \block_mappingtracker\local\coverage::SEARCH_OWN_CATEGORY);
        $mform->addHelpButton('config_searchcategory', 'searchcategory', 'block_mappingtracker');
        $mform->addElement('advcheckbox', 'config_subcategories', '', get_string('searchsubcategories', 'block_mappingtracker'));
        $mform->setDefault('config_subcategories', 1);
        $mform->hideIf(
            'config_subcategories',
            'config_searchcategory',
            'eq',
            \block_mappingtracker\local\coverage::SEARCH_ANYWHERE
        );

        $config = $this->block->config ?? new stdClass();
        $courses = \block_mappingtracker\local\coverage::sharing_courses(
            $courseid,
            \block_mappingtracker\local\coverage::search_categories($courseid, $config)
        );
        // Keep courses already chosen, even if they're outside the categories searched.
        foreach (\block_mappingtracker\local\coverage::counted_courses($courseid, $config) as $chosen) {
            if (!isset($courses[$chosen]) && ($name = $DB->get_field('course', 'fullname', ['id' => $chosen]))) {
                $courses[$chosen] = format_string($name);
            }
        }
        if (!$courses) {
            $mform->addElement(
                'static',
                'nocourses',
                get_string('countcourses', 'block_mappingtracker'),
                get_string('countcoursesnone', 'block_mappingtracker')
            );
            return;
        }
        $mform->addElement(
            'autocomplete',
            'config_countcourses',
            get_string('countcourses', 'block_mappingtracker'),
            $courses,
            ['multiple' => true, 'noselectionstring' => get_string('countcoursesnoselection', 'block_mappingtracker')]
        );
        $mform->addHelpButton('config_countcourses', 'countcourses', 'block_mappingtracker');
    }
}
