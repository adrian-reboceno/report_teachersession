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
 * Version metadata for the report_studentsession plugin.
 *
 * @package   report_studentsession
 * @copyright 2024, Universindad Ciudadana de Nuevo leon {@link http://www.ucnl.edu.mx/}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Adrian Francisco Lozada Reboceño <adrian.lozada@ucnl.edu.mx>
 */

require(__DIR__.'/../../config.php');
defined('MOODLE_INTERNAL') || die();

require_login();
$context = context_system::instance();

$category = optional_param('category_id', 0, PARAM_INT);

$data = getcourses($category);
foreach ($data as $key => $value) {
    echo "<option value='$key'>$value</option>";
}

function getcourses($category) {
    global $DB;
    $sql = "SELECT id, fullname FROM {course} WHERE category = :category";
    $params = array('category' => $category);
    $courses = $DB->get_records_sql($sql, $params);
    $data[0] = 'Todos';
    foreach ($courses as $course) {
        $data[$course->id] = $course->fullname;
    }
    return $data;
}