<?php
require_once __DIR__ . '/../inc/db.php';

$memberId = filter_var($_SESSION['member_id'] ?? null, FILTER_VALIDATE_INT);
if (!$memberId) {
    header('Location: ../auth/login.php');
    exit;
}

$stmt = mysqli_prepare(
    $conn,
    'SELECT email, nickname, intro, profile_image_saved FROM blog_members WHERE id = ? AND status = \'active\' LIMIT 1'
);
if (!$stmt) {
    http_response_code(500);
    exit('회원 정보를 불러오지 못했습니다.');
}

mysqli_stmt_bind_param($stmt, 'i', $memberId);
mysqli_stmt_execute($stmt);
$member = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$member) {
    session_unset();
    session_destroy();
    header('Location: ../auth/login.php');
    exit;
}

if (empty($_SESSION['profile_csrf_token'])) {
    $_SESSION['profile_csrf_token'] = bin2hex(random_bytes(32));
}

$error = (string)($_SESSION['profile_error'] ?? '');
$success = (string)($_SESSION['profile_success'] ?? '');
$oldInput = $_SESSION['profile_old'] ?? [];
unset($_SESSION['profile_error'], $_SESSION['profile_success'], $_SESSION['profile_old']);

$email = array_key_exists('email', $oldInput) ? $oldInput['email'] : $member['email'];
$nickname = array_key_exists('nickname', $oldInput) ? $oldInput['nickname'] : $member['nickname'];
$intro = array_key_exists('intro', $oldInput) ? $oldInput['intro'] : ($member['intro'] ?? '');
$introLength = function_exists('mb_strlen') ? mb_strlen($intro, 'UTF-8') : strlen($intro);
$profileImage = $member['profile_image_saved']
    ? '../uploads/profile/' . rawurlencode($member['profile_image_saved'])
    : '';
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>프로필 수정</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body class="auth-body">
    <main class="screen">
        <section class="signup-shell" aria-labelledby="profile-title">
            <h1 class="signup-title" id="profile-title">프로필 수정</h1>
            <p class="signup-caption">프로필 이미지, 닉네임, 이메일과 소개를 변경할 수 있습니다.</p>

            <?php if ($error !== ''): ?>
                <div class="form-alert" role="alert"><?= h($error) ?></div>
            <?php endif; ?>

            <?php if ($success !== ''): ?>
                <div class="form-success" role="status"><?= h($success) ?></div>
            <?php endif; ?>

            <form class="signup-form" action="mypage_proc.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['profile_csrf_token']) ?>">

                <div>
                    <span class="field-label">프로필 이미지</span>
                    <div class="profile-row">
                        <div class="profile-preview" id="profilePreview">
                            <?php if ($profileImage !== ''): ?>
                                <img src="<?= h($profileImage) ?>" alt="현재 프로필 이미지">
                            <?php else: ?>
                                <span aria-hidden="true">사진</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="file-action" for="profileImage">이미지 선택</label>
                            <input class="hidden-file" id="profileImage" name="profile_image" type="file" accept="image/jpeg,image/png">
                            <p class="file-help">JPG, PNG / 최대 5MB. 선택하지 않으면 현재 이미지가 유지됩니다.</p>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="field-label required" for="nickname">닉네임</label>
                    <input class="signup-input" id="nickname" name="nickname" type="text" value="<?= h($nickname) ?>" maxlength="50" required>
                </div>

                <div>
                    <label class="field-label required" for="email">이메일</label>
                    <input class="signup-input" id="email" name="email" type="email" value="<?= h($email) ?>" maxlength="100" autocomplete="email" required>
                </div>

                <div>
                    <label class="field-label" for="intro">소개</label>
                    <textarea class="form-textarea" id="intro" name="intro" maxlength="500" placeholder="자신을 소개해 주세요."><?= h($intro) ?></textarea>
                    <div class="textarea-count"><span id="introCount"><?= h($introLength) ?></span> / 500</div>
                </div>

                <button class="signup-submit" type="submit">수정하기</button>
                <a class="back-login" href="mypage.php">마이페이지로 돌아가기</a>
            </form>
        </section>
    </main>

<script>
const intro = document.querySelector('#intro');
const introCount = document.querySelector('#introCount');
intro.addEventListener('input', () => {
    introCount.textContent = intro.value.length;
});

const profileImage = document.querySelector('#profileImage');
const profilePreview = document.querySelector('#profilePreview');
profileImage.addEventListener('change', () => {
    const file = profileImage.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.addEventListener('load', () => {
        profilePreview.innerHTML = '';
        const image = document.createElement('img');
        image.src = reader.result;
        image.alt = '선택한 프로필 이미지 미리보기';
        profilePreview.appendChild(image);
    });
    reader.readAsDataURL(file);
});
</script>
</body>
</html>
