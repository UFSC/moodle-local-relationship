@relationship
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
Scenario: Students cannot navigate to the relationship page
  When I log in as "student1"
  And I am on homepage
  And I follow "Course1"
  And I click on "Category 1" "link"
  Then I should not see "Relacionamentos"

@javascript
Scenario: Teachers are able to navigate in the relationship page
  When I log in as "teacher1"
  And I am on homepage
  And I follow "Course1"
  And I click on "Category 1" "link"
  And I click on "Relacionamentos" "link"

@javascript
Scenario: Admin is able to see link to relationship
  When I log in as "admin"
  And I am on homepage
  And I click on "Courses" "link"
  And I click on "Category 1" "link"
  Then I should see "Relacionamentos"

@javascript
Scenario: User with capability is able to see link to relationship
  When I log in as "teacher1"
  And I am on homepage
  And I follow "Course1"
  And I click on "Category 1" "link"
  Then I should see "Relacionamentos"

#@javascript @wip
#Scenario: Students cannot access the relationships page through the url
#  When I log in as "student1"
#  And I am on homepage
#  And I go to "http://150.162.242.121/mariana/unasus-cp/local/relationship             /index.php?contextid=1"
#  Then I should see "Usar relacionamentos e ver membros"

@javascript
Scenario: User with capability has access to the relationship creation and deletion feature
  When I log in as "teacher1"
  And I am on homepage
  And I follow "Course1"
  And I click on "Category 1" "link"
  And I click on "Relacionamentos" "link"
  And I click on "Add" "button"
  Then I should see "Adicionar novo relacionamento"
  When I fill in "Nome" with "Teste 1"
  And I fill in "Descrição" with "Descrição"
  And I click on "Save changes" "button"
  Then I should see "Teste 1" in the "td" "css_element"
  And I should see "Criado manualmente"
  When I click on "Delete" "link"
  And I click on "Continue" "button"
  Then I should not see "Teste 1" in the "td" "css_element"
  And I should not see "criado manualmente"

@javascript
Scenario: User with capability has access to the relationship edition features
  Given I log in as "teacher1"
  And I am on homepage
  And I follow "Course1"
  And I click on "Category 1" "link"
  And I click on "Relacionamentos" "link"
  And I click on "Add" "button"
  And I fill in "Nome" with "Teste 1"
  And I fill in "Descrição" with "Descrição"
  And I click on "Save changes" "button"
  When I click on "Edit" "link"
  Then I should see "Editar relacionamento"
  When I click on "Cancel" "button"
  And I click on "Papeis e coortes" "link"
  Then I should see "Papeis e coortes"
  When I click on "Relacionamentos" "link"
  When I click on "Groups" "link"
  Then I should see "Grupos" in the "h4" "css_element"

@javascript
Scenario: Add a relationship with user with capability
  When I log in as "admin"
  And I am on homepage
  And I follow "Courses"
  And I click on "Category 1" "link"
  And I click on "Relacionamentos" "link"
  And I click on "Add" "button"
  #And I click on "Adicionar novo relacionamento" "link"
  And I set the field "Nome" to "Teste 1"
  And I set the field "Descrição" to "Descrição"
  #And I set the field "Tags" to "Tag1,Tag2"
  And I press "Save changes"
