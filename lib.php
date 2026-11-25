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
 * Callbacks.
 *
 * @package    block_mappingtracker
 * @copyright  2026 Daniel Maloney
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add "Mapping tracker" to a category's More menu, for those who can see a category's mapping.
 *
 * @param navigation_node $navigation
 * @param context_coursecat $context
 */
function block_mappingtracker_extend_navigation_category_settings(navigation_node $navigation, context_coursecat $context): void {
    if (!has_capability('block/mappingtracker:viewcategory', $context)) {
        return;
    }
    $navigation->add(
        get_string('categorymapping', 'block_mappingtracker'),
        new moodle_url('/blocks/mappingtracker/category.php', ['categoryid' => $context->instanceid]),
        navigation_node::TYPE_SETTING,
        null,
        'block_mappingtracker_category',
        new pix_icon('i/report', '')
    );
}
