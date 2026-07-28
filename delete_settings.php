<?php
$filename = $_GET['filename'] ?? '';
$filepath = 'settings/' . $filename;
if (file_exists($filepath) && unlink($filepath)) {
    echo "File deleted successfully.";
} else {
    echo "Error deleting file.";
}
?>