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
  And I follow "Relacionamentos"
  And I press "Add"
  And I set the field "Nome" to "Teste 1"
  And I set the field "Descrição" to "Descrição"
  And I press "Save changes"
  And I follow "Papeis e coortes"
  And I press "Add"
  And I press "Save changes"
  And I log out
  And I log in as "teacher1"
  And I follow "Course1"
  And I follow "Category 1"
  And I follow "Relacionamentos"
  And I follow "Groups"

@javascript
Scenario: Autogroup by number with the # token creates a numbered series
  When I press "Adicionar vários grupos"
  And I set the field "Esquema de nomes" to "Sala #"
  And I set the field "Número de grupos" to "3"
  And I press "Adicionar grupos"
  Then I should see "Sala 1" in the "//table[@id='relationships']/tbody//td[1]" "xpath_element"
  And I should see "Sala 2"
  And I should see "Sala 3"

@javascript
Scenario: Autogroup by number with the @ token creates a letter series
  When I press "Adicionar vários grupos"
  And I set the field "Esquema de nomes" to "Sala @"
  And I set the field "Número de grupos" to "3"
  And I press "Adicionar grupos"
  Then I should see "Sala A"
  And I should see "Sala B"
  And I should see "Sala C"

@javascript
Scenario: Autogroup by cohort uses each member name as a value_is_a_name replacement
  When I press "Adicionar vários grupos"
  And I set the field "Esquema de nomes" to "Grupo @"
  And I click on the element with xpath "//select[@id='id_relationshipcohortid']/option[2]"
  And I press "Adicionar grupos"
  Then I should see "Grupo Student 1"

@javascript
Scenario: Autogroup preview flags pre-existing groups in red without creating duplicates
  When I press "Adicionar novo grupo"
  And I set the field "Nome do Grupo" to "Sala 1"
  And I press "Save changes"
  Then I should see "Sala 1" in the "//table[@id='relationships']/tbody//td[1]" "xpath_element"
  When I press "Adicionar vários grupos"
  And I set the field "Esquema de nomes" to "Sala #"
  And I set the field "Número de grupos" to "3"
  And I press "Pré-visualizar"
  Then I should see "Já existente"
  When I press "Adicionar grupos"
  Then I should see "Sala 2"
  And I should see "Sala 3"
