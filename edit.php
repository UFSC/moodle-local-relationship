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
 * Edit Relationship page
 *
 * @package local_relationship
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/filelib.php');
require($CFG->dirroot.'/local/relationship/lib.php');
require_once($CFG->dirroot.'/local/relationship/locallib.php');

require_login();

$relationshipid = optional_param('relationshipid', 0, PARAM_INT);
if ($relationshipid) {
    $relationship = relationship_get_relationship($relationshipid);
    $context = context::instance_by_id($relationship->contextid, MUST_EXIST);
} else {
    $contextid = optional_param('contextid', 0, PARAM_INT);
    $context = context::instance_by_id($contextid, MUST_EXIST);
    $relationship = new stdClass();
    $relationship->id = 0;
    $relationship->contextid = $context->id;
    $relationship->name = '';
    $relationship->description = '';
    $relationship->tags = array();
}

require_capability('local/relationship:manage', $context);
if (!empty($relationship->component)) {
    print_error('cantedit', 'local_relationship');
}

$baseurl = new moodle_url('/local/relationship/edit.php', array('relationshipid' => $relationship->id, 'contextid' => $context->id));
$returnurl = new moodle_url('/local/relationship/index.php', array('contextid' => $context->id));

if (optional_param('confirmdelete', 0, PARAM_BOOL) && confirm_sesskey() && $relationship->id) {
    $res = relationship_delete_relationship($relationship);
    if($res === -1) {
        print_string('has_cohorts', 'local_relationship');
   }
    redirect($returnurl);
}

if (optional_param('delete', 0, PARAM_BOOL) && $relationship->id) {
    relationship_set_header($context, $baseurl, $relationship);
    relationship_set_title($relationship, 'deleterelationship');
    $yesurl = new moodle_url('/local/relationship/edit.php', array('relationshipid' => $relationship->id, 'confirmdelete' => 1, 'sesskey' => sesskey()));
    $message = get_string('confirmdelete', 'local_relationship', format_string($relationship->name));
    echo $OUTPUT->confirm($message, $yesurl, $returnurl);
    echo $OUTPUT->footer();
    die;
}

relationship_set_header($context, $baseurl, $relationship);

$editoroptions = array('maxfiles' => 0, 'context' => $context);

$relationship_editor = file_prepare_standard_editor($relationship, 'description', $editoroptions, $context);
$editform = new \local_relationship\form\edit_relationship(null, array('editoroptions' => $editoroptions, 'data' => $relationship_editor));

if ($editform->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $editform->get_data()) {
    $data = file_postupdate_standard_editor($data, 'description', $editoroptions, $context);
    if ($data->id) {
        relationship_update_relationship($data);
    } else {
        relationship_add_relationship($data);
    }
    redirect($returnurl);
}

$action = $relationship_editor->id ? 'editrelationship' : 'addrelationship';
relationship_set_title($relationship_editor, $action);
echo $editform->display();
echo $OUTPUT->footer();
