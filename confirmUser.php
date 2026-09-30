<?php require "functions.php";

    if(($_POST['confirm_save'] ?? '') === "1" && validId($_POST['id'] ?? ''))
    {
        $id = validId($_POST['id']);
        $result = confirmEmployee($id); // проверка суммы и запись - в одной транзакции под блокировкой
        if($result === 'notfound')
        {
            header('Location: sign.php');
            exit;
        }
        if($result === 'ok')
        {
            header("Location: ready.php?id=" . urlencode($id));
            exit;
        }
        else
        {
            ?>
            <script>
                alert('Ошибка. Нужно потратить все доступные дни')
                window.location.href = 'user.php?id=<?= urlencode($id) ?>';
            </script>
            <?php
            exit;
        }
    }
    else
    {
        header('Location: sign.php');
        exit;
    }

    var_dump($_POST)
?>