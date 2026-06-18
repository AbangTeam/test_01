<?php
require_once __DIR__ . '/../inc/db.php';

if (!isset($categoryId, $categoryName, $categoryKey)) {
    die('카테고리 설정이 필요합니다.');
}

$categoryId = (int)$categoryId;
$categoryPages = [
    ['id' => 0, 'name' => '전체', 'key' => 'all', 'file' => 'all.php'],
    ['id' => 1, 'name' => '일상', 'key' => 'daily', 'file' => 'daily.php'],
    ['id' => 2, 'name' => '여행', 'key' => 'travel', 'file' => 'travel.php'],
    ['id' => 3, 'name' => '카페', 'key' => 'cafe', 'file' => 'cafe.php'],
];
$allowedCategoryIds = array_column($categoryPages, 'id');

if (!in_array($categoryId, $allowedCategoryIds, true)) {
    die('지원하지 않는 카테고리입니다.');
}

$categoryCounts = array_fill_keys($allowedCategoryIds, 0);
$countSql = "
    SELECT category_id, COUNT(*) AS count
    FROM blog_posts
    WHERE category_id IN (1, 2, 3)
      AND status = 'active'
      AND is_public = 'public'
    GROUP BY category_id
";
$countResult = mysqli_query($conn, $countSql);
if (!$countResult) {
    die('카테고리 글 수 조회 실패: ' . mysqli_error($conn));
}

while ($countRow = mysqli_fetch_assoc($countResult)) {
    $postCount = (int)$countRow['count'];
    $categoryCounts[(int)$countRow['category_id']] = $postCount;
    $categoryCounts[0] += $postCount;
}

if ($categoryId === 0) {
    $recentResult = mysqli_query($conn, "
        SELECT id, title, created_at
        FROM blog_posts
        WHERE status = 'active'
          AND is_public = 'public'
        ORDER BY created_at DESC, id DESC
        LIMIT 5
    ");
    if (!$recentResult) {
        die('최근 글 조회 실패: ' . mysqli_error($conn));
    }

    $postResult = mysqli_query($conn, "
        SELECT
            p.id,
            p.title,
            p.content,
            p.created_at,
            p.thumbnail_saved,
            m.nickname,
            c.name AS category_name
        FROM blog_posts p
        LEFT JOIN blog_members m ON p.member_id = m.id
        LEFT JOIN blog_categories c ON p.category_id = c.id
        WHERE p.status = 'active'
          AND p.is_public = 'public'
        ORDER BY p.created_at DESC, p.id DESC
    ");
    if (!$postResult) {
        die('전체 글 조회 실패: ' . mysqli_error($conn));
    }
} else {
    $recentSql = "
        SELECT id, title, created_at
        FROM blog_posts
        WHERE category_id = ?
          AND status = 'active'
          AND is_public = 'public'
        ORDER BY created_at DESC, id DESC
        LIMIT 5
    ";
    $recentStmt = mysqli_prepare($conn, $recentSql);
    if (!$recentStmt) {
        die('최근 글 조회 준비 실패: ' . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($recentStmt, 'i', $categoryId);
    mysqli_stmt_execute($recentStmt);
    $recentResult = mysqli_stmt_get_result($recentStmt);

    $postSql = "
        SELECT
            p.id,
            p.title,
            p.content,
            p.created_at,
            p.thumbnail_saved,
            m.nickname,
            c.name AS category_name
        FROM blog_posts p
        LEFT JOIN blog_members m ON p.member_id = m.id
        LEFT JOIN blog_categories c ON p.category_id = c.id
        WHERE p.category_id = ?
          AND p.status = 'active'
          AND p.is_public = 'public'
        ORDER BY p.created_at DESC, p.id DESC
    ";
    $postStmt = mysqli_prepare($conn, $postSql);
    if (!$postStmt) {
        die('카테고리 글 조회 준비 실패: ' . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($postStmt, 'i', $categoryId);
    mysqli_stmt_execute($postStmt);
    $postResult = mysqli_stmt_get_result($postStmt);
}

function category_page_short_text($text, $length = 80) {
    $plainText = trim(strip_tags((string)$text));

    if (mb_strlen($plainText, 'UTF-8') <= $length) {
        return $plainText;
    }

    return mb_substr($plainText, 0, $length, 'UTF-8') . '...';
}

function category_page_thumbnail_path($savedName) {
    if (empty($savedName)) {
        return '';
    }

    $fileName = basename((string)$savedName);
    $relativePath = '../uploads/posts/' . $fileName;
    $absolutePath = __DIR__ . '/../uploads/posts/' . $fileName;

    return is_file($absolutePath) ? $relativePath : '';
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>얀블로그 - <?= h($categoryName) ?></title>
  <link rel="stylesheet" href="./css/cafe.css">
</head>
<body>

  <header class="header">
    <a class="logo" href="../index.php">얀블로그</a>

    <nav class="nav">
      <a href="../index.php">홈</a>
      <?php foreach ($categoryPages as $page) { ?>
        <a href="<?= h($page['file']) ?>" class="<?= $page['key'] === $categoryKey ? 'active' : '' ?>">
          <?= h($page['name']) ?>
        </a>
      <?php } ?>
    </nav>

    <form class="top-search" action="../index.php" method="get">
      <input type="text" name="keyword" placeholder="검색어를 입력하세요">
      <button type="submit">검색</button>
    </form>

    <a class="profile-icon" href="../mypage/mypage.php">MY</a>
  </header>

  <div class="layout">
    <aside class="sidebar">
      <div class="side-box">
        <h3>검색</h3>

        <form class="side-search" action="../index.php" method="get">
          <input type="text" name="keyword" placeholder="글 제목, 내용 검색">
          <button type="submit">검색</button>
        </form>
      </div>

      <div class="side-box">
        <h3>카테고리</h3>

        <ul class="category-list">
          <?php foreach ($categoryPages as $page) { ?>
            <li>
              <a href="<?= h($page['file']) ?>" class="<?= $page['id'] === $categoryId ? 'selected' : '' ?>">
                <span><?= h($page['name']) ?></span>
                <em><?= h($categoryCounts[$page['id']] ?? 0) ?></em>
              </a>
            </li>
          <?php } ?>
        </ul>
      </div>

      <div class="side-box">
        <h3>최근 <?= h($categoryName) ?> 글</h3>

        <ul class="recent-list">
          <?php while ($recent = mysqli_fetch_assoc($recentResult)) { ?>
            <li>
              <a href="../view.php?id=<?= h($recent['id']) ?>">
                <?= h(mb_substr($recent['title'], 0, 14, 'UTF-8')) ?>
              </a>
              <span><?= h(date('Y.m.d', strtotime($recent['created_at']))) ?></span>
            </li>
          <?php } ?>
        </ul>
      </div>
    </aside>

    <main class="main">
      <section class="page-title">
        <h1><?= h($categoryName) ?></h1>
      </section>

      <section class="category-tabs">
        <?php foreach ($categoryPages as $page) { ?>
          <a href="<?= h($page['file']) ?>" class="<?= $page['id'] === $categoryId ? 'on' : '' ?>">
            <span><?= h($page['name']) ?></span>
            <em><?= h($categoryCounts[$page['id']] ?? 0) ?></em>
          </a>
        <?php } ?>
      </section>

      <section class="list-header">
        <h2>
          <?= h($categoryName) ?>
          <span><?= h($categoryCounts[$categoryId] ?? 0) ?>개의 글</span>
        </h2>
      </section>

      <section class="post-list">
        <?php if (mysqli_num_rows($postResult) > 0) { ?>
          <?php while ($row = mysqli_fetch_assoc($postResult)) { ?>
            <?php $thumbnailPath = category_page_thumbnail_path($row['thumbnail_saved'] ?? ''); ?>
            <article class="post-card">
              <a href="../view.php?id=<?= h($row['id']) ?>" class="thumb">
                <?php if ($thumbnailPath !== '') { ?>
                  <img src="<?= h($thumbnailPath) ?>" alt="">
                <?php } else { ?>
                  <span><?= h($categoryName) ?></span>
                <?php } ?>
              </a>

              <div class="post-info">
                <a href="../view.php?id=<?= h($row['id']) ?>" class="post-title">
                  <?= h($row['title']) ?>
                </a>

                <p class="post-desc">
                  <?= h(category_page_short_text($row['content'])) ?>
                </p>

                <div class="post-meta">
                  <span><?= h(date('Y.m.d', strtotime($row['created_at']))) ?></span>
                  <span><?= h($row['nickname'] ?? '얀근') ?></span>
                </div>
              </div>
            </article>
          <?php } ?>
        <?php } else { ?>
          <div class="empty">
            <p>아직 등록된 <?= h($categoryName) ?> 글이 없습니다.</p>
          </div>
        <?php } ?>
      </section>

      <div class="pagination">
        <button>&lt;</button>
        <button class="active">1</button>
        <button>&gt;</button>
      </div>
    </main>
  </div>

</body>
</html>
