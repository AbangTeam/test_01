<?php
require_once __DIR__ . '/../inc/post_helpers.php';

$postId = (int)($_GET['id'] ?? 0);
if ($postId <= 0) {
    header('Location: list.php');
    exit;
}

$memberId = !empty($_SESSION['member_id']) ? (int)$_SESSION['member_id'] : null;
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

mysqli_begin_transaction($conn);
try {
    if ($memberId) {
        $viewSql = "INSERT INTO blog_views (post_id, member_id, ip_address, viewed_at) VALUES (?, ?, ?, NOW())";
        $viewStmt = mysqli_prepare($conn, $viewSql);
        mysqli_stmt_bind_param($viewStmt, 'iis', $postId, $memberId, $ipAddress);
    } else {
        $viewSql = "INSERT INTO blog_views (post_id, member_id, ip_address, viewed_at) VALUES (?, NULL, ?, NOW())";
        $viewStmt = mysqli_prepare($conn, $viewSql);
        mysqli_stmt_bind_param($viewStmt, 'is', $postId, $ipAddress);
    }
    mysqli_stmt_execute($viewStmt);

    $updateSql = "UPDATE blog_posts SET view_count = view_count + 1, updated_at = updated_at WHERE id = ? AND status = 'active'";
    $updateStmt = mysqli_prepare($conn, $updateSql);
    mysqli_stmt_bind_param($updateStmt, 'i', $postId);
    mysqli_stmt_execute($updateStmt);

    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
}

$sql = "
    SELECT
        p.id,
        p.member_id,
        p.category_id,
        p.title,
        p.content,
        p.thumbnail_original,
        p.thumbnail_saved,
        p.view_count,
        p.like_count,
        p.comment_count,
        p.is_public,
        p.status,
        p.created_at,
        p.updated_at,
        m.id AS writer_id,
        m.email AS writer_email,
        m.name AS writer_name,
        m.nickname AS writer_nickname,
        m.field AS writer_field,
        m.profile_image_original AS writer_profile_original,
        m.profile_image_saved AS writer_profile_saved,
        m.intro AS writer_intro,
        m.role AS writer_role,
        m.status AS writer_status,
        m.created_at AS writer_created_at,
        m.updated_at AS writer_updated_at,
        c.id AS category_table_id,
        c.name AS category_name,
        c.created_at AS category_created_at
    FROM blog_posts p
    JOIN blog_members m ON m.id = p.member_id
    JOIN blog_categories c ON c.id = p.category_id
    WHERE p.id = ?
      AND p.status = 'active'
      AND p.is_public = 'public'
    LIMIT 1
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $postId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$post = $result ? mysqli_fetch_assoc($result) : null;

if (!$post) {
    header('Location: list.php');
    exit;
}

$tags = fetch_post_tags($conn, $postId);
$categories = fetch_categories($conn);
$categoryCounts = fetch_category_counts($conn);
$totalPosts = fetch_total_active_public_posts($conn);
$recentPosts = fetch_recent_posts($conn, 5);
$profileSrc = current_member_profile_src('../');
$nickname = $_SESSION['member_nickname'] ?? '사용자';
$thumb = $post['thumbnail_saved'] ? '../uploads/posts/' . rawurlencode($post['thumbnail_saved']) : '';
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($post['title']) ?> - 아방로그</title>
    <link rel="stylesheet" href="../assets/css/post.css">
</head>
<body>
<header class="app-header">
    <a class="logo" href="../index.php">아방로그</a>
    <nav class="app-nav" aria-label="주요 메뉴">
        <a href="../index.php">홈</a>
        <a class="active" href="list.php">글 목록</a>
        <a href="list.php">카테고리</a>
        <a href="#">소개</a>
    </nav>
    <div class="header-right">
        <form class="header-search" action="list.php" method="get">
            <input type="search" name="q" placeholder="검색어를 입력하세요">
            <button type="submit" aria-label="검색">⌕</button>
        </form>
        <?php if (!empty($_SESSION['is_login'])): ?>
            <a class="profile-link" href="../index.php" title="<?= h($nickname) ?>님">
                <?php if ($profileSrc): ?><img src="<?= h($profileSrc) ?>" alt="프로필 이미지"><?php else: ?><span>♡</span><?php endif; ?>
            </a>
        <?php else: ?>
            <a class="profile-blank" href="../auth/login.php">♡</a>
        <?php endif; ?>
    </div>
</header>

<div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar-card">
            <h2 class="side-title">검색</h2>
            <form class="header-search" action="list.php" method="get" style="width:100%; margin-bottom:18px;">
                <input type="search" name="q" placeholder="글 제목, 내용 검색">
                <button type="submit" aria-label="검색">⌕</button>
            </form>

            <section class="side-block" style="border-top:0; padding-top:0; margin-top:0;">
                <h3>카테고리</h3>
                <a class="cat-link" href="list.php"><span>전체 글</span><span><?= h($totalPosts) ?></span></a>
                <?php foreach ($categoryCounts as $category): ?>
                    <a class="cat-link <?= (int)$post['category_id'] === (int)$category['id'] ? 'active' : '' ?>" href="list.php?category=<?= h($category['id']) ?>">
                        <span><?= h($category['name']) ?></span>
                        <span><?= h($category['post_count']) ?></span>
                    </a>
                <?php endforeach; ?>
            </section>

            <section class="side-block">
                <h3>최근 글</h3>
                <?php if ($recentPosts): ?>
                    <ul class="recent-list">
                        <?php foreach ($recentPosts as $recent): ?>
                            <li>
                                <a href="detail.php?id=<?= h($recent['id']) ?>"><?= h($recent['title']) ?></a>
                                <span class="recent-date"><?= h(format_short_date($recent['created_at'])) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <div class="side-bottom">
                <?php if (!empty($_SESSION['is_login'])): ?>
                    <a class="back-list-btn" href="create.php">✎ 글쓰기</a>
                <?php else: ?>
                    <a class="back-list-btn" href="../auth/login.php">로그인 후 글쓰기</a>
                <?php endif; ?>
            </div>
        </div>
    </aside>

    <main class="main-area">
        <a class="outline-btn" href="list.php" style="margin-bottom:18px;">← 글 목록으로 돌아가기</a>

        <article class="detail-card">
            <div class="detail-category"><span class="badge"><?= h($post['category_name']) ?></span></div>
            <h1><?= h($post['title']) ?></h1>
            <div class="post-meta">
                <span>▣ <?= h(format_short_date($post['created_at'])) ?></span>
                <span>♙ <?= h($post['writer_nickname']) ?></span>
                <span>👁 <?= h($post['view_count']) ?></span>
                <span>♡ <?= h($post['like_count']) ?></span>
                <span>💬 <?= h($post['comment_count']) ?></span>
            </div>

            <div class="detail-image">
                <?php if ($thumb): ?>
                    <img src="<?= h($thumb) ?>" alt="<?= h($post['thumbnail_original'] ?: $post['title']) ?>">
                <?php else: ?>
                    <svg width="70" height="70" viewBox="0 0 24 24" fill="none">
                        <path d="M4 17.5L9.1 12.4L12.4 15.7L15 13.1L20 18.1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M15.5 8.5H15.51" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                        <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.5"/>
                    </svg>
                <?php endif; ?>
            </div>

            <div class="detail-body"><?= h($post['content']) ?></div>

            <?php if ($tags): ?>
                <div class="tag-row" style="margin-top:26px;">
                    <?php foreach ($tags as $tag): ?>
                        <span class="tag-chip">#<?= h($tag['name']) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
    </main>
</div>
</body>
</html>
