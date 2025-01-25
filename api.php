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
        if (!empty($loginCheck["user"])) {
            $user = $loginCheck["user"];
            // set id
            if (!empty($user["id"])) {
                $userid = $user["id"];
            }
            // set username
            if (!empty($user["name"])) {
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
                    if (empty($params['note_id'])) {
                        $response = ['error' => 'Missing note_id parameters for addNote'];
                    } else {
                        $response = addNote($params['note_id'], $userid);
                    }
                } else {
                    $response = ['redirect' => './login.php'];
                }
                break;

            case 'removeNote':
                if ($LoggedIn) {
                    if (empty($params['note_id'])) {
                        $response = ['error' => 'Missing note_id for removeNote'];
                    } else {
                        $response = removeNote($params['note_id'], $userid);
                    }
                } else {
                    $response = ['redirect' => './login.php'];
                }
                break;

            case "getNote":
                if ($LoggedIn) {
                    if (empty($params['note_id'])) {
                        $response = ['error' => 'Missing note_id for getNote'];
                    } else {
                        $response = getNote($params['note_id'], $userid);
                    }
                } else {
                    $response = ['redirect' => './login.php'];
                }
                break;

            case 'updateNote':
                if (empty($params['note_id']) || empty($params['new_note_name']) || empty($params['new_note_content'])) {
                    $response = ['error' => 'Missing required parameters for updateNote'];
                } else {
                    $response = updateNote($params['note_id'], $params['new_note_name'], $params['new_note_content'], $params['new_images'], $userid);
                }
                break;

            case 'findNote':
                if ($LoggedIn) {
                    if (empty($params['word'])) {
                        $response = ['error' => 'Missing word for findNote'];
                    } else {
                        $response = findNote($params['word'], $userid);
                    }
                } else {
                    $response = ['redirect' => './login.php'];
                }
                break;

            case 'getAllNotes':
                if ($LoggedIn) {
                    $response = getAllNotes($userid);
                } else {
                    $response = ['redirect' => './login.php'];
                }
                break;

            case 'signup':
                if (empty($params['user_id']) || empty($params['email']) || empty($params['pass']) || empty($params['username'])) {
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
                if (empty($params['username']) || empty($params['pass'])) {
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
