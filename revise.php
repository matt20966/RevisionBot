<?php
session_start(); // Start the session
if (!isset($_SESSION['question_id'])) {
    $_SESSION['question_id'] = 1; // Default starting question ID
}

// Define variables
$view = new stdClass();
$view->format = 2;
$format = 2;
$view->result = "";
$view->feedback = "";
$questionID = $_SESSION['question_id']; // Get the current question ID from the session
$topic = "Networking";
$subject = "Computer Science";
$questionFrequency = 1;
$userID = 1;
require_once('Models/QuestionDataSet.php');
require_once('Models/RevisionInfoDataSet.php');
require_once('UserMarkingSystem/markingSystem.php');
$questionData = new QuestionDataSet();
$question = $questionData->getQuestionByID($questionID);
$view->question = $question;
// Check if the form has been submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $userAnswer = $_POST['userAnswer'];
    $view->result = markQuestion($questionID, $userAnswer, $format, $view);
}

if (isset($_POST['next'])) {
    $_SESSION['question_id'] += 1; // Increment question_id
    $questionID = $_SESSION['question_id']; // Update the question_id for the current request
    $question = $questionData->getQuestionByID($questionID);
    $view->question = $question;
}



/**
 * Marks the user's answer
 *
 * @param $questionID
 * @param $userAnswer
 * @param $format
 * @return int returns the mark of the question
 */
function markQuestion($questionID, $userAnswer, $format, $view)
{
    $questionData = new QuestionDataSet();
    $answer = $questionData->getAnswerByID($questionID);
    $context = "HTTP stands for HyperText Transfer Protocol, and it's the computer communication protocol used for most communication on the world wide web.";
    $question = "What does HTTP stand for?";
    $markingBot = new ExtractiveQA('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');
    $result = ($markingBot->returnResults($question,$context,$userAnswer));
    $view->feedback = $result;
    if ($result['data']['answers']['user']['validation']['similarityScore'] > 80) {
        saveData($questionID, 1, 1, $format);
    } else {
        saveData($questionID, 0, 1, $format);
    }
    return "done";
}

function saveData($questionID, $marksAchieved, $questionMarks, $format) {
    // Get existing session data
    $sessionData = getQuestionData();

    // Generate a new ID by finding the highest ID and incrementing it
    $newID = count($sessionData) + 1; // Use count to ensure new ID is unique and incrementing

    // Create new question entry with unique ID
    $newQuestion = array(
        'id' => $newID,
        'questionID' => $questionID,
        'marksAchieved' => $marksAchieved,
        'questionMarks' => $questionMarks,
        'format' => $format,
        'timestamp' => time()
    );

    // Add the new question entry to session data
    $sessionData[$newID] = $newQuestion;

    // Store the updated data in the session
    setSessionData($sessionData);

    return true;
}

function setSessionData($data) {
    $_SESSION['question_data'] = $data;
}

function getQuestionData() {
    return isset($_SESSION['question_data']) ? $_SESSION['question_data'] : array();
}

function clearQuestionData() {
    unset($_SESSION['question_data']);
}

if (isset($_POST['action']) && $_POST['action'] === 'saveSession') {
    $timeSpent = isset($_POST['timeSpent']) ? (int)$_POST['timeSpent'] : 0;
    saveSession($timeSpent, $topic, $subject, $questionFrequency, $userID);
}

function saveSession($timeRevising, $topicRevising, $subjectRevising, $frequencyOfQuestions, $userId) {
    $data = getQuestionData();

    // If session data exists, iterate through each question entry
    if (!empty($data)) {
        $formattedResults = [];

        // Iterate through the questions and save each one in a separate cookie
        foreach ($data as $questionData) {
            // Collect the results of each question (add questionID, marksAchieved, and questionMarks as a comma-separated string)
            $formattedResults = $questionData['questionID'] . "," . $questionData['marksAchieved'] . "," . $questionData['questionMarks'];

            // Now upload the session data (one question at a time)
            $RevisionFormat = $questionData['format'];

            $upload = new RevisionInfoDataSet();
            $response = $upload->uploadData(
                $time = $timeRevising,
                $topic = $topicRevising,
                $subject = $subjectRevising,
                $format = $RevisionFormat,
                $frequency = $frequencyOfQuestions,
                $userID = $userId,
                $results = $formattedResults // Pass the formatted results as a single string
            );
            echo json_encode($response);
        }
        session_unset(); // Unset all session variables
        session_destroy(); // Destroy the session
    } else {
        echo "Error: No data found in session.";
    }
}
require_once('Views/revise.phtml');
