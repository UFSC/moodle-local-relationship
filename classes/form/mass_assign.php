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
 * Mass Assign form definition
 *
 * @package local_relationship
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_relationship\form;
defined('MOODLE_INTERNAL') || die();

use local_relationship\mass_assign_processor;
require_once($CFG->dirroot.'/lib/formslib.php');

class mass_assign extends \moodleform {

    /**
     * Form Definition
     */
    function definition () {

        $mform = $this->_form;
        $relationshipgroup = $this->_customdata['data'];

        $mform->addElement('static', 'searchfield', get_string('searchfield', 'local_relationship'),
            'CPF');

        $mform->addElement('textarea', 'searchvalues', get_string('searchvalues', 'local_relationship'), 'rows="10" cols="60"');
        $mform->setType('searchvalues', PARAM_TEXT);
        $mform->addHelpButton('searchvalues', 'searchvalues', 'local_relationship');

        $rcs = relationship_get_cohort_by_roleshortname($relationshipgroup->relationshipid, 'student');

        $cohortrole = get_string('none');
        if($rcs) {
            $r = get_string('role');
            $cohortrole = "{$rcs->name}  ({$r}: $rcs->rolename)";
        }

        $mform->addElement('static', 'massassigncohortid', get_string('massassigncohortid', 'local_relationship'),
            $cohortrole);

        $mform->addHelpButton('massassigncohortid', 'massassigncohortid', 'local_relationship');

        $mform->addElement('hidden', 'relationshipgroupid', $relationshipgroup->id);
        $mform->setType('relationshipgroupid', PARAM_INT);
        $mform->addElement('hidden', 'relationshipid');
        $mform->setType('relationshipid', PARAM_INT);

        if ($relationshipgroup->userlimit) {
            $mform->addElement('checkbox', 'allowallusers', get_string('allowallusers', 'local_relationship'), false);
            $mform->addHelpButton('allowallusers', 'allowallusers', 'local_relationship');
        }

        if(mass_assign_processor::search_sccp()) {
            $mform->addElement('checkbox', 'registerpendencies', get_string('registerpendencies', 'local_relationship'), false);
            $mform->addHelpButton('registerpendencies', 'registerpendencies', 'local_relationship');
        }

        $this->add_action_buttons(true, get_string('massassignusers', 'local_relationship'));

        $this->set_data($relationshipgroup);
    }

    /**
     * Performs validation of the form information
     *
     * @param array $data
     * @param array $files
     * @return array $errors An array of $errors
     */
    function validation($data, $files) {
        $errors = parent::validation($data, $files);

        preg_match_all('|([\d.-]+)|', $data['searchvalues'], $matches);
        if (empty($matches[1])) {
            $errors['searchvalues'] = get_string('invalidsearchvalues', 'local_relationship');
        }

        return $errors;
    }
}
