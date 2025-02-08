<?php
function createTable($database){
    $connection = connect($database);

    // Creating Users Table
    mysqli_query($connection , "CREATE TABLE IF NOT EXISTS users (
        id VARCHAR(20) PRIMARY KEY, 
        name VARCHAR(100) NOT NULL,
        mail VARCHAR(100) UNIQUE NOT NULL,
        pass VARCHAR(255) NOT NULL,
        token VARCHAR(255) DEFAULT NULL
    )");

    // Creating Notes Table
    mysqli_query($connection , "CREATE TABLE IF NOT EXISTS notes (
        note_id VARCHAR(20) PRIMARY KEY, 
        note_name varchar(100),
        note TEXT,
        note_images JSON,
        checklist JSON,
        pinned BOOLEAN DEFAULT FALSE,
        pin_order INT DEFAULT NULL,
        user_id VARCHAR(20),
        CONSTRAINT fk_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Creating Sharing Table
    mysqli_query($connection , "CREATE TABLE IF NOT EXISTS shared_notes (
        id VARCHAR(20) PRIMARY KEY, 
        note_id VARCHAR(20),
        user_id VARCHAR(20),
        shared_with_all BOOLEAN DEFAULT FALSE,
        CONSTRAINT fk_note_id FOREIGN KEY (note_id) REFERENCES notes(note_id) ON DELETE CASCADE,
        CONSTRAINT fk_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Creating Shared Notes Emails
    mysqli_query($connection , "CREATE TABLE IF NOT EXISTS shared_notes_emails (
        share_id VARCHAR(20),
        email VARCHAR(255),
        user_id VARCHAR(20),
        CONSTRAINT fk_shared_id FOREIGN KEY (share_id) REFERENCES shared_notes(id) ON DELETE CASCADE,
        CONSTRAINT fk_shared_email_user FOREIGN KEY (email) REFERENCES users(mail) ON DELETE CASCADE,
        CONSTRAINT fk_shared_owner FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    )");

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