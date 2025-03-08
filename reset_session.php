<?php

session_start();

// Clear all session variables
session_unset(); // Unset all session variables

// Destroy the session
session_destroy();

// Start a new session
session_start(); // Start a new session

// Initialize session variable for tracking the item
$_SESSION['current_item'] = 1; // Set to 1 to start from the first item

echo json_encode(['status' => 'success', 'message' => 'Session reset']);

