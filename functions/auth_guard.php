<?php

//isset checks if a variable is not null or if it exists
//If the session user does not exist, block access
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}