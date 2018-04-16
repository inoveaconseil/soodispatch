<?php

/*  Copyright (C) 2017       Nicolas ZABOURI     <info@inovea-conseil.com>
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
 */

/**
 * \file    bankwire/bankwire.class.php
 * \ingroup bankwire
 * \brief   This file is an example for a CRUD class file (Create/Read/Update/Delete)
 *          Put some comments here
 */
// Put here all includes required by your class file
require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/bank/class/account.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.class.php';
require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.facture.class.php';

dol_include_once('soodispatch/lib/PHPExcel/IOFactory.php');

/**
 * Class Bankwire
 *
 * Put here description of your class
 *
 * @see CommonObject
 */
class Soodispatch extends CommonObject {

    /**
     * Constructor
     *
     * @param DoliDb $db Database handler
     */
    public function __construct(DoliDB $db) {
        $this->db = $db;
    }
    
    public function sheetprocess($file){
        global $user, $conf;
        if(file_exists($file)){
             
// Chargement du fichier Excel
$objPHPExcel = PHPExcel_IOFactory::load($file);
 
/**
* récupération de la première feuille du fichier Excel
* @var PHPExcel_Worksheet $sheet
*/
$sheet = $objPHPExcel->getSheet(0);
$suppliersf = array();
echo '<table border="1">';
 
// On boucle sur les lignes
$i = 0;
foreach($sheet->getRowIterator() as $valline => $row) {
    if($i == 0) {$i=1;continue;}
    if($sheet->getCell("A".$valline)->getFormattedValue() == "Somme" || $sheet->getCell("A".$valline)->getFormattedValue() == "Compteur" || $sheet->getCell("A".$valline)->getFormattedValue() == "Moyenne" )
            continue;
    if($sheet->getCell("G".$valline)->getFormattedValue() !=1) continue;
    $namesup = $sheet->getCell("F".$valline)->getFormattedValue();
    $supplier = $this->getSupplierByAliasNine($namesup);
    $idsupplier = $supplier->rowid;
    
    if(!is_object($supplier)){ 
        $supplier = $this->getSupplierByAlias($namesup);
        $idsupplier = $supplier->rowid;
        if(is_object($supplier)){
            $upsup = new Societe($this->db);
            $upsup->fetch($idsupplier);
            $upsup->name_alias = '9'.$namesup;
            $upsup->update($idsupplier,$user);
        }
    }
    if(!is_object($supplier)){
        $newsup = new Societe($this->db);
        $newsup->nom = $namesup;
        $newsup->name = $namesup;
        $newsup->name_alias = '9'.$namesup;
        $newsup->fournisseur = 1;
        $newsup->client = 0;
        $newsup->tva_assuj = 0;
        $newsup->entity = $conf->global->entity;
        $newsup->country_id = 1;
        $idsupplier = $newsup->create($user);//tva_assuj
    }
    
    if($supplier->tva_assuj==1) {$tva = 20;} else {$tva=0;}
    $suppliersf[$idsupplier][] = array('designation' => $sheet->getCell("H".$valline)->getFormattedValue().' - '.$sheet->getCell("B".$valline)->getFormattedValue().' - '.$sheet->getCell("D".$valline)->getFormattedValue(). '<br />'.$sheet->getCell("R".$valline)->getFormattedValue().' '.$sheet->getCell("S".$valline)->getFormattedValue(). ' '.$sheet->getCell("T".$valline)->getFormattedValue(),
                                    'price' => $sheet->getCell("P".$valline)->getFormattedValue()+$sheet->getCell("Q".$valline)->getFormattedValue(),
                                    'tva' => $tva,
                                    'date' => $sheet->getCell("H".$valline)->getFormattedValue());
    
    
   echo '<tr>';
 
   // On boucle sur les cellule de la ligne
   foreach ($row->getCellIterator() as $valcell => $cell) {
      echo '<td>';
      print_r($cell->getFormattedValue());
      echo '</td>';
   }
 
   echo '</tr>';
   
}
echo '</table>';
        }
        foreach($suppliersf as $idsup => $lines){

            $invoice = new FactureFournisseur($this->db);
            $invoice->socid = $idsup;
            $invoice->date = dol_now();
             $invoice->amount = 0;
             $invoice->mode_reglement_id = 2;
            foreach($lines as $key => $line){
           // echo "<pre>".print_r($line,1)."</pre>";

                $invoice->lines[$key] = new SupplierInvoiceLine($db);
                $invoice->lines[$key]->description = $line['designation'];
                $invoice->lines[$key]->tva_tx = $line['tva'];
                $invoice->lines[$key]->qty = 1;
                $invoice->lines[$key]->pu_ht = $line['price'];
                $invoice->lines[$key]->total_ht = $line['price'];
                $invoice->lines[$key]->total_tva = $line['price'] * $line['tva']/100;
                $invoice->lines[$key]->total_ttc = $line['price'] + ($line['price'] * $line['tva']/100);
                $invoice->lines[$key]->pu_ttc = $line['price'] + ($line['price'] * $line['tva']/100);
                $invoice->lines[$key]->product_type = 1;
                if($key == 0){
                    $d = explode('/', $line['date']);
                    $invoice->ref_supplier = $d[1].'/'.$d[2];
                    
                }
            }
                                  //  echo "<pre>".print_r($invoice,1)."</pre>";

            $invoice->create($user);

            
        }
        
        //echo "<pre>".print_r($suppliersf,1)."</pre>";
        
        echo "<div style='color:green;font-size:25px'>La création des factures a été réalisée</div>";
    }
    
    
    public function getSupplierByAlias($alias){
        $sql = 'SELECT * FROM '.MAIN_DB_PREFIX.'societe WHERE name_alias="'.$alias.'" LIMIT 1';
        $resql=$this->db->query($sql);
        $obj = $this->db->fetch_object($resql);
        return $obj;
    }
    
        public function getSupplierByAliasNine($alias){
        $sql = 'SELECT * FROM '.MAIN_DB_PREFIX.'societe WHERE name_alias="9'.$alias.'" LIMIT 1';
        $resql=$this->db->query($sql);
        $obj = $this->db->fetch_object($resql);
        return $obj;
    }
}
 ?>