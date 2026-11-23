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
 * Site settings.
 *
 * @package    block_mappingtracker
 * @copyright  2026 Daniel Maloney
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
        'block_mappingtracker/defaulttarget',
        get_string('defaulttarget', 'block_mappingtracker'),
        get_string('defaulttarget_desc', 'block_mappingtracker'),
        1,
        PARAM_INT,
        4
    ));
    $settings->add(new admin_setting_configcheckbox(
        'block_mappingtracker/suggesttargets',
        get_string('suggesttargets', 'block_mappingtracker'),
        get_string('suggesttargets_desc', 'block_mappingtracker'),
        1
    ));
    $settings->add(new admin_setting_configcheckbox(
        'block_mappingtracker/counthidden',
        get_string('counthidden', 'block_mappingtracker'),
        get_string('counthidden_desc', 'block_mappingtracker'),
        1
    ));
}
