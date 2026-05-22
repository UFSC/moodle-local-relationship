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
 * Test data generator for local_relationship.
 *
 * Provides programmatic creation of relationships, cohort links,
 * groups and members so Behat features can set up state via
 * declarative `the following ... exist` tables instead of driving
 * the UI through a long Background.
 *
 * @package    local_relationship
 * @category   test
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class local_relationship_generator extends component_generator_base {

    /**
     * Create a relationship in the given category context.
     *
     * Required: name, contextid (already resolved by the caller).
     *
     * @param array|stdClass $record
     * @return stdClass the inserted relationship row
     */
    public function create_relationship($record) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/relationship/lib.php');

        $record = (object) (array) $record;
        if (empty($record->name)) {
            throw new coding_exception('create_relationship requires a name.');
        }
        if (empty($record->contextid)) {
            throw new coding_exception('create_relationship requires a contextid.');
        }

        $id = relationship_add_relationship($record);
        return $DB->get_record('relationship', array('id' => $id), '*', MUST_EXIST);
    }

    /**
     * Attach a cohort/role to an existing relationship.
     *
     * Required: relationshipid, cohortid, roleid.
     *
     * @param array|stdClass $record
     * @return stdClass the inserted relationship_cohorts row
     */
    public function create_relationship_cohort($record) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/relationship/lib.php');

        $record = (object) (array) $record;
        foreach (array('relationshipid', 'cohortid', 'roleid') as $required) {
            if (empty($record->{$required})) {
                throw new coding_exception("create_relationship_cohort requires {$required}.");
            }
        }
        if (!isset($record->allowdupsingroups)) {
            $record->allowdupsingroups = 0;
        }
        if (!isset($record->uniformdistribution)) {
            $record->uniformdistribution = 0;
        }

        $id = relationship_add_cohort($record);
        return $DB->get_record('relationship_cohorts', array('id' => $id), '*', MUST_EXIST);
    }

    /**
     * Create a group inside an existing relationship.
     *
     * Required: relationshipid, name.
     *
     * @param array|stdClass $record
     * @return stdClass the inserted relationship_groups row
     */
    public function create_relationship_group($record) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/relationship/lib.php');

        $record = (object) (array) $record;
        if (empty($record->relationshipid)) {
            throw new coding_exception('create_relationship_group requires a relationshipid.');
        }
        if (empty($record->name)) {
            throw new coding_exception('create_relationship_group requires a name.');
        }
        if (!isset($record->userlimit)) {
            $record->userlimit = 0;
        }
        if (!isset($record->uniformdistribution)) {
            $record->uniformdistribution = 0;
        }

        $id = relationship_add_group($record);
        return $DB->get_record('relationship_groups', array('id' => $id), '*', MUST_EXIST);
    }

    /**
     * Add a user as a member of a relationship group, going through the
     * canonical API so the cohort/group/user tuple is validated and the
     * member_added event fires.
     *
     * Required: relationshipgroupid, relationshipcohortid, userid.
     *
     * @param array|stdClass $record
     * @return stdClass the inserted relationship_members row
     */
    public function create_relationship_member($record) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/relationship/lib.php');

        $record = (object) (array) $record;
        foreach (array('relationshipgroupid', 'relationshipcohortid', 'userid') as $required) {
            if (empty($record->{$required})) {
                throw new coding_exception("create_relationship_member requires {$required}.");
            }
        }

        $id = relationship_add_member($record->relationshipgroupid, $record->relationshipcohortid, $record->userid);
        if (!$id) {
            throw new coding_exception('relationship_add_member returned false; tuple may already exist.');
        }
        return $DB->get_record('relationship_members', array('id' => $id), '*', MUST_EXIST);
    }
}
