<?php
if (!defined('__DIR__')) define('__DIR__', dirname(__FILE__));
require_once(__DIR__ . '/../approot.inc.php');
require_once(APPROOT . '/application/application.inc.php');
//require_once(APPROOT . '/application/nicewebpage.class.inc.php');
//require_once(APPROOT . '/application/webpage.class.inc.php');
require_once(APPROOT . '/application/clipage.class.inc.php');
require_once(APPROOT . '/core/background.inc.php');

const EXIT_CODE_ERROR = -1;
const EXIT_CODE_FATAL = -2;

$sConfigFile = APPCONF . ITOP_DEFAULT_ENV . '/' . ITOP_CONFIG_FILE;
if (!file_exists($sConfigFile)) {
    echo "iTop is not yet installed. Exiting...\n";
    exit(EXIT_CODE_ERROR);
}
require_once(APPROOT . '/application/startup.inc.php');


set_time_limit(0); // Some background actions may really take long to finish
$oP = new CLIPage("iTop - Attachments load on disk");

// Check CLI mode
if (!$bModeCLI = utils::IsModeCLI()) {
    $oP->p('The file can be executed only if run in mode CLI');
    $oP->output();
    exit(EXIT_CODE_FATAL);
}

function ReadMandatoryParam($oP, $sParam, $sSanitizationFilter = 'parameter')
{
    $sValue = utils::ReadParam($sParam, null, true /* Allow CLI */, $sSanitizationFilter);
    if (is_null($sValue)) {
        $oP->p("ERROR: Missing argument '$sParam'\n");
        UsageAndExit($oP);
    }
    return trim($sValue);
}

function UsageAndExit($oP)
{
    if ($bModeCLI = utils::IsModeCLI()) {
        $oP->p("USAGE:\n");
        $oP->p("php cron.php --auth_user=<login> --auth_pwd=<password> [--param_file=<file>] [--verbose=1] [--debug=1] [--status_only=1]\n");
    } else {
        $oP->p("Optional parameters: verbose, param_file, status_only\n");
    }
    $oP->output();
    exit(EXIT_CODE_FATAL);
}

$sAuthUser = ReadMandatoryParam($oP, 'auth_user', 'raw_data');
$sAuthPwd = ReadMandatoryParam($oP, 'auth_pwd', 'raw_data');
if (UserRights::CheckCredentials($sAuthUser, $sAuthPwd)) {
    UserRights::Login($sAuthUser); // Login & set the user's language
} else {
    $oP->p("Access wrong credentials ('$sAuthUser')");
    $oP->output();
    exit(EXIT_CODE_ERROR);
}

//  ATTACHMENTS LOAD FROM DB TO DIRECTORY
$bSaveOnDisk = MetaModel::GetModuleSetting('itop-attachments', 'save_on_disk');
if (!$bSaveOnDisk) {
    $oP->p('For save attachments on disk, please set parameter ("itop-attachments" => true) in module Attachments!');
    $oP->output();
    exit(EXIT_CODE_ERROR);
}

$sRootPath = MetaModel::GetModuleSetting('itop-attachments', 'root_path_attachments', APPROOT . '/data/attachments/');

if (!is_dir($sRootPath)) {
    if (!mkdir($sRootPath)) {
        $oP->p('The directory "' . $sRootPath . '" is not created.');
        $oP->output();
        exit(EXIT_CODE_ERROR);
    }
}

if (!is_writable($sRootPath)) {
    $oP->p('The directory "' . $sRootPath . '" must be writable to the application.');
    $oP->output();
    exit(EXIT_CODE_ERROR);
}

$oConfig = MetaModel::GetConfig();
$sDBSubname = $oConfig->Get('db_subname');

$sSQL = 'SELECT id FROM ' . $sDBSubname . 'attachment WHERE path="" AND temp_id="" ORDER BY id DESC';

if (!$resQuery = CMDBSource::Query($sSQL)) {
    $oP->p('The table "' . $sDBSubname . 'attachment" is empty.');
    $oP->output();
    exit;
}
while ($aAttachment = CMDBSource::FetchArray($resQuery)) {
    if (!BlobToDiskSaver($aAttachment['id'])) {
        echo 'The Attachment with id = ' . $aAttachment['id'] . ' was not processed!' . PHP_EOL;
    }
}
$oP->p('Attachments_to_disk.cron.php -> The script has successfully completed!');
$oP->output();
/**
 * @param $iAttachId
 * @return bool
 * @throws ArchivedObjectException
 * @throws CoreException
 * @throws CoreUnexpectedValue
 * @throws Exception
 */
function BlobToDiskSaver($iAttachId)
{
    global $sRootPath;
    $oAttachment = MetaModel::GetObject("Attachment", $iAttachId, true);
    $oDoc = $oAttachment->Get('contents');

    if (!$sTmpFilePath = fileTmpPath($oAttachment)) {
        return false;
    }

    if (!fileWrite($oDoc, $sTmpFilePath)) {
        return false;
    }

    if (!compareContentFiles($oDoc, $sRootPath . $sTmpFilePath)) {
        unlink($sRootPath . $sTmpFilePath);
        return false;
    }

    $oNewDoc = new ormDocument("\0", $oDoc->GetMimeType(), $oDoc->GetFileName());
    $oAttachment->Set('path', $sTmpFilePath);
    $oAttachment->Set('contents', $oNewDoc);
    $oAttachment->DBUpdate();
    return true;
}

/**
 * @param Attachment $oAttachment
 * @return bool|string
 * @throws CoreException
 */
function fileTmpPath(Attachment $oAttachment)
{
    $sExpire = $oAttachment->Get('expire');

    if (preg_match("/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}$/", $sExpire)) {
        $iDate = strtotime($sExpire) - MetaModel::GetConfig()->Get('draft_attachments_lifetime');
    } else {
        $iDate = time();
    }

    $sClass = $oAttachment->Get('item_class');
    $oDoc = $oAttachment->Get('contents');
    $sExtension = pathinfo($oDoc->GetFileName(), PATHINFO_EXTENSION);
    if (!$sClass || !$sExtension) {
        echo 'For Attachment WHERE ID=' . $oAttachment->GetKey() . ' empty or invalid required parameters ("item_class" OR "contents_filename") ' . PHP_EOL;
        return false;
    }
    return date('Y', $iDate) . DIRECTORY_SEPARATOR . date('md', $iDate) . DIRECTORY_SEPARATOR .
        $sClass . '_' . $oAttachment->Get('item_id') . '_' . $oAttachment->GetKey() . '.' . $sExtension;
}

/**
 * @param ormDocument $oDoc
 * @param $sTmpFilePath
 * @return bool
 */
function fileWrite(ormDocument $oDoc, $sTmpFilePath)
{
    global $sRootPath;
    $sFullPath = $sRootPath . $sTmpFilePath;
    if (file_exists($sFullPath)) {
        echo 'File already exist "' . $sFullPath . '". ' . PHP_EOL;
        return false;
    }
    if (!file_exists(dirname($sFullPath))) {
        if (!mkdir(dirname($sFullPath), 0644, true)) {
            echo 'Cannot create path "' . dirname($sFullPath) . '". ' . PHP_EOL;
            return false;
        }
    }
    if (!$oDoc->GetData()) {
        echo 'Empty content of the file in the database!' . PHP_EOL;
        return false;
    }
    $fd = fopen($sFullPath, "w");
    if (!fwrite($fd, $oDoc->GetData())) {
        fclose($fd);
        echo 'File was not written "' . $sFullPath . '". ' . PHP_EOL;
        return false;
    }
    fclose($fd);
    return true;
}

/**
 * @param ormDocument $oDoc
 * @param $sFullPath
 * @return bool
 */
function compareContentFiles(ormDocument $oDoc, $sFullPath)
{
    return md5($oDoc->GetData()) === md5(file_get_contents($sFullPath));
}