@local @local_relationship @javascript
Feature: Bulk group creation via autogroup
  In order to quickly create many groups in a relationship
  As a user with local/relationship:manage capability
  I need to use the autogroup UI with naming schemes and previews

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
    | name     | idnumber | contextlevel | reference |
    | Cohort 1 | COHORT1  | Category     | CAT1      |

  And the following "cohort members" exist:
    | user     | cohort  |
    | student1 | COHORT1 |

  And the following "local_relationship > relationships" exist:
    | name    | category |
    | Teste 1 | CAT1     |

  And the following "local_relationship > relationship cohorts" exist:
    | relationship | cohort  | role    |
    | Teste 1      | COHORT1 | student |

  And I log in as "teacher1"
  And I am on the relationships page for category "Category 1"
  And I follow "Groups"

@javascript
Scenario: Autogroup by number with the # token creates a numbered series
  When I press "Add multiple groups"
  And I set the field "Naming scheme" to "Sala #"
  And I set the field "Number of groups" to "3"
  And I press "Add groups"
  Then I should see "Sala 1" in the "//table[@id='relationships']/tbody//td[1]" "xpath_element"
  And I should see "Sala 2"
  And I should see "Sala 3"

@javascript
Scenario: Autogroup by number with the @ token creates a letter series
  When I press "Add multiple groups"
  And I set the field "Naming scheme" to "Sala @"
  And I set the field "Number of groups" to "3"
  And I press "Add groups"
  Then I should see "Sala A"
  And I should see "Sala B"
  And I should see "Sala C"

@javascript
Scenario: Autogroup by cohort uses each member name as a value_is_a_name replacement
  When I press "Add multiple groups"
  And I set the field "Naming scheme" to "Grupo @"
  And I click on the element with xpath "//select[@id='id_relationshipcohortid']/option[2]"
  And I press "Add groups"
  Then I should see "Grupo Student 1"

@javascript
Scenario: Autogroup preview flags pre-existing groups in red without creating duplicates
  When I press "Add new group"
  And I set the field "Group name" to "Sala 1"
  And I press "Save changes"
  Then I should see "Sala 1" in the "//table[@id='relationships']/tbody//td[1]" "xpath_element"
  When I press "Add multiple groups"
  And I set the field "Naming scheme" to "Sala #"
  And I set the field "Number of groups" to "3"
  And I press "Preview"
  Then I should see "Already exists"
  When I press "Add groups"
  Then I should see "Sala 2"
  And I should see "Sala 3"
