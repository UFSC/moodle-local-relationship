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
 * Event handler definition
 *
 * @package local_relationship
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/local/relationship/lib.php');

/**
 * Event handler for relationship local plugin.
 *
 * We try to keep everything in sync via listening to events,
 * it may fail sometimes, so we always do a full sync in cron too.
 */
class local_relationship_observer
{

    /**
     * Event processor - cohort removed.
     * @param \core\event\cohort_deleted $event
     * @return bool
     */
    public static function cohort_removed(\core\event\cohort_deleted $event) {
        global $DB;

        $relationshipcohorts = $DB->get_records('relationship_cohorts', array('cohortid'=>$event->objectid));
        if (!empty($relationshipcohorts)) {
            foreach ($relationshipcohorts as $relationshipcohort) {
                relationship_delete_cohort($relationshipcohort);
            }
        }

        return true;
    }

    /**
     * Event processor - cohort member added.
     * @param \core\event\cohort_member_added $event
     * @return bool
     */
    public static function member_added(\core\event\cohort_member_added $event)
    {
        global $DB;

        if ($rcs = $DB->get_records('relationship_cohorts', array('uniformdistribution' => 1, 'cohortid' => $event->objectid))) {
            $user = array($event->relateduserid);
            foreach ($rcs AS $rc) {
                relationship_uniformly_distribute_users($rc, $user);
            }
        }

        return true;
    }

    /**
     * Event processor - cohort member removed.
     * @param \core\event\cohort_member_removed $event
     * @return bool
     */
    public static function member_removed(\core\event\cohort_member_removed $event)
    {
        global $DB;

        $sql = "SELECT rm.relationshipgroupid, rm.relationshipcohortid, rm.userid
                  FROM {relationship_cohorts} rc
                  JOIN {relationship_groups} rg
                    ON (rg.relationshipid = rc.relationshipid)
                  JOIN {relationship_members} rm
                    ON (rm.relationshipgroupid = rg.id AND rm.relationshipcohortid = rc.id)
                 WHERE rc.cohortid = :cohortid
                   AND rm.userid = :userid";

        $params = array('cohortid' => $event->objectid, 'userid' => $event->relateduserid);
        $rs = $DB->get_records_sql($sql, $params);
        foreach ($rs AS $rec) {
            relationship_remove_member($rec->relationshipgroupid, $rec->relationshipcohortid, $rec->userid);
        }

        return true;
    }

    /**
     * Event processor - check relationship pendencies when user_loggedin event occurs.
     * @param \core\event\user_loggedin $event
     * @return bool
     */
    public static function user_loggedin(\core\event\user_loggedin $event) {
        global $DB;

        if (is_siteadmin($event->userid) || isguestuser($event->userid)) {
            return true;
        }

        if (\local_relationship\mass_assign_processor::search_sccp()) {
            $idnumber = $DB->get_field('user', 'idnumber', array('id'=>$event->userid));
            if (!empty($idnumber)) {
                $pessoas = \local_ufsc\pessoa::by_key('idpessoa', $idnumber);
                if(!empty($pessoas)) {
                    $pessoa = reset($pessoas);
                    if (!empty($pessoa->cpf)) {
                        $where = 'rp.cpf = :cpf';
                        $params['cpf'] = $pessoa->cpf;

                        $sql = "SELECT DISTINCT rp.id, rc.id AS relationshipcohortid, rg.id AS relationshipgroupid, rp.allowallusers
                                  FROM {relationship_pendencies} rp
                             LEFT JOIN {relationship_cohorts} rc ON (rc.id = rp.relationshipcohortid)
                             LEFT JOIN {relationship_groups} rg ON (rg.id = rp.relationshipgroupid)
                                 WHERE {$where}";
                        $recs = $DB->get_records_sql($sql, $params);
                        foreach ($recs AS $rec) {
                            if (!empty($rec->relationshipcohortid) && !empty($rec->relationshipgroupid)) {
                                $massassign = new \local_relationship\mass_assign_processor($rec->relationshipcohortid, $rec->relationshipgroupid, false, $rec->allowallusers);
                                $result = $massassign->process_relationship($event->userid);
                                if (in_array($result->summary,['assigneduser', 'alreadyingroup'])) {
                                    $DB->delete_records('relationship_pendencies', array('id' => $rec->id));
                                }
                            }
                        }
                    }
                }
            }
        }

        return true;
    }
}
