<?php

namespace App\Http\Controllers;

use App\Services\RewardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HelpController extends Controller
{
    public function __construct(private RewardService $rewards) {}

    public function index(Request $request): View
    {
        $activity = DB::table('activities')->where('enabled', true)->firstOrFail();
        $uid = $request->user()->id;
        $landmarks = DB::table('board_cells')->leftJoin('user_landmarks', fn ($join) => $join->on('board_cells.id', '=', 'user_landmarks.board_cell_id')->where('user_landmarks.user_id', $uid))->where(['board_cells.activity_id' => $activity->id, 'board_cells.category' => 'landmark'])->orderBy('board_cells.position')->select('board_cells.*', 'user_landmarks.visit_count', 'user_landmarks.first_unlocked_at')->get();
        $rewards = DB::table('landmark_reward_definitions')->where('activity_id', $activity->id)->orderBy('required_count')->get();
        $claimed = DB::table('user_landmark_rewards')->where('user_id', $uid)->pluck('landmark_reward_definition_id')->all();
        $faqs = DB::table('faq_entries')->where('enabled', true)->orderBy('sort_order')->get()->groupBy('category');
        $state = DB::table('activity_users')->where(['activity_id' => $activity->id, 'user_id' => $uid])->firstOrFail();

        return view('help.index', compact('activity', 'landmarks', 'rewards', 'claimed', 'faqs', 'state'));
    }

    public function completeTutorial(Request $request): RedirectResponse|Response
    {
        $activity = DB::table('activities')->where('enabled', true)->firstOrFail();
        DB::table('activity_users')->where(['activity_id' => $activity->id, 'user_id' => $request->user()->id])->update(['tutorial_seen_at' => now(), 'updated_at' => now()]);

        return $request->expectsJson() ? response()->noContent() : back();
    }

    public function claimLandmarkReward(Request $request, int $reward): RedirectResponse
    {
        $activity = DB::table('activities')->where('enabled', true)->firstOrFail();
        $definition = DB::table('landmark_reward_definitions')->where(['id' => $reward, 'activity_id' => $activity->id])->first();
        abort_if(! $definition, 404);
        $uid = $request->user()->id;
        $count = DB::table('user_landmarks')->where(['activity_id' => $activity->id, 'user_id' => $uid])->count();
        abort_if($count < $definition->required_count, 422, '地标收集数量不足');
        DB::transaction(function () use ($definition, $uid, $activity) {
            abort_if(DB::table('user_landmark_rewards')->where(['landmark_reward_definition_id' => $definition->id, 'user_id' => $uid])->exists(), 422, '奖励已经领取');
            DB::table('user_landmark_rewards')->insert(['landmark_reward_definition_id' => $definition->id, 'user_id' => $uid, 'claimed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $this->rewards->grant($activity->id, $uid, $definition->reward_type, $definition->reward_value, "landmark-reward-{$definition->id}-{$uid}", $definition->reward_label);
        });

        return back()->with('success', '地标收集宝箱已领取');
    }
}
