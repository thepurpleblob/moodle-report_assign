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
 * Web service for report assign
 * @package    report_assign
 * @subpackage db
 * @copyright  2020 Howard Miller
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$functions = array(

'report_assign_save_user_extension' => array(
                'classname' => 'report_assign_external',
                'methodname' => 'save_user_extension',
                'classpath' => 'report/assign/externallib.php',
                'description' => 'Save assignment extension',
                'type' => 'write',
                'ajax' => true,
        ),

'report_assign_get_user_flags' => array(
            'classname' => 'report_assign_external',
            'methodname' => 'get_user_flags',
            'classpath' => 'report/assign/externallib.php',
            'description' => 'Get user flags',
            'type' => 'read',
            'ajax' => true,
    ),

);