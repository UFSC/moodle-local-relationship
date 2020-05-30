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
 * Internal functions used to help manage the Relationship CRUD interface
 *
 * @package local_relationship
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/selector/lib.php');
require_once($CFG->dirroot . '/tag/lib.php');

function relationship_set_header($context, $url, $relationship=null, $module=null) {
    global $PAGE, $COURSE, $DB;

    if ($context->contextlevel != CONTEXT_COURSECAT) {
        print_error('invalidcontext');
    }
    $category = $DB->get_record('course_categories', array('id'=>$context->instanceid), '*', MUST_EXIST);
    $navtitle = get_string('relationships', 'local_relationship');

    $PAGE->set_pagelayout('standard');
    $PAGE->set_context($context);
    $PAGE->set_url($url);
    $PAGE->set_heading($COURSE->fullname);
    $PAGE->set_title($navtitle);

    $PAGE->navbar->add($category->name, new moodle_url('/course/index.php', array('categoryid'=>$category->id)));
    $PAGE->navbar->add($navtitle, new moodle_url('/local/relationship/index.php', array('contextid'=>$context->id)));
    if($module) {
        $PAGE->navbar->add(get_string($module, 'local_relationship'),
                           new moodle_url("/local/relationship/{$module}.php", array('relationshipid'=>$relationship->id)));
    }
}

function relationship_set_title($relationship=null, $action=null, $param=null) {
    global $OUTPUT;

    echo $OUTPUT->header();
    if($relationship) {
        echo $OUTPUT->heading(get_string('relationship', 'local_relationship') . ': ' . format_string($relationship->name));
        echo html_writer::empty_tag('BR');
    }
    if($action) {
        echo $OUTPUT->heading(get_string($action, 'local_relationship', $param), '4');
    }
}

function relationship_groups_parse_name($format, $value, $value_is_a_name=false) {
    if($value_is_a_name) {
        if (strstr($format, '@') !== false) {
            $str = str_replace('@', $value, $format);
        } else {
            $str = str_replace('#', $value, $format);
        }
    } else {
        if (strstr($format, '@') !== false) { // Convert $value to a character series
            $letter = 'A';
            for($i=0; $i<$value; $i++) {
                $letter++;
            }
            $str = str_replace('@', $letter, $format);
        } else { // Convert $value to a number series
            $str = str_replace('#', $value+1, $format);
        }
    }
    return($str);
}

function relationship_get_role_options() {
    $all_roles = role_get_names();
    $ctx_roles = get_roles_for_contextlevels(CONTEXT_COURSE);
    $roles = array();
    foreach($ctx_roles AS $id=>$roleid) {
        if($roleid > 2) {
            $roles[$roleid] = $all_roles[$roleid]->localname;
        }
    }
    asort($roles);
    return $roles;
}

function relationship_get_cohort_options($relationshipid) {
    global $DB;

    $relationship = $DB->get_record('relationship', array('id' => $relationshipid), '*', MUST_EXIST);
    $context = context::instance_by_id($relationship->contextid);

    $contextids = array();
    foreach($context->get_parent_context_ids(true) as $ctxid) {
        $context = context::instance_by_id($ctxid);
        if (has_capability('moodle/cohort:view', $context)) {
            $contextids[] = $ctxid;
        }
    }
    list($in_sql, $params) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);
    $sql = "SELECT id, name FROM {cohort} WHERE contextid {$in_sql} ORDER BY name";
    return $DB->get_records_sql_menu($sql, $params);
}

function relationship_get_courses($relationshipid) {
    global $DB;

    $sql = "SELECT DISTINCT c.id, c.shortname, c.fullname
              FROM {enrol} e
              JOIN {course} c ON (c.id = e.courseid)
             WHERE e.enrol = 'relationship'
               AND e.customint1 = :relationshipid
          ORDER BY c.fullname";
    return $DB->get_records_sql($sql, array('relationshipid'=>$relationshipid));
}

// Nomes de outros grupos que não os do próprio relacionamento
function relationship_get_other_courses_group_names($relationshipid) {
    global $DB;

    $sql = "SELECT DISTINCT g.name, e.courseid, c.fullname
              FROM {enrol} e
              JOIN {course} c ON (c.id = e.courseid)
              JOIN {groups} g ON (g.courseid = e.courseid)
             WHERE e.enrol = 'relationship'
               AND e.customint1 = :relationshipid
               AND g.idnumber NOT LIKE 'relationship_{$relationshipid}_%'
          ORDER BY g.name";
    $groups = array();
    foreach($DB->get_records_sql($sql, array('relationshipid'=>$relationshipid)) as $r) {
        $groups[$r->name][] = $r;
    }
    return $groups;
}

function relationship_is_in_use($relationshipid) {
    global $DB;

    return $DB->record_exists('enrol', array('enrol'=>'relationship', 'customint1'=>$relationshipid));
}

function relationship_courses_where_is_in_use($relationshipid) {
    global $DB;

    $sql = "SELECT DISTINCT c.id, c.shortname, c.fullname
              FROM {enrol} e
              JOIN {course} c ON (c.id = e.courseid)
             WHERE e.enrol = 'relationship'
               AND e.customint1 = :relationshipid";
    return $DB->get_records_sql($sql, array('relationshipid'=>$relationshipid));
}

/**
 * Somehow deal with relationships when deleting course category,
 * we can not just delete them because they might be used in enrol
 * plugins or referenced in external systems.
 * @param  stdClass|coursecat $category
 * @return void
 */
function relationship_delete_category($category) {
    global $DB;
    // TODO: make sure that relationships are really, really not used anywhere and delete, for now just move to parent or system context

    $oldcontext = context_coursecat::instance($category->id);

    if ($category->parent and $parent = $DB->get_record('course_categories', array('id'=>$category->parent))) {
        $parentcontext = context_coursecat::instance($parent->id);
        $sql = "UPDATE {relationship} SET contextid = :newcontext WHERE contextid = :oldcontext";
        $params = array('oldcontext'=>$oldcontext->id, 'newcontext'=>$parentcontext->id);
    } else {
        $syscontext = context_system::instance();
        $sql = "UPDATE {relationship} SET contextid = :newcontext WHERE contextid = :oldcontext";
        $params = array('oldcontext'=>$oldcontext->id, 'newcontext'=>$syscontext->id);
    }

    $DB->execute($sql, $params);
}

/**
 * Get all the relationships defined in given context.
 *
 * @param int $contextid
 * @param int $page number of the current page
 * @param int $perpage items per page
 * @param string $search search string
 * @return array    Array(totalrelationships => int, relationships => array, allrelationships => int)
 */
function relationship_search_relationships($contextid, $page = 0, $perpage = 25, $search = '') {
    global $DB;

    // Add some additional sensible conditions
    $tests = array('contextid = ?');
    $params = array($contextid);

    if (!empty($search)) {
        $conditions = array('name', 'idnumber', 'description');
        $searchparam = '%' . $DB->sql_like_escape($search) . '%';
        foreach ($conditions as $key=>$condition) {
            $conditions[$key] = $DB->sql_like($condition, "?", false);
            $params[] = $searchparam;
        }
        $tests[] = '(' . implode(' OR ', $conditions) . ')';
    }
    $wherecondition = implode(' AND ', $tests);

    $fields = "SELECT *";
    $countfields = "SELECT COUNT(1)";
    $sql = " FROM {relationship}
             WHERE $wherecondition";
    $order = " ORDER BY name ASC";
    $allrelationships = $DB->count_records('relationship', array('contextid'=>$contextid));
    $totalrelationships = $DB->count_records_sql($countfields . $sql, $params);
    $relationships = $DB->get_records_sql($fields . $sql . $order, $params, $page*$perpage, $perpage);
    foreach($relationships as $rl) {
        $rl->tags = core_tag_tag::get_item_tags_array('relationship', 'relationship', $rl->id);
    }

    return array('totalrelationships' => $totalrelationships, 'relationships' => $relationships, 'allrelationships'=>$allrelationships);
}





