<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Choice\StoreChoiceRequest;
use App\Http\Requests\Question\StoreQuestionRequest;
use App\Http\Requests\Quiz\StoreQuizRequest;
use App\Http\Requests\Quiz\SubmitQuizRequest;
use App\Http\Resources\ChoiceResource;
use App\Http\Resources\QuestionResource;
use App\Http\Resources\QuizAttemptResource;
use App\Http\Resources\QuizResource;
use App\Models\Choice;
use App\Models\Level;
use App\Models\Question;
use App\Models\Quiz;
use App\Services\QuizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class QuizController extends Controller
{
    public function __construct(
        protected QuizService $quizService
    ) {}

    public function indexByLevel(Level $level): JsonResponse
    {
        $quizzes = $this->quizService->getActiveQuizzesByLevel($level);

        return response()->json([
            'success' => true,
            'data' => QuizResource::collection($quizzes),
        ], 200);
    }

    public function show(Quiz $quiz): JsonResponse
    {
        $quiz = $this->quizService->getQuizForPlayer($quiz);

        return response()->json([
            'success' => true,
            'data' => new QuizResource($quiz),
        ], 200);
    }

    public function store(StoreQuizRequest $request): JsonResponse
    {
        $quiz = $this->quizService->createQuiz($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Quiz created successfully.',
            'data' => new QuizResource($quiz),
        ], 201);
    }

    public function update(StoreQuizRequest $request, Quiz $quiz): JsonResponse
    {
        $quiz = $this->quizService->updateQuiz($quiz, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Quiz updated successfully.',
            'data' => new QuizResource($quiz),
        ], 200);
    }

    public function destroy(Quiz $quiz): JsonResponse
    {
        $this->quizService->deleteQuiz($quiz);

        return response()->json([
            'success' => true,
            'message' => 'Quiz deleted successfully.',
        ], 200);
    }

    public function addQuestion(StoreQuestionRequest $request, Quiz $quiz): JsonResponse
    {
        $question = $this->quizService->addQuestion($quiz, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Question added successfully.',
            'data' => new QuestionResource($question),
        ], 201);
    }

    public function updateQuestion(StoreQuestionRequest $request, Question $question): JsonResponse
    {
        $question = $this->quizService->updateQuestion($question, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Question updated successfully.',
            'data' => new QuestionResource($question),
        ], 200);
    }

    public function deleteQuestion(Question $question): JsonResponse
    {
        $this->quizService->deleteQuestion($question);

        return response()->json([
            'success' => true,
            'message' => 'Question deleted successfully.',
        ], 200);
    }

    public function addChoice(StoreChoiceRequest $request, Question $question): JsonResponse
    {
        $choice = $this->quizService->addChoice($question, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Choice added successfully.',
            'data' => new ChoiceResource($choice),
        ], 201);
    }

    public function updateChoice(StoreChoiceRequest $request, Choice $choice): JsonResponse
    {
        $choice = $this->quizService->updateChoice($choice, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Choice updated successfully.',
            'data' => new ChoiceResource($choice),
        ], 200);
    }

    public function deleteChoice(Choice $choice): JsonResponse
    {
        $this->quizService->deleteChoice($choice);

        return response()->json([
            'success' => true,
            'message' => 'Choice deleted successfully.',
        ], 200);
    }

    public function submit(SubmitQuizRequest $request, Quiz $quiz): JsonResponse
    {
        $result = $this->quizService->submitQuiz(
            Auth::id(),
            $quiz,
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Quiz submitted successfully.',
            'data' => [
                'attempt' => new QuizAttemptResource($result['attempt']),
                'score' => $result['score'],
                'total_questions' => $result['total_questions'],
            ],
        ], 201);
    }
}
