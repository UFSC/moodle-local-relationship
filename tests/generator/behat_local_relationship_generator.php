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
 * Behat data generator wiring for local_relationship.
 *
 * Exposes "the following 'local_relationship > X' exist" steps for
 * relationships, cohort links, groups and members. Without this,
 * Background blocks have to drive the UI to create each row.
 *
 * @package    local_relationship
 * @category   test
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class behat_local_relationship_generator extends behat_generator_base {

    protected function get_creatable_entities(): array {
        return [
            'relationships' => [
                'datagenerator' => 'relationship',
                'required' => ['name', 'category'],
            ],
            'relationship cohorts' => [
                'datagenerator' => 'relationship_cohort',
                'required' => ['relationship', 'cohort', 'role'],
                'switchids' => [
                    'relationship' => 'relationshipid',
                    'cohort' => 'cohortid',
                    'role' => 'roleid',
                ],
            ],
            'relationship groups' => [
                'datagenerator' => 'relationship_group',
                'required' => ['relationship', 'name'],
                'switchids' => ['relationship' => 'relationshipid'],
            ],
            'relationship members' => [
                'datagenerator' => 'relationship_member',
                'required' => ['relationship', 'cohort', 'role', 'group', 'user'],
                'switchids' => [
                    'cohort' => 'cohortid',
                    'role' => 'roleid',
                    'user' => 'userid',
                ],
            ],
        ];
    }

    /**
     * Convert the 'category' idnumber column to the contextid field the
     * data generator expects. Switchids can't be used because the
     * generator base hard-codes `get_<element>_id` and the inherited
     * get_category_id() returns a category id, not a context id.
     *
     * @param array $data
     * @return array
     */
    protected function preprocess_relationship($data) {
        $categoryid = $this->get_category_id($data['category']);
        $data['contextid'] = context_coursecat::instance($categoryid)->id;
        unset($data['category']);
        return $data;
    }

    /**
     * Resolve a relationship name to its id. Names are unique enough
     * within a Behat scenario for this to be safe.
     *
     * @param string $name
     * @return int relationship.id
     */
    protected function get_relationship_id($name) {
        global $DB;
        if (!$id = $DB->get_field('relationship', 'id', array('name' => $name))) {
            throw new Exception('There is no relationship named "' . $name . '".');
        }
        return $id;
    }

    /**
     * Members need a composite lookup that switchids cannot express:
     * (relationship, cohort, role) maps to a relationship_cohorts row,
     * and (relationship, group) maps to a relationship_groups row.
     * Resolve both before delegating to create_relationship_member.
     *
     * @param array $data row data with relationship/cohortid/roleid/group fields
     * @return array transformed row with relationshipcohortid + relationshipgroupid
     */
    protected function preprocess_relationship_member($data) {
        global $DB;

        $relationshipid = $this->get_relationship_id($data['relationship']);

        $rc = $DB->get_record('relationship_cohorts', array(
                'relationshipid' => $relationshipid,
                'cohortid' => $data['cohortid'],
                'roleid' => $data['roleid']), '*', MUST_EXIST);
        $data['relationshipcohortid'] = $rc->id;

        $rg = $DB->get_record('relationship_groups', array(
                'relationshipid' => $relationshipid,
                'name' => $data['group']), '*', MUST_EXIST);
        $data['relationshipgroupid'] = $rg->id;

        unset($data['relationship']);
        unset($data['cohortid']);
        unset($data['roleid']);
        unset($data['group']);

        return $data;
    }
}
