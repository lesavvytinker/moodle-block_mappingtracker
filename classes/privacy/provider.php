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

namespace block_mappingtracker\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider: the only personal data is who last changed a target.
 *
 * @package    block_mappingtracker
 * @copyright  2026 Daniel Maloney
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** Targets table. */
    private const TABLE = 'block_mappingtracker_target';

    /**
     * Data stored.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(self::TABLE, [
            'competencyid' => 'privacy:metadata:target:competencyid',
            'target' => 'privacy:metadata:target:target',
            'usermodified' => 'privacy:metadata:target:usermodified',
            'timemodified' => 'privacy:metadata:target:timemodified',
        ], 'privacy:metadata:target');
        return $collection;
    }

    /**
     * Targets are site-wide: the system context.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if ($DB->record_exists(self::TABLE, ['usermodified' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Users who changed targets.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        if ($userlist->get_context()->contextlevel == CONTEXT_SYSTEM) {
            $userlist->add_from_sql('usermodified', 'SELECT usermodified FROM {' . self::TABLE . '} WHERE usermodified > 0', []);
        }
    }

    /**
     * Export the targets a user last changed.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_SYSTEM) {
                continue;
            }
            $targets = [];
            foreach ($DB->get_records(self::TABLE, ['usermodified' => $userid], 'id') as $record) {
                $targets[] = [
                    'competency' => (string) $DB->get_field('competency', 'shortname', ['id' => $record->competencyid]),
                    'target' => $record->target,
                    'timemodified' => transform::datetime($record->timemodified),
                ];
            }
            if ($targets) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'block_mappingtracker')],
                    (object) ['targets' => $targets]
                );
            }
        }
    }

    /**
     * Targets belong to the site, not a person: anonymise rather than delete.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            $DB->set_field(self::TABLE, 'usermodified', 0);
        }
    }

    /**
     * Anonymise one user's changes.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_SYSTEM) {
                $DB->set_field(self::TABLE, 'usermodified', 0, ['usermodified' => $contextlist->get_user()->id]);
            }
        }
    }

    /**
     * Anonymise several users' changes.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        if ($userlist->get_context()->contextlevel != CONTEXT_SYSTEM || !$userlist->get_userids()) {
            return;
        }
        [$in, $params] = $DB->get_in_or_equal($userlist->get_userids());
        $DB->set_field_select(self::TABLE, 'usermodified', 0, "usermodified $in", $params);
    }
}
