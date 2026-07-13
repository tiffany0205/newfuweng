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
        <b class="stat-value position" id="position">{{ $state->current_position }}</b>
        <span class="stat-label">当前格子</span>
      </div>
      <div class="stat-card">
        <b class="stat-value">{{ auth()->user()->vip_level }}</b>
        <span class="stat-label">VIP 等级</span>
      </div>
    </div>

    <div class="board-wrapper">
      <div class="board-caption"><span>FORTUNE CIRCUIT</span><button type="button" data-open-legend>格子图例 ?</button><span>36 STEPS · LIVE SEASON</span></div>
      <div class="board" id="board">
        @foreach($cells as $cell)
          @php
            $pos = $cell->position;
            if ($pos <= 10) {
              $row = 1; $col = $pos + 1;
              $arrow = '→'; $dir = 'dir-right';
            } elseif ($pos <= 18) {
              $row = $pos - 9; $col = 11;
              $arrow = '↓'; $dir = 'dir-down';
            } elseif ($pos <= 28) {
              $row = 9; $col = 29 - $pos;
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
            @if($state->current_position === $cell->position)
              <em class="piece">{{ $skinIcon }}</em>
            @endif
          </div>
        @endforeach

        <div class="board-center">
          <div class="center-orbit orbit-one"></div><div class="center-orbit orbit-two"></div>
          <div class="center-copy"><span class="season-label">SEASON 01</span><div class="game-title">LUCKY<br><i>JUMP</i></div><p>ROLL · MOVE · WIN</p></div>
          <div class="command-card">
            <div class="event" id="event">
              {{ $state->is_frozen ? '🧊 已被冰冻，需要一次机会解冻' : '准备好开启下一步好运了吗？' }}
            </div>
            <button id="moveButton" class="dice-btn" data-url="{{ route('game.move') }}">
              <span class="dice-icon" id="dice">✦</span>
              <strong>{{ $state->is_frozen ? '立即解冻' : '掷出好运' }}</strong>
            </button>
            <span class="hint">消耗 1 次机会 · 服务端随机</span>
          </div>
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
        <button class="task-action" data-copy="{{ route('register', ['invite' => auth()->user()->invite_code]) }}">复制链接</button>
      </div>
      <div class="task">
        <div class="task-info"><b>🎁 好友首充达标</b><p>首次满 10 USDT 获得 10 次</p></div>
        <div class="task-action">{{ $invites }} 人</div>
      </div>
    </div>

    <div class="panel">
      <h2>🏆 实时排行榜 TOP 20</h2>
      <ol class="rank-list">
        @forelse($leaderboard as $rank => $item)
          <li class="{{ $item->user_id === auth()->id() ? 'me' : '' }}">
            <span class="rank-num">{{ $rank + 1 }}</span>
            <span class="rank-name">{{ mb_substr($item->name, 0, 1) }}***</span>
            <span class="rank-score">{{ $item->completed_laps }}圈 {{ $item->current_position }}格</span>
          </li>
        @empty
          <li style="color:var(--text-muted);padding:20px;text-align:center;">暂无数据</li>
        @endforelse
      </ol>
    </div>
  </aside>
</div>

<section class="records">
  <details open>
    <summary>机会明细</summary>
    <div class="table-wrap"><table>
      <thead><tr><th>时间</th><th>来源</th><th>变化</th><th>余额</th></tr></thead>
      <tbody>
        @forelse($transactions as $row)
          <tr><td>{{ $row->created_at }}</td><td>{{ $row->remark }}</td><td class="{{ $row->amount > 0 ? 'plus' : 'minus' }}">{{ $row->amount > 0 ? '+' : '' }}{{ $row->amount }}</td><td>{{ $row->balance_after }}</td></tr>
        @empty
          <tr><td colspan="4">暂无记录</td></tr>
        @endforelse
      </tbody>
    </table></div>
  </details>
  <details>
    <summary>中奖列表</summary>
    <div class="table-wrap"><table>
      <thead><tr><th>时间</th><th>奖品</th><th>状态</th></tr></thead>
      <tbody>
        @forelse($winnings as $row)
          <tr><td>{{ $row->created_at }}</td><td>{{ $row->prize_name }}</td><td>{{ $row->status === 'issued' ? '已发放' : '待发放' }}</td></tr>
        @empty
          <tr><td colspan="3">暂无中奖记录</td></tr>
        @endforelse
      </tbody>
    </table></div>
  </details>
</section>

<div class="cell-inspector" id="cellInspector" hidden><button type="button" class="inspector-close">×</button><span class="inspector-type"></span><div class="inspector-main"><b class="inspector-icon"></b><div><h3></h3><p></p></div></div><div class="inspector-status"></div><a href="{{ route('help.index') }}#landmarks">查看完整玩法与地标图鉴 →</a></div>
<div class="legend-popover" id="boardLegend" role="dialog" aria-modal="true" aria-labelledby="legendTitle" hidden><div><b id="legendTitle">棋盘图例</b><button type="button" aria-label="关闭棋盘图例">×</button></div><ul><li><i class="legend-safe"></i><span>安全格</span><small>平稳通行，无事件</small></li><li><i class="legend-landmark"></i><span>地标格</span><small>收集印章与轻量增益</small></li><li><i class="legend-boost"></i><span>增益格</span><small>前进或额外机会</small></li><li><i class="legend-risk"></i><span>风险格</span><small>炸弹、后退或冰冻</small></li><li><i class="legend-reward"></i><span>奖励格</span><small>奖品、VIP 或电池</small></li></ul><a href="{{ route('help.index') }}">打开玩法说明与 FAQ</a></div>

@if(!$state->tutorial_seen_at)
<div class="tutorial-overlay" id="tutorialOverlay"><div class="tutorial-card"><span class="tutorial-step">01 / 05</span><div class="tutorial-icon">🎲</div><h2>欢迎来到幸运跳棋</h2><p>完成签到、任务、充值和邀请，获得跳棋机会。</p><div class="tutorial-dots"><i class="active"></i><i></i><i></i><i></i><i></i></div><div class="tutorial-actions"><form method="post" action="{{ route('help.tutorial') }}">@csrf<button class="tutorial-skip">跳过</button></form><button type="button" class="tutorial-next">下一步</button></div></div></div>
@endif

{{-- Prize Modal Template --}}
<template id="prizeModal">
  <div class="modal-overlay" id="prizeOverlay">
    <div class="modal-confetti" id="confetti"></div>
    <div class="modal-card">
      <div class="prize-rays"></div><div class="prize-halo"></div>
      <span class="prize-kicker">LUCKY MOMENT</span>
      <span class="prize-emoji" id="prizeEmoji">🎉</span>
      <div class="prize-title" id="prizeTitle">恭喜中奖</div>
      <div class="prize-name" id="prizeName"></div>
      <div class="prize-detail" id="prizeDetail"></div>
      <button class="prize-btn" id="prizeClose">开心收下</button>
    </div>
  </div>
</template>

@endsection

@push('scripts')
<script>window.gameConfig={position:{{ $state->current_position }},chance:{{ $state->chance_balance }},skin:@json($skinIcon)};</script>
@endpush
