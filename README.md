[![Build Status](https://travis-ci.org/UFSC/moodle-local-relationship.svg?branch=master)](https://travis-ci.org/UFSC/moodle-local-relationship)
[![Dependency Status](https://gemnasium.com/badges/github.com/UFSC/moodle-local-relationship.svg)](https://gemnasium.com/github.com/UFSC/moodle-local-relationship)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/UFSC/moodle-local-relationship/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/UFSC/moodle-local-relationship/?branch=master)
[![ReviewNinja](https://app.review.ninja/54116132/badge)](https://app.review.ninja/UFSC/moodle-local-relationship)

[![Test Coverage](https://codeclimate.com/github/UFSC/moodle-local-relationship/badges/coverage.svg)](https://codeclimate.com/github/UFSC/moodle-local-relationship/coverage)
[![Code Climate](https://codeclimate.com/github/UFSC/moodle-local-relationship/badges/gpa.svg)](https://codeclimate.com/github/UFSC/moodle-local-relationship)
[![Issue Count](https://codeclimate.com/github/UFSC/moodle-local-relationship/badges/issue_count.svg)](https://codeclimate.com/github/UFSC/moodle-local-relationship)

Relationship
============

Este plugin disponibiliza uma nova forma de agrupamentos, que existe
no contexto de uma categoria, agregando um ou mais cohorts associados
a uma capability específica, com a possibilidade de definição de grupos
a partir dessas fontes de dados.

Com ele é possível representar algumas relações institucionais que não
possuem representação disponível no Moodle, como relação Tutor <->
Estudante.

Exemplo de uso
--------------

Ao definir um Relationship que será utilizado para representar a relação
Tutor <-> Estudante, é necessário associar um cohort que possuam todas as
pessoas que terão o papel de Tutor e atribuir esse papel a esse cohort.

Em seguida realizar o mesmo procedimento para criação de um cohort de
estudantes.

Com essas configurações realizadas, o próximo passo é criar um ou mais
grupos que incluam essas pessoas, podendo dividí-las automaticamente
através da seleção de algumas regras pré-existentes ou manualmente.

Ainda é possível definir restrições como tamanho do grupo e quantidade
máximas de pessoas de um determinado cohort em um grupo.

Atribuição em massa
------------------

Há a possibilidade de atribuição em massa de estudantes em grupos de
relacionamentos.

A atribuição em massa poderá ser realizada para cada grupo de
relalcionamento e somente para o papel estudante.

Caso os estudantes cadastrados na atribuição em massa não estiverem
no cohort vinclulado ao relacionamento no papel de estudante, então
estes estudantes serão incluídos automaticamente no cohort.

Caso haja distribuição automática no cohort, não será permitida a
atribuição em massa em qualquer grupo.

Caso haja distribuição automática em um grupo, não será permitida a
atribuição em massa nos grupos com esta característica.

Para desenvolvedores
--------------------

Caso o cohort de estudante seja mantido, e marcado na base de dados,
por um componente específico, não será perimita a atribuição em massa.

Isto pode ser feito, por exemplo, por um sistema automatizado de
inscrição, desta forma a integridade dos dados é mantida.

Eventos
-------

Este plugin utiliza a nova API de eventos, descrita em:
http://docs.moodle.org/dev/Event_2

Permissões
----------

Para que os relacionamentos possam ficar operacionais as seguintes 
permissões devem ser definidas para os papéis:

|   Capability              | Papel | Descrição |
| --- | --- | --- |
| **local/relationship:manage** | Gerente, Cordenador AVEA  | Gerenciar relacionamentos | 
| **local/relationship:view** | Gerente, Cordenador AVEA | Usar relacionamentos e ver membros |
| **local/relationship:assign** | Gerente, Cordenador AVEA | Designar membros do relacionamento' |
