<?php
require_once 'includes/functions.php';
startSession();
session_destroy();
redirect('login.php');
