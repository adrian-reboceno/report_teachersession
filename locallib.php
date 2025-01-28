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
 * Version metadata for the report_teachersession plugin.
 *
 * @package   report_teachersession
 * @copyright 2024, Universindad Ciudadana de Nuevo leon {@link http://www.ucnl.edu.mx/}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Adrian Francisco Lozada Reboceño <adrian.lozada@ucnl.edu.mx>
 */


define('REPORT_TEACHERSESSION_ACTION_LOAD_FILTER', 'loadfilter');
define('REPORT_TEACHERSESSION_ACTION_QUICK_FILTER', 'quickfilter');
define('FIXED_NUM_COLS', 6);

require_once('../../config.php');
require($CFG->libdir . '/phpspreadsheet/vendor/autoload.php');
    
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use core\notification;

use report_teachersession\forms\filters as form;


function report_teachersession_filter_form_action($filterid = null, $data = [], $quickfilter = false) {
    global $CFG, $PAGE, $USER;
    
    $customdata = [           
        'quickfilter' => $quickfilter,
        'userid'      => (int)$USER->id // For the hidden userid field.
    ];
    $customdata = array_merge($customdata, $data);
    $action     = $quickfilter ? REPORT_TEACHERSESSION_ACTION_QUICK_FILTER : REPORT_TEACHERSESSION_ACTION_LOAD_FILTER;
    $filterform = new form($PAGE->url->out(false) . '?action=' . $action, $customdata);

    return $filterform;
}

function report_teachersession_get_lastaccess($filter){
    global $DB, $USER;
    $plugin = 'report_teachersession';
    $columns = [
        get_string('id',  $plugin),
        get_string('firstname'),
        get_string('lastname'),
        get_string('email'),
        get_string('courseid',  $plugin),        
        get_string('fullname'),
        get_string('shortname'),
        get_string('category'),
        get_string('startdate'),
        get_string('enddate'),
        get_string('lastaccess'),
        get_string('courseaccesslast', $plugin),
        get_string('activity', $plugin),
        get_string('daysinactivity', $plugin),
        get_string('totalstudents', $plugin),
        get_string('totalstudentslogin', $plugin),
        get_string('totalstudentsnotlogin', $plugin),
    ];
    $datarows['thead'] = $columns;
    if(!empty($filter->period)){       
        $period = (int)$filter->period;
        $where= "(cats.path LIKE '%/{$period}%' OR cats.path LIKE '%/{$period}' )";
    }
    if(!empty($filter->program)){                   
        $programa= (int)$filter->program;
        $where= "(cats.path LIKE '%/{$programa}%' OR cats.path LIKE '%/{$programa}' )";
    }
    if(!empty($filter->semester)){        
        $semester = (int)$filter->semester;
        $where= "(cats.path LIKE '%/{$semester}%' OR cats.path LIKE '%/{$semester}' )";
    }
    if(!empty($filter->course)){
        $params['course'] = $filter->course;
        $where = "c.id = :course";
    }
    if(!empty($filter->teacher)){
        $params ['teacher'] = $filter->teacher;  
        $where = "u.id = :teacher";
    }
    if (!empty($where)) {
        $where = '  '. $where;
    } else {
        $where = '';
    }
    
    $sql = "SELECT
    u.id as id,  u.firstname , u.lastname , u.email,
    c.id as idcurso, c.fullname, c.shortname, cats.name AS namecategory, c.startdate, c.enddate, u.lastaccess,   
    (SELECT  timeaccess FROM {user_lastaccess}
    WHERE userid=u.id and courseid=c.id
    ) AS timeaccess,

    (SELECT 
    COUNT(asg.userid) AS allstudents 
    FROM {role_assignments} AS asg 
    JOIN {context} AS context ON asg.contextid = context.id AND context.contextlevel = 50 
    JOIN {user} AS u ON u.id = asg.userid 
    JOIN {course} AS course ON context.instanceid = course.id WHERE asg.roleid = 5
    AND course.id = c.id) AS totalstudents

    
    FROM {course} as c  
    LEFT JOIN {context} AS ctx ON c.id = ctx.instanceid
    JOIN {role_assignments} AS lra ON lra.contextid = ctx.id
    JOIN {user} AS u ON  lra.userid= u.id
    JOIN {course_categories} AS cats ON c.category = cats.id 
    WHERE 
    --c.category = cats.id 
    {$where}
	AND lra.roleid IN (3)
    GROUP BY u.id, u.firstname , u.lastname , u.email, c.id , c.fullname, c.shortname, cats.name, c.startdate, c.enddate, u.lastaccess
    ";  
    $data = $DB->get_records_sql($sql, $params);
    $day  =  date("Y-m-d H:i:s");
    $daytotal=0;
    $day = new DateTime (substr($day,0,10));
    $daysinactivitystudents = get_config('report_ucnl', 'daysinactivityteachers');
    foreach ($data as $key => $value) {
        $daytotal=0;
        $row['id'] = $value->id;
        $row['firstname'] = $value->firstname;
        $row['lastname'] = $value->lastname;
        $row['email'] = $value->email;
        $row['idcurso'] = $value->idcurso;
        $row['fullname'] = $value->fullname;
        $row['shortname'] = $value->shortname;
        $row['namecategory'] = $value->namecategory;
        $row['startdate'] =  date('Y-m-d H:i:s', $value->startdate);
        $row['enddate'] =  date('Y-m-d H:i:s', $value->enddate);
        $row['lastaccess'] = $value->lastaccess ? date('Y-m-d H:i:s', $value->lastaccess) : 'No ingresado';    
        $row['timeaccess'] = $value->timeaccess ? date('Y-m-d H:i:s', $value->timeaccess) : 'No ingresado';
        $fechafinaliza = new DateTime (substr(date('Y-m-d H:i:s', $value->timeaccess), 0, 10));                         
        $diff = $day->diff($fechafinaliza);
        if(empty($value->timeaccess)){
            $row['activity'] = get_string('inactive','report_studentsession');
            $qualified = get_config('report_ucnl', 'notqualified');
            $row['qualified'] = $qualified;
            $row['diff'] = 0;
        }else{
            $daytotal = $diff->days;            
            if($daytotal > $daysinactivitystudents){
                $row['activity'] = get_string('inactive','report_studentsession');
                $qualified = get_config('report_ucnl', 'notqualified');
                $row['qualified'] = $qualified;
            }elseif($daytotal> 3 && $daytotal <= 6){
                $row['activity'] = get_string('active','report_studentsession');
                $qualified = get_config('report_ucnl', 'mediumqualified');
                $row['qualified'] = $qualified;
            }elseif($daytotal >= 1 && $daytotal <= 3){
                $row['activity'] = get_string('veryactive','report_studentsession');
                $qualified = get_config('report_ucnl', 'qualified');
                $row['qualified'] = $qualified;
            }elseif($daytotal == 0){
                $row['activity'] = get_string('veryactive','report_studentsession');
                $qualified = get_config('report_ucnl', 'qualified');
                $row['qualified'] = $qualified;
            }
            $row['diff'] = $daytotal;
        }

        $sqlloing = 'SELECT
                count( distinct (u.id))
                FROM {course} as c  
                LEFT JOIN {context} AS ctx ON c.id = ctx.instanceid
                JOIN {role_assignments} AS lra ON lra.contextid = ctx.id
                JOIN {user} AS u ON  lra.userid= u.id
                JOIN {course_categories} AS cats ON c.category = cats.id 
                JOIN {user_lastaccess} as lasta on lasta.userid = u.id and lasta.courseid=c.id
                WHERE c.category = cats.id AND c.id=:courseid AND lra.roleid=5  group by c.id';
        $toallogin = $DB->get_field_sql($sqlloing, array('courseid'=>$value->idcurso));
        $row['totalstudents'] = $value->totalstudents;
        $row['studentslogin'] = $toallogin;
        $row['studentsnotlogin'] = $value->totalstudents - $toallogin;
        $datarows['rows'][] = $row;
    }
    return $datarows;
}

function report_excel($data){
    global $CFG, $DB;
    $now = time();
    $spread = new Spreadsheet();

    $spread->getActiveSheet()->getStyle('A1:Q1')->getFont()->setBold(true);
    foreach (range('A','Q') as $col) {
        $spread->getActiveSheet()->getColumnDimension($col)->setAutoSize(true);
    }
    foreach($data['thead'] as $key => $value){
        $spread->setActiveSheetIndex(0)->setCellValueByColumnAndRow($key+1, 1, $value);
    }
    foreach($data['rows'] as $key => $value){
        if($value['qualified']){
            $spread->getActiveSheet()->getStyle('L'. $key+2)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB( strtoupper(str_replace('#','',$value['qualified'])));
            $spread->getActiveSheet()->getStyle('M'. $key+2)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB( strtoupper(str_replace('#','',$value['qualified'])));
            $spread->getActiveSheet()->getStyle('N'. $key+2)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB( strtoupper(str_replace('#','',$value['qualified'])));
        }
        $spread->setActiveSheetIndex(0)->setCellValueByColumnAndRow(1, $key+2, $value['id']);
        $spread->setActiveSheetIndex(0)->setCellValueByColumnAndRow(2, $key+2, $value['firstname']);
        $spread->setActiveSheetIndex(0)->setCellValueByColumnAndRow(3, $key+2, $value['lastname']);
        $spread->setActiveSheetIndex(0)->setCellValueByColumnAndRow(4, $key+2, $value['email']);
        $spread->setActiveSheetIndex(0)->setCellValueByColumnAndRow(5, $key+2, $value['idcurso']);
        $spread->setActiveSheetIndex(0)->setCellValueByColumnAndRow(6, $key+2, $value['fullname']);
        $spread->setActiveSheetIndex(0)->setCellValueByColumnAndRow(7, $key+2, $value['shortname']);
        $spread->setActiveSheetIndex(0)->setCellValueByColumnAndRow(8, $key+2, $value['namecategory']);
        $spread->setActiveSheetIndex(0)->setCellValueByColumnAndRow(9, $key+2, $value['startdate']);
        $spread->setActiveSheetIndex(0)->setCellValueByColumnAndRow(10, $key+2, $value['enddate']);
        $spread->setActiveSheetIndex(0)->setCellValueByColumnAndRow(11, $key+2, $value['lastaccess']);
        $spread->setActiveSheetIndex(0)->setCellValueByColumnAndRow(12, $key+2, $value['timeaccess']);
        $spread->setActiveSheetIndex(0)->setCellValueByColumnAndRow(13, $key+2, $value['activity']);
        $spread->setActiveSheetIndex(0)->setCellValueByColumnAndRow(14, $key+2, $value['diff']);
        $spread->setActiveSheetIndex(0)->setCellValueByColumnAndRow(15, $key+2, $value['totalstudents']);
        $spread->setActiveSheetIndex(0)->setCellValueByColumnAndRow(16, $key+2, $value['studentslogin']);
        $spread->setActiveSheetIndex(0)->setCellValueByColumnAndRow(17, $key+2, $value['studentsnotlogin']);
    }
    $spread->getActiveSheet()->setTitle(get_string('pluginname', 'report_teachersession'));
    $writer = new Xlsx($spread);
    $filename = 'report_'.$now.'.xlsx'; 
    $filename = 'export/'.$filename;  
    $writer->save($filename);
    return $filename;
}


