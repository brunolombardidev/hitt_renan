<?php
// Remove session_start() from this file
if(!isset($_SESSION['login_user']))
{
	header("location:login/index.php");
}
?>