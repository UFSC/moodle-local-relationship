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
 * Tests for the privacy provider.
 *
 * Covers get_contexts_for_userid, export_user_data, delete_data_for_user
 * and delete_data_for_all_users_in_context. Each test sets up the smallest
 * fixture that exercises the path under check (membership in a single
 * category context, two users, etc) and asserts the post-state in
 * {relationship_members}.
 *
 * @package    local_relationship
 * @copyright  2026 UFSC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/tag/lib.php');
require_once($CFG->dirroot . '/local/relationship/lib.php');

// "use" is a compile-time alias and does not trigger autoload, so these
// references are safe to import even on Moodle versions where the privacy
// API does not exist — the classes are only resolved when actually used,
// and setUp() skips the test before any test method runs in that case.
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;
use local_relationship\privacy\provider;

/**
 * @group local_relationship
 */
class local_relationship_privacy_testcase extends advanced_testcase {

    /** @var stdClass */
    protected $category;
    /** @var context_coursecat */
    protected $catcontext;
    /** @var int */
    protected $roleid;
    /** @var stdClass */
    protected $cohort;

    protected function setUp() {
        global $DB;

        // The privacy API only exists in Moodle 3.4+. On older Moodle the
        // provider class cannot be loaded, so the rest of this fixture is
        // pointless. Skip gracefully so the test file is still parseable
        // and discoverable.
        if (!interface_exists('\\core_privacy\\local\\metadata\\provider')) {
            $this->markTestSkipped('Privacy API not available in this Moodle version.');
        }

        $this->resetAfterTest();

        $this->category = $this->getDataGenerator()->create_category();
        $this->catcontext = context_coursecat::instance($this->category->id);
        $this->cohort = $this->getDataGenerator()->create_cohort(array('contextid' => $this->catcontext->id));
        $this->roleid = $DB->get_field('role', 'id', array('shortname' => 'student'));
    }

    /**
     * Builds a relationship + cohort link + group and inserts the given user
     * as a member. Returns the relationship_members id.
     *
     * @param stdClass $user
     * @return int
     */
    protected function make_membership($user) {
        $rid = relationship_add_relationship((object) array(
            'contextid' => $this->catcontext->id,
            'name' => 'Fixture rel',
        ));
        $rcid = relationship_add_cohort((object) array(
            'relationshipid' => $rid,
            'cohortid' => $this->cohort->id,
            'roleid' => $this->roleid,
            'allowdupsingroups' => 0,
            'uniformdistribution' => 0,
        ));
        $gid = relationship_add_group((object) array(
            'relationshipid' => $rid,
            'name' => 'Grupo',
            'userlimit' => 0,
            'uniformdistribution' => 0,
        ));
        return relationship_add_member($gid, $rcid, $user->id);
    }

    // ---------------------------------------------------------------------

    public function test_get_metadata_lists_relationship_members_table() {
        $collection = new \core_privacy\local\metadata\collection('local_relationship');
        $collection = provider::get_metadata($collection);
        $items = $collection->get_collection();
        $this->assertNotEmpty($items);

        $tablenames = array();
        foreach ($items as $item) {
            $tablenames[] = $item->get_name();
        }
        $this->assertContains('relationship_members', $tablenames);
    }

    public function test_get_contexts_for_userid_returns_category_context() {
        $user = $this->getDataGenerator()->create_user();
        $this->make_membership($user);

        $contextlist = provider::get_contexts_for_userid($user->id);
        $ids = $contextlist->get_contextids();
        $this->assertCount(1, $ids);
        $this->assertEquals($this->catcontext->id, $ids[0]);
    }

    public function test_get_contexts_for_userid_returns_empty_when_user_has_no_memberships() {
        $user = $this->getDataGenerator()->create_user();
        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(0, $contextlist->get_contextids());
    }

    public function test_get_contexts_for_userid_deduplicates_when_user_has_many_memberships() {
        $user = $this->getDataGenerator()->create_user();
        $this->make_membership($user);
        $this->make_membership($user); // second relationship in same category context.

        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(1, $contextlist->get_contextids());
    }

    public function test_export_user_data_writes_memberships_for_each_context() {
        $user = $this->getDataGenerator()->create_user();
        $this->make_membership($user);

        $contextlist = new approved_contextlist($user, 'local_relationship', array($this->catcontext->id));
        provider::export_user_data($contextlist);

        $writer = \core_privacy\local\request\writer::with_context($this->catcontext);
        $this->assertTrue($writer->has_any_data());
        $exported = $writer->get_data(array(get_string('pluginname', 'local_relationship')));
        $this->assertNotEmpty($exported->memberships);
        $this->assertEquals('Fixture rel', $exported->memberships[0]->relationship);
        $this->assertEquals('Grupo', $exported->memberships[0]->group);
    }

    public function test_export_user_data_is_a_noop_when_context_list_is_empty() {
        $user = $this->getDataGenerator()->create_user();
        $this->make_membership($user);

        $contextlist = new approved_contextlist($user, 'local_relationship', array());
        provider::export_user_data($contextlist);

        $writer = \core_privacy\local\request\writer::with_context($this->catcontext);
        $this->assertFalse($writer->has_any_data());
    }

    public function test_delete_data_for_user_removes_only_target_users_memberships() {
        global $DB;

        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $this->make_membership($u1);
        $this->make_membership($u2);
        $this->assertEquals(1, $DB->count_records('relationship_members', array('userid' => $u1->id)));
        $this->assertEquals(1, $DB->count_records('relationship_members', array('userid' => $u2->id)));

        $contextlist = new approved_contextlist($u1, 'local_relationship', array($this->catcontext->id));
        provider::delete_data_for_user($contextlist);

        $this->assertEquals(0, $DB->count_records('relationship_members', array('userid' => $u1->id)));
        $this->assertEquals(1, $DB->count_records('relationship_members', array('userid' => $u2->id)));
    }

    public function test_delete_data_for_user_ignores_non_category_contexts() {
        global $DB;

        $u1 = $this->getDataGenerator()->create_user();
        $this->make_membership($u1);

        // System context is not CONTEXT_COURSECAT — provider should skip it silently.
        $syscontext = context_system::instance();
        $contextlist = new approved_contextlist($u1, 'local_relationship', array($syscontext->id));
        provider::delete_data_for_user($contextlist);

        $this->assertEquals(1, $DB->count_records('relationship_members', array('userid' => $u1->id)));
    }

    public function test_delete_data_for_all_users_in_context_clears_all_memberships() {
        global $DB;

        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $this->make_membership($u1);
        $this->make_membership($u2);
        $this->assertEquals(2, $DB->count_records('relationship_members'));

        provider::delete_data_for_all_users_in_context($this->catcontext);

        $this->assertEquals(0, $DB->count_records('relationship_members'));
    }

    public function test_delete_data_for_all_users_in_context_skips_non_category_context() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->make_membership($user);

        provider::delete_data_for_all_users_in_context(context_system::instance());

        $this->assertEquals(1, $DB->count_records('relationship_members'));
    }
}
