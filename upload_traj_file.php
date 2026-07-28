<?php
// upload_traj_file.php
if (isset($_FILES['file'])) {
    $target = 'traj_files/' . basename($_FILES['file']['name']);
    move_uploaded_file($_FILES['file']['tmp_name'], $target);
    echo "OK";
} else {
    http_response_code(400);
    echo "No file";
}
?>