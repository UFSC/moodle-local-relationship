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
 * Edit Relationship's Group form definition
 *
 * @package local_relationship
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_relationship\form;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/lib/formslib.php');

class edit_group extends \moodleform {

    /**
     * Define the relationshipgroup edit form
     */
    public function definition() {

        $mform = $this->_form;
        $relationshipgroup = $this->_customdata['data'];

        $mform->addElement('text', 'name', get_string('groupname', 'local_relationship'), 'maxlength="254" size="50"');
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->setType('name', PARAM_NOTAGS);

        $mform->addElement('text', 'userlimit', get_string('userlimit', 'local_relationship'), 'maxlength="5" size="4"');
        $mform->setType('userlimit', PARAM_INT);
        $mform->addRule('userlimit', null, 'numeric', null, 'client');
        $mform->setDefault('userlimit', 0);
        $mform->addHelpButton('userlimit', 'userlimit', 'local_relationship');

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'relationshipid');
        $mform->setType('relationshipid', PARAM_INT);
        $mform->addElement('hidden', 'uniformdistribution');
        $mform->setType('uniformdistribution', PARAM_INT);
        $mform->addElement('hidden', 'timecreated');
        $mform->setType('timecreated', PARAM_INT);

        $this->add_action_buttons();

        $this->set_data($relationshipgroup);
    }

    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);

        if ($DB->record_exists_select('relationship_groups',
                "relationshipid = :relationshipid AND name = :name AND id != :id",
                array('relationshipid' => $data['relationshipid'], 'name' => $data['name'], 'id' => $data['id']))
        ) {
            $errors['name'] = get_string('group_already_exists', 'local_relationship');
        } else {
            $groups = relationship_get_other_courses_group_names($data['relationshipid']);
            if (isset($groups[$data['name']])) {
                $c = reset($groups[$data['name']]);
                $errors['name'] = get_string('course_group_already_exists', 'local_relationship', $c->fullname);
            }
        }

        return $errors;
    }

}

