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
 * Behat data generator.
 *
 * @package    block_mappingtracker
 * @copyright  2026 Daniel Maloney
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_block_mappingtracker_generator extends behat_generator_base {
    /**
     * Entities.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'mappings' => [
                'singular' => 'mapping',
                'datagenerator' => 'mapping',
                'required' => ['activity', 'competency'],
                'switchids' => ['activity' => 'cmid', 'competency' => 'competencyid'],
            ],
        ];
    }

    /**
     * A competency's id from its ID number.
     *
     * @param string $idnumber
     * @return int
     */
    protected function get_competency_id(string $idnumber): int {
        global $DB;
        return (int) $DB->get_field('competency', 'id', ['idnumber' => $idnumber], MUST_EXIST);
    }
}
