<?php
require_once __DIR__ . '/../inc/post_helpers.php';
require_login();

$categories = fetch_categories($conn);
$categoryCounts = fetch_category_counts($conn);
$totalPosts = fetch_total_active_public_posts($conn);
$recentPosts = fetch_recent_posts($conn, 5);

$error = $_SESSION['post_error'] ?? '';
$old = $_SESSION['post_old'] ?? [];
unset($_SESSION['post_error'], $_SESSION['post_old']);

$profileSrc = current_member_profile_src('../');
$nickname = $_SESSION['member_nickname'] ?? '사용자';
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>글 작성 - 아방로그</title>
    <link rel="stylesheet" href="./css/post.css">
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
        <a class="profile-link" href="../index.php" title="<?= h($nickname) ?>님">
            <?php if ($profileSrc): ?>
                <img src="<?= h($profileSrc) ?>" alt="프로필 이미지">
            <?php else: ?>
                <span>♡</span>
            <?php endif; ?>
        </a>
    </div>
</header>

<div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar-card">
            <h2 class="side-title">쓰기</h2>
            <nav class="side-menu">
                <a class="active" href="create.php">✎ 글 작성</a>
            </nav>

            <section class="side-block">
                <h3>카테고리</h3>
                <a class="cat-link" href="list.php"><span>전체 글</span><span><?= h($totalPosts) ?></span></a>
                <?php foreach ($categoryCounts as $category): ?>
                    <a class="cat-link" href="list.php?category=<?= h($category['id']) ?>">
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
                <a class="back-list-btn" href="list.php"><span class="btn-icon" aria-hidden="true">←</span><span>글 목록으로</span></a>
            </div>
        </div>
    </aside>

    <main class="main-area">
        <h1 class="page-title">글 작성</h1>

        <?php if ($error): ?>
            <div class="alert"><?= h($error) ?></div>
        <?php endif; ?>

        <form class="form-card" action="create_process.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="is_public" value="public">
            <input type="hidden" name="status" value="active">

            <div class="form-group">
                <label class="field-label" for="title">제목 <span class="required-dot">*</span></label>
                <input class="form-input" id="title" name="title" type="text" maxlength="100" placeholder="제목을 입력하세요" value="<?= h($old['title'] ?? '') ?>" required>
                <div class="text-count"><span id="titleCount">0</span> / 100</div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="field-label" for="category_id">카테고리 <span class="required-dot">*</span></label>
                    <select class="form-select" id="category_id" name="category_id" required>
                        <option value="">카테고리를 선택하세요</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= h($category['id']) ?>" <?= ((string)($old['category_id'] ?? '') === (string)$category['id']) ? 'selected' : '' ?>>
                                <?= h($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="field-label" for="tagInput">태그</label>
                    <div class="tag-input-wrap" id="tagEditor">
                        <div class="tag-chip-list" id="tagChipList" aria-live="polite"></div>
                        <input class="tag-field" id="tagInput" type="text" placeholder="태그 입력 후 쉼표(,)를 누르세요">
                    </div>
                    <input id="tags" name="tags" type="hidden" value="<?= h($old['tags'] ?? '') ?>">
                    <div class="help-text">예) 여행, 제주도, 맛집 · 쉼표를 누르면 태그 박스로 변환됩니다.</div>
                </div>
            </div>

            <div class="form-group">
                <label class="field-label">대표 이미지</label>
                <div class="image-box">
                    <div class="image-preview" id="thumbnailPreview" aria-hidden="true">
                        <svg width="52" height="52" viewBox="0 0 24 24" fill="none">
                            <path d="M4 17.5L9.1 12.4L12.4 15.7L15 13.1L20 18.1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M15.5 8.5H15.51" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                            <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.5"/>
                        </svg>
                    </div>
                    <div class="image-info">
                        <strong>이미지를 추가해 보세요</strong>
                        <p>대표 이미지는 글 목록에서 썸네일로 사용됩니다.</p>
                        <label class="file-btn" for="thumbnail">이미지 업로드</label>
                        <input class="hidden-file" id="thumbnail" name="thumbnail" type="file" accept="image/jpeg,image/png,image/webp">
                    </div>
                    <div class="image-size">권장 크기: 1200x630px<br>최대 5MB</div>
                </div>
            </div>

            <div class="form-group">
                <label class="field-label" for="content">내용 <span class="required-dot">*</span></label>
                <div class="editor-box">
                    <div class="editor-toolbar" aria-hidden="true">
                        <span class="select-label">본문⌄</span>
                        <span>B</span><span><em>I</em></span><span><u>U</u></span><span><s>S</s></span>
                        <span>≡</span><span>☷</span><span>❞</span><span>🔗</span><span>▧</span><span class="unalign-btn">—</span>
                        <span class="spacer"></span>
                        <span>↶</span><span>↷</span>
                    </div>
                    <textarea class="form-textarea" id="content" name="content" placeholder="내용을 입력하세요" required><?= h($old['content'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <a class="cancel-btn" href="list.php">취소</a>
                <button class="submit-btn" type="submit">등록하기</button>
            </div>
        </form>
    </main>
</div>

<script>
const titleInput = document.getElementById('title');
const titleCount = document.getElementById('titleCount');
function updateTitleCount() {
    titleCount.textContent = titleInput.value.length;
}
titleInput?.addEventListener('input', updateTitleCount);
updateTitleCount();

const thumbnail = document.getElementById('thumbnail');
const thumbnailPreview = document.getElementById('thumbnailPreview');
thumbnail?.addEventListener('change', () => {
    const file = thumbnail.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.addEventListener('load', () => {
        thumbnailPreview.innerHTML = `<img src="${reader.result}" alt="대표 이미지 미리보기">`;
    });
    reader.readAsDataURL(file);
});

const tagEditor = document.getElementById('tagEditor');
const tagInput = document.getElementById('tagInput');
const tagHidden = document.getElementById('tags');
const tagChipList = document.getElementById('tagChipList');
let tags = [];

function normalizeTag(value) {
    return value.replace(/[#，;]/g, '').trim().slice(0, 30);
}

function syncTags() {
    tagHidden.value = tags.join(', ');
    tagChipList.innerHTML = '';

    tags.forEach((tag, index) => {
        const chip = document.createElement('span');
        chip.className = 'tag-chip-editor';

        const label = document.createElement('span');
        label.textContent = `#${tag}`;

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.setAttribute('aria-label', `${tag} 태그 삭제`);
        removeButton.textContent = '×';
        removeButton.addEventListener('click', () => {
            tags.splice(index, 1);
            syncTags();
            tagInput.focus();
        });

        chip.append(label, removeButton);
        tagChipList.appendChild(chip);
    });
}

function addTag(value) {
    const tag = normalizeTag(value);
    if (!tag || tags.includes(tag) || tags.length >= 8) {
        tagInput.value = '';
        return;
    }
    tags.push(tag);
    tagInput.value = '';
    syncTags();
}

function hydrateTags() {
    const saved = tagHidden.value
        .split(/[,%，;；\n\r]+/u)
        .map(normalizeTag)
        .filter(Boolean);
    tags = [...new Set(saved)].slice(0, 8);
    syncTags();
}

tagEditor?.addEventListener('click', () => tagInput.focus());

tagInput?.addEventListener('keydown', event => {
    if (event.key === ',' || event.key === 'Enter') {
        event.preventDefault();
        addTag(tagInput.value);
    }

    if (event.key === 'Backspace' && tagInput.value === '' && tags.length > 0) {
        event.preventDefault();
        tagInput.value = tags.pop();
        syncTags();
    }
});

tagInput?.addEventListener('blur', () => {
    if (tagInput.value.trim() !== '') {
        addTag(tagInput.value);
    }
});

document.querySelector('.form-card')?.addEventListener('submit', () => {
    if (tagInput.value.trim() !== '') {
        addTag(tagInput.value);
    }
});

hydrateTags();
</script>
</body>
</html>
