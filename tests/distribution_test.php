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
 * Tests para o algoritmo de distribuição uniforme em relationship_uniformly_distribute_users.
 *
 * @package    local_relationship
 * @copyright  2026 UFSC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
// tag/lib.php precisa estar carregado antes de lib.php porque relationship_add_relationship()
// chama tag_set() (lib.php não requer tag/lib.php por conta própria; locallib.php é quem faz).
require_once($CFG->dirroot . '/tag/lib.php');
require_once($CFG->dirroot . '/local/relationship/lib.php');

/**
 * @group local_relationship
 */
class local_relationship_distribution_testcase extends advanced_testcase {

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
    /** @var stdClass */
    protected $relationshipcohort;

    protected function setUp() {
        global $DB;

        $this->resetAfterTest();

        $this->category = $this->getDataGenerator()->create_category();
        $this->catcontext = context_coursecat::instance($this->category->id);
        $this->cohort = $this->getDataGenerator()->create_cohort(array('contextid' => $this->catcontext->id));
        $this->roleid = $DB->get_field('role', 'id', array('shortname' => 'student'));

        $this->relationshipid = relationship_add_relationship((object) array(
            'contextid' => $this->catcontext->id,
            'name' => 'Distribuição',
        ));

        $rcid = relationship_add_cohort((object) array(
            'relationshipid' => $this->relationshipid,
            'cohortid' => $this->cohort->id,
            'roleid' => $this->roleid,
            'allowdupsingroups' => 0,
            'uniformdistribution' => 1,
        ));
        $this->relationshipcohort = $DB->get_record('relationship_cohorts', array('id' => $rcid));
    }

    /**
     * Cria um relationship_group com uniformdistribution=1 (para entrar no pool de distribuição).
     */
    protected function add_uniform_group($name, $userlimit = 0) {
        return relationship_add_group((object) array(
            'relationshipid' => $this->relationshipid,
            'name' => $name,
            'userlimit' => $userlimit,
            'uniformdistribution' => 1,
        ));
    }

    /**
     * Cria N users e devolve seus IDs em ordem de criação.
     */
    protected function make_users($n) {
        $ids = array();
        for ($i = 0; $i < $n; $i++) {
            $u = $this->getDataGenerator()->create_user();
            $ids[] = $u->id;
        }
        return $ids;
    }

    /**
     * Conta membros num grupo.
     */
    protected function count_members($groupid) {
        global $DB;
        return $DB->count_records('relationship_members', array('relationshipgroupid' => $groupid));
    }

    // ---------------------------------------------------------------------

    public function test_empty_userids_does_not_create_any_members() {
        global $DB;

        $this->add_uniform_group('A');
        relationship_uniformly_distribute_users($this->relationshipcohort, array());

        $this->assertEquals(0, $DB->count_records('relationship_members'));
    }

    public function test_no_groups_at_all_does_not_create_any_members() {
        global $DB;

        $users = $this->make_users(3);
        relationship_uniformly_distribute_users($this->relationshipcohort, $users);

        $this->assertEquals(0, $DB->count_records('relationship_members'));
    }

    public function test_groups_without_uniformdistribution_flag_are_ignored() {
        global $DB;

        // Grupo sem uniformdistribution não entra no pool.
        relationship_add_group((object) array(
            'relationshipid' => $this->relationshipid,
            'name' => 'Excluído',
            'userlimit' => 0,
            'uniformdistribution' => 0,
        ));

        $users = $this->make_users(2);
        relationship_uniformly_distribute_users($this->relationshipcohort, $users);

        $this->assertEquals(0, $DB->count_records('relationship_members'));
    }

    public function test_single_unlimited_group_receives_all_users() {
        $gid = $this->add_uniform_group('Único');
        $users = $this->make_users(5);

        relationship_uniformly_distribute_users($this->relationshipcohort, $users);

        $this->assertEquals(5, $this->count_members($gid));
    }

    public function test_two_unlimited_groups_split_users_evenly() {
        $a = $this->add_uniform_group('A');
        $b = $this->add_uniform_group('B');
        $users = $this->make_users(4);

        relationship_uniformly_distribute_users($this->relationshipcohort, $users);

        $this->assertEquals(2, $this->count_members($a));
        $this->assertEquals(2, $this->count_members($b));
    }

    public function test_two_groups_with_odd_user_count_assigns_extra_to_first() {
        // Empate em contagem → o primeiro grupo (menor id) vence o desempate.
        $a = $this->add_uniform_group('A');
        $b = $this->add_uniform_group('B');
        $users = $this->make_users(5);

        relationship_uniformly_distribute_users($this->relationshipcohort, $users);

        $this->assertEquals(3, $this->count_members($a));
        $this->assertEquals(2, $this->count_members($b));
    }

    public function test_userlimit_caps_group_size() {
        $a = $this->add_uniform_group('A', 2);
        $b = $this->add_uniform_group('B', 2);
        $users = $this->make_users(4);

        relationship_uniformly_distribute_users($this->relationshipcohort, $users);

        $this->assertEquals(2, $this->count_members($a));
        $this->assertEquals(2, $this->count_members($b));
    }

    public function test_excess_users_are_dropped_when_all_groups_are_full() {
        global $DB;

        $a = $this->add_uniform_group('A', 1);
        $b = $this->add_uniform_group('B', 1);
        $users = $this->make_users(5);

        relationship_uniformly_distribute_users($this->relationshipcohort, $users);

        $this->assertEquals(1, $this->count_members($a));
        $this->assertEquals(1, $this->count_members($b));
        // Total = 2 (limit somado), 3 usuários ficaram de fora.
        $this->assertEquals(2, $DB->count_records('relationship_members'));
    }

    public function test_userlimit_zero_means_unlimited_even_with_other_limited_groups() {
        $unlimited = $this->add_uniform_group('Ilimitado', 0);
        $limited = $this->add_uniform_group('Limitado', 1);
        $users = $this->make_users(5);

        relationship_uniformly_distribute_users($this->relationshipcohort, $users);

        // Limited recebe 1; unlimited absorve os 4 restantes (sempre tem count menor).
        $this->assertEquals(1, $this->count_members($limited));
        $this->assertEquals(4, $this->count_members($unlimited));
    }

    public function test_already_populated_group_starts_with_offset_count() {
        global $DB;

        $a = $this->add_uniform_group('A');
        $b = $this->add_uniform_group('B');

        // Pré-popula A com 2 membros antes da distribuição.
        $pre = $this->make_users(2);
        foreach ($pre as $uid) {
            relationship_add_member($a, $this->relationshipcohort->id, $uid);
        }

        $newusers = $this->make_users(2);
        relationship_uniformly_distribute_users($this->relationshipcohort, $newusers);

        // Algoritmo escolhe B (count=0) duas vezes antes de empatar com A (count=2).
        $this->assertEquals(2, $this->count_members($a));
        $this->assertEquals(2, $this->count_members($b));
    }

    public function test_distribution_is_idempotent_for_already_allocated_users() {
        // Distribuir o mesmo userid 2x: a segunda volta é bloqueada pelo unique index
        // de relationship_members (add_member retorna false), mas o algoritmo segue
        // contando como se tivesse alocado. Documenta o comportamento atual.
        $a = $this->add_uniform_group('A');
        $b = $this->add_uniform_group('B');

        $users = $this->make_users(2);
        relationship_uniformly_distribute_users($this->relationshipcohort, $users);
        relationship_uniformly_distribute_users($this->relationshipcohort, $users);

        // A primeira chamada já distribuiu; a segunda não cria duplicatas.
        $this->assertEquals(1, $this->count_members($a));
        $this->assertEquals(1, $this->count_members($b));
    }
}
