<?php
// Simple test to see what PHP receives when FormData has multiple files with same key
echo "Test: Checking how FormData with multiple files is received\n";
echo "Expected: HTTP POST with 2 image files under 'photo' key\n";
echo "This mimics: formData.append('photo', file1); formData.append('photo', file2);\n";

// Display received data
if (!empty($_FILES)) {
    echo "\nReceived FILES:\n";
    echo json_encode($_FILES, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "\nNo files received\n";
}
?>
