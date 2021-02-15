<?php

namespace Mi2\Import;

use Mi2\Import\Models\Batch;
use Mi2\Import\Models\Response;

class ImportService
{
    protected $file;
    protected $validationMessages = [];

    // These are for while running batches of import
    protected $current_batch_id;
    protected $messages = [];
    protected $num_inserted;
    protected $num_modified;

    protected $current_record_had_correct_pid;
    protected $config;

    public function __construct()
    {
        $this->config = include __DIR__ . "/../import-config.php";
    }

    /**
     * Make the correct importer based on file type
     *
     * @param $file
     */
    public static function makeImporter($filename)
    {
        $importer = null;
        $path_parts = pathinfo($filename);
        if (strtolower($path_parts['extension']) == 'csv') {
            $importer = new AliveAndWellImport();
        } else if (strtolower($path_parts['extension']) == 'png' ||
            strtolower($path_parts['extension']) == 'jpg' ||
            strtolower($path_parts['extension']) == 'jpeg') {
            $importer = new AliveAndWellAttachPhoto();
        } else {
            $importer = new AliveAndWellNullImporter();
        }
        return $importer;
    }

    public function execute()
    {
        // First, find all the batches that are in 'waiting' state


        $waiting_batches = Batch::fetchByStatus(Batch::STATUS_WAIT);

        while ($batch = sqlFetchArray($waiting_batches)) {

            $this->current_batch_id = $batch['id'];

            Batch::update($batch['id'], [
                'status' => Batch::STATUS_PROCESSING,
                'start_datetime' => date('Y-m-d H:i:s')
            ]);

            // Reset validation messages for each batch
            $this->validationMessages = [];

            // if the file is an image, run the image importer, if it's a csv run patient importer
            $importer = self::makeImporter($batch['filename']);

            $importer->setup($batch);

            if ($importer->validate() === true) {
                // Pass the pointer to the open file to the importer
                $response = $importer->import();
            } else {
                // This is the case where the validator fails, get messages and update batch and quit
                $this->validationMessages = array_merge($importer->getValidationMessages(), $this->validationMessages);
                Batch::update($batch['id'], [
                    'status' => Batch::STATUS_ERROR,
                    'messages' => json_encode($this->validationMessages),
                    'end_datetime' => date('Y-m-d H:i:s')
                ]);
                continue;
            }

            if ($response->getResult() === Response::SUCCESS) {
                $this->validationMessages = array_merge($response->getMessages(), $this->validationMessages);
                Batch::update($batch['id'], [
                    'status' => Batch::STATUS_COMPLETE,
                    'messages' => json_encode($this->validationMessages),
                    'end_datetime' => date('Y-m-d H:i:s')
                ]);
            } else {
                $this->validationMessages = array_merge($response->getMessages(), $this->validationMessages);
                Batch::update($batch['id'], [
                    'status' => Batch::STATUS_ERROR,
                    'messages' => json_encode($this->validationMessages),
                    'end_datetime' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }

    /**
     *
     * Set the upload file for validation and to use in creation of batch
     * @param $file
     */
    public function setUploadFile($file)
    {
        $this->file = $file;
    }

    /**
     * Do basic validation on file, like make sure columns are correct
     *
     * Returns true if valid, false OW
     *
     * @param $file
     * @return bool
     */
    public function validateFile()
    {
        if (!empty($this->file)) {
            $importer = self::makeImporter($this->file['name']);
            if ($importer->validateUploadFile($this->file)) {
                return true;
            } else {
                $this->validationMessages = array_merge($importer->getValidationMessages(), $this->validationMessages);
                return false;
            }
        } else {
            $this->validationMessages[] = "No file uploaded";
        }
        return false;
    }

    public static function reArrayFiles(&$file_post)
    {
        $isMulti    = is_array($file_post['name']);
        $file_count    = $isMulti?count($file_post['name']):1;
        $file_keys    = array_keys($file_post);

        $file_ary    = [];    //Итоговый массив
        for($i=0; $i<$file_count; $i++)
            foreach($file_keys as $key)
                if($isMulti)
                    $file_ary[$i][$key] = $file_post[$key][$i];
                else
                    $file_ary[$i][$key]    = $file_post[$key];

        return $file_ary;
    }

    /**
     * Create a new batch entry in 'waiting' state for
     * a newly uploaded file
     *
     * @return int
     */
    public function createBatch()
    {
        // Move the tmp file to documents dir
        $directory = $GLOBALS['OE_SITE_DIR'] . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . 'imports';
        if (!file_exists($directory)) {
            if (!mkdir($directory, 0700, true)) {
                $this->validationMessages[]= xl('Unable to create document directory');
                return false;
            }
        }

        // Create the file with the current date timestamp
        $parts = pathinfo($this->file['name']);
        $filepath = $directory . DIRECTORY_SEPARATOR . date("Ymdhi") . "." . $parts['extension'];
        if (false === move_uploaded_file($this->file['tmp_name'], $filepath)) {
            $this->validationMessages[]= xl('Unable to move uploaded file');
            return false;
        }

        return Batch::create([
            'filename' => $filepath, // The name of the file on disk
            'user_filename' => $this->file['name'], // The name of the file that was uploaded
            'created_datetime' => date('Y-m-d h:i:s'),
            'status' => Batch::STATUS_WAIT
        ]);
    }

    /**
     * @return array
     */
    public function getValidationMessages()
    {
        return $this->validationMessages;
    }

    /*
     * This function inserts the background process if it doesn't already exist
     */
    public static function insertBackgroundService()
    {
        $sql = "SELECT * FROM `background_services` WHERE `name` = ? LIMIT 1";
        $row = sqlQuery($sql,['IMPORT_SERVICE']);
        if (false === $row) {
            // The background service hasn't been created so create it.
            // Set it to run the mss_service.php script every "1 minute" we want to run it every time
            // background services run
            $sql = "INSERT INTO `background_services` (`name`, `title`, `active`, `running`, `next_run`, `execute_interval`, `function`, `require_once`, `sort_order`) VALUES
            ('IMPORT_SERVICE', 'Import Service', 1, 0, '2021-01-10 11:25:10', 1, 'start_import', '/interface/modules/custom_modules/oe-import/import_service.php', 100);";

            sqlStatement($sql);
        }
    }

    public static function change_key( $array, $old_key, $new_key ) {


        if( ! array_key_exists( $old_key, $array ) )
            return $array;

        $keys = array_keys( $array );
        $keys[ array_search( $old_key, $keys ) ] = $new_key;

        return array_combine( $keys, $array );
    }

    //this returns the count, pid, and id.
    private function ptExists($macprac){

        $row = sqlQuery("Select count(*) as count, pid as pid, id from patient_data where macPrac = ?",
            array($macprac));
        if ($row['count'] > 0) {
            return $row;

        } else{

            return false;
        }

    }
}
