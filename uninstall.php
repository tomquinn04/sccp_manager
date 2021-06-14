<?php
/* $Id:$ */

/* !TODO!: In an ideal world this should roll back everything the install.php script did, except for what existed before install.php was run */
/* !TODO!: This would require the install.php to make a note of all the actions that were skipped and/or performed */
/* !TODO!: Might be a good idea to create a backup of the database before removing anything */
// !TODO!: -TODO-: I remove only that which is related to the Manager, it is in my opinion not a critical configuration information. This information is partially present in other files.
/*
function CreateBackUpConfig() {
    global $amp_conf;
    outn("<li>" . _("Create Config BackUp") . "</li>");
    $cnf_int = \FreePBX::Config();
    $backup_files = array('extconfig','extconfig','res_mysql', 'res_config_mysql','sccp');
    $backup_ext = array('_custom.conf', '.conf');
    $dir = $cnf_int->get('ASTETCDIR');
    $zip = new \ZipArchive();
    $filename = $dir . "/sccp_uninstall_backup" . date("Ymd"). ".zip";
    if ($zip->open($filename, \ZIPARCHIVE::CREATE)) {
        foreach ($backup_files as $file) {
            foreach ($backup_ext as $b_ext) {
                if (file_exists($dir . '/'.$file . $b_ext)) {
                    $zip->addFile($dir . '/'.$file . $b_ext);
                }
            }
        }
        $zip->close();
    } else {
        outn("<li>" . _("Error Create BackUp: ") . $filename ."</li>");
    }
    outn("<li>" . _("Create Config BackUp: ") . $filename ."</li>");
}
*/
if (!defined('FREEPBX_IS_AUTH')) {
    die('No direct script access allowed');
}

global $db;
$version = FreePBX::Config()->get('ASTVERSION');
global $sqlTables;
$sqlTables = array('sccpbuttonconfig','sccpdevice','sccpline','sccpuser','sccpsettings','sccpdevmodel');
createBackUpConfig();

function createBackUpConfig()
{
    global $amp_conf;
    global $sqlTables;
    outn("<li>" . _("Creating Config BackUp") . "</li>");
    $cnf_int = \FreePBX::Config();
    $backup_files = array('extensions','extconfig','res_mysql', 'res_config_mysql','sccp','sccp_hardware','sccp_extensions');
    $backup_ext = array('_custom.conf', '_additional.conf','.conf');
    $dir = $cnf_int->get('ASTETCDIR');


    $sqlTables = array('sccpbuttonconfig','sccpdevice','sccpline','sccpuser','sccpsettings','sccpdevmodel');
    $sqlTablesString = implode(' ',$sqlTables);
    $sqlBuFile = $dir.'/sccp_backup_'.date("Ymd").'.sql';
    $result = exec("mysqldump {$amp_conf['AMPDBNAME']} {$sqlTablesString}
                  --password={$amp_conf['AMPDBPASS']}
                  --user={$amp_conf['AMPDBUSER']}
                  --single-transaction >{$sqlBuFile}");
    try {
        $zip = new \ZipArchive();
    } catch (\Exception $e) {
        outn("<br>");
        outn("<font color='red'>PHPx.x-zip not installed where x.x is the installed PHP version. Install it before continuing !</font>");
        die_freepbx();
    }
    $filename = $dir . "/sccp_uninstall_backup" . date("Ymd"). ".zip";
    if ($zip->open($filename, \ZIPARCHIVE::CREATE)) {
        foreach ($backup_files as $file) {
            foreach ($backup_ext as $b_ext) {
                if (file_exists($dir . '/'.$file . $b_ext)) {
                    $zip->addFile($dir . '/'.$file . $b_ext);
                }
            }
        }
        if (file_exists($sqlBuFile)) {
            $zip->addFile($sqlBuFile);
        }
        $zip->close();
    } else {
        outn("<li>" . _("Error Creating BackUp: ") . $filename ."</li>");
        outn("<br>");
        outn("<font color='red'>PHPx.x-zip not installed where x.x is the installed PHP version. Install it before continuing !</font>");
        die_freepbx();
    }
    unlink($sqlBuFile);
    outn("<li>" . _("Config backup created: ") . $filename ."</li>");
}

if (!empty($version)) {
    $check = $db->getRow("SELECT 1 FROM `kvstore` LIMIT 0", DB_FETCHMODE_ASSOC);
    if (!(DB::IsError($check))) {
        outn("<li>" . _("Deleting keys FROM kvstore..") . "</li>");
        sql("DELETE FROM kvstore WHERE module = 'sccpsettings'");
        sql("DELETE FROM kvstore WHERE module = 'Sccp_manager'");
    }
  }
  // FreePbx removes all tables via module.xml, and uninstaller will error
  // If the tables do not exist.
  //outn("<li>" . _('Removing all Sccp_manager tables') . "</li>");
  //foreach ($sqlTables as $table) {
      //$sql = "DROP TABLE IF EXISTS {$table}";
  //}
  outn("<li>" . _('Removing all Sccp_manager views') . "</li>");
  $db->query("DROP VIEW IF EXISTS sccpdeviceconfig");

  outn("<li>" . _("Uninstall Complete") . "</li>");
?>
