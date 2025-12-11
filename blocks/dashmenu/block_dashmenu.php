<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Block dashmenu is defined here.
 *
 * @package     block_dashmenu
 * @copyright   2024 Your Name <you@example.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_dashmenu extends block_base {

    /**
     * Initializes class member variables.
     */
    public function init() {
        // Needed by Moodle to differentiate between blocks.
        $this->title = get_string('pluginname', 'block_dashmenu');
    }

    /**
     * Returns the block contents.
     *
     * @return stdClass The block contents.
     */
    public function get_content() {
        global $DB, $USER, $CFG, $PAGE, $OUTPUT; 
        $PAGE->requires->css('/blocks/dashmenu/css/styles.css');
        

        if ($this->content !== null) {
            return $this->content;
        }

        if (empty($this->instance)) {
            $this->content = '';
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->items = array();
        $this->content->icons = array();
        $this->content->footer = '';

        if (!empty($this->config->text)) {
            $this->content->text = $this->config->text;
        } else {
            $sqlmgr = "SELECT * FROM mdl_role_assignments
                    WHERE contextid = 1 AND roleid = 1 AND userid =".$USER->id;
            $mgr = $DB->get_records_sql($sqlmgr);

            $courselist = $DB->get_records_sql("SELECT mc.id,mc.idnumber,mc.fullname,mc.startdate as startdate,DATEDIFF(DATE(FROM_UNIXTIME(mc.enddate)),DATE(FROM_UNIXTIME(mc.startdate)))+1 as course_duration, mc.visible, mc.id, fullname, mc.idnumber, shortname, mcc.name, mcc2.name as main_name,  DATE_FORMAT(FROM_UNIXTIME(mc.startdate), '%Y-%m-%d') as startdate, 
                            DATE_FORMAT(FROM_UNIXTIME(mc.enddate), '%Y-%m-%d') as enddate,
                                                        (
                                                            SELECT lra.roleid 
                                                            FROM mdl_course as c 
                                                            JOIN mdl_enrol AS me ON me.courseid = c.id 
                                                            JOIN mdl_user_enrolments AS mue ON mue.enrolid = me.id 
                                                            JOIN mdl_user AS mu ON mue.userid = mu.id
                                                            JOIN mdl_role_assignments AS lra ON lra.userid = mu.id
                                                            JOIN mdl_context AS ctx ON lra.contextid = ctx.id
                                                            WHERE c.id = mc.id 
                                                            AND lra.roleid IN (1,2,3,4) AND mu.id = $USER->id AND ctx.instanceid = c.id
                                                            LIMIT 1 
                                                        ) AS enrol_user_role
                                                        FROM mdl_course mc
                                                        JOIN mdl_course_categories mcc ON mc.category = mcc.id
                                            			JOIN mdl_course_categories mcc2 ON SUBSTRING_INDEX(SUBSTRING_INDEX(mcc.path, '/', 2),'/',-1) = mcc2.id
                                                        WHERE mc.visible = 1 AND mc.id !=1 AND mc.fullname NOT LIKE '%template%'
                                                        ORDER BY mc.startdate DESC");
                
                $show = 0;
                foreach($courselist as $courselists){
                    if($courselists->enrol_user_role != NULL){
                        $show = $show + 1;
                    }
                }
            $text  = '
                <style>
                    .profile-container {
                        position: relative;
                        display: inline-block;
                    }

                    .profile-image {
                        display: block;
                        width: 100px; /* Adjust the size as needed */
                        height: 100px;
                        border-radius: 50%;
                    }

                    .notification-badge {
                        position: absolute;
                        bottom:-10;
                        right: -20;
                        background-color: white;
                        color: red;
                        width: 30px;
                        height: 30px;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-size: 15px;
                        font-weight: bold;
                        border-style: solod;
                        border-color: red;
                        border-width: 5px;

                    }
                    
                    .notification-badges {
                        position: absolute;
                        bottom:-10;
                        right: -20;
                        background-color: white;
                        color: red;
                        width: 30px;
                        height: 30px; 
                        border-radius: 25%;                       
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-size: 15px;
                        font-weight: bold;
                        border-style: solod;
                        border-color: red;
                        border-width: 5px;

                    }
                </style>
            
            <h3>'.get_string('welcome', 'block_dashmenu').'<strong> '.$USER->firstname.' '.$USER->lastname.'</strong> '.get_string('to', 'block_dashmenu').' Integrated Command for Education, Training & Military Excellence System!</h3><hr>';
            $text .='
                <br>
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-2 text-center">
                            <div class="act-blue">
                                <br><img src="../blocks/dashmenu/icons/military.png" style = "display: block; margin-left: auto; margin-right: auto; width: 75px;" alt="trainee" >
                                <h5 class="card-header text-white">
                                    '.get_string('noofstudents', 'block_dashmenu').'
                                </h5>
                                <div class="card-body">
                                    <h1 class="card-header text-white">';
                                        $sql = "SELECT COUNT(DISTINCT(userid)) AS cnt 
                                            FROM mdl_role_assignments AS asg
                                            JOIN mdl_context AS context ON asg.contextid = context.id AND context.contextlevel = 50 
                                            WHERE asg.roleid = 5 ";
                                        $result = $DB->get_records_sql($sql);
                                        foreach ($result as $student){
                                           $text .= $student->cnt;
                                           $noofstudent =  $student->cnt;
                                        }                               
            $text .='
                                    </h1>
                                </div>
                                <div class="card-footer text-right text-white">';
                                    if($mgr OR is_siteadmin() OR $show>0){
                                    $text .='<a href = "'.new moodle_url('/local/trainee/').'" class ="linkicon"> <img src="../blocks/dashmenu/icons/list.png" style = "width: 36px;" alt="trainee" >
                                    </a>';
                                    }
            $text .='           </div>
                            </div>
                            <br>
                        </div>
                        
                        <div class="col-md-2 text-center">
                            <div class=" act-red">
                                <br><img src="../blocks/dashmenu/icons/military5.png" style = "display: block; margin-left: auto; margin-right: auto; width: 75px;" alt="trainee" >
                                <h5 class="card-header text-white">
                                    '.get_string('noofinst', 'block_dashmenu').'
                                </h5>
                                <div class="card-body">
                                    <h1 class="card-header text-white">';
                                        $sql = "SELECT COUNT(DISTINCT(userid)) AS cnt 
                                            FROM mdl_role_assignments AS asg
                                            JOIN mdl_context AS context ON asg.contextid = context.id AND context.contextlevel = 50 
                                            WHERE asg.roleid = 3 OR asg.roleid = 4";
                                        $result = $DB->get_records_sql($sql);
                                        foreach ($result as $staff){
                                           $noofstaff = $staff->cnt;
                                            
                                        }                                         
                                        $text .= $noofstaff;                           
            $text .='
                                    </h1>
                                </div>
                                <div class="card-footer text-right text-white">';
                                    if($mgr OR is_siteadmin()){
                                    $text .='<a href = "'.new moodle_url('/local/trainer/').'" class ="linkicon"> <img src="../blocks/dashmenu/icons/list.png" style = "width: 36px;" alt="trainee" >
                                    </a>';
                                    }
            $text .='           </div>
                            </div>
                            <br>
                        </div>
                        <div class="col-md-2 text-center">
                            <div class="act-darkblue">
                                <br><img src="../blocks/dashmenu/icons/barracks.png" style = "display: block; margin-left: auto; margin-right: auto; width: 75px;" alt="trainee" >
                                <h5 class="card-header text-white">
                                    '.get_string('nooffac', 'block_dashmenu').'
                                </h5>
                                <div class="card-body">
                                    <h1 class="card-header text-white">';
                                    $sql = "SELECT COUNT(id) AS cnt FROM mdl_course_categories WHERE parent = 0 AND visible = 1";
                                    $result = $DB->get_records_sql($sql);
                                    foreach ($result as $faculty){
                                        $text .= $faculty->cnt;
                                    } 
            $text .='        
                                    </h1>
                                </div>
                                <div class="card-footer text-right text-white">';
                                    if($mgr OR is_siteadmin()){
                                    $text .='<a href = "'.new moodle_url('/local/faculty_mgt/').'" class ="linkicon"> <img src="../blocks/dashmenu/icons/list.png" style = "width: 36px;" alt="trainee" >
                                    </a>';
                                    }
            $text .='           </div>
                            </div>
                            <br>
                        </div>
                        <div class="col-md-2 text-center">
                            <div class="act-yellow">
                                <br><img src="../blocks/dashmenu/icons/military4.png" style = "display: block; margin-left: auto; margin-right: auto; width: 75px;" alt="trainee" >
                                <h5 class="card-header text-white">
                                    '.get_string('noofcourse', 'block_dashmenu').'
                                </h5>
                                <div class="card-body">
                                    <h1 class="card-header text-white">';
                                    $sql = "SELECT COUNT(id) AS cnt FROM mdl_course mc WHERE mc.id !=1 AND mc.fullname NOT LIKE '%template%' AND mc.visible = 1 AND mc.category != 0 ";
                                    $result = $DB->get_records_sql($sql);
                                    foreach ($result as $course){
                                        $text .= $course->cnt;
                                    } 
            $text .='        
                                    </h1>
                                </div>
                                <div class="card-footer text-right text-white">';
                                    if($mgr OR is_siteadmin() OR $show>0){
                                    $text .='<a href = "'.new moodle_url('/local/course_mgt/').'" class ="linkicon"> <img src="../blocks/dashmenu/icons/list.png" style = "width: 36px;" alt="trainee" >
                                    </a>';
                                    }
            $text .='           </div>
                            </div>
                            <br>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="card text-white bg-primary">';
                                $parameters = array('size' => 50);
            $text .='           <br>'.$OUTPUT->user_picture($USER, $parameters).'
                                <h5 class="text-white">'.$USER->idnumber.' : '.$USER->firstname.' '.$USER->lastname. '</h5>

                                <h5 class="card-header text-white">
                                    '.get_string('mytask', 'block_dashmenu').'
                                </h5>
                                <div class="card-body">
                                    <div class="container-fluid text-white">
                                        <div class="card-header text-white row">
                                            <div class="col col-md-12">
                                                <div class="row">
                                                    <div class="col col-md-4">
                                                        <a href ="../blocks/exalib/index.php?courseid=1">
                                                        <img src="../blocks/dashmenu/icons/open-book.png" style = "display: block; margin-left: auto; margin-right: auto; width: 60px;" alt="trainee" title ="Digital Library"></a>
                                                    </div>
                                                    <div class="col col-md-4">
                                                        <a href ="../calendar/view.php?view=upcoming">
                                                        <img src="../blocks/dashmenu/icons/calendar.png" style = "display: block; margin-left: auto; margin-right: auto; width: 60px;" alt="trainee" title ="Calendar"></a>
                                                    </div> 
                                                    <div class="col col-md-4">
                                                        <a href ="../grade/report/overview/index.php">
                                                        <img src="../blocks/dashmenu/icons/grades.png" style = "display: block; margin-left: auto; margin-right: auto; width: 60px;" alt="trainee" title ="Grades"></a>
                                                    </div>  
                                                </div>                                                     
                                            </div>
                                        </div>  
                                    </div>
                                    <div class="container-fluid text-white">
                                        <div class="card-header text-white row">
                                            <div class="col col-md-12">
                                                <div class="row">
                                                    <div class="col col-md-4">
                                                    <div class="profile-container">
                                                            <a href ="../mod/customcert/my_certificates.php?userid='.$USER->id.'">
                                                        <img src="../blocks/dashmenu/icons/certificate.png" style = "display: block; margin-left: auto; margin-right: auto; width: 60px;" alt="trainee" title ="Certificate">
                                                        </a>';
                                                        $sqlcert = "SELECT COUNT(*) as cnt FROM mdl_customcert_issues WHERE userid =".$USER->id;
                                                        $resultcert = $DB->get_records_sql($sqlcert);
                                                        
                                                        foreach($resultcert as $count){
                                                            $cntcert = $count->cnt;
                                                        }
                                                        if($cntcert > 0){
                                                            $text .= '<div class="notification-badges">'.$cntcert.'</div>';
                                                        }

            $text .='                                </div>
                                                    </div> 
                                                    <div class="col col-md-4">
                                                        <div class="profile-container">
                                                            <a href ="../local/leave/">
                                                            <img src="../blocks/dashmenu/icons/leave.png" style = "display: block; margin-left: auto; margin-right: auto; width: 60px;" alt="trainee" title ="Leaves / Holiday">
                                                            </a>';
                                                            $t = time();
                                                            $t = $t-604800;
                                                            $sqlleave = "SELECT COUNT(*) as cnt FROM mdl_leave WHERE requester_id  =$USER->id AND updated_date >$t";
                                                        $resultleave = $DB->get_records_sql($sqlleave);
                                                        
                                                        foreach($resultleave as $count){
                                                            $cntleave = $count->cnt;
                                                        }
                                                        if($cntleave > 0){
                                                            $text .= '<div class="notification-badge">'.$cntleave.'</div>';
                                                        }
                                                    $text .=' </div>
                                                    </div> 
                                                    <div class="col col-md-4">
                                                                       
                                                        <div class="profile-container">';
                                                            $sqlattp = "SELECT count(u.id) as cnt, u.id, DATE_FORMAT(FROM_UNIXTIME(att.sessdate),'%d %M %Y') AS Date, mcm.id as mid
                                                                            FROM mdl_attendance_sessions AS att
                                                                            JOIN mdl_attendance_log      AS attlog ON att.id           = attlog.sessionid
                                                                            JOIN mdl_attendance_statuses AS attst  ON attlog.statusid  = attst.id
                                                                            JOIN mdl_attendance          AS a      ON att.attendanceid = a.id
                                                                            JOIN mdl_course              AS c      ON a.course         = c.id
                                                                            JOIN mdl_user                AS u      ON attlog.studentid = u.id
                                                                            JOIN mdl_course_modules		 AS mcm		ON c.id = mcm.course
                                                                            JOIN mdl_modules 			AS mm		ON mcm.module = mm.id
                                                                            WHERE attst.acronym = 'P'
                                                                            AND FROM_UNIXTIME('c.startdate','%Y-%m-%d') <= CURDATE()
                                                                            AND mm.name LIKE '%attendance%'
                                                                            AND u.id =".$USER->id."
                                                                            GROUP BY u.id";
                                                            
                                                            $resultattp = $DB->get_records_sql($sqlattp);
                                                            if ($resultattp){
                                                                foreach($resultattp as $count){
                                                                    $cntattp = $count->cnt;
                                                                    $mid = $count->mid;
                                                                }
                                                            }else{
                                                                $cntattp = 0;
                                                                $mid = 0;
                                                            }
                                                            
                                                            $sqlatta = "SELECT count(u.id) as cnt, u.id, DATE_FORMAT(FROM_UNIXTIME(att.sessdate),'%d %M %Y') AS Date, mcm.id as mid
                                                                            FROM mdl_attendance_sessions AS att
                                                                            JOIN mdl_attendance_log      AS attlog ON att.id           = attlog.sessionid
                                                                            JOIN mdl_attendance_statuses AS attst  ON attlog.statusid  = attst.id
                                                                            JOIN mdl_attendance          AS a      ON att.attendanceid = a.id
                                                                            JOIN mdl_course              AS c      ON a.course         = c.id
                                                                            JOIN mdl_user                AS u      ON attlog.studentid = u.id
                                                                            JOIN mdl_course_modules		 AS mcm		ON c.id = mcm.course
                                                                            JOIN mdl_modules 			AS mm		ON mcm.module = mm.id
                                                                            WHERE attst.acronym = 'A'
                                                                            AND FROM_UNIXTIME('c.startdate','%Y-%m-%d') <= CURDATE()
                                                                            AND mm.name LIKE '%attendance%'
                                                                            AND u.id =".$USER->id."
                                                                            GROUP BY u.id";
                                                            
                                                            $resultatta = $DB->get_records_sql($sqlatta);
                                                            if ($resultatta){
                                                                foreach($resultatta as $count){
                                                                    $cntatta = $count->cnt;
                                                                    $mid = $count->mid;
                                                                }
                                                            }else{
                                                                $cntatta = 0;   
                                                            }

                                                            if($mid == 0){
                                                                $text .=' <img src="../blocks/dashmenu/icons/attendance.png" style = "display: block; margin-left: auto; margin-right: auto; width: 60px;" alt="trainee" title ="Attendance">';
                                                            }
                                                            else{
                                                                $text .='<a href ="../mod/attendance/view.php?id='.$mid.'&mode=1"><img src="../blocks/dashmenu/icons/attendance.png" style = "display: block; margin-left: auto; margin-right: auto; width: 60px;" alt="trainee" title ="Attendance"></a>';
                                                            }
                                                                $text .= '<p><small class ="bg-white text-danger" style = "border-radius: 7%;">&nbsp; P = '.$cntattp.', A = '.$cntatta.'&nbsp;</small></p>';
            $text .='                                   </div>
                                                    </div>
                                                </div>                                                     
                                            </div>                                            
                                        </div> 
                                    </div>
                                    <br>
                                </div>                                
                            </div>
                            <br>
                        </div>
                    </div>
                </div>
            ';
            $this->content->text = $text;
        }

        return $this->content;
    }

    /**
     * Defines configuration data.
     *
     * The function is called immediately after init().
     */
    public function specialization() {

        // Load user defined title and make sure it's never empty.
        if (empty($this->config->title)) {
            $this->title = get_string('pluginname', 'block_dashmenu');
        } else {
            $this->title = $this->config->title;
        }
    }

    /**
     * Enables global configuration of the block in settings.php.
     *
     * @return bool True if the global configuration is enabled.
     */
    public function has_config() {
        return true;
    }

    /**
     * Sets the applicable formats for the block.
     *
     * @return string[] Array of pages and permissions.
     */
    public function applicable_formats() {
    //add after generate skeleton to appear in list of blocks
        return array('all' => true);
        
    }
    // add after generate skeleton to avoid install error
    function _self_test() {
        return true;
      }
}
