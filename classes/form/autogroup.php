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
 * Relationship's Autogroup form definition
 *
 * @package local_relationship
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_relationship\form;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/lib/formslib.php');

/**
 * Auto group form class
 *
 * @package enrol_relationship
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class autogroup extends \moodleform {

    /**
     * Form Definition
     */
    function definition() {
        global $DB;

        $mform =& $this->_form;

        $relationshipid = $this->_customdata['relationshipid'];

        $mform->addElement('text', 'namingscheme', get_string('namingscheme', 'local_relationship'));
        $mform->addHelpButton('namingscheme', 'namingscheme', 'local_relationship');
        $mform->addRule('namingscheme', get_string('required'), 'required', null, 'client');
        $mform->setType('namingscheme', PARAM_TEXT);
        $mform->setDefault('namingscheme', get_string('grouptemplate', 'group'));

        $mform->addElement('text', 'userlimit', get_string('userlimit', 'local_relationship'), 'maxlength="5" size="4"');
        $mform->setType('userlimit', PARAM_INT);
        $mform->addRule('userlimit', null, 'numeric', null, 'client');
        $mform->setDefault('userlimit', 0);
        $mform->addHelpButton('userlimit', 'userlimit', 'local_relationship');

        $mform->addElement('text', 'number', get_string('numbergroups', 'local_relationship'), 'maxlength="4" size="4"');
        $mform->setType('number', PARAM_INT);
        $mform->addRule('number', null, 'numeric', null, 'client');
        $mform->setDefault('number', 0);
        $mform->disabledIf('number', 'relationshipcohortid', 'gt', 0);

        $sql = "SELECT rc.id, rc.cohortid, rc.roleid, ch.name
                  FROM {relationship_cohorts} rc
                  JOIN {cohort} ch ON (ch.id = rc.cohortid)
                 WHERE rc.relationshipid = :relationshipid
              ORDER BY ch.name";
        $rcs = $DB->get_records_sql($sql, array('relationshipid' => $relationshipid));
        if ($rcs) {
            $options = array(0 => get_string('none'));
            $r = get_string('role');
            foreach ($rcs AS $rc) {
                $role = $DB->get_record('role', array('id' => $rc->roleid));
                $role_name = role_get_name($role);
                $options[$rc->id] = "{$rc->name}  ({$r}: {$role_name})";
            }
            $mform->addElement('select', 'relationshipcohortid', get_string('fromcohort', 'local_relationship'), $options);
            $mform->setDefault('relationshipcohortid', '0');
            $mform->addHelpButton('relationshipcohortid', 'fromcohort', 'local_relationship');
            $mform->disabledIf('relationshipcohortid', 'number', 'gt', 0);
        } else {
            $mform->addElement('hidden', 'relationshipcohortid');
            $mform->setType('relationshipcohortid', PARAM_INT);
            $mform->setConstant('relationshipcohortid', '0');
        }

        $mform->addElement('hidden', 'relationshipid');
        $mform->setType('relationshipid', PARAM_INT);

        $buttonarray = array();
        $buttonarray[] = & $mform->createElement('submit', 'preview', get_string('preview', 'local_relationship'));
        $buttonarray[] = & $mform->createElement('submit', 'submitbutton', get_string('creategroups', 'local_relationship'));
        $buttonarray[] = & $mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');
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

        return $errors;
    }
}
