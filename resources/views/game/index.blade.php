@extends('layouts.app')
@section('content')

<div class="dashboard">
  <section class="game-area">
    <div class="stats">
      <div class="stat-card">
        <b class="stat-value" id="chance">{{ $state->chance_balance }}</b>
        <span class="stat-label">剩余机会</span>
      </div>
      <div class="stat-card">
        <b class="stat-value laps" id="lap">{{ $state->completed_laps }}</b>
        <span class="stat-label">完成圈数</span>
      </div>
      <div class="stat-card">
        <b class="stat-value position" id="position">{{ $state->current_position + 1 }}</b>
        <span class="stat-label">当前格子</span>
      </div>
      <div class="stat-card">
        <b class="stat-value">{{ auth()->user()->vip_level }}</b>
        <span class="stat-label">VIP 等级</span>
      </div>
    </div>

    <div class="board-wrapper">
      <div class="board-caption"><span>FORTUNE CIRCUIT</span><div class="board-caption-actions"><button type="button" data-open-legend>格子图例 ?</button><button type="button" id="soundToggle" aria-pressed="false" aria-label="关闭掷骰音效"><span>🔊</span><small>音效开</small></button></div><span>36 STEPS · LIVE SEASON</span></div>
      <div class="board board-square" id="board">
        @foreach($cells as $cell)
          @php
            $pos = $cell->position;
            if ($pos <= 9) {
              $row = 1; $col = $pos + 1;
              $arrow = '→'; $dir = 'dir-right';
            } elseif ($pos <= 18) {
              $row = $pos - 8; $col = 10;
              $arrow = '↓'; $dir = 'dir-down';
            } elseif ($pos <= 27) {
              $row = 10; $col = 28 - $pos;
              $arrow = '←'; $dir = 'dir-left';
            } else {
              $row = 37 - $pos; $col = 1;
              $arrow = '↑'; $dir = 'dir-up';
            }
          @endphp
          <div
            class="cell type-{{ $cell->type }} category-{{ $cell->category }} {{ $state->current_position === $cell->position ? 'active' : '' }}"
            data-position="{{ $cell->position }}"
            data-type="{{ $cell->type }}"
            data-category="{{ $cell->category }}"
            data-label="{{ $cell->label }}"
            data-description="{{ $cell->description ?: ($cell->category==='safe'?'安全格：本格不会触发奖励或负面事件。':'落地后立即触发对应效果。') }}"
            data-unlocked="{{ $unlockedLandmarks->has($cell->id) ? '1' : '0' }}"
            data-visits="{{ $unlockedLandmarks->get($cell->id,0) }}"
            style="grid-row:{{ $row }};grid-column:{{ $col }}"
            title="第{{ $cell->position + 1 }}步 {{ $cell->label }}"
            role="button"
            tabindex="0"
            aria-label="第{{ $cell->position + 1 }}格，{{ $cell->label }}，点击查看效果"
          >
            <span class="cell-pos">{{ $cell->position + 1 }}</span>
            <span class="cell-arrow {{ $dir }}">{{ $arrow }}</span>
            <i class="cell-icon">{{ $cell->icon }}</i>
            <small class="cell-label">{{ $cell->label }}</small>
            @if($cell->category === 'landmark')
              <span class="landmark-badge">地标</span>
            @endif
            @if($state->current_position === $cell->position)
              <span class="current-position-aura" aria-hidden="true"></span>
              <em class="piece">{{ $skinIcon }}</em>
            @endif
          </div>
        @endforeach

        <div class="board-center">
          <div class="center-brandmark"><span>LUCKY JUMP</span><small>SEASON 01 · FORTUNE CIRCUIT</small></div>
          <div class="center-statusline"><span><i></i> READY</span><b>剩余 <em id="centerChance">{{ $state->chance_balance }}</em> 次 · 第 <em id="centerPosition">{{ $state->current_position + 1 }}</em> 格</b></div>
          <div class="dice-orbit" aria-hidden="true"><span class="orbit-vip"><b>👑</b><small>VIP</small></span><span class="orbit-cash"><b>💎</b><small>USDT</small></span><span class="orbit-energy"><b>🔋</b><small>能量</small></span><span class="orbit-landmark"><b>📍</b><small>地标</small></span></div>
          <div class="center-action" id="diceConsole">
            <button id="moveButton" class="dice-trigger" data-url="{{ route('game.move') }}" data-frozen="{{ $state->is_frozen ? '1' : '0' }}" aria-label="{{ $state->is_frozen ? '点击解除冰冻' : '点击骰子掷出好运' }}">
              <div class="dice-stage" id="diceStage" aria-hidden="true">
                <div class="dice-float-shell">
                  <div class="dice-cube face-1" id="diceCube">
                    <span class="die-face die-face-1"><i></i></span>
                    <span class="die-face die-face-2"><i></i><i></i></span>
                    <span class="die-face die-face-3"><i></i><i></i><i></i></span>
                    <span class="die-face die-face-4"><i></i><i></i><i></i><i></i></span>
                    <span class="die-face die-face-5"><i></i><i></i><i></i><i></i><i></i></span>
                    <span class="die-face die-face-6"><i></i><i></i><i></i><i></i><i></i><i></i></span>
                  </div>
                </div>
                <span class="dice-shadow"></span>
              </div>
              <span class="dice-prompt"><b>{{ $state->is_frozen ? '点击解冻' : '点击骰子' }}</b><small>{{ $state->is_frozen ? '消耗 1 次机会' : '掷出好运' }}</small></span>
            </button>
            <div class="roll-feedback">
              <div class="roll-result" id="rollResult" aria-live="polite">
                <span>{{ $state->is_frozen ? '当前状态' : '等待投掷' }}</span>
                <div><b id="rollResultValue">—</b><small id="rollResultUnit">点</small></div>
              </div>
            </div>
          </div>
          <div class="event-rail" id="event">{{ $state->is_frozen ? '🧊 已被冰冻，需要一次机会解冻' : '好运正在前方，点击中央骰子出发' }}</div>
          <div class="center-rewards"><span>本轮惊喜</span><b>👑 VIP +1</b><b>💎 USDT</b><b>🔋 能量电池</b></div>
        </div>
      </div>
    </div>
  </section>

  <aside class="side-panel">
    <a class="center-launcher" href="{{ route('experience.center') }}"><div><span>NEW EXPERIENCE</span><b>进入幸运中心</b><small>任务 · 宝箱 · 道具 · 成就 · 领奖</small></div><em>✦</em>@if($unreadMessages)<i>{{ $unreadMessages }}</i>@endif</a>
    <div class="panel">
      <h2>今日任务</h2>
      <div class="task">
        <div class="task-info"><b>📅 连续签到</b><p>普通 5 次，连续第 7 天得 10 次</p></div>
        <div class="task-action">
          @if($checkedIn)
            <span class="done">已签到</span>
          @else
            <form method="post" action="{{ route('game.checkin') }}">@csrf<button>签到</button></form>
          @endif
        </div>
      </div>
      <div class="task">
        <div class="task-info"><b>💳 充值奖励</b><p>每累计 10 USDT 获得 10 次</p></div>
        <div class="task-action">后台记账</div>
      </div>
      <div class="task">
        <div class="task-info"><b>👥 邀请好友</b><p>每位有效好友获得 5 次</p></div>
        <div class="task-actions"><button type="button" class="task-record-trigger" data-task-records="invite">邀请记录</button><button type="button" class="task-action" data-copy="{{ route('register', ['invite' => auth()->user()->invite_code]) }}">复制链接</button></div>
      </div>
      <div class="task">
        <div class="task-info"><b>🎁 好友首充达标</b><p>首次满 10 USDT 获得 10 次</p></div>
        <div class="task-actions"><span class="task-count">{{ $qualifiedInvites }} 人</span><button type="button" class="task-record-trigger" data-task-records="friend_recharge">达标记录</button></div>
      </div>
    </div>

    <div class="panel" id="leaderboard">
      <h2>🏆 实时排行榜 TOP 20</h2>
      <x-ranking-rewards variant="compact" />
      <ol class="rank-list">
        @forelse($leaderboard as $rank => $item)
          <li class="{{ $item->user_id === auth()->id() ? 'me' : '' }}">
            <span class="rank-num">{{ $rank + 1 }}</span>
            <span class="rank-name">{{ mb_substr($item->name, 0, 1) }}***</span>
            <span class="rank-score">{{ $item->completed_laps }}圈 {{ $item->current_position + 1 }}格</span>
          </li>
        @empty
          <li style="color:var(--text-muted);padding:20px;text-align:center;">暂无数据</li>
        @endforelse
      </ol>
    </div>
  </aside>
</div>

<div class="task-record-dialog" id="taskRewardDialog" role="dialog" aria-modal="true" aria-labelledby="taskRewardTitle" data-url="{{ route('game.records.task-rewards') }}" hidden>
  <div class="task-record-backdrop" data-task-record-close></div>
  <section class="task-record-sheet" tabindex="-1">
    <header class="task-record-header">
      <div><span data-task-record-kicker>奖励明细</span><h2 id="taskRewardTitle" data-task-record-title>邀请记录</h2><p data-task-record-description>查看好友通过邀请加入后到账的机会</p></div>
      <button type="button" class="task-record-close" data-task-record-close aria-label="关闭记录弹框">×</button>
    </header>
    <div class="task-record-status" data-task-record-status aria-live="polite">正在加载记录…</div>
    <ol class="task-record-list" data-task-record-list aria-label="任务奖励记录"></ol>
    <div class="task-record-footer">
      <button type="button" class="task-record-retry" data-task-record-retry hidden>重新加载</button>
      <button type="button" class="task-record-more" data-task-record-more hidden>加载更多</button>
    </div>
  </section>
</div>

<section class="records">
  <details open>
    <summary>机会明细</summary>
    <div class="table-wrap"><table>
      <thead><tr><th>时间</th><th>来源</th><th>变化</th><th>余额</th></tr></thead>
      <tbody>
        @forelse($transactions as $row)
          <tr class="chance-record-row" data-record-id="{{ $row->id }}"><td>{{ $row->created_at }}</td><td>{{ $row->remark }}</td><td class="{{ $row->amount > 0 ? 'plus' : 'minus' }}">{{ $row->amount > 0 ? '+' : '' }}{{ $row->amount }}</td><td>{{ $row->balance_after }}</td></tr>
        @empty
          <tr><td colspan="4">暂无记录</td></tr>
        @endforelse
      </tbody>
    </table></div>
    @if($transactions->isNotEmpty())
      <div class="record-loader" data-record-type="chance" data-url="{{ route('game.records.chances') }}" data-cursor="{{ $transactionCursor }}" data-has-more="{{ $hasMoreTransactions ? '1' : '0' }}" aria-live="polite">
        <button type="button" @disabled(!$hasMoreTransactions)>{{ $hasMoreTransactions ? '加载更多' : '已加载全部' }}</button>
      </div>
    @endif
  </details>
  <details open>
    <summary>中奖列表</summary>
    <div class="table-wrap"><table>
      <thead><tr><th>时间</th><th>奖品</th><th>状态</th></tr></thead>
      <tbody>
        @forelse($winnings as $row)
          <tr class="winning-record-row" data-record-id="{{ $row->id }}"><td>{{ $row->created_at }}</td><td>{{ $row->prize_name }}</td><td>{{ $row->status === 'issued' ? '已发放' : '待发放' }}</td></tr>
        @empty
          <tr><td colspan="3">暂无中奖记录</td></tr>
        @endforelse
      </tbody>
    </table></div>
    @if($winnings->isNotEmpty())
      <div class="record-loader" data-record-type="winning" data-url="{{ route('game.records.winnings') }}" data-cursor="{{ $winningCursor }}" data-has-more="{{ $hasMoreWinnings ? '1' : '0' }}" aria-live="polite">
        <button type="button" @disabled(!$hasMoreWinnings)>{{ $hasMoreWinnings ? '加载更多' : '已加载全部' }}</button>
      </div>
    @endif
  </details>
</section>

<div class="cell-inspector" id="cellInspector" hidden><button type="button" class="inspector-close">×</button><span class="inspector-type"></span><div class="inspector-main"><b class="inspector-icon"></b><div><h3></h3><p></p></div></div><div class="inspector-status"></div><a href="{{ route('help.index') }}#landmarks">查看完整玩法与地标图鉴 →</a></div>
<div class="legend-popover" id="boardLegend" role="dialog" aria-modal="true" aria-labelledby="legendTitle" hidden><div><b id="legendTitle">棋盘图例</b><button type="button" aria-label="关闭棋盘图例">×</button></div><ul><li><i class="legend-safe"></i><span>安全格</span><small>平稳通行，无事件</small></li><li><i class="legend-landmark"></i><span>地标格</span><small>收集印章与轻量增益</small></li><li><i class="legend-boost"></i><span>增益格</span><small>前进或额外机会</small></li><li><i class="legend-risk"></i><span>风险格</span><small>炸弹、后退或冰冻</small></li><li><i class="legend-reward"></i><span>奖励格</span><small>奖品、VIP 或电池</small></li></ul><a href="{{ route('help.index') }}">打开玩法说明与 FAQ</a></div>

@if(!$state->tutorial_seen_at)
<div class="tutorial-overlay" id="tutorialOverlay"><div class="tutorial-card"><span class="tutorial-step">01 / 05</span><div class="tutorial-icon">🎲</div><h2>欢迎来到幸运跳棋</h2><p>完成签到、任务、充值和邀请，获得跳棋机会。</p><div class="tutorial-dots"><i class="active"></i><i></i><i></i><i></i><i></i></div><div class="tutorial-actions"><form method="post" action="{{ route('help.tutorial') }}">@csrf<button class="tutorial-skip">跳过</button></form><button type="button" class="tutorial-next">下一步</button></div></div></div>
@endif

{{-- Unified roll feedback modal --}}
<template id="rollFeedbackModal">
  <div class="modal-overlay roll-feedback-overlay" id="rollFeedbackOverlay" role="dialog" aria-modal="true" aria-labelledby="prizeTitle">
    <div class="modal-confetti" id="confetti"></div>
    <div class="modal-card roll-feedback-card">
      <div class="prize-rays"></div><div class="prize-halo"></div>
      <span class="prize-kicker" id="feedbackKicker">本次结果</span>
      <span class="prize-emoji" id="prizeEmoji">🎉</span>
      <div class="prize-title" id="prizeTitle">本次结果</div>
      <div class="prize-name" id="prizeName"></div>
      <div class="prize-detail" id="prizeDetail"></div>
      <div class="feedback-destination" id="feedbackDestination" data-feedback-destination hidden><span>最终停在</span><b></b></div>
      <section class="feedback-settlement" data-feedback-settlement hidden><span>本次结算</span><ul id="feedbackItems" data-feedback-items></ul></section>
      <div class="feedback-balances" id="feedbackBalances" data-feedback-balances hidden></div>
      <button type="button" class="prize-btn" id="prizeClose">继续前进</button>
    </div>
  </div>
</template>

@endsection

@push('scripts')
<script>window.gameConfig={position:{{ $state->current_position }},chance:{{ $state->chance_balance }},skin:@json($skinIcon)};</script>
@endpush
