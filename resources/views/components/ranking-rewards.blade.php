@props(['variant' => 'compact'])
@php($rewards = config('activity.ranking_rewards', []))

@if(count($rewards) >= 6)
  <section class="ranking-reward-showcase {{ $variant }}" aria-labelledby="rankingRewardTitle-{{ $variant }}">
    <header class="ranking-reward-heading">
      <div>
        <span>SEASON REWARDS</span>
        <h3 id="rankingRewardTitle-{{ $variant }}">赛季排名大奖</h3>
      </div>
      <b>TOP 10 有奖</b>
    </header>

    <div class="ranking-reward-content">
      <article class="ranking-champion">
        <div class="ranking-champion-copy">
          <span>{{ $rewards[0]['rank'] }} · 冠军大奖</span>
          <strong>{{ $rewards[0]['prize'] }}</strong>
          <small>登顶最终总进度榜</small>
        </div>
        <img src="{{ asset($rewards[0]['asset']) }}" alt="{{ $rewards[0]['prize'] }} 奖品图" width="240" height="240">
      </article>

      <div class="ranking-cash-grid" aria-label="第 2 名至第 5 名奖励">
        @foreach(array_slice($rewards, 1, 4) as $reward)
          <article>
            <img src="{{ asset($reward['asset']) }}" alt="" width="52" height="52" aria-hidden="true">
            <span>{{ $reward['rank'] }}</span>
            <strong>{{ $reward['prize'] }}</strong>
          </article>
        @endforeach
      </div>

      <div class="ranking-group-reward">
        <span class="ranking-coin-stack" aria-hidden="true">
          <img src="{{ asset($rewards[5]['asset']) }}" alt="" width="52" height="52">
          <img src="{{ asset($rewards[5]['asset']) }}" alt="" width="52" height="52">
        </span>
        <span><b>{{ $rewards[5]['rank'] }}</b><small>现金奖励</small></span>
        <strong>{{ $rewards[5]['prize'] }}</strong>
      </div>
    </div>

    @if($variant === 'full')
      <p class="ranking-reward-scope"><b>奖励榜单：</b>以下奖励仅依据最终总进度榜，今日活跃榜和邀请榜不参与本奖励排名。</p>
    @endif
    <p class="ranking-reward-note">奖励以活动结束后的最终总进度榜及风控审核结果为准。第 11～20 名展示排名，无排名奖励。</p>
  </section>
@endif
