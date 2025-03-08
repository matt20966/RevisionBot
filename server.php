<?php
session_start();

// Ensure session initialization is correct
if (!isset($_SESSION['current_item'])) {
    $_SESSION['current_item'] = 1; // Initialize to the first item
    echo 'Session variable initialized to 1<br>';
}



// Connect to SQLite database
$db = new PDO('sqlite:QuestionAnswer.sqlite');

// Fetch the next item from the database
if ($_SESSION['current_item'] <= 3) { // Ensure this matches the number of items in your database
    $nextItemId = $_SESSION['current_item']; // Get the current item ID
    $stmt = $db->prepare('SELECT * FROM questions WHERE id = :id');
    $stmt->bindParam(':id', $nextItemId, PDO::PARAM_INT);
    $stmt->execute();
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    // Return the item as a JSON response
    if ($item) {
        $_SESSION['current_item']++; // Increment to the next item
        echo json_encode($item); // Send item data as JSON
    } else {
        echo json_encode(['error' => 'Item not found']);
    }
} else {
    echo json_encode(['error' => 'No more items']);
}








