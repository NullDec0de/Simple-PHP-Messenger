<?php
session_start();
require 'database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Поиск пользователей
$search_results = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    $search = $_POST['search'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username LIKE ? AND id != ?");
    $stmt->execute(["%$search%", $user_id]);
    $search_results = $stmt->fetchAll();
}

// Добавление контакта
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_contact'])) {
    $contact_id = $_POST['contact_id'];
    $stmt = $pdo->prepare("INSERT IGNORE INTO contacts (user_id, contact_id) VALUES (?, ?)");
    $stmt->execute([$user_id, $contact_id]);
}

// Получение списка контактов
$contacts = $pdo->prepare("
    SELECT u.id, u.username 
    FROM contacts c
    JOIN users u ON u.id = c.contact_id
    WHERE c.user_id = ?
");
$contacts->execute([$user_id]);
$contacts = $contacts->fetchAll();

// История сообщений с выбранным контактом
$messages = [];
$selected_contact = null;

if (isset($_GET['contact_id'])) {
    $selected_contact = $_GET['contact_id'];
    $stmt = $pdo->prepare("
        SELECT * FROM messages 
        WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) 
        ORDER BY timestamp ASC
    ");
    $stmt->execute([$user_id, $selected_contact, $selected_contact, $user_id]);
    $messages = $stmt->fetchAll();
}

// Отправка сообщения
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = $_POST['message'];
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $selected_contact, $message]);
    echo json_encode(['status' => 'success']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Чат</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
        }
        .container {
            display: flex;
            flex-direction: row;
            height: 100vh;
        }
        .contacts {
            width: 300px;
            border-right: 1px solid #ddd;
            padding: 20px;
            overflow-y: auto;
        }
        .chat-area {
            flex: 1;
            padding: 20px;
            display: flex;
            flex-direction: column;
        }
        .chat-window {
            flex: 1;
            overflow-y: auto;
            background: #f5f5f5;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .message {
            margin-bottom: 10px;
        }
        .message.sent {
            text-align: right;
        }
        .message .text {
            display: inline-block;
            padding: 10px 15px;
            border-radius: 20px;
        }
        .message.sent .text {
            background: #0084ff;
            color: #fff;
        }
        .message.received .text {
            background: #e5e5ea;
            color: #000;
        }
        .avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #0084ff;
            color: white;
            text-align: center;
            line-height: 30px;
            font-weight: bold;
            margin-right: 10px;
        }
        a{
            text-decoration: none;
            font-size: 20px;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<div class="container">
    <!-- Контакты -->
    <div class="contacts">
        <h4>Поиск пользователей</h4>
        <form method="POST" class="mb-3">
            <div class="input-group">
                <input type="text" name="search" class="form-control" placeholder="Искать пользователя по никнейму">
                <button class="btn btn-primary">Искать</button>
            </div>
        </form>

        <?php if (!empty($search_results)): ?>
            <h4>Результаты поиска</h4>
            <ul class="list-group mb-3">
                <?php foreach ($search_results as $result): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>
                            <div class="avatar"><?= strtoupper($result['username'][0]) ?></div>
                            <?= htmlspecialchars($result['username']) ?>
                        </span>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="contact_id" value="<?= $result['id'] ?>">
                            <button type="submit" name="add_contact" class="btn btn-success btn-sm">Добавить</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <h4>Контакты</h4>
        <ul class="list-group">
            <?php foreach ($contacts as $contact): ?>
                <li class="list-group-item">
                    <a href="?contact_id=<?= $contact['id'] ?>">
                        <div class="avatar"><?= strtoupper($contact['username'][0]) ?></div>
                        <?= htmlspecialchars($contact['username']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <a href="logout.php" class="btn btn-danger btn-sm rounded-pill">
    <i class="fa fa-sign-out" aria-hidden="true"></i> Выйти
</a>

    </div>

    <!-- Чат -->
    <div class="chat-area">
        <?php if ($selected_contact): ?>
            <h4>Чат с <?= htmlspecialchars($contacts[array_search($selected_contact, array_column($contacts, 'id'))]['username'] ?? 'пользователем') ?></h4>
            <div id="chatWindow" class="chat-window">
                <?php foreach ($messages as $msg): ?>
                    <div class="message <?= $msg['sender_id'] == $user_id ? 'sent' : 'received' ?>">
                        <div class="text">
                            <?= htmlspecialchars($msg['message']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <form id="messageForm">
                <div class="input-group">
                    <input type="text" name="message" id="messageInput" class="form-control" placeholder="Введите сообщение" required>
                    <button class="btn btn-primary">Отправить</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
    $(document).ready(function () {
        const chatWindow = $('#chatWindow');
        const messageForm = $('#messageForm');
        const messageInput = $('#messageInput');

        messageForm.on('submit', function (e) {
            e.preventDefault();
            const message = messageInput.val();

            if (message.trim() !== '') {
                $.post(window.location.href, { message: message }, function (data) {
                    const response = JSON.parse(data);
                    if (response.status === 'success') {
                        chatWindow.append(`
                            <div class="message sent">
                                <div class="text">${message}</div>
                            </div>
                        `);
                        messageInput.val('');
                        chatWindow.scrollTop(chatWindow[0].scrollHeight);
                    }
                });
            }
        });

        setInterval(function () {
            $.get(window.location.href, function (data) {
                $('#chatWindow').html($(data).find('#chatWindow').html());
            });
        }, 3000);
    });
</script>
</body>
</html>
