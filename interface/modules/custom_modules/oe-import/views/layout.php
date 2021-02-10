<?php
require_once("{$GLOBALS['srcdir']}/options.inc.php");

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use OpenEMR\OeUI\OemrUI;

?>
<html>
<head>
    <?php Header::setupHeader(); ?>
<!--    <script type="module" src="--><?php //echo $GLOBALS['webroot'] ?><!--/interface/modules/custom_modules/oe-custom-module-tpl/assets/js/main.js"></script>-->
    <title><?php echo $this->title ?></title>
</head>
<body class="body_top">

<?php echo $this->content; ?>

</body>

</html>
