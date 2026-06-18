<?php
require_once __DIR__ . '/../inc/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

function login_fail($message, $email = '') {
    $_SESSION['auth_error'] = $message;
    header('Location: login.php?email=' . urlencode($email));
    exit;
}

if ($email === '' || $password === '') {
    login_fail('이메일과 비밀번호를 모두 입력해주세요.', $email);
}

$sql = "
    SELECT id, email, name, password, nickname, field, profile_image_saved, role, status
    FROM blog_members
    WHERE email = ?
    LIMIT 1
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 's', $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$member = mysqli_fetch_assoc($result);

if (!$member) {
    login_fail('가입되지 않은 이메일입니다.', $email);
}

if ($member['status'] !== 'active') {
    login_fail('현재 사용할 수 없는 계정입니다.', $email);
}

if (!password_verify($password, $member['password'])) {
    login_fail('비밀번호가 일치하지 않습니다.', $email);
}

session_regenerate_id(true);
$_SESSION['member_id'] = (int)$member['id'];
$_SESSION['member_email'] = $member['email'];
$_SESSION['member_name'] = $member['name'];
$_SESSION['member_nickname'] = $member['nickname'];
$_SESSION['member_field'] = $member['field'];
$_SESSION['member_profile'] = $member['profile_image_saved'];
$_SESSION['member_role'] = $member['role'];
$_SESSION['is_login'] = true;

header('Location: ../index.php');
exit;
