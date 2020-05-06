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
 * Output class for assignment listing
 *
 * @package    report_assign
 * @copyright  2019 Howard Miller <howardsmiller@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_assign\output;

defined('MOODLE_INTERNAL') || die;

use renderable;
use renderer_base;
use templatable;
use context;
use context_course;

class listassign implements renderable, templatable {

    protected $course;

    protected $fullurl;

    protected $assignments;

    public function __construct($course, $fullurl, $assignments) {
        $this->course = $course;
        $this->fullurl = $fullurl;
        $this->assignments = $assignments;
    }

    /**
     * Human readable group mode
     * @param int groupmode
     * @return string
     */
    protected function groupmode_name($groupmode) {
        switch ($groupmode) {
            case NOGROUPS:
                return '-';
            case VISIBLEGROUPS:
                return get_string('groupsvisible');
            case SEPARATEGROUPS:
                return get_string('groupsseparate');
        }

        return '-';
    }

    /**
     * Format assignment data for display
     * @param array $assignments
     * @return array
     */
    protected function format_assignments($assignments) {
        foreach ($assignments as $assid => $assignment) {
            $cm = \get_coursemodule_from_instance('assign', $assignment->id, 0, false, MUST_EXIST);
            $assign = \report_assign\lib::get_assign($this->course, $assid);
            $assignment->showlink = new \moodle_url($this->fullurl, ['assign' => $assid]);
            $assignment->exportlink = new \moodle_url($this->fullurl, ['assign' => $assid, 'export' => 1]);
            $assignment->dumplink = new \moodle_url('/report/assign/dump.php', ['assign' => $assid, 'id' => $this->course->id]);
            $assignment->groupsubmission = $assign->get_instance()->teamsubmission;
            $assignment->assignurl = new \moodle_url('/mod/assign/view.php', ['id' => $cm->id]);

            // Groups.
            $cm = get_coursemodule_from_instance('assign', $assid);
            $assignment->groupmode = self::groupmode_name($cm->groupmode);
        }

        return array_values($assignments);
    }

    public function export_for_template(renderer_base $output) {
        global $CFG;

        return [
            'baseurl' => $this->fullurl,
            'exportallurl' => new \moodle_url('/report/assign/index.php', ['id' => $this->course->id, 'exportall' => 1]),
            'isassignments' => !empty($this->assignments),
            'assignments' => $this->format_assignments($this->assignments),
            'enableplagiarism' => !empty($CFG->enableplagiarism),
        ];
    }

}