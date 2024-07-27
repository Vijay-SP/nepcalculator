<?php
ob_start();
include_once 'psl-config.php';

function sec_session_start() {
$session_name = 'Convostatus';   // Set a custom session name
$secure = FALSE;
// This stops JavaScript being able to access the session id.
$httponly = true;
// Forces sessions to only use cookies.
if (ini_set('session.use_only_cookies', 1) === FALSE) {
header("Location: ../error.php?err=Could not initiate a safe session (ini_set)");
exit();
}
// Gets current cookies params.
$cookieParams = session_get_cookie_params();
session_set_cookie_params($cookieParams["lifetime"],
 $cookieParams["path"],
 $cookieParams["domain"],
 $secure,
 $httponly);
// Sets the session name to the one set above.
session_name($session_name);
session_start();            // Start the PHP session 
session_regenerate_id();    // regenerated the session, delete the old one. 
}


function login($email, $password, $mysqli) {
// Using prepared statements means that SQL injection is not possible. 
$sql = "SELECT `Email`,`Password`,`ID`,`Collname` FROM `regop` WHERE `Email`= ?";
$stmt = $mysqli->prepare($sql);
if ($stmt === false) {
trigger_error('Wrong SQL: ' . $sql . ' Error: ' . $mysqli->error, E_USER_ERROR);
}
$stmt->bind_param('s', $email);
$stmt->execute();

$stmt->store_result();

// get variables from result.
$stmt->bind_result($email, $db_password, $id, $collname);
$stmt->fetch();

// hash the password with the unique salt.
//$password = crypt($password, $db_password);
if ($stmt->num_rows == 1) {
// If the user exists we check if the account is locked
// from too many login attempts 

// Check if the password in the database matches
// the password the user submitted.
if ( hash_equals($db_password, crypt($password, $db_password))) {
// Password is correct!
// Get the user-agent string of the user.
$user_browser = $_SERVER['HTTP_USER_AGENT'];
// XSS protection as we might print this value
//$user_id = preg_replace("/[^0-9]+/", "", $email);
//$_SESSION['user_id'] = $email;
// XSS protection as we might print this value
$username = $email;//preg_replace("/[^a-zA-Z0-9_\-]+/","",$email);
$_SESSION['username'] = $username;
$_SESSION['login_string'] = hash('sha512',$db_password. $user_browser);
$_SESSION['ID'] = $id;
$_SESSION['collname'] = $collname;
// Login successful.
$now = time();
date_default_timezone_set("Asia/Calcutta"); //India time (GMT+5:30) echo date('d-m-Y H:i:s'); 
$timestamp = date('Y-m-d H:i:s'); 
   
$ip = $_SERVER['REMOTE_ADDR'];
$mysqli->query("INSERT INTO loginattempts(email, time, IP,login) VALUES ('$email', '$timestamp', '$ip',1)");

return true;
} else {
// Password is not correct
// We record this attempt in the database
$now = time();
date_default_timezone_set("Asia/Calcutta"); //India time (GMT+5:30) echo date('d-m-Y H:i:s'); 
$timestamp = date('Y-m-d H:i:s'); 
   
$ip = $_SERVER['REMOTE_ADDR'];
$mysqli->query("INSERT INTO loginattempts(email, time, IP,login) VALUES ('$email', '$timestamp', '$ip',0)");
return false;
}

} else {
// No user exists.
return false;
}
}

function login_check($mysqli) {
    // Check if all session variables are set 
    if (isset($_SESSION['username'],$_SESSION['login_string'])) {
 
        //$user_id = $_SESSION['user_id'];
        $login_string = $_SESSION['login_string'];
        $username = $_SESSION['username'];
 
        // Get the user-agent string of the user.
        $user_browser = $_SERVER['HTTP_USER_AGENT'];
 
        if ($stmt = $mysqli->prepare("SELECT Password 
                                      FROM regop 
                                      WHERE Email = ? LIMIT 1")) {
            // Bind "$user_id" to parameter. 
            
            $stmt->bind_param('s', $username);
            $stmt->execute();   // Execute the prepared query.
            $stmt->store_result();
  
            if ($stmt->num_rows == 1) {
                // If the user exists get variables from result.
                $stmt->bind_result($password);
                $stmt->fetch();
                $login_check = hash('sha512', $password . $user_browser);
                if ($login_check == $login_string) {
                    // Logged In!!!! 
                    return true;
                } else {
                    // Not logged in 
                    
                    return false;
                }
            } else {
                // Not logged in
                
                return false;
            }
        } else {
            // Not logged in
            
            return false;
        }
    } else {
        // Not logged in
        
        return false;
    }
}
function esc_url($url) {
 
    if ('' == $url) {
        return $url;
    }
 
    $url = preg_replace('|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\\x80-\\xff]|i', '', $url);
 
    $strip = array('%0d', '%0a', '%0D', '%0A');
    $url = (string) $url;
 
    $count = 1;
    while ($count) {
        $url = str_replace($strip, '', $url, $count);
    }
 
    $url = str_replace(';//', '://', $url);
 
    $url = htmlentities($url);
 
    $url = str_replace('&amp;', '&#038;', $url);
    $url = str_replace("'", '&#039;', $url);
 
    if ($url[0] !== '/') {
        // We're only interested in relative links from $_SERVER['PHP_SELF']
        return '';
    } else {
        return $url;
    }
}

ob_end_flush();
?>