<?php
$upload_dir = "upload/";
$img = $_POST['imageData'];
$format_image = $_POST["formatImageSave"];
$data = base64_decode($img);
$file = $upload_dir . time() . "." . $format_image;
$success = file_put_contents($file, $data);
print $success ? $file : 'Unable to save the file.';
?>
