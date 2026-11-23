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
        $mform->addElement('header', 'confighdr', get_string('blocksettings', 'block'));
        $courseid = (int) $this->page->course->id;
        $courses = \block_mappingtracker\local\coverage::sharing_courses($courseid);
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
