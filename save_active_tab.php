<?php
session_start();

if (isset($_POST['active_tab'])) {
    $_SESSION['active_tab'] = $_POST['active_tab'];
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>
