<?php
function createTable($database){
    $connection = connect($database);

    // Creating Notes Table
    try {
        if (mysqli_query($connection , "CREATE TABLE notes (
        note_id VARCHAR(20) PRIMARY KEY, 
        note_name varchar(100),
        note TEXT,
        note_images JSON,
        pinned BOOLEAN DEFAULT FALSE,
        pin_order INT DEFAULT NULL,
        user_id VARCHAR(20),
        CONSTRAINT fk_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )")) {
            // do nothing!
        }
    } catch(mysqli_sql_exception $e){
        // do nothing!
    }


    // Creating Users Table
    try {
        if (mysqli_query($connection , "CREATE TABLE users (
        id VARCHAR(20) PRIMARY KEY, 
        name VARCHAR(100) NOT NULL,
        mail VARCHAR(100) UNIQUE NOT NULL,
        pass VARCHAR(255) NOT NULL,
        token VARCHAR(255) DEFAULT NULL
        )")) {
            // do nothing!
        }
    } catch(mysqli_sql_exception $e){
        // do nothing!
    }

    return $connection;
}

function connect($database = null){
    $serverName="localhost:3306";
    $userName="root";
    $password="";
    $connection = null;

    if ($database){
        try {
        $connection = new mysqli($serverName , $userName , $password , $database);
        } catch(mysqli_sql_exception $e){
            try {
                $connection = new mysqli($serverName , $userName , $password);
                mysqli_query($connection, "create database ".$database); 
                $connection = new mysqli($serverName , $userName , $password , $database);
            } catch (mysqli_sql_exception $e){
                echo "Failed to connnect to database";
                die;
            }
        }
    } else {
        $connection =  new mysqli($serverName , $userName , $password);
    }

    if (!$connection){
        echo "Failed to connnect to database";
        die;
    } else {
        return $connection;
    }
}
?>