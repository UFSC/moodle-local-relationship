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
 * Tests para relationship_groups_parse_name (parser do formato @ letra / # número).
 *
 * @package    local_relationship
 * @copyright  2026 UFSC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/relationship/locallib.php');

/**
 * @group local_relationship
 */
class local_relationship_parse_name_testcase extends advanced_testcase {

    public function test_letter_series_starts_at_a_when_value_is_zero() {
        $this->assertSame('Grupo A', relationship_groups_parse_name('Grupo @', 0));
    }

    public function test_letter_series_increments_by_one_for_each_unit() {
        $this->assertSame('Grupo B', relationship_groups_parse_name('Grupo @', 1));
        $this->assertSame('Grupo C', relationship_groups_parse_name('Grupo @', 2));
    }

    public function test_letter_series_reaches_z_at_index_25() {
        $this->assertSame('Grupo Z', relationship_groups_parse_name('Grupo @', 25));
    }

    public function test_letter_series_wraps_to_double_letter_at_index_26() {
        // PHP increments 'Z' to 'AA' — comportamento herdado do operador ++ em string.
        $this->assertSame('Grupo AA', relationship_groups_parse_name('Grupo @', 26));
    }

    public function test_letter_series_double_letter_progression() {
        $this->assertSame('Grupo AB', relationship_groups_parse_name('Grupo @', 27));
    }

    public function test_number_series_starts_at_one_when_value_is_zero() {
        // Formato # adiciona +1 ao value (índice base-0 vira contador base-1).
        $this->assertSame('Sala 1', relationship_groups_parse_name('Sala #', 0));
    }

    public function test_number_series_increments_with_value() {
        $this->assertSame('Sala 6', relationship_groups_parse_name('Sala #', 5));
    }

    public function test_number_series_large_value() {
        $this->assertSame('Sala 1000', relationship_groups_parse_name('Sala #', 999));
    }

    public function test_format_without_token_returns_input_unchanged() {
        $this->assertSame('Grupo fixo', relationship_groups_parse_name('Grupo fixo', 0));
        $this->assertSame('Grupo fixo', relationship_groups_parse_name('Grupo fixo', 42));
    }

    public function test_letter_token_takes_priority_over_number_token() {
        // Quando ambos estão presentes, o ramo @ é executado e # permanece literal.
        $this->assertSame('A-#', relationship_groups_parse_name('@-#', 0));
    }

    public function test_value_is_a_name_replaces_letter_token_literally() {
        $this->assertSame('Turma Alpha', relationship_groups_parse_name('Turma @', 'Alpha', true));
    }

    public function test_value_is_a_name_replaces_number_token_literally() {
        // Sem @ no formato, # é alvo da substituição.
        $this->assertSame('Sala Norte', relationship_groups_parse_name('Sala #', 'Norte', true));
    }

    public function test_value_is_a_name_only_replaces_letter_token_when_both_present() {
        // value_is_a_name segue a mesma precedência: @ ganha se ambos estiverem no formato.
        $this->assertSame('X-#', relationship_groups_parse_name('@-#', 'X', true));
    }

    public function test_value_is_a_name_with_empty_string_replaces_with_empty() {
        $this->assertSame('Turma ', relationship_groups_parse_name('Turma @', '', true));
    }

    public function test_letter_token_at_start_of_format() {
        $this->assertSame('A Grupo', relationship_groups_parse_name('@ Grupo', 0));
    }

    public function test_number_token_in_middle_of_format() {
        $this->assertSame('Pre-3-Post', relationship_groups_parse_name('Pre-#-Post', 2));
    }

    public function test_multiple_letter_tokens_all_replaced() {
        // str_replace substitui todas as ocorrências do token.
        $this->assertSame('A-A', relationship_groups_parse_name('@-@', 0));
    }

    public function test_empty_format_returns_empty_string() {
        $this->assertSame('', relationship_groups_parse_name('', 0));
    }
}
