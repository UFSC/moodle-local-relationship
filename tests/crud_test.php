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
 * Tests CRUD para relationship, cohort, group e member (lib.php).
 *
 * Cobre defaults, validações, eventos disparados, edge cases (duplicatas, dependências,
 * transferências de membros entre cohorts candidatas).
 *
 * @package    local_relationship
 * @copyright  2026 UFSC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/relationship/lib.php');
require_once($CFG->dirroot . '/local/relationship/locallib.php');

/**
 * @group local_relationship
 */
class local_relationship_crud_testcase extends advanced_testcase {

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

        $this->resetAfterTest();

        $this->category = $this->getDataGenerator()->create_category();
        $this->catcontext = context_coursecat::instance($this->category->id);
        $this->cohort = $this->getDataGenerator()->create_cohort(array('contextid' => $this->catcontext->id));
        $this->roleid = $DB->get_field('role', 'id', array('shortname' => 'student'));
    }

    /**
     * Cria um relationship básico e devolve o ID.
     *
     * @param array $overrides
     * @return int
     */
    protected function make_relationship($overrides = array()) {
        $rl = (object) array_merge(array(
            'contextid' => $this->catcontext->id,
            'name' => 'Relacionamento de teste',
        ), $overrides);
        return relationship_add_relationship($rl);
    }

    /**
     * Cria um relationship_cohorts atrelando o cohort fixture ao relationship indicado.
     *
     * @param int $relationshipid
     * @param array $overrides
     * @return int
     */
    protected function make_cohort_link($relationshipid, $overrides = array()) {
        $rc = (object) array_merge(array(
            'relationshipid' => $relationshipid,
            'cohortid' => $this->cohort->id,
            'roleid' => $this->roleid,
            'allowdupsingroups' => 0,
            'uniformdistribution' => 0,
        ), $overrides);
        return relationship_add_cohort($rc);
    }

    /**
     * Cria um relationship_groups.
     *
     * @param int $relationshipid
     * @param array $overrides
     * @return int
     */
    protected function make_group($relationshipid, $overrides = array()) {
        $rg = (object) array_merge(array(
            'relationshipid' => $relationshipid,
            'name' => 'Grupo de teste',
            'userlimit' => 0,
            'uniformdistribution' => 0,
        ), $overrides);
        return relationship_add_group($rg);
    }

    // ---------------------------------------------------------------------
    // relationship_add_relationship
    // ---------------------------------------------------------------------

    public function test_add_relationship_assigns_default_values_for_optional_fields() {
        global $DB;

        $id = relationship_add_relationship((object) array(
            'contextid' => $this->catcontext->id,
            'name' => 'Mínimo',
        ));
        $this->assertNotEmpty($id);

        $record = $DB->get_record('relationship', array('id' => $id), '*', MUST_EXIST);
        $this->assertSame(null, $record->idnumber);
        $this->assertSame('', $record->description);
        $this->assertEquals(FORMAT_HTML, $record->descriptionformat);
        $this->assertSame('', $record->component);
        $this->assertGreaterThan(0, $record->timecreated);
        $this->assertSame($record->timecreated, $record->timemodified);
    }

    public function test_add_relationship_throws_when_name_missing() {
        $this->setExpectedException('coding_exception');

        relationship_add_relationship((object) array(
            'contextid' => $this->catcontext->id,
        ));
    }

    public function test_add_relationship_trims_whitespace_from_name() {
        global $DB;

        $id = $this->make_relationship(array('name' => "   nome com espaços   "));

        $record = $DB->get_record('relationship', array('id' => $id), '*', MUST_EXIST);
        $this->assertSame('nome com espaços', $record->name);
    }

    public function test_add_relationship_triggers_created_event() {
        $sink = $this->redirectEvents();

        $id = $this->make_relationship();

        $events = $sink->get_events();
        $sink->close();
        $this->assertCount(1, $events);
        $this->assertInstanceOf('\\local_relationship\\event\\relationship_created', $events[0]);
        $this->assertEquals($id, $events[0]->objectid);
    }

    // ---------------------------------------------------------------------
    // relationship_get_relationship
    // ---------------------------------------------------------------------

    public function test_get_relationship_returns_record_with_tags_field() {
        $id = $this->make_relationship();

        $relationship = relationship_get_relationship($id);

        $this->assertEquals($id, $relationship->id);
        $this->assertObjectHasAttribute('tags', $relationship);
        $this->assertInternalType('array', $relationship->tags);
    }

    // ---------------------------------------------------------------------
    // relationship_update_relationship
    // ---------------------------------------------------------------------

    public function test_update_relationship_advances_timemodified() {
        global $DB;

        $id = $this->make_relationship();
        $original = $DB->get_record('relationship', array('id' => $id), '*', MUST_EXIST);

        // Força delta para garantir que timemodified mude mesmo com time() de baixa resolução.
        $DB->set_field('relationship', 'timemodified', $original->timemodified - 10, array('id' => $id));

        $update = clone $original;
        $update->name = 'Atualizado';
        $update->tags = array();
        $this->assertTrue(relationship_update_relationship($update));

        $after = $DB->get_record('relationship', array('id' => $id), '*', MUST_EXIST);
        $this->assertSame('Atualizado', $after->name);
        $this->assertGreaterThan($original->timemodified - 10, $after->timemodified);
    }

    public function test_update_relationship_triggers_updated_event() {
        $id = $this->make_relationship();
        $relationship = relationship_get_relationship($id);

        $sink = $this->redirectEvents();
        $relationship->name = 'Renomeado';
        relationship_update_relationship($relationship);

        $events = $sink->get_events();
        $sink->close();
        $this->assertCount(1, $events);
        $this->assertInstanceOf('\\local_relationship\\event\\relationship_updated', $events[0]);
    }

    // ---------------------------------------------------------------------
    // relationship_delete_relationship
    // ---------------------------------------------------------------------

    public function test_delete_relationship_with_no_cohorts_removes_record_and_groups() {
        global $DB;

        $id = $this->make_relationship();
        $this->make_group($id);

        $relationship = relationship_get_relationship($id);
        $this->assertTrue(relationship_delete_relationship($relationship));

        $this->assertFalse($DB->record_exists('relationship', array('id' => $id)));
        $this->assertFalse($DB->record_exists('relationship_groups', array('relationshipid' => $id)));
    }

    public function test_delete_relationship_with_cohorts_returns_negative_one_and_keeps_data() {
        global $DB;

        $id = $this->make_relationship();
        $this->make_cohort_link($id);

        $relationship = relationship_get_relationship($id);
        $result = relationship_delete_relationship($relationship);

        $this->assertSame(-1, $result);
        $this->assertTrue($DB->record_exists('relationship', array('id' => $id)));
        $this->assertTrue($DB->record_exists('relationship_cohorts', array('relationshipid' => $id)));
    }

    public function test_delete_relationship_triggers_deleted_event() {
        $id = $this->make_relationship();
        $relationship = relationship_get_relationship($id);

        $sink = $this->redirectEvents();
        relationship_delete_relationship($relationship);

        $events = $sink->get_events();
        $sink->close();
        $this->assertCount(1, $events);
        $this->assertInstanceOf('\\local_relationship\\event\\relationship_deleted', $events[0]);
    }

    // ---------------------------------------------------------------------
    // relationship_add_cohort / update_cohort / get_cohorts / get_cohort
    // ---------------------------------------------------------------------

    public function test_add_cohort_initializes_timestamps_when_missing() {
        global $DB;

        $rid = $this->make_relationship();
        $cid = $this->make_cohort_link($rid);

        $record = $DB->get_record('relationship_cohorts', array('id' => $cid), '*', MUST_EXIST);
        $this->assertGreaterThan(0, $record->timecreated);
        $this->assertSame($record->timecreated, $record->timemodified);
    }

    public function test_update_cohort_advances_timemodified() {
        global $DB;

        $rid = $this->make_relationship();
        $cid = $this->make_cohort_link($rid);
        $DB->set_field('relationship_cohorts', 'timemodified', 1, array('id' => $cid));

        $rc = $DB->get_record('relationship_cohorts', array('id' => $cid), '*', MUST_EXIST);
        relationship_update_cohort($rc);

        $after = $DB->get_record('relationship_cohorts', array('id' => $cid), '*', MUST_EXIST);
        $this->assertGreaterThan(1, $after->timemodified);
    }

    public function test_get_cohort_full_attaches_cohort_record_and_role_name() {
        $rid = $this->make_relationship();
        $cid = $this->make_cohort_link($rid);

        $rc = relationship_get_cohort($cid, true);

        $this->assertEquals($this->cohort->id, $rc->cohort->id);
        $this->assertNotEmpty($rc->role_name);
        $this->assertNotSame(false, $rc->role_name);
    }

    public function test_get_cohort_returns_false_role_name_when_role_missing() {
        global $DB;

        $rid = $this->make_relationship();
        $cid = $this->make_cohort_link($rid, array('roleid' => 999999));

        $rc = relationship_get_cohort($cid, true);
        $this->assertFalse($rc->role_name);
    }

    public function test_get_cohorts_returns_only_links_of_given_relationship() {
        $r1 = $this->make_relationship(array('name' => 'R1'));
        $r2 = $this->make_relationship(array('name' => 'R2'));
        $this->make_cohort_link($r1);
        $this->make_cohort_link($r1);
        $this->make_cohort_link($r2);

        $r1cohorts = relationship_get_cohorts($r1);
        $r2cohorts = relationship_get_cohorts($r2);

        $this->assertCount(2, $r1cohorts);
        $this->assertCount(1, $r2cohorts);
    }

    // ---------------------------------------------------------------------
    // relationship_delete_cohort — transferência de membros
    // ---------------------------------------------------------------------

    public function test_delete_cohort_without_members_just_removes_link() {
        global $DB;

        $rid = $this->make_relationship();
        $cid = $this->make_cohort_link($rid);

        $this->assertNotFalse(relationship_delete_cohort($DB->get_record('relationship_cohorts', array('id' => $cid))));
        $this->assertFalse($DB->record_exists('relationship_cohorts', array('id' => $cid)));
    }

    public function test_delete_cohort_transfers_member_to_candidate_with_same_role_and_user() {
        global $DB;

        $rid = $this->make_relationship();
        $user = $this->getDataGenerator()->create_user();
        cohort_add_member($this->cohort->id, $user->id);

        $cohortb = $this->getDataGenerator()->create_cohort(array('contextid' => $this->catcontext->id));
        cohort_add_member($cohortb->id, $user->id);

        $rc1 = $this->make_cohort_link($rid);
        $rc2 = $this->make_cohort_link($rid, array('cohortid' => $cohortb->id));
        $gid = $this->make_group($rid);

        $memberid = relationship_add_member($gid, $rc1, $user->id);
        $this->assertNotFalse($memberid);

        relationship_delete_cohort($DB->get_record('relationship_cohorts', array('id' => $rc1)));

        // Member migrou para rc2, mantendo o mesmo group.
        $remaining = $DB->get_records('relationship_members', array('userid' => $user->id));
        $this->assertCount(1, $remaining);
        $row = reset($remaining);
        $this->assertEquals($rc2, $row->relationshipcohortid);
        $this->assertEquals($gid, $row->relationshipgroupid);
    }

    public function test_delete_cohort_removes_member_when_no_candidate_has_the_user() {
        global $DB;

        $rid = $this->make_relationship();
        $user = $this->getDataGenerator()->create_user();
        cohort_add_member($this->cohort->id, $user->id);

        $cohortb = $this->getDataGenerator()->create_cohort(array('contextid' => $this->catcontext->id));
        // candidate existe mas user não está nela: não há transferência possível.
        $rc1 = $this->make_cohort_link($rid);
        $rc2 = $this->make_cohort_link($rid, array('cohortid' => $cohortb->id));
        $gid = $this->make_group($rid);

        relationship_add_member($gid, $rc1, $user->id);

        relationship_delete_cohort($DB->get_record('relationship_cohorts', array('id' => $rc1)));

        $this->assertFalse($DB->record_exists('relationship_members', array('userid' => $user->id)));
    }

    public function test_delete_cohort_deletes_duplicate_when_target_already_has_member() {
        global $DB;

        $rid = $this->make_relationship();
        $user = $this->getDataGenerator()->create_user();
        cohort_add_member($this->cohort->id, $user->id);

        $cohortb = $this->getDataGenerator()->create_cohort(array('contextid' => $this->catcontext->id));
        cohort_add_member($cohortb->id, $user->id);

        $rc1 = $this->make_cohort_link($rid);
        $rc2 = $this->make_cohort_link($rid, array('cohortid' => $cohortb->id));
        $gid = $this->make_group($rid);

        relationship_add_member($gid, $rc1, $user->id);
        relationship_add_member($gid, $rc2, $user->id);

        relationship_delete_cohort($DB->get_record('relationship_cohorts', array('id' => $rc1)));

        // Deve sobrar apenas o member original em rc2 (o "transferido" foi descartado para evitar duplicata).
        $remaining = $DB->get_records('relationship_members', array('userid' => $user->id));
        $this->assertCount(1, $remaining);
        $row = reset($remaining);
        $this->assertEquals($rc2, $row->relationshipcohortid);
    }

    public function test_delete_cohort_ignores_candidate_with_different_role() {
        global $DB;

        $rid = $this->make_relationship();
        $user = $this->getDataGenerator()->create_user();
        cohort_add_member($this->cohort->id, $user->id);

        $cohortb = $this->getDataGenerator()->create_cohort(array('contextid' => $this->catcontext->id));
        cohort_add_member($cohortb->id, $user->id);

        $teacherrole = $DB->get_field('role', 'id', array('shortname' => 'editingteacher'));
        $rc1 = $this->make_cohort_link($rid);
        $this->make_cohort_link($rid, array('cohortid' => $cohortb->id, 'roleid' => $teacherrole));
        $gid = $this->make_group($rid);

        relationship_add_member($gid, $rc1, $user->id);

        relationship_delete_cohort($DB->get_record('relationship_cohorts', array('id' => $rc1)));

        // Sem candidato com mesma role, member é removido (não transferido).
        $this->assertFalse($DB->record_exists('relationship_members', array('userid' => $user->id)));
    }

    // ---------------------------------------------------------------------
    // relationship_add_group / update_group / get_groups
    // ---------------------------------------------------------------------

    public function test_add_group_trims_name_and_initializes_timestamps() {
        global $DB;

        $rid = $this->make_relationship();
        $gid = $this->make_group($rid, array('name' => "   Grupo X   "));

        $record = $DB->get_record('relationship_groups', array('id' => $gid), '*', MUST_EXIST);
        $this->assertSame('Grupo X', $record->name);
        $this->assertGreaterThan(0, $record->timecreated);
        $this->assertSame($record->timecreated, $record->timemodified);
    }

    public function test_add_group_with_nonexistent_relationship_throws() {
        $this->setExpectedException('dml_missing_record_exception');

        relationship_add_group((object) array(
            'relationshipid' => 999999,
            'name' => 'Órfão',
            'userlimit' => 0,
            'uniformdistribution' => 0,
        ));
    }

    public function test_add_group_triggers_created_event() {
        $rid = $this->make_relationship();

        $sink = $this->redirectEvents();
        $this->make_group($rid);

        $events = $sink->get_events();
        $sink->close();
        $this->assertCount(1, $events);
        $this->assertInstanceOf('\\local_relationship\\event\\relationshipgroup_created', $events[0]);
    }

    public function test_update_group_trims_name_when_present() {
        global $DB;

        $rid = $this->make_relationship();
        $gid = $this->make_group($rid);

        $group = $DB->get_record('relationship_groups', array('id' => $gid));
        $group->name = '  Renomeado  ';
        relationship_update_group($group);

        $after = $DB->get_record('relationship_groups', array('id' => $gid));
        $this->assertSame('Renomeado', $after->name);
    }

    public function test_get_groups_includes_size_with_member_count() {
        global $DB;

        $rid = $this->make_relationship();
        $rc = $this->make_cohort_link($rid);
        $g1 = $this->make_group($rid, array('name' => 'Grupo A'));
        $g2 = $this->make_group($rid, array('name' => 'Grupo B'));

        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        cohort_add_member($this->cohort->id, $u1->id);
        cohort_add_member($this->cohort->id, $u2->id);
        relationship_add_member($g1, $rc, $u1->id);
        relationship_add_member($g1, $rc, $u2->id);

        $groups = relationship_get_groups($rid);
        $this->assertEquals(2, $groups[$g1]->size);
        $this->assertEquals(0, $groups[$g2]->size);
    }

    // ---------------------------------------------------------------------
    // relationship_delete_group
    // ---------------------------------------------------------------------

    public function test_delete_group_cascades_to_members() {
        global $DB;

        $rid = $this->make_relationship();
        $rc = $this->make_cohort_link($rid);
        $gid = $this->make_group($rid);

        $user = $this->getDataGenerator()->create_user();
        cohort_add_member($this->cohort->id, $user->id);
        relationship_add_member($gid, $rc, $user->id);

        $group = $DB->get_record('relationship_groups', array('id' => $gid));
        $this->assertTrue(relationship_delete_group($group));

        $this->assertFalse($DB->record_exists('relationship_groups', array('id' => $gid)));
        $this->assertFalse($DB->record_exists('relationship_members', array('relationshipgroupid' => $gid)));
    }

    public function test_delete_group_triggers_deleted_event() {
        global $DB;

        $rid = $this->make_relationship();
        $gid = $this->make_group($rid);
        $group = $DB->get_record('relationship_groups', array('id' => $gid));

        $sink = $this->redirectEvents();
        relationship_delete_group($group);

        $events = $sink->get_events();
        $sink->close();
        $found = false;
        foreach ($events as $e) {
            if ($e instanceof \local_relationship\event\relationshipgroup_deleted) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    // ---------------------------------------------------------------------
    // relationship_add_member / remove_member
    // ---------------------------------------------------------------------

    public function test_add_member_returns_id_for_new_record() {
        global $DB;

        $rid = $this->make_relationship();
        $rc = $this->make_cohort_link($rid);
        $gid = $this->make_group($rid);
        $user = $this->getDataGenerator()->create_user();

        $memberid = relationship_add_member($gid, $rc, $user->id);

        $this->assertNotFalse($memberid);
        $this->assertTrue($DB->record_exists('relationship_members', array('id' => $memberid)));
    }

    public function test_add_member_with_duplicate_triple_returns_false() {
        $rid = $this->make_relationship();
        $rc = $this->make_cohort_link($rid);
        $gid = $this->make_group($rid);
        $user = $this->getDataGenerator()->create_user();

        $this->assertNotFalse(relationship_add_member($gid, $rc, $user->id));
        // Mesma tupla (group, cohort, user) — deve falhar silenciosamente.
        $this->assertFalse(relationship_add_member($gid, $rc, $user->id));
    }

    public function test_add_member_with_invalid_group_throws() {
        $rid = $this->make_relationship();
        $rc = $this->make_cohort_link($rid);
        $user = $this->getDataGenerator()->create_user();

        $this->setExpectedException('dml_missing_record_exception');
        relationship_add_member(999999, $rc, $user->id);
    }

    public function test_add_member_triggers_member_added_event_with_relateduserid() {
        $rid = $this->make_relationship();
        $rc = $this->make_cohort_link($rid);
        $gid = $this->make_group($rid);
        $user = $this->getDataGenerator()->create_user();

        $sink = $this->redirectEvents();
        relationship_add_member($gid, $rc, $user->id);

        $events = $sink->get_events();
        $sink->close();
        $found = null;
        foreach ($events as $e) {
            if ($e instanceof \local_relationship\event\relationshipgroup_member_added) {
                $found = $e;
                break;
            }
        }
        $this->assertNotNull($found);
        $this->assertEquals($user->id, $found->relateduserid);
        $this->assertEquals($gid, $found->objectid);
    }

    public function test_remove_member_returns_true_when_record_existed() {
        global $DB;

        $rid = $this->make_relationship();
        $rc = $this->make_cohort_link($rid);
        $gid = $this->make_group($rid);
        $user = $this->getDataGenerator()->create_user();
        relationship_add_member($gid, $rc, $user->id);

        $this->assertTrue(relationship_remove_member($gid, $rc, $user->id));
        $this->assertFalse($DB->record_exists('relationship_members', array(
            'relationshipgroupid' => $gid,
            'relationshipcohortid' => $rc,
            'userid' => $user->id,
        )));
    }

    public function test_remove_member_returns_false_when_record_absent() {
        $rid = $this->make_relationship();
        $rc = $this->make_cohort_link($rid);
        $gid = $this->make_group($rid);
        $user = $this->getDataGenerator()->create_user();

        $this->assertFalse(relationship_remove_member($gid, $rc, $user->id));
    }

    public function test_remove_member_does_not_trigger_event_when_nothing_was_deleted() {
        $rid = $this->make_relationship();
        $rc = $this->make_cohort_link($rid);
        $gid = $this->make_group($rid);
        $user = $this->getDataGenerator()->create_user();

        $sink = $this->redirectEvents();
        relationship_remove_member($gid, $rc, $user->id);

        $events = $sink->get_events();
        $sink->close();
        foreach ($events as $e) {
            $this->assertNotInstanceOf('\\local_relationship\\event\\relationshipgroup_member_removed', $e);
        }
    }
}
