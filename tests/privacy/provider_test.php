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

declare(strict_types=1);

namespace block_mappingtracker\privacy;

use block_mappingtracker\local\coverage;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
/**
 * Privacy provider tests.
 *
 * @package    block_mappingtracker
 * @copyright  2026 Daniel Maloney
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_mappingtracker\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Export and anonymise.
     */
    public function test_provider(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $cgen = $gen->get_plugin_generator('core_competency');
        $framework = $cgen->create_framework();
        $comp = $cgen->create_competency(['competencyframeworkid' => $framework->get('id'), 'shortname' => 'PC 1.1']);
        $manager = $gen->create_user();
        $other = $gen->create_user();
        $this->setUser($manager);
        coverage::set_target((int) $comp->get('id'), 2);
        $system = \context_system::instance();

        $this->assertEquals([$system->id], provider::get_contexts_for_userid((int) $manager->id)->get_contextids());
        $this->assertEquals([], provider::get_contexts_for_userid((int) $other->id)->get_contextids());
        $userlist = new userlist($system, 'block_mappingtracker');
        provider::get_users_in_context($userlist);
        $this->assertEquals([$manager->id], $userlist->get_userids());

        $this->export_context_data_for_user((int) $manager->id, $system, 'block_mappingtracker');
        $data = writer::with_context($system)->get_data([get_string('pluginname', 'block_mappingtracker')]);
        $this->assertSame('PC 1.1', $data->targets[0]['competency']);

        provider::delete_data_for_user(new approved_contextlist($manager, 'block_mappingtracker', [$system->id]));
        $this->assertEquals(0, $DB->get_field(coverage::TABLE, 'usermodified', ['competencyid' => $comp->get('id')]));
        $this->assertEquals(2, $DB->get_field(coverage::TABLE, 'target', ['competencyid' => $comp->get('id')]));

        $this->setUser($other);
        coverage::set_target((int) $comp->get('id'), 3);
        provider::delete_data_for_users(new approved_userlist($system, 'block_mappingtracker', [$other->id]));
        $this->assertEquals(0, $DB->get_field(coverage::TABLE, 'usermodified', ['competencyid' => $comp->get('id')]));
        $this->setUser($other);
        coverage::set_target((int) $comp->get('id'), 1);
        provider::delete_data_for_all_users_in_context($system);
        $this->assertFalse($DB->record_exists_select(coverage::TABLE, 'usermodified > 0'));
    }
}
