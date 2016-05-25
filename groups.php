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
 * Relationship's Groups listing page
 *
 * @package local_relationship
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../config.php');
require($CFG->dirroot.'/local/relationship/lib.php');
require_once($CFG->dirroot.'/local/relationship/locallib.php');
require_once($CFG->libdir.'/adminlib.php');

require_login();

$relationshipid = required_param('relationshipid', PARAM_INT);
$relationship = $DB->get_record('relationship', array('id' => $relationshipid), '*', MUST_EXIST);
$context = context::instance_by_id($relationship->contextid, MUST_EXIST);

require_capability('local/relationship:view', $context);
$manager = has_capability('local/relationship:manage', $context);
$canassign = has_capability('local/relationship:assign', $context);
$editable = $manager && empty($relationship->component);

$baseurl = new moodle_url('/local/relationship/groups.php', array('relationshipid' => $relationship->id));
$returnurl = new moodle_url('/local/relationship/index.php', array('contextid' => $context->id));

relationship_set_header($context, $baseurl, $relationship, 'groups');
relationship_set_title($relationship, 'groups');

$relationshipgroups = relationship_get_groups($relationshipid);

$data = array();
foreach ($relationshipgroups as $relationshipgroup) {
    $line = array();

    $line[] = format_string($relationshipgroup->name);
    $line[] = $relationshipgroup->size;
    $line[] = $relationshipgroup->userlimit;

    if ($relationshipgroup->uniformdistribution) {
        $status = get_string('yes');
        $uniformdistribution = 0;
        $text = get_string('disable', 'local_relationship');
    } else {
        $status = get_string('no');
        $uniformdistribution = 1;
        $text = get_string('enable', 'local_relationship');
    }
    if ($editable) {
        $url = new moodle_url('/local/relationship/edit_group.php', array('relationshipgroupid' => $relationshipgroup->id, 'uniformdistribution' => $uniformdistribution));
        $line[] = $status.' ('.html_writer::link($url, $text).')';
    } else {
        $line[] = $status;
    }

    $buttons = array();
    if ($editable) {
        $buttons[] = html_writer::link(new moodle_url('/local/relationship/edit_group.php', array('relationshipgroupid' => $relationshipgroup->id, 'delete' => 1)),
                html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('t/delete'), 'alt' => get_string('delete'), 'title' => get_string('delete'), 'class' => 'iconsmall')));
        $buttons[] = html_writer::link(new moodle_url('/local/relationship/edit_group.php', array('relationshipgroupid' => $relationshipgroup->id)),
                html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('t/edit'), 'alt' => get_string('edit'), 'title' => get_string('edit'), 'class' => 'iconsmall')));
    }
    if ($manager || $canassign) {
        $buttons[] = html_writer::link(new moodle_url('/local/relationship/assign.php', array('relationshipgroupid' => $relationshipgroup->id)),
                html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('t/assignroles'), 'alt' => get_string('assign', 'local_relationship'), 'title' => get_string('assign', 'local_relationship'), 'class' => 'iconsmall')));
    }
    $line[] = implode(' ', $buttons);

    $data[] = $line;
}
$table = new html_table();
$table->head = array(get_string('name', 'local_relationship'),
        get_string('memberscount', 'local_relationship'),
        get_string('limit', 'local_relationship').$OUTPUT->help_icon('userlimit', 'local_relationship'),
        get_string('uniformdistribute', 'local_relationship').$OUTPUT->help_icon('uniformdistribute', 'local_relationship'),
        get_string('edit'));
$table->colclasses = array('leftalign name',
        'leftalign size',
        'centeralign uniformdistribute',
        'leftalign name');

$table->id = 'relationships';
$table->attributes['class'] = 'admintable generaltable';
$table->data = $data;

echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
echo html_writer::table($table);
if ($editable) {
    $sql = "SELECT rc.id, rc.roleid, count(*) AS count
              FROM {relationship_cohorts} rc
              JOIN {cohort} ch ON (ch.id = rc.cohortid)
              JOIN {cohort_members} cm ON (cm.cohortid = ch.id)
         LEFT JOIN {relationship_members} rm ON (rm.relationshipcohortid = rc.id AND rm.userid = cm.userid)
             WHERE rc.relationshipid = :relationshipid
               AND rc.uniformdistribution = 1
               AND ISNULL(rm.userid)
          GROUP BY rc.id, rc.roleid";
    $rcs = $DB->get_records_sql($sql, array('relationshipid' => $relationshipid));
    if (!empty($rcs)) {
        echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthnarrow');
        echo $OUTPUT->heading(get_string('remaining', 'local_relationship'));

        $rdata = array();
        foreach ($rcs AS $rc) {
            $role = $DB->get_record('role', array('id' => $rc->roleid));
            $rdata[] = array(role_get_name($role), $rc->count);
        }
        $rtable = new html_table();
        $rtable->head = array(get_string('role'),
                get_string('remaining', 'local_relationship'));
        $rtable->attributes['class'] = 'generaltable';
        $rtable->data = $rdata;
        echo html_writer::table($rtable);

        $distributeremaining = new single_button(new moodle_url('/local/relationship/edit_group.php', array('relationshipid' => $relationshipid, 'distributeremaining' => 1)), get_string('distributeremaining', 'local_relationship'));
        echo $OUTPUT->render($distributeremaining);
        echo $OUTPUT->box_end();
    }

    $addgroup = new single_button(new moodle_url('/local/relationship/edit_group.php', array('relationshipid' => $relationshipid)), get_string('addgroup', 'local_relationship'));
    $addgroups = new single_button(new moodle_url('/local/relationship/autogroup.php', array('relationshipid' => $relationshipid)), get_string('autogroup', 'local_relationship'));
    echo html_writer::tag('div', $OUTPUT->render($addgroup).$OUTPUT->render($addgroups), array('class' => 'buttons'));
} else if ($manager) {
    echo $OUTPUT->heading(get_string('noeditable', 'local_relationship'));
}
echo $OUTPUT->box_end();

echo $OUTPUT->footer();
