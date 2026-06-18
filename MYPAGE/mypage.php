<?php
require_once __DIR__ . '/../inc/db.php';

$memberId = filter_var($_SESSION['member_id'] ?? null, FILTER_VALIDATE_INT);
if (!$memberId) {
    header('Location: ../auth/login.php');
    exit;
}

$memberStmt = mysqli_prepare(
    $conn,
    'SELECT id, email, nickname, intro, profile_image_saved FROM blog_members WHERE id = ? AND status = \'active\' LIMIT 1'
);
if (!$memberStmt) {
    http_response_code(500);
    exit('회원 정보를 불러오지 못했습니다.');
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

function get_member_count(mysqli $conn, string $table, int $memberId): int
{
    $allowedTables = ['blog_posts', 'blog_comments', 'blog_likes'];
    if (!in_array($table, $allowedTables, true)) {
        throw new InvalidArgumentException('허용되지 않은 테이블입니다.');
    }

    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS count FROM {$table} WHERE member_id = ?");
    if (!$stmt) {
        return 0;
    }

    mysqli_stmt_bind_param($stmt, 'i', $memberId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return (int)($row['count'] ?? 0);
}

if (empty($_SESSION['profile_csrf_token'])) {
    $_SESSION['profile_csrf_token'] = bin2hex(random_bytes(32));
}

$profileError = (string)($_SESSION['profile_error'] ?? '');
$profileSuccess = (string)($_SESSION['profile_success'] ?? '');
$profileOld = $_SESSION['profile_old'] ?? [];
unset($_SESSION['profile_error'], $_SESSION['profile_success'], $_SESSION['profile_old']);

$user = [
    'nickname' => $member['nickname'],
    'id' => $member['id'],
    'email' => $member['email'],
    'intro' => $member['intro'] ?? '',
    'post_count' => get_member_count($conn, 'blog_posts', $memberId),
    'comment_count' => get_member_count($conn, 'blog_comments', $memberId),
    'like_count' => get_member_count($conn, 'blog_likes', $memberId),
];

$formUser = [
    'nickname' => array_key_exists('nickname', $profileOld) ? $profileOld['nickname'] : $user['nickname'],
    'email' => array_key_exists('email', $profileOld) ? $profileOld['email'] : $user['email'],
    'intro' => array_key_exists('intro', $profileOld) ? $profileOld['intro'] : $user['intro'],
];

$profileImage = $member['profile_image_saved']
    ? '../uploads/profile/' . rawurlencode($member['profile_image_saved'])
    : '';

// 임시 게시글 데이터
$posts = [
    [
        'title' => 'React 상태관리 정리',
        'content' => 'Redux와 Zustand의 차이를 중심으로 상태관리 라이브러리를 정리해보았습니다.',
        'tags' => ['React', 'JavaScript', '상태관리'],
        'date' => '2025.01.12',
        'views' => 103,
        'likes' => 12
    ],
    [
        'title' => 'Spring Security 로그인 구현',
        'content' => 'JWT 기반 로그인 기능을 구현하면서 Spring Security의 주요 개념을 정리했습니다.',
        'tags' => ['Spring', 'Spring Security', 'JWT'],
        'date' => '2025.01.10',
        'views' => 88,
        'likes' => 7
    ],
    [
        'title' => 'AWS S3 이미지 업로드 정리',
        'content' => 'AWS S3를 이용한 이미지 업로드 과정을 정리하고 예제를 작성해보았습니다.',
        'tags' => ['AWS', 'S3', '이미지업로드'],
        'date' => '2025.01.08',
        'views' => 67,
        'likes' => 4
    ],
];
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>아방로그 - 마이페이지</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- 아이콘 CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Pretendard", "Noto Sans KR", Arial, sans-serif;
            background: #f8fafc;
            color: #111827;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .page {
            max-width: 1180px;
            margin: 0 auto;
            background: #fff;
            min-height: 100vh;
        }

        /* header */
        .header {
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            border-bottom: 1px solid #e5e7eb;
        }

        .logo {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
        }

        .nav {
            display: flex;
            gap: 48px;
            font-size: 14px;
            font-weight: 700;
            color: #111827;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .search-box {
            width: 280px;
            height: 36px;
            display: flex;
            align-items: center;
            border: 1px solid #dbe2ea;
            border-radius: 6px;
            padding: 0 12px;
            background: #fff;
        }

        .search-box input {
            flex: 1;
            border: none;
            outline: none;
            font-size: 13px;
            color: #64748b;
        }

        .search-box i {
            color: #111827;
            font-size: 14px;
        }

        .user-icon {
            width: 34px;
            height: 34px;
            border: 1px solid #dbe2ea;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
        }

        /* main */
        .main {
            padding: 34px 58px 70px;
        }

        .title-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .page-title h1 {
            font-size: 28px;
            font-weight: 900;
            margin-bottom: 10px;
            color: #0f172a;
        }

        .page-title p {
            font-size: 14px;
            color: #64748b;
        }

        .top-edit-btn {
            height: 36px;
            padding: 0 16px;
            border: 1px solid #dbe2ea;
            background: #fff;
            border-radius: 7px;
            font-weight: 700;
            color: #334155;
            cursor: pointer;
        }

        .top-edit-btn i {
            margin-right: 6px;
        }

        /* profile card */
        .profile-card {
            display: grid;
            grid-template-columns: 1fr 1.4fr;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
            padding: 32px 42px;
            margin-bottom: 24px;
        }

        .profile-left {
            display: flex;
            align-items: center;
            gap: 36px;
        }

        .avatar {
            width: 128px;
            height: 128px;
            border-radius: 50%;
            background: linear-gradient(145deg, #eef1f5, #e5e8ed);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #a3acb8;
            font-size: 64px;
            overflow: hidden;
        }

        .avatar img,
        .modal-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-info h2 {
            font-size: 24px;
            font-weight: 900;
            margin-bottom: 12px;
            color: #111827;
        }

        .profile-meta {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 16px;
        }

        .profile-intro {
            font-size: 14px;
            color: #475569;
            margin-bottom: 18px;
        }

        .small-btn {
            padding: 8px 14px;
            border: 1px solid #dbe2ea;
            background: #fff;
            border-radius: 5px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            align-items: center;
        }

        .stat-box {
            text-align: center;
            border-left: 1px solid #e5e7eb;
            padding: 8px 20px;
        }

        .stat-label {
            font-size: 13px;
            font-weight: 700;
            color: #475569;
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 900;
            margin-bottom: 14px;
            color: #111827;
        }

        .stat-link {
            font-size: 13px;
            color: #0066ff;
            font-weight: 700;
        }

        /* content card */
        .content-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.07);
            overflow: hidden;
        }

        .tabs {
            display: flex;
            height: 48px;
            border-bottom: 1px solid #e5e7eb;
        }

        .tab {
            width: 170px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 800;
            color: #475569;
            position: relative;
        }

        .tab.active {
            color: #0066ff;
        }

        .tab.active::after {
            content: "";
            position: absolute;
            left: 0;
            bottom: -1px;
            width: 100%;
            height: 3px;
            background: #0066ff;
        }

        .post-list {
            padding: 0 22px;
        }

        .post-item {
            display: grid;
            grid-template-columns: 130px 1fr 260px;
            gap: 24px;
            padding: 24px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .post-item:last-child {
            border-bottom: none;
        }

        .thumb {
            height: 74px;
            border-radius: 5px;
            background: #eef0f4;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            font-size: 32px;
        }

        .post-title {
            font-size: 16px;
            font-weight: 900;
            color: #111827;
            margin-bottom: 10px;
        }

        .post-content {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 12px;
            line-height: 1.5;
        }

        .tags {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .tag {
            padding: 5px 9px;
            border-radius: 4px;
            background: #f1f5f9;
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
        }

        .post-meta {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 24px;
            font-size: 13px;
            color: #64748b;
        }

        .meta-group {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .more-btn {
            color: #64748b;
            font-size: 18px;
        }

        /* pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 22px;
            padding: 24px 0 30px;
        }

        .page-num,
        .page-arrow {
            border: none;
            background: none;
            cursor: pointer;
            font-size: 14px;
            color: #334155;
        }

        .page-num.active {
            width: 30px;
            height: 30px;
            background: #0066ff;
            color: #fff;
            border-radius: 6px;
            font-weight: 800;
        }

        /* responsive */
        @media (max-width: 900px) {
            .header {
                height: auto;
                padding: 20px;
                flex-direction: column;
                gap: 18px;
            }

            .nav {
                gap: 24px;
            }

            .header-right {
                width: 100%;
            }

            .search-box {
                flex: 1;
            }

            .main {
                padding: 28px 20px 50px;
            }

            .title-row {
                align-items: flex-start;
                gap: 16px;
                flex-direction: column;
            }

            .profile-card {
                grid-template-columns: 1fr;
                padding: 28px 22px;
                gap: 30px;
            }

            .profile-left {
                flex-direction: column;
                text-align: center;
            }

            .profile-stats {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .stat-box {
                border-left: none;
                border-top: 1px solid #e5e7eb;
                padding-top: 20px;
            }

            .post-item {
                grid-template-columns: 1fr;
            }

            .post-meta {
                justify-content: flex-start;
                flex-wrap: wrap;
            }
        }

        @media (max-width: 520px) {
            .nav {
                width: 100%;
                justify-content: space-between;
                gap: 0;
                font-size: 13px;
            }

            .search-box {
                width: 100%;
            }

            .page-title h1 {
                font-size: 24px;
            }

            .tabs {
                overflow-x: auto;
            }

            .tab {
                min-width: 130px;
            }
        }
        /* modal */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(3px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 20px;
}

.modal-overlay.active {
    display: flex;
}

.profile-modal {
    width: 380px;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 24px 80px rgba(15, 23, 42, 0.35);
    overflow: hidden;
    animation: modalFadeUp 0.25s ease;
}

@keyframes modalFadeUp {
    from {
        opacity: 0;
        transform: translateY(20px) scale(0.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal-header {
    height: 64px;
    padding: 0 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.modal-header h2 {
    font-size: 19px;
    font-weight: 900;
    color: #111827;
}

.modal-close {
    border: none;
    background: none;
    font-size: 20px;
    color: #111827;
    cursor: pointer;
}

.profile-modal form {
    padding: 0 28px 22px;
}

.modal-profile-image {
    display: flex;
    align-items: center;
    gap: 22px;
    margin-bottom: 22px;
}

.modal-avatar {
    width: 92px;
    height: 92px;
    border-radius: 50%;
    background: linear-gradient(145deg, #eef1f5, #e5e8ed);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #a3acb8;
    font-size: 42px;
    flex-shrink: 0;
    overflow: hidden;
}

.profile-message {
    margin-bottom: 20px;
    padding: 12px 14px;
    border-radius: 7px;
    font-size: 13px;
    font-weight: 700;
}

.profile-message.success {
    color: #0369a1;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
}

.profile-message.error {
    color: #be123c;
    background: #fff1f2;
    border: 1px solid #fecdd3;
}

.image-upload-box input[type="file"] {
    display: none;
}

.image-upload-btn {
    display: inline-flex;
    height: 34px;
    padding: 0 15px;
    align-items: center;
    justify-content: center;
    border: 1px solid #dbe2ea;
    border-radius: 6px;
    background: #fff;
    font-size: 13px;
    font-weight: 800;
    color: #334155;
    cursor: pointer;
    margin-bottom: 9px;
}

.image-upload-box p {
    font-size: 12px;
    color: #94a3b8;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    font-size: 14px;
    font-weight: 800;
    color: #111827;
    margin-bottom: 8px;
}

.form-group input,
.form-group textarea {
    width: 100%;
    border: 1px solid #dbe2ea;
    border-radius: 6px;
    padding: 12px 13px;
    font-size: 14px;
    color: #334155;
    outline: none;
    font-family: inherit;
}

.form-group input:focus,
.form-group textarea:focus {
    border-color: #0066ff;
    box-shadow: 0 0 0 3px rgba(0, 102, 255, 0.1);
}

.form-group textarea {
    height: 72px;
    resize: vertical;
}

.modal-footer {
    margin: 22px -28px -22px;
    padding: 18px 28px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

.cancel-btn,
.save-btn {
    height: 38px;
    padding: 0 20px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
}

.cancel-btn {
    background: #fff;
    border: 1px solid #dbe2ea;
    color: #334155;
}

.save-btn {
    background: #0066ff;
    border: 1px solid #0066ff;
    color: #fff;
}

.save-btn:hover {
    background: #0055d6;
}

@media (max-width: 520px) {
    .profile-modal {
        width: 100%;
    }

    .modal-profile-image {
        flex-direction: column;
        align-items: flex-start;
    }
}
    </style>
</head>

<body>
<div class="page">

    <!-- 상단 헤더 -->
    <header class="header">
        <a href="#" class="logo">아방로그</a>

        <nav class="nav">
            <a href="#">홈</a>
            <a href="#">글 목록</a>
            <a href="#">카테고리</a>
            <a href="#">소개</a>
        </nav>

        <div class="header-right">
            <div class="search-box">
                <input type="text" placeholder="검색어를 입력하세요">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>

            <div class="user-icon">
                <i class="fa-regular fa-user"></i>
            </div>
        </div>
    </header>

    <main class="main">

        <!-- 마이페이지 제목 -->
        <section class="title-row">
            <div class="page-title">
                <h1>마이페이지</h1>
                <p>나의 활동을 한눈에 확인하고 관리할 수 있습니다.</p>
            </div>

            <button class="top-edit-btn profile-edit-trigger" type="button">
                <i class="fa-regular fa-pen-to-square"></i>
                프로필 수정
            </button>
        </section>

        <?php if ($profileSuccess !== '') : ?>
            <div class="profile-message success" role="status">
                <?= h($profileSuccess) ?>
            </div>
        <?php endif; ?>

        <!-- 프로필 영역 -->
        <section class="profile-card">
            <div class="profile-left">
                <div class="avatar">
                    <?php if ($profileImage !== '') : ?>
                        <img src="<?= h($profileImage) ?>" alt="프로필 이미지">
                    <?php else : ?>
                        <i class="fa-solid fa-user"></i>
                    <?php endif; ?>
                </div>

                <div class="profile-info">
                    <h2><?= htmlspecialchars($user['nickname']) ?></h2>
                    <div class="profile-meta">
                        <?= htmlspecialchars($user['id']) ?>
                        &nbsp;&nbsp;|&nbsp;&nbsp;
                        <?= htmlspecialchars($user['email']) ?>
                    </div>
                    <p class="profile-intro">
                        <?= htmlspecialchars($user['intro']) ?>
                    </p>
                    <button class="small-btn profile-edit-trigger" type="button">프로필 수정</button>
                </div>
            </div>

            <div class="profile-stats">
                <div class="stat-box">
                    <div class="stat-label">작성한 글</div>
                    <div class="stat-number"><?= $user['post_count'] ?></div>
                    <a href="#" class="stat-link">전체 글 보기 〉</a>
                </div>

                <div class="stat-box">
                    <div class="stat-label">댓글</div>
                    <div class="stat-number"><?= $user['comment_count'] ?></div>
                    <a href="#" class="stat-link">전체 댓글 보기 〉</a>
                </div>

                <div class="stat-box">
                    <div class="stat-label">좋아요</div>
                    <div class="stat-number"><?= $user['like_count'] ?></div>
                    <a href="#" class="stat-link">좋아요한 글 보기 〉</a>
                </div>
            </div>
        </section>

        <!-- 게시글 목록 영역 -->
        <section class="content-card">
            <div class="tabs">
                <a href="#" class="tab active">내가 쓴 글</a>
                <a href="#" class="tab">좋아요한 글</a>
                <a href="#" class="tab">댓글 단 글</a>
            </div>

            <div class="post-list">
                <?php foreach ($posts as $post) : ?>
                    <article class="post-item">
                        <div class="thumb">
                            <i class="fa-regular fa-image"></i>
                        </div>

                        <div class="post-body">
                            <h3 class="post-title">
                                <?= htmlspecialchars($post['title']) ?>
                            </h3>

                            <p class="post-content">
                                <?= htmlspecialchars($post['content']) ?>
                            </p>

                            <div class="tags">
                                <?php foreach ($post['tags'] as $tag) : ?>
                                    <span class="tag">#<?= htmlspecialchars($tag) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="post-meta">
                            <span><?= htmlspecialchars($post['date']) ?></span>

                            <span class="meta-group">
                                <i class="fa-regular fa-eye"></i>
                                <?= $post['views'] ?>
                            </span>

                            <span class="meta-group">
                                <i class="fa-regular fa-heart"></i>
                                <?= $post['likes'] ?>
                            </span>

                            <a href="#" class="more-btn">
                                <i class="fa-solid fa-ellipsis-vertical"></i>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <!-- 페이지네이션 -->
            <div class="pagination">
                <button class="page-arrow">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>

                <button class="page-num active">1</button>
                <button class="page-num">2</button>

                <button class="page-arrow">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>
        </section>
    </main>

<!-- 프로필 수정 팝업 -->
<div class="modal-overlay" id="modalOverlay">
    <div class="profile-modal">
        <div class="modal-header">
            <h2>프로필 수정</h2>
            <button type="button" class="modal-close" id="closeProfileModal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form action="mypage_proc.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['profile_csrf_token']) ?>">
            <input type="hidden" name="return_to" value="mypage">

            <?php if ($profileError !== '') : ?>
                <div class="profile-message error" role="alert">
                    <?= h($profileError) ?>
                </div>
            <?php endif; ?>

            <div class="modal-profile-image">
                <div class="modal-avatar">
                    <?php if ($profileImage !== '') : ?>
                        <img src="<?= h($profileImage) ?>" alt="현재 프로필 이미지">
                    <?php else : ?>
                        <i class="fa-solid fa-user"></i>
                    <?php endif; ?>
                </div>

                <div class="image-upload-box">
                    <label for="profileImage" class="image-upload-btn">이미지 변경</label>
                    <input type="file" id="profileImage" name="profile_image" accept="image/jpeg,image/png">
                    <p>JPG, PNG / 최대 5MB</p>
                </div>
            </div>

            <div class="form-group">
                <label for="nickname">닉네임</label>
                <input 
                    type="text" 
                    id="nickname" 
                    name="nickname" 
                    value="<?= h($formUser['nickname']) ?>"
                    maxlength="50"
                    required
                >
            </div>

            <div class="form-group">
                <label for="email">이메일</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    value="<?= h($formUser['email']) ?>"
                    maxlength="100"
                    required
                >
            </div>

            <div class="form-group">
                <label for="intro">소개</label>
                <textarea id="intro" name="intro" maxlength="500"><?= h($formUser['intro']) ?></textarea>
            </div>

            <div class="modal-footer">
                <button type="button" class="cancel-btn" id="cancelProfileModal">취소</button>
                <button type="submit" class="save-btn">저장하기</button>
            </div>
        </form>
    </div>
</div>
<script>
    const profileEditTriggers = document.querySelectorAll('.profile-edit-trigger');
    const closeProfileModal = document.getElementById('closeProfileModal');
    const cancelProfileModal = document.getElementById('cancelProfileModal');
    const modalOverlay = document.getElementById('modalOverlay');
    const profileImageInput = document.getElementById('profileImage');
    const modalAvatar = document.querySelector('.modal-avatar');

    function openModal() {
        modalOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    profileEditTriggers.forEach(function (button) {
        button.addEventListener('click', openModal);
    });

    closeProfileModal.addEventListener('click', function () {
        closeModal();
    });

    cancelProfileModal.addEventListener('click', function () {
        closeModal();
    });

    modalOverlay.addEventListener('click', function (event) {
        if (event.target === modalOverlay) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modalOverlay.classList.contains('active')) {
            closeModal();
        }
    });

    profileImageInput.addEventListener('change', function () {
        const file = profileImageInput.files[0];
        if (!file) {
            return;
        }

        const reader = new FileReader();
        reader.addEventListener('load', function () {
            modalAvatar.innerHTML = '';
            const image = document.createElement('img');
            image.src = reader.result;
            image.alt = '선택한 프로필 이미지 미리보기';
            modalAvatar.appendChild(image);
        });
        reader.readAsDataURL(file);
    });

    function closeModal() {
        modalOverlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    <?php if ($profileError !== '') : ?>
        openModal();
    <?php endif; ?>
</script>
</body>
</html>
