@extends('layouts.app')
@section('content')

<div class="dashboard">
  <section class="game-area">
    {{-- Stats Row --}}
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

    {{-- Board --}}
    <div class="board-wrapper">
      <div class="board" id="board">
        {{-- 4 Corners --}}
        <div class="corner" style="grid-row:1;grid-column:1;">↘</div>
        <div class="corner" style="grid-row:1;grid-column:11;">↙</div>
        <div class="corner" style="grid-row:11;grid-column:11;">↖</div>
        <div class="corner" style="grid-row:11;grid-column:1;">↗</div>

        {{-- Cells: perimeter loop on 11x11 grid --}}
        @foreach($cells as $cell)
          @php
            $pos = $cell->position;
            if ($pos <= 8) {
              $row = 1;
              $col = $pos + 2;
            } elseif ($pos <= 17) {
              $row = $pos - 7;
              $col = 11;
            } elseif ($pos <= 26) {
              $row = 11;
              $col = 28 - $pos;
            } else {
              $row = 37 - $pos;
              $col = 1;
            }
          @endphp
          <div
            class="cell type-{{ $cell->type }} {{ $state->current_position === $cell->position ? 'active' : '' }}"
            data-position="{{ $cell->position }}"
            style="grid-row:{{ $row }};grid-column:{{ $col }};--cell-color:{{ $cell->color }}"
            title="第{{ $cell->position }}格 {{ $cell->label }}"
          >
            <i class="cell-icon">{{ $cell->icon }}</i>
            <small class="cell-label">{{ $cell->label }}</small>
            @if($state->current_position === $cell->position)
              <em class="piece">🚗</em>
            @endif
          </div>
        @endforeach

        {{-- Center Dice Area --}}
        <div class="board-center">
          <div class="game-title">LUCKY JUMP</div>
          <div class="event" id="event">
            {{ $state->is_frozen ? '🧊 已被冰冻，需要一次机会解冻' : '点击骰子开始冒险' }}
          </div>
          <button
            id="moveButton"
            class="dice-btn"
            data-url="{{ route('game.move') }}"
          >
            <span class="dice-icon" id="dice">🎲</span>
            <strong>{{ $state->is_frozen ? '立即解冻' : '立即跳棋' }}</strong>
          </button>
          <span class="hint">每次消耗 1 次机会</span>
        </div>
      </div>
    </div>
  </section>

  {{-- Right Sidebar --}}
  <aside class="side-panel">
    {{-- Tasks --}}
    <div class="panel">
      <h2>今日任务</h2>

      <div class="task">
        <div class="task-info">
          <b>📅 连续签到</b>
          <p>普通 5 次，连续第 7 天得 10 次</p>
        </div>
        <div class="task-action">
          @if($checkedIn)
            <span class="done">已签到</span>
          @else
            <form method="post" action="{{ route('game.checkin') }}">
              @csrf
              <button>签到</button>
            </form>
          @endif
        </div>
      </div>

      <div class="task">
        <div class="task-info">
          <b>💳 充值奖励</b>
          <p>每累计 10 USDT 获得 10 次</p>
        </div>
        <div class="task-action">后台记账</div>
      </div>

      <div class="task">
        <div class="task-info">
          <b>👥 邀请好友</b>
          <p>每位有效好友获得 5 次</p>
        </div>
        <button class="task-action" data-copy="{{ route('register', ['invite' => auth()->user()->invite_code]) }}">
          复制链接
        </button>
      </div>

      <div class="task">
        <div class="task-info">
          <b>🎁 好友首充达标</b>
          <p>首次满 10 USDT 获得 10 次</p>
        </div>
        <div class="task-action">{{ $invites }} 人</div>
      </div>
    </div>

    {{-- Leaderboard --}}
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

{{-- Records --}}
<section class="records">
  <details open>
    <summary>机会明细</summary>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>时间</th><th>来源</th><th>变化</th><th>余额</th></tr>
        </thead>
        <tbody>
          @forelse($transactions as $row)
            <tr>
              <td>{{ $row->created_at }}</td>
              <td>{{ $row->remark }}</td>
              <td class="{{ $row->amount > 0 ? 'plus' : 'minus' }}">{{ $row->amount > 0 ? '+' : '' }}{{ $row->amount }}</td>
              <td>{{ $row->balance_after }}</td>
            </tr>
          @empty
            <tr><td colspan="4">暂无记录</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </details>

  <details>
    <summary>中奖列表</summary>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>时间</th><th>奖品</th><th>状态</th></tr>
        </thead>
        <tbody>
          @forelse($winnings as $row)
            <tr>
              <td>{{ $row->created_at }}</td>
              <td>{{ $row->prize_name }}</td>
              <td>{{ $row->status === 'issued' ? '已发放' : '待发放' }}</td>
            </tr>
          @empty
            <tr><td colspan="3">暂无中奖记录</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </details>
</section>

@endsection

@push('scripts')
<script>window.gameConfig={position:{{ $state->current_position }},chance:{{ $state->chance_balance }}};</script>
@endpush
