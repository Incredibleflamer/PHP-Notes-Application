<?php 
require "connect.php";
$connection = createTable("notesdb");

// check if users token is correct
function login($token) {
    global $connection;
    $stmt = $connection->prepare("SELECT id, name FROM users WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        return [
            'status' => 'success', 
            'user' => [
                'id' => $user['id'], 
                'name' => $user['name']
            ]
        ];
    } else {
        return ['status' => 'error', 'message' => 'Invalid token or user not found.'];
    }
}

// login with password & username
function loginWithPassword($mail, $pass) {
    global $connection;

    $stmt = $connection->prepare("SELECT id, name , mail, pass FROM users WHERE mail = ?");
    $stmt->bind_param("s", $mail);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($pass, $user['pass'])) {
            $token = bin2hex(random_bytes(16));
            $updateStmt = $connection->prepare("UPDATE users SET token = ? WHERE id = ?");
            $updateStmt->bind_param("ss", $token, $user['id']);
            $updateStmt->execute();

            return [
                'status' => 'success', 
                'token' => $token, 
                'user' => [
                    'id' => $user['id'], 
                    'name' => $user['name'],
                    'mail' => $user['mail']
                ]
            ];
        } else {
            return ['status' => 'error', 'message' => 'Invalid password'];
        }
    }
    return ['status' => 'error', 'message' => 'User not found'];
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
        $stmt = $connection->prepare("INSERT INTO notes (note_id, user_id , note) VALUES (?, ?, '')");
        $stmt->bind_param("ss", $note_id, $userid);

        if ($stmt->execute()) {
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

        $stmt = $connection->prepare("SELECT note_images FROM notes WHERE note_id = ? AND user_id = ?");
        $stmt->bind_param("ss", $note_id, $userid);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $note = $result->fetch_assoc();
            $images = json_decode($note['note_images'], true) ?? [];

            foreach ($images as $image_name) {
                $image_path = __DIR__ . "/../images/$image_name";
                if (file_exists($image_path)) {
                    unlink($image_path);
                }
            }

            $deleteStmt = $connection->prepare("DELETE FROM notes WHERE note_id = ? AND user_id = ?");
            $deleteStmt->bind_param("ss", $note_id, $userid);
            if ($deleteStmt->execute()) {
                return [
                    'status' => 'success',
                    'message' => "Note with ID $note_id has been successfully removed.",
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
function updateNote($note_id, $new_note_name, $new_note_content, $new_images, $new_checklists, $userid) {
    global $connection;

    try {
        $note_id = mysqli_real_escape_string($connection, $note_id);
        $userid = mysqli_real_escape_string($connection, $userid);

        $stmt = $connection->prepare("SELECT * FROM notes WHERE note_id = ? AND user_id = ?");
        $stmt->bind_param("ss", $note_id, $userid);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return [
                'status' => 'error',
                'message' => "Note with ID $note_id not found or you don't have permission to edit it!",
            ];
        }

        $new_note_name = mysqli_real_escape_string($connection, $new_note_name);
        $new_note_content = mysqli_real_escape_string($connection, $new_note_content);
        $images_json = json_encode($new_images);
        $checklist_json = json_encode($new_checklists);

        $update_stmt = $connection->prepare("
            UPDATE notes
            SET note_name = ?, note = ?, note_images = ?, checklist = ?
            WHERE note_id = ? AND user_id = ?
        ");
        $update_stmt->bind_param("ssssss", $new_note_name, $new_note_content, $images_json, $checklist_json, $note_id, $userid);

        if ($update_stmt->execute()) {
            return [
                'status' => 'success',
                'message' => "Note with ID $note_id updated successfully!",
            ];
        } else {
            return [
                'status' => 'error',
                'message' => "Failed to update note with ID $note_id.",
            ];
        }
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

        $stmt = $connection->prepare("SELECT note_id, note_name, note FROM notes WHERE note_name LIKE ? AND user_id = ?");
        $likeWord = "%$word%";
        $stmt->bind_param("ss", $likeWord, $userid);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $cleanedNoteContent = preg_replace('/\{\[([^\]]+)\]\}/', '', $row["note"]);
                $data[] = [
                    "note_id" => $row['note_id'],
                    "note_name" => $row['note_name'],
                    "note" => $cleanedNoteContent,
                ];
            }
            return $data;
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

        $stmt = $connection->prepare("SELECT * FROM notes WHERE note_id = ? AND user_id = ?");
        $stmt->bind_param("ss", $note_id, $userid);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $note = $result->fetch_assoc();
            $images = json_decode($note['note_images'], true) ?? [];
            $checklist = json_decode($note['checklist'], true) ?? [];

            $sharingStmt = $connection->prepare("SELECT * FROM shared_notes WHERE note_id = ? AND user_id = ?");
            $sharingStmt->bind_param("ss", $note_id, $userid);
            $sharingStmt->execute();
            $sharingResult = $sharingStmt->get_result();
            
            $sharingInfo = [
                'id' => null,
                'shared_with_all' => false,
                'shared_with_emails' => []
            ];

            if ($sharingResult && $sharingResult->num_rows > 0) {
                $sharingData = $sharingResult->fetch_assoc();
                $sharingInfo['id'] = $sharingData['id'];
                $sharingInfo['shared_with_all'] = $sharingData['shared_with_all'];

                if (!$sharingData['shared_with_all']) {
                    $emailsStmt = $connection->prepare("SELECT email FROM shared_notes_emails WHERE share_id = ?");
                    $emailsStmt->bind_param("s", $sharingData['id']);
                    $emailsStmt->execute();
                    $emailsResult = $emailsStmt->get_result();

                    while ($emailRow = $emailsResult->fetch_assoc()) {
                        $sharingInfo['shared_with_emails'][] = $emailRow['email'];
                    }
                }
            }

            return [
                'status' => 'success',
                'data' => [
                    'note_id' => $note['note_id'],
                    'note_name' => $note['note_name'],
                    'note_content' => $note['note'],
                    'note_images' => $images,
                    'checklist' => $checklist,
                    'sharing_info' => $sharingInfo,
                ],
            ];
        } else {
            return [
                'status' => 'error',
                'message' => "Note with ID $note_id not found for the user."
            ];
        }

    } catch (mysqli_sql_exception $e) {
        return [
            'status' => 'error',
            'message' => 'Exception occurred: ' . $e->getMessage()
        ];
    }
}

// pin & unpin note
function pinNote($note_id, $userid) {
    global $connection;

    try {
        $note_id = mysqli_real_escape_string($connection, $note_id);
        $userid = mysqli_real_escape_string($connection, $userid);

        $stmt = $connection->prepare("SELECT pinned FROM notes WHERE note_id = ? AND user_id = ?");
        $stmt->bind_param("is", $note_id, $userid);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $pinned = false;
            $stmt->bind_result($pinned);
            $stmt->fetch();

            $pin = ($pinned == 1) ? 0 : 1;
            $pin_order = ($pin == 1) ? time() : NULL;

            $updateStmt = $connection->prepare("
                UPDATE notes 
                SET pinned = ?, pin_order = ? 
                WHERE note_id = ? AND user_id = ?
            ");
            $updateStmt->bind_param("iiss", $pin, $pin_order, $note_id, $userid);
            $updateStmt->execute();

            if ($updateStmt->affected_rows > 0) {
                return [
                    'status' => 'success',
                    'pin' => ($pin == 1),  // Return true if pinned, false otherwise
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
    } catch (mysqli_sql_exception $e) {
        return [
            'status' => 'error',
            'message' => 'Exception occurred: ' . $e->getMessage(),
        ];
    }
}

// note sharing
function shareNoteAdd($share_id, $note_id, $userid) {
    global $connection;

    try {
        $note_id = mysqli_real_escape_string($connection, $note_id);
        $share_id = mysqli_real_escape_string($connection, $share_id);

        $stmt = $connection->prepare("SELECT * FROM notes WHERE note_id = ? AND user_id = ?");
        $stmt->bind_param("is", $note_id, $userid);
        $stmt->execute();
        $note_result = $stmt->get_result();

        if ($note_result && $note_result->num_rows > 0) {
            $stmtCheckShared = $connection->prepare("SELECT * FROM shared_notes WHERE id = ? AND note_id = ? AND user_id = ?");
            $stmtCheckShared->bind_param("iss", $share_id, $note_id, $userid);
            $stmtCheckShared->execute();
            $check_shared_result = $stmtCheckShared->get_result();

            if ($check_shared_result && $check_shared_result->num_rows > 0) {
                return ['status' => 'error', 'message' => 'Note is already shared with the same user'];
            }

            $stmtInsertShare = $connection->prepare("INSERT INTO shared_notes (id, note_id, user_id, shared_with_all) VALUES (?, ?, ?, ?)");
            $shared_with_all = false;
            $stmtInsertShare->bind_param("sssi", $share_id, $note_id, $userid, $shared_with_all);
            $stmtInsertShare->execute();

            if ($stmtInsertShare->affected_rows > 0) {
                return ['status' => 'success', 'message' => 'Note shared successfully'];
            } else {
                return ['status' => 'error', 'message' => 'Error sharing note'];
            }
        } else {
            return ['status' => 'error', 'message' => 'Error: Note not found or does not belong to the user'];
        }
    } catch (mysqli_sql_exception $e) {
        return ['status' => 'error', 'message' => 'Exception occurred: ' . $e->getMessage()];
    }
}

// note sharing delete
function ShareNoteRemove($share_id, $note_id, $user_id) {
    global $connection;

    try {
        $stmt = $connection->prepare("SELECT * FROM shared_notes WHERE id = ? AND note_id = ? AND user_id = ?");
        $stmt->bind_param("sss", $share_id, $note_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $deleteStmt = $connection->prepare("DELETE FROM shared_notes WHERE id = ? AND note_id = ? AND user_id = ?");
            $deleteStmt->bind_param("sss", $share_id, $note_id, $user_id);
            $deleteStmt->execute();

            if ($deleteStmt->affected_rows > 0) {
                return ['status' => 'success', 'message' => 'Shared note removed successfully'];
            } else {
                return ['status' => 'error', 'message' => 'Error deleting shared note'];
            }
        } else {
            return ['status' => 'error', 'message' => 'No matching shared note found'];
        }
    } catch (mysqli_sql_exception $e) {
        return ['status' => 'error', 'message' => 'Exception occurred: ' . $e->getMessage()];
    }
}

// note sharing add user
function shareNoteUserAdd($share_id, $mail, $user_id) {
    global $connection;

    try {
        // Check if the user is already added to the shared note
        $query_check = "SELECT * FROM shared_notes_emails WHERE share_id = ? AND email = ? AND user_id = ?";
        $stmt_check = $connection->prepare($query_check);
        $stmt_check->bind_param("sss", $share_id, $mail, $user_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            return ['status' => 'error', 'message' => 'User already added to this shared note'];
        }

        // Check if the email exists in the users table
        $query_user_email = "SELECT mail FROM users WHERE mail = ?";
        $stmt_user_email = $connection->prepare($query_user_email);
        $stmt_user_email->bind_param("s", $mail);
        $stmt_user_email->execute();
        $result_user_email = $stmt_user_email->get_result();

        if ($result_user_email->num_rows > 0) {
            // Add user to the shared notes emails table
            $query_add_user = "INSERT INTO shared_notes_emails (share_id, email, user_id) VALUES (?, ?, ?)";
            $stmt_add_user = $connection->prepare($query_add_user);
            $stmt_add_user->bind_param("sss", $share_id, $mail, $user_id);

            if ($stmt_add_user->execute()) {
                return ['status' => 'success', 'message' => 'User added to shared note'];
            } else {
                return ['status' => 'error', 'message' => 'Failed to add user to shared note'];
            }
        } else {
            return ['status' => 'error', 'message' => 'User not found'];
        }
    } catch (mysqli_sql_exception $e) {
        return ['status' => 'error', 'message' => 'Exception occurred: ' . $e->getMessage()];
    }
}

// note sharing remove user
function shareNoteUserRemove($share_id, $mail, $user_id) {
    global $connection;

    try {
        // Check if the user exists in the users table
        $query_user_email = "SELECT mail FROM users WHERE mail = ?";
        $stmt_user_email = $connection->prepare($query_user_email);
        $stmt_user_email->bind_param("s", $mail);
        $stmt_user_email->execute();
        $result_user_email = $stmt_user_email->get_result();

        if ($result_user_email->num_rows > 0) {
            // Remove the user from the shared notes emails table
            $query_remove_user = "DELETE FROM shared_notes_emails WHERE share_id = ? AND email = ? AND user_id = ?";
            $stmt_remove_user = $connection->prepare($query_remove_user);
            $stmt_remove_user->bind_param("sss", $share_id, $mail, $user_id);

            if ($stmt_remove_user->execute()) {
                return ['status' => 'success', 'message' => 'User removed from shared note'];
            } else {
                return ['status' => 'error', 'message' => 'Failed to remove user from shared note'];
            }
        } else {
            return ['status' => 'error', 'message' => 'User not found'];
        }
    } catch (mysqli_sql_exception $e) {
        return ['status' => 'error', 'message' => 'Exception occurred: ' . $e->getMessage()];
    }
}

// note share change visiblity
function shareNoteVisibility($share_id, $note_id, $visibility, $user_id) {
    global $connection;

    try {
        $share_id = mysqli_real_escape_string($connection, $share_id);
        $note_id = mysqli_real_escape_string($connection, $note_id);
        $visibility = (int) $visibility; 
        $user_id = mysqli_real_escape_string($connection, $user_id);

        $stmt = $connection->prepare("SELECT * FROM shared_notes WHERE id = ? AND note_id = ? AND user_id = ?");
        $stmt->bind_param("sss", $share_id, $note_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $updateStmt = $connection->prepare("UPDATE shared_notes SET shared_with_all = ? WHERE id = ? AND note_id = ? AND user_id = ?");
            $updateStmt->bind_param("isss", $visibility, $share_id, $note_id, $user_id);
            if ($updateStmt->execute()) {
                return ['status' => 'success', 'message' => 'Sharing visibility updated'];
            } else {
                return ['status' => 'error', 'message' => 'Failed to update sharing visibility'];
            }
        } else {
            return ['status' => 'error', 'message' => 'Shared note not found'];
        }
    } catch (mysqli_sql_exception $e) {
        return ['status' => 'error', 'message' => 'Exception occurred: ' . $e->getMessage()];
    }
}

// note find & share info
function ShareNoteGet($share_id, $user_mail) {
    global $connection;

    try {
        $stmt = $connection->prepare("SELECT note_id, shared_with_all FROM shared_notes WHERE id = ?");
        $stmt->bind_param("s", $share_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return ['status' => 'error', 'message' => 'Note not found.'];
        }

        $row = $result->fetch_assoc();
        $note_id = $row['note_id'];
        $shared_with_all = $row['shared_with_all'];
        $hasAccess = false;

        if ($shared_with_all == 1) {
            $hasAccess = true;
        } else {
            $emailStmt = $connection->prepare("SELECT 1 FROM shared_notes_emails WHERE share_id = ? AND email = ?");
            $emailStmt->bind_param("ss", $share_id, $user_mail);
            $emailStmt->execute();
            $emailResult = $emailStmt->get_result();
            if ($emailResult->num_rows > 0) {
                $hasAccess = true;
            }
        }

        if ($hasAccess) {
            $noteStmt = $connection->prepare("SELECT * FROM notes WHERE note_id = ?");
            $noteStmt->bind_param("s", $note_id);
            $noteStmt->execute();
            $noteResult = $noteStmt->get_result();

            if ($noteResult->num_rows > 0) {
                return ['status' => 'success', 'data' => $noteResult->fetch_assoc()];
            }
            return ['status' => 'error', 'message' => 'Note content not found.'];
        }
        return [ 
            'redirct' => "'redirect' => './login.html?error=Access denied You do not have permission to view $share_id note login and try again&redirct=/shared.html/?id=$share_id"
        ];
    } catch (mysqli_sql_exception $e) {
        return ['status' => 'error', 'message' => 'Exception occurred: ' . $e->getMessage()];
    }
}


// get all note
function getAllNotes($userid, $Order) {
    global $connection;

    try {
        $query = "SELECT * FROM notes WHERE user_id = ? ORDER BY pinned DESC, pin_order DESC";

        // For Sorting
        if ($Order === 'asc') {
            $query .= ", note_name ASC";
        } elseif ($Order === 'desc') {
            $query .= ", note_name DESC";
        }

        $stmt = $connection->prepare($query);
        $stmt->bind_param('s', $userid);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $data = [];
            while ($row = $result->fetch_assoc()) {
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

        return []; 
    } catch (mysqli_sql_exception $e) {
        return ['status' => 'error', 'message' => 'Exception occurred: ' . $e->getMessage()];
    }
}
?>