<?php
require_once __DIR__ . '/../inc/db.php';

$categoryOrder = ['일상', '여행', '카페'];
$categories = [];
$sql = "SELECT name FROM blog_categories WHERE name IN ('일상', '여행', '카페') ORDER BY FIELD(name, '일상', '여행', '카페')";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row['name'];
    }
}

// DB에 아직 3개 카테고리가 없을 때 화면이 비지 않도록 최소 표시값 유지
if (!$categories) {
    $categories = $categoryOrder;
}

$error = $_SESSION['auth_error'] ?? '';
$old = $_SESSION['auth_old'] ?? [];
unset($_SESSION['auth_error'], $_SESSION['auth_old']);
$introLength = mb_strlen($old['intro'] ?? '', 'UTF-8');
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>회원가입 - 아방로그</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body class="auth-body register-page">
    <main class="screen">
        <section class="signup-shell" aria-label="회원가입">
            <h1 class="signup-title">회원 가입을 위해<br>정보를 입력해주세요</h1>
            <p class="signup-caption">프로필 이미지, 필수 정보, 관심 분야를 설정할 수 있습니다.</p>

            <?php if ($error): ?>
                <div class="form-alert"><?= h($error) ?></div>
            <?php endif; ?>

            <form class="signup-form" action="register_process.php" method="post" enctype="multipart/form-data">
                <div>
                    <span class="field-label">프로필 이미지</span>
                    <div class="profile-row">
                        <div class="profile-preview" id="profilePreview" aria-hidden="true">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                                <path d="M12 12.5C14.4853 12.5 16.5 10.4853 16.5 8C16.5 5.51472 14.4853 3.5 12 3.5C9.51472 3.5 7.5 5.51472 7.5 8C7.5 10.4853 9.51472 12.5 12 12.5Z" fill="#c8ced8" />
                                <path d="M4.7 20.2C5.6 16.8 8.2 15 12 15C15.8 15 18.4 16.8 19.3 20.2" fill="#c8ced8" />
                            </svg>
                        </div>
                        <div>
                            <label class="file-action" for="profileImage">이미지 업로드</label>
                            <input class="hidden-file" id="profileImage" name="profile_image" type="file" accept="image/png,image/jpeg">
                            <p class="file-help">JPG, PNG 파일 업로드 가능</p>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="field-label required" for="email">이메일</label>
                    <input class="signup-input" id="email" type="email" name="email" value="<?= h($old['email'] ?? '') ?>" placeholder="이메일을 입력해주세요" autocomplete="email" required>
                </div>

                <div>
                    <label class="field-label required" for="name">이름</label>
                    <input class="signup-input" id="name" type="text" name="name" value="<?= h($old['name'] ?? '') ?>" placeholder="이름을 입력해주세요" autocomplete="name" required>
                </div>

                <div class="password-field">
                    <label class="field-label required" for="password">비밀번호</label>
                    <input class="signup-input" id="password" type="password" name="password" placeholder="비밀번호를 입력해주세요" autocomplete="new-password" required>
                    <button class="mini-eye" type="button" data-toggle-password="password" aria-label="비밀번호 보기">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M3 3L21 21" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            <path d="M10.6 5.1C11.06 5.03 11.53 5 12 5C17.5 5 21 12 21 12C20.23 13.56 19.21 14.91 18.02 15.97M15 18.12C14.05 18.68 13.04 19 12 19C6.5 19 3 12 3 12C4.04 9.89 5.57 8.12 7.38 6.93" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </div>

                <div class="password-field">
                    <label class="field-label required" for="passwordConfirm">비밀번호 확인</label>
                    <input class="signup-input" id="passwordConfirm" type="password" name="password_confirm" placeholder="비밀번호를 다시 입력해주세요" autocomplete="new-password" required>
                    <button class="mini-eye" type="button" data-toggle-password="passwordConfirm" aria-label="비밀번호 확인 보기">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M3 3L21 21" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            <path d="M10.6 5.1C11.06 5.03 11.53 5 12 5C17.5 5 21 12 21 12C20.23 13.56 19.21 14.91 18.02 15.97M15 18.12C14.05 18.68 13.04 19 12 19C6.5 19 3 12 3 12C4.04 9.89 5.57 8.12 7.38 6.93" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </div>

                <div>
                    <label class="field-label required" for="nickname">닉네임</label>
                    <input class="signup-input" id="nickname" type="text" name="nickname" value="<?= h($old['nickname'] ?? '') ?>" placeholder="사용할 닉네임을 입력해주세요" required>
                </div>

                <div>
                    <label class="field-label required" for="field">관심 카테고리</label>
                    <select class="signup-select" id="field" name="field" required>
                        <option value="">관심 카테고리를 선택해주세요</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= h($category) ?>" <?= (($old['field'] ?? '') === $category) ? 'selected' : '' ?>><?= h($category) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="field-label" for="intro">자기소개</label>
                    <textarea class="form-textarea" id="intro" name="intro" maxlength="500" placeholder="자기소개를 입력해주세요."><?= h($old['intro'] ?? '') ?></textarea>
                    <div class="textarea-count"><span id="introCount"><?= h($introLength) ?></span> / 500</div>
                </div>

                <button class="signup-submit" type="submit">가입하기</button>
                <a class="back-login" href="login.php">← 로그인으로 돌아가기</a>
            </form>
        </section>
    </main>

<script>
document.querySelectorAll('[data-toggle-password]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.togglePassword);
        input.type = input.type === 'password' ? 'text' : 'password';
        input.focus();
    });
});

const intro = document.querySelector('#intro');
const introCount = document.querySelector('#introCount');
intro?.addEventListener('input', () => {
    introCount.textContent = intro.value.length;
});

const profileImage = document.querySelector('#profileImage');
const profilePreview = document.querySelector('#profilePreview');
profileImage?.addEventListener('change', () => {
    const file = profileImage.files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.addEventListener('load', () => {
        profilePreview.innerHTML = `<img src="${reader.result}" alt="업로드한 프로필 이미지 미리보기">`;
    });
    reader.readAsDataURL(file);
});
</script>
</body>
</html>
