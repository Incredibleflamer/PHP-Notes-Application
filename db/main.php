<?php 
require "connect.php";
$connection = createTable("notesdb");

// check if users token is correct
function login($token){
    global $connection;
    try {
        $query = $connection->prepare("SELECT id, name FROM users WHERE token = ?");
        $query->bind_param("s", $token); 
        $query->execute();
        $result = $query->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc(); 
            return [
                "status" => "success",
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name']
                ]
            ];
        } else {
            return [
                "status" => "error",
                "message" => "Invalid token or user not found."
            ];
        }
    } catch (mysqli_sql_exception $e) {
        return [
            "status" => "error",
            "message" => "Exception occurred: " . $e->getMessage()
        ];
    }
}

// login via password & username
function loginWithPassword($username, $pass) {
    global $connection;

    $query = "SELECT * FROM users WHERE name = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($pass, $user['pass'])) {
            $token = bin2hex(random_bytes(16));

            $stmtToken = $connection->prepare("UPDATE users SET token = ? WHERE id = ?");
            $stmtToken->bind_param("ss", $token, $user["id"]);
            $stmtToken->execute();

            return [
                'status' => 'success',
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name']
                ]
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Invalid password'
            ];
        }
    } else {
        return [
            'status' => 'error',
            'message' => 'User not found'
        ];
    }
}

// signup
function signup($userid, $email, $pass, $username){
    global $connection;
    try {
        $hashedPass = password_hash($pass, PASSWORD_BCRYPT);

        $stmt = $connection->prepare("INSERT INTO users (id, name, mail, pass) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $userid, $username, $email, $hashedPass);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $token = bin2hex(random_bytes(16));

            $stmtToken = $connection->prepare("UPDATE users SET token = ? WHERE id = ?");
            $stmtToken->bind_param("ss", $token, $userid);
            $stmtToken->execute();

            return [
                "status" => "success",
                "token" => $token
            ];
        } else {
            return [
                "status" => "error",
                "message" => "Failed to create user."
            ];
        }
    } catch (mysqli_sql_exception $e) {
        return [
            "status" => "error",
            "message" => "Exception occurred: " . $e->getMessage()
        ];
    }
}

// New note
function addNote($note_id, $userid) {
    global $connection;

    try {
        $note_id = mysqli_real_escape_string($connection, $note_id);
        $userid = mysqli_real_escape_string($connection, $userid);

        $query = "INSERT INTO notes (note_id, user_id , note) VALUES ('$note_id', '$userid', '')";

        if (mysqli_query($connection, $query)) {
            return [
                'status' => 'success',
                'note_id' => $note_id,
                'message' => 'Note added successfully!'
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Error adding note'
            ];
        }
    } catch (mysqli_sql_exception $e) {
        return [
            'status' => 'error',
            'message' => 'Exception occurred: ' . $e->getMessage()
        ];
    }
}

// remove note
function removeNote($note_id, $userid) {
    global $connection;

    try {
        $note_id = mysqli_real_escape_string($connection, $note_id);
        $userid = mysqli_real_escape_string($connection, $userid);

        $result = mysqli_query($connection, "SELECT note_images FROM notes WHERE note_id = '$note_id' AND user_id = '$userid'");

        if ($result && mysqli_num_rows($result) > 0) {
            $note = mysqli_fetch_assoc($result);
            $images = json_decode($note['note_images'], true) ?? [];

            foreach ($images as $image_name) {
                $image_path = __DIR__ . "/../images/$image_name";
                if (file_exists($image_path)) {
                    unlink($image_path);
                }
            }

            if (mysqli_query($connection, "DELETE FROM notes WHERE note_id = '$note_id' AND user_id = '$userid'")) {
                return [
                    'status' => 'success',
                    'message' => "Note with ID $note_id has been successfully removed from the database.",
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Error: Removing note, note not found or access denied.',
                ];
            }
        } else {
            return [
                'status' => 'error',
                'message' => "Note with ID $note_id not found or you don't have permission to delete it.",
            ];
        }

    } catch (mysqli_sql_exception $e) {
        return [
            'status' => 'error',
            'message' => 'Exception occurred: ' . $e->getMessage(),
        ];
    }
}

// upate note
function updateNote($note_id, $new_note_name, $new_note_content, $new_images, $new_checklists , $userid) {
    global $connection;

    try {
        $note_id = mysqli_real_escape_string($connection, $note_id);
        $userid = mysqli_real_escape_string($connection, $userid);

        $result = mysqli_query($connection, "SELECT * FROM notes WHERE note_id = '$note_id' AND user_id = '$userid'");

        if (mysqli_num_rows($result) === 0) {
            return [
                'status' => 'error',
                'message' => "Note with id $note_id not found or you don't have permission to edit it!",
            ];
        }       

        $new_note_name = mysqli_real_escape_string($connection, $new_note_name);
        $new_note_content = mysqli_real_escape_string($connection, $new_note_content);
        $images_json = mysqli_real_escape_string($connection, json_encode($new_images));
        $checklist_json = mysqli_real_escape_string($connection , json_encode($new_checklists));

        $update_query = "
            UPDATE notes
            SET note_name = '$new_note_name', note = '$new_note_content', note_images = '$images_json', checklist = '$checklist_json'
            WHERE note_id = '$note_id' AND user_id = '$userid'
        ";

        if (!mysqli_query($connection, $update_query)) {
            return [
                'status' => 'error',
                'message' => "Failed to update note with ID $note_id.",
            ];
        }

        return [
            'status' => 'success',
            'message' => "Note with ID $note_id updated successfully!",
        ];

    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Exception occurred: ' . $e->getMessage(),
        ];
    }
}

// find note
function findNote($word, $userid) {
    global $connection;

    try {
        $word = mysqli_real_escape_string($connection, $word);
        $userid = mysqli_real_escape_string($connection, $userid);
        
        $stmt = mysqli_prepare($connection, "SELECT note_id, note_name, note FROM notes WHERE note_name LIKE ? AND user_id = ?"); 
        
        $likeWord = "%$word%";
        mysqli_stmt_bind_param($stmt, 'ss', $likeWord, $userid);
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result) {
            if (mysqli_num_rows($result) > 0) {
                $data = [];
                while ($row = mysqli_fetch_assoc($result)) {
                    array_push($data, [
                        "note_id" => $row['note_id'],
                        "note_name" => $row['note_name'],
                        "note" => $row["note"],
                    ]);
                }
                return $data;
            }
        }
        return [];
    } catch (mysqli_sql_exception $e) {
        return [];
    }
}

// get note
function getNote($note_id, $userid) {
    global $connection;

    try {
        $note_id = mysqli_real_escape_string($connection, $note_id);
        $userid = mysqli_real_escape_string($connection, $userid);

        $result = mysqli_query($connection, "SELECT * FROM notes WHERE note_id = '$note_id' AND user_id = '$userid'");

        if ($result) {
            if (mysqli_num_rows($result) > 0) {
                $note = mysqli_fetch_assoc($result);
                $images = json_decode($note['note_images'], true) ?? [];
                $checklist = json_decode($note['checklist'], true) ?? [];

                return [
                    'status' => 'success',
                    'data' => [
                        'note_id' => $note['note_id'],
                        'note_name' => $note['note_name'],
                        'note_content' => $note['note'],
                        'note_images' => $images,
                        'checklist' => $checklist,
                    ],
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => "Note with ID $note_id not found for the user."
                ];
            }
        }
    
        return [
            'status' => 'error',
            'message' => 'Note retrieval failed.'
        ];

    } catch (mysqli_sql_exception $e) {
        return [
            'status' => 'error',
            'message' => 'Exception occurred: ' . $e->getMessage()
        ];
    }
}

// pin & unpin note
function pinNote($note_id, $user_id) {
    global $connection;

    $note_id = mysqli_real_escape_string($connection, $note_id);
    $user_id = mysqli_real_escape_string($connection, $user_id);

    $stmt = $connection->prepare("SELECT pinned FROM notes WHERE note_id = ? AND user_id = ?");
    $stmt->bind_param("is", $note_id, $user_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $pinned = 0;
        $stmt->bind_result($pinned);
        $stmt->fetch(); 

        $pin = $pinned == 1 ? 0 : 1;

        $pin_order = $pin == 1 ? time() : NULL;

        $updateStmt = $connection->prepare("
            UPDATE notes 
            SET pinned = ?, pin_order = ? 
            WHERE note_id = ? AND user_id = ?
        ");

        $updateStmt->bind_param("iiss", $pin, $pin_order, $note_id, $user_id);
        $updateStmt->execute();

        if ($updateStmt->affected_rows > 0) {
            return [
                'status' => 'success',
                'pin' => $pin == 1 ? true : false,
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Failed to update note status.',
            ];
        }
    } else {
        return [
            'status' => 'error',
            'message' => 'Note not found.',
        ];
    }
}

// get all note
function getAllNotes($userid, $Order) {
    global $connection;

    try {

        $query = "SELECT * FROM notes WHERE user_id = ? ORDER BY pinned DESC, pin_order DESC ";

        // For Sorting
        if ($Order === 'asc') {
            $query .= ", note_name ASC";
        } elseif ($Order === 'desc') {
            $query .= ", note_name DESC";
        }

        $stmt = mysqli_prepare($connection, $query);
        mysqli_stmt_bind_param($stmt, 's', $userid);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        if ($result) {
            if (mysqli_num_rows($result) > 0) {
                $data = [];

                while ($row = mysqli_fetch_assoc($result)) {
                    $cleanedNoteContent = preg_replace('/\{\[([^\]]+)\]\}/', '', $row["note"]);

                    $data[] = [
                        "note_id" => $row['note_id'],
                        "note_name" => $row['note_name'],
                        "pin" => $row['pinned'],
                        "note" => $cleanedNoteContent
                    ];
                }
                return $data;
            }
        }
        return [];
    } catch (mysqli_sql_exception $e) {
        return [];
    }
}
?>