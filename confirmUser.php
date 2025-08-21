<?php require "functions.php";

    if($_POST['confirm_save'] === "1" && $_POST['id'])
    {
        $id = $_POST['id'];
        $employee = \Models\Employees::find($id);
        $employee->update(['isReady'=>TRUE]);
        header("Location: ready.php?id=" . urlencode($id));
        exit;

    }
    else
    {
        header('Location: sign.php');
        exit;
    }

    var_dump($_POST)

?>