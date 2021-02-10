<?php

use OpenEMR\Core\Header;

?>
<html>
<head>
    <title><?php echo xlt('Import'); ?></title>

    <link rel="stylesheet" href="<?php echo $GLOBALS['assets_static_relative']; ?>/bootstrap-3-3-4/dist/css/bootstrap.css" type="text/css">

    <link rel="stylesheet" href="<?php echo $GLOBALS['assets_static_relative']; ?>/datatables.net-dt-1-10-13/css/jquery.dataTables.min.css" type="text/css">
    <link rel="stylesheet" href="<?php echo $GLOBALS['assets_static_relative']; ?>/datatables.net-colreorder-dt-1-3-2/css/colReorder.dataTables.min.css" type="text/css">

    <script type="text/javascript" src="<?php echo $GLOBALS['assets_static_relative']; ?>/jquery-min-1-10-2/index.js"></script>
    <script type="text/javascript" src="<?php echo $GLOBALS['assets_static_relative']; ?>/datatables.net-1-10-13/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="<?php echo $GLOBALS['assets_static_relative']; ?>/datatables.net-colreorder-1-3-2/js/dataTables.colReorder.min.js"></script>

    <script type="text/javascript" src="../oe-import/assets/js/datatables/Buttons-1.6.5/js/dataTables.buttons.js"></script>
    <script type="text/javascript" src="../oe-import/assets/js/datatables/Buttons-1.6.5/js/buttons.dataTables.js"></script>
    <script type="text/javascript" src="../oe-import/assets/js/datatables/Buttons-1.6.5/js/buttons.colVis.js"></script>
    <script type="text/javascript" src="../oe-import/assets/js/datatables/Buttons-1.6.5/js/buttons.print.js"></script>
    <script type="text/javascript" src="../oe-import/assets/js/datatables/Buttons-1.6.5/js/buttons.html5.js"></script>
    <script type="text/javascript" src="../oe-import/assets/js/datatables/Buttons-1.6.5/js/buttons.bootstrap4.js"></script>

    <link rel="stylesheet" href="../oe-import/assets/js/datatables/Buttons-1.6.5/css/buttons.bootstrap4.css" type="text/css" />
    <style type="text/css">
        .dataTables_wrapper .dataTables_scroll div.dataTables_scrollBody th, .dataTables_wrapper .dataTables_scroll div.dataTables_scrollBody td
        {
            vertical-align: top;
        }

        .badge {
            padding: 5px 7px 6px 7px;
        }

        .badge.complete {
            background-color: #B4CE99;
        }

        .badge.info {
            background-color: #17a2b8;
        }

        .badge.warning {
            background-color: #ffc107;
        }
    </style>
</head>
<body class="body_top" style="padding: 10px;">

<?php if (count($this->importService->getValidationMessages()) > 0) { ?>
    <div class="alert-danger">
        <ul>
            <?php foreach($this->importService->getValidationMessages() as $message) { ?>
                <li><?php echo $message; ?></li>
            <?php } ?>
        </ul>
    </div>
<?php } ?>

<form class="form-inline" enctype="multipart/form-data" method='post' name='do_import_form' id='do_import_form' action='<?php echo $this->action_url; ?>' onsubmit='return top.restoreSession()'>
<!--    <input type="hidden" name="csrf_token_form" value="--><?php //echo attr(CsrfUtils::collectCsrfToken()); ?><!--" />-->
    <input type="hidden" name="do_import" value="1" />
    <div class="form-group mb-2">
        <label for="input_files">Input Files</label>
        <input type="file" class="form-control-file" id="input_files" name="input_files[]" multiple>
    </div>
    <input type="submit" class="btn css_button_small" value="Do Import" \>
</form>

<hr>

<div id="report_results">
    <table class='table table-striped table-bordered' style="width: 100%" id='mymaintable'>
        <thead class='thead-light'>
        <?php foreach ($this->columns as $title => $key) { ?>
            <th><?php echo xlt($title); ?></th>
        <?php } ?>
        </thead>
        <tbody>
        </tbody>
    </table>
</div>
</body>
<script type="text/javascript">
    $("#mymaintable").DataTable({
        "scrollX": true,
        dom: 'frtip',
        "processing": true,
        // next 2 lines invoke server side processing
        "ajax": {
            "type" : "GET",
            "url" : '<?php echo $this->ajax_source_url; ?>',
            "dataSrc": function (json) {
                return json.data;
            }
        },
        "columns": [
            { "data": "id" },
            {
                "data": "status",
                "render": function(data, type, row, meta) {
                    // Format the status with a nice looking badge
                    if (type === 'display') {
                        if (data == 'complete') {
                            data = '<span class="badge complete">' + data + '</span>';
                        } else if (data == 'waiting') {
                            data = '<span class="badge info">' + data + '</span>';
                        } else {
                            data = '<span class="badge warning">' + data + '</span>';
                        }
                    }

                    return data;
                }
            },
            { "data": "user_filename" },
            { "data": "created_datetime" },
            { "data": "start_datetime" },
            { "data": "end_datetime" },
            {
                "data": "messages",
                "render": function(json, type, row, meta) {
                    // Build the HTML
                    // let json = JSON.parse(data);
                    let messagesHTML = '';
                    if (Array.isArray(json)) {
                        if (type === 'display') {
                            messagesHTML = '<table>';
                            let count = 1;
                            json.forEach(message => {
                                let messageRow = '<tr><td>' + count + '</td><td>' + message + '</td></tr>';
                                messagesHTML = messagesHTML + messageRow;
                                count++;
                            })

                            messagesHTML = messagesHTML + '</table>';
                        }
                    }

                    return messagesHTML;
                }
            }
        ],
        "order": [[0, 'desc']]
    });
</script>
</html>
