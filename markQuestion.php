<?php
require_once('Models/QuestionDataSet.php');
require_once('UserMarkingSystem/markingSystem.php');

/**
 * Marks the user's answer for a given question.
 *
 * @param string $question      The question text.
 * @param string $userAnswer    The user's answer.
 * @param string $context       The context for the question.
 * @param string $correctAnswer The correct answer to the question.
 * @return array The marking result, including the model's answer and marking score.
 */
function markQuestion($question, $userAnswer, $context, $correctAnswer) {
    // Initialize the marking bot with your API key.
    $markingBot = new ExtractiveQA('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');

    try {
        // Get the marking results.
        $result = $markingBot->returnResults($question, $context, $userAnswer);

        // Extract relevant fields.
        $similarityScore = isset($result['data']['answers']['user']['validation']['similarityScore'])
            ? $result['data']['answers']['user']['validation']['similarityScore']
            : null;

        $feedback = isset($result['data']['answers']['user']['validation']['feedback'])
            ? $result['data']['answers']['user']['validation']['feedback']
            : "No feedback available.";

        // Add additional fields for reference.
        return [
            'success' => $result['success'],
            'questionText' => $question,
            'context' => $context,
            'correctAnswer' => $correctAnswer,
            'userAnswer' => $userAnswer,
            'modelAnswer' => $result['data']['answers']['model']['text'] ?? null,
            'confidenceScore' => $result['data']['answers']['model']['confidenceScore'] ?? null,
            'similarityScore' => $similarityScore,
            'feedback' => $feedback
        ];
    } catch (Exception $e) {
        // Return detailed error information.
        return [
            'success' => false,
            'error' => 'Marking failed: ' . $e->getMessage(),
            'userAnswer' => $userAnswer,
            'questionText' => $question,
            'context' => $context,
            'correctAnswer' => $correctAnswer
        ];
    }
}


// Process the POST request.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Expecting 'question', 'userAnswer', 'context', and 'correctAnswer' in POST data.
    $question      = isset($_POST['question']) ? trim($_POST['question']) : '';
    $userAnswer    = isset($_POST['userAnswer']) ? trim($_POST['userAnswer']) : '';
    $context       = isset($_POST['context']) ? trim($_POST['context']) : '';
    $correctAnswer = isset($_POST['correctAnswer']) ? trim($_POST['correctAnswer']) : '';

    // Validate required fields
    if (empty($question) || empty($userAnswer) || empty($correctAnswer)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Missing required fields: question, userAnswer, or correctAnswer.']);
        exit;
    }

    // Mark the question and echo the JSON response.
    $markingResult = markQuestion($question, $userAnswer, $context, $correctAnswer);
    header('Content-Type: application/json');
    echo json_encode($markingResult);
    exit;
}