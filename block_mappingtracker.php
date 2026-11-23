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
 * Mapping tracker block: live coverage of the competencies a course maps, against targets.
 *
 * @package    block_mappingtracker
 * @copyright  2026 Daniel Maloney
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_mappingtracker extends block_base {
    /**
     * Init.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_mappingtracker');
    }

    /**
     * Course pages only.
     *
     * @return array
     */
    public function applicable_formats() {
        return ['course-view' => true, 'site' => false, 'mod' => false, 'my' => false];
    }

    /**
     * One per course.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Site settings.
     *
     * @return bool
     */
    public function has_config() {
        return true;
    }

    /**
     * Block content, for course staff only.
     *
     * @return stdClass
     */
    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = (object) ['text' => '', 'footer' => ''];
        $course = $this->page->course;
        if (
            !$course || $course->id == SITEID
                || !has_capability('block/mappingtracker:view', context_course::instance($course->id))
        ) {
            return $this->content;
        }
        if (!\core_competency\api::is_enabled()) {
            $this->content->text = html_writer::div(get_string('competenciesdisabled', 'block_mappingtracker'), 'small');
            return $this->content;
        }
        $units = \block_mappingtracker\local\coverage::for_course(
            (int) $course->id,
            array_map('intval', (array) ($this->config->countcourses ?? []))
        );
        if (!$units) {
            $this->content->text = html_writer::div(get_string('nocompetencies', 'block_mappingtracker'), 'small') .
                html_writer::link(
                    new moodle_url('/admin/tool/lp/coursecompetencies.php', ['courseid' => $course->id]),
                    get_string('addcompetencies', 'block_mappingtracker'),
                    ['class' => 'small']
                );
            return $this->content;
        }
        $this->content->text = \block_mappingtracker\local\display::block((int) $course->id, $units);
        $this->content->footer = html_writer::link(new moodle_url(
            '/blocks/mappingtracker/view.php',
            ['courseid' => $course->id]
        ), get_string('viewfull', 'block_mappingtracker'));
        return $this->content;
    }
}
