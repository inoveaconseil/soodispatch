<?php
/*
 * Copyright (C) 2017      Nicolas ZABOURI      <info@inovea-conseil.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * Note: Page can be call with param mode=sendremind to bring feature to send
 * remind by emails.
 */
ini_set('max_execution_time', 6000);
$res = 0;

if (!$res && file_exists("../main.inc.php"))
    $res = @include '../main.inc.php';     // to work if your module directory is into dolibarr root htdocs directory
if (!$res && file_exists("../../main.inc.php"))
    $res = @include '../../main.inc.php';   // to work if your module directory is into a subdir of root htdocs directory
if (!$res && file_exists("../../../dolibarr/htdocs/main.inc.php"))
    $res = @include '../../../dolibarr/htdocs/main.inc.php';     // Used on dev env only
if (!$res && file_exists("../../../../dolibarr/htdocs/main.inc.php"))
    $res = @include '../../../../dolibarr/htdocs/main.inc.php';   // Used on dev env only
if (!$res)
    die("Include of main fails");
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.facture.class.php';
dol_include_once('soodispatch/class/soodispatch.class.php');

global $conf, $langs, $db, $user;
$langs->load("mails");
$langs->load("bills");
$langs->load("soodispatch@soodispatch");

$id=GETPOST('id','int');
$action=GETPOST('action','alpha');
$confirm=GETPOST('confirm','alpha');
/*
 * View
 */
if (! $user->rights->soodispatch->read)
    accessforbidden();

$form = new Form($db);
$formfile = new FormFile($db);
$formother = new FormOther($db);

$title = $langs->trans("Soodispatch");

$upload_dir = $conf->soodispatch->dir_output.'/';
$filearray=dol_dir_list($upload_dir,"files",0,'','(\.meta|_preview\.png)$',$sortfield,(strtolower($sortorder)=='desc'?SORT_DESC:SORT_ASC),1);

llxHeader('', $title);

$langs->load("link");
if (empty($relativepathwithnofile)) $relativepathwithnofile='';
if (empty($permtoedit)) $permtoedit=-1;

/*
 * Confirm form to delete
 */
include_once DOL_DOCUMENT_ROOT . '/core/actions_linkedfiles.inc.php';
$afile = GETPOST('sendit');

if(!empty($afile)){

    $file = $_FILES['userfile'];
    if(!file_exists($upload_dir)){
        dol_mkdir($upload_dir);
    }

    $dest_file = $upload_dir.dol_sanitizeFileName(dol_now().$file['name'][0]);

    $result = dol_move_uploaded_file($file['tmp_name'][0], $dest_file, 1, 0, 0);

    /* if(file_exists($dest_file)){ */
    $excel = new Soodispatch($db);
    $excel->sheetprocess($dest_file);

    /* }else{
         setEventMessages(null, $langs->trans('Problem'), 'errors');
     } */
}
if ($action == 'delete')
{
    $langs->load("companies");	// Need for string DeleteFile+ConfirmDeleteFiles
    $ret = $form->form_confirm(
        $_SERVER["PHP_SELF"] . '?urlfile=' . urlencode(GETPOST("urlfile")) . '&linkid=' . GETPOST('linkid', 'int') . (empty($param)?'':$param),
        $langs->trans('DeleteFile'),
        $langs->trans('ConfirmDeleteFile'),
        'confirm_deletefile',
        '',
        0,
        1
    );
    if ($ret == 'html') print '<br>';
}
if($action == "confirm_deletefile" && $confirm=="yes"){
    $file = $upload_dir . "/" . GETPOST('urlfile');	// Do not use urldecode here ($_GET and $_POST are already decoded by PHP).
    $ret=dol_delete_file($file);
}
$formfile=new FormFile($db);

// We define var to enable the feature to add prefix of uploaded files
//$savingdocmask=dol_sanitizeFileName(1).'-__file__';


$modulepart = 'soodispatch';
$permission = $user->rights->fournisseur->facture->creer;
$permtoedit = $user->rights->fournisseur->facture->creer;
$param = '&id=' . 1;
// Show upload form (document and links)
$formfile->form_attach_new_file(
    $_SERVER["PHP_SELF"],
    '',
    0,
    0,
    $permission,
    $conf->browser->layout == 'phone' ? 40 : 60,
    '',
    '',
    1,
    $savingdocmask,0,'soodispatch'
);

// List of document
$formfile->list_of_documents(
    $filearray,
    '',
    $modulepart,
    $param,
    0,
    $relativepathwithnofile,		// relative path with no file. For example "moduledir/0/1"
    1,
    0,
    '',
    0,
    '',
    '',
    0,
    $permtoedit
);

print "<br>";


