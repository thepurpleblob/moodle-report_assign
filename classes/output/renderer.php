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
 * This file contains functions used by the participation report
 *
 * @package    report
 * @subpackage assign
 * @copyright  2013 Howard Miller
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_assign\output;

defined('MOODLE_INTERNAL') || die;

use plugin_renderer_base;
use moodle_url;

class renderer extends plugin_renderer_base {

    /**
     * Render assignmentlist
     * @param renderer_base $listassign
     * @return string
     */
    public function render_listassign(listassign $listassign) {
        return $this->render_from_template('report_assign/listassign', $listassign->export_for_template($this));
    }

    /**
     * Render assignment report
     * @param renderer_base $reportassign
     * @return string
     */
    public function render_reportassign(reportassign $reportassign) {
        return $this->render_from_template('report_assign/reportassign', $reportassign->export_for_template($this));
    }

}

