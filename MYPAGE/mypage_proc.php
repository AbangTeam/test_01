<?php
require_once __DIR__ . '/../inc/db.php';

const PROFILE_MAX_FILE_SIZE = 5 * 1024 * 1024;
const PROFILE_INTRO_MAX_LENGTH = 500;

function profile_redirect_url(): string
{
    return ($_POST['return_to'] ?? '') === 'mypage' ? 'mypage.php' : 'profile_edit.php';
}

function redirect_to_profile_edit(string $message, array $oldInput = []): void
{
    $_SESSION['profile_error'] = $message;
    $_SESSION['profile_old'] = $oldInput;
    header('Location: ' . profile_redirect_url());
    exit;
}

function text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function store_profile_image(array $file, string $uploadDir): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('프로필 이미지 업로드에 실패했습니다.');
    }

    if (($file['size'] ?? 0) <= 0 || $file['size'] > PROFILE_MAX_FILE_SIZE) {
        throw new RuntimeException('프로필 이미지는 5MB 이하의 파일만 사용할 수 있습니다.');
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    $allowedTypes = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
    ];

    if ($imageInfo === false || !isset($allowedTypes[$imageInfo[2]])) {
        throw new RuntimeException('프로필 이미지는 JPG 또는 PNG 파일만 사용할 수 있습니다.');
    }

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('프로필 이미지 저장 폴더를 만들 수 없습니다.');
    }

    $savedName = 'profile_' . date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $allowedTypes[$imageInfo[2]];
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $savedName)) {
        throw new RuntimeException('프로필 이미지를 저장하지 못했습니다.');
    }

    $originalName = basename((string)($file['name'] ?? 'profile'));
    if (text_length($originalName) > 255) {
        $originalName = function_exists('mb_substr')
            ? mb_substr($originalName, 0, 255, 'UTF-8')
            : substr($originalName, 0, 255);
    }

    return [$originalName, $savedName];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile_edit.php');
    exit;
}

$memberId = filter_var($_SESSION['member_id'] ?? null, FILTER_VALIDATE_INT);
if (!$memberId) {
    header('Location: ../auth/login.php');
    exit;
}

$email = trim((string)($_POST['email'] ?? ''));
$nickname = trim((string)($_POST['nickname'] ?? ''));
$intro = trim((string)($_POST['intro'] ?? ''));
$oldInput = compact('email', 'nickname', 'intro');

$sessionToken = (string)($_SESSION['profile_csrf_token'] ?? '');
$requestToken = (string)($_POST['csrf_token'] ?? '');
if ($sessionToken === '' || !hash_equals($sessionToken, $requestToken)) {
    redirect_to_profile_edit('요청을 확인할 수 없습니다. 페이지를 새로고침한 뒤 다시 시도해 주세요.', $oldInput);
}

if ($email === '' || $nickname === '') {
    redirect_to_profile_edit('이메일과 닉네임을 모두 입력해 주세요.', $oldInput);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || text_length($email) > 100) {
    redirect_to_profile_edit('100자 이하의 올바른 이메일 주소를 입력해 주세요.', $oldInput);
}

if (text_length($nickname) > 50) {
    redirect_to_profile_edit('닉네임은 50자 이하로 입력해 주세요.', $oldInput);
}

if (text_length($intro) > PROFILE_INTRO_MAX_LENGTH) {
    redirect_to_profile_edit('소개는 500자 이하로 입력해 주세요.', $oldInput);
}

$memberStmt = mysqli_prepare(
    $conn,
    'SELECT profile_image_saved FROM blog_members WHERE id = ? AND status = \'active\' LIMIT 1'
);
if (!$memberStmt) {
    redirect_to_profile_edit('회원 정보를 확인하지 못했습니다.', $oldInput);
}

mysqli_stmt_bind_param($memberStmt, 'i', $memberId);
mysqli_stmt_execute($memberStmt);
$member = mysqli_fetch_assoc(mysqli_stmt_get_result($memberStmt));
mysqli_stmt_close($memberStmt);

if (!$member) {
    session_unset();
    session_destroy();
    header('Location: ../auth/login.php');
    exit;
}

$duplicateStmt = mysqli_prepare(
    $conn,
    'SELECT id FROM blog_members WHERE (email = ? OR nickname = ?) AND id <> ? LIMIT 1'
);
if (!$duplicateStmt) {
    redirect_to_profile_edit('프로필 중복 여부를 확인하지 못했습니다.', $oldInput);
}

mysqli_stmt_bind_param($duplicateStmt, 'ssi', $email, $nickname, $memberId);
mysqli_stmt_execute($duplicateStmt);
$duplicate = mysqli_fetch_assoc(mysqli_stmt_get_result($duplicateStmt));
mysqli_stmt_close($duplicateStmt);

if ($duplicate) {
    redirect_to_profile_edit('이미 사용 중인 이메일 또는 닉네임입니다.', $oldInput);
}

$uploadDir = __DIR__ . '/../uploads/profile/';
$newOriginalName = null;
$newSavedName = null;
$hasNewImage = isset($_FILES['profile_image'])
    && ($_FILES['profile_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

if ($hasNewImage) {
    try {
        [$newOriginalName, $newSavedName] = store_profile_image($_FILES['profile_image'], $uploadDir);
    } catch (RuntimeException $exception) {
        redirect_to_profile_edit($exception->getMessage(), $oldInput);
    } catch (Throwable $exception) {
        redirect_to_profile_edit('프로필 이미지 처리 중 오류가 발생했습니다.', $oldInput);
    }
}

if ($hasNewImage) {
    $updateSql = '
        UPDATE blog_members
        SET email = ?, nickname = ?, intro = ?, profile_image_original = ?, profile_image_saved = ?
        WHERE id = ?
    ';
    $updateStmt = mysqli_prepare($conn, $updateSql);
    if ($updateStmt) {
        mysqli_stmt_bind_param(
            $updateStmt,
            'sssssi',
            $email,
            $nickname,
            $intro,
            $newOriginalName,
            $newSavedName,
            $memberId
        );
    }
} else {
    $updateSql = 'UPDATE blog_members SET email = ?, nickname = ?, intro = ? WHERE id = ?';
    $updateStmt = mysqli_prepare($conn, $updateSql);
    if ($updateStmt) {
        mysqli_stmt_bind_param($updateStmt, 'sssi', $email, $nickname, $intro, $memberId);
    }
}

if (!$updateStmt || !mysqli_stmt_execute($updateStmt)) {
    if ($newSavedName !== null && is_file($uploadDir . $newSavedName)) {
        unlink($uploadDir . $newSavedName);
    }

    $errorCode = mysqli_errno($conn);
    if ($updateStmt) {
        mysqli_stmt_close($updateStmt);
    }

    $message = $errorCode === 1062
        ? '이미 사용 중인 이메일 또는 닉네임입니다.'
        : '프로필을 저장하지 못했습니다. 잠시 후 다시 시도해 주세요.';
    redirect_to_profile_edit($message, $oldInput);
}

mysqli_stmt_close($updateStmt);

$oldSavedName = basename((string)$member['profile_image_saved']);
if ($newSavedName !== null && $oldSavedName !== '' && is_file($uploadDir . $oldSavedName)) {
    unlink($uploadDir . $oldSavedName);
}

$_SESSION['member_email'] = $email;
$_SESSION['member_nickname'] = $nickname;
if ($newSavedName !== null) {
    $_SESSION['member_profile'] = $newSavedName;
}

unset($_SESSION['profile_error'], $_SESSION['profile_old']);
$_SESSION['profile_success'] = '프로필이 수정되었습니다.';
$_SESSION['profile_csrf_token'] = bin2hex(random_bytes(32));

header('Location: ' . profile_redirect_url());
exit;
