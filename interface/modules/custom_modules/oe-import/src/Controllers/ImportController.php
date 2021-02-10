<?php
/**
 * Created by PhpStorm.
 * User: kchapple
 * Date: 7/29/19
 * Time: 11:00 AM
 */

namespace Mi2\Import\Controllers;

use Mi2\DataTable_1_9\SearchFilter as SearchFilter;
use Mi2\Framework\AbstractController;
use Mi2\Framework\Response;
use Mi2\Import\ImportService;
use Mi2\Import\Models\Batch;

class ImportController extends AbstractController
{
    protected $importService;

    public function __construct()
    {
        $this->importService = new ImportService();
    }

    /**
     * Starting point for mss (link from admin menu)
     * Display the view with upload form.
     *
     * Also display a list of previous batches, and when one is selected,
     * display a report of the changes made in that batch.
     */
    public function _action_import()
    {
        // Get the column mapping
        $this->view->columns = Batch::getColumns();

        // Specify which view script to display
        $this->view->importService = $this->importService; // If there are any validation messages, they'll be in here
        $this->view->action_url = $this->getBaseUrl() . '/index.php?action=import!do_import';
        $this->view->ajax_source_url = $this->getBaseUrl() . '/index.php?action=import!ajax_source';
        $this->setViewScript('import/import.php');
    }

    public function _action_ajax_source()
    {
        $batches_result = Batch::all();
        $response = new \stdClass();
        $response->data = [];
        while ($batch = sqlFetchArray($batches_result)) {
            $element = new \stdClass();
            $element->id = $batch['id'];
            $element->status = $batch['status'];
            $element->user_filename = $batch['user_filename'];
            $element->created_datetime = $batch['created_datetime'];
            $element->start_datetime = $batch['start_datetime'];
            $element->end_datetime = $batch['end_datetime'];
            $element->messages = json_decode($batch['messages']);
            $response->data[] = $element;
        }

        echo json_encode($response);
        exit();
    }

    /**
     * Get the uploaded spreadsheet
     *
     * Do some basic validation on the file so it won't break
     * the backround service.
     *
     * Store the file on the server and create a row in the DB
     * for this batch with a reference to the file and an indicator
     * that it has not been processed.
     */
    public function _action_do_import()
    {
        if (isset($_POST['do_import'])) {
            // Insert the background service entry in case it doesn't exist.
            ImportService::insertBackgroundService();

            $files = ImportService::reArrayFiles($_FILES['input_files']);

            foreach ($files as $file) {
                // Set the file we're using to create the batch
                // $file contains an assos array with all the file's data
                $this->importService->setUploadFile($file);

                // Do basic validation on file
                // Store messages and display them on the UI
                $valid = $this->importService->validateFile();

                // If the file is valid, store the tmp file in a real document directory.
                // Create the batch, set to "waiting" so the background process
                // can pick it up and process it.
                if (true === $valid) {
                    $this->importService->createBatch();
                }
            }
        }

        // Now just do all the things the mss function does for rendering the index page
        $this->_action_import();
    }
}
