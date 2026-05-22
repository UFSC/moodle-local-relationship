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
 * Strings de tradução do componente 'local_relationship' (Português Brasil)
 *
 * @package local_relationship
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['pluginname'] = 'Relacionamentos';
$string['allocated'] = ' (já alocado em outro grupo)';
$string['notallocated'] = ' (ainda não alocado)';
$string['viewreport'] = 'Vizualizar';

$string['enabled'] = 'Ativado';
$string['enable'] = 'ativar';
$string['disable'] = 'desativar';
$string['saved'] = 'Dados foram salvos';

$string['cohorts'] = 'Papeis e coortes';
$string['groups'] = 'Grupos';
$string['list'] = 'Listar';
$string['fromcohort'] = 'A partir do coorte ';
$string['fromcohort_help'] = 'Os grupos são criados tendo por base o coorte selecionado.
    Será criado um grupo para cada membro do coorte, sendo que esse usuário será automaticamente inscrito no grupo.
    O nome do grupo é definido pelo "esquema de nomes".';
$string['namingscheme'] = 'Esquema de nomes';
$string['namingscheme_help'] = 'O símbolo de arroba (@) pode ser usado para criar grupos com nomes que contenham letras.
    Por exemplo, "Grupo @" irá gerar grupos, denominados "Grupo A", "Grupo B", "Grupo C", ...<BR>
    O símbolo de cerquilha (#) pode ser usado para criar grupos com nomes que contenham números.
    Por exemplo, "Grupo #" irá gerar grupos, denominados "Grupo 1", "Grupo 2", "Grupo 3", ...<BR>
    No caso de ser selecionado um coorte, os símbolos arroba (@) e cerquilha (#) podem ser usados para criar grupos com nomes dos membros deste coorte.
    Por exemplo, "Grupo @" irá gerar grupos, denominados "Grupo João Carlos", "Grupo Maria Lima", ...';
$string['limit'] = 'Limite p/papel';
$string['userlimit'] = 'Limite de usuários por papel';
$string['userlimit_help'] = 'Número máximo de usuários permitidos no grupo em cada papel.<br>Este valor só é verificado nos casos
    em que a inscrição é automática em função de algum critério.
    <br>O valor 0 (zero) indica que não há limite para inscrições.';

$string['autogroup'] = 'Adicionar vários grupos';
$string['numbergroups'] = 'Número de grupos';
$string['creategroups'] = 'Adicionar grupos';
$string['preview'] = 'Pré-visualizar';
$string['alreadyexists'] = ' (Já existente)';
$string['alreadyexistsexternal'] = ' (Já existente em algum dos cursos que utilizam este relacionamento)';

$string['allowdupsingroups'] = 'Inscrição em vários grupos';
$string['allowdupsingroups_help'] = 'Quando habilitada, esta opção indica que um membro do coorte pode ser inscrito
    em mais de um dos grupos definidos neste relacionamento. Caso contrário um membro só poderá ser inscrito em um dos grupos.';
$string['rolescohortsfull'] = 'Papeis e coortes para o relacionamento: \'{$a}\'';
$string['noeditable'] = 'Este relacionamento não pode ser alterado pois é de origem externa';
$string['nocohorts'] = 'Não há coortes disponíveis para adição neste relacionamento.';

$string['search'] = 'Buscar';
$string['searchrelationship'] = 'Buscar relacionamentos: ';

$string['uniformdistribute'] = 'Distribuição uniforme';
$string['uniformdistribute_help'] = 'Quando habilitada, esta opção indica que membros de coorte habilitado devem ser
    uniforme e automaticamente distribuídos entre os grupos deste relacionamento que igualmente tenham sido habilitados.';

$string['cantedit'] = 'Este relacionamento não pode ser manualmente alterado';

$string['tochangegroups'] = 'Para mudar grupos de relacionamentos \'{$a}\' é necessário, primeiro, desabilitar a destribuição uniforme dos membros.
   Após você terá que reabilitar manualmente.<BR><BR>Você gostaria de desabilitar a destribuição uniforme para os relacionamento \'{$a}\'?';
$string['groups_unchangeable'] = 'Os grupos não podem ser alterados porque a distribuição uniforme está ativa para este relacionamento';

$string['addgroup'] = 'Adicionar novo grupo';
$string['remaining'] = 'Remanescentes';
$string['distributeremaining'] = 'Distribuir remanescentes';
$string['editgroup'] = 'Editar grupo: \'{$a}\'';
$string['deletegroup'] = 'Remover grupo: \'{$a}\'';

$string['addrelationship'] = 'Adicionar novo relacionamento';
$string['anyrelationship'] = 'Qualquer';

$string['addcohort'] = 'Adicionar novo papel/coorte';
$string['editcohort'] = 'Editar papel/coorte: \'{$a}\'';
$string['deletecohort'] = 'Remover papel/coorte: \'{$a}\'';

$string['assign'] = 'Atribuir';
$string['courses'] = 'Cursos';
$string['coursesusing'] = 'Cursos que utilizam o relacionamento: \'{$a}\'';
$string['assignto'] = 'Membros do grupo: \'{$a}\'';
$string['backtorelationship'] = 'Voltar para o relacionamento';
$string['backtorelationships'] = 'Voltar para relacionamentos';
$string['bulkadd'] = 'Adicionar relacionamento';
$string['bulknorelationship'] = 'Nenhum relacionamento disponível encontrado';
$string['relationship'] = 'Relacionamento';
$string['relationships'] = 'Relacionamentos';
$string['relationshipgroups'] = 'Lista de grupos do relacionamento \'{$a}\'';
$string['relationshipcourses'] = 'Lista de cursos para este relacionamento';
$string['relationship:assign'] = 'Designar membros do relacionamento';
$string['relationship:manage'] = 'Gerenciar relacionamentos';
$string['relationship:view'] = 'Usar relacionamentos e ver membros';
$string['component'] = 'Fonte';
$string['currentusers'] = 'Usuários atuais';
$string['currentusersmatching'] = 'Usuários atuais que conferem';
$string['deleterelationship'] = 'Remover relacionamento';
$string['confirmdelete'] = 'Você realmente quer remover o relacionamento: \'{$a}\'?';
$string['confirmdeletegroup'] = 'Você realmente quer remover o grupo: \'{$a}\'?';
$string['confirmdeleletecohort'] = 'Você realmente quer remover papel/cohort: \'{$a}\'?';
$string['description'] = 'Descrição';
$string['duplicateidnumber'] = 'Já há um relacionamento com essa mesma ID';
$string['editrelationship'] = 'Editar relacionamento';
$string['event_relationship_created'] = 'Relacionamento criado';
$string['event_relationship_deleted'] = 'Relacionamento removido';
$string['event_relationship_updated'] = 'Relacionamento atualizado';
$string['event_relationshipgroup_created'] = 'Relacionamento do grupo criado';
$string['event_relationshipgroup_deleted'] = 'Relacionamento do grupo removido';
$string['event_relationshipgroup_updated'] = 'Relacionamento do grupo atualizado';
$string['event_relationshipgroup_member_added'] = 'Usuários adicionados em um relacionamento';
$string['event_relationshipgroup_member_removed'] = 'Usuários removidos de um relacionamento';
$string['external'] = 'Relacionamento externo';
$string['idnumber'] = 'ID do relacionamento';
$string['memberscount'] = 'Membros';
$string['name'] = 'Nome';
$string['no_name'] = 'É necessário definir um nome para o relacionameto.';
$string['groupname'] = 'Nome do Grupo';
$string['groupname_pattern'] = 'Padrão de nome de grupo';
$string['nocomponent'] = 'Criado manualmente';
$string['potusers'] = 'Potenciais usuários';
$string['potusersmatching'] = 'Possíveis usuários que conferem';
$string['removeuserwarning'] = 'A remoção de usuários de um relacionamento pode resultar no cancelamento da inscrição de usuários em múltiplos cursos o que inclui a remoção de configurações de usuários, notas, participação em grupos e outras informações dos cursos afetados.';
$string['removegroupwarning'] = 'A remoção de grupos de um relacionamento pode resultar no cancelamento da inscrição de usuários em múltiplos cursos o que inclui a remoção de configurações de usuários, notas, participação em grupos e outras informações dos cursos afetados.';
$string['deletecohortwarning'] = 'A remoção de papeis/coortes de um relacionamento pode resultar no cancelamento da inscrição de usuários em múltiplos cursos o que inclui a remoção de configurações de usuários, notas, participação em grupos e outras informações dos cursos afetados.';
$string['selectfromrelationship'] = 'Selecionar membros do relacionamento';
$string['unknownrelationship'] = 'Relacionamento desconhecido ({$a})!';
$string['useradded'] = 'Usuário adicionado ao relacionamento "{$a}"';
$string['tag'] = 'Etiqueta';
$string['tags'] = 'Etiquetas';
$string['addtag'] = 'Adicionar etiqueta';
$string['relationshiptags'] = 'Lista de etiquetas do relacionamento \'{$a}\'';
$string['edittagof'] = 'Editar etiquetas de \'{$a}\'';
$string['deltagof'] = 'Remover etiqueta de \'{$a}\'';
$string['delconfirmtag'] = 'Você realmente quer Remover esta etiqueta \'{$a}\'?';
$string['tagname'] = 'Nome da etiqueta:';
$string['no_delete_tag'] = 'Não é permitido remover etiquetas criadas por outros módulos.';
$string['tag_already_exists'] = 'Esta etiqueta já existe. Entre com outro nome para a etiqueta!';
$string['group_already_exists'] = 'Este grupo já existe. Entre com outro nome para o grupo!';
$string['course_group_already_exists'] = 'já há grupo com mesmo nome no curso: \'{$a}\'. É necessário renomear ou remover esse grupo.';
$string['relationship_already_exists'] = 'Já existe relacionamento com este nome neste contexto. Ofereça outro nome para o relacionamento.';
$string['has_cohorts'] = 'O relacionamento não pode ser excluído pois há um ou mais coortes cadastrados';
