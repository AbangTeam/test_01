<?php
require_once __DIR__ . '/../inc/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}

$email = trim($_POST['email'] ?? '');
$name = trim($_POST['name'] ?? '');
$password = $_POST['password'] ?? '';
$passwordConfirm = $_POST['password_confirm'] ?? '';
$nickname = trim($_POST['nickname'] ?? '');
$field = trim($_POST['field'] ?? '');
$intro = trim($_POST['intro'] ?? '');

$_SESSION['auth_old'] = [
    'email' => $email,
    'name' => $name,
    'nickname' => $nickname,
    'field' => $field,
    'intro' => $intro,
];

function back_with_error($message) {
    $_SESSION['auth_error'] = $message;
    header('Location: register.php');
    exit;
}

if ($email === '' || $name === '' || $password === '' || $passwordConfirm === '' || $nickname === '' || $field === '') {
    back_with_error('필수 입력값을 모두 입력해주세요.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    back_with_error('올바른 이메일 형식으로 입력해주세요.');
}

if (strlen($password) < 6) {
    back_with_error('비밀번호는 6자 이상 입력해주세요.');
}

if ($password !== $passwordConfirm) {
    back_with_error('비밀번호와 비밀번호 확인이 일치하지 않습니다.');
}

// 카테고리 테이블에서 가져온 관심분야만 허용
$categoryCheck = mysqli_prepare($conn, "SELECT id FROM blog_categories WHERE name = ? LIMIT 1");
mysqli_stmt_bind_param($categoryCheck, 's', $field);
mysqli_stmt_execute($categoryCheck);
$categoryResult = mysqli_stmt_get_result($categoryCheck);
if (!mysqli_fetch_assoc($categoryResult)) {
    back_with_error('선택할 수 없는 관심분야입니다.');
}

// 이메일 / 닉네임 중복 확인
$dupSql = "SELECT id FROM blog_members WHERE email = ? OR nickname = ? LIMIT 1";
$dupStmt = mysqli_prepare($conn, $dupSql);
mysqli_stmt_bind_param($dupStmt, 'ss', $email, $nickname);
mysqli_stmt_execute($dupStmt);
$dupResult = mysqli_stmt_get_result($dupStmt);
if (mysqli_fetch_assoc($dupResult)) {
    back_with_error('이미 사용 중인 이메일 또는 닉네임입니다.');
}

$profileOriginal = null;
$profileSaved = null;

if (!empty($_FILES['profile_image']['name'])) {
    $file = $_FILES['profile_image'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        back_with_error('프로필 이미지 업로드에 실패했습니다.');
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        back_with_error('프로필 이미지는 최대 5MB까지 업로드할 수 있습니다.');
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    $mime = mime_content_type($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        back_with_error('프로필 이미지는 JPG 또는 PNG만 업로드할 수 있습니다.');
    }

    $uploadDir = __DIR__ . '/../uploads/profile/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $profileOriginal = $file['name'];
    $profileSaved = 'profile_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $profileSaved)) {
        back_with_error('프로필 이미지를 저장하지 못했습니다.');
    }
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$insertSql = "
    INSERT INTO blog_members
    (email, name, password, nickname, field, profile_image_original, profile_image_saved, intro)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
";
$stmt = mysqli_prepare($conn, $insertSql);
mysqli_stmt_bind_param(
    $stmt,
    'ssssssss',
    $email,
    $name,
    $hashedPassword,
    $nickname,
    $field,
    $profileOriginal,
    $profileSaved,
    $intro
);

if (!mysqli_stmt_execute($stmt)) {
    back_with_error('회원가입 중 오류가 발생했습니다: ' . mysqli_error($conn));
}

unset($_SESSION['auth_old']);
$_SESSION['auth_success'] = '회원가입이 완료되었습니다. 로그인해주세요.';
header('Location: login.php?email=' . urlencode($email));
exit;
