<?php


namespace Mi2\Import;

use Mi2\Import\Models\Batch;
use Mi2\Import\Models\Response;

global $srcdir;
require_once $srcdir . DIRECTORY_SEPARATOR . 'patient.inc';
require_once $srcdir . DIRECTORY_SEPARATOR . 'formdata.inc.php';

class AliveAndWellImport implements ImportsInterface
{
    protected $batch_id;
    protected $filename;
    protected $columns;
    protected $fh_source;
    protected $validation_messages = [];

    protected static $column_mapping = [
        "First Name" => "fname",
        "Last Name" => "lname",
        "Date Of Birth" => "DOB",
        "Gender" => "sex",
        "Street (Mail)" => "street",
        "City (Mail)" => "city",
        "County" => "county",
        "State/Province (Mail)" => "state",
        "Country (Mail)" => "country_code",
        "Postal Code (Mail)" => "postal_code",
        "Phone (Mobile)" => "phone_cell",
        "Email Address" => "email",
        "Membership Status" => "memb_status",
        "Do for Fun" => "do_fun",
        "Favorite Food" => "fav_food",
        "Bucket List" => "bucket_list",
        "NPP Received?" => "hipaa_notice",
        "Primary Member Name" => "guardiansname",
        "How A&W Can Serve" => "aw_serve",
        "Membership Type" => "memtype",
        "IUA" => "awc_iua",
        "Organization Name" => "empl_list",
        "Area Provider" => "providerlsID",
        "Contact ID" => "pubpid",
        "Alive & Well Start Date" => null // Ignored
    ];

    public function __construct()
    {

    }

    public function remove_utf8_bom($text)
    {
        $bom = pack('H*','EFBBBF');
        $text = preg_replace("/^$bom/", '', $text);
        return $text;
    }

    public function escape_column_data()
    {
        $escaped = [];
        foreach ($this->columns as $column) {
            $column = $this->remove_utf8_bom($column);
            $escaped[] = $column;
        }
        $this->columns = $escaped;
    }

    public function setup($batch)
    {
        $this->batch_id = $batch['id'];
        $this->filename = $batch['filename'];

        $this->fh_source = fopen($this->filename, 'r') or die("Failed to open file");

        //read a line
        $this->columns = fgetcsv($this->fh_source, 0 , ',');
        $this->escape_column_data();
    }

    public function validateUploadFile($file)
    {
        $this->fh_source = fopen($file['tmp_name'], 'r') or die("Failed to open file");

        //read a line
        $this->columns = fgetcsv($this->fh_source, 0 , ',');
        $this->escape_column_data();

        return $this->validate();
    }

    public function validate()
    {
        $valid = true;
        // First make sure there are no extra columns in spreadsheet that we don't know what to do with
        $known_columns = array_keys(self::$column_mapping);
        foreach ($this->columns as $column) {
            if (!in_array($column, $known_columns)) {
                $this->validation_messages[] = "Unknown column `$column` in file";
                $valid = false;
            }
        }

        // Then make sure all the required columns are in the spreadsheet
        foreach (self::$column_mapping as $required_column => $mapping) {
            if (
                $mapping !== null &&
                !in_array($required_column, $this->columns)
            ) {
                $this->validation_messages[] = "File missing required column `$required_column` in file";
                $valid = false;
            }
        }

        return $valid;
    }

    public function getValidationMessages()
    {
        return $this->validation_messages;
    }

    public function import()
    {
        // We already have our columns
        $record_count = 0;
        $errors = 0;
        $this->num_inserted = 0;
        $this->num_modified = 0; // We track modifications in the onPatientUpdated callback so we only include actaullly changed patients
        while (!feof($this->fh_source)) {

            //read a line
            $line = fgetcsv($this->fh_source, 0 , ',');

            // make sure the line has data
            if (false === $line) {
                continue;
            }

            // Create an assoc array with the keys bing the column headers of the sheet
            $patient_data = array_combine($this->columns, $line);
            // If we have an existing patient, update. Otherwise create.
            // This probably doesn't need to be in try/catch because nothing throws exception
            // How can we handle errors?
            try {
                $response = $this->importPatient($patient_data);
            } catch (\Exception $e) {
                $errors++;
                $this->validation_messages[] = $e->getMessage();
            }

            $record_count++;
        }
        fclose($this->fh_source);

        $response = new Response();
        $response->setResult(Response::SUCCESS);
        $response->setMessages($this->validation_messages);
        return $response;
    }

    public function getListItemOption($list, $title, $patient_name = "")
    {
        $row = sqlQuery("SELECT option_id FROM list_options WHERE " .
            "list_id = ? AND title = ? AND activity = 1", array($list, $title));
        if (
            $row == false ||
            empty($row['option_id'])
        ) {
            if ($title != "") {
                $this->validation_messages[] = "There was a value `$title` in the list `$list` for `$patient_name` but the option was not found";
            }
            return "";
        }

        return $row['option_id'];
    }

    public function getListItemTitle($list, $option, $patient_name = "", $supress_messages = false)
    {
        $row = sqlQuery("SELECT title FROM list_options WHERE " .
            "list_id = ? AND option_id = ? AND activity = 1", array($list, $option));
        if (
            $row == false ||
            empty($row['title'])
        ) {
            if (
                $option != ""
                && $supress_messages === false
            ) {
                $this->validation_messages[] = "There was a value `$option` in the list `$list` for `$patient_name` but the title was not found";
            }
            return "";
        }

        return xl_list_label($row['title']);
    }

    public static function valOrEmpty($data, $key)
    {
        if ($data[$key]) {
            return $data[$key];
        }

        return "";
    }

    public static function formatPhone($phone)
    {
        if ($phone) {
            $phone = str_replace(["(", ")", "-", " ", "  ", "#"], "", $phone);
            return '(' . substr($phone, 0, 3) . ') ' . substr($phone, 3, 3) . '-' . substr($phone, 6);
        }

        return "";
    }

    public function importPatient(array $patient_data)
    {
        $mapped_data = $this->mapData($patient_data);
        $pubpid_check = "";
        if ($mapped_data['pubpid']) {
            $pubpid_check = "(pubpid = ?) OR";
        }

        // Try to match ContactID, or Fname/Lname/DOB
        $findPatient = "SELECT fname, lname, pubpid, pid
            FROM patient_data
            WHERE $pubpid_check (fname = ? AND lname = ? AND DOB = ?)
            ORDER BY `date` DESC
            LIMIT 1";

        // Only put the pubpid in the binds array if we have the check for it
        $binds = [];
        if ($pubpid_check) {
            $binds[] = $mapped_data['pubpid'];
        }
        $binds[] = $mapped_data['fname'];
        $binds[] = $mapped_data['lname'];
        $binds[] = $mapped_data['DOB'];
        $result = sqlQuery($findPatient, $binds);
        $pid = null;
        if ($result !== false) {
            // We found a patient, so use that
            $pid = $result['pid'];
        } else {
            $patient_name = $mapped_data['fname'] . " " . $mapped_data['lname'];
            $this->validation_messages[] = "No match found for `$patient_name`";
        }

        $return = null;
        if ($pid === null) {
            $return = $this->updatePatientData(null, $mapped_data, true);
        } else {
            if ($pubpid_check) {
                unset($mapped_data['pubpid']);
            }
            $return = $this->updatePatientData($pid, $mapped_data);
        }

        return $return;
    }

    protected function mapData($patient_data)
    {
        $mapped_data = [];
        foreach ($patient_data as $spreadsheet_column_name => $value) {
            // Get the database column name for this column in the spreadsheet
            $mapped_key = self::$column_mapping[$spreadsheet_column_name];
            if ($mapped_key !== null) {
                // if the mapped key is null, we don't care about it.
                $mapped_data[$mapped_key] = $value;
            }
        }

        // For context in error messages, get the patient name
        $patient_name = $mapped_data['fname'] . " " . $mapped_data['lname'];

        // After the initial mapping, we need to do some additional formatting
        if ($mapped_data['DOB'] != "") {
            $mapped_data['DOB'] = date("Y-m-d", strtotime($mapped_data['DOB']));
        }

        // See if the state is a code or spelled out. If it's a code, this will get the spelled-out state name
        $state = $this->getListItemTitle('state', $mapped_data['state'], $patient_name ,true);
        if ($state == "") {
            // if the title wasn't found for the state field, it could be the full name spelled out
            $state_code = $this->getListItemOption('state', $mapped_data['state'], $patient_name);
            $mapped_data['state'] = $state_code;
        }

        // hard-code country to USA
        $mapped_data['country_code'] = 'USA';

        // These values need to be mapped using list options
        // county is uppercase, though I don't think mysql cares about that in the query
        $mapped_data['county'] = $this->getListItemOption('county', strtoupper($mapped_data['county']), $patient_name);
        $mapped_data['sex'] = $this->getListItemOption('sex', $mapped_data['sex'], $patient_name);
        $mapped_data['awc_iua'] = $this->getListItemOption('IUA', $mapped_data['awc_iua'], $patient_name);
        $mapped_data['empl_list'] = $this->getListItemOption('Employer_Organization', $mapped_data['empl_list'], $patient_name);
        $mapped_data['providerlsID'] = $this->getListItemOption('providers', $mapped_data['providerlsID'], $patient_name);

        // If NPP is true, set to YES, o.w. NO
        if ($mapped_data['hipaa_notice'] == 'true') {
            $mapped_data['hipaa_notice'] = 'YES';
        } else {
            $mapped_data['hipaa_notice'] = 'NO';
        }

        // If Membership Status is set to Active, memb_status is YES, o.w. NO
        if ($mapped_data['memb_status'] == 'Active') {
            $mapped_data['memb_status'] = 'YES';
        } else {
            $mapped_data['memb_status'] = 'NO';
        }

        // If there is a value for IUA, their Membership Type (AW Complete in OpenEMR) is YES, o.w. NO
        if ($mapped_data['awc_iua'] == "") {
            $mapped_data['memtype'] = "NO";
        } else {
            $mapped_data["memtype"] = "YES";
        }

        return $mapped_data;
    }

    public function updatePatientData($pid, $new, $create = false)
    {
        if ($create) {

            $result = sqlQuery("SELECT MAX(pid)+1 AS pid FROM patient_data");
            $newpid = 1;
            if ($result['pid'] > 1) {
                $newpid = $result['pid'];
            }

            $sql = "INSERT INTO patient_data SET pid = '" . add_escape_custom($newpid) . "', date = NOW()";
            foreach ($new as $key => $value) {
                if ($key == 'id') {
                    continue;
                }

                $sql .= ", `$key` = " . pdValueOrNull($key, $value);
            }

            $db_id = sqlInsert($sql);
        } else {
            $sql = "UPDATE patient_data SET date = NOW()";
            foreach ($new as $key => $value) {
                $sql .= ", `$key` = " . pdValueOrNull($key, $value);
            }

            $sql .= " WHERE pid = '" . add_escape_custom($pid) . "'";
            sqlStatement($sql);
        }

        return $pid;
    }
}
