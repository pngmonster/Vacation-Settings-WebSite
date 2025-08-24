<?php require "functions.php";

    if($_POST['confirm_save'] === "1" && $_POST['id'])
    {
        $id = $_POST['id'];
        $employee = \Models\Employees::find($id);
        if(($employee->lenght1 + $employee->lenght2 + $employee->lenght3) === $employee->position()->first()->maxday)
        {
            $employee->update(['isReady'=>TRUE]);
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