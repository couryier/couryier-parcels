<?php
defined('ABSPATH')||die('No Script Kiddies Please');
if(isset($_REQUEST['tes_login'])){
    $username=sanitize_text_field($_REQUEST['tes_username']);
    $password=sanitize_text_field($_REQUEST['tes_password']);
    $error=COURYIER_API::login($username,$password);
}
else if(isset($_POST['action'])&&$_POST['action']='logout'){
    COURYIER_API::logout();
}
?>