<?php
ORM::configure('mysql:host=localhost;dbname=studfuehrer');
ORM::configure('username', 'studfuehrer');
ORM::configure('password', 'password');
ORM::configure('driver_options', array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));

// For debugging purposes, we can log every SQL query:
# ORM::configure('logging', true);
