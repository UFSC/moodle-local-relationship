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
 * Translation strings for 'local_relationship' component
 *
 * @package local_relationship
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['pluginname'] = 'Relationships';
$string['allocated'] = ' (already allocated to another group)';
$string['notallocated'] = ' (not yet allocated)';
$string['viewreport'] = 'View';

$string['enabled'] = 'Enabled';
$string['enable'] = 'enable';
$string['disable'] = 'disable';
$string['saved'] = 'Data has been saved';

$string['cohorts'] = 'Roles and cohorts';
$string['groups'] = 'Groups';
$string['list'] = 'List';
$string['fromcohort'] = 'From cohort ';
$string['fromcohort_help'] = 'Groups are created based on the selected cohort.
    One group is created for each cohort member, and that user is automatically enrolled in the group.
    The group name is defined by the "naming scheme".';
$string['namingscheme'] = 'Naming scheme';
$string['namingscheme_help'] = 'The at sign (@) can be used to create groups with letter-based names.
    For example, "Group @" will generate groups named "Group A", "Group B", "Group C", ...<BR>
    The hash sign (#) can be used to create groups with number-based names.
    For example, "Group #" will generate groups named "Group 1", "Group 2", "Group 3", ...<BR>
    When a cohort is selected, the at (@) and hash (#) symbols can be used to create groups named after the cohort members.
    For example, "Group @" will generate groups named "Group João Carlos", "Group Maria Lima", ...';
$string['limit'] = 'Limit per role';
$string['userlimit'] = 'User limit per role';
$string['userlimit_help'] = 'Maximum number of users allowed in the group for each role.<br>This value is only enforced
    when enrolment is automatic based on some criterion.
    <br>A value of 0 (zero) means there is no enrolment limit.';

$string['autogroup'] = 'Add multiple groups';
$string['numbergroups'] = 'Number of groups';
$string['creategroups'] = 'Add groups';
$string['preview'] = 'Preview';
$string['alreadyexists'] = ' (Already exists)';
$string['alreadyexistsexternal'] = ' (Already exists in one of the courses using this relationship)';

$string['allowdupsingroups'] = 'Allow enrolment in multiple groups';
$string['allowdupsingroups_help'] = 'When enabled, this option indicates that a cohort member can be enrolled
    in more than one of the groups defined in this relationship. Otherwise a member can only be enrolled in one group.';
$string['rolescohortsfull'] = 'Roles and cohorts for the relationship: \'{$a}\'';
$string['noeditable'] = 'This relationship cannot be modified because it has an external origin';
$string['nocohorts'] = 'There are no cohorts available to add to this relationship.';

$string['search'] = 'Search';
$string['searchrelationship'] = 'Search relationships: ';

$string['uniformdistribute'] = 'Uniform distribution';
$string['uniformdistribute_help'] = 'When enabled, this option indicates that members of an enabled cohort should be
    uniformly and automatically distributed among the relationship groups that have also been enabled.';

$string['cantedit'] = 'This relationship cannot be manually modified';

$string['tochangegroups'] = 'To change groups of relationships \'{$a}\' you must first disable uniform member distribution.
   You will then have to re-enable it manually.<BR><BR>Would you like to disable uniform distribution for the \'{$a}\' relationships?';
$string['groups_unchangeable'] = 'Groups cannot be modified because uniform distribution is active for this relationship';

$string['addgroup'] = 'Add new group';
$string['remaining'] = 'Remaining';
$string['distributeremaining'] = 'Distribute remaining';
$string['editgroup'] = 'Edit group: \'{$a}\'';
$string['deletegroup'] = 'Delete group: \'{$a}\'';

$string['addrelationship'] = 'Add new relationship';
$string['anyrelationship'] = 'Any';

$string['addcohort'] = 'Add new role/cohort';
$string['editcohort'] = 'Edit role/cohort: \'{$a}\'';
$string['deletecohort'] = 'Delete role/cohort: \'{$a}\'';

$string['assign'] = 'Assign';
$string['courses'] = 'Courses';
$string['coursesusing'] = 'Courses using the relationship: \'{$a}\'';
$string['assignto'] = 'Group members: \'{$a}\'';
$string['backtorelationship'] = 'Back to relationship';
$string['backtorelationships'] = 'Back to relationships';
$string['bulkadd'] = 'Add relationship';
$string['bulknorelationship'] = 'No relationship available';
$string['relationship'] = 'Relationship';
$string['relationships'] = 'Relationships';
$string['relationshipgroups'] = 'List of groups for relationship \'{$a}\'';
$string['relationshipcourses'] = 'List of courses for this relationship';
$string['relationship:assign'] = 'Designate relationship members';
$string['relationship:manage'] = 'Manage relationships';
$string['relationship:view'] = 'Use relationships and view members';
$string['component'] = 'Source';
$string['currentusers'] = 'Current users';
$string['currentusersmatching'] = 'Matching current users';
$string['deleterelationship'] = 'Delete relationship';
$string['confirmdelete'] = 'Are you sure you want to delete the relationship: \'{$a}\'?';
$string['confirmdeletegroup'] = 'Are you sure you want to delete the group: \'{$a}\'?';
$string['confirmdeleletecohort'] = 'Are you sure you want to delete role/cohort: \'{$a}\'?';
$string['description'] = 'Description';
$string['duplicateidnumber'] = 'There is already a relationship with this ID';
$string['editrelationship'] = 'Edit relationship';
$string['event_relationship_created'] = 'Relationship created';
$string['event_relationship_deleted'] = 'Relationship deleted';
$string['event_relationship_updated'] = 'Relationship updated';
$string['event_relationshipgroup_created'] = 'Relationship group created';
$string['event_relationshipgroup_deleted'] = 'Relationship group deleted';
$string['event_relationshipgroup_updated'] = 'Relationship group updated';
$string['event_relationshipgroup_member_added'] = 'Users added to a relationship';
$string['event_relationshipgroup_member_removed'] = 'Users removed from a relationship';
$string['external'] = 'External relationship';
$string['idnumber'] = 'Relationship ID';
$string['memberscount'] = 'Members';
$string['name'] = 'Name';
$string['no_name'] = 'A name for the relationship is required.';
$string['groupname'] = 'Group name';
$string['groupname_pattern'] = 'Group name pattern';
$string['nocomponent'] = 'Manually created';
$string['potusers'] = 'Potential users';
$string['potusersmatching'] = 'Matching potential users';
$string['removeuserwarning'] = 'Removing users from a relationship may result in the unenrolment of users in multiple courses, which includes the removal of user settings, grades, group memberships and other information in the affected courses.';
$string['removegroupwarning'] = 'Removing groups from a relationship may result in the unenrolment of users in multiple courses, which includes the removal of user settings, grades, group memberships and other information in the affected courses.';
$string['deletecohortwarning'] = 'Removing roles/cohorts from a relationship may result in the unenrolment of users in multiple courses, which includes the removal of user settings, grades, group memberships and other information in the affected courses.';
$string['selectfromrelationship'] = 'Select relationship members';
$string['unknownrelationship'] = 'Unknown relationship ({$a})!';
$string['useradded'] = 'User added to relationship "{$a}"';
$string['tag'] = 'Tag';
$string['tags'] = 'Tags';
$string['addtag'] = 'Add tag';
$string['relationshiptags'] = 'List of tags for relationship \'{$a}\'';
$string['edittagof'] = 'Edit tags of \'{$a}\'';
$string['deltagof'] = 'Delete tag of \'{$a}\'';
$string['delconfirmtag'] = 'Are you sure you want to delete this tag \'{$a}\'?';
$string['tagname'] = 'Tag name:';
$string['no_delete_tag'] = 'Cannot delete tags created by other modules.';
$string['tag_already_exists'] = 'This tag already exists. Enter another name for the tag!';
$string['group_already_exists'] = 'This group already exists. Enter another name for the group!';
$string['course_group_already_exists'] = 'There is already a group with the same name in course: \'{$a}\'. Rename or remove that group.';
$string['relationship_already_exists'] = 'A relationship with this name already exists in this context. Provide another name for the relationship.';
$string['has_cohorts'] = 'The relationship cannot be deleted because there are one or more cohorts registered';
