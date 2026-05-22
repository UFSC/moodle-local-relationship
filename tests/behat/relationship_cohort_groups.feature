@local @local_relationship @javascript
Feature: Manipulation of cohorts and groups in a relationship
  In order to add, edit or remove cohorts and groups
  I need to have the right capability

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
  And I follow "Add new cohort"
  And I set the field "name" to "Cohort 2"
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

@javascript
Scenario: User with capability is able to edit a cohort in a relationship
  When I follow "Papeis e coortes"
  Then I should see "No" in the "//table[@id='relationships']/tbody//td[4]" "xpath_element"
  When I follow "Edit"
  Then I should see "Editar papel/coorte"
  When I select "Yes" from the "id_allowdupsingroups" singleselect
  And I press "Save changes"
  Then I should see "Yes" in the "//table[@id='relationships']/tbody//td[4]" "xpath_element"

@javascript
Scenario: User with capability is able to delete cohort from a relationship
  When I follow "Papeis e coortes"
  Then I should see "Cohort 1" in the "//table[@id='relationships']/tbody//td[1]" "xpath_element"
  When I follow "Delete"
  And I press "Continue"
  Then I should not see "Cohort 1"

@javascript
Scenario: User with capability is able to create groups in a relationship
  When I follow "Groups"
  Then I should see "" in the "//table[@id='relationships']/tbody//td[1]" "xpath_element"
  When I press "Adicionar novo grupo"
  And I set the field "Nome do Grupo" to "Grupo teste"
  And I press "Save changes"
  Then I should see "Grupo teste" in the "//table[@id='relationships']/tbody//td[1]" "xpath_element"

@javascript
Scenario: User with capability is able to edit groups in a relationship
  When I follow "Groups"
  And I press "Adicionar novo grupo"
  And I set the field "Nome do Grupo" to "Grupo teste"
  And I press "Save changes"
  Then I should see "Grupo teste" in the "//table[@id='relationships']/tbody//td[1]" "xpath_element"
  When I follow "Edit"
  Then I should see "Editar grupo"
  When  I set the field "Nome do Grupo" to "Teste"
  And I press "Save changes"
  Then I should see "Teste" in the "//table[@id='relationships']/tbody//td[1]" "xpath_element"

@javascript
Scenario: User with capability is able to remove groups from a relationship
  When I follow "Groups"
  And I press "Adicionar novo grupo"
  And  I set the field "Nome do Grupo" to "Grupo teste"
  And I press "Save changes"
  Then I should see "Grupo teste" in the "//table[@id='relationships']/tbody//td[1]" "xpath_element"
  When I follow "Delete"
  And I press "Continue"
  Then I should see "" in the "//table[@id='relationships']/tbody//td[1]" "xpath_element"

@javascript
Scenario: Changes made to groups are shown to user
  When I follow "Groups"
  And I press "Adicionar novo grupo"
  And I set the field "Nome do Grupo" to "Grupo teste"
  And I press "Save changes"
  Then I should see "No" in the "//table[@id='relationships']/tbody//td[4]" "xpath_element"
  When I follow "ativar"
  Then I should see "Yes" in the "//table[@id='relationships']/tbody//td[4]" "xpath_element"


@javascript
Scenario: Removing a cohort with an enrolled user
  When I follow "Papeis e coortes"
  Then I should not see "Cohort 2"
  When I press "Add"
  And I press "Save changes"
  Then I should see "Cohort 2"
  When I follow "Relacionamentos"
  And I follow "Groups"
  And I press "Adicionar novo grupo"
  And I set the field "Nome" to "Grupo 1"
  And I press "Save changes"
  And I follow "Atribuir"
  And I click on "//table/tbody/tr/td[3]/div/select/optgroup/option[1]" "xpath_element"
  And I press "Add"
  And I follow "Grupos"
  Then I should see "1" in the "//table/tbody/tr[1]/td[2]" "xpath_element"
  When I follow "Relacionamentos"
  And I follow "Papeis e coortes"
  And I follow "Delete"
  And I press "Continue"
  And I follow "Relacionamentos"
  And I follow "Groups"
  Then I should see "0" in the "//table/tbody/tr[1]/td[2]" "xpath_element"

@javascript
Scenario: Removing a relationship with an associated cohort
  Then I should see "Teste 1" in the "//table/tbody/tr[1]/td[1]" "xpath_element"
  When I follow "Delete"
  And I press "Continue"
  Then I should see "Teste 1" in the "//table/tbody/tr[1]/td[1]" "xpath_element"

@javascript @wip
Scenario: Editing a relationship's parameters
  Then I should see "Teste 1"
  And I should not see "Novo nome"
  When I follow "Edit"
  And I set the field "Nome" to "Novo nome"
  And I press "Save changes"
  Then I should see "Novo nome"
  And I should not see "Teste 1"

@javascript
Scenario: Two cohorts can be attached to the same relationship with the same role
  When I follow "Papeis e coortes"
  Then I should see "Cohort 1" in the "//table[@id='relationships']/tbody//td[1]" "xpath_element"
  And I should not see "Cohort 2"
  When I press "Add"
  And I press "Save changes"
  Then I should see "Cohort 1" in the "//table[@id='relationships']/tbody/tr[1]/td[1]" "xpath_element"
  And I should see "Cohort 2" in the "//table[@id='relationships']/tbody/tr[2]/td[1]" "xpath_element"

@javascript
Scenario: The user limit per role can be set on a group and is shown on the groups list
  When I follow "Groups"
  And I press "Adicionar novo grupo"
  And I set the field "Nome do Grupo" to "Grupo limitado"
  And I set the field "Limite de usuários por papel" to "5"
  And I press "Save changes"
  Then I should see "Grupo limitado" in the "//table[@id='relationships']/tbody//td[1]" "xpath_element"
  And I should see "5" in the "//table[@id='relationships']/tbody//td[3]" "xpath_element"

@javascript
Scenario: Toggling uniformdistribution on a cohort is persisted and shown on the cohorts list
  When I follow "Papeis e coortes"
  Then I should see "No" in the "//table[@id='relationships']/tbody//tr[1]/td[5]" "xpath_element"
  When I follow "Edit"
  Then I should see "Editar papel/coorte"
  When I select "Yes" from the "id_uniformdistribution" singleselect
  And I press "Save changes"
  Then I should see "Yes" in the "//table[@id='relationships']/tbody//tr[1]/td[5]" "xpath_element"

@javascript
Scenario: Pressing distribute-remaining moves cohort members into uniformdistribution groups
  When I follow "Papeis e coortes"
  And I follow "Edit"
  And I select "Yes" from the "id_uniformdistribution" singleselect
  And I press "Save changes"
  Then I should see "Yes" in the "//table[@id='relationships']/tbody//tr[1]/td[5]" "xpath_element"
  When I follow "Relacionamentos"
  And I follow "Groups"
  And I press "Adicionar novo grupo"
  And I set the field "Nome do Grupo" to "Grupo uniforme"
  And I press "Save changes"
  And I follow "ativar"
  And I press "Distribuir remanescentes"
  Then I should see "1" in the "//table[@id='relationships']/tbody//td[2]" "xpath_element"
