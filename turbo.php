<?php

/**
 * Opencart Turbo
 * Developed by Atomix
 * Version: 0.1
 * http://www.atomix.com.au
 *
 * This script will apply several changes to boost the performance of OpenCart, including:
 * 1) DONE: Convert MySQL DB Storage Engine from MyISAM to InnoDB
 * 2) DONE: Add indexes to all foreign keys
 * 3) TODO: Delete Script Function
 * 4) TODO: Replace config.php and admin/config.php with dynamic Git-friendly version
 *
 * NOTES:
 * 1) This script should be deleted immediately following use
 * 2) This script should be run again following OpenCart upgrades
 */

define('GITHUB_URL','https://github.com/chrisatomix/opencart-turbo/');

/**
 * List of Additional Columns that should be indexed (in the format tablename.columname)
 * NOTE: Exclude any columns that end with '_id' here
 */
$index_list   = array();
$index_list[] = 'product.model';



$action = (!empty($_REQUEST['action'])) ? $_REQUEST['action'] : '';

if(file_exists('./config.php')) {
  require_once './config.php';
}
else {

  die("Aborting: config.php not found!");
}
if(!$db = turbo_db_connect()) {
  die("Unable to connect to DB - Check Settings");
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Opencart Turbo</title>
  <link href="//netdna.bootstrapcdn.com/bootstrap/3.0.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <a href="<?php echo GITHUB_URL; ?>"><img style="position: absolute; top: 0; right: 0; border: 0;" src="https://s3.amazonaws.com/github/ribbons/forkme_right_red_aa0000.png" alt="Fork me on GitHub"></a>
  <br>
  <div class="container">
    <div class="well">

      <h2>Opencart Turbo<br><small>Developed by <a href="http://www.atomix.com.au">Atomix</a></h2>

      <p>
        This script will apply several changes to boost the performance of OpenCart, including:<br>
        <ul>
          <li>Convert MySQL DB Storage Engine from MyISAM to InnoDB</li>
          <li>Add indexes to all foreign keys</li>
          <li>More to come...</li>
        </ul>
        <strong>Notes:</strong><br>
        <ul>
          <li>This script should be deleted immediately following use</li>
          <li>This script should be run again following OpenCart upgrades</li>
          <li>Updates can be found at GitHub: <a href="<?php echo GITHUB_URL; ?>" target="_blank"><?php echo GITHUB_URL; ?></a></li>
        </ul>
      </p>
    </div>

    <div class="panel panel-primary">
      <div class="panel-heading">
        <h3 class="panel-title">Available Options</h3>
      </div>
      <div class="panel-body">
        <a href="turbo.php?action=engine" class="btn btn-success btn-lg" onclick="return confirm('Are you sure you want to convert your Opencart database tables from MyISAM to InnoDB?');">Convert Database Engine</a><br><br>
        <a href="turbo.php?action=indexes" class="btn btn-success btn-lg" onclick="return confirm('Are you sure you want to add Indexes to your Opencart database tables?');">Add Database Indexes</a>
      </div>
    </div>

    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">Output</h3>
      </div>
      <div class="panel-body">
        <p><?php
          switch($action) {
            case 'engine':
              turbo_switch_engine();
              break;
            case 'indexes':
              turbo_table_indexes();
              break;
            case 'delete':
              // Nothing yet
              break;
            default:
              // Nothing yet
              break;
          }
          ?></p>
      </div>
    </div>

  </div>
  <script src="//netdna.bootstrapcdn.com/bootstrap/3.0.2/js/bootstrap.min.js"></script>
</body>
</html>
<?php



function turbo_table_indexes() {
  global $db, $index_list;

  $tables = turbo_get_tables(true);
  if($tables && count($tables) > 0) {

    turbo_log("Adding Indexes to Tables");

    // Loop through Tables
    foreach($tables as $table_name => $table) {

      // Loop through Columns
      foreach($table['columns'] as $column_name => $column) {

        $has_index   = false;
        $needs_index = false;

        // Does this column need an index?
        if(substr($column_name, -3) == '_id') {
          // Column ends in '_id'
          $needs_index = true;
        }
        elseif(in_array($table_name.'.'.$column_name, $index_list)) {
          // This column exists in the manual index list
          $needs_index = true;
        }

        // Loop through the indexes for this column to determine if it has one already
        if($column['indexes'] && !empty($column['indexes'])) {

          foreach($column['indexes'] as $index) {

            if($index['position'] == 1) {
              // This column is in first position in an Index
              $has_index = true;
            }
          }
        }

        if(!$has_index && $needs_index) {
          // Has no Index and needs an Index
          $sql = "ALTER TABLE `{$table_name}` ADD INDEX (  `{$column_name}` )";
          if($output = $db->query($sql)) {
            turbo_log("{$table_name}.{$column_name} - Index Added",'success','SUCCESS');
          }
          else {
            turbo_log("{$table_name}.{$column_name} - Index Add Failed - ".$db->error,'danger','ERROR');
          }
        }
        elseif($needs_index) {
          // Needs an Index but already has one
          turbo_log("{$table_name}.{$column_name} - Index Already Exists",'info','INFO');
        }
      }
    }

  }
  else {
    turbo_log("Aborting",'danger','ERROR');
  }
}

function turbo_switch_engine() {
  global $db;

  $tables = turbo_get_tables();
  if($tables && count($tables) > 0) {

    turbo_log("Switching DB Table Engines");

    foreach ($tables as $table_name => $table) {

      if($table['engine'] != 'InnoDB') {

        $sql = "ALTER TABLE `{$table_name}` ENGINE = INNODB";
        if($rs = $db->query($sql)) {
          turbo_log("{$table_name} Converted from {$table['engine']} to InnoDB",'success','SUCCESS');
        }
        else {
          turbo_log("{$table_name} Engine Switch Failed - ".$db->error,'danger','ERROR');
        }
      }
      else {
        turbo_log("{$table_name} Already InnoDB",'info','SKIP');
      }
    }
  }
  else {
    turbo_log("Aborting",'danger','ERROR');
  }
}


function turbo_get_tables($getindexes=false) {
  global $db;
  $tables = false;
  $sql = "SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA LIKE '".DB_DATABASE."'";
  if($rs = $db->query($sql)) {

    if($rs->num_rows > 0) {
      // Table list loaded
      turbo_log("{$rs->num_rows} Tables Found");
      $tables = array();
      while ($row = $rs->fetch_assoc()) {

        $table               = array();
        $table['name']       = $row['TABLE_NAME'];
        $table['engine']     = $row['ENGINE'];
        $table['columns']    = false;

        if($getindexes) {

          // Get indexes first
          $sqli = "SELECT *
                  FROM INFORMATION_SCHEMA.STATISTICS
                  WHERE TABLE_SCHEMA LIKE '".DB_DATABASE."'
                  AND TABLE_NAME LIKE '".$table['name']."'";
          $table['indexes'] = array();
          if($rsi = $db->query($sqli)) {

            while($indexes = $rsi->fetch_assoc()) {

              $index             = array();
              $index['name']     = $indexes['COLUMN_NAME'];
              $index['key']      = $indexes['INDEX_NAME'];
              $index['unique']   = ($indexes['NON_UNIQUE'] == 1) ? false : true; // Invert logic
              $index['position'] = $indexes['SEQ_IN_INDEX'];

              if(!isset($table['indexes'][$index['name']])) {
                $table['indexes'][$index['name']] = array();
              }
              $table['indexes'][$index['name']][] = $index;
            }
          }

          // Get Columns
          $sqlc = "SELECT *
                  FROM INFORMATION_SCHEMA.COLUMNS
                  WHERE TABLE_SCHEMA LIKE '".DB_DATABASE."'
                  AND TABLE_NAME LIKE '".$table['name']."'";

          if($rsc = $db->query($sqlc)) {

            $table['columns'] = array();
            while($columns = $rsc->fetch_assoc()) {

              $column            = array();
              $column['name']    = $columns['COLUMN_NAME'];
              $column['type']    = $columns['DATA_TYPE'];
              $column['indexes'] = false;

              if(isset($table['indexes'][$column['name']])) {
                // If there are any Indexes for this column, add to Array
                $column['indexes'] = $table['indexes'][$column['name']];
              }
              $table['columns'][$column['name']] = $column;
            }
          }
          else {
            turbo_log("No DB Columns Found in Table {$table['name']}",'danger','ERROR');
          }
        }
        $tables[$table['name']] = $table;
      }
    }
    else {
      // No tables found
      turbo_log("No DB Tables Found",'danger','Error');
    }

  }
  else {
    turbo_log("Error: Unable to retrieve DB Table List");
  }
  return $tables;
}



function turbo_log($input,$type='default',$label='') {

  if($label) {
    echo '<span class="label label-'.$type.'">'.$label.'</span> ';
  }
  echo $input."<br>";
}

/**
 * Connect to Database using Config Settings
 * @return MySQLi Connection Object
 */
function turbo_db_connect() {
  $db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
  return $db;
}

/* End of File */