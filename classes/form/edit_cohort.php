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
 * Relationship's Cohort form definition
 *
 * @package local_relationship
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_relationship\form;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/lib/formslib.php');

class edit_cohort extends \moodleform {

    public function definition() {

        $mform = $this->_form;
        $relationshipcohort = $this->_customdata['data'];

        $cohorts = relationship_get_cohort_options($relationshipcohort->relationshipid);
        $relationshipcohorts = relationship_get_cohorts($relationshipcohort->relationshipid);
        foreach($relationshipcohorts AS $rc) {
            unset($cohorts[$rc->cohortid]);
        }

        $mform->addElement('select', 'cohortid', get_string('cohort', 'cohort'), $cohorts);

        $roles = relationship_get_role_options();
        $mform->addElement('select', 'roleid', get_string('role'), $roles);

        if($relationshipcohort->id) {
            $mform->freeze('roleid');
            $mform->freeze('cohortid');
        }

        $mform->addElement('selectyesno', 'allowdupsingroups', get_string('allowdupsingroups', 'local_relationship'));
        $mform->addHelpButton('allowdupsingroups', 'allowdupsingroups', 'local_relationship');

        $mform->addElement('selectyesno', 'uniformdistribution', get_string('uniformdistribute', 'local_relationship'));
        $mform->addHelpButton('uniformdistribution', 'uniformdistribute', 'local_relationship');

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'relationshipid');
        $mform->setType('relationshipid', PARAM_INT);

        $this->add_action_buttons();

        $this->set_data($relationshipcohort);
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        return $errors;
    }

}

