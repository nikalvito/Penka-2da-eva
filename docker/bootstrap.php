<?php
if (!isset($_GET['id']) || $_GET['id'] === '' || !is_numeric($_GET['id'])) {
    $_GET['id'] = 1;
    $_REQUEST['id'] = 1;
} else {
    $_GET['id'] = (int)$_GET['id'];
    $_REQUEST['id'] = (int)$_GET['id'];
}
