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
 * Tests para os handlers de eventos de cohort (classes/observer.php) e o cron.
 *
 * Cada teste dispara um evento core (cohort_add_member, cohort_remove_member,
 * cohort_delete_cohort) e verifica o efeito no estado do plugin — o que prova
 * indiretamente que o observer está registrado e que a lógica do handler
 * mantém relationship_members/cohorts em sync.
 *
 * @package    local_relationship
 * @copyright  2026 UFSC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/tag/lib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->dirroot . '/local/relationship/lib.php');

/**
 * @group local_relationship
 */
class local_relationship_observer_testcase extends advanced_testcase {

    /** @var stdClass */
    protected $category;
    /** @var context_coursecat */
    protected $catcontext;
    /** @var int */
    protected $roleid;
    /** @var stdClass */
    protected $cohort;
    /** @var int */
    protected $relationshipid;

    protected function setUp() {
        global $DB;

        $this->resetAfterTest();

        $this->category = $this->getDataGenerator()->create_category();
        $this->catcontext = context_coursecat::instance($this->category->id);
        $this->cohort = $this->getDataGenerator()->create_cohort(array('contextid' => $this->catcontext->id));
        $this->roleid = $DB->get_field('role', 'id', array('shortname' => 'student'));

        $this->relationshipid = relationship_add_relationship((object) array(
            'contextid' => $this->catcontext->id,
            'name' => 'Observer fixture',
        ));
    }

    protected function add_cohort_link($overrides = array()) {
        $rc = (object) array_merge(array(
            'relationshipid' => $this->relationshipid,
            'cohortid' => $this->cohort->id,
            'roleid' => $this->roleid,
            'allowdupsingroups' => 0,
            'uniformdistribution' => 0,
        ), $overrides);
        return relationship_add_cohort($rc);
    }

    protected function add_group($overrides = array()) {
        $rg = (object) array_merge(array(
            'relationshipid' => $this->relationshipid,
            'name' => 'Grupo',
            'userlimit' => 0,
            'uniformdistribution' => 0,
        ), $overrides);
        return relationship_add_group($rg);
    }

    // ---------------------------------------------------------------------
    // member_added handler
    // ---------------------------------------------------------------------

    public function test_member_added_observer_distributes_user_when_cohort_has_uniformdistribution() {
        global $DB;

        $rcid = $this->add_cohort_link(array('uniformdistribution' => 1));
        $gid = $this->add_group(array('uniformdistribution' => 1));

        $user = $this->getDataGenerator()->create_user();
        cohort_add_member($this->cohort->id, $user->id);

        // O observer chama relationship_uniformly_distribute_users sob o relationship_cohort
        // do user, que aloca o user no único group com uniformdistribution=1.
        $this->assertTrue($DB->record_exists('relationship_members', array(
            'relationshipgroupid' => $gid,
            'relationshipcohortid' => $rcid,
            'userid' => $user->id,
        )));
    }

    public function test_member_added_observer_does_not_distribute_when_uniformdistribution_disabled() {
        global $DB;

        $this->add_cohort_link(array('uniformdistribution' => 0));
        $this->add_group(array('uniformdistribution' => 0));

        $user = $this->getDataGenerator()->create_user();
        cohort_add_member($this->cohort->id, $user->id);

        // Sem flag, observer não dispara distribuição.
        $this->assertEquals(0, $DB->count_records('relationship_members', array('userid' => $user->id)));
    }

    public function test_member_added_observer_does_nothing_when_cohort_is_unrelated() {
        global $DB;

        $this->add_cohort_link(array('uniformdistribution' => 1));
        $this->add_group(array('uniformdistribution' => 1));

        // Cohort B não está ligado ao relationship; adicionar membro nele não cria nada.
        $cohortb = $this->getDataGenerator()->create_cohort(array('contextid' => $this->catcontext->id));
        $user = $this->getDataGenerator()->create_user();
        cohort_add_member($cohortb->id, $user->id);

        $this->assertEquals(0, $DB->count_records('relationship_members', array('userid' => $user->id)));
    }

    // ---------------------------------------------------------------------
    // member_removed handler
    // ---------------------------------------------------------------------

    public function test_member_removed_observer_removes_user_from_relationship_groups() {
        global $DB;

        $rcid = $this->add_cohort_link();
        $gid = $this->add_group();

        $user = $this->getDataGenerator()->create_user();
        cohort_add_member($this->cohort->id, $user->id);
        relationship_add_member($gid, $rcid, $user->id);
        $this->assertEquals(1, $DB->count_records('relationship_members', array('userid' => $user->id)));

        cohort_remove_member($this->cohort->id, $user->id);

        // Observer roda relationship_remove_member para cada (group, cohort) onde o user estava.
        $this->assertEquals(0, $DB->count_records('relationship_members', array('userid' => $user->id)));
    }

    public function test_member_removed_observer_does_not_touch_other_users() {
        global $DB;

        $rcid = $this->add_cohort_link();
        $gid = $this->add_group();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        cohort_add_member($this->cohort->id, $user1->id);
        cohort_add_member($this->cohort->id, $user2->id);
        relationship_add_member($gid, $rcid, $user1->id);
        relationship_add_member($gid, $rcid, $user2->id);

        cohort_remove_member($this->cohort->id, $user1->id);

        $this->assertEquals(0, $DB->count_records('relationship_members', array('userid' => $user1->id)));
        $this->assertEquals(1, $DB->count_records('relationship_members', array('userid' => $user2->id)));
    }

    // ---------------------------------------------------------------------
    // cron
    // ---------------------------------------------------------------------

    public function test_cron_returns_true_and_distributes_remaining_members() {
        global $DB;

        $rcid = $this->add_cohort_link(array('uniformdistribution' => 1));
        $gid = $this->add_group(array('uniformdistribution' => 1));

        // Pré-popula a cohort SEM passar pelo observer (insert direto), simulando o cenário
        // que o cron existe para resgatar: eventos perdidos ou criados antes do plugin.
        $user = $this->getDataGenerator()->create_user();
        $DB->insert_record('cohort_members', (object) array(
            'cohortid' => $this->cohort->id,
            'userid' => $user->id,
            'timeadded' => time(),
        ));

        $this->assertEquals(0, $DB->count_records('relationship_members'));
        $this->assertTrue(local_relationship_cron());
        $this->assertEquals(1, $DB->count_records('relationship_members', array(
            'relationshipgroupid' => $gid,
            'relationshipcohortid' => $rcid,
            'userid' => $user->id,
        )));
    }
}
