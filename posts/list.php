<?php
require_once __DIR__ . '/../inc/db.php';

$categories = fetch_categories($conn);
$categoryCounts = fetch_category_counts($conn);
$totalPosts = fetch_total_active_public_posts($conn);
$recentPosts = fetch_recent_posts($conn, 5);
$success = $_SESSION['post_success'] ?? '';
unset($_SESSION['post_success']);

$q = trim($_GET['q'] ?? '');
$categoryId = (int)($_GET['category'] ?? 0);
$sort = $_GET['sort'] ?? 'latest';

$where = ["p.status = 'active'", "p.is_public = 'public'"];
$params = [];
$types = '';

if ($q !== '') {
    $where[] = "(p.title LIKE ? OR p.content LIKE ? OR m.nickname LIKE ? OR c.name LIKE ? OR EXISTS (
        SELECT 1
        FROM blog_post_tags spt
        JOIN blog_tags st ON st.id = spt.tag_id
        WHERE spt.post_id = p.id AND st.name LIKE ?
    ))";
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
    $types .= 'sssss';
}

if ($categoryId > 0) {
    $where[] = 'p.category_id = ?';
    $params[] = $categoryId;
    $types .= 'i';
}

$orderBy = 'p.created_at DESC, p.id DESC';
if ($sort === 'popular') {
    $orderBy = 'p.view_count DESC, p.like_count DESC, p.created_at DESC, p.id DESC';
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
        c.created_at AS category_created_at,
        (
            SELECT GROUP_CONCAT(t.name ORDER BY pt.id ASC SEPARATOR ',')
            FROM blog_post_tags pt
            JOIN blog_tags t ON t.id = pt.tag_id
            WHERE pt.post_id = p.id
        ) AS tag_names,
        (
            SELECT GROUP_CONCAT(t.id ORDER BY pt.id ASC SEPARATOR ',')
            FROM blog_post_tags pt
            JOIN blog_tags t ON t.id = pt.tag_id
            WHERE pt.post_id = p.id
        ) AS tag_ids,
        (
            SELECT GROUP_CONCAT(pt.id ORDER BY pt.id ASC SEPARATOR ',')
            FROM blog_post_tags pt
            WHERE pt.post_id = p.id
        ) AS post_tag_ids
    FROM blog_posts p
    JOIN blog_members m ON m.id = p.member_id
    JOIN blog_categories c ON c.id = p.category_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY {$orderBy}
    LIMIT 30
";

$stmt = mysqli_prepare($conn, $sql);
if ($params) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$posts = [];
while ($result && $row = mysqli_fetch_assoc($result)) {
    $posts[] = $row;
}

$countText = $q !== '' ? '검색 결과 ' . count($posts) . '건' : '전체 ' . count($posts) . '개의 글';
if ($categoryId > 0) {
    foreach ($categories as $cat) {
        if ((int)$cat['id'] === $categoryId) {
            $countText = h($cat['name']) . ' ' . count($posts) . '개의 글';
            break;
        }
    }
}

$profileSrc = current_member_profile_src('../');
$nickname = $_SESSION['member_nickname'] ?? '사용자';
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>글 목록 - 아방로그</title>
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
            <input type="search" name="q" value="<?= h($q) ?>" placeholder="검색어를 입력하세요">
            <button type="submit" aria-label="검색">⌕</button>
        </form>
        <?php if (!empty($_SESSION['is_login'])): ?>
            <a class="profile-link" href="../index.php" title="<?= h($nickname) ?>님">
                <?php if ($profileSrc): ?>
                    <img src="<?= h($profileSrc) ?>" alt="프로필 이미지">
                <?php else: ?>
                    <span>♡</span>
                <?php endif; ?>
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
                <input type="search" name="q" value="<?= h($q) ?>" placeholder="글 제목, 내용 검색">
                <button type="submit" aria-label="검색">⌕</button>
            </form>

            <section class="side-block" style="border-top:0; padding-top:0; margin-top:0;">
                <h3>카테고리</h3>
                <a class="cat-link <?= $categoryId === 0 ? 'active' : '' ?>" href="list.php"><span>전체 글</span><span><?= h($totalPosts) ?></span></a>
                <?php foreach ($categoryCounts as $category): ?>
                    <a class="cat-link <?= $categoryId === (int)$category['id'] ? 'active' : '' ?>" href="list.php?category=<?= h($category['id']) ?>">
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
                <?php else: ?>
                    <p class="help-text">아직 등록된 글이 없습니다.</p>
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
        <?php if ($success): ?>
            <div class="success"><?= h($success) ?></div>
        <?php endif; ?>

        <div class="list-head">
            <div>
                <h1 class="page-title" style="margin-bottom:0;"><?= $q !== '' ? '검색 결과' : '글 목록' ?></h1>
                <p><?= $q !== '' ? '“' . h($q) . '”에 대한 ' . h($countText) : h($countText) ?></p>
            </div>
            <form action="list.php" method="get">
                <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= h($q) ?>"><?php endif; ?>
                <?php if ($categoryId > 0): ?><input type="hidden" name="category" value="<?= h($categoryId) ?>"><?php endif; ?>
                <select class="sort-select" name="sort" onchange="this.form.submit()">
                    <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>최신순</option>
                    <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>인기순</option>
                </select>
            </form>
        </div>

        <?php if ($posts): ?>
            <section class="post-list">
                <?php foreach ($posts as $post): ?>
                    <?php
                        $thumb = $post['thumbnail_saved'] ? '../uploads/posts/' . rawurlencode($post['thumbnail_saved']) : '';
                        $tags = $post['tag_names'] ? explode(',', $post['tag_names']) : [];
                    ?>
                    <article class="post-item">
                        <a class="post-thumb" href="detail.php?id=<?= h($post['id']) ?>" aria-label="<?= h($post['title']) ?> 상세보기">
                            <?php if ($thumb): ?>
                                <img src="<?= h($thumb) ?>" alt="<?= h($post['thumbnail_original'] ?: $post['title']) ?>">
                            <?php else: ?>
                                <svg width="52" height="52" viewBox="0 0 24 24" fill="none">
                                    <path d="M4 17.5L9.1 12.4L12.4 15.7L15 13.1L20 18.1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M15.5 8.5H15.51" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                                    <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.5"/>
                                </svg>
                            <?php endif; ?>
                        </a>
                        <div class="post-content">
                            <div class="post-main">
                                <span class="badge"><?= h($post['category_name']) ?></span>
                                <h2><a href="detail.php?id=<?= h($post['id']) ?>"><?= h($post['title']) ?></a></h2>
                                <p class="post-excerpt"><?= h(make_excerpt($post['content'])) ?></p>
                                <div class="post-meta">
                                    <span>▣ <?= h(format_short_date($post['created_at'])) ?></span>
                                    <span>♙ <?= h($post['writer_nickname']) ?></span>
                                </div>
                                <?php if ($tags): ?>
                                    <div class="tag-row">
                                        <?php foreach ($tags as $tag): ?>
                                            <span class="tag-chip">#<?= h($tag) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="post-stats">
                                <span>👁 <?= h($post['view_count']) ?></span>
                                <span>♡ <?= h($post['like_count']) ?></span>
                                <span>💬 <?= h($post['comment_count']) ?></span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php else: ?>
            <section class="empty-state">
                <strong>아직 보여줄 글이 없습니다.</strong>
                <p>글을 발행하면 이 목록에 제목, 내용 요약, 작성자, 카테고리, 조회수, 좋아요, 댓글 수가 바로 표시됩니다.</p>
                <?php if (!empty($_SESSION['is_login'])): ?>
                    <p><a class="outline-btn" href="create.php">첫 글 작성하기</a></p>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
