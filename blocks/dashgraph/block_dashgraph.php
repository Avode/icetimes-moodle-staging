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
 * Block dashgraph is defined here.
 *
 * @package     block_dashgraph
 * @copyright   2024 Your Name <you@example.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_dashgraph extends block_base {

    /**
     * Initializes class member variables.
     */
    public function init() {
        // Needed by Moodle to differentiate between blocks.
        $this->title = get_string('pluginname', 'block_dashgraph');
    }

    /**
     * Returns the block contents.
     *
     * @return stdClass The block contents.
     */
    public function get_content() {
        global $DB, $USER, $CFG, $PAGE;
        $PAGE->requires->css('/blocks/dashgraph/css/styles.css');
        $PAGE->requires->js('/blocks/dashgraph/js/main.js');
        $PAGE->requires->js('/blocks/dashgraph/js/export.js');

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
           $sql1 = "SELECT course.id AS cid, course.fullname AS fullname, 
                    context.id as contextid, 
                    COUNT(course.id) AS students
                    FROM mdl_role_assignments AS asg
                    JOIN mdl_context AS context ON asg.contextid = context.id AND context.contextlevel = 50
                    JOIN mdl_user AS user ON user.id = asg.userid 
                    JOIN mdl_course AS course ON context.instanceid = course.id
                    WHERE asg.roleid = 5 GROUP BY course.id ORDER BY COUNT(course.id) DESC";
            
            $sql2 = "SELECT mcc.name as name, count(mc.id) as number, mcc2.name as main_name 
                FROM mdl_course mc
                JOIN mdl_course_categories mcc ON mc.category = mcc.id
                JOIN mdl_course_categories mcc2 ON SUBSTRING_INDEX(SUBSTRING_INDEX(mcc.path, '/', 2),'/',-1) = mcc2.id
                WHERE mc.id !=1 AND mc.fullname NOT LIKE '%template%'
                GROUP BY SUBSTRING_INDEX(SUBSTRING_INDEX(mcc.path, '/', 2),'/',-1)
                ";  
            $text  ='
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="row">
                                <div class="col-md-12 text-center">
                                <h3>'.get_string('graph1title', 'block_dashgraph').'</h3>';
                                $result2 = $DB->get_records_sql($sql2); 
                                ?>
                                <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>  
                                <script type="text/javascript">  
                                google.charts.load('current', {'packages':['corechart']});  
                                google.charts.setOnLoadCallback(drawChart);  
                                function drawChart()  
                                {  
                                        var data = google.visualization.arrayToDataTable([  
                                                ['Faculty', 'Number'],  
                                                <?php  
                                                foreach($result2 as $courses)
                                                {  
                                                    echo "['".$courses->main_name."', ".$courses->number."],";  
                                                }  
                                                ?>  
                                            ]);  
                                        var options = {  
                                            title: 'Number of courses per Faculty',  
                                            //is3D:true,  
                                            pieHole: 0  
                                            };  
                                        var chart = new google.visualization.PieChart(document.getElementById('piechart'));  
                                        chart.draw(data, options);  
                                }  
                                </script> 
                                <?php
                                $text .='
                                    <!-- chart -->
                                <div style="width:900px;">  
                                        
                                        <br />  
                                        <div id="piechart" style="width: 100%; height: 500px;"></div>  
                                </div> 
                                ';
            $text .='
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">';
                                $sql3   = "SELECT mc.id, fullname, mc.idnumber, shortname, mcc.name, mcc2.name as main_name 
                                            FROM mdl_course mc
                                            JOIN mdl_course_categories mcc ON mc.category = mcc.id
                                            JOIN mdl_course_categories mcc2 ON SUBSTRING_INDEX(SUBSTRING_INDEX(mcc.path, '/', 2),'/',-1) = mcc2.id
                                            WHERE mc.id !=1 AND mc.fullname NOT LIKE '%template%'
                                            ORDER BY mcc2.id";
                                $result3 = $DB -> get_records_sql($sql3);
                                
                                $text .='
                                <!-- Button trigger modal -->
                                <div align = "right">
                                    <button type="button" class="btn btn-info" data-toggle="modal" data-target="#ModalData1">
                                    View Data
                                    </button>
                                </div>
                                <!-- Modal -->
                                <div class="modal fade" id="ModalData1" tabindex="-1" aria-labelledby="ModalData1Label" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
                                        <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="ModalData1Label">Course By Faculty</h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <h3 aria-hidden="true">&times;</h3>
                                            </button>
                                        </div>
                                        <div class="modal-body">';
                                        $table   = '<table class="table table-striped" style="width:100%">
                                        <thead class="thead-dark">
                                            <tr>
                                                <th>'.get_string("number", "block_dashgraph").'</th>
                                                <th>'.get_string("faculty", "block_dashgraph").'</th>
                                                <th>'.get_string("cat", "block_dashgraph").'</th>
                                                <th>'.get_string("course_name", "block_dashgraph").'</th>
                                            </tr>
                                        </thead>
                                        <tbody>';
                                    
                                        $counter = 1;
                                        foreach($result3 as $courses){
                                            $table   .= '<tr>
                                                    <td>'.$counter++.'</td>
                                                    <td>'.$courses->main_name.'</td>
                                                    <td>'.$courses->name.'</td>
                                                    <td><a target="_new" href="../course/view.php?id='.$courses->id.'">'.$courses->fullname.'</a></td>
                                                    
                                                </tr>';         
                                        }
                                        $table   .= '</tbody></table>';
                                        $sql4 = "SELECT id, name FROM mdl_course_categories mcc WHERE mcc.parent = 0";
                                        $result4 = $DB->get_records_sql($sql4); 
                                        $filter1 = '
                                        <div class ="fliter">
                                            <span >Select Faculty :</span>
                                            <select name ="status" id = "status">';
                                            foreach($result4 as $option){
                                                $filter1 .= '
                                                <option value = "'.$option->id.'">'.$option->name.'</option>';
                                            }
                                            $filter1 .= '
                                                <option value = "-1">All Faculty</option>
                                            </select>
                                        </div>
                                        ';
                                        $text   .= $filter1;
                                        $text .= '<div class = "resultset">'.$table .'</div>';   
                                            $text .='</div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                    </div>
                                </div>
                                ';
                                $text .='</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="row">
                                <div class="col-md-12 text-center">
                                <h3>'.get_string('graph2title', 'block_dashgraph').'</h3>';
                                $result1 = $DB->get_records_sql($sql1); 
                                ?>
                                <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>  
                                <script type="text/javascript">  
                                google.charts.load('current', {'packages':['corechart']});  
                                google.charts.setOnLoadCallback(drawChart);  
                                function drawChart()  
                                {  
                                        var data = google.visualization.arrayToDataTable([  
                                                ['Course', 'Students'],  
                                                <?php  
                                                foreach($result1 as $courses)
                                                {  
                                                    echo "['".$courses->fullname."', ".$courses->students."],";  
                                                }  
                                                ?>  
                                            ]);  
                                        var options = {  
                                            title: 'Number of Students per Course',  
                                            //is3D:true,  
                                            pieHole: 0  
                                            };  
                                        var chart = new google.visualization.ColumnChart(document.getElementById('columnchart'));  
                                        chart.draw(data, options);  
                                }  
                                </script> 
                                <?php
            $text .='
                                <!-- chart -->
                                <div style="width:90%;">  
                                        
                                        <br />  
                                        <div id="columnchart" style="width: 100%; height: 500px;"></div>  
                                </div> 
                                ';
                            
            $text  .='
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12 text-center">
                                <!-- Button trigger modal -->
                                <div align = "right">
                                    <button type="button" class="btn btn-info" data-toggle="modal" data-target="#ModalData2">
                                    View Data
                                    </button>
                                </div>

                                <!-- Modal -->
                                <div class="modal fade" id="ModalData2" tabindex="-1" aria-labelledby="ModalData2Label" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
                                    <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="ModalData2Label">Trainees by Course</h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">';
                                    $result = $DB -> get_records_sql($sql1);
                                    $text   .= '
                                        <br>
                                        <table class="table table-striped" style ="width:95%;">
                                        <thead class="thead-dark">
                                            <tr>
                                                <th>'.get_string("number", "block_dashgraph").'</th>
                                                <th style = "width:70%" >'.get_string("course", "block_dashgraph").'</th>
                                                <th>'.get_string("nostudent", "block_dashgraph").'</th>
                                            </tr>
                                        </thead>
                                        <tbody>';
                                    
                                    $counter = 1;
                                    foreach($result as $courses){
                                        $text   .= '<tr>
                                                <td>'.$counter++.'</td>
                                                <td style = "width:70%">
                                                    <a target="_new" href="../course/view.php?id='.$courses->cid.'">'.$courses->fullname.'</a>
                                                </td>
                                                <td>
                                                    <a target="_new" href="../user/index.php?contextid='.$courses->contextid.'">'.$courses->students.'</a>                                            
                                                </td>
                                            </tr>';         
                                    }
                                    $text   .= '</tbody></table>';
                        $text .=    '</div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                    </div>
                                    </div>
                                </div>
                                </div>
                                ';
                                
                    $text .=    '</div>
                            </div>
                        </div>
                    </div>
                </div>
            '
            ;
            
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
            $this->title = get_string('pluginname', 'block_dashgraph');
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
