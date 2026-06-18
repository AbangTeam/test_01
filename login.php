<?php
require_once __DIR__ . '/../inc/db.php';

$error = $_SESSION['auth_error'] ?? '';
$success = $_SESSION['auth_success'] ?? '';
unset($_SESSION['auth_error'], $_SESSION['auth_success']);

$email = $_GET['email'] ?? '';
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>로그인 - 아방로그</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body class="auth-body login-page">
    <main class="screen">
        <section class="auth-card" aria-label="로그인">
            <form class="login-panel" action="login_process.php" method="post">
                <h1 class="auth-title">로그인</h1>
                <p class="auth-subtitle">아방로그에 오신 것을 환영합니다!</p>

                <?php if ($success): ?>
                    <div class="form-success"><?= h($success) ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="form-alert"><?= h($error) ?></div>
                <?php endif; ?>

                <div class="field-group">
                    <div class="input-wrap">
                        <span class="input-icon" aria-hidden="true">
                            <svg width="23" height="23" viewBox="0 0 24 24" fill="none">
                                <path d="M12 12.5C14.4853 12.5 16.5 10.4853 16.5 8C16.5 5.51472 14.4853 3.5 12 3.5C9.51472 3.5 7.5 5.51472 7.5 8C7.5 10.4853 9.51472 12.5 12 12.5Z" stroke="currentColor" stroke-width="1.8" />
                                <path d="M4.5 20.5C5.34 16.95 8.05 15 12 15C15.95 15 18.66 16.95 19.5 20.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                        </span>
                        <input
                            class="form-input"
                            type="email"
                            name="email"
                            value="<?= h($email) ?>"
                            placeholder="아이디 또는 이메일"
                            autocomplete="username"
                            required
                        >
                    </div>

                    <div class="input-wrap">
                        <span class="input-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                                <path d="M7 10V8C7 5.23858 9.23858 3 12 3C14.7614 3 17 5.23858 17 8V10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                <rect x="5" y="10" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.8" />
                                <path d="M12 14V16.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                        </span>
                        <input
                            id="loginPassword"
                            class="form-input"
                            type="password"
                            name="password"
                            placeholder="비밀번호"
                            autocomplete="current-password"
                            required
                        >
                        <button class="right-icon" type="button" data-toggle-password="loginPassword" aria-label="비밀번호 보기">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                                <path d="M3 3L21 21" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                <path d="M10.6 5.1C11.06 5.03 11.53 5 12 5C17.5 5 21 12 21 12C20.23 13.56 19.21 14.91 18.02 15.97M15 18.12C14.05 18.68 13.04 19 12 19C6.5 19 3 12 3 12C4.04 9.89 5.57 8.12 7.38 6.93" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M9.88 9.88C9.34 10.42 9 11.17 9 12C9 13.66 10.34 15 12 15C12.83 15 13.58 14.66 14.12 14.12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="login-options">
                    <label class="check-row">
                        <input type="checkbox" name="remember" value="1">
                        <span>로그인 유지</span>
                    </label>
                </div>

                <button class="primary-btn" type="submit">로그인</button>

                <div class="link-row">
                    <a class="text-btn" href="#" onclick="alert('ID/PW 찾기 화면은 추후 연결하면 됩니다.'); return false;">ID/PW 찾기</a>
                    <span class="divider">|</span>
                    <a class="text-btn" href="register.php">회원가입</a>
                </div>
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
</script>
</body>
</html>
