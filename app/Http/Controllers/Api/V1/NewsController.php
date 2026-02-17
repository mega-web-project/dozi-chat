<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\News;
use App\Models\Comment;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Events\NewsCreated;
use App\Events\CommentAdded;
use App\Events\PollVoted;

class NewsController extends Controller
{
    /**
     * GET /api/v1/news
     * Fetch paginated news feed
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $news = News::with([
                'user:id,name,email',
                'comments.user:id,name',
                'poll.options'
            ])
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => 'News fetched successfully',
            'data' => $news
        ]);
    }

    /**
     * POST /api/v1/news
     * Create news (with optional poll)
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'image' => 'nullable|string',
            'poll_question' => 'nullable|string',
            'poll_options' => 'nullable|array|min:2',
            'poll_options.*' => 'string'
        ]);

        DB::beginTransaction();

        try {

            $news = News::create([
                'user_id' => Auth::id(),
                'title' => $request->title,
                'content' => $request->content,
                'image' => $request->image,
            ]);

            // Create poll if provided
            if ($request->poll_question && $request->poll_options) {

                $poll = Poll::create([
                    'news_id' => $news->id,
                    'question' => $request->poll_question,
                ]);

                foreach ($request->poll_options as $option) {
                    PollOption::create([
                        'poll_id' => $poll->id,
                        'option_text' => $option,
                    ]);
                }
            }

            $news->load('user', 'poll.options');

            DB::commit();

            broadcast(new NewsCreated($news))->toOthers();

            return response()->json([
                'status' => true,
                'message' => 'News created successfully',
                'data' => $news
            ], 201);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Failed to create news',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /api/v1/news/{id}
     */
    public function destroy($id)
    {
        $news = News::findOrFail($id);

        if ($news->user_id !== Auth::id()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $news->delete();

        return response()->json([
            'status' => true,
            'message' => 'News deleted successfully'
        ]);
    }

    /**
     * POST /api/v1/news/{id}/comments
     */
    public function addComment(Request $request, $id)
    {
        $request->validate([
            'comment' => 'required|string'
        ]);

        $news = News::findOrFail($id);

        $comment = Comment::create([
            'news_id' => $news->id,
            'user_id' => Auth::id(),
            'comment' => $request->comment,
        ]);

        $comment->load('user');

        broadcast(new CommentAdded($comment))->toOthers();

        return response()->json([
            'status' => true,
            'message' => 'Comment added successfully',
            'data' => $comment
        ], 201);
    }

    /**
     * POST /api/v1/news/{id}/poll/vote
     */
    public function vote(Request $request, $id)
    {
        $request->validate([
            'option_id' => 'required|exists:poll_options,id'
        ]);

        $poll = Poll::where('news_id', $id)->firstOrFail();

        DB::beginTransaction();

        try {

            // Prevent duplicate voting
            $alreadyVoted = PollVote::where('poll_id', $poll->id)
                ->where('user_id', Auth::id())
                ->exists();

            if ($alreadyVoted) {
                return response()->json([
                    'status' => false,
                    'message' => 'You have already voted'
                ], 400);
            }

            PollVote::create([
                'poll_id' => $poll->id,
                'poll_option_id' => $request->option_id,
                'user_id' => Auth::id(),
            ]);

            $option = PollOption::where('id', $request->option_id)
                ->where('poll_id', $poll->id)
                ->firstOrFail();

            $option->increment('votes');

            $poll->load('options');

            DB::commit();

            broadcast(new PollVoted($poll))->toOthers();

            return response()->json([
                'status' => true,
                'message' => 'Vote recorded successfully',
                'data' => $poll
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Voting failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
