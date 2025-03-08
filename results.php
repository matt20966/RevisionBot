<?php
require_once('Models/QuestionDataSet.php');
require_once('Models/RevisionInfoDataSet.php');
require_once('UserMarkingSystem/markingSystem.php');

// Get the quiz answers from cookies
$quizAnswers = isset($_COOKIE['quizAnswers']) ? json_decode($_COOKIE['quizAnswers'], true) : [];

// Connect to the database
$db = new PDO('sqlite:QuestionAnswer.sqlite');

// Fetch all questions for the topic
$topic = "Networking";
$stmt = $db->prepare('SELECT * FROM questions WHERE topic = :topic ORDER BY id');
$stmt->bindParam(':topic', $topic, PDO::PARAM_STR);
$stmt->execute();
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$questionData = new QuestionDataSet();
$markingBot = new ExtractiveQA('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');

$results = [];

// Loop through all questions from the database
foreach ($questions as $index => $question) {
    $questionId = $index; // or use $question['id'] if available
    $userAnswer = $quizAnswers[$index]['answer'] ?? ""; // Use empty string if no answer was provided

    // Get context, question text, and the correct answer from the question
    $context = $question['context'];
    $questionText = $question['question'];
    $correctAnswer = $question['answer'];

    // Only attempt marking if a non-empty answer was provided
    if (trim($userAnswer) !== "") {
        try {
            // Mark the answer using the marking bot
            $markingResult = $markingBot->returnResults($questionText, $context, $userAnswer);

            // Store the results with question ID for reference, including the correct answer
            $results[$questionId] = [
                'questionId'     => $questionId,
                'marking_result' => $markingResult,
                'score'          => $markingResult['data']['answers']['user']['validation']['similarityScore'],
                'userAnswer'     => $userAnswer,
                'question'       => $questionText,
                'context'        => $context,
                'answer'         => $correctAnswer
            ];
        } catch (Exception $e) {
            $results[$questionId] = [
                'error'      => $e->getMessage(),
                'questionId' => $questionId,
                'userAnswer' => $userAnswer,
                'question'   => $questionText,
                'context'    => $context,
                'answer'     => $correctAnswer
            ];
        }
    } else {
        // No answer was provided, so we simply store the question details with a notice, including the correct answer
        $results[$questionId] = [
            'questionId'     => $questionId,
            'marking_result' => null,
            'score'          => null,
            'userAnswer'     => $userAnswer,
            'question'       => $questionText,
            'context'        => $context,
            'answer'         => $correctAnswer,
            'message'        => 'No answer provided'
        ];
    }
}

// Pass all results to the front-end as a JavaScript variable
echo "<script>var quizResults = " . json_encode($results) . ";</script>";

require_once('Views/results.phtml');
