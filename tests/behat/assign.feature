@local @local_relationship @javascript
Feature: Manual user assignment to relationship groups
  In order to control which users belong to a relationship group
  As a user with local/relationship:assign capability
  I need to add and remove members via the assign UI

Background:
  Given the following "users" exist:
  | username | firstname | lastname | email                 |
  | student1 | Student   |     1    | stundent1@example.com |
  | teacher1 | Teacher   |     1    | teacher1@example.com  |

  And the following "categories" exist:
  | name       | category | idnumber |
  | Category 1 | 0        | CAT1     |

  And the following "courses" exist:
  | fullname | shortname | id | category |
  | Course1  | c1        | 1  | CAT1     |

  And the following "course enrolments" exist:
  | user     | course | role    |
  | student1 | c1     | student |
  | teacher1 | c1     | teacher |

  And the following "permission overrides" exist:
  | capability                | permission | role    | contextlevel | reference |
  | local/relationship:view   | Allow      | teacher | Category     | CAT1      |
  | local/relationship:manage | Allow      | teacher | Category     | CAT1      |
  | local/relationship:assign | Allow      | teacher | Category     | CAT1      |
  | moodle/cohort:view        | Allow      | teacher | Category     | CAT1      |

  And the following "role assigns" exist:
  | user     | role    | contextlevel | reference |
  | teacher1 | teacher | Category     | CAT1      |

  And I log in as "admin"
  And I follow "Courses"
  And I follow "Category 1"
  And I follow "Cohorts"
  And I follow "Add new cohort"
  And I set the field "name" to "Cohort 1"
  And I press "Save changes"
  And I follow "Assign"
  And I click on the element with xpath "//table/tbody/tr/td[3]/div/select/optgroup/option[1]"
  And I press "Add"
  And I follow "Relationships"
  And I press "Add"
  And I set the field "Name" to "Teste 1"
  And I set the field "Description" to "Description"
  And I press "Save changes"
  And I follow "Roles and cohorts"
  And I press "Add"
  And I press "Save changes"
  And I follow "Relationships"
  And I follow "Groups"
  And I press "Add new group"
  And I set the field "Group name" to "Grupo 1"
  And I press "Save changes"
  And I log out
  And I log in as "teacher1"
  And I follow "Course1"
  And I follow "Category 1"
  And I follow "Relationships"

@javascript
Scenario: User with assign capability adds a member to a group manually
  When I follow "Groups"
  And I follow "Assign"
  And I click on the element with xpath "//table/tbody/tr/td[3]/div/select/optgroup/option[1]"
  And I press "Add"
  Then I should see "Student 1" in the "//select[@id='removeselect']" "xpath_element"
  When I follow "Groups"
  Then I should see "1" in the "//table/tbody/tr[1]/td[2]" "xpath_element"

@javascript
Scenario: User with assign capability removes a member from a group manually
  When I follow "Groups"
  And I follow "Assign"
  And I click on the element with xpath "//table/tbody/tr/td[3]/div/select/optgroup/option[1]"
  And I press "Add"
  Then I should see "Student 1" in the "//select[@id='removeselect']" "xpath_element"
  When I click on the element with xpath "//select[@id='removeselect']/optgroup/option[1]"
  And I press "Remove"
  Then I should not see "Student 1" in the "//select[@id='removeselect']" "xpath_element"
  When I follow "Groups"
  Then I should see "0" in the "//table/tbody/tr[1]/td[2]" "xpath_element"

@javascript
Scenario: User without assign capability sees the group members in read-only mode
  Given the following "permission overrides" exist:
  | capability                | permission | role    | contextlevel | reference |
  | local/relationship:assign | Prohibit   | teacher | Category     | CAT1      |
  When I follow "Groups"
  And I follow "Assign"
  Then I should see "Group members: 'Grupo 1'"
  And "//select[@id='removeselect']" "xpath_element" should exist
  And "//select[@id='addselect']" "xpath_element" should not exist
  And "//input[@name='add']" "xpath_element" should not exist
  And "//input[@name='remove']" "xpath_element" should not exist
