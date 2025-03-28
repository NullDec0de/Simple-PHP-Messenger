<?php
session_start();

// Завершаем сессию
session_unset();
session_destroy();

// Перенаправляем пользователя на страницу входа
header("Location: index.php");
exit;
