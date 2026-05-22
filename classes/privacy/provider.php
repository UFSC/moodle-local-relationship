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
 * Privacy Subsystem implementation for local_relationship.
 *
 * @package    local_relationship
 * @copyright  2026 UFSC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_relationship\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_relationship.
 *
 * The plugin stores one personal datum: which relationship groups a user
 * belongs to (and through which cohort/role mapping). The remaining tables
 * (relationship, relationship_cohorts, relationship_groups) only hold
 * structural data with no userid column, so they are not declared here.
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider {

    /**
     * Declare the tables that store user data for this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection) {
        $collection->add_database_table('relationship_members', array(
            'relationshipgroupid'  => 'privacy:metadata:relationship_members:relationshipgroupid',
            'relationshipcohortid' => 'privacy:metadata:relationship_members:relationshipcohortid',
            'userid'               => 'privacy:metadata:relationship_members:userid',
            'timeadded'            => 'privacy:metadata:relationship_members:timeadded',
        ), 'privacy:metadata:relationship_members');
        return $collection;
    }

    /**
     * List the contexts in which the given user has data stored by this plugin.
     *
     * Relationships live in CONTEXT_COURSECAT, so this is the category context
     * of every relationship the user is a member of.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid($userid) {
        $contextlist = new contextlist();

        $sql = "SELECT DISTINCT r.contextid
                  FROM {relationship} r
                  JOIN {relationship_groups} rg ON (rg.relationshipid = r.id)
                  JOIN {relationship_members} rm ON (rm.relationshipgroupid = rg.id)
                 WHERE rm.userid = :userid";
        $contextlist->add_from_sql($sql, array('userid' => $userid));

        return $contextlist;
    }

    /**
     * Export each membership the user holds in the approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();
        $userid = $user->id;
        $contextids = $contextlist->get_contextids();
        list($incontextids, $contextparams) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx');

        $sql = "SELECT rm.id              AS memberid,
                       r.id               AS relationshipid,
                       r.name             AS relationshipname,
                       r.contextid        AS contextid,
                       rg.name            AS groupname,
                       rc.roleid          AS roleid,
                       rm.timeadded       AS timeadded
                  FROM {relationship_members} rm
                  JOIN {relationship_groups} rg ON (rg.id = rm.relationshipgroupid)
                  JOIN {relationship_cohorts} rc ON (rc.id = rm.relationshipcohortid)
                  JOIN {relationship} r ON (r.id = rg.relationshipid)
                 WHERE rm.userid = :userid AND r.contextid {$incontextids}
              ORDER BY r.contextid, r.id, rg.id, rm.id";
        $params = array_merge(array('userid' => $userid), $contextparams);

        $bycontext = array();
        foreach ($DB->get_records_sql($sql, $params) as $row) {
            if (!isset($bycontext[$row->contextid])) {
                $bycontext[$row->contextid] = array();
            }
            $bycontext[$row->contextid][] = (object) array(
                'relationship' => $row->relationshipname,
                'group'        => $row->groupname,
                'roleid'       => $row->roleid,
                'timeadded'    => \core_privacy\local\request\transform::datetime($row->timeadded),
            );
        }

        foreach ($bycontext as $contextid => $entries) {
            $context = \context::instance_by_id($contextid);
            $data = (object) array('memberships' => $entries);
            writer::with_context($context)->export_data(
                    array(get_string('pluginname', 'local_relationship')),
                    $data);
        }
    }

    /**
     * Remove every relationship_members row tied to relationships in the given
     * context. Triggers the member_removed event for each.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $CFG;
        require_once($CFG->dirroot . '/local/relationship/lib.php');

        if ($context->contextlevel !== CONTEXT_COURSECAT) {
            return;
        }

        self::delete_memberships_in_context($context->id, null);
    }

    /**
     * Remove the given user's memberships in the approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $CFG;
        require_once($CFG->dirroot . '/local/relationship/lib.php');

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_COURSECAT) {
                continue;
            }
            self::delete_memberships_in_context($context->id, $user->id);
        }
    }

    /**
     * Delete relationship_members rows for relationships whose context matches,
     * optionally filtered by a single userid. Always goes through
     * relationship_remove_member so the cleanup event fires.
     *
     * @param int $contextid
     * @param int|null $userid
     */
    protected static function delete_memberships_in_context($contextid, $userid) {
        global $DB;

        $sql = "SELECT rm.relationshipgroupid, rm.relationshipcohortid, rm.userid
                  FROM {relationship_members} rm
                  JOIN {relationship_groups} rg ON (rg.id = rm.relationshipgroupid)
                  JOIN {relationship} r ON (r.id = rg.relationshipid)
                 WHERE r.contextid = :contextid";
        $params = array('contextid' => $contextid);
        if ($userid !== null) {
            $sql .= " AND rm.userid = :userid";
            $params['userid'] = $userid;
        }

        foreach ($DB->get_records_sql($sql, $params) as $row) {
            relationship_remove_member($row->relationshipgroupid, $row->relationshipcohortid, $row->userid);
        }
    }
}
