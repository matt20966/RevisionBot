<?php
/**
 * =============================================================================
 * Marking API Endpoint (markAndSave or justSave)
 * =============================================================================
 * This script accepts the usual question and answer details plus an additional
 * "timeSpent" (in seconds) parameter that represents the time the user spent on
 * the question. That value is passed in as $timeRevising to the marking functions.
 * -----------------------------------------------------------------------------
 */

// =============================================================================
// INCLUDE REQUIRED FILES
// =============================================================================
require_once('Models/QuestionDataSet.php');       // Optional: Data model for questions
require_once('UserMarkingSystem/markingSystem.php');  // Marking system using ExtractiveQA
require_once('Models/RevisionInfoDataSet.php');       // Revision info model (for DB insertion)

/**
 * ---------------------------------------------------------------------------
 * Function: markQuestion
 * ---------------------------------------------------------------------------
 * Evaluates the user's answer using the marking bot and saves the results.
 *
 * @param int    $questionID    The question identifier.
 * @param string $question      The question text.
 * @param string $userAnswer    The user's submitted answer.
 * @param string $context       The context for the question (e.g., subject area).
 * @param string $correctAnswer The expected correct answer text.
 * @param int    $format        The question format (e.g., 1, 2, 3, 4).
 * @param int    $timeRevising  The time (in seconds) the user spent on the question.
 *
 * @return array Returns an associative array with marking details.
 */
function markQuestion($questionID, $question, $userAnswer, $context, $correctAnswer, $format, $timeRevising) {
    // Initialize the marking bot with your API key.
    $markingBot = new ExtractiveQA('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');

    try {
        // Validate input parameters.
        if (empty($question) || empty($userAnswer) || empty($correctAnswer)) {
            throw new InvalidArgumentException('Required parameters cannot be empty');
        }
        if (!is_numeric($questionID)) {
            throw new InvalidArgumentException('Invalid question ID');
        }

        // Obtain marking results from the bot.
        $result = $markingBot->returnResults($question, $context, $userAnswer);

        // Extract validation data.
        $similarityScore = isset($result['data']['answers']['user']['validation']['similarityScore'])
            ? $result['data']['answers']['user']['validation']['similarityScore']
            : 0;
        $feedback = isset($result['data']['answers']['user']['validation']['feedback'])
            ? $result['data']['answers']['user']['validation']['feedback']
            : "No feedback available.";

        // Use the provided timeRevising value and preset other constants.
        $topic             = "Networking";
        $subject           = "Computer Science";
        $questionFrequency = 0;
        $userID            = 1;

        // Calculate the mark based on the similarity score.
        $resultsMark = 0;
        if ($similarityScore > 50) {
            $resultsMark = ($similarityScore > 80) ? 2 : 1;
        }

        // Save the marking data to the database.
        $saveResult = saveMark(
            $questionID,
            $timeRevising,
            $topic,
            $subject,
            $format,
            $questionFrequency,
            $userID,
            $resultsMark
        );

        if (!$saveResult['success']) {
            error_log("Failed to save mark for question ID: " . $questionID);
        }

        // Return the complete marking result.
        return array(
            'success'         => true,
            'questionText'    => $question,
            'context'         => $context,
            'correctAnswer'   => $correctAnswer,
            'userAnswer'      => $userAnswer,
            'modelAnswer'     => isset($result['data']['answers']['model']['text']) ? $result['data']['answers']['model']['text'] : null,
            'confidenceScore' => isset($result['data']['answers']['model']['confidenceScore']) ? $result['data']['answers']['model']['confidenceScore'] : null,
            'similarityScore' => $similarityScore,
            'feedback'        => $feedback,
            'mark'            => $resultsMark,
            'saveStatus'      => $saveResult['success']
        );

    } catch (Exception $e) {
        // Log and return error details.
        error_log("Error in markQuestion: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        return array(
            'success'      => false,
            'error'        => 'Marking failed: ' . $e->getMessage(),
            'trace'        => $e->getTraceAsString(),
            'userAnswer'   => $userAnswer,
            'questionText' => $question,
            'context'      => $context,
            'correctAnswer'=> $correctAnswer
        );
    }
}

/**
 * ---------------------------------------------------------------------------
 * Function: justSave
 * ---------------------------------------------------------------------------
 * Simply saves the provided question details to the database using preset
 * values (except for the time spent, which is taken from the POST data).
 *
 * @param int    $questionID    The question identifier.
 * @param string $question      The question text.
 * @param string $userAnswer    The user's submitted answer.
 * @param string $context       The context for the question.
 * @param string $correctAnswer The expected correct answer text.
 * @param int    $format        The question format.
 * @param int    $timeRevising  The time (in seconds) the user spent on the question.
 *
 * @return array Returns an associative array with the saved details and a success status.
 */
function justSave($questionID, $question, $userAnswer, $context, $correctAnswer, $format, $timeRevising) {
    // Preset constants (using the provided timeRevising value).
    $topic             = "Networking";
    $subject           = "Computer Science";
    $questionFrequency = 0;
    $userID            = 1;
    $results           = 0;

    // Save the provided details to the database.
    $saveResult = saveMark(
        $questionID,
        $timeRevising,
        $topic,
        $subject,
        $format,
        $questionFrequency,
        $userID,
        $results
    );

    if (!$saveResult['success']) {
        error_log("Failed to save (justSave) for question ID: " . $questionID);
    }

    // Return the details that were saved along with a success indicator.
    return array(
        'success'           => true,
        'questionText'      => $question,
        'context'           => $context,
        'correctAnswer'     => $correctAnswer,
        'userAnswer'        => $userAnswer,
        'format'            => $format,
        'timeRevising'      => $timeRevising,
        'topic'             => $topic,
        'subject'           => $subject,
        'questionFrequency' => $questionFrequency,
        'results'           => $results,
        'saveStatus'        => $saveResult['success'],
        'lastInsertId'      => $saveResult['lastInsertId']
    );
}

/**
 * ---------------------------------------------------------------------------
 * Function: saveMark
 * ---------------------------------------------------------------------------
 * Saves the marking data to the SQLite database using PDO.
 *
 * @param int    $questionID       The question identifier.
 * @param int    $timeRevising     Time in seconds the user spent revising.
 * @param string $topic            Topic of the revision session.
 * @param string $subject          Subject area of the revision session.
 * @param int    $format           Format type.
 * @param int    $questionFrequency Frequency indicator.
 * @param int    $userID           User identifier.
 * @param int    $results          The calculated mark.
 *
 * @return array Returns an associative array with success status and the last insert ID.
 */
function saveMark($questionID, $timeRevising, $topic, $subject, $format, $questionFrequency, $userID, $results) {
    try {
        // Validate input parameters.
        if (!is_numeric($questionID) || !is_numeric($timeRevising) || !is_numeric($format) ||
            !is_numeric($questionFrequency) || !is_numeric($userID)) {
            throw new InvalidArgumentException('Invalid numeric parameters provided');
        }
        if (empty($topic) || empty($subject)) {
            throw new InvalidArgumentException('Required string parameters cannot be empty');
        }

        // Create a new PDO instance for SQLite.
        $db = new PDO('sqlite:QuestionAnswer.sqlite');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Prepare the SQL INSERT statement.
        $sqlQuery = "INSERT INTO revisionInfo (timeRevising, topic, subject, format, questionFrequency, userID, results, questionID) 
                     VALUES (:timeRevising, :topic, :subject, :format, :questionFrequency, :userID, :results, :questionID)";
        $statement = $db->prepare($sqlQuery);

        // Bind parameters and execute the statement.
        $statement->execute([
            ':timeRevising'      => $timeRevising,
            ':topic'             => $topic,
            ':subject'           => $subject,
            ':format'            => $format,
            ':questionFrequency' => $questionFrequency,
            ':userID'            => $userID,
            ':results'           => $results,
            ':questionID'        => $questionID
        ]);

        // Retrieve and return the last inserted ID.
        $insertId = $db->lastInsertId();
        return array(
            'success'      => true,
            'lastInsertId' => $insertId,
            'message'      => 'Mark saved successfully'
        );

    } catch (Exception $e) {
        // Log and return error details.
        error_log("Error in saveMark function: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        return array(
            'success' => false,
            'error'   => $e->getMessage(),
            'trace'   => $e->getTraceAsString(),
            'message' => 'Failed to save mark'
        );
    }
}

// =============================================================================
// PROCESS INCOMING REQUESTS
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize POST data.
    $questionID    = isset($_POST['questionID']) ? (int)$_POST['questionID'] : 0;
    $question      = isset($_POST['question']) ? trim($_POST['question']) : '';
    $userAnswer    = isset($_POST['userAnswer']) ? trim($_POST['userAnswer']) : '';
    $context       = isset($_POST['context']) ? trim($_POST['context']) : '';
    $correctAnswer = isset($_POST['correctAnswer']) ? trim($_POST['correctAnswer']) : '';
    $format        = isset($_POST['format']) ? (int)$_POST['format'] : 0;
    $timeRevising  = isset($_POST['timeSpent']) ? (int)$_POST['timeSpent'] : 0;

    // Validate required fields.
    if (!$questionID || empty($question) || empty($userAnswer) || empty($correctAnswer) || !$format) {
        header('Content-Type: application/json');
        echo json_encode(array(
            'success' => false,
            'error'   => 'Missing required fields: questionID, question, userAnswer, correctAnswer, or format.',
            'data'    => array(
                'questionID'    => $questionID,
                'question'      => $question,
                'userAnswer'    => $userAnswer,
                'correctAnswer' => $correctAnswer,
                'format'        => $format
            )
        ));
        exit;
    }

    // Check if "justSave" mode is enabled via POST (e.g., justSave=1)
    if (isset($_POST['justSave']) && $_POST['justSave'] == 1) {
        $result = justSave($questionID, $question, $userAnswer, $context, $correctAnswer, $format, $timeRevising);
    } else {
        // Otherwise, perform the usual marking.
        $result = markQuestion($questionID, $question, $userAnswer, $context, $correctAnswer, $format, $timeRevising);
    }

    // Return the result as JSON.
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
} else {
    // If the request is not POST, return a 405 Method Not Allowed response.
    header($_SERVER['SERVER_PROTOCOL'] . ' 405 Method Not Allowed');
    header('Content-Type: application/json');
    echo json_encode(array(
        'success' => false,
        'error'   => 'Method Not Allowed. Use POST.'
    ));
    exit;
}
?>
