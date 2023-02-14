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
 * class based on core/admin/uu_progress_tracker by Petr Skoda

 * @package    local_relationship
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_relationship;
defined('MOODLE_INTERNAL') || die();

use html_writer;
use html_table;
use html_table_row;

class progress_tracker {
    private static $levels = array('normal', 'success', 'info', 'error');

    private $linenum;
    private $rows;
    private $columns;

    public $total;
    public $invalid;
    public $assigneduser;
    public $alreadyingroup;
    public $alreadyrelationshipmember;
    public $erroraddrelationshipmember;
    public $exceedslimit;
    public $registeredaspending;
    public $alreadyregisteredaspending;

    public function __construct($searchfield) {
        $this->rows = array();
        $this->columns = array($searchfield, 'username', 'name', 'status');
        $this->linenum = 0;

        $this->total = 0;
        $this->invalid = 0;
        $this->assigneduser = 0;
        $this->alreadyingroup = 0;
        $this->alreadyrelationshipmember = 0;
        $this->erroraddrelationshipmember = 0;
        $this->exceedslimit = 0;
        $this->registeredaspending = 0;
        $this->alreadyregisteredaspending = 0;
    }

    public function print_tracking() {
        global $OUTPUT;

        $table = new html_table();
        $table->tablealign = 'center';

        $table->head = array(get_string('titleline', 'local_relationship'));
        $table->align = array ('right');
        foreach($this->columns AS $col) {
            $table->head[] = get_string('title'.$col, 'local_relationship');
            $table->align[] = 'left';
        }

        $table->data = array();
        foreach ($this->rows AS $linenum => $r) {
            $row = array($linenum . '.');
            foreach($r AS $col=>$occurs) {
                $text = '';
                foreach ($occurs AS $occur) {
                    $msg = html_writer::tag('div', $occur['msg'], array('class' => 'alert-' . $occur['level']));
                    $text .= $msg;
                }
                $row[] = $text;
            }
            $table->data[] = $row;
        }

        echo $OUTPUT->box(html_writer::table($table), 'generalbox boxaligncenter boxwidthwide');
    }

    public function start_new_row() {
        $this->linenum++;
        $this->rows[$this->linenum] = array();
        foreach ($this->columns as $col) {
            $this->rows[$this->linenum][$col] = array();
        }
    }

    /**
     * Add tracking info
     * @param string $col name of column
     * @param string $msg message
     * @param string $level 'normal', 'success', 'info' or 'error'
     * @param bool $merge true means add as new line, false means override all previous text of the same type
     * @return void
     */
    public function track($col, $msg, $level = 'normal', $merge = true) {
        if (!isset($this->rows[$this->linenum][$col])) {
            debugging('Incorrect column:' . $col);
            return;
        }
        if (!in_array($level, self::$levels)) {
            debugging('Incorrect level:' . $level);
            return;
        }

        $occur = array('level'=>$level, 'msg'=>$msg);
        if ($merge) {
            $this->rows[$this->linenum][$col][] = $occur;
        } else {
            $this->rows[$this->linenum][$col] = array($occur);
        }
    }

    public function print_summary() {
        global $OUTPUT;

        $general = array('invalid', 'alreadyingroup', 'alreadyrelationshipmember', 'erroraddrelationshipmember', 'exceedslimit', 'registeredaspending', 'alreadyregisteredaspending');
        $emphasis = array('assigneduser', 'total');

        $rows = array();
        foreach ($general as $key) {
            if ($this->$key) {
                $rows[] = array(get_string($key, 'local_relationship'), $this->$key);
            }
        }

        foreach ($emphasis as $key) {
            $row = new html_table_row();
            $row->style = 'font-weight: bold';
            $row->cells = array(get_string($key, 'local_relationship'), $this->$key);
            $rows[] = $row;
        }

        echo $OUTPUT->box_start('boxwidthnormal boxaligncenter generalbox');
        echo $OUTPUT->heading(get_string('summary', 'local_relationship'), 4, 'main');
        $table = new html_table();
        $table->data = $rows;
        echo html_writer::table($table);
        echo $OUTPUT->box_end();
    }

}
