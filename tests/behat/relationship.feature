@local @local_relationship @javascript
Feature: Manipulation of relationships
  In order to control usage of relationships
  As a user
  I need to have/not have access to relationships

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

  And the following "cohorts" exist:
    | name     | idnumber |
    | Cohort 1 | COHORT1  |

@javascript
Scenario: Admin is able to open the relationships page for a category
  When I log in as "admin"
  And I am on the relationships page for category "Category 1"
  Then I should see "Relationships"
  And I should see "Add"

@javascript
Scenario: User with capability is able to open the relationships page for a category
  When I log in as "teacher1"
  And I am on the relationships page for category "Category 1"
  Then I should see "Relationships"
  And I should see "Add"

@javascript
Scenario: User with capability has access to the relationship creation and deletion feature
  When I log in as "teacher1"
  And I am on the relationships page for category "Category 1"
  And I press "Add"
  Then I should see "Add new relationship"
  When I set the field "Name" to "Teste 1"
  And I set the field "Description" to "Description"
  And I press "Save changes"
  Then I should see "Teste 1" in the "td" "css_element"
  And I should see "Manually created"
  When I follow "Delete"
  And I press "Continue"
  Then I should not see "Teste 1" in the "td" "css_element"
  And I should not see "manually created"

@javascript
Scenario: User with capability has access to the relationship edition features
  Given I log in as "teacher1"
  And I am on the relationships page for category "Category 1"
  And I press "Add"
  And I set the field "Name" to "Teste 1"
  And I set the field "Description" to "Description"
  And I press "Save changes"
  When I follow "Edit"
  Then I should see "Edit relationship"
  When I press "Cancel"
  And I follow "Roles and cohorts"
  Then I should see "Roles and cohorts"
  When I follow "Relationships"
  When I follow "Groups"
  Then I should see "Groups" in the "h4" "css_element"

@javascript
Scenario: Submitting a new relationship without a name keeps the user on the edit form
  Given I log in as "teacher1"
  And I am on the relationships page for category "Category 1"
  And I press "Add"
  When I set the field "Description" to "Sem nome"
  And I press "Save changes"
  Then I should see "Add new relationship"

@javascript
Scenario: Cancel on the relationship edit form returns to the listing without persisting
  Given I log in as "teacher1"
  And I am on the relationships page for category "Category 1"
  And I press "Add"
  And I set the field "Name" to "Nao persistido"
  When I press "Cancel"
  Then I should not see "Nao persistido"

@javascript
Scenario: Search filters the relationships listing by name
  Given I log in as "teacher1"
  And I am on the relationships page for category "Category 1"
  And I press "Add"
  And I set the field "Name" to "Alpha"
  And I set the field "Description" to "A"
  And I press "Save changes"
  And I press "Add"
  And I set the field "Name" to "Beta"
  And I set the field "Description" to "B"
  And I press "Save changes"
  When I set the field "relationship_search_q" to "Alpha"
  And I press "Search"
  Then I should see "Alpha"
  And I should not see "Beta"

@javascript
Scenario: Relationships flagged with an external component hide the edit and delete icons
  Given I log in as "teacher1"
  And I am on the relationships page for category "Category 1"
  And I press "Add"
  And I set the field "Name" to "Externo"
  And I set the field "Description" to "Owned by another plugin"
  And I press "Save changes"
  And the relationship "Externo" has component "local_relationship"
  When I am on the relationships page for category "Category 1"
  Then I should see "Externo"
  And "//table[@id='relationships']//tr[contains(., 'Externo')]//img[@alt='Edit']" "xpath_element" should not exist
  And "//table[@id='relationships']//tr[contains(., 'Externo')]//img[@alt='Delete']" "xpath_element" should not exist

@javascript
Scenario: Listing paginates when more than 25 relationships exist
  Given 27 relationships exist in category "Category 1" with prefix "Rel"
  And I log in as "teacher1"
  When I am on the relationships page for category "Category 1"
  Then I should see "Rel 01"
  And I should not see "Rel 27"
  When I follow "2"
  Then I should see "Rel 27"
  And I should not see "Rel 01"

@javascript
Scenario: A relationship used by a course shows a Listar link expanding the courses-using panel
  Given I log in as "teacher1"
  And I am on the relationships page for category "Category 1"
  And I press "Add"
  And I set the field "Name" to "Usado"
  And I set the field "Description" to "Has an enrol instance"
  And I press "Save changes"
  And course "c1" uses the relationship "Usado"
  When I am on the relationships page for category "Category 1"
  Then I should see "List" in the "//table[@id='relationships']//tr[contains(., 'Usado')]" "xpath_element"
  When I follow "List"
  Then I should see "Course1"
  And I should see "Courses using"
