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
 * Mass assign processor for relationship
 *
 * @package local_relationship
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_relationship;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/cohort/lib.php');

class mass_assign_processor {

    private $relationshipcohort;
    private $relationshipgroup;
    private $context;
    private $authtype;
    private $registerpendencies;
    private $allowallusers;

    public function __construct($relationshipcohortid, $relationshipgroupid, $registerpendencies=false, $allowallusers=false, $authtype='') {
        global $DB;

        $this->relationshipcohort = relationship_get_cohort($relationshipcohortid);
        if ($this->relationshipcohort->uniformdistribution || !isset($this->relationshipcohort->cohort) || !empty($this->relationshipcohort->cohort->component)) {
            throw new \moodle_exception('cantmassassign', 'local_relationship');
        }

        $this->relationshipgroup = $DB->get_record('relationship_groups', array('id' => $relationshipgroupid), '*', MUST_EXIST);
        if($this->relationshipgroup->uniformdistribution) {
            throw new \moodle_exception('cantmassassign', 'local_relationship');
        }

        $relationship = $DB->get_record('relationship', array('id' => $this->relationshipgroup->relationshipid), '*', MUST_EXIST);
        $this->registerpendencies = $registerpendencies;
        $this->allowallusers = $allowallusers;

        $this->context = \context::instance_by_id($relationship->contextid, MUST_EXIST);

        if (empty($authtype)) {
            $this->authtype = get_config('local_relationship', 'authtype');
        } else {
            $this->authtype = $authtype;
        }

        if (!is_enabled_auth($this->authtype)) {
            throw new \moodle_exception('authnotenabled', 'local_relationship', '', $this->authtype);
        }
    }

    /**
     * Process all values from $searchvalue and if valid add in relationship
     *
     * @param string $searchvalues
     * @return local_relationship_progress_tracker $tracker
     * @throws coding_exception
     */
    public function process_data($searchvalues) {
        $tracker = new progress_tracker('cpf');

        if (is_string($searchvalues)) {
            preg_match_all('|([\w.-]+)|', $searchvalues, $matches);
            $values = $matches[1];
        } else {
            $values = $searchvalues;
        }

        foreach ($values as $value) {
            $tracker->start_new_row();
            $tracker->total++;
            $tracker->track('cpf', $value, 'normal');

            $resultuser = $this->process_user($value);

            if (is_object($resultuser->moodleuser)) {
                $moodleuser = $resultuser->moodleuser;
                $tracker->track('username', $moodleuser->username, 'normal');
                $tracker->track('name', fullname($moodleuser));

                $resultrelationship = $this->process_relationship($moodleuser->id);
                $summary = $resultrelationship->summary;
                $result = array_merge($resultuser->status, $resultrelationship->status);
            } else {
                $summary = $resultuser->summary;
                $result = $resultuser->status;
            }

            foreach ($result as $itemstatus) {
                $tracker->track('status', $itemstatus->desc, $itemstatus->level);
            }
            $tracker->{$summary}++;
        }

        return $tracker;
    }

    /**
     * Find user by $value and if not found try to create user
     *
     * @param string $value
     * @return object
     * @throws coding_exception
     */
    public function process_user($value) {
        $result = (object) [
            'summary' => '',
            'status' => []
        ];

        $cpf = $this->validate_cpf($value);
        if ($cpf === false) {
            $result->summary = 'invalid';
            $result->status[] = (object) [
                'desc' => get_string('invalidcpf', 'local_relationship'),
                'level' => 'error'
            ];
        } else {
            $moodleuser = $this->find_users('cpf', $cpf);
            if (count($moodleuser) > 1) {
                $result->summary = 'invalid';
                $result->status[] = (object) [
                    'desc' => get_string('morethanoneusermoodle', 'local_relationship'),
                    'level' => 'error'
                ];
            } else if (count($moodleuser) == 1) {
                $result->moodleuser = reset($moodleuser);
            } else {
                if (self::search_sccp()) {
                    $pessoas = \local_ufsc\pessoa::by_key('cpf', $cpf);
                    if (count($pessoas) > 1) {
                        $result->summary = 'invalid';
                        $result->status[] = (object) [
                            'desc' => get_string('morethanoneusersccp', 'local_relationship'),
                            'level' => 'error'
                        ];
                    } else if (count($pessoas) == 1) {
                        if ($this->check_userlimit()) {
                            $result->summary = 'exceedslimit';
                            $result->status[] = (object) [
                                'desc' => get_string('exceedslimit', 'local_relationship'),
                                'level' => 'error'
                            ];
                        } else {
                            $pessoa = reset($pessoas);
                            $moodleuser = $this->find_users('idnumber', $pessoa->idpessoa);
                            if (count($moodleuser) > 1) {
                                $result->summary = 'invalid';
                                $result->status[] = (object) [
                                    'desc' => get_string('morethanoneusermoodle', 'local_relationship'),
                                    'level' => 'error'
                                ];
                            } else if (count($moodleuser) == 1) {
                                $result->moodleuser = reset($moodleuser);
                            } else {
                                if (empty($pessoa->email)) {
                                    $result->summary = 'invalid';
                                    $result->status[] = (object) [
                                        'desc' => get_string('emptyemailsccp', 'local_relationship'),
                                        'level' => 'error'
                                    ];
                                } else {
                                    $moodleuser = $this->create_user($pessoa, $this->authtype);
                                    if (is_object($moodleuser)) {
                                        $result->moodleuser = $moodleuser;
                                        $result->status[] = (object) [
                                            'desc' => get_string('addeduser', 'local_relationship'),
                                            'level' => 'success'
                                        ];
                                    } else {
                                        $result->summary = 'invalid';
                                        $result->status[] = (object) [
                                            'desc' => get_string('errorcreatinguser', 'local_relationship', $cpf),
                                            'level' => 'error'
                                        ];
                                    }
                                }
                            }
                        }
                    } else {
                        $result->summary = 'invalid';
                        $result->status[] = (object) [
                            'desc' => get_string('notinsccp', 'local_relationship'),
                            'level' => 'info'
                        ];
                        if ($this->registerpendencies) {
                            $resultregpendency = $this->register_pendency($cpf);
                            $result->summary = $resultregpendency->summary;
                            $result->status = array_merge($result->status, $resultregpendency->status);
                        }
                    }
                } else {
                    $result->summary = 'invalid';
                    $result->status[] = (object) [
                        'desc' => get_string('notuserinmoodle', 'local_relationship'),
                        'level' => 'error'
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Process the relationship data
     *
     * @param int $moodleuserid
     * @return object
     * @throws coding_exception
     */
    public function process_relationship($moodleuserid) {
        global $DB;
        $result = (object) [
            'summary' => '',
            'status' => []
        ];

        if ($DB->record_exists('relationship_members', array('relationshipgroupid' => $this->relationshipgroup->id, 'userid' => $moodleuserid))) {
            $result->summary = 'alreadyingroup';
            $result->status[] = (object) [
                'desc' => get_string('alreadyingroup', 'local_relationship'),
                'level' => 'info'
            ];
        } else if (!$this->relationshipcohort->allowdupsingroups) {
            $sql = "SELECT rg.name
                      FROM {relationship_members} rm
                      JOIN {relationship_groups} rg
                           ON (rm.relationshipgroupid = rg.id)
                     WHERE rg.relationshipid = :relationshipid
                           AND rm.userid = :userid
                           AND rm.relationshipgroupid != :relationshipgroupid";
            $groupsname = $DB->get_fieldset_sql($sql, array('relationshipid' => $this->relationshipcohort->relationshipid, 'userid' => $moodleuserid, 'relationshipgroupid' => $this->relationshipgroup->id));
            if (!empty($groupsname)) {
                $strgroupsname = implode('; ', $groupsname);
                $result->summary = 'alreadyrelationshipmember';
                $result->status[] = (object) [
                    'desc' => get_string('alreadyrelationshipmemberdetail', 'local_relationship', $strgroupsname),
                    'level' => 'info'
                ];
            }
        }

        if (empty($result->summary)) {
            if ($this->check_userlimit()) {
                $result->summary = 'exceedslimit';
                $result->status[] = (object) [
                    'desc' => get_string('exceedslimit', 'local_relationship'),
                    'level' => 'error'
                ];
            } else {
                cohort_add_member($this->relationshipcohort->cohortid, $moodleuserid);
                if (relationship_add_member($this->relationshipgroup->id, $this->relationshipcohort->id, $moodleuserid) === false) {
                    $result->summary = 'erroraddrelationshipmember';
                    $result->status[] = (object) [
                        'desc' => get_string('erroraddrelationshipmember', 'local_relationship'),
                        'level' => 'error'
                    ];
                } else {
                    $result->summary = 'assigneduser';
                    $result->status[] = (object) [
                        'desc' => get_string('assigneduser', 'local_relationship'),
                        'level' => 'success'
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * SCCP search enabled
     *
     * @return bool
     * @throws coding_exception
     */
    public static function search_sccp() {
        if ($searchsccp = get_config('local_relationship', 'searchsccp')) {
            $plugins = \core_component::get_plugin_list('local');
            if (!isset($plugins['ufsc'])) {
                throw new moodle_exception('localufscnotinstalled', 'local_relationship');
            }
        }

        return $searchsccp;
    }

    /**
     * Find Moodle users given a $searchfield and $searchvalue
     *
     * @param String $searchfield ('cpf', 'idnumber')
     * @param String $searchvalue
     * @return array moodle user
     */
    private function find_users($searchfield, $searchvalue) {
        global $DB;

        $moodlefields = 'id, username, email, ' . implode(',', get_all_user_name_fields());

        switch ($searchfield) {
            case 'cpf':
                $moodleusers = $DB->get_records('user', array('username' => $searchvalue), '', $moodlefields);
                break;
            case 'idnumber':
                $moodleusers = $DB->get_records('user', array('idnumber' => $searchvalue), '', $moodlefields);
                break;
            default:
                $moodleusers = array();
        }

        return $moodleusers;
    }

    /**
     * Create Moodle user
     *
     * @param array $pessoa
     * @param string $authtype
     * @return object moodle user
     * @throws coding_exception
     */
    public function create_user($pessoa, $authtype) {
        global $CFG, $DB;
        $user = new \stdClass();
        foreach (get_all_user_name_fields() AS $name) {
            $user->$name = '';
        }
        $user->username  = isset($pessoa->cpf) && !empty($pessoa->cpf) ? $pessoa->cpf : core_text::strtolower($pessoa->idpessoa);
        $user->idnumber  = $pessoa->idpessoa;
        $user->firstname = $pessoa->firstname;
        $user->lastname  = $pessoa->lastname;
        $user->email     = $pessoa->email;
        $user->confirmed  = 1;
        $user->auth       = $authtype;
        $user->mnethostid = $CFG->mnet_localhost_id;
        try {
            $user->id = user_create_user($user, false);
            if (isset($pessoa->cpf) && !empty($pessoa->cpf)) {
                if ($cpffield = $DB->get_record('user_info_field', array('shortname' => 'cpf'))) {
                    $info = new \stdClass();
                    $info->userid = $user->id;
                    $info->fieldid = $cpffield->id;
                    $info->data = $pessoa->cpf;
                    $info->dataformat = 0;
                    $DB->insert_record('user_info_data', $info);
                }
            }
            return $user;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Add relationship pendency
     *
     * @param $value ('cpf')
     * @return object
     * @throws coding_exception
     */
    public function register_pendency($value) {
        global $DB;
        $result = (object) [
            'summary' => '',
            'status' => []
        ];
        $params = array('relationshipcohortid' => $this->relationshipcohort->id, 'relationshipgroupid' => $this->relationshipgroup->id, 'cpf' => $value, 'allowallusers' => $this->allowallusers);
        if ($DB->record_exists('relationship_pendencies', $params)) {
            $result->summary = 'alreadyregisteredaspending';
            $result->status[] = (object) [
                'desc' => get_string('alreadyregisteredaspending', 'local_relationship'),
                'level' => 'info'
            ];
        } else if (!$this->relationshipcohort->allowdupsingroups) {
            $sql = "SELECT rg.name
                      FROM {relationship_pendencies} rp
                      JOIN {relationship_groups} rg ON (rg.id = rp.relationshipgroupid)
                     WHERE rg.relationshipid = :relationshipid
                           AND rp.cpf = :cpf
                           AND rp.relationshipgroupid != :relationshipgroupid";
            $groupsname = $DB->get_fieldset_sql($sql, array('relationshipid' => $this->relationshipcohort->relationshipid, 'cpf' => $value, 'relationshipgroupid' => $this->relationshipgroup->id));
            if (!empty($groupsname)) {
                $strgroupsname = implode('; ', $groupsname);
                $result->summary = 'alreadyregisteredaspending';
                $result->status[] = (object) [
                    'desc' => get_string('alreadyregisteredaspendingdetail', 'local_relationship', $strgroupsname),
                    'level' => 'info'
                ];
            }
        }

        if (empty($result->summary)) {
            if ($this->check_userlimit()) {
                $result->summary = 'exceedslimit';
                $result->status[] = (object) [
                    'desc' => get_string('exceedslimit', 'local_relationship'),
                    'level' => 'error'
                ];
            } else {
                $data = (object)$params;
                $data->timecreated = time();
                $DB->insert_record('relationship_pendencies', $data);
                $result->summary = 'registeredaspending';
                $result->status[] = (object) [
                    'desc' => get_string('registeredaspending', 'local_relationship'),
                    'level' => 'success'
                ];
            }
        }

        return $result;
    }

    /**
     * Validate CPF
     *
     * @param string $value
     * @return string|bool cpf numbers or false if not a valid cpf
     */
    public function validate_cpf($value) {
        $cpf = str_pad(preg_replace('[\D]', '', $value), 11, '0', STR_PAD_LEFT);

        if (strlen($cpf) != 11 || strlen(count_chars($cpf, 3)) == 1) {
            return false;
        } else {
            for ($t = 9; $t < 11; $t++) {
                for ($d = 0, $c = 0; $c < $t; $c++) {
                    $d += $cpf{$c} * (($t + 1) - $c);
                }
                $d = ((10 * $d) % 11) % 10;
                if ($cpf{$c} != $d) {
                    return false;
                }
            }
            return $cpf;
        }
    }

    /**
     * Check if relationshipgroup user limit is exceeded
     *
     * @return object
     */
    public function check_userlimit() {
        global $DB;
        $exceeds = false;

        if ($this->relationshipgroup->userlimit && !$this->allowallusers && $this->relationshipgroup->userlimit <= $DB->count_records('relationship_members', array('relationshipcohortid' => $this->relationshipcohort->id, 'relationshipgroupid' => $this->relationshipgroup->id))) {
            $exceeds = true;
        }

        return $exceeds;
    }

}
