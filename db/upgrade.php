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

defined('MOODLE_INTERNAL') || die();

function xmldb_local_relationship_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2023032000) {

        // Define table relationship_pendencies to be created.
        $table = new xmldb_table('relationship_pendencies');

        // Adding fields to table relationship_pendencies.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('relationshipcohortid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('relationshipgroupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('cpf', XMLDB_TYPE_CHAR, '11', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table relationship_pendencies.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table relationship_pendencies.
        $table->add_index('relpend_relcpf_uix', XMLDB_INDEX_UNIQUE, array('relationshipgroupid', 'relationshipcohortid', 'cpf'));
        $table->add_index('relpend_cpf_ix', XMLDB_INDEX_NOTUNIQUE, array('cpf'));

        // Conditionally launch create table for relationship_pendencies.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Relationship savepoint reached.
        upgrade_plugin_savepoint(true, 2023032000, 'local', 'relationship');
    }

    if ($oldversion < 2023033000) {

        // Define field allowallusers to be added to relationship_pendencies.
        $table = new xmldb_table('relationship_pendencies');
        $field = new xmldb_field('allowallusers', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'cpf');

        // Conditionally launch add field allowallusers.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Relationship savepoint reached.
        upgrade_plugin_savepoint(true, 2023033000, 'local', 'relationship');
    }

    return true;
}
