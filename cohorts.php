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
 * Relationship's Cohorts listing page
 *
 * @package local_relationship
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../config.php');
require($CFG->dirroot.'/local/relationship/lib.php');
require_once($CFG->dirroot.'/local/relationship/locallib.php');

require_login();

$relationshipid = required_param('relationshipid', PARAM_INT);
$relationship = $DB->get_record('relationship', array('id' => $relationshipid), '*', MUST_EXIST);
$context = context::instance_by_id($relationship->contextid, MUST_EXIST);

require_capability('local/relationship:view', $context);
$manager = has_capability('local/relationship:manage', $context);
$editable = $manager && empty($relationship->component);

$baseurl = new moodle_url('/local/relationship/cohorts.php', array('relationshipid' => $relationship->id));
$returnurl = new moodle_url('/local/relationship/index.php', array('contextid' => $context->id));

relationship_set_header($context, $baseurl, $relationship, 'cohorts');
relationship_set_title($relationship, 'cohorts');

$relationshipcohorts = relationship_get_cohorts($relationshipid);
$data = array();
foreach ($relationshipcohorts as $rch) {
    $line = array();

    $line[] = $rch->cohort ? $rch->cohort->name : '?';
    $line[] = $rch->cohort ? $DB->count_records('cohort_members', array('cohortid'=>$rch->cohort->id)) : '?';
    $line[] = $rch->role_name;
    $line[] = $rch->allowdupsingroups ? get_string('yes') : get_string('no');
    $line[] = $rch->uniformdistribution ? get_string('yes') : get_string('no');

    if ($editable) {
        $buttons = array();
        $buttons[] = html_writer::link(new moodle_url('/local/relationship/edit_cohort.php', array('relationshipcohortid' => $rch->id, 'delete' => 1)),
                html_writer::span($OUTPUT->pix_icon('t/delete', get_string('delete'), 'moodle', ['class' => 'iconsmall'])));
        $buttons[] = html_writer::link(new moodle_url('/local/relationship/edit_cohort.php', array('relationshipcohortid' => $rch->id)),
                html_writer::span($OUTPUT->pix_icon('t/edit', get_string('edit'), 'moodle', ['class' => 'iconsmall'])));
        $line[] = implode(' ', $buttons);
    }

    $data[] = $line;
}
$table = new html_table();
$table->head = array(
        get_string('cohort', 'cohort'),
        get_string('size'),
        get_string('role'),
        get_string('allowdupsingroups', 'local_relationship').$OUTPUT->help_icon('allowdupsingroups', 'local_relationship'),
        get_string('uniformdistribute', 'local_relationship').$OUTPUT->help_icon('uniformdistribute', 'local_relationship'),
        get_string('edit')
);
$table->colclasses = array(
        'leftalign',
        'leftalign',
        'leftalign',
        'centeralign',
        'leftalign',
        'leftalign'
);

$table->id = 'relationships';
$table->attributes['class'] = 'admintable generaltable';
$table->data = $data;

echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
echo html_writer::table($table);

if ($editable) {
    $cohorts = relationship_get_cohort_options($relationshipid);
    foreach ($relationshipcohorts AS $rc) {
        unset($cohorts[$rc->cohortid]);
    }
    if (empty($cohorts)) {
        echo $OUTPUT->heading(get_string('nocohorts', 'local_relationship'), 4);
    } else {
        $add = new single_button(new moodle_url('/local/relationship/edit_cohort.php',
                array('relationshipid' => $relationshipid)), get_string('add'));
        echo $OUTPUT->render($add);
    }
} else if ($manager) {
    echo $OUTPUT->heading(get_string('noeditable', 'local_relationship', 4));
}
echo $OUTPUT->box_end();

echo $OUTPUT->footer();
