<?php

function uploadImages($uploadedFiles, $path, $maxWidth = 1920, $maxHeight = 1080)
{

    $return = false;

    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }

    if (is_array($uploadedFiles['tmp_name'])) {
        $return = array();
        foreach ($uploadedFiles['tmp_name'] as $key => $tmpName) {
            $result = processImage($tmpName, $uploadedFiles['name'][$key], $path, $maxWidth, $maxHeight);
            if ($result !== false) { // Check for successful processing
                $return[] = $result;
            } else {
                // Handle error (log, display message, etc.)
                // Example: echo "Error processing image: " . $uploadedFiles['name'][$key];
            }
        }
    } else {
        $return = processImage($uploadedFiles['tmp_name'], $uploadedFiles['name'], $path, $maxWidth, $maxHeight);
        if ($return === false) {
            // Handle error
            // Example: echo "Error processing image: " . $uploadedFiles['name'];
        }
    }

    return $return;
}

function processImage($tmpName, $originalName, $path, $maxWidth, $maxHeight)
{
    list($originalWidth, $originalHeight) = getimagesize($tmpName);

    $imageName = false;
    $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight); // Changed max to min
    $newWidth = (int)round($originalWidth * $ratio);
    $newHeight = (int)round($originalHeight * $ratio);

    $newImage = imagecreatetruecolor($newWidth, $newHeight); // Adjust the newImage size

    $imageType = exif_imagetype($tmpName);
    switch ($imageType) {
        case IMAGETYPE_JPEG:
            $originalImage = imagecreatefromjpeg($tmpName);
            break;
        case IMAGETYPE_PNG:
            $originalImage = imagecreatefrompng($tmpName);
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            break;
        case IMAGETYPE_GIF:
            $originalImage = imagecreatefromgif($tmpName);
            break;
        default:
            return false; // Indicate an error for unsupported image type
    }

    imagecopyresampled($newImage, $originalImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

// Create the final image with the canvas size
    $croppedImage = imagecreatetruecolor($maxWidth, $maxHeight);
    $imageType == IMAGETYPE_PNG ? imagealphablending($croppedImage, false) : null;
    $imageType == IMAGETYPE_PNG ? imagesavealpha($croppedImage, true) : null;

// Calculate the center cropping
    $xOffset = ($newWidth - $maxWidth) / 2;
    $yOffset = ($newHeight - $maxHeight) / 2;
    imagecopy($croppedImage, $newImage, 0, 0, $xOffset, $yOffset, $maxWidth, $maxHeight);

    $destinationPath = $path . '/' . $originalName;
    switch ($imageType) {
        case IMAGETYPE_JPEG:
            imagejpeg($croppedImage, $destinationPath, 90);
            break;
        case IMAGETYPE_PNG:
            imagepng($croppedImage, $destinationPath);
            break;
        case IMAGETYPE_GIF:
            imagegif($croppedImage, $destinationPath);
            break;
    }

    imagedestroy($originalImage);
    imagedestroy($newImage);
    imagedestroy($croppedImage);

    $imageName = $originalName; // Image was successfully processed and resized

    return $imageName;
}