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
 * Edit Relationship form definition
 *
 * @package local_relationship
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_relationship\form;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/lib/formslib.php');

class edit_relationship extends \moodleform {

    public function definition() {

        $mform = $this->_form;
        $editoroptions = $this->_customdata['editoroptions'];
        $relationship = $this->_customdata['data'];

        $mform->addElement('text', 'name', get_string('name', 'local_relationship'), 'maxlength="254" size="50"');
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->setType('name', PARAM_TEXT);

        $mform->addElement('editor', 'description_editor', get_string('description', 'local_relationship'), null, $editoroptions);
        $mform->setType('description_editor', PARAM_RAW);

        $mform->addElement('tags', 'tags', get_string('tags'), array('itemtype' => 'relationship', 'component' => 'relationship'));
        $mform->setType('tags', PARAM_TEXT);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'contextid');
        $mform->setType('contextid', PARAM_INT);


        $mform->addElement('hidden', 'idnumber');
        $mform->setType('idnumber', PARAM_TEXT);

        $mform->addElement('hidden', 'component');
        $mform->setType('component', PARAM_TEXT);

        $mform->addElement('hidden', 'timecreated');
        $mform->setType('timecreated', PARAM_INT);

        $this->add_action_buttons();

        $this->set_data($relationship);
    }

    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);

        $name = trim($data['name']);
        if (empty($name)) {
            $errors['name'] = get_string('no_name', 'local_relationship');
        } else {
            $params = array('name' => addslashes($name), 'contextid' => $data['contextid']);
            if ($data['id']) {
                $where = "id != :id AND";
                $params['id'] = $data['id'];
            } else {
                $where = '';
            }
            $sql = "SELECT id FROM {relationship} WHERE {$where} name = :name AND contextid = :contextid";
            if ($DB->record_exists_sql($sql, $params)) {
                $errors['name'] = get_string('relationship_already_exists', 'local_relationship');
            }
        }

        return $errors;
    }

}
