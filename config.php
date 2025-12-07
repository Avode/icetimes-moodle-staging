<?php  // Moodle configuration file

unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = 'mysqli';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'localhost';
$CFG->dbname    = 'icetimes_moodle_stag';
$CFG->dbuser    = 'icetimes_moodle';
$CFG->dbpass    = 'pt)](!4S8i5Yls!S';
$CFG->prefix    = 'mdlyf_';
$CFG->dboptions = array (
  'dbpersist' => 0,
  'dbport' => '',
  'dbsocket' => '',
  'dbcollation' => 'utf8mb4_0900_ai_ci',
);

$CFG->wwwroot   = 'https://stag.icetimes.my';
$CFG->dirroot   = '/home2/icetimes/public_html/staging';
$CFG->dataroot  = '/home2/icetimes/moodledata_staging';
$CFG->admin     = 'admin';

$CFG->directorypermissions = 0777;

require_once(__DIR__ . '/lib/setup.php');

// There is no php closing tag in this file,
// it is intentional because it prevents trailing whitespace problems!
