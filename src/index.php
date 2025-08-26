<?php
$db = new PDO('sqlite:notes.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec("CREATE TABLE IF NOT EXISTS notes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    path TEXT UNIQUE NOT NULL,
    content TEXT
)");

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = rtrim($path, '/') ?: '/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = trim($_POST['content'] ?? '');
    if ($content === '') {
        $stmt = $db->prepare("DELETE FROM notes WHERE path = ?");
        $stmt->execute([$path]);
    } else {
        $stmt = $db->prepare("INSERT INTO notes (path, content)
            VALUES (?, ?)
            ON CONFLICT(path) DO UPDATE SET content=excluded.content");
        $stmt->execute([$path, $content]);
    }
    echo "saved";
    exit;
}

$stmt = $db->prepare("SELECT content FROM notes WHERE path = ?");
$stmt->execute([$path]);
$note = $stmt->fetchColumn() ?: '';

$like = $path === '/' ? '/%' : "$path/%";
$not_like = "$like/%";
$stmt = $db->prepare("SELECT path FROM notes WHERE path LIKE ? AND path NOT LIKE ?");
$stmt->execute([$like, $not_like]);
$children = $stmt->fetchAll(PDO::FETCH_COLUMN);

function get_parent_path($path) {
    if ($path === '/' || $path === '') return null;
    $parts = explode('/', trim($path, '/'));
    array_pop($parts);
    return '/' . implode('/', $parts);
}
$parentPath = get_parent_path($path);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($path) ?></title>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            font-family: sans-serif;
        }
        .container {
            display: flex;
            height: 100vh;
            width: 100vw;
        }
        .sidebar {
            width: 250px;
            background: #f0f0f0;
            padding: 1rem;
            box-sizing: border-box;
            overflow-y: auto;
        }
        .sidebar a {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            text-decoration: none;
        }
        .editor {
            flex-grow: 1;
            height: 100%;
            overflow: hidden;
        }
        textarea {
            width: 100%;
            height: 100%;
            border: none;
            resize: none;
            font-size: 1rem;
            font-family: monospace;
            padding: 1rem;
            box-sizing: border-box;
        }
        #saveMsg {
            position: fixed;
            top: 10px;
            right: 20px;
            background: #4caf50;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 1.5rem;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        #saveMsg.show {
            opacity: 1;
        }
        form {
            height: 100%;
            width: 100%;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!empty($children) || $parentPath): ?>
            <div class="sidebar">
                <?php if ($parentPath !== null): ?>
                    <a href="<?= htmlspecialchars($parentPath ?: '/') ?>">..</a>
                <?php endif; ?>
                <?php foreach ($children as $child): ?>
                    <a href="<?= htmlspecialchars($child) ?>"><?= htmlspecialchars(basename($child)) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="editor">
            <form method="POST" id="noteForm">
                <textarea name="content"><?= htmlspecialchars($note) ?></textarea>
            </form>
        </div>
    </div>

    <div id="saveMsg">Saved</div>

    <script>
    let timeout;
    const textarea = document.querySelector('textarea');
    const form = document.getElementById('noteForm');
    const saveMsg = document.getElementById('saveMsg');

    textarea.addEventListener('input', () => {
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            fetch(window.location.pathname, {
                method: 'POST',
                body: new FormData(form)
            }).then(resp => resp.text()).then(resp => {
                if (resp.trim() === 'saved') {
                    saveMsg.classList.add('show');
                    setTimeout(() => saveMsg.classList.remove('show'), 1000);
                }
            });
        }, 1000);
    });
    </script>
</body>
</html>
