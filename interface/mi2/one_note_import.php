<?php
/**
 * Created by PhpStorm.
 * User: kchapple
 * Date: 9/23/19
 * Time: 10:40 AM
 */
session_name("OpenEMR");
echo "In doc fetch\n";
$ignoreAuth = true;
$fake_register_globals = false;
$sanitize_all_escapes = true;
$_SESSION['site_id'] = 'default';

require_once __DIR__.'/../globals.php';

$location = '/home/ken/one_note';

$no_match = [];

foreach (glob("$location/*") as $companyDirectory) {
    if (is_dir($companyDirectory)) {
        echo "INFO Processing Files in: $companyDirectory\n";

        foreach (glob("$companyDirectory/*.*") as $patientDoc) {
            echo "\tINFO $patientDoc\n";

            $path_parts = pathinfo($patientDoc);
            $patientDocBasename = $path_parts['basename'];

            $externalId = substr($patientDocBasename, 0,9);
            $documentType = 3;
            $ext = pathinfo($patientDoc, PATHINFO_EXTENSION);
            if ($ext == 'pdf') {
                $documentType = 3; // Medical Record
            } else if ($ext == 'jpg' || $ext == 'jpeg') {
                $documentType = 10; // Patient Photograph
            } else {
                echo "\tWARNING Unknown file type for $patientDoc\n";
            }
            $findPatient = "SELECT fname, lname, pubpid, pid FROM patient_data WHERE pubpid = ?";
            $result = sqlStatement($findPatient, [$externalId]);
            if ($result) {
                $count = 0;
                while ($row = sqlFetchArray($result)) {


                    $doc = new \Document();
                    $file_contents = file_get_contents($patientDoc);
                    $ret = $doc->createDocument($row['pid'], $documentType, $patientDocBasename, mime_content_type($patientDoc), $file_contents);

                    $count++;
                }

                if ($count > 1) {
                    echo "\tWARNING $count patients found for pubpid `$externalId`\n";
                }
            } else {
                echo "\tWARNING No patient for `$externalId`\n";
                $no_match []= $patientDoc;
            }
        }
    } else {
        echo "INFO Ignoring $companyDirectory\n";
    }
}

echo "No matches for the following documents:\t";
foreach ($no_match as $match) {
    echo "$match\n";
}
