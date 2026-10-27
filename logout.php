<?php
<<<<<<< HEAD

session_name('TEAM_SALES_SESSID');
session_start();

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Location: login.php');
exit;
=======
session_start();
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
?>
>>>>>>> origin/main
