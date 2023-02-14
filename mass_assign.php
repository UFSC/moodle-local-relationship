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
 * Mass assign users to Relationship's Group page
 * Based on https://gitlab.setic.ufsc.br/moodle-ufsc/local-mass_enrol
 *
 * @package local_relationship
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../config.php');
require($CFG->dirroot.'/local/relationship/lib.php');
require_once($CFG->dirroot.'/local/relationship/locallib.php');

require_login();

$relationshipgroupid = required_param('relationshipgroupid', PARAM_INT);
$relationshipgroup = $DB->get_record('relationship_groups', array('id' => $relationshipgroupid), '*', MUST_EXIST);
$relationship = $DB->get_record('relationship', array('id' => $relationshipgroup->relationshipid), '*', MUST_EXIST);

$context = context::instance_by_id($relationship->contextid, MUST_EXIST);

require_capability('local/relationship:view', $context);

$canassign = has_capability('local/relationship:assign', $context);
$editable = $canassign && empty($relationship->component);
if (!$editable) {
    print_error('cantedit', 'local_relationship');
}

$relationshipcohort = relationship_get_cohort_by_roleshortname($relationship->id, 'student', MUST_EXIST);

// It is possible the cohort belongs to a component, then the component manipulates the cohort data.
// Therefore, the mass assign only works in cohort without component.
// When uniform distribution is enabled, users are uniformly distribute to any group with uniformdistribution = 1 in the relationship,
// so uniform distribution needs to be disabled to mass assign users in a specific group.
if(!empty($relationshipcohort->component) || $relationshipcohort->uniformdistribution || $relationshipgroup->uniformdistribution) {
    print_error('cantmassassign', 'local_relationship');
}

$action = optional_param('action', 'massassignusers', PARAM_TEXT);

$baseurl = new moodle_url('/local/relationship/mass_assign.php', array('relationshipgroupid' => $relationshipgroupid, 'action' => $action));
$returnurl = new moodle_url('/local/relationship/groups.php', array('relationshipid' => $relationship->id));

$searchsccp = \local_relationship\mass_assign_processor::search_sccp();
$tabs = ['massassignusers'];
if ($searchsccp) {
    $tabs[] = 'pendingmassassign';
}

relationship_set_header($context, $baseurl, $relationship, 'groups');

switch ($action) {
    case 'massassignusers':
        $form = new \local_relationship\form\mass_assign(null, array('data' => $relationshipgroup));
        if ($form->is_cancelled()) {
            redirect($returnurl);
        } else {
            relationship_set_title($relationship);
            relationship_print_tabs($baseurl, $tabs, $action);
            $helptext = \local_relationship\mass_assign_processor::search_sccp() ? 'massassignuserssccp' : 'massassignusers';
            $helpicon = $OUTPUT->help_icon($helptext, 'local_relationship');
            echo $OUTPUT->heading(get_string('massassignto', 'local_relationship', format_string($relationshipgroup->name)) . $helpicon, 4, 'main');
            if ($data = $form->get_data()) {
                $allowallusers = isset($data->allowallusers);
                $registerpendencies = isset($data->registerpendencies);
                $massassign = new \local_relationship\mass_assign_processor($relationshipcohort->id, $relationshipgroup->id, $registerpendencies, $allowallusers);
                $tracker = $massassign->process_data($data->searchvalues);

                $tracker->print_tracking();
                $tracker->print_summary();

                $buttons = array(
                    new single_button($returnurl, get_string('backtogroups', 'local_relationship')),
                    new single_button($baseurl, get_string('massassignusers', 'local_relationship'))
                );
                echo relationship_render_buttons($buttons);
            } else {
                $form->display();
            }
            echo $OUTPUT->footer();
        }
        break;
    case 'pendingmassassign':
        if (!$searchsccp) {
            $baseurl->param('action', 'massassignusers');
            redirect($baseurl);
        }
        $id = optional_param('id', 0, PARAM_INT);
        if ($id && optional_param('confirmdelete', 0, PARAM_BOOL) && confirm_sesskey()) {
            $DB->delete_records('relationship_pendencies', array('id'=>$id));
            redirect($baseurl);
        }

        relationship_set_title($relationship);
        relationship_print_tabs($baseurl, $tabs, $action);

        if ($id && optional_param('delete', 0, PARAM_BOOL)) {
            echo $OUTPUT->heading(get_string('delete', 'local_relationship'), 4, 'main');

            $cancelurl = clone($baseurl);
            $cancelurl->param('confirmdelete', 0);

            $baseurl->param('confirmdelete', 1);
            $baseurl->param('id', $id);
            $baseurl->param('sesskey', sesskey());
            $rec = $DB->get_record('relationship_pendencies', array('id'=>$id), '*', MUST_EXIST);

            $message = get_string('confirmdeletependency', 'local_relationship', $rec->cpf);
            echo $OUTPUT->confirm($message, $baseurl, $cancelurl);
        } else {
            $rcs = relationship_get_cohort_by_roleshortname($relationshipgroup->relationshipid, 'student');

            $cohortrole = get_string('none');
            if($rcs) {
                $r = get_string('role');
                $cohortrole = "{$rcs->name}  ({$r}: $rcs->rolename)";
            }

            echo $OUTPUT->heading(get_string('titlependingassign', 'local_relationship'), 4, 'main');
            echo $OUTPUT->heading(get_string('group', 'local_relationship', format_string($relationshipgroup->name)), 5, 'main');
            echo $OUTPUT->heading(get_string('cohort', 'local_relationship', format_string($cohortrole)), 5, 'main');

            $recs = $DB->get_records('relationship_pendencies', array('relationshipgroupid'=>$relationshipgroup->id, 'relationshipcohortid'=>$relationshipcohort->id), 'cpf');
            $table = new html_table();
            $table->head  = array('CPF', get_string('timecreated', 'local_relationship'));
            $table->data = array();
            foreach($recs AS $rec) {
                $line = array($rec->cpf, userdate($rec->timecreated));
                $baseurl->param('id', $rec->id);
                $baseurl->param('delete', 1);
                $line[] = html_writer::link($baseurl, html_writer::span($OUTPUT->pix_icon('t/delete', get_string('delete'), 'moodle', ['class' => 'iconsmall'])));
                $table->data[] = $line;
            }

            echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
            echo html_writer::table($table);
            echo $OUTPUT->box_end();
        }

        echo $OUTPUT->single_button($returnurl, get_string('backtogroups', 'local_relationship'));
        echo $OUTPUT->footer();

        break;
    default:
        $baseurl->param('action', 'massassignusers');
        redirect($baseurl);
}
