<?php
/**
 * Created by PhpStorm.
 * User: kchapple
 * Date: 7/29/19
 * Time: 11:00 AM
 */

namespace Mi2\CustomModuleTpl\Controllers;

use Mi2\DataTable\SearchFilter;
use Mi2\Framework\AbstractController;
use Mi2\Framework\Response;

class AdminController extends AbstractController
{
    public function __construct()
    {

    }

    public function _action_patient_search()
    {
        $query = $this->request->getParam( 'query' );
        if ( strpos( $query, '/' ) !== false ) {
            $parts = explode("/", $query );
            $year = "";
            $month = "";
            $day = "";
            for ( $i = 0; $i < count($parts); $i++ ) {
                switch ( $i ) {
                    case 0: $month = $parts[0];
                        break;
                    case 1: $day = $parts[1];
                        break;
                    case 2: $year = $parts[2];
                        break;
                }
            }

            $dob = "%-$month-%";
            if ( $day ) {
                $dob = "%-$month-$day";
            }

            if ( $year ) {
                $dob = "%$year-$month-$day%";
            }

            $statement = "SELECT PD.*, ( SELECT left(FE.date,10) FROM form_encounter FE WHERE PD.pid = FE.pid ORDER BY FE.date DESC LIMIT 1 ) AS last_encounter FROM patient_data PD WHERE PD.DOB LIKE ?";
            $result = sqlStatement( $statement, array( $dob ) );
        } else if ( strpos( $query, ',' ) !== false ) {
            $parts = explode( ',', $query );
            $fname = trim( $parts[1] );
            $lname = trim( $parts[0] );
            $statement = "SELECT PD.*, ( SELECT left(FE.date,10) FROM form_encounter FE WHERE PD.pid = FE.pid ORDER BY FE.date DESC LIMIT 1 ) AS last_encounter FROM patient_data PD WHERE PD.lname = ? AND PD.fname LIKE ?";
            $result = sqlStatement( $statement, array( $lname, "$fname%" ) );
        } else {
            $lname = trim( $query );
            $statement = "SELECT PD.*, ( SELECT left(FE.date,10) FROM form_encounter FE WHERE PD.pid = FE.pid ORDER BY FE.date DESC LIMIT 1 ) AS last_encounter FROM patient_data PD WHERE PD.lname LIKE ?";
            $result = sqlStatement( $statement, array( "$lname%" ) );
        }

        $patients = array();
        while ( $row = sqlFetchArray( $result ) ) {
            $patients []= array(
                'id' => $row['pid'],
                'name' => $row['lname'].", ".$row['fname'],
                'DOB' => $row['DOB'],
                'sex' => $row['sex'],
                'pid' => $row['pid'],
                'lastEncounter' => $row['last_encounter'],
                'displayKey' => $row['lname'].", ".$row['fname']." (".$row['pid']." ".$row['DOB'].") "
            );
        }

        echo json_encode( $patients );
        exit;
    }

    /**
     * This is the "home" page for the Patient Privacy settings
     */
    public function _action_index()
    {
        $this->setViewScript( 'admin/settings.php', 'layout.php' );
        $this->view->title = "Messages Settings";
    }
}
