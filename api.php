<?php
require './db/main.php';
$LoggedIn = false;
$username = "";
$userid = "";

if (isset($_COOKIE['token'])) {
    $token = $_COOKIE['token'];
    $loginCheck = login($token);
    if ($loginCheck['status'] !== 'success') {
        setcookie('token', '', time() - 3600, '/');
        $LoggedIn = false;
    } else {
        if (isset($loginCheck["user"])) {
            $user = $loginCheck["user"];
            // set id
            if (isset($user["id"])) {
                $userid = $user["id"];
            }
            // set username
            if (isset($user["name"])) {
                $username = $user["name"];
            }
            $LoggedIn = true;
        } else {
            $LoggedIn = false;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $action = $input['action'] ?? null;
    $params = $input['params'] ?? [];

    $response = [];

    if ($action === null) {
        $response = ['error' => 'Missing action'];
    } else {
        switch ($action) {
            case 'addNote':
                if ($LoggedIn) {
                    if (!isset($params['note_id'])) {
                        $response = ['error' => 'Missing note_id parameters for addNote'];
                    } else {
                        $response = addNote($params['note_id'], $userid);
                    }
                } else {
                    $response = ['redirect' => './login.php?error=You Need To Login First To Access'];
                }
                break;

            case 'removeNote':
                if ($LoggedIn) {
                    if (!isset($params['note_id'])) {
                        $response = ['error' => 'Missing note_id for removeNote'];
                    } else {
                        $response = removeNote($params['note_id'], $userid);
                    }
                } else {
                    $response = ['redirect' => './login.php?error=You Need To Login First To Access'];
                }
                break;

            case "getNote":
                if ($LoggedIn) {
                    if (!isset($params['note_id'])) {
                        $response = ['error' => 'Missing note_id for getNote'];
                    } else {
                        $response = getNote($params['note_id'], $userid);
                    }
                } else {
                    $response = ['redirect' => './login.php?error=You Need To Login First To Access'];
                }
                break;

            case 'updateNote':
                if ($LoggedIn) {
                    if (!isset($params['note_id']) || !isset($params['new_note_name']) || !isset($params['new_note_content']) || !isset($params['new_checklists'])) {
                        $response = ['error' => 'Missing required parameters for updateNote'];
                    } else {
                        $response = updateNote($params['note_id'], $params['new_note_name'], $params['new_note_content'], $params['new_images'], $params['new_checklists'] , $userid);
                    }
                }
                break;

            case 'findNote':
                if ($LoggedIn) {
                    if (!isset($params['word'])) {
                        $response = ['error' => 'Missing word for findNote'];
                    } else {
                        $response = findNote($params['word'], $userid);
                    }
                } else {
                    $response = ['redirect' => './login.php?error=You Need To Login First To Access'];
                }
                break;

            case 'getAllNotes':
                if ($LoggedIn) {
                    $data = getAllNotes($userid, $params['order'] ?? null);
                    $response = [
                        "data" => $data,
                        "username" => $username
                    ];
                } else {
                    $response = ['redirect' => './login.php?error=You Need To Login First To Access'];
                }
                break;

            case 'pinNote':
                if ($LoggedIn){
                    if (!isset($params['note_id'])){
                        $response = ['error' => 'Missing required parameters'];
                    } else {
                        $response = pinNote($params['note_id'], $userid);
                    }
                } else {
                    $response = ['redirect' => './login.php?error=You Need To Login First To Access'];
                }

                break;
            
            case 'signup':
                if (!isset($params['user_id']) || !isset($params['email']) || !isset($params['pass']) || !isset($params['username'])) {
                    $response = ['error' => 'Missing required parameters for signup'];
                } else {
                    $response = signup(
                        $params['user_id'],
                        $params['email'],
                        $params['pass'],
                        $params['username']
                    );

                    $userid = $params["user_id"];
                    $username = $params["username"];

                    if ($response['status'] === 'success') {
                        if (isset($response['token'])) {
                            setcookie('token', $response['token'], time() + (3600 * 24), '/');
                            $LoggedIn = true;
                        } else {
                            $LoggedIn = false;
                            $userid = false;
                            $username = false;
                        }
                    }
                }
                break;

            case 'login':
                if (!isset($params['username']) || !isset($params['pass'])) {
                    $response = ['error' => 'Missing username or password for login'];
                } else {
                    $response = loginWithPassword($params['username'], $params['pass']);
                    if ($response['status'] === 'success') {
                        $username = $params["username"];

                        if (isset($response['token'])) {
                            setcookie('token', $response['token'], time() + (3600 * 24), '/');
                            if (isset($response["user"])) {
                                $user = $response["user"];
                                if (isset($user['id'])) {
                                    $userid = $user['id'];
                                }
                            }
                            $LoggedIn = true;
                        } else {
                            $LoggedIn = false;
                            $username = "";
                            $userid = "";
                        }
                    }
                }
                break;

            default:
                $response = ['error' => 'Invalid action'];
                break;
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>
