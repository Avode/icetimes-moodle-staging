<?php

require_once(__DIR__ . '/../../config.php');
require_login();
global $DB, $USER;

$selected_status   = optional_param('selected_status', null, PARAM_INT);    // Turn editing on and off

if($selected_status==-1){
    $sql   = "SELECT mc.id, fullname, mc.idnumber, shortname, mcc.name, mcc2.name as main_name 
    FROM mdl_course mc
    JOIN mdl_course_categories mcc ON mc.category = mcc.id
    JOIN mdl_course_categories mcc2 ON SUBSTRING_INDEX(SUBSTRING_INDEX(mcc.path, '/', 2),'/',-1) = mcc2.id
    WHERE mc.id !=? AND mc.fullname NOT LIKE '%template%'
    ORDER BY mcc2.id";
    $records = $DB->get_records_sql($sql, array(1));
}else{
    $sql   = "SELECT mc.id, fullname, mc.idnumber, shortname, mcc.name, mcc2.name as main_name 
    FROM mdl_course mc
    JOIN mdl_course_categories mcc ON mc.category = mcc.id
    JOIN mdl_course_categories mcc2 ON SUBSTRING_INDEX(SUBSTRING_INDEX(mcc.path, '/', 2),'/',-1) = mcc2.id
    WHERE mc.id !=? AND SUBSTRING_INDEX(SUBSTRING_INDEX(mcc.path, '/', 2),'/',-1) = ? AND mc.fullname NOT LIKE '%template%'
    ORDER BY mcc2.id";
    $records = $DB->get_records_sql($sql, array(1, $selected_status));
}

$table   = '<table class="table table-striped" style ="width:95%;">
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
foreach($records as $courses){
    $table   .= '<tr>
            <td>'.$counter++.'</td>
            <td>'.$courses->main_name.'</td>
            <td>'.$courses->name.'</td>
            <td><a target="_new" href="../course/view.php?id='.$courses->id.'">'.$courses->fullname.'</a></td>
            
        </tr>';         
}
$table   .= '</tbody></table>';

echo $table;
die;