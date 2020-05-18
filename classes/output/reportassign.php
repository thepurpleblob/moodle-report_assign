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

class reportassign implements renderable, templatable {

    protected $course;

    protected $context;

    protected $fullurl;

    protected $submissions;

    protected $assignment;

    protected $showparticipantnumber;

    protected $extensionsok;

    public function __construct($course, $context, $fullurl, $submissions, $assignment, $showparticipantnumber, $extensionsok) {
        $this->course = $course;
        $this->context = $context;
        $this->fullurl = $fullurl;
        $this->submissions = $submissions;
        $this->assignment = $assignment;
        $this->showparticipantnumber = $showparticipantnumber;
        $this->extensionsok = $extensionsok;
    }

    /**
     * Profile fields
     * @return array
     */
    protected function get_profilefields() {
        $fields = array_filter(explode(',', get_config('report_assign', 'profilefields')));
        $profilefields = [];
        foreach ($fields as $field) {
            $profilefields[] = get_string($field);
        }

        return $profilefields;
    }

    public function export_for_template(renderer_base $output) {
        global $CFG;

        // Group mode?
        $cm = get_coursemodule_from_instance('assign', $this->assignment->id);
        $groupmode = $cm->groupmode;
        $groups = groups_get_all_groups($this->course->id);

        return [
            'canrevealnames' => has_capability('report/assign:shownames', $this->context) && $this->assignment->blindmarking,
            'canexport' => has_capability('report/assign:export', $this->context),
            'baseurl' => $this->fullurl,
            'backurl' => new \moodle_url('/report/assign/index.php', ['id' => $this->course->id]),
            'submissions' => array_values($this->submissions),
            'assignment' => $this->assignment,
            'enableplagiarism' => !empty($CFG->enableplagiarism),
            'turnitinenabled' => \report_assign\lib::turnitin_enabled($this->assignment->id),
            'urkundenabled' => \report_assign\lib::urkund_enabled($this->assignment->id),
            'groupselect' => $groupmode != 0,
            'groups' => array_values($groups),
            'profilefields' => $this->get_profilefields(),
            'blindmarking' => $this->assignment->blindmarking,
            'extensionsok' => $this->extensionsok,
        ];
    }

}
